<?php

declare(strict_types=1);

namespace App\Filters;

use App\Libraries\Permisos\Catalogo;
use App\Models\AuditoriaModel;
use App\Models\RolModel;
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
            // Un intento denegado también se registra: dice que alguien buscó
            // una puerta que no le corresponde. Casi siempre es un enlace viejo
            // o un menú mal configurado, pero conviene poder verlo.
            $this->auditar($request, $exigidos, 'denegado', 403);

            return $this->rechazar($request, 'No tienes permiso para hacer eso.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $exigidos = array_filter((array) $arguments);
        if ($exigidos === []) {
            return;
        }

        $codigo = $response->getStatusCode();

        // Una redirección con aviso de error significa que el controlador no
        // pudo hacerlo: no es lo mismo «canceló la reserva» que «lo intentó».
        $fallo = $codigo >= 400 || session()->getFlashdata('error') !== null;

        $this->auditar($request, $exigidos, $fallo ? 'error' : 'ok', $codigo);
    }

    /**
     * Deja constancia si el permiso ejercido es de los sensibles.
     *
     * Se registran solo esos —los que mueven dinero, tocan datos personales o
     * cambian la configuración— porque registrarlo todo daría una tabla enorme
     * donde lo importante no se encuentra, que es la forma más eficaz de no
     * tener auditoría teniéndola.
     */
    private function auditar(RequestInterface $request, array $exigidos, string $resultado, ?int $http): void
    {
        $sensible = null;
        foreach ($exigidos as $clave) {
            if (Catalogo::esSensible((string) $clave)) {
                $sensible = (string) $clave;
                break;
            }
        }

        if ($sensible === null || ! $request instanceof IncomingRequest) {
            return;
        }

        // `getPath()` y no `getUri()->getPath()`: el segundo arrastra el
        // subdirectorio cuando el sistema no cuelga de la raíz del dominio, y
        // entonces la referencia sale como «hotelcarlos:1» en vez de
        // «registros:1», que es lo que hace inservible el historial.
        $ruta = trim($request->getPath(), '/');

        (new AuditoriaModel())->registrar([
            'usuario_id'     => session()->get('usuario_id'),
            'usuario_nombre' => (string) session()->get('usuario_nombre'),
            'perfil'         => $this->nombrePerfil(),
            'permiso'        => $sensible,
            'metodo'         => $request->getMethod(),
            'ruta'           => $ruta,
            'referencia'     => $this->referencia($ruta),
            'resultado'      => $resultado,
            'http'           => $http,
            'ip'             => $request->getIPAddress(),
        ]);
    }

    /**
     * Sobre qué se actuó: «reservas:34».
     *
     * Es lo que permite después preguntar «¿qué le pasó a la reserva 34?» y
     * que salga todo lo que se hizo con ella, venga de donde venga.
     */
    private function referencia(string $ruta): ?string
    {
        $partes = explode('/', $ruta);
        $id     = null;

        foreach (array_reverse($partes) as $parte) {
            if (ctype_digit($parte)) {
                $id = $parte;
                break;
            }
        }

        return $id === null ? null : ($partes[0] ?? '') . ':' . $id;
    }

    private function nombrePerfil(): ?string
    {
        $rolId = session()->get('usuario_rol_id');
        if ($rolId === null) {
            return (string) session()->get('usuario_rol') ?: null;
        }

        $rol = (new RolModel())->find((int) $rolId);

        return $rol['nombre'] ?? null;
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
