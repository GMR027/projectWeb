/* Instalación de materiales en vPlan (JavaScript puro, sin build).
 * Asigna actividades/subactividades a las líneas y formas del plano, crea la tabla de
 * SheetFlow de la lámina y muestra las fotos de los materiales sobre cada marca. */
(function () {
  'use strict';
  const C = window.VPLAN_CONFIG || {};
  const M = C.materiales;
  if (!M) return;
  const $ = s => document.querySelector(s);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const V = () => window.__vplan || null;
  const ACT = new Map((M.actividades || []).map(a => [Number(a.id), a]));
  const SUB = new Map();
  (M.actividades || []).forEach(a => (a.subactividades || []).forEach(s => SUB.set(Number(s.id), { sub: s, act: a })));
  const PART = new Map((M.partidas || []).map(p => [Number(p.id), p]));
  const LINEAS = ['line', 'arrow', 'darrow'];
  const TIPOS = ['line', 'arrow', 'darrow', 'rect', 'rrect', 'ellipse', 'zone', 'triangle', 'diamond', 'star', 'pentagon', 'hexagon', 'cross', 'xmark', 'check', 'blockarrow', 'chevron', 'trapezoid', 'octagon', 'parallelogram', 'rtriangle', 'house', 'arrowleft', 'arrowup', 'arrowdown', 'arrowlr', 'homeplate', 'star4', 'star6', 'star8', 'heart', 'moon', 'semicircle', 'bolt', 'drop', 'lshape', 'tshape', 'ushape', 'callout', 'flag'];

  const S = {
    tabla: M.tabla || null,
    datos: null,
    imgs: {},          // actividad_id => [{src, nombre}]
    q: '',
    draft: null,
    pendiente: 0,
  };

  // ---------- utilidades de claves ----------
  const claveDe = it => it && it.tipo === 'subactividad' ? 's' + Number(it.id) : (it && it.tipo === 'actividad' ? 'a' + Number(it.id) : '');
  const itemDeClave = k => {
    if (!k) return null;
    const tipo = k.charAt(0) === 's' ? 'subactividad' : 'actividad';
    const id = Number(k.slice(1));
    if (tipo === 'actividad') return ACT.has(id) ? { tipo, id } : null;
    const s = SUB.get(id);
    return s ? { tipo, id, actividad_id: Number(s.act.id) } : null;
  };
  const itemsDe = d => ((d && d.material && Array.isArray(d.material.items)) ? d.material.items : []);
  const nombreClave = k => {
    const it = itemDeClave(k);
    if (!it) return 'Actividad eliminada';
    if (it.tipo === 'actividad') {
      const a = ACT.get(it.id);
      return (a.codigo ? a.codigo + ' · ' : '') + a.nombre;
    }
    const s = SUB.get(it.id);
    return (s.act.codigo ? s.act.codigo + ' · ' : '') + s.sub.nombre;
  };
  const actividadDeItem = it => Number(it.tipo === 'subactividad' ? it.actividad_id : it.id) || 0;
  const marcaActual = () => {
    const v = V();
    return v && v.state.selId ? v.state.shapes.find(x => x.id === v.state.selId) : null;
  };
  const compatible = d => !!d && TIPOS.includes(d.type);

  // ---------- imágenes de materiales ----------
  function imagenesParaMarca(d) {
    const out = [], vistos = new Set();
    for (const it of itemsDe(d)) {
      const aid = actividadDeItem(it);
      for (const im of (S.imgs[aid] || [])) {
        if (!im || !im.src || vistos.has(im.src)) continue;
        vistos.add(im.src);
        out.push(im);
        if (out.length >= 8) return out;
      }
    }
    return out;
  }
  window.MaterialesVplan = window.MaterialesVplan || {};
  window.MaterialesVplan.imagenesParaMarca = imagenesParaMarca;

  async function cargarImagenes() {
    const v = V();
    if (!v) return;
    const ids = [];
    const vistos = new Set();
    v.state.shapes.forEach(d => itemsDe(d).forEach(it => {
      const aid = actividadDeItem(it);
      if (aid && !vistos.has(aid) && !(aid in S.imgs)) { vistos.add(aid); ids.push(aid); }
    }));
    if (!ids.length) return;
    try {
      const fd = new FormData();
      fd.append('proyecto', C.proyecto);
      fd.append('lamina', C.lamina);
      ids.forEach(id => fd.append('actividades[]', id));
      const r = await fetch(M.api.imagenes, { method: 'POST', body: fd }).then(x => x.json());
      if (!r || !r.success) return;
      Object.keys(r.imagenes || {}).forEach(id => { S.imgs[Number(id)] = r.imagenes[id] || []; });
      ids.forEach(id => { if (!(id in S.imgs)) S.imgs[id] = []; });
      if (v.renderOverlays) v.renderOverlays();
      actualizarProps();
    } catch (e) { /* sin fotos: la herramienta sigue funcionando */ }
  }

  // ---------- marcas por clave / colores ----------
  function marcasPorClave() {
    const v = V(), map = new Map();
    if (!v) return map;
    v.state.shapes.forEach(d => {
      const ks = new Set(itemsDe(d).map(claveDe).filter(Boolean));
      ks.forEach(k => { if (!map.has(k)) map.set(k, []); map.get(k).push(d); });
    });
    return map;
  }
  const colorDe = d => LINEAS.includes(d.type) ? (d.stroke || '#0070c0') : (d.fill || d.stroke || '#0070c0');
  function chipsColores(ds) {
    const vistos = [];
    ds.forEach(d => { const c = colorDe(d); if (c && /^#[0-9a-f]{6}$/i.test(c) && !vistos.includes(c)) vistos.push(c); });
    return vistos.slice(0, 4).map(c => '<i class="mat-color" style="background:' + esc(c) + '" title="' + esc(c) + '"></i>').join('') + (vistos.length > 4 ? '<i class="mat-color mat-color-mas">+' + (vistos.length - 4) + '</i>' : '');
  }

  // ---------- panel ----------
  function refrescarPanel() {
    if (!V()) return;
    const crear = $('#mat-crear'), con = $('#mat-con-tabla'), sin = $('#mat-sin-tabla');
    if (!crear) return;
    if (!S.tabla) { sin.hidden = false; con.hidden = true; return; }
    sin.hidden = true; con.hidden = false;
    $('#mat-sheetflow').href = '/sheetflow/ver?proyecto=' + encodeURIComponent(C.proyecto) + '&id=' + encodeURIComponent(S.tabla.hoja || '');
    $('#mat-bloque').href = '/vplan/ver?proyecto=' + encodeURIComponent(C.proyecto) + '&id=' + encodeURIComponent(C.lamina) + '&diseno=1';
    pintarTabla();
  }

  function filasTabla() {
    const m = S.datos && S.datos.matriz;
    if (!m) return { columnas: [], filas: [], ids: [] };
    const ids = ((m.meta && m.meta.materiales && m.meta.materiales.ids) || []);
    return { columnas: m.columnas || [], filas: m.filas || [], ids: ids };
  }

  function pintarTabla() {
    const v = V();
    const { columnas, filas, ids } = filasTabla();
    const porClave = marcasPorClave();
    const q = S.q.toLowerCase();
    const claves = filas.map((fila, i) => String(ids[i] || ''));
    const todas = v.state.shapes.filter(d => compatible(d));
    const conItems = todas.filter(d => itemsDe(d).length);
    const sinAsignar = todas.length - conItems.length;
    const sinUbicacion = claves.filter((k, i) => k && !(porClave.get(k) || []).length).length;
    $('#mat-contador').textContent = filas.length ? '(' + filas.length + ')' : '';
    $('#mat-resumen').innerHTML = '<span><b>' + filas.length + '</b> material(es)</span><span><b>' + conItems.length + '</b> marca(s) con material</span>'
      + (sinAsignar ? '<span class="mat-alerta"><b>' + sinAsignar + '</b> marca(s) sin asignar</span>' : '')
      + (sinUbicacion ? '<span class="mat-alerta"><b>' + sinUbicacion + '</b> renglón(es) sin ubicación</span>' : '');
    $('#mat-tabla-info').textContent = filas.length + ' fila(s) · ' + columnas.length + ' columna(s)';

    // Leyenda
    const ley = $('#mat-leyenda');
    ley.innerHTML = '';
    const visibles = [];
    filas.forEach((fila, i) => {
      const k = String(ids[i] || '');
      const ds = k ? (porClave.get(k) || []) : [];
      const txt = (fila[0] || nombreClave(k) || '') + ' ' + (k ? nombreClave(k) : '');
      if (q && !txt.toLowerCase().includes(q)) return;
      visibles.push(i);
      const li = document.createElement('li');
      li.className = 'mat-fila' + (k && !ds.length ? ' mat-sin-ubicacion' : '');
      li.dataset.clave = k;
      li.dataset.fila = String(i);
      li.innerHTML = '<span class="mat-fila-col">' + chipsColores(ds) + '</span>'
        + '<span class="mat-fila-txt"><b>' + esc(fila[0] || nombreClave(k) || '—') + '</b><small>' + esc(fila[1] || '') + ' · ' + esc(fila[2] || '') + '</small></span>'
        + '<span class="mat-fila-n" title="Líneas o formas en el plano">' + ds.length + '</span>';
      li.addEventListener('click', () => seleccionarClave(k));
      ley.appendChild(li);
    });
    if (!visibles.length) ley.innerHTML = '<li class="mat-vacio">' + (filas.length ? 'Ningún material coincide con la búsqueda.' : 'Aún no hay materiales. Dibuja una línea o forma y asígnale una actividad.') + '</li>';

    // Tabla completa (todas las columnas de SheetFlow)
    const thead = $('#mat-tabla thead'), tbody = $('#mat-tabla tbody');
    thead.innerHTML = '<tr><th>N.</th>' + columnas.map(c => '<th>' + esc(c || '') + '</th>').join('') + '</tr>';
    tbody.innerHTML = '';
    filas.forEach((fila, i) => {
      const k = String(ids[i] || '');
      const ds = k ? (porClave.get(k) || []) : [];
      const txt = fila.join(' ') + ' ' + (k ? nombreClave(k) : '');
      if (q && !txt.toLowerCase().includes(q)) return;
      const tr = document.createElement('tr');
      tr.dataset.clave = k; tr.dataset.fila = String(i);
      if (k && !ds.length) tr.className = 'mat-sin-ubicacion';
      tr.innerHTML = '<td class="c">' + (i + 1) + '</td>' + fila.map((c, j) => '<td' + (j === 0 ? ' class="mat-material"' : '') + '>' + (j === 0 ? chipsColores(ds) : '') + esc(c || '') + '</td>').join('');
      tr.addEventListener('click', () => seleccionarClave(k));
      tbody.appendChild(tr);
    });
    if (!filas.length) tbody.innerHTML = '<tr><td colspan="' + (columnas.length + 1) + '" class="mat-vacio">La tabla aún no tiene renglones.</td></tr>';
  }

  function seleccionarClave(k) {
    const v = V();
    if (!v || !k) return;
    const ds = marcasPorClave().get(k) || [];
    if (!ds.length) return;
    v.selectIds(ds.map(d => d.id));
    const t = document.querySelector('.ve-tabs [data-tab="props"]');
    if (t) t.click();
  }

  // ---------- panel de propiedades ----------
  function actualizarProps() {
    const sec = $('#p-mat-seccion');
    if (!sec) return;
    const d = marcaActual();
    if (!compatible(d)) { sec.style.display = 'none'; return; }
    sec.style.display = '';
    const mat = d.material && Array.isArray(d.material.items) ? d.material : null;
    const lista = $('#p-mat-lista'), vacio = $('#p-mat-vacio');
    const convertida = !!mat;
    $('#p-mat-convertir').style.display = convertida ? 'none' : '';
    $('#p-mat-imagenes').style.display = convertida ? '' : 'none';
    $('#p-mat-agregar').style.display = convertida ? '' : 'none';
    if (!convertida) {
      lista.innerHTML = '';
      vacio.textContent = 'Esta marca todavía no es de materiales. Conviértela para asignarle actividades y ver las fotos de sus materiales.';
      vacio.style.display = '';
      return;
    }
    const items = itemsDe(d);
    vacio.style.display = items.length ? 'none' : '';
    vacio.textContent = 'Sin actividades asignadas. Agrega una actividad o subactividad para relacionar la forma con el programa.';
    lista.innerHTML = items.map(it => {
      const k = claveDe(it);
      const ok = !!itemDeClave(k);
      return '<li class="mat-chip' + (ok ? '' : ' mat-chip-mal') + '" data-clave="' + esc(k) + '"><span>' + esc(ok ? nombreClave(k) : 'Actividad eliminada') + '</span><button type="button" title="Quitar" aria-label="Quitar">✕</button></li>';
    }).join('');
    $('#p-mat-mostrar').checked = !!d.mat_mostrar;
    const tam = Math.max(24, Math.min(400, +d.mat_tam || 64));
    $('#p-mat-tam').value = tam; $('#p-mat-tam-n').value = tam;
    $('#p-mat-pos').value = d.mat_pos === 'borde' ? 'borde' : 'centro';
    $('#p-mat-pos-f').style.display = LINEAS.includes(d.type) ? 'none' : '';
    $('#p-mat-color-estado').checked = !!d.mat_colorEstado;
    const nImg = imagenesParaMarca(d).length;
    $('#p-mat-img-info').textContent = nImg ? nImg + ' imagen(es) del catálogo de materiales.' : (items.length ? 'Las actividades asignadas no tienen fotos en el catálogo de materiales.' : '');
    lista.querySelectorAll('.mat-chip button').forEach(b => b.addEventListener('click', e => {
      const k = e.currentTarget.closest('.mat-chip').dataset.clave;
      modificarMarca(t => { t.material.items = itemsDe(t).filter(it => claveDe(it) !== k); });
    }));
  }

  function modificarMarca(fn) {
    const v = V();
    if (!v) return;
    const nuevas = JSON.parse(JSON.stringify(v.state.shapes));
    const t = nuevas.find(x => x.id === v.state.selId);
    if (!t) return;
    if (!t.material) t.material = { items: [] };
    if (!Array.isArray(t.material.items)) t.material.items = [];
    fn(t);
    v.commit(nuevas);
    actualizarProps();
    refrescarPanel();
    cargarImagenes();
  }

  // ---------- selector de actividades ----------
  function abrirPicker() {
    const d = marcaActual();
    if (!d || !compatible(d)) return;
    S.draft = new Set(itemsDe(d).map(claveDe).filter(Boolean));
    const ov = $('#ve-mat-modal');
    if (!ov) return;
    $('#mat-modal-buscar').value = '';
    pintarPicker('');
    ov.classList.add('abierto'); ov.setAttribute('aria-hidden', 'false');
    setTimeout(() => $('#mat-modal-buscar').focus(), 30);
  }
  function cerrarPicker() {
    const ov = $('#ve-mat-modal');
    if (ov) { ov.classList.remove('abierto'); ov.setAttribute('aria-hidden', 'true'); }
    S.draft = null;
  }
  function pintarPicker(q) {
    const cont = $('#mat-modal-lista');
    if (!cont) return;
    q = (q || '').trim().toLowerCase();
    const sel = S.draft || new Set();
    const grupos = [];
    (M.partidas || []).forEach(p => {
      const acts = (M.actividades || []).filter(a => Number(a.partida_id) === Number(p.id));
      const filtradas = acts.filter(a => {
        if (!q) return true;
        if ((a.nombre || '').toLowerCase().includes(q) || (a.codigo || '').toLowerCase().includes(q) || (p.nombre || '').toLowerCase().includes(q)) return true;
        return (a.subactividades || []).some(s => (s.nombre || '').toLowerCase().includes(q));
      });
      if (filtradas.length) grupos.push({ p, acts: filtradas });
    });
    cont.innerHTML = grupos.map(({ p, acts }) => {
      const abierto = !!q || acts.some(a => sel.has('a' + a.id) || (a.subactividades || []).some(s => sel.has('s' + s.id)));
      return '<details class="mat-grupo"' + (abierto ? ' open' : '') + '><summary><span>' + esc((p.codigo ? p.codigo + ' · ' : '') + p.nombre) + '</span><small>' + acts.length + '</small></summary>'
        + acts.map(a => {
          const subs = (a.subactividades || []).filter(s => !q || (a.nombre || '').toLowerCase().includes(q) || (a.codigo || '').toLowerCase().includes(q) || (p.nombre || '').toLowerCase().includes(q) || (s.nombre || '').toLowerCase().includes(q));
          const subSel = subs.some(s => sel.has('s' + s.id));
          return '<div class="mat-grupo-act">'
            + '<label class="mat-check"><input type="checkbox" data-k="a' + a.id + '"' + (sel.has('a' + a.id) ? ' checked' : '') + '><span>' + esc((a.codigo ? a.codigo + ' · ' : '') + a.nombre) + '</span></label>'
            + (subs.length ? '<details class="mat-subs"' + (q || subSel || sel.has('a' + a.id) ? ' open' : '') + '><summary>' + subs.length + ' subactividad(es)</summary>'
              + subs.map(s => '<label class="mat-check mat-sub"><input type="checkbox" data-k="s' + s.id + '"' + (sel.has('s' + s.id) ? ' checked' : '') + '><span>' + esc(s.nombre) + '</span><small>' + esc(s.fecha_fin || '') + '</small></label>').join('')
              + '</details>' : '')
            + '</div>';
        }).join('') + '</details>';
    }).join('');
    if (!grupos.length) cont.innerHTML = '<p class="mat-vacio">No hay actividades que coincidan.</p>';
    cont.querySelectorAll('input[data-k]').forEach(chk => chk.addEventListener('change', e => {
      const k = e.target.dataset.k;
      if (!S.draft) S.draft = new Set();
      if (e.target.checked) S.draft.add(k); else S.draft.delete(k);
    }));
  }
  function aplicarPicker() {
    const sel = S.draft || new Set();
    const items = [...sel].map(itemDeClave).filter(Boolean);
    modificarMarca(t => { t.material.items = items; });
    cerrarPicker();
  }

  // ---------- API de la tabla ----------
  async function post(accion, extra) {
    const fd = new FormData();
    fd.append('proyecto', C.proyecto);
    fd.append('lamina', C.lamina);
    Object.entries(extra || {}).forEach(([k, v]) => fd.append(k, v));
    return fetch(M.api[accion], { method: 'POST', body: fd }).then(r => r.json());
  }
  function aplicarDatos(d) {
    if (!d || !d.success) return false;
    S.tabla = d.tabla || null;
    S.datos = d;
    M.tabla = d.tabla || null;
    refrescarPanel();
    return true;
  }
  async function cargarDatos() {
    if (!S.tabla) { refrescarPanel(); return; }
    const d = await post('datos');
    aplicarDatos(d);
  }
  async function crearTabla() {
    const v = V(), est = $('#mat-estado');
    if (!v) return;
    if (est) est.textContent = 'Guardando las marcas…';
    const ok = await v.guardar();
    if (ok === false) { if (est) est.textContent = 'No se pudieron guardar las marcas.'; return; }
    if (est) est.textContent = 'Creando la tabla…';
    const d = await post('crear');
    if (aplicarDatos(d)) { if (est) est.textContent = 'Tabla creada ✔'; }
    else if (est) est.textContent = 'Error: ' + ((d && d.error) || 'no se pudo crear');
  }
  async function sincronizar(refrescarBase) {
    const v = V(), est = $('#mat-estado');
    if (!v) return;
    if (est) est.textContent = 'Guardando las marcas…';
    const ok = await v.guardar();
    if (ok === false) { if (est) est.textContent = 'No se pudieron guardar las marcas.'; return; }
    if (est) est.textContent = 'Actualizando la tabla…';
    const d = await post('sincronizar', refrescarBase ? { refrescar: '1' } : {});
    if (aplicarDatos(d)) { if (est) est.textContent = 'Tabla actualizada ✔'; }
    else if (est) est.textContent = 'Error: ' + ((d && d.error) || 'no se pudo actualizar');
  }

  // ---------- eventos ----------
  function iniciar() {
    if (!V()) return;
    // Enlazar botones de la barra y del panel
    [['#mat-crear', crearTabla], ['#mat-crear-barra', crearTabla]].forEach(([sel, fn]) => { const b = $(sel); if (b) b.addEventListener('click', fn); });
    [['#mat-sincronizar', () => sincronizar(false)], ['#mat-sincronizar-barra', () => sincronizar(false)], ['#mat-refrescar', () => sincronizar(true)]].forEach(([sel, fn]) => { const b = $(sel); if (b) b.addEventListener('click', fn); });
    const buscar = $('#mat-buscar');
    if (buscar) buscar.addEventListener('input', () => { S.q = buscar.value; pintarTabla(); });
    // Propiedades
    const agr = $('#p-mat-agregar'); if (agr) agr.addEventListener('click', abrirPicker);
    const conv = $('#p-mat-convertir'); if (conv) conv.addEventListener('click', () => { modificarMarca(t => { t.material = { items: [] }; }); abrirPicker(); });
    const mostrar = $('#p-mat-mostrar'); if (mostrar) mostrar.addEventListener('change', () => modificarMarca(t => { t.mat_mostrar = mostrar.checked; }));
    const tam = $('#p-mat-tam'), tamN = $('#p-mat-tam-n');
    const aplicarTam = (val, commit) => {
      const d = marcaActual(); if (!d || !d.material) return;
      const n = Math.max(24, Math.min(400, Math.round(+val || 64)));
      tam.value = n; if (tamN) tamN.value = n;
      d.mat_tam = n;
      if (V().renderOverlays) V().renderOverlays();
      if (commit) V().dirty(true);
    };
    if (tam) tam.addEventListener('input', () => aplicarTam(tam.value, false));
    if (tam) tam.addEventListener('change', () => aplicarTam(tam.value, true));
    if (tamN) tamN.addEventListener('change', () => aplicarTam(tamN.value, true));
    const pos = $('#p-mat-pos'); if (pos) pos.addEventListener('change', () => modificarMarca(t => { t.mat_pos = pos.value; }));
    const col = $('#p-mat-color-estado'); if (col) col.addEventListener('change', () => modificarMarca(t => { t.mat_colorEstado = col.checked; }));
    // Modal
    const ok = $('#mat-modal-ok'); if (ok) ok.addEventListener('click', aplicarPicker);
    const cancel = $('#mat-modal-cancelar'); if (cancel) cancel.addEventListener('click', cerrarPicker);
    const ov = $('#ve-mat-modal');
    if (ov) {
      ov.addEventListener('click', e => { if (e.target === ov) cerrarPicker(); });
      document.addEventListener('keydown', e => { if (e.key === 'Escape' && ov.classList.contains('abierto')) cerrarPicker(); });
    }
    const mb = $('#mat-modal-buscar'); if (mb) mb.addEventListener('input', () => pintarPicker(mb.value));
    // «Más formas» del tab de materiales
    const bm = $('#mat-mas-formas'), pm = $('#mat-mas-panel');
    if (bm && pm) {
      const cerrar = () => { pm.hidden = true; bm.setAttribute('aria-expanded', 'false'); };
      bm.addEventListener('click', e => { e.stopPropagation(); pm.hidden = !pm.hidden; bm.setAttribute('aria-expanded', pm.hidden ? 'false' : 'true'); });
      pm.addEventListener('click', e => { if (e.target.closest('[data-tool]')) cerrar(); });
      document.addEventListener('click', e => { if (!pm.contains(e.target)) cerrar(); });
      document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrar(); });
    }
    // El núcleo avisa cuando se pinta o se elige otra marca
    document.addEventListener('vplan:panel', actualizarProps);
    let tRender = 0;
    document.addEventListener('vplan:render', () => {
      clearTimeout(tRender);
      tRender = setTimeout(() => { actualizarProps(); refrescarPanel(); cargarImagenes(); }, 80);
    });
    // Al abrir la pestaña Materiales, refrescar los datos de SheetFlow
    document.querySelectorAll('#ve-pestanas [data-pest], .ve-tabs [data-tab]').forEach(b => b.addEventListener('click', () => {
      if (b.dataset.pest === 'materiales' || b.dataset.tab === 'materiales') cargarDatos();
    }));

    refrescarPanel();
    actualizarProps();
    cargarImagenes();
    if (S.tabla) cargarDatos();
    // Vigilante de la tabla (por si se edita en SheetFlow en otra pestaña)
    setInterval(() => {
      const panel = document.querySelector('.ve-tab-panel[data-panel="materiales"]');
      if (!S.tabla || document.hidden || (panel && panel.hidden)) return;
      if (document.querySelector('input:focus, textarea:focus, select:focus')) return;
      cargarDatos();
    }, 20000);
    window.MaterialesVplan.activo = () => !!(document.querySelector('#ve-pestanas [data-pest].activo[data-pest="materiales"]'));
  }

  if (window.__vplan) iniciar();
  else document.addEventListener('vplan:listo', iniciar, { once: true });
})();
