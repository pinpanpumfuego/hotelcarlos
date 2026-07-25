<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<?php
function escenaParaTipoLista(string $nombre): string
{
    $n = mb_strtolower($nombre);
    if (str_contains($n, 'cabaña') || str_contains($n, 'cabana') || str_contains($n, 'casa')) return 'cabana';
    if (str_contains($n, 'glamping') || str_contains($n, 'carpa') || str_contains($n, 'tienda')) return 'glamping';
    if (str_contains($n, 'habitaci')) return 'habitacion';
    return 'generico';
}
?>

<div class="container seccion">
    <div class="text-center mb-5 reveal">
        <p class="etiqueta">Alojamientos</p>
        <h1 class="titulo-seccion">Encuentra tu espacio</h1>
        <p class="text-muted mt-2">Todas las tarifas son por noche e incluyen el acceso al lago y los senderos.</p>
    </div>

    <?php foreach ($tipos as $t): ?>
        <div class="card tarjeta-tipo mb-4 reveal">
            <div class="row g-0 align-items-stretch">
                <div class="col-md-5">
                    <?= view('web/_escena', ['variante' => escenaParaTipoLista($t['nombre'])]) ?>
                </div>
                <div class="col-md-7 d-flex align-items-center">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <h2 class="h4 mb-0"><?= esc($t['nombre']) ?></h2>
                            <span class="precio-desde fs-5">Desde $<?= number_format((float) $t['tarifa_base'], 0, ',', '.') ?> COP <span class="text-muted fs-6">/ noche</span></span>
                        </div>
                        <p class="text-muted"><?= esc($t['descripcion'] ?? '') ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <span class="chip"><i class="bi bi-people me-1"></i>Hasta <?= esc($t['capacidad']) ?> personas</span>
                            <span class="chip"><i class="bi bi-water me-1"></i>Acceso al lago</span>
                            <span class="chip"><i class="bi bi-p-circle me-1"></i>Parqueadero</span>
                        </div>
                        <a class="btn btn-reserva" href="<?= site_url('reservar') ?>">
                            <i class="bi bi-calendar-check me-1"></i>Consultar disponibilidad
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach ?>

    <?php if (empty($tipos)): ?>
        <p class="text-center text-muted reveal">Muy pronto publicaremos nuestros alojamientos.</p>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
