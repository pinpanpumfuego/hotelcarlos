<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tareas de limpieza: nacen, se hacen, se inspeccionan.
 *
 * El ciclo es `pendiente → en_curso → hecha → inspeccionada`, con un desvío
 * posible: `rechazada`, que la devuelve a `pendiente` y suma un reproceso.
 *
 * **La inspección se puede apagar.** Con una sola persona que limpia y revisa,
 * pedirle que se inspeccione a sí misma es un clic sin sentido; con tres, es lo
 * único que garantiza que la cabaña esté como debe.
 */
class LimpiezaModel extends Model
{
    protected $table         = 'limpiezas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'unidad_id', 'usuario_id', 'tipo', 'estado', 'prioridad', 'para_hoy',
        'inicio', 'fin', 'notas', 'inspector_id', 'inspeccion_en',
        'motivo_rechazo', 'reprocesos', 'sin_lenceria',
    ];
    protected $useTimestamps = true;

    public const TIPOS = [
        'salida'     => 'De salida',
        'estancia'   => 'Durante la estancia',
        'profunda'   => 'Profunda',
        'preventiva' => 'Preventiva',
        'zona_comun' => 'Zona común',
    ];

    public const ESTADOS = [
        'pendiente'     => 'Pendiente',
        'en_curso'      => 'En limpieza',
        'hecha'         => 'Limpia, sin inspeccionar',
        'inspeccionada' => 'Inspeccionada',
        'rechazada'     => 'Hay que rehacerla',
    ];

    /** ¿Hace falta que alguien inspeccione, o basta con terminar? */
    public function exigeInspeccion(): bool
    {
        return (new ConfiguracionModel())->obtener('limpieza_exige_inspeccion', '1') === '1';
    }

    /**
     * Crea la tarea de una cabaña si no hay ya una viva.
     *
     * Idempotente a propósito: la crea el check-out, y el tablero también puede
     * pedirla. Dos tareas para la misma cabaña harían que dos personas
     * limpiaran lo mismo.
     */
    public function abrir(int $unidadId, string $tipo = 'salida', string $prioridad = 'normal'): int
    {
        $viva = $this->viva($unidadId);
        if ($viva !== null) {
            return (int) $viva['id'];
        }

        return (int) $this->insert([
            'unidad_id' => $unidadId,
            'tipo'      => isset(self::TIPOS[$tipo]) ? $tipo : 'salida',
            'estado'    => 'pendiente',
            'prioridad' => $prioridad === 'urgente' ? 'urgente' : 'normal',
            'para_hoy'  => date('Y-m-d'),
        ], true);
    }

    /** La tarea viva de una cabaña, si la hay. */
    public function viva(int $unidadId): ?array
    {
        return $this->where('unidad_id', $unidadId)
            ->whereIn('estado', ['pendiente', 'en_curso', 'hecha', 'rechazada'])
            ->orderBy('id', 'DESC')
            ->first();
    }

    /** Limpieza en curso de una unidad (o null). */
    public function enCurso(int $unidadId): ?array
    {
        return $this->where('unidad_id', $unidadId)->where('estado', 'en_curso')->first();
    }

    /** Mapa unidad_id → limpieza en curso, para pintar el tablero sin N consultas. */
    public function todasEnCurso(): array
    {
        $abiertas = $this->select('limpiezas.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id', 'left')
            ->where('limpiezas.estado', 'en_curso')
            ->findAll();

        $mapa = [];
        foreach ($abiertas as $l) {
            $mapa[$l['unidad_id']] = $l;
        }

        return $mapa;
    }

    /**
     * El tablero: lo que hay por hacer, ordenado por lo que urge.
     *
     * Primero lo urgente, después las cabañas con llegada hoy, y al final el
     * resto. Es lo que evita que se limpie por orden alfabético mientras un
     * huésped espera en recepción.
     */
    public function tablero(): array
    {
        $tareas = $this->select('limpiezas.*, unidades.nombre AS cabana, unidades.estado_limpieza,
                                 usuarios.nombre AS quien, inspectores.nombre AS inspector')
            ->join('unidades', 'unidades.id = limpiezas.unidad_id', 'left')
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id', 'left')
            ->join('usuarios AS inspectores', 'inspectores.id = limpiezas.inspector_id', 'left')
            ->whereIn('limpiezas.estado', ['pendiente', 'en_curso', 'hecha', 'rechazada'])
            ->findAll();

        // Qué cabañas tienen llegada hoy: esas van primero
        $conLlegada = array_map('intval', array_column(
            $this->db->table('reservas')
                ->select('unidad_id')
                ->where('fecha_entrada', date('Y-m-d'))
                ->whereIn('estado', ['confirmada', 'pendiente'])
                ->get()->getResultArray(),
            'unidad_id'
        ));

        foreach ($tareas as &$t) {
            $t['llega_hoy'] = in_array((int) $t['unidad_id'], $conLlegada, true);
            $t['minutos']   = $t['inicio'] !== null && $t['fin'] === null
                ? (int) ((time() - strtotime($t['inicio'])) / 60)
                : null;
        }
        unset($t);

        usort($tareas, static fn (array $a, array $b): int => [
            $b['prioridad'] === 'urgente', $b['llega_hoy'], (int) $a['id'] * -1,
        ] <=> [
            $a['prioridad'] === 'urgente', $a['llega_hoy'], (int) $b['id'] * -1,
        ]);

        return $tareas;
    }

    /** Lo que espera inspección. */
    public function porInspeccionar(): array
    {
        return $this->select('limpiezas.*, unidades.nombre AS cabana, usuarios.nombre AS quien')
            ->join('unidades', 'unidades.id = limpiezas.unidad_id', 'left')
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id', 'left')
            ->where('limpiezas.estado', 'hecha')
            ->orderBy('limpiezas.fin')
            ->findAll();
    }

    /** Últimos registros terminados, con unidad y persona. */
    public function historial(int $limite = 30): array
    {
        return $this->select('limpiezas.*, usuarios.nombre AS usuario_nombre,
                              unidades.nombre AS unidad_nombre, inspectores.nombre AS inspector')
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id', 'left')
            ->join('unidades', 'unidades.id = limpiezas.unidad_id', 'left')
            ->join('usuarios AS inspectores', 'inspectores.id = limpiezas.inspector_id', 'left')
            ->whereIn('limpiezas.estado', ['hecha', 'inspeccionada'])
            ->orderBy('limpiezas.id', 'DESC')
            ->findAll($limite);
    }

    /**
     * Cuánto se tarda y cuánto hay que rehacer.
     *
     * Los reprocesos sirven para formar, no para castigar: si una persona
     * repite siempre el mismo punto, lo más probable es que ese punto no esté
     * bien explicado.
     *
     * @return array{tareas: int, minutos_medios: float|null, reprocesos: int, tasa: float|null}
     */
    public function rendimiento(string $desde, string $hasta): array
    {
        $fila = $this->select('COUNT(*) AS tareas,
                               AVG(TIMESTAMPDIFF(MINUTE, inicio, fin)) AS minutos,
                               SUM(reprocesos) AS reprocesos', false)
            ->whereIn('estado', ['hecha', 'inspeccionada'])
            ->where('fin >=', $desde . ' 00:00:00')
            ->where('fin <=', $hasta . ' 23:59:59')
            ->first();

        $tareas     = (int) ($fila['tareas'] ?? 0);
        $reprocesos = (int) ($fila['reprocesos'] ?? 0);

        return [
            'tareas'         => $tareas,
            'minutos_medios' => $fila['minutos'] === null ? null : round((float) $fila['minutos'], 1),
            'reprocesos'     => $reprocesos,
            'tasa'           => $tareas > 0 ? round($reprocesos / $tareas * 100, 1) : null,
        ];
    }

    /** Por persona, para el mismo periodo. */
    public function porPersona(string $desde, string $hasta): array
    {
        return $this->select('usuarios.nombre AS quien, COUNT(*) AS tareas,
                              AVG(TIMESTAMPDIFF(MINUTE, limpiezas.inicio, limpiezas.fin)) AS minutos,
                              SUM(limpiezas.reprocesos) AS reprocesos', false)
            ->join('usuarios', 'usuarios.id = limpiezas.usuario_id')
            ->whereIn('limpiezas.estado', ['hecha', 'inspeccionada'])
            ->where('limpiezas.fin >=', $desde . ' 00:00:00')
            ->where('limpiezas.fin <=', $hasta . ' 23:59:59')
            ->groupBy('usuarios.nombre')
            ->orderBy('tareas', 'DESC')
            ->findAll();
    }
}
