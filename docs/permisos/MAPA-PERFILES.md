# Mapa de perfiles y permisos · para revisar antes de tocar nada

Qué puede hacer cada perfil, acción por acción. Sacado del inventario real:
**296 rutas** del sistema, agrupadas en permisos.

Esto es un borrador para que lo revises y lo corrijas. **Nada de esto está
implementado todavía.**

---

## 1 · Cómo va a funcionar

Hoy el control es `usuarios.rol` (un ENUM de tres) y un filtro que mira la
**ruta completa**. Con once perfiles y matices como *«anulaciones»*,
*«inventario autorizado»* o *«consulta limitada de ocupación»*, eso no llega:
no es «puede entrar a esta pantalla», es «puede hacer esta acción».

El cambio es a tres tablas:

```
roles         (id, clave, nombre, descripcion, es_sistema)
permisos      (id, clave, modulo, nombre, descripcion, es_sensible)
rol_permisos  (rol_id, permiso_id)
usuarios.rol_id → roles.id
```

Y en las rutas, `rol:gerencia` pasa a ser `permiso:reservas.cancelar`.

**Tres decisiones de diseño:**

1. **Los roles son datos, no código.** Gerencia podrá crear «Recepción de fin de
   semana» sin que yo toque nada. Los permisos sí son código: cada uno
   corresponde a una acción que existe.
2. **Gerencia lo tiene todo, siempre, y no se puede editar.** Es la salida de
   emergencia: sin ella, un error marcando casillas te deja fuera de tu propio
   sistema sin manera de volver a entrar.
3. **Lo que no está permitido, se oculta.** El menú y los botones se pintan
   según los permisos. Un botón que da error al pulsarlo es peor que un botón
   que no está.

---

## 2 · Los perfiles

De tus once, **siete** se pueden montar ya con lo que el sistema hace hoy:

| Perfil | Clave | Qué es |
|---|---|---|
| **Gerencia** | `gerencia` | Todo. No editable |
| **Administrador** | `administrador` | Configura el sistema. No opera el día a día |
| **Recepción** | `recepcion` | Reservas, entradas y salidas, huéspedes, folios |
| **Caja / Contabilidad** | `caja` | Turnos, arqueos, cobros, facturación, informes |
| **Restaurante** | `restaurante` | Sala, comandas, cocina, cobro |
| **Housekeeping** | `housekeeping` | Tablero de limpieza, inspecciones, incidencias |
| **Mantenimiento** | `mantenimiento` | Órdenes, prioridades, cierre con fotos |

Los **cuatro restantes** no son un problema de permisos: **necesitan módulos que
no existen**. No tiene sentido crear un perfil vacío.

| Perfil | Qué faltaría construir |
|---|---|
| **Reservas / Comercial** | CRM entero: leads, cotizaciones con seguimiento, campañas, grupos |
| **Seguridad** | Módulo entero: visitantes, control de accesos, parte de incidencias |
| **Agencia / Empresa** | Cupos, tarifas negociadas, estados de cuenta, portal externo |
| **Huésped** | Hoy solo existe el registro por enlace. Falta cuenta, pagos, llave, pedidos |

---

## 3 · Catálogo de permisos

Columnas: **Ger**encia · **Adm**inistrador · **Rec**epción · **Caj**a ·
**Res**taurante · **Hsk** (housekeeping) · **Mto** (mantenimiento).

### 3.1 · Reservas y venta

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `reservas.ver` | Listado y ficha de reservas | ✅ | ✅ | ✅ | ✅ | — | — | — |
| `reservas.crear` | Crear reserva | ✅ | — | ✅ | — | — | — | — |
| `reservas.editar` | Cambiar fechas, cabaña, huéspedes | ✅ | — | ✅ | — | — | — | — |
| `reservas.confirmar` | Pasar de pendiente a confirmada | ✅ | — | ✅ | — | — | — | — |
| `reservas.cancelar` | ⚠️ Cancelar una reserva | ✅ | — | ✅ | — | — | — | — |
| `reservas.checkin` | Registrar la entrada | ✅ | — | ✅ | — | — | — | — |
| `reservas.checkout` | Registrar la salida | ✅ | — | ✅ | — | — | — | — |
| `calendario.ver` | Planning de ocupación | ✅ | ✅ | ✅ | ✅ | — | 🔸 | 🔸 |
| `disponibilidad.bloquear` | Bloquear fechas a mano | ✅ | ✅ | ✅ | — | — | — | — |

