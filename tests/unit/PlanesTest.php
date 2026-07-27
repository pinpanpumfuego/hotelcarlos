<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Planes;
use App\Models\CartaCategoriaModel;
use App\Models\ComandaModel;
use App\Models\ConfiguracionModel;
use App\Models\PlanConsumoModel;
use App\Models\PlanLineaModel;
use App\Models\PlanModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Planes incluidos en la tarifa.
 *
 * Es el error más caro del día a día: cobrar un desayuno que ya estaba pagado,
 * o regalar uno que no lo estaba. Las pruebas van sobre las dos caras.
 *
 * @internal
 */
final class PlanesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Planes $planes;
    private int $planId;
    private int $categoriaId;
    private int $productoId;
    private int $lineaPlanId;
    private array $reserva;
    private int $reservaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planes = new Planes();
        $this->limpiar();

        $db = db_connect();

        $db->table('carta_categorias')->insert(['nombre' => 'Desayunos prueba', 'orden' => 90]);
        $this->categoriaId = (int) $db->insertID();

        $db->table('carta_productos')->insert([
            'categoria_id' => $this->categoriaId, 'nombre' => 'Huevos pericos',
            'precio' => 18000, 'disponible' => 1, 'destino' => 'cocina',
        ]);
        $this->productoId = (int) $db->insertID();

        $this->planId = (int) (new PlanModel())->insert([
            'nombre' => 'Plan prueba desayuno', 'activo' => 1,
        ], true);

        // Un desayuno por persona y día, solo en la franja de desayuno
        (new PlanLineaModel())->guardarDerecho([
            'plan_id'      => $this->planId,
            'categoria_id' => $this->categoriaId,
            'cantidad'     => 1,
            'por'          => 'persona_dia',
            'franja'       => 'desayuno',
        ]);
        $this->lineaPlanId = (int) (new PlanLineaModel())->where('plan_id', $this->planId)->first()['id'];

        // Una reserva de verdad: la foránea de `plan_consumos` no admite
        // inventarse un id, y hace bien.
        $db->table('huespedes')->insert([
            'nombre' => 'Plan', 'apellidos' => 'Prueba',
            'tipo_documento' => 'CC', 'num_documento' => 'PRUEBA-PLAN',
        ]);
        $huespedId = $db->insertID();

        $db->table('reservas')->insert([
            'codigo' => 'PL' . random_int(10000, 99999),
            'huesped_id' => $huespedId, 'unidad_id' => null,
            'fecha_entrada' => date('Y-m-d'), 'fecha_salida' => date('Y-m-d', strtotime('+2 days')),
            'adultos' => 2, 'ninos' => 1, 'estado' => 'confirmada', 'total' => 300000,
            'plan_id' => $this->planId,
        ]);
        $this->reservaId = (int) $db->insertID();

        // 2 adultos + 1 niño = 3 desayunos al día
        $this->reserva = $db->table('reservas')->where('id', $this->reservaId)->get()->getRowArray();

        (new ConfiguracionModel())->guardarPares([
            'carta_franja_desayuno' => '06:30-11:00',
            'carta_franja_comida'   => '12:00-16:00',
            'carta_franja_cena'     => '18:30-22:00',
        ]);
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();
        $reservas = $db->table('reservas r')->select('r.id')
            ->join('huespedes h', 'h.id = r.huesped_id')
            ->where('h.num_documento', 'PRUEBA-PLAN')->get()->getResultArray();
        foreach ($reservas as $r) {
            $db->table('plan_consumos')->where('reserva_id', $r['id'])->delete();
            $db->table('reservas')->where('id', $r['id'])->delete();
        }
        $db->table('huespedes')->where('num_documento', 'PRUEBA-PLAN')->delete();

        $planes = $db->table('planes')->select('id')->where('nombre', 'Plan prueba desayuno')->get()->getResultArray();
        foreach ($planes as $p) {
            $db->table('plan_lineas')->where('plan_id', $p['id'])->delete();
            $db->table('planes')->where('id', $p['id'])->delete();
        }

        $db->table('carta_productos')->where('nombre', 'Huevos pericos')->delete();
        $db->table('carta_categorias')->where('nombre', 'Desayunos prueba')->delete();
    }

    private function producto(): array
    {
        return db_connect()->table('carta_productos')->where('id', $this->productoId)->get()->getRowArray();
    }

    // ── Franjas horarias ────────────────────────────────────────────────

    public function testLaFranjaSeCalculaPorLaHora(): void
    {
        $this->assertSame('desayuno', $this->planes->franjaActual('08:00'));
        $this->assertSame('comida', $this->planes->franjaActual('13:30'));
        $this->assertSame('cena', $this->planes->franjaActual('20:00'));
    }

    public function testFueraDeHorarioNoHayFranja(): void
    {
        // A las cinco de la tarde no hay desayuno incluido que valga, y eso
        // tiene que poder decirse.
        $this->assertNull($this->planes->franjaActual('17:00'));
        $this->assertNull($this->planes->franjaActual('03:00'));
    }

    public function testUnaFranjaSinHorasNoSeAplica(): void
    {
        (new ConfiguracionModel())->guardarPares(['carta_franja_cena' => '']);

        $this->assertNull($this->planes->franjaActual('20:00'));

        (new ConfiguracionModel())->guardarPares(['carta_franja_cena' => '18:30-22:00']);
    }

    // ── Qué cubre el plan ───────────────────────────────────────────────

    public function testElPlanCubreSuCategoriaEnSuFranja(): void
    {
        $cubierto = $this->planes->cubre($this->reserva, $this->producto(), 'desayuno');

        $this->assertNotNull($cubierto);
        $this->assertSame(3, $cubierto['quedan'], '2 adultos + 1 niño = 3 desayunos al día.');
    }

    public function testUnDesayunoIncluidoNoSeGastaEnLaCena(): void
    {
        $this->assertNull(
            $this->planes->cubre($this->reserva, $this->producto(), 'cena'),
            'El derecho vale solo en su franja.'
        );
    }

    public function testSinPlanNoHayNadaIncluido(): void
    {
        $sinPlan = $this->reserva;
        $sinPlan['plan_id'] = null;

        $this->assertNull($this->planes->cubre($sinPlan, $this->producto(), 'desayuno'));
    }

    public function testUnPlanDesactivadoDejaDeAplicarse(): void
    {
        (new PlanModel())->update($this->planId, ['activo' => 0]);

        $this->assertNull($this->planes->cubre($this->reserva, $this->producto(), 'desayuno'));
    }

    public function testUnProductoDeOtraCategoriaNoEstaIncluido(): void
    {
        $db = db_connect();
        $db->table('carta_categorias')->insert(['nombre' => 'Otra prueba', 'orden' => 91]);
        $otraCat = $db->insertID();
        $db->table('carta_productos')->insert([
            'categoria_id' => $otraCat, 'nombre' => 'Cerveza prueba',
            'precio' => 9000, 'disponible' => 1,
        ]);
        $otro = $db->table('carta_productos')->where('id', $db->insertID())->get()->getRowArray();

        $this->assertNull($this->planes->cubre($this->reserva, $otro, 'desayuno'));

        $db->table('carta_productos')->where('nombre', 'Cerveza prueba')->delete();
        $db->table('carta_categorias')->where('nombre', 'Otra prueba')->delete();
    }

    // ── Cuántos quedan ──────────────────────────────────────────────────

    public function testElDerechoSeAgotaAlConsumirlo(): void
    {
        $this->planes->apuntarConsumo($this->reservaId, $this->lineaPlanId, 2, 36000);

        $cubierto = $this->planes->cubre($this->reserva, $this->producto(), 'desayuno');

        $this->assertSame(1, $cubierto['quedan'], 'De 3 se gastaron 2.');
    }

    public function testAgotadoElDerechoDejaDeCubrir(): void
    {
        $this->planes->apuntarConsumo($this->reservaId, $this->lineaPlanId, 3, 54000);

        $this->assertNull(
            $this->planes->cubre($this->reserva, $this->producto(), 'desayuno'),
            'Gastados los tres, el cuarto desayuno se cobra.'
        );
    }

    public function testElDerechoPorDiaSeRenuevaCadaDia(): void
    {
        // Se apunta un consumo de ayer directamente en la tabla
        db_connect()->table('plan_consumos')->insert([
            'reserva_id' => $this->reservaId, 'linea_id' => $this->lineaPlanId,
            'fecha' => date('Y-m-d', strtotime('-1 day')),
            'cantidad' => 3, 'valor' => 54000, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $cubierto = $this->planes->cubre($this->reserva, $this->producto(), 'desayuno');

        $this->assertSame(3, $cubierto['quedan'], 'Lo de ayer no gasta lo de hoy.');
    }

    public function testAnularLaLineaDevuelveElDerecho(): void
    {
        // Si el desayuno no llegó a tomarse, el huésped no lo puede perder.
        $this->planes->apuntarConsumo($this->reservaId, $this->lineaPlanId, 3, 54000, 777001);
        $this->assertNull($this->planes->cubre($this->reserva, $this->producto(), 'desayuno'));

        $this->planes->devolverConsumo(777001);

        $this->assertSame(3, $this->planes->cubre($this->reserva, $this->producto(), 'desayuno')['quedan']);
    }

    public function testUnDerechoPorEstanciaNoSeRenuevaCadaDia(): void
    {
        (new PlanLineaModel())->guardarDerecho([
            'plan_id'      => $this->planId,
            'categoria_id' => $this->categoriaId,
            'cantidad'     => 1,
            'por'          => 'estancia',
            'franja'       => '',
        ]);
        $porEstancia = (new PlanLineaModel())->where('plan_id', $this->planId)->where('por', 'estancia')->first();

        db_connect()->table('plan_consumos')->insert([
            'reserva_id' => $this->reservaId, 'linea_id' => $porEstancia['id'],
            'fecha' => date('Y-m-d', strtotime('-2 days')),
            'cantidad' => 1, 'valor' => 18000, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertSame(0, $this->planes->quedan($this->reserva, $porEstancia),
            'Una botella de bienvenida se toma una vez, no una por noche.');
    }

    // ── Validación de los derechos ──────────────────────────────────────

    public function testUnDerechoTieneQueDecirQueIncluye(): void
    {
        $modelo = new PlanLineaModel();

        $this->assertFalse(
            $modelo->guardarDerecho(['plan_id' => $this->planId]),
            'Sin categoría ni producto no se sabe qué incluye.'
        );

        $this->assertFalse(
            $modelo->guardarDerecho([
                'plan_id' => $this->planId,
                'categoria_id' => $this->categoriaId,
                'producto_id'  => $this->productoId,
            ]),
            'Con las dos cosas no se sabe cuál gasta.'
        );
    }

    // ── El resumen que ve el huésped ────────────────────────────────────

    public function testElResumenDiceCuantoQueda(): void
    {
        $this->planes->apuntarConsumo($this->reservaId, $this->lineaPlanId, 1, 18000);

        $resumen = $this->planes->resumen($this->reserva);

        $this->assertCount(1, $resumen);
        $this->assertSame(2, $resumen[0]['quedan']);
        $this->assertSame(3, $resumen[0]['de']);
        $this->assertSame('Desayunos prueba', $resumen[0]['concepto']);
    }

    // ── Carta por franjas ───────────────────────────────────────────────

    public function testLaCartaSeFiltraPorFranja(): void
    {
        $categorias = new CartaCategoriaModel();
        $categorias->fijarFranjas($this->categoriaId, ['desayuno']);

        $enDesayuno = array_column($categorias->deFranja('desayuno'), 'nombre');
        $enCena     = array_column($categorias->deFranja('cena'), 'nombre');

        $this->assertContains('Desayunos prueba', $enDesayuno);
        $this->assertNotContains('Desayunos prueba', $enCena);
    }

    public function testUnaCategoriaSinFranjasSaleSiempre(): void
    {
        // Es lo que se espera de una carta recién montada: que salga entera
        // hasta que alguien decida lo contrario.
        $categorias = new CartaCategoriaModel();

        foreach (['desayuno', 'comida', 'cena'] as $franja) {
            $this->assertContains(
                'Desayunos prueba',
                array_column($categorias->deFranja($franja), 'nombre')
            );
        }
    }

    public function testSinFranjaSaleLaCartaEntera(): void
    {
        $categorias = new CartaCategoriaModel();
        $categorias->fijarFranjas($this->categoriaId, ['desayuno']);

        // Fuera de todo horario no se esconde nada: mejor enseñar de más que
        // dejar al camarero sin poder vender.
        $this->assertContains(
            'Desayunos prueba',
            array_column($categorias->deFranja(null), 'nombre')
        );
    }

    // ── Total de la comanda ─────────────────────────────────────────────

    public function testElTotalNoCuentaLoIncluidoNiLoInvitado(): void
    {
        $db       = db_connect();
        $comandas = new ComandaModel();

        $comandaId = (int) $comandas->insert([
            'numero' => 'T-' . random_int(1000, 9999), 'estado' => 'abierta', 'total' => 0,
        ], true);

        foreach ([
            ['normal', 10000],
            ['incluida', 18000],
            ['cortesia', 9000],
            ['devuelta', 7000],
            ['normal', 5000],
        ] as [$estado, $precio]) {
            $db->table('comanda_lineas')->insert([
                'comanda_id' => $comandaId, 'nombre_producto' => 'X',
                'precio_unitario' => $precio, 'cantidad' => 1,
                'estado_linea' => $estado, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $total = $comandas->recalcularTotal($comandaId);

        $this->assertEqualsWithDelta(15000, $total, 0.01, 'Solo se cobran las dos líneas normales.');

        // Y las otras siguen ahí: la cocina las gastó igual
        $this->assertSame(5, $db->table('comanda_lineas')->where('comanda_id', $comandaId)->countAllResults());

        $db->table('comanda_lineas')->where('comanda_id', $comandaId)->delete();
        $comandas->delete($comandaId, true);
    }
}
