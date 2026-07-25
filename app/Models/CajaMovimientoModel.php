<?php

namespace App\Models;

use CodeIgniter\Model;

class CajaMovimientoModel extends Model
{
    protected $table         = 'caja_movimientos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['turno_id', 'tipo', 'concepto', 'valor', 'usuario_id', 'folio_movimiento_id'];
    protected $useTimestamps = true;

    public function delTurno(int $turnoId): array
    {
        return $this->select('caja_movimientos.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = caja_movimientos.usuario_id')
            ->where('turno_id', $turnoId)
            ->orderBy('caja_movimientos.created_at', 'DESC')
            ->orderBy('caja_movimientos.id', 'DESC')
            ->findAll();
    }
}
