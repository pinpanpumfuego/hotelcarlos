<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($config['titulo']) ?> · <?= esc($hotel->nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* Pantalla de cocina: alto contraste, legible a distancia y con las manos ocupadas */
        :root {
            --fondo: #f2f4f1;
            --panel: #ffffff;
            --borde: #d5dbd4;
            --texto: #1e2b24;
            --texto-suave: #6b786f;
            --verde: #2f7d52;
            --ambar: #b5761f;
            --rojo: #b3454a;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0; min-height: 100vh; background: var(--fondo); color: var(--texto);
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; user-select: none;
        }
        .cabecera {
            display: flex; align-items: center; gap: 14px; padding: 12px 18px;
            background: var(--panel); border-bottom: 1px solid var(--borde); position: sticky; top: 0; z-index: 5;
        }
        .cabecera h1 { font-size: 1.2rem; margin: 0; }
        .cabecera .sub { color: var(--texto-suave); font-size: .9rem; }
        .btn-cab {
            background: var(--fondo); border: 1px solid var(--borde); border-radius: 10px;
            padding: 10px 14px; min-height: 44px; text-decoration: none; color: inherit;
            display: inline-flex; align-items: center; gap: 6px; font-size: .95rem; cursor: pointer;
        }
        .tablero {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 14px; padding: 16px; align-content: start;
        }
        .comanda {
            background: var(--panel); border: 1px solid var(--borde); border-top: 6px solid var(--verde);
            border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(30,43,36,.10);
        }
        .comanda.media { border-top-color: var(--ambar); }
        .comanda.tarde { border-top-color: var(--rojo); animation: latido 1.6s ease-in-out infinite; }
        @keyframes latido { 50% { box-shadow: 0 0 0 4px rgba(179,69,74,.18); } }
        .comanda .cab {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 14px; border-bottom: 1px solid var(--borde);
        }
        .comanda .numero { font-weight: 700; font-size: 1.05rem; }
        .comanda .donde { color: var(--texto-suave); font-size: .88rem; }
        .reloj { font-weight: 700; font-size: 1.1rem; }
        .comanda.media .reloj { color: var(--ambar); }
        .comanda.tarde .reloj { color: var(--rojo); }
        .plato {
            width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px;
            background: transparent; border: none; border-bottom: 1px solid var(--borde);
            text-align: left; font-size: 1.05rem; cursor: pointer; color: inherit;
        }
        .plato:active { background: var(--fondo); }
        .plato .cant {
            background: var(--texto); color: var(--panel); border-radius: 8px;
            min-width: 38px; text-align: center; padding: 4px 8px; font-weight: 700;
        }
        .plato .nombre { flex: 1; font-weight: 600; }
        .plato .nota { display: block; color: var(--rojo); font-size: .85rem; font-weight: 500; }
        .plato.barra { background: #f0f6f9; }
        .etiqueta-destino {
            display: inline-block; margin-left: 8px; font-size: .75rem; font-weight: 600;
            color: #1f6b8c; background: #dceaf2; border-radius: 999px; padding: 1px 9px;
        }
        .plato .marca { color: var(--verde); font-size: 1.4rem; }
        .todo {
            width: 100%; background: var(--verde); color: #fff; border: none;
            padding: 14px; font-weight: 700; font-size: 1rem; cursor: pointer;
        }
        .vacio { grid-column: 1 / -1; text-align: center; padding: 70px 20px; color: var(--texto-suave); }
        .vacio i { font-size: 3rem; color: var(--verde); display: block; margin-bottom: 12px; }
    </style>
</head>
<body>

<header class="cabecera">
    <h1><i class="bi <?= esc($config['icono']) ?>"></i> <?= esc($config['titulo']) ?></h1>
    <span class="sub" id="resumen">—</span>
    <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
        <span class="sub" id="actualizado"></span>
        <a class="btn-cab" href="<?= site_url($otraZona['ruta']) ?>">
            <i class="bi <?= esc($otraZona['icono']) ?>"></i> <?= esc($otraZona['titulo']) ?>
        </a>
        <a class="btn-cab" href="<?= site_url('pos') ?>"><i class="bi bi-tablet-landscape"></i> TPV</a>
        <a class="btn-cab" href="<?= site_url('panel') ?>"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</header>

<div class="tablero" id="tablero"></div>

<script>
(function () {
    'use strict';
    const BASE = <?= json_encode(rtrim(site_url('cocina'), '/')) ?>;
    const ZONA = <?= json_encode($zona) ?>;
    let token = <?= json_encode(csrf_hash()) ?>;
    let ultimoConteo = 0;

    const $ = (s) => document.querySelector(s);
    const escapar = (t) => String(t == null ? '' : t).replace(/[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function api(ruta, opciones) {
        const cfg = Object.assign({ headers: {} }, opciones || {});
        cfg.headers['X-Requested-With'] = 'XMLHttpRequest';
        if (cfg.method === 'POST') {
            cfg.headers['Content-Type'] = 'application/json';
            cfg.headers['X-CSRF-TOKEN'] = token;
            cfg.body = cfg.body || '{}';
        }
        const res = await fetch(BASE + ruta, cfg);
        const nuevo = res.headers.get('X-CSRF-TOKEN');
        if (nuevo) token = nuevo;
        try { return await res.json(); } catch (e) { return null; }
    }

    function pintar(comandas) {
        const tablero = $('#tablero');
        tablero.innerHTML = '';

        const platos = comandas.reduce((n, c) => n + c.lineas.length, 0);
        $('#resumen').textContent = comandas.length
            ? comandas.length + (comandas.length === 1 ? ' comanda · ' : ' comandas · ') + platos + (platos === 1 ? ' plato' : ' platos')
            : 'Todo al día';
        $('#actualizado').textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });

        if (!comandas.length) {
            tablero.innerHTML = '<div class="vacio"><i class="bi bi-check2-circle"></i>'
                + 'No hay platos pendientes.<br><small>Aquí aparecerán las comandas en cuanto el mesero las envíe.</small></div>';
            return;
        }

        comandas.forEach((c) => {
            const clase = c.minutos >= 20 ? 'tarde' : (c.minutos >= 10 ? 'media' : '');
            const div = document.createElement('div');
            div.className = 'comanda ' + clase;

            let html = '<div class="cab"><div>'
                + '<div class="numero">' + escapar(c.numero) + '</div>'
                + '<div class="donde">' + escapar(c.donde) + '</div></div>'
                + '<div class="reloj">' + c.minutos + ' min</div></div>';

            c.lineas.forEach((l) => {
                const esBarra = l.destino === 'barra';
                html += '<button class="plato' + (esBarra ? ' barra' : '') + '" data-linea="' + l.id + '">'
                    + '<span class="cant">' + l.cantidad + '</span>'
                    + '<span class="nombre">' + escapar(l.nombre)
                    + (esBarra ? '<span class="etiqueta-destino"><i class="bi bi-cup-straw"></i> Barra</span>' : '')
                    + (l.notas ? '<span class="nota"><i class="bi bi-exclamation-triangle"></i> ' + escapar(l.notas) + '</span>' : '')
                    + '</span><span class="marca"><i class="bi bi-check-circle"></i></span></button>';
            });

            html += '<button class="todo" data-comanda="' + c.comanda_id + '">'
                + '<i class="bi bi-check2-all"></i> Todo listo</button>';

            div.innerHTML = html;
            tablero.appendChild(div);
        });

        tablero.querySelectorAll('[data-linea]').forEach((b) => {
            b.onclick = async () => { b.disabled = true; await api('/listo/' + b.dataset.linea, { method: 'POST' }); cargar(); };
        });
        tablero.querySelectorAll('[data-comanda]').forEach((b) => {
            b.onclick = async () => {
                b.disabled = true;
                await api('/comanda/' + b.dataset.comanda + '/lista/' + ZONA, { method: 'POST' });
                cargar();
            };
        });
    }

    async function cargar() {
        const datos = await api('/datos/' + ZONA);
        if (!datos || !datos.ok) return;

        // Aviso sonoro discreto cuando entra una comanda nueva
        const total = datos.comandas.length;
        if (total > ultimoConteo && ultimoConteo !== 0) sonar();
        ultimoConteo = total;

        pintar(datos.comandas);
    }

    function sonar() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const vol = ctx.createGain();
            osc.connect(vol); vol.connect(ctx.destination);
            osc.frequency.value = 880; vol.gain.value = 0.08;
            osc.start(); osc.stop(ctx.currentTime + 0.18);
        } catch (e) { /* sin audio disponible */ }
    }

    cargar();
    setInterval(cargar, 10000);   // la cocina se refresca sola cada 10 segundos
}());
</script>
</body>
</html>
