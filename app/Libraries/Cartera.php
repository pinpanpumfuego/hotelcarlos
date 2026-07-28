<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\CarteraMovimientoModel;
use App\Models\CuentaCarteraModel;
use App\Models\FolioModel;
use App\Models\ReservaModel;
use RuntimeException;

/**
 * Cobrar a crédito: cerrar una reserva contra la cuenta de una empresa.
 *
 * **La cartera no duplica el folio.** La reserva sigue teniendo su folio con
 * todos sus consumos; lo que cambia es quién paga. Al cerrarla contra la
 * cuenta, en el folio queda un pago con medio «cartera» —de forma que el folio
 * cuadra a cero, que es lo que espera recepción— y en la cuenta de la empresa
 * queda un cargo con su fecha de vencimiento.
 *
 * Si la deuda se copiara sin más, el día que alguien añadiera un cargo al folio
 * la cartera dejaría de cuadrar, y a partir de ahí ninguno de los dos números
 * sirve para nada.
 */
class Cartera
{
    private CuentaCarteraModel $cuentas;
    private CarteraMovimientoModel $movimientos;

    public function __construct()
    {
        $this->cuentas     = new CuentaCarteraModel();
        $this->movimientos = new CarteraMovimientoModel();
    }

    /**
     * ¿Se le puede cargar algo a esta cuenta?
     *
     * @return array{puede: bool, motivo: ?string}
     */
    public function puedeCargar(int $cuentaId, float $valor): array
    {
        $cuenta = $this->cuentas->find($cuentaId);

        if ($cuenta === null) {
            return ['puede' => false, 'motivo' => 'Esa cuenta no existe.'];
        }

        if ($cuenta['estado'] !== 'activa') {
            return [
                'puede'  => false,
                'motivo' => 'La cuenta está ' . $cuenta['estado']
                    . ($cuenta['motivo_bloqueo'] ? ': ' . $cuenta['motivo_bloqueo'] : '.'),
            ];
        }

        $disponible = $this->cuentas->disponible($cuenta);

        // `null` es cupo sin límite, que es distinto de cupo cero
        if ($disponible !== null && $valor > $disponible) {
            return [
                'puede'  => false,
                'motivo' => sprintf(
                    'Se pasa del cupo. Le quedan $%s de $%s.',
                    number_format(max(0, $disponible), 0, ',', '.'),
                    number_format((float) $cuenta['cupo'], 0, ',', '.')
                ),
            ];
        }

        return ['puede' => true, 'motivo' => null];
    }

    /**
     * Cierra el folio de una reserva contra la cuenta de la empresa.
     *
     * @return int El id del cargo en la cuenta
     */
    public function cargarReserva(int $reservaId, int $cuentaId, ?int $usuarioId = null): int
    {
        $reservas = new ReservaModel();
        $reserva  = $reservas->find($reservaId);

        if ($reserva === null) {
            throw new RuntimeException('Esa reserva no existe.');
        }

        $folio = new FolioModel();
        $folio->asegurarCargoAlojamiento($reserva);
        $saldo = round($folio->saldo($reservaId), 2);

        if ($saldo <= 0) {
            throw new RuntimeException('Esa reserva no tiene nada pendiente: no hay qué cargar.');
        }

        $permiso = $this->puedeCargar($cuentaId, $saldo);

        if (! $permiso['puede']) {
            throw new RuntimeException((string) $permiso['motivo']);
        }

        if ($this->movimientos->where('reserva_id', $reservaId)->where('tipo', 'cargo')->countAllResults() > 0) {
            throw new RuntimeException('Esa reserva ya se cargó a una cuenta.');
        }

        $cuenta = $this->cuentas->find($cuentaId);
        $db     = db_connect();
        $db->transStart();

        // En el folio queda un pago con medio «cartera»: así el folio cuadra a
        // cero, que es lo que espera recepción al hacer el check-out, y sigue
        // habiendo un solo sitio donde ver lo que consumió el huésped.
        $folio->insert([
            'reserva_id' => $reservaId,
            'tipo'       => 'pago',
            'concepto'   => 'A cuenta de ' . $cuenta['nombre'],
            'valor'      => $saldo,
            'metodo'     => 'otro',
            'usuario_id' => $usuarioId,
        ]);

        // El plazo cuenta desde hoy, que es cuando nace la deuda. Contarlo
        // desde la entrada haría que una estancia larga naciera medio vencida.
        $vence = date('Y-m-d', strtotime('+' . max(0, (int) $cuenta['plazo_dias']) . ' days'));

        $this->movimientos->insert([
            'cuenta_id'  => $cuentaId,
            'tipo'       => 'cargo',
            'concepto'   => 'Reserva ' . $reserva['codigo'],
            'valor'      => $saldo,
            'reserva_id' => $reservaId,
            'fecha'      => date('Y-m-d'),
            'vence_en'   => $vence,
            'usuario_id' => $usuarioId,
        ]);

        $movimientoId = (int) $this->movimientos->getInsertID();

        $reservas->update($reservaId, ['cuenta_id' => $cuentaId]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RuntimeException('No se pudo cargar a la cuenta. No se ha cambiado nada.');
        }

        return $movimientoId;
    }

