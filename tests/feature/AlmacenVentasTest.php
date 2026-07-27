<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Almacen;
use App\Models\BodegaModel;
use App\Models\ComandaLineaModel;
use App\Models\ComandaModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * El enganche entre las ventas y el almacén.
 *
 * Es lo que convierte el inventario en algo real en vez de una hoja aparte: al
 * enviar un plato a cocina se descuentan sus ingredientes, y al anularlo
 * vuelven.
 *
 * Lo que más se comprueba es que **no se descuente dos veces**. El comandero
 * reintenta cuando no hay cobertura, y un descuento doble vaciaría el almacén
 * sin que nadie hubiera cocinado nada.
 *
 * @internal
 */
final class AlmacenVentasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Almacen $almacen;
    private int $harina;
    private int $queso;
    private int $productoId;
    private int $cocina;
    private int $comandaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->almacen = new Almacen();
        $this->limpiar();

        $db = db_connect();

        foreach ([['Harina prueba venta', 'kg', 4000], ['Queso prueba venta', 'kg', 22000]] as [$nombre, $unidad, $costo]) {
            $db->table('insumos')->insert([
                'nombre' => $nombre, 'unidad' => $unidad,
                'costo_unitario' => $costo, 'activo' => 1,
            ]);
        }
        $this->harina = (int) $db->table('insumos')->where('nombre', 'Harina prueba venta')->get()->getRowArray()['id'];
        $this->queso  = (int) $db->table('insumos')->where('nombre', 'Queso prueba venta')->get()->getRowArray()['id'];

        $db->table('carta_categorias')->insert(['nombre' => 'Prueba venta', 'orden' => 97]);
        $categoriaId = $db->insertID();

        $db->table('carta_productos')->insert([
            'categoria_id' => $categoriaId, 'nombre' => 'Pizza prueba venta',
            'precio' => 45000, 'disponible' => 1, 'destino' => 'cocina',
        ]);
        $this->productoId = (int) $db->insertID();

        // Receta: 0,3 kg de harina y 0,2 de queso por pizza
        $db->table('receta_lineas')->insert(['producto_id' => $this->productoId, 'insumo_id' => $this->harina, 'cantidad' => 0.3]);
        $db->table('receta_lineas')->insert(['producto_id' => $this->productoId, 'insumo_id' => $this->queso, 'cantidad' => 0.2]);

        $this->cocina = (int) (new BodegaModel())->where('clave', 'cocina')->first()['id'];

        $this->almacen->entrada($this->harina, $this->cocina, 10, 4000);
        $this->almacen->entrada($this->queso, $this->cocina, 5, 22000);

        $this->comandaId = (int) (new ComandaModel())->insert([
            'numero' => 'AV-' . random_int(1000, 9999), 'estado' => 'abierta', 'total' => 0,
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

        $comandas = $db->table('comandas')->select('id')->like('numero', 'AV-')->get()->getResultArray();
        foreach ($comandas as $c) {
            $db->table('comanda_lineas')->where('comanda_id', $c['id'])->delete();
            $db->table('comandas')->where('id', $c['id'])->delete();
        }

        $productos = $db->table('carta_productos')->select('id')->like('nombre', 'prueba venta')->get()->getResultArray();
        foreach ($productos as $p) {
            $db->table('receta_lineas')->where('producto_id', $p['id'])->delete();
            $db->table('carta_productos')->where('id', $p['id'])->delete();
        }

        $insumos = $db->table('insumos')->select('id')->like('nombre', 'prueba venta')->get()->getResultArray();
        foreach ($insumos as $i) {
            $db->table('movimientos_stock')->where('insumo_id', $i['id'])->delete();
            $db->table('existencias')->where('insumo_id', $i['id'])->delete();
            $db->table('insumos')->where('id', $i['id'])->delete();
        }

        $db->table('carta_categorias')->where('nombre', 'Prueba venta')->delete();
    }

    private function anadirLinea(int $cantidad = 1, string $estado = 'normal'): int
    {
        return (int) (new ComandaLineaModel())->insert([
            'comanda_id'      => $this->comandaId,
            'producto_id'     => $this->productoId,
            'nombre_producto' => 'Pizza prueba venta',
            'precio_unitario' => 45000,
            'cantidad'        => $cantidad,
            'destino'         => 'cocina',
            'estado_linea'    => $estado,
        ], true);
    }

    // ── Descuento al enviar a cocina ────────────────────────────────────

    public function testEnviarACocinaDescuentaLosIngredientes(): void
    {
        $this->anadirLinea(2);   // 2 pizzas

        (new ComandaLineaModel())->enviarAPreparacion($this->comandaId);

        // 10 − (0,3 × 2) = 9,4 · 5 − (0,2 × 2) = 4,6
        $this->assertEqualsWithDelta(9.4, $this->almacen->saldo($this->harina, $this->cocina), 0.001);
        $this->assertEqualsWithDelta(4.6, $this->almacen->saldo($this->queso, $this->cocina), 0.001);
    }

    public function testEnviarDosVecesNoDescuentaElDoble(): void
    {
        // El comandero reintenta cuando no hay cobertura: si descontara dos
        // veces, el almacén se vaciaría sin que nadie hubiera cocinado nada.
        $lineaId = $this->anadirLinea(1);

        $this->almacen->descontarReceta($lineaId);
        $this->almacen->descontarReceta($lineaId);
        $this->almacen->descontarReceta($lineaId);

        $this->assertEqualsWithDelta(9.7, $this->almacen->saldo($this->harina, $this->cocina), 0.001);
    }

    public function testUnProductoSinRecetaNoRompeNada(): void
    {
        $db = db_connect();
        $db->table('receta_lineas')->where('producto_id', $this->productoId)->delete();

        $lineaId = $this->anadirLinea(1);

        $this->assertSame(0, $this->almacen->descontarReceta($lineaId),
            'No todos los platos tienen escandallo, y no tenerlo no puede impedir venderlos.');
        $this->assertEqualsWithDelta(10, $this->almacen->saldo($this->harina, $this->cocina), 0.001);
    }

    public function testLaCantidadDelPlatoMultiplicaLaReceta(): void
    {
        $this->almacen->descontarReceta($this->anadirLinea(5));

        // 0,3 × 5 = 1,5
        $this->assertEqualsWithDelta(8.5, $this->almacen->saldo($this->harina, $this->cocina), 0.001);
    }

    // ── Motivo según el estado de la línea ──────────────────────────────

    public function testUnaCortesiaGastaProductoIgualPeroSeApuntaComoCortesia(): void
    {
        // La cocina la hizo igual: si el movimiento no existiera, el escandallo
        // mentiría sobre lo que ha salido.
        $this->almacen->descontarReceta($this->anadirLinea(1, 'cortesia'));

        $movimiento = db_connect()->table('movimientos_stock')
            ->where('insumo_id', $this->harina)
            ->orderBy('id', 'DESC')->get(1)->getRowArray();

        $this->assertSame('cortesia', $movimiento['tipo']);
        $this->assertEqualsWithDelta(9.7, $this->almacen->saldo($this->harina, $this->cocina), 0.001);
    }

    public function testUnPlatoDevueltoSeApuntaComoMerma(): void
    {
        $this->almacen->descontarReceta($this->anadirLinea(1, 'devuelta'));

        $movimiento = db_connect()->table('movimientos_stock')
            ->where('insumo_id', $this->harina)
            ->orderBy('id', 'DESC')->get(1)->getRowArray();

        $this->assertSame('merma', $movimiento['tipo'], 'Volvió a cocina: se tira.');
    }

    // ── Devolución al anular ────────────────────────────────────────────

    public function testAnularDevuelveLosIngredientes(): void
    {
        $lineaId = $this->anadirLinea(2);
        $this->almacen->descontarReceta($lineaId);
        $this->assertEqualsWithDelta(9.4, $this->almacen->saldo($this->harina, $this->cocina), 0.001);

        $this->almacen->devolverReceta($lineaId);

        $this->assertEqualsWithDelta(10, $this->almacen->saldo($this->harina, $this->cocina), 0.001);
    }

    public function testLaDevolucionNoBorraLosMovimientosSinoQueApuntaLosContrarios(): void
    {
        // Borrarlos rompería el libro: no se podría explicar qué pasó.
        $lineaId = $this->anadirLinea(1);
        $this->almacen->descontarReceta($lineaId);
        $this->almacen->devolverReceta($lineaId);

        $movimientos = db_connect()->table('movimientos_stock')
            ->where('insumo_id', $this->harina)
            ->get()->getResultArray();

        $tipos = array_column($movimientos, 'tipo');
        $this->assertContains('entrada', $tipos);
        $this->assertContains('salida', $tipos);
        $this->assertContains('devolucion', $tipos);
    }

    public function testDespuesDeDevolverSePuedeVolverADescontar(): void
    {
        // Es lo que pasa si se reenvía una comanda: tiene que volver a gastar.
        $lineaId = $this->anadirLinea(1);

        $this->almacen->descontarReceta($lineaId);
        $this->almacen->devolverReceta($lineaId);
        $this->almacen->descontarReceta($lineaId);

        $this->assertEqualsWithDelta(9.7, $this->almacen->saldo($this->harina, $this->cocina), 0.001);
    }

    public function testDevolverDosVecesNoDuplicaElStock(): void
    {
        $lineaId = $this->anadirLinea(1);
        $this->almacen->descontarReceta($lineaId);

        $this->almacen->devolverReceta($lineaId);
        $this->almacen->devolverReceta($lineaId);

        $this->assertEqualsWithDelta(10, $this->almacen->saldo($this->harina, $this->cocina), 0.001);
    }

    // ── La bodega correcta ──────────────────────────────────────────────

    public function testLoDeBarraSaleDeLaBodegaDelBar(): void
    {
        $bar = (int) (new BodegaModel())->where('clave', 'bar')->first()['id'];

        $this->assertSame($bar, $this->almacen->bodegaDeDestino('barra'));
        $this->assertSame($this->cocina, $this->almacen->bodegaDeDestino('cocina'));
    }

    // ── El saldo sigue explicándose ─────────────────────────────────────

    public function testTrasVenderYAnularElSaldoSigueCuadrando(): void
    {
        $linea1 = $this->anadirLinea(2);
        $linea2 = $this->anadirLinea(3);

        $this->almacen->descontarReceta($linea1);
        $this->almacen->descontarReceta($linea2);
        $this->almacen->devolverReceta($linea1);

        $guardado    = $this->almacen->saldo($this->harina, $this->cocina);
        $recalculado = $this->almacen->recalcular($this->harina, $this->cocina);

        $this->assertEqualsWithDelta($guardado, $recalculado, 0.001);
        // 10 − 0,9 (las 3 que quedan vendidas) = 9,1
        $this->assertEqualsWithDelta(9.1, $recalculado, 0.001);
    }
}
