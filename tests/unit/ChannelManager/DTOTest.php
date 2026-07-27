<?php

declare(strict_types=1);

namespace Tests\Unit\ChannelManager;

use App\Libraries\ChannelManager\DTO\AvailabilityDTO;
use App\Libraries\ChannelManager\DTO\RateDTO;
use App\Libraries\ChannelManager\DTO\ReservationDTO;
use App\Libraries\ChannelManager\Exceptions\InvalidDataException;
use CodeIgniter\Test\CIUnitTestCase;
use DateTimeImmutable;

/**
 * Los DTO son la frontera del módulo. La regla que se comprueba aquí es
 * siempre la misma: **no debe poder existir un DTO con datos inválidos.**
 *
 * Si el objeto se construyó, nadie aguas abajo tiene que volver a comprobar
 * nada. Por eso cada caso malo tiene que reventar en el constructor.
 *
 * @internal
 */
final class DTOTest extends CIUnitTestCase
{
    private function fecha(string $f): DateTimeImmutable
    {
        return new DateTimeImmutable($f);
    }

    // ── ReservationDTO ──────────────────────────────────────────────────

    public function testReservaValidaCuentaLasNochesSinContarLaDeSalida(): void
    {
        $dto = $this->reserva('2026-08-10', '2026-08-13');

        $this->assertSame(3, $dto->nights());
    }

    public function testUnaEstanciaDeCeroNochesNoSePuedeConstruir(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->reserva('2026-08-10', '2026-08-10');
    }

    public function testSalidaAnteriorALaEntradaRevienta(): void
    {
        // Es el síntoma clásico de un mapeador que confundió los dos campos.
        // Mejor reventar que bloquear una cabaña del revés.
        $this->expectException(InvalidDataException::class);
        $this->reserva('2026-08-13', '2026-08-10');
    }

    public function testSinIdentificadorExternoRevienta(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->reserva('2026-08-10', '2026-08-12', externalId: '   ');
    }

    public function testMonedaMalFormadaRevienta(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->reserva('2026-08-10', '2026-08-12', currency: 'pesos');
    }

    public function testHuespedesNegativosRevienta(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->reserva('2026-08-10', '2026-08-12', adults: -1);
    }

    public function testLaFilaDeReservaNoLlevaCabana(): void
    {
        // Con las siete cabañas publicadas como un solo alojamiento, la reserva
        // que llega no dice cuál. La asigna el sistema, no el proveedor.
        $fila = $this->reserva('2026-08-10', '2026-08-12')->toReservationRow();

        $this->assertArrayNotHasKey('unidad_id', $fila);
        $this->assertSame('2026-08-10', $fila['fecha_entrada']);
        $this->assertSame('2026-08-12', $fila['fecha_salida']);
    }

    public function testElNombreCompletoSeParteEnNombreYApellidos(): void
    {
        $dto = $this->reserva('2026-08-10', '2026-08-12', guestName: 'Ana María Restrepo Vélez');

        $this->assertSame(['Ana', 'María Restrepo Vélez'], $dto->splitGuestName());
    }

    public function testSinNombreNoSeQuedaVacio(): void
    {
        // Recepción lo corrige en el registro de entrada, pero la reserva no
        // puede entrar con los campos obligatorios en blanco.
        $dto = $this->reserva('2026-08-10', '2026-08-12', guestName: null);

        [$nombre, $apellidos] = $dto->splitGuestName();
        $this->assertNotSame('', $nombre);
        $this->assertNotSame('', $apellidos);
    }

    public function testUnaReservaCanceladaSeReconoceYNoOcupa(): void
    {
        $dto = $this->reserva('2026-08-10', '2026-08-12', status: 'cancelada');

        $this->assertTrue($dto->isCancelled());
        $this->assertFalse($dto->occupies());
    }

    public function testUnaReservaConfirmadaOcupa(): void
    {
        $this->assertTrue($this->reserva('2026-08-10', '2026-08-12', status: 'confirmada')->occupies());
    }

    // ── AvailabilityDTO ─────────────────────────────────────────────────

    public function testDisponibilidadCuentaLasNochesConLasDosPuntasIncluidas(): void
    {
        $dto = new AvailabilityDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-12'), 3);

        $this->assertSame(3, $dto->nights());
    }

    public function testRangoDeDisponibilidadAlRevesRevienta(): void
    {
        $this->expectException(InvalidDataException::class);
        new AvailabilityDTO('7788', $this->fecha('2026-08-12'), $this->fecha('2026-08-10'), 3);
    }

    public function testSeAdmiteCantidadNegativa(): void
    {
        // Beds24 documenta que `numAvail` puede ser negativo cuando un
        // alojamiento está sobrevendido. Ese dato hay que poder leerlo: es
        // justo el que delata el problema.
        $dto = new AvailabilityDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-10'), -2);

        $this->assertSame(-2, $dto->quantity);
    }

