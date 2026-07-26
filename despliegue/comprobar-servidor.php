<?php

/**
 * Comprobador del servidor · San Antonio de los Lagos
 *
 * SUBE SOLO ESTE ARCHIVO a la carpeta web de dinahosting y ábrelo en el
 * navegador. Te dice si el hosting cumple lo que el sistema necesita, ANTES
 * de subir nada más.
 *
 * BÓRRALO en cuanto termines: revela la configuración del servidor.
 */

$php   = PHP_VERSION;
$phpOk = version_compare($php, '8.2.0', '>=');

$extensiones = [
    'intl'      => 'Idiomas y formatos de fecha (CodeIgniter no arranca sin ella)',
    'mbstring'  => 'Textos con tildes y eñes',
    'json'      => 'Comunicación del TPV y el comandero',
    'mysqli'    => 'Base de datos',
    'curl'      => 'Siigo, Wompi y la sincronización con Booking',
    'gd'        => 'Miniaturas de las fotos',
    'fileinfo'  => 'Comprobar qué se sube de verdad en cada archivo',
    'openssl'   => 'Cifrado y correo seguro',
];

$comprobaciones = [];
foreach ($extensiones as $ext => $para) {
    $comprobaciones[] = [extension_loaded($ext), 'Extensión ' . $ext, $para];
}

$comprobaciones[] = [
    (int) ini_get('upload_max_filesize') >= 8 || preg_match('/^(\d+)M$/', ini_get('upload_max_filesize'), $m) && (int) $m[1] >= 8,
    'Subida de archivos ≥ 8 MB',
    'Ahora: ' . ini_get('upload_max_filesize') . '. Las fotos de las cabañas y del fichaje',
];
$comprobaciones[] = [
    function_exists('exec') || function_exists('shell_exec'),
    'Tareas programadas posibles',
    'Para sincronizar Booking y Airbnb cada hora. Si falla, se puede hacer por otra vía',
];
$comprobaciones[] = [
    is_writable(__DIR__),
    'Se puede escribir en el disco',
    'Necesario para las fotos, los documentos y las copias de seguridad',
];

$fallos = count(array_filter($comprobaciones, static fn ($c) => ! $c[0]));
$fallos += $phpOk ? 0 : 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Comprobación del servidor</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f2f5f2; color: #1c2a23;
               margin: 0; padding: 24px 16px; line-height: 1.5; }
        .caja { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 14px;
                padding: 26px; box-shadow: 0 2px 14px rgba(28,42,35,.08); }
        h1 { font-size: 1.3rem; margin: 0 0 4px; }
        .sub { color: #7b8a81; font-size: .9rem; margin: 0 0 22px; }
        .veredicto { border-radius: 12px; padding: 16px 18px; margin-bottom: 22px; font-weight: 600; }
        .bien { background: #e6f3ea; color: #1f4d36; }
        .mal  { background: #fbeceb; color: #96322a; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 10px 6px; border-bottom: 1px solid #eef2ef; vertical-align: top; }
        td.estado { width: 34px; font-size: 1.1rem; }
        .ok { color: #2f7d52; } .no { color: #b3454a; }
        .que { font-weight: 600; }
        .para { color: #7b8a81; font-size: .86rem; }
        .aviso { margin-top: 22px; background: #fff8e6; border: 1px solid #e0c168;
                 border-radius: 12px; padding: 14px 16px; font-size: .9rem; }
    </style>
</head>
<body>
<div class="caja">
    <h1>Comprobación del servidor</h1>
    <p class="sub">San Antonio de los Lagos Ecolodge</p>

    <div class="veredicto <?= $fallos === 0 ? 'bien' : 'mal' ?>">
        <?= $fallos === 0
            ? '✅ El servidor cumple todo. Se puede subir el sistema.'
            : '⚠️ Hay ' . $fallos . ' cosa' . ($fallos === 1 ? '' : 's') . ' que falta' . ($fallos === 1 ? '' : 'n') . '. Míralas abajo antes de subir nada.' ?>
    </div>

    <table>
        <tr>
            <td class="estado <?= $phpOk ? 'ok' : 'no' ?>"><?= $phpOk ? '✓' : '✕' ?></td>
            <td>
                <div class="que">PHP 8.2 o superior</div>
                <div class="para">
                    Este servidor tiene <strong><?= htmlspecialchars($php) ?></strong>.
                    <?= $phpOk ? '' : 'Cámbialo en el panel de dinahosting: Alojamiento web → Versión de PHP.' ?>
                </div>
            </td>
        </tr>
        <?php foreach ($comprobaciones as [$ok, $que, $para]): ?>
            <tr>
                <td class="estado <?= $ok ? 'ok' : 'no' ?>"><?= $ok ? '✓' : '✕' ?></td>
                <td>
                    <div class="que"><?= htmlspecialchars($que) ?></div>
                    <div class="para"><?= htmlspecialchars($para) ?></div>
                </td>
            </tr>
        <?php endforeach ?>
    </table>

    <div class="aviso">
        <strong>Borra este archivo</strong> en cuanto termines de mirarlo.
        Enseña la configuración del servidor y eso no debe quedar público.
    </div>
</div>
</body>
</html>
