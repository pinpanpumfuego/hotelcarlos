<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php use App\Models\PropinaLiquidacionModel; ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Propinas</h1>
        <p class="text-muted mb-0">
            El 100 % de la propina es de los trabajadores. Aquí queda constancia de cómo se repartió.
        </p>
    </div>
    <a href="<?= site_url('propinas/preparar') ?>" class="btn btn-primary">
        <i class="bi bi-cash-stack me-1"></i>Liquidar
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($suelto > 0): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <i class="bi bi-hourglass-split me-1"></i>
            <strong>$<?= number_format($suelto, 0, ',', '.') ?> sin repartir</strong>
            en <?= (int) $comandasSueltas ?> comanda<?= (int) $comandasSueltas === 1 ? '' : 's' ?>.
            Ese dinero no es del hotel.
        </div>
        <a href="<?= site_url('propinas/preparar') ?>" class="btn btn-sm btn-warning">Repartir ahora</a>
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-1"></i>
        No hay propinas pendientes de repartir.
    </div>
<?php endif ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2"></i>Liquidaciones hechas</div>

    <?php if ($liquidaciones === []): ?>
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-cash-stack fs-3 d-block mb-2 opacity-50"></i>
            Todavía no has liquidado ninguna propina.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Periodo</th>
                        <th class="d-none d-md-table-cell">Criterio</th>
                        <th class="text-end">Repartido</th>
                        <th class="text-center">Personas</th>
                        <th>Entrega</th>
                        <th class="d-none d-lg-table-cell">Hecha por</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($liquidaciones as $l): ?>
                        <?php
                        $personas  = (int) $l['personas'];
                        $entregadas = (int) $l['entregadas'];
                        ?>
                        <tr>
                            <td>
                                <span class="fw-semibold">
                                    <?= date('d/m/Y', strtotime($l['desde'])) ?> → <?= date('d/m/Y', strtotime($l['hasta'])) ?>
                                </span>
                                <?php if (trim((string) $l['notas']) !== ''): ?>
                                    <div class="text-muted small"><?= esc($l['notas']) ?></div>
                                <?php endif ?>
                            </td>
                            <td class="d-none d-md-table-cell text-muted small">
                                <?= esc(PropinaLiquidacionModel::CRITERIOS[$l['criterio']] ?? $l['criterio']) ?>
                            </td>
                            <td class="text-end fw-semibold">$<?= number_format((float) $l['repartido'], 0, ',', '.') ?></td>
                            <td class="text-center"><?= $personas ?></td>
                            <td>
                                <?php if ($personas > 0 && $entregadas >= $personas): ?>
                                    <span class="badge text-bg-success">Todo entregado</span>
                                <?php elseif ($entregadas > 0): ?>
                                    <span class="badge text-bg-warning"><?= $entregadas ?> de <?= $personas ?></span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Sin entregar</span>
                                <?php endif ?>
                            </td>
                            <td class="d-none d-lg-table-cell text-muted small"><?= esc($l['usuario_nombre'] ?? '—') ?></td>
                            <td class="text-end">
                                <a href="<?= site_url('propinas/ver/' . $l['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Ver">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</div>

<div class="alert alert-light mt-4">
    <i class="bi bi-shield-check me-1"></i>
    <strong>Por qué existe esta pantalla.</strong> La Ley 1935 de 2018 obliga a que la propina llegue
    entera a los trabajadores y a informarles de cómo se repartió. De palabra no vale: aquí se genera
    un comprobante por persona para que lo firme al recibir su parte.
</div>

<?= $this->endSection() ?>
