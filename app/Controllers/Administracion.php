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

            // Registro de correos enviados
            'correosLog'   => (new \App\Models\CorreoLogModel())->ultimos(20),
            'tiposCorreo'  => \App\Models\CorreoLogModel::TIPOS,

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
