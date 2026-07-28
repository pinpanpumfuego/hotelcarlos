<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CarteraMovimientoModel;
use App\Models\CuentaCarteraModel;
use App\Models\FolioModel;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;

/**
 * Cartera de empresas y agencias.
 *
 * Lo que se defiende:
 *
 * 1. **La cartera no duplica el folio.** Al cargar una reserva a una empresa,
 *    el folio queda saldado con un pago y la deuda pasa a la cuenta. Si se
 *    copiara sin más, el día que alguien añadiera un cargo al folio los dos
 *    números dejarían de cuadrar y ninguno serviría.
 * 2. **El saldo se calcula, no se guarda.** Un saldo en columna se
 *    desincroniza el primer día que algo falle a mitad.
 * 3. **El cupo frena de verdad.** Un cupo que no impide cargar no es un cupo,
 *    es una nota.
 *
 * @internal
 */
final class CarteraTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private \App\Libraries\Cartera $cartera;
    private CuentaCarteraModel $cuentas;
    private int $huespedId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartera = new \App\Libraries\Cartera();
        $this->cuentas = new CuentaCarteraModel();
        $this->limpiar();

        db_connect()->table('huespedes')->insert([
            'nombre' => 'Car', 'apellidos' => 'Prueba',
            'tipo_documento' => 'CC', 'num_documento' => 'PRUEBA-CAR',
        ]);
        $this->huespedId = (int) db_connect()->insertID();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        foreach ($db->table('cuentas_cartera')->select('id')->like('nombre', 'PRUEBA-', 'after')->get()->getResultArray() as $c) {
            $db->table('cartera_movimientos')->where('cuenta_id', $c['id'])->delete();
        }

        $db->table('cuentas_cartera')->like('nombre', 'PRUEBA-', 'after')->delete();

        $ids = array_column(
            $db->table('huespedes')->select('id')->where('num_documento', 'PRUEBA-CAR')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            foreach ($db->table('reservas')->select('id')->where('huesped_id', $id)->get()->getResultArray() as $r) {
                $db->table('folio_movimientos')->where('reserva_id', $r['id'])->delete();
            }

            $db->table('reservas')->where('huesped_id', $id)->delete();
        }

        $db->table('huespedes')->where('num_documento', 'PRUEBA-CAR')->delete();
    }

    private function crearCuenta(array $extra = []): int
    {
        $this->cuentas->insert(array_merge([
            'codigo'     => $this->cuentas->siguienteCodigo(),
            'nombre'     => 'PRUEBA-Empresa',
            'tipo'       => 'empresa',
            'cupo'       => 0,
            'plazo_dias' => 30,
            'estado'     => 'activa',
        ], $extra));

        return (int) db_connect()->insertID();
    }

    private function reservaConSaldo(float $total): int
    {
        $db = db_connect();

        $db->table('reservas')->insert([
            'codigo' => 'CA' . random_int(10000, 99999), 'huesped_id' => $this->huespedId, 'unidad_id' => null,
            'fecha_entrada' => date('Y-m-d', strtotime('-3 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('-1 day')),
            'adultos' => 2, 'estado' => 'checkout', 'total' => $total,
        ]);
        $reservaId = (int) $db->insertID();

        $db->table('folio_movimientos')->insert([
            'reserva_id' => $reservaId, 'tipo' => 'cargo',
            'concepto' => 'Alojamiento (2 noches)', 'valor' => $total,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $reservaId;
    }

    // ── La cartera no duplica el folio ──────────────────────────────────

    public function testCargarUnaReservaSaldaElFolioYPasaLaDeudaALaCuenta(): void
    {
        $cuentaId  = $this->crearCuenta();
        $reservaId = $this->reservaConSaldo(500000);

        $this->cartera->cargarReserva($reservaId, $cuentaId);

        // El folio queda a cero: es lo que espera recepción al hacer el check-out
        $this->assertEqualsWithDelta(0, (new FolioModel())->saldo($reservaId), 0.01);

        // Y la deuda está en la cuenta
        $this->assertEqualsWithDelta(500000, $this->cuentas->saldo($cuentaId), 0.01);
    }

    public function testLaMismaReservaNoSeCargaDosVeces(): void
    {
        $cuentaId  = $this->crearCuenta();
        $reservaId = $this->reservaConSaldo(500000);

        $this->cartera->cargarReserva($reservaId, $cuentaId);

        $this->expectException(RuntimeException::class);
        $this->cartera->cargarReserva($reservaId, $cuentaId);
    }

    public function testUnaReservaSinSaldoNoSePuedeCargar(): void
    {
        $cuentaId  = $this->crearCuenta();
        $reservaId = $this->reservaConSaldo(500000);

        db_connect()->table('folio_movimientos')->insert([
            'reserva_id' => $reservaId, 'tipo' => 'pago',
            'concepto' => 'PRUEBA-Pagado', 'valor' => 500000, 'metodo' => 'efectivo',
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->cartera->cargarReserva($reservaId, $cuentaId);
    }

    public function testElVencimientoCuentaDesdeHoyNoDesdeLaEntrada(): void
    {
        // Contarlo desde la entrada haría que una estancia larga naciera medio
        // vencida antes de que la empresa haya visto la factura.
        $cuentaId  = $this->crearCuenta(['plazo_dias' => 30]);
        $reservaId = $this->reservaConSaldo(300000);

        $movId = $this->cartera->cargarReserva($reservaId, $cuentaId);
        $mov   = (new CarteraMovimientoModel())->find($movId);

        $this->assertSame(date('Y-m-d', strtotime('+30 days')), $mov['vence_en']);
    }

    // ── El cupo ─────────────────────────────────────────────────────────

    public function testElCupoImpideCargarDeMas(): void
    {
        // Un cupo que no frena no es un cupo, es una nota.
        $cuentaId  = $this->crearCuenta(['cupo' => 400000]);
        $reservaId = $this->reservaConSaldo(500000);

        $this->expectException(RuntimeException::class);
        $this->cartera->cargarReserva($reservaId, $cuentaId);
    }

    public function testCupoCeroSignificaSinLimite(): void
    {
        // Es una decisión consciente y distinta de «cupo de cero pesos».
        $cuentaId = $this->crearCuenta(['cupo' => 0]);

        $this->assertNull($this->cuentas->disponible($this->cuentas->find($cuentaId)));
        $this->assertTrue($this->cartera->puedeCargar($cuentaId, 99999999)['puede']);
    }

    public function testElCupoLibreBajaSegunSeCarga(): void
    {
        $cuentaId  = $this->crearCuenta(['cupo' => 1000000]);
        $reservaId = $this->reservaConSaldo(400000);

        $this->cartera->cargarReserva($reservaId, $cuentaId);

        $this->assertEqualsWithDelta(600000, (float) $this->cuentas->disponible($this->cuentas->find($cuentaId)), 0.01);
    }

    public function testUnaCuentaBloqueadaNoAdmiteCargos(): void
    {
        $cuentaId = $this->crearCuenta(['estado' => 'bloqueada', 'motivo_bloqueo' => 'PRUEBA-Lleva tres meses sin pagar']);

        $r = $this->cartera->puedeCargar($cuentaId, 100000);

        $this->assertFalse($r['puede']);
        $this->assertStringContainsString('tres meses', (string) $r['motivo']);
    }

    // ── Abonos y saldo ──────────────────────────────────────────────────

    public function testUnAbonoBajaElSaldo(): void
    {
        $cuentaId  = $this->crearCuenta();
        $reservaId = $this->reservaConSaldo(500000);

        $this->cartera->cargarReserva($reservaId, $cuentaId);
        $this->cartera->abonar($cuentaId, 200000, 'transferencia', 'TRX-1');

        $this->assertEqualsWithDelta(300000, $this->cuentas->saldo($cuentaId), 0.01);
    }

    public function testPagarDeMasDejaSaldoAFavor(): void
    {
        // Pasa de verdad: una empresa paga redondeando hacia arriba.
        $cuentaId  = $this->crearCuenta();
        $reservaId = $this->reservaConSaldo(450000);

        $this->cartera->cargarReserva($reservaId, $cuentaId);
        $this->cartera->abonar($cuentaId, 500000, 'transferencia');

        $this->assertEqualsWithDelta(-50000, $this->cuentas->saldo($cuentaId), 0.01);
    }

    public function testUnAbonoDeCeroRebota(): void
    {
        $this->expectException(RuntimeException::class);
        $this->cartera->abonar($this->crearCuenta(), 0, 'efectivo');
    }

    public function testUnAjusteSinMotivoRebota(): void
    {
        // Dentro de seis meses nadie sabrá qué fue ese ajuste.
        $this->expectException(RuntimeException::class);
        $this->cartera->ajustar($this->crearCuenta(), 50000, '   ');
    }

    public function testUnAjusteNegativoBajaLaDeuda(): void
    {
        $cuentaId  = $this->crearCuenta();
        $reservaId = $this->reservaConSaldo(300000);

        $this->cartera->cargarReserva($reservaId, $cuentaId);
        $this->cartera->ajustar($cuentaId, -50000, 'PRUEBA-Nota de crédito por una queja');

        $this->assertEqualsWithDelta(250000, $this->cuentas->saldo($cuentaId), 0.01);
    }

    // ── Antigüedad ──────────────────────────────────────────────────────

    public function testLaDeudaSeRepartePorAntiguedad(): void
    {
        $cuentaId = $this->crearCuenta();
        $mov      = new CarteraMovimientoModel();

        // Uno sin vencer y otro de hace 100 días
        $mov->insert([
            'cuenta_id' => $cuentaId, 'tipo' => 'cargo', 'concepto' => 'PRUEBA-Reciente',
            'valor' => 200000, 'fecha' => date('Y-m-d'),
            'vence_en' => date('Y-m-d', strtotime('+15 days')),
        ]);
        $mov->insert([
            'cuenta_id' => $cuentaId, 'tipo' => 'cargo', 'concepto' => 'PRUEBA-Viejo',
            'valor' => 300000, 'fecha' => date('Y-m-d', strtotime('-130 days')),
            'vence_en' => date('Y-m-d', strtotime('-100 days')),
        ]);

        $a = $this->cuentas->antiguedad($cuentaId);

        $this->assertEqualsWithDelta(200000, $a['corriente'], 0.01);
        $this->assertEqualsWithDelta(300000, $a['mas90'], 0.01);
        $this->assertEqualsWithDelta(500000, $a['total'], 0.01);
    }

    public function testUnAbonoSeImputaPrimeroALoMasViejo(): void
    {
        // Nadie paga la factura de este mes dejando pendiente la del año pasado.
        $cuentaId = $this->crearCuenta();
        $mov      = new CarteraMovimientoModel();

        $mov->insert([
            'cuenta_id' => $cuentaId, 'tipo' => 'cargo', 'concepto' => 'PRUEBA-Reciente',
            'valor' => 200000, 'fecha' => date('Y-m-d'),
            'vence_en' => date('Y-m-d', strtotime('+15 days')),
        ]);
        $mov->insert([
            'cuenta_id' => $cuentaId, 'tipo' => 'cargo', 'concepto' => 'PRUEBA-Viejo',
            'valor' => 300000, 'fecha' => date('Y-m-d', strtotime('-130 days')),
            'vence_en' => date('Y-m-d', strtotime('-100 days')),
        ]);

        $this->cartera->abonar($cuentaId, 300000, 'transferencia');

        $a = $this->cuentas->antiguedad($cuentaId);

        $this->assertEqualsWithDelta(0, $a['mas90'], 0.01, 'Lo viejo se salda primero.');
        $this->assertEqualsWithDelta(200000, $a['corriente'], 0.01);
    }

    public function testLoVencidoNoIncluyeLoQueAunEstaEnPlazo(): void
    {
        $cuentaId = $this->crearCuenta();

        (new CarteraMovimientoModel())->insert([
            'cuenta_id' => $cuentaId, 'tipo' => 'cargo', 'concepto' => 'PRUEBA-En plazo',
            'valor' => 400000, 'fecha' => date('Y-m-d'),
            'vence_en' => date('Y-m-d', strtotime('+10 days')),
        ]);

        $this->assertEqualsWithDelta(0, $this->cuentas->vencido($cuentaId), 0.01);
    }

    // ── Estado de cuenta ────────────────────────────────────────────────

    public function testElEstadoDeCuentaSalePorEscrito(): void
    {
        $cuentaId  = $this->crearCuenta(['nombre' => 'PRUEBA-Constructora del Valle']);
        $reservaId = $this->reservaConSaldo(500000);

        $this->cartera->cargarReserva($reservaId, $cuentaId);
        $texto = $this->cartera->comoTexto($cuentaId);

        $this->assertStringContainsString('ESTADO DE CUENTA', $texto);
        $this->assertStringContainsString('PRUEBA-Constructora del Valle', $texto);
        $this->assertStringContainsString('ANTIGÜEDAD', $texto);
    }

    // ── Permisos y pantallas ────────────────────────────────────────────

    private function como(string $perfil): array
    {
        $rol = (new RolModel())->porClave($perfil);
        $this->assertNotNull($rol);

        return [
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    public function testLasPantallasSeAbren(): void
    {
        $cuentaId = $this->crearCuenta();
        $sesion   = $this->como('caja');

        foreach (['cartera', 'cartera/ver/' . $cuentaId] as $ruta) {
            $r = $this->withSession($sesion)->get($ruta);

            $this->assertFalse($r->isRedirect(), "/{$ruta} rebotó.");
            $r->assertOK();
        }
    }

    public function testRecepcionVeLaCuentaPeroNoCambiaElCupo(): void
    {
        // Subir un cupo es dejar que alguien deba más: decisión de dinero.
        $cuentaId = $this->crearCuenta(['cupo' => 100000]);
        $sesion   = $this->como('recepcion');

        $this->assertFalse($this->withSession($sesion)->get('cartera')->isRedirect());

        $this->withSession($sesion)->post('cartera/guardar/' . $cuentaId, [
            'csrf_test_name' => csrf_hash(),
            'nombre'         => 'PRUEBA-Empresa',
            'cupo'           => '99999999',
        ]);

        $this->assertEqualsWithDelta(100000, (float) $this->cuentas->find($cuentaId)['cupo'], 0.01);
    }

    public function testRestauranteNoVeLaCartera(): void
    {
        $this->assertTrue($this->withSession($this->como('restaurante'))->get('cartera')->isRedirect());
    }
}
