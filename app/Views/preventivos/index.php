<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Plan preventivo</h1>
        <p class="text-muted mb-0">Lo que hay que mirar cada tanto para que no se rompa.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post" action="<?= site_url('preventivos/generar') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary"><i class="bi bi-arrow-repeat me-1"></i>Generar ahora</button>
        </form>
        <a href="<?= site_url('preventivos/nuevo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo plan
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="alert alert-light border small">
    <i class="bi bi-clock-history me-1"></i>
    Las órdenes se abren solas si la tarea programada corre a diario. Es la misma
    pantalla de <a href="<?= site_url('administracion#tareas') ?>">Administración</a> donde se
    configuran las demás. <strong>Generar ahora</strong> sirve para dos cosas: salir del paso si
    la tarea falló, y comprobar que la orden funciona en este servidor antes de programarla.
</div>

<?php if ($generadas !== []): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-calendar-check me-2 text-primary"></i>Revisiones abiertas ahora mismo
            <span class="badge text-bg-primary ms-1"><?= count($generadas) ?></span>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($generadas as $o): ?>
                <a href="<?= site_url('mantenimiento/ver/' . $o['id']) ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <span class="fw-semibold"><?= esc($o['titulo']) ?></span>
                        <div class="small text-muted">
                            Abierta el <?= date('d/m/Y', strtotime($o['created_at'])) ?>
                            <?php if ($o['vence_en'] !== null && $o['vence_en'] < date('Y-m-d H:i:s')): ?>
                                <span class="badge text-bg-danger ms-1">Fuera de plazo</span>
                            <?php endif ?>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?php if ($planes === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-calendar3 fs-1 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">
                Todavía no hay ningún plan.<br>
                Empieza por lo que la ley obliga a revisar: <strong>extintores</strong>,
                <strong>piscina</strong> y <strong>planta eléctrica</strong>. Después, lo que ya
                sabes que da guerra: calentadores, bombas y cerraduras.
            </p>
            <a href="<?= site_url('preventivos/nuevo') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Crear el primero
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Plan</th>
                        <th class="d-none d-md-table-cell">Cada</th>
                        <th>Próxima</th>
                        <th class="d-none d-lg-table-cell">A cargo de</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planes as $p): ?>
                        <tr class="<?= (int) $p['activo'] === 0 ? 'opacity-50' : '' ?>">
                            <td>
                                <a href="<?= site_url('preventivos/editar/' . $p['id']) ?>" class="fw-semibold text-decoration-none">
                                    <?= esc($p['nombre']) ?>
                                </a>
                                <?php if ((int) $p['obligatorio'] === 1): ?>
                                    <span class="badge text-bg-dark ms-1" title="Lo exige la normativa">Obligatorio</span>
                                <?php endif ?>
                                <?php if ((int) $p['activo'] === 0): ?>
                                    <span class="badge text-bg-secondary ms-1">Apagado</span>
                                <?php endif ?>
                                <div class="small text-muted">
                                    <?php if ($p['activo_nombre'] !== null): ?>
                                        <?= esc($p['activo_nombre']) ?>
                                        <span class="font-monospace">· <?= esc($p['activo_codigo']) ?></span>
                                    <?php elseif ($p['categoria'] !== null): ?>
                                        Todos los de: <?= esc($categorias[$p['categoria']] ?? $p['categoria']) ?>
                                    <?php else: ?>
                                        Tarea general del alojamiento
                                    <?php endif ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell small text-muted">
                                <?= (int) $p['cada'] ?> <?= esc($periodos[$p['periodo']]) ?>
                            </td>
                            <td>
                                <?= date('d/m/Y', strtotime($p['proxima_fecha'])) ?>
                                <?php if ($p['vencido']): ?>
                                    <span class="badge text-bg-danger d-block d-sm-inline mt-1 mt-sm-0">Ya tocaba</span>
                                <?php elseif ($p['pronto']): ?>
                                    <span class="badge text-bg-warning d-block d-sm-inline mt-1 mt-sm-0">Pronto</span>
                                <?php endif ?>
                            </td>
                            <td class="d-none d-lg-table-cell small text-muted"><?= esc($p['asignado_nombre']) ?: '—' ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= site_url('preventivos/eliminar/' . $p['id']) ?>"
                                      onsubmit="return confirm('¿Borrar este plan? Las órdenes que ya generó se conservan.')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
