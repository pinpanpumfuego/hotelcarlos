<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ── Web pública (sin autenticación) ─────────────────────────────────
$routes->get('/', 'Web::inicio');
$routes->get('alojamientos', 'Web::alojamientos');
$routes->get('restaurante', 'Web::carta');
$routes->get('contacto', 'Web::contacto');

// Motor de reservas online
$routes->get('reservar', 'Reservar::index');
$routes->post('reservar/disponibilidad', 'Reservar::disponibilidad');
$routes->post('reservar/datos', 'Reservar::datos');
$routes->post('reservar/confirmar', 'Reservar::confirmar');
$routes->get('reservar/exito/(:segment)', 'Reservar::exito/$1');

// ── Registro en línea del huésped (enlace con token, sin cuenta) ────
$routes->get('registro/(:segment)', 'Registro::index/$1');
$routes->post('registro/(:segment)/datos', 'Registro::guardarDatos/$1');
$routes->post('registro/(:segment)/acompanante', 'Registro::guardarAcompanante/$1');
$routes->post('registro/(:segment)/acompanante/eliminar/(:num)', 'Registro::eliminarAcompanante/$1/$2');
$routes->post('registro/(:segment)/documento', 'Registro::subirDocumento/$1');
$routes->post('registro/(:segment)/documento/eliminar/(:num)', 'Registro::eliminarDocumento/$1/$2');
$routes->post('registro/(:segment)/enviar', 'Registro::enviar/$1');

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

    // Revisión de los registros de llegada
    $routes->get('registros', 'Registros::index');
    $routes->get('registros/ver/(:num)', 'Registros::ver/$1');
    $routes->get('registros/documento/(:num)', 'Registros::documento/$1');
    $routes->get('registros/firma/(:num)', 'Registros::firma/$1');
    $routes->post('registros/aprobar/(:num)', 'Registros::aprobar/$1');
    $routes->post('registros/rechazar/(:num)', 'Registros::rechazar/$1');
    $routes->post('registros/reporte/(:num)', 'Registros::marcarReporte/$1');
    $routes->post('registros/enviar-enlace/(:num)', 'Registros::enviarEnlace/$1');
    $routes->post('registros/generar/(:num)', 'Registros::generar/$1');

    $routes->get('reservas', 'Reservas::index');
    $routes->get('reservas/ver/(:num)', 'Reservas::ver/$1');
    $routes->post('reservas/confirmar/(:num)', 'Reservas::confirmar/$1');
    $routes->post('reservas/folio/cargo/(:num)', 'Reservas::cargoFolio/$1');
    $routes->post('reservas/folio/cupon/(:num)', 'Reservas::cuponFolio/$1');
    $routes->post('reservas/folio/bono/(:num)', 'Reservas::bonoFolio/$1');

    // Bonos regalo: los emite y canjea también recepción
    $routes->get('bonos', 'Bonos::index');
    $routes->get('bonos/ver/(:num)', 'Bonos::ver/$1');
    $routes->get('bonos/imprimir/(:num)', 'Bonos::imprimir/$1');
    $routes->post('bonos/guardar', 'Bonos::guardar');
    $routes->post('bonos/actualizar/(:num)', 'Bonos::actualizar/$1');
    $routes->post('bonos/anular/(:num)', 'Bonos::anular/$1');
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

    // Pantallas de preparación (KDS): cocina y barra
    $routes->get('cocina', 'Cocina::index');
    $routes->get('barra', 'Cocina::barra');
    $routes->group('cocina', ['filter' => 'tokenjson'], static function ($routes) {
        $routes->get('datos/(:segment)', 'Cocina::datos/$1');
        $routes->post('listo/(:num)', 'Cocina::listo/$1');
        $routes->post('comanda/(:num)/lista/(:segment)', 'Cocina::comandaLista/$1/$2');
    });

    // TPV táctil a pantalla completa (API JSON)
    $routes->get('pos', 'Pos::index');
    $routes->get('pos/recibo/(:num)', 'Pos::recibo/$1');
    $routes->group('pos/api', ['filter' => 'tokenjson'], static function ($routes) {
        $routes->get('estado', 'Pos::estado');
        $routes->post('abrir', 'Pos::abrir');
        $routes->get('comanda/(:num)', 'Pos::comanda/$1');
        $routes->post('comanda/(:num)/anadir', 'Pos::anadir/$1');
        $routes->post('comanda/(:num)/cocina', 'Pos::enviarCocina/$1');
        $routes->post('comanda/(:num)/descuento', 'Pos::descuento/$1');
        $routes->post('comanda/(:num)/cupon', 'Pos::cupon/$1');
        $routes->post('comanda/(:num)/propina', 'Pos::propina/$1');
        $routes->post('comanda/(:num)/cliente', 'Pos::cliente/$1');
        $routes->post('comanda/(:num)/cobrar', 'Pos::cobrar/$1');
        $routes->post('comanda/(:num)/anular', 'Pos::anular/$1');
        $routes->post('comanda/(:num)/mover', 'Pos::mover/$1');
        $routes->post('linea/(:num)', 'Pos::linea/$1');
        $routes->post('linea/(:num)/servir', 'Pos::servir/$1');
    });

    // Restaurante (gestión clásica)
    $routes->get('tpv', 'Tpv::index');
    $routes->post('tpv/abrir', 'Tpv::abrir');
    $routes->get('tpv/cocina', 'Tpv::cocina');
    $routes->get('tpv/comanda/(:num)', 'Tpv::comanda/$1');
    $routes->post('tpv/comanda/(:num)/anadir', 'Tpv::anadir/$1');
    $routes->post('tpv/comanda/(:num)/cobrar', 'Tpv::cobrar/$1');
    $routes->post('tpv/comanda/(:num)/anular', 'Tpv::anular/$1');
    $routes->post('tpv/linea/(:num)/cantidad', 'Tpv::cantidad/$1');
    $routes->post('tpv/linea/(:num)/entregar', 'Tpv::entregar/$1');

    $routes->get('caja', 'Caja::index');
    $routes->post('caja/abrir', 'Caja::abrir');
    $routes->post('caja/movimiento', 'Caja::movimiento');
    $routes->post('caja/cerrar', 'Caja::cerrar');
});

