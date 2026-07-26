<?php
/** Alta o edición de un artículo del inventario. Recibe: id, accion, item (array|null), grupos. */
$it    = $item;
$nuevo = $it === null;
?>
<div class="modal fade" id="<?= esc($id) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= esc($accion) ?>">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title"><?= $nuevo ? 'Nuevo artículo' : 'Editar ' . esc($it['nombre']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" required maxlength="100"
                               value="<?= esc($it['nombre'] ?? '') ?>" placeholder="Toalla de cuerpo, Cafetera…">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Zona de la cabaña</label>
                            <input type="text" name="grupo" class="form-control" list="gruposItems" maxlength="40"
                                   value="<?= esc($it['grupo'] ?? 'Dormitorio') ?>">
                            <datalist id="gruposItems">
                                <?php foreach ($grupos as $g): ?>
                                    <option value="<?= esc($g) ?>">
                                <?php endforeach ?>
                            </datalist>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Cantidad estándar</label>
                            <input type="number" name="cantidad_estandar" class="form-control" min="1" max="99"
                                   value="<?= (int) ($it['cantidad_estandar'] ?? 1) ?>">
                            <div class="form-text">La que se pone al crear una cabaña nueva.</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-7">
                            <label class="form-label fw-semibold">Valor de reposición</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="valor_reposicion" class="form-control" min="0" step="1000"
                                       value="<?= (int) ($it['valor_reposicion'] ?? 0) ?>">
                            </div>
                            <div class="form-text">Lo que se cobra si el huésped se lo lleva. 0 = no se cobra.</div>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label fw-semibold">Orden</label>
                            <input type="number" name="orden" class="form-control" min="0" value="<?= (int) ($it['orden'] ?? 0) ?>">
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="activo" id="<?= esc($id) ?>Activo"
                               <?= (int) ($it['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= esc($id) ?>Activo">Activo</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary"><?= $nuevo ? 'Añadir' : 'Guardar' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
