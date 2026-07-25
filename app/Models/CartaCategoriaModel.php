<?php

namespace App\Models;

use CodeIgniter\Model;

class CartaCategoriaModel extends Model
{
    protected $table         = 'carta_categorias';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'orden'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'nombre' => 'required|min_length[2]|max_length[80]',
    ];

    public function ordenadas(): array
    {
        return $this->orderBy('orden')->orderBy('nombre')->findAll();
    }
}
