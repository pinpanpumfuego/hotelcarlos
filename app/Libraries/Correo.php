<?php

namespace App\Libraries;

use App\Models\ConfiguracionModel;
use App\Models\CorreoLogModel;

/**
 * Envío de correos del hotel.
 *
 * Toma la configuración de Administración. Si no está configurada, no falla:
 * deja constancia en el registro para que gerencia sepa qué no se envió.
 */
class Correo
{
    private ConfiguracionModel $config;
    private CorreoLogModel $log;

    public function __construct()
    {
        $this->config = new ConfiguracionModel();
        $this->log    = new CorreoLogModel();
    }

    /** ¿Hay servidor de correo configurado? */
    public function configurado(): bool
    {
        return $this->config->configCorreo() !== null && $this->config->existe('correo_clave');
    }

    /**
     * Envía un correo con la plantilla de marca del hotel.
     *
     * @param array $opciones tipo, para, asunto, vista, datos, reserva_id
     */
    public function enviar(array $opciones): bool
    {
        $hotel   = config('Hotel');
        $para    = trim((string) ($opciones['para'] ?? ''));
        $asunto  = (string) ($opciones['asunto'] ?? 'Mensaje de ' . $hotel->nombre);
        $tipo    = (string) ($opciones['tipo'] ?? 'general');
        $reserva = $opciones['reserva_id'] ?? null;

        if (! filter_var($para, FILTER_VALIDATE_EMAIL)) {
            $this->anotar($tipo, $para, $asunto, 'fallido', 'Dirección de correo no válida.', $reserva);

            return false;
        }

        if (! $this->configurado()) {
            $this->anotar($tipo, $para, $asunto, 'sin_configurar',
                'No hay servidor de correo configurado en Administración.', $reserva);

            return false;
        }

        // El contenido va dentro de una plantilla común con la marca del hotel
        $cuerpo = view('correos/plantilla', [
            'hotel'     => $hotel,
            'asunto'    => $asunto,
            'contenido' => view('correos/' . $opciones['vista'], ($opciones['datos'] ?? []) + ['hotel' => $hotel]),
        ]);

        try {
            $email = service('email');
            $email->initialize($this->config->configCorreo());
            $email->setFrom(
                (string) $this->config->obtener('correo_usuario', $hotel->email),
                (string) $this->config->obtener('correo_remitente_nombre', $hotel->nombre)
            );
            $email->setTo($para);
            $email->setSubject($asunto);
            $email->setMessage($cuerpo);

            if ($email->send(false)) {
                $this->anotar($tipo, $para, $asunto, 'enviado', null, $reserva);

                return true;
            }

            $this->anotar($tipo, $para, $asunto, 'fallido',
                mb_substr(strip_tags((string) $email->printDebugger(['headers'])), 0, 1000), $reserva);
        } catch (\Throwable $e) {
            // Un fallo de correo nunca debe tumbar la operación del hotel
            $this->anotar($tipo, $para, $asunto, 'fallido', mb_substr($e->getMessage(), 0, 1000), $reserva);
            log_message('error', 'Fallo al enviar correo: {msg}', ['msg' => $e->getMessage()]);
        }

        return false;
    }

    /** Confirmación de reserva al huésped, con su enlace de registro. */
    public function confirmacionReserva(array $reserva, ?string $enlaceRegistro = null): bool
    {
        return $this->enviar([
            'tipo'       => 'confirmacion_reserva',
            'para'       => $reserva['email'] ?? '',
            'asunto'     => 'Tu reserva ' . $reserva['codigo'] . ' está confirmada',
            'vista'      => 'confirmacion_reserva',
            'reserva_id' => $reserva['id'] ?? null,
            'datos'      => ['reserva' => $reserva, 'enlaceRegistro' => $enlaceRegistro],
        ]);
    }

    /** Recordatorio con el enlace para completar el registro. */
    public function enlaceRegistro(array $reserva, string $enlace): bool
    {
        return $this->enviar([
            'tipo'       => 'enlace_registro',
            'para'       => $reserva['email'] ?? '',
            'asunto'     => 'Completa tu registro antes de llegar · ' . config('Hotel')->nombre,
            'vista'      => 'enlace_registro',
            'reserva_id' => $reserva['id'] ?? null,
            'datos'      => ['reserva' => $reserva, 'enlace' => $enlace],
        ]);
    }

    /** Aviso interno al hotel cuando entra una reserva por la web. */
    public function avisoReservaWeb(array $reserva): bool
    {
        $destino = (string) $this->config->obtener('hotel_email', config('Hotel')->email);

        return $this->enviar([
            'tipo'       => 'aviso_reserva_web',
            'para'       => $destino,
            'asunto'     => 'Nueva reserva web ' . $reserva['codigo'] . ' · pendiente de confirmar',
            'vista'      => 'aviso_reserva_web',
            'reserva_id' => $reserva['id'] ?? null,
            'datos'      => ['reserva' => $reserva],
        ]);
    }

    private function anotar(string $tipo, string $para, string $asunto, string $estado, ?string $error, $reservaId): void
    {
        $this->log->insert([
            'tipo'         => $tipo,
            'destinatario' => mb_substr($para, 0, 200),
            'asunto'       => mb_substr($asunto, 0, 255),
            'estado'       => $estado,
            'error'        => $error,
            'reserva_id'   => $reservaId ?: null,
            'usuario_id'   => session()->get('usuario_id'),
        ]);
    }
}
