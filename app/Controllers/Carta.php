<?php

namespace App\Controllers;

use App\Models\CartaCategoriaModel;
use App\Models\CartaProductoModel;

/** Carta del restaurante: categorías y productos (solo gerencia). */
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
        return view('carta/index', [
            'titulo'     => 'Carta del restaurante',
            'seccion'    => 'carta',
            'categorias' => $this->categorias->ordenadas(),
            'productos'  => $this->productos->conCategoria(),
        ]);
    }

    public function guardarCategoria()
    {
        $datos = [
            'nombre' => trim((string) $this->request->getPost('nombre')),
            'orden'  => (int) $this->request->getPost('orden'),
        ];

        if (! $this->categorias->insert($datos)) {
            return redirect()->to('carta')->with('error', implode(' ', $this->categorias->errors()));
        }

        return redirect()->to('carta')->with('ok', 'Categoría creada.');
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

    public function guardarProducto()
    {
        $datos = [
            'categoria_id' => (int) $this->request->getPost('categoria_id'),
            'nombre'       => trim((string) $this->request->getPost('nombre')),
            'descripcion'  => trim((string) $this->request->getPost('descripcion')) ?: null,
            'precio'       => (float) $this->request->getPost('precio'),
            'disponible'   => 1,
        ];

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

        $datos = [
            'categoria_id' => (int) $this->request->getPost('categoria_id'),
            'nombre'       => trim((string) $this->request->getPost('nombre')),
            'descripcion'  => trim((string) $this->request->getPost('descripcion')) ?: null,
            'precio'       => (float) $this->request->getPost('precio'),
            'disponible'   => $this->request->getPost('disponible') ? 1 : 0,
        ];

        if (! $this->productos->update($id, $datos)) {
            return redirect()->to('carta')->with('error', implode(' ', $this->productos->errors()));
        }

        return redirect()->to('carta')->with('ok', 'Producto actualizado.');
    }

    /** Activa o desactiva la disponibilidad sin salir de la lista. */
    public function alternarDisponible(int $id)
    {
        $producto = $this->productos->find($id);
        if ($producto === null) {
            return redirect()->to('carta')->with('error', 'El producto no existe.');
        }

        $this->productos->update($id, ['disponible' => $producto['disponible'] ? 0 : 1]);

        return redirect()->to('carta')->with('ok', $producto['disponible']
            ? esc($producto['nombre']) . ' marcado como agotado.'
            : esc($producto['nombre']) . ' vuelve a estar disponible.');
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
}
