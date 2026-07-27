<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $total = array_sum($datos['por_tipo']); ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Informe de PQR</h1>
        <p class="text-muted mb-0">De qué se queja la gente, y si se le contesta a tiempo.</p>
    </div>
    <a href="<?= site_url('pqr') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label class="form-label small text-muted mb-1">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= esc($desde) ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <label class="form-label small text-muted mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= esc($hasta) ?>">
            </div>
            <div class="col-sm-4 col-md-2">
                <button class="btn btn-outline-primary w-100"><i class="bi bi-funnel me-1"></i>Ver</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Recibidas</div>
                <div class="fs-3 fw-semibold"><?= $total ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Se tarda</div>
                <div class="fs-3 fw-semibold">
                    <?= $datos['dias_medio'] !== null ? number_format($datos['dias_medio'], 1, ',', '.') : '—' ?>
                </div>
                <div class="small text-muted">días en contestar</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Fuera de plazo</div>
                <div class="fs-3 fw-semibold <?= $datos['fuera_plazo'] > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= $datos['fuera_plazo'] ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Quedaron conformes</div>
                <div class="fs-3 fw-semibold">
                    <?= $datos['satisfaccion'] !== null ? number_format($datos['satisfaccion'], 1, ',', '.') : '—' ?>
                </div>
                <div class="small text-muted">sobre 5</div>
            </div>
        </div>
    </div>
</div>

<?php if ($datos['fuera_plazo'] > 0): ?>
    <div class="alert alert-danger">
        <strong><?= $datos['fuera_plazo'] ?></strong> se contestaron tarde o siguen sin contestar
        pasado el plazo. La Ley 1755/2015 no admite «se nos pasó»: si esto se repite, conviene
        que alguien tenga las PQR entre sus tareas del día, no entre lo que hará cuando pueda.
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-2"></i>De qué tipo</div>
            <div class="card-body">
                <?php if ($total === 0): ?>
                    <p class="text-muted small mb-0">Nada en este periodo.</p>
                <?php else: ?>
                    <?php foreach ($tipos as $t => $etiqueta): ?>
                        <?php $n = $datos['por_tipo'][$t] ?? 0; ?>
                        <?php if ($n === 0) { continue; } ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= esc($etiqueta) ?></span>
                                <span class="text-muted"><?= $n ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= in_array($t, ['queja', 'reclamo'], true) ? 'bg-danger' : ($t === 'felicitacion' ? 'bg-success' : '') ?>"
                                     style="width: <?= round($n / $total * 100) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-repeat me-2"></i>Lo que más se repite</div>
            <?php if ($datos['por_causa'] === []): ?>
                <div class="card-body text-muted small">
                    Sin causas apuntadas. Se rellenan al contestar una PQR, y es lo único que
                    convierte una lista de quejas en algo que se puede arreglar: cinco quejas
                    distintas por la misma causa no son cinco problemas.
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($datos['por_causa'] as $c): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= esc($c['causa']) ?></span>
                            <span class="badge text-bg-<?= (int) $c['cuantas'] >= 3 ? 'danger' : 'secondary' ?>">
                                <?= (int) $c['cuantas'] ?> vec<?= (int) $c['cuantas'] === 1 ? 'ez' : 'es' ?>
                            </span>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
