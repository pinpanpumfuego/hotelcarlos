# Subir el sistema a dinahosting

Alojamiento web compartido, dominio ya apuntando.

Sigue los pasos en orden. **No saltes el paso 3**: es el que evita que los
documentos de identidad de tus huéspedes queden descargables por cualquiera.

---

## Antes de empezar: lo que tienes que tener a mano

- Acceso al **panel de dinahosting**
- Un programa de **FTP** (FileZilla es gratis) o el gestor de archivos del panel
- Un rato tranquilo: la primera vez lleva una hora larga

**Lo que NO hay que darle a nadie, ni a mí:** la contraseña del panel, la del
FTP y la de la base de datos. Se escriben directamente donde toca.

---

## Paso 1 · Comprobar que el servidor sirve

1. Sube **solo** el archivo `despliegue/comprobar-servidor.php` a la carpeta
   web (la que se ve desde internet, normalmente `www` o `public_html`).
2. Ábrelo en el navegador: `https://tu-dominio/comprobar-servidor.php`
3. Si algo sale en rojo, arréglalo en el panel antes de seguir.

Lo más habitual es que **PHP esté en una versión vieja**. Se cambia en
*Alojamiento web → Versión de PHP*. **Hace falta 8.2 o superior.**

**Borra el archivo** en cuanto lo hayas mirado.

---

## Paso 2 · Crear la base de datos

En el panel, *Bases de datos → Crear*. Apunta en un papel:

- Nombre de la base
- Usuario
- **Contraseña** ← esta no se puede volver a consultar después

---

## Paso 3 · La estructura ⚠️ EL PASO IMPORTANTE

El sistema guarda en `writable/uploads/` las **cédulas y pasaportes de los
huéspedes**, las **firmas del registro** y las **fotos del fichaje del
personal**. Si esa carpeta queda dentro de la zona web, cualquiera que
adivine la dirección se los descarga. Eso es una fuga de datos personales,
con la Ley 1581 de 2012 de por medio.

Hay dos formas de montarlo. **Intenta primero la A.**

### Forma A · Cambiar la carpeta raíz del dominio (la buena)

En el panel, busca dónde se configura la **carpeta raíz** (o *document root*)
del dominio. Si puedes cambiarla:

1. Sube **todo el proyecto** a `/home/TU_USUARIO/hotelcarlos/`
2. Apunta la carpeta raíz del dominio a `/home/TU_USUARIO/hotelcarlos/public`

Y ya está. Todo lo demás queda fuera del alcance del navegador solo.

### Forma B · Si no se puede cambiar la carpeta raíz

Queda así:

```
/home/TU_USUARIO/
├── hotelcarlos/          ← FUERA de la web
│   ├── app/
│   ├── vendor/
│   ├── writable/         ← cédulas, firmas, fotos del fichaje
│   └── .env
└── www/                  ← lo que se ve desde internet
    ├── index.php         ← el de despliegue/index-produccion.php
    ├── .htaccess
    ├── assets/           ← copiado de public/assets
    ├── medios/           ← copiado de public/medios
    ├── favicon.ico, icono-192.png, icono-512.png, robots.txt, sw.js
    └── .env  ✗ NO. El .env va arriba, con app/
```

Usa el `index.php` que te dejo en `despliegue/index-produccion.php`: ya viene
preparado para buscar la aplicación un nivel más arriba.

---

## Paso 4 · Configuración

1. Copia `despliegue/env-produccion.txt` al servidor **junto a `app/`**, con el
   nombre `.env` (con el punto, sin extensión).
2. Rellena el dominio y los datos de la base de datos.
3. **Genera una clave de cifrado nueva.** En tu ordenador:

   ```bash
   php spark key:generate
   ```

   y copia el valor al `.env` del servidor. **No reutilices la de casa.**

---

## Paso 5 · Crear las tablas

Si tienes SSH:

```bash
php spark migrate
```

Si no tienes SSH, dímelo y preparamos un archivo que lo haga desde el
navegador y se borre solo al terminar.

---

## Paso 6 · Comprobar que todo está tapado

Prueba estas direcciones en el navegador. **Las cinco tienen que dar error**
(403 o 404). Si alguna enseña algo, para y avísame:

- `https://tu-dominio/.env`
- `https://tu-dominio/app/Config/Database.php`
- `https://tu-dominio/writable/uploads/documentos/`
- `https://tu-dominio/writable/logs/`
- `https://tu-dominio/vendor/`

Y estas **sí** tienen que funcionar:

- `https://tu-dominio/` → la portada con el logo
- `https://tu-dominio/reservar` → el calendario con precios
- `https://tu-dominio/login` → el acceso del personal

---

## Paso 7 · Lo que solo funciona en el servidor

**Certificado HTTPS.** En el panel, *Certificados SSL*. Sin él no funcionan:
instalar el comandero como app, abrirlo sin señal, la cámara y el GPS del
fichaje, ni el «oído cocina».

**Tarea programada** para sincronizar Booking y Airbnb. En *Tareas
programadas*, cada hora:

```
php /home/TU_USUARIO/hotelcarlos/spark canales:sincronizar
```

**Copia de seguridad diaria** de la base de datos. Si el panel la ofrece,
actívala. Si no, dímelo y preparo un guion.

**Cerrar `/fichar` al internet público.** Hoy es una pantalla sin contraseña
pensada para el quiosco del hotel; en internet abierto cualquiera puede probar
PINs. Se limita por IP desde el panel o desde el `.htaccess`.

---

## Paso 8 · Lo primero al entrar

1. **Cambia la contraseña de `javier@pinpanpum.net`**, que sigue siendo la
   obvia. Es cuenta de gerencia: entra a las reservas, los datos de los
   huéspedes y la caja.
2. Rellena *Administración → Datos del hotel*: teléfono, WhatsApp, correo,
   **municipio** y las **coordenadas** (sin ellas el fichaje por ubicación no
   funciona).
3. Prueba **una factura real con Siigo**. Está conectado pero nunca se ha
   validado contra una factura de verdad, y eso no se descubre el día que
   llega el primer huésped.

---

## Si algo sale mal

Pantalla en blanco casi siempre es PHP en versión vieja o una extensión que
falta: vuelve al paso 1.

Los errores quedan en `writable/logs/`. Mándame el último y te digo qué es.
