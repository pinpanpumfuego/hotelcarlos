<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AutomatizacionModel;
use App\Models\EnvioModel;

/**
 * Decide qué mensajes toca encolar hoy.
 *
 * Corre una vez al día desde una tarea programada. No manda nada: solo encola,
 * y de encolar a mandar se encarga `Mensajero`, que es quien mira el
 * consentimiento. Separarlo importa porque así se puede ver qué va a salir
 * antes de que salga.
 *
 * **Correrlo dos veces el mismo día no duplica nada.** Cada mensaje comprueba
 * si ya existe uno igual para esa reserva. Las tareas programadas de los
 * hostings se disparan más de una vez más a menudo de lo que parece.
 */
class Automatizaciones
{
    private AutomatizacionModel $reglas;
    private Mensajero $mensajero;

    public function __construct()
    {
        $this->reglas    = new AutomatizacionModel();
        $this->mensajero = new Mensajero();
    }

    /**
     * @return array{encolados: int, detalle: list<string>}
     */
    public function correr(?string $hoy = null): array
    {
        $hoy       = $hoy ?? date('Y-m-d');
        $encolados = 0;
        $detalle   = [];

        foreach ($this->reglas->activas() as $regla) {
            $candidatas = $this->candidatas($regla, $hoy);

            foreach ($candidatas as $c) {
                $id = $this->mensajero->encolar((int) $c['huesped_id'], $regla['plantilla_clave'], [
                    'reserva_id'        => isset($c['reserva_id']) ? (int) $c['reserva_id'] : null,
                    'canal'             => $regla['canal'],
                    'cuando'            => $hoy . ' ' . $regla['hora'],
                    'automatizacion_id' => (int) $regla['id'],
                ]);

                if ($id !== null) {
                    $encolados++;
                    $detalle[] = $regla['nombre'] . ' → ' . ($c['codigo'] ?? 'huésped ' . $c['huesped_id']);
                }
            }
        }

        return ['encolados' => $encolados, 'detalle' => $detalle];
    }

