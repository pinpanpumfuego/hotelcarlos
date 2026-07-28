<?php

namespace App\Controllers;

use App\Models\FolioModel;
use App\Models\HuespedModel;
use App\Models\ReservaModel;
use App\Models\UnidadModel;

class Reservas extends BaseController
{
    private ReservaModel $reservas;
    private HuespedModel $huespedes;
    private UnidadModel $unidades;

    public function __construct()
    {
        $this->reservas  = new ReservaModel();
        $this->huespedes = new HuespedModel();
        $this->unidades  = new UnidadModel();
    }

    public function index()
    {
        $filtros = [
            'estado' => (string) $this->request->getGet('estado'),
            'buscar' => trim((string) $this->request->getGet('q')),
        ];

        $consulta = $this->reservas->consultaDetallada($filtros);

        return view('reservas/index', [
            'titulo'      => 'Reservas',
            'seccion'     => 'reservas',
            'reservas'    => $consulta->paginate(20),
            'paginador'   => $this->reservas->pager,
            'filtros'     => $filtros,
            'totalActual' => $this->reservas->pager->getTotal(),
        ]);
    }

    public function nueva()
    {
        // Prefill desde el calendario: ?unidad=X&entrada=YYYY-MM-DD
        $prefill = [];
        $unidad  = (int) $this->request->getGet('unidad');
        $entrada = (string) $this->request->getGet('entrada');

        if ($unidad > 0) {
            $prefill['unidad_id'] = $unidad;
        }
        if (\DateTime::createFromFormat('Y-m-d', $entrada) !== false) {
            $prefill['fecha_entrada'] = $entrada;
            $prefill['fecha_salida']  = (new \DateTime($entrada))->modify('+1 day')->format('Y-m-d');
        }

        return view('reservas/form', [
            'titulo'    => 'Nueva reserva',
            'seccion'   => 'reservas',
            'reserva'   => $prefill,
            'huespedes' => $this->huespedes->orderBy('apellidos')->findAll(),
            'unidades'  => $this->unidades->conTipo(),
        ]);
    }

    public function guardar()
    {
        $datos = $this->request->getPost(['huesped_id', 'unidad_id', 'fecha_entrada', 'fecha_salida', 'adultos', 'ninos', 'estado', 'notas']);
        $datos = array_merge($datos, $this->datosCanal());

        $error = $this->validarFechas($datos);
        if ($error !== null) {
            return redirect()->back()->withInput()->with('errores', [$error]);
        }

        if (! $this->reservas->unidadDisponible((int) $datos['unidad_id'], $datos['fecha_entrada'], $datos['fecha_salida'])) {
            return redirect()->back()->withInput()->with('errores', ['La unidad no está disponible en esas fechas: ya existe otra reserva que se cruza.']);
        }

        $motor      = new \App\Libraries\MotorTarifas();
        $cotizacion = $motor->cotizarUnidad(
            (int) $datos['unidad_id'],
            $datos['fecha_entrada'],
            $datos['fecha_salida'],
            max(1, (int) $datos['adultos']),
            max(0, (int) $datos['ninos'])
        );

        $datos['codigo']          = $this->reservas->generarCodigo();
        $datos['total']           = $cotizacion['total'];
        $datos['desglose_precio'] = $motor->resumenParaGuardar($cotizacion);

        if (! $this->reservas->insert($datos)) {
            return redirect()->back()->withInput()->with('errores', $this->reservas->errors());
        }

        return redirect()->to('reservas')->with('ok', 'Reserva ' . $datos['codigo'] . ' creada. Total: $' . number_format($datos['total'], 0, ',', '.') . ' COP.');
    }

    public function editar(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null) {
            return redirect()->to('reservas')->with('error', 'La reserva no existe.');
        }

