<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\TarjetaModel;
use App\Models\TarjetaMovimientoModel;
use App\Models\TipoTarjetaModel;
use RuntimeException;

/**
 * Tarjetas de saldo: emitir, cargar y cobrar.
 *
 * **Aquí hay dinero de verdad, y eso cambia dos cosas respecto al resto del
 * sistema:**
 *
 * 1. **El descuento se descuenta del saldo, no del cobro.** Una tarjeta con
 *    20 % sobre una cuenta de 100.000 gasta 80.000 de saldo, y los otros
 *    20.000 quedan apuntados como descuento en la venta. Si simplemente se
 *    cobrara menos, la venta quedaría registrada por 80.000 y nadie sabría
 *    nunca cuánto se está regalando en tarjetas.
 *
 * 2. **El saldo se descuenta con un UPDATE condicional.** Leer el saldo,
 *    comprobarlo y después restarlo deja una rendija: dos cobros a la vez leen
 *    el mismo saldo y los dos pasan. `WHERE saldo >= ?` lo resuelve la base de
 *    datos en un solo paso, y si no afectó a ninguna fila es que no había
 *    saldo. Con dinero, esa rendija se acaba encontrando.
 */
class Tarjetas
{
    private TarjetaModel $tarjetas;
    private TarjetaMovimientoModel $movimientos;

    public function __construct()
    {
        $this->tarjetas    = new TarjetaModel();
        $this->movimientos = new TarjetaMovimientoModel();
    }

    // ── Emitir ──────────────────────────────────────────────────────────

    /**
     * @param array{huesped_id?: ?int, empleado_id?: ?int, cuenta_id?: ?int,
     *              pin?: ?string, descuento_pct?: ?float, recarga_mensual?: float,
     *              notas?: ?string, usuario_id?: ?int} $extra
     */
    public function emitir(int $tipoId, string $titular, array $extra = []): int
    {
        $tipo = (new TipoTarjetaModel())->find($tipoId);

        if ($tipo === null || (int) $tipo['activo'] !== 1) {
            throw new RuntimeException('Esa modalidad de tarjeta no existe o está apagada.');
        }

        if (trim($titular) === '') {
            throw new RuntimeException('Una tarjeta personalizada necesita un nombre impreso.');
        }

        $pin = trim((string) ($extra['pin'] ?? ''));

        if ($pin !== '' && ! preg_match('/^\d{4,6}$/', $pin)) {
            throw new RuntimeException('El PIN son entre 4 y 6 dígitos.');
        }

        $caduca = null;

        if ($tipo['caduca_meses'] !== null && (int) $tipo['caduca_meses'] > 0) {
            $caduca = date('Y-m-d', strtotime('+' . (int) $tipo['caduca_meses'] . ' months'));
        }

        $this->tarjetas->insert([
            'tipo_id'     => $tipoId,
            'codigo'      => $this->tarjetas->siguienteCodigo(),
            'titular'     => mb_substr(trim($titular), 0, 150),
            'huesped_id'  => $extra['huesped_id'] ?? null,
            'empleado_id' => $extra['empleado_id'] ?? null,
            'cuenta_id'   => $extra['cuenta_id'] ?? null,
            // Cifrado, nunca en claro: es lo único que separa una tarjeta
            // perdida de un saldo perdido.
            'pin_hash'    => $pin !== '' ? password_hash($pin, PASSWORD_DEFAULT) : null,
            'saldo'       => 0,
            'descuento_pct'   => $extra['descuento_pct'] ?? null,
            'recarga_mensual' => max(0, (float) ($extra['recarga_mensual'] ?? 0)),
            'estado'      => 'activa',
            'caduca'      => $caduca,
            'notas'       => $extra['notas'] ?? null,
            'usuario_id'  => $extra['usuario_id'] ?? null,
        ]);

        return (int) $this->tarjetas->getInsertID();
    }

    // ── Cargar ──────────────────────────────────────────────────────────

