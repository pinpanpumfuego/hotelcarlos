<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4">Mi perfil</h1>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="kpi-icono bg-success-subtle text-success"><i class="bi bi-person"></i></div>
                    <div>
                        <div class="fs-5 fw-semibold"><?= esc($usuario['nombre']) ?></div>
                        <div class="text-muted small"><?= esc($usuario['email']) ?></div>
                    </div>
                </div>
                <p class="mb-1"><span class="text-muted">Rol:</span> <?= esc(\App\Models\UsuarioModel::ROLES[$usuario['rol']] ?? $usuario['rol']) ?></p>
                <p class="mb-0"><span class="text-muted">Último acceso:</span> <?= $usuario['ultimo_acceso'] ? esc(date('d/m/Y H:i', strtotime($usuario['ultimo_acceso']))) : '—' ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-key me-2"></i>Cambiar mi contraseña</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('perfil/clave') ?>" style="max-width: 420px;">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Contraseña actual</label>
                        <input type="password" name="clave_actual" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña nueva</label>
                        <input type="password" name="clave_nueva" class="form-control" required minlength="8" autocomplete="new-password">
                        <div class="form-text">Mínimo 8 caracteres. Combina mayúsculas, minúsculas, números y símbolos.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Repite la contraseña nueva</label>
                        <input type="password" name="clave_confirmacion" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
