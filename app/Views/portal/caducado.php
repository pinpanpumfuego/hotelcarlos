<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc(lang('Portal.caducadoTitulo')) ?> · <?= esc($hotel->nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>body { background: #f2f5f2; color: #1c2a23; font-family: system-ui, sans-serif; }</style>
</head>
<body class="d-flex align-items-center" style="min-height: 100vh;">
<div class="container px-3" style="max-width: 460px;">
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center p-4">
            <i class="bi bi-link-45deg fs-1 text-muted opacity-50"></i>
            <h1 class="h5 mt-2"><?= esc(lang('Portal.caducadoTitulo')) ?></h1>
            <p class="text-muted small"><?= esc(lang('Portal.caducadoTexto')) ?></p>

            <?php // No se dice cuál de las tres cosas pasó -caducado, revocado o
                  // inexistente-: decirle a quien prueba enlaces cuál existió es
                  // ayudarle. ?>
            <div class="d-grid gap-2 mt-3">
                <?php if (! empty($hotel->whatsapp)): ?>
                    <a class="btn btn-success" href="https://wa.me/<?= esc($hotel->whatsapp) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp me-1"></i><?= esc(lang('Portal.escribir')) ?>
                    </a>
                <?php endif ?>
                <?php if (! empty($hotel->email)): ?>
                    <a class="btn btn-outline-secondary" href="mailto:<?= esc($hotel->email) ?>">
                        <i class="bi bi-envelope me-1"></i><?= esc($hotel->email) ?>
                    </a>
                <?php endif ?>
            </div>
        </div>
    </div>
    <p class="text-center text-muted small mt-3 mb-0"><?= esc($hotel->nombre) ?></p>
</div>
</body>
</html>
