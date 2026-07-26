<?php

namespace App\Models;

use CodeIgniter\Model;

/** Salidas contratadas: quién va, cuándo y cuánto se le cobra. */
class ExperienciaReservaModel extends Model
{
    protected $table         = 'experiencia_reservas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'experiencia_id', 'reserva_id', 'huesped_id', 'cliente_nombre', 'cliente_telefono',
        'fecha', 'hora', 'adultos', 'ninos', 'precio_unitario', 'precio_nino', 'total',
        'estado', 'empleado_id', 'folio_movimiento_id', 'cobrado_aparte',
        'notas', 'motivo', 'usuario_id',
    ];
    protected $useTimestamps = true;

    public const ESTADOS = [
        'solicitada' => 'Solicitada',
        'confirmada' => 'Confirmada',
        'realizada'  => 'Realizada',
        'cancelada'  => 'Cancelada',
        'no_show'    => 'No se presentó',
    ];

    public const COLORES = [
        'solicitada' => 'warning',
        'confirmada' => 'primary',
        'realizada'  => 'success',
        'cancelada'  => 'danger',
        'no_show'    => 'secondary',
    ];

    /** Las que ocupan cupo: una cancelada deja su sitio libre. */
    public const ESTADOS_VIVOS = ['solicitada', 'confirmada', 'realizada'];

    /** Consulta base con el nombre de la experiencia y del cliente. */
    private function detallada()
    {
        return $this->select('experiencia_reservas.*, experiencias.nombre AS experiencia,
                              experiencias.categoria, experiencias.duracion_min, experiencias.punto_encuentro,
                              experiencias.capacidad,
                              huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos,
                              huespedes.telefono AS h_telefono,
                              reservas.codigo AS reserva_codigo, unidades.nombre AS unidad_nombre,
                              empleados.nombre AS guia_nombre')
            ->join('experiencias', 'experiencias.id = experiencia_reservas.experiencia_id')
            ->join('huespedes', 'huespedes.id = experiencia_reservas.huesped_id', 'left')
            ->join('reservas', 'reservas.id = experiencia_reservas.reserva_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->join('empleados', 'empleados.id = experiencia_reservas.empleado_id', 'left');
    }

    /** Agenda de un rango de fechas. */
    public function agenda(string $desde, string $hasta, ?int $experienciaId = null): array
    {
        $this->detallada()
            ->where('experiencia_reservas.fecha >=', $desde)
            ->where('experiencia_reservas.fecha <=', $hasta);

        if ($experienciaId !== null) {
            $this->where('experiencia_reservas.experiencia_id', $experienciaId);
        }

        return $this->orderBy('experiencia_reservas.fecha')
            ->orderBy('experiencia_reservas.hora')
            ->orderBy('experiencia_reservas.id')
            ->findAll();
    }

    /** Salidas de un día agrupadas por experiencia y hora, listas para pintar. */
    public function delDia(string $fecha): array
    {
        $salidas = [];

        foreach ($this->agenda($fecha, $fecha) as $r) {
            $clave = $r['experiencia_id'] . '|' . ($r['hora'] ?? 'sin');
            $salidas[$clave]['experiencia'] = $r['experiencia'];
            $salidas[$clave]['hora']        = $r['hora'];
            $salidas[$clave]['capacidad']   = (int) $r['capacidad'];
            $salidas[$clave]['punto']       = $r['punto_encuentro'];
            $salidas[$clave]['reservas'][]  = $r;
            $salidas[$clave]['personas']    = ($salidas[$clave]['personas'] ?? 0)
                + (in_array($r['estado'], self::ESTADOS_VIVOS, true) ? (int) $r['adultos'] + (int) $r['ninos'] : 0);
        }

        uasort($salidas, static fn ($a, $b) => ($a['hora'] ?? '99') <=> ($b['hora'] ?? '99'));

        return $salidas;
    }

    /** Actividades contratadas por una reserva de alojamiento. */
    public function deReserva(int $reservaId): array
    {
        return $this->detallada()
            ->where('experiencia_reservas.reserva_id', $reservaId)
            ->orderBy('experiencia_reservas.fecha')
            ->orderBy('experiencia_reservas.hora')
            ->findAll();
    }

    /** Plazas ya tomadas en una salida concreta (niños incluidos: también ocupan sitio). */
    public function ocupacion(int $experienciaId, string $fecha, ?string $hora): int
    {
        $consulta = $this->select('adultos, ninos')
            ->where('experiencia_id', $experienciaId)
            ->where('fecha', $fecha)
            ->whereIn('estado', self::ESTADOS_VIVOS);

        if ($hora !== null) {
            $consulta->where('hora', $hora);
        }

        $total = 0;
        foreach ($consulta->findAll() as $f) {
            $total += (int) $f['adultos'] + (int) $f['ninos'];
        }

        return $total;
    }

    /** Plazas libres en una salida. */
    public function plazasLibres(array $experiencia, string $fecha, ?string $hora): int
    {
        return max(0, (int) $experiencia['capacidad'] - $this->ocupacion((int) $experiencia['id'], $fecha, $hora));
    }

    /** Lo pendiente de atender: solicitudes por confirmar. */
    public function porConfirmar(): array
    {
        return $this->detallada()
            ->where('experiencia_reservas.estado', 'solicitada')
            ->where('experiencia_reservas.fecha >=', date('Y-m-d'))
            ->orderBy('experiencia_reservas.fecha')
            ->findAll();
    }

    /** Nombre de quien va, sea huésped alojado o cliente de paso. */
    public static function quien(array $r): string
    {
        if ($r['h_nombre'] !== null) {
            return trim($r['h_nombre'] . ' ' . $r['h_apellidos']);
        }

        return trim((string) $r['cliente_nombre']) ?: 'Sin nombre';
    }
}
