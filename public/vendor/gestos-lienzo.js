/* Gestos de lienzo compartidos (vPlan y Editor de marcas). Sin dependencias.
 *
 * Resuelve lo que antes solo hacía la rueda del ratón:
 *   - pellizco con dos dedos (zoom)
 *   - arrastre con dos dedos (paneo)
 *   - doble toque (acercar / volver)
 *
 * Un solo dedo NO se intercepta: cada lienzo decide si dibuja, mueve o desplaza.
 * Los gestos que empiezan sobre un control (input, botón, enlace…) tampoco se tocan,
 * para que un campo dentro de la hoja siga recibiendo su toque.
 *
 * Uso:
 *   window.GestosLienzo.montar(contenedor, {
 *     zoomActual: () => 1,          // obligatorio: zoom con el que está el lienzo ahora
 *     min: 0.2, max: 8,
 *     excepto: '#rd-hoja',          // zona editable donde el pellizco SÍ debe funcionar (ej. contenteditable)
 *     alInicio: ({ z, cx, cy }) => {},
 *     alMover:  ({ z, cx, cy, dx, dy, factor }) => {},
 *     alFin:    ({ z }) => {},
 *     alDobleToque: (cx, cy) => {}
 *   });
 */
(function () {
    'use strict';

    const ESPERA_DOBLE = 320;   // ms entre los dos toques
    const RADIO_DOBLE = 26;     // px: los dos toques tienen que caer casi en el mismo sitio
    const UI = 'input, textarea, select, button, a, [contenteditable="true"], .no-gesto';

    const esTactil = () => (navigator.maxTouchPoints || 0) > 0
        || (window.matchMedia && matchMedia('(pointer: coarse)').matches);
    const distancia = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
    const centro = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
    // `excepto` deja pasar el gesto sobre una zona que normalmente sería «control» (la hoja editable del
    // Redactor es contenteditable: sin esto el pellizco sobre el documento no haría nada).
    const dentroUI = (t, excepto) => !!(t && t.closest && !(excepto && t.closest(excepto)) && t.closest(UI));

    function montar(el, opciones) {
        if (!el) return null;
        const op = opciones || {};
        const min = Number.isFinite(op.min) ? op.min : 0.05;
        const max = Number.isFinite(op.max) ? op.max : 8;
        const acotar = z => Math.max(min, Math.min(max, z));
        const zoomActual = () => {
            const z = op.zoomActual ? +op.zoomActual() : 1;
            return Number.isFinite(z) && z > 0 ? z : 1;
        };

        const punteros = new Map();   // pointerId -> { x, y }
        let gesto = null;             // { z0, cx0, cy0, d0 }
        let bloqueado = false;        // el primer dedo cayó sobre un control
        let ultimoToque = null;       // { t, x, y }
        let cuadro = 0;
        let pendiente = null;

        // Un solo callback por cuadro: mover bloques y medir letra en cada pointermove satura la gama baja.
        function enCuadro(datos) {
            pendiente = datos;
            if (cuadro) return;
            cuadro = requestAnimationFrame(() => {
                cuadro = 0;
                const d = pendiente; pendiente = null;
                if (d && op.alMover) op.alMover(d);
            });
        }

        function abajo(e) {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            punteros.set(e.pointerId, { x: e.clientX, y: e.clientY });

            if (punteros.size === 1) {
                bloqueado = dentroUI(e.target, op.excepto);
                if (!bloqueado) {
                    // Doble toque solo con el dedo: el lápiz ya usa el doble toque para editar textos
                    // y el ratón tiene dblclick.
                    if (e.pointerType === 'touch') {
                        const t = Date.now(), p = { x: e.clientX, y: e.clientY };
                        if (ultimoToque && t - ultimoToque.t < ESPERA_DOBLE
                            && Math.hypot(p.x - ultimoToque.x, p.y - ultimoToque.y) < RADIO_DOBLE) {
                            ultimoToque = null;
                            if (op.alDobleToque) op.alDobleToque(p.x, p.y);
                        } else {
                            ultimoToque = { t: t, x: p.x, y: p.y };
                        }
                    }
                }
                return;
            }

            if (punteros.size === 2) {
                if (bloqueado || dentroUI(e.target, op.excepto)) { gesto = null; return; }
                const [a, b] = [...punteros.values()];
                const c = centro(a, b);
                gesto = { z0: zoomActual(), cx0: c.x, cy0: c.y, d0: Math.max(1, distancia(a, b)) };
                ultimoToque = null;
                e.preventDefault();
                if (op.alInicio) op.alInicio({ z: gesto.z0, cx: gesto.cx0, cy: gesto.cy0 });
            }
        }

        function mover(e) {
            if (!punteros.has(e.pointerId)) return;
            punteros.set(e.pointerId, { x: e.clientX, y: e.clientY });
            if (!gesto || punteros.size < 2) return;
            e.preventDefault();
            const [a, b] = [...punteros.values()];
            const c = centro(a, b), d = Math.max(1, distancia(a, b));
            const factor = d / gesto.d0;
            enCuadro({
                z: acotar(gesto.z0 * factor),
                cx: c.x, cy: c.y,
                dx: c.x - gesto.cx0, dy: c.y - gesto.cy0,
                factor: factor,
            });
        }

        function arriba(e) {
            punteros.delete(e.pointerId);
            if (gesto && punteros.size < 2) {
                const z = acotar(zoomActual());
                gesto = null;
                if (op.alFin) op.alFin({ z: z });
            }
            if (!punteros.size) bloqueado = false;
        }

        el.addEventListener('pointerdown', abajo, { passive: false });
        el.addEventListener('pointermove', mover, { passive: false });
        el.addEventListener('pointerup', arriba);
        el.addEventListener('pointercancel', arriba);
        // Al cambiar de ventana/pestaña los punteros se pierden sin pointerup.
        window.addEventListener('blur', () => { punteros.clear(); gesto = null; bloqueado = false; });
        window.addEventListener('orientationchange', () => { punteros.clear(); gesto = null; bloqueado = false; });

        return {
            activo: () => !!gesto || punteros.size > 1,
            // Se usa cuando otra parte del editor cancela el gesto (p. ej. al cambiar de herramienta).
            soltar: () => { punteros.clear(); gesto = null; bloqueado = false; },
        };
    }

    window.GestosLienzo = { montar: montar, esTactil: esTactil };
})();
