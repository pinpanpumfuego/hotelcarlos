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
- [ ] Página "Mi perfil" para que cada usuario cambie su propia contraseña
- [ ] Caja por turnos y arqueo (siguiente pieza financiera tras el folio)

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
