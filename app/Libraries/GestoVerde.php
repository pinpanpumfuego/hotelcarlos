<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ConfiguracionModel;
use App\Models\GestoVerdeModel;
use App\Models\LimpiezaModel;
use App\Models\ReservaModel;
use RuntimeException;

/**
 * Gesto verde: una lavada menos a cambio de una consumición.
 *
 * **Por qué el ahorro se calcula y se enseña.** Un programa así se defiende
 * solo si la bebida cuesta menos que la lavada. Si no se mide, al año siguiente
 * nadie sabe si salió a cuenta y la discusión se resuelve por intuición. Por
 * eso `balance()` enfrenta las dos cifras con el coste por lavada que ponga el
 * hotel, y si ese coste está sin poner, lo dice en vez de inventarlo.
 *
 * **Por qué el vale nace al confirmar y no al pedir.** Quien lo pide por la
 * mañana puede pedir toallas por la tarde, y entonces se habría pagado por un
 * ahorro que no ocurrió. Housekeeping da fe, y solo entonces hay premio.
 */
class GestoVerde
{
    private ConfiguracionModel $config;
    private GestoVerdeModel $gestos;

    public function __construct()
    {
        $this->config = new ConfiguracionModel();
        $this->gestos = new GestoVerdeModel();
    }

    public function activo(): bool
    {
        return $this->config->obtener('verde_activo', '0') === '1'
            && (int) $this->config->obtener('verde_categoria_id', '') > 0;
    }

    public function categoriaId(): int
    {
        return (int) $this->config->obtener('verde_categoria_id', '0');
    }

    public function horaTope(): string
    {
        return (string) $this->config->obtener('verde_hora_tope', '10:00');
    }

    public function maxSeguidas(): int
    {
        return max(1, (int) $this->config->obtener('verde_max_seguidas', '3'));
    }

    public function costeLavada(): float
    {
        return max(0, (float) $this->config->obtener('verde_coste_lavada', '0'));
    }

    /**
     * ¿Puede esta reserva acogerse a la noche indicada?
     *
     * @return array{ok: bool, motivo: ?string}
     */
    public function disponible(array $reserva, ?string $fecha = null): array
    {
        $fecha = $fecha ?? date('Y-m-d');
        $no    = static fn (string $m): array => ['ok' => false, 'motivo' => $m];

        if (! $this->activo()) {
            return $no('El programa no está activo.');
        }

        if (! in_array($reserva['estado'], ['checkin', 'confirmada'], true)) {
            return $no('Solo mientras hay alguien alojado.');
        }

        // La noche de llegada no hay nada que cambiar todavía.
        if ($fecha <= $reserva['fecha_entrada']) {
            return $no('Desde mañana: hoy la cabaña se preparó para tu llegada.');
        }

        // Y la de salida se lava igual, así que premiarla sería pagar una
        // bebida por un ahorro que no existe.
        if ($fecha >= $reserva['fecha_salida']) {
            return $no('El día de salida la ropa se lava igualmente.');
        }

        if ($this->gestos->deNoche((int) $reserva['id'], $fecha) !== null) {
            return $no('Ya está pedido para hoy.');
        }

        $seguidas = $this->gestos->nochesSeguidas((int) $reserva['id'], $fecha);

        if ($seguidas >= $this->maxSeguidas()) {
            return $no(sprintf(
                'Llevas %d noches sin cambio. Hoy te toca ropa limpia: es por higiene, no por gasto.',
                $seguidas
            ));
        }

        if (date('H:i') > $this->horaTope()) {
            return $no('Hoy ya es tarde: se pide antes de las ' . $this->horaTope() . '. Mañana sí.');
        }

        return ['ok' => true, 'motivo' => null];
    }

    /** El huésped —o recepción por él— renuncia al cambio de esta noche. */
    public function pedir(int $reservaId, string $origen = 'portal', ?int $usuarioId = null, ?string $fecha = null): int
    {
        $reserva = (new ReservaModel())->find($reservaId);

        if ($reserva === null) {
            throw new RuntimeException('Esa reserva no existe.');
        }

        $fecha = $fecha ?? date('Y-m-d');
        $r     = $this->disponible($reserva, $fecha);

        if (! $r['ok']) {
            throw new RuntimeException((string) $r['motivo']);
        }

        $this->gestos->insert([
            'reserva_id' => $reservaId,
            'unidad_id'  => $reserva['unidad_id'],
            'fecha'      => $fecha,
            'origen'     => in_array($origen, ['portal', 'recepcion'], true) ? $origen : 'portal',
            'estado'     => 'pedido',
            'usuario_id' => $usuarioId,
        ]);

        $id = (int) $this->gestos->getInsertID();

        // Se marca la tarea de limpieza del día, que es lo único que hace que
        // esto ocurra de verdad: sin la marca, quien limpia entra y cambia las
        // toallas como cada mañana.
        $this->marcarLimpieza((int) $reserva['unidad_id'], true);

        return $id;
    }

