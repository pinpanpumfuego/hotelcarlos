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

**Total: 68 permisos en 10 módulos.**

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

## 5 · Lo que necesito que decidas

Son las casillas que he marcado 🔸 porque dependen de cómo quieras llevar el
hotel, no de cómo esté hecho el software.

1. **¿Recepción ve los reportes de ingresos y márgenes?** Ver la ocupación es
   necesario para su trabajo; ver el margen de cada cabaña, no tanto.
2. **¿Recepción puede aplicar descuentos en el folio?** Y si sí, ¿con tope? Sin
   tope, un descuento del 100 % es un cobro que desaparece.
3. **¿El encargado de sala cierra su propio turno de caja, o lo cierra caja?**
4. **¿Quién liquida las propinas?** Que las reparta quien las cobra tiene un
   conflicto de interés evidente.
5. **¿Recepción ve las tarifas y las reglas de precio, aunque no pueda
   cambiarlas?** Ayuda a cotizar por teléfono.
6. **¿El encargado de restaurante ve fichajes y turnos de su equipo?** Es lo
   normal en un restaurante, pero son datos laborales.
7. **¿Housekeeping y mantenimiento pueden ver el nombre del huésped, o solo
   «cabaña 3, sale mañana»?** Mi propuesta es lo segundo.
8. **¿Unificamos usuarios y empleados (§4)?** Mi recomendación: no, todavía no.

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
