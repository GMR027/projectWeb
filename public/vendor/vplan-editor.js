/* Editor de marcas de vPlan sobre el plano (Konva, JavaScript puro).
 * Diseño como el ejemplo oficial de Konva: el documento es una lista de datos planos con id (nunca nodos),
 * historial past/present/future y un Transformer para mover, redimensionar y rotar. */
(function () {
  'use strict';
  const C = window.VPLAN_CONFIG;
  // Mismos colores de estado que el programa de obra
  const COLORES_ESTADO = { completo: '#059669', proceso: '#d97706', espera: '#dc2626' };
  const $ = s => document.querySelector(s);
  const POLI = ['triangle', 'diamond', 'star', 'pentagon', 'hexagon', 'cross', 'xmark', 'check', 'blockarrow', 'chevron', 'trapezoid',
    'octagon', 'parallelogram', 'rtriangle', 'house', 'arrowleft', 'arrowup', 'arrowdown', 'arrowlr', 'homeplate', 'star4', 'star6', 'star8', 'heart', 'moon', 'semicircle', 'bolt', 'drop', 'lshape', 'tshape', 'ushape', 'callout', 'flag'];
  const RECT = ['rect', 'rrect', 'image', 'link'];
  const ICONOS_LINK = { link: '🔗', globe: '🌐', map: '📍', doc: '📄', photo: '📷', info: 'i', star: '★', flag: '🚩', video: '▶' };
  let enExport = false, pend360 = null, cambiar360 = null;   // pend360: foto ya subida que espera el área; cambiar360: id del enlace cuya foto se reemplaza
  const ELIPSE = ['ellipse', 'zone'];
  const LINEAS = ['line', 'arrow', 'darrow', 'measure'];
  const CERRADAS = [...POLI, 'rect', 'rrect', 'ellipse', 'zone'];

  // selId = marca elegida cuando hay una sola; sel = todas las elegidas (en el orden en que se eligieron)
  const modoSel = () => state.tool === 'select' || state.tool === 'multi';   // «multi»: recuadro de selección sin Mayús
  const state = { tool: 'select', shapes: [], layers: [], selId: null, sel: [], past: [], future: [], W: 0, H: 0, clip: null, escala: null, slides: null, idx: 0 };
  const UNIDADES = { mm: 1, cm: 10, m: 1000, in: 25.4 };   // milímetros que vale cada unidad
  const FUENTES = ['Arial', 'Verdana', 'Trebuchet MS', 'Georgia', 'Times New Roman', 'Courier New', 'Impact'];
  let capaActiva = null;
  let stage, layer, trLayer, tr, bg, nodes = {};
  let sw = 4, seq = 1, sucio = false;
  const imgCache = {};

  // Estilo del texto: negrita, cursiva, subrayado y tachado (también en tablas); `neg` fuerza negrita (encabezados)
  const famDe = d => d.fontFamily || 'Arial';
  const estiloDe = (d, neg) => [d.italic ? 'italic' : '', neg || d.bold ? 'bold' : ''].filter(Boolean).join(' ') || 'normal';
  const decoDe = d => [d.underline ? 'underline' : '', d.strike ? 'line-through' : ''].filter(Boolean).join(' ');
  const cssTexto = (d, neg) => ({ fontFamily: famDe(d), fontStyle: d.italic ? 'italic' : 'normal', fontWeight: neg || d.bold ? 'bold' : 'normal', textDecoration: decoDe(d) || 'none' });
  const uid = () => 's' + Date.now().toString(36) + (seq++);
  const clone = o => JSON.parse(JSON.stringify(o));
  const status = t => { $('#ve-estado').textContent = t; };
  function dirty(v) { sucio = v; $('#ve-guardar').classList.toggle('ve-pend', v); if (v && state.slides && state.slides[state.idx]) { state.slides[state.idx].dirty = true; programarMiniatura(); } if (v) programarAuto(); }

  // ---------- color con opacidad (porcentaje) ----------
  function rgba(hex, pct) {
    if (!hex) return undefined;
    const a = Math.max(0, Math.min(100, pct ?? 100)) / 100;
    if (!hex.startsWith('#')) return hex;
    const h = hex.length === 4 ? '#' + [...hex.slice(1)].map(c => c + c).join('') : hex;
    return `rgba(${parseInt(h.slice(1, 3), 16)},${parseInt(h.slice(3, 5), 16)},${parseInt(h.slice(5, 7), 16)},${a})`;
  }

  // ---------- capas ----------
  // state.layers va de la más baja a la más alta; cada marca guarda el id de su capa en `layer`.
  const capaPor = id => state.layers.find(l => l.id === id);
  const idxCapa = id => Math.max(0, state.layers.findIndex(l => l.id === id));
  const nombreCapa = l => (l.name && l.name.trim()) || ('Capa ' + l.num);
  const nuevaCapa = (num) => ({ id: 'c' + Date.now().toString(36) + (seq++), name: '', visible: true, locked: false, num });
  const siguienteNumCapa = () => state.layers.reduce((m, l) => Math.max(m, l.num || 0), 0) + 1;
  function asegurarCapas() {
    if (!state.layers.length) state.layers = [nuevaCapa(1)];
    state.layers.forEach((l, i) => { if (!l.num) l.num = i + 1; if (l.visible === undefined) l.visible = true; });
    const primera = state.layers[0].id;
    state.shapes.forEach(sh => { if (!capaPor(sh.layer)) sh.layer = primera; });
    if (!capaPor(capaActiva)) capaActiva = state.layers[state.layers.length - 1].id;
  }
  // Marcas que se dibujan (capas visibles), de la capa más baja a la más alta
  const ordenar = (shapes, layers, soloVisibles = true) => {
    const cp = id => layers.find(l => l.id === id), ix = id => Math.max(0, layers.findIndex(l => l.id === id));
    return [...shapes].filter(sh => !soloVisibles || (cp(sh.layer) || {}).visible !== false).sort((a, b) => ix(a.layer) - ix(b.layer));
  };
  const marcasOrdenadas = (soloVisibles = true) => ordenar(state.shapes, state.layers, soloVisibles);
  function capaOk() {
    const c = capaPor(capaActiva);
    if (!c) return false;
    if (!c.visible) { status('La capa activa «' + nombreCapa(c) + '» está oculta'); return false; }
    if (c.locked) { status('La capa activa «' + nombreCapa(c) + '» está bloqueada'); return false; }
    return true;
  }

  // ---------- historial ----------
  const snap = () => ({ s: clone(state.shapes), l: clone(state.layers), e: clone(state.escala), g: clone(state.guides || []) });
  function commit(nuevas, capas, escala) {
    state.past.push(snap());
    if (state.past.length > 100) state.past.shift();
    state.future = [];
    state.shapes = nuevas || state.shapes;
    if (capas) state.layers = capas;
    if (escala !== undefined) state.escala = escala;
    state.shapes.forEach(sh => { if (!sh.layer) sh.layer = capaActiva; });   // lo que se dibuja cae en la capa activa
    asegurarCapas();
    render(); dirty(true);
  }
  function restaurar(e) { state.shapes = e.s; state.layers = e.l; state.escala = e.e === undefined ? state.escala : e.e; if (e.g) state.guides = e.g; asegurarCapas(); render(); pintarGuias(); dirty(true); }
  function undo() { if (!state.past.length) return; state.future.push(snap()); restaurar(state.past.pop()); }
  function redo() { if (!state.future.length) return; state.past.push(snap()); restaurar(state.future.pop()); }

  // ---------- geometría de polígonos en una caja w x h ----------
  function puntos(tipo, w, h) {
    const P = [];
    const el = (n, off, ri = 1) => { for (let i = 0; i < n; i++) { const a = (off + 360 / n * i) * Math.PI / 180; P.push(w / 2 + Math.cos(a) * w / 2 * ri, h / 2 + Math.sin(a) * h / 2 * ri); } };
    const u = arr => arr.forEach(([x, y]) => P.push(x * w, y * h));
    const estrella = (n, ri) => { for (let i = 0; i < 2 * n; i++) { const a = (-90 + 180 / n * i) * Math.PI / 180, r = i % 2 ? ri : 1; P.push(w / 2 + Math.cos(a) * w / 2 * r, h / 2 + Math.sin(a) * h / 2 * r); } };
    const ajustarCaja = arr => {   // escala una curva muestreada para que llene la caja w x h
      const xs = arr.map(q => q[0]), ys = arr.map(q => q[1]), x0 = Math.min(...xs), y0 = Math.min(...ys), dx = Math.max(...xs) - x0 || 1, dy = Math.max(...ys) - y0 || 1;
      arr.forEach(([x, y]) => P.push((x - x0) / dx * w, (y - y0) / dy * h));
    };
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
      case 'octagon': el(8, 22.5); break;
      case 'parallelogram': u([[.25, 0], [1, 0], [.75, 1], [0, 1]]); break;
      case 'rtriangle': u([[0, 0], [0, 1], [1, 1]]); break;
      case 'house': u([[.5, 0], [1, .35], [1, 1], [0, 1], [0, .35]]); break;
      case 'arrowleft': u([[1, .3], [.4, .3], [.4, 0], [0, .5], [.4, 1], [.4, .7], [1, .7]]); break;
      case 'arrowup': u([[.3, 1], [.3, .4], [0, .4], [.5, 0], [1, .4], [.7, .4], [.7, 1]]); break;
      case 'arrowdown': u([[.3, 0], [.3, .6], [0, .6], [.5, 1], [1, .6], [.7, .6], [.7, 0]]); break;
      case 'arrowlr': u([[0, .5], [.25, 0], [.25, .3], [.75, .3], [.75, 0], [1, .5], [.75, 1], [.75, .7], [.25, .7], [.25, 1]]); break;
      case 'homeplate': u([[0, 0], [.7, 0], [1, .5], [.7, 1], [0, 1]]); break;
      case 'star4': estrella(4, .38); break;
      case 'star6': estrella(6, .55); break;
      case 'star8': estrella(8, .62); break;
      case 'heart': ajustarCaja(Array.from({ length: 72 }, (_, i) => { const t = i / 72 * 2 * Math.PI; return [16 * Math.sin(t) ** 3, -(13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t))]; })); break;
      case 'moon': {
        const pts = [];
        for (let a = 70; a <= 290; a += 10) pts.push([.5 + .5 * Math.cos(a * Math.PI / 180), .5 + .5 * Math.sin(a * Math.PI / 180)]);
        for (let a = 249.2; a >= 110.8; a -= 10) pts.push([.85 + .503 * Math.cos(a * Math.PI / 180), .5 + .503 * Math.sin(a * Math.PI / 180)]);
        ajustarCaja(pts); break;
      }
      case 'semicircle': ajustarCaja(Array.from({ length: 25 }, (_, i) => { const a = (180 + 180 / 24 * i) * Math.PI / 180; return [Math.cos(a), Math.sin(a)]; })); break;
      case 'bolt': u([[.6, 0], [.15, .55], [.45, .55], [.35, 1], [.85, .4], [.55, .4]]); break;
      case 'drop': {
        const pts = [[.5, 0]];
        for (let a = -32.6; a <= 212.6; a += 8) pts.push([.5 + .35 * Math.cos(a * Math.PI / 180), .65 + .35 * Math.sin(a * Math.PI / 180)]);
        ajustarCaja(pts); break;
      }
      case 'lshape': u([[0, 0], [.4, 0], [.4, .6], [1, .6], [1, 1], [0, 1]]); break;
      case 'tshape': u([[0, 0], [1, 0], [1, .35], [.65, .35], [.65, 1], [.35, 1], [.35, .35], [0, .35]]); break;
      case 'ushape': u([[0, 0], [.35, 0], [.35, .65], [.65, .65], [.65, 0], [1, 0], [1, 1], [0, 1]]); break;
      case 'callout': u([[0, 0], [1, 0], [1, .7], [.55, .7], [.3, 1], [.35, .7], [0, .7]]); break;
      case 'flag': u([[0, 0], [.12, 0], [.12, .08], [1, .08], [.75, .35], [1, .62], [.12, .62], [.12, 1], [0, 1]]); break;
    }
    return P;
  }

  // ---------- tablas (grupo de celdas) ----------
  // Celdas seleccionadas de la tabla activa (no forma parte del historial): claves «fila,columna»
  let celdasSel = { id: null, set: new Set() };
  const claveCelda = (r, c) => r + ',' + c;
  const ultimosClics = [];
  function resaltar(n, d) {
    n.find('.resalte').forEach(x => x.destroy());
    if (celdasSel.id !== d.id) return;
    const g = geomTabla(d);
    celdasSel.set.forEach(k => {
      const [r, c] = k.split(',').map(Number);
      if (r >= g.rows || c >= g.cols) return;
      n.add(new Konva.Rect({ name: 'resalte', x: suma(g.cw, c), y: suma(g.rh, r), width: g.cw[c], height: g.rh[r], fill: 'rgba(255,90,31,.18)', stroke: '#ff5a1f', strokeWidth: Math.max(2, d.strokeWidth * 1.5), dash: [8, 5], listening: false }));
    });
  }
  function refrescarCeldas() {
    const d = state.shapes.find(s => s.id === celdasSel.id), n = d && nodes[d.id];
    if (!n) return;
    resaltar(n, d); layer.batchDraw(); panel(d);
  }
  function elegirCeldas(id, claves) { celdasSel = { id, set: new Set(claves) }; refrescarCeldas(); }

  const suma = (arr, hasta) => arr.slice(0, hasta).reduce((a, b) => a + b, 0);
  function geomTabla(d) {
    const cols = d.cols || 3, rows = d.rows || 3;
    const cw = d.colW && d.colW.length === cols ? d.colW : Array(cols).fill((d.w || 1) / cols);
    const rh = d.rowH && d.rowH.length === rows ? d.rowH : Array(rows).fill((d.h || 1) / rows);
    return { cols, rows, cw, rh };
  }
  const pad = d => Math.max(2, d.fontSize * 0.25);
  // Fija las medidas de columnas y filas: con un clic, tamaño por defecto; arrastrando, el área dibujada
  function normalizarTabla(d) {
    const fs = d.fontSize, filaMin = fs * 2.2;
    const ancho = d.w > 6 || d.h > 6 ? d.w : d.cols * fs * 7, alto = d.w > 6 || d.h > 6 ? d.h : d.rows * filaMin;
    d.colW = Array(d.cols).fill(ancho / d.cols);
    d.rowH = Array(d.rows).fill(Math.max(filaMin, alto / d.rows));
    d.cells = Array.from({ length: d.rows }, () => Array(d.cols).fill(''));
    d.w = suma(d.colW); d.h = suma(d.rowH);
  }
  function cambiarTamTabla(d, rows, cols) {
    const g = geomTabla(d);
    d.cells = Array.from({ length: rows }, (_, r) => Array.from({ length: cols }, (_, c) => (d.cells && d.cells[r] && d.cells[r][c]) || ''));
    d.colW = Array.from({ length: cols }, (_, c) => g.cw[c] ?? g.cw[g.cw.length - 1]);
    d.rowH = Array.from({ length: rows }, (_, r) => g.rh[r] ?? g.rh[g.rh.length - 1]);
    d.rows = rows; d.cols = cols; d.w = suma(d.colW); d.h = suma(d.rowH);
    ['cellFill', 'cellText'].forEach(m => { if (d[m]) Object.keys(d[m]).forEach(k => { const [r, c] = k.split(',').map(Number); if (r >= rows || c >= cols) delete d[m][k]; }); });
  }
  // Las filas crecen para que el texto de sus celdas quepa completo
  function ajustarFilas(d) {
    const g = geomTabla(d), p = pad(d);
    d.rowH = g.rh.map((alto, r) => {
      let necesito = d.fontSize * 1.6;
      for (let c = 0; c < g.cols; c++) {
        const txt = d.cells && d.cells[r] && d.cells[r][c];
        if (!txt) continue;
        const m = new Konva.Text({ text: txt, width: Math.max(10, g.cw[c] - 2 * p), fontSize: d.fontSize, fontFamily: famDe(d), fontStyle: estiloDe(d, d.header && r === 0), textDecoration: decoDe(d), wrap: 'word' });
        necesito = Math.max(necesito, m.height() + 2 * p);
      }
      return Math.max(alto, necesito);
    });
    d.h = suma(d.rowH);
  }
  function celdaEn(n, d) {
    const q = n.getAbsoluteTransform().copy().invert().point(stage.getPointerPosition()), g = geomTabla(d);
    let c = -1, r = -1, x = 0, y = 0;
    for (let i = 0; i < g.cols; i++) { if (q.x >= x && q.x < x + g.cw[i]) { c = i; break; } x += g.cw[i]; }
    for (let i = 0; i < g.rows; i++) { if (q.y >= y && q.y < y + g.rh[i]) { r = i; break; } y += g.rh[i]; }
    return c >= 0 && r >= 0 ? { r, c } : null;
  }

  // ---------- nodos ----------
  const act = id => C.actividades.find(a => a.id === id);
  const colorLigado = d => { const a = act(d.actividad_id); return a ? COLORES_ESTADO[a.estado] : null; };
  // Largo del trazo y separación, en múltiplos del grosor (se pueden cambiar en Propiedades)
  const DASH_BASE = { dash: [4, 3], dashdot: [5, 2], dot: [1, 2] };
  const dashMedidas = d => { const b = DASH_BASE[d.dash] || [4, 3]; return [Math.max(0.2, +d.dashLen || b[0]), Math.max(0.2, +d.dashGap || b[1])]; };
  function dashDe(d) {
    const g = d.strokeWidth || sw;
    if (!DASH_BASE[d.dash]) return undefined;
    const [l, s] = dashMedidas(d), min = 0.5;
    return d.dash === 'dashdot' ? [g * l, g * s, g * Math.max(min, l / 5), g * s] : [g * l, g * s];
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
    const base = { id: d.id, x: d.x, y: d.y, rotation: d.rotation || 0, opacity: (d.opacity ?? 100) / 100, draggable: modoSel() };
    const esLinea = LINEAS.includes(d.type);
    const stroke = lig && d.type !== 'measure' && (esLinea || CERRADAS.includes(d.type)) && !d.fill ? lig : (esLinea ? (lig || d.stroke) : d.stroke);
    const relleno = d.fill ? rgba(lig && d.type !== 'text' ? lig : d.fill, d.fillAlpha) : undefined;
    const trazo = { stroke, strokeWidth: d.strokeWidth, dash: dashDe(d), lineJoin: 'round' };
    let n;
    switch (d.type) {
      case 'line': n = new Konva.Line({ ...base, points: d.points, ...trazo, lineCap: d.round ? 'round' : 'butt', tension: d.tension || 0, hitStrokeWidth: Math.max(20, d.strokeWidth * 3) }); break;
      case 'arrow': case 'darrow':
        n = new Konva.Arrow({ ...base, points: d.points, ...trazo, fill: stroke, pointerLength: d.strokeWidth * 4, pointerWidth: d.strokeWidth * 4, pointerAtBeginning: d.type === 'darrow', hitStrokeWidth: Math.max(20, d.strokeWidth * 3) }); break;
      case 'rect': n = new Konva.Rect({ ...base, width: d.w, height: d.h, fill: relleno, ...trazo }); break;
      case 'rrect': n = new Konva.Rect({ ...base, width: d.w, height: d.h, fill: relleno, cornerRadius: Math.min(d.w, d.h) * 0.2, ...trazo }); break;
      case 'link': {   // área clicable: se ve al editar; el icono solo sale en las imágenes exportadas si d.showIcon
        n = new Konva.Group({ ...base, rotation: 0 });
        if (!enExport) n.add(new Konva.Rect({ width: d.w, height: d.h, fill: 'rgba(0,112,192,.16)', stroke: d.iconColor || '#0070c0', strokeWidth: 2, dash: [10, 6], strokeScaleEnabled: false }));
        if (!enExport && d.label) {   // rótulo mientras se edita
          const t = new Konva.Text({ text: d.label, fontSize: 22, fontStyle: 'bold', fill: '#ffffff', padding: 6, listening: false });
          n.add(new Konva.Rect({ x: 0, y: d.h + 6, width: t.width(), height: t.height(), fill: 'rgba(11,19,43,.88)', cornerRadius: 12, listening: false }));
          t.position({ x: 0, y: d.h + 6 }); n.add(t);
        }
        const ic = d.icon || 'link';
        if (ic !== 'none' && (!enExport || d.showIcon)) {
          const r = d.isize || Math.max(10, Math.min(22, Math.min(d.w, d.h) * 0.35)), col = d.iconColor || '#0070c0';
          const glifo = ic === 'num' ? String(d.n || 1) : ICONOS_LINK[ic] || '🔗';
          n.add(new Konva.Circle({ x: r + 3, y: r + 3, radius: r, fill: col, stroke: '#ffffff', strokeWidth: Math.max(1, r * 0.1), listening: false }));
          n.add(new Konva.Text({ x: 3, y: 3, width: r * 2, height: r * 2, text: glifo, fontSize: ic === 'num' ? r * 1.1 : r * 1.15, fontStyle: ic === 'num' || ic === 'info' ? 'bold' : 'normal', fill: '#ffffff', align: 'center', verticalAlign: 'middle', listening: false }));
        }
        break;
      }
      case 'ellipse': n = new Konva.Ellipse({ ...base, radiusX: d.rx, radiusY: d.ry, fill: relleno, ...trazo }); break;
      case 'zone': n = new Konva.Ellipse({ ...base, radiusX: d.rx, radiusY: d.ry, fill: relleno, ...trazo }); break;
      case 'text': {
        const props = { text: d.text, fontSize: d.fontSize, fontFamily: famDe(d), fontStyle: estiloDe(d), textDecoration: decoDe(d), align: d.align || 'left', fill: lig || d.fill || '#000' };
        if (!d.bg) { n = new Konva.Text({ ...base, ...props }); break; }
        // Con fondo: rectángulo del tamaño del texto (más un margen) detrás del texto
        const t = new Konva.Text(props), m = Math.max(3, d.fontSize * 0.3);
        n = new Konva.Group({ ...base });
        n.add(new Konva.Rect({ x: -m, y: -m, width: t.width() + 2 * m, height: t.height() + 2 * m, fill: rgba(d.bg, d.fillAlpha), cornerRadius: m * 0.6 }));
        n.add(t);
        break;
      }
      case 'measure': {
        const [x1, y1, x2, y2] = d.points, len = Math.hypot(x2 - x1, y2 - y1), ang = Math.atan2(y2 - y1, x2 - x1);
        const fs = d.fontSize || 24, tick = Math.max(6, fs * 0.5), grosor = d.strokeWidth || 3;
        n = new Konva.Group({ ...base });
        n.add(new Konva.Line({ points: [x1, y1, x2, y2], stroke: d.stroke, strokeWidth: grosor, dash: dashDe(d), hitStrokeWidth: Math.max(20, grosor * 3) }));
        const nx = -Math.sin(ang) * tick, ny = Math.cos(ang) * tick;
        [[x1, y1], [x2, y2]].forEach(([px, py]) => n.add(new Konva.Line({ points: [px - nx, py - ny, px + nx, py + ny], stroke: d.stroke, strokeWidth: grosor })));
        const t = new Konva.Text({ text: textoMedida(d, len), fontSize: fs, fontFamily: famDe(d), fontStyle: estiloDe(d, true), textDecoration: decoDe(d), fill: d.stroke, stroke: '#ffffff', strokeWidth: Math.max(2, fs * 0.16), fillAfterStrokeEnabled: true, listening: false });
        let grados = ang * 180 / Math.PI; if (grados > 90 || grados <= -90) grados += 180;   // el texto siempre se lee derecho
        t.offsetX(t.width() / 2); t.offsetY(t.height() + grosor + 2); t.position({ x: (x1 + x2) / 2, y: (y1 + y2) / 2 }); t.rotation(grados);
        n.add(t);
        break;
      }
      case 'restr':
        n = new Konva.Group({ ...base });
        n.add(new Konva.Line({ points: [0, -d.size, d.size, 0, 0, d.size, -d.size, 0], closed: true, fill: rgba(d.fill || '#dc2626', d.fillAlpha), stroke: d.stroke || '#991b1b', strokeWidth: Math.max(1, d.size / 12) }));
        n.add(new Konva.Text({ text: String(d.n), fontSize: d.size * 0.95, fontStyle: 'bold', fill: '#fff', align: 'center', verticalAlign: 'middle', width: d.size * 2, height: d.size * 2, offsetX: d.size, offsetY: d.size }));
        break;
      case 'pin':
        n = new Konva.Group({ ...base });
        n.add(new Konva.Circle({ radius: d.size, fill: relleno || '#0070c0', stroke: d.stroke, strokeWidth: d.strokeWidth || 0 }));
        n.add(new Konva.Text({ text: String(d.n), fontSize: d.size * 1.05, fontStyle: 'bold', fill: d.textColor || '#fff', align: 'center', verticalAlign: 'middle', width: d.size * 2, height: d.size * 2, offsetX: d.size, offsetY: d.size }));
        break;
      case 'table': {
        const g = geomTabla(d), p = pad(d);
        n = new Konva.Group({ ...base });
        for (let r = 0; r < g.rows; r++) for (let c = 0; c < g.cols; c++) {
          const x = suma(g.cw, c), y = suma(g.rh, r), enc = d.header && r === 0;
          const k = claveCelda(r, c);
          n.add(new Konva.Rect({ x, y, width: g.cw[c], height: g.rh[r], fill: (d.cellFill && d.cellFill[k]) || (enc ? d.headerFill : d.fill), stroke: d.stroke, strokeWidth: d.strokeWidth }));
          const txt = d.cells && d.cells[r] && d.cells[r][c];
          if (txt) n.add(new Konva.Text({ x, y, width: g.cw[c], height: g.rh[r], text: txt, fontSize: d.fontSize, fontFamily: famDe(d), fontStyle: estiloDe(d, enc), textDecoration: decoDe(d), fill: (d.cellText && d.cellText[k]) || d.textColor || '#111111',
            padding: p, verticalAlign: 'middle', align: enc ? 'center' : 'left', ellipsis: true, listening: false }));
        }
        break;
      }
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
    marcasOrdenadas().forEach(d => {
      const n = crearNodo(d), c = capaPor(d.layer);
      if (c && c.locked) { n.draggable(false); n.listening(false); }   // capa bloqueada: se ve pero no se toca
      nodes[d.id] = n; layer.add(n); attach(n, d); if (d.type === 'table') resaltar(n, d);
      if (d.comment && d.comment.trim()) insigniaComentario(n, d, !!(c && c.locked));
    });
    selectIds(state.selId && nodes[state.selId] ? [state.selId] : state.sel.filter(i => nodes[i]), false);
    layer.batchDraw();
    $('#ve-contador').textContent = state.shapes.length + ' marca(s)';
    pintarCapas(); pintarEscala(); pintarComentarios();
  }

  // ---------- comentarios sobre una marca: insignia amarilla en el editor (no sale en las imágenes exportadas) ----------
  function insigniaComentario(n, d, bloqueada) {
    const b = n.getClientRect({ relativeTo: layer }), r = Math.max(10, state.W / 120);
    const g = new Konva.Group({ x: b.x + b.width, y: b.y, name: 'insignia', listening: !bloqueada });
    g.add(new Konva.Circle({ radius: r, fill: '#facc15', stroke: '#a16207', strokeWidth: r * 0.12 }));
    g.add(new Konva.Text({ text: '…', fontSize: r * 1.5, fontStyle: 'bold', fill: '#713f12', align: 'center', verticalAlign: 'middle', width: r * 2, height: r * 2, offsetX: r, offsetY: r }));
    g.on('click tap', () => { if (modoSel()) { select(d.id); const t = document.querySelector('.ve-tabs [data-tab=props]'); if (t) t.click(); } });
    g.on('mouseenter', () => { stage.container().style.cursor = 'pointer'; }); g.on('mouseleave', () => { stage.container().style.cursor = modoSel() ? 'default' : 'crosshair'; });
    layer.add(g);
  }
  function pintarComentarios() {
    const ul = $('#ve-coment-lista'); if (!ul) return;
    const con = state.shapes.filter(x => x.comment && x.comment.trim());
    $('#ve-coment-n').textContent = con.length ? '(' + con.length + ')' : '';
    ul.innerHTML = con.map(x => '<li data-id="' + x.id + '"><b>' + esc(NOMBRE_TIPO[x.type] || 'Marca') + '</b> ' + esc(x.comment.length > 90 ? x.comment.slice(0, 90) + '…' : x.comment) + '</li>').join('');
  }
  const NOMBRE_TIPO = { text: 'Texto', table: 'Tabla', image: 'Imagen', measure: 'Medida', restr: 'Restricción', pin: 'Círculo', link: 'Hipervínculo', line: 'Línea', arrow: 'Flecha', darrow: 'Flecha', rect: 'Rectángulo', rrect: 'Rectángulo', ellipse: 'Elipse', zone: 'Zona' };

  // ---------- selección y transformación ----------
  // Una o varias marcas elegidas (state.sel, en orden de elección); state.selId solo vale cuando hay exactamente una.
  // Las marcas agrupadas (campo grp) se eligen siempre juntas.
  const forma = id => state.shapes.find(x => x.id === id);
  const grupoDe = id => { const d = forma(id); return d && d.grp ? state.shapes.filter(x => x.grp === d.grp).map(x => x.id) : [id]; };
  let arrastre = null, marco = null, quitarAlSoltar = null;
  function selectIds(ids, expandir = true) {
    if (expandir) ids = ids.flatMap(grupoDe);
    ids = [...new Set(ids)].filter(i => nodes[i]);
    const unico = ids.length === 1 ? ids[0] : null;
    if (celdasSel.id && celdasSel.id !== unico) { const on = nodes[celdasSel.id]; celdasSel = { id: null, set: new Set() }; if (on) { on.find('.resalte').forEach(x => x.destroy()); layer.batchDraw(); } }
    const previa = state.selId;
    state.sel = ids; state.selId = unico;
    const d = unico && forma(unico);
    if (d && previa !== unico && d.layer && d.layer !== capaActiva && capaPor(d.layer)) { capaActiva = d.layer; pintarCapas(); }   // al elegir una marca se activa su capa
    tr.nodes(modoSel() ? ids.map(i => nodes[i]) : []);
    const esLinea = d && LINEAS.includes(d.type);
    tr.rotateEnabled(!ids.some(i => (forma(i) || {}).type === 'link'));
    tr.keepRatio(!!d && (d.type === 'image' || d.type === 'pin' || d.type === 'restr'));
    tr.enabledAnchors(esLinea ? (ids.length === 1 ? [] : ['middle-left', 'middle-right'])
      : d && (d.type === 'image' || d.type === 'pin' || d.type === 'restr' || d.type === 'text') ? ['top-left', 'top-right', 'bottom-left', 'bottom-right']
      : ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'middle-left', 'middle-right', 'top-center', 'bottom-center']);
    pintarNodos();
    trLayer.batchDraw();
    panel(d || null); pintarAlinear();
  }
  const select = id => selectIds(id ? [id] : [], !!id);

  // ---------- nodos de las líneas: arrastra un nodo para moverlo, arrastra el punto medio de un tramo para crear un nodo nuevo, doble clic en un nodo lo quita ----------
  let nodosGrupo = null, nodosNodo = null;
  function limpiarNodos() {
    if (nodosGrupo) { nodosGrupo.destroy(); nodosGrupo = null; }
    if (nodosNodo) { nodosNodo.off('.nodos'); nodosNodo = null; }
  }
  function pintarNodos() {
    limpiarNodos();
    const d = state.sel.length === 1 ? forma(state.sel[0]) : null, n = d && nodes[d.id];
    const capa = d && capaPor(d.layer);
    if (!d || !n || !modoSel() || !LINEAS.includes(d.type) || (capa && capa.locked) || !trLayer) return;
    const k = 1 / stage.scaleX(), editable = d.type !== 'measure';
    let pts = d.points.slice();
    nodosGrupo = new Konva.Group({ name: 'nodos' }); trLayer.add(nodosGrupo);
    nodosNodo = n;
    const aCapa = (x, y) => n.getTransform().point({ x, y });
    const aLocal = p => n.getTransform().copy().invert().point(p);
    let vertices = [], medios = [];
    const colocar = () => {
      const m = n.getTransform();
      vertices.forEach((c, i) => c.position(m.point({ x: pts[2 * i], y: pts[2 * i + 1] })));
      medios.forEach((c, i) => c.position(m.point({ x: (pts[2 * i] + pts[2 * i + 2]) / 2, y: (pts[2 * i + 1] + pts[2 * i + 3]) / 2 })));
      trLayer.batchDraw();
    };
    const vistaPrevia = () => { n.points(pts); layer.batchDraw(); };
    const terminar = () => { const nuevas = clone(state.shapes), t = nuevas.find(x => x.id === d.id); if (t) { t.points = pts.map(v => Math.round(v * 100) / 100); commit(nuevas); } };
    const mkVertice = i => {
      const c = new Konva.Circle({ radius: 6 * k, fill: '#ffffff', stroke: '#0070c0', strokeWidth: 2 * k, draggable: true, name: 'nodo' });
      c.on('mouseenter', () => { stage.container().style.cursor = 'move'; }); c.on('mouseleave', () => { stage.container().style.cursor = 'default'; });
      c.on('dragmove', () => { const p = aLocal(c.position()); pts[2 * i] = p.x; pts[2 * i + 1] = p.y; vistaPrevia(); colocar(); });
      c.on('dragend', terminar);
      if (editable) c.on('dblclick dbltap', () => { if (pts.length <= 4) { status('Una línea necesita al menos dos nodos'); return; } pts.splice(2 * i, 2); terminar(); });
      return c;
    };
    for (let i = 0; i < pts.length / 2; i++) { const c = mkVertice(i); vertices.push(c); nodosGrupo.add(c); }
    if (editable) {
      for (let i = 0; i < pts.length / 2 - 1; i++) {
        const m = new Konva.Circle({ radius: 4.5 * k, fill: 'rgba(0,112,192,.35)', stroke: '#0070c0', strokeWidth: 1 * k, draggable: true, name: 'nodo-medio' });
        m.on('mouseenter', () => { stage.container().style.cursor = 'copy'; }); m.on('mouseleave', () => { stage.container().style.cursor = 'default'; });
        m.on('dragstart', () => {   // el punto medio se vuelve un nodo nuevo
          pts.splice(2 * (i + 1), 0, ...(() => { const p = aLocal(m.position()); return [p.x, p.y]; })());
          m.on('dragmove.nuevo', () => { const p = aLocal(m.position()); pts[2 * (i + 1)] = p.x; pts[2 * (i + 1) + 1] = p.y; vistaPrevia(); });
        });
        m.on('dragend', terminar);
        medios.push(m); nodosGrupo.add(m);
      }
    }
    colocar();
    n.on('dragmove.nodos transform.nodos', colocar);
  }

  function attach(n, d) {
    n.on('mousedown touchstart', e => {
      if (!modoSel()) return;
      e.cancelBubble = true;
      const mas = e.evt.shiftKey || e.evt.ctrlKey || e.evt.metaKey || state.tool === 'multi';
      quitarAlSoltar = null;
      if (mas && !(d.type === 'table' && state.sel.length === 1 && state.sel[0] === d.id)) {   // Mayús/Ctrl: suma o quita (en una tabla elegida, Mayús elige celdas)
        if (state.sel.includes(d.id)) quitarAlSoltar = d.id;   // se quita solo si fue un clic; si arrastra, se mueve la selección completa
        else selectIds([...state.sel, ...grupoDe(d.id)], false);
      } else if (!state.sel.includes(d.id)) select(d.id);
    });
    n.on('click tap', () => {
      if (quitarAlSoltar !== d.id) return;
      quitarAlSoltar = null;
      const g = grupoDe(d.id); selectIds(state.sel.filter(i => !g.includes(i)), false);
    });
    // Arrastrar: con varias marcas elegidas se mueven todas juntas; con el imán, saltan a la cuadrícula
    n.on('dragstart', () => {
      quitarAlSoltar = null;
      arrastre = state.sel.length > 1 && state.sel.includes(d.id)
        ? { id: d.id, x: n.x(), y: n.y(), otros: state.sel.filter(i => i !== d.id && nodes[i]).map(i => ({ id: i, x: nodes[i].x(), y: nodes[i].y() })) } : null;
    });
    n.on('dragmove', () => {
      if (imanOn() || guiasOn()) n.position(ajustar({ x: n.x(), y: n.y() }));
      if (arrastre && arrastre.id === d.id) { const dx = n.x() - arrastre.x, dy = n.y() - arrastre.y; arrastre.otros.forEach(o => nodes[o.id].position({ x: o.x + dx, y: o.y + dy })); }
    });
    n.on('dragend', () => {
      const nuevas = clone(state.shapes), ids = arrastre && arrastre.id === d.id ? [d.id, ...arrastre.otros.map(o => o.id)] : [d.id];
      arrastre = null;
      ids.forEach(i => { const t = nuevas.find(x => x.id === i); if (t && nodes[i]) { t.x = nodes[i].x(); t.y = nodes[i].y(); } });
      commit(nuevas);
    });
    if (d.type === 'text') n.on('dblclick dbltap', () => editarTexto(d.id));
    if (d.type === 'table') n.on('click tap', e => {
      if (!modoSel()) return;
      const dd = state.shapes.find(s => s.id === d.id), ce = dd && celdaEn(n, dd); if (!ce) return;
      const k = claveCelda(ce.r, ce.c); ultimosClics.push(k); if (ultimosClics.length > 2) ultimosClics.shift();
      const suma_ = e.evt.shiftKey || e.evt.ctrlKey || e.evt.metaKey;   // Mayús o Ctrl: suma o quita celdas
      const set = suma_ && celdasSel.id === d.id ? new Set(celdasSel.set) : new Set();
      if (suma_ && set.has(k)) set.delete(k); else set.add(k);
      elegirCeldas(d.id, set);
    });
    // Doble clic = escribir, pero no si fueron dos clics rápidos con Mayús/Ctrl en celdas distintas (selección múltiple)
    if (d.type === 'table') n.on('dblclick dbltap', e => {
      if (e.evt && (e.evt.shiftKey || e.evt.ctrlKey || e.evt.metaKey)) return;
      const ce = celdaEn(n, state.shapes.find(s => s.id === d.id) || d); if (!ce) return;
      const k = claveCelda(ce.r, ce.c);
      if (ultimosClics.length >= 2 && ultimosClics.slice(-2).some(x => x !== k)) return;
      editarCelda(d.id, ce.r, ce.c);
    });
  }
  // Al terminar de transformar (mover esquinas, rotar) se guardan todas las marcas elegidas en un solo paso
  function aplicarTransformacion() {
    const nuevas = clone(state.shapes);
    tr.nodes().forEach(n => {
      const t = nuevas.find(x => x.id === n.getAttr('vid')); if (!t) return;
      const sx = n.scaleX(), sy = n.scaleY();
      t.x = n.x(); t.y = n.y(); t.rotation = n.rotation();
      if (t.type === 'table') { const g = geomTabla(t); t.colW = g.cw.map(v => Math.max(10, v * sx)); t.rowH = g.rh.map(v => Math.max(10, v * sy)); t.w = suma(t.colW); t.h = suma(t.rowH); }
      else if (POLI.includes(t.type) || RECT.includes(t.type)) { t.w = Math.max(4, t.w * sx); t.h = Math.max(4, t.h * sy); }
      else if (ELIPSE.includes(t.type)) { t.rx = Math.max(2, t.rx * sx); t.ry = Math.max(2, t.ry * sy); }
      else if (LINEAS.includes(t.type)) { t.points = t.points.map((v, i) => v * (i % 2 ? sy : sx)); }
      else if (t.type === 'text') { t.fontSize = Math.max(6, Math.round(t.fontSize * Math.max(sx, sy))); }
      else if (t.type === 'restr' || t.type === 'pin') { t.size = Math.max(8, t.size * Math.max(sx, sy)); }
    });
    commit(nuevas);
  }
  // Memoria del trazo (color, grosor y transparencia): las líneas nuevas salen como la última que se ajustó
  const opTrazo = () => Math.min(100, Math.max(5, +$('#t-op').value || 100));
  function guardarTrazo() { try { localStorage.setItem('ve-trazo', JSON.stringify({ stroke: $('#t-stroke').value, sw, op: opTrazo() })); } catch (_) {} }
  function cargarTrazo() {
    try {
      const m = JSON.parse(localStorage.getItem('ve-trazo') || 'null'); if (!m) return;
      if (/^#[0-9a-f]{6}$/i.test(m.stroke)) $('#t-stroke').value = m.stroke;
      if (Number.isFinite(m.sw) && m.sw >= 1) { sw = Math.round(m.sw); $('#t-sw').value = sw; $('#t-sw-r').value = Math.min(60, sw); }
      if (Number.isFinite(m.op)) { $('#t-op').value = $('#t-op-n').value = Math.min(100, Math.max(5, m.op)); }
    } catch (_) {}
  }
  function actualizar(id, cambios) {
    const nuevas = clone(state.shapes); const t = nuevas.find(s => s.id === id); if (!t) return;
    if (LINEAS.includes(t.type) && ('stroke' in cambios || 'strokeWidth' in cambios || 'opacity' in cambios)) {   // ajustar una línea también actualiza la memoria
      if (cambios.stroke) $('#t-stroke').value = cambios.stroke;
      if (cambios.strokeWidth) { sw = cambios.strokeWidth; $('#t-sw').value = sw; $('#t-sw-r').value = Math.min(60, sw); }
      if (cambios.opacity) $('#t-op').value = $('#t-op-n').value = cambios.opacity;
      guardarTrazo();
    }
    Object.assign(t, cambios); commit(nuevas);
  }

  // ---------- texto: textarea superpuesto ----------
  function editarTexto(id) {
    const n = nodes[id], d = state.shapes.find(s => s.id === id); if (!n || !d) return;
    const ta = document.createElement('textarea');
    const pos = n.getAbsolutePosition(), box = stage.container().getBoundingClientRect(), sc = stage.scaleX();
    Object.assign(ta.style, { position: 'fixed', left: box.left + pos.x + 'px', top: box.top + pos.y + 'px', fontSize: d.fontSize * sc + 'px', ...cssTexto(d), textAlign: d.align || 'left', lineHeight: 1.2,
      color: d.fill || '#000', background: 'rgba(255,255,255,.85)', border: '1px solid #0070c0', padding: '2px', margin: 0, minWidth: '120px', minHeight: d.fontSize * sc * 1.4 + 'px', zIndex: 10000, transformOrigin: 'left top', transform: 'rotate(' + (d.rotation || 0) + 'deg)' });
    ta.value = d.text; (document.fullscreenElement || document.body).appendChild(ta);   // en pantalla completa solo se ve lo que está dentro del elemento ampliado
    ta.focus(); ta.select();
    n.hide(); layer.batchDraw();
    let hecho = false;
    const fin = ok => { if (hecho) return; hecho = true; const v = ta.value; if (ta.parentNode) ta.remove(); n.show(); if (ok && v.trim() !== '' && v !== d.text) actualizar(id, { text: v }); else layer.batchDraw(); };
    ta.addEventListener('blur', () => fin(true));
    ta.addEventListener('keydown', e => { if (e.key === 'Escape') fin(false); if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); fin(true); } e.stopPropagation(); });
  }

  // ---------- tabla: editar el texto de una celda (Tab / Mayús+Tab pasan a la siguiente / anterior) ----------
  function editarCelda(id, r, c) {
    const n = nodes[id], d = state.shapes.find(s => s.id === id); if (!n || !d || d.type !== 'table') return;
    const g = geomTabla(d), p0 = n.getAbsoluteTransform().point({ x: suma(g.cw, c), y: suma(g.rh, r) });
    const box = stage.container().getBoundingClientRect(), sc = stage.scaleX() * (n.scaleX() || 1), enc = d.header && r === 0;
    const ta = document.createElement('textarea'), antes = (d.cells && d.cells[r] && d.cells[r][c]) || '';
    Object.assign(ta.style, { position: 'fixed', left: box.left + p0.x + 'px', top: box.top + p0.y + 'px', width: g.cw[c] * sc + 'px', height: g.rh[r] * sc + 'px', boxSizing: 'border-box',
      fontSize: d.fontSize * sc + 'px', ...cssTexto(d, enc), lineHeight: 1.2, color: d.textColor || '#111', background: '#fffbe6', border: '2px solid #0070c0',
      padding: pad(d) * sc + 'px', margin: 0, resize: 'none', overflow: 'auto', zIndex: 10000, transformOrigin: 'left top', transform: 'rotate(' + (d.rotation || 0) + 'deg)', textAlign: enc ? 'center' : 'left' });
    ta.value = antes; (document.fullscreenElement || document.body).appendChild(ta); ta.focus(); ta.select();
    let hecho = false;
    const fin = (ok, paso) => {
      if (hecho) return; hecho = true;
      const v = ta.value; if (ta.parentNode) ta.remove();
      if (ok && v !== antes) {
        const nuevas = clone(state.shapes), t = nuevas.find(s => s.id === id);
        t.cells[r][c] = v; ajustarFilas(t); commit(nuevas);
      }
      if (paso) {
        const i = r * g.cols + c + paso, total = g.rows * g.cols;
        if (i >= 0 && i < total) setTimeout(() => editarCelda(id, Math.floor(i / g.cols), i % g.cols), 30);
      }
    };
    ta.addEventListener('blur', () => fin(true));
    ta.addEventListener('keydown', e => {
      if (e.key === 'Escape') fin(false);
      else if (e.key === 'Tab') { e.preventDefault(); fin(true, e.shiftKey ? -1 : 1); }
      else if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); fin(true); }
      e.stopPropagation();
    });
  }

  // ---------- panel de propiedades ----------
  function sincroniza(rango, num, valor) { $(rango).value = valor; $(num).value = valor; }
  function panel(d) {
    const p = $('#ve-props'), vacio = p.querySelector('.ve-vacio');
    if (!vacio.dataset.base) vacio.dataset.base = vacio.textContent;
    vacio.textContent = state.sel.length > 1 ? 'Hay ' + state.sel.length + ' marcas elegidas: aliníalas, distribúyelas o agrúpalas arriba, y arrástralas para moverlas juntas. Elige una sola para editar sus propiedades.' : vacio.dataset.base;
    vacio.style.display = d ? 'none' : '';
    p.querySelector('.ve-campos').style.display = d ? '' : 'none';
    if (!d) return;
    const mostrar = (sel, ok) => { const el = p.querySelector(sel); if (el) el.closest('.ve-f').style.display = ok ? '' : 'none'; };
    const esTabla = d.type === 'table';
    const pf = $('#p-punch-f'); if (pf) pf.style.display = esTabla && d.punch ? '' : 'none';
    const esLink = d.type === 'link';
    const conTrazo = !['text', 'restr', 'image', 'link'].includes(d.type);
    const cerrada = CERRADAS.includes(d.type);
    const esRestr = d.type === 'restr';
    const conRelleno = cerrada || d.type === 'pin' || esRestr;
    const esTexto = d.type === 'text';
    $('#p-stroke').value = d.stroke || (esRestr ? '#991b1b' : '#111111'); mostrar('#p-stroke', conTrazo || esRestr);
    $('#p-fill').value = d.fill && d.fill.startsWith('#') ? d.fill : (d.type === 'pin' ? '#0070c0' : esRestr ? '#dc2626' : '#ffffff'); mostrar('#p-fill', conRelleno || d.type === 'text' || esTabla);
    $('#p-nofill').checked = cerrada && !d.fill; mostrar('#p-nofill', cerrada);
    sincroniza('#p-fa', '#p-fa-n', d.fillAlpha ?? 100); mostrar('#p-fa', conRelleno || (esTexto && !!d.bg));
    $('#p-bg-on').checked = !!d.bg; $('#p-bg').value = d.bg || '#ffeb00'; mostrar('#p-bg-on', esTexto); mostrar('#p-bg', esTexto && !!d.bg);
    sincroniza('#p-sw-r', '#p-sw', d.strokeWidth || sw); mostrar('#p-sw', conTrazo);
    $('#p-dash').value = d.dash || 'solid'; mostrar('#p-dash', conTrazo && d.type !== 'pin' && !esTabla);
    const conDash = conTrazo && d.type !== 'pin' && !esTabla && !!DASH_BASE[d.dash];
    const [dl, dg] = dashMedidas(d); $('#p-dash-l').value = +dl.toFixed(1); $('#p-dash-g').value = +dg.toFixed(1);
    mostrar('#p-dash-l', conDash); mostrar('#p-dash-g', conDash);
    sincroniza('#p-op', '#p-op-n', d.opacity ?? 100);
    const conLetra = esTexto || esTabla || d.type === 'measure';
    $('#p-fs').value = d.fontSize || 40; mostrar('#p-fs', conLetra);
    mostrar('#p-b', conLetra); mostrar('#p-ff', conLetra); mostrar('#p-al', esTexto);
    [['#p-b', d.bold], ['#p-i', d.italic], ['#p-u', d.underline], ['#p-s', d.strike]].forEach(([q, on]) => { $(q).classList.toggle('activo', !!on); $(q).setAttribute('aria-pressed', !!on); });
    $('#p-ff').value = FUENTES.includes(d.fontFamily) ? d.fontFamily : 'Arial'; $('#p-al').value = d.align || 'left';
    const esMedida = d.type === 'measure';
    mostrar('#p-unit', esMedida); mostrar('#p-ref', esMedida);
    if (esMedida) { $('#p-unit').value = UNIDADES[d.unit] ? d.unit : (state.escala ? state.escala.unit : 'cm'); $('#p-medida').textContent = textoMedida(d, Math.hypot(d.points[2], d.points[3])); }
    p.querySelectorAll('.ve-enlace').forEach(el => { el.style.display = esLink ? '' : 'none'; });
    if (esLink) {
      const es360 = d.kind === '360' && (d.url || '').startsWith('/uploads/'), ext = !es360 && (d.kind || 'ext') !== 'int';
      $('#p-lk-tipo').value = ext ? 'ext' : 'int'; $('#p-lk-ext').style.display = ext ? '' : 'none'; $('#p-lk-int').style.display = !es360 && !ext ? '' : 'none';
      $('#p-lk-tipo-f').style.display = es360 ? 'none' : ''; $('#p-lk-360').style.display = es360 ? '' : 'none';
      if (es360) { $('#p-lk-360-img').src = d.url || ''; $('#p-lk-360-med').textContent = 'Vista 360 interactiva: se abre en la sección View360.'; }
      $('#p-lk-url').value = ext ? d.url || '' : ''; $('#p-lk-label').value = d.label || '';
      $('#p-lk-icon').value = d.icon || 'link'; $('#p-lk-color').value = d.iconColor || '#0070c0'; $('#p-lk-num').value = d.n || 1;
      $('#p-lk-num').closest('.ve-f').style.display = (d.icon || 'link') === 'num' ? '' : 'none';
      sincroniza('#p-lk-size-r', '#p-lk-size', Math.round(d.isize || Math.max(10, Math.min(22, Math.min(d.w, d.h) * 0.35)))); $('#p-lk-show').checked = !!d.showIcon;
      const ds = C.destinos || [], grupos = [...new Set(ds.map(x => x.grupo))];
      $('#p-lk-dest').innerHTML = '<option value="">— Elegir destino —</option>' + grupos.map(g => '<optgroup label="' + esc(g) + '">' + ds.filter(x => x.grupo === g).map(x => '<option value="' + esc(x.url) + '">' + esc(x.etiqueta) + '</option>').join('') + '</optgroup>').join('');
      $('#p-lk-dest').value = !ext && !es360 ? d.url || '' : '';
      const a = $('#p-lk-abrir'); a.href = d.url || '#'; a.style.display = d.url && !es360 ? '' : 'none';
    }
    $('#p-coment').value = d.comment || '';
    const esImg = d.type === 'image';
    mostrar('#p-iw', esImg);
    if (esImg) { $('#p-iw').value = Math.round(d.w); $('#p-ih').value = Math.round(d.h); }
    if (esTabla) {
      const g = geomTabla(d);
      $('#p-rows').value = g.rows; $('#p-cols').value = g.cols; $('#p-hdr').checked = !!d.header;
      $('#p-hfill').value = d.headerFill || '#dbe5f1'; $('#p-tc').value = d.textColor || '#111111';
      $('#p-rh').value = Math.round(g.rh[0]); $('#p-cw').value = Math.round(g.cw[0]);
    }
    ['#p-rows', '#p-hdr', '#p-hfill', '#p-tc', '#p-rh'].forEach(q => mostrar(q, esTabla));
    const nsel = esTabla && celdasSel.id === d.id ? [...celdasSel.set].filter(k => { const [r, c] = k.split(',').map(Number); const g = geomTabla(d); return r < g.rows && c < g.cols; }).length : 0;
    $('#p-celdas').style.display = esTabla ? '' : 'none';
    $('#p-celdas-n').textContent = nsel;
    $('#p-celdas-cont').style.display = nsel ? '' : 'none';
    $('#p-celdas-vacio').style.display = nsel ? 'none' : '';
    if (nsel) {   // muestra el color de la primera celda seleccionada
      const [r, c] = [...celdasSel.set][0].split(',').map(Number), k = claveCelda(r, c);
      $('#p-cf').value = (d.cellFill && d.cellFill[k]) || (d.header && r === 0 ? d.headerFill : d.fill) || '#ffffff';
      $('#p-ct').value = (d.cellText && d.cellText[k]) || d.textColor || '#111111';
    }
    $('#p-n').value = d.n || 1; mostrar('#p-n', d.type === 'restr' || d.type === 'pin');
    $('#p-capa').innerHTML = [...state.layers].reverse().map(l => '<option value="' + l.id + '">' + nombreCapa(l).replace(/[<&]/g, '') + '</option>').join(''); $('#p-capa').value = d.layer;
    $('#p-act').value = d.actividad_id || ''; mostrar('#p-act', !['restr', 'image', 'table', 'link'].includes(d.type) && !C.modo);
  }
  function enlazarPanel() {
    const cur = () => state.shapes.find(s => s.id === state.selId);
    const num = (v, lo, hi, def) => Math.min(hi, Math.max(lo, Number.isFinite(+v) && v !== '' ? +v : def));
    const on = (sel, ev, fn) => $(sel).addEventListener(ev, fn);
    on('#p-stroke', 'change', e => cur() && actualizar(state.selId, { stroke: e.target.value }));
    on('#p-fill', 'change', e => cur() && actualizar(state.selId, { fill: e.target.value }));
    on('#p-bg-on', 'change', e => cur() && actualizar(state.selId, { bg: e.target.checked ? $('#p-bg').value : '' }));
    on('#p-bg', 'change', e => cur() && actualizar(state.selId, { bg: e.target.value }));
    on('#p-nofill', 'change', e => cur() && actualizar(state.selId, { fill: e.target.checked ? '' : $('#p-fill').value }));
    // Grosor: deslizador (se ve en vivo y se guarda al soltar) y cuadro numérico
    on('#p-sw-r', 'input', e => { $('#p-sw').value = e.target.value; const nodo = nodes[state.selId]; if (nodo && nodo.strokeWidth && !(nodo instanceof Konva.Group)) { nodo.strokeWidth(+e.target.value); layer.batchDraw(); } });
    on('#p-sw-r', 'change', e => cur() && actualizar(state.selId, { strokeWidth: num(e.target.value, 1, 80, sw) }));
    on('#p-sw', 'change', e => { const v = num(e.target.value, 1, 80, sw); $('#p-sw-r').value = v; cur() && actualizar(state.selId, { strokeWidth: v }); });
    // Estilo del texto (negrita, cursiva, subrayado, tachado), tipo de letra y alineación
    ['b:bold', 'i:italic', 'u:underline', 's:strike'].forEach(par => { const [q, campo] = par.split(':'); on('#p-' + q, 'click', () => alternarEstilo(campo)); });
    const urlOk = v => /^(https?:\/\/[^\s<>"']+|mailto:[^\s<>"']+|tel:[^\s<>"']+)$/i.test(v);
    on('#p-lk-tipo', 'change', e => cur() && actualizar(state.selId, { kind: e.target.value, url: '' }));
    on('#p-lk-url', 'change', e => {
      const v = e.target.value.trim(), con = /^[\w.-]+\.[a-z]{2,}(\/|$)/i.test(v) ? 'https://' + v : v;   // «maps.google.com/…» → https://…
      if (v && !urlOk(con)) { status('Enlace no válido: usa http://, https://, mailto: o tel:'); e.target.value = (cur() || {}).url || ''; return; }
      cur() && actualizar(state.selId, { url: con.slice(0, 500) });
    });
    on('#p-lk-dest', 'change', e => cur() && actualizar(state.selId, { url: e.target.value }));
    on('#p-lk-icon', 'change', e => {
      const c = cur(); if (!c) return; const cambios = { icon: e.target.value };
      if (e.target.value === 'num' && !c.n) { const usados = state.shapes.filter(x => x.type === 'link' && x.icon === 'num').map(x => x.n || 0); cambios.n = (usados.length ? Math.max(...usados) : 0) + 1; }
      actualizar(state.selId, cambios);
    });
    on('#p-lk-color', 'change', e => cur() && actualizar(state.selId, { iconColor: e.target.value }));
    on('#p-lk-num', 'change', e => cur() && actualizar(state.selId, { n: num(e.target.value, 1, 999, 1) }));
    // Tamaño del icono: el deslizador se ve en vivo y se guarda al soltar; también se puede escribir el valor
    const verIcono = r => {   // redibuja el círculo y el glifo del icono sin crear un paso de historial
      const n = nodes[state.selId], c = n && n.findOne('Circle'), t = n && n.find('Text').pop(), d = cur(); if (!c || !t || !d) return;
      c.radius(r); c.position({ x: r + 3, y: r + 3 }); t.setAttrs({ width: r * 2, height: r * 2, fontSize: r * (d.icon === 'num' ? 1.1 : 1.15) }); layer.batchDraw();
    };
    on('#p-lk-size-r', 'input', e => { $('#p-lk-size').value = e.target.value; verIcono(+e.target.value); });
    on('#p-lk-size-r', 'change', e => cur() && actualizar(state.selId, { isize: num(e.target.value, 6, 200, 18) }));
    on('#p-lk-size', 'change', e => { const v = num(e.target.value, 6, 200, 18); $('#p-lk-size-r').value = Math.min(120, v); cur() && actualizar(state.selId, { isize: v }); });
    on('#p-lk-show', 'change', e => cur() && actualizar(state.selId, { showIcon: e.target.checked }));
    on('#p-lk-360-cambiar', 'click', () => { cambiar360 = state.selId; $('#ve-archivo-360').click(); });
    on('#p-lk-label', 'change', e => cur() && actualizar(state.selId, { label: e.target.value.trim().slice(0, 120) }));
    on('#p-coment', 'change', e => cur() && actualizar(state.selId, { comment: e.target.value.trim().slice(0, 600) }));
    $('#ve-coment-lista').addEventListener('click', e => { const li = e.target.closest('li'); if (li) { select(li.dataset.id); const t = document.querySelector('.ve-tabs [data-tab=props]'); if (t) t.click(); } });
    on('#p-ff', 'change', e => cur() && actualizar(state.selId, { fontFamily: e.target.value }));
    on('#p-al', 'change', e => cur() && actualizar(state.selId, { align: e.target.value }));
    on('#p-unit', 'change', e => cur() && actualizar(state.selId, { unit: e.target.value }));
    on('#p-ref', 'click', async () => {
      const d = cur(); if (!d || d.type !== 'measure') return;
      const len = Math.hypot(d.points[2], d.points[3]), r = await pedirMedida({ titulo: 'Usar como referencia', texto: '¿Cuánto mide en la realidad este segmento? Se recalculan todas las medidas del dibujo.', valor: '', unidad: UNIDADES[d.unit] ? d.unit : 'cm' });
      if (r) commit(undefined, undefined, { mmPorPx: r.valor * UNIDADES[r.unidad] / len, unit: r.unidad });
    });
    // Tamaño de una imagen (o gráfico) insertado: conserva la proporción
    const tamImg = (campo) => () => { const d = cur(); if (!d || d.type !== 'image') return; const v = num($(campo === 'w' ? '#p-iw' : '#p-ih').value, 8, 20000, d[campo]), k = v / d[campo]; actualizar(d.id, { w: campo === 'w' ? v : d.w * k, h: campo === 'h' ? v : d.h * k }); };
    on('#p-iw', 'change', tamImg('w')); on('#p-ih', 'change', tamImg('h'));
    on('#p-dash', 'change', e => cur() && actualizar(state.selId, { dash: e.target.value, dashLen: undefined, dashGap: undefined }));
    on('#p-dash-l', 'change', e => cur() && actualizar(state.selId, { dashLen: Math.min(60, Math.max(0.2, +e.target.value || 1)) }));
    on('#p-dash-g', 'change', e => cur() && actualizar(state.selId, { dashGap: Math.min(60, Math.max(0.2, +e.target.value || 1)) }));
    on('#p-fs', 'change', e => {
      const d = cur(); if (!d) return;
      const fs = num(e.target.value, 6, 400, 40);
      if (d.type === 'table') modTabla(t => { t.fontSize = fs; ajustarFilas(t); }); else actualizar(state.selId, { fontSize: fs });
    });
    // Tabla
    const modTabla = fn => { const nuevas = clone(state.shapes), t = nuevas.find(x => x.id === state.selId); if (t && t.type === 'table') { fn(t); commit(nuevas); } };
    const tamTabla = () => modTabla(t => cambiarTamTabla(t, num($('#p-rows').value, 1, 30, t.rows), num($('#p-cols').value, 1, 15, t.cols)));
    on('#p-rows', 'change', tamTabla); on('#p-cols', 'change', tamTabla);
    on('#p-hdr', 'change', e => modTabla(t => { t.header = e.target.checked; ajustarFilas(t); }));
    on('#p-hfill', 'change', e => modTabla(t => { t.headerFill = e.target.value; }));
    on('#p-tc', 'change', e => modTabla(t => { t.textColor = e.target.value; }));
    on('#p-rh', 'change', e => modTabla(t => { const v = num(e.target.value, 8, 2000, t.rowH[0]); t.rowH = t.rowH.map(() => v); ajustarFilas(t); }));
    // Celdas seleccionadas: relleno y color de texto propios; «Quitar» devuelve el color general de la tabla
    const delSel = (campo, valor) => modTabla(t => {
      const m = t[campo] = t[campo] || {};
      celdasSel.set.forEach(k => { if (valor === null) delete m[k]; else m[k] = valor; });
    });
    on('#p-cf', 'change', e => delSel('cellFill', e.target.value)); on('#p-cf-x', 'click', () => delSel('cellFill', null));
    on('#p-ct', 'change', e => delSel('cellText', e.target.value)); on('#p-ct-x', 'click', () => delSel('cellText', null));
    const todas = (filtro) => { const d = cur(); if (!d || d.type !== 'table') return; const g = geomTabla(d), filas = new Set([...celdasSel.set].map(k => +k.split(',')[0])), cols = new Set([...celdasSel.set].map(k => +k.split(',')[1])), out = [];
      for (let r = 0; r < g.rows; r++) for (let c = 0; c < g.cols; c++) if (filtro(r, c, filas, cols)) out.push(claveCelda(r, c));
      elegirCeldas(d.id, out); };
    on('#b-c-fila', 'click', () => todas((r, c, f) => f.has(r)));
    on('#b-c-col', 'click', () => todas((r, c, f, co) => co.has(c)));
    on('#b-c-todo', 'click', () => todas(() => true));
    on('#b-c-nada', 'click', () => todas(() => false));
    on('#p-cw', 'change', e => modTabla(t => { const v = num(e.target.value, 10, 4000, t.colW[0]); t.colW = t.colW.map(() => v); t.w = suma(t.colW); ajustarFilas(t); }));
    on('#p-n', 'change', e => cur() && actualizar(state.selId, { n: num(e.target.value, 1, 99, 1) }));
    on('#p-capa', 'change', e => { const d = cur(); if (!d) return; const nuevas = clone(state.shapes); nuevas.find(x => x.id === d.id).layer = e.target.value; capaActiva = e.target.value; commit(nuevas); });
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
  const elegidas = () => (state.sel.length ? state.sel : (state.selId ? [state.selId] : [])).filter(i => forma(i));
  function alternarEstilo(campo) {
    const d = forma(state.selId); if (!d) return;
    if (d.type === 'table') { modTablaFuera(t => { t[campo] = !t[campo]; ajustarFilas(t); }); return; }
    actualizar(d.id, { [campo]: !d[campo] });
  }
  function modTablaFuera(fn) { const nuevas = clone(state.shapes), t = nuevas.find(x => x.id === state.selId); if (t) { fn(t); commit(nuevas); } }
  function mover(dir) {
    const ids = new Set(elegidas()); if (!ids.size) return;
    const todas = clone(state.shapes), sel = todas.filter(x => ids.has(x.id)), resto = todas.filter(x => !ids.has(x.id));
    commit(dir > 0 ? [...resto, ...sel] : [...sel, ...resto]);
  }
  function borrar() {
    const ids = new Set(elegidas()); if (!ids.size) return;
    state.selId = null; state.sel = [];
    commit(clone(state.shapes).filter(s => !ids.has(s.id)));
  }
  // Copias con ids nuevos; los grupos copiados siguen agrupados entre sí pero separados del original
  function clonarMarcas(lista, desp) {
    const mapa = {};
    return clone(lista).map(c => { c.id = uid(); if (c.grp) c.grp = mapa[c.grp] = mapa[c.grp] || ('g' + uid()); c.x += desp; c.y += desp; return c; });
  }
  function agregarYElegir(nuevos) {
    state.sel = nuevos.map(c => c.id); state.selId = nuevos.length === 1 ? nuevos[0].id : null;
    commit([...clone(state.shapes), ...nuevos]);
  }
  function duplicar() { const ds = elegidas().map(forma); if (ds.length) agregarYElegir(clonarMarcas(ds, 30)); }
  function copiar() { const ds = elegidas().map(forma); if (ds.length) state.clip = clone(ds); }
  function pegar() { if (!state.clip) return; const nuevos = clonarMarcas(state.clip, 40); state.clip = clone(nuevos); agregarYElegir(nuevos); }
  function agrupar() { const ids = new Set(elegidas()); if (ids.size < 2) return; const g = 'g' + uid(); const nuevas = clone(state.shapes); nuevas.forEach(x => { if (ids.has(x.id)) x.grp = g; }); commit(nuevas); }
  function desagrupar() { const ids = new Set(elegidas()); const nuevas = clone(state.shapes); nuevas.forEach(x => { if (ids.has(x.id)) delete x.grp; }); commit(nuevas); }

  // ---------- alinear y distribuir (como en Inkscape) ----------
  const cajaDe = id => nodes[id].getClientRect({ relativeTo: layer });
  function alinear(modo) {
    const ids = elegidas(); if (!ids.length) return;
    const cajas = ids.map(id => ({ id, b: cajaDe(id) }));
    const u = cajas.reduce((a, { b }) => ({ x1: Math.min(a.x1, b.x), y1: Math.min(a.y1, b.y), x2: Math.max(a.x2, b.x + b.width), y2: Math.max(a.y2, b.y + b.height) }), { x1: Infinity, y1: Infinity, x2: -Infinity, y2: -Infinity });
    let rel = $('#al-rel').value, ref = { x1: u.x1, y1: u.y1, x2: u.x2, y2: u.y2 }, fijo = null;
    if (ids.length === 1 || rel === 'pag') ref = { x1: 0, y1: 0, x2: state.W, y2: state.H };
    else if (rel !== 'sel') {
      const area = c => c.b.width * c.b.height;
      fijo = rel === 'prim' ? cajas[0] : rel === 'ult' ? cajas[cajas.length - 1] : cajas.reduce((a, c) => (rel === 'grande' ? area(c) > area(a) : area(c) < area(a)) ? c : a);
      ref = { x1: fijo.b.x, y1: fijo.b.y, x2: fijo.b.x + fijo.b.width, y2: fijo.b.y + fijo.b.height };
    }
    const mov = {};
    const poner = (c, dx, dy) => { if (c !== fijo && (dx || dy)) mov[c.id] = { dx: dx || 0, dy: dy || 0 }; };
    cajas.forEach(c => {
      const b = c.b;
      switch (modo) {
        case 'izq': poner(c, ref.x1 - b.x, 0); break;
        case 'der': poner(c, ref.x2 - (b.x + b.width), 0); break;
        case 'cx': poner(c, (ref.x1 + ref.x2) / 2 - (b.x + b.width / 2), 0); break;
        case 'sup': poner(c, 0, ref.y1 - b.y); break;
        case 'inf': poner(c, 0, ref.y2 - (b.y + b.height)); break;
        case 'cy': poner(c, 0, (ref.y1 + ref.y2) / 2 - (b.y + b.height / 2)); break;
      }
    });
    aplicarMovimientos(mov);
  }
  function distribuir(modo) {
    const ids = elegidas(); if (ids.length < 3) { status('Elige al menos 3 marcas para distribuir'); return; }
    const horiz = modo[0] === 'h', tipo = modo.slice(2);
    const cajas = ids.map(id => { const b = cajaDe(id); return { id, a: horiz ? b.x : b.y, l: horiz ? b.width : b.height }; }).sort((p, q) => (p.a + p.l / 2) - (q.a + q.l / 2));
    const n = cajas.length, mov = {};
    const ponerEn = (c, nuevoA) => { const d = nuevoA - c.a; if (Math.abs(d) > 0.01) mov[c.id] = horiz ? { dx: d, dy: 0 } : { dx: 0, dy: d }; };
    if (tipo === 'gap') {
      const ordenadas = [...cajas].sort((p, q) => p.a - q.a), ini = ordenadas[0].a, fin = Math.max(...ordenadas.map(c => c.a + c.l));
      const hueco = (fin - ini - ordenadas.reduce((t, c) => t + c.l, 0)) / (n - 1);
      let pos = ini; ordenadas.forEach(c => { ponerEn(c, pos); pos += c.l + hueco; });
    } else {
      const ref = c => tipo === 'ini' ? c.a : tipo === 'fin' ? c.a + c.l : c.a + c.l / 2;
      const primero = ref(cajas[0]), ultimo = ref(cajas[n - 1]);
      cajas.forEach((c, i) => ponerEn(c, c.a + (primero + (ultimo - primero) * i / (n - 1) - ref(c))));
    }
    aplicarMovimientos(mov);
  }
  function aplicarMovimientos(mov) {
    if (!Object.keys(mov).length) return;
    const nuevas = clone(state.shapes);
    nuevas.forEach(x => { const m = mov[x.id]; if (m) { x.x += m.dx; x.y += m.dy; } });
    commit(nuevas);
  }
  function pintarAlinear() {
    const caja = $('#ve-alinear'); if (!caja) return;
    const n = elegidas().length;
    caja.hidden = n < 1;
    $('#ve-sel-n').textContent = n > 1 ? n + ' marcas elegidas' : (n === 1 ? '1 marca elegida' : '');
    caja.querySelectorAll('[data-di]').forEach(b => { b.disabled = n < 3; });
    const tp = document.querySelector('.ve-tabs [data-tab=props]'); if (tp) tp.classList.toggle('con-sel', n > 0);
    $('#b-agrupar').disabled = n < 2;
    $('#b-desagrupar').disabled = !elegidas().some(i => (forma(i) || {}).grp);
    $('#al-rel').disabled = n < 2;
    document.querySelectorAll('#ve-alinear .ve-solo-multi').forEach(el => { el.hidden = n < 2; });
  }
  function enlazarAlinear() {
    const caja = $('#ve-alinear'); if (!caja) return;
    caja.addEventListener('click', e => {
      const a = e.target.closest('[data-al]'), d = e.target.closest('[data-di]');
      if (a) alinear(a.dataset.al); else if (d && !d.disabled) distribuir(d.dataset.di);
    });
    $('#b-agrupar').addEventListener('click', agrupar); $('#b-desagrupar').addEventListener('click', desagrupar);
  }

  // ---------- herramientas de dibujo ----------
  let dibujando = null, panning = null, poli = null;
  const posRel = (crudo) => { const p = layer.getAbsoluteTransform().copy().invert().point(stage.getPointerPosition()); return crudo ? p : ajustar(p); };   // con el imán activo, a la cuadrícula
  const orto = () => $('#t-orto').checked;
  const ortoDelta = (dx, dy) => Math.abs(dx) > Math.abs(dy) ? [dx, 0] : [0, dy];   // solo horizontal o vertical
  const estiloBase = () => ({ stroke: $('#t-stroke').value, strokeWidth: sw, dash: 'solid', opacity: opTrazo() });
  const LINEA_TOOLS = { line: {}, dash: { dash: 'dash' }, dashdot: { dash: 'dashdot' }, dot: { dash: 'dot' }, arrow: { _t: 'arrow' }, darrow: { _t: 'darrow' } };
  function nuevoDato(tool, p) {
    const e = estiloBase(), id = uid();
    if (LINEA_TOOLS[tool]) { const x = LINEA_TOOLS[tool]; return { id, type: x._t || 'line', x: p.x, y: p.y, points: [0, 0, 0, 0], ...e, ...(x.dash ? { dash: x.dash } : {}) }; }
    if (tool === 'measure') return { id, type: 'measure', x: p.x, y: p.y, points: [0, 0, 0, 0], ...e, dash: 'solid', fontSize: Math.max(10, Math.round(sw * 5)), unit: state.escala ? state.escala.unit : 'cm' };
    if (tool === 'table') return { id, type: 'table', x: p.x, y: p.y, w: 1, h: 1, rows: 3, cols: 3, header: true, fontSize: Math.round(sw * 6), fill: '#ffffff', headerFill: '#dbe5f1', textColor: '#111111', rotation: 0, ...e, strokeWidth: Math.max(1, Math.round(sw / 2)) };
    if (tool === 'v360') return { id, type: 'link', x: p.x, y: p.y, w: 1, h: 1, kind: '360', url: pend360.url, label: pend360.nombre, icon: 'globe', iconColor: '#e65100', showIcon: false, rotation: 0, opacity: 100 };
    if (tool === 'link') return { id, type: 'link', x: p.x, y: p.y, w: 1, h: 1, kind: 'ext', url: '', label: '', icon: 'link', iconColor: '#0070c0', showIcon: false, rotation: 0, opacity: 100 };
    if (tool === 'ellipse') return { id, type: 'ellipse', x: p.x, y: p.y, rx: 1, ry: 1, fill: '', fillAlpha: 100, ...e };
    if (tool === 'zone') return { id, type: 'zone', x: p.x, y: p.y, rx: 1, ry: 1, fill: '#ffeb00', fillAlpha: 35, ...e, stroke: '#c8b400' };
    return { id, type: tool, x: p.x, y: p.y, w: 1, h: 1, fill: '', fillAlpha: 100, ...e };   // rect, rrect y polígonos
  }
  const FORMAS = ['rect', 'rrect', 'ellipse', 'zone', ...POLI];

  // Punto de la polilínea respecto a su origen; en modo ortogonal (o con Mayús) el tramo sale horizontal o vertical
  function puntoPoli(d, p, shift) {
    const n = d.points.length, ref = n >= 4 ? [d.points[n - 4], d.points[n - 3]] : [0, 0];
    let dx = p.x - d.x - ref[0], dy = p.y - d.y - ref[1];
    if (orto() || shift) [dx, dy] = ortoDelta(dx, dy);
    return [ref[0] + dx, ref[1] + dy];
  }

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
      if (modoSel()) {
        if (!esFondo) return;
        if (state.tool === 'multi' || e.evt.shiftKey) {   // recuadro de selección (Mayús suma a lo ya elegido)
          const p = posRel(true), base = e.evt.shiftKey ? [...state.sel] : [];
          if (!base.length) select(null);
          const r = new Konva.Rect({ x: p.x, y: p.y, width: 0, height: 0, fill: 'rgba(0,112,192,.12)', stroke: '#0070c0', strokeWidth: 1.5 / stage.scaleX(), dash: [6 / stage.scaleX(), 4 / stage.scaleX()], listening: false });
          trLayer.add(r); marco = { x0: p.x, y0: p.y, r, base }; return;
        }
        select(null); panning = { x: e.evt.clientX, y: e.evt.clientY, sx: stage.x(), sy: stage.y() };
        return;
      }
      if (!capaOk()) return;
      if (['text', 'restr', 'pin', 'image'].includes(state.tool)) return;   // se crean en el clic
      const p = posRel();
      if (state.tool === 'poly') {
        if (!poli) {
          const d = { id: uid(), type: 'line', x: p.x, y: p.y, points: [0, 0, 0, 0], ...estiloBase(), round: true };
          poli = { d, nodo: crearNodo(d) }; layer.add(poli.nodo);
        } else {
          const q = puntoPoli(poli.d, p, e.evt.shiftKey);
          poli.d.points[poli.d.points.length - 2] = q[0]; poli.d.points[poli.d.points.length - 1] = q[1];   // fija el punto temporal
          poli.d.points.push(q[0], q[1]);                                                                    // y abre otro
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
      marcarPuntero();
      if (marco) { const p = posRel(true); marco.r.setAttrs({ x: Math.min(marco.x0, p.x), y: Math.min(marco.y0, p.y), width: Math.abs(p.x - marco.x0), height: Math.abs(p.y - marco.y0) }); trLayer.batchDraw(); return; }
      if (panning) { stage.position({ x: panning.sx + e.evt.clientX - panning.x, y: panning.sy + e.evt.clientY - panning.y }); refrescarVista(); return; }
      if (poli) {
        const p = posRel(), d = poli.d, n = d.points.length;
        if (n === 2) d.points.push(0, 0);
        const q = puntoPoli(d, p, e.evt.shiftKey);
        d.points[d.points.length - 2] = q[0]; d.points[d.points.length - 1] = q[1];
        poli.nodo.destroy(); poli.nodo = crearNodo(d); layer.add(poli.nodo); layer.batchDraw(); return;
      }
      if (!dibujando) return;
      const p = posRel(), { d, p0 } = dibujando; let dx = p.x - p0.x, dy = p.y - p0.y;
      if (dibujando.libre) {
        const ult = d.points.slice(-2), paso = 3 / stage.scaleX();
        if (Math.hypot(dx - ult[0], dy - ult[1]) < paso) return;
        d.points.push(dx, dy);
      } else if (LINEAS.includes(d.type)) {
        if (e.evt.shiftKey || orto()) [dx, dy] = ortoDelta(dx, dy);   // Mayús u «Ortogonal»: recta horizontal/vertical
        d.points = [0, 0, dx, dy];
      } else if (ELIPSE.includes(d.type)) {
        d.x = p0.x + dx / 2; d.y = p0.y + dy / 2; d.rx = Math.abs(dx) / 2; d.ry = Math.abs(dy) / 2;
      } else { d.x = Math.min(p0.x, p.x); d.y = Math.min(p0.y, p.y); d.w = Math.abs(dx); d.h = Math.abs(dy); }
      dibujando.nodo.destroy(); dibujando.nodo = crearNodo(d); layer.add(dibujando.nodo); layer.batchDraw();
    });
    window.addEventListener('mouseup', () => {
      panning = null;
      if (marco) {   // elige lo que toca el recuadro
        const { r, base } = marco, a = r.getClientRect({ relativeTo: layer }); marco = null; r.destroy(); trLayer.batchDraw();
        const dentro = Object.keys(nodes).filter(id => {
          const n = nodes[id]; if (!n.listening()) return false;
          const b = n.getClientRect({ relativeTo: layer });
          return b.x < a.x + a.width && b.x + b.width > a.x && b.y < a.y + a.height && b.y + b.height > a.y;
        });
        selectIds([...base, ...dentro]);
        return;
      }
      if (!dibujando) return;
      const { d, nodo, libre } = dibujando; nodo.destroy(); dibujando = null;
      if (d.type === 'table') normalizarTabla(d);
      const dim = d.points ? (libre ? d.points.length : Math.hypot(d.points[2], d.points[3])) : Math.max(d.w || d.rx * 2 || 0, d.h || d.ry * 2 || 0);
      if (dim > (libre ? 4 : 6)) {
        if (d.type === 'measure') { terminarMedida(d); return; }
        state.selId = d.id; commit([...clone(state.shapes), d]); if (!libre) setTool('select');
        if (d.type === 'link') { pend360 = null; const t = document.querySelector('.ve-tabs [data-tab=props]'); if (t) t.click(); setTimeout(() => { const u = d.kind === '360' ? $('#p-lk-label') : $('#p-lk-url'); if (u) u.focus(); }, 60); }
      } else layer.batchDraw();
    });
    stage.on('dblclick dbltap', () => { if (state.tool === 'poly') finalizarPoli(); });
    stage.on('click tap', e => {
      if (!['text', 'restr', 'pin'].includes(state.tool)) return;
      if (!capaOk()) return;
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
      $('#ve-zoom').textContent = Math.round(nuevo * 100) + '%'; refrescarVista();
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'F8') { e.preventDefault(); const o = $('#t-orto'); o.checked = !o.checked; status('Modo ortogonal ' + (o.checked ? 'activado' : 'desactivado')); return; }
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;
      const k = e.key.toLowerCase();
      if (poli && (k === 'enter' || k === 'escape')) { e.preventDefault(); finalizarPoli(); return; }
      if ((e.ctrlKey || e.metaKey) && k === 'z') { e.preventDefault(); e.shiftKey ? redo() : undo(); }
      else if ((e.ctrlKey || e.metaKey) && k === 'y') { e.preventDefault(); redo(); }
      else if ((e.ctrlKey || e.metaKey) && k === 'c') copiar();
      else if ((e.ctrlKey || e.metaKey) && k === 'v') pegar();
      else if ((e.ctrlKey || e.metaKey) && k === 'd') { e.preventDefault(); duplicar(); }
      else if ((e.ctrlKey || e.metaKey) && k === 's') { e.preventDefault(); guardar(); }
      else if ((e.ctrlKey || e.metaKey) && k === 'a') { e.preventDefault(); selectIds(Object.keys(nodes).filter(id => nodes[id].listening()), false); }
      else if ((e.ctrlKey || e.metaKey) && k === 'g') { e.preventDefault(); e.shiftKey ? desagrupar() : agrupar(); }
      else if ((e.ctrlKey || e.metaKey) && ['b', 'i', 'u'].includes(k) && state.selId && ['text', 'table', 'measure'].includes((forma(state.selId) || {}).type)) { e.preventDefault(); alternarEstilo({ b: 'bold', i: 'italic', u: 'underline' }[k]); }
      else if (k === 'pageup' && state.slides) { e.preventDefault(); irALamina(state.idx - 1); }
      else if (k === 'pagedown' && state.slides) { e.preventDefault(); irALamina(state.idx + 1); }
      else if (k === 'delete' || k === 'backspace') { e.preventDefault(); borrar(); }
      else if (k === 'escape') { setTool('select'); select(null); }
      else if (k.startsWith('arrow') && elegidas().length) {
        e.preventDefault(); const paso = e.shiftKey ? 10 : 1, dx = k === 'arrowleft' ? -paso : k === 'arrowright' ? paso : 0, dy = k === 'arrowup' ? -paso : k === 'arrowdown' ? paso : 0, mov = {};
        elegidas().forEach(i => { mov[i] = { dx, dy }; });
        aplicarMovimientos(mov);
      }
    });
  }

  function setTool(t) {
    if (t === 'image') { if (capaOk()) $('#ve-archivo').click(); return; }
    if (t === 'v360' && !pend360) { if (capaOk()) { cambiar360 = null; $('#ve-archivo-360').click(); } return; }
    if (t !== 'v360') pend360 = null;
    if (poli) finalizarPoli();
    state.tool = t;
    document.querySelectorAll('[data-tool]').forEach(b => b.classList.toggle('activo', b.dataset.tool === t));
    Object.values(nodes).forEach(n => n.draggable(modoSel() && n.listening()));
    stage.container().style.cursor = modoSel() ? 'default' : 'crosshair';
    if (!modoSel()) { tr.nodes([]); limpiarNodos(); trLayer.batchDraw(); }
  }

  // ---------- foto 360 de un hipervínculo «Vista 360» ----------
  async function subirPanorama(file) {
    if (!file) return;
    const destino = cambiar360; cambiar360 = null;
    if (file.size > 40 * 1024 * 1024) { status('La foto 360 no puede pesar más de 40 MB'); return; }
    status('Subiendo foto 360…');
    const fd = new FormData(); fd.append('proyecto', C.proyecto); fd.append('imagen', file);
    try {
      const r = await fetch((C.api && C.api.panorama) || '/api/view360/panorama', { method: 'POST', body: fd });
      const d = await r.json();
      if (!d.success) { status('Error: ' + (d.error || 'no se pudo subir la foto 360')); return; }
      if (destino && forma(destino)) { actualizar(destino, { url: d.url }); status('Foto 360 reemplazada (' + d.ancho + ' × ' + d.alto + ' px)'); return; }
      pend360 = { url: d.url, nombre: (file.name || '').replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ').slice(0, 60) };
      setTool('v360');
      status('Foto 360 cargada (' + d.ancho + ' × ' + d.alto + ' px). Dibuja ahora el área en el plano que abrirá la vista.');
    } catch (e) { status('Error de conexión al subir la foto 360'); }
  }

  // ---------- imágenes dentro del editor ----------
  async function subirImagen(file, fraccion = 0.3, vinculo = null) {
    if (!file) return;
    status('Subiendo imagen…');
    const fd = new FormData(); fd.append('lamina', C.lamina); fd.append('imagen', file);
    try {
      const r = await fetch((C.api && C.api.imagen) || '/api/vplan/imagen', { method: 'POST', body: fd });
      const d = await r.json();
      if (!d.success) { status('Error: ' + (d.error || 'no se pudo subir')); return; }
      const img = new Image();
      img.onload = () => {
        imgCache[d.src] = { img, ok: true };
        const max = Math.min(state.W, state.H) * fraccion, k = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
        const w = img.naturalWidth * k, h = img.naturalHeight * k;
        const c = layer.getAbsoluteTransform().copy().invert().point({ x: stage.width() / 2, y: stage.height() / 2 });
        const sh = { id: uid(), type: 'image', src: d.src, x: c.x - w / 2, y: c.y - h / 2, w, h, rotation: 0, opacity: 100, ...(vinculo ? { ref: vinculo.ref, refv: vinculo.v } : {}) };
        state.selId = sh.id; commit([...clone(state.shapes), sh]); setTool('select'); status('');
      };
      img.src = d.src;
    } catch (e) { status('Error al subir la imagen'); }
  }

  // ---------- zoom / encuadre ----------
  function encuadrar() {
    const c = $('#ve-lienzo'), w = c.clientWidth, h = c.clientHeight, R = reglasOn() ? REGLA : 0;
    const k = Math.min((w - R - 16) / state.W, (h - R - 16) / state.H);
    stage.size({ width: w, height: h }); stage.scale({ x: k, y: k });
    stage.position({ x: R + (w - R - state.W * k) / 2, y: R + (h - R - state.H * k) / 2 });
    $('#ve-zoom').textContent = Math.round(k * 100) + '%';
    refrescarVista();
  }

  // ---------- cuadrícula, imán y reglas ----------
  const REGLA = 22;
  let glayer = null, ptr = null;
  const reglasOn = () => !!$('#t-reglas') && $('#t-reglas').checked;
  const imanOn = () => !!$('#t-snap') && $('#t-snap').checked;
  const pasoGrilla = () => Math.max(2, +($('#t-grid-n') || {}).value || 50);
  function ajustar(p) {
    let q = p;
    if (imanOn()) { const g = pasoGrilla(); q = { x: Math.round(p.x / g) * g, y: Math.round(p.y / g) * g }; }
    if (!guiasOn()) return q;
    const tol = 6 / (stage ? stage.scaleX() : 1);   // 6 px de pantalla
    let mx = tol, my = tol;
    (state.guides || []).forEach(g => {
      if (g.visible === false) return;
      if (g.eje === 'v' && Math.abs(p.x - g.pos) <= mx) { mx = Math.abs(p.x - g.pos); q = { ...q, x: g.pos }; }
      if (g.eje === 'h' && Math.abs(p.y - g.pos) <= my) { my = Math.abs(p.y - g.pos); q = { ...q, y: g.pos }; }
    });
    return q;
  }
  const fmtNum = v => { const a = Math.abs(v); return String(+v.toFixed(a >= 100 ? 1 : a >= 1 ? 2 : 3)); };
  function dibujarGrilla(ctx) {
    if (!$('#t-grid').checked) return;
    const k = stage.scaleX(), c = ctx._context;
    let g = pasoGrilla(); while (g * k < 7) g *= 2;
    const x0 = Math.max(0, -stage.x() / k), x1 = Math.min(state.W, (stage.width() - stage.x()) / k), y0 = Math.max(0, -stage.y() / k), y1 = Math.min(state.H, (stage.height() - stage.y()) / k);
    c.save(); c.lineWidth = 1 / k;
    [false, true].forEach(mayor => {
      c.beginPath();
      for (let i = Math.ceil(x0 / g); i * g <= x1; i++) if ((i % 5 === 0) === mayor) { c.moveTo(i * g, y0); c.lineTo(i * g, y1); }
      for (let j = Math.ceil(y0 / g); j * g <= y1; j++) if ((j % 5 === 0) === mayor) { c.moveTo(x0, j * g); c.lineTo(x1, j * g); }
      c.strokeStyle = mayor ? 'rgba(0,112,192,.65)' : 'rgba(0,112,192,.3)'; c.stroke();
    });
    c.restore();
  }
  // Paso «redondo» (1, 2, 5 × 10ⁿ) para las marcas de las reglas
  const pasoBonito = min => { const mag = 10 ** Math.floor(Math.log10(min)), n = min / mag; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * mag; };
  function dibujarReglas() {
    const ch = $('#ve-regla-h'), cv = $('#ve-regla-v'); if (!ch || !cv || !stage) return;
    const on = reglasOn(); ch.hidden = cv.hidden = !on; $('#ve-regla-esq').hidden = !on;
    if (!on) return;
    const cont = $('#ve-lienzo'), W = cont.clientWidth, H = cont.clientHeight, dpr = window.devicePixelRatio || 1, k = stage.scaleX();
    const e = state.escala, f = e ? e.mmPorPx / UNIDADES[e.unit] : 1, un = e ? e.unit : 'px';   // unidades por píxel del dibujo
    const porUnidad = k / f, paso = pasoBonito(64 / porUnidad), menor = paso / 5;
    const pinta = (canvas, horiz) => {
      const largo = (horiz ? W : H) - REGLA, ancho = REGLA;
      const cw = Math.max(1, Math.round((horiz ? largo : ancho) * dpr)), chh = Math.max(1, Math.round((horiz ? ancho : largo) * dpr));
      if (canvas.width !== cw || canvas.height !== chh) { canvas.width = cw; canvas.height = chh; canvas.style.width = (horiz ? largo : ancho) + 'px'; canvas.style.height = (horiz ? ancho : largo) + 'px'; }
      const x = canvas.getContext('2d'); x.setTransform(dpr, 0, 0, dpr, 0, 0); x.clearRect(0, 0, horiz ? largo : ancho, horiz ? ancho : largo);
      x.fillStyle = '#1e2433'; x.fillRect(0, 0, horiz ? largo : ancho, horiz ? ancho : largo);
      const origen = (horiz ? stage.x() : stage.y()) - REGLA;   // posición en la regla del 0 del dibujo
      const u0 = (-origen / k) * f, u1 = ((largo - origen) / k) * f;
      x.strokeStyle = '#9aa5b8'; x.fillStyle = '#cfd6df'; x.font = '10px sans-serif'; x.lineWidth = 1; x.textBaseline = 'top';
      for (let i = Math.floor(u0 / menor); i * menor <= u1; i++) {
        const u = i * menor, pos = Math.round(origen + (u / f) * k) + .5, mayor = i % 5 === 0, tam = mayor ? ancho : ancho * .35;
        x.beginPath();
        if (horiz) { x.moveTo(pos, ancho); x.lineTo(pos, ancho - tam); } else { x.moveTo(ancho, pos); x.lineTo(ancho - tam, pos); }
        x.stroke();
        if (mayor) {
          const txt = String(+u.toFixed(6));
          if (horiz) x.fillText(txt, pos + 3, 2); else { x.save(); x.translate(2, pos + 3); x.rotate(Math.PI / 2); x.fillText(txt, 0, 0); x.restore(); }
        }
      }
      if (ptr) {   // marca roja donde está el puntero
        x.strokeStyle = '#ff5a1f'; x.beginPath();
        if (horiz) { x.moveTo(ptr.x - REGLA + .5, 0); x.lineTo(ptr.x - REGLA + .5, ancho); } else { x.moveTo(0, ptr.y - REGLA + .5); x.lineTo(ancho, ptr.y - REGLA + .5); }
        x.stroke();
      }
    };
    pinta(ch, true); pinta(cv, false);
    const eq = $('#t-grid-eq'); if (eq) eq.textContent = e ? '≈ ' + fmtNum(pasoGrilla() * f) + ' ' + un : '';
    $('#ve-regla-esq').textContent = un;
  }
  function enlazarVista() {
    ['#t-grid', '#t-reglas', '#t-guias'].forEach(q => $(q) && $(q).addEventListener('change', () => { if (q === '#t-reglas') encuadrar(); else refrescarVista(); }));
    $('#t-grid-n').addEventListener('input', refrescarVista);
    $('#t-unidad').addEventListener('change', e => { if (state.escala) commit(undefined, undefined, { ...state.escala, unit: e.target.value }); });
    $('#ve-escala-x').addEventListener('click', () => { if (state.escala) commit(undefined, undefined, null); });
    window.addEventListener('mouseleave', () => { ptr = null; dibujarReglas(); });
  }
  // Marcador «Editar gráfico»: aparece al acercar el puntero a la esquina superior izquierda de un gráfico del Editor colocado en el plano
  function iniciarMarcadorGrafico() {
    const lienzo = $('#ve-lienzo'); if (!lienzo || !(C.graficos || []).length) return;
    const b = document.createElement('button'); b.type = 'button'; b.className = 've-editar-gr';
    b.innerHTML = '<i class="fa-solid fa-pen"></i> Editar gráfico'; lienzo.appendChild(b);
    let destino = null;
    const ocultar = () => { b.style.display = 'none'; destino = null; };
    lienzo.addEventListener('mouseleave', ocultar);
    lienzo.addEventListener('mousemove', e => {
      if (!stage || !modoSel()) { ocultar(); return; }
      const r = lienzo.getBoundingClientRect(), cr = stage.container().getBoundingClientRect(), px = e.clientX - r.left, py = e.clientY - r.top;
      let mejor = null, dm = 80;
      state.shapes.forEach(d => {
        if (d.type !== 'image') return;
        const g = C.graficos.find(x => x.src === d.src), nodo = nodes[d.id]; if (!g || !nodo) return;
        const o = nodo.getAbsoluteTransform().point({ x: 0, y: 0 }), cx = o.x + cr.left - r.left, cy = o.y + cr.top - r.top;
        const dist = Math.hypot(px - cx - 40, py - cy - 14);
        if (dist < dm) { dm = dist; mejor = { g, cx, cy }; }
      });
      if (!mejor) { if (e.target !== b) ocultar(); return; }
      destino = mejor.g;
      b.style.display = 'inline-flex'; b.style.left = Math.max(2, mejor.cx + 4) + 'px'; b.style.top = Math.max(2, mejor.cy + 4) + 'px';
    });
    b.addEventListener('click', async () => {
      if (!destino) return;
      const url = '/graficos/abrir?proyecto=' + C.proyecto + '&id=' + destino.id;
      const ok = await guardar(); if (ok !== false) location.href = url;
    });
  }
  function refrescarVista() { if (glayer) glayer.batchDraw(); dibujarReglas(); pintarGuias(); if (nodosGrupo) pintarNodos(); }
  function marcarPuntero() { ptr = stage.getPointerPosition(); dibujarReglas(); }


  // ---------- guías (estilo Inkscape) ----------
  // state.guides = [{id, eje:'v'|'h', pos (px del dibujo), visible, locked}]. Se arrastran desde las reglas, se mueven
  // arrastrándolas (soltarlas sobre la regla las quita) y los puntos y las marcas se imantan a ellas. No salen en el PNG.
  const guiasOn = () => !$('#t-guias') || $('#t-guias').checked;
  const nuevaGuia = (eje, pos) => ({ id: 'g' + Date.now().toString(36) + (seq++), eje, pos, visible: true, locked: false });
  const unidadesPorPx = () => { const e = state.escala; return e ? e.mmPorPx / UNIDADES[e.unit] : 1; };
  const unidadGuia = () => state.escala ? state.escala.unit : 'px';
  function cambiarGuias(nuevas) {
    state.past.push(snap()); if (state.past.length > 100) state.past.shift();
    state.future = []; state.guides = nuevas; dirty(true); pintarGuias();
  }
  function pintarGuias() {
    const ov = $('#ve-guias'); if (!ov || !stage) return;
    const R = reglasOn() ? REGLA : 0, k = stage.scaleX(), on = guiasOn();
    ov.style.top = R + 'px'; ov.style.left = R + 'px'; ov.hidden = !on;
    if (on) {
      const ids = new Set();
      (state.guides || []).forEach(g => {
        if (g.visible === false) return;
        ids.add(g.id);
        let el = ov.querySelector('[data-id="' + g.id + '"]');
        if (!el) { el = document.createElement('div'); el.dataset.id = g.id; ov.appendChild(el); }
        el.className = 've-guia ve-guia-' + g.eje + (g.locked ? ' bloqueada' : '');
        el.title = (g.locked ? 'Guía bloqueada' : 'Arrastra para mover; suéltala sobre la regla para quitarla') + ' · ' + fmtNum(g.pos * unidadesPorPx()) + ' ' + unidadGuia();
        if (g.eje === 'v') el.style.left = (stage.x() - R + g.pos * k - 4) + 'px'; else el.style.top = (stage.y() - R + g.pos * k - 4) + 'px';
      });
      [...ov.children].forEach(el => { if (!ids.has(el.dataset.id) && !el.classList.contains('ve-guia-nueva')) el.remove(); });
    }
    pintarListaGuias();
  }
  function pintarListaGuias() {
    const ul = $('#ve-guias-lista'); if (!ul) return;
    if (ul.contains(document.activeElement) && document.activeElement.tagName === 'INPUT') return;   // no pisar lo que se está escribiendo
    const f = unidadesPorPx(), un = unidadGuia();
    ul.innerHTML = (state.guides || []).map((g, i) =>
      '<li class="ve-capa ve-guia-item' + (g.visible === false ? ' oculta' : '') + (g.locked ? ' bloqueada' : '') + '" data-id="' + g.id + '">' +
      '<button type="button" class="ve-cb" data-op="ver" title="' + (g.visible === false ? 'Mostrar' : 'Ocultar') + ' guía">' + ico(g.visible === false ? IC.ojoNo : IC.ojo) + '</button>' +
      '<button type="button" class="ve-cb" data-op="bloq" title="' + (g.locked ? 'Desbloquear' : 'Bloquear') + ' guía">' + ico(g.locked ? IC.candado : IC.abierto) + '</button>' +
      '<span class="ve-capa-nombre">' + (g.eje === 'v' ? 'Vertical' : 'Horizontal') + ' ' + (i + 1) + '</span>' +
      '<input type="number" step="any" class="ve-guia-pos" value="' + +(g.pos * f).toFixed(3) + '" aria-label="Posición de la guía" ' + (g.locked ? 'disabled' : '') + '><small class="text-muted">' + un + '</small>' +
      '<button type="button" class="ve-cb ve-cb-del" data-op="borrar" title="Eliminar guía">' + ico(IC.basura) + '</button></li>').join('') ||
      '<li class="text-muted text-sm">Sin guías. Arrástralas desde las reglas o usa los botones.</li>';
  }
  function enlazarGuias() {
    const cont = $('#ve-lienzo'), ov = $('#ve-guias'); if (!cont || !ov) return;
    const aDibujo = (e, eje) => {
      const r = cont.getBoundingClientRect(), k = stage.scaleX();
      return eje === 'v' ? (e.clientX - r.left - stage.x()) / k : (e.clientY - r.top - stage.y()) / k;
    };
    const sobreRegla = (e) => { const r = cont.getBoundingClientRect(), R = reglasOn() ? REGLA : 0; return e.clientX - r.left < R || e.clientY - r.top < R || e.clientX < r.left || e.clientY < r.top || e.clientX > r.right || e.clientY > r.bottom; };
    // arrastre genérico: guia = objeto existente (se mueve) o {eje} nuevo
    function arrastrar(ev, eje, existente) {
      ev.preventDefault();
      const antes = clone(state.guides || []);
      let g = existente, vivo = null;
      if (!g) { g = nuevaGuia(eje, aDibujo(ev, eje)); }
      const el = document.createElement('div'); el.className = 've-guia ve-guia-nueva ve-guia-' + eje; ov.appendChild(el);
      const mover = e => {
        g.pos = aDibujo(e, eje); vivo = e;
        const R = reglasOn() ? REGLA : 0, k = stage.scaleX();
        if (eje === 'v') el.style.left = (stage.x() - R + g.pos * k - 4) + 'px'; else el.style.top = (stage.y() - R + g.pos * k - 4) + 'px';
        el.classList.toggle('descartar', sobreRegla(e));
        status('Guía ' + (eje === 'v' ? 'vertical' : 'horizontal') + ': ' + fmtNum(g.pos * unidadesPorPx()) + ' ' + unidadGuia() + (sobreRegla(e) ? ' (suelta para quitar)' : ''));
      };
      const fin = e => {
        document.removeEventListener('mousemove', mover); document.removeEventListener('mouseup', fin);
        el.remove();
        const quitar = sobreRegla(e);
        let lista;
        if (existente) lista = quitar ? antes.filter(x => x.id !== g.id) : antes.map(x => x.id === g.id ? { ...x, pos: g.pos } : x);
        else lista = quitar ? antes : [...antes, g];
        // Si la guía existente no se movió, no se registra cambio
        if (existente) existente.pos = antes.find(x => x.id === g.id).pos;
        if (JSON.stringify(lista) !== JSON.stringify(antes)) { state.guides = antes; cambiarGuias(lista); } else pintarGuias();
        status('');
      };
      document.addEventListener('mousemove', mover); document.addEventListener('mouseup', fin);
      mover(ev);
    }
    $('#ve-regla-h').addEventListener('mousedown', e => { if (e.button === 0) arrastrar(e, 'h', null); });   // la regla de arriba da guías horizontales
    $('#ve-regla-v').addEventListener('mousedown', e => { if (e.button === 0) arrastrar(e, 'v', null); });
    ov.addEventListener('mousedown', e => {
      const el = e.target.closest('.ve-guia'); if (!el || e.button !== 0) return;
      const g = (state.guides || []).find(x => x.id === el.dataset.id); if (!g || g.locked) return;
      // Se trabaja sobre una copia: la guía real se actualiza al soltar
      arrastrar(e, g.eje, { ...g });
    });
    // Lista y botones del panel
    const posCentro = eje => (eje === 'v' ? state.W : state.H) / 2;
    $('#ve-guia-v').addEventListener('click', () => cambiarGuias([...(state.guides || []), nuevaGuia('v', posCentro('v'))]));
    $('#ve-guia-h').addEventListener('click', () => cambiarGuias([...(state.guides || []), nuevaGuia('h', posCentro('h'))]));
    $('#ve-guias-x').addEventListener('click', async () => {
      if (!(state.guides || []).length) return;
      const ok = await window.confirmarModal({ titulo: '¿Quitar todas las guías?', mensaje: 'Se eliminan las ' + state.guides.length + ' guías de esta lámina. Puedes deshacerlo con Ctrl+Z.', aceptar: 'Sí, quitar', iconoBoton: 'fa-trash-can' });
      if (ok) cambiarGuias([]);
    });
    const ul = $('#ve-guias-lista');
    ul.addEventListener('click', e => {
      const b = e.target.closest('[data-op]'), li = e.target.closest('.ve-guia-item'); if (!b || !li) return;
      const op = b.dataset.op, id = li.dataset.id;
      const lista = clone(state.guides || []), g = lista.find(x => x.id === id); if (!g) return;
      if (op === 'ver') g.visible = g.visible === false; else if (op === 'bloq') g.locked = !g.locked;
      else if (op === 'borrar') lista.splice(lista.indexOf(g), 1);
      cambiarGuias(lista);
    });
    ul.addEventListener('change', e => {
      const inp = e.target.closest('.ve-guia-pos'); if (!inp) return;
      const id = inp.closest('.ve-guia-item').dataset.id, lista = clone(state.guides || []), g = lista.find(x => x.id === id), v = parseFloat(inp.value);
      if (!g || !isFinite(v)) { pintarListaGuias(); return; }
      g.pos = v / unidadesPorPx(); inp.blur(); cambiarGuias(lista);
    });
    // Flechas sobre una guía no se usan: las flechas del teclado mueven las marcas
  }

  // ---------- escala y medidas ----------
  let escalaRender;   // al dibujar otra lámina, su propia escala
  const escalaEf = () => escalaRender !== undefined ? escalaRender : state.escala;
  function textoMedida(d, len) {
    const e = escalaEf(); if (!e || !e.mmPorPx) return 'sin escala';
    const u = UNIDADES[d.unit] ? d.unit : e.unit;
    return fmtNum(len * e.mmPorPx / UNIDADES[u]) + ' ' + u;
  }
  function pedirMedida({ titulo, texto, valor = '', unidad = 'cm' }) {
    return new Promise(res => {
      const m = $('#ve-modal-medida'); if (!m) return res(null);
      $('#mm-titulo').textContent = titulo; $('#mm-texto').textContent = texto; $('#mm-valor').value = valor; $('#mm-unidad').value = unidad; $('#mm-error').hidden = true;
      m.classList.add('abierto'); m.setAttribute('aria-hidden', 'false'); setTimeout(() => $('#mm-valor').focus(), 30);
      const fin = r => { m.classList.remove('abierto'); m.setAttribute('aria-hidden', 'true'); m.onkeydown = null; $('#mm-ok').onclick = null; $('#mm-cancelar').onclick = null; res(r); };
      const aceptar = () => { const v = parseFloat($('#mm-valor').value); if (!(v > 0)) { $('#mm-error').textContent = 'Escribe una medida mayor que cero'; $('#mm-error').hidden = false; return; } fin({ valor: v, unidad: $('#mm-unidad').value }); };
      $('#mm-ok').onclick = aceptar; $('#mm-cancelar').onclick = () => fin(null);
      m.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); aceptar(); } else if (e.key === 'Escape') fin(null); e.stopPropagation(); };
    });
  }
  async function terminarMedida(d) {
    const len = Math.hypot(d.points[2], d.points[3]);
    if (!state.escala) {
      const r = await pedirMedida({ titulo: 'Medida de referencia', texto: 'Indica cuánto mide en la realidad el segmento que acabas de trazar. Con esa referencia se calculan las demás medidas del dibujo.' });
      if (!r) { layer.batchDraw(); status('Medición cancelada'); return; }
      d.unit = r.unidad; state.selId = d.id;
      commit([...clone(state.shapes), d], undefined, { mmPorPx: r.valor * UNIDADES[r.unidad] / len, unit: r.unidad });
    } else { d.unit = state.escala.unit; state.selId = d.id; commit([...clone(state.shapes), d]); }
  }
  function pintarEscala() {
    const t = $('#ve-escala-txt'); if (!t) return;
    const e = state.escala;
    t.textContent = e ? '1 px = ' + fmtNum(e.mmPorPx) + ' mm' : 'Sin escala: traza una medida de referencia';
    $('#t-unidad').disabled = !e; if (e) $('#t-unidad').value = e.unit;
    $('#ve-escala-x').disabled = !e;
    dibujarReglas();
  }

  // ---------- guardado ----------
  // Dibuja las marcas visibles en una imagen de ancho x alto píxeles. El contenido se ajusta (sin deformarse) y se centra;
  // el espacio sobrante y el fondo transparente se controlan con `transparente`.
  async function renderizar({ ancho, alto, mime = 'image/png', calidad = 0.92, transparente = false, datos, iconos = false }) {
    const shapes = datos ? datos.shapes : state.shapes, layers = datos ? datos.layers || [] : state.layers, escala = datos ? datos.escala || null : state.escala;
    await Promise.all(shapes.filter(s => s.type === 'image').map(s => new Promise(res => {
      const e = imgCache[s.src]; if (e && e.ok) return res();
      const img = new Image(); img.onload = () => { imgCache[s.src] = { img, ok: true }; res(); }; img.onerror = res; img.src = s.src;
    })));
    const k = Math.min(ancho / state.W, alto / state.H);
    const cont = document.createElement('div'); cont.style.cssText = 'position:fixed;left:-99999px;top:0';
    document.body.appendChild(cont);
    const st = new Konva.Stage({ container: cont, width: ancho, height: alto });
    const ly = new Konva.Layer(); st.add(ly);
    const opaco = !transparente || mime === 'image/jpeg';
    if (opaco) ly.add(new Konva.Rect({ width: ancho, height: alto, fill: '#ffffff' }));
    const g = new Konva.Group({ x: (ancho - state.W * k) / 2, y: (alto - state.H * k) / 2, scaleX: k, scaleY: k });
    ly.add(g);
    if (bg instanceof Konva.Image) g.add(new Konva.Image({ image: bg.image(), width: state.W, height: state.H }));
    else if (!transparente) g.add(fondoRect(state.W, state.H));   // fondo de plantilla del Editor
    escalaRender = escala; enExport = true;
    try { ordenar(shapes, layers).filter(d => d.type !== 'link' || (iconos && d.showIcon && d.icon !== 'none')).forEach(d => { const n = crearNodo(d); n.draggable(false); g.add(n); }); }   // las capas ocultas no salen
    finally { escalaRender = undefined; enExport = false; }
    ly.draw();
    const url = st.toDataURL({ mimeType: mime, quality: calidad, pixelRatio: 1 });
    st.destroy(); cont.remove();
    return url;
  }
  // Enlaces externos visibles, en píxeles de una exportación ancho × alto (los internos no van en el PDF)
  function enlacesExternos(ancho, alto) {
    const k = Math.min(ancho / state.W, alto / state.H), ox = (ancho - state.W * k) / 2, oy = (alto - state.H * k) / 2;
    return state.shapes.filter(s => s.type === 'link' && (s.kind || 'ext') === 'ext' && s.url && (capaPor(s.layer) || {}).visible !== false)
      .map(s => ({ x: ox + s.x * k, y: oy + s.y * k, w: s.w * k, h: s.h * k, url: s.url }));
  }
  // Imagen que se guarda con el trabajo (la que muestran la lámina, el PDF y las descargas)
  const exportarPng = () => renderizar({ ancho: state.W, alto: state.H, mime: C.guardado === 'png' ? 'image/png' : 'image/jpeg', calidad: 0.92 });
  let enCurso = null, autoPausado = false, tAuto = null;
  const autoOn = () => { const c = $('#t-auto'); return !!c && c.checked; };
  function programarAuto() {
    clearTimeout(tAuto);
    if (!autoOn() || autoPausado) return;
    tAuto = setTimeout(async () => {
      const ocupado = dibujando || poli || marco || document.querySelector('.modal-overlay.abierto') || (document.activeElement && ['TEXTAREA', 'INPUT', 'SELECT'].includes(document.activeElement.tagName));
      if (!sucio) return;
      if (ocupado) { programarAuto(); return; }
      await guardar({ auto: true });
    }, 12000);
  }
  // Una sola operación de guardado a la vez (manual o automática)
  async function guardar(op = {}) {
    const previa = enCurso;
    const p = (async () => { if (previa) { try { await previa; } catch (_) {} } return guardarReal(op && op.auto ? op : {}); })();
    enCurso = p;
    try { return await p; } finally { if (enCurso === p) enCurso = null; }
  }
  async function guardarReal(op) {
    if (op.auto && !sucio) return true;
    status(op.auto ? 'Autoguardando…' : 'Guardando…');
    try {
      volcarActiva();
      const lista = state.slides ? state.slides.filter(x => x.dirty) : [null];
      for (const sl of lista) {
        const datos = sl ? sl.data : { shapes: state.shapes, layers: state.layers, escala: state.escala, guides: state.guides };
        const png = await renderizar({ ancho: state.W, alto: state.H, mime: C.guardado === 'png' ? 'image/png' : 'image/jpeg', calidad: 0.92, datos });
        const cuerpo = { lamina: C.lamina, json: JSON.stringify({ v: 2, w: state.W, h: state.H, shapes: datos.shapes, layers: datos.layers, escala: datos.escala || null, guides: datos.guides || [] }), png };
        if (sl) { cuerpo.hoja = sl.id; cuerpo.version = sl.version || ''; }
        const enviar = async () => (await fetch((C.api && C.api.marcas) || '/api/vplan/marcas', { method: 'POST', body: new URLSearchParams(cuerpo) })).json();
        let d = await enviar();
        if (!d.success && d.conflicto && op.auto) { autoPausado = true; status('Autoguardado en pausa: la lámina cambió en otra sesión. Guarda a mano para decidir.'); return false; }
        if (!d.success && d.conflicto) {   // otra sesión guardó esta lámina mientras estaba abierta
          const ok = await window.confirmarModal({ titulo: 'La lámina cambió en otra sesión', mensaje: d.error + ' Si sigues, tu versión reemplaza a la guardada. Para no perder nada, cancela, exporta tu trabajo y recarga la página.', aceptar: 'Reemplazar con mi versión', iconoBoton: 'fa-floppy-disk' });
          if (!ok) { status('Guardado cancelado: la lámina cambió en otra sesión'); return false; }
          cuerpo.forzar = '1'; d = await enviar();
        }
        if (!d.success) { status('Error: ' + (d.error || 'no se pudo guardar')); return false; }
        if (sl) { sl.dirty = false; if (d.version) sl.version = d.version; }
      }
      autoPausado = false;
      dirty(false); const hh = new Date(); status(op.auto ? 'Autoguardado ✔ ' + String(hh.getHours()).padStart(2, '0') + ':' + String(hh.getMinutes()).padStart(2, '0') : (lista.length || !state.slides ? 'Guardado ✔' : 'Sin cambios')); pintarLaminas(); return true;
    } catch (e) { status('Error al guardar'); return false; }
  }


  // ---------- láminas del gráfico (solo en el Editor): cada una con sus marcas, capas e historial ----------
  const laminaAct = () => state.slides && state.slides[state.idx];
  function volcarActiva() { const sl = laminaAct(); if (!sl) return; sl.data = { shapes: state.shapes, layers: state.layers, escala: state.escala, guides: state.guides }; sl.past = state.past; sl.future = state.future; }
  function cargarLamina(i) {
    const sl = state.slides[i]; state.idx = i;
    state.shapes = clone(sl.data.shapes || []); state.layers = clone(sl.data.layers || []); state.escala = sl.data.escala || null; state.guides = clone(sl.data.guides || []);
    state.past = sl.past; state.future = sl.future; state.selId = null; state.sel = []; capaActiva = null; celdasSel = { id: null, set: new Set() };
    if (poli) { poli.nodo.destroy(); poli = null; }
    asegurarCapas(); render(); pintarLaminas(); pintarGuias();
  }
  let tMini = null;
  function programarMiniatura() { if (state.slides) { clearTimeout(tMini); tMini = setTimeout(actualizarMiniatura, 700); } }
  async function actualizarMiniatura() {
    clearTimeout(tMini); const sl = laminaAct(); if (!sl) return;
    try { sl.thumb = await renderizar({ ancho: 240, alto: Math.max(20, Math.round(240 * state.H / state.W)), mime: 'image/jpeg', calidad: 0.7, datos: { shapes: state.shapes, layers: state.layers, escala: state.escala } }); } catch (_) {}
    pintarLaminas();
  }
  function refrescarPend() { sucio = state.slides ? state.slides.some(x => x.dirty) : sucio; $('#ve-guardar').classList.toggle('ve-pend', sucio); }
  async function irALamina(i) {
    if (!state.slides || i < 0 || i >= state.slides.length || i === state.idx) return;
    if (laminaAct().dirty) await actualizarMiniatura();
    volcarActiva(); cargarLamina(i);
  }
  const apiLamina = async datos => {
    try {
      const r = await fetch(C.api.lamina, { method: 'POST', body: new URLSearchParams({ lamina: C.lamina, ...datos }) });
      return await r.json();
    } catch (_) { return { success: false, error: 'Error de conexión' }; }
  };
  const nombreLamina = (sl, i) => (sl.nombre && sl.nombre.trim()) || ('Lámina ' + (i + 1));
  function pintarLaminas() {
    const ul = $('#ve-lam-lista'); if (!ul || !state.slides) return;
    ul.innerHTML = state.slides.map((sl, i) => '<li class="ve-lam' + (i === state.idx ? ' activa' : '') + '" data-i="' + i + '" draggable="true" title="Clic: abrir · doble clic en el nombre: renombrar · arrastra para reordenar">' +
      '<span class="ve-lam-n">' + (i + 1) + '</span>' + (sl.thumb ? '<img src="' + esc(sl.thumb) + '" alt="">' : '<span class="ve-lam-vacia"></span>') +
      '<span class="ve-lam-nombre">' + esc(nombreLamina(sl, i)) + '</span>' + (sl.dirty ? '<span class="ve-lam-pend" title="Con cambios sin guardar">●</span>' : '') + '</li>').join('');
    $('#ve-lam-del').disabled = state.slides.length < 2;
    const n = $('#ve-lam-total'); if (n) n.textContent = state.slides.length + ' lámina(s)';
    const ex = $('#ex-alcance-campo'); if (ex) ex.hidden = state.slides.length < 2;
  }
  async function nuevaLamina() {
    volcarActiva(); const d = await apiLamina({ accion: 'crear', despues: laminaAct().id });
    if (!d.success) { status('Error: ' + (d.error || 'no se pudo crear')); return; }
    state.slides.splice(state.idx + 1, 0, { id: d.id, nombre: d.nombre, data: { shapes: [], layers: [], escala: null, guides: [] }, past: [], future: [], thumb: '', dirty: true, version: d.version });   // en blanco pero «con cambios»: así se guarda y no se pierde al exportar
    cargarLamina(state.idx + 1); refrescarPend(); status('Lámina creada');
  }
  async function duplicarLamina() {
    if (!(await guardar())) return;
    const d = await apiLamina({ accion: 'duplicar', hoja: laminaAct().id });
    if (!d.success) { status('Error: ' + (d.error || 'no se pudo duplicar')); return; }
    state.slides.splice(state.idx + 1, 0, { id: d.id, nombre: d.nombre, data: d.marcas || { shapes: [], layers: [], escala: null }, past: [], future: [], thumb: d.png || '', dirty: false, version: d.version || '' });
    volcarActiva(); cargarLamina(state.idx + 1); status('Lámina duplicada');
  }
  async function eliminarLamina() {
    if (state.slides.length < 2) return;
    const sl = laminaAct();
    const ok = await window.confirmarModal({ titulo: '¿Eliminar «' + nombreLamina(sl, state.idx) + '»?', mensaje: 'Se borran sus marcas e imágenes. No se puede deshacer.', aceptar: 'Sí, eliminar', iconoBoton: 'fa-trash-can' });
    if (!ok) return;
    const d = await apiLamina({ accion: 'eliminar', hoja: sl.id });
    if (!d.success) { status('Error: ' + (d.error || 'no se pudo eliminar')); return; }
    state.slides.splice(state.idx, 1);
    cargarLamina(Math.min(state.idx, state.slides.length - 1)); refrescarPend(); status('Lámina eliminada');
  }
  function renombrarLamina(i) {
    const li = $('#ve-lam-lista').querySelector('[data-i="' + i + '"]'), sl = state.slides[i]; if (!li) return;
    const sp = li.querySelector('.ve-lam-nombre'), inp = document.createElement('input');
    inp.type = 'text'; inp.className = 've-capa-input'; inp.maxLength = 60; inp.value = sl.nombre || ''; inp.placeholder = 'Lámina ' + (i + 1);
    sp.replaceWith(inp); inp.focus(); inp.select(); li.draggable = false;
    let hecho = false;
    const fin = async ok => {
      if (hecho) return; hecho = true;
      const v = inp.value.trim().slice(0, 60);
      if (ok && v !== (sl.nombre || '')) { const d = await apiLamina({ accion: 'renombrar', hoja: sl.id, nombre: v }); if (d.success) sl.nombre = d.nombre; else status('Error: ' + (d.error || 'no se pudo renombrar')); }
      pintarLaminas();
    };
    inp.addEventListener('blur', () => fin(true));
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') fin(true); if (e.key === 'Escape') fin(false); e.stopPropagation(); });
  }
  function enlazarLaminas() {
    const ul = $('#ve-lam-lista'); if (!ul) return;
    $('#ve-lam-nueva').addEventListener('click', nuevaLamina); $('#ve-lam-dup').addEventListener('click', duplicarLamina); $('#ve-lam-del').addEventListener('click', eliminarLamina);
    ul.addEventListener('click', e => { const li = e.target.closest('.ve-lam'); if (li && !e.target.closest('input')) irALamina(+li.dataset.i); });
    ul.addEventListener('dblclick', e => { const sp = e.target.closest('.ve-lam-nombre'); if (sp) renombrarLamina(+sp.closest('.ve-lam').dataset.i); });
    let origen = null;
    ul.addEventListener('dragstart', e => { const li = e.target.closest('.ve-lam'); if (!li) return; origen = +li.dataset.i; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(origen)); } catch (_) {} });
    ul.addEventListener('dragover', e => { if (origen === null) return; e.preventDefault(); const li = e.target.closest('.ve-lam'); ul.querySelectorAll('.ve-lam').forEach(x => x.classList.toggle('destino', x === li)); });
    ul.addEventListener('dragend', () => { origen = null; ul.querySelectorAll('.destino').forEach(x => x.classList.remove('destino')); });
    ul.addEventListener('drop', async e => {
      e.preventDefault(); const li = e.target.closest('.ve-lam'); if (origen === null || !li) return;
      const destino = +li.dataset.i, de = origen; origen = null;
      if (destino === de) { pintarLaminas(); return; }
      volcarActiva(); const activa = laminaAct().id;
      const [x] = state.slides.splice(de, 1); state.slides.splice(destino, 0, x);
      state.idx = state.slides.findIndex(z => z.id === activa); pintarLaminas();
      const d = await apiLamina({ accion: 'ordenar', orden: state.slides.map(z => z.id).join(',') });
      if (!d.success) status('Error: ' + (d.error || 'no se pudo reordenar'));
    });
  }

  // ---------- símbolos y gráficos del proyecto: se insertan como imagen (una copia propia del archivo) ----------
  async function aBlob(url, maxLado) {
    if (!/\.svg(\?|$)/i.test(url)) { const r = await fetch(url); if (!r.ok) throw new Error('No se pudo leer la imagen'); return r.blob(); }
    const img = await new Promise((ok, ko) => { const i = new Image(); i.onload = () => ok(i); i.onerror = ko; i.src = url; });
    const w = img.naturalWidth || 128, h = img.naturalHeight || 128, k = maxLado / Math.max(w, h);
    const cv = document.createElement('canvas'); cv.width = Math.round(w * k); cv.height = Math.round(h * k);
    cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
    return new Promise(ok => cv.toBlob(ok, 'image/png'));
  }
  async function insertarDesdeUrl(url, fraccion, vinculo = null) {
    if (!capaOk()) return;
    status('Insertando…');
    try { const b = await aBlob(url, 512 * 2); await subirImagen(new File([b], 'imagen.png', { type: b.type || 'image/png' }), fraccion, vinculo); }
    catch (e) { status('No se pudo insertar: ' + (e.message || 'error')); }
  }
  function pintarSimbolos() {
    const cont = $('#sim-lista'); if (!cont) return;
    const q = ($('#sim-buscar').value || '').trim().toLowerCase();
    const grupos = {};
    (C.simbolos || []).filter(x => !q || (x.nombre + ' ' + x.cat).toLowerCase().includes(q)).forEach(x => { (grupos[x.cat] = grupos[x.cat] || []).push(x); });
    cont.innerHTML = Object.keys(grupos).map(cat => '<h5>' + esc(cat) + '</h5><div class="ve-sims">' + grupos[cat].map(x =>
      '<button type="button" class="ve-sim" data-src="' + esc(x.src) + '"' + (x.ref ? ' data-ref="' + esc(x.ref) + '" data-v="' + esc(x.v || '') + '"' : '') + ' title="' + esc(x.nombre) + '"><img src="' + esc(x.src) + '" alt="" loading="lazy"><span>' + esc(x.nombre) + '</span></button>').join('') + '</div>').join('') || '<p class="text-muted text-sm">Sin resultados.</p>';
    $('#sim-cajetines').innerHTML = (C.cajetines || []).map((c, i) => '<button type="button" class="btn btn-sm btn-ghost" data-caj="' + i + '" title="' + (c.orientacion === 'banda' ? 'Banda inferior' : 'Tabla en la esquina inferior derecha') + '"><i class="fa-solid fa-table-cells"></i> ' + esc(c.nombre) + '</button>').join('');
    const sfCaja = $('#sim-sheetflow-caja');
    if (sfCaja) {
      sfCaja.hidden = !(C.sheetflow || []).length;
      $('#sim-sheetflow').innerHTML = (C.sheetflow || []).map((h, i) => '<button type="button" class="btn btn-sm btn-ghost" data-sf="' + i + '" title="Inserta una copia de la tabla (' + h.filas + ' renglones × ' + h.cols + ' columnas)"><i class="fa-solid fa-table-cells"></i> ' + esc(h.nombre) + '</button>').join('');
    }
    const gr = $('#sim-graficos');
    gr.innerHTML = (C.graficos || []).filter(x => !q || x.nombre.toLowerCase().includes(q)).map(x =>
      '<button type="button" class="ve-sim ve-sim-gr" data-src="' + esc(x.src) + '"' + (x.ref ? ' data-ref="' + esc(x.ref) + '" data-v="' + esc(x.v || '') + '"' : '') + ' title="Insertar «' + esc(x.nombre) + '» (vinculada: se actualiza si la editas)"><img src="' + esc(x.src) + '" alt="" loading="lazy"><span>' + esc(x.nombre) + '</span></button>').join('') || '<p class="text-muted text-sm">No hay otros gráficos guardados en este proyecto.</p>';
  }
  // Cajetín: tabla con los datos del dibujo (proyecto, título, fecha…). Si la lámina ya tiene uno de la misma plantilla, se actualiza
  function insertarCajetin(c) {
    if (!capaOk()) return;
    const hoy = new Date(), p2 = n => String(n).padStart(2, '0');
    const vars = { proyecto: C.proyectoNombre || '', titulo: C.nombre || '', fecha: p2(hoy.getDate()) + '/' + p2(hoy.getMonth() + 1) + '/' + hoy.getFullYear(), autor: C.usuario || '',
      lamina: String(state.slides ? state.idx + 1 : 1), total: String(state.slides ? state.slides.length : 1), escala: state.escala ? fmtNum(state.escala.mmPorPx) + ' mm/px' : '' };
    const val = t => String(t || '').replace(/\{(\w+)\}/g, (m, k) => k in vars ? vars[k] : m);
    const filas = (c.filas || []).map(([e, v]) => [e || '', val(v)]);
    if (!filas.length) return;
    const banda = c.orientacion === 'banda', tw = state.W * Math.min(1, Math.max(0.15, (c.ancho || 28) / 100));
    const d = { id: uid(), type: 'table', x: 0, y: 0, w: 1, h: 1, rows: banda ? 2 : filas.length, cols: banda ? filas.length : 2, header: banda, cajetin: c.id || 'base',
      fontSize: Math.max(10, Math.round(tw / (banda ? filas.length * 9 : 20))), fill: '#ffffff', headerFill: '#dbe5f1', textColor: '#111111', stroke: '#111111', strokeWidth: Math.max(1, Math.round(state.W / 1200)), rotation: 0, opacity: 100 };
    normalizarTabla(d);
    d.colW = banda ? Array(d.cols).fill(tw / d.cols) : [tw * 0.38, tw * 0.62];
    d.cells = banda ? [filas.map(f => f[0]), filas.map(f => f[1])] : filas.map(f => [f[0], f[1]]);
    if (!banda) { d.cellFill = {}; filas.forEach((_, r) => { d.cellFill[r + ',0'] = '#dbe5f1'; }); }
    d.w = suma(d.colW); ajustarFilas(d);
    const margen = state.W * 0.02;
    d.x = banda ? (state.W - d.w) / 2 : state.W - d.w - margen; d.y = state.H - d.h - margen;
    const resto = clone(state.shapes).filter(x => !(x.type === 'table' && x.cajetin === d.cajetin));
    state.selId = d.id; commit([...resto, d]); status('Cajetín «' + (c.nombre || '') + '» colocado');
  }
  // Tabla de SheetFlow: forma `table` del Editor con `sheetflow: id`; al abrir el gráfico se vuelve a leer (vínculo vivo)
  function tablaDeSheetflow(r, id) {
    const filas = r.filas.slice(0, 120), conTot = !!r.totales, nc = r.columnas.length;
    const cells = [r.columnas.map(c => c || ' ')].concat(filas, conTot ? [r.totales] : []);
    const fs = Math.max(10, Math.round(state.W / 90)), pd = Math.max(2, fs * 0.25);
    let colW = Array.from({ length: nc }, (_, c) => Math.min(fs * 28, Math.max(fs * 4, Math.max(...cells.map(f => String(f[c] || '').length)) * fs * 0.6 + 2 * pd)));
    const total = suma(colW), tope = state.W * 0.92;
    if (total > tope) colW = colW.map(w => w * tope / total);
    const e = r.estilo || {};
    const d = { id: uid(), type: 'table', x: 0, y: 0, w: 1, h: 1, rows: cells.length, cols: nc, header: true, fontSize: fs, fill: '#ffffff', headerFill: e.enc || '#1f3a5f', textColor: '#111111', stroke: '#444444', strokeWidth: 1, rotation: 0, opacity: 100,
      sheetflow: id, cells, colW, rowH: cells.map(() => fs * 2.2), cellText: {}, cellFill: {} };
    for (let c = 0; c < nc; c++) d.cellText['0,' + c] = e.encTxt || '#ffffff';
    if (conTot) for (let c = 0; c < nc; c++) d.cellFill[(cells.length - 1) + ',' + c] = '#e2e8f0';
    d.w = suma(colW); ajustarFilas(d);
    return d;
  }
  async function leerSheetflow(id) {
    const fd = new FormData(); fd.append('id', id);
    return (await fetch((C.api || {}).sheetflow || '/api/sheetflow/datos', { method: 'POST', body: fd })).json();
  }
  async function insertarSheetflow(h) {
    if (!capaOk() || !h) return;
    status('Cargando la tabla…');
    try {
      const r = await leerSheetflow(h.id);
      if (!r.success) { status('No se pudo leer la tabla: ' + (r.error || 'error')); return; }
      const d = tablaDeSheetflow(r, h.id);
      d.x = Math.max(0, (state.W - d.w) / 2); d.y = Math.max(state.H * 0.02, Math.min((state.H - d.h) / 2, state.H - d.h));
      state.selId = d.id; commit([...clone(state.shapes), d]);
      status('Tabla «' + r.nombre + '» insertada (vinculada: se actualiza al abrir el gráfico)');
    } catch (err) { status('No se pudo insertar la tabla'); }
  }
  // Al abrir: las tablas vinculadas toman los datos actuales de SheetFlow (conservan posición, giro y opacidad). Si ya no existe la hoja, se dejan como están
  async function refrescarSheetflow() {
    const lista = state.shapes.filter(x => x.type === 'table' && x.sheetflow);
    if (!lista.length) return;
    let cambiadas = 0;
    for (const id of [...new Set(lista.map(x => x.sheetflow))]) {
      try {
        const r = await leerSheetflow(id); if (!r.success) continue;
        const n = tablaDeSheetflow(r, id);
        state.shapes = state.shapes.map(x => {
          if (x.type !== 'table' || x.sheetflow !== id) return x;
          if (JSON.stringify(x.cells) === JSON.stringify(n.cells) && x.headerFill === n.headerFill) return x;
          cambiadas++;
          return Object.assign({}, n, { id: x.id, x: x.x, y: x.y, rotation: x.rotation, opacity: x.opacity, layer: x.layer, comment: x.comment });
        });
      } catch (err) { /* sin conexión: se queda la copia guardada */ }
    }
    if (cambiadas) { render(); dirty(true); status(cambiadas + ' tabla(s) actualizada(s) desde SheetFlow · guarda para conservar el cambio'); }
  }
  // Tabla del Punch list: forma `table` con `punch: cfg`; al abrir se vuelve a leer del módulo Punch
  async function leerPunch(cfg) {
    const fd = new FormData(); fd.append('lamina', C.lamina); fd.append('cfg', JSON.stringify(cfg));
    return (await fetch((C.api || {}).punch || '/api/graficos/punch', { method: 'POST', body: fd })).json();
  }
  // La tabla y sus fotos (imágenes agrupadas con ella, marcadas con punchFoto) se arman juntas; x,y = esquina de la tabla
  function tablaDePunch(r, x, y, grp) {
    const cells = [r.columnas.map(c => c || ' ')].concat(r.filas.length ? r.filas : [r.columnas.map((_, i) => i === 0 ? 'Sin pendientes' : '')]), nc = r.columnas.length;
    const fotos = r.fotos || [], conFoto = new Set(fotos.map(f => f[0] + 1)), colFoto = new Set(fotos.map(f => f[1]));
    const fs = Math.max(10, Math.round(state.W / 90)), pd = Math.max(2, fs * 0.25), altoFoto = fs * 6;
    let colW = Array.from({ length: nc }, (_, c) => colFoto.has(c) ? fs * 9 : Math.min(fs * 28, Math.max(fs * 5, Math.max(...cells.map(f => String(f[c] || '').length)) * fs * 0.6 + 2 * pd)));
    const total = suma(colW), tope = state.W * 0.92;
    if (total > tope) colW = colW.map(w => w * tope / total);
    const d = { id: uid(), type: 'table', x, y, w: 1, h: 1, rows: cells.length, cols: nc, header: true, fontSize: fs, fill: '#ffffff', headerFill: '#1f3a5f', textColor: '#111111', stroke: '#444444', strokeWidth: 1, rotation: 0, opacity: 100,
      punch: r.cfg, cells, colW, rowH: cells.map((_, i) => conFoto.has(i) ? altoFoto : fs * 2.2), cellText: {}, cellFill: {} };
    for (let c = 0; c < nc; c++) d.cellText['0,' + c] = '#ffffff';
    d.w = suma(colW); ajustarFilas(d);
    const out = [d];
    if (fotos.length) {
      d.grp = grp || ('g' + uid());
      fotos.forEach(([fila, col, src, iw, ih]) => {
        const cw = d.colW[col] - 2 * pd, ch = d.rowH[fila + 1] - 2 * pd, k = Math.min(cw / iw, ch / ih), w = iw * k, h = ih * k;
        out.push({ id: uid(), type: 'image', src, punchFoto: true, grp: d.grp, x: x + suma(d.colW, col) + (d.colW[col] - w) / 2, y: y + suma(d.rowH, fila + 1) + (d.rowH[fila + 1] - h) / 2, w, h, rotation: 0, opacity: 100 });
      });
    }
    return out;
  }
  let punchEditando = null;   // id de la tabla que se reconfigura (null = insertar nueva)
  function abrirPunchCfg(forma) {
    punchEditando = forma ? forma.id : null;
    const cfg = forma ? forma.punch : { columnas: C.punchBase, estado: 'pendientes', severidad: '', contratista: '', limite: 40 };
    $('#sim-punch-cols').innerHTML = (C.punchCols || []).map(x => '<label class="ve-chk"><input type="checkbox" value="' + esc(x.k) + '"' + (cfg.columnas.includes(x.k) ? ' checked' : '') + '> ' + esc(x.etq) + '</label>').join(' ');
    $('#sim-punch-estado').value = cfg.estado; $('#sim-punch-sev').value = cfg.severidad || ''; $('#sim-punch-con').value = cfg.contratista || ''; $('#sim-punch-lim').value = Math.min(120, cfg.limite || 40);
    $('#sim-punch-ok').textContent = forma ? 'Aplicar' : 'Insertar';
    $('#sim-punch-cfg').hidden = false;
    const t = document.querySelector('.ve-tabs [data-tab=simbolos]'); if (t) t.click();
  }
  async function aplicarPunchCfg() {
    if (!capaOk()) return;
    const columnas = [...$('#sim-punch-cols').querySelectorAll('input:checked')].map(i => i.value);
    if (!columnas.length) { status('Marca al menos una columna'); return; }
    status('Cargando el Punch list…');
    try {
      const r = await leerPunch({ columnas, estado: $('#sim-punch-estado').value, severidad: $('#sim-punch-sev').value, contratista: $('#sim-punch-con').value, limite: Math.min(120, +$('#sim-punch-lim').value || 40) });
      if (!r.success) { status('No se pudo leer el Punch list: ' + (r.error || 'error')); return; }
      if (punchEditando) {
        const vieja = state.shapes.find(x => x.id === punchEditando);
        if (!vieja) { status('La tabla ya no existe'); return; }
        commit(reemplazarPunch(clone(state.shapes), vieja, r));
        status('Tabla del Punch list actualizada');
      } else {
        const t = tablaDePunch(r, 0, 0), n = t[0];
        const dx = Math.max(0, (state.W - n.w) / 2), dy = Math.max(state.H * 0.02, Math.min((state.H - n.h) / 2, state.H - n.h));
        const nuevas = tablaDePunch(r, dx, dy);
        state.selId = nuevas[0].id; commit([...clone(state.shapes), ...nuevas]);
        status('Tabla del Punch list insertada (se actualiza al abrir el gráfico)');
      }
      $('#sim-punch-cfg').hidden = true; punchEditando = null;
    } catch (err) { status('No se pudo leer el Punch list'); }
  }
  // Cambia una tabla del Punch list por otra leída (conserva posición, opacidad, capa y comentario) y renueva sus fotos
  function reemplazarPunch(shapes, vieja, r) {
    const n = tablaDePunch(r, vieja.x, vieja.y, vieja.grp);
    Object.assign(n[0], { id: vieja.id, opacity: vieja.opacity, layer: vieja.layer, comment: vieja.comment });
    const fuera = x => x.id === vieja.id || (x.punchFoto && vieja.grp && x.grp === vieja.grp);
    const i = shapes.findIndex(x => x.id === vieja.id);
    const resto = shapes.filter(x => !fuera(x));
    resto.splice(Math.max(0, Math.min(i, resto.length)), 0, ...n);
    return resto;
  }
  const firmaPunch = (cells, fotos) => JSON.stringify([cells, (fotos || []).map(f => f[2])]);
  async function refrescarPunch() {
    const lista = state.shapes.filter(x => x.type === 'table' && x.punch);
    if (!lista.length) return;
    let cambiadas = 0, shapes = clone(state.shapes);
    for (const cfg of [...new Set(lista.map(x => JSON.stringify(x.punch)))]) {
      try {
        const r = await leerPunch(JSON.parse(cfg)); if (!r.success) continue;
        const nueva = tablaDePunch(r, 0, 0);
        const fotosViejas = t => shapes.filter(x => x.punchFoto && t.grp && x.grp === t.grp).map(x => [0, 0, x.src]);
        for (const t of shapes.filter(x => x.type === 'table' && x.punch && JSON.stringify(x.punch) === cfg)) {
          if (firmaPunch(t.cells, fotosViejas(t)) === firmaPunch(nueva[0].cells, r.fotos)) continue;
          cambiadas++;
          shapes = reemplazarPunch(shapes, t, r);
        }
      } catch (err) { /* sin conexión: se queda la copia guardada */ }
    }
    if (cambiadas) { state.shapes = shapes; render(); dirty(true); status(cambiadas + ' tabla(s) del Punch list actualizada(s) · guarda para conservar el cambio'); }
  }
  // Imágenes vinculadas a una simbología/símbolo (shape.ref): si el origen cambió desde que se colocaron, se renuevan
  // conservando posición, giro, opacidad y ancho (solo la lámina activa, como las tablas de SheetFlow)
  async function refrescarVinculadas() {
    const actuales = {};
    [...(C.graficos || []), ...(C.simbolos || [])].forEach(x => { if (x.ref) actuales[x.ref] = x; });
    const pend = state.shapes.filter(x => x.type === 'image' && x.ref && actuales[x.ref] && actuales[x.ref].v !== x.refv);
    if (!pend.length) return;
    let cambiadas = 0;
    for (const s of pend) {
      const o = actuales[s.ref];
      try {
        const b = await aBlob(o.src + (o.src.includes('?') ? '&' : '?') + 'r=' + encodeURIComponent(o.v), 1024);
        const fd = new FormData(); fd.append('lamina', C.lamina); fd.append('imagen', new File([b], 'imagen.png', { type: b.type || 'image/png' }));
        const d = await (await fetch((C.api && C.api.imagen) || '/api/vplan/imagen', { method: 'POST', body: fd })).json();
        if (!d.success) continue;
        const dim = await new Promise(ok => { const i = new Image(); i.onload = () => { imgCache[d.src] = { img: i, ok: true }; ok([i.naturalWidth || 1, i.naturalHeight || 1]); }; i.onerror = () => ok(null); i.src = d.src; });
        if (!dim) continue;
        const h = s.w * dim[1] / dim[0];
        state.shapes = state.shapes.map(x => x.id !== s.id ? x : Object.assign({}, x, { src: d.src, refv: o.v, h, y: x.y + (x.h - h) / 2 }));
        cambiadas++;
      } catch (err) { /* sin conexión: se queda la imagen guardada */ }
    }
    if (cambiadas) { render(); dirty(true); status(cambiadas + ' imagen(es) actualizada(s) desde su simbología · guarda para conservar el cambio'); }
  }
  async function importarSimbolos(archivos) {
    if (!archivos || !archivos.length) return;
    const fd = new FormData(); fd.append('proyecto', C.proyecto); [...archivos].forEach(f => fd.append('archivos[]', f));
    status('Importando…');
    try {
      const d = await (await fetch('/api/simbolos/importar', { method: 'POST', body: fd })).json();
      if (!d.success) { status('Error: ' + (d.error || 'no se pudo importar')); return; }
      C.simbolos = d.simbolos; C.cajetines = d.cajetines; pintarSimbolos();
      status('Importados: ' + d.importados + ' símbolo(s)' + (d.cajetines_nuevos ? ', ' + d.cajetines_nuevos + ' cajetín(es)' : '') + (d.omitidos ? ' · ' + d.omitidos + ' omitido(s)' : ''));
    } catch (e) { status('Error al importar'); }
  }
  function enlazarSimbolos() {
    if (!$('#sim-lista')) return;
    const bs = $('#ve-simbolos-btn');
    if (bs) bs.addEventListener('click', () => { const t = document.querySelector('.ve-tabs [data-tab=simbolos]'); if (t) t.click(); const q = $('#sim-buscar'); if (q) q.focus(); });
    pintarSimbolos();
    $('#sim-importar').addEventListener('click', () => $('#sim-importar-archivo').click());
    $('#sim-importar-archivo').addEventListener('change', e => { importarSimbolos(e.target.files); e.target.value = ''; });
    if ($('#sim-punch-caja') && C.modo !== 'grafico') $('#sim-punch-caja').hidden = true;
    if ($('#sim-punch')) {
      $('#sim-punch').addEventListener('click', () => abrirPunchCfg(null));
      $('#sim-punch-ok').addEventListener('click', aplicarPunchCfg);
      $('#sim-punch-x').addEventListener('click', () => { $('#sim-punch-cfg').hidden = true; punchEditando = null; });
      $('#b-punch-cfg').addEventListener('click', () => { const d = state.shapes.find(x => x.id === state.selId); if (d && d.punch) abrirPunchCfg(d); });
    }
    const sfLista = $('#sim-sheetflow');
    if (sfLista) sfLista.addEventListener('click', e => { const b = e.target.closest('[data-sf]'); if (b) insertarSheetflow((C.sheetflow || [])[+b.dataset.sf]); });
    $('#sim-cajetines').addEventListener('click', e => { const b = e.target.closest('[data-caj]'); if (b) insertarCajetin((C.cajetines || [])[+b.dataset.caj]); });
    $('#sim-buscar').addEventListener('input', pintarSimbolos);
    $('#sim-gr-subir').addEventListener('click', () => $('#sim-gr-archivo').click());
    $('#sim-gr-archivo').addEventListener('change', async e => {
      const fs = [...e.target.files]; e.target.value = '';
      for (const f of fs) await subirImagen(f, 0.5);
    });
    $('#sim-gr-todas').addEventListener('click', async () => {
      const gs = C.graficos || [];
      if (!gs.length) { status('No hay simbologías guardadas en este proyecto.'); return; }
      for (const g of gs) await insertarDesdeUrl(g.src, 0.5, g.ref ? { ref: g.ref, v: g.v } : null);
    });
    $('#ve-tab-simbolos').addEventListener('click', e => {
      const b = e.target.closest('.ve-sim'); if (!b) return;
      insertarDesdeUrl(b.dataset.src, b.classList.contains('ve-sim-gr') ? 0.5 : 0.1, b.dataset.ref ? { ref: b.dataset.ref, v: b.dataset.v || '' } : null);
    });
  }

  // ---------- modo presentación: pantalla completa, solo la lámina; flechas, clic o espacio para avanzar, Esc para salir ----------
  async function presentar() {
    const ov = $('#ve-presentacion'); if (!ov) return;
    volcarActiva();
    const datos = state.slides ? state.slides.map(x => x.data) : [{ shapes: state.shapes, layers: state.layers, escala: state.escala }];
    status('Preparando la presentación…');
    const k = Math.min(1, Math.min(2400, Math.round(screen.width * (window.devicePixelRatio || 1))) / state.W), w = Math.round(state.W * k), h = Math.round(state.H * k);
    const imgs = [];
    for (const dt of datos) imgs.push(await renderizar({ ancho: w, alto: h, mime: 'image/jpeg', calidad: 0.9, datos: dt }));
    status('');
    const img = ov.querySelector('img'), cnt = ov.querySelector('.ve-pres-n');
    let i = state.slides ? state.idx : 0;
    const ir = n => { i = Math.max(0, Math.min(imgs.length - 1, n)); img.src = imgs[i]; cnt.textContent = (i + 1) + ' / ' + imgs.length; };
    const tecla = e => {
      if (e.key === 'Escape') { cerrar(); return; }
      if (['ArrowRight', 'ArrowDown', 'PageDown', ' ', 'Enter'].includes(e.key)) ir(i + 1);
      else if (['ArrowLeft', 'ArrowUp', 'PageUp', 'Backspace'].includes(e.key)) ir(i - 1);
      else if (e.key === 'Home') ir(0); else if (e.key === 'End') ir(imgs.length - 1); else return;
      e.preventDefault(); e.stopPropagation();
    };
    const clic = e => { if (e.target.closest('.ve-pres-barra')) return; const r = ov.getBoundingClientRect(); ir(e.clientX < r.left + r.width * 0.3 ? i - 1 : i + 1); };
    const cerrar = () => {
      ov.hidden = true; document.removeEventListener('keydown', tecla, true); ov.removeEventListener('click', clic);
      document.removeEventListener('fullscreenchange', salida); if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    };
    const salida = () => { if (!document.fullscreenElement) cerrar(); };
    ov.querySelector('[data-pres=ant]').onclick = () => ir(i - 1); ov.querySelector('[data-pres=sig]').onclick = () => ir(i + 1); ov.querySelector('[data-pres=cerrar]').onclick = cerrar;
    document.addEventListener('keydown', tecla, true); ov.addEventListener('click', clic);
    ov.hidden = false; ir(i);
    if (ov.requestFullscreen) { try { await ov.requestFullscreen(); document.addEventListener('fullscreenchange', salida); } catch (_) {} }
  }

  // ---------- pestañas del panel lateral ----------
  function iniciarPestanas() {
    const tabs = [...document.querySelectorAll('.ve-tabs [data-tab]')]; if (!tabs.length) return;
    const ir = id => {
      tabs.forEach(t => { const on = t.dataset.tab === id; t.classList.toggle('activa', on); t.setAttribute('aria-selected', on); });
      document.querySelectorAll('.ve-tab-panel').forEach(p => { p.hidden = p.dataset.panel !== id; });
      try { localStorage.setItem('ve-tab', id); } catch (_) {}
    };
    tabs.forEach(t => t.addEventListener('click', () => ir(t.dataset.tab)));
    let guardada = null; try { guardada = localStorage.getItem('ve-tab'); } catch (_) {}
    ir(tabs.some(t => t.dataset.tab === guardada) ? guardada : 'props');
  }

  // ---------- gestor de capas ----------
  const IC = {
    ojo: '<path d="M2 12s3.600-7 10-7 10 7 10 7-3.600 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    ojoNo: '<path d="M17.940 17.940A10.070 10.070 0 0 1 12 19c-6.500 0-10-7-10-7a18.500 18.500 0 0 1 5.060-5.940M9.900 4.240A9.120 9.120 0 0 1 12 4c6.500 0 10 7 10 7a18.500 18.500 0 0 1-2.160 3.190M1 1l22 22"/>',
    candado: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    abierto: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.500-2"/>',
    sube: '<path d="M6 15l6-6 6 6"/>', baja: '<path d="M6 9l6 6 6-6"/>',
    basura: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>'
  };
  const ico = d => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
  const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function pintarCapas() {
    const ul = $('#ve-capas-lista'); if (!ul) return;
    const n = id => state.shapes.filter(x => x.layer === id).length;
    ul.innerHTML = [...state.layers].reverse().map((l, i, arr) => {
      const nombre = nombreCapa(l), arriba = state.layers.indexOf(l) === state.layers.length - 1, abajo = state.layers.indexOf(l) === 0;
      return '<li class="ve-capa' + (l.id === capaActiva ? ' activa' : '') + (l.visible ? '' : ' oculta') + (l.locked ? ' bloqueada' : '') + '" data-id="' + l.id + '">' +
        '<button type="button" class="ve-cb" data-op="ver" title="' + (l.visible ? 'Ocultar' : 'Mostrar') + ' capa" aria-pressed="' + !l.visible + '">' + ico(l.visible ? IC.ojo : IC.ojoNo) + '</button>' +
        '<button type="button" class="ve-cb" data-op="bloq" title="' + (l.locked ? 'Desbloquear' : 'Bloquear') + ' capa" aria-pressed="' + l.locked + '">' + ico(l.locked ? IC.candado : IC.abierto) + '</button>' +
        '<span class="ve-capa-nombre" data-op="activar" title="Clic: activar · doble clic: renombrar">' + esc(nombre) + '</span><span class="ve-capa-n">' + n(l.id) + '</span>' +
        '<button type="button" class="ve-cb" data-op="sube" title="Subir capa"' + (arriba ? ' disabled' : '') + '>' + ico(IC.sube) + '</button>' +
        '<button type="button" class="ve-cb" data-op="baja" title="Bajar capa"' + (abajo ? ' disabled' : '') + '>' + ico(IC.baja) + '</button>' +
        '<button type="button" class="ve-cb ve-cb-del" data-op="borrar" title="Eliminar capa"' + (state.layers.length < 2 ? ' disabled' : '') + '>' + ico(IC.basura) + '</button></li>';
    }).join('');
  }
  function renombrarCapa(id) {
    const li = $('#ve-capas-lista').querySelector('[data-id="' + id + '"]'), l = capaPor(id); if (!li || !l) return;
    const sp = li.querySelector('.ve-capa-nombre'), inp = document.createElement('input');
    inp.type = 'text'; inp.className = 've-capa-input'; inp.maxLength = 40; inp.value = l.name || ''; inp.placeholder = 'Capa ' + l.num;
    sp.replaceWith(inp); inp.focus(); inp.select();
    let hecho = false;
    const fin = ok => {
      if (hecho) return; hecho = true;
      const v = inp.value.trim().slice(0, 40);
      if (ok && v !== (l.name || '')) { const capas = clone(state.layers); capas.find(x => x.id === id).name = v; commit(undefined, capas); } else pintarCapas();
    };
    inp.addEventListener('blur', () => fin(true));
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') fin(true); if (e.key === 'Escape') fin(false); e.stopPropagation(); });
  }
  function enlazarCapas() {
    $('#ve-capa-nueva').addEventListener('click', () => {
      const capas = clone(state.layers), c = nuevaCapa(siguienteNumCapa());
      capas.push(c); capaActiva = c.id; commit(undefined, capas);
      renombrarCapa(c.id);   // queda lista para escribir el nombre; vacío = «Capa N»
    });
    $('#ve-capas-lista').addEventListener('click', async e => {
      const b = e.target.closest('[data-op]'), li = e.target.closest('.ve-capa'); if (!b || !li || b.disabled) return;
      const id = li.dataset.id, l = capaPor(id), op = b.dataset.op;
      const capas = clone(state.layers), i = capas.findIndex(x => x.id === id), c = capas[i];
      if (op === 'activar') { capaActiva = id; pintarCapas(); return; }
      if (op === 'ver') {
        c.visible = !c.visible;
        if (!c.visible && state.selId && (state.shapes.find(x => x.id === state.selId) || {}).layer === id) state.selId = null;
        commit(undefined, capas);
      } else if (op === 'bloq') {
        c.locked = !c.locked;
        if (c.locked && state.selId && (state.shapes.find(x => x.id === state.selId) || {}).layer === id) state.selId = null;
        commit(undefined, capas);
      } else if (op === 'sube' && i < capas.length - 1) { [capas[i], capas[i + 1]] = [capas[i + 1], capas[i]]; commit(undefined, capas); }
      else if (op === 'baja' && i > 0) { [capas[i], capas[i - 1]] = [capas[i - 1], capas[i]]; commit(undefined, capas); }
      else if (op === 'borrar') {
        const cuantas = state.shapes.filter(x => x.layer === id).length;
        const ok = await window.confirmarModal({ titulo: '¿Eliminar la capa «' + nombreCapa(l) + '»?', mensaje: cuantas ? 'Se eliminarán también sus ' + cuantas + ' marca(s). Puedes deshacerlo con Ctrl+Z.' : 'La capa está vacía.', aceptar: 'Sí, eliminar', iconoBoton: 'fa-trash-can' });
        if (!ok) return;
        capas.splice(i, 1);
        if (capaActiva === id) capaActiva = capas[Math.min(i, capas.length - 1)].id;
        if (state.selId && (state.shapes.find(x => x.id === state.selId) || {}).layer === id) state.selId = null;
        commit(clone(state.shapes).filter(x => x.layer !== id), capas);
      }
    });
    $('#ve-capas-lista').addEventListener('dblclick', e => { const sp = e.target.closest('.ve-capa-nombre'); if (sp) renombrarCapa(sp.closest('.ve-capa').dataset.id); });
  }


  // ZIP sin compresión (las imágenes ya vienen comprimidas): entradas [{nombre, datos: Uint8Array}] → Blob
  const TABLA_CRC = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
  const crc32 = b => { let c = 0xFFFFFFFF; for (let i = 0; i < b.length; i++) c = TABLA_CRC[(c ^ b[i]) & 0xFF] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; };
  function zipSinComprimir(archivos) {
    const enc = new TextEncoder(), partes = [], central = []; let desp = 0;
    const d = new Date(), hora = (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1), fecha = ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate();
    archivos.forEach(a => {
      const nom = enc.encode(a.nombre), crc = crc32(a.datos), tam = a.datos.length;
      const lh = new DataView(new ArrayBuffer(30)); [[0, 0x04034b50, 4], [4, 20, 2], [6, 0x0800, 2], [8, 0, 2], [10, hora, 2], [12, fecha, 2], [14, crc, 4], [18, tam, 4], [22, tam, 4], [26, nom.length, 2], [28, 0, 2]].forEach(([o, v, n]) => n === 4 ? lh.setUint32(o, v, true) : lh.setUint16(o, v, true));
      partes.push(lh.buffer, nom, a.datos);
      const ch = new DataView(new ArrayBuffer(46)); [[0, 0x02014b50, 4], [4, 20, 2], [6, 20, 2], [8, 0x0800, 2], [10, 0, 2], [12, hora, 2], [14, fecha, 2], [16, crc, 4], [20, tam, 4], [24, tam, 4], [28, nom.length, 2], [30, 0, 2], [32, 0, 2], [34, 0, 2], [36, 0, 2], [38, 0, 4], [42, desp, 4]].forEach(([o, v, n]) => n === 4 ? ch.setUint32(o, v, true) : ch.setUint16(o, v, true));
      central.push(ch.buffer, nom);
      desp += 30 + nom.length + tam;
    });
    const tamCentral = central.reduce((t, b) => t + (b.byteLength !== undefined ? b.byteLength : b.length), 0);
    const fin = new DataView(new ArrayBuffer(22)); [[0, 0x06054b50, 4], [4, 0, 2], [6, 0, 2], [8, archivos.length, 2], [10, archivos.length, 2], [12, tamCentral, 4], [16, desp, 4], [20, 0, 2]].forEach(([o, v, n]) => n === 4 ? fin.setUint32(o, v, true) : fin.setUint16(o, v, true));
    return new Blob([...partes, ...central, fin.buffer], { type: 'application/zip' });
  }

  // ---------- exportar el gráfico (Editor): formato y tamaño a elección ----------
  function enlazarExportar() {
    const el = id => document.getElementById(id);
    if (!el('ex-btn')) return;
    const MAX_LADO = 10000, MAX_PX = 64e6, razon = () => state.W / state.H;
    const EXT = { png: ['image/png', 'png'], jpg: ['image/jpeg', 'jpg'], webp: ['image/webp', 'webp'], pdf: ['image/png', 'pdf'], odp: ['image/png', 'odp'] };
    const medidas = () => ({ w: Math.round(+el('ex-ancho').value) || 0, h: Math.round(+el('ex-alto').value) || 0 });
    const valida = () => { const { w, h } = medidas(); return w >= 16 && h >= 16 && w <= MAX_LADO && h <= MAX_LADO && w * h <= MAX_PX; };
    const info = () => {
      const { w, h } = medidas();
      el('ex-info').textContent = !valida() ? 'Usa entre 16 y ' + MAX_LADO + ' px por lado (máx. ' + Math.round(MAX_PX / 1e6) + ' millones de píxeles).' : w + ' × ' + h + ' px · ' + (w * h / 1e6).toFixed(1) + ' MP';
      el('ex-info').classList.toggle('bj-error', !valida());
      el('ex-btn').disabled = !valida();
    };
    const formato = () => {
      const f = el('ex-formato').value;
      el('ex-calidad-bloque').hidden = !['jpg', 'webp'].includes(f);
      el('ex-transp').disabled = !['png', 'webp'].includes(f);
      if (el('ex-transp').disabled) el('ex-transp').checked = false;
    };
    el('ex-preset').addEventListener('change', e => {
      const m = /^(\d+)x(\d+)$/.exec(e.target.value); if (!m) return;
      el('ex-ancho').value = m[1]; el('ex-alto').value = m[2];
      el('ex-bloq').checked = Math.abs(+m[1] / +m[2] - razon()) < 0.01;   // otra proporción: el contenido se centra dentro del nuevo tamaño
      info();
    });
    el('ex-ancho').addEventListener('input', () => { if (el('ex-bloq').checked) el('ex-alto').value = Math.round(+el('ex-ancho').value / razon()) || ''; el('ex-preset').value = 'custom'; info(); });
    el('ex-alto').addEventListener('input', () => { if (el('ex-bloq').checked) el('ex-ancho').value = Math.round(+el('ex-alto').value * razon()) || ''; el('ex-preset').value = 'custom'; info(); });
    el('ex-formato').addEventListener('change', formato);
    el('ex-calidad').addEventListener('input', e => { el('ex-calidad-v').textContent = e.target.value + '%'; });
    const slug = t => String(t || 'grafico').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'grafico';
    const bajar = (blob, nombre) => { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nombre; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 5000); };
    el('ex-btn').addEventListener('click', async () => {
      if (!valida()) return;
      const f = el('ex-formato').value, { w, h } = medidas(), [mime, ext] = EXT[f], estado = el('ex-estado');
      const todas = !!state.slides && state.slides.length > 1 && el('ex-alcance').value === 'todas';
      const hoy = new Date(), p = n => String(n).padStart(2, '0');
      const base = hoy.getFullYear() + '_' + p(hoy.getMonth() + 1) + '_' + p(hoy.getDate()) + '_' + slug(C.nombre);
      el('ex-btn').disabled = true; estado.textContent = 'Generando…';
      try {
        if (todas) {
          volcarActiva();
          if (f === 'pdf' || f === 'odp') {   // documento de varias páginas con las láminas guardadas, a su tamaño original
            if (!(await guardar())) throw new Error('No se pudo guardar antes de exportar');
            const r = await fetch(C.urls.descargar + '&formato=' + f);
            if (!r.ok) throw new Error('No se pudo generar el archivo');
            bajar(await r.blob(), base + '.' + ext);
            estado.textContent = 'Listo ✔ ' + base + '.' + ext + ' (láminas a su tamaño original)';
          } else {
            const archivos = [];
            for (let i = 0; i < state.slides.length; i++) {
              estado.textContent = 'Generando lámina ' + (i + 1) + ' de ' + state.slides.length + '…';
              const sl = state.slides[i];
              const url = await renderizar({ ancho: w, alto: h, mime, calidad: +el('ex-calidad').value / 100, transparente: el('ex-transp').checked, datos: sl.data, iconos: true });
              if (f === 'webp' && !url.startsWith('data:image/webp')) throw new Error('Este navegador no puede exportar WebP; usa PNG o JPG');
              archivos.push({ nombre: p(i + 1) + '_' + slug(nombreLamina(sl, i)) + '.' + ext, datos: new Uint8Array(await (await fetch(url)).arrayBuffer()) });
            }
            bajar(zipSinComprimir(archivos), base + '.zip');
            estado.textContent = 'Listo ✔ ' + base + '.zip (' + archivos.length + ' imágenes)';
          }
          return;
        }
        const nombre = base + '.' + ext;
        const url = await renderizar({ ancho: w, alto: h, mime, calidad: +el('ex-calidad').value / 100, transparente: el('ex-transp').checked, iconos: f !== 'pdf' && f !== 'odp' });
        if (f === 'pdf' || f === 'odp') {
          const fd = new FormData(); fd.append('lamina', C.lamina); fd.append('ancho', w); fd.append('alto', h); fd.append('png', url);
          if (f === 'pdf') fd.append('enlaces', JSON.stringify(enlacesExternos(w, h)));
          const r = await fetch(f === 'pdf' ? C.api.pdf : C.api.odp, { method: 'POST', body: fd });
          if (!r.ok) { let m = 'No se pudo generar el archivo'; try { m = (await r.json()).error || m; } catch (_) {} throw new Error(m); }
          bajar(await r.blob(), nombre);
        } else {
          if (f === 'webp' && !url.startsWith('data:image/webp')) throw new Error('Este navegador no puede exportar WebP; usa PNG o JPG');
          bajar(await (await fetch(url)).blob(), nombre);
        }
        estado.textContent = 'Listo ✔ ' + nombre;
      } catch (e) { estado.textContent = 'Error: ' + (e.message || 'no se pudo exportar'); }
      finally { el('ex-btn').disabled = !valida(); }
    });
    formato(); info();
  }

  // ---------- arranque ----------
  function iniciar() {
    if (C.plano) { const img = new Image(); img.onload = () => arrancar(img, img.naturalWidth, img.naturalHeight); img.src = C.plano; }
    else if (C.fondo === 'imagen' && C.fondoImagen) {   // fondo personalizado: primero se carga la imagen
      const f = new Image(); f.onload = () => { fondoImg = f; arrancar(null, C.ancho, C.alto); }; f.onerror = () => arrancar(null, C.ancho, C.alto); f.src = C.fondoImagen;
    }
    else arrancar(null, C.ancho, C.alto);   // Editor: lienzo libre (tamaño elegido y fondo de plantilla)
  }
  // Fondo de plantilla del Editor: color liso o tesela repetida
  let fondoImg = null;   // imagen del fondo personalizado, ya cargada
  function fondoRect(W, H) {
    if (C.fondo === 'imagen') {   // imagen propia: se dibuja según sus ajustes (cubrir, contener, estirar o mosaico)
      return new Konva.Shape({
        width: W, height: H, fill: '#000',
        sceneFunc: ctx => { VPFondos.dibujarImagen(ctx._context, W, H, fondoImg, C.fondoConfig); },
        hitFunc: (ctx, shape) => { ctx.beginPath(); ctx.rect(0, 0, W, H); ctx.closePath(); ctx.fillStrokeShape(shape); }
      });
    }
    const tesela = window.VPFondos && VPFondos.tesela(C.fondo);
    return new Konva.Rect({ width: W, height: H, fill: C.fondoColor || '#ffffff', ...(tesela ? { fillPriority: 'pattern', fillPatternImage: tesela, fillPatternRepeat: 'repeat' } : {}) });
  }
  function arrancar(img, W, H) {
    state.W = W; state.H = H; sw = Math.max(3, Math.round(W / 400));
    $('#t-sw').value = sw; $('#t-sw-r').value = Math.min(60, sw);
    stage = new Konva.Stage({ container: 'ek-stage', width: 800, height: 600 });
    const lbg = new Konva.Layer(); stage.add(lbg);
    if (img) bg = new Konva.Image({ image: img, width: W, height: H });
    else {
      bg = fondoRect(W, H);
      if (window.VPFondos && VPFondos.esOscuro(C.fondo, C.fondoColor)) $('#t-stroke').value = '#ffffff';   // sobre fondo oscuro, trazo claro por defecto
    }
    cargarTrazo();
    lbg.add(bg);
    glayer = new Konva.Layer({ listening: false }); stage.add(glayer);
    glayer.add(new Konva.Shape({ listening: false, sceneFunc: dibujarGrilla }));
    layer = new Konva.Layer(); stage.add(layer);
    trLayer = new Konva.Layer(); stage.add(trLayer);
    tr = new Konva.Transformer({ rotateEnabled: true, rotationSnaps: [0, 45, 90, 135, 180, 225, 270, 315], borderStroke: '#0070c0', anchorStroke: '#0070c0', anchorSize: 10, keepRatio: false });
    trLayer.add(tr);
    tr.on('transformend', aplicarTransformacion);
    if (C.laminas && C.laminas.length) {   // Editor: varias láminas; se abre la elegida (o la primera)
      state.slides = C.laminas.map(l => ({ id: l.id, nombre: l.nombre || '', data: l.marcas || { shapes: [], layers: [], escala: null }, past: [], future: [], thumb: l.png || '', dirty: false, version: l.version || '' }));
      const i = state.slides.findIndex(l => l.id === C.laminaActiva);
      state.idx = i >= 0 ? i : 0;
      const d0 = state.slides[state.idx].data;
      state.shapes = clone(d0.shapes || []); state.layers = clone(d0.layers || []); state.escala = d0.escala || null; state.guides = clone(d0.guides || []);
    } else {
      state.shapes = (C.marcas && C.marcas.shapes) ? C.marcas.shapes : [];
      state.layers = (C.marcas && C.marcas.layers) ? C.marcas.layers : [];
      state.escala = (C.marcas && C.marcas.escala) || null;
      state.guides = clone((C.marcas && C.marcas.guides) || []);
    }
    const nice = [10, 20, 25, 50, 100, 200, 250, 500, 1000], ideal = state.W / 40;
    $('#t-grid-n').value = nice.reduce((a, b) => Math.abs(b - ideal) < Math.abs(a - ideal) ? b : a);   // cuadrícula acorde al tamaño del dibujo
    asegurarCapas();
    configurarEventos(); enlazarPanel(); enlazarCapas(); enlazarAlinear(); enlazarLaminas(); enlazarSimbolos(); enlazarVista(); enlazarGuias(); encuadrar(); render(); pintarLaminas(); dirty(false); refrescarSheetflow(); refrescarPunch(); refrescarVinculadas();
    if (state.slides) state.slides.forEach(x => { if (x.thumb === '' || x.thumb === undefined) x.thumb = ''; });
    window.addEventListener('resize', encuadrar);
    if (C.regen && state.slides) { state.slides.forEach(x => { x.dirty = true; }); guardar().then(() => { try { history.replaceState(null, '', location.href.replace(/[?&]regen=1/, '')); } catch (_) {} }); }
    window.__vplan = { state, guardar, insertarCajetin, presentar, importarSimbolos, setTool, selectIds, alinear, distribuir, agrupar, desagrupar, irALamina, zipSinComprimir, nodes: () => nodes, editarCelda, select, celdasSel: () => celdasSel, capaActiva: () => capaActiva, renderizar };   // para pruebas
    if (C.auto) guardar().then(ok => { if (ok) location.href = C.volver; });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tool]').forEach(b => b.addEventListener('click', () => setTool(b.dataset.tool)));
    $('#ve-archivo').addEventListener('change', e => { subirImagen(e.target.files[0]); e.target.value = ''; });
    $('#ve-archivo-360').addEventListener('change', e => { subirPanorama(e.target.files[0]); e.target.value = ''; });
    $('#t-sw').addEventListener('change', e => { sw = Math.max(1, +e.target.value || sw); $('#t-sw-r').value = Math.min(60, sw); guardarTrazo(); });
    $('#t-sw-r').addEventListener('input', e => { sw = Math.max(1, +e.target.value || sw); $('#t-sw').value = sw; guardarTrazo(); });
    $('#t-stroke').addEventListener('change', guardarTrazo);
    $('#t-op').addEventListener('input', e => { $('#t-op-n').value = e.target.value; guardarTrazo(); });
    $('#t-op-n').addEventListener('change', e => { const v = Math.min(100, Math.max(5, +e.target.value || 100)); e.target.value = $('#t-op').value = v; guardarTrazo(); });
    window.VPColor && VPColor.iniciar(); iniciarPestanas(); enlazarExportar();
    const bp = document.getElementById('ve-presentar'); if (bp) bp.addEventListener('click', presentar);
    $('#ve-guardar').addEventListener('click', () => guardar());
    // Desplegable «Más formas»
    const bm = $('#ve-mas-formas'), pm = $('#ve-mas-panel');
    if (bm && pm) {
      const cerrarMas = () => { pm.hidden = true; bm.setAttribute('aria-expanded', 'false'); };
      bm.addEventListener('click', e => { e.stopPropagation(); pm.hidden = !pm.hidden; bm.setAttribute('aria-expanded', pm.hidden ? 'false' : 'true'); });
      pm.addEventListener('click', e => { if (e.target.closest('[data-tool]')) cerrarMas(); });
      document.addEventListener('click', e => { if (!pm.contains(e.target)) cerrarMas(); });
      document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarMas(); });
    }
    iniciarMarcadorGrafico();
    const bf = document.getElementById('ve-fondo');
    if (bf) bf.addEventListener('click', async () => { const ok = await guardar(); if (ok !== false) location.href = bf.dataset.href; });
    try { const c = $('#t-auto'); if (c) { c.checked = localStorage.getItem('ve-auto') !== '0'; c.addEventListener('change', () => { try { localStorage.setItem('ve-auto', c.checked ? '1' : '0'); } catch (_) {} autoPausado = false; programarAuto(); }); } } catch (_) {}
    $('#ve-undo').addEventListener('click', undo); $('#ve-redo').addEventListener('click', redo);
    $('#ve-fit').addEventListener('click', encuadrar);
    // Al salir con cambios sin guardar: modal de la aplicación (el aviso del navegador queda solo para cerrar la pestaña)
    document.addEventListener('click', async e => {
      const a = e.target.closest('a[href]');
      if (!a || !sucio || a.target === '_blank') return;
      e.preventDefault();
      const r = await window.confirmarModal({ titulo: '¿Salir sin guardar?', mensaje: 'Tienes cambios sin guardar en las marcas del plano. Si sales ahora se perderán.', tono: 'aviso', icono: 'fa-floppy-disk', aceptar: 'Salir sin guardar', iconoBoton: 'fa-right-from-bracket', extra: 'Guardar y salir', cancelar: 'Seguir editando' });
      if (r === 'extra') { const g = await guardar(); if (g !== false) { sucio = false; location.href = a.href; } return; }
      if (r) { sucio = false; location.href = a.href; }
    });
    window.addEventListener('beforeunload', e => { if (sucio) { e.preventDefault(); e.returnValue = ''; } });
    iniciar();
  });
})();
