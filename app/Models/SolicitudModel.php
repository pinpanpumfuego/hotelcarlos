<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Lo que el huésped pide durante la estancia: toallas, una cuna, salir tarde.
 *
 * Es distinto de `mantenimiento`, que son averías del hotel, y de `limpieza`,
 * que es el trabajo programado. Esto son peticiones puntuales de una persona
 * concreta que está aquí ahora, y por eso llevan a quién y a qué cabaña.
 */
class SolicitudModel extends Model
{
    protected $table         = 'solicitudes';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'reserva_id', 'unidad_id', 'tipo', 'detalle', 'para_cuando',
        'estado', 'prioridad', 'respuesta', 'atendida_por', 'atendida_en',
    ];
    protected $useTimestamps = true;

    /** clave => cómo se le enseña al huésped. */
    public const TIPOS = [
        'limpieza'     => 'Limpieza de la cabaña',
        'toallas'      => 'Toallas o ropa de cama',
        'amenidades'   => 'Jabón, papel, agua…',
        'mantenimiento' => 'Algo no funciona',
        'cuna'         => 'Cuna o cama supletoria',
        'decoracion'   => 'Decoración para una ocasión especial',
        'salida_tarde' => 'Salir más tarde el último día',
        'transporte'   => 'Transporte o traslado',
        'otro'         => 'Otra cosa',
    ];

    /**
     * Peticiones que **no** puede resolver el sistema solo.
     *
     * Salir tarde depende de si hay entrada ese día, y el transporte de si hay
     * quien conduzca. Prometerlas automáticamente sería mentir, así que estas
     * se enseñan al huésped como «lo miramos y te decimos», no como hechas.
     */
    public const REQUIEREN_CONFIRMACION = ['salida_tarde', 'transporte', 'decoracion', 'cuna'];

    /** Las que están sin resolver, con la cabaña y el huésped. */
    public function pendientes(): array
    {
        return $this->consulta()->whereIn('solicitudes.estado', ['pendiente', 'en_curso'])
            ->orderBy('solicitudes.prioridad', 'DESC')
            ->orderBy('solicitudes.created_at')
            ->findAll();
    }

    /** Historial completo, con filtros del panel. */
    public function buscar(array $filtros = []): self
    {
        $q = $this->consulta();

        if (! empty($filtros['estado'])) {
            $q->where('solicitudes.estado', $filtros['estado']);
        }
        if (! empty($filtros['tipo'])) {
            $q->where('solicitudes.tipo', $filtros['tipo']);
        }
        if (! empty($filtros['desde'])) {
            $q->where('solicitudes.created_at >=', $filtros['desde'] . ' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $q->where('solicitudes.created_at <=', $filtros['hasta'] . ' 23:59:59');
        }

        return $q->orderBy('solicitudes.id', 'DESC');
    }

    private function consulta(): self
    {
        return $this->select('solicitudes.*, unidades.nombre AS cabana,
                              reservas.codigo AS reserva_codigo,
                              huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos')
            ->join('reservas', 'reservas.id = solicitudes.reserva_id', 'left')
            ->join('unidades', 'unidades.id = solicitudes.unidad_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left');
    }

    /** Las de una reserva, para que el huésped vea en qué van las suyas. */
    public function deReserva(int $reservaId): array
    {
        return $this->where('reserva_id', $reservaId)
            ->orderBy('id', 'DESC')
            ->findAll(30);
    }

    /**
     * Cuántas ha pedido hoy esta reserva.
     *
     * Freno sencillo contra el dedo nervioso y contra un enlace que circule de
     * más: doce peticiones en un día no las hace nadie de buena fe.
     */
    public function hoyDe(int $reservaId): int
    {
        return $this->where('reserva_id', $reservaId)
            ->where('created_at >=', date('Y-m-d') . ' 00:00:00')
            ->countAllResults();
    }

    public function atender(int $id, string $estado, ?string $respuesta, ?int $usuarioId): bool
    {
        if (! in_array($estado, ['pendiente', 'en_curso', 'resuelta', 'rechazada'], true)) {
            return false;
        }

        $datos = ['estado' => $estado, 'respuesta' => $respuesta];

        // La hora de atención se pone al cerrarla, no al tocarla: es el dato
        // que sirve para saber cuánto se tarda en responder.
        if (in_array($estado, ['resuelta', 'rechazada'], true)) {
            $datos['atendida_por'] = $usuarioId;
            $datos['atendida_en']  = date('Y-m-d H:i:s');
        }

        return (bool) $this->update($id, $datos);
    }

    /** Cuántas hay sin atender, para la insignia del menú. */
    public function sinAtender(): int
    {
        return $this->where('estado', 'pendiente')->countAllResults();
    }
}
