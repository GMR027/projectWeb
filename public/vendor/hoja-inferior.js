/* Hoja inferior compartida (bottom sheet). Sin dependencias.
 *
 * Sirve para editar o consultar sin tapar el lienzo: vPlan (ficha de tarea) y los paneles
 * táctiles del editor y del modo diseño. El estilo vive en src/scss/componentes/_hoja-inferior.scss.
 *
 * Uso:
 *   const hoja = window.HojaInferior.crear('tarea');        // una sola vez
 *   hoja.abrir({ titulo: 'Tarea 12', nodo: formulario, posicion: 'media', alCerrar: fn });
 *   hoja.cerrar();
 *
 * `nodo` mueve un elemento real (conserva sus listeners). `html` inserta marcado nuevo.
 */
(function () {
    'use strict';

    const ALTURAS = { peek: 28, media: 55, completa: 92 };
    const MIN = 24, MAX = 94;   // % del alto de la ventana
    const instancias = {};
    let abierta = null;

    const esFoco = el => /^(A|BUTTON|INPUT|SELECT|TEXTAREA)$/.test(el.tagName) && !el.disabled && !el.hidden;
    const enfocables = caja => [...caja.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')]
        .filter(el => el.offsetParent !== null || el === document.activeElement);

    function crear(id) {
        if (instancias[id]) return instancias[id];

        const el = document.createElement('div');
        el.className = 'hi';
        el.id = 'hi-' + id;
        el.hidden = true;
        el.innerHTML = '<div class="hi-fondo" data-hi-cerrar></div>' +
            '<div class="hi-caja" role="dialog" aria-modal="true">' +
            '<div class="hi-asa" aria-hidden="true"></div>' +
            '<header class="hi-cab"><h3 id="hi-' + id + '-titulo"></h3>' +
            '<button type="button" class="hi-cerrar" data-hi-cerrar aria-label="Cerrar"><span aria-hidden="true">×</span></button></header>' +
            '<div class="hi-cuerpo"></div>' +
            '<div class="hi-acc" hidden></div></div>';
        // En pantalla completa solo se pinta lo que cuelga del elemento ampliado (ver capa-superior.js).
        if (window.CapaSuperior) CapaSuperior.adoptar(el); else document.body.appendChild(el);

        const caja = el.querySelector('.hi-caja');
        const cuerpo = el.querySelector('.hi-cuerpo');
        const cab = el.querySelector('.hi-cab');
        const titulo = el.querySelector('.hi-cab h3');
        const acc = el.querySelector('.hi-acc');
        let alCerrarAhora = null;
        let ultimoFoco = null;
        let arrastre = null;

        const altoVentana = () => window.visualViewport ? window.visualViewport.height : window.innerHeight;

        function ponerAlto(pct) {
            const alto = Math.max(MIN, Math.min(MAX, pct));
            caja.style.setProperty('--hi-alto', alto + 'dvh');
            caja.style.height = alto + 'dvh';
            caja.dataset.posicion = alto >= 85 ? 'completa' : alto >= 45 ? 'media' : 'peek';
        }

        function cerrar() {
            if (!el.classList.contains('abierta')) return;
            el.classList.remove('abierta');
            el.classList.remove('arrastrando');
            abierta = null;
            document.body.classList.remove('hi-abierta');
            const fn = alCerrarAhora;
            alCerrarAhora = null;
            setTimeout(() => { if (!el.classList.contains('abierta')) el.hidden = true; }, 240);
            if (ultimoFoco && typeof ultimoFoco.focus === 'function') { try { ultimoFoco.focus(); } catch (_) { /* nodo ido */ } }
            ultimoFoco = null;
            if (fn) fn();
        }

        function abrir(op) {
            op = op || {};
            // Solo una hoja a la vez: abrir otra no debe dejar dos capas activas.
            if (abierta && abierta !== inst) abierta.cerrar();

            titulo.textContent = op.titulo || '';
            titulo.hidden = !op.titulo;
            if (op.nodo) { cuerpo.replaceChildren(op.nodo); }
            else if (op.html !== undefined) { cuerpo.innerHTML = op.html; }

            acc.hidden = !op.acciones || !op.acciones.length;
            acc.replaceChildren();
            (op.acciones || []).forEach(a => {
                if (a instanceof Node) { acc.appendChild(a); return; }
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn ' + (a.clase || 'btn-primary');
                if (a.icono) { const i = document.createElement('i'); i.className = 'fa-solid ' + a.icono; b.appendChild(i); b.appendChild(document.createTextNode(' ')); }
                b.appendChild(document.createTextNode(a.texto || ''));
                b.addEventListener('click', e => { if (a.alHacer) a.alHacer(e, inst); });
                acc.appendChild(b);
            });

            ponerAlto(ALTURAS[op.posicion] || ALTURAS.media);
            alCerrarAhora = op.alCerrar || null;
            const modal = op.modal !== false;
            el.classList.toggle('hi-no-modal', !modal);
            ultimoFoco = modal ? document.activeElement : null;
            cuerpo.scrollTop = 0;
            el.hidden = false;
            abierta = inst;
            if (modal) document.body.classList.add('hi-abierta');
            requestAnimationFrame(() => el.classList.add('abierta'));
            if (modal && op.enfocar !== false) setTimeout(() => {
                const f = enfocables(caja).find(esFoco);
                (f || el.querySelector('.hi-cerrar')).focus({ preventScroll: true });
            }, 80);
            return inst;
        }

        // ---- cerrar: fondo, botón y Escape ----
        el.addEventListener('click', e => { if (e.target.closest('[data-hi-cerrar]')) cerrar(); });
        el.addEventListener('keydown', e => {
            if (e.key === 'Escape') { e.stopPropagation(); cerrar(); return; }
            if (e.key !== 'Tab' || el.classList.contains('hi-no-modal')) return;
            const f = enfocables(caja); if (!f.length) return;
            const primero = f[0], ultimo = f[f.length - 1];
            if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
        });

        // ---- arrastre del asa / cabecera: peek ↔ media ↔ completa, y hacia abajo cierra ----
        const agarrar = e => {
            if (e.button !== 0 || e.target.closest('.hi-cerrar')) return;
            arrastre = { y: e.clientY, alto: caja.getBoundingClientRect().height };
            el.classList.add('arrastrando');
            e.currentTarget.setPointerCapture(e.pointerId);
            e.preventDefault();
        };
        const mover = e => {
            if (!arrastre) return;
            const alto = Math.max(ALTURAS.peek * 0.5, arrastre.alto + (arrastre.y - e.clientY));
            caja.style.height = alto + 'px';
        };
        const soltar = () => {
            if (!arrastre) return;
            arrastre = null;
            el.classList.remove('arrastrando');
            const vh = altoVentana();
            const pct = caja.getBoundingClientRect().height / vh * 100;
            if (pct < 18) { cerrar(); return; }
            const cerca = Object.values(ALTURAS).reduce((a, b) => Math.abs(b - pct) < Math.abs(a - pct) ? b : a);
            ponerAlto(cerca);
        };
        [el.querySelector('.hi-asa'), cab].forEach(z => {
            z.addEventListener('pointerdown', agarrar);
            z.addEventListener('pointermove', mover);
            z.addEventListener('pointerup', soltar);
            z.addEventListener('pointercancel', soltar);
        });

        const inst = {
            el: el, cuerpo: cuerpo, cabecera: cab, acciones: acc,
            abrir: abrir,
            cerrar: cerrar,
            estaAbierta: () => el.classList.contains('abierta'),
            ponerAlto: ponerAlto,
            destruir: () => { cerrar(); el.remove(); delete instancias[id]; },
        };
        instancias[id] = inst;
        return inst;
    }

    window.HojaInferior = { crear: crear, instancia: id => instancias[id] || null };
})();
