<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\ChecklistPuntoModel;
use App\Models\LimpiezaModel;
use App\Models\LimpiezaPuntoModel;
use App\Models\UnidadModel;

/**
 * El ciclo de una limpieza, de principio a fin.
 *
 * Vive aparte del controlador porque lo usan tres sitios: el tablero del panel,
 * el móvil de quien limpia y el check-out, que abre la tarea solo. Si la lógica
 * estuviera en uno de ellos, los otros dos tendrían su propia versión y
 * acabarían discrepando.
 *
 * **Cada cambio de tarea mueve también el estado de la cabaña.** Son dos
 * tablas, pero una sola realidad: una tarea en curso y una cabaña que dice
 * estar limpia es exactamente el descuadre que hace que nadie se fíe del
 * tablero.
 */
class Housekeeping
{
    private LimpiezaModel $limpiezas;
    private UnidadModel $unidades;
    private LimpiezaPuntoModel $puntos;

    public function __construct()
    {
        $this->limpiezas = new LimpiezaModel();
        $this->unidades  = new UnidadModel();
        $this->puntos    = new LimpiezaPuntoModel();
    }

    /**
     * Abre una tarea y deja la cabaña como sucia.
     *
     * Lo llama el check-out, y también recepción cuando hace falta una limpieza
     * durante la estancia o una profunda.
     */
    public function abrirTarea(int $unidadId, string $tipo = 'salida', string $prioridad = 'normal'): int
    {
        $tareaId = $this->limpiezas->abrir($unidadId, $tipo, $prioridad);

        $this->unidades->update($unidadId, ['estado_limpieza' => 'sucia']);
        $this->prepararChecklist($tareaId, $unidadId, $tipo);

        return $tareaId;
    }

    /** Alguien se hace cargo de la tarea. */
    public function empezar(int $tareaId, int $usuarioId): bool
    {
        $tarea = $this->limpiezas->find($tareaId);
        if ($tarea === null || ! in_array($tarea['estado'], ['pendiente', 'rechazada'], true)) {
            return false;
        }

        $this->limpiezas->update($tareaId, [
            'estado'     => 'en_curso',
            'usuario_id' => $usuarioId,
            'inicio'     => date('Y-m-d H:i:s'),
            // Se limpia el rechazo anterior: la tarea empieza de nuevo
            'fin'            => null,
            'motivo_rechazo' => null,
        ]);

        $this->unidades->update((int) $tarea['unidad_id'], ['estado_limpieza' => 'en_limpieza']);

        return true;
    }

    /**
     * Se da por terminada.
     *
     * **No se puede terminar con puntos críticos sin foto.** Es lo que
     * convierte un «ya está» en algo comprobable, y es justo lo que se pide
     * cuando alguien reclama que la cabaña no estaba bien.
     *
     * @return array{ok: bool, error?: string}
     */
    public function terminar(int $tareaId, ?string $notas = null): array
    {
        $tarea = $this->limpiezas->find($tareaId);
        if ($tarea === null || $tarea['estado'] !== 'en_curso') {
            return ['ok' => false, 'error' => 'Esa tarea no está en curso.'];
        }

        $faltan = $this->puntos->criticosSinFoto($tareaId);
        if ($faltan !== []) {
            return [
                'ok'    => false,
                'error' => 'Faltan fotos en: ' . implode(', ', array_column($faltan, 'texto')),
            ];
        }

        $sinHacer = $this->puntos->sinHacer($tareaId);
        if ($sinHacer !== []) {
            return [
                'ok'    => false,
                'error' => 'Quedan puntos sin marcar: ' . implode(', ', array_column($sinHacer, 'texto')),
            ];
        }

        $exigeInspeccion = $this->limpiezas->exigeInspeccion();

        $this->limpiezas->update($tareaId, [
            'estado' => $exigeInspeccion ? 'hecha' : 'inspeccionada',
            'fin'    => date('Y-m-d H:i:s'),
            'notas'  => $notas,
            // Sin inspección, se da por buena en el momento
            'inspeccion_en' => $exigeInspeccion ? null : date('Y-m-d H:i:s'),
        ]);

        $this->unidades->update((int) $tarea['unidad_id'], [
            'estado_limpieza' => $exigeInspeccion ? 'limpia' : 'inspeccionada',
        ]);

        return ['ok' => true];
    }

