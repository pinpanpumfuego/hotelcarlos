<?php

namespace App\Models;

use CodeIgniter\Model;

class CajaTurnoModel extends Model
{
    protected $table         = 'caja_turnos';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'punto_id', 'usuario_id', 'base_inicial', 'apertura', 'cierre', 'cerro_id',
        'efectivo_contado', 'retiros', 'esperado', 'diferencia', 'justificacion', 'notas',
    ];
    protected $useTimestamps = true;

    /**
     * El turno abierto de un punto.
     *
     * Sin punto devuelve el primero que encuentre, que es lo que hacía antes de
     * que hubiera puntos. Se conserva para no romper lo que ya lo llamaba, pero
     * lo correcto de aquí en adelante es preguntar por un punto: con dos cajas
     * abiertas, «el turno abierto» no significa nada.
     */
    public function abierto(?int $puntoId = null): ?array
    {
        $q = $this->select('caja_turnos.*, usuarios.nombre AS usuario_nombre, puntos_caja.nombre AS punto_nombre,
                            puntos_caja.tolerancia, puntos_caja.exige_denominaciones')
            ->join('usuarios', 'usuarios.id = caja_turnos.usuario_id', 'left')
            ->join('puntos_caja', 'puntos_caja.id = caja_turnos.punto_id', 'left')
            ->where('caja_turnos.cierre IS NULL');

        if ($puntoId !== null) {
            $q->where('caja_turnos.punto_id', $puntoId);
        }

        return $q->first();
    }

    /** Todos los turnos abiertos ahora mismo, uno por punto como mucho. */
    public function abiertos(): array
    {
        return $this->select('caja_turnos.*, usuarios.nombre AS usuario_nombre, puntos_caja.nombre AS punto_nombre,
                              puntos_caja.tolerancia, puntos_caja.exige_denominaciones, puntos_caja.base_sugerida')
            ->join('usuarios', 'usuarios.id = caja_turnos.usuario_id', 'left')
            ->join('puntos_caja', 'puntos_caja.id = caja_turnos.punto_id', 'left')
            ->where('caja_turnos.cierre IS NULL')
            ->orderBy('puntos_caja.nombre')
            ->findAll();
    }

    /** Ingresos y egresos en efectivo del turno. */
    public function totales(int $turnoId): array
    {
        $fila = db_connect()->table('caja_movimientos')
            ->select("SUM(CASE WHEN tipo = 'ingreso' THEN valor ELSE 0 END) AS ingresos,
                      SUM(CASE WHEN tipo = 'egreso' THEN valor ELSE 0 END) AS egresos")
            ->where('turno_id', $turnoId)
            ->get()->getRowArray();

        return [
            'ingresos' => (float) ($fila['ingresos'] ?? 0),
            'egresos'  => (float) ($fila['egresos'] ?? 0),
        ];
    }

    /** Últimos turnos cerrados. */
    public function historial(int $limite = 10, ?int $puntoId = null): array
    {
        $q = $this->select('caja_turnos.*, usuarios.nombre AS usuario_nombre,
                            cierra.nombre AS cerro_nombre, puntos_caja.nombre AS punto_nombre')
            ->join('usuarios', 'usuarios.id = caja_turnos.usuario_id', 'left')
            ->join('usuarios AS cierra', 'cierra.id = caja_turnos.cerro_id', 'left')
            ->join('puntos_caja', 'puntos_caja.id = caja_turnos.punto_id', 'left')
            ->where('caja_turnos.cierre IS NOT NULL');

        if ($puntoId !== null) {
            $q->where('caja_turnos.punto_id', $puntoId);
        }

        return $q->orderBy('caja_turnos.cierre', 'DESC')->findAll($limite);
    }

    /**
     * Los descuadres que hay que mirar.
     *
     * Sale en el panel. Un faltante de treinta mil pesos no avisa solo: se
     * queda en un turno cerrado hace tres semanas que nadie va a volver a
     * abrir.
     */
    public function descuadres(float $desde = 1000, int $dias = 30): array
    {
        return $this->select('caja_turnos.*, usuarios.nombre AS usuario_nombre, puntos_caja.nombre AS punto_nombre')
            ->join('usuarios', 'usuarios.id = caja_turnos.usuario_id', 'left')
            ->join('puntos_caja', 'puntos_caja.id = caja_turnos.punto_id', 'left')
            ->where('caja_turnos.cierre IS NOT NULL')
            ->where('caja_turnos.cierre >=', date('Y-m-d', strtotime('-' . $dias . ' days')))
            ->where('ABS(caja_turnos.diferencia) >=', $desde)
            ->orderBy('caja_turnos.cierre', 'DESC')
            ->findAll(50);
    }
}
