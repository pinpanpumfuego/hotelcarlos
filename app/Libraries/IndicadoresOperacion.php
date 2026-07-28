<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Los indicadores de lo que pasa dentro: huéspedes, restaurante, operación y
 * dinero en caja.
 *
 * Van aparte de `Indicadores` porque contestan a otra pregunta. Aquel dice si
 * el hotel se está vendiendo bien; este dice si se está funcionando bien, que
 * no es lo mismo y a menudo va al revés: un mes puede tener récord de
 * ocupación y ser el peor mes de la cocina.
 */
class IndicadoresOperacion
{
    // ── Huéspedes ───────────────────────────────────────────────────────

    /**
     * Quiénes vinieron y de dónde.
     *
     * **«Nuevo» se mide contra el historial, no contra el periodo.** Alguien
     * que vino por primera vez en enero y vuelve en marzo es recurrente en
     * marzo, aunque en el informe de marzo no aparezca su primera visita.
     *
     * @return array<string, mixed>
     */
    public function huespedes(string $desde, string $hasta): array
    {
        $db = db_connect();

        $filas = $db->query(
            "SELECT h.id,
                    (SELECT COUNT(*) FROM reservas r2
                     WHERE r2.huesped_id = h.id
                       AND r2.estado IN ('checkin','checkout')
                       AND r2.fecha_entrada < ?)                       AS antes,
                    (SELECT COALESCE(SUM(f.valor), 0) FROM folio_movimientos f
                     JOIN reservas r3 ON r3.id = f.reserva_id
                     WHERE r3.huesped_id = h.id AND f.tipo = 'cargo'
                       AND r3.fecha_entrada BETWEEN ? AND ?)           AS gasto
             FROM huespedes h
             WHERE h.estado = 'activo'
               AND EXISTS (SELECT 1 FROM reservas r
                           WHERE r.huesped_id = h.id
                             AND r.estado IN ('checkin','checkout')
                             AND r.fecha_entrada BETWEEN ? AND ?)",
            [$desde, $desde, $hasta, $desde, $hasta]
        )->getResultArray();

        $nuevos      = 0;
        $recurrentes = 0;
        $gasto       = 0.0;

        foreach ($filas as $f) {
            (int) $f['antes'] === 0 ? $nuevos++ : $recurrentes++;
            $gasto += (float) $f['gasto'];
        }

        $total = count($filas);

        $procedencia = $db->query(
            "SELECT COALESCE(NULLIF(h.pais, ''), h.nacionalidad, 'Sin apuntar') AS lugar, COUNT(DISTINCT h.id) AS n
             FROM huespedes h
             JOIN reservas r ON r.huesped_id = h.id
             WHERE r.estado IN ('checkin','checkout') AND r.fecha_entrada BETWEEN ? AND ?
             GROUP BY lugar ORDER BY n DESC LIMIT 12",
            [$desde, $hasta]
        )->getResultArray();

        // Satisfacción: solo de las encuestas contestadas en el periodo.
        $encuestas = $db->query(
            'SELECT COUNT(*) AS cuantas, AVG(general) AS media,
                    SUM(CASE WHEN general <= 3 THEN 1 ELSE 0 END) AS bajas
             FROM encuestas WHERE DATE(created_at) BETWEEN ? AND ?',
            [$desde, $hasta]
        )->getRowArray();

        // A cuánta gente se le puede escribir. Es el techo real de cualquier
        // campaña, y casi siempre es mucho menor de lo que la gente cree.
        $conPermiso = count((new \App\Models\ConsentimientoModel())->huespedesQuePermiten('marketing', 'email'));
        $activos    = $db->table('huespedes')->where('estado', 'activo')->countAllResults();

        return [
            'total'        => $total,
            'nuevos'       => $nuevos,
            'recurrentes'  => $recurrentes,
            'repeticion'   => $total > 0 ? round($recurrentes / $total * 100, 1) : 0.0,
            'gasto_medio'  => $total > 0 ? round($gasto / $total) : 0.0,
            'procedencia'  => $procedencia,
            'encuestas'    => (int) ($encuestas['cuantas'] ?? 0),
            'satisfaccion' => $encuestas['media'] !== null ? round((float) $encuestas['media'], 2) : null,
            'notas_bajas'  => (int) ($encuestas['bajas'] ?? 0),
            'base_activa'  => $activos,
            'con_permiso'  => $conPermiso,
            'permiso_pct'  => $activos > 0 ? round($conPermiso / $activos * 100, 1) : 0.0,
        ];
    }

