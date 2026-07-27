<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

class Usuarios extends BaseController
{
    private UsuarioModel $usuarios;

    public function __construct()
    {
        $this->usuarios = new UsuarioModel();
    }

    public function index()
    {
        return view('usuarios/index', [
            'titulo'   => 'Usuarios',
            'seccion'  => 'usuarios',
            'usuarios' => $this->usuarios
                ->select('usuarios.*, roles.nombre AS perfil')
                ->join('roles', 'roles.id = usuarios.rol_id', 'left')
                ->orderBy('usuarios.nombre')
                ->findAll(),
        ]);
    }

    public function nuevo()
    {
        return view('usuarios/form', [
            'titulo'  => 'Nuevo usuario',
            'seccion' => 'usuarios',
            'perfiles' => (new \App\Models\RolModel())->listado(),
        ]);
    }

    public function guardar()
    {
        $datos = $this->request->getPost(['nombre', 'email']);
        $clave = (string) $this->request->getPost('clave');

        $perfil = $this->perfilPedido();
        if ($perfil === null) {
            return redirect()->back()->withInput()->with('errores', ['Elige un perfil de acceso.']);
        }
        $datos['rol_id'] = $perfil['id'];
        $datos['rol']    = $this->rolVestigio($perfil);

        if (strlen($clave) < 8) {
            return redirect()->back()->withInput()->with('errores', ['La contraseña debe tener al menos 8 caracteres.']);
        }

        $datos['email']      = strtolower(trim($datos['email']));
        $datos['clave_hash'] = password_hash($clave, PASSWORD_DEFAULT);
        $datos['activo']     = $this->request->getPost('activo') ? 1 : 0;

        try {
            if (! $this->usuarios->insert($datos)) {
                return redirect()->back()->withInput()->with('errores', $this->usuarios->errors());
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe un usuario con ese email.']);
        }

        return redirect()->to('usuarios')->with('ok', 'Usuario creado correctamente.');
    }

    public function editar(int $id)
    {
        $usuario = $this->usuarios->find($id);
        if ($usuario === null) {
            return redirect()->to('usuarios')->with('error', 'El usuario no existe.');
        }

        return view('usuarios/form', [
            'titulo'  => 'Editar usuario',
            'seccion' => 'usuarios',
            'usuario' => $usuario,
            'perfiles' => (new \App\Models\RolModel())->listado(),
        ]);
    }

    public function actualizar(int $id)
    {
        $usuario = $this->usuarios->find($id);
        if ($usuario === null) {
            return redirect()->to('usuarios')->with('error', 'El usuario no existe.');
        }

        $datos          = $this->request->getPost(['nombre', 'email']);
        $datos['email'] = strtolower(trim($datos['email']));

        // Nadie puede cambiarse el perfil ni desactivarse a sí mismo: es la
        // forma más fácil de quedarse fuera del sistema sin poder volver.
        if ($id === (int) session()->get('usuario_id')) {
            $datos['activo'] = 1;
        } else {
            $perfil = $this->perfilPedido();
            if ($perfil === null) {
                return redirect()->back()->withInput()->with('errores', ['Elige un perfil de acceso.']);
            }
            $datos['rol_id'] = $perfil['id'];
            $datos['rol']    = $this->rolVestigio($perfil);
            $datos['activo'] = $this->request->getPost('activo') ? 1 : 0;
        }

        $clave = (string) $this->request->getPost('clave');
        if ($clave !== '') {
            if (strlen($clave) < 8) {
                return redirect()->back()->withInput()->with('errores', ['La contraseña nueva debe tener al menos 8 caracteres.']);
            }
            $datos['clave_hash'] = password_hash($clave, PASSWORD_DEFAULT);
        }

        try {
            if (! $this->usuarios->update($id, $datos)) {
                return redirect()->back()->withInput()->with('errores', $this->usuarios->errors());
            }
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe otro usuario con ese email.']);
        }

        return redirect()->to('usuarios')->with('ok', 'Usuario actualizado correctamente.');
    }

    /** El perfil que llega del formulario, o null si no es válido. */
    private function perfilPedido(): ?array
    {
        $id = (int) $this->request->getPost('rol_id');

        return $id > 0 ? (new \App\Models\RolModel())->find($id) : null;
    }

    /**
     * Valor para la columna `rol`, que es un vestigio.
     *
     * El ENUM antiguo solo admite tres valores y ya no decide nada: quien manda
     * es `rol_id`. Se sigue rellenando porque la columna es NOT NULL y porque
     * una migración a medias es peor que un vestigio bien documentado. El día
     * que se elimine, no habrá que tocar nada más.
     */
    private function rolVestigio(array $perfil): string
    {
        return $perfil['clave'] === \App\Libraries\Permisos\Catalogo::ROL_TOTAL
            ? 'gerencia'
            : 'recepcion';
    }

    public function eliminar(int $id)
    {
        if ($id === (int) session()->get('usuario_id')) {
            return redirect()->to('usuarios')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $this->usuarios->delete($id);

        return redirect()->to('usuarios')->with('ok', 'Usuario eliminado.');
    }
}
