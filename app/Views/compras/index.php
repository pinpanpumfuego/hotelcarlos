<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$pesos  = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');
$colores = ['borrador' => 'secondary', 'enviada' => 'info', 'parcial' => 'warning', 'recibida' => 'success', 'anulada' => 'dark'];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Compras</h1>
        <p class="text-muted mb-0">
            Lo que se pide y lo que llega. <strong>Solo la recepción mueve el
            almacén</strong>: entre pedir y recibir puede pasar cualquier cosa.
        </p>
    </div>
    <a href="<?= site_url('compras/costes') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-graph-up me-1"></i>Costes de cocina
    </a>
</div>

<?= view('partes/errores') ?>

<?php if ($reponer !== []): ?>
    <div class="alert alert-warning d-flex gap-2">
        <i class="bi bi-cart-plus mt-1"></i>
        <div>
            <strong>Hay <?= count($reponer) ?> cosa(s) bajo mínimo.</strong>
            <div class="small mt-1">
                <?php foreach (array_slice($reponer, 0, 8) as $r): ?>
                    <span class="badge text-bg-light border me-1">
                        <?= esc($r['nombre']) ?>
                        <?php if (! empty($r['proveedor'])): ?>
                            · <?= esc($r['proveedor']) ?>
                        <?php endif ?>
                    </span>
                <?php endforeach ?>
                <?php if (count($reponer) > 8): ?>
                    <span class="text-muted">y <?= count($reponer) - 8 ?> más</span>
                <?php endif ?>
            </div>
        </div>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($estados as $clave => $texto): ?>
                                <option value="<?= esc($clave) ?>" <?= ($filtros['estado'] ?? '') === $clave ? 'selected' : '' ?>>
                                    <?= esc($texto) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Proveedor</label>
                        <select name="proveedor_id" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= (string) ($filtros['proveedor_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>>
                                    <?= esc($p['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Desde</label>
                        <input type="date" name="desde" class="form-control form-control-sm" value="<?= esc($filtros['desde'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Hasta</label>
                        <input type="date" name="hasta" class="form-control form-control-sm" value="<?= esc($filtros['hasta'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
                        <a href="<?= site_url('compras') ?>" class="btn btn-outline-secondary btn-sm">×</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($ordenes === []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-receipt fs-2 d-block mb-2 text-muted opacity-50"></i>
                    <p class="text-muted mb-0">Todavía no hay órdenes de compra.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Orden</th>
                                <th>Proveedor</th>
                                <th class="d-none d-md-table-cell">Recibido</th>
                                <th class="text-end">Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ordenes as $o): ?>
                                <tr>
                                    <td>
                                        <a href="<?= site_url('compras/ver/' . $o['id']) ?>" class="text-decoration-none fw-semibold font-monospace">
                                            <?= esc($o['numero']) ?>
                                        </a>
                                        <div class="small text-muted"><?= date('d/m/Y', strtotime($o['fecha'])) ?></div>
                                    </td>
                                    <td>
                                        <?= esc($o['proveedor']) ?>
                                        <div class="small text-muted"><?= esc($o['bodega']) ?></div>
                                    </td>
                                    <td class="d-none d-md-table-cell small">
                                        <?php if ((float) $o['pedido'] > 0): ?>
                                            <?php $pct = min(100, (int) round($o['recibido'] / $o['pedido'] * 100)); ?>
                                            <div class="progress" style="height: 6px; width: 90px;">
                                                <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : 'warning' ?>" style="width: <?= $pct ?>%"></div>
                                            </div>
                                            <span class="text-muted"><?= $pct ?>%</span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif ?>
                                    </td>
                                    <td class="text-end"><?= $pesos((float) $o['total']) ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= $colores[$o['estado']] ?? 'secondary' ?>">
                                            <?= esc($estados[$o['estado']] ?? $o['estado']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-lg me-2"></i>Nueva orden</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('compras/crear') ?>">
                    <?= csrf_field() ?>
                    <label class="form-label small">Proveedor</label>
                    <select name="proveedor_id" class="form-select form-select-sm mb-2" required>
                        <option value="">— elige —</option>
                        <?php foreach ($proveedores as $p): ?>
                            <?php if (! $p['activo']) { continue; } ?>
                            <option value="<?= (int) $p['id'] ?>"><?= esc($p['nombre']) ?></option>
                        <?php endforeach ?>
                    </select>

                    <label class="form-label small">Entra en la bodega</label>
                    <select name="bodega_id" class="form-select form-select-sm mb-2" required>
                        <?php foreach ($bodegas as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"><?= esc($b['nombre']) ?></option>
                        <?php endforeach ?>
                    </select>

                    <label class="form-label small">Se espera para</label>
                    <input type="date" name="fecha_esperada" class="form-control form-control-sm mb-3">

                    <div class="d-grid">
                        <button class="btn btn-primary btn-sm">Crear orden</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-truck me-2"></i>Proveedores</span>
                <form method="post" action="<?= site_url('compras/proveedores/migrar') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-link text-decoration-none p-0"
                            title="Crea fichas a partir de los proveedores escritos a mano en los insumos">
                        Importar de los insumos
                    </button>
                </form>
            </div>
            <div class="card-body py-2">
                <?php foreach ($proveedores as $p): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom small">
                        <div>
                            <span class="fw-semibold <?= $p['activo'] ? '' : 'text-muted' ?>"><?= esc($p['nombre']) ?></span>
                            <?php if (! empty($p['telefono'])): ?>
                                <div class="text-muted"><?= esc($p['telefono']) ?></div>
                            <?php endif ?>
                        </div>
                        <div class="text-end text-muted">
                            <?= (int) $p['insumos'] ?> insumo(s)
                            <?php if ((int) $p['dias_entrega'] > 0): ?>
                                <div><?= (int) $p['dias_entrega'] ?> día(s)</div>
                            <?php endif ?>
                        </div>
                    </div>
                <?php endforeach ?>

                <form method="post" action="<?= site_url('compras/proveedores') ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-12">
                            <input type="text" name="nombre" class="form-control form-control-sm" required placeholder="Nombre del proveedor">
                        </div>
                        <div class="col-6">
                            <input type="text" name="telefono" class="form-control form-control-sm" placeholder="Teléfono">
                        </div>
                        <div class="col-6">
                            <input type="number" name="dias_entrega" class="form-control form-control-sm" min="0" placeholder="Días">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-outline-primary btn-sm">Añadir proveedor</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