    /**
     * Housekeeping da fe: no se cambió nada. Aquí nace el vale.
     */
    public function confirmar(int $gestoId, ?int $limpiezaId = null, ?int $usuarioId = null): void
    {
        $gesto = $this->exigir($gestoId);

        if ($gesto['estado'] !== 'pedido') {
            throw new RuntimeException('Ese gesto ya no está pendiente.');
        }

        $this->gestos->update($gestoId, [
            'estado'        => 'confirmado',
            'limpieza_id'   => $limpiezaId,
            'confirmo_id'   => $usuarioId,
            'confirmado_en' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Hubo que cambiar igualmente: una mancha, una sábana rota, un huésped que
     * cambió de idea. Sin ahorro no hay premio, y queda escrito por qué.
     */
    public function descartar(int $gestoId, string $motivo, ?int $usuarioId = null): void
    {
        $gesto = $this->exigir($gestoId);

        if ($gesto['estado'] === 'canjeado') {
            throw new RuntimeException('Ese vale ya se tomó: no se puede deshacer.');
        }

        if (trim($motivo) === '') {
            throw new RuntimeException('Di por qué hubo que cambiarla: dentro de un mes nadie lo recordará.');
        }

        $this->gestos->update($gestoId, [
            'estado'      => 'descartado',
            'motivo'      => mb_substr(trim($motivo), 0, 200),
            'confirmo_id' => $usuarioId,
            'confirmado_en' => date('Y-m-d H:i:s'),
        ]);

        $this->marcarLimpieza((int) $gesto['unidad_id'], false);
    }

    /**
     * El huésped se toma su consumición.
     *
     * Se gasta el vale más antiguo, y de uno en uno: dos vales no pagan dos
     * bebidas de golpe si nadie lo ha decidido así.
     */
    public function canjear(int $reservaId, int $comandaLineaId): array
    {
        $vales = $this->gestos->valesDisponibles($reservaId);

        if ($vales === []) {
            throw new RuntimeException('Esta reserva no tiene ningún gesto verde pendiente de tomar.');
        }

        $vale = $vales[0];

        $this->gestos->update($vale['id'], [
            'estado'           => 'canjeado',
            'comanda_linea_id' => $comandaLineaId,
            'canjeado_en'      => date('Y-m-d H:i:s'),
        ]);

        return [
            'vale'      => $vale,
            'restantes' => count($vales) - 1,
        ];
    }

    /**
     * Lavadas ahorradas contra bebidas regaladas.
     *
     * Es el número que dice si el programa sale a cuenta. Si el hotel no ha
     * puesto cuánto le cuesta una lavada, se dice —no se estima: el agua, la
     * energía y el detergente de este hotel no los sé yo.
     *
     * @return array{lavadas: int, coste_lavada: float, ahorro: float,
     *               canjeados: int, coste_premios: float, pvp_regalado: float,
     *               neto: float, pendientes: int, descartados: int,
     *               sabemos_costes: bool}
     */
    public function balance(string $desde, string $hasta): array
    {
        $db = db_connect();

        $por = array_column($db->query(
            "SELECT estado, COUNT(*) AS n FROM gestos_verdes
             WHERE fecha BETWEEN ? AND ? GROUP BY estado",
            [$desde, $hasta]
        )->getResultArray(), 'n', 'estado');

        // Una lavada se ahorra en cuanto housekeeping confirma, se haya tomado
        // la bebida o no: el ahorro ya ocurrió.
        $lavadas = (int) ($por['confirmado'] ?? 0) + (int) ($por['canjeado'] ?? 0);

        $lineas = $db->query(
            "SELECT cl.producto_id, cl.cantidad, cl.precio_unitario
             FROM gestos_verdes g
             JOIN comanda_lineas cl ON cl.id = g.comanda_linea_id
             WHERE g.fecha BETWEEN ? AND ? AND g.estado = 'canjeado'",
            [$desde, $hasta]
        )->getResultArray();

        // Se separan a propósito dos cosas que se confunden siempre: lo que
        // costó producir lo regalado —que es lo que de verdad sale del bolsillo
        // y lo que hay que comparar con la lavada— y su precio de venta, que
        // solo se habría ingresado si esa persona lo hubiera comprado igual.
        $costes = (new \App\Models\RecetaModel())->costesPorProducto();
        $coste  = 0.0;
        $pvp    = 0.0;

        foreach ($lineas as $l) {
            $cantidad = (float) $l['cantidad'];
            $coste += ((float) ($costes[(int) $l['producto_id']] ?? 0)) * $cantidad;
            $pvp   += ((float) $l['precio_unitario']) * $cantidad;
        }

        $costeLavada = $this->costeLavada();
        $ahorro      = round($lavadas * $costeLavada, 2);

        return [
            'lavadas'        => $lavadas,
            'coste_lavada'   => $costeLavada,
            'ahorro'         => $ahorro,
            'canjeados'      => (int) ($por['canjeado'] ?? 0),
            'coste_premios'  => round($coste, 2),
            'pvp_regalado'   => round($pvp, 2),
            'neto'           => round($ahorro - $coste, 2),
            'pendientes'     => (int) ($por['pedido'] ?? 0),
            'descartados'    => (int) ($por['descartado'] ?? 0),
            // Sin el coste de una lavada no hay balance que valga. Se dice, no
            // se estima: el agua y la energía de este hotel no las sé yo.
            'sabemos_costes' => $costeLavada > 0,
        ];
    }

    // ── Lo de dentro ────────────────────────────────────────────────────

    /**
     * Pone o quita la marca en la tarea de limpieza de hoy de esa cabaña.
     *
     * Es el eslabón que convierte una casilla del portal en algo que pasa de
     * verdad en la habitación.
     */
    private function marcarLimpieza(int $unidadId, bool $sinLenceria): void
    {
        if ($unidadId <= 0) {
            return;
        }

        $limpiezas = new LimpiezaModel();

        $tarea = $limpiezas->where('unidad_id', $unidadId)
            ->whereIn('estado', ['pendiente', 'en_curso'])
            ->orderBy('id', 'DESC')
            ->first();

        if ($tarea !== null) {
            $limpiezas->update($tarea['id'], ['sin_lenceria' => $sinLenceria ? 1 : 0]);
        }
    }

    private function exigir(int $gestoId): array
    {
        $gesto = $this->gestos->find($gestoId);

        if ($gesto === null) {
            throw new RuntimeException('Ese gesto no existe.');
        }

        return $gesto;
    }
}
