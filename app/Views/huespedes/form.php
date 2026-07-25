<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$editando = isset($huesped['id']);
$tiposDoc = [
    'CC'        => 'Cédula de ciudadanía',
    'CE'        => 'Cédula de extranjería',
    'PASAPORTE' => 'Pasaporte',
    'TI'        => 'Tarjeta de identidad',
    'OTRO'      => 'Otro',
];
?>

<div class="mb-4">
    <a href="<?= site_url('huespedes') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Huéspedes
    </a>
    <h1 class="h3 mb-0 mt-1">
        <?= $editando ? esc($huesped['nombre'] . ' ' . $huesped['apellidos']) : 'Nuevo huésped' ?>
    </h1>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $editando ? site_url('huespedes/actualizar/' . $huesped['id']) : site_url('huespedes/guardar') ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-person-vcard me-2"></i>Identificación</div>
                <div class="card-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" required
                                   value="<?= esc(old('nombre', $huesped['nombre'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" name="apellidos" class="form-control" required
                                   value="<?= esc(old('apellidos', $huesped['apellidos'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-5">
                            <label class="form-label fw-semibold">Tipo de documento</label>
                            <select name="tipo_documento" class="form-select">
                                <?php foreach ($tiposDoc as $valor => $etiqueta): ?>
                                    <option value="<?= $valor ?>" <?= old('tipo_documento', $huesped['tipo_documento'] ?? 'CC') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Número <span class="text-danger">*</span></label>
                            <input type="text" name="num_documento" class="form-control" required
                                   value="<?= esc(old('num_documento', $huesped['num_documento'] ?? '')) ?>">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label fw-semibold">Nacionalidad</label>
                            <input type="text" name="nacionalidad" class="form-control"
                                   value="<?= esc(old('nacionalidad', $huesped['nacionalidad'] ?? 'Colombia')) ?>">
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        No puede haber dos huéspedes con el mismo tipo y número de documento.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-telephone me-2"></i>Contacto y notas</div>
                <div class="card-body p-4">
                    <label class="form-label fw-semibold">Teléfono</label>
                    <input type="tel" name="telefono" class="form-control mb-3"
                           value="<?= esc(old('telefono', $huesped['telefono'] ?? '')) ?>" placeholder="+57 300 000 0000">

                    <label class="form-label fw-semibold">Correo electrónico</label>
                    <input type="email" name="email" class="form-control mb-1"
                           value="<?= esc(old('email', $huesped['email'] ?? '')) ?>">
                    <div class="form-text mb-3">Se usa para enviarle la confirmación y su enlace de registro.</div>

                    <label class="form-label fw-semibold">Notas</label>
                    <textarea name="notas" class="form-control" rows="4"
                              placeholder="Alergias, preferencias, incidencias previas…"><?= esc(old('notas', $huesped['notas'] ?? '')) ?></textarea>
                    <div class="form-text">Las ve el personal, nunca el huésped.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Guardar huésped</button>
        <a href="<?= site_url('huespedes') ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>
