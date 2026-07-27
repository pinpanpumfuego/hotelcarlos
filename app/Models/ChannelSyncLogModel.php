<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use Config\ChannelManager as ChannelManagerConfig;

/**
 * Bitácora y cola de salida a la vez.
 *
 * Una fila `direction=outbound` con `status=pending` es un envío que falta por
 * hacer. No hay tabla de cola aparte porque sería la misma información contada
 * dos veces, y dos copias de lo mismo acaban discrepando.
 *
 * Los payloads llegan aquí **ya depurados**: `scrub()` sustituye por `***`
 * cualquier campo que huela a credencial o a tarjeta.
 */
class ChannelSyncLogModel extends Model
{
    protected $table         = 'channel_sync_logs';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'channel', 'operation', 'direction', 'reference_type', 'reference_id',
        'request_payload', 'response_payload', 'http_status', 'status',
        'attempts', 'error_message', 'correlation_id', 'next_attempt_at',
        'started_at', 'finished_at',
    ];
    protected $useTimestamps = true;
    protected $updatedField  = '';   // la tabla solo tiene created_at

    /** Abre una operación y devuelve su id. */
    public function start(
        string $channel,
        string $operation,
        string $direction = 'outbound',
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?array $request = null,
        ?string $correlationId = null,
    ): int {
        return (int) $this->insert([
            'channel'         => $channel,
            'operation'       => $operation,
            'direction'       => $direction,
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'request_payload' => $request === null ? null : json_encode(self::scrub($request), JSON_UNESCAPED_UNICODE),
            'status'          => 'processing',
            'attempts'        => 1,
            'correlation_id'  => $correlationId,
            'started_at'      => date('Y-m-d H:i:s'),
        ], true);
    }

    public function succeed(int $id, ?array $response = null, ?int $httpStatus = null): void
    {
        $this->update($id, [
            'status'           => 'success',
            'http_status'      => $httpStatus,
            'response_payload' => $response === null ? null : json_encode(self::scrub($response), JSON_UNESCAPED_UNICODE),
            'error_message'    => null,
            'finished_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Marca el fallo y decide cuándo volver a intentarlo.
     *
     * El retardo crece exponencialmente **con un poco de azar**. Sin el azar,
     * siete envíos que fallaron a la vez vuelven a salir a la vez y vuelven a
     * fallar juntos contra el mismo proveedor caído.
     */
    public function fail(int $id, string $error, ?int $httpStatus = null, bool $reintentable = true): void
    {
        $fila     = $this->find($id);
        $intentos = (int) ($fila['attempts'] ?? 1);
        $config   = new ChannelManagerConfig();

        $datos = [
            'http_status'   => $httpStatus,
            'error_message' => mb_substr($error, 0, 500),
            'finished_at'   => date('Y-m-d H:i:s'),
        ];

        if ($reintentable && $intentos < $config->maxReintentos * 3) {
            $espera             = (2 ** min($intentos, 8)) + random_int(0, 30);
            $datos['status']    = 'pending';
            $datos['next_attempt_at'] = date('Y-m-d H:i:s', time() + $espera);
        } else {
            $datos['status'] = 'failed';
        }

        $this->update($id, $datos);
    }

    /**
     * Encola un envío para más tarde, sin intentarlo ahora.
     *
     * Es lo que se usa al crear una reserva en la web propia: el huésped no
     * tiene por qué esperar a que responda un tercero, y si el tercero está
     * caído la reserva local **no se revierte**.
     */
    public function queue(
        string $channel,
        string $operation,
        string $referenceType,
        string $referenceId,
        array $request,
        ?string $correlationId = null,
    ): int {
        return (int) $this->insert([
            'channel'         => $channel,
            'operation'       => $operation,
            'direction'       => 'outbound',
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'request_payload' => json_encode(self::scrub($request), JSON_UNESCAPED_UNICODE),
            'status'          => 'pending',
            'attempts'        => 0,
            'correlation_id'  => $correlationId,
            'next_attempt_at' => date('Y-m-d H:i:s'),
        ], true);
    }

    /** Envíos que toca reintentar ya. */
    public function dueOutbound(string $channel, int $limite = 20): array
    {
        return $this->where('channel', $channel)
            ->where('direction', 'outbound')
            ->where('status', 'pending')
            ->groupStart()
                ->where('next_attempt_at <=', date('Y-m-d H:i:s'))
                ->orWhere('next_attempt_at', null)
            ->groupEnd()
            ->orderBy('next_attempt_at')
            ->findAll($limite);
    }

    /** Operaciones que llevan fallando lo bastante como para avisar. */
    public function chronicFailures(string $channel): array
    {
        $config = new ChannelManagerConfig();

        return $this->where('channel', $channel)
            ->whereIn('status', ['failed', 'pending'])
            ->where('attempts >=', $config->intentosParaAlerta)
            ->orderBy('id', 'DESC')
            ->findAll(50);
    }

    /**
     * Consulta filtrada para el panel.
     *
     * @param array<string, mixed> $filtros
     */
    public function search(array $filtros): self
    {
        if (! empty($filtros['channel'])) {
            $this->where('channel', $filtros['channel']);
        }
        if (! empty($filtros['operation'])) {
            $this->where('operation', $filtros['operation']);
        }
        if (! empty($filtros['direction'])) {
            $this->where('direction', $filtros['direction']);
        }
        if (! empty($filtros['status'])) {
            $this->where('status', $filtros['status']);
        }
        if (! empty($filtros['desde'])) {
            $this->where('created_at >=', $filtros['desde'] . ' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $this->where('created_at <=', $filtros['hasta'] . ' 23:59:59');
        }

        return $this->orderBy('id', 'DESC');
    }

    /**
     * Quita de un payload cualquier cosa que no deba quedar escrita.
     *
     * Recorre en profundidad. Una clave cuyo nombre contenga `token`, `secret`,
     * `card`, `cvv`… se sustituye por `***`. Booking manda a veces tarjetas
     * virtuales dentro del cuerpo de la reserva, y eso no se guarda jamás.
     *
     * @param array<mixed> $datos
     *
     * @return array<mixed>
     */
    public static function scrub(array $datos): array
    {
        $limpio = [];

        foreach ($datos as $clave => $valor) {
            $nombre = strtolower((string) $clave);
            $es     = false;

            foreach (ChannelManagerConfig::CAMPOS_SENSIBLES as $sensible) {
                if (str_contains($nombre, $sensible)) {
                    $es = true;
                    break;
                }
            }

            if ($es) {
                $limpio[$clave] = '***';

                continue;
            }

            $limpio[$clave] = is_array($valor) ? self::scrub($valor) : $valor;
        }

        return $limpio;
    }
}
