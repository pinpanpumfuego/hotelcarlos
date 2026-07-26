<?php

namespace App\Models;

use CodeIgniter\Model;

class ComandaPagoModel extends Model
{
    protected $table         = 'comanda_pagos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['comanda_id', 'forma_pago', 'valor', 'recibido', 'cambio', 'usuario_id', 'empleado_id'];
    protected $useTimestamps = true;

    /** Pagos de una comanda, con quién los cobró. */
    public function deComanda(int $comandaId): array
    {
        $pagos = $this->select('comanda_pagos.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = comanda_pagos.usuario_id')
            ->where('comanda_id', $comandaId)
            ->orderBy('comanda_pagos.id')
            ->findAll();

        foreach ($pagos as &$p) {
            $p['valor']    = (float) $p['valor'];
            $p['recibido'] = $p['recibido'] !== null ? (float) $p['recibido'] : null;
            $p['cambio']   = $p['cambio'] !== null ? (float) $p['cambio'] : null;
        }

        return $pagos;
    }

    /** Total ya cobrado de una comanda. */
    public function totalPagado(int $comandaId): float
    {
        $fila = $this->selectSum('valor')->where('comanda_id', $comandaId)->first();

        return (float) ($fila['valor'] ?? 0);
    }
}
