<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * El historial de una tarjeta.
 *
 * Es la prueba de qué pasó con el dinero de alguien. El saldo guardado es lo
 * que se comprueba al cobrar; esto es lo que se enseña cuando el titular
 * pregunta por qué le faltan veinte mil pesos.
 */
class TarjetaMovimientoModel extends Model
{
    protected $table         = 'tarjeta_movimientos';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'tarjeta_id', 'tipo', 'origen', 'valor', 'saldo_despues', 'concepto',
        'reserva_id', 'comanda_id', 'pago_online_id', 'referencia', 'usuario_id',
    ];

    public const TIPOS = [
        'carga'      => 'Recarga',
        'bonus'      => 'Regalo por recargar',
        'consumo'    => 'Consumo',
        'devolucion' => 'Devolución',
        'ajuste'     => 'Ajuste',
        'caducidad'  => 'Caducado',
    ];

    public const ORIGENES = [
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Datáfono',
        'transferencia' => 'Transferencia',
        'wompi'         => 'Pago en línea',
        'empresa'       => 'Cargado por la empresa',
        'cortesia'      => 'De la casa',
        'otro'          => 'Otro',
    ];

    public function deTarjeta(int $tarjetaId, int $cuantos = 100): array
    {
        return $this->select('tarjeta_movimientos.*, usuarios.nombre AS usuario_nombre,
                              reservas.codigo AS reserva_codigo, comandas.numero AS comanda_numero')
            ->join('usuarios', 'usuarios.id = tarjeta_movimientos.usuario_id', 'left')
            ->join('reservas', 'reservas.id = tarjeta_movimientos.reserva_id', 'left')
            ->join('comandas', 'comandas.id = tarjeta_movimientos.comanda_id', 'left')
            ->where('tarjeta_movimientos.tarjeta_id', $tarjetaId)
            ->orderBy('tarjeta_movimientos.created_at', 'DESC')
            ->orderBy('tarjeta_movimientos.id', 'DESC')
            ->findAll($cuantos);
    }

    /**
     * Cuánto se ha cargado y cuánto se ha gastado en un periodo.
     *
     * Se separan a propósito: lo cargado es dinero que entró y todavía se debe;
     * lo consumido es lo que de verdad pasó a ser ingreso.
     *
     * @return array{cargado: float, bonus: float, consumido: float}
     */
    public function resumen(string $desde, string $hasta): array
    {
        $fila = db_connect()->query(
            "SELECT
                COALESCE(SUM(CASE WHEN tipo = 'carga'   THEN valor ELSE 0 END), 0) AS cargado,
                COALESCE(SUM(CASE WHEN tipo = 'bonus'   THEN valor ELSE 0 END), 0) AS bonus,
                COALESCE(SUM(CASE WHEN tipo = 'consumo' THEN valor ELSE 0 END), 0) AS consumido
             FROM tarjeta_movimientos
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        return [
            'cargado'   => round((float) $fila['cargado'], 2),
            'bonus'     => round((float) $fila['bonus'], 2),
            'consumido' => round((float) $fila['consumido'], 2),
        ];
    }
}
