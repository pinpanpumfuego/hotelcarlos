<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ── Web pública (sin autenticación) ─────────────────────────────────
//
// Cada idioma tiene su propia dirección: `/alojamientos` es la española y
// `/en/alojamientos` la inglesa. Es lo que permite que Google indexe las
// cuatro versiones por separado y que un enlace compartido lleve siempre al
// mismo idioma. El español va sin prefijo porque es el mercado principal y
// así las direcciones que ya circulan siguen funcionando.
$paginasPublicas = static function ($routes) {
    $routes->get('/', 'Web::inicio');
    $routes->get('alojamientos', 'Web::alojamientos');
    $routes->get('experiencias-actividades', 'Web::experiencias');
    $routes->get('restaurante', 'Web::carta');
    $routes->get('contacto', 'Web::contacto');
    // Preferencia de moneda del visitante (solo cómo se muestra: se cobra en COP)
    $routes->get('moneda/(:segment)', 'Web::moneda/$1');

    // Motor de reservas online
    $routes->get('reservar', 'Reservar::index');
    $routes->get('reservar/calendario', 'Reservar::calendario');
    $routes->post('reservar/disponibilidad', 'Reservar::disponibilidad');
    $routes->post('reservar/datos', 'Reservar::datos');
    $routes->post('reservar/confirmar', 'Reservar::confirmar');
    $routes->get('reservar/exito/(:segment)', 'Reservar::exito/$1');
};

// Español, en la raíz
$paginasPublicas($routes);

// Los demás idiomas, con prefijo literal.
//
// **No se usa `{locale}` a propósito.** CodeIgniter lo convierte en `[^/]+`,
// que coincide con *cualquier* segmento: con él, `/login`, `/panel` y todo el
// resto del sistema caían en la portada pública. Con `en`, `fr` y `de` escritos
// tal cual no hay ambigüedad posible.
foreach (['en', 'fr', 'de'] as $idiomaWeb) {
    $routes->group($idiomaWeb, ['filter' => 'idioma:' . $idiomaWeb], $paginasPublicas);
}

// ── Registro en línea del huésped (enlace con token, sin cuenta) ────
$routes->get('registro/(:segment)', 'Registro::index/$1');
$routes->post('registro/(:segment)/datos', 'Registro::guardarDatos/$1');
$routes->post('registro/(:segment)/acompanante', 'Registro::guardarAcompanante/$1');
$routes->post('registro/(:segment)/acompanante/eliminar/(:num)', 'Registro::eliminarAcompanante/$1/$2');
$routes->post('registro/(:segment)/documento', 'Registro::subirDocumento/$1');
$routes->post('registro/(:segment)/documento/eliminar/(:num)', 'Registro::eliminarDocumento/$1/$2');
$routes->post('registro/(:segment)/enviar', 'Registro::enviar/$1');

// ── Calendario iCal que leen Booking, Airbnb y demás ────────────────
// Sin sesión porque Booking no puede iniciarla; la dirección lleva un token
// largo y solo publica fechas ocupadas, ni nombres ni importes.
$routes->get('calendario-ical/(:segment)', 'Canales::exportar/$1');

// ── Terminal de fichaje (quiosco del hotel, sin sesión) ─────────────
// No pide usuario a propósito: el PIN identifica a la persona. Lleva freno
// de intentos y se puede apagar desde Administración.
$routes->get('fichar', 'Fichar::index');
$routes->group('fichar', ['filter' => 'tokenjson'], static function ($routes) {
    $routes->post('identificar', 'Fichar::identificar');
    $routes->post('marcar', 'Fichar::marcar');
});

// ── Portal del empleado (PWA instalable en el móvil) ────────────────
$routes->get('empleado', 'Empleado::index');
$routes->post('empleado/entrar', 'Empleado::entrar');
$routes->post('empleado/salir', 'Empleado::salir');
$routes->get('empleado/historial', 'Empleado::historial');
$routes->get('empleado/manifest.webmanifest', 'Empleado::manifiesto');
$routes->post('empleado/fichar', 'Empleado::fichar', ['filter' => 'tokenjson']);

// ── Comandero: el camarero toma nota desde su móvil (PWA) ───────────
// Sin usuario del sistema: entra con su documento y el PIN de fichaje, igual
// que en el portal del empleado. No cobra, solo toma nota.
$routes->get('comandero', 'Comandero::index');
$routes->post('comandero/entrar', 'Comandero::entrar');
$routes->post('comandero/salir', 'Comandero::salir');
$routes->get('comandero/manifest.webmanifest', 'Comandero::manifiesto');
$routes->group('comandero/api', ['filter' => ['tokenjson', 'comandero']], static function ($routes) {
    $routes->get('estado', 'Comandero::estado');
    $routes->get('pulso', 'Comandero::pulso');
    $routes->get('comanda/(:num)', 'Comandero::comanda/$1');
    $routes->post('enviar', 'Comandero::enviar');
    $routes->post('borrador', 'Comandero::borrador');
    $routes->post('servir/(:num)', 'Comandero::servir/$1');
});

