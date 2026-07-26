<?php

namespace App\Models;

use CodeIgniter\Model;

class ReglaPrecioModel extends Model
{
    protected $table         = 'reglas_precio';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'nombre', 'tipo', 'dias', 'valor_desde', 'valor_hasta',
        'tipo_ajuste', 'ajuste', 'tipo_unidad_id', 'prioridad', 'activa',
    ];
    protected $useTimestamps = true;

    public const TIPOS = [
        'dia_semana'   => 'Día de la semana',
        'ocupacion'    => 'Ocupación del alojamiento',
        'anticipacion' => 'Antelación de la reserva',
        'duracion'     => 'Duración de la estancia',
    ];

    /** Unidad de medida de valor_desde / valor_hasta según el tipo. */
    public const UNIDADES = [
        'ocupacion'    => '%',
        'anticipacion' => 'días antes de llegar',
        'duracion'     => 'noches',
    ];

    public const DIAS_SEMANA = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
    ];

    protected $validationRules = [
        'nombre'      => 'required|min_length[3]|max_length[120]',
        'tipo'        => 'required|in_list[dia_semana,ocupacion,anticipacion,duracion]',
        'tipo_ajuste' => 'required|in_list[porcentaje,valor]',
        'ajuste'      => 'required|numeric',
    ];

    protected $validationMessages = [
        'nombre' => [
            'required'   => 'Ponle un nombre a la regla.',
            'min_length' => 'El nombre debe tener al menos 3 caracteres.',
        ],
        'ajuste' => ['required' => 'Indica el ajuste.', 'numeric' => 'El ajuste debe ser un número.'],
    ];

    /** Reglas activas ordenadas por prioridad, con el nombre del tipo de alojamiento. */
    public function activas(): array
    {
        return $this->select('reglas_precio.*, tipos_unidad.nombre AS tipo_unidad_nombre')
            ->join('tipos_unidad', 'tipos_unidad.id = reglas_precio.tipo_unidad_id', 'left')
            ->where('reglas_precio.activa', 1)
            ->orderBy('reglas_precio.prioridad', 'DESC')
            ->orderBy('reglas_precio.id')
            ->findAll();
    }

    /** Todas, para la pantalla de gestión. */
    public function listado(): array
    {
        return $this->select('reglas_precio.*, tipos_unidad.nombre AS tipo_unidad_nombre')
            ->join('tipos_unidad', 'tipos_unidad.id = reglas_precio.tipo_unidad_id', 'left')
            ->orderBy('reglas_precio.tipo')
            ->orderBy('reglas_precio.prioridad', 'DESC')
            ->findAll();
    }

    /** Frase legible con la condición de la regla: "Viernes y sábado", "Del 70 % en adelante"… */
    public static function textoCondicion(array $r): string
    {
        if ($r['tipo'] === 'dia_semana') {
            $dias = array_filter(array_map('intval', explode(',', (string) $r['dias'])));
            if ($dias === []) {
                return 'Todos los días';
            }
            $nombres = array_map(static fn ($d) => self::DIAS_SEMANA[$d] ?? '?', $dias);

            return implode(', ', $nombres);
        }

        $unidad = self::UNIDADES[$r['tipo']] ?? '';
        $desde  = $r['valor_desde'];
        $hasta  = $r['valor_hasta'];

        if ($desde !== null && $hasta !== null) {
            return "De {$desde} a {$hasta} {$unidad}";
        }
        if ($desde !== null) {
            return "Desde {$desde} {$unidad}";
        }
        if ($hasta !== null) {
            return "Hasta {$hasta} {$unidad}";
        }

        return 'Siempre';
    }

    /** Etiqueta legible del ajuste. */
    public static function textoAjuste(array $r): string
    {
        $valor = (float) $r['ajuste'];

        if ($r['tipo_ajuste'] === 'porcentaje') {
            return ($valor >= 0 ? '+' : '') . rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',') . ' %';
        }

        return ($valor >= 0 ? '+' : '-') . '$' . number_format(abs($valor), 0, ',', '.');
    }
}
