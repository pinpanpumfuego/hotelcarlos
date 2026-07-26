<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
$grupos = ['Dormitorio', 'Baño', 'Sala', 'Cocina', 'Exterior'];
$total  = 0;
$valor  = 0.0;
foreach ($catalogo as $lista) {
    foreach ($lista as $i) {
        if ((int) $i['activo'] === 1) {
            $total++;
            $valor += (float) $i['valor_reposicion'] * (int) $i['cantidad_estandar'];
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Enseres de las cabañas</h1>
        <p class="text-muted mb-0">
            Lo que debe haber dentro de una cabaña. Se revisa después de cada estancia y,
            si falta algo, se cobra a este valor.
        </p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalItem">
        <i class="bi bi-plus-lg me-1"></i>Nuevo artículo
    </button>
</div>

<?= view('partes/errores') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Artículos en el catálogo</div>
            <div class="valor h4 mb-0"><?= $total ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Valor de dotar una cabaña</div>
            <div class="valor h4 mb-0">$<?= number_format($valor, 0, ',', '.') ?></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body kpi">
            <div class="text-muted small">Dotar las <?= (int) $unidades ?> cabañas</div>
            <div class="valor h4 mb-0">$<?= number_format($valor * (int) $unidades, 0, ',', '.') ?></div>
            <div class="text-muted small">con la cantidad estándar</div>
        </div></div>
    </div>
</div>

<?php foreach ($catalogo as $grupo => $lista): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><?= esc($grupo) ?></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th class="text-center">Cantidad estándar</th>
                        <th class="text-end">Valor de reposición</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lista as $i): ?>
                        <tr class="<?= (int) $i['activo'] !== 1 ? 'opacity-50' : '' ?>">
                            <td>
                                <span class="fw-semibold"><?= esc($i['nombre']) ?></span>
                                <?php if ((int) $i['activo'] !== 1): ?>
                                    <span class="badge text-bg-secondary ms-1">Desactivado</span>
                                <?php endif ?>
                            </td>
                            <td class="text-center"><?= (int) $i['cantidad_estandar'] ?></td>
                            <td class="text-end">
                                <?= (float) $i['valor_reposicion'] > 0
                                    ? '$' . number_format((float) $i['valor_reposicion'], 0, ',', '.')
                                    : '<span class="text-muted">no se cobra</span>' ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalItem<?= $i['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= site_url('inventario-cabanas/eliminar/' . $i['id']) ?>"
                                          onsubmit="return confirm('¿Quitar <?= esc($i['nombre'], 'js') ?> del catálogo?');">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach ?>

<div class="alert alert-light">
    <i class="bi bi-info-circle me-1"></i>
    El <strong>valor de reposición</strong> es lo que se le cobra al huésped si se lleva el artículo.
    Ponlo a 0 en lo que no tenga sentido cobrar (una almohada gastada, por ejemplo).
    Cuánto hay en cada cabaña se ajusta en <a href="<?= site_url('unidades') ?>">su ficha</a>.
</div>

<?= view('catalogos/_modal_item', ['id' => 'modalItem', 'accion' => site_url('inventario-cabanas/guardar'), 'item' => null, 'grupos' => $grupos]) ?>
<?php foreach ($catalogo as $lista): ?>
    <?php foreach ($lista as $i): ?>
        <?= view('catalogos/_modal_item', [
            'id'     => 'modalItem' . $i['id'],
            'accion' => site_url('inventario-cabanas/actualizar/' . $i['id']),
            'item'   => $i,
            'grupos' => $grupos,
        ]) ?>
    <?php endforeach ?>
<?php endforeach ?>

<?= $this->endSection() ?>
