# Hotel Carlos — PMS para hotel rural (Colombia)

Plataforma integral de gestión hotelera basada en la propuesta funcional de
`docs/Propuesta_Plataforma_Gestion_Hotel_Rural_Colombia.pdf`. Responder siempre en español.

## Stack

- CodeIgniter 4.7 + PHP 8.2 (XAMPP en `C:\xamppum`, el proyecto vive en `C:\xamppum\htdocs\hotelcarlos`)
- MariaDB 10.4, base de datos `hotelcarlos` (usuario `root` sin contraseña en local)
- Bootstrap 5 + Bootstrap Icons por CDN
- URL local: http://localhost/hotelcarlos (el `.htaccess` raíz redirige a `public/`)

## Convenciones

- Código, tablas, rutas y vistas en **español** (`unidades`, `huespedes`, `reservas`...)
- Migraciones en `app/Database/Migrations/`; ejecutar con `php spark migrate`
- Datos de ejemplo: `php spark db:seed DatosIniciales`
- Vistas extienden `layouts/panel.php`; mensajes flash `ok` / `error`; validación en los modelos
- Moneda: pesos colombianos (COP); zona horaria `America/Bogota`

## Estado actual (Fase 1 — núcleo operativo)

- [x] Esqueleto CI4, base de datos, migración núcleo (tipos_unidad, unidades, huespedes, reservas)
- [x] Panel de control con estado de unidades
- [x] CRUD de tipos de alojamiento y unidades
- [x] CRUD de huéspedes (con búsqueda por nombre/documento)
- [x] Reservas: creación con control anti-sobreventa (`ReservaModel::unidadDisponible`),
      total automático (noches × tarifa), check-in/out que actualiza el estado de la unidad, cancelación
- [x] Calendario visual de disponibilidad (vista cinta, 21 días, clic en casilla libre = reserva prefijada)
- [x] Autenticación con sesiones: login con throttling (5 intentos/min), CSRF global,
      cabeceras seguras, roles gerencia/recepcion/limpieza aplicados en rutas y menú,
      CRUD de usuarios (solo gerencia). Admin inicial: seeder `UsuarioAdmin`
      (admin@hotelcarlos.local / Admin2026! — cambiar en producción)
- [x] Folio del huésped (`folio_movimientos`, `FolioModel`): cargo de alojamiento automático,
      cargos manuales, pagos por método (efectivo/tarjeta/transferencia/wompi), saldo en vivo;
      el check-out se bloquea si el saldo no es cero. Ficha de reserva completa en `reservas/ver/:id`.
- [x] Flujo de estados completo: pendiente → confirmar (recepción) → check-in → check-out;
      dashboard operativo con ocupación, llegadas/salidas de hoy, reservas web por confirmar,
      ingresos cobrados del mes y estado de cabañas en vivo.
- [x] Rediseño visual del panel: verde bosque de la marca, Inter, sidebar con grupos por rol,
      KPIs con iconos, enlace a la web pública.
- [x] Tablero de limpieza (`Limpieza` controller, tabla `limpiezas`): tarjetas por cabaña con
      acción según estado (empezar/terminar con novedades/enviar a limpieza), historial con
      duración y quién limpió. El rol limpieza entra directo a /limpieza al hacer login y
      no ve dinero ni reservas pendientes en el dashboard.
- [x] Reportes gerenciales (`Reportes`, solo gerencia): filtro por periodo (máx 92 días),
      ocupación %, cobrado/facturado, ADR y RevPAR con su fórmula visible, gráficas Chart.js
      (ocupación por día e ingresos por día), pagos por método, export CSV para Excel.
- [x] "Mi perfil" (`Perfil`): cambio de contraseña propio con verificación de la actual.
      El nombre de usuario de la barra superior enlaza al perfil.
