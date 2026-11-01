/* Capa superior compartida: dónde deben colgarse los flotantes (paletas, menús, hojas, docks).
 *
 * En pantalla completa el navegador solo pinta lo que cuelga del elemento ampliado. Por eso los
 * flotantes que viven en <body> desaparecían («Ampliar» en vPlan y en el editor de marcas).
 *
 * Uso:
 *   (CapaSuperior ? CapaSuperior.actual() : document.body).appendChild(nodo);
 *   CapaSuperior.adoptar(nodo);   // lo marca para moverlo solo al entrar o salir de pantalla completa
 *
 * Quien cree un flotante debe colgarlo de CapaSuperior.actual() y llamar a adoptar() si el nodo
 * puede seguir abierto cuando cambie el modo; así el evento fullscreenchange lo reubica.
 */
(function () {
    'use strict';
    if (window.CapaSuperior) return;

    function actual() {
        return document.fullscreenElement || document.body;
    }

    function adoptar(nodo) {
        if (!nodo) return nodo;
        nodo.setAttribute('data-capa-flotante', '');
        if (nodo.parentNode !== actual()) actual().appendChild(nodo);
        return nodo;
    }

    document.addEventListener('fullscreenchange', function () {
        var capa = actual();
        document.querySelectorAll('[data-capa-flotante]').forEach(function (n) {
            if (n.parentNode !== capa) capa.appendChild(n);
        });
    });

    window.CapaSuperior = { actual: actual, adoptar: adoptar };
})();
