<?php

namespace App\Controllers;

use App\Models\CartaCategoriaModel;
use App\Models\CartaProductoModel;
use App\Models\InsumoModel;
use App\Models\ModificadorGrupoModel;
use App\Models\ModificadorModel;
use App\Models\RecetaModel;

/** Carta del restaurante: categorías, productos, ficha técnica y escandallo (solo gerencia). */
class Carta extends BaseController
{
    private CartaCategoriaModel $categorias;
    private CartaProductoModel $productos;

    public function __construct()
    {
        $this->categorias = new CartaCategoriaModel();
        $this->productos  = new CartaProductoModel();
    }

    public function index()
    {
        $categorias = $this->categorias->ordenadas();
        $productos  = $this->productos->conCategoria();
        $costes     = (new RecetaModel())->costesPorProducto();

        $conteo = [];
        foreach ($productos as &$p) {
            $conteo[$p['categoria_id']] = ($conteo[$p['categoria_id']] ?? 0) + 1;

            // Escandallo: coste, margen y food cost de cada plato
            $p['coste']     = $costes[(int) $p['id']] ?? null;
            $p['margen']    = $p['coste'] !== null ? (float) $p['precio'] - $p['coste'] : null;
            $p['foodcost']  = ($p['coste'] !== null && (float) $p['precio'] > 0)
                ? round($p['coste'] / (float) $p['precio'] * 100, 1) : null;
            $p['alergenos_lista'] = CartaProductoModel::alergenosDe($p['alergenos'] ?? null);
        }
        unset($p);

        return view('carta/index', [
            'titulo'     => 'Carta del restaurante',
            'seccion'    => 'carta',
            'categorias' => $categorias,
            'productos'  => $productos,
            'conteo'     => $conteo,
            'destinos'   => CartaProductoModel::DESTINOS,
            'dietas'     => CartaProductoModel::DIETAS,
            'alergenos'  => CartaProductoModel::ALERGENOS,
            'picante'    => CartaProductoModel::PICANTE,
        ]);
    }

    /** Ficha completa de un plato: dietas, alérgenos, modificadores y escandallo. */
    public function ficha(int $id)
    {
        $producto = $this->productos->find($id);
        if ($producto === null) {
            return redirect()->to('carta')->with('error', 'El producto no existe.');
        }

        $receta = (new RecetaModel())->deProducto($id);
        $coste  = array_sum(array_column($receta, 'costo'));
        $precio = (float) $producto['precio'];

        $gruposAsignados = array_column(
            db_connect()->table('producto_modificador_grupos')
                ->select('grupo_id')->where('producto_id', $id)->get()->getResultArray(),
            'grupo_id'
        );

        return view('carta/ficha', [
            'titulo'          => 'Ficha · ' . $producto['nombre'],
            'seccion'         => 'carta',
            'producto'        => $producto,
            'categorias'      => $this->categorias->ordenadas(),
            'destinos'        => CartaProductoModel::DESTINOS,
            'dietas'          => CartaProductoModel::DIETAS,
            'alergenos'       => CartaProductoModel::ALERGENOS,
            'picante'         => CartaProductoModel::PICANTE,
            'alergenosActivos' => CartaProductoModel::alergenosDe($producto['alergenos'] ?? null),
            'grupos'          => (new ModificadorGrupoModel())->conOpciones(),
            'gruposAsignados' => array_map('intval', $gruposAsignados),
            'insumos'         => (new InsumoModel())->activos(),
            'unidades'        => InsumoModel::UNIDADES,
            'receta'          => $receta,
            'coste'           => $coste,
            'margen'          => $precio - $coste,
            'foodcost'        => $precio > 0 ? round($coste / $precio * 100, 1) : 0,
        ]);
    }

    // ─────────────────────────── Categorías ───────────────────────────

    public function guardarCategoria()
    {
        $datos = [
            'nombre' => trim((string) $this->request->getPost('nombre')),
            'orden'  => (int) $this->request->getPost('orden'),
            'color'  => $this->colorValido((string) $this->request->getPost('color')),
        ];

        if (! $this->categorias->insert($datos)) {
            return redirect()->to('carta')->with('error', implode(' ', $this->categorias->errors()));
        }

        return redirect()->to('carta')->with('ok', 'Categoría creada.');
    }

