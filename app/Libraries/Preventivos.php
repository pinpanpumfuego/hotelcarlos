<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ActivoModel;
use App\Models\MantenimientoModel;
use App\Models\PlanMantenimientoModel;

/**
 * Abre solas las órdenes del plan preventivo.
 *
 * Corre de madrugada desde una tarea programada, sin nadie delante. Por eso se
 * apoya en `Ordenes` en vez de repetir su lógica: si mañana cambia la regla de
 * cuándo se suelta una cabaña, aquí se aplica igual sin tocar nada.
 *
 * Dos cosas que hace y parecen detalles pero no lo son:
 *
 * - **No apila.** Si la revisión de septiembre sigue sin hacerse, la de octubre
 *   no se abre. Tres órdenes idénticas en el tablero es la forma más rápida de
 *   que nadie mire ninguna.
 * - **La fecha siguiente se cuenta desde la que tocaba**, no desde hoy. Si no,
 *   el calendario se desplaza un poco cada vez hasta no significar nada.
 */
class Preventivos
{
    private PlanMantenimientoModel $planes;
    private MantenimientoModel $ordenes;

    public function __construct()
    {
        $this->planes  = new PlanMantenimientoModel();
        $this->ordenes = new MantenimientoModel();
    }

    /**
     * Genera lo que toque.
     *
     * @return array{abiertas: int, saltadas: int, detalle: list<string>}
     */
    public function generar(?string $hoy = null): array
    {
        $hoy      = $hoy ?? date('Y-m-d');
        $abiertas = 0;
        $saltadas = 0;
        $detalle  = [];

        foreach ($this->planes->vencidos($hoy) as $plan) {
            foreach ($this->equiposDelPlan($plan) as $activo) {
                if ($this->tienePendiente((int) $plan['id'], $activo === null ? null : (int) $activo['id'])) {
                    $saltadas++;
                    $detalle[] = 'Saltado (ya hay una sin cerrar): ' . $plan['nombre']
                        . ($activo !== null ? ' · ' . $activo['nombre'] : '');

                    continue;
                }

                $id = $this->abrirOrden($plan, $activo);
                $abiertas++;
                $detalle[] = 'Abierta #' . $id . ': ' . $plan['nombre']
                    . ($activo !== null ? ' · ' . $activo['nombre'] : '');
            }

            // La fecha avanza aunque se haya saltado: si no, el plan se
            // quedaría clavado en el pasado intentándolo cada madrugada.
            $this->planes->update($plan['id'], [
                'proxima_fecha' => $this->planes->siguienteFecha($plan),
                'ultima_fecha'  => $plan['proxima_fecha'],
            ]);
        }

        return ['abiertas' => $abiertas, 'saltadas' => $saltadas, 'detalle' => $detalle];
    }

    /**
     * A qué equipos aplica un plan.
     *
     * Un plan por categoría («todos los extintores») genera una orden por cada
     * equipo. Un plan sin equipo ni categoría es una tarea general del
     * alojamiento —limpiar canaletas, revisar el muelle— y genera una sola.
     *
     * @return list<array|null>
     */
    private function equiposDelPlan(array $plan): array
    {
        if ($plan['activo_id'] !== null) {
            $activo = (new ActivoModel())->find($plan['activo_id']);

            // Un equipo dado de baja ya no se revisa
            return $activo !== null && $activo['estado'] !== 'baja' ? [$activo] : [];
        }

        if ($plan['categoria'] !== null && $plan['categoria'] !== '') {
            $activos = (new ActivoModel())
                ->where('categoria', $plan['categoria'])
                ->where('estado !=', 'baja')
                ->orderBy('nombre')
                ->findAll();

            return $activos;
        }

        return [null];
    }

    /** ¿Queda alguna de este plan sin cerrar? */
    private function tienePendiente(int $planId, ?int $activoId): bool
    {
        $q = $this->ordenes->where('plan_id', $planId)
            ->whereIn('estado', MantenimientoModel::ABIERTAS);

        if ($activoId === null) {
            $q->where('activo_id', null);
        } else {
            $q->where('activo_id', $activoId);
        }

        return $q->countAllResults() > 0;
    }

    private function abrirOrden(array $plan, ?array $activo): int
    {
        $titulo = $plan['nombre'];

        if ($activo !== null) {
            $titulo .= ' · ' . $activo['nombre'];
        }

        $descripcion = trim((string) $plan['instrucciones']);
        $descripcion = $descripcion !== ''
            ? $descripcion
            : 'Revisión programada. Sin instrucciones apuntadas todavía.';

        $descripcion .= "\n· Programada para el " . date('d/m/Y', strtotime($plan['proxima_fecha']));

        if ((int) $plan['obligatorio'] === 1) {
            $descripcion .= "\n· Revisión obligatoria: guarda el certificado en la ficha del equipo.";
        }

        $id = (new Ordenes())->abrir([
            'titulo'      => mb_substr($titulo, 0, 150),
            'descripcion' => $descripcion,
            'tipo'        => 'preventiva',
            'origen'      => 'preventivo',
            'prioridad'   => $plan['prioridad'],
            'activo_id'   => $activo !== null ? (int) $activo['id'] : null,
            'unidad_id'   => $activo !== null && $activo['unidad_id'] !== null ? (int) $activo['unidad_id'] : null,
            'asignado_a'  => $plan['asignado_a'] !== null ? (int) $plan['asignado_a'] : null,
            // Al ir marcada como preventiva, `Ordenes` no deja el equipo como
            // averiado: revisar algo no es que esté roto.
        ]);

        $this->ordenes->update($id, ['plan_id' => (int) $plan['id']]);

        return $id;
    }
}