🔸 *Housekeeping y mantenimiento verían **solo la ocupación**, sin nombres ni
importes: necesitan saber qué cabaña se libera mañana, no quién duerme en ella.
Es tu «consulta limitada de ocupación».*

### 3.2 · Folio y cobros de la reserva

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `folio.ver` | Ver la cuenta de la reserva | ✅ | — | ✅ | ✅ | — | — | — |
| `folio.cargo` | Añadir un cargo | ✅ | — | ✅ | ✅ | — | — | — |
| `folio.pago` | Registrar un pago | ✅ | — | ✅ | ✅ | — | — | — |
| `folio.descuento` | ⚠️ Aplicar descuento | ✅ | — | 🔸 | ✅ | — | — | — |
| `folio.cupon` | Aplicar un cupón | ✅ | — | ✅ | ✅ | — | — | — |
| `folio.bono` | Usar saldo de un bono regalo | ✅ | — | ✅ | ✅ | — | — | — |

🔸 *Pendiente de decisión: ver §5, pregunta 2.*

### 3.3 · Huéspedes y registro en línea

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `huespedes.ver` | Fichas de huéspedes | ✅ | — | ✅ | ✅ | — | — | — |
| `huespedes.editar` | Crear y modificar | ✅ | — | ✅ | — | — | — | — |
| `huespedes.eliminar` | ⚠️ Borrar una ficha | ✅ | — | — | — | — | — | — |
| `registros.ver` | Cola de registros en línea | ✅ | — | ✅ | — | — | — | — |
| `registros.aprobar` | Aprobar o rechazar | ✅ | — | ✅ | — | — | — | — |
| `registros.documentos` | ⚠️ Ver cédulas y firmas | ✅ | — | ✅ | — | — | — | — |
| `registros.reporte` | Generar el reporte legal | ✅ | — | ✅ | — | — | — | — |

⚠️ *`registros.documentos` es el permiso más delicado del sistema: son
documentos de identidad y firmas, cubiertos por la Ley 1581/2012. Debería
tenerlo el menor número de personas posible.*

### 3.4 · Caja

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `caja.ver` | Estado del turno | ✅ | — | ✅ | ✅ | 🔸 | — | — |
| `caja.abrir` | Abrir turno | ✅ | — | ✅ | ✅ | ✅ | — | — |
| `caja.cerrar` | ⚠️ Cerrar turno y cuadrar | ✅ | — | ✅ | ✅ | 🔸 | — | — |
| `caja.movimiento` | Entradas y salidas de efectivo | ✅ | — | ✅ | ✅ | — | — | — |
| `caja.arqueo` | ⚠️ Ver descuadres de otros turnos | ✅ | — | — | ✅ | — | — | — |

🔸 *El encargado de sala abre y cierra **su** turno; los descuadres ajenos, no.*

### 3.5 · Restaurante

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `pos.usar` | Abrir el POS, tomar comandas | ✅ | — | ✅ | — | ✅ | — | — |
| `pos.cobrar` | Cobrar una comanda | ✅ | — | ✅ | ✅ | ✅ | — | — |
| `pos.anular` | ⚠️ Anular comanda o línea | ✅ | — | — | — | ✅ | — | — |
| `pos.descuento` | ⚠️ Descuento en comanda | ✅ | — | — | — | ✅ | — | — |
| `pos.mover` | Cambiar de mesa | ✅ | — | ✅ | — | ✅ | — | — |
| `cocina.ver` | Pantalla de cocina | ✅ | — | — | — | ✅ | — | — |
| `cocina.marcar` | Recibido / listo / entregado | ✅ | — | — | — | ✅ | — | — |
| `barra.ver` | Pantalla de barra | ✅ | — | — | — | ✅ | — | — |
| `propinas.liquidar` | ⚠️ Repartir y cerrar propinas | ✅ | — | — | ✅ | 🔸 | — | — |

