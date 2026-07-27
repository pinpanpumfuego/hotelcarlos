<?php

namespace App\Models;

use CodeIgniter\Model;

class CartaProductoModel extends Model
{
    protected $table         = 'carta_productos';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'categoria_id', 'nombre', 'descripcion', 'precio', 'destino', 'disponible',
        'apto_vegano', 'apto_vegetariano', 'sin_gluten', 'sin_lactosa', 'picante',
        'alergenos', 'divisible', 'en_minibar',
    ];

    public const DESTINOS = [
        'cocina'  => 'Cocina',
        'barra'   => 'Barra',
        'directo' => 'Entrega directa',
    ];

    /** Marcas dietéticas: clave => [etiqueta, icono]. */
    public const DIETAS = [
        'apto_vegano'      => ['etiqueta' => 'Vegano', 'icono' => 'bi-flower1'],
        'apto_vegetariano' => ['etiqueta' => 'Vegetariano', 'icono' => 'bi-tree'],
        'sin_gluten'       => ['etiqueta' => 'Sin gluten', 'icono' => 'bi-slash-circle'],
        'sin_lactosa'      => ['etiqueta' => 'Sin lactosa', 'icono' => 'bi-cup'],
    ];

    /** Alérgenos de declaración habitual. */
    public const ALERGENOS = [
        'gluten'       => 'Gluten',
        'crustaceos'   => 'Crustáceos',
        'huevo'        => 'Huevo',
        'pescado'      => 'Pescado',
        'mani'         => 'Maní / cacahuete',
        'soja'         => 'Soja',
        'lacteos'      => 'Lácteos',
        'frutos_secos' => 'Frutos secos',
        'apio'         => 'Apio',
        'mostaza'      => 'Mostaza',
        'sesamo'       => 'Sésamo',
        'sulfitos'     => 'Sulfitos',
        'moluscos'     => 'Moluscos',
    ];

    public const PICANTE = [0 => 'No picante', 1 => 'Suave', 2 => 'Medio', 3 => 'Muy picante'];

    /** Lista de alérgenos de un producto como array de claves. */
    public static function alergenosDe(?string $valor): array
    {
        if ($valor === null || trim($valor) === '') {
            return [];
        }

        return array_values(array_intersect(
            array_map('trim', explode(',', $valor)),
            array_keys(self::ALERGENOS)
        ));
    }
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
