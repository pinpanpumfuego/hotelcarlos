<?php

namespace App\Libraries;

use App\Models\TemporadaModel;

/**
 * Agente de tarifas: propone el calendario comercial de un año a partir
 * del calendario de festivos de Colombia y del histórico de ocupación.
 *
 * No decide solo: propone y Javier acepta lo que quiera. El precio es
 * dinero, así que nada se aplica sin que alguien lo apruebe.
 *
 * Todo lo que hace es aritmética y reglas explícitas: no hay IA por medio,
 * ni hace falta. El calendario colombiano se calcula (ver FestivosColombia)
 * y los porcentajes salen de la ocupación real cuando la hay.
 */
class AgenteTarifas
{
    /** Recargo por defecto de cada tipo de bloque, en porcentaje. */
    public const SUGERENCIAS = [
        'puente'       => 20,
        'semana_santa' => 30,
        'fin_de_ano'   => 40,
        'mitad_de_ano' => 20,
        'baja'         => -10,
    ];

    private TemporadaModel $temporadas;

    public function __construct()
    {
        $this->temporadas = new TemporadaModel();
    }

    /**
     * Años que conviene preparar y cuántas temporadas les faltan.
     *
     * El agente es manual a propósito —el precio lo aprueba una persona—
     * pero olvidarse no debería ser tan fácil: si nadie lo pasa, los puentes
     * se venden a precio base sin que nadie se entere.
     *
     * @return list<array{anio: int, faltan: int, urgente: bool, motivo: string}>
     */
    public function pendientes(): array
    {
        $avisos = [];
        $anio   = (int) date('Y');
        $mes    = (int) date('n');

        // El año en curso: solo cuenta lo que queda por delante
        $faltan = $this->sinPreparar($anio, true);
        if ($faltan > 0) {
            $avisos[] = [
                'anio'    => $anio,
                'faltan'  => $faltan,
                'urgente' => true,
                'motivo'  => 'Quedan ' . $faltan . ' temporada' . ($faltan === 1 ? '' : 's')
                    . ' de este año sin cargar. Esas fechas se están vendiendo a precio base.',
            ];
        }

        // El siguiente, a partir de octubre: da tiempo a revisarlo con calma
        if ($mes >= 10) {
            $faltanProximo = $this->sinPreparar($anio + 1);
            if ($faltanProximo > 0) {
                $avisos[] = [
                    'anio'    => $anio + 1,
                    'faltan'  => $faltanProximo,
                    'urgente' => false,
                    'motivo'  => 'Se acerca ' . ($anio + 1) . ' y aún no tiene tarifas preparadas.',
                ];
            }
        }

        return $avisos;
    }

    /**
     * Cuántas temporadas propondría el agente para un año y todavía no existen.
     *
     * Se cuenta por la clave, que lleva el año dentro, para no tener que
     * calcular la ocupación histórica solo para pintar un aviso.
     */
    public function sinPreparar(int $anio, bool $soloFuturas = false): int
    {
        $puentes = FestivosColombia::puentes($anio);

        if ($soloFuturas) {
            $hoy     = date('Y-m-d');
            $puentes = array_filter($puentes, static fn ($p) => $p['hasta'] >= $hoy);
        }

        // Los puentes más Semana Santa, fin de año, mitad de año y temporada baja
        $esperadas = count($puentes) + ($soloFuturas ? $this->bloquesFijosPendientes($anio) : 4);

        $existentes = $this->temporadas
            ->where('clave IS NOT NULL')
            ->like('clave', (string) $anio)
            ->countAllResults();

        return max(0, $esperadas - $existentes);
    }

    /** De los cuatro bloques fijos, cuántos aún no han terminado este año. */
    private function bloquesFijosPendientes(int $anio): int
    {
        $hoy = date('Y-m-d');

        $finales = [
            FestivosColombia::semanaSanta($anio)['hasta'],
            ($anio + 1) . '-01-15',   // fin de año
            $anio . '-07-15',         // vacaciones de mitad de año
            $anio . '-03-15',         // temporada baja
        ];

        return count(array_filter($finales, static fn ($f) => $f >= $hoy));
    }

