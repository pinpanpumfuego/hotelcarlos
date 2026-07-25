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

<!-- ═══════════ HERO: paisaje del lago ═══════════ -->
<header class="hero">
    <svg class="fondo" viewBox="0 0 1440 640" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <defs>
            <linearGradient id="cieloHero" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#2c5a43"/>
                <stop offset=".45" stop-color="#568a6b"/>
                <stop offset="1" stop-color="#c9b98a"/>
            </linearGradient>
            <linearGradient id="aguaHero" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#6ea3b8"/>
                <stop offset="1" stop-color="#2c586f"/>
            </linearGradient>
            <radialGradient id="solHero" cx=".5" cy=".5" r=".5">
                <stop offset="0" stop-color="#f7d894" stop-opacity=".95"/>
                <stop offset="1" stop-color="#f7d894" stop-opacity="0"/>
            </radialGradient>
        </defs>

        <rect width="1440" height="640" fill="url(#cieloHero)"/>
        <circle cx="1080" cy="180" r="150" fill="url(#solHero)"/>
        <circle cx="1080" cy="180" r="52" fill="#f2cd7f"/>

        <!-- Cordilleras -->
        <path d="M0 330 L200 180 L420 330 Z" fill="#1c3f2d" opacity=".55"/>
        <path d="M260 340 L520 140 L820 340 Z" fill="#16362a" opacity=".7"/>
        <path d="M700 345 L950 190 L1220 345 Z" fill="#1c3f2d" opacity=".6"/>
        <path d="M1080 350 L1290 220 L1440 340 Z" fill="#16362a" opacity=".75"/>

        <!-- Lago -->
        <rect y="335" width="1440" height="305" fill="url(#aguaHero)"/>
        <ellipse cx="1080" cy="380" rx="130" ry="10" fill="#f2cd7f" opacity=".3"/>
        <ellipse cx="1080" cy="420" rx="90" ry="7" fill="#f2cd7f" opacity=".18"/>
        <ellipse cx="400" cy="430" rx="220" ry="12" fill="#ffffff" opacity=".08"/>
        <ellipse cx="800" cy="520" rx="300" ry="14" fill="#ffffff" opacity=".06"/>

        <!-- Niebla -->
        <ellipse cx="350" cy="330" rx="280" ry="26" fill="#ffffff" opacity=".14"/>
        <ellipse cx="1000" cy="345" rx="320" ry="22" fill="#ffffff" opacity=".10"/>

        <!-- Orilla con pinos -->
        <path d="M0 640 L0 560 Q240 528 480 560 T960 555 T1440 565 L1440 640 Z" fill="#122b1f"/>
        <g fill="#0d211785">
            <path d="M80 570 L108 480 L136 570 Z"/><path d="M150 585 L172 510 L194 585 Z"/>
            <path d="M1250 575 L1278 485 L1306 575 Z"/><path d="M1330 590 L1352 515 L1374 590 Z"/>
        </g>
        <g fill="#0d2117">
            <path d="M60 590 L92 490 L124 590 Z"/><path d="M130 600 L156 520 L182 600 Z"/>
            <path d="M1230 595 L1262 495 L1294 595 Z"/><path d="M1310 605 L1336 530 L1362 605 Z"/>
        </g>

        <!-- Muelle -->
        <rect x="620" y="560" width="200" height="12" rx="3" fill="#5a4630"/>
        <rect x="640" y="572" width="10" height="34" fill="#463522"/>
        <rect x="790" y="572" width="10" height="34" fill="#463522"/>

        <!-- Aves -->
        <path d="M420 150 q14 -14 28 0 M470 170 q11 -11 22 0 M380 190 q9 -9 18 0"
              stroke="#eef3ee" stroke-width="3" fill="none" stroke-linecap="round" opacity=".7"/>
    </svg>

    <div class="container contenido text-center">
        <p class="etiqueta" style="color:#f2cd7f;">Hotel rural · Colombia</p>
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
                    junto al fuego. Habitaciones, cabañas y glamping pensados para desconectarte
                    de la ciudad y reconectarte con lo esencial.
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
