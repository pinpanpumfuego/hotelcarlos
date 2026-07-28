<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\DispositivoModel;
use App\Models\EmpleadoModel;
use App\Models\UsuarioModel;

/**
 * Entrar al panel con correo y PIN de 4 dígitos.
 *
 * **Lo que sostiene los cuatro dígitos.** Diez mil combinaciones son pocas. No
 * se defiende alargando el PIN —que acabaría escrito en un papel bajo el
 * teclado— sino poniendo tres cosas a la vez:
 *
 * 1. **El equipo tiene que estar reconocido.** Se marca al entrar con la
 *    contraseña completa, y la marca vale un año. Desde un navegador
 *    desconocido el PIN no vale ni siendo correcto, así que probar
 *    combinaciones desde fuera no lleva a ningún sitio.
 * 2. **Freno por persona, no por IP.** Quien prueba PINes cambia de red antes
 *    que de víctima. A los cinco fallos se cierra diez minutos; a la tercera
 *    tanda, el PIN se apaga hasta que esa persona entre con su contraseña.
 * 3. **La sesión abierta con PIN no puede hacer lo sensible.** Eso lo aplica el
 *    filtro de permisos: aquí solo se deja dicho que la sesión vino por PIN.
 *
 * **Se identifica con el correo, no solo con el PIN.** El terminal de fichaje
 * identifica por PIN a secas, y por eso obliga a que no se repitan entre
 * empleados; eso es una limitación incómoda con ocho personas e imposible con
 * treinta. Con el correo delante, cada PIN es asunto de su dueño.
 */
class AccesoPin
{
    public const COOKIE = 'hc_equipo';

    /** Fallos seguidos antes de cerrar la puerta un rato. */
    public const INTENTOS = 5;

    /** Minutos que dura ese cierre. */
    public const ESPERA_MIN = 10;

    /** Minutos que dura la contraseña confirmada antes de volver a pedirla. */
    public const ELEVACION_MIN = 15;

    /**
     * ¿Puede este navegador usar el PIN?
     *
     * No pregunta de quién es el equipo: un equipo reconocido lo está para
     * quien se haya identificado en él, y la identidad la pone el correo más
     * el PIN. Atarlo además al usuario obligaría a marcar el mismo mostrador
     * una vez por cada persona del turno, que es cómo se acaba desactivando
     * la medida entera.
     */
    public function equipoReconocido(): bool
    {
        return $this->dispositivo() !== null;
    }

    /** @return array<string, mixed>|null */
    public function dispositivo(): ?array
    {
        $token = (string) (service('request')->getCookie(self::COOKIE) ?? '');

        return (new DispositivoModel())->porToken($token);
    }

