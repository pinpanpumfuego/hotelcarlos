<?php

namespace App\Controllers;

use App\Models\TipoUnidadModel;

class Tipos extends BaseController
{
    private TipoUnidadModel $tipos;

    public function __construct()
    {
        $this->tipos = new TipoUnidadModel();
    }

    public function index()
    {
        return view('tipos/index', [
            'titulo'  => 'Tipos de alojamiento',
            'seccion' => 'tipos',
            'tipos'   => $this->tipos->orderBy('nombre')->findAll(),
        ]);
    }

    public function nuevo()
    {
        return view('tipos/form', [
            'titulo'  => 'Nuevo tipo de alojamiento',
            'seccion' => 'tipos',
        ]);
    }

    public function guardar()
    {
        $datos = $this->request->getPost(['nombre', 'descripcion', 'capacidad', 'tarifa_base']);

        if (! $this->tipos->insert($datos)) {
            return redirect()->back()->withInput()->with('errores', $this->tipos->errors());
        }

        return redirect()->to('tipos')->with('ok', 'Tipo de alojamiento creado correctamente.');
    }

    public function editar(int $id)
    {
        $tipo = $this->tipos->find($id);
        if ($tipo === null) {
            return redirect()->to('tipos')->with('error', 'El tipo no existe.');
        }

        return view('tipos/form', [
            'titulo'  => 'Editar tipo de alojamiento',
            'seccion' => 'tipos',
            'tipo'    => $tipo,
        ]);
    }

    public function actualizar(int $id)
    {
        if ($this->tipos->find($id) === null) {
            return redirect()->to('tipos')->with('error', 'El tipo no existe.');
        }

        $datos = $this->request->getPost(['nombre', 'descripcion', 'capacidad', 'tarifa_base']);

        if (! $this->tipos->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->tipos->errors());
        }

        return redirect()->to('tipos')->with('ok', 'Tipo de alojamiento actualizado.');
    }

    public function eliminar(int $id)
    {
        try {
            $this->tipos->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('tipos')->with('error', 'No se pudo eliminar: hay unidades que usan este tipo.');
        }

        return redirect()->to('tipos')->with('ok', 'Tipo de alojamiento eliminado.');
    }
}
