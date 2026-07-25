<?php
$noches = (new DateTime($reserva['fecha_entrada']))->diff(new DateTime($reserva['fecha_salida']))->days;
?>

<p style="margin:0 0 16px;">Hola <strong><?= esc($reserva['nombre']) ?></strong>,</p>

<p style="margin:0 0 20px;">
    Tu reserva está <strong style="color:#2f7d52;">confirmada</strong>. Aquí tienes los detalles:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:#faf7f2; border:1px solid #ece7dd; border-radius:11px; padding:16px; margin-bottom:22px;">
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Código de reserva</td>
        <td style="padding:5px 0; text-align:right; font-weight:700; font-size:16px; letter-spacing:1px;"><?= esc($reserva['codigo']) ?></td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Alojamiento</td>
        <td style="padding:5px 0; text-align:right; font-weight:600;"><?= esc($reserva['unidad_nombre'] ?? '') ?></td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Llegada</td>
        <td style="padding:5px 0; text-align:right; font-weight:600;"><?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?></td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Salida</td>
        <td style="padding:5px 0; text-align:right; font-weight:600;"><?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?></td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;"><?= $noches ?> noche<?= $noches > 1 ? 's' : '' ?></td>
        <td style="padding:5px 0; text-align:right; font-weight:700; font-size:17px; color:#1f4d36;">
            $<?= number_format((float) $reserva['total'], 0, ',', '.') ?> COP
        </td>
    </tr>
</table>

<?php if ($enlaceRegistro): ?>
    <p style="margin:0 0 12px;"><strong>Agiliza tu llegada</strong></p>
    <p style="margin:0 0 16px; color:#4a5450;">
        Completa tu registro desde el móvil antes de venir: tus datos, quién te acompaña
        y una foto de tu documento. Así, al llegar, solo tendremos que entregarte la llave.
    </p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
        <tr>
            <td style="background:#b9873f; border-radius:10px;">
                <a href="<?= esc($enlaceRegistro) ?>"
                   style="display:inline-block; padding:14px 26px; color:#ffffff; text-decoration:none; font-weight:600;">
                    Completar mi registro
                </a>
            </td>
        </tr>
    </table>
    <p style="margin:0 0 22px; font-size:12px; color:#8b958f;">
        Si el botón no funciona, copia esta dirección en tu navegador:<br>
        <span style="word-break:break-all;"><?= esc($enlaceRegistro) ?></span>
    </p>
<?php endif ?>

<p style="margin:0 0 6px;">Cualquier cosa que necesites, escríbenos por WhatsApp al <?= esc($hotel->telefono) ?>.</p>
<p style="margin:0;">¡Te esperamos!</p>
