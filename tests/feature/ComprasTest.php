<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Almacen;
use App\Libraries\CostosCocina;
use App\Models\BodegaModel;
use App\Models\OrdenCompraLineaModel;
use App\Models\OrdenCompraModel;
use App\Models\ProveedorModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Compras con recepción parcial, y los informes de costes.
 *
 * La recepción parcial no es un caso raro: **es lo normal**. El proveedor trae
 * ocho de los diez kilos pedidos. Un sistema que solo sepa «recibido» o «no
 * recibido» obliga a mentir, y a partir de ahí el stock deja de cuadrar.
 *
 * @internal
 */
final class ComprasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private OrdenCompraModel $ordenes;
    private OrdenCompraLineaModel $lineas;
    private Almacen $almacen;
    private int $proveedorId;
    private int $insumoId;
    private int $bodegaId;
    private int $ordenId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ordenes = new OrdenCompraModel();
        $this->lineas  = new OrdenCompraLineaModel();
        $this->almacen = new Almacen();
        $this->limpiar();

        $db = db_connect();

        $this->proveedorId = (int) (new ProveedorModel())->insert([
            'nombre' => 'Proveedor prueba compras', 'dias_entrega' => 2, 'activo' => 1,
        ], true);

        $db->table('insumos')->insert([
            'nombre' => 'Arroz prueba compras', 'unidad' => 'kg',
            'costo_unitario' => 3000, 'activo' => 1, 'controla_lote' => 1,
        ]);
        $this->insumoId = (int) $db->insertID();

        $this->bodegaId = (int) (new BodegaModel())->where('clave', 'cocina')->first()['id'];

        $this->ordenId = (int) $this->ordenes->insert([
            'numero'       => $this->ordenes->generarNumero(),
            'proveedor_id' => $this->proveedorId,
            'bodega_id'    => $this->bodegaId,
            'estado'       => 'borrador',
            'fecha'        => date('Y-m-d'),
        ], true);
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $proveedores = $db->table('proveedores')->select('id')->like('nombre', 'prueba compras')->get()->getResultArray();
        foreach ($proveedores as $p) {
            $ordenes = $db->table('ordenes_compra')->select('id')->where('proveedor_id', $p['id'])->get()->getResultArray();
            foreach ($ordenes as $o) {
                $db->table('orden_compra_lineas')->where('orden_id', $o['id'])->delete();
                $db->table('ordenes_compra')->where('id', $o['id'])->delete();
            }
            $db->table('proveedores')->where('id', $p['id'])->delete();
        }

        $insumos = $db->table('insumos')->select('id')->like('nombre', 'prueba compras')->get()->getResultArray();
        foreach ($insumos as $i) {
            $db->table('movimientos_stock')->where('insumo_id', $i['id'])->delete();
            $db->table('lotes')->where('insumo_id', $i['id'])->delete();
            $db->table('existencias')->where('insumo_id', $i['id'])->delete();
            $db->table('insumos')->where('id', $i['id'])->delete();
        }
    }

    private function pedir(float $cantidad = 10, float $costo = 3000): int
    {
        $this->lineas->anadir($this->ordenId, $this->insumoId, $cantidad, $costo);
        $this->ordenes->recalcular($this->ordenId);
        $this->ordenes->update($this->ordenId, ['estado' => 'enviada']);

        return (int) $this->lineas->where('orden_id', $this->ordenId)->first()['id'];
    }

    // ── Numeración y armado ─────────────────────────────────────────────

    public function testElNumeroEsCorrelativoDelAnio(): void
    {
        $this->assertMatchesRegularExpression('/^OC-\d{4}-\d{4}$/', $this->ordenes->find($this->ordenId)['numero']);
    }

    public function testElMismoInsumoDosVecesSeSumaEnVezDeDuplicarse(): void
    {
        // Duplicarlo es la forma más fácil de pedir el doble sin querer.
        $this->lineas->anadir($this->ordenId, $this->insumoId, 5, 3000);
        $this->lineas->anadir($this->ordenId, $this->insumoId, 3, 3000);

        $lineas = $this->lineas->where('orden_id', $this->ordenId)->findAll();

        $this->assertCount(1, $lineas);
        $this->assertEqualsWithDelta(8, (float) $lineas[0]['cantidad_pedida'], 0.001);
    }

    public function testElTotalSaleDeLasLineas(): void
    {
        $this->lineas->anadir($this->ordenId, $this->insumoId, 10, 3500);

        $this->assertEqualsWithDelta(35000, $this->ordenes->recalcular($this->ordenId), 0.01);
    }

    // ── Pedir no mueve stock ────────────────────────────────────────────

    public function testPedirNoMueveElAlmacen(): void
    {
        // Dar por buena la mercancía que aún está en el camión es la forma más
        // fácil de que el almacén deje de cuadrar.
        $this->pedir(10);

        $this->assertEqualsWithDelta(0, $this->almacen->saldo($this->insumoId, $this->bodegaId), 0.001);
    }

    // ── Recepción ───────────────────────────────────────────────────────

    public function testRecibirTodoCierraLaOrdenYMeteElStock(): void
    {
        $lineaId = $this->pedir(10, 3000);

        $r = $this->ordenes->recibir($this->ordenId, [$lineaId => ['cantidad' => 10]]);

        $this->assertSame('recibida', $r['estado']);
        $this->assertEqualsWithDelta(10, $this->almacen->saldo($this->insumoId, $this->bodegaId), 0.001);
    }

    public function testRecibirAMediasDejaLaOrdenParcial(): void
    {
        $lineaId = $this->pedir(10);

        $r = $this->ordenes->recibir($this->ordenId, [
            $lineaId => ['cantidad' => 6, 'motivo' => 'No tenían más'],
        ]);

        $this->assertSame('parcial', $r['estado']);
        $this->assertEqualsWithDelta(6, $this->almacen->saldo($this->insumoId, $this->bodegaId), 0.001);

        $linea = $this->lineas->find($lineaId);
        $this->assertSame('No tenían más', $linea['motivo_diferencia']);
    }

    public function testSePuedeRecibirEnVariasVeces(): void
    {
        // Es justo el caso que la recepción parcial resuelve.
        $lineaId = $this->pedir(10);

        $this->ordenes->recibir($this->ordenId, [$lineaId => ['cantidad' => 4]]);
        $r = $this->ordenes->recibir($this->ordenId, [$lineaId => ['cantidad' => 6]]);

        $this->assertSame('recibida', $r['estado']);
        $this->assertEqualsWithDelta(10, $this->almacen->saldo($this->insumoId, $this->bodegaId), 0.001);
        $this->assertEqualsWithDelta(10, (float) $this->lineas->find($lineaId)['cantidad_recibida'], 0.001);
    }

    public function testElCosteRealPuedeSerDistintoAlPedido(): void
    {
        // Pasa: se pide a un precio y llega a otro. Manda el de la factura.
        $lineaId = $this->pedir(10, 3000);

        $this->ordenes->recibir($this->ordenId, [$lineaId => ['cantidad' => 10, 'costo' => 3600]]);

        $this->assertEqualsWithDelta(3600, $this->almacen->costoActual($this->insumoId, $this->bodegaId), 0.01);
    }

    public function testElLoteLlegaConLaMercanciaNoConElPedido(): void
    {
        $lineaId = $this->pedir(10);

        $this->ordenes->recibir($this->ordenId, [
            $lineaId => ['cantidad' => 10, 'lote' => 'L-ARROZ-1', 'caduca_el' => date('Y-m-d', strtotime('+120 days'))],
        ]);

        $lotes = (new \App\Models\LoteModel())->deInsumo($this->insumoId, $this->bodegaId);

        $this->assertCount(1, $lotes);
        $this->assertSame('L-ARROZ-1', $lotes[0]['codigo']);
    }

    public function testUnBorradorNoSePuedeRecibir(): void
    {
        $this->lineas->anadir($this->ordenId, $this->insumoId, 10, 3000);
        $lineaId = (int) $this->lineas->where('orden_id', $this->ordenId)->first()['id'];

        $r = $this->ordenes->recibir($this->ordenId, [$lineaId => ['cantidad' => 10]]);

        $this->assertSame(0, $r['entradas'], 'Primero hay que enviarla al proveedor.');
        $this->assertEqualsWithDelta(0, $this->almacen->saldo($this->insumoId, $this->bodegaId), 0.001);
    }

    public function testLaEntradaQuedaLigadaALaOrden(): void
    {
        // Es lo que permite explicar de dónde salió cada kilo.
        $lineaId = $this->pedir(10);
        $this->ordenes->recibir($this->ordenId, [$lineaId => ['cantidad' => 10]]);

        $movimiento = db_connect()->table('movimientos_stock')
            ->where('referencia_tipo', 'orden_compra')
            ->where('referencia_id', $this->ordenId)
            ->get()->getRowArray();

        $this->assertNotNull($movimiento);
        $this->assertSame('entrada', $movimiento['tipo']);
    }

    // ── Diferencias con el proveedor ────────────────────────────────────

    public function testLasDiferenciasSalenEnElInforme(): void
    {
        $lineaId = $this->pedir(10);
        $this->ordenes->recibir($this->ordenId, [$lineaId => ['cantidad' => 7, 'motivo' => 'Faltó producto']]);

        // Se cierra a mano: la orden quedó parcial y ya no llegará más
        $this->ordenes->update($this->ordenId, ['estado' => 'recibida']);

        $diferencias = $this->ordenes->diferencias(date('Y-m-d'), date('Y-m-d'));
        $insumos     = array_column($diferencias, 'insumo');

        $this->assertContains('Arroz prueba compras', $insumos);
    }

    public function testUnaOrdenParcialNoCuentaComoDiferenciaTodavia(): void
    {
        // Todavía puede llegar el resto.
        $lineaId = $this->pedir(10);
        $this->ordenes->recibir($this->ordenId, [$lineaId => ['cantidad' => 7]]);

        $this->assertNotContains(
            'Arroz prueba compras',
            array_column($this->ordenes->diferencias(date('Y-m-d'), date('Y-m-d')), 'insumo')
        );
    }

    // ── Informes de coste ───────────────────────────────────────────────

    public function testElTeoricoSaleDeLasRecetasDeLoVendido(): void
    {
        $db = db_connect();

        $db->table('carta_categorias')->insert(['nombre' => 'Cat prueba compras', 'orden' => 96]);
        $catId = $db->insertID();
        $db->table('carta_productos')->insert([
            'categoria_id' => $catId, 'nombre' => 'Plato prueba compras',
            'precio' => 25000, 'disponible' => 1, 'destino' => 'cocina',
        ]);
        $productoId = (int) $db->insertID();
        $db->table('receta_lineas')->insert([
            'producto_id' => $productoId, 'insumo_id' => $this->insumoId, 'cantidad' => 0.15,
        ]);

        $comandaId = (int) (new \App\Models\ComandaModel())->insert([
            'numero' => 'CT-' . random_int(1000, 9999), 'estado' => 'abierta', 'total' => 0,
        ], true);

        $db->table('comanda_lineas')->insert([
            'comanda_id' => $comandaId, 'producto_id' => $productoId,
            'nombre_producto' => 'Plato prueba compras', 'precio_unitario' => 25000,
            'cantidad' => 4, 'enviado_cocina' => 1, 'estado_linea' => 'normal',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $teorico = (new CostosCocina())->consumoTeorico(date('Y-m-d'), date('Y-m-d'));

        // 0,15 × 4 = 0,6
        $this->assertArrayHasKey($this->insumoId, $teorico);
        $this->assertEqualsWithDelta(0.6, $teorico[$this->insumoId]['cantidad'], 0.001);

        $db->table('comanda_lineas')->where('comanda_id', $comandaId)->delete();
        $db->table('comandas')->where('id', $comandaId)->delete();
        $db->table('receta_lineas')->where('producto_id', $productoId)->delete();
        $db->table('carta_productos')->where('id', $productoId)->delete();
        $db->table('carta_categorias')->where('id', $catId)->delete();
    }

    public function testLaComparacionDevuelveResumenAunqueNoHayaDatos(): void
    {
        $r = (new CostosCocina())->comparar(date('Y-m-d'), date('Y-m-d'));

        $this->assertArrayHasKey('resumen', $r);
        $this->assertArrayHasKey('desviacion', $r['resumen']);
    }

    // ── Proveedores ─────────────────────────────────────────────────────

    public function testMigrarProveedoresAgrupaLosQueSonElMismo(): void
    {
        $db = db_connect();

        foreach (['Distribuidora del Valle', 'distribuidora del valle', 'Distribuidora  del  Valle'] as $i => $texto) {
            $db->table('insumos')->insert([
                'nombre' => 'Insumo migra prueba compras ' . $i, 'unidad' => 'kg',
                'costo_unitario' => 1000, 'activo' => 1, 'proveedor' => $texto,
            ]);
        }

        $r = (new ProveedorModel())->migrarDesdeTexto();

        $this->assertSame(1, $r['creados'], 'Los tres son el mismo proveedor escrito de tres formas.');
        $this->assertSame(3, $r['enlazados']);

        $db->table('insumos')->like('nombre', 'Insumo migra prueba compras')->delete();
        $db->table('proveedores')->where('nombre', 'Distribuidora del Valle')->delete();
    }
}
