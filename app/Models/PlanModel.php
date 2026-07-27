<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Planes de la tarifa: qué lleva incluido el precio de la noche.
 *
 * «Solo alojamiento», «Con desayuno», «Media pensión». Se cuelgan del tipo de
 * alojamiento —porque forman parte de la tarifa— y se pueden pisar en una
 * reserva concreta para el día que se negocia algo distinto.
 */
class PlanModel extends Model
{
    protected $table         = 'planes';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nombre', 'descripcion', 'activo'];
    protected $useTimestamps = true;

    /** Los que se pueden asignar, con cuántas cosas incluye cada uno. */
    public function listado(): array
    {
        $planes = $this->orderBy('activo', 'DESC')->orderBy('nombre')->findAll();

        foreach ($planes as &$plan) {
            $plan['lineas'] = (new PlanLineaModel())->where('plan_id', $plan['id'])->countAllResults();

            $plan['reservas'] = $this->db->table('reservas')
                ->where('plan_id', $plan['id'])
                ->whereIn('estado', ['pendiente', 'confirmada', 'checkin'])
                ->countAllResults();

            $plan['tipos'] = $this->db->table('tipos_unidad')
                ->where('plan_id', $plan['id'])
                ->countAllResults();
        }

        return $planes;
    }

    public function activos(): array
    {
        return $this->where('activo', 1)->orderBy('nombre')->findAll();
    }

    /**
     * ¿Se puede borrar?
     *
     * No, si hay estancias en curso que dependen de él: quitarles el plan a
     * mitad de estancia les cobraría el desayuno que ya tenían pagado.
     */
    public function sePuedeEliminar(int $id): bool
    {
        $enUso = $this->db->table('reservas')
            ->where('plan_id', $id)
            ->whereIn('estado', ['pendiente', 'confirmada', 'checkin'])
            ->countAllResults();

        $enTipos = $this->db->table('tipos_unidad')->where('plan_id', $id)->countAllResults();

        return $enUso === 0 && $enTipos === 0;
    }
}
