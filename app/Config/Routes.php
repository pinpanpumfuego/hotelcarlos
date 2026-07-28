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
// Correo + PIN de 4 dígitos. Solo funciona en equipos ya reconocidos: lo que
// sostiene cuatro dígitos no es su longitud, es que además haya que estar en
// una máquina del hotel. Ver App\Libraries\AccesoPin.
$routes->post('login/pin', 'Login::pin');
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
    $routes->post('perfil/equipo/quitar/(:num)', 'Perfil::quitarEquipo/$1');

    // Confirmar la contraseña cuando se entró con PIN y toca algo sensible.
    // Va sin `permiso:` a propósito: es la pantalla que devuelve el permiso.
    $routes->get('confirmar', 'Confirmar::index');
    $routes->post('confirmar/clave', 'Confirmar::clave');
});

// ── Mantenimiento ───────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.ver']], static function ($routes) {
    $routes->get('mantenimiento', 'Mantenimiento::index');
    $routes->get('mantenimiento/ver/(:num)', 'Mantenimiento::ver/$1');
    $routes->get('mantenimiento/foto/(:num)', 'Mantenimiento::verFoto/$1');
    $routes->get('mantenimiento/informes', 'Mantenimiento::informes');
});

// ── Plan preventivo ─────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.planes']], static function ($routes) {
    $routes->get('preventivos', 'Preventivos::index');
    $routes->get('preventivos/nuevo', 'Preventivos::nuevo');
    $routes->get('preventivos/editar/(:num)', 'Preventivos::editar/$1');
    $routes->post('preventivos/guardar', 'Preventivos::guardar');
    $routes->post('preventivos/guardar/(:num)', 'Preventivos::guardar/$1');
    $routes->post('preventivos/eliminar/(:num)', 'Preventivos::eliminar/$1');
    $routes->post('preventivos/generar', 'Preventivos::generarAhora');
});

// ── Medidores ───────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:medidores.ver']], static function ($routes) {
    $routes->get('medidores', 'Medidores::index');
    $routes->get('medidores/ver/(:num)', 'Medidores::ver/$1');
    $routes->post('medidores/guardar', 'Medidores::guardar');
    $routes->post('medidores/guardar/(:num)', 'Medidores::guardar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:medidores.leer']], static function ($routes) {
    $routes->post('medidores/leer/(:num)', 'Medidores::leer/$1');
    $routes->post('medidores/lectura/borrar/(:num)', 'Medidores::borrarLectura/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.crear']], static function ($routes) {
    $routes->post('mantenimiento/guardar', 'Mantenimiento::guardar');
});
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.trabajar']], static function ($routes) {
    $routes->post('mantenimiento/iniciar/(:num)', 'Mantenimiento::iniciar/$1');
    $routes->post('mantenimiento/pausar/(:num)', 'Mantenimiento::pausar/$1');
    $routes->post('mantenimiento/resolver/(:num)', 'Mantenimiento::resolver/$1');
    $routes->post('mantenimiento/foto/subir/(:num)', 'Mantenimiento::foto/$1');
    $routes->post('mantenimiento/material/(:num)', 'Mantenimiento::material/$1');
    $routes->post('mantenimiento/material/quitar/(:num)', 'Mantenimiento::quitarMaterial/$1');
});
// El visto bueno lo da otra persona: por eso va en su propio permiso y no
// dentro de `trabajar`. Quien arregla no se firma a sí mismo.
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.verificar']], static function ($routes) {
    $routes->post('mantenimiento/verificar/(:num)', 'Mantenimiento::verificar/$1');
    $routes->post('mantenimiento/rechazar/(:num)', 'Mantenimiento::rechazar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.asignar']], static function ($routes) {
    $routes->post('mantenimiento/asignar/(:num)', 'Mantenimiento::asignar/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:mantenimiento.prioridad']], static function ($routes) {
    $routes->post('mantenimiento/prioridad/(:num)', 'Mantenimiento::prioridad/$1');
    $routes->post('mantenimiento/anular/(:num)', 'Mantenimiento::anular/$1');
});

