<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\HuespedModel;
use App\Models\PqrModel;
use App\Models\PqrNotaModel;
use App\Models\UsuarioModel;

/**
 * Peticiones, quejas y reclamos.
 *
 * La pantalla está ordenada por fecha de vencimiento y no por fecha de entrada
 * a propósito: lo que hay que mirar primero no es lo más nuevo, es lo que se
 * está quedando sin plazo.
 */
class Pqr extends BaseController
{
    private PqrModel $pqr;
    private PqrNotaModel $notas;

    public function __construct()
    {
        $this->pqr   = new PqrModel();
        $this->notas = new PqrNotaModel();
    }

    public function index()
    {
        $filtros = [
            'estado'         => (string) ($this->request->getGet('estado') ?? 'abiertas'),
            'tipo'           => (string) $this->request->getGet('tipo'),
            'responsable_id' => (int) $this->request->getGet('responsable_id'),
        ];

        return view('pqr/index', [
            'titulo'   => 'Quejas y peticiones',
            'seccion'  => 'huespedes',
            'lista'    => $this->pqr->listar($filtros),
            'riesgo'   => $this->pqr->enRiesgo(),
            'conteo'   => $this->pqr->conteo(),
            'filtros'  => $filtros,
            'tipos'    => PqrModel::TIPOS,
            'estados'  => PqrModel::ESTADOS,
            'canales'  => PqrModel::CANALES,
            'plazos'   => PqrModel::PLAZOS,
            'responsables' => (new UsuarioModel())->conPermiso('pqr.responder'),
        ]);
    }

    public function ver(int $id)
    {
        $ficha = $this->pqr->detalle($id);

        if ($ficha === null) {
            return redirect()->to('pqr')->with('error', 'Esa PQR no existe.');
        }

        return view('pqr/ver', [
            'titulo'  => $ficha['codigo'],
            'seccion' => 'huespedes',
            'p'       => $ficha,
            'notas'   => $this->notas->deP($id),
            'tipos'   => PqrModel::TIPOS,
            'estados' => PqrModel::ESTADOS,
            'canales' => PqrModel::CANALES,
            'tipos_nota' => PqrNotaModel::TIPOS,
            'responsables' => (new UsuarioModel())->conPermiso('pqr.responder'),
            'dias_restantes' => $this->diasHabilesRestantes($ficha),
        ]);
    }

    /** Cuántos días hábiles quedan. En negativo, cuántos se pasó. */
    private function diasHabilesRestantes(array $ficha): ?int
    {
        if ($ficha['vence_en'] === null || ! in_array($ficha['estado'], PqrModel::ABIERTAS, true)) {
            return null;
        }

        $hoy   = date('Y-m-d');
        $signo = $ficha['vence_en'] >= $hoy ? 1 : -1;
        [$desde, $hasta] = $signo === 1 ? [$hoy, $ficha['vence_en']] : [$ficha['vence_en'], $hoy];

        $dias  = 0;
        $fecha = $desde;

        for ($i = 0; $i < 400 && $fecha < $hasta; $i++) {
            $fecha = date('Y-m-d', strtotime($fecha . ' +1 day'));

            if (! \App\Libraries\FestivosColombia::esNoLaborable($fecha)) {
                $dias++;
            }
        }

        return $dias * $signo;
    }

    // ── Registrar ───────────────────────────────────────────────────────

