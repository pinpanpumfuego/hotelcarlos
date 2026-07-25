<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<header class="hero">
    <div class="container text-center py-5">
        <h1 class="fw-bold mb-3"><?= esc($hotel->nombre) ?></h1>
        <p class="fs-5 mb-4 opacity-75"><?= esc($hotel->eslogan) ?></p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a class="btn btn-reserva btn-lg px-4" href="https://wa.me/<?= esc($hotel->whatsapp) ?>?text=Hola,%20quiero%20reservar" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp me-1"></i>Reservar ahora
            </a>
            <a class="btn btn-outline-light btn-lg px-4" href="<?= site_url('alojamientos') ?>">Ver alojamientos</a>
        </div>
    </div>
</header>

<section class="seccion">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="h1">Nuestros alojamientos</h2>
            <p class="text-muted">Habitaciones, cabañas y glamping en plena naturaleza</p>
        </div>
        <div class="row g-4">
            <?php
            $iconos = ['bi-door-closed', 'bi-house-heart', 'bi-tree', 'bi-house', 'bi-signpost'];
            $fondos = ['#4f8a68', '#8a6d4b', '#3d7ab0', '#24513a', '#6f5539'];
            ?>
            <?php foreach ($tipos as $i => $t): ?>
                <div class="col-md-4">
                    <div class="card tarjeta-tipo shadow-sm h-100">
                        <div class="cabecera-tipo" style="background: <?= $fondos[$i % count($fondos)] ?>;">
                            <i class="bi <?= $iconos[$i % count($iconos)] ?>"></i>
                        </div>
                        <div class="card-body">
                            <h3 class="h5"><?= esc($t['nombre']) ?></h3>
                            <p class="text-muted small"><?= esc($t['descripcion'] ?? '') ?></p>
                            <p class="mb-1"><i class="bi bi-people me-1"></i>Hasta <?= esc($t['capacidad']) ?> personas</p>
                            <p class="fw-bold mb-0">Desde $<?= number_format((float) $t['tarifa_base'], 0, ',', '.') ?> COP / noche</p>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</section>

<section class="seccion" style="background: var(--verde-claro);">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <i class="bi bi-tree fs-1 text-success"></i>
                <h3 class="h6 mt-2">Naturaleza</h3>
                <p class="small text-muted mb-0">Senderos, montaña y aire puro a la puerta de tu habitación.</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-egg-fried fs-1 text-success"></i>
                <h3 class="h6 mt-2">Cocina local</h3>
                <p class="small text-muted mb-0">Restaurante con productos de la región.</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-binoculars fs-1 text-success"></i>
                <h3 class="h6 mt-2">Experiencias</h3>
                <p class="small text-muted mb-0">Cabalgatas, avistamiento de aves y caminatas guiadas.</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-whatsapp fs-1 text-success"></i>
                <h3 class="h6 mt-2">Reserva directa</h3>
                <p class="small text-muted mb-0">Sin comisiones: hablas directamente con nosotros.</p>
            </div>
        </div>
    </div>
</section>

<section class="seccion text-center">
    <div class="container">
        <h2 class="h1 mb-3">¿Listo para desconectar?</h2>
        <p class="text-muted mb-4">Escríbenos y te ayudamos a planear tu estadía.</p>
        <a class="btn btn-reserva btn-lg px-5" href="https://wa.me/<?= esc($hotel->whatsapp) ?>?text=Hola,%20quiero%20reservar" target="_blank" rel="noopener">
            <i class="bi bi-whatsapp me-1"></i>Reservar por WhatsApp
        </a>
    </div>
</section>

<?= $this->endSection() ?>
