<?php

namespace App\Controllers;

use App\Models\ConfiguracionModel;

/** Módulo de administración (solo gerencia): parámetros del sistema. */
class Administracion extends BaseController
{
    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->config = new ConfiguracionModel();
    }

    public function index()
    {
        $hotel = config('Hotel');

        return view('administracion/index', [
            'titulo'  => 'Administración',
            'seccion' => 'administracion',

            // Estado de lo que debe correr solo en el servidor
            'tareas'  => $this->tareas(),

            // Datos del hotel (con los valores por defecto del código como respaldo)
            'hotel' => [
                'nombre'    => $this->config->obtener('hotel_nombre', $hotel->nombre),
                'eslogan'   => $this->config->obtener('hotel_eslogan', $hotel->eslogan),
                'telefono'  => $this->config->obtener('hotel_telefono', $hotel->telefono),
                'whatsapp'  => $this->config->obtener('hotel_whatsapp', $hotel->whatsapp),
                'email'     => $this->config->obtener('hotel_email', $hotel->email),
                'direccion' => $this->config->obtener('hotel_direccion', $hotel->direccion),
            ],

            // Correo saliente
            'correo' => [
                'host'             => $this->config->obtener('correo_host', ''),
                'puerto'           => $this->config->obtener('correo_puerto', '587'),
                'usuario'          => $this->config->obtener('correo_usuario', ''),
                'cifrado'          => $this->config->obtener('correo_cifrado', 'tls'),
                'remitente_nombre' => $this->config->obtener('correo_remitente_nombre', $hotel->nombre),
                'clave_guardada'   => $this->config->existe('correo_clave'),
            ],

            // TPV compartido
            'tpv' => [
                'compartido'      => $this->config->obtener('tpv_compartido', '0') === '1',
                'bloqueo_seg'     => (int) $this->config->obtener('tpv_bloqueo_seg', '60'),
                'descuento_libre' => (float) $this->config->obtener('tpv_descuento_libre', '10'),
                'propina_sugerida' => (float) $this->config->obtener('tpv_propina_sugerida', '10'),
                'cocina_voz'      => $this->config->obtener('cocina_voz', '1') === '1',
                'cocina_escucha'  => $this->config->obtener('cocina_escucha', '0') === '1',
                'sin_rol'         => (new \App\Models\EmpleadoModel())->where('activo', 1)
                    ->where('rol_tpv', 'ninguno')->countAllResults(),
                'con_rol'         => (new \App\Models\EmpleadoModel())->delTpv(),
            ],

            // Portal del huésped: los textos que lee quien se aloja
            'portal' => [
                'horarios'        => $this->config->obtener('portal_horarios', ''),
                'normas'          => $this->config->obtener('portal_normas', ''),
                'politica'        => $this->config->obtener('portal_politica_cancelacion', ''),
                'rutas'           => $this->config->obtener('portal_rutas', ''),
                'recomendaciones' => $this->config->obtener('portal_recomendaciones', ''),
                'tope_cargo'      => (int) $this->config->obtener('portal_tope_cargo', '300000'),
                'minibar'         => $this->config->obtener('portal_minibar', '0') === '1',
                'cabanas_minibar' => (new \App\Models\UnidadModel())->where('minibar', 1)->countAllResults(),
                'productos_minibar' => (new \App\Models\CartaProductoModel())->where('en_minibar', 1)->countAllResults(),
            ],

            // Control de jornada
            'fichaje' => [
                'terminal' => $this->config->obtener('fichaje_terminal', '1') === '1',
                'foto'     => $this->config->obtener('fichaje_foto', '1') === '1',
                'movil'    => $this->config->obtener('fichaje_movil', '1') === '1',
                'radio'    => (int) $this->config->obtener('fichaje_radio_m', '0'),
                'latitud'  => $this->config->obtener('hotel_latitud', ''),
                'longitud' => $this->config->obtener('hotel_longitud', ''),
            ],

            // Registro de correos enviados
            'correosLog'   => (new \App\Models\CorreoLogModel())->ultimos(20),
            'tiposCorreo'  => \App\Models\CorreoLogModel::TIPOS,

            // Facturación electrónica con Siigo
            'siigo' => [
                'usuario_guardado'    => $this->config->existe('siigo_usuario'),
                'clave_guardada'      => $this->config->existe('siigo_access_key'),
                'partner_guardado'    => $this->config->existe('siigo_partner_id'),
                'documento_id'        => $this->config->obtener('siigo_documento_id', ''),
                'vendedor_id'         => $this->config->obtener('siigo_vendedor_id', ''),
                'pago_id'             => $this->config->obtener('siigo_pago_id', ''),
                'impuesto_id'         => $this->config->obtener('siigo_impuesto_id', ''),
                'codigo_alojamiento'  => $this->config->obtener('siigo_codigo_alojamiento', 'ALOJ'),
                'codigo_restaurante'  => $this->config->obtener('siigo_codigo_restaurante', 'REST'),
                'codigo_otros'        => $this->config->obtener('siigo_codigo_otros', 'OTROS'),
                'enviar_dian'         => $this->config->obtener('siigo_enviar_dian', '1') === '1',
                'enviar_correo'       => $this->config->obtener('siigo_enviar_correo', '1') === '1',
            ],
            'catalogos' => session()->getFlashdata('catalogos'),

            // Wompi
            'wompi' => [
                'ambiente'            => $this->config->obtener('wompi_ambiente', 'pruebas'),
                'llave_publica'       => $this->config->obtener('wompi_llave_publica', ''),
                'privada_guardada'    => $this->config->existe('wompi_llave_privada'),
                'integridad_guardada' => $this->config->existe('wompi_secreto_integridad'),
                'eventos_guardado'    => $this->config->existe('wompi_secreto_eventos'),
                'activo'              => $this->config->obtener('wompi_activo', '0') === '1',
                'anticipo_pct'        => (int) $this->config->obtener('wompi_anticipo_pct', '0'),
                'revision'            => (new \App\Libraries\Wompi())->revisarLlaves(),
            ],

            // Mantenimiento
            'mantenimiento' => [
                'sla' => [
                    'urgente' => (int) $this->config->obtener('mant_sla_urgente', '4'),
                    'alta'    => (int) $this->config->obtener('mant_sla_alta', '24'),
                    'media'   => (int) $this->config->obtener('mant_sla_media', '72'),
                    'baja'    => (int) $this->config->obtener('mant_sla_baja', '168'),
                ],
                'costo_hora' => (float) $this->config->obtener('mant_costo_hora', '0'),
                'verifica'   => $this->config->obtener('mant_verifica_urgentes', '1') === '1',
                'prefijo'    => $this->config->obtener('mant_prefijo', 'SAL'),
            ],
        ]);
    }

    public function guardarHotel()
    {
        $nombre = trim((string) $this->request->getPost('nombre'));
        if ($nombre === '') {
            return redirect()->to('administracion')->with('error', 'El nombre del hotel es obligatorio.');
        }

        $this->config->guardarPares([
            'hotel_nombre'    => $nombre,
            'hotel_eslogan'   => trim((string) $this->request->getPost('eslogan')),
            'hotel_telefono'  => trim((string) $this->request->getPost('telefono')),
            'hotel_whatsapp'  => preg_replace('/\D/', '', (string) $this->request->getPost('whatsapp')),
            'hotel_email'     => strtolower(trim((string) $this->request->getPost('email'))),
            'hotel_direccion' => trim((string) $this->request->getPost('direccion')),
        ]);

        return redirect()->to('administracion')->with('ok', 'Datos del hotel guardados: la web pública y el panel ya los muestran.');
    }

    /**
     * Estado de las tareas que deben correr solas en el servidor.
     *
     * Se enseña aquí porque una tarea programada que deja de funcionar no
     * avisa: simplemente los datos se quedan viejos y nadie se entera hasta
     * que un huésped reserva una noche ya vendida en Booking.
     */
    private function tareas(): array
    {
        $cambios = (new \App\Models\TipoCambioModel())->listado();
        $ultimo  = null;
        foreach ($cambios as $c) {
            if ($ultimo === null || $c['updated_at'] > $ultimo) {
                $ultimo = $c['updated_at'];
            }
        }

        $horas = $ultimo !== null ? (time() - strtotime($ultimo)) / 3600 : null;

        // En palabras, que «hace 214 horas» no se lee de un vistazo
        $antiguedad = null;
        if ($horas !== null) {
            $antiguedad = match (true) {
                $horas < 1  => 'hace unos minutos',
                $horas < 2  => 'hace una hora',
                $horas < 48 => 'hace ' . (int) $horas . ' horas',
                default     => 'hace ' . (int) ($horas / 24) . ' días',
            };
        }

        return [
            'cambios'    => $cambios,
            'ultimo'     => $ultimo,
            // Se trae a diario: pasadas 48 horas algo va mal
            'caducado'   => $ultimo === null || $horas > 48,
            'antiguedad' => $antiguedad,
            // La ruta real de este servidor, para copiar y pegar en el panel
            'ordenes'   => [
                [
                    'que'    => 'Tipo de cambio de las divisas',
                    'cuando' => 'Una vez al día',
                    'orden'  => 'php ' . ROOTPATH . 'spark cambio:actualizar',
                ],
                [
                    'que'    => 'Fechas ocupadas en Booking y Airbnb',
                    'cuando' => 'Cada hora',
                    'orden'  => 'php ' . ROOTPATH . 'spark canales:sincronizar',
                ],
            ],
        ];
    }

    /**
     * Lanza la actualización del cambio desde el panel.
     *
     * Sirve para dos cosas: sacar de un apuro cuando la tarea programada
     * falló, y comprobar que la orden funciona en este servidor antes de
     * programarla.
     */
    public function actualizarCambio()
    {
        // CodeIgniter define STDOUT cuando una orden se lanza desde un
        // controlador, pero se olvida de STDERR. Sin esto, en cuanto la orden
        // escribe un error (justo el caso que más interesa ver) el panel
        // revienta. Y tiene que ser un recurso de verdad, no la cadena
        // 'php://output': CodeIgniter se lo pasa a las funciones que miran si
        // la consola admite color, y esas exigen recurso.
        if (! defined('STDERR')) {
            define('STDERR', fopen('php://output', 'w'));
        }

        try {
            // `command()` ya captura la salida y la devuelve; no hay que
            // envolverla en otro ob_start()
            $salida = (string) command('cambio:actualizar');
        } catch (\Throwable $e) {
            return redirect()->to('administracion#tareas')
                ->with('error', 'No se pudo actualizar: ' . $e->getMessage());
        }

        // Quitar los colores de consola, que aquí solo son basura
        $salida = trim(preg_replace('/\033\[[0-9;]*m/', '', $salida));
        $salida = trim(preg_replace('/\s*\n\s*/', ' · ', $salida));

        $fallo = str_contains($salida, 'No se pudo consultar')
            || (new \App\Models\TipoCambioModel())->vigentes() === [];

        return redirect()->to('administracion#tareas')->with(
            $fallo ? 'error' : 'ok',
            $fallo
                ? 'No se pudo traer el cambio. Lo más probable es que este servidor no tenga salida a internet. ' . $salida
                : 'Tipo de cambio actualizado. ' . $salida
        );
    }

    /** Ajustes del TPV compartido. */
    public function guardarTpv()
    {
        $this->config->guardarPares([
            'tpv_compartido'      => $this->request->getPost('compartido') !== null ? '1' : '0',
            'tpv_bloqueo_seg'     => (string) max(15, min(3600, (int) $this->request->getPost('bloqueo_seg'))),
            'tpv_descuento_libre' => (string) max(0, min(100, (float) $this->request->getPost('descuento_libre'))),
            'tpv_propina_sugerida' => (string) max(0, min(100, (float) $this->request->getPost('propina_sugerida'))),
            'cocina_voz'          => $this->request->getPost('cocina_voz') !== null ? '1' : '0',
            'cocina_escucha'      => $this->request->getPost('cocina_escucha') !== null ? '1' : '0',
        ]);

        return redirect()->to('administracion#tpv')->with('ok', 'Ajustes del TPV guardados.');
    }

    /**
     * Textos y límites del portal del huésped.
     *
     * Todo esto lo escribe gerencia, no yo: las rutas seguras, los horarios
     * reales y qué recomendar de la zona los sabe quien vive allí. Lo que está
     * vacío no se pinta en el portal, así que dejarlo a medias no rompe nada.
     */
    public function guardarPortal()
    {
        $this->config->guardarPares([
            'portal_horarios'              => trim((string) $this->request->getPost('horarios')),
            'portal_normas'                => trim((string) $this->request->getPost('normas')),
            'portal_politica_cancelacion'  => trim((string) $this->request->getPost('politica')),
            'portal_rutas'                 => trim((string) $this->request->getPost('rutas')),
            'portal_recomendaciones'       => trim((string) $this->request->getPost('recomendaciones')),
            // Hasta cuánto puede cargar el huésped a la cabaña sin que nadie lo
            // apruebe. En 0 se apagan los pedidos desde el portal.
            'portal_tope_cargo'            => (string) max(0, (int) $this->request->getPost('tope_cargo')),
            // Si el alojamiento no tiene minibar, no aparece por ninguna parte
            'portal_minibar'               => $this->request->getPost('minibar') !== null ? '1' : '0',
        ]);

        return redirect()->to('administracion#portal')->with('ok', 'Textos del portal del huésped guardados.');
    }

    /** Ajustes del control de jornada. */
    public function guardarFichaje()
    {
        $radio = max(0, (int) $this->request->getPost('radio'));

        $this->config->guardarPares([
            'fichaje_terminal' => $this->request->getPost('terminal') !== null ? '1' : '0',
            'fichaje_foto'     => $this->request->getPost('foto') !== null ? '1' : '0',
            'fichaje_movil'    => $this->request->getPost('movil') !== null ? '1' : '0',
            'fichaje_radio_m'  => (string) $radio,
            'hotel_latitud'    => trim((string) $this->request->getPost('latitud')),
            'hotel_longitud'   => trim((string) $this->request->getPost('longitud')),
        ]);

        return redirect()->to('administracion#fichaje')->with('ok', 'Ajustes de fichaje guardados.');
    }

    public function guardarCorreo()
    {
        $pares = [
            'correo_host'             => trim((string) $this->request->getPost('host')),
            'correo_puerto'           => (string) (int) $this->request->getPost('puerto'),
            'correo_usuario'          => trim((string) $this->request->getPost('usuario')),
            'correo_cifrado'          => in_array($this->request->getPost('cifrado'), ['tls', 'ssl'], true) ? $this->request->getPost('cifrado') : 'tls',
            'correo_remitente_nombre' => trim((string) $this->request->getPost('remitente_nombre')),
        ];

        // La contraseña solo se toca si se escribe algo (en blanco = conservar la actual)
        $clave = (string) $this->request->getPost('clave');
        if ($clave !== '') {
            $pares['correo_clave'] = $clave;
        }

        $this->config->guardarPares($pares);

        return redirect()->to('administracion')->with('ok', 'Parámetros de correo guardados.');
    }

    public function probarCorreo()
    {
        $configCorreo = $this->config->configCorreo();
        if ($configCorreo === null) {
            return redirect()->to('administracion')->with('error', 'Configura primero el servidor de correo.');
        }

        $destino = trim((string) $this->request->getPost('destino'));
        if (! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to('administracion')->with('error', 'Indica un correo de destino válido.');
        }

        $email = service('email');
        $email->initialize($configCorreo);
        $email->setFrom($this->config->obtener('correo_usuario', ''), $this->config->obtener('correo_remitente_nombre', config('Hotel')->nombre));
        $email->setTo($destino);
        $email->setSubject('Correo de prueba · ' . config('Hotel')->nombre);
        $email->setMessage('<p>¡Funciona! Este es un correo de prueba del sistema de gestión de <strong>' . esc(config('Hotel')->nombre) . '</strong>.</p>');

        if ($email->send(false)) {
            return redirect()->to('administracion')->with('ok', 'Correo de prueba enviado a ' . $destino . '. Revisa la bandeja de entrada (y el spam).');
        }

        log_message('error', 'Fallo de correo de prueba: {debug}', ['debug' => $email->printDebugger(['headers'])]);

        return redirect()->to('administracion')->with('error', 'No se pudo enviar. Revisa servidor, puerto, usuario y contraseña. El detalle técnico quedó en el registro de errores.');
    }

    /** Credenciales y parámetros de facturación electrónica. */
    public function guardarSiigo()
    {
        $pares = [
            'siigo_documento_id'       => trim((string) $this->request->getPost('documento_id')),
            'siigo_vendedor_id'        => trim((string) $this->request->getPost('vendedor_id')),
            'siigo_pago_id'            => trim((string) $this->request->getPost('pago_id')),
            'siigo_impuesto_id'        => trim((string) $this->request->getPost('impuesto_id')),
            'siigo_codigo_alojamiento' => trim((string) $this->request->getPost('codigo_alojamiento')) ?: 'ALOJ',
            'siigo_codigo_restaurante' => trim((string) $this->request->getPost('codigo_restaurante')) ?: 'REST',
            'siigo_codigo_otros'       => trim((string) $this->request->getPost('codigo_otros')) ?: 'OTROS',
            'siigo_enviar_dian'        => $this->request->getPost('enviar_dian') ? '1' : '0',
            'siigo_enviar_correo'      => $this->request->getPost('enviar_correo') ? '1' : '0',
        ];

        // Las credenciales solo se tocan si se escriben: en blanco = conservar
        foreach (['usuario' => 'siigo_usuario', 'access_key' => 'siigo_access_key', 'partner_id' => 'siigo_partner_id'] as $campo => $clave) {
            $valor = (string) $this->request->getPost($campo);
            if ($valor !== '') {
                $pares[$clave] = trim($valor);
                $pares['siigo_token'] = '';           // credencial nueva: se descarta el token viejo
                $pares['siigo_token_caduca'] = '0';
            }
        }

        $this->config->guardarPares($pares);

        return redirect()->to('administracion')->with('ok', 'Configuración de facturación guardada.');
    }

    /** Comprueba la conexión con Siigo y trae los catálogos para elegir. */
    public function probarSiigo()
    {
        $siigo = new \App\Libraries\Siigo();

        if (! $siigo->configurado()) {
            return redirect()->to('administracion')->with('error', 'Faltan las credenciales de Siigo.');
        }

        $prueba = $siigo->probar();
        if (! $prueba['ok']) {
            return redirect()->to('administracion')->with('error', 'No se pudo conectar con Siigo: ' . $prueba['error']);
        }

        // Con la conexión viva, se traen los catálogos que hay que configurar
        $catalogos = [
            'documentos' => $prueba['datos'],
            'pagos'      => ($siigo->tiposPago()['datos'] ?? []),
            'vendedores' => ($siigo->vendedores()['datos']['results'] ?? $siigo->vendedores()['datos'] ?? []),
            'impuestos'  => ($siigo->impuestos()['datos'] ?? []),
        ];

        return redirect()->to('administracion')
            ->with('ok', 'Conexión con Siigo correcta. Abajo puedes elegir los parámetros de facturación.')
            ->with('catalogos', $catalogos);
    }

    public function guardarWompi()
    {
        $publica = trim((string) $this->request->getPost('llave_publica'));
        $aviso   = '';
        if ($publica !== '' && ! preg_match('/^pub_(test|prod)_/', $publica)) {
            $aviso = ' Aviso: la llave pública normalmente empieza por pub_test_ o pub_prod_ — revísala.';
        }

        $pares = [
            'wompi_ambiente'      => $this->request->getPost('ambiente') === 'produccion' ? 'produccion' : 'pruebas',
            'wompi_llave_publica' => $publica,
        ];

        $privada = (string) $this->request->getPost('llave_privada');
        if ($privada !== '') {
            $pares['wompi_llave_privada'] = trim($privada);
        }
        $integridad = (string) $this->request->getPost('secreto_integridad');
        if ($integridad !== '') {
            $pares['wompi_secreto_integridad'] = trim($integridad);
        }

        // El secreto de eventos es el que permite comprobar que un aviso de
        // pago viene de verdad de Wompi. Sin él, el webhook rechaza todo.
        $eventos = (string) $this->request->getPost('secreto_eventos');
        if ($eventos !== '') {
            $pares['wompi_secreto_eventos'] = trim($eventos);
        }

        $pares['wompi_activo'] = $this->request->getPost('activo') !== null ? '1' : '0';
        $pares['wompi_anticipo_pct'] = (string) max(0, min(100, (int) $this->request->getPost('anticipo_pct')));

        $this->config->guardarPares($pares);

        // Se avisa de las llaves que no cuadran con el ambiente elegido: pegar
        // una de pruebas en producción significa que nadie cobra nada de verdad.
        $fallos = (new \App\Libraries\Wompi())->revisarLlaves();
        if ($fallos !== []) {
            $aviso .= ' ' . implode(' ', $fallos);
        }

        return redirect()->to('administracion#wompi')->with(
            $fallos === [] ? 'ok' : 'error',
            'Credenciales de Wompi guardadas.' . $aviso
        );
    }

    /** Comprueba que la pasarela responde con las llaves guardadas. */
    public function probarWompi()
    {
        $r = (new \App\Libraries\Wompi())->probar();

        return redirect()->to('administracion#wompi')->with($r['ok'] ? 'ok' : 'error', $r['mensaje']);
    }

    /**
     * Plazos internos, coste de la hora y doble firma.
     *
     * Los plazos no son una promesa a nadie de fuera: sirven para que en el
     * tablero se vea de un vistazo qué se está quedando atrás, que es lo que
     * de verdad pasa. No que nadie arregle nada, sino que algo lleve tres
     * semanas ahí sin que nadie se dé cuenta.
     */
    public function guardarMantenimiento()
    {
        $pares = [];

        foreach (['urgente' => 4, 'alta' => 24, 'media' => 72, 'baja' => 168] as $prioridad => $porDefecto) {
            $horas = (int) $this->request->getPost('sla_' . $prioridad);
            // Entre una hora y un mes: un plazo de cero minutos deja todo
            // marcado en rojo desde el primer segundo y deja de significar nada.
            $pares['mant_sla_' . $prioridad] = (string) ($horas > 0 ? min($horas, 720) : $porDefecto);
        }

        $pares['mant_costo_hora'] = (string) max(0, (float) $this->request->getPost('costo_hora'));
        $pares['mant_verifica_urgentes'] = $this->request->getPost('verifica') !== null ? '1' : '0';

        $anterior = (string) $this->config->obtener('mant_prefijo', 'SAL');

        $prefijo = strtoupper(trim((string) $this->request->getPost('prefijo')));
        $prefijo = preg_replace('/[^A-Z0-9]/', '', $prefijo);

        if ($prefijo !== '') {
            $prefijo                 = mb_substr($prefijo, 0, 6);
            $pares['mant_prefijo']   = $prefijo;
        }

        // El aviso se decide antes de guardar: después, el valor viejo ya no
        // está y la comparación siempre daría que no cambió nada.
        $aviso = $prefijo !== '' && $prefijo !== $anterior
            ? ' Los equipos ya dados de alta conservan su código: sus etiquetas están pegadas.'
            : '';

        $this->config->guardarPares($pares);

        return redirect()->to('administracion#mantenimiento')
            ->with('ok', 'Ajustes de mantenimiento guardados.' . $aviso);
    }
}
