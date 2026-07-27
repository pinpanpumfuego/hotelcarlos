<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$enCurso = $tarea['estado'] === 'en_curso';
$colores = ['pendiente' => 'secondary', 'en_curso' => 'info', 'hecha' => 'primary',
            'inspeccionada' => 'success', 'rechazada' => 'danger'];
?>

<div class="mb-4">
    <a href="<?= site_url('limpieza') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Limpieza
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-0"><?= esc($tarea['cabana'] ?? '—') ?></h1>
            <p class="text-muted mb-0 mt-1">
                <?= esc($tipos[$tarea['tipo']] ?? $tarea['tipo']) ?>
                <?php if (! empty($tarea['quien'])): ?> · <?= esc($tarea['quien']) ?><?php endif ?>
                <?php if ($tarea['inicio'] !== null): ?>
                    · desde las <?= date('H:i', strtotime($tarea['inicio'])) ?>
                <?php endif ?>
            </p>
        </div>
        <span class="badge text-bg-<?= $colores[$tarea['estado']] ?? 'secondary' ?> fs-6">
            <?= esc($estados[$tarea['estado']] ?? $tarea['estado']) ?>
        </span>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if (! empty($tarea['motivo_rechazo'])): ?>
    <div class="alert alert-danger d-flex gap-2">
        <i class="bi bi-arrow-counterclockwise mt-1"></i>
        <div>
            <strong>Hay que rehacerla.</strong>
            <div class="small mt-1"><?= esc($tarea['motivo_rechazo']) ?></div>
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Qué hay que repasar</span>
                <span class="small text-muted"><?= $progreso['hechos'] ?> de <?= $progreso['total'] ?></span>
            </div>
            <?php if ($progreso['total'] > 0): ?>
                <div class="progress rounded-0" style="height: 4px;">
                    <div class="progress-bar bg-success" style="width: <?= $progreso['pct'] ?>%"></div>
                </div>
            <?php endif ?>

            <div class="card-body py-2">
                <?php if ($puntos === []): ?>
                    <p class="text-muted small mb-0">
                        Esta cabaña no tiene checklist configurado. Se puede terminar
                        directamente, o
                        <a href="<?= site_url('limpieza/checklist') ?>" class="text-decoration-none">añadir puntos</a>.
                    </p>
                <?php else: ?>
                    <?php foreach ($puntos as $p): ?>
                        <div class="py-2 border-bottom">
                            <form method="post" action="<?= site_url('limpieza/punto/' . $tarea['id'] . '/' . $p['id']) ?>"
                                  enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <div class="d-flex align-items-start gap-2">
                                    <input type="hidden" name="hecho" value="0">
                                    <input class="form-check-input mt-1" type="checkbox" name="hecho" value="1"
                                           id="p<?= (int) $p['id'] ?>"
                                           <?= $p['hecho'] ? 'checked' : '' ?>
                                           <?= $enCurso ? '' : 'disabled' ?>
                                           onchange="this.form.requestSubmit()">
                                    <label class="form-check-label flex-grow-1" for="p<?= (int) $p['id'] ?>">
                                        <?= esc($p['texto']) ?>
                                        <?php if ($p['exige_foto']): ?>
                                            <span class="badge text-bg-<?= ! empty($p['foto']) ? 'success' : 'warning' ?> ms-1">
                                                <i class="bi bi-camera me-1"></i><?= ! empty($p['foto']) ? 'con foto' : 'necesita foto' ?>
                                            </span>
                                        <?php endif ?>
                                        <?php if (! empty($p['nota'])): ?>
                                            <span class="d-block small text-muted"><?= esc($p['nota']) ?></span>
                                        <?php endif ?>
                                    </label>
                                </div>

                                <?php if ($enCurso && $p['exige_foto']): ?>
                                    <div class="d-flex gap-2 mt-2 ms-4">
                                        <?php // `capture` abre la cámara directamente en el móvil ?>
                                        <input type="file" name="foto" accept="image/*" capture="environment"
                                               class="form-control form-control-sm">
                                        <button class="btn btn-sm btn-outline-primary text-nowrap">Subir</button>
                                    </div>
                                <?php endif ?>

                                <?php if ($enCurso): ?>
                                    <input type="text" name="nota" class="form-control form-control-sm mt-2 ms-4"
                                           style="width: calc(100% - 1.5rem);" maxlength="300"
                                           value="<?= esc($p['nota'] ?? '') ?>" placeholder="Nota (opcional)">
                                <?php endif ?>
                            </form>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>

            <?php if ($enCurso): ?>
                <div class="card-footer bg-white">
                    <form method="post" action="<?= site_url('limpieza/terminar/' . $tarea['id']) ?>">
                        <?= csrf_field() ?>
                        <textarea name="notas" class="form-control form-control-sm mb-2" rows="2"
                                  placeholder="¿Algo que contar? Daños, cosas que faltan…"><?= esc($tarea['notas'] ?? '') ?></textarea>
                        <button class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i>Terminar la limpieza
                        </button>
                        <span class="small text-muted ms-2">
                            No deja terminar si falta algún punto o alguna foto.
                        </span>
                    </form>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-bag me-2"></i>¿Se ha olvidado algo?
            </div>
            <div class="card-body">
                <form method="post" action="<?= site_url('limpieza/objeto') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="unidad_id" value="<?= (int) $tarea['unidad_id'] ?>">
                    <input type="hidden" name="limpieza_id" value="<?= (int) $tarea['id'] ?>">

                    <input type="text" name="descripcion" class="form-control form-control-sm mb-2"
                           maxlength="300" required placeholder="Qué es: cargador, gafas, un libro…">
                    <input type="text" name="donde" class="form-control form-control-sm mb-2"
                           maxlength="150" placeholder="Dónde estaba">
                    <input type="file" name="foto" accept="image/*" capture="environment"
                           class="form-control form-control-sm mb-2">

                    <div class="d-grid">
                        <button class="btn btn-outline-primary btn-sm">Apuntar el objeto</button>
                    </div>
                    <div class="form-text mt-2">
                        Se guarda con la cabaña y con quién estaba alojado, para que
                        recepción pueda avisarle.
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
