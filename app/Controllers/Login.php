<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

class Login extends BaseController
{
    public function index()
    {
        if (session()->has('usuario_id')) {
            return redirect()->to('panel');
        }

        return view('login');
    }

    public function entrar()
    {
        // Límite de intentos: 5 por minuto por IP para frenar fuerza bruta.
        $throttler = service('throttler');
        if ($throttler->check('login-' . md5($this->request->getIPAddress()), 5, MINUTE) === false) {
            return redirect()->to('login')->with('error', 'Demasiados intentos. Espera un minuto y vuelve a probar.');
        }

        $email = (string) $this->request->getPost('email');
        $clave = (string) $this->request->getPost('clave');

        $usuarios = new UsuarioModel();
        $usuario  = $usuarios->buscarPorEmail($email);

        if ($usuario === null || ! password_verify($clave, $usuario['clave_hash']) || (int) $usuario['activo'] !== 1) {
            // Mensaje genérico a propósito: no revelar si el email existe o no.
            return redirect()->to('login')->withInput()->with('error', 'Credenciales incorrectas o usuario inactivo.');
        }

        if (password_needs_rehash($usuario['clave_hash'], PASSWORD_DEFAULT)) {
            $usuarios->update($usuario['id'], ['clave_hash' => password_hash($clave, PASSWORD_DEFAULT)]);
        }

        $usuarios->update($usuario['id'], ['ultimo_acceso' => date('Y-m-d H:i:s')]);

        session()->regenerate(true);
        session()->set([
            'usuario_id'     => $usuario['id'],
            'usuario_nombre' => $usuario['nombre'],
            'usuario_rol'    => $usuario['rol'],
            // El perfil nuevo. Va junto al rol antiguo mientras las rutas se
            // pasan de `rol:` a `permiso:`; quien no tenga perfil asignado
            // sigue trabajando con la equivalencia de siempre.
            'usuario_rol_id' => $usuario['rol_id'] ?? null,
        ]);

        // Quien solo limpia entra directo a su tablero: el panel general no le
        // dice nada y le obligaría a un clic más cada mañana.
        $soloLimpia = service('permisos')->puede('limpieza.trabajar')
            && ! service('permisos')->puede('reservas.ver');

        $porDefecto = $soloLimpia ? site_url('limpieza') : site_url('panel');
        $destino    = session()->get('url_destino') ?? $porDefecto;
        session()->remove('url_destino');

        return redirect()->to($destino)->with('ok', 'Bienvenido, ' . $usuario['nombre'] . '.');
    }

    public function salir()
    {
        session()->destroy();

        return redirect()->to('login')->with('ok', 'Sesión cerrada correctamente.');
    }
}
