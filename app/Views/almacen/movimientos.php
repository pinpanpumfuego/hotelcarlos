<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="mb-4">
    <a href="<?= site_url('almacen') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Almacén
    </a>
    <h1 class="h3 mb-1 mt-1">Movimientos de almacén</h1>
    <p class="text-muted mb-0">
        Todo lo que entró, salió o se movió, con quién y por qué.
        <strong>No se puede editar ni borrar</strong>: un error se corrige con un
        movimiento contrario, como en contabilidad.
    </p>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= esc($filtros['desde']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= esc($filtros['hasta'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Bodega</label>
                <select name="bodega_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($bodegas as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= (string) ($filtros['bodega_id'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>>
                            <?= esc($b['nombre']) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $clave => $texto): ?>
                        <option value="<?= esc($clave) ?>" <?= ($filtros['tipo'] ?? '') === $clave ? 'selected' : '' ?>>
                            <?= esc($texto) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1">Filtrar</button>
                <a href="<?= site_url('almacen/movimientos') ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if ($movimientos === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-clipboard fs-2 d-block mb-2 text-muted opacity-50"></i>
            <p class="text-muted mb-0">No hay movimientos con esos filtros.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Cuándo</th>
                        <th>Insumo</th>
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
                                <a href="<?= site_url('almacen/insumo/' . $m['insumo_id']) ?>" class="text-decoration-none fw-semibold">
                                    <?= esc($m['insumo']) ?>
                                </a>
                                <div class="text-muted"><?= esc($m['bodega']) ?></div>
                            </td>
                            <td>
                                <?= esc($tipos[$m['tipo']] ?? $m['tipo']) ?>
                                <?php if (! empty($m['motivo'])): ?>
                                    <div class="text-muted fst-italic"><?= esc($m['motivo']) ?></div>
                                <?php endif ?>
                                <?php if (! empty($m['lote'])): ?>
                                    <div class="text-muted">lote <?= esc($m['lote']) ?></div>
                                <?php endif ?>
                            </td>
                            <td class="text-end fw-semibold text-nowrap <?= $cantidad > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $cantidad > 0 ? '+' : '' ?><?= number_format($cantidad, 3, ',', '.') ?>
                                <span class="text-muted fw-normal"><?= esc($m['unidad']) ?></span>
                            </td>
                            <td class="text-end d-none d-md-table-cell"><?= number_format((float) $m['saldo'], 3, ',', '.') ?></td>
                            <td class="d-none d-lg-table-cell text-muted"><?= esc($m['usuario_nombre'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3"><?= $paginador->links() ?></div>
<?php endif ?>

<?= $this->endSection() ?>
