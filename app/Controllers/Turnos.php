<?php

namespace App\Controllers;

use App\Models\AusenciaModel;
use App\Models\EmpleadoModel;
use App\Models\TurnoModel;

/** Planificación semanal de turnos y gestión de ausencias. */
class Turnos extends BaseController
{
    private TurnoModel $turnos;
    private EmpleadoModel $empleados;
    private AusenciaModel $ausencias;

    /** Turnos habituales, para asignar de un clic. */
    public const PLANTILLAS = [
        'manana' => ['etiqueta' => 'Mañana', 'inicio' => '06:00', 'fin' => '14:00'],
        'tarde'  => ['etiqueta' => 'Tarde', 'inicio' => '14:00', 'fin' => '22:00'],
        'noche'  => ['etiqueta' => 'Noche', 'inicio' => '22:00', 'fin' => '06:00'],
        'partido' => ['etiqueta' => 'Partido', 'inicio' => '08:00', 'fin' => '17:00'],
    ];

    public function __construct()
    {
        $this->turnos    = new TurnoModel();
        $this->empleados = new EmpleadoModel();
        $this->ausencias = new AusenciaModel();
    }

    /** Cuadrante de la semana. */
    public function index()
    {
        $lunes = $this->lunesDe((string) $this->request->getGet('semana'));
        $dias  = [];
        for ($i = 0; $i < 7; $i++) {
            $dias[] = (clone $lunes)->modify('+' . $i . ' days');
        }
        $desde = $lunes->format('Y-m-d');
        $hasta = end($dias)->format('Y-m-d');

        // Turnos organizados por empleado y día, para pintar el cuadrante
        $porEmpleado = [];
        foreach ($this->turnos->entreFechas($desde, $hasta) as $t) {
            $porEmpleado[(int) $t['empleado_id']][$t['fecha']][] = $t;
        }

        return view('turnos/index', [
            'titulo'      => 'Turnos',
            'seccion'     => 'turnos',
            'dias'        => $dias,
            'desde'       => $desde,
            'hasta'       => $hasta,
            'empleados'   => $this->empleados->activos(),
            'porEmpleado' => $porEmpleado,
            'ausencias'   => $this->ausencias->enRango($desde, $hasta),
            'horas'       => $this->turnos->horasPorEmpleado($desde, $hasta),
            'plantillas'  => self::PLANTILLAS,
            'tiposAusencia' => AusenciaModel::TIPOS,
            'coloresAusencia' => AusenciaModel::COLORES,
            'anterior'    => (clone $lunes)->modify('-7 days')->format('Y-m-d'),
            'siguiente'   => (clone $lunes)->modify('+7 days')->format('Y-m-d'),
        ]);
    }

    public function guardar()
    {
        $empleadoId = (int) $this->request->getPost('empleado_id');
        $fecha      = (string) $this->request->getPost('fecha');
        $volver     = (string) $this->request->getPost('semana');

        $plantilla = (string) $this->request->getPost('plantilla');
        if (array_key_exists($plantilla, self::PLANTILLAS)) {
            $inicio = self::PLANTILLAS[$plantilla]['inicio'];
            $fin    = self::PLANTILLAS[$plantilla]['fin'];
        } else {
            $inicio = (string) $this->request->getPost('hora_inicio');
            $fin    = (string) $this->request->getPost('hora_fin');
        }

        if ($this->empleados->find($empleadoId) === null || ! $this->fechaValida($fecha)) {
            return redirect()->to('turnos?semana=' . $volver)->with('error', 'Datos del turno no válidos.');
        }
        if ($inicio === '' || $fin === '' || $inicio === $fin) {
            return redirect()->to('turnos?semana=' . $volver)->with('error', 'Indica la hora de inicio y de fin.');
        }

        // Un turno de noche cruza la medianoche: se guarda igual pero no se compara solape
        $cruzaMedianoche = $fin < $inicio;
        if (! $cruzaMedianoche && $this->turnos->haySolape($empleadoId, $fecha, $inicio, $fin)) {
            return redirect()->to('turnos?semana=' . $volver)
                ->with('error', 'Esa persona ya tiene un turno que se solapa ese día.');
        }

        $this->turnos->insert([
            'empleado_id' => $empleadoId,
            'fecha'       => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin'    => $fin,
            'puesto'      => trim((string) $this->request->getPost('puesto')) ?: null,
            'notas'       => trim((string) $this->request->getPost('notas')) ?: null,
        ]);

        return redirect()->to('turnos?semana=' . $volver)->with('ok', 'Turno asignado.');
    }

    public function eliminar(int $id)
    {
        $turno = $this->turnos->find($id);
        if ($turno === null) {
            return redirect()->to('turnos');
        }

        $this->turnos->delete($id);
        $semana = $this->lunesDe($turno['fecha'])->format('Y-m-d');

        return redirect()->to('turnos?semana=' . $semana)->with('ok', 'Turno quitado.');
    }

