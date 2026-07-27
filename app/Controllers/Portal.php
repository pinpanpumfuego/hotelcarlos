<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EncuestaModel;
use App\Models\FolioModel;
use App\Models\PortalAccesoModel;
use App\Models\RegistroModel;
use App\Models\ReservaModel;
use App\Models\SolicitudModel;

/**
 * Portal del huésped: su estancia, en su móvil.
 *
 * Sin cuenta y sin contraseña: **el enlace es la credencial**. Por eso cada
 * método vuelve a validar el token, y ninguno se fía de que el anterior lo
 * hiciera. Es repetitivo a propósito; en algo abierto a internet, repetir la
 * comprobación cuesta una línea y olvidarla cuesta una filtración.
 */
class Portal extends BaseController
{
    private PortalAccesoModel $accesos;
    private ReservaModel $reservas;

    /** Peticiones que puede hacer una misma reserva en un día. */
    private const TOPE_SOLICITUDES_DIA = 12;

    public function __construct()
    {
        $this->accesos  = new PortalAccesoModel();
        $this->reservas = new ReservaModel();
    }

    // ── Pantallas ───────────────────────────────────────────────────────

    public function index(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $registro = (new RegistroModel())->where('reserva_id', $ctx['reserva']['id'])->first();

        return view('portal/inicio', $ctx + [
            'registro'   => $registro,
            'noches'     => $this->noches($ctx['reserva']),
            'fase'       => $this->fase($ctx['reserva']),
            'pendientes' => (new SolicitudModel())->deReserva((int) $ctx['reserva']['id']),
        ]);
    }

    /** Los consumos, casi en tiempo real: es el folio tal cual. */
    public function cuenta(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $folio       = new FolioModel();
        $reservaId   = (int) $ctx['reserva']['id'];

        return view('portal/cuenta', $ctx + [
            'movimientos' => $folio->movimientosDeReserva($reservaId),
            'saldo'       => $folio->saldo($reservaId),
            'pagado'      => $folio->totalPagado($reservaId),
            'descuentos'  => $folio->totalDescuentos($reservaId),
        ]);
    }

    /** Directorio, normas, cómo llegar y a quién llamar. */
    public function info(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        return view('portal/info', $ctx);
    }

    public function solicitudes(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        return view('portal/solicitudes', $ctx + [
            'tipos'   => SolicitudModel::TIPOS,
            'previas' => (new SolicitudModel())->deReserva((int) $ctx['reserva']['id']),
            'aviso'   => SolicitudModel::REQUIEREN_CONFIRMACION,
        ]);
    }

    public function pedir(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $reservaId   = (int) $ctx['reserva']['id'];
        $solicitudes = new SolicitudModel();

        // Freno sencillo: doce peticiones en un día no las hace nadie de buena fe
        if ($solicitudes->hoyDe($reservaId) >= self::TOPE_SOLICITUDES_DIA) {
            return redirect()->to($this->ruta($token, 'solicitudes'))
                ->with('error', lang('Portal.demasiadasSolicitudes'));
        }

        $tipo = (string) $this->request->getPost('tipo');
        if (! isset(SolicitudModel::TIPOS[$tipo])) {
            return redirect()->to($this->ruta($token, 'solicitudes'))
                ->with('error', lang('Portal.eligeQueNecesitas'));
        }

        $solicitudes->insert([
            'reserva_id'  => $reservaId,
            'unidad_id'   => $ctx['reserva']['unidad_id'] ?? null,
            'tipo'        => $tipo,
            'detalle'     => mb_substr(trim((string) $this->request->getPost('detalle')), 0, 1000) ?: null,
            'para_cuando' => mb_substr(trim((string) $this->request->getPost('para_cuando')), 0, 100) ?: null,
            'estado'      => 'pendiente',
            'prioridad'   => $this->request->getPost('urgente') ? 'urgente' : 'normal',
        ]);

        // Las que dependen de que haya sitio o de que haya quien conduzca no se
        // prometen: se dice que se mira. Prometerlas sería mentir.
        $mensaje = in_array($tipo, SolicitudModel::REQUIEREN_CONFIRMACION, true)
            ? lang('Portal.recibidaLaMiramos')
            : lang('Portal.recibida');

        return redirect()->to($this->ruta($token, 'solicitudes'))->with('ok', $mensaje);
    }

