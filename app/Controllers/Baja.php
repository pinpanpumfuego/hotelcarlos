<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Mensajero;

/**
 * La baja con un clic desde el enlace del correo.
 *
 * Pública y sin sesión a propósito: pedirle una contraseña a alguien que quiere
 * dejar de recibir correos es exactamente lo que la ley llama poner trabas. Un
 * clic, y ya está.
 *
 * El token no da acceso a nada más: solo sirve para retirar **la finalidad de
 * ese correo**. Quien lo intercepte no puede leer la ficha de nadie, y lo peor
 * que puede hacer es dar de baja de las ofertas a alguien que no lo pidió —
 * molesto, pero no un daño.
 */
class Baja extends BaseController
{
    public function darse(string $token)
    {
        $envio = (new Mensajero())->darDeBaja($token, $this->request->getIPAddress());

        return view('publico/baja', [
            'hotel' => config('Hotel'),
            'ok'    => $envio !== null,
            'que'   => $envio !== null ? $envio['finalidad'] : null,
        ]);
    }

    /**
     * El píxel que dice si se abrió el correo.
     *
     * Solo cuenta si el huésped autorizó el perfilado; si no, devuelve la misma
     * imagen y no apunta nada. La imagen se devuelve siempre para que un correo
     * no salga con un hueco roto en medio.
     */
    public function pixel(string $token)
    {
        (new Mensajero())->anotarApertura($token);

        // Un GIF transparente de 1×1, el más pequeño que existe
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true);

        return $this->response
            ->setHeader('Content-Type', 'image/gif')
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->setBody((string) $gif);
    }
}
