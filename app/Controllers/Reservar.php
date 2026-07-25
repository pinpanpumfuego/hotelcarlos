<?php

namespace App\Controllers;

use App\Models\HuespedModel;
use App\Models\ReservaModel;
use App\Models\TipoUnidadModel;

/** Motor de reservas de la web pública (sin iniciar sesión). */
class Reservar extends BaseController
{
    private ReservaModel $reservas;
    private TipoUnidadModel $tipos;

    public function __construct()
    {
        $this->reservas = new ReservaModel();
        $this->tipos    = new TipoUnidadModel();
    }

    /** Paso 1: elegir fechas y personas. */
    public function index()
    {
        return view('web/reservar', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => 'Reservar',
            'paginaActiva' => 'reservar',
            'descripcion'  => 'Consulta disponibilidad y reserva tu cabaña en línea.',
        ]);
    }

    /** Paso 2: mostrar disponibilidad real y precio total. */
    public function disponibilidad()
    {
        $datos = $this->recogerBusqueda();
        $error = $this->validarBusqueda($datos);
        if ($error !== null) {
            return redirect()->to('reservar')->withInput()->with('error', $error);
        }

        $noches   = (new \DateTime($datos['entrada']))->diff(new \DateTime($datos['salida']))->days;
        $personas = $datos['adultos'] + $datos['ninos'];

        $opciones = [];
        foreach ($this->tipos->findAll() as $tipo) {
            if ((int) $tipo['capacidad'] < $personas) {
                continue;
            }
            $libres = $this->reservas->unidadesLibresDelTipo((int) $tipo['id'], $datos['entrada'], $datos['salida']);
            if ($libres !== []) {
                $opciones[] = [
                    'tipo'   => $tipo,
                    'libres' => count($libres),
                    'total'  => $noches * (float) $tipo['tarifa_base'],
                ];
            }
        }

        return view('web/reservar_opciones', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => 'Disponibilidad',
            'paginaActiva' => 'reservar',
            'busqueda'     => $datos,
            'noches'       => $noches,
            'opciones'     => $opciones,
        ]);
    }

    /** Paso 3: datos del huésped. */
    public function datos()
    {
        $datos = $this->recogerBusqueda();
        $error = $this->validarBusqueda($datos);
        if ($error !== null) {
            return redirect()->to('reservar')->with('error', $error);
        }

        $tipo = $this->tipos->find((int) $this->request->getPost('tipo_id'));
        if ($tipo === null) {
            return redirect()->to('reservar')->with('error', 'El tipo de alojamiento no existe.');
        }

        $noches = (new \DateTime($datos['entrada']))->diff(new \DateTime($datos['salida']))->days;

        return view('web/reservar_datos', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => 'Tus datos',
            'paginaActiva' => 'reservar',
            'busqueda'     => $datos,
            'tipo'         => $tipo,
            'noches'       => $noches,
            'total'        => $noches * (float) $tipo['tarifa_base'],
        ]);
    }

    /** Paso 4: confirmar y crear la reserva. */
    public function confirmar()
    {
        // Antispam: límite por IP y campo señuelo que los robots rellenan
        $throttler = service('throttler');
        if ($throttler->check('reservar-' . md5($this->request->getIPAddress()), 5, MINUTE) === false) {
            return redirect()->to('reservar')->with('error', 'Demasiados intentos. Espera un minuto.');
        }
        if ((string) $this->request->getPost('sitioweb') !== '') {
            return redirect()->to('reservar');
        }

        $busqueda = $this->recogerBusqueda();
        $error    = $this->validarBusqueda($busqueda);
        if ($error !== null) {
            return redirect()->to('reservar')->with('error', $error);
        }

        $tipo = $this->tipos->find((int) $this->request->getPost('tipo_id'));
        if ($tipo === null) {
            return redirect()->to('reservar')->with('error', 'El tipo de alojamiento no existe.');
        }

        // Validación de los datos personales
        $reglas = [
            'nombre'        => 'required|min_length[2]|max_length[100]',
            'apellidos'     => 'required|min_length[2]|max_length[150]',
            'num_documento' => 'required|min_length[4]|max_length[50]',
            'email'         => 'required|valid_email',
            'telefono'      => 'required|min_length[7]|max_length[30]',
            'acepta'        => 'required',
        ];
        if (! $this->validateData($this->request->getPost(), $reglas)) {
            return redirect()->back()->withInput()->with('error', 'Revisa los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        // Re-comprobar disponibilidad justo antes de crear (evita carreras)
        $libres = $this->reservas->unidadesLibresDelTipo((int) $tipo['id'], $busqueda['entrada'], $busqueda['salida']);
        if ($libres === []) {
            return redirect()->to('reservar')->with('error', 'Lo sentimos: la última cabaña disponible se acaba de reservar. Prueba otras fechas.');
        }

        // Crear o reutilizar el huésped por documento
        $huespedes = new HuespedModel();
        $huesped   = $huespedes->where('num_documento', trim((string) $this->request->getPost('num_documento')))->first();

        $datosHuesped = [
            'nombre'         => trim((string) $this->request->getPost('nombre')),
            'apellidos'      => trim((string) $this->request->getPost('apellidos')),
            'tipo_documento' => in_array($this->request->getPost('tipo_documento'), ['CC', 'CE', 'PASAPORTE', 'TI', 'OTRO'], true)
                ? $this->request->getPost('tipo_documento') : 'CC',
            'num_documento'  => trim((string) $this->request->getPost('num_documento')),
            'telefono'       => trim((string) $this->request->getPost('telefono')),
            'email'          => strtolower(trim((string) $this->request->getPost('email'))),
        ];

        if ($huesped === null) {
            $huespedId = $huespedes->insert($datosHuesped);
        } else {
            $huespedId = $huesped['id'];
            $huespedes->update($huespedId, $datosHuesped);
        }

        $noches = (new \DateTime($busqueda['entrada']))->diff(new \DateTime($busqueda['salida']))->days;
        $codigo = $this->reservas->generarCodigo();

        $this->reservas->insert([
            'codigo'        => $codigo,
            'huesped_id'    => $huespedId,
            'unidad_id'     => $libres[0]['id'],
            'fecha_entrada' => $busqueda['entrada'],
            'fecha_salida'  => $busqueda['salida'],
            'adultos'       => $busqueda['adultos'],
            'ninos'         => $busqueda['ninos'],
            'estado'        => 'pendiente',
            'total'         => $noches * (float) $tipo['tarifa_base'],
            'notas'         => 'Reserva creada desde la web. ' . trim((string) $this->request->getPost('comentarios')),
        ]);

        return redirect()->to('reservar/exito/' . $codigo);
    }

    /** Confirmación final con el código de reserva. */
    public function exito(string $codigo)
    {
        $reserva = $this->reservas
            ->select('reservas.*, huespedes.nombre AS huesped_nombre, huespedes.email AS huesped_email, unidades.nombre AS unidad_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->where('reservas.codigo', $codigo)
            ->first();

        if ($reserva === null) {
            return redirect()->to('reservar');
        }

        return view('web/reservar_exito', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => 'Reserva recibida',
            'paginaActiva' => 'reservar',
            'reserva'      => $reserva,
        ]);
    }

    private function recogerBusqueda(): array
    {
        return [
            'entrada' => (string) ($this->request->getPost('entrada') ?? $this->request->getGet('entrada')),
            'salida'  => (string) ($this->request->getPost('salida') ?? $this->request->getGet('salida')),
            'adultos' => max(1, (int) ($this->request->getPost('adultos') ?? 2)),
            'ninos'   => max(0, (int) ($this->request->getPost('ninos') ?? 0)),
        ];
    }

    private function validarBusqueda(array $d): ?string
    {
        $entrada = \DateTime::createFromFormat('Y-m-d', $d['entrada']);
        $salida  = \DateTime::createFromFormat('Y-m-d', $d['salida']);

        if ($entrada === false || $salida === false) {
            return 'Elige las fechas de entrada y salida.';
        }
        if ($entrada->format('Y-m-d') < date('Y-m-d')) {
            return 'La fecha de entrada no puede ser anterior a hoy.';
        }
        if ($salida <= $entrada) {
            return 'La fecha de salida debe ser posterior a la de entrada.';
        }
        if ($entrada->diff($salida)->days > 30) {
            return 'Para estancias de más de 30 noches, contáctanos directamente.';
        }

        return null;
    }
}
