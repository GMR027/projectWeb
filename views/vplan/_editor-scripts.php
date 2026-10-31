<?php // Konva + editor + vista ampliada. Variables: $config. Va después de la sección «Acciones» (necesita #ve-ampliar) ?>
<script>window.VPLAN_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;</script>
<script src="/vendor/konva.min.js"></script>
<script src="/vendor/editor-fondos.js"></script>
<script src="/vendor/kit-edicion.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/kit-edicion.js'); ?>"></script>
<script src="/vendor/vplan-editor.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/vplan-editor.js'); ?>"></script>
<?php if (!empty($config['materiales'])): ?><script src="/vendor/materiales-vplan.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/materiales-vplan.js'); ?>"></script><?php endif; ?>
<script>
(function () {
    const hoja = document.getElementById('ve-hoja'), modalConfirm = document.getElementById('modal-confirm');
    const reencuadrar = () => { window.dispatchEvent(new Event('resize')); setTimeout(() => window.dispatchEvent(new Event('resize')), 120); };   // el editor re-encuadra el plano al cambiar el tamaño
    let scrollPrevio = 0;
    const abrir = () => {
        scrollPrevio = window.scrollY;   // al salir se vuelve a donde estaba la página
        hoja.classList.add('ve-ampliado');
        document.body.classList.add('vp-sin-scroll');
        if (modalConfirm) hoja.appendChild(modalConfirm);   // el aviso de confirmación debe verse a pantalla completa
        if (hoja.requestFullscreen) hoja.requestFullscreen().catch(() => {});
        reencuadrar();
    };
    const cerrar = () => {
        if (!hoja.classList.contains('ve-ampliado')) return;
        hoja.classList.remove('ve-ampliado');
        document.body.classList.remove('vp-sin-scroll');
        if (modalConfirm && modalConfirm.parentNode === hoja) document.body.appendChild(modalConfirm);
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        // Al quitar el position:fixed el navegador recoloca el scroll; se restaura cuando ya hay alto.
        const volver = () => window.scrollTo(0, scrollPrevio);
        volver(); requestAnimationFrame(volver); setTimeout(volver, 150); setTimeout(volver, 400);
        reencuadrar();
    };
    document.getElementById('ve-ampliar').addEventListener('click', abrir);
    document.getElementById('ve-cerrar-ampliado').addEventListener('click', cerrar);
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) cerrar(); else reencuadrar(); });   // Esc del navegador

    // Guardar y estado también dentro de la vista ampliada
    const est = document.getElementById('ve-estado'), estA = document.getElementById('ve-estado-amp');
    const g = document.getElementById('ve-guardar'), gA = document.getElementById('ve-guardar-amp');
    new MutationObserver(() => { estA.textContent = est.textContent; est.title = est.textContent; estA.title = est.textContent; }).observe(est, { childList: true, characterData: true, subtree: true });
    new MutationObserver(() => gA.classList.toggle('ve-pend', g.classList.contains('ve-pend'))).observe(g, { attributes: true, attributeFilter: ['class'] });
    gA.addEventListener('click', () => g.click());
})();
</script>
<script>
(function () {
    // «Lienzo en blanco» del Editor de gráficos: cuatro arranques rápidos que pulsan la herramienta
    // de la barra. Solo en el Editor (en el vPlan hay plano de fondo); se oculta al primer trazo.
    const ov = document.getElementById('ve-lienzo-vacio');
    if (!ov || !window.VPLAN_CONFIG || window.VPLAN_CONFIG.plano) return;
    // La lámina activa se busca por id (no por índice): `laminaActiva` es el id de la lámina.
    const todas = window.VPLAN_CONFIG.laminas || [];
    const act = todas.find(l => l.id === (window.VPLAN_CONFIG.laminaActiva | 0)) || todas[0];
    const marcas = act && act.marcas;
    if (marcas && ((marcas.shapes || []).length || (marcas.guides || []).length || marcas.escala)) return;   // ya hay dibujo
    ov.hidden = false;
    ov.addEventListener('click', e => {
        const b = e.target.closest('[data-quick]');
        if (b) {
            const t = b.dataset.quick.charAt(0) === '#' ? document.querySelector(b.dataset.quick)
                : document.querySelector('[data-tool="' + b.dataset.quick + '"]');
            if (t) t.click();
        }
        ov.hidden = true;
    });
    const hoja = document.getElementById('ve-hoja');
    // Se oculta al primer trazo sobre el lienzo (pero no cuando se pulsa uno de sus propios botones)
    if (hoja) hoja.addEventListener('pointerdown', e => { if (!ov.contains(e.target)) ov.hidden = true; }, { once: true, capture: true });
})();
</script>
<script src="/vendor/tour.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tour.js'); ?>"></script>
<script>
// Guía rápida del lienzo (Editor de gráficos y editor de marcas del vPlan comparten la barra)
window.Tour.registrar('editor-canvas', {
    auto: true,
    pasos: [
        { icono: 'fa-pen-ruler', titulo: 'El lienzo', texto: 'Todo lo que dibujes aquí (formas, líneas, textos, símbolos) se guarda como una imagen y se puede exportar a PNG, PDF o presentación. Los cambios se autoguardan unos segundos después de soltar el ratón.' },
        { sel: '#ve-pestanas', icono: 'fa-table-columns', titulo: 'Tres pestañas', texto: '<b>Dibujo</b> trae formas y líneas, <b>Insertar</b> lo que va de fuera (texto, tablas, símbolos, imágenes, enlaces) y <b>Medir y cuadrícula</b> la escala y las guías.' },
        { sel: '.ve-grupo[data-nombre="Selección"]', icono: 'fa-hand-pointer', titulo: 'Seleccionar y mover', texto: 'Con la flecha (o <b>Esc</b>) se mueve lo dibujado; <b>Mayús + arrastrar</b> elige varias cosas a la vez. Doble clic en un texto lo edita.' },
        { sel: '.ve-grupo[data-nombre="Trazo"]', icono: 'fa-pen', titulo: 'Trazo y color', texto: 'Elige la herramienta y el color de abajo. <b>F8</b> traza líneas solo horizontales o verticales.' },
        { sel: '#ve-simbolos-btn', icono: 'fa-shapes', titulo: 'Símbolos y cajetines', texto: 'La biblioteca del proyecto: cámaras, extintores, leyendas… un clic los pone en el lienzo. Se administran en la pestaña «Símbolos».' },
        { sel: '.ve-grupo[data-nombre="Vista"]', icono: 'fa-rotate-left', titulo: 'Deshacer y encuadrar', texto: '<b>Ctrl + Z</b> deshace y <b>Ctrl + Y</b> rehace; el engranaje encuadra todo el dibujo. El «Autoguardar» está activado por defecto.' },
        { icono: 'fa-floppy-disk', titulo: 'Listo para dibujar', texto: 'Recuerda: doble clic edita textos y tablas, y el botón <b>Guardar</b> (arriba a la derecha) deja la imagen lista para exportar.' }
    ]
});
</script>
<script>
// Editor de gráficos: coloca el lienzo a la vista y lo ajusta al alto de la ventana.
// Sin esto, en portátiles (1366×768) el lienzo empezaba por debajo del borde inferior.
(function () {
    if (!window.VPLAN_CONFIG || window.VPLAN_CONFIG.modo !== 'grafico') return;
    const hoja = document.getElementById('ve-hoja');
    const barra = hoja && hoja.querySelector('.ve-barra-pest');
    const lienzo = document.getElementById('ve-lienzo');
    if (!hoja || !barra || !lienzo) return;

    let raf = 0;
    const ajustar = () => {
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(() => {
            if (hoja.classList.contains('ve-ampliado')) { lienzo.style.height = ''; return; }   // la vista ampliada manda
            const rHoja = hoja.getBoundingClientRect();
            const rBarra = barra.getBoundingClientRect();
            const desdeArriba = Math.max(0, rBarra.bottom - rHoja.top) + 12;   // pestañas + barra + margen
            const alto = Math.min(window.innerHeight - 120, Math.round(window.innerHeight - desdeArriba - 24));
            lienzo.style.height = Math.max(300, alto) + 'px';
            hoja.style.setProperty('--ve-alto', Math.max(300, alto) + 'px');   // el panel lateral usa el mismo alto que el lienzo
            // Konva vuelve a encuadrar sin disparar «resize» (este mismo ajuste escucha ese evento).
            if (window.__vplan && typeof window.__vplan.encuadrar === 'function') window.__vplan.encuadrar();
        });
    };
    let manual = false;   // si la persona ya movió la página, no la recolocamos
    ['wheel', 'touchmove'].forEach(ev => window.addEventListener(ev, () => { manual = true; }, { passive: true }));
    window.addEventListener('keydown', e => { if (['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '].includes(e.key)) manual = true; });
    const colocar = () => {
        if (location.hash || manual) { ajustar(); return; }   // #exportar y otros anclajes tienen prioridad
        const r = hoja.getBoundingClientRect();
        if (r.top > 12 || r.top < -4) window.scrollTo({ top: Math.max(0, window.scrollY + r.top - 8), behavior: 'auto' });
        ajustar();
    };

    document.addEventListener('vplan:listo', () => setTimeout(colocar, 60), { once: true });
    window.addEventListener('load', () => setTimeout(colocar, 120), { once: true });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => { if (!manual) setTimeout(colocar, 80); });
    setTimeout(() => { if (!manual) colocar(); }, 700);   // la cabecera móvil termina de asentarse después
    window.addEventListener('resize', ajustar);
    // Cambiar de pestaña de herramientas o del panel cambia el alto de la barra: volver a medir.
    document.addEventListener('click', e => {
        if (e.target.closest('#ve-pestanas [data-pest], .ve-tabs [data-tab]')) setTimeout(ajustar, 0);
    });
    new MutationObserver(ajustar).observe(hoja, { attributes: true, attributeFilter: ['class'] });
})();
</script>
