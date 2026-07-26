<?php

namespace App\Controllers;

use App\Models\CajaMovimientoModel;
use App\Models\CajaTurnoModel;
use App\Models\CartaCategoriaModel;
use App\Models\CartaProductoModel;
use App\Models\ComandaLineaModel;
use App\Models\ComandaModel;
use App\Models\ComandaPagoModel;
use App\Models\ComanderoBorradorModel;
use App\Models\FolioModel;
use App\Models\LineaModificadorModel;
use App\Models\MesaModel;
use App\Models\ModificadorGrupoModel;
use App\Models\ModificadorModel;
use App\Models\ReservaModel;

/**
 * TPV táctil a pantalla completa.
 * La vista es una sola página; todas las acciones van por estos endpoints JSON.
 */
class Pos extends BaseController
{
    private ComandaModel $comandas;
    private ComandaLineaModel $lineas;
    private \App\Libraries\SesionTpv $tpv;

    public function __construct()
    {
        $this->comandas = new ComandaModel();
        $this->lineas   = new ComandaLineaModel();
        $this->tpv      = new \App\Libraries\SesionTpv();
    }

    /** Pantalla completa del TPV. */
    public function index()
    {
        return view('pos/index', [
            'formasPago'  => ComandaModel::FORMAS_PAGO,
            'usuario'     => session()->get('usuario_nombre'),
            'compartido'  => $this->tpv->compartido(),
            'bloqueoSeg'  => $this->tpv->segundosBloqueo(),
            'camarero'    => $this->tpv->paraPantalla(),
            // Se sugiere, no se impone: la Ley 1935 de 2018 la hace voluntaria
            'propinaSugerida' => (float) (new \App\Models\ConfiguracionModel())->obtener('tpv_propina_sugerida', '10'),
        ]);
    }

    // ───────────────────── Identificación del camarero ─────────────────

