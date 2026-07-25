<?php

namespace App\Models;

use CodeIgniter\Model;

class UnidadModel extends Model
{
    protected $table         = 'unidades';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['tipo_id', 'nombre', 'estado', 'notas'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'tipo_id' => 'required|is_natural_no_zero',
        'nombre'  => 'required|min_length[2]|max_length[100]',
        'estado'  => 'required|in_list[disponible,ocupada,limpieza,bloqueada]',
    ];

    protected $validationMessages = [
        'tipo_id' => ['required' => 'Debes elegir un tipo de alojamiento.', 'is_natural_no_zero' => 'Tipo de alojamiento no válido.'],
        'nombre'  => ['required' => 'El nombre es obligatorio.'],
        'estado'  => ['in_list' => 'Estado no válido.'],
    ];

    /** Unidades con el nombre de su tipo de alojamiento. */
    public function conTipo()
    {
        return $this->select('unidades.*, tipos_unidad.nombre AS tipo_nombre, tipos_unidad.capacidad, tipos_unidad.tarifa_base')
            ->join('tipos_unidad', 'tipos_unidad.id = unidades.tipo_id')
            ->orderBy('unidades.nombre')
            ->findAll();
    }
}
