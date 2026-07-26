<?php

namespace App\Controllers;

use App\Models\InventarioItemModel;
use App\Models\ServicioModel;

/** Catálogos que alimentan las fichas de las cabañas: servicios y enseres. */
class Catalogos extends BaseController
{
    // ─────────────────────────────────────────────────────────────
    //  Servicios que se anuncian
    // ─────────────────────────────────────────────────────────────

    public function servicios()
    {
        $servicios = new ServicioModel();

        return view('catalogos/servicios', [
            'titulo'    => 'Catálogo de servicios',
            'seccion'   => 'servicios',
            'catalogo'  => $servicios->porGrupo(false),
            'enUso'     => $this->serviciosEnUso(),
        ]);
    }

    public function guardarServicio()
    {
        $servicios = new ServicioModel();
        $datos     = $this->datosServicio();

        if (! $servicios->insert($datos)) {
            return redirect()->to('servicios')->withInput()->with('errores', $servicios->errors());
        }

        return redirect()->to('servicios')->with('ok', 'Servicio «' . $datos['nombre'] . '» añadido al catálogo.');
    }

    public function actualizarServicio(int $id)
    {
        $servicios = new ServicioModel();
        if ($servicios->find($id) === null) {
            return redirect()->to('servicios')->with('error', 'Ese servicio no existe.');
        }

        if (! $servicios->update($id, $this->datosServicio())) {
            return redirect()->to('servicios')->withInput()->with('errores', $servicios->errors());
        }

        return redirect()->to('servicios')->with('ok', 'Servicio actualizado.');
    }

    public function eliminarServicio(int $id)
    {
        $servicios = new ServicioModel();
        $servicio  = $servicios->find($id);
        if ($servicio === null) {
            return redirect()->to('servicios')->with('error', 'Ese servicio no existe.');
        }

        // Si está en uso se desactiva: borrarlo lo quitaría de las fichas sin avisar
        $enUso = $this->serviciosEnUso()[$id] ?? 0;
        if ($enUso > 0) {
            $servicios->update($id, ['activo' => 0]);

            return redirect()->to('servicios')->with(
                'ok',
                '«' . $servicio['nombre'] . '» está marcado en ' . $enUso . ' alojamiento(s), '
                    . 'así que se desactivó en vez de borrarlo. Deja de verse en la web.'
            );
        }

        $servicios->delete($id);

        return redirect()->to('servicios')->with('ok', 'Servicio eliminado del catálogo.');
    }

    private function datosServicio(): array
    {
        return [
            'nombre' => trim((string) $this->request->getPost('nombre')),
            'grupo'  => trim((string) $this->request->getPost('grupo')) ?: 'Comodidades',
            'icono'  => trim((string) $this->request->getPost('icono')) ?: 'bi-check-circle',
            'orden'  => (int) $this->request->getPost('orden'),
            'activo' => $this->request->getPost('activo') !== null ? 1 : 0,
        ];
    }

    /** Cuántos tipos usan cada servicio. */
    private function serviciosEnUso(): array
    {
        $filas = db_connect()->table('tipo_servicios')
            ->select('servicio_id, COUNT(*) AS total')
            ->groupBy('servicio_id')
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($filas, 'total', 'servicio_id'));
    }

    // ─────────────────────────────────────────────────────────────
    //  Enseres de las cabañas
    // ─────────────────────────────────────────────────────────────

    public function inventario()
    {
        $items = new InventarioItemModel();

        return view('catalogos/inventario', [
            'titulo'   => 'Enseres de las cabañas',
            'seccion'  => 'inventario-cabanas',
            'catalogo' => $items->porGrupo(),
            'unidades' => (new \App\Models\UnidadModel())->countAllResults(),
        ]);
    }

    public function guardarItem()
    {
        $items = new InventarioItemModel();
        $datos = $this->datosItem();

        if (! $items->insert($datos)) {
            return redirect()->to('inventario-cabanas')->withInput()->with('errores', $items->errors());
        }

        return redirect()->to('inventario-cabanas')
            ->with('ok', '«' . $datos['nombre'] . '» añadido. Asígnalo a cada cabaña desde su ficha.');
    }

    public function actualizarItem(int $id)
    {
        $items = new InventarioItemModel();
        if ($items->find($id) === null) {
            return redirect()->to('inventario-cabanas')->with('error', 'Ese artículo no existe.');
        }

        if (! $items->update($id, $this->datosItem())) {
            return redirect()->to('inventario-cabanas')->withInput()->with('errores', $items->errors());
        }

        return redirect()->to('inventario-cabanas')->with('ok', 'Artículo actualizado.');
    }

    public function eliminarItem(int $id)
    {
        $items = new InventarioItemModel();
        $item  = $items->find($id);
        if ($item === null) {
            return redirect()->to('inventario-cabanas')->with('error', 'Ese artículo no existe.');
        }

        // Si ya se revisó alguna vez, se desactiva para no perder el historial
        $revisado = db_connect()->table('inventario_revision_lineas')->where('item_id', $id)->countAllResults();
        if ($revisado > 0) {
            $items->update($id, ['activo' => 0]);

            return redirect()->to('inventario-cabanas')
                ->with('ok', '«' . $item['nombre'] . '» ya aparece en revisiones anteriores, así que se desactivó en vez de borrarlo.');
        }

        $items->delete($id);

        return redirect()->to('inventario-cabanas')->with('ok', 'Artículo eliminado.');
    }

    private function datosItem(): array
    {
        return [
            'nombre'            => trim((string) $this->request->getPost('nombre')),
            'grupo'             => trim((string) $this->request->getPost('grupo')) ?: 'Dormitorio',
            'valor_reposicion'  => (float) $this->request->getPost('valor_reposicion'),
            'cantidad_estandar' => max(1, (int) $this->request->getPost('cantidad_estandar')),
            'orden'             => (int) $this->request->getPost('orden'),
            'activo'            => $this->request->getPost('activo') !== null ? 1 : 0,
        ];
    }
}
