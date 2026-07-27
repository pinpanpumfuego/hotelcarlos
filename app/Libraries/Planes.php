<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConfiguracionModel;
use App\Models\PlanConsumoModel;
use App\Models\PlanLineaModel;
use App\Models\PlanModel;
use App\Models\ReservaModel;

/**
 * Qué lleva incluido la tarifa y qué hay que cobrar.
 *
 * La pregunta que resuelve es siempre la misma: *«este huésped se está pidiendo
 * esto; ¿lo paga o ya está pagado?»*. Y la respuesta depende de tres cosas: qué
 * incluye su plan, cuánto ha gastado ya hoy, y en qué franja horaria estamos.
 *
 * **El derecho no se calcula, se apunta.** Un desayuno incluido es «uno por
 * persona y día», y para saber cuántos quedan hoy hace falta haber guardado
 * cuándo se gastó cada uno. Deducirlo de las comandas sería frágil: una comanda
 * anulada devolvería un derecho que ya se consumió de verdad.
 */
class Planes
{
    /** Franjas del día. La clave se guarda en `plan_lineas.franja`. */
    public const FRANJAS = [
        'desayuno' => 'Desayuno',
        'comida'   => 'Comida',
        'cena'     => 'Cena',
    ];

    private PlanLineaModel $lineas;
    private PlanConsumoModel $consumos;

    public function __construct()
    {
        $this->lineas   = new PlanLineaModel();
        $this->consumos = new PlanConsumoModel();
    }

    /**
     * En qué franja estamos ahora.
     *
     * `null` fuera de todas: a las cinco de la tarde no hay desayuno incluido
     * que valga, y eso tiene que poder decirse.
     */
    public function franjaActual(?string $hora = null): ?string
    {
        $config = new ConfiguracionModel();
        $ahora  = $hora ?? date('H:i');

        foreach (array_keys(self::FRANJAS) as $franja) {
            $rango = (string) $config->obtener('carta_franja_' . $franja, '');
            if (! str_contains($rango, '-')) {
                continue;
            }

            [$desde, $hasta] = array_map('trim', explode('-', $rango, 2));

            if ($desde !== '' && $hasta !== '' && $ahora >= $desde && $ahora <= $hasta) {
                return $franja;
            }
        }

        return null;
    }

    /** El plan de una reserva: el suyo propio, o el de su tipo de alojamiento. */
    public function deReserva(array $reserva): ?array
    {
        $planes = new PlanModel();

        // El de la reserva manda: es el que se negoció para este caso concreto
        if (! empty($reserva['plan_id'])) {
            $plan = $planes->where('id', (int) $reserva['plan_id'])->where('activo', 1)->first();
            if ($plan !== null) {
                return $plan;
            }
        }

        if (empty($reserva['unidad_id'])) {
            return null;
        }

        $tipo = db_connect()->table('unidades u')
            ->select('tu.plan_id')
            ->join('tipos_unidad tu', 'tu.id = u.tipo_id')
            ->where('u.id', (int) $reserva['unidad_id'])
            ->get()->getRowArray();

        if (empty($tipo['plan_id'])) {
            return null;
        }

        return $planes->where('id', (int) $tipo['plan_id'])->where('activo', 1)->first();
    }

    /**
     * ¿Cubre el plan este producto ahora mismo? Y si sí, ¿cuántos quedan?
     *
     * @return array{linea: array, quedan: int, tope: float}|null
     */
    public function cubre(array $reserva, array $producto, ?string $franja = null): ?array
    {
        $plan = $this->deReserva($reserva);
        if ($plan === null) {
            return null;
        }

        $franja ??= $this->franjaActual();

        foreach ($this->lineas->delPlan((int) $plan['id']) as $linea) {
            // Fuera de su franja, el derecho no vale. Un desayuno incluido no
            // se puede gastar en la cena.
            if (! empty($linea['franja']) && $linea['franja'] !== $franja) {
                continue;
            }

            $coincide = (! empty($linea['producto_id']) && (int) $linea['producto_id'] === (int) $producto['id'])
                || (! empty($linea['categoria_id']) && (int) $linea['categoria_id'] === (int) $producto['categoria_id']);

            if (! $coincide) {
                continue;
            }

            $quedan = $this->quedan($reserva, $linea);
            if ($quedan > 0) {
                return ['linea' => $linea, 'quedan' => $quedan, 'tope' => (float) $linea['tope']];
            }
        }

        return null;
    }

