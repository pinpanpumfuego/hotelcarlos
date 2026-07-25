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

// Tipos de alojamiento
$routes->get('tipos', 'Tipos::index');
$routes->get('tipos/nuevo', 'Tipos::nuevo');
$routes->post('tipos/guardar', 'Tipos::guardar');
$routes->get('tipos/editar/(:num)', 'Tipos::editar/$1');
$routes->post('tipos/actualizar/(:num)', 'Tipos::actualizar/$1');
$routes->post('tipos/eliminar/(:num)', 'Tipos::eliminar/$1');