// ── Acceso ──────────────────────────────────────────────────────────
$routes->get('login', 'Login::index');
$routes->post('login/entrar', 'Login::entrar');
$routes->post('logout', 'Login::salir');

// ── Panel: solo hace falta tener sesión ─────────────────────────────
//
// A partir de aquí, cada ruta declara **qué permiso exige**, no qué rol. Antes
// se cerraba por pantalla («esto es de gerencia») y eso no distingue entre ver
// una reserva y cancelarla, que es justo donde está la diferencia.
//
// Con varios permisos separados por coma basta con tener uno.
$routes->group('', ['filter' => 'auth'], static function ($routes) {
    $routes->get('panel', 'Dashboard::index');

    // Perfil propio: lo tiene cualquiera que entre, sin permiso ninguno
    $routes->get('perfil', 'Perfil::index');
    $routes->post('perfil/clave', 'Perfil::cambiarClave');
});

// ── Mantenimiento ───────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.ver']], static function ($routes) {
    $routes->get('mantenimiento', 'Mantenimiento::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.crear']], static function ($routes) {
    $routes->post('mantenimiento/guardar', 'Mantenimiento::guardar');
});
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.trabajar']], static function ($routes) {
    $routes->post('mantenimiento/iniciar/(:num)', 'Mantenimiento::iniciar/$1');
    $routes->post('mantenimiento/resolver/(:num)', 'Mantenimiento::resolver/$1');
});

// ── Limpieza ────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.ver']], static function ($routes) {
    $routes->get('limpieza', 'Limpieza::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.trabajar']], static function ($routes) {
    $routes->post('limpieza/iniciar/(:num)', 'Limpieza::iniciar/$1');
    $routes->post('limpieza/finalizar/(:num)', 'Limpieza::finalizar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.reportar']], static function ($routes) {
    $routes->post('limpieza/reportar/(:num)', 'Limpieza::reportar/$1');
});

// ── Cabañas: operar frente a configurar ─────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:unidades.estado,unidades.gestionar']], static function ($routes) {
    $routes->get('unidades', 'Unidades::index');
    $routes->get('unidades/ver/(:num)', 'Unidades::ver/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:unidades.estado']], static function ($routes) {
    $routes->get('unidades/editar/(:num)', 'Unidades::editar/$1');
    $routes->post('unidades/actualizar/(:num)', 'Unidades::actualizar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:unidades.revisar']], static function ($routes) {
    $routes->get('unidades/revisar/(:num)', 'Unidades::revisar/$1');
    $routes->post('unidades/revisar/(:num)', 'Unidades::guardarRevision/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:unidades.foto']], static function ($routes) {
    $routes->get('unidades/foto/(:num)', 'Unidades::foto/$1');
    $routes->post('unidades/foto/(:num)', 'Unidades::subirFoto/$1');
    $routes->post('unidades/video/(:num)', 'Unidades::anadirVideo/$1');
    $routes->post('unidades/foto/mover/(:num)', 'Unidades::moverFoto/$1');
    $routes->post('unidades/foto/eliminar/(:num)', 'Unidades::eliminarFoto/$1');
});
// Publicar en la web pública **no** es lo mismo que subir una evidencia de
// limpieza. Hasta ahora compartían puerta, y quien limpiaba podía publicar una
// foto en la portada del hotel.
$routes->group('', ['filter' => ['auth', 'permiso:unidades.publicar']], static function ($routes) {
    $routes->post('unidades/foto/portada/(:num)', 'Unidades::portadaFoto/$1');
    $routes->post('unidades/foto/publicar/(:num)', 'Unidades::alternarPublica/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:unidades.gestionar']], static function ($routes) {
    $routes->get('unidades/nueva', 'Unidades::nueva');
    $routes->post('unidades/guardar', 'Unidades::guardar');
    $routes->post('unidades/eliminar/(:num)', 'Unidades::eliminar/$1');
    $routes->post('unidades/servicios/(:num)', 'Unidades::guardarServicios/$1');
    $routes->post('unidades/inventario/(:num)', 'Unidades::guardarInventario/$1');
    $routes->post('unidades/inventario/copiar/(:num)', 'Unidades::copiarInventario/$1');
});

