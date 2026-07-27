<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lo que se gastó en cada reparación.
 *
 * Dos orígenes distintos y los dos cuentan: el repuesto que sale del almacén
 * (y genera su movimiento de stock) y el que el técnico compró en la ferretería
 * del pueblo esa misma mañana, que no tiene insumo pero sí factura.
 *
 * Sin esto, la pregunta «¿sale más a cuenta arreglar este calentador otra vez o
 * cambiarlo?» no se puede contestar con datos, solo con impresiones.
 */
class MantenimientoMaterialModel extends Model
{
    protected $table         = 'mantenimiento_materiales';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'mantenimiento_id', 'insumo_id', 'descripcion', 'cantidad',
        'costo_unitario', 'costo_total', 'movimiento_id', 'bodega_id', 'usuario_id',
    ];

    /** Las líneas de una orden, con el nombre del insumo si vino del almacén. */
    public function deOrden(int $ordenId): array
    {
        return $this->select('mantenimiento_materiales.*, insumos.unidad AS insumo_unidad,
                              bodegas.nombre AS bodega_nombre, usuarios.nombre AS usuario_nombre')
            ->join('insumos', 'insumos.id = mantenimiento_materiales.insumo_id', 'left')
            ->join('bodegas', 'bodegas.id = mantenimiento_materiales.bodega_id', 'left')
            ->join('usuarios', 'usuarios.id = mantenimiento_materiales.usuario_id', 'left')
            ->where('mantenimiento_materiales.mantenimiento_id', $ordenId)
            ->orderBy('mantenimiento_materiales.created_at')
            ->findAll();
    }

    public function totalDeOrden(int $ordenId): float
    {
        $fila = $this->selectSum('costo_total')->where('mantenimiento_id', $ordenId)->first();

        return round((float) ($fila['costo_total'] ?? 0), 2);
    }

    /**
     * Lo gastado en un activo a lo largo de su vida.
     *
     * Puesto al lado de lo que costó comprarlo, es el número que decide si el
     * siguiente se compra de otra marca.
     */
    public function gastadoEnActivo(int $activoId): float
    {
        $fila = $this->selectSum('mantenimiento_materiales.costo_total')
            ->join('mantenimientos', 'mantenimientos.id = mantenimiento_materiales.mantenimiento_id')
            ->where('mantenimientos.activo_id', $activoId)
            ->where('mantenimientos.estado !=', 'anulada')
            ->first();

        return round((float) ($fila['costo_total'] ?? 0), 2);
    }
}
