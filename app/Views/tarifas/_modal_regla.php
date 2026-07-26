<?php
/**
 * Modal de alta o edición de una regla de precio.
 * Recibe: id (del modal), accion (URL), regla (array|null), tipos (tipos de alojamiento).
 */
use App\Models\ReglaPrecioModel;

$r     = $regla;
$nuevo = $r === null;
$dias  = array_filter(array_map('intval', explode(',', (string) ($r['dias'] ?? ''))));
?>
<div class="modal fade" id="<?= esc($id) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= esc($accion) ?>" class="form-regla">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-sliders me-2"></i><?= $nuevo ? 'Nueva regla de precio' : 'Editar ' . esc($r['nombre']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="120"
                               value="<?= esc($r['nombre'] ?? '') ?>"
                               placeholder="Recargo de fin de semana, Descuento por reserva anticipada…">
                        <div class="form-text">Este nombre es el que verá el huésped en el desglose del precio.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de regla</label>
                        <select name="tipo" class="form-select">
                            <?php foreach (ReglaPrecioModel::TIPOS as $clave => $etiqueta): ?>
                                <option value="<?= $clave ?>" <?= ($r['tipo'] ?? 'dia_semana') === $clave ? 'selected' : '' ?>>
                                    <?= esc($etiqueta) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                        <div class="form-text pista-tipo"
                             data-pistadia_semana="Sube o baja el precio de noches concretas de la semana."
                             data-pistaocupacion="Cuanto más llena está la finca, más vale la última cabaña."
                             data-pistaanticipacion="Premia a quien reserva con tiempo o penaliza el último minuto."
                             data-pistaduracion="Descuento para estancias largas: 3 noches, una semana…"></div>
                    </div>

                    <!-- Días de la semana -->
                    <div class="campo-regla mb-3" data-para="dia_semana">
                        <label class="form-label fw-semibold">Días a los que se aplica</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach (ReglaPrecioModel::DIAS_SEMANA as $num => $nombre): ?>
                                <input type="checkbox" class="btn-check" name="dias[]" value="<?= $num ?>"
                                       id="<?= esc($id) ?>d<?= $num ?>" <?= in_array($num, $dias, true) ? 'checked' : '' ?>>
                                <label class="btn btn-outline-secondary btn-sm" for="<?= esc($id) ?>d<?= $num ?>">
                                    <?= esc(mb_substr($nombre, 0, 3)) ?>
                                </label>
                            <?php endforeach ?>
                        </div>
                        <div class="form-text">La noche cuenta por el día en que se duerme: viernes = noche del viernes al sábado.</div>
                    </div>

                    <!-- Rango numérico -->
                    <div class="campo-regla mb-3" data-para="ocupacion anticipacion duracion">
                        <label class="form-label fw-semibold">Rango en el que se aplica</label>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text">Desde</span>
                                    <input type="number" name="valor_desde" class="form-control" min="0"
                                           value="<?= esc($r['valor_desde'] ?? '') ?>" placeholder="sin mínimo">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text">Hasta</span>
                                    <input type="number" name="valor_hasta" class="form-control" min="0"
                                           value="<?= esc($r['valor_hasta'] ?? '') ?>" placeholder="sin máximo">
                                </div>
                            </div>
                        </div>
                        <div class="form-text">
                            Ocupación en %, antelación en días antes de llegar, duración en noches.
                            Deja un lado vacío para dejarlo abierto (por ejemplo, «desde 70» = del 70 % en adelante).
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Qué hace con el precio</label>
                            <select name="tipo_ajuste" class="form-select">
                                <option value="porcentaje" <?= ($r['tipo_ajuste'] ?? 'porcentaje') === 'porcentaje' ? 'selected' : '' ?>>Porcentaje</option>
                                <option value="valor" <?= ($r['tipo_ajuste'] ?? '') === 'valor' ? 'selected' : '' ?>>Valor fijo en pesos</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Cuánto</label>
                            <input type="number" name="ajuste" class="form-control" step="any"
                                   value="<?= esc((float) ($r['ajuste'] ?? 0)) ?>" placeholder="15">
                            <div class="form-text">Negativo para descontar: <span class="fw-semibold">−10</span>.</div>
                        </div>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-sm-5">
                            <label class="form-label fw-semibold">Solo para</label>
                            <select name="tipo_unidad_id" class="form-select">
                                <option value="">Todos los alojamientos</option>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= $tipo['id'] ?>" <?= (int) ($r['tipo_unidad_id'] ?? 0) === (int) $tipo['id'] ? 'selected' : '' ?>>
                                        <?= esc($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Prioridad</label>
                            <input type="number" name="prioridad" class="form-control" min="0" max="99"
                                   value="<?= esc((int) ($r['prioridad'] ?? 0)) ?>">
                            <div class="form-text">Se aplican de mayor a menor.</div>
                        </div>
                        <div class="col-sm-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activa" id="<?= esc($id) ?>Activa"
                                       <?= (int) ($r['activa'] ?? 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= esc($id) ?>Activa">Activa</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $nuevo ? 'Crear regla' : 'Guardar cambios' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
