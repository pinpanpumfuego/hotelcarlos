<?php

namespace App\Controllers;

use App\Libraries\SincronizadorCanales;
use App\Models\BloqueoModel;
use App\Models\CanalConexionModel;
use App\Models\UnidadModel;

/** Conexión con Booking, Airbnb y demás portales mediante calendarios iCal. */
class Canales extends BaseController
{
    private CanalConexionModel $conexiones;
    private UnidadModel $unidades;
    private BloqueoModel $bloqueos;

    public function __construct()
    {
        $this->conexiones = new CanalConexionModel();
        $this->unidades   = new UnidadModel();
        $this->bloqueos   = new BloqueoModel();
    }

    public function index()
    {
        $unidades = $this->unidades->conTipo();
        $sinc     = new SincronizadorCanales();

        // Cada cabaña necesita su dirección secreta para poder exportar
        foreach ($unidades as $u) {
            $sinc->asegurarToken((int) $u['id']);
        }

        $porUnidad = [];
        foreach ($this->conexiones->listado() as $c) {
            $porUnidad[(int) $c['unidad_id']][] = $c;
        }

        return view('canales/index', [
            'titulo'     => 'Portales y canales',
            'seccion'    => 'canales',
            'unidades'   => $this->unidades->conTipo(),
            'conexiones' => $porUnidad,
            'canales'    => CanalConexionModel::sincronizables(),
            'conflictos' => $this->bloqueos->conflictos(),
            'bloqueos'   => $this->bloqueos->enRango(date('Y-m-d'), date('Y-m-d', strtotime('+6 months'))),
            'ultimaSync' => $this->conexiones->masAntigua(),
        ]);
    }

    /** Guarda o actualiza la dirección de importación de una cabaña y un portal. */
    public function guardar()
    {
        $unidadId = (int) $this->request->getPost('unidad_id');
        $canal    = (string) $this->request->getPost('canal');
        $url      = trim((string) $this->request->getPost('url_importar'));

        if ($this->unidades->find($unidadId) === null) {
            return redirect()->to('canales')->with('error', 'Esa cabaña no existe.');
        }
        if (! array_key_exists($canal, CanalConexionModel::sincronizables())) {
            return redirect()->to('canales')->with('error', 'Ese portal no está reconocido.');
        }
        if ($url !== '' && ! preg_match('~^https?://~i', $url)) {
            return redirect()->to('canales')->with('error', 'La dirección debe empezar por http:// o https://');
        }

        $existente = $this->conexiones->where('unidad_id', $unidadId)->where('canal', $canal)->first();

        $datos = [
            'unidad_id'    => $unidadId,
            'canal'        => $canal,
            'nombre'       => trim((string) $this->request->getPost('nombre')) ?: null,
            'url_importar' => $url ?: null,
            'activa'       => $this->request->getPost('activa') !== null ? 1 : 0,
        ];

        if ($existente === null) {
            $id = $this->conexiones->insert($datos);
        } else {
            $id = (int) $existente['id'];
            $this->conexiones->update($id, $datos);
        }

        // Se lee al momento: así se sabe enseguida si la dirección sirve
        if ($url !== '' && $datos['activa'] === 1) {
            $r = (new SincronizadorCanales())->sincronizar($this->conexiones->find($id));

            return redirect()->to('canales')->with(
                $r['ok'] ? 'ok' : 'error',
                $r['ok']
                    ? 'Conectado. Se trajeron ' . $r['eventos'] . ' fecha' . ($r['eventos'] === 1 ? '' : 's') . ' ocupada' . ($r['eventos'] === 1 ? '' : 's') . '.'
                    : 'Se guardó, pero no se pudo leer el calendario: ' . $r['error']
            );
        }

        return redirect()->to('canales')->with('ok', 'Conexión guardada.');
    }

    /** Lee ahora mismo todos los calendarios. */
    public function sincronizar()
    {
        $r = (new SincronizadorCanales())->sincronizarTodo();

        if ($r['leidas'] === 0 && $r['fallidas'] === 0) {
            return redirect()->to('canales')->with('error', 'No hay ninguna conexión activa con dirección puesta.');
        }

        $mensaje = $r['leidas'] . ' calendario' . ($r['leidas'] === 1 ? '' : 's') . ' leído'
            . ($r['leidas'] === 1 ? '' : 's') . ' con ' . $r['eventos'] . ' fechas ocupadas.';

        if ($r['fallidas'] > 0) {
            return redirect()->to('canales')->with(
                'error',
                $mensaje . ' Fallaron ' . $r['fallidas'] . ': ' . implode(' · ', $r['detalle'])
            );
        }

        return redirect()->to('canales')->with('ok', $mensaje);
    }

