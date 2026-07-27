<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Wompi;
use App\Models\FolioModel;
use App\Models\PagoOnlineModel;
use App\Models\ReservaModel;

/**
 * Cobros en línea.
 *
 * **Nada se da por pagado por lo que diga el navegador.** El huésped vuelve de
 * la pasarela con un `id` en la dirección, pero eso solo dice «mira esta
 * transacción». El estado bueno se pregunta siempre a la API con nuestras
 * credenciales. Quien sepa editar una URL no puede regalarse una estancia.
 */
class Pagos extends BaseController
{
    private Wompi $wompi;
    private PagoOnlineModel $pagos;

    public function __construct()
    {
        $this->wompi = new Wompi();
        $this->pagos = new PagoOnlineModel();
    }

    /**
     * Manda al huésped a pagar su reserva.
     *
     * El intento se guarda **antes** de mandarlo: si paga y se le cae el móvil
     * antes de volver, el aviso de la pasarela llega igual y encuentra a quién
     * apuntárselo.
     *
     * @param string $alcance `anticipo` (lo que pide el motor de reservas al
     *                        reservar) o `total` (lo que queda por pagar, que
     *                        es lo que manda recepción con un enlace a mano).
     */
    public function reserva(string $codigo, string $alcance = 'anticipo')
    {
        if (! $this->wompi->activo()) {
            return $this->fin('Los pagos en línea no están disponibles ahora mismo. Escríbenos y lo resolvemos.');
        }

        $reserva = (new ReservaModel())
            ->select('reservas.*, huespedes.nombre, huespedes.apellidos, huespedes.email,
                      huespedes.telefono, huespedes.num_documento')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->where('reservas.codigo', $codigo)
            ->first();

        if ($reserva === null || $reserva['estado'] === 'cancelada') {
            return $this->fin('No encontramos esa reserva.');
        }

        $porCobrar = $this->porCobrarDeReserva($reserva, $alcance === 'total');

        if ($porCobrar <= 0) {
            // Distinguir los dos «cero» importa: uno es «ya no debes nada» y el
            // otro es «el anticipo está cubierto, el resto se paga al llegar».
            // Decirle lo segundo a quien aún debe el 70 % lo confundiría.
            $queda = (new FolioModel())->saldo((int) $reserva['id']);

            return $this->fin(
                $queda > 0.01
                    ? 'Tu anticipo ya está pagado. El resto se abona a tu llegada al alojamiento.'
                    : 'Esta reserva no tiene nada pendiente de pago.',
                true
            );
        }

        // Freno: alguien probando códigos de reserva a ver cuál existe
        if (service('throttler')->check('pago-' . md5($this->request->getIPAddress()), 10, MINUTE) === false) {
            return $this->fin('Demasiados intentos. Espera un minuto.');
        }

        $pago = $this->pagos->abrir('reserva', (int) $reserva['id'], $porCobrar, [
            'ambiente'  => $this->wompi->ambiente(),
            'email'     => $reserva['email'] ?? null,
            'telefono'  => $reserva['telefono'] ?? null,
            'ip'        => $this->request->getIPAddress(),
            // La pasarela cierra el enlace pasado ese rato: un enlace de pago
            // que vive para siempre acaba usándose cuando ya no toca.
            'expira_en' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ]);

        try {
            $enlace = $this->wompi->enlaceCheckout(
                $pago['referencia'],
                $porCobrar,
                site_url('pago/volver'),
                [
                    'email'     => $reserva['email'] ?? null,
                    'nombre'    => trim(($reserva['nombre'] ?? '') . ' ' . ($reserva['apellidos'] ?? '')),
                    'telefono'  => $reserva['telefono'] ?? null,
                    'documento' => $reserva['num_documento'] ?? null,
                ],
                gmdate('c', strtotime('+2 hours'))
            );
        } catch (\Throwable $e) {
            log_message('error', 'Wompi: no se pudo armar el cobro: {m}', ['m' => $e->getMessage()]);

            return $this->fin('No pudimos preparar el pago. Escríbenos y lo resolvemos.');
        }

        return redirect()->to($enlace);
    }

    /**
     * El huésped vuelve de la pasarela.
     *
     * Aquí no se cree nada de lo que trae: se coge el id y se le pregunta a la
     * API cómo quedó de verdad.
     */
    public function volver()
    {
        $id = trim((string) $this->request->getGet('id'));

        if ($id === '') {
            return $this->fin('No sabemos de qué pago vienes. Si ya pagaste, escríbenos y lo comprobamos.');
        }

        $transaccion = $this->wompi->transaccion($id);

        if ($transaccion === null) {
            // Que no se pueda consultar no significa que no se haya pagado: el
            // aviso puede llegar después y cuadrarlo solo.
            return $this->fin(
                'Estamos comprobando tu pago con el banco. Si se cobró, lo verás en tu cuenta en unos minutos.',
                true
            );
        }

        $pago = $this->aplicar($transaccion);

        if ($pago === null) {
            return $this->fin('No encontramos ese pago en nuestro sistema. Escríbenos con el comprobante.');
        }

        return view('pagos/resultado', [
            'hotel'  => config('Hotel'),
            'pago'   => $pago,
            'estado' => $pago['estado'],
            'exito'  => $pago['estado'] === 'APPROVED',
        ]);
    }

    /**
     * Aviso de la pasarela.
     *
     * Es la red que recoge los pagos que el navegador no llegó a confirmar.
     * **Se comprueba la firma antes de mirar nada**: sin ella, cualquiera que
     * conozca esta dirección podría declararse un pago aprobado.
     */
    public function aviso()
    {
        $cuerpo = $this->request->getJSON(true);

        if (! is_array($cuerpo)) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false]);
        }

        if (! $this->wompi->avisoValido($cuerpo, $this->request->getHeaderLine('X-Event-Checksum'))) {
            log_message('warning', 'Wompi: aviso con firma que no cuadra desde {ip}', [
                'ip' => $this->request->getIPAddress(),
            ]);

            return $this->response->setStatusCode(401)->setJSON(['ok' => false]);
        }

        $transaccion = $cuerpo['data']['transaction'] ?? null;

        if (is_array($transaccion)) {
            // Aunque el aviso venga firmado, el estado se vuelve a preguntar a
            // la API: la firma dice que el mensaje es auténtico, no que su
            // contenido siga siendo el actual.
            $fresca = $this->wompi->transaccion((string) ($transaccion['id'] ?? ''));
            $this->aplicar($fresca ?? $transaccion);
        }

        // Se contesta 200 siempre que la firma cuadre: si no, Wompi reintenta
        // hasta tres veces en 24 horas y llenaría el registro de duplicados.
        return $this->response->setJSON(['ok' => true]);
    }

    // ── Lo común ────────────────────────────────────────────────────────

    /**
     * Anota el resultado y, si está aprobado, lo lleva a la cuenta.
     *
     * @param array<string, mixed> $transaccion
     */
    private function aplicar(array $transaccion): ?array
    {
        $referencia = (string) ($transaccion['reference'] ?? '');
        $pago       = $referencia !== '' ? $this->pagos->porReferencia($referencia) : null;

        if ($pago === null) {
            log_message('error', 'Wompi: llegó una transacción con referencia desconocida: {r}', ['r' => $referencia]);

            return null;
        }

        $this->pagos->anotarResultado((int) $pago['id'], $transaccion);
        $pago = $this->pagos->find($pago['id']);

        // Solo se apunta en la cuenta una vez. La pasarela puede mandar el
        // mismo aviso varias veces y el huésped puede recargar la vuelta.
        if ($pago['estado'] === 'APPROVED' && ! $this->pagos->yaAplicado($pago)) {
            $this->llevarACuenta($pago);
            $this->pagos->marcarAplicado((int) $pago['id']);
            $pago = $this->pagos->find($pago['id']);
        }

        return $pago;
    }

    /** Mete el cobro donde toque. */
    private function llevarACuenta(array $pago): void
    {
        if ($pago['concepto_tipo'] !== 'reserva') {
            return;
        }

        $reservas = new ReservaModel();
        $reserva  = $reservas->find((int) $pago['concepto_id']);

        if ($reserva === null) {
            return;
        }

        (new FolioModel())->insert([
            'reserva_id' => (int) $reserva['id'],
            'tipo'       => 'pago',
            'concepto'   => 'Pago en línea · ' . ($pago['metodo'] ?? 'Wompi'),
            'valor'      => (float) $pago['valor'],
            'metodo'     => 'wompi',
        ]);

        // Una reserva pendiente que ya ha pagado deja de ser una promesa
        if ($reserva['estado'] === 'pendiente') {
            $reservas->update($reserva['id'], ['estado' => 'confirmada']);
        }
    }

    /** Cuánto falta por cobrar de una reserva, según el anticipo configurado. */
    private function porCobrarDeReserva(array $reserva, bool $todo = false): float
    {
        $folio = new FolioModel();

        if ($todo) {
            // Sin el cargo de alojamiento el folio saldría a cero y parecería
            // que no se debe nada. Es idempotente: si ya está, no toca nada.
            $folio->asegurarCargoAlojamiento($reserva);

            // Aquí se va al saldo del folio y no al total de la reserva: cuando
            // recepción manda el enlace, lo que se debe incluye el minibar, las
            // cenas y las actividades, que no estaban en el precio inicial.
            return max(0, round($folio->saldo((int) $reserva['id']), 2));
        }

        $total = (float) $reserva['total'];

        $pct = (float) (new \App\Models\ConfiguracionModel())->obtener('wompi_anticipo_pct', '0');
        $pct = max(0, min(100, $pct));

        // Con 0 % configurado se cobra la reserva entera.
        $objetivo = $pct > 0 ? round($total * $pct / 100, 2) : $total;

        $yaPagado = $folio->totalPagado((int) $reserva['id']);

        return max(0, round($objetivo - $yaPagado, 2));
    }

    private function fin(string $mensaje, bool $exito = false)
    {
        return view('pagos/resultado', [
            'hotel'   => config('Hotel'),
            'pago'    => null,
            'estado'  => $exito ? 'PENDING' : 'ERROR',
            'exito'   => false,
            'mensaje' => $mensaje,
        ]);
    }
}
