<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4"><?= isset($reserva['id']) ? 'Editar reserva ' . esc($reserva['codigo']) : 'Nueva reserva' ?></h1>

<div class="card border-0 shadow-sm" style="max-width: 720px;">
    <div class="card-body">
        <?php if (session()->getFlashdata('errores')): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach (session()->getFlashdata('errores') as $e): ?>
                        <li><?= esc($e) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>

        <form method="post" action="<?= isset($reserva['id']) ? site_url('reservas/actualizar/' . $reserva['id']) : site_url('reservas/guardar') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Huésped titular</label>
                <select name="huesped_id" class="form-select" required>
                    <option value="">Elige un huésped…</option>
                    <?php foreach ($huespedes as $h): ?>
                        <option value="<?= esc($h['id']) ?>" <?= (string) old('huesped_id', $reserva['huesped_id'] ?? '') === (string) $h['id'] ? 'selected' : '' ?>>
                            <?= esc($h['apellidos']) ?>, <?= esc($h['nombre']) ?> (<?= esc($h['tipo_documento']) ?> <?= esc($h['num_documento']) ?>)
                        </option>
                    <?php endforeach ?>
                </select>
                <div class="form-text">¿No está en la lista? <a href="<?= site_url('huespedes/nuevo') ?>">Registrar huésped nuevo</a></div>
            </div>

            <div class="mb-3">
                <label class="form-label">Unidad de alojamiento</label>
                <select name="unidad_id" class="form-select" required>
                    <option value="">Elige una unidad…</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= esc($u['id']) ?>" <?= (string) old('unidad_id', $reserva['unidad_id'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>>
                            <?= esc($u['nombre']) ?> — <?= esc($u['tipo_nombre']) ?> (<?= esc($u['capacidad']) ?> pers., $<?= number_format((float) $u['tarifa_base'], 0, ',', '.') ?>/noche)
                        </option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Fecha de entrada</label>
                    <input type="date" name="fecha_entrada" class="form-control" required
                           value="<?= esc(old('fecha_entrada', $reserva['fecha_entrada'] ?? '')) ?>">
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Fecha de salida</label>
                    <input type="date" name="fecha_salida" class="form-control" required
                           value="<?= esc(old('fecha_salida', $reserva['fecha_salida'] ?? '')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-4 mb-3">
                    <label class="form-label">Adultos</label>
                    <input type="number" name="adultos" class="form-control" min="1" required
                           value="<?= esc(old('adultos', $reserva['adultos'] ?? 2)) ?>">
                </div>
                <div class="col-4 mb-3">
                    <label class="form-label">Niños</label>
                    <input type="number" name="ninos" class="form-control" min="0"
                           value="<?= esc(old('ninos', $reserva['ninos'] ?? 0)) ?>">
                </div>
                <div class="col-4 mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach (['pendiente', 'confirmada'] as $e): ?>
                            <option value="<?= $e ?>" <?= old('estado', $reserva['estado'] ?? 'confirmada') === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="3"><?= esc(old('notas', $reserva['notas'] ?? '')) ?></textarea>
            </div>

            <div class="alert alert-info py-2">
                <i class="bi bi-info-circle me-1"></i>
                El total se calcula automáticamente: noches × tarifa base del tipo de unidad.
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar reserva</button>
                <a href="<?= site_url('reservas') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
