<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="mb-4">
    <h1 class="h3 mb-1">Traducciones</h1>
    <p class="text-muted mb-0">
        Lo que escribes tú: cabañas, servicios, experiencias y carta.
        Si algo no está traducido, la web enseña el español; nunca queda un hueco.
    </p>
</div>

<?= view('partes/errores') ?>

<!-- ── Cuánto falta en cada idioma ── -->
<div class="row g-3 mb-4">
    <?php foreach ($avance as $codigo => $a): ?>
        <div class="col-6 col-lg-4">
            <a href="<?= site_url('traducciones?idioma=' . $codigo . '&tabla=' . $tabla) ?>"
               class="card border-0 shadow-sm h-100 text-decoration-none text-reset
                      <?= $codigo === $idioma ? 'border-primary border-2' : '' ?>"
               style="<?= $codigo === $idioma ? 'outline:2px solid var(--bs-primary)' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold"><?= $a['bandera'] ?> <?= esc($a['nombre']) ?></span>
                        <span class="badge <?= $a['pct'] === 100 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= $a['pct'] ?> %
                        </span>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar <?= $a['pct'] === 100 ? 'bg-success' : '' ?>"
                             style="width: <?= $a['pct'] ?>%"></div>
                    </div>
                    <div class="text-muted small mt-2"><?= $a['hechos'] ?> de <?= $a['total'] ?> textos</div>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<!-- ── Qué se traduce ── -->
<div class="d-flex gap-2 flex-wrap mb-4">
    <?php foreach ($grupos as $clave => $info): ?>
        <a href="<?= site_url('traducciones?idioma=' . $idioma . '&tabla=' . $clave) ?>"
           class="btn btn-sm <?= $clave === $tabla ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= esc($info['etiqueta']) ?>
        </a>
    <?php endforeach ?>
</div>

<?php if ($fichas === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-translate fs-3 d-block mb-2 opacity-50"></i>
            No hay nada escrito todavía en esta sección.
        </div>
    </div>
<?php else: ?>

<form method="post" action="<?= site_url('traducciones/guardar') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="idioma" value="<?= esc($idioma) ?>">
    <input type="hidden" name="tabla" value="<?= esc($tabla) ?>">

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">
                <?= $idiomas[$idioma]['bandera'] ?>
                <?= esc($grupos[$tabla]['etiqueta']) ?> en <?= esc($idiomas[$idioma]['nombre']) ?>
            </span>
            <span class="text-muted small">El español está a la izquierda para poder copiarlo.</span>
        </div>

        <div class="card-body">
            <?php foreach ($fichas as $f): ?>
                <div class="mb-4 pb-3 border-bottom">
                    <div class="fw-semibold mb-2"><?= esc($f['titulo']) ?></div>

                    <?php foreach ($f['textos'] as $t): ?>
                        <div class="row g-2 mb-2 align-items-start">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-1">
                                    <?= esc(ucfirst(str_replace('_', ' ', $t['campo']))) ?> · español
                                </label>
                                <div class="form-control bg-light text-muted" style="min-height:38px; white-space:pre-wrap;">
                                    <?= esc($t['original']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">
                                    <?= esc($idiomas[$idioma]['nombre']) ?>
                                    <?php if (trim($t['traducido']) === ''): ?>
                                        <span class="badge text-bg-warning ms-1">Falta</span>
                                    <?php endif ?>
                                </label>
                                <?php if (mb_strlen($t['original']) > 90): ?>
                                    <textarea class="form-control" rows="3"
                                              name="textos[<?= (int) $f['id'] ?>][<?= esc($t['campo']) ?>]"
                                              placeholder="Sin traducir"><?= esc($t['traducido']) ?></textarea>
                                <?php else: ?>
                                    <input type="text" class="form-control"
                                           name="textos[<?= (int) $f['id'] ?>][<?= esc($t['campo']) ?>]"
                                           value="<?= esc($t['traducido']) ?>" placeholder="Sin traducir">
                                <?php endif ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        </div>

        <div class="card-footer bg-white p-3 d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            <a href="<?= url_web('', $idioma) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-up-right me-1"></i>Ver la web en <?= esc($idiomas[$idioma]['nombre']) ?>
            </a>
            <span class="text-muted small ms-auto">
                Deja un campo vacío para marcarlo como no traducido.
            </span>
        </div>
    </div>
</form>

<?php endif ?>

<div class="alert alert-light mt-4">
    <i class="bi bi-lightbulb me-1"></i>
    <strong>Los nombres de los platos no se traducen a propósito.</strong>
    «Sancocho de gallina» o «Calentado paisa» son nombres propios: traducirlos da resultados
    ridículos y le quita al plato justo lo que lo hace interesante para un extranjero.
    Lo que sí se traduce es la descripción de al lado, que es donde se explica qué lleva.
</div>

<?= $this->endSection() ?>
