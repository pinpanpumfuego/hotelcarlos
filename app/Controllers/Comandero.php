<?php

namespace App\Controllers;

use App\Filters\Comandero as FiltroComandero;
use App\Models\CartaCategoriaModel;
use App\Models\CartaProductoModel;
use App\Models\ComandaLineaModel;
use App\Models\ComandaModel;
use App\Models\ComanderoBorradorModel;
use App\Models\EmpleadoModel;
use App\Models\LineaModificadorModel;
use App\Models\MesaModel;
use App\Models\ModificadorGrupoModel;
use App\Models\ModificadorModel;
use App\Models\ReservaModel;

/**
 * Comandero: el camarero toma nota desde su propio teléfono.
 *
 * Dos decisiones marcan todo lo demás:
 *
 * 1. **No se cobra desde aquí.** El dinero se queda en la caja fija. Si se
 *    pierde el teléfono no se pierde dinero, y el arqueo sigue teniendo un
 *    solo sitio del que responder.
 *
 * 2. **Tiene que funcionar sin señal.** El hotel está junto a un lago y el
 *    wifi no llega al muelle ni a la fogata. El teléfono guarda la carta y
 *    acumula la ronda entera; cuando vuelve la cobertura la manda de una vez
 *    a `enviar()`. Por eso no hay un endpoint por plato: una ronda es un solo
 *    envío atómico, identificado por un `uuid` que el teléfono no cambia al
 *    reintentar. Así un reintento de algo que sí había llegado no duplica
 *    platos en cocina.
 */
class Comandero extends BaseController
{
    private ComandaModel $comandas;
    private ComandaLineaModel $lineas;

    public function __construct()
    {
        $this->comandas = new ComandaModel();
        $this->lineas   = new ComandaLineaModel();
    }

    // ───────────────────────────── Acceso ─────────────────────────────

    public function index()
    {
        $empleado = $this->actual();

        if ($empleado === null) {
            return view('comandero/entrar', [
                'hotel'  => config('Hotel'),
                'titulo' => 'Comandero',
            ]);
        }

        return view('comandero/app', [
            'hotel'    => config('Hotel'),
            'titulo'   => 'Comandero',
            'empleado' => [
                'id'     => (int) $empleado['id'],
                'nombre' => $empleado['nombre'],
                'rol'    => $empleado['rol_tpv'],
            ],
        ]);
    }

    public function entrar()
    {
        // Freno: un documento y cuatro cifras se prueban solos en un rato
        $clave = 'comandero-' . md5($this->request->getIPAddress());
        if (service('throttler')->check($clave, 8, MINUTE) === false) {
            return redirect()->to('comandero')->with('error', 'Demasiados intentos. Espera un minuto.');
        }

        $documento = trim((string) $this->request->getPost('documento'));
        $pin       = trim((string) $this->request->getPost('pin'));

        $empleado = (new EmpleadoModel())->porPin($pin);

        if ($empleado === null || $empleado['num_documento'] !== $documento) {
            log_message('warning', 'Acceso fallido al comandero desde {ip}', ['ip' => $this->request->getIPAddress()]);

            return redirect()->to('comandero')->with('error', 'El documento o el PIN no coinciden.');
        }

        if ((int) $empleado['activo'] !== 1) {
            return redirect()->to('comandero')->with('error', 'Tu ficha está inactiva. Habla con gerencia.');
        }

        if (! in_array($empleado['rol_tpv'], FiltroComandero::ROLES, true)) {
            return redirect()->to('comandero')->with('error', 'Tu ficha no tiene permiso para tomar comandas. Habla con gerencia.');
        }

        session()->regenerate();
        session()->set([
            'empleado_id'     => (int) $empleado['id'],
            'empleado_nombre' => $empleado['nombre'],
        ]);

        return redirect()->to('comandero');
    }

    public function salir()
    {
        session()->remove(['empleado_id', 'empleado_nombre']);

        return redirect()->to('comandero')->with('ok', 'Sesión cerrada.');
    }

    /** El camarero de esta sesión, o null. */
    private function actual(): ?array
    {
        $id = session()->get('empleado_id');
        if ($id === null) {
            return null;
        }

        $empleado = (new EmpleadoModel())->find((int) $id);

        if ($empleado === null || (int) $empleado['activo'] !== 1
            || ! in_array($empleado['rol_tpv'], FiltroComandero::ROLES, true)) {
            return null;
        }

        return $empleado;
    }

    private function empleadoId(): int
    {
        return (int) session()->get('empleado_id');
    }

    // ─────────────────────────────── API ───────────────────────────────

