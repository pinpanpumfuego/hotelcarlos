<?php

namespace App\Controllers;

use App\Models\CajaMovimientoModel;
use App\Models\CajaTurnoModel;
use App\Models\CartaCategoriaModel;
use App\Models\CartaProductoModel;
use App\Models\ComandaLineaModel;
use App\Models\ComandaModel;
use App\Models\FolioModel;
use App\Models\ReservaModel;

/** TPV del restaurante: comandas, cobro y pantalla de cocina. */
class Tpv extends BaseController
{
    private ComandaModel $comandas;
    private ComandaLineaModel $lineas;

    public function __construct()
    {
        $this->comandas = new ComandaModel();
        $this->lineas   = new ComandaLineaModel();
    }

    /** Listado de comandas abiertas. */
    public function index()
    {
        return view('tpv/index', [
            'titulo'    => 'Restaurante',
            'seccion'   => 'tpv',
            'abiertas'  => $this->comandas->abiertas(),
            'historial' => $this->comandas->historial(),
            'alojados'  => $this->reservasAlojadas(),
        ]);
    }

    /** Abre una comanda nueva, opcionalmente ligada a una reserva alojada. */
    public function abrir()
    {
        $mesa      = trim((string) $this->request->getPost('mesa'));
        $reservaId = (int) $this->request->getPost('reserva_id') ?: null;

        if ($mesa === '' && $reservaId === null) {
            return redirect()->to('tpv')->with('error', 'Indica una mesa o elige un huésped alojado.');
        }

        if ($reservaId !== null) {
            $reserva = (new ReservaModel())->find($reservaId);
            if ($reserva === null || $reserva['estado'] !== 'checkin') {
                return redirect()->to('tpv')->with('error', 'Esa reserva no tiene un huésped alojado.');
            }
        }

        $id = $this->comandas->insert([
            'numero'     => $this->comandas->generarNumero(),
            'mesa'       => $mesa ?: null,
            'reserva_id' => $reservaId,
            'estado'     => 'abierta',
            'usuario_id' => session()->get('usuario_id'),
        ]);

        return redirect()->to('tpv/comanda/' . $id);
    }

    /** Pantalla de la comanda: carta a la izquierda, cuenta a la derecha. */
    public function comanda(int $id)
    {
        $comanda = $this->comandaConDetalles($id);
        if ($comanda === null) {
            return redirect()->to('tpv')->with('error', 'La comanda no existe.');
        }

        $productos = (new CartaProductoModel())->conCategoria(true);

        // Agrupa por categoría para las pestañas del TPV
        $porCategoria = [];
        foreach ($productos as $p) {
            $porCategoria[$p['categoria_nombre']][] = $p;
        }

        return view('tpv/comanda', [
            'titulo'       => 'Comanda ' . $comanda['numero'],
            'seccion'      => 'tpv',
            'comanda'      => $comanda,
            'lineas'       => $this->lineas->deComanda($id),
            'porCategoria' => $porCategoria,
            'formasPago'   => ComandaModel::FORMAS_PAGO,
            'hayCarta'     => $productos !== [],
        ]);
    }

    /** Añade un producto a la comanda (o suma uno si ya está). */
    public function anadir(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return redirect()->to('tpv')->with('error', 'La comanda no está abierta.');
        }

        $producto = (new CartaProductoModel())->find((int) $this->request->getPost('producto_id'));
        if ($producto === null) {
            return redirect()->to('tpv/comanda/' . $id)->with('error', 'El producto no existe.');
        }

        // Si el mismo producto ya está sin entregar y sin notas, se suma a esa línea
        $existente = $this->lineas
            ->where('comanda_id', $id)
            ->where('producto_id', $producto['id'])
            ->where('entregado', 0)
            ->where('notas IS NULL')
            ->first();

        if ($existente !== null) {
            $this->lineas->update($existente['id'], ['cantidad' => $existente['cantidad'] + 1]);
        } else {
            $this->lineas->insert([
                'comanda_id'      => $id,
                'producto_id'     => $producto['id'],
                'nombre_producto' => $producto['nombre'],
                'precio_unitario' => $producto['precio'],
                'cantidad'        => 1,
            ]);
        }

        $this->comandas->recalcularTotal($id);

