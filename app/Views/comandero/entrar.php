<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#12281d">
    <meta name="robots" content="noindex, nofollow">
    <title>Comandero · <?= esc($hotel->nombre) ?></title>
    <link rel="manifest" href="<?= site_url('comandero/manifest.webmanifest') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('icono-192.png') ?>">
    <link rel="stylesheet" href="<?= base_url('vendor/bootstrap-icons/bootstrap-icons.css') ?>">
    <style>
        :root { --fondo:#12281d; --tarjeta:#1c3a2a; --borde:#2e5741; --verde:#4f8a68;
                --tinta:#eef5f0; --suave:#9fbcac; --rojo:#c0563f; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin:0; min-height:100dvh; background:var(--fondo); color:var(--tinta);
               font-family: system-ui, -apple-system, sans-serif;
               display:flex; flex-direction:column; justify-content:center;
               padding: 24px 20px calc(24px + env(safe-area-inset-bottom)); }
        .marca { text-align:center; margin-bottom:26px; }
        .marca .hotel { color:var(--suave); font-size:.85rem; }
        .marca h1 { margin:6px 0 0; font-size:1.6rem; }
        .marca p { color:var(--suave); font-size:.9rem; margin:8px 0 0; }
        form { background:var(--tarjeta); border:1px solid var(--borde); border-radius:18px; padding:20px; }
        label { display:block; font-size:.85rem; color:var(--suave); margin-bottom:6px; }
        input { width:100%; font:inherit; font-size:1.15rem; padding:14px; border-radius:12px;
                border:1px solid var(--borde); background:#0d1f16; color:var(--tinta); margin-bottom:16px; }
        input:focus { outline:none; border-color:var(--verde); }
        /* 56 px: se pulsa con el pulgar y con el móvil en una mano */
        button { width:100%; font:inherit; font-size:1.05rem; font-weight:600; min-height:56px;
                 border:0; border-radius:14px; background:var(--verde); color:#fff; }
        button:active { transform: scale(.98); }
        .pista { font-size:.8rem; color:var(--suave); margin:-10px 0 16px; }
        .aviso { border-radius:12px; padding:12px 14px; font-size:.9rem; margin-bottom:16px; }
        .aviso.error { background:rgba(192,86,63,.18); border:1px solid var(--rojo); }
        .aviso.ok { background:rgba(79,138,104,.18); border:1px solid var(--verde); }
        .pie { text-align:center; color:var(--suave); font-size:.8rem; margin-top:22px; }
        .pie a { color:var(--suave); }
    </style>
</head>
<body>

<div class="marca">
    <div class="hotel"><?= esc($hotel->nombre) ?></div>
    <h1>Comandero</h1>
    <p>Toma las comandas desde tu teléfono</p>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="aviso error"><i class="bi bi-exclamation-triangle"></i> <?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('ok')): ?>
    <div class="aviso ok"><?= esc(session()->getFlashdata('ok')) ?></div>
<?php endif ?>

<form method="post" action="<?= site_url('comandero/entrar') ?>">
    <?= csrf_field() ?>

    <label for="documento">Número de documento</label>
    <input type="text" id="documento" name="documento" inputmode="numeric" autocomplete="username"
           required maxlength="50" placeholder="1023456789">

    <label for="pin">PIN</label>
    <input type="password" id="pin" name="pin" inputmode="numeric" autocomplete="current-password"
           required maxlength="<?= \App\Models\EmpleadoModel::LONGITUD_PIN ?>"
           pattern="\d{<?= \App\Models\EmpleadoModel::LONGITUD_PIN ?>}" placeholder="••••">
    <div class="pista">El mismo del terminal de fichaje.</div>

    <button><i class="bi bi-box-arrow-in-right"></i> Entrar</button>
</form>

<p class="pie">
    ¿Sin PIN? Pídeselo a gerencia.<br>
    <a href="<?= site_url('empleado') ?>">Ir a Mi jornada</a>
</p>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(<?= json_encode(base_url('sw.js')) ?>)
                .catch(function (e) { console.warn('Service worker no registrado', e); });
        });
    }
</script>

</body>
</html>