    public function eliminar(int $id)
    {
        $conexion = $this->conexiones->find($id);
        if ($conexion === null) {
            return redirect()->to('canales')->with('error', 'Esa conexión no existe.');
        }

        // Al quitar la conexión se van sus bloqueos: esas fechas vuelven a estar libres
        $this->bloqueos->where('conexion_id', $id)->where('origen', 'ical')->delete();
        $this->conexiones->delete($id);

        return redirect()->to('canales')->with('ok', 'Conexión eliminada y fechas liberadas.');
    }

    /** Cambia la dirección secreta de una cabaña, por si se filtró. */
    public function renovarToken(int $unidadId)
    {
        if ($this->unidades->find($unidadId) === null) {
            return redirect()->to('canales')->with('error', 'Esa cabaña no existe.');
        }

        $this->unidades->update($unidadId, ['token_ical' => bin2hex(random_bytes(24))]);

        return redirect()->to('canales')->with(
            'ok',
            'Dirección renovada. Tienes que volver a pegarla en los portales: la anterior ya no funciona.'
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Bloqueos a mano
    // ─────────────────────────────────────────────────────────────

    public function bloquear()
    {
        $unidadId = (int) $this->request->getPost('unidad_id');
        $desde    = (string) $this->request->getPost('desde');
        $hasta    = (string) $this->request->getPost('hasta');

        if ($this->unidades->find($unidadId) === null) {
            return redirect()->to('canales')->with('error', 'Esa cabaña no existe.');
        }
        if (strtotime($desde) === false || strtotime($hasta) === false || $hasta <= $desde) {
            return redirect()->to('canales')->with('error', 'Revisa las fechas: la de fin debe ser posterior a la de inicio.');
        }

        $motivo = trim((string) $this->request->getPost('resumen')) ?: 'No disponible';

        // Si ya hay una reserva ahí, mejor decirlo antes de tapar la fecha
        $ocupada = (new \App\Models\ReservaModel())
            ->where('unidad_id', $unidadId)
            ->whereIn('estado', \App\Models\ReservaModel::ESTADOS_ACTIVOS)
            ->where('fecha_entrada <', $hasta)
            ->where('fecha_salida >', $desde)
            ->countAllResults();

        $this->bloqueos->insert([
            'unidad_id'     => $unidadId,
            'canal'         => 'otro',
            'resumen'       => $motivo,
            'fecha_entrada' => $desde,
            'fecha_salida'  => $hasta,
            'origen'        => 'manual',
            'notas'         => trim((string) $this->request->getPost('notas')) ?: null,
            'usuario_id'    => session()->get('usuario_id'),
        ]);

        return redirect()->to('canales')->with(
            'ok',
            'Fechas bloqueadas.' . ($ocupada > 0
                ? ' Ojo: ya había ' . $ocupada . ' reserva(s) propia(s) en ese tramo.'
                : '')
        );
    }

    public function desbloquear(int $id)
    {
        $bloqueo = $this->bloqueos->find($id);
        if ($bloqueo === null) {
            return redirect()->to('canales')->with('error', 'Ese bloqueo no existe.');
        }

        if ($bloqueo['origen'] === 'ical') {
            return redirect()->to('canales')->with(
                'error',
                'Ese bloqueo viene de ' . CanalConexionModel::nombreCanal($bloqueo['canal'])
                    . '. Cancélalo allí: si lo borras aquí, volverá en la próxima sincronización.'
            );
        }

        $this->bloqueos->delete($id);

        return redirect()->to('canales')->with('ok', 'Fechas liberadas.');
    }

    // ─────────────────────────────────────────────────────────────
    //  Calendario público (lo leen las plataformas)
    // ─────────────────────────────────────────────────────────────

    /**
     * Devuelve el calendario de una cabaña.
     *
     * No pide sesión —Booking no puede iniciarla— pero la dirección lleva un
     * token de 48 caracteres y solo publica fechas: ni nombres ni importes.
     */
    public function exportar(string $token)
    {
        $unidad = $this->unidades->where('token_ical', $token)->first();

        if ($unidad === null || strlen($token) < 32) {
            return $this->response->setStatusCode(404)->setBody('No encontrado.');
        }

        $calendario = (new SincronizadorCanales())->calendarioDeUnidad($unidad);

        return $this->response
            ->setHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->setHeader('Content-Disposition', 'inline; filename="' . url_title($unidad['nombre'], '-', true) . '.ics"')
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setBody($calendario);
    }
}
