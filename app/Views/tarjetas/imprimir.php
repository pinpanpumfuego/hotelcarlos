<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($t['codigo']) ?></title>
    <?php
    /**
     * Tamaño de tarjeta bancaria: 85,6 × 54 mm. Es la medida de las fundas y de
     * las plastificadoras que se compran en cualquier papelería, y así cabe en
     * una cartera sin doblarse.
     */
    $paleta = [
        'primary'   => ['#12321f', '#e8efe9'],
        'success'   => ['#14532d', '#e7f3ec'],
        'info'      => ['#0b4a5c', '#e4f1f5'],
        'warning'   => ['#5c4409', '#f7f0dd'],
        'danger'    => ['#5c1616', '#f7e6e6'],
        'secondary' => ['#2b2f2d', '#eceeed'],
        'dark'      => ['#111', '#eee'],
    ];
    [$fondo, $tinta] = $paleta[$t['color']] ?? $paleta['primary'];
    $descuento = $t['descuento_pct'] !== null ? (float) $t['descuento_pct'] : (float) $t['tipo_descuento'];
    ?>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px; background: #f2f5f2;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #1c2a23;
        }
        .hoja { display: flex; gap: 12px; flex-wrap: wrap; }
        .tarjeta {
            width: 85.6mm; height: 54mm; border-radius: 3.5mm; padding: 5mm;
            position: relative; overflow: hidden; color: <?= $tinta ?>;
            background: <?= $fondo ?>;
            display: flex; flex-direction: column; justify-content: space-between;
        }
        .tarjeta.reverso { background: <?= $tinta ?>; color: <?= $fondo ?>; border: .3mm solid <?= $fondo ?>; }
        .marca { font-size: 7pt; letter-spacing: .18em; text-transform: uppercase; opacity: .8; }
        .modalidad { font-size: 12pt; font-weight: 700; letter-spacing: -.01em; }
        .titular { font-size: 10pt; font-weight: 600; }
        .codigo { font-family: ui-monospace, Consolas, monospace; font-size: 11pt; letter-spacing: .12em; }
        .nota { font-size: 6.5pt; line-height: 1.35; opacity: .85; }
        .qr { width: 26mm; height: 26mm; }
        .qr svg { width: 100%; height: 100%; display: block; }
        .sello { font-size: 7pt; font-weight: 700; border: .3mm solid currentColor;
                 border-radius: 2mm; padding: .6mm 1.6mm; display: inline-block; }
        .barra { display: flex; justify-content: space-between; align-items: flex-end; gap: 3mm; }

        .acciones { margin-bottom: 18px; }
        .acciones button, .acciones a {
            font: inherit; font-size: 14px; padding: 8px 14px; border-radius: 8px;
            border: 1px solid #c5d0c8; background: #fff; color: #1c2a23;
            text-decoration: none; cursor: pointer;
        }
        .pista { font-size: 12px; color: #6b7a71; margin-top: 12px; max-width: 480px; }

        @media print {
            body { background: #fff; padding: 0; }
            .acciones, .pista { display: none; }
            .tarjeta { break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="acciones">
    <button onclick="window.print()">Imprimir</button>
    <a href="<?= site_url('tarjetas/ver/' . $t['id']) ?>">Volver</a>
</div>

<div class="hoja">
    <?php // Anverso ?>
    <div class="tarjeta">
        <div>
            <div class="marca"><?= esc($hotel->nombre) ?></div>
            <div class="modalidad"><?= esc($t['tipo_nombre']) ?></div>
        </div>

        <div class="barra">
            <div>
                <div class="titular"><?= esc($t['titular']) ?></div>
                <div class="codigo"><?= esc($t['codigo']) ?></div>
                <?php if ($descuento > 0): ?>
                    <div style="margin-top:1.5mm;">
                        <span class="sello">
                            <?= rtrim(rtrim(number_format($descuento, 2, ',', '.'), '0'), ',') ?>% dto.
                        </span>
                    </div>
                <?php endif ?>
            </div>
            <div class="qr"><?= $qr ?></div>
        </div>
    </div>

    <?php // Reverso ?>
    <div class="tarjeta reverso">
        <div>
            <div class="marca">Cómo se usa</div>
            <div class="nota" style="margin-top:2mm;">
                Preséntala al pagar en
                <strong><?= esc(strtolower($ambitos[$t['ambito']] ?? 'todo el hotel')) ?></strong>.
                El cobro lo hace siempre una persona del hotel; el código por sí solo
                no paga nada.
                <?php if ($t['pin_hash'] !== null): ?>
                    Por encima de
                    $<?= number_format((float) $t['pin_desde'], 0, ',', '.') ?>
                    se te pedirá tu PIN.
                <?php endif ?>
            </div>
            <div class="nota" style="margin-top:2mm;">
                Escanea el QR para ver tu saldo y recargarla.
            </div>
        </div>

        <div class="barra">
            <div class="nota">
                <?php if ($t['caduca'] !== null): ?>
                    Válida hasta el <?= date('d/m/Y', strtotime($t['caduca'])) ?>.<br>
                <?php endif ?>
                Si la pierdes, avísanos y la bloqueamos al momento.<br>
                <?= esc($hotel->telefono ?? '') ?>
            </div>
            <div class="codigo" style="font-size:9pt;"><?= esc($t['codigo']) ?></div>
        </div>
    </div>
</div>

<p class="pista">
    Imprímela en cartulina y plastifícala. Sale a tamaño de tarjeta bancaria, así que
    en el cuadro de impresión hay que dejar la escala en <strong>100 %</strong>, no en
    «ajustar a la página»: si se escala, el QR puede dejar de leerse.
</p>

</body>
</html>
