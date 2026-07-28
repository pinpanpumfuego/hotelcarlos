<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Las noches en que un huésped renunció al cambio de lencería.
 *
 * Cada fila es una noche concreta de una reserva concreta. Se guarda la noche
 * y no el momento de pedirlo porque es lo que permite las dos cuentas que
 * importan: cuántas noches seguidas lleva sin cambio —hay un tope— y cuántas
 * lavadas se ahorraron de verdad.
 */
class GestoVerdeModel extends Model
{
    protected $table         = 'gestos_verdes';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'reserva_id', 'unidad_id', 'fecha', 'origen', 'estado', 'limpieza_id',
        'confirmo_id', 'confirmado_en', 'motivo', 'comanda_linea_id', 'canjeado_en',
        'usuario_id',
    ];

    public const ESTADOS = [
        'pedido'     => 'Pedido',
        'confirmado' => 'Confirmado',
        'descartado' => 'No se pudo',
        'canjeado'   => 'Canjeado',
    ];

    public function deNoche(int $reservaId, string $fecha): ?array
    {
        return $this->where('reserva_id', $reservaId)->where('fecha', $fecha)->first();
    }

    /** @return list<array<string, mixed>> */
    public function deReserva(int $reservaId): array
    {
        return $this->where('reserva_id', $reservaId)->orderBy('fecha', 'DESC')->findAll();
    }

    /**
     * Vales ganados y todavía sin tomar.
     *
     * `confirmado` y no `pedido`: el vale nace cuando housekeeping da fe de que
     * no cambió nada, no cuando el huésped lo pide.
     */
    public function valesDisponibles(int $reservaId): array
    {
        return $this->where('reserva_id', $reservaId)
            ->where('estado', 'confirmado')
            ->orderBy('fecha')
            ->findAll();
    }

    /**
     * Cuántas noches seguidas lleva ya sin cambio, contando hacia atrás desde
     * la noche indicada.
     *
     * Descartadas no cuentan: si hubo que cambiar la ropa igualmente, la cadena
     * se rompió de verdad.
     */
    public function nochesSeguidas(int $reservaId, string $hasta): int
    {
        $seguidas = 0;
        $dia      = strtotime($hasta . ' -1 day');

        for ($i = 0; $i < 60; $i++) {
            $gesto = $this->deNoche($reservaId, date('Y-m-d', $dia));

            if ($gesto === null || $gesto['estado'] === 'descartado') {
                break;
            }

            $seguidas++;
            $dia = strtotime('-1 day', $dia);
        }

        return $seguidas;
    }

    /** Los que esperan que housekeeping confirme, para el tablero del día. */
    public function pendientesDe(string $fecha): array
    {
        return $this->select('gestos_verdes.*, reservas.codigo AS reserva_codigo, unidades.nombre AS unidad')
            ->join('reservas', 'reservas.id = gestos_verdes.reserva_id')
            ->join('unidades', 'unidades.id = gestos_verdes.unidad_id', 'left')
            ->where('gestos_verdes.fecha', $fecha)
            ->where('gestos_verdes.estado', 'pedido')
            ->orderBy('unidades.nombre')
            ->findAll();
    }
}
