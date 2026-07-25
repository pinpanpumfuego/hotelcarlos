<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion">
    <div class="text-center mb-5">
        <h1>Alojamientos</h1>
        <p class="text-muted">Elige el espacio perfecto para tu descanso</p>
    </div>

    <?php
    $iconos = ['bi-door-closed', 'bi-house-heart', 'bi-tree', 'bi-house', 'bi-signpost'];
    $fondos = ['#4f8a68', '#8a6d4b', '#3d7ab0', '#24513a', '#6f5539'];
    ?>
    <?php foreach ($tipos as $i => $t): ?>
        <div class="card tarjeta-tipo shadow-sm mb-4">
            <div class="row g-0">
                <div class="col-md-4 cabecera-tipo" style="background: <?= $fondos[$i % count($fondos)] ?>; min-height: 220px;">
                    <i class="bi <?= $iconos[$i % count($iconos)] ?>"></i>
                </div>
                <div class="col-md-8">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <h2 class="h4 mb-2"><?= esc($t['nombre']) ?></h2>
                            <span class="badge text-bg-success fs-6">Desde $<?= number_format((float) $t['tarifa_base'], 0, ',', '.') ?> COP/noche</span>
                        </div>
                        <p class="text-muted"><?= esc($t['descripcion'] ?? '') ?></p>
                        <p class="mb-3"><i class="bi bi-people me-1"></i>Capacidad: hasta <?= esc($t['capacidad']) ?> personas</p>
                        <a class="btn btn-reserva" href="https://wa.me/<?= esc($hotel->whatsapp) ?>?text=Hola,%20me%20interesa%20<?= rawurlencode($t['nombre']) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-whatsapp me-1"></i>Consultar disponibilidad
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <?php if (empty($tipos)): ?>
        <p class="text-center text-muted">Muy pronto publicaremos nuestros alojamientos.</p>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
