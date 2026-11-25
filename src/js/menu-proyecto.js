// Navegación del proyecto: barra compacta, riel lateral, panel «Todas las herramientas»
// y barra inferior móvil. Sin dependencias; los datos vienen de window.PW_NAV.
(() => {
    'use strict';

    const $ = (sel, raiz = document) => raiz.querySelector(sel);
    const $$ = (sel, raiz = document) => Array.from(raiz.querySelectorAll(sel));
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const norm = (t) => String(t ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    const leerJSON = (clave, defecto) => {
        try {
            const v = JSON.parse(localStorage.getItem(clave));
            return v === null || v === undefined ? defecto : v;
        } catch (_) { return defecto; }
    };
    const guardarJSON = (clave, valor) => { try { localStorage.setItem(clave, JSON.stringify(valor)); } catch (_) { /* sin almacenamiento */ } };

    const dialog = document.getElementById('nav-panel');
    const datos = Array.isArray(window.PW_NAV) ? window.PW_NAV : [];
    const mapaDatos = new Map(datos.map((t) => [t.key, t]));
    const activa = dialog?.dataset.activa || '';
    const pinesDefecto = (dialog?.dataset.fijadasDefecto || '').split(',').filter(Boolean);
    const CLAVE_PIN = 'pw-nav-fijadas';
    const CLAVE_REC = 'pw-recientes';

    // ---------- Fijadas ----------
    const leerFijadas = () => {
        const guardadas = leerJSON(CLAVE_PIN, null);
        const lista = Array.isArray(guardadas) ? guardadas : pinesDefecto;
        return lista.filter((k) => mapaDatos.has(k)).slice(0, 6);
    };

    const actualizarPines = () => {
        const fijadas = leerFijadas();
        $$('[data-nav-pin]').forEach((b) => {
            const on = fijadas.includes(b.dataset.navPin);
            const titulo = on ? 'Quitar de fijadas' : 'Fijar esta herramienta';
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.title = titulo;
            b.setAttribute('aria-label', titulo);
        });
        const botonBarra = $('[data-nav-fijar]');
        if (botonBarra) {
            const on = !!activa && fijadas.includes(activa);
            const titulo = on ? 'Quitar de fijadas' : 'Fijar esta herramienta';
            botonBarra.setAttribute('aria-pressed', on ? 'true' : 'false');
            botonBarra.title = titulo;
            botonBarra.setAttribute('aria-label', titulo);
            botonBarra.disabled = !activa;
        }
    };

    const chipDestacado = (t, quitar) => '<span class="nav-destacado">' +
        '<a href="' + esc(t.url) + '"><i class="fa-solid ' + esc(t.icono) + '"></i><span>' + esc(t.label) + '</span></a>' +
        (quitar ? '<button type="button" class="nav-destacado-quitar" data-nav-quitar="' + esc(t.key) + '" title="Quitar de fijadas" aria-label="Quitar de fijadas">×</button>' : '') +
        '</span>';

    const leerRecientes = () => {
        const lista = leerJSON(CLAVE_REC, []);
        if (!Array.isArray(lista)) return [];
        const vistos = new Set();
        const salida = [];
        lista.forEach((r) => {
            if (!r || !r.href) return;
            let path = '';
            try { path = new URL(r.href, location.origin).pathname.replace(/\/+$/, '') || '/'; } catch (_) { return; }
            const t = datos.find((x) => {
                try { return new URL(x.url, location.origin).pathname.replace(/\/+$/, '') === path; } catch (_) { return false; }
            });
            if (!t || vistos.has(t.key)) return;
            vistos.add(t.key);
            salida.push(t);
        });
        return salida.slice(0, 4);
    };

    const renderDestacados = () => {
        const caja = document.getElementById('nav-panel-destacados');
        if (!caja) return;
        const fijadas = leerFijadas().map((k) => mapaDatos.get(k)).filter(Boolean);
        const recents = leerRecientes();
        const bloques = [];
        if (fijadas.length) {
            bloques.push('<div class="nav-panel-destacado"><h4><i class="fa-solid fa-thumbtack"></i> Fijadas</h4><div>' + fijadas.map((t) => chipDestacado(t, true)).join('') + '</div></div>');
        }
        if (recents.length) {
            bloques.push('<div class="nav-panel-destacado"><h4><i class="fa-solid fa-clock-rotate-left"></i> Recientes</h4><div>' + recents.map((t) => chipDestacado(t, false)).join('') + '</div></div>');
        }
        caja.innerHTML = bloques.join('');
        caja.hidden = bloques.length === 0;
        $$('[data-nav-quitar]', caja).forEach((b) => b.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            alternarFijada(b.dataset.navQuitar);
        }));
    };

    const alternarFijada = (clave) => {
        if (!mapaDatos.has(clave)) return;
        const lista = leerFijadas();
        const i = lista.indexOf(clave);
        if (i >= 0) lista.splice(i, 1); else lista.unshift(clave);
        guardarJSON(CLAVE_PIN, lista.slice(0, 6));
        actualizarPines();
        renderDestacados();
    };

    // ---------- Panel «Todas las herramientas» ----------
    let filtroActual = 'todas';
    const aplicarFiltro = (f) => {
        filtroActual = (!f || f === 'todas') ? 'todas' : String(f);
        if (dialog) {
            $$('.nav-panel-chip', dialog).forEach((b) => {
                const on = b.dataset.navFiltro === filtroActual;
                b.classList.toggle('activo', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }
        aplicarBusqueda();
    };

    const aplicarBusqueda = () => {
        if (!dialog) return;
        const q = norm($('#nav-panel-q', dialog)?.value || '');
        let visibles = 0;
        $$('.nav-panel-grupo', dialog).forEach((sec) => {
            const gi = sec.dataset.navGrupoPanel;
            const coincideGrupo = filtroActual === 'todas' || filtroActual === gi;
            let n = 0;
            $$('.nav-panel-item', sec).forEach((item) => {
                const coincide = coincideGrupo && (!q || norm(item.dataset.buscar).includes(q));
                item.hidden = !coincide;
                if (coincide) n++;
            });
            sec.hidden = n === 0;
            visibles += n;
        });
        const vacio = document.getElementById('nav-panel-vacio');
        if (vacio) vacio.hidden = visibles > 0;
        const destacados = document.getElementById('nav-panel-destacados');
        if (destacados) destacados.hidden = destacados.innerHTML === '' || q !== '' || filtroActual !== 'todas';
    };

    const abrirPanel = (filtro = 'todas') => {
        if (!dialog) return;
        cerrarProyecto();
        cerrarCuenta();
        cerrarSecciones();
        cerrarRiel();
        cerrarBuscar();
        const q = document.getElementById('nav-panel-q');
        if (q) q.value = '';
        aplicarFiltro(filtro);
        if (typeof dialog.showModal === 'function') {
            if (!dialog.open) dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
            dialog.style.display = 'block';
        }
        setTimeout(() => { if (q) q.focus(); }, 30);
    };

    const cerrarPanel = () => {
        if (!dialog) return;
        if (typeof dialog.close === 'function' && dialog.open) dialog.close();
        else { dialog.removeAttribute('open'); dialog.style.display = ''; }
    };

    if (dialog) {
        $$('[data-nav-cerrar]', dialog).forEach((b) => b.addEventListener('click', cerrarPanel));
        dialog.addEventListener('click', (e) => { if (e.target === dialog) cerrarPanel(); });
        const q = document.getElementById('nav-panel-q');
        q?.addEventListener('input', aplicarBusqueda);
        q?.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            const primero = $$('.nav-panel-enlace', dialog).find((a) => a.offsetParent !== null);
            if (primero) { e.preventDefault(); primero.click(); }
        });
        $$('.nav-panel-chip', dialog).forEach((b) => b.addEventListener('click', () => aplicarFiltro(b.dataset.navFiltro)));
        $$('[data-nav-pin]', dialog).forEach((b) => b.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            alternarFijada(b.dataset.navPin);
        }));
        actualizarPines();
        renderDestacados();
        aplicarFiltro('todas');
        $$('[data-nav-kbd]').forEach((k) => { k.textContent = /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘K' : 'Ctrl K'; });
        // Escape cierra aunque el foco esté en el buscador (el input de búsqueda lo consume si no se captura).
        dialog.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            e.preventDefault();
            e.stopPropagation();
            cerrarPanel();
        }, true);
        // Las recientes las guarda app.js al cargar: se repinta cuando ya están.
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', renderDestacados);
        else setTimeout(renderDestacados, 0);
    }

    // ---------- Barra de contexto: selector de sección ----------
    const nav = $('.nav-contexto');
    const cerrarSecciones = () => {
        const menu = document.getElementById('nav-secciones');
        if (menu) menu.hidden = true;
        const btn = $('[data-nav-seccion]');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    };

    const revisarDock = () => {
        const dock = $('[data-nav-dock]', nav || document);
        const mas = $('.nav-mas', nav || document);
        if (!dock || !mas) return;
        mas.style.display = dock.scrollWidth > dock.clientWidth + 2 ? 'inline-flex' : 'none';
    };

    if (nav) {
        const btnSeccion = $('[data-nav-seccion]', nav);
        const menu = document.getElementById('nav-secciones');
        const opciones = $$('[data-nav-grupo]', menu || document);

        const cambiarSeccion = (i) => {
            const op = opciones.find((o) => o.dataset.navGrupo === String(i));
            if (!op) return;
            $$('[data-nav-panel]', nav).forEach((p) => { p.hidden = p.dataset.navPanel !== String(i); });
            opciones.forEach((o) => o.setAttribute('aria-pressed', o === op ? 'true' : 'false'));
            const txt = $('[data-nav-seccion-txt]', btnSeccion);
            if (txt) txt.textContent = op.dataset.navNombre;
            const cuenta = $('[data-nav-seccion-cuenta]', btnSeccion);
            if (cuenta) cuenta.textContent = op.dataset.navCuenta;
            const icono = btnSeccion.querySelector('i');
            if (icono) icono.className = 'fa-solid ' + op.dataset.navIcono;
            const mas = $('.nav-mas', nav);
            if (mas) mas.dataset.navFiltroGrupo = String(i);
            cerrarSecciones();
            setTimeout(revisarDock, 0);
        };

        btnSeccion?.addEventListener('click', () => {
            if (!menu) return;
            const abrir = menu.hidden;
            cerrarSecciones();
            if (abrir) {
                menu.hidden = false;
                btnSeccion.setAttribute('aria-expanded', 'true');
            }
        });
        opciones.forEach((op) => op.addEventListener('click', () => cambiarSeccion(op.dataset.navGrupo)));
        menu?.addEventListener('click', (e) => e.stopPropagation());
        revisarDock();
        window.addEventListener('resize', revisarDock);
    }

    // ---------- Botones comunes: todas, fijar, menú ----------
    $$('[data-nav-todas]').forEach((b) => b.addEventListener('click', (e) => {
        e.preventDefault();
        if (!dialog) { document.getElementById('btn-menu')?.click(); return; }
        abrirPanel(b.dataset.navFiltroGrupo || 'todas');
    }));

    $('[data-nav-fijar]')?.addEventListener('click', () => { if (activa) alternarFijada(activa); });

    $$('[data-nav-abrir-menu]').forEach((b) => b.addEventListener('click', (e) => {
        e.preventDefault();
        cerrarRiel();
        document.getElementById('btn-menu')?.click();
    }));

    // ---------- Riel lateral ----------
    const rielPanel = document.getElementById('nav-riel-panel');
    const rielBtns = $$('[data-riel-grupo]');
    const rielGrupos = $$('[data-riel-panel]', rielPanel || document);
    const rielTitulo = document.getElementById('nav-riel-panel-titulo');
    const rielActivaInicial = rielBtns.find((b) => b.classList.contains('activa')) || null;

    const cerrarRiel = () => {
        if (rielPanel) rielPanel.hidden = true;
        rielBtns.forEach((b) => b.setAttribute('aria-expanded', 'false'));
        rielBtns.forEach((b) => b.classList.toggle('activa', b === rielActivaInicial));
    };

    const abrirRiel = (i) => {
        const btn = rielBtns.find((b) => b.dataset.rielGrupo === String(i));
        if (!btn || !rielPanel) return;
        if (rielTitulo) rielTitulo.textContent = btn.dataset.rielNombre;
        rielGrupos.forEach((g) => { g.hidden = g.dataset.rielPanel !== String(i); });
        rielPanel.hidden = false;
        rielBtns.forEach((b) => {
            b.setAttribute('aria-expanded', b === btn ? 'true' : 'false');
            b.classList.toggle('activa', b === btn);
        });
    };

    rielBtns.forEach((b) => b.addEventListener('click', () => {
        const abierto = rielPanel && !rielPanel.hidden && b.getAttribute('aria-expanded') === 'true';
        if (abierto) { cerrarRiel(); return; }
        abrirRiel(b.dataset.rielGrupo);
    }));
    $('[data-riel-cerrar]')?.addEventListener('click', cerrarRiel);

    // ---------- Menús de la barra: proyecto y cuenta ----------
    const cerrarProyecto = () => {
        const panel = document.getElementById('appbar-proyecto-panel');
        const btn = document.getElementById('appbar-proyecto-btn');
        if (panel) panel.hidden = true;
        if (btn) btn.setAttribute('aria-expanded', 'false');
    };
    const cerrarCuenta = () => {
        const panel = document.getElementById('appbar-menu');
        const btn = document.getElementById('appbar-avatar-btn');
        if (panel) panel.hidden = true;
        if (btn) btn.setAttribute('aria-expanded', 'false');
    };

    const menuProyectoBtn = document.getElementById('appbar-proyecto-btn');
    const menuProyecto = document.getElementById('appbar-proyecto-panel');
    if (menuProyectoBtn && menuProyecto) {
        menuProyectoBtn.addEventListener('click', () => {
            const abrir = menuProyecto.hidden;
            cerrarProyecto();
            cerrarCuenta();
            cerrarSecciones();
            cerrarRiel();
            if (abrir) {
                menuProyecto.hidden = false;
                menuProyectoBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    const menuCuentaBtn = document.getElementById('appbar-avatar-btn');
    const menuCuenta = document.getElementById('appbar-menu');
    if (menuCuentaBtn && menuCuenta) {
        menuCuentaBtn.addEventListener('click', () => {
            const abrir = menuCuenta.hidden;
            cerrarProyecto();
            cerrarCuenta();
            cerrarSecciones();
            cerrarRiel();
            if (abrir) {
                menuCuenta.hidden = false;
                menuCuentaBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // ---------- Búsqueda móvil ----------
    const btnBuscar = document.getElementById('appbar-buscar-btn');
    const cajaBuscar = document.getElementById('appbar-buscar-movil');
    const cerrarBuscar = () => {
        if (!cajaBuscar) return;
        cajaBuscar.hidden = true;
        btnBuscar?.setAttribute('aria-expanded', 'false');
    };
    if (btnBuscar && cajaBuscar) {
        btnBuscar.addEventListener('click', () => {
            const abrir = cajaBuscar.hidden;
            cerrarBuscar();
            if (abrir) {
                cajaBuscar.hidden = false;
                btnBuscar.setAttribute('aria-expanded', 'true');
                cajaBuscar.querySelector('input[type=search]')?.focus();
            }
        });
        $('[data-buscar-cerrar]', cajaBuscar)?.addEventListener('click', cerrarBuscar);
        cajaBuscar.addEventListener('click', (e) => e.stopPropagation());
    }

    // ---------- Cerrar al hacer clic fuera / Escape ----------
    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!t.closest('#appbar-proyecto-btn, #appbar-proyecto-panel')) cerrarProyecto();
        if (!t.closest('#appbar-avatar-btn, #appbar-menu')) cerrarCuenta();
        if (!t.closest('[data-nav-seccion], #nav-secciones')) cerrarSecciones();
        if (!t.closest('.nav-riel, #nav-riel-panel')) cerrarRiel();
        if (!t.closest('#appbar-buscar-btn, #appbar-buscar-movil')) cerrarBuscar();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cerrarProyecto();
            cerrarCuenta();
            cerrarSecciones();
            cerrarRiel();
            cerrarBuscar();
            return;
        }
        if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 'k') return;
        if (!dialog) return;
        const t = e.target;
        if (t && (t.closest('input, textarea, select') || t.isContentEditable)) return;
        e.preventDefault();
        abrirPanel('todas');
    });

    // La campana cierra los menús de la barra antes de abrir su panel.
    document.getElementById('notif-btn')?.addEventListener('click', () => {
        cerrarProyecto();
        cerrarCuenta();
        cerrarSecciones();
        cerrarRiel();
        cerrarBuscar();
    });
})();