### 3.6 · Housekeeping

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `limpieza.ver` | Tablero de limpieza | ✅ | — | ✅ | — | — | ✅ | — |
| `limpieza.trabajar` | Iniciar y finalizar tareas | ✅ | — | — | — | — | ✅ | — |
| `limpieza.reportar` | Reportar una incidencia | ✅ | — | ✅ | — | — | ✅ | ✅ |
| `unidades.estado` | Cambiar el estado de la cabaña | ✅ | — | ✅ | — | — | ✅ | ✅ |
| `unidades.revisar` | Revisión de inventario | ✅ | — | — | — | — | ✅ | — |
| `unidades.foto` | Subir foto de evidencia | ✅ | — | — | — | — | ✅ | ✅ |
| `unidades.publicar` | ⚠️ Publicar fotos en la web | ✅ | ✅ | — | — | — | — | — |

> **⚠️ Fallo real que sale de este mapa.** Hoy `unidades/foto/publicar` está en
> el grupo abierto a **todo el personal**. Es decir: quien limpia puede
> publicar una foto en la web pública del hotel. No es malicia, es que ese
> botón está donde no debería. Separar «subir evidencia» de «publicar en la
> web» arregla esto.

### 3.7 · Mantenimiento

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `mantenimiento.ver` | Listado de órdenes | ✅ | — | ✅ | — | — | ✅ | ✅ |
| `mantenimiento.crear` | Abrir una orden | ✅ | — | ✅ | — | ✅ | ✅ | ✅ |
| `mantenimiento.trabajar` | Iniciar y resolver | ✅ | — | — | — | — | — | ✅ |
| `mantenimiento.prioridad` | Cambiar la prioridad | ✅ | — | ✅ | — | — | — | ✅ |

### 3.8 · Configuración (el perfil Administrador)

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `tarifas.ver` | Temporadas, reglas, simulador | ✅ | ✅ | 🔸 | — | — | — | — |
| `tarifas.editar` | ⚠️ Cambiar precios | ✅ | ✅ | — | — | — | — | — |
| `tipos.gestionar` | Tipos de alojamiento | ✅ | ✅ | — | — | — | — | — |
| `unidades.gestionar` | Alta y baja de cabañas | ✅ | ✅ | — | — | — | — | — |
| `carta.gestionar` | Carta, productos, recetas | ✅ | ✅ | — | — | 🔸 | — | — |
| `carta.disponible` | Marcar un plato agotado | ✅ | ✅ | — | — | ✅ | — | — |
| `insumos.gestionar` | Insumos y escandallos | ✅ | ✅ | — | — | 🔸 | — | — |
| `experiencias.gestionar` | Catálogo de experiencias | ✅ | ✅ | — | — | — | — | — |
| `experiencias.vender` | Reservar una experiencia | ✅ | — | ✅ | — | — | — | — |
| `cupones.gestionar` | ⚠️ Crear y anular cupones | ✅ | ✅ | — | — | — | — | — |
| `bonos.gestionar` | ⚠️ Emitir y anular bonos | ✅ | ✅ | — | ✅ | — | — | — |
| `canales.gestionar` | Conexiones con OTAs | ✅ | ✅ | — | — | — | — | — |
| `traducciones.gestionar` | Textos de la web | ✅ | ✅ | — | — | — | — | — |
| `servicios.gestionar` | Catálogo de servicios | ✅ | ✅ | — | — | — | — | — |