    public function actualizarCategoria(int $id)
    {
        if ($this->categorias->find($id) === null) {
            return redirect()->to('carta')->with('error', 'La categoría no existe.');
        }

        $datos = [
            'nombre' => trim((string) $this->request->getPost('nombre')),
            'orden'  => (int) $this->request->getPost('orden'),
            'color'  => $this->colorValido((string) $this->request->getPost('color')),
        ];

        if (! $this->categorias->update($id, $datos)) {
            return redirect()->to('carta')->with('error', implode(' ', $this->categorias->errors()));
        }

        return redirect()->to('carta')->with('ok', 'Categoría actualizada.');
    }

    public function eliminarCategoria(int $id)
    {
        try {
            $this->categorias->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('carta')->with('error', 'No se puede eliminar: la categoría tiene productos.');
        }

        return redirect()->to('carta')->with('ok', 'Categoría eliminada.');
    }

    // ─────────────────────────── Productos ───────────────────────────

    public function guardarProducto()
    {
        $datos = $this->datosProducto();

        if (! $this->productos->insert($datos)) {
            return redirect()->to('carta')->with('error', implode(' ', $this->productos->errors()));
        }

        return redirect()->to('carta')->with('ok', 'Producto añadido a la carta.');
    }

    public function actualizarProducto(int $id)
    {
        if ($this->productos->find($id) === null) {
            return redirect()->to('carta')->with('error', 'El producto no existe.');
        }

        $datos = $this->datosProducto(true);

        if (! $this->productos->update($id, $datos)) {
            return redirect()->back()->with('error', implode(' ', $this->productos->errors()));
        }

        // Grupos de modificadores aplicables (solo desde la ficha completa)
        if ($this->request->getPost('desde_ficha')) {
            $this->guardarGruposDelProducto($id);

            return redirect()->to('carta/ficha/' . $id)->with('ok', 'Ficha del plato actualizada.');
        }

        return redirect()->to('carta')->with('ok', 'Producto actualizado.');
    }

    public function alternarDisponible(int $id)
    {
        $producto = $this->productos->find($id);
        if ($producto === null) {
            return redirect()->to('carta')->with('error', 'El producto no existe.');
        }

        $this->productos->update($id, ['disponible' => $producto['disponible'] ? 0 : 1]);

        return redirect()->back()->with('ok', $producto['disponible']
            ? $producto['nombre'] . ' marcado como agotado.'
            : $producto['nombre'] . ' vuelve a estar disponible.');
    }

    public function eliminarProducto(int $id)
    {
        try {
            $this->productos->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('carta')->with('error', 'No se puede eliminar: el producto está en comandas. Márcalo como agotado.');
        }

        return redirect()->to('carta')->with('ok', 'Producto eliminado.');
    }

    // ─────────────────────────── Escandallo ───────────────────────────

    public function guardarInsumo()
    {
        $insumos = new InsumoModel();
        $datos   = [
            'nombre'         => trim((string) $this->request->getPost('nombre')),
            'unidad'         => array_key_exists($this->request->getPost('unidad'), InsumoModel::UNIDADES)
                ? $this->request->getPost('unidad') : 'g',
            'costo_unitario' => (float) $this->request->getPost('costo_unitario'),
            'proveedor'      => trim((string) $this->request->getPost('proveedor')) ?: null,
            'activo'         => 1,
        ];

        if (! $insumos->insert($datos)) {
            return redirect()->back()->with('error', implode(' ', $insumos->errors()));
        }

        return redirect()->back()->with('ok', 'Insumo añadido.');
    }

