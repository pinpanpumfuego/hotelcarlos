<?= $this->extend('layouts/web') ?>
<?= $this->section('contenido') ?>

<div class="container seccion" style="max-width: 680px;">
    <div class="card tarjeta-tipo text-center">
        <div class="card-body p-5">
            <div class="mb-3" style="font-size: 3.2rem; color: #2f8a5b;"><i class="bi bi-check-circle-fill"></i></div>
            <h1 class="titulo-seccion mb-2">¡Reserva recibida!</h1>
            <p class="text-muted mb-4">Guarda tu código de reserva:</p>
            <div class="fs-2 fw-bold mb-4 px-4 py-2 d-inline-block rounded-3" style="background: var(--crema); border: 2px dashed var(--arena); letter-spacing: .08em;">
                <?= esc($reserva['codigo']) ?>
            </div>
            <div class="text-start mx-auto" style="max-width: 380px;">
                <p class="mb-2"><i class="bi bi-house-heart me-2 text-success"></i><?= esc($reserva['unidad_nombre']) ?></p>
                <p class="mb-2"><i class="bi bi-calendar3 me-2 text-success"></i><?= esc(date('d/m/Y', strtotime($reserva['fecha_entrada']))) ?> → <?= esc(date('d/m/Y', strtotime($reserva['fecha_salida']))) ?></p>
                <p class="mb-2"><i class="bi bi-cash-coin me-2 text-success"></i>Alojamiento: $<?= number_format((float) $reserva['total'], 0, ',', '.') ?> COP</p>
                <?php if (! empty($descuento)): ?>
                    <p class="mb-2"><i class="bi bi-ticket-perforated me-2 text-success"></i>Descuento del cupón: −$<?= number_format((float) $descuento, 0, ',', '.') ?> COP</p>
                    <p class="mb-2 fw-semibold"><i class="bi bi-check2 me-2 text-success"></i>A pagar: $<?= number_format((float) $reserva['total'] - (float) $descuento, 0, ',', '.') ?> COP</p>
                <?php endif ?>
                <?php if (! empty($actividades)): ?>
                    <hr class="my-3">
                    <p class="mb-2 fw-semibold"><i class="bi bi-compass me-2 text-success"></i>Experiencias pedidas</p>
                    <?php foreach ($actividades as $a): ?>
                        <p class="mb-1 ms-4 small">
                            <?= esc($a['experiencia']) ?> ·
                            <?= date('d/m', strtotime($a['fecha'])) ?>
                            <?= $a['hora'] !== null ? 'a las ' . substr($a['hora'], 0, 5) : '' ?>
                            · $<?= number_format((float) $a['total'], 0, ',', '.') ?>
                        </p>
                    <?php endforeach ?>
                    <p class="mb-0 ms-4 small text-muted">
                        Te confirmamos el cupo al responderte. Se pagan en el hotel.
                    </p>
                <?php endif ?>

                <?php if (! empty($pedidas['sin_sitio'])): ?>
                    <hr class="my-3">
                    <p class="mb-0 small text-warning-emphasis">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= esc(implode(' y ', $pedidas['sin_sitio'])) ?>
                        se llenó justo antes de que confirmaras. Te buscamos otro día al contactarte.
                    </p>
                <?php endif ?>

                <hr class="my-3">
                <p class="mb-0"><i class="bi bi-envelope me-2 text-success"></i>Te contactaremos en <strong><?= esc($reserva['huesped_email']) ?></strong> para confirmar y coordinar el pago.</p>
            </div>
            <hr class="my-4">
            <p class="small text-muted mb-3">¿Alguna duda sobre tu reserva? Escríbenos indicando tu código.</p>
            <a class="btn btn-reserva" href="https://wa.me/<?= esc($hotel->whatsapp) ?>?text=Hola,%20tengo%20la%20reserva%20<?= esc($reserva['codigo']) ?>" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp me-1"></i>Contactar por WhatsApp
            </a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
