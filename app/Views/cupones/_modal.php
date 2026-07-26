<?php
/**
 * Modal de alta o edición de un cupón.
 * Recibe: id (del modal), accion (URL), cupon (array|null).
 */
use App\Models\CuponModel;

$c     = $cupon;
$nuevo = $c === null;
?>
<div class="modal fade" id="<?= esc($id) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?= esc($accion) ?>">
                <?= csrf_field() ?>

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-ticket-perforated me-2"></i><?= $nuevo ? 'Nuevo cupón' : 'Editar ' . esc($c['codigo']) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-5">
                            <label class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
                            <input type="text" name="codigo" class="form-control text-uppercase" required maxlength="40"
                                   value="<?= esc($c['codigo'] ?? '') ?>" placeholder="BIENVENIDA10" autocomplete="off">
                            <div class="form-text">Sin espacios ni tildes. Es lo que teclea el huésped.</div>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label fw-semibold">Descripción interna</label>
                            <input type="text" name="descripcion" class="form-control" maxlength="200"
                                   value="<?= esc($c['descripcion'] ?? '') ?>"
                                   placeholder="Campaña de apertura, acuerdo con agencia…">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Tipo de descuento</label>
                            <select name="tipo" class="form-select">
                                <?php foreach (CuponModel::TIPOS as $clave => $etiqueta): ?>
                                    <option value="<?= $clave ?>" <?= ($c['tipo'] ?? 'porcentaje') === $clave ? 'selected' : '' ?>>
                                        <?= esc($etiqueta) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Cuánto <span class="text-danger">*</span></label>
                            <input type="number" name="valor" class="form-control" min="0" step="any" required
                                   value="<?= esc((float) ($c['valor'] ?? 0)) ?>" placeholder="10">
                            <div class="form-text">10 = 10 % o $10 según el tipo.</div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Descuento máximo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="descuento_maximo" class="form-control" min="0" step="1000"
                                       value="<?= ($c['descuento_maximo'] ?? null) !== null ? (int) $c['descuento_maximo'] : '' ?>"
                                       placeholder="Sin tope">
                            </div>
                            <div class="form-text">Útil con porcentajes.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">¿Sobre qué descuenta?</label>
                        <select name="ambito" class="form-select">
                            <?php foreach (CuponModel::AMBITOS as $clave => $etiqueta): ?>
                                <option value="<?= $clave ?>" <?= ($c['ambito'] ?? 'alojamiento') === $clave ? 'selected' : '' ?>>
                                    <?= esc($etiqueta) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">¿Dónde se puede canjear?</label>
                        <div class="d-flex gap-3 flex-wrap">
                            <?php foreach (['en_web' => 'Reserva en línea', 'en_recepcion' => 'Recepción', 'en_tpv' => 'TPV del restaurante'] as $campo => $texto): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="<?= $campo ?>"
                                           id="<?= esc($id) ?><?= $campo ?>" <?= (int) ($c[$campo] ?? 1) === 1 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= esc($id) ?><?= $campo ?>"><?= $texto ?></label>
                                </div>
                            <?php endforeach ?>
                        </div>
                    </div>

                    <hr>
                    <h3 class="h6 fw-semibold mb-3">Condiciones</h3>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Válido desde</label>
                            <input type="date" name="desde" class="form-control" value="<?= esc($c['desde'] ?? '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Válido hasta</label>
                            <input type="date" name="hasta" class="form-control" value="<?= esc($c['hasta'] ?? '') ?>">
                            <div class="form-text">Vacío = sin caducidad.</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Importe mínimo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="importe_minimo" class="form-control" min="0" step="1000"
                                       value="<?= ($c['importe_minimo'] ?? null) !== null ? (int) $c['importe_minimo'] : '' ?>"
                                       placeholder="Sin mínimo">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Usos totales</label>
                            <input type="number" name="limite_usos" class="form-control" min="1"
                                   value="<?= esc($c['limite_usos'] ?? '') ?>" placeholder="Ilimitado">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Usos por huésped</label>
                            <input type="number" name="limite_por_huesped" class="form-control" min="1"
                                   value="<?= esc($c['limite_por_huesped'] ?? '') ?>" placeholder="Ilimitado">
                            <div class="form-text">Se controla por documento.</div>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="activo" id="<?= esc($id) ?>Activo"
                               <?= (int) ($c['activo'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= esc($id) ?>Activo">Cupón activo</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= $nuevo ? 'Crear cupón' : 'Guardar cambios' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
