// ============================================================
// Archivo: explorador de documentos
// - Búsqueda instantánea, vista cuadrícula/lista, selección múltiple
// - Arrastrar archivos a una carpeta, panel de detalles y modales de carpeta
// ============================================================
(() => {
    'use strict';

    const cfg = window.ARCHIVO || {};
    const $ = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
    const norm = (txt) => String(txt ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const lista = $('#arch-lista');
    const cards = lista ? $$('.archivo-card', lista) : [];
    const buscador = $('#arch-buscar');
    const contador = $('#arch-resultados');
    const vacio = $('#arch-sin-resultados');
    const seccionCarpetas = $('.archivo-seccion-carpetas');
    const bulk = $('#archivo-bulk');
    const todos = $('#arch-todos');
    const moverBtn = $('#arch-mover-btn');
    const fab = $('.archivo-fab');
    const checks = () => $$('.arch-sel', lista || document);

    // ---------- Vista cuadrícula / lista ----------
    const claveVista = 'archivos-vista:' + (cfg.proyecto || '0');
    const botonesVista = $$('.archivo-vista [data-vista]');
    const vistaInicial = (lista && lista.dataset.vistaInicial) || cfg.vistaInicial || 'cuadricula';
    const aplicarVista = (vista, guardar) => {
        if (!lista) return;
        const esLista = vista === 'lista';
        lista.classList.toggle('vista-lista', esLista);
        lista.classList.toggle('vista-cuadricula', !esLista);
        botonesVista.forEach((b) => b.setAttribute('aria-pressed', b.dataset.vista === vista ? 'true' : 'false'));
        if (guardar) {
            try { localStorage.setItem(claveVista, vista); } catch (e) { /* almacenamiento no disponible */ }
        }
    };
    let vistaGuardada = null;
    try { vistaGuardada = localStorage.getItem(claveVista); } catch (e) { /* almacenamiento no disponible */ }
    aplicarVista(vistaGuardada === 'lista' || vistaGuardada === 'cuadricula' ? vistaGuardada : vistaInicial, false);
    botonesVista.forEach((b) => b.addEventListener('click', () => aplicarVista(b.dataset.vista, true)));

    // ---------- Selección ----------
    const actualizarSeleccion = () => {
        const seleccionados = checks().filter((c) => c.checked);
        if ($('#arch-n')) $('#arch-n').textContent = String(seleccionados.length);
        if (moverBtn) moverBtn.disabled = seleccionados.length === 0;
        if (bulk) bulk.hidden = seleccionados.length === 0;
        if (fab) fab.hidden = seleccionados.length > 0;
        cards.forEach((card) => {
            const check = $('.arch-sel', card);
            card.classList.toggle('seleccionado', !!(check && check.checked));
        });
        if (todos) {
            const visibles = cards.filter((c) => !c.hidden);
            const marcados = visibles.filter((c) => {
                const check = $('.arch-sel', c);
                return !!(check && check.checked);
            }).length;
            todos.checked = visibles.length > 0 && marcados === visibles.length;
            todos.indeterminate = marcados > 0 && marcados < visibles.length;
        }
    };

    // ---------- Búsqueda ----------
    const filtrar = () => {
        const q = norm(buscador ? buscador.value : '');
        let visibles = 0;
        cards.forEach((card) => {
            const coincide = !q || norm(card.dataset.buscar || '').includes(q);
            card.hidden = !coincide;
            if (coincide) visibles++;
        });
        if (seccionCarpetas) seccionCarpetas.hidden = q !== '';
        if (vacio) vacio.hidden = !q || visibles > 0;
        if (contador) contador.textContent = q ? visibles + ' coincidencia(s)' : visibles + ' archivo(s)';
        actualizarSeleccion();
    };

    if (buscador) {
        buscador.addEventListener('input', filtrar);
        buscador.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                buscador.value = '';
                filtrar();
            }
            if (e.key === 'Enter') e.preventDefault();
        });
    }
    document.addEventListener('keydown', (e) => {
        const activo = document.activeElement;
        const escribiendo = activo && /INPUT|TEXTAREA|SELECT/.test(activo.tagName || '');
        if (e.key === '/' && !escribiendo && buscador && !detalleAbierto()) {
            e.preventDefault();
            buscador.focus();
        }
    });

    checks().forEach((c) => c.addEventListener('change', actualizarSeleccion));
    if (todos) {
        todos.addEventListener('change', () => {
            cards.filter((c) => !c.hidden).forEach((card) => {
                const check = $('.arch-sel', card);
                if (check) check.checked = todos.checked;
            });
            actualizarSeleccion();
        });
    }
    const limpiar = $('#arch-limpiar');
    if (limpiar) {
        limpiar.addEventListener('click', () => {
            checks().forEach((c) => { c.checked = false; });
            actualizarSeleccion();
        });
    }
    actualizarSeleccion();

    // ---------- Mover archivos ----------
    const mover = (ids, destino) => {
        if (!ids.length) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/archivos/mover';
        form.style.display = 'none';
        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };
        add('proyecto', cfg.proyecto || '');
        ids.forEach((id) => add('ids[]', String(id)));
        add('carpeta_id', destino);
        document.body.appendChild(form);
        form.submit();
    };

    // ---------- Arrastrar y soltar ----------
    if (cfg.puede) {
        cards.forEach((card) => {
            card.addEventListener('dragstart', (e) => {
                const check = $('.arch-sel', card);
                const seleccionados = checks().filter((c) => c.checked).map((c) => c.value);
                const ids = check && check.checked && seleccionados.length ? seleccionados : [card.dataset.id];
                e.dataTransfer.setData('text/plain', JSON.stringify(ids));
                e.dataTransfer.effectAllowed = 'move';
                card.classList.add('arrastrando');
            });
            card.addEventListener('dragend', () => card.classList.remove('arrastrando'));
        });
        $$('[data-carpeta-id]').forEach((zona) => {
            zona.addEventListener('dragover', (e) => {
                if (!e.dataTransfer) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                zona.classList.add('drop-activo');
            });
            zona.addEventListener('dragleave', (e) => {
                if (e.relatedTarget && zona.contains(e.relatedTarget)) return;
                zona.classList.remove('drop-activo');
            });
            zona.addEventListener('drop', (e) => {
                e.preventDefault();
                zona.classList.remove('drop-activo');
                let ids = [];
                try { ids = JSON.parse(e.dataTransfer.getData('text/plain') || '[]'); } catch (err) { ids = []; }
                if (!Array.isArray(ids) || !ids.length) return;
                mover(ids, zona.dataset.carpetaId || '');
            });
        });
    }

    // ---------- Panel de detalles ----------
    const detalle = $('#archivo-detalle');
    const fondo = $('#archivo-detalle-fondo');
    const btnCerrarDetalle = $('#archivo-detalle-cerrar');
    let focoDetalle = null;
    const detalleAbierto = () => !!(detalle && detalle.classList.contains('abierto'));

    const setTexto = (sel, texto) => {
        const el = $(sel);
        if (el) el.textContent = texto || '—';
    };
    const setEnlace = (sel, url) => {
        const el = $(sel);
        if (!el) return;
        if (url) {
            el.href = url;
            el.hidden = false;
        } else {
            el.removeAttribute('href');
            el.hidden = true;
        }
    };
    const cerrarDetalle = () => {
        if (!detalle) return;
        detalle.classList.remove('abierto');
        detalle.setAttribute('aria-hidden', 'true');
        if (fondo) {
            fondo.classList.remove('abierto');
            fondo.setAttribute('aria-hidden', 'true');
        }
        $$('.archivo-detalle-btn').forEach((b) => b.setAttribute('aria-expanded', 'false'));
        if (focoDetalle && typeof focoDetalle.focus === 'function') focoDetalle.focus();
    };

    const abrirDetalle = (card) => {
        if (!detalle || !card) return;
        const d = card.dataset;
        focoDetalle = document.activeElement;
        setTexto('#archivo-detalle-nombre', d.nombre);
        setTexto('#archivo-detalle-categoria', d.categoria);
        setTexto('#archivo-detalle-carpeta', d.carpeta || 'Sin carpeta');
        setTexto('#archivo-detalle-revision', d.revision ? 'Rev. ' + d.revision : '—');
        setTexto('#archivo-detalle-fecha', d.fecha);
        setTexto('#archivo-detalle-tamano', d.tamano);
        setTexto('#archivo-detalle-subido', d.subido);
        setTexto('#archivo-detalle-original', d.original);
        const filaEnsamblado = $('#archivo-detalle-ensamblado-fila');
        if (filaEnsamblado) filaEnsamblado.hidden = d.ensamblado !== '1';

        const chips = $('#archivo-detalle-chips');
        if (chips) {
            chips.innerHTML = '';
            const add = (texto, clase) => {
                if (!texto) return;
                const chip = document.createElement('span');
                chip.className = 'archivo-chip' + (clase ? ' ' + clase : '');
                chip.textContent = texto;
                chips.appendChild(chip);
            };
            add(d.categoria, 'archivo-chip-cat');
            add(d.revision ? 'Rev. ' + d.revision : '', 'archivo-chip-rev');
            add((d.ext || '').toUpperCase());
            add(d.tamano);
            chips.hidden = chips.childElementCount === 0;
        }

        const preview = $('#archivo-detalle-preview');
        if (preview) {
            preview.innerHTML = '';
            if (d.preview) {
                const img = document.createElement('img');
                img.src = d.preview;
                img.alt = d.nombre || '';
                img.loading = 'lazy';
                preview.appendChild(img);
            } else {
                const tipo = card.querySelector('.archivo-tipo');
                if (tipo) preview.appendChild(tipo.cloneNode(true));
                else {
                    const icono = document.createElement('i');
                    icono.className = 'fa-regular fa-file';
                    icono.setAttribute('aria-hidden', 'true');
                    preview.appendChild(icono);
                }
            }
        }

        setEnlace('#archivo-detalle-ver', d.ver);
        setEnlace('#archivo-detalle-descargar', d.descargar);
        setEnlace('#archivo-detalle-editar', d.editar);
        const btnEliminar = $('#archivo-detalle-eliminar');
        if (btnEliminar) btnEliminar.hidden = d.eliminar !== '1';

        const idMover = $('#archivo-detalle-id');
        if (idMover) idMover.value = d.id || '';
        const selectCarpeta = $('#archivo-detalle-carpeta');
        if (selectCarpeta) selectCarpeta.value = d.carpetaId && d.carpetaId !== '0' ? d.carpetaId : '';

        const bloqueRevisiones = $('#archivo-detalle-revisiones');
        const listaRevisiones = $('#archivo-detalle-revisiones-lista');
        if (bloqueRevisiones && listaRevisiones) {
            listaRevisiones.innerHTML = '';
            const revisiones = cards.filter((c) => c !== card && c.dataset.docKey && c.dataset.docKey === d.docKey);
            revisiones.sort((a, b) => String(b.dataset.fechaIso || '').localeCompare(String(a.dataset.fechaIso || '')));
            if (revisiones.length) {
                bloqueRevisiones.hidden = false;
                revisiones.forEach((otro) => {
                    const li = document.createElement('li');
                    const enlace = document.createElement('a');
                    const url = otro.dataset.ver || otro.dataset.descargar || '';
                    if (url) enlace.href = url;
                    enlace.textContent = (otro.dataset.revision ? 'Rev. ' + otro.dataset.revision + ' · ' : '') +
                        (otro.dataset.fecha || '') + ' · ' + (otro.dataset.tamano || '');
                    li.appendChild(enlace);
                    listaRevisiones.appendChild(li);
                });
            } else {
                bloqueRevisiones.hidden = true;
            }
        }

        detalle.classList.add('abierto');
        detalle.setAttribute('aria-hidden', 'false');
        if (fondo) {
            fondo.classList.add('abierto');
            fondo.setAttribute('aria-hidden', 'false');
        }
        const boton = card.querySelector('.archivo-detalle-btn');
        if (boton) boton.setAttribute('aria-expanded', 'true');
        if (btnCerrarDetalle) btnCerrarDetalle.focus();
    };

    $$('.archivo-detalle-btn').forEach((boton) => {
        boton.addEventListener('click', () => abrirDetalle(boton.closest('.archivo-card')));
    });
    if (btnCerrarDetalle) btnCerrarDetalle.addEventListener('click', cerrarDetalle);
    if (fondo) fondo.addEventListener('click', cerrarDetalle);
    const btnEliminarDetalle = $('#archivo-detalle-eliminar');
    if (btnEliminarDetalle) {
        btnEliminarDetalle.addEventListener('click', () => {
            const id = ($('#archivo-detalle-id') || {}).value;
            const card = cards.find((c) => c.dataset.id === id);
            const form = card && card.querySelector('form.archivo-eliminar');
            if (!form) return;
            const boton = form.querySelector('button[type="submit"]');
            if (boton) boton.click();
            else form.requestSubmit();
        });
    }

    // ---------- Modales de carpeta ----------
    const modalCarpeta = $('#arch-modal-carpeta');
    const modalBorrar = $('#arch-modal-borrar');
    const abrirModal = (modal) => {
        if (!modal) return;
        modal.classList.add('abierto');
        modal.setAttribute('aria-hidden', 'false');
    };
    const cerrarModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('abierto');
        modal.setAttribute('aria-hidden', 'true');
    };
    [modalCarpeta, modalBorrar].forEach((modal) => {
        if (!modal) return;
        modal.addEventListener('click', (e) => {
            if (e.target === modal || e.target.closest('[data-cerrar]')) cerrarModal(modal);
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (detalleAbierto()) { cerrarDetalle(); return; }
        cerrarModal(modalCarpeta);
        cerrarModal(modalBorrar);
    });

    const abrirCarpeta = (id, nombre) => {
        if (!modalCarpeta) return;
        const campoId = $('#arch-carpeta-id');
        const campoNombre = $('#arch-carpeta-nombre');
        const titulo = $('#arch-carpeta-titulo');
        if (campoId) campoId.value = id || '';
        if (campoNombre) campoNombre.value = nombre || '';
        if (titulo) titulo.textContent = id ? 'Renombrar carpeta' : 'Nueva carpeta';
        abrirModal(modalCarpeta);
        if (campoNombre) campoNombre.focus();
    };
    $$('.arch-nueva').forEach((boton) => boton.addEventListener('click', () => abrirCarpeta('', '')));
    $$('.arch-renombrar').forEach((boton) => {
        boton.addEventListener('click', () => abrirCarpeta(boton.dataset.id || '', boton.dataset.nombre || ''));
    });
    $$('.arch-borrar').forEach((boton) => {
        boton.addEventListener('click', () => {
            if (!modalBorrar) return;
            const total = parseInt(boton.dataset.total || '0', 10) || 0;
            const id = $('#arch-borrar-id');
            const mensaje = $('#arch-borrar-msg');
            const totalEl = $('#arch-borrar-n');
            const opcionTodo = $('#arch-borrar-todo');
            if (id) id.value = boton.dataset.id || '';
            if (mensaje) mensaje.textContent = '¿Qué hacer con la carpeta «' + (boton.dataset.nombre || '') + '»?' + (total ? '' : ' Está vacía.');
            if (totalEl) totalEl.textContent = String(total);
            if (opcionTodo) opcionTodo.style.display = total ? '' : 'none';
            const solo = modalBorrar.querySelector('input[value="solo"]');
            if (solo) solo.checked = true;
            abrirModal(modalBorrar);
        });
    });
})();
