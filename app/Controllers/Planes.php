<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Planes as LibreriaPlanes;
use App\Models\CartaCategoriaModel;
use App\Models\CartaProductoModel;
use App\Models\PlanConsumoModel;
use App\Models\PlanLineaModel;
use App\Models\PlanModel;

/**
 * Planes de la tarifa: qué lleva incluido el precio de la noche.
 *
 * Es lo que evita el error más caro del día a día: cobrar un desayuno que ya
 * estaba pagado, o regalarlo cuando no lo estaba.
 */
class Planes extends BaseController
{
    private PlanModel $planes;
    private PlanLineaModel $lineas;

    public function __construct()
    {
        $this->planes = new PlanModel();
        $this->lineas = new PlanLineaModel();
    }

    public function index()
    {
        return view('planes/index', [
            'titulo'  => 'Planes incluidos',
            'seccion' => 'planes',
            'planes'  => $this->planes->listado(),
        ]);
    }

    public function ver(int $id)
    {
        $plan = $this->planes->find($id);
        if ($plan === null) {
            return redirect()->to('planes')->with('error', 'El plan no existe.');
        }

        return view('planes/ver', [
            'titulo'      => 'Plan ' . $plan['nombre'],
            'seccion'     => 'planes',
            'plan'        => $plan,
            'derechos'    => $this->lineas->delPlanConNombres($id),
            'categorias'  => (new CartaCategoriaModel())->orderBy('orden')->findAll(),
            'productos'   => (new CartaProductoModel())->orderBy('nombre')->findAll(),
            'formas'      => PlanLineaModel::FORMAS,
            'franjas'     => LibreriaPlanes::FRANJAS,
            'consumoMes'  => (new PlanConsumoModel())->valorPeriodo(date('Y-m-01'), date('Y-m-t')),
        ]);
    }

    public function guardar()
    {
        $nombre = trim((string) $this->request->getPost('nombre'));
        if ($nombre === '') {
            return redirect()->to('planes')->with('error', 'El plan necesita un nombre.');
        }

        $id = (int) $this->planes->insert([
            'nombre'      => $nombre,
            'descripcion' => trim((string) $this->request->getPost('descripcion')) ?: null,
            'activo'      => 1,
        ], true);

        return redirect()->to('planes/ver/' . $id)->with('ok', 'Plan creado. Añade lo que incluye.');
    }

    public function actualizar(int $id)
    {
        if ($this->planes->find($id) === null) {
            return redirect()->to('planes')->with('error', 'El plan no existe.');
        }

        $this->planes->update($id, [
            'nombre'      => trim((string) $this->request->getPost('nombre')),
            'descripcion' => trim((string) $this->request->getPost('descripcion')) ?: null,
            'activo'      => $this->request->getPost('activo') ? 1 : 0,
        ]);

        return redirect()->to('planes/ver/' . $id)->with('ok', 'Plan actualizado.');
    }

    /** Añade un derecho: «un desayuno por persona y día». */
    public function anadirDerecho(int $id)
    {
        if ($this->planes->find($id) === null) {
            return redirect()->to('planes')->with('error', 'El plan no existe.');
        }

        $ok = $this->lineas->guardarDerecho([
            'plan_id'      => $id,
            'categoria_id' => $this->request->getPost('categoria_id'),
            'producto_id'  => $this->request->getPost('producto_id'),
            'cantidad'     => $this->request->getPost('cantidad'),
            'por'          => $this->request->getPost('por'),
            'franja'       => $this->request->getPost('franja'),
            'tope'         => $this->request->getPost('tope'),
        ]);

        return redirect()->to('planes/ver/' . $id)->with(
            $ok ? 'ok' : 'error',
            $ok
                ? 'Añadido a lo que incluye el plan.'
                : 'Elige **o** una categoría **o** un producto concreto, pero no las dos cosas ni ninguna.'
        );
    }

    public function quitarDerecho(int $id, int $lineaId)
    {
        $this->lineas->where('plan_id', $id)->delete($lineaId);

        return redirect()->to('planes/ver/' . $id)->with('ok', 'Quitado del plan.');
    }

    public function eliminar(int $id)
    {
        if (! $this->planes->sePuedeEliminar($id)) {
            return redirect()->to('planes')->with(
                'error',
                'No se puede eliminar: hay estancias o tipos de alojamiento usándolo. '
                . 'Quitarles el plan a mitad de estancia les cobraría el desayuno que ya tenían pagado.'
            );
        }

        $this->planes->delete($id);

        return redirect()->to('planes')->with('ok', 'Plan eliminado.');
    }

    /** Guarda las horas de cada franja de la carta. */
    public function guardarFranjas()
    {
        $pares = [];
        foreach (array_keys(LibreriaPlanes::FRANJAS) as $franja) {
            $desde = trim((string) $this->request->getPost('desde_' . $franja));
            $hasta = trim((string) $this->request->getPost('hasta_' . $franja));

            // Se guarda vacío si falta una de las dos: media franja no sirve
            $pares['carta_franja_' . $franja] = ($desde !== '' && $hasta !== '')
                ? $desde . '-' . $hasta
                : '';
        }

        (new \App\Models\ConfiguracionModel())->guardarPares($pares);

        return redirect()->to('planes')->with('ok', 'Horarios de la carta guardados.');
    }
}
