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

        $motor    = new \App\Libraries\MotorTarifas();
        $opciones = [];

        foreach ($this->tipos->findAll() as $tipo) {
            if ((int) $tipo['capacidad'] < $personas) {
                continue;
            }
            $libres = $this->reservas->unidadesLibresDelTipo((int) $tipo['id'], $datos['entrada'], $datos['salida']);
            if ($libres !== []) {
                $cotizacion = $motor->cotizar((int) $tipo['id'], $datos['entrada'], $datos['salida'], $datos['adultos'], $datos['ninos']);
                $opciones[] = [
                    'tipo'       => $tipo,
                    'libres'     => count($libres),
                    'total'      => $cotizacion['total'],
                    'porNoche'   => $cotizacion['media_noche'],
                    'cotizacion' => $cotizacion,
                    'galeria'    => $this->galeriaDelTipo((int) $tipo['id'], $libres),
                    'servicios'  => (new \App\Models\ServicioModel())->fichaDeTipo((int) $tipo['id']),
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

        $noches     = (new \DateTime($datos['entrada']))->diff(new \DateTime($datos['salida']))->days;
        $cotizacion = (new \App\Libraries\MotorTarifas())
            ->cotizar((int) $tipo['id'], $datos['entrada'], $datos['salida'], $datos['adultos'], $datos['ninos']);

        // Cupón opcional: se comprueba aquí para que el huésped vea el descuento antes de confirmar
        $cupon      = trim((string) $this->request->getPost('cupon'));
        $descuento  = 0.0;
        $cuponError = null;

        if ($cupon !== '') {
            $r = (new \App\Models\CuponModel())->validar($cupon, 'web', 'alojamiento', $cotizacion['total']);
            if ($r['ok']) {
                $descuento = $r['descuento'];
            } else {
                $cuponError = $r['mensaje'];
                $cupon      = '';
            }
        }

        return view('web/reservar_datos', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => 'Tus datos',
            'paginaActiva' => 'reservar',
            'busqueda'     => $datos,
            'tipo'         => $tipo,
            'noches'       => $noches,
            'total'        => $cotizacion['total'],
            'cotizacion'   => $cotizacion,
            'cupon'        => $cupon,
            'descuento'    => $descuento,
            'cuponError'   => $cuponError,
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

        $codigo = $this->reservas->generarCodigo();
        $motor  = new \App\Libraries\MotorTarifas();

        // El precio se congela aquí: si mañana cambian las reglas, esta reserva no cambia
        $cotizacion = $motor->cotizar(
            (int) $tipo['id'],
            $busqueda['entrada'],
            $busqueda['salida'],
            $busqueda['adultos'],
            $busqueda['ninos']
        );

        $reservaId = $this->reservas->insert([
            'codigo'          => $codigo,
            'huesped_id'      => $huespedId,
            'unidad_id'       => $libres[0]['id'],
            'fecha_entrada'   => $busqueda['entrada'],
            'fecha_salida'    => $busqueda['salida'],
            'adultos'         => $busqueda['adultos'],
            'ninos'           => $busqueda['ninos'],
            'estado'          => 'pendiente',
            'total'           => $cotizacion['total'],
            'desglose_precio' => $motor->resumenParaGuardar($cotizacion),
            'notas'           => 'Reserva creada desde la web. ' . trim((string) $this->request->getPost('comentarios')),
        ]);

        // Cupón: se descuenta en el folio, no en el precio del alojamiento,
        // para que quede claro cuánto valía la estancia y cuánto se rebajó.
        $cupon = trim((string) $this->request->getPost('cupon'));
        if ($cupon !== '' && $reservaId) {
            $cupones = new \App\Models\CuponModel();
            $r       = $cupones->validar($cupon, 'web', 'alojamiento', $cotizacion['total'], (int) $huespedId);

            if ($r['ok']) {
                $folio = new \App\Models\FolioModel();
                $folio->asegurarCargoAlojamiento([
                    'id'            => $reservaId,
                    'total'         => $cotizacion['total'],
                    'fecha_entrada' => $busqueda['entrada'],
                    'fecha_salida'  => $busqueda['salida'],
                ]);
                $folio->insert([
                    'reserva_id' => $reservaId,
                    'tipo'       => 'descuento',
                    'concepto'   => 'Descuento cupón ' . $r['cupon']['codigo'],
                    'valor'      => $r['descuento'],
                ]);

                $cupones->registrarUso($r['cupon'], [
                    'reserva_id' => $reservaId,
                    'huesped_id' => $huespedId,
                    'base'       => $cotizacion['total'],
                    'descuento'  => $r['descuento'],
                    'canal'      => 'web',
                ]);
            }
        }

        // Aviso interno: el hotel debe saber que hay una reserva por confirmar
        $completa = $this->reservas
            ->select('reservas.*, huespedes.nombre, huespedes.apellidos, huespedes.email, huespedes.telefono,
                      unidades.nombre AS unidad_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->where('reservas.id', $reservaId)
            ->first();

        if ($completa !== null) {
            (new \App\Libraries\Correo())->avisoReservaWeb($completa);
        }

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
            'descuento'    => (new \App\Models\FolioModel())->totalDescuentos((int) $reserva['id']),
        ]);
    }

    /**
     * Galería de un tipo con las fotos propias de las cabañas libres.
     * Solo se enseña lo que el huésped podría llegar a ocupar.
     */
    private function galeriaDelTipo(int $tipoId, array $unidadesLibres): array
    {
        $medios  = new \App\Models\MedioModel();
        $galeria = $medios->deTipo($tipoId);

        $porCabana = $medios->publicasDeUnidades(array_map('intval', array_column($unidadesLibres, 'id')));

        foreach ($unidadesLibres as $u) {
            foreach ($porCabana[(int) $u['id']] ?? [] as $m) {
                $m['alt']  = trim(($m['alt'] ?? '') !== '' ? $u['nombre'] . ' · ' . $m['alt'] : $u['nombre']);
                $galeria[] = $m;
            }
        }

        return $galeria;
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
