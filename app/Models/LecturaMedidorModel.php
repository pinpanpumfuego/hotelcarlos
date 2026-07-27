<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;
use RuntimeException;

/**
 * Las lecturas de los contadores.
 *
 * **Se guarda lo que marca el contador, no el consumo.** El consumo se calcula
 * restando la lectura anterior. Si se guardara el consumo, un error de tecleo
 * se arrastraría para siempre sin forma de detectarlo; con la lectura, el
 * número está ahí en el contador y siempre se puede ir a mirar.
 */
class LecturaMedidorModel extends Model
{
    protected $table         = 'lecturas_medidor';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = ['medidor_id', 'fecha', 'lectura', 'consumo', 'dias', 'sospechosa', 'nota', 'usuario_id'];

    /**
     * Apunta una lectura y calcula lo consumido desde la anterior.
     *
     * @throws RuntimeException si ya hay una de ese día
     */
    public function apuntar(int $medidorId, float $lectura, string $fecha, ?int $usuarioId = null, ?string $nota = null): int
    {
        $medidor = (new MedidorModel())->find($medidorId);

        if ($medidor === null) {
            throw new RuntimeException('Ese medidor no existe.');
        }

        if ($fecha > date('Y-m-d')) {
            throw new RuntimeException('No se puede apuntar una lectura del futuro.');
        }

        // Dos del mismo día partirían el consumo en dos trozos sin sentido
        if ($this->where('medidor_id', $medidorId)->where('fecha', $fecha)->countAllResults() > 0) {
            throw new RuntimeException('Ya hay una lectura de ese día. Corrígela en vez de añadir otra.');
        }

        $anterior = $this->where('medidor_id', $medidorId)
            ->where('fecha <', $fecha)
            ->orderBy('fecha', 'DESC')
            ->first();

        $consumo    = null;
        $dias       = null;
        $sospechosa = 0;

        if ($anterior !== null) {
            $dias  = max(1, (int) round((strtotime($fecha) - strtotime($anterior['fecha'])) / 86400));
            $bruto = $lectura - (float) $anterior['lectura'];

            if ((int) $medidor['acumulativo'] === 1) {
                if ($bruto < 0) {
                    // Se tecleó mal, el contador dio la vuelta o lo cambiaron.
                    // Las tres hay que mirarlas, así que se marca en vez de
                    // guardar un consumo negativo que ensuciaría los informes.
                    $sospechosa = 1;
                } else {
                    $consumo = round($bruto, 3);
                }
            } else {
                // Un tanque baja al consumirse: el consumo es la bajada. Si
                // sube es que lo llenaron, y eso no es consumo.
                $consumo = $bruto < 0 ? round(-$bruto, 3) : null;
            }
        }

        $this->insert([
            'medidor_id' => $medidorId,
            'fecha'      => $fecha,
            'lectura'    => $lectura,
            'consumo'    => $consumo,
            'dias'       => $dias,
            'sospechosa' => $sospechosa,
            'nota'       => $nota !== null ? mb_substr($nota, 0, 200) : null,
            'usuario_id' => $usuarioId,
        ]);

        return (int) $this->getInsertID();
    }

    public function ultima(int $medidorId): ?array
    {
        return $this->where('medidor_id', $medidorId)->orderBy('fecha', 'DESC')->first();
    }

    public function historial(int $medidorId, int $cuantas = 30): array
    {
        return $this->select('lecturas_medidor.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = lecturas_medidor.usuario_id', 'left')
            ->where('medidor_id', $medidorId)
            ->orderBy('fecha', 'DESC')
            ->findAll($cuantas);
    }

    /**
     * Consumo por día de las últimas lecturas.
     *
     * Se pide por día y no por lectura porque los intervalos no son iguales:
     * comparar «120 en once días» con «95 en siete» a ojo no dice nada.
     */
    public function consumoDiarioReciente(int $medidorId, int $ultimas = 3): ?float
    {
        $filas = $this->where('medidor_id', $medidorId)
            ->where('consumo IS NOT NULL')
            ->where('dias >', 0)
            ->orderBy('fecha', 'DESC')
            ->findAll($ultimas);

        if ($filas === []) {
            return null;
        }

        $consumo = 0.0;
        $dias    = 0;

        foreach ($filas as $f) {
            $consumo += (float) $f['consumo'];
            $dias    += (int) $f['dias'];
        }

        return $dias > 0 ? round($consumo / $dias, 3) : null;
    }

    /** Lo consumido en un rango, para el informe mensual. */
    public function consumoEntre(int $medidorId, string $desde, string $hasta): float
    {
        $fila = $this->selectSum('consumo')
            ->where('medidor_id', $medidorId)
            ->where('fecha >=', $desde)
            ->where('fecha <=', $hasta)
            ->first();

        return round((float) ($fila['consumo'] ?? 0), 3);
    }
}
