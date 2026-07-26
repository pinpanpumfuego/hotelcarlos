<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TPV · <?= esc(config('Hotel')->nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* ── Tema claro (por defecto): cómodo con luz de día y en jornadas largas ── */
        :root {
            --fondo: #eef1ee;          /* gris verdoso suave, nunca blanco puro */
            --panel: #ffffff;
            --panel-claro: #f4f6f3;
            --borde: #d9ded8;
            --texto: #22302a;          /* verde muy oscuro, más suave que el negro */
            --texto-suave: #6d7a72;
            --verde: #2f7d52;
            --verde-oscuro: #24603f;
            --ambar: #9d6220;
            --ambar-suave: #fdf3e4;
            --rojo: #b3454a;
            --azul: #2e6f8e;
            --sombra: 0 1px 3px rgba(34, 48, 42, .10);
            --alto-boton: 68px;
        }

        /* ── Modo noche: apagado, sin negros duros ── */
        [data-tema="noche"] {
            --fondo: #1b2129;
            --panel: #232b34;
            --panel-claro: #2c353f;
            --borde: #3a4550;
            --texto: #dfe6ea;
            --texto-suave: #9aa7b1;
            --verde: #4fa87a;
            --verde-oscuro: #377a58;
            --ambar: #d8a05a;
            --ambar-suave: #3a3020;
            --rojo: #c9686d;
            --azul: #5b9ac4;
            --sombra: 0 1px 3px rgba(0, 0, 0, .3);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body {
            margin: 0; height: 100%; overflow: hidden;
            background: var(--fondo); color: var(--texto);
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            font-size: 16px; user-select: none;
        }
        button { font-family: inherit; font-size: 1rem; color: inherit; cursor: pointer; border: none; }
        button:active { transform: scale(.97); }

        /* ── Cabecera ── */
        .cabecera {
            height: 58px; display: flex; align-items: center; gap: 12px;
            padding: 0 14px; background: var(--panel); border-bottom: 1px solid var(--borde);
        }
        .cabecera .titulo { font-weight: 700; font-size: 1.05rem; }
        .cabecera .sub { color: var(--texto-suave); font-size: .85rem; }
        .btn-cab {
            background: var(--panel-claro); border: 1px solid var(--borde); border-radius: 10px;
            padding: 10px 14px; min-height: 44px; display: flex; align-items: center; gap: 6px;
        }
        .btn-cab:active { background: var(--borde); }

        /* ── Cuerpo ── */
        .cuerpo { height: calc(100% - 58px); display: flex; }
        .vista { display: none; width: 100%; height: 100%; }
        .vista.activa { display: flex; }

        /* ── Mapa de mesas ── */
        #vista-mesas { flex-direction: column; overflow-y: auto; padding: 18px; }
        .zona-titulo {
            color: var(--texto-suave); text-transform: uppercase; letter-spacing: .12em;
            font-size: .75rem; margin: 18px 0 10px;
        }
        .rejilla-mesas { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
        .mesa {
            background: var(--panel); border: 2px solid var(--borde); border-radius: 14px;
            padding: 16px 12px; min-height: 118px; text-align: left; box-shadow: var(--sombra);
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .mesa .nombre { font-weight: 700; font-size: 1.1rem; }
        .mesa .dato { color: var(--texto-suave); font-size: .85rem; }
        .mesa.ocupada { border-color: var(--ambar); background: var(--ambar-suave); }
        .mesa.ocupada .total { color: var(--ambar); font-weight: 700; font-size: 1.15rem; }
        .mesa.libre .estado { color: var(--verde); font-weight: 600; }

        /* ── Comanda: carta + ticket ── */
        #vista-comanda { flex-direction: row; }
        .zona-carta { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .categorias {
            display: flex; gap: 8px; padding: 12px; overflow-x: auto;
            background: var(--panel); border-bottom: 1px solid var(--borde);
        }
        .cat {
            flex: 0 0 auto; padding: 12px 20px 10px; border-radius: 12px; font-weight: 600;
            background: var(--panel-claro); color: var(--texto); min-height: 56px;
            border: 1px solid var(--borde); border-bottom: 4px solid var(--cat, var(--borde));
        }
        .cat.activa { background: var(--panel); box-shadow: var(--sombra); border-color: var(--cat, var(--borde)); }
        .productos {
            flex: 1; overflow-y: auto; padding: 12px;
            display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px; align-content: start;
        }
        .producto {
            background: var(--panel); border: 1px solid var(--borde); border-radius: 12px;
            padding: 14px 12px; min-height: 92px; text-align: left; box-shadow: var(--sombra);
            display: flex; flex-direction: column; justify-content: space-between; gap: 6px;
        }
        .producto:active { background: var(--panel-claro); }
        .producto .pnombre { font-weight: 600; line-height: 1.25; }
        .producto .pprecio { color: var(--verde); font-weight: 700; }

        /* ── Ticket ── */
        .ticket {
            width: 380px; flex: 0 0 380px; background: var(--panel);
            border-left: 1px solid var(--borde); display: flex; flex-direction: column;
        }
        .ticket-cab { padding: 12px 14px; border-bottom: 1px solid var(--borde); }
        .ticket-cab .numero { font-weight: 700; font-size: 1.05rem; }
        .ticket-cab .donde { color: var(--texto-suave); font-size: .85rem; }
        .chip-cliente {
            width: 100%; margin-top: 10px; display: flex; align-items: center; gap: 8px;
            background: var(--panel-claro); border: 1px dashed var(--borde); border-radius: 10px;
            padding: 10px 12px; min-height: 48px; text-align: left; font-size: .9rem;
        }
        .chip-cliente.asignado { border-style: solid; border-color: var(--verde); background: rgba(47,125,82,.08); }
        .chip-cliente.asignado #ico-cliente { color: var(--verde); }
        .chip-cliente span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lineas { flex: 1; overflow-y: auto; padding: 6px; }
        .linea {
            width: 100%; text-align: left; background: transparent; border-radius: 10px;
            padding: 10px 12px; display: flex; gap: 10px; align-items: flex-start;
            border: 2px solid transparent;
        }
        .linea.sel { background: var(--panel-claro); border-color: var(--azul); }
        .linea .cant {
            background: var(--panel-claro); border: 1px solid var(--borde); border-radius: 8px;
            min-width: 34px; text-align: center; padding: 3px 6px; font-weight: 700;
        }
        /* Estados del plato: nuevo → en cocina/barra → listo → servido */
        .linea.en_cocina .cant, .linea.en_barra .cant { background: var(--ambar); border-color: var(--ambar); color: #fff; }
        .estado-plato.en_barra { color: var(--azul); }
        .linea.listo .cant { background: var(--verde); border-color: var(--verde); color: #fff; }
        .linea.servido .cant { background: var(--panel-claro); color: var(--texto-suave); }
        .linea.servido .nom { color: var(--texto-suave); }
        .estado-plato { font-size: .78rem; display: block; }
        .estado-plato.en_cocina { color: var(--ambar); }
        .estado-plato.listo { color: var(--verde); font-weight: 700; }
        .estado-plato.servido { color: var(--texto-suave); }
        .aviso-listos {
            margin: 6px 10px; padding: 10px 12px; border-radius: 10px;
            background: rgba(47,125,82,.12); border: 1px solid var(--verde);
            color: var(--verde); font-weight: 600; font-size: .9rem;
            display: none; align-items: center; gap: 8px;
        }
        .aviso-listos.ver { display: flex; }
        .linea .desc { flex: 1; min-width: 0; }
        .linea .nom { font-weight: 600; }
        .linea .nota { color: var(--ambar); font-size: .8rem; }
        .linea .imp { font-weight: 700; white-space: nowrap; }
        .vacio { color: var(--texto-suave); text-align: center; padding: 40px 20px; }

        .acciones-linea { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; padding: 6px 10px; }
        .acciones-linea button {
            background: var(--panel-claro); border-radius: 10px; padding: 12px 4px;
            min-height: 52px; font-size: .82rem; display: flex; flex-direction: column;
            align-items: center; gap: 3px; border: 1px solid var(--borde);
        }
        .acciones-linea button:disabled { opacity: .35; }
        .acciones-linea i { font-size: 1.15rem; }

        .totales { padding: 12px 14px; border-top: 1px solid var(--borde); }
        .fila-total { display: flex; justify-content: space-between; margin-bottom: 6px; color: var(--texto-suave); }
        .fila-total.grande { color: var(--texto); font-size: 1.6rem; font-weight: 700; margin-bottom: 0; }
        .acciones { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 10px 14px 14px; }
        .btn {
            border-radius: 12px; min-height: var(--alto-boton); font-weight: 700; font-size: 1rem;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            background: var(--panel-claro); border: 1px solid var(--borde);
        }
        .btn.verde { background: var(--verde); border-color: var(--verde); color: #fff; }
        .btn.ambar { background: var(--ambar); border-color: var(--ambar); color: #fff; }
        .btn.rojo { background: transparent; border-color: var(--rojo); color: var(--rojo); }
        .btn.ancho { grid-column: span 2; }
        .btn:disabled { opacity: .4; }
        .insignia {
            background: rgba(0, 0, 0, .18); color: inherit; border-radius: 999px;
            padding: 1px 8px; font-size: .8rem; margin-left: 4px;
        }

        /* ── Modales ── */
        .capa {
            position: fixed; inset: 0; background: rgba(22, 32, 27, .55);
            display: none; align-items: center; justify-content: center; padding: 20px; z-index: 50;
        }
        .capa.abierta { display: flex; }
        .modal-pos {
            background: var(--panel); border: 1px solid var(--borde); border-radius: 18px;
            padding: 20px; width: 100%; max-width: 460px; max-height: 92vh; overflow-y: auto;
        }
        .modal-pos h2 { margin: 0 0 4px; font-size: 1.2rem; }
        .modal-pos .ayuda { color: var(--texto-suave); font-size: .88rem; margin: 0 0 14px; }
        .visor {
            background: var(--fondo); border: 1px solid var(--borde); border-radius: 12px;
            padding: 14px; font-size: 2rem; font-weight: 700; text-align: right; margin-bottom: 12px;
            min-height: 66px; word-break: break-all;
        }
        .teclado { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .teclado button {
            background: var(--panel-claro); border: 1px solid var(--borde); border-radius: 12px;
            min-height: 62px; font-size: 1.35rem; font-weight: 600;
        }
        .rapidos { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px; }
        .rapidos button {
            background: var(--panel-claro); border: 1px solid var(--borde); border-radius: 10px;
            min-height: 52px; font-weight: 600;
        }
        .formas { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
        .forma {
            background: var(--panel-claro); border: 2px solid var(--borde); border-radius: 12px;
            min-height: 62px; font-weight: 600; display: flex; align-items: center;
            justify-content: center; gap: 8px; padding: 8px;
        }
        .forma.sel { border-color: var(--verde); background: rgba(47,125,82,.12); }
        .campo {
            width: 100%; background: var(--fondo); border: 1px solid var(--borde); border-radius: 10px;
            padding: 14px; color: var(--texto); font-size: 1rem; margin-bottom: 12px;
        }
        .pestanas { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
        .pest {
            background: var(--panel-claro); border: 2px solid var(--borde); border-radius: 12px;
            min-height: 56px; font-weight: 600; display: flex; align-items: center;
            justify-content: center; gap: 6px; padding: 8px; font-size: .92rem;
        }
        .pest.activa { border-color: var(--verde); background: rgba(47,125,82,.10); }
        .opcion-cliente {
            width: 100%; background: var(--panel-claro); border: 1px solid var(--borde);
            border-radius: 10px; padding: 12px 14px; min-height: 56px; margin-bottom: 8px;
            text-align: left; display: flex; align-items: center; gap: 10px;
        }
        .opcion-cliente:active { background: var(--borde); }
        .opcion-cliente .cab { font-weight: 700; }
        .opcion-cliente .quien { color: var(--texto-suave); font-size: .85rem; }

        /* Personalización del producto */
        .grupo-mod { margin-bottom: 14px; }
        .grupo-mod h3 { font-size: .95rem; margin: 0 0 6px; }
        .grupo-mod .obliga { color: var(--ambar); font-size: .8rem; font-weight: 600; }
        .opciones-mod { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .op-mod {
            background: var(--panel-claro); border: 2px solid var(--borde); border-radius: 10px;
            padding: 12px; min-height: 56px; text-align: left; font-size: .9rem;
            display: flex; justify-content: space-between; align-items: center; gap: 6px;
        }
        .op-mod.sel { border-color: var(--verde); background: rgba(47,125,82,.12); }
        .op-mod .extra { color: var(--verde); font-weight: 700; font-size: .82rem; }
        .marcas-ficha { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
        .marca-ficha {
            font-size: .78rem; border-radius: 999px; padding: 2px 10px;
            background: var(--panel-claro); border: 1px solid var(--borde);
        }
        .marca-ficha.dieta { color: var(--verde); border-color: var(--verde); }
        .marca-ficha.alergeno { color: var(--rojo); border-color: var(--rojo); }
        .linea .mods { display: block; color: var(--texto-suave); font-size: .8rem; }
        .cambio { background: rgba(47,125,82,.10); border: 1px solid var(--verde); border-radius: 12px; padding: 12px; margin-bottom: 12px; }
        .cambio .valor { font-size: 1.8rem; font-weight: 700; color: var(--verde); }

        /* ── Aviso flotante ── */
        /* ── Bloqueo del TPV compartido ── */
        .capa-bloqueo {
            position: fixed; inset: 0; z-index: 200; display: none;
            align-items: center; justify-content: center; padding: 20px;
            background: linear-gradient(165deg, #143425 0%, #1f4d36 60%, #26593f 100%);
        }
        .capa-bloqueo.abierta { display: flex; }
        .panel-bloqueo {
            background: var(--panel); border-radius: 20px; padding: 24px;
            width: 100%; max-width: 380px; text-align: center;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .3);
        }
        .panel-bloqueo h2 { margin: 4px 0 2px; font-size: 1.3rem; }
        .marca-bloqueo {
            width: 54px; height: 54px; margin: 0 auto 10px; border-radius: 50%;
            background: #e9f4ee; color: var(--verde);
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        }
        .visor-pin { display: flex; justify-content: center; gap: 14px; margin: 14px 0 10px; }
        .punto {
            width: 16px; height: 16px; border-radius: 50%;
            border: 2px solid var(--borde); transition: all .16s ease;
        }
        .punto.lleno { background: var(--verde); border-color: var(--verde); transform: scale(1.12); }
        .capa-bloqueo .teclado, #modal-autoriza .teclado {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; margin-top: 6px;
        }
        .capa-bloqueo .teclado button, #modal-autoriza .teclado button {
            font: inherit; font-size: 1.45rem; font-weight: 600; padding: 15px 0;
            border-radius: 13px; border: 1px solid var(--borde); background: #fbfdfb;
            color: var(--tinta); cursor: pointer; transition: transform .08s ease;
        }
        .capa-bloqueo .teclado button:active, #modal-autoriza .teclado button:active { transform: scale(.94); }
        .capa-bloqueo .teclado button.gris, #modal-autoriza .teclado button.gris {
            color: var(--tinta-suave); font-size: 1.05rem;
        }
        .oculto { display: none !important; }

        /* Propina sugerida: «Sin propina» tiene el mismo peso visual que el
           resto, porque la ley la define como voluntaria. */
        .propinas-rapidas { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 10px; }
        .propinas-rapidas .btn-propina {
            font: inherit; font-size: 1.05rem; font-weight: 600; padding: 14px 4px;
            border-radius: 13px; border: 1px solid var(--borde); background: #fbfdfb;
            color: var(--tinta); cursor: pointer; display: flex; flex-direction: column;
            align-items: center; gap: 2px; transition: transform .08s ease;
        }
        .propinas-rapidas .btn-propina:active { transform: scale(.95); }
        .propinas-rapidas .btn-propina small { font-size: .72rem; font-weight: 400; color: var(--tinta-suave); }

        /* Quién está en la pantalla */
        .chip-camarero {
            display: inline-flex; align-items: center; gap: 7px; cursor: pointer;
            background: rgba(255,255,255,.14); border: 0; border-radius: 99px;
            padding: 5px 12px 5px 5px; color: #fff; font: inherit; font-size: .84rem;
        }
        .chip-camarero:hover { background: rgba(255,255,255,.22); }
        .chip-camarero .inicial {
            width: 26px; height: 26px; border-radius: 50%; background: #e3c9a4; color: #143425;
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .8rem;
        }

        #aviso {
            position: fixed; left: 50%; bottom: 26px; transform: translateX(-50%) translateY(120%);
            background: var(--panel); border: 1px solid var(--borde); border-left: 5px solid var(--verde);
            border-radius: 12px; padding: 14px 20px; max-width: 90vw; z-index: 90;
            transition: transform .25s ease; box-shadow: 0 8px 26px rgba(34, 48, 42, .22);
        }
        #aviso.ver { transform: translateX(-50%) translateY(0); }
        #aviso.error { border-left-color: var(--rojo); }

        @media (max-width: 900px) {
            .ticket { width: 320px; flex-basis: 320px; }
            .productos { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); }
        }
        @media (max-width: 680px) {
            #vista-comanda { flex-direction: column; }
            .ticket { width: 100%; flex: 1 1 auto; border-left: none; border-top: 1px solid var(--borde); }
            .zona-carta { flex: 0 0 46%; }
        }
    </style>
</head>
<body>

<header class="cabecera">
    <button class="btn-cab" id="btn-volver" style="display:none">
        <i class="bi bi-grid-3x3-gap"></i> Mesas
    </button>
    <div>
        <div class="titulo" id="cab-titulo">Mesas</div>
        <div class="sub" id="cab-sub"><?= esc(config('Hotel')->nombre) ?></div>
    </div>
    <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
        <?php if ($compartido): ?>
            <button class="chip-camarero" id="chip-camarero" title="Cambiar de camarero">
                <span class="inicial" id="cam-inicial">?</span>
                <span id="cam-nombre">Nadie</span>
            </button>
        <?php endif ?>
        <span class="sub" id="reloj"></span>
        <span class="sub d-none" id="aviso-caja" style="color:var(--ambar); display:none;">
            <i class="bi bi-exclamation-triangle"></i> Sin turno de caja
        </span>
        <button class="btn-cab" id="btn-tema" title="Cambiar entre modo día y noche"><i class="bi bi-moon"></i></button>
        <button class="btn-cab" id="btn-suelta"><i class="bi bi-bag"></i> Para llevar</button>
        <a class="btn-cab" href="<?= site_url('cocina') ?>" style="text-decoration:none" title="Pantalla de cocina">
            <i class="bi bi-fire"></i><span class="insignia" id="ins-listos" style="display:none">0</span>
        </a>
        <a class="btn-cab" href="<?= site_url('barra') ?>" style="text-decoration:none" title="Pantalla de barra">
            <i class="bi bi-cup-straw"></i>
        </a>
        <a class="btn-cab" href="<?= site_url('panel') ?>" style="text-decoration:none"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</header>

<div class="cuerpo">
    <!-- ═══ Mapa de mesas ═══ -->
    <section class="vista activa" id="vista-mesas">
        <div id="contenedor-mesas"></div>
    </section>

    <!-- ═══ Comanda ═══ -->
    <section class="vista" id="vista-comanda">
        <div class="zona-carta">
            <div class="categorias" id="categorias"></div>
            <div class="productos" id="productos"></div>
        </div>

        <aside class="ticket">
            <div class="ticket-cab">
                <div class="numero" id="tk-numero">—</div>
                <div class="donde" id="tk-donde"></div>
                <button class="chip-cliente" id="btn-cliente">
                    <i class="bi bi-person" id="ico-cliente"></i>
                    <span id="tk-cliente">Sin cliente asignado</span>
                    <i class="bi bi-pencil" style="margin-left:auto; opacity:.6"></i>
                </button>
            </div>
            <div class="aviso-listos" id="aviso-listos">
                <i class="bi bi-bell-fill"></i><span id="texto-listos"></span>
            </div>
            <div class="lineas" id="tk-lineas"></div>

            <div class="acciones-linea" id="acciones-linea">
                <button id="acc-menos" disabled><i class="bi bi-dash-lg"></i>Quitar</button>
                <button id="acc-mas" disabled><i class="bi bi-plus-lg"></i>Añadir</button>
                <button id="acc-cant" disabled><i class="bi bi-123"></i>Cantidad</button>
                <button id="acc-nota" disabled><i class="bi bi-chat-left-text"></i>Nota</button>
            </div>
            <div style="padding:6px 10px; display:none" id="zona-servir">
                <button class="btn verde" style="width:100%" id="acc-servir">
                    <i class="bi bi-check2-all"></i> Marcar como servido
                </button>
            </div>

            <div class="totales">
                <div class="fila-total" id="fila-descuento" style="display:none">
                    <span>Descuento</span><span id="tk-descuento">$0</span>
                </div>
                <div class="fila-total" id="fila-propina" style="display:none">
                    <span>Propina</span><span id="tk-propina">$0</span>
                </div>
                <div class="fila-total" id="fila-pagado" style="display:none">
                    <span>Ya cobrado</span><span id="tk-pagado">$0</span>
                </div>
                <div class="fila-total grande">
                    <span id="tk-etiqueta-total">Total</span><span id="tk-total">$0</span>
                </div>
            </div>

            <div class="acciones">
                <button class="btn ambar ancho" id="btn-cocina">
                    <i class="bi bi-fire"></i> <span id="texto-cocina">Enviar a cocina</span>
                    <span class="insignia" id="ins-pendientes">0</span>
                </button>
                <button class="btn" id="btn-descuento"><i class="bi bi-tag"></i> Descuento</button>
                <button class="btn" id="btn-cupon"><i class="bi bi-ticket-perforated"></i> Cupón</button>
                <button class="btn" id="btn-propina"><i class="bi bi-coin"></i> Propina</button>
                <button class="btn" id="btn-recibo"><i class="bi bi-printer"></i> Recibo</button>
                <button class="btn rojo" id="btn-anular"><i class="bi bi-x-lg"></i> Anular</button>
                <button class="btn verde ancho" id="btn-cobrar"><i class="bi bi-cash-coin"></i> Cobrar</button>
            </div>
        </aside>
    </section>
</div>

<!-- ═══ Modal teclado numérico ═══ -->
<div class="capa" id="modal-teclado">
    <div class="modal-pos">
        <h2 id="tec-titulo">Cantidad</h2>
        <p class="ayuda" id="tec-ayuda"></p>
        <div class="visor" id="tec-visor">0</div>
        <div class="rapidos" id="tec-rapidos" style="display:none"></div>
        <div class="teclado">
            <button data-tec="1">1</button><button data-tec="2">2</button><button data-tec="3">3</button>
            <button data-tec="4">4</button><button data-tec="5">5</button><button data-tec="6">6</button>
            <button data-tec="7">7</button><button data-tec="8">8</button><button data-tec="9">9</button>
            <button data-tec="000">000</button><button data-tec="0">0</button>
            <button data-tec="borrar"><i class="bi bi-backspace"></i></button>
        </div>
        <div class="acciones" style="padding:14px 0 0;">
            <button class="btn" data-cerrar>Cancelar</button>
            <button class="btn verde" id="tec-aceptar">Aceptar</button>
        </div>
    </div>
</div>

<!-- ═══ Modal cobro ═══ -->
<div class="capa" id="modal-cobro">
    <div class="modal-pos">
        <h2>Cobrar comanda</h2>
        <p class="ayuda">Pendiente de cobro: <strong id="cob-total">$0</strong></p>

        <p class="ayuda" style="margin-bottom:6px">Dividir la cuenta</p>
        <div class="rapidos" id="cob-dividir"></div>
        <div class="visor" id="cob-importe" style="font-size:1.35rem">$0</div>

        <div class="formas" id="cob-formas"></div>
        <div id="cob-bono" style="display:none">
            <p class="ayuda">Código del bono regalo</p>
            <input type="text" class="campo" id="cob-bono-codigo" placeholder="BR-XXXX-XXXX"
                   autocomplete="off" style="text-transform:uppercase">
            <p class="ayuda">Se usa el saldo que tenga; si no llega, se cobra el resto con otra forma de pago.</p>
        </div>
        <div id="cob-efectivo">
            <p class="ayuda">Efectivo recibido</p>
            <div class="visor" id="cob-visor">0</div>
            <div class="rapidos" id="cob-rapidos"></div>
            <div class="teclado" id="cob-teclado">
                <button data-tec="1">1</button><button data-tec="2">2</button><button data-tec="3">3</button>
                <button data-tec="4">4</button><button data-tec="5">5</button><button data-tec="6">6</button>
                <button data-tec="7">7</button><button data-tec="8">8</button><button data-tec="9">9</button>
                <button data-tec="000">000</button><button data-tec="0">0</button>
                <button data-tec="borrar"><i class="bi bi-backspace"></i></button>
            </div>
            <div class="cambio" id="cob-cambio" style="display:none">
                Cambio a devolver<div class="valor" id="cob-cambio-valor">$0</div>
            </div>
        </div>
        <div class="acciones" style="padding:14px 0 0;">
            <button class="btn" data-cerrar>Cancelar</button>
            <button class="btn verde" id="cob-confirmar">Confirmar cobro</button>
        </div>
    </div>
</div>

<!-- ═══ Modal personalizar producto ═══ -->
<div class="capa" id="modal-producto">
    <div class="modal-pos" style="max-width: 560px;">
        <h2 id="prod-nombre"></h2>
        <p class="ayuda" id="prod-ficha"></p>
        <div id="prod-grupos"></div>
        <div id="prod-mitades" style="display:none">
            <p class="ayuda" style="margin-bottom:6px"><strong>¿Mitad y mitad?</strong> Elige el otro sabor (se cobra el más caro).</p>
            <div id="prod-lista-mitades"></div>
        </div>
        <label class="ayuda" style="margin:12px 0 4px; display:block">Nota para preparación (opcional)</label>
        <input type="text" class="campo" id="prod-nota" placeholder="Ej.: poco sal, para compartir" autocomplete="off">
        <div class="acciones" style="padding:6px 0 0;">
            <button class="btn" data-cerrar>Cancelar</button>
            <button class="btn verde" id="prod-aceptar">Añadir <span id="prod-precio"></span></button>
        </div>
    </div>
</div>

<!-- ═══ Modal cliente ═══ -->
<div class="capa" id="modal-cliente">
    <div class="modal-pos">
        <h2>¿Quién es el cliente?</h2>
        <p class="ayuda">Asigna un huésped para poder cargar el consumo a su cabaña, o registra un cliente ocasional.</p>

        <div class="pestanas">
            <button class="pest activa" data-pest="huesped"><i class="bi bi-house-heart"></i> Huésped alojado</button>
            <button class="pest" data-pest="ocasional"><i class="bi bi-person"></i> Cliente ocasional</button>
        </div>

        <div id="panel-huesped">
            <input type="search" class="campo" id="cli-buscar" placeholder="Buscar por cabaña o nombre" autocomplete="off">
            <div id="cli-lista"></div>
        </div>

        <div id="panel-ocasional" style="display:none">
            <input type="text" class="campo" id="cli-nombre" placeholder="Nombre del cliente *" autocomplete="off">
            <input type="text" class="campo" id="cli-documento" placeholder="Documento (opcional)" autocomplete="off">
            <input type="tel" class="campo" id="cli-telefono" placeholder="Teléfono (opcional)" autocomplete="off">
            <button class="btn verde" style="width:100%" id="cli-guardar-ocasional">
                <i class="bi bi-check-lg"></i> Asignar cliente
            </button>
        </div>

        <div class="acciones" style="padding:14px 0 0;">
            <button class="btn" data-cerrar>Cancelar</button>
            <button class="btn rojo" id="cli-quitar"><i class="bi bi-person-dash"></i> Quitar cliente</button>
        </div>
    </div>
</div>

<!-- ═══ Modal texto (nota, descuento, anular, para llevar) ═══ -->
<div class="capa" id="modal-texto">
    <div class="modal-pos">
        <h2 id="txt-titulo"></h2>
        <p class="ayuda" id="txt-ayuda"></p>
        <div id="txt-extra"></div>
        <input type="text" class="campo" id="txt-campo" autocomplete="off">
        <div class="acciones" style="padding:6px 0 0;">
            <button class="btn" data-cerrar>Cancelar</button>
            <button class="btn verde" id="txt-aceptar">Aceptar</button>
        </div>
    </div>
</div>

<!-- ═══ Bloqueo del TPV compartido ═══ -->
<?php if ($compartido): ?>
<div class="capa-bloqueo" id="bloqueo">
    <div class="panel-bloqueo">
        <div class="marca-bloqueo">
            <i class="bi bi-lock-fill"></i>
        </div>
        <h2 id="blq-titulo">Identifícate</h2>
        <p class="ayuda" id="blq-ayuda">Pasa tu tarjeta o teclea tu PIN</p>

        <div class="visor-pin" id="blq-visor">
            <span class="punto"></span><span class="punto"></span>
            <span class="punto"></span><span class="punto"></span>
        </div>

        <div class="aviso error oculto" id="blq-error"></div>

        <div class="teclado">
            <button data-blq="1">1</button><button data-blq="2">2</button><button data-blq="3">3</button>
            <button data-blq="4">4</button><button data-blq="5">5</button><button data-blq="6">6</button>
            <button data-blq="7">7</button><button data-blq="8">8</button><button data-blq="9">9</button>
            <button class="gris" data-blq="limpiar">Borrar</button>
            <button data-blq="0">0</button>
            <button class="gris" data-blq="atras"><i class="bi bi-backspace"></i></button>
        </div>
    </div>
</div>

<!-- Autorización de un encargado para acciones delicadas -->
<div class="capa" id="modal-autoriza">
    <div class="modal-pos">
        <h2>Hace falta un encargado</h2>
        <p class="ayuda" id="aut-ayuda"></p>

        <div class="visor-pin" id="aut-visor">
            <span class="punto"></span><span class="punto"></span>
            <span class="punto"></span><span class="punto"></span>
        </div>

        <div class="aviso error oculto" id="aut-error"></div>

        <div class="teclado">
            <button data-aut="1">1</button><button data-aut="2">2</button><button data-aut="3">3</button>
            <button data-aut="4">4</button><button data-aut="5">5</button><button data-aut="6">6</button>
            <button data-aut="7">7</button><button data-aut="8">8</button><button data-aut="9">9</button>
            <button class="gris" data-aut="limpiar">Borrar</button>
            <button data-aut="0">0</button>
            <button class="gris" data-aut="atras"><i class="bi bi-backspace"></i></button>
        </div>

        <div class="acciones" style="padding:6px 0 0;">
            <button class="btn" data-cerrar>Cancelar</button>
        </div>
    </div>
</div>
<?php endif ?>

<div id="aviso"></div>

<script>
(function () {
    'use strict';

    const BASE = <?= json_encode(rtrim(site_url('pos/api'), '/')) ?>;
    let token = <?= json_encode(csrf_hash()) ?>;
    const NOMBRE_TOKEN = <?= json_encode(csrf_token()) ?>;

    const estado = { mesas: [], categorias: [], alojados: [], sinMesa: [], turnoCaja: false };
    let comanda = null;        // comanda abierta en pantalla
    let categoriaActiva = null;
    let lineaSel = null;

    // ── Utilidades ───────────────────────────────────────────────
    const $ = (sel) => document.querySelector(sel);
    const pesos = (n) => '$' + Math.round(n).toLocaleString('es-CO');

    function avisar(mensaje, esError) {
        const a = $('#aviso');
        a.textContent = mensaje;
        a.classList.toggle('error', !!esError);
        a.classList.add('ver');
        clearTimeout(a._t);
        a._t = setTimeout(() => a.classList.remove('ver'), 3800);
    }

    async function api(ruta, opciones) {
        const cfg = Object.assign({ headers: {} }, opciones || {});
        cfg.headers['X-Requested-With'] = 'XMLHttpRequest';
        if (cfg.body) {
            cfg.headers['Content-Type'] = 'application/json';
            cfg.headers['X-CSRF-TOKEN'] = token;
        }
        const res = await fetch(BASE + ruta, cfg);
        const nuevo = res.headers.get('X-CSRF-TOKEN');
        if (nuevo) token = nuevo;

        let datos = {};
        try { datos = await res.json(); } catch (e) { /* respuesta sin JSON */ }

        if (!res.ok) {
            // La pantalla se bloqueó mientras tanto
            if (datos.bloqueado) {
                bloquearPantalla(false);
                avisar(datos.error, true);
                return null;
            }

            // Hace falta que lo autorice un encargado: se pide y se reintenta
            if (datos.necesita_pin && cfg.body) {
                const pin = await pedirAutorizacion(datos.error);
                if (!pin) { return null; }

                const cuerpo = JSON.parse(cfg.body);
                cuerpo.pin_encargado = pin;

                return api(ruta, Object.assign({}, opciones, { body: JSON.stringify(cuerpo) }));
            }

            avisar(datos.error || 'No se pudo completar la operación.', true);
            return null;
        }
        return datos;
    }

    // ── TPV compartido: bloqueo e identificación ─────────────────
    const PROPINA_SUGERIDA = <?= (float) $propinaSugerida ?>;
    const COMPARTIDO = <?= $compartido ? 'true' : 'false' ?>;
    const SEG_BLOQUEO = <?= (int) $bloqueoSeg ?>;
    let camarero = <?= json_encode($camarero) ?>;
    let pinBlq = '';
    let relojInactividad = null;

    function pintarCamarero() {
        if (!COMPARTIDO) { return; }
        const chip = $('#chip-camarero');
        $('#cam-inicial').textContent = camarero ? camarero.inicial : '?';
        $('#cam-nombre').textContent = camarero ? camarero.nombre : 'Nadie';
        chip.title = camarero
            ? camarero.nombre + (camarero.rol === 'encargado' ? ' (encargado)' : '') + ' — toca para cambiar'
            : 'Identifícate';
    }

    function pintarVisor(selector, valor) {
        const puntos = $(selector).children;
        for (let i = 0; i < puntos.length; i++) {
            puntos[i].classList.toggle('lleno', i < valor.length);
        }
    }

    function errorBloqueo(texto) {
        const caja = $('#blq-error');
        caja.textContent = texto;
        caja.classList.remove('oculto');
        clearTimeout(caja._t);
        caja._t = setTimeout(() => caja.classList.add('oculto'), 4000);
    }

    /** Cierra la pantalla. avisarServidor=false cuando el servidor ya la cerró. */
    async function bloquearPantalla(avisarServidor) {
        camarero = null;
        pinBlq = '';
        pintarVisor('#blq-visor', '');
        pintarCamarero();
        $('#bloqueo').classList.add('abierta');
        clearTimeout(relojInactividad);

        if (avisarServidor !== false) {
            try { await api('/bloquear', { method: 'POST', body: '{}' }); } catch (e) { /* da igual */ }
        }
    }

    function reiniciarInactividad() {
        if (!COMPARTIDO || !camarero) { return; }
        clearTimeout(relojInactividad);
        relojInactividad = setTimeout(() => bloquearPantalla(true), SEG_BLOQUEO * 1000);
    }

    async function identificar(datos) {
        const r = await fetch(BASE + '/identificar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(datos),
        });
        const nuevo = r.headers.get('X-CSRF-TOKEN');
        if (nuevo) { token = nuevo; }

        let cuerpo = {};
        try { cuerpo = await r.json(); } catch (e) { /* sin JSON */ }

        if (!r.ok || !cuerpo.ok) {
            errorBloqueo(cuerpo.error || 'No se pudo comprobar.');
            pinBlq = '';
            pintarVisor('#blq-visor', '');
            return;
        }

        camarero = cuerpo.camarero;
        pinBlq = '';
        pintarVisor('#blq-visor', '');
        pintarCamarero();
        $('#bloqueo').classList.remove('abierta');
        avisar(cuerpo.mensaje);
        reiniciarInactividad();
    }

    if (COMPARTIDO) {
        pintarCamarero();
        if (!camarero) { $('#bloqueo').classList.add('abierta'); }

        document.querySelectorAll('[data-blq]').forEach(function (b) {
            b.addEventListener('click', function () {
                const t = b.dataset.blq;
                if (t === 'limpiar') { pinBlq = ''; }
                else if (t === 'atras') { pinBlq = pinBlq.slice(0, -1); }
                else if (pinBlq.length < 4) { pinBlq += t; }

                pintarVisor('#blq-visor', pinBlq);
                if (pinBlq.length === 4) { setTimeout(() => identificar({ pin: pinBlq }), 120); }
            });
        });

        $('#chip-camarero').onclick = () => bloquearPantalla(true);

        /**
         * Lector de tarjetas.
         *
         * Los lectores RFID baratos se comportan como un teclado: «escriben»
         * el número de la tarjeta y un Enter, mucho más rápido de lo que
         * teclea una persona. Se distingue por esa velocidad.
         */
        let bufferTarjeta = '';
        let ultimaTecla = 0;

        document.addEventListener('keydown', function (e) {
            const ahora = Date.now();
            const rapido = ahora - ultimaTecla < 60;
            ultimaTecla = ahora;

            if (e.key === 'Enter') {
                if (bufferTarjeta.length >= 4) {
                    identificar({ tarjeta: bufferTarjeta });
                }
                bufferTarjeta = '';
                return;
            }

            if (!/^[a-zA-Z0-9]$/.test(e.key)) { return; }

            // Si viene lento es una persona tecleando, no una tarjeta
            bufferTarjeta = rapido ? bufferTarjeta + e.key : e.key;

            // Teclado físico sobre la pantalla de bloqueo: también vale para el PIN
            if (!rapido && $('#bloqueo').classList.contains('abierta') && /^[0-9]$/.test(e.key)) {
                if (pinBlq.length < 4) {
                    pinBlq += e.key;
                    pintarVisor('#blq-visor', pinBlq);
                    if (pinBlq.length === 4) { setTimeout(() => identificar({ pin: pinBlq }), 120); }
                }
            }
        });

        // Cualquier gesto cuenta como actividad
        ['click', 'touchstart', 'keydown'].forEach(function (evento) {
            document.addEventListener(evento, reiniciarInactividad, { passive: true });
        });
        reiniciarInactividad();
    }

    // ── Autorización de un encargado ─────────────────────────────
    let resolverAutorizacion = null;
    let pinAut = '';

    /** Devuelve el PIN del encargado, o null si se cancela. */
    function pedirAutorizacion(motivo) {
        if (!COMPARTIDO) { return Promise.resolve(null); }

        pinAut = '';
        pintarVisor('#aut-visor', '');
        $('#aut-ayuda').textContent = motivo || 'Pide a un encargado que teclee su PIN.';
        $('#aut-error').classList.add('oculto');
        $('#modal-autoriza').classList.add('abierta');

        return new Promise(function (resolver) { resolverAutorizacion = resolver; });
    }

    function cerrarAutorizacion(pin) {
        $('#modal-autoriza').classList.remove('abierta');
        if (resolverAutorizacion) { resolverAutorizacion(pin); resolverAutorizacion = null; }
    }

    if (COMPARTIDO) {
        document.querySelectorAll('[data-aut]').forEach(function (b) {
            b.addEventListener('click', function () {
                const t = b.dataset.aut;
                if (t === 'limpiar') { pinAut = ''; }
                else if (t === 'atras') { pinAut = pinAut.slice(0, -1); }
                else if (pinAut.length < 4) { pinAut += t; }

                pintarVisor('#aut-visor', pinAut);
                if (pinAut.length === 4) { setTimeout(() => cerrarAutorizacion(pinAut), 140); }
            });
        });

        $('#modal-autoriza').querySelector('[data-cerrar]').addEventListener('click', function () {
            cerrarAutorizacion(null);
        });
    }

    // ── Vistas ───────────────────────────────────────────────────
    function mostrarVista(cual) {
        $('#vista-mesas').classList.toggle('activa', cual === 'mesas');
        $('#vista-comanda').classList.toggle('activa', cual === 'comanda');
        $('#btn-volver').style.display = cual === 'comanda' ? 'flex' : 'none';
        $('#btn-suelta').style.display = cual === 'mesas' ? 'flex' : 'none';
    }

    async function cargarEstado() {
        const datos = await api('/estado');
        if (!datos) return;
        Object.assign(estado, datos);
        $('#aviso-caja').style.display = estado.turnoCaja ? 'none' : 'inline';
        const ins = $('#ins-listos');
        ins.style.display = estado.listos > 0 ? 'inline' : 'none';
        ins.textContent = estado.listos;
        pintarMesas();
        if (categoriaActiva === null && estado.categorias.length) {
            categoriaActiva = estado.categorias[0].id;
        }
        pintarCategorias();
    }

    function pintarMesas() {
        const cont = $('#contenedor-mesas');
        cont.innerHTML = '';

        // Comandas sin mesa (para llevar / cabañas)
        if (estado.sinMesa.length) {
            cont.appendChild(tituloZona('Para llevar y cabañas'));
            const rej = document.createElement('div');
            rej.className = 'rejilla-mesas';
            estado.sinMesa.forEach((c) => {
                const b = document.createElement('button');
                b.className = 'mesa ocupada';
                const quien = c.unidad_nombre
                    ? c.unidad_nombre + ' · ' + c.h_nombre
                    : (c.cliente_nombre || c.mesa || 'Para llevar');
                b.innerHTML = '<div><div class="nombre">' + escaparHtml(quien) + '</div>'
                    + '<div class="dato">' + escaparHtml(c.numero) + '</div></div>'
                    + '<div class="total">' + pesos(c.total) + '</div>';
                b.onclick = () => abrirComanda(c.id);
                rej.appendChild(b);
            });
            cont.appendChild(rej);
        }

        // Mesas por zona
        const zonas = {};
        estado.mesas.forEach((m) => { (zonas[m.zona] = zonas[m.zona] || []).push(m); });

        Object.keys(zonas).forEach((zona) => {
            cont.appendChild(tituloZona(zona));
            const rej = document.createElement('div');
            rej.className = 'rejilla-mesas';
            zonas[zona].forEach((m) => {
                const b = document.createElement('button');
                b.className = 'mesa ' + (m.ocupada ? 'ocupada' : 'libre');
                if (m.ocupada) {
                    b.innerHTML = '<div><div class="nombre">' + escaparHtml(m.nombre) + '</div>'
                        + '<div class="dato"><i class="bi bi-clock"></i> ' + m.abierta_hace + ' min</div></div>'
                        + '<div class="total">' + pesos(m.total) + '</div>';
                } else {
                    b.innerHTML = '<div><div class="nombre">' + escaparHtml(m.nombre) + '</div>'
                        + '<div class="dato">' + m.capacidad + ' personas</div></div>'
                        + '<div class="estado">Libre</div>';
                }
                b.onclick = () => abrirMesa(m);
                rej.appendChild(b);
            });
            cont.appendChild(rej);
        });
    }

    function tituloZona(texto) {
        const h = document.createElement('div');
        h.className = 'zona-titulo';
        h.textContent = texto;
        return h;
    }

    function escaparHtml(t) {
        return String(t == null ? '' : t).replace(/[&<>"']/g, (c) => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        ));
    }

    // ── Carta ────────────────────────────────────────────────────
    function pintarCategorias() {
        const cont = $('#categorias');
        cont.innerHTML = '';
        estado.categorias.forEach((c) => {
            const b = document.createElement('button');
            b.className = 'cat' + (c.id === categoriaActiva ? ' activa' : '');
            b.style.setProperty('--cat', c.color);
            b.textContent = c.nombre;
            b.onclick = () => { categoriaActiva = c.id; pintarCategorias(); pintarProductos(); };
            cont.appendChild(b);
        });
        pintarProductos();
    }

    function pintarProductos() {
        const cont = $('#productos');
        cont.innerHTML = '';
        const cat = estado.categorias.find((c) => c.id === categoriaActiva);
        if (!cat) {
            cont.innerHTML = '<p class="vacio">La carta está vacía. Configúrala desde Gerencia → Carta.</p>';
            return;
        }
        cat.productos.forEach((p) => {
            const b = document.createElement('button');
            b.className = 'producto';
            b.style.borderLeft = '5px solid ' + cat.color;
            const marcas = []
                .concat((p.dietas || []).map(() => ''))
                .filter(Boolean);
            b.innerHTML = '<span class="pnombre">' + escaparHtml(p.nombre)
                + (p.alergenos && p.alergenos.length ? ' <i class="bi bi-exclamation-triangle" style="color:var(--rojo)" title="' + escaparHtml(p.alergenos.join(', ')) + '"></i>' : '')
                + (p.picante > 0 ? ' ' + '🌶'.repeat(p.picante) : '')
                + (p.divisible ? ' <i class="bi bi-pie-chart" title="Se puede pedir por mitades"></i>' : '')
                + '</span>'
                + '<span class="pprecio">' + pesos(p.precio) + '</span>';
            b.onclick = () => tocarProducto(p);
            cont.appendChild(b);
        });
        if (!cat.productos.length) {
            cont.innerHTML = '<p class="vacio">Sin productos en esta categoría.</p>';
        }
    }

    // ── Comanda ──────────────────────────────────────────────────
    async function abrirMesa(mesa) {
        const datos = await api('/abrir', { method: 'POST', body: JSON.stringify({ mesa_id: mesa.id }) });
        if (datos) abrirComanda(datos.comanda_id);
    }

    async function abrirComanda(id) {
        const datos = await api('/comanda/' + id);
        if (!datos) return;
        comanda = datos.comanda;
        lineaSel = null;
        mostrarVista('comanda');
        pintarComanda();
    }

    function pintarComanda() {
        if (!comanda) return;
        $('#cab-titulo').textContent = comanda.mesa || 'Comanda';
        $('#cab-sub').textContent = comanda.numero;
        $('#tk-numero').textContent = comanda.numero;
        $('#tk-donde').textContent = comanda.mesa || 'Para llevar';

        // Chip de cliente: huésped alojado, ocasional o sin asignar
        $('#tk-cliente').textContent = comanda.cliente_texto;
        const chip = $('#btn-cliente');
        chip.classList.toggle('asignado', comanda.cliente_tipo !== 'ninguno');
        $('#ico-cliente').className = 'bi ' + (comanda.cliente_tipo === 'huesped' ? 'bi-house-heart'
            : (comanda.cliente_tipo === 'ocasional' ? 'bi-person-check' : 'bi-person-plus'));

        const cont = $('#tk-lineas');
        cont.innerHTML = '';
        if (!comanda.lineas.length) {
            cont.innerHTML = '<p class="vacio">Toca un producto de la carta para empezar.</p>';
        }
        const textoEstado = {
            nuevo: '', en_cocina: 'En cocina', en_barra: 'En barra',
            listo: '¡Listo para servir!', servido: 'Servido',
        };

        comanda.lineas.forEach((l) => {
            const b = document.createElement('button');
            b.className = 'linea ' + l.estado + (lineaSel === l.id ? ' sel' : '');
            const mods = (l.modificadores || []).map((m) =>
                m.nombre + (m.precio_extra > 0 ? ' (+' + pesos(m.precio_extra) + ')' : '')
            ).join(' · ');

            b.innerHTML = '<span class="cant">' + l.cantidad + '</span>'
                + '<span class="desc"><span class="nom">' + escaparHtml(l.nombre_producto) + '</span>'
                + (mods ? '<span class="mods">' + escaparHtml(mods) + '</span>' : '')
                + (l.notas ? '<span class="nota"><i class="bi bi-chat-left-text"></i> ' + escaparHtml(l.notas) + '</span>' : '')
                + (textoEstado[l.estado] ? '<span class="estado-plato ' + l.estado + '">' + textoEstado[l.estado] + '</span>' : '')
                + '</span>'
                + '<span class="imp">' + pesos(l.subtotal) + '</span>';
            b.onclick = () => { lineaSel = (lineaSel === l.id ? null : l.id); pintarComanda(); };
            cont.appendChild(b);
        });

        // Aviso de platos que cocina ya terminó
        const avisoListos = $('#aviso-listos');
        avisoListos.classList.toggle('ver', comanda.listos > 0);
        if (comanda.listos > 0) {
            $('#texto-listos').textContent = comanda.listos === 1
                ? '1 plato listo en cocina · toca la línea verde y pulsa Servido'
                : comanda.listos + ' platos listos en cocina · márcalos como servidos al llevarlos';
        }

        const hayLinea = lineaSel !== null;
        const sel = lineaActual();
        ['#acc-menos', '#acc-mas', '#acc-cant', '#acc-nota'].forEach((s) => { $(s).disabled = !hayLinea; });

        // Se puede servir lo que ya está listo y, además, las bebidas de barra
        // (el cajero las prepara y sirve él mismo, sin esperar a la pantalla de barra)
        const mostrarServir = sel && (sel.estado === 'listo' || sel.estado === 'en_barra');
        $('#zona-servir').style.display = mostrarServir ? 'block' : 'none';
        $('#acciones-linea').style.display = mostrarServir ? 'none' : 'grid';
        if (mostrarServir) {
            $('#acc-servir').innerHTML = sel.estado === 'en_barra'
                ? '<i class="bi bi-cup-straw"></i> Preparada y servida'
                : '<i class="bi bi-check2-all"></i> Marcar como servido';
        }

        $('#fila-descuento').style.display = comanda.descuento > 0 ? 'flex' : 'none';
        $('#tk-descuento').textContent = '−' + pesos(comanda.descuento);
        $('#fila-propina').style.display = comanda.propina > 0 ? 'flex' : 'none';
        $('#tk-propina').textContent = pesos(comanda.propina);

        const parcial = comanda.pagado > 0;
        $('#fila-pagado').style.display = parcial ? 'flex' : 'none';
        $('#tk-pagado').textContent = pesos(comanda.pagado);
        $('#tk-etiqueta-total').textContent = parcial ? 'Pendiente' : 'Total';
        $('#tk-total').textContent = pesos(parcial ? comanda.pendiente : comanda.a_pagar);

        $('#ins-pendientes').textContent = comanda.pendientes;
        $('#btn-cocina').disabled = comanda.pendientes === 0;
        // Si lo nuevo no necesita preparación, el botón lo dice claramente
        $('#texto-cocina').textContent = comanda.pendientes > 0 && comanda.a_preparar === 0
            ? 'Marcar entregado' : 'Enviar a cocina';
        $('#btn-cobrar').disabled = comanda.pendiente <= 0;
        $('#btn-recibo').disabled = comanda.lineas.length === 0;
    }

    async function anadirProducto(productoId, extra) {
        if (!comanda) return;
        const cuerpo = Object.assign({ producto_id: productoId }, extra || {});
        const datos = await api('/comanda/' + comanda.id + '/anadir', {
            method: 'POST', body: JSON.stringify(cuerpo),
        });
        if (datos) { comanda = datos.comanda; pintarComanda(); }
    }

    // ── Personalización al añadir un producto ────────────────────
    const DIETAS = { apto_vegano: 'Vegano', apto_vegetariano: 'Vegetariano', sin_gluten: 'Sin gluten', sin_lactosa: 'Sin lactosa' };
    let prodActual = null;
    let seleccion = {};       // grupo_id → [ids de opciones]
    let mitadElegida = null;

    /** Devuelve todos los productos divisibles de la carta. */
    function divisibles(exceptoId) {
        const lista = [];
        estado.categorias.forEach((c) => c.productos.forEach((p) => {
            if (p.divisible && p.id !== exceptoId) lista.push(p);
        }));
        return lista;
    }

    function tocarProducto(producto) {
        const necesitaModal = (producto.grupos && producto.grupos.length) || producto.divisible;
        if (!necesitaModal) { anadirProducto(producto.id); return; }

        prodActual = producto;
        seleccion = {};
        mitadElegida = null;
        $('#prod-nombre').textContent = producto.nombre;
        $('#prod-nota').value = '';

        // Marcas de la ficha técnica: dietas, picante y alérgenos
        let ficha = '';
        (producto.dietas || []).forEach((d) => {
            ficha += '<span class="marca-ficha dieta">' + escaparHtml(DIETAS[d] || d) + '</span>';
        });
        if (producto.picante > 0) ficha += '<span class="marca-ficha">' + '🌶'.repeat(producto.picante) + '</span>';
        (producto.alergenos || []).forEach((a) => {
            ficha += '<span class="marca-ficha alergeno"><i class="bi bi-exclamation-triangle"></i> ' + escaparHtml(a) + '</span>';
        });
        $('#prod-ficha').innerHTML = ficha ? '<span class="marcas-ficha">' + ficha + '</span>' : '';

        // Grupos de modificadores
        const cont = $('#prod-grupos');
        cont.innerHTML = '';
        (producto.grupos || []).forEach((g) => {
            const div = document.createElement('div');
            div.className = 'grupo-mod';
            div.innerHTML = '<h3>' + escaparHtml(g.nombre)
                + (parseInt(g.obligatorio, 10) ? ' <span class="obliga">· obligatorio</span>' : '')
                + (g.tipo === 'unico' ? '' : ' <span class="ayuda">· varios</span>') + '</h3>';

            const rej = document.createElement('div');
            rej.className = 'opciones-mod';
            g.opciones.forEach((o) => {
                const b = document.createElement('button');
                b.className = 'op-mod';
                const extra = parseFloat(o.precio_extra) || 0;
                b.innerHTML = '<span>' + escaparHtml(o.nombre) + '</span>'
                    + (extra > 0 ? '<span class="extra">+' + pesos(extra) + '</span>' : '');
                b.onclick = () => {
                    const gid = g.id;
                    seleccion[gid] = seleccion[gid] || [];
                    const idx = seleccion[gid].indexOf(o.id);
                    if (g.tipo === 'unico') {
                        seleccion[gid] = idx === -1 ? [o.id] : [];
                        rej.querySelectorAll('.op-mod').forEach((x) => x.classList.remove('sel'));
                        if (idx === -1) b.classList.add('sel');
                    } else {
                        if (idx === -1) { seleccion[gid].push(o.id); b.classList.add('sel'); }
                        else { seleccion[gid].splice(idx, 1); b.classList.remove('sel'); }
                    }
                    actualizarPrecioModal();
                };
                rej.appendChild(b);
            });
            div.appendChild(rej);
            cont.appendChild(div);
        });

        // Mitades, si el producto es divisible
        const otras = producto.divisible ? divisibles(producto.id) : [];
        $('#prod-mitades').style.display = otras.length ? 'block' : 'none';
        const listaMit = $('#prod-lista-mitades');
        listaMit.innerHTML = '';
        otras.forEach((o) => {
            const b = document.createElement('button');
            b.className = 'op-mod';
            b.innerHTML = '<span>½ ' + escaparHtml(o.nombre) + '</span><span class="extra">' + pesos(o.precio) + '</span>';
            b.onclick = () => {
                const yaEra = mitadElegida === o.id;
                listaMit.querySelectorAll('.op-mod').forEach((x) => x.classList.remove('sel'));
                mitadElegida = yaEra ? null : o.id;
                if (!yaEra) b.classList.add('sel');
                actualizarPrecioModal();
            };
            listaMit.appendChild(b);
        });

        actualizarPrecioModal();
        $('#modal-producto').classList.add('abierta');
    }

    function actualizarPrecioModal() {
        let precio = prodActual.precio;

        // Mitad y mitad: se cobra la más cara
        if (mitadElegida) {
            const otra = divisibles(-1).find((p) => p.id === mitadElegida);
            if (otra) precio = Math.max(precio, otra.precio);
        }
        // Extras de los modificadores
        Object.keys(seleccion).forEach((gid) => {
            const grupo = prodActual.grupos.find((g) => String(g.id) === String(gid));
            if (!grupo) return;
            seleccion[gid].forEach((oid) => {
                const op = grupo.opciones.find((o) => String(o.id) === String(oid));
                if (op) precio += parseFloat(op.precio_extra) || 0;
            });
        });

        $('#prod-precio').textContent = pesos(precio);
    }

    $('#prod-aceptar').onclick = () => {
        // Comprueba los grupos obligatorios antes de añadir
        const faltan = (prodActual.grupos || []).filter((g) =>
            parseInt(g.obligatorio, 10) && !(seleccion[g.id] || []).length
        );
        if (faltan.length) {
            avisar('Falta elegir: ' + faltan.map((g) => g.nombre).join(', '), true);
            return;
        }

        const ids = [];
        Object.keys(seleccion).forEach((gid) => ids.push(...seleccion[gid]));

        const extra = { modificadores: ids, notas: $('#prod-nota').value.trim() };
        if (mitadElegida) extra.mitad_id = mitadElegida;

        $('#modal-producto').classList.remove('abierta');
        anadirProducto(prodActual.id, extra);
    };

    async function actualizarLinea(cambios) {
        if (!lineaSel) return;
        const datos = await api('/linea/' + lineaSel, { method: 'POST', body: JSON.stringify(cambios) });
        if (datos) {
            comanda = datos.comanda;
            if (!comanda.lineas.some((l) => l.id === lineaSel)) lineaSel = null;
            pintarComanda();
        }
    }

    function lineaActual() {
        return comanda ? comanda.lineas.find((l) => l.id === lineaSel) : null;
    }

    // ── Teclado numérico reutilizable ────────────────────────────
    function teclear(visorSel, tecla, alCambiar) {
        const v = $(visorSel);
        let txt = v.textContent.replace(/\D/g, '');

        // El primer dígito reemplaza el valor de partida, como en cualquier TPV
        if (v.dataset.intacto === '1' && tecla !== 'borrar') {
            txt = '';
        }
        v.dataset.intacto = '0';

        if (tecla === 'borrar') txt = txt.slice(0, -1);
        else txt = (txt === '0' ? '' : txt) + tecla;
        if (txt.length > 9) txt = txt.slice(0, 9);
        v.textContent = txt === '' ? '0' : txt;
        if (alCambiar) alCambiar(parseInt(v.textContent, 10) || 0);
    }

    /** Prepara un visor con su valor de partida, listo para que el primer dígito lo reemplace. */
    function prepararVisor(sel, valor) {
        const v = $(sel);
        v.textContent = String(valor || 0);
        v.dataset.intacto = '1';
    }

    document.querySelectorAll('#modal-teclado .teclado button').forEach((b) => {
        b.onclick = () => teclear('#tec-visor', b.dataset.tec);
    });
    document.querySelectorAll('#modal-cobro .teclado button').forEach((b) => {
        b.onclick = () => teclear('#cob-visor', b.dataset.tec, actualizarCambio);
    });
    document.querySelectorAll('[data-cerrar]').forEach((b) => {
        b.onclick = () => document.querySelectorAll('.capa').forEach((c) => c.classList.remove('abierta'));
    });

    function abrirTeclado(titulo, ayuda, valorInicial, alAceptar) {
        $('#tec-titulo').textContent = titulo;
        $('#tec-ayuda').textContent = ayuda || '';
        prepararVisor('#tec-visor', valorInicial);
        $('#modal-teclado').classList.add('abierta');
        $('#tec-aceptar').onclick = () => {
            $('#modal-teclado').classList.remove('abierta');
            alAceptar(parseInt($('#tec-visor').textContent, 10) || 0);
        };
    }

    function abrirTexto(titulo, ayuda, valorInicial, alAceptar, htmlExtra) {
        $('#txt-titulo').textContent = titulo;
        $('#txt-ayuda').textContent = ayuda || '';
        $('#txt-extra').innerHTML = htmlExtra || '';
        $('#txt-campo').value = valorInicial || '';
        $('#txt-campo').style.textTransform = 'none';
        $('#modal-texto').classList.add('abierta');
        setTimeout(() => $('#txt-campo').focus(), 100);
        $('#txt-aceptar').onclick = () => {
            $('#modal-texto').classList.remove('abierta');
            alAceptar($('#txt-campo').value.trim());
        };
    }

    // ── Acciones sobre la línea ──────────────────────────────────
    $('#acc-menos').onclick = () => {
        const l = lineaActual();
        if (l) actualizarLinea({ cantidad: l.cantidad - 1 });
    };
    $('#acc-mas').onclick = () => {
        const l = lineaActual();
        if (l) actualizarLinea({ cantidad: l.cantidad + 1 });
    };
    $('#acc-cant').onclick = () => {
        const l = lineaActual();
        if (!l) return;
        abrirTeclado('Cantidad', l.nombre_producto, l.cantidad, (n) => actualizarLinea({ cantidad: n }));
    };
    $('#acc-nota').onclick = () => {
        const l = lineaActual();
        if (!l) return;
        abrirTexto('Nota para cocina', l.nombre_producto, l.notas || '',
            (t) => actualizarLinea({ notas: t }));
    };
    $('#acc-servir').onclick = async () => {
        if (!lineaSel) return;
        const datos = await api('/linea/' + lineaSel + '/servir', { method: 'POST', body: '{}' });
        if (datos) { comanda = datos.comanda; lineaSel = null; pintarComanda(); avisar('Plato marcado como servido.'); }
    };

    // ── Acciones de la comanda ───────────────────────────────────
    $('#btn-volver').onclick = async () => {
        comanda = null; lineaSel = null;
        mostrarVista('mesas');
        $('#cab-titulo').textContent = 'Mesas';
        $('#cab-sub').textContent = <?= json_encode(config('Hotel')->nombre) ?>;
        await cargarEstado();
    };

    $('#btn-cocina').onclick = async () => {
        const datos = await api('/comanda/' + comanda.id + '/cocina', { method: 'POST', body: '{}' });
        if (datos) { comanda = datos.comanda; pintarComanda(); avisar(datos.mensaje); }
    };

    $('#btn-descuento').onclick = () => {
        abrirTeclado('Descuento en pesos', 'Total actual: ' + pesos(comanda.total), comanda.descuento, async (valor) => {
            const datos = await api('/comanda/' + comanda.id + '/descuento', {
                method: 'POST', body: JSON.stringify({ tipo: 'valor', valor: valor, motivo: 'Descuento en TPV' }),
            });
            if (datos) { comanda = datos.comanda; pintarComanda(); }
        });
    };

    $('#btn-cupon').onclick = () => {
        abrirTexto('Cupón de descuento', 'Total actual: ' + pesos(comanda.total), '', async (codigo) => {
            if (!codigo) { return; }
            const datos = await api('/comanda/' + comanda.id + '/cupon', {
                method: 'POST', body: JSON.stringify({ codigo: codigo }),
            });
            if (datos) { comanda = datos.comanda; pintarComanda(); avisar(datos.mensaje); }
        });
        $('#txt-campo').style.textTransform = 'uppercase';
    };

    // ── Cliente de la comanda ────────────────────────────────────
    function pintarListaHuespedes(filtro) {
        const cont = $('#cli-lista');
        cont.innerHTML = '';
        const texto = (filtro || '').toLowerCase();
        const lista = estado.alojados.filter((a) =>
            !texto || (a.unidad_nombre + ' ' + a.nombre + ' ' + a.apellidos).toLowerCase().includes(texto)
        );

        if (!lista.length) {
            cont.innerHTML = '<p class="ayuda" style="text-align:center; padding:18px 0">'
                + (estado.alojados.length ? 'Ningún huésped coincide.' : 'No hay huéspedes alojados ahora mismo.')
                + '</p>';
            return;
        }

        lista.forEach((a) => {
            const b = document.createElement('button');
            b.className = 'opcion-cliente';
            b.innerHTML = '<i class="bi bi-house-heart" style="color:var(--verde)"></i>'
                + '<span><span class="cab">' + escaparHtml(a.unidad_nombre) + '</span><br>'
                + '<span class="quien">' + escaparHtml(a.nombre + ' ' + a.apellidos + ' · ' + a.codigo) + '</span></span>';
            b.onclick = () => asignarCliente({ tipo: 'huesped', reserva_id: a.id });
            cont.appendChild(b);
        });
    }

    async function asignarCliente(cuerpo) {
        const datos = await api('/comanda/' + comanda.id + '/cliente', {
            method: 'POST', body: JSON.stringify(cuerpo),
        });
        if (!datos) return;
        comanda = datos.comanda;
        if (datos.alojados) estado.alojados = datos.alojados;
        $('#modal-cliente').classList.remove('abierta');
        pintarComanda();
        avisar(comanda.cliente_tipo === 'ninguno' ? 'Cliente quitado de la comanda.' : 'Cliente asignado: ' + comanda.cliente_texto);
    }

    function mostrarPestanaCliente(cual) {
        document.querySelectorAll('#modal-cliente .pest').forEach((p) => {
            p.classList.toggle('activa', p.dataset.pest === cual);
        });
        $('#panel-huesped').style.display = cual === 'huesped' ? 'block' : 'none';
        $('#panel-ocasional').style.display = cual === 'ocasional' ? 'block' : 'none';
    }

    document.querySelectorAll('#modal-cliente .pest').forEach((p) => {
        p.onclick = () => mostrarPestanaCliente(p.dataset.pest);
    });

    $('#btn-cliente').onclick = () => {
        if (!comanda) return;
        $('#cli-buscar').value = '';
        $('#cli-nombre').value = comanda.cliente_nombre || '';
        $('#cli-documento').value = comanda.cliente_documento || '';
        $('#cli-telefono').value = comanda.cliente_telefono || '';
        pintarListaHuespedes('');
        // Si ya es ocasional, abre directamente esa pestaña
        mostrarPestanaCliente(comanda.cliente_tipo === 'ocasional' ? 'ocasional' : 'huesped');
        $('#modal-cliente').classList.add('abierta');
    };

    $('#cli-buscar').oninput = (e) => pintarListaHuespedes(e.target.value);

    $('#cli-guardar-ocasional').onclick = () => {
        const nombre = $('#cli-nombre').value.trim();
        if (!nombre) { avisar('Escribe el nombre del cliente.', true); return; }
        asignarCliente({
            tipo: 'ocasional',
            nombre: nombre,
            documento: $('#cli-documento').value.trim(),
            telefono: $('#cli-telefono').value.trim(),
        });
    };

    $('#cli-quitar').onclick = () => asignarCliente({ tipo: 'ninguno' });

    /**
     * Propina.
     *
     * La Ley 1935 de 2018 la define como voluntaria: se puede sugerir, pero el
     * cliente debe poder rechazarla o cambiarla, y hay que decírselo. Por eso
     * el «Sin propina» está a la misma altura que el resto, no escondido.
     */
    $('#btn-propina').onclick = () => {
        const base = Math.max(0, comanda.total - comanda.descuento);

        async function fijar(valor) {
            const datos = await api('/comanda/' + comanda.id + '/propina', {
                method: 'POST', body: JSON.stringify({ tipo: 'valor', valor: Math.round(valor) }),
            });
            if (datos) { comanda = datos.comanda; pintarComanda(); }
        }

        const sugeridos = PROPINA_SUGERIDA > 0
            ? [0, Math.round(PROPINA_SUGERIDA / 2), PROPINA_SUGERIDA]
            : [0, 5, 10];

        const botones = sugeridos.map(function (pct) {
            const importe = Math.round(base * pct / 100);
            return '<button class="btn-propina" data-pct="' + pct + '" data-importe="' + importe + '">'
                + (pct === 0 ? 'Sin propina' : pct + '%')
                + (pct > 0 ? '<small>' + pesos(importe) + '</small>' : '')
                + '</button>';
        }).join('');

        abrirTexto(
            'Propina voluntaria',
            'Pregúntale al cliente. Puede no dejar nada, o poner otra cantidad.',
            '',
            async (escrito) => {
                const valor = parseInt(String(escrito).replace(/\D/g, ''), 10);
                if (!isNaN(valor)) { await fijar(valor); }
            },
            '<div class="propinas-rapidas">' + botones + '</div>'
            + '<p class="ayuda" style="margin:2px 0 6px">…o escribe otra cantidad en pesos:</p>'
        );

        $('#txt-campo').setAttribute('inputmode', 'numeric');
        $('#txt-campo').placeholder = 'Otra cantidad';

        document.querySelectorAll('.btn-propina').forEach(function (b) {
            b.onclick = async () => {
                $('#modal-texto').classList.remove('abierta');
                await fijar(parseInt(b.dataset.importe, 10) || 0);
            };
        });
    };

    $('#btn-recibo').onclick = () => {
        window.open(<?= json_encode(rtrim(site_url('pos/recibo'), '/')) ?> + '/' + comanda.id + '?auto=1', '_blank');
    };

    $('#btn-anular').onclick = () => {
        abrirTexto('Anular comanda', 'Escribe el motivo (queda registrado).', '', async (motivo) => {
            if (!motivo) { avisar('Debes indicar el motivo.', true); return; }
            const datos = await api('/comanda/' + comanda.id + '/anular', {
                method: 'POST', body: JSON.stringify({ motivo: motivo }),
            });
            if (datos) { avisar(datos.mensaje); $('#btn-volver').click(); }
        });
    };

    $('#btn-suelta').onclick = () => {
        const opciones = estado.alojados.map((a) =>
            '<option value="' + a.id + '">' + escaparHtml(a.unidad_nombre + ' · ' + a.nombre + ' ' + a.apellidos) + '</option>'
        ).join('');
        const extra = '<p class="ayuda">Cargar a un huésped alojado (opcional)</p>'
            + '<select class="campo" id="sel-alojado"><option value="">Sin huésped · cliente de paso</option>' + opciones + '</select>';
        abrirTexto('Comanda para llevar', 'Nombre o referencia del pedido.', '', async (nombre) => {
            const reservaId = $('#sel-alojado') ? $('#sel-alojado').value : '';
            const cuerpo = { nombre: nombre || 'Para llevar' };
            if (reservaId) cuerpo.reserva_id = parseInt(reservaId, 10);
            const datos = await api('/abrir', { method: 'POST', body: JSON.stringify(cuerpo) });
            if (datos) abrirComanda(datos.comanda_id);
        }, extra);
    };

    // ── Cobro ────────────────────────────────────────────────────
    let formaSel = 'efectivo';
    let importeCobro = 0;   // cuánto se cobra en este pago (permite dividir la cuenta)

    function actualizarCambio() {
        const recibido = parseInt($('#cob-visor').textContent, 10) || 0;
        const cambio = recibido - importeCobro;
        $('#cob-cambio').style.display = cambio >= 0 && recibido > 0 ? 'block' : 'none';
        $('#cob-cambio-valor').textContent = pesos(Math.max(0, cambio));
    }

    function fijarImporte(valor) {
        importeCobro = Math.min(Math.max(0, Math.round(valor)), comanda.pendiente);
        $('#cob-importe').textContent = pesos(importeCobro);
        pintarRapidosEfectivo();
        actualizarCambio();
    }

    function pintarRapidosEfectivo() {
        const rap = $('#cob-rapidos');
        rap.innerHTML = '';
        const exacto = importeCobro;
        const sugerencias = [exacto, Math.ceil(exacto / 10000) * 10000, 20000, 50000, 100000, 200000];
        [...new Set(sugerencias)].filter((v) => v >= exacto && v > 0).slice(0, 6).forEach((v) => {
            const b = document.createElement('button');
            b.textContent = v === exacto ? 'Justo · ' + pesos(v) : pesos(v);
            b.onclick = () => { prepararVisor('#cob-visor', Math.round(v)); actualizarCambio(); };
            rap.appendChild(b);
        });
    }

    $('#btn-cobrar').onclick = () => {
        if (!comanda || comanda.pendiente <= 0) return;
        formaSel = 'efectivo';
        $('#cob-total').textContent = pesos(comanda.pendiente);
        prepararVisor('#cob-visor', 0);
        $('#cob-cambio').style.display = 'none';

        // Dividir la cuenta: todo, o en partes iguales entre los comensales
        const div = $('#cob-dividir');
        div.innerHTML = '';
        const opciones = [{ etiqueta: 'Todo', partes: 1 }, { etiqueta: 'Mitad', partes: 2 },
            { etiqueta: 'En 3', partes: 3 }, { etiqueta: 'En 4', partes: 4 }];
        opciones.forEach((o) => {
            const b = document.createElement('button');
            const importe = Math.round(comanda.pendiente / o.partes);
            b.innerHTML = o.etiqueta + '<br><small>' + pesos(importe) + '</small>';
            b.onclick = () => {
                div.querySelectorAll('button').forEach((x) => x.style.borderColor = 'var(--borde)');
                b.style.borderColor = 'var(--verde)';
                fijarImporte(importe);
            };
            div.appendChild(b);
        });
        fijarImporte(comanda.pendiente);

        // Formas de pago disponibles
        const formas = <?= json_encode($formasPago) ?>;
        const cont = $('#cob-formas');
        cont.innerHTML = '';
        Object.keys(formas).forEach((clave) => {
            if (clave === 'habitacion' && !comanda.reserva_id) return;
            const b = document.createElement('button');
            b.className = 'forma' + (clave === formaSel ? ' sel' : '');
            b.textContent = formas[clave];
            b.onclick = () => {
                formaSel = clave;
                cont.querySelectorAll('.forma').forEach((x) => x.classList.remove('sel'));
                b.classList.add('sel');
                $('#cob-efectivo').style.display = clave === 'efectivo' ? 'block' : 'none';
                $('#cob-bono').style.display = clave === 'bono' ? 'block' : 'none';
                if (clave === 'bono') { setTimeout(() => $('#cob-bono-codigo').focus(), 80); }
            };
            cont.appendChild(b);
        });

        $('#cob-efectivo').style.display = 'block';
        $('#cob-bono').style.display = 'none';
        $('#cob-bono-codigo').value = '';
        $('#modal-cobro').classList.add('abierta');
    };

    $('#cob-confirmar').onclick = async () => {
        const cuerpo = { forma_pago: formaSel, importe: importeCobro };
        if (formaSel === 'efectivo') {
            const recibido = parseInt($('#cob-visor').textContent, 10) || 0;
            cuerpo.recibido = recibido > 0 ? recibido : importeCobro;
        }
        if (formaSel === 'bono') {
            cuerpo.codigo = $('#cob-bono-codigo').value.trim().toUpperCase();
            if (!cuerpo.codigo) { avisar('Escribe el código del bono.', true); return; }
        }
        const datos = await api('/comanda/' + comanda.id + '/cobrar', {
            method: 'POST', body: JSON.stringify(cuerpo),
        });
        if (!datos) return;

        $('#modal-cobro').classList.remove('abierta');
        let mensaje = datos.mensaje;
        if (datos.cambio !== null && datos.cambio > 0) {
            mensaje = 'Cambio a devolver: ' + pesos(datos.cambio) + '. ' + mensaje;
        }
        avisar(mensaje);

        if (datos.cerrada) {
            // Ofrece el recibo antes de volver al mapa de mesas
            const comandaId = datos.comanda_id;
            abrirTexto('Comanda cobrada', mensaje, '', () => {},
                '<div class="acciones" style="padding:0 0 8px;">'
                + '<button class="btn" id="rec-imprimir"><i class="bi bi-printer"></i> Imprimir recibo</button>'
                + '<button class="btn verde" id="rec-listo">Listo</button></div>');
            $('#txt-campo').style.display = 'none';
            $('#txt-aceptar').parentElement.style.display = 'none';
            $('#rec-imprimir').onclick = () => {
                window.open(<?= json_encode(rtrim(site_url('pos/recibo'), '/')) ?> + '/' + comandaId + '?auto=1', '_blank');
            };
            $('#rec-listo').onclick = () => {
                // Cobrar cierra el turno de esa persona en la pantalla: el
                // siguiente que llegue tiene que identificarse.
                if (COMPARTIDO) { bloquearPantalla(true); }
                $('#modal-texto').classList.remove('abierta');
                $('#txt-campo').style.display = '';
                $('#txt-aceptar').parentElement.style.display = '';
                $('#btn-volver').click();
            };
        } else {
            comanda = datos.comanda;
            pintarComanda();
        }
    };

    // ── Tema día / noche ─────────────────────────────────────────
    function aplicarTema(tema) {
        document.documentElement.dataset.tema = tema;
        $('#btn-tema').innerHTML = tema === 'noche'
            ? '<i class="bi bi-sun"></i>'
            : '<i class="bi bi-moon"></i>';
        try { localStorage.setItem('pos-tema', tema); } catch (e) { /* sin almacenamiento */ }
    }

    $('#btn-tema').onclick = () => {
        aplicarTema(document.documentElement.dataset.tema === 'noche' ? 'dia' : 'noche');
    };

    let temaGuardado = 'dia';
    try { temaGuardado = localStorage.getItem('pos-tema') || 'dia'; } catch (e) { /* sin almacenamiento */ }
    aplicarTema(temaGuardado);

    // ── Reloj y arranque ─────────────────────────────────────────
    function reloj() {
        $('#reloj').textContent = new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
    }
    reloj();
    setInterval(reloj, 30000);

    // Refresca el mapa de mesas mientras no haya comanda abierta
    setInterval(() => { if (!comanda) cargarEstado(); }, 30000);

    // Con una comanda abierta, consulta a cocina para avisar de los platos listos
    let listosPrevios = 0;
    setInterval(async () => {
        if (!comanda) return;
        const datos = await api('/comanda/' + comanda.id);
        if (!datos || !datos.comanda) return;

        const nuevos = datos.comanda.listos;
        // Conserva la línea seleccionada al refrescar
        comanda = datos.comanda;
        pintarComanda();

        if (nuevos > listosPrevios) {
            avisar('Cocina terminó ' + (nuevos - listosPrevios === 1 ? 'un plato' : (nuevos - listosPrevios) + ' platos') + ' de esta mesa.');
        }
        listosPrevios = nuevos;
    }, 15000);

    cargarEstado();
}());
</script>
</body>
</html>
