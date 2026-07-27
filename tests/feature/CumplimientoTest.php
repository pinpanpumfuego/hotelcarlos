<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Cumplimiento;
use App\Libraries\Crm;
use App\Libraries\ExpedienteDatos;
use App\Models\ConfiguracionModel;
use App\Models\HuespedModel;
use App\Models\HuespedPreferenciaModel;
use App\Models\PoliticaModel;
use App\Models\RolModel;
use App\Models\SolicitudDatosModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Cumplimiento y derechos del titular.
 *
 * Lo que se defiende:
 *
 * 1. **No se entrega nada sin comprobar quién pide.** Darle los datos de
 *    alguien a quien no es esa persona es la misma infracción que se intenta
 *    evitar, hecha por escrito y con acuse de recibo.
 * 2. **El texto de una política que ya firmó gente no se cambia.** Eso
 *    convertiría su firma en una firma sobre otro documento.
 * 3. **El expediente no lleva la cédula ni la firma dentro.** Existen y se
 *    dicen, pero un fichero que viaja por correo no es sitio para eso.
 *
 * @internal
 */
final class CumplimientoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private SolicitudDatosModel $solicitudes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->solicitudes = new SolicitudDatosModel();
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

        $db->table('solicitudes_datos')->like('nombre', 'PRUEBA-', 'after')->delete();
        $db->table('politicas')->like('titulo', 'PRUEBA-', 'after')->delete();

        $ids = array_column(
            $db->table('huespedes')->select('id')->like('num_documento', 'PRUEBA-LEG', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            foreach ($db->table('reservas')->select('id')->where('huesped_id', $id)->get()->getResultArray() as $r) {
                $db->table('folio_movimientos')->where('reserva_id', $r['id'])->delete();
                $db->table('encuestas')->where('reserva_id', $r['id'])->delete();
            }

            $db->table('envios')->where('huesped_id', $id)->delete();
            $db->table('reservas')->where('huesped_id', $id)->delete();
            $db->table('consentimientos')->where('huesped_id', $id)->delete();
            $db->table('huesped_preferencias')->where('huesped_id', $id)->delete();
        }

        $db->table('huespedes')->like('num_documento', 'PRUEBA-LEG', 'after')->delete();
    }

    private function crearHuesped(string $documento): int
    {
        (new HuespedModel())->insert([
            'nombre'         => 'Legal',
            'apellidos'      => 'Prueba',
            'tipo_documento' => 'CC',
            'num_documento'  => $documento,
            'email'          => 'legal.prueba@example.test',
        ]);

        return (int) db_connect()->insertID();
    }

    private function como(string $perfil): array
    {
        $rol = (new RolModel())->porClave($perfil);
        $this->assertNotNull($rol, "Falta el perfil «{$perfil}».");

        return [
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ];
    }

    private function crearSolicitud(array $extra = []): int
    {
        $this->solicitudes->insert(array_merge([
            'codigo'    => $this->solicitudes->siguienteCodigo(),
            'tipo'      => 'acceso',
            'nombre'    => 'PRUEBA-Quien pide',
            'documento' => 'PRUEBA-LEG-DOC',
            'estado'    => 'recibida',
            'vence_en'  => $this->solicitudes->vencimiento('acceso'),
        ], $extra));

        return (int) db_connect()->insertID();
    }

    // ── Plazos ──────────────────────────────────────────────────────────

    /**
     * @dataProvider arranques
     */
    public function testElPlazoSeCuentaEnDiasHabiles(string $desde): void
    {
        // Se cuenta por un camino distinto al que lo calcula: si se saltara un
        // festivo de más o de menos, no cuadraría.
        $config = new ConfiguracionModel();
        $dias   = (int) $config->obtener('datos_plazo_consulta', '10');

        $vence   = $this->solicitudes->vencimiento('acceso', $desde);
        $habiles = 0;
        $fecha   = $desde;

        while ($fecha < $vence) {
            $fecha = date('Y-m-d', strtotime($fecha . ' +1 day'));

            if (! \App\Libraries\FestivosColombia::esNoLaborable($fecha)) {
                $habiles++;
            }
        }

        $this->assertSame($dias, $habiles, 'Con inicio en ' . $desde);
        $this->assertFalse(
            \App\Libraries\FestivosColombia::esNoLaborable($vence),
            'El plazo no puede vencer en domingo ni en festivo.'
        );
    }

    public static function arranques(): array
    {
        return [
            'diciembre'    => ['2026-12-01'],
            'semana santa' => ['2026-03-25'],
            'un sábado'    => ['2026-09-05'],
        ];
    }

    public function testUnaConsultaTieneMenosPlazoQueUnReclamo(): void
    {
        // Mirar qué datos hay es más rápido que comprobar y corregir.
        $consulta = $this->solicitudes->vencimiento('acceso', '2026-06-01');
        $reclamo  = $this->solicitudes->vencimiento('supresion', '2026-06-01');

        $this->assertLessThan($reclamo, $consulta);
    }

    public function testElPlazoSeCongelaAlRegistrarla(): void
    {
        $id    = $this->crearSolicitud();
        $antes = $this->solicitudes->find($id)['vence_en'];

        $this->withSession($this->como('gerencia'))->get('legal/derecho/' . $id);

        $this->assertSame($antes, $this->solicitudes->find($id)['vence_en']);
    }

    // ── No se entrega sin comprobar quién pide ──────────────────────────

    public function testSinComprobarLaIdentidadNoSeBajaElExpediente(): void
    {
        // Es el paso que no se puede saltar.
        $huespedId = $this->crearHuesped('PRUEBA-LEG-1');
        $id        = $this->crearSolicitud(['huesped_id' => $huespedId, 'identificado' => 0]);

        $r = $this->withSession($this->como('gerencia'))->get('legal/derecho/expediente/' . $id);

        $this->assertTrue($r->isRedirect(), 'Se entregó el expediente sin comprobar quién pedía.');
    }

    public function testSinComprobarLaIdentidadNoSePuedeDarPorAtendida(): void
    {
        $id = $this->crearSolicitud();

        $this->withSession($this->como('gerencia'))->post('legal/derecho/atender/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'respuesta'      => 'PRUEBA-Se le entregó todo.',
        ]);

        $this->assertSame('recibida', $this->solicitudes->find($id)['estado']);
    }

    public function testIdentificarExigeDecirComoSeComprobo(): void
    {
        // «Se identificó» sin decir cómo no vale de nada el día que lo revisen.
        $id = $this->crearSolicitud();

        $this->withSession($this->como('gerencia'))->post('legal/derecho/identificar/' . $id, [
            'csrf_test_name'  => csrf_hash(),
            'como_identifico' => '   ',
        ]);

        $this->assertSame(0, (int) $this->solicitudes->find($id)['identificado']);
    }

    public function testConIdentidadComprobadaSiSeEntrega(): void
    {
        $huespedId = $this->crearHuesped('PRUEBA-LEG-2');
        $id        = $this->crearSolicitud(['huesped_id' => $huespedId]);
        $sesion    = $this->como('gerencia');

        $this->withSession($sesion)->post('legal/derecho/identificar/' . $id, [
            'csrf_test_name'  => csrf_hash(),
            'como_identifico' => 'PRUEBA-Vino con su cédula.',
        ]);

        $r = $this->withSession($sesion)->get('legal/derecho/expediente/' . $id);

        $r->assertOK();
        $r->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function testRechazarExigeMotivo(): void
    {
        $id = $this->crearSolicitud();

        $this->withSession($this->como('gerencia'))->post('legal/derecho/rechazar/' . $id, [
            'csrf_test_name'  => csrf_hash(),
            'motivo_rechazo'  => '',
        ]);

        $this->assertSame('recibida', $this->solicitudes->find($id)['estado']);
    }

    public function testRecepcionVeElPanelPeroNoEntregaDatos(): void
    {
        $id     = $this->crearSolicitud();
        $sesion = $this->como('recepcion');

        $this->assertFalse($this->withSession($sesion)->get('legal')->isRedirect(), 'Recepción debería ver el panel.');

        $r = $this->withSession($sesion)->post('legal/derecho/identificar/' . $id, [
            'csrf_test_name'  => csrf_hash(),
            'como_identifico' => 'PRUEBA-No debería poder',
        ]);

        $this->assertTrue($r->isRedirect());
        $this->assertSame(0, (int) $this->solicitudes->find($id)['identificado']);
    }

    // ── El expediente ───────────────────────────────────────────────────

    public function testElExpedienteNoLlevaElDocumentoCompleto(): void
    {
        // Un fichero que viaja por correo no es sitio para una cédula entera.
        $huespedId = $this->crearHuesped('PRUEBA-LEG-1234567');

        $texto = (new ExpedienteDatos())->comoTexto($huespedId);

        $this->assertNotNull($texto);
        $this->assertStringNotContainsString('PRUEBA-LEG-1234567', $texto, 'Se coló el documento completo.');
        $this->assertStringContainsString('4567', $texto, 'Los últimos dígitos sí, para que se reconozca.');
    }

    public function testElExpedienteIncluyeElRastroDeAutorizaciones(): void
    {
        // El libro entero, no el estado de hoy: es la prueba de qué autorizó.
        $huespedId = $this->crearHuesped('PRUEBA-LEG-3');
        $crm       = new Crm();

        $crm->otorgar($huespedId, 'marketing', 'email', ['origen' => 'registro']);
        $crm->retirar($huespedId, 'marketing', 'email', ['origen' => 'baja_email']);

        $texto = (new ExpedienteDatos())->comoTexto($huespedId);

        $this->assertStringContainsString('autorizó', (string) $texto);
        $this->assertStringContainsString('retiró', (string) $texto);
    }

    public function testElExpedienteIncluyeLoSensible(): void
    {
        // Son SUS datos: el derecho de acceso es justamente a todos ellos.
        $huespedId = $this->crearHuesped('PRUEBA-LEG-4');

        (new HuespedPreferenciaModel())->insert([
            'huesped_id' => $huespedId, 'tipo' => 'alergia', 'valor' => 'PRUEBA-Maní', 'critica' => 1,
        ]);

        $this->assertStringContainsString('PRUEBA-Maní', (string) (new ExpedienteDatos())->comoTexto($huespedId));
    }

    public function testDeUnPerfilQueNoExisteNoHayExpediente(): void
    {
        $this->assertNull((new ExpedienteDatos())->comoTexto(999999));
    }

    // ── Políticas ───────────────────────────────────────────────────────

    public function testUnaPoliticaAceptadaNoSePuedeReescribir(): void
    {
        // Cambiar el texto convertiría la firma de quien la aceptó en una firma
        // sobre otro documento.
        $politicas = new PoliticaModel();

        $politicas->insert([
            'tipo' => 'datos', 'version' => 'PRUEBA-9.9', 'titulo' => 'PRUEBA-Política',
            'texto' => 'Texto original.', 'vigente_desde' => date('Y-m-d'), 'publicada' => 1,
        ]);
        $id = (int) db_connect()->insertID();

        // Alguien la acepta
        $db = db_connect();
        $db->table('reservas')->insert([
            'codigo' => 'LG' . random_int(10000, 99999), 'huesped_id' => $this->crearHuesped('PRUEBA-LEG-5'),
            'unidad_id' => null, 'fecha_entrada' => date('Y-m-d'), 'fecha_salida' => date('Y-m-d', strtotime('+2 days')),
            'adultos' => 1, 'estado' => 'confirmada', 'total' => 100000,
        ]);
        $reservaId = (int) $db->insertID();

        $db->table('registros')->insert([
            'reserva_id' => $reservaId, 'token' => bin2hex(random_bytes(16)),
            'estado' => 'aprobado', 'acepta_datos' => 1, 'version_politica' => 'PRUEBA-9.9',
        ]);

        $this->assertSame(1, $politicas->cuantosAceptaron('PRUEBA-9.9'));

        $this->withSession($this->como('gerencia'))->post('legal/politica/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'tipo'           => 'datos',
            'version'        => 'PRUEBA-9.9',
            'titulo'         => 'PRUEBA-Política',
            'texto'          => 'Texto CAMBIADO a traición.',
            'vigente_desde'  => date('Y-m-d'),
        ]);

        $this->assertSame('Texto original.', $politicas->find($id)['texto'], 'Se pudo reescribir lo ya firmado.');

        $db->table('registros')->where('reserva_id', $reservaId)->delete();
        $db->table('reservas')->where('id', $reservaId)->delete();
    }

    public function testUnaPoliticaSinAceptarSiSePuedeCorregir(): void
    {
        $politicas = new PoliticaModel();

        $politicas->insert([
            'tipo' => 'datos', 'version' => 'PRUEBA-0.1', 'titulo' => 'PRUEBA-Borrador',
            'texto' => 'Primera redacción.', 'vigente_desde' => date('Y-m-d'), 'publicada' => 0,
        ]);
        $id = (int) db_connect()->insertID();

        $this->withSession($this->como('gerencia'))->post('legal/politica/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'tipo'           => 'datos',
            'version'        => 'PRUEBA-0.1',
            'titulo'         => 'PRUEBA-Borrador',
            'texto'          => 'Segunda redacción, mejor.',
            'vigente_desde'  => date('Y-m-d'),
        ]);

        $this->assertSame('Segunda redacción, mejor.', $politicas->find($id)['texto']);
    }

    public function testLaVigenteEsLaPublicadaMasReciente(): void
    {
        $politicas = new PoliticaModel();

        $politicas->insert([
            'tipo' => 'reglamento', 'version' => 'PRUEBA-1.0', 'titulo' => 'PRUEBA-Vieja',
            'texto' => 'x', 'vigente_desde' => date('Y-m-d', strtotime('-1 year')), 'publicada' => 1,
        ]);
        $politicas->insert([
            'tipo' => 'reglamento', 'version' => 'PRUEBA-2.0', 'titulo' => 'PRUEBA-Nueva',
            'texto' => 'y', 'vigente_desde' => date('Y-m-d'), 'publicada' => 1,
        ]);

        $this->assertSame('PRUEBA-2.0', $politicas->vigente('reglamento')['version']);
    }

    public function testUnaPoliticaNoPublicadaNoRige(): void
    {
        $politicas = new PoliticaModel();

        $politicas->insert([
            'tipo' => 'cookies', 'version' => 'PRUEBA-1.0', 'titulo' => 'PRUEBA-Sin publicar',
            'texto' => 'x', 'vigente_desde' => date('Y-m-d'), 'publicada' => 0,
        ]);

        $this->assertNull($politicas->vigente('cookies'));
    }

    // ── El panel ────────────────────────────────────────────────────────

    public function testElPanelRevisaTodosLosTemas(): void
    {
        $revision = (new Cumplimiento())->revisar();

        foreach (['rnt', 'datos', 'huespedes', 'consumidor', 'seguridad'] as $tema) {
            $this->assertArrayHasKey($tema, $revision);
            $this->assertNotEmpty($revision[$tema]['puntos'], 'El tema ' . $tema . ' no revisa nada.');
        }
    }

    public function testCadaPuntoDicePorQueImporta(): void
    {
        // Dentro de seis meses nadie recordará para qué servía una línea.
        foreach ((new Cumplimiento())->revisar() as $grupo) {
            foreach ($grupo['puntos'] as $p) {
                $this->assertNotSame('', trim($p['porque']), 'El punto «' . $p['titulo'] . '» no explica nada.');
                $this->assertContains($p['estado'], ['bien', 'atencion', 'mal', 'sin_dato']);
            }
        }
    }

    public function testSinPoliticaPublicadaElPanelLoMarcaComoFalta(): void
    {
        db_connect()->table('politicas')->where('tipo', 'datos')->update(['publicada' => 0]);

        $revision = (new Cumplimiento())->revisar();
        $punto    = null;

        foreach ($revision['datos']['puntos'] as $p) {
            if ($p['titulo'] === 'Política de tratamiento de datos') {
                $punto = $p;
            }
        }

        $this->assertNotNull($punto);
        $this->assertSame('mal', $punto['estado']);
    }

    public function testElResumenCuentaTodosLosPuntos(): void
    {
        $cumplimiento = new Cumplimiento();
        $revision     = $cumplimiento->revisar();

        $total = 0;

        foreach ($revision as $grupo) {
            $total += count($grupo['puntos']);
        }

        $this->assertSame($total, array_sum($cumplimiento->resumen($revision)));
    }

    public function testLasPantallasSeAbren(): void
    {
        $id     = $this->crearSolicitud();
        $sesion = $this->como('gerencia');

        foreach (['legal', 'legal/datos', 'legal/politicas', 'legal/politica',
                  'legal/derechos', 'legal/derecho/' . $id] as $ruta) {
            $r = $this->withSession($sesion)->get($ruta);

            $this->assertFalse($r->isRedirect(), "/{$ruta} rebotó.");
            $r->assertOK();
        }
    }
}
