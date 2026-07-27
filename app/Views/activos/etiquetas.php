<?php
/**
 * Hoja de etiquetas para imprimir.
 *
 * No extiende el layout del panel a propósito: esto va a una impresora, y el
 * menú lateral y las barras solo gastarían tinta.
 *
 * Cada etiqueta lleva el QR y, debajo, el código en grande. Lo segundo no es
 * adorno: cuando el QR se despegue, se raye o se queme —y con un calentador se
 * va a quemar—, el código tecleado a mano sigue llevando al mismo sitio.
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas de equipos</title>
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            margin: 0;
            padding: 10mm;
            background: #f5f5f5;
        }

        .barra {
            max-width: 190mm;
            margin: 0 auto 8mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .barra h1 { font-size: 16px; margin: 0; }
        .barra p  { font-size: 12px; color: #555; margin: 4px 0 0; max-width: 110mm; }

        .barra button {
            padding: 8px 16px;
            font-size: 14px;
            border: 0;
            border-radius: 6px;
            background: #2c6e49;
            color: #fff;
            cursor: pointer;
        }

        .hoja {
            max-width: 190mm;
            margin: 0 auto;
            background: #fff;
            padding: 5mm;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4mm;
        }

        .etiqueta {
            border: 1px dashed #bbb;
            border-radius: 3mm;
            padding: 3mm;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .etiqueta svg { width: 100%; height: auto; max-width: 34mm; }

        .codigo {
            font-family: "SF Mono", Consolas, monospace;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-top: 1mm;
        }

        .nombre {
            font-size: 10px;
            line-height: 1.25;
            margin-top: 1mm;
            /* Dos líneas como mucho: si el nombre es largo, manda el código */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .sitio { font-size: 9px; color: #666; margin-top: 0.5mm; }
        .hotel { font-size: 8px; color: #999; margin-top: 1mm; }

        .vacio { padding: 20mm; text-align: center; color: #777; }

        @media print {
            body { background: #fff; padding: 0; }
            .barra { display: none; }
            .hoja { max-width: none; padding: 0; gap: 3mm; }
            .etiqueta { border-color: #ddd; }
        }
    </style>
</head>
<body>

<div class="barra">
    <div>
        <h1>Etiquetas de equipos — <?= count($etiquetas) ?></h1>
        <p>
            Imprímelas en papel adhesivo y pégalas donde se vean sin agacharse.
            En sitios con agua o calor, protégelas con cinta transparente encima:
            un QR descolorido no lo lee ningún móvil.
        </p>
    </div>
    <button onclick="window.print()">Imprimir</button>
</div>

<?php if ($etiquetas === []): ?>
    <div class="hoja"><div class="vacio">No hay ningún equipo que etiquetar.</div></div>
<?php else: ?>
    <div class="hoja">
        <?php foreach ($etiquetas as $e): ?>
            <div class="etiqueta">
                <?= $e['qr'] ?>
                <div class="codigo"><?= esc($e['activo']['codigo']) ?></div>
                <div class="nombre"><?= esc($e['activo']['nombre']) ?></div>
                <div class="sitio"><?= esc($e['activo']['unidad_nombre'] ?? $e['activo']['ubicacion'] ?? '') ?></div>
                <div class="hotel"><?= esc($hotel->nombre ?? '') ?></div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

</body>
</html>
