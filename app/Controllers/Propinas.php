<?php

namespace App\Controllers;

use App\Models\PropinaLiquidacionModel;

/**
 * Liquidación de propinas.
 *
 * La ley pide dos cosas: que el 100 % llegue a los trabajadores y que se les
 * informe cómo se repartió. Esto último es lo que aquí queda documentado.
 */
class Propinas extends BaseController
{
    private PropinaLiquidacionModel $liquidaciones;

    public function __construct()
    {
        $this->liquidaciones = new PropinaLiquidacionModel();
    }

    public function index()
    {
        // Lo que hay suelto sin liquidar, para que no se acumule en silencio
        $sinLiquidar = $this->liquidaciones->pendientesDe('2000-01-01', date('Y-m-d'));
        $suelto      = array_sum(array_map(static fn ($c) => (float) $c['propina'], $sinLiquidar));

        return view('propinas/index', [
            'titulo'        => 'Propinas',
            'seccion'       => 'propinas',
            'liquidaciones' => $this->liquidaciones->listado(),
            'suelto'        => $suelto,
            'comandasSueltas' => count($sinLiquidar),
        ]);
    }

    /** Pantalla de preparación: elige periodo y criterio, y ve la propuesta. */
    public function preparar()
    {
        $desde    = (string) ($this->request->getGet('desde') ?: date('Y-m-01'));
        $hasta    = (string) ($this->request->getGet('hasta') ?: date('Y-m-d'));
        $criterio = (string) ($this->request->getGet('criterio') ?: 'ventas');

        if (! array_key_exists($criterio, PropinaLiquidacionModel::CRITERIOS)) {
            $criterio = 'ventas';
        }
        if (strtotime($desde) === false || strtotime($hasta) === false || $hasta < $desde) {
            return redirect()->to('propinas')->with('error', 'Revisa las fechas del periodo.');
        }

        return view('propinas/preparar', [
            'titulo'    => 'Liquidar propinas',
            'seccion'   => 'propinas',
            'desde'     => $desde,
            'hasta'     => $hasta,
            'criterio'  => $criterio,
            'propuesta' => $this->liquidaciones->proponer($desde, $hasta, $criterio),
            'criterios' => PropinaLiquidacionModel::CRITERIOS,
        ]);
    }

