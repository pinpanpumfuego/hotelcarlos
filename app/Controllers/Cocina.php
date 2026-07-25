<?php

namespace App\Controllers;

use App\Models\ComandaLineaModel;

/** Pantalla de cocina (KDS): lo enviado por los meseros y aún no preparado. */
class Cocina extends BaseController
{
    private ComandaLineaModel $lineas;

    public function __construct()
    {
        $this->lineas = new ComandaLineaModel();
    }

    public function index()
    {
        return view('cocina/index', [
            'pendientes' => $this->lineas->pendientesCocina(),
            'hotel'      => config('Hotel'),
        ]);
    }

    /** Datos en JSON para refrescar la pantalla sin recargarla. */
    public function datos()
    {
        $pendientes = $this->lineas->pendientesCocina();

        $comandas = [];
        foreach ($pendientes as $l) {
            $clave = $l['comanda_id'];
            if (! isset($comandas[$clave])) {
                $comandas[$clave] = [
                    'comanda_id' => (int) $clave,
                    'numero'     => $l['numero'],
                    'donde'      => $l['unidad_nombre'] ?: ($l['mesa'] ?: ($l['cliente_nombre'] ?: 'Para llevar')),
                    'enviado_en' => $l['enviado_en'],
                    'minutos'    => (int) ((time() - strtotime($l['enviado_en'])) / 60),
                    'lineas'     => [],
                ];
            }
            $comandas[$clave]['lineas'][] = [
                'id'       => (int) $l['id'],
                'cantidad' => (int) $l['cantidad'],
                'nombre'   => $l['nombre_producto'],
                'notas'    => $l['notas'],
            ];
        }

        return $this->response->setJSON(['ok' => true, 'comandas' => array_values($comandas)]);
    }

    /** Cocina marca un plato como listo para servir. */
    public function listo(int $lineaId)
    {
        $linea = $this->lineas->find($lineaId);
        if ($linea === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'La línea no existe.']);
        }

        $this->lineas->update($lineaId, [
            'entregado' => 1,
            'listo_en'  => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON(['ok' => true]);
    }

    /** Toda la comanda lista de una vez. */
    public function comandaLista(int $comandaId)
    {
        $this->lineas->builder()
            ->where('comanda_id', $comandaId)
            ->where('enviado_cocina', 1)
            ->where('entregado', 0)
            ->update(['entregado' => 1, 'listo_en' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->response->setJSON(['ok' => true]);
    }
}
