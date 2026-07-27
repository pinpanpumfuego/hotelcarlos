<?php
/**
 * Comprobante imprimible.
 *
 * No extiende el layout del portal a propósito: esto se imprime o se guarda en
 * PDF desde el navegador, y la barra de navegación de abajo sobra en el papel.
 *
 * **No es la factura electrónica.** Esa la emite Siigo con su numeración
 * oficial. Esto es el justificante de lo consumido, que es lo que el huésped
 * pide cuando va a reclamarlo a su empresa.
 */
$pesos = static fn (float $v): string => '$' . number_format($v, 0, ',', '.');

$cargos = 0.0;
foreach ($movimientos as $m) {
    if ($m['tipo'] === 'cargo') {
        $cargos += (float) $m['valor'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= esc($acceso['idioma'] ?? 'es') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc(lang('Portal.comprobante')) ?> · <?= esc($reserva['codigo']) ?></title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif; color: #1c2a23;
            max-width: 640px; margin: 0 auto; padding: 24px 18px; font-size: 14px;
        }
        h1 { font-size: 19px; margin: 0 0 2px; }
        .marca { font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: #7b8a81; }
        table { width: 100%; border-collapse: collapse; margin: 18px 0; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .06em;
             color: #7b8a81; border-bottom: 1px solid #cfd8d1; padding: 6px 0; }
        td { padding: 7px 0; border-bottom: 1px solid #eef2ef; vertical-align: top; }
        td.importe { text-align: right; white-space: nowrap; }
        .resumen td { border: 0; padding: 4px 0; }
        .total { font-size: 18px; font-weight: 600; border-top: 2px solid #1c2a23; }
        .pie { margin-top: 26px; font-size: 11px; color: #7b8a81; line-height: 1.6; }
        .imprimir { margin: 20px 0; }
        .imprimir button {
            background: #1f4d36; color: #fff; border: 0; border-radius: 8px;
            padding: 11px 22px; font-size: 14px; cursor: pointer;
        }
        @media print { .imprimir { display: none; } body { padding: 0; } }
    </style>
</head>
<body>

<div class="marca"><?= esc($hotel->nombre) ?></div>
<h1><?= esc(lang('Portal.comprobante')) ?></h1>

<table class="resumen" style="margin-top:12px;">
    <tr>
        <td><?= esc(lang('Portal.codigo')) ?></td>
        <td class="importe"><strong><?= esc($reserva['codigo']) ?></strong></td>
    </tr>
    <tr>
        <td><?= esc($reserva['huesped_nombre'] . ' ' . $reserva['huesped_apellidos']) ?></td>
        <td class="importe"><?= esc($reserva['unidad_nombre'] ?? '') ?></td>
    </tr>
    <tr>
        <td><?= esc(lang('Portal.entrada')) ?> · <?= esc(lang('Portal.salida')) ?></td>
        <td class="importe">
            <?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?> —
            <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?>
        </td>
    </tr>
</table>

<?php if ($movimientos === []): ?>
    <p><?= esc(lang('Portal.cuentaVacia')) ?></p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th><?= esc(lang('Portal.concepto')) ?></th>
                <th style="text-align:right;"><?= esc(lang('Portal.importe')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($movimientos as $m): ?>
                <?php $resta = in_array($m['tipo'], ['pago', 'descuento'], true); ?>
                <tr>
                    <td>
                        <?= esc($m['concepto']) ?><br>
                        <span style="font-size:11px; color:#7b8a81;">
                            <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?>
                        </span>
                    </td>
                    <td class="importe"><?= $resta ? '−' : '' ?><?= $pesos((float) $m['valor']) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <table class="resumen">
        <tr>
            <td><?= esc(lang('Portal.totalCargos')) ?></td>
            <td class="importe"><?= $pesos($cargos) ?></td>
        </tr>
        <?php if ((float) $descuentos > 0): ?>
            <tr>
                <td><?= esc(lang('Portal.descuentos')) ?></td>
                <td class="importe">−<?= $pesos((float) $descuentos) ?></td>
            </tr>
        <?php endif ?>
        <tr>
            <td><?= esc(lang('Portal.totalPagado')) ?></td>
            <td class="importe">−<?= $pesos((float) $pagado) ?></td>
        </tr>
        <tr class="total">
            <td><?= esc(lang('Portal.saldo')) ?></td>
            <td class="importe"><?= $pesos(max(0, (float) $saldo)) ?></td>
        </tr>
    </table>
<?php endif ?>

<div class="imprimir">
    <button onclick="window.print()"><?= esc(lang('Portal.comprobante')) ?></button>
</div>

<div class="pie">
    <?= esc($hotel->nombre) ?><?= ! empty($hotel->direccion) ? ' · ' . esc($hotel->direccion) : '' ?><br>
    <?= esc($hotel->telefono) ?> · <?= esc($hotel->email) ?><br>
    <?= esc($emitido) ?>
</div>

</body>
</html>
