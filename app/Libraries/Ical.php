<?php

namespace App\Libraries;

/**
 * Lectura y escritura de calendarios iCalendar (RFC 5545).
 *
 * Es el único lenguaje común que Booking, Airbnb y Expedia abren a
 * cualquier alojamiento. Solo viajan fechas ocupadas: ni precios,
 * ni nombres, ni importes.
 *
 * Se implementa a mano porque el formato es simple y así el sistema no
 * depende de una librería externa en un hosting compartido.
 */
class Ical
{
    /**
     * Genera un calendario con los tramos ocupados.
     *
     * @param list<array{desde: string, hasta: string, resumen: string, uid: string}> $eventos
     */
    public static function generar(array $eventos, string $nombre): string
    {
        $lineas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//San Antonio de los Lagos//Reservas//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escapar($nombre),
            'X-WR-TIMEZONE:America/Bogota',
        ];

        $sello = gmdate('Ymd\THis\Z');

        foreach ($eventos as $e) {
            $lineas[] = 'BEGIN:VEVENT';
            $lineas[] = 'UID:' . $e['uid'];
            $lineas[] = 'DTSTAMP:' . $sello;
            // Fechas sin hora: la noche de salida ya no está ocupada
            $lineas[] = 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime($e['desde']));
            $lineas[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime($e['hasta']));
            $lineas[] = 'SUMMARY:' . self::escapar($e['resumen']);
            $lineas[] = 'TRANSP:OPAQUE';
            $lineas[] = 'END:VEVENT';
        }

        $lineas[] = 'END:VCALENDAR';

        // El formato exige líneas de 75 octetos y saltos CRLF
        $salida = [];
        foreach ($lineas as $linea) {
            $salida[] = self::plegar($linea);
        }

        return implode("\r\n", $salida) . "\r\n";
    }

    /**
     * Extrae los eventos de un calendario ajeno.
     *
     * @return list<array{uid: string, desde: string, hasta: string, resumen: string}>
     */
    public static function leer(string $contenido): array
    {
        // Se deshace el plegado de líneas antes de nada
        $texto  = preg_replace("/\r\n[ \t]/", '', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $contenido)));
        $lineas = preg_split("/\r\n|\n/", (string) $texto);

        $eventos = [];
        $actual  = null;

        foreach ($lineas as $linea) {
            $linea = rtrim($linea);

            if ($linea === 'BEGIN:VEVENT') {
                $actual = ['uid' => '', 'desde' => '', 'hasta' => '', 'resumen' => ''];

                continue;
            }

            if ($linea === 'END:VEVENT') {
                if ($actual !== null && $actual['desde'] !== '' && $actual['hasta'] !== '') {
                    // Sin UID propio se genera uno estable, para no duplicar al releer
                    if ($actual['uid'] === '') {
                        $actual['uid'] = 'sin-uid-' . md5($actual['desde'] . $actual['hasta'] . $actual['resumen']);
                    }
                    $eventos[] = $actual;
                }
                $actual = null;

                continue;
            }

            if ($actual === null || ! str_contains($linea, ':')) {
                continue;
            }

            [$clave, $valor] = explode(':', $linea, 2);
            $propiedad = strtoupper(explode(';', $clave, 2)[0]);

            switch ($propiedad) {
                case 'UID':
                    $actual['uid'] = mb_substr(trim($valor), 0, 200);
                    break;

                case 'DTSTART':
                    $actual['desde'] = self::fecha($valor);
                    break;

                case 'DTEND':
                    $actual['hasta'] = self::fecha($valor);
                    break;

                case 'SUMMARY':
                    $actual['resumen'] = mb_substr(self::desescapar($valor), 0, 200);
                    break;
            }
        }

        return $eventos;
    }

    /**
     * Descarga un calendario.
     *
     * @return array{ok: bool, contenido: string, error: string}
     */
    public static function descargar(string $url): array
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('~^https?://~i', $url)) {
            return ['ok' => false, 'contenido' => '', 'error' => 'La dirección no es válida. Debe empezar por http:// o https://'];
        }

        $contexto = stream_context_create([
            'http' => [
                'timeout'       => 20,
                'user_agent'    => 'SanAntonioDeLosLagos/1.0 (+reservas)',
                'ignore_errors' => true,
                'follow_location' => 1,
                'max_redirects' => 3,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $contenido = @file_get_contents($url, false, $contexto);

        if ($contenido === false) {
            return ['ok' => false, 'contenido' => '', 'error' => 'No se pudo conectar con esa dirección.'];
        }

        // Un calendario de 7 cabañas no llega ni a 100 KB: si es enorme, algo va mal
        if (strlen($contenido) > 2 * 1024 * 1024) {
            return ['ok' => false, 'contenido' => '', 'error' => 'La respuesta es demasiado grande para ser un calendario.'];
        }

        if (! str_contains($contenido, 'BEGIN:VCALENDAR')) {
            return [
                'ok'        => false,
                'contenido' => '',
                'error'     => 'Esa dirección no devuelve un calendario. Comprueba que copiaste el enlace de exportar (.ics).',
            ];
        }

        return ['ok' => true, 'contenido' => $contenido, 'error' => ''];
    }

    // ─────────────────────────────────────────────────────────────

    /** «20260814» o «20260814T060000Z» → «2026-08-14». */
    private static function fecha(string $valor): string
    {
        $valor = trim($valor);

        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $valor, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return '';
    }

    private static function escapar(string $texto): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ';', ','],
            ['\\\\', '\\n', '\\n', '\\;', '\\,'],
            $texto
        );
    }

    private static function desescapar(string $texto): string
    {
        return str_replace(
            ['\\n', '\\N', '\\;', '\\,', '\\\\'],
            ["\n", "\n", ';', ',', '\\'],
            $texto
        );
    }

    /** Las líneas largas se parten a 75 octetos con un espacio al inicio. */
    private static function plegar(string $linea): string
    {
        if (strlen($linea) <= 75) {
            return $linea;
        }

        $partes = [];
        $resto  = $linea;
        $limite = 74;

        while (strlen($resto) > $limite) {
            // Se corta por caracteres completos: partir un byte UTF-8 rompería el archivo
            $trozo = mb_strcut($resto, 0, $limite, 'UTF-8');
            $partes[] = $trozo;
            $resto  = substr($resto, strlen($trozo));
            $limite = 73;
        }

        $partes[] = $resto;

        return implode("\r\n ", $partes);
    }
}
