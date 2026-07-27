<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\Exceptions;

/**
 * Los datos no cumplen las reglas del dominio.
 *
 * Se lanza desde los constructores de los DTO. La idea es que **no exista un
 * DTO inválido**: si un objeto se ha construido, sus datos ya están bien, y
 * nadie aguas abajo tiene que volver a comprobarlos.
 *
 * No es reintentable: unos datos mal formados no se arreglan repitiendo.
 */
final class InvalidDataException extends ChannelManagerException
{
    public static function because(string $motivo): self
    {
        return new self($motivo);
    }
}
