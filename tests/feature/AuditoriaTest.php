<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Permisos\Catalogo;
use App\Models\AuditoriaModel;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * La auditoría.
 *
 * Lo que se comprueba: que **queda constancia de lo que importa** y que **no se
 * llena de ruido**. Las dos mitades cuentan: una auditoría que lo registra todo
 * es una tabla enorme donde lo importante no se encuentra, que es la forma más
 * eficaz de no tener auditoría teniéndola.
 *
 * @internal
 */
final class AuditoriaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private AuditoriaModel $auditoria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditoria = new AuditoriaModel();
        db_connect()->table('auditoria')->truncate();
    }

    protected function tearDown(): void
    {
        db_connect()->table('auditoria')->truncate();
        parent::tearDown();
    }

    private function sesion(string $perfil): array
    {
        $rol = (new RolModel())->porClave($perfil);

        return [
            'usuario_id'     => 1,
            'usuario_nombre' => 'Ana Gerente',
            'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    // ── Qué se registra ─────────────────────────────────────────────────

    public function testQuedaConstanciaDeMirarUnaCedula(): void
    {
        // Es el dato más delicado que guarda el sistema (Ley 1581/2012). Que
        // quede quién lo miró es media protección.
        $this->withSession($this->sesion('gerencia'))->get('registros/documento/7');

        $fila = $this->auditoria->orderBy('id', 'DESC')->first();

        $this->assertNotNull($fila, 'Mirar un documento de identidad tiene que dejar rastro.');
        $this->assertSame('registros.documentos', $fila['permiso']);
        $this->assertSame('Ana Gerente', $fila['usuario_nombre']);
        $this->assertSame('registros:7', $fila['referencia'], 'La referencia permite reconstruir el historial de esa ficha.');
    }

    public function testSeGuardaElNombreYNoSoloElId(): void
    {
        // Una auditoría que dice «el usuario 7 canceló la reserva» deja de
        // servir el día que el usuario 7 se borra, que es justo el día en que
        // más falta hace.
        $this->withSession($this->sesion('gerencia'))->get('reportes');

        $fila = $this->auditoria->orderBy('id', 'DESC')->first();

        $this->assertSame('Ana Gerente', $fila['usuario_nombre']);
        $this->assertSame('Gerencia', $fila['perfil']);
    }

    public function testLaRutaNoArrastraElSubdirectorio(): void
    {
        // Si el sistema no cuelga de la raíz del dominio, `getUri()->getPath()`
        // devuelve también la carpeta, y la referencia sale como
        // «hotelcarlos:7» en vez de «registros:7».
        $this->withSession($this->sesion('gerencia'))->get('registros/documento/7');

        $fila = $this->auditoria->orderBy('id', 'DESC')->first();

        $this->assertSame('registros/documento/7', $fila['ruta']);
    }

    public function testUnIntentoDenegadoTambienSeRegistra(): void
    {
        // Dice que alguien buscó una puerta que no le corresponde.
        $this->withSession($this->sesion('housekeeping'))->get('registros/documento/7');

        $fila = $this->auditoria->orderBy('id', 'DESC')->first();

        $this->assertNotNull($fila);
        $this->assertSame('denegado', $fila['resultado']);
        $this->assertSame(403, (int) $fila['http']);
    }

    public function testUnaAccionQueNoSaleAdelanteSeDistingueDeUnaQueSi(): void
    {
        // No es lo mismo «canceló la reserva» que «lo intentó y no pudo».
        $this->withSession($this->sesion('gerencia'))->get('personal/documento/999999');

        $fila = $this->auditoria->orderBy('id', 'DESC')->first();

        $this->assertSame('error', $fila['resultado']);
    }

    // ── Qué NO se registra ──────────────────────────────────────────────

    public function testLoQueNoEsSensibleNoEnsuciaLaAuditoria(): void
    {
        foreach (['reservas', 'huespedes', 'limpieza', 'unidades', 'calendario'] as $ruta) {
            $this->withSession($this->sesion('gerencia'))->get($ruta);
        }

        $this->assertSame(
            0,
            $this->auditoria->countAllResults(),
            'Consultar pantallas normales no debe dejar rastro: la auditoría se llenaría de ruido.'
        );
    }

    public function testLasPantallasSinPermisoNoSeRegistran(): void
    {
        // `panel` y `perfil` van sin permiso: no hay nada sensible que anotar.
        $this->withSession($this->sesion('gerencia'))->get('panel');

        $this->assertSame(0, $this->auditoria->countAllResults());
    }

    // ── Coherencia con el catálogo ──────────────────────────────────────

    public function testTodoPermisoSensibleTieneNombreLegible(): void
    {
        // La pantalla enseña el nombre, no la clave. Si falta, saldría
        // «folio.descuento.sintope» en la tabla, que no se lo explica a nadie.
        foreach (Catalogo::sensibles() as $clave) {
            $this->assertNotSame(
                '',
                Catalogo::PERMISOS[$clave]['nombre'] ?? '',
                "El permiso sensible «{$clave}» no tiene nombre legible."
            );
        }
    }

    public function testHayPermisosSensiblesEnLosSitiosQueImportan(): void
    {
        $sensibles = Catalogo::sensibles();

        foreach ([
            'reservas.cancelar',
            'folio.descuento',
            'registros.documentos',
            'caja.cerrar',
            'pos.anular',
            'propinas.liquidar',
            'facturas.emitir',
            'unidades.publicar',
            'administracion.integraciones',
            'roles.gestionar',
        ] as $clave) {
            $this->assertContains($clave, $sensibles, "«{$clave}» debería estar marcado como sensible.");
        }
    }

    // ── La pantalla ─────────────────────────────────────────────────────

    public function testSoloGerenciaAbreLaAuditoria(): void
    {
        $this->assertFalse($this->withSession($this->sesion('gerencia'))->get('auditoria')->isRedirect());

        foreach (['administrador', 'recepcion', 'caja', 'restaurante', 'housekeeping'] as $perfil) {
            $this->assertTrue(
                $this->withSession($this->sesion($perfil))->get('auditoria')->isRedirect(),
                "«{$perfil}» no debería ver la auditoría."
            );
        }
    }

    public function testNoHayRutaParaBorrarNiEditarLaAuditoria(): void
    {
        // Una auditoría que se puede retocar no sirve para lo único que sirve
        // una auditoría.
        // Se comprueba con peticiones reales y no leyendo la tabla de rutas:
        // lo que importa es que **no se pueda**, no cómo esté declarado.
        foreach (['auditoria', 'auditoria/eliminar/1', 'auditoria/borrar'] as $ruta) {
            $existe = true;

            try {
                $this->withSession($this->sesion('gerencia'))->post($ruta, [csrf_token() => csrf_hash()]);
            } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
                $existe = false;
            }

            $this->assertFalse($existe, "POST /{$ruta} no debería existir: se podría alterar el rastro.");
        }

        // Y la de lectura sí existe, para que la ausencia de POST signifique
        // «es de solo lectura» y no «se me olvidó declararla».
        $this->assertFalse(
            $this->withSession($this->sesion('gerencia'))->get('auditoria')->isRedirect(),
            'La auditoría se tiene que poder consultar.'
        );
    }

    // ── Purga ───────────────────────────────────────────────────────────

    public function testLaPurgaBorraLoViejoYRespetaLoReciente(): void
    {
        $tabla = db_connect()->table('auditoria');

        $tabla->insert([
            'usuario_nombre' => 'Antiguo', 'permiso' => 'caja.cerrar', 'metodo' => 'POST',
            'ruta' => 'caja/cerrar', 'resultado' => 'ok',
            'created_at' => date('Y-m-d H:i:s', strtotime('-800 days')),
        ]);
        $tabla->insert([
            'usuario_nombre' => 'Reciente', 'permiso' => 'caja.cerrar', 'metodo' => 'POST',
            'ruta' => 'caja/cerrar', 'resultado' => 'ok',
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
        ]);

        $borrados = $this->auditoria->purgar();

        $this->assertSame(1, $borrados);
        $this->assertSame(1, $this->auditoria->countAllResults());
        $this->assertSame('Reciente', $this->auditoria->first()['usuario_nombre']);
    }

    public function testElHistorialDeUnaReferenciaLoJuntaTodo(): void
    {
        $tabla = db_connect()->table('auditoria');

        foreach (['reservas.cancelar', 'folio.descuento', 'facturas.emitir'] as $permiso) {
            $tabla->insert([
                'usuario_nombre' => 'Ana', 'permiso' => $permiso, 'metodo' => 'POST',
                'ruta' => 'reservas/x/34', 'referencia' => 'reservas:34', 'resultado' => 'ok',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $tabla->insert([
            'usuario_nombre' => 'Ana', 'permiso' => 'reservas.cancelar', 'metodo' => 'POST',
            'ruta' => 'reservas/cancelar/99', 'referencia' => 'reservas:99', 'resultado' => 'ok',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertCount(3, $this->auditoria->historialDe('reservas:34'));
    }
}
