// vPlan: zoom de la lámina.
//   100 % = el tamaño real de la hoja (1 mm = 1 mm), que es como se ve la lámina en el PDF.
//   «Ajustar» la mete en el ancho disponible; el modo automático además sube el zoom si la letra
//   quedaría tan pequeña que no se puede leer (típico cuando la tabla tiene mucha información).
(function () {
    const sheet = document.getElementById('vp-sheet');
    if (!sheet) return;
    const cont = sheet.closest('.vp-hoja') || sheet.parentElement;
    const lbl = document.getElementById('vp-zoom-val');
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

    function aplicar(z) {
        z = Math.max(MIN_Z, Math.min(MAX_Z, z));
        // En modo diseño la hoja tiene que caber en el panel: si no, las reglas se descuadran
        if (sheet.classList.contains('vp-diseno')) z = Math.min(z, zAjuste());
        zActual = z;
        sheet.style.setProperty('--vp-ancho-px', (FISICO * z) + 'px');
        if (lbl) lbl.textContent = Math.round(z * 100) + ' %';
        if (window.vplanAjustar) window.vplanAjustar();
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

    window.vplanZoom = { aplicar: aplicar, refrescar: refrescar, valor: () => zActual };
    refrescar();
    window.addEventListener('load', refrescar);
})();
