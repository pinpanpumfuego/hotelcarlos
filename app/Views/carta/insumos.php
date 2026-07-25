<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">Insumos y costes</h1>
    <a href="<?= site_url('carta') ?>" class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>Ir a la carta</a>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Da de alta aquí la materia prima con su <strong>coste por unidad</strong> (por gramo, mililitro o unidad).
    Después, en la ficha de cada plato, indicas cuánto lleva de cada insumo y el sistema calcula el coste del plato,
    el margen y el <em>food cost</em>. Si cambia el precio de un insumo, todos los platos se recalculan solos.
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Insumo</th>
                    <th>Unidad</th>
                    <th class="text-end">Coste por unidad</th>
                    <th class="d-none d-md-table-cell">Proveedor</th>
                    <th>Usado en</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($insumos)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-box-seam fs-3 d-block mb-2 opacity-50"></i>
                        Aún no hay insumos. Añade el primero abajo.
                    </td></tr>
                <?php endif ?>
                <?php foreach ($insumos as $i): ?>
                    <tr class="<?= $i['activo'] ? '' : 'opacity-50' ?>">
                        <td class="fw-semibold"><?= esc($i['nombre']) ?></td>
                        <td><?= esc($unidades[$i['unidad']] ?? $i['unidad']) ?></td>
                        <td class="text-end">$<?= number_format((float) $i['costo_unitario'], 2, ',', '.') ?></td>
                        <td class="text-muted d-none d-md-table-cell"><?= esc($i['proveedor'] ?? '—') ?></td>
                        <td>
                            <?php if ($i['platos'] > 0): ?>
                                <span class="badge text-bg-light border"><?= $i['platos'] ?> plato<?= $i['platos'] > 1 ? 's' : '' ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= $i['activo'] ? 'success' : 'secondary' ?>"><?= $i['activo'] ? 'Activo' : 'Inactivo' ?></span>
                        </td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#ed<?= $i['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" action="<?= site_url('insumos/eliminar/' . $i['id']) ?>" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar el insumo <?= esc($i['nombre'], 'js') ?>?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <tr class="collapse" id="ed<?= $i['id'] ?>">
                        <td colspan="7" class="bg-light">
                            <form method="post" action="<?= site_url('insumos/actualizar/' . $i['id']) ?>" class="row g-2 align-items-end">
                                <?= csrf_field() ?>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Nombre</label>
                                    <input type="text" name="nombre" class="form-control form-control-sm" value="<?= esc($i['nombre']) ?>" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Unidad</label>
                                    <select name="unidad" class="form-select form-select-sm">
                                        <?php foreach ($unidades as $clave => $etiqueta): ?>
                                            <option value="<?= $clave ?>" <?= $i['unidad'] === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-1">Coste por unidad</label>
                                    <input type="number" name="costo_unitario" class="form-control form-control-sm" step="0.0001" min="0"
                                           value="<?= esc($i['costo_unitario']) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Proveedor</label>
                                    <input type="text" name="proveedor" class="form-control form-control-sm" value="<?= esc($i['proveedor'] ?? '') ?>">
                                </div>
                                <div class="col-md-2 d-flex gap-2 align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="act<?= $i['id'] ?>" <?= $i['activo'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="act<?= $i['id'] ?>">Activo</label>
                                    </div>
                                    <button class="btn btn-sm btn-primary">Guardar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-2"></i>Nuevo insumo</div>
    <div class="card-body">
        <form method="post" action="<?= site_url('insumos/guardar') ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label small mb-1">Nombre</label>
                <input type="text" name="nombre" class="form-control" placeholder="Trucha, harina, queso mozzarella…" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Unidad de medida</label>
                <select name="unidad" class="form-select">
                    <?php foreach ($unidades as $clave => $etiqueta): ?>
                        <option value="<?= $clave ?>"><?= $etiqueta ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Coste por unidad</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" name="costo_unitario" class="form-control" step="0.0001" min="0" required placeholder="0,00">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Proveedor (opcional)</label>
                <input type="text" name="proveedor" class="form-control">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
            </div>
        </form>
        <p class="small text-muted mt-3 mb-0">
            <i class="bi bi-lightbulb me-1"></i>
            Consejo: si compras la trucha a $28.000 el kilo y la mides en gramos, el coste por gramo es 28.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
