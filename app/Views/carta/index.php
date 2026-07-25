<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$iconoDestino = ['cocina' => 'bi-fire', 'barra' => 'bi-cup-straw', 'directo' => 'bi-box-seam'];
$colorDestino = ['cocina' => 'warning', 'barra' => 'info', 'directo' => 'secondary'];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">Carta del restaurante</h1>
    <a href="<?= site_url('pos') ?>" class="btn btn-outline-primary"><i class="bi bi-tablet-landscape me-1"></i>Ver en el TPV</a>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Destino de cada producto:</strong>
    <span class="badge text-bg-warning"><i class="bi bi-fire"></i> Cocina</span> se prepara y aparece en la pantalla de cocina ·
    <span class="badge text-bg-info"><i class="bi bi-cup-straw"></i> Barra</span> lo prepara el bar (también sale en esa pantalla, marcado) ·
    <span class="badge text-bg-secondary"><i class="bi bi-box-seam"></i> Entrega directa</span> se sirve tal cual (cerveza, agua)
    y <strong>no pasa por cocina</strong>.
</div>

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
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <span>
                                <span style="display:inline-block; width:14px; height:14px; border-radius:4px; background:<?= esc($c['color'] ?? '#4f8a68') ?>; vertical-align:middle;"></span>
                                <span class="ms-1"><?= esc($c['nombre']) ?></span>
                                <span class="text-muted small">· <?= (int) ($conteo[$c['id']] ?? 0) ?> productos</span>
                            </span>
                            <span class="text-nowrap">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#cat<?= $c['id'] ?>" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" action="<?= site_url('carta/categoria/eliminar/' . $c['id']) ?>" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar la categoría <?= esc($c['nombre'], 'js') ?>?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </span>
                        </div>
                        <form method="post" action="<?= site_url('carta/categoria/actualizar/' . $c['id']) ?>"
                              class="collapse mt-3 row g-2 align-items-end" id="cat<?= $c['id'] ?>">
                            <?= csrf_field() ?>
                            <div class="col-6">
                                <label class="form-label small mb-1">Nombre</label>
                                <input type="text" name="nombre" class="form-control form-control-sm" value="<?= esc($c['nombre']) ?>" required>
                            </div>
                            <div class="col-3">
                                <label class="form-label small mb-1">Orden</label>
                                <input type="number" name="orden" class="form-control form-control-sm" value="<?= (int) $c['orden'] ?>">
                            </div>
                            <div class="col-3">
                                <label class="form-label small mb-1">Color</label>
                                <input type="color" name="color" class="form-control form-control-sm form-control-color w-100"
                                       value="<?= esc($c['color'] ?? '#4f8a68') ?>">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-sm btn-primary w-100">Guardar cambios</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="card-body border-top">
                <h2 class="h6 text-muted mb-3">Nueva categoría</h2>
                <form method="post" action="<?= site_url('carta/categoria/guardar') ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-6">
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre" required>
                    </div>
                    <div class="col-3">
                        <input type="number" name="orden" class="form-control" placeholder="Nº" title="Orden en el TPV">
                    </div>
                    <div class="col-2">
                        <input type="color" name="color" class="form-control form-control-color w-100" value="#4f8a68" title="Color">
                    </div>
                    <div class="col-1">
                        <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
                    </div>
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
                        <tr>
                            <th>Producto</th>
                            <th class="d-none d-md-table-cell">Categoría</th>
                            <th>Destino</th>
                            <th>Ficha</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end d-none d-lg-table-cell">Coste</th>
                            <th class="text-end d-none d-lg-table-cell">Food cost</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-journal-x fs-3 d-block mb-2 opacity-50"></i>
                                La carta está vacía.
                            </td></tr>
                        <?php endif ?>
                        <?php foreach ($productos as $p): ?>
                            <?php $d = $p['destino'] ?? 'cocina'; ?>
                            <tr class="<?= $p['disponible'] ? '' : 'opacity-50' ?>">
                                <td>
                                    <span class="fw-semibold"><?= esc($p['nombre']) ?></span>
                                    <?php if ($p['descripcion']): ?>
                                        <div class="small text-muted"><?= esc($p['descripcion']) ?></div>
                                    <?php endif ?>
                                </td>
                                <td class="d-none d-md-table-cell"><?= esc($p['categoria_nombre']) ?></td>
                                <td>
                                    <span class="badge text-bg-<?= $colorDestino[$d] ?>">
                                        <i class="bi <?= $iconoDestino[$d] ?>"></i> <?= esc($destinos[$d]) ?>
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <?php foreach ($dietas as $campo => $info): ?>
                                        <?php if (! empty($p[$campo])): ?>
                                            <i class="bi <?= $info['icono'] ?> text-success" title="<?= esc($info['etiqueta']) ?>"></i>
                                        <?php endif ?>
                                    <?php endforeach ?>
                                    <?php if ((int) ($p['picante'] ?? 0) > 0): ?>
                                        <span title="<?= esc($picante[(int) $p['picante']]) ?>"><?= str_repeat('🌶', (int) $p['picante']) ?></span>
                                    <?php endif ?>
                                    <?php if (! empty($p['alergenos_lista'])): ?>
                                        <span class="badge text-bg-danger-subtle text-danger-emphasis border border-danger-subtle"
                                              title="<?= esc(implode(', ', array_map(static fn ($a) => $alergenos[$a], $p['alergenos_lista']))) ?>">
                                            <i class="bi bi-exclamation-triangle"></i> <?= count($p['alergenos_lista']) ?>
                                        </span>
                                    <?php endif ?>
                                    <?php if (! empty($p['divisible'])): ?>
                                        <span class="badge text-bg-light border" title="Se vende por mitades"><i class="bi bi-pie-chart"></i></span>
                                    <?php endif ?>
                                </td>
                                <td class="text-end">$<?= number_format((float) $p['precio'], 0, ',', '.') ?></td>
                                <td class="text-end d-none d-lg-table-cell text-muted">
                                    <?= $p['coste'] !== null ? '$' . number_format($p['coste'], 0, ',', '.') : '—' ?>
                                </td>
                                <td class="text-end d-none d-lg-table-cell">
                                    <?php if ($p['foodcost'] !== null): ?>
                                        <span class="fw-semibold text-<?= $p['foodcost'] > 35 ? 'danger' : ($p['foodcost'] > 30 ? 'warning' : 'success') ?>">
                                            <?= $p['foodcost'] ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif ?>
                                </td>
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
                                    <a href="<?= site_url('carta/ficha/' . $p['id']) ?>" class="btn btn-sm btn-primary" title="Ficha completa: alérgenos, modificadores y escandallo">
                                        <i class="bi bi-clipboard-data"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse"
                                            data-bs-target="#editar<?= $p['id'] ?>" title="Edición rápida">
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
                                <td colspan="9" class="bg-light">
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
                                            <label class="form-label small mb-1">Destino</label>
                                            <select name="destino" class="form-select form-select-sm">
                                                <?php foreach ($destinos as $valor => $etiqueta): ?>
                                                    <option value="<?= $valor ?>" <?= $d === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                                <?php endforeach ?>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small mb-1">Precio</label>
                                            <input type="number" name="precio" class="form-control form-control-sm" value="<?= esc((int) $p['precio']) ?>" min="0" step="100" required>
                                        </div>
                                        <div class="col-md-1 d-flex gap-2 align-items-center">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="disponible" value="1" id="disp<?= $p['id'] ?>" <?= $p['disponible'] ? 'checked' : '' ?>>
                                                <label class="form-check-label small" for="disp<?= $p['id'] ?>">Disp.</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-sm btn-primary">Guardar cambios</button>
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
                            <label class="form-label small mb-1">Nombre</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Nombre" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" placeholder="Opcional">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Categoría</label>
                            <select name="categoria_id" class="form-select" required>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= esc($c['id']) ?>"><?= esc($c['nombre']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">Destino</label>
                            <select name="destino" class="form-select">
                                <?php foreach ($destinos as $valor => $etiqueta): ?>
                                    <option value="<?= $valor ?>"><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small mb-1">Precio</label>
                            <input type="number" name="precio" class="form-control" min="0" step="100" required>
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
