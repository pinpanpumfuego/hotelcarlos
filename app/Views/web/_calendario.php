<?php
/**
 * Calendario de rango con precios y disponibilidad real.
 *
 * Se hace a mano porque `<input type="date">` abre el calendario del sistema
 * operativo: no deja pintar precios ni tachar días ocupados, que es justo lo
 * que se pide aquí.
 *
 * Los dos campos originales siguen existiendo ocultos, así que el resto del
 * motor de reservas no cambia ni una línea.
 */
?>
<div class="cal" id="cal" data-url="<?= url_web('reservar/calendario') ?>">
    <div class="cal-campos">
        <button type="button" class="cal-campo activo" data-campo="entrada">
            <span class="et"><?= esc(lang('Web.llegada')) ?></span>
            <strong id="cal-txt-entrada"><?= esc(lang('Web.elegir')) ?></strong>
        </button>
        <i class="bi bi-arrow-right cal-flecha" aria-hidden="true"></i>
        <button type="button" class="cal-campo" data-campo="salida">
            <span class="et"><?= esc(lang('Web.salida')) ?></span>
            <strong id="cal-txt-salida">—</strong>
        </button>
    </div>

    <div class="cal-meses" id="cal-meses">
        <div class="cal-cargando"><i class="bi bi-arrow-repeat"></i> <?= esc(lang('Web.buscando')) ?></div>
    </div>

    <div class="cal-pie">
        <button type="button" class="cal-nav" id="cal-antes" aria-label="<?= esc(lang('Web.mesAnterior')) ?>">
            <i class="bi bi-chevron-left"></i>
        </button>
        <div class="cal-resumen" id="cal-resumen"><?= esc(lang('Web.eligeFechas')) ?></div>
        <button type="button" class="cal-nav" id="cal-despues" aria-label="<?= esc(lang('Web.mesSiguiente')) ?>">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>

    <p class="cal-leyenda">
        <span><i class="bi bi-circle-fill"></i> <?= esc(lang('Web.calLibre')) ?></span>
        <span class="pocas"><i class="bi bi-circle-fill"></i> <?= esc(lang('Web.calPocas')) ?></span>
        <span class="lleno"><i class="bi bi-x-lg"></i> <?= esc(lang('Web.calLleno')) ?></span>
    </p>
</div>

<input type="hidden" name="entrada" id="f-entrada" value="<?= esc(old('entrada', '')) ?>" required>
<input type="hidden" name="salida"  id="f-salida"  value="<?= esc(old('salida', '')) ?>" required>

