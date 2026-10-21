// vPlan: ajusta la letra de las tablas (tareas y tablas adicionales) para que se vean completas dentro de su bloque.
// Se vuelve a ejecutar al redimensionar un bloque (modo diseño), al agregar/quitar renglones y al cambiar el texto.
(function () {
    const sheet = document.getElementById('vp-sheet');
    if (!sheet) return;
    const num = v => parseFloat(v) || 0;
    const crecer = ta => { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; };

    function ajustar(blk) {
        const tab = blk.querySelector(':scope > .vp-tab');
        if (!tab || !tab.style.getPropertyValue('--vp-pad')) return;   // el cajetín conserva su tamaño propio
        const tabla = tab.querySelector('table');
        if (!tabla) return;
        if (!tab.dataset.ratio) tab.dataset.ratio = (num(tab.style.getPropertyValue('--vp-pad')) / (num(tab.style.fontSize) || 1)).toString();
        const ratio = num(tab.dataset.ratio);
        const tas = tab.querySelectorAll('textarea.vp-ta');
        const cabe = x => {
            tab.style.fontSize = x + 'cqw';
            tab.style.setProperty('--vp-pad', (x * ratio) + 'cqw');
            tas.forEach(crecer);
            return tabla.offsetHeight <= tab.clientHeight && tab.scrollWidth <= tab.clientWidth + 1;
        };
        // El tope es la letra que eligió el servidor (`--vp-max`): si la tabla crece para llenar su
        // bloque (hasta 48 pt) la vista usa la misma, y así la lámina se ve igual que el PDF.
        const max = num(getComputedStyle(tab).getPropertyValue('--vp-max')) || 0.74;
        let lo = 0.25, hi = max;
        if (cabe(hi)) return;
        for (let i = 0; i < 12; i++) {
            const mid = (lo + hi) / 2;
            if (cabe(mid)) lo = mid; else hi = mid;
        }
        cabe(lo);
    }

    const todos = () => sheet.querySelectorAll('.vp-blk').forEach(ajustar);
    let t = null;
    const pronto = blk => { cancelAnimationFrame(t); t = requestAnimationFrame(() => (blk ? ajustar(blk) : todos())); };

    window.vplanAjustar = blk => { if (blk) pronto(blk); else pronto(); };
    new MutationObserver(() => pronto()).observe(sheet, { childList: true, subtree: true });
    document.addEventListener('change', e => { if (e.target.closest && e.target.closest('.vp-tab')) pronto(); });
    window.addEventListener('load', todos);
    todos();
})();
