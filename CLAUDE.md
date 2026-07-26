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
- [x] **Destino por producto** (`carta_productos.destino`, copiado a `comanda_lineas.destino`):
      `cocina` (va a la pantalla de cocina), `barra` (va también pero etiquetado en azul) y
      `directo` (cerveza, agua: **no pasa por preparación**; al enviar queda entregado y servido
      automáticamente). Si lo nuevo es todo directo, el botón del TPV cambia a "Marcar entregado".
      Gestión de carta ampliada: destino por producto, categorías editables con color y orden,
      y contador de productos por categoría.
- [x] **Pantalla de barra propia** (`/barra`, misma vista que cocina con `zona`): cocina y barra
      ven solo lo suyo y se enlazan entre sí. **El cajero puede servir las bebidas de barra
      directamente desde el TPV** (botón "Preparada y servida" → marca entregado + servido de una
      vez), sin depender de que alguien atienda la pantalla de barra. Los platos de **cocina**
      siguen exigiendo que cocina los marque listos: es el control de que el plato existe.
- [x] **Gestión detallada de platos** (4 bloques, todos en la ficha `carta/ficha/:id`):
      1. **Ficha técnica**: dietas (vegano/vegetariano/sin gluten/sin lactosa), nivel de picante
         y alérgenos declarables. Visibles en la carta del panel, en el TPV (iconos + aviso al
         personalizar), en la comanda de cocina y en la **carta pública** (`/restaurante`).
      2. **Modificadores** (`modificador_grupos`, `modificadores`, `producto_modificador_grupos`,
         `comanda_linea_modificadores`): grupos tipo "elegir uno/varios", obligatorios opcionales,
         con precio extra. Al tocar un producto con grupos, el TPV abre el paso de personalización;
         el precio se recalcula y cocina recibe los modificadores elegidos.
      3. **Escandallo** (`insumos`, `receta_lineas`): coste por unidad de cada insumo, receta por
         plato y cálculo de coste, margen y **food cost** (verde <30 %, ámbar 30-35 %, rojo >35 %).
         Al cambiar el coste de un insumo se recalculan todos los platos.
      4. **Divisibles**: productos marcados `divisible` se pueden pedir mitad y mitad;
         **se cobra la mitad más cara** y la línea queda como "½ A + ½ B".
      Seeder `EscandalloEjemplo` con insumos, grupos de modificadores y la receta del sancocho.
- [x] **Subrecetas / preparaciones** (`insumos.es_preparacion` + `rendimiento`, tabla
      `preparacion_lineas`, `PreparacionModel`, controlador `Preparaciones`, ruta `/preparaciones`):
      una preparación (hogao, masa) es un insumo con receta propia y rendimiento; su coste por
      unidad se **calcula** (coste de la tanda ÷ rendimiento) y se propaga en cadena a las
      preparaciones y platos que la usan. `recalcularCostes()` itera hasta converger y se dispara
      al cambiar un insumo o una preparación. **Ciclos bloqueados** en la interfaz (no se ofrecen
      candidatos que dependan de la actual) y en el servidor (`generariaCiclo`).
      Verificado: tomate $8→$12/g ⇒ hogao $4→$7/g ⇒ sancocho $10.070→$10.230.
- [x] **Segundo repaso de usabilidad** (auditoría de las 45 vistas): formularios antiguos rehechos
      (tipos, unidades, huéspedes, usuarios, reservas) con enlace de vuelta, textos de ayuda bajo
      cada campo, selección de estado y rol como tarjetas en vez de desplegables, y barra de acciones
      en el pie de la tarjeta. Partial reutilizable `partes/errores.php`. Columnas secundarias
      ocultas en móvil en caja, usuarios, mantenimiento, restaurante y preparaciones. Estado vacío
      en el calendario con acción directa. Eliminados `welcome_message.php` y el controlador `Home`
      (código muerto de la instalación de CI4).
      Verificado: 27 rutas del panel y 5 públicas responden 200, y 10 pantallas sin desbordamiento a 375 px.
- [x] **Rediseño visual del panel**: sistema de diseño con variables (paleta de marca, tres niveles
      de sombra, radios y escala tipográfica), **Fraunces en los títulos** igual que la web pública e
      Inter en el texto. Menú lateral con degradado, marca dorada en la sección activa, animación al
      pasar el ratón e **insignia de registros por revisar**. Barra superior translúcida con avatar
      de iniciales. Tablas, botones, formularios, avisos y paginación con estilo propio.
      Panel de control rehecho: saludo según la hora, KPIs con tipografía editorial, **gráfica de
      ocupación de 14 días** (Chart.js con degradado y tooltip en %), lista de cabañas legible de un
      vistazo y estados vacíos ilustrados. Verificado en 11 pantallas a 1280 px y en móvil.
- [x] **Menú reorganizado en grupos plegables**: de 22 enlaces sueltos en 3 grupos a 5 áreas
      (Recepción · Restaurante · Operación · Equipo · Gerencia) que se pliegan; se abre solo el
      grupo de la sección en la que estás (en el Panel, el primero). El restaurante reúne ahora
      operación y configuración de carta en un solo sitio. Los avisos pendientes se suman en la
      cabecera del grupo para verlos aunque esté plegado. IDs únicos por instancia para que el
      menú fijo y el móvil no colisionen.
