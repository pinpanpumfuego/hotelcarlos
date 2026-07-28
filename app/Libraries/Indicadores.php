<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\UnidadModel;

/**
 * Los indicadores de un hotel, calculados como se calculan en un hotel.
 *
 * **La decisión que lo cambia todo: el alojamiento se prorratea por noche.**
 * Antes se sumaba el cargo del folio por la fecha en que se creó, y el cargo se
 * crea entero al principio de la estancia. Con eso, una reserva de diez noches
 * cobrada el 1 de marzo metía en marzo ingresos que son de abril, y el ADR y el
 * RevPAR de cualquier mes salían mal — mal hacia arriba en el mes que empieza y
 * hacia abajo en el siguiente. Cada noche lleva su parte y solo su parte.
 *
 * Las definiciones que se usan, para que no haya dudas al leerlas:
 *
 * - **Noches disponibles** = cabañas vendibles × noches. Las bloqueadas por
 *   avería NO cuentan como disponibles: no se podían vender.
 * - **Noches vendidas** = noches ocupadas por reservas que se cumplieron o van
 *   camino de cumplirse. Las canceladas y los no-show no son ventas.
 * - **ADR** = alojamiento ÷ noches vendidas. **Solo alojamiento.** Meter el
 *   restaurante aquí es el error más común y hace que el ADR no sirva para
 *   comparar con nadie.
 * - **RevPAR** = alojamiento ÷ noches disponibles. Es ADR × ocupación.
 * - **TRevPAR** = todo el ingreso ÷ noches disponibles. Ahí sí entra el
 *   restaurante, los extras y las actividades.
 */
class Indicadores
{
    /** Lo que cuenta como venta. Ni pendiente, ni cancelada, ni no-show. */
    public const VENDIDOS = ['confirmada', 'checkin', 'checkout'];

    /**
     * El cuadro completo de un periodo.
     *
     * @return array<string, mixed>
     */
    public function periodo(string $desde, string $hasta): array
    {
        $noches = $this->porNoche($desde, $hasta);

        $vendidas   = 0;
        $alojamiento = 0.0;
        $cortesias  = 0;

        foreach ($noches as $n) {
            $vendidas   += $n['vendidas'];
            $cortesias  += $n['cortesias'];
            $alojamiento += $n['alojamiento'];
        }

        $dias        = count($noches);
        $vendibles   = $this->cabanasVendibles();
        $disponibles = $dias * $vendibles;

        // Las cortesías ocupan cabaña pero no dejan dinero. Contarlas como
        // vendidas hunde el ADR sin que nadie haya bajado un precio, así que
        // se sacan del divisor y se enseñan aparte.
        $paraAdr = max(0, $vendidas - $cortesias);

        $otros = $this->otrosIngresos($desde, $hasta);
        $total = $alojamiento + $otros['total'];

        return [
            'dias'        => $dias,
            'cabanas'     => $vendibles,
            'fuera_servicio' => $this->fueraDeServicio(),
            'disponibles' => $disponibles,
            'vendidas'    => $vendidas,
            'cortesias'   => $cortesias,

            // Todos como `float`, también cuando salen a cero. `round()`
            // devuelve float, así que devolver el entero 0 en el caso vacío
            // haría que el tipo cambiara según si hubo ventas — y quien
            // comparara con `===` obtendría respuestas distintas sin motivo.
            'ocupacion'   => $disponibles > 0 ? round($vendidas / $disponibles * 100, 1) : 0.0,
            'alojamiento' => round($alojamiento, 2),
            'adr'         => $paraAdr > 0 ? round($alojamiento / $paraAdr) : 0.0,
            'revpar'      => $disponibles > 0 ? round($alojamiento / $disponibles) : 0.0,
            'trevpar'     => $disponibles > 0 ? round($total / $disponibles) : 0.0,
            'ingreso_total' => round($total, 2),
            'otros'       => $otros,

            'estancia_media' => $this->estanciaMedia($desde, $hasta),
            'llegadas'    => $this->llegadas($desde, $hasta),
            'salidas'     => $this->salidas($desde, $hasta),
            'por_noche'   => $noches,
        ];
    }