    public function testDosRangosContiguosEIgualesSeFunden(): void
    {
        // Sin esto, un año de disponibilidad son 365 objetos y el crédito de la
        // cuenta se agota antes de llegar a marzo.
        $a = new AvailabilityDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-12'), 3);
        $b = new AvailabilityDTO('7788', $this->fecha('2026-08-13'), $this->fecha('2026-08-20'), 3);

        $this->assertTrue($a->canMergeWith($b));

        $unido = $a->mergedWith($b);
        $this->assertSame('2026-08-10', $unido->from->format('Y-m-d'));
        $this->assertSame('2026-08-20', $unido->to->format('Y-m-d'));
    }

    public function testNoSeFundenSiCambiaLaCantidad(): void
    {
        $a = new AvailabilityDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-12'), 3);
        $b = new AvailabilityDTO('7788', $this->fecha('2026-08-13'), $this->fecha('2026-08-20'), 2);

        $this->assertFalse($a->canMergeWith($b));
    }

    public function testNoSeFundenSiHayUnHuecoEntreMedias(): void
    {
        $a = new AvailabilityDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-12'), 3);
        $b = new AvailabilityDTO('7788', $this->fecha('2026-08-15'), $this->fecha('2026-08-20'), 3);

        $this->assertFalse($a->canMergeWith($b));
    }

    public function testNoSeFundenAlojamientosDistintos(): void
    {
        $a = new AvailabilityDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-12'), 3);
        $b = new AvailabilityDTO('9999', $this->fecha('2026-08-13'), $this->fecha('2026-08-20'), 3);

        $this->assertFalse($a->canMergeWith($b));
    }

    // ── RateDTO ─────────────────────────────────────────────────────────

    public function testTarifaValidaDevuelveElHuecoDePrecioComoNumero(): void
    {
        $dto = new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), 380000.0, 'COP', '3');

        $this->assertSame(3, $dto->rateSlot());
    }

    public function testUnPrecioNuloEsValidoPorqueSignificaBorrarlo(): void
    {
        $dto = new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), null);

        $this->assertNull($dto->price);
    }

    public function testPrecioNegativoRevienta(): void
    {
        $this->expectException(InvalidDataException::class);
        new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), -1000.0);
    }

    public function testHuecoDePrecioFueraDeRangoRevienta(): void
    {
        // Beds24 tiene price1..price16 y nada más.
        $this->expectException(InvalidDataException::class);
        new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), 100.0, 'COP', '17');
    }

    public function testHuecoDePrecioNoNumericoRevienta(): void
    {
        $this->expectException(InvalidDataException::class);
        new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), 100.0, 'COP', 'estandar');
    }

    public function testEstanciaMaximaMenorQueLaMinimaRevienta(): void
    {
        // Se podría enviar, y Beds24 lo aceptaría, pero esa cabaña no se podría
        // reservar nunca. Es un error que no da error: mejor pararlo aquí.
        $this->expectException(InvalidDataException::class);
        new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), 100.0, 'COP', '1', 5, 3);
    }

    public function testEstanciaMinimaFueraDelLimiteDocumentadoRevienta(): void
    {
        $this->expectException(InvalidDataException::class);
        new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), 100.0, 'COP', '1', 400);
    }

    public function testSeDistingueUnaTarifaConRestriccionesDeUnaSoloDePrecio(): void
    {
        $soloPrecio = new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), 380000.0);
        $conMinimo  = new RateDTO('7788', $this->fecha('2026-08-10'), $this->fecha('2026-08-20'), 380000.0, 'COP', '1', 2);

        $this->assertFalse($soloPrecio->hasRestrictions());
        $this->assertTrue($conMinimo->hasRestrictions());
    }

    // ── Ayudante ────────────────────────────────────────────────────────

    private function reserva(
        string $entrada,
        string $salida,
        string $externalId = 'B24-1001',
        string $status = 'confirmada',
        int $adults = 2,
        string $currency = 'COP',
        ?string $guestName = 'Juan Pérez',
    ): ReservationDTO {
        return new ReservationDTO(
            channel: 'beds24',
            externalId: $externalId,
            externalPropertyId: '12345',
            externalUnitId: '7788',
            status: $status,
            checkIn: $this->fecha($entrada),
            checkOut: $this->fecha($salida),
            adults: $adults,
            children: 0,
            totalAmount: 760000.0,
            currency: $currency,
            guestName: $guestName,
            guestEmail: 'juan@example.com',
            guestPhone: '3001234567',
            sourceChannel: 'booking',
        );
    }
}
