<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$dinero = static fn (float $n): string => '$' . number_format($n, 0, ',', '.');
$colorTipo = ['ingreso' => 'success', 'egreso' => 'danger', 'retiro' => 'warning', 'ajuste' => 'secondary'];
$nombreTipo = ['ingreso' => 'Ingreso', 'egreso' => 'Gasto', 'retiro' => 'Retiro', 'ajuste' => 'Ajuste'];
$dif = (float) $turno['diferencia'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Turno cerrado</h1>
        <p class="text-muted mb-0">
            <?= esc($turno['punto_nombre']) ?: 'Sin punto' ?>
            · <?= date('d/m/Y H:i', strtotime($turno['apertura'])) ?>
            → <?= $turno['cierre'] ? date('d/m/Y H:i', strtotime($turno['cierre'])) : 'abierto' ?>
        </p>
    </div>
    <a href="<?= site_url('caja') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if (abs($dif) >= 0.01): ?>
    <div class="alert alert-<?= $dif < 0 ? 'danger' : 'warning' ?>">
        <strong><?= $dif < 0 ? 'Faltaron' : 'Sobraron' ?> <?= $dinero(abs($dif)) ?>.</strong>
        <?php if ($turno['justificacion']): ?>
            <div class="mt-1"><?= esc($turno['justificacion']) ?></div>
        <?php else: ?>
            <div class="mt-1 small">Sin explicación apuntada.</div>
        <?php endif ?>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calculator me-2"></i>El cierre</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-7 text-muted fw-normal">Base inicial</dt>
                    <dd class="col-5 text-end"><?= $dinero((float) $turno['base_inicial']) ?></dd>

                    <dt class="col-7 text-muted fw-normal">+ Efectivo cobrado</dt>
                    <dd class="col-5 text-end text-success"><?= $dinero($totales['ingresos']) ?></dd>

                    <dt class="col-7 text-muted fw-normal">− Gastos</dt>
                    <dd class="col-5 text-end text-danger"><?= $dinero($totales['egresos']) ?></dd>

                    <dt class="col-7 text-muted fw-normal">− Retiros a caja fuerte</dt>
                    <dd class="col-5 text-end text-warning"><?= $dinero($totales['retiros']) ?></dd>

                    <dt class="col-7 border-top pt-2">Debería haber</dt>
                    <dd class="col-5 text-end border-top pt-2">
                        <?= $dinero((float) ($turno['esperado'] ?? 0)) ?>
                    </dd>

                    <dt class="col-7">Se contó</dt>
                    <dd class="col-5 text-end fw-semibold"><?= $dinero((float) $turno['efectivo_contado']) ?></dd>

                    <dt class="col-7 border-top pt-2">Diferencia</dt>
                    <dd class="col-5 text-end border-top pt-2 fw-semibold <?= abs($dif) < 0.01 ? 'text-success' : ($dif < 0 ? 'text-danger' : 'text-warning') ?>">
                        <?= abs($dif) < 0.01 ? 'Cuadró' : (($dif < 0 ? '−' : '+') . $dinero(abs($dif))) ?>
                    </dd>
                </dl>

                <div class="small text-muted border-top mt-3 pt-2">
                    Abrió <?= esc($turno['usuario_nombre']) ?: '—' ?>
                    <?php if ($turno['cerro_nombre']): ?>
                        · cerró <?= esc($turno['cerro_nombre']) ?>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <?php if ($conteo !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-cash me-2"></i>Cómo se contó</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($denominaciones as $d): ?>
                                <?php if (($conteo[$d] ?? 0) === 0) { continue; } ?>
                                <tr>
                                    <td class="text-muted small">$<?= number_format($d, 0, ',', '.') ?></td>
                                    <td class="text-center">× <?= (int) $conteo[$d] ?></td>
                                    <td class="text-end"><?= $dinero((float) ($d * $conteo[$d])) ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-7">
        <?php if ($totales['otros_medios'] !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-credit-card me-2"></i>Cobrado por otros medios
                </div>
                <div class="card-body py-2">
                    <div class="row g-2 small">
                        <?php foreach ($totales['otros_medios'] as $nombre => $valor): ?>
                            <div class="col-md-6 d-flex justify-content-between border-bottom py-1">
                                <span class="text-muted"><?= esc($nombre) ?></span>
                                <strong><?= $dinero($valor) ?></strong>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-list-ul me-2"></i>Movimientos
                <span class="badge text-bg-secondary ms-1"><?= count($lista) ?></span>
            </div>
            <?php if ($lista === []): ?>
                <div class="card-body text-muted small">No hubo movimientos en este turno.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Concepto</th>
                                <th class="d-none d-md-table-cell">Medio</th>
                                <th class="text-end">Valor</th>
                                <th class="d-none d-lg-table-cell">Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista as $m): ?>
                                <tr>
                                    <td>
                                        <span class="badge text-bg-<?= $colorTipo[$m['tipo']] ?>"><?= $nombreTipo[$m['tipo']] ?></span>
                                        <?= esc($m['concepto']) ?>
                                        <?php if ($m['referencia']): ?>
                                            <div class="small text-muted font-monospace"><?= esc($m['referencia']) ?></div>
                                        <?php endif ?>
                                    </td>
                                    <td class="small text-muted d-none d-md-table-cell"><?= esc($m['medio_nombre']) ?: '—' ?></td>
                                    <td class="text-end fw-semibold text-<?= $m['tipo'] === 'ingreso' ? 'success' : 'danger' ?>">
                                        <?= $m['tipo'] === 'ingreso' ? '+' : '−' ?><?= $dinero((float) $m['valor']) ?>
                                    </td>
                                    <td class="small text-muted d-none d-lg-table-cell">
                                        <?= date('H:i', strtotime($m['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
