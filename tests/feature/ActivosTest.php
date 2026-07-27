<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivoModel;
use App\Models\MantenimientoModel;
use App\Models\RolModel;
use App\Models\UnidadModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Activos y su enganche con las averías.
 *
 * Lo que se comprueba aquí es sobre todo que las dos verdades no se separen: si
 * el calentador tiene una avería abierta, el listado de equipos no puede decir
 * que está en servicio. Cuando eso pasa, nadie vuelve a fiarse de la pantalla.
 *
 * @internal
 */
final class ActivosTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private ActivoModel $activos;
    private MantenimientoModel $incidencias;
    private int $unidadId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activos     = new ActivoModel();
        $this->incidencias = new MantenimientoModel();
        $this->limpiar();

        $this->unidadId = $this->cabanaDePrueba();
    }

    /**
     * Una cabaña propia, creada aquí.
     *
     * Antes esto cogía la primera que hubiera, y las pruebas que la bloquean le
     * cambiaban el estado a una cabaña de verdad. En la base de pruebas da
     * igual, pero es la clase de atajo que un día se ejecuta contra otra base.
     */
    private function cabanaDePrueba(): int
    {
        $db = db_connect();

        $tipo = $db->table('tipos_unidad')->get()->getRowArray();

        if ($tipo === null) {
            $db->table('tipos_unidad')->insert([
                'nombre'      => 'PRUEBA-Tipo',
                'capacidad'   => 2,
                'tarifa_base' => 100000,
            ]);
            $tipo = ['id' => $db->insertID()];
        }

        $existe = $db->table('unidades')->where('nombre', 'PRUEBA-Cabaña')->get()->getRowArray();

        if ($existe !== null) {
            return (int) $existe['id'];
        }

        (new UnidadModel())->insert([
            'tipo_id' => (int) $tipo['id'],
            'nombre'  => 'PRUEBA-Cabaña',
            'estado'  => 'disponible',
        ]);

        return (int) db_connect()->insertID();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $ids = array_column(
            $db->table('activos')->select('id')->like('nombre', 'PRUEBA-', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            $db->table('mantenimientos')->where('activo_id', $id)->delete();
            $db->table('activo_documentos')->where('activo_id', $id)->delete();
        }

        $db->table('activos')->like('nombre', 'PRUEBA-', 'after')->delete();
        $db->table('mantenimientos')->like('titulo', 'PRUEBA-', 'after')->delete();

        // La cabaña de prueba se deja en su sitio y en su estado: se reutiliza
        // entre pruebas, pero cada una la encuentra limpia y disponible.
        $db->table('unidades')->where('nombre', 'PRUEBA-Cabaña')->update([
            'estado'          => 'disponible',
            'estado_limpieza' => 'limpia',
            'motivo_bloqueo'  => null,
            'nota_bloqueo'    => null,
            'bloqueada_hasta' => null,
        ]);
    }

    private function como(string $perfil): array
    {
        $rol = (new RolModel())->porClave($perfil);
        $this->assertNotNull($rol, "Falta el perfil «{$perfil}».");

        return [
            'usuario_id'     => 1,
            'usuario_nombre' => 'Prueba',
            'usuario_rol'    => 'gerencia',
            'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    private function crearActivo(array $extra = []): int
    {
        $this->activos->insert(array_merge([
            'codigo'    => $this->activos->siguienteCodigo(),
            'nombre'    => 'PRUEBA-Calentador',
            'categoria' => 'calentador',
            'unidad_id' => $this->unidadId,
            'estado'    => 'activo',
        ], $extra));

        return (int) $this->activos->getInsertID();
    }

    // ── Los códigos ─────────────────────────────────────────────────────

    public function testLosCodigosNoSeRepiten(): void
    {
        $vistos = [];

        for ($i = 0; $i < 5; $i++) {
            $codigo = $this->activos->siguienteCodigo();
            $this->assertNotContains($codigo, $vistos, 'Se repitió un código.');

            $vistos[] = $codigo;
            $this->activos->insert([
                'codigo' => $codigo, 'nombre' => 'PRUEBA-Equipo ' . $i, 'categoria' => 'otro',
            ]);
        }
    }

    public function testElCodigoSigueElPrefijoConfigurado(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+-\d{4}$/', $this->activos->siguienteCodigo());
    }

    public function testUnCodigoOcupadoAManoNoBloqueaElSiguiente(): void
    {
        // Alguien da de alta uno escribiendo el código él mismo en la base.
        $codigo = $this->activos->siguienteCodigo();
        $this->activos->insert(['codigo' => $codigo, 'nombre' => 'PRUEBA-A mano', 'categoria' => 'otro']);

        $this->assertNotSame($codigo, $this->activos->siguienteCodigo());
    }

    // ── El escaneo ──────────────────────────────────────────────────────

    public function testEscanearLaEtiquetaAbreLaFichaDelEquipo(): void
    {
        $id     = $this->crearActivo();
        $activo = $this->activos->find($id);

        $r = $this->withSession($this->como('mantenimiento'))->get('activo/' . $activo['codigo']);

        $r->assertOK();
        $r->assertSee('PRUEBA-Calentador');
        $r->assertSee('¿Algo no funciona?');
    }

    public function testUnCodigoQueNoExisteLoDiceEnVezDeReventar(): void
    {
        // Pasa de verdad: una etiqueta de un equipo dado de baja que nadie
        // despegó, o una pegatina de otro sitio.
        $r = $this->withSession($this->como('mantenimiento'))->get('activo/NOEXISTE-9999');

        $r->assertOK();
        $r->assertSee('No hay ningún equipo con el código');
    }

    public function testSinSesionElQrNoEnsenaNada(): void
    {
        // El QR se puede fotografiar desde fuera: no puede ser una llave.
        $id     = $this->crearActivo();
        $activo = $this->activos->find($id);

        $this->assertTrue($this->get('activo/' . $activo['codigo'])->isRedirect());
    }

    public function testElQrLlevaALaDireccionDelEquipo(): void
    {
        $id     = $this->crearActivo();
        $activo = $this->activos->find($id);

        $r = $this->withSession($this->como('mantenimiento'))->get('activos/qr/' . $id);

        $r->assertOK();
        $r->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', (string) $r->response()->getBody());

        // Que ese QR se pueda volver a leer se comprueba en QrTest, con un
        // lector escrito aparte. Aquí solo importa que la ruta lo sirva.
    }

    // ── Que las dos verdades no se separen ──────────────────────────────

    public function testReportarUnaAveriaDejaElEquipoComoAveriado(): void
    {
        $id = $this->crearActivo();

        $this->withSession($this->como('mantenimiento'))->post('mantenimiento/guardar', [
            'csrf_test_name' => csrf_hash(),
            'titulo'         => 'PRUEBA-No calienta',
            'activo_id'      => $id,
            'prioridad'      => 'alta',
        ]);

        $this->assertSame('averiado', $this->activos->find($id)['estado']);
    }

    public function testResolverLaAveriaLoDevuelveAServicio(): void
    {
        $id = $this->crearActivo();
        $sesion = $this->como('mantenimiento');

        $this->withSession($sesion)->post('mantenimiento/guardar', [
            'csrf_test_name' => csrf_hash(),
            'titulo'         => 'PRUEBA-No calienta',
            'activo_id'      => $id,
        ]);

        $inc = $this->incidencias->where('activo_id', $id)->first();

        $this->withSession($sesion)->post('mantenimiento/resolver/' . $inc['id'], [
            'csrf_test_name' => csrf_hash(),
            'solucion'       => 'PRUEBA-Se cambió la resistencia',
        ]);

        $this->assertSame('activo', $this->activos->find($id)['estado']);
    }

    public function testConDosAveriasAbiertasNoVuelveAServicioAlCerrarUna(): void
    {
        // Darlo por bueno con algo sin cerrar sería mentir, y el listado
        // diría que todo está bien mientras el tablero dice lo contrario.
        $id     = $this->crearActivo();
        $sesion = $this->como('mantenimiento');

        foreach (['PRUEBA-No calienta', 'PRUEBA-Gotea'] as $titulo) {
            $this->withSession($sesion)->post('mantenimiento/guardar', [
                'csrf_test_name' => csrf_hash(),
                'titulo'         => $titulo,
                'activo_id'      => $id,
            ]);
        }

        $primera = $this->incidencias->where('activo_id', $id)->orderBy('id')->first();

        $this->withSession($sesion)->post('mantenimiento/resolver/' . $primera['id'], [
            'csrf_test_name' => csrf_hash(),
            'solucion'       => 'PRUEBA-Resistencia cambiada',
        ]);

        $this->assertSame('averiado', $this->activos->find($id)['estado']);
    }

    public function testLaAveriaHeredaLaCabanaDelEquipo(): void
    {
        // Quien escanea desde el móvil no va a rellenar la cabaña otra vez.
        $id = $this->crearActivo();

        $this->withSession($this->como('mantenimiento'))->post('mantenimiento/guardar', [
            'csrf_test_name' => csrf_hash(),
            'titulo'         => 'PRUEBA-Gotea',
            'activo_id'      => $id,
        ]);

        $inc = $this->incidencias->where('activo_id', $id)->first();

        $this->assertSame($this->unidadId, (int) $inc['unidad_id']);
    }

    public function testBloquearDejaLaCabanaSinVenderYAlResolverVuelveSucia(): void
    {
        $id       = $this->crearActivo();
        $unidades = new UnidadModel();
        $sesion   = $this->como('mantenimiento');

        $this->withSession($sesion)->post('mantenimiento/guardar', [
            'csrf_test_name' => csrf_hash(),
            'titulo'         => 'PRUEBA-Fuga de gas',
            'activo_id'      => $id,
            'prioridad'      => 'urgente',
            'bloquear'       => '1',
        ]);

        $unidad = $unidades->find($this->unidadId);
        $this->assertSame('bloqueada', $unidad['estado']);
        $this->assertSame('mantenimiento', $unidad['motivo_bloqueo']);

        $inc = $this->incidencias->where('activo_id', $id)->first();

        $this->withSession($sesion)->post('mantenimiento/resolver/' . $inc['id'], [
            'csrf_test_name' => csrf_hash(),
            'solucion'       => 'PRUEBA-Cambiada la manguera',
        ]);

        // Todavía no: era urgente y bloqueó la cabaña, así que hace falta que
        // otra persona lo dé por bueno. Volver a venderla esa misma tarde con
        // la palabra de quien arregló la fuga de gas es justo lo que no puede
        // pasar.
        $this->assertSame('bloqueada', $unidades->find($this->unidadId)['estado']);

        $otra               = $this->como('recepcion');
        $otra['usuario_id'] = 2;

        $this->withSession($otra)->post('mantenimiento/verificar/' . $inc['id'], [
            'csrf_test_name' => csrf_hash(),
        ]);

        $unidad = $unidades->find($this->unidadId);
        $this->assertSame('disponible', $unidad['estado']);
        // Después de una reparación queda polvo: si saliera directa a
        // disponible y limpia, el siguiente huésped se la encuentra así.
        $this->assertSame('sucia', $unidad['estado_limpieza']);
    }

    // ── La pantalla de la orden ─────────────────────────────────────────

    public function testLaPantallaDeLaOrdenSeAbre(): void
    {
        $id     = $this->crearActivo();
        $sesion = $this->como('mantenimiento');

        $this->withSession($sesion)->post('mantenimiento/guardar', [
            'csrf_test_name' => csrf_hash(),
            'titulo'         => 'PRUEBA-No calienta',
            'activo_id'      => $id,
        ]);

        $inc = $this->incidencias->where('activo_id', $id)->first();
        $r   = $this->withSession($sesion)->get('mantenimiento/ver/' . $inc['id']);

        $r->assertOK();
        $r->assertSee('PRUEBA-No calienta');
        $r->assertSee('Qué hacer ahora');
    }

    public function testHousekeepingNoPuedeDarPorBuenaUnaReparacion(): void
    {
        // No es solo que no salga el botón: la ruta tampoco se puede llamar.
        $id     = $this->crearActivo();
        $sesion = $this->como('mantenimiento');

        $this->withSession($sesion)->post('mantenimiento/guardar', [
            'csrf_test_name' => csrf_hash(),
            'titulo'         => 'PRUEBA-Fuga',
            'activo_id'      => $id,
            'prioridad'      => 'urgente',
        ]);

        $inc = $this->incidencias->where('activo_id', $id)->first();

        $this->withSession($sesion)->post('mantenimiento/resolver/' . $inc['id'], [
            'csrf_test_name' => csrf_hash(),
            'solucion'       => 'PRUEBA-Hecho',
        ]);

        $limpieza               = $this->como('housekeeping');
        $limpieza['usuario_id'] = 3;

        $r = $this->withSession($limpieza)->post('mantenimiento/verificar/' . $inc['id'], [
            'csrf_test_name' => csrf_hash(),
        ]);

        $this->assertTrue($r->isRedirect());
        $this->assertSame('resuelta', $this->incidencias->find($inc['id'])['estado']);
    }

    public function testLasPantallasNuevasSeAbren(): void
    {
        // Una vista con una variable que el controlador no pasa revienta al
        // pintarse, no al guardarse: solo se ve entrando de verdad.
        $sesion = $this->como('mantenimiento');

        foreach (['preventivos', 'preventivos/nuevo', 'medidores', 'mantenimiento/informes'] as $ruta) {
            $r = $this->withSession($sesion)->get($ruta);

            $this->assertFalse($r->isRedirect(), "/{$ruta} rebotó.");
            $r->assertOK();
        }
    }

    public function testRestauranteNoLlegaAlPlanPreventivoNiALosMedidores(): void
    {
        $sesion = $this->como('restaurante');

        foreach (['preventivos', 'medidores'] as $ruta) {
            $this->assertTrue($this->withSession($sesion)->get($ruta)->isRedirect(), "/{$ruta} se abrió.");
        }
    }

    // ── El plazo interno ────────────────────────────────────────────────

    public function testCadaPrioridadTieneSuPlazo(): void
    {
        $urgente = strtotime($this->incidencias->vencimiento('urgente'));
        $baja    = strtotime($this->incidencias->vencimiento('baja'));

        $this->assertGreaterThan($urgente, $baja, 'Lo urgente tiene que vencer antes.');
        $this->assertGreaterThan(time(), $urgente);
    }

    public function testElPlazoSeGuardaAlAbrirla(): void
    {
        // Si mañana se cambia el plazo de «urgente», lo abierto hoy conserva el
        // suyo. Recalcularlo al mirar movería la meta a mitad de partido.
        $id = $this->crearActivo();

        $this->withSession($this->como('mantenimiento'))->post('mantenimiento/guardar', [
            'csrf_test_name' => csrf_hash(),
            'titulo'         => 'PRUEBA-Ruido raro',
            'activo_id'      => $id,
            'prioridad'      => 'urgente',
        ]);

        $inc = $this->incidencias->where('activo_id', $id)->first();

        $this->assertNotNull($inc['vence_en']);
        $this->assertGreaterThan(time(), strtotime($inc['vence_en']));
    }

    // ── Permisos ────────────────────────────────────────────────────────

    public function testHousekeepingConsultaLosEquiposPeroNoLosCambia(): void
    {
        $sesion = $this->como('housekeeping');

        $this->assertFalse($this->withSession($sesion)->get('activos')->isRedirect());

        $r = $this->withSession($sesion)->get('activos/nuevo');
        $this->assertTrue($r->isRedirect(), 'Housekeeping no debería poder dar de alta equipos.');
    }

    public function testRestauranteNiSiquieraVeElInventario(): void
    {
        $r = $this->withSession($this->como('restaurante'))->get('activos');

        $this->assertTrue($r->isRedirect());
    }

    public function testDarDeBajaNoBorraElHistorial(): void
    {
        $id     = $this->crearActivo();
        $sesion = $this->como('mantenimiento');

        $this->withSession($sesion)->post('mantenimiento/guardar', [
            'csrf_test_name' => csrf_hash(),
            'titulo'         => 'PRUEBA-Se quemó',
            'activo_id'      => $id,
        ]);

        $this->withSession($sesion)->post('activos/baja/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'baja_motivo'    => 'PRUEBA-No compensaba repararlo',
        ]);

        $activo = $this->activos->find($id);

        $this->assertSame('baja', $activo['estado']);
        $this->assertNotNull($activo['baja_en']);
        $this->assertSame(1, $this->incidencias->where('activo_id', $id)->countAllResults());
    }

    public function testUnaBajaSinMotivoNoPasa(): void
    {
        $id = $this->crearActivo();

        $this->withSession($this->como('mantenimiento'))->post('activos/baja/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'baja_motivo'    => '   ',
        ]);

        $this->assertSame('activo', $this->activos->find($id)['estado']);
    }

    // ── Garantías ───────────────────────────────────────────────────────

    public function testAvisaDeLasGarantiasQueSeAcaban(): void
    {
        $this->crearActivo(['garantia_hasta' => date('Y-m-d', strtotime('+20 days'))]);

        $nombres = array_column($this->activos->garantiasQueVencen(), 'nombre');

        $this->assertContains('PRUEBA-Calentador', $nombres);
    }

    public function testLaGarantiaYaVencidaNoAvisa(): void
    {
        $this->crearActivo(['garantia_hasta' => date('Y-m-d', strtotime('-1 day'))]);

        $nombres = array_column($this->activos->garantiasQueVencen(), 'nombre');

        $this->assertNotContains('PRUEBA-Calentador', $nombres);
    }

    public function testUnaGarantiaLejanaTampocoMeteRuido(): void
    {
        $this->crearActivo(['garantia_hasta' => date('Y-m-d', strtotime('+2 years'))]);

        $nombres = array_column($this->activos->garantiasQueVencen(), 'nombre');

        $this->assertNotContains('PRUEBA-Calentador', $nombres);
    }
}
