<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ── Web pública (sin autenticación) ─────────────────────────────────
$routes->get('/', 'Web::inicio');
$routes->get('alojamientos', 'Web::alojamientos');
$routes->get('contacto', 'Web::contacto');

// Motor de reservas online
$routes->get('reservar', 'Reservar::index');
$routes->post('reservar/disponibilidad', 'Reservar::disponibilidad');
$routes->post('reservar/datos', 'Reservar::datos');
$routes->post('reservar/confirmar', 'Reservar::confirmar');
$routes->get('reservar/exito/(:segment)', 'Reservar::exito/$1');

// ── Acceso ──────────────────────────────────────────────────────────
$routes->get('login', 'Login::index');
$routes->post('login/entrar', 'Login::entrar');
$routes->post('logout', 'Login::salir');

// ── Panel: requiere sesión ──────────────────────────────────────────
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('panel', 'Dashboard::index');

    // Unidades: ver y actualizar estado, todo el personal (limpieza incluida)
    $routes->get('unidades', 'Unidades::index');
    $routes->get('unidades/editar/(:num)', 'Unidades::editar/$1');
    $routes->post('unidades/actualizar/(:num)', 'Unidades::actualizar/$1');
});

// ── Venta y recepción: gerencia + recepción ─────────────────────────
$routes->group('', ['filter' => ['auth', 'rol:gerencia,recepcion']], static function ($routes) {
    $routes->get('calendario', 'Calendario::index');

    $routes->get('huespedes', 'Huespedes::index');
    $routes->get('huespedes/nuevo', 'Huespedes::nuevo');
    $routes->post('huespedes/guardar', 'Huespedes::guardar');
    $routes->get('huespedes/editar/(:num)', 'Huespedes::editar/$1');
    $routes->post('huespedes/actualizar/(:num)', 'Huespedes::actualizar/$1');
    $routes->post('huespedes/eliminar/(:num)', 'Huespedes::eliminar/$1');

    $routes->get('reservas', 'Reservas::index');
    $routes->get('reservas/nueva', 'Reservas::nueva');
    $routes->post('reservas/guardar', 'Reservas::guardar');
    $routes->get('reservas/editar/(:num)', 'Reservas::editar/$1');
    $routes->post('reservas/actualizar/(:num)', 'Reservas::actualizar/$1');
    $routes->post('reservas/checkin/(:num)', 'Reservas::checkin/$1');
    $routes->post('reservas/checkout/(:num)', 'Reservas::checkout/$1');
    $routes->post('reservas/cancelar/(:num)', 'Reservas::cancelar/$1');

    $routes->get('unidades/nueva', 'Unidades::nueva');
    $routes->post('unidades/guardar', 'Unidades::guardar');
});

// ── Configuración: solo gerencia ────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'rol:gerencia']], static function ($routes) {
    $routes->post('unidades/eliminar/(:num)', 'Unidades::eliminar/$1');

    $routes->get('tipos', 'Tipos::index');
    $routes->get('tipos/nuevo', 'Tipos::nuevo');
    $routes->post('tipos/guardar', 'Tipos::guardar');
    $routes->get('tipos/editar/(:num)', 'Tipos::editar/$1');
    $routes->post('tipos/actualizar/(:num)', 'Tipos::actualizar/$1');
    $routes->post('tipos/eliminar/(:num)', 'Tipos::eliminar/$1');

    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('usuarios/nuevo', 'Usuarios::nuevo');
    $routes->post('usuarios/guardar', 'Usuarios::guardar');
    $routes->get('usuarios/editar/(:num)', 'Usuarios::editar/$1');
    $routes->post('usuarios/actualizar/(:num)', 'Usuarios::actualizar/$1');
    $routes->post('usuarios/eliminar/(:num)', 'Usuarios::eliminar/$1');
});
