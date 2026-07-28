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
    private \App\Libraries\Traductor $t;

    public function __construct()
    {
        $this->reservas = new ReservaModel();
        $this->tipos    = new TipoUnidadModel();
        // El motor de reservas es parte de la web pública, así que también
        // traduce el contenido: es justo donde un extranjero decide si paga
        $this->t = new \App\Libraries\Traductor();
    }

    /** Paso 1: elegir fechas y personas. */
    public function index()
    {
        return view('web/reservar', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => lang('Web.reservar'),
            'paginaActiva' => 'reservar',
            'descripcion'  => 'Consulta disponibilidad y reserva tu cabaña en línea.',
        ]);
    }

    /**
     * Datos del calendario: qué noches quedan libres y a qué precio.
     *
     * Tres cuidados:
     *
     * 1. **Un día lleno sigue valiendo como fecha de salida.** Si la última
     *    cabaña está ocupada la noche del 15, uno se puede ir la mañana del 15
     *    igualmente. Por eso se devuelve la ocupación de la **noche**, y es el
     *    calendario el que aplica una regla u otra según esté eligiendo la
     *    llegada o la salida. Confundirlo es el fallo clásico que hace perder
     *    una noche vendible por reserva.
     *
     * 2. **El precio sale del mismo motor que la cotización.** Si el calendario
     *    calculara por su cuenta, acabaría prometiendo un precio que luego la
     *    reserva no respeta.
     *
     * 3. **Se muestra el más barato disponible** («desde»), porque en este paso
     *    el visitante todavía no ha elegido tipo de alojamiento.
     */
    public function calendario()
    {
        $desde = $this->request->getGet('desde') ?: date('Y-m-01');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = date('Y-m-01');
        }

        // Un tope sensato: nadie reserva a tres años vista y calcular de más
        // solo hace la respuesta más lenta
        $meses = max(1, min(4, (int) ($this->request->getGet('meses') ?: 2)));
        $hasta = date('Y-m-d', strtotime($desde . ' +' . $meses . ' month'));

        $libres = $this->reservas->libresPorNoche($desde, $hasta);
        $motor  = new \App\Libraries\MotorTarifas();
        $hoy    = date('Y-m-d');

        // El precio de cada tipo para cada noche, pedido una sola vez por tipo
        $tipos   = $this->tipos->findAll();
        $precios = [];
        foreach ($tipos as $t) {
            foreach (array_keys($libres) as $fecha) {
                $precios[(int) $t['id']][$fecha] = $motor->precioNoche((int) $t['id'], $fecha)['precio'];
            }
        }

        $dias = [];
        foreach ($libres as $fecha => $porTipo) {
            $totalLibres = 0;
            $masBarato   = null;

            foreach ($porTipo as $tipoId => $n) {
                if ($n <= 0) {
                    continue;
                }
                $totalLibres += $n;
                $p = $precios[$tipoId][$fecha] ?? null;
                if ($p !== null && ($masBarato === null || $p < $masBarato)) {
                    $masBarato = $p;
                }
            }

            $dias[$fecha] = [
                'libres' => $totalLibres,
                'desde'  => $masBarato !== null ? (int) round($masBarato) : null,
                'pasado' => $fecha < $hoy,
            ];
        }

        return $this->response->setJSON([
            'ok'     => true,
            'desde'  => $desde,
            'hasta'  => $hasta,
            'hoy'    => $hoy,
            'dias'   => $dias,
            // Por debajo de esto se avisa de que quedan pocas
            'pocas'  => 2,
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
                    'tipo'       => $this->t->filas('tipos_unidad', [$tipo], ['nombre', 'descripcion'])[0],
                    'libres'     => count($libres),
                    'total'      => $cotizacion['total'],
                    'porNoche'   => $cotizacion['media_noche'],
                    'cotizacion' => $cotizacion,
                    'galeria'    => $this->galeriaDelTipo((int) $tipo['id'], $libres),
                    'servicios'  => $this->t->filas(
                        'servicios',
                        (new \App\Models\ServicioModel())->fichaDeTipo((int) $tipo['id']),
                        ['nombre']
                    ),
                ];
            }
        }

        return view('web/reservar_opciones', [
            'hotel'        => config('Hotel'),
            'tituloPagina' => lang('Web.disponibilidad'),
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
            'tituloPagina' => lang('Web.tusDatos'),
            'paginaActiva' => 'reservar',
            'busqueda'     => $datos,
            'tipo'         => $tipo,
            'noches'       => $noches,
            'total'        => $cotizacion['total'],
            'cotizacion'   => $cotizacion,
            'cupon'        => $cupon,
            'descuento'    => $descuento,
            'cuponError'   => $cuponError,
            'experiencias' => $this->experienciasDisponibles($datos),
            // Pedirle un código de referido a alguien cuando el programa está
            // apagado es pedirle algo que no le va a servir de nada.
            'referidosActivos' => (new \App\Libraries\Referidos())->activo(),
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

        // Experiencias pedidas: quedan como solicitudes, no como venta cerrada.
        // El hotel confirma el cupo y solo se cobra cuando se hacen.
        $pedidas = $this->guardarExperiencias((int) $reservaId, (int) $huespedId, $busqueda);

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

        // Si viene recomendado, se apunta. No se reparte nada todavía: el
        // premio se entrega cuando la estancia ocurra de verdad.
        $referido = trim((string) $this->request->getPost('referido'));

        if ($referido !== '') {
            (new \App\Libraries\Referidos())->apuntar($reservaId, $referido, $huespedId);
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
            $correo = new \App\Libraries\Correo();

            // Dos correos distintos, no uno con dos destinatarios: al hotel se
            // le dice «hay trabajo» y al huésped «llegó tu solicitud». Sin el
            // segundo, quien acaba de dejar sus datos cierra la pestaña y se
            // queda sin el código y sin saber si funcionó.
            $correo->avisoReservaWeb($completa);
            $correo->solicitudRecibida($completa);
        }

        return redirect()->to('reservar/exito/' . $codigo)
            ->with('experiencias', $pedidas);
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
            'tituloPagina' => lang('Web.reservaRecibida'),
            'paginaActiva' => 'reservar',
            'reserva'      => $reserva,
            'descuento'    => (new \App\Models\FolioModel())->totalDescuentos((int) $reserva['id']),
            'actividades'  => (new \App\Models\ExperienciaReservaModel())->deReserva((int) $reserva['id']),
            'pedidas'      => session()->getFlashdata('experiencias'),
        ]);
    }

    /**
     * Apunta las experiencias que marcó el huésped.
     *
     * Se vuelve a comprobar el cupo aquí: entre que vio la página y confirmó
     * pudo entrar otro. Lo que ya no cabe simplemente no se apunta, y se le dice.
     *
     * @return array{apuntadas: list<string>, sin_sitio: list<string>}
     */
    private function guardarExperiencias(int $reservaId, int $huespedId, array $busqueda): array
    {
        $marcadas = (array) $this->request->getPost('experiencia');
        if ($marcadas === []) {
            return ['apuntadas' => [], 'sin_sitio' => []];
        }

        $modelo  = new \App\Models\ExperienciaModel();
        $salidas = new \App\Models\ExperienciaReservaModel();
        $cuando  = (array) $this->request->getPost('experiencia_fecha');

        $apuntadas = [];
        $sinSitio  = [];
        $personas  = $busqueda['adultos'] + $busqueda['ninos'];

        foreach ($marcadas as $expId) {
            $exp = $modelo->find((int) $expId);
            if ($exp === null || (int) $exp['activa'] !== 1 || (int) $exp['publicada'] !== 1) {
                continue;
            }

            // El valor viene como «2026-08-14|06:00» o «2026-08-14|»
            [$fecha, $hora] = array_pad(explode('|', (string) ($cuando[$expId] ?? '')), 2, '');
            $hora = $hora !== '' ? $hora : null;

            if (strtotime($fecha) === false
                || $fecha < $busqueda['entrada'] || $fecha > $busqueda['salida']
                || ! \App\Models\ExperienciaModel::seHace($exp, $fecha)) {
                $sinSitio[] = $exp['nombre'];

                continue;
            }

            if ($salidas->plazasLibres($exp, $fecha, $hora) < $personas) {
                $sinSitio[] = $exp['nombre'];

                continue;
            }

            $salidas->insert([
                'experiencia_id'  => (int) $exp['id'],
                'reserva_id'      => $reservaId,
                'huesped_id'      => $huespedId,
                'fecha'           => $fecha,
                'hora'            => $hora,
                'adultos'         => $busqueda['adultos'],
                'ninos'           => $busqueda['ninos'],
                'precio_unitario' => (float) $exp['precio'],
                'precio_nino'     => $exp['precio_nino'],
                'total'           => \App\Models\ExperienciaModel::calcularTotal($exp, $busqueda['adultos'], $busqueda['ninos']),
                'estado'          => 'solicitada',
                'notas'           => 'Pedida al reservar en línea',
            ]);

            $apuntadas[] = $exp['nombre'];
        }

        return ['apuntadas' => $apuntadas, 'sin_sitio' => $sinSitio];
    }

    /**
     * Experiencias que se pueden hacer durante la estancia, con los días
     * concretos en que hay sitio. Solo se ofrece lo que de verdad cabe.
     */
    private function experienciasDisponibles(array $busqueda): array
    {
        $experiencias = $this->t->filas(
            'experiencias',
            (new \App\Models\ExperienciaModel())->publicas(),
            ['nombre', 'descripcion', 'incluye']
        );
        if ($experiencias === []) {
            return [];
        }

        $salidas  = new \App\Models\ExperienciaReservaModel();
        $medios   = new \App\Models\MedioModel();
        $personas = $busqueda['adultos'] + $busqueda['ninos'];

        // Se ofrecen los días de la estancia, incluido el de salida
        $dias = [];
        for ($d = $busqueda['entrada']; $d <= $busqueda['salida']; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $dias[] = $d;
        }

        $disponibles = [];

        foreach ($experiencias as $e) {
            $fechas = [];

            foreach ($dias as $dia) {
                if (! \App\Models\ExperienciaModel::seHace($e, $dia)) {
                    continue;
                }

                $horas = \App\Models\ExperienciaModel::horariosDe($e);
                foreach ($horas ?: [null] as $hora) {
                    if ($salidas->plazasLibres($e, $dia, $hora) >= $personas) {
                        $fechas[] = ['fecha' => $dia, 'hora' => $hora];
                    }
                }
            }

            if ($fechas === []) {
                continue;
            }

            $galeria = $medios->deExperiencia((int) $e['id']);
            $foto    = null;
            foreach ($galeria as $m) {
                if ($m['tipo'] === 'foto') {
                    $foto = $m;
                    break;
                }
            }

            $disponibles[] = [
                'experiencia' => $e,
                'fechas'      => $fechas,
                'foto'        => $foto,
                'total'       => \App\Models\ExperienciaModel::calcularTotal($e, $busqueda['adultos'], $busqueda['ninos']),
            ];
        }

        return $disponibles;
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
