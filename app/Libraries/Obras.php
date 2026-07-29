<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConfiguracionModel;

/**
 * El cartel de «todavía no abrimos» de la web pública.
 *
 * **Qué es y qué no es.** Es una cortina para que quien llegue de rebote no vea
 * un hotel a medio configurar, y una llave para que el equipo pueda enseñarlo y
 * probarlo. No es seguridad: detrás no hay datos de nadie, solo la web
 * comercial. Por eso una clave corta es proporcionada aquí y no lo sería en el
 * panel, donde hay dinero y documentos de huéspedes.
 *
 * La clave se guarda cifrada igual que las demás, y el pase que se deja en el
 * navegador **se deriva de ese cifrado**: cambiar la clave invalida sola todos
 * los pases repartidos, sin tener que ir persiguiéndolos.
 */
class Obras
{
    public const COOKIE = 'hc_obras';

    /** Un mes: lo bastante para enseñar el proyecto sin repetir la clave cada día. */
    public const DIAS_PASE = 30;

    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->config = new ConfiguracionModel();
    }

    public function activo(): bool
    {
        return $this->config->obtener('obras_activo', '0') === '1';
    }

    /** ¿Este navegador ya dio la clave? */
    public function tienePase(): bool
    {
        $pase = (string) (service('request')->getCookie(self::COOKIE) ?? '');

        return $pase !== '' && hash_equals($this->paseEsperado(), $pase);
    }

    /**
     * @return array{ok: bool, motivo: ?string}
     */
    public function entrar(string $clave): array
    {
        $guardada = (string) $this->config->obtener('obras_clave', '');

        if ($guardada === '') {
            return ['ok' => false, 'motivo' => 'Todavía no hay clave puesta. Habla con el hotel.'];
        }

        // Freno flojo, pero freno: sin él, una clave de cuatro cifras se
        // adivina en un rato desde cualquier script.
        if (service('throttler')->check('obras-' . md5(service('request')->getIPAddress()), 10, MINUTE * 5) === false) {
            return ['ok' => false, 'motivo' => 'Demasiados intentos. Espera unos minutos.'];
        }

        if (! password_verify(trim($clave), $guardada)) {
            return ['ok' => false, 'motivo' => 'Esa clave no es.'];
        }

        service('response')->setCookie([
            'name'     => self::COOKIE,
            'value'    => $this->paseEsperado(),
            'expire'   => self::DIAS_PASE * DAY,
            'httponly' => true,
            'secure'   => service('request')->isSecure(),
            'samesite' => 'Lax',
        ]);

        return ['ok' => true, 'motivo' => null];
    }

    public function guardarClave(string $clave): void
    {
        $this->config->guardarPares(['obras_clave' => password_hash(trim($clave), PASSWORD_DEFAULT)]);
    }

    /**
     * @return array{titulo: string, texto: string, fecha: string}
     */
    public function textos(): array
    {
        return [
            'titulo' => (string) $this->config->obtener('obras_titulo', 'Estamos terminando'),
            'texto'  => (string) $this->config->obtener('obras_texto', ''),
            'fecha'  => (string) $this->config->obtener('obras_fecha', ''),
        ];
    }

    /**
     * El valor del pase.
     *
     * Sale del cifrado de la clave, no de la clave: quien se lleve la cookie no
     * se lleva nada que sirva para nada más, y el día que se cambie la clave
     * todos los pases dejan de valer sin tocar nada.
     */
    private function paseEsperado(): string
    {
        return hash_hmac('sha256', 'pase-obras', (string) $this->config->obtener('obras_clave', ''));
    }
}
