<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

/** Perfil propio: cualquier usuario puede cambiar su contraseña. */
class Perfil extends BaseController
{
    public function index()
    {
        $usuario = (new UsuarioModel())->find(session()->get('usuario_id'));

        return view('perfil/index', [
            'titulo'  => 'Mi perfil',
            'seccion' => 'perfil',
            'usuario' => $usuario,
        ]);
    }

    public function cambiarClave()
    {
        $usuarios = new UsuarioModel();
        $usuario  = $usuarios->find(session()->get('usuario_id'));

        $actual       = (string) $this->request->getPost('clave_actual');
        $nueva        = (string) $this->request->getPost('clave_nueva');
        $confirmacion = (string) $this->request->getPost('clave_confirmacion');

        if (! password_verify($actual, $usuario['clave_hash'])) {
            return redirect()->to('perfil')->with('error', 'La contraseña actual no es correcta.');
        }
        if (strlen($nueva) < 8) {
            return redirect()->to('perfil')->with('error', 'La contraseña nueva debe tener al menos 8 caracteres.');
        }
        if ($nueva !== $confirmacion) {
            return redirect()->to('perfil')->with('error', 'La confirmación no coincide con la contraseña nueva.');
        }
        if ($nueva === $actual) {
            return redirect()->to('perfil')->with('error', 'La contraseña nueva debe ser distinta de la actual.');
        }

        $usuarios->update($usuario['id'], ['clave_hash' => password_hash($nueva, PASSWORD_DEFAULT)]);
        session()->regenerate(true);

        return redirect()->to('perfil')->with('ok', 'Contraseña actualizada correctamente.');
    }
}
