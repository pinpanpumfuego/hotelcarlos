<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.'); ?>

<div class="mb-4">
    <a href="<?= site_url('almacen') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Almacén
    </a>
    <h1 class="h3 mb-0 mt-1"><?= esc($bodega['nombre']) ?></h1>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small mb-1">Buscar</label>
                        <input type="text" name="buscar" class="form-control form-control-sm"
                               value="<?= esc($filtros['buscar']) ?>" placeholder="Nombre del insumo">
                    </div>
                    <div class="col-md-3 d-flex align-items-center gap-3 pt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="bajos" value="1" id="bajos"
                                   <?= $filtros['solo_bajos'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="bajos">Bajo mínimo</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="negativos" value="1" id="neg"
                                   <?= $filtros['solo_negativos'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="neg">Negativos</label>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
                        <a href="<?= site_url('almacen/bodega/' . $bodega['id']) ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($existencias === []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inboxes fs-2 d-block mb-2 text-muted opacity-50"></i>
                    <p class="text-muted mb-0">
                        Esta bodega está vacía. Registra una entrada para empezar.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= site_url('almacen/conteo') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="bodega_id" value="<?= (int) $bodega['id'] ?>">

                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Insumo</th>
                                    <th class="text-end">Hay</th>
                                    <th class="text-end d-none d-md-table-cell">Coste</th>
                                    <th class="text-end d-none d-lg-table-cell">Valor</th>
                                    <th style="width: 8rem;">Contado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existencias as $e): ?>
                                    <?php
                                    $cantidad = (float) $e['cantidad'];
                                    $minimo   = (float) $e['stock_minimo'];
                                    $bajo     = $minimo > 0 && $cantidad <= $minimo;
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?= site_url('almacen/insumo/' . $e['insumo_id']) ?>" class="text-decoration-none fw-semibold">
                                                <?= esc($e['nombre']) ?>
                                            </a>
                                            <?php if (! empty($e['controla_lote'])): ?>
                                                <i class="bi bi-upc-scan text-muted ms-1" title="Lleva control de lote"></i>
                                            <?php endif ?>
                                            <?php if ($minimo > 0): ?>
                                                <div class="small text-muted">mín. <?= number_format($minimo, 2, ',', '.') ?></div>
                                            <?php endif ?>
                                        </td>
                                        <td class="text-end fw-semibold <?= $cantidad < 0 ? 'text-danger' : ($bajo ? 'text-warning-emphasis' : '') ?>">
                                            <?= number_format($cantidad, 2, ',', '.') ?>
                                            <span class="text-muted fw-normal small"><?= esc($e['unidad']) ?></span>
                                        </td>
                                        <td class="text-end d-none d-md-table-cell small"><?= $pesos((float) $e['costo_medio']) ?></td>
                                        <td class="text-end d-none d-lg-table-cell small"><?= $pesos($cantidad * (float) $e['costo_medio']) ?></td>
                                        <td>
                                            <?php // Vacío significa «no lo he contado», que no es cero. ?>
                                            <input type="number" step="0.001" class="form-control form-control-sm"
                                                   name="contado[<?= (int) $e['insumo_id'] ?>]" placeholder="—">
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            Escribe lo que has contado de verdad. Lo que dejes en blanco no se toca.
                        </span>
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-clipboard-check me-1"></i>Ajustar al conteo
                        </button>
                    </div>
                </div>
            </form>
        <?php endif ?>
    </div>

    <div class="col-lg-4">
        <?php
        $acciones = [
            ['entrada', 'Entrada de mercancía', 'bi-box-arrow-in-down', 'success'],
            ['salida', 'Merma o consumo interno', 'bi-box-arrow-up', 'warning'],
            ['traslado', 'Traslado a otra bodega', 'bi-arrow-left-right', 'secondary'],
        ];
        ?>
        <div class="accordion" id="acciones">
            <?php foreach ($acciones as $i => [$clave, $titulo, $icono, $color]): ?>
                <div class="accordion-item border-0 shadow-sm mb-2">
                    <h2 class="accordion-header">
                        <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?> fw-semibold"
                                type="button" data-bs-toggle="collapse" data-bs-target="#acc<?= $clave ?>">
                            <i class="bi <?= $icono ?> me-2 text-<?= $color ?>"></i><?= esc($titulo) ?>
                        </button>
                    </h2>
                    <div id="acc<?= $clave ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                         data-bs-parent="#acciones">
                        <div class="accordion-body">
                            <form method="post" action="<?= site_url('almacen/' . $clave) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="bodega_id" value="<?= (int) $bodega['id'] ?>">

                                <label class="form-label small">Insumo</label>
                                <select name="insumo_id" class="form-select form-select-sm mb-2" required>
                                    <option value="">— elige —</option>
                                    <?php foreach ($insumos as $ins): ?>
                                        <option value="<?= (int) $ins['id'] ?>">
                                            <?= esc($ins['nombre']) ?> (<?= esc($ins['unidad']) ?>)
                                        </option>
                                    <?php endforeach ?>
                                </select>

                                <label class="form-label small">Cantidad</label>
                                <input type="number" step="0.001" min="0.001" name="cantidad"
                                       class="form-control form-control-sm mb-2" required>

                                <?php if ($clave === 'entrada'): ?>
                                    <label class="form-label small">Coste por unidad</label>
                                    <input type="number" step="0.01" min="0" name="costo_unitario"
                                           class="form-control form-control-sm mb-2" required>

                                    <label class="form-label small">Proveedor</label>
                                    <select name="proveedor_id" class="form-select form-select-sm mb-2">
                                        <option value="">— sin especificar —</option>
                                        <?php foreach ($proveedores as $p): ?>
                                            <option value="<?= (int) $p['id'] ?>"><?= esc($p['nombre']) ?></option>
                                        <?php endforeach ?>
                                    </select>

                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label class="form-label small">Lote</label>
                                            <input type="text" name="lote" class="form-control form-control-sm" maxlength="60">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small">Caduca</label>
                                            <input type="date" name="caduca_el" class="form-control form-control-sm">
                                        </div>
                                    </div>
                                    <div class="form-text mb-2">
                                        El lote solo hace falta en lo que caduca. Para la sal, déjalo en blanco.
                                    </div>
                                <?php endif ?>

                                <?php if ($clave === 'salida'): ?>
                                    <label class="form-label small">Por qué sale</label>
                                    <select name="tipo" class="form-select form-select-sm mb-2" required>
                                        <option value="merma">Merma (se echó a perder)</option>
                                        <option value="consumo_interno">Consumo interno (personal)</option>
                                        <option value="cortesia">Cortesía a un huésped</option>
                                    </select>
                                <?php endif ?>

                                <?php if ($clave === 'traslado'): ?>
                                    <label class="form-label small">A qué bodega</label>
                                    <select name="destino_id" class="form-select form-select-sm mb-2" required>
                                        <?php foreach ($bodegas as $b): ?>
                                            <?php if ((int) $b['id'] === (int) $bodega['id']) { continue; } ?>
                                            <option value="<?= (int) $b['id'] ?>"><?= esc($b['nombre']) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                <?php endif ?>

                                <label class="form-label small">
                                    Motivo <?= $clave === 'salida' ? '<span class="text-danger">*</span>' : '' ?>
                                </label>
                                <input type="text" name="motivo" class="form-control form-control-sm mb-3"
                                       maxlength="200" <?= $clave === 'salida' ? 'required' : '' ?>>

                                <div class="d-grid">
                                    <button class="btn btn-<?= $color ?> btn-sm">Registrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
