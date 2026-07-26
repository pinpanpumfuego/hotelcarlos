<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$entregadas = count(array_filter($lineas, static fn ($l) => (int) $l['entregado'] === 1));
?>

<div class="mb-4">
    <a href="<?= site_url('propinas') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Propinas
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-1">
        <div>
            <h1 class="h3 mb-1">
                Liquidación del <?= date('d/m/Y', strtotime($liquidacion['desde'])) ?>
                al <?= date('d/m/Y', strtotime($liquidacion['hasta'])) ?>
            </h1>
            <p class="text-muted mb-0">
                <?= esc($criterios[$liquidacion['criterio']] ?? $liquidacion['criterio']) ?> ·
                cerrada el <?= date('d/m/Y H:i', strtotime($liquidacion['cerrada_en'])) ?>
            </p>
        </div>
        <a href="<?= site_url('propinas/comprobante/' . $liquidacion['id']) ?>" target="_blank" rel="noopener"
           class="btn btn-primary">
            <i class="bi bi-printer me-1"></i>Imprimir comprobantes
        </a>
    </div>
</div>

<?= view('partes/errores') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Repartido</div>
            <div class="valor h4 mb-0">$<?= number_format((float) $liquidacion['repartido'], 0, ',', '.') ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Entre</div>
            <div class="valor h4 mb-0"><?= count($lineas) ?> persona<?= count($lineas) === 1 ? '' : 's' ?></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Entregado</div>
            <div class="valor h4 mb-0 <?= $entregadas === count($lineas) ? 'text-success' : 'text-warning' ?>">
                <?= $entregadas ?> de <?= count($lineas) ?>
            </div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-2"></i>Reparto</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th class="d-none d-md-table-cell">Cargo</th>
                    <th class="text-center d-none d-lg-table-cell">Comandas</th>
                    <th class="text-end">Le corresponde</th>
                    <th>Entrega</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lineas as $l): ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($l['nombre']) ?> <?= esc($l['apellidos']) ?></td>
                        <td class="d-none d-md-table-cell text-muted small"><?= esc($l['cargo']) ?></td>
                        <td class="text-center d-none d-lg-table-cell text-muted"><?= (int) $l['comandas'] ?></td>
                        <td class="text-end fw-semibold">$<?= number_format((float) $l['importe'], 0, ',', '.') ?></td>
                        <td>
                            <form method="post" action="<?= site_url('propinas/entregar/' . $l['id']) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-<?= (int) $l['entregado'] === 1 ? 'success' : 'secondary' ?>">
                                    <i class="bi <?= (int) $l['entregado'] === 1 ? 'bi-check-circle-fill' : 'bi-circle' ?> me-1"></i>
                                    <?= (int) $l['entregado'] === 1
                                        ? 'Entregada ' . date('d/m', strtotime($l['entregado_en']))
                                        : 'Marcar entregada' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="3">Total</td>
                    <td class="text-end">$<?= number_format((float) $liquidacion['repartido'], 0, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-receipt me-2"></i>Comandas incluidas (<?= count($comandas) ?>)
    </div>
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($comandas as $c): ?>
                <span class="badge text-bg-light text-muted">
                    <?= esc($c['numero']) ?> · $<?= number_format((float) $c['propina'], 0, ',', '.') ?>
                </span>
            <?php endforeach ?>
        </div>
        <?php if (trim((string) $liquidacion['notas']) !== ''): ?>
            <hr>
            <p class="small text-muted mb-0"><i class="bi bi-sticky me-1"></i><?= esc($liquidacion['notas']) ?></p>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
