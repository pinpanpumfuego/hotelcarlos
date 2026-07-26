<?php

namespace App\Filters;

use App\Models\EmpleadoModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Deja pasar solo a un camarero identificado con su PIN.
 *
 * El comandero vive en el teléfono del camarero, que no tiene usuario del
 * sistema: la identidad es la misma del portal del empleado (`empleado_id`).
 * Se comprueba en cada petición y no solo al entrar, porque una ficha se
 * puede desactivar a media tarde y el teléfono seguiría con la sesión viva.
 */
class Comandero implements FilterInterface
{
    /** Quién puede tomar comandas. */
    public const ROLES = ['camarero', 'encargado'];

    public function before(RequestInterface $request, $argumentos = null)
    {
        $id = session()->get('empleado_id');

        if ($id !== null) {
            $empleado = (new EmpleadoModel())->find((int) $id);

            if ($empleado !== null
                && (int) $empleado['activo'] === 1
                && in_array($empleado['rol_tpv'], self::ROLES, true)) {
                return null;
            }
        }

        session()->remove(['empleado_id', 'empleado_nombre']);

        // La cola del teléfono guarda rondas sin enviar: si le contestáramos con
        // el HTML de la pantalla de entrada, las daría por enviadas y las perdería
        if ($request->isAJAX() || $request->getHeaderLine('Accept') === 'application/json') {
            return service('response')->setStatusCode(401)->setJSON([
                'ok'      => false,
                'entrar'  => true,
                'error'   => 'Vuelve a entrar con tu PIN. Lo que tengas sin enviar no se pierde.',
            ]);
        }

        return redirect()->to('comandero')->with('error', 'Entra con tu documento y tu PIN.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $argumentos = null)
    {
        return null;
    }
}