// ── Calendario ──────────────────────────────────────────────────────
// Housekeeping y mantenimiento entran con `calendario.ocupacion`: la vista les
// enseña qué cabaña se libera, sin nombre ni importe. Quién ve qué lo decide la
// plantilla; la puerta solo deja pasar.
$routes->group('', ['filter' => ['auth', 'permiso:calendario.ver,calendario.ocupacion']], static function ($routes) {
    $routes->get('calendario', 'Calendario::index');
});

// ── Experiencias ────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:experiencias.vender']], static function ($routes) {
    $routes->get('experiencias', 'Experiencias::index');
    $routes->get('experiencias/catalogo', 'Experiencias::catalogo');
    $routes->get('experiencias/ficha/(:num)', 'Experiencias::ficha/$1');
    $routes->get('experiencias/disponibilidad', 'Experiencias::disponibilidad');
    $routes->post('experiencias/reservar', 'Experiencias::reservar');
    $routes->post('experiencias/estado/(:num)', 'Experiencias::estado/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:experiencias.gestionar']], static function ($routes) {
    $routes->get('experiencias/nueva', 'Experiencias::nueva');
    $routes->post('experiencias/guardar', 'Experiencias::guardar');
    $routes->get('experiencias/editar/(:num)', 'Experiencias::editar/$1');
    $routes->post('experiencias/actualizar/(:num)', 'Experiencias::actualizar/$1');
    $routes->post('experiencias/eliminar/(:num)', 'Experiencias::eliminar/$1');
    $routes->post('experiencias/foto/(:num)', 'Experiencias::subirFoto/$1');
    $routes->post('experiencias/video/(:num)', 'Experiencias::anadirVideo/$1');
    $routes->post('experiencias/foto/portada/(:num)', 'Experiencias::portadaFoto/$1');
    $routes->post('experiencias/foto/eliminar/(:num)', 'Experiencias::eliminarFoto/$1');
});

// ── Huéspedes ───────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:huespedes.ver']], static function ($routes) {
    $routes->get('huespedes', 'Huespedes::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:huespedes.editar']], static function ($routes) {
    $routes->get('huespedes/nuevo', 'Huespedes::nuevo');
    $routes->post('huespedes/guardar', 'Huespedes::guardar');
    $routes->get('huespedes/editar/(:num)', 'Huespedes::editar/$1');
    $routes->post('huespedes/actualizar/(:num)', 'Huespedes::actualizar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:huespedes.eliminar']], static function ($routes) {
    $routes->post('huespedes/eliminar/(:num)', 'Huespedes::eliminar/$1');
});

// ── Registro en línea del huésped ───────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:registros.ver']], static function ($routes) {
    $routes->get('registros', 'Registros::index');
    $routes->get('registros/ver/(:num)', 'Registros::ver/$1');
    $routes->post('registros/enviar-enlace/(:num)', 'Registros::enviarEnlace/$1');
    $routes->post('registros/generar/(:num)', 'Registros::generar/$1');
});
// Cédulas y firmas: el permiso más delicado del sistema (Ley 1581/2012).
$routes->group('', ['filter' => ['auth', 'permiso:registros.documentos']], static function ($routes) {
    $routes->get('registros/documento/(:num)', 'Registros::documento/$1');
    $routes->get('registros/firma/(:num)', 'Registros::firma/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:registros.aprobar']], static function ($routes) {
    $routes->post('registros/aprobar/(:num)', 'Registros::aprobar/$1');
    $routes->post('registros/rechazar/(:num)', 'Registros::rechazar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:registros.reporte']], static function ($routes) {
    $routes->post('registros/reporte/(:num)', 'Registros::marcarReporte/$1');
});

// ── Reservas ────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:reservas.ver']], static function ($routes) {
    $routes->get('reservas', 'Reservas::index');
    $routes->get('reservas/ver/(:num)', 'Reservas::ver/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:reservas.crear']], static function ($routes) {
    $routes->get('reservas/nueva', 'Reservas::nueva');
    $routes->post('reservas/guardar', 'Reservas::guardar');
});
$routes->group('', ['filter' => ['auth', 'permiso:reservas.editar']], static function ($routes) {
    $routes->get('reservas/editar/(:num)', 'Reservas::editar/$1');
    $routes->post('reservas/actualizar/(:num)', 'Reservas::actualizar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:reservas.confirmar']], static function ($routes) {
    $routes->post('reservas/confirmar/(:num)', 'Reservas::confirmar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:reservas.checkin']], static function ($routes) {
    $routes->post('reservas/checkin/(:num)', 'Reservas::checkin/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:reservas.checkout']], static function ($routes) {
    $routes->post('reservas/checkout/(:num)', 'Reservas::checkout/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:reservas.cancelar']], static function ($routes) {
    $routes->post('reservas/cancelar/(:num)', 'Reservas::cancelar/$1');
});

