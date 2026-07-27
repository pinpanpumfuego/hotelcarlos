<?php

namespace App\Libraries;

use App\Models\ConfiguracionModel;
use App\Models\EmpleadoModel;

/**
 * Quién está delante del TPV en este momento.
 *
 * El TPV es una pantalla compartida: el usuario del sistema es siempre el
 * mismo (el que dejó la sesión abierta por la mañana) y encima va una capa
 * de camareros que se identifican con su PIN de fichaje o con su tarjeta.
 *
 * La identidad vive en la sesión del servidor, no en el navegador: si solo
 * fuera una pantalla de bloqueo en el cliente, bastaría con llamar a la API
 * directamente para saltársela.
 */
class SesionTpv
{
    private EmpleadoModel $empleados;
    private ConfiguracionModel $config;

    /** Acciones que un camarero no puede hacer sin que las autorice un encargado. */
    public const REQUIEREN_ENCARGADO = ['anular', 'descuento', 'cupon', 'cortesia'];

    public function __construct()
    {
        $this->empleados = new EmpleadoModel();
        $this->config    = new ConfiguracionModel();
    }

    /** ¿Está encendido el modo compartido? */
    public function compartido(): bool
    {
        return $this->config->obtener('tpv_compartido', '0') === '1';
    }

    /** Segundos de inactividad antes de bloquear. */
    public function segundosBloqueo(): int
    {
        return max(15, (int) $this->config->obtener('tpv_bloqueo_seg', '60'));
    }

    /** Descuento máximo (en %) que un camarero puede hacer por su cuenta. */
    public function descuentoLibre(): float
    {
        return (float) $this->config->obtener('tpv_descuento_libre', '10');
    }

    /** El camarero que tiene la pantalla ahora, o null si está bloqueada. */
    public function actual(): ?array
    {
        if (! $this->compartido()) {
            return null;
        }

        $id = session()->get('tpv_empleado_id');
        if ($id === null) {
            return null;
        }

        $empleado = $this->empleados->find((int) $id);

        if ($empleado === null || (int) $empleado['activo'] !== 1
            || ! in_array($empleado['rol_tpv'], ['camarero', 'encargado'], true)) {
            $this->bloquear();

            return null;
        }

        return $empleado;
    }

    /**
     * Identifica por PIN o por tarjeta y abre la pantalla.
     *
     * @return array{ok: bool, mensaje: string, empleado: array|null}
     */
    public function abrir(string $pin = '', string $tarjeta = ''): array
    {
        $empleado = trim($tarjeta) !== ''
            ? $this->empleados->porTarjeta($tarjeta)
            : $this->empleados->porPin($pin);

        if ($empleado === null) {
            log_message('warning', 'Identificación fallida en el TPV desde {ip}', [
                'ip' => service('request')->getIPAddress(),
            ]);

            return [
                'ok'       => false,
                'empleado' => null,
                'mensaje'  => trim($tarjeta) !== ''
                    ? 'Esa tarjeta no está registrada.'
                    : 'Ese PIN no corresponde a nadie.',
            ];
        }

        if (! in_array($empleado['rol_tpv'], ['camarero', 'encargado'], true)) {
            return [
                'ok'       => false,
                'empleado' => null,
                'mensaje'  => $empleado['nombre'] . ' no tiene acceso al TPV. Habla con gerencia.',
            ];
        }

        session()->set('tpv_empleado_id', (int) $empleado['id']);

        return ['ok' => true, 'empleado' => $empleado, 'mensaje' => 'Hola, ' . $empleado['nombre'] . '.'];
    }

    /** Cierra la pantalla. Se llama también desde el cliente al bloquearse solo. */
    public function bloquear(): void
    {
        session()->remove('tpv_empleado_id');
    }

    /**
     * Comprueba si quien está delante puede hacer una acción, y si no,
     * si la autoriza un encargado con su PIN.
     *
     * @return array{ok: bool, mensaje: string, empleado: array|null, autorizo: array|null}
     */
    public function autorizar(string $accion, string $pinEncargado = ''): array
    {
        // Con el modo apagado manda el usuario del sistema, como siempre
        if (! $this->compartido()) {
            return ['ok' => true, 'mensaje' => '', 'empleado' => null, 'autorizo' => null];
        }

        $empleado = $this->actual();

        if ($empleado === null) {
            return [
                'ok'       => false,
                'mensaje'  => 'La pantalla se bloqueó. Identifícate otra vez.',
                'empleado' => null,
                'autorizo' => null,
            ];
        }

        // Un encargado no necesita que nadie le dé permiso
        if (! in_array($accion, self::REQUIEREN_ENCARGADO, true) || $empleado['rol_tpv'] === 'encargado') {
            return ['ok' => true, 'mensaje' => '', 'empleado' => $empleado, 'autorizo' => null];
        }

        if (trim($pinEncargado) === '') {
            return [
                'ok'       => false,
                'mensaje'  => 'Esto lo tiene que autorizar un encargado con su PIN.',
                'empleado' => $empleado,
                'autorizo' => null,
            ];
        }

        $encargado = $this->empleados->porPin($pinEncargado);

        if ($encargado === null || $encargado['rol_tpv'] !== 'encargado') {
            log_message('warning', 'Intento de autorización fallido en el TPV para {a}', ['a' => $accion]);

            return [
                'ok'       => false,
                'mensaje'  => 'Ese PIN no es de un encargado.',
                'empleado' => $empleado,
                'autorizo' => null,
            ];
        }

        log_message('info', 'Acción {a} autorizada por el encargado {e} para {c}', [
            'a' => $accion, 'e' => $encargado['id'], 'c' => $empleado['id'],
        ]);

        return ['ok' => true, 'mensaje' => 'Autorizado por ' . $encargado['nombre'] . '.', 'empleado' => $empleado, 'autorizo' => $encargado];
    }

    /** Datos del camarero para pintarlos en la pantalla. */
    public function paraPantalla(): ?array
    {
        $empleado = $this->actual();

        if ($empleado === null) {
            return null;
        }

        return [
            'id'      => (int) $empleado['id'],
            'nombre'  => $empleado['nombre'],
            'inicial' => mb_strtoupper(mb_substr($empleado['nombre'], 0, 1)),
            'rol'     => $empleado['rol_tpv'],
        ];
    }
}
