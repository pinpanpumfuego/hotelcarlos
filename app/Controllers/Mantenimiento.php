<?php

namespace App\Controllers;

use App\Models\MantenimientoModel;
use App\Models\UnidadModel;

/** Incidencias de mantenimiento: cualquier miembro del personal puede reportar. */
class Mantenimiento extends BaseController
{
    private MantenimientoModel $incidencias;
    private UnidadModel $unidades;

    public function __construct()
    {
        $this->incidencias = new MantenimientoModel();
        $this->unidades    = new UnidadModel();
    }

    public function index()
    {
        return view('mantenimiento/index', [
            'titulo'      => 'Mantenimiento',
            'seccion'     => 'mantenimiento',
            'abiertas'    => $this->incidencias->listar(true),
            'resueltas'   => $this->incidencias->listar(false),
            'unidades'    => $this->unidades->orderBy('nombre')->findAll(),
            'prioridades' => MantenimientoModel::PRIORIDADES,
            // El desplegable solo si puede verlos: quien no tiene `activos.ver`
            // no debería enterarse aquí de qué equipos hay.
            'equipos'     => puede('activos.ver')
                ? (new \App\Models\ActivoModel())->listar()
                : [],
        ]);
    }

    public function guardar()
    {
        // La pantalla del QR manda aquí también, y quiere volver a la ficha del
        // equipo en vez de al tablero.
        $volver = (string) $this->request->getPost('volver');
        $volver = preg_match('#^activos/ver/\d+$#', $volver) === 1 ? $volver : 'mantenimiento';

        $titulo = trim((string) $this->request->getPost('titulo'));
        if ($titulo === '') {
            return redirect()->to($volver)->with('error', 'Describe brevemente la incidencia.');
        }

        $activoId = (int) $this->request->getPost('activo_id') ?: null;
        $unidadId = (int) $this->request->getPost('unidad_id') ?: null;
        $activo   = $activoId !== null ? (new \App\Models\ActivoModel())->find($activoId) : null;

        if ($activo === null) {
            $activoId = null;
        } elseif ($unidadId === null) {
            // Si el equipo vive en una cabaña, la avería es de esa cabaña
            // aunque quien reporta no lo haya dicho.
            $unidadId = $activo['unidad_id'] !== null ? (int) $activo['unidad_id'] : null;
        }

        $prioridad = array_key_exists((string) $this->request->getPost('prioridad'), MantenimientoModel::PRIORIDADES)
            ? (string) $this->request->getPost('prioridad')
            : 'media';

        $this->incidencias->insert([
            'unidad_id'   => $unidadId,
            'activo_id'   => $activoId,
            'ubicacion'   => $unidadId === null
                ? (trim((string) $this->request->getPost('ubicacion')) ?: ($activo['ubicacion'] ?? 'Zonas comunes'))
                : null,
            'titulo'      => $titulo,
            'tipo'        => 'correctiva',
            'origen'      => $this->origenDeQuienReporta(),
            'descripcion' => trim((string) $this->request->getPost('descripcion')) ?: null,
            'prioridad'   => $prioridad,
            'estado'      => 'abierta',
            'reporto_id'  => session()->get('usuario_id'),
            // El plazo se calcula al abrirla, no al mirarla: si mañana se
            // cambia el plazo de «urgente», lo abierto hoy conserva el suyo.
            'vence_en'    => $this->incidencias->vencimiento($prioridad),
        ]);

        $id = (int) $this->incidencias->getInsertID();

        // Si la avería compromete la cabaña, se bloquea y deja de venderse al instante
        $aviso = '';
        if ($unidadId !== null && $this->request->getPost('bloquear')) {
            (new \App\Libraries\Housekeeping())->bloquear(
                $unidadId,
                'mantenimiento',
                mb_substr($titulo, 0, 300)
            );
            $this->incidencias->update($id, ['bloqueo_unidad' => 1]);
            $aviso = ' La cabaña quedó bloqueada y no se venderá hasta resolverla.';
        }

        // Un equipo con avería abierta deja de estar «en servicio»: si no, el
        // listado diría que todo está bien mientras el tablero dice lo contrario.
        if ($activo !== null && $activo['estado'] === 'activo') {
            (new \App\Models\ActivoModel())->update($activoId, ['estado' => 'averiado']);
        }

        return redirect()->to($volver)->with('ok', 'Incidencia reportada.' . $aviso);
    }

