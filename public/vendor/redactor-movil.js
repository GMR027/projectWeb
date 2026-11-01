/* Redactor en tablet y celular: barra de acciones fija, herramientas en hoja inferior y zoom por pellizco.
 *
 * El Redactor ya no se bloquea en el celular: se edita con el mismo motor. Lo que cambia en pantallas
 * táctiles es dónde viven las herramientas:
 *   - una barra inferior (dock) con lo que se usa todo el tiempo: deshacer, rehacer, negrita, cursiva,
 *     viñetas, «Herramientas» y guardar;
 *   - la barra de pestañas, la barra de formato, el navegador de páginas y los paneles viajan a una hoja inferior
 *     (hoja-inferior.js) para no tapar el documento; al cerrarla vuelven a su sitio;
 *   - pellizco de dos dedos para el zoom de la hoja (gestos-lienzo.js) y doble toque para ajustar al ancho.
 *
 * Se carga después de redactor.js, gestos-lienzo.js y hoja-inferior.js.
 */
(function () {
    'use strict';
    if (!window.RD || !window.HojaInferior || !window.GestosLienzo) return;

    const $ = id => document.getElementById(id);
    const cuerpo = document.body;
    if (!GestosLienzo.esTactil()) return;
    cuerpo.classList.add('rd-tactil');

    // ---------------------------------------------------------------- zoom de la hoja
    function ponerZoom(z) {
        z = Math.max(0.3, Math.min(2.5, +z || 1));
        RD.pagina.style.zoom = z.toFixed(3);
        const sel = $('rd-zoom');
        if (sel) {
            let op = sel.querySelector('option[data-ajuste]');
            if (!op) { op = document.createElement('option'); op.dataset.ajuste = '1'; sel.appendChild(op); }
            op.value = z.toFixed(3);
            op.textContent = Math.round(z * 100) + '%';
            sel.value = op.value;
        }
    }
    function ajustarAlAncho() {
        const fondo = $('rd-fondo'), medidas = RD.medidas();
        if (!fondo || !medidas) return;
        ponerZoom(Math.max(0.55, Math.min(1.6, (fondo.clientWidth - 24) / medidas.w)));
    }

    const fondo = $('rd-fondo');
    if (fondo) {
        GestosLienzo.montar(fondo, {
            zoomActual: () => RD.zoom(),
            min: 0.3, max: 2.5,
            excepto: '#rd-hoja',   // la hoja es editable: el pellizco sobre el texto también debe acercar
            alMover: g => ponerZoom(g.z),
            alDobleToque: ajustarAlAncho,
        });
    }
    // La primera vez, la hoja se ajusta para que quepa casi entera (sin quedar ilegible).
    setTimeout(ajustarAlAncho, 500);

    // En tablet (y en cualquier pantalla ancha) la cinta compacta va en la propia página, como OnlyOffice.
    // El dock y la hoja de herramientas son solo para el celular.
    if (!window.matchMedia('(max-width: 44rem)').matches) return;
    cuerpo.classList.add('rd-movil-dock');

    // ---------------------------------------------------------------- hoja de herramientas
    const hoja = HojaInferior.crear('rd-herramientas');
    const caja = document.createElement('div');
    caja.className = 'rd-hoja-herramientas';
    const barraTabs = document.createElement('div');
    barraTabs.className = 'rd-hoja-tabs';
    caja.appendChild(barraTabs);

    let movidos = [];   // { nodo, padre, siguiente }

    function llevarALaHoja() {
        if (movidos.length) return;
        const nodos = [].slice.call(document.querySelectorAll('#rd-pestanas, #rd-barra, .rd-panel:not(.rd-flota)'));
        nodos.forEach(n => {
            if (!n || caja.contains(n)) return;
            movidos.push({ nodo: n, padre: n.parentNode, siguiente: n.nextSibling });
        });
        // Pestañas y barra juntas y pegadas arriba; los paneles debajo, en su orden.
        movidos.forEach(m => {
            if (m.nodo.id === 'rd-pestanas') barraTabs.appendChild(m.nodo);
            else caja.appendChild(m.nodo);
        });
    }
    function devolver() {
        movidos.forEach(m => {
            // Si su sitio original ya no existe (por ejemplo, un guardado recargó la vista) se deja donde está.
            if (!m.padre || !document.contains(m.padre)) return;
            if (m.siguiente && m.siguiente.parentNode === m.padre) m.padre.insertBefore(m.nodo, m.siguiente);
            else m.padre.appendChild(m.nodo);
        });
        movidos = [];
        barraTabs.replaceChildren();
    }
    function abrirHerramientas(pestana) {
        llevarALaHoja();
        if (pestana) {
            const b = document.querySelector('#rd-pestanas [data-pest="' + pestana + '"]');
            if (b) b.click();
        }
        hoja.abrir({
            titulo: 'Herramientas del documento',
            nodo: caja,
            posicion: 'completa',
            modal: false,
            enfocar: false,
            alCerrar: () => { cuerpo.classList.remove('rd-hoja-abierta'); devolver(); },
        });
        cuerpo.classList.add('rd-hoja-abierta');
    }

    // ---------------------------------------------------------------- barra inferior (dock)
    const ACCIONES = [
        ['undo', 'Deshacer', 'fa-rotate-left', '[data-cmd="undo"]'],
        ['redo', 'Rehacer', 'fa-rotate-right', '[data-cmd="redo"]'],
        ['negrita', 'Negrita', 'fa-bold', '[data-cmd="bold"]'],
        ['cursiva', 'Cursiva', 'fa-italic', '[data-cmd="italic"]'],
        ['lista', 'Lista con viñetas', 'fa-list-ul', '[data-cmd="insertUnorderedList"]'],
        ['herramientas', 'Herramientas: formato, insertar, diseño y revisión', 'fa-sliders', null],
        ['guardar', 'Guardar', 'fa-floppy-disk', '#rd-guardar'],
    ];
    const dock = document.createElement('div');
    dock.className = 'rd-dock no-print';
    dock.id = 'rd-dock';
    dock.setAttribute('role', 'toolbar');
    dock.setAttribute('aria-label', 'Acciones rápidas de edición');
    ACCIONES.forEach(([clave, titulo, icono, destino]) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'rd-dock-b' + (clave === 'guardar' ? ' rd-dock-primario' : '');
        b.dataset.accion = clave;
        b.title = titulo;
        b.setAttribute('aria-label', titulo);
        b.innerHTML = '<i class="fa-solid ' + icono + '" aria-hidden="true"></i>';
        b.addEventListener('mousedown', e => e.preventDefault());   // no perder la selección del texto
        b.addEventListener('click', () => {
            if (clave === 'herramientas') { hoja.estaAbierta() ? hoja.cerrar() : abrirHerramientas(); return; }
            const d = document.querySelector(destino);
            if (d) d.click();
        });
        dock.appendChild(b);
    });
    cuerpo.appendChild(dock);
    // En el modo enfoque (pantalla completa) el dock debe seguir viéndose: cuelga de la capa ampliada.
    if (window.CapaSuperior) CapaSuperior.adoptar(dock);

    // Atajo con teclado externo (tablet con teclado): Alt+H abre o cierra las herramientas.
    document.addEventListener('keydown', e => {
        if (e.key === 'F2' || (e.altKey && e.key && e.key.toLowerCase() === 'h')) {
            e.preventDefault();
            hoja.estaAbierta() ? hoja.cerrar() : abrirHerramientas();
        }
    });
})();
