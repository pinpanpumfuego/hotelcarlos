<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Cuentas de empresa, agencia y OTA.
 *
 * **El saldo se calcula sumando los movimientos, nunca se guarda.** Un saldo en
 * columna se desincroniza el primer día que algo falle a mitad, y a partir de
 * ahí nadie sabe cuál de los dos números es el bueno — ni el contador ni la
 * empresa que llama a reclamar.
 */
class CuentaCarteraModel extends Model
{
    protected $table         = 'cuentas_cartera';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'codigo', 'nombre', 'tipo', 'nit', 'contacto', 'email', 'telefono', 'direccion',
        'cupo', 'plazo_dias', 'descuento_pct', 'estado', 'motivo_bloqueo', 'notas',
    ];

    public const TIPOS = [
        'empresa'    => 'Empresa',
        'agencia'    => 'Agencia de viajes',
        'ota'        => 'Portal de reservas',
        'particular' => 'Particular con crédito',
    ];

    public const ESTADOS = [
        'activa'    => 'Activa',
        'bloqueada' => 'Bloqueada',
        'cerrada'   => 'Cerrada',
    ];

    protected $validationRules = [
        'nombre' => 'required|max_length[200]',
    ];

    protected $validationMessages = [
        'nombre' => ['required' => 'Ponle el nombre de la empresa.'],
    ];

    public function siguienteCodigo(): string
    {
        $ultimo = $this->like('codigo', 'CT-', 'after')->orderBy('id', 'DESC')->first();
        $numero = 1;

        if ($ultimo !== null && preg_match('/-(\d+)$/', $ultimo['codigo'], $m) === 1) {
            $numero = (int) $m[1] + 1;
        }

        do {
            $codigo = 'CT-' . str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
            $numero++;
        } while ($this->where('codigo', $codigo)->countAllResults() > 0);

        return $codigo;
    }

    /**
     * El saldo de una cuenta: lo que debe menos lo que ha pagado.
     *
     * Positivo es deuda. Negativo es saldo a favor, que pasa cuando una empresa
     * paga de más o cuando se le abona una nota de crédito.
     */
    public function saldo(int $cuentaId): float
    {
        $fila = db_connect()->query(
            "SELECT COALESCE(SUM(CASE WHEN tipo IN ('cargo','ajuste') THEN valor ELSE -valor END), 0) AS saldo
             FROM cartera_movimientos WHERE cuenta_id = ?",
            [$cuentaId]
        )->getRowArray();

        return round((float) ($fila['saldo'] ?? 0), 2);
    }

    /**
     * Cuánto puede cargar todavía.
     *
     * Con cupo en 0 no hay límite, que es una decisión consciente y no un
     * descuido: por eso devuelve `null` y no un número enorme.
     */
    public function disponible(array $cuenta): ?float
    {
        $cupo = (float) $cuenta['cupo'];

        if ($cupo <= 0) {
            return null;
        }

        return round($cupo - $this->saldo((int) $cuenta['id']), 2);
    }

    /**
     * Lo vencido, repartido por antigüedad.
     *
     * Es el informe que de verdad se usa para llamar por teléfono: no es lo
     * mismo deber algo desde hace una semana que desde hace cuatro meses.
     *
     * @return array{corriente: float, d30: float, d60: float, d90: float, mas90: float, total: float}
     */
    public function antiguedad(int $cuentaId): array
    {
        $filas = db_connect()->query(
            "SELECT vence_en, valor FROM cartera_movimientos
             WHERE cuenta_id = ? AND tipo IN ('cargo','ajuste') AND vence_en IS NOT NULL",
            [$cuentaId]
        )->getResultArray();

        $tramos = ['corriente' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'mas90' => 0.0];
        $hoy    = strtotime(date('Y-m-d'));

        foreach ($filas as $f) {
            $dias  = (int) floor(($hoy - strtotime($f['vence_en'])) / 86400);
            $valor = (float) $f['valor'];

            $tramo = match (true) {
                $dias <= 0  => 'corriente',
                $dias <= 30 => 'd30',
                $dias <= 60 => 'd60',
                $dias <= 90 => 'd90',
                default     => 'mas90',
            };

            $tramos[$tramo] += $valor;
        }

        // Los abonos se descuentan del tramo más viejo primero, que es como se
        // imputa un pago en la práctica: nadie paga la factura de este mes
        // dejando pendiente la del año pasado.
        $abonado = (float) (db_connect()->query(
            "SELECT COALESCE(SUM(valor), 0) AS t FROM cartera_movimientos
             WHERE cuenta_id = ? AND tipo IN ('abono','nota_credito')",
            [$cuentaId]
        )->getRowArray()['t'] ?? 0);

        foreach (['mas90', 'd90', 'd60', 'd30', 'corriente'] as $tramo) {
            if ($abonado <= 0) {
                break;
            }

            $resta          = min($abonado, $tramos[$tramo]);
            $tramos[$tramo] -= $resta;
            $abonado        -= $resta;
        }

        foreach ($tramos as $k => $v) {
            $tramos[$k] = round($v, 2);
        }

        $tramos['total'] = round(array_sum($tramos), 2);

        return $tramos;
    }

    /** Las cuentas con su saldo, para el listado. */
    public function listar(bool $soloActivas = false): array
    {
        $q = $this->orderBy('nombre');

        if ($soloActivas) {
            $q->where('estado', 'activa');
        }

        $cuentas = $q->findAll();

        foreach ($cuentas as &$c) {
            $c['saldo']      = $this->saldo((int) $c['id']);
            $c['disponible'] = $this->disponible($c);
            $c['vencido']    = $this->vencido((int) $c['id']);
        }

        return $cuentas;
    }

    /** Lo que ya pasó de plazo. */
    public function vencido(int $cuentaId): float
    {
        $a = $this->antiguedad($cuentaId);

        return round($a['d30'] + $a['d60'] + $a['d90'] + $a['mas90'], 2);
    }

    /** Cuentas con algo vencido, para el aviso del panel. */
    public function conVencidos(): array
    {
        $lista = [];

        foreach ($this->where('estado !=', 'cerrada')->findAll() as $c) {
            $vencido = $this->vencido((int) $c['id']);

            if ($vencido > 0.01) {
                $c['vencido'] = $vencido;
                $c['saldo']   = $this->saldo((int) $c['id']);
                $lista[]      = $c;
            }
        }

        usort($lista, static fn (array $a, array $b): int => $b['vencido'] <=> $a['vencido']);

        return $lista;
    }
}
