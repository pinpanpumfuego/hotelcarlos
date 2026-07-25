<?php

namespace App\Models;

use CodeIgniter\Model;

class MantenimientoModel extends Model
{
    protected $table         = 'mantenimientos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['unidad_id', 'ubicacion', 'titulo', 'descripcion', 'prioridad', 'estado', 'reporto_id', 'resolvio_id', 'resuelta_en', 'solucion'];
    protected $useTimestamps = true;

    public const PRIORIDADES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'urgente' => 'Urgente'];

    /** Incidencias con nombres de unidad y personas. */
    public function listar(bool $soloAbiertas): array
    {
        $builder = $this->select('mantenimientos.*, unidades.nombre AS unidad_nombre,
                                  reporta.nombre AS reporto_nombre, resuelve.nombre AS resolvio_nombre')
            ->join('unidades', 'unidades.id = mantenimientos.unidad_id', 'left')
            ->join('usuarios AS reporta', 'reporta.id = mantenimientos.reporto_id')
            ->join('usuarios AS resuelve', 'resuelve.id = mantenimientos.resolvio_id', 'left');

        if ($soloAbiertas) {
            return $builder->whereIn('mantenimientos.estado', ['abierta', 'en_proceso'])
                ->orderBy("FIELD(mantenimientos.prioridad, 'urgente', 'alta', 'media', 'baja')")
                ->orderBy('mantenimientos.created_at')
                ->findAll();
        }

        return $builder->where('mantenimientos.estado', 'resuelta')
            ->orderBy('mantenimientos.resuelta_en', 'DESC')
            ->findAll(10);
    }
}
