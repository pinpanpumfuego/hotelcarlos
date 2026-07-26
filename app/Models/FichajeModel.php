<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Control de jornada.
 *
 * Un fichaje nunca se borra ni se reescribe: si hay un error se anula dejando
 * el motivo y quién lo hizo. Eso es lo que convierte el registro en una prueba
 * y no en una hoja de cálculo.
 */
class FichajeModel extends Model
{
    protected $table         = 'fichajes';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'empleado_id', 'tipo', 'marcado_en', 'origen', 'foto',
        'latitud', 'longitud', 'precision_m', 'distancia_m',
        'ip', 'observacion', 'editado_por', 'editado_en', 'anulado', 'motivo',
    ];
    protected $useTimestamps = true;

    public const TIPOS = [
        'entrada'      => 'Entrada',
        'salida'       => 'Salida',
        'pausa_inicio' => 'Inicio de pausa',
        'pausa_fin'    => 'Fin de pausa',
    ];

    public const ICONOS = [
        'entrada'      => 'bi-box-arrow-in-right',
        'salida'       => 'bi-box-arrow-right',
        'pausa_inicio' => 'bi-pause-circle',
        'pausa_fin'    => 'bi-play-circle',
    ];

    /** Último fichaje válido de un empleado. */
    public function ultimo(int $empleadoId): ?array
    {
        return $this->where('empleado_id', $empleadoId)
            ->where('anulado', 0)
            ->orderBy('marcado_en', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Qué le toca fichar ahora a este empleado.
     * Evita que alguien marque dos entradas seguidas por despiste.
     */
    public function siguienteTipo(int $empleadoId): string
    {
        $ultimo = $this->ultimo($empleadoId);

        if ($ultimo === null) {
            return 'entrada';
        }

        return match ($ultimo['tipo']) {
            'entrada', 'pausa_fin' => 'salida',
            'pausa_inicio'         => 'pausa_fin',
            default                => 'entrada',
        };
    }

    /** ¿Está dentro ahora mismo? */
    public function estado(int $empleadoId): string
    {
        $ultimo = $this->ultimo($empleadoId);

        if ($ultimo === null || $ultimo['tipo'] === 'salida') {
            return 'fuera';
        }

        return $ultimo['tipo'] === 'pausa_inicio' ? 'pausa' : 'dentro';
    }

    /** Acciones que tienen sentido ofrecerle ahora. */
    public function accionesPosibles(int $empleadoId): array
    {
        return match ($this->estado($empleadoId)) {
            'fuera' => ['entrada'],
            'pausa' => ['pausa_fin', 'salida'],
            default => ['pausa_inicio', 'salida'],
        };
    }

    /** Fichajes de un empleado en un rango, en orden cronológico. */
    public function delEmpleado(int $empleadoId, string $desde, string $hasta): array
    {
        return $this->where('empleado_id', $empleadoId)
            ->where('DATE(marcado_en) >=', $desde)
            ->where('DATE(marcado_en) <=', $hasta)
            ->orderBy('marcado_en')
            ->orderBy('id')
            ->findAll();
    }

    /** Fichajes de todos, con el nombre del empleado, para el control de gerencia. */
    public function listado(string $desde, string $hasta, ?int $empleadoId = null): array
    {
        $this->select('fichajes.*, empleados.nombre, empleados.apellidos, empleados.cargo,
                       usuarios.nombre AS editor')
            ->join('empleados', 'empleados.id = fichajes.empleado_id')
            ->join('usuarios', 'usuarios.id = fichajes.editado_por', 'left')
            ->where('DATE(fichajes.marcado_en) >=', $desde)
            ->where('DATE(fichajes.marcado_en) <=', $hasta);

        if ($empleadoId !== null) {
            $this->where('fichajes.empleado_id', $empleadoId);
        }

        return $this->orderBy('fichajes.marcado_en', 'DESC')->orderBy('fichajes.id', 'DESC')->findAll();
    }

    /**
     * Reconstruye las jornadas de un empleado emparejando entradas con salidas.
     *
     * Devuelve una lista por día con sus tramos, los minutos trabajados, los de
     * pausa y si quedó algo abierto (una entrada sin su salida).
     */
    public function jornadas(int $empleadoId, string $desde, string $hasta): array
    {
        $fichajes = $this->delEmpleado($empleadoId, $desde, $hasta);

        $dias = [];
        foreach ($fichajes as $f) {
            if ((int) $f['anulado'] === 1) {
                continue;
            }
            $dias[substr($f['marcado_en'], 0, 10)][] = $f;
        }

        $resultado = [];

        foreach ($dias as $dia => $marcas) {
            $tramos    = [];
            $minutos   = 0;
            $pausa     = 0;
            $abierta   = false;
            $entrada   = null;
            $pausaIni  = null;

            foreach ($marcas as $m) {
                $hora = strtotime($m['marcado_en']);

                switch ($m['tipo']) {
                    case 'entrada':
                        $entrada = $hora;
                        $abierta = true;
                        break;

                    case 'salida':
                        if ($entrada !== null) {
                            $minutos += (int) round(($hora - $entrada) / 60);
                            $tramos[] = ['desde' => $entrada, 'hasta' => $hora];
                            $entrada  = null;
                            $abierta  = false;
                        }
                        break;

                    case 'pausa_inicio':
                        $pausaIni = $hora;
                        break;

                    case 'pausa_fin':
                        if ($pausaIni !== null) {
                            $pausa   += (int) round(($hora - $pausaIni) / 60);
                            $pausaIni = null;
                        }
                        break;
                }
            }

            $resultado[$dia] = [
                'fecha'    => $dia,
                'marcas'   => $marcas,
                'tramos'   => $tramos,
                'minutos'  => max(0, $minutos - $pausa),
                'pausa'    => $pausa,
                'abierta'  => $abierta,
                'primera'  => $marcas[0]['marcado_en'] ?? null,
                'ultima'   => end($marcas)['marcado_en'] ?: null,
            ];
        }

        ksort($resultado);

        return $resultado;
    }

    /** Resumen de horas por empleado en un periodo, para nóminas. */
    public function resumen(string $desde, string $hasta): array
    {
        $empleados = (new EmpleadoModel())->where('activo', 1)->orderBy('apellidos')->findAll();

        $resumen = [];
        foreach ($empleados as $e) {
            $jornadas = $this->jornadas((int) $e['id'], $desde, $hasta);

            $minutos   = 0;
            $pausas    = 0;
            $abiertas  = 0;
            foreach ($jornadas as $j) {
                $minutos += $j['minutos'];
                $pausas  += $j['pausa'];
                if ($j['abierta']) {
                    $abiertas++;
                }
            }

            $resumen[] = [
                'empleado' => $e,
                'dias'     => count($jornadas),
                'minutos'  => $minutos,
                'pausas'   => $pausas,
                'abiertas' => $abiertas,
                'estado'   => $this->estado((int) $e['id']),
            ];
        }

        return $resumen;
    }

    /** Quién está dentro ahora mismo. */
    public function presentes(): array
    {
        $empleados = (new EmpleadoModel())->where('activo', 1)->orderBy('nombre')->findAll();

        $dentro = [];
        foreach ($empleados as $e) {
            $estado = $this->estado((int) $e['id']);
            if ($estado !== 'fuera') {
                $ultimo   = $this->ultimo((int) $e['id']);
                $dentro[] = [
                    'empleado' => $e,
                    'estado'   => $estado,
                    'desde'    => $ultimo['marcado_en'] ?? null,
                ];
            }
        }

        return $dentro;
    }

    /** «7 h 45 min» a partir de minutos. */
    public static function horas(int $minutos): string
    {
        if ($minutos <= 0) {
            return '—';
        }

        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        if ($h === 0) {
            return $m . ' min';
        }

        return $h . ' h' . ($m > 0 ? ' ' . $m . ' min' : '');
    }
}
