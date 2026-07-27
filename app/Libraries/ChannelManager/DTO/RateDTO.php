<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\DTO;

use App\Libraries\ChannelManager\Exceptions\InvalidDataException;
use DateTimeImmutable;

/**
 * Precio y restricciones de venta para un rango de noches.
 *
 * Igual que `AvailabilityDTO`, el rango es **[from, to]** con las dos incluidas
 * y cuenta noches.
 *
 * Sobre `ratePlan`: **Beds24 no tiene identificadores de plan tarifario.** Cada
 * alojamiento dispone de dieciséis huecos de precio (`price1`…`price16`), y lo
 * que aquí se llama plan es el **número de hueco**. Se modela como cadena
 * porque otro proveedor sí usará un id de verdad, y la frontera del módulo no
 * debe casarse con la forma que tiene Beds24 de hacer las cosas.
 */
final class RateDTO
{
    /** Beds24 admite `price1` … `price16`. */
    public const MAX_RATE_SLOTS = 16;

    /** Límites documentados en la especificación oficial de Beds24. */
    public const MIN_STAY_MAX = 365;
    public const MAX_STAY_MAX = 364;

    /**
     * @param string            $externalUnitId
     * @param DateTimeImmutable $from       Primera noche
     * @param DateTimeImmutable $to         Última noche, incluida
     * @param float|null        $price      Precio por noche. `null` = borrar el precio
     * @param string            $currency   ISO 4217. Solo para validar: la moneda es un ajuste de la propiedad, no viaja en cada tarifa
     * @param string            $ratePlan   Hueco de precio, '1'…'16'
     * @param int|null          $minStay
     * @param int|null          $maxStay
     * @param bool              $closedOnArrival
     * @param bool              $closedOnDeparture
     * @param int|null          $quantity   Cupo, si se quiere fijar junto al precio
     * @param int|null          $minGuests  Sin campo equivalente confirmado en Beds24 (TODO_BEDS24_DOCUMENTATION)
     */
    public function __construct(
        public readonly string $externalUnitId,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly ?float $price,
        public readonly string $currency = 'COP',
        public readonly string $ratePlan = '1',
        public readonly ?int $minStay = null,
        public readonly ?int $maxStay = null,
        public readonly bool $closedOnArrival = false,
        public readonly bool $closedOnDeparture = false,
        public readonly ?int $quantity = null,
        public readonly ?int $minGuests = null,
    ) {
        if (trim($this->externalUnitId) === '') {
            throw InvalidDataException::because('La tarifa no indica a qué alojamiento externo se refiere.');
        }

        if ($this->to < $this->from) {
            throw InvalidDataException::because(sprintf(
                'Rango de tarifa al revés: de %s a %s.',
                $this->from->format('Y-m-d'),
                $this->to->format('Y-m-d')
            ));
        }

        // `null` significa «borra este precio», que es válido. Negativo, no.
        if ($this->price !== null && $this->price < 0) {
            throw InvalidDataException::because('Un precio no puede ser negativo: ' . $this->price);
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw InvalidDataException::because(sprintf('Moneda no válida: "%s".', $this->currency));
        }

        $hueco = (int) $this->ratePlan;
        if ((string) $hueco !== $this->ratePlan || $hueco < 1 || $hueco > self::MAX_RATE_SLOTS) {
            throw InvalidDataException::because(sprintf(
                'Plan tarifario "%s" no válido: se espera un número del 1 al %d.',
                $this->ratePlan,
                self::MAX_RATE_SLOTS
            ));
        }

        if ($this->minStay !== null && ($this->minStay < 1 || $this->minStay > self::MIN_STAY_MAX)) {
            throw InvalidDataException::because('Estancia mínima fuera del rango admitido (1-' . self::MIN_STAY_MAX . '): ' . $this->minStay);
        }

        if ($this->maxStay !== null && ($this->maxStay < 1 || $this->maxStay > self::MAX_STAY_MAX)) {
            throw InvalidDataException::because('Estancia máxima fuera del rango admitido (1-' . self::MAX_STAY_MAX . '): ' . $this->maxStay);
        }

        if ($this->minStay !== null && $this->maxStay !== null && $this->maxStay < $this->minStay) {
            throw InvalidDataException::because(sprintf(
                'La estancia máxima (%d) es menor que la mínima (%d): nunca se podría reservar.',
                $this->maxStay,
                $this->minStay
            ));
        }

        if ($this->quantity !== null && $this->quantity < 0) {
            throw InvalidDataException::because('El cupo no puede ser negativo.');
        }

        if ($this->minGuests !== null && $this->minGuests < 1) {
            throw InvalidDataException::because('El número mínimo de huéspedes debe ser al menos 1.');
        }
    }

    /** Hueco de precio como entero, listo para componer `price1`…`price16`. */
    public function rateSlot(): int
    {
        return (int) $this->ratePlan;
    }

    public function nights(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /** ¿Lleva alguna restricción, o es solo un precio? */
    public function hasRestrictions(): bool
    {
        return $this->minStay !== null
            || $this->maxStay !== null
            || $this->closedOnArrival
            || $this->closedOnDeparture;
    }
}
