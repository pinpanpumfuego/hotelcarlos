<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($tituloPagina ?? $hotel->nombre) ?> · <?= esc($hotel->nombre) ?></title>
    <meta name="description" content="<?= esc($descripcion ?? $hotel->eslogan . '. Reserva directa sin comisiones.') ?>">
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

        /* ── Animación de aparición ── */
        .reveal { opacity: 0; transform: translateY(16px); transition: opacity .55s ease, transform .55s ease; }
        .reveal.visible { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none; }
            .tarjeta-tipo, .tarjeta-tipo:hover { transform: none; transition: none; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-md navbar-web sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= site_url('/') ?>">
            <i class="bi bi-tree me-1"></i><?= esc($hotel->nombre) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuWeb" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuWeb">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-3">
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'inicio' ? 'active' : '' ?>" href="<?= site_url('/') ?>">Inicio</a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'alojamientos' ? 'active' : '' ?>" href="<?= site_url('alojamientos') ?>">Alojamientos</a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'experiencias' ? 'active' : '' ?>" href="<?= site_url('experiencias-actividades') ?>">Experiencias</a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'carta' ? 'active' : '' ?>" href="<?= site_url('restaurante') ?>">Restaurante</a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'contacto' ? 'active' : '' ?>" href="<?= site_url('contacto') ?>">Contacto</a></li>
                <li class="nav-item ms-md-2">
                    <a class="btn btn-reserva btn-sm px-3 py-2" href="<?= site_url('reservar') ?>">
                        <i class="bi bi-calendar-check me-1"></i>Reservar
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
                <h2 class="h6 mb-3 titulo-pie">Contacto</h2>
                <p class="small mb-1"><i class="bi bi-geo-alt me-1"></i><?= esc($hotel->direccion) ?></p>
                <p class="small mb-1"><i class="bi bi-telephone me-1"></i><a href="tel:<?= esc($hotel->telefono) ?>"><?= esc($hotel->telefono) ?></a></p>
                <p class="small mb-0"><i class="bi bi-envelope me-1"></i><a href="mailto:<?= esc($hotel->email) ?>"><?= esc($hotel->email) ?></a></p>
            </div>
            <div class="col-md-4">
                <h2 class="h6 mb-3 titulo-pie">Reservas</h2>
                <p class="small mb-2">Reserva directa, sin comisiones de intermediarios.</p>
                <a class="btn btn-reserva btn-sm px-3" href="<?= site_url('reservar') ?>">
                    <i class="bi bi-calendar-check me-1"></i>Reservar en línea
                </a>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <p class="small text-center mb-0">&copy; <?= date('Y') ?> <?= esc($hotel->nombre) ?> · <a href="<?= site_url('login') ?>">Acceso personal</a></p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Aparición suave de las secciones al hacer scroll
    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); observador.unobserve(e.target); } });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => observador.observe(el));
</script>
</body>
</html>
