<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1f4d36">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Mi jornada">
    <link rel="manifest" href="<?= site_url('empleado/manifest.webmanifest') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('icono-192.png') ?>">
    <title><?= esc($titulo ?? 'Mi jornada') ?> · <?= esc($hotel->nombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bosque: #1f4d36; --bosque-claro: #2a5f44; --arena: #9d6220;
            --fondo: #f2f5f2; --panel: #fff; --borde: #e2e8e3;
            --tinta: #1c2a23; --tinta-media: #4a5a51; --tinta-suave: #7b8a81;
            --radio: 16px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0; min-height: 100vh; background: var(--fondo); color: var(--tinta);
            font-family: 'Inter', system-ui, -apple-system, sans-serif; font-size: 15px;
            padding-bottom: env(safe-area-inset-bottom);
        }
        h1, h2 { font-family: 'Fraunces', Georgia, serif; font-weight: 600; letter-spacing: -.01em; }

        .cabecera {
            background: linear-gradient(165deg, #143425, var(--bosque) 70%);
            color: #fff; padding: calc(18px + env(safe-area-inset-top)) 18px 22px;
            border-radius: 0 0 22px 22px;
        }
        .cabecera .saludo { font-size: .8rem; color: #9dbcaa; }
        .cabecera h1 { font-size: 1.4rem; margin: 2px 0 0; color: #fff; }
        .cabecera .barra { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
        .cabecera a.salir { color: #9dbcaa; font-size: 1.2rem; text-decoration: none; }

        main { padding: 16px; max-width: 560px; margin: 0 auto; }

        .tarjeta {
            background: var(--panel); border: 1px solid var(--borde); border-radius: var(--radio);
            padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 3px rgba(28,42,35,.05);
        }
        .tarjeta h2 { font-size: 1rem; margin: 0 0 10px; }

        .btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; font: inherit; font-weight: 600; font-size: 1.05rem;
            padding: 16px; border-radius: 14px; border: 0; cursor: pointer;
            color: #fff; background: var(--bosque); text-decoration: none;
            transition: transform .08s ease, opacity .15s ease;
        }
        .btn:active { transform: scale(.98); }
        .btn:disabled { opacity: .6; }
        .btn.salida { background: var(--arena); }
        .btn.pausa_inicio { background: #5b7c99; }
        .btn.pausa_fin { background: var(--bosque-claro); }
        .btn.suave { background: #e6ece7; color: var(--tinta); }
        .btn + .btn { margin-top: 10px; }

        .estado-grande { text-align: center; padding: 6px 0 14px; }
        .estado-grande .etiqueta {
            display: inline-flex; align-items: center; gap: 6px; font-size: .82rem; font-weight: 600;
            padding: .3rem .85rem; border-radius: 99px;
        }
        .etiqueta.dentro { background: #e9f4ee; color: #1c5c3c; }
        .etiqueta.fuera { background: #f1f4f2; color: var(--tinta-suave); }
        .etiqueta.pausa { background: #fdf5e7; color: #7d5a1c; }
        .estado-grande .reloj { font-family: 'Fraunces', serif; font-size: 2.6rem; line-height: 1.1; margin-top: 8px; }
        .estado-grande .detalle { color: var(--tinta-suave); font-size: .86rem; }

        .campo { margin-bottom: 14px; }
        .campo label { display: block; font-size: .85rem; color: var(--tinta-media); margin-bottom: 5px; }
        .campo input {
            width: 100%; font: inherit; padding: 13px 14px; border-radius: 12px;
            border: 1px solid #cfd8d1; background: #fff;
        }
        .campo input:focus { outline: 0; border-color: var(--bosque-claro); box-shadow: 0 0 0 3px rgba(31,77,54,.1); }
        .campo .pista { font-size: .78rem; color: var(--tinta-suave); margin-top: 4px; }

        .aviso { padding: 12px 14px; border-radius: 12px; font-size: .9rem; margin-bottom: 14px; }
        .aviso.error { background: #fbecec; color: #8f3237; }
        .aviso.ok { background: #e9f4ee; color: #1c5c3c; }
        .aviso.info { background: #eef2ef; color: var(--tinta-media); font-size: .84rem; }

        .lista { list-style: none; margin: 0; padding: 0; }
        .lista li {
            display: flex; justify-content: space-between; align-items: center; gap: 10px;
            padding: 11px 0; border-bottom: 1px solid var(--borde);
        }
        .lista li:last-child { border-bottom: 0; }
        .lista .dia { font-weight: 600; text-transform: capitalize; }
        .lista .detalle { font-size: .8rem; color: var(--tinta-suave); }
        .lista .horas { font-family: 'Fraunces', serif; font-weight: 600; }

        .pie { text-align: center; color: var(--tinta-suave); font-size: .76rem; padding: 6px 16px 26px; }
        .pie a { color: var(--bosque-claro); }

        .instalar {
            position: fixed; left: 12px; right: 12px; bottom: calc(12px + env(safe-area-inset-bottom));
            background: var(--tinta); color: #fff; border-radius: 14px; padding: 12px 14px;
            display: none; align-items: center; gap: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.25);
            max-width: 536px; margin: 0 auto;
        }
        .instalar button { font: inherit; font-weight: 600; border: 0; border-radius: 10px;
                           padding: 8px 14px; background: #fff; color: var(--tinta); cursor: pointer; }
        .instalar .cerrar { background: transparent; color: #9dbcaa; padding: 4px 8px; }
    </style>
</head>
<body>
    <?= $this->renderSection('contenido') ?>

    <div class="instalar" id="instalar">
        <i class="bi bi-phone"></i>
        <span style="flex:1; font-size:.86rem">Instálala en tu teléfono para entrar más rápido</span>
        <button id="btn-instalar">Instalar</button>
        <button class="cerrar" id="btn-cerrar-instalar" aria-label="Cerrar">✕</button>
    </div>

<script>
    // Registra el service worker: sin él la aplicación no se puede instalar
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(<?= json_encode(base_url('sw.js')) ?>, {
                scope: <?= json_encode(site_url('empleado')) ?>
            }).catch(function (e) { console.warn('Service worker no registrado', e); });
        });
    }

    // Aviso de instalación, solo si el navegador lo ofrece y no se rechazó antes
    let peticionInstalar = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        peticionInstalar = e;
        if (localStorage.getItem('instalar-oculto') !== '1') {
            document.getElementById('instalar').style.display = 'flex';
        }
    });
    document.getElementById('btn-instalar').addEventListener('click', async function () {
        document.getElementById('instalar').style.display = 'none';
        if (peticionInstalar) { peticionInstalar.prompt(); peticionInstalar = null; }
    });
    document.getElementById('btn-cerrar-instalar').addEventListener('click', function () {
        localStorage.setItem('instalar-oculto', '1');
        document.getElementById('instalar').style.display = 'none';
    });
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
