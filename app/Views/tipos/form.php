<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $editando = isset($tipo['id']); ?>

<div class="mb-4">
    <a href="<?= site_url('tipos') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Tipos de alojamiento
    </a>
    <h1 class="h3 mb-0 mt-1"><?= $editando ? 'Editar ' . esc($tipo['nombre']) : 'Nuevo tipo de alojamiento' ?></h1>
    <p class="text-muted mb-0 mt-1">
        Un tipo agrupa alojamientos iguales: comparten capacidad, tarifa y descripción.
        Las cabañas concretas se crean después en <a href="<?= site_url('unidades') ?>">Cabañas</a>.
    </p>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $editando ? site_url('tipos/actualizar/' . $tipo['id']) : site_url('tipos/guardar') ?>">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm" style="max-width: 680px;">
        <div class="card-body p-4">
            <div class="mb-3">
                <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                <input type="text" name="nombre" class="form-control form-control-lg" required
                       value="<?= esc(old('nombre', $tipo['nombre'] ?? '')) ?>" placeholder="Cabaña, Glamping, Habitación estándar…">
                <div class="form-text">Así lo verá el huésped en la web y en el motor de reservas.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"
                          placeholder="Cabaña triangular con techo de paja a la orilla del lago…"><?= esc(old('descripcion', $tipo['descripcion'] ?? '')) ?></textarea>
                <div class="form-text">Aparece en la web pública, bajo el nombre del alojamiento.</div>
            </div>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Capacidad <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="capacidad" class="form-control" min="1" max="20" required
                               value="<?= esc(old('capacidad', $tipo['capacidad'] ?? 2)) ?>">
                        <span class="input-group-text">personas</span>
                    </div>
                    <div class="form-text">El motor de reservas no ofrecerá este tipo a grupos mayores.</div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Tarifa por noche <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="tarifa_base" class="form-control" min="0" step="1000" required
                               value="<?= esc(old('tarifa_base', (int) ($tipo['tarifa_base'] ?? 0))) ?>">
                        <span class="input-group-text">COP</span>
                    </div>
                    <div class="form-text">Se usa para calcular el total de cada reserva.</div>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white d-flex gap-2 p-3">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            <a href="<?= site_url('tipos') ?>" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
