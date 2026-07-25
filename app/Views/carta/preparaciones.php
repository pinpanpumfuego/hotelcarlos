<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">Preparaciones</h1>
    <div class="d-flex gap-2">
        <a href="<?= site_url('insumos') ?>" class="btn btn-outline-secondary"><i class="bi bi-box-seam me-1"></i>Insumos</a>
        <a href="<?= site_url('carta') ?>" class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>Carta</a>
    </div>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Una <strong>preparación</strong> (o subreceta) es algo que se elabora en tandas y luego se usa en varios platos:
    hogao, masa de pizza, salsa de ajillo, caldo base…
    Defines <strong>qué lleva</strong> y <strong>cuánto produce una tanda</strong>, y el sistema calcula su coste por unidad.
    Después, en el escandallo de cada plato, la usas como un ingrediente más.
    Si sube el precio del tomate, el hogao y todos los platos que lo llevan se recalculan solos.
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Preparación</th>
                    <th class="d-none d-md-table-cell">Rendimiento por tanda</th>
                    <th class="text-end">Coste por unidad</th>
                    <th class="d-none d-lg-table-cell">Se usa en</th>
                    <th class="d-none d-md-table-cell">Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($preparaciones)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-stack fs-3 d-block mb-2 opacity-50"></i>
                        Aún no hay preparaciones. Crea la primera abajo.
                    </td></tr>
                <?php endif ?>
                <?php foreach ($preparaciones as $p): ?>
                    <tr class="<?= $p['activo'] ? '' : 'opacity-50' ?>">
                        <td>
                            <a href="<?= site_url('preparaciones/ficha/' . $p['id']) ?>" class="fw-semibold text-decoration-none">
                                <?= esc($p['nombre']) ?>
                            </a>
                            <?php if ($p['notas']): ?>
                                <div class="small text-muted"><?= esc($p['notas']) ?></div>
                            <?php endif ?>
                        </td>
                        <td class="d-none d-md-table-cell"><?= rtrim(rtrim(number_format((float) $p['rendimiento'], 3, ',', '.'), '0'), ',') ?> <?= esc($p['unidad']) ?></td>
                        <td class="text-end">
                            <?php if ($p['listo']): ?>
                                $<?= number_format((float) $p['costo_unitario'], 2, ',', '.') ?> / <?= esc($p['unidad']) ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif ?>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?php if ($p['usos'] > 0): ?>
                                <span class="badge text-bg-light border"><?= $p['usos'] ?> receta<?= $p['usos'] > 1 ? 's' : '' ?></span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php if (! $p['listo']): ?>
                                <span class="badge text-bg-warning">Sin ingredientes</span>
                            <?php elseif (! $p['activo']): ?>
                                <span class="badge text-bg-secondary">Inactiva</span>
                            <?php else: ?>
                                <span class="badge text-bg-success">Lista</span>
                            <?php endif ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= site_url('preparaciones/ficha/' . $p['id']) ?>" class="btn btn-sm btn-primary" title="Ver receta">
                                <i class="bi bi-clipboard-data"></i>
                            </a>
                            <form method="post" action="<?= site_url('preparaciones/eliminar/' . $p['id']) ?>" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar la preparación <?= esc($p['nombre'], 'js') ?>?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-2"></i>Nueva preparación</div>
    <div class="card-body">
        <form method="post" action="<?= site_url('preparaciones/crear') ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label small mb-1">Nombre</label>
                <input type="text" name="nombre" class="form-control" placeholder="Hogao, masa de pizza, caldo base…" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Se mide en</label>
                <select name="unidad" class="form-select">
                    <?php foreach ($unidades as $clave => $etiqueta): ?>
                        <option value="<?= $clave ?>"><?= $etiqueta ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Una tanda produce</label>
                <input type="number" name="rendimiento" class="form-control" step="0.001" min="0.001" required placeholder="1200">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Notas (opcional)</label>
                <input type="text" name="notas" class="form-control" placeholder="Cocción 40 min, dura 3 días">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
            </div>
        </form>
        <p class="small text-muted mt-3 mb-0">
            <i class="bi bi-lightbulb me-1"></i>
            Ejemplo: el hogao se mide en <strong>gramos</strong> y una tanda produce <strong>1.200 g</strong>.
            Si esa tanda cuesta $9.600, el sistema calculará $8 por gramo.
        </p>
    </div>
</div>

<?= $this->endSection() ?>
