<p style="margin:0 0 16px;">Hola <strong><?= esc($reserva['nombre']) ?></strong>,</p>

<p style="margin:0 0 20px;">
    Se acerca tu llegada a <strong><?= esc($hotel->nombre) ?></strong>
    (<?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?>, reserva <?= esc($reserva['codigo']) ?>).
    Para que el registro sea rápido, puedes completarlo ahora desde el móvil.
</p>

<p style="margin:0 0 16px; color:#4a5450;">Solo te llevará un par de minutos:</p>
<ul style="margin:0 0 22px; padding-left:20px; color:#4a5450;">
    <li style="margin-bottom:6px;">Tus datos y los de quienes te acompañan</li>
    <li style="margin-bottom:6px;">Una foto de tu documento de identidad</li>
    <li>Tu firma, dibujada con el dedo</li>
</ul>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 22px;">
    <tr>
        <td style="background:#1f4d36; border-radius:10px;">
            <a href="<?= esc($enlace) ?>"
               style="display:inline-block; padding:14px 26px; color:#ffffff; text-decoration:none; font-weight:600;">
                Completar mi registro
            </a>
        </td>
    </tr>
</table>

<p style="margin:0 0 18px; font-size:12px; color:#8b958f;">
    Si el botón no funciona, copia esta dirección en tu navegador:<br>
    <span style="word-break:break-all;"><?= esc($enlace) ?></span>
</p>

<p style="margin:0; font-size:12px; color:#8b958f;">
    Este enlace es personal y deja de funcionar después de tu salida.
    Tus datos se tratan conforme a la Ley 1581 de 2012 de protección de datos personales.
</p>