    /**
     * Noche a noche: cuántas cabañas ocupadas y cuánto alojamiento toca.
     *
     * Es el corazón del módulo. Todo lo demás son sumas de esta tabla.
     *
     * @return array<string, array{vendidas: int, cortesias: int, alojamiento: float}>
     */
    public function porNoche(string $desde, string $hasta): array
    {
        $noches = [];

        for ($d = new \DateTime($desde); $d->format('Y-m-d') <= $hasta; $d->modify('+1 day')) {
            $noches[$d->format('Y-m-d')] = ['vendidas' => 0, 'cortesias' => 0, 'alojamiento' => 0.0];
        }

        if ($noches === []) {
            return [];
        }

        $finExclusivo = (new \DateTime($hasta))->modify('+1 day')->format('Y-m-d');

        $reservas = db_connect()->query(
            "SELECT r.id, r.fecha_entrada, r.fecha_salida, r.total, r.canal,
                    COALESCE((SELECT SUM(f.valor) FROM folio_movimientos f
                              WHERE f.reserva_id = r.id AND f.tipo = 'descuento'), 0) AS descuento
             FROM reservas r
             WHERE r.estado IN ('" . implode("','", self::VENDIDOS) . "')
               AND r.fecha_entrada < ? AND r.fecha_salida > ?",
            [$finExclusivo, $desde]
        )->getResultArray();

        foreach ($reservas as $r) {
            // Las noches de TODA la estancia, no solo las del periodo: el
            // precio por noche es el total entre las noches que se reservaron.
            // Dividirlo entre las que caen dentro del mes daría una tarifa
            // inventada, más alta cuanto más corto sea el trozo.
            $nochesTotales = max(1, (int) ((strtotime($r['fecha_salida']) - strtotime($r['fecha_entrada'])) / 86400));

            $neto     = max(0, (float) $r['total'] - (float) $r['descuento']);
            $porNoche = $neto / $nochesTotales;

            $ini = max($r['fecha_entrada'], $desde);
            $fin = min($r['fecha_salida'], $finExclusivo);

            for ($d = new \DateTime($ini); $d->format('Y-m-d') < $fin; $d->modify('+1 day')) {
                $clave = $d->format('Y-m-d');

                if (! isset($noches[$clave])) {
                    continue;
                }

                $noches[$clave]['vendidas']++;
                $noches[$clave]['alojamiento'] += $porNoche;

                // Una reserva a cero es una cortesía o un uso de la casa
                if ($neto <= 0) {
                    $noches[$clave]['cortesias']++;
                }
            }
        }

        return $noches;
    }

    // ── Inventario ──────────────────────────────────────────────────────

    /**
     * Cabañas que se pueden vender.
     *
     * Las bloqueadas no cuentan como disponibles: no se podían vender, y
     * meterlas en el divisor haría que la ocupación bajara justo cuando el
     * hotel está haciendo lo correcto —cerrar una cabaña con una avería— lo
     * que empuja a no cerrarla.
     */
    public function cabanasVendibles(): int
    {
        return (new UnidadModel())->where('estado !=', 'bloqueada')->countAllResults();
    }

    public function fueraDeServicio(): int
    {
        return (new UnidadModel())->where('estado', 'bloqueada')->countAllResults();
    }

    // ── Ingresos que no son alojamiento ─────────────────────────────────

