<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\Exceptions;

/**
 * Llegó algo para una unidad externa que no está mapeada a ninguna cabaña.
 *
 * **No es reintentable**: el mapeo no va a aparecer solo. El evento se marca
 * como fallido y sale en el panel, en «Unidades sin mapear», para que alguien
 * lo resuelva. Es el caso típico del día que se añade una cabaña nueva en
 * Beds24 y se olvida mapearla aquí.
 */
final class MappingNotFoundException extends ChannelManagerException
{
    public static function forExternalUnit(string $channel, string $externalUnitId): self
    {
        return new self(sprintf(
            'No hay ninguna cabaña mapeada al alojamiento externo "%s" del canal "%s".',
            $externalUnitId,
            $channel
        ));
    }

    public static function forLocalRoomType(string $channel, int $roomTypeId): self
    {
        return new self(sprintf(
            'El tipo de alojamiento local %d no está mapeado en el canal "%s".',
            $roomTypeId,
            $channel
        ));
    }
}