- [x] **Módulo de personal** (`Personal` y `Turnos`; tablas `empleados`, `turnos`, `ausencias`,
      `empleado_documentos`): fichas con datos personales, laborales y **seguridad social colombiana**
      (EPS, ARL, fondo de pensiones, caja de compensación, banco), contacto de emergencia, antigüedad
      calculada, vínculo opcional con una cuenta de `usuarios` y baja que conserva el historial.
      **Cuadrante semanal de turnos** con plantillas (mañana/tarde/noche/partido), horas por persona,
      copia de una semana a la siguiente y **detección de solapes**. **Ausencias** (vacaciones,
      incapacidad, permiso, licencia, falta) con aprobación; una vez aprobadas **bloquean esos días
      en el cuadrante**. Documentos laborales con el mismo almacenamiento seguro que los del huésped.
      **Fuera de alcance a propósito: nómina** — la liquidación va en el software contable.
- [x] **Facturación electrónica con Siigo** (`App\Libraries\Siigo`, controlador `Facturas`,
      tablas `facturas` y `factura_lineas`). Proveedor: **Siigo**, autorizado por la DIAN.
      **API verificada en la documentación oficial:** auth `POST https://api.siigo.com/auth`
      con `{username, access_key}` + cabecera `Partner-Id` → `access_token` válido 86400 s
      (se cachea en `configuracion` y se renueva con 5 min de margen; reintento único ante 401);
      facturas en `POST /v1/invoices`; correo en `POST /v1/invoices/{id}/mail`;
      catálogos en `/v1/document-types?type=FV`, `/v1/payment-types`, `/v1/users`, `/v1/taxes`.
      Credenciales cifradas en Administración, con **prueba de conexión que lista los catálogos**
      de la cuenta para copiar los identificadores. La factura sale del **folio** de una reserva
      o de una **comanda**, con pantalla de revisión previa (la emisión es siempre manual: tiene
      efectos fiscales). Se guarda copia local con número, **CUFE** y enlace público.
      **Pendiente de probar contra una cuenta real:** el cuerpo exacto de `/v1/invoices` está
      implementado según la estructura documentada, pero no se ha podido validar contra Siigo.
- [x] **Motor de tarifas dinámicas** (`App\Libraries\MotorTarifas`, controlador `Tarifas`,
      tablas `temporadas`, `reglas_precio`, `tarifas_temporada`; columnas nuevas en `tipos_unidad`:
      `precio_minimo`, `precio_maximo`, `personas_incluidas`, `suplemento_adulto`, `suplemento_nino`;
      columna `reservas.desglose_precio`). El precio se calcula **noche a noche** apilando en orden:
      **1.** tarifa base del tipo → **2.** temporada que cubre la fecha (precio cerrado por tipo en
      `tarifas_temporada`, o ajuste en porcentaje / valor; si se solapan gana la de mayor `prioridad`)
      → **3.** reglas activas por prioridad (`dia_semana` con días "5,6"; `ocupacion` en % calculada
      con las reservas activas sobre las unidades no bloqueadas; `anticipacion` en días hasta la
      llegada; `duracion` en noches) → **4.** topes mínimo y máximo del tipo → **5.** suplementos
      por personas que superan `personas_incluidas`. **Siempre devuelve el desglose**: cada ajuste
      con su concepto, su texto ("+20 %") y su importe en pesos.
      El desglose se **congela en la reserva** (`desglose_precio`, JSON v1) al crearla: si mañana
      cambian las reglas, la reserva sigue explicándose con lo que se le cobró al huésped. Al editar
      una reserva solo se recalcula si cambian fechas, cabaña u ocupantes, o si se marca la casilla
      "recalcular"; una corrección de notas no altera el precio pactado.
      Pantallas: `/tarifas` (temporadas y reglas con modales, atajos de temporada, activar/desactivar),
      `/tarifas/temporada/:id` (precio cerrado por tipo), `/tarifas/calendario` (rejilla del mes con
      precio, ocupación, color de temporada y desglose en el tooltip; medias, mínimo, máximo e ingreso
      potencial) y `/tarifas/simulador` (cotización completa con el porqué de cada peso).
      El desglose también se ve en la web pública (paso 2 y 3 del motor de reservas) y en la ficha
      de la reserva. **Verificado**: temporada +35 % × fin de semana +20 % × tope máximo × suplemento
      por adulto adicional dan el mismo total en simulador, calendario y web.