// ── Activos y equipos ───────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:activos.ver']], static function ($routes) {
    $routes->get('activos', 'Activos::index');
    $routes->get('activos/ver/(:num)', 'Activos::ver/$1');
    $routes->get('activos/qr/(:num)', 'Activos::qr/$1');
    $routes->get('activos/documento/(:num)', 'Activos::documento/$1');

    // Lo que sale al escanear la etiqueta. Pide sesión como todo lo demás: el
    // QR no es una llave, y quien lo fotografíe sin cuenta no ve nada.
    $routes->get('activo/(:segment)', 'Activos::escanear/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:activos.gestionar']], static function ($routes) {
    $routes->get('activos/nuevo', 'Activos::nuevo');
    $routes->get('activos/editar/(:num)', 'Activos::editar/$1');
    $routes->get('activos/etiquetas', 'Activos::etiquetas');
    $routes->post('activos/guardar', 'Activos::guardar');
    $routes->post('activos/guardar/(:num)', 'Activos::guardar/$1');
    $routes->post('activos/baja/(:num)', 'Activos::darDeBaja/$1');
    $routes->post('activos/documento/subir/(:num)', 'Activos::subirDocumento/$1');
    $routes->post('activos/documento/borrar/(:num)', 'Activos::borrarDocumento/$1');
});

// ── Limpieza ────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.ver']], static function ($routes) {
    $routes->get('limpieza', 'Limpieza::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.trabajar']], static function ($routes) {
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
    $routes->get('huespedes/ver/(:num)', 'Huespedes::ver/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:huespedes.editar']], static function ($routes) {
    $routes->get('huespedes/nuevo', 'Huespedes::nuevo');
    $routes->post('huespedes/guardar', 'Huespedes::guardar');
    $routes->get('huespedes/editar/(:num)', 'Huespedes::editar/$1');
    $routes->post('huespedes/actualizar/(:num)', 'Huespedes::actualizar/$1');
    // El permiso fino de lo sensible lo comprueba el controlador: aquí solo
    // entra quien pueda editar la ficha.
    $routes->post('huespedes/preferencia/(:num)', 'Huespedes::anadirPreferencia/$1');
    $routes->post('huespedes/preferencia/borrar/(:num)', 'Huespedes::borrarPreferencia/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:consentimientos.gestionar']], static function ($routes) {
    $routes->post('huespedes/consentimiento/(:num)', 'Huespedes::consentimiento/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:huespedes.fusionar']], static function ($routes) {
    $routes->post('huespedes/fusionar/(:num)', 'Huespedes::fusionar/$1');
});

// ── Comunicaciones ──────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:comunicaciones.ver']], static function ($routes) {
    $routes->get('comunicaciones', 'Comunicaciones::index');
    $routes->get('comunicaciones/ver/(:num)', 'Comunicaciones::ver/$1');
    $routes->get('comunicaciones/plantillas', 'Comunicaciones::plantillas');
});
$routes->group('', ['filter' => ['auth', 'permiso:comunicaciones.enviar']], static function ($routes) {
    $routes->post('comunicaciones/procesar', 'Comunicaciones::procesar');
    $routes->post('comunicaciones/encolar', 'Comunicaciones::encolarHoy');
    $routes->post('comunicaciones/reintentar/(:num)', 'Comunicaciones::reintentar/$1');
    $routes->post('comunicaciones/cancelar/(:num)', 'Comunicaciones::cancelar/$1');
});
// Cambiar una plantilla cambia lo que se le dice a todo el mundo: va aparte.
$routes->group('', ['filter' => ['auth', 'permiso:comunicaciones.plantillas']], static function ($routes) {
    $routes->get('comunicaciones/plantilla/(:num)', 'Comunicaciones::editarPlantilla/$1');
    $routes->post('comunicaciones/plantilla/(:num)', 'Comunicaciones::guardarPlantilla/$1');
    $routes->post('comunicaciones/regla/(:num)', 'Comunicaciones::guardarRegla/$1');
});

// ── Niveles y referidos ─────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:niveles.gestionar']], static function ($routes) {
    $routes->get('niveles', 'Niveles::index');
    $routes->post('niveles/guardar/(:num)', 'Niveles::guardar/$1');
    $routes->post('niveles/referidos', 'Niveles::guardarReferidos');
});

// ── Campañas ────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:campanas.gestionar']], static function ($routes) {
    $routes->get('campanas', 'Campanas::index');
    $routes->get('campanas/nueva', 'Campanas::nueva');
    $routes->get('campanas/ver/(:num)', 'Campanas::ver/$1');
    $routes->post('campanas/guardar', 'Campanas::guardar');
    $routes->post('campanas/enviar/(:num)', 'Campanas::enviar/$1');
    $routes->post('campanas/cancelar/(:num)', 'Campanas::cancelar/$1');
});

