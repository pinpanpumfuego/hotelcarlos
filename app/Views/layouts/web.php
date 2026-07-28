<?php
$idiomas   = idiomas_web();
$idiomaHtml = \App\Libraries\Traductor::IDIOMAS[idioma_web()]['html'];
?>
<!DOCTYPE html>
<html lang="<?= esc($idiomaHtml) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($tituloPagina ?? $hotel->nombre) ?> · <?= esc($hotel->nombre) ?></title>
    <meta name="description" content="<?= esc($descripcion ?? $hotel->eslogan) ?>">

    <?php // Le dice a Google que estas cuatro páginas son la misma en distinto
          // idioma, no contenido duplicado. Sin esto, las versiones traducidas
          // compiten entre sí en los resultados. ?>
    <?php foreach ($idiomas as $i): ?>
        <link rel="alternate" hreflang="<?= esc($i['html']) ?>" href="<?= esc($i['url']) ?>">
    <?php endforeach ?>
    <link rel="alternate" hreflang="x-default" href="<?= esc(url_web(ruta_actual_web(), 'es')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bosque: #1f4d36;
            --bosque-oscuro: #143425;
            --lago: #2e6f8e;
            --arena: #b98a4a;
            --arena-oscura: #96703a;
            --crema: #faf6ef;
            --tinta: #22302a;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--tinta);
            background: var(--crema);
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, h3, .fuente-titulo { font-family: 'Fraunces', Georgia, serif; }

        /* ── Navegación ── */
        .navbar-web {
            background: rgba(20, 52, 37, .92);
            backdrop-filter: blur(8px);
            padding-top: .8rem; padding-bottom: .8rem;
        }
        .navbar-web .nav-link, .navbar-web .navbar-brand { color: #f2eee3 !important; }
        .navbar-web .nav-link { font-size: .95rem; letter-spacing: .02em; }
        .navbar-web .nav-link.active { color: #e9c98b !important; }
        .navbar-web .navbar-brand { font-family: 'Fraunces', serif; font-size: 1.15rem; }

        /* ── Botones ── */
        .btn-reserva {
            background: var(--arena); border: none; color: #fff;
            border-radius: 100px; padding: .65rem 1.6rem; font-weight: 500;
        }
        .btn-reserva:hover { background: var(--arena-oscura); color: #fff; }
        .btn-contorno {
            border: 1px solid rgba(255,255,255,.7); color: #fff;
            border-radius: 100px; padding: .65rem 1.6rem; font-weight: 500;
        }
        .btn-contorno:hover { background: rgba(255,255,255,.15); color: #fff; }

        /* ── Hero ── */
        .hero { position: relative; overflow: hidden; color: #fff; }
        .hero .fondo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .hero .velo { position: absolute; inset: 0; background: linear-gradient(rgba(10, 25, 18, .25), rgba(10, 25, 18, .55)); }
        .hero .contenido {
            position: relative; z-index: 1;
            padding: clamp(5rem, 14vh, 9rem) 0 clamp(6rem, 16vh, 10rem);
            text-shadow: 0 1px 12px rgba(10, 30, 20, .45);
        }
        .hero h1 { font-size: clamp(2.4rem, 6vw, 4rem); font-weight: 600; }

        /* ── Logo de la portada ──
           Es una ilustración con mucho detalle (el barranquero, el letrero, las
           flores), así que necesita tamaño: por debajo de unos 200 px deja de
           leerse y se convierte en una mancha. La sombra lo despega del vídeo. */
        .logo-hero {
            display: block; margin: 0 auto;
            width: clamp(210px, 34vw, 380px); height: auto;
            filter: drop-shadow(0 6px 26px rgba(8, 24, 16, .55));
        }
        @media (max-width: 575.98px) {
            /* En móvil el vídeo se ve poco y el logo es lo que manda */
            .logo-hero { width: min(76vw, 300px); }
        }

        /* ── El logo cobra vida ──
           El vídeo es 9:16 y el logo 1:1. Se muestra entero dentro de la misma
           caja, centrado: así no se recorta y, sobre todo, **la caja no cambia
           de tamaño**, que es lo que haría saltar toda la portada. */
        .logo-vivo {
            position: relative; display: inline-block; line-height: 0;
        }
        .logo-video {
            position: absolute; inset: 0; margin: auto;
            max-width: 100%; max-height: 100%;
            opacity: 0; transition: opacity .45s ease;
            pointer-events: none; border-radius: 12px;
        }
        .logo-vivo.animando .logo-video { opacity: 1; }
        .logo-vivo.animando .logo-hero { opacity: 0; transition: opacity .45s ease; }
        .logo-hero { transition: opacity .45s ease; }

        /* El aviso de que ahí hay algo. Sin esto nadie descubriría el vídeo. */
        .logo-toque {
            position: absolute; right: 4%; bottom: 6%;
            width: 44px; height: 44px; border: 0; border-radius: 50%;
            background: rgba(20, 52, 37, .78); color: #f2cd7f;
            display: grid; place-items: center; font-size: 1.35rem; line-height: 1;
            backdrop-filter: blur(4px); cursor: pointer;
            transition: transform .2s ease, background .2s ease, opacity .3s ease;
        }
        .logo-toque:hover { background: rgba(20, 52, 37, .95); transform: scale(1.08); }
        .logo-vivo.animando .logo-toque { opacity: 0; pointer-events: none; }
        /* Con ratón el vídeo sale solo al pasar por encima, así que el botón
           solo hace falta donde no hay ratón */
        @media (hover: hover) and (pointer: fine) {
            .logo-toque { opacity: .55; }
            .logo-vivo:hover .logo-toque { opacity: 0; }
        }
        .hero .eslogan { font-size: clamp(1.05rem, 2vw, 1.3rem); opacity: .92; }

        /* ── Secciones ── */
        .seccion { padding: clamp(3.5rem, 8vw, 6rem) 0; }
        .etiqueta {
            display: inline-block; font-size: .78rem; font-weight: 600;
            letter-spacing: .18em; text-transform: uppercase; color: var(--arena);
            margin-bottom: .6rem;
        }
        .titulo-seccion { font-size: clamp(1.7rem, 3.5vw, 2.4rem); font-weight: 600; color: var(--bosque-oscuro); }

        /* ── Tarjetas ── */
        .tarjeta-tipo {
            border: none; border-radius: 1.1rem; overflow: hidden; background: #fff;
            box-shadow: 0 6px 24px rgba(31, 77, 54, .10);
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .tarjeta-tipo:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(31, 77, 54, .16); }
        .tarjeta-tipo .escena { display: block; width: 100%; height: auto; }
        .precio-desde { color: var(--bosque); font-weight: 600; }
        /* La conversión, siempre secundaria al precio real en pesos: es
           orientativa y el cargo se hace en COP. */
        .aprox { display: block; font-size: .78rem; font-weight: 400; color: #8a8375; margin-top: .1rem; }
        .chip {
            display: inline-block; background: #f0ebe0; border-radius: 100px;
            padding: .25rem .8rem; font-size: .82rem; color: #5c5546;
        }

        /* ── Banda destacada ── */
        .banda { background: var(--bosque); color: #eef3ee; }
        .banda .icono-banda { font-size: 2rem; color: #e9c98b; }

        /* ── CTA final ── */
        .cta-final { background: linear-gradient(140deg, var(--bosque-oscuro), var(--bosque) 65%, #2a5f44); color: #fff; }

        /* ── Pie ── */
        footer { background: #10271b; color: #b8ccbd; }
        footer a { color: #d8e5da; }
        footer .titulo-pie { color: #fff; font-family: 'Fraunces', serif; }

        /* ═══════════════════════════════════════════════════════════
           AVES

           Tres reglas para que esto quede elegante y no a página de los 2000:
           pocas, lentas y translúcidas. Todo se mueve con `transform` y
           `opacity`, que la tarjeta gráfica resuelve sola: cero recálculo de
           diseño, cero tirones y sin castigar la batería del móvil.
           ═══════════════════════════════════════════════════════════ */

        .cielo { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 1; }
        .ave {
            position: absolute; display: block;
            will-change: transform;
            animation: cruzar linear infinite;
        }
        .ave svg { display: block; width: 100%; height: auto; fill: currentColor; }
        /* Las alas: dos dibujos que se alternan. Una silueta que solo se
           desliza sin aletear parece un adhesivo pegado a la pantalla. */
        .ave .arriba, .ave .abajo { position: absolute; inset: 0; }
        .ave .arriba { animation: aletear steps(1) infinite; }
        .ave .abajo  { animation: aletear steps(1) infinite; animation-direction: reverse; }

        @keyframes cruzar {
            /* Sale por un lado y entra por el otro, con una leve subida:
               las aves no vuelan en línea recta perfecta */
            from { transform: translate3d(-14vw, 0, 0); }
            50%  { transform: translate3d(43vw, -3.2vh, 0); }
            to   { transform: translate3d(112vw, 0, 0); }
        }
        @keyframes aletear { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }

        /* Cuatro aves a distintas profundidades. Las de arriba son más
           pequeñas, más pálidas y más lentas: así se lee la distancia. */
        .hero .ave { color: #eef3ee; }
        .ave.v1 { top: 16%; width: 52px; opacity: .30; animation-duration: 58s; }
        .ave.v1 .arriba, .ave.v1 .abajo { animation-duration: 1.1s; }
        .ave.v2 { top: 26%; width: 34px; opacity: .22; animation-duration: 76s; animation-delay: -22s; }
        .ave.v2 .arriba, .ave.v2 .abajo { animation-duration: 1.35s; }
        .ave.v3 { top: 11%; width: 24px; opacity: .16; animation-duration: 94s; animation-delay: -48s; }
        .ave.v3 .arriba, .ave.v3 .abajo { animation-duration: 1.6s; }
        .ave.v4 { top: 33%; width: 40px; opacity: .18; animation-duration: 67s; animation-delay: -9s; }
        .ave.v4 .arriba, .ave.v4 .abajo { animation-duration: 1.25s; }

        /* ── El colibrí ──
           Entra volando, se queda suspendido junto al botón de reservar y se
           va. Una sola vez por visita: en bucle sería un mosquito. */
        .colibri {
            position: absolute; z-index: 3; width: 46px; color: #f0e2bd;
            opacity: 0; pointer-events: none;
            animation: colibri-visita 13s ease-in-out 3.5s 1 forwards;
        }
        .colibri svg { display: block; width: 100%; height: auto; fill: currentColor; }
        .colibri .cuerpo { animation: suspender 1.9s ease-in-out infinite; transform-origin: 60% 60%; }
        @keyframes colibri-visita {
            0%   { opacity: 0; transform: translate3d(26vw, 8vh, 0) scale(.7); }
            14%  { opacity: .9; }
            /* Se para junto al botón, que es donde interesa que mire el ojo */
            30%, 62% { opacity: .92; transform: translate3d(0, 0, 0) scale(1); }
            78%  { opacity: .85; transform: translate3d(-6vw, -3vh, 0) scale(.95); }
            100% { opacity: 0; transform: translate3d(-24vw, -12vh, 0) scale(.75); }
        }
        /* Suspendido no quiere decir quieto: el colibrí vibra */
        @keyframes suspender {
            0%, 100% { transform: translateY(0) rotate(-3deg); }
            50%      { transform: translateY(-3px) rotate(2deg); }
        }

        /* ── Sección de especies ── */
        .aves { position: relative; overflow: hidden; background: #f4f1e8; }
        .aves-fondo { position: absolute; inset: 0; pointer-events: none; }
        .ave-fondo { position: absolute; display: block; color: var(--bosque); }
        .ave-fondo svg { display: block; width: 100%; height: auto; fill: currentColor; }
        .ave-fondo.a1 { top: 12%; width: 66px; opacity: .07; animation: cruzar 84s linear infinite; }
        .ave-fondo.a2 { top: 68%; width: 44px; opacity: .05; animation: cruzar 112s linear infinite -40s; }

        .ficha-ave {
            display: flex; flex-direction: column; align-items: center; text-align: center;
            background: #fff; border-radius: 1rem; padding: 1.1rem .7rem 1rem; margin: 0;
            box-shadow: 0 4px 18px rgba(31, 77, 54, .07);
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .ficha-ave:hover { transform: translateY(-4px); box-shadow: 0 10px 26px rgba(31, 77, 54, .14); }
        .ficha-ave .silueta { width: 76px; height: 76px; margin-bottom: .7rem; }
        .ficha-ave .silueta svg { width: 100%; height: 100%; fill: var(--tinte); opacity: .9; }
        .ficha-ave:hover .silueta svg { opacity: 1; }
        .ficha-ave .nombre { display: block; font-weight: 600; font-size: .92rem; line-height: 1.25; }
        .ficha-ave .cientifico {
            display: block; font-style: italic; font-size: .78rem;
            color: var(--texto-suave, #6d7a72); margin-top: .2rem;
        }
        .ficha-ave .endemica {
            display: inline-block; margin-top: .5rem; font-size: .68rem; font-weight: 600;
            letter-spacing: .04em; text-transform: uppercase;
            background: rgba(185, 138, 74, .16); color: #8a6427;
            border-radius: 99px; padding: .15rem .55rem;
        }

        /* ── Animación de aparición ── */
        .reveal { opacity: 0; transform: translateY(16px); transition: opacity .55s ease, transform .55s ease; }
        .reveal.visible { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none; }
            .tarjeta-tipo, .tarjeta-tipo:hover { transform: none; transition: none; }

            /* A quien le sienta mal el movimiento, las aves se le quedan
               quietas. No se esconden: se posan. La silueta sigue ahí, que
               es lo que da ambiente; lo que desaparece es el vaivén. */
            .ave, .ave-fondo, .colibri, .colibri .cuerpo,
            .ave .arriba, .ave .abajo { animation: none !important; }
            .ave .arriba { opacity: 0; }
            .colibri { opacity: .85; transform: none; }
            .ficha-ave, .ficha-ave:hover { transform: none; transition: none; }
        }

        /* En pantallas pequeñas, menos aves: ocupan más y la batería importa */
        @media (max-width: 575.98px) {
            .ave.v3, .ave.v4 { display: none; }
            .colibri { width: 36px; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-md navbar-web sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= url_web() ?>">
            <i class="bi bi-tree me-1"></i><?= esc($hotel->nombre) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuWeb"
                aria-label="<?= esc(lang('Web.abrirMenu')) ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuWeb">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-3">
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'inicio' ? 'active' : '' ?>" href="<?= url_web() ?>"><?= esc(lang('Web.inicio')) ?></a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'alojamientos' ? 'active' : '' ?>" href="<?= url_web('alojamientos') ?>"><?= esc(lang('Web.alojamientos')) ?></a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'experiencias' ? 'active' : '' ?>" href="<?= url_web('experiencias-actividades') ?>"><?= esc(lang('Web.experiencias')) ?></a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'carta' ? 'active' : '' ?>" href="<?= url_web('restaurante') ?>"><?= esc(lang('Web.restaurante')) ?></a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'contacto' ? 'active' : '' ?>" href="<?= url_web('contacto') ?>"><?= esc(lang('Web.contacto')) ?></a></li>

                <?php // Selector de idioma: lleva a la misma página, no a la portada ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                       aria-label="<?= esc(lang('Web.cambiarIdioma')) ?>">
                        <i class="bi bi-translate me-1"></i><?= strtoupper(idioma_web()) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php foreach ($idiomas as $i): ?>
                            <li>
                                <a class="dropdown-item <?= $i['activo'] ? 'active' : '' ?>" href="<?= esc($i['url']) ?>"
                                   hreflang="<?= esc($i['html']) ?>" lang="<?= esc($i['html']) ?>">
                                    <span class="me-2"><?= $i['bandera'] ?></span><?= esc($i['nombre']) ?>
                                </a>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </li>

                <?php // Selector de moneda. Solo aparece si hay cambio guardado:
                      // ofrecer euros sin poder convertirlos sería peor que nada. ?>
                <?php $mon = monedas(); ?>
                <?php if ($mon->hayConversion()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                           aria-label="<?= esc(lang('Web.cambiarMoneda')) ?>">
                            <i class="bi bi-cash-coin me-1"></i><?= esc($mon->actual()) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php foreach ($mon->disponibles() as $m): ?>
                                <li>
                                    <a class="dropdown-item <?= $m['activa'] ? 'active' : '' ?>"
                                       href="<?= site_url('moneda/' . $m['codigo']) ?>?volver=<?= urlencode(ruta_actual_web()) ?>">
                                        <span class="me-2"><?= $m['bandera'] ?></span><?= esc($m['nombre']) ?>
                                    </a>
                                </li>
                            <?php endforeach ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><span class="dropdown-item-text small text-muted" style="max-width:230px; white-space:normal;">
                                <?= esc(lang('Web.cobroEnPesos')) ?>
                            </span></li>
                        </ul>
                    </li>
                <?php endif ?>

                <li class="nav-item ms-md-2">
                    <a class="btn btn-reserva btn-sm px-3 py-2" href="<?= url_web('reservar') ?>">
                        <i class="bi bi-calendar-check me-1"></i><?= esc(lang('Web.reservar')) ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?= $this->renderSection('contenido') ?>

<footer class="pt-5 pb-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h2 class="h5 mb-3 titulo-pie"><i class="bi bi-tree me-1"></i><?= esc($hotel->nombre) ?></h2>
                <p class="small mb-0"><?= esc($hotel->eslogan) ?></p>
            </div>
            <div class="col-md-4">
                <h2 class="h6 mb-3 titulo-pie"><?= esc(lang('Web.contacto')) ?></h2>
                <p class="small mb-1"><i class="bi bi-geo-alt me-1"></i><?= esc($hotel->direccion) ?></p>
                <p class="small mb-1"><i class="bi bi-telephone me-1"></i><a href="tel:<?= esc($hotel->telefono) ?>"><?= esc($hotel->telefono) ?></a></p>
                <p class="small mb-0"><i class="bi bi-envelope me-1"></i><a href="mailto:<?= esc($hotel->email) ?>"><?= esc($hotel->email) ?></a></p>
            </div>
            <div class="col-md-4">
                <h2 class="h6 mb-3 titulo-pie"><?= esc(lang('Web.pieReservas')) ?></h2>
                <p class="small mb-2"><?= esc(lang('Web.pieSinComisiones')) ?></p>
                <a class="btn btn-reserva btn-sm px-3" href="<?= url_web('reservar') ?>">
                    <i class="bi bi-calendar-check me-1"></i><?= esc(lang('Web.reservarEnLinea')) ?>
                </a>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <p class="small text-center mb-0">
            &copy; <?= date('Y') ?> <?= esc($hotel->nombre) ?> ·
            <a href="<?= site_url('login') ?>"><?= esc(lang('Web.accesoPersonal')) ?></a>
        </p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Aparición suave de las secciones al hacer scroll
    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observador.unobserve(e.target); } });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => observador.observe(el));

    /* ── El logo cobra vida ──────────────────────────────────────────
       Con ratón basta pasar por encima; sin ratón hay que tocar el botón,
       porque el hover no existe en un teléfono.

       El vídeo pesa 4,9 MB y no se precarga: solo se pide cuando alguien
       muestra interés de verdad. Y ese interés se mide con 220 ms de espera,
       para que cruzar el logo camino del botón de reservar no dispare nada.
    */
    (function () {
        const caja = document.querySelector('.logo-vivo.con-video');
        if (!caja) { return; }

        const video = caja.querySelector('.logo-video');
        const boton = caja.querySelector('.logo-toque');
        const quieto = window.matchMedia('(prefers-reduced-motion: reduce)');
        let espera = null;

        function arrancar(conSonido) {
            if (quieto.matches && !conSonido) { return; }
            video.muted = !conSonido;
            caja.classList.add('animando');
            // play() puede rechazar (política del navegador): no pasa nada,
            // simplemente se queda el póster, que ya es el logo
            const p = video.play();
            if (p && p.catch) { p.catch(() => { video.muted = true; video.play().catch(() => {}); }); }
        }

        function parar() {
            clearTimeout(espera);
            caja.classList.remove('animando');
            video.pause();
            video.currentTime = 0;
            video.muted = true;
        }

        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            caja.addEventListener('mouseenter', () => {
                clearTimeout(espera);
                espera = setTimeout(() => arrancar(false), 220);
            });
            caja.addEventListener('mouseleave', parar);
        }

        // El botón sí lleva sonido: es un gesto deliberado, que es justo lo
        // que los navegadores exigen para dejar sonar algo. Y el sonido del
        // bosque es la mitad de la gracia.
        boton.addEventListener('click', (e) => {
            e.preventDefault();
            if (caja.classList.contains('animando')) { parar(); return; }
            arrancar(true);
        });

        // Al salir de la pantalla se para: nadie quiere un vídeo corriendo
        // por detrás gastando batería
        new IntersectionObserver((ent) => {
            ent.forEach((x) => { if (!x.isIntersecting) { parar(); } });
        }, { threshold: 0 }).observe(caja);
    }());
</script>

<?= view("partes/espera") ?>
</body>
</html>
