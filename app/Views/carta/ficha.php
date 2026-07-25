<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('carta') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Carta</a>
        <h1 class="h3 mb-0 mt-1"><?= esc($producto['nombre']) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <form method="post" action="<?= site_url('carta/producto/disponible/' . $producto['id']) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-secondary">
                <i class="bi <?= $producto['disponible'] ? 'bi-eye-slash' : 'bi-eye' ?> me-1"></i>
                <?= $producto['disponible'] ? 'Marcar agotado' : 'Marcar disponible' ?>
            </button>
        </form>
    </div>
</div>

<form method="post" action="<?= site_url('carta/producto/actualizar/' . $producto['id']) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="desde_ficha" value="1">

    <div class="row g-4">
        <!-- ═══ Datos básicos ═══ -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-card-text me-2"></i>Datos del plato</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="<?= esc($producto['nombre']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" value="<?= esc($producto['descripcion'] ?? '') ?>"
                                   placeholder="Como aparece en la carta">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" class="form-select">
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= esc($c['id']) ?>" <?= (int) $producto['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['nombre']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Precio de venta (COP)</label>
                            <input type="number" name="precio" class="form-control" value="<?= esc((int) $producto['precio']) ?>" min="0" step="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destino</label>
                            <select name="destino" class="form-select">
                                <?php foreach ($destinos as $valor => $etiqueta): ?>
                                    <option value="<?= $valor ?>" <?= ($producto['destino'] ?? 'cocina') === $valor ? 'selected' : '' ?>><?= $etiqueta ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="disponible" value="1" id="disp" <?= $producto['disponible'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="disp">Disponible en el TPV</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="divisible" value="1" id="divisible" <?= ! empty($producto['divisible']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="divisible">
                                    <strong>Se puede vender por mitades</strong>
                                    <span class="d-block small text-muted">Permite componer media y media con otro plato divisible (pizzas, tablas).</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Ficha técnica ═══ -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-heart-pulse me-2"></i>Ficha técnica: dietas y alérgenos</div>
                <div class="card-body">
                    <label class="form-label">Apto para</label>
                    <div class="d-flex flex-wrap gap-3 mb-3">
                        <?php foreach ($dietas as $campo => $info): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="<?= $campo ?>" value="1"
                                       id="d<?= $campo ?>" <?= ! empty($producto[$campo]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="d<?= $campo ?>">
                                    <i class="bi <?= $info['icono'] ?> me-1"></i><?= $info['etiqueta'] ?>
                                </label>
                            </div>
                        <?php endforeach ?>
                    </div>

                    <label class="form-label">Nivel de picante</label>
                    <select name="picante" class="form-select mb-3">
                        <?php foreach ($picante as $nivel => $etiqueta): ?>
                            <option value="<?= $nivel ?>" <?= (int) ($producto['picante'] ?? 0) === $nivel ? 'selected' : '' ?>><?= $etiqueta ?></option>
                        <?php endforeach ?>
                    </select>

                    <label class="form-label">
                        Alérgenos que contiene
                        <span class="text-muted small">— se avisará al mesero y al huésped</span>
                    </label>
                    <div class="row g-1">
                        <?php foreach ($alergenos as $clave => $etiqueta): ?>
                            <div class="col-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="alergenos[]" value="<?= $clave ?>"
                                           id="a<?= $clave ?>" <?= in_array($clave, $alergenosActivos, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="a<?= $clave ?>"><?= esc($etiqueta) ?></label>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Modificadores aplicables ═══ -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-sliders me-2"></i>Personalización</span>
                    <a href="<?= site_url('modificadores') ?>" class="small text-decoration-none">Gestionar grupos</a>
                </div>
                <div class="card-body">
                    <?php if (empty($grupos)): ?>
                        <p class="text-muted mb-0">
                            Aún no hay grupos de modificadores.
                            <a href="<?= site_url('modificadores') ?>">Crea el primero</a> (punto de la carne, extras, quitar ingredientes…).
                        </p>
                    <?php else: ?>
                        <p class="text-muted small">Marca los grupos que el mesero podrá elegir al añadir este plato.</p>
                        <?php foreach ($grupos as $g): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="grupos[]" value="<?= esc($g['id']) ?>"
                                       id="g<?= $g['id'] ?>" <?= in_array((int) $g['id'], $gruposAsignados, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="g<?= $g['id'] ?>">
                                    <strong><?= esc($g['nombre']) ?></strong>
                                    <span class="badge text-bg-light border ms-1"><?= $g['tipo'] === 'unico' ? 'Elegir uno' : 'Elegir varios' ?></span>
                                    <?php if ($g['obligatorio']): ?><span class="badge text-bg-warning ms-1">Obligatorio</span><?php endif ?>
                                    <span class="d-block small text-muted">
                                        <?= esc(implode(' · ', array_map(
                                            static fn ($o) => $o['nombre'] . ((float) $o['precio_extra'] > 0 ? ' (+$' . number_format((float) $o['precio_extra'], 0, ',', '.') . ')' : ''),
                                            $g['opciones']
                                        ))) ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
            </div>
        </div>

        <!-- ═══ Escandallo ═══ -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-calculator me-2"></i>Escandallo</span>
                    <a href="<?= site_url('insumos') ?>" class="small text-decoration-none">Gestionar insumos</a>
                </div>
                <div class="card-body">
                    <div class="row text-center g-2 mb-3">
                        <div class="col-4">
                            <div class="text-muted small">Coste</div>
                            <div class="fs-5 fw-bold">$<?= number_format($coste, 0, ',', '.') ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Margen</div>
                            <div class="fs-5 fw-bold text-<?= $margen > 0 ? 'success' : 'danger' ?>">$<?= number_format($margen, 0, ',', '.') ?></div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Food cost</div>
                            <div class="fs-5 fw-bold text-<?= $foodcost > 35 ? 'danger' : ($foodcost > 30 ? 'warning' : 'success') ?>">
                                <?= $coste > 0 ? $foodcost . '%' : '—' ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($coste > 0): ?>
                        <p class="small text-muted">
                            Referencia habitual en restauración: un food cost por debajo del 30–35 % se considera sano.
                        </p>
                    <?php endif ?>

                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr><th>Ingrediente</th><th class="text-end">Cantidad</th><th class="text-end">Coste</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($receta)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Sin receta todavía.</td></tr>
                            <?php endif ?>
                            <?php foreach ($receta as $r): ?>
                                <tr>
                                    <td><?= esc($r['nombre']) ?></td>
                                    <td class="text-end"><?= rtrim(rtrim(number_format($r['cantidad'], 3, ',', '.'), '0'), ',') ?> <?= esc($r['unidad']) ?></td>
                                    <td class="text-end">$<?= number_format($r['costo'], 0, ',', '.') ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="document.getElementById('quitar<?= $r['id'] ?>').submit()">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 mb-2">
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Guardar ficha del plato</button>
        <a href="<?= site_url('carta') ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
</form>

<!-- Formularios auxiliares fuera del principal (no se pueden anidar) -->
<?php foreach ($receta as $r): ?>
    <form method="post" action="<?= site_url('carta/receta/eliminar/' . $r['id']) ?>" id="quitar<?= $r['id'] ?>" class="d-none">
        <?= csrf_field() ?>
    </form>
<?php endforeach ?>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h6 text-muted mb-3">Añadir ingrediente a la receta</h2>
        <?php if (empty($insumos)): ?>
            <p class="text-muted mb-0">
                No hay insumos dados de alta. <a href="<?= site_url('insumos') ?>">Crea los insumos</a>
                (harina, trucha, queso…) con su coste por unidad y vuelve aquí.
            </p>
        <?php else: ?>
            <form method="post" action="<?= site_url('carta/receta/' . $producto['id']) ?>" class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-5">
                    <label class="form-label small mb-1">Insumo</label>
                    <select name="insumo_id" class="form-select" required>
                        <?php foreach ($insumos as $i): ?>
                            <option value="<?= esc($i['id']) ?>">
                                <?= esc($i['nombre']) ?> — $<?= number_format((float) $i['costo_unitario'], 2, ',', '.') ?>/<?= esc($i['unidad']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Cantidad por plato</label>
                    <input type="number" name="cantidad" class="form-control" step="0.001" min="0.001" required placeholder="Ej.: 180">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Añadir</button>
                </div>
            </form>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
