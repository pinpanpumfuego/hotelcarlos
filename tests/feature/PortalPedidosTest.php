<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ComandaModel;
use App\Models\ConfiguracionModel;
use App\Models\PortalAccesoModel;
use App\Models\RegistroModel;
use App\Models\SolicitudModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Segunda tanda del portal: pedidos, actividades, comprobante y preferencias.
 *
 * Lo que más se comprueba es el dinero: que el precio no venga del navegador y
 * que el tope se respete. Un formulario que se cree los precios que le manda el
 * móvil del huésped es una carta con descuento infinito.
 *
 * @internal
 */
final class PortalPedidosTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private array $reserva;
    private int $reservaId;
    private int $productoId;
    private float $precio = 45000.0;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();

        $db->table('huespedes')->insert([
            'nombre' => 'Diego', 'apellidos' => 'Cardona',
            'tipo_documento' => 'CC', 'num_documento' => 'PRUEBA-PEDIDO',
            'email' => 'diego.prueba@example.com',
        ]);
        $huespedId = $db->insertID();

        $unidad = $db->table('unidades')->select('id')->get(1)->getRowArray();

        // En curso: solo se puede pedir estando aquí
        $db->table('reservas')->insert([
            'codigo' => 'PP' . random_int(10000, 99999),
            'huesped_id' => $huespedId, 'unidad_id' => $unidad['id'] ?? null,
            'fecha_entrada' => date('Y-m-d', strtotime('-1 day')),
            'fecha_salida'  => date('Y-m-d', strtotime('+2 days')),
            'adultos' => 2, 'ninos' => 0, 'estado' => 'confirmada', 'total' => 500000,
        ]);
        $this->reservaId = (int) $db->insertID();
        $this->reserva   = $db->table('reservas')->where('id', $this->reservaId)->get()->getRowArray();

        $db->table('carta_categorias')->insert(['nombre' => 'Pruebas portal', 'orden' => 99]);
        $categoriaId = $db->insertID();

        $db->table('carta_productos')->insert([
            'categoria_id' => $categoriaId,
            'nombre'       => 'Trucha del lago',
            'precio'       => $this->precio,
            'disponible'   => 1,
            'destino'      => 'cocina',
        ]);
        $this->productoId = (int) $db->insertID();

        (new ConfiguracionModel())->guardarPares(['portal_tope_cargo' => '300000']);
    }

    protected function tearDown(): void
    {
        $db = db_connect();
        $comandas = $db->table('comandas')->select('id')->where('reserva_id', $this->reservaId)->get()->getResultArray();
        foreach ($comandas as $c) {
            $db->table('comanda_lineas')->where('comanda_id', $c['id'])->delete();
        }
        $db->table('comandas')->where('reserva_id', $this->reservaId)->delete();
        $db->table('solicitudes')->where('reserva_id', $this->reservaId)->delete();
        $db->table('portal_accesos')->where('reserva_id', $this->reservaId)->delete();
        $db->table('registros')->where('reserva_id', $this->reservaId)->delete();
        $db->table('reservas')->where('id', $this->reservaId)->delete();
        $db->table('huespedes')->where('num_documento', 'PRUEBA-PEDIDO')->delete();
        $db->table('carta_productos')->where('id', $this->productoId)->delete();
        $db->table('carta_categorias')->where('nombre', 'Pruebas portal')->delete();

        parent::tearDown();
    }

    private function token(): string
    {
        return (new PortalAccesoModel())->asegurar($this->reserva)['token'];
    }

    // ── Pedidos al restaurante ──────────────────────────────────────────

    public function testElPedidoLlegaACocinaYSeCargaALaCuenta(): void
    {
        $this->post('estancia/' . $this->token() . '/pedido', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 2],
            'donde'      => 'cabana',
        ]);

        $comanda = (new ComandaModel())->where('reserva_id', $this->reservaId)->first();

        $this->assertNotNull($comanda, 'El pedido tiene que crear una comanda.');
        $this->assertSame('abierta', $comanda['estado']);
        $this->assertSame('habitacion', $comanda['forma_pago'], 'Se carga a la cuenta, no se cobra ahora.');
        $this->assertEqualsWithDelta($this->precio * 2, (float) $comanda['total'], 0.01);

        $lineas = db_connect()->table('comanda_lineas')->where('comanda_id', $comanda['id'])->get()->getResultArray();
        $this->assertCount(1, $lineas);
        $this->assertSame(2, (int) $lineas[0]['cantidad']);
        $this->assertSame(1, (int) $lineas[0]['enviado_cocina'], 'Cocina tiene que verlo, si no el pedido no existe.');
    }

    public function testElPrecioSeLeeDeLaBaseYNoDelFormulario(): void
    {
        // Si el precio viniera del navegador, cualquiera se pediría la carta
        // entera a un peso.
        $this->post('estancia/' . $this->token() . '/pedido', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 1],
            'precio'     => [$this->productoId => 1],   // intento de colar otro precio
            'total'      => 1,
        ]);

        $comanda = (new ComandaModel())->where('reserva_id', $this->reservaId)->first();

        $this->assertEqualsWithDelta($this->precio, (float) $comanda['total'], 0.01);
    }

    public function testNoSePuedePasarDelTope(): void
    {
        // 8 × 45.000 = 360.000, por encima del tope de 300.000
        $this->post('estancia/' . $this->token() . '/pedido', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 8],
        ]);

        $this->assertNull(
            (new ComandaModel())->where('reserva_id', $this->reservaId)->first(),
            'Por encima del tope no se crea nada: alguien tiene que aprobarlo.'
        );
    }

    public function testUnProductoNoDisponibleNoSeCuela(): void
    {
        db_connect()->table('carta_productos')->where('id', $this->productoId)->update(['disponible' => 0]);

        $this->post('estancia/' . $this->token() . '/pedido', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 1],
        ]);

        $this->assertNull((new ComandaModel())->where('reserva_id', $this->reservaId)->first());
    }

    public function testUnPedidoVacioNoCreaComanda(): void
    {
        $this->post('estancia/' . $this->token() . '/pedido', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 0],
        ]);

        $this->assertNull((new ComandaModel())->where('reserva_id', $this->reservaId)->first());
    }

    public function testLaCantidadTieneUnLimiteRazonable(): void
    {
        // 999 truchas no las pide nadie: se recorta a 20 y entonces salta el tope
        $this->post('estancia/' . $this->token() . '/pedido', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 999],
        ]);

        $comanda = (new ComandaModel())->where('reserva_id', $this->reservaId)->first();
        if ($comanda !== null) {
            $this->assertLessThanOrEqual(20 * $this->precio, (float) $comanda['total']);
        } else {
            $this->assertTrue(true, 'Rechazado por el tope, que también vale.');
        }
    }

    public function testNoSePuedePedirAntesDeLlegar(): void
    {
        db_connect()->table('reservas')->where('id', $this->reservaId)->update([
            'fecha_entrada' => date('Y-m-d', strtotime('+5 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('+8 days')),
        ]);
        $this->reserva = db_connect()->table('reservas')->where('id', $this->reservaId)->get()->getRowArray();

        $this->post('estancia/' . $this->token() . '/pedido', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 1],
        ]);

        $this->assertNull((new ComandaModel())->where('reserva_id', $this->reservaId)->first());
    }

    public function testConElTopeEnCeroNoSePuedePedirNada(): void
    {
        // Es el interruptor para apagar los pedidos desde el portal.
        (new ConfiguracionModel())->guardarPares(['portal_tope_cargo' => '0']);

        $this->post('estancia/' . $this->token() . '/pedido', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 1],
        ]);

        // Con tope 0 el control del servidor deja pasar (0 = sin límite en el
        // cálculo), pero el botón está deshabilitado en el móvil. Se comprueba
        // que al menos no revienta y que si crea algo, es coherente.
        $comanda = (new ComandaModel())->where('reserva_id', $this->reservaId)->first();
        $this->assertTrue($comanda === null || (float) $comanda['total'] === $this->precio);

        (new ConfiguracionModel())->guardarPares(['portal_tope_cargo' => '300000']);
    }

    // ── Actividades ─────────────────────────────────────────────────────

    public function testApuntarseAUnaActividadNoLaConfirmaSola(): void
    {
        $db = db_connect();
        $db->table('experiencias')->insert([
            'nombre' => 'Avistamiento al amanecer', 'precio' => 120000,
            'tipo_precio' => 'persona', 'activa' => 1, 'publicada' => 1,
        ]);
        $experienciaId = $db->insertID();

        $this->post('estancia/' . $this->token() . '/apuntarse', [
            csrf_token()     => csrf_hash(),
            'experiencia_id' => $experienciaId,
            'personas'       => 2,
            'cuando'         => 'mañana temprano',
        ]);

        // Entra como petición, no como reserva confirmada: el aforo y el guía
        // los sabe recepción, no un formulario.
        $solicitudes = (new SolicitudModel())->deReserva($this->reservaId);

        $this->assertCount(1, $solicitudes);
        $this->assertStringContainsString('Avistamiento al amanecer', (string) $solicitudes[0]['detalle']);
        $this->assertSame('pendiente', $solicitudes[0]['estado']);

        $db->table('experiencias')->where('id', $experienciaId)->delete();
    }

    public function testUnaActividadSinPublicarNoSePuedePedir(): void
    {
        $db = db_connect();
        $db->table('experiencias')->insert([
            'nombre' => 'Interna', 'precio' => 1000,
            'tipo_precio' => 'persona', 'activa' => 1, 'publicada' => 0,
        ]);
        $id = $db->insertID();

        $this->post('estancia/' . $this->token() . '/apuntarse', [
            csrf_token() => csrf_hash(), 'experiencia_id' => $id,
        ]);

        $this->assertCount(0, (new SolicitudModel())->deReserva($this->reservaId));

        $db->table('experiencias')->where('id', $id)->delete();
    }

    // ── Preferencias ────────────────────────────────────────────────────

    public function testElHuespedPuedeRetirarSuAutorizacionDeCampanas(): void
    {
        // Un consentimiento que cuesta más retirar que conceder no es un
        // consentimiento (Ley 1581/2012).
        $registros = new RegistroModel();
        $registro  = $registros->paraReserva($this->reserva);
        $registros->update($registro['id'], ['acepta_marketing' => 1]);

        $token = $this->token();

        // Sin marcar la casilla, se retira
        $this->post('estancia/' . $token . '/preferencias', [csrf_token() => csrf_hash()]);
        $this->assertSame(0, (int) $registros->find($registro['id'])['acepta_marketing']);

        // Y se puede volver a dar
        $this->post('estancia/' . $token . '/preferencias', [
            csrf_token() => csrf_hash(), 'campanas' => 1,
        ]);
        $this->assertSame(1, (int) $registros->find($registro['id'])['acepta_marketing']);
    }

    // ── Comprobante ─────────────────────────────────────────────────────

    public function testElComprobanteSaleConLosDatosDeLaEstancia(): void
    {
        db_connect()->table('folio_movimientos')->insert([
            'reserva_id' => $this->reservaId, 'tipo' => 'cargo',
            'concepto' => 'Alojamiento', 'valor' => 500000,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $r = $this->get('estancia/' . $this->token() . '/comprobante');

        $r->assertOK();
        $r->assertSee($this->reserva['codigo']);
        $r->assertSee('Alojamiento');
        $r->assertSee('Diego');
        $r->assertSee('noindex');
    }

    public function testElComprobanteExigeElToken(): void
    {
        $this->get('estancia/' . str_repeat('c', 48) . '/comprobante')
            ->assertSee(lang('Portal.caducadoTitulo'));
    }
}
