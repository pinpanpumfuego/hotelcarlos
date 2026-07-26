<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Bono regalo: saldo prepagado.
 *
 * Contablemente NO es un descuento: el dinero entró cuando se vendió el bono.
 * Al canjearlo solo se cancela la deuda que el hotel tenía con el portador,
 * por eso se registra como una forma de pago y nunca como una rebaja.
 */
class BonoModel extends Model
{
    protected $table         = 'bonos';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'codigo', 'importe_inicial', 'saldo',
        'comprador_nombre', 'comprador_email', 'comprador_telefono',
        'beneficiario', 'mensaje', 'caduca', 'forma_pago', 'estado', 'notas', 'usuario_id',
    ];
    protected $useTimestamps = true;

    public const FORMAS_PAGO = [
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'wompi'         => 'Wompi',
        'otro'          => 'Otro',
    ];

    protected $validationRules = [
        'importe_inicial'  => 'required|numeric|greater_than[0]',
        'comprador_nombre' => 'required|min_length[3]|max_length[150]',
        'comprador_email'  => 'permit_empty|valid_email',
    ];

    protected $validationMessages = [
        'importe_inicial' => [
            'required'     => 'Indica el importe del bono.',
            'greater_than' => 'El importe debe ser mayor que cero.',
        ],
        'comprador_nombre' => ['required' => 'Escribe quién compra el bono.'],
        'comprador_email'  => ['valid_email' => 'Ese correo no parece válido.'],
    ];

    /** Código legible y difícil de adivinar: BR-A3F2-91KD. */
    public function generarCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin I, O, 0 ni 1: se confunden al leerlos

        do {
            $bloques = [];
            for ($b = 0; $b < 2; $b++) {
                $bloque = '';
                for ($i = 0; $i < 4; $i++) {
                    $bloque .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                }
                $bloques[] = $bloque;
            }
            $codigo = 'BR-' . implode('-', $bloques);
        } while ($this->where('codigo', $codigo)->countAllResults() > 0);

        return $codigo;
    }

    public static function normalizar(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($codigo)));
    }

    /** Listado con quien lo emitió. */
    public function listado(array $filtros = []): array
    {
        $this->select('bonos.*, usuarios.nombre AS usuario_nombre')
            ->join('usuarios', 'usuarios.id = bonos.usuario_id', 'left');

        if (($filtros['estado'] ?? '') === 'con_saldo') {
            $this->where('bonos.estado', 'activo')->where('bonos.saldo >', 0);
        } elseif (($filtros['estado'] ?? '') === 'agotados') {
            $this->where('bonos.saldo <=', 0);
        } elseif (($filtros['estado'] ?? '') === 'anulados') {
            $this->where('bonos.estado', 'anulado');
        }

        if (! empty($filtros['buscar'])) {
            $this->groupStart()
                ->like('bonos.codigo', $filtros['buscar'])
                ->orLike('bonos.comprador_nombre', $filtros['buscar'])
                ->orLike('bonos.beneficiario', $filtros['buscar'])
                ->groupEnd();
        }

        return $this->orderBy('bonos.id', 'DESC')->findAll();
    }

    /**
     * Comprueba que un bono se puede canjear y cuánto se puede usar ahora.
     *
     * @return array{ok: bool, mensaje: string, bono: array|null, importe: float}
     */
    public function validar(string $codigo, float $pendiente): array
    {
        $no = static fn (string $mensaje) => ['ok' => false, 'mensaje' => $mensaje, 'bono' => null, 'importe' => 0.0];

        $codigo = self::normalizar($codigo);
        if ($codigo === '') {
            return $no('Escribe el código del bono.');
        }

        $bono = $this->where('codigo', $codigo)->first();
        if ($bono === null) {
            return $no('Ese bono no existe.');
        }
        if ($bono['estado'] === 'anulado') {
            return $no('Ese bono está anulado.');
        }
        if ((float) $bono['saldo'] <= 0) {
            return $no('Ese bono ya no tiene saldo.');
        }
        if ($bono['caduca'] !== null && date('Y-m-d') > $bono['caduca']) {
            return $no('Ese bono caducó el ' . date('d/m/Y', strtotime($bono['caduca'])) . '.');
        }
        if ($pendiente <= 0) {
            return $no('No hay nada pendiente de cobrar.');
        }

        // Se usa lo menor entre el saldo del bono y lo que falta por pagar
        $importe = round(min((float) $bono['saldo'], $pendiente), 2);

        return [
            'ok'      => true,
            'mensaje' => 'Bono ' . $bono['codigo'] . ': se aplican $' . number_format($importe, 0, ',', '.')
                . ' de los $' . number_format((float) $bono['saldo'], 0, ',', '.') . ' que tenía.',
            'bono'    => $bono,
            'importe' => $importe,
        ];
    }

    /** Descuenta saldo y deja el movimiento anotado. */
    public function consumir(array $bono, float $importe, array $contexto = []): void
    {
        $importe = round(min($importe, (float) $bono['saldo']), 2);
        if ($importe <= 0) {
            return;
        }

        $saldo = round((float) $bono['saldo'] - $importe, 2);
        $this->update($bono['id'], ['saldo' => $saldo]);

        (new BonoMovimientoModel())->insert(array_merge([
            'bono_id'       => $bono['id'],
            'tipo'          => 'consumo',
            'valor'         => $importe,
            'saldo_despues' => $saldo,
            'usuario_id'    => session()->get('usuario_id'),
        ], $contexto));
    }

    /** Devuelve saldo al bono (anulación de una comanda, corrección…). */
    public function devolver(int $bonoId, float $importe, string $concepto, array $contexto = []): void
    {
        $bono = $this->find($bonoId);
        if ($bono === null || $importe <= 0) {
            return;
        }

        $saldo = round(min((float) $bono['importe_inicial'], (float) $bono['saldo'] + $importe), 2);
        $this->update($bonoId, ['saldo' => $saldo]);

        (new BonoMovimientoModel())->insert(array_merge([
            'bono_id'       => $bonoId,
            'tipo'          => 'devolucion',
            'valor'         => $importe,
            'saldo_despues' => $saldo,
            'concepto'      => $concepto,
            'usuario_id'    => session()->get('usuario_id'),
        ], $contexto));
    }

    /** Estado del bono para pintarlo. */
    public static function estado(array $b): array
    {
        if ($b['estado'] === 'anulado') {
            return ['secondary', 'Anulado'];
        }
        if ((float) $b['saldo'] <= 0) {
            return ['dark', 'Agotado'];
        }
        if ($b['caduca'] !== null && date('Y-m-d') > $b['caduca']) {
            return ['danger', 'Caducado'];
        }
        if ((float) $b['saldo'] < (float) $b['importe_inicial']) {
            return ['warning', 'Usado en parte'];
        }

        return ['success', 'Sin usar'];
    }

    /** Saldo total pendiente de todos los bonos: es dinero que el hotel debe. */
    public function saldoVivo(): float
    {
        $fila = $this->selectSum('saldo')->where('estado', 'activo')->first();

        return (float) ($fila['saldo'] ?? 0);
    }
}
