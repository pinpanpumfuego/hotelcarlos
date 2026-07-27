<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\BodegaModel;
use App\Models\ExistenciaModel;
use App\Models\LoteModel;
use App\Models\MovimientoStockModel;
use RuntimeException;

/**
 * El motor del almacén: todo lo que entra, sale o se mueve pasa por aquí.
 *
 * **Nadie escribe en `existencias` directamente.** Si el saldo se pudiera tocar
 * a mano, dejaría de coincidir con los movimientos y la pregunta «¿por qué hay
 * tres kilos y no cinco?» se quedaría sin respuesta. Aquí siempre se hacen las
 * dos cosas a la vez, en transacción: se apunta el movimiento y se ajusta el
 * saldo.
 *
 * Sobre el coste: **promedio ponderado**. En cada entrada se recalcula mezclando
 * lo que había con lo que llega. Es menos exacto que ir por lotes, pero no
 * obliga a arrastrar de qué lote salió cada gramo, y para una cocina de hotel
 * da un número suficientemente bueno. Los lotes siguen ahí para lo que de
 * verdad importan: saber qué caduca y de dónde vino.
 */
class Almacen
{
    private MovimientoStockModel $movimientos;
    private ExistenciaModel $existencias;
    private LoteModel $lotes;

    /** Tipos que suman al saldo. El resto resta. */
    public const SUMAN = ['entrada', 'devolucion'];

    public function __construct()
    {
        $this->movimientos = new MovimientoStockModel();
        $this->existencias = new ExistenciaModel();
        $this->lotes       = new LoteModel();
    }

    /**
     * Registra una entrada de mercancía.
     *
     * @param array{lote?: string, caduca_el?: string, proveedor_id?: int} $lote
     */
    public function entrada(
        int $insumoId,
        int $bodegaId,
        float $cantidad,
        float $costoUnitario,
        ?string $motivo = null,
        array $lote = [],
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
    ): int {
        if ($cantidad <= 0) {
            throw new RuntimeException('Una entrada tiene que ser de una cantidad positiva.');
        }

        return $this->registrar([
            'tipo'            => 'entrada',
            'insumo_id'       => $insumoId,
            'bodega_id'       => $bodegaId,
            'cantidad'        => abs($cantidad),
            'costo_unitario'  => max(0, $costoUnitario),
            'motivo'          => $motivo,
            'lote'            => $lote,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id'   => $referenciaId,
        ]);
    }

    /**
     * Registra una salida: venta, merma, consumo interno, cortesía…
     *
     * **No se impide sacar más de lo que hay.** Un saldo negativo significa que
     * se vendió algo que el sistema no sabía que existía, y eso es información,
     * no un error que deba parar el servicio. Bloquearlo dejaría a un camarero
     * sin poder cobrar una mesa por un descuadre de almacén.
     */
    public function salida(
        int $insumoId,
        int $bodegaId,
        float $cantidad,
        string $tipo = 'salida',
        ?string $motivo = null,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
    ): int {
        if ($cantidad <= 0) {
            throw new RuntimeException('Una salida tiene que ser de una cantidad positiva.');
        }

        return $this->registrar([
            'tipo'            => $tipo,
            'insumo_id'       => $insumoId,
            'bodega_id'       => $bodegaId,
            'cantidad'        => -abs($cantidad),
            'costo_unitario'  => $this->costoActual($insumoId, $bodegaId),
            'motivo'          => $motivo,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id'   => $referenciaId,
        ]);
    }

