<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\ChannelManager\DTO\ReservationDTO;
use CodeIgniter\Model;

/**
 * La equivalencia entre una reserva del proveedor y una nuestra.
 *
 * El índice único `(channel, external_reservation_id)` es lo que hace que todo
 * el flujo entrante se pueda repetir sin duplicar nada: procesar dos veces el
 * mismo aviso actualiza la misma fila en vez de crear otra reserva.
 */
class ChannelReservationModel extends Model
{
    protected $table         = 'channel_reservations';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'channel', 'external_reservation_id', 'local_reservation_id',
        'external_property_id', 'external_unit_id', 'source_channel', 'external_reference',
        'status', 'check_in', 'check_out', 'guest_name', 'guest_email', 'guest_phone',
        'adults', 'children', 'total_amount', 'currency', 'raw_payload',
        'cancelled_at', 'cancelled_source', 'last_external_update_at',
    ];
    protected $useTimestamps = true;

    public function findByExternal(string $channel, string $externalId): ?array
    {
        return $this->where('channel', $channel)
            ->where('external_reservation_id', $externalId)
            ->first();
    }

    public function findByLocal(int $localReservationId): ?array
    {
        return $this->where('local_reservation_id', $localReservationId)->first();
    }

    /**
     * Guarda o actualiza el reflejo de una reserva externa.
     *
     * No decide nada: el estado y las fechas vienen ya resueltos en el DTO.
     * Aquí solo se deja constancia.
     */
    public function upsertFromDTO(ReservationDTO $dto, ?int $localReservationId = null): int
    {
        $datos = [
            'channel'                 => $dto->channel,
            'external_reservation_id' => $dto->externalId,
            'external_property_id'    => $dto->externalPropertyId,
            'external_unit_id'        => $dto->externalUnitId,
            'source_channel'          => $dto->sourceChannel,
            'external_reference'      => $dto->externalReference,
            'status'                  => $dto->status,
            'check_in'                => $dto->checkIn->format('Y-m-d'),
            'check_out'               => $dto->checkOut->format('Y-m-d'),
            'guest_name'              => $dto->guestName,
            'guest_email'             => $dto->guestEmail,
            'guest_phone'             => $dto->guestPhone,
            'adults'                  => $dto->adults,
            'children'                => $dto->children,
            'total_amount'            => $dto->totalAmount,
            'currency'                => $dto->currency,
            'raw_payload'             => json_encode($dto->raw, JSON_UNESCAPED_UNICODE),
            'last_external_update_at' => $dto->lastExternalUpdateAt?->format('Y-m-d H:i:s'),
        ];

        if ($localReservationId !== null) {
            $datos['local_reservation_id'] = $localReservationId;
        }

        // La fecha de cancelación se pone una sola vez: la primera. Si el aviso
        // se reprocesa, no debe moverse; es el momento real en que se canceló.
        $existente = $this->findByExternal($dto->channel, $dto->externalId);

        if ($dto->isCancelled() && ($existente === null || empty($existente['cancelled_at']))) {
            $datos['cancelled_at']     = date('Y-m-d H:i:s');
            $datos['cancelled_source'] = $dto->sourceChannel ?? $dto->channel;
        }

        if ($existente !== null) {
            $this->update($existente['id'], $datos);

            return (int) $existente['id'];
        }

        return (int) $this->insert($datos, true);
    }

    /** Enlaza con la reserva local, cuando se crea después. */
    public function linkLocal(int $id, int $localReservationId): void
    {
        $this->update($id, ['local_reservation_id' => $localReservationId]);
    }

    /**
     * Reservas externas que **no** llegaron a tener reserva local.
     *
     * Casi siempre significa una cabaña sin mapear. Es dinero cobrado en una
     * OTA que aquí no existe: sale en el panel como diferencia a resolver.
     */
    public function orphans(string $channel): array
    {
        return $this->where('channel', $channel)
            ->where('local_reservation_id', null)
            ->where('status !=', 'cancelada')
            ->orderBy('check_in')
            ->findAll();
    }

    /** Para el panel: últimas reservas importadas. */
    public function latest(string $channel, int $limite = 50): array
    {
        return $this->where('channel', $channel)
            ->orderBy('created_at', 'DESC')
            ->findAll($limite);
    }

    /**
     * Vacía el payload original de lo más viejo.
     *
     * Ley 1581/2012: los datos personales no se guardan más de lo necesario.
     * La fila se conserva —hace falta para la trazabilidad— pero la copia
     * literal con nombre, correo y teléfono se borra.
     */
    public function purgeOldPayloads(int $dias): int
    {
        $limite = date('Y-m-d H:i:s', strtotime('-' . max(1, $dias) . ' days'));

        $this->builder()
            ->where('created_at <', $limite)
            ->where('raw_payload IS NOT NULL')
            ->update(['raw_payload' => null]);

        return $this->db->affectedRows();
    }
}
