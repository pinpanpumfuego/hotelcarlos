<?php
/**
 * Escena ilustrada SVG para las tarjetas de alojamiento.
 * Uso: view('web/_escena', ['variante' => 'cabana|glamping|habitacion|generico'])
 */
$variante = $variante ?? 'generico';
$id = 'esc' . substr(md5($variante . mt_rand()), 0, 6); // ids únicos por si hay varias escenas en la página
?>
<svg class="escena" viewBox="0 0 400 240" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Ilustración del alojamiento">
    <defs>
        <linearGradient id="<?= $id ?>-cielo" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#dff0e6"/>
            <stop offset="1" stop-color="#f7ecd4"/>
        </linearGradient>
        <linearGradient id="<?= $id ?>-agua" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#8fc0d4"/>
            <stop offset="1" stop-color="#4d87a3"/>
        </linearGradient>
    </defs>

    <!-- Cielo y sol -->
    <rect width="400" height="240" fill="url(#<?= $id ?>-cielo)"/>
    <circle cx="322" cy="52" r="26" fill="#f2c877" opacity=".9"/>

    <!-- Montañas -->
    <path d="M0 120 L70 60 L140 120 Z" fill="#a7c4b2"/>
    <path d="M90 130 L180 48 L280 130 Z" fill="#7fa892"/>
    <path d="M230 132 L320 70 L400 132 L400 140 L230 140 Z" fill="#93b8a3"/>

    <!-- Lago -->
    <rect y="130" width="400" height="110" fill="url(#<?= $id ?>-agua)"/>
    <ellipse cx="322" cy="150" rx="40" ry="5" fill="#f2c877" opacity=".35"/>
    <ellipse cx="120" cy="170" rx="70" ry="6" fill="#ffffff" opacity=".18"/>
    <ellipse cx="270" cy="200" rx="90" ry="7" fill="#ffffff" opacity=".12"/>

    <!-- Orilla -->
    <path d="M0 240 L0 205 Q120 185 240 205 T400 200 L400 240 Z" fill="#3c6650"/>

    <?php if ($variante === 'cabana'): ?>
        <!-- Cabaña entre pinos -->
        <g transform="translate(52,150)">
            <rect x="18" y="26" width="64" height="40" rx="2" fill="#7a5a3a"/>
            <path d="M10 28 L50 0 L90 28 Z" fill="#59422b"/>
            <rect x="40" y="42" width="16" height="24" fill="#3d2e1e"/>
            <rect x="24" y="34" width="12" height="10" fill="#f2d99b"/>
            <rect x="64" y="34" width="12" height="10" fill="#f2d99b"/>
        </g>
        <g fill="#28503c">
            <path d="M160 200 L172 168 L184 200 Z"/><rect x="169" y="198" width="6" height="8" fill="#59422b"/>
            <path d="M190 204 L204 164 L218 204 Z"/><rect x="200" y="202" width="7" height="9" fill="#59422b"/>
        </g>
    <?php elseif ($variante === 'glamping'): ?>
        <!-- Tienda glamping con fogata -->
        <g transform="translate(60,158)">
            <path d="M0 52 L38 0 L76 52 Z" fill="#e8e2d2"/>
            <path d="M28 52 L38 16 L48 52 Z" fill="#5c5142"/>
            <path d="M38 0 L38 52" stroke="#c9c0ab" stroke-width="2"/>
        </g>
        <g transform="translate(168,196)">
            <circle cx="0" cy="8" r="3" fill="#59422b"/><circle cx="10" cy="8" r="3" fill="#59422b"/>
            <path d="M2 4 Q5 -6 8 4 Z" fill="#e8964f"/>
        </g>
        <g fill="#28503c">
            <path d="M210 202 L224 160 L238 202 Z"/><rect x="220" y="200" width="7" height="9" fill="#59422b"/>
        </g>
    <?php elseif ($variante === 'habitacion'): ?>
        <!-- Edificio principal con ventanas cálidas -->
        <g transform="translate(48,142)">
            <rect x="0" y="22" width="110" height="52" rx="3" fill="#8a6a44"/>
            <path d="M-8 24 L55 -6 L118 24 Z" fill="#5f4a30"/>
            <rect x="12" y="34" width="16" height="14" rx="1" fill="#f2d99b"/>
            <rect x="47" y="34" width="16" height="14" rx="1" fill="#f2d99b"/>
            <rect x="82" y="34" width="16" height="14" rx="1" fill="#f2d99b"/>
            <rect x="47" y="52" width="16" height="22" fill="#3d2e1e"/>
        </g>
        <g fill="#28503c">
            <path d="M200 202 L214 162 L228 202 Z"/><rect x="210" y="200" width="7" height="9" fill="#59422b"/>
        </g>
    <?php else: ?>
        <!-- Escena genérica: muelle sobre el lago -->
        <g>
            <rect x="60" y="176" width="90" height="8" rx="2" fill="#7a5a3a"/>
            <rect x="70" y="184" width="6" height="18" fill="#59422b"/>
            <rect x="132" y="184" width="6" height="18" fill="#59422b"/>
        </g>
        <g fill="#28503c">
            <path d="M220 204 L234 162 L248 204 Z"/><rect x="230" y="202" width="7" height="9" fill="#59422b"/>
            <path d="M254 208 L266 172 L278 208 Z"/><rect x="262" y="206" width="6" height="8" fill="#59422b"/>
        </g>
    <?php endif ?>

    <!-- Aves -->
    <path d="M140 46 q6 -6 12 0 M158 54 q5 -5 10 0" stroke="#4a6a58" stroke-width="2" fill="none" stroke-linecap="round"/>
</svg>
