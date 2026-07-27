<?php

declare(strict_types=1);

namespace App\Libraries\Permisos;

/**
 * Catálogo de permisos del sistema.
 *
 * **Los permisos son código; los roles son datos.** Un permiso corresponde a
 * una acción que existe de verdad en el sistema, así que inventarlo desde una
 * pantalla no serviría de nada: no habría nada detrás. Los roles, en cambio, se
 * crean y se editan desde el panel, porque son una decisión de gerencia.
 *
 * Los perfiles de aquí abajo son solo el **punto de partida** que se siembra la
 * primera vez. A partir de ahí, quien mande en el hotel los cambia desde el
 * panel y este fichero deja de opinar.
 */
final class Catalogo
{
    /** Módulos, en el orden en que se pintan en la pantalla de perfiles. */
    public const MODULOS = [
        'reservas'      => 'Reservas y venta',
        'folio'         => 'Folio y cobros',
        'huespedes'     => 'Huéspedes y registro',
        'caja'          => 'Caja',
        'restaurante'   => 'Restaurante',
        'housekeeping'  => 'Housekeeping',
        'mantenimiento' => 'Mantenimiento',
        'configuracion' => 'Configuración',
        'personal'      => 'Personal',
        'sistema'       => 'Informes y sistema',
    ];

