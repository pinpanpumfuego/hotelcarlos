<?php

namespace App\Models;

use CodeIgniter\Model;

class TipoUnidadModel extends Model
{
    protected $table         = 'tipos_unidad';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'nombre', 'descripcion', 'capacidad', 'tarifa_base',
        'precio_minimo', 'precio_maximo', 'personas_incluidas',
        'suplemento_adulto', 'suplemento_nino',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre'      => 'required|min_length[3]|max_length[100]',
        'capacidad'   => 'required|is_natural_no_zero',
        'tarifa_base' => 'required|decimal',
        'precio_minimo'      => 'permit_empty|decimal',
        'precio_maximo'      => 'permit_empty|decimal',
        'personas_incluidas' => 'permit_empty|is_natural_no_zero',
        'suplemento_adulto'  => 'permit_empty|decimal',
        'suplemento_nino'    => 'permit_empty|decimal',
    ];

    protected $validationMessages = [
        'nombre' => [
            'required'   => 'El nombre es obligatorio.',
            'min_length' => 'El nombre debe tener al menos 3 caracteres.',
        ],
        'capacidad' => [
            'required'           => 'La capacidad es obligatoria.',
            'is_natural_no_zero' => 'La capacidad debe ser un número mayor que cero.',
        ],
        'tarifa_base' => [
            'required' => 'La tarifa base es obligatoria.',
            'decimal'  => 'La tarifa debe ser un número válido.',
        ],
    ];
}
