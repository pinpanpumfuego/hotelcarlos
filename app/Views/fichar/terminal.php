<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Fichaje · <?= esc($hotel->nombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bosque: #1f4d36; --bosque-claro: #2a5f44; --arena: #9d6220;
            --tinta: #1c2a23; --tinta-suave: #7b8a81; --panel: #ffffff; --borde: #d9e2db;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif; color: var(--tinta);
            background: linear-gradient(165deg, #143425 0%, var(--bosque) 55%, #26593f 100%);
            display: flex; flex-direction: column; overflow: hidden;
            user-select: none;
        }

        header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 22px; color: #fff; flex-shrink: 0;
        }
        header .marca { font-family: 'Fraunces', serif; font-size: 1.05rem; font-weight: 600; }
        header .lugar { font-size: .64rem; letter-spacing: .16em; text-transform: uppercase; color: #9dbcaa; }
        header .reloj { text-align: right; }
        header .hora { font-family: 'Fraunces', serif; font-size: 2rem; font-weight: 600; line-height: 1; }
        header .fecha { font-size: .76rem; color: #9dbcaa; text-transform: capitalize; }

        main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px 18px 18px; min-height: 0; }

        .tarjeta {
            background: var(--panel); border-radius: 22px; width: 100%; max-width: 420px;
            box-shadow: 0 18px 50px rgba(0,0,0,.25); padding: 22px;
            display: flex; flex-direction: column; gap: 14px; max-height: 100%;
        }
        .tarjeta.ancha { max-width: 560px; }

        h1 { font-family: 'Fraunces', serif; font-size: 1.35rem; margin: 0; text-align: center; font-weight: 600; }
        .ayuda { text-align: center; color: var(--tinta-suave); font-size: .88rem; margin: 0; }

        .visor {
            display: flex; justify-content: center; gap: 14px; margin: 4px 0 2px;
        }
        .punto {
            width: 18px; height: 18px; border-radius: 50%;
            border: 2px solid var(--borde); transition: all .16s ease;
        }
        .punto.lleno { background: var(--bosque); border-color: var(--bosque); transform: scale(1.12); }

        .teclado { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .teclado button {
            font: inherit; font-size: 1.55rem; font-weight: 600; padding: 16px 0;
            border-radius: 14px; border: 1px solid var(--borde); background: #fbfdfb;
            color: var(--tinta); cursor: pointer; transition: transform .08s ease, background .12s ease;
        }
        .teclado button:active { transform: scale(.94); background: #eef4ef; }
        .teclado button.gris { color: var(--tinta-suave); font-size: 1.2rem; }

        .acciones { display: grid; gap: 10px; }
        .accion {
            font: inherit; font-size: 1.1rem; font-weight: 600; padding: 18px;
            border-radius: 16px; border: 0; cursor: pointer; color: #fff;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: transform .08s ease;
        }
        .accion:active { transform: scale(.97); }
        .accion.entrada { background: var(--bosque); }
        .accion.salida { background: var(--arena); }
        .accion.pausa_inicio { background: #5b7c99; }
        .accion.pausa_fin { background: var(--bosque-claro); }
        .accion.gris { background: #e6ece7; color: var(--tinta); }

        .quien { text-align: center; }
        .quien .nombre { font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 600; }
        .quien .cargo { color: var(--tinta-suave); font-size: .9rem; }
        .quien .estado {
            display: inline-block; margin-top: 8px; font-size: .78rem; font-weight: 600;
            padding: .2rem .7rem; border-radius: 99px;
        }
        .estado.dentro { background: #e9f4ee; color: #1c5c3c; }
        .estado.fuera { background: #f1f4f2; color: var(--tinta-suave); }
        .estado.pausa { background: #fdf5e7; color: #7d5a1c; }

        #camara { width: 100%; border-radius: 14px; background: #0d1a13; aspect-ratio: 4/3; object-fit: cover; }
        .camara-caja { position: relative; }
        .camara-aviso {
            position: absolute; inset: auto 0 0 0; padding: 6px 10px; font-size: .74rem;
            color: #fff; background: linear-gradient(transparent, rgba(0,0,0,.6));
            border-radius: 0 0 14px 14px; text-align: center;
        }

        .exito { text-align: center; padding: 8px 0; }
        .exito .marca-ok {
            width: 76px; height: 76px; margin: 0 auto 14px; border-radius: 50%;
            background: #e9f4ee; color: #1c5c3c; display: flex; align-items: center;
            justify-content: center; font-size: 2.6rem;
            animation: aparece .35s cubic-bezier(.2,1.4,.5,1) both;
        }
        @keyframes aparece { from { transform: scale(.5); opacity: 0; } to { transform: none; opacity: 1; } }

        .aviso { padding: 12px 14px; border-radius: 12px; font-size: .9rem; text-align: center; }
        .aviso.error { background: #fbecec; color: #8f3237; }
        .aviso.info { background: #eef4ef; color: var(--tinta-suave); font-size: .78rem; }

        footer { text-align: center; color: #9dbcaa; font-size: .72rem; padding: 0 18px 14px; flex-shrink: 0; }
        footer a { color: #cfe0d5; }

        .presentes {
            display: flex; gap: 6px; flex-wrap: wrap; justify-content: center;
            padding-top: 10px; border-top: 1px solid var(--borde);
        }
        .presentes .chip {
            font-size: .74rem; background: #e9f4ee; color: #1c5c3c;
            padding: .15rem .55rem; border-radius: 99px;
        }
        .presentes .chip.pausa { background: #fdf5e7; color: #7d5a1c; }

        .oculto { display: none !important; }
        @media (max-height: 700px) {
            .teclado button { padding: 12px 0; font-size: 1.35rem; }
            header .hora { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <div class="marca"><?= esc($hotel->nombre) ?></div>
            <div class="lugar">Control de jornada</div>
        </div>
        <div class="reloj">
            <div class="hora" id="reloj">--:--</div>
            <div class="fecha" id="fecha"></div>
        </div>
    </header>

    <main>
        <!-- ── Paso 1: PIN ── -->
        <div class="tarjeta" id="paso-pin">
            <h1>Marca tu jornada</h1>
            <p class="ayuda">Teclea tu PIN de <?= \App\Models\EmpleadoModel::LONGITUD_PIN ?> dígitos</p>

            <div class="visor" id="visor">
                <span class="punto"></span><span class="punto"></span>
                <span class="punto"></span><span class="punto"></span>
            </div>

            <div id="error-pin" class="aviso error oculto"></div>

            <div class="teclado">
                <button data-tecla="1">1</button><button data-tecla="2">2</button><button data-tecla="3">3</button>
                <button data-tecla="4">4</button><button data-tecla="5">5</button><button data-tecla="6">6</button>
                <button data-tecla="7">7</button><button data-tecla="8">8</button><button data-tecla="9">9</button>
                <button class="gris" data-tecla="limpiar">Borrar</button>
                <button data-tecla="0">0</button>
                <button class="gris" data-tecla="atras"><i class="bi bi-backspace"></i></button>
            </div>

            <?php if ($presentes !== []): ?>
                <div class="presentes">
                    <?php foreach ($presentes as $p): ?>
                        <span class="chip <?= $p['estado'] === 'pausa' ? 'pausa' : '' ?>">
                            <?= esc($p['empleado']['nombre']) ?>
                            <?= $p['estado'] === 'pausa' ? '· en pausa' : '' ?>
                        </span>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <!-- ── Paso 2: elegir acción ── -->
        <div class="tarjeta ancha oculto" id="paso-accion">
            <div class="quien">
                <div class="nombre" id="ac-nombre"></div>
                <div class="cargo" id="ac-cargo"></div>
                <span class="estado" id="ac-estado"></span>
                <p class="ayuda" id="ac-ultimo" style="margin-top:8px"></p>
            </div>

            <?php if ($pideFoto): ?>
                <div class="camara-caja">
                    <video id="camara" autoplay playsinline muted></video>
                    <div class="camara-aviso" id="camara-estado">Preparando la cámara…</div>
                </div>
            <?php endif ?>

            <div id="error-accion" class="aviso error oculto"></div>

            <div class="acciones" id="ac-botones"></div>
            <button class="accion gris" data-cancelar>Cancelar</button>
        </div>

        <!-- ── Paso 3: confirmación ── -->
        <div class="tarjeta oculto" id="paso-ok">
            <div class="exito">
                <div class="marca-ok"><i class="bi bi-check-lg"></i></div>
                <h1 id="ok-mensaje"></h1>
                <div class="aviso info oculto" id="ok-sinfoto" style="margin-top:12px">
                    <i class="bi bi-camera-video-off"></i>
                    La cámara no funcionó, así que quedó registrado sin foto. Avisa a gerencia.
                </div>
                <p class="ayuda" style="margin-top:10px">Volviendo al inicio…</p>
            </div>
        </div>
    </main>

    <footer>
        <?php if ($pideFoto): ?>
            Al fichar se toma una foto para verificar quién marca. Se conserva solo con ese fin
            y la consulta únicamente gerencia (Ley 1581 de 2012).
        <?php else: ?>
            Cada marca queda registrada con su hora. Si te equivocas, avisa a gerencia: se corrige dejando constancia.
        <?php endif ?>
    </footer>

<script>
(function () {
    'use strict';

    const PIDE_FOTO = <?= $pideFoto ? 'true' : 'false' ?>;
    const LONGITUD = <?= \App\Models\EmpleadoModel::LONGITUD_PIN ?>;
    const BASE = <?= json_encode(rtrim(site_url('fichar'), '/')) ?>;
    let token = <?= json_encode(csrf_hash()) ?>;
    const NOMBRE_TOKEN = <?= json_encode(csrf_token()) ?>;

    let pin = '';
    let flujo = null;      // datos del empleado identificado
    let volverEn = null;

    const $ = (s) => document.querySelector(s);

    // ── Reloj ────────────────────────────────────────────────────
    function pintarReloj() {
        const ahora = new Date();
        $('#reloj').textContent = ahora.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', hour12: false });
        $('#fecha').textContent = ahora.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });
    }
    pintarReloj();
    setInterval(pintarReloj, 10000);

    // ── Peticiones ───────────────────────────────────────────────
    async function api(ruta, cuerpo) {
        const r = await fetch(BASE + ruta, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(Object.assign({}, cuerpo, { [NOMBRE_TOKEN]: token })),
        });
        const nuevo = r.headers.get('X-CSRF-TOKEN');
        if (nuevo) { token = nuevo; }

        let datos = null;
        try { datos = await r.json(); } catch (e) { datos = null; }

        if (!r.ok || !datos || !datos.ok) {
            return { ok: false, error: (datos && datos.error) || 'No se pudo conectar con el sistema.' };
        }
        return datos;
    }

    // ── Paso 1: PIN ──────────────────────────────────────────────
    function pintarVisor() {
        const puntos = $('#visor').children;
        for (let i = 0; i < puntos.length; i++) {
            puntos[i].classList.toggle('lleno', i < pin.length);
        }
    }

    function errorPin(texto) {
        const caja = $('#error-pin');
        caja.textContent = texto;
        caja.classList.remove('oculto');
        setTimeout(() => caja.classList.add('oculto'), 4500);
    }

    document.querySelectorAll('[data-tecla]').forEach(function (b) {
        b.addEventListener('click', function () {
            const t = b.dataset.tecla;
            if (t === 'limpiar') { pin = ''; }
            else if (t === 'atras') { pin = pin.slice(0, -1); }
            else if (pin.length < LONGITUD) { pin += t; }

            pintarVisor();
            if (pin.length === LONGITUD) { setTimeout(identificar, 120); }
        });
    });

    // También con el teclado físico, por si el terminal es un computador
    document.addEventListener('keydown', function (e) {
        if ($('#paso-pin').classList.contains('oculto')) { return; }
        if (e.key >= '0' && e.key <= '9' && pin.length < LONGITUD) {
            pin += e.key; pintarVisor();
            if (pin.length === LONGITUD) { setTimeout(identificar, 120); }
        } else if (e.key === 'Backspace') {
            pin = pin.slice(0, -1); pintarVisor();
        } else if (e.key === 'Escape') {
            pin = ''; pintarVisor();
        }
    });

    async function identificar() {
        const datos = await api('/identificar', { pin: pin });

        if (!datos.ok) {
            errorPin(datos.error);
            pin = ''; pintarVisor();
            return;
        }

        flujo = datos;
        mostrarAcciones();
    }

    // ── Paso 2: acciones ─────────────────────────────────────────
    function mostrarAcciones() {
        $('#ac-nombre').textContent = flujo.empleado.nombre + ' ' + flujo.empleado.apellidos;
        $('#ac-cargo').textContent = flujo.empleado.cargo || '';

        const etiquetas = { dentro: 'Estás dentro', fuera: 'Estás fuera', pausa: 'En pausa' };
        const estado = $('#ac-estado');
        estado.textContent = etiquetas[flujo.estado] || '';
        estado.className = 'estado ' + flujo.estado;

        $('#ac-ultimo').textContent = flujo.ultimo ? 'Última marca: ' + flujo.ultimo : 'Es tu primer fichaje.';

        const cont = $('#ac-botones');
        cont.innerHTML = '';
        flujo.acciones.forEach(function (a) {
            const b = document.createElement('button');
            b.className = 'accion ' + a.tipo;
            b.innerHTML = '<i class="bi ' + a.icono + '"></i> ' + a.etiqueta;
            b.onclick = () => marcar(a.tipo, b);
            cont.appendChild(b);
        });

        cambiarPaso('#paso-accion');
        if (PIDE_FOTO) { abrirCamara(); }
        programarVuelta(45000);
    }

    // ── Cámara ───────────────────────────────────────────────────
    let flujoVideo = null;

    async function abrirCamara() {
        const aviso = $('#camara-estado');
        try {
            flujoVideo = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 } }, audio: false,
            });
            $('#camara').srcObject = flujoVideo;
            aviso.textContent = 'Mira a la cámara y pulsa la acción';
        } catch (e) {
            aviso.textContent = 'Sin cámara disponible: avisa a gerencia';
        }
    }

    function cerrarCamara() {
        if (flujoVideo) {
            flujoVideo.getTracks().forEach((t) => t.stop());
            flujoVideo = null;
        }
    }

    function capturar() {
        const video = $('#camara');
        if (!flujoVideo || !video.videoWidth) { return null; }

        const c = document.createElement('canvas');
        c.width = 480;
        c.height = Math.round(480 * video.videoHeight / video.videoWidth);
        c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);

        return c.toDataURL('image/jpeg', 0.72);
    }

    // ── Marcar ───────────────────────────────────────────────────
    async function marcar(tipo, boton) {
        boton.disabled = true;
        const original = boton.innerHTML;
        boton.innerHTML = '<i class="bi bi-hourglass-split"></i> Registrando…';

        const datos = await api('/marcar', { pin: pin, tipo: tipo, foto: PIDE_FOTO ? capturar() : null });

        if (!datos.ok) {
            const caja = $('#error-accion');
            caja.textContent = datos.error;
            caja.classList.remove('oculto');
            boton.disabled = false;
            boton.innerHTML = original;
            programarVuelta(20000);
            return;
        }

        cerrarCamara();
        $('#ok-mensaje').textContent = datos.mensaje;
        $('#ok-sinfoto').classList.toggle('oculto', !datos.sin_foto);
        cambiarPaso('#paso-ok');
        programarVuelta(datos.sin_foto ? 7000 : 5000);
    }

    // ── Navegación ───────────────────────────────────────────────
    function cambiarPaso(cual) {
        ['#paso-pin', '#paso-accion', '#paso-ok'].forEach(function (p) {
            $(p).classList.toggle('oculto', p !== cual);
        });
        $('#error-accion').classList.add('oculto');
    }

    function reiniciar() {
        cerrarCamara();
        pin = ''; flujo = null;
        pintarVisor();
        cambiarPaso('#paso-pin');
        clearTimeout(volverEn);
    }

    function programarVuelta(ms) {
        clearTimeout(volverEn);
        volverEn = setTimeout(reiniciar, ms);
    }

    document.querySelectorAll('[data-cancelar]').forEach((b) => b.addEventListener('click', reiniciar));

    // Si el terminal se queda solo con la pantalla a medias, vuelve al inicio
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { reiniciar(); }
    });
})();
</script>

<?= view("partes/espera") ?>
</body>
</html>
