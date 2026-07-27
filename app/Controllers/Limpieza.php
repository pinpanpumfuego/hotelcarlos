<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Housekeeping;
use App\Models\ChecklistPuntoModel;
use App\Models\LimpiezaModel;
use App\Models\LimpiezaPuntoModel;
use App\Models\ObjetoOlvidadoModel;
use App\Models\UnidadModel;

/**
 * Tablero de limpieza: la pantalla de trabajo del equipo de aseo.
 *
 * Todo el ciclo vive en `Libraries\Housekeeping`, no aquí: lo usan también el
 * móvil y el check-out, y tres copias de la misma lógica acabarían discrepando.
 */
class Limpieza extends BaseController
{
    private UnidadModel $unidades;
    private LimpiezaModel $limpiezas;
    private Housekeeping $hk;

    public function __construct()
    {
        $this->unidades  = new UnidadModel();
        $this->limpiezas = new LimpiezaModel();
        $this->hk        = new Housekeeping();
    }

    public function index()
    {
        return view('limpieza/index', [
            'titulo'          => 'Limpieza',
            'seccion'         => 'limpieza',
            'unidades'        => $this->unidades->conTipo(),
            'tareas'          => $this->limpiezas->tablero(),
            'inspeccion'      => $this->limpiezas->porInspeccionar(),
            'exigeInspeccion' => $this->limpiezas->exigeInspeccion(),
            'historial'       => $this->limpiezas->historial(10),
            'tipos'           => LimpiezaModel::TIPOS,
            'estados'         => LimpiezaModel::ESTADOS,
            'objetos'         => (new ObjetoOlvidadoModel())->sinCerrar(),
            'yo'              => (int) session()->get('usuario_id'),
        ]);
    }

    /** La ficha de una tarea: su checklist punto por punto. */
    public function tarea(int $id)
    {
        $tarea = $this->limpiezas
            ->select('limpiezas.*, unidades.nombre AS cabana, usuarios.nombre AS quien')
            ->join('unidades', 'unidades.id = limpiezas.unidad_id', 'left')
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id', 'left')
            ->where('limpiezas.id', $id)
            ->first();

        if ($tarea === null) {
            return redirect()->to('limpieza')->with('error', 'Esa tarea no existe.');
        }

        $puntos = new LimpiezaPuntoModel();

        return view('limpieza/tarea', [
            'titulo'   => 'Limpieza · ' . ($tarea['cabana'] ?? ''),
            'seccion'  => 'limpieza',
            'tarea'    => $tarea,
            'puntos'   => $puntos->deTarea($id),
            'progreso' => $puntos->progreso($id),
            'tipos'    => LimpiezaModel::TIPOS,
            'estados'  => LimpiezaModel::ESTADOS,
        ]);
    }

    // ── El ciclo ────────────────────────────────────────────────────────

    /** Abre una tarea a mano: limpieza durante la estancia, profunda… */
    public function abrir(int $unidadId)
    {
        $unidad = $this->unidades->find($unidadId);
        if ($unidad === null) {
            return redirect()->to('limpieza')->with('error', 'Esa cabaña no existe.');
        }

        $id = $this->hk->abrirTarea(
            $unidadId,
            (string) $this->request->getPost('tipo'),
            (string) $this->request->getPost('prioridad')
        );

        return redirect()->to('limpieza/tarea/' . $id)->with('ok', 'Tarea abierta para ' . $unidad['nombre'] . '.');
    }

    public function empezar(int $id)
    {
        if (! $this->hk->empezar($id, (int) session()->get('usuario_id'))) {
            return redirect()->to('limpieza')->with('error', 'Esa tarea no se puede empezar ahora.');
        }

        return redirect()->to('limpieza/tarea/' . $id)->with('ok', '¡A ello! El reloj ya corre.');
    }

