<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Enlace no válido · <?= esc($hotel->nombre) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #f7f4ee; color: #22302a; font-family: 'Inter', system-ui, sans-serif; padding: 20px;
        }
        .caja {
            background: #fff; border: 1px solid #ddd7cc; border-radius: 16px;
            padding: 34px 26px; max-width: 420px; text-align: center;
            box-shadow: 0 2px 12px rgba(34,48,42,.07);
        }
        .icono { font-size: 3rem; color: #b9873f; }
        h1 { font-family: 'Fraunces', serif; font-size: 1.35rem; margin: 12px 0 8px; }
        p { color: #6d7a72; line-height: 1.55; margin: 0 0 8px; }
        a { color: #1f4d36; font-weight: 600; }
    </style>
</head>
<body>
    <div class="caja">
        <div class="icono"><i class="bi bi-hourglass-bottom"></i></div>
        <h1>Este enlace ya no está disponible</h1>
        <p>
            Puede que haya caducado o que la dirección no sea correcta.
            Los enlaces de registro dejan de funcionar después de la fecha de salida, por seguridad.
        </p>
        <p style="margin-top:16px">
            Escríbenos y te enviamos uno nuevo:<br>
            <a href="https://wa.me/<?= esc($hotel->whatsapp) ?>"><?= esc($hotel->telefono) ?></a>
        </p>
    </div>
</body>
</html>
