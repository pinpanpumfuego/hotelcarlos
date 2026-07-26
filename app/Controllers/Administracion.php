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
                'sin_rol'         => (new \App\Models\EmpleadoModel())->where('activo', 1)
                    ->where('rol_tpv', 'ninguno')->countAllResults(),
                'con_rol'         => (new \App\Models\EmpleadoModel())->delTpv(),
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

    /** Ajustes del TPV compartido. */
    public function guardarTpv()
    {
        $this->config->guardarPares([
            'tpv_compartido'      => $this->request->getPost('compartido') !== null ? '1' : '0',
            'tpv_bloqueo_seg'     => (string) max(15, min(3600, (int) $this->request->getPost('bloqueo_seg'))),
            'tpv_descuento_libre' => (string) max(0, min(100, (float) $this->request->getPost('descuento_libre'))),
            'tpv_propina_sugerida' => (string) max(0, min(100, (float) $this->request->getPost('propina_sugerida'))),
        ]);

        return redirect()->to('administracion#tpv')->with('ok', 'Ajustes del TPV guardados.');
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

        $this->config->guardarPares($pares);

        return redirect()->to('administracion')->with('ok', 'Credenciales de Wompi guardadas.' . $aviso);
    }
}
