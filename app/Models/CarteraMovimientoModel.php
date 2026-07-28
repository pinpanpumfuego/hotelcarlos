<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Los movimientos de una cuenta de cartera.
 *
 * Un cargo nace cuando una reserva se cierra contra la cuenta; un abono, cuando
 * la empresa paga. Los abonos no van contra una factura concreta: van contra la
 * cuenta, que es como se paga en la práctica.
 */
class CarteraMovimientoModel extends Model
{
    protected $table         = 'cartera_movimientos';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'cuenta_id', 'tipo', 'concepto', 'valor', 'reserva_id', 'factura_id',
        'fecha', 'vence_en', 'medio_pago', 'referencia', 'usuario_id',
    ];

    public const TIPOS = [
        'cargo'        => 'Cargo',
        'abono'        => 'Abono',
        'nota_credito' => 'Nota de crédito',
        'ajuste'       => 'Ajuste',
    ];

    /**
     * Los movimientos de una cuenta.
     *
     * Las columnas van calificadas con la tabla a propósito: `reservas` también
     * tiene `cuenta_id` desde que una reserva puede cargarse a una empresa, y
     * sin calificar MySQL no sabe a cuál de las dos se refiere.
     */
    public function deCuenta(int $cuentaId, int $cuantos = 200): array
    {
        return $this->select('cartera_movimientos.*, reservas.codigo AS reserva_codigo,
                              usuarios.nombre AS usuario_nombre')
            ->join('reservas', 'reservas.id = cartera_movimientos.reserva_id', 'left')
            ->join('usuarios', 'usuarios.id = cartera_movimientos.usuario_id', 'left')
            ->where('cartera_movimientos.cuenta_id', $cuentaId)
            ->orderBy('cartera_movimientos.fecha', 'DESC')
            ->orderBy('cartera_movimientos.id', 'DESC')
            ->findAll($cuantos);
    }

    /**
     * Los cargos que ya vencieron y siguen sin cubrirse.
     *
     * Se enseña en la ficha para poder decir por teléfono «la de septiembre».
     */
    public function vencidos(int $cuentaId): array
    {
        return $this->select('cartera_movimientos.*, reservas.codigo AS reserva_codigo')
            ->join('reservas', 'reservas.id = cartera_movimientos.reserva_id', 'left')
            ->where('cartera_movimientos.cuenta_id', $cuentaId)
            ->whereIn('cartera_movimientos.tipo', ['cargo', 'ajuste'])
            ->where('cartera_movimientos.vence_en <', date('Y-m-d'))
            ->orderBy('cartera_movimientos.vence_en')
            ->findAll();
    }
}
