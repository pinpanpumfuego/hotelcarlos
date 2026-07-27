<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $colorFinalidad = ['operativo' => 'primary', 'encuestas' => 'info', 'marketing' => 'warning']; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Plantillas de mensajes</h1>
        <p class="text-muted mb-0">Los textos que se le mandan al huésped.</p>
    </div>
    <a href="<?= site_url('comunicaciones') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Las de tipo <span class="badge text-bg-primary">No pide permiso</span> son avisos de la
    propia reserva: se mandan siempre, porque forman parte del servicio. Las demás solo salen
    a quien lo haya autorizado.
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Plantilla</th>
                    <th class="d-none d-md-table-cell">Asunto</th>
                    <th>Para qué</th>
                    <th class="text-center">Activa</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plantillas as $p): ?>
                    <tr class="<?= (int) $p['activa'] === 0 ? 'opacity-50' : '' ?>">
                        <td>
                            <a href="<?= site_url('comunicaciones/plantilla/' . $p['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= esc($p['nombre']) ?>
                            </a>
                            <div class="small text-muted font-monospace">
                                <?= esc($p['clave']) ?> · <?= esc($p['idioma']) ?> · <?= esc($p['canal']) ?>
                            </div>
                            <?php if ($p['desconocidas'] !== []): ?>
                                <div class="small text-danger mt-1">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    {{<?= esc(implode('}}, {{', $p['desconocidas'])) ?>}} no existe y saldrá en blanco
                                </div>
                            <?php endif ?>
                        </td>
                        <td class="d-none d-md-table-cell small text-muted"><?= esc($p['asunto']) ?></td>
                        <td>
                            <span class="badge text-bg-<?= $colorFinalidad[$p['finalidad']] ?? 'secondary' ?>">
                                <?= $p['finalidad'] === 'operativo' ? 'No pide permiso' : 'Pide permiso' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <i class="bi bi-<?= (int) $p['activa'] === 1 ? 'check-circle text-success' : 'x-circle text-muted' ?>"></i>
                        </td>
                        <td class="text-end">
                            <?php if (puede('comunicaciones.plantillas')): ?>
                                <a href="<?= site_url('comunicaciones/plantilla/' . $p['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-braces me-2"></i>Variables que puedes usar</div>
    <div class="card-body">
        <div class="row g-2 small">
            <?php foreach ($variables as $v => $que): ?>
                <div class="col-md-6 col-lg-4">
                    <code>{{<?= $v ?>}}</code>
                    <span class="text-muted">· <?= esc($que) ?></span>
                </div>
            <?php endforeach ?>
        </div>
        <div class="form-text mt-3">
            Lo que no exista se queda en blanco. Un correo que dice «Hola {{nombre}}» delata que
            nadie lo miró, así que es preferible que diga «Hola» a secas.
        </div>
    </div>
</div>

<?= $this->endSection() ?>
