<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bono regalo <?= esc($bono['codigo']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bosque: #1f4d36; --arena: #9d6220; --tinta: #1c2a23; --tinta-suave: #7b8a81; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 28px; background: #f2f5f2; color: var(--tinta);
            font-family: 'Inter', system-ui, sans-serif;
        }
        .bono {
            max-width: 640px; margin: 0 auto; background: #fff; border-radius: 18px;
            overflow: hidden; box-shadow: 0 8px 24px rgba(28,42,35,.10);
        }
        .cabecera {
            background: linear-gradient(150deg, #143425 0%, var(--bosque) 60%, #26593f 100%);
            color: #fff; padding: 30px 34px 26px; text-align: center;
        }
        .cabecera .marca { font-family: 'Fraunces', serif; font-size: 1.35rem; font-weight: 600; }
        .cabecera .lugar {
            font-size: .68rem; letter-spacing: .16em; text-transform: uppercase;
            color: #9dbcaa; margin-top: 5px;
        }
        .cabecera .titulo {
            font-family: 'Fraunces', serif; font-size: 2rem; margin: 18px 0 0; font-weight: 600;
        }
        .cuerpo { padding: 30px 34px; }
        .importe {
            font-family: 'Fraunces', serif; font-size: 3rem; font-weight: 600;
            color: var(--bosque); text-align: center; line-height: 1;
        }
        .importe small { display: block; font-family: 'Inter', sans-serif; font-size: .78rem;
                         letter-spacing: .12em; text-transform: uppercase; color: var(--tinta-suave);
                         font-weight: 600; margin-top: 8px; }
        .codigo {
            margin: 24px auto 0; max-width: 320px; text-align: center;
            font-family: 'Inter', monospace; font-size: 1.5rem; font-weight: 700; letter-spacing: .18em;
            border: 2px dashed var(--arena); border-radius: 12px; padding: 14px 10px; color: var(--bosque);
        }
        .dedicatoria {
            margin-top: 24px; text-align: center; font-family: 'Fraunces', serif;
            font-size: 1.05rem; color: var(--tinta); font-style: italic;
        }
        .datos { margin-top: 26px; border-top: 1px solid #e2e8e3; padding-top: 18px;
                 font-size: .88rem; color: var(--tinta-suave); }
        .datos div { display: flex; justify-content: space-between; padding: 4px 0; gap: 16px; }
        .datos strong { color: var(--tinta); font-weight: 600; }
        .letra-pequena { margin-top: 20px; font-size: .74rem; color: var(--tinta-suave); line-height: 1.6; }
        .acciones { max-width: 640px; margin: 20px auto 0; text-align: center; }
        .acciones button, .acciones a {
            font: inherit; font-size: .9rem; padding: 10px 20px; border-radius: 10px;
            border: 1px solid #cfd8d1; background: #fff; color: var(--tinta); cursor: pointer;
            text-decoration: none; display: inline-block;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .bono { box-shadow: none; border-radius: 0; max-width: none; }
            .acciones { display: none; }
        }
    </style>
</head>
<body>
    <div class="bono">
        <div class="cabecera">
            <div class="marca"><?= esc($hotel->nombre) ?></div>
            <div class="lugar">Cabañas junto al lago</div>
            <h1 class="titulo">Bono regalo</h1>
        </div>

        <div class="cuerpo">
            <div class="importe">
                $<?= number_format((float) $bono['importe_inicial'], 0, ',', '.') ?>
                <small>pesos colombianos</small>
            </div>

            <div class="codigo"><?= esc($bono['codigo']) ?></div>

            <?php if (trim((string) $bono['mensaje']) !== ''): ?>
                <p class="dedicatoria">«<?= esc($bono['mensaje']) ?>»</p>
            <?php endif ?>

            <div class="datos">
                <?php if ($bono['beneficiario'] !== null): ?>
                    <div><span>Para</span><strong><?= esc($bono['beneficiario']) ?></strong></div>
                <?php endif ?>
                <div><span>De parte de</span><strong><?= esc($bono['comprador_nombre']) ?></strong></div>
                <div><span>Emitido el</span><strong><?= date('d/m/Y', strtotime($bono['created_at'])) ?></strong></div>
                <div>
                    <span>Válido hasta</span>
                    <strong><?= $bono['caduca'] !== null ? date('d/m/Y', strtotime($bono['caduca'])) : 'sin caducidad' ?></strong>
                </div>
            </div>

            <p class="letra-pequena">
                Canjeable en alojamiento y en el restaurante presentando este código. Si el importe de la
                estancia o la cuenta es menor, el saldo restante queda disponible para una próxima visita.
                No es canjeable por dinero. Sujeto a disponibilidad de cabañas.
                <?= esc($hotel->telefono) ?> · <?= esc($hotel->email) ?>
            </p>
        </div>
    </div>

    <div class="acciones">
        <button onclick="window.print()">Imprimir</button>
        <a href="<?= site_url('bonos/ver/' . $bono['id']) ?>">Volver</a>
    </div>
</body>
</html>
