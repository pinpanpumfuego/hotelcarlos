<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Qué le pareció la estancia.
 *
 * Hay dos momentos y no es lo mismo: preguntar **a mitad de estancia** permite
 * arreglar las cosas mientras el huésped sigue aquí, que es cuando aún se
 * puede. La de salida ya solo sirve para aprender.
 */
class EncuestaModel extends Model
{
    protected $table         = 'encuestas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'reserva_id', 'momento', 'general', 'limpieza', 'atencion', 'comida',
        'entorno', 'comentario', 'publicable', 'leida_en',
    ];
    protected $useTimestamps = true;

    public const APARTADOS = [
        'limpieza' => 'Limpieza',
        'atencion' => 'Atención',
        'comida'   => 'Comida',
        'entorno'  => 'Entorno y naturaleza',
    ];

    /** Por debajo de esto, alguien tiene que llamar al huésped. */
    public const UMBRAL_ATENCION = 3;

    /**
     * Guarda la respuesta. Si ya había una de ese momento, la sustituye.
     *
     * Se actualiza en vez de insertar otra para que contestar dos veces no
     * cuente doble en la media, que es lo que convertiría el promedio en un
     * número bonito y falso.
     */
    public function responder(int $reservaId, string $momento, array $datos): bool
    {
        $valores = [
            'reserva_id' => $reservaId,
            'momento'    => in_array($momento, ['estancia', 'salida'], true) ? $momento : 'salida',
            'general'    => $this->nota($datos['general'] ?? null) ?? 0,
            'limpieza'   => $this->nota($datos['limpieza'] ?? null),
            'atencion'   => $this->nota($datos['atencion'] ?? null),
            'comida'     => $this->nota($datos['comida'] ?? null),
            'entorno'    => $this->nota($datos['entorno'] ?? null),
            'comentario' => trim((string) ($datos['comentario'] ?? '')) ?: null,
            'publicable' => ! empty($datos['publicable']) ? 1 : 0,
        ];

        if ($valores['general'] < 1) {
            return false;
        }

        $existente = $this->where('reserva_id', $reservaId)->where('momento', $valores['momento'])->first();

        return $existente === null
            ? (bool) $this->insert($valores)
            : (bool) $this->update($existente['id'], $valores);
    }

    /** Una nota válida es del 1 al 5; cualquier otra cosa es «no contestó». */
    private function nota(mixed $valor): ?int
    {
        $n = (int) $valor;

        return $n >= 1 && $n <= 5 ? $n : null;
    }

    public function deReserva(int $reservaId, string $momento): ?array
    {
        return $this->where('reserva_id', $reservaId)->where('momento', $momento)->first();
    }

    /**
     * Las que piden una llamada: nota baja o comentario sin leer.
     *
     * Es lo que evita que una queja se quede en una tabla. Las de estancia van
     * primero porque a esas todavía se les puede dar la vuelta.
     */
    public function requierenAtencion(): array
    {
        return $this->select('encuestas.*, reservas.codigo, reservas.fecha_salida,
                              huespedes.nombre, huespedes.apellidos, huespedes.telefono')
            ->join('reservas', 'reservas.id = encuestas.reserva_id')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->groupStart()
                ->where('encuestas.general <=', self::UMBRAL_ATENCION)
                ->orWhere('encuestas.leida_en', null)
            ->groupEnd()
            ->orderBy('encuestas.momento')          // «estancia» antes que «salida»
            ->orderBy('encuestas.general')
            ->findAll(50);
    }

    public function marcarLeida(int $id): void
    {
        $this->update($id, ['leida_en' => date('Y-m-d H:i:s')]);
    }

    /**
     * Medias del periodo.
     *
     * @return array{respuestas: int, general: float|null, apartados: array<string, float|null>}
     */
    public function medias(?string $desde = null, ?string $hasta = null): array
    {
        $q = $this->select('COUNT(*) AS n, AVG(general) AS general, AVG(limpieza) AS limpieza,
                            AVG(atencion) AS atencion, AVG(comida) AS comida, AVG(entorno) AS entorno');

        if ($desde !== null) {
            $q->where('created_at >=', $desde . ' 00:00:00');
        }
        if ($hasta !== null) {
            $q->where('created_at <=', $hasta . ' 23:59:59');
        }

        $fila = $q->first();
        $n    = (int) ($fila['n'] ?? 0);

        $apartados = [];
        foreach (array_keys(self::APARTADOS) as $clave) {
            $apartados[$clave] = $fila[$clave] === null ? null : round((float) $fila[$clave], 2);
        }

        return [
            'respuestas' => $n,
            'general'    => $n === 0 || $fila['general'] === null ? null : round((float) $fila['general'], 2),
            'apartados'  => $apartados,
        ];
    }

    /** Comentarios que el huésped autorizó a usar. */
    public function publicables(int $limite = 20): array
    {
        return $this->select('encuestas.comentario, encuestas.general, encuestas.created_at, huespedes.nombre')
            ->join('reservas', 'reservas.id = encuestas.reserva_id')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->where('encuestas.publicable', 1)
            ->where('encuestas.comentario IS NOT NULL')
            ->orderBy('encuestas.id', 'DESC')
            ->findAll($limite);
    }
}
