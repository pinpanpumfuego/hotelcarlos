<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\CostosCocina;
use App\Models\BodegaModel;
use App\Models\InsumoModel;
use App\Models\OrdenCompraLineaModel;
use App\Models\OrdenCompraModel;
use App\Models\ProveedorModel;

/**
 * Compras: proveedores, órdenes y los informes de costes.
 *
 * La recepción es lo único que mueve stock. Pedir no mueve nada: entre pedir y
 * recibir puede pasar cualquier cosa, y dar por buena la mercancía que aún está
 * en el camión es la forma más fácil de que el almacén deje de cuadrar.
 */
class Compras extends BaseController
{
    private OrdenCompraModel $ordenes;
    private OrdenCompraLineaModel $lineas;

    public function __construct()
    {
        $this->ordenes = new OrdenCompraModel();
        $this->lineas  = new OrdenCompraLineaModel();
    }

    public function index()
    {
        $filtros = [
            'estado'       => $this->request->getGet('estado'),
            'proveedor_id' => $this->request->getGet('proveedor_id'),
            'desde'        => $this->request->getGet('desde'),
            'hasta'        => $this->request->getGet('hasta'),
        ];

        return view('compras/index', [
            'titulo'      => 'Compras',
            'seccion'     => 'compras',
            'ordenes'     => $this->ordenes->listado($filtros),
            'filtros'     => $filtros,
            'estados'     => OrdenCompraModel::ESTADOS,
            'proveedores' => (new ProveedorModel())->conResumen(),
            'bodegas'     => (new BodegaModel())->activas(),
            'reponer'     => (new \App\Models\ExistenciaModel())->bajoMinimo(),
        ]);
    }

    public function ver(int $id)
    {
        $orden = $this->ordenes
            ->select('ordenes_compra.*, proveedores.nombre AS proveedor, bodegas.nombre AS bodega')
            ->join('proveedores', 'proveedores.id = ordenes_compra.proveedor_id')
            ->join('bodegas', 'bodegas.id = ordenes_compra.bodega_id')
            ->where('ordenes_compra.id', $id)
            ->first();

        if ($orden === null) {
            return redirect()->to('compras')->with('error', 'La orden no existe.');
        }

        return view('compras/ver', [
            'titulo'   => 'Orden ' . $orden['numero'],
            'seccion'  => 'compras',
            'orden'    => $orden,
            'lineas'   => $this->lineas->deOrden($id),
            'insumos'  => (new InsumoModel())->where('activo', 1)->orderBy('nombre')->findAll(),
            'editable' => $this->ordenes->sePuedeEditar($orden),
            'estados'  => OrdenCompraModel::ESTADOS,
        ]);
    }

    public function crear()
    {
        $proveedorId = (int) $this->request->getPost('proveedor_id');
        $bodegaId    = (int) $this->request->getPost('bodega_id');

        if ($proveedorId <= 0 || $bodegaId <= 0) {
            return redirect()->to('compras')->with('error', 'Elige proveedor y bodega.');
        }

        $id = (int) $this->ordenes->insert([
            'numero'         => $this->ordenes->generarNumero(),
            'proveedor_id'   => $proveedorId,
            'bodega_id'      => $bodegaId,
            'estado'         => 'borrador',
            'fecha'          => date('Y-m-d'),
            'fecha_esperada' => $this->request->getPost('fecha_esperada') ?: null,
            'usuario_id'     => session()->get('usuario_id'),
        ], true);

        return redirect()->to('compras/ver/' . $id)->with('ok', 'Orden creada. Añade lo que hay que pedir.');
    }

    public function anadirLinea(int $id)
    {
        $orden = $this->ordenes->find($id);
        if ($orden === null || ! $this->ordenes->sePuedeEditar($orden)) {
            return redirect()->to('compras/ver/' . $id)->with('error', 'Esta orden ya no se puede cambiar.');
        }

        $ok = $this->lineas->anadir(
            $id,
            (int) $this->request->getPost('insumo_id'),
            (float) $this->request->getPost('cantidad'),
            (float) $this->request->getPost('costo_unitario')
        );

        $this->ordenes->recalcular($id);

        return redirect()->to('compras/ver/' . $id)->with(
            $ok ? 'ok' : 'error',
            $ok ? 'Añadido a la orden.' : 'La cantidad tiene que ser mayor que cero.'
        );
    }

    public function quitarLinea(int $id, int $lineaId)
    {
        $orden = $this->ordenes->find($id);
        if ($orden === null || ! $this->ordenes->sePuedeEditar($orden)) {
            return redirect()->to('compras/ver/' . $id)->with('error', 'Esta orden ya no se puede cambiar.');
        }

        $this->lineas->where('orden_id', $id)->delete($lineaId);
        $this->ordenes->recalcular($id);

        return redirect()->to('compras/ver/' . $id)->with('ok', 'Quitado de la orden.');
    }

