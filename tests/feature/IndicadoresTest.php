<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Indicadores;
use App\Models\RolModel;
use App\Models\UnidadModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Los indicadores del hotel.
 *
 * Aquí no se comprueba que la pantalla se vea bien: se comprueba que las
 * cuentas están bien. Un ADR mal calculado no se nota, se usa para poner
 * precios durante meses, y cuando alguien lo compara con el de otro hotel ya se
 * han tomado veinte decisiones sobre un número falso.
 *
 * Lo que se defiende:
 *
 * 1. **El alojamiento se reparte noche a noche.** Una estancia a caballo entre
 *    dos meses deja en cada uno lo que le toca. Antes se imputaba entera al día
 *    del cargo, y eso inflaba un mes y vaciaba el siguiente.
 * 2. **El ADR es solo alojamiento.** El restaurante no entra.
 * 3. **Las canceladas no son ventas** y **las bloqueadas no son disponibles**.
 *
 * @internal
 */
final class IndicadoresTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Indicadores $ind;
    private int $huespedId;
    private array $unidades = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ind = new Indicadores();
        $this->limpiar();

        $db = db_connect();

        $db->table('huespedes')->insert([
            'nombre' => 'Ind', 'apellidos' => 'Prueba',
            'tipo_documento' => 'CC', 'num_documento' => 'PRUEBA-IND',
        ]);
        $this->huespedId = (int) $db->insertID();

        // Cabañas propias: así el número de disponibles es conocido y las
        // pruebas no dependen de lo que haya en la base de verdad.
        $tipo = $db->table('tipos_unidad')->get()->getRowArray();

        if ($tipo === null) {
            $db->table('tipos_unidad')->insert(['nombre' => 'PRUEBA-IND-Tipo', 'capacidad' => 2, 'tarifa_base' => 100000]);
            $tipo = ['id' => $db->insertID()];
        }

        for ($i = 1; $i <= 4; $i++) {
            (new UnidadModel())->insert([
                'tipo_id' => (int) $tipo['id'],
                'nombre'  => 'PRUEBA-IND-Cabaña ' . $i,
                'estado'  => 'disponible',
            ]);
            $this->unidades[] = (int) $db->insertID();
        }
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
            $db->table('huespedes')->select('id')->where('num_documento', 'PRUEBA-IND')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            foreach ($db->table('reservas')->select('id')->where('huesped_id', $id)->get()->getResultArray() as $r) {
                $db->table('folio_movimientos')->where('reserva_id', $r['id'])->delete();
            }

            $db->table('reservas')->where('huesped_id', $id)->delete();
        }

        $db->table('huespedes')->where('num_documento', 'PRUEBA-IND')->delete();
        $db->table('unidades')->like('nombre', 'PRUEBA-IND-', 'after')->delete();
        $db->table('tipos_unidad')->like('nombre', 'PRUEBA-IND-', 'after')->delete();
        $db->table('comandas')->like('mesa', 'PRUEBA-IND', 'after')->delete();
    }

    private function reservar(string $entrada, string $salida, float $total, string $estado = 'checkout', string $canal = ''): int
    {
        db_connect()->table('reservas')->insert([
            'codigo'        => 'IN' . random_int(10000, 99999),
            'huesped_id'    => $this->huespedId,
            'unidad_id'     => $this->unidades[0] ?? null,
            'fecha_entrada' => $entrada,
            'fecha_salida'  => $salida,
            'adultos'       => 2,
            'estado'        => $estado,
            'canal'         => $canal,
            'total'         => $total,
        ]);

        return (int) db_connect()->insertID();
    }

    /** Cuántas cabañas hay de verdad, incluidas las que ya existieran. */
    private function vendibles(): int
    {
        return $this->ind->cabanasVendibles();
    }

    // ── El prorrateo, que es lo que lo cambia todo ──────────────────────

    public function testElAlojamientoSeRepartENocheANoche(): void
    {
        // Diez noches a 100.000 la noche: cada noche tiene que llevar 100.000,
        // no un millón el primer día y cero los demás.
        $this->reservar('2026-03-01', '2026-03-11', 1000000);

        $noches = $this->ind->porNoche('2026-03-01', '2026-03-10');

        foreach ($noches as $fecha => $n) {
            $this->assertEqualsWithDelta(100000, $n['alojamiento'], 0.01, 'Falla el ' . $fecha);
        }
    }

    public function testUnaEstanciaACaballoDejaEnCadaMesLoQueLeToca(): void
    {
        // ESTA es la prueba que justifica el cambio. Antes, una reserva del 28
        // de marzo al 4 de abril metía TODO en marzo.
        $this->reservar('2026-03-28', '2026-04-04', 700000);   // 7 noches a 100.000

        $marzo = $this->ind->periodo('2026-03-01', '2026-03-31');
        $abril = $this->ind->periodo('2026-04-01', '2026-04-30');

        // 28, 29, 30 y 31 de marzo son 4 noches; 1, 2 y 3 de abril son 3
        $this->assertEqualsWithDelta(400000, $marzo['alojamiento'], 0.01);
        $this->assertEqualsWithDelta(300000, $abril['alojamiento'], 0.01);
        $this->assertSame(4, $marzo['vendidas']);
        $this->assertSame(3, $abril['vendidas']);
    }

    public function testElPrecioPorNocheSaleDeLaEstanciaEnteraNoDelTrozo(): void
    {
        // Dividir entre las noches que caen en el mes daría una tarifa
        // inventada, más alta cuanto más corto fuera el trozo.
        $this->reservar('2026-03-30', '2026-04-09', 1000000);   // 10 noches

        $marzo = $this->ind->periodo('2026-03-01', '2026-03-31');

        // Solo 2 noches en marzo, a 100.000 cada una
        $this->assertSame(2, $marzo['vendidas']);
        $this->assertEqualsWithDelta(200000, $marzo['alojamiento'], 0.01);
        $this->assertEqualsWithDelta(100000, (float) $marzo['adr'], 1);
    }

    public function testLosDescuentosBajanElAdr(): void
    {
        $reservaId = $this->reservar('2026-05-01', '2026-05-03', 400000);   // 2 noches

        db_connect()->table('folio_movimientos')->insert([
            'reserva_id' => $reservaId, 'tipo' => 'descuento',
            'concepto' => 'PRUEBA-Cupón', 'valor' => 100000,
        ]);

        $mayo = $this->ind->periodo('2026-05-01', '2026-05-02');

        $this->assertEqualsWithDelta(300000, $mayo['alojamiento'], 0.01);
        $this->assertEqualsWithDelta(150000, (float) $mayo['adr'], 1);
    }

    // ── Qué cuenta como venta ───────────────────────────────────────────

    public function testUnaCanceladaNoEsUnaVenta(): void
    {
        $this->reservar('2026-06-01', '2026-06-03', 400000, 'cancelada');

        $junio = $this->ind->periodo('2026-06-01', '2026-06-02');

        $this->assertSame(0, $junio['vendidas']);
        $this->assertEqualsWithDelta(0, $junio['alojamiento'], 0.01);
    }

    public function testUnaPendienteTampocoEsUnaVenta(): void
    {
        // Una reserva sin confirmar puede no llegar nunca. Contarla como venta
        // hace que la ocupación de la semana que viene parezca mejor de lo que
        // es, que es exactamente cuando se decide un precio.
        $this->reservar('2026-06-10', '2026-06-12', 400000, 'pendiente');

        $this->assertSame(0, $this->ind->periodo('2026-06-10', '2026-06-11')['vendidas']);
    }

    public function testUnNoShowNoEsUnaVenta(): void
    {
        $this->reservar('2026-06-20', '2026-06-22', 400000, 'no_show');

        $this->assertSame(0, $this->ind->periodo('2026-06-20', '2026-06-21')['vendidas']);
    }

    // ── El ADR ──────────────────────────────────────────────────────────

    public function testElAdrNoIncluyeElRestaurante(): void
    {
        // Es el error más común y hace que el ADR no sirva para comparar con
        // nadie: cada hotel lo estaría calculando distinto.
        $this->reservar('2026-07-01', '2026-07-03', 400000);

        db_connect()->table('comandas')->insert([
            'numero' => 'PRUEBA-IND-1', 'mesa' => 'PRUEBA-IND', 'estado' => 'cobrada',
            'total' => 500000, 'created_at' => '2026-07-01 20:00:00', 'updated_at' => '2026-07-01 20:00:00',
        ]);

        $julio = $this->ind->periodo('2026-07-01', '2026-07-02');

        $this->assertEqualsWithDelta(200000, (float) $julio['adr'], 1, 'El restaurante se coló en el ADR.');
        $this->assertGreaterThan($julio['revpar'], $julio['trevpar'], 'El TRevPAR sí lo incluye.');
    }

    public function testUnaCortesiaNoHundeElAdr(): void
    {
        // Ocupa cabaña pero no deja dinero. Contarla como vendida bajaría el
        // ADR sin que nadie haya rebajado un precio.
        $this->reservar('2026-08-01', '2026-08-03', 400000);   // 2 noches a 200.000
        $this->reservar('2026-08-01', '2026-08-03', 0);        // cortesía

        $agosto = $this->ind->periodo('2026-08-01', '2026-08-02');

        $this->assertSame(4, $agosto['vendidas'], 'Las cuatro noches están ocupadas.');
        $this->assertSame(2, $agosto['cortesias']);
        $this->assertEqualsWithDelta(200000, (float) $agosto['adr'], 1, 'El ADR se calcula sin las cortesías.');
    }

    public function testRevparEsAdrPorOcupacion(): void
    {
        // Es la identidad que tiene que cumplirse siempre. Si no se cumple, uno
        // de los dos está mal calculado.
        $this->reservar('2026-09-01', '2026-09-05', 800000);

        $p = $this->ind->periodo('2026-09-01', '2026-09-04');

        $this->assertEqualsWithDelta(
            (float) $p['adr'] * $p['ocupacion'] / 100,
            (float) $p['revpar'],
            2,
            'RevPAR debería ser ADR × ocupación.'
        );
    }

    // ── Disponibilidad ──────────────────────────────────────────────────

    public function testUnaCabanaBloqueadaNoCuentaComoDisponible(): void
    {
        // Meterla en el divisor haría que la ocupación bajase justo cuando el
        // hotel hace lo correcto —cerrar una cabaña averiada—, lo que empuja a
        // no cerrarla.
        $antes = $this->vendibles();

        (new UnidadModel())->update($this->unidades[0], ['estado' => 'bloqueada']);

        $this->assertSame($antes - 1, $this->ind->cabanasVendibles());
        $this->assertSame(1, $this->ind->fueraDeServicio());

        (new UnidadModel())->update($this->unidades[0], ['estado' => 'disponible']);
    }

    public function testLasNochesDisponiblesSonCabanasPorDias(): void
    {
        $p = $this->ind->periodo('2026-10-01', '2026-10-10');

        $this->assertSame(10, $p['dias']);
        $this->assertSame(10 * $this->vendibles(), $p['disponibles']);
    }

    // ── Movimiento ──────────────────────────────────────────────────────

    public function testLaEstanciaMediaUsaLaDuracionCompleta(): void
    {
        // Medirla con las noches recortadas al periodo daría siempre una
        // estancia más corta cuanto más corto fuera el informe.
        $this->reservar('2026-11-28', '2026-12-08', 1000000);   // 10 noches

        $this->assertEqualsWithDelta(10, $this->ind->estanciaMedia('2026-11-01', '2026-11-30'), 0.1);
    }

    public function testLlegadasYSalidasSeCuentanPorSuFecha(): void
    {
        $this->reservar('2026-11-05', '2026-11-08', 300000);

        $this->assertSame(1, $this->ind->llegadas('2026-11-01', '2026-11-30'));
        $this->assertSame(1, $this->ind->salidas('2026-11-01', '2026-11-30'));
        $this->assertSame(0, $this->ind->llegadas('2026-12-01', '2026-12-31'));
    }

    // ── Canales ─────────────────────────────────────────────────────────

    public function testCadaCanalSeMideAparteYDescuentaSuComision(): void
    {
        $db = db_connect();

        $booking = $this->reservar('2026-04-10', '2026-04-12', 400000, 'checkout', 'booking');
        $db->table('reservas')->where('id', $booking)->update(['comision' => 60000]);

        $this->reservar('2026-04-10', '2026-04-12', 400000, 'checkout', '');

        $canales = [];

        foreach ($this->ind->porCanal('2026-04-01', '2026-04-30') as $c) {
            $canales[$c['canal']] = $c;
        }

        $this->assertArrayHasKey('booking', $canales);
        $this->assertArrayHasKey('directo', $canales, 'Sin canal apuntado es venta directa.');

        $this->assertEqualsWithDelta(340000, $canales['booking']['neto'], 0.01);
        $this->assertEqualsWithDelta(400000, $canales['directo']['neto'], 0.01, 'La directa no paga comisión.');
        $this->assertGreaterThan($canales['booking']['adr_neto'], $canales['directo']['adr_neto']);
    }

    public function testLasCanceladasCuentanEnLaTasaDeCancelacionPeroNoEnLaProduccion(): void
    {
        $this->reservar('2026-04-20', '2026-04-22', 400000, 'checkout', 'airbnb');
        $this->reservar('2026-04-20', '2026-04-22', 400000, 'cancelada', 'airbnb');

        $airbnb = null;

        foreach ($this->ind->porCanal('2026-04-01', '2026-04-30') as $c) {
            if ($c['canal'] === 'airbnb') {
                $airbnb = $c;
            }
        }

        $this->assertNotNull($airbnb);
        $this->assertSame(2, $airbnb['reservas']);
        $this->assertSame(1, $airbnb['vendidas']);
        $this->assertEqualsWithDelta(400000, $airbnb['produccion'], 0.01, 'La cancelada no produce.');
        $this->assertEqualsWithDelta(50.0, $airbnb['cancelacion_pct'], 0.1);
    }

    // ── Comparación ─────────────────────────────────────────────────────

    public function testNoSeInventaUnPorcentajeSinBase(): void
    {
        // Crecer desde cero no es «infinito por ciento»: es que antes no había
        // nada, y decirlo con un número es mentir con precisión.
        $this->assertNull($this->ind->variacion(500, 0));
        $this->assertEqualsWithDelta(25.0, (float) $this->ind->variacion(500, 400), 0.01);
        $this->assertEqualsWithDelta(-50.0, (float) $this->ind->variacion(200, 400), 0.01);
    }

    public function testUnPeriodoSinNadaDevuelveCerosYNoRompe(): void
    {
        $p = $this->ind->periodo('2019-01-01', '2019-01-31');

        $this->assertSame(0, $p['vendidas']);
        $this->assertEqualsWithDelta(0, $p['adr'], 0.01);
        $this->assertEqualsWithDelta(0, $p['revpar'], 0.01);
        $this->assertEqualsWithDelta(0, $p['ocupacion'], 0.01);

        // Y siempre del mismo tipo, haya ventas o no: si cambiara, quien
        // compare con `===` obtendría respuestas distintas sin motivo.
        foreach (['adr', 'revpar', 'trevpar', 'ocupacion'] as $campo) {
            $this->assertIsFloat($p[$campo], $campo . ' debería ser float siempre.');
        }
    }

    // ── Pickup ──────────────────────────────────────────────────────────

    public function testElPickupMiraHaciaAdelante(): void
    {
        $mesQueViene = date('Y-m-01', strtotime('+1 month'));

        $this->reservar(
            date('Y-m-05', strtotime($mesQueViene)),
            date('Y-m-08', strtotime($mesQueViene)),
            300000,
            'confirmada'
        );

        $pickup = $this->ind->pickup(3);

        $this->assertCount(3, $pickup);
        $this->assertSame(3, $pickup[1]['noches'], 'Las tres noches del mes que viene.');
        $this->assertEqualsWithDelta(300000, $pickup[1]['produccion'], 0.01);
    }

    // ── La pantalla ─────────────────────────────────────────────────────

    public function testElCuadroDeMandoSeAbre(): void
    {
        $rol = (new RolModel())->porClave('gerencia');

        $r = $this->withSession([
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ])->get('reportes/gerencia');

        $r->assertOK();
        $r->assertSee('RevPAR');
        $r->assertSee('Cómo se calculan');
    }

    public function testRestauranteNoVeElCuadroDeMando(): void
    {
        $rol = (new RolModel())->porClave('restaurante');

        $r = $this->withSession([
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ])->get('reportes/gerencia');

        $this->assertTrue($r->isRedirect());
    }
}
