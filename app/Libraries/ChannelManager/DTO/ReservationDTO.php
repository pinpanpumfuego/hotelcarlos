<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\DTO;

use App\Libraries\ChannelManager\Exceptions\InvalidDataException;
use DateTimeImmutable;

/**
 * Una reserva vista desde la frontera del channel manager.
 *
 * Es el único formato en el que una reserva cruza la frontera del módulo: un
 * array crudo de Beds24 **no sale nunca** del adaptador. Así el núcleo del PMS
 * no se entera de qué proveedor hay detrás, y el día que entre otro solo hay
 * que escribir un mapeador nuevo.
 *
 * Es inmutable y se valida al construirse: **si el objeto existe, sus datos ya
 * están bien**. Nadie aguas abajo tiene que volver a comprobar que la salida
 * es posterior a la entrada.
 */
final class ReservationDTO
{
    /**
     * @param string                $channel            Canal interno: 'beds24'
     * @param string                $externalId         Id de la reserva en el proveedor
     * @param string|null           $externalPropertyId Id de la propiedad en el proveedor
     * @param string|null           $externalUnitId     Id del alojamiento (room) en el proveedor
     * @param string                $status             Estado **interno** ya mapeado
     * @param DateTimeImmutable     $checkIn            Fecha de entrada
     * @param DateTimeImmutable     $checkOut           Fecha de salida (esa noche ya no ocupa)
     * @param int                   $adults
     * @param int                   $children
     * @param float                 $totalAmount
     * @param string                $currency           ISO 4217, tres letras
     * @param string|null           $guestName
     * @param string|null           $guestEmail
     * @param string|null           $guestPhone
     * @param string|null           $sourceChannel      OTA de origen: 'booking', 'airbnb'…
     * @param string|null           $externalReference  Localizador que ve el huésped en la OTA
     * @param string|null           $comment
     * @param DateTimeImmutable|null $lastExternalUpdateAt Última modificación según el proveedor
     * @param array<string, mixed>  $raw                Payload original, ya depurado
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $externalId,
        public readonly ?string $externalPropertyId,
        public readonly ?string $externalUnitId,
        public readonly string $status,
        public readonly DateTimeImmutable $checkIn,
        public readonly DateTimeImmutable $checkOut,
        public readonly int $adults = 1,
        public readonly int $children = 0,
        public readonly float $totalAmount = 0.0,
        public readonly string $currency = 'COP',
        public readonly ?string $guestName = null,
        public readonly ?string $guestEmail = null,
        public readonly ?string $guestPhone = null,
        public readonly ?string $sourceChannel = null,
        public readonly ?string $externalReference = null,
        public readonly ?string $comment = null,
        public readonly ?DateTimeImmutable $lastExternalUpdateAt = null,
        public readonly array $raw = [],
    ) {
        if (trim($this->channel) === '') {
            throw InvalidDataException::because('La reserva no indica de qué canal viene.');
        }

        if (trim($this->externalId) === '') {
            throw InvalidDataException::because('La reserva externa no tiene identificador; sin él no se puede evitar duplicarla.');
        }

        // Una estancia de cero noches no existe. Y una salida anterior a la
        // entrada suele significar que el mapeador confundió los dos campos:
        // mejor reventar aquí que bloquear una cabaña al revés.
        if ($this->checkOut <= $this->checkIn) {
            throw InvalidDataException::because(sprintf(
                'La salida (%s) no es posterior a la entrada (%s) en la reserva externa %s.',
                $this->checkOut->format('Y-m-d'),
                $this->checkIn->format('Y-m-d'),
                $this->externalId
            ));
        }

        if ($this->adults < 0 || $this->children < 0) {
            throw InvalidDataException::because('El número de huéspedes no puede ser negativo.');
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw InvalidDataException::because(sprintf('Moneda no válida: "%s". Se espera un código ISO de tres letras.', $this->currency));
        }
    }

    /** Noches que ocupa. La de salida no cuenta. */
    public function nights(): int
    {
        return (int) $this->checkIn->diff($this->checkOut)->days;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelada';
    }

    /** ¿Ocupa la cabaña? Lo decide la configuración, no el DTO. */
    public function occupies(): bool
    {
        return in_array($this->status, config('ChannelManager')->estadosQueOcupan, true);
    }

    /**
     * Datos listos para `ReservaModel`. No incluye `unidad_id`: qué cabaña le
     * toca lo decide el sistema, no el proveedor (con un solo alojamiento en
     * Beds24 representando las siete cabañas, la reserva entrante no lo dice).
     *
     * @return array<string, mixed>
     */
    public function toReservationRow(): array
    {
        return [
            'fecha_entrada'      => $this->checkIn->format('Y-m-d'),
            'fecha_salida'       => $this->checkOut->format('Y-m-d'),
            'adultos'            => max(1, $this->adults),
            'ninos'              => $this->children,
            'estado'             => $this->status,
            'total'              => $this->totalAmount,
            'canal'              => $this->sourceChannel ?? $this->channel,
            'referencia_externa' => $this->externalReference ?? $this->externalId,
        ];
    }

    /**
     * Nombre y apellidos por separado, que es como los guarda `huespedes`.
     *
     * Las OTAs mandan el nombre completo en un solo campo. Se parte por el
     * primer espacio: imperfecto para un «María del Carmen», pero recepción lo
     * corrige en el registro de entrada y es mejor que dejarlo vacío.
     *
     * @return array{0: string, 1: string}
     */
    public function splitGuestName(): array
    {
        $completo = trim((string) $this->guestName);

        if ($completo === '') {
            return ['Huésped', 'sin nombre'];
        }

        $partes   = preg_split('/\s+/', $completo, 2) ?: [];
        $nombre   = $partes[0] ?? $completo;
        $apellido = $partes[1] ?? '(sin apellidos)';

        return [$nombre, $apellido];
    }
}
