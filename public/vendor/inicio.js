// Inicio · widgets, pestañas, ventana de personalización y lanzador de herramientas.
// Sin dependencias. Los datos de herramientas los pinta el servidor; aquí solo se
// ordenan, filtran y se les cambia el proyecto.
(() => {
    'use strict';

    const $ = (sel, raiz = document) => raiz.querySelector(sel);
    const $$ = (sel, raiz = document) => Array.from(raiz.querySelectorAll(sel));
    const norm = (t) => String(t ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    const leerJSON = (clave, defecto) => {
        try {
            const v = JSON.parse(localStorage.getItem(clave));
            return v === null || v === undefined ? defecto : v;
        } catch (_) { return defecto; }
    };
    const guardar = (clave, valor) => { try { localStorage.setItem(clave, JSON.stringify(valor)); } catch (_) { /* sin almacenamiento */ } };
    const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---------- Diálogos ----------
    const dlgPers = $('#inicio-personalizar');
    const dlgHerr = $('#inicio-herramientas-dialogo');

    const abrir = (dlg) => {
        if (!dlg) return;
        if (dlg.open) dlg.close();
        if (typeof dlg.showModal === 'function') dlg.showModal();
        else dlg.setAttribute('open', '');
    };
    const cerrar = (dlg) => {
        if (!dlg) return;
        if (typeof dlg.close === 'function') dlg.close();
        else dlg.removeAttribute('open');
    };

    $$('[data-inicio-personalizar]').forEach((b) => b.addEventListener('click', (e) => {
        e.preventDefault();
        abrir(dlgPers);
    }));
    $$('[data-inicio-herramientas]').forEach((b) => b.addEventListener('click', (e) => {
        e.preventDefault();
        abrir(dlgHerr);
        const grupo = b.dataset.grupo;
        if (grupo !== undefined && dlgHerr) {
            const chip = $(`[data-inicio-dialogo-seccion="${grupo}"]`, dlgHerr);
            if (chip) chip.click();
        }
        setTimeout(() => { const q = $('#inicio-herr-q'); if (q) q.focus(); }, 60);
    }));
    $$('[data-inicio-cerrar]').forEach((b) => b.addEventListener('click', () => {
        const dlg = b.closest('dialog');
        cerrar(dlg);
    }));
    $$('dialog.inicio-dialogo').forEach((dlg) => {
        dlg.addEventListener('click', (e) => {
            if (e.target === dlg) cerrar(dlg);
        });
    });

    // Si se llegó con ?personalizar=1, el diálogo viene con open para no depender de JS.
    if (dlgPers && dlgPers.dataset.inicioAbrir === '1') abrir(dlgPers);

    // Ctrl/⌘ + K abre todas las herramientas.
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            if (!dlgHerr) return;
            e.preventDefault();
            abrir(dlgHerr);
            setTimeout(() => { const q = $('#inicio-herr-q'); if (q) { q.focus(); q.select(); } }, 60);
        }
    });

    // ---------- Pestañas de atención ----------
    const activarTab = (nombre, mover = true) => {
        $$('[data-inicio-tab-btn]').forEach((b) => {
            const on = b.dataset.inicioTabBtn === nombre;
            b.classList.toggle('activo', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        $$('[data-inicio-panel]').forEach((p) => { p.hidden = p.dataset.inicioPanel !== nombre; });
        if (mover) {
            const destino = $('#atencion');
            if (destino) destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };
    $$('[data-inicio-tab-btn]').forEach((b) => b.addEventListener('click', () => activarTab(b.dataset.inicioTabBtn)));
    $$('[data-inicio-tab]').forEach((a) => a.addEventListener('click', (e) => {
        const nombre = a.dataset.inicioTab;
        if (!nombre || !$('#atencion')) return;
        e.preventDefault();
        activarTab(nombre, true);
    }));

    // ---------- Personalización ----------
    const lista = $('#inicio-bloques');
    const previa = $('#inicio-previa');
    const renumerar = () => {
        if (!lista) return;
        Array.from(lista.children).forEach((li, i) => {
            $$('[data-f]', li).forEach((inp) => {
                const campo = inp.dataset.f;
                if (campo === 'k') inp.name = `bloques[${i}][k]`;
                else if (campo === 'on') inp.name = `bloques[${i}][on]`;
                else if (campo === 'ancho') inp.name = `bloques[${i}][ancho]`;
            });
        });
        if (previa) {
            Array.from(lista.children).forEach((li) => {
                const p = $(`[data-previa="${li.dataset.bloque}"]`, previa);
                if (p) previa.append(p);
            });
        }
    };
    const sincronizarPrevia = () => {
        if (!lista || !previa) return;
        Array.from(lista.children).forEach((li) => {
            const k = li.dataset.bloque;
            const p = $(`[data-previa="${k}"]`, previa);
            if (!p) return;
            const on = $('[data-f="on"]', li)?.checked;
            const ancho = $('[data-f="ancho"]:checked', li)?.value || '12';
            p.hidden = !on;
            p.className = `inicio-previa-bloque inicio-previa-w-${ancho}`;
        });
        renumerar();
    };
    if (lista) {
        lista.addEventListener('click', (e) => {
            const b = e.target.closest('[data-mover]');
            if (!b) return;
            const li = b.closest('li');
            const d = Number(b.dataset.mover);
            if (d < 0 && li.previousElementSibling) li.previousElementSibling.before(li);
            if (d > 0 && li.nextElementSibling) li.nextElementSibling.after(li);
            sincronizarPrevia();
            const foco = $(`[data-mover="${d}"]`, li);
            if (foco) foco.focus();
        });
        lista.addEventListener('change', (e) => {
            if (e.target.matches('[data-f="on"], [data-f="ancho"]')) sincronizarPrevia();
        });
        lista.querySelectorAll('li').forEach((li) => {
            li.addEventListener('dragstart', (e) => {
                li.classList.add('arrastrando');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', li.dataset.bloque || '');
            });
            li.addEventListener('dragend', () => {
                li.classList.remove('arrastrando');
                sincronizarPrevia();
            });
        });
        lista.addEventListener('dragover', (e) => {
            e.preventDefault();
            const mov = $('.arrastrando', lista);
            if (!mov) return;
            const siguiente = Array.from(lista.children)
                .filter((x) => x !== mov)
                .find((x) => e.clientY < x.getBoundingClientRect().top + x.offsetHeight / 2);
            if (siguiente) siguiente.before(mov);
            else lista.append(mov);
        });
        sincronizarPrevia();
    }

    // ---------- Herramientas: proyecto, secciones, búsqueda, fijadas y recientes ----------
    const selects = $$('[data-inicio-proyecto]');
    const CLAVE_PROYECTO = 'pw-inicio-proyecto';
    const CLAVE_PIN = 'pw-nav-fijadas';
    const CLAVE_REC = 'pw-recientes';

    const proyectoActual = () => {
        const s = selects.find((x) => x.value) || selects[0];
        return s ? s.value : '';
    };

    const cambiarProyecto = (id, propagar = true) => {
        if (!id) return;
        if (propagar) selects.forEach((s) => { if (s.value !== id) s.value = id; });
        guardar(CLAVE_PROYECTO, id);
        $$('[data-inicio-tool]').forEach((a) => {
            const href = a.getAttribute('href') || '';
            if (!href || href.startsWith('#')) return;
            try {
                const u = new URL(href, location.origin);
                u.searchParams.set('proyecto', id);
                a.setAttribute('href', u.pathname + u.search + u.hash);
            } catch (_) { /* enlace externo */ }
        });
        $$('[data-inicio-proyecto-enlace]').forEach((a) => {
            const href = a.getAttribute('href') || '';
            if (!href) return;
            try {
                const u = new URL(href, location.origin);
                u.searchParams.set('proyecto', id);
                a.setAttribute('href', u.pathname + u.search + u.hash);
            } catch (_) { /* enlace externo */ }
        });
        const sel = selects[0];
        const opt = sel ? sel.options[sel.selectedIndex] : null;
        $$('[data-inicio-proyecto-nombre]').forEach((el) => { if (opt) el.textContent = opt.textContent.trim(); });
    };

    selects.forEach((s) => s.addEventListener('change', () => cambiarProyecto(s.value)));
    if (selects.length) {
        const guardado = leerJSON(CLAVE_PROYECTO, null);
        const existe = guardado && selects[0] && Array.from(selects[0].options).some((o) => o.value === String(guardado));
        if (existe) cambiarProyecto(String(guardado));
        else cambiarProyecto(proyectoActual(), false);
    }

    // Secciones del widget
    $$('[data-inicio-seccion]').forEach((b) => b.addEventListener('click', () => {
        const i = String(b.dataset.inicioSeccion);
        $$('[data-inicio-seccion]').forEach((x) => {
            const on = x === b;
            x.classList.toggle('activo', on);
            x.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        $$('[data-inicio-grupo]').forEach((g) => { g.hidden = g.dataset.inicioGrupo !== i; });
    }));

    // Ventana de todas las herramientas
    const buscar = $('[data-inicio-busca]');
    const gruposDlg = $$('[data-inicio-dialogo-grupo]');
    let chipDlg = 'todas';

    const aplicarFiltroDlg = () => {
        const q = norm(buscar?.value || '');
        let visibles = 0;
        gruposDlg.forEach((g) => {
            const idx = String(g.dataset.inicioDialogoGrupo);
            const coincideChip = chipDlg === 'todas' || chipDlg === idx;
            let n = 0;
            $$('[data-inicio-tool]', g).forEach((a) => {
                const coincide = !q || norm(a.dataset.buscar || a.textContent).includes(q);
                a.hidden = !coincide;
                if (coincide) n++;
            });
            const mostrar = n > 0 && (q ? true : coincideChip);
            g.hidden = !mostrar;
            if (mostrar) visibles += n;
        });
        const vacio = $('#inicio-herr-vacio');
        if (vacio) vacio.hidden = visibles > 0;
        const fijadas = $('#inicio-fijadas');
        const recientes = $('#inicio-recientes');
        if (q) {
            if (fijadas) fijadas.hidden = true;
            if (recientes) recientes.hidden = true;
        } else {
            if (fijadas) fijadas.hidden = !$('[data-inicio-tool]', fijadas);
            if (recientes) recientes.hidden = !$('[data-inicio-tool]', recientes);
        }
    };

    $$('[data-inicio-dialogo-seccion]').forEach((b) => b.addEventListener('click', () => {
        chipDlg = String(b.dataset.inicioDialogoSeccion);
        $$('[data-inicio-dialogo-seccion]').forEach((x) => {
            const on = x === b;
            x.classList.toggle('activo', on);
            x.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (buscar) buscar.value = '';
        aplicarFiltroDlg();
    }));
    if (buscar) buscar.addEventListener('input', aplicarFiltroDlg);

    // Fijadas y recientes (mismo almacenamiento que la navegación compacta)
    const clonarSeccion = (destino, titulo, items) => {
        if (!destino || !items.length) return;
        destino.innerHTML = '<h4><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> ' + esc(titulo) + '</h4><div class="inicio-dialogo-grid"></div>';
        const grid = $('.inicio-dialogo-grid', destino);
        items.forEach((it) => {
            const a = it.closest ? it : null;
            if (a) grid.append(a.cloneNode(true));
            else if (it.a) grid.append(it.a.cloneNode(true));
        });
        destino.hidden = false;
    };

    if (dlgHerr) {
        const porClave = new Map($$('[data-inicio-tool][data-key]', dlgHerr).map((a) => [a.dataset.key, a]));
        const fijadas = leerJSON(CLAVE_PIN, []);
        const listaFijas = Array.isArray(fijadas) ? fijadas : [];
        const itemsFijas = listaFijas.map((k) => porClave.get(k)).filter(Boolean).slice(0, 8);
        if (itemsFijas.length) {
            clonarSeccion($('#inicio-fijadas'), 'Fijadas', itemsFijas);
            const h4 = $('#inicio-fijadas h4');
            if (h4) h4.innerHTML = '<i class="fa-solid fa-thumbtack" aria-hidden="true"></i> ' + esc(document.documentElement.lang === 'en' ? 'Pinned' : 'Fijadas');
        }

        const recientes = leerJSON(CLAVE_REC, []);
        const vistos = new Set();
        const itemsRec = [];
        (Array.isArray(recientes) ? recientes : []).forEach((r) => {
            if (!r || !r.href) return;
            let path = '';
            try { path = new URL(r.href, location.origin).pathname.replace(/\/+$/, '') || '/'; } catch (_) { return; }
            const a = $$('[data-inicio-tool]', dlgHerr).find((x) => {
                try { return new URL(x.getAttribute('href') || '', location.origin).pathname.replace(/\/+$/, '') === path; } catch (_) { return false; }
            });
            if (!a || vistos.has(a.dataset.key)) return;
            vistos.add(a.dataset.key);
            itemsRec.push(a);
        });
        if (itemsRec.length) {
            clonarSeccion($('#inicio-recientes'), 'Recientes', itemsRec.slice(0, 4));
            const h4 = $('#inicio-recientes h4');
            if (h4) h4.innerHTML = '<i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> ' + esc(document.documentElement.lang === 'en' ? 'Recent' : 'Recientes');
        }
        aplicarFiltroDlg();
    }
})();