// ── PQR ─────────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:pqr.ver']], static function ($routes) {
    $routes->get('pqr', 'Pqr::index');
    $routes->get('pqr/ver/(:num)', 'Pqr::ver/$1');
    $routes->get('pqr/informe', 'Pqr::informe');
});
$routes->group('', ['filter' => ['auth', 'permiso:pqr.gestionar']], static function ($routes) {
    $routes->post('pqr/guardar', 'Pqr::guardar');
    $routes->post('pqr/asignar/(:num)', 'Pqr::asignar/$1');
    $routes->post('pqr/nota/(:num)', 'Pqr::nota/$1');
    $routes->get('pqr/buscar-huesped', 'Pqr::buscarHuesped');
});
// Contestar en nombre del hotel y cerrar una queja no lo hace cualquiera.
$routes->group('', ['filter' => ['auth', 'permiso:pqr.responder']], static function ($routes) {
    $routes->post('pqr/responder/(:num)', 'Pqr::responder/$1');
    $routes->post('pqr/cerrar/(:num)', 'Pqr::cerrar/$1');
    $routes->post('pqr/reabrir/(:num)', 'Pqr::reabrir/$1');
});

// ── Cumplimiento ────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:cumplimiento.ver']], static function ($routes) {
    $routes->get('legal', 'Legal::index');
    $routes->get('legal/derechos', 'Legal::derechos');
    $routes->get('legal/derecho/(:num)', 'Legal::verDerecho/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:cumplimiento.gestionar']], static function ($routes) {
    $routes->get('legal/datos', 'Legal::datos');
    $routes->post('legal/datos', 'Legal::guardarDatos');
    $routes->post('legal/copia', 'Legal::copiaHecha');
    $routes->get('legal/politicas', 'Legal::politicas');
    $routes->get('legal/politica', 'Legal::editarPolitica');
    $routes->get('legal/politica/(:num)', 'Legal::editarPolitica/$1');
    $routes->post('legal/politica', 'Legal::guardarPolitica');
    $routes->post('legal/politica/(:num)', 'Legal::guardarPolitica/$1');
});
// Entregar los datos de alguien, o borrarlos, va en su propio permiso.
$routes->group('', ['filter' => ['auth', 'permiso:datos.derechos']], static function ($routes) {
    $routes->post('legal/derecho', 'Legal::registrarDerecho');
    $routes->post('legal/derecho/identificar/(:num)', 'Legal::identificar/$1');
    $routes->get('legal/derecho/expediente/(:num)', 'Legal::expediente/$1');
    $routes->post('legal/derecho/atender/(:num)', 'Legal::atenderDerecho/$1');
    $routes->post('legal/derecho/rechazar/(:num)', 'Legal::rechazarDerecho/$1');
});

// ── Baja de correos ─────────────────────────────────────────────────
//
// Públicas y sin sesión a propósito: pedirle una contraseña a alguien que
// quiere dejar de recibir correos es exactamente poner trabas. El token solo
// sirve para retirar la finalidad de ese correo; no abre nada más.
$routes->get('baja/(:segment)', 'Baja::darse/$1');
$routes->get('px/(:segment)', 'Baja::pixel/$1');
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
    $routes->post('linea/(:num)/estado', 'Pos::estadoLinea/$1');
    // El gesto verde no pasa por el PIN del encargado: ya se lo ganó el
    // huésped y housekeeping lo confirmó. Ver Pos::canjearVerde().
    $routes->post('linea/(:num)/verde', 'Pos::canjearVerde/$1');
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

// ── Cartera ─────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:cartera.ver']], static function ($routes) {
    $routes->get('cartera', 'Cartera::index');
    $routes->get('cartera/ver/(:num)', 'Cartera::ver/$1');
    $routes->get('cartera/estado/(:num)', 'Cartera::estadoTexto/$1');
});
// Subir un cupo es dejar que alguien deba más: es decisión de dinero.
$routes->group('', ['filter' => ['auth', 'permiso:cartera.gestionar']], static function ($routes) {
    $routes->post('cartera/guardar', 'Cartera::guardar');
    $routes->post('cartera/guardar/(:num)', 'Cartera::guardar/$1');
    $routes->post('cartera/estado/(:num)', 'Cartera::estado/$1');
    $routes->post('cartera/ajustar/(:num)', 'Cartera::ajustar/$1');
    $routes->post('cartera/cargar', 'Cartera::cargarReserva');
});
$routes->group('', ['filter' => ['auth', 'permiso:cartera.cobrar']], static function ($routes) {
    $routes->post('cartera/abonar/(:num)', 'Cartera::abonar/$1');
});

