<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Perfiles de acceso</h1>
        <p class="text-muted mb-0">
            Un perfil decide qué puede hacer cada puesto. Se asigna a cada persona
            desde <a href="<?= site_url('usuarios') ?>" class="text-decoration-none">Usuarios</a>.
        </p>
    </div>
    <a href="<?= site_url('roles/nuevo') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nuevo perfil
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($huerfanos !== []): ?>
    <div class="alert alert-warning d-flex gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <strong>Hay permisos guardados que ya no existen en el sistema.</strong>
            <div class="small mt-1">
                No hacen daño —al comprobar se ignoran— pero suelen significar que
                algo se renombró y quedó a medias:
                <code><?= esc(implode('</code>, <code>', $huerfanos), 'raw') ?></code>
            </div>
        </div>
    </div>
<?php endif ?>

<div class="row g-3">
    <?php foreach ($roles as $r): ?>
        <?php
        $esTotal = $r['clave'] === \App\Libraries\Permisos\Catalogo::ROL_TOTAL;
        $pct     = $totalPerm > 0 ? (int) round($r['permisos'] / $totalPerm * 100) : 0;
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h5 mb-0"><?= esc($r['nombre']) ?></h2>
                        <?php if ($esTotal): ?>
                            <span class="badge text-bg-dark"><i class="bi bi-shield-lock me-1"></i>Acceso total</span>
                        <?php elseif ((int) $r['es_sistema'] === 1): ?>
                            <span class="badge text-bg-secondary">Del sistema</span>
                        <?php endif ?>
                    </div>

                    <p class="small text-muted flex-grow-1"><?= esc($r['descripcion'] ?? '') ?></p>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Permisos</span>
                            <span class="fw-semibold"><?= (int) $r['permisos'] ?> de <?= $totalPerm ?></span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar <?= $esTotal ? 'bg-dark' : '' ?>" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>

                    <div class="small text-muted mb-3">
                        <i class="bi bi-people me-1"></i>
                        <?= (int) $r['usuarios'] === 0
                            ? 'Nadie lo tiene todavía'
                            : (int) $r['usuarios'] . ' persona' . ((int) $r['usuarios'] === 1 ? '' : 's') ?>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?= site_url('roles/editar/' . $r['id']) ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                            <i class="bi bi-<?= $esTotal ? 'eye' : 'sliders' ?> me-1"></i>
                            <?= $esTotal ? 'Ver permisos' : 'Configurar' ?>
                        </a>
                        <?php if (! $esTotal && (int) $r['es_sistema'] === 0 && (int) $r['usuarios'] === 0): ?>
                            <form method="post" action="<?= site_url('roles/eliminar/' . $r['id']) ?>"
                                  onsubmit="return confirm('¿Eliminar el perfil «<?= esc($r['nombre'], 'js') ?>»?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<div class="alert alert-light border small mt-4 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Gerencia no se puede tocar.</strong> Tiene todos los permisos siempre,
    y es a propósito: si un día se configura mal otro perfil, es la cuenta que
    sigue pudiendo entrar a arreglarlo.
</div>

<?= $this->endSection() ?>
