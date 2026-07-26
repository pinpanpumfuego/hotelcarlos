<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<?php
// Asigna la escena ilustrada según el nombre del tipo de alojamiento
function escenaParaTipo(string $nombre): string
{
    $n = mb_strtolower($nombre);
    if (str_contains($n, 'cabaña') || str_contains($n, 'cabana') || str_contains($n, 'casa')) return 'cabana';
    if (str_contains($n, 'glamping') || str_contains($n, 'carpa') || str_contains($n, 'tienda')) return 'glamping';
    if (str_contains($n, 'habitaci')) return 'habitacion';
    return 'generico';
}
?>

<!-- ═══════════ HERO: vídeo real del hotel ═══════════ -->
<header class="hero">
    <video class="fondo" autoplay muted loop playsinline preload="auto"
           poster="<?= base_url('assets/img/portada.jpg') ?>" aria-hidden="true">
        <source src="<?= base_url('assets/video/portada.mp4') ?>" type="video/mp4">
    </video>
    <div class="velo" aria-hidden="true"></div>

    <div class="container contenido text-center">
        <?php
        // El logo va dentro del <h1>: lleva el nombre del hotel dibujado, así que
        // repetirlo debajo como texto sería redundante y quedaría de aficionado.
        // Con el `alt` puesto, los buscadores y los lectores de pantalla siguen
        // leyendo el nombre igual que si fuera texto.
        $logo = FCPATH . 'assets/img/logo.png';
        ?>
        <?php if (is_file($logo)): ?>
            <h1 class="mb-3">
                <img src="<?= base_url('assets/img/logo.png') ?>" class="logo-hero"
                     alt="<?= esc($hotel->nombre) ?> · <?= esc(lang('Web.avesNaturaleza')) ?>"
                     width="1024" height="1024" fetchpriority="high">
            </h1>
        <?php else: ?>
            <?php // Mientras no esté el archivo, la portada sigue funcionando ?>
            <p class="etiqueta" style="color:#f2cd7f;"><?= esc(lang('Web.ecolodgeColombia')) ?></p>
            <h1 class="mb-3"><?= esc($hotel->nombre) ?></h1>
        <?php endif ?>
        <p class="eslogan mb-4">
            <?= esc(lang('Web.eslogan1')) ?><br class="d-sm-none"> <?= esc(lang('Web.eslogan2')) ?>
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a class="btn btn-reserva btn-lg" href="<?= url_web('reservar') ?>">
                <i class="bi bi-calendar-check me-1"></i><?= esc(lang('Web.reservarAhora')) ?>
            </a>
            <a class="btn btn-contorno btn-lg" href="<?= url_web('alojamientos') ?>"><?= esc(lang('Web.verAlojamientos')) ?></a>
        </div>
    </div>
</header>

<!-- ═══════════ Bienvenida ═══════════ -->
<section class="seccion">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center reveal">
                <p class="etiqueta"><?= esc(lang('Web.bienvenido')) ?></p>
                <h2 class="titulo-seccion mb-3"><?= esc(lang('Web.refugio')) ?></h2>
                <p class="text-muted fs-5 mb-0"><?= esc(lang('Web.bienvenidaTexto', [$hotel->nombre])) ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ Alojamientos ═══════════ -->
<section class="seccion pt-0">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <p class="etiqueta"><?= esc(lang('Web.nuestrosEspacios')) ?></p>
            <h2 class="titulo-seccion"><?= esc(lang('Web.eligeDormir')) ?></h2>
        </div>
        <div class="row g-4">
            <?php foreach ($tipos as $t): ?>
                <div class="col-md-4 reveal">
                    <div class="card tarjeta-tipo h-100">
                        <?= view('web/_escena', ['variante' => escenaParaTipo($t['nombre'])]) ?>
                        <div class="card-body p-4">
                            <h3 class="h5 mb-2"><?= esc($t['nombre']) ?></h3>
                            <p class="text-muted small mb-3"><?= esc($t['descripcion'] ?? '') ?></p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="chip">
                                    <i class="bi bi-people me-1"></i><?= esc(lang('Web.hasta')) ?>
                                    <?= esc($t['capacidad']) ?> <?= esc(lang('Web.personas')) ?>
                                </span>
                            </div>
                            <p class="precio-desde mb-0 fs-5">
                                <?= esc(lang('Web.desde')) ?> $<?= number_format((float) $t['tarifa_base'], 0, ',', '.') ?> COP
                                <span class="text-muted fs-6">/ <?= esc(lang('Web.porNoche')) ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
        <div class="text-center mt-4 reveal">
            <a class="btn btn-reserva" href="<?= url_web('alojamientos') ?>"><?= esc(lang('Web.verTodosDetalles')) ?></a>
        </div>
    </div>