    public function guardar()
    {
        $tipo = (string) $this->request->getPost('tipo');
        $tipo = array_key_exists($tipo, PqrModel::TIPOS) ? $tipo : 'queja';

        $canal = (string) $this->request->getPost('canal_entrada');
        $canal = array_key_exists($canal, PqrModel::CANALES) ? $canal : 'presencial';

        $huespedId = (int) $this->request->getPost('huesped_id') ?: null;
        $reservaId = (int) $this->request->getPost('reserva_id') ?: null;

        // Si viene de una reserva, el huésped se deduce: pedirlo dos veces es
        // pedir que alguien se equivoque.
        if ($huespedId === null && $reservaId !== null) {
            $reserva = db_connect()->table('reservas')->where('id', $reservaId)->get()->getRowArray();

            if ($reserva !== null) {
                $huespedId = $reserva['huesped_id'] !== null ? (int) $reserva['huesped_id'] : null;
            }
        }

        $datos = [
            'codigo'        => $this->pqr->siguienteCodigo(),
            'tipo'          => $tipo,
            'huesped_id'    => $huespedId,
            'reserva_id'    => $reservaId,
            'canal_entrada' => $canal,
            'nombre_contacto'   => trim((string) $this->request->getPost('nombre_contacto')) ?: null,
            'email_contacto'    => trim((string) $this->request->getPost('email_contacto')) ?: null,
            'telefono_contacto' => trim((string) $this->request->getPost('telefono_contacto')) ?: null,
            'asunto'    => trim((string) $this->request->getPost('asunto')),
            'detalle'   => trim((string) $this->request->getPost('detalle')),
            'estado'    => 'recibida',
            'prioridad' => array_key_exists((string) $this->request->getPost('prioridad'), \App\Models\MantenimientoModel::PRIORIDADES)
                ? (string) $this->request->getPost('prioridad') : 'media',
            'responsable_id' => (int) $this->request->getPost('responsable_id') ?: null,
            // El plazo se congela al registrarla. Recalcularlo al mirarla
            // movería la fecha límite cada vez que se abre la pantalla.
            'vence_en'  => $this->pqr->vencimiento($tipo),
        ];

        if (! $this->pqr->insert($datos)) {
            return redirect()->back()->withInput()->with('errores', $this->pqr->errors());
        }

        $id = (int) $this->pqr->getInsertID();

        $this->notas->apuntar(
            $id,
            'estado',
            'Registrada. Plazo hasta el ' . date('d/m/Y', strtotime($datos['vence_en'])) . '.',
            session()->get('usuario_id')
        );

        return redirect()->to('pqr/ver/' . $id)
            ->with('ok', 'Registrada como ' . $datos['codigo'] . '. Hay que contestar antes del '
                . date('d/m/Y', strtotime($datos['vence_en'])) . '.');
    }

    // ── Gestionar ───────────────────────────────────────────────────────

    public function asignar(int $id)
    {
        if ($this->pqr->find($id) === null) {
            return redirect()->to('pqr')->with('error', 'Esa PQR no existe.');
        }

        $responsableId = (int) $this->request->getPost('responsable_id') ?: null;
        $nombre        = 'nadie';

        if ($responsableId !== null) {
            $usuario = (new UsuarioModel())->find($responsableId);
            $nombre  = $usuario['nombre'] ?? 'alguien';
        }

        $this->pqr->update($id, ['responsable_id' => $responsableId, 'estado' => 'en_gestion']);
        $this->notas->apuntar($id, 'asignacion', 'A cargo de ' . $nombre . '.', session()->get('usuario_id'));

        return redirect()->to('pqr/ver/' . $id)->with('ok', 'Repartida.');
    }

    public function nota(int $id)
    {
        if ($this->pqr->find($id) === null) {
            return redirect()->to('pqr')->with('error', 'Esa PQR no existe.');
        }

        $texto = trim((string) $this->request->getPost('texto'));

        if ($texto === '') {
            return redirect()->to('pqr/ver/' . $id)->with('error', 'Escribe qué pasó.');
        }

        $this->notas->apuntar(
            $id,
            (string) $this->request->getPost('tipo'),
            $texto,
            session()->get('usuario_id')
        );

        return redirect()->to('pqr/ver/' . $id)->with('ok', 'Apuntado.');
    }

