<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * El rastro de una PQR. Se escribe, no se corrige.
 *
 * Sin esto, «se habló con el huésped y quedó conforme» es la palabra de
 * alguien. Con esto se puede reconstruir qué se hizo, cuándo y quién, que es
 * exactamente lo que hace falta el día que una queja deja de serlo y se
 * convierte en una reclamación de verdad.
 */
class PqrNotaModel extends Model
{
    protected $table         = 'pqr_notas';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['pqr_id', 'tipo', 'texto', 'usuario_id'];

    public const TIPOS = [
        'nota'       => 'Nota',
        'estado'     => 'Cambio de estado',
        'asignacion' => 'Reparto',
        'contacto'   => 'Se habló con el huésped',
        'respuesta'  => 'Respuesta',
    ];

    public function deP(int $pqrId): array
    {
        return $this->select('pqr_notas.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = pqr_notas.usuario_id', 'left')
            ->where('pqr_id', $pqrId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->findAll();
    }

    public function apuntar(int $pqrId, string $tipo, string $texto, ?int $usuarioId = null): int
    {
        $this->insert([
            'pqr_id'     => $pqrId,
            'tipo'       => array_key_exists($tipo, self::TIPOS) ? $tipo : 'nota',
            'texto'      => mb_substr(trim($texto), 0, 500),
            'usuario_id' => $usuarioId,
        ]);

        return (int) $this->getInsertID();
    }
}
