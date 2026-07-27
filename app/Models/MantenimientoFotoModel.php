<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Las fotos de una orden.
 *
 * «Antes» es lo que se encontró; «después» es la prueba de que se hizo. Sin la
 * segunda, «resuelta» es solo la palabra de alguien — y quien tiene que darla
 * por buena está normalmente en otro sitio, no delante del calentador.
 *
 * Van a `writable/uploads/mantenimiento/`, fuera de la carpeta pública.
 */
class MantenimientoFotoModel extends Model
{
    protected $table         = 'mantenimiento_fotos';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['mantenimiento_id', 'momento', 'archivo', 'usuario_id'];

    public const MOMENTOS = ['antes' => 'Cómo estaba', 'despues' => 'Cómo quedó'];

    /** Cuántas caben por orden. El tope evita llenar el disco sin querer. */
    public const TOPE_POR_ORDEN = 8;

    public const TOPE_BYTES = 6 * 1024 * 1024;

    public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function deOrden(int $ordenId): array
    {
        return $this->select('mantenimiento_fotos.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = mantenimiento_fotos.usuario_id', 'left')
            ->where('mantenimiento_id', $ordenId)
            ->orderBy('momento')
            ->orderBy('created_at')
            ->findAll();
    }

    public function cuantasTiene(int $ordenId): int
    {
        return $this->where('mantenimiento_id', $ordenId)->countAllResults();
    }
}
