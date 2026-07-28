<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AcompananteModel;
use App\Models\CartaProductoModel;
use App\Models\ComandaLineaModel;
use App\Models\ComandaModel;
use App\Models\ConfiguracionModel;
use App\Models\EncuestaModel;
use App\Models\ExperienciaModel;
use App\Models\ExperienciaReservaModel;
use App\Models\FolioModel;
use App\Models\PortalAccesoModel;
use App\Models\RegistroModel;
use App\Models\ReservaModel;
use App\Models\ServicioModel;
use App\Models\SolicitudModel;
use App\Models\UnidadModel;

/**
 * Portal del huésped: su estancia, en su móvil.
 *
 * Sin cuenta y sin contraseña: **el enlace es la credencial**. Por eso cada
 * método vuelve a validar el token, y ninguno se fía de que el anterior lo
 * hiciera. Es repetitivo a propósito; en algo abierto a internet, repetir la
 * comprobación cuesta una línea y olvidarla cuesta una filtración.
 */
class Portal extends BaseController
{
    private PortalAccesoModel $accesos;
    private ReservaModel $reservas;

    /** Peticiones que puede hacer una misma reserva en un día. */
    private const TOPE_SOLICITUDES_DIA = 12;

    public function __construct()
    {
        $this->accesos  = new PortalAccesoModel();
        $this->reservas = new ReservaModel();
    }

    // ── Pantallas ───────────────────────────────────────────────────────

    public function index(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $registro = (new RegistroModel())->where('reserva_id', $ctx['reserva']['id'])->first();

        return view('portal/inicio', $ctx + [
            'registro'   => $registro,
            'noches'     => $this->noches($ctx['reserva']),
            'fase'       => $this->fase($ctx['reserva']),
            'pendientes' => (new SolicitudModel())->deReserva((int) $ctx['reserva']['id']),
            'minibar' => $this->hayMinibar($ctx['reserva']),
            // Los nombres de quienes vienen, no solo el número: ya están en la
            // base de datos desde el registro y no enseñarlos era desperdiciarlos.
            'acompanantes' => $registro === null
                ? []
                : (new AcompananteModel())->deRegistro((int) $registro['id']),
        ]);
    }

    /** Los consumos, casi en tiempo real: es el folio tal cual. */
    public function cuenta(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $folio       = new FolioModel();
        $reservaId   = (int) $ctx['reserva']['id'];

        return view('portal/cuenta', $ctx + [
            'movimientos' => $folio->movimientosDeReserva($reservaId),
            'saldo'       => $folio->saldo($reservaId),
            'pagado'      => $folio->totalPagado($reservaId),
            'descuentos'  => $folio->totalDescuentos($reservaId),
        ]);
    }

    /** Directorio, normas, rutas, cómo llegar y a quién llamar. */
    public function info(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $config = new ConfiguracionModel();

        return view('portal/info', $ctx + [
            // Todo esto lo escribe gerencia desde Administración. Si está
            // vacío, la tarjeta ni se pinta: mejor una pantalla más corta que
            // un apartado con un texto de relleno.
            'politica'        => $config->obtener('portal_politica_cancelacion', ''),
            'horarios'        => $config->obtener('portal_horarios', ''),
            'rutas'           => $config->obtener('portal_rutas', ''),
            'recomendaciones' => $config->obtener('portal_recomendaciones', ''),
            'normas'          => $config->obtener('portal_normas', ''),
            'servicios'       => (new ServicioModel())->where('activo', 1)->orderBy('grupo')->orderBy('orden')->findAll(),
        ]);
    }

    /**
     * Preferencias del huésped.
     *
     * De momento solo la autorización para escribirle. Está aquí y no enterrada
     * en el registro porque **un consentimiento que no se puede retirar con la
     * misma facilidad con que se dio es un consentimiento cojo** (Ley 1581/2012).
     */
    public function preferencias(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $consentimientos = new \App\Models\ConsentimientoModel();
        $huespedId       = (int) $ctx['reserva']['huesped_id'];

        return view('portal/preferencias', $ctx + [
            'registro' => (new RegistroModel())->where('reserva_id', $ctx['reserva']['id'])->first(),
            // Lo que él mismo ve marcado tiene que ser lo mismo que decide si
            // entra en una campaña. Si se leyera de sitios distintos, alguien
            // se daría de baja aquí y seguiría recibiendo correos.
            'permisos' => [
                'marketing' => $consentimientos->permite($huespedId, 'marketing', 'email'),
                'encuestas' => $consentimientos->permite($huespedId, 'encuestas', 'email'),
            ],
        ]);
    }

