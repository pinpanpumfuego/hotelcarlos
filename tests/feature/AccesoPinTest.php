<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\AccesoPin;
use App\Models\DispositivoModel;
use App\Models\EmpleadoModel;
use App\Models\UsuarioModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Entrar al panel con correo y PIN de 4 dígitos.
 *
 * Cuatro dígitos son diez mil combinaciones. Lo que se prueba aquí no es que
 * el PIN funcione —eso es lo fácil— sino que **las tres cosas que lo sostienen
 * siguen en pie**:
 *
 * 1. Desde un equipo sin reconocer, el PIN correcto tampoco entra.
 * 2. El freno de intentos cierra la puerta, y a la tercera tanda el PIN se
 *    apaga solo.
 * 3. Una sesión abierta con PIN no puede tocar lo sensible sin la contraseña.
 *
 * Si alguna de las tres se cae en una refactorización, el sistema queda
 * protegido por cuatro dígitos y nadie se entera hasta que pasa algo.
 *
 * @internal
 */
final class AccesoPinTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private const PIN   = '7391';
    private const CLAVE = 'contrasena-de-prueba';

    private AccesoPin $acceso;
    private int $usuarioId;
    private int $empleadoId;
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acceso = new AccesoPin();
        $this->limpiar();

        $this->email = 'pin-prueba@ejemplo.test';

        $usuarios = new UsuarioModel();
        $rol      = db_connect()->table('roles')->where('clave', 'recepcion')->get()->getRowArray();

        db_connect()->table('usuarios')->insert([
            'nombre'     => 'Prueba PIN',
            'email'      => $this->email,
            'clave_hash' => password_hash(self::CLAVE, PASSWORD_DEFAULT),
            'rol'        => 'recepcion',
            'rol_id'     => $rol['id'] ?? null,
            'activo'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->usuarioId = (int) db_connect()->insertID();

        $empleados = new EmpleadoModel();
        $empleados->insert([
            'usuario_id'     => $this->usuarioId,
            'nombre'         => 'Prueba',
            'apellidos'      => 'PIN',
            'tipo_documento' => 'CC',
            'num_documento'  => 'PRUEBA-PIN',
            'cargo'          => 'Recepción',
            'activo'         => 1,
        ]);
        $this->empleadoId = (int) db_connect()->insertID();

        $empleados->update($this->empleadoId, [
            'pin_hash'        => password_hash(self::PIN, PASSWORD_DEFAULT),
            'pin_actualizado' => date('Y-m-d H:i:s'),
            'pin_panel'       => 1,
        ]);

        $this->soltarFrenos();
        $usuarios->purgeDeleted();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db  = db_connect();
        $ids = array_column(
            $db->table('usuarios')->select('id')->like('email', 'pin-prueba', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            $db->table('dispositivos_confiables')->where('usuario_id', $id)->delete();
            $db->table('empleados')->where('usuario_id', $id)->delete();
        }

        $db->table('empleados')->where('num_documento', 'PRUEBA-PIN')->delete();
        $db->table('usuarios')->like('email', 'pin-prueba', 'after')->delete();
    }

    private function soltarFrenos(): void
    {
        $freno = service('throttler');
        $freno->remove('pin-panel-' . md5($this->email));
        $freno->remove('pin-tandas-' . md5($this->email));
        $freno->remove('login-' . md5('0.0.0.0'));
        $freno->remove('login-' . md5('127.0.0.1'));
    }

    /** Marca este navegador como reconocido y devuelve el token. */
    private function reconocerEquipo(): string
    {
        $token = bin2hex(random_bytes(32));

        (new DispositivoModel())->insert([
            'usuario_id' => $this->usuarioId,
            'token_hash' => hash('sha256', $token),
            'nombre'     => 'Equipo de prueba',
            'expira'     => date('Y-m-d', strtotime('+1 year')),
        ]);

        $this->fijarCookie($token);

        return $token;
    }

    private function olvidarEquipo(): void
    {
        unset($_COOKIE[AccesoPin::COOKIE]);
        $this->fijarCookie(null);
    }

    /**
     * Las cookies se leen del servicio `superglobals`, que se construye una
     * sola vez con lo que hubiera en `$_COOKIE`. Cambiar la variable después no
     * lo toca, así que hay que rehacer ese servicio y la petición o la prueba
     * estaría midiendo el estado de antes.
     */
    private function fijarCookie(?string $token): void
    {
        if ($token === null) {
            unset($_COOKIE[AccesoPin::COOKIE]);
        } else {
            $_COOKIE[AccesoPin::COOKIE] = $token;
        }

        \Config\Services::resetSingle('superglobals');
        \Config\Services::resetSingle('request');
    }

    // ── El equipo tiene que estar reconocido ────────────────────────────

    /**
     * Esto es lo que sostiene los cuatro dígitos. Si un día deja de cumplirse,
     * el panel queda protegido por diez mil combinaciones y nada más.
     */
    public function testDesdeUnEquipoSinReconocerElPinCorrectoNoEntra(): void
    {
        $this->olvidarEquipo();

        $r = $this->acceso->comprobar($this->email, self::PIN);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('equipo', mb_strtolower((string) $r['motivo']));
    }

    public function testDesdeUnEquipoReconocidoElPinCorrectoEntra(): void
    {
        $this->reconocerEquipo();

        $r = $this->acceso->comprobar($this->email, self::PIN);

        $this->assertTrue($r['ok'], (string) $r['motivo']);
        $this->assertSame($this->usuarioId, (int) $r['usuario']['id']);
    }

    /** Un token caducado es un equipo desconocido, no un equipo con prórroga. */
    public function testUnEquipoCaducadoYaNoVale(): void
    {
        $token = $this->reconocerEquipo();

        (new DispositivoModel())
            ->where('token_hash', hash('sha256', $token))
            ->set(['expira' => date('Y-m-d', strtotime('-1 day'))])
            ->update();

        $this->assertFalse($this->acceso->equipoReconocido());
        $this->assertFalse($this->acceso->comprobar($this->email, self::PIN)['ok']);
    }

    /** Y un token inventado tampoco, por si acaso. */
    public function testUnTokenInventadoNoReconoceNada(): void
    {
        $_COOKIE[AccesoPin::COOKIE] = bin2hex(random_bytes(32));

        $this->assertFalse($this->acceso->equipoReconocido());
    }

    // ── PIN, permisos y bloqueos ────────────────────────────────────────

    public function testUnPinEquivocadoNoEntra(): void
    {
        $this->reconocerEquipo();

        $this->assertFalse($this->acceso->comprobar($this->email, '0000')['ok']);
    }

    /**
     * El mensaje no puede decir si el correo existe: quien prueba se ahorraría
     * la mitad del trabajo sabiendo qué correos hay.
     */
    public function testElMensajeNoRevelaSiElCorreoExiste(): void
    {
        $this->reconocerEquipo();

        $malPin    = $this->acceso->comprobar($this->email, '0000')['motivo'];
        $noExiste  = $this->acceso->comprobar('no-existe-pin-prueba@ejemplo.test', '0000')['motivo'];

        $this->assertSame($malPin, $noExiste);
    }

    /** Tener PIN para fichar no es tener permiso para entrar al panel. */
    public function testSinElPermisoDelPanelElPinNoEntra(): void
    {
        $this->reconocerEquipo();
        (new EmpleadoModel())->update($this->empleadoId, ['pin_panel' => 0]);

        $r = $this->acceso->comprobar($this->email, self::PIN);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('contraseña', (string) $r['motivo']);
    }

    public function testUnUsuarioInactivoNoEntraConPin(): void
    {
        $this->reconocerEquipo();
        (new UsuarioModel())->update($this->usuarioId, ['activo' => 0]);

        $this->assertFalse($this->acceso->comprobar($this->email, self::PIN)['ok']);
    }

    /**
     * El freno va por persona y no por IP: quien prueba PINes cambia de red
     * antes que de víctima.
     */
    public function testAlQuintoFalloSeCierraLaPuerta(): void
    {
        $this->reconocerEquipo();

        for ($i = 0; $i < AccesoPin::INTENTOS; $i++) {
            $this->acceso->comprobar($this->email, '0000');
        }

        // Ahora ni siquiera con el PIN bueno
        $r = $this->acceso->comprobar($this->email, self::PIN);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('intentos', mb_strtolower((string) $r['motivo']));
    }

    /** Y quien insiste tanda tras tanda se queda sin PIN hasta usar su contraseña. */
    public function testInsistiendoSeApagaElPin(): void
    {
        $this->reconocerEquipo();

        for ($tanda = 0; $tanda < 4; $tanda++) {
            for ($i = 0; $i < AccesoPin::INTENTOS; $i++) {
                $this->acceso->comprobar($this->email, '0000');
            }

            // Se suelta el freno de la tanda para simular que esperó los diez
            // minutos y volvió a probar, que es justo el caso que preocupa.
            service('throttler')->remove('pin-panel-' . md5($this->email));
        }

        $empleado = (new EmpleadoModel())->find($this->empleadoId);
        $this->assertNotNull($empleado['pin_bloqueado'], 'El PIN tenía que haberse apagado solo.');

        $this->assertFalse($this->acceso->comprobar($this->email, self::PIN)['ok']);
    }

    /** Entrar con la contraseña lo devuelve a la vida. */
    public function testEntrarConLaContrasenaReactivaElPin(): void
    {
        (new EmpleadoModel())->update($this->empleadoId, ['pin_bloqueado' => date('Y-m-d H:i:s')]);

        $this->acceso->desbloquear($this->usuarioId);

        $this->assertNull((new EmpleadoModel())->find($this->empleadoId)['pin_bloqueado']);
    }

    // ── Lo que puede hacer una sesión de PIN ────────────────────────────

    /**
     * Trabajar sí; cobrar, ver documentos de identidad o entrar en
     * administración, no sin volver a demostrar quién eres.
     */
    public function testLaSesionDePinPideContrasenaSoloParaLoSensible(): void
    {
        session()->set('sesion_pin', true);
        session()->remove('elevada_hasta');

        $this->assertFalse(
            $this->acceso->pideContrasena(['reservas.ver']),
            'Ver reservas es el trabajo del día: no puede pedir contraseña.'
        );

        $this->assertTrue(
            $this->acceso->pideContrasena(['administracion.integraciones']),
            'Las credenciales de terceros sí.'
        );
    }

    public function testUnaSesionNormalNoPideNadaExtra(): void
    {
        session()->set('sesion_pin', false);

        $this->assertFalse($this->acceso->pideContrasena(['administracion.integraciones']));
    }

    /** Una vez confirmada, no se vuelve a pedir durante un rato. */
    public function testConfirmarLaContrasenaAbreUnaVentanaYSeCierraSola(): void
    {
        session()->set('sesion_pin', true);
        $this->acceso->elevar();

        $this->assertFalse($this->acceso->pideContrasena(['administracion.integraciones']));

        // Pasado el plazo, vuelve a pedirla
        session()->set('elevada_hasta', time() - 1);

        $this->assertTrue($this->acceso->pideContrasena(['administracion.integraciones']));
    }

    // ── Las pantallas ───────────────────────────────────────────────────

    /** En un equipo sin reconocer no se enseña la pestaña del PIN. */
    public function testLaPantallaDeEntradaSoloOfreceElPinDondeSirve(): void
    {
        $this->olvidarEquipo();
        $sin = (string) $this->get('login')->getBody();
        $this->assertStringNotContainsString('login/pin', $sin);

        $this->reconocerEquipo();
        $con = (string) $this->get('login')->getBody();
        $this->assertStringContainsString('login/pin', $con);
    }

    /** Entrar con la contraseña deja el equipo reconocido para la próxima. */
    public function testEntrarConLaContrasenaReconoceElEquipo(): void
    {
        $this->olvidarEquipo();
        $antes = (new DispositivoModel())->where('usuario_id', $this->usuarioId)->countAllResults();

        $this->post('login/entrar', [
            'email' => $this->email, 'clave' => self::CLAVE, 'csrf_test_name' => csrf_hash(),
        ]);

        $this->assertSame(
            $antes + 1,
            (new DispositivoModel())->where('usuario_id', $this->usuarioId)->countAllResults()
        );
    }
}
