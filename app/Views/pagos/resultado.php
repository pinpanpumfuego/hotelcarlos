<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $exito ? 'Pago recibido' : 'Estado del pago' ?> · <?= esc($hotel->nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f2f5f2; color: #1c2a23; font-family: system-ui, -apple-system, sans-serif; }
        .marca { font-size: 11px; letter-spacing: .15em; text-transform: uppercase; color: #7b8a81; }
    </style>
</head>
<body class="d-flex align-items-center" style="min-height: 100vh;">
<div class="container px-3" style="max-width: 480px;">
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center p-4">
            <?php
            $estados = [
                'APPROVED' => ['bi-check-circle-fill', 'text-success', 'Pago recibido'],
                'PENDING'  => ['bi-hourglass-split', 'text-warning', 'Pago en proceso'],
                'DECLINED' => ['bi-x-circle-fill', 'text-danger', 'Pago rechazado'],
                'VOIDED'   => ['bi-slash-circle-fill', 'text-secondary', 'Pago anulado'],
                'ERROR'    => ['bi-exclamation-triangle-fill', 'text-danger', 'No se pudo completar'],
            ];
            [$icono, $color, $titulo] = $estados[$estado] ?? $estados['ERROR'];
            ?>

            <div class="marca"><?= esc($hotel->nombre) ?></div>
            <i class="bi <?= $icono ?> <?= $color ?> fs-1 d-block mt-3"></i>
            <h1 class="h4 mt-2"><?= esc($titulo) ?></h1>

            <?php if (! empty($mensaje)): ?>
                <p class="text-muted"><?= esc($mensaje) ?></p>
            <?php elseif ($pago !== null): ?>
                <p class="fs-3 fw-semibold mb-1">
                    $<?= number_format((float) $pago['valor'], 0, ',', '.') ?>
                </p>
                <p class="text-muted small">
                    Referencia <?= esc($pago['referencia']) ?>
                    <?php if (! empty($pago['metodo'])): ?>
                        <span class="d-block"><?= esc($pago['metodo']) ?></span>
                    <?php endif ?>
                </p>

                <?php if ($estado === 'APPROVED'): ?>
                    <p class="text-muted">
                        Gracias. Ya está apuntado en tu cuenta y te llegará el comprobante
                        del banco por correo.
                    </p>
                <?php elseif ($estado === 'PENDING'): ?>
                    <p class="text-muted">
                        Algunos medios de pago tardan unos minutos en confirmarse.
                        <strong>No vuelvas a pagar</strong>: si se cobró, lo veremos.
                    </p>
                <?php elseif ($estado === 'DECLINED'): ?>
                    <p class="text-muted">
                        El banco no autorizó el cobro. No se te ha cobrado nada; puedes
                        intentarlo con otro medio de pago.
                    </p>
                <?php endif ?>
            <?php endif ?>

            <div class="d-grid gap-2 mt-4">
                <?php if (! empty($hotel->whatsapp)): ?>
                    <a class="btn btn-outline-success" href="https://wa.me/<?= esc($hotel->whatsapp) ?>"
                       target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp me-1"></i>Escríbenos por WhatsApp
                    </a>
                <?php endif ?>
                <a class="btn btn-outline-secondary" href="<?= site_url() ?>">Volver a la web</a>
            </div>
        </div>
    </div>

    <p class="text-center text-muted small mt-3 mb-0">
        Pago procesado por Wompi. El hotel no guarda los datos de tu tarjeta.
    </p>
</div>
</body>
</html>
