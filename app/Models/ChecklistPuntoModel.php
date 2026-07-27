<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Los puntos que hay que repasar en cada limpieza.
 *
 * Se configuran **por tipo de alojamiento**, no por cabaña: si hay siete
 * cabañas iguales, escribir el mismo checklist siete veces es la forma más
 * segura de que acaben siendo distintos.
 *
 * Un punto sin tipo vale para todas: son las cosas que se repasan siempre,
 * como cerrar el gas.
 */
class ChecklistPuntoModel extends Model
{
    protected $table         = 'checklist_puntos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['tipo_unidad_id', 'tipos', 'texto', 'zona', 'orden', 'exige_foto', 'activo'];
    protected $useTimestamps = true;

    /**
     * Los puntos que tocan para un tipo de alojamiento y un tipo de limpieza.
     *
     * Una limpieza durante la estancia no repasa lo mismo que una de salida:
     * en la primera el huésped tiene sus cosas puestas.
     */
    public function paraTipo(?int $tipoUnidadId, string $tipoLimpieza): array
    {
        $this->where('activo', 1);

        if ($tipoUnidadId !== null) {
            $this->groupStart()
                ->where('tipo_unidad_id', $tipoUnidadId)
                ->orWhere('tipo_unidad_id', null)
                ->groupEnd();
        } else {
            $this->where('tipo_unidad_id', null);
        }

        $puntos = $this->orderBy('zona')->orderBy('orden')->orderBy('id')->findAll();

        return array_values(array_filter($puntos, static function (array $p) use ($tipoLimpieza): bool {
            $suyos = array_filter(array_map('trim', explode(',', (string) ($p['tipos'] ?? ''))));

            // Sin tipos marcados, el punto vale para todas las limpiezas
            return $suyos === [] || in_array($tipoLimpieza, $suyos, true);
        }));
    }

    /** Todos, agrupados por tipo de alojamiento, para la pantalla de ajustes. */
    public function porTipoUnidad(): array
    {
        $puntos = $this->select('checklist_puntos.*, tipos_unidad.nombre AS tipo_nombre')
            ->join('tipos_unidad', 'tipos_unidad.id = checklist_puntos.tipo_unidad_id', 'left')
            ->orderBy('checklist_puntos.tipo_unidad_id')
            ->orderBy('checklist_puntos.zona')
            ->orderBy('checklist_puntos.orden')
            ->findAll();

        $agrupado = [];
        foreach ($puntos as $p) {
            $clave = $p['tipo_nombre'] ?? 'Todas las cabañas';
            $agrupado[$clave][] = $p;
        }

        return $agrupado;
    }
}
