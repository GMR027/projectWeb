/* Editor en línea y acciones de los hallazgos de IA (Inconsistencias y Análisis de materiales).
   Sin Gulp: se carga solo en esas dos vistas. La CSP ya permite /vendor.
   El editor se pide al abrir el aviso (GET /<herramienta>/editor?id=…) para no mandar 12 MB de opciones en la lista. */
(function () {
    'use strict';
    var cfg = window.HALLAZGOS || {};
    var RIESGO = ['ajustar_fechas', 'ajustar_progreso', 'ajustar_duracion', 'agregar_dependencia', 'quitar_dependencia', 'recalcular_partida', 'sincronizar_instalacion', 'sincronizar_msr_material'];
    var rechazo = { id: 0 };

    function tarjeta(id) { return document.querySelector('.inc-item[data-id="' + id + '"]'); }
    function formActivo() { return document.querySelector('#inc-editor-host .inc-form'); }
    function visible(form) { return form.querySelector('.ed-panel:not([hidden])'); }

    var CAMPOS = ['inicio', 'fin', 'progreso', 'status', 'dur', 'nombre', 'descripcion', 'sin_material',
        'nombre_base', 'marca', 'modelo', 'valor_extra', 'especialidad', 'cantidad', 'unidad', 'referencia', 'compra',
        'fecha_compra_prog', 'fecha_compra_real', 'fecha_entrega_prog', 'fecha_entrega_real', 'link_web'];

    function contexto(form, sel) {
        if (!sel || !sel.options.length) return;
        var opt = sel.options[sel.selectedIndex];
        var d = {};
        try { d = JSON.parse(opt.getAttribute('data-json') || '{}'); } catch (e) { d = {}; }
        var panel = sel.closest('.ed-panel');
        if (!panel) return;
        CAMPOS.forEach(function (k) {
            var el = panel.querySelector('[name="' + k + '"]');
            if (!el) return;
            if (Object.prototype.hasOwnProperty.call(d, k) && d[k] !== null && d[k] !== undefined) el.value = d[k];
            else el.value = '';
        });
        avisarRestricciones(form, d);
    }

    function avisarRestricciones(form, d) {
        var aviso = form.querySelector('.inc-bloqueo');
        if (!aviso) return;
        var n = (d.restricciones || []).length;
        aviso.innerHTML = n
            ? '<div class="alerta alerta-error text-sm"><i class="fa-solid fa-lock"></i> Esta actividad tiene ' + n + ' restricción(es) activa(s): no se puede cambiar el avance hasta resolverlas. Usa los botones «Resolver» de abajo.</div>'
            : '';
    }

    function refrescar(form) {
        var sel = form.querySelector('.ed-accion');
        if (!sel) return;
        var accion = sel.value;
        form.querySelectorAll('.ed-panel').forEach(function (p) {
            var on = p.getAttribute('data-panel') === accion;
            p.hidden = !on;
            // Los campos de un panel oculto no deben bloquear la validación del formulario
            p.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = !on; });
        });
        var panel = visible(form);
        if (!panel) return;
        var act = panel.querySelector('.ed-act');
        if (act) contexto(form, act);
        var mat = panel.querySelector('.ed-mat');
        if (mat) contexto(form, mat);
        if (act) {
            var opt = act.options[act.selectedIndex];
            try { avisarRestricciones(form, JSON.parse(opt.getAttribute('data-json') || '{}')); } catch (e) { }
        }
    }

    function paramsDe(form) {
        var panel = visible(form);
        var params = {};
        if (!panel) return params;
        panel.querySelectorAll('[name]').forEach(function (el) {
            if (!el.name) return;
            params[el.name] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
        });
        return params;
    }

    function contar(j) {
        if (!j.conteos) return;
        var mapa = { pendiente: j.conteos.accionables, observaciones: j.conteos.observaciones, vencidas: j.conteos.vencidas,
            aceptada: j.conteos.aceptada, rechazada: j.conteos.rechazada };
        Object.keys(mapa).forEach(function (k) {
            var n = document.getElementById('inc-n-' + k);
            if (n && mapa[k] !== undefined) n.textContent = mapa[k];
        });
        var c = document.getElementById('inc-n-criticas');
        if (c) c.textContent = j.conteos.criticas;
    }

    function enviar(url, datos) {
        var d = new FormData();
        Object.keys(datos).forEach(function (k) {
            var v = datos[k];
            if (v && typeof v === 'object' && !(v instanceof File)) {
                Object.keys(v).forEach(function (k2) { d.append(k + '[' + k2 + ']', v[k2]); });
            } else d.append(k, v);
        });
        return fetch(url, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    function pedirConfirmacion(titulo, mensaje) {
        if (typeof window.confirmarModal !== 'function') return Promise.resolve(window.confirm(titulo + '\n\n' + mensaje));
        return window.confirmarModal({ titulo: titulo, mensaje: mensaje, aceptar: 'Aplicar', tono: 'info' });
    }

    function cerrarEditor() {
        var d = document.getElementById('inc-editor-dialog'), h = document.getElementById('inc-editor-host');
        if (h) h.innerHTML = '';
        if (d && d.open) d.close();
    }

    function abrirEditor(id, url) {
        var d = document.getElementById('inc-editor-dialog'), h = document.getElementById('inc-editor-host');
        if (!d || !h) return;
        h.innerHTML = '<p class="text-muted">Cargando el editor…</p>';
        if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
        fetch(url + '?id=' + encodeURIComponent(id) + '&proyecto=' + encodeURIComponent(cfg.proyecto), { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error('No se pudo abrir el editor'); return r.text(); })
            .then(function (html) {
                h.innerHTML = html;
                var det = h.querySelector('.inc-editor');
                if (det) det.open = true;
                h.querySelectorAll('.inc-form').forEach(refrescar);
            })
            .catch(function (e) { h.innerHTML = '<div class="alerta alerta-error">' + e.message + '</div>'; });
    }

    function quitarTarjeta(id) {
        var li = tarjeta(id);
        if (li) { li.classList.add('hecha'); setTimeout(function () { li.remove(); }, 1000); }
    }

    document.addEventListener('change', function (e) {
        var form = e.target.closest('.inc-form');
        if (!form) return;
        if (e.target.classList.contains('ed-accion')) refrescar(form);
        if (e.target.classList.contains('ed-act') || e.target.classList.contains('ed-mat')) contexto(form, e.target);
        if (e.target.classList.contains('ed-act')) {
            var panel = e.target.closest('.ed-panel');
            if (panel && (panel.getAttribute('data-panel') === 'ajustar_progreso' || panel.getAttribute('data-panel') === 'fijar_status')) {
                try { avisarRestricciones(form, JSON.parse(e.target.options[e.target.selectedIndex].getAttribute('data-json') || '{}')); } catch (err) { }
            }
        }
    });

    document.addEventListener('click', function (e) {
        var abrir = e.target.closest('.inc-abrir-editor');
        if (abrir) { abrirEditor(abrir.dataset.id, abrir.dataset.url); return; }

        var cerrar = e.target.closest('[data-cerrar-editor]');
        if (cerrar) { cerrarEditor(); return; }

        var calc = e.target.closest('.ed-calcular');
        if (calc) {
            var form = calc.closest('.inc-form');
            var act = form.querySelector('.ed-panel[data-panel="ajustar_fechas"] .ed-act');
            var inicio = form.querySelector('.ed-panel[data-panel="ajustar_fechas"] [name="inicio"]');
            var fin = form.querySelector('.ed-panel[data-panel="ajustar_fechas"] [name="fin"]');
            var msg = form.querySelector('.ed-msg');
            if (!inicio.value) { inicio.focus(); return; }
            calc.disabled = true;
            enviar('/inconsistencias/calcular-fin', { proyecto: cfg.proyecto, actividad_id: act.value, inicio: inicio.value })
                .then(function (j) { if (j.success) { fin.value = j.fin; msg.textContent = 'Fin calculado con el calendario: ' + j.fin; } else msg.textContent = j.error || 'No se pudo calcular'; })
                .catch(function () { msg.textContent = 'Error de conexión'; })
                .finally(function () { calc.disabled = false; });
            return;
        }

        var imp = e.target.closest('.ed-impacto');
        if (imp) {
            if (!cfg.impacto) { imp.hidden = true; return; }
            var f = imp.closest('.inc-form');
            var a = f.querySelector('.ed-panel[data-panel="ajustar_fechas"] .ed-act');
            var i = f.querySelector('.ed-panel[data-panel="ajustar_fechas"] [name="inicio"]');
            var fin2 = f.querySelector('.ed-panel[data-panel="ajustar_fechas"] [name="fin"]');
            var m = f.querySelector('.ed-impacto-msg');
            if (!i.value || !fin2.value) { m.textContent = 'Escribe el inicio y el fin para simular el impacto.'; return; }
            imp.disabled = true; m.textContent = 'Simulando…';
            enviar(cfg.impacto, { proyecto: cfg.proyecto, actividad_id: a.value, inicio: i.value, fin: fin2.value })
                .then(function (j) { m.textContent = j.mensaje || j.error || ''; })
                .catch(function () { m.textContent = 'Error de conexión'; })
                .finally(function () { imp.disabled = false; });
            return;
        }

        var res = e.target.closest('[data-resolver]');
        if (res) {
            var formR = res.closest('.inc-form');
            var msgR = formR.querySelector('.ed-msg-restriccion');
            res.disabled = true;
            enviar('/inconsistencias/corregir', { proyecto: cfg.proyecto, id: formR.getAttribute('data-id'), accion: 'resolver_restriccion', params: { restriccion_id: res.dataset.resolver }, corrida: cfg.corrida })
                .then(function (j) {
                    if (msgR) msgR.textContent = j.mensaje || '';
                    if (j.success) { contar(j); res.classList.add('hecha'); setTimeout(function () { location.reload(); }, 1000); }
                    else res.disabled = false;
                })
                .catch(function () { if (msgR) msgR.textContent = 'Error de conexión'; res.disabled = false; });
            return;
        }

        var grupo = e.target.closest('.inc-grupo-aplicar');
        if (grupo) {
            var ids = [];
            try { ids = JSON.parse(grupo.getAttribute('data-ids') || '[]'); } catch (err) { ids = []; }
            if (!ids.length) return;
            var cajaG = grupo.closest('.inc-grupo');
            var msgG = cajaG.querySelector('.inc-grupo-msg');
            pedirConfirmacion('¿Aplicar ' + ids.length + ' correcciones seguras de este problema?', 'Se aplican una por una; cada una guarda su valor anterior. Las que mueven fechas, avance o relaciones no entran por lote: se revisan una por una.').then(function (ok) {
                if (!ok) return;
                grupo.disabled = true;
                enviar(cfg.grupo, { proyecto: cfg.proyecto, ids: ids, corrida: cfg.corrida }).then(function (j) {
                    if (msgG) msgG.textContent = j.mensaje || (j.ok + ' aplicadas');
                    contar(j);
                    setTimeout(function () { location.reload(); }, 1200);
                }).catch(function () { if (msgG) msgG.textContent = 'Error de conexión'; grupo.disabled = false; });
            });
            return;
        }

        var todas = e.target.closest('#inc-todas');
        if (todas) {
            var items = Array.prototype.filter.call(document.querySelectorAll('.inc-item.est-pendiente'), function (li) {
                var tag = li.querySelector('.inc-tag');
                var accion = li.getAttribute('data-accion-tipo') || '';
                return tag && !tag.textContent.startsWith('Sin cambio') && RIESGO.indexOf(accion) === -1;
            });
            if (!items.length) { document.getElementById('inc-todas-msg').textContent = 'No hay propuestas seguras para aplicar en lote.'; return; }
            pedirConfirmacion('¿Aplicar ' + items.length + ' correcciones seguras?', 'Las que mueven el programa se quedan fuera del lote para revisar su impacto.').then(function (ok) {
                if (!ok) return;
                todas.disabled = true;
                enviar(cfg.grupo, { proyecto: cfg.proyecto, ids: items.map(function (li) { return li.dataset.id; }), corrida: cfg.corrida }).then(function (j) {
                    document.getElementById('inc-todas-msg').textContent = j.mensaje || (j.ok + ' aplicadas');
                    contar(j);
                    setTimeout(function () { location.reload(); }, 1200);
                }).catch(function () { document.getElementById('inc-todas-msg').textContent = 'Error de conexión'; todas.disabled = false; });
            });
            return;
        }

        var accionBtn = e.target.closest('button[data-accion]');
        if (accionBtn) {
            var li = accionBtn.closest('.inc-item');
            if (!li) return;
            var accion = accionBtn.dataset.accion;
            var msg = li.querySelector('.inc-msg');
            if (accion === 'rechazar') {
                rechazo.id = li.dataset.id;
                var dl = document.getElementById('inc-rechazo-dialog');
                var nota = document.getElementById('inc-rechazo-nota');
                if (nota) nota.value = '';
                if (dl && typeof dl.showModal === 'function') dl.showModal(); else if (dl) dl.setAttribute('open', '');
                return;
            }
            var titulo = accion === 'aceptar' ? '¿Aceptar la solución?' : '¿Revertir o reabrir?';
            var texto = accion === 'aceptar' ? 'Se modificarán datos del proyecto. El valor anterior queda guardado y se puede revertir.' : 'El hallazgo volverá a quedar pendiente.';
            if (accion === 'aceptar' && li.querySelector('.inc-tag') && li.querySelector('.inc-tag').textContent.startsWith('Sin cambio')) texto = 'Solo se marcará como atendida.';
            pedirConfirmacion(titulo, texto).then(function (ok) {
                if (!ok) return;
                accionBtn.disabled = true;
                enviar('/' + cfg.herramienta + '/' + accion, { proyecto: cfg.proyecto, id: li.dataset.id, corrida: cfg.corrida }).then(function (j) {
                    if (msg) msg.textContent = j.mensaje || '';
                    if (j.success) { contar(j); quitarTarjeta(li.dataset.id); }
                    else accionBtn.disabled = false;
                }).catch(function () { if (msg) msg.textContent = 'Error de conexión'; accionBtn.disabled = false; });
            });
            return;
        }

        var cancelar = e.target.closest('[data-cancelar-rechazo]');
        if (cancelar) { var d1 = document.getElementById('inc-rechazo-dialog'); if (d1 && d1.open) d1.close(); return; }

        var confirmar = e.target.closest('[data-confirmar-rechazo]');
        if (confirmar) {
            var motivo = document.getElementById('inc-rechazo-motivo').value;
            var nota2 = document.getElementById('inc-rechazo-nota').value;
            var msg2 = document.getElementById('inc-rechazo-msg');
            confirmar.disabled = true;
            enviar('/' + cfg.herramienta + '/rechazar', { proyecto: cfg.proyecto, id: rechazo.id, motivo: motivo, nota: nota2, corrida: cfg.corrida })
                .then(function (j) {
                    if (j.success) { contar(j); quitarTarjeta(rechazo.id); var d2 = document.getElementById('inc-rechazo-dialog'); if (d2 && d2.open) d2.close(); }
                    else if (msg2) msg2.textContent = j.mensaje || 'No se pudo rechazar';
                })
                .catch(function () { if (msg2) msg2.textContent = 'Error de conexión'; })
                .finally(function () { confirmar.disabled = false; });
        }
    });

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('.inc-form');
        if (!form) return;
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        var accion = form.querySelector('.ed-accion').value;
        var params = paramsDe(form);
        var id = form.getAttribute('data-id');
        var msg = form.querySelector('.ed-msg');
        var boton = form.querySelector('button[type="submit"]');
        pedirConfirmacion('¿Aplicar la corrección?', 'Se modificará el dato del proyecto. El valor anterior queda guardado y se puede revertir.').then(function (ok) {
            if (!ok) return;
            boton.disabled = true;
            if (msg) msg.textContent = 'Aplicando…';
            enviar(form.getAttribute('data-url'), { proyecto: cfg.proyecto, id: id, accion: accion, params: params, corrida: cfg.corrida })
                .then(function (j) {
                    if (msg) msg.textContent = j.mensaje || '';
                    if (j.success) { contar(j); quitarTarjeta(id); cerrarEditor(); }
                    else boton.disabled = false;
                })
                .catch(function () { if (msg) msg.textContent = 'Error de conexión'; boton.disabled = false; });
        });
    });

    // Estado inicial: no hay editores en la lista (se cargan al abrir cada aviso)
    document.querySelectorAll('.inc-form').forEach(refrescar);
})();
