/**
 * Riel de secciones de Cuenta y administración.
 * - data-modo="tabs": muestra una sección a la vez (Ajustes del proyecto).
 * - data-modo="scroll": navegación por anclas con scrollspy (Sistema).
 * La búsqueda filtra los enlaces del riel; no oculta contenido.
 */
(function () {
    'use strict';

    const normalizar = (texto) => String(texto || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    function iniciar(nav) {
        const modo = nav.dataset.modo === 'scroll' ? 'scroll' : 'tabs';
        const enlaces = [...nav.querySelectorAll('a[data-sec]')];
        if (!enlaces.length) return;

        const contenido = nav.closest('.ctl-layout')?.querySelector('.ctl-contenido') || document;
        const secciones = modo === 'scroll'
            ? [...document.querySelectorAll('.ctl-seccion[id]')]
            : [...contenido.querySelectorAll('.ctl-seccion[id]')];
        const busca = nav.querySelector('[data-control-busca]');
        const vacio = nav.querySelector('[data-control-vacio]');
        const grupos = [...nav.querySelectorAll('.ctl-rail-grupo')];
        const clave = 'control-tab:' + location.pathname;
        const claveAjustes = location.pathname === '/ajustes' ? 'ajustes-tab' : null;

        const marcar = (id) => {
            enlaces.forEach((a) => {
                const activo = a.dataset.sec === id;
                a.classList.toggle('activo', activo);
                if (activo) a.setAttribute('aria-current', 'true');
                else a.removeAttribute('aria-current');
            });
        };

        const mostrar = (id, guardar) => {
            if (!enlaces.some((a) => a.dataset.sec === id)) id = enlaces[0].dataset.sec;
            if (modo === 'tabs') {
                secciones.forEach((seccion) => { seccion.hidden = seccion.id !== id; });
            }
            marcar(id);
            if (guardar) {
                try { sessionStorage.setItem(clave, id); } catch (e) {}
                if (claveAjustes) { try { sessionStorage.setItem(claveAjustes, id); } catch (e) {} }
            }
            return id;
        };

        const desdeHash = () => {
            if (location.hash.length <= 1) return false;
            const id = decodeURIComponent(location.hash.slice(1));
            const destino = document.getElementById(id);
            if (!destino) return false;
            // Un ancla interna (por ejemplo #tema) abre la sección que la contiene.
            const seccion = destino.closest('.ctl-seccion[id]');
            const destinoSec = seccion ? seccion.id : id;
            if (!enlaces.some((a) => a.dataset.sec === destinoSec)) return false;
            if (modo === 'tabs') mostrar(destinoSec, false);
            else marcar(destinoSec);
            destino.scrollIntoView({ block: 'start' });
            return true;
        };

        enlaces.forEach((a) => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const id = a.dataset.sec;
                mostrar(id, true);
                const destino = document.getElementById(id);
                if (modo === 'scroll' && destino) {
                    destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                history.replaceState(null, '', '#' + id);
            });
        });

        window.addEventListener('hashchange', desdeHash);

        if (modo === 'tabs') {
            let guardada = null;
            try { guardada = sessionStorage.getItem(clave) || (claveAjustes ? sessionStorage.getItem(claveAjustes) : null); } catch (e) {}
            if (!desdeHash()) mostrar(guardada, false);
        } else {
            // Scrollspy: el enlace activo sigue a la sección visible.
            if ('IntersectionObserver' in window) {
                const observador = new IntersectionObserver((entradas) => {
                    const visibles = entradas
                        .filter((e) => e.isIntersecting)
                        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
                    if (visibles.length) marcar(visibles[0].target.id);
                }, {
                    rootMargin: '-18% 0px -62% 0px',
                    threshold: [0, .15, .4],
                });
                secciones.forEach((seccion) => observador.observe(seccion));
            }
            desdeHash();
        }

        if (busca) {
            const filtrar = () => {
                const q = normalizar(busca.value.trim());
                let visibles = 0;
                enlaces.forEach((a) => {
                    const coincide = !q || normalizar(a.textContent).includes(q);
                    a.hidden = !coincide;
                    if (coincide) visibles++;
                });
                grupos.forEach((grupo) => {
                    const alguno = [...grupo.querySelectorAll('a[data-sec]')].some((a) => !a.hidden);
                    grupo.hidden = !alguno;
                });
                if (vacio) vacio.hidden = visibles > 0;
            };
            busca.addEventListener('input', filtrar);
            busca.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') { busca.value = ''; filtrar(); }
                if (e.key === 'Enter') {
                    const primero = enlaces.find((a) => !a.hidden);
                    if (primero) primero.click();
                }
            });
            filtrar();
        }
    }

    document.querySelectorAll('[data-control-nav]').forEach(iniciar);
})();
