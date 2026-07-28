<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Correo;
use App\Models\CorreoLogModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Los correos de una reserva web.
 *
 * Lo que se defiende: **que una reserva por la web genera dos correos y no
 * uno**. Durante un tiempo solo salió el aviso interno al hotel, y quien
 * reservaba se quedaba sin nada: sin código, sin resumen y sin saber si el
 * formulario había funcionado. Es el fallo que más llamadas de teléfono genera
 * y el más fácil de volver a introducir sin darse cuenta.
 *
 * En estas pruebas no hay servidor de correo configurado, así que nada sale de
 * verdad: se comprueba **a quién iba dirigido cada uno**, que es lo que se
 * rompió.
 *
 * @internal
 */
final class CorreoReservaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Correo $correo;
    private CorreoLogModel $log;

    protected function setUp(): void
    {
        parent::setUp();
        $this->correo = new Correo();
        $this->log    = new CorreoLogModel();
        $this->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        db_connect()->table('correos_log')->like('asunto', 'CORREOPRUEBA', 'both')->delete();
        db_connect()->table('correos_log')->like('destinatario', 'correoprueba', 'after')->delete();
    }

    /** @return array<string, mixed> */
    private function reserva(?string $email = 'correoprueba-huesped@ejemplo.test'): array
    {
        return [
            'id'            => null,
            'codigo'        => 'CORREOPRUEBA1',
            'nombre'        => 'Ana',
            'apellidos'     => 'Prueba',
            'email'         => $email,
            'telefono'      => '3000000000',
            'fecha_entrada' => date('Y-m-d', strtotime('+10 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('+12 days')),
            'adultos'       => 2,
            'ninos'         => 1,
            'total'         => 480000,
            'unidad_nombre' => 'Cabaña del lago',
        ];
    }

    private function ultimoDe(string $tipo): ?array
    {
        return $this->log->where('tipo', $tipo)->orderBy('id', 'DESC')->first();
    }

    /**
     * El aviso interno va al hotel. Eso estaba bien y tiene que seguir estando.
     */
    public function testElAvisoInternoVaAlHotel(): void
    {
        $this->correo->avisoReservaWeb($this->reserva());

        $fila = $this->ultimoDe('aviso_reserva_web');

        $this->assertNotNull($fila);
        $this->assertStringNotContainsString('correoprueba-huesped', (string) $fila['destinatario']);
    }

    /**
     * Y el acuse va a quien reservó. Este es el que faltaba.
     */
    public function testElAcuseVaAlHuesped(): void
    {
        $this->correo->solicitudRecibida($this->reserva());

        $fila = $this->ultimoDe('solicitud_recibida');

        $this->assertNotNull($fila, 'No se intentó ningún acuse al huésped.');
        $this->assertSame('correoprueba-huesped@ejemplo.test', $fila['destinatario']);
        $this->assertStringContainsString('CORREOPRUEBA1', (string) $fila['asunto']);
    }

    /**
     * No puede decir «confirmada»: la reserva entra pendiente de que el hotel
     * la acepte, y prometer una cabaña que quizá no hay es peor que no escribir.
     */
    public function testElAcuseNoPrometeQueEsteConfirmada(): void
    {
        $cuerpo = view('correos/solicitud_recibida', [
            'reserva' => $this->reserva(),
            'hotel'   => config('Hotel'),
        ]);

        $this->assertStringContainsString('Todavía no está confirmada', $cuerpo);
        $this->assertStringContainsString('CORREOPRUEBA1', $cuerpo, 'El código tiene que ir en el cuerpo.');
    }

    /** Sin dirección no se intenta nada, y no se ensucia el registro. */
    public function testSinCorreoDelHuespedNoSeIntentaNada(): void
    {
        $antes = $this->log->countAllResults();

        $this->assertFalse($this->correo->solicitudRecibida($this->reserva(null)));
        $this->assertSame($antes, $this->log->countAllResults());
    }

    /**
     * Una reserva web deja rastro de los dos correos, y con destinatarios
     * distintos. Si algún día vuelve a salir uno solo, esto lo caza.
     */
    public function testUnaReservaWebDejaLosDosCorreosConDestinatariosDistintos(): void
    {
        $reserva = $this->reserva();

        $this->correo->avisoReservaWeb($reserva);
        $this->correo->solicitudRecibida($reserva);

        $alHotel   = $this->ultimoDe('aviso_reserva_web');
        $alHuesped = $this->ultimoDe('solicitud_recibida');

        $this->assertNotNull($alHotel);
        $this->assertNotNull($alHuesped);
        $this->assertNotSame(
            $alHotel['destinatario'],
            $alHuesped['destinatario'],
            'Los dos correos no pueden acabar en la misma bandeja.'
        );
    }
}