// ── Folio de la reserva ─────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:folio.cargo']], static function ($routes) {
    $routes->post('reservas/folio/cargo/(:num)', 'Reservas::cargoFolio/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:folio.pago']], static function ($routes) {
    $routes->post('reservas/folio/pago/(:num)', 'Reservas::pagoFolio/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:folio.cupon']], static function ($routes) {
    $routes->post('reservas/folio/cupon/(:num)', 'Reservas::cuponFolio/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:folio.bono']], static function ($routes) {
    $routes->post('reservas/folio/bono/(:num)', 'Reservas::bonoFolio/$1');
});

// ── Bonos regalo ────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:folio.bono,bonos.gestionar']], static function ($routes) {
    $routes->get('bonos', 'Bonos::index');
    $routes->get('bonos/ver/(:num)', 'Bonos::ver/$1');
    $routes->get('bonos/imprimir/(:num)', 'Bonos::imprimir/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:bonos.gestionar']], static function ($routes) {
    $routes->post('bonos/guardar', 'Bonos::guardar');
    $routes->post('bonos/actualizar/(:num)', 'Bonos::actualizar/$1');
    $routes->post('bonos/anular/(:num)', 'Bonos::anular/$1');
});

// ── Pantallas de preparación (KDS) ──────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:cocina.ver']], static function ($routes) {
    $routes->get('cocina', 'Cocina::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:barra.ver']], static function ($routes) {
    $routes->get('barra', 'Cocina::barra');
});
$routes->group('cocina', ['filter' => ['auth', 'tokenjson', 'permiso:cocina.ver']], static function ($routes) {
    $routes->get('datos/(:segment)', 'Cocina::datos/$1');
});
$routes->group('cocina', ['filter' => ['auth', 'tokenjson', 'permiso:cocina.marcar']], static function ($routes) {
    $routes->post('recibir/(:num)/(:segment)', 'Cocina::recibir/$1/$2');
    $routes->post('listo/(:num)', 'Cocina::listo/$1');
    $routes->post('comanda/(:num)/lista/(:segment)', 'Cocina::comandaLista/$1/$2');
});

// ── POS táctil ──────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:pos.usar']], static function ($routes) {
    $routes->get('pos', 'Pos::index');
    $routes->get('pos/recibo/(:num)', 'Pos::recibo/$1');
});
// Identificación y estado: se usan con la pantalla bloqueada
$routes->group('pos/api', ['filter' => ['auth', 'tokenjson', 'permiso:pos.usar']], static function ($routes) {
    $routes->post('identificar', 'Pos::identificar');
    $routes->post('bloquear', 'Pos::bloquear');
    $routes->get('estado', 'Pos::estado');
});
// El resto exige además un camarero identificado cuando el TPV es compartido
$routes->group('pos/api', ['filter' => ['auth', 'tokenjson', 'tpv', 'permiso:pos.usar']], static function ($routes) {
    $routes->post('abrir', 'Pos::abrir');
    $routes->get('comanda/(:num)', 'Pos::comanda/$1');
    $routes->post('comanda/(:num)/anadir', 'Pos::anadir/$1');
    $routes->post('comanda/(:num)/cocina', 'Pos::enviarCocina/$1');
    $routes->post('comanda/(:num)/cupon', 'Pos::cupon/$1');
    $routes->post('comanda/(:num)/propina', 'Pos::propina/$1');
    $routes->post('comanda/(:num)/cliente', 'Pos::cliente/$1');
    $routes->post('linea/(:num)', 'Pos::linea/$1');
    $routes->post('linea/(:num)/servir', 'Pos::servir/$1');
});
$routes->group('pos/api', ['filter' => ['auth', 'tokenjson', 'tpv', 'permiso:pos.mover']], static function ($routes) {
    $routes->post('comanda/(:num)/mover', 'Pos::mover/$1');
});
$routes->group('pos/api', ['filter' => ['auth', 'tokenjson', 'tpv', 'permiso:pos.cobrar']], static function ($routes) {
    $routes->post('comanda/(:num)/cobrar', 'Pos::cobrar/$1');
});
$routes->group('pos/api', ['filter' => ['auth', 'tokenjson', 'tpv', 'permiso:pos.anular']], static function ($routes) {
    $routes->post('comanda/(:num)/anular', 'Pos::anular/$1');
});
$routes->group('pos/api', ['filter' => ['auth', 'tokenjson', 'tpv', 'permiso:pos.descuento']], static function ($routes) {
    $routes->post('comanda/(:num)/descuento', 'Pos::descuento/$1');
});

