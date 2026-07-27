<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Políticas y textos legales</h1>
        <p class="text-muted mb-0">Lo que el huésped acepta, y qué decía exactamente cuando lo aceptó.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= site_url('legal/politica') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nueva versión
        </a>
        <a href="<?= site_url('legal') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    El registro de llegada ya guardaba <strong>qué versión</strong> aceptó cada huésped, pero no
    había dónde consultar <strong>qué decía esa versión</strong>. Guardar el número sin el texto es
    guardar una referencia a un documento que nadie puede recuperar — justo lo que habría que
    enseñar si alguien reclama.
</div>

<?php if ($politicas === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-file-text fs-1 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">
                Ninguna versión guardada todavía.<br>
                Empieza por la <strong>política de tratamiento de datos</strong>: es la que sostiene
                todas las autorizaciones que ya se están recogiendo.
            </p>
            <a href="<?= site_url('legal/politica') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Escribir la primera
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Documento</th>
                        <th>Versión</th>
                        <th>Vigente desde</th>
                        <th class="text-center">Publicada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($politicas as $p): ?>
                        <tr class="<?= (int) $p['publicada'] === 0 ? 'opacity-50' : '' ?>">
                            <td>
                                <a href="<?= site_url('legal/politica/' . $p['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($p['titulo']) ?>
                                </a>
                                <div class="small text-muted"><?= esc($tipos[$p['tipo']] ?? $p['tipo']) ?></div>
                            </td>
                            <td class="font-monospace"><?= esc($p['version']) ?></td>
                            <td class="small"><?= date('d/m/Y', strtotime($p['vigente_desde'])) ?></td>
                            <td class="text-center">
                                <i class="bi bi-<?= (int) $p['publicada'] === 1 ? 'check-circle text-success' : 'x-circle text-muted' ?>"></i>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('legal/politica/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
