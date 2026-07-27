<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Preventivos;
use App\Models\ActivoModel;
use App\Models\LecturaMedidorModel;
use App\Models\MantenimientoModel;
use App\Models\MedidorModel;
use App\Models\PlanMantenimientoModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * Preventivos y medidores.
 *
 * Las dos cosas que se defienden aquí:
 *
 * 1. **No apilar.** Si la revisión de septiembre sigue sin hacerse, la de
 *    octubre no se abre. Tres órdenes idénticas en el tablero son la forma más
 *    rápida de que nadie mire ninguna.
 * 2. **Una lectura rara se marca, no se calcula.** Un consumo negativo se
 *    colaría en todos los informes y nadie sabría de dónde salió.
 *
 * @internal
 */
final class PreventivosTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private PlanMantenimientoModel $planes;
    private MantenimientoModel $ordenes;
    private ActivoModel $activos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planes  = new PlanMantenimientoModel();
        $this->ordenes = new MantenimientoModel();
        $this->activos = new ActivoModel();
        $this->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $db->table('mantenimientos')->like('titulo', 'PRUEBA-', 'after')->delete();
        $db->table('planes_mantenimiento')->like('nombre', 'PRUEBA-', 'after')->delete();
        $db->table('activos')->like('nombre', 'PRUEBA-', 'after')->delete();

        $medidores = $db->table('medidores')->select('id')->like('nombre', 'PRUEBA-', 'after')->get()->getResultArray();

        foreach ($medidores as $m) {
            $db->table('lecturas_medidor')->where('medidor_id', $m['id'])->delete();
        }

        $db->table('medidores')->like('nombre', 'PRUEBA-', 'after')->delete();
    }

    private function crearActivo(string $nombre = 'PRUEBA-Extintor cocina', string $categoria = 'extintor'): int
    {
        $this->activos->insert([
            'codigo'    => $this->activos->siguienteCodigo(),
            'nombre'    => $nombre,
            'categoria' => $categoria,
        ]);

        return (int) $this->activos->getInsertID();
    }

    private function crearPlan(array $extra = []): int
    {
        $this->planes->insert(array_merge([
            'nombre'        => 'PRUEBA-Revisión anual',
            'cada'          => 12,
            'periodo'       => 'meses',
            'proxima_fecha' => date('Y-m-d'),
            'aviso_dias'    => 7,
            'prioridad'     => 'media',
            'activo'        => 1,
        ], $extra));

        return (int) $this->planes->getInsertID();
    }

    // ── Generación ──────────────────────────────────────────────────────

    public function testUnPlanQueTocaAbreSuOrden(): void
    {
        $activoId = $this->crearActivo();
        $this->crearPlan(['activo_id' => $activoId]);

        $r = (new Preventivos())->generar();

        $this->assertSame(1, $r['abiertas']);
        $this->assertSame(1, $this->ordenes->where('activo_id', $activoId)->where('tipo', 'preventiva')->countAllResults());
    }

    public function testUnPlanQueNoTocaTodaviaNoAbreNada(): void
    {
        $this->crearPlan([
            'activo_id'     => $this->crearActivo(),
            'proxima_fecha' => date('Y-m-d', strtotime('+3 months')),
            'aviso_dias'    => 7,
        ]);

        $this->assertSame(0, (new Preventivos())->generar()['abiertas']);
    }

    public function testElAvisoPrevioAdelantaLaOrden(): void
    {
        // Si la revisión aparece el mismo día que vence, no da tiempo a llamar
        // a nadie.
        $this->crearPlan([
            'activo_id'     => $this->crearActivo(),
            'proxima_fecha' => date('Y-m-d', strtotime('+10 days')),
            'aviso_dias'    => 15,
        ]);

        $this->assertSame(1, (new Preventivos())->generar()['abiertas']);
    }

    public function testCorrerloDosVecesNoDuplicaLaOrden(): void
    {
        // Las tareas programadas de los hostings a veces se disparan más de una
        // vez, y hay un botón de «generar ahora» que cualquiera puede pulsar.
        $activoId = $this->crearActivo();
        $this->crearPlan(['activo_id' => $activoId]);

        $preventivos = new Preventivos();
        $preventivos->generar();
        $segunda = $preventivos->generar();

        $this->assertSame(0, $segunda['abiertas']);
        $this->assertSame(1, $this->ordenes->where('activo_id', $activoId)->countAllResults());
    }

    public function testConLaAnteriorSinCerrarNoSeAbreLaSiguiente(): void
    {
        $activoId = $this->crearActivo();
        $planId   = $this->crearPlan(['activo_id' => $activoId, 'cada' => 1, 'periodo' => 'meses']);

        (new Preventivos())->generar();

        // Pasan dos meses y la de antes sigue ahí
        $this->planes->update($planId, ['proxima_fecha' => date('Y-m-d')]);
        $r = (new Preventivos())->generar();

        $this->assertSame(0, $r['abiertas']);
        $this->assertSame(1, $r['saltadas']);
    }

    public function testCerrandoLaAnteriorSiSeAbreLaSiguiente(): void
    {
        $activoId = $this->crearActivo();
        $planId   = $this->crearPlan(['activo_id' => $activoId, 'cada' => 1]);

        (new Preventivos())->generar();

        $orden = $this->ordenes->where('activo_id', $activoId)->first();
        $this->ordenes->update($orden['id'], ['estado' => 'verificada']);

        $this->planes->update($planId, ['proxima_fecha' => date('Y-m-d')]);

        $this->assertSame(1, (new Preventivos())->generar()['abiertas']);
    }

    public function testUnPlanPorCategoriaAbreUnaOrdenPorEquipo(): void
    {
        // Así, cuando se compre otro extintor, entra en el plan solo.
        $this->crearActivo('PRUEBA-Extintor cocina');
        $this->crearActivo('PRUEBA-Extintor recepción');
        $this->crearActivo('PRUEBA-Extintor muelle');

        $this->crearPlan(['categoria' => 'extintor']);

        $this->assertSame(3, (new Preventivos())->generar()['abiertas']);
    }

    public function testUnEquipoDadoDeBajaYaNoSeRevisa(): void
    {
        $activoId = $this->crearActivo();
        $this->activos->update($activoId, ['estado' => 'baja']);
        $this->crearPlan(['activo_id' => $activoId]);

        $this->assertSame(0, (new Preventivos())->generar()['abiertas']);
    }

    public function testUnPlanApagadoNoGeneraNada(): void
    {
        $this->crearPlan(['activo_id' => $this->crearActivo(), 'activo' => 0]);

        $this->assertSame(0, (new Preventivos())->generar()['abiertas']);
    }

    public function testUnPlanSinEquipoNiCategoriaGeneraUnaSolaOrden(): void
    {
        // Limpiar canaletas, revisar el muelle: tareas del alojamiento.
        $this->crearPlan(['nombre' => 'PRUEBA-Limpiar canaletas']);

        $this->assertSame(1, (new Preventivos())->generar()['abiertas']);
    }

    // ── Un preventivo no es una avería ──────────────────────────────────

    public function testUnaRevisionNoDejaElEquipoComoAveriado(): void
    {
        // Si lo hiciera, cada trimestre se marcarían equipos rotos que están
        // perfectamente, y con ellos se bloquearían cabañas.
        $activoId = $this->crearActivo();
        $this->crearPlan(['activo_id' => $activoId]);

        (new Preventivos())->generar();

        $this->assertSame('activo', $this->activos->find($activoId)['estado']);
    }

    public function testUnaRevisionDeUnEquipoCriticoNoSubeSolaDePrioridad(): void
    {
        // Estar programada no la convierte en una urgencia.
        $activoId = $this->crearActivo('PRUEBA-Planta eléctrica', 'planta_electrica');
        $this->activos->update($activoId, ['critico' => 1]);

        $this->crearPlan(['activo_id' => $activoId, 'prioridad' => 'media']);
        (new Preventivos())->generar();

        $orden = $this->ordenes->where('activo_id', $activoId)->first();

        $this->assertSame('media', $orden['prioridad']);
    }

    public function testLasInstruccionesViajanDentroDeLaOrden(): void
    {
        // Quien la atienda dentro de seis meses no tiene por qué acordarse.
        $this->crearPlan([
            'activo_id'     => $this->crearActivo(),
            'instrucciones' => 'PRUEBA-Mirar presión y precinto',
        ]);

        (new Preventivos())->generar();

        $orden = $this->ordenes->like('titulo', 'PRUEBA-', 'after')->first();

        $this->assertStringContainsString('Mirar presión y precinto', (string) $orden['descripcion']);
    }

    // ── El calendario no se desplaza ────────────────────────────────────

    public function testLaSiguienteFechaSeCuentaDesdeLaQueTocaba(): void
    {
        // Si una revisión anual se atrasa dos semanas, la del año que viene
        // sigue cayendo en su mes. Contando desde hoy, el calendario se iría
        // desplazando un poco cada año hasta no significar nada.
        $planId = $this->crearPlan([
            'activo_id'     => $this->crearActivo(),
            'cada'          => 12,
            'periodo'       => 'meses',
            'proxima_fecha' => date('Y-m-d', strtotime('-14 days')),
        ]);

        (new Preventivos())->generar();

        $plan     = $this->planes->find($planId);
        $esperado = date('Y-m-d', strtotime(date('Y-m-d', strtotime('-14 days')) . ' +12 months'));

        $this->assertSame($esperado, $plan['proxima_fecha']);
    }

    public function testUnPlanMuyAtrasadoNoSeQuedaClavadoEnElPasado(): void
    {
        // Con tres años de retraso, contar desde la fecha vieja dejaría la
        // siguiente también vencida y el plan se dispararía cada madrugada.
        $planId = $this->crearPlan([
            'activo_id'     => $this->crearActivo(),
            'cada'          => 1,
            'periodo'       => 'meses',
            'proxima_fecha' => date('Y-m-d', strtotime('-3 years')),
        ]);

        (new Preventivos())->generar();

        $this->assertGreaterThan(date('Y-m-d'), $this->planes->find($planId)['proxima_fecha']);
    }

    public function testLaFechaAvanzaAunqueLaOrdenSeHayaSaltado(): void
    {
        $activoId = $this->crearActivo();
        $planId   = $this->crearPlan(['activo_id' => $activoId, 'cada' => 1]);

        $preventivos = new Preventivos();
        $preventivos->generar();

        $this->planes->update($planId, ['proxima_fecha' => date('Y-m-d')]);
        $preventivos->generar();

        $this->assertGreaterThan(date('Y-m-d'), $this->planes->find($planId)['proxima_fecha']);
    }

    // ── Medidores ───────────────────────────────────────────────────────

    private function crearMedidor(array $extra = []): int
    {
        $medidores = new MedidorModel();
        $medidores->insert(array_merge([
            'nombre'        => 'PRUEBA-Contador general',
            'tipo'          => 'agua',
            'unidad_medida' => 'm³',
            'acumulativo'   => 1,
            'activa'        => 1,
        ], $extra));

        return (int) $medidores->getInsertID();
    }

    public function testElConsumoSeCalculaRestandoLaLecturaAnterior(): void
    {
        $id       = $this->crearMedidor();
        $lecturas = new LecturaMedidorModel();

        $lecturas->apuntar($id, 1000, date('Y-m-d', strtotime('-10 days')));
        $segunda = $lecturas->apuntar($id, 1085, date('Y-m-d'));

        $fila = $lecturas->find($segunda);

        $this->assertEqualsWithDelta(85, (float) $fila['consumo'], 0.001);
        $this->assertSame(10, (int) $fila['dias']);
    }

    public function testLaPrimeraLecturaNoTieneConsumo(): void
    {
        // Solo sirve de punto de partida: inventarle un consumo sería mentira.
        $id   = $this->crearMedidor();
        $fila = (new LecturaMedidorModel())->find(
            (new LecturaMedidorModel())->apuntar($id, 1000, date('Y-m-d'))
        );

        $this->assertNull($fila['consumo']);
    }

    public function testUnaLecturaQueBajaSeMarcaEnVezDeCalcularUnConsumoNegativo(): void
    {
        // Se tecleó mal, el contador dio la vuelta o lo cambiaron. Las tres hay
        // que mirarlas; un consumo negativo se colaría en todos los informes.
        $id       = $this->crearMedidor();
        $lecturas = new LecturaMedidorModel();

        $lecturas->apuntar($id, 1000, date('Y-m-d', strtotime('-5 days')));
        $fila = $lecturas->find($lecturas->apuntar($id, 900, date('Y-m-d')));

        $this->assertSame(1, (int) $fila['sospechosa']);
        $this->assertNull($fila['consumo']);
    }

    public function testEnUnTanqueLoQueBajaEsLoConsumido(): void
    {
        $id       = $this->crearMedidor(['acumulativo' => 0, 'tipo' => 'combustible', 'unidad_medida' => 'gal']);
        $lecturas = new LecturaMedidorModel();

        $lecturas->apuntar($id, 100, date('Y-m-d', strtotime('-7 days')));
        $fila = $lecturas->find($lecturas->apuntar($id, 62, date('Y-m-d')));

        $this->assertEqualsWithDelta(38, (float) $fila['consumo'], 0.001);
        $this->assertSame(0, (int) $fila['sospechosa']);
    }

    public function testLlenarElTanqueNoCuentaComoConsumo(): void
    {
        $id       = $this->crearMedidor(['acumulativo' => 0]);
        $lecturas = new LecturaMedidorModel();

        $lecturas->apuntar($id, 20, date('Y-m-d', strtotime('-2 days')));
        $fila = $lecturas->find($lecturas->apuntar($id, 100, date('Y-m-d')));

        $this->assertNull($fila['consumo']);
    }

    public function testDosLecturasDelMismoDiaRebotan(): void
    {
        // Partirían el consumo en dos trozos sin sentido.
        $id       = $this->crearMedidor();
        $lecturas = new LecturaMedidorModel();
        $lecturas->apuntar($id, 1000, date('Y-m-d'));

        $this->expectException(RuntimeException::class);
        $lecturas->apuntar($id, 1010, date('Y-m-d'));
    }

    public function testUnaLecturaDelFuturoRebota(): void
    {
        $id = $this->crearMedidor();

        $this->expectException(RuntimeException::class);
        (new LecturaMedidorModel())->apuntar($id, 1000, date('Y-m-d', strtotime('+2 days')));
    }

    public function testElConsumoDiarioPromediaVariasLecturas(): void
    {
        // Los intervalos no son iguales: comparar «120 en once días» con «95 en
        // siete» a ojo no dice nada.
        $id       = $this->crearMedidor();
        $lecturas = new LecturaMedidorModel();

        $lecturas->apuntar($id, 1000, date('Y-m-d', strtotime('-20 days')));
        $lecturas->apuntar($id, 1100, date('Y-m-d', strtotime('-10 days')));
        $lecturas->apuntar($id, 1200, date('Y-m-d'));

        // 200 en 20 días
        $this->assertEqualsWithDelta(10, (float) (new LecturaMedidorModel())->consumoDiarioReciente($id), 0.01);
    }

    public function testUnaLecturaEnUnMedidorQueNoExisteRebota(): void
    {
        $this->expectException(RuntimeException::class);
        (new LecturaMedidorModel())->apuntar(999999, 100, date('Y-m-d'));
    }
}
