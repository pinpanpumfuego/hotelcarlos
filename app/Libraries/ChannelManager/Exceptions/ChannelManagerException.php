<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\Exceptions;

use RuntimeException;

/**
 * Raíz de todas las excepciones del módulo.
 *
 * Existe para que quien llama pueda capturar `ChannelManagerException` y
 * atrapar cualquier fallo del channel manager sin tener que conocer a Beds24
 * ni a ningún otro proveedor. Capturar `\Throwable` a secas taparía también
 * los errores de programación, que deben verse.
 */
class ChannelManagerException extends RuntimeException
{
    /**
     * Identificador de la operación, para poder seguirle la pista en los logs.
     */
    protected ?string $correlationId = null;

    public function withCorrelationId(?string $id): static
    {
        $this->correlationId = $id;

        return $this;
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * ¿Tiene sentido reintentar esta operación tal cual?
     *
     * Un tiempo de espera agotado sí; un mapeo que no existe, no: por muchas
     * veces que se repita seguirá sin existir. Distinguirlo evita que la cola
     * de reintentos se llene de trabajo que nunca va a salir bien.
     */
    public function isRetryable(): bool
    {
        return false;
    }
}