- [x] **Sincronización con portales (Booking, Airbnb) por iCal**
      (`App\Libraries\Ical`, `App\Libraries\SincronizadorCanales`, controlador `Canales`,
      comando `canales:sincronizar`; tablas `canal_conexiones` y `bloqueos`; columnas nuevas
      `reservas.canal / comision / referencia_externa` y `unidades.token_ical`).
      **Por qué iCal y no la API de Booking**: las APIs existen (Reservations API, ARI) pero solo
      se entregan a proveedores del *Connectivity Partner Programme*, con certificación y cartera de
      alojamientos. Un hotel suelto no las obtiene, y eso no se resuelve programando. iCal es lo
      único que Booking, Airbnb y Expedia abren a cualquier alojamiento.
      · **Exportar**: `/calendario-ical/{token}` por cabaña, sin sesión (Booking no puede iniciarla)
      pero con token de 48 caracteres, renovable. Publica **solo fechas**: ni nombres ni importes.
      **No devuelve los bloqueos que vinieron de otro portal**, para no crear un eco entre portales
      que acabaría bloqueando fechas realmente libres.
      · **Importar**: una conexión por cabaña y portal. `reemplazarDeConexion()` borra y reinserta,
      que es la única forma de que una reserva cancelada en Booking desaparezca de aquí.
      · **`ReservaModel::unidadDisponible()` mira ahora también los bloqueos**: ahí es donde esto
      sirve de algo. Verificado: con la Cabaña 2 bloqueada, el motor ofrece 5 de 7.
      · **Detección de sobreventa** (`BloqueoModel::conflictos()`): reservas propias que chocan con
      un bloqueo, en rojo arriba del todo y también en la salida del comando.
      · `Ical` se implementa a mano (RFC 5545: plegado a 75 octetos con `mb_strcut`, escapado,
      `DTEND` exclusivo) para no depender de una librería en hosting compartido.
      · **Canal de origen** en cada reserva con su comisión típica (Booking 17 %, Airbnb 15 %),
      para poder comparar en Reportes lo que deja la venta directa.
      **Verificado de punta a punta**: calendario exportado válido; importado en otra cabaña (3 fechas);
      el motor deja de ofrecerla; sobreventa provocada y detectada; y dirección inválida que devuelve
      «esa dirección no devuelve un calendario».
      **Pendiente**: programar `canales:sincronizar` cada hora al desplegar.
- [x] **Experiencias y actividades** (`ExperienciaModel`, `ExperienciaReservaModel`, controlador
      `Experiencias`; tablas `experiencias` y `experiencia_reservas`; `medios.experiencia_id`).
      Venta de mayor margen que la habitación, ofrecida en tres sitios: la web pública
      (`/experiencias-actividades`), la ficha de la reserva y la agenda del mostrador.
      · **Catálogo**: precio **por persona o cerrado por grupo**, precio de niño, **coste** para ver
      el margen en vivo mientras se escribe, duración, cupo por salida, mínimo para que salga,
      edad mínima, días de la semana, horas de salida (`08:00,15:00`), punto de encuentro,
      proveedor externo y aviso previo. Galería propia reutilizando `medios` + `Galeria`.
      · **Agenda** (`/experiencias`): tira de la semana con marca en los días con salida, y el día
      abierto agrupado por hora con quién va, de qué cabaña y cuántas plazas quedan.
      · **El cupo es real**: `plazasLibres()` bloquea la sobreventa y `seHace()` impide apuntar en un
      día en que no se hace. Ambas se validan **en el servidor**, no solo en el formulario.
      · **Cobro**: se carga al folio al marcar la salida como **realizada**, no al apuntarla — se
      cobra lo que de verdad ocurrió, así una cancelación no necesita revertir nada.
      · Un `<select>` de plazas libres se refresca por AJAX; lleva **numeración de peticiones** para
      que, al cambiar rápido de fecha, una respuesta lenta no pinte la disponibilidad de otro día.
      · **Venta cruzada en el motor de reservas** (paso 3): se ofrecen solo las experiencias que
      **caben de verdad** durante esa estancia — se comprueba día a día `seHace()` y `plazasLibres()`
      para el número de personas de la reserva, y el `<select>` de día solo lista salidas con sitio.
      Al marcar una, el resumen de la derecha suma el importe al instante. Quedan como
      **`solicitada`**, no como venta cerrada: aparecen en el aviso «sin confirmar» de la agenda.
      El cupo **se vuelve a comprobar al confirmar** (entre ver la página y enviar pudo entrar otro);
      lo que ya no cabe no se apunta y se le dice al huésped en la pantalla de confirmación.
      **Verificado**: margen 62,5 % calculado en vivo; 4 adultos + 1 niño = $560.000; rechazo por
      día no operativo y por cupo insuficiente («solo quedan 1 plaza»); cargo al folio al realizarla;
      y reserva web de $1.190.000 que pasa a **$1.610.000** al añadir cabalgata y paseo en lancha.
- [x] **Propina conforme a la Ley 1935 de 2018** (revisión del TPV a petición de Javier).
      **Ya estaba bien**: la propina se muestra separada en el ticket, y **no se factura** —
      `Facturas::emitir()` recalcula el total desde las líneas de producto, así que no entra en la
      base gravable ni va a la DIAN. Era lo más fácil de hacer mal.
      **Se corrigió**:
      · **No se decía en ninguna parte que es voluntaria.** La ley obliga a informarlo por escrito
      en la carta y en la cuenta. Añadido el aviso en el **ticket** y en la **carta pública**.
      · **No había propina sugerida**: el camarero tecleaba una cifra a mano. Ahora el TPV ofrece
      `Sin propina / mitad / sugerida` (10 % configurable en Administración, 0 para no sugerir).
      **`Sin propina` tiene el mismo peso visual que el resto**: la ley la hace voluntaria, así que
      esconder esa opción sería empujar al cliente.
      · **La pantalla de facturar sumaba la propina al total** aunque luego no se facturaba. Ahora
      dice «Total a facturar» y debajo «Propina voluntaria — no se factura».
      **Aviso**: esto es lectura de la norma, no asesoría legal; conviene que lo confirme su contador.
