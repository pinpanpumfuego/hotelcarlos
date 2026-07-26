<?php

namespace App\Libraries;

use App\Models\ReglaPrecioModel;
use App\Models\TemporadaModel;
use DateInterval;
use DatePeriod;
use DateTime;

/**
 * Motor de tarifas dinámicas.
 *
 * Calcula el precio noche a noche partiendo de la tarifa base del tipo de
 * alojamiento y apilando, en este orden:
 *
 *   1. Temporada  → precio cerrado, porcentaje o valor fijo.
 *   2. Reglas     → día de semana, ocupación, antelación y duración.
 *   3. Topes      → precio mínimo y máximo del tipo de alojamiento.
 *   4. Suplementos por personas que superan las incluidas en la tarifa.
 *
 * Siempre devuelve el desglose completo para poder explicarle al huésped
 * (y a recepción) de dónde sale cada peso.
 */
class MotorTarifas
{
    private $db;

    /** Cachés por instancia: evitan repetir consultas al cotizar un calendario. */
    private ?array $temporadas = null;
    private ?array $reglas     = null;
    private array $tipos       = [];
    private ?int $totalUnidades = null;
    private array $ocupacionPorFecha = [];
    private array $preciosCerrados   = [];

    public function __construct()
    {
        $this->db = db_connect();
    }

    // ─────────────────────────────────────────────────────────────
    //  API pública
    // ─────────────────────────────────────────────────────────────

    /**
     * Cotiza una estancia completa para un tipo de alojamiento.
     *
     * @return array{noches: list<array>, noches_total: int, alojamiento: float,
     *               suplementos: list<array>, suplementos_total: float,
     *               total: float, media_noche: float, tipo: array|null}
     */
    public function cotizar(
        int $tipoUnidadId,
        string $entrada,
        string $salida,
        int $adultos = 2,
        int $ninos = 0,
        ?int $excluirReservaId = null
    ): array {
        $tipo   = $this->tipo($tipoUnidadId);
        $noches = $this->fechas($entrada, $salida);
        $total  = count($noches);

        $detalle     = [];
        $alojamiento = 0.0;

        foreach ($noches as $fecha) {
            $linea = $this->precioNoche($tipoUnidadId, $fecha, [
                'noches'           => $total,
                'entrada'          => $entrada,
                'excluir_reserva'  => $excluirReservaId,
            ]);
            $detalle[] = $linea;
            $alojamiento += $linea['precio'];
        }

        // ── Suplementos por ocupación de la unidad ──
        $suplementos = [];
        if ($tipo !== null && $total > 0) {
            $incluidas = max(1, (int) $tipo['personas_incluidas']);
            $extra     = max(0, ($adultos + $ninos) - $incluidas);

            if ($extra > 0) {
                // Los niños ocupan las últimas plazas: primero se cubren con adultos.
                $adultosExtra = max(0, min($extra, $adultos - $incluidas));
                $ninosExtra   = $extra - $adultosExtra;

                if ($adultosExtra > 0 && (float) $tipo['suplemento_adulto'] > 0) {
                    $importe = $adultosExtra * (float) $tipo['suplemento_adulto'] * $total;
                    $suplementos[] = [
                        'concepto' => $adultosExtra . ' adulto' . ($adultosExtra > 1 ? 's' : '') . ' adicional' . ($adultosExtra > 1 ? 'es' : ''),
                        'detalle'  => '$' . $this->pesos($tipo['suplemento_adulto']) . ' × ' . $total . ' noche' . ($total > 1 ? 's' : ''),
                        'importe'  => $importe,
                    ];
                }

                if ($ninosExtra > 0 && (float) $tipo['suplemento_nino'] > 0) {
                    $importe = $ninosExtra * (float) $tipo['suplemento_nino'] * $total;
                    $suplementos[] = [
                        'concepto' => $ninosExtra . ' niño' . ($ninosExtra > 1 ? 's' : '') . ' adicional' . ($ninosExtra > 1 ? 'es' : ''),
                        'detalle'  => '$' . $this->pesos($tipo['suplemento_nino']) . ' × ' . $total . ' noche' . ($total > 1 ? 's' : ''),
                        'importe'  => $importe,
                    ];
                }
            }
        }

        $suplementosTotal = array_sum(array_column($suplementos, 'importe'));

        return [
            'tipo'              => $tipo,
            'noches'            => $detalle,
            'noches_total'      => $total,
            'alojamiento'       => round($alojamiento, 2),
            'suplementos'       => $suplementos,
            'suplementos_total' => round($suplementosTotal, 2),
            'total'             => round($alojamiento + $suplementosTotal, 2),
            'media_noche'       => $total > 0 ? round($alojamiento / $total, 2) : 0.0,
        ];
    }

