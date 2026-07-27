<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Los puntos de caja.
 *
 * La de recepción y la del bar no son la misma plata ni la cuenta la misma
 * persona. Con una caja única, un descuadre en el bar se le apunta a quien
 * estaba en recepción, y eso no es un problema de contabilidad: es un problema
 * entre dos personas que van a trabajar juntas mañana.
 */
class PuntoCajaModel extends Model
{
    protected $table         = 'puntos_caja';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'clave', 'nombre', 'tipo', 'base_sugerida', 'exige_denominaciones', 'tolerancia', 'activo',
    ];

    public const TIPOS = [
        'recepcion'   => 'Recepción',
        'restaurante' => 'Restaurante',
        'bar'         => 'Bar',
        'tienda'      => 'Tienda',
        'otro'        => 'Otro',
    ];

    protected $validationRules = [
        'nombre' => 'required|max_length[60]',
    ];

    public function activos(): array
    {
        return $this->where('activo', 1)->orderBy('nombre')->findAll();
    }

    public function porClave(string $clave): ?array
    {
        return $this->where('clave', $clave)->first();
    }

    /** El de recepción, que es el que se usa si nadie dice otra cosa. */
    public function porDefecto(): ?array
    {
        return $this->porClave('recepcion') ?? $this->activos()[0] ?? null;
    }
}