    /**
     * Mueve mercancía de una bodega a otra.
     *
     * Son dos movimientos, no uno: la mercancía sale de un sitio y entra en
     * otro. Con un solo apunte no se podría explicar el saldo de ninguna de las
     * dos bodegas.
     *
     * @return array{salida: int, entrada: int}
     */
    public function traslado(int $insumoId, int $origen, int $destino, float $cantidad, ?string $motivo = null): array
    {
        if ($origen === $destino) {
            throw new RuntimeException('El origen y el destino no pueden ser la misma bodega.');
        }
        if ($cantidad <= 0) {
            throw new RuntimeException('Un traslado tiene que ser de una cantidad positiva.');
        }

        $costo = $this->costoActual($insumoId, $origen);
        $db    = db_connect();

        $db->transStart();

        $sale = $this->registrar([
            'tipo'      => 'traslado',
            'insumo_id' => $insumoId,
            'bodega_id' => $origen,
            'cantidad'  => -abs($cantidad),
            'costo_unitario' => $costo,
            'motivo'    => $motivo ?? 'Traslado a otra bodega',
            'referencia_tipo' => 'traslado',
        ]);

        $entra = $this->registrar([
            'tipo'      => 'traslado',
            'insumo_id' => $insumoId,
            'bodega_id' => $destino,
            'cantidad'  => abs($cantidad),
            // El coste viaja con la mercancía: si no, trasladar cambiaría el
            // valor del inventario sin que nadie haya comprado ni vendido nada.
            'costo_unitario'  => $costo,
            'motivo'          => $motivo ?? 'Traslado desde otra bodega',
            'referencia_tipo' => 'traslado',
            'referencia_id'   => $sale,
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new RuntimeException('No se pudo completar el traslado.');
        }

        return ['salida' => $sale, 'entrada' => $entra];
    }

    /**
     * Ajusta el saldo a lo que se ha contado de verdad.
     *
     * No fija el saldo: apunta la **diferencia**. Así el movimiento explica
     * cuánto sobraba o faltaba, que es lo que se quiere saber después.
     */
    public function ajustar(int $insumoId, int $bodegaId, float $contado, ?string $motivo = null, ?int $conteoId = null): ?int
    {
        $actual     = $this->saldo($insumoId, $bodegaId);
        $diferencia = round($contado - $actual, 3);

        if (abs($diferencia) < 0.001) {
            return null;   // cuadraba: no hay nada que apuntar
        }

        return $this->registrar([
            'tipo'            => 'ajuste',
            'insumo_id'       => $insumoId,
            'bodega_id'       => $bodegaId,
            'cantidad'        => $diferencia,
            'costo_unitario'  => $this->costoActual($insumoId, $bodegaId),
            'motivo'          => $motivo ?? ($diferencia > 0 ? 'Sobra en el conteo' : 'Falta en el conteo'),
            'referencia_tipo' => 'conteo',
            'referencia_id'   => $conteoId,
        ]);
    }

    // ── Enganche con las ventas ─────────────────────────────────────────

    /**
     * Descuenta del almacén los ingredientes de una línea de comanda.
     *
     * Se llama **al enviar a preparación**, no al cobrar: el ingrediente se
     * gasta cuando el plato se hace, no cuando el cliente paga. Si la comanda
     * se anula después, se devuelve con `devolverReceta()`.
     *
     * **Es idempotente.** Enviar dos veces la misma línea —cosa que pasa: el
     * comandero reintenta cuando no hay cobertura— no puede descontar el doble.
     * Se comprueba por la referencia antes de tocar nada.
     *
     * Si el producto no tiene receta, no hace nada: no todos los platos tienen
     * escandallo, y no tenerlo no puede impedir venderlos.
     *
     * @return int cuántos insumos se descontaron
     */
    public function descontarReceta(int $comandaLineaId): int
    {
        $linea = db_connect()->table('comanda_lineas')
            ->where('id', $comandaLineaId)
            ->get()->getRowArray();

        if ($linea === null || empty($linea['producto_id'])) {
            return 0;
        }

        if ($this->yaMovido('comanda_linea', $comandaLineaId)) {
            return 0;
        }

        $receta = db_connect()->table('receta_lineas')
            ->select('receta_lineas.insumo_id, receta_lineas.cantidad')
            ->where('receta_lineas.producto_id', (int) $linea['producto_id'])
            ->get()->getResultArray();

        if ($receta === []) {
            return 0;
        }

        $bodegaId = $this->bodegaDeDestino($linea['destino'] ?? 'cocina');
        if ($bodegaId === null) {
            return 0;
        }

        // Una cortesía o una devolución también gastan producto: la cocina lo
        // hizo igual. Lo que cambia es el motivo, para saber dónde se va.
        $tipo = match ($linea['estado_linea'] ?? 'normal') {
            'cortesia'  => 'cortesia',
            'devuelta'  => 'merma',
            default     => 'salida',
        };

        $cantidadPlatos = max(1, (int) $linea['cantidad']);
        $movidos        = 0;

        foreach ($receta as $ingrediente) {
            $cantidad = (float) $ingrediente['cantidad'] * $cantidadPlatos;
            if ($cantidad <= 0) {
                continue;
            }

            $this->salida(
                (int) $ingrediente['insumo_id'],
                $bodegaId,
                $cantidad,
                $tipo,
                $linea['nombre_producto'] ?? null,
                'comanda_linea',
                $comandaLineaId
            );
            $movidos++;
        }

        return $movidos;
    }

    /**
     * Devuelve al almacén lo que gastó una línea que se anula.
     *
     * No borra los movimientos —eso rompería el libro— sino que apunta los
     * contrarios. Después de esto, la línea puede volver a descontarse si se
     * reenvía.
     */
    public function devolverReceta(int $comandaLineaId): int
    {
        $movimientos = $this->movimientos
            ->where('referencia_tipo', 'comanda_linea')
            ->where('referencia_id', $comandaLineaId)
            ->findAll();

        // Cuántas devoluciones hay ya apuntadas. Sin esta cuenta, llamar dos
        // veces —cosa que pasa: anular una comanda recorre sus líneas y alguna
        // puede haberse devuelto ya al quitarla— metería el stock dos veces.
        $yaDevueltas = $this->movimientos
            ->where('referencia_tipo', 'comanda_linea_anulada')
            ->where('referencia_id', $comandaLineaId)
            ->countAllResults();

        $salidas = 0;
        foreach ($movimientos as $m) {
            if ((float) $m['cantidad'] < 0) {
                $salidas++;
            }
        }

        if ($yaDevueltas >= $salidas) {
            return 0;
        }

        $devueltos = 0;

        foreach ($movimientos as $m) {
            // Solo se devuelve lo que salió; si ya hay una devolución apuntada,
            // no se devuelve otra vez.
            if ((float) $m['cantidad'] >= 0) {
                continue;
            }

            $this->registrar([
                'tipo'            => 'devolucion',
                'insumo_id'       => (int) $m['insumo_id'],
                'bodega_id'       => (int) $m['bodega_id'],
                'cantidad'        => abs((float) $m['cantidad']),
                'costo_unitario'  => (float) $m['costo_unitario'],
                'motivo'          => 'Anulación de ' . ($m['motivo'] ?? 'comanda'),
                'referencia_tipo' => 'comanda_linea_anulada',
                'referencia_id'   => $comandaLineaId,
            ]);
            $devueltos++;
        }

        return $devueltos;
    }

    /**
     * ¿Ya se movió stock por esta referencia?
     *
     * Cuenta salidas y devoluciones: si hay las mismas de cada, la línea está
     * en paz y se puede volver a descontar.
     */
    private function yaMovido(string $tipo, int $id): bool
    {
        $salidas = $this->movimientos
            ->where('referencia_tipo', $tipo)
            ->where('referencia_id', $id)
            ->countAllResults();

        $devueltas = $this->movimientos
            ->where('referencia_tipo', $tipo . '_anulada')
            ->where('referencia_id', $id)
            ->countAllResults();

        return $salidas > 0 && $salidas > $devueltas;
    }

    /**
     * De qué bodega sale cada cosa según a dónde va la comanda.
     *
     * Aprovecha el destino que ya lleva cada línea: lo de cocina sale de la
     * bodega de cocina y lo de barra de la del bar. Si no hay bodega de ese
     * tipo, se cae a la de por defecto.
     */
    public function bodegaDeDestino(string $destino): ?int
    {
        $tipo = match ($destino) {
            'barra' => 'bar',
            default => 'cocina',
        };

        $bodega = (new BodegaModel())->where('tipo', $tipo)->where('activa', 1)->first();

        return $bodega !== null ? (int) $bodega['id'] : $this->bodegaPorDefecto();
    }

    // ── Consultas ───────────────────────────────────────────────────────

    public function saldo(int $insumoId, int $bodegaId): float
    {
        $fila = $this->existencias
            ->where('insumo_id', $insumoId)
            ->where('bodega_id', $bodegaId)
            ->first();

        return (float) ($fila['cantidad'] ?? 0);
    }

    public function costoActual(int $insumoId, int $bodegaId): float
    {
        $fila = $this->existencias
            ->where('insumo_id', $insumoId)
            ->where('bodega_id', $bodegaId)
            ->first();

        // Sin existencias todavía, vale el coste de ficha del insumo
        if ($fila === null || (float) $fila['costo_medio'] <= 0) {
            $insumo = db_connect()->table('insumos')->where('id', $insumoId)->get()->getRowArray();

            return (float) ($insumo['costo_unitario'] ?? 0);
        }

        return (float) $fila['costo_medio'];
    }

    /** La bodega de la que sale lo que se vende si nadie dice otra cosa. */
    public function bodegaPorDefecto(): ?int
    {
        $fila = (new BodegaModel())->where('por_defecto', 1)->where('activa', 1)->first();

        return $fila === null ? null : (int) $fila['id'];
    }

    /**
     * Recalcula un saldo desde los movimientos.
     *
     * Es la red de seguridad: si alguna vez el saldo y los movimientos no
     * cuadran, mandan los movimientos, porque son lo que nadie puede editar.
     */
    public function recalcular(int $insumoId, int $bodegaId): float
    {
        $fila = $this->movimientos
            ->selectSum('cantidad', 'total')
            ->where('insumo_id', $insumoId)
            ->where('bodega_id', $bodegaId)
            ->first();

        $saldo = round((float) ($fila['total'] ?? 0), 3);

        $this->existencias->fijar($insumoId, $bodegaId, $saldo, null);

        return $saldo;
    }

    // ── El corazón ──────────────────────────────────────────────────────

    /**
     * Apunta el movimiento y ajusta el saldo, siempre juntos.
     *
     * @param array<string, mixed> $datos
     */
    private function registrar(array $datos): int
    {
        $insumoId = (int) $datos['insumo_id'];
        $bodegaId = (int) $datos['bodega_id'];
        $cantidad = (float) $datos['cantidad'];

        $db = db_connect();
        $db->transStart();

        $anterior = $this->saldo($insumoId, $bodegaId);
        $nuevo    = round($anterior + $cantidad, 3);

        // El promedio se recalcula solo al entrar mercancía: al salir, el coste
        // medio no cambia, solo la cantidad.
        $costoMedio = null;
        if ($cantidad > 0 && (float) $datos['costo_unitario'] > 0) {
            $costoAnterior = $this->costoActual($insumoId, $bodegaId);
            $valorAnterior = max(0, $anterior) * $costoAnterior;
            $valorEntrada  = $cantidad * (float) $datos['costo_unitario'];
            $unidades      = max(0, $anterior) + $cantidad;

            $costoMedio = $unidades > 0 ? round(($valorAnterior + $valorEntrada) / $unidades, 4) : (float) $datos['costo_unitario'];
        }

        // El lote, si el insumo lo lleva
        $loteId = null;
        if (! empty($datos['lote']['lote'])) {
            $loteId = $this->lotes->asegurar(
                $insumoId,
                $bodegaId,
                (string) $datos['lote']['lote'],
                $datos['lote']['caduca_el'] ?? null,
                $cantidad,
                (float) $datos['costo_unitario'],
                isset($datos['lote']['proveedor_id']) ? (int) $datos['lote']['proveedor_id'] : null
            );
        }

        $usuario = session()->get('usuario_nombre');

        $id = (int) $this->movimientos->insert([
            'insumo_id'       => $insumoId,
            'bodega_id'       => $bodegaId,
            'lote_id'         => $loteId,
            'tipo'            => $datos['tipo'],
            'cantidad'        => $cantidad,
            'costo_unitario'  => (float) $datos['costo_unitario'],
            'saldo'           => $nuevo,
            'referencia_tipo' => $datos['referencia_tipo'] ?? null,
            'referencia_id'   => $datos['referencia_id'] ?? null,
            'motivo'          => $datos['motivo'] ?? null,
            'usuario_id'      => session()->get('usuario_id'),
            // El nombre copiado, no solo el id: si el usuario se borra, el
            // movimiento tiene que seguir diciendo quién lo hizo.
            'usuario_nombre'  => $usuario !== null ? (string) $usuario : null,
        ], true);

        $this->existencias->fijar($insumoId, $bodegaId, $nuevo, $costoMedio);

        $db->transComplete();

        if (! $db->transStatus()) {
            throw new RuntimeException('No se pudo registrar el movimiento de almacén.');
        }

        return $id;
    }
}