    /**
     * Propuestas para un año.
     *
     * @return list<array>
     */
    public function propuestas(int $anio): array
    {
        $existentes = $this->clavesExistentes();
        $propuestas = [];

        // ── Semana Santa: la semana entera, no solo el puente ──
        $ss = FestivosColombia::semanaSanta($anio);
        $propuestas[] = $this->armar(
            'semana-santa-' . $anio,
            'Semana Santa ' . $anio,
            $ss['desde'],
            $ss['hasta'],
            self::SUGERENCIAS['semana_santa'],
            10,
            '#7a5195',
            'La semana completa, de Domingo de Ramos al Domingo de Resurrección. '
                . 'Es la temporada más fuerte del turismo interno colombiano.',
            $existentes
        );

        // ── Puentes festivos ──
        foreach (FestivosColombia::puentes($anio) as $p) {
            // La Semana Santa ya está cubierta arriba: no se propone dos veces
            if ($p['desde'] >= $ss['desde'] && $p['hasta'] <= $ss['hasta']) {
                continue;
            }

            $nombres = array_values($p['festivos']);
            $nombre  = 'Puente de ' . $this->acortar($nombres[0]);

            // La noche anterior también se vende al precio del puente:
            // el huésped llega el viernes y duerme esa noche.
            $desde = date('Y-m-d', strtotime($p['desde'] . ' -1 day'));

            $propuestas[] = $this->armar(
                'puente-' . $p['hasta'],
                $nombre,
                $desde,
                $p['hasta'],
                self::SUGERENCIAS['puente'],
                5,
                '#b9873f',
                implode(' y ', $nombres) . '. Fin de semana largo de ' . $p['dias'] . ' días.',
                $existentes
            );
        }

        // ── Fin de año ──
        $propuestas[] = $this->armar(
            'fin-de-ano-' . $anio,
            'Fin de año ' . $anio,
            $anio . '-12-20',
            ($anio + 1) . '-01-15',
            self::SUGERENCIAS['fin_de_ano'],
            12,
            '#c1440e',
            'Del 20 de diciembre al 15 de enero: Navidad, Año Nuevo y Reyes seguidos.',
            $existentes
        );

        // ── Vacaciones de mitad de año ──
        $propuestas[] = $this->armar(
            'mitad-de-ano-' . $anio,
            'Vacaciones de mitad de año ' . $anio,
            $anio . '-06-15',
            $anio . '-07-15',
            self::SUGERENCIAS['mitad_de_ano'],
            8,
            '#2e7d6b',
            'Vacaciones escolares de junio y julio.',
            $existentes
        );

        // ── Temporada baja ──
        $propuestas[] = $this->armar(
            'baja-' . $anio,
            'Temporada baja ' . $anio,
            $anio . '-01-16',
            $anio . '-03-15',
            self::SUGERENCIAS['baja'],
            1,
            '#5b7c99',
            'Entre Reyes y Semana Santa cae la demanda. Bajar el precio ayuda a llenar; '
                . 'los puentes de este tramo mandan sobre esta temporada porque tienen más prioridad.',
            $existentes
        );

        // Se ordenan por fecha para revisarlos de corrido
        usort($propuestas, static fn ($a, $b) => $a['desde'] <=> $b['desde']);

        return $propuestas;
    }

    /**
     * Aplica las propuestas elegidas. Devuelve [creadas, actualizadas].
     *
     * @param list<string> $claves
     */
    public function aplicar(int $anio, array $claves, array $ajustes = []): array
    {
        $creadas      = 0;
        $actualizadas = 0;

        foreach ($this->propuestas($anio) as $p) {
            if (! in_array($p['clave'], $claves, true)) {
                continue;
            }

            $ajuste = array_key_exists($p['clave'], $ajustes) && $ajustes[$p['clave']] !== ''
                ? (float) $ajustes[$p['clave']]
                : $p['ajuste'];

            $datos = [
                'nombre'      => $p['nombre'],
                'desde'       => $p['desde'],
                'hasta'       => $p['hasta'],
                'tipo_ajuste' => 'porcentaje',
                'ajuste'      => $ajuste,
                'prioridad'   => $p['prioridad'],
                'color'       => $p['color'],
                'activa'      => 1,
                'origen'      => 'agente',
                'clave'       => $p['clave'],
            ];

            $existente = $this->temporadas->where('clave', $p['clave'])->first();

            if ($existente === null) {
                $this->temporadas->insert($datos);
                $creadas++;
            } else {
                // Solo se refrescan las que creó el propio agente: si Javier la
                // pasó a manual, se respeta lo que él haya puesto.
                if ($existente['origen'] === 'agente') {
                    $this->temporadas->update($existente['id'], $datos);
                    $actualizadas++;
                }
            }
        }

        return [$creadas, $actualizadas];
    }

