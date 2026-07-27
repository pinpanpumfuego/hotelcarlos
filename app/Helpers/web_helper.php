<?php

use App\Libraries\Traductor;

/**
 * Direcciones de la web pública que respetan el idioma.
 *
 * `site_url('alojamientos')` da siempre la española. Estas funciones ponen el
 * prefijo cuando toca, para que navegar en alemán no te devuelva al español
 * en el primer clic.
 */

if (! function_exists('idioma_web')) {
    /** El idioma de la página que se está sirviendo. */
    function idioma_web(): string
    {
        return Traductor::valido(service('request')->getLocale());
    }
}

if (! function_exists('url_web')) {
    /**
     * Dirección pública en el idioma actual (o en el que se pida).
     *
     * El español va sin prefijo: es el original y así las direcciones que ya
     * circulan por ahí siguen funcionando.
     */
    function url_web(string $ruta = '', ?string $idioma = null): string
    {
        $idioma = Traductor::valido($idioma ?? idioma_web());
        $ruta   = ltrim($ruta, '/');

        if ($idioma === Traductor::ORIGINAL) {
            return site_url($ruta);
        }

        return site_url($idioma . ($ruta === '' ? '' : '/' . $ruta));
    }
}

if (! function_exists('ruta_actual_web')) {
    /**
     * La página actual sin el prefijo de idioma, para poder ofrecer la misma
     * página en otro idioma en vez de mandar a todo el mundo a la portada.
     */
    function ruta_actual_web(): string
    {
        $ruta = trim(service('request')->getUri()->getPath(), '/');

        // Fuera la carpeta de instalación (en local es /hotelcarlos/…)
        $base = trim(parse_url(base_url(), PHP_URL_PATH) ?? '', '/');
        if ($base !== '' && str_starts_with($ruta, $base)) {
            $ruta = trim(substr($ruta, strlen($base)), '/');
        }

        // Y fuera el idioma, si lo lleva
        $trozos = explode('/', $ruta);
        if (isset($trozos[0]) && isset(Traductor::IDIOMAS[$trozos[0]])) {
            array_shift($trozos);
        }

        return implode('/', $trozos);
    }
}

if (! function_exists('monedas')) {
    /** La calculadora de monedas de esta petición, creada una sola vez. */
    function monedas(): \App\Libraries\Monedas
    {
        static $m = null;

        return $m ??= new \App\Libraries\Monedas();
    }
}

if (! function_exists('precio')) {
    /**
     * El precio en pesos, que es lo que se cobra.
     *
     * Siempre en primer lugar y con «COP» escrito: es la cifra real y la que
     * aparecerá en el cargo.
     */
    function precio(float $pesos): string
    {
        return '$' . number_format($pesos, 0, ',', '.') . ' COP';
    }
}

if (! function_exists('precio_aprox')) {
    /**
     * La conversión orientativa, o cadena vacía si no procede.
     *
     * Devuelve el HTML ya montado con la clase `.aprox`, para que ninguna
     * plantilla se olvide de marcarla como aproximada. Enseñar «95 €» sin más
     * y luego cobrar en pesos es cómo se ganan las reclamaciones.
     */
    function precio_aprox(float $pesos): string
    {
        $texto = monedas()->aproximado($pesos);

        if ($texto === null) {
            return '';
        }

        return '<span class="aprox">≈ ' . esc($texto) . '</span>';
    }
}

if (! function_exists('idiomas_web')) {
    /**
     * Los cuatro idiomas con su dirección para la página actual.
     *
     * Sirve para el selector y para las etiquetas `hreflang`, que es lo que le
     * dice a Google que estas cuatro páginas son la misma en distinto idioma
     * y no contenido duplicado.
     */
    function idiomas_web(): array
    {
        $ruta   = ruta_actual_web();
        $actual = idioma_web();
        $lista  = [];

        foreach (Traductor::IDIOMAS as $codigo => $datos) {
            $lista[] = $datos + [
                'codigo' => $codigo,
                'url'    => url_web($ruta, $codigo),
                'activo' => $codigo === $actual,
            ];
        }

        return $lista;
    }
}
