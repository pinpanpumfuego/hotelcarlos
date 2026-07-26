<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exige que haya un camarero identificado en el TPV compartido.
 *
 * Va en un filtro y no repartido por el controlador porque olvidarse en un
 * solo método dejaría un hueco por el que se podría operar con la pantalla
 * bloqueada.
 */
class Tpv implements FilterInterface
{
    public function before(RequestInterface $request, $argumentos = null)
    {
        $tpv = new \App\Libraries\SesionTpv();

        // Con el modo apagado, el TPV funciona como siempre
        if (! $tpv->compartido()) {
            return null;
        }

        if ($tpv->actual() !== null) {
            return null;
        }

        return service('response')
            ->setStatusCode(401)
            ->setJSON([
                'ok'        => false,
                'bloqueado' => true,
                'error'     => 'La pantalla está bloqueada. Identifícate para seguir.',
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $argumentos = null)
    {
        return null;
    }
}
