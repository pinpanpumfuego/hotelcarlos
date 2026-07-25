<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<h1 class="h3 mb-4">Carta del restaurante</h1>

<div class="row g-4">
    <!-- ═══ Categorías ═══ -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-tags me-2"></i>Categorías</div>
            <div class="list-group list-group-flush">
                <?php if (empty($categorias)): ?>
                    <div class="list-group-item text-muted text-center py-4">
                        Crea primero una categoría (Entradas, Platos fuertes, Bebidas…).
                    </div>
                <?php endif ?>
                <?php foreach ($categorias as $c): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= esc($c['nombre']) ?></span>
                        <form method="post" action="<?= site_url('carta/categoria/eliminar/' . $c['id']) ?>"
                              onsubmit="return confirm('¿Eliminar la categoría <?= esc($c['nombre'], 'js') ?>?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="card-body border-top">
                <form method="post" action="<?= site_url('carta/categoria/guardar') ?>" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <input type="text" name="nombre" class="form-control" placeholder="Nueva categoría" required>
                    <input type="number" name="orden" class="form-control" style="max-width: 80px;" placeholder="Nº" title="Orden">
                    <button class="btn btn-primary"><i class="bi bi-plus-lg"></i></button>
                </form>
            </div>
        </div>
    </div>

    <!-- ═══ Productos ═══ -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cup-hot me-2"></i>Productos</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Producto</th><th>Categoría</th><th class="text-end">Precio</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-journal-x fs-4 d-block mb-2 opacity-50"></i>
                                La carta está vacía.
                            </td></tr>
                        <?php endif ?>
                        <?php foreach ($productos as $p): ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold"><?= esc($p['nombre']) ?></span>
                                    <?php if ($p['descripcion']): ?>
                                        <div class="small text-muted"><?= esc($p['descripcion']) ?></div>
                                    <?php endif ?>
                                </td>
                                <td><?= esc($p['categoria_nombre']) ?></td>
                                <td class="text-end">$<?= number_format((float) $p['precio'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $p['disponible'] ? 'success' : 'secondary' ?>">
                                        <?= $p['disponible'] ? 'Disponible' : 'Agotado' ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <form method="post" action="<?= site_url('carta/producto/disponible/' . $p['id']) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-secondary" title="<?= $p['disponible'] ? 'Marcar agotado' : 'Marcar disponible' ?>">
                                            <i class="bi <?= $p['disponible'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse"
                                            data-bs-target="#editar<?= $p['id'] ?>" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= site_url('carta/producto/eliminar/' . $p['id']) ?>" class="d-inline"
                                          onsubmit="return confirm('¿Eliminar <?= esc($p['nombre'], 'js') ?> de la carta?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <tr class="collapse" id="editar<?= $p['id'] ?>">
                                <td colspan="5" class="bg-light">
                                    <form method="post" action="<?= site_url('carta/producto/actualizar/' . $p['id']) ?>" class="row g-2 align-items-end">
                                        <?= csrf_field() ?>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Nombre</label>
                                            <input type="text" name="nombre" class="form-control form-control-sm" value="<?= esc($p['nombre']) ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Descripción</label>
                                            <input type="text" name="descripcion" class="form-control form-control-sm" value="<?= esc($p['descripcion'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small mb-1">Categoría</label>
                                            <select name="categoria_id" class="form-select form-select-sm">
                                                <?php foreach ($categorias as $c): ?>
                                                    <option value="<?= esc($c['id']) ?>" <?= (int) $p['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['nombre']) ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small mb-1">Precio</label>
                                            <input type="number" name="precio" class="form-control form-control-sm" value="<?= esc((int) $p['precio']) ?>" min="0" step="100" required>
                                        </div>
                                        <div class="col-md-2 d-flex gap-2">
                                            <div class="form-check align-self-center">
                                                <input class="form-check-input" type="checkbox" name="disponible" value="1" id="disp<?= $p['id'] ?>" <?= $p['disponible'] ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="disp<?= $p['id'] ?>">Disp.</label>
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
            <?php if (! empty($categorias)): ?>
                <div class="card-body border-top">
                    <h2 class="h6 text-muted mb-3">Añadir producto</h2>
                    <form method="post" action="<?= site_url('carta/producto/guardar') ?>" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <div class="col-md-3">
                            <input type="text" name="nombre" class="form-control" placeholder="Nombre" required>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="descripcion" class="form-control" placeholder="Descripción (opcional)">
                        </div>
                        <div class="col-md-2">
                            <select name="categoria_id" class="form-select" required>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= esc($c['id']) ?>"><?= esc($c['nombre']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="precio" class="form-control" placeholder="Precio" min="0" step="100" required>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </form>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
