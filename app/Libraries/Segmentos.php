<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\HuespedModel;

/**
 * A quién le mandamos esto.
 *
 * Un segmento no se guarda como lista de personas: se guarda como **los
 * filtros**. Si se guardara la lista, mandar la campaña una semana después
 * incluiría a quien se dio de baja entre medias, y quien se dio de baja está
 * precisamente diciendo que no quiere estar en esa lista.
 *
 * Este objeto no manda nada ni mira consentimientos: solo dice quién cumple los
 * criterios. La puerta del consentimiento está en `Mensajero`, y una sola.
 */
class Segmentos
{
    /** Los filtros que existen, con lo que significan. */
    public const FILTROS = [
        'origen'        => 'Cómo nos conoció',
        'canal'         => 'Por dónde reservó',
        'pais'          => 'País',
        'ciudad'        => 'Ciudad',
        'idioma'        => 'Idioma',
        'empresa'       => 'Viene de una empresa',
        'vip'           => 'Marcado como VIP',
        'estancias_min' => 'Ha venido al menos N veces',
        'gasto_min'     => 'Ha dejado al menos',
        'sin_venir_min' => 'Lleva sin venir al menos N días',
        'sin_venir_max' => 'Y no más de N días',
        'cumple_mes'    => 'Cumple años en el mes',
        'interes'       => 'Le interesa',
    ];

    /**
     * Quiénes cumplen.
     *
     * @param array<string, mixed> $filtros
     *
     * @return list<array<string, mixed>>
     */
    public function buscar(array $filtros, int $tope = 1000): array
    {
        [$sql, $args] = $this->consulta($filtros);

        return db_connect()->query(
            $sql . ' ORDER BY h.apellidos, h.nombre LIMIT ' . max(1, $tope),
            $args
        )->getResultArray();
    }

    /** Cuántos son, sin traérselos todos. */
    public function contar(array $filtros): int
    {
        [$sql, $args] = $this->consulta($filtros);

        $fila = db_connect()->query('SELECT COUNT(*) AS cuantos FROM (' . $sql . ') AS s', $args)->getRowArray();

        return (int) ($fila['cuantos'] ?? 0);
    }

