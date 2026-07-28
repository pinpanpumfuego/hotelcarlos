<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Administración, repartida en cuatro pantallas.
 *
 * Lo que se defiende:
 *
 * 1. **Las cinco pantallas abren enteras.** Una vista a la que le falta una
 *    variable no rompe hasta que alguien la abre, y este cambio movió más de
 *    novecientas líneas de sitio.
 * 2. **La de credenciales está cerrada de verdad.** Wompi y Siigo dan poder
 *    para facturar en nombre del hotel y cobrar con su pasarela: ahora la
 *    puerta es la página entera, no solo el botón de guardar.
 * 3. **La portada avisa de lo que falta.** Es lo que le da jerarquía a un
 *    módulo que antes era once formularios del mismo tamaño.
 *
 * @internal
 */
final class AdministracionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    /** @return array<string, mixed> */
    private function como(string $perfil): array
    {
        $rol = (new RolModel())->porClave($perfil);
        $this->assertNotNull($rol, "Falta el perfil «{$perfil}».");

        return [
            'usuario_id'     => 1,
            'usuario_nombre' => 'Prueba',
            'usuario_rol'    => $perfil,
            'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    public function testLasCincoPantallasSeAbren(): void
    {
        foreach (['administracion', 'administracion/hotel', 'administracion/cobros',
                  'administracion/operacion', 'administracion/sistema'] as $ruta) {
            $r = $this->withSession($this->como('gerencia'))->get($ruta);

            $this->assertFalse($r->isRedirect(), "/{$ruta} rebotó.");
            $r->assertOK();
            $this->assertStringNotContainsString('Whoops', (string) $r->getBody(), "/{$ruta} peta.");
        }
    }

    /**
     * Quien no puede tocar credenciales tampoco puede abrir la pantalla donde
     * están. Antes veía las llaves y solo fallaba al guardar.
     */
    public function testSinElPermisoDeCredencialesNoSeAbreLaPantallaDeCobros(): void
    {
        // «administrador» configura el sistema pero no toca el dinero de
        // terceros: tiene administracion.ver y no administracion.integraciones.
        $sesion = $this->como('administrador');

        $this->assertFalse(
            $this->withSession($sesion)->get('administracion')->isRedirect(),
            'Este perfil sí debe poder entrar a Administración.'
        );

        $this->assertTrue(
            $this->withSession($sesion)->get('administracion/cobros')->isRedirect(),
            'La pantalla de credenciales se abrió sin permiso.'
        );
    }

    /** La portada dice qué falta, no configura nada. */
    public function testLaPortadaListaLoQueFalta(): void
    {
        $cuerpo = (string) $this->withSession($this->como('gerencia'))->get('administracion')->getBody();

        // En una base recién migrada faltan cosas de sobra: el correo, Wompi,
        // los datos legales. Lo que se comprueba es que las enseña.
        $this->assertStringContainsString('pendiente', mb_strtolower($cuerpo));
        $this->assertStringNotContainsString('administracion/wompi', $cuerpo, 'La portada ya no lleva formularios.');
    }

    /**
     * El aviso más importante que puede dar: el fichaje abierto a internet.
     * Si esta comprobación desaparece, nadie se entera de que está abierto.
     */
    public function testAvisaSiElFichajeEstaAbiertoAInternet(): void
    {
        $config = new \App\Models\ConfiguracionModel();
        $antes  = $config->obtener('fichaje_terminal', '1');

        $config->guardarPares(['fichaje_terminal' => '1']);

        $claves = array_column((new \App\Libraries\Pendientes())->todos(), 'clave');
        $this->assertContains('fichar', $claves);

        $config->guardarPares(['fichaje_terminal' => '0']);

        $claves = array_column((new \App\Libraries\Pendientes())->todos(), 'clave');
        $this->assertNotContains('fichar', $claves, 'Apagado, el aviso tiene que desaparecer.');

        $config->guardarPares(['fichaje_terminal' => (string) $antes]);
    }

    /** Lo grave va primero: da igual el portal si el fichaje está abierto. */
    public function testLoGraveSaleAntesQueLoDemas(): void
    {
        $avisos = (new \App\Libraries\Pendientes())->todos();

        if (count($avisos) < 2) {
            $this->markTestSkipped('Hacen falta al menos dos avisos para comprobar el orden.');
        }

        $orden = ['grave' => 0, 'aviso' => 1, 'suelto' => 2];
        $ultimo = -1;

        foreach ($avisos as $a) {
            $this->assertGreaterThanOrEqual($ultimo, $orden[$a['nivel']], 'La lista no está ordenada por gravedad.');
            $ultimo = $orden[$a['nivel']];
        }
    }
}