### 3.9 · Personal

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `personal.ver` | Fichas de empleados | ✅ | ✅ | — | — | 🔸 | — | — |
| `personal.editar` | ⚠️ Alta, baja, contratos | ✅ | ✅ | — | — | — | — | — |
| `personal.documentos` | ⚠️ Documentos del empleado | ✅ | — | — | — | — | — | — |
| `turnos.ver` | Cuadrante semanal | ✅ | ✅ | ✅ | — | ✅ | ✅ | ✅ |
| `turnos.editar` | Planificar turnos | ✅ | ✅ | — | — | 🔸 | — | — |
| `ausencias.gestionar` | ⚠️ Aprobar vacaciones y bajas | ✅ | ✅ | — | — | — | — | — |
| `fichajes.ver` | ⚠️ Registro horario | ✅ | ✅ | — | — | 🔸 | — | — |
| `fichajes.corregir` | ⚠️ Añadir o anular fichajes | ✅ | — | — | — | — | — | — |
| `fichajes.pin` | Asignar PIN de fichaje | ✅ | ✅ | — | — | — | — | — |

### 3.10 · Dinero, informes y sistema

| Permiso | Qué permite | Ger | Adm | Rec | Caj | Res | Hsk | Mto |
|---|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|
| `reportes.ver` | ⚠️ Ingresos, ocupación, márgenes | ✅ | ✅ | 🔸 | ✅ | — | — | — |
| `reportes.exportar` | ⚠️ Descargar en CSV | ✅ | ✅ | — | ✅ | — | — | — |
| `facturas.ver` | Facturas emitidas | ✅ | ✅ | ✅ | ✅ | — | — | — |
| `facturas.emitir` | ⚠️ Emitir factura electrónica | ✅ | — | ✅ | ✅ | — | — | — |
| `usuarios.gestionar` | ⚠️ Crear usuarios y asignar roles | ✅ | ✅ | — | — | — | — | — |
| `roles.gestionar` | ⚠️ Crear perfiles y marcar permisos | ✅ | — | — | — | — | — | — |
| `administracion.ver` | Pantalla de administración | ✅ | ✅ | — | — | — | — | — |
| `administracion.integraciones` | ⚠️ Siigo, Wompi, correo, TPV | ✅ | — | — | — | — | — | — |
| `auditoria.ver` | ⚠️ Quién hizo qué | ✅ | — | — | — | — | — | — |

**Total: 82 permisos en 10 módulos**, 26 de ellos sensibles.

*(El catálogo definitivo, ya implementado, está en `app/Libraries/Permisos/Catalogo.php`. Sube de 68 a 82 por el desglose de la decisión 1 y 2: reportes partidos en ocupación e ingresos, y el descuento con y sin tope.)*

Los marcados **⚠️** son *sensibles*: mueven dinero, tocan datos personales o
cambian la configuración. Van marcados en la tabla `permisos` para que al
asignarlos el panel avise, y para que queden siempre en la auditoría.

---

## 4 · La decisión grande: las dos identidades

Ahora mismo hay **dos formas de entrar al sistema**, y no se hablan:

| | **Usuarios** | **Empleados** |
|---|---|---|
| Entran con | correo y contraseña | documento y PIN de fichaje |
| Tienen | `usuarios.rol` (3 valores) | `empleados.cargo` (texto libre) |
| Acceden a | el panel completo | fichaje, comandero, TPV compartido |
| Hoy hay | 2 | los del hotel |

Tu tabla pone «Restaurante» como un perfil más, pero **en el sistema los
camareros no son usuarios**: entran con PIN al comandero, sin cuenta. Hay que
decidir, y no es una decisión pequeña:

**Opción A · Unificar.** Todo el mundo es un usuario con rol. El camarero
tendría cuenta y podría entrar al panel con lo que su perfil le permita. El PIN
seguiría existiendo para el TPV compartido y el fichaje, pero como un atajo de
la misma persona, no como otra identidad.
*A favor:* un solo sitio donde dar y quitar permisos; auditoría de verdad
(«quién anuló esa comanda» tiene una respuesta). *En contra:* más trabajo, y
hay que darle cuenta y contraseña a gente que hoy solo necesita cuatro números.

**Opción B · Dejarlas separadas.** Los usuarios llevan el panel; los empleados
llevan sala y cocina. El perfil «Restaurante» sería para el **encargado**, que
sí necesita panel.
*A favor:* mucho menos trabajo, y no obliga a un camarero a recordar una
contraseña. *En contra:* dos sitios donde mirar quién puede qué, y la auditoría
del TPV se queda como está.

