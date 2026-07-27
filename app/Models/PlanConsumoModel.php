<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lo que ya se ha gastado del plan.
 *
 * Se apunta al consumir en lugar de deducirlo de las comandas. Deducirlo sería
 * frágil: una comanda anulada devolvería un derecho que se gastó de verdad, y
 * el huésped desayunaría dos veces gratis.
 */
class PlanConsumoModel extends Model
{
    protected $table         = 'plan_consumos';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['reserva_id', 'linea_id', 'comanda_linea_id', 'fecha', 'cantidad', 'valor'];
    protected $useTimestamps = true;

    /** Cuánto se ha gastado hoy de un derecho concreto. */
    public function gastadoHoy(int $reservaId, int $lineaId): int
    {
        $fila = $this->selectSum('cantidad', 'total')
            ->where('reserva_id', $reservaId)
            ->where('linea_id', $lineaId)
            ->where('fecha', date('Y-m-d'))
            ->first();

        return (int) ($fila['total'] ?? 0);
    }

    /** Cuánto se ha gastado en toda la estancia. */
    public function gastadoTotal(int $reservaId, int $lineaId): int
    {
        $fila = $this->selectSum('cantidad', 'total')
            ->where('reserva_id', $reservaId)
            ->where('linea_id', $lineaId)
            ->first();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Lo incluido que se ha consumido en un periodo.
     *
     * Es lo que hace falta para saber cuánto está costando de verdad un plan:
     * el desayuno incluido no se cobra, pero la cocina lo gasta igual.
     */
    public function valorPeriodo(string $desde, string $hasta): array
    {
        return $this->select('plan_consumos.fecha, SUM(plan_consumos.cantidad) AS unidades,
                              SUM(plan_consumos.valor) AS valor')
            ->where('plan_consumos.fecha >=', $desde)
            ->where('plan_consumos.fecha <=', $hasta)
            ->groupBy('plan_consumos.fecha')
            ->orderBy('plan_consumos.fecha')
            ->findAll();
    }

    /** Lo consumido de una estancia, para la ficha de la reserva. */
    public function deReserva(int $reservaId): array
    {
        return $this->select('plan_consumos.*,
                              COALESCE(carta_productos.nombre, carta_categorias.nombre) AS concepto')
            ->join('plan_lineas', 'plan_lineas.id = plan_consumos.linea_id', 'left')
            ->join('carta_productos', 'carta_productos.id = plan_lineas.producto_id', 'left')
            ->join('carta_categorias', 'carta_categorias.id = plan_lineas.categoria_id', 'left')
            ->where('plan_consumos.reserva_id', $reservaId)
            ->orderBy('plan_consumos.fecha', 'DESC')
            ->findAll(100);
    }
}