    public function actualizarInsumo(int $id)
    {
        $insumos = new InsumoModel();
        if ($insumos->find($id) === null) {
            return redirect()->to('insumos')->with('error', 'El insumo no existe.');
        }

        $datos = [
            'nombre'         => trim((string) $this->request->getPost('nombre')),
            'unidad'         => array_key_exists($this->request->getPost('unidad'), InsumoModel::UNIDADES)
                ? $this->request->getPost('unidad') : 'g',
            'costo_unitario' => (float) $this->request->getPost('costo_unitario'),
            'proveedor'      => trim((string) $this->request->getPost('proveedor')) ?: null,
            'activo'         => $this->request->getPost('activo') ? 1 : 0,
        ];

        if (! $insumos->update($id, $datos)) {
            return redirect()->back()->with('error', implode(' ', $insumos->errors()));
        }

        // Si cambió el coste, las preparaciones que lo usan deben recalcularse
        (new \App\Models\PreparacionModel())->recalcularCostes();

        return redirect()->to('insumos')->with('ok', 'Insumo actualizado. Los costes de preparaciones y platos se recalculan solos.');
    }

    public function eliminarInsumo(int $id)
    {
        try {
            (new InsumoModel())->delete($id);
        } catch (\Throwable $e) {
            return redirect()->to('insumos')->with('error', 'No se puede eliminar: el insumo está en alguna receta.');
        }

        return redirect()->to('insumos')->with('ok', 'Insumo eliminado.');
    }

    /** Listado de insumos simples (las preparaciones tienen su propia pantalla). */
    public function insumos()
    {
        $insumos = (new InsumoModel())->where('es_preparacion', 0)->orderBy('nombre')->findAll();

        $usos = db_connect()->table('receta_lineas')
            ->select('insumo_id, COUNT(*) AS platos')
            ->groupBy('insumo_id')->get()->getResultArray();
        $mapaUsos = array_column($usos, 'platos', 'insumo_id');

        foreach ($insumos as &$i) {
            $i['platos'] = (int) ($mapaUsos[$i['id']] ?? 0);
        }
        unset($i);

        return view('carta/insumos', [
            'titulo'   => 'Insumos y costes',
            'seccion'  => 'insumos',
            'insumos'  => $insumos,
            'unidades' => InsumoModel::UNIDADES,
        ]);
    }

    public function guardarReceta(int $productoId)
    {
        $receta = new RecetaModel();

        $insumoId = (int) $this->request->getPost('insumo_id');
        $cantidad = (float) $this->request->getPost('cantidad');

        if ($insumoId <= 0 || $cantidad <= 0) {
            return redirect()->to('carta/ficha/' . $productoId)->with('error', 'Elige un insumo y una cantidad mayor que cero.');
        }

        $existente = $receta->where('producto_id', $productoId)->where('insumo_id', $insumoId)->first();
        if ($existente !== null) {
            $receta->update($existente['id'], ['cantidad' => $cantidad]);
        } else {
            $receta->insert(['producto_id' => $productoId, 'insumo_id' => $insumoId, 'cantidad' => $cantidad]);
        }

        return redirect()->to('carta/ficha/' . $productoId)->with('ok', 'Receta actualizada.');
    }

    public function eliminarReceta(int $lineaId)
    {
        $receta = new RecetaModel();
        $linea  = $receta->find($lineaId);
        if ($linea === null) {
            return redirect()->to('carta')->with('error', 'La línea de receta no existe.');
        }

        $receta->delete($lineaId);

        return redirect()->to('carta/ficha/' . $linea['producto_id'])->with('ok', 'Ingrediente quitado de la receta.');
    }

    // ─────────────────────────── Modificadores ───────────────────────────

    public function modificadores()
    {
        return view('carta/modificadores', [
            'titulo'  => 'Modificadores',
            'seccion' => 'modificadores',
            'grupos'  => (new ModificadorGrupoModel())->conOpciones(),
            'tipos'   => ModificadorGrupoModel::TIPOS,
        ]);
    }

    public function guardarGrupo()
    {
        $grupos = new ModificadorGrupoModel();
        $datos  = [
            'nombre'      => trim((string) $this->request->getPost('nombre')),
            'tipo'        => $this->request->getPost('tipo') === 'unico' ? 'unico' : 'multiple',
            'obligatorio' => $this->request->getPost('obligatorio') ? 1 : 0,
            'orden'       => (int) $this->request->getPost('orden'),
        ];

        if (! $grupos->insert($datos)) {
            return redirect()->to('modificadores')->with('error', implode(' ', $grupos->errors()));
        }

        return redirect()->to('modificadores')->with('ok', 'Grupo de modificadores creado.');
    }

