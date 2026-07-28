<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#12281d">
    <meta name="robots" content="noindex, nofollow">
    <title>Comandero · <?= esc($hotel->nombre) ?></title>
    <link rel="manifest" href="<?= site_url('comandero/manifest.webmanifest') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('icono-192.png') ?>">
    <link rel="stylesheet" href="<?= base_url('vendor/bootstrap-icons/bootstrap-icons.css') ?>">
    <style>
        :root {
            --fondo:#12281d; --tarjeta:#1c3a2a; --alta:#24503a; --borde:#2e5741;
            --verde:#4f8a68; --verde-vivo:#5fb37f; --tinta:#eef5f0; --suave:#9fbcac;
            --rojo:#c0563f; --ambar:#c9922f;
            --barra-alta: calc(64px + env(safe-area-inset-bottom));
        }
        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        html, body { height:100%; }
        body {
            margin:0; background:var(--fondo); color:var(--tinta);
            font-family:system-ui,-apple-system,sans-serif; font-size:16px;
            overscroll-behavior-y:contain;
            /* Sin selección: el camarero arrastra el dedo para desplazar, no para copiar */
            -webkit-user-select:none; user-select:none;
        }
        button, input { font:inherit; color:inherit; }
        button { border:0; background:none; }
        button:active { transform:scale(.97); }

        /* ── Barra superior ── */
        .superior {
            position:sticky; top:0; z-index:30; background:var(--fondo);
            padding: calc(8px + env(safe-area-inset-top)) 12px 8px;
            display:flex; align-items:center; gap:10px; border-bottom:1px solid var(--borde);
        }
        .superior .quien { flex:1; min-width:0; }
        .superior .quien strong { display:block; font-size:.95rem; }
        .superior .quien span { display:block; font-size:.75rem; color:var(--suave); }
        .icono { width:44px; height:44px; border-radius:12px; display:grid; place-items:center;
                 font-size:1.2rem; background:var(--tarjeta); flex:none; }

        /* ── Estado de la conexión ── */
        .estado { font-size:.75rem; padding:7px 11px; border-radius:999px; display:flex;
                  align-items:center; gap:6px; background:var(--tarjeta); flex:none; }
        .estado .punto { width:8px; height:8px; border-radius:50%; background:var(--verde-vivo); flex:none; }
        .estado.sin .punto { background:var(--ambar); }
        .estado.pendiente { background:rgba(201,146,47,.2); color:#f0cf90; }
        .estado.pendiente .punto { background:var(--ambar); animation:latido 1.4s infinite; }
        @keyframes latido { 50% { opacity:.25; } }

        /* ── Contenido ── */
        main { padding:12px 12px calc(var(--barra-alta) + 12px); }
        .vista { display:none; }
        .vista.activa { display:block; }
        h2.titulo { font-size:1.1rem; margin:4px 0 12px; }
        .zona { font-size:.72rem; letter-spacing:.09em; text-transform:uppercase;
                color:var(--suave); margin:18px 0 8px; }
        .zona:first-of-type { margin-top:4px; }

        /* ── Mesas ── */
        .mesas { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
        .mesa {
            background:var(--tarjeta); border:1px solid var(--borde); border-radius:16px;
            padding:14px 12px; text-align:left; min-height:88px; position:relative;
            display:flex; flex-direction:column; justify-content:space-between;
        }
        .mesa .nombre { font-weight:600; font-size:1rem; }
        .mesa .dato { font-size:.8rem; color:var(--suave); margin-top:6px; }
        .mesa.ocupada { border-color:var(--verde); background:var(--alta); }
        .mesa.ocupada .dato { color:var(--verde-vivo); font-weight:600; }
        .mesa .globo {
            position:absolute; top:-6px; right:-6px; min-width:24px; height:24px; padding:0 7px;
            border-radius:999px; background:var(--ambar); color:#20160a; font-size:.72rem;
            font-weight:700; display:grid; place-items:center;
        }
        .sueltas { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:18px; }
        .sueltas button { background:var(--tarjeta); border:1px dashed var(--borde);
                          border-radius:16px; padding:16px 12px; min-height:64px; font-size:.9rem; }

        /* ── Líneas de la comanda ── */
        .cabecera-comanda { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
        .cabecera-comanda h2 { margin:0; font-size:1.15rem; flex:1; }
        .grupo { font-size:.72rem; letter-spacing:.09em; text-transform:uppercase;
                 color:var(--suave); margin:16px 0 8px; }
        .linea {
            background:var(--tarjeta); border:1px solid var(--borde); border-radius:14px;
            padding:12px; margin-bottom:8px; display:flex; align-items:center; gap:10px;
        }
        .linea .cuerpo { flex:1; min-width:0; }
        .linea .plato { font-weight:600; font-size:.95rem; }
        /* Etiqueta de destino: cada color un sitio, para leerlo sin pararse */
        .destino {
            display:inline-block; margin-left:7px; padding:2px 7px; border-radius:6px;
            font-size:.66rem; font-weight:600; letter-spacing:.03em; vertical-align:middle;
            text-transform:uppercase;
        }
        .destino.cocina  { background:rgba(201,146,47,.22); color:#f0cf90; }
        .destino.barra   { background:rgba(46,111,142,.28); color:#9ccbe4; }
        .destino.directo { background:var(--alta); color:var(--suave); }
        .linea .detalle { font-size:.78rem; color:var(--suave); margin-top:3px; }
        .linea .precio { font-size:.85rem; color:var(--suave); white-space:nowrap; }
        .linea.enviada { opacity:.62; }
        .linea.lista { border-color:var(--verde-vivo); }
        .contador { display:flex; align-items:center; gap:2px; flex:none; }
        /* 44 px: es el botón que más se pulsa y casi siempre con el móvil en
           una mano. Por debajo de eso se falla y se cambia la cantidad de otro plato. */
        .contador button { width:44px; height:44px; border-radius:11px; background:var(--alta);
                           font-size:1.15rem; display:grid; place-items:center; }
        .contador .n { min-width:26px; text-align:center; font-weight:700; }
        .marca-estado { font-size:.7rem; padding:3px 8px; border-radius:999px; flex:none; }
        .marca-estado.cocina { background:rgba(201,146,47,.2); color:#f0cf90; }
        .marca-estado.listo { background:rgba(95,179,127,.22); color:var(--verde-vivo); }
        .marca-estado.servido { background:var(--alta); color:var(--suave); }

        /* ── Carta ── */
        .buscador { position:relative; margin-bottom:10px; }
        .buscador input { width:100%; padding:14px 40px 14px 40px; border-radius:14px;
                          border:1px solid var(--borde); background:var(--tarjeta); font-size:1rem; }
        .buscador input:focus { outline:none; border-color:var(--verde); }
        .buscador .lupa { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--suave); }
        .buscador .limpiar { position:absolute; right:6px; top:50%; transform:translateY(-50%);
                             width:34px; height:34px; border-radius:10px; color:var(--suave); }
        .fichas { display:flex; gap:8px; overflow-x:auto; padding-bottom:10px; margin-bottom:6px;
                  scrollbar-width:none; }
        .fichas::-webkit-scrollbar { display:none; }
        .fichas button { flex:none; padding:10px 16px; border-radius:999px; background:var(--tarjeta);
                         border:1px solid var(--borde); font-size:.88rem; white-space:nowrap; }
        .fichas button.activa { background:var(--verde); border-color:var(--verde); font-weight:600; }
        .producto {
            width:100%; text-align:left; background:var(--tarjeta); border:1px solid var(--borde);
            border-radius:14px; padding:14px 12px; margin-bottom:8px; min-height:60px;
            display:flex; align-items:center; gap:12px;
        }
        .producto .cuerpo { flex:1; min-width:0; }
        .producto .nombre { font-weight:600; font-size:.95rem; }
        .producto .avisos { font-size:.75rem; color:var(--suave); margin-top:3px; }
        .producto .precio { font-weight:600; white-space:nowrap; }
        .producto .mas { width:40px; height:40px; border-radius:12px; background:var(--verde);
                         display:grid; place-items:center; font-size:1.3rem; flex:none; }
        .producto.puesto { border-color:var(--verde-vivo); }

        /* ── Barra inferior ── */
        .inferior {
            position:fixed; left:0; right:0; bottom:0; z-index:40; background:var(--tarjeta);
            border-top:1px solid var(--borde); display:flex;
            padding-bottom:env(safe-area-inset-bottom);
        }
        .inferior button { flex:1; min-height:64px; display:flex; flex-direction:column;
                           align-items:center; justify-content:center; gap:3px;
                           font-size:.7rem; color:var(--suave); position:relative; }
        .inferior button i { font-size:1.3rem; }
        .inferior button.activa { color:var(--verde-vivo); }
        .inferior .globo {
            position:absolute; top:8px; left:calc(50% + 6px); min-width:20px; height:20px;
            padding:0 6px; border-radius:999px; background:var(--rojo); color:#fff;
            font-size:.7rem; font-weight:700; display:grid; place-items:center;
        }
        .inferior .globo.verde { background:var(--verde-vivo); color:#0d1f16; }

        /* La acción principal ocupa toda la barra: es lo único que se pulsa al terminar */
        .accion {
            position:fixed; left:0; right:0; bottom:0; z-index:41; background:var(--fondo);
            border-top:1px solid var(--borde); padding:10px 12px calc(10px + env(safe-area-inset-bottom));
            display:none;
        }
        .accion.visible { display:block; }
        .accion .resumen { display:flex; justify-content:space-between; font-size:.85rem;
                           color:var(--suave); margin-bottom:8px; }
        .accion .grande { width:100%; min-height:56px; border-radius:14px; background:var(--verde);
                          font-size:1.05rem; font-weight:700; display:flex; align-items:center;
                          justify-content:center; gap:8px; }
        .accion .grande[disabled] { background:var(--alta); color:var(--suave); }

        /* ── Modales ── */
        .capa { position:fixed; inset:0; z-index:60; background:rgba(6,16,11,.72);
                display:none; align-items:flex-end; }
        .capa.abierta { display:flex; }
        .hoja {
            width:100%; max-height:88dvh; overflow-y:auto; background:var(--fondo);
            border-radius:20px 20px 0 0; padding:18px 16px calc(18px + env(safe-area-inset-bottom));
            border-top:1px solid var(--borde);
        }
        .hoja h3 { margin:0 0 4px; font-size:1.1rem; }
        .hoja .sub { color:var(--suave); font-size:.85rem; margin:0 0 16px; }
        .hoja label.opcion {
            display:flex; align-items:center; gap:12px; background:var(--tarjeta);
            border:1px solid var(--borde); border-radius:12px; padding:14px 12px; margin-bottom:8px;
        }
        .hoja label.opcion input { width:22px; height:22px; accent-color:var(--verde-vivo); flex:none; }
        .hoja label.opcion .extra { margin-left:auto; color:var(--suave); font-size:.85rem; }
        .hoja input[type=text] { width:100%; padding:14px; border-radius:12px;
                                 border:1px solid var(--borde); background:var(--tarjeta); }
        .hoja .botones { display:flex; gap:10px; margin-top:16px; }
        .hoja .botones button { flex:1; min-height:52px; border-radius:14px; font-weight:600;
                                background:var(--tarjeta); border:1px solid var(--borde); }
        .hoja .botones button.principal { background:var(--verde); border-color:var(--verde); }

        /* ── Avisos ── */
        .nota { border-radius:14px; padding:14px; font-size:.88rem; line-height:1.5; margin-bottom:12px; }
        .nota.ambar { background:rgba(201,146,47,.16); border:1px solid var(--ambar); }
        .nota.rojo { background:rgba(192,86,63,.16); border:1px solid var(--rojo); }
        .nota.calma { background:var(--tarjeta); border:1px solid var(--borde); color:var(--suave); }
        .nota button { text-decoration:underline; color:inherit; font-size:.85rem; margin-top:8px; }
        .vacio { text-align:center; color:var(--suave); padding:48px 20px; }
        .vacio i { font-size:2rem; display:block; margin-bottom:10px; opacity:.5; }
        .brindis {
            position:fixed; left:12px; right:12px; bottom:calc(var(--barra-alta) + 12px); z-index:70;
            background:var(--alta); border:1px solid var(--verde); border-radius:14px;
            padding:13px 15px; font-size:.9rem; opacity:0; transform:translateY(10px);
            transition:opacity .2s, transform .2s; pointer-events:none;
        }
        .brindis.visible { opacity:1; transform:none; }
        .brindis.malo { background:rgba(192,86,63,.9); border-color:var(--rojo); }
    </style>
</head>
<body>

<!-- ═══ Barra superior ═══ -->
<header class="superior">
    <div class="icono"><i class="bi bi-person"></i></div>
    <div class="quien">
        <strong><?= esc($empleado['nombre']) ?></strong>
        <span><?= esc($hotel->nombre) ?></span>
    </div>
    <div class="estado" id="estado"><span class="punto"></span><span id="estado-txt">Conectado</span></div>
    <button class="icono" id="btn-salir" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></button>
</header>

<main>
    <!-- ═══ Mesas ═══ -->
    <section class="vista activa" id="v-mesas">
        <div id="avisos"></div>
        <div id="lista-mesas"></div>
        <div class="sueltas">
            <button id="btn-llevar"><i class="bi bi-bag"></i><br>Para llevar</button>
            <button id="btn-cabana"><i class="bi bi-house"></i><br>A una cabaña</button>
        </div>
        <div id="lista-sinmesa"></div>
    </section>

    <!-- ═══ Comanda ═══ -->
    <section class="vista" id="v-comanda">
        <div class="cabecera-comanda">
            <button class="icono" id="btn-volver"><i class="bi bi-arrow-left"></i></button>
            <h2 id="comanda-titulo">Mesa</h2>
        </div>
        <div id="comanda-cuerpo"></div>
    </section>

    <!-- ═══ Carta ═══ -->
    <section class="vista" id="v-carta">
        <div class="cabecera-comanda">
            <button class="icono" id="btn-volver-carta"><i class="bi bi-arrow-left"></i></button>
            <h2 id="carta-titulo">Añadir platos</h2>
        </div>
        <div class="buscador">
            <i class="bi bi-search lupa"></i>
            <input type="search" id="buscar" placeholder="Buscar plato…" autocomplete="off" enterkeyhint="search">
            <button class="limpiar" id="btn-limpiar" style="display:none"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="fichas" id="categorias"></div>
        <div id="lista-productos"></div>
    </section>

    <!-- ═══ Listos para servir ═══ -->
    <section class="vista" id="v-listos">
        <h2 class="titulo">Listos para servir</h2>
        <div id="lista-listos"></div>
    </section>
</main>

<!-- ═══ Barra inferior ═══ -->
<nav class="inferior" id="inferior">
    <button data-ir="mesas" class="activa"><i class="bi bi-grid-3x3-gap"></i>Mesas</button>
    <button data-ir="listos"><i class="bi bi-bell"></i>Listos<span class="globo verde" id="globo-listos" style="display:none">0</span></button>
</nav>

<!-- Barra de acción de la comanda -->
<div class="accion" id="accion">
    <div class="resumen">
        <span id="accion-izq">Sin platos</span>
        <span id="accion-der"></span>
    </div>
    <button class="grande" id="btn-enviar" disabled>
        <i class="bi bi-send"></i><span id="btn-enviar-txt">Enviar a cocina</span>
    </button>
</div>

<!-- ═══ Modal: opciones del plato ═══ -->
<div class="capa" id="modal-plato">
    <div class="hoja">
        <h3 id="plato-nombre"></h3>
        <p class="sub" id="plato-precio"></p>
        <div id="plato-grupos"></div>
        <div id="plato-mitades" style="display:none">
            <div class="grupo">¿Mitad y mitad?</div>
            <div id="plato-lista-mitades"></div>
        </div>
        <div class="grupo">Nota para cocina</div>
        <input type="text" id="plato-nota" placeholder="Sin cebolla, poco sal…" maxlength="120" autocomplete="off">
        <div class="botones">
            <button data-cerrar>Cancelar</button>
            <button class="principal" id="plato-aceptar">Añadir</button>
        </div>
    </div>
</div>

<!-- ═══ Modal: destino suelto ═══ -->
<div class="capa" id="modal-destino">
    <div class="hoja">
        <h3 id="destino-titulo">Para llevar</h3>
        <p class="sub" id="destino-sub"></p>
        <div id="destino-cuerpo"></div>
        <div class="botones">
            <button data-cerrar>Cancelar</button>
            <button class="principal" id="destino-aceptar">Abrir comanda</button>
        </div>
    </div>
</div>

<div class="brindis" id="brindis"></div>

<script>
(function () {
    'use strict';

    const BASE     = <?= json_encode(rtrim(site_url('comandero'), '/')) ?>;
    const EMPLEADO = <?= json_encode($empleado) ?>;
    const LONGITUD_PIN_MAX = 99;

    let token = <?= json_encode(csrf_hash()) ?>;
    const $ = (s) => document.querySelector(s);
    const pesos = (n) => '$' + Math.round(n || 0).toLocaleString('es-CO');

    // A dónde va cada cosa. «Directo» es lo que se coge y se entrega sin que
    // nadie lo prepare: una botella, una bolsa de papas.
    const DESTINOS = { cocina: 'Cocina', barra: 'Barra', directo: 'Directo' };

    // ═══════════════════════════════════════════════════════════════
    //  Almacén local
    //
    //  Todo lo que el camarero necesita vive en el teléfono: la carta para
    //  poder pedir sin señal, los borradores de cada mesa y la cola de
    //  rondas que aún no han llegado al servidor. Nada de esto se borra
    //  al recargar ni al cerrar la aplicación.
    // ═══════════════════════════════════════════════════════════════
    const Almacen = {
        leer(clave, porDefecto) {
            try { return JSON.parse(localStorage.getItem('comandero.' + clave)) ?? porDefecto; }
            catch (e) { return porDefecto; }
        },
        guardar(clave, valor) {
            try { localStorage.setItem('comandero.' + clave, JSON.stringify(valor)); }
            catch (e) { avisar('El teléfono no tiene espacio para guardar.', true); }
        },
    };

    let carta       = Almacen.leer('carta', null);      // categorías y productos
    let mesas       = Almacen.leer('mesas', []);
    let alojados    = Almacen.leer('alojados', []);
    let borradores  = Almacen.leer('borradores', {});   // lo no enviado, por destino
    let cola        = Almacen.leer('cola', []);         // rondas esperando señal
    let listos      = [];

    let claveActual = null;   // el destino que se está atendiendo
    let vista       = 'mesas';
    let categoriaId = null;
    let filtro      = '';
    let enviando    = false;

    // Todo cambio pasa por aquí, así que es el sitio para avisar al TPV de lo
    // que se lleva apuntado. Engancharlo en cada botón sería olvidarse en uno.
    const guardarBorradores = () => {
        Almacen.guardar('borradores', borradores);
        if (claveActual) { avisarAlTpv(claveActual); }
    };
    const guardarCola = () => Almacen.guardar('cola', cola);

    function uuid() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        return 'r-' + Date.now() + '-' + Math.random().toString(16).slice(2, 10);
    }

    function avisar(texto, malo) {
        const b = $('#brindis');
        b.textContent = texto;
        b.className = 'brindis visible' + (malo ? ' malo' : '');
        clearTimeout(b._t);
        b._t = setTimeout(() => { b.className = 'brindis'; }, malo ? 4200 : 2200);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Red
    // ═══════════════════════════════════════════════════════════════
    // Aquí importa el doble: el camarero está de pie en la sala y no sabe si
    // la comanda salió o se quedó en la cola esperando cobertura.
    async function api(ruta, opciones) {
        if (window.espera) { window.espera.iniciar(); }
        try {
            return await apiCrudo(ruta, opciones);
        } finally {
            if (window.espera) { window.espera.terminar(); }
        }
    }

    async function apiCrudo(ruta, opciones) {
        const cfg = Object.assign({ headers: {} }, opciones || {});
        cfg.headers['X-Requested-With'] = 'XMLHttpRequest';
        cfg.headers['Accept'] = 'application/json';
        if (cfg.body) {
            cfg.headers['Content-Type'] = 'application/json';
            cfg.headers['X-CSRF-TOKEN'] = token;
        }

        const r = await fetch(BASE + '/api' + ruta, cfg);

        // El token rota en cada petición: por eso la cola se vacía de una en
        // una y nunca en paralelo, o la segunda llegaría con el token viejo
        const nuevo = r.headers.get('X-CSRF-TOKEN');
        if (nuevo) { token = nuevo; }

        if (r.status === 401) {
            const e = new Error('sesion');
            e.sesion = true;
            throw e;
        }
        if (r.status === 403) {
            const e = new Error('El token de seguridad caducó.');
            e.token = true;
            throw e;
        }

        const datos = await r.json().catch(() => ({ ok: false, error: 'Respuesta ilegible del servidor.' }));
        if (!r.ok || !datos.ok) {
            const e = new Error(datos.error || 'No se pudo completar.');
            e.rechazada = r.status >= 400 && r.status < 500;   // culpa del contenido, no de la red
            throw e;
        }
        return datos;
    }

    // ═══════════════════════════════════════════════════════════════
    //  Cola de envío
    //
    //  Una ronda entera se manda de una vez, con un identificador que no
    //  cambia entre reintentos. Si el servidor ya la tenía, contesta que
    //  era repetida y no duplica nada en cocina.
    // ═══════════════════════════════════════════════════════════════
    /**
     * Consigue un token CSRF válido con una lectura.
     *
     * Hace falta porque la aplicación se puede abrir desde la caché estando
     * sin señal: el token que venía dentro del HTML es de la última vez que
     * se guardó y hace rato que caducó. Las peticiones GET están exentas de
     * CSRF, así que una lectura cualquiera devuelve uno recién hecho.
     */
    async function refrescarToken() {
        try { await api('/pulso'); return true; }
        catch (e) { return !!e.sesion ? false : false; }
    }

    async function sincronizar() {
        if (enviando || !navigator.onLine || cola.length === 0) { pintarEstado(); return; }
        enviando = true;
        pintarEstado();

        await refrescarToken();

        while (cola.length > 0) {
            const item = cola[0];
            if (item.bloqueada) { break; }   // necesita que decida una persona

            try {
                const datos = await api('/enviar', { method: 'POST', body: JSON.stringify(item.payload) });

                // El servidor manda el id de la comanda: el borrador de esa mesa
                // lo guarda para que la siguiente ronda caiga en la misma cuenta
                const b = borradores[item.clave];
                if (b) { b.comanda_id = datos.comanda_id; guardarBorradores(); }

                cola.shift();
                guardarCola();
            } catch (e) {
                if (e.sesion) {
                    avisar('Vuelve a entrar con tu PIN. No se ha perdido nada.', true);
                    setTimeout(() => { location.href = BASE; }, 2500);
                    break;
                }
                if (e.token) {
                    // Token caducado: se pide uno nuevo y se reintenta esta misma
                    // ronda. Si tampoco así, se deja para el siguiente latido.
                    if (!item.reintentoToken) { item.reintentoToken = true; await refrescarToken(); continue; }
                    break;
                }
                if (e.rechazada) {
                    // El servidor la rechaza y la rechazará siempre: reintentar
                    // en bucle solo esconde el problema. Se le enseña al camarero.
                    item.bloqueada = true;
                    item.motivo = e.message;
                    guardarCola();
                    pintarAvisos();
                    break;
                }
                break;   // sin red: se queda tal cual y se reintenta luego
            }
        }

        enviando = false;
        pintarEstado();
        pintarAvisos();
        if (cola.length === 0 && navigator.onLine) { refrescar(); }
    }

    function pintarEstado() {
        const caja = $('#estado');
        const txt  = $('#estado-txt');
        const pendientes = cola.length;

        if (pendientes > 0) {
            caja.className = 'estado pendiente';
            txt.textContent = pendientes + (pendientes === 1 ? ' ronda sin enviar' : ' rondas sin enviar');
        } else if (!navigator.onLine) {
            caja.className = 'estado sin';
            txt.textContent = 'Sin conexión';
        } else {
            caja.className = 'estado';
            txt.textContent = 'Conectado';
        }
    }

    function pintarAvisos() {
        const caja = $('#avisos');
        caja.innerHTML = '';

        const atascada = cola.find((c) => c.bloqueada);
        if (atascada) {
            const d = document.createElement('div');
            d.className = 'nota rojo';
            d.innerHTML = '<strong>Una ronda no se pudo enviar.</strong><br>' +
                escapar(atascada.motivo || 'El servidor la rechazó.') +
                '<br><button id="btn-descartar">Descartar esa ronda y seguir</button>';
            caja.appendChild(d);
            $('#btn-descartar').onclick = () => {
                if (!confirm('Se borrará esa ronda del teléfono. Tendrás que volver a tomarla. ¿Seguro?')) { return; }
                cola = cola.filter((c) => c !== atascada);
                guardarCola();
                pintarAvisos(); pintarEstado(); sincronizar();
            };
            return;
        }

        if (cola.length > 0) {
            const d = document.createElement('div');
            d.className = 'nota ambar';
            d.innerHTML = '<strong>' + cola.length + (cola.length === 1 ? ' ronda esperando señal.' : ' rondas esperando señal.') +
                '</strong><br>Se envían solas en cuanto vuelva la cobertura. Puedes seguir tomando nota.';
            caja.appendChild(d);
        }

        if (!carta) {
            const d = document.createElement('div');
            d.className = 'nota rojo';
            d.innerHTML = '<strong>Todavía no tienes la carta.</strong><br>' +
                'Conéctate una vez al wifi del hotel para descargarla; después ya funciona sin señal.';
            caja.appendChild(d);
        }
    }

    function escapar(t) {
        const d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    // ═══════════════════════════════════════════════════════════════
    //  Datos del servidor
    // ═══════════════════════════════════════════════════════════════
    async function refrescar() {
        if (!navigator.onLine) { return; }
        try {
            const d = await api('/estado');
            carta    = d.categorias;
            mesas    = d.mesas;
            alojados = d.alojados;
            listos   = d.listos || [];
            Almacen.guardar('carta', carta);
            Almacen.guardar('mesas', mesas);
            Almacen.guardar('alojados', alojados);
            if (categoriaId === null && carta.length) { categoriaId = carta[0].id; }
            pintarTodo();
        } catch (e) {
            if (e.sesion) { location.href = BASE; }
        }
    }

    async function pulso() {
        if (!navigator.onLine || cola.length > 0) { return; }
        try {
            const d = await api('/pulso');
            mesas  = d.mesas;
            listos = d.listos || [];
            Almacen.guardar('mesas', mesas);
            if (vista === 'mesas') { pintarMesas(); }
            if (vista === 'listos') { pintarListos(); }
            pintarGloboListos();
        } catch (e) { /* si falla, se reintenta al siguiente pulso */ }
    }

    // ═══════════════════════════════════════════════════════════════
    //  Borradores: lo tomado y aún no enviado, por destino
    // ═══════════════════════════════════════════════════════════════
    function borrador(clave) {
        return borradores[clave] || null;
    }

    function abrirDestino(clave, datos) {
        if (!borradores[clave]) {
            borradores[clave] = Object.assign({
                clave: clave, mesa_id: null, reserva_id: null, nombre: null,
                comensales: 1, comanda_id: null, lineas: [],
            }, datos || {});
            guardarBorradores();
        } else if (datos) {
            Object.assign(borradores[clave], datos);
            guardarBorradores();
        }
        claveActual = clave;
        irA('comanda');
    }

    function anadirLinea(producto, extras) {
        const b = borrador(claveActual);
        if (!b) { return; }
        extras = extras || {};

        const modificadores = extras.modificadores || [];
        const extra = modificadores.reduce((a, m) => a + Number(m.precio_extra || 0), 0);
        let precio = Number(producto.precio) + extra;
        let nombre = producto.nombre;

        if (extras.mitad) {
            precio = Math.max(Number(producto.precio), Number(extras.mitad.precio)) + extra;
            nombre = '½ ' + producto.nombre + ' + ½ ' + extras.mitad.nombre;
        }

        // Solo se junta con una línea igual si no lleva nada personalizado
        const simple = modificadores.length === 0 && !extras.mitad && !extras.notas;
        if (simple) {
            const igual = b.lineas.find((l) => l.producto_id === producto.id && l.simple);
            if (igual) {
                igual.cantidad++;
                guardarBorradores();
                pintarComanda();
                return;
            }
        }

        b.lineas.push({
            tmp: uuid(),
            producto_id: producto.id,
            nombre: nombre,
            destino: producto.destino || 'cocina',
            precio: precio,
            cantidad: 1,
            notas: extras.notas || '',
            mitad_id: extras.mitad ? extras.mitad.id : 0,
            modificadores: modificadores,
            simple: simple,
        });
        guardarBorradores();
        pintarComanda();
    }

    /**
     * Avisa al TPV de lo que se lleva apuntado, sin haberlo enviado aún.
     *
     * Va con retardo a propósito: si se mandara en cada toque, añadir seis
     * platos serían seis peticiones seguidas. Y si no hay señal no pasa nada:
     * esto es un extra para el que está en la caja, no el camino por el que
     * viaja la comanda. Lo apuntado sigue a salvo en el teléfono.
     */
    let avisoTpv = null;
    function avisarAlTpv(clave) {
        clearTimeout(avisoTpv);
        avisoTpv = setTimeout(async () => {
            const b = borrador(clave);
            if (!b || !navigator.onLine) { return; }
            try {
                await api('/borrador', {
                    method: 'POST',
                    body: JSON.stringify({
                        clave: b.clave,
                        mesa_id: b.mesa_id,
                        comanda_id: b.comanda_id,
                        destino: b.nombre,
                        lineas: b.lineas.map((l) => ({
                            nombre: l.nombre, cantidad: l.cantidad, importe: l.precio,
                        })),
                    }),
                });
            } catch (e) { /* que el TPV no se entere no rompe nada aquí */ }
        }, 1500);
    }

    function cambiarCantidad(tmp, delta) {
        const b = borrador(claveActual);
        const l = b.lineas.find((x) => x.tmp === tmp);
        if (!l) { return; }
        l.cantidad += delta;
        if (l.cantidad <= 0) { b.lineas = b.lineas.filter((x) => x.tmp !== tmp); }
        guardarBorradores();
        pintarComanda();
    }

    /** Pasa lo tomado a la cola de envío y limpia el borrador. */
    function enviarRonda() {
        const b = borrador(claveActual);
        if (!b || b.lineas.length === 0) { return; }

        // Se cuenta antes de vaciar el borrador, para poder decir a dónde fue
        const reparto = { cocina: 0, barra: 0, directo: 0 };
        b.lineas.forEach((l) => { reparto[l.destino || 'cocina'] += l.cantidad; });
        const trozos = [];
        if (reparto.cocina)  { trozos.push(reparto.cocina + ' a cocina'); }
        if (reparto.barra)   { trozos.push(reparto.barra + ' a barra'); }
        if (reparto.directo) { trozos.push(reparto.directo + ' para entregar'); }

        cola.push({
            uuid: uuid(),
            clave: b.clave,
            payload: {
                uuid: null,   // se rellena justo debajo
                clave: b.clave,
                comanda_id: b.comanda_id,
                mesa_id: b.mesa_id,
                reserva_id: b.reserva_id,
                nombre: b.nombre,
                comensales: b.comensales,
                a_cocina: true,
                tomada_en: Math.floor(Date.now() / 1000),
                lineas: b.lineas.map((l) => ({
                    producto_id: l.producto_id,
                    cantidad: l.cantidad,
                    notas: l.notas,
                    mitad_id: l.mitad_id,
                    modificadores: l.modificadores.map((m) => m.id),
                })),
            },
        });
        cola[cola.length - 1].payload.uuid = cola[cola.length - 1].uuid;

        b.lineas = [];
        guardarBorradores();
        guardarCola();

        avisar(navigator.onLine
            ? 'Enviado · ' + trozos.join(' · ')
            : 'Guardado: se enviará al recuperar la señal');
        pintarComanda();
        pintarEstado();
        pintarAvisos();
        sincronizar();
    }

    // ═══════════════════════════════════════════════════════════════
    //  Pintado
    // ═══════════════════════════════════════════════════════════════
    function pintarTodo() {
        pintarMesas(); pintarComanda(); pintarCarta(); pintarListos();
        pintarEstado(); pintarAvisos(); pintarGloboListos();
    }

    function pintarMesas() {
        const caja = $('#lista-mesas');
        caja.innerHTML = '';

        if (!mesas.length) {
            caja.innerHTML = '<div class="vacio"><i class="bi bi-grid-3x3-gap"></i>No hay mesas dadas de alta.</div>';
        }

        let zona = null;
        mesas.forEach((m) => {
            if (m.zona !== zona) {
                zona = m.zona;
                const t = document.createElement('div');
                t.className = 'zona';
                t.textContent = zona || 'Sala';
                caja.appendChild(t);
                const rejilla = document.createElement('div');
                rejilla.className = 'mesas';
                rejilla.dataset.zona = zona || 'sala';
                caja.appendChild(rejilla);
            }

            const rejilla = caja.lastElementChild;
            const clave   = 'mesa-' + m.id;
            const b       = borrador(clave);
            const sinEnviar = b ? b.lineas.reduce((a, l) => a + l.cantidad, 0) : 0;
            const enCola  = cola.filter((c) => c.clave === clave).length;

            const btn = document.createElement('button');
            btn.className = 'mesa' + (m.ocupada || sinEnviar ? ' ocupada' : '');
            btn.innerHTML =
                '<div class="nombre">' + escapar(m.nombre) + '</div>' +
                '<div class="dato">' + (
                    m.ocupada
                        ? pesos(m.total) + ' · ' + m.abierta_hace + ' min'
                        : (sinEnviar ? 'Tomando nota' : (m.capacidad || 0) + ' personas · Libre')
                ) + '</div>' +
                (sinEnviar || enCola
                    ? '<span class="globo">' + (sinEnviar || enCola) + '</span>'
                    : '');
            btn.onclick = () => abrirDestino(clave, { mesa_id: m.id, nombre: m.nombre, comensales: m.comensales || 1 });
            rejilla.appendChild(btn);
        });

        // Comandas sueltas ya abiertas (para llevar, cabañas)
        const sueltas = $('#lista-sinmesa');
        sueltas.innerHTML = '';
        Object.values(borradores).forEach((b) => {
            if (b.mesa_id) { return; }
            const pend = b.lineas.reduce((a, l) => a + l.cantidad, 0);
            if (pend === 0 && !b.comanda_id) { return; }
            const btn = document.createElement('button');
            btn.className = 'mesa ocupada';
            btn.style.width = '100%';
            btn.style.marginTop = '10px';
            btn.innerHTML = '<div class="nombre">' + escapar(b.nombre || 'Sin nombre') + '</div>' +
                '<div class="dato">' + (pend ? pend + ' sin enviar' : 'Comanda abierta') + '</div>';
            btn.onclick = () => { claveActual = b.clave; irA('comanda'); };
            sueltas.appendChild(btn);
        });
    }

    function pintarComanda() {
        const b = borrador(claveActual);
        const caja = $('#comanda-cuerpo');
        const accion = $('#accion');

        if (!b) { accion.classList.remove('visible'); return; }

        $('#comanda-titulo').textContent = b.nombre || 'Comanda';
        caja.innerHTML = '';

        // ── Lo que aún no se ha mandado ──
        const cab = document.createElement('div');
        cab.className = 'grupo';
        cab.textContent = 'Por enviar';
        caja.appendChild(cab);

        if (b.lineas.length === 0) {
            const v = document.createElement('div');
            v.className = 'nota calma';
            v.textContent = 'Nada tomado todavía. Pulsa «Añadir platos».';
            caja.appendChild(v);
        }

        b.lineas.forEach((l) => {
            const d = document.createElement('div');
            d.className = 'linea';
            const detalle = [];
            if (l.modificadores.length) { detalle.push(l.modificadores.map((m) => m.nombre).join(', ')); }
            if (l.notas) { detalle.push('“' + l.notas + '”'); }

            d.innerHTML =
                '<div class="cuerpo"><div class="plato">' + escapar(l.nombre) +
                '<span class="destino ' + (l.destino || 'cocina') + '">' +
                DESTINOS[l.destino || 'cocina'] + '</span></div>' +
                (detalle.length ? '<div class="detalle">' + escapar(detalle.join(' · ')) + '</div>' : '') +
                '</div>' +
                '<div class="precio">' + pesos(l.precio * l.cantidad) + '</div>' +
                '<div class="contador">' +
                '<button data-menos="' + l.tmp + '">−</button>' +
                '<span class="n">' + l.cantidad + '</span>' +
                '<button data-mas="' + l.tmp + '">+</button>' +
                '</div>';
            caja.appendChild(d);
        });

        caja.querySelectorAll('[data-menos]').forEach((x) => {
            x.onclick = () => cambiarCantidad(x.dataset.menos, -1);
        });
        caja.querySelectorAll('[data-mas]').forEach((x) => {
            x.onclick = () => cambiarCantidad(x.dataset.mas, 1);
        });

        // ── Botón de añadir ──
        const anadir = document.createElement('button');
        anadir.className = 'producto';
        anadir.style.borderStyle = 'dashed';
        anadir.innerHTML = '<div class="cuerpo"><div class="nombre">Añadir platos</div></div>' +
            '<div class="mas"><i class="bi bi-plus"></i></div>';
        anadir.onclick = () => irA('carta');
        caja.appendChild(anadir);

        // ── Rondas de esta mesa esperando señal ──
        const suyas = cola.filter((c) => c.clave === b.clave);
        if (suyas.length) {
            const t = document.createElement('div');
            t.className = 'grupo';
            t.textContent = 'Esperando señal';
            caja.appendChild(t);
            suyas.forEach((c) => {
                const n = c.payload.lineas.reduce((a, l) => a + l.cantidad, 0);
                const d = document.createElement('div');
                d.className = 'linea enviada';
                d.innerHTML = '<div class="cuerpo"><div class="plato">' + n +
                    (n === 1 ? ' plato' : ' platos') + ' por enviar</div>' +
                    '<div class="detalle">' + (c.bloqueada ? escapar(c.motivo) : 'Se manda al volver la cobertura') +
                    '</div></div><span class="marca-estado cocina">' +
                    (c.bloqueada ? 'Atascada' : 'En cola') + '</span>';
                caja.appendChild(d);
            });
        }

        // ── Lo que ya está en cocina (solo si hay conexión y comanda) ──
        if (b.enviadas && b.enviadas.length) {
            const t = document.createElement('div');
            t.className = 'grupo';
            t.textContent = 'Ya en cocina';
            caja.appendChild(t);
            b.enviadas.forEach((l) => {
                const estado = Number(l.servido) === 1 ? ['servido', 'Servido']
                    : (Number(l.entregado) === 1 ? ['listo', '¡Listo!'] : ['cocina', 'En cocina']);
                const d = document.createElement('div');
                d.className = 'linea enviada' + (estado[0] === 'listo' ? ' lista' : '');
                d.innerHTML = '<div class="cuerpo"><div class="plato">' + l.cantidad + '× ' +
                    escapar(l.nombre_producto) + '</div></div>' +
                    '<span class="marca-estado ' + estado[0] + '">' + estado[1] + '</span>';
                caja.appendChild(d);
            });
        }

        // ── Barra de acción ──
        // El botón dice a dónde va cada cosa, no «a cocina» a secas: en la
        // misma ronda puede haber platos, bebidas de barra y una botella que
        // se entrega tal cual sin pasar por ningún lado.
        const unidades = b.lineas.reduce((a, l) => a + l.cantidad, 0);
        const importe  = b.lineas.reduce((a, l) => a + l.precio * l.cantidad, 0);
        const reparto  = { cocina: 0, barra: 0, directo: 0 };
        b.lineas.forEach((l) => { reparto[l.destino || 'cocina'] += l.cantidad; });

        $('#accion-izq').textContent = unidades === 0 ? 'Sin platos'
            : unidades + (unidades === 1 ? ' plato' : ' platos');
        $('#accion-der').textContent = unidades === 0 ? '' : pesos(importe);
        $('#btn-enviar').disabled = unidades === 0;

        const trozos = [];
        if (reparto.cocina)  { trozos.push(reparto.cocina + ' a cocina'); }
        if (reparto.barra)   { trozos.push(reparto.barra + ' a barra'); }
        if (reparto.directo) { trozos.push(reparto.directo + ' directo'); }
        $('#btn-enviar-txt').textContent = unidades === 0
            ? 'Enviar' : 'Enviar · ' + trozos.join(' · ');
    }

    /** Trae del servidor lo que ya está en cocina de esta comanda. */
    async function cargarEnviadas() {
        const b = borrador(claveActual);
        if (!b || !b.comanda_id || !navigator.onLine) { return; }
        try {
            const d = await api('/comanda/' + b.comanda_id);
            b.enviadas = (d.comanda.lineas || []).filter((l) => Number(l.enviado_cocina) === 1);
            guardarBorradores();
            if (vista === 'comanda') { pintarComanda(); }
        } catch (e) { /* sin conexión: se ve solo lo local */ }
    }

    function pintarCarta() {
        const fichas = $('#categorias');
        fichas.innerHTML = '';
        if (!carta) { return; }

        carta.forEach((c) => {
            const b = document.createElement('button');
            b.textContent = c.nombre;
            b.className = c.id === categoriaId ? 'activa' : '';
            b.onclick = () => { categoriaId = c.id; filtro = ''; $('#buscar').value = ''; pintarCarta(); };
            fichas.appendChild(b);
        });

        const caja = $('#lista-productos');
        caja.innerHTML = '';

        // Con búsqueda activa se mira en toda la carta, no solo en la categoría
        let productos = [];
        if (filtro !== '') {
            const t = filtro.toLowerCase();
            carta.forEach((c) => c.productos.forEach((p) => {
                if (p.nombre.toLowerCase().includes(t)) { productos.push(p); }
            }));
            fichas.style.display = 'none';
        } else {
            fichas.style.display = 'flex';
            const cat = carta.find((c) => c.id === categoriaId) || carta[0];
            productos = cat ? cat.productos : [];
        }

        if (productos.length === 0) {
            caja.innerHTML = '<div class="vacio"><i class="bi bi-search"></i>Nada que coincida.</div>';
            return;
        }

        const b = borrador(claveActual);
        productos.forEach((p) => {
            const puesto = b ? b.lineas.some((l) => l.producto_id === p.id) : false;
            const avisos = [];
            if (p.picante) { avisos.push('🌶'.repeat(Math.min(3, p.picante))); }
            if (p.alergenos && p.alergenos.length) { avisos.push(p.alergenos.join(', ')); }

            const btn = document.createElement('button');
            btn.className = 'producto' + (puesto ? ' puesto' : '');
            btn.innerHTML =
                '<div class="cuerpo"><div class="nombre">' + escapar(p.nombre) + '</div>' +
                (avisos.length ? '<div class="avisos">' + escapar(avisos.join(' · ')) + '</div>' : '') +
                '</div><div class="precio">' + pesos(p.precio) + '</div>' +
                '<div class="mas"><i class="bi bi-plus"></i></div>';

            btn.onclick = () => {
                const necesitaElegir = (p.grupos && p.grupos.length) || p.divisible;
                if (necesitaElegir) { abrirModalPlato(p); }
                else { anadirLinea(p); avisar(p.nombre + ' añadido'); pintarCarta(); }
            };
            caja.appendChild(btn);
        });
    }

    function pintarListos() {
        const caja = $('#lista-listos');
        caja.innerHTML = '';

        if (!listos.length) {
            caja.innerHTML = '<div class="vacio"><i class="bi bi-bell"></i>Nada listo ahora mismo.</div>';
            return;
        }

        listos.forEach((l) => {
            const d = document.createElement('div');
            d.className = 'linea lista';
            const donde = l.mesa || l.unidad_nombre || l.numero;
            d.innerHTML = '<div class="cuerpo"><div class="plato">' + l.cantidad + '× ' +
                escapar(l.nombre_producto) + '</div>' +
                '<div class="detalle">' + escapar(donde) + '</div></div>' +
                '<button class="mas" data-servir="' + l.id + '" style="width:44px;height:44px;' +
                'border-radius:12px;background:var(--verde);display:grid;place-items:center">' +
                '<i class="bi bi-check-lg"></i></button>';
            caja.appendChild(d);
        });

        caja.querySelectorAll('[data-servir]').forEach((btn) => {
            btn.onclick = async () => {
                if (!navigator.onLine) { avisar('Sin conexión: márcalo cuando vuelva la señal.', true); return; }
                try {
                    const d = await api('/servir/' + btn.dataset.servir, { method: 'POST', body: '{}' });
                    listos = d.listos || [];
                    pintarListos(); pintarGloboListos();
                    avisar('Servido');
                } catch (e) {
                    avisar(e.sesion ? 'Vuelve a entrar con tu PIN.' : e.message, true);
                }
            };
        });
    }

    function pintarGloboListos() {
        const g = $('#globo-listos');
        g.textContent = listos.length;
        g.style.display = listos.length ? 'grid' : 'none';
    }

    // ═══════════════════════════════════════════════════════════════
    //  Modal del plato: modificadores, mitades y nota
    // ═══════════════════════════════════════════════════════════════
    let platoActual = null;
    let mitadElegida = null;

    function abrirModalPlato(p) {
        platoActual = p;
        mitadElegida = null;

        $('#plato-nombre').textContent = p.nombre;
        $('#plato-precio').textContent = pesos(p.precio);
        $('#plato-nota').value = '';

        const grupos = $('#plato-grupos');
        grupos.innerHTML = '';
        (p.grupos || []).forEach((g) => {
            const t = document.createElement('div');
            t.className = 'grupo';
            t.textContent = g.nombre + (Number(g.obligatorio) === 1 ? ' · obligatorio' : '');
            grupos.appendChild(t);

            (g.opciones || []).forEach((o) => {
                const unico = Number(g.maximo) === 1;
                const l = document.createElement('label');
                l.className = 'opcion';
                l.innerHTML = '<input type="' + (unico ? 'radio' : 'checkbox') + '" ' +
                    'name="g' + g.id + '" value="' + o.id + '" ' +
                    'data-precio="' + (o.precio_extra || 0) + '" data-nombre="' + escapar(o.nombre) + '">' +
                    '<span>' + escapar(o.nombre) + '</span>' +
                    (Number(o.precio_extra) > 0 ? '<span class="extra">+' + pesos(o.precio_extra) + '</span>' : '');
                grupos.appendChild(l);
            });
        });

        // Mitad y mitad
        const caja = $('#plato-mitades');
        if (p.divisible) {
            caja.style.display = 'block';
            const lista = $('#plato-lista-mitades');
            lista.innerHTML = '';
            (carta || []).forEach((c) => c.productos.forEach((otro) => {
                if (!otro.divisible || otro.id === p.id) { return; }
                const l = document.createElement('label');
                l.className = 'opcion';
                l.innerHTML = '<input type="radio" name="mitad" value="' + otro.id + '">' +
                    '<span>' + escapar(otro.nombre) + '</span>' +
                    '<span class="extra">' + pesos(otro.precio) + '</span>';
                l.querySelector('input').onchange = () => { mitadElegida = otro; };
                lista.appendChild(l);
            }));
        } else {
            caja.style.display = 'none';
        }

        $('#modal-plato').classList.add('abierta');
    }

    $('#plato-aceptar').onclick = () => {
        const modificadores = [];
        $('#plato-grupos').querySelectorAll('input:checked').forEach((i) => {
            modificadores.push({
                id: Number(i.value),
                nombre: i.dataset.nombre,
                precio_extra: Number(i.dataset.precio),
            });
        });

        anadirLinea(platoActual, {
            modificadores: modificadores,
            mitad: mitadElegida,
            notas: $('#plato-nota').value.trim(),
        });

        $('#modal-plato').classList.remove('abierta');
        avisar(platoActual.nombre + ' añadido');
        pintarCarta();
    };

    // ═══════════════════════════════════════════════════════════════
    //  Destinos sueltos: para llevar y cabañas
    // ═══════════════════════════════════════════════════════════════
    let destinoTipo = 'llevar';

    function abrirModalDestino(tipo) {
        destinoTipo = tipo;
        const cuerpo = $('#destino-cuerpo');
        cuerpo.innerHTML = '';

        if (tipo === 'llevar') {
            $('#destino-titulo').textContent = 'Para llevar';
            $('#destino-sub').textContent = 'Un nombre para reconocer el pedido cuando lo recojan.';
            cuerpo.innerHTML = '<input type="text" id="destino-nombre" maxlength="60" ' +
                'placeholder="Nombre del cliente" autocomplete="off">';
        } else {
            $('#destino-titulo').textContent = 'A una cabaña';
            $('#destino-sub').textContent = 'Se carga a la cuenta del huésped alojado.';
            if (!alojados.length) {
                cuerpo.innerHTML = '<div class="nota calma">No hay nadie alojado ahora mismo.</div>';
            }
            alojados.forEach((a) => {
                const l = document.createElement('label');
                l.className = 'opcion';
                l.innerHTML = '<input type="radio" name="alojado" value="' + a.id + '">' +
                    '<span>' + escapar((a.unidad || 'Sin cabaña') + ' · ' + a.nombre + ' ' + a.apellidos) + '</span>';
                cuerpo.appendChild(l);
            });
        }

        $('#modal-destino').classList.add('abierta');
    }

    $('#destino-aceptar').onclick = () => {
        if (destinoTipo === 'llevar') {
            const nombre = ($('#destino-nombre') || {}).value;
            if (!nombre || !nombre.trim()) { avisar('Pon un nombre.', true); return; }
            abrirDestino('llevar-' + uuid(), { nombre: nombre.trim() });
        } else {
            const elegido = $('#destino-cuerpo').querySelector('input:checked');
            if (!elegido) { avisar('Elige un huésped.', true); return; }
            const a = alojados.find((x) => String(x.id) === elegido.value);
            abrirDestino('cabana-' + a.id, {
                reserva_id: Number(a.id),
                nombre: (a.unidad || 'Cabaña') + ' · ' + a.nombre,
            });
        }
        $('#modal-destino').classList.remove('abierta');
    };

    // ═══════════════════════════════════════════════════════════════
    //  Navegación
    // ═══════════════════════════════════════════════════════════════
    function irA(destino) {
        vista = destino;
        document.querySelectorAll('.vista').forEach((v) => v.classList.remove('activa'));
        $('#v-' + destino).classList.add('activa');

        // La barra de acción solo tiene sentido con una comanda delante
        $('#accion').classList.toggle('visible', destino === 'comanda' || destino === 'carta');
        $('#inferior').style.display = (destino === 'comanda' || destino === 'carta') ? 'none' : 'flex';

        document.querySelectorAll('#inferior button').forEach((b) => {
            b.classList.toggle('activa', b.dataset.ir === destino);
        });

        window.scrollTo(0, 0);

        // Al abrir una mesa se reavisa al TPV aunque no se toque nada: si no,
        // un borrador de hace rato no aparecería, y el que ya se está viendo
        // caducaría a los 8 minutos mientras el camarero sigue en la mesa
        if (destino === 'comanda') { pintarComanda(); cargarEnviadas(); avisarAlTpv(claveActual); }
        if (destino === 'carta')   { pintarCarta(); }
        if (destino === 'listos')  { pintarListos(); }
        if (destino === 'mesas')   { pintarMesas(); pintarAvisos(); }
    }

    document.querySelectorAll('#inferior button').forEach((b) => {
        b.onclick = () => irA(b.dataset.ir);
    });
    $('#btn-volver').onclick = () => { claveActual = null; irA('mesas'); };
    $('#btn-volver-carta').onclick = () => irA('comanda');
    $('#btn-llevar').onclick = () => abrirModalDestino('llevar');
    $('#btn-cabana').onclick = () => abrirModalDestino('cabana');
    $('#btn-enviar').onclick = enviarRonda;

    document.querySelectorAll('[data-cerrar]').forEach((b) => {
        b.onclick = () => b.closest('.capa').classList.remove('abierta');
    });
    document.querySelectorAll('.capa').forEach((c) => {
        c.onclick = (e) => { if (e.target === c) { c.classList.remove('abierta'); } };
    });

    // Buscador
    $('#buscar').oninput = function () {
        filtro = this.value.trim();
        $('#btn-limpiar').style.display = filtro ? 'grid' : 'none';
        pintarCarta();
    };
    $('#btn-limpiar').onclick = () => {
        $('#buscar').value = ''; filtro = '';
        $('#btn-limpiar').style.display = 'none';
        pintarCarta();
    };

    // Salir: con rondas sin enviar hay que avisar, se perderían
    $('#btn-salir').onclick = () => {
        const pendientes = cola.length +
            Object.values(borradores).reduce((a, b) => a + b.lineas.length, 0);
        if (pendientes > 0 &&
            !confirm('Tienes cosas sin enviar. Si sales ahora se quedan en este teléfono hasta que vuelvas a entrar. ¿Salir?')) {
            return;
        }
        const f = document.createElement('form');
        f.method = 'post';
        f.action = BASE + '/salir';
        f.innerHTML = '<input type="hidden" name="<?= csrf_token() ?>" value="' + token + '">';
        document.body.appendChild(f);
        f.submit();
    };

    // ═══════════════════════════════════════════════════════════════
    //  Arranque
    // ═══════════════════════════════════════════════════════════════
    window.addEventListener('online',  () => { pintarEstado(); sincronizar(); });
    window.addEventListener('offline', () => { pintarEstado(); avisar('Sin conexión: se sigue tomando nota igual.'); });

    if (carta && carta.length && categoriaId === null) { categoriaId = carta[0].id; }
    pintarTodo();
    refrescar();
    sincronizar();

    // Latido: mesas y platos listos. Solo en las pantallas donde se ven.
    setInterval(() => { if (vista === 'mesas' || vista === 'listos') { pulso(); } }, 20000);

    // Mantiene vivo en el TPV el borrador de la mesa que se está atendiendo
    setInterval(() => {
        const b = claveActual ? borrador(claveActual) : null;
        if (b && b.lineas.length > 0) { avisarAlTpv(claveActual); }
    }, 120000);
    setInterval(sincronizar, 15000);

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(<?= json_encode(base_url('sw.js')) ?>)
            .catch(function (e) { console.warn('Service worker no registrado', e); });
    }
})();
</script>


<?= view("partes/espera") ?>
</body>
</html>
