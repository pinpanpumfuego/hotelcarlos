<?php

namespace App\Controllers;

use App\Models\MantenimientoModel;
use App\Models\UnidadModel;

/** Incidencias de mantenimiento: cualquier miembro del personal puede reportar. */
class Mantenimiento extends BaseController
{
    private MantenimientoModel $incidencias;
    private UnidadModel $unidades;

    public function __construct()
    {
        $this->incidencias = new MantenimientoModel();
        $this->unidades    = new UnidadModel();
    }

    public function index()
    {
        return view('mantenimiento/index', [
            'titulo'      => 'Mantenimiento',
            'seccion'     => 'mantenimiento',
            'abiertas'    => $this->incidencias->listar(true),
            'resueltas'   => $this->incidencias->listar(false),
            'unidades'    => $this->unidades->orderBy('nombre')->findAll(),
            'prioridades' => MantenimientoModel::PRIORIDADES,
        ]);
    }

    public function guardar()
    {
        $titulo = trim((string) $this->request->getPost('titulo'));
        if ($titulo === '') {
            return redirect()->to('mantenimiento')->with('error', 'Describe brevemente la incidencia.');
        }

        $unidadId = (int) $this->request->getPost('unidad_id') ?: null;

        $this->incidencias->insert([
            'unidad_id'   => $unidadId,
            'ubicacion'   => $unidadId === null ? (trim((string) $this->request->getPost('ubicacion')) ?: 'Zonas comunes') : null,
            'titulo'      => $titulo,
            'descripcion' => trim((string) $this->request->getPost('descripcion')) ?: null,
            'prioridad'   => array_key_exists($this->request->getPost('prioridad'), MantenimientoModel::PRIORIDADES)
                ? $this->request->getPost('prioridad') : 'media',
            'estado'     => 'abierta',
            'reporto_id' => session()->get('usuario_id'),
        ]);

        // Si la avería compromete la cabaña, se bloquea y deja de venderse al instante
        $aviso = '';
        if ($unidadId !== null && $this->request->getPost('bloquear')) {
            $this->unidades->update($unidadId, ['estado' => 'bloqueada']);
            $aviso = ' La cabaña quedó bloqueada y no se venderá hasta resolverla.';
        }

        return redirect()->to('mantenimiento')->with('ok', 'Incidencia reportada.' . $aviso);
    }

    public function iniciar(int $id)
    {
        $incidencia = $this->incidencias->find($id);
        if ($incidencia === null || $incidencia['estado'] !== 'abierta') {
            return redirect()->to('mantenimiento')->with('error', 'Esa incidencia no está abierta.');
        }

        $this->incidencias->update($id, ['estado' => 'en_proceso']);

        return redirect()->to('mantenimiento')->with('ok', 'Incidencia en proceso.');
    }

    public function resolver(int $id)
    {
        $incidencia = $this->incidencias->find($id);
        if ($incidencia === null || $incidencia['estado'] === 'resuelta') {
            return redirect()->to('mantenimiento')->with('error', 'Esa incidencia ya está resuelta.');
        }

        $solucion = trim((string) $this->request->getPost('solucion'));
        if ($solucion === '') {
            return redirect()->to('mantenimiento')->with('error', 'Describe qué se hizo para resolverla.');
        }

        $this->incidencias->update($id, [
            'estado'      => 'resuelta',
            'solucion'    => $solucion,
            'resolvio_id' => session()->get('usuario_id'),
            'resuelta_en' => date('Y-m-d H:i:s'),
        ]);

        // Si la cabaña estaba bloqueada, pasa a limpieza antes de volver a venderse
        $aviso = '';
        if ($incidencia['unidad_id'] !== null) {
            $unidad = $this->unidades->find($incidencia['unidad_id']);
            if ($unidad !== null && $unidad['estado'] === 'bloqueada') {
                $this->unidades->update($unidad['id'], ['estado' => 'limpieza']);
                $aviso = ' La cabaña pasó a limpieza para dejarla lista.';
            }
        }

        return redirect()->to('mantenimiento')->with('ok', 'Incidencia resuelta.' . $aviso);
    }
}
