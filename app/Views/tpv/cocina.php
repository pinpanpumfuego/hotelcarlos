<?= $this->extend('layouts/panel') ?>
<?= $this->section('contenido') ?>

<?php
// Agrupa las líneas pendientes por comanda
$porComanda = [];
foreach ($pendientes as $l) {
    $porComanda[$l['comanda_id']]['datos'] = $l;
    $porComanda[$l['comanda_id']]['lineas'][] = $l;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="<?= site_url('tpv') ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left me-1"></i>Restaurante</a>
        <h1 class="h3 mb-0 mt-1">Cocina</h1>
    </div>
    <button class="btn btn-outline-secondary" onclick="location.reload()">
        <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
    </button>
</div>

<div class="row g-3">
    <?php if (empty($porComanda)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-check2-all fs-1 d-block mb-2 text-success opacity-75"></i>
                    No hay nada pendiente de preparar.
                </div>
            </div>
        </div>
    <?php endif ?>

    <?php foreach ($porComanda as $comandaId => $grupo): ?>
        <?php
        $minutos = (int) ((time() - strtotime($grupo['datos']['comanda_hora'])) / 60);
        $color   = $minutos >= 20 ? 'danger' : ($minutos >= 10 ? 'warning' : 'success');
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-<?= $color ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><?= esc($grupo['datos']['numero']) ?></span>
                    <span class="badge text-bg-<?= $color ?>"><?= $minutos ?> min</span>
                </div>
                <div class="card-body pb-2">
                    <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i><?= esc($grupo['datos']['mesa'] ?? 'Cabaña / room service') ?></p>
                    <?php foreach ($grupo['lineas'] as $l): ?>
                        <form method="post" action="<?= site_url('tpv/linea/' . $l['id'] . '/entregar') ?>"
                              class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="volver" value="cocina">
                            <span>
                                <span class="badge text-bg-dark"><?= esc($l['cantidad']) ?></span>
                                <?= esc($l['nombre_producto']) ?>
                                <?php if ($l['notas']): ?>
                                    <span class="d-block small text-danger"><i class="bi bi-exclamation-circle me-1"></i><?= esc($l['notas']) ?></span>
                                <?php endif ?>
                            </span>
                            <button class="btn btn-sm btn-outline-success text-nowrap"><i class="bi bi-check-lg"></i> Listo</button>
                        </form>
                    <?php endforeach ?>
                </div>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?= $this->endSection() ?>
