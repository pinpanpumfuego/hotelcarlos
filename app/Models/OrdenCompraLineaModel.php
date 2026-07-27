<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** Cada renglón de una orden de compra: lo pedido y lo que llegó. */
class OrdenCompraLineaModel extends Model
{
    protected $table         = 'orden_compra_lineas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'orden_id', 'insumo_id', 'cantidad_pedida', 'cantidad_recibida',
        'costo_unitario', 'lote', 'caduca_el', 'motivo_diferencia',
    ];
    protected $useTimestamps = true;

    /** Las líneas con el nombre del insumo y si lleva control de lote. */
    public function deOrden(int $ordenId): array
    {
        return $this->select('orden_compra_lineas.*, insumos.nombre AS insumo,
                              insumos.unidad, insumos.controla_lote')
            ->join('insumos', 'insumos.id = orden_compra_lineas.insumo_id')
            ->where('orden_compra_lineas.orden_id', $ordenId)
            ->orderBy('insumos.nombre')
            ->findAll();
    }

    /**
     * Añade un insumo a la orden, o le suma cantidad si ya estaba.
     *
     * Sumar en vez de duplicar evita que el mismo insumo aparezca dos veces en
     * el mismo pedido, que es la forma más fácil de pedir el doble sin querer.
     */
    public function anadir(int $ordenId, int $insumoId, float $cantidad, float $costo): bool
    {
        if ($cantidad <= 0) {
            return false;
        }

        $existente = $this->where('orden_id', $ordenId)->where('insumo_id', $insumoId)->first();

        if ($existente !== null) {
            return (bool) $this->update($existente['id'], [
                'cantidad_pedida' => round((float) $existente['cantidad_pedida'] + $cantidad, 3),
                'costo_unitario'  => $costo > 0 ? $costo : $existente['costo_unitario'],
            ]);
        }

        return (bool) $this->insert([
            'orden_id'        => $ordenId,
            'insumo_id'       => $insumoId,
            'cantidad_pedida' => round($cantidad, 3),
            'costo_unitario'  => $costo,
        ]);
    }

    /** Lo que falta por recibir de una orden. */
    public function pendientes(int $ordenId): array
    {
        return array_values(array_filter(
            $this->deOrden($ordenId),
            static fn (array $l): bool => (float) $l['cantidad_recibida'] < (float) $l['cantidad_pedida']
        ));
    }
}
