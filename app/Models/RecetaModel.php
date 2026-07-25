<?php

namespace App\Models;

use CodeIgniter\Model;

/** Receta (escandallo) de un plato: qué insumos lleva y en qué cantidad. */
class RecetaModel extends Model
{
    protected $table         = 'receta_lineas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['producto_id', 'insumo_id', 'cantidad'];
    protected $useTimestamps = true;

    /** Ingredientes de un plato con su coste calculado. */
    public function deProducto(int $productoId): array
    {
        $filas = $this->select('receta_lineas.*, insumos.nombre, insumos.unidad, insumos.costo_unitario')
            ->join('insumos', 'insumos.id = receta_lineas.insumo_id')
            ->where('producto_id', $productoId)
            ->orderBy('insumos.nombre')
            ->findAll();

        foreach ($filas as &$f) {
            $f['cantidad']       = (float) $f['cantidad'];
            $f['costo_unitario'] = (float) $f['costo_unitario'];
            $f['costo']          = $f['cantidad'] * $f['costo_unitario'];
        }

        return $filas;
    }

    /** Coste teórico de un plato: suma de sus ingredientes. */
    public function costeDeProducto(int $productoId): float
    {
        $fila = $this->select('SUM(receta_lineas.cantidad * insumos.costo_unitario) AS coste', false)
            ->join('insumos', 'insumos.id = receta_lineas.insumo_id')
            ->where('producto_id', $productoId)
            ->first();

        return (float) ($fila['coste'] ?? 0);
    }

    /** Coste teórico de todos los platos que tienen receta: [producto_id => coste]. */
    public function costesPorProducto(): array
    {
        $filas = $this->select('receta_lineas.producto_id, SUM(receta_lineas.cantidad * insumos.costo_unitario) AS coste', false)
            ->join('insumos', 'insumos.id = receta_lineas.insumo_id')
            ->groupBy('receta_lineas.producto_id')
            ->findAll();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['producto_id']] = (float) $f['coste'];
        }

        return $mapa;
    }
}
