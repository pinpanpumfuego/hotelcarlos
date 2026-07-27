<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$colorEstado = ['activo' => 'success', 'averiado' => 'danger', 'reparacion' => 'warning', 'baja' => 'secondary'];
$iconos      = [
    'cerradura' => 'key', 'calentador' => 'thermometer-half', 'bomba' => 'moisture',
    'piscina' => 'water', 'planta_electrica' => 'lightning-charge', 'extintor' => 'fire',
    'cocina' => 'fire', 'frio' => 'snow', 'clima' => 'wind', 'vehiculo' => 'truck',
    'mobiliario' => 'lamp', 'electronica' => 'pc-display', 'red' => 'wifi',
    'jardin' => 'tree', 'otro' => 'box',
];
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Equipos y activos</h1>
        <p class="text-muted mb-0">Lo que se avería y hay que mantener, con su etiqueta para escanear.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (puede('activos.gestionar')): ?>
            <a href="<?= site_url('activos/etiquetas') ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                <i class="bi bi-qr-code me-1"></i>Imprimir etiquetas
            </a>
            <a href="<?= site_url('activos/nuevo') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Nuevo equipo
            </a>
        <?php endif ?>
    </div>
</div>

<?= view('partes/errores') ?>

<?php if ($garantias !== []): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-shield-exclamation fs-5"></i>
        <div>
            <strong>Garantías que se acaban pronto.</strong>
            Si algo de esto va a fallar, más vale que falle antes de la fecha.
            <ul class="mb-0 mt-1 small">
                <?php foreach ($garantias as $g): ?>
                    <li>
                        <a href="<?= site_url('activos/ver/' . $g['id']) ?>"><?= esc($g['nombre']) ?></a>
                        · vence el <?= date('d/m/Y', strtotime($g['garantia_hasta'])) ?>
                        <?php if ($g['proveedor_nombre']): ?>
                            · <?= esc($g['proveedor_nombre']) ?>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    </div>
<?php endif ?>

<div class="row g-3 mb-4">
    <?php foreach (['activo', 'averiado', 'reparacion', 'baja'] as $e): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= site_url('activos?estado=' . $e) ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 <?= $filtros['estado'] === $e ? 'border-start border-4 border-' . $colorEstado[$e] : '' ?>">
                    <div class="card-body py-3">
                        <div class="text-muted small"><?= esc($estados[$e]) ?></div>
                        <div class="fs-3 fw-semibold text-<?= $colorEstado[$e] ?>"><?= (int) $conteo[$e] ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Buscar</label>
                <input type="search" name="buscar" class="form-control" value="<?= esc($filtros['buscar']) ?>"
                       placeholder="Nombre, código, serie, marca…">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Categoría</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $c => $etiqueta): ?>
                        <option value="<?= $c ?>" <?= $filtros['categoria'] === $c ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Dónde está</label>
                <select name="unidad_id" class="form-select">
                    <option value="">Todo el alojamiento</option>
                    <?php foreach ($unidades as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filtros['unidad_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= esc($u['nombre']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-fill"><i class="bi bi-funnel"></i></button>
                <a href="<?= site_url('activos') ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>
</div>

<?php if ($activos === []): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-box-seam fs-1 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">
                <?php if (array_filter($filtros)): ?>
                    Ningún equipo cuadra con eso.
                <?php else: ?>
                    Todavía no hay ningún equipo dado de alta.<br>
                    Empieza por lo que más se avería: calentadores, bombas y cerraduras.
                <?php endif ?>
            </p>
            <?php if (puede('activos.gestionar') && ! array_filter($filtros)): ?>
                <a href="<?= site_url('activos/nuevo') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Dar de alta el primero
                </a>
            <?php endif ?>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Equipo</th>
                        <th class="d-none d-md-table-cell">Dónde está</th>
                        <th class="d-none d-lg-table-cell">Marca y modelo</th>
                        <th>Estado</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activos as $a): ?>
                        <tr>
                            <td class="font-monospace small"><?= esc($a['codigo']) ?></td>
                            <td>
                                <a href="<?= site_url('activos/ver/' . $a['id']) ?>" class="text-decoration-none fw-semibold">
                                    <i class="bi bi-<?= $iconos[$a['categoria']] ?? 'box' ?> me-1 text-muted"></i>
                                    <?= esc($a['nombre']) ?>
                                </a>
                                <?php if ((int) $a['critico'] === 1): ?>
                                    <span class="badge text-bg-danger ms-1" title="Si falla, compromete la seguridad o deja la cabaña sin vender">Crítico</span>
                                <?php endif ?>
                                <div class="small text-muted d-md-none"><?= esc($a['unidad_nombre'] ?? $a['ubicacion']) ?></div>
                            </td>
                            <td class="d-none d-md-table-cell small text-muted"><?= esc($a['unidad_nombre'] ?? $a['ubicacion']) ?></td>
                            <td class="d-none d-lg-table-cell small text-muted">
                                <?= esc(trim(($a['marca'] ?? '') . ' ' . ($a['modelo'] ?? ''))) ?: '—' ?>
                            </td>
                            <td><span class="badge text-bg-<?= $colorEstado[$a['estado']] ?>"><?= esc($estados[$a['estado']]) ?></span></td>
                            <td class="text-end">
                                <a href="<?= site_url('activos/qr/' . $a['id']) ?>" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-secondary" title="Ver su QR">
                                    <i class="bi bi-qr-code"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif ?>

<?= $this->endSection() ?>