    /** Igual que cotizar(), pero partiendo de una unidad concreta. */
    public function cotizarUnidad(
        int $unidadId,
        string $entrada,
        string $salida,
        int $adultos = 2,
        int $ninos = 0,
        ?int $excluirReservaId = null
    ): array {
        $fila = $this->db->table('unidades')
            ->select('tipo_id')
            ->where('id', $unidadId)
            ->get()
            ->getRowArray();

        return $this->cotizar((int) ($fila['tipo_id'] ?? 0), $entrada, $salida, $adultos, $ninos, $excluirReservaId);
    }

    /**
     * Precio de una sola noche con su desglose.
     *
     * $contexto admite: noches (duración total), entrada (para la antelación)
     * y excluir_reserva (al recalcular una reserva que se está editando).
     */
    public function precioNoche(int $tipoUnidadId, string $fecha, array $contexto = []): array
    {
        $tipo = $this->tipo($tipoUnidadId);
        $base = (float) ($tipo['tarifa_base'] ?? 0);

        $linea = [
            'fecha'     => $fecha,
            'dia'       => $this->nombreDia($fecha),
            'base'      => $base,
            'precio'    => $base,
            'temporada' => null,
            'ajustes'   => [],
            'tope'      => null,
            'ocupacion' => null,
        ];

        // ── 1. Temporada ──
        $temporada = $this->temporadaDe($fecha);
        if ($temporada !== null) {
            $linea['temporada'] = $temporada['nombre'];
            $cerrado = $this->precioCerrado((int) $temporada['id'], $tipoUnidadId);

            if ($cerrado !== null) {
                $linea['ajustes'][] = [
                    'concepto' => 'Temporada: ' . $temporada['nombre'],
                    'texto'    => 'precio cerrado',
                    'importe'  => $cerrado - $linea['precio'],
                ];
                $linea['precio'] = $cerrado;
            } else {
                $nuevo = $this->aplicar($linea['precio'], $temporada['tipo_ajuste'], (float) $temporada['ajuste']);
                if (abs($nuevo - $linea['precio']) > 0.001) {
                    $linea['ajustes'][] = [
                        'concepto' => 'Temporada: ' . $temporada['nombre'],
                        'texto'    => TemporadaModel::textoAjuste($temporada),
                        'importe'  => $nuevo - $linea['precio'],
                    ];
                }
                $linea['precio'] = $nuevo;
            }
        }

        // ── 2. Reglas ──
        // La ocupación se muestra siempre: es el dato que más ayuda a entender el precio.
        $ocupacion          = $this->ocupacion($fecha, $contexto['excluir_reserva'] ?? null);
        $linea['ocupacion'] = $ocupacion;

        foreach ($this->reglas() as $regla) {
            if ($regla['tipo_unidad_id'] !== null && (int) $regla['tipo_unidad_id'] !== $tipoUnidadId) {
                continue;
            }

            if (! $this->reglaAplica($regla, $fecha, $contexto, $ocupacion)) {
                continue;
            }

            $nuevo = $this->aplicar($linea['precio'], $regla['tipo_ajuste'], (float) $regla['ajuste']);
            if (abs($nuevo - $linea['precio']) > 0.001) {
                $linea['ajustes'][] = [
                    'concepto' => $regla['nombre'],
                    'texto'    => ReglaPrecioModel::textoAjuste($regla),
                    'importe'  => $nuevo - $linea['precio'],
                ];
            }
            $linea['precio'] = $nuevo;
        }

        // ── 3. Topes ──
        $minimo = $tipo['precio_minimo'] ?? null;
        $maximo = $tipo['precio_maximo'] ?? null;

        if ($minimo !== null && (float) $minimo > 0 && $linea['precio'] < (float) $minimo) {
            $linea['ajustes'][] = [
                'concepto' => 'Precio mínimo del alojamiento',
                'texto'    => '$' . $this->pesos($minimo),
                'importe'  => (float) $minimo - $linea['precio'],
            ];
            $linea['precio'] = (float) $minimo;
            $linea['tope']   = 'minimo';
        }

        if ($maximo !== null && (float) $maximo > 0 && $linea['precio'] > (float) $maximo) {
            $linea['ajustes'][] = [
                'concepto' => 'Precio máximo del alojamiento',
                'texto'    => '$' . $this->pesos($maximo),
                'importe'  => (float) $maximo - $linea['precio'],
            ];
            $linea['precio'] = (float) $maximo;
            $linea['tope']   = 'maximo';
        }

        $linea['precio'] = round($linea['precio'], 2);

        return $linea;
    }