    public function actualizarGrupo(int $id)
    {
        $grupos = new ModificadorGrupoModel();
        if ($grupos->find($id) === null) {
            return redirect()->to('modificadores')->with('error', 'El grupo no existe.');
        }

        $grupos->update($id, [
            'nombre'      => trim((string) $this->request->getPost('nombre')),
            'tipo'        => $this->request->getPost('tipo') === 'unico' ? 'unico' : 'multiple',
            'obligatorio' => $this->request->getPost('obligatorio') ? 1 : 0,
            'orden'       => (int) $this->request->getPost('orden'),
        ]);

        return redirect()->to('modificadores')->with('ok', 'Grupo actualizado.');
    }

    public function eliminarGrupo(int $id)
    {
        (new ModificadorGrupoModel())->delete($id);

        return redirect()->to('modificadores')->with('ok', 'Grupo eliminado.');
    }

    public function guardarModificador()
    {
        $modificadores = new ModificadorModel();
        $datos         = [
            'grupo_id'     => (int) $this->request->getPost('grupo_id'),
            'nombre'       => trim((string) $this->request->getPost('nombre')),
            'precio_extra' => (float) $this->request->getPost('precio_extra'),
            'orden'        => (int) $this->request->getPost('orden'),
        ];

        if (! $modificadores->insert($datos)) {
            return redirect()->to('modificadores')->with('error', implode(' ', $modificadores->errors()));
        }

        return redirect()->to('modificadores')->with('ok', 'Opción añadida.');
    }

    public function eliminarModificador(int $id)
    {
        (new ModificadorModel())->delete($id);

        return redirect()->to('modificadores')->with('ok', 'Opción eliminada.');
    }

    // ─────────────────────────── Ayudantes ───────────────────────────

    private function datosProducto(bool $conFicha = false): array
    {
        $datos = [
            'categoria_id' => (int) $this->request->getPost('categoria_id'),
            'nombre'       => trim((string) $this->request->getPost('nombre')),
            'descripcion'  => trim((string) $this->request->getPost('descripcion')) ?: null,
            'precio'       => (float) $this->request->getPost('precio'),
            'destino'      => array_key_exists($this->request->getPost('destino'), CartaProductoModel::DESTINOS)
                ? $this->request->getPost('destino') : 'cocina',
        ];

        if ($conFicha) {
            $datos['disponible'] = $this->request->getPost('disponible') ? 1 : 0;
        } else {
            $datos['disponible'] = 1;
        }

        // Ficha técnica: dietas, picante, alérgenos y divisible
        if ($this->request->getPost('desde_ficha')) {
            foreach (array_keys(CartaProductoModel::DIETAS) as $campo) {
                $datos[$campo] = $this->request->getPost($campo) ? 1 : 0;
            }
            $datos['picante']   = min(3, max(0, (int) $this->request->getPost('picante')));
            $datos['divisible'] = $this->request->getPost('divisible') ? 1 : 0;
            $datos['en_minibar'] = $this->request->getPost('en_minibar') ? 1 : 0;

            $marcados = (array) ($this->request->getPost('alergenos') ?? []);
            $validos   = array_values(array_intersect($marcados, array_keys(CartaProductoModel::ALERGENOS)));
            $datos['alergenos'] = $validos === [] ? null : implode(',', $validos);
        }

        return $datos;
    }

    private function guardarGruposDelProducto(int $productoId): void
    {
        $tabla = db_connect()->table('producto_modificador_grupos');
        $tabla->where('producto_id', $productoId)->delete();

        $grupos = array_map('intval', (array) ($this->request->getPost('grupos') ?? []));
        foreach (array_unique($grupos) as $grupoId) {
            if ($grupoId > 0) {
                $tabla->insert(['producto_id' => $productoId, 'grupo_id' => $grupoId]);
            }
        }
    }

    /** Acepta solo colores hexadecimales; si no, usa el verde de la marca. */
    private function colorValido(string $color): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#4f8a68';
    }
}