    /**
     * La empresa paga.
     *
     * Un abono no va contra una factura concreta: va contra la cuenta. Un pago
     * de cinco millones puede saldar cuatro reservas y dejar saldo a favor, y
     * obligar a repartirlo a mano sería inventar un trabajo que nadie hace.
     */
    public function abonar(
        int $cuentaId,
        float $valor,
        string $medio,
        ?string $referencia = null,
        ?int $usuarioId = null,
        ?string $concepto = null,
    ): int {
        if ($valor <= 0) {
            throw new RuntimeException('El abono tiene que ser mayor que cero.');
        }

        if ($this->cuentas->find($cuentaId) === null) {
            throw new RuntimeException('Esa cuenta no existe.');
        }

        $this->movimientos->insert([
            'cuenta_id'  => $cuentaId,
            'tipo'       => 'abono',
            'concepto'   => $concepto !== null && trim($concepto) !== ''
                ? mb_substr(trim($concepto), 0, 200)
                : 'Abono a la cuenta',
            'valor'      => round($valor, 2),
            'fecha'      => date('Y-m-d'),
            'medio_pago' => $medio,
            'referencia' => $referencia !== null ? mb_substr(trim($referencia), 0, 80) : null,
            'usuario_id' => $usuarioId,
        ]);

        return (int) $this->movimientos->getInsertID();
    }

    /**
     * Un ajuste a mano, siempre con motivo.
     *
     * Sirve para lo que la realidad tiene y el sistema no previó: una
     * penalización, un redondeo, una nota de crédito de un año anterior. Sin
     * motivo escrito no se puede explicar dentro de seis meses.
     */
    public function ajustar(int $cuentaId, float $valor, string $motivo, ?int $usuarioId = null): int
    {
        if (trim($motivo) === '') {
            throw new RuntimeException('Un ajuste sin motivo no se puede explicar después.');
        }

        if (abs($valor) < 0.01) {
            throw new RuntimeException('Un ajuste de cero no ajusta nada.');
        }

        // Positivo sube la deuda; negativo la baja. Se guarda como `ajuste` con
        // valor positivo o como `nota_credito` según el signo, para que los
        // informes por tipo digan algo.
        $this->movimientos->insert([
            'cuenta_id'  => $cuentaId,
            'tipo'       => $valor > 0 ? 'ajuste' : 'nota_credito',
            'concepto'   => mb_substr(trim($motivo), 0, 200),
            'valor'      => round(abs($valor), 2),
            'fecha'      => date('Y-m-d'),
            'vence_en'   => $valor > 0 ? date('Y-m-d') : null,
            'usuario_id' => $usuarioId,
        ]);

        return (int) $this->movimientos->getInsertID();
    }

    /**
     * El estado de cuenta: qué debe, desde cuándo y por qué.
     *
     * @return array<string, mixed>
     */
    public function estadoDeCuenta(int $cuentaId): array
    {
        $cuenta = $this->cuentas->find($cuentaId);

        if ($cuenta === null) {
            throw new RuntimeException('Esa cuenta no existe.');
        }

        return [
            'cuenta'      => $cuenta,
            'saldo'       => $this->cuentas->saldo($cuentaId),
            'disponible'  => $this->cuentas->disponible($cuenta),
            'antiguedad'  => $this->cuentas->antiguedad($cuentaId),
            'movimientos' => $this->movimientos->deCuenta($cuentaId),
        ];
    }

    /** El estado de cuenta en texto, para mandárselo a la empresa. */
    public function comoTexto(int $cuentaId): string
    {
        $e = $this->estadoDeCuenta($cuentaId);
        $d = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');

        $l   = [];
        $l[] = str_repeat('=', 64);
        $l[] = 'ESTADO DE CUENTA';
        $l[] = config('Hotel')->nombre;
        $l[] = $e['cuenta']['nombre'] . ($e['cuenta']['nit'] ? '  ·  NIT ' . $e['cuenta']['nit'] : '');
        $l[] = 'Al ' . date('d/m/Y');
        $l[] = str_repeat('=', 64);
        $l[] = '';
        $l[] = sprintf('%-12s %-34s %12s', 'FECHA', 'CONCEPTO', 'VALOR');
        $l[] = str_repeat('-', 64);

        foreach (array_reverse($e['movimientos']) as $m) {
            $signo = in_array($m['tipo'], ['cargo', 'ajuste'], true) ? '' : '-';

            $l[] = sprintf(
                '%-12s %-34s %12s',
                date('d/m/Y', strtotime($m['fecha'])),
                mb_substr($m['concepto'], 0, 34),
                $signo . $d((float) $m['valor'])
            );
        }

        $l[] = str_repeat('-', 64);
        $l[] = sprintf('%-47s %12s', 'SALDO', $d($e['saldo']));
        $l[] = '';
        $l[] = '— ANTIGÜEDAD DE LA DEUDA —';
        $l[] = sprintf('  Sin vencer      %12s', $d($e['antiguedad']['corriente']));
        $l[] = sprintf('  1 a 30 días     %12s', $d($e['antiguedad']['d30']));
        $l[] = sprintf('  31 a 60 días    %12s', $d($e['antiguedad']['d60']));
        $l[] = sprintf('  61 a 90 días    %12s', $d($e['antiguedad']['d90']));
        $l[] = sprintf('  Más de 90 días  %12s', $d($e['antiguedad']['mas90']));

        if ($e['antiguedad']['mas90'] > 0) {
            $l[] = '';
            $l[] = 'Hay saldo con más de 90 días. Agradecemos su gestión.';
        }

        return implode("\n", $l) . "\n";
    }
}