- [x] **Liquidación de propinas y propina elegida al cobrar** (cierra lo que quedó pendiente arriba).
      · **La propina se pregunta en el momento de cobrar**, no antes: el bloque
      `#cob-propina` aparece dentro del modal de cobro, con la cuenta delante del cliente, y ofrece
      `Sin propina / la mitad / la sugerida`. El pendiente se recalcula al vuelo.
      **Se oculta si ya hay pagos parciales**: cambiar la propina después alteraría lo ya cobrado.
      · **Liquidación** (`propinas`, solo gerencia). Tres criterios: `ventas` (proporcional a lo que
      generó cada uno), `partes_iguales` y `manual`. Se puede ajustar cifra a cifra.
      · **La regla que gobierna todo el módulo**: lo repartido tiene que ser **exactamente** igual a
      lo recaudado. `PropinaLiquidacionModel::repartir()` usa `floor()` por línea y le da los pesos
      sobrantes del redondeo a la primera, y `Propinas::guardar()` **se niega a cerrar** si no cuadra.
      La propina no es del hotel: no puede quedarse un peso.
      · Las comandas ya liquidadas se marcan con `comandas.liquidacion_id`, así que **no se pueden
      repartir dos veces**.
      · La propina sin camarero asignado (comandas hechas con el TPV compartido apagado) se avisa
      aparte, pero **entra en el total** y hay que repartirla igual.
      · `propinas/comprobante/N` imprime **una hoja por trabajador** con su importe, el periodo, el
      texto de la Ley 1935 y hueco de firma: es la constancia que exige la norma.
- [x] **Comandero: el camarero toma nota desde su móvil** (`/comandero`, PWA instalable).
      Dos decisiones de Javier marcan el diseño: **no se cobra desde el móvil** (el dinero se queda
      en la caja fija, así que perder el teléfono no es perder dinero) y **tiene que funcionar sin
      señal** (el wifi no llega al muelle ni a la fogata).
      · **Entra con documento + PIN de fichaje**, no con usuario del sistema: un camarero no tiene
      cuenta del panel. Comparte sesión con el portal del empleado (`empleado_id`) y el filtro
      `Comandero` exige `rol_tpv` de camarero o encargado **en cada petición**, no solo al entrar:
      si a alguien le retiran el permiso a media tarde, se le corta al momento.
      · **Una ronda se manda entera y de una vez** a `POST comandero/api/enviar`, no plato a plato.
      Sin señal se guarda en el teléfono y se reintenta sola. El `uuid` que genera el teléfono **no
      cambia entre reintentos** y queda en `comandero_envios`: por eso un reintento de algo que sí
      había llegado devuelve `repetida:true` y **no duplica platos en cocina**. Verificado.
      · **La cola se vacía de una en una, nunca en paralelo**: con `$regenerate = true` el token CSRF
      rota en cada petición y dos envíos simultáneos harían que el segundo llegara con el token
      viejo. Y como la pantalla se sirve de la caché cuando no hay red, el token que trae el HTML
      puede estar caducado: antes de vaciar la cola se pide uno nuevo con un GET (exento de CSRF).
      · **El precio se recalcula siempre en el servidor**, nunca se acepta el del teléfono: la carta
      cacheada puede tener días.
      · La hora de envío se sella al llegar (`updated_at`), no cuando se tomó: si no, una ronda que
      pasó media hora en el teléfono saldría la primera en cocina como si llevara media hora
      esperando. Se guarda aparte `demora_seg`, que delata qué zonas no tienen cobertura.
      · **Aviso de platos listos** con la mesa a la que ir (`listosDetalle()`), no un número suelto.
      · **`comandas.usuario_id` pasó a admitir nulos** (migración `ComandaSinUsuario`): una comanda
      del móvil no tiene usuario del sistema, responde el `empleado_id`. Ojo: `historial()` hacía un
      `join` normal contra `usuarios` y esas comandas **habrían desaparecido del historial sin
      avisar**; ahora es `left join` con `COALESCE(usuario, empleado)`.
- [x] **El TPV enseña quién atiende cada mesa** (a petición de Javier).
      El dato existía desde el TPV compartido (`comandas.empleado_id` y `comanda_lineas.empleado_id`)
      pero **no se pintaba en ninguna parte**: se guardaba para los informes y nadie lo veía en
      caliente, que es cuando hace falta.
      · **Rejilla de mesas**: el nombre de quien la lleva, con icono de teléfono si la comanda entró
      por el comandero. En un turno con varios, evita que dos vayan a la misma mesa.
      · **Cabecera de la comanda**: «Atiende: X · desde el móvil».
      · **Línea a línea, solo si la mesa la han tocado varios** (`varias_manos`). Con un solo
      camarero repetir su nombre en cada plato es ruido y ya está en la cabecera.
      · `origen` se deduce de `usuario_id IS NULL && empleado_id IS NOT NULL`: la pantalla fija
      siempre graba usuario, el móvil nunca.
