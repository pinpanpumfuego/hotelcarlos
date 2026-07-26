<?php

namespace App\Libraries;

/**
 * Calendario de festivos de Colombia, calculado (no consultado a ningún servicio).
 *
 * Las reglas son de ley y no cambian de un año a otro:
 *
 *  · Festivos de fecha fija que NO se mueven:
 *      1 ene, 1 may, 20 jul, 7 ago, 8 dic y 25 dic.
 *  · Festivos que se trasladan al lunes siguiente (Ley 51 de 1983, «Ley Emiliani»):
 *      6 ene, 19 mar, 29 jun, 15 ago, 12 oct, 1 nov y 11 nov.
 *  · Festivos que dependen de la Pascua:
 *      Jueves y Viernes Santo (no se mueven) y Ascensión (+43), Corpus Christi (+64)
 *      y Sagrado Corazón (+71), que ya caen en lunes por el traslado.
 *
 * La Pascua se calcula con el algoritmo gregoriano anónimo (Meeus/Jones/Butcher),
 * así que no depende de la extensión «calendar» de PHP ni de la zona horaria.
 */
class FestivosColombia
{
    /** Festivos ya calculados, por año. */
    private static array $cache = [];

    /** Domingo de Resurrección del año indicado, en Y-m-d. */
    public static function pascua(int $anio): string
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /**
     * Los 18 festivos del año, indexados por fecha Y-m-d.
     *
     * @return array<string, string> fecha => nombre
     */
    public static function festivos(int $anio): array
    {
        if (isset(self::$cache[$anio])) {
            return self::$cache[$anio];
        }

        $pascua = self::pascua($anio);
        $festivos = [];

        // ── Fijos: se celebran el día que caigan ──
        $fijos = [
            '01-01' => 'Año Nuevo',
            '05-01' => 'Día del Trabajo',
            '07-20' => 'Independencia de Colombia',
            '08-07' => 'Batalla de Boyacá',
            '12-08' => 'Inmaculada Concepción',
            '12-25' => 'Navidad',
        ];
        foreach ($fijos as $dia => $nombre) {
            $festivos[$anio . '-' . $dia] = $nombre;
        }

        // ── Trasladables: al lunes siguiente si no caen en lunes ──
        $trasladables = [
            '01-06' => 'Reyes Magos',
            '03-19' => 'San José',
            '06-29' => 'San Pedro y San Pablo',
            '08-15' => 'Asunción de la Virgen',
            '10-12' => 'Día de la Diversidad Étnica y Cultural',
            '11-01' => 'Todos los Santos',
            '11-11' => 'Independencia de Cartagena',
        ];
        foreach ($trasladables as $dia => $nombre) {
            $festivos[self::alLunes($anio . '-' . $dia)] = $nombre;
        }

        // ── De Pascua: los dos santos no se mueven ──
        $festivos[self::sumar($pascua, -3)] = 'Jueves Santo';
        $festivos[self::sumar($pascua, -2)] = 'Viernes Santo';

        // ── De Pascua y trasladables: el desplazamiento ya los deja en lunes ──
        $festivos[self::sumar($pascua, 43)] = 'Ascensión del Señor';
        $festivos[self::sumar($pascua, 64)] = 'Corpus Christi';
        $festivos[self::sumar($pascua, 71)] = 'Sagrado Corazón de Jesús';

        ksort($festivos);

        return self::$cache[$anio] = $festivos;
    }

    /** ¿Esta fecha es festivo nacional? */
    public static function esFestivo(string $fecha): bool
    {
        return isset(self::festivos((int) substr($fecha, 0, 4))[$fecha]);
    }

    /** Nombre del festivo, o null si es un día normal. */
    public static function nombre(string $fecha): ?string
    {
        return self::festivos((int) substr($fecha, 0, 4))[$fecha] ?? null;
    }

    /** ¿Es día no laborable? (festivo, sábado o domingo) */
    public static function esNoLaborable(string $fecha): bool
    {
        return self::esFestivo($fecha) || (int) date('N', strtotime($fecha)) >= 6;
    }

    /**
     * Rachas de días no laborables seguidos del año, con los festivos que contienen.
     * Una racha de 3 días o más con al menos un festivo es un «puente».
     *
     * @return list<array{desde: string, hasta: string, dias: int, festivos: array<string,string>, puente: bool}>
     */
    public static function rachas(int $anio): array
    {
        $festivos = self::festivos($anio);
        $rachas   = [];
        $actual   = null;

        // Se recorre desde el 25 de diciembre anterior para no cortar el fin de año
        $fecha = ($anio - 1) . '-12-25';
        $fin   = $anio . '-12-31';

        while ($fecha <= $fin) {
            if (self::esNoLaborable($fecha)) {
                if ($actual === null) {
                    $actual = ['desde' => $fecha, 'hasta' => $fecha, 'festivos' => []];
                } else {
                    $actual['hasta'] = $fecha;
                }
                if (isset($festivos[$fecha])) {
                    $actual['festivos'][$fecha] = $festivos[$fecha];
                }
            } elseif ($actual !== null) {
                $rachas[] = self::cerrarRacha($actual);
                $actual   = null;
            }

            $fecha = self::sumar($fecha, 1);
        }

        if ($actual !== null) {
            $rachas[] = self::cerrarRacha($actual);
        }

        // Solo interesan las rachas que tocan el año pedido
        return array_values(array_filter(
            $rachas,
            static fn ($r) => substr($r['hasta'], 0, 4) === (string) $anio
        ));
    }

    /** Solo los puentes: rachas de 3 días o más que incluyen algún festivo. */
    public static function puentes(int $anio): array
    {
        return array_values(array_filter(self::rachas($anio), static fn ($r) => $r['puente']));
    }

    /** Semana Santa: de Domingo de Ramos a Domingo de Resurrección. */
    public static function semanaSanta(int $anio): array
    {
        $pascua = self::pascua($anio);

        return [
            'desde'  => self::sumar($pascua, -7),
            'hasta'  => $pascua,
            'dias'   => 8,
            'pascua' => $pascua,
        ];
    }

    // ─────────────────────────────────────────────────────────────

    private static function cerrarRacha(array $r): array
    {
        $dias = (int) ((strtotime($r['hasta']) - strtotime($r['desde'])) / 86400) + 1;

        return [
            'desde'    => $r['desde'],
            'hasta'    => $r['hasta'],
            'dias'     => $dias,
            'festivos' => $r['festivos'],
            'puente'   => $dias >= 3 && $r['festivos'] !== [],
        ];
    }

    /** Traslada una fecha al lunes siguiente, si no es ya lunes. */
    private static function alLunes(string $fecha): string
    {
        $diaSemana = (int) date('N', strtotime($fecha)); // 1 = lunes

        return $diaSemana === 1 ? $fecha : self::sumar($fecha, 8 - $diaSemana);
    }

    private static function sumar(string $fecha, int $dias): string
    {
        return date('Y-m-d', strtotime($fecha . ' ' . ($dias >= 0 ? '+' : '-') . abs($dias) . ' days'));
    }
}
