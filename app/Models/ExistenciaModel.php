<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * El saldo de cada insumo en cada bodega.
 *
 * **Solo lo escribe `Libraries\Almacen`.** Si se pudiera tocar desde cualquier
 * sitio, dejaría de coincidir con los movimientos y la pregunta «¿por qué hay
 * tres kilos y no cinco?» se quedaría sin respuesta.
 */
class ExistenciaModel extends Model
{
    protected $table         = 'existencias';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['insumo_id', 'bodega_id', 'cantidad', 'costo_medio', 'ultimo_movimiento'];
    protected $useTimestamps = true;

    /**
     * Fija el saldo, creando la fila si no existía.
     *
     * `$costoMedio` a `null` deja el que hubiera: al sacar mercancía el coste
     * medio no cambia, solo la cantidad.
     */
    public function fijar(int $insumoId, int $bodegaId, float $cantidad, ?float $costoMedio): void
    {
        $fila = $this->where('insumo_id', $insumoId)->where('bodega_id', $bodegaId)->first();

        $datos = [
            'cantidad'          => round($cantidad, 3),
            'ultimo_movimiento' => date('Y-m-d H:i:s'),
        ];

        if ($costoMedio !== null) {
            $datos['costo_medio'] = round($costoMedio, 4);
        }

        if ($fila === null) {
            $this->insert($datos + [
                'insumo_id'   => $insumoId,
                'bodega_id'   => $bodegaId,
                'costo_medio' => round($costoMedio ?? 0, 4),
            ]);

            return;
        }

        $this->update($fila['id'], $datos);
    }

    /**
     * Existencias de una bodega, con el nombre del insumo y su mínimo.
     *
     * @param array<string, mixed> $filtros
     */
    public function deBodega(int $bodegaId, array $filtros = []): array
    {
        $this->select('existencias.*, insumos.nombre, insumos.unidad, insumos.categoria,
                       insumos.stock_minimo, insumos.stock_maximo, insumos.controla_lote')
            ->join('insumos', 'insumos.id = existencias.insumo_id')
            ->where('existencias.bodega_id', $bodegaId);

        if (! empty($filtros['buscar'])) {
            $this->like('insumos.nombre', $filtros['buscar']);
        }
        if (! empty($filtros['categoria'])) {
            $this->where('insumos.categoria', $filtros['categoria']);
        }
        if (! empty($filtros['solo_bajos'])) {
            $this->where('existencias.cantidad <= insumos.stock_minimo', null, false)
                ->where('insumos.stock_minimo >', 0);
        }
        if (! empty($filtros['solo_negativos'])) {
            $this->where('existencias.cantidad <', 0);
        }

        return $this->orderBy('insumos.nombre')->findAll();
    }

    /**
     * Lo que hay que reponer.
     *
     * Solo cuenta lo que tiene mínimo puesto: sin mínimo no hay forma de saber
     * si dos kilos es mucho o poco, y avisar de todo es no avisar de nada.
     */
    public function bajoMinimo(): array
    {
        return $this->select('existencias.*, insumos.nombre, insumos.unidad, insumos.stock_minimo,
                              insumos.stock_maximo, bodegas.nombre AS bodega,
                              proveedores.nombre AS proveedor, proveedores.dias_entrega')
            ->join('insumos', 'insumos.id = existencias.insumo_id')
            ->join('bodegas', 'bodegas.id = existencias.bodega_id')
            ->join('proveedores', 'proveedores.id = insumos.proveedor_id', 'left')
            ->where('insumos.stock_minimo >', 0)
            ->where('insumos.activo', 1)
            ->where('bodegas.activa', 1)
            ->where('existencias.cantidad <= insumos.stock_minimo', null, false)
            ->orderBy('existencias.cantidad')
            ->findAll();
    }

    /** Saldos en negativo: se vendió algo que el sistema no sabía que había. */
    public function negativos(): array
    {
        return $this->select('existencias.*, insumos.nombre, insumos.unidad, bodegas.nombre AS bodega')
            ->join('insumos', 'insumos.id = existencias.insumo_id')
            ->join('bodegas', 'bodegas.id = existencias.bodega_id')
            ->where('existencias.cantidad <', 0)
            ->orderBy('existencias.cantidad')
            ->findAll();
    }

    /** Lo que vale todo el almacén, por bodega. */
    public function valoracion(): array
    {
        return $this->select('bodegas.nombre AS bodega, bodegas.id AS bodega_id,
                              COUNT(*) AS referencias,
                              SUM(existencias.cantidad * existencias.costo_medio) AS valor')
            ->join('bodegas', 'bodegas.id = existencias.bodega_id')
            ->where('existencias.cantidad !=', 0)
            ->groupBy('bodegas.id, bodegas.nombre')
            ->orderBy('valor', 'DESC')
            ->findAll();
    }
}
