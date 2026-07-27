<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ActivoModel;

/**
 * Los números que sirven para decidir algo.
 *
 * Un informe de mantenimiento que solo cuenta órdenes no vale nada: siempre
 * hay órdenes. Los tres que importan son:
 *
 * 1. **Qué se rompe siempre.** Un equipo con seis averías en un año no tiene
 *    seis problemas: tiene uno que nadie ha resuelto.
 * 2. **Cuánto cuesta mantenerlo vivo** frente a lo que costó comprarlo. Es la
 *    única forma honesta de contestar «¿lo arreglo otra vez o lo cambio?».
 * 3. **De dónde salen las averías.** Si las encuentran los huéspedes antes que
 *    nosotros, el preventivo no está funcionando, por muchas órdenes que haya.
 */
class InformesMantenimiento
{
    /**
     * Equipos ordenados por lo que están dando de guerra.
     *
     * @return list<array<string, mixed>>
     */
    public function porActivo(string $desde, string $hasta): array
    {
        $filas = db_connect()->query(
            "SELECT a.id, a.codigo, a.nombre, a.categoria, a.estado, a.critico,
                    a.valor_compra, a.fecha_compra, a.vida_util_meses,
                    COUNT(m.id)                                            AS averias,
                    COALESCE(SUM(m.costo_materiales + m.costo_mano_obra),0) AS costo,
                    COALESCE(SUM(m.minutos),0)                             AS minutos,
                    SUM(CASE WHEN m.bloqueo_unidad = 1 THEN 1 ELSE 0 END)  AS bloqueos,
                    MAX(m.created_at)                                      AS ultima
             FROM activos a
             JOIN mantenimientos m ON m.activo_id = a.id
                  AND m.tipo = 'correctiva'
                  AND m.estado != 'anulada'
                  AND DATE(m.created_at) BETWEEN ? AND ?
             GROUP BY a.id
             ORDER BY averias DESC, costo DESC",
            [$desde, $hasta]
        )->getResultArray();

        foreach ($filas as &$f) {
            $f['averias']  = (int) $f['averias'];
            $f['costo']    = (float) $f['costo'];
            $f['bloqueos'] = (int) $f['bloqueos'];
            $f['veredicto'] = $this->veredicto($f);
        }

        return $filas;
    }

    /**
     * ¿Reparar otra vez o cambiarlo?
     *
     * Sin valor de compra no se opina: inventar un umbral sobre un dato que no
     * existe es peor que callarse. Con él, la regla es la de siempre en
     * mantenimiento — cuando lo gastado pasa de la mitad de lo que costó, cada
     * reparación siguiente es dinero tirado.
     *
     * @return array{nivel: string, texto: string}
     */
    private function veredicto(array $f): array
    {
        $valor = $f['valor_compra'] !== null ? (float) $f['valor_compra'] : null;

        if ($f['averias'] >= 4) {
            return [
                'nivel' => 'danger',
                'texto' => 'Se rompe demasiado. ' . $f['averias'] . ' averías: probablemente sea siempre la misma causa.',
            ];
        }

        if ($valor === null || $valor <= 0) {
            return ['nivel' => 'muted', 'texto' => 'Sin el valor de compra no se puede comparar.'];
        }

        $proporcion = $f['costo'] / $valor;

        if ($proporcion >= 0.5) {
            return [
                'nivel' => 'danger',
                'texto' => 'Llevas gastado el ' . round($proporcion * 100) . ' % de lo que costó. Mira cuánto vale uno nuevo.',
            ];
        }

        if ($proporcion >= 0.25) {
            return [
                'nivel' => 'warning',
                'texto' => 'El ' . round($proporcion * 100) . ' % de lo que costó. Vigílalo.',
            ];
        }

        return ['nivel' => 'success', 'texto' => 'Sale a cuenta seguir arreglándolo.'];
    }