    /**
     * Todo lo que el teléfono necesita para trabajar sin señal.
     *
     * Se manda la carta entera de una vez: son unos pocos kilobytes y evita
     * que el camarero se quede a medias por perder la cobertura entre plato
     * y plato.
     */
    public function estado()
    {
        $categorias = (new CartaCategoriaModel())->ordenadas();
        $productos  = (new CartaProductoModel())->conCategoria(true);

        $asignaciones = db_connect()->table('producto_modificador_grupos')->get()->getResultArray();
        $gruposPorProducto = [];
        foreach ($asignaciones as $a) {
            $gruposPorProducto[(int) $a['producto_id']][] = (int) $a['grupo_id'];
        }
        $todosGrupos = (new ModificadorGrupoModel())->conOpciones();

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
            if (! isset($porCategoria[$p['categoria_id']])) {
                continue;
            }
            $ids = $gruposPorProducto[(int) $p['id']] ?? [];

            $porCategoria[$p['categoria_id']]['productos'][] = [
                'id'        => (int) $p['id'],
                'nombre'    => $p['nombre'],
                'precio'    => (float) $p['precio'],
                // Para que el teléfono pueda decir a dónde va cada cosa antes
                // de mandarla, y sin tener que preguntar al servidor
                'destino'   => $p['destino'] ?? 'cocina',
                'divisible' => (int) ($p['divisible'] ?? 0) === 1,
                'picante'   => (int) ($p['picante'] ?? 0),
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

        return $this->response->setJSON([
            'ok'         => true,
            'mesas'      => (new MesaModel())->conEstado(),
            'categorias' => array_values($porCategoria),
            'alojados'   => $this->alojados(),
            'listos'     => $this->lineas->listosDetalle(),
            'sinMesa'    => $this->comandas
                ->select('id, numero, mesa, total, created_at')
                ->where('estado', 'abierta')
                ->where('mesa_id IS NULL')
                ->orderBy('created_at')
                ->findAll(),
            'servidor'   => time(),
        ]);
    }

    /** Huéspedes alojados, para cargar la consumición a su cabaña. */
    private function alojados(): array
    {
        return (new ReservaModel())
            ->select('reservas.id, reservas.codigo, unidades.nombre AS unidad,
                      huespedes.nombre, huespedes.apellidos')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('reservas.estado', 'checkin')
            ->orderBy('unidades.nombre')
            ->findAll();
    }

    /**
     * Recibe una ronda entera y la vuelca en una comanda.
     *
     * Es el único punto por el que entra lo que se tomó en el móvil. Todo o
     * nada: si una línea falla, no se queda media ronda a medias en cocina.
     */
    public function enviar()
    {
        $datos = $this->request->getJSON(true) ?? [];
        $uuid  = trim((string) ($datos['uuid'] ?? ''));

        if ($uuid === '' || strlen($uuid) > 40) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Falta el identificador de la ronda.']);
        }

        // ¿Ya había llegado? Entonces esto es un reintento y no se toca nada
        $envios = db_connect()->table('comandero_envios');
        $previo = $envios->where('uuid', $uuid)->get()->getRowArray();
        if ($previo !== null) {
            return $this->response->setJSON([
                'ok'         => true,
                'repetida'   => true,
                'comanda_id' => (int) $previo['comanda_id'],
                'comanda'    => $this->detalle((int) $previo['comanda_id']),
            ]);
        }

        $lineas = (array) ($datos['lineas'] ?? []);
        if ($lineas === []) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'La ronda no lleva nada.']);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $comandaId = $this->resolverComanda($datos);
            if (is_string($comandaId)) {
                $db->transRollback();

                return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $comandaId]);
            }

            $insertadas = 0;
            foreach ($lineas as $l) {
                $error = $this->insertarLinea($comandaId, (array) $l);
                if ($error !== null) {
                    $db->transRollback();

                    return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $error]);
                }
                $insertadas++;
            }

            $this->comandas->recalcularTotal($comandaId);

            // Cada cosa a su sitio: cocina, barra o entrega directa. Es la misma
            // función que usa el TPV fijo, para que no vuelvan a divergir.
            $aCocina = ! empty($datos['a_cocina']);
            $conteo  = ['cocina' => 0, 'barra' => 0, 'directo' => 0];
            if ($aCocina) {
                $conteo = $this->lineas->enviarAPreparacion($comandaId);

                // La pantalla de cocina ordena por `updated_at` y lo toma como
                // la hora de envío, así que se sella ahora: si no, una ronda que
                // pasó media hora en el teléfono aparecería como si llevara
                // media hora esperando en cocina y saldría la primera.
                $this->lineas->builder()
                    ->where('comanda_id', $comandaId)
                    ->where('enviado_cocina', 1)
                    ->where('entregado', 0)
                    ->update(['updated_at' => date('Y-m-d H:i:s')]);
            }

            // Cuánto tardó en llegar: si sale grande y siempre en la misma zona,
            // el problema es el wifi de esa zona, no el teléfono
            $tomada = (int) ($datos['tomada_en'] ?? 0);
            $demora = $tomada > 0 ? max(0, time() - $tomada) : 0;

            // Lo apuntado ya es real: el borrador de esa mesa sobra y hay que
            // quitarlo, o el TPV seguiría diciendo que se está tomando nota
            $borradores = new ComanderoBorradorModel();
            $borradores->borrarDeComanda($comandaId);
            if (! empty($datos['clave'])) {
                $borradores->borrar($this->empleadoId(), (string) $datos['clave']);
            }

            $envios->insert([
                'uuid'        => $uuid,
                'comanda_id'  => $comandaId,
                'empleado_id' => $this->empleadoId(),
                'lineas'      => $insertadas,
                'a_cocina'    => $aCocina ? 1 : 0,
                'demora_seg'  => min($demora, 86400),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Comandero: ronda {uuid} no guardada: {m}', ['uuid' => $uuid, 'm' => $e->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON([
                'ok' => false, 'error' => 'No se pudo guardar la ronda. Sigue en el teléfono, inténtalo otra vez.',
            ]);
        }

        return $this->response->setJSON([
            'ok'         => true,
            'comanda_id' => $comandaId,
            'reparto'    => $conteo,
            'comanda'    => $this->detalle($comandaId),
        ]);
    }

    /**
     * Decide en qué comanda va la ronda: la abierta de la mesa, o una nueva.
     *
     * Devuelve el id, o un texto con el motivo si no se puede.
     */
    private function resolverComanda(array $datos)
    {
        $comandaId = (int) ($datos['comanda_id'] ?? 0);
        if ($comandaId > 0) {
            $c = $this->comandas->find($comandaId);
            if ($c !== null && $c['estado'] === 'abierta') {
                return $comandaId;
            }
            // Se cobró mientras el teléfono estaba sin señal: no se reabre,
            // se abre una nueva en la misma mesa si la hay
        }

        $mesaId = (int) ($datos['mesa_id'] ?? 0) ?: null;
        $mesa   = null;

        if ($mesaId !== null) {
            $abierta = $this->comandas->where('mesa_id', $mesaId)->where('estado', 'abierta')->first();
            if ($abierta !== null) {
                return (int) $abierta['id'];
            }
            $mesa = (new MesaModel())->find($mesaId);
            if ($mesa === null) {
                return 'Esa mesa ya no existe.';
            }
        }

        $reservaId = (int) ($datos['reserva_id'] ?? 0) ?: null;
        if ($reservaId !== null) {
            $reserva = (new ReservaModel())->find($reservaId);
            if ($reserva === null || $reserva['estado'] !== 'checkin') {
                return 'Ese huésped ya no está alojado.';
            }
        }

        $nombre = trim((string) ($datos['nombre'] ?? ''));

        if ($mesaId === null && $reservaId === null && $nombre === '') {
            return 'Indica la mesa, el huésped o un nombre.';
        }

        // Quien responde de esta comanda es el empleado: en el móvil no hay
        // usuario del sistema, y por eso `usuario_id` admite nulos
        $nueva = $this->comandas->insert([
            'numero'      => $this->comandas->generarNumero(),
            'mesa_id'     => $mesaId,
            'mesa'        => $mesaId !== null ? ($mesa['nombre'] ?? null) : ($nombre ?: null),
            'reserva_id'  => $reservaId,
            'comensales'  => max(1, (int) ($datos['comensales'] ?? 1)),
            'estado'      => 'abierta',
            'usuario_id'  => null,
            'empleado_id' => $this->empleadoId(),
        ]);

        // Si la comanda no se pudo crear hay que pararse aquí. Seguir con un
        // id 0 metería las líneas en ninguna parte y el camarero creería que
        // su ronda llegó a cocina.
        if (empty($nueva)) {
            return 'No se pudo abrir la comanda. Avisa a recepción.';
        }

        return (int) $nueva;
    }

    /**
     * Mete una línea. Devuelve null si fue bien o el motivo si no.
     *
     * El precio sale siempre de la base de datos, nunca de lo que mande el
     * teléfono: la carta cacheada puede tener días y un precio viejo.
     */
    private function insertarLinea(int $comandaId, array $l): ?string
    {
        $productos = new CartaProductoModel();
        $producto  = $productos->find((int) ($l['producto_id'] ?? 0));

        if ($producto === null) {
            return 'Uno de los platos ya no está en la carta.';
        }

        $cantidad = max(1, min(99, (int) ($l['cantidad'] ?? 1)));
        $nombre   = $producto['nombre'];
        $precio   = (float) $producto['precio'];
        $composicion = null;

        // Mitad y mitad
        $mitadId = (int) ($l['mitad_id'] ?? 0);
        if ($mitadId > 0) {
            $otra = $productos->find($mitadId);
            if ($otra === null || empty($producto['divisible']) || empty($otra['divisible'])) {
                return 'Solo se pueden combinar dos productos divisibles.';
            }
            $precio      = max((float) $producto['precio'], (float) $otra['precio']);
            $nombre      = '½ ' . $producto['nombre'] . ' + ½ ' . $otra['nombre'];
            $composicion = $producto['nombre'] . ' | ' . $otra['nombre'];
        }

        // Modificadores
        $elegidos = [];
        $idsMod   = array_map('intval', (array) ($l['modificadores'] ?? []));
        if ($idsMod !== []) {
            foreach ((new ModificadorModel())->whereIn('id', $idsMod)->findAll() as $o) {
                $elegidos[] = [
                    'modificador_id' => (int) $o['id'],
                    'nombre'         => $o['nombre'],
                    'precio_extra'   => (float) $o['precio_extra'],
                ];
                $precio += (float) $o['precio_extra'];
            }
        }

        $lineaId = $this->lineas->insert([
            'comanda_id'      => $comandaId,
            'producto_id'     => (int) $producto['id'],
            'nombre_producto' => $nombre,
            'composicion'     => $composicion,
            'destino'         => $producto['destino'] ?? 'cocina',
            'precio_unitario' => $precio,
            'cantidad'        => $cantidad,
            'notas'           => trim((string) ($l['notas'] ?? '')) ?: null,
            'empleado_id'     => $this->empleadoId(),
        ]);

        if ($elegidos !== []) {
            $modelo = new LineaModificadorModel();
            foreach ($elegidos as $e) {
                $modelo->insert($e + ['linea_id' => $lineaId]);
            }
        }

        return null;
    }

    /**
     * Guarda lo que el camarero lleva apuntado sin enviar.
     *
     * Existe para que quien está en el TPV vea la mesa atendiéndose en vivo,
     * en vez de esperar a que se pulse «Enviar a cocina». Es informativo: ni
     * va a cocina, ni suma al total, ni se factura. Si no hay señal no llega
     * nada y el TPV simplemente no lo enseña; lo apuntado sigue a salvo en el
     * teléfono, que es lo que no puede fallar.
     */
    public function borrador()
    {
        $datos = $this->request->getJSON(true) ?? [];
        $clave = trim((string) ($datos['clave'] ?? ''));

        if ($clave === '' || mb_strlen($clave) > 60) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => 'Destino no válido.']);
        }

        (new ComanderoBorradorModel())->guardar($this->empleadoId(), $clave, $datos);

        return $this->response->setJSON(['ok' => true]);
    }

    /** La comanda con sus líneas, para pintarla en el teléfono. */
    public function comanda(int $id)
    {
        $detalle = $this->detalle($id);

        if ($detalle === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'La comanda no existe.']);
        }

        return $this->response->setJSON(['ok' => true, 'comanda' => $detalle]);
    }

    private function detalle(int $id): ?array
    {
        $comanda = $this->comandas
            ->select('comandas.*, unidades.nombre AS unidad_nombre,
                      huespedes.nombre AS h_nombre, huespedes.apellidos AS h_apellidos')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->join('unidades', 'unidades.id = reservas.unidad_id', 'left')
            ->where('comandas.id', $id)
            ->first();

        if ($comanda === null) {
            return null;
        }

        $comanda['lineas'] = $this->lineas->deComanda($id);

        return $comanda;
    }

    /** Marca un plato como servido en la mesa. */
    public function servir(int $lineaId)
    {
        $linea = $this->lineas->find($lineaId);

        if ($linea === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Esa línea no existe.']);
        }

        $this->lineas->update($lineaId, ['servido' => 1]);

        return $this->response->setJSON(['ok' => true, 'listos' => $this->lineas->listosDetalle()]);
    }

    /** Solo lo que cambia a menudo: mesas y platos listos. Se pide cada poco. */
    public function pulso()
    {
        return $this->response->setJSON([
            'ok'     => true,
            'mesas'  => (new MesaModel())->conEstado(),
            'listos' => $this->lineas->listosDetalle(),
        ]);
    }

    // ────────────────────────────── PWA ────────────────────────────────

    public function manifiesto()
    {
        $hotel = config('Hotel');

        return $this->response
            ->setContentType('application/manifest+json')
            ->setJSON([
                'name'             => 'Comandero · ' . $hotel->nombre,
                'short_name'       => 'Comandero',
                'description'      => 'Toma de comandas del restaurante de ' . $hotel->nombre,
                'start_url'        => site_url('comandero'),
                'scope'            => site_url('comandero'),
                'display'          => 'standalone',
                'orientation'      => 'portrait',
                'background_color' => '#12281d',
                'theme_color'      => '#12281d',
                'lang'             => 'es-CO',
                'icons'            => [
                    ['src' => base_url('icono-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => base_url('icono-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ],
            ]);
    }
}
