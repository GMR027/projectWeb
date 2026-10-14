/* Editor de marcas de vPlan sobre el plano (Konva, JavaScript puro).
 * Diseño como el ejemplo oficial de Konva: el documento es una lista de datos planos con id (nunca nodos),
 * historial past/present/future y un Transformer para mover, redimensionar y rotar. */
(function () {
  'use strict';
  const C = window.VPLAN_CONFIG;
  // Mismos colores de estado que el programa de obra
  const COLORES_ESTADO = { completo: '#059669', proceso: '#d97706', espera: '#dc2626' };
  const $ = s => document.querySelector(s);
  const POLI = ['triangle', 'diamond', 'star', 'pentagon', 'hexagon', 'cross', 'xmark', 'check', 'blockarrow', 'chevron', 'trapezoid'];
  const RECT = ['rect', 'rrect', 'image'];
  const ELIPSE = ['ellipse', 'zone'];
  const LINEAS = ['line', 'arrow', 'darrow'];
  const CERRADAS = [...POLI, 'rect', 'rrect', 'ellipse', 'zone'];

  const state = { tool: 'select', shapes: [], selId: null, past: [], future: [], W: 0, H: 0, clip: null };
  let stage, layer, trLayer, tr, bg, nodes = {};
  let sw = 4, seq = 1, sucio = false;
  const imgCache = {};

  const uid = () => 's' + Date.now().toString(36) + (seq++);
  const clone = o => JSON.parse(JSON.stringify(o));
  const status = t => { $('#ve-estado').textContent = t; };
  function dirty(v) { sucio = v; $('#ve-guardar').classList.toggle('ve-pend', v); }

  // ---------- color con opacidad (porcentaje) ----------
  function rgba(hex, pct) {
    if (!hex) return undefined;
    const a = Math.max(0, Math.min(100, pct ?? 100)) / 100;
    if (!hex.startsWith('#')) return hex;
    const h = hex.length === 4 ? '#' + [...hex.slice(1)].map(c => c + c).join('') : hex;
    return `rgba(${parseInt(h.slice(1, 3), 16)},${parseInt(h.slice(3, 5), 16)},${parseInt(h.slice(5, 7), 16)},${a})`;
  }

  // ---------- historial ----------
  function commit(nuevas) {
    state.past.push(clone(state.shapes));
    if (state.past.length > 100) state.past.shift();
    state.future = [];
    state.shapes = nuevas || state.shapes;
    render(); dirty(true);
  }
  function undo() { if (!state.past.length) return; state.future.push(clone(state.shapes)); state.shapes = state.past.pop(); render(); dirty(true); }
  function redo() { if (!state.future.length) return; state.past.push(clone(state.shapes)); state.shapes = state.future.pop(); render(); dirty(true); }

  // ---------- geometría de polígonos en una caja w x h ----------
  function puntos(tipo, w, h) {
    const P = [];
    const el = (n, off, ri = 1) => { for (let i = 0; i < n; i++) { const a = (off + 360 / n * i) * Math.PI / 180; P.push(w / 2 + Math.cos(a) * w / 2 * ri, h / 2 + Math.sin(a) * h / 2 * ri); } };
    const u = arr => arr.forEach(([x, y]) => P.push(x * w, y * h));
    switch (tipo) {
      case 'triangle': u([[.5, 0], [1, 1], [0, 1]]); break;
      case 'diamond': u([[.5, 0], [1, .5], [.5, 1], [0, .5]]); break;
      case 'pentagon': el(5, -90); break;
      case 'hexagon': el(6, 0); break;
      case 'star': for (let i = 0; i < 10; i++) { const a = (-90 + 36 * i) * Math.PI / 180, r = i % 2 ? .4 : 1; P.push(w / 2 + Math.cos(a) * w / 2 * r, h / 2 + Math.sin(a) * h / 2 * r); } break;
      case 'cross': u([[1/3, 0], [2/3, 0], [2/3, 1/3], [1, 1/3], [1, 2/3], [2/3, 2/3], [2/3, 1], [1/3, 1], [1/3, 2/3], [0, 2/3], [0, 1/3], [1/3, 1/3]]); break;
      case 'xmark': { const c = [[1/3, 0], [2/3, 0], [2/3, 1/3], [1, 1/3], [1, 2/3], [2/3, 2/3], [2/3, 1], [1/3, 1], [1/3, 2/3], [0, 2/3], [0, 1/3], [1/3, 1/3]];
        const k = Math.SQRT1_2 * 1.06; u(c.map(([x, y]) => { const dx = x - .5, dy = y - .5; return [.5 + (dx - dy) * k, .5 + (dx + dy) * k]; })); break; }
      case 'check': u([[.04, .55], [.2, .4], [.4, .62], [.8, .08], [.96, .24], [.4, .94]]); break;
      case 'blockarrow': u([[0, .3], [.6, .3], [.6, 0], [1, .5], [.6, 1], [.6, .7], [0, .7]]); break;
      case 'chevron': u([[0, 0], [.6, 0], [1, .5], [.6, 1], [0, 1], [.4, .5]]); break;
      case 'trapezoid': u([[.2, 0], [.8, 0], [1, 1], [0, 1]]); break;
    }
    return P;
  }

  // ---------- nodos ----------
  const act = id => C.actividades.find(a => a.id === id);
  const colorLigado = d => { const a = act(d.actividad_id); return a ? COLORES_ESTADO[a.estado] : null; };
  function dashDe(d) {
    const g = d.strokeWidth || sw;
    return d.dash === 'dash' ? [g * 4, g * 3] : d.dash === 'dashdot' ? [g * 5, g * 2, g, g * 2] : d.dash === 'dot' ? [g, g * 2] : undefined;
  }
  function getImg(src) {
    const e = imgCache[src];
    if (e) return e.ok ? e.img : null;
    const img = new Image(); imgCache[src] = { img, ok: false };
    img.onload = () => { imgCache[src].ok = true; if (layer) render(); };
    img.src = src;
    return null;
  }
  function crearNodo(d) {
    const lig = colorLigado(d);
    const base = { id: d.id, x: d.x, y: d.y, rotation: d.rotation || 0, opacity: (d.opacity ?? 100) / 100, draggable: state.tool === 'select' };
    const esLinea = LINEAS.includes(d.type);
    const stroke = lig && (esLinea || CERRADAS.includes(d.type)) && !d.fill ? lig : (esLinea ? (lig || d.stroke) : d.stroke);
    const relleno = d.fill ? rgba(lig && d.type !== 'text' ? lig : d.fill, d.fillAlpha) : undefined;
    const trazo = { stroke, strokeWidth: d.strokeWidth, dash: dashDe(d), lineJoin: 'round' };
    let n;
    switch (d.type) {
      case 'line': n = new Konva.Line({ ...base, points: d.points, ...trazo, lineCap: d.round ? 'round' : 'butt', tension: d.tension || 0, hitStrokeWidth: Math.max(20, d.strokeWidth * 3) }); break;
      case 'arrow': case 'darrow':
        n = new Konva.Arrow({ ...base, points: d.points, ...trazo, fill: stroke, pointerLength: d.strokeWidth * 4, pointerWidth: d.strokeWidth * 4, pointerAtBeginning: d.type === 'darrow', hitStrokeWidth: Math.max(20, d.strokeWidth * 3) }); break;
      case 'rect': n = new Konva.Rect({ ...base, width: d.w, height: d.h, fill: relleno, ...trazo }); break;
      case 'rrect': n = new Konva.Rect({ ...base, width: d.w, height: d.h, fill: relleno, cornerRadius: Math.min(d.w, d.h) * 0.2, ...trazo }); break;
      case 'ellipse': n = new Konva.Ellipse({ ...base, radiusX: d.rx, radiusY: d.ry, fill: relleno, ...trazo }); break;
      case 'zone': n = new Konva.Ellipse({ ...base, radiusX: d.rx, radiusY: d.ry, fill: relleno, ...trazo }); break;
      case 'text': n = new Konva.Text({ ...base, text: d.text, fontSize: d.fontSize, fontFamily: 'Arial', fontStyle: d.bold ? 'bold' : 'normal', fill: lig || d.fill || '#000' }); break;
      case 'restr':
        n = new Konva.Group({ ...base });
        n.add(new Konva.Line({ points: [0, -d.size, d.size, 0, 0, d.size, -d.size, 0], closed: true, fill: '#dc2626', stroke: '#991b1b', strokeWidth: Math.max(1, d.size / 12) }));
        n.add(new Konva.Text({ text: String(d.n), fontSize: d.size * 0.95, fontStyle: 'bold', fill: '#fff', align: 'center', verticalAlign: 'middle', width: d.size * 2, height: d.size * 2, offsetX: d.size, offsetY: d.size }));
        break;
      case 'pin':
        n = new Konva.Group({ ...base });
        n.add(new Konva.Circle({ radius: d.size, fill: relleno || '#0070c0', stroke: d.stroke, strokeWidth: d.strokeWidth || 0 }));
        n.add(new Konva.Text({ text: String(d.n), fontSize: d.size * 1.05, fontStyle: 'bold', fill: d.textColor || '#fff', align: 'center', verticalAlign: 'middle', width: d.size * 2, height: d.size * 2, offsetX: d.size, offsetY: d.size }));
        break;
      case 'image': {
        const img = getImg(d.src);
        n = img ? new Konva.Image({ ...base, image: img, width: d.w, height: d.h })
                : new Konva.Rect({ ...base, width: d.w, height: d.h, fill: '#cfd6df', stroke: '#8b95a5', dash: [8, 6] });
        break;
      }
      default: n = new Konva.Line({ ...base, points: puntos(d.type, d.w, d.h), closed: true, fill: relleno, ...trazo });
    }
    n.setAttr('vid', d.id);
    return n;
  }

  function render() {
    layer.destroyChildren(); nodes = {};
    state.shapes.forEach(d => { const n = crearNodo(d); nodes[d.id] = n; layer.add(n); attach(n, d); });
    select(state.shapes.some(s => s.id === state.selId) ? state.selId : null);
    layer.batchDraw();
    $('#ve-contador').textContent = state.shapes.length + ' marca(s)';
  }

  // ---------- selección y transformación ----------
  function select(id) {
    state.selId = id;
    const n = id && nodes[id];
    const d = id && state.shapes.find(s => s.id === id);
    tr.nodes(n ? [n] : []);
    const esLinea = d && LINEAS.includes(d.type);
    tr.keepRatio(!!d && (d.type === 'image' || d.type === 'pin' || d.type === 'restr'));
    tr.enabledAnchors(esLinea ? ['middle-left', 'middle-right']
      : d && (d.type === 'image' || d.type === 'pin' || d.type === 'restr' || d.type === 'text') ? ['top-left', 'top-right', 'bottom-left', 'bottom-right']
      : ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'middle-left', 'middle-right', 'top-center', 'bottom-center']);
    trLayer.batchDraw();
    panel(d || null);
  }
  function attach(n, d) {
    n.on('mousedown touchstart', e => { if (state.tool === 'select') { e.cancelBubble = true; if (state.selId !== d.id) select(d.id); } });
    n.on('dragend', () => { const nuevas = clone(state.shapes); const t = nuevas.find(s => s.id === d.id); t.x = n.x(); t.y = n.y(); commit(nuevas); });
    n.on('transformend', () => {
      const nuevas = clone(state.shapes); const t = nuevas.find(s => s.id === d.id);
      const sx = n.scaleX(), sy = n.scaleY();
      t.x = n.x(); t.y = n.y(); t.rotation = n.rotation();
      if (POLI.includes(t.type) || RECT.includes(t.type)) { t.w = Math.max(4, t.w * sx); t.h = Math.max(4, t.h * sy); }
      else if (ELIPSE.includes(t.type)) { t.rx = Math.max(2, t.rx * sx); t.ry = Math.max(2, t.ry * sy); }
      else if (LINEAS.includes(t.type)) { t.points = t.points.map((v, i) => v * (i % 2 ? sy : sx)); }
      else if (t.type === 'text') { t.fontSize = Math.max(6, Math.round(t.fontSize * Math.max(sx, sy))); }
      else if (t.type === 'restr' || t.type === 'pin') { t.size = Math.max(8, t.size * Math.max(sx, sy)); }
      commit(nuevas);
    });
    if (d.type === 'text') n.on('dblclick dbltap', () => editarTexto(d.id));
  }
  function actualizar(id, cambios) {
    const nuevas = clone(state.shapes); const t = nuevas.find(s => s.id === id); if (!t) return;
    Object.assign(t, cambios); commit(nuevas);
  }

  // ---------- texto: textarea superpuesto ----------
  function editarTexto(id) {
    const n = nodes[id], d = state.shapes.find(s => s.id === id); if (!n || !d) return;
    const ta = document.createElement('textarea');
    const pos = n.getAbsolutePosition(), box = stage.container().getBoundingClientRect(), sc = stage.scaleX();
    Object.assign(ta.style, { position: 'fixed', left: box.left + pos.x + 'px', top: box.top + pos.y + 'px', fontSize: d.fontSize * sc + 'px', fontFamily: 'Arial', fontWeight: d.bold ? 'bold' : 'normal', lineHeight: 1.2,
      color: d.fill || '#000', background: 'rgba(255,255,255,.85)', border: '1px solid #0070c0', padding: '2px', margin: 0, minWidth: '120px', minHeight: d.fontSize * sc * 1.4 + 'px', zIndex: 10000, transformOrigin: 'left top', transform: 'rotate(' + (d.rotation || 0) + 'deg)' });
    ta.value = d.text; document.body.appendChild(ta); ta.focus(); ta.select();
    n.hide(); layer.batchDraw();
    let hecho = false;
    const fin = ok => { if (hecho) return; hecho = true; const v = ta.value; if (ta.parentNode) ta.remove(); n.show(); if (ok && v.trim() !== '' && v !== d.text) actualizar(id, { text: v }); else layer.batchDraw(); };
    ta.addEventListener('blur', () => fin(true));
    ta.addEventListener('keydown', e => { if (e.key === 'Escape') fin(false); if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); fin(true); } e.stopPropagation(); });
  }

  // ---------- panel de propiedades ----------
  function sincroniza(rango, num, valor) { $(rango).value = valor; $(num).value = valor; }
  function panel(d) {
    const p = $('#ve-props'); p.querySelector('.ve-vacio').style.display = d ? 'none' : '';
    p.querySelector('.ve-campos').style.display = d ? '' : 'none';
    if (!d) return;
    const mostrar = (sel, ok) => { const el = p.querySelector(sel); if (el) el.closest('.ve-f').style.display = ok ? '' : 'none'; };
    const conTrazo = !['text', 'restr', 'image'].includes(d.type);
    const cerrada = CERRADAS.includes(d.type);
    const conRelleno = cerrada || d.type === 'pin';
    $('#p-stroke').value = d.stroke || '#111111'; mostrar('#p-stroke', conTrazo);
    $('#p-fill').value = d.fill && d.fill.startsWith('#') ? d.fill : (d.type === 'pin' ? '#0070c0' : '#ffffff'); mostrar('#p-fill', conRelleno || d.type === 'text');
    $('#p-nofill').checked = cerrada && !d.fill; mostrar('#p-nofill', cerrada);
    sincroniza('#p-fa', '#p-fa-n', d.fillAlpha ?? 100); mostrar('#p-fa', conRelleno);
    $('#p-sw').value = d.strokeWidth || sw; mostrar('#p-sw', conTrazo);
    $('#p-dash').value = d.dash || 'solid'; mostrar('#p-dash', conTrazo && d.type !== 'pin');
    sincroniza('#p-op', '#p-op-n', d.opacity ?? 100);
    $('#p-fs').value = d.fontSize || 40; mostrar('#p-fs', d.type === 'text');
    $('#p-n').value = d.n || 1; mostrar('#p-n', d.type === 'restr' || d.type === 'pin');
    $('#p-act').value = d.actividad_id || ''; mostrar('#p-act', !['restr', 'image'].includes(d.type));
  }
  function enlazarPanel() {
    const cur = () => state.shapes.find(s => s.id === state.selId);
    const num = (v, lo, hi, def) => Math.min(hi, Math.max(lo, Number.isFinite(+v) && v !== '' ? +v : def));
    const on = (sel, ev, fn) => $(sel).addEventListener(ev, fn);
    on('#p-stroke', 'change', e => cur() && actualizar(state.selId, { stroke: e.target.value }));
    on('#p-fill', 'change', e => cur() && actualizar(state.selId, { fill: e.target.value }));
    on('#p-nofill', 'change', e => cur() && actualizar(state.selId, { fill: e.target.checked ? '' : $('#p-fill').value }));
    on('#p-sw', 'change', e => cur() && actualizar(state.selId, { strokeWidth: num(e.target.value, 1, 80, sw) }));
    on('#p-dash', 'change', e => cur() && actualizar(state.selId, { dash: e.target.value }));
    on('#p-fs', 'change', e => cur() && actualizar(state.selId, { fontSize: num(e.target.value, 6, 400, 40) }));
    on('#p-n', 'change', e => cur() && actualizar(state.selId, { n: num(e.target.value, 1, 99, 1) }));
    on('#p-act', 'change', e => cur() && actualizar(state.selId, { actividad_id: e.target.value ? +e.target.value : null }));
    // Opacidades en porcentaje: el deslizador se aplica en vivo y se guarda al soltar
    [['#p-op', '#p-op-n', 'opacity', 5], ['#p-fa', '#p-fa-n', 'fillAlpha', 0]].forEach(([r, n, campo, min]) => {
      on(r, 'input', e => { $(n).value = e.target.value; const nodo = nodes[state.selId], d = cur(); if (nodo && d) { if (campo === 'opacity') nodo.opacity(e.target.value / 100); else { const t = clone(d); t.fillAlpha = +e.target.value; const nuevo = crearNodo(t); nodo.fill && nuevo.fill && nodo.fill(nuevo.fill()); } layer.batchDraw(); } });
      on(r, 'change', e => cur() && actualizar(state.selId, { [campo]: num(e.target.value, min, 100, 100) }));
      on(n, 'change', e => { const v = num(e.target.value, min, 100, 100); $(r).value = v; cur() && actualizar(state.selId, { [campo]: v }); });
    });
    on('#b-front', 'click', () => mover(1)); on('#b-back', 'click', () => mover(-1));
    on('#b-dup', 'click', duplicar); on('#b-del', 'click', borrar);
  }
  function mover(dir) {
    const i = state.shapes.findIndex(s => s.id === state.selId); if (i < 0) return;
    const j = dir > 0 ? state.shapes.length - 1 : 0; if (i === j) return;
    const nuevas = clone(state.shapes); const [x] = nuevas.splice(i, 1); dir > 0 ? nuevas.push(x) : nuevas.unshift(x); commit(nuevas);
  }
  function borrar() { if (!state.selId) return; const id = state.selId; state.selId = null; commit(clone(state.shapes).filter(s => s.id !== id)); }
  function duplicar() { const d = state.shapes.find(s => s.id === state.selId); if (!d) return; const c = clone(d); c.id = uid(); c.x += 30; c.y += 30; state.selId = c.id; commit([...clone(state.shapes), c]); }
  function copiar() { const d = state.shapes.find(s => s.id === state.selId); if (d) state.clip = clone(d); }
  function pegar() { if (!state.clip) return; const c = clone(state.clip); c.id = uid(); c.x += 40; c.y += 40; state.clip = clone(c); state.selId = c.id; commit([...clone(state.shapes), c]); }

  // ---------- herramientas de dibujo ----------
  let dibujando = null, panning = null, poli = null;
  const posRel = () => layer.getAbsoluteTransform().copy().invert().point(stage.getPointerPosition());
  const estiloBase = () => ({ stroke: $('#t-stroke').value, strokeWidth: sw, dash: 'solid', opacity: 100 });
  const LINEA_TOOLS = { line: {}, dash: { dash: 'dash' }, dashdot: { dash: 'dashdot' }, dot: { dash: 'dot' }, arrow: { _t: 'arrow' }, darrow: { _t: 'darrow' } };
  function nuevoDato(tool, p) {
    const e = estiloBase(), id = uid();
    if (LINEA_TOOLS[tool]) { const x = LINEA_TOOLS[tool]; return { id, type: x._t || 'line', x: p.x, y: p.y, points: [0, 0, 0, 0], ...e, ...(x.dash ? { dash: x.dash } : {}) }; }
    if (tool === 'ellipse') return { id, type: 'ellipse', x: p.x, y: p.y, rx: 1, ry: 1, fill: '', fillAlpha: 100, ...e };
    if (tool === 'zone') return { id, type: 'zone', x: p.x, y: p.y, rx: 1, ry: 1, fill: '#ffeb00', fillAlpha: 35, ...e, stroke: '#c8b400' };
    return { id, type: tool, x: p.x, y: p.y, w: 1, h: 1, fill: '', fillAlpha: 100, ...e };   // rect, rrect y polígonos
  }
  const FORMAS = ['rect', 'rrect', 'ellipse', 'zone', ...POLI];

  function finalizarPoli() {
    if (!poli) return;
    const d = poli.d; poli.nodo.destroy();
    d.points = d.points.slice(0, -2);   // quita el punto temporal
    poli = null;
    if (d.points.length >= 4) { state.selId = d.id; commit([...clone(state.shapes), d]); setTool('select'); } else layer.batchDraw();
  }

  function configurarEventos() {
    stage.on('mousedown touchstart', e => {
      const esFondo = e.target === stage || e.target === bg;
      if (state.tool === 'select') {
        if (esFondo) { select(null); panning = { x: e.evt.clientX, y: e.evt.clientY, sx: stage.x(), sy: stage.y() }; }
        return;
      }
      if (['text', 'restr', 'pin', 'image'].includes(state.tool)) return;   // se crean en el clic
      const p = posRel();
      if (state.tool === 'poly') {
        if (!poli) {
          const d = { id: uid(), type: 'line', x: p.x, y: p.y, points: [0, 0, 0, 0], ...estiloBase(), round: true };
          poli = { d, nodo: crearNodo(d) }; layer.add(poli.nodo);
        } else {
          poli.d.points.push(p.x - poli.d.x, p.y - poli.d.y);   // fija el punto temporal y abre otro
        }
        return;
      }
      if (state.tool === 'pencil') {
        const d = { id: uid(), type: 'line', x: p.x, y: p.y, points: [0, 0], ...estiloBase(), round: true, tension: 0.35 };
        dibujando = { d, p0: p, nodo: crearNodo(d), libre: true }; layer.add(dibujando.nodo); return;
      }
      const d = nuevoDato(state.tool, p);
      dibujando = { d, p0: p, nodo: crearNodo(d) }; layer.add(dibujando.nodo);
    });
    stage.on('mousemove touchmove', e => {
      if (panning) { stage.position({ x: panning.sx + e.evt.clientX - panning.x, y: panning.sy + e.evt.clientY - panning.y }); return; }
      if (poli) {
        const p = posRel(), d = poli.d, n = d.points.length;
        if (n === 2) d.points.push(0, 0);
        d.points[d.points.length - 2] = p.x - d.x; d.points[d.points.length - 1] = p.y - d.y;
        poli.nodo.destroy(); poli.nodo = crearNodo(d); layer.add(poli.nodo); layer.batchDraw(); return;
      }
      if (!dibujando) return;
      const p = posRel(), { d, p0 } = dibujando; let dx = p.x - p0.x, dy = p.y - p0.y;
      if (dibujando.libre) {
        const ult = d.points.slice(-2), paso = 3 / stage.scaleX();
        if (Math.hypot(dx - ult[0], dy - ult[1]) < paso) return;
        d.points.push(dx, dy);
      } else if (LINEAS.includes(d.type)) {
        if (e.evt.shiftKey) { if (Math.abs(dx) > Math.abs(dy)) dy = 0; else dx = 0; }   // Mayús: recta horizontal/vertical
        d.points = [0, 0, dx, dy];
      } else if (ELIPSE.includes(d.type)) {
        d.x = p0.x + dx / 2; d.y = p0.y + dy / 2; d.rx = Math.abs(dx) / 2; d.ry = Math.abs(dy) / 2;
      } else { d.x = Math.min(p0.x, p.x); d.y = Math.min(p0.y, p.y); d.w = Math.abs(dx); d.h = Math.abs(dy); }
      dibujando.nodo.destroy(); dibujando.nodo = crearNodo(d); layer.add(dibujando.nodo); layer.batchDraw();
    });
    window.addEventListener('mouseup', () => {
      panning = null;
      if (!dibujando) return;
      const { d, nodo, libre } = dibujando; nodo.destroy(); dibujando = null;
      const dim = d.points ? (libre ? d.points.length : Math.hypot(d.points[2], d.points[3])) : Math.max(d.w || d.rx * 2 || 0, d.h || d.ry * 2 || 0);
      if (dim > (libre ? 4 : 6)) { state.selId = d.id; commit([...clone(state.shapes), d]); if (!libre) setTool('select'); } else layer.batchDraw();
    });
    stage.on('dblclick dbltap', () => { if (state.tool === 'poly') finalizarPoli(); });
    stage.on('click tap', e => {
      if (!['text', 'restr', 'pin'].includes(state.tool)) return;
      const p = posRel();
      if (state.tool === 'text') {
        const d = { id: uid(), type: 'text', x: p.x, y: p.y, text: 'Texto', fontSize: Math.round(sw * 10), fill: $('#t-stroke').value, bold: true, rotation: 0, opacity: 100 };
        state.selId = d.id; commit([...clone(state.shapes), d]); setTool('select'); setTimeout(() => editarTexto(d.id), 30); return;
      }
      const tipo = state.tool;
      const usados = state.shapes.filter(s => s.type === tipo).map(s => s.n);
      const n = (usados.length ? Math.max(...usados) : 0) + 1;
      const d = { id: uid(), type: tipo, x: p.x, y: p.y, size: sw * 5, n, rotation: 0, opacity: 100 };
      if (tipo === 'pin') Object.assign(d, { fill: '#0070c0', fillAlpha: 100, textColor: '#ffffff', stroke: '#ffffff', strokeWidth: Math.max(1, Math.round(sw / 2)) });
      state.selId = d.id; commit([...clone(state.shapes), d]); setTool('select');
    });
    stage.on('wheel', e => {
      e.evt.preventDefault();
      const old = stage.scaleX(), ptr = stage.getPointerPosition();
      const k = e.evt.deltaY < 0 ? 1.12 : 1 / 1.12, nuevo = Math.min(8, Math.max(0.05, old * k));
      const o = { x: (ptr.x - stage.x()) / old, y: (ptr.y - stage.y()) / old };
      stage.scale({ x: nuevo, y: nuevo }); stage.position({ x: ptr.x - o.x * nuevo, y: ptr.y - o.y * nuevo });
      $('#ve-zoom').textContent = Math.round(nuevo * 100) + '%';
    });
    document.addEventListener('keydown', e => {
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
      const k = e.key.toLowerCase();
      if (poli && (k === 'enter' || k === 'escape')) { e.preventDefault(); finalizarPoli(); return; }
      if ((e.ctrlKey || e.metaKey) && k === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
      else if ((e.ctrlKey || e.metaKey) && k === 'y') { e.preventDefault(); redo(); }
      else if ((e.ctrlKey || e.metaKey) && k === 'c') copiar();
      else if ((e.ctrlKey || e.metaKey) && k === 'v') pegar();
      else if ((e.ctrlKey || e.metaKey) && k === 'd') { e.preventDefault(); duplicar(); }
      else if ((e.ctrlKey || e.metaKey) && k === 's') { e.preventDefault(); guardar(); }
      else if (k === 'delete' || k === 'backspace') { e.preventDefault(); borrar(); }
      else if (k === 'escape') { setTool('select'); select(null); }
      else if (k.startsWith('arrow') && state.selId) {
        e.preventDefault(); const paso = e.shiftKey ? 10 : 1, d = state.shapes.find(s => s.id === state.selId);
        actualizar(state.selId, { x: d.x + (k === 'arrowleft' ? -paso : k === 'arrowright' ? paso : 0), y: d.y + (k === 'arrowup' ? -paso : k === 'arrowdown' ? paso : 0) });
      }
    });
  }

  function setTool(t) {
    if (t === 'image') { $('#ve-archivo').click(); return; }
    if (poli) finalizarPoli();
    state.tool = t;
    document.querySelectorAll('[data-tool]').forEach(b => b.classList.toggle('activo', b.dataset.tool === t));
    Object.values(nodes).forEach(n => n.draggable(t === 'select'));
    stage.container().style.cursor = t === 'select' ? 'default' : 'crosshair';
    if (t !== 'select') { tr.nodes([]); trLayer.batchDraw(); }
  }

  // ---------- imágenes dentro del editor ----------
  async function subirImagen(file) {
    if (!file) return;
    status('Subiendo imagen…');
    const fd = new FormData(); fd.append('lamina', C.lamina); fd.append('imagen', file);
    try {
      const r = await fetch('/api/vplan/imagen', { method: 'POST', body: fd });
      const d = await r.json();
      if (!d.success) { status('Error: ' + (d.error || 'no se pudo subir')); return; }
      const img = new Image();
      img.onload = () => {
        imgCache[d.src] = { img, ok: true };
        const max = Math.min(state.W, state.H) * 0.3, k = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
        const w = img.naturalWidth * k, h = img.naturalHeight * k;
        const c = layer.getAbsoluteTransform().copy().invert().point({ x: stage.width() / 2, y: stage.height() / 2 });
        const sh = { id: uid(), type: 'image', src: d.src, x: c.x - w / 2, y: c.y - h / 2, w, h, rotation: 0, opacity: 100 };
        state.selId = sh.id; commit([...clone(state.shapes), sh]); setTool('select'); status('');
      };
      img.src = d.src;
    } catch (e) { status('Error al subir la imagen'); }
  }

  // ---------- zoom / encuadre ----------
  function encuadrar() {
    const c = $('#ve-lienzo'), w = c.clientWidth, h = c.clientHeight;
    const k = Math.min(w / state.W, h / state.H);
    stage.size({ width: w, height: h }); stage.scale({ x: k, y: k });
    stage.position({ x: (w - state.W * k) / 2, y: (h - state.H * k) / 2 });
    $('#ve-zoom').textContent = Math.round(k * 100) + '%';
  }

  // ---------- guardado ----------
  async function exportarPng() {
    await Promise.all(state.shapes.filter(s => s.type === 'image').map(s => new Promise(res => {
      const e = imgCache[s.src]; if (e && e.ok) return res();
      const img = new Image(); img.onload = () => { imgCache[s.src] = { img, ok: true }; res(); }; img.onerror = res; img.src = s.src;
    })));
    const cont = document.createElement('div'); cont.style.cssText = 'position:fixed;left:-99999px;top:0';
    document.body.appendChild(cont);
    const st = new Konva.Stage({ container: cont, width: state.W, height: state.H });
    const ly = new Konva.Layer(); st.add(ly);
    ly.add(new Konva.Rect({ width: state.W, height: state.H, fill: '#ffffff' }));
    ly.add(new Konva.Image({ image: bg.image(), width: state.W, height: state.H }));
    state.shapes.forEach(d => { const n = crearNodo(d); n.draggable(false); ly.add(n); });
    ly.draw();
    const url = st.toDataURL({ mimeType: 'image/jpeg', quality: 0.92, pixelRatio: 1 });
    st.destroy(); cont.remove();
    return url;
  }
  async function guardar() {
    status('Guardando…');
    try {
      const png = await exportarPng();
      const r = await fetch('/api/vplan/marcas', { method: 'POST', body: new URLSearchParams({ lamina: C.lamina, json: JSON.stringify({ v: 2, w: state.W, h: state.H, shapes: state.shapes }), png }) });
      const d = await r.json();
      if (!d.success) { status('Error: ' + (d.error || 'no se pudo guardar')); return false; }
      dirty(false); status('Guardado ✔'); return true;
    } catch (e) { status('Error al guardar'); return false; }
  }

  // ---------- arranque ----------
  function iniciar() {
    const img = new Image();
    img.onload = () => {
      state.W = img.naturalWidth; state.H = img.naturalHeight; sw = Math.max(3, Math.round(state.W / 400));
      $('#t-sw').value = sw;
      stage = new Konva.Stage({ container: 'ek-stage', width: 800, height: 600 });
      const lbg = new Konva.Layer(); stage.add(lbg);
      bg = new Konva.Image({ image: img, width: state.W, height: state.H }); lbg.add(bg);
      layer = new Konva.Layer(); stage.add(layer);
      trLayer = new Konva.Layer(); stage.add(trLayer);
      tr = new Konva.Transformer({ rotateEnabled: true, rotationSnaps: [0, 45, 90, 135, 180, 225, 270, 315], borderStroke: '#0070c0', anchorStroke: '#0070c0', anchorSize: 10, keepRatio: false });
      trLayer.add(tr);
      state.shapes = (C.marcas && C.marcas.shapes) ? C.marcas.shapes : [];
      configurarEventos(); enlazarPanel(); encuadrar(); render(); dirty(false);
      window.addEventListener('resize', encuadrar);
      window.__vplan = { state, guardar, setTool, nodes: () => nodes };   // para pruebas
      if (C.auto) guardar().then(ok => { if (ok) location.href = C.volver; });
    };
    img.src = C.plano;
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tool]').forEach(b => b.addEventListener('click', () => setTool(b.dataset.tool)));
    $('#ve-archivo').addEventListener('change', e => { subirImagen(e.target.files[0]); e.target.value = ''; });
    $('#t-sw').addEventListener('change', e => { sw = Math.max(1, +e.target.value || sw); });
    $('#ve-guardar').addEventListener('click', guardar);
    $('#ve-undo').addEventListener('click', undo); $('#ve-redo').addEventListener('click', redo);
    $('#ve-fit').addEventListener('click', encuadrar);
    // Al salir con cambios sin guardar: modal de la aplicación (el aviso del navegador queda solo para cerrar la pestaña)
    document.addEventListener('click', async e => {
      const a = e.target.closest('a[href]');
      if (!a || !sucio || a.target === '_blank') return;
      e.preventDefault();
      const ok = await window.confirmarModal({ titulo: '¿Salir sin guardar?', mensaje: 'Tienes cambios sin guardar en las marcas del plano. Si sales ahora se perderán.', aceptar: 'Salir sin guardar', iconoBoton: 'fa-right-from-bracket' });
      if (ok) { sucio = false; location.href = a.href; }
    });
    window.addEventListener('beforeunload', e => { if (sucio) { e.preventDefault(); e.returnValue = ''; } });
    iniciar();
  });
})();
