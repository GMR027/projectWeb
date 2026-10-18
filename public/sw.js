// Service Worker del modo sin conexión (solo se registra si el usuario lo activa en Ajustes; ver src/js/offline.js).
// - Recursos estáticos (css, js, fuentes, imágenes del build): caché primero, se actualizan en segundo plano.
// - Páginas: red primero; si no hay red se muestra la última copia vista en este equipo.
// - Nada que no sea GET, ni /api/, ni descargas, ni cerrar sesión se guarda.
const CACHE = 'po-v1';
const NUNCA = [/^\/api\//, /^\/logout/, /^\/exportaciones\//, /^\/login/, /^\/sw\.js/, /^\/uploads\//];

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(
    caches.keys().then(ks => Promise.all(ks.filter(k => k.startsWith('po-') && k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim())
));

self.addEventListener('fetch', e => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    const mismo = url.origin === location.origin;
    if (mismo && NUNCA.some(r => r.test(url.pathname))) return;

    const estatico = ['style', 'script', 'font', 'image'].includes(req.destination);
    if (estatico) {
        e.respondWith(caches.open(CACHE).then(async c => {
            const hit = await c.match(req);
            const red = fetch(req).then(r => { if (r && (r.ok || r.type === 'opaque')) c.put(req, r.clone()); return r; }).catch(() => hit);
            return hit || red;
        }));
        return;
    }
    if (mismo && (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html'))) {
        e.respondWith(fetch(req).then(r => {
            // Solo se guarda lo que respondió bien y no es una redirección al login
            if (r.ok && !r.redirected) {
                const copia = r.clone();   // se clona antes de devolverla: después el cuerpo ya estaría en uso
                caches.open(CACHE).then(c => c.put(req, copia));
            }
            return r;
        }).catch(async () => (await caches.match(req)) || new Response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Sin conexión</title><body style="font-family:sans-serif;padding:2rem"><h1>Sin conexión</h1><p>Esta pantalla no se descargó para uso sin conexión. Vuelve a abrirla con red o usa «Descargar el proyecto» en Ajustes.</p>', { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
    }
});
