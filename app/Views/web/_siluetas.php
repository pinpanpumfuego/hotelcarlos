<?php
/**
 * Siluetas de aves, dibujadas a mano en SVG.
 *
 * Se usan siluetas y no ilustraciones a color por una razón práctica: una
 * ilustración realista mal hecha canta muchísimo, y una silueta bien
 * proporcionada es elegante siempre. Es lo que usan los buenos hoteles de
 * naturaleza.
 *
 * Cada especie tiene la suya, pensada para reconocerse por la forma: la cola
 * de raqueta del barranquero, el pico grueso del tucán barbudo, la cresta en
 * media luna del gallito de roca, el pico largo del colibrí.
 *
 * Van en un `<defs>` una sola vez y luego se referencian con `<use>`: así el
 * mismo dibujo puede aparecer diez veces sin repetir su código.
 */
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <defs>
        <!-- ── Ave en vuelo, alas abajo ── -->
        <symbol id="ave-abajo" viewBox="0 0 100 44">
            <path d="M50 20c-3.4 0-6 1.1-7.6 2.6C36.8 20.2 26 16.6 14 17.6 8 18.1 3.4 20 0 22.6
                     6 21.4 12 21.6 17.6 23c7 1.8 13.4 5.6 18.6 10.6 2.6 2.5 5.6 4.4 8.8 5.6
                     1.8.7 3.4 1 5 1s3.2-.3 5-1c3.2-1.2 6.2-3.1 8.8-5.6C69 28.6 75.4 24.8 82.4 23
                     88 21.6 94 21.4 100 22.6c-3.4-2.6-8-4.5-14-5-12-1-22.8 2.6-28.4 5A11.6 11.6 0 0 0 50 20z"/>
        </symbol>

        <!-- ── Ave en vuelo, alas arriba ── -->
        <symbol id="ave-arriba" viewBox="0 0 100 44">
            <path d="M50 30c-3.4 0-6-1.1-7.6-2.6C36.8 29.8 26 33.4 14 32.4 8 31.9 3.4 30 0 27.4
                     6 28.6 12 28.4 17.6 27c7-1.8 13.4-5.6 18.6-10.6 2.6-2.5 5.6-4.4 8.8-5.6
                     1.8-.7 3.4-1 5-1s3.2.3 5 1c3.2 1.2 6.2 3.1 8.8 5.6C69 21.4 75.4 25.2 82.4 27
                     88 28.4 94 28.6 100 27.4c-3.4 2.6-8 4.5-14 5-12 1-22.8-2.6-28.4-5A11.6 11.6 0 0 1 50 30z"/>
        </symbol>

        <!-- ── Ave planeando, alas en V suave ── -->
        <symbol id="ave-planea" viewBox="0 0 100 30">
            <path d="M50 16c-2.6 0-4.6.8-6 2-6-3.4-17.4-7-30-6.4C7.6 11.8 3 13.2 0 15
                     5.4 13.4 11.4 13 17.6 14c8.8 1.4 16.4 4.6 22 8.4 2.6 1.8 5.6 3 8.4 3.4h4
                     c2.8-.4 5.8-1.6 8.4-3.4 5.6-3.8 13.2-7 22-8.4 6.2-1 12.2-.6 17.6 1
                     -3-1.8-7.6-3.2-14-3.4-12.6-.6-24 3-30 6.4-1.4-1.2-3.4-2-6-2z"/>
        </symbol>

        <!-- ── Barranquero: la cola de raqueta lo delata ── -->
        <symbol id="perfil-motmot" viewBox="0 0 120 120">
            <path d="M72 22c-9 0-16.6 5-20.4 12.4-2.6 5-3 9.6-2.6 14.6.4 5.2-.6 9-3.4 13.4
                     -3 4.6-4.6 9.4-4.6 14.6 0 6 2.4 11 6.4 14.6l-3 4.4 5.4-1.6c3 1.6 6.4 2.4 10 2.4
                     11.6 0 21-8.6 22.6-19.8.6-4.2 2.2-7.4 4.8-10.8 4-5.2 6-11 6-17.4C93.2 33 84 22 72 22z"/>
            <path d="M91 30l13-6-11 9z"/>
            <!-- La raqueta: dos plumas desnudas con la paleta al final -->
            <path d="M56 92c-1 8-2.4 16.4-4.4 24.6l1.8.4c2.4-8.2 4-16.6 5-24.8z"/>
            <path d="M64 92c-.4 8-.6 16.2-.4 24.6h1.8c0-8.4.4-16.6 1-24.6z"/>
            <ellipse cx="50.6" cy="115" rx="3.4" ry="4.6"/>
            <ellipse cx="64.6" cy="115" rx="3.4" ry="4.6"/>
        </symbol>

        <!-- ── Tangara: pequeña, compacta, cola corta ── -->
        <symbol id="perfil-tangara" viewBox="0 0 120 120">
            <path d="M66 34c-14 0-25.4 9.6-28.4 22.4-1 4.2-3 7.4-6 10.6-4.4 4.6-6.6 10-6.6 16
                     0 12.4 10 22 23 22 5 0 9.6-1.4 13.4-3.8 3.4-2.2 6.6-3.2 10.6-3.4
                     14.4-.8 25-11.4 25-25.4C97 51.8 83.4 34 66 34z"/>
            <path d="M36 58L22 52l14 2z"/>
            <path d="M76 96c3.4 6 6.4 12.4 9 19.2l1.8-.6c-2.4-7-5-13.6-8-19.6z"/>
        </symbol>

        <!-- ── Tucán barbudo: todo es el pico ── -->
        <symbol id="perfil-barbudo" viewBox="0 0 120 120">
            <path d="M70 34c-15.6 0-28 10.6-30.6 24.8-.8 4.4-2.6 7.6-5.6 11-4.4 5-6.6 10.6-6.6 16.8
                     0 12.6 10 22.4 23 22.4 5.4 0 10.4-1.6 14.4-4.4 3.2-2.2 6.4-3.2 10.2-3.6
                     14-1.4 24.2-12 24.2-26C99 50.6 86.4 34 70 34z"/>
            <!-- Pico grueso y curvo, la marca de la casa -->
            <path d="M42 52c-8.6-2-17-5.6-24.6-10.6-.8 5.4 1 10 5 13.6 4.6 4 11 6.2 18.4 6.4z"/>
            <path d="M84 100c3.6 5.6 6.8 11.6 9.4 18l1.8-.6c-2.4-6.6-5.2-12.8-8.4-18.4z"/>
        </symbol>

        <!-- ── Gallito de roca: la cresta en media luna ── -->
        <symbol id="perfil-gallito" viewBox="0 0 120 120">
            <path d="M64 40c-16 0-29 11.4-29 26 0 5-1.6 8.8-4.6 12.8-4 5.4-5.4 10.6-5.4 16.2
                     0 12.4 9.6 21 22 21 5 0 9.6-1.4 13.4-4 3.4-2.2 6.6-3 10.4-3.4
                     14-1.4 24.2-11.6 24.2-25.4C95 57.4 82 40 64 40z"/>
            <!-- La cresta: un disco que le tapa el pico -->
            <path d="M64 40c-14 0-25.4-6.6-25.4-16.4C38.6 13.8 50 6 64 6s25.4 7.8 25.4 17.6
                     C89.4 33.4 78 40 64 40z"/>
            <path d="M78 98c4 6 7.6 12.6 10.4 19.6l1.8-.6c-2.6-7.2-6-14-9.8-20.2z"/>
        </symbol>

        <!-- ── Colibrí: pico larguísimo y alas en aspa ── -->
        <symbol id="perfil-colibri" viewBox="0 0 120 120">
            <path d="M62 46c-10 0-18 7-18 16.4 0 4-1.4 6.8-4 9.6-3 3.4-4.4 7-4.4 11
                     0 8.4 6.6 14.6 15.4 14.6 3.6 0 6.8-1 9.4-2.8 2.4-1.6 4.6-2.2 7.4-2.4
                     9.6-.8 16.6-8 16.6-17.6C84.4 58.6 74.4 46 62 46z"/>
            <!-- El pico, casi tan largo como el cuerpo -->
            <path d="M46 56L10 42l35.4 16.6z" stroke-width="0"/>
            <path d="M45 55.4L9.6 41.2l-.8 2 35.4 14.2z"/>
            <path d="M74 88c3 5 5.6 10.4 7.6 16.2l1.8-.6c-1.8-6-4.4-11.6-7.4-16.8z"/>
        </symbol>

        <!-- ── Pava caucana: cuerpo largo y cola ancha ── -->
        <symbol id="perfil-pava" viewBox="0 0 120 120">
            <path d="M52 30c-8.6 0-15.4 6.4-15.4 14.6 0 3.6.8 6.4 2.4 9.4 1.8 3.4 2 6.2 1 9.8
                     -1.4 5-1 9.6 1 14 3.6 8 11.6 12.8 21.4 12.8 4.6 0 8.8-1 12.4-3
                     3-1.6 5.8-2.2 9-2.4 2.6-.2 5-.8 7.2-1.8-2.8 4.6-7 8-12.4 9.8
                     l.6 1.8c8.4-2.6 14.4-8.6 16.6-16.4 1-3.6 1-7 .2-10.4C92.4 44.6 74 30 52 30z"/>
            <path d="M38 40L24 34l14 2z"/>
            <!-- Cola larga, característica de las pavas -->
            <path d="M36 62c-8 8.6-15.6 18.4-22.6 29.4l1.6 1c7.2-11 14.8-20.6 22.6-29z"/>
            <path d="M40 68c-6.6 9.6-12.6 20.2-18 31.6l1.8.8c5.4-11.2 11.2-21.6 17.6-31z"/>
        </symbol>
    </defs>
</svg>
