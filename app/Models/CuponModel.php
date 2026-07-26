<?php

namespace App\Models;

use CodeIgniter\Model;

class CuponModel extends Model
{
    protected $table         = 'cupones';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'codigo', 'descripcion', 'tipo', 'valor', 'ambito',
        'desde', 'hasta', 'importe_minimo', 'descuento_maximo',
        'limite_usos', 'limite_por_huesped', 'usos',
        'en_web', 'en_recepcion', 'en_tpv', 'activo',
    ];
    protected $useTimestamps = true;

    public const TIPOS = [
        'porcentaje' => 'Porcentaje del importe',
        'valor'      => 'Descuento fijo en pesos',
    ];

    public const AMBITOS = [
        'alojamiento' => 'Solo alojamiento',
        'restaurante' => 'Solo restaurante',
        'todo'        => 'Alojamiento y restaurante',
    ];

    public const CANALES = [
        'web'       => 'Reserva en línea',
        'recepcion' => 'Recepción',
        'tpv'       => 'TPV del restaurante',
    ];

    protected $validationRules = [
        'codigo' => 'required|min_length[3]|max_length[40]|is_unique[cupones.codigo,id,{id}]',
        'tipo'   => 'required|in_list[porcentaje,valor]',
        'valor'  => 'required|numeric|greater_than[0]',
        'ambito' => 'required|in_list[alojamiento,restaurante,todo]',
    ];

    protected $validationMessages = [
        'codigo' => [
            'required'   => 'El cupón necesita un código.',
            'min_length' => 'El código debe tener al menos 3 caracteres.',
            'is_unique'  => 'Ya existe un cupón con ese código.',
        ],
        'valor' => [
            'required'     => 'Indica cuánto descuenta.',
            'numeric'      => 'El descuento debe ser un número.',
            'greater_than' => 'El descuento debe ser mayor que cero.',
        ],
    ];

    /** Normaliza el código: mayúsculas, sin espacios ni acentos raros. */
    public static function normalizar(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', trim($codigo)));
    }

    /** Listado con el número de canjes reales. */
    public function listado(): array
    {
        return $this->select('cupones.*, COUNT(cupon_usos.id) AS canjes,
                              COALESCE(SUM(cupon_usos.descuento), 0) AS descontado')
            ->join('cupon_usos', 'cupon_usos.cupon_id = cupones.id', 'left')
            ->groupBy('cupones.id')
            ->orderBy('cupones.activo', 'DESC')
            ->orderBy('cupones.id', 'DESC')
            ->findAll();
    }

    /**
     * Comprueba si un cupón se puede aplicar y calcula el descuento.
     *
     * @param string   $canal     web | recepcion | tpv
     * @param string   $ambito    alojamiento | restaurante
     * @param float    $base      importe sobre el que se descuenta
     *
     * @return array{ok: bool, mensaje: string, cupon: array|null, descuento: float}
     */
    public function validar(string $codigo, string $canal, string $ambito, float $base, ?int $huespedId = null): array
    {
        $no = static fn (string $mensaje) => ['ok' => false, 'mensaje' => $mensaje, 'cupon' => null, 'descuento' => 0.0];

        $codigo = self::normalizar($codigo);
        if ($codigo === '') {
            return $no('Escribe el código del cupón.');
        }

        $cupon = $this->where('codigo', $codigo)->first();
        if ($cupon === null) {
            return $no('Ese código no existe.');
        }
        if ((int) $cupon['activo'] !== 1) {
            return $no('Ese cupón está desactivado.');
        }

        $campoCanal = ['web' => 'en_web', 'recepcion' => 'en_recepcion', 'tpv' => 'en_tpv'][$canal] ?? 'en_recepcion';
        if ((int) $cupon[$campoCanal] !== 1) {
            return $no('Ese cupón no se puede usar aquí (' . (self::CANALES[$canal] ?? $canal) . ').');
        }

        if ($cupon['ambito'] !== 'todo' && $cupon['ambito'] !== $ambito) {
            return $no('Ese cupón es ' . mb_strtolower(self::AMBITOS[$cupon['ambito']]) . '.');
        }

        $hoy = date('Y-m-d');
        if ($cupon['desde'] !== null && $hoy < $cupon['desde']) {
            return $no('Ese cupón todavía no está vigente: empieza el ' . date('d/m/Y', strtotime($cupon['desde'])) . '.');
        }
        if ($cupon['hasta'] !== null && $hoy > $cupon['hasta']) {
            return $no('Ese cupón caducó el ' . date('d/m/Y', strtotime($cupon['hasta'])) . '.');
        }

        if ($cupon['importe_minimo'] !== null && $base < (float) $cupon['importe_minimo']) {
            return $no('Ese cupón pide un importe mínimo de $' . number_format((float) $cupon['importe_minimo'], 0, ',', '.') . '.');
        }

        if ($cupon['limite_usos'] !== null && (int) $cupon['usos'] >= (int) $cupon['limite_usos']) {
            return $no('Ese cupón ya agotó sus usos.');
        }

        if ($cupon['limite_por_huesped'] !== null && $huespedId !== null) {
            $usados = $this->db->table('cupon_usos')
                ->where('cupon_id', $cupon['id'])
                ->where('huesped_id', $huespedId)
                ->countAllResults();

            if ($usados >= (int) $cupon['limite_por_huesped']) {
                return $no('Este huésped ya usó ese cupón el máximo de veces permitido.');
            }
        }

        $descuento = self::calcularDescuento($cupon, $base);
        if ($descuento <= 0) {
            return $no('Ese cupón no descuenta nada sobre este importe.');
        }

        return [
            'ok'        => true,
            'mensaje'   => 'Cupón ' . $cupon['codigo'] . ' aplicado: −$' . number_format($descuento, 0, ',', '.') . '.',
            'cupon'     => $cupon,
            'descuento' => $descuento,
        ];
    }

    /** Descuento en pesos que produce el cupón sobre una base, con su tope. */
    public static function calcularDescuento(array $cupon, float $base): float
    {
        $descuento = $cupon['tipo'] === 'porcentaje'
            ? $base * ((float) $cupon['valor'] / 100)
            : (float) $cupon['valor'];

        if ($cupon['descuento_maximo'] !== null && (float) $cupon['descuento_maximo'] > 0) {
            $descuento = min($descuento, (float) $cupon['descuento_maximo']);
        }

        // Nunca puede dejar la cuenta en negativo
        return round(min($descuento, $base), 2);
    }

    /** Anota el canje y suma al contador. Devuelve el id del uso. */
    public function registrarUso(array $cupon, array $datos): int
    {
        $usos = new CuponUsoModel();
        $id   = (int) $usos->insert(array_merge([
            'cupon_id'   => $cupon['id'],
            'usuario_id' => session()->get('usuario_id'),
        ], $datos));

        $this->builder()->where('id', $cupon['id'])->set('usos', 'usos + 1', false)->update();

        return $id;
    }

    /** Deshace un canje (por ejemplo, al anular una comanda). */
    public function devolverUso(int $cuponId, array $filtro): void
    {
        $usos = new CuponUsoModel();
        $fila = $usos->where(array_merge(['cupon_id' => $cuponId], $filtro))->first();

        if ($fila === null) {
            return;
        }

        $usos->delete($fila['id']);
        $this->builder()->where('id', $cuponId)->where('usos >', 0)->set('usos', 'usos - 1', false)->update();
    }

    /** Etiqueta legible del descuento. */
    public static function textoValor(array $c): string
    {
        return $c['tipo'] === 'porcentaje'
            ? rtrim(rtrim(number_format((float) $c['valor'], 2, ',', '.'), '0'), ',') . ' %'
            : '$' . number_format((float) $c['valor'], 0, ',', '.');
    }

    /** Estado de un cupón para pintarlo en el listado. */
    public static function estado(array $c): array
    {
        $hoy = date('Y-m-d');

        if ((int) $c['activo'] !== 1) {
            return ['secondary', 'Desactivado'];
        }
        if ($c['desde'] !== null && $hoy < $c['desde']) {
            return ['info', 'Aún no empieza'];
        }
        if ($c['hasta'] !== null && $hoy > $c['hasta']) {
            return ['danger', 'Caducado'];
        }
        if ($c['limite_usos'] !== null && (int) $c['usos'] >= (int) $c['limite_usos']) {
            return ['danger', 'Agotado'];
        }

        return ['success', 'Activo'];
    }
}
