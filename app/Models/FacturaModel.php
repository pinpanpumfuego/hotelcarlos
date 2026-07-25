<?php

namespace App\Models;

use CodeIgniter\Model;

class FacturaModel extends Model
{
    protected $table         = 'facturas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'origen', 'reserva_id', 'comanda_id',
        'cliente_nombre', 'cliente_documento', 'cliente_email',
        'subtotal', 'impuestos', 'total',
        'estado', 'siigo_id', 'numero', 'cufe', 'url_publica', 'respuesta', 'error',
        'emitida_en', 'usuario_id',
    ];
    protected $useTimestamps = true;

    public const ESTADOS = [
        'pendiente' => 'Pendiente de emitir',
        'emitida'   => 'Emitida',
        'error'     => 'Con error',
        'anulada'   => 'Anulada',
    ];

    /** Facturas con su origen, para el listado. */
    public function listado(?string $estado = null): array
    {
        $builder = $this->select('facturas.*, reservas.codigo AS reserva_codigo, comandas.numero AS comanda_numero')
            ->join('reservas', 'reservas.id = facturas.reserva_id', 'left')
            ->join('comandas', 'comandas.id = facturas.comanda_id', 'left');

        if ($estado !== null && array_key_exists($estado, self::ESTADOS)) {
            $builder->where('facturas.estado', $estado);
        }

        return $builder->orderBy('facturas.id', 'DESC')->findAll(100);
    }

    /** Factura ya emitida de una reserva, si la hay. */
    public function deReserva(int $reservaId): ?array
    {
        return $this->where('reserva_id', $reservaId)
            ->whereIn('estado', ['emitida', 'pendiente'])
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Factura ya emitida de una comanda, si la hay. */
    public function deComanda(int $comandaId): ?array
    {
        return $this->where('comanda_id', $comandaId)
            ->whereIn('estado', ['emitida', 'pendiente'])
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function conProblemas(): int
    {
        return $this->where('estado', 'error')->countAllResults();
    }
}