**Mi recomendación: la B, por ahora.** El hotel aún no ha abierto y no sabemos
cuánta gente habrá. La A se puede hacer más adelante sin tirar nada de esto: los
permisos son los mismos, cambia solo quién los lleva colgados.

---

## 5 · Decisiones · resueltas el 27-07-2026

Las 🔸 del catálogo quedan así. Las cuatro primeras las decidió Javier; las
cuatro últimas las decidí yo con su encargo de *«lo que sea más conveniente»*,
y quedan escritas aquí para que se puedan cambiar viéndolas.

| # | Decisión | Cómo queda |
|---|---|---|
| 1 | Reportes de recepción | **Solo ocupación.** Recepción no ve ingresos ni márgenes. Se parte `reportes.ver` en dos: `reportes.ocupacion` y `reportes.ingresos` |
| 2 | Descuentos de recepción | **Sí, con tope configurable.** Nuevo permiso `folio.descuento.sintope` para quien pueda saltárselo |
| 3 | Liquidación de propinas | **Caja y gerencia. El restaurante no.** Quien cobra la propina no la reparte: es la separación de funciones de toda la vida, y evita una conversación desagradable el día que alguien sospeche |
| 4 | Datos del huésped para housekeeping | **Solo la ocupación.** «Cabaña 3, sale mañana». Sin nombre, sin importe, sin teléfono |
| 5 | Recepción y las tarifas | **Las ve, no las toca.** Sin verlas no puede cotizar por teléfono, y cotizar a ojo sale más caro que el riesgo de que las mire |
| 6 | Encargado de sala y su equipo | **Ve turnos, no ve fichajes.** El cuadrante lo necesita para organizar el servicio; las horas trabajadas son dato laboral y se quedan en gerencia y administración |
| 7 | Turno de caja del restaurante | **Abre y cierra el suyo.** El restaurante cierra a otra hora que recepción, y hacerle esperar no aporta control. Los arqueos ajenos, no |
| 8 | Unificar usuarios y empleados | **No, todavía no.** Ver §4. Se puede hacer más adelante sin tirar nada de esto |

### Lo que cambia en el catálogo

- `reportes.ver` → se parte en **`reportes.ocupacion`** (Ger, Adm, Rec, Caj) y
  **`reportes.ingresos`** (Ger, Adm, Caj).
- Nuevo **`folio.descuento.sintope`** (Ger, Caj). Sin él, el descuento queda
  limitado por el tope de `configuracion.descuento_tope`.
- `propinas.liquidar` → Ger y **Caj**. Se le quita al restaurante.
- `tarifas.ver` → se le añade **Rec**.
- `turnos.ver` mantiene a Res; **`fichajes.ver` se le quita**.
- `caja.abrir` y `caja.cerrar` mantienen a Res; `caja.arqueo` no.

**Total: 82 permisos.**

---

## 6 · Qué habría que tocar

| Paso | Qué |
|---|---|
| 1 | Migración: `roles`, `permisos`, `rol_permisos`, `usuarios.rol_id`. **Traduciendo los 3 roles actuales** para que nadie pierda el acceso |
| 2 | `Permisos`: librería que responde `puede('reservas.cancelar')` |
| 3 | Filtro `permiso:` en sustitución de `rol:`, ruta por ruta (296) |
| 4 | Menú y botones que se pintan según permisos |
| 5 | Pantalla de perfiles: crear roles y marcar casillas |
| 6 | Auditoría de las acciones ⚠️ |
| 7 | Pruebas: que cada perfil llega a lo suyo y **rebota** en lo ajeno |

**Riesgo principal:** el paso 3 toca 296 rutas. Un despiste ahí deja a alguien
fuera de su trabajo, o —peor— abre algo que debía estar cerrado. Por eso el
paso 7 no es opcional: una prueba automática por perfil que compruebe las dos
caras, lo que sí y lo que no.

**El paso 1 es reversible; el 3 no del todo.** Conviene tener las respuestas
del §5 antes de empezar el 3.

---