    /**
     * A quién le toca hoy según la regla.
     *
     * @return list<array<string, mixed>>
     */
    private function candidatas(array $regla, string $hoy): array
    {
        $db   = db_connect();
        $dias = (int) $regla['dias'];

        // La fecha que tiene que cumplirse hoy. Con `dias = -3` sobre la
        // llegada, hoy le toca a quien llega dentro de tres días.
        $objetivo = date('Y-m-d', strtotime($hoy . ' ' . ($dias >= 0 ? '-' : '+') . abs($dias) . ' days'));

        return match ($regla['evento']) {
            // Al confirmarse. Se mira la fecha de confirmación por
            // `updated_at`, que es lo que hay: no guardamos «confirmada_en».
            'reserva_confirmada' => $db->query(
                "SELECT r.id AS reserva_id, r.huesped_id, r.codigo
                 FROM reservas r
                 WHERE r.estado IN ('confirmada','checkin')
                   AND DATE(r.updated_at) = ?
                   AND r.huesped_id IS NOT NULL",
                [$objetivo]
            )->getResultArray(),

            // Reservas que llegan pronto y siguen debiendo dinero
            'pago_pendiente' => $db->query(
                "SELECT r.id AS reserva_id, r.huesped_id, r.codigo
                 FROM reservas r
                 WHERE r.estado IN ('pendiente','confirmada')
                   AND r.fecha_entrada = ?
                   AND r.huesped_id IS NOT NULL
                   AND (SELECT COALESCE(SUM(CASE WHEN f.tipo='cargo' THEN f.valor ELSE -f.valor END),0)
                        FROM folio_movimientos f WHERE f.reserva_id = r.id) > 0",
                [$objetivo]
            )->getResultArray(),

            'antes_llegada', 'dia_llegada' => $db->query(
                "SELECT r.id AS reserva_id, r.huesped_id, r.codigo
                 FROM reservas r
                 WHERE r.estado IN ('confirmada','checkin')
                   AND r.fecha_entrada = ?
                   AND r.huesped_id IS NOT NULL",
                [$objetivo]
            )->getResultArray(),

            // Durante la estancia: solo quien está dentro de verdad
            'durante' => $db->query(
                "SELECT r.id AS reserva_id, r.huesped_id, r.codigo
                 FROM reservas r
                 WHERE r.estado = 'checkin'
                   AND ? BETWEEN r.fecha_entrada AND r.fecha_salida
                   AND r.huesped_id IS NOT NULL",
                [$hoy]
            )->getResultArray(),

            'dia_salida' => $db->query(
                "SELECT r.id AS reserva_id, r.huesped_id, r.codigo
                 FROM reservas r
                 WHERE r.estado IN ('checkin','checkout')
                   AND r.fecha_salida = ?
                   AND r.huesped_id IS NOT NULL",
                [$objetivo]
            )->getResultArray(),

            // Tras la salida. Solo a quien de verdad se fue: mandarle la
            // encuesta a quien canceló es la clase de detalle que hace que
            // alguien no vuelva.
            'tras_salida' => $db->query(
                "SELECT r.id AS reserva_id, r.huesped_id, r.codigo
                 FROM reservas r
                 WHERE r.estado = 'checkout'
                   AND r.fecha_salida = ?
                   AND r.huesped_id IS NOT NULL",
                [$objetivo]
            )->getResultArray(),

            // Recuperación: se fue hace `dias` días y no ha vuelto ni tiene
            // nada reservado. Va por huésped, no por reserva.
            'recuperacion' => $db->query(
                "SELECT h.id AS huesped_id, MAX(r.fecha_salida) AS ultima
                 FROM huespedes h
                 JOIN reservas r ON r.huesped_id = h.id AND r.estado = 'checkout'
                 WHERE h.estado = 'activo'
                 GROUP BY h.id
                 HAVING ultima = ?
                    AND (SELECT COUNT(*) FROM reservas r2
                         WHERE r2.huesped_id = h.id
                           AND r2.fecha_entrada > ?
                           AND r2.estado != 'cancelada') = 0",
                [$objetivo, $objetivo]
            )->getResultArray(),

            default => [],
        };
    }

    /**
     * Lo que saldría hoy sin encolar nada.
     *
     * Sirve para mirar antes de encender una automatización. Encender una
     * campaña a ciegas y descubrir a quién le llegó después es la peor forma
     * de estrenar esto.
     *
     * @return list<array{regla: string, cuantos: int}>
     */
    public function ensayo(?string $hoy = null): array
    {
        $hoy   = $hoy ?? date('Y-m-d');
        $lista = [];

        foreach ($this->reglas->findAll() as $regla) {
            $lista[] = [
                'regla'   => $regla['nombre'],
                'activa'  => (int) $regla['activa'] === 1,
                'cuantos' => count($this->candidatas($regla, $hoy)),
            ];
        }

        return $lista;
    }

    /**
     * Encola la confirmación en el momento en que recepción confirma.
     *
     * Va aparte del barrido diario porque una confirmación que llega al día
     * siguiente no es una confirmación.
     */
    public function alConfirmar(int $reservaId): ?int
    {
        $reserva = db_connect()->table('reservas')->where('id', $reservaId)->get()->getRowArray();

        if ($reserva === null || $reserva['huesped_id'] === null) {
            return null;
        }

        $regla = $this->reglas->where('evento', 'reserva_confirmada')->where('activa', 1)->first();

        if ($regla === null) {
            return null;
        }

        return $this->mensajero->encolar((int) $reserva['huesped_id'], $regla['plantilla_clave'], [
            'reserva_id'        => $reservaId,
            'canal'             => $regla['canal'],
            'automatizacion_id' => (int) $regla['id'],
        ]);
    }

    /** Cuántos hay esperando en la cola. */
    public function enCola(): int
    {
        return (new EnvioModel())->where('estado', 'pendiente')->countAllResults();
    }
}
