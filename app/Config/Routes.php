<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Acceso (sin autenticación; el resto del panel la exige vía filtro global)
$routes->get('login', 'Login::index');
$routes->post('login/entrar', 'Login::entrar');
$routes->post('logout', 'Login::salir');

// Por ahora la raíz lleva al panel; más adelante será la web pública del hotel.
$routes->get('/', 'Dashboard::index');
$routes->get('panel', 'Dashboard::index');

// Calendario de disponibilidad
$routes->get('calendario', 'Calendario::index', ['filter' => 'rol:gerencia,recepcion']);

// Reservas y huéspedes: gerencia y recepción
$routes->group('', ['filter' => 'rol:gerencia,recepcion'], static function ($routes) {
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
});

// Unidades: todo el personal autenticado (limpieza necesita ver y cambiar estados)
$routes->get('unidades', 'Unidades::index');
$routes->get('unidades/nueva', 'Unidades::nueva', ['filter' => 'rol:gerencia,recepcion']);
$routes->post('unidades/guardar', 'Unidades::guardar', ['filter' => 'rol:gerencia,recepcion']);
$routes->get('unidades/editar/(:num)', 'Unidades::editar/$1');
$routes->post('unidades/actualizar/(:num)', 'Unidades::actualizar/$1');
$routes->post('unidades/eliminar/(:num)', 'Unidades::eliminar/$1', ['filter' => 'rol:gerencia']);

// Configuración: solo gerencia
$routes->group('', ['filter' => 'rol:gerencia'], static function ($routes) {
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
