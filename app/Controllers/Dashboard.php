<?php

namespace App\Controllers;

use App\Models\UnidadModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $unidadModel = new UnidadModel();
        $unidades    = $unidadModel->conTipo();

        $porEstado = array_count_values(array_column($unidades, 'estado'));

        return view('dashboard/index', [
            'titulo'        => 'Panel de control',
            'seccion'       => 'panel',
            'unidades'      => $unidades,
            'totalUnidades' => count($unidades),
            'disponibles'   => $porEstado['disponible'] ?? 0,
            'ocupadas'      => $porEstado['ocupada'] ?? 0,
            'noVendibles'   => ($porEstado['limpieza'] ?? 0) + ($porEstado['bloqueada'] ?? 0),
        ]);
    }
}