- [x] Módulo Administración (`Administracion`, solo gerencia): configuración clave-valor en BD
      (`configuracion`, `ConfiguracionModel`) con **cifrado** para secretos (contraseña SMTP,
      llave privada y secreto de integridad Wompi; requiere `encryption.key` en .env).
      Tres bloques: datos del hotel (pisan a `Config\Hotel` vía su constructor), correo SMTP
      con botón de envío de prueba, y credenciales Wompi (guardadas para la futura
      integración de pago). Los secretos nunca se re-muestran: badge "Guardada" + en blanco = conservar.
- [x] Caja por turnos (`Caja`, gerencia+recepción; tablas `caja_turnos` y `caja_movimientos`):
      apertura con base, movimientos manuales, **los pagos en efectivo del folio entran solos
      al turno abierto** (aviso si no hay turno), cierre con arqueo (contado vs esperado,
      faltante/sobrante en historial). Un solo turno abierto a la vez.
- [x] Mantenimiento (`Mantenimiento`, todo el personal; tabla `mantenimientos`): reporte de
      incidencias por cabaña o zona común con prioridad, opción de **bloquear la cabaña**
      (deja de venderse en la web al instante), flujo abierta → en proceso → resuelta con
      registro de quién y qué se hizo. Al resolver, la cabaña bloqueada pasa a limpieza.
- [x] Pulido general: layout del panel responsive de verdad (menú fijo en escritorio, offcanvas
      en móvil, sin scroll horizontal), menú generado desde un array por rol, paginación
      Bootstrap propia (`app/Views/paginacion/bootstrap.php`, alias `bootstrap` en Config\Pager),
      filtros + buscador + paginación en Reservas y Huéspedes, estados vacíos con icono,
      columnas que se ocultan en móvil y vista de Cabañas coherente con el rol.
- [x] **TPV del restaurante** (`Tpv` operación, `Carta` gerencia; tablas `carta_categorias`,
      `carta_productos`, `comandas`, `comanda_lineas`): carta configurable con categorías,
      precios y disponibilidad (agotado); comandas por mesa o ligadas a un huésped **alojado**;
      pantalla táctil con carta por pestañas y cuenta en vivo; cobro por efectivo (entra a la
      **caja** del turno), tarjeta/transferencia/wompi, o **cargar a la cabaña** (cargo al
      **folio**, se paga en el check-out); anulación con motivo; pantalla de **cocina** con
      líneas pendientes, antigüedad por color y marcar "listo". Seeder `CartaEjemplo`.
- [x] **TPV táctil a pantalla completa** (`Pos` + `app/Views/pos/index.php`, ruta `/pos`):
      interfaz oscura sin sidebar, botones ≥60px, mapa de **mesas** por zonas (tabla `mesas`)
      con color según estado, tiempo abierto e importe; carta por categorías con color;
      ticket lateral con línea seleccionable y acciones (−, +, cantidad por teclado numérico,
      nota a cocina); **enviar a cocina** separado del cobro (líneas ya enviadas no se borran);
      descuento; anulación con motivo obligatorio; **pantalla de cobro** con formas de pago,
      importes rápidos de efectivo y **cálculo de cambio**; comandas "para llevar" y ligadas a
      huésped alojado. Todo por API JSON (`/pos/api/*`) sin recargar; el filtro `tokenjson`
      devuelve el CSRF renovado en la cabecera `X-CSRF-TOKEN`.
      **Ojo al ampliar tablas:** añadir siempre los campos nuevos a `$allowedFields` del modelo
      (CI4 los descarta en silencio si no están) — ya pasó con descuento/mesa_id/recibido.
- [x] TPV avanzado: **propina**, **división de cuenta con pagos parciales** (tabla `comanda_pagos`:
      varias formas de pago por comanda; la comanda se cierra sola cuando lo cobrado cubre el total)
      y **recibo imprimible** en formato 80 mm (`/pos/recibo/:id`, `?auto=1` lanza la impresión).
