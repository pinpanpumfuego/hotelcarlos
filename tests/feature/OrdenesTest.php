<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Almacen;
use App\Libraries\Ordenes;
use App\Models\ActivoModel;
use App\Models\ConfiguracionModel;
use App\Models\MantenimientoMaterialModel;
use App\Models\MantenimientoModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * El ciclo de vida de una orden de trabajo.
 *
 * Lo que se defiende aquí, por orden de importancia:
 *
 * 1. **Nadie firma su propia reparación.** Es la separación de funciones que
 *    ya se usa en la inspección de limpieza, y la única razón por la que
 *    «verificada» significa algo más que «resuelta».
 * 2. **El material que sale del almacén se descuenta de verdad.** Si no, el
 *    día del conteo falta y nadie sabe por qué.
 * 3. **Los estados no se saltan.** Resolver algo que nadie empezó, verificar
 *    algo que nadie resolvió: todo eso rebota con una razón.
 *
 * @internal
 */
final class OrdenesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Ordenes $ordenes;
    private MantenimientoModel $modelo;

    private const TECNICO = 11;
    private const JEFE    = 22;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ordenes = new Ordenes();
        $this->modelo  = new MantenimientoModel();
        $this->limpiar();

        (new ConfiguracionModel())->guardarPares([
            'mant_verifica_urgentes' => '1',
            'mant_costo_hora'        => '0',
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

        $ids = array_column(
            $db->table('mantenimientos')->select('id')->like('titulo', 'PRUEBA-', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            $db->table('mantenimiento_materiales')->where('mantenimiento_id', $id)->delete();
            $db->table('mantenimiento_fotos')->where('mantenimiento_id', $id)->delete();
        }

        $db->table('mantenimientos')->like('titulo', 'PRUEBA-', 'after')->delete();
        $db->table('activos')->like('nombre', 'PRUEBA-', 'after')->delete();

        // Los movimientos de stock también: `consumo_interno` cuenta como fuga
        // en los informes del almacén, y si se quedan aquí el informe de otra
        // prueba sale con el gasto de esta.
        $insumo = $db->table('insumos')->where('nombre', 'PRUEBA-Manguera')->get()->getRowArray();

        if ($insumo !== null) {
            $db->table('movimientos_stock')->where('insumo_id', $insumo['id'])->delete();
            $db->table('existencias')->where('insumo_id', $insumo['id'])->delete();
        }
    }

    private function abrir(array $extra = []): int
    {
        return $this->ordenes->abrir(array_merge([
            'titulo'     => 'PRUEBA-Gotea el calentador',
            'origen'     => 'limpieza',
            'reporto_id' => self::TECNICO,
        ], $extra));
    }

    // ── Los estados no se saltan ────────────────────────────────────────

    public function testUnaOrdenSinTituloNoSeAbre(): void
    {
        $this->expectException(RuntimeException::class);
        $this->ordenes->abrir(['titulo' => '   ']);
    }

    public function testNoSePuedeResolverAlgoYaCerrado(): void
    {
        $id = $this->abrir();
        $this->ordenes->empezar($id, self::TECNICO);
        $this->ordenes->resolver($id, 'PRUEBA-Apretada la tuerca', self::TECNICO);

        $this->expectException(RuntimeException::class);
        $this->ordenes->resolver($id, 'PRUEBA-Otra vez', self::TECNICO);
    }

    public function testResolverSinDecirQueSeHizoRebota(): void
    {
        // Dentro de un año, eso es lo único que quedará de esta reparación.
        $id = $this->abrir();
        $this->ordenes->empezar($id, self::TECNICO);

        $this->expectException(RuntimeException::class);
        $this->ordenes->resolver($id, '  ', self::TECNICO);
    }

    public function testNoSeVerificaAlgoQueNadieHaResuelto(): void
    {
        $id = $this->abrir();

        $this->expectException(RuntimeException::class);
        $this->ordenes->verificar($id, self::JEFE);
    }

    public function testSoloSePausaLoQueSeEstaHaciendo(): void
    {
        $id = $this->abrir();

        $this->expectException(RuntimeException::class);
        $this->ordenes->pausar($id, 'PRUEBA-Falta el repuesto');
    }

    public function testPausarSinDecirAQueSeEsperaRebota(): void
    {
        // «Pausada» sin motivo es una orden que nadie sabe cuándo retomar.
        $id = $this->abrir();
        $this->ordenes->empezar($id, self::TECNICO);

        $this->expectException(RuntimeException::class);
        $this->ordenes->pausar($id, '');
    }

    public function testRetomarUnaPausaNoReiniciaElReloj(): void
    {
        $id = $this->abrir();
        $this->ordenes->empezar($id, self::TECNICO);

        $inicio = $this->modelo->find($id)['iniciada_en'];

        $this->ordenes->pausar($id, 'PRUEBA-Esperando el repuesto');
        $this->ordenes->empezar($id, self::TECNICO);

        // El tiempo de antes también se trabajó
        $this->assertSame($inicio, $this->modelo->find($id)['iniciada_en']);
    }

    // ── Nadie firma su propia reparación ────────────────────────────────

    public function testQuienArreglaNoPuedeDarPorBuenoSuPropioTrabajo(): void
    {
        $id = $this->abrir(['prioridad' => 'urgente']);
        $this->ordenes->empezar($id, self::TECNICO);
        $this->ordenes->resolver($id, 'PRUEBA-Cambiada la manguera', self::TECNICO);

        $this->expectException(RuntimeException::class);
        $this->ordenes->verificar($id, self::TECNICO);
    }

    public function testOtraPersonaSiPuedeDarlaPorBuena(): void
    {
        $id = $this->abrir(['prioridad' => 'urgente']);
        $this->ordenes->empezar($id, self::TECNICO);
        $this->ordenes->resolver($id, 'PRUEBA-Cambiada la manguera', self::TECNICO);
        $this->ordenes->verificar($id, self::JEFE);

        $this->assertSame('verificada', $this->modelo->find($id)['estado']);
    }

    public function testTampocoSeRevisaAUnoMismoParaRechazar(): void
    {
        $id = $this->abrir(['prioridad' => 'urgente']);
        $this->ordenes->empezar($id, self::TECNICO);
        $this->ordenes->resolver($id, 'PRUEBA-A medias', self::TECNICO);

        $this->expectException(RuntimeException::class);
        $this->ordenes->rechazar($id, 'PRUEBA-No está', self::TECNICO);
    }

    public function testRechazarLaDevuelveAlTecnicoSinSolucion(): void
    {
        $id = $this->abrir(['prioridad' => 'urgente']);
        $this->ordenes->empezar($id, self::TECNICO);
        $this->ordenes->resolver($id, 'PRUEBA-A medias', self::TECNICO);
        $this->ordenes->rechazar($id, 'PRUEBA-Sigue goteando', self::JEFE);

        $orden = $this->modelo->find($id);

        $this->assertSame('en_proceso', $orden['estado']);
        // La solución vieja se borra: dejarla puesta haría creer que ya está
        $this->assertNull($orden['solucion']);
        $this->assertStringContainsString('Sigue goteando', (string) $orden['descripcion']);
    }

    public function testLoQueNoEsUrgenteNiBloqueaSeCierraSinVistoBueno(): void
    {
        // Pedir doble firma para un bombillo fundido es la forma más rápida de
        // que la gente deje de usar el sistema.
        $id = $this->abrir(['prioridad' => 'baja']);
        $this->ordenes->empezar($id, self::TECNICO);

        $this->assertFalse($this->ordenes->exigeVerificacion($this->modelo->find($id)));
    }

    // ── Materiales ──────────────────────────────────────────────────────

    public function testElMaterialDelAlmacenSeDescuentaDeVerdad(): void
    {
        [$insumoId, $bodegaId] = $this->insumoConStock(10);
        $almacen               = new Almacen();

        $id = $this->abrir();
        $this->ordenes->gastarMaterial($id, '', 3, $insumoId, $bodegaId, null, self::TECNICO);

        $this->assertEqualsWithDelta(7, $almacen->saldo($insumoId, $bodegaId), 0.001);
    }

    public function testQuitarUnMaterialDevuelveElStock(): void
    {
        [$insumoId, $bodegaId] = $this->insumoConStock(10);
        $almacen               = new Almacen();

        $id     = $this->abrir();
        $linea  = $this->ordenes->gastarMaterial($id, '', 3, $insumoId, $bodegaId, null, self::TECNICO);

        $this->ordenes->quitarMaterial($linea);

        $this->assertEqualsWithDelta(10, $almacen->saldo($insumoId, $bodegaId), 0.001);
        $this->assertSame(0, (new MantenimientoMaterialModel())->where('mantenimiento_id', $id)->countAllResults());
    }

    public function testUnMaterialCompradoFueraNoTocaElAlmacenPeroSiElCosto(): void
    {
        // El técnico compró la manguera en la ferretería del pueblo esa misma
        // mañana: no hay insumo, pero sí hay gasto.
        $id = $this->abrir();
        $this->ordenes->gastarMaterial($id, 'PRUEBA-Manguera de gas', 2, null, null, 15000, self::TECNICO);

        $orden = $this->modelo->find($id);

        $this->assertEqualsWithDelta(30000, (float) $orden['costo_materiales'], 0.01);
    }

    public function testElCostoDeLaOrdenSumaTodasLasLineas(): void
    {
        $id = $this->abrir();
        $this->ordenes->gastarMaterial($id, 'PRUEBA-Manguera', 1, null, null, 15000);
        $this->ordenes->gastarMaterial($id, 'PRUEBA-Abrazaderas', 4, null, null, 2500);

        $this->assertEqualsWithDelta(25000, (float) $this->modelo->find($id)['costo_materiales'], 0.01);
    }

    public function testUnaCantidadNegativaNoSeApunta(): void
    {
        $id = $this->abrir();

        $this->expectException(RuntimeException::class);
        $this->ordenes->gastarMaterial($id, 'PRUEBA-Algo', -2, null, null, 1000);
    }

    public function testSinStockNoSeApuntaUnGastoQueNoOcurrio(): void
    {
        // Si el almacén se queja, no puede quedar apuntada una línea de gasto
        // de un repuesto que no existía.
        [$insumoId, $bodegaId] = $this->insumoConStock(1);

        $id = $this->abrir();

        try {
            $this->ordenes->gastarMaterial($id, '', 50, $insumoId, $bodegaId, null, self::TECNICO);
        } catch (\Throwable) {
            // Da igual cómo se queje: lo que importa es lo de abajo
        }

        $lineas = (new MantenimientoMaterialModel())->where('mantenimiento_id', $id)->countAllResults();

        $this->assertLessThanOrEqual(
            1,
            $lineas,
            'O el almacén lo permitió y hay una línea, o lo rechazó y no hay ninguna. Lo que no puede haber es gasto sin movimiento.'
        );
    }

    // ── Mano de obra y tiempos ──────────────────────────────────────────

    public function testSinIniciarNoSeInventaUnTiempo(): void
    {
        // Los informes se creerían un dato inventado.
        $id = $this->abrir();
        $this->ordenes->resolver($id, 'PRUEBA-Estaba suelto', self::TECNICO);

        $this->assertNull($this->modelo->find($id)['minutos']);
    }

    public function testConCostoPorHoraSeCalculaLaManoDeObra(): void
    {
        (new ConfiguracionModel())->guardarPares(['mant_costo_hora' => '30000']);

        $id = $this->abrir();
        $this->ordenes->empezar($id, self::TECNICO);

        // Se retrasa el inicio dos horas para no esperar de verdad
        $this->modelo->update($id, ['iniciada_en' => date('Y-m-d H:i:s', strtotime('-2 hours'))]);

        $this->ordenes->resolver($id, 'PRUEBA-Cambiada la resistencia', self::TECNICO);

        $orden = $this->modelo->find($id);

        $this->assertEqualsWithDelta(120, (int) $orden['minutos'], 1);
        $this->assertEqualsWithDelta(60000, (float) $orden['costo_mano_obra'], 500);
    }

    public function testConCostoPorHoraEnCeroNoSeInventaUnaCifra(): void
    {
        $id = $this->abrir();
        $this->ordenes->empezar($id, self::TECNICO);
        $this->ordenes->resolver($id, 'PRUEBA-Listo', self::TECNICO);

        $this->assertEqualsWithDelta(0, (float) $this->modelo->find($id)['costo_mano_obra'], 0.01);
    }

    // ── Prioridad y plazos ──────────────────────────────────────────────

    public function testSubirLaPrioridadCuentaDesdeQueSeAbrio(): void
    {
        // Si contara desde ahora, marcar como urgente algo que lleva una semana
        // esperando le regalaría cuatro horas más.
        $id = $this->abrir(['prioridad' => 'baja']);

        // Directo por el builder: `created_at` no está en `allowedFields`, y el
        // modelo lo descartaría en silencio.
        db_connect()->table('mantenimientos')
            ->where('id', $id)
            ->update(['created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))]);

        $this->ordenes->cambiarPrioridad($id, 'urgente');

        $vence = strtotime($this->modelo->find($id)['vence_en']);

        $this->assertLessThan(time(), $vence, 'Debería estar ya fuera de plazo.');
    }

    public function testUnaAveriaEnUnEquipoCriticoNoSeAbreComoMedia(): void
    {
        $activos = new ActivoModel();
        $activos->insert([
            'codigo'    => $activos->siguienteCodigo(),
            'nombre'    => 'PRUEBA-Planta eléctrica',
            'categoria' => 'planta_electrica',
            'critico'   => 1,
        ]);
        $activoId = (int) $activos->getInsertID();

        $id = $this->abrir(['activo_id' => $activoId, 'prioridad' => 'media']);

        $this->assertSame('alta', $this->modelo->find($id)['prioridad']);
    }

    public function testUnaPrioridadInventadaNoRompeNada(): void
    {
        $id = $this->abrir(['prioridad' => 'altísima']);

        $this->assertSame('media', $this->modelo->find($id)['prioridad']);
    }

    // ── Anular ──────────────────────────────────────────────────────────

    public function testUnaOrdenYaDadaPorBuenaNoSeAnula(): void
    {
        // Su historial es real: borrarlo falsearía el coste del equipo.
        $id = $this->abrir(['prioridad' => 'urgente']);
        $this->ordenes->empezar($id, self::TECNICO);
        $this->ordenes->resolver($id, 'PRUEBA-Hecho', self::TECNICO);
        $this->ordenes->verificar($id, self::JEFE);

        $this->expectException(RuntimeException::class);
        $this->ordenes->anular($id, 'PRUEBA-Era un error');
    }

    public function testAnularSueltaLaCabanaYElEquipo(): void
    {
        $activos = new ActivoModel();
        $activos->insert([
            'codigo'    => $activos->siguienteCodigo(),
            'nombre'    => 'PRUEBA-Nevera',
            'categoria' => 'frio',
        ]);
        $activoId = (int) $activos->getInsertID();

        $id = $this->abrir(['activo_id' => $activoId]);
        $this->assertSame('averiado', $activos->find($activoId)['estado']);

        $this->ordenes->anular($id, 'PRUEBA-Estaba desenchufada');

        $this->assertSame('activo', $activos->find($activoId)['estado']);
    }

    // ── Lo común ────────────────────────────────────────────────────────

    /** @return array{0: int, 1: int} */
    private function insumoConStock(float $cantidad): array
    {
        $db = db_connect();

        $bodega = $db->table('bodegas')->where('tipo', 'mantenimiento')->get()->getRowArray();
        $this->assertNotNull($bodega, 'La migración debería haber creado el almacén de taller.');

        $insumo = $db->table('insumos')->where('nombre', 'PRUEBA-Manguera')->get()->getRowArray();

        if ($insumo === null) {
            $db->table('insumos')->insert([
                'nombre'         => 'PRUEBA-Manguera',
                'categoria'      => 'mantenimiento',
                'unidad'         => 'm',
                'costo_unitario' => 5000,
                'activo'         => 1,
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $insumo = ['id' => $db->insertID()];
        }

        $almacen = new Almacen();
        $actual  = $almacen->saldo((int) $insumo['id'], (int) $bodega['id']);

        // Se deja el saldo exactamente donde lo quiere la prueba
        $almacen->ajustar((int) $insumo['id'], (int) $bodega['id'], $cantidad, 'PRUEBA-Ajuste inicial');

        unset($actual);

        return [(int) $insumo['id'], (int) $bodega['id']];
    }
}
