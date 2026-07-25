<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($asunto) ?></title>
</head>
<!--
    Los correos usan estilos en línea y tablas a propósito:
    es la única forma de que se vean bien en Gmail, Outlook y el móvil.
-->
<body style="margin:0; padding:0; background:#f0ede6; font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#22302a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0ede6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px; background:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #ddd7cc;">

                    <!-- Cabecera -->
                    <tr>
                        <td style="background:#1f4d36; padding:28px 24px; text-align:center;">
                            <div style="color:#c9dbd0; font-size:11px; letter-spacing:2px; text-transform:uppercase;">Ecolodge · Colombia</div>
                            <div style="color:#ffffff; font-size:21px; font-weight:700; margin-top:6px;"><?= esc($hotel->nombre) ?></div>
                        </td>
                    </tr>

                    <!-- Contenido -->
                    <tr>
                        <td style="padding:28px 24px; font-size:15px; line-height:1.6;">
                            <?= $contenido ?>
                        </td>
                    </tr>

                    <!-- Pie -->
                    <tr>
                        <td style="background:#faf7f2; padding:20px 24px; text-align:center; font-size:12px; color:#6d7a72; border-top:1px solid #ece7dd;">
                            <strong style="color:#22302a;"><?= esc($hotel->nombre) ?></strong><br>
                            <?= esc($hotel->direccion) ?><br>
                            <?= esc($hotel->telefono) ?> · <?= esc($hotel->email) ?><br>
                            <a href="<?= esc($hotel->dominio) ?>" style="color:#1f4d36;"><?= esc($hotel->dominio) ?></a>
                        </td>
                    </tr>
                </table>

                <div style="max-width:560px; margin-top:14px; font-size:11px; color:#8b958f; text-align:center;">
                    Recibes este mensaje porque tienes una reserva o solicitud con nosotros.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
