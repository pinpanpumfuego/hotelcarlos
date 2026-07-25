<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'Panel') ?> · <?= esc(config('Hotel')->nombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* ═══════════ Sistema de diseño ═══════════ */
        :root {
            /* Marca */
            --bosque: #1f4d36;
            --bosque-oscuro: #143425;
            --bosque-claro: #2a5f44;
            --arena: #9d6220;
            --arena-clara: #b9873f;

            /* Superficies */
            --fondo: #f2f5f2;
            --panel: #ffffff;
            --panel-tenue: #f7f9f7;
            --borde: #e2e8e3;
            --borde-fuerte: #cfd8d1;

            /* Texto */
            --tinta: #1c2a23;
            --tinta-media: #4a5a51;
            --tinta-suave: #7b8a81;

            /* Sombras: sutiles, con el verde de la marca */
            --sombra-1: 0 1px 2px rgba(28,42,35,.06), 0 1px 3px rgba(28,42,35,.04);
            --sombra-2: 0 2px 4px rgba(28,42,35,.06), 0 4px 12px rgba(28,42,35,.06);
            --sombra-3: 0 8px 24px rgba(28,42,35,.10);

            --radio: .75rem;
            --radio-sm: .5rem;
            --ancho-menu: 252px;
        }

        body {
            min-height: 100vh; background: var(--fondo); color: var(--tinta);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            font-size: .95rem; -webkit-font-smoothing: antialiased;
        }

        /* Tipografía editorial en los títulos, igual que la web pública */
        h1, h2, h3, .fuente-titulo {
            font-family: 'Fraunces', Georgia, serif; font-weight: 600;
            letter-spacing: -.01em; color: var(--tinta);
        }
        h1.h3 { font-size: 1.6rem; }

        /* ═══════════ Menú lateral ═══════════ */
        .menu-lateral {
            background: linear-gradient(170deg, var(--bosque-oscuro) 0%, var(--bosque) 60%, #26593f 100%);
            width: var(--ancho-menu);
        }
        .menu-lateral .nav-link {
            color: #b9cec1; border-radius: var(--radio-sm); padding: .6rem .85rem;
            font-size: .9rem; font-weight: 500; position: relative;
            transition: background .16s ease, color .16s ease, padding-left .16s ease;
        }
        .menu-lateral .nav-link i { opacity: .8; transition: opacity .16s ease; }
        .menu-lateral .nav-link:hover { background: rgba(255,255,255,.07); color: #fff; padding-left: 1.05rem; }
        .menu-lateral .nav-link:hover i { opacity: 1; }
        .menu-lateral .nav-link.active {
            background: rgba(255,255,255,.13); color: #fff; font-weight: 600;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.07);
        }
        /* Marca de sección activa */
        .menu-lateral .nav-link.active::before {
            content: ''; position: absolute; left: -12px; top: 50%; transform: translateY(-50%);
            width: 4px; height: 22px; border-radius: 0 4px 4px 0; background: var(--arena-clara);
        }
        .menu-lateral .nav-link.active i { opacity: 1; color: #e3c9a4; }
        .menu-lateral .marca {
            color: #fff; font-family: 'Fraunces', serif; font-weight: 600;
            font-size: 1.02rem; line-height: 1.3;
        }
        .menu-lateral .marca .lugar { display: block; font-family: 'Inter', sans-serif;
            font-size: .68rem; font-weight: 500; letter-spacing: .14em;
            text-transform: uppercase; color: #86a893; margin-top: 3px; }
        .menu-lateral .titulo-grupo {
            color: #7fa48d; font-size: .67rem; text-transform: uppercase;
            letter-spacing: .14em; font-weight: 600; margin: 1.15rem 0 .35rem .85rem;
        }
        @media (min-width: 992px) {
            .menu-lateral { position: fixed; top: 0; bottom: 0; left: 0; overflow-y: auto; z-index: 1030; }
            .zona-contenido { margin-left: var(--ancho-menu); }
        }

        /* ═══════════ Barra superior ═══════════ */
        .barra-superior {
            background: rgba(255,255,255,.88); backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--borde); position: sticky; top: 0; z-index: 1020;
        }
        .chip-usuario {
            display: inline-flex; align-items: center; gap: .5rem; padding: .35rem .7rem;
            border-radius: 99px; border: 1px solid var(--borde); background: var(--panel-tenue);
            color: var(--tinta-media); text-decoration: none; font-size: .85rem;
            transition: border-color .16s ease, background .16s ease;
        }
        .chip-usuario:hover { border-color: var(--borde-fuerte); background: #fff; color: var(--tinta); }
        .avatar {
            width: 26px; height: 26px; border-radius: 50%; background: var(--bosque);
            color: #fff; display: inline-flex; align-items: center; justify-content: center;
            font-size: .72rem; font-weight: 700; font-family: 'Inter', sans-serif;
        }

        /* ═══════════ Tarjetas ═══════════ */
        .card {
            border-radius: var(--radio); border: 1px solid var(--borde);
            box-shadow: var(--sombra-1); transition: box-shadow .18s ease;
        }
        .card.shadow-sm { box-shadow: var(--sombra-1) !important; }
        .card-header {
            border-radius: var(--radio) var(--radio) 0 0 !important;
            border-bottom: 1px solid var(--borde); font-size: .92rem;
            padding: .85rem 1.1rem; letter-spacing: -.005em;
        }
        .card-header.bg-white { background: var(--panel-tenue) !important; }
        .card-footer { border-top: 1px solid var(--borde); }

        /* Tarjetas con acción: reaccionan al pasar el ratón */
        a > .card, .card.interactiva { transition: transform .18s ease, box-shadow .18s ease; }
        a:hover > .card, .card.interactiva:hover { transform: translateY(-2px); box-shadow: var(--sombra-2); }

        /* ═══════════ Indicadores ═══════════ */
        .kpi-icono {
            width: 50px; height: 50px; border-radius: .7rem; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .kpi .valor { font-family: 'Fraunces', serif; font-weight: 600; line-height: 1.1; }

        /* ═══════════ Tablas ═══════════ */
        .table { --bs-table-border-color: var(--borde); margin-bottom: 0; }
        .table > thead th {
            font-size: .74rem; text-transform: uppercase; letter-spacing: .07em;
            font-weight: 600; color: var(--tinta-suave); background: var(--panel-tenue);
            border-bottom: 1px solid var(--borde); padding: .7rem 1.1rem; white-space: nowrap;
        }
        .table > tbody > tr > td { padding: .8rem 1.1rem; vertical-align: middle; }
        .table > tbody > tr:last-child > td { border-bottom: 0; }
        .table-hover > tbody > tr:hover > * { background: var(--panel-tenue); }

        /* ═══════════ Botones ═══════════ */
        .btn { border-radius: var(--radio-sm); font-weight: 500; transition: all .15s ease; }
        .btn-lg { border-radius: .65rem; }
        .btn-primary { background: var(--bosque); border-color: var(--bosque); }
        .btn-primary:hover, .btn-primary:focus {
            background: var(--bosque-claro); border-color: var(--bosque-claro);
            box-shadow: 0 3px 10px rgba(31,77,54,.24);
        }
        .btn-outline-primary { color: var(--bosque); border-color: var(--borde-fuerte); }
        .btn-outline-primary:hover { background: var(--bosque); border-color: var(--bosque); }
        .btn-outline-secondary { border-color: var(--borde-fuerte); color: var(--tinta-media); }
        .btn-sm { padding: .28rem .6rem; }

        /* ═══════════ Formularios ═══════════ */
        .form-control, .form-select {
            border-radius: var(--radio-sm); border-color: var(--borde-fuerte);
            padding: .55rem .8rem; font-size: .93rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--bosque-claro); box-shadow: 0 0 0 3px rgba(31,77,54,.10);
        }
        .form-label { font-size: .85rem; color: var(--tinta-media); margin-bottom: .3rem; }
        .form-text { font-size: .8rem; color: var(--tinta-suave); }
        .input-group-text { background: var(--panel-tenue); border-color: var(--borde-fuerte); font-size: .9rem; }
        .form-check-input:checked { background-color: var(--bosque); border-color: var(--bosque); }

        /* Botones de opción tipo tarjeta (estado, rol…) */
        .btn-check + .btn { text-align: left; border-color: var(--borde-fuerte); }
        .btn-check:checked + .btn { box-shadow: 0 0 0 2px currentColor inset; }

        /* ═══════════ Insignias y avisos ═══════════ */
        .badge { font-weight: 600; letter-spacing: .01em; padding: .34em .6em; border-radius: 99px; }
        .alert { border-radius: var(--radio); border: 1px solid transparent; }
        .alert-success { background: #e9f4ee; border-color: #c6e2d2; color: #1c5c3c; }
        .alert-danger { background: #fbecec; border-color: #f0cdcf; color: #8f3237; }
        .alert-warning { background: #fdf5e7; border-color: #f2e0bd; color: #7d5a1c; }
        .alert-info { background: #e9f1f6; border-color: #c9dfec; color: #1f5875; }
        .alert-light { background: var(--panel-tenue); border-color: var(--borde); color: var(--tinta-media); }

        /* ═══════════ Paginación ═══════════ */
        .page-link { color: var(--bosque); border-color: var(--borde); }
        .page-link:hover { background: var(--panel-tenue); color: var(--bosque-oscuro); }
        .active > .page-link { background: var(--bosque); border-color: var(--bosque); }

        a { color: var(--bosque-claro); text-underline-offset: 2px; }
        a:hover { color: var(--bosque-oscuro); }
        .text-muted { color: var(--tinta-suave) !important; }

        /* ═══════════ Entrada suave del contenido ═══════════ */
        main > * { animation: aparecer .3s ease both; }
        @keyframes aparecer { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) {
            *, main > * { animation: none !important; transition: none !important; }
            a:hover > .card, .card.interactiva:hover { transform: none; }
        }

        @media (max-width: 575px) {
            main { padding: 1rem !important; }
            h1.h3 { font-size: 1.3rem; }
            .card-header, .table > thead th, .table > tbody > tr > td { padding-left: .8rem; padding-right: .8rem; }
        }
    </style>
</head>
<body>
<?php
$rol         = session()->get('usuario_rol');
$rolEtiqueta = \App\Models\UsuarioModel::ROLES[$rol] ?? $rol;
$puedeVender = in_array($rol, ['gerencia', 'recepcion'], true);
$activa      = $seccion ?? '';
$nombreUsuario = (string) session()->get('usuario_nombre');
$iniciales   = mb_strtoupper(mb_substr($nombreUsuario, 0, 1));

// Avisos que se muestran como insignia en el menú
$porRevisar = 0;
if ($puedeVender) {
    try { $porRevisar = (new \App\Models\RegistroModel())->pendientesDeRevision(); } catch (\Throwable $e) { $porRevisar = 0; }
}

// Menú según el rol: [clave, url, icono, etiqueta, insignia]
$grupos = [];
$grupos[''] = [['panel', 'panel', 'bi-speedometer2', 'Panel', 0]];
if ($puedeVender) {
    $grupos['Recepción'] = [
        ['calendario', 'calendario', 'bi-calendar3', 'Calendario', 0],
        ['reservas', 'reservas', 'bi-calendar-check', 'Reservas', 0],
        ['huespedes', 'huespedes', 'bi-people', 'Huéspedes', 0],
        ['registros', 'registros', 'bi-person-vcard', 'Registros de llegada', $porRevisar],
        ['caja', 'caja', 'bi-cash-coin', 'Caja', 0],
        ['pos', 'pos', 'bi-tablet-landscape', 'TPV táctil', 0],
        ['cocina', 'cocina', 'bi-fire', 'Cocina', 0],
        ['barra', 'barra', 'bi-cup-straw', 'Barra', 0],
        ['tpv', 'tpv', 'bi-cup-hot', 'Restaurante', 0],
    ];
}
$grupos['Operación'] = [
    ['limpieza', 'limpieza', 'bi-bucket', 'Limpieza', 0],
    ['mantenimiento', 'mantenimiento', 'bi-tools', 'Mantenimiento', 0],
    ['unidades', 'unidades', 'bi-door-open', 'Cabañas', 0],
];
if ($rol === 'gerencia') {
    $grupos['Gerencia'] = [
        ['reportes', 'reportes', 'bi-graph-up', 'Reportes', 0],
        ['tipos', 'tipos', 'bi-houses', 'Tipos de alojamiento', 0],
        ['carta', 'carta', 'bi-journal-text', 'Carta del restaurante', 0],
        ['modificadores', 'modificadores', 'bi-sliders', 'Modificadores', 0],
        ['insumos', 'insumos', 'bi-box-seam', 'Insumos y costes', 0],
        ['preparaciones', 'preparaciones', 'bi-stack', 'Preparaciones', 0],
        ['usuarios', 'usuarios', 'bi-person-gear', 'Usuarios', 0],
        ['administracion', 'administracion', 'bi-gear', 'Administración', 0],
    ];
}

// El mismo menú se pinta en el panel fijo y en el desplegable móvil
$pintarMenu = static function () use ($grupos, $activa) { ?>
    <a class="marca d-block mb-3 text-decoration-none" href="<?= site_url('panel') ?>">
        <i class="bi bi-tree me-1"></i><?= esc(config('Hotel')->nombre) ?>
        <span class="lugar">Sistema de gestión</span>
    </a>
    <ul class="nav nav-pills flex-column gap-1">
        <?php foreach ($grupos as $tituloGrupo => $enlaces): ?>
            <?php if ($tituloGrupo !== ''): ?>
                <div class="titulo-grupo"><?= esc($tituloGrupo) ?></div>
            <?php endif ?>
            <?php foreach ($enlaces as [$clave, $ruta, $icono, $etiqueta, $insignia]): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?= $activa === $clave ? 'active' : '' ?>" href="<?= site_url($ruta) ?>">
                        <i class="bi <?= $icono ?> me-2"></i><?= esc($etiqueta) ?>
                        <?php if ($insignia > 0): ?>
                            <span class="badge text-bg-warning ms-auto"><?= $insignia ?></span>
                        <?php endif ?>
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

        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="<?= site_url('perfil') ?>" class="chip-usuario" title="Mi perfil">
                <span class="avatar"><?= esc($iniciales) ?></span>
                <span class="d-none d-sm-inline"><?= esc($nombreUsuario) ?></span>
                <span class="badge text-bg-light border fw-normal"><?= esc($rolEtiqueta) ?></span>
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
            <div class="alert alert-success alert-dismissible fade show d-flex gap-2">
                <i class="bi bi-check-circle-fill mt-1"></i>
                <div><?= esc(session()->getFlashdata('ok')) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex gap-2">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div><?= esc(session()->getFlashdata('error')) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif ?>

        <?= $this->renderSection('contenido') ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
