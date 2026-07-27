<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * El libro del almacén: todo lo que entró, salió o se movió.
 *
 * **No se edita ni se borra nunca.** Un error se corrige con un movimiento
 * contrario, igual que en contabilidad. Si se pudiera retocar, dejaría de
 * servir para lo único que sirve: explicar por qué el stock es el que es.
 *
 * Por eso no hay `update()` ni `delete()` propios, y el nombre del usuario se
 * guarda copiado: si la cuenta se borra, el movimiento tiene que seguir
 * diciendo quién lo hizo.
 */
class MovimientoStockModel extends Model
{
    protected $table         = 'movimientos_stock';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'insumo_id', 'bodega_id', 'lote_id', 'tipo', 'cantidad', 'costo_unitario',
        'saldo', 'referencia_tipo', 'referencia_id', 'motivo', 'usuario_id', 'usuario_nombre',
    ];
    protected $useTimestamps = true;
    protected $updatedField  = '';   // la tabla solo tiene created_at

    public const TIPOS = [
        'entrada'         => 'Entrada',
        'salida'          => 'Salida por venta',
        'traslado'        => 'Traslado entre bodegas',
        'ajuste'          => 'Ajuste de conteo',
        'merma'           => 'Merma',
        'consumo_interno' => 'Consumo interno',
        'cortesia'        => 'Cortesía',
        'devolucion'      => 'Devolución',
    ];

    /**
     * El historial, con filtros.
     *
     * @param array<string, mixed> $filtros
     */
    public function buscar(array $filtros = []): self
    {
        $this->select('movimientos_stock.*, insumos.nombre AS insumo, insumos.unidad,
                       bodegas.nombre AS bodega, lotes.codigo AS lote')
            ->join('insumos', 'insumos.id = movimientos_stock.insumo_id')
            ->join('bodegas', 'bodegas.id = movimientos_stock.bodega_id')
            ->join('lotes', 'lotes.id = movimientos_stock.lote_id', 'left');

        if (! empty($filtros['insumo_id'])) {
            $this->where('movimientos_stock.insumo_id', (int) $filtros['insumo_id']);
        }
        if (! empty($filtros['bodega_id'])) {
            $this->where('movimientos_stock.bodega_id', (int) $filtros['bodega_id']);
        }
        if (! empty($filtros['tipo'])) {
            $this->where('movimientos_stock.tipo', $filtros['tipo']);
        }
        if (! empty($filtros['usuario_id'])) {
            $this->where('movimientos_stock.usuario_id', (int) $filtros['usuario_id']);
        }
        if (! empty($filtros['desde'])) {
            $this->where('movimientos_stock.created_at >=', $filtros['desde'] . ' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $this->where('movimientos_stock.created_at <=', $filtros['hasta'] . ' 23:59:59');
        }

        return $this->orderBy('movimientos_stock.id', 'DESC');
    }

    /** La ficha de un insumo: su película completa en una bodega. */
    public function fichaDe(int $insumoId, ?int $bodegaId = null, int $limite = 200): array
    {
        $this->select('movimientos_stock.*, bodegas.nombre AS bodega, lotes.codigo AS lote')
            ->join('bodegas', 'bodegas.id = movimientos_stock.bodega_id')
            ->join('lotes', 'lotes.id = movimientos_stock.lote_id', 'left')
            ->where('movimientos_stock.insumo_id', $insumoId);

        if ($bodegaId !== null) {
            $this->where('movimientos_stock.bodega_id', $bodegaId);
        }

        return $this->orderBy('movimientos_stock.id', 'DESC')->findAll($limite);
    }

    /**
     * Lo consumido de verdad en un periodo, por insumo.
     *
     * Es la mitad de la comparación entre teórico y real: esto es lo que salió
     * del almacén; lo teórico sale de las recetas de lo vendido.
     *
     * @return array<int, array{insumo_id: int, insumo: string, unidad: string, cantidad: float, valor: float}>
     */
    public function consumoReal(string $desde, string $hasta, ?int $bodegaId = null): array
    {
        $this->select('movimientos_stock.insumo_id, insumos.nombre AS insumo, insumos.unidad,
                       SUM(-movimientos_stock.cantidad) AS cantidad,
                       SUM(-movimientos_stock.cantidad * movimientos_stock.costo_unitario) AS valor', false)
            ->join('insumos', 'insumos.id = movimientos_stock.insumo_id')
            // Los traslados no son consumo: la mercancía sigue en casa
            ->whereIn('movimientos_stock.tipo', ['salida', 'merma', 'consumo_interno', 'cortesia'])
            ->where('movimientos_stock.created_at >=', $desde . ' 00:00:00')
            ->where('movimientos_stock.created_at <=', $hasta . ' 23:59:59');

        if ($bodegaId !== null) {
            $this->where('movimientos_stock.bodega_id', $bodegaId);
        }

        $filas = $this->groupBy('movimientos_stock.insumo_id, insumos.nombre, insumos.unidad')
            ->orderBy('valor', 'DESC')
            ->findAll();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['insumo_id']] = [
                'insumo_id' => (int) $f['insumo_id'],
                'insumo'    => $f['insumo'],
                'unidad'    => $f['unidad'],
                'cantidad'  => (float) $f['cantidad'],
                'valor'     => (float) $f['valor'],
            ];
        }

        return $mapa;
    }

    /** Cuánto se ha ido en mermas y cortesías: el dinero que se evapora. */
    public function fugas(string $desde, string $hasta): array
    {
        return $this->select('movimientos_stock.tipo, COUNT(*) AS veces,
                              SUM(-movimientos_stock.cantidad * movimientos_stock.costo_unitario) AS valor', false)
            ->whereIn('movimientos_stock.tipo', ['merma', 'consumo_interno', 'cortesia'])
            ->where('movimientos_stock.created_at >=', $desde . ' 00:00:00')
            ->where('movimientos_stock.created_at <=', $hasta . ' 23:59:59')
            ->groupBy('movimientos_stock.tipo')
            ->orderBy('valor', 'DESC')
            ->findAll();
    }
}
