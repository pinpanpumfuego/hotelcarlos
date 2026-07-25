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

    protected $validationMessages = [
        'huesped_id' => ['required' => 'Debes elegir un huésped.', 'is_natural_no_zero' => 'Huésped no válido.'],
        'unidad_id'  => ['required' => 'Debes elegir una unidad.', 'is_natural_no_zero' => 'Unidad no válida.'],
        'adultos'    => ['required' => 'Indica el número de adultos.', 'is_natural_no_zero' => 'Debe haber al menos un adulto.'],
    ];

    /** Estados que ocupan inventario: bloquean la unidad en sus fechas. */
    public const ESTADOS_ACTIVOS = ['pendiente', 'confirmada', 'checkin'];

    /** Genera un código de reserva único, p. ej. HC-4F7A2B. */
    public function generarCodigo(): string
    {
        do {
            $codigo = 'HC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while ($this->where('codigo', $codigo)->countAllResults() > 0);

        return $codigo;
    }

    /** Reservas con datos del huésped y de la unidad. */
    public function conDetalles()
    {
        return $this->select('reservas.*, huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos, unidades.nombre AS unidad_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->orderBy('reservas.fecha_entrada', 'DESC')
            ->findAll();
    }

    /**
     * Comprueba si la unidad está libre en el rango [entrada, salida).
     * El día de salida no cuenta como ocupado: otra reserva puede entrar ese mismo día.
     */
    public function unidadDisponible(int $unidadId, string $entrada, string $salida, ?int $excluirReservaId = null): bool
    {
        $builder = $this->where('unidad_id', $unidadId)
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->where('fecha_entrada <', $salida)
            ->where('fecha_salida >', $entrada);

        if ($excluirReservaId !== null) {
            $builder->where('id !=', $excluirReservaId);
        }

        return $builder->countAllResults() === 0;
    }

    /** Calcula el total: noches × tarifa base del tipo de la unidad. */
    public function calcularTotal(int $unidadId, string $entrada, string $salida): float
    {
        $noches = (new \DateTime($entrada))->diff(new \DateTime($salida))->days;

        $fila = $this->db->table('unidades')
            ->select('tipos_unidad.tarifa_base')
            ->join('tipos_unidad', 'tipos_unidad.id = unidades.tipo_id')
            ->where('unidades.id', $unidadId)
            ->get()
            ->getRowArray();

        return $noches * (float) ($fila['tarifa_base'] ?? 0);
    }
}
