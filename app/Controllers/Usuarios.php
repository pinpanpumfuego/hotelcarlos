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
            'usuarios' => $this->usuarios->orderBy('nombre')->findAll(),
        ]);
    }

    public function nuevo()
    {
        return view('usuarios/form', [
            'titulo'  => 'Nuevo usuario',
            'seccion' => 'usuarios',
        ]);
    }

    public function guardar()
    {
        $datos = $this->request->getPost(['nombre', 'email', 'rol']);
        $clave = (string) $this->request->getPost('clave');

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
        ]);
    }

    public function actualizar(int $id)
    {
        $usuario = $this->usuarios->find($id);
        if ($usuario === null) {
            return redirect()->to('usuarios')->with('error', 'El usuario no existe.');
        }

        $datos          = $this->request->getPost(['nombre', 'email', 'rol']);
        $datos['email'] = strtolower(trim($datos['email']));

        // Nadie puede desactivarse ni quitarse el rol de gerencia a sí mismo.
        if ($id === (int) session()->get('usuario_id')) {
            $datos['rol']    = $usuario['rol'];
            $datos['activo'] = 1;
        } else {
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

    public function eliminar(int $id)
    {
        if ($id === (int) session()->get('usuario_id')) {
            return redirect()->to('usuarios')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $this->usuarios->delete($id);

        return redirect()->to('usuarios')->with('ok', 'Usuario eliminado.');
    }
}
