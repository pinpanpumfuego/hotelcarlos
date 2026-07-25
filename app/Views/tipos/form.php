<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4"><?= isset($tipo['id']) ? 'Editar tipo de alojamiento' : 'Nuevo tipo de alojamiento' ?></h1>

<div class="card border-0 shadow-sm" style="max-width: 640px;">
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

        <form method="post" action="<?= isset($tipo['id']) ? site_url('tipos/actualizar/' . $tipo['id']) : site_url('tipos/guardar') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control" required
                       value="<?= esc(old('nombre', $tipo['nombre'] ?? '')) ?>" placeholder="Ej.: Habitación estándar, Cabaña familiar">
            </div>

            <div class="mb-3">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"><?= esc(old('descripcion', $tipo['descripcion'] ?? '')) ?></textarea>
            </div>

            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Capacidad (personas)</label>
                    <input type="number" name="capacidad" class="form-control" min="1" required
                           value="<?= esc(old('capacidad', $tipo['capacidad'] ?? 2)) ?>">
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Tarifa base por noche (COP)</label>
                    <input type="number" name="tarifa_base" class="form-control" min="0" step="1000" required
                           value="<?= esc(old('tarifa_base', $tipo['tarifa_base'] ?? 0)) ?>">
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                <a href="<?= site_url('tipos') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
