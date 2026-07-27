<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\DTO;

use App\Libraries\ChannelManager\Exceptions\InvalidDataException;
use DateTimeImmutable;

/**
 * Cuántas cabañas quedan libres en un rango de fechas.
 *
 * El rango es **[from, to]**, ambas incluidas, y cuenta **noches**, no días de
 * calendario. Si el 14 y el 15 hay tres libres, esto es un solo objeto con
 * from=14, to=15, quantity=3. Comprimir en rangos no es un capricho: Beds24
 * cobra por créditos, y mandar 365 objetos de un día cada uno en lugar de
 * quince rangos es tirar el crédito de la cuenta.
 */
final class AvailabilityDTO
{
    /**
     * @param string            $externalUnitId Alojamiento (room) en el proveedor
     * @param DateTimeImmutable $from           Primera noche del rango
     * @param DateTimeImmutable $to             Última noche del rango, incluida
     * @param int               $quantity       Unidades libres esas noches
     * @param bool              $blocked        Cerrado del todo (obra, uso propio)
     * @param bool              $closedOnArrival  No se permite entrar
     * @param bool              $closedOnDeparture No se permite salir
     */
    public function __construct(
        public readonly string $externalUnitId,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly int $quantity,
        public readonly bool $blocked = false,
        public readonly bool $closedOnArrival = false,
        public readonly bool $closedOnDeparture = false,
    ) {
        if (trim($this->externalUnitId) === '') {
            throw InvalidDataException::because('La disponibilidad no indica a qué alojamiento externo se refiere.');
        }

        if ($this->to < $this->from) {
            throw InvalidDataException::because(sprintf(
                'Rango de disponibilidad al revés: de %s a %s.',
                $this->from->format('Y-m-d'),
                $this->to->format('Y-m-d')
            ));
        }

        // Se admite negativo a propósito. Beds24 documenta que `numAvail` puede
        // serlo cuando un alojamiento está sobrevendido, y ese dato hay que
        // poder leerlo: es precisamente el que delata el problema.
        if ($this->quantity < -999 || $this->quantity > 999) {
            throw InvalidDataException::because('Cantidad disponible fuera de un rango creíble: ' . $this->quantity);
        }
    }

    /** Noches que abarca el rango. */
    public function nights(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    public function isSameDay(): bool
    {
        return $this->from->format('Y-m-d') === $this->to->format('Y-m-d');
    }

    /**
     * ¿Se puede fundir con el rango siguiente?
     *
     * Solo si es el mismo alojamiento, tiene exactamente los mismos valores y
     * las fechas son consecutivas. Es lo que permite comprimir un año entero
     * en unos pocos rangos.
     */
    public function canMergeWith(self $otro): bool
    {
        if ($this->externalUnitId !== $otro->externalUnitId) {
            return false;
        }

        if ($this->quantity !== $otro->quantity
            || $this->blocked !== $otro->blocked
            || $this->closedOnArrival !== $otro->closedOnArrival
            || $this->closedOnDeparture !== $otro->closedOnDeparture) {
            return false;
        }

        return $this->to->modify('+1 day')->format('Y-m-d') === $otro->from->format('Y-m-d');
    }

    /** Une dos rangos contiguos e iguales en uno solo. */
    public function mergedWith(self $otro): self
    {
        if (! $this->canMergeWith($otro)) {
            throw InvalidDataException::because('Se intentó unir dos rangos de disponibilidad que no son contiguos o no coinciden.');
        }

        return new self(
            $this->externalUnitId,
            $this->from,
            $otro->to,
            $this->quantity,
            $this->blocked,
            $this->closedOnArrival,
            $this->closedOnDeparture,
        );
    }
}
