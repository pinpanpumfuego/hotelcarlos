<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\Exceptions;

/**
 * Se agotó el crédito del proveedor (HTTP 429).
 *
 * Beds24 no limita por número de peticiones sino por **créditos en una ventana
 * móvil de cinco minutos**, compartidos por todos los tokens de la misma
 * cuenta. Por eso lleva `retryAfter`: cuando la respuesta dice cuántos
 * segundos faltan para que se reponga, esperar esa cifra exacta es mucho mejor
 * que un backoff a ciegas.
 */
final class RateLimitException extends TransportException
{
    public function __construct(
        string $message,
        private readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 429, $previous);
    }

    /** Segundos que conviene esperar, si el proveedor lo ha dicho. */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
