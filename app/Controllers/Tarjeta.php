<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TarjetaModel;
use App\Models\TipoTarjetaModel;

/**
 * Lo que ve el titular al escanear su propia tarjeta.
 *
 * **Pública y sin sesión, pero enseña muy poco a propósito.** Un QR se
 * fotografía sin querer: si esta pantalla mostrara el saldo y el nombre,
 * cualquiera que pasara al lado sabría cuánto lleva encima esa persona.
 *
 * Sin PIN solo se ve que la tarjeta existe y se puede recargar. **El saldo y
 * los movimientos solo con el PIN**, y con freno de intentos: seis dígitos se
 * adivinan en un rato si se puede probar sin límite.
 */
class Tarjeta extends BaseController
{
    public function ver(string $codigo)
    {
        $tarjeta = (new TarjetaModel())->porCodigo($codigo);

        if ($tarjeta === null || $tarjeta['estado'] === 'anulada') {
            return view('publico/tarjeta', [
                'hotel'   => config('Hotel'),
                'tarjeta' => null,
                'saldo'   => null,
                'error'   => null,
            ]);
        }

        return view('publico/tarjeta', [
            'hotel'   => config('Hotel'),
            'tarjeta' => $tarjeta,
            'tipo'    => (new TipoTarjetaModel())->find($tarjeta['tipo_id']),
            'saldo'   => null,
            'error'   => null,
        ]);
    }

    /** Con el PIN correcto sí se enseña el saldo. */
    public function consultar(string $codigo)
    {
        $tarjetas = new TarjetaModel();
        $tarjeta  = $tarjetas->porCodigo($codigo);

        if ($tarjeta === null || $tarjeta['estado'] === 'anulada') {
            return redirect()->to('tarjeta/' . $codigo);
        }

        // Freno por tarjeta, no por IP: quien prueba PINes a mano cambia de red
        // antes que de tarjeta, y así el freno protege a la tarjeta concreta.
        $freno = service('throttler');

        if ($freno->check('tarjeta-pin-' . $tarjeta['id'], 5, MINUTE * 10) === false) {
            return view('publico/tarjeta', [
                'hotel'   => config('Hotel'),
                'tarjeta' => $tarjeta,
                'tipo'    => (new TipoTarjetaModel())->find($tarjeta['tipo_id']),
                'saldo'   => null,
                'error'   => 'Demasiados intentos. Prueba dentro de un rato o pregunta en recepción.',
            ]);
        }

        $pin = trim((string) $this->request->getPost('pin'));

        if ($tarjeta['pin_hash'] === null || ! password_verify($pin, (string) $tarjeta['pin_hash'])) {
            return view('publico/tarjeta', [
                'hotel'   => config('Hotel'),
                'tarjeta' => $tarjeta,
                'tipo'    => (new TipoTarjetaModel())->find($tarjeta['tipo_id']),
                'saldo'   => null,
                'error'   => $tarjeta['pin_hash'] === null
                    ? 'Esta tarjeta no tiene PIN. Pregunta el saldo en recepción.'
                    : 'El PIN no es correcto.',
            ]);
        }

        return view('publico/tarjeta', [
            'hotel'   => config('Hotel'),
            'tarjeta' => $tarjeta,
            'tipo'    => (new TipoTarjetaModel())->find($tarjeta['tipo_id']),
            'saldo'   => (float) $tarjeta['saldo'],
            'movimientos' => (new \App\Models\TarjetaMovimientoModel())->deTarjeta((int) $tarjeta['id'], 15),
            'tipos_mov'   => \App\Models\TarjetaMovimientoModel::TIPOS,
            'error'   => null,
        ]);
    }
}