    public function guardarPreferencias(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $huespedId = (int) $ctx['reserva']['huesped_id'];
        $crm       = new \App\Libraries\Crm();

        $prueba = [
            'origen'      => 'portal',
            'ip'          => $this->request->getIPAddress(),
            'dispositivo' => $this->request->getUserAgent()->getAgentString(),
            'reserva_id'  => (int) $ctx['reserva']['id'],
            'nota'        => 'Lo cambió el propio huésped desde su portal.',
        ];

        // Solo se escribe cuando de verdad cambia algo. Guardar una fila cada
        // vez que alguien abre esta pantalla y le da a guardar llenaría el
        // libro de ruido y haría imposible encontrar el consentimiento bueno.
        $consentimientos = new \App\Models\ConsentimientoModel();

        foreach (['marketing' => 'campanas', 'encuestas' => 'encuestas'] as $finalidad => $campo) {
            $quiere = $this->request->getPost($campo) !== null;
            $tiene  = $consentimientos->permite($huespedId, $finalidad, 'email');

            if ($quiere === $tiene) {
                continue;
            }

            $quiere
                ? $crm->otorgar($huespedId, $finalidad, 'email', $prueba)
                : $crm->retirar($huespedId, $finalidad, 'email', $prueba);
        }

        // Se conserva la casilla vieja del registro para no romper el reporte
        // legal de llegada, que la mira. Manda la tabla de consentimientos.
        $registros = new RegistroModel();
        $registro  = $registros->where('reserva_id', $ctx['reserva']['id'])->first();

        if ($registro !== null) {
            $registros->update($registro['id'], [
                'acepta_marketing' => $this->request->getPost('campanas') !== null ? 1 : 0,
            ]);
        }

        return redirect()->to($this->ruta($token, 'preferencias'))->with('ok', lang('Portal.preferenciasOk'));
    }

    // ── Actividades ─────────────────────────────────────────────────────

    public function actividades(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        return view('portal/actividades', $ctx + [
            'experiencias' => (new ExperienciaModel())
                ->where('activa', 1)->where('publicada', 1)
                ->orderBy('categoria')->orderBy('nombre')->findAll(),
            'mias' => (new ExperienciaReservaModel())->deReserva((int) $ctx['reserva']['id']),
        ]);
    }

    /**
     * El huésped se apunta a una actividad.
     *
     * **No se confirma sola.** El aforo, el horario y si hay guía disponible los
     * sabe recepción, no este formulario. Entra como petición y alguien la
     * confirma; decirle «apuntado» y que luego no haya sitio es peor que
     * pedirle que espere diez minutos.
     */
    public function apuntarse(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $experiencia = (new ExperienciaModel())
            ->where('id', (int) $this->request->getPost('experiencia_id'))
            ->where('activa', 1)->where('publicada', 1)
            ->first();

        if ($experiencia === null) {
            return redirect()->to($this->ruta($token, 'actividades'))->with('error', lang('Portal.eligeQueNecesitas'));
        }

        (new SolicitudModel())->insert([
            'reserva_id'  => (int) $ctx['reserva']['id'],
            'unidad_id'   => $ctx['reserva']['unidad_id'] ?? null,
            'tipo'        => 'otro',
            'detalle'     => 'Actividad: ' . $experiencia['nombre']
                . ' · ' . max(1, (int) $this->request->getPost('personas')) . ' persona(s)',
            'para_cuando' => mb_substr(trim((string) $this->request->getPost('cuando')), 0, 100) ?: null,
            'estado'      => 'pendiente',
        ]);

        return redirect()->to($this->ruta($token, 'actividades'))->with('ok', lang('Portal.actividadPedida'));
    }

    // ── Carta y pedidos ─────────────────────────────────────────────────