    /**
     * Restaurante, extras y actividades del periodo.
     *
     * Estos sí van por la fecha del cargo: una cena se consume el día que se
     * pide, no repartida por la estancia.
     *
     * @return array{restaurante: float, extras: float, descuentos: float, total: float}
     */
    public function otrosIngresos(string $desde, string $hasta): array
    {
        $db = db_connect();

        // Del restaurante: lo cobrado en comandas cerradas, que es lo que de
        // verdad se vendió allí.
        $restaurante = (float) ($db->query(
            "SELECT COALESCE(SUM(total), 0) AS t FROM comandas
             WHERE estado = 'cobrada' AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray()['t'] ?? 0);

        // Del folio: todo lo que no es alojamiento. El alojamiento ya se contó
        // prorrateado y sumarlo aquí lo duplicaría.
        $extras = (float) ($db->query(
            "SELECT COALESCE(SUM(valor), 0) AS t FROM folio_movimientos
             WHERE tipo = 'cargo' AND concepto NOT LIKE 'Alojamiento%'
               AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray()['t'] ?? 0);

        $descuentos = (float) ($db->query(
            "SELECT COALESCE(SUM(valor), 0) AS t FROM folio_movimientos
             WHERE tipo = 'descuento' AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray()['t'] ?? 0);

        return [
            'restaurante' => round($restaurante, 2),
            'extras'      => round($extras, 2),
            'descuentos'  => round($descuentos, 2),
            'total'       => round($restaurante + $extras, 2),
        ];
    }

    // ── Movimiento ──────────────────────────────────────────────────────

    public function llegadas(string $desde, string $hasta): int
    {
        return db_connect()->table('reservas')
            ->whereIn('estado', self::VENDIDOS)
            ->where('fecha_entrada >=', $desde)
            ->where('fecha_entrada <=', $hasta)
            ->countAllResults();
    }

    public function salidas(string $desde, string $hasta): int
    {
        return db_connect()->table('reservas')
            ->whereIn('estado', self::VENDIDOS)
            ->where('fecha_salida >=', $desde)
            ->where('fecha_salida <=', $hasta)
            ->countAllResults();
    }

    /**
     * Cuántas noches se queda la gente.
     *
     * Se mide sobre las estancias que EMPIEZAN en el periodo, con su duración
     * completa. Medirla con las noches recortadas al periodo daría siempre una
     * estancia más corta cuanto más corto fuera el informe.
     */
    public function estanciaMedia(string $desde, string $hasta): float
    {
        $fila = db_connect()->query(
            "SELECT AVG(DATEDIFF(fecha_salida, fecha_entrada)) AS media
             FROM reservas
             WHERE estado IN ('" . implode("','", self::VENDIDOS) . "')
               AND fecha_entrada BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        return $fila['media'] !== null ? round((float) $fila['media'], 1) : 0.0;
    }

    // ── Canales ─────────────────────────────────────────────────────────

    /**
     * Qué produce cada canal y qué cuesta.
     *
     * La rentabilidad por canal es la cifra que decide dónde invertir. Booking
     * puede traer más reservas y dejar menos: se ve aquí y en ningún otro sitio.
     *
     * @return list<array<string, mixed>>
     */
    public function porCanal(string $desde, string $hasta): array
    {
        $filas = db_connect()->query(
            "SELECT COALESCE(NULLIF(r.canal, ''), 'directo') AS canal,
                    COUNT(*)                                              AS reservas,
                    SUM(CASE WHEN r.estado IN ('confirmada','checkin','checkout') THEN 1 ELSE 0 END) AS vendidas,
                    SUM(CASE WHEN r.estado = 'cancelada' THEN 1 ELSE 0 END) AS canceladas,
                    SUM(CASE WHEN r.estado = 'no_show'   THEN 1 ELSE 0 END) AS no_shows,
                    SUM(CASE WHEN r.estado IN ('confirmada','checkin','checkout')
                             THEN r.total ELSE 0 END)                      AS produccion,
                    SUM(CASE WHEN r.estado IN ('confirmada','checkin','checkout')
                             THEN COALESCE(r.comision, 0) ELSE 0 END)      AS comision,
                    SUM(CASE WHEN r.estado IN ('confirmada','checkin','checkout')
                             THEN DATEDIFF(r.fecha_salida, r.fecha_entrada) ELSE 0 END) AS noches
             FROM reservas r
             WHERE r.fecha_entrada BETWEEN ? AND ?
             GROUP BY canal
             ORDER BY produccion DESC",
            [$desde, $hasta]
        )->getResultArray();

        foreach ($filas as &$f) {
            $f['reservas']   = (int) $f['reservas'];
            $f['vendidas']   = (int) $f['vendidas'];
            $f['canceladas'] = (int) $f['canceladas'];
            $f['no_shows']   = (int) $f['no_shows'];
            $f['noches']     = (int) $f['noches'];
            $f['produccion'] = round((float) $f['produccion'], 2);
            $f['comision']   = round((float) $f['comision'], 2);

            // Lo que de verdad queda en casa
            $f['neto'] = round($f['produccion'] - $f['comision'], 2);
            $f['adr']  = $f['noches'] > 0 ? round($f['produccion'] / $f['noches']) : 0;
            $f['adr_neto'] = $f['noches'] > 0 ? round($f['neto'] / $f['noches']) : 0;
            $f['comision_pct'] = $f['produccion'] > 0 ? round($f['comision'] / $f['produccion'] * 100, 1) : 0.0;
            $f['cancelacion_pct'] = $f['reservas'] > 0
                ? round(($f['canceladas'] + $f['no_shows']) / $f['reservas'] * 100, 1)
                : 0.0;
        }

        return $filas;
    }

    // ── Reservas ────────────────────────────────────────────────────────

    /**
     * Cómo se comportan las reservas: anticipación, cancelación y pickup.
     *
     * @return array<string, mixed>
     */
    public function comportamiento(string $desde, string $hasta): array
    {
        $db = db_connect();

        // Anticipación: cuántos días antes se reserva. Es lo que dice si tiene
        // sentido abrir ventas con nueve meses o si aquí nadie reserva hasta la
        // semana de antes.
        $anticipacion = $db->query(
            "SELECT AVG(DATEDIFF(fecha_entrada, DATE(created_at))) AS media,
                    MIN(DATEDIFF(fecha_entrada, DATE(created_at))) AS minimo,
                    MAX(DATEDIFF(fecha_entrada, DATE(created_at))) AS maximo
             FROM reservas
             WHERE estado IN ('" . implode("','", self::VENDIDOS) . "')
               AND fecha_entrada BETWEEN ? AND ?
               AND DATE(created_at) <= fecha_entrada",
            [$desde, $hasta]
        )->getRowArray();

        // Creadas y canceladas EN el periodo, que es distinto de las que
        // entran en el periodo: mide la actividad comercial, no la ocupación.
        $creadas = (int) ($db->query(
            'SELECT COUNT(*) AS n FROM reservas WHERE DATE(created_at) BETWEEN ? AND ?',
            [$desde, $hasta]
        )->getRowArray()['n'] ?? 0);

        $canceladas = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM reservas
             WHERE estado = 'cancelada'
               AND DATE(COALESCE(cancelada_en, updated_at)) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray()['n'] ?? 0);

        // Las que caducaron sin confirmarse: son el abandono del motor de
        // reservas, y se distinguen de las que alguien canceló a propósito.
        // Solo constan desde que se empezó a guardar el origen: lo anterior no
        // se puede reconstruir, y contarlo como abandono sería inventárselo.
        $abandonadas = (int) ($db->query(
            "SELECT COUNT(*) AS n FROM reservas
             WHERE estado = 'cancelada' AND cancelada_origen = 'expiracion'
               AND DATE(COALESCE(cancelada_en, updated_at)) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray()['n'] ?? 0);

        $porOrigen = $db->query(
            "SELECT COALESCE(NULLIF(h.origen, ''), 'sin_saber') AS origen, COUNT(*) AS n
             FROM reservas r JOIN huespedes h ON h.id = r.huesped_id
             WHERE r.estado IN ('" . implode("','", self::VENDIDOS) . "')
               AND r.fecha_entrada BETWEEN ? AND ?
             GROUP BY origen ORDER BY n DESC",
            [$desde, $hasta]
        )->getResultArray();

        return [
            'anticipacion_media' => $anticipacion['media'] !== null ? round((float) $anticipacion['media'], 1) : 0.0,
            'anticipacion_min'   => (int) ($anticipacion['minimo'] ?? 0),
            'anticipacion_max'   => (int) ($anticipacion['maximo'] ?? 0),
            'creadas'            => $creadas,
            'canceladas'         => $canceladas,
            'abandonadas'        => $abandonadas,
            'cancelacion_pct'    => $creadas > 0 ? round($canceladas / $creadas * 100, 1) : 0.0,
            'por_origen'         => $porOrigen,
        ];
    }

    /**
     * Pickup: cuántas noches se han vendido ya para los próximos meses.
     *
     * Es lo único que mira hacia adelante. Todo lo demás cuenta lo que ya pasó,
     * y con eso no se decide un precio.
     *
     * @return list<array{mes: string, noches: int, produccion: float, ocupacion: float}>
     */
    public function pickup(int $meses = 6): array
    {
        $lista     = [];
        $vendibles = $this->cabanasVendibles();

        for ($i = 0; $i < $meses; $i++) {
            $inicio = date('Y-m-01', strtotime('+' . $i . ' months'));
            $fin    = date('Y-m-t', strtotime($inicio));

            $noches      = $this->porNoche($inicio, $fin);
            $vendidas    = 0;
            $produccion  = 0.0;

            foreach ($noches as $n) {
                $vendidas   += $n['vendidas'];
                $produccion += $n['alojamiento'];
            }

            $disponibles = count($noches) * $vendibles;

            $lista[] = [
                'mes'        => $inicio,
                'noches'     => $vendidas,
                'produccion' => round($produccion, 2),
                'ocupacion'  => $disponibles > 0 ? round($vendidas / $disponibles * 100, 1) : 0.0,
            ];
        }

        return $lista;
    }

    // ── Comparar con el año pasado ──────────────────────────────────────

    /**
     * El mismo periodo del año anterior.
     *
     * Un número solo no dice nada: un 62 % de ocupación puede ser excelente o
     * un desastre según lo que se hiciera el año pasado por estas fechas.
     *
     * @return array<string, mixed>
     */
    public function mismoPeriodoAnterior(string $desde, string $hasta): array
    {
        return $this->periodo(
            date('Y-m-d', strtotime($desde . ' -1 year')),
            date('Y-m-d', strtotime($hasta . ' -1 year'))
        );
    }

    /** Cuánto ha cambiado algo, en porcentaje. */
    public function variacion(float $ahora, float $antes): ?float
    {
        // Sin base con la que comparar, no se inventa un porcentaje: crecer
        // desde cero no es «infinito por ciento», es que antes no había nada.
        if ($antes <= 0) {
            return null;
        }

        return round(($ahora - $antes) / $antes * 100, 1);
    }
}
