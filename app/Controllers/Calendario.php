<?php

namespace App\Controllers;

use App\Models\ReservaModel;
use App\Models\UnidadModel;

class Calendario extends BaseController
{
    private const DIAS_VISIBLES = 21;

    public function index()
    {
        $desdeParam = (string) $this->request->getGet('desde');
        $desde      = \DateTime::createFromFormat('Y-m-d', $desdeParam) ?: new \DateTime('today');
        $desde->setTime(0, 0);

        $hasta = (clone $desde)->modify('+' . self::DIAS_VISIBLES . ' days');

        $dias = [];
        for ($d = clone $desde; $d < $hasta; $d->modify('+1 day')) {
            $dias[] = clone $d;
        }

        $unidades = (new UnidadModel())->conTipo();

        $reservaModel = new ReservaModel();
        $reservas     = $reservaModel
            ->select('reservas.*, huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->whereIn('reservas.estado', ReservaModel::ESTADOS_ACTIVOS)
            ->where('reservas.fecha_entrada <', $hasta->format('Y-m-d'))
            ->where('reservas.fecha_salida >', $desde->format('Y-m-d'))
            ->findAll();

        // Mapa unidad → día → reserva, para pintar la cinta sin consultas por celda.
        $ocupacion = [];
        foreach ($reservas as $r) {
            $ini = max(new \DateTime($r['fecha_entrada']), $desde);
            $fin = min(new \DateTime($r['fecha_salida']), $hasta);
            for ($d = clone $ini; $d < $fin; $d->modify('+1 day')) {
                $ocupacion[$r['unidad_id']][$d->format('Y-m-d')] = $r;
            }
        }

        return view('calendario/index', [
            'titulo'    => 'Calendario',
            'seccion'   => 'calendario',
            'dias'      => $dias,
            'desde'     => $desde,
            'unidades'  => $unidades,
            'ocupacion' => $ocupacion,
            'anterior'  => (clone $desde)->modify('-' . self::DIAS_VISIBLES . ' days')->format('Y-m-d'),
            'siguiente' => $hasta->format('Y-m-d'),
        ]);
    }
}
