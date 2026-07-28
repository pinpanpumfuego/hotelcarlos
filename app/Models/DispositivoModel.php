<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Los equipos desde los que se puede entrar con PIN.
 *
 * Se guarda el resumen del token, nunca el token: esta tabla no sirve para
 * entrar a ninguna parte aunque alguien se la lleve entera.
 */
class DispositivoModel extends Model
{
    protected $table         = 'dispositivos_confiables';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'usuario_id', 'token_hash', 'nombre', 'ip_alta', 'ultimo_uso', 'expira',
    ];

    /** Un año. Lo bastante para no molestar cada semana, no tanto como para olvidarse. */
    public const DIAS_VALIDEZ = 365;

    public function porToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        return $this->where('token_hash', hash('sha256', $token))
            ->where('expira >=', date('Y-m-d'))
            ->first();
    }

    /** @return list<array<string, mixed>> */
    public function deUsuario(int $usuarioId): array
    {
        return $this->where('usuario_id', $usuarioId)
            ->where('expira >=', date('Y-m-d'))
            ->orderBy('ultimo_uso', 'DESC')
            ->findAll();
    }

    /** Los caducados no sirven de nada y sí estorban al mirar la lista. */
    public function purgar(): int
    {
        $this->where('expira <', date('Y-m-d'))->delete();

        return $this->db->affectedRows();
    }
}
