<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Housekeeping;
use App\Models\ChecklistPuntoModel;
use App\Models\ConfiguracionModel;
use App\Models\LimpiezaModel;
use App\Models\LimpiezaPuntoModel;
use App\Models\ObjetoOlvidadoModel;
use App\Models\UnidadModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * El ciclo de una limpieza, de principio a fin.
 *
 * Lo que más se comprueba es que **el estado de la tarea y el de la cabaña no
 * se separen nunca**. Son dos tablas, pero una sola realidad: una tarea en
 * curso y una cabaña que dice estar limpia es el descuadre que hace que nadie
 * se fíe del tablero.
 *
 * @internal
 */
final class LimpiezaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Housekeeping $hk;
    private LimpiezaModel $limpiezas;
    private UnidadModel $unidades;
    private int $unidadId;
    private int $tipoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hk        = new Housekeeping();
        $this->limpiezas = new LimpiezaModel();
        $this->unidades  = new UnidadModel();
        $this->limpiar();

        $db = db_connect();

        $db->table('tipos_unidad')->insert(['nombre' => 'Tipo prueba limpieza', 'capacidad' => 2, 'tarifa_base' => 100000]);
        $this->tipoId = (int) $db->insertID();

        $db->table('unidades')->insert([
            'tipo_id' => $this->tipoId, 'nombre' => 'Cabaña prueba limpieza',
            'estado' => 'disponible', 'estado_limpieza' => 'limpia',
        ]);
        $this->unidadId = (int) $db->insertID();

        (new ConfiguracionModel())->guardarPares(['limpieza_exige_inspeccion' => '1']);
        session()->set(['usuario_id' => 1, 'usuario_nombre' => 'Prueba']);
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        (new ConfiguracionModel())->guardarPares(['limpieza_exige_inspeccion' => '1']);
        session()->remove(['usuario_id', 'usuario_nombre']);
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $unidades = $db->table('unidades')->select('id')->like('nombre', 'prueba limpieza')->get()->getResultArray();
        foreach ($unidades as $u) {
            $tareas = $db->table('limpiezas')->select('id')->where('unidad_id', $u['id'])->get()->getResultArray();
            foreach ($tareas as $t) {
                $db->table('limpieza_puntos')->where('limpieza_id', $t['id'])->delete();
            }
            $db->table('limpiezas')->where('unidad_id', $u['id'])->delete();
            $db->table('objetos_olvidados')->where('unidad_id', $u['id'])->delete();
            $db->table('unidades')->where('id', $u['id'])->delete();
        }

        $tipos = $db->table('tipos_unidad')->select('id')->like('nombre', 'prueba limpieza')->get()->getResultArray();
        foreach ($tipos as $t) {
            $db->table('checklist_puntos')->where('tipo_unidad_id', $t['id'])->delete();
            $db->table('tipos_unidad')->where('id', $t['id'])->delete();
        }
    }

    private function estadoLimpieza(): string
    {
        return (string) $this->unidades->find($this->unidadId)['estado_limpieza'];
    }

    private function punto(bool $exigeFoto = false): int
    {
        return (int) (new ChecklistPuntoModel())->insert([
            'tipo_unidad_id' => $this->tipoId,
            'texto'          => $exigeFoto ? 'Foto del baño' : 'Repasar el suelo',
            'exige_foto'     => $exigeFoto ? 1 : 0,
            'activo'         => 1,
        ], true);
    }

    // ── El ciclo ────────────────────────────────────────────────────────

    public function testAbrirTareaDejaLaCabanaSucia(): void
    {
        $this->hk->abrirTarea($this->unidadId);

        $this->assertSame('sucia', $this->estadoLimpieza());
    }

    public function testAbrirDosVecesNoCreaDosTareas(): void
    {
        // Dos tareas para la misma cabaña harían que dos personas limpiaran
        // lo mismo.
        $a = $this->hk->abrirTarea($this->unidadId);
        $b = $this->hk->abrirTarea($this->unidadId);

        $this->assertSame($a, $b);
    }

    public function testEmpezarPoneLaCabanaEnLimpieza(): void
    {
        $id = $this->hk->abrirTarea($this->unidadId);

        $this->assertTrue($this->hk->empezar($id, 1));
        $this->assertSame('en_limpieza', $this->estadoLimpieza());
        $this->assertNotNull($this->limpiezas->find($id)['inicio']);
    }

    public function testTerminarSinInspeccionDejaLaCabanaLista(): void
    {
        (new ConfiguracionModel())->guardarPares(['limpieza_exige_inspeccion' => '0']);

        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);

        $this->assertTrue($this->hk->terminar($id)['ok']);
        $this->assertSame('inspeccionada', $this->estadoLimpieza());
        $this->assertSame('inspeccionada', $this->limpiezas->find($id)['estado']);
    }

    public function testConInspeccionLaCabanaQuedaEsperando(): void
    {
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);
        $this->hk->terminar($id);

        $this->assertSame('limpia', $this->estadoLimpieza(), 'Limpia, pero todavía sin inspeccionar.');
        $this->assertSame('hecha', $this->limpiezas->find($id)['estado']);
    }

    public function testAprobarDejaLaCabanaLista(): void
    {
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);
        $this->hk->terminar($id);

        $this->assertTrue($this->hk->aprobar($id, 2));
        $this->assertSame('inspeccionada', $this->estadoLimpieza());
        $this->assertSame(2, (int) $this->limpiezas->find($id)['inspector_id']);
    }

    // ── Rechazo y reprocesos ────────────────────────────────────────────

    public function testRechazarDevuelveLaTareaYSumaUnReproceso(): void
    {
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);
        $this->hk->terminar($id);

        $this->assertTrue($this->hk->rechazar($id, 2, 'Falta limpiar la ducha')['ok']);

        $tarea = $this->limpiezas->find($id);
        $this->assertSame('rechazada', $tarea['estado']);
        $this->assertSame(1, (int) $tarea['reprocesos']);
        $this->assertSame('sucia', $this->estadoLimpieza());
    }

    public function testRechazarSinMotivoNoSePuede(): void
    {
        // «Está mal» no le dice a nadie qué hay que arreglar, y la tarea
        // volvería igual.
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);
        $this->hk->terminar($id);

        $r = $this->hk->rechazar($id, 2, '   ');

        $this->assertFalse($r['ok']);
        $this->assertSame('hecha', $this->limpiezas->find($id)['estado']);
    }

    public function testAlRechazarLosPuntosVuelvenASinHacer(): void
    {
        // Hay que repasarlos otra vez, no dar por buenos los de la vez anterior.
        $this->punto();
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);

        $puntos = new LimpiezaPuntoModel();
        $suyos  = $puntos->deTarea($id);
        $puntos->marcar((int) $suyos[0]['id'], true);

        $this->hk->terminar($id);
        $this->hk->rechazar($id, 2, 'Repásalo');

        $this->assertCount(1, $puntos->sinHacer($id));
    }

    public function testUnaTareaRechazadaSePuedeVolverAEmpezar(): void
    {
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);
        $this->hk->terminar($id);
        $this->hk->rechazar($id, 2, 'Repásalo');

        $this->assertTrue($this->hk->empezar($id, 1));
        $this->assertNull($this->limpiezas->find($id)['motivo_rechazo'], 'Empieza limpia de rechazos.');
    }

    // ── Checklist ───────────────────────────────────────────────────────

    public function testAlAbrirSeCopiaElChecklistDelTipo(): void
    {
        $this->punto();
        $id = $this->hk->abrirTarea($this->unidadId);

        $this->assertCount(1, (new LimpiezaPuntoModel())->deTarea($id));
    }

    public function testNoSePuedeTerminarConPuntosSinMarcar(): void
    {
        $this->punto();
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);

        $r = $this->hk->terminar($id);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('sin marcar', $r['error']);
    }

    public function testNoSePuedeTerminarSinLaFotoDeUnPuntoCritico(): void
    {
        // La foto es lo que convierte un «ya está» en algo comprobable.
        $this->punto(true);
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);

        $puntos = new LimpiezaPuntoModel();
        $suyos  = $puntos->deTarea($id);
        $puntos->marcar((int) $suyos[0]['id'], true);   // marcado, pero sin foto

        $r = $this->hk->terminar($id);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('fotos', $r['error']);
    }

    public function testConLaFotoPuestaSiSePuedeTerminar(): void
    {
        $this->punto(true);
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->empezar($id, 1);

        $puntos = new LimpiezaPuntoModel();
        $suyos  = $puntos->deTarea($id);
        $puntos->marcar((int) $suyos[0]['id'], true, 'foto123.jpg');

        $this->assertTrue($this->hk->terminar($id)['ok']);
    }

    public function testElTextoDelPuntoSeCopiaYNoSeEnlaza(): void
    {
        // Si mañana cambia el checklist, lo que se hizo ayer tiene que seguir
        // diciendo lo que decía.
        $puntoId = $this->punto();
        $id      = $this->hk->abrirTarea($this->unidadId);

        (new ChecklistPuntoModel())->update($puntoId, ['texto' => 'Texto cambiado después']);

        $suyos = (new LimpiezaPuntoModel())->deTarea($id);
        $this->assertSame('Repasar el suelo', $suyos[0]['texto']);
    }

    public function testUnPuntoSoloDeSalidaNoSaleEnUnaLimpiezaDeEstancia(): void
    {
        (new ChecklistPuntoModel())->insert([
            'tipo_unidad_id' => $this->tipoId,
            'tipos'          => 'salida',
            'texto'          => 'Cambiar todas las sábanas',
            'activo'         => 1,
        ]);

        $id = $this->hk->abrirTarea($this->unidadId, 'estancia');

        $this->assertCount(0, (new LimpiezaPuntoModel())->deTarea($id));
    }

    // ── Estados de la cabaña ────────────────────────────────────────────

    public function testNoMolestarNoPierdeLaTarea(): void
    {
        // Perder la tarea significaría que esa cabaña se queda sin limpiar y
        // nadie se entera.
        $id = $this->hk->abrirTarea($this->unidadId);
        $this->hk->noMolestar($this->unidadId);

        $this->assertSame('no_molestar', $this->estadoLimpieza());
        $this->assertNotNull($this->limpiezas->viva($this->unidadId));
        $this->assertSame($id, (int) $this->limpiezas->viva($this->unidadId)['id']);
    }

    public function testBloquearGuardaElMotivo(): void
    {
        $this->hk->bloquear($this->unidadId, 'bioseguridad', 'Desinfección', date('Y-m-d', strtotime('+3 days')));

        $unidad = $this->unidades->find($this->unidadId);

        $this->assertSame('bloqueada', $unidad['estado']);
        $this->assertSame('bioseguridad', $unidad['motivo_bloqueo']);
        $this->assertSame('Desinfección', $unidad['nota_bloqueo']);
    }

    public function testUnMotivoInventadoSeGuardaComoOtro(): void
    {
        $this->hk->bloquear($this->unidadId, 'lo_que_sea');

        $this->assertSame('otro', $this->unidades->find($this->unidadId)['motivo_bloqueo']);
    }

    public function testDesbloquearLimpiaElMotivo(): void
    {
        $this->hk->bloquear($this->unidadId, 'dano', 'Gotera');
        $this->hk->desbloquear($this->unidadId);

        $unidad = $this->unidades->find($this->unidadId);

        $this->assertSame('disponible', $unidad['estado']);
        $this->assertNull($unidad['motivo_bloqueo']);
    }

    // ── El tablero ──────────────────────────────────────────────────────

    public function testLoUrgenteVaPrimero(): void
    {
        $db = db_connect();
        $db->table('unidades')->insert([
            'tipo_id' => $this->tipoId, 'nombre' => 'Otra prueba limpieza',
            'estado' => 'disponible', 'estado_limpieza' => 'limpia',
        ]);
        $otra = (int) $db->insertID();

        $this->hk->abrirTarea($this->unidadId, 'salida', 'normal');
        $this->hk->abrirTarea($otra, 'salida', 'urgente');

        $tablero = $this->limpiezas->tablero();
        $primera = reset($tablero);

        $this->assertSame($otra, (int) $primera['unidad_id']);
    }

    // ── Objetos olvidados ───────────────────────────────────────────────

    public function testUnObjetoSeApuntaConSuCabana(): void
    {
        $modelo = new ObjetoOlvidadoModel();
        $id     = $modelo->apuntar([
            'unidad_id'   => $this->unidadId,
            'descripcion' => 'Cargador de móvil',
            'donde'       => 'Junto a la cama',
        ]);

        $objeto = $modelo->find($id);

        $this->assertSame('guardado', $objeto['estado']);
        $this->assertSame('Cargador de móvil', $objeto['descripcion']);
    }

    public function testElObjetoRecorreSusEstados(): void
    {
        $modelo = new ObjetoOlvidadoModel();
        $id     = $modelo->apuntar(['unidad_id' => $this->unidadId, 'descripcion' => 'Gafas']);

        $modelo->cambiarEstado($id, 'avisado');
        $this->assertNotNull($modelo->find($id)['avisado_el']);

        $modelo->cambiarEstado($id, 'devuelto');
        $this->assertNotNull($modelo->find($id)['cerrado_el']);
    }

    public function testUnEstadoInventadoNoSeAcepta(): void
    {
        $modelo = new ObjetoOlvidadoModel();
        $id     = $modelo->apuntar(['unidad_id' => $this->unidadId, 'descripcion' => 'Libro']);

        $this->assertFalse($modelo->cambiarEstado($id, 'perdido_para_siempre'));
        $this->assertSame('guardado', $modelo->find($id)['estado']);
    }
}
