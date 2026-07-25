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
- [ ] Calendario visual de disponibilidad (vista cinta)
- [ ] Autenticación y roles (gerencia, recepción, limpieza...)
- [ ] Folio del huésped y caja

## Fases siguientes (según propuesta)

2. Venta digital: web pública, motor de reservas, pagos Wompi, autocheck-in
3. Operación interna: housekeeping, mantenimiento, lavandería
4. Alimentos y bebidas: POS restaurante, KDS, inventarios
5. Automatización: cerraduras, facturación electrónica, TRA, channel manager, CRM
6. Optimización: indicadores avanzados, revenue, fidelización