<style>
    .cal { background: #fff; border: 1px solid #e6e1d6; border-radius: 1rem; padding: 1rem; }

    /* ── Los dos campos ── */
    .cal-campos { display: flex; align-items: center; gap: .6rem; margin-bottom: 1rem; }
    .cal-campo {
        flex: 1; text-align: left; background: var(--crema); border: 2px solid transparent;
        border-radius: .8rem; padding: .6rem .9rem; min-height: 58px;
    }
    .cal-campo.activo { border-color: var(--bosque); background: #fff; }
    .cal-campo .et { display: block; font-size: .74rem; letter-spacing: .06em;
                     text-transform: uppercase; color: var(--arena); font-weight: 600; }
    .cal-campo strong { font-size: 1rem; }
    .cal-flecha { color: #b9b2a2; }

    /* ── Rejilla ── */
    .cal-meses { display: grid; gap: 1.4rem; }
    @media (min-width: 768px) { .cal-meses { grid-template-columns: 1fr 1fr; } }
    .cal-mes h3 { font-size: .98rem; text-align: center; margin: 0 0 .6rem;
                  text-transform: capitalize; font-family: 'Inter', sans-serif; font-weight: 600; }
    .cal-dias, .cal-cabecera { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
    .cal-cabecera span { text-align: center; font-size: .68rem; color: #9a9384;
                         text-transform: uppercase; padding-bottom: .3rem; }

    /* Cada día: número arriba, precio abajo. 46 px de alto para que se pueda
       tocar con el dedo sin fallar. */
    .cal-dia {
        border: 0; background: transparent; border-radius: .55rem; padding: .25rem 0 .3rem;
        min-height: 46px; display: flex; flex-direction: column; align-items: center;
        justify-content: center; line-height: 1.1; cursor: pointer; color: var(--tinta);
    }
    .cal-dia .n { font-size: .9rem; font-weight: 500; }
    .cal-dia .p { font-size: .58rem; color: #8a8375; margin-top: 1px; }
    .cal-dia:hover:not(:disabled) { background: #eef3ee; }
    .cal-dia.vacio { visibility: hidden; }

    /* Ocupado o pasado: no es que esté apagado, es que no se puede */
    .cal-dia:disabled { cursor: not-allowed; color: #c9c4b8; }
    .cal-dia:disabled .p { display: none; }
    .cal-dia.lleno .n { text-decoration: line-through; }

    /* Quedan una o dos: es información cierta, no un truco de urgencia */
    .cal-dia.pocas .p { color: var(--arena-oscura); font-weight: 600; }

    .cal-dia.entrada, .cal-dia.salida { background: var(--bosque); color: #fff; }
    .cal-dia.entrada .p, .cal-dia.salida .p { color: rgba(255,255,255,.8); }
    .cal-dia.entrada { border-radius: .55rem 0 0 .55rem; }
    .cal-dia.salida  { border-radius: 0 .55rem .55rem 0; }
    .cal-dia.entrada.salida { border-radius: .55rem; }
    .cal-dia.dentro { background: #e3ede5; border-radius: 0; }

    .cal-pie { display: flex; align-items: center; gap: .8rem; margin-top: 1rem; }
    .cal-nav { width: 40px; height: 40px; border-radius: 50%; border: 1px solid #e6e1d6;
               background: #fff; flex: none; }
    .cal-nav:disabled { opacity: .35; }
    .cal-resumen { flex: 1; text-align: center; font-size: .9rem; color: var(--texto-suave, #6d7a72); }
    .cal-resumen strong { color: var(--bosque); }

    .cal-leyenda { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;
                   font-size: .74rem; color: #8a8375; margin: .9rem 0 0; }
    .cal-leyenda i { font-size: .5rem; vertical-align: middle; color: var(--bosque); }
    .cal-leyenda .pocas i { color: var(--arena); }
    .cal-leyenda .lleno i { font-size: .65rem; color: #c9c4b8; }
    .cal-cargando { text-align: center; color: #9a9384; padding: 2rem 0; }
    .cal-cargando i { display: inline-block; animation: girar 1s linear infinite; }
    @keyframes girar { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .cal-cargando i { animation: none; } }
</style>

<script>
(function () {
    'use strict';
    const caja = document.getElementById('cal');
    if (!caja) { return; }

    const IDIOMA = document.documentElement.lang || 'es';
    const T = {
        elegir: <?= json_encode(lang('Web.elegir')) ?>,
        eligeFechas: <?= json_encode(lang('Web.eligeFechas')) ?>,
        eligeSalida: <?= json_encode(lang('Web.eligeSalida')) ?>,
        noches: <?= json_encode(lang('Web.nochesN', [0])) ?>,
        total: <?= json_encode(lang('Web.total')) ?>,
        quedanUna: <?= json_encode(lang('Web.quedaUna')) ?>,
    };

    let datos = null;          // lo que devuelve el servidor
    let primerMes = null;      // primer día del mes que se muestra a la izquierda
    let entrada = null, salida = null;
    let eligiendo = 'entrada';

    const pesos = (n) => '$' + Math.round(n / 1000) + 'k';
    const iso = (d) => d.toISOString().slice(0, 10);
    const sumaDias = (f, n) => { const d = new Date(f + 'T12:00:00'); d.setDate(d.getDate() + n); return iso(d); };

    async function cargar(desdeMes) {
        const r = await fetch(caja.dataset.url + '?desde=' + desdeMes + '&meses=2', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        datos = await r.json();
        pintar();
    }

    /** ¿Se puede llegar ese día? Tiene que quedar algo esa noche. */
    function sirveComoEntrada(f) {
        const d = datos.dias[f];
        return d && !d.pasado && d.libres > 0;
    }

    /**
     * ¿Se puede salir ese día?
     *
     * Hay que poder dormir **todas** las noches desde la llegada hasta la
     * víspera. Y ojo: el propio día de salida no se duerme, así que da igual
     * que esté lleno. Es el detalle que casi todas las webs de hotel fallan.
     */
    function sirveComoSalida(f) {
        if (!entrada || f <= entrada) { return false; }
        for (let n = entrada; n < f; n = sumaDias(n, 1)) {
            const d = datos.dias[n];
            if (!d || d.libres <= 0) { return false; }
        }
        return true;
    }

    function pintar() {
        const cont = document.getElementById('cal-meses');
        cont.innerHTML = '';

        for (let m = 0; m < 2; m++) {
            const base = new Date(primerMes + 'T12:00:00');
            base.setMonth(base.getMonth() + m);
            cont.appendChild(pintarMes(base));
        }

        // No se puede retroceder más allá del mes en curso
        const hoyMes = datos.hoy.slice(0, 7) + '-01';
        document.getElementById('cal-antes').disabled = primerMes <= hoyMes;
        pintarResumen();
    }

    function pintarMes(base) {
        const div = document.createElement('div');
        div.className = 'cal-mes';

        const titulo = base.toLocaleDateString(IDIOMA, { month: 'long', year: 'numeric' });
        // Los días de la semana salen del propio navegador: así el orden y los
        // nombres son los correctos en cada idioma sin traducirlos a mano
        let cab = '';
        for (let i = 0; i < 7; i++) {
            const d = new Date(2024, 0, 1 + i);   // 1 de enero de 2024 fue lunes
            cab += '<span>' + d.toLocaleDateString(IDIOMA, { weekday: 'narrow' }) + '</span>';
        }

        div.innerHTML = '<h3>' + titulo + '</h3><div class="cal-cabecera">' + cab + '</div>';

        const rejilla = document.createElement('div');
        rejilla.className = 'cal-dias';

        const primero = new Date(base.getFullYear(), base.getMonth(), 1);
        const ultimo = new Date(base.getFullYear(), base.getMonth() + 1, 0);
        // La semana empieza en lunes
        const hueco = (primero.getDay() + 6) % 7;
        for (let i = 0; i < hueco; i++) {
            rejilla.insertAdjacentHTML('beforeend', '<span class="cal-dia vacio"></span>');
        }

        for (let dia = 1; dia <= ultimo.getDate(); dia++) {
            const f = base.getFullYear() + '-' + String(base.getMonth() + 1).padStart(2, '0')
                + '-' + String(dia).padStart(2, '0');
            rejilla.appendChild(pintarDia(f, dia));
        }

        div.appendChild(rejilla);
        return div;
    }

    function pintarDia(f, numero) {
        const d = datos.dias[f];
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'cal-dia';
        b.dataset.fecha = f;

        const sirve = eligiendo === 'salida' ? sirveComoSalida(f) : sirveComoEntrada(f);
        const lleno = d && !d.pasado && d.libres <= 0;

        b.disabled = !sirve;
        if (lleno) { b.classList.add('lleno'); }
        if (d && d.libres > 0 && d.libres <= datos.pocas) { b.classList.add('pocas'); }

        if (f === entrada) { b.classList.add('entrada'); }
        if (f === salida) { b.classList.add('salida'); }
        if (entrada && salida && f > entrada && f < salida) { b.classList.add('dentro'); }

        let precio = '';
        if (d && d.desde && !d.pasado && d.libres > 0) {
            precio = '<span class="p">' + (d.libres <= datos.pocas
                ? (d.libres === 1 ? T.quedanUna : d.libres + '·' + pesos(d.desde))
                : pesos(d.desde)) + '</span>';
        }
        b.innerHTML = '<span class="n">' + numero + '</span>' + precio;

        b.onclick = () => elegir(f);
        return b;
    }

    function elegir(f) {
        if (eligiendo === 'entrada' || !entrada || f <= entrada) {
            entrada = f;
            salida = null;
            eligiendo = 'salida';
        } else {
            salida = f;
            eligiendo = 'entrada';
        }
        document.getElementById('f-entrada').value = entrada || '';
        document.getElementById('f-salida').value = salida || '';
        document.querySelectorAll('.cal-campo').forEach((c) =>
            c.classList.toggle('activo', c.dataset.campo === eligiendo));
        pintar();
    }

    function pintarResumen() {
        const fmt = (f) => f ? new Date(f + 'T12:00:00').toLocaleDateString(IDIOMA,
            { day: 'numeric', month: 'short' }) : null;

        document.getElementById('cal-txt-entrada').textContent = fmt(entrada) || T.elegir;
        document.getElementById('cal-txt-salida').textContent = fmt(salida) || '—';

        const r = document.getElementById('cal-resumen');
        if (!entrada) { r.textContent = T.eligeFechas; return; }
        if (!salida) { r.textContent = T.eligeSalida; return; }

        let noches = 0, total = 0;
        for (let n = entrada; n < salida; n = sumaDias(n, 1)) {
            noches++;
            total += (datos.dias[n] && datos.dias[n].desde) || 0;
        }
        r.innerHTML = '<strong>' + noches + '</strong> · ' + T.total + ' <strong>$'
            + Math.round(total).toLocaleString('es-CO') + '</strong>';
    }

    function mover(meses) {
        const d = new Date(primerMes + 'T12:00:00');
        d.setMonth(d.getMonth() + meses);
        primerMes = iso(new Date(d.getFullYear(), d.getMonth(), 1));
        cargar(primerMes);
    }

    document.getElementById('cal-antes').onclick = () => mover(-1);
    document.getElementById('cal-despues').onclick = () => mover(1);
    document.querySelectorAll('.cal-campo').forEach((c) => {
        c.onclick = () => {
            eligiendo = c.dataset.campo;
            if (eligiendo === 'entrada') { salida = null; document.getElementById('f-salida').value = ''; }
            document.querySelectorAll('.cal-campo').forEach((x) =>
                x.classList.toggle('activo', x === c));
            pintar();
        };
    });

    const hoy = new Date();
    primerMes = iso(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
    cargar(primerMes);
}());
</script>