</section>

<!-- ═══════════ El lugar: fotos reales ═══════════ -->
<section class="seccion pt-0">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <p class="etiqueta"><?= esc(lang('Web.elLugar')) ?></p>
            <h2 class="titulo-seccion"><?= esc(lang('Web.asiSeVe')) ?></h2>
        </div>
        <div class="row g-3">
            <div class="col-md-4 reveal">
                <img src="<?= base_url('assets/img/cabanas.jpg') ?>" alt="<?= esc(lang('Web.altCabanas')) ?>" class="img-fluid rounded-4 shadow-sm w-100" style="object-fit:cover; aspect-ratio: 4/3;" loading="lazy">
            </div>
            <div class="col-md-4 reveal">
                <img src="<?= base_url('assets/img/lago.jpg') ?>" alt="<?= esc(lang('Web.altLago')) ?>" class="img-fluid rounded-4 shadow-sm w-100" style="object-fit:cover; aspect-ratio: 4/3;" loading="lazy">
            </div>
            <div class="col-md-4 reveal">
                <img src="<?= base_url('assets/img/valle.jpg') ?>" alt="<?= esc(lang('Web.altValle')) ?>" class="img-fluid rounded-4 shadow-sm w-100" style="object-fit:cover; aspect-ratio: 4/3;" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ Experiencias ═══════════ -->
<section class="banda seccion">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <p class="etiqueta" style="color:#e9c98b;"><?= esc(lang('Web.laExperiencia')) ?></p>
            <h2 class="titulo-seccion" style="color:#fff;"><?= esc(lang('Web.masQueNoche')) ?></h2>
        </div>
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3 reveal">
                <div class="icono-banda mb-2"><i class="bi bi-water"></i></div>
                <h3 class="h6"><?= esc(lang('Web.elLago')) ?></h3>
                <p class="small opacity-75 mb-0"><?= esc(lang('Web.elLagoTexto')) ?></p>
            </div>
            <div class="col-6 col-md-3 reveal">
                <div class="icono-banda mb-2"><i class="bi bi-signpost-2"></i></div>
                <h3 class="h6"><?= esc(lang('Web.senderos')) ?></h3>
                <p class="small opacity-75 mb-0"><?= esc(lang('Web.senderosTexto')) ?></p>
            </div>
            <div class="col-6 col-md-3 reveal">
                <div class="icono-banda mb-2"><i class="bi bi-egg-fried"></i></div>
                <h3 class="h6"><?= esc(lang('Web.cocinaLocal')) ?></h3>
                <p class="small opacity-75 mb-0"><?= esc(lang('Web.cocinaLocalTexto')) ?></p>
            </div>
            <div class="col-6 col-md-3 reveal">
                <div class="icono-banda mb-2"><i class="bi bi-moon-stars"></i></div>
                <h3 class="h6"><?= esc(lang('Web.cieloNocturno')) ?></h3>
                <p class="small opacity-75 mb-0"><?= esc(lang('Web.cieloNocturnoTexto')) ?></p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ CTA final ═══════════ -->
<section class="cta-final seccion text-center">
    <div class="container reveal">
        <h2 class="titulo-seccion mb-3" style="color:#fff;"><?= esc(lang('Web.listoDesconectar')) ?></h2>
        <p class="opacity-75 mb-4 fs-5"><?= esc(lang('Web.escribenosPlanear')) ?></p>
        <a class="btn btn-reserva btn-lg px-5" href="<?= url_web('reservar') ?>">
            <i class="bi bi-calendar-check me-1"></i><?= esc(lang('Web.reservarEnLinea')) ?>
        </a>
        <p class="small opacity-50 mt-3 mb-0"><?= esc(lang('Web.disponibilidadReal')) ?></p>
    </div>
</section>

<?= $this->endSection() ?>
