<?php

namespace App\Models;

use CodeIgniter\Model;

/** Insumos del escandallo: materia prima con su coste por unidad. */
class InsumoModel extends Model
{
    protected $table         = 'insumos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'unidad', 'es_preparacion', 'rendimiento',
        'costo_unitario', 'proveedor', 'notas', 'activo'];
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
        return $this->where('activo', 1)->orderBy('es_preparacion', 'DESC')->orderBy('nombre')->findAll();
    }

    /** Solo materia prima simple (sin preparaciones). */
    public function simples(): array
    {
        return $this->where('activo', 1)->where('es_preparacion', 0)->orderBy('nombre')->findAll();
    }

    /** Solo preparaciones (subrecetas). */
    public function preparaciones(): array
    {
        return $this->where('es_preparacion', 1)->orderBy('nombre')->findAll();
    }
}