// ── Caja ────────────────────────────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:caja.ver']], static function ($routes) {
    $routes->get('caja', 'Caja::index');
    $routes->get('caja/turno/(:num)', 'Caja::turno/$1');
});
// Retirar plata del cajón no es lo mismo que apuntar un gasto: quien puede
// hacerlo puede vaciar la caja.
$routes->group('', ['filter' => ['auth', 'permiso:caja.retirar']], static function ($routes) {
    $routes->post('caja/retirar', 'Caja::retirar');
});
$routes->group('', ['filter' => ['auth', 'permiso:caja.puntos']], static function ($routes) {
    $routes->get('caja/configurar', 'Caja::configurar');
    $routes->post('caja/punto', 'Caja::guardarPunto');
    $routes->post('caja/punto/(:num)', 'Caja::guardarPunto/$1');
});
// Cambiar si un medio «afecta caja» descuadra todos los arqueos siguientes.
$routes->group('', ['filter' => ['auth', 'permiso:medios.gestionar']], static function ($routes) {
    $routes->post('caja/medio/(:num)', 'Caja::guardarMedio/$1');
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
    $routes->get('reportes/gerencia', 'Reportes::gerencia');
    $routes->get('reportes/operacion', 'Reportes::operacion');
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
    $routes->post('administracion/portal', 'Administracion::guardarPortal');
    $routes->post('administracion/verde', 'Administracion::guardarVerde');
    $routes->post('administracion/fichaje', 'Administracion::guardarFichaje');
});
// Credenciales de terceros: solo gerencia. Quien las tiene puede facturar en
// nombre del hotel y cobrar con su pasarela.
$routes->group('', ['filter' => ['auth', 'permiso:administracion.integraciones']], static function ($routes) {
    $routes->post('administracion/correo', 'Administracion::guardarCorreo');
    $routes->post('administracion/correo/probar', 'Administracion::probarCorreo');
    $routes->post('administracion/wompi', 'Administracion::guardarWompi');
    $routes->post('administracion/wompi/probar', 'Administracion::probarWompi');
    $routes->post('administracion/mantenimiento', 'Administracion::guardarMantenimiento');
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
    $routes->post('fichajes/pin/panel/(:num)', 'Fichajes::pinPanel/$1');
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

// ── Portal del huésped · segunda tanda ──────────────────────────────
$routes->get('estancia/(:segment)/preferencias', 'Portal::preferencias/$1');
$routes->post('estancia/(:segment)/preferencias', 'Portal::guardarPreferencias/$1');
$routes->get('estancia/(:segment)/actividades', 'Portal::actividades/$1');
$routes->post('estancia/(:segment)/apuntarse', 'Portal::apuntarse/$1');
$routes->get('estancia/(:segment)/carta', 'Portal::carta/$1');
$routes->post('estancia/(:segment)/pedido', 'Portal::pedido/$1');
$routes->get('estancia/(:segment)/comprobante', 'Portal::comprobante/$1');

// ── Minibar del portal (solo si el hotel y la cabaña lo tienen) ─────
// Gesto verde: el huésped renuncia al cambio de lencería de esta noche.
$routes->get('estancia/(:segment)/verde', 'Portal::verde/$1');
$routes->post('estancia/(:segment)/verde', 'Portal::pedirVerde/$1');

$routes->get("estancia/(:segment)/minibar", "Portal::minibar/$1");
$routes->post("estancia/(:segment)/minibar", "Portal::declararMinibar/$1");
$routes->post("estancia/(:segment)/minibar/reponer", "Portal::reponerMinibar/$1");

// ── Planes incluidos en la tarifa ───────────────────────────────────
// Van con las tarifas: qué incluye el precio de la noche es una decisión de
// precio, no de restaurante.
$routes->group('', ['filter' => ['auth', 'permiso:tarifas.ver']], static function ($routes) {
    $routes->get('planes', 'Planes::index');
    $routes->get('planes/ver/(:num)', 'Planes::ver/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:tarifas.editar']], static function ($routes) {
    $routes->post('planes/guardar', 'Planes::guardar');
    $routes->post('planes/actualizar/(:num)', 'Planes::actualizar/$1');
    $routes->post('planes/eliminar/(:num)', 'Planes::eliminar/$1');
    $routes->post('planes/derecho/(:num)', 'Planes::anadirDerecho/$1');
    $routes->post('planes/derecho/quitar/(:num)/(:num)', 'Planes::quitarDerecho/$1/$2');
    $routes->post('planes/franjas', 'Planes::guardarFranjas');
});

// ── Almacén ─────────────────────────────────────────────────────────
// Se apoya en `insumos.gestionar`: quien lleva los escandallos es quien lleva
// la despensa. Cuando el almacén tenga responsable propio, se le dará permiso
// aparte sin tocar nada más.
$routes->group('', ['filter' => ['auth', 'permiso:insumos.gestionar']], static function ($routes) {
    $routes->get('almacen', 'Almacen::index');
    $routes->get('almacen/bodega/(:num)', 'Almacen::bodega/$1');
    $routes->get('almacen/insumo/(:num)', 'Almacen::insumo/$1');
    $routes->get('almacen/movimientos', 'Almacen::movimientos');

    $routes->post('almacen/entrada', 'Almacen::entrada');
    $routes->post('almacen/salida', 'Almacen::salida');
    $routes->post('almacen/traslado', 'Almacen::traslado');
    $routes->post('almacen/conteo', 'Almacen::conteo');
    $routes->post('almacen/recalcular/(:num)/(:num)', 'Almacen::recalcular/$1/$2');
});

// ── Compras y costes de cocina ──────────────────────────────────────
$routes->group('', ['filter' => ['auth', 'permiso:insumos.gestionar']], static function ($routes) {
    $routes->get('compras', 'Compras::index');
    $routes->get('compras/ver/(:num)', 'Compras::ver/$1');
    $routes->get('compras/costes', 'Compras::costes');

    $routes->post('compras/crear', 'Compras::crear');
    $routes->post('compras/linea/(:num)', 'Compras::anadirLinea/$1');
    $routes->post('compras/linea/quitar/(:num)/(:num)', 'Compras::quitarLinea/$1/$2');
    $routes->post('compras/enviar/(:num)', 'Compras::enviar/$1');
    $routes->post('compras/recibir/(:num)', 'Compras::recibir/$1');
    $routes->post('compras/anular/(:num)', 'Compras::anular/$1');
    $routes->post('compras/proveedores', 'Compras::guardarProveedor');
    $routes->post('compras/proveedores/migrar', 'Compras::migrarProveedores');
});

// ── Limpieza: tareas, inspección, objetos y checklist ───────────────
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.ver']], static function ($routes) {
    $routes->get('limpieza/tarea/(:num)', 'Limpieza::tarea/$1');
    $routes->get('limpieza/objetos', 'Limpieza::objetos');
});
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.trabajar']], static function ($routes) {
    $routes->post('limpieza/abrir/(:num)', 'Limpieza::abrir/$1');
    $routes->post('limpieza/empezar/(:num)', 'Limpieza::empezar/$1');
    $routes->post('limpieza/punto/(:num)/(:num)', 'Limpieza::punto/$1/$2');
    $routes->post('limpieza/terminar/(:num)', 'Limpieza::terminar/$1');
    $routes->post('limpieza/no-molestar/(:num)', 'Limpieza::noMolestar/$1');
});
// Quien reporta una incidencia también puede apuntar un objeto olvidado
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.reportar']], static function ($routes) {
    $routes->post('limpieza/objeto', 'Limpieza::guardarObjeto');
    $routes->post('limpieza/objeto/estado/(:num)', 'Limpieza::estadoObjeto/$1');
});
// Inspeccionar y bloquear son decisiones, no trabajo de campo: van con
// `unidades.estado`, que es quien decide si la cabaña se puede vender.
$routes->group('', ['filter' => ['auth', 'permiso:unidades.estado']], static function ($routes) {
    $routes->post('limpieza/aprobar/(:num)', 'Limpieza::aprobar/$1');
    $routes->post('limpieza/rechazar/(:num)', 'Limpieza::rechazar/$1');
    $routes->post('limpieza/bloquear/(:num)', 'Limpieza::bloquear/$1');
    $routes->post('limpieza/desbloquear/(:num)', 'Limpieza::desbloquear/$1');
});
// Configurar el checklist y la política es cosa de quien organiza, no de quien
// limpia: se apoya en el permiso de gestionar cabañas.
$routes->group('', ['filter' => ['auth', 'permiso:unidades.gestionar']], static function ($routes) {
    $routes->get('limpieza/checklist', 'Limpieza::checklist');
    $routes->post('limpieza/checklist/guardar', 'Limpieza::guardarPunto');
    $routes->post('limpieza/checklist/eliminar/(:num)', 'Limpieza::eliminarPunto/$1');
    $routes->post('limpieza/politica', 'Limpieza::politica');
});

