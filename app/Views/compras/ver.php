<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$pesos   = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');
$colores = ['borrador' => 'secondary', 'enviada' => 'info', 'parcial' => 'warning', 'recibida' => 'success', 'anulada' => 'dark'];
$puedeRecibir = in_array($orden['estado'], ['enviada', 'parcial'], true);
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('compras') ?>" class="text-decoration-none small text-muted">
            <i class="bi bi-arrow-left me-1"></i>Compras
        </a>
        <h1 class="h3 mb-0 mt-1 font-monospace"><?= esc($orden['numero']) ?></h1>
        <p class="text-muted mb-0 mt-1">
            <?= esc($orden['proveedor']) ?> · entra en <?= esc($orden['bodega']) ?>
            <?php if (! empty($orden['fecha_esperada'])): ?>
                · se espera el <?= date('d/m/Y', strtotime($orden['fecha_esperada'])) ?>
            <?php endif ?>
        </p>
    </div>
    <span class="badge text-bg-<?= $colores[$orden['estado']] ?? 'secondary' ?> fs-6">
        <?= esc($estados[$orden['estado']] ?? $orden['estado']) ?>
    </span>
</div>

<?= view('partes/errores') ?>

<?php if ($puedeRecibir): ?>
    <form method="post" action="<?= site_url('compras/recibir/' . $orden['id']) ?>">
        <?= csrf_field() ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-box-arrow-in-down me-2 text-success"></i>Registrar lo que ha llegado
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Insumo</th>
                            <th class="text-end">Pedido</th>
                            <th class="text-end">Ya recibido</th>
                            <th style="width: 7rem;">Llega ahora</th>
                            <th style="width: 7rem;" class="d-none d-lg-table-cell">Coste</th>
                            <th style="width: 12rem;" class="d-none d-xl-table-cell">Lote y caducidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lineas as $l): ?>
                            <?php
                            $pedida   = (float) $l['cantidad_pedida'];
                            $recibida = (float) $l['cantidad_recibida'];
                            $falta    = max(0, $pedida - $recibida);
                            ?>
                            <tr class="<?= $falta <= 0 ? 'opacity-50' : '' ?>">
                                <td>
                                    <span class="fw-semibold"><?= esc($l['insumo']) ?></span>
                                    <span class="text-muted small">(<?= esc($l['unidad']) ?>)</span>
                                </td>
                                <td class="text-end"><?= number_format($pedida, 2, ',', '.') ?></td>
                                <td class="text-end <?= $recibida > 0 && $recibida < $pedida ? 'text-warning-emphasis' : '' ?>">
                                    <?= number_format($recibida, 2, ',', '.') ?>
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                           name="cantidad[<?= (int) $l['id'] ?>]"
                                           placeholder="<?= $falta > 0 ? number_format($falta, 2, ',', '.') : '—' ?>">
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="costo[<?= (int) $l['id'] ?>]"
                                           placeholder="<?= number_format((float) $l['costo_unitario'], 0, ',', '.') ?>">
                                </td>
                                <td class="d-none d-xl-table-cell">
                                    <?php if (! empty($l['controla_lote'])): ?>
                                        <input type="text" class="form-control form-control-sm mb-1"
                                               name="lote[<?= (int) $l['id'] ?>]" placeholder="Lote" maxlength="60">
                                        <input type="date" class="form-control form-control-sm"
                                               name="caduca[<?= (int) $l['id'] ?>]">
                                    <?php else: ?>
                                        <span class="text-muted small">no lleva lote</span>
                                    <?php endif ?>
                                </td>
                            </tr>
                            <?php if ($falta > 0): ?>
                                <tr class="<?= $falta <= 0 ? 'opacity-50' : '' ?>">
                                    <td colspan="6" class="pt-0 border-0">
                                        <input type="text" class="form-control form-control-sm"
                                               name="motivo[<?= (int) $l['id'] ?>]" maxlength="200"
                                               placeholder="Si no llega todo, por qué (se lo dirás al proveedor)">
                                    </td>
                                </tr>
                            <?php endif ?>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Número de factura o remisión</label>
                        <input type="text" name="documento" class="form-control form-control-sm" maxlength="60"
                               value="<?= esc($orden['documento'] ?? '') ?>">
                    </div>
                    <div class="col-md-7 text-md-end">
                        <span class="small text-muted me-2">
                            Deja en blanco lo que no haya llegado. Se puede recibir en varias veces.
                        </span>
                        <button class="btn btn-success btn-sm">
                            <i class="bi bi-check-lg me-1"></i>Meter en el almacén
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Lo pedido</div>
            <?php if ($lineas === []): ?>
                <div class="card-body text-center py-4">
                    <p class="text-muted mb-0">La orden está vacía.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Insumo</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Coste</th>
                                <th class="text-end">Importe</th>
                                <?php if ($editable): ?><th></th><?php endif ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineas as $l): ?>
                                <tr>
                                    <td>
                                        <?= esc($l['insumo']) ?>
                                        <?php if (! empty($l['motivo_diferencia'])): ?>
                                            <div class="small text-warning-emphasis">
                                                <i class="bi bi-exclamation-triangle me-1"></i><?= esc($l['motivo_diferencia']) ?>
                                            </div>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end">
                                        <?= number_format((float) $l['cantidad_pedida'], 2, ',', '.') ?>
                                        <span class="text-muted small"><?= esc($l['unidad']) ?></span>
                                    </td>
                                    <td class="text-end"><?= $pesos((float) $l['costo_unitario']) ?></td>
                                    <td class="text-end"><?= $pesos((float) $l['cantidad_pedida'] * (float) $l['costo_unitario']) ?></td>
                                    <?php if ($editable): ?>
                                        <td class="text-end">
                                            <form method="post" action="<?= site_url('compras/linea/quitar/' . $orden['id'] . '/' . $l['id']) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        </td>
                                    <?php endif ?>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="3">Total</th>
                                <th class="text-end"><?= $pesos((float) $orden['total']) ?></th>
                                <?php if ($editable): ?><th></th><?php endif ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if ($editable): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold">Añadir a la orden</div>
                <div class="card-body">
                    <form method="post" action="<?= site_url('compras/linea/' . $orden['id']) ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small">Insumo</label>
                        <select name="insumo_id" class="form-select form-select-sm mb-2" required>
                            <option value="">— elige —</option>
                            <?php foreach ($insumos as $i): ?>
                                <option value="<?= (int) $i['id'] ?>" data-costo="<?= (float) $i['costo_unitario'] ?>">
                                    <?= esc($i['nombre']) ?> (<?= esc($i['unidad']) ?>)
                                </option>
                            <?php endforeach ?>
                        </select>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small">Cantidad</label>
                                <input type="number" step="0.001" min="0.001" name="cantidad" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Coste unidad</label>
                                <input type="number" step="0.01" min="0" name="costo_unitario" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button class="btn btn-primary btn-sm">Añadir</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="d-grid gap-2">
                <form method="post" action="<?= site_url('compras/enviar/' . $orden['id']) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-success w-100">
                        <i class="bi bi-send me-1"></i>Dar por enviada
                    </button>
                </form>
                <div class="form-text">
                    A partir de aquí no se podrá cambiar lo pedido: si se pudiera,
                    comparar lo pedido con lo recibido dejaría de significar nada.
                </div>
            </div>
        <?php endif ?>

        <?php if ($orden['estado'] !== 'recibida' && $orden['estado'] !== 'anulada'): ?>
            <form method="post" action="<?= site_url('compras/anular/' . $orden['id']) ?>" class="mt-3"
                  onsubmit="return confirm('¿Anular esta orden?');">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm w-100">Anular la orden</button>
            </form>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
