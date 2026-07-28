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

        <?php // Un equipo reconocido lo está un año. Eso es lo que hace cómoda la
              // entrada con PIN, y también lo que obliga a poder quitarlo el día
              // que se pierde un móvil. ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-laptop me-2"></i>Equipos donde puedes entrar con PIN
            </div>
            <div class="card-body">
                <?php if (empty($equipos)): ?>
                    <p class="text-muted mb-0">
                        Todavía no hay ninguno. Cada vez que entres con tu contraseña en un equipo,
                        ese equipo queda reconocido durante un año.
                    </p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($equipos as $e): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0 gap-3">
                                <div>
                                    <div class="fw-semibold">
                                        <?= esc($e['nombre'] ?? 'Equipo') ?>
                                        <?php if ((int) $e['id'] === (int) $equipo_actual): ?>
                                            <span class="badge text-bg-success ms-1">este</span>
                                        <?php endif ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php if ($e['ultimo_uso']): ?>
                                            Último uso: <?= date('d/m/Y H:i', strtotime($e['ultimo_uso'])) ?> ·
                                        <?php endif ?>
                                        Caduca el <?= date('d/m/Y', strtotime($e['expira'])) ?>
                                    </div>
                                </div>
                                <form method="post" action="<?= site_url('perfil/equipo/quitar/' . $e['id']) ?>"
                                      onsubmit="return confirm('¿Quitar este equipo? Desde ahí habrá que entrar con la contraseña.')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger">Quitar</button>
                                </form>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <div class="form-text mt-3">
                        Si pierdes un móvil o dejas de usar un equipo, quítalo de aquí. Mientras
                        siga en la lista, quien lo tenga solo necesita tu correo y tu PIN.
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
