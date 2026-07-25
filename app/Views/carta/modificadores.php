<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0">Modificadores</h1>
    <a href="<?= site_url('carta') ?>" class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>Ir a la carta</a>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle me-1"></i>
    Los modificadores son las opciones que el mesero elige al añadir un plato.
    Se agrupan: por ejemplo <strong>“Punto de la carne”</strong> (elegir uno, obligatorio),
    <strong>“Extras”</strong> (elegir varios, cada uno con su precio) o <strong>“Quitar”</strong> (sin cebolla, sin salsa).
    Luego, en la ficha de cada plato, marcas qué grupos se le aplican.
</div>

<div class="row g-4">
    <?php foreach ($grupos as $g): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <span class="fw-semibold"><?= esc($g['nombre']) ?></span>
                        <div class="small">
                            <span class="badge text-bg-light border"><?= esc($tipos[$g['tipo']]) ?></span>
                            <?php if ($g['obligatorio']): ?>
                                <span class="badge text-bg-warning">Obligatorio</span>
                            <?php endif ?>
                        </div>
                    </div>
                    <span class="text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#g<?= $g['id'] ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="post" action="<?= site_url('modificadores/grupo/eliminar/' . $g['id']) ?>" class="d-inline"
                              onsubmit="return confirm('¿Eliminar el grupo <?= esc($g['nombre'], 'js') ?> y todas sus opciones?');">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </span>
                </div>

                <div class="collapse" id="g<?= $g['id'] ?>">
                    <div class="card-body bg-light border-bottom">
                        <form method="post" action="<?= site_url('modificadores/grupo/actualizar/' . $g['id']) ?>" class="row g-2 align-items-end">
                            <?= csrf_field() ?>
                            <div class="col-7">
                                <label class="form-label small mb-1">Nombre</label>
                                <input type="text" name="nombre" class="form-control form-control-sm" value="<?= esc($g['nombre']) ?>" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label small mb-1">Orden</label>
                                <input type="number" name="orden" class="form-control form-control-sm" value="<?= (int) $g['orden'] ?>">
                            </div>
                            <div class="col-7">
                                <label class="form-label small mb-1">Tipo</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    <?php foreach ($tipos as $valor => $etiqueta): ?>
                                        <option value="<?= $valor ?>" <?= $g['tipo'] === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="col-5 d-flex align-items-center">
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" name="obligatorio" value="1" id="ob<?= $g['id'] ?>" <?= $g['obligatorio'] ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="ob<?= $g['id'] ?>">Obligatorio</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-sm btn-primary w-100">Guardar grupo</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    <?php if (empty($g['opciones'])): ?>
                        <div class="list-group-item text-muted small text-center py-3">Sin opciones todavía.</div>
                    <?php endif ?>
                    <?php foreach ($g['opciones'] as $o): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                            <span>
                                <?= esc($o['nombre']) ?>
                                <?php if ((float) $o['precio_extra'] > 0): ?>
                                    <span class="badge text-bg-success ms-1">+$<?= number_format((float) $o['precio_extra'], 0, ',', '.') ?></span>
                                <?php endif ?>
                            </span>
                            <form method="post" action="<?= site_url('modificadores/opcion/eliminar/' . $o['id']) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button>
                            </form>
                        </div>
                    <?php endforeach ?>
                </div>

                <div class="card-body border-top">
                    <form method="post" action="<?= site_url('modificadores/opcion/guardar') ?>" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="grupo_id" value="<?= esc($g['id']) ?>">
                        <div class="col-7">
                            <input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nueva opción" required>
                        </div>
                        <div class="col-3">
                            <input type="number" name="precio_extra" class="form-control form-control-sm" placeholder="+$" min="0" step="500">
                        </div>
                        <div class="col-2">
                            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <!-- Nuevo grupo -->
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 border-2 border-dashed">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle me-2"></i>Nuevo grupo</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('modificadores/grupo/guardar') ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label small mb-1">Nombre del grupo</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Punto de la carne, Extras…" required>
                    </div>
                    <div class="col-7">
                        <label class="form-label small mb-1">Tipo</label>
                        <select name="tipo" class="form-select">
                            <?php foreach ($tipos as $valor => $etiqueta): ?>
                                <option value="<?= $valor ?>"><?= $etiqueta ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label small mb-1">Orden</label>
                        <input type="number" name="orden" class="form-control" value="0">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="obligatorio" value="1" id="obNuevo">
                            <label class="form-check-label small" for="obNuevo">
                                Obligatorio — el mesero tendrá que elegir una opción sí o sí
                            </label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Crear grupo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