    /**
     * Mete saldo en la tarjeta.
     *
     * El bonus se apunta como un movimiento **aparte** del dinero cargado: son
     * dos cosas distintas contablemente. Lo cargado es una deuda del hotel con
     * el cliente; el bonus es un descuento que se regala por adelantado, y
     * mezclarlos haría imposible saber cuánto está costando el programa.
     *
     * @return array{cargado: float, bonus: float, saldo: float}
     */
    public function cargar(
        int $tarjetaId,
        float $valor,
        string $origen = 'efectivo',
        ?string $referencia = null,
        ?int $usuarioId = null,
        ?int $pagoOnlineId = null,
    ): array {
        if ($valor <= 0) {
            throw new RuntimeException('La carga tiene que ser mayor que cero.');
        }

        $tarjeta = $this->exigir($tarjetaId);
        $tipo    = (new TipoTarjetaModel())->find($tarjeta['tipo_id']);

        if ($tarjeta['estado'] === 'anulada') {
            throw new RuntimeException('Esa tarjeta está anulada: no admite cargas.');
        }

        if ((int) $tipo['recargable'] !== 1 && (float) $tarjeta['saldo'] > 0) {
            throw new RuntimeException('Esta modalidad no se recarga: emite una tarjeta nueva.');
        }

        $db = db_connect();
        $db->transStart();

        // Si el tipo no acumula, la carga REEMPLAZA el saldo: eso es un
        // auxilio mensual, no un monedero. Se apunta el ajuste para que el
        // titular pueda ver qué pasó con lo que no gastó.
        if ((int) $tipo['acumula'] !== 1 && (float) $tarjeta['saldo'] > 0) {
            $sobrante = (float) $tarjeta['saldo'];

            $this->apuntar($tarjetaId, 'ajuste', -$sobrante, 0, [
                'concepto'   => 'Saldo no usado del periodo anterior',
                'usuario_id' => $usuarioId,
            ]);

            $this->tarjetas->update($tarjetaId, ['saldo' => 0]);
            $tarjeta['saldo'] = 0;
        }

        $saldo = round((float) $tarjeta['saldo'] + $valor, 2);

        $this->tarjetas->update($tarjetaId, [
            'saldo'          => $saldo,
            'ultima_recarga' => date('Y-m-d'),
        ]);

        $this->apuntar($tarjetaId, 'carga', $valor, $saldo, [
            'origen'         => $origen,
            'concepto'       => 'Recarga',
            'referencia'     => $referencia,
            'usuario_id'     => $usuarioId,
            'pago_online_id' => $pagoOnlineId,
        ]);

        // El regalo por recargar, si lo hay
        $bonus = 0.0;
        $pct   = (float) $tipo['bonus_pct'];

        if ($pct > 0) {
            $bonus = round($valor * $pct / 100, 2);
            $saldo = round($saldo + $bonus, 2);

            $this->tarjetas->update($tarjetaId, ['saldo' => $saldo]);

            $this->apuntar($tarjetaId, 'bonus', $bonus, $saldo, [
                'origen'     => 'cortesia',
                'concepto'   => 'Regalo del ' . rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ',') . ' % por recargar',
                'usuario_id' => $usuarioId,
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RuntimeException('No se pudo cargar la tarjeta. No se ha cambiado nada.');
        }

        return ['cargado' => round($valor, 2), 'bonus' => $bonus, 'saldo' => $saldo];
    }

    // ── Cobrar ──────────────────────────────────────────────────────────

    /**
     * Qué pasaría al pagar un importe con esta tarjeta, sin tocar nada.
     *
     * Sirve para enseñárselo a quien cobra antes de confirmar: cuánto se
     * descuenta, cuánto sale del saldo y si hace falta PIN.
     *
     * @return array{ok: bool, motivo: ?string, descuento: float, a_pagar: float,
     *               cubre: float, falta: float, pide_pin: bool, tarjeta: ?array}
     */
    public function simular(string $codigo, float $importe, string $ambito = 'todo'): array
    {
        $no = static fn (string $motivo): array => [
            'ok' => false, 'motivo' => $motivo, 'descuento' => 0.0, 'a_pagar' => 0.0,
            'cubre' => 0.0, 'falta' => 0.0, 'pide_pin' => false, 'tarjeta' => null,
        ];

        $tarjeta = $this->tarjetas->porCodigo($codigo);

        if ($tarjeta === null) {
            return $no('No existe ninguna tarjeta con ese código.');
        }

        if ($tarjeta['estado'] === 'congelada') {
            return $no('La tarjeta está congelada' . ($tarjeta['motivo_estado'] ? ': ' . $tarjeta['motivo_estado'] : '.'));
        }

        if ($tarjeta['estado'] === 'anulada') {
            return $no('Esa tarjeta está anulada.');
        }

        if ($tarjeta['caduca'] !== null && $tarjeta['caduca'] < date('Y-m-d')) {
            return $no('La tarjeta caducó el ' . date('d/m/Y', strtotime($tarjeta['caduca'])) . '.');
        }

        $tipo = (new TipoTarjetaModel())->find($tarjeta['tipo_id']);

        // El ámbito limita dónde vale: una tarjeta del personal para el
        // restaurante no puede pagar una estancia.
        if ($tipo['ambito'] !== 'todo' && $ambito !== 'todo' && $tipo['ambito'] !== $ambito) {
            return $no('Esta tarjeta solo vale en ' . $tipo['ambito'] . '.');
        }

        if ($importe <= 0) {
            return $no('No hay nada que cobrar.');
        }

        // El descuento propio de la tarjeta pisa el de su modalidad
        $pct = $tarjeta['descuento_pct'] !== null
            ? (float) $tarjeta['descuento_pct']
            : (float) $tipo['descuento_pct'];

        $descuento = round($importe * max(0, min(100, $pct)) / 100, 2);
        $aPagar    = round($importe - $descuento, 2);

        $saldo = (float) $tarjeta['saldo'];

        if ($saldo <= 0) {
            return $no('La tarjeta no tiene saldo.');
        }

        // Se cubre hasta donde llegue: el resto se cobra por otro medio, que es
        // lo normal cuando la cuenta pasa del saldo.
        $cubre = min($saldo, $aPagar);
        $falta = round($aPagar - $cubre, 2);

        return [
            'ok'        => true,
            'motivo'    => null,
            'descuento' => $descuento,
            'a_pagar'   => $aPagar,
            'cubre'     => round($cubre, 2),
            'falta'     => $falta,
            // El PIN se pide sobre lo que de verdad sale de la tarjeta
            'pide_pin'  => $tarjeta['pin_hash'] !== null && $cubre >= (float) $tipo['pin_desde'],
            'tarjeta'   => $tarjeta,
        ];
    }

    /**
     * Cobra de verdad.
     *
     * @param array{pin?: ?string, reserva_id?: ?int, comanda_id?: ?int,
     *              usuario_id?: ?int, concepto?: ?string} $contexto
     *
     * @return array{descuento: float, cobrado: float, falta: float, saldo: float}
     */
    public function cobrar(string $codigo, float $importe, string $ambito = 'todo', array $contexto = []): array
    {
        $r = $this->simular($codigo, $importe, $ambito);

        if (! $r['ok']) {
            throw new RuntimeException((string) $r['motivo']);
        }

        $tarjeta = $r['tarjeta'];

        if ($r['pide_pin']) {
            $pin = trim((string) ($contexto['pin'] ?? ''));

            if ($pin === '' || ! password_verify($pin, (string) $tarjeta['pin_hash'])) {
                throw new RuntimeException('El PIN no es correcto.');
            }
        }

        // ── El descuento atómico ────────────────────────────────────────
        //
        // Leer el saldo, comprobarlo y después restarlo deja una rendija: dos
        // cobros a la vez leen lo mismo y los dos pasan. Así lo resuelve la
        // base de datos en un solo paso.
        $db = db_connect();
        $db->transStart();

        $db->query(
            'UPDATE tarjetas SET saldo = saldo - ?, updated_at = ? WHERE id = ? AND saldo >= ?',
            [$r['cubre'], date('Y-m-d H:i:s'), $tarjeta['id'], $r['cubre']]
        );

        if ($db->affectedRows() === 0) {
            $db->transRollback();

            throw new RuntimeException('El saldo cambió mientras se cobraba. Vuelve a intentarlo.');
        }

        $saldo = round((float) $tarjeta['saldo'] - $r['cubre'], 2);

        // El apunte va dentro de la misma transacción que el descuento: si se
        // restara el saldo y no quedara escrito por qué, el titular vería
        // desaparecer dinero sin explicación y nadie podría reconstruirlo.
        $this->apuntar((int) $tarjeta['id'], 'consumo', $r['cubre'], $saldo, [
            'concepto'   => $contexto['concepto'] ?? 'Consumo',
            'reserva_id' => $contexto['reserva_id'] ?? null,
            'comanda_id' => $contexto['comanda_id'] ?? null,
            'usuario_id' => $contexto['usuario_id'] ?? null,
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RuntimeException('No se pudo cobrar. No se ha tocado el saldo.');
        }

        return [
            'descuento' => $r['descuento'],
            'cobrado'   => $r['cubre'],
            'falta'     => $r['falta'],
            'saldo'     => $saldo,
        ];
    }

    /** Devuelve saldo a la tarjeta: una anulación, un plato devuelto. */
    public function devolver(int $tarjetaId, float $valor, string $concepto, ?int $usuarioId = null): float
    {
        if ($valor <= 0) {
            throw new RuntimeException('La devolución tiene que ser mayor que cero.');
        }

        $tarjeta = $this->exigir($tarjetaId);
        $saldo   = round((float) $tarjeta['saldo'] + $valor, 2);

        $this->tarjetas->update($tarjetaId, ['saldo' => $saldo]);
        $this->apuntar($tarjetaId, 'devolucion', $valor, $saldo, [
            'concepto'   => $concepto,
            'usuario_id' => $usuarioId,
        ]);

        return $saldo;
    }

    // ── Estado ──────────────────────────────────────────────────────────

    public function cambiarEstado(int $tarjetaId, string $estado, string $motivo = '', ?int $usuarioId = null): void
    {
        if (! in_array($estado, ['activa', 'congelada', 'anulada'], true)) {
            throw new RuntimeException('Ese estado no existe.');
        }

        $tarjeta = $this->exigir($tarjetaId);

        // Congelar sin decir por qué deja a quien atienda mañana sin saber si
        // puede descongelarla.
        if ($estado !== 'activa' && trim($motivo) === '') {
            throw new RuntimeException('Di por qué: quien atienda mañana necesita saberlo.');
        }

        $this->tarjetas->update($tarjetaId, [
            'estado'        => $estado,
            'motivo_estado' => $estado !== 'activa' ? mb_substr(trim($motivo), 0, 200) : null,
        ]);

        // Anular con saldo dentro es quedarse con dinero de alguien. Se apunta
        // el ajuste para que quede escrito qué pasó con él.
        if ($estado === 'anulada' && (float) $tarjeta['saldo'] > 0) {
            $this->apuntar($tarjetaId, 'ajuste', -(float) $tarjeta['saldo'], 0, [
                'concepto'   => 'Saldo retirado al anular: ' . mb_substr(trim($motivo), 0, 100),
                'usuario_id' => $usuarioId,
            ]);

            $this->tarjetas->update($tarjetaId, ['saldo' => 0]);
        }
    }

    public function cambiarPin(int $tarjetaId, string $pin): void
    {
        $this->exigir($tarjetaId);

        if ($pin !== '' && ! preg_match('/^\d{4,6}$/', $pin)) {
            throw new RuntimeException('El PIN son entre 4 y 6 dígitos.');
        }

        $this->tarjetas->update($tarjetaId, [
            'pin_hash' => $pin !== '' ? password_hash($pin, PASSWORD_DEFAULT) : null,
        ]);
    }

    /**
     * Comprueba que el saldo guardado cuadra con los movimientos.
     *
     * El saldo se guarda porque hace falta para descontarlo de forma atómica,
     * pero eso abre la puerta a que un día se separe de su historia. Esto lo
     * caza: sale en la pantalla y en las pruebas.
     *
     * @return list<array{tarjeta: array, guardado: float, calculado: float}>
     */
    public function descuadres(): array
    {
        $filas = db_connect()->query(
            "SELECT t.id, t.codigo, t.titular, t.saldo AS guardado,
                    COALESCE((SELECT SUM(CASE WHEN m.tipo IN ('consumo', 'caducidad') THEN -m.valor
                                              ELSE m.valor END)
                              FROM tarjeta_movimientos m WHERE m.tarjeta_id = t.id), 0) AS calculado
             FROM tarjetas t"
        )->getResultArray();

        $malas = [];

        foreach ($filas as $f) {
            if (abs((float) $f['guardado'] - (float) $f['calculado']) > 0.01) {
                $malas[] = [
                    'tarjeta'   => $f,
                    'guardado'  => round((float) $f['guardado'], 2),
                    'calculado' => round((float) $f['calculado'], 2),
                ];
            }
        }

        return $malas;
    }

    // ── Lo de dentro ────────────────────────────────────────────────────

    private function apuntar(int $tarjetaId, string $tipo, float $valor, float $saldoDespues, array $extra): void
    {
        $this->movimientos->insert([
            'tarjeta_id'     => $tarjetaId,
            'tipo'           => $tipo,
            'origen'         => $extra['origen'] ?? null,
            'valor'          => round($valor, 2),
            'saldo_despues'  => round($saldoDespues, 2),
            'concepto'       => mb_substr((string) ($extra['concepto'] ?? '—'), 0, 150),
            'reserva_id'     => $extra['reserva_id'] ?? null,
            'comanda_id'     => $extra['comanda_id'] ?? null,
            'pago_online_id' => $extra['pago_online_id'] ?? null,
            'referencia'     => isset($extra['referencia']) ? mb_substr((string) $extra['referencia'], 0, 80) : null,
            'usuario_id'     => $extra['usuario_id'] ?? null,
        ]);
    }

    private function exigir(int $tarjetaId): array
    {
        $tarjeta = $this->tarjetas->find($tarjetaId);

        if ($tarjeta === null) {
            throw new RuntimeException('Esa tarjeta no existe.');
        }

        return $tarjeta;
    }
}
