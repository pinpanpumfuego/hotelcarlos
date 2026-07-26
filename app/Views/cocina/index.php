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
        .plato .mods { display: block; color: #1f6b8c; font-size: .85rem; font-weight: 600; }
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

        /* ── Comanda que nadie ha mirado todavía ──
           Se distingue de «va con retraso»: aquí el problema no es que se tarde
           en cocinar, es que puede que nadie la haya visto. */
        .comanda.sin-recibir { border-top-color: #1f6b8c; border-color: #1f6b8c; }
        .comanda.sin-recibir .cab { background: #eaf4f9; }
        .btn-recibir {
            width: 100%; background: #1f6b8c; color: #fff; border: none;
            padding: 16px; font-weight: 700; font-size: 1.05rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-recibir:active { background: #185874; }
        .marca-recibida {
            font-size: .78rem; color: var(--verde); font-weight: 600;
            padding: 6px 14px; display: flex; align-items: center; gap: 5px;
            border-bottom: 1px solid var(--borde);
        }

        /* ── Voz ── */
        .btn-cab.apagado { opacity: .5; }
        .aviso-voz {
            position: fixed; left: 50%; transform: translateX(-50%); bottom: 22px; z-index: 20;
            background: #1f6b8c; color: #fff; border: none; border-radius: 14px;
            padding: 16px 26px; font-size: 1rem; font-weight: 600; cursor: pointer;
            display: none; align-items: center; gap: 10px; box-shadow: 0 4px 16px rgba(30,43,36,.25);
            animation: latido 1.6s ease-in-out infinite;
        }
        .aviso-voz.ver { display: flex; }
    </style>
</head>
<body>

<header class="cabecera">
    <h1><i class="bi <?= esc($config['icono']) ?>"></i> <?= esc($config['titulo']) ?></h1>
    <span class="sub" id="resumen">—</span>
    <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
        <span class="sub" id="actualizado"></span>
        <button class="btn-cab" id="btn-voz" title="Leer las comandas en voz alta">
            <i class="bi bi-volume-up" id="ico-voz"></i>
        </button>
        <a class="btn-cab" href="<?= site_url($otraZona['ruta']) ?>">
            <i class="bi <?= esc($otraZona['icono']) ?>"></i> <?= esc($otraZona['titulo']) ?>
        </a>
        <a class="btn-cab" href="<?= site_url('pos') ?>"><i class="bi bi-tablet-landscape"></i> TPV</a>
        <a class="btn-cab" href="<?= site_url('panel') ?>"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</header>

<div class="tablero" id="tablero"></div>

<button class="aviso-voz" id="aviso-voz">
    <i class="bi bi-volume-up-fill"></i> Toca para activar la voz
</button>

<script>
(function () {
    'use strict';
    const BASE = <?= json_encode(rtrim(site_url('cocina'), '/')) ?>;
    const ZONA = <?= json_encode($zona) ?>;
    let token = <?= json_encode(csrf_hash()) ?>;

    // Comandas ya anunciadas y cuándo se repitió el aviso de cada una.
    // Se lleva por identificador y no por número total: si en el mismo refresco
    // entra una y sale otra, el total no cambia y la nueva pasaría sin avisar.
    const anunciadas = new Set();
    const repetido   = {};
    let primerCiclo  = true;

    /** Segundos sin que nadie la reciba antes de volver a cantarla. */
    const REPETIR_SEG = 90;

    let vozPermitida = true;    // lo que diga gerencia en Administración
    let vozQuerida   = localStorage.getItem('cocina.voz') !== '0';   // esta pantalla
    let vozLista     = false;   // el navegador no deja hablar hasta que se toca

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
            div.className = 'comanda ' + clase + (c.recibida ? '' : ' sin-recibir');

            let html = '<div class="cab"><div>'
                + '<div class="numero">' + escapar(c.numero) + '</div>'
                + '<div class="donde">' + escapar(c.donde) + '</div></div>'
                + '<div class="reloj">' + c.minutos + ' min</div></div>';

            // Primero acusar recibo, y solo entonces se ven los platos como
            // tarea en marcha. El botón va arriba porque es lo primero que toca.
            if (!c.recibida) {
                html += '<button class="btn-recibir" data-recibir="' + c.comanda_id + '">'
                    + '<i class="bi bi-eye"></i> Recibido</button>';
            } else {
                html += '<div class="marca-recibida"><i class="bi bi-eye-fill"></i> Recibida · en preparación</div>';
            }

            c.lineas.forEach((l) => {
                const esBarra = l.destino === 'barra';
                html += '<button class="plato' + (esBarra ? ' barra' : '') + '" data-linea="' + l.id + '">'
                    + '<span class="cant">' + l.cantidad + '</span>'
                    + '<span class="nombre">' + escapar(l.nombre)
                    + (esBarra ? '<span class="etiqueta-destino"><i class="bi bi-cup-straw"></i> Barra</span>' : '')
                    + ((l.modificadores && l.modificadores.length)
                        ? '<span class="mods"><i class="bi bi-sliders"></i> ' + escapar(l.modificadores.join(' · ')) + '</span>' : '')
                    + (l.notas ? '<span class="nota"><i class="bi bi-exclamation-triangle"></i> ' + escapar(l.notas) + '</span>' : '')
                    + '</span><span class="marca"><i class="bi bi-check-circle"></i></span></button>';
            });

            html += '<button class="todo" data-comanda="' + c.comanda_id + '">'
                + '<i class="bi bi-check2-all"></i> Todo listo</button>';

            div.innerHTML = html;
            tablero.appendChild(div);
        });

        tablero.querySelectorAll('[data-recibir]').forEach((b) => {
            b.onclick = async () => {
                b.disabled = true;
                // Se calla el aviso en el acto: ya está vista
                try { speechSynthesis.cancel(); } catch (e) { /* nada */ }
                await api('/recibir/' + b.dataset.recibir + '/' + ZONA, { method: 'POST' });
                cargar();
            };
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

        vozPermitida = datos.voz !== false;
        pintarBotonVoz();

        const ahora = Date.now();
        const vivas = new Set(datos.comandas.map((c) => c.comanda_id));

        datos.comandas.forEach((c) => {
            if (!anunciadas.has(c.comanda_id)) {
                anunciadas.add(c.comanda_id);
                repetido[c.comanda_id] = ahora;

                // En el primer refresco no se canta nada: son las que ya
                // estaban esperando. Si se recarga la pantalla a media tarde,
                // leer ocho comandas viejas seguidas no ayuda a nadie.
                if (!primerCiclo) { sonar(); hablar(frase(c)); }
                return;
            }

            // Sigue sin que nadie la mire: se vuelve a cantar
            if (!c.recibida && ahora - (repetido[c.comanda_id] || 0) > REPETIR_SEG * 1000) {
                repetido[c.comanda_id] = ahora;
                sonar();
                hablar('Sigue pendiente. ' + frase(c).replace('Nueva comanda. ', ''));
            }
        });

        // Se olvidan las que ya salieron, para no crecer sin fin en todo el turno
        anunciadas.forEach((id) => {
            if (!vivas.has(id)) { anunciadas.delete(id); delete repetido[id]; }
        });

        primerCiclo = false;
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

    // ═══════════════════════════════════════════════════════════════
    //  Voz
    //
    //  Usa el lector que ya trae el navegador: no cuesta nada, habla
    //  español y funciona sin internet, que en un ecolodge de montaña
    //  no es un detalle menor.
    // ═══════════════════════════════════════════════════════════════

    const NUMEROS = ['cero', 'una', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete',
        'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince'];

    function enPalabras(n) {
        return NUMEROS[n] || String(n);
    }

    /**
     * Arma la frase de una comanda.
     *
     * Se dice «dos de trucha al ajillo» y no «dos truchas al ajillos»: poner el
     * plural a un nombre de plato en español sale mal casi siempre, y además así
     * es como se habla de verdad en una cocina.
     */
    function frase(c) {
        // En minúscula: va en mitad de la frase, no al principio
        const suave = (n) => n.charAt(0).toLowerCase() + n.slice(1);
        const platos = c.lineas.map((l) => l.cantidad > 1
            ? enPalabras(l.cantidad) + ' de ' + suave(l.nombre)
            : suave(l.nombre));

        return 'Nueva comanda. ' + c.donde + ': ' + platos.join(', ') + '.';
    }

    function hablar(texto) {
        if (!vozActiva() || !('speechSynthesis' in window)) { return; }
        try {
            // Si ya estaba diciendo algo, se corta: interesa lo último que entró
            speechSynthesis.cancel();
            const d = new SpeechSynthesisUtterance(texto);
            d.lang = 'es-CO';
            d.rate = 0.95;      // un pelín despacio: se escucha con ruido de fondo
            speechSynthesis.speak(d);
        } catch (e) { /* sin voz disponible: queda el pitido */ }
    }

    const vozActiva = () => vozPermitida && vozQuerida && vozLista;

    function pintarBotonVoz() {
        const b = $('#btn-voz');
        b.style.display = vozPermitida ? 'inline-flex' : 'none';
        b.classList.toggle('apagado', !vozQuerida);
        $('#ico-voz').className = 'bi ' + (vozQuerida ? 'bi-volume-up' : 'bi-volume-mute');
        b.title = vozQuerida ? 'Voz encendida · tocar para apagar' : 'Voz apagada · tocar para encender';

        // El navegador no deja sonar nada hasta que alguien toca la pantalla
        $('#aviso-voz').classList.toggle('ver', vozPermitida && vozQuerida && !vozLista);
    }

    function desbloquearVoz() {
        if (vozLista) { return; }
        vozLista = true;
        try { speechSynthesis.getVoices(); } catch (e) { /* nada */ }
        pintarBotonVoz();
    }

    $('#btn-voz').onclick = () => {
        vozQuerida = !vozQuerida;
        localStorage.setItem('cocina.voz', vozQuerida ? '1' : '0');
        desbloquearVoz();
        pintarBotonVoz();
        if (vozQuerida) { hablar('Voz encendida.'); }
    };
    $('#aviso-voz').onclick = desbloquearVoz;
    document.addEventListener('click', desbloquearVoz, { once: true });
    document.addEventListener('touchstart', desbloquearVoz, { once: true, passive: true });

    pintarBotonVoz();
    cargar();
    setInterval(cargar, 10000);   // la cocina se refresca sola cada 10 segundos
}());
</script>
</body>
</html>
