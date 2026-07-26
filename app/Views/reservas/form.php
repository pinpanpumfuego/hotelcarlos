<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $editando = isset($reserva['id']); ?>

<div class="mb-4">
    <a href="<?= site_url('reservas') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Reservas
    </a>
    <h1 class="h3 mb-0 mt-1"><?= $editando ? 'Editar reserva ' . esc($reserva['codigo']) : 'Nueva reserva' ?></h1>
    <p class="text-muted mb-0 mt-1">
        El sistema comprueba la disponibilidad: no dejará guardar si la cabaña ya está ocupada en esas fechas.
    </p>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $editando ? site_url('reservas/actualizar/' . $reserva['id']) : site_url('reservas/guardar') ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2"></i>Huésped</div>
                <div class="card-body p-4">
                    <label class="form-label fw-semibold">Titular de la reserva <span class="text-danger">*</span></label>
                    <select name="huesped_id" class="form-select form-select-lg" required>
                        <option value="">Busca o elige un huésped…</option>
                        <?php foreach ($huespedes as $h): ?>
                            <option value="<?= esc($h['id']) ?>" <?= (string) old('huesped_id', $reserva['huesped_id'] ?? '') === (string) $h['id'] ? 'selected' : '' ?>>
                                <?= esc($h['apellidos']) ?>, <?= esc($h['nombre']) ?> — <?= esc($h['tipo_documento']) ?> <?= esc($h['num_documento']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text">
                        ¿Es la primera vez que viene? <a href="<?= site_url('huespedes/nuevo') ?>">Regístralo primero</a>.
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-range me-2"></i>Estancia</div>
                <div class="card-body p-4">
                    <label class="form-label fw-semibold">Cabaña <span class="text-danger">*</span></label>
                    <select name="unidad_id" class="form-select mb-3" required>
                        <option value="">Elige una cabaña…</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= esc($u['id']) ?>" <?= (string) old('unidad_id', $reserva['unidad_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= esc($u['nombre']) ?> — <?= esc($u['tipo_nombre']) ?>
                                (<?= esc($u['capacidad']) ?> pers., $<?= number_format((float) $u['tarifa_base'], 0, ',', '.') ?>/noche)
                            </option>
                        <?php endforeach ?>
                    </select>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Llegada <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_entrada" class="form-control" required
                                   value="<?= esc(old('fecha_entrada', $reserva['fecha_entrada'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Salida <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_salida" class="form-control" required
                                   value="<?= esc(old('fecha_salida', $reserva['fecha_salida'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        El día de salida queda libre para otro huésped: puede entrar esa misma tarde.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-2"></i>Ocupantes</div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Adultos <span class="text-danger">*</span></label>
                            <input type="number" name="adultos" class="form-control" min="1" max="20" required
                                   value="<?= esc(old('adultos', $reserva['adultos'] ?? 2)) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Niños</label>
                            <input type="number" name="ninos" class="form-control" min="0" max="20"
                                   value="<?= esc(old('ninos', $reserva['ninos'] ?? 0)) ?>">
                        </div>
                    </div>

                    <label class="form-label fw-semibold mt-3">Estado inicial</label>
                    <select name="estado" class="form-select">
                        <option value="confirmada" <?= old('estado', $reserva['estado'] ?? 'confirmada') === 'confirmada' ? 'selected' : '' ?>>
                            Confirmada — el huésped ya la tiene asegurada
                        </option>
                        <option value="pendiente" <?= old('estado', $reserva['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>
                            Pendiente — a falta de confirmar o pagar
                        </option>
                    </select>

                    <label class="form-label fw-semibold mt-3">Notas</label>
                    <textarea name="notas" class="form-control" rows="3"
                              placeholder="Peticiones especiales, hora de llegada…"><?= esc(old('notas', $reserva['notas'] ?? '')) ?></textarea>
                </div>
            </div>

            <div class="alert alert-info d-flex gap-2">
                <i class="bi bi-calculator mt-1"></i>
                <div>
                    <strong>El total se calcula solo</strong>
                    <span class="d-block small">
                        Lo pone el <a href="<?= site_url('tarifas') ?>">motor de tarifas</a>: temporada, día de la semana,
                        ocupación y topes. Los consumos del restaurante se añaden después al folio.
                    </span>
                    <a href="<?= site_url('tarifas/simulador') ?>" class="small">Ver el precio antes de guardar</a>
                </div>
            </div>

            <?php if ($editando): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="recalcular" id="recalcular">
                            <label class="form-check-label fw-semibold" for="recalcular">Recalcular el precio con las tarifas de hoy</label>
                            <div class="form-text">
                                Ahora está en <strong>$<?= number_format((float) $reserva['total'], 0, ',', '.') ?></strong>.
                                Si cambias fechas, cabaña u ocupantes se recalcula igualmente.
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="d-flex gap-2 mt-2">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Guardar reserva</button>
        <a href="<?= site_url('reservas') ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>
