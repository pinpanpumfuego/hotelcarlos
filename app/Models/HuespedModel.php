<?php

namespace App\Models;

use CodeIgniter\Model;

class HuespedModel extends Model
{
    protected $table         = 'huespedes';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'apellidos', 'tipo_documento', 'num_documento', 'nacionalidad', 'telefono', 'email', 'notas'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre'         => 'required|max_length[100]',
        'apellidos'      => 'required|max_length[150]',
        'tipo_documento' => 'required|in_list[CC,CE,PASAPORTE,TI,OTRO]',
        'num_documento'  => 'required|max_length[50]',
        'email'          => 'permit_empty|valid_email',
    ];
}
