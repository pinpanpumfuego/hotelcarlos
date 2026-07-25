<?php

namespace App\Models;

use CodeIgniter\Model;

class AusenciaModel extends Model
{
    protected $table         = 'ausencias';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['empleado_id', 'tipo', 'desde', 'hasta', 'motivo', 'estado', 'aprobada_por', 'aprobada_en'];
    protected $useTimestamps = true;

    public const TIPOS = [
        'vacaciones'  => 'Vacaciones',
        'incapacidad' => 'Incapacidad',
        'permiso'     => 'Permiso',
        'licencia'    => 'Licencia',
        'falta'       => 'Falta injustificada',
    ];

    public const COLORES = [
        'vacaciones'  => 'info',
        'incapacidad' => 'warning',
        'permiso'     => 'secondary',
        'licencia'    => 'primary',
        'falta'       => 'danger',
    ];

    /** Ausencias con el nombre del empleado. */
    public function conEmpleado(?string $estado = null): array
    {
        $builder = $this->select('ausencias.*, empleados.nombre, empleados.apellidos, empleados.cargo')
            ->join('empleados', 'empleados.id = ausencias.empleado_id');

        if ($estado !== null) {
            $builder->where('ausencias.estado', $estado);
        }

        return $builder->orderBy('ausencias.desde', 'DESC')->findAll();
    }

    /** Ausencias aprobadas que tocan un rango: para avisar en la planificación. */
    public function enRango(string $desde, string $hasta): array
    {
        $filas = $this->select('ausencias.*, empleados.nombre, empleados.apellidos')
            ->join('empleados', 'empleados.id = ausencias.empleado_id')
            ->where('ausencias.estado', 'aprobada')
            ->where('ausencias.desde <=', $hasta)
            ->where('ausencias.hasta >=', $desde)
            ->findAll();

        // Se expande a un mapa empleado → día → tipo, para consultarlo rápido
        $mapa = [];
        foreach ($filas as $a) {
            $ini = max($a['desde'], $desde);
            $fin = min($a['hasta'], $hasta);
            for ($d = new \DateTime($ini); $d->format('Y-m-d') <= $fin; $d->modify('+1 day')) {
                $mapa[(int) $a['empleado_id']][$d->format('Y-m-d')] = $a['tipo'];
            }
        }

        return $mapa;
    }

    /** Días de la ausencia, ambos incluidos. */
    public static function dias(array $ausencia): int
    {
        $desde = new \DateTime($ausencia['desde']);
        $hasta = new \DateTime($ausencia['hasta']);

        return $desde->diff($hasta)->days + 1;
    }
}
