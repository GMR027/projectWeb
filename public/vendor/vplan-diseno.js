/* vPlan · modo diseño: mueve y redimensiona los bloques de la lámina, gestiona capas y guías, inserta bloques/tablas/recovery,
   cambia el tamaño de la hoja y guarda.
   Datos que espera en window.VP_DISENO: { lamina, proyecto, volver, bloques, partidas, presets, tamano, tablas, tipos, guias, catalogo, recovery, etiquetasRecovery } */
(function () {
    'use strict';
    const D = window.VP_DISENO;
    const sheet = document.getElementById('vp-sheet');
    const panel = document.getElementById('vp-dis-campos');
    if (!D || !sheet || !panel) return;

    const $ = id => document.getElementById(id);
    const { esc, post, guardado, guarda, masCercano, renombrarEnLinea, listaOrdenable } = window.KitEdicion;
    const num = (v, d) => { const n = parseFloat(String(v).replace(',', '.')); return isFinite(n) ? n : d; };
    const redond = (v, d = 3) => Math.round(v * Math.pow(10, d)) / Math.pow(10, d);
    const ICONOS = { titulo: 'fa-heading', logo: 'fa-copyright', plano: 'fa-map', tareas: 'fa-list-check', tabla: 'fa-table', simbologia: 'fa-icons', imagen: 'fa-shapes', cajetin: 'fa-table-list', estampa: 'fa-wand-magic-sparkles', schedule: 'fa-chart-gantt', supervisor: 'fa-user-tie', fecha: 'fa-calendar-day', texto: 'fa-font' };
    const UNICOS = ['plano', 'schedule'];   // solo uno por lámina
    const shell = $('vp-shell');

    let bloques = D.bloques.map(b => ({ ...b, config: { ...(b.config || {}) }, clave: String(b.id) }));
    let guias = (D.guias || []).map(g => ({ id: String(g.id), eje: g.eje, pos: +g.pos, visible: g.visible !== false, bloqueada: !!g.bloqueada, nombre: g.nombre || '' }));
    let tamano = { ...D.tamano };
    let seleccion = null;      // bloque
    let guiaSel = null;        // guía
    let sucio = false;
    let nuevoN = 0;
    const opts = { rejilla: true, iman: true, guias: true, reglas: true };
    try { Object.assign(opts, JSON.parse(guardado('vp-dis-opts', '{}'))); } catch (_) { /* valores por defecto */ }

    const marcarSucio = () => { sucio = true; $('vp-dis-guardar').classList.add('vp-pendiente'); $('vp-dis-msg').textContent = 'Cambios sin guardar'; };

    const porClave = k => bloques.find(b => b.clave === k);
    const elDe = b => sheet.querySelector('.vp-blk[data-clave="' + b.clave + '"]');
    const mm = () => ({ w: tamano.ancho_mm, h: tamano.alto_mm });
    const aCm = (pct, eje) => redond(pct / 100 * (eje === 'x' ? mm().w : mm().h) / 10, 2);
    const dePct = (cm, eje) => cm * 10 / (eje === 'x' ? mm().w : mm().h) * 100;
    const nombreDe = b => (b.config.nombre || '').trim() || D.tipos[b.tipo] || b.tipo;
    const normalizarZ = () => { bloques.sort((a, b) => a.z - b.z).forEach((b, i) => { b.z = i; }); };
    normalizarZ();

    // ---- Preparar los bloques que ya están en la página (una capa encima para moverlos sin tocar sus campos) ----
    const asegurarCapa = (b, el) => {
        el.dataset.clave = b.clave;
        el.querySelectorAll(':scope > .vp-ov').forEach(x => x.remove());
        const ov = document.createElement('div');
        ov.className = 'vp-ov';
        ov.innerHTML = '<span class="vp-ov-etq">' + esc(nombreDe(b)) + '</span>' + ['n', 's', 'e', 'w', 'ne', 'nw', 'se', 'sw'].map(h => '<i class="vp-h vp-h-' + h + '" data-h="' + h + '"></i>').join('');
        el.appendChild(ov);
        el.classList.toggle('vp-bloq', !!b.config.bloqueado);
    };
    sheet.querySelectorAll('.vp-blk').forEach(el => { const b = bloques.find(x => x.clave === el.dataset.id); if (b) asegurarCapa(b, el); });

    const aplicar = b => {
        const el = elDe(b);
        if (!el) return;
        el.style.left = b.x + '%'; el.style.top = b.y + '%'; el.style.width = b.w + '%'; el.style.height = b.h + '%'; el.style.zIndex = b.z;
        if (window.vplanAjustar) window.vplanAjustar(el);
    };
    const aplicarTodos = () => bloques.forEach(aplicar);

    // ---- Imán: guías visibles + bordes y centro de la hoja ----
    const candidatos = eje => {
        if (!opts.iman) return [];
        const c = [0, 50, 100].map(p => ({ pos: p }));
        if (opts.guias) guias.filter(g => g.visible && g.eje === (eje === 'x' ? 'v' : 'h')).forEach(g => c.push({ pos: g.pos, guia: g }));
        return c;
    };
    /** El valor (de los que se mueven) que más cerca queda de un candidato dentro del umbral: { d, guia } */
    const imantar = (valores, eje, umbral) => {
        let mejor = null;
        const cand = candidatos(eje);
        valores.forEach(v => {
            const m = masCercano(v, cand, umbral);
            if (m && (!mejor || Math.abs(m.d) < Math.abs(mejor.d))) mejor = { d: m.d, guia: m.ref.guia };
        });
        return mejor;
    };
    const resaltarGuias = lista => sheet.querySelectorAll('.vp-guia').forEach(el => el.classList.toggle('vp-guia-iman', lista.some(g => g && g.id === el.dataset.id)));
    const rejilla = v => opts.rejilla ? Math.round(v / 0.5) * 0.5 : v;

    // ---- Arrastrar y redimensionar bloques ----
    sheet.addEventListener('pointerdown', e => {
        const ov = e.target.closest('.vp-ov');
        if (!ov || e.button !== 0) return;
        const el = ov.parentElement, b = porClave(el.dataset.clave);
        if (!b) return;
        e.preventDefault();
        seleccionar(b, true);
        if (b.config.bloqueado) return;
        const asa = e.target.dataset.h || '';
        const r = sheet.getBoundingClientRect();
        const ini = { x: b.x, y: b.y, w: b.w, h: b.h, px: e.clientX, py: e.clientY };
        const ux = 6 / r.width * 100, uy = 6 / r.height * 100;   // 6 px de imán
        ov.setPointerCapture(e.pointerId);
        const mover = ev => {
            const dx = (ev.clientX - ini.px) / r.width * 100, dy = (ev.clientY - ini.py) / r.height * 100;
            let { x, y, w, h } = ini;
            const tocadas = [];
            if (!asa) {
                x = ini.x + dx; y = ini.y + dy;
                const sx = imantar([x, x + w / 2, x + w], 'x', ux), sy = imantar([y, y + h / 2, y + h], 'y', uy);
                if (sx) { x += sx.d; tocadas.push(sx.guia); } else x = rejilla(x);
                if (sy) { y += sy.d; tocadas.push(sy.guia); } else y = rejilla(y);
            } else {
                if (asa.includes('e')) { w = ini.w + dx; const s = imantar([x + w], 'x', ux); if (s) { w += s.d; tocadas.push(s.guia); } else w = rejilla(w); }
                if (asa.includes('s')) { h = ini.h + dy; const s = imantar([y + h], 'y', uy); if (s) { h += s.d; tocadas.push(s.guia); } else h = rejilla(h); }
                if (asa.includes('w')) {
                    x = ini.x + dx; const s = imantar([x], 'x', ux); if (s) { x += s.d; tocadas.push(s.guia); } else x = rejilla(x);
                    w = ini.x + ini.w - x;
                }
                if (asa.includes('n')) {
                    y = ini.y + dy; const s = imantar([y], 'y', uy); if (s) { y += s.d; tocadas.push(s.guia); } else y = rejilla(y);
                    h = ini.y + ini.h - y;
                }
                if (w < 1) { if (asa.includes('w')) x = ini.x + ini.w - 1; w = 1; }
                if (h < 0.5) { if (asa.includes('n')) y = ini.y + ini.h - 0.5; h = 0.5; }
            }
            x = Math.max(0, Math.min(100 - w, x)); y = Math.max(0, Math.min(100 - h, y));
            Object.assign(b, { x: redond(x), y: redond(y), w: redond(w), h: redond(h) });
            aplicar(b); llenarPosicion(b); marcarSucio(); resaltarGuias(tocadas);
        };
        const fin = () => { ov.removeEventListener('pointermove', mover); ov.removeEventListener('pointerup', fin); ov.removeEventListener('pointercancel', fin); resaltarGuias([]); };
        ov.addEventListener('pointermove', mover); ov.addEventListener('pointerup', fin); ov.addEventListener('pointercancel', fin);
    });
    sheet.addEventListener('pointerdown', e => { if (e.target === sheet) { seleccionar(null); seleccionarGuia(null); } });

    document.addEventListener('keydown', e => {
        if (/^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '')) return;
        const paso = e.shiftKey ? 1 : 0.2;
        const mueve = { ArrowLeft: [-paso, 0], ArrowRight: [paso, 0], ArrowUp: [0, -paso], ArrowDown: [0, paso] }[e.key];
        const b = seleccion, g = guiaSel;
        if (b && !b.config.bloqueado && mueve) {
            e.preventDefault();
            b.x = redond(Math.max(0, Math.min(100 - b.w, b.x + mueve[0]))); b.y = redond(Math.max(0, Math.min(100 - b.h, b.y + mueve[1])));
            aplicar(b); llenarPosicion(b); marcarSucio();
        } else if (g && !g.bloqueada && mueve) {
            e.preventDefault();
            g.pos = redond(Math.max(0, Math.min(100, g.pos + (g.eje === 'v' ? mueve[0] : mueve[1]))));
            posicionarGuia(g); llenarGuia(); pintarGuiasLista(); marcarSucio();
        } else if (e.key === 'Delete') {
            if (b) { e.preventDefault(); quitar(b); } else if (g) { e.preventDefault(); quitarGuia(g); }
        }
    });

    // ================== Guías ==================
    const nuevoIdGuia = () => 'g' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5);
    const nombreGuia = g => g.nombre || ('Guía ' + (g.eje === 'v' ? 'vertical ' : 'horizontal ') + (guias.filter(x => x.eje === g.eje).indexOf(g) + 1));
    const elGuia = g => sheet.querySelector('.vp-guia[data-id="' + g.id + '"]');
    function posicionarGuia(g) {
        const el = elGuia(g);
        if (!el) return;
        if (g.eje === 'v') el.style.left = g.pos + '%'; else el.style.top = g.pos + '%';
    }
    function claseGuia(g) {
        return 'vp-guia vp-guia-' + g.eje + (g.bloqueada ? ' vp-guia-bloq' : '') + (g === guiaSel ? ' vp-guia-sel' : '');
    }
    function pintarGuias() {
        sheet.querySelectorAll('.vp-guia').forEach(x => x.remove());
        if (!opts.guias) return;
        guias.filter(g => g.visible).forEach(g => {
            const el = document.createElement('div');
            el.className = claseGuia(g);
            el.dataset.id = g.id;
            el.title = nombreGuia(g) + ' · ' + cmDeGuia(g) + ' cm';
            sheet.appendChild(el);
            posicionarGuia(g);
        });
    }
    const cmDeGuia = g => redond(g.pos / 100 * (g.eje === 'v' ? mm().w : mm().h) / 10, 2);
    const guiaDeCm = (cm, eje) => redond(cm * 10 / (eje === 'v' ? mm().w : mm().h) * 100);

    sheet.addEventListener('pointerdown', e => {
        const el = e.target.closest('.vp-guia');
        if (!el || e.button !== 0) return;
        const g = guias.find(x => x.id === el.dataset.id);
        if (!g) return;
        e.preventDefault();
        seleccionarGuia(g, true);
        if (g.bloqueada) return;
        const r = sheet.getBoundingClientRect();
        el.setPointerCapture(e.pointerId);
        const mover = ev => {
            const v = g.eje === 'v' ? (ev.clientX - r.left) / r.width * 100 : (ev.clientY - r.top) / r.height * 100;
            g.pos = redond(rejilla(v));
            el.classList.toggle('vp-guia-fuera', g.pos < 0 || g.pos > 100);
            posicionarGuia(g); llenarGuia(); pintarGuiasLista(); marcarSucio();
        };
        const fin = () => {
            el.removeEventListener('pointermove', mover); el.removeEventListener('pointerup', fin); el.removeEventListener('pointercancel', fin);
            if (g.pos < 0 || g.pos > 100) quitarGuia(g);   // soltada fuera de la hoja: se descarta, como en Inkscape
        };
        el.addEventListener('pointermove', mover); el.addEventListener('pointerup', fin); el.addEventListener('pointercancel', fin);
    });

    function quitarGuia(g) {
        guias = guias.filter(x => x !== g);
        if (guiaSel === g) guiaSel = null;
        pintarGuias(); seleccionarGuia(guiaSel); pintarGuiasLista(); marcarSucio();
    }
    function agregarGuia(eje, pos) {
        const g = { id: nuevoIdGuia(), eje, pos: redond(pos), visible: true, bloqueada: false, nombre: '' };
        guias.push(g);
        opts.guias = true; sincronizarToggles();
        pintarGuias(); pintarGuiasLista(); marcarSucio();
        return g;
    }
    $('vp-guia-nueva-v').addEventListener('click', () => seleccionarGuia(agregarGuia('v', 50)));
    $('vp-guia-nueva-h').addEventListener('click', () => seleccionarGuia(agregarGuia('h', 50)));
    $('vp-guias-ojo').addEventListener('click', () => {
        const alguna = guias.some(g => g.visible);
        guias.forEach(g => { g.visible = !alguna; });
        pintarGuias(); pintarGuiasLista(); marcarSucio();
    });
    $('vp-guias-borrar').addEventListener('click', async () => {
        if (!guias.length) return;
        const ok = await window.confirmarModal({ titulo: '¿Quitar todas las guías?', mensaje: 'Se aplica al guardar el diseño. Los bloques no cambian.', aceptar: 'Sí, quitar', iconoBoton: 'fa-trash-can' });
        if (!ok) return;
        guias = []; guiaSel = null;
        pintarGuias(); seleccionarGuia(null); pintarGuiasLista(); marcarSucio();
    });

    // ---- Reglas: ticks en cm y creación de guías arrastrando desde ellas ----
    const rx = $('vp-regla-x'), ry = $('vp-regla-y');
    function dibujarReglas() {
        if (!opts.reglas) return;
        const r = sheet.getBoundingClientRect();
        if (!r.width) return;
        const dpr = window.devicePixelRatio || 1;
        const color = getComputedStyle(rx).color;
        [[rx, true], [ry, false]].forEach(([cv, horiz]) => {
            const largo = horiz ? r.width : r.height, grosor = 24;
            cv.width = Math.round((horiz ? largo : grosor) * dpr); cv.height = Math.round((horiz ? grosor : largo) * dpr);
            const c = cv.getContext('2d');
            c.setTransform(dpr, 0, 0, dpr, 0, 0);
            c.clearRect(0, 0, cv.width, cv.height);
            c.strokeStyle = color; c.fillStyle = color; c.font = '10px Inter, sans-serif'; c.lineWidth = 1;
            const totalMm = horiz ? mm().w : mm().h, pxMm = largo / totalMm;
            const cmPx = pxMm * 10;
            const cada = cmPx >= 26 ? 1 : cmPx >= 13 ? 2 : cmPx >= 6 ? 5 : 10;   // cm entre números
            c.beginPath();
            for (let cm = 0; cm * 10 <= totalMm + 0.01; cm++) {
                const p = Math.round(cm * cmPx) + 0.5;
                const mayor = cm % cada === 0;
                if (cmPx >= 40) for (let k = 1; k < 10; k++) {   // milímetros
                    const pm = Math.round(cm * cmPx + k * pxMm) + 0.5;
                    if ((cm * 10 + k) > totalMm) break;
                    const t = k === 5 ? 8 : 4;
                    if (horiz) { c.moveTo(pm, grosor); c.lineTo(pm, grosor - t); } else { c.moveTo(grosor, pm); c.lineTo(grosor - t, pm); }
                }
                if (!mayor) continue;
                if (horiz) { c.moveTo(p, grosor); c.lineTo(p, 8); c.fillText(String(cm), p + 3, 10); }
                else { c.moveTo(grosor, p); c.lineTo(8, p); c.save(); c.translate(10, p + 3); c.rotate(-Math.PI / 2); c.textAlign = 'right'; c.fillText(String(cm), 0, 0); c.restore(); }
            }
            c.stroke();
        });
    }
    [rx, ry].forEach(regla => regla.addEventListener('pointerdown', e => {
        if (e.button !== 0) return;
        e.preventDefault();
        const eje = regla === rx ? 'h' : 'v';   // la regla de arriba da guías horizontales; la de la izquierda, verticales
        const r = sheet.getBoundingClientRect();
        let g = null;
        regla.setPointerCapture(e.pointerId);
        const valor = ev => eje === 'v' ? (ev.clientX - r.left) / r.width * 100 : (ev.clientY - r.top) / r.height * 100;
        const mover = ev => {
            const v = valor(ev);
            if (!g && v >= 0 && v <= 100) { g = agregarGuia(eje, rejilla(v)); seleccionarGuia(g, true); }
            if (g) { g.pos = redond(rejilla(v)); posicionarGuia(g); llenarGuia(); pintarGuiasLista(); }
        };
        const fin = ev => {
            regla.removeEventListener('pointermove', mover); regla.removeEventListener('pointerup', fin); regla.removeEventListener('pointercancel', fin);
            if (g && (g.pos < 0 || g.pos > 100)) quitarGuia(g);
        };
        regla.addEventListener('pointermove', mover); regla.addEventListener('pointerup', fin); regla.addEventListener('pointercancel', fin);
    }));
    if (window.ResizeObserver) new ResizeObserver(() => dibujarReglas()).observe(sheet);
    window.addEventListener('resize', dibujarReglas);

    // ---- Ayudas de dibujo (rejilla, imán, guías, reglas) ----
    function sincronizarToggles() {
        document.querySelectorAll('.vp-tgl').forEach(t => t.setAttribute('aria-pressed', opts[t.dataset.opt] ? 'true' : 'false'));
        shell.classList.toggle('vp-sin-reglas', !opts.reglas);
        guarda('vp-dis-opts', JSON.stringify(opts));
    }
    document.querySelectorAll('.vp-tgl').forEach(t => t.addEventListener('click', () => {
        opts[t.dataset.opt] = !opts[t.dataset.opt];
        sincronizarToggles();
        if (t.dataset.opt === 'guias') pintarGuias();
        if (t.dataset.opt === 'reglas') requestAnimationFrame(dibujarReglas);
    }));
    sincronizarToggles();

    // ---- Panel con pestañas ----
    let pestana = guardado('vp-dis-ptab', 'props');
    function irAPestana(p) {
        pestana = p;
        document.querySelectorAll('[data-ptab]').forEach(t => { t.classList.toggle('activo', t.dataset.ptab === p); t.setAttribute('aria-selected', t.dataset.ptab === p ? 'true' : 'false'); });
        document.querySelectorAll('[data-pan]').forEach(s => { s.hidden = s.dataset.pan !== p; });
        guarda('vp-dis-ptab', p);
    }
    document.querySelectorAll('[data-ptab]').forEach(t => t.addEventListener('click', () => irAPestana(t.dataset.ptab)));
    if (!['props', 'capas', 'insertar', 'hoja'].includes(pestana)) pestana = 'props';
    irAPestana(pestana);

    // ================== Capas ==================
    const resumen = b => {
        const c = b.config || {};
        if (['titulo', 'supervisor', 'fecha', 'texto'].includes(b.tipo)) return (c.texto || '').split('\n')[0] || 'automático';
        if (b.tipo === 'tareas') return (c.titulo || '') || ((c.fuentes || []).map(f => (D.partidas.find(p => p.id === f.partida) || {}).codigo).filter(Boolean).join(', ') || 'sin partidas');
        if (b.tipo === 'tabla') return ((D.tablas || {})[c.tabla_id] || {}).titulo || '';
        if (b.tipo === 'logo') return c.cual === 'empresa' ? 'empresa' : 'contratista';
        return '';
    };
    const ib = (acc, icono, titulo, extra = '') => '<button type="button" class="vp-ib vp-ib-sm' + extra + '" data-acc="' + acc + '" title="' + esc(titulo) + '" aria-label="' + esc(titulo) + '"><i class="fa-solid ' + icono + '"></i></button>';

    function pintarCapas() {
        const ul = $('vp-dis-capas');
        const orden = bloques.slice().sort((a, b) => b.z - a.z);
        $('vp-cont-capas').textContent = bloques.length + guias.length ? String(bloques.length + guias.length) : '';
        ul.innerHTML = orden.map((b, i) => {
            const oc = !!b.config.oculto, bl = !!b.config.bloqueado;
            return '<li class="vp-capa' + (b === seleccion ? ' vp-capa-sel' : '') + (oc ? ' vp-capa-oculta' : '') + (bl ? ' vp-capa-bloq' : '') + '" data-clave="' + esc(b.clave) + '" draggable="true">' +
                '<span class="vp-capa-grip" title="Arrastra para cambiar el orden"><i class="fa-solid fa-grip-vertical"></i></span>' +
                '<i class="fa-solid ' + (ICONOS[b.tipo] || 'fa-square') + ' vp-capa-ico"></i>' +
                '<button type="button" class="vp-capa-nombre" data-acc="sel" title="Clic: seleccionar · doble clic: renombrar">' + esc(nombreDe(b)) + ' <small>' + esc(b.config.nombre ? D.tipos[b.tipo] : resumen(b)) + '</small></button>' +
                ib('ojo', oc ? 'fa-eye-slash' : 'fa-eye', oc ? 'Mostrar' : 'Ocultar', oc ? ' vp-ib-on' : '') +
                ib('cand', bl ? 'fa-lock' : 'fa-lock-open', bl ? 'Desbloquear' : 'Bloquear', bl ? ' vp-ib-on' : '') +
                (i > 0 ? ib('sube', 'fa-chevron-up', 'Subir (más al frente)') : '<span class="vp-ib-hueco"></span>') +
                (i < orden.length - 1 ? ib('baja', 'fa-chevron-down', 'Bajar (más al fondo)') : '<span class="vp-ib-hueco"></span>') +
                '</li>';
        }).join('');
        actualizarInsertar();
    }
    function pintarGuiasLista() {
        const ul = $('vp-dis-guias');
        $('vp-guias-vacio').hidden = guias.length > 0;
        $('vp-cont-capas').textContent = bloques.length + guias.length ? String(bloques.length + guias.length) : '';
        const foco = document.activeElement && document.activeElement.classList.contains('vp-guia-in') ? document.activeElement.closest('li').dataset.gid : null;
        if (foco) return;   // no pisar el campo que se está escribiendo
        ul.innerHTML = guias.map(g =>
            '<li class="vp-capa vp-capa-guia' + (g === guiaSel ? ' vp-capa-sel' : '') + (!g.visible ? ' vp-capa-oculta' : '') + (g.bloqueada ? ' vp-capa-bloq' : '') + '" data-gid="' + esc(g.id) + '">' +
            '<i class="fa-solid ' + (g.eje === 'v' ? 'fa-grip-lines-vertical' : 'fa-grip-lines') + ' vp-capa-ico"></i>' +
            '<button type="button" class="vp-capa-nombre" data-acc="gsel" title="Clic: seleccionar · doble clic: renombrar">' + esc(nombreGuia(g)) + '</button>' +
            '<input type="number" class="vp-guia-in" step="0.1" value="' + cmDeGuia(g) + '" title="Posición en cm" aria-label="Posición de la guía en cm"' + (g.bloqueada ? ' disabled' : '') + '>' +
            ib('gojo', g.visible ? 'fa-eye' : 'fa-eye-slash', g.visible ? 'Ocultar' : 'Mostrar', g.visible ? '' : ' vp-ib-on') +
            ib('gcand', g.bloqueada ? 'fa-lock' : 'fa-lock-open', g.bloqueada ? 'Desbloquear' : 'Bloquear', g.bloqueada ? ' vp-ib-on' : '') +
            ib('gdel', 'fa-trash-can', 'Quitar guía', ' vp-ib-peligro') + '</li>').join('');
    }

    const mover = (b, delta) => {   // delta > 0: más al frente
        const orden = bloques.slice().sort((a, c) => a.z - c.z);
        const i = orden.indexOf(b), j = Math.max(0, Math.min(orden.length - 1, i + delta));
        if (j === i) return;
        orden.splice(i, 1); orden.splice(j, 0, b);
        orden.forEach((x, k) => { x.z = k; });
        bloques = orden; aplicarTodos(); pintarCapas(); marcarSucio();
    };
    // Renombrar con doble clic
    function renombrar(boton, actual, aplicarNombre) {
        renombrarEnLinea(boton, { valor: actual, max: 60, clase: 'vp-capa-renombrar', alTerminar: (ok, v) => { if (ok) aplicarNombre(v); pintarCapas(); pintarGuiasLista(); } });
    }
    $('vp-dis-capas').addEventListener('dblclick', e => {
        const n = e.target.closest('.vp-capa-nombre'), li = e.target.closest('.vp-capa');
        const b = li && porClave(li.dataset.clave);
        if (!n || !b) return;
        renombrar(n, b.config.nombre || '', v => {
            if (v) b.config.nombre = v; else delete b.config.nombre;
            const et = elDe(b) && elDe(b).querySelector('.vp-ov-etq'); if (et) et.textContent = nombreDe(b);
            if (seleccion === b) pintarCampos(b);
            marcarSucio();
        });
    });
    $('vp-dis-capas').addEventListener('click', e => {
        const li = e.target.closest('.vp-capa'), btn = e.target.closest('[data-acc]');
        if (!li || !btn) return;
        const b = porClave(li.dataset.clave);
        if (!b) return;
        switch (btn.dataset.acc) {
            case 'ojo': poneOculto(b, !b.config.oculto); break;
            case 'cand': poneBloqueado(b, !b.config.bloqueado); break;
            case 'sube': mover(b, 1); break;
            case 'baja': mover(b, -1); break;
            default: seleccionar(b);
        }
    });
    // Arrastrar capas para reordenar
    listaOrdenable($('vp-dis-capas'), {
        item: '.vp-capa', clave: li => li.dataset.clave,
        claseArrastrando: 'vp-arrastrando', claseArriba: 'vp-drop-arriba', claseAbajo: 'vp-drop-abajo',
        alSoltar: (claveOrigen, claveDestino, arriba) => {
            const origen = porClave(claveOrigen), destino = porClave(claveDestino);
            if (!origen || !destino || destino === origen) return;
            const lista = bloques.slice().sort((a, c) => c.z - a.z).filter(x => x !== origen);   // de frente a fondo
            lista.splice(lista.indexOf(destino) + (arriba ? 0 : 1), 0, origen);
            lista.forEach((x, k) => { x.z = lista.length - 1 - k; });
            bloques = lista.slice().reverse(); aplicarTodos(); pintarCapas(); marcarSucio();
        },
    });
    // Lista de guías
    $('vp-dis-guias').addEventListener('dblclick', e => {
        const n = e.target.closest('.vp-capa-nombre'), li = e.target.closest('.vp-capa');
        const g = li && guias.find(x => x.id === li.dataset.gid);
        if (n && g) renombrar(n, g.nombre, v => { g.nombre = v; marcarSucio(); });
    });
    $('vp-dis-guias').addEventListener('click', e => {
        const li = e.target.closest('.vp-capa'), btn = e.target.closest('[data-acc]');
        const g = li && guias.find(x => x.id === li.dataset.gid);
        if (!g || !btn) return;
        switch (btn.dataset.acc) {
            case 'gojo': g.visible = !g.visible; pintarGuias(); pintarGuiasLista(); marcarSucio(); break;
            case 'gcand': g.bloqueada = !g.bloqueada; pintarGuias(); pintarGuiasLista(); marcarSucio(); break;
            case 'gdel': quitarGuia(g); break;
            default: seleccionarGuia(g);
        }
    });
    $('vp-dis-guias').addEventListener('input', e => {
        const i = e.target.closest('.vp-guia-in'), li = e.target.closest('.vp-capa');
        const g = i && guias.find(x => x.id === li.dataset.gid);
        if (!g) return;
        g.pos = guiaDeCm(num(i.value, 0), g.eje);
        posicionarGuia(g); marcarSucio();
        if (guiaSel === g) llenarGuia();
    });
    $('vp-dis-guias').addEventListener('change', () => pintarGuiasLista());

    function poneOculto(b, v) {
        if (v) b.config.oculto = true; else delete b.config.oculto;
        const el = elDe(b); if (el) el.classList.toggle('vp-b-oculto', !!v);
        if (seleccion === b) $('vp-c-oculto').checked = !!v;
        marcarSucio(); pintarCapas();
    }
    function poneBloqueado(b, v) {
        if (v) b.config.bloqueado = true; else delete b.config.bloqueado;
        const el = elDe(b); if (el) el.classList.toggle('vp-bloq', !!v);
        if (seleccion === b) { $('vp-c-bloqueado').checked = !!v; bloquearPosicion(b); }
        marcarSucio(); pintarCapas();
    }
    $('vp-c-oculto').addEventListener('change', e => { if (seleccion) poneOculto(seleccion, e.target.checked); });
    $('vp-c-bloqueado').addEventListener('change', e => { if (seleccion) poneBloqueado(seleccion, e.target.checked); });

    // ---- Selección y propiedades ----
    function seleccionar(b, desdeLienzo) {
        seleccion = b;
        if (b) guiaSel = null;
        sheet.querySelectorAll('.vp-blk.vp-sel').forEach(x => x.classList.remove('vp-sel'));
        sheet.querySelectorAll('.vp-guia-sel').forEach(x => x.classList.remove('vp-guia-sel'));
        const cuerpo = $('vp-dis-cuerpo'), vacio = $('vp-dis-vacio');
        cuerpo.hidden = !b; vacio.hidden = !!b || !!guiaSel;
        $('vp-dis-guia-cuerpo').hidden = !guiaSel;
        pintarCapas(); pintarGuiasLista();
        if (!b) return;
        if (desdeLienzo && (pestana === 'insertar' || pestana === 'hoja')) irAPestana('props');
        $('vp-c-oculto').checked = !!b.config.oculto;
        $('vp-c-bloqueado').checked = !!b.config.bloqueado;
        const el = elDe(b);
        if (el) el.classList.add('vp-sel');
        $('vp-dis-tipo').textContent = nombreDe(b);
        $('vp-dis-tipo2').textContent = D.tipos[b.tipo] || b.tipo;
        $('vp-dis-ico').className = 'fa-solid ' + (ICONOS[b.tipo] || 'fa-square');
        $('vp-dis-duplicar').hidden = UNICOS.includes(b.tipo) || b.tipo === 'tabla';
        llenarPosicion(b); bloquearPosicion(b);
        pintarCampos(b);
    }
    function seleccionarGuia(g, desdeLienzo) {
        guiaSel = g;
        if (g) seleccion = null;
        sheet.querySelectorAll('.vp-blk.vp-sel').forEach(x => x.classList.remove('vp-sel'));
        sheet.querySelectorAll('.vp-guia-sel').forEach(x => x.classList.remove('vp-guia-sel'));
        $('vp-dis-cuerpo').hidden = true;
        $('vp-dis-guia-cuerpo').hidden = !g;
        $('vp-dis-vacio').hidden = !!g || !!seleccion;
        if (g) {
            const el = elGuia(g); if (el) el.classList.add('vp-guia-sel');
            if (desdeLienzo && (pestana === 'insertar' || pestana === 'hoja')) irAPestana('props');
            $('vp-guia-tipo').textContent = g.eje === 'v' ? 'vertical' : 'horizontal';
            $('vp-guia-desde').textContent = g.eje === 'v' ? 'el borde izquierdo' : 'el borde superior';
            llenarGuia();
        }
        pintarCapas(); pintarGuiasLista();
    }
    function llenarGuia() {
        if (!guiaSel) return;
        const i = $('vp-guia-pos');
        if (document.activeElement !== i) i.value = cmDeGuia(guiaSel);
        i.disabled = guiaSel.bloqueada;
    }
    $('vp-guia-pos').addEventListener('input', e => { const g = guiaSel; if (!g) return; g.pos = guiaDeCm(num(e.target.value, 0), g.eje); posicionarGuia(g); pintarGuiasLista(); marcarSucio(); });
    $('vp-guia-quitar').addEventListener('click', () => { if (guiaSel) quitarGuia(guiaSel); });

    function llenarPosicion(b) {
        if (seleccion !== b) return;
        [['x', aCm(b.x, 'x')], ['y', aCm(b.y, 'y')], ['w', aCm(b.w, 'x')], ['h', aCm(b.h, 'y')]].forEach(([k, v]) => {
            const i = $('vp-pos-' + k);
            if (document.activeElement !== i) i.value = v;
        });
    }
    const bloquearPosicion = b => ['x', 'y', 'w', 'h'].forEach(k => { $('vp-pos-' + k).disabled = !!b.config.bloqueado; });
    ['x', 'y', 'w', 'h'].forEach(k => $('vp-pos-' + k).addEventListener('input', e => {
        const b = seleccion; if (!b || b.config.bloqueado) return;
        const v = dePct(num(e.target.value, 0), k === 'x' || k === 'w' ? 'x' : 'y');
        b[k] = redond(Math.max(k === 'w' ? 1 : k === 'h' ? 0.5 : 0, Math.min(100, v)));
        if (b.x + b.w > 100) b.x = redond(100 - b.w);
        if (b.y + b.h > 100) b.y = redond(100 - b.h);
        aplicar(b); marcarSucio();
    }));
    $('vp-dis-frente').addEventListener('click', () => { if (seleccion) mover(seleccion, bloques.length); });
    $('vp-dis-fondo').addEventListener('click', () => { if (seleccion) mover(seleccion, -bloques.length); });
    $('vp-dis-quitar').addEventListener('click', () => { if (seleccion) quitar(seleccion); });
    $('vp-dis-duplicar').addEventListener('click', () => {
        const b = seleccion;
        if (!b) return;
        const copia = crearBloque(b.tipo, { ...b.config, nombre: b.config.nombre ? b.config.nombre + ' (copia)' : undefined }, Math.min(b.x + 2, 100 - b.w), Math.min(b.y + 2, 100 - b.h), b.w, b.h);
        if (!copia.config.nombre) delete copia.config.nombre;
        seleccionar(copia);
    });

    async function quitar(b) {
        const aviso = b.tipo === 'tabla' ? 'Se quita la tabla de esta lámina (sigue en el catálogo).' : 'Se quita este bloque de la lámina.';
        const ok = await window.confirmarModal({ titulo: '¿Quitar «' + nombreDe(b) + '»?', mensaje: aviso + ' Se aplica al guardar el diseño.', aceptar: 'Sí, quitar', iconoBoton: 'fa-trash-can' });
        if (!ok) return;
        const el = elDe(b); if (el) el.remove();
        bloques = bloques.filter(x => x !== b);
        normalizarZ();
        seleccionar(null); marcarSucio(); pintarCapas();
    }

    // ---- Campos según el tipo ----
    const campo = (id, etiqueta, html) => '<div class="campo"><label for="' + id + '">' + etiqueta + '</label>' + html + '</div>';
    const opciones = (lista, actual) => lista.map(([v, t]) => '<option value="' + esc(v) + '"' + (v === actual ? ' selected' : '') + '>' + esc(t) + '</option>').join('');
    const ALIN = [['izquierda', 'Izquierda'], ['centro', 'Centro'], ['derecha', 'Derecha']];

    function pintarCampos(b) {
        const c = b.config;
        let h = campo('vp-c-nombre', 'Nombre de la capa', '<input type="text" id="vp-c-nombre" maxlength="60" value="' + esc(c.nombre || '') + '" placeholder="' + esc(D.tipos[b.tipo] || b.tipo) + '">');
        if (['titulo', 'supervisor', 'fecha', 'texto'].includes(b.tipo)) {
            const auto = { titulo: 'Automático: proyecto y título de la lámina', supervisor: 'Automático: el supervisor de la lámina', fecha: 'Automático: UPDATED con la fecha de hoy', texto: '' }[b.tipo];
            const def = { titulo: 'izquierda', supervisor: 'izquierda', fecha: 'derecha', texto: 'izquierda' };
            h += campo('vp-c-texto', 'Texto', '<textarea id="vp-c-texto" rows="3" placeholder="' + esc(auto) + '">' + esc(c.texto || '') + '</textarea>');
            h += '<div class="vp-dis-fila">' + campo('vp-c-tamano', 'Letra (pt)', '<input type="number" id="vp-c-tamano" min="3" max="120" step="0.5" value="' + esc(c.tamano || '') + '" placeholder="auto">') +
                campo('vp-c-alinear', 'Alineación', '<select id="vp-c-alinear"><option value="">' + (b.tipo === 'titulo' ? 'Centro' : 'Por defecto') + '</option>' + opciones(ALIN, c.alinear || '') + '</select>') + '</div>';
            h += '<div class="vp-dis-fila"><label class="text-sm"><input type="checkbox" id="vp-c-negrita"' + (c.negrita ? ' checked' : '') + '> Negrita</label>' +
                '<label class="text-sm">Color <input type="color" id="vp-c-color" value="' + esc(c.color || '#000000') + '"></label><button type="button" class="btn btn-sm btn-ghost" id="vp-c-color-auto">Auto</button></div>';
        } else if (b.tipo === 'logo') {
            h += campo('vp-c-cual', 'Logo', '<select id="vp-c-cual">' + opciones([['contratista', 'Del contratista'], ['empresa', 'De la empresa']], c.cual || 'contratista') + '</select>');
            h += campo('vp-c-alinear', 'Alineación', '<select id="vp-c-alinear">' + opciones(ALIN, c.alinear || (c.cual === 'empresa' ? 'derecha' : 'izquierda')) + '</select>');
        } else if (b.tipo === 'tareas') {
            h += campo('vp-c-titulo', 'Título de la tabla', '<input type="text" id="vp-c-titulo" maxlength="200" value="' + esc(c.titulo || '') + '" placeholder="Automático: nombre de las partidas">');
            h += '<div class="vp-dis-fila">' + campo('vp-c-seq', 'Numeración (SEQ)', '<select id="vp-c-seq">' + opciones([['continuo', 'Continua 1..n'], ['partida', 'La de cada partida, con subtítulos']], c.seq || 'continuo') + '</select>') +
                '<div class="campo"><label for="vp-c-color">Color</label><input type="color" id="vp-c-color" value="' + esc(c.color || '#5b82b4') + '"> <button type="button" class="btn btn-sm btn-ghost" id="vp-c-color-auto">Auto</button></div></div>';
            h += '<div class="campo"><label>Partidas y actividades</label><p class="text-sm text-muted">Marca las partidas completas o solo algunas actividades. Se ve al guardar el diseño.</p><div id="vp-fuentes" class="vp-fuentes"></div></div>';
            h += '<div class="campo"><label>Columnas</label><p class="text-sm text-muted">Mueve las columnas de izquierda a derecha o agrega columnas de texto propias (se capturan en la lámina). Se ve al guardar el diseño.</p><ul id="vp-cols" class="vp-capas-lista"></ul>' +
                '<button type="button" class="btn btn-sm btn-primary" id="vp-col-mas"><i class="fa-solid fa-plus"></i> Agregar columna de texto</button></div>';
        } else if (b.tipo === 'tabla') {
            const t = (D.tablas || {})[(c.tabla_id || 0)] || {};
            h += '<p class="text-sm">Tabla: <strong>' + esc(t.titulo || '') + '</strong></p><p class="text-sm"><a href="/vplan/tablas/editar?proyecto=' + D.proyecto + '&id=' + (c.tabla_id || 0) + '">Editar sus datos</a></p>' +
                '<p class="text-sm text-muted">El tamaño de la letra se ajusta solo al espacio del bloque.</p>';
        } else if (b.tipo === 'imagen') {
            h += '<p class="text-sm text-muted">' + (c.ref ? '<i class="fa-solid fa-link"></i> Vinculada a su simbología o símbolo: si lo editas, esta imagen se actualiza sola al abrir la lámina. ' : 'Copia de un símbolo o simbología. ') + 'Se ajusta al bloque sin deformarse; cambia el tamaño arrastrando las esquinas.</p>' +
                campo('vp-c-alinear', 'Alineación', '<select id="vp-c-alinear">' + opciones(ALIN, c.alinear || 'centro') + '</select>');
        } else if (b.tipo === 'cajetin') {
            const cjs = (D.biblioteca && D.biblioteca.cajetines) || [];
            h += campo('vp-c-cajetin', 'Cajetín', '<select id="vp-c-cajetin">' + opciones(cjs.map(x => [String(x.id), x.nombre]), String(c.cajetin_id || 0)) + '</select>');
            h += campo('vp-c-tamano', 'Letra (pt)', '<input type="number" id="vp-c-tamano" min="3" max="60" step="0.5" value="' + esc(c.tamano || '') + '" placeholder="auto">');
            h += '<p class="text-sm text-muted">Los datos ({proyecto}, {titulo}, {fecha}…) se llenan solos. Edita las filas en <a href="/simbolos?proyecto=' + D.proyecto + '#cajetines" target="_blank" rel="noopener">Símbolos › Cajetines</a>.</p>';
        } else if (b.tipo === 'estampa') {
            const ests = (D.biblioteca && D.biblioteca.estampas) || [];
            const acts = [['0', '— Ninguna —']];
            D.partidas.forEach(p => p.actividades.forEach(a => acts.push([String(a.id), p.codigo + '.' + a.seq + ' · ' + a.nombre])));
            h += campo('vp-c-estampa', 'Estampa', '<select id="vp-c-estampa"><option value="0">— Elegir —</option>' + opciones(ests.map(x => [String(x.id), x.nombre]), String(c.estampa_id || 0)) + '</select>');
            h += campo('vp-c-efm', 'Fecha de las etiquetas de fecha', '<select id="vp-c-efm">' + opciones([['hoy', 'La de hoy (se actualiza sola)'], ['fija', 'Una fecha fija']], c.fecha_modo || 'hoy') + '</select>');
            if (c.fecha_modo === 'fija') h += campo('vp-c-efecha', 'Fecha', '<input type="date" id="vp-c-efecha" value="' + esc(c.fecha || '') + '">');
            h += campo('vp-c-eact', 'Actividad (para las etiquetas «de la actividad»)', '<select id="vp-c-eact">' + opciones(acts, String(c.actividad_id || 0)) + '</select>');
            h += '<p class="text-sm text-muted">El contenido se llena solo con los datos del proyecto y se actualiza cada vez que abres la lámina. Cambia las etiquetas en <a href="/simbolos?proyecto=' + D.proyecto + '&editar=' + (c.estampa_id || 0) + '#estampas" target="_blank" rel="noopener">Símbolos › Estampas inteligentes</a>.</p>';
        } else if (b.tipo === 'plano') {
            h += '<p class="text-sm text-muted">La imagen se ajusta al bloque sin deformarse. La imagen se cambia en «Editar lámina»; las marcas, en «Editar marcas».</p>';
        } else {
            h += '<p class="text-sm text-muted">La imagen se ajusta al bloque sin deformarse. Se cambia en «Editar lámina».</p>';
        }
        panel.innerHTML = h;
        enlazarCampos(b);
        $('vp-c-nombre').addEventListener('input', e => {
            const v = e.target.value.trim();
            if (v) c.nombre = v; else delete c.nombre;
            const et = elDe(b) && elDe(b).querySelector('.vp-ov-etq'); if (et) et.textContent = nombreDe(b);
            $('vp-dis-tipo').textContent = nombreDe(b);
            marcarSucio(); pintarCapas();
        });
        if (b.tipo === 'tareas') { pintarFuentes(b); pintarColumnas(b); }
    }

    function enlazarCampos(b) {
        const c = b.config;
        const g = id => document.getElementById(id);
        const vivo = () => {   // vista previa de textos y letra sin recargar
            const el = elDe(b), t = el && el.querySelector('.vp-txt');
            if (!t) return;
            if ((c.texto || '').trim()) t.innerHTML = '<div>' + (c.texto).split('\n').map(l => '<div>' + esc(l) + '</div>').join('') + '</div>';
            if (c.tamano) t.style.fontSize = (c.tamano / 1224.57 * 100) + 'cqw';
            t.style.fontWeight = c.negrita ? '700' : (b.tipo === 'titulo' ? '700' : '');
            if (c.color) t.style.color = c.color;
            const al = c.alinear || { titulo: 'centro', fecha: 'derecha' }[b.tipo] || 'izquierda';
            t.style.justifyContent = { izquierda: 'flex-start', centro: 'center', derecha: 'flex-end' }[al];
            t.style.textAlign = al === 'centro' ? 'center' : al === 'derecha' ? 'right' : 'left';
        };
        const ligar = (id, evento, fn) => { const i = g(id); if (i) i.addEventListener(evento, e => { fn(e.target); marcarSucio(); vivo(); }); };
        ligar('vp-c-texto', 'input', i => c.texto = i.value);
        ligar('vp-c-tamano', 'input', i => { c.tamano = num(i.value, 0) || 0; });
        ligar('vp-c-alinear', 'change', i => { if (i.value) c.alinear = i.value; else delete c.alinear; });
        ligar('vp-c-negrita', 'change', i => c.negrita = i.checked);
        ligar('vp-c-cual', 'change', i => { c.cual = i.value; });
        ligar('vp-c-cajetin', 'change', i => { c.cajetin_id = +i.value || 0; });
        ligar('vp-c-estampa', 'change', i => { c.estampa_id = +i.value || 0; cargarEstampa(b); });
        ligar('vp-c-efm', 'change', i => { c.fecha_modo = i.value; if (i.value !== 'fija') c.fecha = ''; cargarEstampa(b); pintarCampos(b); });
        ligar('vp-c-efecha', 'change', i => { c.fecha = i.value; cargarEstampa(b); });
        ligar('vp-c-eact', 'change', i => { c.actividad_id = +i.value || 0; cargarEstampa(b); });
        ligar('vp-c-titulo', 'input', i => c.titulo = i.value);
        ligar('vp-c-seq', 'change', i => c.seq = i.value);
        ligar('vp-c-color', 'input', i => c.color = i.value);
        const auto = g('vp-c-color-auto');
        if (auto) auto.addEventListener('click', () => { c.color = ''; marcarSucio(); if (b.tipo !== 'tareas') { const t = elDe(b).querySelector('.vp-txt'); if (t) t.style.color = ''; } });
    }

    // Columnas de un bloque de tareas: orden horizontal y columnas de texto propias
    const COLS_BASE = [['seq', '# SEQ'], ['tarea', 'TASK'], ['inicio', 'START'], ['fin', 'FINISH'], ['rec', 'RECOVERY (según la lámina)'], ['pct', '%'], ['restr', 'CONSTRAINS']];
    function ordenColumnas(c) {
        const ids = (c.columnas || []).map(x => 'x:' + x.id), validos = COLS_BASE.map(x => x[0]).concat(ids);
        const o = (c.orden || []).filter((t, i, a) => validos.includes(t) && a.indexOf(t) === i);
        COLS_BASE.forEach((x, i) => { if (!o.includes(x[0])) o.splice(Math.min(i, o.length), 0, x[0]); });
        ids.forEach(t => { if (!o.includes(t)) o.push(t); });
        return o;
    }
    function pintarColumnas(b) {
        const c = b.config, ul = document.getElementById('vp-cols');
        if (!ul) return;
        const orden = c.orden = ordenColumnas(c);
        ul.innerHTML = orden.map((t, i) => {
            const base = COLS_BASE.find(x => x[0] === t), col = base ? null : (c.columnas || []).find(x => 'x:' + x.id === t);
            return '<li class="vp-capa" data-t="' + esc(t) + '"><span class="vp-capa-nombre">' + (base ? esc(base[1]) : '<input type="text" class="vp-col-tit" maxlength="40" placeholder="Título de la columna" value="' + esc(col.titulo) + '">') + '</span>' +
                '<button type="button" class="vp-ib" data-m="-1" title="Mover a la izquierda"' + (i === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-left"></i></button>' +
                '<button type="button" class="vp-ib" data-m="1" title="Mover a la derecha"' + (i === orden.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-right"></i></button>' +
                (base ? '' : '<button type="button" class="vp-ib" data-q="1" title="Quitar la columna"><i class="fa-solid fa-trash-can"></i></button>') + '</li>';
        }).join('');
        ul.querySelectorAll('li').forEach(li => {
            const t = li.dataset.t;
            li.querySelectorAll('[data-m]').forEach(bt => bt.addEventListener('click', () => {
                const i = c.orden.indexOf(t), j = i + (+bt.dataset.m);
                if (j < 0 || j >= c.orden.length) return;
                [c.orden[i], c.orden[j]] = [c.orden[j], c.orden[i]]; marcarSucio(); pintarColumnas(b);
            }));
            const tit = li.querySelector('.vp-col-tit');
            if (tit) tit.addEventListener('input', () => { c.columnas.find(x => 'x:' + x.id === t).titulo = tit.value; marcarSucio(); });
            const q = li.querySelector('[data-q]');
            if (q) q.addEventListener('click', () => {
                c.columnas = c.columnas.filter(x => 'x:' + x.id !== t); c.orden = c.orden.filter(x => x !== t);
                if (!c.columnas.length) delete c.columnas;
                marcarSucio(); pintarColumnas(b);
            });
        });
        document.getElementById('vp-col-mas').disabled = (c.columnas || []).length >= 6;
    }
    document.addEventListener('click', e => {
        const bt = e.target.closest && e.target.closest('#vp-col-mas');
        if (!bt || !seleccion) return;
        const c = seleccion.config;
        if ((c.columnas || []).length >= 6) return;
        const id = Math.random().toString(36).slice(2, 8);
        (c.columnas = c.columnas || []).push({ id, titulo: 'COLUMNA' });
        (c.orden = ordenColumnas(c)); marcarSucio(); pintarColumnas(seleccion);
    });

    // Partidas y actividades de un bloque de tareas
    function pintarFuentes(b) {
        const caja = document.getElementById('vp-fuentes');
        const fuentes = b.config.fuentes || [];
        const estado = id => { const f = fuentes.find(x => x.partida === id); return f ? (f.actividades ? new Set(f.actividades) : 'todas') : null; };
        caja.innerHTML = D.partidas.map(p => {
            const st = estado(p.id);
            const marcadas = st === 'todas' ? p.actividades.length : (st ? st.size : 0);
            return '<details class="vp-fuente" data-partida="' + p.id + '"' + (st ? ' open' : '') + '><summary><label><input type="checkbox" class="vp-fp" data-partida="' + p.id + '"' + (marcadas === p.actividades.length && marcadas ? ' checked' : '') + '> <b>' + esc(p.codigo) + '</b> ' + esc(p.nombre) + ' <small class="text-muted">(' + p.actividades.length + ')</small></label></summary>' +
                p.actividades.map(a => '<label class="vp-acts-item"><input type="checkbox" class="vp-fa" data-partida="' + p.id + '" value="' + a.id + '"' + (st === 'todas' || (st && st.has(a.id)) ? ' checked' : '') + '> <b>' + a.seq + '.</b> ' + esc(a.nombre) + '</label>').join('') + '</details>';
        }).join('');
        caja.querySelectorAll('.vp-fp').forEach(i => { const marc = caja.querySelectorAll('.vp-fa[data-partida="' + i.dataset.partida + '"]:checked').length, tot = caja.querySelectorAll('.vp-fa[data-partida="' + i.dataset.partida + '"]').length; i.indeterminate = marc > 0 && marc < tot; });
        const leer = () => {
            const out = [];
            D.partidas.forEach(p => {
                const todas = [...caja.querySelectorAll('.vp-fa[data-partida="' + p.id + '"]')], marc = todas.filter(x => x.checked).map(x => +x.value);
                if (marc.length) out.push({ partida: p.id, actividades: marc.length === todas.length ? null : marc });
                const pc = caja.querySelector('.vp-fp[data-partida="' + p.id + '"]');
                pc.checked = marc.length === todas.length && marc.length > 0; pc.indeterminate = marc.length > 0 && marc.length < todas.length;
            });
            b.config.fuentes = out; marcarSucio();
        };
        caja.addEventListener('change', e => {
            if (e.target.classList.contains('vp-fp')) caja.querySelectorAll('.vp-fa[data-partida="' + e.target.dataset.partida + '"]').forEach(x => x.checked = e.target.checked);
            leer();
        });
    }

    /** Pide al servidor el dibujo de la estampa con datos reales y lo pone dentro del bloque (sin tocar la capa de edición) */
    async function cargarEstampa(b) {
        const el = elDe(b); if (!el) return;
        const c = b.config;
        if (!c.estampa_id) { el.querySelectorAll(':scope > :not(.vp-ov)').forEach(x => x.remove()); el.insertAdjacentHTML('afterbegin', '<p class="vp-vacio-blk">Elige una estampa</p>'); return; }
        try {
            const d = await post('/api/estampas/vista', { proyecto: D.proyecto, id: c.estampa_id, lamina: D.lamina, modo: 'cq', actividad: c.actividad_id || 0, corte: c.fecha_modo === 'fija' ? (c.fecha || '') : '' });
            el.querySelectorAll(':scope > :not(.vp-ov)').forEach(x => x.remove());
            el.insertAdjacentHTML('afterbegin', d.success ? d.html : '<p class="vp-vacio-blk">' + esc(d.error || 'Estampa no disponible') + '</p>');
        } catch (e) { /* sin conexión: se verá al guardar */ }
    }

    // ================== Insertar ==================
    const DIMS = { titulo: [30, 8], logo: [14, 8], plano: [46, 60], tareas: [47, 40], simbologia: [27, 16], schedule: [16, 16], supervisor: [30, 3], fecha: [30, 3], texto: [30, 6], imagen: [10, 10], cajetin: [26, 16], estampa: [26, 16] };

    /** Crea un bloque nuevo (aún sin guardar) y su capa en la hoja */
    function crearBloque(tipo, config, x, y, w, h) {
        const dims = DIMS[tipo] || [20, 8];
        w = w || dims[0]; h = h || dims[1];
        const b = { id: null, clave: 'n' + (++nuevoN), tipo, x: redond(x == null ? (100 - w) / 2 : x), y: redond(y == null ? (100 - h) / 2 : y), w, h, z: bloques.length,
            config: config || (tipo === 'tareas' ? { fuentes: [], seq: 'continuo' } : tipo === 'logo' ? { cual: 'contratista' } : {}) };
        bloques.push(b);
        const el = document.createElement('div');
        el.className = 'vp-blk vp-b-' + tipo + ' vp-b-nuevo';
        el.dataset.tipo = tipo;
        el.innerHTML = '<p class="vp-vacio-blk">' + esc(D.tipos[tipo]) + '<br><small>Guarda el diseño para ver su contenido</small></p>';
        sheet.appendChild(el);
        asegurarCapa(b, el); aplicar(b); marcarSucio(); pintarCapas();
        return b;
    }
    function agregarBloque(tipo) {
        if (UNICOS.includes(tipo) && bloques.some(b => b.tipo === tipo)) { window.avisoModal('Bloque repetido', 'Esta lámina ya tiene un bloque de ese tipo.'); return; }
        const b = crearBloque(tipo);
        seleccionar(b, true);
    }
    function actualizarInsertar() {
        const caja = $('vp-ins-bloques');
        caja.innerHTML = Object.keys(D.tipos).filter(t => t !== 'tabla' && t !== 'imagen' && t !== 'cajetin' && t !== 'estampa' && t !== 'simbologia').map(t => {
            const usado = UNICOS.includes(t) && bloques.some(b => b.tipo === t);
            return '<button type="button" class="vp-ins-bloque" data-tipo="' + t + '"' + (usado ? ' disabled title="Ya está en la lámina"' : '') + '><i class="fa-solid ' + (ICONOS[t] || 'fa-square') + '"></i><span>' + esc(D.tipos[t]) + '</span></button>';
        }).join('');
    }
    $('vp-ins-bloques').addEventListener('click', e => { const b = e.target.closest('.vp-ins-bloque'); if (b && !b.disabled) agregarBloque(b.dataset.tipo); });

    // Símbolos, simbologías del proyecto y cajetines: se insertan como copia propia (imagen) o como tabla con variables
    const BIB = D.biblioteca || { simbolos: [], graficos: [], cajetines: [], estampas: [] };
    function pintarBiblioteca() {
        const lista = $('vp-sim-lista'); if (!lista) return;
        const q = $('vp-sim-buscar').value.trim().toLowerCase();
        const grupos = {};
        (BIB.simbolos || []).filter(x => !q || (x.nombre + ' ' + x.cat).toLowerCase().includes(q)).forEach(x => { (grupos[x.cat] = grupos[x.cat] || []).push(x); });
        const gr = (BIB.graficos || []).filter(x => !q || x.nombre.toLowerCase().includes(q));
        const sims = gr.filter(x => x.tipo === 'simbologia'), otros = gr.filter(x => x.tipo !== 'simbologia');
        const g0 = {};
        if (sims.length) g0['Simbologías del proyecto'] = sims;
        if (otros.length) g0['Gráficos del proyecto'] = otros;
        Object.keys(grupos).forEach(k => { g0[k] = grupos[k]; });
        Object.keys(grupos).forEach(k => { delete grupos[k]; });
        Object.assign(grupos, g0);
        const btnTodas = $('vp-sim-todas'); if (btnTodas) btnTodas.hidden = !(BIB.graficos || []).some(x => x.tipo === 'simbologia');
        lista.innerHTML = Object.keys(grupos).map(cat => '<h5 class="vp-ins-grupo">' + esc(cat) + '</h5><div class="vp-sim-grid">' + grupos[cat].map(x =>
            '<button type="button" class="vp-sim" data-src="' + esc(x.src) + '" data-nombre="' + esc(x.nombre) + '"' + (x.ref ? ' data-ref="' + esc(x.ref) + '" data-v="' + esc(x.v || '') + '"' : '') + ' title="' + esc(x.nombre) + (x.ref ? ' (vinculada: se actualiza sola)' : '') + '"><img src="' + esc(x.src) + '" alt="" loading="lazy"><span>' + esc(x.nombre) + '</span></button>').join('') + '</div>').join('')
            || '<p class="text-sm text-muted">Sin resultados.</p>';
        const est = $('vp-sim-estampas');
        if (est) est.innerHTML = (BIB.estampas || []).map(x => '<button type="button" class="vp-ins-bloque" data-estampa="' + (+x.id) + '" title="Estampa inteligente: se actualiza sola con los datos del proyecto"><i class="fa-solid fa-wand-magic-sparkles"></i><span>' + esc(x.nombre) + '</span></button>').join('')
            || '<p class="text-sm text-muted">Aún no hay estampas. Créalas en <a href="/simbolos?proyecto=' + D.proyecto + '#estampas" target="_blank" rel="noopener">Símbolos › Estampas inteligentes</a>.</p>';
        $('vp-sim-cajetines').innerHTML = (BIB.cajetines || []).map(c =>
            '<button type="button" class="vp-ins-bloque" data-cajetin="' + (+c.id || 0) + '" title="' + esc(c.orientacion === 'banda' ? 'Banda' : 'Tabla de dos columnas') + '"><i class="fa-solid fa-table-list"></i><span>' + esc(c.nombre) + '</span></button>').join('');
    }
    const rasterizar = url => window.KitEdicion.rasterizar(url, 1024);
    async function insertarImagen(src, nombre, ref, refv) {
        const est = $('vp-sim-estado'); est.textContent = 'Insertando…';
        try {
            const blob = await rasterizar(src);
            const fd = new FormData(); fd.append('lamina', D.lamina); fd.append('imagen', new File([blob], 'simbolo.png', { type: blob.type || 'image/png' }));
            const d = await (await fetch('/api/vplan/imagen', { method: 'POST', body: fd })).json();
            if (!d.success) throw new Error(d.error || 'no se pudo subir');
            const dim = await new Promise(ok => { const i = new Image(); i.onload = () => ok([i.naturalWidth || 1, i.naturalHeight || 1]); i.onerror = () => ok([1, 1]); i.src = d.src; });
            const ancho = Math.min(20, 12), hojaR = (tamano.ancho_mm || 1) / (tamano.alto_mm || 1);
            let w = ancho, h = redond(w * hojaR * dim[1] / dim[0]);
            if (h > 40) { h = 40; w = redond(h / hojaR * dim[0] / dim[1]); }
            const b = crearBloque('imagen', Object.assign({ archivo: d.src.split('/').pop(), nombre }, ref ? { ref, refv: refv || '' } : {}), null, null, w, h);
            const el = elDe(b); if (el) el.innerHTML = '<div class="vp-img" style="justify-content:center"><img src="' + esc(d.src) + '" alt=""></div>';
            seleccionar(b, true);
            est.textContent = '';
        } catch (e) { est.textContent = 'No se pudo insertar: ' + (e.message || 'error'); }
    }
    if ($('vp-sim-lista')) {
        pintarBiblioteca();
        $('vp-sim-buscar').addEventListener('input', pintarBiblioteca);
        $('vp-sim-todas').addEventListener('click', async () => {
            for (const x of (BIB.graficos || []).filter(x => x.tipo === 'simbologia')) await insertarImagen(x.src, x.nombre, x.ref, x.v);
        });
        $('vp-sim-lista').addEventListener('click', e => { const b = e.target.closest('.vp-sim'); if (b) insertarImagen(b.dataset.src, b.dataset.nombre, b.dataset.ref, b.dataset.v); });
        if ($('vp-sim-estampas')) $('vp-sim-estampas').addEventListener('click', e => {
            const b = e.target.closest('[data-estampa]'); if (!b) return;
            const x = (BIB.estampas || []).find(y => +y.id === +b.dataset.estampa); if (!x) return;
            const blk = crearBloque('estampa', { estampa_id: +x.id, fecha_modo: 'hoy' }, null, null, Math.min(100, x.w || 26), Math.min(100, x.h || 16));
            seleccionar(blk, true); cargarEstampa(blk);
        });
        $('vp-sim-cajetines').addEventListener('click', e => {
            const b = e.target.closest('[data-cajetin]'); if (!b) return;
            const id = +b.dataset.cajetin || 0, c = (BIB.cajetines || []).find(x => (+x.id || 0) === id) || {};
            const banda = c.orientacion === 'banda', n = (c.filas || []).length || 4;
            const w = banda ? Math.min(90, Math.max(30, n * 12)) : Math.min(60, Math.max(15, +c.ancho || 26));
            const h = banda ? 6 : Math.min(40, Math.max(6, n * 3.2));
            seleccionar(crearBloque('cajetin', { cajetin_id: id }, null, null, w, h), true);
        });
    }

    // Tablas: todas las del catálogo (de todos los tipos) y las hojas de SheetFlow
    const catalogo = D.catalogo || [];
    function pintarTablas() {
        const q = $('vp-ins-buscar').value.trim().toLowerCase();
        const lista = catalogo.map((t, i) => ({ t, i })).filter(({ t }) => !q || (t.nombre + ' ' + t.titulo + ' ' + t.tipoNombre).toLowerCase().includes(q));
        const grupos = {};
        lista.forEach(x => { (grupos[x.t.tipoNombre] = grupos[x.t.tipoNombre] || []).push(x); });
        const caja = $('vp-ins-tablas');
        if (!lista.length) { caja.innerHTML = '<p class="text-sm text-muted">' + (catalogo.length ? 'Ninguna tabla coincide.' : 'Aún no hay tablas. Crea la primera abajo.') + '</p>'; return; }
        caja.innerHTML = Object.keys(grupos).map(g =>
            '<h5 class="vp-ins-grupo">' + esc(g) + ' <small>(' + grupos[g].length + ')</small></h5>' + grupos[g].map(({ t, i }) =>
                '<div class="vp-ins-tabla' + (t.enLamina ? ' vp-ya' : '') + '" data-i="' + i + '"><div class="vp-ins-tabla-txt"><b>' + esc(t.nombre) + '</b><small>' + t.filas + ' renglón(es)' + (t.sheetflow ? ' · SheetFlow' : '') + '</small></div>' +
                (t.enLamina ? '<span class="vp-chip" title="Ya está en esta lámina">En la lámina</span>' : '<button type="button" class="btn btn-sm btn-primary" data-acc="ins"><i class="fa-solid fa-plus"></i> Insertar</button>') +
                (t.id ? '<button type="button" class="vp-ib vp-ib-sm" data-acc="copia" title="Insertar una copia independiente (sus cambios no afectan a las demás láminas)" aria-label="Insertar copia"><i class="fa-regular fa-copy"></i></button>' : '') +
                '</div>').join('')).join('');
    }
    $('vp-ins-buscar').addEventListener('input', pintarTablas);
    $('vp-ins-tablas').addEventListener('click', async e => {
        const btn = e.target.closest('[data-acc]'), fila = e.target.closest('.vp-ins-tabla');
        if (!btn || !fila) return;
        const t = catalogo[+fila.dataset.i];
        const datos = { proyecto: D.proyecto, lamina: D.lamina, accion: 'agregar', ajax: 1 };
        if (t.id) datos.tabla_id = t.id; else datos.sheetflow_id = t.sheetflow_id;
        if (btn.dataset.acc === 'copia') datos.copia = 1;
        await conServidor(datos, '/vplan/lamina/tabla', 'No se pudo insertar la tabla');
    });

    // Recovery: tantas columnas como se necesiten
    function pintarRecovery() {
        const n = D.recovery.n;
        $('vp-ins-recovery').innerHTML = n
            ? Array.from({ length: n }, (_, i) => '<li class="vp-capa"><i class="fa-solid fa-rotate-left vp-capa-ico"></i><span class="vp-capa-nombre">' + esc(D.etiquetasRecovery[i]) + ' <small>inicio · fin</small></span></li>').join('')
            : '<li class="text-sm text-muted">Sin columnas de recovery.</li>';
        $('vp-rec-mas').disabled = n >= D.recovery.max;
        $('vp-rec-menos').disabled = n <= 0;
    }
    $('vp-rec-mas').addEventListener('click', () => conServidor({ proyecto: D.proyecto, lamina: D.lamina, accion: 'agregar', ajax: 1 }, '/vplan/lamina/recovery', 'No se pudo agregar el recovery'));
    $('vp-rec-menos').addEventListener('click', async () => {
        const ok = await window.confirmarModal({ titulo: '¿Quitar «' + D.etiquetasRecovery[D.recovery.n - 1] + '»?', mensaje: 'Se oculta la última columna de recovery; las fechas capturadas se conservan.', aceptar: 'Sí, quitar', iconoBoton: 'fa-trash-can' });
        if (ok) conServidor({ proyecto: D.proyecto, lamina: D.lamina, accion: 'quitar', ajax: 1 }, '/vplan/lamina/recovery', 'No se pudo quitar el recovery');
    });

    /** Guarda el diseño pendiente, hace el cambio en el servidor y recarga la hoja (las tablas y columnas se dibujan en el servidor) */
    async function conServidor(datos, url, errorMsg) {
        const msg = $('vp-dis-msg');
        document.querySelectorAll('#vp-ins-tablas button, #vp-rec-mas, #vp-rec-menos').forEach(b => { b.disabled = true; });
        try {
            if (sucio && !(await guardarDiseno())) throw new Error('guardar');
            const d = await post(url, datos);
            if (!d.success) throw new Error('servidor');
            location.href = D.volver + '&diseno=1';
        } catch (_) {
            if (!msg.textContent || msg.textContent === 'Guardando…') msg.textContent = errorMsg;
            pintarTablas(); pintarRecovery();
        }
    }

    // ---- Tamaño de la hoja ----
    const selPreset = document.getElementById('vp-dis-preset'), selOri = document.getElementById('vp-dis-orientacion'), custom = document.getElementById('vp-dis-custom');
    const leerTamano = () => {
        const preset = selPreset.value, ori = selOri.value;
        let w, h;
        if (D.presets[preset]) { [w, h] = D.presets[preset].slice(1); if (ori === 'vertical') [w, h] = [h, w]; }
        else {
            const u = { mm: 1, cm: 10, in: 25.4 }[document.getElementById('vp-dis-unidad').value] || 1;
            w = num(document.getElementById('vp-dis-ancho').value, 0) * u; h = num(document.getElementById('vp-dis-alto').value, 0) * u;
        }
        return { preset, w: Math.max(100, Math.min(1500, Math.round(w))), h: Math.max(100, Math.min(1500, Math.round(h))) };
    };
    const cambioTamano = () => {
        custom.hidden = selPreset.value !== 'personalizado';
        selOri.disabled = selPreset.value === 'personalizado';
        const t = leerTamano();
        if (!(t.w > 0 && t.h > 0)) return;
        tamano = { preset: t.preset, ancho_mm: t.w, alto_mm: t.h };
        sheet.style.aspectRatio = t.w + ' / ' + t.h;
        sheet.style.setProperty('--vp-ratio', t.w / t.h);
        document.getElementById('vp-dis-medidas').textContent = t.w + ' × ' + t.h + ' mm';
        if (seleccion) llenarPosicion(seleccion);
        marcarSucio();
    };
    [selPreset, selOri, document.getElementById('vp-dis-ancho'), document.getElementById('vp-dis-alto'), document.getElementById('vp-dis-unidad')].forEach(i => i.addEventListener('change', cambioTamano));
    custom.hidden = selPreset.value !== 'personalizado';
    selOri.disabled = selPreset.value === 'personalizado';


    // ================== Guardar ==================
    async function guardarDiseno() {
        const btn = $('vp-dis-guardar'), msg = $('vp-dis-msg');
        btn.disabled = true; msg.textContent = 'Guardando…';
        const t = leerTamano();
        const diseno = {
            tamano: { preset: t.preset, orientacion: selOri.value, ancho: t.w, alto: t.h, unidad: 'mm' },
            bloques: bloques.slice().sort((a, b) => a.z - b.z).map(b => ({ id: b.id, tipo: b.tipo, x: b.x, y: b.y, w: b.w, h: b.h, config: b.config })),
            guias: guias.map(g => ({ id: g.id, eje: g.eje, pos: g.pos, visible: g.visible, bloqueada: g.bloqueada, nombre: g.nombre })),
        };
        try {
            const d = await post('/api/vplan/diseno', { lamina: D.lamina, diseno: JSON.stringify(diseno) });
            if (!d.success) { msg.textContent = d.error || 'No se pudo guardar'; btn.disabled = false; return false; }
            sucio = false; btn.classList.remove('vp-pendiente'); btn.disabled = false; msg.textContent = '';
            return true;
        } catch (_) { msg.textContent = 'Error de conexión'; btn.disabled = false; return false; }
    }
    $('vp-dis-guardar').addEventListener('click', async () => { if (await guardarDiseno()) location.href = D.volver + '&diseno=1'; });
    // ---- Deshacer / rehacer (Ctrl+Z, Ctrl+Y o Ctrl+Mayús+Z): foto de bloques y guías tras cada cambio ----
    const hist = window.KitEdicion.historial({
        foto: () => { const b = JSON.stringify(bloques), g = JSON.stringify(guias); return { k: b + '|' + g, b, g, els: Object.fromEntries(bloques.map(x => [x.clave, elDe(x)])) }; },
        aplica: f => {
            bloques = JSON.parse(f.b); guias = JSON.parse(f.g);
            const claves = new Set(bloques.map(x => x.clave));
            sheet.querySelectorAll('.vp-blk').forEach(el => { if (!claves.has(el.dataset.clave)) el.remove(); });
            bloques.forEach(x => {
                let el = elDe(x);
                if (!el && f.els[x.clave]) { el = f.els[x.clave]; sheet.appendChild(el); }
                if (el) { asegurarCapa(x, el); el.classList.toggle('vp-b-oculto', !!x.config.oculto); }
            });
            aplicarTodos(); pintarGuias(); pintarGuiasLista(); seleccionar(null); pintarCapas(); marcarSucio();
        },
    });
    ['input', 'change', 'click', 'pointerup', 'keyup'].forEach(t => document.addEventListener(t, hist.programar));
    window.KitEdicion.atajos({
        deshacer: hist.deshacer, rehacer: hist.rehacer,
        antes: (e, k) => { if (k !== 's') return false; e.preventDefault(); $('vp-dis-guardar').click(); return true; },
    });
    // Al salir con cambios sin guardar: modal de la aplicación (el aviso del navegador queda solo para cerrar la pestaña o recargar)
    window.KitEdicion.avisoSalir({
        sucio: () => sucio, guardar: guardarDiseno, limpiar: () => { sucio = false; },
        titulo: '¿Salir sin guardar el diseño?', mensaje: 'Hiciste cambios en el diseño de la lámina que aún no se guardan.',
    });

    pintarGuias(); pintarCapas(); pintarGuiasLista(); actualizarInsertar(); pintarTablas(); pintarRecovery();
    seleccionar(null);
    requestAnimationFrame(dibujarReglas);
})();
