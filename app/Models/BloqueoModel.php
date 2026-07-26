<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Fechas ocupadas que vienen de fuera (Booking, Airbnb) o que se bloquean
 * a mano (obra, uso propio de la familia).
 *
 * No son reservas: no tienen huésped, ni folio, ni factura. Solo impiden
 * que el motor venda esa cabaña esas noches.
 */
class BloqueoModel extends Model
{
    protected $table         = 'bloqueos';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'unidad_id', 'conexion_id', 'canal', 'uid', 'resumen',
        'fecha_entrada', 'fecha_salida', 'origen', 'notas', 'usuario_id',
    ];
    protected $useTimestamps = true;

    /**
     * ¿Hay algún bloqueo que se cruce con estas fechas?
     * Misma regla que las reservas: el día de salida no cuenta como ocupado.
     */
    public function unidadBloqueada(int $unidadId, string $entrada, string $salida): bool
    {
        return $this->where('unidad_id', $unidadId)
            ->where('fecha_entrada <', $salida)
            ->where('fecha_salida >', $entrada)
            ->countAllResults() > 0;
    }

    /** Bloqueos que se cruzan con un rango, con el nombre de la cabaña. */
    public function enRango(string $desde, string $hasta, ?int $unidadId = null): array
    {
        $this->select('bloqueos.*, unidades.nombre AS unidad_nombre')
            ->join('unidades', 'unidades.id = bloqueos.unidad_id')
            ->where('bloqueos.fecha_entrada <=', $hasta)
            ->where('bloqueos.fecha_salida >=', $desde);

        if ($unidadId !== null) {
            $this->where('bloqueos.unidad_id', $unidadId);
        }

        return $this->orderBy('bloqueos.fecha_entrada')->findAll();
    }

    /** Los de una cabaña, de hoy en adelante. */
    public function proximosDeUnidad(int $unidadId): array
    {
        return $this->where('unidad_id', $unidadId)
            ->where('fecha_salida >=', date('Y-m-d'))
            ->orderBy('fecha_entrada')
            ->findAll();
    }

    /**
     * Reemplaza los bloqueos de una conexión por los que acaba de traer.
     *
     * Se borra y se vuelve a insertar en vez de ir comparando: es la única
     * forma de que una reserva cancelada en Booking desaparezca de aquí.
     *
     * @param list<array> $eventos
     */
    public function reemplazarDeConexion(int $conexionId, int $unidadId, string $canal, array $eventos): int
    {
        $this->where('conexion_id', $conexionId)->where('origen', 'ical')->delete();

        $guardados = 0;
        $hoy       = date('Y-m-d', strtotime('-1 year'));

        foreach ($eventos as $e) {
            // Lo muy viejo no aporta nada y engorda la tabla
            if ($e['hasta'] < $hoy || $e['desde'] === '' || $e['hasta'] === '') {
                continue;
            }
            if ($e['hasta'] <= $e['desde']) {
                continue;
            }

            $this->insert([
                'unidad_id'     => $unidadId,
                'conexion_id'   => $conexionId,
                'canal'         => $canal,
                'uid'           => $e['uid'],
                'resumen'       => $e['resumen'] !== '' ? $e['resumen'] : null,
                'fecha_entrada' => $e['desde'],
                'fecha_salida'  => $e['hasta'],
                'origen'        => 'ical',
            ]);
            $guardados++;
        }

        return $guardados;
    }

    /**
     * Reservas propias que chocan con un bloqueo recién traído.
     * Es la sobreventa que hay que mirar a la cara cuanto antes.
     */
    public function conflictos(): array
    {
        return $this->db->table('bloqueos')
            ->select('bloqueos.id AS bloqueo_id, bloqueos.canal, bloqueos.fecha_entrada AS b_entrada,
                      bloqueos.fecha_salida AS b_salida, bloqueos.resumen,
                      reservas.id AS reserva_id, reservas.codigo, reservas.fecha_entrada, reservas.fecha_salida,
                      unidades.nombre AS unidad_nombre,
                      huespedes.nombre, huespedes.apellidos')
            ->join('reservas', 'reservas.unidad_id = bloqueos.unidad_id
                    AND reservas.fecha_entrada < bloqueos.fecha_salida
                    AND reservas.fecha_salida > bloqueos.fecha_entrada')
            ->join('unidades', 'unidades.id = bloqueos.unidad_id')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->whereIn('reservas.estado', ['pendiente', 'confirmada', 'checkin'])
            ->orderBy('bloqueos.fecha_entrada')
            ->get()
            ->getResultArray();
    }
}
