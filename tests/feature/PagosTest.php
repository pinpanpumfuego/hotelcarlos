<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfiguracionModel;
use App\Models\FolioModel;
use App\Models\PagoOnlineModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * El flujo de cobro completo.
 *
 * La prueba que justifica todo el módulo es una: **que nadie pueda regalarse
 * una estancia editando la URL de vuelta**. El navegador trae un id, y ese id
 * no vale nada hasta que la pasarela lo confirma.
 *
 * @internal
 */
final class PagosTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private PagoOnlineModel $pagos;
    private int $reservaId;
    private string $codigo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pagos = new PagoOnlineModel();
        $this->limpiar();

        // El freno de 10 intentos por minuto y por IP es de verdad: sin
        // vaciarlo, a partir de la décima prueba todas fallarían por él y el
        // error señalaría al sitio equivocado.
        foreach (['0.0.0.0', '127.0.0.1'] as $ip) {
            service('throttler')->remove('pago-' . md5($ip));
        }

        $db = db_connect();

        $db->table('huespedes')->insert([
            'nombre' => 'Pago', 'apellidos' => 'Prueba',
            'tipo_documento' => 'CC', 'num_documento' => 'PRUEBA-PAGO',
            'email' => 'pago.prueba@example.com',
        ]);
        $huespedId = $db->insertID();

        $this->codigo = 'PG' . random_int(10000, 99999);

        $db->table('reservas')->insert([
            'codigo' => $this->codigo, 'huesped_id' => $huespedId, 'unidad_id' => null,
            'fecha_entrada' => date('Y-m-d', strtotime('+10 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('+12 days')),
            'adultos' => 2, 'estado' => 'pendiente', 'total' => 600000,
        ]);
        $this->reservaId = (int) $db->insertID();

        (new ConfiguracionModel())->guardarPares([
            'wompi_ambiente'           => 'pruebas',
            'wompi_activo'             => '1',
            'wompi_llave_publica'      => 'pub_test_PRUEBAxxxxxxxxxxxxxxxxxxxx',
            'wompi_secreto_integridad' => 'test_integrity_PRUEBAxxxxxxxxxxxxx',
            'wompi_secreto_eventos'    => 'test_events_PRUEBAxxxxxxxxxxxxxxxx',
            'wompi_anticipo_pct'       => '0',
        ]);
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        (new ConfiguracionModel())->guardarPares(['wompi_activo' => '0']);
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $reservas = $db->table('reservas r')->select('r.id')
            ->join('huespedes h', 'h.id = r.huesped_id')
            ->where('h.num_documento', 'PRUEBA-PAGO')->get()->getResultArray();

        foreach ($reservas as $r) {
            $db->table('pagos_online')->where('concepto_tipo', 'reserva')->where('concepto_id', $r['id'])->delete();
            $db->table('folio_movimientos')->where('reserva_id', $r['id'])->delete();
            $db->table('reservas')->where('id', $r['id'])->delete();
        }
        $db->table('huespedes')->where('num_documento', 'PRUEBA-PAGO')->delete();
    }

    // ── Empezar el cobro ────────────────────────────────────────────────

    public function testPedirPagarLlevaALaPasarela(): void
    {
        $r = $this->get('pago/reserva/' . $this->codigo);

        $this->assertTrue($r->isRedirect());
        $this->assertStringStartsWith('https://checkout.wompi.co/p/', (string) $r->getRedirectUrl());
    }

    public function testElIntentoSeGuardaAntesDeMandarANadieAPagar(): void
    {
        // Si el huésped paga y se le cae el móvil antes de volver, el aviso
        // llega igual y tiene que encontrar a quién apuntárselo.
        $this->get('pago/reserva/' . $this->codigo);

        $intentos = $this->pagos->deConcepto('reserva', $this->reservaId);

        $this->assertCount(1, $intentos);
        $this->assertSame('creado', $intentos[0]['estado']);
        $this->assertEqualsWithDelta(600000, (float) $intentos[0]['valor'], 0.01);
    }

    public function testUnCodigoQueNoExisteNoCreaNada(): void
    {
        $this->get('pago/reserva/NOEXISTE')->assertSee('No encontramos esa reserva');

        $this->assertSame(0, $this->pagos->countAllResults());
    }

    public function testSeCobraSoloElAnticipoSiEstaConfigurado(): void
    {
        (new ConfiguracionModel())->guardarPares(['wompi_anticipo_pct' => '30']);

        $this->get('pago/reserva/' . $this->codigo);
        $intento = $this->pagos->deConcepto('reserva', $this->reservaId)[0];

        $this->assertEqualsWithDelta(180000, (float) $intento['valor'], 0.01, '30 % de 600.000');
    }

    public function testElEnlaceDeRecepcionCobraTodoElSaldoAunqueHayaAnticipo(): void
    {
        // El anticipo es para el motor de reservas. Si recepción manda el
        // enlace a mano y solo se pudiera cobrar el 30 %, el módulo no serviría
        // para cobrar la salida, que es cuando más falta hace.
        (new ConfiguracionModel())->guardarPares(['wompi_anticipo_pct' => '30']);

        $this->get('pago/reserva/' . $this->codigo . '/total');
        $intento = $this->pagos->deConcepto('reserva', $this->reservaId)[0];

        $this->assertEqualsWithDelta(600000, (float) $intento['valor'], 0.01);
    }

    public function testElEnlaceDeRecepcionIncluyeLosExtras(): void
    {
        // Minibar y cenas no estaban en el precio de la reserva, pero se deben.
        (new FolioModel())->insert([
            'reserva_id' => $this->reservaId, 'tipo' => 'cargo',
            'concepto' => 'Alojamiento (2 noches)', 'valor' => 600000,
        ]);
        (new FolioModel())->insert([
            'reserva_id' => $this->reservaId, 'tipo' => 'cargo',
            'concepto' => 'Cena', 'valor' => 80000,
        ]);

        $this->get('pago/reserva/' . $this->codigo . '/total');
        $intento = $this->pagos->deConcepto('reserva', $this->reservaId)[0];

        $this->assertEqualsWithDelta(680000, (float) $intento['valor'], 0.01);
    }

    public function testConElAnticipoCubiertoSeExplicaQueElRestoSePagaAlLlegar(): void
    {
        (new ConfiguracionModel())->guardarPares(['wompi_anticipo_pct' => '30']);
        (new FolioModel())->insert([
            'reserva_id' => $this->reservaId, 'tipo' => 'cargo',
            'concepto' => 'Alojamiento (2 noches)', 'valor' => 600000,
        ]);
        (new FolioModel())->insert([
            'reserva_id' => $this->reservaId, 'tipo' => 'pago',
            'concepto' => 'Anticipo', 'valor' => 180000, 'metodo' => 'wompi',
        ]);

        // Decirle «no debes nada» a quien aún debe el 70 % sería mentirle.
        $this->get('pago/reserva/' . $this->codigo)->assertSee('resto se abona a tu llegada');
    }

    public function testLoYaPagadoSeDescuentaDeLoQueQueda(): void
    {
        (new FolioModel())->insert([
            'reserva_id' => $this->reservaId, 'tipo' => 'pago',
            'concepto' => 'Adelanto en efectivo', 'valor' => 200000, 'metodo' => 'efectivo',
        ]);

        $this->get('pago/reserva/' . $this->codigo);
        $intento = $this->pagos->deConcepto('reserva', $this->reservaId)[0];

        $this->assertEqualsWithDelta(400000, (float) $intento['valor'], 0.01);
    }

    public function testSiNoQuedaNadaPorPagarNoSeCobra(): void
    {
        (new FolioModel())->insert([
            'reserva_id' => $this->reservaId, 'tipo' => 'pago',
            'concepto' => 'Pagado entero', 'valor' => 600000, 'metodo' => 'transferencia',
        ]);

        $this->get('pago/reserva/' . $this->codigo)->assertSee('no tiene nada pendiente');
        $this->assertSame(0, $this->pagos->countAllResults());
    }

    public function testConLaPasarelaApagadaNoSePuedePagar(): void
    {
        (new ConfiguracionModel())->guardarPares(['wompi_activo' => '0']);

        $this->get('pago/reserva/' . $this->codigo)->assertSee('no están disponibles');
    }

    // ── La vuelta: lo que más importa ───────────────────────────────────

    public function testVolverSinIdNoDaNadaPorPagado(): void
    {
        $this->get('pago/volver')->assertSee('No sabemos de qué pago vienes');

        $this->assertSame(0.0, $this->pagos->totalAprobado('reserva', $this->reservaId));
    }

    public function testUnIdInventadoNoRegalaLaEstancia(): void
    {
        // Ésta es la prueba que justifica el módulo entero. La consulta a la
        // pasarela falla (no hay red ni llaves reales), así que el sistema NO
        // puede dar el pago por bueno pase lo que pase.
        $this->get('pago/reserva/' . $this->codigo);

        $r = $this->get('pago/volver?id=01-INVENTADO-9999');

        $this->assertSame(0.0, $this->pagos->totalAprobado('reserva', $this->reservaId));
        $this->assertSame(
            'pendiente',
            db_connect()->table('reservas')->where('id', $this->reservaId)->get()->getRowArray()['estado'],
            'La reserva no se confirma con un id inventado.'
        );
        $this->assertSame(
            0,
            (new FolioModel())->where('reserva_id', $this->reservaId)->where('metodo', 'wompi')->countAllResults()
        );
    }

    // ── El aviso de la pasarela ─────────────────────────────────────────

    public function testUnAvisoSinFirmaSeRechazaCon401(): void
    {
        $r = $this->withBodyFormat('json')->post('pago/aviso', [
            'event' => 'transaction.updated',
            'data'  => ['transaction' => ['id' => '01-1', 'status' => 'APPROVED', 'reference' => 'REF']],
        ]);

        $r->assertStatus(401);
        $this->assertSame(0.0, $this->pagos->totalAprobado('reserva', $this->reservaId));
    }

    public function testUnAvisoConFirmaFalsaTampocoCuela(): void
    {
        $this->get('pago/reserva/' . $this->codigo);
        $referencia = $this->pagos->deConcepto('reserva', $this->reservaId)[0]['referencia'];

        $r = $this->withBodyFormat('json')->post('pago/aviso', [
            'event'     => 'transaction.updated',
            'timestamp' => '1690000000',
            'data'      => ['transaction' => ['id' => '01-9', 'status' => 'APPROVED', 'reference' => $referencia]],
            'signature' => [
                'properties' => ['transaction.id', 'transaction.status'],
                'checksum'   => str_repeat('f', 64),
            ],
        ]);

        $r->assertStatus(401);
        $this->assertSame(0.0, $this->pagos->totalAprobado('reserva', $this->reservaId));
    }

    public function testElAvisoNoPasaPorCsrf(): void
    {
        // Es máquina a máquina: no hay navegador que pueda llevar un token.
        // Si el CSRF lo cortara, devolvería 403 en vez de 401.
        $r = $this->withBodyFormat('json')->post('pago/aviso', ['event' => 'x']);

        $this->assertNotSame(403, $r->response()->getStatusCode());
    }

    // ── Idempotencia ────────────────────────────────────────────────────

    public function testUnPagoAprobadoSoloSeApuntaUnaVezEnLaCuenta(): void
    {
        // La pasarela puede mandar el mismo aviso hasta tres veces, y el
        // huésped puede recargar la página de vuelta.
        $this->get('pago/reserva/' . $this->codigo);
        $pago = $this->pagos->deConcepto('reserva', $this->reservaId)[0];

        // Se simula lo que hace el controlador al recibir una aprobación
        $this->pagos->anotarResultado((int) $pago['id'], [
            'id' => '01-APROBADA', 'status' => 'APPROVED', 'reference' => $pago['referencia'],
            'payment_method_type' => 'CARD',
        ]);

        $fresco = $this->pagos->find($pago['id']);
        $this->assertFalse($this->pagos->yaAplicado($fresco));

        $this->pagos->marcarAplicado((int) $pago['id']);

        $this->assertTrue($this->pagos->yaAplicado($this->pagos->find($pago['id'])));
    }

    public function testNoSePuedenGuardarDosPagosConLaMismaTransaccion(): void
    {
        $this->get('pago/reserva/' . $this->codigo);
        $pagos = $this->pagos->deConcepto('reserva', $this->reservaId);

        $this->pagos->anotarResultado((int) $pagos[0]['id'], [
            'id' => '01-UNICA', 'status' => 'APPROVED', 'reference' => $pagos[0]['referencia'],
        ]);

        // Un segundo intento con el mismo id de transacción choca contra el
        // índice único: es lo que impide cobrar dos veces lo mismo.
        $otro = $this->pagos->abrir('reserva', $this->reservaId, 100000);

        $repetido = true;
        try {
            $this->pagos->anotarResultado((int) $otro['id'], [
                'id' => '01-UNICA', 'status' => 'APPROVED', 'reference' => $otro['referencia'],
            ]);
        } catch (\Throwable) {
            $repetido = false;
        }

        $this->assertFalse($repetido, 'Dos pagos no pueden compartir id de transacción.');
    }

    // ── La pantalla de recepción ────────────────────────────────────────

    /** Sesión de gerencia, que llega a todo. */
    private function comoGerencia(): array
    {
        $rol = (new \App\Models\RolModel())->porClave('gerencia');

        return [
            'usuario_id'     => 1,
            'usuario_nombre' => 'Prueba',
            'usuario_rol'    => 'gerencia',
            'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    public function testLaFichaDeLaReservaOfreceElEnlaceDeCobro(): void
    {
        $r = $this->withSession($this->comoGerencia())->get('reservas/ver/' . $this->reservaId);

        $r->assertOK();
        $r->assertSee('Cobrar en línea');
        $r->assertSee('pago/reserva/' . $this->codigo . '/total');

        // De paso: esta reserva no tiene cabaña asignada y la ficha se abre
        // igual. Antes desaparecía, que era justo la que hay que abrir para
        // asignarle una.
        $r->assertSee('Sin cabaña asignada');
    }

    public function testConLaPasarelaApagadaNoSeOfreceElEnlaceEnLaFicha(): void
    {
        // Mandar al huésped a una pantalla de error sería peor que no ofrecerlo.
        (new ConfiguracionModel())->guardarPares(['wompi_activo' => '0']);

        $r = $this->withSession($this->comoGerencia())->get('reservas/ver/' . $this->reservaId);

        $r->assertOK();
        $r->assertDontSee('Cobrar en línea');
    }

    // ── Datos sensibles ─────────────────────────────────────────────────

    public function testNoSeGuardanDatosDeTarjeta(): void
    {
        $this->get('pago/reserva/' . $this->codigo);
        $pago = $this->pagos->deConcepto('reserva', $this->reservaId)[0];

        $this->pagos->anotarResultado((int) $pago['id'], [
            'id' => '01-TARJETA', 'status' => 'APPROVED', 'reference' => $pago['referencia'],
            'payment_method' => [
                'type' => 'CARD',
                'extra' => ['number' => '4242424242424242', 'exp_month' => '12', 'card_holder' => 'ANA RUIZ'],
            ],
        ]);

        $guardado = (string) $this->pagos->find($pago['id'])['respuesta'];

        $this->assertStringNotContainsString('4242424242424242', $guardado);
        $this->assertStringNotContainsString('ANA RUIZ', $guardado);
        $this->assertStringContainsString('***', $guardado);
    }

    // ── Referencias ─────────────────────────────────────────────────────

    public function testLaReferenciaNoLlevaDatosDelHuesped(): void
    {
        // Va en la URL de la pasarela y queda en sus registros.
        $referencia = $this->pagos->generarReferencia('reserva', $this->reservaId);

        $this->assertStringNotContainsString('Pago', $referencia);
        $this->assertStringNotContainsString('PRUEBA-PAGO', $referencia);
        $this->assertMatchesRegularExpression('/^RES-\d+-[a-f0-9]{12}$/', $referencia);
    }

    public function testDosReferenciasNuncaSeRepiten(): void
    {
        $vistas = [];
        for ($i = 0; $i < 50; $i++) {
            $vistas[] = $this->pagos->generarReferencia('reserva', $this->reservaId);
        }

        $this->assertCount(50, array_unique($vistas));
    }
}