// ── Restaurante (gestión clásica) ───────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:pos.usar']], static function ($routes) {
    $routes->get('tpv', 'Tpv::index');
    $routes->post('tpv/abrir', 'Tpv::abrir');
    $routes->get('tpv/cocina', 'Tpv::cocina');
    $routes->get('tpv/comanda/(:num)', 'Tpv::comanda/$1');
    $routes->post('tpv/comanda/(:num)/anadir', 'Tpv::anadir/$1');
    $routes->post('tpv/linea/(:num)/cantidad', 'Tpv::cantidad/$1');
    $routes->post('tpv/linea/(:num)/entregar', 'Tpv::entregar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:pos.cobrar']], static function ($routes) {
    $routes->post('tpv/comanda/(:num)/cobrar', 'Tpv::cobrar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:pos.anular']], static function ($routes) {
    $routes->post('tpv/comanda/(:num)/anular', 'Tpv::anular/$1');
});

// ── Caja ────────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:caja.ver']], static function ($routes) {
    $routes->get('caja', 'Caja::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:caja.abrir']], static function ($routes) {
    $routes->post('caja/abrir', 'Caja::abrir');
});
$routes->group('', ['filter' => ['auth', 'permiso:caja.movimiento']], static function ($routes) {
    $routes->post('caja/movimiento', 'Caja::movimiento');
});
$routes->group('', ['filter' => ['auth', 'permiso:caja.cerrar']], static function ($routes) {
    $routes->post('caja/cerrar', 'Caja::cerrar');
});

// ── Propinas (Ley 1935 de 2018) ─────────────────────────────────────
// Las liquida caja, no el restaurante: quien cobra la propina no la reparte.
$routes->group('', ['filter' => ['auth', 'permiso:propinas.liquidar']], static function ($routes) {
    $routes->get('propinas', 'Propinas::index');
    $routes->get('propinas/preparar', 'Propinas::preparar');
    $routes->post('propinas/guardar', 'Propinas::guardar');
    $routes->get('propinas/ver/(:num)', 'Propinas::ver/$1');
    $routes->get('propinas/comprobante/(:num)', 'Propinas::comprobante/$1');
    $routes->post('propinas/entregar/(:num)', 'Propinas::entregar/$1');
});

// ── Informes ────────────────────────────────────────────────────────
// Recepción entra con `reportes.ocupacion`; los ingresos y los márgenes los
// esconde la propia pantalla.
$routes->group('', ['filter' => ['auth', 'permiso:reportes.ocupacion,reportes.ingresos']], static function ($routes) {
    $routes->get('reportes', 'Reportes::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:reportes.exportar']], static function ($routes) {
    $routes->get('reportes/csv', 'Reportes::csv');
});

// ── Carta del restaurante ───────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:carta.gestionar']], static function ($routes) {
    $routes->get('carta', 'Carta::index');
    $routes->get('carta/ficha/(:num)', 'Carta::ficha/$1');
    $routes->post('carta/receta/(:num)', 'Carta::guardarReceta/$1');
    $routes->post('carta/receta/eliminar/(:num)', 'Carta::eliminarReceta/$1');
    $routes->post('carta/categoria/guardar', 'Carta::guardarCategoria');
    $routes->post('carta/categoria/actualizar/(:num)', 'Carta::actualizarCategoria/$1');
    $routes->post('carta/categoria/eliminar/(:num)', 'Carta::eliminarCategoria/$1');
    $routes->post('carta/producto/guardar', 'Carta::guardarProducto');
    $routes->post('carta/producto/actualizar/(:num)', 'Carta::actualizarProducto/$1');
    $routes->post('carta/producto/eliminar/(:num)', 'Carta::eliminarProducto/$1');

    // Modificadores
    $routes->get('modificadores', 'Carta::modificadores');
    $routes->post('modificadores/grupo/guardar', 'Carta::guardarGrupo');
    $routes->post('modificadores/grupo/actualizar/(:num)', 'Carta::actualizarGrupo/$1');
    $routes->post('modificadores/grupo/eliminar/(:num)', 'Carta::eliminarGrupo/$1');
    $routes->post('modificadores/opcion/guardar', 'Carta::guardarModificador');
    $routes->post('modificadores/opcion/eliminar/(:num)', 'Carta::eliminarModificador/$1');
});
// Marcar un plato agotado lo hace sala en mitad del servicio: no exige poder
// tocar la carta entera.
$routes->group('', ['filter' => ['auth', 'permiso:carta.disponible']], static function ($routes) {
    $routes->post('carta/producto/disponible/(:num)', 'Carta::alternarDisponible/$1');
});