    /** Copia la planificación de una semana a la siguiente. */
    public function copiarSemana()
    {
        $desde = $this->lunesDe((string) $this->request->getPost('semana'));
        $hasta = (clone $desde)->modify('+6 days');
        $turnos = $this->turnos->entreFechas($desde->format('Y-m-d'), $hasta->format('Y-m-d'));

        if ($turnos === []) {
            return redirect()->to('turnos?semana=' . $desde->format('Y-m-d'))
                ->with('error', 'Esta semana no tiene turnos que copiar.');
        }

        $copiados = 0;
        foreach ($turnos as $t) {
            $nuevaFecha = (new \DateTime($t['fecha']))->modify('+7 days')->format('Y-m-d');

            if ($this->turnos->haySolape((int) $t['empleado_id'], $nuevaFecha, $t['hora_inicio'], $t['hora_fin'])) {
                continue;   // ya había algo: no se pisa
            }

            $this->turnos->insert([
                'empleado_id' => $t['empleado_id'],
                'fecha'       => $nuevaFecha,
                'hora_inicio' => $t['hora_inicio'],
                'hora_fin'    => $t['hora_fin'],
                'puesto'      => $t['puesto'],
                'notas'       => $t['notas'],
            ]);
            $copiados++;
        }

        $siguiente = (clone $desde)->modify('+7 days')->format('Y-m-d');

        $mensaje = $copiados === 1
            ? '1 turno copiado a esta semana.'
            : $copiados . ' turnos copiados a esta semana.';

        if ($copiados === 0) {
            $mensaje = 'No se copió ningún turno: la semana siguiente ya los tenía asignados.';
        }

        return redirect()->to('turnos?semana=' . $siguiente)->with('ok', $mensaje);
    }

    // ─────────────────────────── Ausencias ───────────────────────────

    public function ausencias()
    {
        return view('turnos/ausencias', [
            'titulo'     => 'Ausencias',
            'seccion'    => 'ausencias',
            'solicitadas' => $this->ausencias->conEmpleado('solicitada'),
            'historial'  => $this->ausencias->conEmpleado(),
            'empleados'  => $this->empleados->activos(),
            'tipos'      => AusenciaModel::TIPOS,
            'colores'    => AusenciaModel::COLORES,
        ]);
    }

    public function guardarAusencia()
    {
        $empleadoId = (int) $this->request->getPost('empleado_id');
        $desde      = (string) $this->request->getPost('desde');
        $hasta      = (string) $this->request->getPost('hasta');
        $tipo       = (string) $this->request->getPost('tipo');

        if ($this->empleados->find($empleadoId) === null) {
            return redirect()->to('ausencias')->with('error', 'Elige un empleado.');
        }
        if (! $this->fechaValida($desde) || ! $this->fechaValida($hasta) || $hasta < $desde) {
            return redirect()->to('ausencias')->with('error', 'Revisa las fechas: la de fin no puede ser anterior a la de inicio.');
        }

        $this->ausencias->insert([
            'empleado_id' => $empleadoId,
            'tipo'        => array_key_exists($tipo, AusenciaModel::TIPOS) ? $tipo : 'permiso',
            'desde'       => $desde,
            'hasta'       => $hasta,
            'motivo'      => trim((string) $this->request->getPost('motivo')) ?: null,
            'estado'      => 'solicitada',
        ]);

        return redirect()->to('ausencias')->with('ok', 'Ausencia registrada, pendiente de aprobar.');
    }

    public function resolverAusencia(int $id)
    {
        $ausencia = $this->ausencias->find($id);
        if ($ausencia === null || $ausencia['estado'] !== 'solicitada') {
            return redirect()->to('ausencias')->with('error', 'Solo se pueden resolver ausencias pendientes.');
        }

        $aprobar = $this->request->getPost('decision') === 'aprobar';

        $this->ausencias->update($id, [
            'estado'       => $aprobar ? 'aprobada' : 'rechazada',
            'aprobada_por' => session()->get('usuario_id'),
            'aprobada_en'  => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('ausencias')->with('ok', $aprobar
            ? 'Ausencia aprobada. Aparecerá marcada en el cuadrante de turnos.'
            : 'Ausencia rechazada.');
    }

    public function eliminarAusencia(int $id)
    {
        $this->ausencias->delete($id);

        return redirect()->to('ausencias')->with('ok', 'Ausencia eliminada.');
    }

    // ─────────────────────────── Ayudantes ───────────────────────────

    /** Lunes de la semana que contiene la fecha dada (o de la semana actual). */
    private function lunesDe(string $fecha): \DateTime
    {
        $d = \DateTime::createFromFormat('Y-m-d', trim($fecha)) ?: new \DateTime('today');
        $d->setTime(0, 0);

        $diaSemana = (int) $d->format('N');   // 1 = lunes

        return $d->modify('-' . ($diaSemana - 1) . ' days');
    }

    private function fechaValida(string $fecha): bool
    {
        return \DateTime::createFromFormat('Y-m-d', trim($fecha)) !== false;
    }
}
