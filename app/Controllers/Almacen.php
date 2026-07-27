<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Almacen as MotorAlmacen;
use App\Models\BodegaModel;
use App\Models\ExistenciaModel;
use App\Models\InsumoModel;
use App\Models\LoteModel;
use App\Models\MovimientoStockModel;
use App\Models\ProveedorModel;

/**
 * El almacén visto desde el panel.
 *
 * Todo lo que mueve stock pasa por `Libraries\Almacen`: aquí solo se recogen
 * los datos del formulario y se le pide al motor que haga el apunte. Si este
 * controlador escribiera saldos por su cuenta, el libro dejaría de cuadrar.
 */
class Almacen extends BaseController
{
    private MotorAlmacen $motor;
    private ExistenciaModel $existencias;

    public function __construct()
    {
        $this->motor       = new MotorAlmacen();
        $this->existencias = new ExistenciaModel();
    }

    /** Resumen: qué hay que reponer, qué caduca y qué está en negativo. */
    public function index()
    {
        return view('almacen/index', [
            'titulo'     => 'Almacén',
            'seccion'    => 'almacen',
            'bodegas'    => (new BodegaModel())->conResumen(),
            'bajoMinimo' => $this->existencias->bajoMinimo(),
            'negativos'  => $this->existencias->negativos(),
            'porCaducar' => (new LoteModel())->porCaducar(),
            'valoracion' => $this->existencias->valoracion(),
            'fugas'      => (new MovimientoStockModel())->fugas(date('Y-m-01'), date('Y-m-d')),
        ]);
    }

    /** Las existencias de una bodega. */
    public function bodega(int $id)
    {
        $bodega = (new BodegaModel())->find($id);
        if ($bodega === null) {
            return redirect()->to('almacen')->with('error', 'La bodega no existe.');
        }

        $filtros = [
            'buscar'         => trim((string) $this->request->getGet('buscar')),
            'categoria'      => $this->request->getGet('categoria'),
            'solo_bajos'     => $this->request->getGet('bajos'),
            'solo_negativos' => $this->request->getGet('negativos'),
        ];

        return view('almacen/bodega', [
            'titulo'      => 'Almacén · ' . $bodega['nombre'],
            'seccion'     => 'almacen',
            'bodega'      => $bodega,
            'existencias' => $this->existencias->deBodega($id, $filtros),
            'filtros'     => $filtros,
            'insumos'     => (new InsumoModel())->where('activo', 1)->orderBy('nombre')->findAll(),
            'bodegas'     => (new BodegaModel())->activas(),
            'proveedores' => (new ProveedorModel())->activos(),
        ]);
    }

    /** La película completa de un insumo. */
    public function insumo(int $id)
    {
        $insumo = (new InsumoModel())->find($id);
        if ($insumo === null) {
            return redirect()->to('almacen')->with('error', 'El insumo no existe.');
        }

        return view('almacen/insumo', [
            'titulo'      => 'Almacén · ' . $insumo['nombre'],
            'seccion'     => 'almacen',
            'insumo'      => $insumo,
            'existencias' => $this->existencias
                ->select('existencias.*, bodegas.nombre AS bodega')
                ->join('bodegas', 'bodegas.id = existencias.bodega_id')
                ->where('existencias.insumo_id', $id)->findAll(),
            'movimientos' => (new MovimientoStockModel())->fichaDe($id),
            'lotes'       => (new LoteModel())->deInsumo($id),
            'tipos'       => MovimientoStockModel::TIPOS,
        ]);
    }

    /** El historial, filtrable. Es la trazabilidad que pide la norma. */
    public function movimientos()
    {
        $filtros = [
            'insumo_id' => $this->request->getGet('insumo_id'),
            'bodega_id' => $this->request->getGet('bodega_id'),
            'tipo'      => $this->request->getGet('tipo'),
            'desde'     => $this->request->getGet('desde') ?: date('Y-m-d', strtotime('-30 days')),
            'hasta'     => $this->request->getGet('hasta'),
        ];

        $modelo = new MovimientoStockModel();

        return view('almacen/movimientos', [
            'titulo'      => 'Movimientos de almacén',
            'seccion'     => 'almacen',
            'movimientos' => $modelo->buscar($filtros)->paginate(60),
            'paginador'   => $modelo->pager,
            'filtros'     => $filtros,
            'bodegas'     => (new BodegaModel())->activas(),
            'tipos'       => MovimientoStockModel::TIPOS,
        ]);
    }

