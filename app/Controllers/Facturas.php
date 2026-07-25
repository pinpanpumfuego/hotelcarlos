<?php

namespace App\Controllers;

use App\Libraries\Siigo;
use App\Models\ComandaModel;
use App\Models\ConfiguracionModel;
use App\Models\FacturaModel;
use App\Models\FolioModel;
use App\Models\ReservaModel;

/**
 * Facturación electrónica a través de Siigo.
 *
 * La emisión siempre es una acción explícita del personal: una factura
 * electrónica tiene efectos fiscales y no debe salir sola.
 */
class Facturas extends BaseController
{
    private FacturaModel $facturas;
    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->facturas = new FacturaModel();
        $this->config   = new ConfiguracionModel();
    }

    public function index()
    {
        $siigo = new Siigo();

        return view('facturas/index', [
            'titulo'      => 'Facturación electrónica',
            'seccion'     => 'facturas',
            'facturas'    => $this->facturas->listado((string) $this->request->getGet('estado') ?: null),
            'estados'     => FacturaModel::ESTADOS,
            'filtro'      => (string) $this->request->getGet('estado'),
            'configurado' => $siigo->configurado(),
            'listoParaFacturar' => $this->listoParaFacturar(),
        ]);
    }

    public function ver(int $id)
    {
        $factura = $this->facturas->find($id);
        if ($factura === null) {
            return redirect()->to('facturas')->with('error', 'La factura no existe.');
        }

        return view('facturas/ver', [
            'titulo'  => 'Factura ' . ($factura['numero'] ?: '#' . $factura['id']),
            'seccion' => 'facturas',
            'factura' => $factura,
            'lineas'  => db_connect()->table('factura_lineas')->where('factura_id', $id)->get()->getResultArray(),
            'estados' => FacturaModel::ESTADOS,
        ]);
    }

    /** Prepara la factura de una reserva a partir de su folio. */
    public function desdeReserva(int $reservaId)
    {
        $reserva = (new ReservaModel())
            ->select('reservas.*, huespedes.nombre, huespedes.apellidos, huespedes.tipo_documento,
                      huespedes.num_documento, huespedes.email, unidades.nombre AS unidad_nombre')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id')
            ->join('unidades', 'unidades.id = reservas.unidad_id')
            ->where('reservas.id', $reservaId)
            ->first();

        if ($reserva === null) {
            return redirect()->to('reservas')->with('error', 'La reserva no existe.');
        }

        $existente = $this->facturas->deReserva($reservaId);
        if ($existente !== null && $existente['estado'] === 'emitida') {
            return redirect()->to('facturas/ver/' . $existente['id'])
                ->with('error', 'Esta reserva ya tiene una factura emitida. Para corregirla hay que emitir una nota crédito desde Siigo.');
        }

        // Los cargos del folio son las líneas de la factura
        $movimientos = (new FolioModel())->where('reserva_id', $reservaId)->where('tipo', 'cargo')->findAll();
        if ($movimientos === []) {
            return redirect()->to('reservas/ver/' . $reservaId)
                ->with('error', 'La reserva no tiene cargos en el folio: no hay nada que facturar.');
        }

        $lineas = [];
        foreach ($movimientos as $m) {
            $lineas[] = [
                'codigo'      => $this->codigoProducto($m['concepto']),
                'descripcion' => $m['concepto'],
                'cantidad'    => 1,
                'precio'      => (float) $m['valor'],
            ];
        }

        return view('facturas/preparar', [
            'titulo'   => 'Facturar reserva ' . $reserva['codigo'],
            'seccion'  => 'facturas',
            'origen'   => 'reserva',
            'registro' => $reserva,
            'cliente'  => [
                'nombre'    => trim($reserva['nombre'] . ' ' . $reserva['apellidos']),
                'documento' => $reserva['num_documento'],
                'email'     => $reserva['email'],
            ],
            'lineas'   => $lineas,
            'total'    => array_sum(array_column($lineas, 'precio')),
            'config'   => $this->parametros(),
            'listo'    => $this->listoParaFacturar(),
        ]);
    }

    /** Prepara la factura de una comanda del restaurante. */
    public function desdeComanda(int $comandaId)
    {
        $comanda = (new ComandaModel())
            ->select('comandas.*, huespedes.nombre, huespedes.apellidos, huespedes.num_documento, huespedes.email')
            ->join('reservas', 'reservas.id = comandas.reserva_id', 'left')
            ->join('huespedes', 'huespedes.id = reservas.huesped_id', 'left')
            ->where('comandas.id', $comandaId)
            ->first();

        if ($comanda === null) {
            return redirect()->to('tpv')->with('error', 'La comanda no existe.');
        }

        $existente = $this->facturas->deComanda($comandaId);
        if ($existente !== null && $existente['estado'] === 'emitida') {
            return redirect()->to('facturas/ver/' . $existente['id'])->with('error', 'Esta comanda ya está facturada.');
        }

        $lineas = [];
        foreach (db_connect()->table('comanda_lineas')->where('comanda_id', $comandaId)->get()->getResultArray() as $l) {
            $lineas[] = [
                'codigo'      => 'REST',
                'descripcion' => $l['nombre_producto'],
                'cantidad'    => (int) $l['cantidad'],
                'precio'      => (float) $l['precio_unitario'],
            ];
        }

        if ($lineas === []) {
            return redirect()->to('tpv')->with('error', 'La comanda está vacía.');
        }

        return view('facturas/preparar', [
            'titulo'   => 'Facturar comanda ' . $comanda['numero'],
            'seccion'  => 'facturas',
            'origen'   => 'comanda',
            'registro' => $comanda,
            'cliente'  => [
                'nombre'    => trim((string) ($comanda['cliente_nombre'] ?: ($comanda['nombre'] ?? '') . ' ' . ($comanda['apellidos'] ?? ''))),
                'documento' => (string) ($comanda['cliente_documento'] ?: ($comanda['num_documento'] ?? '')),
                'email'     => (string) ($comanda['email'] ?? ''),
            ],
            'lineas'   => $lineas,
            'total'    => (float) $comanda['total'] - (float) $comanda['descuento'] + (float) $comanda['propina'],
            'config'   => $this->parametros(),
            'listo'    => $this->listoParaFacturar(),
        ]);
    }

    /** Envía la factura a Siigo y guarda el resultado. */
    public function emitir()
    {
        $siigo = new Siigo();
        if (! $siigo->configurado()) {
            return redirect()->to('administracion')->with('error', 'Configura primero las credenciales de Siigo.');
        }

        $parametros = $this->parametros();
        if ($parametros['documento_id'] === '' || $parametros['vendedor_id'] === '' || $parametros['pago_id'] === '') {
            return redirect()->to('administracion')
                ->with('error', 'Faltan los parámetros de facturación: tipo de documento, vendedor y forma de pago.');
        }

        $post   = $this->request->getPost();
        $origen = ($post['origen'] ?? '') === 'comanda' ? 'comanda' : 'reserva';
        $refId  = (int) ($post['referencia_id'] ?? 0);

        $nombre    = trim((string) ($post['cliente_nombre'] ?? ''));
        $documento = preg_replace('/\D/', '', (string) ($post['cliente_documento'] ?? ''));
        $email     = trim((string) ($post['cliente_email'] ?? ''));

        if ($nombre === '' || $documento === '') {
            return redirect()->back()->withInput()
                ->with('error', 'La factura electrónica exige identificar al cliente: nombre y número de documento.');
        }

        $lineas = $this->lineasDelFormulario($post);
        if ($lineas === []) {
            return redirect()->back()->with('error', 'No hay líneas que facturar.');
        }

        $total = 0.0;
        foreach ($lineas as $l) {
            $total += $l['precio'] * $l['cantidad'];
        }

        // Se guarda antes de enviar: si Siigo falla, queda el rastro del intento
        $facturaId = $this->facturas->insert([
            'origen'            => $origen,
            'reserva_id'        => $origen === 'reserva' ? $refId : null,
            'comanda_id'        => $origen === 'comanda' ? $refId : null,
            'cliente_nombre'    => $nombre,
            'cliente_documento' => $documento,
            'cliente_email'     => $email ?: null,
            'subtotal'          => $total,
            'impuestos'         => 0,
            'total'             => $total,
            'estado'            => 'pendiente',
            'usuario_id'        => session()->get('usuario_id'),
        ]);

        $tablaLineas = db_connect()->table('factura_lineas');
        foreach ($lineas as $l) {
            $tablaLineas->insert($l + [
                'factura_id' => $facturaId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Estructura que espera la API de Siigo
        $cuerpo = [
            'document' => ['id' => (int) $parametros['documento_id']],
            'date'     => date('Y-m-d'),
            'customer' => [
                'identification' => $documento,
                'branch_office'  => 0,
            ],
            'seller'       => (int) $parametros['vendedor_id'],
            'observations' => trim((string) ($post['observaciones'] ?? '')),
            'items'        => [],
            'payments'     => [[
                'id'       => (int) $parametros['pago_id'],
                'value'    => round($total, 2),
                'due_date' => date('Y-m-d'),
            ]],
        ];

        // La DIAN solo recibe el documento si se pide el timbrado
        if ($parametros['enviar_dian']) {
            $cuerpo['stamp'] = ['send' => true];
        }
        if ($email !== '' && $parametros['enviar_correo']) {
            $cuerpo['mail'] = ['send' => true];
        }

        foreach ($lineas as $l) {
            $item = [
                'code'        => $l['codigo'],
                'description' => mb_substr($l['descripcion'], 0, 255),
                'quantity'    => (float) $l['cantidad'],
                'price'       => round((float) $l['precio'], 2),
            ];
            if ($parametros['impuesto_id'] !== '') {
                $item['taxes'] = [['id' => (int) $parametros['impuesto_id']]];
            }
            $cuerpo['items'][] = $item;
        }

        $respuesta = $siigo->crearFactura($cuerpo);

        if (! $respuesta['ok']) {
            $this->facturas->update($facturaId, [
                'estado'    => 'error',
                'error'     => $respuesta['error'],
                'respuesta' => json_encode($respuesta['datos'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);

            return redirect()->to('facturas/ver/' . $facturaId)
                ->with('error', 'Siigo no aceptó la factura: ' . $respuesta['error']);
        }

        $datos = $respuesta['datos'];

        $this->facturas->update($facturaId, [
            'estado'      => 'emitida',
            'siigo_id'    => (string) ($datos['id'] ?? ''),
            'numero'      => trim((string) ($datos['name'] ?? '') ?: (string) ($datos['number'] ?? '')),
            'cufe'        => (string) ($datos['stamp']['cufe'] ?? $datos['cufe'] ?? ''),
            'url_publica' => (string) ($datos['public_url'] ?? ''),
            'respuesta'   => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'emitida_en'  => date('Y-m-d H:i:s'),
            'error'       => null,
        ]);

        return redirect()->to('facturas/ver/' . $facturaId)->with('ok', 'Factura emitida correctamente.');
    }

    /** Reenvía por correo una factura ya emitida. */
    public function reenviar(int $id)
    {
        $factura = $this->facturas->find($id);
        if ($factura === null || $factura['estado'] !== 'emitida' || empty($factura['siigo_id'])) {
            return redirect()->to('facturas')->with('error', 'Solo se pueden reenviar facturas emitidas.');
        }

        $correo = trim((string) $this->request->getPost('correo')) ?: (string) $factura['cliente_email'];
        if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return redirect()->to('facturas/ver/' . $id)->with('error', 'Indica un correo válido.');
        }

        $r = (new Siigo())->enviarPorCorreo($factura['siigo_id'], $correo);

        return redirect()->to('facturas/ver/' . $id)->with($r['ok'] ? 'ok' : 'error', $r['ok']
            ? 'Factura reenviada a ' . $correo . '.'
            : 'No se pudo reenviar: ' . $r['error']);
    }

    // ─────────────────────────── Ayudantes ───────────────────────────

    /** Parámetros de facturación elegidos en Administración. */
    private function parametros(): array
    {
        return [
            'documento_id'  => (string) $this->config->obtener('siigo_documento_id', ''),
            'vendedor_id'   => (string) $this->config->obtener('siigo_vendedor_id', ''),
            'pago_id'       => (string) $this->config->obtener('siigo_pago_id', ''),
            'impuesto_id'   => (string) $this->config->obtener('siigo_impuesto_id', ''),
            'enviar_dian'   => $this->config->obtener('siigo_enviar_dian', '1') === '1',
            'enviar_correo' => $this->config->obtener('siigo_enviar_correo', '1') === '1',
        ];
    }

    /** ¿Está todo lo necesario para poder facturar? */
    private function listoParaFacturar(): array
    {
        $siigo = new Siigo();
        $p     = $this->parametros();

        $faltan = [];
        if (! $siigo->configurado()) {
            $faltan[] = 'las credenciales de Siigo';
        }
        if ($p['documento_id'] === '') {
            $faltan[] = 'el tipo de documento (resolución de facturación)';
        }
        if ($p['vendedor_id'] === '') {
            $faltan[] = 'el vendedor';
        }
        if ($p['pago_id'] === '') {
            $faltan[] = 'la forma de pago';
        }

        return ['ok' => $faltan === [], 'faltan' => $faltan];
    }

    /** Código de producto para Siigo, según el concepto del folio. */
    private function codigoProducto(string $concepto): string
    {
        $c = mb_strtolower($concepto);

        if (str_contains($c, 'alojamiento')) {
            return (string) $this->config->obtener('siigo_codigo_alojamiento', 'ALOJ');
        }
        if (str_contains($c, 'restaurante')) {
            return (string) $this->config->obtener('siigo_codigo_restaurante', 'REST');
        }

        return (string) $this->config->obtener('siigo_codigo_otros', 'OTROS');
    }

    /** Líneas editadas en el formulario de preparación. */
    private function lineasDelFormulario(array $post): array
    {
        $codigos      = (array) ($post['linea_codigo'] ?? []);
        $descripciones = (array) ($post['linea_descripcion'] ?? []);
        $cantidades   = (array) ($post['linea_cantidad'] ?? []);
        $precios      = (array) ($post['linea_precio'] ?? []);

        $lineas = [];
        foreach ($descripciones as $i => $desc) {
            $desc   = trim((string) $desc);
            $precio = (float) ($precios[$i] ?? 0);
            $cant   = max(1, (int) ($cantidades[$i] ?? 1));

            if ($desc === '' || $precio <= 0) {
                continue;
            }

            $lineas[] = [
                'codigo'      => mb_substr(trim((string) ($codigos[$i] ?? 'OTROS')) ?: 'OTROS', 0, 60),
                'descripcion' => mb_substr($desc, 0, 255),
                'cantidad'    => $cant,
                'precio'      => $precio,
            ];
        }

        return $lineas;
    }
}