// ── Escandallo: insumos y preparaciones ─────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:insumos.gestionar']], static function ($routes) {
    $routes->get('insumos', 'Carta::insumos');
    $routes->post('insumos/guardar', 'Carta::guardarInsumo');
    $routes->post('insumos/actualizar/(:num)', 'Carta::actualizarInsumo/$1');
    $routes->post('insumos/eliminar/(:num)', 'Carta::eliminarInsumo/$1');

    $routes->get('preparaciones', 'Preparaciones::index');
    $routes->post('preparaciones/crear', 'Preparaciones::crear');
    $routes->get('preparaciones/ficha/(:num)', 'Preparaciones::ficha/$1');
    $routes->post('preparaciones/actualizar/(:num)', 'Preparaciones::actualizar/$1');
    $routes->post('preparaciones/componente/(:num)', 'Preparaciones::guardarComponente/$1');
    $routes->post('preparaciones/componente/eliminar/(:num)', 'Preparaciones::eliminarComponente/$1');
    $routes->post('preparaciones/eliminar/(:num)', 'Preparaciones::eliminar/$1');
});

// ── Administración ──────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:administracion.ver']], static function ($routes) {
    $routes->get('administracion', 'Administracion::index');
    $routes->post('administracion/hotel', 'Administracion::guardarHotel');
    $routes->post('administracion/tpv', 'Administracion::guardarTpv');
    $routes->post('administracion/cambio', 'Administracion::actualizarCambio');
    $routes->post('administracion/fichaje', 'Administracion::guardarFichaje');
});
// Credenciales de terceros: solo gerencia. Quien las tiene puede facturar en
// nombre del hotel y cobrar con su pasarela.
$routes->group('', ['filter' => ['auth', 'permiso:administracion.integraciones']], static function ($routes) {
    $routes->post('administracion/correo', 'Administracion::guardarCorreo');
    $routes->post('administracion/correo/probar', 'Administracion::probarCorreo');
    $routes->post('administracion/wompi', 'Administracion::guardarWompi');
    $routes->post('administracion/siigo', 'Administracion::guardarSiigo');
    $routes->post('administracion/siigo/probar', 'Administracion::probarSiigo');
});

// ── Facturación electrónica ─────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:facturas.ver']], static function ($routes) {
    $routes->get('facturas', 'Facturas::index');
    $routes->get('facturas/ver/(:num)', 'Facturas::ver/$1');
    $routes->get('facturas/reserva/(:num)', 'Facturas::desdeReserva/$1');
    $routes->get('facturas/comanda/(:num)', 'Facturas::desdeComanda/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:facturas.emitir']], static function ($routes) {
    $routes->post('facturas/emitir', 'Facturas::emitir');
    $routes->post('facturas/reenviar/(:num)', 'Facturas::reenviar/$1');
});

// ── Cupones ─────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:cupones.gestionar']], static function ($routes) {
    $routes->get('cupones', 'Cupones::index');
    $routes->get('cupones/ver/(:num)', 'Cupones::ver/$1');
    $routes->post('cupones/guardar', 'Cupones::guardar');
    $routes->post('cupones/actualizar/(:num)', 'Cupones::actualizar/$1');
    $routes->post('cupones/estado/(:num)', 'Cupones::alternar/$1');
    $routes->post('cupones/eliminar/(:num)', 'Cupones::eliminar/$1');
});

// ── Portales y canales ──────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:canales.gestionar']], static function ($routes) {
    $routes->get('canales', 'Canales::index');
    $routes->post('canales/guardar', 'Canales::guardar');
    $routes->post('canales/sincronizar', 'Canales::sincronizar');
    $routes->post('canales/eliminar/(:num)', 'Canales::eliminar/$1');
    $routes->post('canales/token/(:num)', 'Canales::renovarToken/$1');
    $routes->post('canales/bloquear', 'Canales::bloquear');
    $routes->post('canales/desbloquear/(:num)', 'Canales::desbloquear/$1');
});

// ── Motor de tarifas ────────────────────────────────────────────────
// Recepción las ve para poder cotizar por teléfono; cambiarlas, no.
$routes->group('', ['filter' => ['auth', 'permiso:tarifas.ver']], static function ($routes) {
    $routes->get('tarifas', 'Tarifas::index');
    $routes->get('tarifas/calendario', 'Tarifas::calendario');
    $routes->get('tarifas/agente', 'Tarifas::agente');
    $routes->get('tarifas/simulador', 'Tarifas::simulador');
    $routes->get('tarifas/temporada/(:num)', 'Tarifas::temporada/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:tarifas.editar']], static function ($routes) {
    $routes->post('tarifas/agente/aplicar', 'Tarifas::aplicarAgente');
    $routes->post('tarifas/temporada/guardar', 'Tarifas::guardarTemporada');
    $routes->post('tarifas/temporada/actualizar/(:num)', 'Tarifas::actualizarTemporada/$1');
    $routes->post('tarifas/temporada/precios/(:num)', 'Tarifas::guardarPrecios/$1');
    $routes->post('tarifas/temporada/estado/(:num)', 'Tarifas::alternarTemporada/$1');
    $routes->post('tarifas/temporada/eliminar/(:num)', 'Tarifas::eliminarTemporada/$1');
    $routes->post('tarifas/regla/guardar', 'Tarifas::guardarRegla');
    $routes->post('tarifas/regla/actualizar/(:num)', 'Tarifas::actualizarRegla/$1');
    $routes->post('tarifas/regla/estado/(:num)', 'Tarifas::alternarRegla/$1');
    $routes->post('tarifas/regla/eliminar/(:num)', 'Tarifas::eliminarRegla/$1');
});

