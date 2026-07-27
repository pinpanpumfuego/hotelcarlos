<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Crm;
use App\Libraries\Segmentos;
use App\Models\CampanaModel;
use App\Models\EnvioModel;
use App\Models\HuespedModel;
use App\Models\PqrModel;
use App\Models\PqrNotaModel;
use App\Models\RolModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * PQR, segmentación y campañas.
 *
 * Lo que se defiende aquí:
 *
 * 1. **El plazo se cuenta en días hábiles.** Quince días naturales desde el 20
 *    de diciembre no son quince días hábiles, y la Ley 1755/2015 habla de los
 *    segundos. Un plazo mal calculado da una fecha falsa, que es peor que no
 *    tener fecha.
 * 2. **No se cierra una queja sin contestarla.** Sería hacerla desaparecer del
 *    tablero sin que el huésped haya sabido nada.
 * 3. **Una campaña no llega a quien no lo autorizó**, aunque cumpla los
 *    filtros. Y la pantalla lo dice antes de mandar, no después.
 *
 * @internal
 */
final class PqrCampanasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private PqrModel $pqr;
    private Crm $crm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pqr = new PqrModel();
        $this->crm = new Crm();
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

        foreach ($db->table('pqr')->select('id')->like('asunto', 'PRUEBA-', 'after')->get()->getResultArray() as $p) {
            $db->table('pqr_notas')->where('pqr_id', $p['id'])->delete();
        }

        $db->table('pqr')->like('asunto', 'PRUEBA-', 'after')->delete();
        $db->table('campanas')->like('nombre', 'PRUEBA-', 'after')->delete();

        $ids = array_column(
            $db->table('huespedes')->select('id')->like('num_documento', 'PRUEBA-SEG', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            foreach ($db->table('reservas')->select('id')->where('huesped_id', $id)->get()->getResultArray() as $r) {
                $db->table('folio_movimientos')->where('reserva_id', $r['id'])->delete();
            }

            $db->table('envios')->where('huesped_id', $id)->delete();
            $db->table('reservas')->where('huesped_id', $id)->delete();
            $db->table('consentimientos')->where('huesped_id', $id)->delete();
            $db->table('huesped_preferencias')->where('huesped_id', $id)->delete();
        }

        $db->table('huespedes')->like('num_documento', 'PRUEBA-SEG', 'after')->delete();
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

    private function crearPqr(array $extra = []): int
    {
        $this->pqr->insert(array_merge([
            'codigo'   => $this->pqr->siguienteCodigo(),
            'tipo'     => 'queja',
            'asunto'   => 'PRUEBA-La ducha no calentaba',
            'detalle'  => 'PRUEBA-Detalle de lo que pasó.',
            'estado'   => 'recibida',
            'vence_en' => $this->pqr->vencimiento('queja'),
        ], $extra));

        return (int) $this->pqr->getInsertID();
    }

    private function crearHuesped(array $extra = []): int
    {
        static $n = 0;
        $n++;

        (new HuespedModel())->insert(array_merge([
            'nombre'         => 'Seg',
            'apellidos'      => 'Prueba ' . $n,
            'tipo_documento' => 'CC',
            'num_documento'  => 'PRUEBA-SEG-' . $n . '-' . random_int(1000, 9999),
            'email'          => 'seg' . $n . '@example.test',
        ], $extra));

        return (int) db_connect()->insertID();
    }

    // ── El plazo legal ──────────────────────────────────────────────────

    public function testElPlazoSeCuentaEnDiasHabiles(): void
    {
        // Quince días hábiles siempre caen más lejos que quince naturales,
        // porque por medio hay al menos cuatro fines de semana.
        $vence = $this->pqr->vencimiento('queja', '2026-12-01');

        $this->assertGreaterThan(
            date('Y-m-d', strtotime('2026-12-01 +15 days')),
            $vence,
            'Se está contando en días naturales.'
        );
    }

    /**
     * @dataProvider arranques
     */
    public function testEntreElInicioYElPlazoHayExactamenteQuinceDiasHabiles(string $desde): void
    {
        // Se cuenta por otro camino que como se calcula: aquí se recorre día a
        // día y se van contando los hábiles. Si el cálculo se saltara un
        // festivo de más o de menos, no cuadraría.
        $vence = $this->pqr->vencimiento('queja', $desde);

        $habiles = 0;
        $fecha   = $desde;

        while ($fecha < $vence) {
            $fecha = date('Y-m-d', strtotime($fecha . ' +1 day'));

            if (! \App\Libraries\FestivosColombia::esNoLaborable($fecha)) {
                $habiles++;
            }
        }

        $this->assertSame(15, $habiles, 'Con inicio en ' . $desde);
        $this->assertFalse(
            \App\Libraries\FestivosColombia::esNoLaborable($vence),
            'El plazo no puede vencer en domingo ni en festivo: ' . $vence
        );
    }

    public static function arranques(): array
    {
        // Meses con festivos de sobra y meses sin ninguno, más el arranque en
        // viernes y en sábado, que es donde fallan estos cálculos.
        return [
            'diciembre'      => ['2026-12-01'],
            'junio'          => ['2026-06-01'],
            'semana santa'   => ['2026-03-25'],
            'un viernes'     => ['2026-09-04'],
            'un sábado'      => ['2026-09-05'],
            'fin de año'     => ['2026-12-28'],
        ];
    }

    public function testCadaTipoTieneSuPlazo(): void
    {
        $queja        = $this->pqr->vencimiento('queja', '2026-06-01');
        $felicitacion = $this->pqr->vencimiento('felicitacion', '2026-06-01');

        $this->assertGreaterThan($felicitacion, $queja);
    }

    public function testElPlazoSeCongelaAlRegistrarla(): void
    {
        // Recalcularlo al mirarla movería la fecha límite cada vez que se abre
        // la pantalla, y entonces nunca vencería nada.
        $id     = $this->crearPqr();
        $antes  = $this->pqr->find($id)['vence_en'];

        $this->withSession($this->como('recepcion'))->get('pqr/ver/' . $id);

        $this->assertSame($antes, $this->pqr->find($id)['vence_en']);
    }

    public function testLosCodigosNoSeRepiten(): void
    {
        $vistos = [];

        for ($i = 0; $i < 4; $i++) {
            $id     = $this->crearPqr();
            $codigo = $this->pqr->find($id)['codigo'];

            $this->assertNotContains($codigo, $vistos);
            $vistos[] = $codigo;
        }
    }

    // ── El ciclo ────────────────────────────────────────────────────────

    public function testNoSeCierraSinContestar(): void
    {
        // Cerrar sin contestar es hacer desaparecer la queja del tablero sin
        // que el huésped haya sabido nada.
        $id = $this->crearPqr();

        $this->withSession($this->como('recepcion'))->post('pqr/cerrar/' . $id, [
            'csrf_test_name' => csrf_hash(),
        ]);

        $this->assertSame('recibida', $this->pqr->find($id)['estado']);
    }

    public function testResponderYCerrarFunciona(): void
    {
        $id     = $this->crearPqr();
        $sesion = $this->como('recepcion');

        $this->withSession($sesion)->post('pqr/responder/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'respuesta'      => 'PRUEBA-Se cambió la resistencia y se le devolvió una noche.',
            'causa'          => 'PRUEBA-Calentador',
        ]);

        $this->assertSame('respondida', $this->pqr->find($id)['estado']);

        $this->withSession($sesion)->post('pqr/cerrar/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'satisfaccion'   => '4',
        ]);

        $ficha = $this->pqr->find($id);
        $this->assertSame('cerrada', $ficha['estado']);
        $this->assertSame(4, (int) $ficha['satisfaccion']);
    }

    public function testResponderSinTextoRebota(): void
    {
        $id = $this->crearPqr();

        $this->withSession($this->como('recepcion'))->post('pqr/responder/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'respuesta'      => '   ',
        ]);

        $this->assertSame('recibida', $this->pqr->find($id)['estado']);
    }

    public function testReabrirDaUnPlazoNuevo(): void
    {
        // Si conservara el viejo nacería ya vencida y nadie la miraría por
        // estar siempre en rojo.
        $id     = $this->crearPqr(['vence_en' => date('Y-m-d', strtotime('-20 days'))]);
        $sesion = $this->como('recepcion');

        $this->withSession($sesion)->post('pqr/responder/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'respuesta'      => 'PRUEBA-Contestado.',
        ]);
        $this->withSession($sesion)->post('pqr/reabrir/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'motivo'         => 'PRUEBA-Volvió a escribir',
        ]);

        $ficha = $this->pqr->find($id);

        $this->assertSame('reabierta', $ficha['estado']);
        $this->assertGreaterThan(date('Y-m-d'), $ficha['vence_en']);
    }

    public function testCadaPasoDejaRastro(): void
    {
        // «Se habló con el huésped» sin rastro es la palabra de alguien.
        $id     = $this->crearPqr();
        $sesion = $this->como('recepcion');

        $this->withSession($sesion)->post('pqr/nota/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'tipo'           => 'contacto',
            'texto'          => 'PRUEBA-Se le llamó y quedó en volver a probar.',
        ]);

        $this->withSession($sesion)->post('pqr/responder/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'respuesta'      => 'PRUEBA-Resuelto.',
        ]);

        $notas = (new PqrNotaModel())->deP($id);
        $tipos = array_column($notas, 'tipo');

        $this->assertContains('contacto', $tipos);
        $this->assertContains('respuesta', $tipos);
    }

    public function testHousekeepingRecogeUnaQuejaPeroNoLaContesta(): void
    {
        $id     = $this->crearPqr();
        $sesion = $this->como('housekeeping');

        // Puede apuntar lo que vio
        $this->withSession($sesion)->post('pqr/nota/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'tipo'           => 'nota',
            'texto'          => 'PRUEBA-Vi la ducha y goteaba.',
        ]);
        $this->assertSame(1, (new PqrNotaModel())->where('pqr_id', $id)->countAllResults());

        // Pero no puede contestar en nombre del hotel
        $r = $this->withSession($sesion)->post('pqr/responder/' . $id, [
            'csrf_test_name' => csrf_hash(),
            'respuesta'      => 'PRUEBA-Lo que sea.',
        ]);

        $this->assertTrue($r->isRedirect());
        $this->assertSame('recibida', $this->pqr->find($id)['estado']);
    }

    public function testLasQueSeQuedanSinPlazoSalenAvisadas(): void
    {
        $this->crearPqr(['vence_en' => date('Y-m-d', strtotime('-2 days'))]);

        $codigos = array_column($this->pqr->enRiesgo(), 'asunto');

        $this->assertContains('PRUEBA-La ducha no calentaba', $codigos);
    }

    public function testLoCerradoNoSaleComoEnRiesgo(): void
    {
        $this->crearPqr([
            'vence_en' => date('Y-m-d', strtotime('-2 days')),
            'estado'   => 'cerrada',
            'asunto'   => 'PRUEBA-Ya cerrada',
        ]);

        $asuntos = array_column($this->pqr->enRiesgo(), 'asunto');

        $this->assertNotContains('PRUEBA-Ya cerrada', $asuntos);
    }

    // ── Segmentación ────────────────────────────────────────────────────

    public function testUnSegmentoFiltraPorOrigen(): void
    {
        $recomendado = $this->crearHuesped(['origen' => 'recomendado']);
        $booking     = $this->crearHuesped(['origen' => 'booking']);

        $ids = array_map('intval', array_column((new Segmentos())->buscar(['origen' => 'recomendado']), 'id'));

        $this->assertContains($recomendado, $ids);
        $this->assertNotContains($booking, $ids);
    }

    public function testQuienNuncaVinoNoEntraEnRecuperacion(): void
    {
        // Quien nunca vino no «lleva sin venir»: simplemente no ha venido, y
        // mandarle una campaña de recuperación no tiene ningún sentido.
        $nuevo = $this->crearHuesped();

        $ids = array_map('intval', array_column((new Segmentos())->buscar(['sin_venir_min' => 30]), 'id'));

        $this->assertNotContains($nuevo, $ids);
    }

    public function testElSegmentoCuentaLasEstanciasDeVerdad(): void
    {
        $huespedId = $this->crearHuesped();
        $db        = db_connect();

        foreach (['checkout', 'cancelada'] as $estado) {
            $db->table('reservas')->insert([
                'codigo' => 'SG' . random_int(10000, 99999), 'huesped_id' => $huespedId, 'unidad_id' => null,
                'fecha_entrada' => date('Y-m-d', strtotime('-10 days')),
                'fecha_salida'  => date('Y-m-d', strtotime('-8 days')),
                'adultos' => 2, 'estado' => $estado, 'total' => 500000,
            ]);
        }

        $segmentos = new Segmentos();

        $unaVez = array_map('intval', array_column($segmentos->buscar(['estancias_min' => 1]), 'id'));
        $dosVeces = array_map('intval', array_column($segmentos->buscar(['estancias_min' => 2]), 'id'));

        $this->assertContains($huespedId, $unaVez);
        $this->assertNotContains($huespedId, $dosVeces, 'Una cancelada no es una estancia.');
    }

    public function testUnPerfilFusionadoNoEntraEnNingunSegmento(): void
    {
        $id = $this->crearHuesped();
        (new HuespedModel())->update($id, ['estado' => 'fusionado']);

        $ids = array_map('intval', array_column((new Segmentos())->buscar([]), 'id'));

        $this->assertNotContains($id, $ids);
    }

    public function testContarYBuscarDicenLoMismo(): void
    {
        $this->crearHuesped(['origen' => 'web']);
        $this->crearHuesped(['origen' => 'web']);

        $segmentos = new Segmentos();

        $this->assertSame(
            $segmentos->contar(['origen' => 'web']),
            count($segmentos->buscar(['origen' => 'web'], 5000))
        );
    }

    public function testLosFiltrosVaciosSeDescartan(): void
    {
        $limpio = (new Segmentos())->limpiar([
            'origen' => 'web', 'pais' => '', 'estancias_min' => '0', 'inventado' => 'x',
        ]);

        $this->assertSame(['origen' => 'web'], $limpio);
    }

    // ── Campañas ────────────────────────────────────────────────────────

    private function crearCampana(array $filtros): int
    {
        (new CampanaModel())->insert([
            'nombre'          => 'PRUEBA-Campaña',
            'plantilla_clave' => 'recuperacion',
            'canal'           => 'email',
            'filtros'         => json_encode($filtros),
            'estado'          => 'borrador',
        ]);

        return (int) db_connect()->insertID();
    }

    public function testUnaCampanaSoloLlegaAQuienLoAutorizo(): void
    {
        $conPermiso = $this->crearHuesped(['origen' => 'web']);
        $sinPermiso = $this->crearHuesped(['origen' => 'web']);

        $this->crm->otorgar($conPermiso, 'marketing', 'email');

        $campanaId = $this->crearCampana(['origen' => 'web']);

        $this->withSession($this->como('recepcion'))->post('campanas/enviar/' . $campanaId, [
            'csrf_test_name' => csrf_hash(),
            'confirmar'      => 'ENVIAR',
        ]);

        $envios = new EnvioModel();

        $this->assertSame(1, $envios->where('huesped_id', $conPermiso)->countAllResults());
        $this->assertSame(0, $envios->where('huesped_id', $sinPermiso)->countAllResults());
    }

    public function testSinEscribirEnviarNoSeMandaNada(): void
    {
        // Un clic de más es barato comparado con un correo que no se recoge.
        $id = $this->crearHuesped(['origen' => 'web']);
        $this->crm->otorgar($id, 'marketing', 'email');

        $campanaId = $this->crearCampana(['origen' => 'web']);

        $this->withSession($this->como('recepcion'))->post('campanas/enviar/' . $campanaId, [
            'csrf_test_name' => csrf_hash(),
            'confirmar'      => 'si',
        ]);

        $this->assertSame('borrador', (new CampanaModel())->find($campanaId)['estado']);
        $this->assertSame(0, (new EnvioModel())->where('huesped_id', $id)->countAllResults());
    }

    public function testUnaCampanaNoSeMandaDosVeces(): void
    {
        $id = $this->crearHuesped(['origen' => 'web']);
        $this->crm->otorgar($id, 'marketing', 'email');

        $campanaId = $this->crearCampana(['origen' => 'web']);
        $sesion    = $this->como('recepcion');

        foreach ([1, 2] as $vez) {
            $this->withSession($sesion)->post('campanas/enviar/' . $campanaId, [
                'csrf_test_name' => csrf_hash(),
                'confirmar'      => 'ENVIAR',
            ]);
        }

        $this->assertSame(1, (new EnvioModel())->where('huesped_id', $id)->countAllResults());
    }

    public function testLaCampanaGuardaLosFiltrosNoLaLista(): void
    {
        // Guardar la lista congelada significaría mandársela a quien se dio de
        // baja entre que se preparó y se envió.
        $campanaId = $this->crearCampana(['origen' => 'web', 'estancias_min' => 2]);
        $campana   = (new CampanaModel())->find($campanaId);

        $this->assertSame(
            ['origen' => 'web', 'estancias_min' => 2],
            (new CampanaModel())->filtrosDe($campana)
        );
    }

    // ── Las pantallas ───────────────────────────────────────────────────

    public function testLasPantallasSeAbren(): void
    {
        $id     = $this->crearPqr();
        $sesion = $this->como('recepcion');

        foreach (['pqr', 'pqr/ver/' . $id, 'pqr/informe', 'campanas', 'campanas/nueva'] as $ruta) {
            $r = $this->withSession($sesion)->get($ruta);

            $this->assertFalse($r->isRedirect(), "/{$ruta} rebotó.");
            $r->assertOK();
        }
    }

    public function testRestauranteNoLlegaALasCampanas(): void
    {
        // Mandarle algo a media base de datos no lo hace cualquiera.
        $r = $this->withSession($this->como('restaurante'))->get('campanas');

        $this->assertTrue($r->isRedirect());
    }
}
