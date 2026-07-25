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
        return view('reservas/index', [
            'titulo'   => 'Reservas',
            'seccion'  => 'reservas',
            'reservas' => $this->reservas->conDetalles(),
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

        $error = $this->validarFechas($datos);
        if ($error !== null) {
            return redirect()->back()->withInput()->with('errores', [$error]);
        }

        if (! $this->reservas->unidadDisponible((int) $datos['unidad_id'], $datos['fecha_entrada'], $datos['fecha_salida'])) {
            return redirect()->back()->withInput()->with('errores', ['La unidad no está disponible en esas fechas: ya existe otra reserva que se cruza.']);
        }

        $datos['codigo'] = $this->reservas->generarCodigo();
        $datos['total']  = $this->reservas->calcularTotal((int) $datos['unidad_id'], $datos['fecha_entrada'], $datos['fecha_salida']);

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

        $error = $this->validarFechas($datos);
        if ($error !== null) {
            return redirect()->back()->withInput()->with('errores', [$error]);
        }

        if (! $this->reservas->unidadDisponible((int) $datos['unidad_id'], $datos['fecha_entrada'], $datos['fecha_salida'], $id)) {
            return redirect()->back()->withInput()->with('errores', ['La unidad no está disponible en esas fechas: ya existe otra reserva que se cruza.']);
        }

        $datos['total'] = $this->reservas->calcularTotal((int) $datos['unidad_id'], $datos['fecha_entrada'], $datos['fecha_salida']);

        if (! $this->reservas->update($id, $datos)) {
            return redirect()->back()->withInput()->with('errores', $this->reservas->errors());
        }

        return redirect()->to('reservas')->with('ok', 'Reserva actualizada. Total recalculado: $' . number_format($datos['total'], 0, ',', '.') . ' COP.');
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
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->join('tipos_unidad', 'tipos_unidad.id = unidades.tipo_id')
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
            'metodos'     => FolioModel::METODOS,
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

        return redirect()->to('reservas/ver/' . $id)->with('ok', 'Reserva confirmada.');
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

    /** Registra un pago en el folio. */
    public function pagoFolio(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || $reserva['estado'] === 'cancelada') {
            return redirect()->back()->with('error', 'No se pueden registrar pagos en esta reserva.');
        }

        $valor  = (float) $this->request->getPost('valor');
        $metodo = (string) $this->request->getPost('metodo');

        if ($valor <= 0 || ! array_key_exists($metodo, FolioModel::METODOS)) {
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
        $this->unidades->update($reserva['unidad_id'], ['estado' => 'limpieza']);

        return redirect()->to('reservas')->with('ok', 'Check-out realizado: la unidad pasa a limpieza.');
    }

    public function cancelar(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || ! in_array($reserva['estado'], ['pendiente', 'confirmada'], true)) {
            return redirect()->to('reservas')->with('error', 'Solo se puede cancelar una reserva pendiente o confirmada.');
        }

        $this->reservas->update($id, ['estado' => 'cancelada']);

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