- [x] **Circuito TPV ↔ cocina completo.** Estados de cada línea: `nuevo` (aún no enviado) →
      `en_cocina` (`enviado_cocina=1`) → `listo` (`entregado=1`, cocina terminó) → `servido`
      (`servido=1`, el mesero lo llevó). Pantalla de cocina propia (`Cocina`, ruta `/cocina`,
      vista `cocina/index.php`): se refresca sola cada 10 s, solo muestra lo **ya enviado**,
      reloj desde el envío con color por antigüedad (verde/ámbar/rojo con parpadeo a los 20 min),
      pitido al entrar comanda nueva, botón por plato y "Comanda completa". El TPV consulta cada
      15 s y avisa ("Cocina terminó un plato"), pinta la línea en verde con "¡Listo para servir!"
      y ofrece el botón "Marcar como servido"; insignia de platos listos en la cabecera.
      **Fallo corregido:** antes cocina veía los platos antes de que el mesero pulsara "Enviar".
- [ ] Notificaciones por correo (confirmación de reserva web) — cuando haya SMTP real en Administración
- [ ] Pago online Wompi en el motor de reservas — cuando haya credenciales reales en Administración

## Decisión de arquitectura: local-first + nube

El PMS operativo corre en un **servidor local del hotel** (LAN, sin depender de internet:
recepción/limpieza/restaurante siguen funcionando si se corta la conexión). La **nube** aloja
la web pública + motor de reservas online (sigue vendiendo aunque el hotel esté offline),
las copias de seguridad y el acceso remoto. Un **servicio de sincronización con colas**
(reintentos + idempotencia) intercambia cambios; las reservas online entran como "pendientes"
y una bandeja de excepciones resuelve cruces. Implicación para todo el código nuevo:
códigos únicos, timestamps, operaciones idempotentes, nunca asumir conexión permanente.

## Fase 2 (venta digital) — en curso

- [x] Web pública "escaparate" (`Web` controller, vistas `web/*`, layout `layouts/web.php`):
      inicio, alojamientos (tarifas leídas de la BD) y contacto, con botón de reserva por WhatsApp.
      La raíz `/` es la web pública; el panel vive en `/panel`. Marca y contactos centralizados
      en `app/Config/Hotel.php`. Nombre confirmado por Javier: **San Antonio de los Lagos Ecolodge**;
      los alojamientos reales son **cabañas** (triangulares con techo de paja, junto al lago — ver
      video en `public/assets/video/`). Teléfono/dirección aún provisionales.
- [ ] Desplegar en dinahosting (hosting compartido "Multihosting", dominio sanantoniodeloslagos.com,
      contratado 2026-07-25). Método: clave SSH generada en el PC local, pública pegada en el panel
      de dinahosting; nunca pedir contraseñas por chat.
- [x] Motor de reservas online (`Reservar` controller): fechas/personas → disponibilidad real
      por tipo → datos del huésped → crea reserva `pendiente` con código y asignación automática
      de cabaña. Antispam (honeypot + throttle 5/min/IP), re-chequeo anti-carrera, PRG.
      Recepción confirma desde el panel. Los CTA de la web apuntan a /reservar (WhatsApp quedó
      como canal secundario en contacto).
- [x] Inventario real: 1 tipo "Cabaña" × 7 unidades (seeder `InventarioReal`);
      capacidad 4 y tarifa 350.000 COP son PROVISIONALES, confirmar con Javier.
- [ ] Pago online con Wompi sobre el motor de reservas
- [ ] Aviso por correo al hotel/huésped cuando entra una reserva web (necesita SMTP)
- [ ] Autocheck-in y portal del huésped

## Fases siguientes (según propuesta)
3. Operación interna: housekeeping, mantenimiento, lavandería
4. Alimentos y bebidas: POS restaurante, KDS, inventarios
5. Automatización: cerraduras, facturación electrónica, TRA, channel manager, CRM
6. Optimización: indicadores avanzados, revenue, fidelización
