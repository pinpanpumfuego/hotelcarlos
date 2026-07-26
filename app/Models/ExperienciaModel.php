<?php

namespace App\Models;

use CodeIgniter\Model;

class ExperienciaModel extends Model
{
    protected $table         = 'experiencias';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'nombre', 'descripcion', 'incluye', 'no_incluye', 'categoria',
        'tipo_precio', 'precio', 'precio_nino', 'coste',
        'duracion_min', 'capacidad', 'minimo', 'edad_minima',
        'horarios', 'dias', 'aviso_horas', 'punto_encuentro', 'proveedor',
        'notas_internas', 'activa', 'publicada', 'orden',
    ];
    protected $useTimestamps = true;

    public const CATEGORIAS = [
        'Naturaleza'   => 'bi-tree',
        'Agua'         => 'bi-water',
        'Aventura'     => 'bi-compass',
        'Gastronomía'  => 'bi-egg-fried',
        'Bienestar'    => 'bi-flower1',
        'Cultura'      => 'bi-book',
    ];

    public const DIAS = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
    ];

    protected $validationRules = [
        'nombre'      => 'required|min_length[3]|max_length[120]',
        'precio'      => 'required|numeric|greater_than_equal_to[0]',
        'capacidad'   => 'required|is_natural_no_zero',
        'tipo_precio' => 'required|in_list[persona,grupo]',
    ];

    protected $validationMessages = [
        'nombre'    => ['required' => 'Ponle un nombre a la experiencia.'],
        'precio'    => ['required' => 'Indica el precio.', 'numeric' => 'El precio debe ser un número.'],
        'capacidad' => ['required' => 'Indica cuánta gente cabe.', 'is_natural_no_zero' => 'Debe caber al menos una persona.'],
    ];

    /** Activas, ordenadas como se quieren mostrar. */
    public function activas(): array
    {
        return $this->where('activa', 1)->orderBy('orden')->orderBy('nombre')->findAll();
    }

    /** Las que se enseñan en la web. */
    public function publicas(): array
    {
        return $this->where('activa', 1)->where('publicada', 1)
            ->orderBy('orden')->orderBy('nombre')->findAll();
    }

    /** Horarios de salida como lista: ['08:00', '15:00']. */
    public static function horariosDe(array $exp): array
    {
        $horas = array_filter(array_map('trim', explode(',', (string) $exp['horarios'])));

        return array_values(array_filter($horas, static fn ($h) => preg_match('/^\d{1,2}:\d{2}$/', $h) === 1));
    }

    /** Días de la semana en que se hace. */
    public static function diasDe(array $exp): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $exp['dias']))));
    }

    /** ¿Se hace ese día de la semana? */
    public static function seHace(array $exp, string $fecha): bool
    {
        $dias = self::diasDe($exp);

        return $dias === [] || in_array((int) date('N', strtotime($fecha)), $dias, true);
    }

    /** Frase corta con los días: «Todos los días» o «Sábados y domingos». */
    public static function textoDias(array $exp): string
    {
        $dias = self::diasDe($exp);

        if ($dias === [] || count($dias) === 7) {
            return 'Todos los días';
        }

        $nombres = array_map(static fn ($d) => mb_strtolower(self::DIAS[$d] ?? ''), $dias);

        if (count($nombres) === 1) {
            return ucfirst($nombres[0]) . 's';
        }

        $ultimo = array_pop($nombres);

        return ucfirst(implode(', ', $nombres)) . ' y ' . $ultimo;
    }

    /** «2 h 30 min» a partir de minutos. */
    public static function duracion(int $minutos): string
    {
        if ($minutos < 60) {
            return $minutos . ' min';
        }

        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return $h . ' h' . ($m > 0 ? ' ' . $m . ' min' : '');
    }

    /** Margen en pesos y en porcentaje sobre el precio de venta. */
    public static function margen(array $exp): array
    {
        $precio = (float) $exp['precio'];
        $coste  = (float) $exp['coste'];
        $margen = $precio - $coste;

        return [
            'valor'      => $margen,
            'porcentaje' => $precio > 0 ? round($margen / $precio * 100, 1) : 0.0,
        ];
    }

    /**
     * Calcula lo que cuesta una salida.
     * Por grupo se cobra una vez; por persona, según adultos y niños.
     */
    public static function calcularTotal(array $exp, int $adultos, int $ninos = 0): float
    {
        if ($exp['tipo_precio'] === 'grupo') {
            return (float) $exp['precio'];
        }

        $precioNino = $exp['precio_nino'] !== null ? (float) $exp['precio_nino'] : (float) $exp['precio'];

        return round($adultos * (float) $exp['precio'] + $ninos * $precioNino, 2);
    }
}