    /**
     * Precios de un mes para el calendario de tarifas.
     *
     * @return array<string, array> indexado por fecha Y-m-d
     */
    public function mes(int $tipoUnidadId, int $anio, int $mes): array
    {
        $primero = sprintf('%04d-%02d-01', $anio, $mes);
        $ultimo  = date('Y-m-t', strtotime($primero));
        $salida  = date('Y-m-d', strtotime($ultimo . ' +1 day'));

        $dias = [];
        foreach ($this->fechas($primero, $salida) as $fecha) {
            // Sin contexto de estancia: el calendario muestra el precio de una noche suelta.
            $dias[$fecha] = $this->precioNoche($tipoUnidadId, $fecha);
        }

        return $dias;
    }

    /** Ocupación de una fecha en porcentaje (0–100). */
    public function ocupacion(string $fecha, ?int $excluirReservaId = null): float
    {
        $clave = $fecha . '|' . ($excluirReservaId ?? '');
        if (isset($this->ocupacionPorFecha[$clave])) {
            return $this->ocupacionPorFecha[$clave];
        }

        $total = $this->totalUnidades();
        if ($total === 0) {
            return $this->ocupacionPorFecha[$clave] = 0.0;
        }

        $builder = $this->db->table('reservas')
            ->whereIn('estado', ['pendiente', 'confirmada', 'checkin'])
            ->where('fecha_entrada <=', $fecha)
            ->where('fecha_salida >', $fecha);

        if ($excluirReservaId !== null) {
            $builder->where('id !=', $excluirReservaId);
        }

        $ocupadas = $builder->countAllResults();

        return $this->ocupacionPorFecha[$clave] = round(min(100, $ocupadas / $total * 100), 1);
    }

