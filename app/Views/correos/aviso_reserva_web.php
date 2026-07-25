<p style="margin:0 0 16px;">
    Ha entrado una <strong>reserva nueva desde la web</strong> y está pendiente de confirmar.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background:#faf7f2; border:1px solid #ece7dd; border-radius:11px; padding:16px; margin-bottom:22px;">
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Código</td>
        <td style="padding:5px 0; text-align:right; font-weight:700;"><?= esc($reserva['codigo']) ?></td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Huésped</td>
        <td style="padding:5px 0; text-align:right; font-weight:600;">
            <?= esc(trim(($reserva['nombre'] ?? '') . ' ' . ($reserva['apellidos'] ?? ''))) ?>
        </td>
    </tr>
    <?php if (! empty($reserva['telefono'])): ?>
        <tr>
            <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Teléfono</td>
            <td style="padding:5px 0; text-align:right;"><?= esc($reserva['telefono']) ?></td>
        </tr>
    <?php endif ?>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Fechas</td>
        <td style="padding:5px 0; text-align:right; font-weight:600;">
            <?= date('d/m/Y', strtotime($reserva['fecha_entrada'])) ?> → <?= date('d/m/Y', strtotime($reserva['fecha_salida'])) ?>
        </td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Alojamiento</td>
        <td style="padding:5px 0; text-align:right; font-weight:600;"><?= esc($reserva['unidad_nombre'] ?? '') ?></td>
    </tr>
    <tr>
        <td style="padding:5px 0; color:#6d7a72; font-size:13px;">Total</td>
        <td style="padding:5px 0; text-align:right; font-weight:700; color:#1f4d36;">
            $<?= number_format((float) $reserva['total'], 0, ',', '.') ?> COP
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px;">
    <tr>
        <td style="background:#1f4d36; border-radius:10px;">
            <a href="<?= esc(site_url('reservas/ver/' . ($reserva['id'] ?? ''))) ?>"
               style="display:inline-block; padding:13px 24px; color:#ffffff; text-decoration:none; font-weight:600;">
                Ver y confirmar la reserva
            </a>
        </td>
    </tr>
</table>

<p style="margin:0; font-size:12px; color:#8b958f;">
    Recuerda que la cabaña queda bloqueada en esas fechas desde ya, aunque la reserva
    esté pendiente. Si no se confirma, cancélala para liberar la disponibilidad.
</p>
