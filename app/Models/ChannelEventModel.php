<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Todo lo que llega de un canal, guardado antes de intentar entenderlo.
 *
 * El orden importa: **primero se guarda, después se procesa**. Si el proceso
 * revienta a mitad, el aviso sigue aquí y se puede reintentar. Al revés se
 * perdería, y una reserva perdida no avisa: simplemente la cabaña se vende dos
 * veces.
 */
class ChannelEventModel extends Model
{
    protected $table         = 'channel_events';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'channel', 'external_event_id', 'event_type', 'external_reservation_id',
        'payload', 'payload_hash', 'status', 'attempts', 'error_message',
        'source_ip', 'correlation_id', 'received_at', 'processed_at',
    ];
    protected $useTimestamps = true;
    protected $updatedField  = 'updated_at';

    /** Intentos tras los cuales se deja de reintentar solo. */
    public const MAX_INTENTOS = 8;

    /**
     * Guarda un aviso. Si ya estaba, no hace nada.
     *
     * La idempotencia se apoya en el hash del contenido, no en un identificador
     * del proveedor: no está confirmado que Beds24 mande uno. El mismo aviso
     * repetido produce el mismo hash y choca contra el índice único.
     *
     * @param array<string, mixed> $payload
     *
     * @return array{id: int|null, repetido: bool}
     */
    public function record(
        string $channel,
        array $payload,
        string $eventType = 'booking',
        ?string $externalReservationId = null,
        ?string $sourceIp = null,
        ?string $correlationId = null,
    ): array {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = hash('sha256', $channel . '|' . $eventType . '|' . $json);

        $existe = $this->where('channel', $channel)->where('payload_hash', $hash)->first();
        if ($existe !== null) {
            return ['id' => (int) $existe['id'], 'repetido' => true];
        }

        // La carrera sigue siendo posible entre el SELECT y el INSERT: dos
        // avisos idénticos a la vez. El índice único es quien lo resuelve de
        // verdad; aquí solo se atrapa el error para no romper la petición.
        try {
            $id = $this->insert([
                'channel'                 => $channel,
                'event_type'              => $eventType,
                'external_reservation_id' => $externalReservationId,
                'payload'                 => $json,
                'payload_hash'            => $hash,
                'status'                  => 'pending',
                'source_ip'               => $sourceIp,
                'correlation_id'          => $correlationId,
                'received_at'             => date('Y-m-d H:i:s'),
            ], true);
        } catch (\Throwable) {
            return ['id' => null, 'repetido' => true];
        }

        return ['id' => $id === false ? null : (int) $id, 'repetido' => false];
    }

    /**
     * Reserva un lote de avisos pendientes para este proceso.
     *
     * Se hace con un UPDATE marcado y un SELECT después, **no con
     * `FOR UPDATE SKIP LOCKED`**: `SKIP LOCKED` no existe en MariaDB anterior a
     * la 10.6, y el servidor de producción es un Debian 11. Este patrón funciona
     * en cualquier versión y consigue lo mismo: dos procesos simultáneos no se
     * llevan el mismo aviso.
     *
     * @return list<array<string, mixed>>
     */
    public function claim(string $channel, string $correlationId, int $limite = 25): array
    {
        $marcados = $this->db->query(
            'UPDATE `channel_events`
                SET `status` = ?, `correlation_id` = ?, `attempts` = `attempts` + 1, `updated_at` = ?
              WHERE `channel` = ? AND `status` = ? AND `attempts` < ?
              ORDER BY `id`
              LIMIT ' . max(1, $limite),
            ['processing', $correlationId, date('Y-m-d H:i:s'), $channel, 'pending', self::MAX_INTENTOS]
        );

        if ($marcados === false || $this->db->affectedRows() === 0) {
            return [];
        }

        return $this->where('channel', $channel)
            ->where('correlation_id', $correlationId)
            ->where('status', 'processing')
            ->orderBy('id')
            ->findAll();
    }

    public function markProcessed(int $id): void
    {
        $this->update($id, [
            'status'        => 'processed',
            'processed_at'  => date('Y-m-d H:i:s'),
            'error_message' => null,
        ]);
    }

    /** No se procesa pero tampoco es un fallo: una consulta, por ejemplo. */
    public function markIgnored(int $id, string $motivo): void
    {
        $this->update($id, [
            'status'        => 'ignored',
            'processed_at'  => date('Y-m-d H:i:s'),
            'error_message' => mb_substr($motivo, 0, 500),
        ]);
    }

    /**
     * @param bool $reintentable Si no lo es, se marca `failed` y no se vuelve
     *                           a intentar solo. Un mapeo que falta no aparece
     *                           por repetir la operación.
     */
    public function markFailed(int $id, string $error, bool $reintentable = true): void
    {
        $fila     = $this->find($id);
        $intentos = (int) ($fila['attempts'] ?? 0);

        $this->update($id, [
            'status'        => ($reintentable && $intentos < self::MAX_INTENTOS) ? 'pending' : 'failed',
            'error_message' => mb_substr($error, 0, 500),
        ]);
    }

    /** Avisos que se quedaron en `processing` porque el proceso murió. */
    public function rescueStale(int $minutos = 15): int
    {
        $limite = date('Y-m-d H:i:s', strtotime('-' . $minutos . ' minutes'));

        $this->builder()
            ->where('status', 'processing')
            ->where('updated_at <', $limite)
            ->update(['status' => 'pending']);

        return $this->db->affectedRows();
    }

    /** @return array<string, int> estado => cuántos */
    public function counters(string $channel): array
    {
        $filas = $this->select('status, COUNT(*) AS total')
            ->where('channel', $channel)
            ->groupBy('status')
            ->findAll();

        $mapa = ['pending' => 0, 'processing' => 0, 'processed' => 0, 'failed' => 0, 'ignored' => 0];
        foreach ($filas as $f) {
            $mapa[$f['status']] = (int) $f['total'];
        }

        return $mapa;
    }

    /** Devuelve los avisos fallidos a la cola. Es el botón «Reintentar». */
    public function retryFailed(string $channel): int
    {
        $this->builder()
            ->where('channel', $channel)
            ->where('status', 'failed')
            ->update(['status' => 'pending', 'attempts' => 0, 'error_message' => null]);

        return $this->db->affectedRows();
    }

    /** @return array<string, mixed>|null */
    public function decodedPayload(array $evento): ?array
    {
        $datos = json_decode((string) ($evento['payload'] ?? ''), true);

        return is_array($datos) ? $datos : null;
    }
}
