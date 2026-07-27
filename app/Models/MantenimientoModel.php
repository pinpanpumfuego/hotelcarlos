<?php

namespace App\Models;

use CodeIgniter\Model;

class MantenimientoModel extends Model
{
    protected $table         = 'mantenimientos';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'unidad_id', 'activo_id', 'ubicacion', 'titulo', 'tipo', 'origen', 'solicitud_id',
        'descripcion', 'prioridad', 'estado', 'reporto_id', 'asignado_a', 'vence_en',
        'iniciada_en', 'resolvio_id', 'resuelta_en', 'minutos', 'costo_materiales',
        'costo_mano_obra', 'bloqueo_unidad', 'solucion',
    ];
    protected $useTimestamps = true;

    public const PRIORIDADES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'urgente' => 'Urgente'];

    public const ORIGENES = [
        'recepcion'  => 'Recepción',
        'limpieza'   => 'Limpieza',
        'tecnico'    => 'Mantenimiento',
        'huesped'    => 'El huésped',
        'preventivo' => 'Plan preventivo',
        'otro'       => 'Otro',
    ];

    /** Las que siguen vivas: ni resueltas, ni verificadas, ni anuladas. */
    public const ABIERTAS = ['abierta', 'en_proceso', 'pausada'];

    /**
     * Cuándo debería estar resuelta, según su prioridad.
     *
     * No es una promesa a nadie de fuera. Sirve para que en el tablero se vea de
     * un vistazo qué se está quedando atrás, que es lo que de verdad pasa: no
     * que nadie arregle nada, sino que algo lleve tres semanas ahí sin que
     * nadie se dé cuenta.
     */
    public function vencimiento(string $prioridad, ?string $desde = null): string
    {
        $horas = (int) (new ConfiguracionModel())->obtener('mant_sla_' . $prioridad, '72');
        $horas = max(1, $horas);

        return date('Y-m-d H:i:s', strtotime(($desde ?? date('Y-m-d H:i:s')) . ' +' . $horas . ' hours'));
    }

    /** Incidencias con nombres de unidad y personas. */
    public function listar(bool $soloAbiertas): array
    {
        $builder = $this->select('mantenimientos.*, unidades.nombre AS unidad_nombre,
                                  activos.nombre AS activo_nombre, activos.codigo AS activo_codigo,
                                  reporta.nombre AS reporto_nombre, resuelve.nombre AS resolvio_nombre')
            ->join('unidades', 'unidades.id = mantenimientos.unidad_id', 'left')
            ->join('activos', 'activos.id = mantenimientos.activo_id', 'left')
            // `left` porque quien reporta puede no ser un usuario del panel: el
            // huésped no lo es, y con un `join` normal sus averías no salían.
            ->join('usuarios AS reporta', 'reporta.id = mantenimientos.reporto_id', 'left')
            ->join('usuarios AS resuelve', 'resuelve.id = mantenimientos.resolvio_id', 'left');

        if ($soloAbiertas) {
            return $builder->whereIn('mantenimientos.estado', self::ABIERTAS)
                ->orderBy("FIELD(mantenimientos.prioridad, 'urgente', 'alta', 'media', 'baja')")
                ->orderBy('mantenimientos.created_at')
                ->findAll();
        }

        return $builder->whereIn('mantenimientos.estado', ['resuelta', 'verificada'])
            ->orderBy('mantenimientos.resuelta_en', 'DESC')
            ->findAll(10);
    }
}
