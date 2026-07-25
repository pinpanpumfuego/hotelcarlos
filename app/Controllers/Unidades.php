<?php

namespace App\Controllers;

use App\Models\TipoUnidadModel;
use App\Models\UnidadModel;

class Unidades extends BaseController
{
    private UnidadModel $unidades;
    private TipoUnidadModel $tipos;

    public function __construct()
    {
        $this->unidades = new UnidadModel();
        $this->tipos    = new TipoUnidadModel();
    }

    public function index()
    {
        return view('unidades/index', [
            'titulo'   => 'Unidades',
            'seccion'  => 'unidades',
            'unidades' => $this->unidades->conTipo(),
        ]);
    }

    public function nueva()
    {
        return view('unidades/form', [
            'titulo'  => 'Nueva unidad',
            'seccion' => 'unidades',
            'tipos'   => $this->tipos->orderBy('nombre')->findAll(),
        ]);
    }

    public function guardar()
    {
        $datos = $this->request->getPost(['nombre', 'tipo_id', 'estado', 'notas']);

        if (! $this->unidades->insert($datos)) {
            return redirect()->back()->withInput()->with('errores', $this->unidades->errors());
        }

        return redirect()->to('unidades')->with('ok', 'Unidad creada correctamente.');
    }

    public function editar(int $id)
    {
        $unidad = $this->unidades->find($id);
        if ($unidad === null) {
            return redirect()->to('unidades')->with('error', 'La unidad no existe.');
        }

        return view('unidades/form', [
            'titulo'  => 'Editar unidad',
            'seccion' => 'unidades',
            'unidad'  => $unidad,
            'tipos'   => $this->tipos->orderBy('nombre')->findAll(),
        ]);
    }

    public function actualizar(int $id)
    {
        if ($this->unidades->find($id) === null) {
            return redirect()->to('unidades')->with('error', 'La unidad no existe.');
        }

        $datos = $this->request->getPost(['nombre', 'tipo_id', 'estado', 'notas']);

        if (! $this->unidades->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->unidades->errors());
        }

        return redirect()->to('unidades')->with('ok', 'Unidad actualizada correctamente.');
    }

    public function eliminar(int $id)
    {
        try {
            $this->unidades->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('unidades')->with('error', 'No se pudo eliminar: la unidad tiene datos asociados (p. ej. reservas).');
        }

        return redirect()->to('unidades')->with('ok', 'Unidad eliminada.');
    }
}
