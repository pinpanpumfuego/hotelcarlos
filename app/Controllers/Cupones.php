<?php

namespace App\Controllers;

use App\Models\CuponModel;
use App\Models\CuponUsoModel;

/** Gestión de cupones de descuento (solo gerencia). */
class Cupones extends BaseController
{
    private CuponModel $cupones;

    public function __construct()
    {
        $this->cupones = new CuponModel();
    }

    public function index()
    {
        return view('cupones/index', [
            'titulo'  => 'Cupones de descuento',
            'seccion' => 'cupones',
            'cupones' => $this->cupones->listado(),
        ]);
    }

    public function ver(int $id)
    {
        $cupon = $this->cupones->find($id);
        if ($cupon === null) {
            return redirect()->to('cupones')->with('error', 'El cupón no existe.');
        }

        return view('cupones/ver', [
            'titulo'  => 'Cupón ' . $cupon['codigo'],
            'seccion' => 'cupones',
            'cupon'   => $cupon,
            'usos'    => (new CuponUsoModel())->deCupon($id),
        ]);
    }

    public function guardar()
    {
        $datos = $this->datos();

        if (! $this->cupones->insert($datos)) {
            return redirect()->to('cupones')->withInput()->with('errores', $this->cupones->errors());
        }

        return redirect()->to('cupones')->with('ok', 'Cupón ' . $datos['codigo'] . ' creado.');
    }

    public function actualizar(int $id)
    {
        if ($this->cupones->find($id) === null) {
            return redirect()->to('cupones')->with('error', 'El cupón no existe.');
        }

        if (! $this->cupones->update($id, $this->datos())) {
            return redirect()->to('cupones')->withInput()->with('errores', $this->cupones->errors());
        }

        return redirect()->to('cupones')->with('ok', 'Cupón actualizado.');
    }

    public function alternar(int $id)
    {
        $cupon = $this->cupones->find($id);
        if ($cupon === null) {
            return redirect()->to('cupones')->with('error', 'El cupón no existe.');
        }

        $activo = (int) $cupon['activo'] === 1 ? 0 : 1;
        $this->cupones->update($id, ['activo' => $activo]);

        return redirect()->to('cupones')->with(
            'ok',
            $activo === 1 ? 'Cupón activado.' : 'Cupón desactivado: deja de poder canjearse.'
        );
    }

    public function eliminar(int $id)
    {
        $cupon = $this->cupones->find($id);
        if ($cupon === null) {
            return redirect()->to('cupones')->with('error', 'El cupón no existe.');
        }

        // Un cupón ya canjeado no se borra: se desactiva, para no perder el historial
        $canjes = (new CuponUsoModel())->where('cupon_id', $id)->countAllResults();
        if ($canjes > 0) {
            $this->cupones->update($id, ['activo' => 0]);

            return redirect()->to('cupones')->with(
                'ok',
                'Ese cupón ya se canjeó ' . $canjes . ' vez' . ($canjes > 1 ? 'es' : '') . ', así que se ha desactivado en vez de borrarlo (se conserva el historial).'
            );
        }

        $this->cupones->delete($id);

        return redirect()->to('cupones')->with('ok', 'Cupón eliminado.');
    }

    private function datos(): array
    {
        $vacio = static fn ($v) => trim((string) $v) === '' ? null : $v;

        return [
            'codigo'             => CuponModel::normalizar((string) $this->request->getPost('codigo')),
            'descripcion'        => trim((string) $this->request->getPost('descripcion')),
            'tipo'               => $this->request->getPost('tipo') === 'valor' ? 'valor' : 'porcentaje',
            'valor'              => (float) $this->request->getPost('valor'),
            'ambito'             => in_array($this->request->getPost('ambito'), ['alojamiento', 'restaurante', 'todo'], true)
                ? (string) $this->request->getPost('ambito') : 'alojamiento',
            'desde'              => $vacio($this->request->getPost('desde')),
            'hasta'              => $vacio($this->request->getPost('hasta')),
            'importe_minimo'     => $vacio($this->request->getPost('importe_minimo')),
            'descuento_maximo'   => $vacio($this->request->getPost('descuento_maximo')),
            'limite_usos'        => $vacio($this->request->getPost('limite_usos')),
            'limite_por_huesped' => $vacio($this->request->getPost('limite_por_huesped')),
            'en_web'             => $this->request->getPost('en_web') !== null ? 1 : 0,
            'en_recepcion'       => $this->request->getPost('en_recepcion') !== null ? 1 : 0,
            'en_tpv'             => $this->request->getPost('en_tpv') !== null ? 1 : 0,
            'activo'             => $this->request->getPost('activo') !== null ? 1 : 0,
        ];
    }
}