// ── Configuración: solo gerencia ────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'rol:gerencia']], static function ($routes) {
    $routes->get('reportes', 'Reportes::index');
    $routes->get('reportes/csv', 'Reportes::csv');

    // Carta del restaurante
    $routes->get('carta', 'Carta::index');
    $routes->get('carta/ficha/(:num)', 'Carta::ficha/$1');
    $routes->post('carta/receta/(:num)', 'Carta::guardarReceta/$1');
    $routes->post('carta/receta/eliminar/(:num)', 'Carta::eliminarReceta/$1');

    // Escandallo: preparaciones (subrecetas)
    $routes->get('preparaciones', 'Preparaciones::index');
    $routes->post('preparaciones/crear', 'Preparaciones::crear');
    $routes->get('preparaciones/ficha/(:num)', 'Preparaciones::ficha/$1');
    $routes->post('preparaciones/actualizar/(:num)', 'Preparaciones::actualizar/$1');
    $routes->post('preparaciones/componente/(:num)', 'Preparaciones::guardarComponente/$1');
    $routes->post('preparaciones/componente/eliminar/(:num)', 'Preparaciones::eliminarComponente/$1');
    $routes->post('preparaciones/eliminar/(:num)', 'Preparaciones::eliminar/$1');

    // Escandallo: insumos
    $routes->get('insumos', 'Carta::insumos');
    $routes->post('insumos/guardar', 'Carta::guardarInsumo');
    $routes->post('insumos/actualizar/(:num)', 'Carta::actualizarInsumo/$1');
    $routes->post('insumos/eliminar/(:num)', 'Carta::eliminarInsumo/$1');

    // Modificadores
    $routes->get('modificadores', 'Carta::modificadores');
    $routes->post('modificadores/grupo/guardar', 'Carta::guardarGrupo');
    $routes->post('modificadores/grupo/actualizar/(:num)', 'Carta::actualizarGrupo/$1');
    $routes->post('modificadores/grupo/eliminar/(:num)', 'Carta::eliminarGrupo/$1');
    $routes->post('modificadores/opcion/guardar', 'Carta::guardarModificador');
    $routes->post('modificadores/opcion/eliminar/(:num)', 'Carta::eliminarModificador/$1');

    $routes->post('carta/categoria/guardar', 'Carta::guardarCategoria');
    $routes->post('carta/categoria/actualizar/(:num)', 'Carta::actualizarCategoria/$1');
    $routes->post('carta/categoria/eliminar/(:num)', 'Carta::eliminarCategoria/$1');
    $routes->post('carta/producto/guardar', 'Carta::guardarProducto');
    $routes->post('carta/producto/actualizar/(:num)', 'Carta::actualizarProducto/$1');
    $routes->post('carta/producto/disponible/(:num)', 'Carta::alternarDisponible/$1');
    $routes->post('carta/producto/eliminar/(:num)', 'Carta::eliminarProducto/$1');

    $routes->get('administracion', 'Administracion::index');
    $routes->post('administracion/hotel', 'Administracion::guardarHotel');
    $routes->post('administracion/correo', 'Administracion::guardarCorreo');
    $routes->post('administracion/correo/probar', 'Administracion::probarCorreo');
    $routes->post('administracion/wompi', 'Administracion::guardarWompi');
    $routes->post('administracion/siigo', 'Administracion::guardarSiigo');
    $routes->post('administracion/siigo/probar', 'Administracion::probarSiigo');

    // Facturación electrónica
    $routes->get('facturas', 'Facturas::index');
    $routes->get('facturas/ver/(:num)', 'Facturas::ver/$1');
    $routes->get('facturas/reserva/(:num)', 'Facturas::desdeReserva/$1');
    $routes->get('facturas/comanda/(:num)', 'Facturas::desdeComanda/$1');
    $routes->post('facturas/emitir', 'Facturas::emitir');
    $routes->post('facturas/reenviar/(:num)', 'Facturas::reenviar/$1');

    $routes->post('unidades/eliminar/(:num)', 'Unidades::eliminar/$1');

    // Cupones de descuento
    $routes->get('cupones', 'Cupones::index');
    $routes->get('cupones/ver/(:num)', 'Cupones::ver/$1');
    $routes->post('cupones/guardar', 'Cupones::guardar');
    $routes->post('cupones/actualizar/(:num)', 'Cupones::actualizar/$1');
    $routes->post('cupones/estado/(:num)', 'Cupones::alternar/$1');
    $routes->post('cupones/eliminar/(:num)', 'Cupones::eliminar/$1');

    // Motor de tarifas: temporadas, reglas, calendario y simulador
    $routes->get('tarifas', 'Tarifas::index');
    $routes->get('tarifas/calendario', 'Tarifas::calendario');
    $routes->get('tarifas/agente', 'Tarifas::agente');
    $routes->post('tarifas/agente/aplicar', 'Tarifas::aplicarAgente');
    $routes->get('tarifas/simulador', 'Tarifas::simulador');
    $routes->get('tarifas/temporada/(:num)', 'Tarifas::temporada/$1');
    $routes->post('tarifas/temporada/guardar', 'Tarifas::guardarTemporada');
    $routes->post('tarifas/temporada/actualizar/(:num)', 'Tarifas::actualizarTemporada/$1');
    $routes->post('tarifas/temporada/precios/(:num)', 'Tarifas::guardarPrecios/$1');
    $routes->post('tarifas/temporada/estado/(:num)', 'Tarifas::alternarTemporada/$1');
    $routes->post('tarifas/temporada/eliminar/(:num)', 'Tarifas::eliminarTemporada/$1');
    $routes->post('tarifas/regla/guardar', 'Tarifas::guardarRegla');
    $routes->post('tarifas/regla/actualizar/(:num)', 'Tarifas::actualizarRegla/$1');
    $routes->post('tarifas/regla/estado/(:num)', 'Tarifas::alternarRegla/$1');
    $routes->post('tarifas/regla/eliminar/(:num)', 'Tarifas::eliminarRegla/$1');

    $routes->get('tipos', 'Tipos::index');
    $routes->get('tipos/nuevo', 'Tipos::nuevo');
    $routes->post('tipos/guardar', 'Tipos::guardar');
    $routes->get('tipos/editar/(:num)', 'Tipos::editar/$1');
    $routes->post('tipos/actualizar/(:num)', 'Tipos::actualizar/$1');
    $routes->post('tipos/eliminar/(:num)', 'Tipos::eliminar/$1');

    // Personal: fichas, turnos y ausencias
    $routes->get('personal', 'Personal::index');
    $routes->get('personal/nuevo', 'Personal::nuevo');
    $routes->post('personal/guardar', 'Personal::guardar');
    $routes->get('personal/ver/(:num)', 'Personal::ver/$1');
    $routes->get('personal/editar/(:num)', 'Personal::editar/$1');
    $routes->post('personal/actualizar/(:num)', 'Personal::actualizar/$1');
    $routes->post('personal/estado/(:num)', 'Personal::alternarActivo/$1');
    $routes->post('personal/documento/(:num)', 'Personal::subirDocumento/$1');
    $routes->get('personal/documento/(:num)', 'Personal::documento/$1');
    $routes->post('personal/documento/eliminar/(:num)', 'Personal::eliminarDocumento/$1');

    $routes->get('turnos', 'Turnos::index');
    $routes->post('turnos/guardar', 'Turnos::guardar');
    $routes->post('turnos/eliminar/(:num)', 'Turnos::eliminar/$1');
    $routes->post('turnos/copiar', 'Turnos::copiarSemana');

    $routes->get('ausencias', 'Turnos::ausencias');
    $routes->post('ausencias/guardar', 'Turnos::guardarAusencia');
    $routes->post('ausencias/resolver/(:num)', 'Turnos::resolverAusencia/$1');
    $routes->post('ausencias/eliminar/(:num)', 'Turnos::eliminarAusencia/$1');

    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('usuarios/nuevo', 'Usuarios::nuevo');
    $routes->post('usuarios/guardar', 'Usuarios::guardar');
    $routes->get('usuarios/editar/(:num)', 'Usuarios::editar/$1');
    $routes->post('usuarios/actualizar/(:num)', 'Usuarios::actualizar/$1');
    $routes->post('usuarios/eliminar/(:num)', 'Usuarios::eliminar/$1');
});
