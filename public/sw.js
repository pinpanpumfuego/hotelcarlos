/**
 * Service worker de las dos aplicaciones de móvil del hotel.
 *
 * Tratan la caché al revés a propósito:
 *
 * · **Fichaje** (`/empleado`, `/fichar`): NO se cachea. Un fichaje servido
 *   desde la caché mostraría un estado viejo y el trabajador creería haber
 *   marcado cuando no lo hizo.
 *
 * · **Comandero** (`/comandero`): SÍ se cachea la pantalla. El camarero está
 *   en el muelle sin cobertura y tiene que poder abrir la aplicación y seguir
 *   tomando nota; lo que apunte se guarda en el teléfono y se manda solo al
 *   volver la señal. Aquí una pantalla de hace un rato no engaña a nadie,
 *   porque los datos que importan viven en el propio teléfono.
 */

const VERSION = 'jornada-v2';
const ESTATICOS = VERSION + '-estaticos';
const CONCHA = VERSION + '-comandero';

self.addEventListener('install', function (evento) {
    self.skipWaiting();
});

self.addEventListener('activate', function (evento) {
    evento.waitUntil(
        caches.keys().then(function (nombres) {
            return Promise.all(
                nombres
                    .filter(function (n) { return n.indexOf(VERSION) !== 0; })
                    .map(function (n) { return caches.delete(n); })
            );
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (evento) {
    const peticion = evento.request;

    // Nada que no sea una lectura simple pasa por aquí
    if (peticion.method !== 'GET') {
        return;
    }

    const url = new URL(peticion.url);

    // Tipografías e iconos: de la caché si están, y se guardan al vuelo
    if (url.origin !== self.location.origin) {
        evento.respondWith(
            caches.open(ESTATICOS).then(function (cache) {
                return cache.match(peticion).then(function (guardada) {
                    const red = fetch(peticion).then(function (respuesta) {
                        if (respuesta && respuesta.status === 200) {
                            cache.put(peticion, respuesta.clone());
                        }
                        return respuesta;
                    }).catch(function () { return guardada; });

                    return guardada || red;
                });
            })
        );
        return;
    }

    // El comandero: primero la red para tener la carta y el token frescos, y
    // si no hay, lo último que se guardó. Sin esto, un camarero sin cobertura
    // se encontraría el dinosaurio del navegador en vez de su comandero.
    if (peticion.mode === 'navigate' && url.pathname.indexOf('/comandero') !== -1) {
        evento.respondWith(
            fetch(peticion).then(function (respuesta) {
                if (respuesta && respuesta.status === 200) {
                    const copia = respuesta.clone();
                    caches.open(CONCHA).then(function (cache) { cache.put(peticion, copia); });
                }
                return respuesta;
            }).catch(function () {
                return caches.match(peticion).then(function (guardada) {
                    return guardada || new Response(
                        '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">' +
                        '<meta name="viewport" content="width=device-width, initial-scale=1">' +
                        '<title>Sin conexión</title><style>' +
                        'body{margin:0;height:100vh;display:flex;align-items:center;justify-content:center;' +
                        'font-family:system-ui,sans-serif;background:#12281d;color:#eef5f0;text-align:center;padding:24px}' +
                        'p{color:#9fbcac;line-height:1.6}button{font:inherit;font-weight:600;padding:12px 20px;' +
                        'border:0;border-radius:12px;background:#4f8a68;color:#fff;margin-top:12px}</style></head><body><div>' +
                        '<h1>Sin conexión</h1>' +
                        '<p>Conéctate una vez al wifi del hotel para descargar la carta.<br>' +
                        'A partir de ahí el comandero funciona sin señal.</p>' +
                        '<button onclick="location.reload()">Reintentar</button>' +
                        '</div></body></html>',
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    );
                });
            })
        );
        return;
    }

    // Las páginas del portal siempre se piden a la red: el estado debe ser real
    if (peticion.mode === 'navigate') {
        evento.respondWith(
            fetch(peticion).catch(function () {
                return new Response(
                    '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">' +
                    '<meta name="viewport" content="width=device-width, initial-scale=1">' +
                    '<title>Sin conexión</title><style>' +
                    'body{margin:0;height:100vh;display:flex;align-items:center;justify-content:center;' +
                    'font-family:system-ui,sans-serif;background:#f2f5f2;color:#1c2a23;text-align:center;padding:24px}' +
                    'h1{font-size:1.3rem}p{color:#7b8a81;line-height:1.6}' +
                    'button{font:inherit;font-weight:600;padding:12px 20px;border:0;border-radius:12px;' +
                    'background:#1f4d36;color:#fff;margin-top:12px}</style></head><body><div>' +
                    '<h1>Sin conexión</h1>' +
                    '<p>No se pudo conectar con el sistema del hotel.<br>' +
                    'Si necesitas fichar ahora, hazlo en el terminal de recepción.</p>' +
                    '<button onclick="location.reload()">Reintentar</button>' +
                    '</div></body></html>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            })
        );
    }
});
