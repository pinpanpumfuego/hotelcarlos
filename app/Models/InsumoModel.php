<?php

namespace App\Models;

use CodeIgniter\Model;

/** Insumos del escandallo: materia prima con su coste por unidad. */
class InsumoModel extends Model
{
    protected $table         = 'insumos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'unidad', 'costo_unitario', 'proveedor', 'activo'];
    protected $useTimestamps = true;

    /** Unidades de medida admitidas, con su descripción para la interfaz. */
    public const UNIDADES = [
        'g'  => 'gramos',
        'kg' => 'kilogramos',
        'ml' => 'mililitros',
        'l'  => 'litros',
        'ud' => 'unidades',
        'porc' => 'porciones',
    ];

    protected $validationRules = [
        'nombre'         => 'required|min_length[2]|max_length[120]',
        'costo_unitario' => 'required|decimal',
    ];

    protected $validationMessages = [
        'nombre'         => ['required' => 'El nombre del insumo es obligatorio.'],
        'costo_unitario' => ['required' => 'Indica el coste por unidad.', 'decimal' => 'El coste debe ser un número.'],
    ];

    public function activos(): array
    {
        return $this->where('activo', 1)->orderBy('nombre')->findAll();
    }
}