    /**
     * De dónde salió la avería, según quién la reporta.
     *
     * Importa para saber si los huéspedes se están encontrando las averías
     * antes que nosotros, que es la señal de que el preventivo no funciona.
     */
    private function origenDeQuienReporta(): string
    {
        return match (true) {
            puede('mantenimiento.trabajar') => 'tecnico',
            puede('limpieza.trabajar')      => 'limpieza',
            puede('reservas.checkin')       => 'recepcion',
            default                         => 'otro',
        };
    }

    public function iniciar(int $id)
    {
        $incidencia = $this->incidencias->find($id);
        if ($incidencia === null || $incidencia['estado'] !== 'abierta') {
            return redirect()->to('mantenimiento')->with('error', 'Esa incidencia no está abierta.');
        }

        $this->incidencias->update($id, [
            'estado'      => 'en_proceso',
            'iniciada_en' => date('Y-m-d H:i:s'),
            // Quien la empieza se la queda, salvo que ya tuviera dueño
            'asignado_a'  => $incidencia['asignado_a'] ?? session()->get('usuario_id'),
        ]);

        return redirect()->to('mantenimiento')->with('ok', 'Incidencia en proceso.');
    }

    public function resolver(int $id)
    {
        $incidencia = $this->incidencias->find($id);
        if ($incidencia === null || $incidencia['estado'] === 'resuelta') {
            return redirect()->to('mantenimiento')->with('error', 'Esa incidencia ya está resuelta.');
        }

        $solucion = trim((string) $this->request->getPost('solucion'));
        if ($solucion === '') {
            return redirect()->to('mantenimiento')->with('error', 'Describe qué se hizo para resolverla.');
        }

        $ahora = date('Y-m-d H:i:s');

        $this->incidencias->update($id, [
            'estado'      => 'resuelta',
            'solucion'    => $solucion,
            'resolvio_id' => session()->get('usuario_id'),
            'resuelta_en' => $ahora,
            // Cuánto se tardó de verdad, desde que se empezó. Si nadie pulsó
            // «iniciar», se queda en blanco: inventar un dato de tiempo sería
            // peor que no tenerlo, porque los informes se lo creerían.
            'minutos'     => $incidencia['iniciada_en'] !== null
                ? max(1, (int) round((strtotime($ahora) - strtotime($incidencia['iniciada_en'])) / 60))
                : null,
        ]);

        $aviso = '';

        // Si la cabaña estaba bloqueada por esto, vuelve a venderse — pero
        // sucia: después de una reparación queda polvo, y que salga directa a
        // «disponible» significa que el siguiente huésped se la encuentra así.
        if ($incidencia['unidad_id'] !== null) {
            $unidad = $this->unidades->find($incidencia['unidad_id']);

            if ($unidad !== null && $unidad['estado'] === 'bloqueada' && $unidad['motivo_bloqueo'] === 'mantenimiento') {
                (new \App\Libraries\Housekeeping())->desbloquear((int) $unidad['id']);
                $this->unidades->update($unidad['id'], ['estado_limpieza' => 'sucia']);
                $aviso = ' La cabaña vuelve a venderse y queda pendiente de limpieza.';
            }
        }

        // El equipo vuelve a estar en servicio, salvo que le quede otra avería
        // abierta: darlo por bueno con algo sin cerrar sería mentir.
        if ($incidencia['activo_id'] !== null) {
            $quedan = $this->incidencias
                ->where('activo_id', $incidencia['activo_id'])
                ->whereIn('estado', MantenimientoModel::ABIERTAS)
                ->countAllResults();

            if ($quedan === 0) {
                $activos = new \App\Models\ActivoModel();
                $activo  = $activos->find($incidencia['activo_id']);

                if ($activo !== null && in_array($activo['estado'], ['averiado', 'reparacion'], true)) {
                    $activos->update($activo['id'], ['estado' => 'activo']);
                }
            }
        }

        return redirect()->to('mantenimiento')->with('ok', 'Incidencia resuelta.' . $aviso);
    }
}