    /** Desbloquea la pantalla con el PIN de fichaje o con una tarjeta. */
    public function identificar()
    {
        if (! $this->tpv->compartido()) {
            return $this->response->setJSON(['ok' => true, 'camarero' => null]);
        }

        // Freno: un PIN de cuatro cifras se prueba entero en un rato
        if (service('throttler')->check('tpv-' . md5($this->request->getIPAddress()), 15, MINUTE) === false) {
            return $this->response->setStatusCode(429)
                ->setJSON(['ok' => false, 'error' => 'Demasiados intentos. Espera un minuto.']);
        }

        $datos = $this->request->getJSON(true) ?? [];
        $r     = $this->tpv->abrir((string) ($datos['pin'] ?? ''), (string) ($datos['tarjeta'] ?? ''));

        if (! $r['ok']) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['mensaje']]);
        }

        return $this->response->setJSON([
            'ok'       => true,
            'mensaje'  => $r['mensaje'],
            'camarero' => $this->tpv->paraPantalla(),
        ]);
    }

    /** Bloquea la pantalla: por inactividad, tras cobrar o a mano. */
    public function bloquear()
    {
        $this->tpv->bloquear();

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Comprueba que hay alguien identificado antes de dejar tocar nada.
     * Devuelve null si todo bien, o la respuesta de error si no.
     */
    private function exigirCamarero()
    {
        if (! $this->tpv->compartido() || $this->tpv->actual() !== null) {
            return null;
        }

        return $this->response->setStatusCode(401)
            ->setJSON(['ok' => false, 'bloqueado' => true, 'error' => 'Identifícate para seguir.']);
    }

    /** El empleado al que hay que apuntar lo que se haga ahora. */
    private function camareroId(): ?int
    {
        $actual = $this->tpv->actual();

        return $actual === null ? null : (int) $actual['id'];
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
        // Grupos de modificadores asignados a cada producto
        $asignaciones = db_connect()->table('producto_modificador_grupos')->get()->getResultArray();
        $gruposPorProducto = [];
        foreach ($asignaciones as $a) {
            $gruposPorProducto[(int) $a['producto_id']][] = (int) $a['grupo_id'];
        }
        $todosGrupos = (new ModificadorGrupoModel())->conOpciones();

        foreach ($productos as $p) {
            if (isset($porCategoria[$p['categoria_id']])) {
                $ids = $gruposPorProducto[(int) $p['id']] ?? [];

                $porCategoria[$p['categoria_id']]['productos'][] = [
                    'id'        => (int) $p['id'],
                    'nombre'    => $p['nombre'],
                    'precio'    => (float) $p['precio'],
                    'divisible' => (int) ($p['divisible'] ?? 0) === 1,
                    'picante'   => (int) ($p['picante'] ?? 0),
                    'dietas'    => array_values(array_filter(
                        array_keys(CartaProductoModel::DIETAS),
                        static fn ($d) => ! empty($p[$d])
                    )),
                    'alergenos' => array_map(
                        static fn ($a) => CartaProductoModel::ALERGENOS[$a],
                        CartaProductoModel::alergenosDe($p['alergenos'] ?? null)
                    ),
                    'grupos'    => array_values(array_filter(
                        $todosGrupos,
                        static fn ($g) => in_array((int) $g['id'], $ids, true)
                    )),
                ];
            }
        }

        // Comandas abiertas sin mesa (para llevar, room service)
        $sinMesa = $this->comandas
            ->select('comandas.id, comandas.numero, comandas.total, comandas.created_at,
                      comandas.reserva_id, comandas.mesa, comandas.cliente_nombre,
                      unidades.nombre AS unidad_nombre,
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
            'listos'     => $this->lineas->listosParaServir(),
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
            'numero'      => $this->comandas->generarNumero(),
            'mesa_id'     => $mesaId,
            'mesa'        => $mesaId !== null ? ($mesa['nombre'] ?? null) : (trim((string) ($datos['nombre'] ?? '')) ?: null),
            'reserva_id'  => $reservaId,
            'comensales'  => $comensales,
            'estado'      => 'abierta',
            'usuario_id'  => session()->get('usuario_id'),
            'empleado_id' => $this->camareroId(),
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

    /**
     * Añade un producto a la comanda.
     * Admite modificadores elegidos y, en productos divisibles, una segunda
     * mitad: el precio de la mitad y mitad es el de la más cara.
     */
    public function anadir(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $datos     = $this->request->getJSON(true) ?? [];
        $productos = new CartaProductoModel();
        $producto  = $productos->find((int) ($datos['producto_id'] ?? 0));
        if ($producto === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Producto no encontrado.']);
        }

        $cantidad = max(1, (int) ($datos['cantidad'] ?? 1));
        $nombre   = $producto['nombre'];
        $precio   = (float) $producto['precio'];
        $composicion = null;

        // ── Mitad y mitad ──
        $mitadId = (int) ($datos['mitad_id'] ?? 0);
        if ($mitadId > 0) {
            $otra = $productos->find($mitadId);
            if ($otra === null || empty($producto['divisible']) || empty($otra['divisible'])) {
                return $this->response->setStatusCode(422)->setJSON([
                    'ok' => false, 'error' => 'Solo se pueden combinar dos productos marcados como divisibles.',
                ]);
            }
            // Se cobra la mitad más cara: criterio habitual en restauración
            $precio      = max((float) $producto['precio'], (float) $otra['precio']);
            $nombre      = '½ ' . $producto['nombre'] . ' + ½ ' . $otra['nombre'];
            $composicion = $producto['nombre'] . ' | ' . $otra['nombre'];
        }

        // ── Modificadores elegidos ──
        $elegidos = [];
        $idsMod   = array_map('intval', (array) ($datos['modificadores'] ?? []));
        if ($idsMod !== []) {
            $opciones = (new ModificadorModel())->whereIn('id', $idsMod)->findAll();
            foreach ($opciones as $o) {
                $elegidos[] = [
                    'modificador_id' => (int) $o['id'],
                    'nombre'         => $o['nombre'],
                    'precio_extra'   => (float) $o['precio_extra'],
                ];
                $precio += (float) $o['precio_extra'];
            }
        }

        $notas = trim((string) ($datos['notas'] ?? '')) ?: null;

        // Solo se agrupa con una línea existente si es exactamente igual
        $puedeAgrupar = $elegidos === [] && $notas === null && $mitadId === 0;
        $existente    = null;
        if ($puedeAgrupar) {
            $existente = $this->lineas
                ->where('comanda_id', $id)
                ->where('producto_id', $producto['id'])
                ->where('enviado_cocina', 0)
                ->where('notas IS NULL')
                ->where('composicion IS NULL')
                ->first();

            // Y siempre que no tenga modificadores
            if ($existente !== null
                && (new LineaModificadorModel())->where('linea_id', $existente['id'])->countAllResults() > 0) {
                $existente = null;
            }
        }

        if ($existente !== null) {
            $this->lineas->update($existente['id'], ['cantidad' => $existente['cantidad'] + $cantidad]);
        } else {
            $lineaId = $this->lineas->insert([
                'comanda_id'      => $id,
                'producto_id'     => $producto['id'],
                'nombre_producto' => $nombre,
                'composicion'     => $composicion,
                'destino'         => $producto['destino'] ?? 'cocina',
                'precio_unitario' => $precio,
                'cantidad'        => $cantidad,
                'notas'           => $notas,
                'empleado_id'     => $this->camareroId(),
            ]);

            if ($elegidos !== []) {
                $modelo = new LineaModificadorModel();
                foreach ($elegidos as $e) {
                    $modelo->insert($e + ['linea_id' => $lineaId]);
                }
            }
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

    /**
     * El mesero confirma que llevó el producto a la mesa.
     * Las bebidas de barra las puede preparar y servir el propio cajero,
     * sin esperar a que alguien las marque en la pantalla de barra.
     */
    public function servir(int $lineaId)
    {
        $linea = $this->lineas->find($lineaId);
        if ($linea === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Línea no encontrada.']);
        }

        $listo = (int) $linea['entregado'] === 1;

        // La cocina sí tiene que marcarlo: es el control de que el plato existe
        if (! $listo && $linea['destino'] === 'cocina') {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false, 'error' => 'Cocina aún no ha marcado ese plato como listo.',
            ]);
        }

        $cambios = ['servido' => 1];
        if (! $listo) {
            // Barra o entrega directa: el cajero lo prepara y sirve en el momento
            $cambios['entregado'] = 1;
            $cambios['listo_en']  = date('Y-m-d H:i:s');
        }

        $this->lineas->update($lineaId, $cambios);

        return $this->response->setJSON(['ok' => true, 'comanda' => $this->comandaDetalle((int) $linea['comanda_id'])]);
    }

    /** Marca las líneas nuevas como enviadas a cocina. */
    public function enviarCocina(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $nuevas = $this->lineas->where('comanda_id', $id)->where('enviado_cocina', 0)->findAll();
        if ($nuevas === []) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'No hay nada nuevo que enviar.']);
        }

        // El reparto por destino vive en el modelo: lo usan también el comandero
        $conteo = $this->lineas->enviarAPreparacion($id);

        $partes = [];
        if ($conteo['cocina'] > 0) {
            $partes[] = $conteo['cocina'] . ($conteo['cocina'] === 1 ? ' plato a cocina.' : ' platos a cocina.');
        }
        if ($conteo['barra'] > 0) {
            $partes[] = $conteo['barra'] . ($conteo['barra'] === 1 ? ' bebida a barra.' : ' bebidas a barra.');
        }
        if ($conteo['directo'] > 0) {
            $partes[] = $conteo['directo'] . ($conteo['directo'] === 1 ? ' producto de entrega directa' : ' productos de entrega directa')
                . ' (no pasan por preparación).';
        }

        return $this->response->setJSON([
            'ok'      => true,
            'mensaje' => implode(' ', $partes),
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

        // Un camarero puede rebajar hasta cierto punto; de ahí en adelante
        // lo tiene que autorizar un encargado con su PIN.
        $porcentaje = $bruto > 0 ? $descuento / $bruto * 100 : 0;
        $autorizo   = null;

        if ($porcentaje > $this->tpv->descuentoLibre()) {
            $permiso = $this->tpv->autorizar('descuento', (string) ($datos['pin_encargado'] ?? ''));

            if (! $permiso['ok']) {
                return $this->response->setStatusCode(403)->setJSON([
                    'ok'           => false,
                    'necesita_pin' => true,
                    'error'        => $permiso['mensaje'] . ' (más del '
                        . rtrim(rtrim(number_format($this->tpv->descuentoLibre(), 1, ',', '.'), '0'), ',') . ' %)',
                ]);
            }

            $autorizo = $permiso['autorizo']['id'] ?? null;
        }

        // Un descuento manual sustituye a un cupón anterior: se devuelve su uso
        if ($comanda['cupon_id'] !== null) {
            (new \App\Models\CuponModel())->devolverUso((int) $comanda['cupon_id'], ['comanda_id' => $id]);
        }

        $this->comandas->update($id, [
            'descuento'        => $descuento,
            'motivo_descuento' => trim((string) ($datos['motivo'] ?? '')) ?: null,
            'cupon_id'         => null,
            'autorizo_id'      => $autorizo,
        ]);

        return $this->response->setJSON(['ok' => true, 'comanda' => $this->comandaDetalle($id)]);
    }

    /** Canjea un cupón de descuento sobre la comanda. */
    public function cupon(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $datos  = $this->request->getJSON(true) ?? [];
        $codigo = (string) ($datos['codigo'] ?? '');
        $bruto  = (float) $this->comandas->recalcularTotal($id);

        // El huésped alojado, si lo hay, para poder controlar el límite por persona
        $huespedId = null;
        if ($comanda['reserva_id'] !== null) {
            $reserva   = (new ReservaModel())->find((int) $comanda['reserva_id']);
            $huespedId = $reserva === null ? null : (int) $reserva['huesped_id'];
        }

        $cupones = new \App\Models\CuponModel();
        $r       = $cupones->validar($codigo, 'tpv', 'restaurante', $bruto, $huespedId);

        if (! $r['ok']) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['mensaje']]);
        }

        // Si ya había un cupón puesto, se devuelve antes de aplicar el nuevo
        if ($comanda['cupon_id'] !== null) {
            $cupones->devolverUso((int) $comanda['cupon_id'], ['comanda_id' => $id]);
        }

        $cupones->registrarUso($r['cupon'], [
            'comanda_id' => $id,
            'huesped_id' => $huespedId,
            'base'       => $bruto,
            'descuento'  => $r['descuento'],
            'canal'      => 'tpv',
        ]);

        $this->comandas->update($id, [
            'descuento'        => $r['descuento'],
            'motivo_descuento' => 'Cupón ' . $r['cupon']['codigo'],
            'cupon_id'         => $r['cupon']['id'],
        ]);

        return $this->response->setJSON([
            'ok'      => true,
            'mensaje' => $r['mensaje'],
            'comanda' => $this->comandaDetalle($id),
        ]);
    }

    /**
     * Asigna el cliente de la comanda: un huésped alojado (permite cargar a
     * la cabaña) o un cliente ocasional identificado por su nombre.
     */
    public function cliente(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $datos = $this->request->getJSON(true) ?? [];
        $tipo  = (string) ($datos['tipo'] ?? '');

        if ($tipo === 'huesped') {
            $reservaId = (int) ($datos['reserva_id'] ?? 0);
            $reserva   = (new ReservaModel())->find($reservaId);
            if ($reserva === null || $reserva['estado'] !== 'checkin') {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Ese huésped no está alojado ahora mismo.']);
            }

            // Si ya se cobró algo a la cabaña anterior, no se puede cambiar sin más
            if ($comanda['reserva_id'] !== null && (int) $comanda['reserva_id'] !== $reservaId
                && (new ComandaPagoModel())->where('comanda_id', $id)->where('forma_pago', 'habitacion')->countAllResults() > 0) {
                return $this->response->setStatusCode(422)->setJSON([
                    'ok' => false, 'error' => 'Ya hay un cargo hecho a la cabaña anterior: anula la comanda y créala de nuevo.',
                ]);
            }

            $this->comandas->update($id, [
                'reserva_id'        => $reservaId,
                'cliente_nombre'    => null,
                'cliente_documento' => null,
                'cliente_telefono'  => null,
            ]);
        } elseif ($tipo === 'ocasional') {
            $nombre = trim((string) ($datos['nombre'] ?? ''));
            if ($nombre === '') {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Escribe el nombre del cliente.']);
            }

            $this->comandas->update($id, [
                'reserva_id'        => null,
                'cliente_nombre'    => $nombre,
                'cliente_documento' => trim((string) ($datos['documento'] ?? '')) ?: null,
                'cliente_telefono'  => trim((string) ($datos['telefono'] ?? '')) ?: null,
            ]);
        } elseif ($tipo === 'ninguno') {
            $this->comandas->update($id, [
                'reserva_id'        => null,
                'cliente_nombre'    => null,
                'cliente_documento' => null,
                'cliente_telefono'  => null,
            ]);
        } else {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Tipo de cliente no válido.']);
        }

        return $this->response->setJSON([
            'ok'       => true,
            'comanda'  => $this->comandaDetalle($id),
            'alojados' => $this->alojados(),
        ]);
    }

    /** Fija la propina de la comanda. */
    public function propina(int $id)
    {
        $comanda = $this->comandas->find($id);
        if ($comanda === null || $comanda['estado'] !== 'abierta') {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda no está abierta.']);
        }

        $datos = $this->request->getJSON(true) ?? [];
        $valor = (float) ($datos['valor'] ?? 0);
        $tipo  = ($datos['tipo'] ?? 'valor') === 'porcentaje' ? 'porcentaje' : 'valor';

        $bruto   = (float) $this->comandas->recalcularTotal($id) - (float) $comanda['descuento'];
        $propina = $tipo === 'porcentaje' ? round(max(0, $bruto) * min(100, max(0, $valor)) / 100) : max(0, $valor);

        $this->comandas->update($id, ['propina' => $propina]);

        return $this->response->setJSON(['ok' => true, 'comanda' => $this->comandaDetalle($id)]);
    }

    /**
     * Registra un pago (total o parcial). La comanda se cierra sola
     * cuando lo cobrado cubre el importe a pagar.
     */
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

        $pendiente = (float) $comanda['pendiente'];
        // Importe de este pago: el indicado, o todo lo que queda
        $importe = isset($datos['importe']) ? (float) $datos['importe'] : $pendiente;
        $importe = min(max(0, $importe), $pendiente);

        if ($importe <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La comanda ya está pagada.']);
        }

        // Bono regalo: el dinero ya se cobró al venderlo, aquí solo se gasta saldo
        $bono = null;
        if ($forma === 'bono') {
            $bonos = new \App\Models\BonoModel();
            $r     = $bonos->validar((string) ($datos['codigo'] ?? ''), $importe);

            if (! $r['ok']) {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['mensaje']]);
            }

            $bono    = $r['bono'];
            $importe = $r['importe']; // nunca más de lo que queda en el bono
        }

        $recibido = null;
        $cambio   = null;
        if ($forma === 'efectivo') {
            $recibido = (float) ($datos['recibido'] ?? $importe);
            if ($recibido < $importe) {
                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'El efectivo recibido es menor que el importe a cobrar.']);
            }
            $cambio = round($recibido - $importe, 2);
        }

        $avisos = [];

        if ($forma === 'habitacion') {
            (new FolioModel())->insert([
                'reserva_id' => $comanda['reserva_id'],
                'tipo'       => 'cargo',
                'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
                'valor'      => $importe,
                'usuario_id' => session()->get('usuario_id'),
            ]);
            $avisos[] = 'Cargado al folio de ' . $comanda['reserva_codigo'] . '.';
        } elseif ($forma === 'bono' && $bono !== null) {
            (new \App\Models\BonoModel())->consumir($bono, $importe, [
                'comanda_id' => $id,
                'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
            ]);
            $restante = round((float) $bono['saldo'] - $importe, 2);
            $avisos[] = 'Bono ' . $bono['codigo'] . ' aplicado. Le quedan $' . number_format($restante, 0, ',', '.') . ' de saldo.';
        } elseif ($forma === 'efectivo') {
            $turno = (new CajaTurnoModel())->abierto();
            if ($turno !== null) {
                (new CajaMovimientoModel())->insert([
                    'turno_id'   => $turno['id'],
                    'tipo'       => 'ingreso',
                    'concepto'   => 'Restaurante · comanda ' . $comanda['numero'],
                    'valor'      => $importe,
                    'usuario_id' => session()->get('usuario_id'),
                ]);
                $avisos[] = 'El efectivo entró en la caja del turno.';
            } else {
                $avisos[] = 'Aviso: no hay turno de caja abierto, el efectivo no quedó registrado en ninguna caja.';
            }
        }

        (new ComandaPagoModel())->insert([
            'comanda_id'  => $id,
            'forma_pago'  => $forma,
            'valor'       => $importe,
            'recibido'    => $recibido,
            'cambio'      => $cambio,
            'empleado_id' => $this->camareroId(),
            'usuario_id'  => session()->get('usuario_id'),
        ]);

        $actualizada = $this->comandaDetalle($id);
        $cerrada     = $actualizada['pendiente'] <= 0.01;

        if ($cerrada) {
            $this->comandas->update($id, [
                'estado'     => 'cobrada',
                'forma_pago' => $forma,
                'recibido'   => $recibido,
                'cambio'     => $cambio,
                'cerrada_en' => date('Y-m-d H:i:s'),
            ]);
            array_unshift($avisos, 'Comanda ' . $comanda['numero'] . ' cobrada por completo.');
        } else {
            array_unshift($avisos, 'Pago de ' . number_format($importe, 0, ',', '.')
                . ' registrado. Quedan $' . number_format($actualizada['pendiente'], 0, ',', '.') . ' por cobrar.');
        }

        return $this->response->setJSON([
            'ok'         => true,
            'cerrada'    => $cerrada,
            'cambio'     => $cambio,
            'pendiente'  => $actualizada['pendiente'],
            'comanda'    => $cerrada ? null : $actualizada,
            'comanda_id' => $id,
            'numero'     => $comanda['numero'],
            'mensaje'    => implode(' ', $avisos),
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

        // Anular borra una venta de los libros: siempre pasa por un encargado
        $permiso = $this->tpv->autorizar('anular', (string) ($datos['pin_encargado'] ?? ''));
        if (! $permiso['ok']) {
            return $this->response->setStatusCode(403)->setJSON([
                'ok' => false, 'necesita_pin' => true, 'error' => $permiso['mensaje'],
            ]);
        }

        // Si llevaba cupón, se devuelve el uso: la comanda no llegó a cobrarse
        if ($comanda['cupon_id'] !== null) {
            (new \App\Models\CuponModel())->devolverUso((int) $comanda['cupon_id'], ['comanda_id' => $id]);
        }

        // Y si se había pagado parte con un bono, se le devuelve el saldo
        $bonos = new \App\Models\BonoModel();
        foreach ((new \App\Models\BonoMovimientoModel())->where('comanda_id', $id)->where('tipo', 'consumo')->findAll() as $mov) {
            $bonos->devolver(
                (int) $mov['bono_id'],
                (float) $mov['valor'],
                'Devuelto al anular la comanda ' . $comanda['numero'],
                ['comanda_id' => $id]
            );
        }

        $this->comandas->update($id, [
            'estado'      => 'anulada',
            'cerrada_en'  => date('Y-m-d H:i:s'),
            'notas'       => 'Anulada: ' . $motivo,
            'cupon_id'    => null,
            'autorizo_id' => $permiso['autorizo']['id'] ?? null,
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

    /** Recibo imprimible (80 mm) de una comanda. */
    public function recibo(int $id)
    {
        $comanda = $this->comandaDetalle($id);
        if ($comanda === null) {
            return redirect()->to('pos');
        }

        return view('pos/recibo', [
            'comanda' => $comanda,
            'hotel'   => config('Hotel'),
            'formas'  => ComandaModel::FORMAS_PAGO,
            'cajero'  => session()->get('usuario_nombre'),
        ]);
    }

    // ───────────────────────────── Ayudantes ─────────────────────────────

    private function comandaDetalle(int $id): ?array
    {
        $comanda = $this->comandas
            ->select('comandas.*, reservas.codigo AS reserva_codigo,
                      huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos,
                      unidades.nombre AS unidad_nombre,
                      camarero.nombre AS camarero_nombre, camarero.apellidos AS camarero_apellidos,
                      usuarios.nombre AS usuario_nombre')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->join('empleados camarero', 'camarero.id = comandas.empleado_id', 'left')
            ->join('usuarios', 'usuarios.id = comandas.usuario_id', 'left')
            ->where('comandas.id', $id)
            ->first();

        if ($comanda === null) {
            return null;
        }

        $lineas = $this->lineas->deComandaConCamarero($id);
        $modifs = (new LineaModificadorModel())->deLineas(array_column($lineas, 'id'));

        foreach ($lineas as &$l) {
            $l['modificadores'] = $modifs[(int) $l['id']] ?? [];
            $l['id']              = (int) $l['id'];
            $l['cantidad']        = (int) $l['cantidad'];
            $l['precio_unitario'] = (float) $l['precio_unitario'];
            $l['enviado_cocina']  = (int) $l['enviado_cocina'];
            $l['entregado']       = (int) $l['entregado'];
            $l['servido']         = (int) $l['servido'];
            $l['subtotal']        = $l['precio_unitario'] * $l['cantidad'];

            // Estado visible para el mesero
            if ($l['servido'] === 1) {
                $l['estado'] = 'servido';
            } elseif ($l['entregado'] === 1) {
                $l['estado'] = 'listo';       // preparación terminada, falta llevarlo a la mesa
            } elseif ($l['enviado_cocina'] === 1) {
                // Un agua no está «en cocina»: no pasa por preparación
                $l['estado'] = match ($l['destino']) {
                    'barra'   => 'en_barra',
                    'directo' => 'directo',
                    default   => 'en_cocina',
                };
            } else {
                $l['estado'] = 'nuevo';       // aún no se ha enviado
            }
        }
        unset($l);

        $pagos = (new ComandaPagoModel())->deComanda($id);

        $comanda['id']         = (int) $comanda['id'];
        $comanda['reserva_id'] = $comanda['reserva_id'] !== null ? (int) $comanda['reserva_id'] : null;
        $comanda['total']      = (float) $comanda['total'];
        $comanda['descuento']  = (float) $comanda['descuento'];
        $comanda['propina']    = (float) $comanda['propina'];
        $comanda['a_pagar']    = max(0, $comanda['total'] - $comanda['descuento'] + $comanda['propina']);
        $comanda['pagado']     = array_sum(array_column($pagos, 'valor'));
        $comanda['pendiente']  = max(0, round($comanda['a_pagar'] - $comanda['pagado'], 2));
        $comanda['pagos']      = $pagos;
        $comanda['lineas']     = $lineas;
        $comanda['pendientes'] = count(array_filter($lineas, static fn ($l) => $l['estado'] === 'nuevo'));
        $comanda['listos']     = count(array_filter($lineas, static fn ($l) => $l['estado'] === 'listo'));
        // De lo nuevo, cuánto necesita preparación (el resto se entrega tal cual)
        $comanda['a_preparar'] = count(array_filter(
            $lineas,
            static fn ($l) => $l['estado'] === 'nuevo' && $l['destino'] !== 'directo'
        ));

        // ── Quién atiende esta mesa ──
        // Se responde a dos preguntas distintas: quién la lleva (para saber a
        // quién preguntar) y por dónde entró (una comanda del móvil no tiene
        // usuario del sistema, así que si alguien la cobra mal no basta con
        // mirar quién tenía la pantalla abierta).
        $comanda['camarero'] = trim((string) ($comanda['camarero_nombre'] ?? '')) !== ''
            ? trim($comanda['camarero_nombre'] . ' ' . ($comanda['camarero_apellidos'] ?? ''))
            : null;
        $comanda['origen'] = $comanda['usuario_id'] === null && $comanda['empleado_id'] !== null
            ? 'movil' : 'pantalla';

        // Los nombres de todos los que han metido algo, en orden de aparición.
        // Si son varios, la interfaz lo dice línea a línea.
        $manos = [];
        foreach ($lineas as $l) {
            $quien = trim((string) ($l['camarero'] ?? ''));
            if ($quien !== '' && ! in_array($quien, $manos, true)) {
                $manos[] = $quien;
            }
        }
        $comanda['camareros']       = $manos;
        $comanda['varias_manos']    = count($manos) > 1;

        // Lo que alguien esté apuntando ahora en el móvil para esta mesa y no
        // haya enviado todavía. Va aparte de las líneas a propósito: no suma
        // al total ni se puede cobrar, porque aún no existe.
        $comanda['tomando'] = (new ComanderoBorradorModel())
            ->deMesaOComanda(
                $comanda['mesa_id'] !== null ? (int) $comanda['mesa_id'] : null,
                (int) $comanda['id']
            );

        // Etiqueta del cliente para la interfaz
        if ($comanda['reserva_id'] !== null) {
            $comanda['cliente_tipo']  = 'huesped';
            $comanda['cliente_texto'] = $comanda['h_nombre'] . ' ' . $comanda['h_apellidos']
                . ' · ' . $comanda['unidad_nombre'];
        } elseif (! empty($comanda['cliente_nombre'])) {
            $comanda['cliente_tipo']  = 'ocasional';
            $comanda['cliente_texto'] = $comanda['cliente_nombre'];
        } else {
            $comanda['cliente_tipo']  = 'ninguno';
            $comanda['cliente_texto'] = 'Sin cliente asignado';
        }

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
