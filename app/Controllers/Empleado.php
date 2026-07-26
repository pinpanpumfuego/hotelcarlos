<?php

namespace App\Controllers;

use App\Libraries\Fichaje;
use App\Models\ConfiguracionModel;
use App\Models\EmpleadoModel;
use App\Models\FichajeModel;

/**
 * Portal del trabajador: una aplicación que se instala en el móvil (PWA).
 *
 * Tiene su propia sesión, separada de la del panel: un trabajador de limpieza
 * entra aquí con su documento y su PIN sin tener usuario del sistema.
 */
class Empleado extends BaseController
{
    private EmpleadoModel $empleados;
    private FichajeModel $fichajes;

    public function __construct()
    {
        $this->empleados = new EmpleadoModel();
        $this->fichajes  = new FichajeModel();
    }

    // ─────────────────────────────────────────────────────────────
    //  Acceso
    // ─────────────────────────────────────────────────────────────

    public function index()
    {
        $empleado = $this->actual();

        if ($empleado === null) {
            return view('empleado/entrar', [
                'hotel'  => config('Hotel'),
                'titulo' => 'Entrar',
            ]);
        }

        return $this->panel($empleado);
    }

    public function entrar()
    {
        // Freno: sin esto, el documento y un PIN de cuatro cifras se prueban solos
        $clave = 'empleado-' . md5($this->request->getIPAddress());
        if (service('throttler')->check($clave, 8, MINUTE) === false) {
            return redirect()->to('empleado')->with('error', 'Demasiados intentos. Espera un minuto.');
        }

        $documento = trim((string) $this->request->getPost('documento'));
        $pin       = trim((string) $this->request->getPost('pin'));

        $empleado = $this->empleados->porPin($pin);

        if ($empleado === null || $empleado['num_documento'] !== $documento) {
            log_message('warning', 'Acceso fallido al portal del empleado desde {ip}', ['ip' => $this->request->getIPAddress()]);

            return redirect()->to('empleado')->with('error', 'El documento o el PIN no coinciden.');
        }

        if ((int) $empleado['activo'] !== 1) {
            return redirect()->to('empleado')->with('error', 'Tu ficha está inactiva. Habla con gerencia.');
        }

        session()->regenerate();
        session()->set([
            'empleado_id'     => (int) $empleado['id'],
            'empleado_nombre' => $empleado['nombre'],
        ]);

        return redirect()->to('empleado');
    }

    public function salir()
    {
        session()->remove(['empleado_id', 'empleado_nombre']);

        return redirect()->to('empleado')->with('ok', 'Sesión cerrada.');
    }

    // ─────────────────────────────────────────────────────────────
    //  Pantalla principal
    // ─────────────────────────────────────────────────────────────

    private function panel(array $empleado)
    {
        $id     = (int) $empleado['id'];
        $config = new ConfiguracionModel();

        $desde = date('Y-m-d', strtotime('monday this week'));
        $hasta = date('Y-m-d', strtotime('sunday this week'));

        $jornadas = $this->fichajes->jornadas($id, $desde, $hasta);
        $minutos  = array_sum(array_column($jornadas, 'minutos'));

        return view('empleado/panel', [
            'hotel'     => config('Hotel'),
            'titulo'    => 'Mi jornada',
            'empleado'  => $empleado,
            'estado'    => $this->fichajes->estado($id),
            'acciones'  => $this->fichajes->accionesPosibles($id),
            'ultimo'    => $this->fichajes->ultimo($id),
            'jornadas'  => array_reverse($jornadas, true),
            'minutos'   => $minutos,
            'desde'     => $desde,
            'hasta'     => $hasta,
            'puedeMovil' => (int) $empleado['ficha_movil'] === 1
                && $config->obtener('fichaje_movil', '1') === '1',
            'radio'     => Fichaje::radioPermitido(),
            'turnos'    => $this->turnosProximos($id),
        ]);
    }