    /**
     * Cierra el pedido y lo da por enviado.
     *
     * A partir de aquí no se puede editar: si se pudiera cambiar lo pedido
     * después de pedirlo, comparar lo pedido con lo recibido dejaría de
     * significar nada.
     */
    public function enviar(int $id)
    {
        $orden = $this->ordenes->find($id);
        if ($orden === null || $orden['estado'] !== 'borrador') {
            return redirect()->to('compras/ver/' . $id)->with('error', 'Solo se puede enviar un borrador.');
        }

        if ($this->lineas->where('orden_id', $id)->countAllResults() === 0) {
            return redirect()->to('compras/ver/' . $id)->with('error', 'La orden está vacía.');
        }

        $this->ordenes->update($id, ['estado' => 'enviada', 'enviada_en' => date('Y-m-d H:i:s')]);

        return redirect()->to('compras/ver/' . $id)->with('ok', 'Orden enviada. Ya solo queda recibirla.');
    }

    /** Registra lo que ha llegado. Puede llegar en varias veces. */
    public function recibir(int $id)
    {
        $recibido = [];

        foreach ((array) $this->request->getPost('cantidad') as $lineaId => $cantidad) {
            if ((float) $cantidad <= 0) {
                continue;
            }

            $recibido[(int) $lineaId] = [
                'cantidad'  => (float) $cantidad,
                'costo'     => (float) (($this->request->getPost('costo')[$lineaId] ?? 0)),
                'lote'      => (string) (($this->request->getPost('lote')[$lineaId] ?? '')),
                'caduca_el' => ($this->request->getPost('caduca')[$lineaId] ?? null) ?: null,
                'motivo'    => (string) (($this->request->getPost('motivo')[$lineaId] ?? '')),
            ];
        }

        if ($recibido === []) {
            return redirect()->to('compras/ver/' . $id)->with('error', 'No has puesto ninguna cantidad recibida.');
        }

        $documento = trim((string) $this->request->getPost('documento'));
        if ($documento !== '') {
            $this->ordenes->update($id, ['documento' => mb_substr($documento, 0, 60)]);
        }

        $r = $this->ordenes->recibir($id, $recibido);

        return redirect()->to('compras/ver/' . $id)->with(
            'ok',
            sprintf(
                '%d línea(s) recibidas y metidas en el almacén. La orden queda como «%s».',
                $r['entradas'],
                OrdenCompraModel::ESTADOS[$r['estado']] ?? $r['estado']
            )
        );
    }

    public function anular(int $id)
    {
        $orden = $this->ordenes->find($id);
        if ($orden === null || $orden['estado'] === 'recibida') {
            return redirect()->to('compras')->with('error', 'Una orden ya recibida no se anula: la mercancía está dentro.');
        }

        $this->ordenes->update($id, ['estado' => 'anulada']);

        return redirect()->to('compras')->with('ok', 'Orden anulada.');
    }

    // ── Proveedores ─────────────────────────────────────────────────────

    public function guardarProveedor()
    {
        $proveedores = new ProveedorModel();

        if (! $proveedores->insert([
            'nombre'       => trim((string) $this->request->getPost('nombre')),
            'nit'          => trim((string) $this->request->getPost('nit')) ?: null,
            'contacto'     => trim((string) $this->request->getPost('contacto')) ?: null,
            'telefono'     => trim((string) $this->request->getPost('telefono')) ?: null,
            'email'        => trim((string) $this->request->getPost('email')) ?: null,
            'dias_entrega' => max(0, (int) $this->request->getPost('dias_entrega')),
            'activo'       => 1,
        ])) {
            return redirect()->to('compras')->with('errores', $proveedores->errors());
        }

        return redirect()->to('compras')->with('ok', 'Proveedor añadido.');
    }

    /** Pasa a fichas los proveedores que estaban escritos a mano. */
    public function migrarProveedores()
    {
        $r = (new ProveedorModel())->migrarDesdeTexto();

        return redirect()->to('compras')->with(
            'ok',
            sprintf('%d proveedor(es) creados y %d insumo(s) enlazados.', $r['creados'], $r['enlazados'])
        );
    }

    // ── Informes ────────────────────────────────────────────────────────

    /** Teórico contra real, y qué se vende y qué deja. */
    public function costes()
    {
        $desde = $this->request->getGet('desde') ?: date('Y-m-01');
        $hasta = $this->request->getGet('hasta') ?: date('Y-m-d');

        $costos = new CostosCocina();

        return view('compras/costes', [
            'titulo'      => 'Costes de cocina',
            'seccion'     => 'compras',
            'desde'       => $desde,
            'hasta'       => $hasta,
            'comparacion' => $costos->comparar($desde, $hasta),
            'popularidad' => $costos->popularidad($desde, $hasta),
            'diferencias' => $this->ordenes->diferencias($desde, $hasta),
        ]);
    }
}
