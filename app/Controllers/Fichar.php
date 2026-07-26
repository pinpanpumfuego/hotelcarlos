<?php

namespace App\Controllers;

use App\Libraries\Fichaje;
use App\Models\ConfiguracionModel;
use App\Models\EmpleadoModel;
use App\Models\FichajeModel;

/**
 * Terminal de fichaje: la tableta o el computador que está en recepción.
 *
 * No pide sesión a propósito — el trabajador de limpieza no tiene por qué
 * tener usuario del sistema — pero por eso mismo lleva freno de intentos,
 * se puede apagar desde Administración y todo queda registrado.
 */
class Fichar extends BaseController
{
    private EmpleadoModel $empleados;
    private FichajeModel $fichajes;

    public function __construct()
    {
        $this->empleados = new EmpleadoModel();
        $this->fichajes  = new FichajeModel();
    }

    /** Pantalla del terminal. */
    public function index()
    {
        if (! $this->terminalActivo()) {
            return view('fichar/apagado', ['hotel' => config('Hotel')]);
        }

        return view('fichar/terminal', [
            'hotel'     => config('Hotel'),
            'pideFoto'  => $this->pideFoto(),
            'presentes' => $this->fichajes->presentes(),
        ]);
    }

    /**
     * Paso 1: el trabajador teclea su PIN y el terminal le dice quién es
     * y qué puede fichar ahora. Todavía no se registra nada.
     */
    public function identificar()
    {
        if (! $this->terminalActivo()) {
            return $this->error('El terminal de fichaje está apagado.', 403);
        }

        // Freno por dispositivo: 12 intentos por minuto bastan para trabajar
        // y no para probar los 10.000 PIN posibles.
        $clave = 'fichaje-' . md5($this->request->getIPAddress());
        if (service('throttler')->check($clave, 12, MINUTE) === false) {
            return $this->error('Demasiados intentos seguidos. Espera un minuto.', 429);
        }

        $pin      = (string) ($this->request->getJSON(true)['pin'] ?? '');
        $empleado = $this->empleados->porPin($pin);

        if ($empleado === null) {
            log_message('warning', 'PIN de fichaje incorrecto desde {ip}', ['ip' => $this->request->getIPAddress()]);

            return $this->error('Ese PIN no corresponde a nadie. Revísalo o pídeselo a gerencia.', 404);
        }

        $id = (int) $empleado['id'];

        return $this->response->setJSON([
            'ok'       => true,
            'empleado' => [
                'id'     => $id,
                'nombre' => $empleado['nombre'],
                'apellidos' => $empleado['apellidos'],
                'cargo'  => $empleado['cargo'],
            ],
            'estado'   => $this->fichajes->estado($id),
            'acciones' => array_map(
                static fn ($t) => ['tipo' => $t, 'etiqueta' => FichajeModel::TIPOS[$t], 'icono' => FichajeModel::ICONOS[$t]],
                $this->fichajes->accionesPosibles($id)
            ),
            'ultimo'   => $this->ultimoLegible($id),
            'pide_foto' => $this->pideFoto(),
        ]);
    }

    /** Paso 2: se confirma la acción y, si toca, se guarda la foto. */
    public function marcar()
    {
        if (! $this->terminalActivo()) {
            return $this->error('El terminal de fichaje está apagado.', 403);
        }

        $datos    = $this->request->getJSON(true) ?? [];
        $empleado = $this->empleados->porPin((string) ($datos['pin'] ?? ''));

        if ($empleado === null) {
            return $this->error('Vuelve a teclear tu PIN.', 404);
        }

        // Si la cámara falla, el fichaje se registra igual pero queda señalado.
        // Nadie puede quedarse sin poder marcar su jornada porque un cacharro
        // se estropeó: eso es un problema del hotel, no del trabajador.
        $sinFoto = $this->pideFoto() && empty($datos['foto']);

        $r = (new Fichaje())->marcar($empleado, [
            'tipo'        => $datos['tipo'] ?? '',
            'origen'      => 'terminal',
            'foto'        => $datos['foto'] ?? null,
            'observacion' => $sinFoto ? 'Sin foto: la cámara no estaba disponible' : null,
        ]);

        if (! $r['ok']) {
            return $this->error($r['mensaje'], 422);
        }

        return $this->response->setJSON([
            'ok'       => true,
            'mensaje'  => $r['mensaje'],
            'sin_foto' => $sinFoto,
            'estado'   => $this->fichajes->estado((int) $empleado['id']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────

    private function ultimoLegible(int $empleadoId): ?string
    {
        $ultimo = $this->fichajes->ultimo($empleadoId);
        if ($ultimo === null) {
            return null;
        }

        $cuando = strtotime($ultimo['marcado_en']);
        $dia    = date('Y-m-d', $cuando) === date('Y-m-d')
            ? 'hoy'
            : (date('Y-m-d', $cuando) === date('Y-m-d', strtotime('-1 day')) ? 'ayer' : date('d/m', $cuando));

        return FichajeModel::TIPOS[$ultimo['tipo']] . ' ' . $dia . ' a las ' . date('H:i', $cuando);
    }

    private function terminalActivo(): bool
    {
        return (new ConfiguracionModel())->obtener('fichaje_terminal', '1') === '1';
    }

    private function pideFoto(): bool
    {
        return (new ConfiguracionModel())->obtener('fichaje_foto', '1') === '1';
    }

    private function error(string $mensaje, int $codigo)
    {
        return $this->response->setStatusCode($codigo)->setJSON(['ok' => false, 'error' => $mensaje]);
    }
}