    // ── Restaurante ─────────────────────────────────────────────────────

    /**
     * Venta, ticket, tiempos y lo que se cae por el camino.
     *
     * @return array<string, mixed>
     */
    public function restaurante(string $desde, string $hasta): array
    {
        $db = db_connect();

        $venta = $db->query(
            "SELECT COUNT(*) AS comandas, COALESCE(SUM(total), 0) AS total,
                    COALESCE(SUM(comensales), 0) AS comensales,
                    COALESCE(SUM(descuento), 0) AS descuentos
             FROM comandas
             WHERE estado = 'cobrada' AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        $anuladas = $db->query(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS valor
             FROM comandas WHERE estado = 'anulada' AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        // Líneas que no se cobraron: cortesías y devoluciones. Dos cosas
        // distintas —una se regala y la otra se rechaza— y conviene no
        // sumarlas: el remedio no es el mismo.
        $lineas = $db->query(
            "SELECT cl.estado_linea, COUNT(*) AS n,
                    COALESCE(SUM(cl.precio_unitario * cl.cantidad), 0) AS valor
             FROM comanda_lineas cl
             JOIN comandas c ON c.id = cl.comanda_id
             WHERE cl.estado_linea IN ('cortesia','devuelta')
               AND DATE(c.created_at) BETWEEN ? AND ?
             GROUP BY cl.estado_linea",
            [$desde, $hasta]
        )->getResultArray();

        $incidencias = ['cortesia' => ['n' => 0, 'valor' => 0.0], 'devuelta' => ['n' => 0, 'valor' => 0.0]];

        foreach ($lineas as $l) {
            $incidencias[$l['estado_linea']] = ['n' => (int) $l['n'], 'valor' => (float) $l['valor']];
        }

        // Tiempos de cocina. Solo de las líneas que pasaron por los dos
        // estados: las que nadie marcó no dan un tiempo, dan un hueco.
        $tiempos = $db->query(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, cl.created_at, cl.recibido_en)) AS espera,
                    AVG(TIMESTAMPDIFF(MINUTE, cl.recibido_en, cl.listo_en))   AS preparacion,
                    COUNT(*) AS medidas
             FROM comanda_lineas cl
             JOIN comandas c ON c.id = cl.comanda_id
             WHERE cl.recibido_en IS NOT NULL AND cl.listo_en IS NOT NULL
               AND DATE(c.created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        $comandas = (int) ($venta['comandas'] ?? 0);
        $total    = (float) ($venta['total'] ?? 0);

        return [
            'comandas'    => $comandas,
            'venta'       => round($total, 2),
            'ticket'      => $comandas > 0 ? round($total / $comandas) : 0.0,
            'comensales'  => (int) ($venta['comensales'] ?? 0),
            'por_persona' => (int) ($venta['comensales'] ?? 0) > 0
                ? round($total / (int) $venta['comensales'])
                : 0.0,
            'descuentos'  => round((float) ($venta['descuentos'] ?? 0), 2),
            'anuladas'    => (int) ($anuladas['n'] ?? 0),
            'anuladas_valor' => round((float) ($anuladas['valor'] ?? 0), 2),
            'cortesias'   => $incidencias['cortesia'],
            'devueltas'   => $incidencias['devuelta'],
            'espera'      => $tiempos['espera'] !== null ? round((float) $tiempos['espera'], 1) : null,
            'preparacion' => $tiempos['preparacion'] !== null ? round((float) $tiempos['preparacion'], 1) : null,
            'lineas_medidas' => (int) ($tiempos['medidas'] ?? 0),
            'populares'   => (new CostosCocina())->popularidad($desde, $hasta),
        ];
    }

    /**
     * Lo que la cocina debería haber gastado contra lo que gastó.
     *
     * La diferencia son mermas, raciones generosas o cosas que salieron por la
     * puerta. Ninguna de las tres se arregla mirando el total de ventas.
     *
     * @return array<string, mixed>
     */
    public function consumoCocina(string $desde, string $hasta): array
    {
        // `comparar()` ya trae su propio resumen. Volver a sumarlo aquí sería
        // una segunda cuenta de lo mismo, y dos cuentas de lo mismo acaban
        // discrepando el día que una de las dos se toque.
        $c       = (new CostosCocina())->comparar($desde, $hasta);
        $teorico = (float) $c['resumen']['teorico'];

        return [
            'lineas'         => array_slice($c['lineas'], 0, 15),
            'teorico'        => $teorico,
            'real'           => (float) $c['resumen']['real'],
            'desviacion'     => (float) $c['resumen']['desviacion'],
            'desviacion_pct' => $teorico > 0
                ? round((float) $c['resumen']['desviacion'] / $teorico * 100, 1)
                : null,
        ];
    }

    // ── Operación ───────────────────────────────────────────────────────

    /**
     * Limpieza, mantenimiento y cuánto tiempo hubo cabañas sin poder venderse.
     *
     * @return array<string, mixed>
     */
    public function operacion(string $desde, string $hasta): array
    {
        $db = db_connect();

        $limpieza = $db->query(
            "SELECT COUNT(*) AS tareas,
                    AVG(CASE WHEN inicio IS NOT NULL AND fin IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, inicio, fin) END)     AS minutos,
                    SUM(CASE WHEN estado = 'rechazada' THEN 1 ELSE 0 END)     AS rechazadas,
                    SUM(COALESCE(reprocesos, 0))                              AS reprocesos,
                    SUM(CASE WHEN estado IN ('hecha','inspeccionada') THEN 1 ELSE 0 END) AS terminadas
             FROM limpiezas
             WHERE DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        $mantenimiento = $db->query(
            "SELECT COUNT(*) AS ordenes,
                    SUM(CASE WHEN tipo = 'preventiva' THEN 1 ELSE 0 END)      AS preventivas,
                    SUM(CASE WHEN estado IN ('resuelta','verificada') THEN 1 ELSE 0 END) AS cerradas,
                    SUM(CASE WHEN vence_en IS NOT NULL
                              AND COALESCE(resuelta_en, NOW()) > vence_en THEN 1 ELSE 0 END) AS fuera_plazo,
                    AVG(minutos)                                              AS minutos,
                    COALESCE(SUM(costo_materiales + costo_mano_obra), 0)      AS costo
             FROM mantenimientos
             WHERE estado != 'anulada' AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        // Horas de cabaña sin poder venderse por una avería. Es lo que traduce
        // el mantenimiento a dinero: tres días bloqueada en temporada son tres
        // noches que no se vendieron.
        $paradas = $db->query(
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, created_at, COALESCE(resuelta_en, NOW()))), 0) AS horas,
                    COUNT(*) AS cuantas
             FROM mantenimientos
             WHERE bloqueo_unidad = 1 AND estado != 'anulada'
               AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        $ordenes = (int) ($mantenimiento['ordenes'] ?? 0);
        $tareas  = (int) ($limpieza['tareas'] ?? 0);

        return [
            'limpiezas'      => $tareas,
            'limpieza_min'   => $limpieza['minutos'] !== null ? round((float) $limpieza['minutos'], 1) : null,
            'rechazadas'     => (int) ($limpieza['rechazadas'] ?? 0),
            'reprocesos'     => (int) ($limpieza['reprocesos'] ?? 0),
            'reproceso_pct'  => $tareas > 0
                ? round(((int) $limpieza['rechazadas'] + (int) $limpieza['reprocesos']) / $tareas * 100, 1)
                : 0.0,

            'ordenes'        => $ordenes,
            'preventivas'    => (int) ($mantenimiento['preventivas'] ?? 0),
            'correctivas'    => $ordenes - (int) ($mantenimiento['preventivas'] ?? 0),
            'cerradas'       => (int) ($mantenimiento['cerradas'] ?? 0),
            'fuera_plazo'    => (int) ($mantenimiento['fuera_plazo'] ?? 0),
            'sla_pct'        => $ordenes > 0
                ? round(($ordenes - (int) $mantenimiento['fuera_plazo']) / $ordenes * 100, 1)
                : 100.0,
            'mant_min'       => $mantenimiento['minutos'] !== null ? round((float) $mantenimiento['minutos'], 1) : null,
            'mant_costo'     => round((float) ($mantenimiento['costo'] ?? 0), 2),

            'bloqueos'       => (int) ($paradas['cuantas'] ?? 0),
            'horas_paradas'  => (int) ($paradas['horas'] ?? 0),
            'dias_paradas'   => round((int) ($paradas['horas'] ?? 0) / 24, 1),
        ];
    }

    // ── Finanzas ────────────────────────────────────────────────────────

    /**
     * Caja, descuadres, cobros por medio y lo que queda por cobrar.
     *
     * @return array<string, mixed>
     */
    public function finanzas(string $desde, string $hasta): array
    {
        $db = db_connect();

        $turnos = $db->query(
            "SELECT COUNT(*) AS cerrados,
                    COALESCE(SUM(ABS(diferencia)), 0) AS descuadre_total,
                    SUM(CASE WHEN ABS(diferencia) >= 0.01 THEN 1 ELSE 0 END) AS con_descuadre,
                    COALESCE(SUM(retiros), 0) AS retiros
             FROM caja_turnos
             WHERE cierre IS NOT NULL AND DATE(cierre) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        // Cobros por medio de pago. Se mira el folio, que es donde está todo
        // lo del alojamiento; el restaurante lleva su propia tabla.
        $porMedio = $db->query(
            "SELECT metodo, COUNT(*) AS n, COALESCE(SUM(valor), 0) AS total
             FROM folio_movimientos
             WHERE tipo = 'pago' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY metodo ORDER BY total DESC",
            [$desde, $hasta]
        )->getResultArray();

        // Lo que está pendiente de cobro AHORA, no en el periodo: una deuda no
        // pertenece al mes en que nació, pertenece al presente.
        $pendiente = $db->query(
            "SELECT COUNT(*) AS reservas, COALESCE(SUM(saldo), 0) AS total FROM (
                SELECT r.id,
                       COALESCE(SUM(CASE WHEN f.tipo = 'cargo' THEN f.valor ELSE -f.valor END), 0) AS saldo
                FROM reservas r
                JOIN folio_movimientos f ON f.reserva_id = r.id
                WHERE r.estado != 'cancelada'
                GROUP BY r.id
                HAVING saldo > 0
             ) AS deudas"
        )->getRowArray();

        // Anticipos: lo cobrado de estancias que todavía no han empezado. Es
        // dinero en casa que aún no es ingreso.
        $anticipos = $db->query(
            "SELECT COALESCE(SUM(f.valor), 0) AS total
             FROM folio_movimientos f
             JOIN reservas r ON r.id = f.reserva_id
             WHERE f.tipo = 'pago' AND r.estado IN ('pendiente','confirmada')
               AND r.fecha_entrada > CURDATE()"
        )->getRowArray();

        $pagosOnline = $db->query(
            "SELECT COUNT(*) AS n, COALESCE(SUM(valor), 0) AS total
             FROM pagos_online
             WHERE estado = 'APPROVED' AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        $facturas = $db->query(
            "SELECT estado, COUNT(*) AS n, COALESCE(SUM(total), 0) AS total
             FROM facturas WHERE DATE(created_at) BETWEEN ? AND ?
             GROUP BY estado",
            [$desde, $hasta]
        )->getResultArray();

        $cerrados = (int) ($turnos['cerrados'] ?? 0);

        return [
            'turnos'         => $cerrados,
            'con_descuadre'  => (int) ($turnos['con_descuadre'] ?? 0),
            'descuadre'      => round((float) ($turnos['descuadre_total'] ?? 0), 2),
            'cuadre_pct'     => $cerrados > 0
                ? round(($cerrados - (int) $turnos['con_descuadre']) / $cerrados * 100, 1)
                : 100.0,
            'retiros'        => round((float) ($turnos['retiros'] ?? 0), 2),
            'por_medio'      => $porMedio,
            'por_cobrar'     => round((float) ($pendiente['total'] ?? 0), 2),
            'deudores'       => (int) ($pendiente['reservas'] ?? 0),
            'anticipos'      => round((float) ($anticipos['total'] ?? 0), 2),
            'online'         => (int) ($pagosOnline['n'] ?? 0),
            'online_total'   => round((float) ($pagosOnline['total'] ?? 0), 2),
            'facturas'       => $facturas,
        ];
    }
}