    /** Marca un punto del checklist, con su foto si la exige. */
    public function punto(int $id, int $puntoId)
    {
        $puntos = new LimpiezaPuntoModel();
        $punto  = $puntos->find($puntoId);

        if ($punto === null || (int) $punto['limpieza_id'] !== $id) {
            return redirect()->to('limpieza/tarea/' . $id)->with('error', 'Ese punto no es de esta tarea.');
        }

        $ruta = $punto['foto'];
        $foto = $this->request->getFile('foto');

        if ($foto !== null && $foto->isValid() && ! $foto->hasMoved()) {
            // Fuera del árbol público: son fotos del interior de las cabañas
            $destino = WRITEPATH . 'uploads/limpieza/';
            if (! is_dir($destino)) {
                mkdir($destino, 0775, true);
            }
            $ruta = $foto->getRandomName();
            $foto->move($destino, $ruta);
        }

        $puntos->marcar(
            $puntoId,
            (bool) $this->request->getPost('hecho'),
            $ruta,
            (string) $this->request->getPost('nota')
        );

        return redirect()->to('limpieza/tarea/' . $id);
    }

    public function terminar(int $id)
    {
        $r = $this->hk->terminar($id, trim((string) $this->request->getPost('notas')) ?: null);

        if (! $r['ok']) {
            return redirect()->to('limpieza/tarea/' . $id)->with('error', $r['error']);
        }

        return redirect()->to('limpieza')->with(
            'ok',
            $this->limpiezas->exigeInspeccion()
                ? 'Terminada. Queda a la espera de que alguien la inspeccione.'
                : 'Terminada. La cabaña ya se puede entregar.'
        );
    }

    // ── Inspección ──────────────────────────────────────────────────────

    public function aprobar(int $id)
    {
        if (! $this->hk->aprobar($id, (int) session()->get('usuario_id'))) {
            return redirect()->to('limpieza')->with('error', 'Esa tarea no está esperando inspección.');
        }

        return redirect()->to('limpieza')->with('ok', 'Inspeccionada. La cabaña ya se puede entregar.');
    }

    public function rechazar(int $id)
    {
        $r = $this->hk->rechazar(
            $id,
            (int) session()->get('usuario_id'),
            (string) $this->request->getPost('motivo')
        );

        if (! $r['ok']) {
            return redirect()->to('limpieza')->with('error', $r['error']);
        }

        return redirect()->to('limpieza')->with('ok', 'Devuelta para rehacer. Quien la hizo lo verá en su tablero.');
    }

    // ── Estados de la cabaña ────────────────────────────────────────────

    public function noMolestar(int $unidadId)
    {
        $this->hk->noMolestar($unidadId, (bool) $this->request->getPost('activar'));

        return redirect()->to('limpieza')->with('ok', 'Estado actualizado.');
    }

    public function bloquear(int $unidadId)
    {
        $this->hk->bloquear(
            $unidadId,
            (string) $this->request->getPost('motivo'),
            trim((string) $this->request->getPost('nota')) ?: null,
            $this->request->getPost('hasta') ?: null
        );

        return redirect()->to('limpieza')->with('ok', 'Cabaña bloqueada. No se venderá hasta que la desbloquees.');
    }

    public function desbloquear(int $unidadId)
    {
        $this->hk->desbloquear($unidadId);

        return redirect()->to('limpieza')->with('ok', 'Cabaña desbloqueada.');
    }

    /** Marcar una cabaña como pendiente de limpieza sin venir de un check-out. */
    public function reportar(int $unidadId)
    {
        $unidad = $this->unidades->find($unidadId);
        if ($unidad === null) {
            return redirect()->to('limpieza')->with('error', 'Esa cabaña no existe.');
        }

        $this->hk->abrirTarea($unidadId, 'estancia');

        return redirect()->to('limpieza')->with('ok', $unidad['nombre'] . ' marcada como pendiente de limpieza.');
    }

    // ── Objetos olvidados ───────────────────────────────────────────────

    public function objetos()
    {
        $modelo = new ObjetoOlvidadoModel();

        return view('limpieza/objetos', [
            'titulo'   => 'Objetos olvidados',
            'seccion'  => 'limpieza',
            'objetos'  => $modelo->listado($this->request->getGet('estado')),
            'plazo'    => $modelo->pasadosDePlazo(),
            'estados'  => ObjetoOlvidadoModel::ESTADOS,
            'unidades' => $this->unidades->orderBy('nombre')->findAll(),
            'filtro'   => $this->request->getGet('estado'),
        ]);
    }