    /**
     * Deja marcado este equipo. Se llama al entrar con la contraseña completa.
     */
    public function recordarEquipo(int $usuarioId): void
    {
        $dispositivos = new DispositivoModel();
        $existente    = $this->dispositivo();

        // Ya marcado: se alarga y se deja constancia del uso, sin repartir un
        // token nuevo por cada visita.
        if ($existente !== null) {
            $dispositivos->update($existente['id'], [
                'usuario_id' => $usuarioId,
                'ultimo_uso' => date('Y-m-d H:i:s'),
                'expira'     => date('Y-m-d', strtotime('+' . DispositivoModel::DIAS_VALIDEZ . ' days')),
            ]);

            return;
        }

        $token = bin2hex(random_bytes(32));

        $dispositivos->insert([
            'usuario_id' => $usuarioId,
            'token_hash' => hash('sha256', $token),
            'nombre'     => $this->nombreDelEquipo(),
            'ip_alta'    => service('request')->getIPAddress(),
            'ultimo_uso' => date('Y-m-d H:i:s'),
            'expira'     => date('Y-m-d', strtotime('+' . DispositivoModel::DIAS_VALIDEZ . ' days')),
        ]);

        service('response')->setCookie([
            'name'     => self::COOKIE,
            'value'    => $token,
            'expire'   => DispositivoModel::DIAS_VALIDEZ * DAY,
            'httponly' => true,
            'secure'   => service('request')->isSecure(),
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Comprueba correo y PIN.
     *
     * @return array{ok: bool, motivo: ?string, usuario: ?array}
     */
    public function comprobar(string $email, string $pin): array
    {
        $no = static fn (string $motivo): array => ['ok' => false, 'motivo' => $motivo, 'usuario' => null];

        if (! $this->equipoReconocido()) {
            return $no(
                'Este equipo todavía no está reconocido. Entra una vez con tu contraseña y '
                . 'a partir de la siguiente te bastará el PIN.'
            );
        }

        $email = trim(mb_strtolower($email));
        $pin   = trim($pin);

        if (! preg_match('/^\d{' . EmpleadoModel::LONGITUD_PIN . '}$/', $pin)) {
            return $no('El PIN son ' . EmpleadoModel::LONGITUD_PIN . ' dígitos.');
        }

        $usuario = (new UsuarioModel())->buscarPorEmail($email);

        // A partir de aquí, un solo mensaje para todo lo que falle: decir «ese
        // correo no existe» le regala a quien prueba la mitad del trabajo.
        $generico = 'Correo o PIN incorrectos.';

        if ($usuario === null || (int) $usuario['activo'] !== 1) {
            // Se gasta el intento igualmente: si no, se podría averiguar qué
            // correos existen midiendo cuáles cuentan para el freno.
            $this->gastarIntento($email);

            return $no($generico);
        }

        if (service('throttler')->check($this->llave($email), self::INTENTOS, MINUTE * self::ESPERA_MIN) === false) {
            return $no('Demasiados intentos. Espera ' . self::ESPERA_MIN . ' minutos o entra con tu contraseña.');
        }

        $empleados = new EmpleadoModel();
        $empleado  = $empleados->where('usuario_id', $usuario['id'])->first();

        if ($empleado === null || (int) ($empleado['pin_panel'] ?? 0) !== 1 || $empleado['pin_hash'] === null) {
            return $no('Tu cuenta no tiene la entrada con PIN activada. Entra con tu contraseña.');
        }

        if ($empleado['pin_bloqueado'] !== null) {
            return $no('Tu PIN está bloqueado por intentos fallidos. Entra con tu contraseña para reactivarlo.');
        }

        if (! password_verify($pin, (string) $empleado['pin_hash'])) {
            // Tres tandas de fallos ya no es alguien que se equivoca: se apaga
            // el PIN y solo lo revive quien sepa la contraseña.
            if (service('throttler')->check('pin-tandas-' . md5($email), 3, HOUR) === false) {
                $empleados->update($empleado['id'], ['pin_bloqueado' => date('Y-m-d H:i:s')]);

                return $no('Demasiados fallos. Tu PIN queda bloqueado: entra con tu contraseña para reactivarlo.');
            }

            return $no($generico);
        }

        (new DispositivoModel())->update($this->dispositivo()['id'], ['ultimo_uso' => date('Y-m-d H:i:s')]);

        return ['ok' => true, 'motivo' => null, 'usuario' => $usuario];
    }

    /** Al entrar con contraseña se le devuelve el PIN a quien lo tenía apagado. */
    public function desbloquear(int $usuarioId): void
    {
        $empleados = new EmpleadoModel();
        $empleado  = $empleados->where('usuario_id', $usuarioId)->first();

        if ($empleado !== null && $empleado['pin_bloqueado'] !== null) {
            $empleados->update($empleado['id'], ['pin_bloqueado' => null]);
        }
    }

    // ── La sesión abierta con PIN ───────────────────────────────────────

    public function sesionEsDePin(): bool
    {
        return session()->get('sesion_pin') === true;
    }

    /** ¿Confirmó la contraseña hace poco? */
    public function elevada(): bool
    {
        return (int) session()->get('elevada_hasta') > time();
    }

    public function elevar(): void
    {
        session()->set('elevada_hasta', time() + MINUTE * self::ELEVACION_MIN);
    }

    /**
     * ¿Hay que pedirle la contraseña para esto?
     *
     * Solo para lo que el catálogo marca como sensible: lo que mueve dinero,
     * toca datos personales o cambia la configuración. El resto del trabajo del
     * día se hace con el PIN y ya está, que es el sentido de todo esto.
     */
    public function pideContrasena(array $permisos): bool
    {
        if (! $this->sesionEsDePin() || $this->elevada()) {
            return false;
        }

        foreach ($permisos as $permiso) {
            if (\App\Libraries\Permisos\Catalogo::esSensible((string) $permiso)) {
                return true;
            }
        }

        return false;
    }

    // ── Lo de dentro ────────────────────────────────────────────────────

    private function gastarIntento(string $email): void
    {
        service('throttler')->check($this->llave($email), self::INTENTOS, MINUTE * self::ESPERA_MIN);
    }

    private function llave(string $email): string
    {
        return 'pin-panel-' . md5($email);
    }

    private function nombreDelEquipo(): string
    {
        $agente = (string) service('request')->getUserAgent();

        foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad',
                  'Windows' => 'Windows', 'Macintosh' => 'Mac', 'Linux' => 'Linux'] as $aguja => $nombre) {
            if (str_contains($agente, $aguja)) {
                return $nombre . ' · ' . date('d/m/Y');
            }
        }

        return 'Equipo · ' . date('d/m/Y');
    }
}
