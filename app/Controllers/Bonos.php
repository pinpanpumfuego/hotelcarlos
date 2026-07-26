<?php

namespace App\Controllers;

use App\Models\BonoModel;
use App\Models\BonoMovimientoModel;
use App\Models\CajaMovimientoModel;
use App\Models\CajaTurnoModel;

/** Bonos regalo: emisión, consulta y anulación (gerencia y recepción). */
class Bonos extends BaseController
{
    private BonoModel $bonos;

    public function __construct()
    {
        $this->bonos = new BonoModel();
    }

    public function index()
    {
        $filtros = [
            'estado' => (string) $this->request->getGet('estado'),
            'buscar' => trim((string) $this->request->getGet('buscar')),
        ];

        $todos = $this->bonos->listado($filtros);

        return view('bonos/index', [
            'titulo'     => 'Bonos regalo',
            'seccion'    => 'bonos',
            'bonos'      => $todos,
            'filtros'    => $filtros,
            'saldoVivo'  => $this->bonos->saldoVivo(),
            'formasPago' => BonoModel::FORMAS_PAGO,
        ]);
    }

    public function ver(int $id)
    {
        $bono = $this->bonos->find($id);
        if ($bono === null) {
            return redirect()->to('bonos')->with('error', 'El bono no existe.');
        }

        return view('bonos/ver', [
            'titulo'      => 'Bono ' . $bono['codigo'],
            'seccion'     => 'bonos',
            'bono'        => $bono,
            'movimientos' => (new BonoMovimientoModel())->deBono($id),
        ]);
    }

    /** Emite un bono nuevo: el dinero entra ahora, el servicio se presta después. */
    public function guardar()
    {
        $importe = (float) $this->request->getPost('importe_inicial');
        $forma   = (string) $this->request->getPost('forma_pago');

        $datos = [
            'codigo'             => $this->bonos->generarCodigo(),
            'importe_inicial'    => $importe,
            'saldo'              => $importe,
            'comprador_nombre'   => trim((string) $this->request->getPost('comprador_nombre')),
            'comprador_email'    => trim((string) $this->request->getPost('comprador_email')) ?: null,
            'comprador_telefono' => trim((string) $this->request->getPost('comprador_telefono')) ?: null,
            'beneficiario'       => trim((string) $this->request->getPost('beneficiario')) ?: null,
            'mensaje'            => trim((string) $this->request->getPost('mensaje')) ?: null,
            'caduca'             => trim((string) $this->request->getPost('caduca')) ?: null,
            'forma_pago'         => array_key_exists($forma, BonoModel::FORMAS_PAGO) ? $forma : 'efectivo',
            'estado'             => 'activo',
            'notas'              => trim((string) $this->request->getPost('notas')) ?: null,
            'usuario_id'         => session()->get('usuario_id'),
        ];

        $id = $this->bonos->insert($datos);
        if ($id === false) {
            return redirect()->to('bonos')->withInput()->with('errores', $this->bonos->errors());
        }

        (new BonoMovimientoModel())->insert([
            'bono_id'       => $id,
            'tipo'          => 'emision',
            'valor'         => $importe,
            'saldo_despues' => $importe,
            'concepto'      => 'Venta del bono (' . BonoModel::FORMAS_PAGO[$datos['forma_pago']] . ')',
            'usuario_id'    => session()->get('usuario_id'),
        ]);

        // La venta del bono es dinero que entra hoy: si es efectivo, a la caja del turno
        $aviso = '';
        if ($datos['forma_pago'] === 'efectivo') {
            $turno = (new CajaTurnoModel())->abierto();
            if ($turno !== null) {
                (new CajaMovimientoModel())->insert([
                    'turno_id'   => $turno['id'],
                    'tipo'       => 'ingreso',
                    'concepto'   => 'Venta bono regalo ' . $datos['codigo'],
                    'valor'      => $importe,
                    'usuario_id' => session()->get('usuario_id'),
                ]);
                $aviso = ' El efectivo entró en la caja del turno.';
            } else {
                $aviso = ' Aviso: no hay turno de caja abierto, así que el efectivo no quedó registrado en ninguna caja.';
            }
        }

        return redirect()->to('bonos/ver/' . $id)
            ->with('ok', 'Bono ' . $datos['codigo'] . ' emitido por $' . number_format($importe, 0, ',', '.') . '.' . $aviso);
    }

    /** Actualiza los datos descriptivos. El saldo nunca se toca a mano desde aquí. */
    public function actualizar(int $id)
    {
        $bono = $this->bonos->find($id);
        if ($bono === null) {
            return redirect()->to('bonos')->with('error', 'El bono no existe.');
        }

        $ok = $this->bonos->update($id, [
            'comprador_nombre'   => trim((string) $this->request->getPost('comprador_nombre')),
            'comprador_email'    => trim((string) $this->request->getPost('comprador_email')) ?: null,
            'comprador_telefono' => trim((string) $this->request->getPost('comprador_telefono')) ?: null,
            'beneficiario'       => trim((string) $this->request->getPost('beneficiario')) ?: null,
            'mensaje'            => trim((string) $this->request->getPost('mensaje')) ?: null,
            'caduca'             => trim((string) $this->request->getPost('caduca')) ?: null,
            'notas'              => trim((string) $this->request->getPost('notas')) ?: null,
        ]);

        if (! $ok) {
            return redirect()->to('bonos/ver/' . $id)->withInput()->with('errores', $this->bonos->errors());
        }

        return redirect()->to('bonos/ver/' . $id)->with('ok', 'Datos del bono actualizados.');
    }

    /** Anula un bono: deja de poder canjearse y su saldo se pone a cero. */
    public function anular(int $id)
    {
        $bono = $this->bonos->find($id);
        if ($bono === null) {
            return redirect()->to('bonos')->with('error', 'El bono no existe.');
        }
        if ($bono['estado'] === 'anulado') {
            return redirect()->to('bonos/ver/' . $id)->with('error', 'Ese bono ya estaba anulado.');
        }

        $motivo = trim((string) $this->request->getPost('motivo'));
        if ($motivo === '') {
            return redirect()->to('bonos/ver/' . $id)->with('error', 'Indica el motivo de la anulación: queda en el historial.');
        }

        $saldo = (float) $bono['saldo'];
        $this->bonos->update($id, ['estado' => 'anulado', 'saldo' => 0]);

        (new BonoMovimientoModel())->insert([
            'bono_id'       => $id,
            'tipo'          => 'anulacion',
            'valor'         => $saldo,
            'saldo_despues' => 0,
            'concepto'      => 'Anulado: ' . $motivo,
            'usuario_id'    => session()->get('usuario_id'),
        ]);

        return redirect()->to('bonos/ver/' . $id)
            ->with('ok', 'Bono anulado. Se retiró un saldo de $' . number_format($saldo, 0, ',', '.') . '.');
    }

    /** Ficha imprimible para entregar al comprador. */
    public function imprimir(int $id)
    {
        $bono = $this->bonos->find($id);
        if ($bono === null) {
            return redirect()->to('bonos')->with('error', 'El bono no existe.');
        }

        return view('bonos/imprimir', [
            'bono'  => $bono,
            'hotel' => config('Hotel'),
        ]);
    }
}
