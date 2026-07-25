<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Devuelve el token CSRF renovado en la cabecera de las respuestas JSON,
 * para que el TPV pueda seguir enviando peticiones sin recargar la página.
 */
class TokenJson implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-CSRF-TOKEN', csrf_hash());

        return $response;
    }
}
