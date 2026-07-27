<?php

declare(strict_types=1);

use App\Libraries\Permisos\Permisos;

/**
 * Atajos para las vistas.
 *
 * La regla es que **lo que no se puede hacer no se pinta**. Un botón que da
 * error al pulsarlo es peor que un botón que no está: el primero parece una
 * avería del sistema, el segundo es simplemente que ese trabajo no es tuyo.
 */

if (! function_exists('permisos')) {
    function permisos(): Permisos
    {
        return service('permisos');
    }
}

if (! function_exists('puede')) {
    /** ¿Puede el usuario de la sesión hacer esto? */
    function puede(string $clave): bool
    {
        return service('permisos')->puede($clave);
    }
}

if (! function_exists('puede_alguno')) {
    /**
     * ¿Puede alguna de estas cosas?
     *
     * Para las entradas de menú que agrupan varias pantallas: basta con poder
     * entrar a una para que el menú tenga sentido.
     *
     * @param list<string> $claves
     */
    function puede_alguno(array $claves): bool
    {
        return service('permisos')->puedeAlguno($claves);
    }
}

if (! function_exists('tope_descuento')) {
    /** Tope de descuento en porcentaje. `null` = sin límite, `0.0` = ninguno. */
    function tope_descuento(): ?float
    {
        return service('permisos')->topeDescuento();
    }
}
