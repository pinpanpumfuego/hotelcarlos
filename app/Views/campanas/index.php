<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $colorEstado = ['borrador' => 'secondary', 'enviada' => 'success', 'cancelada' => 'dark']; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Campañas</h1>
        <p class="text-muted mb-0">Mandarle lo mismo a un grupo, sabiendo antes a quién.</p>
    </div>
    <a href="<?= site_url('campanas/nueva') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nueva campaña
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($campanas === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-megaphone fs-1 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">
                Ninguna todavía.<br>
                Una campaña útil no es «a todos»: es «a los que vinieron hace más de un año
                y les gustaban las aves».
            </p>
            <a href="<?= site_url('campanas/nueva') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Crear la primera
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Campaña</th>
                        <th class="d-none d-md-table-cell">A quién</th>
                        <th>Estado</th>
                        <th class="text-end">Salieron</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campanas as $c): ?>
                        <?php $filtros = json_decode((string) $c['filtros'], true) ?: []; ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('campanas/ver/' . $c['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($c['nombre']) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= esc($c['plantilla_nombre'] ?? $c['plantilla_clave']) ?>
                                    <?php if ($c['usuario_nombre']): ?>
                                        · <?= esc($c['usuario_nombre']) ?>
                                    <?php endif ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell small text-muted">
                                <?= esc($segmentos->describir($filtros)) ?>
                            </td>
                            <td><span class="badge text-bg-<?= $colorEstado[$c['estado']] ?>"><?= ucfirst($c['estado']) ?></span></td>
                            <td class="text-end">
                                <?php if ($c['estado'] === 'enviada'): ?>
                                    <?= (int) $c['encolados'] ?>
                                    <?php if ((int) $c['saltados'] > 0): ?>
                                        <div class="small text-muted"><?= (int) $c['saltados'] ?> sin permiso</div>
                                    <?php endif ?>
                                <?php else: ?>
                                    —
                                <?php endif ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('campanas/ver/' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye"></i>
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
