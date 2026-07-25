<?php

namespace App\Controllers;

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

    /** El huésped se va: la reserva pasa a "checkout" y la unidad a "limpieza". */
    public function checkout(int $id)
    {
        $reserva = $this->reservas->find($id);
        if ($reserva === null || $reserva['estado'] !== 'checkin') {
            return redirect()->to('reservas')->with('error', 'Solo se puede hacer check-out de una reserva con check-in.');
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
