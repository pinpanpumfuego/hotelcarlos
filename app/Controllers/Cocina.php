<?php

namespace App\Controllers;

use App\Models\ComandaLineaModel;

/**
 * Pantallas de preparación (KDS): cocina y barra.
 * Ambas comparten la misma vista y solo cambian de zona.
 */
class Cocina extends BaseController
{
    private ComandaLineaModel $lineas;

    private const ZONAS = [
        'cocina' => ['titulo' => 'Cocina', 'icono' => 'bi-fire', 'ruta' => 'cocina'],
        'barra'  => ['titulo' => 'Barra', 'icono' => 'bi-cup-straw', 'ruta' => 'barra'],
    ];

    public function __construct()
    {
        $this->lineas = new ComandaLineaModel();
    }

    public function index()
    {
        return $this->pantalla('cocina');
    }

    public function barra()
    {
        return $this->pantalla('barra');
    }

    /** Datos en JSON para refrescar la pantalla sin recargarla. */
    public function datos(string $zona = 'cocina')
    {
        $zona       = isset(self::ZONAS[$zona]) ? $zona : 'cocina';
        $pendientes = $this->lineas->pendientesDeZona($zona);

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
                'destino'  => $l['destino'],
                'notas'    => $l['notas'],
            ];
        }

        return $this->response->setJSON(['ok' => true, 'comandas' => array_values($comandas)]);
    }

    /** Marca un producto como listo para servir. */
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

    /** Toda la comanda de esa zona lista de una vez. */
    public function comandaLista(int $comandaId, string $zona = 'cocina')
    {
        $zona = isset(self::ZONAS[$zona]) ? $zona : 'cocina';

        $this->lineas->builder()
            ->where('comanda_id', $comandaId)
            ->where('destino', $zona)
            ->where('enviado_cocina', 1)
            ->where('entregado', 0)
            ->update(['entregado' => 1, 'listo_en' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->response->setJSON(['ok' => true]);
    }

    private function pantalla(string $zona)
    {
        $otra = $zona === 'cocina' ? 'barra' : 'cocina';

        return view('cocina/index', [
            'hotel'      => config('Hotel'),
            'zona'       => $zona,
            'config'     => self::ZONAS[$zona],
            'otraZona'   => self::ZONAS[$otra],
            'pendientes' => $this->lineas->pendientesDeZona($zona),
        ]);
    }
}