    public function encuesta(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $momento = $this->momentoEncuesta($ctx['reserva']);

        return view('portal/encuesta', $ctx + [
            'momento'   => $momento,
            'apartados' => EncuestaModel::APARTADOS,
            'previa'    => (new EncuestaModel())->deReserva((int) $ctx['reserva']['id'], $momento),
        ]);
    }

    public function responderEncuesta(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $guardada = (new EncuestaModel())->responder(
            (int) $ctx['reserva']['id'],
            $this->momentoEncuesta($ctx['reserva']),
            [
                'general'    => $this->request->getPost('general'),
                'limpieza'   => $this->request->getPost('limpieza'),
                'atencion'   => $this->request->getPost('atencion'),
                'comida'     => $this->request->getPost('comida'),
                'entorno'    => $this->request->getPost('entorno'),
                'comentario' => $this->request->getPost('comentario'),
                'publicable' => $this->request->getPost('publicable'),
            ]
        );

        return redirect()->to($this->ruta($token, 'encuesta'))
            ->with($guardada ? 'ok' : 'error', $guardada ? lang('Portal.graciasEncuesta') : lang('Portal.faltaLaNota'));
    }

    // ── Lo común ────────────────────────────────────────────────────────

    /**
     * Valida el token y devuelve todo lo que necesitan las vistas.
     *
     * @return array{acceso: array, reserva: array, token: string, hotel: object}|null
     */
    private function contexto(string $token): ?array
    {
        $acceso = $this->accesos->porToken($token);
        if ($acceso === null) {
            return null;
        }

        // Consulta propia y con `left join` a `unidades`: `consultaDetallada()`
        // usa un join interno, y una reserva a la que todavía no se le ha
        // asignado cabaña desaparecería. El huésped tiene reserva igual, y
        // enseñarle un enlace muerto sería lo peor que podría pasarle.
        $reserva = $this->reservas
            ->select('reservas.*, huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos,
                      huespedes.email AS huesped_email, unidades.nombre AS unidad_nombre,
                      tipos_unidad.nombre AS tipo_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->join('tipos_unidad', 'tipos_unidad.id = unidades.tipo_id', 'left')
            ->where('reservas.id', (int) $acceso['reserva_id'])
            ->first();

        if ($reserva === null || $reserva['estado'] === 'cancelada') {
            return null;
        }

        // El idioma del enlace manda sobre el del navegador: si reservó en
        // alemán, su portal está en alemán, entre desde donde entre.
        if (! empty($acceso['idioma'])) {
            service('request')->setLocale($acceso['idioma']);
        }

        $this->accesos->anotarUso((int) $acceso['id'], $this->request->getIPAddress());

        return [
            'acceso'  => $acceso,
            'reserva' => $reserva,
            'token'   => $token,
            'hotel'   => config('Hotel'),
        ];
    }

    private function caducado()
    {
        return view('portal/caducado', ['hotel' => config('Hotel')]);
    }

    private function ruta(string $token, string $seccion = ''): string
    {
        return 'estancia/' . $token . ($seccion !== '' ? '/' . $seccion : '');
    }

    private function noches(array $reserva): int
    {
        return (int) (new \DateTime($reserva['fecha_entrada']))
            ->diff(new \DateTime($reserva['fecha_salida']))->days;
    }

    /** Antes de llegar, durante, o ya se fue. Cambia lo que se le enseña. */
    private function fase(array $reserva): string
    {
        $hoy = date('Y-m-d');

        return match (true) {
            $hoy < $reserva['fecha_entrada'] => 'antes',
            $hoy >= $reserva['fecha_salida'] => 'despues',
            default                          => 'durante',
        };
    }

    /**
     * Cuál de las dos encuestas toca.
     *
     * Durante la estancia se pregunta para poder arreglar las cosas mientras el
     * huésped sigue aquí. Después, ya solo para aprender.
     */
    private function momentoEncuesta(array $reserva): string
    {
        return $this->fase($reserva) === 'durante' ? 'estancia' : 'salida';
    }
}
