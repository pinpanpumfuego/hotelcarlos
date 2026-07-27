<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * El plan de mantenimiento preventivo.
 *
 * Un plan dice «esto hay que mirarlo cada tanto»: los extintores cada año, la
 * bomba de la piscina cada tres meses, las cerraduras cada seis. La gracia no
 * es la lista —eso cabe en una libreta— sino que **la orden se abra sola** unos
 * días antes, con las instrucciones dentro, y aparezca en el mismo tablero que
 * lo demás.
 *
 * Se llama así y no `PlanModel` porque `planes` ya existe: son los planes de
 * comidas.
 */
class PlanMantenimientoModel extends Model
{
    protected $table         = 'planes_mantenimiento';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'nombre', 'activo_id', 'categoria', 'cada', 'periodo', 'proxima_fecha',
        'aviso_dias', 'prioridad', 'asignado_a', 'duracion_min', 'instrucciones',
        'obligatorio', 'activo', 'ultima_fecha',
    ];

    public const PERIODOS = ['dias' => 'días', 'meses' => 'meses'];

    protected $validationRules = [
        'nombre'        => 'required|min_length[3]|max_length[150]',
        'cada'          => 'required|is_natural_no_zero',
        'proxima_fecha' => 'required|valid_date',
    ];

    protected $validationMessages = [
        'nombre'        => ['required' => 'Ponle un nombre al plan.'],
        'cada'          => ['is_natural_no_zero' => 'La periodicidad tiene que ser al menos 1.'],
        'proxima_fecha' => ['required' => 'Di cuándo toca la primera vez.'],
    ];

    /**
     * Planes que ya toca abrir, contando el aviso previo.
     *
     * El aviso previo no es un lujo: si la revisión del extintor aparece el
     * mismo día que vence, no da tiempo a llamar a nadie.
     */
    public function vencidos(?string $hoy = null): array
    {
        $hoy = $hoy ?? date('Y-m-d');

        return $this->select('planes_mantenimiento.*, activos.nombre AS activo_nombre,
                              activos.unidad_id AS activo_unidad, activos.estado AS activo_estado')
            ->join('activos', 'activos.id = planes_mantenimiento.activo_id', 'left')
            ->where('planes_mantenimiento.activo', 1)
            ->where('DATE_SUB(planes_mantenimiento.proxima_fecha, INTERVAL planes_mantenimiento.aviso_dias DAY) <=', $hoy)
            ->orderBy('planes_mantenimiento.proxima_fecha')
            ->findAll();
    }

    /** Todos, con el equipo al que apuntan. */
    public function listar(bool $soloActivos = false): array
    {
        $q = $this->select('planes_mantenimiento.*, activos.nombre AS activo_nombre, activos.codigo AS activo_codigo,
                            usuarios.nombre AS asignado_nombre')
            ->join('activos', 'activos.id = planes_mantenimiento.activo_id', 'left')
            ->join('usuarios', 'usuarios.id = planes_mantenimiento.asignado_a', 'left');

        if ($soloActivos) {
            $q->where('planes_mantenimiento.activo', 1);
        }

        return $q->orderBy('planes_mantenimiento.activo', 'DESC')
            ->orderBy('planes_mantenimiento.proxima_fecha')
            ->findAll();
    }

    /**
     * La fecha siguiente, contada desde la que tocaba.
     *
     * Desde la que tocaba y no desde hoy a propósito: si la revisión anual del
     * extintor se hace con dos semanas de retraso, la del año que viene sigue
     * cayendo en su mes. Contando desde hoy, el calendario se iría desplazando
     * un poco cada año hasta no significar nada.
     */
    public function siguienteFecha(array $plan): string
    {
        $intervalo = '+' . max(1, (int) $plan['cada']) . ' ' . ($plan['periodo'] === 'dias' ? 'days' : 'months');
        $siguiente = date('Y-m-d', strtotime($plan['proxima_fecha'] . ' ' . $intervalo));

        // Salvo que el retraso sea tan grande que la siguiente ya estuviera
        // pasada: ahí sí se cuenta desde hoy, porque abrir una orden con fecha
        // vencida el mismo día de crearla no le sirve a nadie.
        if ($siguiente < date('Y-m-d')) {
            $siguiente = date('Y-m-d', strtotime('today ' . $intervalo));
        }

        return $siguiente;
    }
}
