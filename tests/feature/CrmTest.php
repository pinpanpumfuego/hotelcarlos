<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Crm;
use App\Models\ConsentimientoModel;
use App\Models\HuespedModel;
use App\Models\HuespedPreferenciaModel;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use RuntimeException;

/**
 * Perfil único, consentimientos y datos sensibles.
 *
 * Este es el módulo donde un fallo no se paga con una pantalla fea: se paga con
 * una sanción de la Superintendencia o con alguien en el hospital. Lo que se
 * defiende aquí, por orden:
 *
 * 1. **El silencio no es un sí.** De quien no consta nada, no se sabe nada, y
 *    a esa gente no se le escribe.
 * 2. **Una retirada no borra el «sí» de antes.** La tabla es un libro. Lo que
 *    valdría ante una queja es poder demostrar qué autorizó esa persona y
 *    cuándo lo quitó.
 * 3. **Las alergias no las ve cualquiera**, y quien no puede verlas tampoco
 *    puede escribirlas.
 * 4. **Fusionar no pierde nada**: ni reservas, ni consentimientos, ni el
 *    rastro de que aquel perfil existió.
 *
 * @internal
 */
final class CrmTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Crm $crm;
    private HuespedModel $huespedes;
    private ConsentimientoModel $consentimientos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crm             = new Crm();
        $this->huespedes       = new HuespedModel();
        $this->consentimientos = new ConsentimientoModel();
        $this->limpiar();
    }

    protected function tearDown(): void
    {
        $this->limpiar();
        parent::tearDown();
    }

    private function limpiar(): void
    {
        $db = db_connect();

        $ids = array_column(
            $db->table('huespedes')->select('id')->like('num_documento', 'PRUEBA-CRM', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            $reservas = array_column(
                $db->table('reservas')->select('id')->where('huesped_id', $id)->get()->getResultArray(),
                'id'
            );

            foreach ($reservas as $r) {
                $db->table('folio_movimientos')->where('reserva_id', $r)->delete();
                $db->table('encuestas')->where('reserva_id', $r)->delete();
                $db->table('solicitudes')->where('reserva_id', $r)->delete();
            }

            $db->table('reservas')->where('huesped_id', $id)->delete();
            $db->table('consentimientos')->where('huesped_id', $id)->delete();
            $db->table('huesped_preferencias')->where('huesped_id', $id)->delete();
        }

        $db->table('huespedes')->like('num_documento', 'PRUEBA-CRM', 'after')->delete();
    }

    private function crearHuesped(array $extra = []): int
    {
        static $n = 0;
        $n++;

        $this->huespedes->insert(array_merge([
            'nombre'         => 'Prueba',
            'apellidos'      => 'CRM ' . $n,
            'tipo_documento' => 'CC',
            'num_documento'  => 'PRUEBA-CRM-' . $n . '-' . random_int(1000, 9999),
        ], $extra));

        return (int) $this->huespedes->getInsertID();
    }

    private function como(string $perfil, int $usuarioId = 1): array
    {
        $rol = (new RolModel())->porClave($perfil);
        $this->assertNotNull($rol, "Falta el perfil «{$perfil}».");

        return [
            'usuario_id'     => $usuarioId,
            'usuario_nombre' => 'Prueba',
            'usuario_rol'    => 'gerencia',
            'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    // ── El silencio no es un sí ─────────────────────────────────────────

    public function testDeQuienNoConstaNadaNoSePuedeEscribir(): void
    {
        // Es exactamente la gente a la que no se le puede escribir, y el fallo
        // más fácil de cometer: dar por bueno que quien no dijo que no, dijo
        // que sí.
        $id = $this->crearHuesped();

        $this->assertFalse($this->crm->puedeContactar($id, 'marketing'));
        $this->assertFalse($this->crm->puedeContactar($id, 'encuestas'));
    }

    public function testLoOperativoNoNecesitaPermiso(): void
    {
        // Mandarle la confirmación de su reserva no es marketing, y pedirle
        // permiso para eso sería absurdo.
        $id = $this->crearHuesped();

        $this->assertTrue($this->crm->puedeContactar($id, 'operativo'));
    }

    public function testAutorizarYRetirarCambiaLaRespuesta(): void
    {
        $id = $this->crearHuesped();

        $this->crm->otorgar($id, 'marketing', 'email', ['origen' => 'portal']);
        $this->assertTrue($this->crm->puedeContactar($id, 'marketing'));

        $this->crm->retirar($id, 'marketing', 'email', ['origen' => 'baja_email']);
        $this->assertFalse($this->crm->puedeContactar($id, 'marketing'));
    }

    public function testAutorizarElCorreoNoAutorizaElWhatsapp(): void
    {
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');

        $this->assertTrue($this->crm->puedeContactar($id, 'marketing', 'email'));
        $this->assertFalse($this->crm->puedeContactar($id, 'marketing', 'whatsapp'));
    }

    public function testAutorizarParaMarketingNoAutorizaParaEncuestas(): void
    {
        // «Acepta que le escribamos» no es un consentimiento válido: hay que
        // decir para qué.
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');

        $this->assertFalse($this->crm->puedeContactar($id, 'encuestas', 'email'));
    }

    public function testCualquieraValeParaTodosLosCanales(): void
    {
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'cualquiera');

        $this->assertTrue($this->crm->puedeContactar($id, 'marketing', 'email'));
        $this->assertTrue($this->crm->puedeContactar($id, 'marketing', 'whatsapp'));
    }

    public function testUnaRetiradaPosteriorTumbaElPermisoGeneral(): void
    {
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'cualquiera');
        $this->crm->retirar($id, 'marketing', 'whatsapp');

        $this->assertTrue($this->crm->puedeContactar($id, 'marketing', 'email'));
        $this->assertFalse($this->crm->puedeContactar($id, 'marketing', 'whatsapp'));
    }

    public function testUnaFinalidadInventadaRebota(): void
    {
        $this->expectException(RuntimeException::class);
        $this->crm->otorgar($this->crearHuesped(), 'lo_que_sea', 'email');
    }

    // ── El libro no se corrige ──────────────────────────────────────────

    public function testRetirarNoBorraElSiDeAntes(): void
    {
        // Es la prueba que valdría ante una queja: qué autorizó esa persona el
        // día que lo autorizó. Un UPDATE la borraría.
        $id = $this->crearHuesped();

        $this->crm->otorgar($id, 'marketing', 'email', ['origen' => 'registro', 'ip' => '1.2.3.4']);
        $this->crm->retirar($id, 'marketing', 'email', ['origen' => 'baja_email']);

        $historial = $this->consentimientos->historial($id);

        $this->assertCount(2, $historial, 'Tienen que quedar las dos filas.');
        $this->assertSame(0, (int) $historial[0]['otorgado'], 'La más nueva es la retirada.');
        $this->assertSame(1, (int) $historial[1]['otorgado'], 'La vieja sigue ahí.');
        $this->assertSame('1.2.3.4', $historial[1]['ip'], 'Y con su prueba.');
    }

    public function testVolverAAutorizarDespuesDeUnaBajaFunciona(): void
    {
        $id = $this->crearHuesped();

        $this->crm->otorgar($id, 'marketing', 'email');
        $this->crm->retirar($id, 'marketing', 'email');
        $this->crm->otorgar($id, 'marketing', 'email');

        $this->assertTrue($this->crm->puedeContactar($id, 'marketing'));
        $this->assertCount(3, $this->consentimientos->historial($id));
    }

    // ── Campañas y ficha dicen lo mismo ─────────────────────────────────

    public function testLaListaDeCampanaCoincideConLaFicha(): void
    {
        // Si difieren, una campaña acaba incluyendo a alguien que la ficha dice
        // que se dio de baja. Es la clase de fallo que se descubre por una queja.
        $si   = $this->crearHuesped();
        $no   = $this->crearHuesped();
        $baja = $this->crearHuesped();
        $mudo = $this->crearHuesped();

        $this->crm->otorgar($si, 'marketing', 'email');
        $this->crm->otorgar($no, 'encuestas', 'email');
        $this->crm->otorgar($baja, 'marketing', 'email');
        $this->crm->retirar($baja, 'marketing', 'email');

        $lista = $this->consentimientos->huespedesQuePermiten('marketing', 'email');

        foreach ([$si, $no, $baja, $mudo] as $huespedId) {
            $this->assertSame(
                $this->crm->puedeContactar($huespedId, 'marketing', 'email'),
                in_array($huespedId, $lista, true),
                'La campaña y la ficha no dicen lo mismo del huésped ' . $huespedId
            );
        }
    }

    public function testUnPerfilFusionadoNoEntraEnLasCampanas(): void
    {
        $ganador  = $this->crearHuesped(['email' => 'crm.fusion@example.test']);
        $perdedor = $this->crearHuesped(['email' => 'crm.fusion@example.test']);

        $this->crm->otorgar($perdedor, 'marketing', 'email');
        $this->crm->fusionar($ganador, $perdedor);

        $lista = $this->consentimientos->huespedesQuePermiten('marketing', 'email');

        $this->assertNotContains($perdedor, $lista, 'El perfil viejo no puede recibir nada.');
        $this->assertContains($ganador, $lista, 'Pero su autorización sí vale para el que se queda.');
    }

    // ── Datos sensibles ─────────────────────────────────────────────────

    public function testLasAlergiasNoSeLeEnsenanAQuienNoPuedeVerlas(): void
    {
        $id           = $this->crearHuesped();
        $preferencias = new HuespedPreferenciaModel();

        $preferencias->insert([
            'huesped_id' => $id, 'tipo' => 'alergia', 'valor' => 'Maní', 'critica' => 1,
        ]);
        $preferencias->insert([
            'huesped_id' => $id, 'tipo' => 'interes', 'valor' => 'Aves',
        ]);

        $conPermiso = array_column($preferencias->deHuesped($id, true), 'valor');
        $sinPermiso = array_column($preferencias->deHuesped($id, false), 'valor');

        $this->assertContains('Maní', $conPermiso);
        $this->assertNotContains('Maní', $sinPermiso, 'Un dato de salud se le coló a quien no puede verlo.');
        $this->assertContains('Aves', $sinPermiso, 'Lo que no es sensible sí se ve.');
    }

    public function testHousekeepingNoVeLaFichaDelHuesped(): void
    {
        // Ve qué cabaña se libera, no quién duerme en ella.
        $id = $this->crearHuesped();

        $r = $this->withSession($this->como('housekeeping'))->get('huespedes/ver/' . $id);

        $this->assertTrue($r->isRedirect());
    }

    public function testQuienNoPuedeVerAlergiasTampocoPuedeApuntarlas(): void
    {
        // Escribir un dato de salud que después no se puede consultar deja el
        // dato ahí sin que nadie pueda revisarlo. O las dos cosas o ninguna.
        $id = $this->crearHuesped();

        // Caja puede editar huéspedes pero no tiene `huespedes.sensibles`
        $this->withSession($this->como('caja'))->post('huespedes/preferencia/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'tipo'           => 'alergia',
            'valor'          => 'PRUEBA-Mariscos',
        ]);

        $this->assertSame(
            0,
            (new HuespedPreferenciaModel())->where('huesped_id', $id)->countAllResults(),
            'Se apuntó una alergia sin permiso para verla.'
        );
    }

    public function testRecepcionSiPuedeApuntarUnaAlergia(): void
    {
        $id = $this->crearHuesped();

        $this->withSession($this->como('recepcion'))->post('huespedes/preferencia/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'tipo'           => 'alergia',
            'valor'          => 'PRUEBA-Mariscos',
        ]);

        $pref = (new HuespedPreferenciaModel())->where('huesped_id', $id)->first();

        $this->assertNotNull($pref);
        // Una alergia siempre es crítica: no es una preferencia, es algo que
        // puede acabar en un hospital.
        $this->assertSame(1, (int) $pref['critica']);
    }

    // ── Fusión de duplicados ────────────────────────────────────────────

    public function testFusionarMueveLasReservasYNoBorraNada(): void
    {
        $ganador  = $this->crearHuesped();
        $perdedor = $this->crearHuesped();
        $db       = db_connect();

        $db->table('reservas')->insert([
            'codigo' => 'CRM' . random_int(10000, 99999), 'huesped_id' => $perdedor, 'unidad_id' => null,
            'fecha_entrada' => date('Y-m-d', strtotime('-10 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('-8 days')),
            'adultos' => 2, 'estado' => 'checkout', 'total' => 400000,
        ]);

        $r = $this->crm->fusionar($ganador, $perdedor);

        $this->assertSame(1, $r['reservas']);
        $this->assertSame(1, $db->table('reservas')->where('huesped_id', $ganador)->countAllResults());

        $viejo = $this->huespedes->find($perdedor);
        $this->assertNotNull($viejo, 'El perfil viejo NO se borra: dejaría huecos sin explicar.');
        $this->assertSame('fusionado', $viejo['estado']);
        $this->assertSame($ganador, (int) $viejo['fusionado_en']);
    }

    public function testFusionarCompletaLosHuecosDelQueSeQueda(): void
    {
        // Uno traía el teléfono y el otro el correo: la fusión los junta.
        $ganador  = $this->crearHuesped(['telefono' => '3001112233']);
        $perdedor = $this->crearHuesped(['email' => 'crm.hueco@example.test', 'ciudad' => 'Cali']);

        $this->crm->fusionar($ganador, $perdedor);
        $g = $this->huespedes->find($ganador);

        $this->assertSame('crm.hueco@example.test', $g['email']);
        $this->assertSame('Cali', $g['ciudad']);
        $this->assertSame('3001112233', $g['telefono'], 'Lo que ya tenía no se pisa.');
    }

    public function testLosConsentimientosSobrevivenALaFusion(): void
    {
        $ganador  = $this->crearHuesped();
        $perdedor = $this->crearHuesped();

        $this->crm->otorgar($perdedor, 'marketing', 'email', ['origen' => 'registro', 'ip' => '9.9.9.9']);

        $this->crm->fusionar($ganador, $perdedor);

        $historial = $this->consentimientos->historial($ganador);

        $this->assertCount(1, $historial);
        $this->assertSame('9.9.9.9', $historial[0]['ip'], 'La prueba tiene que viajar entera.');
        $this->assertTrue($this->crm->puedeContactar($ganador, 'marketing'));
    }

    public function testNoSePuedeFusionarUnPerfilConsigoMismo(): void
    {
        $id = $this->crearHuesped();

        $this->expectException(RuntimeException::class);
        $this->crm->fusionar($id, $id);
    }

    public function testNoSePuedeFusionarDosVecesElMismoPerfil(): void
    {
        $a = $this->crearHuesped();
        $b = $this->crearHuesped();
        $c = $this->crearHuesped();

        $this->crm->fusionar($a, $b);

        $this->expectException(RuntimeException::class);
        $this->crm->fusionar($c, $b);
    }

    public function testLaFichaDeUnPerfilFusionadoLlevaALaBuena(): void
    {
        $ganador  = $this->crearHuesped();
        $perdedor = $this->crearHuesped();
        $this->crm->fusionar($ganador, $perdedor);

        $r = $this->withSession($this->como('recepcion'))->get('huespedes/ver/' . $perdedor);

        $this->assertTrue($r->isRedirect());
        $this->assertStringContainsString('huespedes/ver/' . $ganador, (string) $r->getRedirectUrl());
    }

    public function testLosDuplicadosSeDetectanPorCorreoYTelefono(): void
    {
        $uno = $this->crearHuesped(['email' => 'crm.dup@example.test', 'telefono' => '+57 300 444 5566']);
        $dos = $this->crearHuesped(['telefono' => '3004445566']);

        $encontrados = array_column($this->crm->posiblesDuplicados($this->huespedes->find($uno)), 'id');

        // El mismo móvil se escribe de cinco maneras distintas
        $this->assertContains($dos, array_map('intval', $encontrados));
    }

    public function testUnPerfilSinCorreoNiTelefonoNoArrastraATodaLaBase(): void
    {
        // Buscar por nombre metería a todos los «Juan Pérez» del mundo.
        $id = $this->crearHuesped();
        $this->huespedes->update($id, ['email' => null, 'telefono' => null]);

        $huesped = $this->huespedes->find($id);
        $huesped['num_documento'] = '';

        $this->assertSame([], $this->crm->posiblesDuplicados($huesped));
    }

    // ── El valor ────────────────────────────────────────────────────────

    public function testElValorCuentaSoloLoQueSeConsumio(): void
    {
        $id = $this->crearHuesped();
        $db = db_connect();

        foreach ([['checkout', 400000], ['cancelada', 900000], ['pendiente', 300000]] as [$estado, $total]) {
            $db->table('reservas')->insert([
                'codigo' => 'CRM' . random_int(10000, 99999), 'huesped_id' => $id, 'unidad_id' => null,
                'fecha_entrada' => date('Y-m-d', strtotime('-10 days')),
                'fecha_salida'  => date('Y-m-d', strtotime('-8 days')),
                'adultos' => 2, 'estado' => $estado, 'total' => $total,
            ]);
        }

        $valor = $this->crm->valor($id);

        $this->assertSame(1, $valor['estancias'], 'Una cancelada no es una estancia.');
        $this->assertSame(2, $valor['noches']);
        $this->assertSame(1, $valor['cancelaciones']);
    }

    public function testElGastoSaleDelFolioYNoDelTotalDeLaReserva(): void
    {
        // Ahí están el restaurante, el minibar y las actividades, que es donde
        // de verdad se ve quién gasta.
        $id = $this->crearHuesped();
        $db = db_connect();

        $db->table('reservas')->insert([
            'codigo' => 'CRM' . random_int(10000, 99999), 'huesped_id' => $id, 'unidad_id' => null,
            'fecha_entrada' => date('Y-m-d', strtotime('-5 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('-3 days')),
            'adultos' => 2, 'estado' => 'checkout', 'total' => 400000,
        ]);
        $reservaId = (int) $db->insertID();

        $db->table('folio_movimientos')->insert([
            'reserva_id' => $reservaId, 'tipo' => 'cargo', 'concepto' => 'Alojamiento', 'valor' => 400000,
        ]);
        $db->table('folio_movimientos')->insert([
            'reserva_id' => $reservaId, 'tipo' => 'cargo', 'concepto' => 'Cena', 'valor' => 120000,
        ]);
        $db->table('folio_movimientos')->insert([
            'reserva_id' => $reservaId, 'tipo' => 'pago', 'concepto' => 'Efectivo', 'valor' => 520000,
        ]);

        $this->assertEqualsWithDelta(520000, $this->crm->valor($id)['gasto'], 0.01);
    }

    public function testUnHuespedNuevoNoTieneValorNiRompeNada(): void
    {
        $valor = $this->crm->valor($this->crearHuesped());

        $this->assertSame(0, $valor['estancias']);
        $this->assertEqualsWithDelta(0, $valor['gasto'], 0.01);
        $this->assertNull($valor['ultima']);
        $this->assertNull($valor['dias_desde']);
    }

    // ── La pantalla ─────────────────────────────────────────────────────

    public function testLaFichaSeAbreYAvisaDeLasAlergias(): void
    {
        $id = $this->crearHuesped();

        (new HuespedPreferenciaModel())->insert([
            'huesped_id' => $id, 'tipo' => 'alergia', 'valor' => 'PRUEBA-Maní', 'critica' => 1,
        ]);

        $r = $this->withSession($this->como('recepcion'))->get('huespedes/ver/' . $id);

        $r->assertOK();
        $r->assertSee('PRUEBA-Maní');
        $r->assertSee('Qué nos ha autorizado');
    }
}
