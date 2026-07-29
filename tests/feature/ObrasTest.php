<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Obras;
use App\Models\ConfiguracionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * El cartel de «todavía no abrimos».
 *
 * Lo que hay que defender no es que el cartel salga —eso es lo fácil— sino
 * **qué NO tapa**. Un cartel de obras que se come el aviso de la pasarela de
 * pagos deja cobros en el aire; uno que tapa el portal del huésped deja sin su
 * cuenta a gente que ya está alojada; uno que corta el calendario deja a
 * Booking vendiendo noches ya ocupadas.
 *
 * Esas tres no se descubren mirando la web: se descubren tres semanas después,
 * cuando alguien reclama.
 *
 * @internal
 */
final class ObrasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private ConfiguracionModel $config;
    private ?string $activoAntes = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config      = new ConfiguracionModel();
        $this->activoAntes = $this->config->obtener('obras_activo', '0');

        $this->config->guardarPares(['obras_activo' => '1']);
        (new Obras())->guardarClave('1234');
        $this->sinPase();

        service('throttler')->remove('obras-' . md5('0.0.0.0'));
        service('throttler')->remove('obras-' . md5('127.0.0.1'));
    }

    protected function tearDown(): void
    {
        $this->config->guardarPares(['obras_activo' => (string) $this->activoAntes]);
        $this->sinPase();
        parent::tearDown();
    }

    private function sinPase(): void
    {
        unset($_COOKIE[Obras::COOKIE]);
        \Config\Services::resetSingle('superglobals');
        \Config\Services::resetSingle('request');
    }

    private function conPase(): void
    {
        $_COOKIE[Obras::COOKIE] = hash_hmac(
            'sha256',
            'pase-obras',
            (string) $this->config->obtener('obras_clave', '')
        );
        \Config\Services::resetSingle('superglobals');
        \Config\Services::resetSingle('request');
    }

    // ── Lo que sí tapa ──────────────────────────────────────────────────

    public function testLaPortadaSaleTapada(): void
    {
        $r = $this->get('/');

        // 503 y no 200: a Google se le dice «vuelve más tarde», no se le da a
        // indexar una página en construcción como si fuera la portada del hotel.
        $r->assertStatus(503);
        $this->assertStringContainsString('clave de acceso', mb_strtolower((string) $r->getBody()));
    }

    public function testElMotorDeReservasTambien(): void
    {
        $this->get('reservar')->assertStatus(503);
    }

    public function testYLasVersionesEnOtrosIdiomas(): void
    {
        $this->get('en')->assertStatus(503);
    }

    // ── Lo que NO puede tapar ───────────────────────────────────────────

    /**
     * Se pide de verdad cada dirección y se comprueba que **no** contesta el
     * cartel. Mirar la configuración de rutas no sirve: se puede quedar sin
     * cargar y entonces la prueba pasa sin haber comprobado nada.
     *
     * @dataProvider rutasQueNoSeTapan
     */
    public function testHayRutasQueElCartelNoPuedeTapar(string $ruta, string $verbo, string $porQue): void
    {
        $r = $verbo === 'post' ? $this->post($ruta) : $this->get($ruta);

        $cuerpo = mb_strtolower((string) $r->getBody());

        // Sin esto la comprobación pasaría sola con una respuesta vacía, que es
        // como una prueba se queda verde sin comprobar nada.
        $this->assertNotSame('', $cuerpo, 'La ruta ' . $ruta . ' no contestó nada.');

        // Da igual qué conteste —un 404, un 403, un error de firma—: lo que no
        // puede es contestar el cartel de obras.
        $this->assertStringNotContainsString('clave de acceso', $cuerpo, $porQue);
    }

    /** @return array<string, array{string, string, string}> */
    public static function rutasQueNoSeTapan(): array
    {
        return [
            'aviso de la pasarela' => [
                'pago/aviso', 'post',
                'Si el cartel se come el aviso de la pasarela, quedan pagos cobrados que el hotel '
                . 'no ve nunca. Y no se descubre mirando la web: se descubre cuando alguien reclama.',
            ],
            'portal del huésped' => [
                'estancia/xxxx', 'get',
                'Quien ya está alojado se quedaría sin su cuenta y sin su comprobante.',
            ],
            'calendario de canales' => [
                'calendario-ical/xxxx', 'get',
                'Booking dejaría de ver las fechas ocupadas y seguiría vendiendo noches ya vendidas.',
            ],
            'enlaces de registro ya enviados' => [
                'registro/xxxx', 'get',
                'Los enlaces que ya salieron por correo dejarían de funcionar.',
            ],
            'entrada al panel' => [
                'login', 'get',
                'El hotel se quedaría fuera de su propio sistema.',
            ],
            'terminal de fichaje' => [
                'fichar', 'get',
                'Nadie podría fichar su jornada.',
            ],
        ];
    }


    // ── La llave ────────────────────────────────────────────────────────

    public function testConLaClaveBuenaSePasa(): void
    {
        $this->assertTrue((new Obras())->entrar('1234')['ok']);
    }

    public function testConLaClaveMalaNo(): void
    {
        $r = (new Obras())->entrar('9999');

        $this->assertFalse($r['ok']);
        $this->assertNotNull($r['motivo']);
    }

    /**
     * Con el pase puesto, el cartel se aparta.
     *
     * Se comprueba sobre el filtro y no cargando la portada entera: la portada
     * de verdad trae tipos de cambio y disponibilidad, y una prueba que espera
     * a todo eso mide la red, no el cartel.
     */
    public function testConElPaseElCartelSeAparta(): void
    {
        $this->conPase();

        $this->assertNull(
            (new \App\Filters\Obras())->before(service('request')),
            'Con el pase, el filtro tiene que dejar pasar.'
        );
    }

    /**
     * Cambiar la clave invalida los pases repartidos, sin ir persiguiéndolos.
     * Es lo que permite enseñar el proyecto a alguien y después cerrarle la
     * puerta.
     */
    public function testCambiarLaClaveInvalidaLosPasesAntiguos(): void
    {
        $this->conPase();
        $this->assertTrue((new Obras())->tienePase());

        (new Obras())->guardarClave('5678');

        $this->assertFalse((new Obras())->tienePase(), 'El pase viejo tenía que haber caducado solo.');
    }

    /** Apagado el cartel, la web vuelve sola sin tocar nada más. */
    public function testApagadoElCartelLaWebVuelve(): void
    {
        $this->config->guardarPares(['obras_activo' => '0']);

        $this->assertNull((new \App\Filters\Obras())->before(service('request')));
    }
}