    // ─────────────────────────────────────────────────────────────

    private function armar(
        string $clave,
        string $nombre,
        string $desde,
        string $hasta,
        float $ajuste,
        int $prioridad,
        string $color,
        string $motivo,
        array $existentes
    ): array {
        $historico = $this->ocupacionHistorica($desde, $hasta);

        // Con histórico, la sugerencia se afina: si el año pasado esas fechas
        // se llenaron, se puede pedir más; si quedaron vacías, conviene menos.
        $sugerido = $ajuste;
        $nota     = null;

        if ($historico !== null) {
            if ($historico >= 80) {
                $sugerido = round($ajuste + 10);
                $nota     = 'El año pasado estas fechas estuvieron al ' . $historico . ' %: se puede pedir más.';
            } elseif ($historico >= 50) {
                $nota = 'El año pasado estas fechas estuvieron al ' . $historico . ' %.';
            } else {
                $sugerido = round($ajuste / 2);
                $nota     = 'El año pasado estas fechas solo llegaron al ' . $historico . ' %: mejor no cargar tanto.';
            }
        }

        $noches = (int) ((strtotime($hasta) - strtotime($desde)) / 86400) + 1;

        return [
            'clave'      => $clave,
            'nombre'     => $nombre,
            'desde'      => $desde,
            'hasta'      => $hasta,
            'noches'     => $noches,
            'ajuste'     => $sugerido,
            'prioridad'  => $prioridad,
            'color'      => $color,
            'motivo'     => $motivo,
            'nota'       => $nota,
            'historico'  => $historico,
            'existe'     => in_array($clave, $existentes, true),
            // Crear una temporada que ya terminó no cambia nada: se ofrece
            // pero sin marcar, para que no ensucie el año en curso.
            'pasada'     => $hasta < date('Y-m-d'),
        ];
    }

    /**
     * Ocupación media de esas mismas fechas el año anterior, o null si no hay datos.
     *
     * Aquí sí cuentan las estancias ya terminadas (checkout): son justamente
     * las que dicen cómo se vendió de verdad ese puente.
     */
    private function ocupacionHistorica(string $desde, string $hasta): ?float
    {
        $desdeAnterior = date('Y-m-d', strtotime($desde . ' -1 year'));
        $hastaAnterior = date('Y-m-d', strtotime($hasta . ' -1 year'));

        $db       = db_connect();
        $unidades = $db->table('unidades')->where('estado !=', 'bloqueada')->countAllResults();

        if ($unidades === 0) {
            return null;
        }

        $reservas = $db->table('reservas')
            ->select('fecha_entrada, fecha_salida')
            ->whereIn('estado', ['confirmada', 'checkin', 'checkout'])
            ->where('fecha_entrada <=', $hastaAnterior)
            ->where('fecha_salida >', $desdeAnterior)
            ->get()
            ->getResultArray();

        // Sin reservas en ese tramo no hay nada que aprender: mejor callar que inventar
        if ($reservas === []) {
            return null;
        }

        $suma  = 0.0;
        $dias  = 0;
        $fecha = $desdeAnterior;

        while ($fecha <= $hastaAnterior) {
            $ocupadas = 0;
            foreach ($reservas as $r) {
                if ($r['fecha_entrada'] <= $fecha && $r['fecha_salida'] > $fecha) {
                    $ocupadas++;
                }
            }
            $suma += min(100, $ocupadas / $unidades * 100);
            $dias++;
            $fecha = date('Y-m-d', strtotime($fecha . ' +1 day'));
        }

        return $dias > 0 ? round($suma / $dias, 1) : null;
    }

    private function clavesExistentes(): array
    {
        return array_column(
            $this->temporadas->select('clave')->where('clave IS NOT NULL')->findAll(),
            'clave'
        );
    }

    /** «Independencia de Cartagena» → «Cartagena», para que el nombre no sea kilométrico. */
    private function acortar(string $nombre): string
    {
        $corto = preg_replace('/^(Independencia de|Día de la|Día del|Batalla de)\s+/u', '', $nombre);

        return $corto === '' ? $nombre : $corto;
    }
}
