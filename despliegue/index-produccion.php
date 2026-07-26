<?php

/**
 * Arranque para alojamiento compartido, cuando NO se puede cambiar la
 * carpeta raíz del dominio.
 *
 * Va en la carpeta web (`www` o `public_html`) sustituyendo al `index.php`
 * normal. La aplicación vive un nivel más arriba, fuera del alcance del
 * navegador, que es lo que mantiene a salvo las cédulas de los huéspedes,
 * las firmas y las fotos del fichaje.
 *
 * Estructura que espera:
 *
 *   /home/TU_USUARIO/
 *   ├── hotelcarlos/     ← app, vendor, writable, .env   (NO se ve desde la web)
 *   └── www/             ← esta carpeta
 *       └── index.php    ← este archivo
 *
 * Si la carpeta de la aplicación se llama distinto, cambia la línea de abajo.
 */

// ── Dónde está la aplicación ─────────────────────────────────────────
$carpetaApp = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'hotelcarlos';

if (! is_dir($carpetaApp)) {
    http_response_code(500);
    exit('No encuentro la aplicación. Revisa la ruta en index.php.');
}

// A partir de aquí es el arranque normal de CodeIgniter, con las rutas
// apuntando fuera de la carpeta web.
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

require $carpetaApp . '/app/Config/Paths.php';

$paths = new Config\Paths();
// Se corrigen a mano: por defecto Paths.php se orienta con __DIR__, que aquí
// apuntaría a la carpeta web en vez de a la de la aplicación
$paths->systemDirectory   = $carpetaApp . '/vendor/codeigniter4/framework/system';
$paths->appDirectory      = $carpetaApp . '/app';
$paths->writableDirectory = $carpetaApp . '/writable';
$paths->testsDirectory    = $carpetaApp . '/tests';
$paths->viewDirectory     = $carpetaApp . '/app/Views';
// El .env vive con la aplicación, nunca en la carpeta web
$paths->envDirectory      = $carpetaApp . DIRECTORY_SEPARATOR;

require $paths->systemDirectory . '/Boot.php';

exit(CodeIgniter\Boot::bootWeb($paths));
