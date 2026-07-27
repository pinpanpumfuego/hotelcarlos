<?php

namespace App\Libraries;

use App\Models\TipoCambioModel;

/**
 * Precios en la moneda del visitante, cobrando siempre en pesos.
 *
 * Tres reglas que gobiernan todo lo demás:
 *
 * 1. **El peso colombiano manda.** La conversión es orientativa y se enseña
 *    como tal. Si un visitante cree que paga 95 € exactos y su banco le aplica
 *    otro cambio más comisión, la reclamación llega igual aunque la web fuera
 *    «técnicamente correcta».
 *
 * 2. **Nunca puede romper la página.** Si no hay cambio guardado, se enseñan
 *    solo pesos y ya está. Una web de hotel no puede caerse porque un servicio
 *    de divisas no conteste.
 *
 * 3. **Se redondea hacia arriba, a cifras limpias.** «≈ 95 USD» se lee; «≈
 *    94,73 USD» finge una precisión que no existe, porque el cambio de mañana
 *    será otro.
 */
class Monedas
{
    public const ORIGINAL = 'COP';

    public const MONEDAS = [
        'COP' => ['nombre' => 'Peso colombiano', 'simbolo' => '$',   'bandera' => '🇨🇴'],
        'USD' => ['nombre' => 'US dollar',       'simbolo' => 'US$', 'bandera' => '🇺🇸'],
        'EUR' => ['nombre' => 'Euro',            'simbolo' => '€',   'bandera' => '🇪🇺'],
    ];

    /**
     * Qué moneda se propone según el idioma de la página.
     *
     * El inglés va a dólares porque Estados Unidos es el principal mercado
     * extranjero de Colombia. Un británico verá dólares en vez de libras,
     * que es peor que acertar pero mejor que no ver nada. Para eso está el
     * selector.
     */
    public const POR_IDIOMA = [
        'es' => 'COP',
        'en' => 'USD',
        'fr' => 'EUR',
        'de' => 'EUR',
    ];

    /** @var array<string,float> moneda => pesos por unidad */
    private array $cambios;
    private string $moneda;

    public function __construct(?string $moneda = null)
    {
        $this->cambios = (new TipoCambioModel())->vigentes();
        $this->moneda  = $this->valida($moneda ?? $this->preferida());
    }

    /** Una moneda que conozcamos y para la que tengamos cambio. */
    public function valida(?string $moneda): string
    {
        $moneda = strtoupper((string) $moneda);

        if ($moneda === self::ORIGINAL || ! isset(self::MONEDAS[$moneda])) {
            return self::ORIGINAL;
        }

        // Sin cambio guardado no se puede convertir: se enseña en pesos
        return isset($this->cambios[$moneda]) ? $moneda : self::ORIGINAL;
    }

    /** La elegida por el visitante, o la que sugiere su idioma. */
    private function preferida(): string
    {
        $guardada = service('request')->getCookie('moneda');
        if ($guardada !== null && $guardada !== '') {
            return (string) $guardada;
        }

        $idioma = service('request')->getLocale();

        return self::POR_IDIOMA[$idioma] ?? self::ORIGINAL;
    }

    public function actual(): string
    {
        return $this->moneda;
    }

    public function esOriginal(): bool
    {
        return $this->moneda === self::ORIGINAL;
    }

    /** ¿Hay algo que ofrecer además de pesos? */
    public function hayConversion(): bool
    {
        return $this->cambios !== [];
    }

    /**
     * El importe convertido y ya formateado, o null si no procede.
     *
     * Devuelve null cuando la moneda es la original o falta el cambio, para
     * que la vista pueda decidir si pinta la línea o no, sin comprobaciones
     * repartidas por las plantillas.
     */
    public function aproximado(float $pesos): ?string
    {
        if ($this->esOriginal() || $pesos <= 0) {
            return null;
        }

        $tasa = $this->cambios[$this->moneda] ?? null;
        if ($tasa === null || $tasa <= 0) {
            return null;
        }

        return self::MONEDAS[$this->moneda]['simbolo'] . ' ' . $this->redondear($pesos / $tasa);
    }

    /**
     * Redondeo con cifras limpias.
     *
     * Por debajo de 100 se redondea al entero; por encima, a la decena. Fingir
     * céntimos en una conversión que cambia cada día es dar una precisión
     * falsa.
     */
    private function redondear(float $valor): string
    {
        $n = $valor < 100 ? round($valor) : round($valor / 10) * 10;

        return number_format($n, 0, ',', '.');
    }

    /** Las monedas disponibles, para el selector. */
    public function disponibles(): array
    {
        $lista = [];

        foreach (self::MONEDAS as $codigo => $datos) {
            // Solo se ofrece lo que se puede convertir de verdad
            if ($codigo !== self::ORIGINAL && ! isset($this->cambios[$codigo])) {
                continue;
            }
            $lista[] = $datos + ['codigo' => $codigo, 'activa' => $codigo === $this->moneda];
        }

        return $lista;
    }
}