    /**
     * Cuántas unidades quedan hoy de un derecho.
     *
     * El total depende de cómo se cuente: por persona y día se multiplica por
     * los huéspedes y se mira solo el día de hoy; por estancia, se mira todo lo
     * gastado desde que llegó.
     */
    public function quedan(array $reserva, array $linea): int
    {
        $personas = max(1, (int) $reserva['adultos'] + (int) $reserva['ninos']);

        $total = match ($linea['por']) {
            'persona_dia'      => (int) $linea['cantidad'] * $personas,
            'persona_estancia' => (int) $linea['cantidad'] * $personas,
            default            => (int) $linea['cantidad'],
        };

        $gastado = $linea['por'] === 'persona_dia'
            ? $this->consumos->gastadoHoy((int) $reserva['id'], (int) $linea['id'])
            : $this->consumos->gastadoTotal((int) $reserva['id'], (int) $linea['id']);

        return max(0, $total - $gastado);
    }

    /**
     * Apunta que se ha gastado parte de un derecho.
     *
     * Se llama **después** de crear la línea de comanda, con su id, para que se
     * pueda deshacer si la comanda se anula.
     */
    public function apuntarConsumo(int $reservaId, int $lineaPlanId, int $cantidad, float $valor, ?int $comandaLineaId = null): void
    {
        $this->consumos->insert([
            'reserva_id'       => $reservaId,
            'linea_id'         => $lineaPlanId,
            'comanda_linea_id' => $comandaLineaId,
            'fecha'            => date('Y-m-d'),
            'cantidad'         => max(1, $cantidad),
            'valor'            => $valor,
        ]);
    }

    /** Devuelve el derecho cuando se anula la línea que lo gastó. */
    public function devolverConsumo(int $comandaLineaId): void
    {
        $this->consumos->where('comanda_linea_id', $comandaLineaId)->delete();
    }

    /**
     * Resumen para el panel y para el portal: qué le queda hoy al huésped.
     *
     * @return list<array{concepto: string, quedan: int, de: int, franja: string|null}>
     */
    public function resumen(array $reserva): array
    {
        $plan = $this->deReserva($reserva);
        if ($plan === null) {
            return [];
        }

        $personas = max(1, (int) $reserva['adultos'] + (int) $reserva['ninos']);
        $resumen  = [];

        foreach ($this->lineas->delPlanConNombres((int) $plan['id']) as $linea) {
            $total = in_array($linea['por'], ['persona_dia', 'persona_estancia'], true)
                ? (int) $linea['cantidad'] * $personas
                : (int) $linea['cantidad'];

            $resumen[] = [
                'concepto' => $linea['concepto'] ?? 'Incluido',
                'quedan'   => $this->quedan($reserva, $linea),
                'de'       => $total,
                'franja'   => $linea['franja'] ?: null,
                'por'      => $linea['por'],
            ];
        }

        return $resumen;
    }

    /** Reservas alojadas ahora mismo, para el desplegable del TPV. */
    public function reservasEnCasa(): array
    {
        return (new ReservaModel())
            ->select('reservas.id, reservas.codigo, reservas.adultos, reservas.ninos, reservas.plan_id,
                      reservas.unidad_id, unidades.nombre AS cabana,
                      huespedes.nombre, huespedes.apellidos')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->whereIn('reservas.estado', ['confirmada', 'checkin'])
            ->where('reservas.fecha_entrada <=', date('Y-m-d'))
            ->where('reservas.fecha_salida >=', date('Y-m-d'))
            ->orderBy('unidades.nombre')
            ->findAll();
    }
}
