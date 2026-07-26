<?php

namespace App\Libraries;

use App\Models\TraduccionModel;

/**
 * Traduce el contenido que escribe el hotel: cabañas, servicios, experiencias
 * y carta.
 *
 * Dos reglas gobiernan todo lo demás:
 *
 * 1. **El español es el original y nunca se pierde.** Si falta una traducción
 *    se enseña el español. Un hueco o media frase en inglés es peor que la
 *    frase entera en español: el visitante al menos puede copiarla y buscarla.
 *
 * 2. **Se carga de una vez, no ficha a ficha.** La página de alojamientos
 *    saca siete cabañas con sus servicios; pedir cada texto por separado
 *    serían decenas de consultas. `precargar()` trae todo lo de una tabla en
 *    una sola.
 */
class Traductor
{
    /** Idiomas de la web pública. El primero es el original. */
    public const IDIOMAS = [
        'es' => ['nombre' => 'Español',  'bandera' => '🇨🇴', 'html' => 'es-CO'],
        'en' => ['nombre' => 'English',  'bandera' => '🇬🇧', 'html' => 'en'],
        'fr' => ['nombre' => 'Français', 'bandera' => '🇫🇷', 'html' => 'fr'],
        'de' => ['nombre' => 'Deutsch',  'bandera' => '🇩🇪', 'html' => 'de'],
    ];

    public const ORIGINAL = 'es';

    /**
     * Qué se traduce de cada tabla.
     *
     * Los nombres de plato **no están aquí a propósito**: «Sancocho de gallina»
     * o «Calentado paisa» son nombres propios de un plato, no palabras. Un
     * restaurante serio los deja como están y traduce la explicación de al
     * lado. Traducirlos da resultados ridículos y además le quita al plato
     * justo lo que lo hace interesante para un extranjero.
     */
    public const CAMPOS = [
        'tipos_unidad'     => ['etiqueta' => 'Tipos de alojamiento', 'campos' => ['nombre', 'descripcion']],
        'unidades'         => ['etiqueta' => 'Cabañas',              'campos' => ['descripcion']],
        'servicios'        => ['etiqueta' => 'Servicios',            'campos' => ['nombre']],
        'experiencias'     => ['etiqueta' => 'Experiencias',         'campos' => ['nombre', 'descripcion', 'incluye']],
        'carta_categorias' => ['etiqueta' => 'Categorías de la carta', 'campos' => ['nombre']],
        'carta_productos'  => ['etiqueta' => 'Platos (solo la descripción)', 'campos' => ['descripcion']],
    ];

    private string $idioma;
    private TraduccionModel $modelo;

    /** @var array<string, array<int, array<string, string>>> tabla => id => campo => texto */
    private array $cache = [];

    public function __construct(?string $idioma = null)
    {
        $this->idioma = self::valido($idioma ?? service('request')->getLocale());
        $this->modelo = new TraduccionModel();
    }

    /** Un idioma que conozcamos, o el original. */
    public static function valido(?string $idioma): string
    {
        return isset(self::IDIOMAS[$idioma]) ? $idioma : self::ORIGINAL;
    }

    public function idioma(): string
    {
        return $this->idioma;
    }

    public function esOriginal(): bool
    {
        return $this->idioma === self::ORIGINAL;
    }

    /** Trae de golpe todo lo traducido de esas tablas en el idioma actual. */
    public function precargar(array $tablas): void
    {
        if ($this->esOriginal()) {
            return;   // el original no necesita nada
        }

        foreach ($tablas as $tabla) {
            if (isset($this->cache[$tabla])) {
                continue;
            }
            $this->cache[$tabla] = $this->modelo->deTabla($tabla, $this->idioma);
        }
    }

    /**
     * El texto en el idioma actual, o el original si no está traducido.
     *
     * @param string $original lo que hay escrito en español
     */
    public function texto(string $tabla, int $id, string $campo, ?string $original): string
    {
        if ($this->esOriginal()) {
            return (string) $original;
        }

        if (! isset($this->cache[$tabla])) {
            $this->precargar([$tabla]);
        }

        $traducido = $this->cache[$tabla][$id][$campo] ?? null;

        // Una traducción vacía es como si no existiera: mejor el español
        return ($traducido !== null && trim($traducido) !== '')
            ? $traducido
            : (string) $original;
    }

    /**
     * Traduce una lista de filas del modelo, dejándolas listas para la vista.
     *
     * @param array $filas  filas tal cual salen del modelo
     * @param array $campos qué campos traducir
     */
    public function filas(string $tabla, array $filas, array $campos): array
    {
        if ($this->esOriginal()) {
            return $filas;
        }

        $this->precargar([$tabla]);

        foreach ($filas as &$f) {
            $id = (int) ($f['id'] ?? 0);
            foreach ($campos as $campo) {
                if (array_key_exists($campo, $f)) {
                    $f[$campo] = $this->texto($tabla, $id, $campo, $f[$campo]);
                }
            }
        }

        return $filas;
    }

    /** Cuántos textos hay y cuántos traducidos, por idioma. Para el panel. */
    public function avance(): array
    {
        $porTabla = $this->modelo->conteoPorTablaEIdioma();
        $avance   = [];

        foreach (self::IDIOMAS as $codigo => $datos) {
            if ($codigo === self::ORIGINAL) {
                continue;
            }
            $hechos = 0;
            $total  = 0;
            foreach (self::CAMPOS as $tabla => $info) {
                $filas  = $this->modelo->cuantosOriginales($tabla, $info['campos']);
                $total  += $filas;
                $hechos += $porTabla[$tabla][$codigo] ?? 0;
            }
            $avance[$codigo] = [
                'nombre'  => $datos['nombre'],
                'bandera' => $datos['bandera'],
                'hechos'  => min($hechos, $total),
                'total'   => $total,
                'pct'     => $total > 0 ? (int) round(min($hechos, $total) / $total * 100) : 100,
            ];
        }

        return $avance;
    }
}
