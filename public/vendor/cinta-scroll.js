// Cinta de herramientas (Redactor y SheetFlow): una sola fila con altura estable.
// Oculta la barra de desplazamiento nativa (que cambiaba el alto al cambiar de pestaña),
// permite desplazar con la rueda del ratón y avisa con un degradado cuando quedan
// herramientas fuera de la vista. No toca los botones ni el estado de las pestañas.
(function () {
    'use strict';

    function preparar(barra, pestanas) {
        if (!barra || barra.dataset.cintaLista) return;
        barra.dataset.cintaLista = '1';

        function actualizar() {
            var sobra = barra.scrollWidth - barra.clientWidth;
            var desborda = sobra > 2;
            barra.classList.toggle('cinta-desborda', desborda);
            barra.classList.toggle('cinta-ini', barra.scrollLeft <= 2);
            barra.classList.toggle('cinta-fin', !desborda || barra.scrollLeft >= sobra - 2);
        }

        barra.addEventListener('scroll', actualizar, { passive: true });
        window.addEventListener('resize', actualizar);

        // Rueda vertical sobre la cinta: desplaza en horizontal (sin shift; con shift el
        // navegador ya lo hace). Si ya no puede moverse, deja que la página siga su curso.
        barra.addEventListener('wheel', function (e) {
            if (e.shiftKey || Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
            if (barra.scrollWidth <= barra.clientWidth) return;
            var antes = barra.scrollLeft;
            barra.scrollLeft = antes + e.deltaY;
            if (barra.scrollLeft !== antes) e.preventDefault();
        }, { passive: false });

        // Las pestañas y el botón de plegar cambian el contenido visible: recalcular.
        if (pestanas) pestanas.addEventListener('click', function () { setTimeout(actualizar, 60); });
        var plegar = document.querySelector('#rd-plegar, #sf-plegar');
        if (plegar) plegar.addEventListener('click', function () { setTimeout(actualizar, 60); });

        if (window.ResizeObserver) new ResizeObserver(actualizar).observe(barra);
        window.addEventListener('load', actualizar);
        actualizar();
        setTimeout(actualizar, 120);

        barra.cintaActualizar = actualizar;
    }

    function iniciar() {
        preparar(document.getElementById('rd-barra'), document.getElementById('rd-pestanas'));
        preparar(document.getElementById('sf-barra'), document.getElementById('sf-pestanas'));
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
