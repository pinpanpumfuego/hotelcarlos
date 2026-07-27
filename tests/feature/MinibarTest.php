<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfiguracionModel;
use App\Models\FolioModel;
use App\Models\PortalAccesoModel;
use App\Models\SolicitudModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Minibar: puede haber o no.
 *
 * Lo que más se comprueba es que **no aparezca cuando no lo hay**. Enseñarle un
 * minibar a quien no lo tiene en la cabaña es una promesa que no se puede
 * cumplir, y el huésped se entera a las once de la noche.
 *
 * @internal
 */
final class MinibarTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private array $reserva;
    private int $reservaId;
    private int $unidadId;
    private int $productoId;
    private float $precio = 12000.0;

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();

        // Se limpia también al empezar: si una ejecución anterior se cortó a
        // mitad, sus restos harían fallar el `insert` por documento repetido y
        // el error apuntaría al sitio equivocado.
        $this->limpiar();

        $db->table('huespedes')->insert([
            'nombre' => 'Laura', 'apellidos' => 'Mejía',
            'tipo_documento' => 'CC', 'num_documento' => 'PRUEBA-MINIBAR',
        ]);
        $huespedId = $db->insertID();

        // La base de pruebas se migra pero no se siembra: si no hay ningún tipo
        // de alojamiento, se crea uno aquí en vez de dar por hecho que existe.
        $tipo = $db->table('tipos_unidad')->select('id')->get(1)->getRowArray();
        if ($tipo === null) {
            $db->table('tipos_unidad')->insert(['nombre' => 'Tipo prueba minibar', 'capacidad' => 2, 'tarifa_base' => 100000]);
            $tipo = ['id' => $db->insertID()];
        }

        $db->table('unidades')->insert([
            'tipo_id' => $tipo['id'], 'nombre' => 'Cabaña minibar', 'estado' => 'disponible', 'minibar' => 1,
        ]);
        $this->unidadId = (int) $db->insertID();

        $db->table('reservas')->insert([
            'codigo' => 'MB' . random_int(10000, 99999),
            'huesped_id' => $huespedId, 'unidad_id' => $this->unidadId,
            'fecha_entrada' => date('Y-m-d', strtotime('-1 day')),
            'fecha_salida'  => date('Y-m-d', strtotime('+2 days')),
            'adultos' => 2, 'estado' => 'confirmada', 'total' => 400000,
        ]);
        $this->reservaId = (int) $db->insertID();
        $this->reserva   = $db->table('reservas')->where('id', $this->reservaId)->get()->getRowArray();

        $db->table('carta_categorias')->insert(['nombre' => 'Minibar prueba', 'orden' => 98]);
        $categoriaId = $db->insertID();
        $db->table('carta_productos')->insert([
            'categoria_id' => $categoriaId, 'nombre' => 'Cerveza artesanal',
            'precio' => $this->precio, 'disponible' => 1, 'en_minibar' => 1, 'destino' => 'directo',
        ]);
        $this->productoId = (int) $db->insertID();

        (new ConfiguracionModel())->guardarPares(['portal_minibar' => '1']);
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        (new ConfiguracionModel())->guardarPares(['portal_minibar' => '0']);

        parent::tearDown();
    }

    /** Borra todo lo de esta prueba, en el orden que respeta las foráneas. */
    private function limpiar(): void
    {
        $db = db_connect();

        $reservas = $db->table('reservas r')
            ->select('r.id')
            ->join('huespedes h', 'h.id = r.huesped_id')
            ->where('h.num_documento', 'PRUEBA-MINIBAR')
            ->get()->getResultArray();

        foreach ($reservas as $r) {
            $db->table('folio_movimientos')->where('reserva_id', $r['id'])->delete();
            $db->table('solicitudes')->where('reserva_id', $r['id'])->delete();
            $db->table('portal_accesos')->where('reserva_id', $r['id'])->delete();
            $db->table('reservas')->where('id', $r['id'])->delete();
        }

        $db->table('unidades')->where('nombre', 'Cabaña minibar')->delete();
        $db->table('huespedes')->where('num_documento', 'PRUEBA-MINIBAR')->delete();
        $db->table('carta_productos')->where('nombre', 'Cerveza artesanal')->delete();
        $db->table('carta_categorias')->where('nombre', 'Minibar prueba')->delete();
        $db->table('tipos_unidad')->where('nombre', 'Tipo prueba minibar')->delete();
    }

    private function token(): string
    {
        return (new PortalAccesoModel())->asegurar($this->reserva)['token'];
    }

    // ── Cuándo se ve y cuándo no ────────────────────────────────────────

    public function testSeVeSiElHotelLoTieneYLaCabanaTambien(): void
    {
        $r = $this->get('estancia/' . $this->token() . '/minibar');

        $r->assertOK();
        $r->assertSee('Cerveza artesanal');
    }

    public function testNoSeVeSiElHotelLoTieneApagado(): void
    {
        (new ConfiguracionModel())->guardarPares(['portal_minibar' => '0']);

        $r = $this->get('estancia/' . $this->token() . '/minibar');

        $this->assertTrue($r->isRedirect(), 'Con el interruptor apagado no se entra.');
    }

    public function testNoSeVeSiLaCabanaNoLoTiene(): void
    {
        // El interruptor general encendido no basta: en un ecolodge es normal
        // que unas cabañas tengan nevera y otras no.
        db_connect()->table('unidades')->where('id', $this->unidadId)->update(['minibar' => 0]);

        $this->assertTrue($this->get('estancia/' . $this->token() . '/minibar')->isRedirect());
    }

    public function testNoSeVeSiLaReservaNoTieneCabanaAsignada(): void
    {
        db_connect()->table('reservas')->where('id', $this->reservaId)->update(['unidad_id' => null]);

        $this->assertTrue($this->get('estancia/' . $this->token() . '/minibar')->isRedirect());
    }

    public function testElAccesoNoSePintaSiNoHayMinibar(): void
    {
        (new ConfiguracionModel())->guardarPares(['portal_minibar' => '0']);

        $this->get('estancia/' . $this->token())->assertDontSee('/minibar');
    }

    public function testElAccesoSePintaSiLoHay(): void
    {
        $this->get('estancia/' . $this->token())->assertSee('/minibar');
    }

    // ── Declarar el consumo ─────────────────────────────────────────────

    public function testLoDeclaradoSeCargaALaCuentaConSuNombre(): void
    {
        $this->post('estancia/' . $this->token() . '/minibar', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 2],
        ]);

        $movimientos = (new FolioModel())->where('reserva_id', $this->reservaId)->findAll();

        $this->assertCount(1, $movimientos);
        $this->assertSame('cargo', $movimientos[0]['tipo']);
        $this->assertEqualsWithDelta($this->precio * 2, (float) $movimientos[0]['valor'], 0.01);
        $this->assertStringContainsString(
            'Cerveza artesanal',
            $movimientos[0]['concepto'],
            'Con el nombre dentro, para que al salir no haya discusión sobre qué es cada línea.'
        );
    }

    public function testElPrecioSaleDeLaBaseYNoDelFormulario(): void
    {
        $this->post('estancia/' . $this->token() . '/minibar', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 1],
            'precio'     => [$this->productoId => 1],
        ]);

        $movimiento = (new FolioModel())->where('reserva_id', $this->reservaId)->first();

        $this->assertEqualsWithDelta($this->precio, (float) $movimiento['valor'], 0.01);
    }

    public function testUnProductoQueNoEstaEnElMinibarNoSeCuela(): void
    {
        db_connect()->table('carta_productos')->where('id', $this->productoId)->update(['en_minibar' => 0]);

        $this->post('estancia/' . $this->token() . '/minibar', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 1],
        ]);

        $this->assertSame(0, (new FolioModel())->where('reserva_id', $this->reservaId)->countAllResults());
    }

    public function testDeclararCeroNoCargaNada(): void
    {
        $this->post('estancia/' . $this->token() . '/minibar', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 0],
        ]);

        $this->assertSame(0, (new FolioModel())->where('reserva_id', $this->reservaId)->countAllResults());
    }

    public function testNoSePuedeDeclararSiElMinibarEstaApagado(): void
    {
        (new ConfiguracionModel())->guardarPares(['portal_minibar' => '0']);

        $this->post('estancia/' . $this->token() . '/minibar', [
            csrf_token() => csrf_hash(),
            'cantidad'   => [$this->productoId => 3],
        ]);

        $this->assertSame(0, (new FolioModel())->where('reserva_id', $this->reservaId)->countAllResults());
    }

    // ── Reposición ──────────────────────────────────────────────────────

    public function testPedirQueLoRepongaVaComoPeticionYNoComoCargo(): void
    {
        $this->post('estancia/' . $this->token() . '/minibar/reponer', [
            csrf_token() => csrf_hash(),
            'detalle'    => 'Se acabó el agua',
        ]);

        $solicitudes = (new SolicitudModel())->deReserva($this->reservaId);

        $this->assertCount(1, $solicitudes);
        $this->assertSame('minibar', $solicitudes[0]['tipo']);
        $this->assertSame(0, (new FolioModel())->where('reserva_id', $this->reservaId)->countAllResults(),
            'Reponer no se le cobra a nadie.');
    }

    public function testElTokenSigueHaciendoFaltaParaTodo(): void
    {
        foreach (['minibar'] as $seccion) {
            $this->get('estancia/' . str_repeat('d', 48) . '/' . $seccion)
                ->assertSee(lang('Portal.caducadoTitulo'));
        }
    }
}
