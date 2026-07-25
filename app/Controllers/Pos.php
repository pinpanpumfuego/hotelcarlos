<?php

namespace App\Controllers;

use App\Models\CajaMovimientoModel;
use App\Models\CajaTurnoModel;
use App\Models\CartaCategoriaModel;
use App\Models\CartaProductoModel;
use App\Models\ComandaLineaModel;
use App\Models\ComandaModel;
use App\Models\FolioModel;
use App\Models\MesaModel;
use App\Models\ReservaModel;

/**
 * TPV táctil a pantalla completa.
 * La vista es una sola página; todas las acciones van por estos endpoints JSON.
 */
class Pos extends BaseController
{
    private ComandaModel $comandas;
    private ComandaLineaModel $lineas;

    public function __construct()
    {
        $this->comandas = new ComandaModel();
        $this->lineas   = new ComandaLineaModel();
    }

    /** Pantalla completa del TPV. */
    public function index()
    {
        return view('pos/index', [
            'formasPago' => ComandaModel::FORMAS_PAGO,
            'usuario'    => session()->get('usuario_nombre'),
        ]);
    }

    // ─────────────────────────────── API ───────────────────────────────

    /** Estado inicial: mesas, carta, huéspedes alojados y comandas sin mesa. */
    public function estado()
    {
        $categorias = (new CartaCategoriaModel())->ordenadas();
        $productos  = (new CartaProductoModel())->conCategoria(true);

        $porCategoria = [];
        foreach ($categorias as $c) {
            $porCategoria[$c['id']] = [
                'id'        => (int) $c['id'],
                'nombre'    => $c['nombre'],
                'color'     => $c['color'] ?? '#4f8a68',
                'productos' => [],
            ];
        }
        foreach ($productos as $p) {
            if (isset($porCategoria[$p['categoria_id']])) {
                $porCategoria[$p['categoria_id']]['productos'][] = [
                    'id'     => (int) $p['id'],
                    'nombre' => $p['nombre'],
                    'precio' => (float) $p['precio'],
                ];
            }
        }

        // Comandas abiertas sin mesa (para llevar, room service)
        $sinMesa = $this->comandas
            ->select('comandas.id, comandas.numero, comandas.total, comandas.created_at,
                      comandas.reserva_id, unidades.nombre AS unidad_nombre,
                      huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.estado', 'abierta')
            ->where('comandas.mesa_id IS NULL')
            ->orderBy('comandas.created_at')
            ->findAll();

        return $this->response->setJSON([
            'mesas'      => (new MesaModel())->conEstado(),
            'categorias' => array_values($porCategoria),
            'sinMesa'    => $sinMesa,
            'alojados'   => $this->alojados(),
            'turnoCaja'  => (new CajaTurnoModel())->abierto() !== null,
        ]);
    }

    /** Abre una comanda en una mesa, o suelta (para llevar / cabaña). */
    public function abrir()
    {
        $datos     = $this->request->getJSON(true) ?? [];
        $mesaId    = isset($datos['mesa_id']) ? (int) $datos['mesa_id'] : null;
        $reservaId = isset($datos['reserva_id']) ? (int) $datos['reserva_id'] : null;
        $comensales = max(1, (int) ($datos['comensales'] ?? 1));

        if ($mesaId !== null) {
            $abierta = $this->comandas->where('mesa_id', $mesaId)->where('estado', 'abierta')->first();
            if ($abierta !== null) {
                return $this->response->setJSON(['ok' => true, 'comanda_id' => (int) $abierta['id']]);
            }
            $mesa = (new MesaModel())->find($mesaId);
            if ($mesa === null) {
                return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'La mesa no existe.']);
            }
        }

        if ($reservaId !== null) {
            $reserva = (new ReservaModel())->find($reservaId);
            if ($reserva === null || $reserva['estado'] !== 'checkin') {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Esa reserva no tiene un huésped alojado.']);
            }
        }

