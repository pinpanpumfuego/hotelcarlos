<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConfiguracionModel;
use RuntimeException;

/**
 * Pasarela de pagos Wompi.
 *
 * Todo lo de aquí está sacado de la documentación oficial (docs.wompi.co),
 * consultada el 27-07-2026. **Nada está inventado.**
 *
 * La regla que gobierna esta clase: **el navegador del huésped no es una
 * fuente de verdad**. Vuelve de la pasarela con un `id` en la dirección, pero
 * eso solo dice «mira esta transacción». Si está pagada o no se le pregunta a
 * la API con nuestras credenciales, siempre. Fiarse de lo que traiga el
 * navegador es dejar la caja abierta a cualquiera que sepa editar una URL.
 */
class Wompi
{
    /** Direcciones oficiales de la API. */
    private const API = [
        'pruebas'    => 'https://sandbox.wompi.co/v1',
        'produccion' => 'https://production.wompi.co/v1',
    ];

    /** El checkout web es el mismo en los dos ambientes: manda la llave. */
    private const CHECKOUT = 'https://checkout.wompi.co/p/';

    /** Prefijos que debe tener cada llave, para avisar de una mal pegada. */
    private const PREFIJOS = [
        'pruebas' => [
            'publica'    => 'pub_test_',
            'privada'    => 'prv_test_',
            'integridad' => 'test_integrity_',
            'eventos'    => 'test_events_',
        ],
        'produccion' => [
            'publica'    => 'pub_prod_',
            'privada'    => 'prv_prod_',
            'integridad' => 'prod_integrity_',
            'eventos'    => 'prod_events_',
        ],
    ];