// ── Gesto verde ─────────────────────────────────────────────────────
//
// Se apoya en los permisos de limpieza: quien lleva las cabañas es quien sabe
// si se cambió la ropa, y confirmarlo es parte de esa tarea, no una función
// aparte que haga falta conceder a nadie.
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.ver,limpieza.trabajar']], static function ($routes) {
    $routes->get('verde', 'Verde::index');
});
$routes->group('', ['filter' => ['auth', 'permiso:limpieza.trabajar']], static function ($routes) {
    $routes->post('verde/pedir', 'Verde::pedir');
    $routes->post('verde/confirmar/(:num)', 'Verde::confirmar/$1');
    $routes->post('verde/descartar/(:num)', 'Verde::descartar/$1');
});

// ── Pagos en línea ──────────────────────────────────────────────────
//
// Públicas: el huésped no tiene cuenta. La seguridad no está en la sesión sino
// en que **nada se da por pagado por lo que diga el navegador**: el estado se
// pregunta siempre a la pasarela con nuestras credenciales.
$routes->get('pago/reserva/(:segment)', 'Pagos::reserva/$1');
// `/total` es el enlace que manda recepción: cobra todo el saldo del folio,
// extras incluidos, en vez de quedarse en el anticipo del motor de reservas.
$routes->get('pago/reserva/(:segment)/total', 'Pagos::reserva/$1/total');
$routes->get('pago/volver', 'Pagos::volver');
// Recargar una tarjeta de saldo desde su propio QR. Lo peor que puede hacer un
// desconocido con este enlace es meterle dinero a una tarjeta que no es suya.
$routes->get('pago/tarjeta/(:segment)', 'Pagos::tarjeta/$1');

