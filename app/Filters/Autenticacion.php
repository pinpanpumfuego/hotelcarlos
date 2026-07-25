<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/** Exige sesión iniciada para acceder al panel. */
class Autenticacion implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->has('usuario_id')) {
            session()->set('url_destino', current_url());

            return redirect()->to('login')->with('error', 'Inicia sesión para continuar.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
