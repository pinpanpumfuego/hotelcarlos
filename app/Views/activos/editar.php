<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= $activo === null ? 'Nuevo equipo' : 'Editar equipo' ?></h1>
        <p class="text-muted mb-0">
            <?php if ($activo === null): ?>
                Le tocará el código <strong class="font-monospace"><?= esc($codigo) ?></strong>.
                Al guardarlo podrás imprimir su etiqueta.
            <?php else: ?>
                Código <strong class="font-monospace"><?= esc($codigo) ?></strong> — no cambia nunca,
                porque ya está pegado en el equipo.
            <?php endif ?>
        </p>
    </div>
    <a href="<?= $activo === null ? site_url('activos') : site_url('activos/ver/' . $activo['id']) ?>"
       class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?= view('partes/errores') ?>

<form method="post" action="<?= $activo === null ? site_url('activos/guardar') : site_url('activos/guardar/' . $activo['id']) ?>">
    <?= csrf_field() ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam me-2"></i>Qué es</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required maxlength="120"
                               value="<?= esc(old('nombre', $activo['nombre'] ?? '')) ?>"
                               placeholder="Calentador de la cabaña Sinsonte">
                        <div class="form-text">
                            Como lo llama el personal. Si alguien dice «el calentador del Sinsonte»,
                            que se encuentre buscando eso.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select name="categoria" class="form-select" required>
                                <?php foreach ($categorias as $c => $etiqueta): ?>
                                    <option value="<?= $c ?>" <?= old('categoria', $activo['categoria'] ?? '') === $c ? 'selected' : '' ?>>
                                        <?= esc($etiqueta) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <?php foreach ($estados as $e => $etiqueta): ?>
                                    <option value="<?= $e ?>" <?= old('estado', $activo['estado'] ?? 'activo') === $e ? 'selected' : '' ?>>
                                        <?= esc($etiqueta) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="critico" value="1" id="critico"
                               <?= old('critico', $activo['critico'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="critico">
                            <strong>Es crítico</strong>
                            <span class="d-block form-text">
                                Si falla, deja la cabaña sin poder venderse o pone en riesgo a alguien.
                                Sus averías salen con prioridad alta y proponen bloquear la unidad.
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-2"></i>Dónde está</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Cabaña</label>
                        <select name="unidad_id" class="form-select" id="unidadSel">
                            <option value="">No está en ninguna cabaña</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= (int) old('unidad_id', $activo['unidad_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                                    <?= esc($u['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="mb-0" id="ubicacionLibre">
                        <label class="form-label">Sitio</label>
                        <input type="text" name="ubicacion" class="form-control" maxlength="120"
                               value="<?= esc(old('ubicacion', $activo['ubicacion'] ?? '')) ?>"
                               placeholder="Cuarto de máquinas, cocina, recepción, mirador…">
                        <div class="form-text">Solo si no está en una cabaña.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-upc me-2"></i>Identificación</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Marca</label>
                            <input type="text" name="marca" class="form-control" maxlength="80"
                                   value="<?= esc(old('marca', $activo['marca'] ?? '')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Modelo</label>
                            <input type="text" name="modelo" class="form-control" maxlength="80"
                                   value="<?= esc(old('modelo', $activo['modelo'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Número de serie</label>
                            <input type="text" name="serie" class="form-control font-monospace" maxlength="80"
                                   value="<?= esc(old('serie', $activo['serie'] ?? '')) ?>">
                            <div class="form-text">
                                Es lo primero que pide el servicio técnico por teléfono, y está siempre
                                en una pegatina que no se ve sin agacharse.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2"></i>Compra y garantía</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Proveedor</label>
                        <select name="proveedor_id" class="form-select">
                            <option value="">Sin proveedor</option>
                            <?php foreach ($proveedores as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (int) old('proveedor_id', $activo['proveedor_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= esc($p['nombre']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Fecha de compra</label>
                            <input type="date" name="fecha_compra" class="form-control"
                                   value="<?= esc(old('fecha_compra', $activo['fecha_compra'] ?? '')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Valor</label>
                            <input type="number" name="valor_compra" class="form-control" min="0" step="any"
                                   value="<?= esc(old('valor_compra', $activo['valor_compra'] ?? '')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Garantía hasta</label>
                            <input type="date" name="garantia_hasta" class="form-control"
                                   value="<?= esc(old('garantia_hasta', $activo['garantia_hasta'] ?? '')) ?>">
                            <div class="form-text">Se avisa 60 días antes.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Vida útil</label>
                            <div class="input-group">
                                <input type="number" name="vida_util_meses" class="form-control" min="0"
                                       value="<?= esc(old('vida_util_meses', $activo['vida_util_meses'] ?? '')) ?>">
                                <span class="input-group-text">meses</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notas" class="form-control" rows="3"
                                      placeholder="Dónde está la llave de paso, qué repuesto lleva, con quién se contrata…"><?= esc(old('notas', $activo['notas'] ?? '')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button class="btn btn-primary btn-lg">
            <i class="bi bi-check-lg me-1"></i><?= $activo === null ? 'Dar de alta' : 'Guardar cambios' ?>
        </button>
    </div>
</form>

<script>
    // El sitio libre solo tiene sentido si no está en una cabaña: si no, serían
    // dos verdades sobre lo mismo y acabarían discrepando.
    (function () {
        var sel = document.getElementById('unidadSel');
        var libre = document.getElementById('ubicacionLibre');

        function ajustar() {
            libre.style.display = sel.value === '' ? '' : 'none';
        }

        sel.addEventListener('change', ajustar);
        ajustar();
    })();
</script>

<?= $this->endSection() ?>