    public function responder(int $id)
    {
        $ficha = $this->pqr->find($id);

        if ($ficha === null) {
            return redirect()->to('pqr')->with('error', 'Esa PQR no existe.');
        }

        $respuesta = trim((string) $this->request->getPost('respuesta'));

        if ($respuesta === '') {
            return redirect()->to('pqr/ver/' . $id)->with('error', 'Escribe la respuesta que se le dio.');
        }

        $ahora = date('Y-m-d H:i:s');

        $this->pqr->update($id, [
            'estado'        => 'respondida',
            'respuesta'     => $respuesta,
            'respondida_en' => $ahora,
            'respondio_id'  => session()->get('usuario_id'),
            'causa'         => trim((string) $this->request->getPost('causa')) ?: null,
        ]);

        $this->notas->apuntar($id, 'respuesta', $respuesta, session()->get('usuario_id'));

        // Decirlo aquí importa: contestar fuera de plazo no se arregla, pero
        // sí se puede dejar de repetir.
        $tarde = $ficha['vence_en'] !== null && date('Y-m-d') > $ficha['vence_en'];

        return redirect()->to('pqr/ver/' . $id)->with(
            $tarde ? 'error' : 'ok',
            $tarde
                ? 'Respondida, pero fuera del plazo legal. Queda apuntado.'
                : 'Respondida dentro de plazo.'
        );
    }

    public function cerrar(int $id)
    {
        $ficha = $this->pqr->find($id);

        if ($ficha === null) {
            return redirect()->to('pqr')->with('error', 'Esa PQR no existe.');
        }

        if ($ficha['respondida_en'] === null) {
            // Cerrar sin contestar es hacer desaparecer una queja del tablero
            // sin que el huésped haya sabido nada.
            return redirect()->to('pqr/ver/' . $id)
                ->with('error', 'Antes de cerrarla hay que contestarla. Si no procede, contéstalo y ciérrala.');
        }

        $satisfaccion = (int) $this->request->getPost('satisfaccion');

        $this->pqr->update($id, [
            'estado'       => 'cerrada',
            'cerrada_en'   => date('Y-m-d H:i:s'),
            'satisfaccion' => $satisfaccion >= 1 && $satisfaccion <= 5 ? $satisfaccion : null,
        ]);

        $this->notas->apuntar($id, 'estado', 'Cerrada.', session()->get('usuario_id'));

        return redirect()->to('pqr/ver/' . $id)->with('ok', 'Cerrada.');
    }

    /** Se reabre cuando el huésped vuelve a escribir por lo mismo. */
    public function reabrir(int $id)
    {
        $ficha = $this->pqr->find($id);

        if ($ficha === null) {
            return redirect()->to('pqr')->with('error', 'Esa PQR no existe.');
        }

        $motivo = trim((string) $this->request->getPost('motivo'));

        $this->pqr->update($id, [
            'estado' => 'reabierta',
            // Plazo nuevo desde hoy: si no, nacería ya vencida y nadie la
            // miraría por estar siempre en rojo.
            'vence_en' => $this->pqr->vencimiento($ficha['tipo']),
        ]);

        $this->notas->apuntar(
            $id,
            'estado',
            'Reabierta' . ($motivo !== '' ? ': ' . $motivo : '.'),
            session()->get('usuario_id')
        );

        return redirect()->to('pqr/ver/' . $id)->with('ok', 'Reabierta con plazo nuevo.');
    }

    // ── Informe ─────────────────────────────────────────────────────────

    public function informe()
    {
        $desde = (string) ($this->request->getGet('desde') ?: date('Y-m-d', strtotime('-12 months')));
        $hasta = (string) ($this->request->getGet('hasta') ?: date('Y-m-d'));

        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return view('pqr/informe', [
            'titulo'  => 'Informe de PQR',
            'seccion' => 'huespedes',
            'desde'   => $desde,
            'hasta'   => $hasta,
            'datos'   => $this->pqr->informe($desde, $hasta),
            'tipos'   => PqrModel::TIPOS,
        ]);
    }

    /** El buscador de huéspedes del formulario. */
    public function buscarHuesped()
    {
        $q = trim((string) $this->request->getGet('q'));

        if (mb_strlen($q) < 2) {
            return $this->response->setJSON([]);
        }

        $filas = (new HuespedModel())->activos()
            ->groupStart()
                ->like('nombre', $q)->orLike('apellidos', $q)
                ->orLike('num_documento', $q)->orLike('email', $q)
            ->groupEnd()
            ->orderBy('apellidos')
            ->findAll(10);

        return $this->response->setJSON(array_map(static fn (array $h): array => [
            'id'     => (int) $h['id'],
            'nombre' => trim($h['nombre'] . ' ' . $h['apellidos']),
            'doc'    => $h['num_documento'],
        ], $filas));
    }
}
