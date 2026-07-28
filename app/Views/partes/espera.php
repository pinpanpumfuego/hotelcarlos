<?php
/**
 * Aviso de «se está haciendo» para todo el sistema.
 *
 * Tres cosas, y ninguna estorba cuando no hace falta:
 *
 * 1. **Barra fina arriba** mientras se navega o se envía algo. Aparece a los
 *    180 ms: si la pantalla responde antes —que es lo normal en la red del
 *    hotel— no llega a verse, y así no se convierte en un parpadeo constante
 *    que acaba ignorándose.
 * 2. **El botón que se pulsó** se queda con una ruedita y deja de admitir
 *    clics. Es donde el usuario está mirando, y de paso resuelve el doble
 *    envío: dos clics rápidos en «Cobrar» eran dos cobros.
 * 3. **`window.espera`** para las pantallas que hablan por `fetch` —TPV,
 *    comandero, cocina— que no recargan y por tanto no disparan lo anterior.
 *
 * Sin Bootstrap a propósito: el TPV, el terminal de fichaje y el comandero
 * tienen su propio CSS y esto tiene que funcionar igual en los cuatro sitios.
 */
?>
<style>
    .espera-barra {
        position: fixed; top: 0; left: 0; height: 3px; width: 0;
        background: currentColor; color: #2e7d5b;
        z-index: 20000; opacity: 0; pointer-events: none;
        transition: width .2s ease, opacity .2s ease;
        box-shadow: 0 0 8px currentColor;
    }
    .espera-barra.viva { opacity: 1; }

    .espera-rueda {
        display: inline-block; width: 1em; height: 1em;
        border: 2px solid currentColor; border-right-color: transparent;
        border-radius: 50%; vertical-align: -.125em;
        animation: espera-giro .7s linear infinite;
    }

    @keyframes espera-giro { to { transform: rotate(360deg); } }

    /* Mientras se espera, el cursor lo dice y nada del botón admite clics. */
    [data-esperando] { cursor: progress !important; opacity: .85; }
    [data-esperando] > * { pointer-events: none; }

    @media (prefers-reduced-motion: reduce) {
        .espera-rueda { animation-duration: 2s; }
        .espera-barra { transition: none; }
    }
</style>

<div class="espera-barra" id="espera-barra" role="progressbar" aria-hidden="true"></div>

