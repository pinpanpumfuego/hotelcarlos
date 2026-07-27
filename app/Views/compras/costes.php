<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');
$res   = $comparacion['resumen'];
?>

<div class="mb-4">
    <a href="<?= site_url('compras') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Compras
    </a>
    <h1 class="h3 mb-1 mt-1">Costes de cocina</h1>
    <p class="text-muted mb-0">
        Lo que las recetas dicen que debería haberse gastado, frente a lo que salió
        del almacén de verdad.
    </p>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= esc($desde) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= esc($hasta) ?>">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary btn-sm">Ver</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="dato text-muted small text-uppercase">Debería haberse gastado</div>
                <div class="fs-4 fw-semibold"><?= $pesos((float) $res['teorico']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="dato text-muted small text-uppercase">Se gastó de verdad</div>
                <div class="fs-4 fw-semibold"><?= $pesos((float) $res['real']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 <?= abs((float) $res['desviacion']) > 0 ? 'border-start border-4 border-' . ((float) $res['desviacion'] > 0 ? 'danger' : 'success') : '' ?>">
            <div class="card-body">
                <div class="dato text-muted small text-uppercase">Diferencia</div>
                <div class="fs-4 fw-semibold text-<?= (float) $res['desviacion'] > 0 ? 'danger' : 'success' ?>">
                    <?= (float) $res['desviacion'] > 0 ? '+' : '' ?><?= $pesos((float) $res['desviacion']) ?>
                </div>
                <div class="small text-muted">
                    <?= (float) $res['desviacion'] > 0 ? 'Se gastó de más' : 'Se gastó de menos' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Una desviación pequeña es normal y perseguirla cuesta más de lo que ahorra.
    <strong>Lo que hay que mirar son las grandes y las que se repiten mes a mes</strong>:
    ésas suelen ser raciones más generosas de la cuenta, o algo que se va sin apuntar.
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Teórico contra real, insumo a insumo</div>
            <?php if ($comparacion['lineas'] === []): ?>
                <div class="card-body text-center py-4">
                    <p class="text-muted mb-0">
                        No hay datos en este periodo. Hacen falta ventas con receta y
                        movimientos de almacén.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Insumo</th>
                                <th class="text-end">Teórico</th>
                                <th class="text-end">Real</th>
                                <th class="text-end">Desvío</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($comparacion['lineas'], 0, 40) as $l): ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url('almacen/insumo/' . $l['insumo_id']) ?>" class="text-decoration-none">
                                            <?= esc($l['insumo']) ?>
                                        </a>
                                        <span class="text-muted"><?= esc($l['unidad']) ?></span>
                                    </td>
                                    <td class="text-end"><?= number_format($l['teorico'], 2, ',', '.') ?></td>
                                    <td class="text-end"><?= number_format($l['real'], 2, ',', '.') ?></td>
                                    <td class="text-end fw-semibold text-<?= $l['diferencia'] > 0 ? 'danger' : ($l['diferencia'] < 0 ? 'success' : 'muted') ?>">
                                        <?= $l['desviacion'] === null ? '—' : (($l['desviacion'] > 0 ? '+' : '') . $l['desviacion'] . '%') ?>
                                        <div class="fw-normal text-muted"><?= $pesos((float) $l['valor']) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Qué se vende y qué deja</div>
            <?php if ($popularidad === []): ?>
                <div class="card-body text-center py-4">
                    <p class="text-muted mb-0">No hay ventas en este periodo.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Plato</th>
                                <th class="text-end">Uds.</th>
                                <th class="text-end">Ingreso</th>
                                <th class="text-end">Margen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($popularidad, 0, 40) as $p): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold"><?= esc($p['producto']) ?></span>
                                        <div class="text-muted">
                                            <?= esc($p['categoria']) ?> · <?= $p['cuota'] ?>% de lo vendido
                                            <?php if ($p['regaladas'] > 0): ?>
                                                · <?= number_format($p['regaladas'], 0) ?> regalada(s)
                                            <?php endif ?>
                                        </div>
                                    </td>
                                    <td class="text-end"><?= number_format($p['unidades'], 0, ',', '.') ?></td>
                                    <td class="text-end"><?= $pesos((float) $p['ingreso']) ?></td>
                                    <td class="text-end">
                                        <span class="fw-semibold text-<?= $p['margen'] > 0 ? 'success' : 'danger' ?>">
                                            <?= $pesos((float) $p['margen']) ?>
                                        </span>
                                        <?php if ($p['margen_pct'] !== null): ?>
                                            <div class="text-muted"><?= $p['margen_pct'] ?>%</div>
                                        <?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">
                    El plato que se vende mucho y deja poco puede estar llenando la cocina
                    sin llenar la caja. Si no aparece el margen, es que no tiene receta.
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?php if ($diferencias !== []): ?>
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-truck me-2"></i>Diferencias con los proveedores
        </div>
        <div class="card-body py-2">
            <p class="small text-muted mb-2">
                Órdenes cerradas donde no llegó lo que se pidió. Es lo que dice qué
                proveedor cumple.
            </p>
            <?php foreach ($diferencias as $d): ?>
                <div class="d-flex justify-content-between align-items-start py-2 border-bottom small">
                    <div>
                        <span class="fw-semibold"><?= esc($d['proveedor']) ?></span>
                        <span class="text-muted">· <?= esc($d['insumo']) ?> · <?= esc($d['numero']) ?></span>
                        <?php if (! empty($d['motivo_diferencia'])): ?>
                            <div class="text-muted fst-italic"><?= esc($d['motivo_diferencia']) ?></div>
                        <?php endif ?>
                    </div>
                    <div class="text-end text-nowrap">
                        <span class="text-muted"><?= number_format((float) $d['cantidad_pedida'], 2, ',', '.') ?> →</span>
                        <span class="fw-semibold"><?= number_format((float) $d['cantidad_recibida'], 2, ',', '.') ?></span>
                        <span class="badge text-bg-<?= (float) $d['diferencia'] < 0 ? 'warning' : 'info' ?> ms-1">
                            <?= (float) $d['diferencia'] > 0 ? '+' : '' ?><?= number_format((float) $d['diferencia'], 2, ',', '.') ?>
                        </span>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
