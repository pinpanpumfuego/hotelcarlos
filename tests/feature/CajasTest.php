<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Cajas;
use App\Models\CajaTurnoModel;
use App\Models\MedioPagoModel;
use App\Models\PuntoCajaModel;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;

/**
 * Cajas por punto, arqueo y medios de pago.
 *
 * La prueba que justifica el módulo entero es una: **en el arqueo solo se
 * cuenta lo que de verdad está dentro del cajón**. Si un cobro con tarjeta
 * sumara al esperado, la caja «faltaría» exactamente eso todos los días, nadie
 * se fiaría del descuadre, y un robo pequeño no se notaría nunca.
 *
 * Y la segunda: **un retiro no es un gasto**. Es plata que sale del cajón y
 * sigue siendo del hotel.
 *
 * @internal
 */
final class CajasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Cajas $cajas;
    private CajaTurnoModel $turnos;
    private int $puntoId;
    private int $otroPuntoId;

    private const USUARIO = 7;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cajas  = new Cajas();
        $this->turnos = new CajaTurnoModel();
        $this->limpiar();

        $this->puntoId     = $this->crearPunto('PRUEBA-Recepción');
        $this->otroPuntoId = $this->crearPunto('PRUEBA-Bar');
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $puntos = array_column(
            $db->table('puntos_caja')->select('id')->like('nombre', 'PRUEBA-', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($puntos as $p) {
            $turnos = array_column(
                $db->table('caja_turnos')->select('id')->where('punto_id', $p)->get()->getResultArray(),
                'id'
            );

            foreach ($turnos as $t) {
                $db->table('arqueo_denominaciones')->where('turno_id', $t)->delete();
                $db->table('caja_movimientos')->where('turno_id', $t)->delete();
            }

            $db->table('caja_turnos')->where('punto_id', $p)->delete();
        }

        $db->table('puntos_caja')->like('nombre', 'PRUEBA-', 'after')->delete();
    }

    private function crearPunto(string $nombre, float $tolerancia = 2000): int
    {
        (new PuntoCajaModel())->insert([
            'clave'      => strtolower(str_replace([' ', 'ó', 'É'], ['-', 'o', 'e'], $nombre)) . '-' . random_int(100, 999),
            'nombre'     => $nombre,
            'tipo'       => 'otro',
            'tolerancia' => $tolerancia,
            'activo'     => 1,
        ]);

        return (int) db_connect()->insertID();
    }

    // ── Lo que entra en el cajón ────────────────────────────────────────

    public function testUnCobroConTarjetaNoSumaAlEfectivoEsperado(): void
    {
        // ESTA es la prueba que justifica el módulo. Si sumara, la caja
        // faltaría exactamente lo cobrado con tarjeta todos los días.
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 200000);

        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Cobro efectivo', 100000, 'efectivo');
        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Cobro tarjeta', 500000, 'tarjeta', 'APR-9988');

        $this->assertEqualsWithDelta(300000, $this->cajas->esperado($turnoId), 0.01);
    }

    public function testLoCobradoPorOtrosMediosSeVeAparte(): void
    {
        // No entra en el cajón, pero hay que poder cuadrarlo con el datáfono.
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);

        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Tarjeta', 300000, 'tarjeta', 'APR-1');
        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Transferencia', 200000, 'transferencia', 'TRX-1');

        $totales = $this->cajas->totales($turnoId);

        $this->assertEqualsWithDelta(500000, array_sum($totales['otros_medios']), 0.01);
        $this->assertEqualsWithDelta(0, $totales['ingresos'], 0.01, 'Nada de eso es efectivo.');
        $this->assertEqualsWithDelta(500000, $totales['total_cobrado'], 0.01, 'Pero sí se cobró.');
    }

    public function testUnRetiroNoEsUnGasto(): void
    {
        // Sale del cajón pero sigue siendo del hotel: mezclarlo con los gastos
        // hace que el día parezca que costó lo que está en la caja fuerte.
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);

        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Cobro', 400000, 'efectivo');
        $this->cajas->retirar($this->puntoId, 300000, 'PRUEBA-A la caja fuerte', self::USUARIO);

        $totales = $this->cajas->totales($turnoId);

        $this->assertEqualsWithDelta(300000, $totales['retiros'], 0.01);
        $this->assertEqualsWithDelta(0, $totales['egresos'], 0.01, 'Un retiro no es un gasto.');
        $this->assertEqualsWithDelta(200000, $this->cajas->esperado($turnoId), 0.01);
    }

    public function testNoSePuedeRetirarMasDeLoQueHay(): void
    {
        // Dejaría la caja en negativo, un estado que no existe en la realidad.
        $this->cajas->abrir($this->puntoId, self::USUARIO, 50000);

        $this->expectException(RuntimeException::class);
        $this->cajas->retirar($this->puntoId, 200000, 'PRUEBA-Demasiado', self::USUARIO);
    }

    // ── Los puntos no se mezclan ────────────────────────────────────────

    public function testCadaPuntoTieneSuPropiaCaja(): void
    {
        $recepcion = $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);
        $bar       = $this->cajas->abrir($this->otroPuntoId, self::USUARIO, 50000);

        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Cobro recepción', 200000, 'efectivo');

        $this->assertEqualsWithDelta(300000, $this->cajas->esperado($recepcion), 0.01);
        $this->assertEqualsWithDelta(50000, $this->cajas->esperado($bar), 0.01, 'El bar no ha cobrado nada.');
    }

    public function testUnPuntoNoPuedeTenerDosTurnosAbiertos(): void
    {
        // Con dos, ningún arqueo se podría explicar: los dos cuentan el mismo
        // cajón.
        $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);

        $this->expectException(RuntimeException::class);
        $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);
    }

    public function testNoSeApuntaEnUnPuntoSinTurnoAbierto(): void
    {
        $this->expectException(RuntimeException::class);
        $this->cajas->apuntar($this->otroPuntoId, 'ingreso', 'PRUEBA-Algo', 10000, 'efectivo');
    }

    // ── El arqueo ───────────────────────────────────────────────────────

    public function testElTotalDelArqueoLoCalculaElSistema(): void
    {
        // Escribir «hay 847.000» de memoria y que cuadre es demasiado fácil.
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 0);

        $total = $this->cajas->guardarConteo($turnoId, [
            100000 => 3, 50000 => 2, 10000 => 4, 1000 => 7,
        ]);

        $this->assertEqualsWithDelta(300000 + 100000 + 40000 + 7000, $total, 0.01);
    }

    public function testCerrarGuardaElEsperadoCongelado(): void
    {
        // Recalcularlo después daría otro número si alguien tocara un
        // movimiento viejo, y el descuadre firmado ese día no se podría
        // explicar.
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);
        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Cobro', 50000, 'efectivo');

        $this->cajas->cerrar($turnoId, [100000 => 1, 50000 => 1], self::USUARIO);

        $turno = $this->turnos->find($turnoId);

        $this->assertEqualsWithDelta(150000, (float) $turno['esperado'], 0.01);
        $this->assertEqualsWithDelta(150000, (float) $turno['efectivo_contado'], 0.01);
        $this->assertEqualsWithDelta(0, (float) $turno['diferencia'], 0.01);
        $this->assertNotNull($turno['cierre']);
    }

    public function testUnDescuadreGrandeExigeExplicacion(): void
    {
        // Dentro de un mes nadie se acordará de por qué faltaban treinta mil,
        // y sin explicación solo queda la sospecha.
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);

        $this->expectException(RuntimeException::class);
        $this->cajas->cerrar($turnoId, [50000 => 1], self::USUARIO);
    }

    public function testConExplicacionSiSePuedeCerrarDescuadrado(): void
    {
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);

        $r = $this->cajas->cerrar($turnoId, [50000 => 1], self::USUARIO, 'PRUEBA-Se pagó un taxi sin apuntar.');

        $this->assertEqualsWithDelta(-50000, $r['diferencia'], 0.01);
        $this->assertNotNull($this->turnos->find($turnoId)['justificacion']);
    }

    public function testUnDescuadrePequenoNoExigeExplicacion(): void
    {
        // Un redondeo de mil pesos no es un incidente: pedir un informe por
        // eso hace que se escriba «cuadró» sin mirar.
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);

        $r = $this->cajas->cerrar($turnoId, [50000 => 1, 20000 => 2, 5000 => 1, 2000 => 2], self::USUARIO);

        $this->assertEqualsWithDelta(-1000, $r['diferencia'], 0.01);
    }

    public function testUnTurnoCerradoNoSeCierraDosVeces(): void
    {
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 0);
        $this->cajas->cerrar($turnoId, [], self::USUARIO);

        $this->expectException(RuntimeException::class);
        $this->cajas->cerrar($turnoId, [], self::USUARIO);
    }

    public function testTrasCerrarSePuedeAbrirOtro(): void
    {
        $primero = $this->cajas->abrir($this->puntoId, self::USUARIO, 0);
        $this->cajas->cerrar($primero, [], self::USUARIO);

        $segundo = $this->cajas->abrir($this->puntoId, self::USUARIO, 50000);

        $this->assertNotSame($primero, $segundo);
    }

    // ── Validaciones ────────────────────────────────────────────────────

    public function testUnMedioQuePideReferenciaNoPasaSinElla(): void
    {
        // Un datáfono sin número de aprobación es un cobro que no se puede
        // reclamar el día que el banco no lo abone.
        $this->cajas->abrir($this->puntoId, self::USUARIO, 0);

        $this->expectException(RuntimeException::class);
        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Tarjeta', 100000, 'tarjeta');
    }

    public function testUnMovimientoSinConceptoRebota(): void
    {
        $this->cajas->abrir($this->puntoId, self::USUARIO, 0);

        $this->expectException(RuntimeException::class);
        $this->cajas->apuntar($this->puntoId, 'ingreso', '   ', 100000, 'efectivo');
    }

    public function testUnValorNegativoRebota(): void
    {
        $this->cajas->abrir($this->puntoId, self::USUARIO, 0);

        $this->expectException(RuntimeException::class);
        $this->cajas->apuntar($this->puntoId, 'ingreso', 'PRUEBA-Algo', -5000, 'efectivo');
    }

    public function testUnaBaseNegativaRebota(): void
    {
        $this->expectException(RuntimeException::class);
        $this->cajas->abrir($this->puntoId, self::USUARIO, -100);
    }

    public function testLosGastosSiemprSalenDelEfectivo(): void
    {
        // Un gasto pagado «con tarjeta» desde el cajón no tiene sentido: el
        // cajón solo tiene billetes.
        $turnoId = $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);
        $this->cajas->apuntar($this->puntoId, 'egreso', 'PRUEBA-Ferretería', 30000, 'tarjeta');

        $this->assertEqualsWithDelta(70000, $this->cajas->esperado($turnoId), 0.01);
    }

    // ── Medios de pago ──────────────────────────────────────────────────

    public function testSoloElEfectivoAfectaLaCajaPorDefecto(): void
    {
        $medios = new MedioPagoModel();

        $this->assertTrue($medios->entraEnCaja('efectivo'));

        foreach (['tarjeta', 'transferencia', 'wompi', 'bono', 'cortesia'] as $clave) {
            $this->assertFalse($medios->entraEnCaja($clave), $clave . ' no puede contarse en el cajón.');
        }
    }

    public function testUnMedioInventadoNoEntraEnCaja(): void
    {
        // Ante la duda, no. Contarlo de más descuadra el arqueo hacia arriba,
        // que es peor: parece que sobra plata.
        $this->assertFalse((new MedioPagoModel())->entraEnCaja('lo_que_sea'));
    }

    public function testCadaSitioTieneSusMedios(): void
    {
        $medios = new MedioPagoModel();

        $web = array_column($medios->disponibles('web'), 'clave');

        $this->assertContains('wompi', $web);
        $this->assertNotContains('efectivo', $web, 'No se puede pagar en efectivo por internet.');
    }

    // ── La pantalla ─────────────────────────────────────────────────────

    public function testLasPantallasSeAbren(): void
    {
        $rol = (new RolModel())->porClave('gerencia');

        $sesion = [
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ];

        foreach (['caja', 'caja/configurar'] as $ruta) {
            $r = $this->withSession($sesion)->get($ruta);

            $this->assertFalse($r->isRedirect(), "/{$ruta} rebotó.");
            $r->assertOK();
        }
    }

    public function testRecepcionNoPuedeRetirarNiConfigurar(): void
    {
        $rol = (new RolModel())->porClave('recepcion');

        $sesion = [
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ];

        $this->assertTrue($this->withSession($sesion)->get('caja/configurar')->isRedirect());

        $this->cajas->abrir($this->puntoId, self::USUARIO, 100000);
        $turnoId = (int) $this->cajas->abiertoEn($this->puntoId)['id'];

        $this->withSession($sesion + ['caja_punto' => $this->puntoId])->post('caja/retirar', [
            'csrf_test_name' => csrf_hash(),
            'valor'          => '50000',
            'motivo'         => 'PRUEBA-No debería poder',
        ]);

        $this->assertEqualsWithDelta(
            100000,
            $this->cajas->esperado($turnoId),
            0.01,
            'Recepción no puede sacar plata del cajón.'
        );
    }
}