        if ($mesaId === null && $reservaId === null && empty($datos['nombre'])) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Indica mesa, huésped o un nombre.']);
        }

        $id = $this->comandas->insert([
            'numero'     => $this->comandas->generarNumero(),
            'mesa_id'    => $mesaId,
            'mesa'       => $mesaId !== null ? ($mesa['nombre'] ?? null) : (trim((string) ($datos['nombre'] ?? '')) ?: null),
            'reserva_id' => $reservaId,
            'comensales' => $comensales,
            'estado'     => 'abierta',
            'usuario_id' => session()->get('usuario_id'),
        ]);

        return $this->response->setJSON(['ok' => true, 'comanda_id' => (int) $id]);
    }

    /** Devuelve la comanda con sus líneas. */
    public function comanda(int $id)
    {
        $comanda = $this->comandaDetalle($id);
        if ($comanda === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'La comanda no existe.']);
        }

        return $this->response->setJSON(['ok' => true, 'comanda' => $comanda]);
    }

    /** Añade un producto (o suma cantidad si ya está pendiente sin nota). */
    public function anadir(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $datos    = $this->request->getJSON(true) ?? [];
        $producto = (new CartaProductoModel())->find((int) ($datos['producto_id'] ?? 0));
        if ($producto === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Producto no encontrado.']);
        }

        $cantidad = max(1, (int) ($datos['cantidad'] ?? 1));

        $existente = $this->lineas
            ->where('comanda_id', $id)
            ->where('producto_id', $producto['id'])
            ->where('enviado_cocina', 0)
            ->where('notas IS NULL')
            ->first();

        if ($existente !== null) {
            $this->lineas->update($existente['id'], ['cantidad' => $existente['cantidad'] + $cantidad]);
        } else {
            $this->lineas->insert([
                'comanda_id'      => $id,
                'producto_id'     => $producto['id'],
                'nombre_producto' => $producto['nombre'],
                'precio_unitario' => $producto['precio'],
                'cantidad'        => $cantidad,
            ]);
        }

        $this->comandas->recalcularTotal($id);

        return $this->response->setJSON(['ok' => true, 'comanda' => $this->comandaDetalle($id)]);
    }

    /** Cambia cantidad (0 elimina) o la nota de una línea. */
    public function linea(int $lineaId)
    {
        $linea = $this->lineas->find($lineaId);
        if ($linea === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Línea no encontrada.']);
        }

        $comanda = $this->comandas->find($linea['comanda_id']);
        if ($comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda ya está cerrada.']);
        }

        $datos = $this->request->getJSON(true) ?? [];

        if (array_key_exists('cantidad', $datos)) {
            $cantidad = max(0, (int) $datos['cantidad']);
            // Una línea ya enviada a cocina no se borra sin dejar rastro
            if ($cantidad === 0 && (int) $linea['enviado_cocina'] === 1) {
                return $this->response->setStatusCode(422)->setJSON([
                    'ok' => false, 'error' => 'Ese plato ya se envió a cocina: anúlalo desde la comanda o avisa al responsable.',
                ]);
            }
            if ($cantidad === 0) {
                $this->lineas->delete($lineaId);
            } else {
                $this->lineas->update($lineaId, ['cantidad' => $cantidad]);
            }
        }

        if (array_key_exists('notas', $datos)) {
            $this->lineas->update($lineaId, ['notas' => trim((string) $datos['notas']) ?: null]);
        }

        $this->comandas->recalcularTotal((int) $comanda['id']);

        return $this->response->setJSON(['ok' => true, 'comanda' => $this->comandaDetalle((int) $comanda['id'])]);
    }

    /** Marca las líneas nuevas como enviadas a cocina. */
    public function enviarCocina(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $pendientes = $this->lineas->where('comanda_id', $id)->where('enviado_cocina', 0)->countAllResults();
        if ($pendientes === 0) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'No hay platos nuevos que enviar.']);
        }

        $this->lineas->builder()
            ->where('comanda_id', $id)
            ->where('enviado_cocina', 0)
            ->update(['enviado_cocina' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->response->setJSON([
            'ok'      => true,
            'mensaje' => $pendientes . ($pendientes === 1 ? ' plato enviado' : ' platos enviados') . ' a cocina.',
            'comanda' => $this->comandaDetalle($id),
        ]);
    }

    /** Aplica un descuento en pesos o porcentaje. */
    public function descuento(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $datos = $this->request->getJSON(true) ?? [];
        $valor = (float) ($datos['valor'] ?? 0);
        $tipo  = ($datos['tipo'] ?? 'valor') === 'porcentaje' ? 'porcentaje' : 'valor';

        $bruto     = (float) $this->comandas->recalcularTotal($id);
        $descuento = $tipo === 'porcentaje' ? round($bruto * min(100, max(0, $valor)) / 100) : max(0, $valor);
        $descuento = min($descuento, $bruto);

        $this->comandas->update($id, [
            'descuento'        => $descuento,
            'motivo_descuento' => trim((string) ($datos['motivo'] ?? '')) ?: null,
        ]);

        return $this->response->setJSON(['ok' => true, 'comanda' => $this->comandaDetalle($id)]);
    }

    /** Cobra la comanda; efectivo va a caja, "habitacion" al folio. */
    public function cobrar(int $id)
    {
        $comanda = $this->comandaDetalle($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }
        if ($comanda['a_pagar'] <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda está vacía.']);
        }

        $datos = $this->request->getJSON(true) ?? [];
        $forma = (string) ($datos['forma_pago'] ?? '');
        if (! array_key_exists($forma, ComandaModel::FORMAS_PAGO)) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Forma de pago no válida.']);
        }
        if ($forma === 'habitacion' && $comanda['reserva_id'] === null) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Para cargar a la cabaña, la comanda debe estar ligada a un huésped alojado.']);
        }

        $aPagar   = (float) $comanda['a_pagar'];
        $recibido = $forma === 'efectivo' ? (float) ($datos['recibido'] ?? $aPagar) : null;
        $cambio   = $recibido !== null ? max(0, $recibido - $aPagar) : null;

        if ($forma === 'efectivo' && $recibido < $aPagar) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'El efectivo recibido es menor que el total.']);
        }

        $aviso = '';
        if ($forma === 'habitacion') {
            (new FolioModel())->insert([
                'reserva_id' => $comanda['reserva_id'],
                'tipo'       => 'cargo',
                'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
                'valor'      => $aPagar,
                'usuario_id' => session()->get('usuario_id'),
            ]);
            $aviso = 'Cargado al folio de ' . $comanda['reserva_codigo'] . '.';
        } elseif ($forma === 'efectivo') {
            $turno = (new CajaTurnoModel())->abierto();
            if ($turno !== null) {
                (new CajaMovimientoModel())->insert([
                    'turno_id'   => $turno['id'],
                    'tipo'       => 'ingreso',
                    'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
                    'valor'      => $aPagar,
                    'usuario_id' => session()->get('usuario_id'),
                ]);
                $aviso = 'El efectivo entró en la caja del turno.';
            } else {
                $aviso = 'Aviso: no hay turno de caja abierto, el efectivo no quedó registrado en ninguna caja.';
            }
        }

        $this->comandas->update($id, [
            'estado'     => 'cobrada',
            'forma_pago' => $forma,
            'recibido'   => $recibido,
            'cambio'     => $cambio,
            'cerrada_en' => date('Y-m-d H:i:s'),
        ]);

        return $this->response->setJSON([
            'ok'      => true,
            'cambio'  => $cambio,
            'total'   => $aPagar,
            'numero'  => $comanda['numero'],
            'mensaje' => 'Comanda ' . $comanda['numero'] . ' cobrada. ' . $aviso,
        ]);
    }

    public function anular(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Solo se anulan comandas abiertas.']);
        }

        $datos  = $this->request->getJSON(true) ?? [];
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        if ($motivo === '') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Indica el motivo de la anulación.']);
        }

        $this->comandas->update($id, [
            'estado'     => 'anulada',
            'cerrada_en' => date('Y-m-d H:i:s'),
            'notas'      => 'Anulada: ' . $motivo,
        ]);

        return $this->response->setJSON(['ok' => true, 'mensaje' => 'Comanda ' . $comanda['numero'] . ' anulada.']);
    }

    /** Mueve la comanda a otra mesa (o la deja sin mesa). */
    public function mover(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $datos  = $this->request->getJSON(true) ?? [];
        $mesaId = (int) ($datos['mesa_id'] ?? 0);

        $mesa = (new MesaModel())->find($mesaId);
        if ($mesa === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'La mesa no existe.']);
        }

        $ocupada = $this->comandas->where('mesa_id', $mesaId)->where('estado', 'abierta')->first();
        if ($ocupada !== null) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Esa mesa ya tiene una comanda abierta.']);
        }

        $this->comandas->update($id, ['mesa_id' => $mesaId, 'mesa' => $mesa['nombre']]);

        return $this->response->setJSON(['ok' => true, 'mensaje' => 'Comanda movida a ' . $mesa['nombre'] . '.']);
    }

    // ───────────────────────────── Ayudantes ─────────────────────────────

    private function comandaDetalle(int $id): ?array
    {
        $comanda = $this->comandas
            ->select('comandas.*, reservas.codigo AS reserva_codigo,
                      huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos,
                      unidades.nombre AS unidad_nombre')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.id', $id)
            ->first();

        if ($comanda === null) {
            return null;
        }

        $lineas = $this->lineas->deComanda($id);
        foreach ($lineas as &$l) {
            $l['id']              = (int) $l['id'];
            $l['cantidad']        = (int) $l['cantidad'];
            $l['precio_unitario'] = (float) $l['precio_unitario'];
            $l['enviado_cocina']  = (int) $l['enviado_cocina'];
            $l['subtotal']        = $l['precio_unitario'] * $l['cantidad'];
        }
        unset($l);

        $comanda['id']         = (int) $comanda['id'];
        $comanda['reserva_id'] = $comanda['reserva_id'] !== null ? (int) $comanda['reserva_id'] : null;
        $comanda['total']      = (float) $comanda['total'];
        $comanda['descuento']  = (float) $comanda['descuento'];
        $comanda['a_pagar']    = max(0, $comanda['total'] - $comanda['descuento']);
        $comanda['lineas']     = $lineas;
        $comanda['pendientes'] = count(array_filter($lineas, static fn ($l) => $l['enviado_cocina'] === 0));

        return $comanda;
    }

    private function alojados(): array
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
