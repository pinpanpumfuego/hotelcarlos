<?php

namespace App\Models;

use CodeIgniter\Model;

class ReservaModel extends Model
{
    protected $table         = 'reservas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['codigo', 'huesped_id', 'unidad_id', 'fecha_entrada', 'fecha_salida', 'adultos', 'ninos', 'estado', 'total', 'notas'];
    protected $useTimestamps = true;

    protected $validationRules = [
        'huesped_id'    => 'required|is_natural_no_zero',
        'unidad_id'     => 'required|is_natural_no_zero',
        'fecha_entrada' => 'required|valid_date[Y-m-d]',
        'fecha_salida'  => 'required|valid_date[Y-m-d]',
        'adultos'       => 'required|is_natural_no_zero',
        'estado'        => 'required|in_list[pendiente,confirmada,checkin,checkout,cancelada]',
    ];

    /** Genera un código de reserva único, p. ej. HC-4F7A2B. */
    public static function generarCodigo(): string
    {
        return 'HC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }
}
