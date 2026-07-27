<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Quién trajo a quién.
 *
 * El premio queda `pendiente` hasta que la estancia ocurre de verdad. Una
 * recomendación que acaba en una reserva cancelada no ha traído a nadie.
 */
class ReferidoModel extends Model
{
    protected $table         = 'referidos';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'referidor_id', 'referido_id', 'reserva_id', 'codigo_usado',
        'estado', 'cupon_referidor', 'cupon_referido', 'cumplido_en', 'nota',
    ];

    public const ESTADOS = [
        'pendiente' => 'Esperando la estancia',
        'cumplido'  => 'Cumplido',
        'anulado'   => 'No llegó a cumplirse',
    ];

    /** A quién ha traído alguien. */
    public function deReferidor(int $huespedId): array
    {
        return $this->select('referidos.*, h.nombre AS referido_nombre, h.apellidos AS referido_apellidos,
                              r.codigo AS reserva_codigo, r.fecha_entrada')
            ->join('huespedes h', 'h.id = referidos.referido_id', 'left')
            ->join('reservas r', 'r.id = referidos.reserva_id', 'left')
            ->where('referidos.referidor_id', $huespedId)
            ->orderBy('referidos.created_at', 'DESC')
            ->findAll();
    }

    /** Cuántos ha traído de verdad: los cumplidos. */
    public function cuantosTrajo(int $huespedId): int
    {
        return $this->where('referidor_id', $huespedId)->where('estado', 'cumplido')->countAllResults();
    }

    /** El tablero, para ver si el programa funciona. */
    public function listar(): array
    {
        return $this->select('referidos.*,
                              quien.nombre AS referidor_nombre, quien.apellidos AS referidor_apellidos,
                              h.nombre AS referido_nombre, h.apellidos AS referido_apellidos,
                              r.codigo AS reserva_codigo')
            ->join('huespedes quien', 'quien.id = referidos.referidor_id')
            ->join('huespedes h', 'h.id = referidos.referido_id', 'left')
            ->join('reservas r', 'r.id = referidos.reserva_id', 'left')
            ->orderBy('referidos.created_at', 'DESC')
            ->findAll(100);
    }
}
