<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\Almacen;
use CodeIgniter\Model;

/**
 * Órdenes de compra.
 *
 * **La recepción es lo que mueve el stock**, no el pedido. Entre pedir y
 * recibir puede pasar cualquier cosa, y dar por bueno lo pedido dejaría el
 * almacén contando mercancía que aún está en el camión.
 */
class OrdenCompraModel extends Model
{
    protected $table         = 'ordenes_compra';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'numero', 'proveedor_id', 'bodega_id', 'estado', 'fecha', 'fecha_esperada',
        'total', 'documento', 'archivo', 'notas', 'usuario_id', 'enviada_en', 'recibida_en',
    ];
    protected $useTimestamps = true;

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'enviada'  => 'Enviada al proveedor',
        'parcial'  => 'Recibida a medias',
        'recibida' => 'Recibida',
        'anulada'  => 'Anulada',
    ];

    /** Número correlativo del año: OC-2026-0001. */
    public function generarNumero(): string
    {
        $anio  = date('Y');
        $ultima = $this->like('numero', 'OC-' . $anio . '-', 'after')
            ->orderBy('id', 'DESC')->first();

        $siguiente = 1;
        if ($ultima !== null && preg_match('/-(\d+)$/', $ultima['numero'], $m) === 1) {
            $siguiente = (int) $m[1] + 1;
        }

        return sprintf('OC-%s-%04d', $anio, $siguiente);
    }

    /** Listado con proveedor, bodega y cuánto se ha recibido. */
    public function listado(array $filtros = []): array
    {
        $this->select('ordenes_compra.*, proveedores.nombre AS proveedor, bodegas.nombre AS bodega')
            ->join('proveedores', 'proveedores.id = ordenes_compra.proveedor_id')
            ->join('bodegas', 'bodegas.id = ordenes_compra.bodega_id');

        if (! empty($filtros['estado'])) {
            $this->where('ordenes_compra.estado', $filtros['estado']);
        }
        if (! empty($filtros['proveedor_id'])) {
            $this->where('ordenes_compra.proveedor_id', (int) $filtros['proveedor_id']);
        }
        if (! empty($filtros['desde'])) {
            $this->where('ordenes_compra.fecha >=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $this->where('ordenes_compra.fecha <=', $filtros['hasta']);
        }

        $ordenes = $this->orderBy('ordenes_compra.id', 'DESC')->findAll(200);

        foreach ($ordenes as &$o) {
            $fila = $this->db->table('orden_compra_lineas')
                ->select('COUNT(*) AS lineas, SUM(cantidad_pedida) AS pedido, SUM(cantidad_recibida) AS recibido', false)
                ->where('orden_id', $o['id'])
                ->get()->getRowArray();

            $o['lineas']   = (int) ($fila['lineas'] ?? 0);
            $o['pedido']   = (float) ($fila['pedido'] ?? 0);
            $o['recibido'] = (float) ($fila['recibido'] ?? 0);
        }

        return $ordenes;
    }

    /** Recalcula el total desde las líneas. */
    public function recalcular(int $ordenId): float
    {
        $fila = $this->db->table('orden_compra_lineas')
            ->select('SUM(cantidad_pedida * costo_unitario) AS total', false)
            ->where('orden_id', $ordenId)
            ->get()->getRowArray();

        $total = round((float) ($fila['total'] ?? 0), 2);
        $this->update($ordenId, ['total' => $total]);

        return $total;
    }

    /**
     * Registra lo que ha llegado y lo mete en el almacén.
     *
     * Cada línea recibida genera su entrada, con su lote y su coste. El estado
     * de la orden sale de comparar lo pedido con lo recibido: no se declara a
     * mano, se deduce.
     *
     * @param array<int, array{cantidad: float, costo?: float, lote?: string, caduca_el?: string, motivo?: string}> $recibido
     *
     * @return array{entradas: int, estado: string}
     */
    public function recibir(int $ordenId, array $recibido): array
    {
        $orden = $this->find($ordenId);
        if ($orden === null || in_array($orden['estado'], ['borrador', 'anulada'], true)) {
            return ['entradas' => 0, 'estado' => $orden['estado'] ?? 'desconocido'];
        }

        $lineasModelo = new OrdenCompraLineaModel();
        $almacen      = new Almacen();
        $entradas     = 0;

        $db = db_connect();
        $db->transStart();

        foreach ($lineasModelo->where('orden_id', $ordenId)->findAll() as $linea) {
            $datos = $recibido[(int) $linea['id']] ?? null;
            if ($datos === null) {
                continue;
            }

            $cantidad = (float) ($datos['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $costo = isset($datos['costo']) && (float) $datos['costo'] > 0
                ? (float) $datos['costo']
                : (float) $linea['costo_unitario'];

            $almacen->entrada(
                (int) $linea['insumo_id'],
                (int) $orden['bodega_id'],
                $cantidad,
                $costo,
                'Orden ' . $orden['numero'],
                [
                    'lote'         => trim((string) ($datos['lote'] ?? '')),
                    'caduca_el'    => $datos['caduca_el'] ?? null,
                    'proveedor_id' => (int) $orden['proveedor_id'],
                ],
                'orden_compra',
                $ordenId
            );

            $lineasModelo->update($linea['id'], [
                // Se suma a lo que ya hubiera: una orden se puede recibir en
                // varias veces, que es justo el caso que esto resuelve.
                'cantidad_recibida' => round((float) $linea['cantidad_recibida'] + $cantidad, 3),
                'costo_unitario'    => $costo,
                'lote'              => trim((string) ($datos['lote'] ?? '')) ?: $linea['lote'],
                'caduca_el'         => $datos['caduca_el'] ?? $linea['caduca_el'],
                'motivo_diferencia' => trim((string) ($datos['motivo'] ?? '')) ?: $linea['motivo_diferencia'],
            ]);

            $entradas++;
        }

        $estado = $this->estadoSegunRecepcion($ordenId);
        $this->update($ordenId, [
            'estado'      => $estado,
            'recibida_en' => $estado === 'recibida' ? date('Y-m-d H:i:s') : $orden['recibida_en'],
        ]);

        $db->transComplete();

        return ['entradas' => $entradas, 'estado' => $estado];
    }

    /**
     * El estado se deduce de las cantidades, no se declara.
     *
     * Se admite recibir de más: pasa, y negarlo no lo evita. Si todo lo pedido
     * ha llegado o más, la orden está cerrada.
     */
    private function estadoSegunRecepcion(int $ordenId): string
    {
        $fila = $this->db->table('orden_compra_lineas')
            ->select('SUM(cantidad_pedida) AS pedido, SUM(cantidad_recibida) AS recibido', false)
            ->where('orden_id', $ordenId)
            ->get()->getRowArray();

        $pedido   = (float) ($fila['pedido'] ?? 0);
        $recibido = (float) ($fila['recibido'] ?? 0);

        if ($recibido <= 0) {
            return 'enviada';
        }

        return $recibido >= $pedido ? 'recibida' : 'parcial';
    }

    /**
     * Diferencias entre lo pedido y lo recibido.
     *
     * Es lo que dice qué proveedor cumple. Solo mira órdenes cerradas: en una
     * parcial todavía puede llegar el resto.
     */
    public function diferencias(string $desde, string $hasta): array
    {
        return $this->db->table('orden_compra_lineas l')
            ->select('p.nombre AS proveedor, i.nombre AS insumo, i.unidad,
                      o.numero, o.fecha,
                      l.cantidad_pedida, l.cantidad_recibida, l.motivo_diferencia,
                      (l.cantidad_recibida - l.cantidad_pedida) AS diferencia')
            ->join('ordenes_compra o', 'o.id = l.orden_id')
            ->join('proveedores p', 'p.id = o.proveedor_id')
            ->join('insumos i', 'i.id = l.insumo_id')
            ->where('o.estado', 'recibida')
            ->where('o.fecha >=', $desde)
            ->where('o.fecha <=', $hasta)
            ->where('l.cantidad_recibida != l.cantidad_pedida', null, false)
            ->orderBy('o.fecha', 'DESC')
            ->get()->getResultArray();
    }

    public function sePuedeEditar(array $orden): bool
    {
        return $orden['estado'] === 'borrador';
    }
}
