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
        <p class="etiqueta" style="color:#f2cd7f;">Ecolodge · Colombia</p>
        <h1 class="mb-3"><?= esc($hotel->nombre) ?></h1>
        <p class="eslogan mb-4">Montaña, lago y silencio.<br class="d-sm-none"> El descanso que estabas buscando.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a class="btn btn-reserva btn-lg" href="https://wa.me/<?= esc($hotel->whatsapp) ?>?text=Hola,%20quiero%20reservar" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp me-1"></i>Reservar ahora
            </a>
            <a class="btn btn-contorno btn-lg" href="<?= site_url('alojamientos') ?>">Ver alojamientos</a>
        </div>
    </div>
</header>

<!-- ═══════════ Bienvenida ═══════════ -->
<section class="seccion">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center reveal">
                <p class="etiqueta">Bienvenido</p>
                <h2 class="titulo-seccion mb-3">Un refugio entre montañas y agua</h2>
                <p class="text-muted fs-5 mb-0">
                    En <?= esc($hotel->nombre) ?> el día empieza con niebla sobre el lago y termina
                    junto al fuego. Nuestras cabañas a la orilla del agua están pensadas para
                    desconectarte de la ciudad y reconectarte con lo esencial.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ Alojamientos ═══════════ -->
<section class="seccion pt-0">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <p class="etiqueta">Nuestros espacios</p>
            <h2 class="titulo-seccion">Elige cómo quieres dormir</h2>
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
                                <span class="chip"><i class="bi bi-people me-1"></i>Hasta <?= esc($t['capacidad']) ?> personas</span>
                            </div>
                            <p class="precio-desde mb-0 fs-5">Desde $<?= number_format((float) $t['tarifa_base'], 0, ',', '.') ?> COP <span class="text-muted fs-6">/ noche</span></p>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
        <div class="text-center mt-4 reveal">
            <a class="btn btn-reserva" href="<?= site_url('alojamientos') ?>">Ver todos los detalles</a>
        </div>
    </div>
</section>

<!-- ═══════════ El lugar: fotos reales ═══════════ -->
<section class="seccion pt-0">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <p class="etiqueta">El lugar</p>
            <h2 class="titulo-seccion">Así se ve de verdad</h2>
        </div>
        <div class="row g-3">
            <div class="col-md-4 reveal">
                <img src="<?= base_url('assets/img/cabanas.jpg') ?>" alt="Cabañas junto al lago" class="img-fluid rounded-4 shadow-sm w-100" style="object-fit:cover; aspect-ratio: 4/3;" loading="lazy">
            </div>
            <div class="col-md-4 reveal">
                <img src="<?= base_url('assets/img/lago.jpg') ?>" alt="El lago con su muelle entre montañas" class="img-fluid rounded-4 shadow-sm w-100" style="object-fit:cover; aspect-ratio: 4/3;" loading="lazy">
            </div>
            <div class="col-md-4 reveal">
                <img src="<?= base_url('assets/img/valle.jpg') ?>" alt="El valle y las montañas que rodean el hotel" class="img-fluid rounded-4 shadow-sm w-100" style="object-fit:cover; aspect-ratio: 4/3;" loading="lazy">
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ Experiencias ═══════════ -->
<section class="banda seccion">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <p class="etiqueta" style="color:#e9c98b;">La experiencia</p>
            <h2 class="titulo-seccion" style="color:#fff;">Más que una noche de hotel</h2>
        </div>
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3 reveal">
                <div class="icono-banda mb-2"><i class="bi bi-water"></i></div>
                <h3 class="h6">El lago</h3>
                <p class="small opacity-75 mb-0">Amaneceres con niebla, pesca y paseos por la orilla.</p>
            </div>
            <div class="col-6 col-md-3 reveal">
                <div class="icono-banda mb-2"><i class="bi bi-signpost-2"></i></div>
                <h3 class="h6">Senderos</h3>
                <p class="small opacity-75 mb-0">Caminatas guiadas y cabalgatas entre montañas.</p>
            </div>
            <div class="col-6 col-md-3 reveal">
                <div class="icono-banda mb-2"><i class="bi bi-egg-fried"></i></div>
                <h3 class="h6">Cocina local</h3>
                <p class="small opacity-75 mb-0">Restaurante con productos frescos de la región.</p>
            </div>
            <div class="col-6 col-md-3 reveal">
                <div class="icono-banda mb-2"><i class="bi bi-moon-stars"></i></div>
                <h3 class="h6">Cielo nocturno</h3>
                <p class="small opacity-75 mb-0">Fogata, estrellas y el silencio del campo.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════ CTA final ═══════════ -->
<section class="cta-final seccion text-center">
    <div class="container reveal">
        <h2 class="titulo-seccion mb-3" style="color:#fff;">¿Listo para desconectar?</h2>
        <p class="opacity-75 mb-4 fs-5">Escríbenos y te ayudamos a planear tu estadía perfecta.</p>
        <a class="btn btn-reserva btn-lg px-5" href="https://wa.me/<?= esc($hotel->whatsapp) ?>?text=Hola,%20quiero%20reservar" target="_blank" rel="noopener">
            <i class="bi bi-whatsapp me-1"></i>Reservar por WhatsApp
        </a>
        <p class="small opacity-50 mt-3 mb-0">Respuesta rápida en horario de atención · Reserva directa sin comisiones</p>
    </div>
</section>

<?= $this->endSection() ?>