    /** Ficha desde el móvil, con la ubicación si el navegador la da. */
    public function fichar()
    {
        $empleado = $this->actual();
        if ($empleado === null) {
            return $this->response->setStatusCode(401)->setJSON(['ok' => false, 'error' => 'Vuelve a entrar.']);
        }

        $config = new ConfiguracionModel();
        if ((int) $empleado['ficha_movil'] !== 1 || $config->obtener('fichaje_movil', '1') !== '1') {
            return $this->response->setStatusCode(403)->setJSON([
                'ok' => false, 'error' => 'Tu ficha solo puede marcarse en el terminal del hotel.',
            ]);
        }

        $datos = $this->request->getJSON(true) ?? [];

        $r = (new Fichaje())->marcar($empleado, [
            'tipo'        => $datos['tipo'] ?? '',
            'origen'      => 'movil',
            'latitud'     => $datos['latitud'] ?? null,
            'longitud'    => $datos['longitud'] ?? null,
            'precision_m' => $datos['precision_m'] ?? null,
        ]);

        if (! $r['ok']) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['mensaje']]);
        }

        // Fuera del radio no se rechaza: se anota. Puede haber mala señal,
        // o el trabajador puede estar de verdad haciendo un recado del hotel.
        $aviso    = '';
        $radio    = Fichaje::radioPermitido();
        $distancia = $r['fichaje']['distancia_m'] ?? null;

        if ($radio > 0 && $distancia !== null && (int) $distancia > $radio) {
            $aviso = ' Quedó anotado que marcaste a ' . number_format((int) $distancia, 0, ',', '.')
                . ' m del hotel; gerencia lo verá.';
        }

        return $this->response->setJSON([
            'ok'      => true,
            'mensaje' => $r['mensaje'] . $aviso,
            'estado'  => $this->fichajes->estado((int) $empleado['id']),
        ]);
    }

    /** Historial propio del trabajador: tiene derecho a ver sus horas. */
    public function historial()
    {
        $empleado = $this->actual();
        if ($empleado === null) {
            return redirect()->to('empleado');
        }

        $mes  = (int) ($this->request->getGet('mes') ?: date('n'));
        $anio = (int) ($this->request->getGet('anio') ?: date('Y'));
        $mes  = max(1, min(12, $mes));

        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        $jornadas = $this->fichajes->jornadas((int) $empleado['id'], $desde, $hasta);

        return view('empleado/historial', [
            'hotel'    => config('Hotel'),
            'titulo'   => 'Mis horas',
            'empleado' => $empleado,
            'jornadas' => array_reverse($jornadas, true),
            'minutos'  => array_sum(array_column($jornadas, 'minutos')),
            'mes'      => $mes,
            'anio'     => $anio,
        ]);
    }

    /** Manifiesto de la aplicación instalable. */
    public function manifiesto()
    {
        $hotel = config('Hotel');

        return $this->response
            ->setContentType('application/manifest+json')
            ->setJSON([
                'name'             => 'Mi jornada · ' . $hotel->nombre,
                'short_name'       => 'Mi jornada',
                'description'      => 'Fichaje y horas del personal de ' . $hotel->nombre,
                'start_url'        => site_url('empleado'),
                'scope'            => site_url('empleado'),
                'display'          => 'standalone',
                'orientation'      => 'portrait',
                'background_color' => '#1f4d36',
                'theme_color'      => '#1f4d36',
                'lang'             => 'es-CO',
                'icons'            => [
                    ['src' => base_url('icono-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => base_url('icono-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ],
            ]);
    }

    // ─────────────────────────────────────────────────────────────

    /** El empleado de la sesión, o null. */
    private function actual(): ?array
    {
        $id = session()->get('empleado_id');
        if ($id === null) {
            return null;
        }

        $empleado = $this->empleados->find((int) $id);

        if ($empleado === null || (int) $empleado['activo'] !== 1) {
            session()->remove(['empleado_id', 'empleado_nombre']);

            return null;
        }

        return $empleado;
    }

    /** Próximos turnos asignados, si el módulo de turnos los tiene. */
    private function turnosProximos(int $empleadoId): array
    {
        return db_connect()->table('turnos')
            ->where('empleado_id', $empleadoId)
            ->where('fecha >=', date('Y-m-d'))
            ->orderBy('fecha')
            ->limit(5)
            ->get()
            ->getResultArray();
    }
}
