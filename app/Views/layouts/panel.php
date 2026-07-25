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
        .barra-superior { background: #fff; border-bottom: 1px solid #e3e6ec; }
    </style>
</head>
<body>
<?php
$rol        = session()->get('usuario_rol');
$rolEtiqueta = \App\Models\UsuarioModel::ROLES[$rol] ?? $rol;
$puedeVender = in_array($rol, ['gerencia', 'recepcion'], true);
?>
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
                <?php if ($puedeVender): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($seccion ?? '') === 'calendario' ? 'active' : '' ?>" href="<?= site_url('calendario') ?>">
                            <i class="bi bi-calendar3 me-2"></i>Calendario
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($seccion ?? '') === 'reservas' ? 'active' : '' ?>" href="<?= site_url('reservas') ?>">
                            <i class="bi bi-calendar-check me-2"></i>Reservas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($seccion ?? '') === 'huespedes' ? 'active' : '' ?>" href="<?= site_url('huespedes') ?>">
                            <i class="bi bi-people me-2"></i>Huéspedes
                        </a>
                    </li>
                <?php endif ?>
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'unidades' ? 'active' : '' ?>" href="<?= site_url('unidades') ?>">
                        <i class="bi bi-door-open me-2"></i>Unidades
                    </a>
                </li>
                <?php if ($rol === 'gerencia'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($seccion ?? '') === 'tipos' ? 'active' : '' ?>" href="<?= site_url('tipos') ?>">
                            <i class="bi bi-houses me-2"></i>Tipos de alojamiento
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($seccion ?? '') === 'usuarios' ? 'active' : '' ?>" href="<?= site_url('usuarios') ?>">
                            <i class="bi bi-person-gear me-2"></i>Usuarios
                        </a>
                    </li>
                <?php endif ?>
            </ul>
        </nav>

        <div class="col-12 col-md-9 col-lg-10 p-0">
            <div class="barra-superior d-flex justify-content-end align-items-center gap-3 px-4 py-2">
                <span class="text-muted small">
                    <i class="bi bi-person-circle me-1"></i><?= esc(session()->get('usuario_nombre')) ?>
                    <span class="badge text-bg-light border ms-1"><?= esc($rolEtiqueta) ?></span>
                </span>
                <form action="<?= site_url('logout') ?>" method="post" class="mb-0">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-secondary" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right me-1"></i>Salir
                    </button>
                </form>
            </div>

            <main class="p-4">
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
