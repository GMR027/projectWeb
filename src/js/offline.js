// Modo sin conexión: guarda en una cola local (IndexedDB) los cambios hechos sin red y los sube al volver.
// Se activa por equipo en Ajustes. Los cambios de la cola llevan la marca de la última vez que se vio el registro (`base`):
// si en el servidor cambió mientras tanto, no se pisa en silencio: se pregunta cuál versión queda.
(function () {
    'use strict';
    const CLAVE = 'po_offline';
    const BD = 'po-offline';
    const COLA = 'cola';

    const activo = () => { try { return localStorage.getItem(CLAVE) === '1'; } catch (_) { return false; } };

    function abrir() {
        return new Promise((ok, mal) => {
            if (!window.indexedDB) return mal(new Error('Sin IndexedDB'));
            const r = indexedDB.open(BD, 1);
            r.onupgradeneeded = () => r.result.createObjectStore(COLA, { keyPath: 'id', autoIncrement: true });
            r.onsuccess = () => ok(r.result);
            r.onerror = () => mal(r.error);
        });
    }

    async function operar(modo, fn) {
        const db = await abrir();
        return new Promise((ok, mal) => {
            const t = db.transaction(COLA, modo);
            const res = fn(t.objectStore(COLA));
            t.oncomplete = () => { db.close(); ok(res && 'result' in res ? res.result : undefined); };
            t.onerror = t.onabort = () => { db.close(); mal(t.error); };
        });
    }

    const listar = () => operar('readonly', s => s.getAll());
    const guardar = item => operar('readwrite', s => s.put(item));
    const quitar = id => operar('readwrite', s => s.delete(id));

    async function encolar(item) {
        await guardar({ estado: 'pendiente', creado: new Date().toISOString(), ...item });
        await pintar();
    }

    let subiendo = false;
    async function vaciar() {
        if (subiendo || !navigator.onLine) return;
        subiendo = true;
        try {
            for (const it of (await listar()).sort((a, b) => a.id - b.id)) {
                if (it.estado !== 'pendiente') continue;
                let r, d = {};
                try {
                    r = await fetch(it.url, { method: 'POST', body: new URLSearchParams(it.cuerpo), headers: { Accept: 'application/json' } });
                    d = await r.json().catch(() => ({}));
                } catch (_) { break; }   // sin red: se reintenta después
                if (r.ok && d.success !== false) { await quitar(it.id); continue; }
                if (r.status === 409) await guardar({ ...it, estado: 'conflicto', actual: d.actual || null });
                else if ([401, 403, 419].includes(r.status)) { await guardar({ ...it, estado: 'sesion', mensaje: 'La sesión caducó: inicia sesión y vuelve a subir.' }); break; }
                else await guardar({ ...it, estado: 'error', mensaje: d.error || 'El servidor rechazó el cambio (' + r.status + ')' });
            }
        } finally {
            subiendo = false;
            await pintar();
            if (!(await listar().catch(() => [])).some(i => i.estado === 'pendiente')) document.dispatchEvent(new CustomEvent('po-offline-subido'));
        }
    }

    async function resolver(id, aceptarMio) {
        const it = (await listar()).find(i => i.id === id);
        if (!it) return;
        if (aceptarMio) await guardar({ ...it, estado: 'pendiente', cuerpo: { ...it.cuerpo, forzar: '1' } });
        else await quitar(id);
        await vaciar();
        await pintar();
    }

    // ---------- aviso en pantalla ----------
    let banner;
    async function pintar() {
        if (!activo()) { if (banner) banner.hidden = true; return; }
        let items = [];
        try { items = await listar(); } catch (_) {}
        if (!banner) {
            banner = document.createElement('div');
            banner.className = 'po-offline-banner';
            banner.setAttribute('role', 'status');
            document.body.appendChild(banner);
            banner.addEventListener('click', e => {
                const b = e.target.closest('[data-po]');
                if (!b) return;
                if (b.dataset.po === 'subir') vaciar();
                else resolver(Number(b.dataset.id), b.dataset.po === 'mio');
            });
        }
        const sinRed = !navigator.onLine;
        const pend = items.filter(i => i.estado === 'pendiente').length;
        const otros = items.filter(i => i.estado !== 'pendiente');
        banner.hidden = !sinRed && !items.length;
        if (banner.hidden) return;
        const esc = t => String(t ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        banner.innerHTML = '<span class="po-offline-estado ' + (sinRed ? 'sin-red' : '') + '"><i class="fa-solid ' + (sinRed ? 'fa-plane' : 'fa-cloud-arrow-up') + '"></i> ' + (sinRed ? 'Sin conexión' : 'Con conexión') + '</span>'
            + (pend ? '<span>' + pend + ' cambio(s) por subir</span>' + (sinRed ? '' : ' <button type="button" class="btn btn-sm btn-primary" data-po="subir">Subir ahora</button>') : '')
            + (otros.length ? '<ul>' + otros.map(i => '<li><b>' + esc(i.etiqueta) + '</b> · ' + (i.estado === 'conflicto'
                ? 'cambió en el servidor' + (i.actual ? ' (ahora: ' + esc(i.actual.estado) + ')' : '') + ' <button type="button" class="btn btn-sm btn-primary" data-po="mio" data-id="' + i.id + '">Aplicar el mío</button> <button type="button" class="btn btn-sm btn-ghost" data-po="descartar" data-id="' + i.id + '">Descartar el mío</button>'
                : esc(i.mensaje || 'error') + ' <button type="button" class="btn btn-sm btn-ghost" data-po="descartar" data-id="' + i.id + '">Quitar</button>') + '</li>').join('') + '</ul>' : '');
    }

    // ---------- activar / desactivar ----------
    async function registrar() {
        if (!('serviceWorker' in navigator)) return false;
        try { await navigator.serviceWorker.register('/sw.js', { scope: '/' }); return true; } catch (_) { return false; }
    }

    async function activar() {
        try { localStorage.setItem(CLAVE, '1'); } catch (_) { return false; }
        const ok = await registrar();
        await pintar();
        return ok;
    }

    async function desactivar() {
        try { localStorage.removeItem(CLAVE); } catch (_) {}
        if ('serviceWorker' in navigator) (await navigator.serviceWorker.getRegistrations()).forEach(r => r.unregister());
        if (window.caches) (await caches.keys()).filter(k => k.startsWith('po-')).forEach(k => caches.delete(k));
        await pintar();
    }

    /** Baja al equipo las pantallas del proyecto para consultarlas sin red (el Service Worker las guarda al pasar) */
    async function precargar(proyecto, avance) {
        const urls = ['/programa', '/cambios', '/cambios/cruce', '/lineas-base', '/msr', '/lookahead', '/dependencias', '/reportes', '/bitacora'].map(u => u + '?proyecto=' + proyecto);
        try {
            const html = await (await fetch('/cambios?proyecto=' + proyecto, { credentials: 'same-origin', headers: { Accept: 'text/html' } })).text();
            for (const m of html.matchAll(/href="(\/cambios\/ver\?[^"]+)"/g)) urls.push(m[1].replace(/&amp;/g, '&'));
        } catch (_) {}
        const unicas = [...new Set(urls)];
        let hechos = 0;
        for (const u of unicas) {
            try { await fetch(u, { credentials: 'same-origin', headers: { Accept: 'text/html' } }); } catch (_) {}   // el Service Worker guarda las respuestas HTML
            hechos++;
            if (avance) avance(hechos, unicas.length);
        }
        return hechos;
    }

    window.POOffline = { activo, encolar, listar, vaciar, activar, desactivar, precargar, resolver };

    // Al cerrar sesión se borran las copias guardadas: en un equipo compartido no deben quedar páginas de otro usuario
    document.addEventListener('click', e => {
        if (e.target.closest('a[href="/logout"]') && window.caches) caches.keys().then(ks => ks.filter(k => k.startsWith('po-')).forEach(k => caches.delete(k)));
    }, true);

    window.addEventListener('online', () => { vaciar(); pintar(); });
    window.addEventListener('offline', pintar);
    document.addEventListener('DOMContentLoaded', () => {
        if (!activo()) return;
        registrar();
        pintar();
        vaciar();
        setInterval(vaciar, 60000);
    });
})();