// ── Catálogos que alimentan las fichas ──────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:servicios.gestionar']], static function ($routes) {
    $routes->get('servicios', 'Catalogos::servicios');
    $routes->post('servicios/guardar', 'Catalogos::guardarServicio');
    $routes->post('servicios/actualizar/(:num)', 'Catalogos::actualizarServicio/$1');
    $routes->post('servicios/eliminar/(:num)', 'Catalogos::eliminarServicio/$1');

    $routes->get('inventario-cabanas', 'Catalogos::inventario');
    $routes->post('inventario-cabanas/guardar', 'Catalogos::guardarItem');
    $routes->post('inventario-cabanas/actualizar/(:num)', 'Catalogos::actualizarItem/$1');
    $routes->post('inventario-cabanas/eliminar/(:num)', 'Catalogos::eliminarItem/$1');
});

// ── Tipos de alojamiento ────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:tipos.gestionar']], static function ($routes) {
    $routes->get('tipos', 'Tipos::index');
    $routes->get('tipos/ficha/(:num)', 'Tipos::ficha/$1');
    $routes->post('tipos/servicios/(:num)', 'Tipos::guardarServicios/$1');
    $routes->post('tipos/foto/(:num)', 'Tipos::subirFoto/$1');
    $routes->post('tipos/video/(:num)', 'Tipos::anadirVideo/$1');
    $routes->post('tipos/foto/portada/(:num)', 'Tipos::portada/$1');
    $routes->post('tipos/foto/mover/(:num)', 'Tipos::moverFoto/$1');
    $routes->post('tipos/foto/eliminar/(:num)', 'Tipos::eliminarFoto/$1');
    $routes->get('tipos/nuevo', 'Tipos::nuevo');
    $routes->post('tipos/guardar', 'Tipos::guardar');
    $routes->get('tipos/editar/(:num)', 'Tipos::editar/$1');
    $routes->post('tipos/actualizar/(:num)', 'Tipos::actualizar/$1');
    $routes->post('tipos/eliminar/(:num)', 'Tipos::eliminar/$1');
});

// ── Textos de la web pública ────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:traducciones.gestionar']], static function ($routes) {
    $routes->get('traducciones', 'Traducciones::index');
    $routes->post('traducciones/guardar', 'Traducciones::guardar');
});

// ── Personal ────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:personal.ver']], static function ($routes) {
    $routes->get('personal', 'Personal::index');
    $routes->get('personal/ver/(:num)', 'Personal::ver/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:personal.editar']], static function ($routes) {
    $routes->get('personal/nuevo', 'Personal::nuevo');
    $routes->post('personal/guardar', 'Personal::guardar');
    $routes->get('personal/editar/(:num)', 'Personal::editar/$1');
    $routes->post('personal/actualizar/(:num)', 'Personal::actualizar/$1');
    $routes->post('personal/estado/(:num)', 'Personal::alternarActivo/$1');
    $routes->post('personal/tpv/(:num)', 'Personal::tpv/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:personal.documentos']], static function ($routes) {
    $routes->get('personal/documento/(:num)', 'Personal::documento/$1');
    $routes->post('personal/documento/(:num)', 'Personal::subirDocumento/$1');
    $routes->post('personal/documento/eliminar/(:num)', 'Personal::eliminarDocumento/$1');
});

// ── Control de jornada ──────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:fichajes.ver']], static function ($routes) {
    $routes->get('fichajes', 'Fichajes::index');
    $routes->get('fichajes/empleado/(:num)', 'Fichajes::empleado/$1');
    $routes->get('fichajes/foto/(:num)', 'Fichajes::foto/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:fichajes.corregir']], static function ($routes) {
    $routes->post('fichajes/anadir', 'Fichajes::anadir');
    $routes->post('fichajes/anular/(:num)', 'Fichajes::anular/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:fichajes.pin']], static function ($routes) {
    $routes->post('fichajes/pin/(:num)', 'Fichajes::pin/$1');
    $routes->post('fichajes/pin/quitar/(:num)', 'Fichajes::quitarPin/$1');
    $routes->post('fichajes/movil/(:num)', 'Fichajes::alternarMovil/$1');
});

