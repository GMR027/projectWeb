/* Programa de obra — interacciones de la vista (sin frameworks) */
(function () {
    'use strict';

    const PRG = window.PRG || {};
    const $ = (sel, ctx) => (ctx || document).querySelector(sel);
    const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));
    const hoyISO = () => {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };
    const sumarDias = (fecha, dias) => {
        if (!fecha) return '';
        const d = new Date(fecha + 'T12:00:00');
        d.setDate(d.getDate() + dias);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };
    const diferenciaDias = (a, b) => {
        if (!a || !b) return 0;
        return Math.round((new Date(b + 'T12:00:00') - new Date(a + 'T12:00:00')) / 86400000) + 1;
    };
    const guardarFlash = (texto) => {
        try { sessionStorage.setItem('prg-toast', texto); } catch (e) {}
    };
    const leerFlash = () => {
        try {
            const t = sessionStorage.getItem('prg-toast');
            if (t) sessionStorage.removeItem('prg-toast');
            return t;
        } catch (e) { return ''; }
    };
    const toast = (texto, tono) => {
        if (!texto) return;
        const el = document.createElement('div');
        el.className = 'prg-toast' + (tono === 'error' ? ' prg-toast-error' : '');
        el.setAttribute('role', 'status');
        el.innerHTML = '<i class="fa-solid ' + (tono === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check') + '"></i><span></span>';
        el.querySelector('span').textContent = texto;
        document.body.appendChild(el);
        requestAnimationFrame(() => el.classList.add('visible'));
        setTimeout(() => {
            el.classList.remove('visible');
            setTimeout(() => el.remove(), 250);
        }, 2800);
    };
    window.prgToast = toast;

    // ============ FOCO EN LA FILA TRAS GUARDAR ============
    (function () {
        const params = new URLSearchParams(location.search);
        const m = /^([pa]s?\d+|s\d+)$/.exec(params.get('foco') || '');
        if (!m) return;
        const fila = document.getElementById('fila-' + m[1]);
        if (fila) {
            fila.scrollIntoView({ block: 'center', behavior: 'smooth' });
            fila.classList.add('fila-foco');
        }
        params.delete('foco');
        const url = new URL(location.href);
        url.search = params.toString();
        history.replaceState(null, '', url);
    })();

    // ============ COLAPSAR PARTIDAS ============
    (function () {
        const tabla = document.getElementById('prg-tabla');
        if (!tabla) return;
        const clave = 'po_prg_colapsadas_' + (PRG.proyecto || '');
        let colapsadas = new Set();
        try {
            const raw = localStorage.getItem(clave);
            if (raw) colapsadas = new Set(JSON.parse(raw).map(String));
        } catch (e) {}
        const persistir = () => {
            try { localStorage.setItem(clave, JSON.stringify(Array.from(colapsadas))); } catch (e) {}
        };
        const filasDePartida = (tr) => {
            const out = [];
            let n = tr.nextElementSibling;
            while (n && !n.classList.contains('prg-partida')) {
                if (n.classList.contains('prg-actividad') || n.classList.contains('fila-rapida')) out.push(n);
                n = n.nextElementSibling;
            }
            return out;
        };
        const aplicar = () => {
            $$('.prg-partida', tabla).forEach((tr) => {
                const id = String(tr.dataset.partidaId || '');
                const cerrada = colapsadas.has(id);
                tr.classList.toggle('prg-colapsada', cerrada);
                const btn = $('.prg-partida-toggle', tr);
                if (btn) {
                    btn.setAttribute('aria-expanded', cerrada ? 'false' : 'true');
                    const icono = $('i', btn);
                    if (icono) icono.className = 'fa-solid ' + (cerrada ? 'fa-chevron-right' : 'fa-chevron-down');
                }
                filasDePartida(tr).forEach((fila) => { fila.hidden = cerrada; });
            });
            const btnTodo = document.getElementById('prg-colapsar');
            if (btnTodo) {
                const ids = $$('.prg-partida', tabla).map((tr) => String(tr.dataset.partidaId || '')).filter(Boolean);
                const todas = ids.length > 0 && ids.every((id) => colapsadas.has(id));
                btnTodo.innerHTML = '<i class="fa-solid ' + (todas ? 'fa-angles-down' : 'fa-angles-up') + '"></i> ' + (todas ? 'Expandir' : 'Contraer');
                btnTodo.setAttribute('aria-pressed', todas ? 'true' : 'false');
            }
        };
        tabla.addEventListener('click', (e) => {
            const btn = e.target.closest('.prg-partida-toggle');
            if (!btn) return;
            const id = String(btn.dataset.partidaToggle || '');
            if (!id) return;
            if (colapsadas.has(id)) colapsadas.delete(id); else colapsadas.add(id);
            persistir();
            aplicar();
        });
        const btnTodo = document.getElementById('prg-colapsar');
        if (btnTodo) {
            btnTodo.addEventListener('click', () => {
                const ids = $$('.prg-partida', tabla).map((tr) => String(tr.dataset.partidaId || '')).filter(Boolean);
                const total = ids.length;
                if (total && total === colapsadas.size) colapsadas.clear();
                else ids.forEach((id) => colapsadas.add(id));
                persistir();
                aplicar();
            });
        }
        aplicar();
    })();

    // ============ MODAL DE ACTIVIDAD / PARTIDA / SUBACTIVIDAD ============
    (function () {
        const modal = document.getElementById('prg-modal-actividad');
        const frame = document.getElementById('prg-modal-frame');
        if (!modal || !frame) return;
        const titulo = document.getElementById('prg-modal-titulo');
        const cargando = document.getElementById('prg-modal-cargando');
        const completo = document.getElementById('prg-modal-completo');
        let focoPrevio = null;
        let ultimoUrl = '';

        const urlCompleta = (url) => {
            try {
                const u = new URL(url, location.origin);
                u.searchParams.delete('modal');
                return u.pathname + (u.search || '') + (u.hash || '');
            } catch (e) {
                return String(url || '').replace(/([?&])modal=1&?/, '$1').replace(/[?&]$/, '');
            }
        };

        const cerrar = () => {
            modal.classList.remove('abierto');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('prg-modal-abierto');
            frame.src = 'about:blank';
            ultimoUrl = '';
            if (focoPrevio && document.contains(focoPrevio)) focoPrevio.focus();
            focoPrevio = null;
        };
        const abrir = (url, texto) => {
            if (!url) return;
            ultimoUrl = url;
            if (titulo) titulo.textContent = texto || 'Actividad';
            if (completo) completo.href = urlCompleta(url);
            if (cargando) cargando.hidden = false;
            frame.hidden = true;
            frame.src = url;
            modal.classList.add('abierto');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('prg-modal-abierto');
            focoPrevio = document.activeElement;
            setTimeout(() => { const x = document.getElementById('prg-modal-cerrar'); if (x) x.focus(); }, 60);
        };
        window.prgAbrirModal = abrir;

        frame.addEventListener('load', () => {
            if (frame.src === 'about:blank') return;
            if (cargando) cargando.hidden = true;
            frame.hidden = false;
        });

        // Enlaces con data-modal-url (alta/edición de actividad, partida o subactividad)
        document.addEventListener('click', (e) => {
            const a = e.target.closest('[data-modal-url]');
            if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
            e.preventDefault();
            abrir(a.dataset.modalUrl, a.dataset.modalTitulo);
        });

        // Barras del Gantt clicables
        const grid = document.getElementById('prg-gantt-grid');
        if (grid) {
            const activar = (ev) => {
                const b = ev.target.closest('.prg-gantt-barra[data-editar-url]');
                if (!b) return;
                ev.preventDefault();
                abrir(b.dataset.editarUrl, b.dataset.modalTitulo);
            };
            grid.addEventListener('click', activar);
            grid.addEventListener('keydown', (ev) => {
                if (ev.key !== 'Enter' && ev.key !== ' ') return;
                const b = ev.target.closest('.prg-gantt-barra[data-editar-url]');
                if (!b) return;
                ev.preventDefault();
                abrir(b.dataset.editarUrl, b.dataset.modalTitulo);
            });
        }

        const cerrarBtn = document.getElementById('prg-modal-cerrar');
        if (cerrarBtn) cerrarBtn.addEventListener('click', cerrar);
        modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('abierto')) { e.preventDefault(); cerrar(); }
            if (e.key === 'Tab' && modal.classList.contains('abierto')) {
                const focos = $$('button, [href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"])', modal)
                    .filter((el) => !el.disabled && el.offsetParent !== null);
                if (!focos.length) return;
                const primero = focos[0], ultimo = focos[focos.length - 1];
                if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
                else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
            }
        });

        // El formulario del iframe avisa al guardar. Si trae destino, se navega a la fila focada.
        window.addEventListener('message', (e) => {
            if (e.origin !== location.origin || e.source !== frame.contentWindow || !e.data) return;
            if (e.data.tipo === 'cerrar-modal') { cerrar(); return; }
            if (!String(e.data.tipo || '').endsWith('guardada')) return;
            const destino = typeof e.data.destino === 'string' ? e.data.destino : '';
            if (destino) location.href = destino;
            else location.reload();
        });
    })();

    // ============ MODAL DE FECHAS ============
    (function () {
        const modal = document.getElementById('modal-fechas');
        if (!modal) return;
        const ini = document.getElementById('mf-inicio');
        const fin = document.getElementById('mf-fin');
        const err = document.getElementById('mf-error');
        const actividad = document.getElementById('mf-actividad');
        const duracion = document.getElementById('mf-duracion');
        const nota = document.getElementById('mf-nota');
        const btnGuardar = document.getElementById('mf-guardar');
        if (!ini || !fin) return;
        let actual = null;
        let focoPrevio = null;

        const abrir = (btn) => {
            actual = btn.dataset;
            if (actividad) actividad.textContent = (btn.dataset.codigo ? btn.dataset.codigo + ' · ' : '') + (btn.dataset.nombre || '');
            ini.value = btn.dataset.inicio || '';
            fin.value = btn.dataset.fin || '';
            if (err) { err.hidden = true; err.textContent = ''; }
            const bloqueado = btn.dataset.bloqueaFin === '1';
            fin.disabled = bloqueado;
            if (nota) {
                nota.hidden = !bloqueado;
                nota.textContent = bloqueado ? 'El fin se calcula de sus subactividades.' : '';
            }
            pintar();
            focoPrevio = document.activeElement;
            modal.classList.add('abierto');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('prg-modal-abierto');
            setTimeout(() => ini.focus(), 80);
        };
        const cerrar = () => {
            modal.classList.remove('abierto');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('prg-modal-abierto');
            if (focoPrevio && document.contains(focoPrevio)) focoPrevio.focus();
            focoPrevio = null;
        };
        let seqDuracion = 0;
        const pintar = () => {
            if (!duracion) return;
            const d = diferenciaDias(ini.value, fin.value);
            duracion.innerHTML = '<i class="fa-solid fa-clock" aria-hidden="true"></i> ' + (d > 0 ? d + ' día' + (d === 1 ? '' : 's') + ' calendario' : '—');
            if (err && !err.hidden && d > 0) { err.hidden = true; }
            if (d <= 0) return;
            const seq = ++seqDuracion;
            const qs = new URLSearchParams({ proyecto: PRG.proyecto, inicio: ini.value, fin: fin.value });
            fetch('/api/calendario/duracion?' + qs.toString(), { credentials: 'same-origin' })
                .then((r) => r.json())
                .then((j) => {
                    if (seq !== seqDuracion || !j || !j.success) return;
                    const n = Number(j.dias);
                    const texto = (Math.round(n * 10) / 10).toLocaleString('es-MX');
                    duracion.innerHTML = '<i class="fa-solid fa-clock" aria-hidden="true"></i> ' + texto + ' día' + (n === 1 ? '' : 's') + ' hábil' + (n === 1 ? '' : 'es');
                })
                .catch(() => {});
        };

        $$('.btn-fechas').forEach((b) => b.addEventListener('click', () => abrir(b)));
        document.getElementById('mf-cancelar')?.addEventListener('click', cerrar);
        document.getElementById('mf-cerrar')?.addEventListener('click', cerrar);
        modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('abierto')) { e.preventDefault(); cerrar(); }
        });
        ini.addEventListener('change', () => {
            if (!fin.disabled && ini.value) {
                const d = Math.max(1, diferenciaDias(actual?.inicio || ini.value, actual?.fin || fin.value));
                fin.value = sumarDias(ini.value, d - 1);
            }
            pintar();
        });
        fin.addEventListener('change', pintar);

        $$('[data-fecha-rapida]', modal).forEach((b) => b.addEventListener('click', () => {
            const modo = b.dataset.fechaRapida;
            const dur = Math.max(1, diferenciaDias(ini.value, fin.value) || 1);
            if (modo === 'hoy') {
                ini.value = hoyISO();
                if (!fin.disabled) fin.value = sumarDias(ini.value, dur - 1);
            } else {
                const dias = modo === 'semana' ? 7 : 1;
                ini.value = sumarDias(ini.value || hoyISO(), dias);
                if (!fin.disabled) fin.value = sumarDias(fin.value || ini.value, dias);
            }
            pintar();
        }));

        const enviar = async (tipo, fecha) => {
            const fd = new FormData();
            fd.append('actividad_id', actual.id);
            fd.append('tipo', tipo);
            fd.append('fecha', fecha);
            const r = await fetch('/api/actividad/actualizar-fecha', { method: 'POST', body: fd });
            return r.json();
        };
        const textoGuardar = btnGuardar ? btnGuardar.innerHTML : '';
        if (btnGuardar) btnGuardar.addEventListener('click', async () => {
            if (!actual) return;
            if (err) { err.hidden = true; err.textContent = ''; }
            if (!ini.value || !fin.value || fin.value < ini.value) {
                if (err) { err.textContent = 'La fecha de fin no puede ser anterior a la de inicio.'; err.hidden = false; }
                return;
            }
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';
            try {
                if (ini.value !== actual.inicio) {
                    const d = await enviar('inicio', ini.value);
                    if (!d.success) throw new Error(d.error || 'No se pudo guardar');
                }
                if (!fin.disabled && fin.value !== actual.fin) {
                    const d2 = await enviar('fin', fin.value);
                    if (!d2.success) throw new Error(d2.error || 'No se pudo guardar');
                }
                guardarFlash('Fechas actualizadas');
                location.reload();
            } catch (e) {
                if (err) { err.textContent = e.message || 'No se pudo guardar'; err.hidden = false; }
            } finally {
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = textoGuardar;
            }
        });
    })();

    // ============ SELECCIÓN MÚLTIPLE ============
    (function () {
        const form = document.getElementById('form-seleccion');
        if (!form) return;
        const filas = $$('.sel-fila');
        const todo = document.getElementById('sel-todo');
        const cuenta = document.getElementById('sel-n');
        const campos = document.getElementById('sel-campos');
        const marcadas = () => filas.filter((c) => c.checked);
        const refrescar = () => {
            const m = marcadas();
            if (cuenta) cuenta.textContent = m.length;
            form.hidden = m.length === 0;
            if (todo) {
                todo.checked = m.length > 0 && m.length === filas.length;
                todo.indeterminate = m.length > 0 && m.length < filas.length;
            }
            filas.forEach((c) => { const tr = c.closest('tr'); if (tr) tr.classList.toggle('fila-seleccionada', c.checked); });
        };
        filas.forEach((c) => c.addEventListener('change', refrescar));
        todo?.addEventListener('change', () => { filas.forEach((c) => { c.checked = todo.checked; }); refrescar(); });
        document.getElementById('sel-limpiar')?.addEventListener('click', () => { filas.forEach((c) => { c.checked = false; }); refrescar(); });
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const m = marcadas();
            const np = m.filter((c) => c.value[0] === 'p').length;
            const na = m.length - np;
            const partes = [];
            if (np) partes.push(np + (np === 1 ? ' partida (con todas sus actividades)' : ' partidas (con todas sus actividades)'));
            if (na) partes.push(na + (na === 1 ? ' actividad' : ' actividades'));
            const ok = await window.confirmarModal({ titulo: '¿Eliminar selección?', mensaje: 'Se eliminarán ' + partes.join(' y ') + ' y sus restricciones asociadas. Esta acción no se puede deshacer.' });
            if (!ok) return;
            if (campos) {
                campos.innerHTML = '';
                m.forEach((c) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'sel[]'; i.value = c.value; campos.appendChild(i); });
            }
            form.submit();
        });
        refrescar();
    })();

    // ============ RENOMBRAR Y REORDENAR COLUMNAS ============
    (function () {
        const tabla = document.querySelector('.tabla-programa');
        if (!tabla) return;
        const proyecto = tabla.dataset.proyecto;
        const dlg = document.getElementById('prg-dlg-columna');
        const nom = document.getElementById('prg-col-nombre');
        const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(async (r) => ({ ok: r.ok, d: await r.json().catch(() => ({})) }));
        if (!dlg || !nom) return;
        let colActual = null;
        tabla.querySelectorAll('thead th[data-col]').forEach((th) => th.addEventListener('dblclick', () => {
            colActual = th.dataset.col;
            nom.value = th.textContent.replace(/\s+/g, ' ').trim();
            dlg.showModal();
            nom.select();
        }));
        document.getElementById('prg-col-cerrar')?.addEventListener('click', () => dlg.close());
        document.getElementById('prg-col-cancelar')?.addEventListener('click', () => dlg.close());
        document.getElementById('prg-form-columna')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const { ok, d } = await post('/api/programa/columna', { proyecto, k: colActual, n: nom.value });
            dlg.close();
            if (ok && d.success) { guardarFlash('Columna renombrada'); location.reload(); }
            else toast(d.error || 'No se pudo renombrar la columna', 'error');
        });

        let arrastrada = null;
        tabla.querySelectorAll('thead th[data-col][draggable]').forEach((th) => {
            th.addEventListener('dragstart', (e) => { arrastrada = th; e.dataTransfer.effectAllowed = 'move'; th.classList.add('prg-arrastrando'); });
            th.addEventListener('dragend', () => { th.classList.remove('prg-arrastrando'); tabla.querySelectorAll('th.prg-destino').forEach((x) => x.classList.remove('prg-destino')); });
            th.addEventListener('dragover', (e) => { if (arrastrada && arrastrada !== th) { e.preventDefault(); th.classList.add('prg-destino'); } });
            th.addEventListener('dragleave', () => th.classList.remove('prg-destino'));
            th.addEventListener('drop', async (e) => {
                e.preventDefault();
                if (!arrastrada || arrastrada === th) return;
                const orden = [...tabla.querySelectorAll('thead th[data-col]')].map((x) => x.dataset.col).filter((k) => k !== arrastrada.dataset.col);
                orden.splice(orden.indexOf(th.dataset.col), 0, arrastrada.dataset.col);
                const { ok } = await post('/api/programa/orden', { proyecto, orden: orden.join(',') });
                if (ok) { guardarFlash('Orden de columnas guardado'); location.reload(); }
                else toast('No se pudo guardar el orden', 'error');
            });
        });
    })();

    // ============ CAMPOS NUEVOS Y DEPENDENCIAS EN LÍNEA ============
    (function () {
        const tabla = document.querySelector('.tabla-programa');
        if (!tabla) return;
        const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(async (r) => ({ ok: r.ok, d: await r.json().catch(() => ({})) }));
        const marcar = (el, ok, msg) => { el.classList.toggle('msr-error', !ok); el.title = ok ? '' : (msg || 'No se pudo guardar'); };
        tabla.addEventListener('change', async (e) => {
            const inp = e.target.closest('.prg-campo');
            if (inp) {
                const { ok, d } = await post('/api/programa/campo', { actividad_id: inp.dataset.actividadId, campo: inp.dataset.campo, valor: inp.value });
                marcar(inp, ok && d.success, d.error);
                if (ok && d.success) {
                    if (inp.dataset.campo === 'libre_numero') { guardarFlash('Campo actualizado'); location.reload(); }
                    else toast('Campo actualizado');
                } else toast(d.error || 'No se pudo guardar el campo', 'error');
                return;
            }
            const sel = e.target.closest('.prg-dep');
            if (sel) {
                const { ok, d } = await post('/api/programa/dependencia', { actividad_id: sel.dataset.actividadId, lado: sel.dataset.lado, valor: sel.value });
                marcar(sel, ok && d.success, d.error);
                if (ok && d.success) { guardarFlash('Dependencia guardada'); location.reload(); }
                else { if (d.error) await window.avisoModal('No se pudo guardar la dependencia', d.error); location.reload(); }
            }
        });
    })();

    // ============ GANTT: FLECHAS, HOY Y LÍNEA DE TIEMPO ============
    (function () {
        const grid = document.getElementById('prg-gantt-grid');
        if (!grid) return;
        const scroll = document.getElementById('prg-gantt-scroll');
        const chk = document.getElementById('gantt-dep-toggle');
        const rels = Array.isArray(PRG.depFlechas) ? PRG.depFlechas : [];
        const NS = 'http://www.w3.org/2000/svg';
        let svg = null;

        const caja = (id) => {
            const el = grid.querySelector('.gantt-barra[data-nodo="' + id + '"]');
            if (!el) return null;
            const g = grid.getBoundingClientRect();
            const r = el.getBoundingClientRect();
            return { l: r.left - g.left, r: r.right - g.left, y: r.top - g.top + r.height / 2 };
        };
        const dibujar = () => {
            if (!rels.length) return;
            if (!svg) { svg = document.createElementNS(NS, 'svg'); svg.setAttribute('class', 'gantt-deps prg-gantt-deps'); grid.appendChild(svg); }
            svg.innerHTML = '';
            if (chk && !chk.checked) return;
            svg.setAttribute('width', grid.scrollWidth);
            svg.setAttribute('height', grid.scrollHeight);
            let out = '';
            rels.forEach((r) => {
                const A = caja(r.pred), B = caja(r.suc);
                if (!A || !B) return;
                const salidaFin = r.tipo === 'FS' || r.tipo === 'FF';
                const entraInicio = r.tipo === 'FS' || r.tipo === 'SS';
                const x1 = salidaFin ? A.r : A.l;
                const x2 = entraInicio ? B.l : B.r;
                const d1 = salidaFin ? 1 : -1;
                const d2 = entraInicio ? 1 : -1;
                const ym = A.y < B.y ? A.y + (B.y - A.y) / 2 : A.y - (A.y - B.y) / 2;
                const pts = [[x1, A.y], [x1 + d1 * 7, A.y], [x1 + d1 * 7, ym], [x2 - d2 * 7, ym], [x2 - d2 * 7, B.y], [x2, B.y]];
                const cls = (r.critica ? ' crit' : '') + (r.conflicto ? ' conf' : '');
                out += '<path class="gantt-dep' + cls + '" d="M' + pts.map((p) => p.join(',')).join(' L') + '"/><path class="gantt-dep-punta' + cls + '" d="M' + x2 + ',' + B.y + ' l' + (-d2 * 6) + ',-3.5 l0,7 z"/>';
            });
            svg.innerHTML = out;
        };

        const dibujarHoy = () => {
            const col = Number(PRG.hoyCol || 0);
            if (!col) return;
            const celda = grid.querySelector('.prg-semana-cab.semana-hoy');
            if (!celda) return;
            let linea = grid.querySelector('.prg-gantt-hoy-linea');
            if (!linea) { linea = document.createElement('div'); linea.className = 'prg-gantt-hoy-linea'; grid.appendChild(linea); }
            linea.style.left = (celda.offsetLeft + celda.offsetWidth / 2) + 'px';
            linea.style.height = grid.scrollHeight + 'px';
        };

        const refrescar = () => { dibujar(); dibujarHoy(); };
        if (chk) chk.addEventListener('change', dibujar);
        window.addEventListener('resize', refrescar);
        if (window.ResizeObserver) new ResizeObserver(refrescar).observe(grid);
        requestAnimationFrame(refrescar);
        window.addEventListener('load', refrescar);

        const btnHoy = document.getElementById('prg-gantt-hoy');
        if (btnHoy && scroll) {
            btnHoy.addEventListener('click', () => {
                const celda = grid.querySelector('.prg-semana-cab.semana-hoy');
                if (!celda) return;
                scroll.scrollTo({ left: Math.max(0, celda.offsetLeft - scroll.clientWidth / 2 + celda.offsetWidth / 2), behavior: 'smooth' });
            });
        }
    })();

    // ============ MENSAJES DE GUARDADO ============
    const flash = leerFlash();
    if (flash) setTimeout(() => toast(flash), 180);

    window.addEventListener('resize', () => {
        const barra = document.querySelector('.prg-filtros-detalles[open]');
        if (barra && window.innerWidth <= 768) barra.removeAttribute('open');
    }, { passive: true });
})();
