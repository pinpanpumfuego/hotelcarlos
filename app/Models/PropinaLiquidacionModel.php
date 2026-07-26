<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Reparto de propinas entre los trabajadores.
 *
 * Regla que gobierna todo esto: lo repartido tiene que ser igual a lo
 * recaudado. La propina no es del hotel, así que no puede quedarse un peso.
 */
class PropinaLiquidacionModel extends Model
{
    protected $table         = 'propina_liquidaciones';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'desde', 'hasta', 'criterio', 'recaudado', 'repartido',
        'estado', 'notas', 'usuario_id', 'cerrada_en',
    ];
    protected $useTimestamps = true;

    public const CRITERIOS = [
        'ventas'         => 'Según lo que atendió cada uno',
        'partes_iguales' => 'A partes iguales',
        'manual'         => 'Reparto a mano',
    ];

    /** Listado con quién la hizo. */
    public function listado(): array
    {
        return $this->select('propina_liquidaciones.*, usuarios.nombre AS usuario_nombre,
                              COUNT(propina_liquidacion_lineas.id) AS personas,
                              SUM(propina_liquidacion_lineas.entregado) AS entregadas')
            ->join('usuarios', 'usuarios.id = propina_liquidaciones.usuario_id', 'left')
            ->join('propina_liquidacion_lineas', 'propina_liquidacion_lineas.liquidacion_id = propina_liquidaciones.id', 'left')
            ->groupBy('propina_liquidaciones.id')
            ->orderBy('propina_liquidaciones.desde', 'DESC')
            ->findAll();
    }

    /**
     * Propinas cobradas en un periodo que aún no se han liquidado.
     *
     * Solo cuentan las comandas cobradas: una anulada no dejó propina.
     */
    public function pendientesDe(string $desde, string $hasta, ?int $excluirLiquidacion = null): array
    {
        $consulta = $this->db->table('comandas')
            ->select('comandas.id, comandas.numero, comandas.propina, comandas.empleado_id,
                      empleados.nombre, empleados.apellidos')
            ->join('empleados', 'empleados.id = comandas.empleado_id', 'left')
            ->where('comandas.estado', 'cobrada')
            ->where('comandas.propina >', 0)
            ->where('DATE(comandas.cerrada_en) >=', $desde)
            ->where('DATE(comandas.cerrada_en) <=', $hasta);

        if ($excluirLiquidacion !== null) {
            $consulta->groupStart()
                ->where('comandas.liquidacion_id IS NULL')
                ->orWhere('comandas.liquidacion_id', $excluirLiquidacion)
                ->groupEnd();
        } else {
            $consulta->where('comandas.liquidacion_id IS NULL');
        }

        return $consulta->orderBy('comandas.cerrada_en')->get()->getResultArray();
    }

    /**
     * Propone el reparto de un periodo.
     *
     * @return array{recaudado: float, comandas: int, sin_camarero: float, lineas: list<array>}
     */
    public function proponer(string $desde, string $hasta, string $criterio, ?int $excluir = null): array
    {
        $comandas = $this->pendientesDe($desde, $hasta, $excluir);

        $recaudado   = 0.0;
        $sinCamarero = 0.0;
        $porEmpleado = [];

        foreach ($comandas as $c) {
            $propina    = (float) $c['propina'];
            $recaudado += $propina;

            // Sin TPV compartido las comandas no tienen camarero: entonces el
            // reparto por ventas no se puede calcular y se avisa.
            if ($c['empleado_id'] === null) {
                $sinCamarero += $propina;

                continue;
            }

            $id = (int) $c['empleado_id'];
            $porEmpleado[$id] ??= [
                'empleado_id' => $id,
                'nombre'      => trim($c['nombre'] . ' ' . $c['apellidos']),
                'generado'    => 0.0,
                'comandas'    => 0,
            ];
            $porEmpleado[$id]['generado'] += $propina;
            $porEmpleado[$id]['comandas']++;
        }

        // Si nadie atendió con camarero identificado, se reparte entre quienes
        // pueden usar el TPV: es lo más razonable que se puede proponer.
        if ($porEmpleado === [] && $recaudado > 0) {
            foreach ((new EmpleadoModel())->delTpv() as $e) {
                $porEmpleado[(int) $e['id']] = [
                    'empleado_id' => (int) $e['id'],
                    'nombre'      => trim($e['nombre'] . ' ' . $e['apellidos']),
                    'generado'    => 0.0,
                    'comandas'    => 0,
                ];
            }
            $criterio = 'partes_iguales';
        }

        $lineas = array_values($porEmpleado);
        $lineas = $this->repartir($lineas, $recaudado, $criterio);

        return [
            'recaudado'    => round($recaudado, 2),
            'comandas'     => count($comandas),
            'sin_camarero' => round($sinCamarero, 2),
            'criterio'     => $criterio,
            'lineas'       => $lineas,
        ];
    }

    /**
     * Reparte un importe entre las líneas según el criterio.
     * El redondeo sobrante se le da a la primera: así la suma cuadra exacta.
     */
    private function repartir(array $lineas, float $total, string $criterio): array
    {
        if ($lineas === [] || $total <= 0) {
            foreach ($lineas as &$l) {
                $l['importe'] = 0.0;
            }

            return $lineas;
        }

        $base = array_sum(array_column($lineas, 'generado'));

        foreach ($lineas as &$l) {
            if ($criterio === 'partes_iguales' || $base <= 0) {
                $l['importe'] = floor($total / count($lineas));
            } else {
                $l['importe'] = floor($total * $l['generado'] / $base);
            }
        }
        unset($l);

        // Los pesos que quedan sueltos por el redondeo hacia abajo
        $sobra = round($total - array_sum(array_column($lineas, 'importe')));
        if ($sobra > 0) {
            $lineas[0]['importe'] += $sobra;
        }

        return $lineas;
    }

    /** Líneas guardadas de una liquidación. */
    public function lineas(int $liquidacionId): array
    {
        return $this->db->table('propina_liquidacion_lineas')
            ->select('propina_liquidacion_lineas.*, empleados.nombre, empleados.apellidos, empleados.cargo')
            ->join('empleados', 'empleados.id = propina_liquidacion_lineas.empleado_id')
            ->where('liquidacion_id', $liquidacionId)
            ->orderBy('propina_liquidacion_lineas.importe', 'DESC')
            ->get()
            ->getResultArray();
    }

    /** Comandas incluidas en una liquidación cerrada. */
    public function comandasDe(int $liquidacionId): array
    {
        return $this->db->table('comandas')
            ->select('numero, propina, cerrada_en')
            ->where('liquidacion_id', $liquidacionId)
            ->orderBy('cerrada_en')
            ->get()
            ->getResultArray();
    }
}
