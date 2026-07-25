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
            --ancho-menu: 250px;
        }
        body { min-height: 100vh; background: #f4f6f4; font-family: 'Inter', system-ui, sans-serif; }

        /* ── Menú lateral: fijo en escritorio, desplegable en móvil ── */
        .menu-lateral {
            background: linear-gradient(180deg, var(--bosque-oscuro), var(--bosque) 70%);
            width: var(--ancho-menu);
        }
        .menu-lateral .nav-link { color: #c5d6cb; border-radius: .5rem; padding: .55rem .9rem; font-size: .93rem; }
        .menu-lateral .nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
        .menu-lateral .nav-link.active { background: rgba(255,255,255,.14); color: #fff; font-weight: 600; }
        .menu-lateral .marca { color: #fff; font-weight: 700; font-size: 1rem; line-height: 1.3; }
        .menu-lateral .titulo-grupo {
            color: #86a893; font-size: .7rem; text-transform: uppercase;
            letter-spacing: .12em; margin: 1.1rem 0 .3rem .9rem;
        }
        @media (min-width: 992px) {
            .menu-lateral { position: fixed; top: 0; bottom: 0; left: 0; overflow-y: auto; z-index: 1030; }
            .zona-contenido { margin-left: var(--ancho-menu); }
        }

        /* ── Barra superior ── */
        .barra-superior { background: #fff; border-bottom: 1px solid #e3e8e3; position: sticky; top: 0; z-index: 1020; }

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
        .page-link { color: var(--bosque); }
        .active > .page-link { background: var(--bosque); border-color: var(--bosque); }

        /* En móvil, las acciones de las tablas no se aprietan */
        @media (max-width: 575px) {
            main { padding: 1rem !important; }
            h1.h3 { font-size: 1.35rem; }
        }
    </style>
</head>
<body>
<?php
$rol         = session()->get('usuario_rol');
$rolEtiqueta = \App\Models\UsuarioModel::ROLES[$rol] ?? $rol;
$puedeVender = in_array($rol, ['gerencia', 'recepcion'], true);
$activa      = $seccion ?? '';

// Menú según el rol: [clave, url, icono, etiqueta] agrupados por sección
$grupos = [];
$grupos[''] = [['panel', 'panel', 'bi-speedometer2', 'Panel']];
if ($puedeVender) {
    $grupos['Recepción'] = [
        ['calendario', 'calendario', 'bi-calendar3', 'Calendario'],
        ['reservas', 'reservas', 'bi-calendar-check', 'Reservas'],
        ['huespedes', 'huespedes', 'bi-people', 'Huéspedes'],
        ['registros', 'registros', 'bi-person-vcard', 'Registros de llegada'],
        ['caja', 'caja', 'bi-cash-coin', 'Caja'],
        ['pos', 'pos', 'bi-tablet-landscape', 'TPV táctil'],
        ['cocina', 'cocina', 'bi-fire', 'Cocina'],
        ['barra', 'barra', 'bi-cup-straw', 'Barra'],
        ['tpv', 'tpv', 'bi-cup-hot', 'Restaurante'],
    ];
}
$grupos['Operación'] = [
    ['limpieza', 'limpieza', 'bi-bucket', 'Limpieza'],
    ['mantenimiento', 'mantenimiento', 'bi-tools', 'Mantenimiento'],
    ['unidades', 'unidades', 'bi-door-open', 'Cabañas'],
];
if ($rol === 'gerencia') {
    $grupos['Gerencia'] = [
        ['reportes', 'reportes', 'bi-graph-up', 'Reportes'],
        ['tipos', 'tipos', 'bi-houses', 'Tipos de alojamiento'],
        ['carta', 'carta', 'bi-journal-text', 'Carta del restaurante'],
        ['modificadores', 'modificadores', 'bi-sliders', 'Modificadores'],
        ['insumos', 'insumos', 'bi-box-seam', 'Insumos y costes'],
        ['preparaciones', 'preparaciones', 'bi-stack', 'Preparaciones'],
        ['usuarios', 'usuarios', 'bi-person-gear', 'Usuarios'],
        ['administracion', 'administracion', 'bi-gear', 'Administración'],
    ];
}
?>

<?php
// El mismo menú se usa en el panel lateral fijo y en el desplegable móvil
$pintarMenu = static function () use ($grupos, $activa) { ?>
    <a class="marca d-block mb-3 text-decoration-none" href="<?= site_url('panel') ?>">
        <i class="bi bi-tree me-1"></i><?= esc(config('Hotel')->nombre) ?>
    </a>
    <ul class="nav nav-pills flex-column gap-1">
        <?php foreach ($grupos as $tituloGrupo => $enlaces): ?>
            <?php if ($tituloGrupo !== ''): ?>
                <div class="titulo-grupo"><?= esc($tituloGrupo) ?></div>
            <?php endif ?>
            <?php foreach ($enlaces as [$clave, $ruta, $icono, $etiqueta]): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $activa === $clave ? 'active' : '' ?>" href="<?= site_url($ruta) ?>">
                        <i class="bi <?= $icono ?> me-2"></i><?= esc($etiqueta) ?>
                    </a>
                </li>
            <?php endforeach ?>
        <?php endforeach ?>
    </ul>
    <div class="mt-4 pt-3 border-top border-light border-opacity-10">
        <a href="<?= site_url('/') ?>" class="nav-link" target="_blank" rel="noopener">
            <i class="bi bi-globe2 me-2"></i>Ver web pública
        </a>
    </div>
<?php }; ?>

<!-- Menú fijo (escritorio) -->
<nav class="menu-lateral p-3 d-none d-lg-block"><?php $pintarMenu(); ?></nav>

<!-- Menú desplegable (móvil y tableta) -->
<div class="offcanvas offcanvas-start menu-lateral d-lg-none" tabindex="-1" id="menuMovil" aria-label="Menú de navegación">
    <div class="offcanvas-header pb-0">
        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body pt-0"><?php $pintarMenu(); ?></div>
</div>

<div class="zona-contenido">
    <div class="barra-superior d-flex align-items-center gap-3 px-3 px-md-4 py-2">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#menuMovil" aria-label="Abrir menú">
            <i class="bi bi-list"></i>
        </button>
        <span class="fw-semibold d-lg-none text-truncate"><?= esc($titulo ?? 'Panel') ?></span>

        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="<?= site_url('perfil') ?>" class="text-muted small text-decoration-none text-nowrap" title="Mi perfil">
                <i class="bi bi-person-circle me-1"></i>
                <span class="d-none d-sm-inline"><?= esc(session()->get('usuario_nombre')) ?></span>
                <span class="badge text-bg-light border ms-1"><?= esc($rolEtiqueta) ?></span>
            </a>
            <form action="<?= site_url('logout') ?>" method="post" class="mb-0">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-secondary" title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i><span class="d-none d-md-inline ms-1">Salir</span>
                </button>
            </form>
        </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
