/* Estampas inteligentes y cajetines dinámicos (pestañas «Estampas inteligentes» y «Cajetines» de Símbolos).
   Datos del servidor en window.EST_DATA (catálogo de campos, partidas, actividades, personal, estampas). Sin build. */
(function () {
    'use strict';
    const D = window.EST_DATA;
    if (!D) return;
    const $ = (s, r) => (r || document).querySelector(s);
    const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());
    const debounce = (f, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), ms); }; };
    const opciones = (lista, sel) => lista.map(([v, t]) => '<option value="' + esc(v) + '"' + (String(v) === String(sel) ? ' selected' : '') + '>' + esc(t) + '</option>').join('');

    /** <option> agrupados del catálogo de campos. soloDoc = sin los campos de «Actividad elegida» (cajetines) */
    function opcionesCampos(sel, filtro) {
        return Object.keys(D.grupos).map(g => {
            const cs = D.campos.filter(c => c[1] === g && (!filtro || filtro(c)));
            return cs.length ? '<optgroup label="' + esc(D.grupos[g]) + '">' + cs.map(c => '<option value="' + esc(c[0]) + '"' + (c[0] === sel ? ' selected' : '') + '>' + esc(c[2]) + (c[4] ? ' — ej. ' + esc(c[4]) : '') + '</option>').join('') + '</optgroup>' : '';
        }).join('');
    }
    const campoDe = k => D.campos.find(c => c[0] === k);
    const ALIN = [['', 'Auto'], ['izquierda', 'Izquierda'], ['centro', 'Centro'], ['derecha', 'Derecha']];

    // ============================================================ Editor de estampas
    const caja = $('#est-editor');
    if (caja) {
        let cfg = null, id = 0, nombre = '', sucio = false;

        // Deshacer / rehacer (Ctrl+Z, Ctrl+Y o Ctrl+Mayús+Z): foto de la configuración tras cada cambio
        let hPas = [], hFut = [], hUlt = null, hTimer = null;
        const hReinicia = () => { clearTimeout(hTimer); hPas = []; hFut = []; hUlt = cfg ? JSON.stringify(cfg) : null; };
        const hRevisa = () => {
            clearTimeout(hTimer); if (!cfg || hUlt === null) return;
            const a = JSON.stringify(cfg); if (a === hUlt) return;
            hPas.push(hUlt); if (hPas.length > 100) hPas.shift(); hFut = []; hUlt = a;
        };
        const hProgramar = () => { clearTimeout(hTimer); hTimer = setTimeout(hRevisa, 350); };
        const hAplica = snap => { cfg = JSON.parse(snap); hUlt = snap; sel = -1; (cfg.modo === 'lienzo' ? pintarLienzo : pintar)(); };
        function deshacer() { hRevisa(); if (!hPas.length) return; hFut.push(hUlt); hAplica(hPas.pop()); }
        function rehacer() { hRevisa(); if (!hFut.length) return; hPas.push(hUlt); hAplica(hFut.pop()); }
        ['input', 'change', 'click', 'pointerup'].forEach(t => caja.addEventListener(t, hProgramar));
        document.addEventListener('keydown', ev => {
            if (caja.hidden || !cfg || !(ev.ctrlKey || ev.metaKey)) return;
            const k = ev.key.toLowerCase(); if (k !== 'z' && k !== 'y') return;
            if (/^(INPUT|TEXTAREA|SELECT)$/.test(ev.target.tagName) && !['range', 'color', 'checkbox'].includes(ev.target.type) && ev.target.tagName !== 'SELECT') return;   // en los cuadros de texto vale el deshacer del navegador
            ev.preventDefault(); (k === 'y' || ev.shiftKey) ? rehacer() : deshacer();
        });
        const actividadesOpc = () => [['0', '— La actividad del bloque —']].concat(D.actividades.map(a => [String(a.id), (a.codigo ? a.codigo + ' · ' : '') + a.nombre]));
        const valoresNuevos = {
            campo: () => ({ t: 'campo', campo: 'proyecto', prefijo: '', sufijo: '', formato: '', actividad_id: 0, tamano: 0, negrita: false, color: '', alinear: '' }),
            texto: () => ({ t: 'texto', texto: 'Texto libre. Puedes usar campos como {proyecto} o {corte:largo}', tamano: 0, negrita: false, color: '', alinear: '' }),
            semaforo: () => ({ t: 'semaforo', campo: 'spi', prefijo: 'SPI', sufijo: '', formato: '', actividad_id: 0, modo: 'mayor', verde: 0.95, rojo: 0.85, tamano: 0, negrita: false, color: '', alinear: '' }),
            actividades: () => ({ t: 'actividades', fecha_modo: 'corte', fecha: '', partidas: [], responsable: 0, max: 8, columnas: 2, campos: ['responsable', 'avance', 'dias', 'restriccion'], solo_pendientes: false, color_por: 'partida', tamano: 0, negrita: false, color: '', alinear: '' }),
        };
        const NOMBRE_TIPO = { campo: 'Campo inteligente', texto: 'Texto libre', semaforo: 'Semáforo', actividades: 'Actividades del día (post-its)' };
        const ICONO_TIPO = { campo: 'fa-tag', texto: 'fa-font', semaforo: 'fa-traffic-light', actividades: 'fa-note-sticky' };

        function formatosDe(k) {
            const c = campoDe(k); if (!c) return [['', 'Normal']];
            if (c[3] === 'fecha') return D.formatosFecha;
            if (c[3] === 'num' || c[3] === 'pct') return [['', 'Automático'], ['0', 'Sin decimales'], ['1', '1 decimal'], ['2', '2 decimales']];
            return [['', 'Normal']];
        }

        function tarjetaEtiqueta(e, i) {
            let h = '<div class="est-et" data-i="' + i + '"><div class="est-et-cab"><i class="fa-solid ' + ICONO_TIPO[e.t] + '"></i> <strong>' + esc(NOMBRE_TIPO[e.t]) + '</strong>' +
                '<span class="est-et-acc"><button type="button" class="btn-icono" data-acc="sube" title="Subir" aria-label="Subir"><i class="fa-solid fa-arrow-up"></i></button>' +
                '<button type="button" class="btn-icono" data-acc="baja" title="Bajar" aria-label="Bajar"><i class="fa-solid fa-arrow-down"></i></button>' +
                '<button type="button" class="btn-icono" data-acc="quita" title="Quitar" aria-label="Quitar"><i class="fa-solid fa-trash-can"></i></button></span></div><div class="est-et-cuerpo">';
            const campoSel = (clave, titulo) => '<label class="est-f est-f-ancho"><span>' + titulo + '</span><select data-k="campo">' + opcionesCampos(e.campo, c => e.t !== 'semaforo' || ['num', 'pct'].includes(c[3])) + '</select></label>';
            const actSel = () => (e.campo || '').startsWith('act_') ? '<label class="est-f est-f-ancho"><span>¿De qué actividad?</span><select data-k="actividad_id">' + opciones(actividadesOpc(), e.actividad_id || 0) + '</select></label>' : '';
            if (e.t === 'campo' || e.t === 'semaforo') {
                h += '<div class="est-fila">' + campoSel('campo', e.t === 'semaforo' ? 'Dato que mide el semáforo' : 'Dato que se llena solo') + actSel() + '</div>';
                h += '<div class="est-fila"><label class="est-f"><span>Antes del dato (prefijo)</span><input type="text" data-k="prefijo" maxlength="80" value="' + esc(e.prefijo) + '" placeholder="Ej. Avance: "></label>' +
                    '<label class="est-f"><span>Después del dato (sufijo)</span><input type="text" data-k="sufijo" maxlength="80" value="' + esc(e.sufijo) + '" placeholder="Ej. %"></label>' +
                    '<label class="est-f"><span>Formato</span><select data-k="formato">' + opciones(formatosDe(e.campo), e.formato) + '</select></label></div>';
                if (e.t === 'semaforo') {
                    h += '<div class="est-fila est-sem"><label class="est-f"><span>Es mejor cuando el valor es…</span><select data-k="modo">' + opciones([['mayor', 'más alto'], ['menor', 'más bajo']], e.modo) + '</select></label>' +
                        '<label class="est-f"><span><i class="est-pto" style="background:#16a34a"></i> Verde ' + (e.modo === 'menor' ? 'hasta' : 'desde') + '</span><input type="number" step="any" data-k="verde" value="' + esc(e.verde) + '"></label>' +
                        '<label class="est-f"><span><i class="est-pto" style="background:#dc2626"></i> Rojo ' + (e.modo === 'menor' ? 'por encima de' : 'por debajo de') + '</span><input type="number" step="any" data-k="rojo" value="' + esc(e.rojo) + '"></label></div>' +
                        '<p class="text-sm text-muted">Entre los dos umbrales sale amarillo. Se actualiza solo con los datos del proyecto.</p>';
                }
            } else if (e.t === 'texto') {
                h += '<label class="est-f est-f-ancho"><span>Texto (admite campos, ej. {proyecto} {corte:largo})</span><textarea data-k="texto" rows="2" maxlength="500">' + esc(e.texto) + '</textarea></label>' +
                    '<div class="est-fila"><label class="est-f"><span>Insertar campo</span><select data-ins="texto"><option value="">Elegir…</option>' + opcionesCampos('', c => !c[0].startsWith('act_')) + '</select></label></div>';
            } else if (e.t === 'actividades') {
                h += '<p class="text-sm text-muted">Una tarjeta (post-it) por cada actividad que cae en la fecha. Cambia sola cada día, sin editar nada.</p>' +
                    '<div class="est-fila"><label class="est-f"><span>Fecha</span><select data-k="fecha_modo">' + opciones([['corte', 'La de hoy (o la del bloque)'], ['fija', 'Una fecha fija']], e.fecha_modo) + '</select></label>' +
                    (e.fecha_modo === 'fija' ? '<label class="est-f"><span>Día</span><input type="date" data-k="fecha" value="' + esc(e.fecha) + '"></label>' : '') +
                    '<label class="est-f"><span>Máximo de tarjetas</span><input type="number" min="1" max="24" data-k="max" value="' + esc(e.max) + '"></label>' +
                    '<label class="est-f"><span>Columnas</span><select data-k="columnas">' + opciones([[1, '1'], [2, '2'], [3, '3'], [4, '4']], e.columnas) + '</select></label>' +
                    '<label class="est-f"><span>Color del post-it</span><select data-k="color_por">' + opciones([['partida', 'Color de la partida'], ['estado', 'Según el estado'], ['amarillo', 'Amarillo clásico']], e.color_por) + '</select></label></div>';
                h += '<div class="est-fila"><label class="est-f"><span>Solo de la partida</span><select data-k="partida1">' + opciones([['0', 'Todas las partidas']].concat(D.partidas.map(p => [String(p.id), p.codigo + ' · ' + p.nombre])), (e.partidas || [])[0] || 0) + '</select></label>' +
                    '<label class="est-f"><span>Solo de un responsable</span><select data-k="responsable">' + opciones([['0', 'Todos']].concat(D.personal.map(p => [String(p.id), p.nombre])), e.responsable || 0) + '</select></label>' +
                    '<label class="est-f est-chk"><input type="checkbox" data-k="solo_pendientes"' + (e.solo_pendientes ? ' checked' : '') + '> Ocultar las ya terminadas</label></div>';
                h += '<div class="est-f est-f-ancho"><span>Datos que muestra cada post-it</span><div class="est-checks">' + Object.keys(D.camposPostit).map(k =>
                    '<label><input type="checkbox" data-pc="' + k + '"' + ((e.campos || []).includes(k) ? ' checked' : '') + '> ' + esc(D.camposPostit[k]) + '</label>').join('') + '</div></div>';
            }
            h += '<details class="est-estilo"><summary>Estilo</summary><div class="est-fila"><label class="est-f"><span>Letra (pt, 0 = auto)</span><input type="number" min="0" max="60" step="0.5" data-k="tamano" value="' + esc(e.tamano || 0) + '"></label>' +
                '<label class="est-f"><span>Alineación</span><select data-k="alinear">' + opciones(ALIN, e.alinear || '') + '</select></label>' +
                '<label class="est-f est-chk"><input type="checkbox" data-k="negrita"' + (e.negrita ? ' checked' : '') + '> Negrita</label>' +
                '<label class="est-f"><span>Color</span><input type="color" data-k="color" value="' + esc(e.color || '#000000') + '"> <button type="button" class="btn btn-sm btn-ghost" data-acc="sincolor">Auto</button></label></div></details>';
            return h + '</div></div>';
        }

        function pintar() {
            const hoy = new Date().toISOString().slice(0, 10);
            caja.innerHTML =
                '<div class="est-ed-cab"><h4>' + (id ? 'Editar estampa' : 'Nueva estampa') + '</h4><button type="button" class="btn btn-ghost btn-sm" id="est-cerrar">Cerrar</button></div>' +
                '<div class="est-ed-grid"><div class="est-ed-form">' +
                '<div class="est-fila"><label class="est-f est-f-ancho"><span>Nombre de la estampa *</span><input type="text" id="est-nombre" maxlength="100" value="' + esc(nombre) + '" placeholder="Ej. Cajetín de planos eléctricos"></label></div>' +
                '<div class="est-fila"><label class="est-f"><span>Cómo se acomodan las etiquetas</span><select data-g="disposicion">' + opciones([['lineas', 'Una debajo de otra'], ['tabla', 'Tabla: título a la izquierda, dato a la derecha'], ['banda', 'En banda (una al lado de otra)']], cfg.disposicion) + '</select></label>' +
                '<label class="est-f"><span>Letra base (pt)</span><input type="number" min="3" max="40" step="0.5" data-g="tamano" value="' + esc(cfg.tamano) + '"></label>' +
                '<label class="est-f"><span>Ancho en la hoja (%)</span><input type="number" min="5" max="100" data-g="w" value="' + esc(cfg.w) + '"></label>' +
                '<label class="est-f"><span>Alto en la hoja (%)</span><input type="number" min="3" max="100" data-g="h" value="' + esc(cfg.h) + '"></label></div>' +
                '<div class="est-fila"><label class="est-f"><span>Fondo</span><input type="color" data-g="fondo" value="' + esc(cfg.fondo || '#ffffff') + '"> <label class="text-sm"><input type="checkbox" data-gsin="fondo"' + (cfg.fondo ? '' : ' checked') + '> sin fondo</label></label>' +
                '<label class="est-f"><span>Borde</span><input type="color" data-g="borde" value="' + esc(cfg.borde || '#444444') + '"> <label class="text-sm"><input type="checkbox" data-gsin="borde"' + (cfg.borde ? '' : ' checked') + '> sin borde</label></label>' +
                '<label class="est-f est-f-ancho"><span>Título de la estampa (opcional, admite campos)</span><input type="text" data-g="titulo" maxlength="200" value="' + esc(cfg.titulo) + '" placeholder="Ej. {proyecto}"></label>' +
                '<label class="est-f"><span>Color del título</span><input type="color" data-g="titulo_fondo" value="' + esc(cfg.titulo_fondo || '#5b82b4') + '"> <input type="color" data-g="titulo_color" value="' + esc(cfg.titulo_color || '#ffffff') + '" title="Texto"></label></div>' +
                '<div class="est-fila"><label class="est-f"><span>Revisión (para {revision})</span><input type="text" data-d="revision" maxlength="40" value="' + esc(cfg.datos.revision) + '" placeholder="Rev. 00"></label>' +
                '<label class="est-f"><span>Escala (para {escala})</span><input type="text" data-d="escala" maxlength="40" value="' + esc(cfg.datos.escala) + '" placeholder="1:100"></label></div>' +
                '<h5 class="est-h5">Etiquetas <small>(cada una = prefijo + dato + sufijo)</small></h5><div id="est-lista">' + cfg.etiquetas.map(tarjetaEtiqueta).join('') + '</div>' +
                '<div class="est-add"><button type="button" class="btn btn-sm btn-ghost" data-add="campo"><i class="fa-solid fa-tag"></i> + Campo</button><button type="button" class="btn btn-sm btn-ghost" data-add="texto"><i class="fa-solid fa-font"></i> + Texto</button>' +
                '<button type="button" class="btn btn-sm btn-ghost" data-add="semaforo"><i class="fa-solid fa-traffic-light"></i> + Semáforo</button><button type="button" class="btn btn-sm btn-ghost" data-add="actividades"><i class="fa-solid fa-note-sticky"></i> + Actividades del día</button></div>' +
                '<div class="est-ed-pie"><button type="button" class="btn btn-primary" id="est-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar estampa</button><span class="text-sm" id="est-estado"></span></div></div>' +
                '<div class="est-ed-prev"><h5 class="est-h5">Vista previa con datos reales</h5><div class="est-fila"><label class="est-f"><span>Actividad de ejemplo</span><select id="est-p-act">' + opciones([['0', '— Ninguna —']].concat(actividadesOpc().slice(1)), 0) + '</select></label>' +
                '<label class="est-f"><span>Fecha de corte</span><input type="date" id="est-p-corte" value="' + hoy + '"></label></div><div class="est-prev-marco"><div id="est-prev" class="est-prev"></div></div>' +
                '<p class="text-sm text-muted">Así se verá en la lámina. En el vPlan el bloque conserva este diseño y se actualiza solo con cada visita.</p></div></div>';
            previsualizar();
        }

        const previsualizar = debounce(async () => {
            if (cfg && cfg.modo === 'lienzo') { ajustarLienzo(); previsualizarL(); return; }
            const cont = $('#est-prev'); if (!cont) return;
            const act = $('#est-p-act'), corte = $('#est-p-corte');
            try {
                const d = await post('/api/estampas/vista', { proyecto: D.proyecto, config: JSON.stringify(cfg), modo: 'px', actividad: act ? act.value : 0, corte: corte ? corte.value : '' });
                const w = Math.round(cfg.w * 16), h = Math.round(cfg.h * 10.33), disp = Math.min(1, ($('.est-prev-marco').clientWidth - 24) / w);
                cont.style.cssText = 'width:' + w + 'px;height:' + h + 'px;zoom:' + (disp > 0 ? disp.toFixed(3) : 1);
                cont.innerHTML = d.success ? d.html : '<p class="text-muted">' + esc(d.error || 'Sin vista previa') + '</p>';
            } catch (e) { cont.textContent = 'No se pudo generar la vista previa'; }
        }, 300);

        // ============================================================ Lienzo (mini editor gráfico)
        const TIPOS_L = {
            linea: ['Línea', 'fa-minus'], rect: ['Rectángulo', 'fa-square'], elipse: ['Elipse', 'fa-circle'], rombo: ['Rombo', 'fa-diamond'],
            texto: ['Texto fijo (prefijo + valor)', 'fa-font'], campo: ['Campo inteligente', 'fa-tag'], semaforo: ['Semáforo', 'fa-traffic-light'],
            partida_dia: ['Partida del día', 'fa-layer-group'], actividades_dia: ['Actividades del día', 'fa-list-check'], logo: ['Logo', 'fa-image'],
        };
        const GRAF = ['linea', 'rect', 'elipse', 'rombo'];
        const nuevoEl = (tipo, x, y, w, h) => Object.assign({ tipo, x, y, w, h }, {
            linea: { color: '#000000', grosor: 1.4, estilo: 'solida', inv: false },
            rect: { relleno: '#fde68a', relleno2: '#ffffff', degradado: '', borde: '#000000', grosor: 1, radio: 0, sombra: false },
            elipse: { relleno: '#bfdbfe', relleno2: '#ffffff', degradado: '', borde: '#000000', grosor: 1, radio: 0, sombra: false },
            rombo: { relleno: '#fecaca', relleno2: '#ffffff', degradado: '', borde: '#000000', grosor: 1, radio: 0, sombra: false },
            texto: { prefijo: 'Etiqueta:', valor: 'Valor', tamano: 8, tamano_pre: 0, negrita: false, color: '', alinear: 'izquierda', valign: 'arriba' },
            campo: { campo: 'proyecto', prefijo: '', sufijo: '', formato: '', actividad_id: 0, tamano: 8, negrita: false, color: '', alinear: 'izquierda', valign: 'arriba' },
            semaforo: { campo: 'spi', prefijo: 'SPI', sufijo: '', formato: '', actividad_id: 0, modo: 'mayor', verde: 0.95, rojo: 0.85, tamano: 8, negrita: false, color: '', alinear: 'izquierda', valign: 'arriba' },
            partida_dia: { prefijo: 'PARTIDA:', modo: 'dia', partida_id: 0, varias: 'todas', fecha_modo: 'corte', fecha: '', tamano: 8, negrita: false, color: '', alinear: 'izquierda', valign: 'medio' },
            actividades_dia: { fecha_modo: 'corte', fecha: '', partidas: [], responsable: 0, max: 4, solo_pendientes: false, encabezado: true, rombos: true, codigo_partida: false, tit_actividad: 'ACTIVIDAD:', tit_dias: 'DÍAS PARA TERMINAR:', tamano: 7, negrita: false, color: '', alinear: '', valign: 'arriba' },
            logo: { fuente: 'compania', alinear: 'izquierda' },
        }[tipo]);
        let sel = -1, herr = 'sel', zoomL = 0, zoomMan = null, unidadL = 'mm';
        const LAM_MM = [432, 279], UNI = { mm: 1, cm: 10, 'in': 25.4 };   // lámina tabloide 11 x 17" horizontal
        const PAPELES = [['', 'Tamaño de papel…'], ['432,279', 'Tabloide 17 × 11" (432 × 279 mm)'], ['297,210', 'A4 horizontal (297 × 210 mm)'], ['279,216', 'Carta horizontal (279 × 216 mm)'], ['356,216', 'Oficio horizontal (356 × 216 mm)'], ['216,140', 'Media carta (216 × 140 mm)'], ['210,148', 'A5 horizontal (210 × 148 mm)']];
        const aUnidad = (pct, i) => String(Math.round(pct / 100 * LAM_MM[i] / UNI[unidadL] * 1000) / 1000);
        const redondea = n => Math.round(n * 10) / 10;
        const limita = (n, a, b) => Math.max(a, Math.min(b, n));

        function pintarLienzo() {
            const tb = [['sel', 'Seleccionar', 'fa-arrow-pointer']].concat(['linea', 'rect', 'elipse', 'rombo', 'texto', 'campo', 'semaforo', 'partida_dia', 'actividades_dia', 'logo'].map(k => [k, TIPOS_L[k][0], TIPOS_L[k][1]]));
            caja.innerHTML =
                '<div class="est-ed-cab"><h4>' + (id ? 'Editar estampa' : 'Nueva estampa') + ' · lienzo</h4><button type="button" class="btn btn-ghost btn-sm" id="est-cerrar">Cerrar</button></div>' +
                '<div class="est-fila"><label class="est-f est-f-ancho"><span>Nombre de la estampa *</span><input type="text" id="est-nombre" maxlength="100" value="' + esc(nombre) + '" placeholder="Ej. Encabezado de MSR"></label></div>' +
                '<div class="est-fila"><label class="est-f"><span>Tamaño de papel</span><select id="est-papel">' + opciones(PAPELES, '') + '</select></label>' +
                '<label class="est-f"><span>Unidad</span><select id="est-unidad">' + opciones([['mm', 'Milímetros (mm)'], ['cm', 'Centímetros (cm)'], ['in', 'Pulgadas (")']], unidadL) + '</select></label>' +
                '<label class="est-f"><span>Ancho (<b class="est-u">' + unidadL + '</b>)</span><input type="number" min="1" step="any" data-mm="0" value="' + aUnidad(cfg.w, 0) + '"></label>' +
                '<label class="est-f"><span>Alto (<b class="est-u">' + unidadL + '</b>)</span><input type="number" min="1" step="any" data-mm="1" value="' + aUnidad(cfg.h, 1) + '"></label>' +
                '<span class="text-sm text-muted est-f-ancho">La lámina mide 432 × 279 mm: ese es el máximo. Al imprimir la estampa en un formato más grande conserva su proporción.</span></div>' +
                '<div class="est-fila"><label class="est-f"><span>Ancho (% de la lámina)</span><input type="number" min="3" max="100" step="0.1" data-g="w" value="' + esc(cfg.w) + '"></label>' +
                '<label class="est-f"><span>Alto (% de la lámina)</span><input type="number" min="3" max="100" step="0.1" data-g="h" value="' + esc(cfg.h) + '"></label>' +
                '<label class="est-f"><span>Letra base (pt)</span><input type="number" min="3" max="40" step="0.5" data-g="tamano" value="' + esc(cfg.tamano) + '"></label>' +
                '<label class="est-f"><span>Fondo</span><input type="color" data-g="fondo" value="' + esc(cfg.fondo || '#ffffff') + '"> <label class="text-sm"><input type="checkbox" data-gsin="fondo"' + (cfg.fondo ? '' : ' checked') + '> sin fondo</label></label>' +
                '<label class="est-f"><span>Borde</span><input type="color" data-g="borde" value="' + esc(cfg.borde || '#000000') + '"> <label class="text-sm"><input type="checkbox" data-gsin="borde"' + (cfg.borde ? '' : ' checked') + '> sin borde</label></label></div>' +
                '<div class="est-fila"><span class="text-sm text-muted">Tamaños rápidos (lámina de 432 × 279 mm):</span><button type="button" class="btn btn-sm btn-ghost" data-tam="16.43,25.45">Post-it 7 × 7 cm</button><button type="button" class="btn btn-sm btn-ghost" data-tam="100,12">Encabezado ancho</button><button type="button" class="btn btn-sm btn-ghost" data-tam="30,20">Cajetín</button></div>' +
                '<div class="est-lz-barra">' + tb.map(([k, t, ic]) => '<button type="button" class="btn btn-sm ' + (herr === k ? 'btn-primary' : 'btn-ghost') + '" data-herr="' + k + '" title="' + esc(t) + '"><i class="fa-solid ' + ic + '"></i> ' + esc(t.replace(/ \(.*/, '')) + '</button>').join('') + '</div>' +
                '<p class="text-sm text-muted">' + (herr === 'sel' ? 'Haz clic en un elemento para editarlo; arrástralo para moverlo y usa la esquina para cambiar su tamaño.' : 'Arrastra sobre el lienzo para dibujar «' + esc(TIPOS_L[herr][0]) + '». Las figuras y líneas quedan siempre detrás de los datos inteligentes.') + '</p>' +
                '<div class="est-lz-grid"><div class="est-lz-col"><div class="est-lz-zoom"><button type="button" class="btn btn-sm btn-ghost" data-hist="deshacer" title="Deshacer (Ctrl+Z)"><i class="fa-solid fa-rotate-left"></i></button><button type="button" class="btn btn-sm btn-ghost" data-hist="rehacer" title="Rehacer (Ctrl+Y)"><i class="fa-solid fa-rotate-right"></i></button><span class="est-sep"></span><button type="button" class="btn btn-sm btn-ghost" data-zoom="-" title="Alejar"><i class="fa-solid fa-magnifying-glass-minus"></i></button><span id="est-zoom-txt" class="est-zoom-txt">100%</span><button type="button" class="btn btn-sm btn-ghost" data-zoom="+" title="Acercar"><i class="fa-solid fa-magnifying-glass-plus"></i></button><button type="button" class="btn btn-sm btn-ghost" data-zoom="1" title="Tamaño real">100%</button><button type="button" class="btn btn-sm btn-ghost" data-zoom="ajustar" title="Ajustar a la ventana"><i class="fa-solid fa-expand"></i> Ajustar</button><span class="text-sm text-muted est-zoom-ayuda">Ctrl + rueda = zoom · botón central o espacio + arrastrar = mover</span></div><div class="est-lz-marco" id="est-lz-marco"><div id="est-lz" class="est-lz"><div id="est-lz-bg" class="est-lz-bg"></div><div id="est-lz-ov" class="est-lz-ov"></div></div></div></div>' +
                '<div class="est-lz-lado"><div class="est-lz-capas" id="est-lz-capas"></div><div class="est-lz-prop" id="est-lz-prop"></div></div></div>' +
                '<div class="est-fila"><label class="est-f"><span>Actividad de ejemplo</span><select id="est-p-act">' + opciones([['0', '— Ninguna —']].concat(actividadesOpc().slice(1)), 0) + '</select></label>' +
                '<label class="est-f"><span>Fecha de corte (vista previa)</span><input type="date" id="est-p-corte" value="' + new Date().toISOString().slice(0, 10) + '"></label></div>' +
                '<div class="est-ed-pie"><button type="button" class="btn btn-primary" id="est-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar estampa</button><span class="text-sm" id="est-estado"></span></div>';
            ajustarLienzo(); propsLienzo(); capaLienzo(); previsualizarL();
        }

        function ajustarLienzo() {
            const lz = $('#est-lz'), marco = $('.est-lz-marco'); if (!lz) return;
            const W = cfg.w * 16, H = cfg.h * 10.33;
            const fit = limita(Math.min((marco.clientWidth - 40) / W, (marco.clientHeight - 40) / H), 0.1, 5);
            zoomL = zoomMan === null ? fit : limita(zoomMan, 0.1, 5);
            lz.style.cssText = 'width:' + W + 'px;height:' + H + 'px;zoom:' + zoomL.toFixed(3);
            const t = $('#est-zoom-txt'); if (t) t.textContent = Math.round(zoomL * 100) + '%';
        }

        function cambiarZoom(nuevo, cx, cy) {
            const marco = $('#est-lz-marco'), lz = $('#est-lz'); if (!marco || !lz) return;
            const antes = zoomL, r = marco.getBoundingClientRect(), l0 = lz.getBoundingClientRect();
            const px = cx === undefined ? r.left + r.width / 2 : cx, py = cy === undefined ? r.top + r.height / 2 : cy;
            const fx = (px - l0.left) / l0.width, fy = (py - l0.top) / l0.height;   // punto del lienzo bajo el cursor
            zoomMan = nuevo === null ? null : limita(nuevo, 0.1, 5);
            ajustarLienzo();
            if (nuevo === null) { marco.scrollLeft = marco.scrollTop = 0; return; }
            const l1 = lz.getBoundingClientRect();
            marco.scrollLeft += (l1.left + fx * l1.width) - px; marco.scrollTop += (l1.top + fy * l1.height) - py;
        }

        const nombreCapa = e => (e.nombre || '').trim() || ((e.tipo === 'texto' && (e.valor || e.prefijo)) ? TIPOS_L.texto[0] + ': ' + String(e.valor || e.prefijo).replace(/\s+/g, ' ').slice(0, 24) : ((e.tipo === 'campo' || e.tipo === 'semaforo') && campoDe(e.campo) ? TIPOS_L[e.tipo][0] + ': ' + campoDe(e.campo)[2].replace(/ \(.*/, '') : TIPOS_L[e.tipo][0]));

        function capaLienzo() {
            const ov = $('#est-lz-ov'); if (!ov) return;
            ov.innerHTML = cfg.elementos.map((e, i) => e.oculto ? '' : '<div class="est-lz-el' + (i === sel ? ' sel' : '') + (e.bloqueado ? ' bloq' : '') + '" data-i="' + i + '" style="left:' + e.x + '%;top:' + e.y + '%;width:' + e.w + '%;height:' + e.h + '%" title="' + esc(nombreCapa(e)) + (e.bloqueado ? ' (bloqueada)' : '') + '">' +
                (i === sel && !e.bloqueado ? '<i class="est-lz-mango" data-mango="1"></i>' : '') + '</div>').join('');
            pintarCapasLista();
        }

        // Gestión de capas: la de arriba de la lista es la que queda al frente
        function pintarCapasLista() {
            const c = $('#est-lz-capas'); if (!c) return;
            const l = cfg.elementos, sx = l[sel];
            c.innerHTML = '<div class="est-capas-cab"><h5 class="est-h5">Capas <small>(' + l.length + ')</small></h5><span class="est-capas-acc">' +
                ['frente|fa-angles-up|Traer al frente', 'adelante|fa-angle-up|Subir una capa', 'atras|fa-angle-down|Bajar una capa', 'fondo|fa-angles-down|Enviar al fondo'].map(x => { const [k, ic, t] = x.split('|'); return '<button type="button" class="btn btn-sm btn-ghost" data-el="' + k + '" title="' + t + '"' + (sx ? '' : ' disabled') + '><i class="fa-solid ' + ic + '"></i></button>'; }).join('') +
                '</span></div>' +
                '<select class="est-cap-nueva" id="est-cap-nueva" aria-label="Nueva capa"><option value="">＋ Nueva capa…</option>' + Object.keys(TIPOS_L).map(k => '<option value="' + k + '">' + esc(TIPOS_L[k][0]) + '</option>').join('') + '</select>' +
                (l.length ? '<ol class="est-capas">' + l.map((e, i) => i).reverse().map(i => { const e = l[i];
                    return '<li class="est-cap' + (i === sel ? ' sel' : '') + (e.oculto ? ' oculta' : '') + '" draggable="true" data-i="' + i + '"><i class="fa-solid fa-grip-vertical est-cap-asa" title="Arrastra para cambiar el orden"></i>' +
                        '<button type="button" class="est-cap-nom" data-pick="' + i + '" title="' + esc(nombreCapa(e)) + '"><i class="fa-solid ' + TIPOS_L[e.tipo][1] + '"></i> <span>' + esc(nombreCapa(e)) + '</span></button>' +
                        '<button type="button" class="est-cap-b' + (e.oculto ? ' on' : '') + '" data-cap="ver" data-i="' + i + '" title="' + (e.oculto ? 'Mostrar' : 'Ocultar') + '"><i class="fa-solid ' + (e.oculto ? 'fa-eye-slash' : 'fa-eye') + '"></i></button>' +
                        '<button type="button" class="est-cap-b' + (e.bloqueado ? ' on' : '') + '" data-cap="bloq" data-i="' + i + '" title="' + (e.bloqueado ? 'Desbloquear' : 'Bloquear (no se mueve)') + '"><i class="fa-solid ' + (e.bloqueado ? 'fa-lock' : 'fa-lock-open') + '"></i></button>' +
                        '<button type="button" class="est-cap-b" data-cap="dup" data-i="' + i + '" title="Duplicar"><i class="fa-solid fa-clone"></i></button>' +
                        '<button type="button" class="est-cap-b est-cap-del" data-cap="del" data-i="' + i + '" title="Quitar"><i class="fa-solid fa-trash-can"></i></button></li>'; }).join('') + '</ol>'
                    : '<p class="text-muted text-sm">Aún no hay capas. Elige una herramienta y dibuja sobre el lienzo.</p>');
        }

        const previsualizarL = debounce(async () => {
            const bg = $('#est-lz-bg'); if (!bg) return;
            const act = $('#est-p-act'), corte = $('#est-p-corte');
            try {
                const d = await post('/api/estampas/vista', { proyecto: D.proyecto, config: JSON.stringify(cfg), modo: 'px', actividad: act ? act.value : 0, corte: corte ? corte.value : '' });
                bg.innerHTML = d.success ? d.html : '<p class="text-muted">' + esc(d.error || 'Sin vista previa') + '</p>';
            } catch (e) { bg.textContent = 'No se pudo generar la vista previa'; }
        }, 250);

        function campoP(e, k, titulo, tipo, extra) {
            const v = e[k];
            if (tipo === 'color') return '<label class="est-f"><span>' + titulo + '</span><input type="color" data-ek="' + k + '" value="' + esc(v || '#000000') + '"></label>';
            if (tipo === 'chk') return '<label class="est-f est-chk"><input type="checkbox" data-ek="' + k + '"' + (v ? ' checked' : '') + '> ' + titulo + '</label>';
            if (tipo === 'num') return '<label class="est-f"><span>' + titulo + '</span><input type="number" step="' + (extra || 0.1) + '" data-ek="' + k + '" value="' + esc(v) + '"></label>';
            if (tipo === 'sel') return '<label class="est-f"><span>' + titulo + '</span><select data-ek="' + k + '">' + opciones(extra, v) + '</select></label>';
            if (tipo === 'campo') return '<label class="est-f est-f-ancho"><span>' + titulo + '</span><select data-ek="' + k + '">' + opcionesCampos(v, c => e.tipo !== 'semaforo' || ['num', 'pct'].includes(c[3])) + '</select></label>';
            return '<label class="est-f"><span>' + titulo + '</span><input type="text" data-ek="' + k + '" value="' + esc(v) + '"></label>';
        }

        function propsLienzo() {
            const p = $('#est-lz-prop'); if (!p) return;
            const e = cfg.elementos[sel];
            if (!e) {
                p.innerHTML = '<h5 class="est-h5">Propiedades</h5><p class="text-muted text-sm">Selecciona una capa de la lista o un elemento del lienzo para editarlo. Con <b>Ctrl + clic</b> en la lista no se mueve nada: solo se elige.</p>';
                return;
            }
            const g = GRAF.includes(e.tipo);
            let h = '<h5 class="est-h5"><i class="fa-solid ' + TIPOS_L[e.tipo][1] + '"></i> ' + esc(TIPOS_L[e.tipo][0]) + '</h5>' +
                '<div class="est-fila">' + campoP(e, 'nombre', 'Nombre de la capa (opcional)').replace('class="est-f"', 'class="est-f est-f-ancho"') + '</div>' +
                '<div class="est-fila est-alin">' + [['i', 'fa-align-left', 'Alinear a la izquierda'], ['c', 'fa-align-center', 'Centrar horizontal'], ['d', 'fa-align-right', 'Alinear a la derecha'], ['s', 'fa-arrow-up-from-bracket', 'Pegar arriba'], ['m', 'fa-arrows-up-down', 'Centrar vertical'], ['b', 'fa-arrow-down-to-line', 'Pegar abajo']].map(([k, ic, t]) => '<button type="button" class="btn btn-sm btn-ghost" data-alin="' + k + '" title="' + t + '"><i class="fa-solid ' + ic + '"></i></button>').join('') + '</div>' +
                '<div class="est-fila">' + campoP(e, 'x', 'X (%)', 'num') + campoP(e, 'y', 'Y (%)', 'num') + campoP(e, 'w', 'Ancho (%)', 'num') + campoP(e, 'h', 'Alto (%)', 'num') + '</div>';
            if (e.tipo === 'linea') h += '<div class="est-fila">' + campoP(e, 'color', 'Color', 'color') + campoP(e, 'grosor', 'Grosor', 'num') + campoP(e, 'estilo', 'Trazo', 'sel', [['solida', 'Continuo'], ['guiones', 'Guiones'], ['puntos', 'Puntos']]) + campoP(e, 'inv', 'Diagonal ascendente', 'chk') + '</div>';
            else if (g) h += '<div class="est-fila">' + campoP(e, 'relleno', 'Relleno', 'color') + '<label class="est-f est-chk"><input type="checkbox" data-sinrel="1"' + (e.relleno ? '' : ' checked') + '> sin relleno</label>' + campoP(e, 'degradado', 'Sombreado', 'sel', [['', 'Liso'], ['v', 'Degradado vertical'], ['h', 'Degradado horizontal'], ['d', 'Degradado diagonal']]) + campoP(e, 'relleno2', 'Color final', 'color') + '</div>' +
                '<div class="est-fila">' + campoP(e, 'borde', 'Borde', 'color') + '<label class="est-f est-chk"><input type="checkbox" data-sinbor="1"' + (e.borde ? '' : ' checked') + '> sin borde</label>' + campoP(e, 'grosor', 'Grosor', 'num') + (e.tipo === 'rect' ? campoP(e, 'radio', 'Esquinas redondas', 'num', 1) : '') + campoP(e, 'sombra', 'Sombra', 'chk') + '</div>';
            else if (e.tipo === 'texto') h += '<div class="est-fila">' + campoP(e, 'prefijo', 'Prefijo fijo') + campoP(e, 'valor', 'Valor fijo (admite {campos})') + '</div>';
            else if (e.tipo === 'campo' || e.tipo === 'semaforo') {
                h += '<div class="est-fila">' + campoP(e, 'campo', e.tipo === 'semaforo' ? 'Dato que mide' : 'Dato que se llena solo', 'campo') + '</div><div class="est-fila">' + campoP(e, 'prefijo', 'Prefijo') + campoP(e, 'sufijo', 'Sufijo') + campoP(e, 'formato', 'Formato', 'sel', formatosDe(e.campo)) + '</div>';
                if ((e.campo || '').startsWith('act_')) h += '<div class="est-fila">' + campoP(e, 'actividad_id', '¿De qué actividad?', 'sel', actividadesOpc()) + '</div>';
                if (e.tipo === 'semaforo') h += '<div class="est-fila">' + campoP(e, 'modo', 'Es mejor cuando es…', 'sel', [['mayor', 'más alto'], ['menor', 'más bajo']]) + campoP(e, 'verde', 'Verde', 'num', 'any') + campoP(e, 'rojo', 'Rojo', 'num', 'any') + '</div>';
            } else if (e.tipo === 'partida_dia') {
                h += '<div class="est-fila">' + campoP(e, 'prefijo', 'Texto antes') + campoP(e, 'modo', 'Qué partida', 'sel', [['dia', 'La(s) del día'], ['fija', 'Una partida fija']]) +
                    (e.modo === 'fija' ? campoP(e, 'partida_id', 'Partida', 'sel', [['0', '—']].concat(D.partidas.map(q => [String(q.id), q.codigo + ' · ' + q.nombre]))) : campoP(e, 'varias', 'Si hay varias', 'sel', [['todas', 'Poner «TODAS LAS PARTIDAS»'], ['unir', 'Listarlas']])) + '</div>' +
                    '<div class="est-fila">' + campoP(e, 'fecha_modo', 'Día', 'sel', [['corte', 'El de hoy (o el del bloque)'], ['fija', 'Fecha fija']]) + (e.fecha_modo === 'fija' ? campoP(e, 'fecha', 'Fecha') .replace('type="text"', 'type="date"') : '') + '</div>';
            } else if (e.tipo === 'actividades_dia') {
                h += '<div class="est-fila">' + campoP(e, 'fecha_modo', 'Día', 'sel', [['corte', 'El de hoy (o el del bloque)'], ['fija', 'Fecha fija']]) + (e.fecha_modo === 'fija' ? campoP(e, 'fecha', 'Fecha').replace('type="text"', 'type="date"') : '') + campoP(e, 'max', 'Máx. de renglones', 'num', 1) + '</div>' +
                    '<div class="est-fila"><label class="est-f"><span>Solo de la partida</span><select data-ek="partida1">' + opciones([['0', 'Todas']].concat(D.partidas.map(q => [String(q.id), q.codigo + ' · ' + q.nombre])), (e.partidas || [])[0] || 0) + '</select></label>' +
                    campoP(e, 'encabezado', 'Mostrar encabezado', 'chk') + campoP(e, 'rombos', 'Rombos de semáforo', 'chk') + campoP(e, 'codigo_partida', 'Código de partida', 'chk') + campoP(e, 'solo_pendientes', 'Ocultar terminadas', 'chk') + '</div>' +
                    '<div class="est-fila">' + campoP(e, 'tit_actividad', 'Título columna 1') + campoP(e, 'tit_dias', 'Título columna 2') + '</div>' +
                    '<p class="text-sm text-muted">Cada renglón: rombos (verde finalizada · amarillo en proceso · rojo con restricción), actividad y días hábiles para terminar.</p>';
            } else if (e.tipo === 'logo') h += '<div class="est-fila">' + campoP(e, 'fuente', 'Logo de', 'sel', [['compania', 'La compañía (empresa)'], ['proyecto', 'El proyecto'], ['contratista', 'El contratista / cliente']]) + campoP(e, 'alinear', 'Alineación', 'sel', ALIN.slice(1)) + '</div>';
            if (['texto', 'campo', 'semaforo', 'partida_dia', 'actividades_dia'].includes(e.tipo)) h += '<div class="est-fila">' + campoP(e, 'tamano', 'Letra (pt, 0 = base)', 'num', 0.5) + campoP(e, 'alinear', 'Alineación', 'sel', ALIN) + campoP(e, 'valign', 'Vertical', 'sel', [['arriba', 'Arriba'], ['medio', 'Centro'], ['abajo', 'Abajo']]) + campoP(e, 'color', 'Color', 'color') + campoP(e, 'negrita', 'Negrita', 'chk') + '</div>';
            h += '<div class="est-fila"><button type="button" class="btn btn-sm btn-ghost" data-el="duplica"><i class="fa-solid fa-clone"></i> Duplicar</button><button type="button" class="btn btn-sm btn-danger" data-el="quita"><i class="fa-solid fa-trash-can"></i> Quitar</button></div>';
            p.innerHTML = h;
        }

        function lzCambio() { capaLienzo(); previsualizarL(); }

        // Arrastrar, redimensionar y dibujar
        caja.addEventListener('pointerdown', ev => {
            const lz = ev.target.closest('#est-lz'); if (!lz || !cfg || cfg.modo !== 'lienzo') return;
            const r = lz.getBoundingClientRect();
            const pos = e2 => [limita((e2.clientX - r.left) / r.width * 100, 0, 100), limita((e2.clientY - r.top) / r.height * 100, 0, 100)];
            const [x0, y0] = pos(ev);
            const el = ev.target.closest('.est-lz-el');
            if (herr !== 'sel' && !el) {   // dibujar
                ev.preventDefault();
                const e = nuevoEl(herr, redondea(x0), redondea(y0), 0, 0); cfg.elementos.push(e); sel = cfg.elementos.length - 1;
                const mover = m => { const [x1, y1] = pos(m); e.x = redondea(Math.min(x0, x1)); e.y = redondea(Math.min(y0, y1)); e.w = redondea(Math.abs(x1 - x0)); e.h = redondea(Math.abs(y1 - y0)); e.inv = herr === 'linea' && ((x1 - x0) * (y1 - y0) < 0); capaLienzo(); };
                const fin = () => {
                    document.removeEventListener('pointermove', mover); document.removeEventListener('pointerup', fin);
                    const grande = e.w > 1 || e.h > 1;
                    if (!grande) { const def = { texto: [30, 8], campo: [30, 8], semaforo: [30, 8], partida_dia: [40, 12], actividades_dia: [90, 50], logo: [30, 14], rect: [20, 14], elipse: [20, 14], rombo: [16, 14], linea: [30, 0] }[herr]; e.w = Math.min(def[0], 100 - e.x); e.h = Math.min(def[1], 100 - e.y); }
                    if (herr === 'linea' && e.w > e.h * 3) e.h = 0; else if (herr === 'linea' && e.h > e.w * 3) e.w = 0;
                    herr = 'sel'; pintarLienzo();
                };
                document.addEventListener('pointermove', mover); document.addEventListener('pointerup', fin);
                return;
            }
            if (!el) { if (sel !== -1) { sel = -1; capaLienzo(); propsLienzo(); } return; }
            ev.preventDefault();
            const i = +el.dataset.i, e = cfg.elementos[i], mango = !!ev.target.dataset.mango;
            const cambioSel = sel !== i; sel = i;
            if (e.bloqueado) { capaLienzo(); propsLienzo(); return; }   // capa bloqueada: solo se selecciona
            const o = { x: e.x, y: e.y, w: e.w, h: e.h };
            const mover = m => {
                const [x1, y1] = pos(m), dx = x1 - x0, dy = y1 - y0;
                if (mango) { e.w = redondea(limita(o.w + dx, 0, 100 - o.x)); e.h = redondea(limita(o.h + dy, 0, 100 - o.y)); }
                else { e.x = redondea(limita(o.x + dx, 0, 100 - o.w)); e.y = redondea(limita(o.y + dy, 0, 100 - o.h)); }
                capaLienzo();
            };
            const fin = () => { document.removeEventListener('pointermove', mover); document.removeEventListener('pointerup', fin); propsLienzo(); capaLienzo(); previsualizarL(); };
            document.addEventListener('pointermove', mover); document.addEventListener('pointerup', fin);
            if (cambioSel) { capaLienzo(); propsLienzo(); }
        });

        caja.addEventListener('input', ev => {
            const t = ev.target; if (!cfg || cfg.modo !== 'lienzo') return;
            if (t.dataset.ek) {
                const e = cfg.elementos[sel]; if (!e) return; const k = t.dataset.ek;
                if (k === 'partida1') e.partidas = +t.value ? [+t.value] : [];
                else e[k] = t.type === 'checkbox' ? t.checked : (t.type === 'number' ? (t.value === '' ? 0 : +t.value) : (['actividad_id', 'partida_id'].includes(k) ? +t.value : t.value));
                lzCambio();
            } else if (t.dataset.sinrel) { const e = cfg.elementos[sel]; e.relleno = t.checked ? '' : ($('[data-ek="relleno"]', caja).value); lzCambio(); }
            else if (t.dataset.sinbor) { const e = cfg.elementos[sel]; e.borde = t.checked ? '' : ($('[data-ek="borde"]', caja).value); lzCambio(); }
        });
        caja.addEventListener('change', ev => {
            const t = ev.target; if (!cfg || cfg.modo !== 'lienzo') return;
            if (t.dataset.ek && ['campo', 'modo', 'fecha_modo', 'partida_id'].includes(t.dataset.ek)) {
                const e = cfg.elementos[sel];
                if (t.dataset.ek === 'campo') { e.formato = ''; const d = D.semaforo[t.value]; if (e.tipo === 'semaforo' && d) { e.modo = d[0]; e.verde = d[1]; e.rojo = d[2]; } const c = campoDe(t.value); if (c && !e.prefijo) e.prefijo = c[2].replace(/ \(.*/, '') + ': '; }
                propsLienzo(); lzCambio();
            }
        });
        caja.addEventListener('click', ev => {
            const b = ev.target.closest('button'); if (!b || !cfg || cfg.modo !== 'lienzo') return;
            if (b.dataset.hist) { b.dataset.hist === 'deshacer' ? deshacer() : rehacer(); return; }
            if (b.dataset.zoom) { const z = b.dataset.zoom; cambiarZoom(z === 'ajustar' ? null : z === '+' ? zoomL * 1.25 : z === '-' ? zoomL / 1.25 : +z); return; }
            if (b.dataset.herr) { herr = b.dataset.herr; pintarLienzo(); return; }
            if (b.dataset.tam) { [cfg.w, cfg.h] = b.dataset.tam.split(',').map(Number); pintarLienzo(); return; }
            if (b.dataset.pick) { sel = +b.dataset.pick; capaLienzo(); propsLienzo(); return; }
            if (b.dataset.cap) {
                const l = cfg.elementos, i = +b.dataset.i, e = l[i]; if (!e) return;
                if (b.dataset.cap === 'ver') { e.oculto = !e.oculto; if (e.oculto && sel === i) sel = -1; }
                else if (b.dataset.cap === 'bloq') e.bloqueado = !e.bloqueado;
                else if (b.dataset.cap === 'dup') { const c = JSON.parse(JSON.stringify(e)); c.x = Math.min(c.x + 2, 100 - c.w); c.y = Math.min(c.y + 2, 100 - c.h); c.nombre = e.nombre ? e.nombre + ' (copia)' : ''; l.splice(i + 1, 0, c); sel = i + 1; }
                else if (b.dataset.cap === 'del') { l.splice(i, 1); sel = sel === i ? -1 : (sel > i ? sel - 1 : sel); }
                capaLienzo(); propsLienzo(); previsualizarL(); return;
            }
            if (b.dataset.alin) {
                const e = cfg.elementos[sel]; if (!e || e.bloqueado) return;
                const a = b.dataset.alin;
                if (a === 'i') e.x = 0; else if (a === 'c') e.x = redondea((100 - e.w) / 2); else if (a === 'd') e.x = redondea(100 - e.w);
                else if (a === 's') e.y = 0; else if (a === 'm') e.y = redondea((100 - e.h) / 2); else if (a === 'b') e.y = redondea(100 - e.h);
                capaLienzo(); propsLienzo(); previsualizarL(); return;
            }
            if (b.dataset.el) {
                const l = cfg.elementos;
                if (b.dataset.el === 'quita') { l.splice(sel, 1); sel = -1; }
                else if (b.dataset.el === 'duplica') { const c = JSON.parse(JSON.stringify(l[sel])); c.x = Math.min(c.x + 2, 100 - c.w); c.y = Math.min(c.y + 2, 100 - c.h); l.push(c); sel = l.length - 1; }
                else if (b.dataset.el === 'frente' && sel < l.length - 1) { l.push(l.splice(sel, 1)[0]); sel = l.length - 1; }
                else if (b.dataset.el === 'fondo' && sel > 0) { l.unshift(l.splice(sel, 1)[0]); sel = 0; }
                else if (b.dataset.el === 'adelante' && sel < l.length - 1) { [l[sel + 1], l[sel]] = [l[sel], l[sel + 1]]; sel++; }
                else if (b.dataset.el === 'atras' && sel > 0) { [l[sel - 1], l[sel]] = [l[sel], l[sel - 1]]; sel--; }
                capaLienzo(); propsLienzo(); previsualizarL();
            }
        }, true);
        window.addEventListener('resize', () => { if (cfg && cfg.modo === 'lienzo') ajustarLienzo(); });
        // Reordenar capas arrastrándolas en la lista
        let capaArr = -1;
        caja.addEventListener('dragstart', ev => { const li = ev.target.closest && ev.target.closest('.est-cap'); if (!li) return; capaArr = +li.dataset.i; ev.dataTransfer.effectAllowed = 'move'; try { ev.dataTransfer.setData('text/plain', String(capaArr)); } catch (_) { /* sin datos */ } li.classList.add('arrastrada'); });
        caja.addEventListener('dragend', () => { capaArr = -1; caja.querySelectorAll('.est-cap').forEach(x => x.classList.remove('arrastrada', 'sobre-arriba', 'sobre-abajo')); });
        caja.addEventListener('dragover', ev => {
            const li = ev.target.closest && ev.target.closest('.est-cap'); if (!li || capaArr < 0) return;
            ev.preventDefault(); const r = li.getBoundingClientRect(), arriba = ev.clientY < r.top + r.height / 2;
            caja.querySelectorAll('.est-cap').forEach(x => x.classList.remove('sobre-arriba', 'sobre-abajo')); li.classList.add(arriba ? 'sobre-arriba' : 'sobre-abajo');
        });
        caja.addEventListener('drop', ev => {
            const li = ev.target.closest && ev.target.closest('.est-cap'); if (!li || capaArr < 0 || !cfg) return;
            ev.preventDefault();
            const l = cfg.elementos, r = li.getBoundingClientRect(), arriba = ev.clientY < r.top + r.height / 2;   // arriba en la lista = más al frente
            let dest = +li.dataset.i + (arriba ? 1 : 0);
            const mov = l[capaArr], cur = l[sel];
            l.splice(capaArr, 1); if (capaArr < dest) dest--;
            l.splice(dest, 0, mov); sel = cur ? l.indexOf(cur) : -1; capaArr = -1;
            capaLienzo(); propsLienzo(); previsualizarL();
        });
        caja.addEventListener('wheel', ev => {
            const m = ev.target.closest('#est-lz-marco'); if (!m || !ev.ctrlKey) return;
            ev.preventDefault(); cambiarZoom(zoomL * (ev.deltaY < 0 ? 1.12 : 1 / 1.12), ev.clientX, ev.clientY);
        }, { passive: false });
        // Mover el lienzo: rueda central, espacio + arrastrar, o arrastrar el fondo gris
        let espacio = false;
        document.addEventListener('keydown', ev => { if (ev.code === 'Space' && cfg && cfg.modo === 'lienzo' && !/INPUT|TEXTAREA|SELECT|BUTTON/.test(ev.target.tagName) && $('#est-lz-marco')) { espacio = true; $('#est-lz-marco').classList.add('mover'); ev.preventDefault(); } });
        document.addEventListener('keyup', ev => { if (ev.code === 'Space') { espacio = false; const m = $('#est-lz-marco'); if (m) m.classList.remove('mover'); } });
        caja.addEventListener('pointerdown', ev => {
            const m = ev.target.closest('#est-lz-marco'); if (!m || !cfg || cfg.modo !== 'lienzo') return;
            if (!(ev.button === 1 || (espacio && ev.button === 0) || (ev.target === m && ev.button === 0))) return;
            ev.preventDefault(); ev.stopPropagation();
            const x0 = ev.clientX, y0 = ev.clientY, sx = m.scrollLeft, sy = m.scrollTop; m.classList.add('arrastrando');
            const mv = e2 => { m.scrollLeft = sx - (e2.clientX - x0); m.scrollTop = sy - (e2.clientY - y0); };
            const fin = () => { m.classList.remove('arrastrando'); document.removeEventListener('pointermove', mv); document.removeEventListener('pointerup', fin); };
            document.addEventListener('pointermove', mv); document.addEventListener('pointerup', fin);
        }, true);

        function abrir(e, modoNuevo) {
            id = e ? e.id : 0;
            const modo = e ? (e.config && e.config.modo === 'lienzo' ? 'lienzo' : 'etiquetas') : (modoNuevo || 'lienzo'); nombre = e ? e.nombre : '';
            const base = { disposicion: 'lineas', tamano: 9, fondo: '#ffffff', borde: '#444444', titulo: '', titulo_fondo: '#5b82b4', titulo_color: '#ffffff', datos: { revision: '', escala: '' }, w: 26, h: 16, etiquetas: [], elementos: [], modo: 'etiquetas' };
            cfg = Object.assign(base, JSON.parse(JSON.stringify(e ? e.config : {})));
            cfg.datos = Object.assign({ revision: '', escala: '' }, cfg.datos || {});
            if (!e) cfg.etiquetas = [valoresNuevos.campo()];
            if (modo === 'lienzo') { zoomMan = null; cfg.modo = 'lienzo'; cfg.elementos = cfg.elementos || []; if (!e) { cfg.w = 16.43; cfg.h = 25.45; cfg.etiquetas = []; } sel = -1; herr = 'sel'; }
            else cfg.modo = 'etiquetas';
            caja.hidden = false; (cfg.modo === 'lienzo' ? pintarLienzo : pintar)(); hReinicia(); caja.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        window.EstampasEditor = { abrir };

        // Cambios en el formulario
        caja.addEventListener('input', ev => {
            const t = ev.target;
            if (t.id === 'est-nombre') { nombre = t.value; return; }
            if (t.id === 'est-p-act' || t.id === 'est-p-corte') { previsualizar(); return; }
            if (t.dataset.mm !== undefined) {   // ancho/alto en mm, cm o pulgadas
                const i = +t.dataset.mm, v = +t.value; if (!(v > 0)) return;
                cfg[i ? 'h' : 'w'] = limita(Math.round(v * UNI[unidadL] / LAM_MM[i] * 100 * 1000) / 1000, i ? 3 : 3, 100);
                const g = $('[data-g="' + (i ? 'h' : 'w') + '"]', caja); if (g) g.value = cfg[i ? 'h' : 'w'];
            }
            else if (t.dataset.g) {
                cfg[t.dataset.g] = t.type === 'number' ? +t.value : t.value;
                if (t.dataset.g === 'w' || t.dataset.g === 'h') { const m = $('[data-mm="' + (t.dataset.g === 'h' ? 1 : 0) + '"]', caja); if (m) m.value = aUnidad(cfg[t.dataset.g], t.dataset.g === 'h' ? 1 : 0); }
            }
            else if (t.dataset.gsin) { const k = t.dataset.gsin; cfg[k] = t.checked ? '' : $('[data-g="' + k + '"]', caja).value; }
            else if (t.dataset.d) cfg.datos[t.dataset.d] = t.value;
            else if (t.dataset.ins) { /* se maneja en change */ }
            else {
                const tar = t.closest('.est-et'); if (!tar) return;
                const e = cfg.etiquetas[+tar.dataset.i];
                if (t.dataset.pc) { e.campos = [...tar.querySelectorAll('[data-pc]')].filter(c => c.checked).map(c => c.dataset.pc); }
                else if (t.dataset.k === 'partida1') e.partidas = +t.value ? [+t.value] : [];
                else if (t.dataset.k) e[t.dataset.k] = t.type === 'checkbox' ? t.checked : (t.type === 'number' ? (t.value === '' ? '' : +t.value) : (['actividad_id', 'responsable'].includes(t.dataset.k) ? +t.value : t.value));
            }
            previsualizar();
        });
        caja.addEventListener('change', ev => {
            const t = ev.target;
            if (t.id === 'est-papel' && t.value) { const [mw, mh] = t.value.split(',').map(Number); cfg.w = limita(Math.round(mw / LAM_MM[0] * 100000) / 1000, 3, 100); cfg.h = limita(Math.round(mh / LAM_MM[1] * 100000) / 1000, 3, 100); pintarLienzo(); return; }
            if (t.id === 'est-cap-nueva' && t.value && cfg && cfg.modo === 'lienzo') {   // crea una capa nueva centrada, encima de las demás
                const tipo = t.value, d = { texto: [30, 8], campo: [30, 8], semaforo: [30, 8], partida_dia: [40, 12], actividades_dia: [90, 50], logo: [30, 14], rect: [20, 14], elipse: [20, 14], rombo: [16, 14], linea: [30, 0] }[tipo] || [20, 10];
                const w = Math.min(d[0], 100), h = Math.min(d[1], 100), e = nuevoEl(tipo, redondea((100 - w) / 2), redondea((100 - h) / 2), w, h);
                cfg.elementos.push(e); sel = cfg.elementos.length - 1; herr = 'sel'; capaLienzo(); propsLienzo(); previsualizarL(); return;
            }
            if (t.id === 'est-unidad') { unidadL = t.value; pintarLienzo(); return; }
            if (t.dataset.gsin) { const k = t.dataset.gsin; cfg[k] = t.checked ? '' : $('[data-g="' + k + '"]', caja).value; previsualizar(); return; }
            if (t.dataset.ins) {   // insertar {campo} al final del texto libre
                if (!t.value) return;
                const tar = t.closest('.est-et'), e = cfg.etiquetas[+tar.dataset.i]; e.texto = (e.texto || '') + '{' + t.value + '}'; pintar(); return;
            }
            const tar = t.closest('.est-et'); if (!tar) return;
            const e = cfg.etiquetas[+tar.dataset.i];
            if (t.dataset.k === 'campo') {   // al cambiar de dato se ajustan formato y umbrales
                e.formato = ''; const d = D.semaforo[t.value]; if (e.t === 'semaforo' && d) { e.modo = d[0]; e.verde = d[1]; e.rojo = d[2]; }
                const c = campoDe(t.value); if (c && !e.prefijo) e.prefijo = c[2].replace(/ \(.*/, '') + ': ';
                pintar(); return;
            }
            if (['modo', 'fecha_modo'].includes(t.dataset.k)) pintar();
        });
        caja.addEventListener('click', async ev => {
            const b = ev.target.closest('button'); if (!b) return;
            if (b.id === 'est-cerrar') { caja.hidden = true; return; }
            if (b.dataset.add) { cfg.etiquetas.push(valoresNuevos[b.dataset.add]()); pintar(); return; }
            const tar = b.closest('.est-et');
            if (tar && b.dataset.acc) {
                const i = +tar.dataset.i, l = cfg.etiquetas;
                if (b.dataset.acc === 'quita') l.splice(i, 1);
                else if (b.dataset.acc === 'sube' && i > 0) [l[i - 1], l[i]] = [l[i], l[i - 1]];
                else if (b.dataset.acc === 'baja' && i < l.length - 1) [l[i + 1], l[i]] = [l[i], l[i + 1]];
                else if (b.dataset.acc === 'sincolor') l[i].color = '';
                pintar(); return;
            }
            if (b.id === 'est-guardar') {
                const est = $('#est-estado'); est.textContent = 'Guardando…'; b.disabled = true;
                try {
                    const d = await post('/simbolos/estampa', { proyecto: D.proyecto, id: id || '', nombre, config: JSON.stringify(cfg) });
                    if (!d.success) { est.textContent = d.error || 'No se pudo guardar'; b.disabled = false; return; }
                    location.href = '/simbolos?proyecto=' + D.proyecto + '&ok=' + encodeURIComponent('Estampa guardada') + '#estampas';
                } catch (e) { est.textContent = 'No se pudo guardar'; b.disabled = false; }
            }
        });

        // Lista de estampas y plantillas
        document.addEventListener('click', async ev => {
            const b = ev.target.closest('[data-est]'); if (!b) return;
            const acc = b.dataset.est, eid = +b.dataset.id || 0;
            if (acc === 'nueva') abrir(null, 'lienzo');
            else if (acc === 'nueva-et') abrir(null, 'etiquetas');
            else if (acc === 'editar') abrir(D.estampas.find(e => e.id === eid));
            else if (acc === 'plantilla') {
                b.disabled = true;
                const d = await post('/simbolos/estampa/plantilla', { proyecto: D.proyecto, clave: b.dataset.clave });
                if (d.success) location.href = '/simbolos?proyecto=' + D.proyecto + '&editar=' + d.id + '#estampas'; else { window.avisoModal('No se pudo crear', d.error || 'No se pudo crear'); b.disabled = false; }
            } else if (acc === 'duplicar') {
                const d = await post('/simbolos/estampa/duplicar', { proyecto: D.proyecto, id: eid });
                if (d.success) location.href = '/simbolos?proyecto=' + D.proyecto + '&ok=' + encodeURIComponent('Estampa duplicada') + '#estampas';
            } else if (acc === 'eliminar') {
                if (!await window.confirmarModal({ titulo: '¿Eliminar la estampa?', mensaje: '«' + b.dataset.nombre + '»: los bloques de las láminas que la usan quedarán vacíos.', aceptar: 'Eliminar' })) return;
                const d = await post('/simbolos/estampa/eliminar', { proyecto: D.proyecto, id: eid });
                if (d.success) location.href = '/simbolos?proyecto=' + D.proyecto + '&ok=' + encodeURIComponent('Estampa eliminada') + '#estampas';
            }
        });
        const q = new URLSearchParams(location.search).get('editar');
        if (q) { const e = D.estampas.find(x => x.id === +q); if (e) abrir(e); }
    }

    // ============================================================ Editor de filas de cajetín
    const PLANTILLAS_CAJETIN = {
        estandar: ['Estándar', [['Proyecto', '{proyecto}'], ['Título', '{titulo}'], ['Fecha', '{fecha}'], ['Elaboró', '{autor}'], ['Revisó', ''], ['Lámina', '{lamina} de {total}']]],
        plano: ['Plano de obra', [['Proyecto', '{proyecto}'], ['Cliente', '{cliente}'], ['Plano', '{titulo}'], ['Escala', '{escala}'], ['Revisión', '{revision}'], ['Fecha', '{fecha:corto}'], ['Lámina', '{lamina} / {total}']]],
        avance: ['Avance de obra', [['Proyecto', '{proyecto}'], ['Corte', '{corte:largo}'], ['Avance real', '{avance_real} %'], ['Programado', '{avance_prog} %'], ['SPI', '{spi}'], ['Días restantes', '{dias_restantes}']]],
        control: ['Control de obra', [['Proyecto', '{proyecto}'], ['Restricciones activas', '{restr_activas}'], ['Órdenes de cambio abiertas', '{oc_abiertas}'], ['Retiros pendientes', '{retiros_pendientes}'], ['Último reporte', '{reporte_ultimo}']]],
    };
    document.querySelectorAll('.cj-form').forEach(form => {
        const ta = $('textarea[name=filas]', form), orient = $('select[name=orientacion]', form);
        if (!ta) return;
        const cont = document.createElement('div'); cont.className = 'cj-editor';
        ta.hidden = true; ta.insertAdjacentElement('afterend', cont);
        let filas = ta.value.split('\n').filter(l => l.trim() !== '').map(l => { const p = l.split('|'); return [(p.shift() || '').trim(), p.join('|').trim()]; });
        if (!filas.length) filas = [['Proyecto', '{proyecto}']];
        const sync = () => { ta.value = filas.map(f => f[0].replace(/\|/g, '/') + ' | ' + f[1]).join('\n'); };
        const vista = debounce(async () => {
            const t = $('.cj-vista', cont); if (!t) return;
            const textos = filas.flatMap(f => f);
            try {
                const d = await post('/api/estampas/textos', { proyecto: D.proyecto, textos });
                const r = d.success ? d.textos : textos, banda = orient && orient.value === 'banda';
                const par = filas.map((f, i) => [r[i * 2], r[i * 2 + 1]]);
                t.innerHTML = banda ? '<table><thead><tr>' + par.map(p => '<th>' + esc(p[0]) + '</th>').join('') + '</tr></thead><tbody><tr>' + par.map(p => '<td>' + esc(p[1]) + '</td>').join('') + '</tr></tbody></table>'
                    : '<table><tbody>' + par.map(p => '<tr><th>' + esc(p[0]) + '</th><td>' + esc(p[1]) + '</td></tr>').join('') + '</tbody></table>';
            } catch (e) { t.textContent = ''; }
        }, 300);
        function pintar() {
            cont.innerHTML = '<p class="text-sm text-muted">Cada renglón tiene un <strong>título</strong> (a la izquierda) y un <strong>dato</strong> (a la derecha). El dato puede ser un texto fijo o un <strong>campo</strong> que se llena solo, como el nombre del proyecto, la fecha o el avance.</p>' +
                '<div class="cj-plant"><label class="text-sm">Empezar con una plantilla <select class="cj-pl"><option value="">Elegir…</option>' + Object.keys(PLANTILLAS_CAJETIN).map(k => '<option value="' + k + '">' + esc(PLANTILLAS_CAJETIN[k][0]) + '</option>').join('') + '</select></label></div>' +
                '<div class="cj-filas">' + filas.map((f, i) => '<div class="cj-fila" data-i="' + i + '"><input type="text" class="cj-e" maxlength="60" value="' + esc(f[0]) + '" placeholder="Título (ej. Proyecto)" aria-label="Título">' +
                    '<input type="text" class="cj-v" maxlength="120" value="' + esc(f[1]) + '" placeholder="Dato (ej. {proyecto} o un texto)" aria-label="Dato">' +
                    '<select class="cj-campo" aria-label="Insertar campo"><option value="">＋ Campo…</option>' + opcionesCampos('', c => !c[0].startsWith('act_')) + '</select>' +
                    '<button type="button" class="btn-icono" data-a="sube" aria-label="Subir"><i class="fa-solid fa-arrow-up"></i></button><button type="button" class="btn-icono" data-a="baja" aria-label="Bajar"><i class="fa-solid fa-arrow-down"></i></button>' +
                    '<button type="button" class="btn-icono" data-a="quita" aria-label="Quitar renglón"><i class="fa-solid fa-trash-can"></i></button></div>').join('') + '</div>' +
                '<button type="button" class="btn btn-sm btn-ghost cj-mas"><i class="fa-solid fa-plus"></i> Añadir renglón</button>' +
                '<div class="cj-prev"><span class="text-sm text-muted">Vista previa con datos reales:</span><div class="cj-vista"></div></div>';
            sync(); vista();
        }
        cont.addEventListener('input', ev => {
            const fila = ev.target.closest('.cj-fila'); if (!fila) return;
            const f = filas[+fila.dataset.i];
            if (ev.target.classList.contains('cj-e')) f[0] = ev.target.value; else if (ev.target.classList.contains('cj-v')) f[1] = ev.target.value;
            sync(); vista();
        });
        cont.addEventListener('change', ev => {
            const t = ev.target;
            if (t.classList.contains('cj-pl')) { const p = PLANTILLAS_CAJETIN[t.value]; if (p) { filas = p[1].map(f => f.slice()); pintar(); } return; }
            if (t.classList.contains('cj-campo') && t.value) {
                const fila = t.closest('.cj-fila'), inp = $('.cj-v', fila), f = filas[+fila.dataset.i], pos = inp.selectionStart == null ? f[1].length : inp.selectionStart;
                f[1] = f[1].slice(0, pos) + '{' + t.value + '}' + f[1].slice(pos); pintar();
            }
        });
        cont.addEventListener('click', ev => {
            if (ev.target.closest('.cj-mas')) { if (filas.length < 20) filas.push(['', '']); pintar(); return; }
            const b = ev.target.closest('[data-a]'); if (!b) return;
            const i = +b.closest('.cj-fila').dataset.i;
            if (b.dataset.a === 'quita') filas.splice(i, 1);
            else if (b.dataset.a === 'sube' && i > 0) [filas[i - 1], filas[i]] = [filas[i], filas[i - 1]];
            else if (b.dataset.a === 'baja' && i < filas.length - 1) [filas[i + 1], filas[i]] = [filas[i], filas[i + 1]];
            if (!filas.length) filas.push(['', '']);
            pintar();
        });
        if (orient) orient.addEventListener('change', vista);
        form.addEventListener('submit', () => { filas = filas.filter(f => f[0].trim() || f[1].trim()); sync(); });
        pintar();
    });
})();