    /**
     * Resumen del desglose que se guarda en la reserva, en JSON compacto.
     * Se guarda el resultado, no las reglas: si mañana cambian, la reserva
     * sigue explicándose con lo que se le cobró al huésped.
     */
    public function resumenParaGuardar(array $cotizacion): string
    {
        $noches = array_map(static fn ($n) => [
            'f' => $n['fecha'],
            'p' => $n['precio'],
            'a' => array_map(static fn ($a) => [$a['concepto'], $a['texto'], round($a['importe'], 2)], $n['ajustes']),
        ], $cotizacion['noches']);

        return json_encode([
            'v'           => 1,
            'calculado'   => date('Y-m-d H:i:s'),
            'noches'      => $noches,
            'alojamiento' => $cotizacion['alojamiento'],
            'suplementos' => $cotizacion['suplementos'],
            'total'       => $cotizacion['total'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /** Lee el desglose guardado en una reserva. Devuelve null si no hay o está corrupto. */
    public static function leerResumen(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $datos = json_decode($json, true);

        return is_array($datos) ? $datos : null;
    }

    // ─────────────────────────────────────────────────────────────
    //  Internos
    // ─────────────────────────────────────────────────────────────

    /** Fechas de las noches del rango [entrada, salida): la de salida no se cobra. */
    private function fechas(string $entrada, string $salida): array
    {
        $desde = new DateTime($entrada);
        $hasta = new DateTime($salida);

        if ($hasta <= $desde) {
            return [];
        }

        $lista = [];
        foreach (new DatePeriod($desde, new DateInterval('P1D'), $hasta) as $dia) {
            $lista[] = $dia->format('Y-m-d');
        }

        return $lista;
    }

    /** Aplica un ajuste porcentual, de valor o de precio cerrado. */
    private function aplicar(float $precio, string $tipoAjuste, float $ajuste): float
    {
        return match ($tipoAjuste) {
            'porcentaje' => $precio * (1 + $ajuste / 100),
            'valor'      => $precio + $ajuste,
            'fijo'       => $ajuste,
            default      => $precio,
        };
    }

    /** Temporada activa que cubre la fecha; si hay varias, gana la de mayor prioridad. */
    private function temporadaDe(string $fecha): ?array
    {
        foreach ($this->temporadas() as $t) {
            if ($fecha >= $t['desde'] && $fecha <= $t['hasta']) {
                return $t;
            }
        }

        return null;
    }

    /** Precio cerrado del tipo en esa temporada, si lo hay. */
    private function precioCerrado(int $temporadaId, int $tipoUnidadId): ?float
    {
        $clave = $temporadaId . ':' . $tipoUnidadId;

        if (! array_key_exists($clave, $this->preciosCerrados)) {
            $fila = $this->db->table('tarifas_temporada')
                ->select('precio')
                ->where('temporada_id', $temporadaId)
                ->where('tipo_unidad_id', $tipoUnidadId)
                ->get()
                ->getRowArray();

            $this->preciosCerrados[$clave] = $fila === null ? null : (float) $fila['precio'];
        }

        return $this->preciosCerrados[$clave];
    }

    /** ¿Se cumple la condición de la regla para esta noche? */
    private function reglaAplica(array $regla, string $fecha, array $contexto, ?float $ocupacion): bool
    {
        switch ($regla['tipo']) {
            case 'dia_semana':
                $dias = array_filter(array_map('intval', explode(',', (string) $regla['dias'])));
                if ($dias === []) {
                    return false;
                }

                return in_array((int) date('N', strtotime($fecha)), $dias, true);

            case 'ocupacion':
                return $this->enRango($ocupacion ?? $this->ocupacion($fecha, $contexto['excluir_reserva'] ?? null), $regla);

            case 'anticipacion':
                $entrada = $contexto['entrada'] ?? $fecha;
                $dias    = (int) floor((strtotime($entrada) - strtotime(date('Y-m-d'))) / 86400);

                return $this->enRango(max(0, $dias), $regla);

            case 'duracion':
                return $this->enRango((float) ($contexto['noches'] ?? 1), $regla);
        }

        return false;
    }

    private function enRango(float $valor, array $regla): bool
    {
        if ($regla['valor_desde'] !== null && $valor < (float) $regla['valor_desde']) {
            return false;
        }
        if ($regla['valor_hasta'] !== null && $valor > (float) $regla['valor_hasta']) {
            return false;
        }

        return true;
    }

    private function temporadas(): array
    {
        return $this->temporadas ??= (new TemporadaModel())->activas();
    }

    private function reglas(): array
    {
        return $this->reglas ??= (new ReglaPrecioModel())->activas();
    }

    private function tipo(int $id): ?array
    {
        if (! array_key_exists($id, $this->tipos)) {
            $this->tipos[$id] = $this->db->table('tipos_unidad')->where('id', $id)->get()->getRowArray();
        }

        return $this->tipos[$id];
    }

    private function totalUnidades(): int
    {
        return $this->totalUnidades ??= $this->db->table('unidades')
            ->where('estado !=', 'bloqueada')
            ->countAllResults();
    }

    private function nombreDia(string $fecha): string
    {
        return ReglaPrecioModel::DIAS_SEMANA[(int) date('N', strtotime($fecha))] ?? '';
    }

    private function pesos($valor): string
    {
        return number_format((float) $valor, 0, ',', '.');
    }
}
