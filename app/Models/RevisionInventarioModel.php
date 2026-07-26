<?php

namespace App\Models;

use CodeIgniter\Model;

/** Revisiones del inventario de una cabaña, normalmente tras el aseo. */
class RevisionInventarioModel extends Model
{
    protected $table         = 'inventario_revisiones';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['unidad_id', 'reserva_id', 'usuario_id', 'estado', 'faltantes', 'danados', 'notas'];
    protected $useTimestamps = true;

    /** Revisiones de una cabaña con quién las hizo. */
    public function deUnidad(int $unidadId, int $limite = 20): array
    {
        return $this->select('inventario_revisiones.*, usuarios.nombre AS usuario_nombre,
                              reservas.codigo AS reserva_codigo')
            ->join('usuarios', 'usuarios.id = inventario_revisiones.usuario_id', 'left')
            ->join('reservas', 'reservas.id = inventario_revisiones.reserva_id', 'left')
            ->where('inventario_revisiones.unidad_id', $unidadId)
            ->orderBy('inventario_revisiones.id', 'DESC')
            ->findAll($limite);
    }

    /** Líneas con incidencia de una revisión. */
    public function incidencias(int $revisionId): array
    {
        return $this->db->table('inventario_revision_lineas')
            ->select('inventario_revision_lineas.*, inventario_items.nombre, inventario_items.valor_reposicion')
            ->join('inventario_items', 'inventario_items.id = inventario_revision_lineas.item_id')
            ->where('revision_id', $revisionId)
            ->where('inventario_revision_lineas.estado !=', 'ok')
            ->orderBy('inventario_items.grupo')
            ->get()
            ->getResultArray();
    }

    /** Última revisión de cada cabaña, indexada por unidad. */
    public function ultimasPorUnidad(): array
    {
        $filas = $this->orderBy('id', 'DESC')->findAll();

        $ultimas = [];
        foreach ($filas as $f) {
            $ultimas[(int) $f['unidad_id']] ??= $f;
        }

        return $ultimas;
    }
}