// ── Turnos y ausencias ──────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:turnos.ver']], static function ($routes) {
    $routes->get('turnos', 'Turnos::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:turnos.editar']], static function ($routes) {
    $routes->post('turnos/guardar', 'Turnos::guardar');
    $routes->post('turnos/eliminar/(:num)', 'Turnos::eliminar/$1');
    $routes->post('turnos/copiar', 'Turnos::copiarSemana');
});
$routes->group('', ['filter' => ['auth', 'permiso:ausencias.gestionar']], static function ($routes) {
    $routes->get('ausencias', 'Turnos::ausencias');
    $routes->post('ausencias/guardar', 'Turnos::guardarAusencia');
    $routes->post('ausencias/resolver/(:num)', 'Turnos::resolverAusencia/$1');
    $routes->post('ausencias/eliminar/(:num)', 'Turnos::eliminarAusencia/$1');
});

// ── Usuarios ────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:usuarios.gestionar']], static function ($routes) {
    $routes->get('usuarios', 'Usuarios::index');
    $routes->get('usuarios/nuevo', 'Usuarios::nuevo');
    $routes->post('usuarios/guardar', 'Usuarios::guardar');
    $routes->get('usuarios/editar/(:num)', 'Usuarios::editar/$1');
    $routes->post('usuarios/actualizar/(:num)', 'Usuarios::actualizar/$1');
    $routes->post('usuarios/eliminar/(:num)', 'Usuarios::eliminar/$1');
});

// ── Perfiles de acceso ──────────────────────────────────────────────
// Solo gerencia: quien puede editar perfiles puede darse a sí mismo cualquier
// permiso. Es la llave maestra y no se reparte.
$routes->group('', ['filter' => ['auth', 'permiso:roles.gestionar']], static function ($routes) {
    $routes->get('roles', 'Roles::index');
    $routes->get('roles/nuevo', 'Roles::nuevo');
    $routes->post('roles/guardar', 'Roles::guardar');
    $routes->get('roles/editar/(:num)', 'Roles::editar/$1');
    $routes->post('roles/actualizar/(:num)', 'Roles::actualizar/$1');
    $routes->post('roles/eliminar/(:num)', 'Roles::eliminar/$1');
});

// ── Auditoría ───────────────────────────────────────────────────────
// Solo lectura: no hay ruta para editar ni para borrar. Una auditoría que se
// puede retocar no sirve para lo único que sirve una auditoría.
$routes->group('', ['filter' => ['auth', 'permiso:auditoria.ver']], static function ($routes) {
    $routes->get('auditoria', 'Auditoria::index');
    $routes->get('auditoria/referencia/(:segment)', 'Auditoria::referencia/$1');
});

// ── Portal del huésped: su estancia, en su móvil ────────────────────
//
// Sin sesión y sin contraseña: **el enlace es la credencial**. Nadie se crea
// una cuenta para pedir toallas, y una contraseña más es una contraseña que
// acaba apuntada en un papel. A cambio, el token es largo, aleatorio, caduca
// solo y se puede revocar desde recepción.
//
// Cada método del controlador vuelve a validarlo; ninguno se fía de que el
// anterior lo hiciera.
$routes->get('estancia/(:segment)', 'Portal::index/$1');
$routes->get('estancia/(:segment)/cuenta', 'Portal::cuenta/$1');
$routes->get('estancia/(:segment)/info', 'Portal::info/$1');
$routes->get('estancia/(:segment)/solicitudes', 'Portal::solicitudes/$1');
$routes->post('estancia/(:segment)/pedir', 'Portal::pedir/$1');
$routes->get('estancia/(:segment)/encuesta', 'Portal::encuesta/$1');
$routes->post('estancia/(:segment)/encuesta', 'Portal::responderEncuesta/$1');

// ── Peticiones de huéspedes, desde el panel ─────────────────────────
//
// Sin esta pantalla el portal sería un buzón sin cartero: el huésped pediría
// toallas y la petición se quedaría en una tabla que nadie mira.
//
// Se apoya en los permisos que ya existen —quien atiende el tablero de
// limpieza es quien lleva las toallas— en vez de inventar unos nuevos.
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.ver,mantenimiento.ver']], static function ($routes) {
    $routes->get('solicitudes', 'Solicitudes::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.trabajar,mantenimiento.trabajar,reservas.editar']], static function ($routes) {
    $routes->post('solicitudes/atender/(:num)', 'Solicitudes::atender/$1');
    $routes->post('solicitudes/encuesta/(:num)', 'Solicitudes::leerEncuesta/$1');
});
