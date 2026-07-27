<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\Wompi;
use App\Models\ConfiguracionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * La pasarela de pagos.
 *
 * Aquí se comprueban dos cosas que, mal hechas, cuestan dinero de verdad:
 *
 * 1. **La firma de integridad.** Si el orden de la concatenación no es el
 *    exacto, la pasarela rechaza el cobro sin decir por qué, y depurarlo a
 *    ciegas contra un servicio ajeno es una tarde perdida.
 * 2. **La validación del aviso.** Sin ella, cualquiera que conozca la
 *    dirección del webhook podría declararse a sí mismo un pago aprobado.
 *
 * @internal
 */
final class WompiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Wompi $wompi;
    private ConfiguracionModel $config;

    /** Llaves inventadas con el formato correcto: no sirven contra nadie. */
    private const LLAVES = [
        'wompi_ambiente'           => 'pruebas',
        'wompi_activo'             => '1',
        'wompi_llave_publica'      => 'pub_test_PRUEBAxxxxxxxxxxxxxxxxxxxx',
        'wompi_llave_privada'      => 'prv_test_PRUEBAxxxxxxxxxxxxxxxxxxxx',
        'wompi_secreto_integridad' => 'test_integrity_PRUEBAxxxxxxxxxxxxx',
        'wompi_secreto_eventos'    => 'test_events_PRUEBAxxxxxxxxxxxxxxxx',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wompi  = new Wompi();
        $this->config = new ConfiguracionModel();
        $this->config->guardarPares(self::LLAVES);
    }

    protected function tearDown(): void
    {
        $this->config->guardarPares(['wompi_activo' => '0', 'wompi_ambiente' => 'pruebas']);
        parent::tearDown();
    }

    // ── Importes ────────────────────────────────────────────────────────

    public function testElImporteSePasaACentavos(): void
    {
        // La API los exige aunque el peso no use decimales en la práctica:
        // 95.000 COP se manda como 9500000.
        $this->assertSame(9500000, $this->wompi->centavos(95000));
        $this->assertSame(2490000, $this->wompi->centavos(24900));
    }

    public function testLosCentavosSeRedondeanSinPerderNada(): void
    {
        $this->assertSame(150050, $this->wompi->centavos(1500.50));
        $this->assertSame(1, $this->wompi->centavos(0.01));
    }

    // ── La firma de integridad ──────────────────────────────────────────

    public function testLaFirmaSigueLaFormulaOficial(): void
    {
        // SHA256(referencia + centavos + moneda + secreto), en ese orden.
        $esperado = hash(
            'sha256',
            'REF-123' . '2490000' . 'COP' . self::LLAVES['wompi_secreto_integridad']
        );

        $this->assertSame($esperado, $this->wompi->firma('REF-123', 2490000));
    }

    public function testConCaducidadLaFechaVaAntesDelSecreto(): void
    {
        // SHA256(referencia + centavos + moneda + caducidad + secreto).
        $caduca   = '2026-08-21T18:00:00.000Z';
        $esperado = hash(
            'sha256',
            'REF-123' . '2490000' . 'COP' . $caduca . self::LLAVES['wompi_secreto_integridad']
        );

        $this->assertSame($esperado, $this->wompi->firma('REF-123', 2490000, 'COP', $caduca));
    }

    public function testElOrdenImporta(): void
    {
        // Si alguien «arregla» el orden un día, esta prueba lo caza: la
        // pasarela rechazaría el cobro sin explicar por qué.
        $bueno = $this->wompi->firma('REF-123', 2490000);
        $malo  = hash('sha256', '2490000' . 'REF-123' . 'COP' . self::LLAVES['wompi_secreto_integridad']);

        $this->assertNotSame($malo, $bueno);
    }

    public function testCambiarUnPesoCambiaLaFirma(): void
    {
        $this->assertNotSame(
            $this->wompi->firma('REF-123', 2490000),
            $this->wompi->firma('REF-123', 2490100)
        );
    }

    // ── El enlace de cobro ──────────────────────────────────────────────

    public function testElEnlaceLlevaTodoLoObligatorio(): void
    {
        $enlace = $this->wompi->enlaceCheckout('REF-999', 95000, 'https://ejemplo.test/volver');

        $this->assertStringStartsWith('https://checkout.wompi.co/p/?', $enlace);

        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $p);

        $this->assertSame(self::LLAVES['wompi_llave_publica'], $p['public-key']);
        $this->assertSame('COP', $p['currency']);
        $this->assertSame('9500000', $p['amount-in-cents']);
        $this->assertSame('REF-999', $p['reference']);
        $this->assertArrayHasKey('signature:integrity', $p);
        $this->assertSame('https://ejemplo.test/volver', $p['redirect-url']);
    }

    public function testLaFirmaDelEnlaceCuadraConElImporteQueLleva(): void
    {
        $enlace = $this->wompi->enlaceCheckout('REF-777', 24900, 'https://ejemplo.test/volver');
        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $p);

        $this->assertSame(
            $this->wompi->firma('REF-777', (int) $p['amount-in-cents']),
            $p['signature:integrity']
        );
    }

    public function testLosDatosDelClienteViajanSiLosHay(): void
    {
        $enlace = $this->wompi->enlaceCheckout('REF-1', 50000, 'https://ejemplo.test/v', [
            'email'    => 'ana@example.com',
            'nombre'   => 'Ana Ruiz',
            'telefono' => '3001234567',
        ]);
        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $p);

        $this->assertSame('ana@example.com', $p['customer-data:email']);
        $this->assertSame('Ana Ruiz', $p['customer-data:full-name']);
    }

    public function testUnClienteVacioNoMeteParametrosEnBlanco(): void
    {
        $enlace = $this->wompi->enlaceCheckout('REF-2', 50000, 'https://ejemplo.test/v', [
            'email' => '', 'nombre' => '   ',
        ]);
        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $p);

        $this->assertArrayNotHasKey('customer-data:email', $p);
        $this->assertArrayNotHasKey('customer-data:full-name', $p);
    }

    public function testNoSePuedeCobrarCero(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->wompi->enlaceCheckout('REF-3', 0, 'https://ejemplo.test/v');
    }

    public function testSinConfigurarNoSeGeneraNingunEnlace(): void
    {
        $this->config->guardarPares(['wompi_activo' => '0']);

        $this->expectException(\RuntimeException::class);
        (new Wompi())->enlaceCheckout('REF-4', 50000, 'https://ejemplo.test/v');
    }

    // ── Las llaves ──────────────────────────────────────────────────────

    public function testAvisaSiLasLlavesNoSonDelAmbiente(): void
    {
        // Pegar una llave de pruebas en producción es el error más fácil de
        // cometer y el más caro: nadie cobraría nada de verdad.
        $this->config->guardarPares(['wompi_ambiente' => 'produccion']);

        $fallos = (new Wompi())->revisarLlaves();

        $this->assertNotEmpty($fallos);
        $this->assertStringContainsString('pub_prod_', implode(' ', $fallos));
    }

    public function testConLasLlavesCorrectasNoAvisaDeNada(): void
    {
        $this->assertSame([], $this->wompi->revisarLlaves());
    }

    public function testSinLlavesNoEstaActivo(): void
    {
        $this->config->guardarPares(['wompi_llave_publica' => '']);

        $this->assertFalse((new Wompi())->activo());
    }

    // ── Los avisos de la pasarela ───────────────────────────────────────

    private function aviso(array $datos, string $timestamp, array $propiedades, ?string $checksum = null): array
    {
        $cadena = '';
        foreach ($propiedades as $ruta) {
            $valor = $datos;
            foreach (explode('.', $ruta) as $paso) {
                $valor = $valor[$paso] ?? null;
            }
            $cadena .= (string) $valor;
        }

        return [
            'event'     => 'transaction.updated',
            'data'      => $datos,
            'timestamp' => $timestamp,
            'signature' => [
                'properties' => $propiedades,
                'checksum'   => $checksum ?? hash('sha256', $cadena . $timestamp . self::LLAVES['wompi_secreto_eventos']),
            ],
        ];
    }

    public function testUnAvisoBienFirmadoSeAcepta(): void
    {
        $aviso = $this->aviso(
            ['transaction' => ['id' => '01-1234', 'status' => 'APPROVED', 'amount_in_cents' => 9500000]],
            '1690000000',
            ['transaction.id', 'transaction.status', 'transaction.amount_in_cents']
        );

        $this->assertTrue($this->wompi->avisoValido($aviso));
    }

    public function testUnAvisoConFirmaInventadaSeRechaza(): void
    {
        // Sin esta comprobación, cualquiera que conozca la dirección del
        // webhook podría declararse un pago aprobado.
        $aviso = $this->aviso(
            ['transaction' => ['id' => '01-1234', 'status' => 'APPROVED']],
            '1690000000',
            ['transaction.id', 'transaction.status'],
            str_repeat('a', 64)
        );

        $this->assertFalse($this->wompi->avisoValido($aviso));
    }

    public function testCambiarElImporteInvalidaLaFirma(): void
    {
        $aviso = $this->aviso(
            ['transaction' => ['id' => '01-1234', 'status' => 'APPROVED', 'amount_in_cents' => 9500000]],
            '1690000000',
            ['transaction.id', 'transaction.status', 'transaction.amount_in_cents']
        );

        // Alguien intercepta y sube el importe
        $aviso['data']['transaction']['amount_in_cents'] = 100;

        $this->assertFalse($this->wompi->avisoValido($aviso));
    }

    public function testLaFirmaTambienValeDesdeLaCabecera(): void
    {
        $aviso = $this->aviso(
            ['transaction' => ['id' => '01-9', 'status' => 'APPROVED']],
            '1690000001',
            ['transaction.id', 'transaction.status']
        );

        $cabecera = $aviso['signature']['checksum'];
        unset($aviso['signature']['checksum']);

        $this->assertTrue($this->wompi->avisoValido($aviso, $cabecera));
    }

    public function testSinSecretoDeEventosNoSeAceptaNingunAviso(): void
    {
        // Dar por bueno lo que llega sin poder comprobarlo sería peor que
        // rechazarlo.
        $this->config->guardarPares(['wompi_secreto_eventos' => '']);

        $aviso = $this->aviso(
            ['transaction' => ['id' => '01-1', 'status' => 'APPROVED']],
            '1690000000',
            ['transaction.id', 'transaction.status']
        );

        $this->assertFalse((new Wompi())->avisoValido($aviso));
    }

    public function testUnAvisoSinFirmaSeRechaza(): void
    {
        $this->assertFalse($this->wompi->avisoValido([
            'event' => 'transaction.updated',
            'data'  => ['transaction' => ['id' => '01-1']],
        ]));
    }

    public function testUnaPropiedadQueNoExisteNoRompeNiCuela(): void
    {
        $aviso = $this->aviso(
            ['transaction' => ['id' => '01-1']],
            '1690000000',
            ['transaction.id', 'transaction.no_existe']
        );

        // La firma se calculó con el hueco vacío, así que cuadra
        $this->assertTrue($this->wompi->avisoValido($aviso));
    }
}
