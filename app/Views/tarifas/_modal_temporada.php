<?php
/**
 * Modal de alta o edición de una temporada.
 * Recibe: id (del modal), accion (URL), temporada (array|null), anio.
 */
$t       = $temporada;
$nuevo   = $t === null;
$sigAnio = $anio + 1;
?>
<div class="modal fade" id="<?= esc($id) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="post" action="<?= esc($accion) ?>">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-range me-2"></i><?= $nuevo ? 'Nueva temporada' : 'Editar ' . esc($t['nombre']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <?php if ($nuevo): ?>
                        <div class="mb-3">
                            <div class="form-text mb-2">Atajos para empezar rápido:</div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="1"
                                        data-nombre="Fin de año" data-desde="<?= $anio ?>-12-20" data-hasta="<?= $sigAnio ?>-01-15" data-ajuste="35">
                                    Fin de año · +35 %
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="1"
                                        data-nombre="Vacaciones de mitad de año" data-desde="<?= $sigAnio ?>-06-15" data-hasta="<?= $sigAnio ?>-07-15" data-ajuste="20">
                                    Mitad de año · +20 %
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-preset="1"
                                        data-nombre="Temporada baja" data-desde="<?= $sigAnio ?>-02-01" data-hasta="<?= $sigAnio ?>-05-31" data-ajuste="-15">
                                    Temporada baja · −15 %
                                </button>
                            </div>
                        </div>
                    <?php endif ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="100"
                               value="<?= esc($t['nombre'] ?? '') ?>"
                               placeholder="Temporada alta, Puente de Reyes, Semana Santa…">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Desde <span class="text-danger">*</span></label>
                            <input type="date" name="desde" class="form-control" required value="<?= esc($t['desde'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Hasta <span class="text-danger">*</span></label>
                            <input type="date" name="hasta" class="form-control" required value="<?= esc($t['hasta'] ?? '') ?>">
                            <div class="form-text">Ambos días incluidos.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Qué hace con el precio</label>
                            <select name="tipo_ajuste" class="form-select">
                                <?php foreach (\App\Models\TemporadaModel::AJUSTES as $clave => $etiqueta): ?>
                                    <option value="<?= $clave ?>" <?= ($t['tipo_ajuste'] ?? 'porcentaje') === $clave ? 'selected' : '' ?>>
                                        <?= esc($etiqueta) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Cuánto</label>
                            <input type="number" name="ajuste" class="form-control" step="any"
                                   value="<?= esc((float) ($t['ajuste'] ?? 0)) ?>" placeholder="20">
                            <div class="form-text">
                                En porcentaje usa <span class="fw-semibold">20</span> para subir un 20 % y
                                <span class="fw-semibold">−15</span> para bajar un 15 %.
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Prioridad</label>
                            <input type="number" name="prioridad" class="form-control" min="0" max="99"
                                   value="<?= esc((int) ($t['prioridad'] ?? 0)) ?>">
                            <div class="form-text">Si dos temporadas se solapan, gana la de número más alto.</div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Color</label>
                            <input type="color" name="color" class="form-control form-control-color w-100"
                                   value="<?= esc($t['color'] ?? '#b9873f') ?>">
                            <div class="form-text">Se usa en el calendario.</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activa" id="<?= esc($id) ?>Activa"
                                       <?= (int) ($t['activa'] ?? 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= esc($id) ?>Activa">Temporada activa</label>
                            </div>
                        </div>
                    </div>

                    <?php if (! $nuevo): ?>
                        <div class="alert alert-light mt-3 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Para poner un <strong>precio cerrado por tipo de cabaña</strong> dentro de esta temporada,
                            entra en <a href="<?= site_url('tarifas/temporada/' . $t['id']) ?>">su ficha</a>.
                        </div>
                    <?php endif ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $nuevo ? 'Crear temporada' : 'Guardar cambios' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
