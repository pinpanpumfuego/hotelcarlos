<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ActivoModel;
use App\Models\ConfiguracionModel;
use App\Models\MantenimientoMaterialModel;
use App\Models\MantenimientoModel;
use App\Models\UnidadModel;
use RuntimeException;

/**
 * El ciclo de vida de una orden de trabajo.
 *
 * Vive aquí y no en el controlador porque las órdenes se van a abrir desde tres
 * sitios: el tablero, la etiqueta QR y —en la siguiente tanda— el plan
 * preventivo, que corre solo de madrugada sin nadie delante. Si la regla de
 * «al bloquear una cabaña hay que dejarla sucia al desbloquearla» vive en un
 * controlador, el plan preventivo no la aplica y las dos verdades se separan.
 *
 * Los estados:
 *
 *     abierta ──> en_proceso ──> resuelta ──> verificada
 *                    │  ↑            │
 *                    ↓  │            └──> (rechazada) vuelve a en_proceso
 *                 pausada
 *
 * `anulada` sale de cualquiera: es «esto no era una avería».
 */
class Ordenes
{
    private MantenimientoModel $ordenes;
    private ActivoModel $activos;
    private UnidadModel $unidades;
    private ConfiguracionModel $config;

    public function __construct()
    {
        $this->ordenes  = new MantenimientoModel();
        $this->activos  = new ActivoModel();
        $this->unidades = new UnidadModel();
        $this->config   = new ConfiguracionModel();
    }

    // ── Abrir ───────────────────────────────────────────────────────────

    /**
     * Abre una orden y deja el mundo coherente con ella.
     *
     * @param array{
     *     titulo: string, descripcion?: ?string, prioridad?: string,
     *     tipo?: string, origen?: string, activo_id?: ?int, unidad_id?: ?int,
     *     ubicacion?: ?string, reporto_id?: ?int, asignado_a?: ?int,
     *     solicitud_id?: ?int, bloquear?: bool
     * } $datos
     */
    public function abrir(array $datos): int
    {
        $titulo = trim($datos['titulo']);

        if ($titulo === '') {
            throw new RuntimeException('Una orden sin título no le dice nada a quien la tiene que atender.');
        }

        $activo   = isset($datos['activo_id']) && $datos['activo_id'] > 0
            ? $this->activos->find($datos['activo_id'])
            : null;
        $activoId = $activo !== null ? (int) $activo['id'] : null;

        $unidadId = isset($datos['unidad_id']) && $datos['unidad_id'] > 0 ? (int) $datos['unidad_id'] : null;

        // Si el equipo vive en una cabaña, la avería es de esa cabaña aunque
        // quien reporta no lo haya dicho.
        if ($unidadId === null && $activo !== null && $activo['unidad_id'] !== null) {
            $unidadId = (int) $activo['unidad_id'];
        }

        $tipo = in_array($datos['tipo'] ?? '', ['correctiva', 'preventiva'], true) ? $datos['tipo'] : 'correctiva';

        $prioridad = $datos['prioridad'] ?? 'media';

        if (! array_key_exists($prioridad, MantenimientoModel::PRIORIDADES)) {
            $prioridad = 'media';
        }

        // Lo que rompe un equipo crítico no se abre como «media» por descuido.
        // Solo vale para lo correctivo: una revisión programada de la planta
        // eléctrica no es una urgencia por el hecho de estar programada.
        if ($tipo === 'correctiva' && $activo !== null && (int) $activo['critico'] === 1
            && in_array($prioridad, ['baja', 'media'], true)) {
            $prioridad = 'alta';
        }

        $this->ordenes->insert([
            'titulo'       => mb_substr($titulo, 0, 150),
            'descripcion'  => isset($datos['descripcion']) ? (trim((string) $datos['descripcion']) ?: null) : null,
            'tipo'         => $tipo,
            'origen'       => array_key_exists($datos['origen'] ?? '', MantenimientoModel::ORIGENES) ? $datos['origen'] : 'otro',
            'activo_id'    => $activoId,
            'unidad_id'    => $unidadId,
            'ubicacion'    => $unidadId === null
                ? (trim((string) ($datos['ubicacion'] ?? '')) ?: ($activo['ubicacion'] ?? 'Zonas comunes'))
                : null,
            'prioridad'    => $prioridad,
            'estado'       => 'abierta',
            'reporto_id'   => $datos['reporto_id'] ?? null,
            'asignado_a'   => $datos['asignado_a'] ?? null,
            'solicitud_id' => $datos['solicitud_id'] ?? null,
            // El plazo se congela al abrir. Recalcularlo al mirar movería la
            // meta a mitad de partido cada vez que se toque la configuración.
            'vence_en'     => $this->ordenes->vencimiento($prioridad),
        ]);

        $id = (int) $this->ordenes->getInsertID();

        if (($datos['bloquear'] ?? false) === true && $unidadId !== null) {
            (new Housekeeping())->bloquear($unidadId, 'mantenimiento', mb_substr($titulo, 0, 300));
            $this->ordenes->update($id, ['bloqueo_unidad' => 1]);
        }

        // Un equipo con avería abierta deja de estar «en servicio»: si no, el
        // inventario diría que todo va bien mientras el tablero dice lo
        // contrario. Solo lo correctivo: revisar algo no es que esté roto, y
        // marcarlo averiado bloquearía equipos cada trimestre sin motivo.
        if ($tipo === 'correctiva' && $activo !== null && $activo['estado'] === 'activo') {
            $this->activos->update($activoId, ['estado' => 'averiado']);
        }

        return $id;
    }

