<?php

namespace App\Models;

use CodeIgniter\Model;

/** Cada canje de un cupón: sin este registro no se pueden controlar los límites. */
class CuponUsoModel extends Model
{
    protected $table         = 'cupon_usos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['cupon_id', 'reserva_id', 'comanda_id', 'huesped_id', 'usuario_id', 'base', 'descuento', 'canal'];
    protected $useTimestamps = true;

    /** Canjes de un cupón con el contexto de cada uno. */
    public function deCupon(int $cuponId): array
    {
        return $this->select('cupon_usos.*, reservas.codigo AS reserva_codigo,
                              comandas.numero AS comanda_numero,
                              huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos,
                              usuarios.nombre AS usuario_nombre')
            ->join('reservas', 'reservas.id = cupon_usos.reserva_id', 'left')
            ->join('comandas', 'comandas.id = cupon_usos.comanda_id', 'left')
            ->join('huespedes', 'huespedes.id = cupon_usos.huesped_id', 'left')
            ->join('usuarios', 'usuarios.id = cupon_usos.usuario_id', 'left')
            ->where('cupon_usos.cupon_id', $cuponId)
            ->orderBy('cupon_usos.id', 'DESC')
            ->findAll();
    }
}
