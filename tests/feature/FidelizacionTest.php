<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Crm;
use App\Libraries\Referidos;
use App\Models\ConfiguracionModel;
use App\Models\CuponModel;
use App\Models\HuespedModel;
use App\Models\NivelModel;
use App\Models\ReferidoModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Niveles y referidos.
 *
 * Lo que se defiende:
 *
 * 1. **El nivel se calcula, no se guarda.** Cambiar un umbral tiene que
 *    recolocar a todo el mundo solo. Si estuviera guardado, media base de datos
 *    quedaría mintiendo y nadie sabría cuál mitad.
 * 2. **El premio del referido se entrega al SALIR.** Si se diera al reservar,
 *    una cancelación dejaría dos cupones regalados por una estancia que no
 *    ocurrió, y basta con que alguien lo descubra una vez.
 * 3. **Nadie se recomienda a sí mismo.** Es la primera idea que se le ocurre a
 *    cualquiera al ver un programa de referidos.
 *
 * @internal
 */
final class FidelizacionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private NivelModel $niveles;
    private Referidos $referidos;
    private Crm $crm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->niveles   = new NivelModel();
        $this->referidos = new Referidos();
        $this->crm       = new Crm();
        $this->limpiar();

        (new ConfiguracionModel())->guardarPares([
            'referido_activo'      => '1',
            'referido_premio_pct'  => '10',
            'referido_premio_dias' => '365',
        ]);
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        (new ConfiguracionModel())->guardarPares(['referido_activo' => '0']);
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $ids = array_column(
            $db->table('huespedes')->select('id')->like('num_documento', 'PRUEBA-FID', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            foreach ($db->table('reservas')->select('id')->where('huesped_id', $id)->get()->getResultArray() as $r) {
                $db->table('folio_movimientos')->where('reserva_id', $r['id'])->delete();
                $db->table('referidos')->where('reserva_id', $r['id'])->delete();
            }

            $db->table('referidos')->where('referidor_id', $id)->delete();
            $db->table('reservas')->where('huesped_id', $id)->delete();
        }

        $db->table('huespedes')->like('num_documento', 'PRUEBA-FID', 'after')->delete();
        $db->table('cupones')->like('descripcion', 'Referido:', 'after')->delete();
    }

    private function crearHuesped(): int
    {
        static $n = 0;
        $n++;

        (new HuespedModel())->insert([
            'nombre'         => 'Fid',
            'apellidos'      => 'Prueba ' . $n,
            'tipo_documento' => 'CC',
            'num_documento'  => 'PRUEBA-FID-' . $n . '-' . random_int(1000, 9999),
            'email'          => 'fid' . $n . '@example.test',
        ]);

        return (int) db_connect()->insertID();
    }

    /** Le crea N estancias cumplidas, con su gasto en el folio. */
    private function conEstancias(int $huespedId, int $cuantas, float $gastoPorEstancia = 500000): void
    {
        $db = db_connect();

        for ($i = 0; $i < $cuantas; $i++) {
            $db->table('reservas')->insert([
                'codigo' => 'FD' . random_int(10000, 99999), 'huesped_id' => $huespedId, 'unidad_id' => null,
                'fecha_entrada' => date('Y-m-d', strtotime('-' . (30 * ($i + 1)) . ' days')),
                'fecha_salida'  => date('Y-m-d', strtotime('-' . (30 * ($i + 1) - 2) . ' days')),
                'adultos' => 2, 'estado' => 'checkout', 'total' => $gastoPorEstancia,
            ]);
            $reservaId = (int) $db->insertID();

            $db->table('folio_movimientos')->insert([
                'reserva_id' => $reservaId, 'tipo' => 'cargo',
                'concepto' => 'Alojamiento', 'valor' => $gastoPorEstancia,
            ]);
        }
    }

    // ── Los niveles se calculan ─────────────────────────────────────────

    public function testUnHuespedNuevoEstaEnElNivelDeEntrada(): void
    {
        $id    = $this->crearHuesped();
        $nivel = $this->niveles->de($this->crm->valor($id));

        $this->assertNotNull($nivel, 'Todo el mundo tiene que caer en algún nivel.');
        $this->assertSame(0, (int) $nivel['orden']);
    }

    public function testConMasEstanciasSeSubeDeNivel(): void
    {
        $id = $this->crearHuesped();
        $this->conEstancias($id, 5, 100000);

        $nivel = $this->niveles->de($this->crm->valor($id));

        $this->assertGreaterThanOrEqual(2, (int) $nivel['orden']);
    }

    public function testBastaConCumplirUnoDeLosDosCriterios(): void
    {
        // Quien vino dos veces gastando mucho y quien vino seis gastando poco
        // son los dos buenos clientes, por razones distintas.
        $porGasto = $this->crearHuesped();
        $this->conEstancias($porGasto, 1, 6000000);

        $nivel = $this->niveles->de($this->crm->valor($porGasto));

        $this->assertGreaterThanOrEqual(
            2,
            (int) $nivel['orden'],
            'Con una sola estancia pero mucho gasto debería subir igual.'
        );
    }

    public function testCambiarElUmbralRecolocaSolo(): void
    {
        // Es la razón de calcularlo en vez de guardarlo.
        $id = $this->crearHuesped();
        $this->conEstancias($id, 3, 100000);

        $habitual = $this->niveles->where('clave', 'habitual')->first();
        $original = (int) $habitual['estancias_min'];

        $antes = $this->niveles->de($this->crm->valor($id));

        $this->niveles->update($habitual['id'], ['estancias_min' => 2]);
        $despues = $this->niveles->de($this->crm->valor($id));

        $this->niveles->update($habitual['id'], ['estancias_min' => $original]);

        $this->assertNotSame(
            $antes['clave'],
            $despues['clave'],
            'Bajar el umbral debería haberle cambiado el nivel sin tocar nada más.'
        );
    }

    public function testUnNivelApagadoNoSeAsigna(): void
    {
        $id = $this->crearHuesped();
        $this->conEstancias($id, 12, 100000);

        $top      = $this->niveles->where('clave', 'anfitrion')->first();
        $conTop   = $this->niveles->de($this->crm->valor($id));

        $this->niveles->update($top['id'], ['activo' => 0]);
        $sinTop = $this->niveles->de($this->crm->valor($id));
        $this->niveles->update($top['id'], ['activo' => 1]);

        $this->assertSame('anfitrion', $conTop['clave']);
        $this->assertNotSame('anfitrion', $sinTop['clave']);
    }

    public function testDiceCuantoFaltaParaElSiguiente(): void
    {
        // «Le falta una estancia» es la frase que hace que alguien reserve.
        $id = $this->crearHuesped();
        $this->conEstancias($id, 1, 100000);

        $siguiente = $this->niveles->siguiente($this->crm->valor($id));

        $this->assertNotNull($siguiente);
        $this->assertSame(1, $siguiente['faltan_estancias']);
    }

    public function testEnElNivelMasAltoNoHaySiguiente(): void
    {
        $id = $this->crearHuesped();
        $this->conEstancias($id, 20, 2000000);

        $this->assertNull($this->niveles->siguiente($this->crm->valor($id)));
    }

    public function testLosBeneficiosSeLeenLineaALinea(): void
    {
        $nivel = $this->niveles->where('clave', 'amigo')->first();

        $this->assertGreaterThan(1, count($this->niveles->beneficiosDe($nivel)));
    }

    // ── Referidos ───────────────────────────────────────────────────────

    public function testElCodigoSeGeneraSoloLaPrimeraVez(): void
    {
        $id = $this->crearHuesped();

        $primero = $this->referidos->codigoDe($id);
        $segundo = $this->referidos->codigoDe($id);

        $this->assertNotNull($primero);
        $this->assertSame($primero, $segundo, 'El código no puede cambiar: ya se lo dio a alguien.');
    }

    public function testElCodigoNoLlevaLetrasQueSeConfundan(): void
    {
        // Se dicta por teléfono: la I con el 1, la O con el 0, la S con el 5.
        $codigo = $this->referidos->codigoDe($this->crearHuesped());

        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRTUVWXYZ2346789]{6}$/', (string) $codigo);
    }

    public function testConElProgramaApagadoNoSeApuntaNada(): void
    {
        (new ConfiguracionModel())->guardarPares(['referido_activo' => '0']);

        $referidor = $this->crearHuesped();
        $codigo    = (new Referidos())->codigoDe($referidor);
        $nuevo     = $this->crearHuesped();
        $reservaId = $this->reservaDe($nuevo);

        $this->assertNull((new Referidos())->apuntar($reservaId, (string) $codigo, $nuevo));
    }

    public function testNadieSeRecomiendaASiMismo(): void
    {
        $id     = $this->crearHuesped();
        $codigo = $this->referidos->codigoDe($id);

        $this->assertNull($this->referidos->apuntar($this->reservaDe($id), (string) $codigo, $id));
    }

    public function testUnCodigoInventadoNoRompeNada(): void
    {
        $nuevo = $this->crearHuesped();

        $this->assertNull($this->referidos->apuntar($this->reservaDe($nuevo), 'NOEXISTE', $nuevo));
    }

    public function testUnaReservaSoloPuedeVenirDeUnaRecomendacion(): void
    {
        $uno = $this->crearHuesped();
        $dos = $this->crearHuesped();
        $c1  = (string) $this->referidos->codigoDe($uno);
        $c2  = (string) $this->referidos->codigoDe($dos);

        $nuevo     = $this->crearHuesped();
        $reservaId = $this->reservaDe($nuevo);

        $this->assertNotNull($this->referidos->apuntar($reservaId, $c1, $nuevo));
        $this->assertNull($this->referidos->apuntar($reservaId, $c2, $nuevo));
    }

    public function testAlApuntarNoSeRepartNadaTodavia(): void
    {
        // El premio se entrega al salir. Si se diera al reservar, una
        // cancelación dejaría dos cupones por una estancia que no ocurrió.
        $referidor = $this->crearHuesped();
        $codigo    = (string) $this->referidos->codigoDe($referidor);
        $nuevo     = $this->crearHuesped();

        $this->referidos->apuntar($this->reservaDe($nuevo), $codigo, $nuevo);

        $this->assertSame(0, (new CuponModel())->like('descripcion', 'Referido:', 'after')->countAllResults());
        $this->assertSame(0, (new ReferidoModel())->cuantosTrajo($referidor));
    }

    public function testAlCumplirseSeEmitenLosDosCupones(): void
    {
        $referidor = $this->crearHuesped();
        $codigo    = (string) $this->referidos->codigoDe($referidor);
        $nuevo     = $this->crearHuesped();
        $reservaId = $this->reservaDe($nuevo);

        $this->referidos->apuntar($reservaId, $codigo, $nuevo);
        $premio = $this->referidos->cumplir($reservaId);

        $this->assertNotNull($premio);
        $this->assertNotNull($premio['referidor']);
        $this->assertNotNull($premio['referido']);
        $this->assertSame(2, (new CuponModel())->like('descripcion', 'Referido:', 'after')->countAllResults());
        $this->assertSame(1, (new ReferidoModel())->cuantosTrajo($referidor));
    }

    public function testLosCuponesDeReferidoSonDeUnSoloUso(): void
    {
        // Un cupón de referido que circule por WhatsApp deja de ser un premio
        // y pasa a ser la tarifa.
        $referidor = $this->crearHuesped();
        $codigo    = (string) $this->referidos->codigoDe($referidor);
        $nuevo     = $this->crearHuesped();
        $reservaId = $this->reservaDe($nuevo);

        $this->referidos->apuntar($reservaId, $codigo, $nuevo);
        $premio = $this->referidos->cumplir($reservaId);

        $cupon = (new CuponModel())->where('codigo', $premio['referidor'])->first();

        $this->assertSame(1, (int) $cupon['limite_usos']);
        $this->assertSame(1, (int) $cupon['limite_por_huesped']);
        $this->assertSame(0, (int) $cupon['en_tpv'], 'Un premio de alojamiento no se gasta en el bar.');
    }

    public function testCumplirDosVecesNoDuplicaLosCupones(): void
    {
        $referidor = $this->crearHuesped();
        $codigo    = (string) $this->referidos->codigoDe($referidor);
        $nuevo     = $this->crearHuesped();
        $reservaId = $this->reservaDe($nuevo);

        $this->referidos->apuntar($reservaId, $codigo, $nuevo);
        $this->referidos->cumplir($reservaId);
        $segunda = $this->referidos->cumplir($reservaId);

        $this->assertNull($segunda);
        $this->assertSame(2, (new CuponModel())->like('descripcion', 'Referido:', 'after')->countAllResults());
    }

    public function testSiLaReservaSeCaeNoSeRepartNada(): void
    {
        $referidor = $this->crearHuesped();
        $codigo    = (string) $this->referidos->codigoDe($referidor);
        $nuevo     = $this->crearHuesped();
        $reservaId = $this->reservaDe($nuevo);

        $this->referidos->apuntar($reservaId, $codigo, $nuevo);
        $this->referidos->anular($reservaId, 'PRUEBA-Se canceló');

        $this->assertNull($this->referidos->cumplir($reservaId));
        $this->assertSame(0, (new CuponModel())->like('descripcion', 'Referido:', 'after')->countAllResults());
    }

    public function testUnPerfilFusionadoNoTieneCodigo(): void
    {
        $id = $this->crearHuesped();
        (new HuespedModel())->update($id, ['estado' => 'fusionado']);

        $this->assertNull($this->referidos->codigoDe($id));
    }

    // ── La pantalla ─────────────────────────────────────────────────────

    public function testLaPantallaDeNivelesSeAbre(): void
    {
        $rol = (new \App\Models\RolModel())->porClave('gerencia');

        $r = $this->withSession([
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ])->get('niveles');

        $r->assertOK();
        $r->assertSee('Programa de referidos');
    }

    public function testRecepcionNoTocaLosNiveles(): void
    {
        // Bajar un umbral asciende de golpe a media base de datos, y con el
        // nivel van los descuentos: es dinero.
        $rol = (new \App\Models\RolModel())->porClave('recepcion');

        $r = $this->withSession([
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ])->get('niveles');

        $this->assertTrue($r->isRedirect());
    }

    private function reservaDe(int $huespedId): int
    {
        db_connect()->table('reservas')->insert([
            'codigo' => 'FD' . random_int(10000, 99999), 'huesped_id' => $huespedId, 'unidad_id' => null,
            'fecha_entrada' => date('Y-m-d', strtotime('+5 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('+7 days')),
            'adultos' => 2, 'estado' => 'confirmada', 'total' => 600000,
        ]);

        return (int) db_connect()->insertID();
    }
}
