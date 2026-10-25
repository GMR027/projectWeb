// ============================================================
// Tour de guía rápida — pieza compartida entre herramientas (2026-10-25)
// Cómo se usa en una vista:
//   <script src="/vendor/tour.js?v=…"></script>
//   <script>window.Tour.registrar('vplan-diseno', { auto: true, pasos: [
//       { sel: '#vp-dis-guardar', titulo: 'Guarda tu diseño', texto: '…', icono: 'fa-floppy-disk' },
//       { sel: null, titulo: '…', texto: '…' }                 // paso centrado, sin elemento
//   ]});</script>
//   <button data-tour="vplan-diseno">Guía rápida</button>       // lo vuelve a abrir cuando quieras
// Disparo: la primera visita por usuario y herramienta (localStorage «tour-<id>»), el botón
// [data-tour] o ?tour=1 en la URL. Un paso puede llevar `alEntrar` (p. ej. abrir una pestaña
// antes de destacar su panel) y `alSalir`.
// Posicionamiento (falla de 2026-10-25, corregida): antes se posicionaba ANTES de acercar el
// elemento, así que un paso que estaba más abajo de la página dibujaba la burbuja a y = 3000
// con una ventana de 700 y quedaba fuera de la vista. Ahora: se acerca el elemento, se mide,
// y la burbuja SIEMPRE queda dentro de la ventana (clamp en x e y); scroll y resize recolocan.
// ============================================================
(function () {
    'use strict';
    const registros = {};
    let overlay = null, foco = null, burbuja = null, activo = null, idx = 0, pasos = [];

    const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const clamp = (v, a, b) => Math.max(a, Math.min(v, Math.max(a, b)));   // b nunca por debajo de a
    const yaVisto = id => { try { return localStorage.getItem('tour-' + id) === '1'; } catch (e) { return false; } };
    const marcarVisto = id => { try { localStorage.setItem('tour-' + id, '1'); } catch (e) { /* sin almacenamiento */ } };

    function montar() {
        desmontar();
        overlay = document.createElement('div'); overlay.className = 'tour-overlay';
        foco = document.createElement('div'); foco.className = 'tour-foco';
        burbuja = document.createElement('div'); burbuja.className = 'tour-burbuja'; burbuja.setAttribute('role', 'dialog');
        overlay.append(foco, burbuja);
        document.body.appendChild(overlay);
        document.addEventListener('keydown', teclas, true);
        window.addEventListener('resize', repintar);
        window.addEventListener('scroll', repintar, true);   // el tour recoloca, no se cierra al subir/bajar
    }

    function desmontar() {
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        overlay = foco = burbuja = null;
        document.removeEventListener('keydown', teclas, true);
        window.removeEventListener('resize', repintar);
        window.removeEventListener('scroll', repintar, true);
        activo = null;
    }

    function teclas(e) {
        if (!overlay) return;
        if (e.key === 'Escape') terminar();
        else if (e.key === 'ArrowRight' || e.key === 'Enter') siguiente();
        else if (e.key === 'ArrowLeft') anterior();
        e.stopPropagation();   // el tour se queda con el teclado (no dispara atajos de la herramienta)
    }

    function rectSeguro(el) {
        try { const r = el.getBoundingClientRect(); return r.width || r.height ? r : null; } catch (e) { return null; }
    }

    function pintar(i) {
        const p = pasos[i];
        if (!p) return terminar();
        if (activo && activo.alSalir) { try { activo.alSalir(); } catch (e) { /* seguir */ } }
        activo = p;
        if (p.alEntrar) { try { p.alEntrar(); } catch (e) { /* seguir */ } }
        burbuja.innerHTML =
            '<header>' + (p.icono ? '<i class="fa-solid ' + esc(p.icono) + '"></i>' : '') + '<b>' + esc(p.titulo) + '</b></header>' +
            '<p>' + (p.texto || '') + '</p>' +
            '<div class="tour-pie">' +
            '<span class="tour-puntos">' + pasos.map((_, k) => '<span class="' + (k === i ? 'activo' : '') + '"></span>').join('') + '</span>' +
            '<button type="button" class="tour-saltar">Saltar</button>' +
            (i > 0 ? '<button type="button" class="btn btn-sm btn-ghost tour-ant">&larr; Anterior</button>' : '') +
            '<button type="button" class="btn btn-sm btn-primary tour-sig">' + (i === pasos.length - 1 ? 'Listo <i class="fa-solid fa-check"></i>' : 'Siguiente <i class="fa-solid fa-chevron-right"></i>') + '</button>' +
            '</div>';
        burbuja.hidden = false;
        burbuja.querySelector('.tour-sig').addEventListener('click', siguiente);
        const ant = burbuja.querySelector('.tour-ant');
        if (ant) ant.addEventListener('click', anterior);
        burbuja.querySelector('.tour-saltar').addEventListener('click', terminar);
        const boton = burbuja.querySelector('.tour-sig');
        if (boton) boton.focus({ preventScroll: true });
        idx = i;
        posicionar();
    }

    /**
     * Coloca el foco y la burbuja del paso actual:
     *  1) si el elemento no está razonablemente a la vista, se le acerca (instantáneo) ANTES de medir.
     *     Si el elemento es más alto que la ventana, se acerca por su inicio (centrar no ayuda);
     *  2) el foco se RECORTA a lo visible: un panel más alto o ancho que la ventana ya no dibuja su
     *     marco por fuera de la vista;
     *  3) la burbuja se mide con su contenido y SIEMPRE queda dentro de la ventana (clamp en x e y,
     *     con el max-height de su CSS si la ventana es muy baja);
     *  4) solo se acerca si falta, para que el scroll que dispara este recolocado no cree un bucle.
     */
    function posicionar() {
        const p = pasos[idx];
        if (!p || !overlay) return;
        const M = 14;
        const el = p.sel ? document.querySelector(p.sel) : null;
        if (el && p.alCentro !== false) {
            const r0 = el.getBoundingClientRect();
            if (r0.height > innerHeight - M * 2) {
                if (r0.top > M * 2 || r0.bottom < innerHeight - M * 2) { try { el.scrollIntoView({ block: 'start' }); } catch (e) { /* seguir */ } }
            } else if (r0.top < innerHeight * .18 || r0.bottom > innerHeight * .82) {
                try { el.scrollIntoView({ block: 'center' }); } catch (e) { /* seguir */ }
            }
        }
        const r = el ? rectSeguro(el) : null;
        if (r) {
            // Foco: lo visible del elemento (si es más alto o ancho que la ventana, el marco se recorta)
            const ft = Math.max(r.top, M), fb = Math.min(r.bottom, innerHeight - M);
            const fl = Math.max(r.left, M), fr = Math.min(r.right, innerWidth - M);
            const rv = Math.max(0, fr - fl), rhv = Math.max(0, fb - ft);
            foco.className = 'tour-foco';
            foco.style.top = (ft - 4) + 'px'; foco.style.left = (fl - 4) + 'px';
            foco.style.width = (rv + 8) + 'px'; foco.style.height = (rhv + 8) + 'px';

            const bw = burbuja.offsetWidth, bh = burbuja.offsetHeight;
            const centroX = fl + rv / 2 - bw / 2;
            const cabeAbajo = fb + M + bh <= innerHeight - M;
            const cabeArriba = ft - M - bh >= M;
            const cabeDerecha = fl + rv + M + bw <= innerWidth - M;
            burbuja.style.width = ''; burbuja.style.transform = ''; burbuja.style.bottom = '';
            burbuja.style.maxWidth = Math.min(340, innerWidth - M * 2) + 'px';
            let x, y;
            if (cabeDerecha && (rv > innerWidth * .45 || !cabeAbajo)) {    // a la derecha (paneles laterales, elementos anchos)
                x = fl + rv + M;
                y = clamp(ft, M, innerHeight - bh - M);
            } else if (cabeAbajo) {                                        // debajo
                x = clamp(centroX, M, innerWidth - bw - M);
                y = fb + M;
            } else if (cabeArriba) {                                       // arriba
                x = clamp(centroX, M, innerWidth - bw - M);
                y = ft - bh - M;
            } else {                                                      // no cabe en ningún lado: dentro, con el aire que haya
                x = cabeDerecha ? fl + rv + M : clamp(centroX, M, innerWidth - bw - M);
                y = clamp(fb + M, M, innerHeight - bh - M);
            }
            burbuja.style.left = clamp(x, M, innerWidth - bw - M) + 'px';
            burbuja.style.top = clamp(y, M, innerHeight - bh - M) + 'px';
        } else {
            // Paso sin elemento: burbuja centrada (left/top explícitos: sin ellos el translate la manda a -50 % de 0)
            const ANCHO = Math.min(400, innerWidth - 40);
            foco.className = 'tour-foco tour-centrado';
            foco.style.top = (innerHeight / 2) + 'px'; foco.style.left = (innerWidth / 2) + 'px';
            foco.style.width = '0'; foco.style.height = '0';
            burbuja.style.bottom = '';
            burbuja.style.width = ANCHO + 'px'; burbuja.style.maxWidth = ANCHO + 'px';
            burbuja.style.left = (innerWidth / 2) + 'px';
            burbuja.style.top = (innerHeight / 2) + 'px';
            burbuja.style.transform = 'translate(-50%, -50%)';
        }
    }

    const repintar = () => { if (overlay) posicionar(); };   // scroll/resize: solo recolocar (alEntrar ya corrió)
    const anterior = () => { if (idx > 0) pintar(idx - 1); };
    const siguiente = () => { if (idx < pasos.length - 1) pintar(idx + 1); else terminar(); };

    function terminar() {
        // Se marca como vista aunque el usuario la salte: una guía que se abre sola cada visita molesta
        const id = activoId;
        desmontar();
        if (id) marcarVisto(id);
    }

    let activoId = null;
    function iniciar(id) {
        const def = registros[id];
        if (!def || !def.pasos || !def.pasos.length) return;
        activoId = id;
        pasos = def.pasos;
        idx = 0;
        montar();
        pintar(0);
    }

    window.Tour = {
        registrar(id, def) {
            registros[id] = def;
            const auto = def.auto !== false;
            const forzar = new URLSearchParams(location.search).get('tour');
            const abrir = forzar === id || (forzar === '1' && auto);
            const lanzar = () => { if (abrir || (auto && !yaVisto(id))) iniciar(id); };
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', lanzar, { once: true });
            else lanzar();
        },
        iniciar,
        cerrar: terminar,
        yaVisto,
    };

    // Botón «Guía rápida» de cualquier vista: <button data-tour="id">
    document.addEventListener('click', e => {
        const b = e.target.closest('[data-tour]');
        if (!b) return;
        e.preventDefault();
        iniciar(b.dataset.tour);
    });
})();
