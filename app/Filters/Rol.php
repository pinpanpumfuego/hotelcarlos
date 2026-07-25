<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restringe una ruta a los roles indicados como argumentos del filtro.
 * Uso en rutas: ['filter' => 'rol:gerencia'] o 'rol:gerencia,recepcion'.
 */
class Rol implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $rolActual = session()->get('usuario_rol');

        if ($rolActual === null || ! in_array($rolActual, (array) $arguments, true)) {
            return redirect()->to('panel')->with('error', 'No tienes permisos para acceder a esa sección.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
