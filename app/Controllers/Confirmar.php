<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AccesoPin;
use App\Models\UsuarioModel;

/**
 * «Esto pide tu contraseña.»
 *
 * Quien entró con PIN trabaja normal todo el día, pero al llegar a algo
 * sensible —dinero, documentos de identidad, configuración— se le pide la
 * contraseña una vez y se le abre un rato. Cuatro dígitos valen para abrir la
 * jornada; no para autorizar un cobro ni para abrir la cédula de un huésped.
 */
class Confirmar extends BaseController
{
    public function index()
    {
        $acceso = new AccesoPin();

        // Sin sesión de PIN o ya confirmada, aquí no hay nada que hacer.
        if (! $acceso->sesionEsDePin() || $acceso->elevada()) {
            return redirect()->to($this->vuelta());
        }

        return view('confirmar', [
            'titulo'  => 'Confirma que eres tú',
            'seccion' => 'perfil',
            'destino' => $this->vuelta(),
            'minutos' => AccesoPin::ELEVACION_MIN,
        ]);
    }

    public function clave()
    {
        // El mismo freno que la entrada: aquí se prueba una contraseña, y una
        // pantalla de confirmación sin límite es una puerta trasera cómoda.
        if (service('throttler')->check('confirmar-' . session()->get('usuario_id'), 5, MINUTE * 5) === false) {
            return redirect()->to('confirmar')->with('error', 'Demasiados intentos. Espera unos minutos.');
        }

        $usuario = (new UsuarioModel())->find(session()->get('usuario_id'));

        if ($usuario === null || ! password_verify((string) $this->request->getPost('clave'), $usuario['clave_hash'])) {
            return redirect()->to('confirmar')->with('error', 'La contraseña no es correcta.');
        }

        (new AccesoPin())->elevar();

        $destino = session()->get('url_confirmar') ?? site_url('panel');
        session()->remove('url_confirmar');

        return redirect()->to($destino)->with('ok', 'Confirmado. Tienes ' . AccesoPin::ELEVACION_MIN . ' minutos.');
    }

    private function vuelta(): string
    {
        return (string) (session()->get('url_confirmar') ?? site_url('panel'));
    }
}