    /**
     * La consulta y sus argumentos.
     *
     * Las estancias y el gasto se calculan aquí con subconsultas en vez de
     * leerse de columnas guardadas. Con contadores denormalizados, cualquier
     * cambio en una reserva vieja los dejaría mintiendo, y una campaña montada
     * sobre un número que miente llega a quien no debía.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function consulta(array $filtros): array
    {
        $args  = [];
        $donde = ["h.estado = 'activo'"];

        $texto = static function (string $campo, ?string $valor) use (&$donde, &$args): void {
            if ($valor !== null && trim($valor) !== '') {
                $donde[] = $campo . ' = ?';
                $args[]  = trim($valor);
            }
        };

        $texto('h.origen', $filtros['origen'] ?? null);
        $texto('h.pais', $filtros['pais'] ?? null);
        $texto('h.ciudad', $filtros['ciudad'] ?? null);
        $texto('h.idioma', $filtros['idioma'] ?? null);

        if (! empty($filtros['empresa'])) {
            $donde[] = "h.empresa IS NOT NULL AND h.empresa != ''";
        }

        if (! empty($filtros['vip'])) {
            $donde[] = 'h.vip = 1';
        }

        if (! empty($filtros['cumple_mes'])) {
            $donde[] = 'MONTH(h.fecha_nacimiento) = ?';
            $args[]  = (int) $filtros['cumple_mes'];
        }

        // Por dónde reservó: basta con que UNA de sus reservas venga de ahí.
        if (! empty($filtros['canal'])) {
            $donde[] = 'EXISTS (SELECT 1 FROM reservas rc WHERE rc.huesped_id = h.id AND rc.canal = ?)';
            $args[]  = $filtros['canal'];
        }

        if (! empty($filtros['interes'])) {
            $donde[] = "EXISTS (SELECT 1 FROM huesped_preferencias p
                                WHERE p.huesped_id = h.id AND p.tipo = 'interes' AND p.valor LIKE ?)";
            $args[]  = '%' . $filtros['interes'] . '%';
        }

        // Solo cuentan las estancias que de verdad ocurrieron
        $estancias = "(SELECT COUNT(*) FROM reservas r1
                       WHERE r1.huesped_id = h.id AND r1.estado IN ('checkout','checkin'))";

        $gasto = "(SELECT COALESCE(SUM(f.valor), 0) FROM folio_movimientos f
                   JOIN reservas r2 ON r2.id = f.reserva_id
                   WHERE r2.huesped_id = h.id AND f.tipo = 'cargo' AND r2.estado != 'cancelada')";

        $ultima = "(SELECT MAX(r3.fecha_salida) FROM reservas r3
                    WHERE r3.huesped_id = h.id AND r3.estado = 'checkout')";

        if (($filtros['estancias_min'] ?? 0) > 0) {
            $donde[] = $estancias . ' >= ?';
            $args[]  = (int) $filtros['estancias_min'];
        }

        if (($filtros['gasto_min'] ?? 0) > 0) {
            $donde[] = $gasto . ' >= ?';
            $args[]  = (float) $filtros['gasto_min'];
        }

        // «Lleva sin venir al menos N días» exige que haya venido alguna vez:
        // quien nunca vino no «lleva sin venir», simplemente no ha venido, y
        // meterlo en una campaña de recuperación no tiene sentido.
        if (($filtros['sin_venir_min'] ?? 0) > 0) {
            $donde[] = $ultima . ' IS NOT NULL AND DATEDIFF(CURDATE(), ' . $ultima . ') >= ?';
            $args[]  = (int) $filtros['sin_venir_min'];
        }

        if (($filtros['sin_venir_max'] ?? 0) > 0) {
            $donde[] = $ultima . ' IS NOT NULL AND DATEDIFF(CURDATE(), ' . $ultima . ') <= ?';
            $args[]  = (int) $filtros['sin_venir_max'];
        }

        $sql = 'SELECT h.id, h.nombre, h.apellidos, h.email, h.telefono, h.idioma, h.origen,
                       ' . $estancias . ' AS estancias,
                       ' . $gasto . ' AS gasto,
                       ' . $ultima . ' AS ultima
                FROM huespedes h
                WHERE ' . implode(' AND ', $donde);

        return [$sql, $args];
    }

    /** Limpia lo que llega del formulario y descarta lo vacío. */
    public function limpiar(array $entrada): array
    {
        $limpio = [];

        foreach (array_keys(self::FILTROS) as $clave) {
            $valor = $entrada[$clave] ?? null;

            if ($valor === null || $valor === '' || $valor === '0') {
                continue;
            }

            $limpio[$clave] = is_string($valor) ? trim($valor) : $valor;
        }

        return $limpio;
    }

    /** Cómo se lee un segmento, para enseñarlo sin que parezca un formulario. */
    public function describir(array $filtros): string
    {
        if ($filtros === []) {
            return 'Todos los huéspedes activos';
        }

        $trozos = [];

        foreach ($filtros as $clave => $valor) {
            $trozos[] = match ($clave) {
                'empresa'       => 'de empresa',
                'vip'           => 'marcados VIP',
                'estancias_min' => 'con ' . (int) $valor . ' estancias o más',
                'gasto_min'     => 'que han dejado más de $' . number_format((float) $valor, 0, ',', '.'),
                'sin_venir_min' => 'sin venir desde hace ' . (int) $valor . ' días',
                'sin_venir_max' => 'y menos de ' . (int) $valor,
                'cumple_mes'    => 'que cumplen años en el mes ' . (int) $valor,
                'interes'       => 'a los que les interesa «' . $valor . '»',
                default         => (self::FILTROS[$clave] ?? $clave) . ': ' . $valor,
            };
        }

        return ucfirst(implode(', ', $trozos));
    }

    /** Los valores que de verdad hay en la base, para los desplegables. */
    public function valoresDisponibles(): array
    {
        $db = db_connect();

        $columna = static function (string $sql) use ($db): array {
            return array_values(array_filter(array_column($db->query($sql)->getResultArray(), 'v')));
        };

        return [
            'origenes' => HuespedModel::ORIGENES,
            'paises'   => $columna("SELECT DISTINCT pais AS v FROM huespedes WHERE pais != '' AND estado='activo' ORDER BY pais"),
            'ciudades' => $columna("SELECT DISTINCT ciudad AS v FROM huespedes WHERE ciudad != '' AND estado='activo' ORDER BY ciudad"),
            'canales'  => $columna("SELECT DISTINCT canal AS v FROM reservas WHERE canal != '' ORDER BY canal"),
        ];
    }
}
