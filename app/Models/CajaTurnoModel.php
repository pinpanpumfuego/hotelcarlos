<?php

namespace App\Models;

use CodeIgniter\Model;

class CajaTurnoModel extends Model
{
    protected $table         = 'caja_turnos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['usuario_id', 'base_inicial', 'apertura', 'cierre', 'efectivo_contado', 'diferencia', 'notas'];
    protected $useTimestamps = true;

    /** El turno abierto actualmente (solo puede haber uno), o null. */
    public function abierto(): ?array
    {
        return $this->select('caja_turnos.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = caja_turnos.usuario_id')
            ->where('cierre IS NULL')
            ->first();
    }

    /** Ingresos y egresos en efectivo del turno. */
    public function totales(int $turnoId): array
    {
        $fila = db_connect()->table('caja_movimientos')
            ->select("SUM(CASE WHEN tipo = 'ingreso' THEN valor ELSE 0 END) AS ingresos,
                      SUM(CASE WHEN tipo = 'egreso' THEN valor ELSE 0 END) AS egresos")
            ->where('turno_id', $turnoId)
            ->get()->getRowArray();

        return [
            'ingresos' => (float) ($fila['ingresos'] ?? 0),
            'egresos'  => (float) ($fila['egresos'] ?? 0),
        ];
    }

    /** Últimos turnos cerrados. */
    public function historial(int $limite = 10): array
    {
        return $this->select('caja_turnos.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = caja_turnos.usuario_id')
            ->where('cierre IS NOT NULL')
            ->orderBy('cierre', 'DESC')
            ->findAll($limite);
    }
}
