<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Permisos\Catalogo;
use App\Models\PermisoModel;
use App\Models\RolModel;

/**
 * Perfiles de acceso: qué puede hacer cada puesto.
 *
 * Todo lo de aquí exige `roles.gestionar`, que solo tiene gerencia. El motivo
 * es sencillo: **quien puede editar perfiles puede darse a sí mismo cualquier
 * permiso**. Es la llave maestra y no se reparte.
 */
class Roles extends BaseController
{
    private RolModel $roles;

    public function __construct()
    {
        $this->roles = new RolModel();
    }

    public function index()
    {
        return view('roles/index', [
            'titulo'     => 'Perfiles de acceso',
            'seccion'    => 'roles',
            'roles'      => $this->roles->listado(),
            'totalPerm'  => count(Catalogo::claves()),
            'huerfanos'  => (new PermisoModel())->huerfanos(),
        ]);
    }

    public function nuevo()
    {
        return view('roles/editar', [
            'titulo'   => 'Nuevo perfil',
            'seccion'  => 'roles',
            'rol'      => null,
            'marcados' => [],
            'modulos'  => Catalogo::porModulo(),
            'plantillas' => Catalogo::PERFILES,
        ]);
    }

    public function guardar()
    {
        $nombre = trim((string) $this->request->getPost('nombre'));

        if ($nombre === '') {
            return redirect()->back()->withInput()->with('errores', ['El perfil necesita un nombre.']);
        }

        // La clave se deriva del nombre: es lo que usa el código, y pedírsela a
        // gerencia sería pedirle que entienda algo que no le importa.
        $clave = $this->claveDesde($nombre);

        if ($this->roles->porClave($clave) !== null) {
            return redirect()->back()->withInput()->with('errores', ['Ya existe un perfil con ese nombre.']);
        }

        $id = (int) $this->roles->insert([
            'clave'       => $clave,
            'nombre'      => $nombre,
            'descripcion' => trim((string) $this->request->getPost('descripcion')) ?: null,
            'es_sistema'  => 0,
        ], true);

        // Se puede partir de un perfil que ya existe en vez de marcar 82
        // casillas desde cero.
        $plantilla = (string) $this->request->getPost('plantilla');
        $permisos  = $plantilla !== '' && isset(Catalogo::PERFILES[$plantilla])
            ? Catalogo::permisosDe($plantilla)
            : (array) $this->request->getPost('permisos');

        $this->roles->fijarPermisos($id, $permisos);

        return redirect()->to('roles/editar/' . $id)->with('ok', 'Perfil creado. Revisa lo que puede hacer.');
    }

    public function editar(int $id)
    {
        $rol = $this->roles->find($id);
        if ($rol === null) {
            return redirect()->to('roles')->with('error', 'El perfil no existe.');
        }

        return view('roles/editar', [
            'titulo'     => 'Perfil ' . $rol['nombre'],
            'seccion'    => 'roles',
            'rol'        => $rol,
            'marcados'   => $rol['clave'] === Catalogo::ROL_TOTAL
                ? Catalogo::claves()
                : $this->roles->permisos($id),
            'modulos'    => Catalogo::porModulo(),
            'plantillas' => Catalogo::PERFILES,
            'usuarios'   => $this->usuariosDe($id),
        ]);
    }

    public function actualizar(int $id)
    {
        $rol = $this->roles->find($id);
        if ($rol === null) {
            return redirect()->to('roles')->with('error', 'El perfil no existe.');
        }

        if ($rol['clave'] === Catalogo::ROL_TOTAL) {
            return redirect()->to('roles')->with(
                'error',
                'Gerencia no se puede modificar: es la salida de emergencia si un perfil se configura mal.'
            );
        }

        $nombre = trim((string) $this->request->getPost('nombre'));
        if ($nombre !== '') {
            $this->roles->update($id, [
                'nombre'      => $nombre,
                'descripcion' => trim((string) $this->request->getPost('descripcion')) ?: null,
            ]);
        }

        $permisos = array_values(array_filter((array) $this->request->getPost('permisos')));
        $this->roles->fijarPermisos($id, $permisos);

        // Los permisos se consultan en cada petición, no se guardan en la
        // sesión: quien esté trabajando ahora mismo con este perfil nota el
        // cambio en su siguiente clic, no cuando vuelva a entrar.
        service('permisos')->olvidar();

        return redirect()->to('roles/editar/' . $id)
            ->with('ok', 'Perfil actualizado: ' . count($permisos) . ' permiso(s). Surte efecto ya.');
    }

    public function eliminar(int $id)
    {
        if (! $this->roles->sePuedeEliminar($id)) {
            return redirect()->to('roles')->with(
                'error',
                'No se puede eliminar: o es un perfil del sistema, o hay gente usándolo. Cámbiales el perfil primero.'
            );
        }

        $this->roles->delete($id);

        return redirect()->to('roles')->with('ok', 'Perfil eliminado.');
    }

    /** Quién tiene este perfil. Sale en la ficha para saber a quién afecta. */
    private function usuariosDe(int $id): array
    {
        return db_connect()->table('usuarios')
            ->select('id, nombre, email, activo')
            ->where('rol_id', $id)
            ->orderBy('nombre')
            ->get()
            ->getResultArray();
    }

    private function claveDesde(string $nombre): string
    {
        $clave = mb_strtolower(trim($nombre));
        $clave = strtr($clave, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
        $clave = preg_replace('/[^a-z0-9]+/', '_', $clave) ?? '';

        return trim($clave, '_') ?: 'perfil_' . time();
    }
}
