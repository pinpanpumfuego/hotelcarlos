<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EncuestaModel;
use App\Models\PortalAccesoModel;
use App\Models\SolicitudModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Portal del huésped.
 *
 * El enlace **es** la credencial, así que lo que más se comprueba aquí es que
 * un enlace que no vale no abre nada. En algo expuesto a internet y sin
 * contraseña, esa es toda la seguridad que hay.
 *
 * @internal
 */
final class PortalHuespedTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private int $reservaId;
    private array $reserva;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reserva   = $this->crearReserva();
        $this->reservaId = (int) $this->reserva['id'];
    }

    protected function tearDown(): void
    {
        $db = db_connect();
        $db->table('encuestas')->where('reserva_id', $this->reservaId)->delete();
        $db->table('solicitudes')->where('reserva_id', $this->reservaId)->delete();
        $db->table('portal_accesos')->where('reserva_id', $this->reservaId)->delete();
        $db->table('folio_movimientos')->where('reserva_id', $this->reservaId)->delete();
        $db->table('reservas')->where('id', $this->reservaId)->delete();
        $db->table('huespedes')->where('num_documento', 'PRUEBA-PORTAL')->delete();

        parent::tearDown();
    }

    private function crearReserva(array $extra = []): array
    {
        $db = db_connect();

        $db->table('huespedes')->insert([
            'nombre'         => 'Marta',
            'apellidos'      => 'Ruiz Salazar',
            'tipo_documento' => 'CC',
            'num_documento'  => 'PRUEBA-PORTAL',
            'email'          => 'marta.prueba@example.com',
        ]);
        $huespedId = $db->insertID();

        $unidad = $db->table('unidades')->select('id')->get(1)->getRowArray();

        $db->table('reservas')->insert([
            'codigo'        => 'PT' . random_int(10000, 99999),
            'huesped_id'    => $huespedId,
            'unidad_id'     => $unidad['id'] ?? null,
            'fecha_entrada' => $extra['entrada'] ?? date('Y-m-d', strtotime('-1 day')),
            'fecha_salida'  => $extra['salida'] ?? date('Y-m-d', strtotime('+2 days')),
            'adultos'       => 2,
            'ninos'         => 1,
            'estado'        => $extra['estado'] ?? 'confirmada',
            'total'         => 900000,
        ]);
        $id = $db->insertID();

        return $db->table('reservas')->where('id', $id)->get()->getRowArray();
    }

    private function acceso(): array
    {
        return (new PortalAccesoModel())->asegurar($this->reserva);
    }

    // ── Quién entra y quién no ──────────────────────────────────────────

    public function testConUnEnlaceValidoSeVeLaEstancia(): void
    {
        $r = $this->get('estancia/' . $this->acceso()['token']);

        $r->assertOK();
        $r->assertSee('Marta');
        $r->assertSee($this->reserva['codigo']);
    }

    public function testUnEnlaceInventadoNoAbreNada(): void
    {
        $r = $this->get('estancia/' . str_repeat('a', 48));

        $r->assertOK();   // se responde con una página amable, no con un error
        $r->assertSee(lang('Portal.caducadoTitulo'));
        $r->assertDontSee('Marta');
    }

    public function testUnTokenConFormatoRaroNiSiquieraSeBusca(): void
    {
        // El modelo descarta por longitud y por caracteres antes de tocar la
        // base de datos. Un intento de salirse de la ruta ni siquiera llega al
        // controlador: no hay ruta que lo recoja, y eso también vale.
        foreach (['corto', '../../etc/passwd', 'ZZZZ', str_repeat('a', 200), '48chars-pero-no-hex-aaaaaaaaaaaaaaaaaaaaaaaa'] as $malo) {
            try {
                $this->get('estancia/' . $malo)->assertDontSee('Marta');
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
                $this->assertTrue(true, 'Sin ruta que lo recoja: tampoco entra.');
            }
        }
    }

    public function testUnEnlaceCaducadoDejaDeServir(): void
    {
        $acceso = $this->acceso();
        (new PortalAccesoModel())->update($acceso['id'], [
            'expira_en' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $this->get('estancia/' . $acceso['token'])->assertSee(lang('Portal.caducadoTitulo'));
    }

    public function testUnEnlaceRevocadoDejaDeServir(): void
    {
        // Es lo que se usa cuando un enlace acaba donde no debe.
        $acceso = $this->acceso();
        (new PortalAccesoModel())->revocar($this->reservaId);

        $this->get('estancia/' . $acceso['token'])->assertSee(lang('Portal.caducadoTitulo'));
    }

    public function testUnaReservaCanceladaCierraElPortal(): void
    {
        $acceso = $this->acceso();
        db_connect()->table('reservas')->where('id', $this->reservaId)->update(['estado' => 'cancelada']);

        $this->get('estancia/' . $acceso['token'])->assertSee(lang('Portal.caducadoTitulo'));
    }

    public function testTodasLasSeccionesExigenElToken(): void
    {
        // Ninguna se fía de que otra lo haya comprobado.
        foreach (['cuenta', 'info', 'solicitudes', 'encuesta'] as $seccion) {
            $this->get('estancia/' . str_repeat('b', 48) . '/' . $seccion)
                ->assertSee(lang('Portal.caducadoTitulo'));
        }
    }

    // ── El enlace ───────────────────────────────────────────────────────

    public function testPedirElAccesoDosVecesNoCambiaElEnlace(): void
    {
        // Se llama al confirmar y al reenviar el correo; si cambiara, el enlace
        // que el huésped ya tiene guardado dejaría de funcionar.
        $primero = $this->acceso();
        $segundo = $this->acceso();

        $this->assertSame($primero['token'], $segundo['token']);
    }

    public function testElEnlaceViveDespuesDeLaSalida(): void
    {
        // El huésped todavía quiere su comprobante, y la encuesta de salida se
        // contesta cuando ya se ha ido.
        $acceso = $this->acceso();
        $dias   = (int) (new \DateTime($this->reserva['fecha_salida']))
            ->diff(new \DateTime($acceso['expira_en']))->days;

        $this->assertSame(PortalAccesoModel::DIAS_TRAS_SALIDA, $dias);
    }

    public function testSeAnotaQuienUsaElEnlace(): void
    {
        $acceso = $this->acceso();
        $this->get('estancia/' . $acceso['token']);

        $despues = (new PortalAccesoModel())->find($acceso['id']);

        $this->assertGreaterThan(0, (int) $despues['accesos']);
        $this->assertNotNull($despues['ultimo_acceso']);
    }

    public function testElPortalNoSeIndexaEnBuscadores(): void
    {
        $this->get('estancia/' . $this->acceso()['token'])->assertSee('noindex');
    }

    // ── La cuenta ───────────────────────────────────────────────────────

    public function testElHuespedVeSusConsumos(): void
    {
        db_connect()->table('folio_movimientos')->insert([
            'reserva_id' => $this->reservaId,
            'tipo'       => 'cargo',
            'concepto'   => 'Cena del jueves',
            'valor'      => 85000,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $r = $this->get('estancia/' . $this->acceso()['token'] . '/cuenta');

        $r->assertOK();
        $r->assertSee('Cena del jueves');
        $r->assertSee('85.000');
    }

    public function testUnaCuentaVaciaNoAsusta(): void
    {
        $this->get('estancia/' . $this->acceso()['token'] . '/cuenta')
            ->assertSee(lang('Portal.cuentaVacia'));
    }

    // ── Solicitudes ─────────────────────────────────────────────────────

    public function testElHuespedPuedePedirAlgo(): void
    {
        $token = $this->acceso()['token'];

        $this->post('estancia/' . $token . '/pedir', [
            csrf_token()  => csrf_hash(),
            'tipo'        => 'toallas',
            'detalle'     => 'Dos toallas de baño, por favor',
            'para_cuando' => 'esta tarde',
        ]);

        $solicitudes = (new SolicitudModel())->deReserva($this->reservaId);

        $this->assertCount(1, $solicitudes);
        $this->assertSame('toallas', $solicitudes[0]['tipo']);
        $this->assertSame('pendiente', $solicitudes[0]['estado']);
        $this->assertSame((int) $this->reserva['unidad_id'], (int) $solicitudes[0]['unidad_id']);
    }

    public function testLoQueDependeDelDiaNoSePromete(): void
    {
        // Salir tarde depende de si hay entrada ese día. Prometerlo sería mentir.
        $token = $this->acceso()['token'];

        $r = $this->post('estancia/' . $token . '/pedir', [
            csrf_token() => csrf_hash(),
            'tipo'       => 'salida_tarde',
        ]);

        $this->assertSame(lang('Portal.recibidaLaMiramos'), session()->getFlashdata('ok'));
        $this->assertTrue($r->isRedirect());
    }

    public function testUnTipoInventadoNoCrearNada(): void
    {
        $this->post('estancia/' . $this->acceso()['token'] . '/pedir', [
            csrf_token() => csrf_hash(),
            'tipo'       => 'un_masaje_gratis',
        ]);

        $this->assertCount(0, (new SolicitudModel())->deReserva($this->reservaId));
    }

    public function testHayUnTopeDePeticionesAlDia(): void
    {
        // Doce peticiones en un día no las hace nadie de buena fe.
        $token = $this->acceso()['token'];

        for ($i = 0; $i < 14; $i++) {
            $this->post('estancia/' . $token . '/pedir', [
                csrf_token() => csrf_hash(),
                'tipo'       => 'toallas',
            ]);
        }

        $this->assertLessThanOrEqual(12, count((new SolicitudModel())->deReserva($this->reservaId)));
    }

    // ── Encuesta ────────────────────────────────────────────────────────

    public function testDuranteLaEstanciaSePreguntaPorLaEstancia(): void
    {
        // Con el huésped todavía aquí, todavía se pueden arreglar las cosas.
        $this->get('estancia/' . $this->acceso()['token'] . '/encuesta')
            ->assertSee(lang('Portal.encuestaEstancia'));
    }

    public function testSeGuardaLaRespuesta(): void
    {
        $token = $this->acceso()['token'];

        $this->post('estancia/' . $token . '/encuesta', [
            csrf_token() => csrf_hash(),
            'general'    => 5,
            'limpieza'   => 4,
            'comentario' => 'Las aves al amanecer valen el viaje',
            'publicable' => 1,
        ]);

        $encuesta = (new EncuestaModel())->deReserva($this->reservaId, 'estancia');

        $this->assertNotNull($encuesta);
        $this->assertSame(5, (int) $encuesta['general']);
        $this->assertSame(1, (int) $encuesta['publicable']);
    }

    public function testResponderDosVecesNoCuentaDoble(): void
    {
        // Si contase doble, la media sería un número bonito y falso.
        $token = $this->acceso()['token'];

        foreach ([2, 5] as $nota) {
            $this->post('estancia/' . $token . '/encuesta', [
                csrf_token() => csrf_hash(),
                'general'    => $nota,
            ]);
        }

        $encuestas = (new EncuestaModel())->where('reserva_id', $this->reservaId)->findAll();

        $this->assertCount(1, $encuestas);
        $this->assertSame(5, (int) $encuestas[0]['general'], 'Se queda la última respuesta.');
    }

    public function testSinNotaGeneralNoSeGuarda(): void
    {
        $this->post('estancia/' . $this->acceso()['token'] . '/encuesta', [
            csrf_token() => csrf_hash(),
            'comentario' => 'Solo un comentario',
        ]);

        $this->assertNull((new EncuestaModel())->deReserva($this->reservaId, 'estancia'));
    }

    public function testSinAutorizacionElComentarioNoEsPublicable(): void
    {
        $this->post('estancia/' . $this->acceso()['token'] . '/encuesta', [
            csrf_token() => csrf_hash(),
            'general'    => 5,
            'comentario' => 'Todo perfecto',
        ]);

        $encuesta = (new EncuestaModel())->deReserva($this->reservaId, 'estancia');

        $this->assertSame(0, (int) $encuesta['publicable']);
        $this->assertSame([], (new EncuestaModel())->publicables());
    }

    public function testUnaNotaBajaPideAtencion(): void
    {
        $this->post('estancia/' . $this->acceso()['token'] . '/encuesta', [
            csrf_token() => csrf_hash(),
            'general'    => 2,
            'comentario' => 'La ducha no calienta',
        ]);

        $pendientes = (new EncuestaModel())->requierenAtencion();
        $codigos    = array_column($pendientes, 'codigo');

        $this->assertContains($this->reserva['codigo'], $codigos);
    }
}