    // ── Repartir el trabajo ─────────────────────────────────────────────

    public function asignar(int $id, ?int $usuarioId): void
    {
        $orden = $this->exigir($id);

        if (! in_array($orden['estado'], MantenimientoModel::ABIERTAS, true)) {
            throw new RuntimeException('Esa orden ya está cerrada.');
        }

        $this->ordenes->update($id, ['asignado_a' => $usuarioId ?: null]);
    }

    public function cambiarPrioridad(int $id, string $prioridad): void
    {
        $orden = $this->exigir($id);

        if (! array_key_exists($prioridad, MantenimientoModel::PRIORIDADES)) {
            throw new RuntimeException('Esa prioridad no existe.');
        }

        // Subir la prioridad acorta el plazo, y tiene que contar desde que se
        // abrió, no desde ahora: si no, marcar algo como urgente le regalaría
        // cuatro horas más a algo que lleva una semana esperando.
        $this->ordenes->update($id, [
            'prioridad' => $prioridad,
            'vence_en'  => $this->ordenes->vencimiento($prioridad, $orden['created_at']),
        ]);
    }

    // ── Trabajar ────────────────────────────────────────────────────────

    public function empezar(int $id, ?int $usuarioId): void
    {
        $orden = $this->exigir($id);

        if (! in_array($orden['estado'], ['abierta', 'pausada'], true)) {
            throw new RuntimeException('Esa orden no está esperando a que la empiecen.');
        }

        $this->ordenes->update($id, [
            'estado'      => 'en_proceso',
            // Solo la primera vez: al reanudar tras una pausa no se reinicia el
            // reloj, porque el tiempo de antes también se trabajó.
            'iniciada_en' => $orden['iniciada_en'] ?? date('Y-m-d H:i:s'),
            'asignado_a'  => $orden['asignado_a'] ?? $usuarioId,
        ]);
    }

    /**
     * Aparcada a la espera de algo: un repuesto, un proveedor, que se vaya el
     * huésped. Es distinto de «abierta»: nadie tiene que volver a mirarla hasta
     * que llegue eso, pero sigue contando el plazo.
     */
    public function pausar(int $id, string $motivo): void
    {
        $orden = $this->exigir($id);

        if ($orden['estado'] !== 'en_proceso') {
            throw new RuntimeException('Solo se puede pausar algo que se está haciendo.');
        }

        if (trim($motivo) === '') {
            throw new RuntimeException('Di a qué está esperando: si no, nadie sabe cuándo retomarla.');
        }

        $this->ordenes->update($id, [
            'estado'      => 'pausada',
            'descripcion' => trim(($orden['descripcion'] ?? '') . "\n· En espera: " . trim($motivo)),
        ]);
    }

    /**
     * Cerrada por quien la arregló.
     *
     * Ojo: «resuelta» no es lo mismo que «verificada». Esto es su palabra.
     */
    public function resolver(int $id, string $solucion, ?int $usuarioId): void
    {
        $orden = $this->exigir($id);

        if (in_array($orden['estado'], ['resuelta', 'verificada', 'anulada'], true)) {
            throw new RuntimeException('Esa orden ya está cerrada.');
        }

        if (trim($solucion) === '') {
            throw new RuntimeException('Describe qué se hizo. Dentro de un año, eso es lo único que quedará.');
        }

        $ahora = date('Y-m-d H:i:s');

        $minutos = $orden['iniciada_en'] !== null
            ? max(1, (int) round((strtotime($ahora) - strtotime($orden['iniciada_en'])) / 60))
            : null;

        $this->ordenes->update($id, [
            'estado'          => 'resuelta',
            'solucion'        => trim($solucion),
            'resolvio_id'     => $usuarioId,
            'resuelta_en'     => $ahora,
            // Sin «iniciar» no hay tiempo. Inventarlo sería peor que no
            // tenerlo, porque los informes se lo creerían.
            'minutos'         => $minutos,
            'costo_mano_obra' => $this->costoManoObra($minutos),
        ]);

        $this->recalcularCostos($id);

        // Si no hace falta que otro lo dé por bueno, se cierra del todo aquí
        if (! $this->exigeVerificacion($orden)) {
            $this->soltarElMundo($id);
        }
    }

