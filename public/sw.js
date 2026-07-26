/**
 * Service worker del portal del empleado.
 *
 * Deliberadamente conservador: NO guarda en caché las páginas del fichaje.
 * Un fichaje servido desde la caché mostraría un estado viejo y el trabajador
 * creería haber marcado cuando no lo hizo. Solo se cachean tipografías e
 * iconos, y se ofrece una página de cortesía cuando no hay conexión.
 */

const VERSION = 'jornada-v1';
const ESTATICOS = VERSION + '-estaticos';

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
