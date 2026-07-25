<?php

namespace App\Controllers;

use App\Models\InsumoModel;
use App\Models\PreparacionModel;

/**
 * Preparaciones (subrecetas): hogao, masa de pizza, salsa de ajillo…
 * Se elaboran en tandas y se usan por cantidad en varios platos.
 */
class Preparaciones extends BaseController
{
    private InsumoModel $insumos;
    private PreparacionModel $preparaciones;

    public function __construct()
    {
        $this->insumos       = new InsumoModel();
        $this->preparaciones = new PreparacionModel();
    }

    public function index()
    {
        $lista = $this->insumos->preparaciones();

        foreach ($lista as &$p) {
            $usos       = $this->preparaciones->dondeSeUsa((int) $p['id']);
            $p['usos']  = count($usos['platos']) + count($usos['preparaciones']);
            $p['listo'] = (float) ($p['rendimiento'] ?? 0) > 0
                && $this->preparaciones->where('preparacion_id', $p['id'])->countAllResults() > 0;
        }
        unset($p);

        return view('carta/preparaciones', [
            'titulo'        => 'Preparaciones',
            'seccion'       => 'preparaciones',
            'preparaciones' => $lista,
            'unidades'      => InsumoModel::UNIDADES,
        ]);
    }

    public function crear()
    {
        $datos = [
            'nombre'         => trim((string) $this->request->getPost('nombre')),
            'unidad'         => array_key_exists($this->request->getPost('unidad'), InsumoModel::UNIDADES)
                ? $this->request->getPost('unidad') : 'g',
            'es_preparacion' => 1,
            'rendimiento'    => max(0, (float) $this->request->getPost('rendimiento')),
            'costo_unitario' => 0,
            'notas'          => trim((string) $this->request->getPost('notas')) ?: null,
            'activo'         => 1,
        ];

        if ($datos['nombre'] === '') {
            return redirect()->to('preparaciones')->with('error', 'Escribe el nombre de la preparación.');
        }
        if ($datos['rendimiento'] <= 0) {
            return redirect()->to('preparaciones')->with('error', 'Indica cuánto produce una tanda (rendimiento).');
        }

        $id = $this->insumos->insert($datos);
        if ($id === false) {
            return redirect()->to('preparaciones')->with('error', implode(' ', $this->insumos->errors()));
        }

        return redirect()->to('preparaciones/ficha/' . $id)
            ->with('ok', 'Preparación creada. Añade ahora sus ingredientes.');
    }

    public function ficha(int $id)
    {
        $preparacion = $this->insumos->find($id);
        if ($preparacion === null || (int) $preparacion['es_preparacion'] !== 1) {
            return redirect()->to('preparaciones')->with('error', 'La preparación no existe.');
        }

        $componentes = $this->preparaciones->componentesDe($id);
        $costeTanda  = array_sum(array_column($componentes, 'costo'));
        $rendimiento = (float) ($preparacion['rendimiento'] ?? 0);

        // Insumos disponibles como componente, descartando los que crearían un ciclo
        $candidatos = array_values(array_filter(
            $this->insumos->activos(),
            fn ($i) => (int) $i['id'] !== $id && ! $this->preparaciones->generariaCiclo($id, (int) $i['id'])
        ));

        return view('carta/preparacion_ficha', [
            'titulo'      => 'Preparación · ' . $preparacion['nombre'],
            'seccion'     => 'preparaciones',
            'preparacion' => $preparacion,
            'componentes' => $componentes,
            'candidatos'  => $candidatos,
            'unidades'    => InsumoModel::UNIDADES,
            'costeTanda'  => $costeTanda,
            'porUnidad'   => $rendimiento > 0 ? $costeTanda / $rendimiento : 0,
            'usos'        => $this->preparaciones->dondeSeUsa($id),
        ]);
    }

    public function actualizar(int $id)
    {
        $preparacion = $this->insumos->find($id);
        if ($preparacion === null) {
            return redirect()->to('preparaciones')->with('error', 'La preparación no existe.');
        }

        $rendimiento = max(0, (float) $this->request->getPost('rendimiento'));
        if ($rendimiento <= 0) {
            return redirect()->to('preparaciones/ficha/' . $id)->with('error', 'El rendimiento debe ser mayor que cero.');
        }

        $this->insumos->update($id, [
            'nombre'      => trim((string) $this->request->getPost('nombre')),
            'unidad'      => array_key_exists($this->request->getPost('unidad'), InsumoModel::UNIDADES)
                ? $this->request->getPost('unidad') : 'g',
            'rendimiento' => $rendimiento,
            'notas'       => trim((string) $this->request->getPost('notas')) ?: null,
            'activo'      => $this->request->getPost('activo') ? 1 : 0,
        ]);

        $this->preparaciones->recalcularCostes();

        return redirect()->to('preparaciones/ficha/' . $id)->with('ok', 'Preparación actualizada y costes recalculados.');
    }

    public function guardarComponente(int $id)
    {
        $insumoId = (int) $this->request->getPost('insumo_id');
        $cantidad = (float) $this->request->getPost('cantidad');

        if ($insumoId <= 0 || $cantidad <= 0) {
            return redirect()->to('preparaciones/ficha/' . $id)->with('error', 'Elige un ingrediente y una cantidad mayor que cero.');
        }

        if ($this->preparaciones->generariaCiclo($id, $insumoId)) {
            return redirect()->to('preparaciones/ficha/' . $id)
                ->with('error', 'No se puede: esa preparación ya usa esta, y se crearía un bucle sin fin.');
        }

        $existente = $this->preparaciones->where('preparacion_id', $id)->where('insumo_id', $insumoId)->first();
        if ($existente !== null) {
            $this->preparaciones->update($existente['id'], ['cantidad' => $cantidad]);
        } else {
            $this->preparaciones->insert(['preparacion_id' => $id, 'insumo_id' => $insumoId, 'cantidad' => $cantidad]);
        }

        $this->preparaciones->recalcularCostes();

        return redirect()->to('preparaciones/ficha/' . $id)->with('ok', 'Ingrediente añadido y coste recalculado.');
    }

    public function eliminarComponente(int $lineaId)
    {
        $linea = $this->preparaciones->find($lineaId);
        if ($linea === null) {
            return redirect()->to('preparaciones')->with('error', 'El componente no existe.');
        }

        $this->preparaciones->delete($lineaId);
        $this->preparaciones->recalcularCostes();

        return redirect()->to('preparaciones/ficha/' . $linea['preparacion_id'])->with('ok', 'Ingrediente quitado.');
    }

    public function eliminar(int $id)
    {
        $usos = $this->preparaciones->dondeSeUsa($id);
        if ($usos['platos'] !== [] || $usos['preparaciones'] !== []) {
            return redirect()->to('preparaciones')
                ->with('error', 'No se puede eliminar: se usa en ' . implode(', ', array_merge($usos['platos'], $usos['preparaciones'])) . '.');
        }

        $this->insumos->delete($id);

        return redirect()->to('preparaciones')->with('ok', 'Preparación eliminada.');
    }
}
