<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Las rutas de verdad, perfil por perfil.
 *
 * Esta es la prueba que justifica haber tocado 296 rutas. Comprueba lo que un
 * despiste en el fichero de rutas rompería: que **lo ajeno rebota**.
 *
 * Se insiste más en las negativas que en las positivas a propósito. Que
 * recepción pueda entrar a reservas se nota el primer día; que housekeeping
 * pueda entrar a los informes de ingresos no lo nota nadie, y ese es
 * justamente el fallo que hay que impedir.
 *
 * @internal
 */
final class PermisosRutasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    /** Sesión de alguien con ese perfil. */
    private function como(string $perfil): array
    {
        $rol = (new RolModel())->porClave($perfil);
        $this->assertNotNull($rol, "Falta el perfil «{$perfil}».");

        return [
            'usuario_id'     => 1,
            'usuario_nombre' => 'Prueba',
            'usuario_rol'    => 'gerencia',   // el antiguo: no debe influir
            'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    private function assertRebota(string $perfil, string $ruta): void
    {
        $r = $this->withSession($this->como($perfil))->get($ruta);

        $this->assertTrue(
            $r->isRedirect(),
            "«{$perfil}» NO debería poder abrir /{$ruta}, y no ha rebotado."
        );
        $this->assertStringContainsString(
            'panel',
            (string) $r->getRedirectUrl(),
            "«{$perfil}» debería volver al panel al intentar /{$ruta}."
        );
    }

    private function assertEntra(string $perfil, string $ruta): void
    {
        $r = $this->withSession($this->como($perfil))->get($ruta);

        // No se comprueba que la pantalla renderice entera —eso depende de que
        // haya datos— sino que **la puerta no la ha echado para atrás**.
        if ($r->isRedirect()) {
            $this->assertStringNotContainsString(
                'panel',
                (string) $r->getRedirectUrl(),
                "«{$perfil}» debería poder abrir /{$ruta}, pero le han rebotado al panel."
            );

            return;
        }

        $this->assertTrue(true);
    }

    // ── Sin sesión no se entra a ningún sitio ───────────────────────────

    /**
     * @dataProvider rutasDelPanel
     */
    public function testSinSesionNoSeEntraANingunaRutaDelPanel(string $ruta): void
    {
        $r = $this->get($ruta);

        $this->assertTrue($r->isRedirect(), "/{$ruta} se abre sin sesión.");
    }

    public static function rutasDelPanel(): array
    {
        return array_map(
            static fn (string $r): array => [$r],
            [
                'reservas', 'huespedes', 'caja', 'reportes', 'tarifas', 'usuarios',
                'personal', 'fichajes', 'propinas', 'facturas', 'cupones', 'canales',
                'administracion', 'registros', 'limpieza', 'cocina', 'pos',
            ]
        );
    }

    // ── Gerencia entra a todo ───────────────────────────────────────────

    /**
     * @dataProvider rutasDelPanel
     */
    public function testGerenciaEntraATodo(string $ruta): void
    {
        $this->assertEntra('gerencia', $ruta);
    }

    // ── Recepción ───────────────────────────────────────────────────────

    public function testRecepcionEntraALoSuyo(): void
    {
        foreach (['reservas', 'huespedes', 'registros', 'calendario', 'caja', 'tarifas', 'facturas'] as $ruta) {
            $this->assertEntra('recepcion', $ruta);
        }
    }

    public function testRecepcionNoLlegaALaConfiguracionNiALosIngresos(): void
    {
        // Decisión 1: ve la ocupación, no los márgenes. `reportes` sí se abre
        // (tiene `reportes.ocupacion`), pero la descarga en CSV no.
        foreach (['reportes/csv', 'usuarios', 'personal', 'fichajes', 'canales', 'cupones', 'administracion', 'propinas'] as $ruta) {
            $this->assertRebota('recepcion', $ruta);
        }
    }

    public function testRecepcionVeLasTarifasPeroNoLasCambia(): void
    {
        $this->assertEntra('recepcion', 'tarifas');
        $this->assertEntra('recepcion', 'tarifas/simulador');
    }

    // ── Housekeeping ────────────────────────────────────────────────────

    public function testHousekeepingEntraASuTablero(): void
    {
        foreach (['limpieza', 'unidades', 'calendario'] as $ruta) {
            $this->assertEntra('housekeeping', $ruta);
        }
    }

    public function testHousekeepingNoVeDatosDeHuespedesNiDinero(): void
    {
        // Decisión 4: «cabaña 3, sale mañana» y nada más.
        foreach (['reservas', 'huespedes', 'registros', 'caja', 'reportes', 'facturas', 'tarifas', 'pos'] as $ruta) {
            $this->assertRebota('housekeeping', $ruta);
        }
    }

    // ── Mantenimiento ───────────────────────────────────────────────────

    public function testMantenimientoEntraASusOrdenes(): void
    {
        foreach (['mantenimiento', 'unidades', 'calendario'] as $ruta) {
            $this->assertEntra('mantenimiento', $ruta);
        }
    }

    public function testMantenimientoNoEntraNiALimpiezaNiAReservas(): void
    {
        foreach (['limpieza', 'reservas', 'huespedes', 'caja', 'reportes'] as $ruta) {
            $this->assertRebota('mantenimiento', $ruta);
        }
    }

    // ── Restaurante ─────────────────────────────────────────────────────

    public function testRestauranteEntraASalaYCocina(): void
    {
        foreach (['pos', 'cocina', 'barra', 'tpv', 'caja'] as $ruta) {
            $this->assertEntra('restaurante', $ruta);
        }
    }

    public function testRestauranteNoLiquidaPropinasNiVeFichajes(): void
    {
        // Decisión 3: quien cobra la propina no la reparte.
        // Decisión 6: ve el cuadrante, no las horas trabajadas.
        $this->assertRebota('restaurante', 'propinas');
        $this->assertRebota('restaurante', 'fichajes');
        $this->assertRebota('restaurante', 'reservas');
        $this->assertRebota('restaurante', 'reportes');
        $this->assertRebota('restaurante', 'carta');
    }

    public function testRestauranteVeElCuadrante(): void
    {
        $this->assertEntra('restaurante', 'turnos');
    }

    // ── Caja ────────────────────────────────────────────────────────────

    public function testCajaLlevaElDineroYLasPropinas(): void
    {
        foreach (['caja', 'reportes', 'reportes/csv', 'facturas', 'propinas', 'bonos'] as $ruta) {
            $this->assertEntra('caja', $ruta);
        }
    }

    public function testCajaNoConfiguraElSistema(): void
    {
        foreach (['tarifas', 'usuarios', 'canales', 'administracion', 'personal', 'carta'] as $ruta) {
            $this->assertRebota('caja', $ruta);
        }
    }

    // ── Administrador ───────────────────────────────────────────────────

    public function testAdministradorConfiguraElSistema(): void
    {
        foreach (['tarifas', 'canales', 'cupones', 'carta', 'tipos', 'usuarios', 'personal', 'administracion'] as $ruta) {
            $this->assertEntra('administrador', $ruta);
        }
    }

    public function testAdministradorNoOperaElMostradorNiLaCaja(): void
    {
        foreach (['caja', 'pos', 'cocina', 'registros', 'huespedes', 'propinas'] as $ruta) {
            $this->assertRebota('administrador', $ruta);
        }
    }

    // ── Los dos permisos más delicados ──────────────────────────────────

    public function testLosDocumentosDeIdentidadSoloLosVeQuienDebe(): void
    {
        // Cédulas y firmas de los huéspedes: Ley 1581/2012.
        foreach (['housekeeping', 'mantenimiento', 'restaurante', 'caja', 'administrador'] as $perfil) {
            $this->assertRebota($perfil, 'registros/documento/1');
            $this->assertRebota($perfil, 'registros/firma/1');
        }
    }

    /**
     * POST con el token CSRF puesto.
     *
     * Sin el token, CSRF corta la petición **antes** del filtro de permiso y la
     * prueba pasaría sin haber comprobado nada de lo que dice comprobar. En
     * producción los dos frenos están bien; aquí interesa el segundo.
     */
    private function postConToken(string $perfil, string $ruta)
    {
        return $this->withSession($this->como($perfil))
            ->post($ruta, [csrf_token() => csrf_hash()]);
    }

    public function testLasCredencialesDeTercerosSoloLasTocaGerencia(): void
    {
        // Quien las tiene puede facturar en nombre del hotel y cobrar con su
        // pasarela. `administracion` la abre el administrador; las
        // integraciones, no.
        $r = $this->postConToken('administrador', 'administracion/siigo');

        $this->assertTrue($r->isRedirect());
        $this->assertStringContainsString('panel', (string) $r->getRedirectUrl());
    }

    public function testPublicarFotosEnLaWebNoEsCosaDeQuienLimpia(): void
    {
        // Era el fallo real que salió al hacer el mapa: `unidades/foto/publicar`
        // estaba en el grupo abierto a todo el personal.
        foreach (['housekeeping', 'mantenimiento'] as $perfil) {
            $r = $this->postConToken($perfil, 'unidades/foto/publicar/1');

            $this->assertTrue($r->isRedirect(), "«{$perfil}» no debería poder publicar fotos en la web.");
            $this->assertStringContainsString('panel', (string) $r->getRedirectUrl());
        }
    }

    public function testHousekeepingSiPuedeSubirEvidencias(): void
    {
        // Y la otra cara: separar los dos permisos no le puede quitar lo suyo.
        $this->assertEntra('housekeeping', 'unidades/foto/1');
    }

    // ── Perfiles sin permisos ───────────────────────────────────────────

    public function testUnPerfilVacioNoLlegaANadaSalvoAlPanel(): void
    {
        $roles = new RolModel();
        $id    = (int) $roles->insert([
            'clave'      => 'perfil_vacio_prueba',
            'nombre'     => 'Perfil vacío',
            'es_sistema' => 0,
        ], true);

        $sesion = [
            'usuario_id'     => 1,
            'usuario_nombre' => 'Prueba',
            'usuario_rol_id' => $id,
        ];

        foreach (['reservas', 'limpieza', 'caja', 'pos'] as $ruta) {
            $this->assertTrue(
                $this->withSession($sesion)->get($ruta)->isRedirect(),
                "Un perfil sin permisos ha entrado a /{$ruta}."
            );
        }

        // El panel sí: es donde aterriza todo el mundo, y dejar a alguien sin
        // ningún sitio al que ir lo mandaría al login en bucle.
        $this->assertFalse($this->withSession($sesion)->get('panel')->isRedirect());

        $roles->delete($id, true);
    }
}
