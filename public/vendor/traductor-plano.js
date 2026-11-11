/* Traductor · Plano en el lugar: extrae los textos del PDF con PDF.js, los traduce con el motor del servidor
   y escribe la traducción encima de cada texto, en su misma posición. El PDF no se sube. */
(function () {
    'use strict';
    const raiz = document.getElementById('trd-plano');
    if (!raiz) return;
    const $ = (id) => document.getElementById(id);
    const proyecto = document.getElementById('trd')?.dataset.proyecto || new URLSearchParams(location.search).get('proyecto') || '';
    const de = $('trd-de'), a = $('trd-a');
    const archivo = $('trp-archivo'), fondo = $('trp-fondo'), paginas = $('trp-paginas');
    const btn = $('trp-traducir'), descargar = $('trp-descargar'), cancelar = $('trp-cancelar');
    const estado = $('trp-estado'), barra = $('trp-barra'), previa = $('trp-previa'), iframe = $('trp-iframe');

    let cancelado = false, ocupado = false, documento = null, urlPdf = null, nombreArchivo = 'plano';
    const medidor = document.createElement('canvas').getContext('2d');
    const tieneLetras = (s) => /\p{L}/u.test(s);
    const limpiar = (s) => String(s).replace(/\s+/g, ' ').trim();

    if (window.pdfjsLib) window.pdfjsLib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.js';

    const decir = (t) => { estado.textContent = t; };
    const ceder = () => new Promise((r) => setTimeout(r, 0));
    const anchoTexto = (texto, tam, fuente) => {
        medidor.font = `${tam}px ${fuente}`;
        return medidor.measureText(texto).width;
    };

    function paginasElegidas(total) {
        const crudo = paginas.value.trim();
        if (!crudo) return Array.from({ length: total }, (_, i) => i + 1);
        const salida = new Set();
        for (const parte of crudo.split(',')) {
            const m = parte.trim().match(/^(\d+)(?:\s*-\s*(\d+))?$/);
            if (!m) continue;
            let x = parseInt(m[1], 10), y = m[2] ? parseInt(m[2], 10) : x;
            if (!Number.isFinite(x) || !Number.isFinite(y)) continue;
            if (x > y) [x, y] = [y, x];
            for (let i = Math.max(1, x); i <= Math.min(total, y); i++) salida.add(i);
        }
        return Array.from(salida).sort((x, y) => x - y);
    }

    async function traducirLotes(textos) {
        const salida = new Map();
        const unicos = Array.from(new Set(textos));
        const MAX = 180, MAX_CHARS = 18000;
        let i = 0;
        while (i < unicos.length) {
            if (cancelado) throw new Error('Traducción cancelada.');
            const lote = [];
            let chars = 0;
            while (i < unicos.length && lote.length < MAX && (chars + unicos[i].length <= MAX_CHARS || !lote.length)) {
                lote.push(unicos[i]); chars += unicos[i].length; i++;
            }
            const fd = new FormData();
            fd.append('proyecto', proyecto);
            fd.append('de', de.value);
            fd.append('a', a.value);
            fd.append('textos', JSON.stringify(lote));
            const r = await fetch('/api/traductor/lista', { method: 'POST', body: fd, credentials: 'same-origin' });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || !Array.isArray(j.textos)) throw new Error(j.error || 'No se pudo traducir el lote de textos.');
            lote.forEach((t, k) => salida.set(t, String(j.textos[k] ?? t)));
            decir(`Traduciendo textos… ${Math.min(i, unicos.length)} de ${unicos.length}`);
            await ceder();
        }
        return salida;
    }

    const aplicar = (m, x, y) => [m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5]];

    function colorFondo(ctx, minX, minY, maxX, maxY) {
        const puntos = [
            [minX - 3, (minY + maxY) / 2], [maxX + 3, (minY + maxY) / 2],
            [(minX + maxX) / 2, minY - 3], [(minX + maxX) / 2, maxY + 3],
            [minX + 2, minY + 2], [maxX - 2, minY + 2], [minX + 2, maxY - 2], [maxX - 2, maxY - 2],
        ];
        let r = 0, g = 0, b = 0, n = 0;
        for (const [x, y] of puntos) {
            if (x < 0 || y < 0 || x >= ctx.canvas.width || y >= ctx.canvas.height) continue;
            try {
                const d = ctx.getImageData(Math.floor(x), Math.floor(y), 1, 1).data;
                r += d[0]; g += d[1]; b += d[2]; n++;
            } catch (e) { /* canvas contaminado */ }
        }
        if (!n) return { fill: '#ffffff', ink: '#111827' };
        r = Math.round(r / n); g = Math.round(g / n); b = Math.round(b / n);
        const lum = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return { fill: `rgb(${r},${g},${b})`, ink: lum < .48 ? '#ffffff' : '#111827' };
    }

    function pintarTexto(ctx, item, traduccion) {
        const m = item.tx;
        const esquinas = [[0, 0], [item.width, 0], [0, item.height], [item.width, item.height]].map(([x, y]) => aplicar(m, x, y));
        const xs = esquinas.map(p => p[0]), ys = esquinas.map(p => p[1]);
        let minX = Math.min(...xs), maxX = Math.max(...xs), minY = Math.min(...ys), maxY = Math.max(...ys);
        if (maxX < 0 || minX > ctx.canvas.width || maxY < 0 || minY > ctx.canvas.height) return;
        const pad = 1.5;
        let ink = '#111827';
        ctx.globalAlpha = 1;
        if (fondo.value !== 'sin') {
            const c = fondo.value === 'auto' ? colorFondo(ctx, minX, minY, maxX, maxY) : { fill: '#ffffff', ink: '#111827' };
            ink = c.ink;
            ctx.fillStyle = c.fill;
            ctx.fillRect(minX - pad, minY - pad, Math.max(1, maxX - minX) + pad * 2, Math.max(1, maxY - minY) + pad * 2);
        }
        const disponible = Math.max(4, item.width);
        let tam = Math.max(3, item.fontSize);
        const minimo = Math.max(3.5, item.fontSize * .45);
        while (tam > minimo && anchoTexto(traduccion, tam, 'Arial') > disponible) tam -= .25;
        ctx.save();
        ctx.transform(m[0], m[1], m[2], m[3], m[4], m[5]);
        ctx.fillStyle = ink;
        ctx.font = `${tam}px Arial, Helvetica, sans-serif`;
        ctx.textBaseline = 'alphabetic';
        ctx.fillText(traduccion, 0, 0);
        ctx.restore();
    }

    function crearPdf(w, h) {
        const { jsPDF } = window.jspdf || {};
        if (!jsPDF) throw new Error('No se pudo cargar el generador de PDF.');
        return new jsPDF({ unit: 'pt', format: [w, h], orientation: w > h ? 'landscape' : 'portrait', compress: true });
    }

    async function ejecutar() {
        if (ocupado) return;
        if (!window.pdfjsLib) { decir('No se pudo cargar el lector de PDF.'); return; }
        const f = archivo.files[0];
        if (!f) { decir('Elige un plano PDF.'); return; }
        if (f.size > 25 * 1024 * 1024) { decir('El PDF supera los 25 MB.'); return; }
        ocupado = true; cancelado = false;
        btn.disabled = true; cancelar.hidden = false; descargar.hidden = true; previa.hidden = true; barra.hidden = false; barra.value = 0;
        if (urlPdf) { URL.revokeObjectURL(urlPdf); urlPdf = null; }
        nombreArchivo = f.name.replace(/\.pdf$/i, '') + '-' + a.value.toUpperCase() + '.pdf';
        try {
            decir('Leyendo el PDF…');
            const datos = new Uint8Array(await f.arrayBuffer());
            documento = await window.pdfjsLib.getDocument({ data: datos }).promise;
            const total = documento.numPages;
            if (total > 40) throw new Error('El PDF tiene ' + total + ' páginas; el máximo para traducir en el lugar es 40.');
            const elegidas = paginasElegidas(total);
            if (!elegidas.length) throw new Error('El rango de páginas no es válido.');

            const paginasDatos = [], textos = [];
            for (const n of elegidas) {
                if (cancelado) throw new Error('Traducción cancelada.');
                const page = await documento.getPage(n);
                const v1 = page.getViewport({ scale: 1 });
                const escala = Math.max(1.2, Math.min(2.2, 3600 / Math.max(1, v1.width, v1.height)));
                const viewport = page.getViewport({ scale: escala });
                const contenido = await page.getTextContent();
                const items = [];
                for (const it of contenido.items) {
                    if (!it.str || !tieneLetras(it.str)) continue;
                    const texto = limpiar(it.str);
                    if (texto.length < 2 || !it.width || !it.height) continue;
                    const tx = window.pdfjsLib.Util.transform(viewport.transform, it.transform);
                    const fontSize = Math.max(2, Math.hypot(it.transform[2], it.transform[3]));
                    items.push({ texto, tx, width: Math.max(1, it.width), height: Math.max(1, it.height), fontSize });
                    textos.push(texto);
                }
                paginasDatos.push({ page, viewport, items, w: v1.width, h: v1.height });
                decir(`Leyendo página ${n} de ${total}…`);
                barra.value = (paginasDatos.length / elegidas.length) * .25;
                await ceder();
            }
            if (!textos.length) throw new Error('El PDF no tiene texto seleccionable: parece escaneado o el texto está convertido a curvas.');

            const unicos = Array.from(new Set(textos));
            if (unicos.length > 6000) throw new Error('El plano tiene demasiados textos (' + unicos.length + '). Divide el PDF en partes.');
            const traducciones = await traducirLotes(unicos);
            decir('Dibujando la traducción…');

            let doc = null;
            for (let i = 0; i < paginasDatos.length; i++) {
                if (cancelado) throw new Error('Traducción cancelada.');
                const p = paginasDatos[i];
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.floor(p.viewport.width));
                canvas.height = Math.max(1, Math.floor(p.viewport.height));
                const ctx = canvas.getContext('2d');
                await p.page.render({ canvasContext: ctx, viewport: p.viewport }).promise;
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                for (const it of p.items) {
                    const tr = traducciones.get(it.texto);
                    if (!tr || tr === it.texto || !tieneLetras(tr)) continue;
                    pintarTexto(ctx, it, tr);
                }
                if (!doc) doc = crearPdf(p.w, p.h); else doc.addPage([p.w, p.h], p.w > p.h ? 'landscape' : 'portrait');
                doc.addImage(canvas.toDataURL('image/jpeg', .88), 'JPEG', 0, 0, p.w, p.h);
                barra.value = .25 + ((i + 1) / paginasDatos.length) * .75;
                decir(`Dibujando página ${i + 1} de ${paginasDatos.length}…`);
                await ceder();
            }
            if (!doc) throw new Error('No se pudo generar el PDF traducido.');
            urlPdf = doc.output('bloburl');
            iframe.src = urlPdf;
            previa.hidden = false;
            descargar.hidden = false;
            descargar.dataset.nombre = nombreArchivo;
            decir('Listo. Revisa la vista previa y descarga el PDF.');
            barra.value = 1;
        } catch (e) {
            decir('✘ ' + (e && e.message ? e.message : 'No se pudo traducir el plano.'));
        } finally {
            ocupado = false; btn.disabled = false; cancelar.hidden = true; barra.hidden = true;
        }
    }

    btn.addEventListener('click', ejecutar);
    cancelar.addEventListener('click', () => { cancelado = true; decir('Cancelando…'); });
    descargar.addEventListener('click', () => {
        if (!urlPdf) return;
        const l = document.createElement('a');
        l.href = urlPdf; l.download = descargar.dataset.nombre || 'plano-traducido.pdf';
        document.body.appendChild(l); l.click(); l.remove();
    });
    archivo.addEventListener('change', () => {
        previa.hidden = true; descargar.hidden = true; decir('');
        if (urlPdf) { URL.revokeObjectURL(urlPdf); urlPdf = null; }
        barra.value = 0;
    });
})();
