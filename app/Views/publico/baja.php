<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Preferencias de correo · <?= esc($hotel->nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f2f5f2; color: #1c2a23; font-family: system-ui, -apple-system, sans-serif; }
        .marca { font-size: 11px; letter-spacing: .15em; text-transform: uppercase; color: #7b8a81; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">

<div class="container" style="max-width: 480px;">
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center p-4 p-md-5">
            <div class="marca mb-3"><?= esc($hotel->nombre) ?></div>

            <?php if ($ok): ?>
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                <h1 class="h4 mt-3">Listo, no te escribimos más</h1>
                <p class="text-muted">
                    <?php if ($que === 'encuestas'): ?>
                        No volveremos a preguntarte qué tal estuvo.
                    <?php else: ?>
                        No volverás a recibir novedades ni ofertas nuestras.
                    <?php endif ?>
                </p>
                <?php // Decirlo aquí evita el susto de creer que se ha borrado
                      // de todo y quedarse sin la confirmación de su reserva. ?>
                <div class="alert alert-light border small text-start mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Los avisos de tus reservas —confirmaciones, cambios, tu comprobante—
                    te seguirán llegando. Eso no es publicidad, es tu reserva.
                </div>
            <?php else: ?>
                <i class="bi bi-question-circle text-muted" style="font-size: 3rem;"></i>
                <h1 class="h4 mt-3">Este enlace ya no sirve</h1>
                <p class="text-muted mb-0">
                    Puede que ya te hayas dado de baja antes. Si sigues recibiendo correos
                    que no quieres, escríbenos a
                    <a href="mailto:<?= esc($hotel->email) ?>"><?= esc($hotel->email) ?></a>
                    y lo arreglamos a mano.
                </p>
            <?php endif ?>
        </div>
    </div>
</div>

</body>
</html>
