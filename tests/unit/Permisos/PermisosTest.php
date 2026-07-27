<?php

declare(strict_types=1);

namespace Tests\Unit\Permisos;

use App\Libraries\Permisos\Catalogo;
use App\Libraries\Permisos\Permisos;
use App\Models\PermisoModel;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Que cada perfil llegue a lo suyo **y rebote en lo ajeno**.
 *
 * Las dos caras importan igual. Una prueba que solo comprueba que recepción
 * puede crear reservas pasaría también si recepción pudiera hacerlo todo, que
 * es exactamente el fallo que hay que evitar.
 *
 * @internal
 */
final class PermisosTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Permisos $permisos;
    private RolModel $roles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permisos = new Permisos();
        $this->roles    = new RolModel();
    }

    private function rol(string $clave): int
    {
        $fila = $this->roles->porClave($clave);
        $this->assertNotNull($fila, "Falta el perfil de partida «{$clave}».");

        return (int) $fila['id'];
    }

    // ── El catálogo ─────────────────────────────────────────────────────

    public function testTodosLosPerfilesUsanPermisosQueExisten(): void
    {
        // Un permiso mal escrito en el catálogo no da error: simplemente ese
        // perfil se queda sin poder hacer eso, y nadie lo descubre hasta que
        // alguien no puede trabajar.
        foreach (array_keys(Catalogo::PERFILES) as $perfil) {
            foreach (Catalogo::permisosDe($perfil) as $clave) {
                $this->assertTrue(
                    Catalogo::existe($clave),
                    "El perfil «{$perfil}» concede «{$clave}», que no está en el catálogo."
                );
            }
        }
    }

    public function testTodoPermisoPerteneceAUnModuloDeclarado(): void
    {
        foreach (Catalogo::PERMISOS as $clave => $datos) {
            $this->assertArrayHasKey(
                $datos['modulo'],
                Catalogo::MODULOS,
                "El permiso «{$clave}» dice ser del módulo «{$datos['modulo']}», que no existe."
            );
        }
    }

    public function testLaTablaEstaSincronizadaConElCatalogo(): void
    {
        $enTabla = array_column((new PermisoModel())->select('clave')->findAll(), 'clave');

        foreach (Catalogo::claves() as $clave) {
            $this->assertContains($clave, $enTabla, "Falta «{$clave}» en la tabla de permisos.");
        }
    }

    public function testSincronizarEsIdempotente(): void
    {
        $modelo = new PermisoModel();
        $antes  = $modelo->countAllResults();

        $resultado = $modelo->sincronizar();

        $this->assertSame(0, $resultado['nuevos'], 'Sincronizar dos veces no debe duplicar nada.');
        $this->assertSame($antes, $modelo->countAllResults());
    }

    // ── Gerencia ────────────────────────────────────────────────────────

    public function testGerenciaLoPuedeTodo(): void
    {
        $suyos = $this->permisos->deRol($this->rol('gerencia'));

        $this->assertSame([], array_diff(Catalogo::claves(), $suyos), 'A gerencia no le puede faltar ningún permiso.');
    }

    public function testGerenciaLoPuedeTodoAunqueSuTablaEsteVacia(): void
    {
        // Los tiene por definición, en código. Si dependiera de las filas de
        // `rol_permisos`, un borrado accidental dejaría el sistema sin nadie
        // capaz de arreglarlo.
        $id    = $this->rol('gerencia');
        $filas = $this->db->table('rol_permisos')->where('rol_id', $id)->countAllResults();

        $this->assertSame(0, $filas, 'A gerencia no se le cuelgan permisos en la tabla.');
        $this->assertTrue(in_array('auditoria.ver', $this->permisos->deRol($id), true));
    }

    public function testNoSePuedenCambiarLosPermisosDeGerencia(): void
    {
        $this->assertFalse(
            $this->roles->fijarPermisos($this->rol('gerencia'), ['reservas.ver']),
            'Vaciar los permisos de gerencia dejaría el sistema sin salida de emergencia.'
        );
        $this->assertTrue(in_array('roles.gestionar', $this->permisos->deRol($this->rol('gerencia')), true));
    }

    public function testGerenciaNoSePuedeEliminar(): void
    {
        $this->assertFalse($this->roles->sePuedeEliminar($this->rol('gerencia')));
    }

    // ── Cada perfil: lo suyo y lo ajeno ─────────────────────────────────

    /**
     * @dataProvider perfilesYLimites
     */
    public function testCadaPerfilLlegaALoSuyoYRebotaEnLoAjeno(string $perfil, array $puede, array $noPuede): void
    {
        $suyos = $this->permisos->deRol($this->rol($perfil));

        foreach ($puede as $clave) {
            $this->assertContains($clave, $suyos, "«{$perfil}» debería poder «{$clave}».");
        }

        foreach ($noPuede as $clave) {
            $this->assertNotContains($clave, $suyos, "«{$perfil}» NO debería poder «{$clave}».");
        }
    }

    public static function perfilesYLimites(): array
    {
        return [
            'administrador configura pero no opera' => [
                'administrador',
                ['tarifas.editar', 'carta.gestionar', 'usuarios.gestionar', 'reportes.ingresos'],
                // Configura el sistema; no atiende el mostrador ni toca la caja.
                ['reservas.crear', 'reservas.checkin', 'caja.abrir', 'pos.cobrar', 'roles.gestionar'],
            ],

            'recepcion ve ocupacion pero no ingresos' => [
                'recepcion',
                ['reservas.crear', 'reservas.checkin', 'folio.descuento', 'tarifas.ver', 'reportes.ocupacion'],
                // Decisión 1: la ocupación sí, los márgenes no.
                // Decisión 2: descuenta, pero con tope.
                ['reportes.ingresos', 'reportes.exportar', 'folio.descuento.sintope', 'tarifas.editar', 'caja.arqueo'],
            ],

            'caja lleva el dinero y liquida propinas' => [
                'caja',
                ['caja.arqueo', 'caja.cerrar', 'reportes.ingresos', 'propinas.liquidar', 'folio.descuento.sintope'],
                // No opera reservas ni configura el sistema.
                ['reservas.crear', 'reservas.cancelar', 'tarifas.editar', 'usuarios.gestionar'],
            ],

            'restaurante manda en sala pero no en propinas' => [
                'restaurante',
                ['pos.anular', 'pos.descuento', 'cocina.marcar', 'caja.abrir', 'caja.cerrar', 'turnos.ver'],
                // Decisión 3: quien cobra la propina no la reparte.
                // Decisión 6: ve el cuadrante, no las horas trabajadas.
                ['propinas.liquidar', 'fichajes.ver', 'caja.arqueo', 'reservas.crear', 'carta.gestionar'],
            ],

            'housekeeping no ve datos del huesped' => [
                'housekeeping',
                ['limpieza.trabajar', 'unidades.revisar', 'unidades.foto', 'calendario.ocupacion'],
                // Decisión 4: «cabaña 3, sale mañana» y nada más.
                // Y no publica fotos en la web pública del hotel.
                ['calendario.ver', 'huespedes.ver', 'reservas.ver', 'unidades.publicar', 'folio.ver'],
            ],

            'mantenimiento cierra ordenes y poco mas' => [
                'mantenimiento',
                ['mantenimiento.trabajar', 'mantenimiento.prioridad', 'unidades.foto', 'calendario.ocupacion'],
                ['limpieza.trabajar', 'huespedes.ver', 'calendario.ver', 'unidades.publicar'],
            ],
        ];
    }

    public function testNadieSalvoGerenciaGestionaPerfiles(): void
    {
        // Quien puede editar perfiles puede darse a sí mismo cualquier permiso.
        // Es la llave maestra y no debe estar repartida.
        foreach (array_keys(Catalogo::PERFILES) as $perfil) {
            if ($perfil === Catalogo::ROL_TOTAL) {
                continue;
            }

            $this->assertNotContains(
                'roles.gestionar',
                $this->permisos->deRol($this->rol($perfil)),
                "«{$perfil}» no debería poder gestionar perfiles."
            );
        }
    }

    public function testSoloGerenciaTocaLasIntegraciones(): void
    {
        foreach (array_keys(Catalogo::PERFILES) as $perfil) {
            if ($perfil === Catalogo::ROL_TOTAL) {
                continue;
            }

            $this->assertNotContains(
                'administracion.integraciones',
                $this->permisos->deRol($this->rol($perfil)),
                "Las credenciales de Siigo y Wompi no son de «{$perfil}»."
            );
        }
    }

    // ── Cambiar permisos de un perfil ───────────────────────────────────

    public function testSePuedenCambiarLosPermisosDeUnPerfilNormal(): void
    {
        $id = $this->rol('mantenimiento');

        $this->assertTrue($this->roles->fijarPermisos($id, ['mantenimiento.ver', 'turnos.ver']));

        $this->permisos->olvidar();
        $suyos = $this->permisos->deRol($id);

        sort($suyos);
        $this->assertSame(['mantenimiento.ver', 'turnos.ver'], $suyos);

        // Y se deja como estaba, que las pruebas no se refrescan entre sí.
        $this->roles->fijarPermisos($id, Catalogo::permisosDe('mantenimiento'));
    }

    public function testUnPermisoInventadoNoSeConcede(): void
    {
        $id = $this->rol('mantenimiento');

        $this->roles->fijarPermisos($id, ['mantenimiento.ver', 'reservas.borrarlo.todo']);
        $this->permisos->olvidar();

        $this->assertSame(['mantenimiento.ver'], $this->permisos->deRol($id));

        $this->roles->fijarPermisos($id, Catalogo::permisosDe('mantenimiento'));
    }

    public function testFijarPermisosNoDuplica(): void
    {
        $id = $this->rol('housekeeping');

        $this->roles->fijarPermisos($id, ['limpieza.ver', 'limpieza.ver', 'limpieza.trabajar']);
        $this->permisos->olvidar();

        $this->assertCount(2, $this->permisos->deRol($id));

        $this->roles->fijarPermisos($id, Catalogo::permisosDe('housekeeping'));
    }

    // ── Tope de descuento ───────────────────────────────────────────────

    public function testQuienNoPuedeDescontarTieneTopeCero(): void
    {
        // Cero no es lo mismo que «sin límite», y confundirlos sería caro.
        session()->set('usuario_rol_id', $this->rol('housekeeping'));

        $this->assertSame(0.0, (new Permisos())->topeDescuento());
    }

    public function testRecepcionDescuentaHastaElTope(): void
    {
        session()->set('usuario_rol_id', $this->rol('recepcion'));
        $p = new Permisos();

        $this->assertSame(15.0, $p->topeDescuento(), 'El tope de partida son 15 puntos.');
        $this->assertTrue($p->descuentoPermitido(10.0));
        $this->assertTrue($p->descuentoPermitido(15.0));
        $this->assertFalse($p->descuentoPermitido(15.5));
        $this->assertFalse($p->descuentoPermitido(100.0), 'Un descuento del 100 % es un cobro que desaparece.');
    }

    public function testCajaDescuentaSinTope(): void
    {
        session()->set('usuario_rol_id', $this->rol('caja'));
        $p = new Permisos();

        $this->assertNull($p->topeDescuento());
        $this->assertTrue($p->descuentoPermitido(100.0));
    }

    // ── Transición desde los tres roles antiguos ────────────────────────

    public function testSesionAntiguaSigueFuncionando(): void
    {
        // Mientras las 296 rutas se pasan de `rol:` a `permiso:`, quien tenga
        // una sesión abierta de antes no puede quedarse tirado.
        session()->remove('usuario_rol_id');
        session()->set('usuario_rol', 'recepcion');

        $p = new Permisos();

        $this->assertTrue($p->puede('reservas.crear'));
        $this->assertFalse($p->puede('reportes.ingresos'));
    }

    public function testLimpiezaAntiguaEquivaleAHousekeeping(): void
    {
        session()->remove('usuario_rol_id');
        session()->set('usuario_rol', 'limpieza');

        $p = new Permisos();

        $this->assertTrue($p->puede('limpieza.trabajar'));
        $this->assertFalse($p->puede('huespedes.ver'));
    }

    public function testSinSesionNoSePuedeNada(): void
    {
        session()->remove('usuario_rol_id');
        session()->remove('usuario_rol');

        $this->assertSame([], (new Permisos())->mios());
    }

    // ── El atajo de las vistas ──────────────────────────────────────────

    public function testElHelperDeLasVistasFunciona(): void
    {
        // Es el que van a usar las plantillas para decidir si pintan un botón.
        // Si no se carga, la vista revienta con «función no definida», y eso se
        // descubre en producción, no aquí.
        helper('permisos');

        $this->assertTrue(function_exists('puede'));
        $this->assertTrue(function_exists('puede_alguno'));
        $this->assertTrue(function_exists('tope_descuento'));

        session()->set('usuario_rol_id', $this->rol('recepcion'));

        $this->assertTrue(puede('reservas.crear'));
        $this->assertFalse(puede('reportes.ingresos'));
        $this->assertTrue(puede_alguno(['reportes.ingresos', 'reservas.ver']));
        $this->assertFalse(puede_alguno(['reportes.ingresos', 'roles.gestionar']));
    }
}
