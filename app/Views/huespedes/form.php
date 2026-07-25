<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4"><?= isset($huesped['id']) ? 'Editar huésped' : 'Nuevo huésped' ?></h1>

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

        <form method="post" action="<?= isset($huesped['id']) ? site_url('huespedes/actualizar/' . $huesped['id']) : site_url('huespedes/guardar') ?>">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" required
                           value="<?= esc(old('nombre', $huesped['nombre'] ?? '')) ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Apellidos</label>
                    <input type="text" name="apellidos" class="form-control" required
                           value="<?= esc(old('apellidos', $huesped['apellidos'] ?? '')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tipo de documento</label>
                    <select name="tipo_documento" class="form-select">
                        <?php
                        $tiposDoc = ['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'PASAPORTE' => 'Pasaporte', 'TI' => 'Tarjeta de identidad', 'OTRO' => 'Otro'];
                        foreach ($tiposDoc as $valor => $etiqueta): ?>
                            <option value="<?= $valor ?>" <?= old('tipo_documento', $huesped['tipo_documento'] ?? 'CC') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Número de documento</label>
                    <input type="text" name="num_documento" class="form-control" required
                           value="<?= esc(old('num_documento', $huesped['num_documento'] ?? '')) ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nacionalidad</label>
                    <input type="text" name="nacionalidad" class="form-control"
                           value="<?= esc(old('nacionalidad', $huesped['nacionalidad'] ?? 'Colombia')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" name="telefono" class="form-control"
                           value="<?= esc(old('telefono', $huesped['telefono'] ?? '')) ?>" placeholder="+57 300 000 0000">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= esc(old('email', $huesped['email'] ?? '')) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Notas (alergias, preferencias...)</label>
                <textarea name="notas" class="form-control" rows="3"><?= esc(old('notas', $huesped['notas'] ?? '')) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                <a href="<?= site_url('huespedes') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
