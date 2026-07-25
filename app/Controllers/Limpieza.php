<?php

namespace App\Controllers;

use App\Models\LimpiezaModel;
use App\Models\UnidadModel;

/** Tablero de limpieza: la pantalla de trabajo del equipo de aseo. */
class Limpieza extends BaseController
{
    private UnidadModel $unidades;
    private LimpiezaModel $limpiezas;

    public function __construct()
    {
        $this->unidades  = new UnidadModel();
        $this->limpiezas = new LimpiezaModel();
    }

    public function index()
    {
        return view('limpieza/index', [
            'titulo'    => 'Limpieza',
            'seccion'   => 'limpieza',
            'unidades'  => $this->unidades->conTipo(),
            'enCurso'   => $this->limpiezas->todasEnCurso(),
            'historial' => $this->limpiezas->historial(8),
        ]);
    }

    /** Empezar a limpiar una cabaña que está pendiente de limpieza. */
    public function iniciar(int $unidadId)
    {
        $unidad = $this->unidades->find($unidadId);
        if ($unidad === null || $unidad['estado'] !== 'limpieza') {
            return redirect()->to('limpieza')->with('error', 'Esa cabaña no está pendiente de limpieza.');
        }
        if ($this->limpiezas->enCurso($unidadId) !== null) {
            return redirect()->to('limpieza')->with('error', 'Esa cabaña ya tiene una limpieza en curso.');
        }

        $this->limpiezas->insert([
            'unidad_id'  => $unidadId,
            'usuario_id' => session()->get('usuario_id'),
            'inicio'     => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('limpieza')->with('ok', 'Limpieza iniciada en ' . $unidad['nombre'] . '. ¡Buen trabajo!');
    }

    /** Terminar la limpieza: la cabaña vuelve a estar disponible. */
    public function finalizar(int $unidadId)
    {
        $registro = $this->limpiezas->enCurso($unidadId);
        if ($registro === null) {
            return redirect()->to('limpieza')->with('error', 'Esa cabaña no tiene una limpieza en curso.');
        }

        $this->limpiezas->update($registro['id'], [
            'fin'   => date('Y-m-d H:i:s'),
            'notas' => trim((string) $this->request->getPost('notas')) ?: null,
        ]);
        $this->unidades->update($unidadId, ['estado' => 'disponible']);

        return redirect()->to('limpieza')->with('ok', 'Cabaña lista y disponible para la venta.');
    }

    /** Marcar una cabaña disponible como pendiente de limpieza. */
    public function reportar(int $unidadId)
    {
        $unidad = $this->unidades->find($unidadId);
        if ($unidad === null || $unidad['estado'] !== 'disponible') {
            return redirect()->to('limpieza')->with('error', 'Solo se puede enviar a limpieza una cabaña disponible.');
        }

        $this->unidades->update($unidadId, ['estado' => 'limpieza']);

        return redirect()->to('limpieza')->with('ok', $unidad['nombre'] . ' marcada como pendiente de limpieza.');
    }
}
