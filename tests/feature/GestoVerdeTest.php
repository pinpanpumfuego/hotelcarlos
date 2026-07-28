<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\GestoVerde as Motor;
use App\Models\ConfiguracionModel;
use App\Models\GestoVerdeModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * Gesto verde: una lavada menos a cambio de una consumición.
 *
 * Lo que se defiende aquí es lo que hace que el programa no salga caro:
 *
 * 1. **Ni la noche de llegada ni la de salida.** El día que se va la ropa se
 *    lava igual: premiar esa noche es pagar una bebida por un ahorro que no
 *    existe. Es el error clásico de estos programas.
 * 2. **Hay un suelo de higiene.** Pasado el tope de noches seguidas se cambia
 *    la ropa aunque el huésped quiera seguir.
 * 3. **El vale nace al confirmar, no al pedir.** Si naciera al pedirlo se
 *    pagaría por ahorros que no ocurrieron.
 * 4. **El balance no se inventa.** Sin el coste de una lavada, el informe dice
 *    que no lo sabe en vez de estimarlo.
 *
 * @internal
 */
final class GestoVerdeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Motor $motor;
    private GestoVerdeModel $gestos;
    private ConfiguracionModel $config;
    private int $huespedId;
    private int $categoriaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->motor  = new Motor();
        $this->gestos = new GestoVerdeModel();
        $this->config = new ConfiguracionModel();
        $this->limpiar();

        $db = db_connect();

        $db->table('carta_categorias')->insert([
            'nombre' => 'PRUEBA-Bebidas', 'orden' => 99,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->categoriaId = (int) $db->insertID();

        $db->table('huespedes')->insert([
            'nombre' => 'Verde', 'apellidos' => 'Prueba',
            'tipo_documento' => 'CC', 'num_documento' => 'PRUEBA-VERDE',
        ]);
        $this->huespedId = (int) $db->insertID();

        $this->config->guardarPares([
            'verde_activo'       => '1',
            'verde_categoria_id' => (string) $this->categoriaId,
            'verde_hora_tope'    => '23:59',
            'verde_max_seguidas' => '3',
            'verde_coste_lavada' => '12000',
        ]);
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        $this->config->guardarPares(['verde_activo' => '0', 'verde_categoria_id' => '', 'verde_coste_lavada' => '0']);
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        foreach ($db->table('reservas')->select('id')->like('codigo', 'VER', 'after')->get()->getResultArray() as $r) {
            $db->table('gestos_verdes')->where('reserva_id', $r['id'])->delete();
        }

        $db->table('reservas')->like('codigo', 'VER', 'after')->delete();
        $db->table('huespedes')->where('num_documento', 'PRUEBA-VERDE')->delete();
        $db->table('carta_categorias')->like('nombre', 'PRUEBA-', 'after')->delete();
    }

    /** Una reserva de varias noches, ya alojada. */
    private function reserva(int $noches = 5): array
    {
        $db = db_connect();

        $db->table('reservas')->insert([
            'codigo'        => 'VER' . random_int(10000, 99999),
            'huesped_id'    => $this->huespedId,
            'unidad_id'     => null,
            'fecha_entrada' => date('Y-m-d', strtotime('-2 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('+' . ($noches - 2) . ' days')),
            'adultos'       => 2,
            'estado'        => 'checkin',
            'total'         => 500000,
        ]);

        return (new \App\Models\ReservaModel())->find((int) $db->insertID());
    }

    // ── Cuándo sí y cuándo no ───────────────────────────────────────────

    public function testEnUnaNocheIntermediaSePuede(): void
    {
        $this->assertTrue($this->motor->disponible($this->reserva())['ok']);
    }

    /**
     * El día de salida la ropa se lava igualmente. Premiarlo sería regalar una
     * bebida por un ahorro que no existe, y es el fallo típico de estos
     * programas.
     */
    public function testLaNocheDeSalidaNoCuenta(): void
    {
        $reserva = $this->reserva();
        $r       = $this->motor->disponible($reserva, $reserva['fecha_salida']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('salida', mb_strtolower((string) $r['motivo']));
    }

    /** Y el día de llegada no hay nada que cambiar todavía. */
    public function testLaNocheDeLlegadaTampoco(): void
    {
        $reserva = $this->reserva();

        $this->assertFalse($this->motor->disponible($reserva, $reserva['fecha_entrada'])['ok']);
    }

    public function testNoSePuedePedirDosVecesLaMismaNoche(): void
    {
        $reserva = $this->reserva();
        $this->motor->pedir((int) $reserva['id']);

        $this->expectException(RuntimeException::class);
        $this->motor->pedir((int) $reserva['id']);
    }

    /**
     * El suelo de higiene: pasadas las noches del tope, toca ropa limpia
     * aunque el huésped quiera seguir sumando invitaciones.
     */
    public function testPasadoElTopeDeNochesSeguidasTocaRopaLimpia(): void
    {
        $reserva = $this->reserva(10);

        // Tres noches seguidas ya vividas, todas confirmadas
        for ($i = 3; $i >= 1; $i--) {
            $this->gestos->insert([
                'reserva_id' => $reserva['id'],
                'fecha'      => date('Y-m-d', strtotime("-{$i} days")),
                'estado'     => 'confirmado',
            ]);
        }

        $r = $this->motor->disponible($reserva, date('Y-m-d'));

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('higiene', mb_strtolower((string) $r['motivo']));
    }

    /** Una noche descartada rompe la cadena: ahí sí se cambió la ropa. */
    public function testUnaNocheDescartadaRompeLaCadena(): void
    {
        $reserva = $this->reserva(10);

        $this->gestos->insert([
            'reserva_id' => $reserva['id'],
            'fecha'      => date('Y-m-d', strtotime('-1 day')),
            'estado'     => 'descartado',
            'motivo'     => 'Se manchó la sábana',
        ]);

        $this->assertSame(0, $this->gestos->nochesSeguidas((int) $reserva['id'], date('Y-m-d')));
        $this->assertTrue($this->motor->disponible($reserva, date('Y-m-d'))['ok']);
    }

    public function testConElProgramaApagadoNoSePuede(): void
    {
        $this->config->guardarPares(['verde_activo' => '0']);

        $this->assertFalse($this->motor->disponible($this->reserva())['ok']);
    }

    /** Encenderlo sin decir de qué puede elegir no lo enciende de verdad. */
    public function testSinCategoriaElegidaElProgramaNoEstaActivo(): void
    {
        $this->config->guardarPares(['verde_categoria_id' => '']);

        $this->assertFalse($this->motor->activo());
    }

    // ── El vale nace al confirmar ───────────────────────────────────────

    /**
     * Quien lo pide por la mañana puede pedir toallas por la tarde. Si el vale
     * naciera al pedirlo, se habría pagado por un ahorro que no ocurrió.
     */
    public function testPedirloNoDaTodaviaNingunVale(): void
    {
        $reserva = $this->reserva();
        $this->motor->pedir((int) $reserva['id']);

        $this->assertSame([], $this->gestos->valesDisponibles((int) $reserva['id']));
    }

    public function testCuandoHousekeepingConfirmaApareceElVale(): void
    {
        $reserva = $this->reserva();
        $id      = $this->motor->pedir((int) $reserva['id']);

        $this->motor->confirmar($id);

        $this->assertCount(1, $this->gestos->valesDisponibles((int) $reserva['id']));
    }

    /** Si hubo que cambiar la ropa, no hay ahorro y no hay premio. */
    public function testDescartarNoDejaVale(): void
    {
        $reserva = $this->reserva();
        $id      = $this->motor->pedir((int) $reserva['id']);

        $this->motor->descartar($id, 'Se manchó la sábana');

        $this->assertSame([], $this->gestos->valesDisponibles((int) $reserva['id']));
        $this->assertSame('descartado', $this->gestos->find($id)['estado']);
    }

    /** Descartar sin decir por qué deja a quien lo lea mañana sin saber nada. */
    public function testNoSePuedeDescartarSinMotivo(): void
    {
        $id = $this->motor->pedir((int) $this->reserva()['id']);

        $this->expectException(RuntimeException::class);
        $this->motor->descartar($id, '   ');
    }

    public function testCanjearGastaElValeYNoDejaCanjearloDosVeces(): void
    {
        $reserva = $this->reserva();
        $id      = $this->motor->pedir((int) $reserva['id']);
        $this->motor->confirmar($id);

        $r = $this->motor->canjear((int) $reserva['id'], 999);

        $this->assertSame(0, $r['restantes']);
        $this->assertSame('canjeado', $this->gestos->find($id)['estado']);

        $this->expectException(RuntimeException::class);
        $this->motor->canjear((int) $reserva['id'], 1000);
    }

    // ── El balance ──────────────────────────────────────────────────────

    /**
     * Una lavada se da por ahorrada cuando housekeeping confirma, se haya
     * tomado la bebida o no: el ahorro ya ocurrió.
     */
    public function testElBalanceCuentaLasLavadasConfirmadasAunqueNadieSeTomeNada(): void
    {
        $reserva = $this->reserva(10);

        // Anoche y hoy. Más atrás no cabe: la reserva entró hace dos días y la
        // noche de llegada no cuenta.
        foreach ([1, 0] as $i) {
            $id = $this->motor->pedir((int) $reserva['id'], 'portal', null, date('Y-m-d', strtotime("-{$i} days")));
            $this->motor->confirmar($id);
        }

        $b = $this->motor->balance(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));

        $this->assertSame(2, $b['lavadas']);
        $this->assertSame(24000.0, $b['ahorro'], 'Dos lavadas a 12.000.');
        $this->assertSame(0, $b['canjeados']);
        $this->assertTrue($b['sabemos_costes']);
    }

    /** Las descartadas no cuentan como ahorro: ahí se lavó igual. */
    public function testLasDescartadasNoCuentanComoAhorro(): void
    {
        $reserva = $this->reserva(10);
        $id      = $this->motor->pedir((int) $reserva['id'], 'portal', null, date('Y-m-d', strtotime('-1 day')));
        $this->motor->descartar($id, 'Sábana rota');

        $b = $this->motor->balance(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));

        $this->assertSame(0, $b['lavadas']);
        $this->assertSame(1, $b['descartados']);
    }

    /**
     * Sin el coste de una lavada no se puede decir si el programa sale a
     * cuenta, y el informe tiene que decirlo en vez de estimarlo: el agua y la
     * energía de este hotel no las sabe el programa.
     */
    public function testSinElCosteDeUnaLavadaElBalanceLoDiceEnVezDeInventarlo(): void
    {
        $this->config->guardarPares(['verde_coste_lavada' => '0']);

        $reserva = $this->reserva(10);
        $id      = $this->motor->pedir((int) $reserva['id'], 'portal', null, date('Y-m-d', strtotime('-1 day')));
        (new Motor())->confirmar($id);

        $b = (new Motor())->balance(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));

        $this->assertFalse($b['sabemos_costes']);
        $this->assertSame(0.0, $b['ahorro']);
        $this->assertSame(1, $b['lavadas'], 'La lavada se ahorró igual, aunque no sepamos cuánto valía.');
    }
}