// El aviso de la pasarela es máquina a máquina: sin CSRF y con firma propia.
$routes->post('pago/aviso', 'Pagos::aviso');

// ── Tarjetas de saldo ───────────────────────────────────────────────
//
// La pantalla que ve el titular al escanear su QR. Pública porque el titular no
// tiene cuenta en el panel, pero **sin PIN no enseña ni el nombre ni el saldo**:
// un QR se fotografía sin querer y quien pase al lado no tiene por qué saber
// cuánto lleva encima esa persona.
$routes->get('tarjeta/(:segment)', 'Tarjeta::ver/$1');
$routes->post('tarjeta/(:segment)/consultar', 'Tarjeta::consultar/$1');

$routes->group('', ['filter' => ['auth', 'permiso:tarjetas.ver']], static function ($routes) {
    $routes->get('tarjetas', 'Tarjetas::index');
    $routes->get('tarjetas/ver/(:num)', 'Tarjetas::ver/$1');
    $routes->get('tarjetas/qr/(:num)', 'Tarjetas::qr/$1');
    $routes->get('tarjetas/imprimir/(:num)', 'Tarjetas::imprimir/$1');
});
// Emitir una tarjeta, ponerle un descuento propio o congelarla es decisión de
// dinero: se separa de poder cargarla o cobrarla.
$routes->group('', ['filter' => ['auth', 'permiso:tarjetas.gestionar']], static function ($routes) {
    $routes->get('tarjetas/nueva', 'Tarjetas::nueva');
    $routes->post('tarjetas/emitir', 'Tarjetas::emitir');
    $routes->post('tarjetas/estado/(:num)', 'Tarjetas::estado/$1');
    $routes->post('tarjetas/pin/(:num)', 'Tarjetas::pin/$1');
    $routes->get('tarjetas/tipos', 'Tarjetas::tipos');
    $routes->post('tarjetas/tipos/guardar', 'Tarjetas::guardarTipo');
    $routes->post('tarjetas/tipos/guardar/(:num)', 'Tarjetas::guardarTipo/$1');
});
$routes->group('', ['filter' => ['auth', 'permiso:tarjetas.cargar']], static function ($routes) {
    $routes->post('tarjetas/cargar/(:num)', 'Tarjetas::cargar/$1');
    $routes->post('tarjetas/recarga-mensual', 'Tarjetas::recargaMensual');
});
$routes->group('', ['filter' => ['auth', 'permiso:tarjetas.cobrar']], static function ($routes) {
    $routes->get('tarjetas/simular', 'Tarjetas::simular');
    $routes->post('tarjetas/cobrar-folio/(:num)', 'Tarjetas::cobrarFolio/$1');
});
