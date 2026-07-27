<?php

namespace App\Models;

use CodeIgniter\Model;

class CajaMovimientoModel extends Model
{
    protected $table         = 'caja_movimientos';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'turno_id', 'tipo', 'medio_id', 'concepto', 'referencia', 'valor',
        'usuario_id', 'folio_movimiento_id', 'comanda_id',
    ];
    protected $useTimestamps = true;

    public function delTurno(int $turnoId): array
    {
        // `left` en los dos: un movimiento lo puede generar el sistema sin
        // usuario, y los viejos no tienen medio de pago apuntado.
        return $this->select('caja_movimientos.*, usuarios.nombre AS usuario_nombre,
                              medios_pago.nombre AS medio_nombre, medios_pago.afecta_caja')
            ->join('usuarios', 'usuarios.id = caja_movimientos.usuario_id', 'left')
            ->join('medios_pago', 'medios_pago.id = caja_movimientos.medio_id', 'left')
            ->where('turno_id', $turnoId)
            ->orderBy('caja_movimientos.created_at', 'DESC')
            ->orderBy('caja_movimientos.id', 'DESC')
            ->findAll();
    }
}
