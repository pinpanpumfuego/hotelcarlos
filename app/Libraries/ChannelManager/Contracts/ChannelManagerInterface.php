<?php

declare(strict_types=1);

namespace App\Libraries\ChannelManager\Contracts;

use App\Libraries\ChannelManager\DTO\AvailabilityDTO;
use App\Libraries\ChannelManager\DTO\RateDTO;
use App\Libraries\ChannelManager\DTO\ReservationDTO;
use App\Libraries\ChannelManager\Exceptions\AuthenticationException;
use App\Libraries\ChannelManager\Exceptions\ChannelManagerException;
use App\Libraries\ChannelManager\Exceptions\MappingNotFoundException;
use App\Libraries\ChannelManager\Exceptions\TransportException;
use DateTimeInterface;

/**
 * Lo que el PMS le puede pedir a un channel manager, sea cual sea.
 *
 * **Esta es la frontera del módulo.** Todo lo que hay por encima —servicios,
 * comandos, panel— habla solo con esta interfaz y con los DTO. Nada de lo que
 * hay por encima puede mencionar a Beds24; el día que entre SiteMinder se
 * escribe otra implementación y se cambia una variable en `.env`.
 *
 * Reglas que toda implementación debe cumplir:
 *
 * - **Devuelve DTO, nunca arrays crudos del proveedor.**
 * - **No abre transacciones ni escribe en las tablas del PMS.** De eso se
 *   encargan los servicios: un adaptador que además tocara la base de datos
 *   sería imposible de probar y de sustituir.
 * - **Lanza excepciones del módulo**, nunca `\Exception` a secas, para que
 *   quien llama pueda distinguir lo que merece un reintento de lo que no.
 */
interface ChannelManagerInterface
{
    /**
     * Reservas **modificadas** dentro del rango.
     *
     * El rango es de fecha de *modificación*, no de llegada. Es la única forma
     * de enterarse de que una reserva del mes pasado se ha cancelado hoy:
     * filtrando por llegada, esa cancelación no aparecería nunca.
     *
     * Quien llama debe pedir una ventana **más ancha** que el intervalo real
     * entre ejecuciones. Reprocesar es inofensivo —el flujo entrante es
     * idempotente— y perder una reserva no lo es.
     *
     * @return list<ReservationDTO>
     *
     * @throws TransportException      Fallo de red o del proveedor
     * @throws AuthenticationException Credenciales rechazadas o caducadas
     */
    public function getReservations(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * Una reserva concreta, o `null` si el proveedor no la conoce.
     *
     * Es lo que se usa tras recibir un aviso: el webhook solo dice «mira la
     * reserva X», y el estado bueno se pide aquí con nuestras credenciales.
     *
     * @throws TransportException
     * @throws AuthenticationException
     */
    public function getReservation(string $externalReservationId): ?ReservationDTO;

    /**
     * Envía disponibilidad.
     *
     * @param list<AvailabilityDTO>|AvailabilityDTO $availability Se admite un
     *        lote porque el proveedor cobra por petición: mandar 365 días de
     *        uno en uno agota el crédito de la cuenta sin llegar a marzo.
     *
     * @return bool `true` si el proveedor aceptó **todo** el lote
     *
     * @throws TransportException
     * @throws AuthenticationException
     * @throws ChannelManagerException El proveedor rechazó el contenido
     */
    public function pushAvailability(array|AvailabilityDTO $availability): bool;

    /**
     * Envía precios y restricciones.
     *
     * @param list<RateDTO>|RateDTO $rate
     *
     * @throws TransportException
     * @throws AuthenticationException
     * @throws ChannelManagerException
     */
    public function pushRate(array|RateDTO $rate): bool;

    /**
     * Crea o actualiza una reserva en el proveedor.
     *
     * Sirve para que una reserva hecha en la web propia ocupe también en las
     * OTAs. Si el DTO trae `externalId`, se actualiza; si no, se crea.
     *
     * @return string Id de la reserva en el proveedor
     *
     * @throws MappingNotFoundException La cabaña no está mapeada
     * @throws TransportException
     * @throws AuthenticationException
     */
    public function createOrUpdateReservation(ReservationDTO $reservation): string;

    /**
     * Cancela una reserva en el proveedor.
     *
     * **Cancelar es cambiar el estado, nunca borrar.** Una reserva cancelada
     * tiene que seguir existiendo: es la prueba de lo que pasó.
     *
     * @throws TransportException
     * @throws AuthenticationException
     */
    public function cancelReservation(string $externalReservationId): bool;

    /**
     * Disponibilidad tal y como la ve el proveedor.
     *
     * No está en la lista original de métodos, pero **la conciliación no se
     * puede hacer sin esto**: comparar lo nuestro con lo suyo exige poder leer
     * lo suyo. Sin este método, el informe de diferencias sería un informe de
     * lo que creemos haber enviado, que no es lo mismo.
     *
     * @return list<AvailabilityDTO>
     *
     * @throws TransportException
     * @throws AuthenticationException
     */
    public function fetchAvailability(DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * ¿Responde el proveedor y son válidas las credenciales?
     *
     * No lanza excepción: devuelve `false`. Es lo que hay detrás del botón
     * «Probar conexión» del panel, y ahí un error tiene que verse como un
     * mensaje, no como una pantalla blanca.
     */
    public function testConnection(): bool;

    /** Identificador interno del proveedor: 'beds24'. */
    public function name(): string;
}
