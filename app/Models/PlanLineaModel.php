<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Cada derecho de un plan: «un desayuno por persona y día».
 *
 * Puede apuntar a una categoría entera —lo normal, para dejar elegir dentro de
 * lo incluido— o a un producto concreto.
 */
class PlanLineaModel extends Model
{
    protected $table         = 'plan_lineas';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['plan_id', 'categoria_id', 'producto_id', 'cantidad', 'por', 'franja', 'tope'];
    protected $useTimestamps = true;

    /** Cómo se cuenta cada derecho. */
    public const FORMAS = [
        'persona_dia'      => 'por persona y día',
        'persona_estancia' => 'por persona y estancia',
        'estancia'         => 'por estancia',
    ];

    public function delPlan(int $planId): array
    {
        return $this->where('plan_id', $planId)->orderBy('id')->findAll();
    }

    /** Igual, pero con el nombre de lo que incluye, para enseñarlo. */
    public function delPlanConNombres(int $planId): array
    {
        return $this->select('plan_lineas.*,
                              COALESCE(carta_productos.nombre, carta_categorias.nombre) AS concepto')
            ->join('carta_productos', 'carta_productos.id = plan_lineas.producto_id', 'left')
            ->join('carta_categorias', 'carta_categorias.id = plan_lineas.categoria_id', 'left')
            ->where('plan_lineas.plan_id', $planId)
            ->orderBy('plan_lineas.id')
            ->findAll();
    }

    /**
     * Guarda un derecho.
     *
     * Exige que apunte a **una cosa u otra**, no a las dos ni a ninguna: un
     * derecho que no dice qué incluye no se puede aplicar, y uno que dice dos
     * cosas no se sabe cuál gasta.
     */
    public function guardarDerecho(array $datos): bool
    {
        $categoria = ! empty($datos['categoria_id']) ? (int) $datos['categoria_id'] : null;
        $producto  = ! empty($datos['producto_id']) ? (int) $datos['producto_id'] : null;

        if (($categoria === null && $producto === null) || ($categoria !== null && $producto !== null)) {
            return false;
        }

        return (bool) $this->insert([
            'plan_id'      => (int) $datos['plan_id'],
            'categoria_id' => $categoria,
            'producto_id'  => $producto,
            'cantidad'     => max(1, (int) ($datos['cantidad'] ?? 1)),
            'por'          => isset(self::FORMAS[$datos['por'] ?? '']) ? $datos['por'] : 'persona_dia',
            'franja'       => ! empty($datos['franja']) ? $datos['franja'] : null,
            'tope'         => max(0, (float) ($datos['tope'] ?? 0)),
        ]);
    }
}