    /**
     * Otro la da por buena.
     *
     * Es el momento en que la cabaña vuelve al mercado, no antes: dar por
     * arreglada una fuga de gas con la palabra de quien la arregló y volver a
     * vender la cabaña esa misma tarde es exactamente lo que no puede pasar.
     */
    public function verificar(int $id, ?int $usuarioId): void
    {
        $orden = $this->exigir($id);

        if ($orden['estado'] !== 'resuelta') {
            throw new RuntimeException('Solo se da por buena una orden que alguien haya resuelto.');
        }

        if ($usuarioId !== null && (int) $orden['resolvio_id'] === $usuarioId) {
            throw new RuntimeException('No puedes dar por buena tu propia reparación. Que la mire otra persona.');
        }

        $this->ordenes->update($id, ['estado' => 'verificada']);
        $this->soltarElMundo($id);
    }

    /** Vuelve al técnico porque no está bien. */
    public function rechazar(int $id, string $motivo, ?int $usuarioId): void
    {
        $orden = $this->exigir($id);

        if ($orden['estado'] !== 'resuelta') {
            throw new RuntimeException('Solo se rechaza una orden que alguien haya resuelto.');
        }

        if (trim($motivo) === '') {
            throw new RuntimeException('Di qué falta: rechazar sin decir por qué es mandar a alguien a ciegas.');
        }

        if ($usuarioId !== null && (int) $orden['resolvio_id'] === $usuarioId) {
            throw new RuntimeException('No puedes revisar tu propia reparación.');
        }

        $this->ordenes->update($id, [
            'estado'      => 'en_proceso',
            'descripcion' => trim(($orden['descripcion'] ?? '') . "\n· Devuelta: " . trim($motivo)),
            // El plazo se recalcula desde ahora: la orden empieza de nuevo.
            'vence_en'    => $this->ordenes->vencimiento($orden['prioridad']),
            'resuelta_en' => null,
            'solucion'    => null,
        ]);
    }

    /** No era una avería, o estaba repetida. */
    public function anular(int $id, string $motivo): void
    {
        $orden = $this->exigir($id);

        if ($orden['estado'] === 'verificada') {
            throw new RuntimeException('Una orden ya dada por buena no se anula: su historial es real.');
        }

        $this->ordenes->update($id, [
            'estado'      => 'anulada',
            'solucion'    => trim($motivo) ?: 'Anulada sin motivo indicado.',
            'resuelta_en' => date('Y-m-d H:i:s'),
        ]);

        $this->soltarElMundo($id);
    }

    // ── Materiales ──────────────────────────────────────────────────────

    /**
     * Apunta un material y lo saca del almacén.
     *
     * Si el repuesto sale del inventario sin apuntarse, el día del conteo falta
     * y nadie sabe por qué. Y si el técnico lo compró en la ferretería esa
     * misma mañana, no hay insumo pero sí hay gasto: eso también se anota.
     */
    public function gastarMaterial(
        int $id,
        string $descripcion,
        float $cantidad,
        ?int $insumoId = null,
        ?int $bodegaId = null,
        ?float $costoUnitario = null,
        ?int $usuarioId = null,
    ): int {
        $this->exigir($id);

        if ($cantidad <= 0) {
            throw new RuntimeException('La cantidad tiene que ser positiva.');
        }

        $descripcion = trim($descripcion);
        $materiales  = new MantenimientoMaterialModel();
        $movimiento  = null;

        if ($insumoId !== null && $bodegaId !== null) {
            $almacen = new Almacen();

            // Se descuenta primero: si el almacén se queja —porque no hay
            // stock, por ejemplo—, no se apunta un gasto que no ocurrió.
            $movimiento = $almacen->salida(
                $insumoId,
                $bodegaId,
                $cantidad,
                'consumo_interno',
                'Orden de mantenimiento #' . $id,
                'mantenimiento',
                $id
            );

            $costoUnitario ??= $almacen->costoActual($insumoId, $bodegaId);

            if ($descripcion === '') {
                $insumo      = db_connect()->table('insumos')->where('id', $insumoId)->get()->getRowArray();
                $descripcion = $insumo['nombre'] ?? 'Material';
            }
        }

        if ($descripcion === '') {
            throw new RuntimeException('Di qué material se gastó.');
        }

        $costoUnitario = (float) ($costoUnitario ?? 0);

        $materiales->insert([
            'mantenimiento_id' => $id,
            'insumo_id'        => $insumoId,
            'descripcion'      => mb_substr($descripcion, 0, 150),
            'cantidad'         => $cantidad,
            'costo_unitario'   => $costoUnitario,
            'costo_total'      => round($cantidad * $costoUnitario, 2),
            'movimiento_id'    => $movimiento,
            'bodega_id'        => $bodegaId,
            'usuario_id'       => $usuarioId,
        ]);

        $lineaId = (int) $materiales->getInsertID();

        $this->recalcularCostos($id);

        return $lineaId;
    }

