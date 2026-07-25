<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'Panel') ?> · <?= esc(config('Hotel')->nombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bosque: #1f4d36;
            --bosque-oscuro: #143425;
            --bosque-claro: #2a5f44;
            --arena: #b98a4a;
        }
        body { min-height: 100vh; background: #f4f6f4; font-family: 'Inter', system-ui, sans-serif; }

        /* ── Menú lateral ── */
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, var(--bosque-oscuro), var(--bosque) 70%); }
        .sidebar .nav-link { color: #c5d6cb; border-radius: .5rem; padding: .55rem .9rem; font-size: .93rem; }
        .sidebar .nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .sidebar .nav-link.active { background: rgba(255,255,255,.14); color: #fff; font-weight: 600; }
        .sidebar .navbar-brand { color: #fff; font-weight: 700; font-size: 1rem; line-height: 1.3; }
        .sidebar .titulo-grupo { color: #86a893; font-size: .7rem; text-transform: uppercase; letter-spacing: .12em; margin: 1.1rem 0 .3rem .9rem; }

        /* ── Barra superior ── */
        .barra-superior { background: #fff; border-bottom: 1px solid #e3e8e3; }

        /* ── Tarjetas y tablas ── */
        .card { border-radius: .8rem; }
        .card-header { border-radius: .8rem .8rem 0 0 !important; }
        .kpi-icono {
            width: 52px; height: 52px; border-radius: .8rem; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .table > :not(caption) > * > * { padding-top: .7rem; padding-bottom: .7rem; }
        .btn-primary { background: var(--bosque); border-color: var(--bosque); }
        .btn-primary:hover, .btn-primary:focus { background: var(--bosque-claro); border-color: var(--bosque-claro); }
        .btn-outline-primary { color: var(--bosque); border-color: var(--bosque); }
        .btn-outline-primary:hover { background: var(--bosque); border-color: var(--bosque); }
        a { color: var(--bosque-claro); }
    </style>
</head>
<body>
<?php
$rol         = session()->get('usuario_rol');
$rolEtiqueta = \App\Models\UsuarioModel::ROLES[$rol] ?? $rol;
$puedeVender = in_array($rol, ['gerencia', 'recepcion'], true);
?>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <a class="navbar-brand d-block mb-3 text-decoration-none" href="<?= site_url('panel') ?>">
                <i class="bi bi-tree me-1"></i><?= esc(config('Hotel')->nombre) ?>
            </a>
            <ul class="nav nav-pills flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'panel' ? 'active' : '' ?>" href="<?= site_url('panel') ?>">
                        <i class="bi bi-speedometer2 me-2"></i>Panel
                    </a>
                </li>
                <?php if ($puedeVender): ?>
                    <div class="titulo-grupo">Recepción</div>
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
                <div class="titulo-grupo">Operación</div>
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'limpieza' ? 'active' : '' ?>" href="<?= site_url('limpieza') ?>">
                        <i class="bi bi-bucket me-2"></i>Limpieza
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($seccion ?? '') === 'unidades' ? 'active' : '' ?>" href="<?= site_url('unidades') ?>">
                        <i class="bi bi-door-open me-2"></i>Cabañas
                    </a>
                </li>
                <?php if ($rol === 'gerencia'): ?>
                    <div class="titulo-grupo">Gerencia</div>
                    <li class="nav-item">
                        <a class="nav-link <?= ($seccion ?? '') === 'reportes' ? 'active' : '' ?>" href="<?= site_url('reportes') ?>">
                            <i class="bi bi-graph-up me-2"></i>Reportes
                        </a>
                    </li>
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
            <div class="mt-4 pt-3 border-top border-light border-opacity-10">
                <a href="<?= site_url('/') ?>" class="nav-link" target="_blank" rel="noopener">
                    <i class="bi bi-globe2 me-2"></i>Ver web pública
                </a>
            </div>
        </nav>

        <div class="col-12 col-md-9 col-lg-10 p-0">
            <div class="barra-superior d-flex justify-content-end align-items-center gap-3 px-4 py-2">
                <a href="<?= site_url('perfil') ?>" class="text-muted small text-decoration-none" title="Mi perfil">
                    <i class="bi bi-person-circle me-1"></i><?= esc(session()->get('usuario_nombre')) ?>
                    <span class="badge text-bg-light border ms-1"><?= esc($rolEtiqueta) ?></span>
                </a>
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
                        <i class="bi bi-check-circle me-1"></i><?= esc(session()->getFlashdata('ok')) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif ?>
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?>
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