    /** Estados que devuelve la pasarela. */
    public const ESTADOS = [
        'PENDING'  => 'Pendiente',
        'APPROVED' => 'Aprobado',
        'DECLINED' => 'Rechazado',
        'VOIDED'   => 'Anulado',
        'ERROR'    => 'Error',
    ];

    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->config = new ConfiguracionModel();
    }

    // ── Configuración ───────────────────────────────────────────────────

    public function ambiente(): string
    {
        return $this->config->obtener('wompi_ambiente', 'pruebas') === 'produccion'
            ? 'produccion'
            : 'pruebas';
    }

    public function activo(): bool
    {
        return $this->config->obtener('wompi_activo', '0') === '1'
            && $this->llavePublica() !== ''
            && $this->secretoIntegridad() !== '';
    }

    public function llavePublica(): string
    {
        return trim((string) $this->config->obtener('wompi_llave_publica', ''));
    }

    private function llavePrivada(): string
    {
        return trim((string) $this->config->obtener('wompi_llave_privada', ''));
    }

    private function secretoIntegridad(): string
    {
        return trim((string) $this->config->obtener('wompi_secreto_integridad', ''));
    }

    private function secretoEventos(): string
    {
        return trim((string) $this->config->obtener('wompi_secreto_eventos', ''));
    }

    /**
     * Comprueba que las llaves son del ambiente que dicen ser.
     *
     * Pegar una llave de pruebas en producción es el error más fácil de
     * cometer y el más caro: las transacciones se harían contra el simulador y
     * nadie cobraría nada de verdad.
     *
     * @return list<string> los problemas encontrados
     */
    public function revisarLlaves(): array
    {
        $ambiente = $this->ambiente();
        $esperado = self::PREFIJOS[$ambiente];
        $fallos   = [];

        $llaves = [
            'publica'    => ['La llave pública', $this->llavePublica()],
            'privada'    => ['La llave privada', $this->llavePrivada()],
            'integridad' => ['El secreto de integridad', $this->secretoIntegridad()],
            'eventos'    => ['El secreto de eventos', $this->secretoEventos()],
        ];

        foreach ($llaves as $clave => [$nombre, $valor]) {
            if ($valor === '') {
                continue;   // que falte se avisa aparte
            }

            if (! str_starts_with($valor, $esperado[$clave])) {
                $fallos[] = sprintf(
                    '%s no parece de %s: debería empezar por «%s».',
                    $nombre,
                    $ambiente === 'produccion' ? 'producción' : 'pruebas',
                    $esperado[$clave]
                );
            }
        }

        return $fallos;
    }

    // ── Cobro ───────────────────────────────────────────────────────────

    /**
     * Importe en centavos, que es como lo quiere Wompi.
     *
     * El peso colombiano no usa decimales en la práctica, pero la API los
     * exige igual: 95.000 COP se manda como 9500000.
     */
    public function centavos(float $pesos): int
    {
        return (int) round($pesos * 100);
    }

    /**
     * La firma que garantiza que nadie ha tocado el importe por el camino.
     *
     * Fórmula oficial: SHA256(referencia + centavos + moneda + secreto), y con
     * caducidad, SHA256(referencia + centavos + moneda + caducidad + secreto).
     * **El orden es crítico**: cambiarlo produce una firma válida en apariencia
     * que la pasarela rechaza sin decir por qué.
     */
    public function firma(string $referencia, int $centavos, string $moneda = 'COP', ?string $caduca = null): string
    {
        $cadena = $referencia . $centavos . $moneda;

        if ($caduca !== null && $caduca !== '') {
            $cadena .= $caduca;
        }

        return hash('sha256', $cadena . $this->secretoIntegridad());
    }

    /**
     * La dirección del checkout, ya firmada.
     *
     * @param array{email?: string, nombre?: string, telefono?: string, documento?: string} $cliente
     */
    public function enlaceCheckout(
        string $referencia,
        float $pesos,
        string $urlRetorno,
        array $cliente = [],
        ?string $caduca = null,
    ): string {
        if (! $this->activo()) {
            throw new RuntimeException('Los pagos en línea no están configurados.');
        }

        $centavos = $this->centavos($pesos);

        if ($centavos <= 0) {
            throw new RuntimeException('No se puede cobrar un importe de cero.');
        }

        $parametros = [
            'public-key'         => $this->llavePublica(),
            'currency'           => 'COP',
            'amount-in-cents'    => (string) $centavos,
            'reference'          => $referencia,
            'signature:integrity' => $this->firma($referencia, $centavos, 'COP', $caduca),
            'redirect-url'       => $urlRetorno,
        ];

        if ($caduca !== null && $caduca !== '') {
            $parametros['expiration-time'] = $caduca;
        }

        // Datos del cliente: le ahorran teclear y suben la tasa de aprobación
        foreach ([
            'customer-data:email'        => $cliente['email'] ?? null,
            'customer-data:full-name'    => $cliente['nombre'] ?? null,
            'customer-data:phone-number' => $cliente['telefono'] ?? null,
            'customer-data:legal-id'     => $cliente['documento'] ?? null,
        ] as $clave => $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                $parametros[$clave] = trim((string) $valor);
            }
        }

        return self::CHECKOUT . '?' . http_build_query($parametros);
    }

    // ── Consulta ────────────────────────────────────────────────────────

    /**
     * Pregunta a la pasarela cómo quedó una transacción.
     *
     * **Esta es la única fuente de verdad.** Se autentica con la llave pública
     * como Bearer, que es lo que dice la documentación.
     *
     * @return array<string, mixed>|null
     */
    public function transaccion(string $id): ?array
    {
        if ($id === '' || $this->llavePublica() === '') {
            return null;
        }

        try {
            $respuesta = service('curlrequest')->get(
                self::API[$this->ambiente()] . '/transactions/' . rawurlencode($id),
                [
                    'headers' => ['Authorization' => 'Bearer ' . $this->llavePublica()],
                    'timeout' => 20,
                    'http_errors' => false,
                ]
            );

            if ($respuesta->getStatusCode() !== 200) {
                log_message('error', 'Wompi: la consulta de {id} devolvió {c}', [
                    'id' => $id, 'c' => $respuesta->getStatusCode(),
                ]);

                return null;
            }

            $cuerpo = json_decode((string) $respuesta->getBody(), true);

            return is_array($cuerpo) && isset($cuerpo['data']) ? $cuerpo['data'] : null;
        } catch (\Throwable $e) {
            log_message('error', 'Wompi: no se pudo consultar {id}: {m}', [
                'id' => $id, 'm' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** ¿Responde la pasarela con estas llaves? Para el botón «probar». */
    public function probar(): array
    {
        $fallos = $this->revisarLlaves();

        if ($this->llavePublica() === '') {
            return ['ok' => false, 'mensaje' => 'Falta la llave pública.'];
        }
        if ($this->secretoIntegridad() === '') {
            return ['ok' => false, 'mensaje' => 'Falta el secreto de integridad: sin él no se puede firmar nada.'];
        }
        if ($fallos !== []) {
            return ['ok' => false, 'mensaje' => implode(' ', $fallos)];
        }

        // Se pide una transacción que no existe: un 404 significa que la
        // pasarela contestó y entendió la llave, que es lo que se quiere saber.
        try {
            $respuesta = service('curlrequest')->get(
                self::API[$this->ambiente()] . '/transactions/no-existe-comprobacion',
                [
                    'headers' => ['Authorization' => 'Bearer ' . $this->llavePublica()],
                    'timeout' => 15,
                    'http_errors' => false,
                ]
            );

            $codigo = $respuesta->getStatusCode();

            if ($codigo === 401 || $codigo === 403) {
                return ['ok' => false, 'mensaje' => 'La pasarela rechazó la llave pública.'];
            }

            return [
                'ok'      => true,
                'mensaje' => 'La pasarela responde en el ambiente de '
                    . ($this->ambiente() === 'produccion' ? 'producción' : 'pruebas') . '.',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo conectar: ' . $e->getMessage()];
        }
    }

    // ── Avisos de la pasarela ───────────────────────────────────────────

    /**
     * Comprueba que un aviso viene de verdad de Wompi.
     *
     * La documentación lo define así: se concatenan **los valores de las
     * propiedades que el propio aviso lista** en `signature.properties`
     * —sacadas de `data` por su ruta con puntos—, después la marca de tiempo, y
     * al final el secreto de eventos. De todo eso, SHA256.
     *
     * Sin esta comprobación, cualquiera que conozca la dirección del webhook
     * podría declararse a sí mismo un pago aprobado.
     */
    public function avisoValido(array $aviso, ?string $cabecera = null): bool
    {
        $secreto = $this->secretoEventos();

        if ($secreto === '') {
            // Sin secreto configurado no se puede comprobar nada, y dar por
            // bueno lo que llega sería peor que rechazarlo.
            return false;
        }

        $propiedades = $aviso['signature']['properties'] ?? null;
        $checksum    = $cabecera ?: ($aviso['signature']['checksum'] ?? null);
        $timestamp   = $aviso['timestamp'] ?? null;

        if (! is_array($propiedades) || $checksum === null || $timestamp === null) {
            return false;
        }

        $cadena = '';
        foreach ($propiedades as $ruta) {
            $cadena .= (string) ($this->valorPorRuta($aviso['data'] ?? [], (string) $ruta) ?? '');
        }

        $calculado = hash('sha256', $cadena . $timestamp . $secreto);

        // Comparación en tiempo constante: comparar con === filtra información
        // sobre cuántos caracteres coincidían.
        return hash_equals($calculado, (string) $checksum);
    }

    /** Saca un valor de un array anidado con una ruta tipo «transaction.id». */
    private function valorPorRuta(array $datos, string $ruta): mixed
    {
        $actual = $datos;

        foreach (explode('.', $ruta) as $paso) {
            if (! is_array($actual) || ! array_key_exists($paso, $actual)) {
                return null;
            }
            $actual = $actual[$paso];
        }

        return $actual;
    }
}
