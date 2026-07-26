<?php

namespace App\Models;

use CodeIgniter\Model;

class UnidadModel extends Model
{
    protected $table         = 'unidades';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['tipo_id', 'nombre', 'descripcion', 'ubicacion', 'orden', 'estado', 'notas', 'token_ical'];
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
            ->orderBy('unidades.orden')
            ->orderBy('unidades.nombre')
            ->findAll();
    }

    /** Una unidad con los datos de su tipo. */
    public function conTipoUna(int $id): ?array
    {
        return $this->select('unidades.*, tipos_unidad.nombre AS tipo_nombre, tipos_unidad.capacidad,
                              tipos_unidad.tarifa_base, tipos_unidad.descripcion AS tipo_descripcion')
            ->join('tipos_unidad', 'tipos_unidad.id = unidades.tipo_id')
            ->where('unidades.id', $id)
            ->first();
    }

    /** Próximas reservas de una cabaña, para la ficha. */
    public function proximasReservas(int $unidadId, int $limite = 6): array
    {
        return $this->db->table('reservas')
            ->select('reservas.id, reservas.codigo, reservas.fecha_entrada, reservas.fecha_salida,
                      reservas.estado, huespedes.nombre, huespedes.apellidos')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->where('reservas.unidad_id', $unidadId)
            ->whereIn('reservas.estado', ['pendiente', 'confirmada', 'checkin'])
            ->where('reservas.fecha_salida >=', date('Y-m-d'))
            ->orderBy('reservas.fecha_entrada')
            ->limit($limite)
            ->get()
            ->getResultArray();
    }
}
