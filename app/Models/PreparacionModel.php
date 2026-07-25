<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Componentes de una preparación (subreceta).
 * Un componente puede ser un insumo simple u otra preparación.
 */
class PreparacionModel extends Model
{
    protected $table         = 'preparacion_lineas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['preparacion_id', 'insumo_id', 'cantidad'];
    protected $useTimestamps = true;

    /** Componentes de una preparación, con su coste. */
    public function componentesDe(int $preparacionId): array
    {
        $filas = $this->select('preparacion_lineas.*, insumos.nombre, insumos.unidad,
                                insumos.costo_unitario, insumos.es_preparacion')
            ->join('insumos', 'insumos.id = preparacion_lineas.insumo_id')
            ->where('preparacion_id', $preparacionId)
            ->orderBy('insumos.nombre')
            ->findAll();

        foreach ($filas as &$f) {
            $f['cantidad']       = (float) $f['cantidad'];
            $f['costo_unitario'] = (float) $f['costo_unitario'];
            $f['costo']          = $f['cantidad'] * $f['costo_unitario'];
        }

        return $filas;
    }

    /**
     * Recalcula el coste por unidad de todas las preparaciones.
     *
     * Se repite hasta que los valores dejan de cambiar, de modo que una
     * preparación que use otra quede bien calculada sin importar el orden.
     * Como no se admiten ciclos, siempre converge.
     */
    public function recalcularCostes(): void
    {
        $insumos       = new InsumoModel();
        $preparaciones = $insumos->where('es_preparacion', 1)->findAll();
        if ($preparaciones === []) {
            return;
        }

        $vueltas = count($preparaciones) + 1;

        for ($i = 0; $i < $vueltas; $i++) {
            $huboCambios = false;

            foreach ($preparaciones as $p) {
                $rendimiento = (float) ($p['rendimiento'] ?? 0);
                if ($rendimiento <= 0) {
                    continue;   // sin rendimiento no se puede repartir el coste
                }

                $fila = $this->select('SUM(preparacion_lineas.cantidad * insumos.costo_unitario) AS coste', false)
                    ->join('insumos', 'insumos.id = preparacion_lineas.insumo_id')
                    ->where('preparacion_lineas.preparacion_id', $p['id'])
                    ->first();

                $costeTanda = (float) ($fila['coste'] ?? 0);
                $porUnidad  = round($costeTanda / $rendimiento, 4);

                if (abs($porUnidad - (float) $p['costo_unitario']) > 0.0001) {
                    $insumos->update($p['id'], ['costo_unitario' => $porUnidad]);
                    $huboCambios = true;
                }
            }

            if (! $huboCambios) {
                break;
            }

            $preparaciones = $insumos->where('es_preparacion', 1)->findAll();
        }
    }

    /**
     * ¿Usar $candidato dentro de $preparacion crearía un ciclo?
     * Lo hay si la preparación actual ya forma parte, directa o
     * indirectamente, de los componentes del candidato.
     */
    public function generariaCiclo(int $preparacionId, int $candidatoId): bool
    {
        if ($preparacionId === $candidatoId) {
            return true;
        }

        $porVisitar = [$candidatoId];
        $visitados  = [];

        while ($porVisitar !== []) {
            $actual = array_pop($porVisitar);
            if (isset($visitados[$actual])) {
                continue;
            }
            $visitados[$actual] = true;

            $hijos = $this->where('preparacion_id', $actual)->findAll();
            foreach ($hijos as $h) {
                $hijoId = (int) $h['insumo_id'];
                if ($hijoId === $preparacionId) {
                    return true;
                }
                $porVisitar[] = $hijoId;
            }
        }

        return false;
    }

    /** Platos y preparaciones donde se usa un insumo o preparación. */
    public function dondeSeUsa(int $insumoId): array
    {
        $platos = db_connect()->table('receta_lineas')
            ->select('carta_productos.nombre')
            ->join('carta_productos', 'carta_productos.id = receta_lineas.producto_id')
            ->where('receta_lineas.insumo_id', $insumoId)
            ->get()->getResultArray();

        $otras = $this->select('insumos.nombre')
            ->join('insumos', 'insumos.id = preparacion_lineas.preparacion_id')
            ->where('preparacion_lineas.insumo_id', $insumoId)
            ->findAll();

        return [
            'platos'        => array_column($platos, 'nombre'),
            'preparaciones' => array_column($otras, 'nombre'),
        ];
    }
}
