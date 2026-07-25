<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4"><?= isset($usuario['id']) ? 'Editar usuario' : 'Nuevo usuario' ?></h1>

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

        <?php $esYo = isset($usuario['id']) && (int) $usuario['id'] === (int) session()->get('usuario_id'); ?>

        <form method="post" action="<?= isset($usuario['id']) ? site_url('usuarios/actualizar/' . $usuario['id']) : site_url('usuarios/guardar') ?>">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Nombre completo</label>
                <input type="text" name="nombre" class="form-control" required
                       value="<?= esc(old('nombre', $usuario['nombre'] ?? '')) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="email" class="form-control" required
                       value="<?= esc(old('email', $usuario['email'] ?? '')) ?>">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select" <?= $esYo ? 'disabled' : '' ?>>
                        <?php foreach (\App\Models\UsuarioModel::ROLES as $valor => $etiqueta): ?>
                            <option value="<?= $valor ?>" <?= old('rol', $usuario['rol'] ?? 'recepcion') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                        <?php endforeach ?>
                    </select>
                    <?php if ($esYo): ?>
                        <div class="form-text">No puedes cambiar tu propio rol.</div>
                    <?php endif ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label d-block">Estado</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="chkActivo"
                               <?= old('activo', $usuario['activo'] ?? 1) ? 'checked' : '' ?> <?= $esYo ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="chkActivo">Usuario activo</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= isset($usuario['id']) ? 'Nueva contraseña (dejar en blanco para no cambiarla)' : 'Contraseña' ?></label>
                <input type="password" name="clave" class="form-control" minlength="8"
                       <?= isset($usuario['id']) ? '' : 'required' ?> autocomplete="new-password">
                <div class="form-text">Mínimo 8 caracteres. Combina mayúsculas, minúsculas y números.</div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                <a href="<?= site_url('usuarios') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
