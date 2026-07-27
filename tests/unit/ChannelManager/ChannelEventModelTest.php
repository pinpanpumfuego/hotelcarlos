<?php

declare(strict_types=1);

namespace Tests\Unit\ChannelManager;

use App\Models\ChannelEventModel;
use App\Models\ChannelSyncLogModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Idempotencia del flujo entrante y depuración de payloads.
 *
 * Lo que se comprueba aquí es lo que impide que una reserva se cree dos veces
 * cuando el mismo aviso llega repetido — y llega repetido: los proveedores
 * reintentan cuando no reciben el 200 a tiempo.
 *
 * Usa un canal inventado (`pruebas`) para no tocar datos reales, y borra lo
 * suyo al terminar.
 *
 * @internal
 */
final class ChannelEventModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    // Migra la base de pruebas (`hotelcarlos_test`) la primera vez. No se
    // refresca entre pruebas: cada una limpia lo suyo, y volver a montar el
    // esquema entero en cada método haría la suite insoportablemente lenta.
    protected $migrate     = true;
    protected $refresh     = false;

    // `null` = migrar **todos** los espacios de nombres. Por defecto
    // CodeIgniter solo migra `Tests\Support`, que no tiene nuestras tablas: sin
    // esto la base de pruebas se queda vacía y todo falla con «la tabla no
    // existe», que despista mucho para lo tonto que es.
    protected $namespace   = null;

    private const CANAL    = 'pruebas';

    private ChannelEventModel $eventos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventos = new ChannelEventModel();
        $this->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        db_connect()->table('channel_events')->where('channel', self::CANAL)->delete();
        db_connect()->table('channel_sync_logs')->where('channel', self::CANAL)->delete();
    }

    // ── Idempotencia ────────────────────────────────────────────────────

    public function testElMismoAvisoDosVecesSoloSeGuardaUnaVez(): void
    {
        $payload = ['id' => 55501, 'status' => 'confirmed', 'arrival' => '2026-09-01'];

        $primero = $this->eventos->record(self::CANAL, $payload);
        $segundo = $this->eventos->record(self::CANAL, $payload);

        $this->assertFalse($primero['repetido']);
        $this->assertTrue($segundo['repetido'], 'El aviso repetido tiene que reconocerse como tal.');
        $this->assertSame($primero['id'], $segundo['id'], 'Debe devolver el mismo registro, no uno nuevo.');

        $this->assertSame(1, $this->eventos->where('channel', self::CANAL)->countAllResults());
    }

    public function testUnAvisoConContenidoDistintoSiEsUnoNuevo(): void
    {
        // Misma reserva, pero ahora cancelada: es otro suceso y hay que verlo.
        $this->eventos->record(self::CANAL, ['id' => 55501, 'status' => 'confirmed']);
        $segundo = $this->eventos->record(self::CANAL, ['id' => 55501, 'status' => 'cancelled']);

        $this->assertFalse($segundo['repetido']);
        $this->assertSame(2, $this->eventos->where('channel', self::CANAL)->countAllResults());
    }

    public function testElHashDistingueElTipoDeSuceso(): void
    {
        $payload = ['id' => 55502];

        $this->eventos->record(self::CANAL, $payload, 'booking');
        $otro = $this->eventos->record(self::CANAL, $payload, 'availability');

        $this->assertFalse($otro['repetido'], 'El tipo de suceso forma parte del hash.');
    }

    // ── Reparto entre procesos ──────────────────────────────────────────

    public function testDosProcesosNoSeLlevanElMismoAviso(): void
    {
        foreach ([1, 2, 3] as $n) {
            $this->eventos->record(self::CANAL, ['id' => 60000 + $n]);
        }

        $primero = $this->eventos->claim(self::CANAL, 'corr-A', 2);
        $segundo = $this->eventos->claim(self::CANAL, 'corr-B', 5);

        $this->assertCount(2, $primero);
        $this->assertCount(1, $segundo, 'El segundo proceso solo debe llevarse lo que quedaba.');

        $idsA = array_column($primero, 'id');
        $idsB = array_column($segundo, 'id');
        $this->assertSame([], array_intersect($idsA, $idsB), 'Ningún aviso puede ir a los dos procesos.');
    }

    public function testAlReclamarSeCuentaElIntento(): void
    {
        $this->eventos->record(self::CANAL, ['id' => 61001]);

        $lote = $this->eventos->claim(self::CANAL, 'corr-C', 5);

        $this->assertSame(1, (int) $lote[0]['attempts']);
    }

    // ── Fallos y reintentos ─────────────────────────────────────────────

    public function testUnFalloReintentableVuelveALaCola(): void
    {
        $id = $this->eventos->record(self::CANAL, ['id' => 62001])['id'];
        $this->eventos->claim(self::CANAL, 'corr-D', 5);

        $this->eventos->markFailed((int) $id, 'Tiempo de espera agotado', true);

        $this->assertSame('pending', $this->eventos->find($id)['status']);
    }

    public function testUnFalloNoReintentableSeQuedaFallido(): void
    {
        // Una cabaña sin mapear no aparece por repetir la operación: reintentarlo
        // solo llenaría la cola de trabajo que nunca va a salir bien.
        $id = $this->eventos->record(self::CANAL, ['id' => 62002])['id'];
        $this->eventos->claim(self::CANAL, 'corr-E', 5);

        $this->eventos->markFailed((int) $id, 'Cabaña sin mapear', false);

        $this->assertSame('failed', $this->eventos->find($id)['status']);
    }

    public function testTrasDemasiadosIntentosDejaDeReintentarse(): void
    {
        $id = $this->eventos->record(self::CANAL, ['id' => 62003])['id'];
        $this->eventos->update($id, ['attempts' => ChannelEventModel::MAX_INTENTOS]);

        $this->eventos->markFailed((int) $id, 'Sigue fallando', true);

        $this->assertSame('failed', $this->eventos->find($id)['status']);
    }

    public function testLoQueSeQuedoColgadoVuelveALaCola(): void
    {
        // El proceso murió a mitad y el aviso se quedó en `processing`. Sin este
        // rescate se quedaría ahí para siempre, sin procesar y sin avisar.
        $id = $this->eventos->record(self::CANAL, ['id' => 63001])['id'];
        $this->eventos->update($id, ['status' => 'processing']);
        db_connect()->table('channel_events')
            ->where('id', $id)
            ->update(['updated_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        $rescatados = $this->eventos->rescueStale(15);

        $this->assertGreaterThanOrEqual(1, $rescatados);
        $this->assertSame('pending', $this->eventos->find($id)['status']);
    }

    public function testElBotonReintentarDevuelveLosFallidosALaCola(): void
    {
        $id = $this->eventos->record(self::CANAL, ['id' => 64001])['id'];
        $this->eventos->update($id, ['status' => 'failed', 'attempts' => 8]);

        $this->eventos->retryFailed(self::CANAL);

        $fila = $this->eventos->find($id);
        $this->assertSame('pending', $fila['status']);
        $this->assertSame(0, (int) $fila['attempts'], 'Reintentar a mano reinicia la cuenta de intentos.');
    }

    // ── Depuración de payloads ──────────────────────────────────────────

    public function testNoSeGuardanTokensNiTarjetas(): void
    {
        $limpio = ChannelSyncLogModel::scrub([
            'roomId'      => 7788,
            'token'       => 'abc123secreto',
            'refreshToken' => 'zzz',
            'guest'       => [
                'name'       => 'Ana',
                'cardNumber' => '4111111111111111',
                'cvv'        => '123',
            ],
        ]);

        $this->assertSame(7788, $limpio['roomId'], 'Lo que no es sensible se conserva.');
        $this->assertSame('***', $limpio['token']);
        $this->assertSame('***', $limpio['refreshToken']);
        $this->assertSame('Ana', $limpio['guest']['name']);
        $this->assertSame('***', $limpio['guest']['cardNumber'], 'Booking manda tarjetas virtuales: eso no se guarda jamás.');
        $this->assertSame('***', $limpio['guest']['cvv']);
    }

    public function testLaDepuracionEntraEnListasAnidadas(): void
    {
        $limpio = ChannelSyncLogModel::scrub([
            'bookings' => [
                ['id' => 1, 'apiKey' => 'secreto'],
                ['id' => 2, 'apiKey' => 'otro'],
            ],
        ]);

        $this->assertSame('***', $limpio['bookings'][0]['apiKey']);
        $this->assertSame('***', $limpio['bookings'][1]['apiKey']);
        $this->assertSame(2, $limpio['bookings'][1]['id']);
    }

    // ── Cola de salida ──────────────────────────────────────────────────

    public function testUnEnvioEncoladoSaleComoPendiente(): void
    {
        $logs = new ChannelSyncLogModel();
        $logs->queue(self::CANAL, 'pushAvailability', 'unidad', '3', ['roomId' => 7788]);

        $pendientes = $logs->dueOutbound(self::CANAL);

        $this->assertCount(1, $pendientes);
        $this->assertSame('pushAvailability', $pendientes[0]['operation']);
    }

    public function testUnEnvioFallidoSeReprogramaMasTarde(): void
    {
        $logs = new ChannelSyncLogModel();
        $id   = $logs->start(self::CANAL, 'pushAvailability', 'outbound');

        $logs->fail($id, 'Tiempo de espera agotado', 504, true);

        $fila = $logs->find($id);
        $this->assertSame('pending', $fila['status']);
        $this->assertGreaterThan(date('Y-m-d H:i:s'), $fila['next_attempt_at'], 'No debe reintentarse de inmediato.');
    }

    public function testUnEnvioNoReintentableSeQuedaFallido(): void
    {
        $logs = new ChannelSyncLogModel();
        $id   = $logs->start(self::CANAL, 'pushAvailability', 'outbound');

        $logs->fail($id, 'La cabaña no está mapeada', 400, false);

        $this->assertSame('failed', $logs->find($id)['status']);
    }
}
