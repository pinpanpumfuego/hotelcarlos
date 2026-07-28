<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Tu tarjeta · <?= esc($hotel->nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: #f2f5f2; color: #1c2a23; font-family: system-ui, -apple-system, sans-serif; }
        .marca { font-size: 11px; letter-spacing: .15em; text-transform: uppercase; color: #7b8a81; }
        .saldo { font-size: 2.4rem; font-weight: 700; letter-spacing: -.02em; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center py-4" style="min-height: 100vh;">

<div class="container" style="max-width: 460px;">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="marca mb-3"><?= esc($hotel->nombre) ?></div>

            <?php if ($tarjeta === null): ?>
                <div class="text-center py-3">
                    <i class="bi bi-x-circle text-muted" style="font-size: 3rem;"></i>
                    <h1 class="h5 mt-3">Esta tarjeta no está activa</h1>
                    <p class="text-muted mb-0">
                        Si crees que es un error, pregunta en recepción.
                    </p>
                </div>
            <?php else: ?>
                <?php // Sin PIN no se enseña ni el nombre ni el saldo. Un QR se
                      // fotografía sin querer, y quien pase al lado no tiene por
                      // qué saber cuánto lleva encima esa persona. ?>
                <div class="text-center">
                    <div class="badge text-bg-<?= esc($tipo['color'] ?? 'primary') ?> mb-2">
                        <?= esc($tipo['nombre'] ?? 'Tarjeta') ?>
                    </div>
                    <div class="font-monospace fs-5 fw-semibold" style="letter-spacing: .1em;">
                        <?= esc($tarjeta['codigo']) ?>
                    </div>
                </div>

                <?php if ($tarjeta['estado'] === 'congelada'): ?>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="bi bi-snow me-1"></i>
                        Esta tarjeta está bloqueada temporalmente. Pregunta en recepción.
                    </div>
                <?php endif ?>

                <?php if ($saldo !== null): ?>
                    <hr>
                    <div class="text-center">
                        <div class="text-muted small">Tu saldo</div>
                        <div class="saldo">$<?= number_format($saldo, 0, ',', '.') ?></div>
                        <div class="text-muted small"><?= esc($tarjeta['titular']) ?></div>
                    </div>

                    <?php if (! empty($movimientos)): ?>
                        <hr>
                        <div class="small text-muted mb-2">Últimos movimientos</div>
                        <?php foreach ($movimientos as $m): ?>
                            <div class="d-flex justify-content-between border-bottom py-1 small">
                                <div>
                                    <?= esc($m['concepto']) ?>
                                    <div class="text-muted" style="font-size: .75rem;">
                                        <?= date('d/m/Y', strtotime($m['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="text-<?= $m['tipo'] === 'consumo' ? 'danger' : 'success' ?> text-nowrap">
                                    <?= $m['tipo'] === 'consumo' ? '−' : '+' ?>$<?= number_format((float) $m['valor'], 0, ',', '.') ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    <?php endif ?>
                <?php else: ?>
                    <hr>
                    <?php if (! empty($error)): ?>
                        <div class="alert alert-danger small"><?= esc($error) ?></div>
                    <?php endif ?>

                    <form method="post" action="<?= site_url('tarjeta/' . $tarjeta['codigo'] . '/consultar') ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small">Escribe tu PIN para ver el saldo</label>
                        <div class="d-flex gap-2">
                            <input type="password" name="pin" class="form-control form-control-lg text-center"
                                   inputmode="numeric" maxlength="6" autocomplete="off" required
                                   style="letter-spacing: .5em;">
                            <button class="btn btn-dark btn-lg"><i class="bi bi-arrow-right"></i></button>
                        </div>
                        <div class="form-text">
                            Sin el PIN no se muestra nada: así, si alguien fotografía tu tarjeta,
                            no sabe cuánto llevas.
                        </div>
                    </form>
                <?php endif ?>

                <?php // La recarga sí es pública: lo peor que puede pasar es que
                      // alguien le meta dinero a una tarjeta que no es suya. ?>
                <?php if ($tarjeta['estado'] === 'activa' && (new \App\Libraries\Wompi())->activo()): ?>
                    <hr>
                    <div class="small text-muted mb-2">Recargar desde aquí</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ([50000, 100000, 200000] as $v): ?>
                            <a class="btn btn-outline-secondary btn-sm flex-fill"
                               href="<?= site_url('pago/tarjeta/' . $tarjeta['codigo']) ?>?valor=<?= $v ?>">
                                $<?= number_format($v, 0, ',', '.') ?>
                            </a>
                        <?php endforeach ?>
                    </div>
                    <div class="form-text">Pago seguro con tarjeta, PSE o Nequi.</div>
                <?php endif ?>
            <?php endif ?>
        </div>
    </div>

    <p class="text-center text-muted small mt-3 mb-0">
        <?= esc($hotel->nombre) ?>
        <?php if (! empty($hotel->telefono)): ?>· <?= esc($hotel->telefono) ?><?php endif ?>
    </p>
</div>


<?= view("partes/espera") ?>
</body>
</html>
