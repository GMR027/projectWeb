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
// ============================================================
(function () {
    'use strict';
    const registros = {};
    let overlay = null, foco = null, burbuja = null, activo = null, idx = 0, pasos = [];

    const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const yaVisto = id => { try { return localStorage.getItem('tour-' + id) === '1'; } catch (e) { return false; } };
    const marcarVisto = id => { try { localStorage.setItem('tour-' + id, '1'); } catch (e) { /* sin almacenamiento */ } };

    function montar() {
        desmontar();
        overlay = document.createElement('div'); overlay.className = 'tour-overlay'; overlay.hidden = false;
        foco = document.createElement('div'); foco.className = 'tour-foco';
        burbuja = document.createElement('div'); burbuja.className = 'tour-burbuja'; burbuja.setAttribute('role', 'dialog');
        overlay.append(foco, burbuja);
        document.body.appendChild(overlay);
        document.addEventListener('keydown', teclas, true);
        window.addEventListener('resize', repintar);
    }

    function desmontar() {
        if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
        overlay = foco = burbuja = null;
        document.removeEventListener('keydown', teclas, true);
        window.removeEventListener('resize', repintar);
        activo = null;
    }

    function teclas(e) {
        if (!overlay) return;
        if (e.key === 'Escape') terminar(false);
        else if (e.key === 'ArrowRight' || e.key === 'Enter') siguiente();
        else if (e.key === 'ArrowLeft') anterior();
        e.stopPropagation();   // el tour se queda con el teclado (no dispara atajos de la herramienta)
    }

    function rectSeguro(el) {
        try { const r = el.getBoundingClientRect(); return r.width || r.height ? r : null; } catch (e) { return null; }
    }

    function pintar(i) {
        const p = pasos[i];
        if (!p) return terminar(true);
        if (activo && activo.alSalir) { try { activo.alSalir(); } catch (e) { /* seguir */ } }
        activo = p;
        if (p.alEntrar) { try { p.alEntrar(); } catch (e) { /* seguir */ } }
        const el = p.sel ? document.querySelector(p.sel) : null;
        const r = el ? rectSeguro(el) : null;
        burbuja.hidden = false;
        if (r) {
            // lugar para la burbuja: debajo si cabe, si no arriba; si el elemento es muy ancho, a la derecha
            const M = 14, ANCHO = Math.min(340, window.innerWidth - 32);
            foco.className = 'tour-foco';
            foco.style.top = (r.top - 4) + 'px'; foco.style.left = (r.left - 4) + 'px';
            foco.style.width = (r.width + 8) + 'px'; foco.style.height = (r.height + 8) + 'px';
            let arriba = r.top + r.height + M + 150 > window.innerHeight && r.top > 200;   // ¿cabe debajo?
            const ladoDerecho = r.width > window.innerWidth * .55 && r.left + r.width + ANCHO + M * 2 < window.innerWidth;
            burbuja.style.width = ''; burbuja.style.maxWidth = ANCHO + 'px';
            if (ladoDerecho) { burbuja.style.left = (r.left + r.width + M) + 'px'; burbuja.style.top = Math.max(M, Math.min(r.top, window.innerHeight - 260)) + 'px'; burbuja.style.transform = ''; }
            else {
                burbuja.style.left = Math.max(M, Math.min(r.left, window.innerWidth - ANCHO - M)) + 'px';
                if (arriba) { burbuja.style.top = ''; burbuja.style.bottom = (window.innerHeight - r.top + M) + 'px'; }
                else { burbuja.style.bottom = ''; burbuja.style.top = (r.top + r.height + M) + 'px'; }
            }
            if (p.alCentro !== false) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        } else {
            // paso sin elemento: burbuja centrada, sin foco visible
            foco.className = 'tour-foco tour-centrado';
            foco.style.top = '50%'; foco.style.left = '50%'; foco.style.width = '0'; foco.style.height = '0';
            const ANCHO = Math.min(400, window.innerWidth - 40);
            burbuja.style.top = ''; burbuja.style.bottom = ''; burbuja.style.left = '';
            burbuja.style.width = ANCHO + 'px'; burbuja.style.maxWidth = ANCHO + 'px';
            burbuja.style.transform = 'translate(-50%, -50%)';
            foco.style.top = (window.innerHeight / 2) + 'px'; foco.style.left = (window.innerWidth / 2) + 'px';
        }
        burbuja.innerHTML =
            '<header>' + (p.icono ? '<i class="fa-solid ' + esc(p.icono) + '"></i>' : '') + '<b>' + esc(p.titulo) + '</b></header>' +
            '<p>' + (p.texto || '') + '</p>' +
            '<div class="tour-pie">' +
            '<span class="tour-puntos">' + pasos.map((_, k) => '<span class="' + (k === i ? 'activo' : '') + '"></span>').join('') + '</span>' +
            '<button type="button" class="tour-saltar">Saltar</button>' +
            (i > 0 ? '<button type="button" class="btn btn-sm btn-ghost tour-ant">&larr; Anterior</button>' : '') +
            '<button type="button" class="btn btn-sm btn-primary tour-sig">' + (i === pasos.length - 1 ? 'Listo <i class="fa-solid fa-check"></i>' : 'Siguiente <i class="fa-solid fa-chevron-right"></i>') + '</button>' +
            '</div>';
        burbuja.querySelector('.tour-sig').addEventListener('click', siguiente);
        const ant = burbuja.querySelector('.tour-ant');
        if (ant) ant.addEventListener('click', anterior);
        burbuja.querySelector('.tour-saltar').addEventListener('click', () => terminar(false));
        const boton = burbuja.querySelector('.tour-sig');
        if (boton) boton.focus({ preventScroll: true });
        idx = i;
    }

    const repintar = () => { if (overlay) pintar(idx); };
    const anterior = () => { if (idx > 0) pintar(idx - 1); };
    const siguiente = () => { if (idx < pasos.length - 1) pintar(idx + 1); else terminar(true); };

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
            const lanzar = () => { if ((abrir) || (auto && !yaVisto(id))) iniciar(id); };
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
