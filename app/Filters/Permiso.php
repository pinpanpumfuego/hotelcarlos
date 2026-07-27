<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restringe una ruta a quien tenga alguno de los permisos indicados.
 *
 *   ['filter' => 'permiso:reservas.cancelar']
 *   ['filter' => 'permiso:calendario.ver,calendario.ocupacion']
 *
 * Con varios permisos basta con tener **uno**. Es lo que hace falta para las
 * pantallas que sirven a dos perfiles con distinto alcance: el calendario lo
 * ven recepción (completo) y housekeeping (solo ocupación), y quien decide
 * cuánto se enseña de cada cosa es la vista, no la puerta.
 *
 * Sustituye al filtro `rol:`, que solo sabía de tres roles y miraba la ruta
 * entera en vez de la acción.
 */
class Permiso implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $exigidos = array_filter((array) $arguments);

        // Una ruta protegida sin decir con qué se cierra, por si acaso. Un
        // filtro mal escrito no puede convertirse en una puerta abierta.
        if ($exigidos === []) {
            return $this->rechazar($request, 'Ruta protegida sin permiso declarado.');
        }

        if (session()->get('usuario_id') === null) {
            return $this->rechazar($request, 'Tu sesión ha caducado. Vuelve a entrar.', true);
        }

        if (! service('permisos')->puedeAlguno($exigidos)) {
            return $this->rechazar($request, 'No tienes permiso para hacer eso.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    /**
     * Una petición de pantalla se va al panel con un aviso; una de datos
     * recibe un 403 con JSON.
     *
     * Sin esta distinción, el POS —que habla por JSON— recibiría la portada
     * del panel en lugar de un error, y el fallo aparecería como un «no se
     * pudo interpretar la respuesta» que no dice nada de lo que pasa.
     */
    private function rechazar(RequestInterface $request, string $mensaje, bool $aLogin = false)
    {
        if ($request instanceof IncomingRequest && $this->esPeticionDeDatos($request)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON(['ok' => false, 'error' => $mensaje]);
        }

        return redirect()->to($aLogin ? 'login' : 'panel')->with('error', $mensaje);
    }

    private function esPeticionDeDatos(IncomingRequest $request): bool
    {
        if ($request->isAJAX()) {
            return true;
        }

        return str_contains(strtolower((string) $request->getHeaderLine('Accept')), 'application/json');
    }
}
