<?php

namespace App\Models;

use CodeIgniter\Model;

class TipoUnidadModel extends Model
{
    protected $table         = 'tipos_unidad';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'descripcion', 'capacidad', 'tarifa_base'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre'      => 'required|min_length[3]|max_length[100]',
        'capacidad'   => 'required|is_natural_no_zero',
        'tarifa_base' => 'required|decimal',
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
