<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Obras as Cartel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Tapa la web pública mientras el hotel no está listo para recibir visitas.
 *
 * **Solo cubre la web de cara al público y el motor de reservas**, que son las
 * rutas a las que está enganchado. A propósito no toca nada de lo que ya está
 * funcionando y no se puede parar: el portal del huésped, los enlaces de
 * registro que ya se enviaron, el calendario que lee Booking, el terminal de
 * fichaje, el panel ni —sobre todo— el aviso que manda la pasarela de pagos.
 * Un cartel de obras que corta un webhook deja cobros en el aire.
 *
 * Responde **503 y no 200**: le dice a Google «vuelve más tarde» en vez de
 * indexar una página en construcción como si fuera la portada del hotel.
 */
class Obras implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $cartel = new Cartel();

        if (! $cartel->activo() || $cartel->tienePase()) {
            return null;
        }

        return service('response')
            ->setStatusCode(503)
            // Sin esto, algunos buscadores tratan un 503 largo como si la web
            // hubiera desaparecido para siempre.
            ->setHeader('Retry-After', '86400')
            ->setBody(view('publico/obras', [
                'hotel' => config('Hotel'),
                'datos' => $cartel->textos(),
                'error' => null,
            ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
