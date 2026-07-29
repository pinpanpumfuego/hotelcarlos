<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Obras;

/**
 * La puerta del cartel de obras.
 *
 * Vive fuera del filtro que tapa la web: si estuviera dentro, la propia
 * pantalla para dar la clave estaría tapada por el cartel.
 */
class ObrasAcceso extends BaseController
{
    public function entrar()
    {
        $cartel = new Obras();
        $r      = $cartel->entrar((string) $this->request->getPost('clave'));

        if (! $r['ok']) {
            return $this->response
                ->setStatusCode(503)
                ->setBody(view('publico/obras', [
                    'hotel' => config('Hotel'),
                    'datos' => $cartel->textos(),
                    'error' => $r['motivo'],
                ]));
        }

        return redirect()->to('/')->with('ok', 'Adelante.');
    }

    /** Devolver el pase: para comprobar cómo se ve el cartel de verdad. */
    public function salir()
    {
        $this->response->deleteCookie(Obras::COOKIE);

        return redirect()->to('/');
    }
}
