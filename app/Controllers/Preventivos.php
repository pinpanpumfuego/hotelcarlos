<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ActivoModel;
use App\Models\MantenimientoModel;
use App\Models\PlanMantenimientoModel;
use App\Models\UsuarioModel;

/**
 * El plan de mantenimiento preventivo.
 *
 * Es la parte del módulo que evita trabajo en vez de repartirlo. Cada plan dice
 * «esto hay que mirarlo cada tanto» y la orden se abre sola unos días antes.
 */
class Preventivos extends BaseController
{
    private PlanMantenimientoModel $planes;

    public function __construct()
    {
        $this->planes = new PlanMantenimientoModel();
    }

    public function index()
    {
        $planes = $this->planes->listar();
        $hoy    = date('Y-m-d');

        foreach ($planes as &$p) {
            $p['vencido'] = (int) $p['activo'] === 1 && $p['proxima_fecha'] < $hoy;
            $p['pronto']  = (int) $p['activo'] === 1 && ! $p['vencido']
                && $p['proxima_fecha'] <= date('Y-m-d', strtotime('+' . (int) $p['aviso_dias'] . ' days'));
        }

        return view('preventivos/index', [
            'titulo'     => 'Plan preventivo',
            'seccion'    => 'mantenimiento',
            'planes'     => $planes,
            'periodos'   => PlanMantenimientoModel::PERIODOS,
            'prioridades' => MantenimientoModel::PRIORIDADES,
            'categorias' => ActivoModel::CATEGORIAS,
            'generadas'  => (new MantenimientoModel())
                ->select('mantenimientos.*, activos.nombre AS activo_nombre')
                ->join('activos', 'activos.id = mantenimientos.activo_id', 'left')
                ->where('mantenimientos.tipo', 'preventiva')
                ->whereIn('mantenimientos.estado', MantenimientoModel::ABIERTAS)
                ->orderBy('mantenimientos.created_at')
                ->findAll(20),
        ]);
    }

    public function nuevo()
    {
        return $this->formulario(null);
    }

    public function editar(int $id)
    {
        $plan = $this->planes->find($id);

        if ($plan === null) {
            return redirect()->to('preventivos')->with('error', 'Ese plan no existe.');
        }

        return $this->formulario($plan);
    }

    private function formulario(?array $plan)
    {
        return view('preventivos/editar', [
            'titulo'      => $plan === null ? 'Nuevo plan' : 'Editar plan',
            'seccion'     => 'mantenimiento',
            'plan'        => $plan,
            'activos'     => (new ActivoModel())->listar(),
            'categorias'  => ActivoModel::CATEGORIAS,
            'periodos'    => PlanMantenimientoModel::PERIODOS,
            'prioridades' => MantenimientoModel::PRIORIDADES,
            'tecnicos'    => (new UsuarioModel())->conPermiso('mantenimiento.trabajar'),
        ]);
    }

    public function guardar(?int $id = null)
    {
        $activoId  = (int) $this->request->getPost('activo_id') ?: null;
        $categoria = (string) $this->request->getPost('categoria');

        $datos = [
            'nombre'    => trim((string) $this->request->getPost('nombre')),
            'activo_id' => $activoId,
            // Un plan apunta a un equipo o a una categoría, nunca a los dos:
            // si no, no se sabría cuántas órdenes tiene que generar.
            'categoria' => $activoId === null && array_key_exists($categoria, ActivoModel::CATEGORIAS)
                ? $categoria
                : null,
            'cada'          => max(1, (int) $this->request->getPost('cada')),
            'periodo'       => $this->request->getPost('periodo') === 'dias' ? 'dias' : 'meses',
            'proxima_fecha' => (string) $this->request->getPost('proxima_fecha'),
            'aviso_dias'    => max(0, min(60, (int) $this->request->getPost('aviso_dias'))),
            'prioridad'     => array_key_exists((string) $this->request->getPost('prioridad'), MantenimientoModel::PRIORIDADES)
                ? (string) $this->request->getPost('prioridad')
                : 'media',
            'asignado_a'    => (int) $this->request->getPost('asignado_a') ?: null,
            'duracion_min'  => (int) $this->request->getPost('duracion_min') ?: null,
            'instrucciones' => trim((string) $this->request->getPost('instrucciones')) ?: null,
            'obligatorio'   => $this->request->getPost('obligatorio') !== null ? 1 : 0,
            'activo'        => $this->request->getPost('activo') !== null ? 1 : 0,
        ];

        if ($id === null) {
            if (! $this->planes->insert($datos)) {
                return redirect()->back()->withInput()->with('errores', $this->planes->errors());
            }

            return redirect()->to('preventivos')->with('ok', 'Plan creado. La orden se abrirá sola cuando toque.');
        }

        if (! $this->planes->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->planes->errors());
        }

        return redirect()->to('preventivos')->with('ok', 'Plan actualizado.');
    }

    public function eliminar(int $id)
    {
        $plan = $this->planes->find($id);

        if ($plan === null) {
            return redirect()->to('preventivos')->with('error', 'Ese plan no existe.');
        }

        if ((int) $plan['obligatorio'] === 1) {
            // Los extintores y la piscina los exige la ley: apagar ese plan sin
            // querer es de las cosas que no se descubren hasta la inspección.
            return redirect()->to('preventivos')
                ->with('error', 'Ese plan está marcado como obligatorio por normativa. Quítale la marca antes de borrarlo.');
        }

        $this->planes->delete($id);

        return redirect()->to('preventivos')->with('ok', 'Plan borrado. Las órdenes que ya generó se conservan.');
    }

    /**
     * Genera ahora lo que toque, sin esperar a la tarea de madrugada.
     *
     * Sirve para dos cosas: salir del paso si la tarea programada falló, y
     * comprobar que la orden funciona en este servidor antes de programarla.
     */
    public function generarAhora()
    {
        $r = (new \App\Libraries\Preventivos())->generar();

        if ($r['abiertas'] === 0 && $r['saltadas'] === 0) {
            return redirect()->to('preventivos')->with('ok', 'No tocaba ninguna revisión todavía.');
        }

        $mensaje = $r['abiertas'] . ' orden(es) abierta(s).';

        if ($r['saltadas'] > 0) {
            $mensaje .= ' Se saltaron ' . $r['saltadas'] . ' porque ya tenían una sin cerrar.';
        }

        return redirect()->to('preventivos')->with('ok', $mensaje);
    }
}