        return redirect()->to('tpv/comanda/' . $id);
    }

    /** Cambia la cantidad de una línea; con 0 se elimina. */
    public function cantidad(int $lineaId)
    {
        $linea = $this->lineas->find($lineaId);
        if ($linea === null) {
            return redirect()->to('tpv')->with('error', 'La línea no existe.');
        }

        $comanda = $this->comandas->find($linea['comanda_id']);
        if ($comanda['estado'] !== 'abierta') {
            return redirect()->to('tpv/comanda/' . $comanda['id'])->with('error', 'La comanda ya está cerrada.');
        }

        $cantidad = max(0, (int) $this->request->getPost('cantidad'));
        if ($cantidad === 0) {
            $this->lineas->delete($lineaId);
        } else {
            $this->lineas->update($lineaId, ['cantidad' => $cantidad]);
        }

        $this->comandas->recalcularTotal((int) $comanda['id']);

        return redirect()->to('tpv/comanda/' . $comanda['id']);
    }

    /** Marca una línea como entregada (o la devuelve a pendiente). */
    public function entregar(int $lineaId)
    {
        $linea = $this->lineas->find($lineaId);
        if ($linea === null) {
            return redirect()->to('tpv')->with('error', 'La línea no existe.');
        }

        $this->lineas->update($lineaId, ['entregado' => $linea['entregado'] ? 0 : 1]);

        $destino = $this->request->getPost('volver') === 'cocina' ? 'cocina' : 'tpv/comanda/' . $linea['comanda_id'];

        return redirect()->to($destino);
    }

    /** Cobra la comanda: efectivo a caja, o cargo al folio de la cabaña. */
    public function cobrar(int $id)
    {
        $comanda = $this->comandaConDetalles($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return redirect()->to('tpv')->with('error', 'La comanda no está abierta.');
        }

        $total = $this->comandas->recalcularTotal($id);
        if ($total <= 0) {
            return redirect()->to('tpv/comanda/' . $id)->with('error', 'La comanda está vacía: añade productos antes de cobrar.');
        }

        $forma = (string) $this->request->getPost('forma_pago');
        if (! array_key_exists($forma, ComandaModel::FORMAS_PAGO)) {
            return redirect()->to('tpv/comanda/' . $id)->with('error', 'Elige una forma de pago.');
        }

        if ($forma === 'habitacion' && $comanda['reserva_id'] === null) {
            return redirect()->to('tpv/comanda/' . $id)->with('error', 'Para cargar a la cabaña, la comanda debe estar ligada a un huésped alojado.');
        }

        $aviso   = '';
        $cobrado = $total;

        // El bono y la tarjeta de saldo no son «formas de pago» a secas: hay que
        // descontarle el dinero a alguien. Si esto no se hiciera, la comanda
        // quedaría cobrada y el saldo intacto, que es regalar la cuenta.
        if ($forma === 'bono') {
            $bonos = new \App\Models\BonoModel();
            $r     = $bonos->validar((string) $this->request->getPost('codigo'), $total);

            if (! $r['ok']) {
                return redirect()->to('tpv/comanda/' . $id)->with('error', $r['mensaje']);
            }

            if ($r['importe'] < $total) {
                return redirect()->to('tpv/comanda/' . $id)->with(
                    'error',
                    'Al bono le faltan $' . number_format($total - $r['importe'], 0, ',', '.')
                    . '. Esta pantalla cobra la comanda entera de una vez: para partir el cobro, usa el TPV táctil.'
                );
            }

            $bonos->consumir($r['bono'], $total, [
                'comanda_id' => $id,
                'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
            ]);

            $aviso = ' Bono ' . $r['bono']['codigo'] . ' aplicado.';
        } elseif ($forma === 'tarjeta_saldo') {
            $motor = new \App\Libraries\Tarjetas();
            $sim   = $motor->simular((string) $this->request->getPost('codigo'), $total, 'restaurante');

            if (! $sim['ok']) {
                return redirect()->to('tpv/comanda/' . $id)->with('error', $sim['motivo']);
            }

            if ($sim['falta'] > 0) {
                return redirect()->to('tpv/comanda/' . $id)->with(
                    'error',
                    'A la tarjeta le faltan $' . number_format($sim['falta'], 0, ',', '.')
                    . '. Esta pantalla cobra la comanda entera de una vez: para partir el cobro, usa el TPV táctil.'
                );
            }

            try {
                $cobro = $motor->cobrar((string) $this->request->getPost('codigo'), $total, 'restaurante', [
                    'pin'        => (string) $this->request->getPost('pin'),
                    'comanda_id' => $id,
                    'usuario_id' => session()->get('usuario_id'),
                    'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
                ]);
            } catch (\RuntimeException $e) {
                return redirect()->to('tpv/comanda/' . $id)->with('error', $e->getMessage());
            }

            // El descuento baja lo que paga el cliente, pero queda escrito: la
            // venta valió lo que valía y se ve cuánto se regaló.
            if ($cobro['descuento'] > 0) {
                $this->comandas->update($id, [
                    'descuento'        => round((float) $comanda['descuento'] + $cobro['descuento'], 2),
                    'motivo_descuento' => 'Tarjeta ' . $sim['tarjeta']['codigo'],
                ]);
                // El total de la comanda sigue siendo lo que valió; lo que se
                // cobró de verdad es menos, y es lo que se dice en pantalla.
                $cobrado = $cobro['cobrado'];
                $aviso   = ' Descuento de tarjeta: $' . number_format($cobro['descuento'], 0, ',', '.') . '.';
            }

            $aviso .= ' Tarjeta ' . $sim['tarjeta']['codigo'] . ': le quedan $'
                . number_format($cobro['saldo'], 0, ',', '.') . ' de saldo.';
        } elseif ($forma === 'habitacion') {
            // Va como cargo al folio: el huésped lo paga al hacer el check-out
            (new FolioModel())->insert([
                'reserva_id' => $comanda['reserva_id'],
                'tipo'       => 'cargo',
                'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
                'valor'      => $total,
                'usuario_id' => session()->get('usuario_id'),
            ]);
            $aviso = ' Cargado al folio de la reserva ' . $comanda['reserva_codigo'] . '.';
        } elseif ($forma === 'efectivo') {
            $turno = (new CajaTurnoModel())->abierto();
            if ($turno !== null) {
                (new CajaMovimientoModel())->insert([
                    'turno_id'   => $turno['id'],
                    'tipo'       => 'ingreso',
                    'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
                    'valor'      => $total,
                    'usuario_id' => session()->get('usuario_id'),
                ]);
                $aviso = ' El efectivo entró en la caja del turno.';
            } else {
                $aviso = ' Aviso: no hay turno de caja abierto, el efectivo no quedó registrado en ninguna caja.';
            }
        }

        $this->comandas->update($id, [
            'estado'     => 'cobrada',
            'forma_pago' => $forma,
            'total'      => $total,
            'cerrada_en' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('tpv')->with('ok', 'Comanda ' . $comanda['numero'] . ' cobrada por $' . number_format($cobrado, 0, ',', '.') . ' COP.' . $aviso);
    }

    public function anular(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return redirect()->to('tpv')->with('error', 'Solo se pueden anular comandas abiertas.');
        }

        $this->comandas->update($id, [
            'estado'     => 'anulada',
            'cerrada_en' => date('Y-m-d H:i:s'),
            'notas'      => trim((string) $this->request->getPost('motivo')) ?: null,
        ]);

        return redirect()->to('tpv')->with('ok', 'Comanda ' . $comanda['numero'] . ' anulada.');
    }

    /** La cocina tiene ahora su propia pantalla, pensada para verse a distancia. */
    public function cocina()
    {
        return redirect()->to('cocina');
    }

    private function comandaConDetalles(int $id): ?array
    {
        return $this->comandas
            ->select('comandas.*, reservas.codigo AS reserva_codigo,
                      huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos,
                      unidades.nombre AS unidad_nombre')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.id', $id)
            ->first();
    }

    /** Reservas con huésped alojado, para poder cargar consumos a su cabaña. */
    private function reservasAlojadas(): array
    {
        return (new ReservaModel())
            ->select('reservas.id, reservas.codigo, huespedes.nombre, huespedes.apellidos, unidades.nombre AS unidad_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->where('reservas.estado', 'checkin')
            ->orderBy('unidades.nombre')
            ->findAll();
    }
}
