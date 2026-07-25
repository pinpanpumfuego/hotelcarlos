<?php

namespace App\Controllers;

use App\Models\CajaMovimientoModel;
use App\Models\CajaTurnoModel;

/** Caja de recepción: turnos, movimientos de efectivo y arqueo. */
class Caja extends BaseController
{
    private CajaTurnoModel $turnos;
    private CajaMovimientoModel $movimientos;

    public function __construct()
    {
        $this->turnos      = new CajaTurnoModel();
        $this->movimientos = new CajaMovimientoModel();
    }

    public function index()
    {
        $turno = $this->turnos->abierto();
        $datos = [
            'titulo'    => 'Caja',
            'seccion'   => 'caja',
            'turno'     => $turno,
            'historial' => $this->turnos->historial(10),
        ];

        if ($turno !== null) {
            $totales           = $this->turnos->totales((int) $turno['id']);
            $datos['totales']  = $totales;
            $datos['esperado'] = (float) $turno['base_inicial'] + $totales['ingresos'] - $totales['egresos'];
            $datos['lista']    = $this->movimientos->delTurno((int) $turno['id']);
        }

        return view('caja/index', $datos);
    }

    public function abrir()
    {
        if ($this->turnos->abierto() !== null) {
            return redirect()->to('caja')->with('error', 'Ya hay un turno de caja abierto. Ciérralo antes de abrir otro.');
        }

        $base = (float) $this->request->getPost('base_inicial');
        if ($base < 0) {
            return redirect()->to('caja')->with('error', 'La base inicial no puede ser negativa.');
        }

        $this->turnos->insert([
            'usuario_id'   => session()->get('usuario_id'),
            'base_inicial' => $base,
            'apertura'     => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('caja')->with('ok', 'Turno abierto con base de $' . number_format($base, 0, ',', '.') . ' COP.');
    }

    public function movimiento()
    {
        $turno = $this->turnos->abierto();
        if ($turno === null) {
            return redirect()->to('caja')->with('error', 'No hay un turno abierto.');
        }

        $tipo     = $this->request->getPost('tipo') === 'egreso' ? 'egreso' : 'ingreso';
        $concepto = trim((string) $this->request->getPost('concepto'));
        $valor    = (float) $this->request->getPost('valor');

        if ($concepto === '' || $valor <= 0) {
            return redirect()->to('caja')->with('error', 'Indica un concepto y un valor mayor que cero.');
        }

        $this->movimientos->insert([
            'turno_id'   => $turno['id'],
            'tipo'       => $tipo,
            'concepto'   => $concepto,
            'valor'      => $valor,
            'usuario_id' => session()->get('usuario_id'),
        ]);

        return redirect()->to('caja')->with('ok', ucfirst($tipo) . ' registrado en la caja.');
    }

    public function cerrar()
    {
        $turno = $this->turnos->abierto();
        if ($turno === null) {
            return redirect()->to('caja')->with('error', 'No hay un turno abierto.');
        }

        $contado = (float) $this->request->getPost('efectivo_contado');
        if ($contado < 0) {
            return redirect()->to('caja')->with('error', 'El efectivo contado no puede ser negativo.');
        }

        $totales  = $this->turnos->totales((int) $turno['id']);
        $esperado = (float) $turno['base_inicial'] + $totales['ingresos'] - $totales['egresos'];

        $this->turnos->update($turno['id'], [
            'cierre'           => date('Y-m-d H:i:s'),
            'efectivo_contado' => $contado,
            'diferencia'       => $contado - $esperado,
            'notas'            => trim((string) $this->request->getPost('notas')) ?: null,
        ]);

        $diferencia = $contado - $esperado;
        $mensaje    = 'Turno cerrado. ';
        if (abs($diferencia) < 0.01) {
            $mensaje .= 'Arqueo perfecto: la caja cuadró.';
        } elseif ($diferencia < 0) {
            $mensaje .= 'Faltante de $' . number_format(abs($diferencia), 0, ',', '.') . ' COP registrado.';
        } else {
            $mensaje .= 'Sobrante de $' . number_format($diferencia, 0, ',', '.') . ' COP registrado.';
        }

        return redirect()->to('caja')->with($diferencia == 0 ? 'ok' : 'error', $mensaje);
    }
}
