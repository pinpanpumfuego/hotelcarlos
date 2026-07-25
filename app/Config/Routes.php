<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// Por ahora la raíz lleva al panel; más adelante será la web pública del hotel.
$routes->get('/', 'Dashboard::index');
$routes->get('panel', 'Dashboard::index');

// Unidades de alojamiento
$routes->get('unidades', 'Unidades::index');
$routes->get('unidades/nueva', 'Unidades::nueva');
$routes->post('unidades/guardar', 'Unidades::guardar');
$routes->get('unidades/editar/(:num)', 'Unidades::editar/$1');
$routes->post('unidades/actualizar/(:num)', 'Unidades::actualizar/$1');
$routes->post('unidades/eliminar/(:num)', 'Unidades::eliminar/$1');

// Huéspedes
$routes->get('huespedes', 'Huespedes::index');
$routes->get('huespedes/nuevo', 'Huespedes::nuevo');
$routes->post('huespedes/guardar', 'Huespedes::guardar');
$routes->get('huespedes/editar/(:num)', 'Huespedes::editar/$1');
$routes->post('huespedes/actualizar/(:num)', 'Huespedes::actualizar/$1');
$routes->post('huespedes/eliminar/(:num)', 'Huespedes::eliminar/$1');

// Reservas
$routes->get('reservas', 'Reservas::index');
$routes->get('reservas/nueva', 'Reservas::nueva');
$routes->post('reservas/guardar', 'Reservas::guardar');
$routes->get('reservas/editar/(:num)', 'Reservas::editar/$1');
$routes->post('reservas/actualizar/(:num)', 'Reservas::actualizar/$1');
$routes->post('reservas/checkin/(:num)', 'Reservas::checkin/$1');
$routes->post('reservas/checkout/(:num)', 'Reservas::checkout/$1');
$routes->post('reservas/cancelar/(:num)', 'Reservas::cancelar/$1');

// Tipos de alojamiento
$routes->get('tipos', 'Tipos::index');
$routes->get('tipos/nuevo', 'Tipos::nuevo');
$routes->post('tipos/guardar', 'Tipos::guardar');
$routes->get('tipos/editar/(:num)', 'Tipos::editar/$1');
$routes->post('tipos/actualizar/(:num)', 'Tipos::actualizar/$1');
$routes->post('tipos/eliminar/(:num)', 'Tipos::eliminar/$1');
