<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\Exceptions;

/**
 * El proveedor rechazó las credenciales (401 / 403).
 *
 * **No es reintentable**, y además es urgente: en Beds24 el *refresh token*
 * muere si pasa 30 días sin usarse, y cuando eso ocurre la sincronización se
 * apaga en silencio. Un alojamiento puede tardar semanas en notar que dejó de
 * recibir reservas. Por eso esta excepción tiene que llegar al panel como
 * aviso, no quedarse en un log.
 */
final class AuthenticationException extends ChannelManagerException
{
    public function isRetryable(): bool
    {
        return false;
    }
}
