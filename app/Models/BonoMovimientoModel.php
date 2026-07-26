<?php

namespace App\Models;

use CodeIgniter\Model;

/** Historial del saldo de un bono regalo. */
class BonoMovimientoModel extends Model
{
    protected $table         = 'bono_movimientos';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'bono_id', 'tipo', 'valor', 'saldo_despues', 'concepto',
        'reserva_id', 'comanda_id', 'usuario_id',
    ];
    protected $useTimestamps = true;

    public const TIPOS = [
        'emision'    => 'Emisión',
        'consumo'    => 'Canje',
        'devolucion' => 'Devolución de saldo',
        'anulacion'  => 'Anulación',
    ];

    /** Movimientos de un bono con su contexto. */
    public function deBono(int $bonoId): array
    {
        return $this->select('bono_movimientos.*, reservas.codigo AS reserva_codigo,
                              comandas.numero AS comanda_numero, usuarios.nombre AS usuario_nombre')
            ->join('reservas', 'reservas.id = bono_movimientos.reserva_id', 'left')
            ->join('comandas', 'comandas.id = bono_movimientos.comanda_id', 'left')
            ->join('usuarios', 'usuarios.id = bono_movimientos.usuario_id', 'left')
            ->where('bono_movimientos.bono_id', $bonoId)
            ->orderBy('bono_movimientos.id', 'DESC')
            ->findAll();
    }
}
