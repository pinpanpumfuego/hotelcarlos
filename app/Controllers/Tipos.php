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
        $datos = $this->recogerDatos();

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

        $datos = $this->recogerDatos();

        if (! $this->tipos->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->tipos->errors());
        }

        return redirect()->to('tipos')->with('ok', 'Tipo de alojamiento actualizado.');
    }

    /** Campos del formulario; los topes vacíos se guardan como «sin límite». */
    private function recogerDatos(): array
    {
        $datos = $this->request->getPost([
            'nombre', 'descripcion', 'capacidad', 'tarifa_base',
            'precio_minimo', 'precio_maximo', 'personas_incluidas',
            'suplemento_adulto', 'suplemento_nino',
        ]);

        foreach (['precio_minimo', 'precio_maximo'] as $campo) {
            if (trim((string) ($datos[$campo] ?? '')) === '') {
                $datos[$campo] = null;
            }
        }

        $datos['personas_incluidas'] = max(1, (int) ($datos['personas_incluidas'] ?? 2));
        $datos['suplemento_adulto']  = (float) ($datos['suplemento_adulto'] ?? 0);
        $datos['suplemento_nino']    = (float) ($datos['suplemento_nino'] ?? 0);

        return $datos;
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
