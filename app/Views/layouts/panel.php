<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'Panel') ?> · Hotel Carlos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; background: #f5f6fa; }
        .sidebar { min-height: 100vh; background: #1a3c5e; }
        .sidebar .nav-link { color: #cfd8e3; border-radius: .4rem; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .sidebar .nav-link.active { background: #2e5f8a; color: #fff; }
        .sidebar .navbar-brand { color: #fff; font-weight: 600; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <a class="navbar-brand d-block mb-4 text-decoration-none" href="<?= site_url('panel') ?>">
                <i class="bi bi-building"></i> Hotel Carlos
            </a>
            <ul class="nav nav-pills flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'panel' ? 'active' : '' ?>" href="<?= site_url('panel') ?>">
                        <i class="bi bi-speedometer2 me-2"></i>Panel
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'unidades' ? 'active' : '' ?>" href="<?= site_url('unidades') ?>">
                        <i class="bi bi-door-open me-2"></i>Unidades
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'tipos' ? 'active' : '' ?>" href="<?= site_url('tipos') ?>">
                        <i class="bi bi-houses me-2"></i>Tipos de alojamiento
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'huespedes' ? 'active' : '' ?>" href="<?= site_url('huespedes') ?>">
                        <i class="bi bi-people me-2"></i>Huéspedes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'reservas' ? 'active' : '' ?>" href="<?= site_url('reservas') ?>">
                        <i class="bi bi-calendar-check me-2"></i>Reservas
                    </a>
                </li>
            </ul>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <?php if (session()->getFlashdata('ok')): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= esc(session()->getFlashdata('ok')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= esc(session()->getFlashdata('error')) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif ?>

            <?= $this->renderSection('contenido') ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
