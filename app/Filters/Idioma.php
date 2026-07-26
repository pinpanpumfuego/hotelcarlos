<?php

namespace App\Filters;

use App\Libraries\Traductor;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Fija el idioma de la página según el prefijo de la dirección.
 *
 * Las rutas traducidas llevan el idioma escrito tal cual (`/en/…`, `/fr/…`)
 * en vez del comodín `{locale}` de CodeIgniter, porque ese comodín acepta
 * cualquier segmento y se tragaba `/login` y `/panel`. Como el prefijo es
 * literal, el idioma se pasa aquí como argumento del filtro.
 */
class Idioma implements FilterInterface
{
    public function before(RequestInterface $request, $argumentos = null)
    {
        $idioma = Traductor::valido($argumentos[0] ?? null);
        $request->setLocale($idioma);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $argumentos = null)
    {
        return null;
    }
}
