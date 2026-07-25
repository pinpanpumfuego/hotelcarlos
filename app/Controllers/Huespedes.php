<?php

namespace App\Controllers;

use App\Models\HuespedModel;

class Huespedes extends BaseController
{
    private HuespedModel $huespedes;

    public function __construct()
    {
        $this->huespedes = new HuespedModel();
    }

    public function index()
    {
        $buscar  = trim((string) $this->request->getGet('q'));
        $builder = $this->huespedes->orderBy('apellidos')->orderBy('nombre');

        if ($buscar !== '') {
            $builder = $builder->groupStart()
                ->like('nombre', $buscar)
                ->orLike('apellidos', $buscar)
                ->orLike('num_documento', $buscar)
                ->groupEnd();
        }

        return view('huespedes/index', [
            'titulo'      => 'Huéspedes',
            'seccion'     => 'huespedes',
            'huespedes'   => $builder->paginate(20),
            'paginador'   => $this->huespedes->pager,
            'totalActual' => $this->huespedes->pager->getTotal(),
            'buscar'      => $buscar,
        ]);
    }

    public function nuevo()
    {
        return view('huespedes/form', [
            'titulo'  => 'Nuevo huésped',
            'seccion' => 'huespedes',
        ]);
    }

    public function guardar()
    {
        $datos = $this->request->getPost(['nombre', 'apellidos', 'tipo_documento', 'num_documento', 'nacionalidad', 'telefono', 'email', 'notas']);

        try {
            if (! $this->huespedes->insert($datos)) {
                return redirect()->back()->withInput()->with('errores', $this->huespedes->errors());
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe un huésped con ese documento.']);
        }

        return redirect()->to('huespedes')->with('ok', 'Huésped registrado correctamente.');
    }

    public function editar(int $id)
    {
        $huesped = $this->huespedes->find($id);
        if ($huesped === null) {
            return redirect()->to('huespedes')->with('error', 'El huésped no existe.');
        }

        return view('huespedes/form', [
            'titulo'  => 'Editar huésped',
            'seccion' => 'huespedes',
            'huesped' => $huesped,
        ]);
    }

    public function actualizar(int $id)
    {
        if ($this->huespedes->find($id) === null) {
            return redirect()->to('huespedes')->with('error', 'El huésped no existe.');
        }

        $datos = $this->request->getPost(['nombre', 'apellidos', 'tipo_documento', 'num_documento', 'nacionalidad', 'telefono', 'email', 'notas']);

        try {
            if (! $this->huespedes->update($id, $datos)) {
                return redirect()->back()->withInput()->with('errores', $this->huespedes->errors());
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe otro huésped con ese documento.']);
        }

        return redirect()->to('huespedes')->with('ok', 'Huésped actualizado correctamente.');
    }

    public function eliminar(int $id)
    {
        try {
            $this->huespedes->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('huespedes')->with('error', 'No se pudo eliminar: el huésped tiene reservas asociadas.');
        }

        return redirect()->to('huespedes')->with('ok', 'Huésped eliminado.');
    }
}
