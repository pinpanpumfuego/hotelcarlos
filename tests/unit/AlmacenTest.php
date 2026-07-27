<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Almacen;
use App\Models\BodegaModel;
use App\Models\ExistenciaModel;
use App\Models\LoteModel;
use App\Models\MovimientoStockModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use RuntimeException;

/**
 * El motor del almacén.
 *
 * Lo que se comprueba es que **el saldo siempre se pueda explicar**: que
 * coincida con la suma de los movimientos, que el coste medio salga bien, y que
 * los casos raros —sacar de más, trasladar, ajustar— no lo desordenen.
 *
 * @internal
 */
final class AlmacenTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Almacen $almacen;
    private int $insumoId;
    private int $cocina;
    private int $bar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->almacen = new Almacen();
        $this->limpiar();

        $db = db_connect();

        $db->table('insumos')->insert([
            'nombre' => 'Trucha prueba almacén', 'unidad' => 'kg',
            'costo_unitario' => 20000, 'stock_minimo' => 5, 'activo' => 1,
            'controla_lote' => 1, 'dias_aviso_caducidad' => 3,
        ]);
        $this->insumoId = (int) $db->insertID();

        $bodegas = new BodegaModel();
        $this->cocina = (int) $bodegas->where('clave', 'cocina')->first()['id'];
        $this->bar    = (int) $bodegas->where('clave', 'bar')->first()['id'];
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();
        $insumos = $db->table('insumos')->select('id')->like('nombre', 'prueba almacén')->get()->getResultArray();

        foreach ($insumos as $i) {
            $db->table('movimientos_stock')->where('insumo_id', $i['id'])->delete();
            $db->table('lotes')->where('insumo_id', $i['id'])->delete();
            $db->table('existencias')->where('insumo_id', $i['id'])->delete();
            $db->table('insumos')->where('id', $i['id'])->delete();
        }
    }

    // ── Entradas y coste medio ──────────────────────────────────────────

    public function testUnaEntradaSubeElSaldo(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000, 'Compra inicial');

        $this->assertEqualsWithDelta(10, $this->almacen->saldo($this->insumoId, $this->cocina), 0.001);
    }

    public function testElCosteMedioSeRecalculaAlEntrarMasCaro(): void
    {
        // 10 kg a 20.000 y luego 10 kg a 30.000 → media 25.000
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000);
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 30000);

        $this->assertEqualsWithDelta(25000, $this->almacen->costoActual($this->insumoId, $this->cocina), 0.01);
    }

    public function testElCosteMedioNoCambiaAlSacar(): void
    {
        // Sacar no cambia lo que costó lo que queda.
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000);
        $this->almacen->salida($this->insumoId, $this->cocina, 4);

        $this->assertEqualsWithDelta(20000, $this->almacen->costoActual($this->insumoId, $this->cocina), 0.01);
        $this->assertEqualsWithDelta(6, $this->almacen->saldo($this->insumoId, $this->cocina), 0.001);
    }

    public function testSinExistenciasElCosteEsElDeFicha(): void
    {
        $this->assertEqualsWithDelta(20000, $this->almacen->costoActual($this->insumoId, $this->cocina), 0.01);
    }

    public function testUnaEntradaDeCeroOMenosSeRechaza(): void
    {
        $this->expectException(RuntimeException::class);
        $this->almacen->entrada($this->insumoId, $this->cocina, 0, 20000);
    }

    // ── Salidas ─────────────────────────────────────────────────────────

    public function testSePuedeSacarMasDeLoQueHayYQuedaEnNegativo(): void
    {
        // Un saldo negativo significa que se vendió algo que el sistema no
        // sabía que había. Bloquearlo dejaría a un camarero sin poder cobrar
        // una mesa por un descuadre de almacén, y eso es peor.
        $this->almacen->entrada($this->insumoId, $this->cocina, 2, 20000);
        $this->almacen->salida($this->insumoId, $this->cocina, 5);

        $this->assertEqualsWithDelta(-3, $this->almacen->saldo($this->insumoId, $this->cocina), 0.001);
    }

    public function testLosNegativosSalenEnSuLista(): void
    {
        $this->almacen->salida($this->insumoId, $this->cocina, 3);

        $nombres = array_column((new ExistenciaModel())->negativos(), 'nombre');

        $this->assertContains('Trucha prueba almacén', $nombres);
    }

    // ── Traslados ───────────────────────────────────────────────────────

    public function testUnTrasladoDejaElTotalIgual(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000);
        $this->almacen->traslado($this->insumoId, $this->cocina, $this->bar, 4);

        $this->assertEqualsWithDelta(6, $this->almacen->saldo($this->insumoId, $this->cocina), 0.001);
        $this->assertEqualsWithDelta(4, $this->almacen->saldo($this->insumoId, $this->bar), 0.001);
    }

    public function testElCosteViajaConLaMercancia(): void
    {
        // Si no, trasladar cambiaría el valor del inventario sin que nadie haya
        // comprado ni vendido nada.
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 35000);
        $this->almacen->traslado($this->insumoId, $this->cocina, $this->bar, 4);

        $this->assertEqualsWithDelta(35000, $this->almacen->costoActual($this->insumoId, $this->bar), 0.01);
    }

    public function testNoSePuedeTrasladarALaMismaBodega(): void
    {
        $this->expectException(RuntimeException::class);
        $this->almacen->traslado($this->insumoId, $this->cocina, $this->cocina, 1);
    }

    public function testUnTrasladoDejaDosMovimientos(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000);
        $this->almacen->traslado($this->insumoId, $this->cocina, $this->bar, 4);

        $traslados = (new MovimientoStockModel())
            ->where('insumo_id', $this->insumoId)
            ->where('tipo', 'traslado')
            ->countAllResults();

        $this->assertSame(2, $traslados, 'Sale de un sitio y entra en otro: dos apuntes, no uno.');
    }

    // ── Ajustes ─────────────────────────────────────────────────────────

    public function testElAjusteApuntaLaDiferenciaYNoElTotal(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000);

        // Se cuentan 8: faltan 2
        $id = $this->almacen->ajustar($this->insumoId, $this->cocina, 8, 'Conteo de prueba');

        $this->assertNotNull($id);

        $movimiento = (new MovimientoStockModel())->find($id);
        $this->assertEqualsWithDelta(-2, (float) $movimiento['cantidad'], 0.001);
        $this->assertEqualsWithDelta(8, $this->almacen->saldo($this->insumoId, $this->cocina), 0.001);
    }

    public function testUnAjusteQueCuadraNoApuntaNada(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000);

        $this->assertNull(
            $this->almacen->ajustar($this->insumoId, $this->cocina, 10),
            'Si el conteo coincide, no hay nada que explicar.'
        );
    }

    // ── El saldo siempre se puede explicar ──────────────────────────────

    public function testElSaldoCoincideConLaSumaDeLosMovimientos(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000);
        $this->almacen->salida($this->insumoId, $this->cocina, 3);
        $this->almacen->salida($this->insumoId, $this->cocina, 2, 'merma', 'Se echó a perder');
        $this->almacen->entrada($this->insumoId, $this->cocina, 5, 22000);
        $this->almacen->ajustar($this->insumoId, $this->cocina, 9);

        $guardado   = $this->almacen->saldo($this->insumoId, $this->cocina);
        $recalculado = $this->almacen->recalcular($this->insumoId, $this->cocina);

        $this->assertEqualsWithDelta($guardado, $recalculado, 0.001,
            'El saldo guardado tiene que coincidir con la suma de los movimientos.');
        $this->assertEqualsWithDelta(9, $recalculado, 0.001);
    }

    public function testCadaMovimientoGuardaElSaldoQueDejo(): void
    {
        // Permite reconstruir la película sin volver a sumarlo todo.
        $this->almacen->entrada($this->insumoId, $this->cocina, 10, 20000);
        $this->almacen->salida($this->insumoId, $this->cocina, 4);

        $ultimos = (new MovimientoStockModel())
            ->where('insumo_id', $this->insumoId)
            ->orderBy('id')
            ->findAll();

        $this->assertEqualsWithDelta(10, (float) $ultimos[0]['saldo'], 0.001);
        $this->assertEqualsWithDelta(6, (float) $ultimos[1]['saldo'], 0.001);
    }

    public function testQuedaQuienHizoCadaMovimiento(): void
    {
        session()->set(['usuario_id' => 1, 'usuario_nombre' => 'Ana Cocina']);

        $id = $this->almacen->entrada($this->insumoId, $this->cocina, 5, 20000);
        $movimiento = (new MovimientoStockModel())->find($id);

        // El nombre copiado, no solo el id: si la cuenta se borra, el
        // movimiento tiene que seguir diciendo quién lo hizo.
        $this->assertSame('Ana Cocina', $movimiento['usuario_nombre']);

        session()->remove(['usuario_id', 'usuario_nombre']);
    }

    // ── Lotes ───────────────────────────────────────────────────────────

    public function testUnaEntradaConLoteLoCrea(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 6, 20000, 'Compra', [
            'lote'      => 'L-2026-01',
            'caduca_el' => date('Y-m-d', strtotime('+2 days')),
        ]);

        $lotes = (new LoteModel())->deInsumo($this->insumoId, $this->cocina);

        $this->assertCount(1, $lotes);
        $this->assertSame('L-2026-01', $lotes[0]['codigo']);
        $this->assertEqualsWithDelta(6, (float) $lotes[0]['cantidad'], 0.001);
    }

    public function testElMismoLoteDosVecesSeSumaEnVezDeDuplicarse(): void
    {
        foreach ([4, 3] as $cantidad) {
            $this->almacen->entrada($this->insumoId, $this->cocina, $cantidad, 20000, null, ['lote' => 'L-99']);
        }

        $lotes = (new LoteModel())->deInsumo($this->insumoId, $this->cocina);

        $this->assertCount(1, $lotes, 'Un mismo código de lote es el mismo lote.');
        $this->assertEqualsWithDelta(7, (float) $lotes[0]['cantidad'], 0.001);
    }

    public function testLoQueCaducaProntoSale(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 5, 20000, null, [
            'lote'      => 'L-CADUCA',
            'caduca_el' => date('Y-m-d', strtotime('+1 day')),
        ]);

        $avisos = array_column((new LoteModel())->porCaducar(), 'codigo');

        $this->assertContains('L-CADUCA', $avisos, 'Avisa 3 días antes, y caduca mañana.');
    }

    public function testLoQueCaducaTardeNoMolesta(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 5, 20000, null, [
            'lote'      => 'L-LEJOS',
            'caduca_el' => date('Y-m-d', strtotime('+60 days')),
        ]);

        $this->assertNotContains('L-LEJOS', array_column((new LoteModel())->porCaducar(), 'codigo'));
    }

    // ── Avisos de reposición ────────────────────────────────────────────

    public function testLoQueBajaDelMinimoSaleEnLaListaDeReposicion(): void
    {
        // Mínimo 5, quedan 2
        $this->almacen->entrada($this->insumoId, $this->cocina, 2, 20000);

        $nombres = array_column((new ExistenciaModel())->bajoMinimo(), 'nombre');

        $this->assertContains('Trucha prueba almacén', $nombres);
    }

    public function testConStockDeSobraNoAvisa(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 50, 20000);

        $this->assertNotContains(
            'Trucha prueba almacén',
            array_column((new ExistenciaModel())->bajoMinimo(), 'nombre')
        );
    }

    // ── Consumo real y fugas ────────────────────────────────────────────

    public function testElConsumoRealNoCuentaLosTraslados(): void
    {
        // La mercancía trasladada sigue en casa: no se ha consumido.
        $this->almacen->entrada($this->insumoId, $this->cocina, 20, 20000);
        $this->almacen->salida($this->insumoId, $this->cocina, 5);
        $this->almacen->traslado($this->insumoId, $this->cocina, $this->bar, 8);

        $consumo = (new MovimientoStockModel())->consumoReal(date('Y-m-d'), date('Y-m-d'));

        $this->assertArrayHasKey($this->insumoId, $consumo);
        $this->assertEqualsWithDelta(5, $consumo[$this->insumoId]['cantidad'], 0.001);
    }

    public function testLasFugasSeAgrupanPorTipo(): void
    {
        $this->almacen->entrada($this->insumoId, $this->cocina, 30, 20000);
        $this->almacen->salida($this->insumoId, $this->cocina, 2, 'merma', 'Se echó a perder');
        $this->almacen->salida($this->insumoId, $this->cocina, 1, 'cortesia', 'Plato mal salido');
        $this->almacen->salida($this->insumoId, $this->cocina, 3, 'consumo_interno', 'Comida del personal');

        $fugas = [];
        foreach ((new MovimientoStockModel())->fugas(date('Y-m-d'), date('Y-m-d')) as $f) {
            $fugas[$f['tipo']] = (float) $f['valor'];
        }

        $this->assertEqualsWithDelta(40000, $fugas['merma'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(20000, $fugas['cortesia'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(60000, $fugas['consumo_interno'] ?? 0, 0.01);
    }
}
