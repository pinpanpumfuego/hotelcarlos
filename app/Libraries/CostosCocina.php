<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\MovimientoStockModel;

/**
 * Lo que debería haberse gastado frente a lo que se gastó de verdad.
 *
 * Es el informe que justifica todo el módulo de almacén. El teórico sale de
 * multiplicar lo vendido por sus recetas; el real, de lo que salió del almacén.
 * **La diferencia es lo que se pierde**: raciones más generosas de la cuenta,
 * cosas que se caen, cosas que se van sin apuntar.
 *
 * Un aviso sobre cómo leerlo: una desviación pequeña es normal y perseguirla
 * cuesta más de lo que ahorra. Lo que hay que mirar son las grandes y las que
 * se repiten mes a mes.
 */
class CostosCocina
{
    /**
     * Lo que las recetas dicen que debería haberse gastado.
     *
     * Cuenta solo las líneas **enviadas a preparación**: una comanda que se
     * quedó a medias y se anuló antes de mandarla no gastó nada.
     *
     * Las cortesías y las devueltas también cuentan: la cocina las hizo igual.
     *
     * @return array<int, array{insumo_id: int, insumo: string, unidad: string, cantidad: float}>
     */
    public function consumoTeorico(string $desde, string $hasta): array
    {
        $filas = db_connect()->table('comanda_lineas cl')
            ->select('rl.insumo_id, i.nombre AS insumo, i.unidad,
                      SUM(rl.cantidad * cl.cantidad) AS cantidad', false)
            ->join('comandas c', 'c.id = cl.comanda_id')
            ->join('receta_lineas rl', 'rl.producto_id = cl.producto_id')
            ->join('insumos i', 'i.id = rl.insumo_id')
            ->where('cl.enviado_cocina', 1)
            ->where('c.estado !=', 'anulada')
            ->where('cl.created_at >=', $desde . ' 00:00:00')
            ->where('cl.created_at <=', $hasta . ' 23:59:59')
            ->groupBy('rl.insumo_id, i.nombre, i.unidad')
            ->get()->getResultArray();

        $mapa = [];
        foreach ($filas as $f) {
            $mapa[(int) $f['insumo_id']] = [
                'insumo_id' => (int) $f['insumo_id'],
                'insumo'    => $f['insumo'],
                'unidad'    => $f['unidad'],
                'cantidad'  => (float) $f['cantidad'],
            ];
        }

        return $mapa;
    }

    /**
     * Teórico contra real, insumo a insumo.
     *
     * @return array{lineas: list<array>, resumen: array{teorico: float, real: float, desviacion: float}}
     */
    public function comparar(string $desde, string $hasta, ?int $bodegaId = null): array
    {
        $teorico = $this->consumoTeorico($desde, $hasta);
        $real    = (new MovimientoStockModel())->consumoReal($desde, $hasta, $bodegaId);

        $todos = $teorico + $real;   // la unión de los dos, sin repetir claves

        $lineas      = [];
        $valorTeorico = 0.0;
        $valorReal    = 0.0;

        foreach (array_keys($todos) as $insumoId) {
            $t = $teorico[$insumoId]['cantidad'] ?? 0.0;
            $r = $real[$insumoId]['cantidad'] ?? 0.0;

            $nombre = $teorico[$insumoId]['insumo'] ?? ($real[$insumoId]['insumo'] ?? '—');
            $unidad = $teorico[$insumoId]['unidad'] ?? ($real[$insumoId]['unidad'] ?? '');

            // El valor sale del coste al que salió de verdad; si no hubo
            // movimientos reales, del coste de ficha.
            $costo = $r > 0 && isset($real[$insumoId])
                ? ($real[$insumoId]['valor'] / max(0.0001, $r))
                : $this->costoFicha($insumoId);

            $valorTeorico += $t * $costo;
            $valorReal    += $r * $costo;

            $lineas[] = [
                'insumo_id'  => $insumoId,
                'insumo'     => $nombre,
                'unidad'     => $unidad,
                'teorico'    => round($t, 3),
                'real'       => round($r, 3),
                'diferencia' => round($r - $t, 3),
                // En porcentaje sobre lo teórico: es lo que permite comparar
                // una desviación de 2 kg de harina con una de 200 g de azafrán.
                'desviacion' => $t > 0 ? round((($r - $t) / $t) * 100, 1) : null,
                'valor'      => round(($r - $t) * $costo, 2),
            ];
        }

        // Lo más desviado primero, en valor: es donde está el dinero
        usort($lineas, static fn ($a, $b) => abs($b['valor']) <=> abs($a['valor']));

        return [
            'lineas'  => $lineas,
            'resumen' => [
                'teorico'    => round($valorTeorico, 2),
                'real'       => round($valorReal, 2),
                'desviacion' => round($valorReal - $valorTeorico, 2),
            ],
        ];
    }

    /**
     * Qué se vende y qué deja.
     *
     * Cruza lo vendido con el coste de la receta. Es el informe que dice qué
     * platos hay que empujar y cuáles habría que replantear: el que se vende
     * mucho y deja poco puede estar llenando la cocina sin llenar la caja.
     *
     * @return list<array>
     */
    public function popularidad(string $desde, string $hasta): array
    {
        $filas = db_connect()->table('comanda_lineas cl')
            ->select('cl.producto_id, cl.nombre_producto AS producto,
                      cc.nombre AS categoria,
                      SUM(cl.cantidad) AS unidades,
                      SUM(CASE WHEN cl.estado_linea = \'normal\' THEN cl.precio_unitario * cl.cantidad ELSE 0 END) AS ingreso,
                      SUM(CASE WHEN cl.estado_linea != \'normal\' THEN cl.cantidad ELSE 0 END) AS regaladas', false)
            ->join('comandas c', 'c.id = cl.comanda_id')
            ->join('carta_productos cp', 'cp.id = cl.producto_id', 'left')
            ->join('carta_categorias cc', 'cc.id = cp.categoria_id', 'left')
            ->where('c.estado !=', 'anulada')
            ->where('cl.enviado_cocina', 1)
            ->where('cl.created_at >=', $desde . ' 00:00:00')
            ->where('cl.created_at <=', $hasta . ' 23:59:59')
            ->groupBy('cl.producto_id, cl.nombre_producto, cc.nombre')
            ->orderBy('unidades', 'DESC')
            ->get()->getResultArray();

        $recetas = new \App\Models\RecetaModel();
        $total   = 0.0;
        foreach ($filas as $f) {
            $total += (float) $f['unidades'];
        }

        $salida = [];
        foreach ($filas as $f) {
            $unidades = (float) $f['unidades'];
            $ingreso  = (float) $f['ingreso'];
            $coste    = $f['producto_id'] !== null
                ? $recetas->costeDeProducto((int) $f['producto_id']) * $unidades
                : 0.0;

            $salida[] = [
                'producto_id' => $f['producto_id'] === null ? null : (int) $f['producto_id'],
                'producto'    => $f['producto'],
                'categoria'   => $f['categoria'] ?? '—',
                'unidades'    => $unidades,
                'regaladas'   => (float) $f['regaladas'],
                'cuota'       => $total > 0 ? round($unidades / $total * 100, 1) : 0.0,
                'ingreso'     => round($ingreso, 2),
                'coste'       => round($coste, 2),
                'margen'      => round($ingreso - $coste, 2),
                // Sobre el ingreso, no sobre el coste: es como se mira en
                // restauración («este plato deja un 68 %»).
                'margen_pct'  => $ingreso > 0 ? round(($ingreso - $coste) / $ingreso * 100, 1) : null,
            ];
        }

        return $salida;
    }

    private function costoFicha(int $insumoId): float
    {
        $insumo = db_connect()->table('insumos')->where('id', $insumoId)->get()->getRowArray();

        return (float) ($insumo['costo_unitario'] ?? 0);
    }
}