    public function carta(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        // Solo se puede pedir estando aquí: ni antes de llegar ni después de irse
        if ($this->fase($ctx['reserva']) !== 'durante') {
            return redirect()->to($this->ruta($token))->with('error', lang('Portal.eligeQueNecesitas'));
        }

        // Solo lo que se sirve a esta hora: pedir una cena a las diez de la
        // mañana no lleva a nada bueno.
        $franja      = (new \App\Libraries\Planes())->franjaActual();
        $idsCategoria = array_column((new \App\Models\CartaCategoriaModel())->deFranja($franja), 'id');

        $productos = (new CartaProductoModel())
            ->select('carta_productos.*, carta_categorias.nombre AS categoria')
            ->join('carta_categorias', 'carta_categorias.id = carta_productos.categoria_id', 'left')
            ->whereIn('carta_productos.categoria_id', $idsCategoria ?: [0])
            ->where('carta_productos.disponible', 1)
            ->orderBy('carta_categorias.orden')
            ->orderBy('carta_productos.nombre')
            ->findAll();

        $porCategoria = [];
        foreach ($productos as $p) {
            $porCategoria[$p['categoria'] ?? '—'][] = $p;
        }

        return view('portal/carta', $ctx + [
            'porCategoria' => $porCategoria,
            'tope'         => $this->topeCargo(),
        ]);
    }

    /**
     * Manda el pedido a cocina y lo carga a la cuenta.
     *
     * Va dentro de una transacción: una comanda sin líneas es un pedido que
     * cocina ve vacío, y unas líneas sin comanda no las ve nadie.
     */
    public function pedido(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        if ($this->fase($ctx['reserva']) !== 'durante') {
            return redirect()->to($this->ruta($token))->with('error', lang('Portal.eligeQueNecesitas'));
        }

        $cantidades = (array) $this->request->getPost('cantidad');
        $productos  = new CartaProductoModel();
        $lineas     = [];
        $total      = 0.0;

        foreach ($cantidades as $productoId => $cantidad) {
            $cantidad = max(0, min(20, (int) $cantidad));
            if ($cantidad === 0) {
                continue;
            }

            // El precio se lee de la base, nunca del formulario: si viniera del
            // navegador, cualquiera podría pedirse la carta entera a un peso.
            $producto = $productos->where('id', (int) $productoId)->where('disponible', 1)->first();
            if ($producto === null) {
                continue;
            }

            $lineas[] = ['producto' => $producto, 'cantidad' => $cantidad];
            $total += (float) $producto['precio'] * $cantidad;
        }

        if ($lineas === []) {
            return redirect()->to($this->ruta($token, 'carta'))->with('error', lang('Portal.pedidoVacio'));
        }

        $tope = $this->topeCargo();
        if ($tope > 0 && $total > $tope) {
            return redirect()->to($this->ruta($token, 'carta'))->with('error', lang('Portal.pedidoTope'));
        }

        $db = db_connect();
        $db->transStart();

        $comandas = new ComandaModel();
        $donde    = $this->request->getPost('donde') === 'restaurante'
            ? 'Restaurante'
            : ($ctx['reserva']['unidad_nombre'] ?? 'Cabaña');

        $comandaId = (int) $comandas->insert([
            'numero'         => $comandas->generarNumero(),
            'mesa'           => $donde,
            'reserva_id'     => (int) $ctx['reserva']['id'],
            'cliente_nombre' => trim(($ctx['reserva']['huesped_nombre'] ?? '') . ' ' . ($ctx['reserva']['huesped_apellidos'] ?? '')),
            'estado'         => 'abierta',
            'total'          => $total,
            'forma_pago'     => 'habitacion',
        ], true);

        $modeloLineas = new ComandaLineaModel();
        foreach ($lineas as $l) {
            // Los campos se llaman `nombre_producto` y `precio_unitario`: con
            // otros nombres CodeIgniter los descarta en silencio por no estar
            // en `$allowedFields`, y la línea revienta por columna obligatoria.
            $modeloLineas->insert([
                'comanda_id'      => $comandaId,
                'producto_id'     => (int) $l['producto']['id'],
                'nombre_producto' => $l['producto']['nombre'],
                'cantidad'        => $l['cantidad'],
                'precio_unitario' => (float) $l['producto']['precio'],
                'destino'         => $l['producto']['destino'] ?? 'cocina',
            ]);
        }

        // Se manda a preparación con la misma lógica que el comandero, para que
        // cada cosa vaya a su sitio: la cerveza a barra y el plato a cocina.
        $modeloLineas->enviarAPreparacion($comandaId);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($this->ruta($token, 'carta'))->with('error', lang('Portal.pedidoTope'));
        }