- [x] **El TPV ve en vivo lo que se está tomando en el móvil** (`comandero_borradores`).
      Javier lo pidió con un caso concreto: cargó una comanda en la Mesa 4 desde el teléfono y en el
      TPV no se veía nada. **No era un fallo**: el comandero acumula la ronda en el teléfono hasta
      que se pulsa «Enviar a cocina», que es justo lo que le permite funcionar sin señal. Lo que
      faltaba era enseñar el borrador.
      · El teléfono **empuja lo que lleva apuntado con 1,5 s de retardo** desde `guardarBorradores()`,
      que es el único sitio por el que pasan todos los cambios (engancharlo botón a botón sería
      olvidarse en uno). Sin señal no se manda nada y no pasa nada: **es un extra para quien está en
      la caja, no el camino por el que viaja la comanda**.
      · **No son líneas de comanda y no deben serlo.** Si lo fueran saldrían en cocina y sumarían al
      total antes de tiempo. Verificado: con el borrador visible, el total sigue en $0 y **Cobrar
      sigue bloqueado**. El bloque lo dice: «Sin enviar a cocina · no entra en el total todavía».
      · **Caduca solo** a los `VIGENCIA_MIN` (8 min). Si el teléfono se apaga o se queda sin batería,
      mejor no enseñar nada que enseñar algo de hace media hora como si estuviera pasando ahora.
      Verificado con un borrador de 20 minutos: no aparece.
      · Se borra al enviar la ronda, tanto por `comanda_id` como por la `clave` del teléfono.
      · El refresco del mapa de mesas bajó de 30 s a **12 s**: ver que una mesa se está atendiendo
      tiene que notarse en el momento.
      · En azul y con borde discontinuo. El ámbar ya significa «ocupada» y el discontinuo dice que
      esto todavía no es firme.
- [x] **Modo táctil del panel** (`layouts/panel.php`, conmutador `#btn-tactil`).
      El TPV, el terminal de fichaje y el portal del empleado ya nacieron para dedos; **el panel de
      gestión no**. Medido antes de tocar nada: 28 botones de solo icono cuya única explicación era
      un `title`, 25 reglas `:hover` sin proteger y botones de acción de **35 × 32 px con 4 px de
      separación** — con una yema de 45-57 mm eso no es incomodidad, es borrar algo por error.
      · **La preferencia es por dispositivo** (`localStorage`), no del hotel: la misma instalación la
      abre una tableta en recepción y un portátil en la oficina, y cada uno debe quedarse a su gusto.
      Se autodetecta con `(pointer: coarse)` y se puede forzar desde la barra superior. La decisión
      se toma en un `<script>` del `<head>` para que la página no salte al cargar.
      · **Objetivos de 44 px** (recomendación de Apple), filas más altas y separación real entre los
      botones de acción. **Medido: 35 × 32 px con 4 px → 136 × 44 px con 10 px.**
      · Los botones de solo icono **muestran su `title` como texto** vía
      `.btn[title]:has(> i:only-child)::after` — sin tocar las 28 plantillas. Si el navegador no
      soporta `:has()` simplemente no se ven las etiquetas: no rompe nada.
      · **Las 25 reglas `:hover` van dentro de `@media (hover: hover)`**, lo que quita el «hover
      pegado» de táctil. El `:focus` se dejó fuera a propósito: hace falta para el teclado.
      · **El desglose del calendario de tarifas ya no vive solo en el tooltip**: en una tableta esa
      información era inalcanzable. Ahora se abre un panel al tocar la noche (y también con el
      teclado). Verificado en el sábado del puente de Asunción: base $350.000 + temporada +20 % +
      fin de semana +20 % = **$504.000**.
      **Verificado**: con ratón queda apagado por defecto y nada cambia; en tableta (768 px) la
      página no desborda —solo las tablas, que ya tienen su propio desplazamiento—; la preferencia
      sobrevive a la recarga y se puede apagar.
      **No verificable por mí**: el uso con un dedo real y el comportamiento de la tableta concreta.
