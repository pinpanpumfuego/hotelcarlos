<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');
$total = 0.0;
foreach ($valoracion as $v) {
    $total += (float) $v['valor'];
}
$tiposFuga = \App\Models\MovimientoStockModel::TIPOS;
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Almacén</h1>
        <p class="text-muted mb-0">
            Lo que hay, lo que falta y lo que se va. El stock se descuenta solo
            al enviar los platos a cocina.
        </p>
    </div>
    <a href="<?= site_url('almacen/movimientos') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-clock-history me-1"></i>Ver movimientos
    </a>
</div>

<?= view('partes/errores') ?>

<?php // Lo que exige actuar va primero: si está enterrado, no se mira. ?>
<?php if ($negativos !== []): ?>
    <div class="card border-0 shadow-sm mb-4 border-start border-danger border-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-exclamation-octagon me-2 text-danger"></i>Existencias en negativo
        </div>
        <div class="card-body py-2">
            <p class="small text-muted mb-2">
                Se ha vendido algo que el sistema no sabía que había. No es un fallo
                del programa: casi siempre falta registrar una compra. Se cuadra con un conteo.
            </p>
            <?php foreach ($negativos as $n): ?>
                <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span>
                        <a href="<?= site_url('almacen/insumo/' . $n['insumo_id']) ?>" class="text-decoration-none">
                            <?= esc($n['nombre']) ?>
                        </a>
                        <span class="text-muted">· <?= esc($n['bodega']) ?></span>
                    </span>
                    <span class="text-danger fw-semibold">
                        <?= number_format((float) $n['cantidad'], 2, ',', '.') ?> <?= esc($n['unidad']) ?>
                    </span>
                </div>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-box-seam me-2 text-success"></i>Bodegas
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Bodega</th>
                            <th class="text-end">Referencias</th>
                            <th class="text-end">Valor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bodegas as $b): ?>
                            <tr class="<?= $b['activa'] ? '' : 'opacity-50' ?>">
                                <td>
                                    <span class="fw-semibold"><?= esc($b['nombre']) ?></span>
                                    <?php if ($b['por_defecto']): ?>
                                        <span class="badge text-bg-primary ms-1">Por defecto</span>
                                    <?php endif ?>
                                    <?php if (! $b['activa']): ?>
                                        <span class="badge text-bg-secondary ms-1">Inactiva</span>
                                    <?php endif ?>
                                    <?php if ((int) $b['negativos'] > 0): ?>
                                        <span class="badge text-bg-danger ms-1"><?= (int) $b['negativos'] ?> en negativo</span>
                                    <?php endif ?>
                                </td>
                                <td class="text-end"><?= (int) $b['referencias'] ?></td>
                                <td class="text-end"><?= $pesos((float) $b['valor']) ?></td>
                                <td class="text-end">
                                    <a href="<?= site_url('almacen/bodega/' . $b['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        Abrir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2">Valor del almacén</th>
                            <th class="text-end"><?= $pesos($total) ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php if ($bajoMinimo !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-cart-plus me-2 text-warning"></i>Hay que reponer
                </div>
                <div class="card-body py-2">
                    <p class="small text-muted mb-2">
                        Solo aparece lo que tiene mínimo puesto. Sin mínimo no hay forma de
                        saber si dos kilos es mucho o poco, y avisar de todo es no avisar de nada.
                    </p>
                    <?php foreach ($bajoMinimo as $b): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <a href="<?= site_url('almacen/insumo/' . $b['insumo_id']) ?>" class="text-decoration-none fw-semibold">
                                    <?= esc($b['nombre']) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= esc($b['bodega']) ?>
                                    <?php if (! empty($b['proveedor'])): ?>
                                        · <?= esc($b['proveedor']) ?>
                                        <?php if ((int) $b['dias_entrega'] > 0): ?>
                                            (tarda <?= (int) $b['dias_entrega'] ?> día(s))
                                        <?php endif ?>
                                    <?php endif ?>
                                </div>
                            </div>
                            <div class="text-end small">
                                <span class="fw-semibold <?= (float) $b['cantidad'] <= 0 ? 'text-danger' : 'text-warning-emphasis' ?>">
                                    <?= number_format((float) $b['cantidad'], 2, ',', '.') ?>
                                </span>
                                <span class="text-muted">
                                    / <?= number_format((float) $b['stock_minimo'], 2, ',', '.') ?> <?= esc($b['unidad']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-5">
        <?php if ($porCaducar !== []): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-hourglass-split me-2 text-danger"></i>Caduca pronto
                </div>
                <div class="card-body py-2">
                    <?php foreach ($porCaducar as $l): ?>
                        <?php $dias = (int) $l['dias']; ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div class="small">
                                <span class="fw-semibold"><?= esc($l['insumo']) ?></span>
                                <div class="text-muted">
                                    Lote <?= esc($l['codigo']) ?> · <?= esc($l['bodega']) ?>
                                    · <?= number_format((float) $l['cantidad'], 2, ',', '.') ?> <?= esc($l['unidad']) ?>
                                </div>
                            </div>
                            <span class="badge text-bg-<?= $dias < 0 ? 'danger' : ($dias <= 1 ? 'warning' : 'light border') ?>">
                                <?= $dias < 0 ? 'Caducado' : ($dias === 0 ? 'Hoy' : 'En ' . $dias . ' día(s)') ?>
                            </span>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-graph-down-arrow me-2"></i>Lo que se va este mes
            </div>
            <div class="card-body py-2">
                <?php if ($fugas === []): ?>
                    <p class="small text-muted mb-0">Todavía no hay mermas ni cortesías este mes.</p>
                <?php else: ?>
                    <p class="small text-muted mb-2">
                        Lo que sale del almacén sin venderse. No es dinero perdido del todo
                        —una cortesía puede salvar una reseña— pero conviene saber cuánto es.
                    </p>
                    <?php $totalFugas = 0; ?>
                    <?php foreach ($fugas as $f): ?>
                        <?php $totalFugas += (float) $f['valor']; ?>
                        <div class="d-flex justify-content-between py-1 border-bottom small">
                            <span><?= esc($tiposFuga[$f['tipo']] ?? $f['tipo']) ?>
                                <span class="text-muted">(<?= (int) $f['veces'] ?>)</span>
                            </span>
                            <span class="fw-semibold"><?= $pesos((float) $f['valor']) ?></span>
                        </div>
                    <?php endforeach ?>
                    <div class="d-flex justify-content-between pt-2 fw-semibold">
                        <span>Total</span>
                        <span><?= $pesos($totalFugas) ?></span>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
