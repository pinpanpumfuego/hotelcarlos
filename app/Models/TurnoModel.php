<?php

namespace App\Models;

use CodeIgniter\Model;

class TurnoModel extends Model
{
    protected $table         = 'turnos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['empleado_id', 'fecha', 'hora_inicio', 'hora_fin', 'puesto', 'notas'];
    protected $useTimestamps = true;

    /** Turnos de un rango de fechas, con los datos del empleado. */
    public function entreFechas(string $desde, string $hasta): array
    {
        return $this->select('turnos.*, empleados.nombre, empleados.apellidos, empleados.area, empleados.cargo')
            ->join('empleados', 'empleados.id = turnos.empleado_id')
            ->where('turnos.fecha >=', $desde)
            ->where('turnos.fecha <=', $hasta)
            ->orderBy('turnos.fecha')
            ->orderBy('turnos.hora_inicio')
            ->findAll();
    }

    /**
     * ¿Se solapa con otro turno del mismo empleado ese día?
     * Dos turnos se pisan si uno empieza antes de que acabe el otro.
     */
    public function haySolape(int $empleadoId, string $fecha, string $inicio, string $fin, ?int $excluirId = null): bool
    {
        $builder = $this->where('empleado_id', $empleadoId)
            ->where('fecha', $fecha)
            ->where('hora_inicio <', $fin)
            ->where('hora_fin >', $inicio);

        if ($excluirId !== null) {
            $builder->where('id !=', $excluirId);
        }

        return $builder->countAllResults() > 0;
    }

    /** Horas trabajadas por empleado en un rango, para control interno. */
    public function horasPorEmpleado(string $desde, string $hasta): array
    {
        $filas = $this->select('empleado_id, SUM(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin)) AS minutos', false)
            ->where('fecha >=', $desde)
            ->where('fecha <=', $hasta)
            ->groupBy('empleado_id')
            ->findAll();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['empleado_id']] = round(((int) $f['minutos']) / 60, 1);
        }

        return $mapa;
    }
}
