<?php
/**
 * Acuse para quien acaba de reservar por la web.
 *
 * **No dice «confirmada», y eso no es un matiz.** La reserva entra pendiente de
 * que alguien del hotel la acepte. Un correo que diga «confirmada» estaría
 * prometiendo una cabaña que quizá no está libre, y quien lo lea hará planes
 * sobre esa promesa.
 */
?>
<p style="margin:0 0 16px;">
    Hola <?= esc($reserva['nombre'] ?? '') ?>, hemos recibido tu solicitud de reserva.
    <strong>Todavía no está confirmada:</strong> la revisamos y te escribimos para confirmártela.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:#faf7f2; border:1px solid #ece7dd; border-radius:11px; padding:16px; margin-bottom:22px;">
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Tu código</td>
        <td style="padding:5px 0; text-align:right; font-weight:700; letter-spacing:.04em;">
            <?= esc($reserva['codigo']) ?>
        </td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Fechas</td>
        <td style="padding:5px 0; text-align:right; font-weight:600;">
            <?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?>
            →
            <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?>
        </td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Alojamiento</td>
        <td style="padding:5px 0; text-align:right; font-weight:600;">
            <?= esc($reserva['unidad_nombre'] ?? '') ?>
        </td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Huéspedes</td>
        <td style="padding:5px 0; text-align:right;">
            <?= (int) ($reserva['adultos'] ?? 1) ?> adulto<?= (int) ($reserva['adultos'] ?? 1) === 1 ? '' : 's' ?>
            <?php if ((int) ($reserva['ninos'] ?? 0) > 0): ?>
                · <?= (int) $reserva['ninos'] ?> niño<?= (int) $reserva['ninos'] === 1 ? '' : 's' ?>
            <?php endif ?>
        </td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Total</td>
        <td style="padding:5px 0; text-align:right; font-weight:700; color:#1f4d36;">
            $<?= number_format((float) $reserva['total'], 0, ',', '.') ?> COP
        </td>
    </tr>
</table>

<p style="margin:0 0 16px;">
    Guarda ese código: es con lo que te identificamos si necesitas escribirnos o cambiar algo.
</p>

<p style="margin:0 0 16px; color:#6d7a72; font-size:14px;">
    Si algo de lo de arriba no es lo que pediste, contéstanos a este correo antes de que la
    confirmemos y lo arreglamos sin problema.
</p>

<?php if (! empty($hotel->telefono)): ?>
    <p style="margin:0; color:#6d7a72; font-size:14px;">
        También puedes escribirnos al <?= esc($hotel->telefono) ?>.
    </p>
<?php endif ?>
