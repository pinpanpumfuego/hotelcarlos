<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorResultado = ['ok' => 'success', 'error' => 'warning', 'denegado' => 'danger'];
$etiqueta       = ['ok' => 'Hecho', 'error' => 'No se pudo', 'denegado' => 'Sin permiso'];
?>

<div class="mb-4">
    <a href="<?= site_url('auditoria') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Auditoría
    </a>
    <h1 class="h3 mb-0 mt-1">Historial de <code><?= esc($referencia) ?></code></h1>
    <p class="text-muted mb-0 mt-1">
        Todo lo que se hizo sobre esto, venga de donde venga: del panel, del TPV
        o del móvil.
    </p>
</div>

<?php if ($registros === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-clipboard fs-2 d-block mb-2 text-muted opacity-50"></i>
            <p class="text-muted mb-0">No hay nada registrado sobre esto.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php foreach ($registros as $r): ?>
                <div class="d-flex gap-3 pb-3 mb-3 border-bottom">
                    <div class="text-center text-muted small" style="min-width: 78px;">
                        <div class="fw-semibold"><?= date('d/m/Y', strtotime($r['created_at'])) ?></div>
                        <div><?= date('H:i:s', strtotime($r['created_at'])) ?></div>
                    </div>
                    <div class="flex-grow-1">
                        <div>
                            <strong><?= esc($r['usuario_nombre']) ?></strong>
                            <?php if (! empty($r['perfil'])): ?>
                                <span class="badge text-bg-light border ms-1"><?= esc($r['perfil']) ?></span>
                            <?php endif ?>
                            <span class="badge text-bg-<?= $colorResultado[$r['resultado']] ?? 'secondary' ?> ms-1">
                                <?= $etiqueta[$r['resultado']] ?? $r['resultado'] ?>
                            </span>
                        </div>
                        <div class="small mt-1"><?= esc($sensibles[$r['permiso']] ?? $r['permiso']) ?></div>
                        <code class="small text-muted"><?= esc($r['metodo']) ?> /<?= esc($r['ruta']) ?></code>
                        <?php if (! empty($r['ip'])): ?>
                            <span class="small text-muted ms-2">desde <?= esc($r['ip']) ?></span>
                        <?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
