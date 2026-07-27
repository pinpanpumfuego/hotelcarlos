<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Libraries\Automatizaciones;
use App\Libraries\Crm;
use App\Libraries\Mensajero;
use App\Models\AutomatizacionModel;
use App\Models\ConsentimientoModel;
use App\Models\EnvioModel;
use App\Models\HuespedModel;
use App\Models\PlantillaModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * El motor de comunicaciones.
 *
 * Lo que se defiende aquí, por orden de gravedad si falla:
 *
 * 1. **Nada sale sin permiso.** La puerta está en un solo sitio a propósito;
 *    estas pruebas comprueban que de verdad no se puede rodear.
 * 2. **Se vuelve a preguntar justo antes de mandar.** Entre encolar un
 *    pre-arrival y que salga pasan tres días, y en tres días alguien se puede
 *    dar de baja.
 * 3. **Correr la tarea dos veces no manda dos veces.** Las tareas programadas
 *    de los hostings se disparan de más más a menudo de lo que parece.
 * 4. **La baja con un clic funciona y retira solo lo que toca**: darse de baja
 *    de las ofertas no puede dejar a nadie sin la confirmación de su reserva.
 *
 * @internal
 */
final class ComunicacionesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = false;
    protected $namespace = null;

    private Mensajero $mensajero;
    private EnvioModel $envios;
    private Crm $crm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mensajero = new Mensajero();
        $this->envios    = new EnvioModel();
        $this->crm       = new Crm();
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
            $db->table('huespedes')->select('id')->like('num_documento', 'PRUEBA-COM', 'after')->get()->getResultArray(),
            'id'
        );

        foreach ($ids as $id) {
            $reservas = array_column(
                $db->table('reservas')->select('id')->where('huesped_id', $id)->get()->getResultArray(),
                'id'
            );

            foreach ($reservas as $r) {
                $db->table('folio_movimientos')->where('reserva_id', $r)->delete();
                $db->table('portal_accesos')->where('reserva_id', $r)->delete();
            }

            $db->table('envios')->where('huesped_id', $id)->delete();
            $db->table('reservas')->where('huesped_id', $id)->delete();
            $db->table('consentimientos')->where('huesped_id', $id)->delete();
        }

        $db->table('huespedes')->like('num_documento', 'PRUEBA-COM', 'after')->delete();
    }

    private function crearHuesped(array $extra = []): int
    {
        static $n = 0;
        $n++;

        (new HuespedModel())->insert(array_merge([
            'nombre'         => 'Prueba',
            'apellidos'      => 'Comunicaciones ' . $n,
            'tipo_documento' => 'CC',
            'num_documento'  => 'PRUEBA-COM-' . $n . '-' . random_int(1000, 9999),
            'email'          => 'prueba.com' . $n . '@example.test',
            'idioma'         => 'es',
        ], $extra));

        return (int) db_connect()->insertID();
    }

    private function crearReserva(int $huespedId, array $extra = []): int
    {
        db_connect()->table('reservas')->insert(array_merge([
            'codigo'        => 'CM' . random_int(10000, 99999),
            'huesped_id'    => $huespedId,
            'unidad_id'     => null,
            'fecha_entrada' => date('Y-m-d', strtotime('+5 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('+8 days')),
            'adultos'       => 2,
            'estado'        => 'confirmada',
            'total'         => 900000,
        ], $extra));

        return (int) db_connect()->insertID();
    }

    // ── La puerta del consentimiento ────────────────────────────────────

    public function testSinPermisoNoSeEncolaLoQueEsMarketing(): void
    {
        $id = $this->crearHuesped();

        $this->assertNull($this->mensajero->encolar($id, 'recuperacion'));
        $this->assertSame(0, $this->envios->where('huesped_id', $id)->countAllResults());
    }

    public function testConPermisoSiSeEncola(): void
    {
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');

        $this->assertNotNull($this->mensajero->encolar($id, 'recuperacion'));
    }

    public function testLoOperativoSaleSinPedirPermiso(): void
    {
        // Mandarle la confirmación de su propia reserva no es publicidad.
        $huespedId = $this->crearHuesped();
        $reservaId = $this->crearReserva($huespedId);

        $this->assertNotNull($this->mensajero->encolar($huespedId, 'confirmacion', ['reserva_id' => $reservaId]));
    }

    public function testSinCorreoNoSeEncolaNada(): void
    {
        // Encolarlo para que falle tres veces solo ensucia la pantalla.
        $id = $this->crearHuesped(['email' => null]);
        $this->crm->otorgar($id, 'marketing', 'email');

        $this->assertNull($this->mensajero->encolar($id, 'recuperacion'));
    }

    public function testUnPerfilFusionadoNoRecibeNada(): void
    {
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');
        (new HuespedModel())->update($id, ['estado' => 'fusionado']);

        $this->assertNull($this->mensajero->encolar($id, 'recuperacion'));
    }

    public function testDarseDeBajaEntreEncolarYMandarCancelaElEnvio(): void
    {
        // Es el caso que justifica volver a preguntar: un pre-arrival se encola
        // hoy y sale dentro de tres días.
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');

        $envioId = $this->mensajero->encolar($id, 'recuperacion');
        $this->assertNotNull($envioId);

        $this->crm->retirar($id, 'marketing', 'email');
        $this->mensajero->procesar();

        $this->assertSame('cancelado', $this->envios->find($envioId)['estado']);
    }

    // ── No se manda dos veces ───────────────────────────────────────────

    public function testNoSeEncolaDosVecesLoMismoParaLaMismaReserva(): void
    {
        $huespedId = $this->crearHuesped();
        $reservaId = $this->crearReserva($huespedId);

        $this->assertNotNull($this->mensajero->encolar($huespedId, 'confirmacion', ['reserva_id' => $reservaId]));
        $this->assertNull($this->mensajero->encolar($huespedId, 'confirmacion', ['reserva_id' => $reservaId]));
        $this->assertSame(1, $this->envios->where('reserva_id', $reservaId)->countAllResults());
    }

    public function testCorrerLasAutomatizacionesDosVecesNoDuplica(): void
    {
        $huespedId = $this->crearHuesped();
        $this->crearReserva($huespedId, ['fecha_entrada' => date('Y-m-d', strtotime('+3 days'))]);

        $auto = new Automatizaciones();
        $auto->correr();
        $segunda = $auto->correr();

        $this->assertSame(0, $segunda['encolados']);
        $this->assertSame(1, $this->envios->where('huesped_id', $huespedId)->countAllResults());
    }

    // ── Las automatizaciones eligen bien a quién ────────────────────────

    public function testElPreArrivalLeTocaAQuienLlegaEnTresDias(): void
    {
        $enTres = $this->crearHuesped();
        $this->crearReserva($enTres, ['fecha_entrada' => date('Y-m-d', strtotime('+3 days'))]);

        $enDiez = $this->crearHuesped();
        $this->crearReserva($enDiez, ['fecha_entrada' => date('Y-m-d', strtotime('+10 days'))]);

        (new Automatizaciones())->correr();

        $this->assertSame(1, $this->envios->where('huesped_id', $enTres)->countAllResults());
        $this->assertSame(0, $this->envios->where('huesped_id', $enDiez)->countAllResults());
    }

    public function testLaEncuestaNoSeLeMandaAQuienCancelo(): void
    {
        // Es la clase de detalle que hace que alguien no vuelva.
        $sefue    = $this->crearHuesped();
        $cancelo  = $this->crearHuesped();
        $ayer     = date('Y-m-d', strtotime('-1 day'));

        foreach ([[$sefue, 'checkout'], [$cancelo, 'cancelada']] as [$h, $estado]) {
            $this->crm->otorgar($h, 'encuestas', 'email');
            $this->crearReserva($h, [
                'fecha_entrada' => date('Y-m-d', strtotime('-3 days')),
                'fecha_salida'  => $ayer,
                'estado'        => $estado,
            ]);
        }

        (new Automatizaciones())->correr();

        $this->assertSame(1, $this->envios->where('huesped_id', $sefue)->countAllResults());
        $this->assertSame(0, $this->envios->where('huesped_id', $cancelo)->countAllResults());
    }

    public function testLaEncuestaNoSaleSinSuPermiso(): void
    {
        $id = $this->crearHuesped();
        $this->crearReserva($id, [
            'fecha_entrada' => date('Y-m-d', strtotime('-3 days')),
            'fecha_salida'  => date('Y-m-d', strtotime('-1 day')),
            'estado'        => 'checkout',
        ]);

        (new Automatizaciones())->correr();

        $this->assertSame(0, $this->envios->where('huesped_id', $id)->countAllResults());
    }

    public function testUnaReglaApagadaNoManda(): void
    {
        $reglas = new AutomatizacionModel();
        $regla  = $reglas->where('evento', 'antes_llegada')->first();
        $reglas->update($regla['id'], ['activa' => 0]);

        $id = $this->crearHuesped();
        $this->crearReserva($id, ['fecha_entrada' => date('Y-m-d', strtotime('+3 days'))]);

        (new Automatizaciones())->correr();

        $this->assertSame(0, $this->envios->where('huesped_id', $id)->countAllResults());

        $reglas->update($regla['id'], ['activa' => 1]);
    }

    public function testElEnsayoNoEncolaNada(): void
    {
        // Sirve para mirar antes de encender algo. Si encolara, no serviría.
        $id = $this->crearHuesped();
        $this->crearReserva($id, ['fecha_entrada' => date('Y-m-d', strtotime('+3 days'))]);

        $lista = (new Automatizaciones())->ensayo();

        $this->assertNotSame([], $lista);
        $this->assertSame(0, $this->envios->where('huesped_id', $id)->countAllResults());
    }

    // ── Variables ───────────────────────────────────────────────────────

    public function testLasVariablesSeRellenanConLosDatosDeLaReserva(): void
    {
        $huespedId = $this->crearHuesped(['nombre' => 'Marta']);
        $reservaId = $this->crearReserva($huespedId);

        $envioId = $this->mensajero->encolar($huespedId, 'confirmacion', ['reserva_id' => $reservaId]);
        $envio   = $this->envios->find($envioId);

        $codigo = db_connect()->table('reservas')->where('id', $reservaId)->get()->getRowArray()['codigo'];

        $this->assertStringContainsString('Marta', $envio['cuerpo']);
        $this->assertStringContainsString($codigo, $envio['cuerpo']);
        $this->assertStringNotContainsString('{{', $envio['cuerpo'], 'Quedó una variable sin rellenar.');
    }

    public function testUnaVariableQueNoExisteSeQuedaEnBlanco(): void
    {
        // «Hola {{nombre}}» delata que nadie miró el correo. Mejor «Hola».
        $texto = $this->mensajero->rellenar('Hola {{nombre}}, {{inventada}}.', ['nombre' => 'Ana']);

        $this->assertSame('Hola Ana, .', $texto);
    }

    public function testElAsuntoTambienSeRellena(): void
    {
        $huespedId = $this->crearHuesped(['nombre' => 'Carlos']);
        $reservaId = $this->crearReserva($huespedId);

        $envio = $this->envios->find($this->mensajero->encolar($huespedId, 'confirmacion', ['reserva_id' => $reservaId]));

        $this->assertStringNotContainsString('{{', (string) $envio['asunto']);
    }

    public function testElTextoSeCongelaAlEncolar(): void
    {
        // Si mañana se cambia la plantilla, lo que se mandó ayer tiene que
        // seguir siendo lo que se mandó ayer.
        $huespedId = $this->crearHuesped();
        $reservaId = $this->crearReserva($huespedId);
        $envioId   = $this->mensajero->encolar($huespedId, 'confirmacion', ['reserva_id' => $reservaId]);

        $antes      = $this->envios->find($envioId)['cuerpo'];
        $plantillas = new PlantillaModel();
        $plantilla  = $plantillas->where('clave', 'confirmacion')->first();
        $original   = $plantilla['cuerpo'];

        $plantillas->update($plantilla['id'], ['cuerpo' => 'Texto completamente distinto.']);

        $this->assertSame($antes, $this->envios->find($envioId)['cuerpo']);

        $plantillas->update($plantilla['id'], ['cuerpo' => $original]);
    }

    // ── La baja con un clic ─────────────────────────────────────────────

    public function testElEnlaceDeBajaRetiraSoloEsaFinalidad(): void
    {
        // Darse de baja de las ofertas no puede dejar a nadie sin la
        // confirmación de su propia reserva.
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');
        $this->crm->otorgar($id, 'encuestas', 'email');

        $envio = $this->envios->find($this->mensajero->encolar($id, 'recuperacion'));

        $r = $this->get('baja/' . $envio['token']);

        $r->assertOK();
        $r->assertSee('no te escribimos más');

        $consentimientos = new ConsentimientoModel();
        $this->assertFalse($consentimientos->permite($id, 'marketing', 'email'));
        $this->assertTrue($consentimientos->permite($id, 'encuestas', 'email'), 'Retiró de más.');
        $this->assertTrue($consentimientos->permite($id, 'operativo', 'email'));
    }

    public function testLaBajaNoPideNiSesionNiContrasena(): void
    {
        // Ponerle trabas a quien quiere dejar de recibir correos es justo lo
        // que la ley prohíbe. Y quien no puede darse de baja marca spam.
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');
        $envio = $this->envios->find($this->mensajero->encolar($id, 'recuperacion'));

        $r = $this->get('baja/' . $envio['token']);

        $this->assertFalse($r->isRedirect(), 'No puede mandar a iniciar sesión.');
        $r->assertOK();
    }

    public function testUnTokenInventadoNoRompeNada(): void
    {
        $r = $this->get('baja/' . str_repeat('a', 48));

        $r->assertOK();
        $r->assertSee('ya no sirve');
    }

    public function testUnTokenConFormaRaraNiSiquieraConsulta(): void
    {
        $r = $this->get('baja/loquesea');

        $r->assertOK();
        $r->assertSee('ya no sirve');
    }

    public function testLaConfirmacionNoLlevaEnlaceDeBaja(): void
    {
        // «Date de baja» en la confirmación de tu propia reserva confunde y no
        // significa nada: eso no se puede dejar de recibir.
        $huespedId = $this->crearHuesped();
        $reservaId = $this->crearReserva($huespedId);
        $envio     = $this->envios->find($this->mensajero->encolar($huespedId, 'confirmacion', ['reserva_id' => $reservaId]));

        $this->assertSame('operativo', $envio['finalidad']);
        $this->assertNull($this->mensajero->darDeBaja($envio['token']), 'Lo operativo no se puede dar de baja.');
    }

    // ── El píxel de apertura ────────────────────────────────────────────

    public function testSinPermisoDePerfiladoNoSeApuntaLaApertura(): void
    {
        // Saber quién abre qué es analizar su comportamiento, y eso tiene su
        // propia casilla. Ponerle el píxel a quien no lo autorizó es hacerlo
        // a escondidas.
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');
        $envioId = $this->mensajero->encolar($id, 'recuperacion');
        $envio   = $this->envios->find($envioId);

        $this->get('px/' . $envio['token'])->assertOK();

        $this->assertNull($this->envios->find($envioId)['abierto_en']);
    }

    public function testConPermisoDePerfiladoSiSeApunta(): void
    {
        $id = $this->crearHuesped();
        $this->crm->otorgar($id, 'marketing', 'email');
        $this->crm->otorgar($id, 'perfilado', 'email');

        $envioId = $this->mensajero->encolar($id, 'recuperacion');
        $this->get('px/' . $this->envios->find($envioId)['token']);

        $this->assertNotNull($this->envios->find($envioId)['abierto_en']);
    }

    public function testElPixelDevuelveUnaImagenAunqueNoApunteNada(): void
    {
        // Si no devolviera imagen, el correo saldría con un hueco roto.
        $r = $this->get('px/' . str_repeat('b', 48));

        $r->assertOK();
        $r->assertHeader('Content-Type', 'image/gif');
    }

    // ── Las pantallas ───────────────────────────────────────────────────

    public function testLasPantallasSeAbren(): void
    {
        // Una vista con una variable que el controlador no pasa revienta al
        // pintarse, no al guardarse.
        $rol = (new \App\Models\RolModel())->porClave('recepcion');

        $sesion = [
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ];

        foreach (['comunicaciones', 'comunicaciones/plantillas'] as $ruta) {
            $r = $this->withSession($sesion)->get($ruta);

            $this->assertFalse($r->isRedirect(), "/{$ruta} rebotó.");
            $r->assertOK();
        }
    }

    public function testRecepcionNoPuedeEditarLasPlantillas(): void
    {
        // Cambiar una plantilla cambia lo que se le dice a todo el mundo.
        $rol       = (new \App\Models\RolModel())->porClave('recepcion');
        $plantilla = (new PlantillaModel())->where('clave', 'confirmacion')->first();

        $r = $this->withSession([
            'usuario_id' => 1, 'usuario_nombre' => 'Prueba',
            'usuario_rol' => 'gerencia', 'usuario_rol_id' => (int) $rol['id'],
        ])->get('comunicaciones/plantilla/' . $plantilla['id']);

        $this->assertTrue($r->isRedirect());
    }

    // ── Reintentos ──────────────────────────────────────────────────────

    public function testTrasTresFallosSeDejaDeInsistir(): void
    {
        // Insistir con una dirección muerta no la resucita, y gasta el cupo
        // diario del servidor de correo.
        $huespedId = $this->crearHuesped();
        $reservaId = $this->crearReserva($huespedId);
        $envioId   = $this->mensajero->encolar($huespedId, 'confirmacion', ['reserva_id' => $reservaId]);

        for ($i = 0; $i < EnvioModel::MAX_INTENTOS; $i++) {
            $this->envios->anotarFallo($envioId, 'PRUEBA-No contesta', $i);
        }

        $envio = $this->envios->find($envioId);

        $this->assertSame('fallido', $envio['estado']);
        $this->assertSame(EnvioModel::MAX_INTENTOS, (int) $envio['intentos']);
        $this->assertSame([], $this->envios->pendientes());
    }

    public function testUnEnvioProgramadoParaMananaNoSaleHoy(): void
    {
        $huespedId = $this->crearHuesped();
        $reservaId = $this->crearReserva($huespedId);

        $this->mensajero->encolar($huespedId, 'confirmacion', [
            'reserva_id' => $reservaId,
            'cuando'     => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $ids = array_column($this->envios->pendientes(), 'huesped_id');

        $this->assertNotContains($huespedId, array_map('intval', $ids));
    }
}
