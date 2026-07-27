<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.'); ?>

<div class="mb-4">
    <a href="<?= site_url('almacen') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Almacén
    </a>
    <h1 class="h3 mb-0 mt-1"><?= esc($insumo['nombre']) ?></h1>
    <p class="text-muted mb-0 mt-1">
        Se mide en <?= esc($insumo['unidad']) ?>
        <?php if ((float) $insumo['stock_minimo'] > 0): ?>
            · mínimo <?= number_format((float) $insumo['stock_minimo'], 2, ',', '.') ?>
        <?php endif ?>
        <?php if (! empty($insumo['controla_lote'])): ?>
            · lleva control de lote, avisa <?= (int) $insumo['dias_aviso_caducidad'] ?> día(s) antes
        <?php endif ?>
    </p>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Existencias</div>
            <div class="card-body py-2">
                <?php if ($existencias === []): ?>
                    <p class="small text-muted mb-0">Todavía no hay existencias de este insumo.</p>
                <?php else: ?>
                    <?php foreach ($existencias as $e): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-semibold"><?= esc($e['bodega']) ?></div>
                                <div class="small text-muted"><?= $pesos((float) $e['costo_medio']) ?> por <?= esc($insumo['unidad']) ?></div>
                            </div>
                            <div class="text-end">
                                <span class="fw-semibold <?= (float) $e['cantidad'] < 0 ? 'text-danger' : '' ?>">
                                    <?= number_format((float) $e['cantidad'], 3, ',', '.') ?>
                                </span>
                                <form method="post" action="<?= site_url('almacen/recalcular/' . $insumo['id'] . '/' . $e['bodega_id']) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-link text-decoration-none p-0 ms-1"
                                            title="Recalcular desde los movimientos">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>

        <?php if ($lotes !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Lotes vivos</div>
                <div class="card-body py-2">
                    <p class="small text-muted mb-2">
                        El más antiguo primero: es el que hay que gastar antes.
                    </p>
                    <?php foreach ($lotes as $l): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom small">
                            <div>
                                <span class="fw-semibold"><?= esc($l['codigo']) ?></span>
                                <div class="text-muted">
                                    <?= esc($l['bodega']) ?>
                                    <?php if (! empty($l['proveedor'])): ?>
                                        · <?= esc($l['proveedor']) ?>
                                    <?php endif ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div><?= number_format((float) $l['cantidad'], 2, ',', '.') ?></div>
                                <?php if (! empty($l['caduca_el'])): ?>
                                    <?php $dias = (int) ((strtotime($l['caduca_el']) - strtotime(date('Y-m-d'))) / 86400); ?>
                                    <span class="badge text-bg-<?= $dias < 0 ? 'danger' : ($dias <= (int) $insumo['dias_aviso_caducidad'] ? 'warning' : 'light border') ?>">
                                        <?= date('d/m/Y', strtotime($l['caduca_el'])) ?>
                                    </span>
                                <?php endif ?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-clock-history me-2"></i>Todo lo que ha pasado
            </div>
            <?php if ($movimientos === []): ?>
                <div class="card-body text-center py-5">
                    <p class="text-muted mb-0">Todavía no hay movimientos.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Cuándo</th>
                                <th>Qué</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end d-none d-md-table-cell">Saldo</th>
                                <th class="d-none d-lg-table-cell">Quién</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $m): ?>
                                <?php $cantidad = (float) $m['cantidad']; ?>
                                <tr>
                                    <td class="text-nowrap text-muted">
                                        <?= date('d/m/Y', strtotime($m['created_at'])) ?>
                                        <span class="d-block"><?= date('H:i', strtotime($m['created_at'])) ?></span>
                                    </td>
                                    <td>
                                        <?= esc($tipos[$m['tipo']] ?? $m['tipo']) ?>
                                        <div class="text-muted">
                                            <?= esc($m['bodega']) ?>
                                            <?php if (! empty($m['lote'])): ?>
                                                · lote <?= esc($m['lote']) ?>
                                            <?php endif ?>
                                        </div>
                                        <?php if (! empty($m['motivo'])): ?>
                                            <div class="text-muted fst-italic"><?= esc($m['motivo']) ?></div>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end fw-semibold text-nowrap <?= $cantidad > 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $cantidad > 0 ? '+' : '' ?><?= number_format($cantidad, 3, ',', '.') ?>
                                    </td>
                                    <td class="text-end d-none d-md-table-cell"><?= number_format((float) $m['saldo'], 3, ',', '.') ?></td>
                                    <td class="d-none d-lg-table-cell text-muted"><?= esc($m['usuario_nombre'] ?? '—') ?></td>
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
