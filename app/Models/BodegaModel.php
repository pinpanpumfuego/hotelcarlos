<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Bodegas: cocina, bar, minibar, lencería, mantenimiento, aseo.
 *
 * Se siembran las seis al instalar, con el minibar apagado. Un almacén vacío
 * obliga a inventarse la estructura antes de poder hacer nada; las que no se
 * usen se desactivan en dos clics.
 */
class BodegaModel extends Model
{
    protected $table         = 'bodegas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'clave', 'tipo', 'por_defecto', 'notas', 'activa'];
    protected $useTimestamps = true;

    public const TIPOS = [
        'cocina'        => 'Cocina',
        'bar'           => 'Bar',
        'minibar'       => 'Minibar',
        'lenceria'      => 'Lencería',
        'mantenimiento' => 'Mantenimiento',
        'aseo'          => 'Aseo',
        'general'       => 'General',
    ];

    public function activas(): array
    {
        return $this->where('activa', 1)->orderBy('por_defecto', 'DESC')->orderBy('nombre')->findAll();
    }

    /** Con cuántos insumos y cuánto vale lo que hay dentro. */
    public function conResumen(): array
    {
        $bodegas = $this->orderBy('activa', 'DESC')->orderBy('nombre')->findAll();

        foreach ($bodegas as &$b) {
            $fila = $this->db->table('existencias')
                ->select('COUNT(*) AS referencias, SUM(cantidad * costo_medio) AS valor', false)
                ->where('bodega_id', $b['id'])
                ->where('cantidad !=', 0)
                ->get()->getRowArray();

            $b['referencias'] = (int) ($fila['referencias'] ?? 0);
            $b['valor']       = (float) ($fila['valor'] ?? 0);

            $b['negativos'] = $this->db->table('existencias')
                ->where('bodega_id', $b['id'])
                ->where('cantidad <', 0)
                ->countAllResults();
        }

        return $bodegas;
    }

    /**
     * Marca una bodega como la de por defecto, quitándoselo a las demás.
     *
     * Solo puede haber una: si hubiera dos, cada venta tendría que elegir y el
     * TPV no puede pararse a preguntar de dónde sale la cerveza.
     */
    public function fijarPorDefecto(int $id): bool
    {
        $this->db->transStart();
        $this->builder()->where('id !=', $id)->update(['por_defecto' => 0]);
        $this->update($id, ['por_defecto' => 1, 'activa' => 1]);
        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * ¿Se puede borrar?
     *
     * No, si tiene movimientos: borrarla dejaría movimientos huérfanos y el
     * historial dejaría de explicarse. Se desactiva en su lugar.
     */
    public function sePuedeEliminar(int $id): bool
    {
        return $this->db->table('movimientos_stock')->where('bodega_id', $id)->countAllResults() === 0;
    }
}
