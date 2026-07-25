<?php

namespace App\Models;

use CodeIgniter\Model;

class CartaProductoModel extends Model
{
    protected $table         = 'carta_productos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['categoria_id', 'nombre', 'descripcion', 'precio', 'disponible'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'categoria_id' => 'required|is_natural_no_zero',
        'nombre'       => 'required|min_length[2]|max_length[120]',
        'precio'       => 'required|decimal',
    ];

    protected $validationMessages = [
        'categoria_id' => ['required' => 'Elige una categoría.'],
        'nombre'       => ['required' => 'El nombre del producto es obligatorio.'],
        'precio'       => ['required' => 'Indica el precio.', 'decimal' => 'El precio debe ser un número.'],
    ];

    /** Productos con su categoría, para la carta y el TPV. */
    public function conCategoria(bool $soloDisponibles = false): array
    {
        $builder = $this->select('carta_productos.*, carta_categorias.nombre AS categoria_nombre, carta_categorias.orden AS categoria_orden')
            ->join('carta_categorias', 'carta_categorias.id = carta_productos.categoria_id');

        if ($soloDisponibles) {
            $builder->where('carta_productos.disponible', 1);
        }

        return $builder->orderBy('carta_categorias.orden')
            ->orderBy('carta_categorias.nombre')
            ->orderBy('carta_productos.nombre')
            ->findAll();
    }
}
