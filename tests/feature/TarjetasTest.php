<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Tarjetas;
use App\Models\TarjetaModel;
use App\Models\TarjetaMovimientoModel;
use App\Models\TipoTarjetaModel;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;

/**
 * Tarjetas de saldo.
 *
 * Aquí hay dinero de clientes, así que lo que se defiende no es que la pantalla
 * se vea bien:
 *
 * 1. **El saldo guardado no puede separarse de su historial.** Se guarda para
 *    poder descontarlo de forma atómica al cobrar, y eso abre la puerta a que un
 *    día se despegue de los movimientos. Cada prueba termina comprobando que no
 *    hay descuadres.
 * 2. **No se puede gastar más de lo que hay.** Ni a la vez, ni engañando al
 *    ámbito, ni con la tarjeta congelada.
 * 3. **El descuento se regala, no se esconde.** Una tarjeta con descuento gasta
 *    menos saldo, y la diferencia queda apuntada como descuento. Si se cobrara
 *    menos y ya está, nadie sabría nunca cuánto cuesta el programa.
 * 4. **El PIN protege de verdad.** Por encima del importe configurado, sin PIN
 *    correcto no sale dinero.
 *
 * @internal
 */
final class TarjetasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Tarjetas $motor;
    private TarjetaModel $tarjetas;
    private TipoTarjetaModel $tipos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->motor    = new Tarjetas();
        $this->tarjetas = new TarjetaModel();
        $this->tipos    = new TipoTarjetaModel();
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
            $db->table('tipos_tarjeta')->select('id')->like('clave', 'prueba-', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            foreach ($db->table('tarjetas')->select('id')->where('tipo_id', $id)->get()->getResultArray() as $t) {
                $db->table('tarjeta_movimientos')->where('tarjeta_id', $t['id'])->delete();
            }

            $db->table('tarjetas')->where('tipo_id', $id)->delete();
        }

        $db->table('tipos_tarjeta')->like('clave', 'prueba-', 'after')->delete();
    }

    /** @param array<string, mixed> $extra */
    private function tipo(array $extra = []): int
    {
        $this->tipos->insert(array_merge([
            'clave'         => 'prueba-' . random_int(1000, 9999),
            'nombre'        => 'Prueba',
            'recargable'    => 1,
            'acumula'       => 1,
            'caduca_meses'  => null,
            'bonus_pct'     => 0,
            'descuento_pct' => 0,
            'ambito'        => 'todo',
            'pin_desde'     => 0,
            'color'         => 'primary',
            'activo'        => 1,
        ], $extra));

        return (int) db_connect()->insertID();
    }

    /** @param array<string, mixed> $extra */
    private function tarjeta(float $saldo = 0, array $tipoExtra = [], array $extra = []): int
    {
        $id = $this->motor->emitir($this->tipo($tipoExtra), 'Prueba Titular', $extra);

        if ($saldo > 0) {
            $this->motor->cargar($id, $saldo);
        }

        return $id;
    }

    private function saldo(int $id): float
    {
        return round((float) $this->tarjetas->find($id)['saldo'], 2);
    }

    /** El saldo guardado tiene que seguir cuadrando con sus movimientos. */
    private function cuadra(): void
    {
        $this->assertSame([], $this->motor->descuadres(), 'El saldo guardado se separó de su historial.');
    }

    // ── Cargar ──────────────────────────────────────────────────────────

    public function testCargarSumaSaldoYDejaRastro(): void
    {
        $id = $this->tarjeta();
        $r  = $this->motor->cargar($id, 100000, 'efectivo', 'recibo-1');

        $this->assertSame(100000.0, $r['cargado']);
        $this->assertSame(100000.0, $r['saldo']);
        $this->assertSame(100000.0, $this->saldo($id));

        $movs = (new TarjetaMovimientoModel())->deTarjeta($id);
        $this->assertCount(1, $movs);
        $this->assertSame('carga', $movs[0]['tipo']);
        $this->assertSame('efectivo', $movs[0]['origen']);
        $this->cuadra();
    }

    /**
     * El regalo por recargar es un movimiento aparte del dinero cargado.
     *
     * Mezclarlos haría imposible saber cuánto cuesta el programa de
     * fidelización: lo cargado es una deuda con el cliente, el regalo es gasto
     * de marketing.
     */
    public function testElRegaloPorRecargarSeApuntaAparte(): void
    {
        $id = $this->tarjeta(0, ['bonus_pct' => 10]);
        $r  = $this->motor->cargar($id, 100000);

        $this->assertSame(100000.0, $r['cargado']);
        $this->assertSame(10000.0, $r['bonus']);
        $this->assertSame(110000.0, $this->saldo($id));

        $tipos = array_column((new TarjetaMovimientoModel())->deTarjeta($id), 'tipo');
        $this->assertContains('carga', $tipos);
        $this->assertContains('bonus', $tipos);
        $this->cuadra();
    }

    /**
     * Una modalidad que no acumula es un auxilio mensual, no un monedero: la
     * carga nueva sustituye a lo que quedaba, y lo que se pierde queda escrito.
     */
    public function testSinAcumularLaCargaSustituyeElSaldoYSeApuntaLoPerdido(): void
    {
        $id = $this->tarjeta(0, ['acumula' => 0]);
        $this->motor->cargar($id, 100000);
        $this->motor->cobrar($this->tarjetas->find($id)['codigo'], 30000);

        $this->assertSame(70000.0, $this->saldo($id));

        $this->motor->cargar($id, 100000);
        $this->assertSame(100000.0, $this->saldo($id));

        $ajustes = array_filter(
            (new TarjetaMovimientoModel())->deTarjeta($id),
            static fn (array $m): bool => $m['tipo'] === 'ajuste'
        );
        $this->assertCount(1, $ajustes, 'Lo que se perdió al renovar tiene que quedar escrito.');
        $this->cuadra();
    }

    public function testUnaModalidadNoRecargableNoAdmiteUnaSegundaCarga(): void
    {
        $id = $this->tarjeta(50000, ['recargable' => 0]);

        $this->expectException(RuntimeException::class);
        $this->motor->cargar($id, 10000);
    }

    // ── Cobrar ──────────────────────────────────────────────────────────

    public function testCobrarDescuentaElSaldoYLoDeja(): void
    {
        $id     = $this->tarjeta(100000);
        $codigo = $this->tarjetas->find($id)['codigo'];

        $r = $this->motor->cobrar($codigo, 30000, 'todo', ['concepto' => 'Cena']);

        $this->assertSame(30000.0, $r['cobrado']);
        $this->assertSame(0.0, $r['falta']);
        $this->assertSame(70000.0, $r['saldo']);
        $this->assertSame(70000.0, $this->saldo($id));
        $this->cuadra();
    }

    /**
     * El descuento no es «pagar menos»: sale menos saldo y la diferencia queda
     * apuntada como lo que se regaló.
     */
    public function testElDescuentoSaleDelSaldoYQuedaApuntado(): void
    {
        $id     = $this->tarjeta(100000, ['descuento_pct' => 20]);
        $codigo = $this->tarjetas->find($id)['codigo'];

        $r = $this->motor->cobrar($codigo, 50000);

        $this->assertSame(10000.0, $r['descuento'], 'El 20 % de 50.000 son 10.000.');
        $this->assertSame(40000.0, $r['cobrado'], 'De la tarjeta solo salen los otros 40.000.');
        $this->assertSame(60000.0, $this->saldo($id));
        $this->cuadra();
    }

    /** El descuento propio de una tarjeta pisa al de su modalidad. */
    public function testElDescuentoPropioPisaElDeLaModalidad(): void
    {
        $tipoId = $this->tipo(['descuento_pct' => 10]);
        $id     = $this->motor->emitir($tipoId, 'Prueba Titular', ['descuento_pct' => 50]);
        $this->motor->cargar($id, 100000);

        $r = $this->motor->cobrar($this->tarjetas->find($id)['codigo'], 40000);

        $this->assertSame(20000.0, $r['descuento']);
        $this->assertSame(20000.0, $r['cobrado']);
        $this->cuadra();
    }

    /** Si el saldo no llega, se cubre lo que se pueda y se dice cuánto falta. */
    public function testSiElSaldoNoLlegaSeCubreLoQueHayYSeAvisaDelResto(): void
    {
        $id     = $this->tarjeta(15000);
        $codigo = $this->tarjetas->find($id)['codigo'];

        $r = $this->motor->cobrar($codigo, 50000);

        $this->assertSame(15000.0, $r['cobrado']);
        $this->assertSame(35000.0, $r['falta']);
        $this->assertSame(0.0, $this->saldo($id));
        $this->cuadra();
    }

    public function testNoSePuedeGastarUnaTarjetaSinSaldo(): void
    {
        $id     = $this->tarjeta();
        $codigo = $this->tarjetas->find($id)['codigo'];

        $this->expectException(RuntimeException::class);
        $this->motor->cobrar($codigo, 1000);
    }

    /**
     * El saldo no puede quedar en negativo aunque dos cobros lleguen a la vez.
     *
     * Se simula la carrera cobrando dos veces sobre la misma lectura: el
     * descuento condicional de la base de datos tiene que dejar pasar solo al
     * primero por lo que quedaba.
     */
    public function testDosCobrosSeguidosNoDejanElSaldoEnNegativo(): void
    {
        $id     = $this->tarjeta(50000);
        $codigo = $this->tarjetas->find($id)['codigo'];

        $this->motor->cobrar($codigo, 40000);

        try {
            $this->motor->cobrar($codigo, 40000);
        } catch (RuntimeException $e) {
            // Puede fallar o cubrir solo lo que quedaba; lo que no puede es
            // dejar la tarjeta debiendo dinero.
        }

        $this->assertGreaterThanOrEqual(0.0, $this->saldo($id));
        $this->cuadra();
    }

    // ── Ámbito, estado y caducidad ──────────────────────────────────────

    public function testUnaTarjetaDeRestauranteNoPagaAlojamiento(): void
    {
        $id     = $this->tarjeta(100000, ['ambito' => 'restaurante']);
        $codigo = $this->tarjetas->find($id)['codigo'];

        $r = $this->motor->simular($codigo, 10000, 'alojamiento');

        $this->assertFalse($r['ok']);
        $this->assertSame(100000.0, $this->saldo($id));
    }

    public function testUnaTarjetaCongeladaNoPagaYSuSaldoSigueIntacto(): void
    {
        $id     = $this->tarjeta(100000);
        $codigo = $this->tarjetas->find($id)['codigo'];

        $this->motor->cambiarEstado($id, 'congelada', 'La perdió el cliente');

        $this->assertFalse($this->motor->simular($codigo, 10000)['ok']);
        $this->assertSame(100000.0, $this->saldo($id), 'Congelar no es quitarle el dinero a nadie.');
        $this->cuadra();
    }

    /** Congelar sin decir por qué deja a quien atienda mañana sin saber qué hacer. */
    public function testNoSePuedeCongelarSinMotivo(): void
    {
        $id = $this->tarjeta(1000);

        $this->expectException(RuntimeException::class);
        $this->motor->cambiarEstado($id, 'congelada', '   ');
    }

    /** Anular con saldo dentro es quedarse con dinero: tiene que quedar escrito. */
    public function testAnularConSaldoLoRetiraYLoDejaApuntado(): void
    {
        $id = $this->tarjeta(80000);

        $this->motor->cambiarEstado($id, 'anulada', 'Duplicada por error');

        $this->assertSame(0.0, $this->saldo($id));

        $ajustes = array_filter(
            (new TarjetaMovimientoModel())->deTarjeta($id),
            static fn (array $m): bool => $m['tipo'] === 'ajuste'
        );
        $this->assertCount(1, $ajustes);
        $this->assertSame(-80000.0, round((float) reset($ajustes)['valor'], 2));
        $this->cuadra();
    }

    public function testUnaTarjetaCaducadaNoPaga(): void
    {
        $id = $this->tarjeta(50000);
        $this->tarjetas->update($id, ['caduca' => date('Y-m-d', strtotime('-1 day'))]);

        $r = $this->motor->simular($this->tarjetas->find($id)['codigo'], 1000);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('caduc', mb_strtolower((string) $r['motivo']));
    }

    // ── PIN ─────────────────────────────────────────────────────────────

    public function testPorEncimaDelImportePactadoElPinEsObligatorio(): void
    {
        $id     = $this->motor->emitir($this->tipo(['pin_desde' => 20000]), 'Prueba Titular', ['pin' => '1234']);
        $this->motor->cargar($id, 100000);
        $codigo = $this->tarjetas->find($id)['codigo'];

        // Por debajo del listón pasa sin PIN
        $this->assertFalse($this->motor->simular($codigo, 5000)['pide_pin']);
        $this->motor->cobrar($codigo, 5000);

        // Por encima, lo pide
        $this->assertTrue($this->motor->simular($codigo, 50000)['pide_pin']);

        try {
            $this->motor->cobrar($codigo, 50000, 'todo', ['pin' => '9999']);
            $this->fail('Un PIN equivocado no puede sacar dinero.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('PIN', $e->getMessage());
        }

        $this->assertSame(95000.0, $this->saldo($id), 'El intento fallido no puede haber tocado el saldo.');

        // Con el PIN bueno, sí
        $this->motor->cobrar($codigo, 50000, 'todo', ['pin' => '1234']);
        $this->assertSame(45000.0, $this->saldo($id));
        $this->cuadra();
    }

    public function testElPinNoSeGuardaEnClaro(): void
    {
        $id = $this->motor->emitir($this->tipo(), 'Prueba Titular', ['pin' => '4321']);

        $hash = (string) $this->tarjetas->find($id)['pin_hash'];

        $this->assertNotSame('4321', $hash);
        $this->assertTrue(password_verify('4321', $hash));
    }

    public function testUnPinQueNoSonCuatroASeisDigitosSeRechaza(): void
    {
        $this->expectException(RuntimeException::class);
        $this->motor->emitir($this->tipo(), 'Prueba Titular', ['pin' => '12']);
    }

    // ── Códigos y devoluciones ──────────────────────────────────────────

    /**
     * El código se dicta por teléfono cuando el QR se borra, así que no puede
     * llevar las letras y los números que se confunden al oírlos.
     */
    public function testElCodigoNoLlevaCaracteresQueSeConfundenAlDictarlos(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $codigo = $this->tarjetas->siguienteCodigo();

            $this->assertMatchesRegularExpression('/^TJ-[A-Z0-9]{6}$/', $codigo);
            $this->assertDoesNotMatchRegularExpression('/[OI015S]/', substr($codigo, 3));
        }
    }

    public function testDevolverDevuelveElSaldoYQuedaEscrito(): void
    {
        $id     = $this->tarjeta(50000);
        $codigo = $this->tarjetas->find($id)['codigo'];

        $this->motor->cobrar($codigo, 20000);
        $this->motor->devolver($id, 20000, 'Comanda anulada');

        $this->assertSame(50000.0, $this->saldo($id));

        $tipos = array_column((new TarjetaMovimientoModel())->deTarjeta($id), 'tipo');
        $this->assertContains('devolucion', $tipos);
        $this->cuadra();
    }

    /**
     * El saldo en circulación es una deuda del hotel, no un ingreso, y por eso
     * las anuladas no cuentan: ese dinero ya no se debe.
     */
    public function testElSaldoEnCirculacionNoCuentaLasAnuladas(): void
    {
        $antes = $this->tarjetas->saldoEnCirculacion();

        $viva     = $this->tarjeta(30000);
        $muerta   = $this->tarjeta(70000);
        $this->motor->cambiarEstado($muerta, 'anulada', 'Prueba');

        $this->assertSame(round($antes + 30000, 2), $this->tarjetas->saldoEnCirculacion());
        $this->assertGreaterThan(0, $viva);
        $this->cuadra();
    }

    /**
     * El detector de descuadres tiene que detectar de verdad: si no, la
     * pantalla de tarjetas daría una tranquilidad falsa.
     */
    public function testElDetectorDeDescuadresCazaUnSaldoTocadoPorFuera(): void
    {
        $id = $this->tarjeta(40000);
        $this->cuadra();

        db_connect()->query('UPDATE tarjetas SET saldo = saldo + 5000 WHERE id = ?', [$id]);

        $malas = $this->motor->descuadres();
        $this->assertNotSame([], $malas);
        $this->assertSame($id, (int) $malas[0]['tarjeta']['id']);

        // Se deja como estaba para no ensuciar las pruebas siguientes
        db_connect()->query('UPDATE tarjetas SET saldo = saldo - 5000 WHERE id = ?', [$id]);
    }

    // ── Las pantallas ───────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function comoGerencia(): array
    {
        $rol = (new RolModel())->porClave('gerencia');
        $this->assertNotNull($rol, 'Falta el perfil «gerencia».');

        return [
            'usuario_id'     => 1,
            'usuario_nombre' => 'Prueba',
            'usuario_rol'    => 'gerencia',
            'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    /**
     * Que las pantallas rendericen enteras.
     *
     * Una vista a la que le falta una variable no rompe hasta que alguien la
     * abre, y con dinero de por medio eso se descubre en el peor momento.
     */
    public function testLasPantallasDelPanelSeAbrenSinRomperse(): void
    {
        $id = $this->tarjeta(50000, ['descuento_pct' => 15, 'bonus_pct' => 10]);

        foreach (['tarjetas', 'tarjetas/nueva', 'tarjetas/tipos',
                  'tarjetas/ver/' . $id, 'tarjetas/imprimir/' . $id] as $ruta) {
            $r = $this->withSession($this->comoGerencia())->get($ruta);

            $this->assertFalse($r->isRedirect(), "/{$ruta} rebotó.");
            $r->assertOK();
            $this->assertStringNotContainsString('Whoops', (string) $r->getBody(), "/{$ruta} peta.");
        }
    }

    /** El QR sale como imagen, no como una página de error. */
    public function testElQrSeSirveComoImagen(): void
    {
        $id = $this->tarjeta(1000);
        $r  = $this->withSession($this->comoGerencia())->get('tarjetas/qr/' . $id);

        $r->assertOK();
        $this->assertStringContainsString('<svg', (string) $r->getBody());
    }

    /**
     * La pantalla pública no puede enseñar ni el saldo ni el titular sin PIN.
     *
     * Un QR se fotografía sin querer en cualquier mesa. Si esta pantalla
     * contara lo que lleva dentro, la foto valdría tanto como la tarjeta.
     */
    public function testLaPantallaPublicaNoEnsenaElSaldoSinPin(): void
    {
        $id = $this->motor->emitir($this->tipo(), 'Prueba Titular', ['pin' => '1234']);
        $this->motor->cargar($id, 123456);
        $codigo = $this->tarjetas->find($id)['codigo'];

        $cuerpo = (string) $this->get('tarjeta/' . $codigo)->getBody();

        $this->assertStringContainsString($codigo, $cuerpo, 'Sí puede verse que la tarjeta existe.');
        $this->assertStringNotContainsString('123.456', $cuerpo, 'El saldo no puede salir sin PIN.');
        $this->assertStringNotContainsString('Prueba Titular', $cuerpo, 'El nombre tampoco.');
    }

    /** Y una tarjeta anulada no cuenta nada de nada. */
    public function testLaPantallaPublicaDeUnaTarjetaAnuladaNoDiceNada(): void
    {
        $id = $this->tarjeta(10000);
        $this->motor->cambiarEstado($id, 'anulada', 'Prueba');
        $codigo = $this->tarjetas->find($id)['codigo'];

        $cuerpo = (string) $this->get('tarjeta/' . $codigo)->getBody();

        $this->assertStringNotContainsString($codigo, $cuerpo);
        $this->assertStringNotContainsString('Prueba Titular', $cuerpo);
    }
}
