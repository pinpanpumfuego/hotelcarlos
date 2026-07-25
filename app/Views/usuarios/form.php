<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$editando = isset($usuario['id']);
$esYo     = $editando && (int) $usuario['id'] === (int) session()->get('usuario_id');
$permisos = [
    'gerencia'  => ['Gerencia', 'Acceso total: reportes, tarifas, usuarios y configuración.', 'bi-briefcase'],
    'recepcion' => ['Recepción', 'Reservas, huéspedes, caja, restaurante y registros de llegada.', 'bi-person-badge'],
    'limpieza'  => ['Limpieza', 'Solo su tablero de limpieza y el estado de las cabañas.', 'bi-bucket'],
];
$rolActual = old('rol', $usuario['rol'] ?? 'recepcion');
?>

<div class="mb-4">
    <a href="<?= site_url('usuarios') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Usuarios
    </a>
    <h1 class="h3 mb-0 mt-1"><?= $editando ? 'Editar ' . esc($usuario['nombre']) : 'Nuevo usuario' ?></h1>
    <p class="text-muted mb-0 mt-1">Cada persona del equipo entra con su propia cuenta: así todo queda trazado.</p>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $editando ? site_url('usuarios/actualizar/' . $usuario['id']) : site_url('usuarios/guardar') ?>">
    <?= csrf_field() ?>

    <div class="card border-0 shadow-sm mb-4" style="max-width: 720px;">
        <div class="card-body p-4">
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" required
                           value="<?= esc(old('nombre', $usuario['nombre'] ?? '')) ?>">
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Correo electrónico <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required autocomplete="off"
                           value="<?= esc(old('email', $usuario['email'] ?? '')) ?>">
                    <div class="form-text">Con este correo iniciará sesión.</div>
                </div>
            </div>

            <label class="form-label fw-semibold">Qué podrá hacer</label>
            <div class="row g-2 mb-3">
                <?php foreach ($permisos as $valor => [$titulo, $detalle, $icono]): ?>
                    <div class="col-12">
                        <input type="radio" class="btn-check" name="rol" value="<?= $valor ?>"
                               id="r<?= $valor ?>" <?= $rolActual === $valor ? 'checked' : '' ?> <?= $esYo ? 'disabled' : '' ?>>
                        <label class="btn btn-outline-primary w-100 text-start p-3" for="r<?= $valor ?>">
                            <i class="bi <?= $icono ?> me-1"></i><strong><?= $titulo ?></strong>
                            <span class="d-block small opacity-75"><?= $detalle ?></span>
                        </label>
                    </div>
                <?php endforeach ?>
            </div>
            <?php if ($esYo): ?>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Es tu propia cuenta: no puedes cambiarte el rol ni desactivarte, para no quedarte fuera del sistema.
                </div>
            <?php else: ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="activo" value="1" id="chkActivo"
                           <?= old('activo', $usuario['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="chkActivo">
                        <strong>Cuenta activa</strong>
                        <span class="d-block small text-muted">Al desactivarla, esa persona deja de poder entrar sin perder su historial.</span>
                    </label>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" style="max-width: 720px;">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-key me-2"></i>Contraseña</div>
        <div class="card-body p-4">
            <label class="form-label fw-semibold">
                <?= $editando ? 'Nueva contraseña' : 'Contraseña' ?>
                <?= $editando ? '' : '<span class="text-danger">*</span>' ?>
            </label>
            <input type="password" name="clave" class="form-control" minlength="8" autocomplete="new-password"
                   <?= $editando ? '' : 'required' ?>
                   placeholder="<?= $editando ? 'Déjala en blanco para no cambiarla' : 'Mínimo 8 caracteres' ?>">
            <div class="form-text">
                Mínimo 8 caracteres. Combina mayúsculas, minúsculas, números y algún símbolo.
                Se guarda cifrada: nadie, ni tú, podrá leerla después.
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Guardar usuario</button>
        <a href="<?= site_url('usuarios') ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<?= $this->endSection() ?>
