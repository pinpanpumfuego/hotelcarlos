<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($tituloPagina ?? $hotel->nombre) ?> · <?= esc($hotel->nombre) ?></title>
    <meta name="description" content="<?= esc($descripcion ?? $hotel->eslogan . '. Reserva directa sin comisiones.') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --verde-bosque: #24513a;
            --verde-claro: #eaf3ee;
            --tierra: #8a6d4b;
            --crema: #faf7f2;
        }
        body { font-family: Georgia, 'Times New Roman', serif; color: #2c2c2c; background: var(--crema); }
        .navbar-web { background: rgba(36, 81, 58, .96); }
        .navbar-web .nav-link, .navbar-web .navbar-brand { color: #f0ece4 !important; }
        .navbar-web .nav-link.active { text-decoration: underline; text-underline-offset: 6px; }
        .hero {
            min-height: 70vh;
            background: linear-gradient(rgba(20, 45, 32, .45), rgba(20, 45, 32, .55)),
                        radial-gradient(circle at 30% 40%, #4f8a68 0%, #24513a 55%, #16331f 100%);
            display: flex; align-items: center; color: #fff;
        }
        .hero h1 { font-size: clamp(2rem, 5vw, 3.4rem); }
        .btn-reserva { background: var(--tierra); border: none; color: #fff; }
        .btn-reserva:hover { background: #6f5539; color: #fff; }
        .tarjeta-tipo { border: none; border-radius: 1rem; overflow: hidden; transition: transform .15s ease; }
        .tarjeta-tipo:hover { transform: translateY(-4px); }
        .cabecera-tipo { height: 160px; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #fff; }
        footer { background: #16331f; color: #cfe0d6; }
        footer a { color: #cfe0d6; }
        .seccion { padding: 4rem 0; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-md navbar-web sticky-top">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= site_url('/') ?>">
            <i class="bi bi-tree me-1"></i><?= esc($hotel->nombre) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuWeb" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menuWeb">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'inicio' ? 'active' : '' ?>" href="<?= site_url('/') ?>">Inicio</a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'alojamientos' ? 'active' : '' ?>" href="<?= site_url('alojamientos') ?>">Alojamientos</a></li>
                <li class="nav-item"><a class="nav-link <?= ($paginaActiva ?? '') === 'contacto' ? 'active' : '' ?>" href="<?= site_url('contacto') ?>">Contacto</a></li>
                <li class="nav-item ms-md-2">
                    <a class="btn btn-reserva btn-sm px-3" href="https://wa.me/<?= esc($hotel->whatsapp) ?>?text=Hola,%20quiero%20reservar" target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp me-1"></i>Reservar
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?= $this->renderSection('contenido') ?>

<footer class="pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h2 class="h5 mb-3"><i class="bi bi-tree me-1"></i><?= esc($hotel->nombre) ?></h2>
                <p class="small mb-0"><?= esc($hotel->eslogan) ?></p>
            </div>
            <div class="col-md-4">
                <h2 class="h6 mb-3">Contacto</h2>
                <p class="small mb-1"><i class="bi bi-geo-alt me-1"></i><?= esc($hotel->direccion) ?></p>
                <p class="small mb-1"><i class="bi bi-telephone me-1"></i><a href="tel:<?= esc($hotel->telefono) ?>"><?= esc($hotel->telefono) ?></a></p>
                <p class="small mb-0"><i class="bi bi-envelope me-1"></i><a href="mailto:<?= esc($hotel->email) ?>"><?= esc($hotel->email) ?></a></p>
            </div>
            <div class="col-md-4">
                <h2 class="h6 mb-3">Reservas</h2>
                <p class="small mb-2">Reserva directa, sin comisiones de intermediarios.</p>
                <a class="btn btn-reserva btn-sm px-3" href="https://wa.me/<?= esc($hotel->whatsapp) ?>?text=Hola,%20quiero%20reservar" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp me-1"></i>Escríbenos por WhatsApp
                </a>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <p class="small text-center mb-0">&copy; <?= date('Y') ?> <?= esc($hotel->nombre) ?> · <a href="<?= site_url('login') ?>">Acceso personal</a></p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