    /**
     * Todos los permisos.
     *
     * `sensible` marca lo que mueve dinero, toca datos personales o cambia la
     * configuración. Al asignarlo, el panel avisa; al usarlo, queda en la
     * auditoría.
     *
     * @var array<string, array{modulo: string, nombre: string, sensible: bool}>
     */
    public const PERMISOS = [
        // ── Reservas y venta ────────────────────────────────────────────
        'reservas.ver'            => ['modulo' => 'reservas', 'nombre' => 'Ver reservas', 'sensible' => false],
        'reservas.crear'          => ['modulo' => 'reservas', 'nombre' => 'Crear reservas', 'sensible' => false],
        'reservas.editar'         => ['modulo' => 'reservas', 'nombre' => 'Modificar reservas', 'sensible' => false],
        'reservas.confirmar'      => ['modulo' => 'reservas', 'nombre' => 'Confirmar reservas', 'sensible' => false],
        'reservas.cancelar'       => ['modulo' => 'reservas', 'nombre' => 'Cancelar reservas', 'sensible' => true],
        'reservas.checkin'        => ['modulo' => 'reservas', 'nombre' => 'Registrar entrada', 'sensible' => false],
        'reservas.checkout'       => ['modulo' => 'reservas', 'nombre' => 'Registrar salida', 'sensible' => false],
        'calendario.ver'          => ['modulo' => 'reservas', 'nombre' => 'Calendario completo', 'sensible' => false],
        // El calendario sin nombres ni importes: «cabaña 3, sale mañana». Es lo
        // que necesita quien limpia para organizarse, y nada más.
        'calendario.ocupacion'    => ['modulo' => 'reservas', 'nombre' => 'Calendario solo ocupación', 'sensible' => false],
        'disponibilidad.bloquear' => ['modulo' => 'reservas', 'nombre' => 'Bloquear fechas', 'sensible' => false],

        // ── Folio y cobros ──────────────────────────────────────────────
        'folio.ver'               => ['modulo' => 'folio', 'nombre' => 'Ver la cuenta', 'sensible' => false],
        'folio.cargo'             => ['modulo' => 'folio', 'nombre' => 'Añadir cargos', 'sensible' => false],
        'folio.pago'              => ['modulo' => 'folio', 'nombre' => 'Registrar pagos', 'sensible' => false],
        'folio.descuento'         => ['modulo' => 'folio', 'nombre' => 'Aplicar descuento (con tope)', 'sensible' => true],
        // Sin este permiso, el descuento queda limitado por el tope que fije
        // gerencia. Un descuento del 100 % es un cobro que desaparece.
        'folio.descuento.sintope' => ['modulo' => 'folio', 'nombre' => 'Descuento sin límite', 'sensible' => true],
        'folio.cupon'             => ['modulo' => 'folio', 'nombre' => 'Aplicar cupones', 'sensible' => false],
        'folio.bono'              => ['modulo' => 'folio', 'nombre' => 'Usar bonos regalo', 'sensible' => false],

        // ── Huéspedes y registro ────────────────────────────────────────
        'huespedes.ver'        => ['modulo' => 'huespedes', 'nombre' => 'Ver huéspedes', 'sensible' => false],
        'huespedes.editar'     => ['modulo' => 'huespedes', 'nombre' => 'Crear y modificar huéspedes', 'sensible' => false],
        'huespedes.eliminar'   => ['modulo' => 'huespedes', 'nombre' => 'Eliminar huéspedes', 'sensible' => true],
        'registros.ver'        => ['modulo' => 'huespedes', 'nombre' => 'Cola de registros en línea', 'sensible' => false],
        'registros.aprobar'    => ['modulo' => 'huespedes', 'nombre' => 'Aprobar o rechazar registros', 'sensible' => false],
        // El más delicado del sistema: cédulas y firmas, Ley 1581/2012.
        'registros.documentos' => ['modulo' => 'huespedes', 'nombre' => 'Ver documentos de identidad y firmas', 'sensible' => true],
        'registros.reporte'    => ['modulo' => 'huespedes', 'nombre' => 'Generar el reporte legal', 'sensible' => false],
        // Alergias, dietas y accesibilidad son datos de salud: la Ley
        // 1581/2012 los llama sensibles y no los ve cualquiera. Cocina y
        // recepción sí, porque sin ellos pueden mandar a alguien al hospital.
        'huespedes.sensibles'  => ['modulo' => 'huespedes', 'nombre' => 'Ver alergias y datos de salud', 'sensible' => true],
        'huespedes.fusionar'   => ['modulo' => 'huespedes', 'nombre' => 'Fusionar perfiles duplicados', 'sensible' => true],
        'consentimientos.gestionar' => ['modulo' => 'huespedes', 'nombre' => 'Dar y retirar consentimientos', 'sensible' => true],
        'comunicaciones.ver'   => ['modulo' => 'huespedes', 'nombre' => 'Ver los mensajes enviados', 'sensible' => false],
        // Cambiar una plantilla cambia lo que se le dice a todo el mundo.
        'comunicaciones.plantillas' => ['modulo' => 'huespedes', 'nombre' => 'Editar plantillas y automatizaciones', 'sensible' => true],
        'comunicaciones.enviar' => ['modulo' => 'huespedes', 'nombre' => 'Mandar mensajes a mano', 'sensible' => false],
        // Mandarle algo a media base de datos de golpe no lo hace cualquiera.
        'campanas.gestionar'   => ['modulo' => 'huespedes', 'nombre' => 'Crear y lanzar campañas', 'sensible' => true],
        // Bajar un umbral asciende de golpe a media base de datos, y con
        // el nivel van los descuentos: es dinero.
        'niveles.gestionar'    => ['modulo' => 'huespedes', 'nombre' => 'Niveles, beneficios y referidos', 'sensible' => true],
        // Las PQR llevan plazo legal (Ley 1755/2015). Cualquiera puede
        // recoger una; contestarla en nombre del hotel, no.
        'pqr.ver'              => ['modulo' => 'huespedes', 'nombre' => 'Ver quejas y peticiones', 'sensible' => false],
        'pqr.gestionar'        => ['modulo' => 'huespedes', 'nombre' => 'Registrar y repartir PQR', 'sensible' => false],
        'pqr.responder'        => ['modulo' => 'huespedes', 'nombre' => 'Responder y cerrar PQR', 'sensible' => true],

        // ── Caja ────────────────────────────────────────────────────────
        'caja.ver'        => ['modulo' => 'caja', 'nombre' => 'Ver el turno', 'sensible' => false],
        'caja.abrir'      => ['modulo' => 'caja', 'nombre' => 'Abrir turno', 'sensible' => false],
        'caja.cerrar'     => ['modulo' => 'caja', 'nombre' => 'Cerrar turno y cuadrar', 'sensible' => true],
        'caja.movimiento' => ['modulo' => 'caja', 'nombre' => 'Entradas y salidas de efectivo', 'sensible' => false],
        'caja.arqueo'     => ['modulo' => 'caja', 'nombre' => 'Ver descuadres de otros turnos', 'sensible' => true],

        // ── Restaurante ─────────────────────────────────────────────────
        'pos.usar'          => ['modulo' => 'restaurante', 'nombre' => 'Usar el POS', 'sensible' => false],
        'pos.cobrar'        => ['modulo' => 'restaurante', 'nombre' => 'Cobrar comandas', 'sensible' => false],
        'pos.anular'        => ['modulo' => 'restaurante', 'nombre' => 'Anular comandas o líneas', 'sensible' => true],
        'pos.descuento'     => ['modulo' => 'restaurante', 'nombre' => 'Descuento en comanda', 'sensible' => true],
        'pos.mover'         => ['modulo' => 'restaurante', 'nombre' => 'Cambiar de mesa', 'sensible' => false],
        'cocina.ver'        => ['modulo' => 'restaurante', 'nombre' => 'Pantalla de cocina', 'sensible' => false],
        'cocina.marcar'     => ['modulo' => 'restaurante', 'nombre' => 'Marcar recibido, listo y entregado', 'sensible' => false],
        'barra.ver'         => ['modulo' => 'restaurante', 'nombre' => 'Pantalla de barra', 'sensible' => false],
        // Quien cobra la propina no la reparte: separación de funciones.
        'propinas.liquidar' => ['modulo' => 'restaurante', 'nombre' => 'Liquidar propinas', 'sensible' => true],

        // ── Housekeeping ────────────────────────────────────────────────
        'limpieza.ver'      => ['modulo' => 'housekeeping', 'nombre' => 'Tablero de limpieza', 'sensible' => false],
        'limpieza.trabajar' => ['modulo' => 'housekeeping', 'nombre' => 'Iniciar y finalizar tareas', 'sensible' => false],
        'limpieza.reportar' => ['modulo' => 'housekeeping', 'nombre' => 'Reportar incidencias', 'sensible' => false],
        'unidades.estado'   => ['modulo' => 'housekeeping', 'nombre' => 'Cambiar el estado de la cabaña', 'sensible' => false],
        'unidades.revisar'  => ['modulo' => 'housekeeping', 'nombre' => 'Revisión de inventario', 'sensible' => false],
        'unidades.foto'     => ['modulo' => 'housekeeping', 'nombre' => 'Subir fotos de evidencia', 'sensible' => false],
        // Separado de `unidades.foto` a propósito: hasta ahora quien limpiaba
        // podía publicar una foto en la web pública del hotel.
        'unidades.publicar' => ['modulo' => 'housekeeping', 'nombre' => 'Publicar fotos en la web', 'sensible' => true],

        // ── Mantenimiento ───────────────────────────────────────────────
        'mantenimiento.ver'       => ['modulo' => 'mantenimiento', 'nombre' => 'Ver órdenes', 'sensible' => false],
        'mantenimiento.crear'     => ['modulo' => 'mantenimiento', 'nombre' => 'Abrir órdenes', 'sensible' => false],
        'mantenimiento.trabajar'  => ['modulo' => 'mantenimiento', 'nombre' => 'Iniciar y resolver', 'sensible' => false],
        'mantenimiento.prioridad' => ['modulo' => 'mantenimiento', 'nombre' => 'Cambiar la prioridad', 'sensible' => false],
        'mantenimiento.asignar'   => ['modulo' => 'mantenimiento', 'nombre' => 'Repartir el trabajo', 'sensible' => false],
        // Quien arregla no se firma a sí mismo el visto bueno: el mismo
        // criterio que ya se usa en la inspección de limpieza.
        'mantenimiento.verificar' => ['modulo' => 'mantenimiento', 'nombre' => 'Dar por buena una reparación', 'sensible' => false],
        'mantenimiento.costos'    => ['modulo' => 'mantenimiento', 'nombre' => 'Ver costos y materiales', 'sensible' => true],
        'mantenimiento.planes'    => ['modulo' => 'mantenimiento', 'nombre' => 'Plan de preventivos', 'sensible' => false],
        'medidores.ver'           => ['modulo' => 'mantenimiento', 'nombre' => 'Ver medidores y consumos', 'sensible' => false],
        'medidores.leer'          => ['modulo' => 'mantenimiento', 'nombre' => 'Apuntar lecturas', 'sensible' => false],
        'activos.ver'             => ['modulo' => 'mantenimiento', 'nombre' => 'Ver el inventario de equipos', 'sensible' => false],
        // Separado de `ver` a propósito: el técnico consulta la ficha del
        // calentador todos los días, pero dar de baja un equipo borra su
        // historial de la vista y eso no lo hace cualquiera.
        'activos.gestionar'       => ['modulo' => 'mantenimiento', 'nombre' => 'Alta, baja y edición de equipos', 'sensible' => true],

        // ── Configuración ───────────────────────────────────────────────
        'tarifas.ver'             => ['modulo' => 'configuracion', 'nombre' => 'Ver tarifas y reglas', 'sensible' => false],
        'tarifas.editar'          => ['modulo' => 'configuracion', 'nombre' => 'Cambiar precios', 'sensible' => true],
        'tipos.gestionar'         => ['modulo' => 'configuracion', 'nombre' => 'Tipos de alojamiento', 'sensible' => false],
        'unidades.gestionar'      => ['modulo' => 'configuracion', 'nombre' => 'Alta y baja de cabañas', 'sensible' => false],
        'carta.gestionar'         => ['modulo' => 'configuracion', 'nombre' => 'Carta, productos y recetas', 'sensible' => false],
        'carta.disponible'        => ['modulo' => 'configuracion', 'nombre' => 'Marcar platos agotados', 'sensible' => false],
        'insumos.gestionar'       => ['modulo' => 'configuracion', 'nombre' => 'Insumos y escandallos', 'sensible' => false],
        'experiencias.gestionar'  => ['modulo' => 'configuracion', 'nombre' => 'Catálogo de experiencias', 'sensible' => false],
        'experiencias.vender'     => ['modulo' => 'configuracion', 'nombre' => 'Reservar experiencias', 'sensible' => false],
        'cupones.gestionar'       => ['modulo' => 'configuracion', 'nombre' => 'Crear y anular cupones', 'sensible' => true],
        'bonos.gestionar'         => ['modulo' => 'configuracion', 'nombre' => 'Emitir y anular bonos', 'sensible' => true],
        'canales.gestionar'       => ['modulo' => 'configuracion', 'nombre' => 'Conexiones con OTAs', 'sensible' => false],
        'traducciones.gestionar'  => ['modulo' => 'configuracion', 'nombre' => 'Textos de la web', 'sensible' => false],
        'servicios.gestionar'     => ['modulo' => 'configuracion', 'nombre' => 'Catálogo de servicios', 'sensible' => false],

        // ── Personal ────────────────────────────────────────────────────
        'personal.ver'        => ['modulo' => 'personal', 'nombre' => 'Ver fichas de empleados', 'sensible' => false],
        'personal.editar'     => ['modulo' => 'personal', 'nombre' => 'Alta, baja y contratos', 'sensible' => true],
        'personal.documentos' => ['modulo' => 'personal', 'nombre' => 'Documentos del empleado', 'sensible' => true],
        'turnos.ver'          => ['modulo' => 'personal', 'nombre' => 'Ver el cuadrante', 'sensible' => false],
        'turnos.editar'       => ['modulo' => 'personal', 'nombre' => 'Planificar turnos', 'sensible' => false],
        'ausencias.gestionar' => ['modulo' => 'personal', 'nombre' => 'Aprobar vacaciones y bajas', 'sensible' => true],
        'fichajes.ver'        => ['modulo' => 'personal', 'nombre' => 'Ver el registro horario', 'sensible' => true],
        'fichajes.corregir'   => ['modulo' => 'personal', 'nombre' => 'Añadir o anular fichajes', 'sensible' => true],
        'fichajes.pin'        => ['modulo' => 'personal', 'nombre' => 'Asignar PIN de fichaje', 'sensible' => false],

        // ── Informes y sistema ──────────────────────────────────────────
        'reportes.ocupacion'          => ['modulo' => 'sistema', 'nombre' => 'Informe de ocupación', 'sensible' => false],
        'reportes.ingresos'           => ['modulo' => 'sistema', 'nombre' => 'Informe de ingresos y márgenes', 'sensible' => true],
        'reportes.exportar'           => ['modulo' => 'sistema', 'nombre' => 'Descargar informes en CSV', 'sensible' => true],
        'facturas.ver'                => ['modulo' => 'sistema', 'nombre' => 'Ver facturas', 'sensible' => false],
        'facturas.emitir'             => ['modulo' => 'sistema', 'nombre' => 'Emitir factura electrónica', 'sensible' => true],
        'usuarios.gestionar'          => ['modulo' => 'sistema', 'nombre' => 'Crear usuarios y asignar perfiles', 'sensible' => true],
        'roles.gestionar'             => ['modulo' => 'sistema', 'nombre' => 'Crear perfiles y marcar permisos', 'sensible' => true],
        'administracion.ver'          => ['modulo' => 'sistema', 'nombre' => 'Pantalla de administración', 'sensible' => false],
        'administracion.integraciones' => ['modulo' => 'sistema', 'nombre' => 'Siigo, Wompi, correo y TPV', 'sensible' => true],
        'auditoria.ver'               => ['modulo' => 'sistema', 'nombre' => 'Quién hizo qué', 'sensible' => true],
    ];