- [x] **TPV compartido entre camareros** (`App\Libraries\SesionTpv`, `App\Filters\Tpv`;
      columnas nuevas `empleados.tarjeta_uid` y `rol_tpv`, `comandas.empleado_id` y `autorizo_id`,
      `comanda_lineas.empleado_id`, `comanda_pagos.empleado_id`).
      Antes todo se apuntaba a quien abrió sesión por la mañana; con varios turnos en la misma
      pantalla eso no sirve ni para pagar propinas ni para saber quién anuló una comanda.
      · **Activable** desde Administración: apagado, el TPV funciona como siempre.
      · **Identificación con el PIN de fichaje** (se reutiliza, el camarero ya se lo sabe) **o con
      tarjeta**. Los lectores RFID baratos se comportan como un teclado: «escriben» el número y un
      Enter. Se distinguen de una persona **por la velocidad** (menos de 60 ms entre teclas), así
      que no hacen falta drivers ni permisos del navegador. Descartado WebNFC: solo Android/Chrome.
      · **La identidad vive en la sesión del servidor**, no en el navegador, y se exige con un
      **filtro** (`tpv`) sobre el grupo de rutas — repartir la comprobación por el controlador
      dejaría un hueco al olvidarla en un método. `identificar`, `bloquear` y `estado` quedan fuera
      para poder pintar el mapa de mesas con la pantalla bloqueada.
      **Verificado**: con token CSRF válido y la pantalla bloqueada, `POST /pos/api/abrir` → 401.
      · **Bloqueo** por inactividad (configurable, 60 s por defecto), al cobrar y a mano desde el chip.
      · **Permisos**: anular pide siempre PIN de encargado; el descuento, solo por encima del límite
      configurable. El cliente reintenta solo la petición añadiendo `pin_encargado`.
      **Verificado**: Pedro (camarero) intenta anular → pide encargado; con su propio PIN → «ese PIN
      no es de un encargado»; con el de Marta → anulada, y en la BD queda `empleado_id=Pedro`,
      `autorizo_id=Marta` y la línea también a nombre de Pedro.
      · **Informe en Reportes**: ventas, ticket medio, propinas y anuladas por camarero, más el
      listado de anulaciones y descuentos con quién los autorizó.
      · Cuidado tomado: `.visor` ya existía en el TPV para el teclado numérico; las bolitas del PIN
      usan `.visor-pin` para no pisar el estilo.
- [x] **Control de jornada / fichaje** (`FichajeModel`, `App\Libraries\Fichaje`, controladores
      `Fichar` (terminal), `Empleado` (PWA) y `Fichajes` (gerencia); tabla `fichajes`;
      columnas nuevas en `empleados`: `pin_hash`, `pin_actualizado`, `ficha_movil`, `foto`).
      · **Terminal quiosco** en `/fichar`: pantalla completa con teclado numérico, reloj y foto de
      la webcam. **Sin sesión a propósito** — quien limpia no tiene usuario del sistema — con freno
      de 12 intentos/minuto por IP, se apaga desde Administración y todo queda en el log.
      El PIN de 4 cifras se guarda con `password_hash`; como identifica a la persona, `fijarPin()`
      **impide que dos empleados compartan PIN** y rechaza los obvios (1234, 0000…).
      **Decisión importante: si la cámara falla, el fichaje se registra igual** con la observación
      «Sin foto», visible para gerencia. Nadie puede quedarse sin registrar su jornada porque un
      cacharro se estropeó — eso sería un problema del hotel, no del trabajador.
      · **Portal del empleado como PWA** en `/empleado`: manifiesto + `public/sw.js` + iconos
      generados. Entra con documento + PIN (sesión propia, separada de la del panel), ficha con
      geolocalización, ve sus horas de la semana y del mes y sus próximos turnos.
      El service worker **no cachea las páginas del fichaje** a propósito: una pantalla servida de
      caché mostraría un estado viejo y el trabajador creería haber marcado sin hacerlo.
      · **Coherencia**: `siguienteTipo()` / `accionesPosibles()` impiden dos entradas seguidas, y se
      rechaza una segunda marca en menos de 60 s (doble toque). `jornadas()` empareja entradas con
      salidas, descuenta pausas y señala los días que quedaron sin cerrar.
      · **El registro es prueba, no hoja de cálculo**: las marcas **no se borran**, se anulan con
      motivo y queda quién lo hizo; las añadidas a mano se marcan como `manual` con su motivo.
      · **Ubicación**: si se configuran las coordenadas del hotel se calcula la distancia (haversine).
      Fichar fuera del radio **no se bloquea**: se anota y gerencia lo ve — puede ser mala señal o
      un recado real del hotel.
      · **Datos personales** (Ley 1581/2012): las fotos van a `writable/uploads/fichajes/`, se sirven
      solo a gerencia con `no-store` y cada consulta queda en el log; el terminal avisa al trabajador.
      **Verificado**: PIN → identificación → entrada; el sistema reconoce el estado y solo ofrece
      pausa/salida; salida desde el móvil cerrando 3 h 1 min; anulación con motivo que devuelve el
      día a «sin cerrar».
