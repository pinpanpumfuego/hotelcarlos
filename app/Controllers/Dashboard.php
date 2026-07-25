<?php

namespace App\Controllers;

use App\Models\FolioModel;
use App\Models\ReservaModel;
use App\Models\UnidadModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $hoy = date('Y-m-d');

        $unidadModel  = new UnidadModel();
        $reservaModel = new ReservaModel();

        $unidades  = $unidadModel->conTipo();
        $porEstado = array_count_values(array_column($unidades, 'estado'));

        $base = fn () => $reservaModel
            ->select('reservas.*, huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos, unidades.nombre AS unidad_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id');

        $llegadasHoy = $base()->whereIn('reservas.estado', ['pendiente', 'confirmada'])
            ->where('reservas.fecha_entrada', $hoy)->orderBy('reservas.id')->findAll();

        $salidasHoy = $base()->where('reservas.estado', 'checkin')
            ->where('reservas.fecha_salida <=', $hoy)->orderBy('reservas.fecha_salida')->findAll();

        $pendientes = $base()->where('reservas.estado', 'pendiente')
            ->orderBy('reservas.created_at', 'DESC')->findAll();

        $proximas = $base()->whereIn('reservas.estado', ['confirmada'])
            ->where('reservas.fecha_entrada >', $hoy)
            ->orderBy('reservas.fecha_entrada')->findAll(5);

        // Ingresos cobrados este mes (pagos del folio)
        $folioModel  = new FolioModel();
        $ingresosMes = (float) ($folioModel->selectSum('valor')
            ->where('tipo', 'pago')
            ->where('created_at >=', date('Y-m-01 00:00:00'))
            ->first()['valor'] ?? 0);

        $totalUnidades = count($unidades);
        $ocupadas      = $porEstado['ocupada'] ?? 0;

        return view('dashboard/index', [
            'titulo'        => 'Panel de control',
            'seccion'       => 'panel',
            'unidades'      => $unidades,
            'totalUnidades' => $totalUnidades,
            'ocupadas'      => $ocupadas,
            'disponibles'   => $porEstado['disponible'] ?? 0,
            'noVendibles'   => ($porEstado['limpieza'] ?? 0) + ($porEstado['bloqueada'] ?? 0),
            'ocupacionPct'  => $totalUnidades > 0 ? round($ocupadas / $totalUnidades * 100) : 0,
            'llegadasHoy'   => $llegadasHoy,
            'salidasHoy'    => $salidasHoy,
            'pendientes'    => $pendientes,
            'proximas'      => $proximas,
            'ingresosMes'   => $ingresosMes,
        ]);
    }
}
