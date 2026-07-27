<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php $pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.'); ?>

<div class="mb-4">
    <a href="<?= site_url('planes') ?>" class="text-decoration-none small text-muted">
        <i class="bi bi-arrow-left me-1"></i>Planes
    </a>
    <h1 class="h3 mb-0 mt-1"><?= esc($plan['nombre']) ?></h1>
</div>

<?= view('partes/errores') ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Qué incluye</div>
            <div class="card-body">
                <?php if ($derechos === []): ?>
                    <p class="text-muted small mb-0">
                        Todavía no incluye nada. Un plan sin derechos no cambia nada al cobrar.
                    </p>
                <?php else: ?>
                    <?php foreach ($derechos as $d): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <span class="fw-semibold"><?= (int) $d['cantidad'] ?> × <?= esc($d['concepto'] ?? '—') ?></span>
                                <span class="d-block small text-muted">
                                    <?= esc($formas[$d['por']] ?? $d['por']) ?>
                                    <?php if (! empty($d['franja'])): ?>
                                        · solo en <?= esc($franjas[$d['franja']] ?? $d['franja']) ?>
                                    <?php else: ?>
                                        · a cualquier hora
                                    <?php endif ?>
                                    <?php if ((float) $d['tope'] > 0): ?>
                                        · hasta <?= $pesos((float) $d['tope']) ?>
                                    <?php endif ?>
                                    <?= empty($d['producto_id']) ? ' · categoría entera' : ' · producto concreto' ?>
                                </span>
                            </div>
                            <form method="post" action="<?= site_url('planes/derecho/quitar/' . $plan['id'] . '/' . $d['id']) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Añadir algo al plan</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('planes/derecho/' . $plan['id']) ?>">
                    <?= csrf_field() ?>
                    <p class="small text-muted">
                        Elige <strong>una categoría entera</strong> —lo normal, para que el huésped
                        pueda escoger dentro de lo incluido— <strong>o</strong> un producto concreto.
                        No las dos cosas.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoría entera</label>
                            <select name="categoria_id" class="form-select">
                                <option value="">— ninguna —</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= esc($c['nombre']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">…o un producto concreto</label>
                            <select name="producto_id" class="form-select">
                                <option value="">— ninguno —</option>
                                <?php foreach ($productos as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= esc($p['nombre']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cuántos</label>
                            <input type="number" name="cantidad" class="form-control" min="1" max="20" value="1">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Cómo se cuenta</label>
                            <select name="por" class="form-select">
                                <?php foreach ($formas as $clave => $texto): ?>
                                    <option value="<?= esc($clave) ?>"><?= esc($texto) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Solo en</label>
                            <select name="franja" class="form-select">
                                <option value="">Cualquier hora</option>
                                <?php foreach ($franjas as $clave => $texto): ?>
                                    <option value="<?= esc($clave) ?>"><?= esc($texto) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3"><i class="bi bi-plus-lg me-1"></i>Añadir</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Datos del plan</div>
            <div class="card-body">
                <form method="post" action="<?= site_url('planes/actualizar/' . $plan['id']) ?>">
                    <?= csrf_field() ?>
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control mb-3" required value="<?= esc($plan['nombre']) ?>">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control mb-3" rows="2"><?= esc($plan['descripcion'] ?? '') ?></textarea>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo"
                               <?= $plan['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">
                            <strong>Activo</strong>
                            <span class="d-block small text-muted">
                                Al desactivarlo deja de aplicarse, pero las estancias en curso
                                lo conservan hasta que se vayan.
                            </span>
                        </label>
                    </div>
                    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </form>
            </div>
        </div>

        <?php if ($consumoMes !== []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-graph-down me-2"></i>Lo incluido consumido este mes
                </div>
                <div class="card-body py-2">
                    <p class="small text-muted">
                        Lo incluido no se cobra, pero la cocina lo gasta igual. Esto es lo que
                        cuesta de verdad el plan.
                    </p>
                    <?php $totalMes = 0; ?>
                    <?php foreach ($consumoMes as $dia): ?>
                        <?php $totalMes += (float) $dia['valor']; ?>
                        <div class="d-flex justify-content-between small py-1 border-bottom">
                            <span><?= date('d/m', strtotime($dia['fecha'])) ?></span>
                            <span><?= (int) $dia['unidades'] ?> ud · <?= $pesos((float) $dia['valor']) ?></span>
                        </div>
                    <?php endforeach ?>
                    <div class="d-flex justify-content-between fw-semibold pt-2">
                        <span>Total del mes</span>
                        <span><?= $pesos($totalMes) ?></span>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<?= $this->endSection() ?>