- [x] **Ficha completa de las cabañas** (`MedioModel`, `ServicioModel`, `InventarioItemModel`,
      `RevisionInventarioModel`, `App\Libraries\Galeria`, controladores `Unidades`, `Catalogos` y
      ampliación de `Tipos`; tablas `medios`, `servicios`, `tipo_servicios`, `unidad_servicios`,
      `inventario_items`, `unidad_inventario`, `inventario_revisiones`,
      `inventario_revision_lineas`; columnas nuevas en `unidades`: `descripcion`, `ubicacion`, `orden`).
      **Reparto de responsabilidades**: lo que se anuncia va en el **tipo** (es lo que se vende) y lo
      que se limpia, se avería y se cuenta va en la **unidad** física.
      · **Galerías**: hay dos niveles y **cada cabaña tiene la suya propia**.
      En el tipo (`/tipos/ficha/:id`) van las fotos comunes a todas; en cada cabaña
      (`/unidades/ver/:id#galeria`) las suyas: su vista, su terraza, su vídeo. Ambas admiten fotos
      y **vídeos de YouTube/Vimeo por enlace** (el archivo de vídeo no se sube: pesaría demasiado),
      con portada, reordenar y texto alternativo.
      Redimensionado por GD a 1600 px + miniatura de 480 px (verificado: 2400×1600 → 1600×1067, 51 KB).
      · **`medios.publico` decide dónde acaba el archivo**, no a quién pertenece:
      publicable → `public/medios/tipos/` o `public/medios/cabanas/`; interno →
      `writable/uploads/unidades/`, servido solo por controlador con `no-store` y registro de acceso.
      El botón del ojo pasa una foto de una galería a la otra **moviendo el archivo de carpeta**.
      **Verificado**: acceso directo al archivo interno → 403, y sin sesión el controlador no lo sirve.
      · En la web, la galería de un tipo **junta lo común con las fotos de sus cabañas**, etiquetadas
      con el nombre («Cabaña 1 · Terraza al amanecer»). En el motor de reservas solo se enseñan las
      de las cabañas realmente libres en esas fechas.
      · **Servicios**: catálogo con icono y grupo; se marcan en el tipo y las cabañas los heredan.
      Cada cabaña admite **excepciones** (`unidad_servicios` con estado `si`/`no`): solo se guarda
      lo que se aparta del tipo, así un cambio en el tipo se propaga solo.
      · **Inventario de enseres**: catálogo con valor de reposición y cantidad estándar; cantidades
      por cabaña (con «copiar a las demás»); pantalla de **revisión** pensada para el móvil donde se
      marca ok / falta / dañado. Lo dañado abre un **aviso de mantenimiento** y lo que falta se
      **carga al folio** del huésped al valor de reposición. **Verificado de punta a punta**:
      1 toalla faltante + 1 secador dañado → aviso de mantenimiento abierto y $40.000 al folio.
      · **Ficha `/unidades/ver/:id`**: estado, próximas reservas, limpiezas, incidencias abiertas,
      servicios, inventario, revisiones y fotos internas. El listado pasó de tabla a tarjetas con foto.
      · **Web pública**: `web/_galeria_tipo.php` muestra carrusel con las fotos reales y los servicios
      como chips, en Alojamientos y en el paso 2 del motor de reservas. **Mientras no haya fotos
      sigue mostrando las ilustraciones SVG**: es mejor un dibujo cuidado que un hueco.
- [x] **Agente de tarifas + calendario de festivos de Colombia**
      (`App\Libraries\FestivosColombia`, `App\Libraries\AgenteTarifas`, `/tarifas/agente`;
      columnas `temporadas.origen` y `temporadas.clave`).
      **No usa IA ni servicios externos**: los festivos son reglas de ley y se calculan.
      Pascua con el algoritmo gregoriano anónimo (Meeus/Jones/Butcher) implementado a mano —
      no depende de la extensión `calendar` ni de la zona horaria. Los 18 festivos salen de tres
      grupos: fijos (1 ene, 1 may, 20 jul, 7 ago, 8 dic, 25 dic), trasladables al lunes siguiente
      por la **Ley 51 de 1983 («Ley Emiliani»)** (6 ene, 19 mar, 29 jun, 15 ago, 12 oct, 1 nov,
      11 nov) y los de Pascua (Jueves y Viernes Santo sin mover; Ascensión +43, Corpus +64 y
      Sagrado Corazón +71, que ya caen en lunes). **Verificado contra 2026 y 2027.**
      Los **puentes** se detectan solos: rachas de 3+ días no laborables seguidos que contengan
      un festivo; la temporada empieza la noche anterior porque esa noche también se vende.
      El agente propone el año entero (puentes, Semana Santa completa, fin de año, vacaciones de
      mitad de año y temporada baja) y **Javier acepta lo que quiera**: nada se guarda sin marcarlo.
      · **Aviso de años sin preparar** (`pendientes()` / `sinPreparar()`): sale en el panel de control
      y en Tarifas. Que sea manual es a propósito, pero olvidarse no debería ser fácil: si nadie lo
      pasa, los puentes se venden a precio base sin que nadie se entere. Avisa del **año en curso**
      contando solo lo que queda por delante, y del **siguiente a partir de octubre**. El conteo es
      barato (los festivos son cálculo puro y una sola consulta por clave), así que no pesa en el panel.
      · Las propuestas cuyo tramo **ya terminó** salen marcadas «Ya pasó» y **sin casilla activada**:
      crearlas no cambia nada y solo ensuciaría el año.
      Cada propuesta lleva `clave` única, así que volver a pasarlo no duplica; y solo refresca las
      temporadas de `origen = agente` (si las editas a mano, se respetan).
      **Aprende de la ocupación real** cuando la haya: mira esas mismas fechas del año anterior
      (incluidas las estancias ya terminadas) y sube o baja la sugerencia. Sin histórico lo dice
      claramente en vez de inventar. El calendario de tarifas marca los festivos con una estrella.
