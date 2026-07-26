<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Comprobantes de propina · <?= esc($hotel->nombre) ?></title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 20px;
               background: #f2f5f2; color: #1c2a23; font-size: 14px; }
        .hoja {
            background: #fff; max-width: 700px; margin: 0 auto 18px; padding: 28px 32px;
            border: 1px solid #d9e2db; border-radius: 10px;
        }
        h1 { font-size: 1.15rem; margin: 0 0 2px; }
        .marca { font-size: .8rem; color: #7b8a81; margin-bottom: 18px; }
        .importe { font-size: 2rem; font-weight: 700; color: #1f4d36; margin: 14px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin: 14px 0; }
        td { padding: 6px 0; border-bottom: 1px solid #eef2ef; }
        td:last-child { text-align: right; }
        .legal { font-size: .78rem; color: #4a5a51; line-height: 1.55; margin-top: 16px;
                 border-top: 1px solid #d9e2db; padding-top: 12px; }
        .firma { margin-top: 34px; display: flex; gap: 40px; }
        .firma div { flex: 1; border-top: 1px solid #1c2a23; padding-top: 6px; font-size: .78rem; color: #7b8a81; }
        .acciones { max-width: 700px; margin: 0 auto; text-align: center; padding: 10px 0 24px; }
        .acciones button, .acciones a {
            font: inherit; padding: 10px 20px; border-radius: 10px; border: 1px solid #cfd8d1;
            background: #fff; color: #1c2a23; cursor: pointer; text-decoration: none; display: inline-block;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .hoja { border: 0; border-radius: 0; max-width: none; margin: 0;
                    page-break-after: always; }
            .hoja:last-of-type { page-break-after: auto; }
            .acciones { display: none; }
        }
    </style>
</head>
<body>

<?php foreach ($lineas as $l): ?>
    <div class="hoja">
        <h1><?= esc($hotel->nombre) ?></h1>
        <div class="marca">Comprobante de entrega de propinas</div>

        <table>
            <tr><td>Trabajador</td><td><strong><?= esc($l['nombre']) ?> <?= esc($l['apellidos']) ?></strong></td></tr>
            <tr><td>Cargo</td><td><?= esc($l['cargo']) ?></td></tr>
            <tr>
                <td>Periodo liquidado</td>
                <td><?= date('d/m/Y', strtotime($liquidacion['desde'])) ?>
                    al <?= date('d/m/Y', strtotime($liquidacion['hasta'])) ?></td>
            </tr>
            <tr><td>Mesas atendidas</td><td><?= (int) $l['comandas'] ?></td></tr>
            <?php if ((float) $l['generado'] > 0): ?>
                <tr>
                    <td>Propina generada en sus mesas</td>
                    <td>$<?= number_format((float) $l['generado'], 0, ',', '.') ?></td>
                </tr>
            <?php endif ?>
            <tr>
                <td>Total repartido en el periodo</td>
                <td>$<?= number_format((float) $liquidacion['repartido'], 0, ',', '.') ?></td>
            </tr>
        </table>

        <div class="importe">$<?= number_format((float) $l['importe'], 0, ',', '.') ?></div>
        <div class="marca">Importe que le corresponde, en pesos colombianos</div>

        <p class="legal">
            Las propinas recibidas de los clientes se destinan <strong>en su totalidad</strong> a los
            trabajadores que prestaron el servicio, conforme a la <strong>Ley 1935 de 2018</strong>.
            Este documento informa del reparto correspondiente al periodo indicado.
            La propina no constituye salario ni ingreso del establecimiento.
        </p>

        <div class="firma">
            <div>Firma del trabajador</div>
            <div>Fecha de entrega</div>
        </div>
    </div>
<?php endforeach ?>

<div class="acciones">
    <button onclick="window.print()">Imprimir</button>
    <a href="<?= site_url('propinas/ver/' . $liquidacion['id']) ?>">Volver</a>
</div>

</body>
</html>