    /** El inspector la da por buena: la cabaña ya se puede entregar. */
    public function aprobar(int $tareaId, int $inspectorId): bool
    {
        $tarea = $this->limpiezas->find($tareaId);
        if ($tarea === null || $tarea['estado'] !== 'hecha') {
            return false;
        }

        $this->limpiezas->update($tareaId, [
            'estado'        => 'inspeccionada',
            'inspector_id'  => $inspectorId,
            'inspeccion_en' => date('Y-m-d H:i:s'),
        ]);

        $this->unidades->update((int) $tarea['unidad_id'], ['estado_limpieza' => 'inspeccionada']);

        return true;
    }

    /**
     * La devuelve para rehacerla.
     *
     * Suma un reproceso y **exige motivo**: «está mal» no le dice a nadie qué
     * hay que arreglar, y la tarea volvería igual.
     */
    public function rechazar(int $tareaId, int $inspectorId, string $motivo): array
    {
        $tarea = $this->limpiezas->find($tareaId);
        if ($tarea === null || $tarea['estado'] !== 'hecha') {
            return ['ok' => false, 'error' => 'Esa tarea no está esperando inspección.'];
        }

        if (trim($motivo) === '') {
            return ['ok' => false, 'error' => 'Di qué hay que rehacer: «está mal» no ayuda a nadie.'];
        }

        $this->limpiezas->update($tareaId, [
            'estado'         => 'rechazada',
            'inspector_id'   => $inspectorId,
            'inspeccion_en'  => date('Y-m-d H:i:s'),
            'motivo_rechazo' => mb_substr(trim($motivo), 0, 300),
            'reprocesos'     => (int) $tarea['reprocesos'] + 1,
        ]);

        $this->unidades->update((int) $tarea['unidad_id'], ['estado_limpieza' => 'sucia']);

        // Los puntos vuelven a cero: hay que repasarlos, no dar por buenos los
        // de la vez anterior.
        $this->puntos->reiniciar($tareaId);

        return ['ok' => true];
    }

    /**
     * El huésped pide que no le molesten.
     *
     * No cancela la tarea: la deja esperando. Volver a intentarlo más tarde es
     * exactamente lo que hay que hacer, y perder la tarea significaría que esa
     * cabaña se queda sin limpiar y nadie se entera.
     */
    public function noMolestar(int $unidadId, bool $activar = true): void
    {
        $this->unidades->update($unidadId, [
            'estado_limpieza' => $activar ? 'no_molestar' : 'sucia',
        ]);
    }

    /** Bloquea la cabaña con motivo. */
    public function bloquear(int $unidadId, string $motivo, ?string $nota = null, ?string $hasta = null): void
    {
        $validos = ['dano', 'mantenimiento', 'bioseguridad', 'uso_propio', 'otro'];

        $this->unidades->update($unidadId, [
            'estado'          => 'bloqueada',
            'motivo_bloqueo'  => in_array($motivo, $validos, true) ? $motivo : 'otro',
            'nota_bloqueo'    => $nota !== null ? mb_substr($nota, 0, 300) : null,
            'bloqueada_hasta' => $hasta ?: null,
        ]);
    }

    public function desbloquear(int $unidadId): void
    {
        $this->unidades->update($unidadId, [
            'estado'          => 'disponible',
            'motivo_bloqueo'  => null,
            'nota_bloqueo'    => null,
            'bloqueada_hasta' => null,
        ]);
    }

    /**
     * Copia el checklist del tipo de alojamiento a la tarea.
     *
     * Se copia el texto, no se enlaza: si mañana cambia el checklist, lo que se
     * hizo ayer tiene que seguir diciendo lo que decía.
     */
    private function prepararChecklist(int $tareaId, int $unidadId, string $tipo): void
    {
        if ($this->puntos->where('limpieza_id', $tareaId)->countAllResults() > 0) {
            return;
        }

        $unidad = $this->unidades->find($unidadId);
        $puntos = (new ChecklistPuntoModel())->paraTipo(
            $unidad === null ? null : (int) $unidad['tipo_id'],
            $tipo
        );

        foreach ($puntos as $p) {
            $this->puntos->insert([
                'limpieza_id' => $tareaId,
                'punto_id'    => (int) $p['id'],
                'texto'       => $p['texto'],
                'exige_foto'  => (int) $p['exige_foto'],
                'hecho'       => 0,
            ]);
        }
    }
}