- [x] **Cupones de descuento** (`CuponModel`, `CuponUsoModel`, controlador `Cupones`, tablas `cupones`
      y `cupon_usos`; `folio_movimientos.tipo` amplía el ENUM con `descuento` y `comandas.cupon_id`).
      Cada cupón define: porcentaje o valor fijo, **descuento máximo**, ámbito (alojamiento /
      restaurante / los dos), vigencia, **importe mínimo**, **límite de usos totales y por huésped**
      (se controla por documento), y en qué **canales** se puede canjear (web, recepción, TPV).
      `CuponModel::validar()` es el único punto de verdad: devuelve un mensaje en español explicando
      por qué no se puede aplicar. Cada canje se anota en `cupon_usos` (base, descuento, canal,
      quién y dónde), y el contador `usos` sube; el TPV **devuelve el uso** si se anula la comanda o
      se sustituye por un descuento manual. Un cupón ya canjeado no se borra: se desactiva.
      En el folio el descuento es un **tipo propio de movimiento** (ni cargo ni pago) y resta del saldo.
- [x] **Bonos regalo** (`BonoModel`, `BonoMovimientoModel`, controlador `Bonos`, tablas `bonos` y
      `bono_movimientos`; `folio_movimientos.metodo` y `comanda_pagos.forma_pago` añaden `bono`).
      **Distinción contable importante**: un bono no es un descuento, es dinero cobrado por
      adelantado; al canjearlo se registra como **forma de pago**, y la venta del bono en efectivo
      entra en la caja del turno. Código legible tipo `BR-9888-UM43` (alfabeto sin I/O/0/1).
      Saldo parcial: se consume lo que haga falta y el resto queda para otra visita; todo movimiento
      (emisión, canje, devolución, anulación) queda en el historial con el saldo resultante.
      Se canjea desde la ficha de la reserva y desde el TPV (forma de pago «Bono regalo», compatible
      con la división de cuenta). Al anular una comanda se **devuelve el saldo** al bono.
      Ficha imprimible con la marca para entregar al comprador. Los reportes avisan de que lo cobrado
      con bono no es ingreso del día. **Verificado de punta a punta**: cupón del 10 % en la web
      (−$160.650 sobre $1.606.500), bono de $500.000 canjeado en el folio, y en el TPV cupón +
      pago partido bono/efectivo (50.000 + 67.900 = 117.900).
- [ ] Escandallo fase B: inventario con descuento de existencias (cuando el hotel esté operando)
- [ ] Combos / menús cerrados (producto compuesto de otros productos) — propuesto, no elegido aún
- [x] **Registro en línea del huésped / autocheck-in** (tablas `registros`, `registro_acompanantes`,
      `registro_documentos`; controladores `Registro` público y `Registros` de revisión):
      enlace con token de 48 caracteres que **caduca un día después de la salida**; formulario móvil
      por pasos (datos TRA, acompañantes, documento, avisos legales, **firma con el dedo** en canvas);
      cola de revisión en recepción con aprobación o devolución con motivo (el huésped reusa el enlace).
      **Cumplimiento:** autorización de tratamiento de datos (Ley 1581/2012 y Decreto 1377/2013) con
      versión guardada, aviso ESCNNA (Leyes 679/2001 y 1336/2009), detección automática de **menores**
      (por fecha de nacimiento) y de **extranjeros** (por nacionalidad) con avisos a recepción, y
      marcas de control para los reportes **TRA** y **SIRE** (el sistema no transmite: ayuda a llevar
      el control). **Seguridad de documentos:** se guardan en `writable/uploads/documentos` (fuera de
      public/, acceso web directo 403), solo se sirven por controlador a personal con sesión, con
      `no-store` y registro en el log de cada consulta. Verificado el ciclo completo en móvil.
- [x] **Sistema de correos** (`App\Libraries\Correo`, plantillas en `app/Views/correos/`,
      tabla `correos_log`): plantilla HTML con la marca (tablas + estilos en línea, compatible con
      Gmail/Outlook/móvil) y tres avisos automáticos — **confirmación de reserva** con su enlace de
      registro (al confirmar), **aviso interno** al hotel cuando entra una reserva web, y **reenvío
      manual** del enlace de registro. Todo envío queda anotado en `correos_log` con su estado
      (enviado / fallido / sin configurar) y el motivo, visible en Administración.
      **Nunca interrumpe la operación**: si el correo no está configurado o falla, la reserva se
      confirma igual y el mensaje al usuario le dice qué hacer (enviar por WhatsApp).
      Pendiente solo de que Javier ponga credenciales SMTP reales.
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
- [ ] Pago online con Wompi sobre el motor de reservas (ver más abajo; requiere credenciales)

## Fases siguientes (según propuesta)
3. Operación interna: housekeeping, mantenimiento, lavandería
4. Alimentos y bebidas: POS restaurante, KDS, inventarios
5. Automatización: cerraduras, facturación electrónica, TRA, channel manager, CRM
6. Optimización: indicadores avanzados, revenue, fidelización