<script>
(function () {
    'use strict';

    var barra   = document.getElementById('espera-barra');
    var pendientes = 0;
    var temporizador = null;
    var avance = null;

    function pintar(pct) { barra.style.width = pct + '%'; }

    function arrancar() {
        // 180 ms de gracia: lo que responde antes no necesita anunciarse, y una
        // barra que parpadea en cada clic deja de significar nada.
        temporizador = setTimeout(function () {
            barra.classList.add('viva');
            var pct = 8;
            pintar(pct);

            // Se acerca al final sin llegar nunca: no sabemos cuánto falta, y
            // fingir un porcentaje exacto sería mentir con precisión.
            avance = setInterval(function () {
                pct += (92 - pct) * 0.12;
                pintar(pct);
            }, 220);
        }, 180);
    }

    function parar() {
        clearTimeout(temporizador);
        clearInterval(avance);
        pintar(100);
        setTimeout(function () {
            barra.classList.remove('viva');
            setTimeout(function () { pintar(0); }, 220);
        }, 160);
    }

    var espera = {
        iniciar: function () {
            if (pendientes++ === 0) { arrancar(); }
        },
        terminar: function () {
            if (--pendientes <= 0) { pendientes = 0; parar(); }
        },
        /** Envuelve una promesa: la barra se va sola, salga bien o salga mal. */
        durante: function (promesa) {
            espera.iniciar();

            return Promise.resolve(promesa).finally(function () { espera.terminar(); });
        },
    };

    window.espera = espera;

    // ── El botón que se pulsó ───────────────────────────────────────────

    var ultimoBoton = null;

    document.addEventListener('click', function (e) {
        // `type` sale ya resuelto: un <button> sin type dentro de un formulario
        // vale 'submit'. Así no se marca el botón de un desplegable o de un
        // modal, que no envía nada y se quedaría girando.
        var b = e.target.closest('button, input[type="submit"]');
        if (b && b.form && b.type === 'submit') { ultimoBoton = b; }
    }, true);

    function ocupar(boton) {
        if (! boton || boton.dataset.esperando) { return; }

        // Se fija el ancho antes de cambiar el contenido: si no, el botón
        // encoge al perder su texto y la pantalla da un salto justo cuando el
        // usuario acaba de pulsar, que es cuando peor sienta.
        var caja = boton.getBoundingClientRect();
        boton.style.minWidth = Math.ceil(caja.width) + 'px';
        boton.dataset.esperando = '1';
        boton.dataset.contenido = boton.innerHTML;
        boton.innerHTML = '<span class="espera-rueda"></span>';
        boton.setAttribute('aria-busy', 'true');

        // Deshabilitar va en el siguiente ciclo a propósito: un botón
        // deshabilitado NO se envía, y hay formularios que distinguen qué
        // botón se pulsó. Primero se manda, después se apaga.
        setTimeout(function () { boton.disabled = true; }, 0);
    }

    function soltar(boton) {
        if (! boton || ! boton.dataset.esperando) { return; }

        boton.disabled = false;
        boton.innerHTML = boton.dataset.contenido;
        boton.removeAttribute('aria-busy');
        delete boton.dataset.esperando;
        delete boton.dataset.contenido;
    }

    window.esperaSoltar = soltar;

    // ── Formularios ─────────────────────────────────────────────────────

    document.addEventListener('submit', function (e) {
        var form = e.target;

        // Este oyente va después del `onsubmit` del propio formulario, así que
        // aquí ya se sabe si un confirm() dijo que no. Sin esto, cancelar una
        // anulación dejaba la pantalla girando para siempre.
        if (e.defaultPrevented) { return; }
        if (form.hasAttribute('data-sin-espera')) { return; }

        var boton = (ultimoBoton && ultimoBoton.form === form)
            ? ultimoBoton
            : form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');

        ocupar(boton);

        // A una pestaña nueva —imprimir, descargar— esta página no va a ninguna
        // parte: la barra se quedaría colgada esperando algo que no llega.
        if (form.getAttribute('target') === '_blank') {
            setTimeout(function () { soltar(boton); }, 1200);

            return;
        }

        espera.iniciar();
    }, false);

    // ── Enlaces que navegan ─────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) { return; }

        var a = e.target.closest('a');
        if (! a) { return; }

        var destino = a.getAttribute('href') || '';

        if (a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-sin-espera')
            || a.hasAttribute('data-bs-toggle') || destino === '' || destino.charAt(0) === '#'
            || destino.indexOf('javascript:') === 0 || destino.indexOf('mailto:') === 0
            || destino.indexOf('tel:') === 0) {
            return;
        }

        // Solo lo que de verdad se va de esta página: un enlace a otro dominio
        // tampoco lo controlamos, pero al menos el navegador ya está yendo.
        if (a.host && a.host !== window.location.host) { return; }

        espera.iniciar();
    }, false);

    // ── Volver atrás ────────────────────────────────────────────────────

    // El navegador guarda la página tal cual estaba, botón girando incluido.
    // Sin esto, volver atrás enseña una pantalla que parece colgada.
    window.addEventListener('pageshow', function (e) {
        if (! e.persisted) { return; }

        pendientes = 0;
        parar();

        var ocupados = document.querySelectorAll('[data-esperando]');
        for (var i = 0; i < ocupados.length; i++) { soltar(ocupados[i]); }
    });

    // Si la navegación se cancela (una descarga, un enlace que no lleva a
    // ningún sitio), la barra no se queda ahí para siempre.
    window.addEventListener('pagehide', function () { pendientes = 0; });
})();
</script>
