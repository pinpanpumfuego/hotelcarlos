<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php // Que no lo indexe nadie: esto no es la portada del hotel. ?>
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($datos['titulo']) ?> · <?= esc($hotel->nombre) ?></title>
    <style>
        :root {
            --bosque: #1f4d36;
            --tinta:  #f1f5f1;
            --suave:  #a9c0b1;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: linear-gradient(165deg, #12301f 0%, var(--bosque) 55%, #2a5f44 100%);
            color: var(--tinta);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            line-height: 1.6;
            text-align: center;
        }

        .caja { max-width: 30rem; width: 100%; }

        .marca {
            font-size: .72rem;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--suave);
            margin-bottom: 1.6rem;
        }

        h1 {
            font-family: Georgia, "Times New Roman", serif;
            font-weight: 600;
            font-size: clamp(1.8rem, 6vw, 2.6rem);
            line-height: 1.15;
            margin: 0 0 1rem;
            text-wrap: balance;
        }

        p { color: var(--suave); margin: 0 0 1rem; }

        .fecha {
            display: inline-block;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 99px;
            padding: .35rem .95rem;
            font-size: .85rem;
            color: var(--tinta);
            margin-top: .4rem;
        }

        .contacto { font-size: .9rem; margin-top: 2rem; }
        .contacto a { color: var(--tinta); }

        details {
            margin-top: 3rem;
            border-top: 1px solid rgba(255, 255, 255, .14);
            padding-top: 1.2rem;
        }

        summary {
            cursor: pointer;
            font-size: .8rem;
            color: var(--suave);
            list-style: none;
        }

        summary::-webkit-details-marker { display: none; }

        form { display: flex; gap: .5rem; margin-top: 1rem; }

        input {
            flex: 1;
            font: inherit;
            padding: .6rem .8rem;
            border-radius: .5rem;
            border: 1px solid rgba(255, 255, 255, .25);
            background: rgba(255, 255, 255, .07);
            color: var(--tinta);
            text-align: center;
            letter-spacing: .3em;
        }

        input::placeholder { color: rgba(255, 255, 255, .35); letter-spacing: normal; }

        button {
            font: inherit;
            padding: .6rem 1.1rem;
            border-radius: .5rem;
            border: 0;
            background: var(--tinta);
            color: var(--bosque);
            font-weight: 600;
            cursor: pointer;
        }

        .error {
            margin-top: .8rem;
            font-size: .85rem;
            color: #ffc9bd;
        }
    </style>
</head>
<body>

<div class="caja">
    <div class="marca"><?= esc($hotel->nombre) ?></div>

    <h1><?= esc($datos['titulo']) ?></h1>

    <?php if ($datos['texto'] !== ''): ?>
        <p><?= nl2br(esc($datos['texto'])) ?></p>
    <?php endif ?>

    <?php if ($datos['fecha'] !== ''): ?>
        <div class="fecha"><?= esc($datos['fecha']) ?></div>
    <?php endif ?>

    <?php // Aunque la web no esté, alguien puede querer preguntar algo. ?>
    <?php if (! empty($hotel->email) || ! empty($hotel->telefono)): ?>
        <p class="contacto">
            Mientras tanto puedes escribirnos
            <?php if (! empty($hotel->email)): ?>
                a <a href="mailto:<?= esc($hotel->email) ?>"><?= esc($hotel->email) ?></a>
            <?php endif ?>
            <?php if (! empty($hotel->telefono)): ?>
                o llamarnos al <?= esc($hotel->telefono) ?>
            <?php endif ?>.
        </p>
    <?php endif ?>

    <?php // Plegado: quien no sabe que hay una clave, no tiene por qué verla. ?>
    <details <?= $error !== null ? 'open' : '' ?>>
        <summary>Tengo una clave de acceso</summary>

        <form method="post" action="<?= site_url('obras/entrar') ?>">
            <?= csrf_field() ?>
            <input type="password" name="clave" inputmode="numeric" autocomplete="off"
                   placeholder="Clave" aria-label="Clave de acceso" required>
            <button>Entrar</button>
        </form>

        <?php if ($error !== null): ?>
            <div class="error"><?= esc($error) ?></div>
        <?php endif ?>
    </details>
</div>

</body>
</html>
