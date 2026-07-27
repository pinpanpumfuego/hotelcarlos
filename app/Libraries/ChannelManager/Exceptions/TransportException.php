<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\Exceptions;

/**
 * Falló la comunicación con el proveedor: tiempo agotado, DNS, conexión
 * cortada, error 5xx, o una respuesta que decía ser JSON y no lo era.
 *
 * **Sí es reintentable.** Es justo el caso para el que existe el backoff:
 * el servidor de enfrente puede estar teniendo un mal momento y dentro de
 * ocho segundos responder perfectamente.
 */
class TransportException extends ChannelManagerException
{
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