    /**
     * Guarda y cierra la liquidación.
     *
     * Se comprueba que lo repartido cuadre con lo recaudado: si sobrara un
     * peso, ese peso se lo estaría quedando el hotel.
     */
    public function guardar()
    {
        $desde    = (string) $this->request->getPost('desde');
        $hasta    = (string) $this->request->getPost('hasta');
        $criterio = (string) $this->request->getPost('criterio');

        if (strtotime($desde) === false || strtotime($hasta) === false || $hasta < $desde) {
            return redirect()->to('propinas')->with('error', 'Revisa las fechas del periodo.');
        }
        if (! array_key_exists($criterio, PropinaLiquidacionModel::CRITERIOS)) {
            $criterio = 'manual';
        }

        $comandas = $this->liquidaciones->pendientesDe($desde, $hasta);
        if ($comandas === []) {
            return redirect()->to('propinas')->with('error', 'No hay propinas sin liquidar en ese periodo.');
        }

        $recaudado = round(array_sum(array_map(static fn ($c) => (float) $c['propina'], $comandas)), 2);

        // Importes tal como quedaron en el formulario (se pueden ajustar a mano)
        $importes = (array) $this->request->getPost('importe');
        $lineas   = [];
        $repartido = 0.0;

        foreach ($importes as $empleadoId => $importe) {
            $importe = round((float) $importe, 2);
            if ($importe <= 0) {
                continue;
            }
            $lineas[] = ['empleado_id' => (int) $empleadoId, 'importe' => $importe];
            $repartido += $importe;
        }

        if ($lineas === []) {
            return redirect()->back()->withInput()->with('error', 'Reparte la propina entre al menos una persona.');
        }

        $diferencia = round($recaudado - $repartido, 2);
        if (abs($diferencia) > 0.5) {
            return redirect()->back()->withInput()->with(
                'error',
                'Lo repartido ($' . number_format($repartido, 0, ',', '.') . ') no cuadra con lo recaudado ($'
                    . number_format($recaudado, 0, ',', '.') . '). '
                    . ($diferencia > 0
                        ? 'Faltan $' . number_format($diferencia, 0, ',', '.') . ' por repartir: la propina es de los trabajadores, no puede quedarse nada.'
                        : 'Sobran $' . number_format(abs($diferencia), 0, ',', '.') . '.')
            );
        }

        $generados = [];
        $conteos   = [];
        foreach ($comandas as $c) {
            if ($c['empleado_id'] !== null) {
                $id = (int) $c['empleado_id'];
                $generados[$id] = ($generados[$id] ?? 0) + (float) $c['propina'];
                $conteos[$id]   = ($conteos[$id] ?? 0) + 1;
            }
        }

        $id = $this->liquidaciones->insert([
            'desde'      => $desde,
            'hasta'      => $hasta,
            'criterio'   => $criterio,
            'recaudado'  => $recaudado,
            'repartido'  => $repartido,
            'estado'     => 'cerrada',
            'notas'      => trim((string) $this->request->getPost('notas')) ?: null,
            'usuario_id' => session()->get('usuario_id'),
            'cerrada_en' => date('Y-m-d H:i:s'),
        ]);

        $tabla = db_connect()->table('propina_liquidacion_lineas');
        foreach ($lineas as $l) {
            $tabla->insert([
                'liquidacion_id' => $id,
                'empleado_id'    => $l['empleado_id'],
                'generado'       => $generados[$l['empleado_id']] ?? 0,
                'comandas'       => $conteos[$l['empleado_id']] ?? 0,
                'importe'        => $l['importe'],
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
        }

        // Se marcan las comandas para que no se liquiden dos veces
        db_connect()->table('comandas')
            ->whereIn('id', array_map(static fn ($c) => (int) $c['id'], $comandas))
            ->update(['liquidacion_id' => $id]);

        log_message('info', 'Liquidación de propinas {id}: {r} repartidos entre {n} personas', [
            'id' => $id, 'r' => $repartido, 'n' => count($lineas),
        ]);

        return redirect()->to('propinas/ver/' . $id)
            ->with('ok', 'Liquidación cerrada. Imprime el comprobante de cada persona y que lo firme al recibirlo.');
    }

    public function ver(int $id)
    {
        $liquidacion = $this->liquidaciones->find($id);
        if ($liquidacion === null) {
            return redirect()->to('propinas')->with('error', 'Esa liquidación no existe.');
        }

        return view('propinas/ver', [
            'titulo'      => 'Liquidación de propinas',
            'seccion'     => 'propinas',
            'liquidacion' => $liquidacion,
            'lineas'      => $this->liquidaciones->lineas($id),
            'comandas'    => $this->liquidaciones->comandasDe($id),
            'criterios'   => PropinaLiquidacionModel::CRITERIOS,
        ]);
    }

    /** Marca que una persona ya recibió su parte. */
    public function entregar(int $lineaId)
    {
        $tabla = db_connect()->table('propina_liquidacion_lineas');
        $linea = $tabla->where('id', $lineaId)->get()->getRowArray();

        if ($linea === null) {
            return redirect()->to('propinas')->with('error', 'Esa línea no existe.');
        }

        $entregado = (int) $linea['entregado'] === 1 ? 0 : 1;
        $tabla->where('id', $lineaId)->update([
            'entregado'    => $entregado,
            'entregado_en' => $entregado === 1 ? date('Y-m-d H:i:s') : null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('propinas/ver/' . $linea['liquidacion_id'])
            ->with('ok', $entregado === 1 ? 'Marcada como entregada.' : 'Marcada como pendiente.');
    }

    /** Comprobante para que lo firme el trabajador. */
    public function comprobante(int $id)
    {
        $liquidacion = $this->liquidaciones->find($id);
        if ($liquidacion === null) {
            return redirect()->to('propinas')->with('error', 'Esa liquidación no existe.');
        }

        return view('propinas/comprobante', [
            'liquidacion' => $liquidacion,
            'lineas'      => $this->liquidaciones->lineas($id),
            'hotel'       => config('Hotel'),
        ]);
    }
}