    /**
     * Cuánto tiempo hubo cabañas sin poder venderse por una avería.
     *
     * Es el número que traduce el mantenimiento a dinero: una cabaña bloqueada
     * tres días en temporada son tres noches que no se vendieron.
     *
     * @return array{horas: float, ordenes: int, detalle: list<array<string,mixed>>}
     */
    public function fueraDeServicio(string $desde, string $hasta): array
    {
        $filas = db_connect()->query(
            "SELECT m.id, m.titulo, m.created_at, m.resuelta_en, m.estado,
                    u.nombre AS unidad_nombre
             FROM mantenimientos m
             LEFT JOIN unidades u ON u.id = m.unidad_id
             WHERE m.bloqueo_unidad = 1
               AND m.estado != 'anulada'
               AND DATE(m.created_at) BETWEEN ? AND ?
             ORDER BY m.created_at DESC",
            [$desde, $hasta]
        )->getResultArray();

        $horas = 0.0;

        foreach ($filas as &$f) {
            // Lo que sigue abierto cuenta hasta ahora: si no, una cabaña que
            // lleva un mes bloqueada aportaría cero al informe.
            $fin        = $f['resuelta_en'] ?? date('Y-m-d H:i:s');
            $f['horas'] = round((strtotime($fin) - strtotime($f['created_at'])) / 3600, 1);
            $f['abierta'] = $f['resuelta_en'] === null;
            $horas += $f['horas'];
        }

        return ['horas' => round($horas, 1), 'ordenes' => count($filas), 'detalle' => $filas];
    }

    /**
     * De dónde salen las averías.
     *
     * Si el huésped es quien más reporta, el preventivo no está funcionando.
     *
     * @return array<string, int>
     */
    public function porOrigen(string $desde, string $hasta): array
    {
        $filas = db_connect()->query(
            "SELECT origen, COUNT(*) AS cuantas
             FROM mantenimientos
             WHERE estado != 'anulada' AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY origen",
            [$desde, $hasta]
        )->getResultArray();

        $conteo = [];

        foreach ($filas as $f) {
            $conteo[$f['origen']] = (int) $f['cuantas'];
        }

        return $conteo;
    }

    /**
     * Lo correctivo frente a lo preventivo.
     *
     * En un sitio donde el preventivo funciona, lo programado pesa más que lo
     * roto. Al revés significa que se está apagando fuegos.
     *
     * @return array{correctiva: int, preventiva: int, a_tiempo: int, tarde: int}
     */
    public function resumen(string $desde, string $hasta): array
    {
        $fila = db_connect()->query(
            "SELECT
                SUM(CASE WHEN tipo = 'correctiva' THEN 1 ELSE 0 END) AS correctiva,
                SUM(CASE WHEN tipo = 'preventiva' THEN 1 ELSE 0 END) AS preventiva,
                SUM(CASE WHEN resuelta_en IS NOT NULL AND vence_en IS NOT NULL
                          AND resuelta_en <= vence_en THEN 1 ELSE 0 END) AS a_tiempo,
                SUM(CASE WHEN vence_en IS NOT NULL
                          AND COALESCE(resuelta_en, NOW()) > vence_en THEN 1 ELSE 0 END) AS tarde
             FROM mantenimientos
             WHERE estado != 'anulada' AND DATE(created_at) BETWEEN ? AND ?",
            [$desde, $hasta]
        )->getRowArray();

        return [
            'correctiva' => (int) ($fila['correctiva'] ?? 0),
            'preventiva' => (int) ($fila['preventiva'] ?? 0),
            'a_tiempo'   => (int) ($fila['a_tiempo'] ?? 0),
            'tarde'      => (int) ($fila['tarde'] ?? 0),
        ];
    }

    /** Equipos que se acercan al final de su vida útil estimada. */
    public function agotandoVida(): array
    {
        $activos = (new ActivoModel())
            ->where('estado !=', 'baja')
            ->where('vida_util_meses IS NOT NULL')
            ->where('fecha_compra IS NOT NULL')
            ->findAll();

        $lista = [];

        foreach ($activos as $a) {
            $fin   = strtotime($a['fecha_compra'] . ' +' . (int) $a['vida_util_meses'] . ' months');
            $meses = (int) round(($fin - time()) / (86400 * 30.4));

            // Los que ya se pasaron y los que están a menos de seis meses
            if ($meses <= 6) {
                $a['meses_restantes'] = $meses;
                $lista[]              = $a;
            }
        }

        usort($lista, static fn (array $x, array $y): int => $x['meses_restantes'] <=> $y['meses_restantes']);

        return $lista;
    }
}
