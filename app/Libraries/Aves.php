<?php

namespace App\Libraries;

/**
 * Aves del bosque nublado andino de los Farallones de Cali.
 *
 * Dos cuidados que gobiernan este archivo:
 *
 * 1. **No se promete nada.** La web dice «aves que habitan el bosque nublado
 *    andino», no «las verás». Un observador de aves comprueba las listas, y
 *    prometer una especie que no aparece es la forma más rápida de perder
 *    justo al público al que va dirigido esto.
 *
 * 2. **El nombre científico manda.** Es el que usan los observadores en todo
 *    el mundo y el único que no depende del idioma. Los nombres comunes en
 *    francés y alemán están puestos de buena fe pero **conviene que los
 *    revise alguien**: en esto la gente es quisquillosa.
 *
 * El `perfil` es la silueta con que se dibuja: cada una está pensada para que
 * la especie se reconozca por su forma (la cola de raqueta del barranquero,
 * el pico del tucán barbudo, la cresta del gallito de roca).
 */
class Aves
{
    /** Especies emblemáticas, en el orden en que se muestran. */
    public const ESPECIES = [
        [
            'clave'      => 'barranquero',
            'cientifico' => 'Momotus aequatorialis',
            'perfil'     => 'motmot',
            'color'      => '#2f9e8f',
            // El del logo: es la cara del hotel
            'destacada'  => true,
        ],
        [
            'clave'      => 'tangaraMulticolor',
            'cientifico' => 'Chlorochrysa nitidissima',
            'perfil'     => 'tangara',
            'color'      => '#d8a72e',
            'endemica'   => true,
        ],
        [
            'clave'      => 'tucanBarbudo',
            'cientifico' => 'Semnornis ramphastinus',
            'perfil'     => 'barbudo',
            'color'      => '#c4642c',
        ],
        [
            'clave'      => 'gallitoRoca',
            'cientifico' => 'Rupicola peruvianus',
            'perfil'     => 'gallito',
            'color'      => '#c33b28',
        ],
        [
            'clave'      => 'colibri',
            'cientifico' => 'Colibri coruscans',
            'perfil'     => 'colibri',
            'color'      => '#3f7fb8',
        ],
        [
            'clave'      => 'pavaCaucana',
            'cientifico' => 'Penelope perspicax',
            'perfil'     => 'pava',
            'color'      => '#7a6a4f',
            'endemica'   => true,
        ],
    ];
}
