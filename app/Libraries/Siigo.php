<?php

namespace App\Libraries;

use App\Models\ConfiguracionModel;

/**
 * Cliente de la API de Siigo, proveedor tecnológico autorizado por la DIAN.
 *
 * Autenticación: POST https://api.siigo.com/auth con {username, access_key}
 * y la cabecera Partner-Id. Devuelve un token JWT válido 24 horas, que aquí
 * se guarda en la configuración para no pedir uno en cada operación.
 *
 * Las credenciales se generan en Siigo Nube:
 * Configuración › Alianzas e integraciones › Credenciales de integración.
 */
class Siigo
{
    private const BASE       = 'https://api.siigo.com';
    private const ESPERA     = 25;     // segundos de espera máxima por petición
    private const MARGEN     = 300;    // renueva el token 5 minutos antes de que caduque

    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->config = new ConfiguracionModel();
    }

    /** ¿Están cargadas las credenciales? */
    public function configurado(): bool
    {
        return $this->config->existe('siigo_usuario')
            && $this->config->existe('siigo_access_key')
            && $this->config->existe('siigo_partner_id');
    }

    /**
     * Token de acceso vigente. Reutiliza el guardado mientras no caduque.
     *
     * @return array{ok: bool, token?: string, error?: string}
     */
    public function token(bool $forzarNuevo = false): array
    {
        if (! $this->configurado()) {
            return ['ok' => false, 'error' => 'Faltan las credenciales de Siigo en Administración.'];
        }

        $guardado = $this->config->obtener('siigo_token');
        $caduca   = (int) $this->config->obtener('siigo_token_caduca', '0');

        if (! $forzarNuevo && $guardado !== null && $caduca > time() + self::MARGEN) {
            return ['ok' => true, 'token' => $guardado];
        }

        $respuesta = $this->peticion('POST', '/auth', [
            'username'   => (string) $this->config->obtener('siigo_usuario'),
            'access_key' => (string) $this->config->obtener('siigo_access_key'),
        ], false);

        if (! $respuesta['ok']) {
            return ['ok' => false, 'error' => $respuesta['error']];
        }

        $datos = $respuesta['datos'];
        if (empty($datos['access_token'])) {
            return ['ok' => false, 'error' => 'Siigo no devolvió un token. Revisa el usuario API y el access key.'];
        }

        $this->config->guardarPares([
            'siigo_token'        => $datos['access_token'],
            'siigo_token_caduca' => (string) (time() + (int) ($datos['expires_in'] ?? 86400)),
        ]);

        return ['ok' => true, 'token' => $datos['access_token']];
    }

    /** Comprueba la conexión trayendo los tipos de documento. */
    public function probar(): array
    {
        $token = $this->token(true);
        if (! $token['ok']) {
            return $token;
        }

        $r = $this->llamar('GET', '/v1/document-types?type=FV');
        if (! $r['ok']) {
            return $r;
        }

        return ['ok' => true, 'datos' => $r['datos']];
    }

    /** Catálogos que el hotel necesita elegir una sola vez. */
    public function tiposDocumento(): array
    {
        return $this->llamar('GET', '/v1/document-types?type=FV');
    }

    public function tiposPago(): array
    {
        return $this->llamar('GET', '/v1/payment-types?document_type=FV');
    }

    public function vendedores(): array
    {
        return $this->llamar('GET', '/v1/users');
    }

    public function impuestos(): array
    {
        return $this->llamar('GET', '/v1/taxes');
    }

    /**
     * Crea una factura de venta.
     *
     * @param array $factura estructura ya montada para la API
     * @return array{ok: bool, datos?: array, error?: string}
     */
    public function crearFactura(array $factura): array
    {
        return $this->llamar('POST', '/v1/invoices', $factura);
    }

    /** Envía la factura por correo al cliente. */
    public function enviarPorCorreo(string $siigoId, string $correo): array
    {
        return $this->llamar('POST', '/v1/invoices/' . $siigoId . '/mail', [
            'guid'    => $siigoId,
            'mail_to' => $correo,
        ]);
    }

    // ─────────────────────────── Interno ───────────────────────────

    /** Petición autenticada: obtiene el token si hace falta. */
    private function llamar(string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        $token = $this->token();
        if (! $token['ok']) {
            return $token;
        }

        $r = $this->peticion($metodo, $ruta, $cuerpo, true, $token['token']);

        // Si el token caducó antes de tiempo, se pide uno nuevo y se reintenta una vez
        if (! $r['ok'] && ($r['codigo'] ?? 0) === 401) {
            $token = $this->token(true);
            if (! $token['ok']) {
                return $token;
            }
            $r = $this->peticion($metodo, $ruta, $cuerpo, true, $token['token']);
        }

        return $r;
    }

    /**
     * Petición HTTP a Siigo.
     *
     * @return array{ok: bool, datos?: array, error?: string, codigo?: int}
     */
    private function peticion(string $metodo, string $ruta, ?array $cuerpo, bool $conToken, ?string $token = null): array
    {
        $cabeceras = [
            'Content-Type: application/json',
            'Partner-Id: ' . (string) $this->config->obtener('siigo_partner_id'),
        ];
        if ($conToken && $token !== null) {
            $cabeceras[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init(self::BASE . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_TIMEOUT        => self::ESPERA,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($cuerpo !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
        }

        $respuesta = curl_exec($ch);
        $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errorRed  = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false) {
            // Sin internet o Siigo caído: no debe tumbar la operación del hotel
            return ['ok' => false, 'codigo' => 0, 'error' => 'No se pudo contactar con Siigo: ' . $errorRed];
        }

        $datos = json_decode($respuesta, true);
        if (! is_array($datos)) {
            return ['ok' => false, 'codigo' => $codigo, 'error' => 'Respuesta inesperada de Siigo (código ' . $codigo . ').'];
        }

        if ($codigo >= 200 && $codigo < 300) {
            return ['ok' => true, 'codigo' => $codigo, 'datos' => $datos];
        }

        return ['ok' => false, 'codigo' => $codigo, 'error' => $this->mensajeError($datos, $codigo), 'datos' => $datos];
    }

    /** Traduce el error de Siigo a algo que recepción pueda entender. */
    private function mensajeError(array $datos, int $codigo): string
    {
        // Siigo devuelve los errores de validación en un array "Errors"
        $errores = $datos['Errors'] ?? $datos['errors'] ?? null;
        if (is_array($errores)) {
            $mensajes = [];
            foreach ($errores as $e) {
                if (is_array($e)) {
                    $mensajes[] = trim(($e['Message'] ?? $e['message'] ?? '') . ' ' . ($e['Params'] ?? ''));
                } elseif (is_string($e)) {
                    $mensajes[] = $e;
                }
            }
            $mensajes = array_filter($mensajes);
            if ($mensajes !== []) {
                return implode(' · ', $mensajes);
            }
        }

        if (! empty($datos['message'])) {
            return (string) $datos['message'];
        }

        if ($codigo === 401) {
            return 'Siigo rechazó las credenciales (401). Revisa el usuario API, el access key y el Partner-Id.';
        }

        return 'Siigo respondió con el código ' . $codigo . '.';
    }
}
