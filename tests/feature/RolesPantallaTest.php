<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Permisos\Catalogo;
use App\Libraries\Permisos\Permisos;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * La pantalla de perfiles.
 *
 * Es la llave maestra del sistema: quien entra aquí puede darse a sí mismo
 * cualquier permiso. Por eso lo que más se comprueba es **quién no puede
 * entrar** y **qué no se puede romper desde dentro**.
 *
 * @internal
 */
final class RolesPantallaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private function sesion(string $perfil): array
    {
        $rol = (new RolModel())->porClave($perfil);
        $this->assertNotNull($rol);

        return [
            'usuario_id'     => 1,
            'usuario_nombre' => 'Prueba',
            'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    private function idDe(string $clave): int
    {
        return (int) (new RolModel())->porClave($clave)['id'];
    }

    // ── Quién entra ─────────────────────────────────────────────────────

    public function testSoloGerenciaAbreLaPantallaDePerfiles(): void
    {
        $this->assertFalse(
            $this->withSession($this->sesion('gerencia'))->get('roles')->isRedirect(),
            'Gerencia tiene que poder gestionar los perfiles.'
        );

        foreach (['administrador', 'recepcion', 'caja', 'restaurante', 'housekeeping', 'mantenimiento'] as $perfil) {
            $r = $this->withSession($this->sesion($perfil))->get('roles');

            $this->assertTrue($r->isRedirect(), "«{$perfil}» no debería abrir la pantalla de perfiles.");
            $this->assertStringContainsString('panel', (string) $r->getRedirectUrl());
        }
    }

    public function testNiSiquieraElAdministradorPuedeEditarUnPerfil(): void
    {
        // El administrador gestiona usuarios, pero no qué puede hacer cada uno.
        // Si pudiera, se daría a sí mismo el permiso de facturar.
        $r = $this->withSession($this->sesion('administrador'))
            ->post('roles/actualizar/' . $this->idDe('recepcion'), [
                csrf_token() => csrf_hash(),
                'permisos'   => ['reportes.ingresos'],
            ]);

        $this->assertTrue($r->isRedirect());
        $this->assertStringContainsString('panel', (string) $r->getRedirectUrl());

        // Y recepción sigue sin ver los ingresos.
        $this->assertNotContains(
            'reportes.ingresos',
            (new Permisos())->deRol($this->idDe('recepcion'))
        );
    }

    // ── Gerencia es intocable ───────────────────────────────────────────

    public function testNoSePuedeVaciarGerenciaDesdeLaPantalla(): void
    {
        $id = $this->idDe('gerencia');

        $r = $this->withSession($this->sesion('gerencia'))
            ->post('roles/actualizar/' . $id, [
                csrf_token() => csrf_hash(),
                'permisos'   => ['reservas.ver'],
            ]);

        $this->assertTrue($r->isRedirect());

        // Sigue teniéndolo todo: es la salida de emergencia del sistema.
        $this->assertSame(
            [],
            array_diff(Catalogo::claves(), (new Permisos())->deRol($id))
        );
    }

    public function testGerenciaNoSePuedeEliminar(): void
    {
        $this->withSession($this->sesion('gerencia'))
            ->post('roles/eliminar/' . $this->idDe('gerencia'), [csrf_token() => csrf_hash()]);

        $this->assertNotNull((new RolModel())->porClave('gerencia'), 'Gerencia sigue existiendo.');
    }

    // ── Crear, editar y borrar ──────────────────────────────────────────

    public function testSeCreaUnPerfilPartiendoDeOtro(): void
    {
        $this->withSession($this->sesion('gerencia'))->post('roles/guardar', [
            csrf_token()  => csrf_hash(),
            'nombre'      => 'Recepción fin de semana',
            'descripcion' => 'Cubre el mostrador sábados y domingos',
            'plantilla'   => 'recepcion',
        ]);

        $roles = new RolModel();
        $nuevo = $roles->porClave('recepcion_fin_de_semana');

        $this->assertNotNull($nuevo, 'La clave se deriva del nombre, sin tildes ni espacios.');
        $this->assertSame(
            count(Catalogo::permisosDe('recepcion')),
            count($roles->permisos((int) $nuevo['id'])),
            'Partir de una plantilla copia sus permisos.'
        );

        $roles->delete($nuevo['id'], true);
    }

    public function testCambiarLosPermisosSurteEfectoSinVolverAEntrar(): void
    {
        // Los permisos se consultan en cada petición, no se guardan en sesión.
        // Si se guardaran, quitarle un permiso a alguien no haría nada hasta
        // que cerrara sesión, que es lo contrario de lo que se espera.
        $roles = new RolModel();
        $id    = (int) $roles->insert(['clave' => 'prueba_efecto', 'nombre' => 'Prueba', 'es_sistema' => 0], true);
        $roles->fijarPermisos($id, ['reservas.ver']);

        $sesion = ['usuario_id' => 1, 'usuario_nombre' => 'X', 'usuario_rol_id' => $id];
        $this->assertFalse($this->withSession($sesion)->get('reservas')->isRedirect());

        $this->withSession($this->sesion('gerencia'))->post('roles/actualizar/' . $id, [
            csrf_token() => csrf_hash(),
            'nombre'     => 'Prueba',
            'permisos'   => ['limpieza.ver'],
        ]);

        $this->assertTrue(
            $this->withSession($sesion)->get('reservas')->isRedirect(),
            'Al quitarle el permiso, deja de entrar en el acto.'
        );

        $roles->delete($id, true);
    }

    public function testUnPerfilConGenteNoSePuedeEliminar(): void
    {
        // Dejar usuarios sin perfil es dejarlos sin poder trabajar, y el error
        // aparecería mañana por la mañana, no ahora.
        $roles = new RolModel();
        $id    = (int) $roles->insert(['clave' => 'prueba_ocupado', 'nombre' => 'Ocupado', 'es_sistema' => 0], true);

        db_connect()->table('usuarios')->insert([
            'nombre'     => 'Alguien',
            'email'      => 'alguien.prueba@example.com',
            'clave_hash' => password_hash('x', PASSWORD_DEFAULT),
            'rol'        => 'recepcion',
            'rol_id'     => $id,
            'activo'     => 1,
        ]);

        $this->assertFalse($roles->sePuedeEliminar($id));

        $this->withSession($this->sesion('gerencia'))
            ->post('roles/eliminar/' . $id, [csrf_token() => csrf_hash()]);

        $this->assertNotNull($roles->find($id), 'El perfil sigue ahí porque hay alguien usándolo.');

        db_connect()->table('usuarios')->where('email', 'alguien.prueba@example.com')->delete();
        $roles->delete($id, true);
    }

    public function testUnPerfilVacioNoSePuedeCrearSinNombre(): void
    {
        $antes = (new RolModel())->countAllResults();

        $this->withSession($this->sesion('gerencia'))->post('roles/guardar', [
            csrf_token() => csrf_hash(),
            'nombre'     => '   ',
        ]);

        $this->assertSame($antes, (new RolModel())->countAllResults());
    }

    public function testNoSeCreanDosPerfilesConElMismoNombre(): void
    {
        $roles = new RolModel();
        $antes = $roles->countAllResults();

        $this->withSession($this->sesion('gerencia'))->post('roles/guardar', [
            csrf_token() => csrf_hash(),
            'nombre'     => 'Recepción',   // ya existe
        ]);

        $this->assertSame($antes, $roles->countAllResults());
    }
}
