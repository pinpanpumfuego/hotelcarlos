<!DOCTYPE html>
<html lang="<?= esc($acceso['idioma'] ?? 'es') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($titulo ?? lang('Portal.tuEstancia')) ?> · <?= esc($hotel->nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bosque: #1f4d36;
            --bosque-oscuro: #143425;
            --arena: #9d6220;
            --fondo: #f2f5f2;
            --tinta: #1c2a23;
            --tinta-suave: #7b8a81;
            --borde: #e2e8e3;
        }
        body {
            background: var(--fondo); color: var(--tinta);
            font-family: 'Inter', system-ui, sans-serif; font-size: .96rem;
            /* Sitio para la barra de abajo, incluida la zona segura del iPhone */
            padding-bottom: calc(74px + env(safe-area-inset-bottom));
            -webkit-font-smoothing: antialiased;
        }
        h1, h2, .titulo { font-family: 'Fraunces', Georgia, serif; font-weight: 600; letter-spacing: -.01em; }

        .cabecera {
            background: linear-gradient(165deg, var(--bosque-oscuro), var(--bosque));
            color: #fff; padding: 1.4rem 1.1rem 1.6rem;
            border-radius: 0 0 1.4rem 1.4rem;
        }
        .cabecera .marca {
            font-size: .68rem; letter-spacing: .16em; text-transform: uppercase;
            color: #9dbcaa; font-weight: 600;
        }
        .card { border: 1px solid var(--borde); border-radius: .85rem; box-shadow: 0 1px 2px rgba(28,42,35,.05); }
        .dato { font-size: .74rem; text-transform: uppercase; letter-spacing: .07em; color: var(--tinta-suave); }

        /* Barra inferior: el pulgar llega, y no tapa contenido al hacer scroll */
        .barra-abajo {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030;
            background: rgba(255,255,255,.97); backdrop-filter: blur(10px);
            border-top: 1px solid var(--borde);
            padding-bottom: env(safe-area-inset-bottom);
        }
        .barra-abajo a {
            flex: 1; text-align: center; padding: .55rem .2rem .5rem;
            color: var(--tinta-suave); text-decoration: none; font-size: .68rem; font-weight: 500;
        }
        .barra-abajo a i { display: block; font-size: 1.22rem; margin-bottom: .1rem; }
        .barra-abajo a.activo { color: var(--bosque); font-weight: 600; }

        .btn-bosque { background: var(--bosque); border-color: var(--bosque); color: #fff; }
        .btn-bosque:hover, .btn-bosque:focus { background: var(--bosque-oscuro); border-color: var(--bosque-oscuro); color: #fff; }

        /* Las notas de la encuesta: objetivos grandes, se tocan con el pulgar */
        .nota input { position: absolute; opacity: 0; width: 0; height: 0; }
        .nota label {
            display: flex; align-items: center; justify-content: center;
            width: 100%; aspect-ratio: 1; border: 1px solid var(--borde);
            border-radius: .7rem; background: #fff; font-size: 1.5rem; cursor: pointer;
            transition: transform .12s ease, border-color .12s ease, background .12s ease;
        }
        .nota input:checked + label { border-color: var(--bosque); background: #eaf2ed; transform: scale(1.06); }
        .nota input:focus-visible + label { outline: 3px solid var(--arena); outline-offset: 2px; }

        @media (prefers-reduced-motion: reduce) {
            .nota label { transition: none; }
            .nota input:checked + label { transform: none; }
        }
    </style>
</head>
<body>

<?= $this->renderSection('cabecera') ?>

<main class="container px-3" style="max-width: 640px;">
    <?php if (session()->getFlashdata('ok')): ?>
        <div class="alert alert-success d-flex gap-2 mt-3 mb-0">
            <i class="bi bi-check-circle-fill mt-1"></i>
            <div><?= esc(session()->getFlashdata('ok')) ?></div>
        </div>
    <?php endif ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-warning d-flex gap-2 mt-3 mb-0">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div><?= esc(session()->getFlashdata('error')) ?></div>
        </div>
    <?php endif ?>

    <?= $this->renderSection('contenido') ?>
</main>

<?php
$secciones = [
    ''             => ['bi-house', lang('Portal.inicio')],
    'cuenta'       => ['bi-receipt', lang('Portal.cuenta')],
    'solicitudes'  => ['bi-hand-index', lang('Portal.pedir')],
    'info'         => ['bi-info-circle', lang('Portal.info')],
    'encuesta'     => ['bi-chat-heart', lang('Portal.encuesta')],
];
$aqui = $seccion ?? '';
?>
<nav class="barra-abajo d-flex">
    <?php foreach ($secciones as $ruta => [$icono, $texto]): ?>
        <a href="<?= site_url('estancia/' . $token . ($ruta !== '' ? '/' . $ruta : '')) ?>"
           class="<?= $aqui === $ruta ? 'activo' : '' ?>">
            <i class="bi <?= $icono ?>"></i><?= esc($texto) ?>
        </a>
    <?php endforeach ?>
</nav>

</body>
</html>
