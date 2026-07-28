<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Las modalidades de tarjeta.
 *
 * «Regalo», «fidelización» y «personal» son la misma tabla con distinta
 * configuración. El día que haga falta una modalidad nueva no hay que tocar
 * código: se crea desde el panel.
 */
class TipoTarjetaModel extends Model
{
    protected $table         = 'tipos_tarjeta';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'clave', 'nombre', 'recargable', 'acumula', 'caduca_meses', 'bonus_pct',
        'descuento_pct', 'ambito', 'pin_desde', 'color', 'activo',
    ];

    public const AMBITOS = [
        'todo'        => 'Todo el hotel',
        'alojamiento' => 'Solo alojamiento',
        'restaurante' => 'Solo restaurante',
    ];

    protected $validationRules = [
        'nombre' => 'required|max_length[60]',
    ];

    public function activos(): array
    {
        return $this->where('activo', 1)->orderBy('nombre')->findAll();
    }

    public function listar(): array
    {
        return $this->orderBy('activo', 'DESC')->orderBy('nombre')->findAll();
    }
}
