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

    // Perfil propio
    $routes->get('perfil', 'Perfil::index');
    $routes->post('perfil/clave', 'Perfil::cambiarClave');

    // Mantenimiento: todo el personal puede reportar y resolver
    $routes->get('mantenimiento', 'Mantenimiento::index');
    $routes->post('mantenimiento/guardar', 'Mantenimiento::guardar');
    $routes->post('mantenimiento/iniciar/(:num)', 'Mantenimiento::iniciar/$1');
    $routes->post('mantenimiento/resolver/(:num)', 'Mantenimiento::resolver/$1');

    // Tablero de limpieza: todo el personal
    $routes->get('limpieza', 'Limpieza::index');
    $routes->post('limpieza/iniciar/(:num)', 'Limpieza::iniciar/$1');
    $routes->post('limpieza/finalizar/(:num)', 'Limpieza::finalizar/$1');
    $routes->post('limpieza/reportar/(:num)', 'Limpieza::reportar/$1');

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
    $routes->get('reservas/ver/(:num)', 'Reservas::ver/$1');
    $routes->post('reservas/confirmar/(:num)', 'Reservas::confirmar/$1');
    $routes->post('reservas/folio/cargo/(:num)', 'Reservas::cargoFolio/$1');
    $routes->post('reservas/folio/pago/(:num)', 'Reservas::pagoFolio/$1');
    $routes->get('reservas/nueva', 'Reservas::nueva');
    $routes->post('reservas/guardar', 'Reservas::guardar');
    $routes->get('reservas/editar/(:num)', 'Reservas::editar/$1');
    $routes->post('reservas/actualizar/(:num)', 'Reservas::actualizar/$1');
    $routes->post('reservas/checkin/(:num)', 'Reservas::checkin/$1');
    $routes->post('reservas/checkout/(:num)', 'Reservas::checkout/$1');
    $routes->post('reservas/cancelar/(:num)', 'Reservas::cancelar/$1');

    $routes->get('unidades/nueva', 'Unidades::nueva');
    $routes->post('unidades/guardar', 'Unidades::guardar');

    $routes->get('caja', 'Caja::index');
    $routes->post('caja/abrir', 'Caja::abrir');
    $routes->post('caja/movimiento', 'Caja::movimiento');
    $routes->post('caja/cerrar', 'Caja::cerrar');
});

// ── Configuración: solo gerencia ────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'rol:gerencia']], static function ($routes) {
    $routes->get('reportes', 'Reportes::index');
    $routes->get('reportes/csv', 'Reportes::csv');

    $routes->get('administracion', 'Administracion::index');
    $routes->post('administracion/hotel', 'Administracion::guardarHotel');
    $routes->post('administracion/correo', 'Administracion::guardarCorreo');
    $routes->post('administracion/correo/probar', 'Administracion::probarCorreo');
    $routes->post('administracion/wompi', 'Administracion::guardarWompi');

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
