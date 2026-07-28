<?php

namespace App\Controllers;

use App\Libraries\AccesoPin;
use App\Models\DispositivoModel;
use App\Models\UsuarioModel;

/** Perfil propio: cualquier usuario puede cambiar su contraseña. */
class Perfil extends BaseController
{
    public function index()
    {
        $usuario = (new UsuarioModel())->find(session()->get('usuario_id'));
        $actual  = (new AccesoPin())->dispositivo();

        return view('perfil/index', [
            'titulo'  => 'Mi perfil',
            'seccion' => 'perfil',
            'usuario' => $usuario,
            'equipos' => (new DispositivoModel())->deUsuario((int) $usuario['id']),
            'equipo_actual' => $actual['id'] ?? null,
        ]);
    }

    /**
     * Retira un equipo de la lista de reconocidos.
     *
     * Es lo que se hace cuando se pierde un móvil o se cambia una tablet: sin
     * esto, un equipo reconocido lo seguiría siendo un año entero, y ese es
     * justamente el plazo que hace cómoda la medida y peligroso el descuido.
     */
    public function quitarEquipo(int $id)
    {
        $dispositivos = new DispositivoModel();
        $equipo       = $dispositivos->find($id);

        // Solo los propios: el identificador va en la dirección y no se puede
        // dar por bueno lo que llegue por ahí.
        if ($equipo === null || (int) $equipo['usuario_id'] !== (int) session()->get('usuario_id')) {
            return redirect()->to('perfil')->with('error', 'Ese equipo no es tuyo.');
        }

        $dispositivos->delete($id);

        return redirect()->to('perfil')->with('ok', 'Equipo retirado. Desde ahí ya no se puede entrar con PIN.');
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