        return redirect()->to($this->ruta($token, 'cuenta'))->with('ok', lang('Portal.pedidoEnviado'));
    }

    // ── Minibar ─────────────────────────────────────────────────────────

    /**
     * ¿Tiene minibar esta estancia?
     *
     * Hacen falta las dos cosas: que el alojamiento lo tenga encendido y que
     * **esta cabaña concreta** lo tenga. En un ecolodge de siete cabañas es
     * normal que unas tengan nevera y otras no, y enseñarle un minibar a quien
     * no lo tiene en la cabaña es una promesa que no se puede cumplir.
     */
    private function hayMinibar(array $reserva): bool
    {
        if ((new ConfiguracionModel())->obtener('portal_minibar', '0') !== '1') {
            return false;
        }

        if (empty($reserva['unidad_id'])) {
            return false;
        }

        $unidad = (new UnidadModel())->find((int) $reserva['unidad_id']);

        return $unidad !== null && (int) ($unidad['minibar'] ?? 0) === 1;
    }

    public function minibar(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        if (! $this->hayMinibar($ctx['reserva'])) {
            return redirect()->to($this->ruta($token));
        }

        return view('portal/minibar', $ctx + [
            'productos' => (new CartaProductoModel())
                ->where('en_minibar', 1)->where('disponible', 1)
                ->orderBy('nombre')->findAll(),
            'consumido' => (new FolioModel())
                ->where('reserva_id', (int) $ctx['reserva']['id'])
                ->like('concepto', 'Minibar', 'after')
                ->orderBy('id', 'DESC')->findAll(20),
        ]);
    }

    /**
     * El huésped declara lo que se ha tomado.
     *
     * Se carga a la cuenta en el acto y con su nombre en el concepto, para que
     * al salir no haya sorpresas ni discusiones sobre qué es cada línea. Es más
     * honesto que apuntarlo en un papel y cobrarlo al final.
     */
    public function declararMinibar(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        if (! $this->hayMinibar($ctx['reserva'])) {
            return redirect()->to($this->ruta($token));
        }

        $productos = new CartaProductoModel();
        $folio     = new FolioModel();
        $reservaId = (int) $ctx['reserva']['id'];
        $apuntados = 0;

        foreach ((array) $this->request->getPost('cantidad') as $productoId => $cantidad) {
            $cantidad = max(0, min(20, (int) $cantidad));
            if ($cantidad === 0) {
                continue;
            }

            // El precio, de la base. Nunca del formulario.
            $producto = $productos->where('id', (int) $productoId)
                ->where('en_minibar', 1)->where('disponible', 1)->first();

            if ($producto === null) {
                continue;
            }

            $folio->insert([
                'reserva_id' => $reservaId,
                'tipo'       => 'cargo',
                'concepto'   => 'Minibar: ' . $cantidad . ' × ' . $producto['nombre'],
                'valor'      => (float) $producto['precio'] * $cantidad,
            ]);
            $apuntados++;
        }

        if ($apuntados === 0) {
            return redirect()->to($this->ruta($token, 'minibar'))->with('error', lang('Portal.pedidoVacio'));
        }

        return redirect()->to($this->ruta($token, 'cuenta'))->with('ok', lang('Portal.minibarApuntado'));
    }

    /** Pedir que lo repongan. Va como petición, no como cargo. */
    public function reponerMinibar(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        if (! $this->hayMinibar($ctx['reserva'])) {
            return redirect()->to($this->ruta($token));
        }

        (new SolicitudModel())->insert([
            'reserva_id' => (int) $ctx['reserva']['id'],
            'unidad_id'  => $ctx['reserva']['unidad_id'] ?? null,
            'tipo'       => 'minibar',
            'detalle'    => mb_substr(trim((string) $this->request->getPost('detalle')), 0, 500) ?: null,
            'estado'     => 'pendiente',
        ]);

        return redirect()->to($this->ruta($token, 'minibar'))->with('ok', lang('Portal.recibida'));
    }

    // ── Comprobante ─────────────────────────────────────────────────────

    /**
     * La cuenta en una página imprimible.
     *
     * No es la factura electrónica —esa la emite Siigo y va con su numeración—
     * sino el justificante de lo consumido, que es lo que el huésped pide
     * cuando va a reclamarlo a su empresa.
     */
    public function comprobante(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $folio     = new FolioModel();
        $reservaId = (int) $ctx['reserva']['id'];

        return view('portal/comprobante', $ctx + [
            'movimientos' => $folio->movimientosDeReserva($reservaId),
            'saldo'       => $folio->saldo($reservaId),
            'pagado'      => $folio->totalPagado($reservaId),
            'descuentos'  => $folio->totalDescuentos($reservaId),
            'emitido'     => date('d/m/Y H:i'),
        ]);
    }

    /** Cuánto puede cargar el huésped a la cabaña sin que nadie lo apruebe. */
    private function topeCargo(): float
    {
        return (float) (new ConfiguracionModel())->obtener('portal_tope_cargo', '300000');
    }

    public function solicitudes(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        return view('portal/solicitudes', $ctx + [
            'tipos'   => SolicitudModel::TIPOS,
            'previas' => (new SolicitudModel())->deReserva((int) $ctx['reserva']['id']),
            'aviso'   => SolicitudModel::REQUIEREN_CONFIRMACION,
        ]);
    }

    public function pedir(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $reservaId   = (int) $ctx['reserva']['id'];
        $solicitudes = new SolicitudModel();

        // Freno sencillo: doce peticiones en un día no las hace nadie de buena fe
        if ($solicitudes->hoyDe($reservaId) >= self::TOPE_SOLICITUDES_DIA) {
            return redirect()->to($this->ruta($token, 'solicitudes'))
                ->with('error', lang('Portal.demasiadasSolicitudes'));
        }

        $tipo = (string) $this->request->getPost('tipo');
        if (! isset(SolicitudModel::TIPOS[$tipo])) {
            return redirect()->to($this->ruta($token, 'solicitudes'))
                ->with('error', lang('Portal.eligeQueNecesitas'));
        }

        $solicitudes->insert([
            'reserva_id'  => $reservaId,
            'unidad_id'   => $ctx['reserva']['unidad_id'] ?? null,
            'tipo'        => $tipo,
            'detalle'     => mb_substr(trim((string) $this->request->getPost('detalle')), 0, 1000) ?: null,
            'para_cuando' => mb_substr(trim((string) $this->request->getPost('para_cuando')), 0, 100) ?: null,
            'estado'      => 'pendiente',
            'prioridad'   => $this->request->getPost('urgente') ? 'urgente' : 'normal',
        ]);

        // Las que dependen de que haya sitio o de que haya quien conduzca no se
        // prometen: se dice que se mira. Prometerlas sería mentir.
        $mensaje = in_array($tipo, SolicitudModel::REQUIEREN_CONFIRMACION, true)
            ? lang('Portal.recibidaLaMiramos')
            : lang('Portal.recibida');

        return redirect()->to($this->ruta($token, 'solicitudes'))->with('ok', $mensaje);
    }

    /**
     * Gesto verde: renunciar al cambio de lencería de esta noche.
     *
     * Se pide por la noche que viene, no por la que pasó, y el vale no llega
     * hasta que quien limpia confirma que efectivamente no cambió nada.
     */
    public function verde(string $token)
    {
        $ctx = $this->contexto($token);

        if ($ctx === null) {
            return $this->caducado();
        }

        $verde   = new \App\Libraries\GestoVerde();
        $reserva = $ctx['reserva'];

        if (! $verde->activo()) {
            return redirect()->to($this->ruta($token, ''));
        }

        $gestos = new \App\Models\GestoVerdeModel();

        return view('portal/verde', $ctx + [
            'disponible' => $verde->disponible($reserva),
            'hoy'        => $gestos->deNoche((int) $reserva['id'], date('Y-m-d')),
            'historial'  => $gestos->deReserva((int) $reserva['id']),
            'vales'      => $gestos->valesDisponibles((int) $reserva['id']),
            'texto'      => (new \App\Models\ConfiguracionModel())->obtener('verde_texto', ''),
            'hora_tope'  => $verde->horaTope(),
            'estados'    => \App\Models\GestoVerdeModel::ESTADOS,
        ]);
    }

    public function pedirVerde(string $token)
    {
        $ctx = $this->contexto($token);

        if ($ctx === null) {
            return $this->caducado();
        }

        try {
            (new \App\Libraries\GestoVerde())->pedir((int) $ctx['reserva']['id'], 'portal');
        } catch (\RuntimeException $e) {
            return redirect()->to($this->ruta($token, 'verde'))->with('error', $e->getMessage());
        }

        return redirect()->to($this->ruta($token, 'verde'))->with(
            'ok',
            'Hecho. Hoy no tocamos tu ropa de cama ni tus toallas, y cuando el equipo lo confirme '
            . 'te aparecerá aquí tu invitación.'
        );
    }

    public function encuesta(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $momento = $this->momentoEncuesta($ctx['reserva']);

        return view('portal/encuesta', $ctx + [
            'momento'   => $momento,
            'apartados' => EncuestaModel::APARTADOS,
            'previa'    => (new EncuestaModel())->deReserva((int) $ctx['reserva']['id'], $momento),
        ]);
    }

    public function responderEncuesta(string $token)
    {
        $ctx = $this->contexto($token);
        if ($ctx === null) {
            return $this->caducado();
        }

        $guardada = (new EncuestaModel())->responder(
            (int) $ctx['reserva']['id'],
            $this->momentoEncuesta($ctx['reserva']),
            [
                'general'    => $this->request->getPost('general'),
                'limpieza'   => $this->request->getPost('limpieza'),
                'atencion'   => $this->request->getPost('atencion'),
                'comida'     => $this->request->getPost('comida'),
                'entorno'    => $this->request->getPost('entorno'),
                'comentario' => $this->request->getPost('comentario'),
                'publicable' => $this->request->getPost('publicable'),
            ]
        );

        return redirect()->to($this->ruta($token, 'encuesta'))
            ->with($guardada ? 'ok' : 'error', $guardada ? lang('Portal.graciasEncuesta') : lang('Portal.faltaLaNota'));
    }

    // ── Lo común ────────────────────────────────────────────────────────

    /**
     * Valida el token y devuelve todo lo que necesitan las vistas.
     *
     * @return array{acceso: array, reserva: array, token: string, hotel: object}|null
     */
    private function contexto(string $token): ?array
    {
        $acceso = $this->accesos->porToken($token);
        if ($acceso === null) {
            return null;
        }

        // Consulta propia y con `left join` a `unidades`: `consultaDetallada()`
        // usa un join interno, y una reserva a la que todavía no se le ha
        // asignado cabaña desaparecería. El huésped tiene reserva igual, y
        // enseñarle un enlace muerto sería lo peor que podría pasarle.
        $reserva = $this->reservas
            ->select('reservas.*, huespedes.nombre AS huesped_nombre, huespedes.apellidos AS huesped_apellidos,
                      huespedes.email AS huesped_email, unidades.nombre AS unidad_nombre,
                      tipos_unidad.nombre AS tipo_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->join('tipos_unidad', 'tipos_unidad.id = unidades.tipo_id', 'left')
            ->where('reservas.id', (int) $acceso['reserva_id'])
            ->first();

        if ($reserva === null || $reserva['estado'] === 'cancelada') {
            return null;
        }

        // El idioma del enlace manda sobre el del navegador: si reservó en
        // alemán, su portal está en alemán, entre desde donde entre.
        if (! empty($acceso['idioma'])) {
            service('request')->setLocale($acceso['idioma']);
        }

        $this->accesos->anotarUso((int) $acceso['id'], $this->request->getIPAddress());

        return [
            'acceso'  => $acceso,
            'reserva' => $reserva,
            'token'   => $token,
            'hotel'   => config('Hotel'),
        ];
    }

    private function caducado()
    {
        return view('portal/caducado', ['hotel' => config('Hotel')]);
    }

    private function ruta(string $token, string $seccion = ''): string
    {
        return 'estancia/' . $token . ($seccion !== '' ? '/' . $seccion : '');
    }

    private function noches(array $reserva): int
    {
        return (int) (new \DateTime($reserva['fecha_entrada']))
            ->diff(new \DateTime($reserva['fecha_salida']))->days;
    }

    /** Antes de llegar, durante, o ya se fue. Cambia lo que se le enseña. */
    private function fase(array $reserva): string
    {
        $hoy = date('Y-m-d');

        return match (true) {
            $hoy < $reserva['fecha_entrada'] => 'antes',
            $hoy >= $reserva['fecha_salida'] => 'despues',
            default                          => 'durante',
        };
    }

    /**
     * Cuál de las dos encuestas toca.
     *
     * Durante la estancia se pregunta para poder arreglar las cosas mientras el
     * huésped sigue aquí. Después, ya solo para aprender.
     */
    private function momentoEncuesta(array $reserva): string
    {
        return $this->fase($reserva) === 'durante' ? 'estancia' : 'salida';
    }
}
