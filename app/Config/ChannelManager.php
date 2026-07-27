<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Ajustes del módulo de channel manager.
 *
 * Todo lo que aquí depende del proveedor se lee de `.env`, nunca del código:
 * el día que entre SiteMinder o Cloudbeds se cambia una variable y se añade
 * una carpeta en Libraries/ChannelManager/, sin tocar nada más.
 *
 * El mapeo de estados vive aquí y no dentro del adaptador a propósito: es una
 * decisión de negocio («una consulta de Airbnb, ¿ocupa la cabaña o no?») y
 * gerencia tiene que poder verla sin leer código.
 */
class ChannelManager extends BaseConfig
{
    /**
     * Interruptor maestro.
     *
     * Apagado de fábrica. El módulo se puede instalar entero —tablas, panel,
     * comandos— sin que toque una sola reserva. Se enciende cuando las cabañas
     * están mapeadas y se ha probado la conexión.
     */
    public bool $enabled = false;

    /** Proveedor activo. Debe existir en `self::PROVIDERS`. */
    public string $provider = 'beds24';

    /**
     * Proveedores implementados.
     *
     * @var array<string, class-string>
     */
    public const PROVIDERS = [
        'beds24' => \App\Libraries\ChannelManager\Beds24\Beds24Adapter::class,
    ];

    // ── Estados ─────────────────────────────────────────────────────────

    /**
     * Estado externo → estado interno.
     *
     * Los estados internos son los del ENUM de `reservas`, más dos valores
     * que **no** son estados de reserva y se tratan aparte:
     *
     *   - `ignorar`  : no genera nada. Una consulta no es una reserva.
     *   - `bloqueo`  : va a la tabla `bloqueos`, no a `reservas`. Beds24 llama
     *                  `black` a un bloqueo del propietario: no tiene huésped,
     *                  y meterlo en `reservas` crearía reservas fantasma.
     *
     * @var array<string, string>
     */
    public array $estadosEntrantes = [
        'new'       => 'pendiente',
        'request'   => 'pendiente',
        'confirmed' => 'confirmada',
        'cancelled' => 'cancelada',
        'inquiry'   => 'ignorar',
        'black'     => 'bloqueo',
    ];

    /**
     * Estado interno → estado externo, para lo que empujamos nosotros.
     *
     * `checkin` y `checkout` viajan como `confirmed`: para el channel manager
     * una estancia en curso o terminada sigue ocupando la cabaña, y no tiene
     * un estado propio para eso.
     *
     * @var array<string, string>
     */
    public array $estadosSalientes = [
        'pendiente'  => 'new',
        'confirmada' => 'confirmed',
        'checkin'    => 'confirmed',
        'checkout'   => 'confirmed',
        'cancelada'  => 'cancelled',
        'no_show'    => 'cancelled',
    ];

    /**
     * Estados internos que ocupan la cabaña.
     *
     * Es la misma lista que `ReservaModel::ESTADOS_ACTIVOS`; se repite aquí
     * porque el módulo no debe depender del núcleo para una decisión suya.
     *
     * @var list<string>
     */
    public array $estadosQueOcupan = ['pendiente', 'confirmada', 'checkin'];

    // ── Ritmo y ventanas ────────────────────────────────────────────────

    /** Cuántos días hacia delante se sincroniza la disponibilidad. */
    public int $diasSincronizacion = 365;

    /**
     * Cuánto retrocede la ventana de `modifiedFrom` en cada barrido.
     *
     * Se pide más de lo necesario a propósito. La zona horaria que usa Beds24
     * en `modifiedTime` no está documentada (TODO_BEDS24_DOCUMENTATION), y un
     * desfase de horas significaría perder reservas en silencio. Reprocesar es
     * inofensivo: el flujo entrante es idempotente.
     */
    public int $minutosSolape = 15;

    /** Reintentos de una operación fallida antes de rendirse en ese ciclo. */
    public int $maxReintentos = 3;

    /** A partir de cuántos intentos fallidos se avisa en el panel. */
    public int $intentosParaAlerta = 5;

    /** Días que se conserva el payload original antes de purgarlo. */
    public int $diasPayload = 365;

    // ── Red ─────────────────────────────────────────────────────────────

    public int $timeout = 30;
    public int $connectTimeout = 10;

    /**
     * Crédito por debajo del cual solo se hace lo urgente.
     *
     * Beds24 limita por créditos en ventana móvil de 5 minutos, no por número
     * de peticiones. Traer reservas es urgente; empujar el precio de dentro de
     * ocho meses puede esperar al siguiente ciclo.
     */
    public int $creditoMinimo = 50;

    // ── Campos que nunca se escriben en un log ──────────────────────────

    /**
     * Cualquier clave que **contenga** una de estas cadenas se sustituye por
     * `***` antes de guardar un payload.
     *
     * `card` y `cvv` están porque Booking manda a veces tarjetas virtuales en
     * el cuerpo de la reserva. Eso no se guarda jamás.
     *
     * @var list<string>
     */
    public const CAMPOS_SENSIBLES = [
        'token', 'refreshtoken', 'secret', 'password', 'apikey', 'authorization',
        'card', 'cardnumber', 'cvv', 'cvc', 'ccnumber', 'securitycode',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->enabled            = filter_var(env('CHANNEL_MANAGER_ENABLED', false), FILTER_VALIDATE_BOOL);
        $this->provider           = (string) env('CHANNEL_MANAGER_PROVIDER', 'beds24');
        $this->diasSincronizacion = (int) env('BEDS24_SYNC_DAYS', 365);
        $this->minutosSolape      = (int) env('BEDS24_LOOKBACK_MINUTES', 15);
        $this->maxReintentos      = (int) env('BEDS24_MAX_RETRIES', 3);
        $this->intentosParaAlerta = (int) env('BEDS24_FAILURE_ALERT_ATTEMPTS', 5);
        $this->diasPayload        = (int) env('BEDS24_RAW_PAYLOAD_TTL_DAYS', 365);
        $this->timeout            = (int) env('BEDS24_TIMEOUT', 30);
        $this->connectTimeout     = (int) env('BEDS24_CONNECT_TIMEOUT', 10);
        $this->creditoMinimo      = (int) env('BEDS24_CREDIT_FLOOR', 50);
    }

    /** Estado interno para un estado externo, o null si no está mapeado. */
    public function estadoInterno(string $externo): ?string
    {
        return $this->estadosEntrantes[strtolower($externo)] ?? null;
    }
}
