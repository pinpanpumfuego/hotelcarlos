<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('preparaciones') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Preparaciones</a>
        <h1 class="h3 mb-0 mt-1"><?= esc($preparacion['nombre']) ?></h1>
    </div>
</div>

<div class="row g-4">
    <!-- ═══ Datos y coste ═══ -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calculator me-2"></i>Coste de la preparación</div>
            <div class="card-body">
                <div class="row text-center g-2 mb-3">
                    <div class="col-6">
                        <div class="text-muted small">Coste de una tanda</div>
                        <div class="fs-4 fw-bold">$<?= number_format($costeTanda, 0, ',', '.') ?></div>
                        <div class="small text-muted">
                            produce <?= rtrim(rtrim(number_format((float) $preparacion['rendimiento'], 3, ',', '.'), '0'), ',') ?> <?= esc($preparacion['unidad']) ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Coste por <?= esc($preparacion['unidad']) ?></div>
                        <div class="fs-4 fw-bold text-success">$<?= number_format($porUnidad, 2, ',', '.') ?></div>
                        <div class="small text-muted">es lo que se usa en los platos</div>
                    </div>
                </div>
                <p class="small text-muted mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Este coste se recalcula solo cada vez que cambia el precio de algún ingrediente.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-sliders me-2"></i>Datos de la preparación</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('preparaciones/actualizar/' . $preparacion['id']) ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?= esc($preparacion['nombre']) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Se mide en</label>
                        <select name="unidad" class="form-select">
                            <?php foreach ($unidades as $clave => $etiqueta): ?>
                                <option value="<?= $clave ?>" <?= $preparacion['unidad'] === $clave ? 'selected' : '' ?>><?= $etiqueta ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Una tanda produce</label>
                        <input type="number" name="rendimiento" class="form-control" step="0.001" min="0.001"
                               value="<?= esc($preparacion['rendimiento']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas de elaboración</label>
                        <input type="text" name="notas" class="form-control" value="<?= esc($preparacion['notas'] ?? '') ?>"
                               placeholder="Cocción, conservación, quién la prepara…">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="activo" value="1" id="act" <?= $preparacion['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="act">Activa</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Guardar y recalcular</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($usos['platos'] !== [] || $usos['preparaciones'] !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Se usa en</div>
                <div class="card-body">
                    <?php if ($usos['platos'] !== []): ?>
                        <p class="mb-2"><span class="text-muted small d-block">Platos</span>
                            <?php foreach ($usos['platos'] as $n): ?>
                                <span class="badge text-bg-light border me-1"><?= esc($n) ?></span>
                            <?php endforeach ?>
                        </p>
                    <?php endif ?>
                    <?php if ($usos['preparaciones'] !== []): ?>
                        <p class="mb-0"><span class="text-muted small d-block">Otras preparaciones</span>
                            <?php foreach ($usos['preparaciones'] as $n): ?>
                                <span class="badge text-bg-light border me-1"><?= esc($n) ?></span>
                            <?php endforeach ?>
                        </p>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>

    <!-- ═══ Ingredientes ═══ -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-2"></i>Ingredientes de una tanda</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Ingrediente</th><th class="text-end">Cantidad</th><th class="text-end">Coste</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($componentes)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">
                                Sin ingredientes todavía. Añádelos abajo.
                            </td></tr>
                        <?php endif ?>
                        <?php foreach ($componentes as $c): ?>
                            <tr>
                                <td>
                                    <?= esc($c['nombre']) ?>
                                    <?php if ((int) $c['es_preparacion'] === 1): ?>
                                        <span class="badge text-bg-info ms-1"><i class="bi bi-stack"></i> preparación</span>
                                    <?php endif ?>
                                </td>
                                <td class="text-end"><?= rtrim(rtrim(number_format($c['cantidad'], 3, ',', '.'), '0'), ',') ?> <?= esc($c['unidad']) ?></td>
                                <td class="text-end">$<?= number_format($c['costo'], 0, ',', '.') ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= site_url('preparaciones/componente/eliminar/' . $c['id']) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                    <?php if (! empty($componentes)): ?>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="2">Total de la tanda</td>
                                <td class="text-end">$<?= number_format($costeTanda, 0, ',', '.') ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    <?php endif ?>
                </table>
            </div>
            <div class="card-body border-top">
                <h2 class="h6 text-muted mb-3">Añadir ingrediente</h2>
                <?php if (empty($candidatos)): ?>
                    <p class="text-muted mb-0">
                        No hay insumos disponibles. <a href="<?= site_url('insumos') ?>">Crea los insumos</a> primero.
                    </p>
                <?php else: ?>
                    <form method="post" action="<?= site_url('preparaciones/componente/' . $preparacion['id']) ?>" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <div class="col-md-7">
                            <label class="form-label small mb-1">Ingrediente</label>
                            <select name="insumo_id" class="form-select" required>
                                <?php foreach ($candidatos as $i): ?>
                                    <option value="<?= esc($i['id']) ?>">
                                        <?= (int) $i['es_preparacion'] === 1 ? '↳ ' : '' ?><?= esc($i['nombre']) ?>
                                        — $<?= number_format((float) $i['costo_unitario'], 2, ',', '.') ?>/<?= esc($i['unidad']) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Cantidad por tanda</label>
                            <input type="number" name="cantidad" class="form-control" step="0.001" min="0.001" required>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Añadir</button>
                        </div>
                    </form>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
