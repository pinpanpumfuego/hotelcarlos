<?php

namespace App\Models;

use CodeIgniter\Model;

/** Opciones concretas dentro de un grupo de modificadores. */
class ModificadorModel extends Model
{
    protected $table         = 'modificadores';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['grupo_id', 'nombre', 'precio_extra', 'orden'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'grupo_id' => 'required|is_natural_no_zero',
        'nombre'   => 'required|min_length[1]|max_length[100]',
    ];
}