    /** Quita un material apuntado por error y devuelve el stock. */
    public function quitarMaterial(int $lineaId): void
    {
        $materiales = new MantenimientoMaterialModel();
        $linea      = $materiales->find($lineaId);

        if ($linea === null) {
            throw new RuntimeException('Esa línea de material no existe.');
        }

        if ($linea['movimiento_id'] !== null && $linea['insumo_id'] !== null && $linea['bodega_id'] !== null) {
            (new Almacen())->entrada(
                (int) $linea['insumo_id'],
                (int) $linea['bodega_id'],
                (float) $linea['cantidad'],
                (float) $linea['costo_unitario'],
                'Material devuelto de la orden #' . $linea['mantenimiento_id'],
                [],
                'mantenimiento_devolucion',
                (int) $linea['mantenimiento_id']
            );
        }

        $materiales->delete($lineaId);
        $this->recalcularCostos((int) $linea['mantenimiento_id']);
    }

    // ── Lo de dentro ────────────────────────────────────────────────────

    /**
     * ¿Hace falta que otro la dé por buena?
     *
     * Lo pide lo urgente y lo que dejó una cabaña bloqueada. Ahí es donde una
     * reparación a medias tiene consecuencias que se pagan.
     */
    public function exigeVerificacion(array $orden): bool
    {
        if ($this->config->obtener('mant_verifica_urgentes', '1') !== '1') {
            return false;
        }

        return $orden['prioridad'] === 'urgente' || (int) $orden['bloqueo_unidad'] === 1;
    }

    /** Suelta la cabaña y el equipo cuando la orden deja de estar viva. */
    private function soltarElMundo(int $id): void
    {
        $orden = $this->ordenes->find($id);

        if ($orden === null) {
            return;
        }

        if ($orden['unidad_id'] !== null) {
            $unidad = $this->unidades->find($orden['unidad_id']);

            if ($unidad !== null && $unidad['estado'] === 'bloqueada' && $unidad['motivo_bloqueo'] === 'mantenimiento') {
                // Vuelve a venderse, pero sucia: después de una reparación
                // queda polvo, y salir directa a «limpia» significa que el
                // siguiente huésped se la encuentra así.
                (new Housekeeping())->desbloquear((int) $unidad['id']);
                $this->unidades->update($unidad['id'], ['estado_limpieza' => 'sucia']);
            }
        }

        if ($orden['activo_id'] === null) {
            return;
        }

        // Darlo por bueno con otra avería sin cerrar sería mentir. Se cuentan
        // solo las correctivas: una revisión programada abierta no significa
        // que el equipo esté roto, y si contara, el equipo se quedaría
        // «averiado» hasta que alguien hiciera el preventivo del trimestre.
        $quedan = $this->ordenes
            ->where('activo_id', $orden['activo_id'])
            ->where('tipo', 'correctiva')
            ->whereIn('estado', MantenimientoModel::ABIERTAS)
            ->countAllResults();

        if ($quedan > 0) {
            return;
        }

        $activo = $this->activos->find($orden['activo_id']);

        if ($activo !== null && in_array($activo['estado'], ['averiado', 'reparacion'], true)) {
            $this->activos->update($activo['id'], ['estado' => 'activo']);
        }
    }

    private function recalcularCostos(int $id): void
    {
        $this->ordenes->update($id, [
            'costo_materiales' => (new MantenimientoMaterialModel())->totalDeOrden($id),
        ]);
    }

    private function costoManoObra(?int $minutos): float
    {
        $hora = (float) $this->config->obtener('mant_costo_hora', '0');

        if ($minutos === null || $hora <= 0) {
            return 0;
        }

        return round($minutos / 60 * $hora, 2);
    }

    private function exigir(int $id): array
    {
        $orden = $this->ordenes->find($id);

        if ($orden === null) {
            throw new RuntimeException('Esa orden no existe.');
        }

        return $orden;
    }
}
