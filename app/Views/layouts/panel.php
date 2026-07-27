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
        /* En táctil no hay ratón: sin este @media el elemento se queda
           «pegado» en estado hover después de tocarlo. */
        @media (hover: hover) {
            .menu-lateral .nav-link:hover { background: rgba(255,255,255,.07); color: #fff; padding-left: 1.05rem; }
            .menu-lateral .nav-link:hover i { opacity: 1; }
        }
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
        /* Cabecera de grupo plegable */
        .menu-lateral .grupo-btn {
            display: flex; align-items: center; width: 100%;
            background: transparent; border: 0; color: #9dbcaa;
            font-size: .69rem; text-transform: uppercase; letter-spacing: .13em;
            font-weight: 700; padding: .55rem .85rem .35rem; margin-top: .5rem;
            border-radius: var(--radio-sm); transition: color .16s ease, background .16s ease;
        }
        @media (hover: hover) {
            .menu-lateral .grupo-btn:hover { color: #fff; background: rgba(255,255,255,.05); }
        }
        .menu-lateral .grupo-btn .flecha {
            font-size: .8rem; transition: transform .2s ease; opacity: .7;
        }
        .menu-lateral .grupo-btn.collapsed .flecha { transform: rotate(-90deg); }
        .menu-lateral .grupo-btn .badge { font-size: .62rem; }
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
        @media (hover: hover) {
            .chip-usuario:hover { border-color: var(--borde-fuerte); background: #fff; color: var(--tinta); }
        }
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
        @media (hover: hover) {
            a:hover > .card, .card.interactiva:hover { transform: translateY(-2px); box-shadow: var(--sombra-2); }
        }

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
        @media (hover: hover) {
            .table-hover > tbody > tr:hover > * { background: var(--panel-tenue); }
        }

        /* ═══════════ Botones ═══════════ */
        .btn { border-radius: var(--radio-sm); font-weight: 500; transition: all .15s ease; }
        .btn-lg { border-radius: .65rem; }
        .btn-primary { background: var(--bosque); border-color: var(--bosque); }
        /* El :focus se queda fuera del @media: hace falta para el teclado */
        .btn-primary:focus {
            background: var(--bosque-claro); border-color: var(--bosque-claro);
            box-shadow: 0 3px 10px rgba(31,77,54,.24);
        }
        .btn-outline-primary { color: var(--bosque); border-color: var(--borde-fuerte); }
        @media (hover: hover) {
            .btn-primary:hover {
                background: var(--bosque-claro); border-color: var(--bosque-claro);
                box-shadow: 0 3px 10px rgba(31,77,54,.24);
            }
            .btn-outline-primary:hover { background: var(--bosque); border-color: var(--bosque); }
        }
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
        .active > .page-link { background: var(--bosque); border-color: var(--bosque); }

        a { color: var(--bosque-claro); text-underline-offset: 2px; }
        @media (hover: hover) {
            .page-link:hover { background: var(--panel-tenue); color: var(--bosque-oscuro); }
            a:hover { color: var(--bosque-oscuro); }
        }
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

        /* ═══════════ Modo táctil ═══════════
           Se activa por dispositivo, no por hotel: el mismo sistema lo abre
           una tableta en recepción y un portátil en la oficina. Objetivo
           mínimo 44 px, que es lo que recomienda Apple y algo menos que una
           yema (45-57 mm según el MIT Touch Lab). */
        html.tactil {
            --alto-toque: 44px;
        }
        html.tactil body { font-size: 1rem; }

        html.tactil .btn,
        html.tactil .form-control,
        html.tactil .form-select,
        html.tactil .input-group-text,
        html.tactil .page-link {
            min-height: var(--alto-toque);
            display: inline-flex; align-items: center; justify-content: center;
        }
        html.tactil .form-control,
        html.tactil .form-select { display: block; padding-top: .6rem; padding-bottom: .6rem; }
        html.tactil textarea.form-control { display: block; }

        html.tactil .btn-sm {
            min-width: var(--alto-toque); min-height: var(--alto-toque);
            padding: .45rem .8rem; font-size: .92rem;
        }

        /* Los botones de acción de las tablas iban pegados: separarlos evita
           tocar «Eliminar» queriendo tocar «Editar». */
        html.tactil td .d-inline-flex.gap-1,
        html.tactil td .d-inline-flex.gap-2 { gap: .6rem !important; }
        html.tactil .table > tbody > tr > td { padding-top: 1rem; padding-bottom: 1rem; }
        html.tactil .table > thead th { padding-top: .95rem; padding-bottom: .95rem; }

        /* Sin ratón no hay tooltip: el título del botón se muestra al lado.
           Solo en los que no tienen ya texto propio. */
        html.tactil .btn[title]:has(> i:only-child)::after {
            content: attr(title);
            font-size: .78rem; font-weight: 500; margin-left: .45rem;
            white-space: nowrap;
        }
        html.tactil .btn[title]:has(> i:only-child) { min-width: 0; padding-inline: .7rem; }

        /* Casillas e interruptores del tamaño de un dedo */
        html.tactil .form-check-input { width: 1.35rem; height: 1.35rem; margin-top: .1rem; }
        html.tactil .form-check-input[type="checkbox"].form-check-input { border-width: 2px; }
        html.tactil .form-check { padding-left: 2rem; min-height: var(--alto-toque); }
        html.tactil .form-switch .form-check-input { width: 2.6rem; height: 1.35rem; }

        html.tactil .nav-link { padding: .8rem .85rem; }
        html.tactil .list-group-item { padding-top: .9rem; padding-bottom: .9rem; }
        html.tactil .btn-close { width: 1.4rem; height: 1.4rem; padding: .6rem; }
        html.tactil .modal-footer .btn { min-width: 110px; }

        /* Tocar y arrastrar para desplazar, sin selección accidental de texto */
        html.tactil .table-responsive { -webkit-overflow-scrolling: touch; }
        html.tactil .btn, html.tactil .nav-link, html.tactil .grupo-btn {
            -webkit-user-select: none; user-select: none;
            -webkit-tap-highlight-color: rgba(31,77,54,.12);
        }

        /* El conmutador de la barra superior */
        .btn-tactil {
            display: inline-flex; align-items: center; gap: .35rem;
            border: 1px solid var(--borde); background: var(--panel-tenue);
            color: var(--tinta-media); border-radius: 99px;
            padding: .3rem .7rem; font-size: .82rem; cursor: pointer;
        }
        .btn-tactil.activo { background: #e9f4ee; border-color: #c6e2d2; color: #1c5c3c; }
        html.tactil .btn-tactil { min-height: var(--alto-toque); padding-inline: .9rem; }
    </style>

    <script>
        /*
         * Modo táctil. Se decide antes de pintar nada para que la página no
         * aparezca primero pequeña y luego dé un salto.
         *
         * La preferencia es de este dispositivo (localStorage), no del hotel:
         * la misma instalación la abre una tableta en recepción y un portátil
         * en la oficina, y cada uno debe quedarse como le va bien.
         */
        (function () {
            var guardado = null;
            try { guardado = localStorage.getItem('tactil'); } catch (e) { /* modo privado */ }

            // Sin preferencia: se mira si el puntero principal es un dedo
            var grueso = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
            var activo = guardado === null ? grueso : guardado === '1';

            if (activo) { document.documentElement.classList.add('tactil'); }
        })();
    </script>
</head>
<body>
<?php
$rol = session()->get('usuario_rol');

// El nombre del perfil sale de `roles`; si el usuario aún no tiene perfil
// asignado se cae al rol antiguo, para no dejar la insignia en blanco.
$rolEtiqueta = \App\Models\UsuarioModel::ROLES[$rol] ?? $rol;
if (session()->get('usuario_rol_id') !== null) {
    $fila = (new \App\Models\RolModel())->find((int) session()->get('usuario_rol_id'));
    if ($fila !== null) {
        $rolEtiqueta = $fila['nombre'];
    }
}

$activa        = $seccion ?? '';
$nombreUsuario = (string) session()->get('usuario_nombre');
$iniciales     = mb_strtoupper(mb_substr($nombreUsuario, 0, 1));

// Avisos que se muestran como insignia en el menú
$porAtender = 0;
if (puede_alguno(['limpieza.ver', 'mantenimiento.ver'])) {
    try { $porAtender = (new \App\Models\SolicitudModel())->sinAtender(); } catch (\Throwable $e) { $porAtender = 0; }
}

$porRevisar = 0;
if (puede('registros.ver')) {
    try { $porRevisar = (new \App\Models\RegistroModel())->pendientesDeRevision(); } catch (\Throwable $e) { $porRevisar = 0; }
}

/**
 * Menú agrupado por áreas de trabajo.
 * Cada grupo: icono, y enlaces [clave, ruta, icono, etiqueta, insignia].
 * Los grupos se pliegan; se abre solo aquel en el que estás.
 */
$grupos = [];

// Cada enlace se pinta solo si su permiso lo consiente. Lo que no se puede
// hacer no se enseña: un enlace que rebota parece una avería del sistema,
// mientras que uno que no está es simplemente trabajo de otra persona.
$soloPermitidos = static function (array $enlaces): array {
    return array_values(array_filter(
        $enlaces,
        static fn (array $e): bool => puede_alguno((array) $e[5])
    ));
};

// [clave, ruta, icono, etiqueta, insignia, permisos que lo abren]
$recepcion = $soloPermitidos([
    ['calendario', 'calendario', 'bi-calendar3', 'Calendario', 0, ['calendario.ver', 'calendario.ocupacion']],
    ['reservas', 'reservas', 'bi-calendar-check', 'Reservas', 0, ['reservas.ver']],
    ['huespedes', 'huespedes', 'bi-people', 'Huéspedes', 0, ['huespedes.ver']],
    ['registros', 'registros', 'bi-person-vcard', 'Registros de llegada', $porRevisar, ['registros.ver']],
    ['solicitudes', 'solicitudes', 'bi-hand-index', 'Peticiones de huéspedes', $porAtender, ['limpieza.ver', 'mantenimiento.ver']],
    ['experiencias', 'experiencias', 'bi-compass', 'Experiencias', 0, ['experiencias.vender']],
    ['caja', 'caja', 'bi-cash-coin', 'Caja', 0, ['caja.ver']],
    ['bonos', 'bonos', 'bi-gift', 'Bonos regalo', 0, ['folio.bono', 'bonos.gestionar']],
]);
if ($recepcion !== []) {
    $grupos['Recepción'] = ['icono' => 'bi-bell', 'enlaces' => $recepcion];
}

$restaurante = $soloPermitidos([
    ['pos', 'pos', 'bi-tablet-landscape', 'TPV táctil', 0, ['pos.usar']],
    ['cocina', 'cocina', 'bi-fire', 'Cocina', 0, ['cocina.ver']],
    ['barra', 'barra', 'bi-cup-straw', 'Barra', 0, ['barra.ver']],
    ['tpv', 'tpv', 'bi-receipt', 'Comandas', 0, ['pos.usar']],
    ['carta', 'carta', 'bi-journal-text', 'Carta', 0, ['carta.gestionar']],
    ['modificadores', 'modificadores', 'bi-sliders', 'Modificadores', 0, ['carta.gestionar']],
    ['insumos', 'insumos', 'bi-box-seam', 'Insumos y costes', 0, ['insumos.gestionar']],
    ['almacen', 'almacen', 'bi-boxes', 'Almacén', 0, ['insumos.gestionar']],
    ['preparaciones', 'preparaciones', 'bi-stack', 'Preparaciones', 0, ['insumos.gestionar']],
]);
if ($restaurante !== []) {
    $grupos['Restaurante'] = ['icono' => 'bi-cup-hot', 'enlaces' => $restaurante];
}

$operacion = $soloPermitidos([
    ['limpieza', 'limpieza', 'bi-bucket', 'Limpieza', 0, ['limpieza.ver']],
    ['mantenimiento', 'mantenimiento', 'bi-wrench-adjustable', 'Mantenimiento', 0, ['mantenimiento.ver']],
    ['unidades', 'unidades', 'bi-door-open', 'Cabañas', 0, ['unidades.estado', 'unidades.gestionar']],
]);
if ($operacion !== []) {
    $grupos['Operación'] = ['icono' => 'bi-tools', 'enlaces' => $operacion];
}

$equipo = $soloPermitidos([
    ['personal', 'personal', 'bi-person-badge', 'Fichas del personal', 0, ['personal.ver']],
    ['fichajes', 'fichajes', 'bi-clock-history', 'Control de jornada', 0, ['fichajes.ver']],
    ['turnos', 'turnos', 'bi-calendar-week', 'Turnos', 0, ['turnos.ver']],
    ['ausencias', 'ausencias', 'bi-calendar-x', 'Ausencias', 0, ['ausencias.gestionar']],
    ['propinas', 'propinas', 'bi-cash-stack', 'Propinas', 0, ['propinas.liquidar']],
]);
if ($equipo !== []) {
    $grupos['Equipo'] = ['icono' => 'bi-people-fill', 'enlaces' => $equipo];
}

$gestion = $soloPermitidos([
    ['reportes', 'reportes', 'bi-graph-up', 'Reportes', 0, ['reportes.ocupacion', 'reportes.ingresos']],
    ['facturas', 'facturas', 'bi-receipt-cutoff', 'Facturación', 0, ['facturas.ver']],
    ['tarifas', 'tarifas', 'bi-tags', 'Tarifas', 0, ['tarifas.ver']],
    ['planes', 'planes', 'bi-basket', 'Planes incluidos', 0, ['tarifas.ver']],
    ['canales', 'canales', 'bi-diagram-3', 'Portales y canales', 0, ['canales.gestionar']],
    ['traducciones', 'traducciones', 'bi-translate', 'Traducciones', 0, ['traducciones.gestionar']],
    ['cupones', 'cupones', 'bi-ticket-perforated', 'Cupones', 0, ['cupones.gestionar']],
    ['tipos', 'tipos', 'bi-houses', 'Tipos de alojamiento', 0, ['tipos.gestionar']],
    ['servicios', 'servicios', 'bi-stars', 'Servicios', 0, ['servicios.gestionar']],
    ['inventario-cabanas', 'inventario-cabanas', 'bi-box-seam', 'Enseres de cabañas', 0, ['servicios.gestionar']],
    ['usuarios', 'usuarios', 'bi-person-gear', 'Usuarios', 0, ['usuarios.gestionar']],
    ['roles', 'roles', 'bi-shield-lock', 'Perfiles de acceso', 0, ['roles.gestionar']],
    ['auditoria', 'auditoria', 'bi-clipboard-check', 'Auditoría', 0, ['auditoria.ver']],
    ['administracion', 'administracion', 'bi-gear', 'Administración', 0, ['administracion.ver']],
]);
if ($gestion !== []) {
    $grupos['Gestión'] = ['icono' => 'bi-briefcase', 'enlaces' => $gestion];
}

// Grupo que contiene la sección abierta: es el que se muestra desplegado
$grupoActivo = '';
foreach ($grupos as $titulo => $g) {
    foreach ($g['enlaces'] as $e) {
        if ($e[0] === $activa) {
            $grupoActivo = $titulo;
            break 2;
        }
    }
}

// En el panel de control no hay grupo activo: se abre el primero para no dejarlo todo cerrado
if ($grupoActivo === '' && $grupos !== []) {
    $grupoActivo = array_key_first($grupos);
}

// El mismo menú se pinta en el panel fijo y en el desplegable móvil
$instancia  = 0;
$pintarMenu = static function () use ($grupos, $activa, $grupoActivo, $porRevisar, &$instancia) {
    $instancia++;
    $sufijo = 'm' . $instancia;
    ?>
    <a class="marca d-block mb-3 text-decoration-none" href="<?= site_url('panel') ?>">
        <i class="bi bi-tree me-1"></i><?= esc(config('Hotel')->nombre) ?>
        <span class="lugar">Sistema de gestión</span>
    </a>

    <ul class="nav nav-pills flex-column gap-1 mb-2">
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center <?= $activa === 'panel' ? 'active' : '' ?>" href="<?= site_url('panel') ?>">
                <i class="bi bi-speedometer2 me-2"></i>Panel
            </a>
        </li>
    </ul>

    <?php foreach ($grupos as $tituloGrupo => $g): ?>
        <?php
        $abierto = $tituloGrupo === $grupoActivo;
        $id      = 'g' . md5($tituloGrupo) . $sufijo;
        // Avisos del grupo, para verlos aunque esté plegado
        $avisos = 0;
        foreach ($g['enlaces'] as $e) {
            $avisos += (int) $e[4];
        }
        ?>
        <button class="grupo-btn <?= $abierto ? '' : 'collapsed' ?>" data-bs-toggle="collapse"
                data-bs-target="#<?= $id ?>" aria-expanded="<?= $abierto ? 'true' : 'false' ?>">
            <i class="bi <?= $g['icono'] ?> me-2"></i><?= esc($tituloGrupo) ?>
            <?php if ($avisos > 0): ?>
                <span class="badge text-bg-warning ms-1"><?= $avisos ?></span>
            <?php endif ?>
            <i class="bi bi-chevron-down flecha ms-auto"></i>
        </button>

        <div class="collapse <?= $abierto ? 'show' : '' ?>" id="<?= $id ?>">
            <ul class="nav nav-pills flex-column gap-1 mb-1">
                <?php foreach ($g['enlaces'] as [$clave, $ruta, $icono, $etiqueta, $insignia]): ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center <?= $activa === $clave ? 'active' : '' ?>" href="<?= site_url($ruta) ?>">
                            <i class="bi <?= $icono ?> me-2"></i><?= esc($etiqueta) ?>
                            <?php if ($insignia > 0): ?>
                                <span class="badge text-bg-warning ms-auto"><?= $insignia ?></span>
                            <?php endif ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endforeach ?>
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
            <button class="btn-tactil" id="btn-tactil" type="button"
                    title="Botones grandes para pantalla táctil">
                <i class="bi bi-hand-index-thumb"></i>
                <span class="d-none d-md-inline">Táctil</span>
            </button>
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

<script>
    // Conmutador del modo táctil
    (function () {
        var boton = document.getElementById('btn-tactil');
        if (!boton) { return; }

        function pintar() {
            var activo = document.documentElement.classList.contains('tactil');
            boton.classList.toggle('activo', activo);
            boton.title = activo
                ? 'Modo táctil encendido en este dispositivo — toca para apagarlo'
                : 'Botones grandes para pantalla táctil';
        }

        boton.addEventListener('click', function () {
            var activo = document.documentElement.classList.toggle('tactil');
            try { localStorage.setItem('tactil', activo ? '1' : '0'); } catch (e) { /* modo privado */ }
            pintar();
        });

        pintar();
    })();
</script>
</body>
</html>
