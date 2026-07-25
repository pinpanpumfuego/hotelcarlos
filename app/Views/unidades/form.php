<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4"><?= isset($unidad['id']) ? 'Editar unidad' : 'Nueva unidad' ?></h1>

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

        <form method="post" action="<?= isset($unidad['id']) ? site_url('unidades/actualizar/' . $unidad['id']) : site_url('unidades/guardar') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Nombre de la unidad</label>
                <input type="text" name="nombre" class="form-control" required
                       value="<?= esc(old('nombre', $unidad['nombre'] ?? '')) ?>" placeholder="Ej.: Habitación 4, Cabaña El Nogal">
            </div>

            <div class="mb-3">
                <label class="form-label">Tipo de alojamiento</label>
                <select name="tipo_id" class="form-select" required>
                    <option value="">Elige un tipo…</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= esc($t['id']) ?>" <?= (string) old('tipo_id', $unidad['tipo_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>>
                            <?= esc($t['nombre']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <?php foreach (['disponible', 'ocupada', 'limpieza', 'bloqueada'] as $e): ?>
                        <option value="<?= $e ?>" <?= old('estado', $unidad['estado'] ?? 'disponible') === $e ? 'selected' : '' ?>><?= ucfirst($e) ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="3"><?= esc(old('notas', $unidad['notas'] ?? '')) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                <a href="<?= site_url('unidades') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
