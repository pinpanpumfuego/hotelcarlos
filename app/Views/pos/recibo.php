<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo <?= esc($comanda['numero']) ?></title>
    <style>
        /* Formato pensado para impresora térmica de 80 mm */
        @page { size: 80mm auto; margin: 4mm; }
        body {
            font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.45;
            width: 72mm; margin: 0 auto; color: #000; background: #fff; padding: 6px 0;
        }
        .centro { text-align: center; }
        .marca { font-size: 15px; font-weight: bold; }
        .sep { border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .der { text-align: right; white-space: nowrap; }
        .grande { font-size: 15px; font-weight: bold; }
        .nota { font-style: italic; padding-left: 10px; }
        .pie { font-size: 11px; margin-top: 10px; }
        .acciones { margin: 16px 0; text-align: center; }
        .acciones button, .acciones a {
            font-family: system-ui, sans-serif; font-size: 14px; padding: 12px 18px;
            border: 1px solid #333; border-radius: 8px; background: #f2f2f2;
            text-decoration: none; color: #000; cursor: pointer; margin: 0 4px;
        }
        @media print { .acciones { display: none; } }
    </style>
</head>
<body>

<div class="centro">
    <div class="marca"><?= esc($hotel->nombre) ?></div>
    <?php if ($hotel->direccion): ?><div><?= esc($hotel->direccion) ?></div><?php endif ?>
    <?php if ($hotel->telefono): ?><div>Tel. <?= esc($hotel->telefono) ?></div><?php endif ?>
</div>

<div class="sep"></div>

<table>
    <tr><td>Comanda</td><td class="der"><?= esc($comanda['numero']) ?></td></tr>
    <tr><td>Fecha</td><td class="der"><?= date('d/m/Y H:i', strtotime($comanda['cerrada_en'] ?? $comanda['created_at'])) ?></td></tr>
    <tr><td><?= $comanda['reserva_id'] ? 'Cabaña' : 'Mesa' ?></td>
        <td class="der"><?= esc($comanda['reserva_id'] ? $comanda['unidad_nombre'] : ($comanda['mesa'] ?? 'Para llevar')) ?></td></tr>
    <?php if ($comanda['reserva_id']): ?>
        <tr><td>Huésped</td><td class="der"><?= esc($comanda['h_nombre'] . ' ' . $comanda['h_apellidos']) ?></td></tr>
    <?php endif ?>
    <tr><td>Atendió</td><td class="der"><?= esc($cajero) ?></td></tr>
</table>

<div class="sep"></div>

<table>
    <?php foreach ($comanda['lineas'] as $l): ?>
        <tr>
            <td><?= esc($l['cantidad']) ?> × <?= esc($l['nombre_producto']) ?></td>
            <td class="der">$<?= number_format($l['subtotal'], 0, ',', '.') ?></td>
        </tr>
        <?php if ($l['notas']): ?>
            <tr><td colspan="2" class="nota">· <?= esc($l['notas']) ?></td></tr>
        <?php endif ?>
    <?php endforeach ?>
</table>

<div class="sep"></div>

<table>
    <tr><td>Subtotal</td><td class="der">$<?= number_format($comanda['total'], 0, ',', '.') ?></td></tr>
    <?php if ($comanda['descuento'] > 0): ?>
        <tr><td>Descuento</td><td class="der">−$<?= number_format($comanda['descuento'], 0, ',', '.') ?></td></tr>
    <?php endif ?>
    <?php if ($comanda['propina'] > 0): ?>
        <tr><td>Propina</td><td class="der">$<?= number_format($comanda['propina'], 0, ',', '.') ?></td></tr>
    <?php endif ?>
    <tr class="grande"><td>TOTAL</td><td class="der">$<?= number_format($comanda['a_pagar'], 0, ',', '.') ?></td></tr>
</table>

<?php if (! empty($comanda['pagos'])): ?>
    <div class="sep"></div>
    <table>
        <?php foreach ($comanda['pagos'] as $p): ?>
            <tr>
                <td><?= esc($formas[$p['forma_pago']] ?? $p['forma_pago']) ?></td>
                <td class="der">$<?= number_format($p['valor'], 0, ',', '.') ?></td>
            </tr>
            <?php if ($p['cambio'] !== null && $p['cambio'] > 0): ?>
                <tr>
                    <td>Recibido $<?= number_format($p['recibido'], 0, ',', '.') ?> · Cambio</td>
                    <td class="der">$<?= number_format($p['cambio'], 0, ',', '.') ?></td>
                </tr>
            <?php endif ?>
        <?php endforeach ?>
        <?php if ($comanda['pendiente'] > 0): ?>
            <tr class="grande"><td>PENDIENTE</td><td class="der">$<?= number_format($comanda['pendiente'], 0, ',', '.') ?></td></tr>
        <?php endif ?>
    </table>
<?php endif ?>

<div class="sep"></div>
<div class="centro pie">
    ¡Gracias por su visita!<br>
    <?= esc($hotel->dominio) ?><br>
    <span style="font-size:10px">Documento no válido como factura electrónica</span>
</div>

<div class="acciones">
    <button onclick="window.print()">Imprimir</button>
    <a href="<?= site_url('pos') ?>">Volver al TPV</a>
</div>

<script>
    // Al abrirse desde el TPV, lanza el diálogo de impresión directamente
    if (new URLSearchParams(location.search).get('auto') === '1') {
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    }
</script>
</body>
</html>