    /** Clave del perfil que lo puede todo y no se puede editar. */
    public const ROL_TOTAL = 'gerencia';

    /**
     * Perfiles de partida.
     *
     * Solo se siembran la primera vez. Después mandan los datos: si gerencia
     * quita un permiso desde el panel, este fichero no se lo devuelve.
     *
     * @var array<string, array{nombre: string, descripcion: string, sistema: bool, permisos: list<string>|string}>
     */
    public const PERFILES = [
        'gerencia' => [
            'nombre'      => 'Gerencia',
            'descripcion' => 'Acceso total. No se puede modificar: es la salida de emergencia si un perfil se configura mal.',
            'sistema'     => true,
            'permisos'    => '*',
        ],

        'administrador' => [
            'nombre'      => 'Administrador',
            'descripcion' => 'Configura el sistema: tarifas, catálogos, canales y usuarios. No lleva el día a día.',
            'sistema'     => false,
            'permisos'    => [
                'reservas.ver', 'calendario.ver', 'disponibilidad.bloquear',
                'tarifas.ver', 'tarifas.editar', 'tipos.gestionar', 'unidades.gestionar',
                'carta.gestionar', 'carta.disponible', 'insumos.gestionar',
                'experiencias.gestionar', 'cupones.gestionar', 'bonos.gestionar',
                'canales.gestionar', 'traducciones.gestionar', 'servicios.gestionar',
                'unidades.publicar',
                'personal.ver', 'personal.editar', 'turnos.ver', 'turnos.editar',
                'ausencias.gestionar', 'fichajes.ver', 'fichajes.pin',
                'reportes.ocupacion', 'reportes.ingresos', 'reportes.exportar',
                'facturas.ver', 'usuarios.gestionar', 'administracion.ver',
            ],
        ],

        'recepcion' => [
            'nombre'      => 'Recepción',
            'descripcion' => 'Reservas, entradas y salidas, huéspedes y folios. Ve la ocupación, no los márgenes.',
            'sistema'     => false,
            'permisos'    => [
                'reservas.ver', 'reservas.crear', 'reservas.editar', 'reservas.confirmar',
                'reservas.cancelar', 'reservas.checkin', 'reservas.checkout',
                'calendario.ver', 'disponibilidad.bloquear',
                'folio.ver', 'folio.cargo', 'folio.pago', 'folio.descuento',
                'folio.cupon', 'folio.bono',
                'huespedes.ver', 'huespedes.editar',
                'registros.ver', 'registros.aprobar', 'registros.documentos', 'registros.reporte',
                'huespedes.sensibles', 'huespedes.fusionar', 'consentimientos.gestionar',
                'comunicaciones.ver', 'comunicaciones.enviar',
                'campanas.gestionar',
                'pqr.ver', 'pqr.gestionar', 'pqr.responder',
                'caja.ver', 'caja.abrir', 'caja.cerrar', 'caja.movimiento',
                'pos.usar', 'pos.cobrar', 'pos.mover',
                'limpieza.ver', 'limpieza.reportar', 'unidades.estado',
                'mantenimiento.ver', 'mantenimiento.crear', 'mantenimiento.prioridad',
                'mantenimiento.asignar', 'mantenimiento.verificar',
                'activos.ver', 'medidores.ver', 'medidores.leer',
                // Sin ver las tarifas no puede cotizar por teléfono.
                'tarifas.ver', 'experiencias.vender', 'turnos.ver',
                // Ocupación sí, ingresos no.
                'reportes.ocupacion',
                'facturas.ver', 'facturas.emitir',
            ],
        ],

        'caja' => [
            'nombre'      => 'Caja y contabilidad',
            'descripcion' => 'Turnos, arqueos, cobros, facturación e informes de ingresos. Liquida las propinas.',
            'sistema'     => false,
            'permisos'    => [
                'reservas.ver', 'calendario.ver',
                'folio.ver', 'folio.cargo', 'folio.pago', 'folio.descuento',
                'folio.descuento.sintope', 'folio.cupon', 'folio.bono',
                'huespedes.ver',
                'caja.ver', 'caja.abrir', 'caja.cerrar', 'caja.movimiento', 'caja.arqueo',
                'pos.cobrar',
                // Quien cobra la propina no la reparte.
                'propinas.liquidar', 'bonos.gestionar',
                'reportes.ocupacion', 'reportes.ingresos', 'reportes.exportar',
                'facturas.ver', 'facturas.emitir',
            ],
        ],

        'restaurante' => [
            'nombre'      => 'Restaurante',
            'descripcion' => 'Encargado de sala: comandas, cocina, cobro y anulaciones. Abre y cierra su turno de caja.',
            'sistema'     => false,
            'permisos'    => [
                'pos.usar', 'pos.cobrar', 'pos.anular', 'pos.descuento', 'pos.mover',
                'cocina.ver', 'cocina.marcar', 'barra.ver',
                // Su turno sí; los arqueos de otros, no.
                'caja.ver', 'caja.abrir', 'caja.cerrar',
                'carta.disponible',
                'mantenimiento.crear',
                // Sin ver las alergias, la cocina puede mandar a alguien al hospital.
                'huespedes.ver', 'huespedes.sensibles',
                'pqr.ver', 'pqr.gestionar',
                // El cuadrante lo necesita para organizar el servicio. Las horas
                // trabajadas son dato laboral y se quedan en gerencia.
                'turnos.ver',
            ],
        ],

        'housekeeping' => [
            'nombre'      => 'Housekeeping',
            'descripcion' => 'Tablero de limpieza, inspecciones y evidencias. Ve qué cabaña se libera, no quién duerme en ella.',
            'sistema'     => false,
            'permisos'    => [
                'calendario.ocupacion',
                'limpieza.ver', 'limpieza.trabajar', 'limpieza.reportar',
                'unidades.estado', 'unidades.revisar', 'unidades.foto',
                'mantenimiento.ver', 'mantenimiento.crear', 'activos.ver',
                'pqr.ver', 'pqr.gestionar',
                'turnos.ver',
            ],
        ],

        'mantenimiento' => [
            'nombre'      => 'Mantenimiento',
            'descripcion' => 'Órdenes, prioridades y cierre con fotos. Ve la ocupación para planificar, sin datos del huésped.',
            'sistema'     => false,
            'permisos'    => [
                'calendario.ocupacion',
                'limpieza.reportar',
                'unidades.estado', 'unidades.foto',
                'mantenimiento.ver', 'mantenimiento.crear',
                'mantenimiento.trabajar', 'mantenimiento.prioridad',
                'mantenimiento.asignar', 'mantenimiento.costos', 'mantenimiento.planes',
                'activos.ver', 'activos.gestionar',
                'medidores.ver', 'medidores.leer',
                'pqr.ver', 'pqr.gestionar',
                'turnos.ver',
            ],
        ],
    ];

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::PERMISOS);
    }

    /** @return list<string> */
    public static function sensibles(): array
    {
        return array_keys(array_filter(self::PERMISOS, static fn (array $p): bool => $p['sensible']));
    }

    public static function existe(string $clave): bool
    {
        return isset(self::PERMISOS[$clave]);
    }

    public static function esSensible(string $clave): bool
    {
        return self::PERMISOS[$clave]['sensible'] ?? false;
    }

    /**
     * Permisos de un perfil de partida, ya resuelto el comodín.
     *
     * @return list<string>
     */
    public static function permisosDe(string $perfil): array
    {
        $def = self::PERFILES[$perfil] ?? null;
        if ($def === null) {
            return [];
        }

        return $def['permisos'] === '*' ? self::claves() : $def['permisos'];
    }

    /**
     * Permisos agrupados por módulo, para pintar la pantalla de perfiles.
     *
     * @return array<string, array<string, array{modulo: string, nombre: string, sensible: bool}>>
     */
    public static function porModulo(): array
    {
        $agrupado = array_fill_keys(array_keys(self::MODULOS), []);

        foreach (self::PERMISOS as $clave => $datos) {
            $agrupado[$datos['modulo']][$clave] = $datos;
        }

        return $agrupado;
    }
}