*Borrador del 27-07-2026. Inventario: 296 rutas. Nada implementado.*

---

## 7 · Estado de la implementación · 27-07-2026

| Paso | Estado |
|---|---|
| 1 · Tablas `roles`, `permisos`, `rol_permisos`, `usuarios.rol_id` | ✅ hecho |
| 2 · Motor de permisos: `Catalogo`, `Permisos`, helper `puede()` | ✅ hecho |
| 3 · Cambiar el filtro en las rutas | ✅ hecho · 251 rutas con `permiso:`, 0 con `rol:` |
| 4 · Menú según permisos | ✅ hecho · el layout ya no mira el rol |
| 5 · Pantalla de perfiles y alta de usuarios | ✅ hecho |
| 6 · Auditoría de las acciones sensibles | ⬜ pendiente |
| 7 · Pruebas por perfil | ✅ 131 pruebas en total |

**Los pasos 1 y 2 no cambian el comportamiento de nada.** La columna `rol`
antigua sigue en su sitio, las 296 rutas siguen usando el filtro `rol:`, y a
cada usuario se le asignó su perfil equivalente al migrar. Todo lo nuevo está
al lado, esperando a que el paso 3 se haga ruta por ruta.

Comprobado tras migrar: los 7 perfiles sembrados, los 82 permisos en la tabla,
los 2 usuarios enlazados a `gerencia`, el tope de descuento en 15 %, y las
pantallas de siempre respondiendo 200.

```bash
php vendor/bin/phpunit tests/unit/ --no-coverage
```

### Comprobado tras el paso 3

- **333 rutas antes, 333 después.** Comparado el inventario de `spark routes`
  entero: ni una perdida, ni una añadida.
- **251 rutas con `permiso:`**, 0 con `rol:`.
- **3 rutas solo con sesión**: `panel`, `perfil` y `perfil/clave`. A propósito:
  son de cualquiera que entre, y dejar a alguien sin ningún sitio al que ir lo
  mandaría al login en bucle.
- **78 sin sesión**: la web pública en cuatro idiomas, el registro por enlace,
  el iCal, el fichaje y el comandero (esos van por PIN, no por usuario).
- Ninguna ruta quedó con `permiso:` pero sin `auth`.

### Un fallo que apareció de paso

La migración `2026-08-05-090000_TpvCompartido` **no soporta `migrate:refresh`**:
su `down()` no deshace lo que hace `up()`, y al reconstruir el esquema desde
cero se queda a medias. No afecta a producción —allí solo se migra hacia
adelante— pero impide reconstruir la base de pruebas de un tirón. Queda anotado.

Se quitaron además `tests/database/ExampleDatabaseTest.php` y
`tests/session/ExampleSessionTest.php`: son los de fábrica de CodeIgniter, no
prueban nada de este sistema, y su `$seed` forzaba justo ese refresh.

### Paso 5 · qué se puede hacer ya

- **Gestión → Perfiles de acceso**: una tarjeta por perfil con cuántos permisos
  tiene y a cuánta gente afecta. Gerencia sale marcada como intocable.
- **Configurar un perfil**: las 82 casillas agrupadas en los 10 módulos, con
  «marcar todo el bloque» para no morir a clics, y el triángulo de aviso en los
  26 sensibles. Al abrir un perfil se avisa a quién afecta el cambio.
- **Crear un perfil nuevo** partiendo de otro que ya existe. La clave interna se
  deriva del nombre, sin tildes ni espacios.
- **Usuarios**: el formulario ya pide **perfil**, no el rol de tres valores.

Lo que ya no decide nada: `usuarios.rol`. Se sigue rellenando porque la columna
es NOT NULL, pero no se consulta en ningún sitio para decidir. Los seis puntos
que aún miraban el rol —el panel de inicio, la ficha de cabañas, el TPV, el
destino tras iniciar sesión— pasaron a preguntar por permisos.

Los cambios de permisos **surten efecto en el siguiente clic**, sin volver a
entrar: los permisos se consultan en cada petición y no se guardan en la sesión.
Hay una prueba que lo comprueba.
