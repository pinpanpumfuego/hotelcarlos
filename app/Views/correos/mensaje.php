<?php
/**
 * El cuerpo de un mensaje del CRM.
 *
 * El texto viene de una plantilla que edita una persona, así que se escapa
 * entero: si alguien pega HTML sin querer —o queriendo— no puede romper el
 * correo ni meter un enlace que no se ve.
 *
 * Las direcciones se convierten en enlaces después de escapar, no antes.
 */
$html = esc($cuerpo);

// Enlaces: solo http y https, y el texto visible es la propia dirección para
// que se vea a dónde lleva antes de pulsar.
$html = preg_replace(
    '~(https?://[^\s<]+)~',
    '<a href="$1" style="color:#2c6e49;">$1</a>',
    $html
);

$html = nl2br($html);
?>

<div style="font-size: 15px; line-height: 1.6; color: #333;">
    <?= $html ?>
</div>

<?php if (! empty($baja)): ?>
    <?php // Tan visible como el resto. Un enlace de baja escondido en gris
          // claro de seis píxeles cumple la letra y se salta el sentido. ?>
    <hr style="border: 0; border-top: 1px solid #e5e5e5; margin: 28px 0 14px;">
    <p style="font-size: 13px; color: #666; line-height: 1.5;">
        <?php if ($finalidad === 'encuestas'): ?>
            Recibes esto porque nos autorizaste a preguntarte qué tal estuvo.
        <?php else: ?>
            Recibes esto porque nos autorizaste a mandarte novedades.
        <?php endif ?>
        <a href="<?= esc($baja, 'attr') ?>" style="color:#666;">Si ya no quieres, cancélalo aquí</a>.
        Es un clic y no te pedimos nada a cambio.
    </p>
    <p style="font-size: 12px; color: #999;">
        Los avisos de tus reservas te seguirán llegando: eso no es publicidad.
    </p>
<?php endif ?>
