<?php // Konva + editor + vista ampliada. Variables: $config. Va después de la sección «Acciones» (necesita #ve-ampliar) ?>
<script>window.VPLAN_CONFIG = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;</script>
<script src="/vendor/konva.min.js"></script>
<script src="/vendor/editor-fondos.js"></script>
<script src="/vendor/vplan-editor.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/vplan-editor.js'); ?>"></script>
<script>
(function () {
    const hoja = document.getElementById('ve-hoja'), modalConfirm = document.getElementById('modal-confirm');
    const reencuadrar = () => { window.dispatchEvent(new Event('resize')); setTimeout(() => window.dispatchEvent(new Event('resize')), 120); };   // el editor re-encuadra el plano al cambiar el tamaño
    const abrir = () => {
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
        reencuadrar();
    };
    document.getElementById('ve-ampliar').addEventListener('click', abrir);
    document.getElementById('ve-cerrar-ampliado').addEventListener('click', cerrar);
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) cerrar(); else reencuadrar(); });   // Esc del navegador

    // Guardar y estado también dentro de la vista ampliada
    const est = document.getElementById('ve-estado'), estA = document.getElementById('ve-estado-amp');
    const g = document.getElementById('ve-guardar'), gA = document.getElementById('ve-guardar-amp');
    new MutationObserver(() => { estA.textContent = est.textContent; }).observe(est, { childList: true, characterData: true, subtree: true });
    new MutationObserver(() => gA.classList.toggle('ve-pend', g.classList.contains('ve-pend'))).observe(g, { attributes: true, attributeFilter: ['class'] });
    gA.addEventListener('click', () => g.click());
})();
</script>