    public function guardarObjeto()
    {
        $descripcion = trim((string) $this->request->getPost('descripcion'));
        if ($descripcion === '') {
            return redirect()->to('limpieza/objetos')->with('error', 'Di qué se ha encontrado.');
        }

        $ruta = null;
        $foto = $this->request->getFile('foto');
        if ($foto !== null && $foto->isValid() && ! $foto->hasMoved()) {
            $destino = WRITEPATH . 'uploads/objetos/';
            if (! is_dir($destino)) {
                mkdir($destino, 0775, true);
            }
            $ruta = $foto->getRandomName();
            $foto->move($destino, $ruta);
        }

        (new ObjetoOlvidadoModel())->apuntar([
            'unidad_id'   => (int) $this->request->getPost('unidad_id') ?: null,
            'limpieza_id' => (int) $this->request->getPost('limpieza_id') ?: null,
            'descripcion' => $descripcion,
            'donde'       => trim((string) $this->request->getPost('donde')) ?: null,
            'foto'        => $ruta,
        ]);

        return redirect()->to('limpieza/objetos')->with('ok', 'Apuntado. Recepción puede avisar al huésped.');
    }

    public function estadoObjeto(int $id)
    {
        $ok = (new ObjetoOlvidadoModel())->cambiarEstado(
            $id,
            (string) $this->request->getPost('estado'),
            trim((string) $this->request->getPost('notas')) ?: null
        );

        return redirect()->to('limpieza/objetos')->with(
            $ok ? 'ok' : 'error',
            $ok ? 'Actualizado.' : 'Estado no válido.'
        );
    }

    // ── Checklist y política ────────────────────────────────────────────

    public function checklist()
    {
        return view('limpieza/checklist', [
            'titulo'          => 'Checklist de limpieza',
            'seccion'         => 'limpieza',
            'puntos'          => (new ChecklistPuntoModel())->porTipoUnidad(),
            'tipos'           => (new \App\Models\TipoUnidadModel())->orderBy('nombre')->findAll(),
            'tiposLimpieza'   => LimpiezaModel::TIPOS,
            'exigeInspeccion' => $this->limpiezas->exigeInspeccion(),
            'rendimiento'     => $this->limpiezas->rendimiento(date('Y-m-01'), date('Y-m-d')),
            'porPersona'      => $this->limpiezas->porPersona(date('Y-m-01'), date('Y-m-d')),
        ]);
    }

    public function guardarPunto()
    {
        $texto = trim((string) $this->request->getPost('texto'));
        if ($texto === '') {
            return redirect()->to('limpieza/checklist')->with('error', 'Escribe qué hay que repasar.');
        }

        $tipos = array_values(array_intersect(
            (array) ($this->request->getPost('tipos') ?? []),
            array_keys(LimpiezaModel::TIPOS)
        ));

        (new ChecklistPuntoModel())->insert([
            'tipo_unidad_id' => (int) $this->request->getPost('tipo_unidad_id') ?: null,
            'tipos'          => $tipos === [] ? null : implode(',', $tipos),
            'texto'          => mb_substr($texto, 0, 200),
            'zona'           => trim((string) $this->request->getPost('zona')) ?: null,
            'orden'          => (int) $this->request->getPost('orden'),
            'exige_foto'     => $this->request->getPost('exige_foto') ? 1 : 0,
            'activo'         => 1,
        ]);

        return redirect()->to('limpieza/checklist')->with('ok', 'Punto añadido al checklist.');
    }

    public function eliminarPunto(int $id)
    {
        (new ChecklistPuntoModel())->delete($id);

        return redirect()->to('limpieza/checklist')->with('ok', 'Punto quitado.');
    }

    /** Enciende o apaga la inspección. */
    public function politica()
    {
        (new \App\Models\ConfiguracionModel())->guardarPares([
            'limpieza_exige_inspeccion' => $this->request->getPost('inspeccion') ? '1' : '0',
        ]);

        return redirect()->to('limpieza/checklist')->with('ok', 'Política de inspección guardada.');
    }
}