    // ── Acciones ────────────────────────────────────────────────────────

    public function entrada()
    {
        $insumoId = (int) $this->request->getPost('insumo_id');
        $bodegaId = (int) $this->request->getPost('bodega_id');
        $cantidad = (float) $this->request->getPost('cantidad');
        $costo    = (float) $this->request->getPost('costo_unitario');

        try {
            $this->motor->entrada($insumoId, $bodegaId, $cantidad, $costo,
                trim((string) $this->request->getPost('motivo')) ?: 'Entrada manual',
                [
                    'lote'         => trim((string) $this->request->getPost('lote')),
                    'caduca_el'    => $this->request->getPost('caduca_el') ?: null,
                    'proveedor_id' => (int) $this->request->getPost('proveedor_id') ?: null,
                ]
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('almacen/bodega/' . $bodegaId)->with('ok', 'Entrada registrada.');
    }

    /** Salidas que no vienen de una venta: merma, consumo interno, cortesía. */
    public function salida()
    {
        $bodegaId = (int) $this->request->getPost('bodega_id');
        $tipo     = (string) $this->request->getPost('tipo');

        if (! in_array($tipo, ['merma', 'consumo_interno', 'cortesia', 'salida'], true)) {
            return redirect()->back()->with('error', 'Tipo de salida no válido.');
        }

        $motivo = trim((string) $this->request->getPost('motivo'));
        if ($motivo === '') {
            return redirect()->back()->with('error', 'Di por qué sale: dentro de un mes nadie sabrá qué pasó.');
        }

        try {
            $this->motor->salida(
                (int) $this->request->getPost('insumo_id'),
                $bodegaId,
                (float) $this->request->getPost('cantidad'),
                $tipo,
                $motivo
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('almacen/bodega/' . $bodegaId)->with('ok', 'Salida registrada.');
    }

    public function traslado()
    {
        $origen = (int) $this->request->getPost('bodega_id');

        try {
            $this->motor->traslado(
                (int) $this->request->getPost('insumo_id'),
                $origen,
                (int) $this->request->getPost('destino_id'),
                (float) $this->request->getPost('cantidad'),
                trim((string) $this->request->getPost('motivo')) ?: null
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('almacen/bodega/' . $origen)->with('ok', 'Traslado registrado.');
    }

    /**
     * Conteo físico: se apuntan las cantidades contadas y se ajusta.
     *
     * Se procesan todas juntas para que el conteo sea uno y no veinte apuntes
     * sueltos: así se puede mirar después qué pasó ese día.
     */
    public function conteo()
    {
        $bodegaId = (int) $this->request->getPost('bodega_id');
        $contados = (array) $this->request->getPost('contado');
        $motivo   = trim((string) $this->request->getPost('motivo')) ?: 'Conteo físico del ' . date('d/m/Y');

        $ajustados = 0;
        $sobra     = 0.0;
        $falta     = 0.0;

        foreach ($contados as $insumoId => $valor) {
            // Vacío significa «no lo he contado», que no es lo mismo que cero.
            if ($valor === '' || $valor === null) {
                continue;
            }

            $antes = $this->motor->saldo((int) $insumoId, $bodegaId);
            $id    = $this->motor->ajustar((int) $insumoId, $bodegaId, (float) $valor, $motivo);

            if ($id !== null) {
                $ajustados++;
                $diferencia = (float) $valor - $antes;
                $diferencia > 0 ? $sobra += $diferencia : $falta += abs($diferencia);
            }
        }

        return redirect()->to('almacen/bodega/' . $bodegaId)->with(
            'ok',
            $ajustados === 0
                ? 'Todo cuadraba: no hubo nada que ajustar.'
                : sprintf('%d ajuste(s). Sobraba %s y faltaba %s.', $ajustados, $sobra, $falta)
        );
    }

    /** Recalcula un saldo desde los movimientos, si algo no cuadra. */
    public function recalcular(int $insumoId, int $bodegaId)
    {
        $saldo = $this->motor->recalcular($insumoId, $bodegaId);

        return redirect()->back()->with('ok', 'Saldo recalculado desde los movimientos: ' . $saldo);
    }
}
