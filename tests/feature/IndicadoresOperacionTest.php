<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\IndicadoresOperacion;
use App\Libraries\Crm;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Indicadores de operación.
 *
 * Lo que se defiende:
 *
 * 1. **«Nuevo» se mide contra el historial, no contra el periodo.** Quien vino
 *    en enero y vuelve en marzo es recurrente en marzo, aunque en el informe de
 *    marzo no salga su primera visita.
 * 2. **Cortesías y devoluciones no se suman.** Una se regala y la otra la
 *    rechazó el huésped: el remedio no es el mismo.
 * 3. **Lo por cobrar es de hoy, no del periodo.** Una deuda no pertenece al mes
 *    en que nació.
 *
 * @internal
 */
final class IndicadoresOperacionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private IndicadoresOperacion $op;

    protected function setUp(): void
    {
        parent::setUp();
        $this->op = new IndicadoresOperacion();
        $this->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $ids = array_column(
            $db->table('huespedes')->select('id')->like('num_documento', 'PRUEBA-OPE', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            foreach ($db->table('reservas')->select('id')->where('huesped_id', $id)->get()->getResultArray() as $r) {
                $db->table('folio_movimientos')->where('reserva_id', $r['id'])->delete();
                $db->table('encuestas')->where('reserva_id', $r['id'])->delete();
            }

            $db->table('consentimientos')->where('huesped_id', $id)->delete();
            $db->table('reservas')->where('huesped_id', $id)->delete();
        }

        $db->table('huespedes')->like('num_documento', 'PRUEBA-OPE', 'after')->delete();

        foreach ($db->table('comandas')->select('id')->like('numero', 'PRUEBA-OPE', 'after')->get()->getResultArray() as $c) {
            $db->table('comanda_lineas')->where('comanda_id', $c['id'])->delete();
        }

        $db->table('comandas')->like('numero', 'PRUEBA-OPE', 'after')->delete();
        $db->table('mantenimientos')->like('titulo', 'PRUEBA-OPE', 'after')->delete();
    }

    private function crearHuesped(string $sufijo): int
    {
        db_connect()->table('huespedes')->insert([
            'nombre' => 'Ope', 'apellidos' => 'Prueba ' . $sufijo,
            'tipo_documento' => 'CC', 'num_documento' => 'PRUEBA-OPE-' . $sufijo,
            'pais' => 'Colombia', 'estado' => 'activo',
        ]);

        return (int) db_connect()->insertID();
    }

    private function reservar(int $huespedId, string $entrada, string $salida, string $estado = 'checkout'): int
    {
        db_connect()->table('reservas')->insert([
            'codigo' => 'OP' . random_int(10000, 99999), 'huesped_id' => $huespedId, 'unidad_id' => null,
            'fecha_entrada' => $entrada, 'fecha_salida' => $salida,
            'adultos' => 2, 'estado' => $estado, 'total' => 400000,
        ]);

        return (int) db_connect()->insertID();
    }

    // ── Huéspedes ───────────────────────────────────────────────────────

    public function testNuevoSeMideContraElHistorialNoContraElPeriodo(): void
    {
        // Quien vino en enero y vuelve en marzo es recurrente en marzo, aunque
        // el informe de marzo no vea su primera visita.
        $repetidor = $this->crearHuesped('REP');
        $this->reservar($repetidor, '2026-01-10', '2026-01-12');
        $this->reservar($repetidor, '2026-03-10', '2026-03-12');

        $nuevo = $this->crearHuesped('NUE');
        $this->reservar($nuevo, '2026-03-15', '2026-03-17');

        $h = $this->op->huespedes('2026-03-01', '2026-03-31');

        $this->assertSame(2, $h['total']);
        $this->assertSame(1, $h['nuevos']);
        $this->assertSame(1, $h['recurrentes']);
        $this->assertEqualsWithDelta(50.0, $h['repeticion'], 0.1);
    }

    public function testUnaCanceladaNoCuentaComoVisita(): void
    {
        $id = $this->crearHuesped('CAN');
        $this->reservar($id, '2026-04-10', '2026-04-12', 'cancelada');

        $this->assertSame(0, $this->op->huespedes('2026-04-01', '2026-04-30')['total']);
    }

    public function testElTechoDeUnaCampanaEsQuienLoAutorizo(): void
    {
        // Casi siempre es mucho menor de lo que la gente cree.
        $con = $this->crearHuesped('CON');
        $this->crearHuesped('SIN');

        (new Crm())->otorgar($con, 'marketing', 'email');

        $h = $this->op->huespedes('2026-05-01', '2026-05-31');

        $this->assertGreaterThanOrEqual(1, $h['con_permiso']);
        $this->assertLessThanOrEqual($h['base_activa'], $h['con_permiso']);
    }

    // ── Restaurante ─────────────────────────────────────────────────────

    private function comanda(string $estado, float $total, int $comensales = 2): int
    {
        db_connect()->table('comandas')->insert([
            'numero' => 'PRUEBA-OPE-' . random_int(1000, 9999),
            'mesa' => 'PRUEBA', 'comensales' => $comensales,
            'estado' => $estado, 'total' => $total,
            'created_at' => '2026-06-15 20:00:00', 'updated_at' => '2026-06-15 20:00:00',
        ]);

        return (int) db_connect()->insertID();
    }

    public function testElTicketMedioNoCuentaLasAnuladas(): void
    {
        // Una comanda anulada no es una venta con ticket cero: no es una venta.
        $this->comanda('cobrada', 200000);
        $this->comanda('cobrada', 100000);
        $this->comanda('anulada', 500000);

        $r = $this->op->restaurante('2026-06-01', '2026-06-30');

        $this->assertSame(2, $r['comandas']);
        $this->assertEqualsWithDelta(300000, $r['venta'], 0.01);
        $this->assertEqualsWithDelta(150000, (float) $r['ticket'], 1);
        $this->assertSame(1, $r['anuladas']);
    }

    public function testCortesiasYDevolucionesSeCuentanAparte(): void
    {
        // Una se regala y la otra la rechazó el huésped. El remedio no es el
        // mismo, así que sumarlas escondería cuál de los dos problemas hay.
        $comandaId = $this->comanda('cobrada', 100000);
        $db        = db_connect();

        foreach ([['cortesia', 30000], ['cortesia', 20000], ['devuelta', 45000]] as [$estado, $precio]) {
            $db->table('comanda_lineas')->insert([
                'comanda_id' => $comandaId, 'nombre_producto' => 'PRUEBA', 'destino' => 'cocina',
                'precio_unitario' => $precio, 'cantidad' => 1, 'estado_linea' => $estado,
                'created_at' => '2026-06-15 20:00:00', 'updated_at' => '2026-06-15 20:00:00',
            ]);
        }

        $r = $this->op->restaurante('2026-06-01', '2026-06-30');

        $this->assertSame(2, $r['cortesias']['n']);
        $this->assertEqualsWithDelta(50000, $r['cortesias']['valor'], 0.01);
        $this->assertSame(1, $r['devueltas']['n']);
        $this->assertEqualsWithDelta(45000, $r['devueltas']['valor'], 0.01);
    }

    public function testSinMarcarCocinaNoSeInventaUnTiempo(): void
    {
        // Sin los dos toques no hay tiempo que medir. Devolver un cero haría
        // creer que la cocina sirve al instante.
        $this->comanda('cobrada', 100000);

        $r = $this->op->restaurante('2026-06-01', '2026-06-30');

        $this->assertNull($r['espera']);
        $this->assertNull($r['preparacion']);
        $this->assertSame(0, $r['lineas_medidas']);
    }

    public function testConLosDosToquesSiSeMideElTiempo(): void
    {
        $comandaId = $this->comanda('cobrada', 100000);

        db_connect()->table('comanda_lineas')->insert([
            'comanda_id' => $comandaId, 'nombre_producto' => 'PRUEBA', 'destino' => 'cocina',
            'precio_unitario' => 50000, 'cantidad' => 1, 'estado_linea' => 'normal',
            'created_at'  => '2026-06-15 20:00:00',
            'recibido_en' => '2026-06-15 20:05:00',
            'listo_en'    => '2026-06-15 20:20:00',
            'updated_at'  => '2026-06-15 20:20:00',
        ]);

        $r = $this->op->restaurante('2026-06-01', '2026-06-30');

        $this->assertEqualsWithDelta(5, (float) $r['espera'], 0.5);
        $this->assertEqualsWithDelta(15, (float) $r['preparacion'], 0.5);
    }

    // ── Operación ───────────────────────────────────────────────────────

    public function testElSlaCuentaLoQueSeSalioDePlazo(): void
    {
        $db = db_connect();

        // Una a tiempo y otra tarde
        $db->table('mantenimientos')->insert([
            'titulo' => 'PRUEBA-OPE-a tiempo', 'tipo' => 'correctiva', 'estado' => 'verificada',
            'prioridad' => 'media', 'vence_en' => '2026-07-10 10:00:00',
            'resuelta_en' => '2026-07-08 10:00:00',
            'created_at' => '2026-07-05 10:00:00', 'updated_at' => '2026-07-08 10:00:00',
        ]);
        $db->table('mantenimientos')->insert([
            'titulo' => 'PRUEBA-OPE-tarde', 'tipo' => 'correctiva', 'estado' => 'verificada',
            'prioridad' => 'media', 'vence_en' => '2026-07-10 10:00:00',
            'resuelta_en' => '2026-07-20 10:00:00',
            'created_at' => '2026-07-05 10:00:00', 'updated_at' => '2026-07-20 10:00:00',
        ]);

        $o = $this->op->operacion('2026-07-01', '2026-07-31');

        $this->assertSame(2, $o['ordenes']);
        $this->assertSame(1, $o['fuera_plazo']);
        $this->assertEqualsWithDelta(50.0, $o['sla_pct'], 0.1);
    }

    public function testLasHorasDeCabanaParadaSeCuentanSoloSiBloquearon(): void
    {
        $db = db_connect();

        $db->table('mantenimientos')->insert([
            'titulo' => 'PRUEBA-OPE-bloqueo', 'tipo' => 'correctiva', 'estado' => 'verificada',
            'prioridad' => 'urgente', 'bloqueo_unidad' => 1,
            'resuelta_en' => '2026-07-03 10:00:00',
            'created_at' => '2026-07-01 10:00:00', 'updated_at' => '2026-07-03 10:00:00',
        ]);
        $db->table('mantenimientos')->insert([
            'titulo' => 'PRUEBA-OPE-sin bloqueo', 'tipo' => 'correctiva', 'estado' => 'verificada',
            'prioridad' => 'media', 'bloqueo_unidad' => 0,
            'resuelta_en' => '2026-07-05 10:00:00',
            'created_at' => '2026-07-01 10:00:00', 'updated_at' => '2026-07-05 10:00:00',
        ]);

        $o = $this->op->operacion('2026-07-01', '2026-07-31');

        $this->assertSame(1, $o['bloqueos']);
        $this->assertSame(48, $o['horas_paradas'], 'Dos días exactos.');
        $this->assertEqualsWithDelta(2.0, $o['dias_paradas'], 0.1);
    }

    public function testLoAnuladoNoCuentaComoOrdenDeTrabajo(): void
    {
        db_connect()->table('mantenimientos')->insert([
            'titulo' => 'PRUEBA-OPE-anulada', 'tipo' => 'correctiva', 'estado' => 'anulada',
            'prioridad' => 'media',
            'created_at' => '2026-07-01 10:00:00', 'updated_at' => '2026-07-01 10:00:00',
        ]);

        $this->assertSame(0, $this->op->operacion('2026-07-01', '2026-07-31')['ordenes']);
    }

    public function testSinOrdenesElSlaEsCienNoCero(): void
    {
        // Un mes sin averías no es un mes con el 0 % de cumplimiento.
        $this->assertEqualsWithDelta(100.0, $this->op->operacion('2019-01-01', '2019-01-31')['sla_pct'], 0.1);
    }

    // ── Finanzas ────────────────────────────────────────────────────────

    public function testLoPorCobrarEsDeHoyNoDelPeriodo(): void
    {
        // Una deuda no pertenece al mes en que nació, pertenece al presente.
        $id        = $this->crearHuesped('DEU');
        $reservaId = $this->reservar($id, '2020-01-10', '2020-01-12');
        $db        = db_connect();

        $db->table('folio_movimientos')->insert([
            'reserva_id' => $reservaId, 'tipo' => 'cargo',
            'concepto' => 'Alojamiento', 'valor' => 400000,
            'created_at' => '2020-01-10 12:00:00', 'updated_at' => '2020-01-10 12:00:00',
        ]);

        // Se pregunta por un periodo que no toca esa deuda de 2020
        $f = $this->op->finanzas('2026-01-01', '2026-01-31');

        $this->assertGreaterThanOrEqual(400000, $f['por_cobrar'], 'La deuda vieja sigue estando ahí.');
        $this->assertGreaterThanOrEqual(1, $f['deudores']);
    }

    public function testUnaReservaPagadaNoSaleComoDeudora(): void
    {
        $id        = $this->crearHuesped('PAG');
        $reservaId = $this->reservar($id, '2020-02-10', '2020-02-12');
        $db        = db_connect();

        $antes = $this->op->finanzas('2026-01-01', '2026-01-31')['por_cobrar'];

        foreach ([['cargo', 400000], ['pago', 400000]] as [$tipo, $valor]) {
            $db->table('folio_movimientos')->insert([
                'reserva_id' => $reservaId, 'tipo' => $tipo,
                'concepto' => 'PRUEBA', 'valor' => $valor,
                'created_at' => '2020-02-10 12:00:00', 'updated_at' => '2020-02-10 12:00:00',
            ]);
        }

        $this->assertEqualsWithDelta(
            $antes,
            $this->op->finanzas('2026-01-01', '2026-01-31')['por_cobrar'],
            0.01,
            'Una reserva pagada entera no debe sumar a lo por cobrar.'
        );
    }

    public function testSinTurnosDeCajaElCuadreEsCienNoCero(): void
    {
        $this->assertEqualsWithDelta(100.0, $this->op->finanzas('2019-01-01', '2019-01-31')['cuadre_pct'], 0.1);
    }

    // ── La pantalla ─────────────────────────────────────────────────────

    public function testLaPantallaSeAbre(): void
    {
        $rol = (new RolModel())->porClave('gerencia');

        $r = $this->withSession([
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ])->get('reportes/operacion');

        $r->assertOK();
        $r->assertSee('Huéspedes');
        $r->assertSee('Restaurante');
    }

    public function testRecepcionNoVeElDineroEnLaPantallaDeOperacion(): void
    {
        // Recepción tiene `reportes.ocupacion` pero no `reportes.ingresos`:
        // ve cómo va la operación, no los márgenes.
        $rol = (new RolModel())->porClave('recepcion');

        $r = $this->withSession([
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ])->get('reportes/operacion');

        $r->assertOK();
        $r->assertDontSee('Descuadre acumulado');
        $r->assertDontSee('Anticipos en casa');
    }
}
