<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Tarjetas de saldo personalizadas.
 *
 * El `saldo` se guarda a propósito, al contrario que en la cartera: aquí no es
 * un informe, es una puerta que hay que comprobar y cerrar en el mismo
 * instante. Ver la nota de `App\Libraries\Tarjetas`.
 */
class TarjetaModel extends Model
{
    protected $table         = 'tarjetas';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'tipo_id', 'codigo', 'huesped_id', 'empleado_id', 'cuenta_id', 'titular',
        'pin_hash', 'saldo', 'descuento_pct', 'recarga_mensual', 'ultima_recarga',
        'estado', 'motivo_estado', 'caduca', 'notas', 'usuario_id',
    ];

    public const ESTADOS = [
        'activa'    => 'Activa',
        'congelada' => 'Congelada',
        'anulada'   => 'Anulada',
    ];

    /**
     * Un código corto que se pueda dictar por teléfono y teclear si el QR se
     * borra. Sin las letras y los números que se confunden al oírlos.
     */
    public function siguienteCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

        for ($intento = 0; $intento < 30; $intento++) {
            $codigo = 'TJ-';

            for ($i = 0; $i < 6; $i++) {
                $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }

            if ($this->where('codigo', $codigo)->countAllResults() === 0) {
                return $codigo;
            }
        }

        throw new \RuntimeException('No se pudo generar un código de tarjeta.');
    }

    public function porCodigo(string $codigo): ?array
    {
        return $this->where('codigo', strtoupper(trim($codigo)))->first();
    }

    /** La ficha, con su modalidad y su dueño. */
    public function detalle(int $id): ?array
    {
        return $this->consulta()->where('tarjetas.id', $id)->first();
    }

    /**
     * @param array{estado?: string, tipo_id?: int, buscar?: string} $filtros
     */
    public function listar(array $filtros = []): array
    {
        $q = $this->consulta();

        if (($filtros['estado'] ?? '') !== '') {
            $q->where('tarjetas.estado', $filtros['estado']);
        }

        if (($filtros['tipo_id'] ?? 0) > 0) {
            $q->where('tarjetas.tipo_id', $filtros['tipo_id']);
        }

        if (trim((string) ($filtros['buscar'] ?? '')) !== '') {
            $texto = trim((string) $filtros['buscar']);
            $q->groupStart()
                ->like('tarjetas.codigo', $texto)
                ->orLike('tarjetas.titular', $texto)
                ->groupEnd();
        }

        return $q->orderBy('tarjetas.created_at', 'DESC')->findAll(300);
    }

    /**
     * Todo el saldo que hay ahí fuera.
     *
     * **Es una deuda del hotel, no un ingreso.** El ingreso ocurre cuando se
     * gasta. Si una recarga contara como venta, el mes que una empresa cargue
     * cinco millones parecería un mes récord y el mes que se lo gasten
     * parecería vacío.
     */
    public function saldoEnCirculacion(): float
    {
        $fila = $this->selectSum('saldo')->where('estado !=', 'anulada')->first();

        return round((float) ($fila['saldo'] ?? 0), 2);
    }

    /** Las que toca recargar este mes y aún no se han recargado. */
    public function pendientesDeRecarga(): array
    {
        return $this->consulta()
            ->where('tarjetas.estado', 'activa')
            ->where('tarjetas.recarga_mensual >', 0)
            ->groupStart()
                ->where('tarjetas.ultima_recarga IS NULL')
                ->orWhere('tarjetas.ultima_recarga <', date('Y-m-01'))
            ->groupEnd()
            ->orderBy('tarjetas.titular')
            ->findAll();
    }

    private function consulta()
    {
        return $this->select('tarjetas.*, tipos_tarjeta.nombre AS tipo_nombre, tipos_tarjeta.color,
                              tipos_tarjeta.ambito, tipos_tarjeta.descuento_pct AS tipo_descuento,
                              tipos_tarjeta.bonus_pct, tipos_tarjeta.pin_desde, tipos_tarjeta.acumula,
                              huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos,
                              cuentas_cartera.nombre AS cuenta_nombre')
            ->join('tipos_tarjeta', 'tipos_tarjeta.id = tarjetas.tipo_id')
            ->join('huespedes', 'huespedes.id = tarjetas.huesped_id', 'left')
            ->join('cuentas_cartera', 'cuentas_cartera.id = tarjetas.cuenta_id', 'left');
    }
}