        return view('reservas/form', [
            'titulo'    => 'Editar reserva',
            'seccion'   => 'reservas',
            'reserva'   => $reserva,
            'huespedes' => $this->huespedes->orderBy('apellidos')->findAll(),
            'unidades'  => $this->unidades->conTipo(),
        ]);
    }

    public function actualizar(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null) {
            return redirect()->to('reservas')->with('error', 'La reserva no existe.');
        }

        $datos = $this->request->getPost(['huesped_id', 'unidad_id', 'fecha_entrada', 'fecha_salida', 'adultos', 'ninos', 'estado', 'notas']);
        $datos = array_merge($datos, $this->datosCanal());

        $error = $this->validarFechas($datos);
        if ($error !== null) {
            return redirect()->back()->withInput()->with('errores', [$error]);
        }

        if (! $this->reservas->unidadDisponible((int) $datos['unidad_id'], $datos['fecha_entrada'], $datos['fecha_salida'], $id)) {
            return redirect()->back()->withInput()->with('errores', ['La unidad no está disponible en esas fechas: ya existe otra reserva que se cruza.']);
        }

        // El precio solo se recalcula si cambia algo que lo afecta, o si se pide
        // expresamente. Así una corrección de notas no altera lo pactado.
        $cambioPrecio = (int) $datos['unidad_id'] !== (int) $reserva['unidad_id']
            || $datos['fecha_entrada'] !== $reserva['fecha_entrada']
            || $datos['fecha_salida'] !== $reserva['fecha_salida']
            || (int) $datos['adultos'] !== (int) $reserva['adultos']
            || (int) $datos['ninos'] !== (int) $reserva['ninos'];

        $recalcular = $cambioPrecio || $this->request->getPost('recalcular') !== null;

        if ($recalcular) {
            $motor      = new \App\Libraries\MotorTarifas();
            $cotizacion = $motor->cotizarUnidad(
                (int) $datos['unidad_id'],
                $datos['fecha_entrada'],
                $datos['fecha_salida'],
                max(1, (int) $datos['adultos']),
                max(0, (int) $datos['ninos']),
                $id
            );
            $datos['total']           = $cotizacion['total'];
            $datos['desglose_precio'] = $motor->resumenParaGuardar($cotizacion);
        }

        if (! $this->reservas->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->reservas->errors());
        }

        return redirect()->to('reservas')->with(
            'ok',
            $recalcular
                ? 'Reserva actualizada. Total recalculado: $' . number_format($datos['total'], 0, ',', '.') . ' COP.'
                : 'Reserva actualizada. El precio se mantiene en $' . number_format((float) $reserva['total'], 0, ',', '.') . ' COP.'
        );
    }

    /** Ficha completa de la reserva con su folio. */
    public function ver(int $id)
    {
        $reserva = $this->reservas
            ->select('reservas.*, huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos,
                      huespedes.tipo_documento, huespedes.num_documento, huespedes.telefono AS h_telefono,
                      huespedes.email AS h_email, unidades.nombre AS unidad_nombre,
                      tipos_unidad.nombre AS tipo_nombre, tipos_unidad.capacidad')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            // `left` a propósito: una reserva puede existir sin cabaña asignada
            // todavía. Con `join` normal desaparecía de la pantalla y parecía
            // que no existía, justo la que hay que abrir para asignarle una.
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->join('tipos_unidad', 'tipos_unidad.id = unidades.tipo_id', 'left')
            ->where('reservas.id', $id)
            ->first();

        if ($reserva === null) {
            return redirect()->to('reservas')->with('error', 'La reserva no existe.');
        }

        $folio = new FolioModel();
        if (! in_array($reserva['estado'], ['cancelada'], true)) {
            $folio->asegurarCargoAlojamiento($reserva);
        }

        return view('reservas/ver', [
            'titulo'      => 'Reserva ' . $reserva['codigo'],
            'seccion'     => 'reservas',
            'reserva'     => $reserva,
            'movimientos' => $folio->movimientosDeReserva($id),
            'saldo'       => $folio->saldo($id),
            // El bono no se elige a mano: se canjea con su código, más abajo
            // El bono y la tarjeta de saldo no se eligen a mano: se piden por su
            // código y descuentan de verdad. Dejarlos en el desplegable sería
            // poder dar por pagada una cuenta sin que nadie pague nada.
            'metodos'     => array_diff_key(FolioModel::METODOS, ['bono' => '', 'tarjeta_saldo' => '']),
            'registro'    => (new \App\Models\RegistroModel())->where('reserva_id', $id)->first(),
            'actividades' => (new \App\Models\ExperienciaReservaModel())->deReserva($id),
            'experiencias' => (new \App\Models\ExperienciaModel())->activas(),
            // Sin pasarela configurada, el enlace de cobro no se enseña: mandar
            // al huésped a una pantalla de error es peor que no ofrecerlo.
            'cobroOnline' => (new \App\Libraries\Wompi())->activo(),
            // Cuentas de empresa a las que se puede cargar esta reserva
            'cuentas' => puede('cartera.ver')
                ? (new \App\Models\CuentaCarteraModel())->where('estado', 'activa')->orderBy('nombre')->findAll()
                : [],
            'yaEnCartera' => (new \App\Models\CarteraMovimientoModel())
                ->where('reserva_id', $id)->where('tipo', 'cargo')->first(),
        ]);
    }

    /** Recepción valida una reserva web: pendiente → confirmada. */
    public function confirmar(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || $reserva['estado'] !== 'pendiente') {
            return redirect()->back()->with('error', 'Solo se puede confirmar una reserva pendiente.');
        }

        $this->reservas->update($id, ['estado' => 'confirmada']);

        // Al confirmar se avisa al huésped y se le envía su enlace de registro
        $aviso    = '';
        $completa = $this->reservaParaCorreo($id);

        if ($completa !== null && ! empty($completa['email'])) {
            $registro = (new \App\Models\RegistroModel())->paraReserva($completa);

            // El portal de la estancia nace aquí y acompaña al huésped hasta dos
            // semanas después de irse: todavía quiere su comprobante, y la
            // encuesta de salida se contesta cuando ya se ha ido.
            $accesos = new \App\Models\PortalAccesoModel();
            $acceso  = $accesos->asegurar($completa);

            $correo = new \App\Libraries\Correo();

            // Se encola por el CRM si hay una automatización de confirmación
            // encendida; si no, se manda el correo de siempre. Así encender la
            // automatización no duplica el mensaje, y apagarla no deja al
            // huésped sin confirmación.
            $encolado = (new \App\Libraries\Automatizaciones())->alConfirmar($id);

            if ($encolado !== null) {
                $aviso = ' La confirmación quedó en cola y saldrá en unos minutos.';
            } elseif ($correo->confirmacionReserva($completa, site_url('registro/' . $registro['token']), $accesos->enlace($acceso))) {
                $aviso = ' Se envió la confirmación por correo a ' . $completa['email'] . ' con su enlace de registro.';
            } elseif (! $correo->configurado()) {
                $aviso = ' El correo no está configurado todavía: envíale el enlace de registro por WhatsApp desde esta ficha.';
            } else {
                $aviso = ' No se pudo enviar el correo (revisa Administración → correos enviados).';
            }
        }

        return redirect()->to('reservas/ver/' . $id)->with('ok', 'Reserva confirmada.' . $aviso);
    }

    /**
     * De dónde vino la reserva y qué comisión se lleva el portal.
     *
     * La comisión no se descuenta del folio —el huésped paga el total— pero
     * saberla es lo que permite comparar de verdad la venta directa con la
     * de los portales en los reportes.
     */
    private function datosCanal(): array
    {
        $canal = (string) $this->request->getPost('canal');
        if (! array_key_exists($canal, \App\Models\CanalConexionModel::CANALES)) {
            $canal = 'directa';
        }

        $comision = $this->request->getPost('comision');

        return [
            'canal'    => $canal,
            'comision' => trim((string) $comision) !== ''
                ? (float) $comision
                : \App\Models\CanalConexionModel::comisionCanal($canal),
            'referencia_externa' => trim((string) $this->request->getPost('referencia_externa')) ?: null,
        ];
    }

    /** Reserva con los datos que necesitan las plantillas de correo. */
    private function reservaParaCorreo(int $id): ?array
    {
        return $this->reservas
            ->select('reservas.*, huespedes.nombre, huespedes.apellidos, huespedes.email, huespedes.telefono,
                      unidades.nombre AS unidad_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->where('reservas.id', $id)
            ->first();
    }

    /** Añade un cargo al folio (consumos, daños, servicios...). */
    public function cargoFolio(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || in_array($reserva['estado'], ['cancelada', 'checkout'], true)) {
            return redirect()->back()->with('error', 'No se pueden añadir cargos a esta reserva.');
        }

        $concepto = trim((string) $this->request->getPost('concepto'));
        $valor    = (float) $this->request->getPost('valor');

        if ($concepto === '' || $valor <= 0) {
            return redirect()->to('reservas/ver/' . $id)->with('error', 'Indica un concepto y un valor mayor que cero.');
        }

        (new FolioModel())->insert([
            'reserva_id' => $id,
            'tipo'       => 'cargo',
            'concepto'   => $concepto,
            'valor'      => $valor,
            'usuario_id' => session()->get('usuario_id'),
        ]);

        return redirect()->to('reservas/ver/' . $id)->with('ok', 'Cargo añadido al folio.');
    }

    /** Canjea un cupón de descuento sobre el folio de la reserva. */
    public function cuponFolio(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || in_array($reserva['estado'], ['cancelada', 'checkout'], true)) {
            return redirect()->back()->with('error', 'No se pueden aplicar cupones a esta reserva.');
        }

        $folio = new FolioModel();

        // La base del descuento son los cargos del folio menos lo ya descontado
        $cargos      = (float) ($folio->selectSum('valor')->where(['reserva_id' => $id, 'tipo' => 'cargo'])->first()['valor'] ?? 0);
        $descontado  = (float) ($folio->selectSum('valor')->where(['reserva_id' => $id, 'tipo' => 'descuento'])->first()['valor'] ?? 0);
        $base        = max(0, $cargos - $descontado);

        $cupones = new \App\Models\CuponModel();
        $r       = $cupones->validar(
            (string) $this->request->getPost('codigo'),
            'recepcion',
            'alojamiento',
            $base,
            (int) $reserva['huesped_id']
        );

        if (! $r['ok']) {
            return redirect()->to('reservas/ver/' . $id)->with('error', $r['mensaje']);
        }

        $folio->insert([
            'reserva_id' => $id,
            'tipo'       => 'descuento',
            'concepto'   => 'Descuento cupón ' . $r['cupon']['codigo'],
            'valor'      => $r['descuento'],
            'usuario_id' => session()->get('usuario_id'),
        ]);

        $cupones->registrarUso($r['cupon'], [
            'reserva_id' => $id,
            'huesped_id' => $reserva['huesped_id'],
            'base'       => $base,
            'descuento'  => $r['descuento'],
            'canal'      => 'recepcion',
        ]);

        return redirect()->to('reservas/ver/' . $id)->with('ok', $r['mensaje']);
    }

    /**
     * Canjea un bono regalo contra el saldo pendiente del folio.
     * No es un descuento: es un pago, porque ese dinero ya entró al venderlo.
     */
    public function bonoFolio(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || $reserva['estado'] === 'cancelada') {
            return redirect()->back()->with('error', 'No se pueden canjear bonos en esta reserva.');
        }

        $folio     = new FolioModel();
        $pendiente = $folio->saldo($id);

        $bonos = new \App\Models\BonoModel();
        $r     = $bonos->validar((string) $this->request->getPost('codigo'), $pendiente);

        if (! $r['ok']) {
            return redirect()->to('reservas/ver/' . $id)->with('error', $r['mensaje']);
        }

        $folio->insert([
            'reserva_id' => $id,
            'tipo'       => 'pago',
            'concepto'   => 'Bono regalo ' . $r['bono']['codigo'],
            'valor'      => $r['importe'],
            'metodo'     => 'bono',
            'usuario_id' => session()->get('usuario_id'),
        ]);

        $bonos->consumir($r['bono'], $r['importe'], [
            'reserva_id' => $id,
            'concepto'   => 'Reserva ' . $reserva['codigo'],
        ]);

        return redirect()->to('reservas/ver/' . $id)->with('ok', $r['mensaje']);
    }

    /** Registra un pago en el folio. */
    public function pagoFolio(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || $reserva['estado'] === 'cancelada') {
            return redirect()->back()->with('error', 'No se pueden registrar pagos en esta reserva.');
        }

        $valor  = (float) $this->request->getPost('valor');
        $metodo = (string) $this->request->getPost('metodo');

        if ($valor <= 0 || ! array_key_exists($metodo, FolioModel::METODOS)
            || in_array($metodo, ['bono', 'tarjeta_saldo'], true)) {
            return redirect()->to('reservas/ver/' . $id)->with('error', 'Indica un valor mayor que cero y un método de pago.');
        }

        $folioId = (new FolioModel())->insert([
            'reserva_id' => $id,
            'tipo'       => 'pago',
            'concepto'   => 'Pago (' . FolioModel::METODOS[$metodo] . ')',
            'valor'      => $valor,
            'metodo'     => $metodo,
            'usuario_id' => session()->get('usuario_id'),
        ]);

        // Los pagos en efectivo entran automáticamente a la caja del turno abierto
        $aviso = '';
        if ($metodo === 'efectivo') {
            $turno = (new \App\Models\CajaTurnoModel())->abierto();
            if ($turno !== null) {
                (new \App\Models\CajaMovimientoModel())->insert([
                    'turno_id'            => $turno['id'],
                    'tipo'                => 'ingreso',
                    'concepto'            => 'Pago reserva ' . $reserva['codigo'],
                    'valor'               => $valor,
                    'usuario_id'          => session()->get('usuario_id'),
                    'folio_movimiento_id' => $folioId,
                ]);
                $aviso = ' El efectivo quedó registrado en la caja del turno.';
            } else {
                $aviso = ' Aviso: no hay turno de caja abierto, el efectivo no quedó en ninguna caja.';
            }
        }

        return redirect()->to('reservas/ver/' . $id)->with('ok', 'Pago registrado.' . $aviso);
    }

    /** El huésped llega: la reserva pasa a "checkin" y la unidad a "ocupada". */
    public function checkin(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || ! in_array($reserva['estado'], ['pendiente', 'confirmada'], true)) {
            return redirect()->to('reservas')->with('error', 'Solo se puede hacer check-in de una reserva pendiente o confirmada.');
        }

        $this->reservas->update($id, ['estado' => 'checkin']);
        $this->unidades->update($reserva['unidad_id'], ['estado' => 'ocupada']);

        return redirect()->to('reservas')->with('ok', 'Check-in realizado: la unidad queda ocupada.');
    }

    /** El huésped se va: exige folio en cero; la unidad pasa a "limpieza". */
    public function checkout(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || $reserva['estado'] !== 'checkin') {
            return redirect()->to('reservas')->with('error', 'Solo se puede hacer check-out de una reserva con check-in.');
        }

        $folio = new FolioModel();
        $folio->asegurarCargoAlojamiento($reserva);
        $saldo = $folio->saldo($id);

        if (abs($saldo) > 0.01) {
            return redirect()->to('reservas/ver/' . $id)
                ->with('error', 'No se puede hacer check-out: el folio tiene un saldo pendiente de $' . number_format($saldo, 0, ',', '.') . ' COP. Registra el pago primero.');
        }

        $this->reservas->update($id, ['estado' => 'checkout']);
        // Abre la tarea de limpieza con su checklist en vez de tocar el
        // estado a mano: así la cabaña no vuelve a estar vendible hasta que
        // alguien la haya limpiado y, si toca, inspeccionado.
        (new \App\Libraries\Housekeeping())->abrirTarea((int) $reserva['unidad_id'], 'salida');
        $this->unidades->update($reserva['unidad_id'], ['estado' => 'disponible']);

        // La estancia ocurrió: ahora sí se reparten los cupones del referido.
        // Antes no, porque una reserva cancelada habría dejado dos cupones
        // regalados por una estancia que no llegó a pasar.
        $premio = (new \App\Libraries\Referidos())->cumplir($id);
        $aviso  = $premio === null
            ? ''
            : ' Vino recomendado: se emitieron los cupones ' . $premio['referidor']
                . ($premio['referido'] !== null ? ' y ' . $premio['referido'] : '') . '.';

        return redirect()->to('reservas')->with('ok', 'Check-out realizado: la unidad pasa a limpieza.' . $aviso);
    }

    public function cancelar(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || ! in_array($reserva['estado'], ['pendiente', 'confirmada'], true)) {
            return redirect()->to('reservas')->with('error', 'Solo se puede cancelar una reserva pendiente o confirmada.');
        }

        // `cancelada_en` y `cancelada_origen` existían desde hace tres
        // migraciones y no las escribía nadie: sin ellas, cualquier informe de
        // cancelaciones sale en cero y no se distingue lo que alguien canceló
        // a propósito de lo que caducó solo.
        $this->reservas->update($id, [
            'estado'           => 'cancelada',
            'cancelada_en'     => date('Y-m-d H:i:s'),
            'cancelada_origen' => 'recepcion',
        ]);

        // Si venía de una recomendación, esa recomendación no ha traído a nadie
        (new \App\Libraries\Referidos())->anular($id, 'La reserva se canceló.');

        return redirect()->to('reservas')->with('ok', 'Reserva cancelada. Sus fechas quedan libres.');
    }

    private function validarFechas(array $datos): ?string
    {
        if (empty($datos['fecha_entrada']) || empty($datos['fecha_salida'])) {
            return 'Debes indicar fecha de entrada y de salida.';
        }
        if ($datos['fecha_salida'] <= $datos['fecha_entrada']) {
            return 'La fecha de salida debe ser posterior a la de entrada.';
        }

        return null;
    }
}
