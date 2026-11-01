// vPlan: zoom de la lámina.
//   100 % = el tamaño real de la hoja (1 mm = 1 mm), que es como se ve la lámina en el PDF.
//   «Ajustar» la mete en el ancho disponible; el modo automático además sube el zoom si la letra
//   quedaría tan pequeña que no se puede leer (típico cuando la tabla tiene mucha información).
(function () {
    const sheet = document.getElementById('vp-sheet');
    if (!sheet) return;
    const cont = sheet.closest('.vp-hoja') || sheet.parentElement;
    const lbl = document.getElementById('vp-zoom-val');
    const lblFlot = document.getElementById('vp-zoom-flot-val');
    const anchoMm = parseFloat(sheet.dataset.ancho) || 432;
    const FISICO = anchoMm * 96 / 25.4;      // px del ancho real de la hoja (96 dpi)
    const MIN_LEIBLE = 10;                   // px: la letra más chica de una tabla tiene que leerse
    const MAX_Z = 2.5, MIN_Z = 0.3;
    const CLAVE = 'vp-zoom';

    let modo = 'auto';    // auto | fit | real | manual
    let manual = 1;
    let zActual = 1;

    try {
        const g = JSON.parse(localStorage.getItem(CLAVE) || 'null');
        if (g && g.modo) { modo = g.modo; manual = +g.manual || 1; }
    } catch (e) { }

    function guardar() {
        try { localStorage.setItem(CLAVE, JSON.stringify({ modo: modo, manual: manual })); } catch (e) { }
    }

    let enGesto = false;   // durante el pellizco no se re-mide la letra: se hace una vez al soltar

    function aplicar(z) {
        z = Math.max(MIN_Z, Math.min(MAX_Z, z));
        // En modo diseño la hoja tiene que caber en el panel: si no, las reglas se descuadran
        if (sheet.classList.contains('vp-diseno')) z = Math.min(z, zAjuste());
        zActual = z;
        sheet.style.setProperty('--vp-ancho-px', (FISICO * z) + 'px');
        if (lbl) lbl.textContent = Math.round(z * 100) + ' %';
        if (lblFlot) lblFlot.textContent = Math.round(z * 100) + ' %';
        if (!enGesto && window.vplanAjustar) window.vplanAjustar();
    }

    // ---------- gestos: pellizco, dos dedos y doble toque ----------
    // El zoom sigue siendo por ancho (100 % = tamaño real), así que el paneo se hace con el scroll del
    // contenedor. El punto que queda bajo los dedos se conserva: se guarda su posición dentro de la
    // hoja al empezar y se recoloca al terminar cada movimiento.
    let anclaGesto = null;

    const anchoReal = () => FISICO * zActual;
    const desfaseX = () => Math.max(0, (((cont && cont.clientWidth) || 0) - anchoReal()) / 2);

    function puntoDeContenido(cx, cy) {
        const r = cont.getBoundingClientRect();
        return {
            x: (cx - r.left - desfaseX() + cont.scrollLeft) / Math.max(1, anchoReal()),
            y: cy - r.top + cont.scrollTop,
        };
    }

    function llevarAPunto(a, cx, cy) {
        const r = cont.getBoundingClientRect();
        cont.scrollLeft = a.x * anchoReal() + desfaseX() - (cx - r.left);
        cont.scrollTop = a.y - (cy - r.top);
    }

    function zoomEn(cx, cy, z) {
        const a = puntoDeContenido(cx, cy);
        modo = 'manual';
        manual = Math.max(MIN_Z, Math.min(MAX_Z, z));
        aplicar(manual);
        manual = zActual;   // en modo diseño el zoom se limita a «Ajustar»
        llevarAPunto(a, cx, cy);
        guardar();
    }

    function dobleToque(cx, cy) {
        const za = zAjuste();
        // Si ya está acercado, vuelve a «Ajustar»; si no, acerca al doble (nunca menos del tamaño real).
        const objetivo = zActual > za * 1.25 ? za : Math.min(MAX_Z, Math.max(zActual * 2, 1));
        zoomEn(cx, cy, objetivo);
    }

    if (window.GestosLienzo && cont) {
        window.GestosLienzo.montar(cont, {
            zoomActual: () => zActual,
            min: MIN_Z, max: MAX_Z,
            alInicio: ({ cx, cy }) => { enGesto = true; anclaGesto = puntoDeContenido(cx, cy); },
            alMover: ({ z, cx, cy }) => {
                if (!anclaGesto) return;
                aplicar(z);
                modo = 'manual';
                manual = zActual;
                llevarAPunto(anclaGesto, cx, cy);
            },
            alFin: () => {
                anclaGesto = null;
                enGesto = false;
                modo = 'manual';
                manual = zActual;
                guardar();
                if (window.vplanAjustar) window.vplanAjustar();   // una sola medición al soltar
            },
            alDobleToque: dobleToque,
        });
    }

    // Zoom que encaja la hoja en el ancho disponible
    function zAjuste() {
        const w = (cont && cont.clientWidth) || window.innerWidth;
        return Math.max(MIN_Z, (w - 4) / FISICO);
    }

    // Letra más pequeña de las tablas, en cqw (independiente del zoom con el que se está viendo).
    // Solo cuentan las tablas que ajusta el vivo (las de tareas y las adicionales): el cajetín y las
    // estampas llevan su propio tamaño y no deben forzar el zoom.
    function letraCqw() {
        let min = Infinity;
        sheet.querySelectorAll('.vp-tab').forEach(t => {
            if (!t.style.getPropertyValue('--vp-pad')) return;
            const v = (t.style.fontSize || '').trim();
            if (!v.endsWith('cqw')) return;
            const n = parseFloat(v);
            if (n > 0 && n < min) min = n;
        });
        return min === Infinity ? null : min;
    }

    // Zoom necesario para que la letra más pequeña se lea (en px de pantalla)
    function zLegible() {
        const cqw = letraCqw();
        if (!cqw) return null;
        return MIN_LEIBLE / ((cqw / 100) * FISICO);
    }

    function modoActual() {
        if (modo === 'manual') return manual;
        if (modo === 'real') return 1;
        const za = zAjuste();
        if (modo === 'fit') return za;
        // automático: encaja, pero sube lo necesario para que la letra se lea
        const zl = zLegible();
        return zl && zl > za ? Math.min(MAX_Z, zl) : za;
    }

    let temporizador = null;
    function refrescar() {
        if (temporizador) clearTimeout(temporizador);
        aplicar(modoActual());
        // El ajuste en vivo fija la letra un par de después: se vuelve a medir por si la achicó
        temporizador = setTimeout(() => {
            if (modo === 'auto' || modo === 'fit') {
                const zl = zLegible();
                const za = zAjuste();
                const z = zl && zl > za ? Math.min(MAX_Z, zl) : za;
                if (Math.abs(z - zActual) > 0.01) aplicar(z);
            }
        }, 150);
    }

    function manual_(z) {
        modo = 'manual';
        manual = Math.max(MIN_Z, Math.min(MAX_Z, z));
        guardar();
        aplicar(manual);
    }

    const btnMenos = document.getElementById('vp-zoom-menos');
    const btnMas = document.getElementById('vp-zoom-mas');
    const btnFit = document.getElementById('vp-zoom-fit');
    const btnReal = document.getElementById('vp-zoom-real');
    if (btnMenos) btnMenos.addEventListener('click', () => manual_(zActual / 1.15));
    if (btnMas) btnMas.addEventListener('click', () => manual_(zActual * 1.15));
    if (btnFit) btnFit.addEventListener('click', () => { modo = 'fit'; guardar(); refrescar(); });
    if (btnReal) btnReal.addEventListener('click', () => { modo = 'real'; guardar(); refrescar(); });

    // Centrar la hoja en el espacio visible (útil después de acercar o al abrir en el celular)
    function centrar() {
        if (!cont) return;
        cont.scrollLeft = Math.max(0, (cont.scrollWidth - cont.clientWidth) / 2);
        cont.scrollTop = Math.max(0, (cont.scrollHeight - cont.clientHeight) / 2);
    }

    // Controles flotantes de la lámina (solo visibles en táctil, ver SCSS)
    const flot = document.querySelector('.vp-zoom-flot');
    if (flot) flot.addEventListener('click', e => {
        const b = e.target.closest('[data-vpz]');
        if (!b) return;
        const k = b.dataset.vpz;
        if (k === 'menos') manual_(zActual / 1.15);
        else if (k === 'mas') manual_(zActual * 1.15);
        else if (k === 'fit') { modo = 'fit'; guardar(); refrescar(); centrar(); }
        else if (k === 'centro') centrar();
    });

    // La rueda del mouse sobre la hoja hace zoom (con Ctrl para no quitarle el scroll a la página)
    sheet.addEventListener('wheel', e => {
        if (!e.ctrlKey && !e.metaKey) return;
        e.preventDefault();
        manual_(zActual * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
    }, { passive: false });

    let anchoPrevio = cont ? cont.clientWidth : 0;
    window.addEventListener('resize', () => {
        if (!cont) return;
        if (cont.clientWidth === anchoPrevio) return;
        anchoPrevio = cont.clientWidth;
        refrescar();
    });

    window.vplanZoom = { aplicar: aplicar, refrescar: refrescar, centrar: centrar, valor: () => zActual };
    refrescar();
    window.addEventListener('load', refrescar);
})();
