/* Piezas comunes de los editores (vPlan modo diseño, marcas del Editor y Estampas). Sin build.
   Antes cada editor tenía su copia de deshacer/rehacer, aviso de salida sin guardar y rasterizado de SVG. */
(function () {
    'use strict';
    const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());
    const debounce = (f, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), ms); }; };
    const guardado = (k, d) => { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch (_) { return d; } };
    const guarda = (k, v) => { try { localStorage.setItem(k, v); } catch (_) { /* sin almacenamiento */ } };

    /**
     * Deshacer/rehacer por fotos. foto() devuelve {k, ...datos} (k = texto que se compara para saber si algo cambió) o null si aún no hay nada que guardar.
     * aplica(foto) deja el editor como estaba en esa foto. Tras cada cambio hay que llamar a programar() (o a revisa() para fijarlo ya).
     */
    function historial({ foto, aplica, max = 100, espera = 350 }) {
        let pas = [], fut = [], ult = foto(), timer = null;
        const revisa = () => {
            clearTimeout(timer);
            const a = foto();
            if (!a || !ult || a.k === ult.k) return;
            pas.push(ult); if (pas.length > max) pas.shift(); fut = []; ult = a;
        };
        const reinicia = () => { clearTimeout(timer); pas = []; fut = []; ult = foto(); };
        const programar = () => { clearTimeout(timer); timer = setTimeout(revisa, espera); };
        const deshacer = () => { revisa(); if (!pas.length) return; fut.push(ult); ult = pas.pop(); aplica(ult); };
        const rehacer = () => { revisa(); if (!fut.length) return; pas.push(ult); ult = fut.pop(); aplica(ult); };
        return { revisa, reinicia, programar, deshacer, rehacer, hayPasado: () => pas.length > 0, hayFuturo: () => fut.length > 0 };
    }

    /**
     * Ctrl+Z / Ctrl+Y / Ctrl+Mayús+Z. En los cuadros de texto vale el deshacer del navegador (salvo range, color y checkbox).
     * activo() permite apagarlo (pestaña oculta); antes(e, tecla) atiende otros atajos y devuelve true si ya los resolvió (p. ej. Ctrl+S).
     */
    function atajos({ deshacer, rehacer, activo = () => true, antes = null }) {
        document.addEventListener('keydown', e => {
            if (!activo() || !(e.ctrlKey || e.metaKey)) return;
            const k = e.key.toLowerCase();
            if (antes && antes(e, k)) return;
            if (k !== 'z' && k !== 'y') return;
            const t = e.target;
            if (/^(INPUT|TEXTAREA)$/.test(t.tagName) && !['range', 'color', 'checkbox'].includes(t.type)) return;
            e.preventDefault();
            (k === 'y' || e.shiftKey) ? rehacer() : deshacer();
        });
    }

    /**
     * Al salir con cambios sin guardar: modal de la aplicación (el aviso del navegador queda solo para cerrar la pestaña o recargar).
     * sucio() dice si hay cambios; guardar() guarda y devuelve false si falló; limpiar() marca todo como guardado antes de irse.
     */
    function avisoSalir({ sucio, guardar, limpiar, titulo = '¿Salir sin guardar?', mensaje = 'Tienes cambios sin guardar. Si sales ahora se perderán.' }) {
        document.addEventListener('click', async e => {
            const a = e.target.closest('a[href]');
            if (!a || !sucio() || a.target === '_blank' || e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
            if (a.getAttribute('href').startsWith('#') || a.hasAttribute('download')) return;
            e.preventDefault();
            const r = await window.confirmarModal({ titulo, mensaje, tono: 'aviso', icono: 'fa-floppy-disk', aceptar: 'Salir sin guardar', iconoBoton: 'fa-right-from-bracket', extra: 'Guardar y salir', cancelar: 'Seguir editando' });
            if (r === 'extra') { if (await guardar() !== false) { limpiar(); location.href = a.href; } return; }
            if (r) { limpiar(); location.href = a.href; }
        });
        window.addEventListener('beforeunload', e => { if (sucio()) { e.preventDefault(); e.returnValue = ''; } });
    }

    /** Blob de una imagen. Los SVG pasan a PNG (lado mayor = maxLado) para que el PDF y la pantalla los dibujen igual; el resto se copia tal cual */
    async function rasterizar(url, maxLado = 1024) {
        if (!/\.svg(\?|$)/i.test(url)) { const r = await fetch(url); if (!r.ok) throw new Error('No se pudo leer la imagen'); return r.blob(); }
        const img = await new Promise((ok, ko) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => ko(new Error('No se pudo leer el SVG')); i.src = url; });
        const w = img.naturalWidth || 128, h = img.naturalHeight || 128, k = maxLado / Math.max(w, h);
        const cv = document.createElement('canvas'); cv.width = Math.round(w * k); cv.height = Math.round(h * k);
        cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
        return new Promise(ok => cv.toBlob(ok, 'image/png'));
    }

    /**
     * De una lista de candidatos {pos, ...} el más cercano a `valor` dentro del umbral: {d, ref} (d = pos - valor) o null.
     * Con empate gana el primero; `ultimoGana` hace que gane el último (como hacía el editor Konva).
     */
    function masCercano(valor, candidatos, umbral, ultimoGana = false) {
        let mejor = null;
        candidatos.forEach(c => {
            const d = c.pos - valor, a = Math.abs(d);
            if (a <= umbral && (!mejor || (ultimoGana ? a <= Math.abs(mejor.d) : a < Math.abs(mejor.d)))) mejor = { d, ref: c };
        });
        return mejor;
    }

    /**
     * Cambia el texto de una fila por un cuadro para renombrarla (Enter o salir del cuadro = aceptar, Escape = cancelar).
     * alTerminar(ok, valorSinEspacios) se llama una sola vez. `detener`: que las teclas no sigan hacia los atajos de la página.
     */
    function renombrarEnLinea(elemento, { valor = '', max = 60, clase = '', placeholder = '', detener = false, alTerminar }) {
        const inp = document.createElement('input');
        inp.type = 'text'; inp.maxLength = max; inp.value = valor; inp.className = clase;
        if (placeholder) inp.placeholder = placeholder;
        elemento.replaceWith(inp);
        inp.focus(); inp.select();
        let hecho = false;
        const fin = ok => { if (hecho) return; hecho = true; alTerminar(ok, inp.value.trim()); };
        inp.addEventListener('blur', () => fin(true));
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') fin(true); else if (e.key === 'Escape') fin(false); if (detener) e.stopPropagation(); });
        return inp;
    }

    /**
     * Reordenar filas arrastrándolas. `raiz` recibe los eventos; `item` es el selector de la fila; clave(li) identifica la fila.
     * alSoltar(claveOrigen, claveDestino, arriba) — arriba = se soltó en la mitad superior de la fila destino.
     */
    function listaOrdenable(raiz, { item, clave, claseArrastrando, claseArriba, claseAbajo, alSoltar }) {
        let origen = null;
        const fila = e => e.target.closest && e.target.closest(item);
        const arriba = (e, li) => { const r = li.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; };
        const sobre = () => raiz.querySelectorAll(item).forEach(x => x.classList.remove(claseArriba, claseAbajo));
        raiz.addEventListener('dragstart', e => {
            const li = fila(e); if (!li) return;
            origen = clave(li);
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', String(origen)); } catch (_) { /* sin datos */ }
            li.classList.add(claseArrastrando);
        });
        raiz.addEventListener('dragend', () => { origen = null; raiz.querySelectorAll(item).forEach(x => x.classList.remove(claseArrastrando, claseArriba, claseAbajo)); });
        raiz.addEventListener('dragover', e => {
            const li = fila(e); if (!li || origen === null) return;
            e.preventDefault(); sobre();
            li.classList.add(arriba(e, li) ? claseArriba : claseAbajo);
        });
        raiz.addEventListener('drop', e => {
            const li = fila(e); if (!li || origen === null) return;
            e.preventDefault();
            const alto = arriba(e, li), o = origen;
            sobre();
            alSoltar(o, clave(li), alto);
        });
    }

    window.KitEdicion = { esc, post, debounce, guardado, guarda, historial, atajos, avisoSalir, rasterizar, masCercano, renombrarEnLinea, listaOrdenable };
})();
