/* Comparador de documentos: dos imágenes o PDF (del proyecto o externos) superpuestos y comparables con un deslizador,
   con anotaciones (globos, textos, flechas, formas, mano alzada, nubes, medidas), hilos de revisión, diferencias
   con tolerancia, análisis de páginas, comparaciones guardadas con historial y enlace público de solo lectura.
   Todo se dibuja en el navegador; los archivos externos solo se suben cuando se guarda la comparación. */
(function () {
  'use strict';
  const C = window.CMP_CONFIG || {};
  const G = window.CMP_GUARDADO || null;
  const H = parseInt(window.CMP_HISTORIAL, 10) || 0;
  const $ = s => document.querySelector(s);
  const LADO_MAX = 3000;   // lado mayor (px) con el que se dibuja cada documento
  const EXT_IMG = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
  const TIPOS_ANOT = ['seleccion', 'globo', 'texto', 'resaltador', 'resaltado', 'flecha', 'linea', 'rect', 'elipse', 'nube', 'medir', 'area', 'mano'];
  const PUBLICO = !!C.publico;
  if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = C.worker;

  // slots: lienzo, tamaño, PDF abierto (si lo es), origen y datos para mostrar
  const slots = {
    a: { canvas: $('#cmp-ca'), w: 0, h: 0, pdf: null, nombre: '', listo: false, tok: 0, pagina: 1, origen: null, archivo: null, tamanos: {} },
    b: { canvas: $('#cmp-cb'), w: 0, h: 0, pdf: null, nombre: '', listo: false, tok: 0, pagina: 1, origen: null, archivo: null, tamanos: {} },
  };
  const vista = { x: 0, y: 0, k: 1 };
  const bT = { x: 0, y: 0, s: 1, rot: 0 };
  const est = { modo: 'slider', pos: 0.5, dir: 'v', fade: 50, tol: 24, moverB: false, encuadrado: false };
  const anot = {
    activo: false, ver: true, herramienta: 'seleccion', color: '#e11d48', grosor: 5, relleno: false,
    shapes: [], sel: -1, multi: [], undo: [], redo: [], drag: null, editando: -1, contador: 0,
    escala: 0, unidad: 'm', calibrando: false, filtro: 'todas', todasPaginas: false,
    portapapeles: [], presentando: false, presIdx: -1,
  };
  let guardadoId = G ? (parseInt(G.id, 10) || 0) : 0;
  let nombreActual = G ? String(G.nombre || '') : '';
  let descripcionActual = G ? String(G.descripcion || '') : '';
  let soloLectura = PUBLICO;
  let restaurando = false;

  const anotEl = $('#cmp-anot');
  const anotCtx = anotEl ? anotEl.getContext('2d') : null;
  const vistaEl = $('#cmp-vista');

  const fuenteUI = k => document.querySelector('.cmp-fuente[data-slot="' + k + '"]');
  const estado = (k, txt) => { const u = fuenteUI(k); if (u) u.querySelector('.cmp-estado').textContent = txt; };
  const avisar = txt => { const e = $('#cmp-guardar-estado'); if (e) e.textContent = txt; };
  const p2 = n => String(n).padStart(2, '0');
  const fechaISO = () => new Date().toISOString();
  const fechaCorta = v => {
    if (!v) return '';
    const d = new Date(v);
    return isNaN(d) ? String(v).slice(0, 16) : d.toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };
  const pintarSelectores = () => {
    ['a', 'b'].forEach(k => {
      const sel = document.querySelector('.cmp-proy[data-slot="' + k + '"]'); if (!sel) return;
      const o = slots[k].origen;
      sel.value = o && ['archivo', 'vplan', 'editor', 'redactor'].includes(o.tipo) ? o.tipo + ':' + o.id : '';
    });
    actualizarPrev();
  };
  function actualizarPrev() {
    const sel = document.querySelector('.cmp-proy[data-slot="a"]'), b = $('#cmp-prev');
    if (!sel || !b) return;
    const op = sel.selectedOptions[0], prev = op && op.dataset ? op.dataset.prev : '';
    b.hidden = !prev;
    b.dataset.prev = prev || '';
  }

  // ---------- carga ----------
  async function abrir(k, buffer, ext, nombre, pagina) {
    if (k === 'a') {
      pags.datos = [];
      const ex = $('#cmp-exportar-cambios'); if (ex) ex.disabled = true;
      const grid = $('#cmp-paginas-grid'); if (grid) grid.innerHTML = '';
      const estd = $('#cmp-analizar-estado'); if (estd) estd.textContent = '';
    }
    const s = slots[k], tok = ++s.tok, ui = fuenteUI(k), pag = ui && ui.querySelector('.cmp-pag');
    if (k === 'a') s.tamanos = {};
    estado(k, 'Cargando…');
    try {
      if (ext === 'pdf') {
        if (!window.pdfjsLib) throw new Error('No se pudo cargar el lector de PDF (revisa tu conexión)');
        const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
        if (tok !== s.tok) return;
        s.pdf = pdf;
        if (pag) pag.hidden = false;
        if (ui) {
          ui.querySelector('.cmp-pagina').max = pdf.numPages;
          ui.querySelector('.cmp-pagina').value = 1;
          ui.querySelector('.cmp-total').textContent = 'de ' + pdf.numPages;
        }
        const n = Math.min(pdf.numPages, Math.max(1, parseInt(pagina, 10) || 1));
        await dibujarPdf(k, n, tok);
      } else {
        const tipo = ext === 'jpg' ? 'jpeg' : ext;
        const bmp = await createImageBitmap(new Blob([buffer], { type: 'image/' + tipo }));
        if (tok !== s.tok) return;
        s.pdf = null; s.pagina = 1; if (pag) pag.hidden = true;
        const f = Math.min(1, LADO_MAX / Math.max(bmp.width, bmp.height));
        s.w = Math.round(bmp.width * f); s.h = Math.round(bmp.height * f);
        s.canvas.width = s.w; s.canvas.height = s.h;
        const g = s.canvas.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, s.w, s.h); g.drawImage(bmp, 0, 0, s.w, s.h);
        if (k === 'a') { s.tamanos = { 1: { w: s.w, h: s.h } }; sizeAnot(); }
      }
      s.nombre = nombre; s.listo = true; diff.listo = false;
      estado(k, nombre + (s.pdf ? ' · pág. ' + s.pagina : '') + ' · ' + s.w + ' × ' + s.h + ' px');
      listo();
    } catch (e) {
      if (tok === s.tok) { s.listo = false; estado(k, 'No se pudo abrir: ' + (e && e.message ? e.message : 'archivo no válido')); }
    }
  }
  async function dibujarPdf(k, n, tok) {
    const s = slots[k], page = await s.pdf.getPage(n), base = page.getViewport({ scale: 1 });
    const vp = page.getViewport({ scale: Math.min(4, LADO_MAX / Math.max(base.width, base.height)) });
    if (tok !== undefined && tok !== s.tok) return;
    s.w = Math.round(vp.width); s.h = Math.round(vp.height); s.pagina = n;
    if (k === 'a') s.tamanos[n] = { w: s.w, h: s.h };
    s.canvas.width = s.w; s.canvas.height = s.h;
    const g = s.canvas.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, s.w, s.h);
    await page.render({ canvasContext: g, viewport: vp }).promise;
    if (k === 'a') { sizeAnot(); redibujarAnot(); }
    diff.listo = false;
    const inp = fuenteUI(k) && fuenteUI(k).querySelector('.cmp-pagina');
    if (inp) { inp.value = n; inp.max = s.pdf.numPages; }
    if (est.modo === 'diff') programarDiff(true);
  }
  const extDe = nombre => (String(nombre).split('.').pop() || '').toLowerCase();
  const avisarSiAnotaciones = k => {
    if (k !== 'a' || !anot.shapes.length) return true;
    return confirm('Cambiar el documento A borrará sus anotaciones. ¿Continuar?');
  };

  document.querySelectorAll('.cmp-proy').forEach(sel => sel.addEventListener('change', async () => {
    const k = sel.dataset.slot, op = sel.selectedOptions[0];
    if (!sel.value) return;
    const [tipo, idTxt] = sel.value.split(':');
    const id = parseInt(idTxt, 10) || 0;
    if (!tipo || !id) return;
    if (!avisarSiAnotaciones(k)) { pintarSelectores(); return; }
    fuenteUI(k).querySelector('.cmp-ext').value = '';
    slots[k].archivo = null;
    if (k === 'a') { anot.shapes = []; anot.sel = -1; anot.multi = []; pintarAnotUI(); redibujarAnot(); }
    slots[k].origen = { tipo, id, nombre: op.dataset.nombre || '', ext: op.dataset.ext || '', pagina: 1 };
    if (k === 'a') actualizarPrev();
    estado(k, 'Descargando…');
    try {
      const r = await fetch(fuenteUrl(tipo, id), { credentials: 'same-origin' });
      if (!r.ok) throw new Error('el servidor respondió ' + r.status);
      await abrir(k, await r.arrayBuffer(), op.dataset.ext, op.dataset.nombre, 1);
    } catch (e) { estado(k, 'No se pudo descargar: ' + e.message); }
  }));
  const fuenteUrl = (tipo, id) => C.publico
    ? '/comparador/publico/fuente?t=' + encodeURIComponent(C.token) + '&slot=' + (tipo === 'a' ? 'a' : 'b')
    : '/comparador/fuente?proyecto=' + C.proyecto + '&tipo=' + encodeURIComponent(tipo) + '&id=' + encodeURIComponent(id);

  document.querySelectorAll('.cmp-ext').forEach(inp => inp.addEventListener('change', async () => {
    const k = inp.dataset.slot, f = inp.files[0]; if (!f) return;
    const ext = extDe(f.name) || (f.type === 'application/pdf' ? 'pdf' : '');
    if (ext !== 'pdf' && !EXT_IMG.includes(ext)) { estado(k, 'Formato no permitido: usa PDF o imagen (png, jpg, webp, gif)'); inp.value = ''; return; }
    if (!avisarSiAnotaciones(k)) { inp.value = ''; return; }
    const sp = document.querySelector('.cmp-proy[data-slot="' + k + '"]'); if (sp) sp.value = '';
    if (k === 'a') { anot.shapes = []; anot.sel = -1; anot.multi = []; pintarAnotUI(); redibujarAnot(); }
    slots[k].origen = { tipo: 'nuevo', nombre: f.name, ext, pagina: 1 };
    slots[k].archivo = f;
    await abrir(k, await f.arrayBuffer(), ext, f.name, 1);
  }));
  document.querySelectorAll('.cmp-pagina').forEach(inp => inp.addEventListener('change', async () => {
    const k = inp.dataset.slot, s = slots[k]; if (!s.pdf) return;
    const n = Math.min(s.pdf.numPages, Math.max(1, Math.round(+inp.value) || 1));
    inp.value = n;
    estado(k, 'Cargando página…');
    await dibujarPdf(k, n, s.tok);
    if (k === 'a') { anot.sel = -1; anot.multi = []; pintarAnotUI(); redibujarAnot(); }
    if (slots[k].origen) slots[k].origen.pagina = n;
    estado(k, s.nombre + ' · pág. ' + n + ' · ' + s.w + ' × ' + s.h + ' px');
    aplicar();
    if (est.modo === 'diff') programarDiff(true);
  }));
  document.querySelectorAll('.cmp-pag-prev,.cmp-pag-next').forEach(b => b.addEventListener('click', () => {
    const k = b.dataset.slot, inp = document.querySelector('.cmp-pagina[data-slot="' + k + '"]');
    if (!inp) return;
    inp.value = Math.max(1, (parseInt(inp.value, 10) || 1) + (b.classList.contains('cmp-pag-next') ? 1 : -1));
    inp.dispatchEvent(new Event('change'));
  }));
  if ($('#cmp-prev')) $('#cmp-prev').addEventListener('click', () => {
    const b = $('#cmp-prev'), sel = document.querySelector('.cmp-proy[data-slot="b"]');
    if (!b.dataset.prev || !sel) return;
    sel.value = b.dataset.prev;
    sel.dispatchEvent(new Event('change'));
  });

  // ---------- panel ----------
  function listo() {
    if (!(slots.a.listo && slots.b.listo)) return;
    $('#cmp').hidden = false;
    if (restaurando) { aplicar(); return; }
    if (!est.encuadrado) { ajustarB(); encuadrar(); est.encuadrado = true; } else aplicar();
  }

  function aplicar() {
    const b = slots.b, ma = $('#cmp-mundo-a'), mb = $('#cmp-mundo-b'), md = $('#cmp-mundo-diff');
    const t = `translate(${vista.x}px,${vista.y}px) scale(${vista.k})`;
    ma.style.transform = mb.style.transform = t;
    if (md) md.style.transform = t;
    b.canvas.style.transform = `translate(${bT.x}px,${bT.y}px) rotate(${bT.rot}deg) scale(${bT.s})`;
    const capaA = $('#cmp-capa-a'), capaB = $('#cmp-capa-b'), capaD = $('#cmp-capa-diff'), div = $('#cmp-div'), v = $('#cmp-vista');
    const esDiff = est.modo === 'diff';
    capaA.style.visibility = esDiff ? 'hidden' : '';
    capaB.style.visibility = esDiff ? 'hidden' : '';
    if (capaD) capaD.hidden = !esDiff;
    capaB.dataset.modo = est.modo; capaB.style.clipPath = ''; capaB.style.opacity = '';
    div.hidden = est.modo !== 'slider';
    if (est.modo === 'slider') {
      const p = (est.pos * 100).toFixed(2);
      if (est.dir === 'v') { capaB.style.clipPath = `inset(0 0 0 ${p}%)`; div.className = 'cmp-div v'; div.style.left = p + '%'; div.style.top = ''; }
      else { capaB.style.clipPath = `inset(${p}% 0 0 0)`; div.className = 'cmp-div h'; div.style.top = p + '%'; div.style.left = ''; }
      div.setAttribute('aria-valuenow', Math.round(est.pos * 100));
    } else if (est.modo === 'fade') capaB.style.opacity = $('#cmp-fade').value / 100;
    v.classList.toggle('moviendo', est.moverB);
    $('#cmp-esc').value = Math.round(bT.s * 1000) / 10; $('#cmp-rot').value = Math.round(bT.rot * 10) / 10;
    $('#cmp-bx').value = Math.round(bT.x); $('#cmp-by').value = Math.round(bT.y);
    $('#cmp-mover').setAttribute('aria-pressed', est.moverB); $('#cmp-mover').classList.toggle('btn-primary', est.moverB); $('#cmp-mover').classList.toggle('btn-ghost', !est.moverB);
    document.querySelectorAll('[data-modo]').forEach(b2 => { if (b2.tagName === 'BUTTON') { b2.classList.toggle('btn-primary', b2.dataset.modo === est.modo); b2.classList.toggle('btn-ghost', b2.dataset.modo !== est.modo); } });
    $('#cmp-dir-l').hidden = est.modo !== 'slider'; $('#cmp-fade-l').hidden = est.modo !== 'fade';
    const dl = $('#cmp-diff-l'); if (dl) dl.hidden = !esDiff;
    if (esDiff) programarDiff();
    if (anotEl) anotEl.classList.toggle('activo', anot.activo && !est.moverB && anot.herramienta !== 'mano');
    redibujarAnot();
  }

  function encuadrar() {
    const v = $('#cmp-vista'), W = v.clientWidth, H = v.clientHeight, a = slots.a;
    if (!a.w) return;
    vista.k = Math.min(W / a.w, H / a.h) * 0.96;
    vista.x = (W - a.w * vista.k) / 2; vista.y = (H - a.h * vista.k) / 2;
    aplicar();
  }
  function ajustarB() {
    const a = slots.a, b = slots.b; if (!a.w || !b.w) return;
    bT.rot = 0; bT.s = Math.min(a.w / b.w, a.h / b.h);
    bT.x = (a.w - b.w * bT.s) / 2; bT.y = (a.h - b.h * bT.s) / 2;
    aplicar();
  }

  // ---------- eventos de comparación ----------
  document.querySelectorAll('button[data-modo]').forEach(b => b.addEventListener('click', () => { est.modo = b.dataset.modo; aplicar(); }));
  $('#cmp-dir').addEventListener('change', e => { est.dir = e.target.value; aplicar(); });
  $('#cmp-fade').addEventListener('input', aplicar);
  $('#cmp-mover').addEventListener('click', () => { est.moverB = !est.moverB; aplicar(); });
  $('#cmp-ajustar').addEventListener('click', ajustarB);
  $('#cmp-reset-b').addEventListener('click', () => { bT.x = bT.y = 0; bT.s = 1; bT.rot = 0; aplicar(); });
  $('#cmp-encuadrar').addEventListener('click', encuadrar);
  $('#cmp-intercambiar').addEventListener('click', () => {
    if (anot.shapes.length && !confirm('Intercambiar A y B borrará las anotaciones del documento A. ¿Continuar?')) return;
    const A = slots.a, B = slots.b, ca = A.canvas, cb = B.canvas;
    const campos = ['w', 'h', 'pdf', 'nombre', 'listo', 'pagina', 'origen', 'archivo', 'tok'];
    campos.forEach(c => { const t = A[c]; A[c] = B[c]; B[c] = t; });
    const g = (c, o) => { const x = document.createElement('canvas'); x.width = o.width; x.height = o.height; x.getContext('2d').drawImage(o, 0, 0); return x; };
    const ta = g(0, ca), tb = g(0, cb);
    ca.width = tb.width; ca.height = tb.height; ca.getContext('2d').drawImage(tb, 0, 0);
    cb.width = ta.width; cb.height = ta.height; cb.getContext('2d').drawImage(ta, 0, 0);
    anot.shapes = []; anot.sel = -1; anot.multi = []; sizeAnot(); redibujarAnot(); pintarAnotUI();
    ['a', 'b'].forEach(k => { estado(k, slots[k].nombre ? slots[k].nombre + (slots[k].pdf ? ' · pág. ' + slots[k].pagina : '') + ' · ' + slots[k].w + ' × ' + slots[k].h + ' px' : 'Sin documento'); const u = fuenteUI(k); if (u) u.querySelector('.cmp-pag').hidden = !slots[k].pdf; });
    pintarSelectores();
    ajustarB(); encuadrar();
  });
  const num = (id, fn) => $(id).addEventListener('change', e => { const v = parseFloat(e.target.value); if (Number.isFinite(v)) { fn(v); aplicar(); if (est.modo === 'diff') programarDiff(); } else aplicar(); });
  num('#cmp-esc', v => { bT.s = Math.max(0.05, Math.min(10, v / 100)); });
  num('#cmp-rot', v => { bT.rot = Math.max(-180, Math.min(180, v)); });
  num('#cmp-bx', v => { bT.x = v; }); num('#cmp-by', v => { bT.y = v; });

  vistaEl.addEventListener('wheel', e => {
    e.preventDefault();
    const r = vistaEl.getBoundingClientRect(), px = e.clientX - r.left, py = e.clientY - r.top;
    const k = Math.min(20, Math.max(0.02, vista.k * (e.deltaY < 0 ? 1.12 : 1 / 1.12)));
    vista.x = px - (px - vista.x) * k / vista.k; vista.y = py - (py - vista.y) * k / vista.k; vista.k = k; aplicar();
  }, { passive: false });
  let arr = null;
  vistaEl.addEventListener('pointerdown', e => {
    if (e.target.closest('.cmp-div') || e.target === anotEl) return;
    if (anot.activo && anot.herramienta !== 'mano') return;
    if (gestos && gestos.activo()) return;
    arr = { x: e.clientX, y: e.clientY }; vistaEl.setPointerCapture(e.pointerId); vistaEl.classList.add('arrastrando'); vistaEl.focus();
  });
  vistaEl.addEventListener('pointermove', e => {
    if (gestos && gestos.activo()) { arr = null; return; }
    if (!arr) return;
    const dx = e.clientX - arr.x, dy = e.clientY - arr.y; arr = { x: e.clientX, y: e.clientY };
    if (est.moverB) { bT.x += dx / vista.k; bT.y += dy / vista.k; } else { vista.x += dx; vista.y += dy; }
    aplicar();
  });
  const soltar = () => { arr = null; vistaEl.classList.remove('arrastrando'); };
  vistaEl.addEventListener('pointerup', soltar); vistaEl.addEventListener('pointercancel', soltar);
  vistaEl.addEventListener('keydown', e => {
    if (!est.moverB || e.target !== vistaEl) return;
    const p = e.shiftKey ? 10 : 1, m = { ArrowLeft: [-p, 0], ArrowRight: [p, 0], ArrowUp: [0, -p], ArrowDown: [0, p] }[e.key];
    if (m) { e.preventDefault(); bT.x += m[0]; bT.y += m[1]; aplicar(); }
  });

  // gestos táctiles (pellizco, dos dedos, doble toque) con la pieza compartida
  let gestos = null, gestoBase = null;
  if (window.GestosLienzo && vistaEl) {
    gestos = window.GestosLienzo.montar(vistaEl, {
      zoomActual: () => vista.k, min: 0.02, max: 20,
      alInicio: ({ z, cx, cy }) => {
        arr = null; anot.drag = null;
        const r = vistaEl.getBoundingClientRect();
        gestoBase = { z0: z, cx0: cx, cy0: cy, x0: vista.x, y0: vista.y, k0: vista.k, r };
      },
      alMover: ({ z, cx, cy }) => {
        if (!gestoBase) return;
        const gb = gestoBase, r = gb.r;
        const px = gb.cx0 - r.left, py = gb.cy0 - r.top;
        const docX = (px - gb.x0) / gb.k0, docY = (py - gb.y0) / gb.k0;
        vista.k = Math.max(0.02, Math.min(20, z));
        vista.x = (cx - r.left) - docX * vista.k;
        vista.y = (cy - r.top) - docY * vista.k;
        aplicar();
      },
      alFin: () => { gestoBase = null; if (est.modo === 'diff') programarDiff(); },
      alDobleToque: (cx, cy) => {
        const r = vistaEl.getBoundingClientRect();
        const k = vista.k >= 3 ? Math.max(0.04, vista.k / 4) : Math.min(8, vista.k * 4);
        const px = cx - r.left, py = cy - r.top;
        vista.x = px - (px - vista.x) * k / vista.k;
        vista.y = py - (py - vista.y) * k / vista.k;
        vista.k = k; aplicar();
      },
    });
  }

  // deslizador
  const div = $('#cmp-div');
  let movDiv = false;
  const posDiv = e => {
    const r = vistaEl.getBoundingClientRect();
    est.pos = Math.min(1, Math.max(0, est.dir === 'v' ? (e.clientX - r.left) / r.width : (e.clientY - r.top) / r.height)); aplicar();
  };
  div.addEventListener('pointerdown', e => { movDiv = true; div.setPointerCapture(e.pointerId); e.preventDefault(); });
  div.addEventListener('pointermove', e => { if (movDiv) posDiv(e); });
  div.addEventListener('pointerup', () => { movDiv = false; });
  div.addEventListener('keydown', e => {
    const p = e.shiftKey ? 0.1 : 0.01, d = { ArrowLeft: -p, ArrowUp: -p, ArrowRight: p, ArrowDown: p }[e.key];
    if (d) { e.preventDefault(); est.pos = Math.min(1, Math.max(0, est.pos + d)); aplicar(); }
  });

  // ===================================================================== diferencias
  const diff = { cv: $('#cmp-cd'), ctx: null, f: 1, regiones: [], idx: -1, pct: 0, timer: 0, listo: false };
  if (diff.cv) diff.ctx = diff.cv.getContext('2d');
  function programarDiff(inmediato) {
    if (est.modo !== 'diff' || !slots.a.w || !slots.b.w) return;
    clearTimeout(diff.timer);
    diff.timer = setTimeout(calcularDiff, inmediato ? 0 : 220);
  }
  function calcularDiff() {
    if (est.modo !== 'diff' || !slots.a.w || !slots.b.w || !diff.ctx) return;
    const a = slots.a, b = slots.b;
    const f = Math.min(1, 1600 / Math.max(a.w, a.h));
    const dw = Math.max(1, Math.round(a.w * f)), dh = Math.max(1, Math.round(a.h * f));
    const ca = document.createElement('canvas'), cb = document.createElement('canvas');
    ca.width = cb.width = dw; ca.height = cb.height = dh;
    const ga = ca.getContext('2d'), gb = cb.getContext('2d');
    ga.fillStyle = '#fff'; ga.fillRect(0, 0, dw, dh); ga.drawImage(a.canvas, 0, 0, dw, dh);
    gb.fillStyle = '#fff'; gb.fillRect(0, 0, dw, dh);
    gb.save(); gb.scale(f, f);
    gb.translate(bT.x + b.w / 2, bT.y + b.h / 2);
    gb.rotate(bT.rot * Math.PI / 180); gb.scale(bT.s, bT.s);
    gb.drawImage(b.canvas, -b.w / 2, -b.h / 2); gb.restore();
    let da, db;
    try { da = ga.getImageData(0, 0, dw, dh).data; db = gb.getImageData(0, 0, dw, dh).data; }
    catch (e) { return; }
    const tol = Math.max(0, Math.min(100, parseInt(($('#cmp-diff-tol') || {}).value, 10) || 0));
    const out = diff.ctx.createImageData(dw, dh), o = out.data;
    const cw = Math.max(4, Math.round(dw / 70)), ch = cw;
    const cx = Math.ceil(dw / cw), cy = Math.ceil(dh / ch);
    const celdas = new Uint8Array(cx * cy);
    let cambiados = 0;
    for (let i = 0, j = 0; i < da.length; i += 4, j += 4) {
      const d = Math.max(Math.abs(da[i] - db[i]), Math.abs(da[i + 1] - db[i + 1]), Math.abs(da[i + 2] - db[i + 2]));
      if (d > tol) {
        o[j] = 255; o[j + 1] = 70; o[j + 2] = 70; o[j + 3] = 255; cambiados++;
        const px = (i / 4) % dw, py = Math.floor((i / 4) / dw);
        celdas[Math.floor(py / ch) * cx + Math.floor(px / cw)] = 1;
      } else { o[j] = 14; o[j + 1] = 18; o[j + 2] = 25; o[j + 3] = 255; }
    }
    diff.cv.width = dw; diff.cv.height = dh;
    diff.ctx.putImageData(out, 0, 0);
    diff.cv.style.width = a.w + 'px'; diff.cv.style.height = a.h + 'px';
    // Agrupa celdas cambiadas en regiones (para «anterior / siguiente»)
    const vistas = new Uint8Array(cx * cy), regiones = [];
    for (let y = 0; y < cy; y++) for (let x = 0; x < cx; x++) {
      const k0 = y * cx + x;
      if (!celdas[k0] || vistas[k0]) continue;
      let cola = [[x, y]], minX = x, maxX = x, minY = y, maxY = y, n = 0;
      vistas[k0] = 1;
      while (cola.length) {
        const [px, py] = cola.pop(); n++;
        minX = Math.min(minX, px); maxX = Math.max(maxX, px); minY = Math.min(minY, py); maxY = Math.max(maxY, py);
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) {
          const nx = px + dx, ny = py + dy;
          if (nx < 0 || ny < 0 || nx >= cx || ny >= cy) continue;
          const k1 = ny * cx + nx;
          if (celdas[k1] && !vistas[k1]) { vistas[k1] = 1; cola.push([nx, ny]); }
        }
      }
      if (n < 1) continue;
      regiones.push({ x: minX * cw / f, y: minY * ch / f, w: Math.max(cw, (maxX - minX + 1) * cw) / f, h: Math.max(ch, (maxY - minY + 1) * ch) / f, n });
    }
    regiones.sort((r1, r2) => r2.n - r1.n);
    // Contorno suave de cada región
    diff.ctx.save(); diff.ctx.strokeStyle = 'rgba(255,214,10,.85)'; diff.ctx.lineWidth = Math.max(1, 2 / f / Math.max(0.1, vista.k));
    regiones.forEach(r => diff.ctx.strokeRect(r.x * f, r.y * f, r.w * f, r.h * f));
    diff.ctx.restore();
    diff.f = f; diff.regiones = regiones; diff.idx = regiones.length ? 0 : -1;
    diff.pct = 100 * cambiados / (dw * dh);
    const pct = $('#cmp-diff-pct'); if (pct) pct.textContent = diff.pct.toFixed(2) + '%';
    actualizarDiffNav();
  }
  function actualizarDiffNav() {
    const n = $('#cmp-diff-n'); if (n) n.textContent = (diff.idx + 1) + ' / ' + diff.regiones.length;
    const p = $('#cmp-diff-prev'), s = $('#cmp-diff-next');
    if (p) p.disabled = !diff.regiones.length; if (s) s.disabled = !diff.regiones.length;
  }
  function irCambio(dir) {
    if (!diff.regiones.length) return;
    diff.idx = (diff.idx + dir + diff.regiones.length) % diff.regiones.length;
    const r = diff.regiones[diff.idx], W = vistaEl.clientWidth, H = vistaEl.clientHeight;
    const pad = 1.35;
    vista.k = Math.max(0.05, Math.min(8, Math.min(W / Math.max(1, r.w * pad), H / Math.max(1, r.h * pad))));
    vista.x = W / 2 - (r.x + r.w / 2) * vista.k;
    vista.y = H / 2 - (r.y + r.h / 2) * vista.k;
    aplicar(); actualizarDiffNav();
  }
  if ($('#cmp-diff-prev')) $('#cmp-diff-prev').addEventListener('click', () => irCambio(-1));
  if ($('#cmp-diff-next')) $('#cmp-diff-next').addEventListener('click', () => irCambio(1));
  if ($('#cmp-diff-tol')) $('#cmp-diff-tol').addEventListener('input', () => programarDiff());
  if ($('#cmp-diff-tol')) $('#cmp-diff-tol').addEventListener('change', () => programarDiff(true));

  // ===================================================================== anotaciones
  const escala = () => Math.max(0.7, Math.min(3, (slots.a.w || 1200) / 1200));
  const vacia = sh => ['rect', 'elipse', 'nube', 'resaltado', 'area'].includes(sh.tipo);
  const clone = o => JSON.parse(JSON.stringify(o));
  const deep = i => anot.shapes[i] ? clone(anot.shapes[i]) : null;
  /** Píxeles de pantalla → píxeles del documento (para tiradores, tolerancias y trazos de selección) */
  const pxPantalla = () => 1 / Math.max(0.02, vista.k);
  const dpr = () => Math.min(3, Math.max(1, window.devicePixelRatio || 1));
  const paginaActual = () => Math.max(1, parseInt(slots.a.pagina, 10) || 1);
  const paginaDe = sh => Math.max(1, parseInt(sh && sh.pagina, 10) || 1);
  const enPaginaActual = sh => paginaDe(sh) === paginaActual();
  const enPaginaLista = sh => anot.todasPaginas || enPaginaActual(sh);
  const indicesPagina = () => anot.shapes.map((sh, i) => i).filter(i => enPaginaActual(anot.shapes[i]));
  const indicesLista = () => anot.shapes.map((sh, i) => i).filter(i => enPaginaLista(anot.shapes[i]));
  const pasaFiltro = sh => anot.filtro === 'todas' || (sh.estado || 'abierta') === (anot.filtro === 'abiertas' ? 'abierta' : 'resuelta');
  const seleccionada = i => i === anot.sel || anot.multi.includes(i);
  function seleccionar(i, shift) {
    if (i < 0) { anot.sel = -1; anot.multi = []; return; }
    const sh = anot.shapes[i];
    if (shift) {
      if (!anot.multi.includes(i)) anot.multi.push(i); else anot.multi = anot.multi.filter(x => x !== i);
      anot.sel = i; return;
    }
    anot.sel = i;
    anot.multi = sh && sh.grupo ? anot.shapes.map((s, j) => s.grupo === sh.grupo ? j : -1).filter(j => j >= 0) : [i];
  }

  function sizeAnot() { redibujarAnot(); }

  function snapshot() {
    anot.undo.push(JSON.stringify(anot.shapes));
    if (anot.undo.length > 60) anot.undo.shift();
    anot.redo = [];
    pintarAnotUI();
  }
  function deshacer() {
    if (!anot.undo.length) return;
    anot.redo.push(JSON.stringify(anot.shapes));
    anot.shapes = JSON.parse(anot.undo.pop());
    anot.sel = -1; anot.multi = []; cerrarEditor(false); redibujarAnot(); pintarAnotUI();
  }
  function rehacer() {
    if (!anot.redo.length) return;
    anot.undo.push(JSON.stringify(anot.shapes));
    anot.shapes = JSON.parse(anot.redo.pop());
    anot.sel = -1; anot.multi = []; cerrarEditor(false); redibujarAnot(); pintarAnotUI();
  }

  function puntoA(e) {
    const r = vistaEl.getBoundingClientRect();
    const k = Math.max(0.02, vista.k);
    return { x: (e.clientX - r.left - vista.x) / k, y: (e.clientY - r.top - vista.y) / k };
  }

  function envolver(g, texto, maxW) {
    const lineas = [];
    String(texto || '').split(/\r?\n/).forEach(par => {
      const palabras = par.split(/\s+/);
      let linea = '';
      palabras.forEach(pal => {
        const prueba = linea ? linea + ' ' + pal : pal;
        if (linea && g.measureText(prueba).width > maxW) { lineas.push(linea); linea = pal; }
        else linea = prueba;
      });
      lineas.push(linea || ' ');
    });
    return lineas;
  }
  function medidaTexto(sh, g = anotCtx) {
    const e = escala(), f = (sh.fuente || 16) * e;
    g.save(); g.font = '600 ' + f + 'px Inter, system-ui, sans-serif';
    const maxW = 420 * e, lineas = envolver(g, sh.texto, maxW);
    let w = 0; lineas.forEach(l => { w = Math.max(w, g.measureText(l).width); });
    const out = { f, lineas, w: Math.max(20 * e, Math.min(maxW, w)), h: Math.max(f, lineas.length * f * 1.28), maxW };
    g.restore();
    return out;
  }
  function cajaTextoGlobo(sh, g = anotCtx) {
    const e = escala(), m = medidaTexto(sh, g), r = (sh.radio || 28) * e;
    return { x: sh.x + r + 10 * e, y: sh.y - r, w: Math.min(320 * e, m.w + 18 * e), h: Math.max(r * 2, m.h + 12 * e) };
  }
  function caja(sh, g = anotCtx) {
    if (vacia(sh)) return { x: sh.x, y: sh.y, w: sh.w, h: sh.h };
    if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') return { x: Math.min(sh.x1, sh.x2), y: Math.min(sh.y1, sh.y2), w: Math.abs(sh.x2 - sh.x1), h: Math.abs(sh.y2 - sh.y1) };
    if (sh.tipo === 'mano') {
      const xs = sh.pts.map(p => p[0]), ys = sh.pts.map(p => p[1]);
      return { x: Math.min(...xs), y: Math.min(...ys), w: Math.max(1, Math.max(...xs) - Math.min(...xs)), h: Math.max(1, Math.max(...ys) - Math.min(...ys)) };
    }
    if (sh.tipo === 'texto') { const m = medidaTexto(sh, g); return { x: sh.x, y: sh.y, w: m.w, h: m.h }; }
    if (sh.tipo === 'globo') {
      const e = escala(), r = (sh.radio || 28) * e;
      let b = { x: sh.x - r, y: sh.y - r, w: r * 2, h: r * 2 };
      if (sh.texto) { const c = cajaTextoGlobo(sh, g); b = { x: Math.min(b.x, c.x), y: Math.min(b.y, c.y), w: Math.max(b.x + b.w, c.x + c.w) - Math.min(b.x, c.x), h: Math.max(b.y + b.h, c.y + c.h) - Math.min(b.y, c.y) }; }
      return b;
    }
    return { x: 0, y: 0, w: 0, h: 0 };
  }

  const fmtLong = px => {
    if (!anot.escala) return Math.round(px) + ' px';
    const m = px * anot.escala;
    if (m >= 1) return m.toFixed(2) + ' m';
    if (m >= 0.01) return (m * 100).toFixed(0) + ' cm';
    return (m * 1000).toFixed(0) + ' mm';
  };
  const fmtArea = px2 => {
    if (!anot.escala) return Math.round(px2) + ' px²';
    const m2 = px2 * anot.escala * anot.escala;
    if (m2 >= 1) return m2.toFixed(2) + ' m²';
    return (m2 * 10000).toFixed(0) + ' cm²';
  };
  const longitud = sh => Math.hypot(sh.x2 - sh.x1, sh.y2 - sh.y1);

  function etiqueta(g, x, y, texto, col) {
    const e = escala(), f = Math.max(10, 13 * e);
    g.save(); g.font = '700 ' + f + 'px Inter, system-ui, sans-serif';
    const w = g.measureText(texto).width + 10 * e, h = f + 8 * e;
    g.fillStyle = 'rgba(15,23,42,.88)'; g.strokeStyle = col; g.lineWidth = Math.max(1, 1.5 * e);
    g.beginPath(); g.roundRect(x - w / 2, y - h / 2, w, h, 4 * e); g.fill(); g.stroke();
    g.fillStyle = '#fff'; g.textAlign = 'center'; g.textBaseline = 'middle';
    g.fillText(texto, x, y + f * .04);
    g.restore();
  }

  function dibujarShape(g, sh, seleccionada) {
    const e = escala(), col = sh.color || '#e11d48', grosor = Math.max(1, (sh.grosor || 5) * e);
    g.save();
    g.strokeStyle = col; g.fillStyle = col; g.lineWidth = grosor; g.lineCap = 'round'; g.lineJoin = 'round';
    if ((sh.estado || 'abierta') === 'resuelta' && (sh.tipo === 'globo' || sh.tipo === 'texto')) g.globalAlpha = 0.78;
    switch (sh.tipo) {
      case 'rect':
      case 'elipse':
        g.beginPath();
        if (sh.tipo === 'rect') g.rect(sh.x, sh.y, sh.w, sh.h); else g.ellipse(sh.x + sh.w / 2, sh.y + sh.h / 2, Math.abs(sh.w / 2), Math.abs(sh.h / 2), 0, 0, Math.PI * 2);
        if (sh.relleno) { g.globalAlpha = .22; g.fill(); g.globalAlpha = 1; }
        g.stroke(); break;
      case 'resaltado':
        g.globalAlpha = .32; g.fillRect(sh.x, sh.y, sh.w, sh.h); g.globalAlpha = 1; break;
      case 'area':
        g.globalAlpha = .14; g.fillRect(sh.x, sh.y, sh.w, sh.h); g.globalAlpha = 1; g.strokeRect(sh.x, sh.y, sh.w, sh.h);
        etiqueta(g, sh.x + sh.w / 2, sh.y + sh.h / 2, fmtArea(Math.abs(sh.w * sh.h)), col); break;
      case 'nube': nube(g, sh.x, sh.y, sh.w, sh.h, Math.max(8 * e, Math.min(sh.w, sh.h) / 8)); if (sh.relleno) { g.globalAlpha = .12; g.fill(); g.globalAlpha = 1; } g.stroke(); break;
      case 'flecha': case 'linea':
        g.beginPath(); g.moveTo(sh.x1, sh.y1); g.lineTo(sh.x2, sh.y2); g.stroke();
        if (sh.tipo === 'flecha') {
          const ang = Math.atan2(sh.y2 - sh.y1, sh.x2 - sh.x1), f = Math.max(10 * e, grosor * 2.6);
          g.beginPath(); g.moveTo(sh.x2, sh.y2); g.lineTo(sh.x2 - f * Math.cos(ang - .42), sh.y2 - f * Math.sin(ang - .42)); g.lineTo(sh.x2 - f * Math.cos(ang + .42), sh.y2 - f * Math.sin(ang + .42)); g.closePath(); g.fill();
        }
        break;
      case 'medir': {
        const t = Math.max(6 * e, grosor * 1.8), ang = Math.atan2(sh.y2 - sh.y1, sh.x2 - sh.x1);
        g.beginPath(); g.moveTo(sh.x1, sh.y1); g.lineTo(sh.x2, sh.y2); g.stroke();
        [[sh.x1, sh.y1], [sh.x2, sh.y2]].forEach(([x, y]) => {
          g.beginPath(); g.moveTo(x - Math.sin(ang) * t, y + Math.cos(ang) * t); g.lineTo(x + Math.sin(ang) * t, y - Math.cos(ang) * t); g.stroke();
        });
        etiqueta(g, (sh.x1 + sh.x2) / 2, (sh.y1 + sh.y2) / 2 - 16 * e, fmtLong(longitud(sh)), col);
        break;
      }
      case 'mano': case 'resaltador':
        if (!sh.pts || sh.pts.length < 2) break;
        g.globalAlpha = sh.tipo === 'resaltador' ? .36 : 1;
        g.lineWidth = sh.tipo === 'resaltador' ? grosor * 2.6 : grosor;
        g.beginPath(); g.moveTo(sh.pts[0][0], sh.pts[0][1]); sh.pts.slice(1).forEach(p => g.lineTo(p[0], p[1])); g.stroke(); g.globalAlpha = 1; break;
      case 'texto': {
        const m = medidaTexto(sh, g); g.font = '600 ' + m.f + 'px Inter, system-ui, sans-serif'; g.textBaseline = 'top';
        m.lineas.forEach((l, i) => g.fillText(l, sh.x, sh.y + i * m.f * 1.28));
        if ((sh.estado || 'abierta') === 'resuelta') { g.globalAlpha = 1; g.fillStyle = '#16a34a'; g.font = '700 ' + Math.max(10, 12 * e) + 'px Inter, sans-serif'; g.fillText('✓ resuelta', sh.x, sh.y - 18 * e); }
        break;
      }
      case 'globo': {
        const r = (sh.radio || 28) * e;
        g.beginPath(); g.moveTo(sh.ax, sh.ay); g.lineTo(sh.x, sh.y); g.stroke();
        g.beginPath(); g.arc(sh.x, sh.y, r, 0, Math.PI * 2); g.fill();
        g.lineWidth = Math.max(2, 2 * e); g.strokeStyle = '#fff'; g.stroke();
        g.fillStyle = '#fff'; g.font = '700 ' + Math.round(r * .95) + 'px Inter, sans-serif'; g.textAlign = 'center'; g.textBaseline = 'middle';
        g.fillText(String(sh.numero || ''), sh.x, sh.y + r * .02); g.textAlign = 'start'; g.textBaseline = 'alphabetic';
        if (sh.texto) {
          const c = cajaTextoGlobo(sh, g), m = medidaTexto(sh, g);
          g.fillStyle = 'rgba(255,255,255,.94)'; g.strokeStyle = col; g.lineWidth = Math.max(1.5, 2 * e);
          g.beginPath(); g.roundRect(c.x, c.y, c.w, c.h, 6 * e); g.fill(); g.stroke();
          g.fillStyle = '#111827'; g.font = '600 ' + m.f + 'px Inter, system-ui, sans-serif'; g.textBaseline = 'top';
          m.lineas.forEach((l, i) => g.fillText(l, c.x + 9 * e, c.y + 6 * e + i * m.f * 1.28));
        }
        if ((sh.estado || 'abierta') === 'resuelta') {
          g.globalAlpha = 1; g.beginPath(); g.arc(sh.x + r * .78, sh.y - r * .78, r * .38, 0, Math.PI * 2); g.fillStyle = '#16a34a'; g.fill();
          g.strokeStyle = '#fff'; g.lineWidth = Math.max(1.5, 2 * e); g.stroke();
          g.fillStyle = '#fff'; g.textAlign = 'center'; g.textBaseline = 'middle';
          g.font = '700 ' + Math.round(r * .55) + 'px Inter, sans-serif'; g.fillText('✓', sh.x + r * .78, sh.y - r * .78);
          g.textAlign = 'start'; g.textBaseline = 'alphabetic';
        }
        break;
      }
    }
    if (seleccionada) seleccion(g, sh);
    g.restore();
  }

  function nube(g, x, y, w, h, r) {
    r = Math.max(2, Math.min(r, Math.min(Math.abs(w), Math.abs(h)) / 4));
    const nX = Math.max(2, Math.round(Math.abs(w) / (r * 1.7))), nY = Math.max(2, Math.round(Math.abs(h) / (r * 1.7)));
    const dw = w / nX, dh = h / nY;
    g.beginPath(); g.moveTo(x, y);
    for (let i = 0; i < nX; i++) g.arc(x + dw * (i + .5), y, Math.abs(dw) / 2, Math.PI, 0, false);
    for (let i = 0; i < nY; i++) g.arc(x + w, y + dh * (i + .5), Math.abs(dh) / 2, -Math.PI / 2, Math.PI / 2, false);
    for (let i = 0; i < nX; i++) g.arc(x + w - dw * (i + .5), y + h, Math.abs(dw) / 2, 0, Math.PI, false);
    for (let i = 0; i < nY; i++) g.arc(x, y + h - dh * (i + .5), Math.abs(dh) / 2, Math.PI / 2, Math.PI * 3 / 2, false);
    g.closePath();
  }

  function handles(sh) {
    const b = caja(sh);
    return [
      { k: 'nw', x: b.x, y: b.y }, { k: 'ne', x: b.x + b.w, y: b.y },
      { k: 'sw', x: b.x, y: b.y + b.h }, { k: 'se', x: b.x + b.w, y: b.y + b.h },
    ];
  }
  function seleccion(g, sh) {
    const b = caja(sh), p = pxPantalla();
    g.save(); g.strokeStyle = '#2563eb'; g.lineWidth = Math.max(1, 1.4 * p); g.setLineDash([6 * p, 4 * p]); g.strokeRect(b.x, b.y, b.w, b.h); g.setLineDash([]);
    handles(sh).forEach(h => { g.fillStyle = '#fff'; g.strokeStyle = '#2563eb'; g.lineWidth = 1.6 * p; g.fillRect(h.x - 4 * p, h.y - 4 * p, 8 * p, 8 * p); g.strokeRect(h.x - 4 * p, h.y - 4 * p, 8 * p, 8 * p); });
    g.restore();
  }

  function hitShape(sh, p) {
    const e = escala(), b = caja(sh), t = Math.max(6 * pxPantalla(), (sh.grosor || 5) * e);
    if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') return distSeg(p, { x: sh.x1, y: sh.y1 }, { x: sh.x2, y: sh.y2 }) <= t;
    if (sh.tipo === 'globo') {
      if (Math.hypot(p.x - sh.x, p.y - sh.y) <= (sh.radio || 28) * e + 6 * pxPantalla()) return true;
      if (sh.texto) { const c = cajaTextoGlobo(sh); if (p.x >= c.x && p.x <= c.x + c.w && p.y >= c.y && p.y <= c.y + c.h) return true; }
      const d = distSeg(p, { x: sh.ax, y: sh.ay }, { x: sh.x, y: sh.y });
      return d <= 8 * pxPantalla();
    }
    if (sh.tipo === 'mano' || sh.tipo === 'resaltador') {
      const m = 8 * pxPantalla();
      if (p.x < b.x - m || p.x > b.x + b.w + m || p.y < b.y - m || p.y > b.y + b.h + m) return false;
      for (let i = 1; i < sh.pts.length; i++) if (distSeg(p, { x: sh.pts[i - 1][0], y: sh.pts[i - 1][1] }, { x: sh.pts[i][0], y: sh.pts[i][1] }) <= Math.max(10 * pxPantalla(), (sh.grosor || 5) * e)) return true;
      return false;
    }
    const m = 4 * pxPantalla();
    return p.x >= b.x - m && p.x <= b.x + b.w + m && p.y >= b.y - m && p.y <= b.y + b.h + m;
  }
  function distSeg(p, a, b) {
    const dx = b.x - a.x, dy = b.y - a.y, l2 = dx * dx + dy * dy;
    if (!l2) return Math.hypot(p.x - a.x, p.y - a.y);
    let t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / l2; t = Math.max(0, Math.min(1, t));
    return Math.hypot(p.x - (a.x + t * dx), p.y - (a.y + t * dy));
  }
  function hitIndex(p) {
    const ix = indicesPagina();
    for (let j = ix.length - 1; j >= 0; j--) if (hitShape(anot.shapes[ix[j]], p)) return ix[j];
    return -1;
  }
  function hitHandle(p) {
    if (anot.sel < 0) return null;
    const h = handles(anot.shapes[anot.sel]), t = 9 * pxPantalla();
    for (const x of h) if (Math.abs(p.x - x.x) <= t && Math.abs(p.y - x.y) <= t) return x.k;
    return null;
  }

  function mover(sh, dx, dy) {
    if (sh.tipo === 'mano' || sh.tipo === 'resaltador') { sh.pts = sh.pts.map(p => [p[0] + dx, p[1] + dy]); }
    else if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') { sh.x1 += dx; sh.y1 += dy; sh.x2 += dx; sh.y2 += dy; }
    else if (sh.tipo === 'globo') { sh.x += dx; sh.y += dy; sh.ax += dx; sh.ay += dy; }
    else { sh.x += dx; sh.y += dy; }
  }
  function escalar(sh, ox, oy, sx, sy) {
    const e = escala(), avg = (Math.abs(sx) + Math.abs(sy)) / 2;
    const X = v => ox + (v - ox) * sx, Y = v => oy + (v - oy) * sy;
    if (sh.tipo === 'mano' || sh.tipo === 'resaltador') sh.pts = sh.pts.map(p => [X(p[0]), Y(p[1])]);
    else if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') { sh.x1 = X(sh.x1); sh.y1 = Y(sh.y1); sh.x2 = X(sh.x2); sh.y2 = Y(sh.y2); }
    else if (sh.tipo === 'globo') { sh.x = X(sh.x); sh.y = Y(sh.y); sh.ax = X(sh.ax); sh.ay = Y(sh.ay); sh.radio = Math.max(8, (sh.radio || 28) * avg); }
    else { sh.x = X(sh.x); sh.y = Y(sh.y); sh.w *= sx; sh.h *= sy; if (sh.tipo === 'texto') sh.fuente = Math.max(6, (sh.fuente || 16) * avg); }
  }

  function redibujarAnot() {
    if (!anotCtx) return;
    const W = vistaEl.clientWidth, H = vistaEl.clientHeight;
    if (!W || !H) return;
    const q = dpr(), w = Math.max(1, Math.round(W * q)), h = Math.max(1, Math.round(H * q));
    if (anotEl.width !== w || anotEl.height !== h) { anotEl.width = w; anotEl.height = h; }
    anotCtx.setTransform(1, 0, 0, 1, 0, 0);
    anotCtx.clearRect(0, 0, w, h);
    if (!anot.ver || !slots.a.w) return;
    // La vista se dibuja con la misma transformación del documento: trazos y textos quedan nítidos a cualquier zoom.
    anotCtx.setTransform(q, 0, 0, q, 0, 0);
    anotCtx.translate(vista.x, vista.y);
    anotCtx.scale(vista.k, vista.k);
    anot.shapes.forEach((sh, i) => { if (enPaginaActual(sh)) dibujarShape(anotCtx, sh, seleccionada(i)); });
  }

  function siguienteNumero() {
    let n = 0; anot.shapes.forEach(s => { if (s.tipo === 'globo') n = Math.max(n, parseInt(s.numero, 10) || 0); });
    return n + 1;
  }

  function activarAnotar(si) {
    if (si && soloLectura) { avisar('Esta comparación es de solo lectura. Duplícala para anotarla.'); return; }
    anot.activo = si;
    if (si) est.moverB = false;
    cerrarEditor(false);
    pintarAnotUI();
    aplicar();
  }
  function pintarAnotUI() {
    const act = $('#cmp-anot-toggle');
    if (act) { act.setAttribute('aria-pressed', anot.activo); act.classList.toggle('btn-primary', anot.activo); act.classList.toggle('btn-ghost', !anot.activo); }
    const h = $('#cmp-anot-herr'); if (h) h.hidden = !anot.activo;
    document.querySelectorAll('.cmp-anot-tool').forEach(b => {
      const a = b.dataset.anot === anot.herramienta;
      b.classList.toggle('btn-primary', a); b.classList.toggle('btn-ghost', !a);
    });
    if ($('#cmp-anot-color')) $('#cmp-anot-color').value = anot.color;
    if ($('#cmp-anot-grosor')) $('#cmp-anot-grosor').value = String(anot.grosor);
    if ($('#cmp-anot-relleno')) $('#cmp-anot-relleno').checked = anot.relleno;
    if ($('#cmp-anot-undo')) $('#cmp-anot-undo').disabled = soloLectura || !anot.undo.length;
    if ($('#cmp-anot-redo')) $('#cmp-anot-redo').disabled = soloLectura || !anot.redo.length;
    if ($('#cmp-anot-borrar')) $('#cmp-anot-borrar').disabled = soloLectura || anot.sel < 0;
    if ($('#cmp-anot-cuenta')) $('#cmp-anot-cuenta').textContent = String(anot.shapes.filter(enPaginaActual).length);
    const ver = $('#cmp-anot-ver');
    if (ver) { ver.setAttribute('aria-pressed', anot.ver); ver.querySelector('i').className = 'fa-solid ' + (anot.ver ? 'fa-eye' : 'fa-eye-slash'); }
    const ayuda = $('#cmp-anot-ayuda');
    if (ayuda) {
      if (!anot.activo) ayuda.textContent = 'Activa Anotar y elige una herramienta.';
      else if (anot.calibrando) ayuda.textContent = 'Calibración: dibuja una distancia conocida del plano (una cota) y escribe cuántos metros mide.';
      else if (anot.herramienta === 'seleccion') ayuda.textContent = 'Haz clic para seleccionar; Mayús+clic suma a la selección; arrastra para mover; doble clic edita textos y globos.';
      else if (anot.herramienta === 'mano') ayuda.textContent = 'Arrastra para mover la vista; la rueda y el pellizco siguen haciendo zoom.';
      else if (anot.herramienta === 'medir' || anot.herramienta === 'area') ayuda.textContent = anot.escala ? 'Dibuja sobre el plano: la medida sale en ' + anot.unidad + ' con la escala calibrada.' : 'Dibuja la medida y pulsa «Calibrar» para darle escala real.';
      else ayuda.textContent = 'Dibuja sobre el documento A. Ctrl+Z deshace y Supr borra lo seleccionado.';
    }
    actualizarEscalaInfo();
    pintarLista();
    pintarDetalle();
    if (anotEl) anotEl.classList.toggle('activo', anot.activo && !est.moverB && anot.herramienta !== 'mano');
  }
  function actualizarEscalaInfo() {
    const el = $('#cmp-escala-info'); if (!el) return;
    el.textContent = anot.escala > 0 ? '1 m = ' + Math.round(1 / anot.escala) + ' px' : 'Sin escala';
  }

  function pintarLista() {
    const ol = $('#cmp-anot-items'); if (!ol) return;
    ol.innerHTML = '';
    const ix = indicesLista().filter(i => pasaFiltro(anot.shapes[i]));
    ix.forEach(i => {
      const sh = anot.shapes[i];
      const li = document.createElement('li');
      li.className = seleccionada(i) ? 'sel' : '';
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'cmp-anot-item';
      const icon = { globo: 'fa-comment-dots', texto: 'fa-font', resaltador: 'fa-highlighter', resaltado: 'fa-fill-drip', flecha: 'fa-arrow-right', linea: 'fa-minus', rect: 'fa-square', elipse: 'fa-circle', nube: 'fa-cloud', mano: 'fa-pen', medir: 'fa-ruler', area: 'fa-vector-square' }[sh.tipo] || 'fa-pen';
      const ico = document.createElement('i'); ico.className = 'fa-solid ' + icon;
      if ((sh.estado || 'abierta') === 'resuelta' && (sh.tipo === 'globo' || sh.tipo === 'texto')) ico.className = 'fa-solid fa-circle-check cmp-ok';
      const span = document.createElement('span');
      let texto = sh.tipo === 'globo' ? 'Globo ' + sh.numero + (sh.texto ? ': ' + sh.texto : '') : sh.tipo === 'texto' ? sh.texto : sh.tipo === 'medir' ? 'Medida ' + fmtLong(longitud(sh)) : sh.tipo === 'area' ? 'Área ' + fmtArea(Math.abs(sh.w * sh.h)) : sh.tipo;
      if (anot.todasPaginas || paginaDe(sh) !== paginaActual()) texto = 'p. ' + paginaDe(sh) + ' · ' + texto;
      if (sh.notas && sh.notas.length) texto += ' · ' + sh.notas.length + ' nota(s)';
      span.textContent = String(texto || '').slice(0, 90);
      btn.append(ico, span);
      btn.addEventListener('click', e => {
        seleccionar(i, e.shiftKey);
        anot.herramienta = 'seleccion'; centrarEn(i); pintarAnotUI(); redibujarAnot();
      });
      const acc = document.createElement('span'); acc.className = 'cmp-anot-li-acc';
      if (sh.tipo === 'globo' || sh.tipo === 'texto') {
        const st = document.createElement('button'); st.type = 'button';
        st.className = 'cmp-anot-estado-dot ' + ((sh.estado || 'abierta') === 'resuelta' ? 'ok' : 'abierta');
        st.title = (sh.estado || 'abierta') === 'resuelta' ? 'Reabrir' : 'Marcar como resuelta';
        st.innerHTML = '<i class="fa-solid ' + ((sh.estado || 'abierta') === 'resuelta' ? 'fa-circle-check' : 'fa-circle') + '"></i>';
        st.addEventListener('click', () => { snapshot(); sh.estado = (sh.estado || 'abierta') === 'resuelta' ? 'abierta' : 'resuelta'; pintarAnotUI(); redibujarAnot(); });
        acc.appendChild(st);
      }
      const del = document.createElement('button'); del.type = 'button'; del.className = 'cmp-anot-del'; del.title = 'Eliminar';
      del.innerHTML = '<i class="fa-solid fa-xmark"></i>';
      del.addEventListener('click', () => { snapshot(); anot.shapes.splice(i, 1); anot.sel = -1; anot.multi = []; pintarAnotUI(); redibujarAnot(); });
      acc.appendChild(del);
      li.append(btn, acc);
      ol.appendChild(li);
    });
    const vacio = $('#cmp-anot-vacio'); if (vacio) vacio.hidden = ix.length > 0;
  }

  function pintarDetalle() {
    const p = $('#cmp-anot-detalle'); if (!p) return;
    const sh = anot.shapes[anot.sel];
    if (!sh || !anot.activo && !anot.ver) { p.hidden = true; return; }
    p.hidden = false;
    const titulo = $('#cmp-det-titulo');
    titulo.textContent = sh.tipo === 'globo' ? 'Globo ' + sh.numero : sh.tipo === 'texto' ? 'Texto' : sh.tipo === 'medir' ? 'Medida' : sh.tipo === 'area' ? 'Área' : sh.tipo;
    const meta = [];
    if (sh.autor) meta.push(sh.autor);
    if (sh.creado) meta.push(fechaCorta(sh.creado));
    meta.push('pág. ' + paginaDe(sh));
    if (sh.tipo === 'medir') meta.push(fmtLong(longitud(sh)));
    if (sh.tipo === 'area') meta.push(fmtArea(Math.abs(sh.w * sh.h)));
    if ((sh.estado || 'abierta') === 'resuelta') meta.push('resuelta');
    $('#cmp-det-meta').textContent = meta.join(' · ');
    const estBtn = $('#cmp-det-estado');
    estBtn.hidden = !(sh.tipo === 'globo' || sh.tipo === 'texto');
    estBtn.innerHTML = (sh.estado || 'abierta') === 'resuelta' ? '<i class="fa-solid fa-rotate-left"></i> Reabrir' : '<i class="fa-solid fa-circle-check"></i> Resolver';
    estBtn.disabled = soloLectura;
    const gb = $('#cmp-det-grupo');
    if (gb) {
      const n = sh.grupo ? anot.shapes.filter(s => s.grupo === sh.grupo).length : (anot.multi.length || 1);
      gb.innerHTML = sh.grupo ? '<i class="fa-solid fa-object-ungroup"></i> Desagrupar' : '<i class="fa-solid fa-object-group"></i> Agrupar (' + n + ')';
      gb.hidden = !sh.grupo && n < 2;
      gb.disabled = soloLectura;
    }
    const notas = $('#cmp-det-notas'); notas.innerHTML = '';
    (sh.notas || []).forEach(n => {
      const d = document.createElement('div'); d.className = 'cmp-nota';
      const c = document.createElement('div'); c.className = 'cmp-nota-cab';
      const a = document.createElement('strong'); a.textContent = n.autor || '—';
      const f = document.createElement('small'); f.className = 'text-muted'; f.textContent = fechaCorta(n.fecha);
      c.append(a, f);
      const t = document.createElement('p'); t.textContent = n.texto;
      d.append(c, t); notas.appendChild(d);
    });
    if (!(sh.notas || []).length) { const v = document.createElement('p'); v.className = 'text-sm text-muted'; v.textContent = 'Sin notas todavía.'; notas.appendChild(v); }
    const form = $('#cmp-nota-form'); form.hidden = soloLectura;
    if (soloLectura) form.hidden = true;
    ['#cmp-det-punch', '#cmp-det-rfi', '#cmp-det-mover'].forEach(s => { const b = $(s); if (b) b.disabled = soloLectura; });
    $('#cmp-det-punch').hidden = !C.puedeGuardar; $('#cmp-det-rfi').hidden = !C.puedeGuardar;
  }
  function centrarEn(i) {
    const sh = anot.shapes[i]; if (!sh || !vistaEl.clientWidth) return;
    const b = caja(sh);
    vista.x = vistaEl.clientWidth / 2 - (b.x + b.w / 2) * vista.k;
    vista.y = vistaEl.clientHeight / 2 - (b.y + b.h / 2) * vista.k;
    aplicar();
  }

  // editor de texto flotante
  const editor = $('#cmp-anot-txt');
  function abrirEditor(i) {
    const sh = anot.shapes[i]; if (!sh || !editor) return;
    anot.editando = i; anot.sel = i; anot.multi = [];
    editor.value = sh.texto || '';
    const b = sh.tipo === 'globo' && !sh.texto ? { x: sh.x, y: sh.y, w: 200, h: 60 } : caja(sh);
    const vr = vistaEl.getBoundingClientRect(), k = Math.max(0.02, vista.k);
    // Posición en pantalla a partir de las coordenadas del documento (el lienzo ahora ocupa la ventana).
    const sx = vista.x + b.x * k, sy = vista.y + (b.y + b.h) * k;
    editor.style.left = Math.max(4, Math.min(vr.width - 190, sx)) + 'px';
    editor.style.top = Math.max(4, Math.min(vr.height - 48, sy + 8)) + 'px';
    editor.style.width = Math.min(340, Math.max(190, b.w * k)) + 'px';
    editor.hidden = false;
    setTimeout(() => { editor.focus(); editor.select(); }, 0);
    pintarAnotUI(); redibujarAnot();
  }
  function cerrarEditor(guardar = true) {
    if (anot.editando < 0) return;
    const i = anot.editando, sh = anot.shapes[i]; anot.editando = -1;
    if (sh && guardar) {
      sh.texto = editor.value.trim();
      if (sh.tipo === 'texto' && sh.texto === '') { anot.shapes.splice(i, 1); anot.sel = -1; }
    }
    editor.hidden = true;
    pintarAnotUI(); redibujarAnot();
  }
  editor.addEventListener('keydown', e => {
    if (e.key === 'Escape') { e.preventDefault(); cerrarEditor(false); }
    else if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); cerrarEditor(true); }
  });
  editor.addEventListener('blur', () => cerrarEditor(true));

  // interacción de anotaciones
  if (anotEl) {
    anotEl.addEventListener('pointerdown', e => {
      if (!anot.activo || est.moverB || anot.herramienta === 'mano' || soloLectura) return;
      if (gestos && gestos.activo()) return;
      e.preventDefault(); e.stopPropagation();
      if (anot.editando >= 0) cerrarEditor(true);
      const p = puntoA(e), e1 = escala();
      if (anot.herramienta === 'seleccion') {
        const h = hitHandle(p);
        if (h && anot.sel >= 0) {
          anot.drag = { modo: 'resize', i: anot.sel, h, ini: p, orig: deep(anot.sel) };
        } else {
          const i = hitIndex(p);
          if (i >= 0 && e.shiftKey) {
            seleccionar(i, true);
          } else {
            seleccionar(i, false);
          }
          if (i >= 0) anot.drag = { modo: 'move', i, ini: p, origs: (anot.multi.length > 1 ? anot.multi : [i]).map(x => [x, deep(x)]) };
        }
        pintarAnotUI(); redibujarAnot();
        anotEl.setPointerCapture(e.pointerId);
        return;
      }
      snapshot();
      const comun = { color: anot.color, grosor: anot.grosor, relleno: anot.relleno, texto: '', autor: C.usuario || '', creado: fechaISO(), estado: 'abierta', notas: [], grupo: 0, pagina: paginaActual() };
      let sh;
      if (anot.herramienta === 'texto') sh = Object.assign(comun, { tipo: 'texto', x: p.x, y: p.y, fuente: 16 });
      else if (anot.herramienta === 'globo') sh = Object.assign(comun, { tipo: 'globo', x: p.x + 40 * e1, y: p.y - 40 * e1, ax: p.x, ay: p.y, numero: siguienteNumero(), radio: 28 });
      else if (anot.herramienta === 'flecha' || anot.herramienta === 'linea' || anot.herramienta === 'medir') sh = Object.assign(comun, { tipo: anot.herramienta, x1: p.x, y1: p.y, x2: p.x, y2: p.y });
      else if (anot.herramienta === 'mano' || anot.herramienta === 'resaltador') sh = Object.assign(comun, { tipo: anot.herramienta, pts: [[p.x, p.y]] });
      else sh = Object.assign(comun, { tipo: anot.herramienta, x: p.x, y: p.y, w: 0, h: 0 });
      anot.shapes.push(sh); anot.sel = anot.shapes.length - 1; anot.multi = [anot.sel];
      pintarAnotUI(); redibujarAnot();
      if (anot.herramienta === 'texto' || anot.herramienta === 'globo') { abrirEditor(anot.sel); return; }
      anot.drag = { modo: 'draw', i: anot.sel, ini: p, orig: clone(sh) };
      anotEl.setPointerCapture(e.pointerId);
    });
    anotEl.addEventListener('pointermove', e => {
      const d = anot.drag; if (!d) return;
      const p = puntoA(e);
      if (d.modo !== 'draw' && !d.snap) { d.snap = true; snapshot(); }   // el estado previo, recién al mover
      if (d.modo === 'move') {
        const dx = p.x - d.ini.x, dy = p.y - d.ini.y;
        (d.origs || []).forEach(([i, orig]) => { const s = anot.shapes[i]; if (s) Object.assign(s, moverCopia(orig, dx, dy)); });
      } else if (d.modo === 'resize') {
        const o = d.orig, b = caja(o), h = d.h;
        let sx = 1, sy = 1, fx = b.x, fy = b.y;
        if (h.includes('e')) { sx = Math.max(.02, (p.x - b.x) / Math.max(1, b.w)); fx = b.x; }
        if (h.includes('w')) { sx = Math.max(.02, (b.x + b.w - p.x) / Math.max(1, b.w)); fx = b.x + b.w; }
        if (h.includes('s')) { sy = Math.max(.02, (p.y - b.y) / Math.max(1, b.h)); fy = b.y; }
        if (h.includes('n')) { sy = Math.max(.02, (b.y + b.h - p.y) / Math.max(1, b.h)); fy = b.y + b.h; }
        const sh = clone(o); escalar(sh, fx, fy, sx, sy); anot.shapes[d.i] = sh;
      } else if (d.modo === 'draw') {
        const sh = anot.shapes[d.i]; if (!sh) return;
        if (sh.tipo === 'mano' || sh.tipo === 'resaltador') sh.pts.push([p.x, p.y]);
        else if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') { sh.x2 = p.x; sh.y2 = p.y; }
        else {
          const x1 = Math.min(p.x, d.ini.x), y1 = Math.min(p.y, d.ini.y), x2 = Math.max(p.x, d.ini.x), y2 = Math.max(p.y, d.ini.y);
          sh.x = x1; sh.y = y1; sh.w = x2 - x1; sh.h = y2 - y1;
        }
      }
      redibujarAnot();
    });
    const fin = e => {
      const d = anot.drag; if (!d) return;
      const sh = anot.shapes[d.i];
      if (d.modo === 'draw' && sh) {
        if (vacia(sh) && (Math.abs(sh.w) < 4 * escala() || Math.abs(sh.h) < 4 * escala())) anot.shapes.splice(d.i, 1);
        else if (sh.tipo === 'mano' || sh.tipo === 'resaltador') { if (!sh.pts || sh.pts.length < 2) anot.shapes.splice(d.i, 1); }
        else if (sh.tipo === 'medir' && longitud(sh) < 4) anot.shapes.splice(d.i, 1);
      }
      anot.drag = null;
      if (anot.shapes.length <= d.i) anot.sel = -1;
      if (anot.sel >= 0) anot.sel = Math.min(anot.sel, anot.shapes.length - 1);
      if (d.modo === 'draw' && sh && sh.tipo === 'medir' && anot.calibrando && anot.shapes[d.i] === sh) calibrarCon(sh);
      if (d.modo === 'draw' && (anot.herramienta === 'flecha' || anot.herramienta === 'linea')) anot.herramienta = 'flecha';
      pintarAnotUI(); redibujarAnot();
    };
    anotEl.addEventListener('pointerup', fin); anotEl.addEventListener('pointercancel', fin);
    anotEl.addEventListener('dblclick', e => {
      if (!anot.activo || soloLectura) return;
      const i = hitIndex(puntoA(e));
      if (i >= 0 && (anot.shapes[i].tipo === 'texto' || anot.shapes[i].tipo === 'globo')) { snapshot(); abrirEditor(i); }
    });
  }
  function moverCopia(orig, dx, dy) { const s = clone(orig); mover(s, dx, dy); return s; }

  function calibrarCon(sh) {
    const px = longitud(sh); if (px <= 1) return;
    const m = parseFloat(prompt('¿Cuántos metros mide la línea que dibujaste?', '1'));
    anot.calibrando = false;
    if (!Number.isFinite(m) || m <= 0) { pintarAnotUI(); return; }
    anot.escala = m / px; anot.unidad = 'm';
    sh.texto = sh.texto || '';
    pintarAnotUI(); redibujarAnot();
  }

  // botones de anotación
  if ($('#cmp-anot-toggle')) {
    $('#cmp-anot-toggle').addEventListener('click', () => activarAnotar(!anot.activo));
    document.querySelectorAll('.cmp-anot-tool').forEach(b => b.addEventListener('click', () => {
      anot.herramienta = TIPOS_ANOT.includes(b.dataset.anot) ? b.dataset.anot : 'seleccion';
      anot.sel = anot.herramienta === 'seleccion' ? anot.sel : -1;
      anot.multi = anot.sel >= 0 ? [anot.sel] : [];
      pintarAnotUI(); redibujarAnot();
    }));
    $('#cmp-anot-color').addEventListener('input', e => { anot.color = e.target.value; });
    document.querySelectorAll('.cmp-anot-swatch').forEach(b => b.addEventListener('click', () => { anot.color = b.dataset.color; pintarAnotUI(); }));
    $('#cmp-anot-grosor').addEventListener('change', e => { anot.grosor = parseFloat(e.target.value) || 5; });
    $('#cmp-anot-relleno').addEventListener('change', e => { anot.relleno = e.target.checked; });
    $('#cmp-anot-undo').addEventListener('click', deshacer);
    $('#cmp-anot-redo').addEventListener('click', rehacer);
    $('#cmp-anot-borrar').addEventListener('click', () => { if (anot.sel < 0) return; snapshot(); anot.shapes.splice(anot.sel, 1); anot.sel = -1; anot.multi = []; pintarAnotUI(); redibujarAnot(); });
    $('#cmp-anot-ver').addEventListener('click', () => { anot.ver = !anot.ver; pintarAnotUI(); redibujarAnot(); });
    $('#cmp-anot-lista-btn').addEventListener('click', () => {
      const p = $('#cmp-anot-lista'), b = $('#cmp-anot-lista-btn');
      p.hidden = !p.hidden; b.setAttribute('aria-pressed', !p.hidden); b.classList.toggle('btn-primary', !p.hidden);
      pintarLista();
    });
    $('#cmp-anot-cerrar').addEventListener('click', () => { $('#cmp-anot-lista').hidden = true; $('#cmp-anot-lista-btn').setAttribute('aria-pressed', 'false'); $('#cmp-anot-lista-btn').classList.remove('btn-primary'); });
    if ($('#cmp-calibrar')) $('#cmp-calibrar').addEventListener('click', () => {
      anot.calibrando = true; anot.herramienta = 'medir'; anot.activo = true; pintarAnotUI();
    });
    document.querySelectorAll('.cmp-chip[data-filtro]').forEach(b => b.addEventListener('click', () => {
      anot.filtro = b.dataset.filtro;
      document.querySelectorAll('.cmp-chip[data-filtro]').forEach(x => x.classList.toggle('activo', x === b));
      pintarLista();
    }));
    if ($('#cmp-anot-todas-paginas')) $('#cmp-anot-todas-paginas').addEventListener('change', e => { anot.todasPaginas = e.target.checked; pintarAnotUI(); redibujarAnot(); });
    if ($('#cmp-det-cerrar')) $('#cmp-det-cerrar').addEventListener('click', () => { anot.sel = -1; anot.multi = []; pintarAnotUI(); redibujarAnot(); });
    if ($('#cmp-det-estado')) $('#cmp-det-estado').addEventListener('click', () => {
      const sh = anot.shapes[anot.sel]; if (!sh) return;
      snapshot(); sh.estado = (sh.estado || 'abierta') === 'resuelta' ? 'abierta' : 'resuelta'; pintarAnotUI(); redibujarAnot();
    });
    if ($('#cmp-det-grupo')) $('#cmp-det-grupo').addEventListener('click', () => {
      const sh = anot.shapes[anot.sel]; if (!sh) return;
      snapshot();
      if (sh.grupo) {
        const g = sh.grupo; anot.shapes.forEach(s => { if (s.grupo === g) s.grupo = 0; });
        anot.multi = [anot.sel];
      } else {
        const g = anot.shapes.reduce((m, s) => Math.max(m, parseInt(s.grupo, 10) || 0), 0) + 1;
        (anot.multi.length ? anot.multi : [anot.sel]).forEach(i => { if (anot.shapes[i]) anot.shapes[i].grupo = g; });
      }
      pintarAnotUI(); redibujarAnot();
    });
    if ($('#cmp-nota-form')) $('#cmp-nota-form').addEventListener('submit', e => {
      e.preventDefault();
      const sh = anot.shapes[anot.sel], t = $('#cmp-nota-texto');
      if (!sh || !t || !t.value.trim()) return;
      snapshot();
      (sh.notas = sh.notas || []).push({ texto: t.value.trim(), autor: C.usuario || '', fecha: fechaISO() });
      t.value = ''; pintarAnotUI(); redibujarAnot();
    });
    if ($('#cmp-det-mover')) $('#cmp-det-mover').addEventListener('click', () => {
      const sh = anot.shapes[anot.sel], max = slots.a.pdf ? slots.a.pdf.numPages : 1;
      if (!sh) return;
      const n = parseInt(prompt('Mover la anotación a la página (1–' + max + '):', paginaDe(sh)), 10);
      if (!n || n < 1 || n > max) return;
      snapshot(); sh.pagina = n; anot.sel = -1; anot.multi = []; pintarAnotUI(); redibujarAnot();
    });
    if ($('#cmp-det-punch')) $('#cmp-det-punch').addEventListener('click', () => crearPunch());
    if ($('#cmp-det-rfi')) $('#cmp-det-rfi').addEventListener('click', () => crearRfi());
    document.addEventListener('keydown', e => {
      const tag = (document.activeElement || {}).tagName || '';
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;
      if (!anot.activo) return;
      const ctrl = e.ctrlKey || e.metaKey;
      if (ctrl && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? rehacer() : deshacer(); return; }
      if (ctrl && e.key.toLowerCase() === 'c' && anot.sel >= 0) { anot.portapapeles = (anot.multi.length ? anot.multi : [anot.sel]).map(i => clone(anot.shapes[i])).filter(Boolean); return; }
      if (ctrl && e.key.toLowerCase() === 'v' && anot.portapapeles.length) { e.preventDefault(); pegar(); return; }
      if (ctrl && e.key.toLowerCase() === 'd' && anot.sel >= 0) { e.preventDefault(); duplicarSeleccion(); return; }
      if ((e.key === 'Delete' || e.key === 'Backspace') && anot.sel >= 0) { e.preventDefault(); snapshot(); const ix = (anot.multi.length ? anot.multi : [anot.sel]).slice().sort((a, b) => b - a); ix.forEach(i => anot.shapes.splice(i, 1)); anot.sel = -1; anot.multi = []; pintarAnotUI(); redibujarAnot(); return; }
      if (e.key === 'Escape') { anot.sel = -1; anot.multi = []; anot.herramienta = 'seleccion'; pintarAnotUI(); redibujarAnot(); return; }
      const k = e.key.toLowerCase(), mapa = { v: 'seleccion', g: 'globo', t: 'texto', f: 'flecha', l: 'linea', r: 'rect', e: 'elipse', n: 'nube', m: 'medir', a: 'area' };
      if (mapa[k]) { anot.herramienta = mapa[k]; pintarAnotUI(); }
    });
  }
  function pegar() {
    snapshot();
    const base = anot.portapapeles.map(clone);
    const off = 20 * escala();
    base.forEach(sh => { mover(sh, off, off); sh.autor = C.usuario || ''; sh.creado = fechaISO(); sh.pagina = paginaActual(); sh.estado = 'abierta'; anot.shapes.push(sh); });
    anot.multi = anot.shapes.map((_, i) => i).slice(-base.length);
    anot.sel = anot.multi[anot.multi.length - 1] || -1;
    pintarAnotUI(); redibujarAnot();
  }
  function duplicarSeleccion() {
    const ix = anot.multi.length ? anot.multi.slice() : [anot.sel];
    const ors = ix.map(i => clone(anot.shapes[i])).filter(Boolean);
    snapshot();
    ors.forEach(sh => { mover(sh, 18 * escala(), 18 * escala()); sh.creado = fechaISO(); sh.autor = C.usuario || ''; anot.shapes.push(sh); });
    anot.multi = anot.shapes.map((_, i) => i).slice(-ors.length);
    anot.sel = anot.multi[anot.multi.length - 1] || -1;
    pintarAnotUI(); redibujarAnot();
  }
  function crearPunch() {
    const sh = anot.shapes[anot.sel]; if (!sh) return;
    const txt = (sh.texto || '').trim() || ('Globo ' + (sh.numero || '') + ' de la comparación').trim();
    const desc = txt + (sh.notas && sh.notas.length ? '\n\nNotas:\n' + sh.notas.map(n => '· ' + n.texto).join('\n') : '') + '\n\nOrigen: ' + (nombreActual || 'comparación de documentos') + ' · pág. ' + paginaDe(sh);
    const q = new URLSearchParams({ proyecto: C.proyecto, desde: 'comparador', titulo: txt.slice(0, 200), ubicacion: (slots.a.nombre || '') + ' · pág. ' + paginaDe(sh), descripcion: desc });
    window.open('/punch?' + q.toString(), '_blank', 'noopener');
  }
  function crearRfi() {
    const sh = anot.shapes[anot.sel]; if (!sh) return;
    const txt = (sh.texto || '').trim() || 'Aclaración sobre la comparación de documentos';
    const pregunta = txt + (sh.notas && sh.notas.length ? '\n\nNotas:\n' + sh.notas.map(n => '· ' + n.texto).join('\n') : '') + '\n\nDocumento: ' + (slots.a.nombre || 'A') + ' pág. ' + paginaDe(sh);
    const q = new URLSearchParams({ proyecto: C.proyecto, nuevo: 1, asunto: txt.slice(0, 200), pregunta, desde: 'comparador' });
    window.open('/rfi?' + q.toString(), '_blank', 'noopener');
  }
  pintarAnotUI();

  // ===================================================================== presentación
  function entrarPresentacion() {
    if (!anot.shapes.length) { avisar('Agrega anotaciones antes de presentar.'); return; }
    anot.presentando = true;
    $('#cmp').classList.add('presentando');
    const p = $('#cmp-presentacion'); if (p) p.hidden = false;
    const ix = indicesPagina();
    anot.presIdx = ix.length ? ix[0] : 0;
    irPresentacion(0);
    const req = vistaEl.requestFullscreen ? vistaEl.requestFullscreen() : null;
    if (req && req.catch) req.catch(() => {});
  }
  function salirPresentacion() {
    anot.presentando = false;
    $('#cmp') && $('#cmp').classList.remove('presentando');
    const p = $('#cmp-presentacion'); if (p) p.hidden = true;
    if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    aplicar();
  }
  function irPresentacion(dir) {
    const ix = indicesPagina().filter(i => (anot.shapes[i].tipo === 'globo' || anot.shapes[i].tipo === 'texto' || anot.shapes[i].tipo === 'medir' || anot.shapes[i].tipo === 'area'));
    if (!ix.length) return;
    let pos = ix.indexOf(anot.sel);
    pos = pos < 0 ? 0 : (pos + dir + ix.length) % ix.length;
    anot.sel = ix[pos]; anot.multi = [anot.sel];
    centrarEn(anot.sel);
    const sh = anot.shapes[anot.sel];
    const info = $('#cmp-pres-info');
    if (info) info.textContent = (anot.sel + 1) + '/' + anot.shapes.length + ' · ' + (sh.tipo === 'globo' ? 'Globo ' + sh.numero : sh.tipo) + (sh.texto ? ': ' + sh.texto.slice(0, 80) : '');
    pintarAnotUI(); redibujarAnot();
  }
  if ($('#cmp-presentar')) $('#cmp-presentar').addEventListener('click', () => anot.presentando ? salirPresentacion() : entrarPresentacion());
  if ($('#cmp-presentacion')) {
    $('#cmp-pres-prev').addEventListener('click', () => irPresentacion(-1));
    $('#cmp-pres-next').addEventListener('click', () => irPresentacion(1));
    $('#cmp-pres-salir').addEventListener('click', salirPresentacion);
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement && anot.presentando) salirPresentacion(); });
    document.addEventListener('keydown', e => {
      if (!anot.presentando) return;
      if (e.key === 'ArrowLeft') { e.preventDefault(); irPresentacion(-1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); irPresentacion(1); }
      if (e.key === 'Escape') { e.preventDefault(); salirPresentacion(); }
    });
  }

  // ===================================================================== guardar / abrir
  function fuenteGuardar(k) {
    const s = slots[k], o = s.origen || {};
    const proyecto = ['archivo', 'vplan', 'editor', 'redactor'].includes(o.tipo);
    return {
      tipo: proyecto ? o.tipo : (o.tipo === 'subido' ? 'subido' : 'nuevo'),
      id: proyecto ? parseInt(o.id, 10) || 0 : 0,
      archivo: o.tipo === 'subido' ? String(o.archivo || '') : '',
      nombre: String(o.nombre || s.nombre || ''), ext: String(o.ext || ''), pagina: parseInt(s.pagina, 10) || 1,
    };
  }
  function guardarDialogo() {
    if (!(slots.a.listo && slots.b.listo)) { avisar('Carga los dos documentos antes de guardar.'); return; }
    if (!C.puedeGuardar) { avisar('No tienes permiso para guardar comparaciones.'); return; }
    $('#cmp-nombre').value = nombreActual || '';
    $('#cmp-desc').value = descripcionActual || '';
    $('#cmp-copia').checked = false;
    $('#cmp-copia-l').hidden = !guardadoId;
    const externos = ['a', 'b'].some(k => slots[k].origen && (slots[k].origen.tipo === 'nuevo'));
    $('#cmp-guardar-aviso').textContent = externos ? 'Los documentos externos se guardarán como copia en el proyecto y ocuparán almacenamiento.' : '';
    const dlg = $('#cmp-guardar-dlg');
    if (dlg && dlg.showModal) dlg.showModal(); else { if (nombreActual === '' && !(nombreActual = prompt('Nombre de la comparación') || '')) return; confirmarGuardar(); }
  }
  function miniatura() {
    const v = vistaEl, W = Math.max(1, v.clientWidth / Math.max(.02, vista.k)), H = Math.max(1, v.clientHeight / Math.max(.02, vista.k));
    const f = Math.min(1, 1400 / Math.max(W, H));
    const cv = document.createElement('canvas'); cv.width = Math.max(1, Math.round(W * f)); cv.height = Math.max(1, Math.round(H * f));
    const g = cv.getContext('2d'); g.scale(f, f);
    dibujarComposicion(g, -vista.x / vista.k, -vista.y / vista.k, W, H, 'ambos');
    if (anot.ver) dibujarAnotaciones(g, -vista.x / vista.k, -vista.y / vista.k);
    return cv.toDataURL('image/jpeg', .82);
  }
  async function confirmarGuardar() {
    const nombre = $('#cmp-nombre') ? $('#cmp-nombre').value.trim() : nombreActual.trim();
    if (!nombre) { $('#cmp-guardar-aviso').textContent = 'Escribe un nombre.'; return; }
    const dlg = $('#cmp-guardar-dlg'); if (dlg && dlg.open) dlg.close();
    avisar('Guardando…');
    const fd = new FormData();
    const copia = $('#cmp-copia') && $('#cmp-copia').checked;
    fd.set('proyecto', C.proyecto); if (guardadoId && !copia) fd.set('id', guardadoId);
    fd.set('nombre', nombre); fd.set('descripcion', $('#cmp-desc') ? $('#cmp-desc').value : descripcionActual);
    fd.set('a_json', JSON.stringify(fuenteGuardar('a'))); fd.set('b_json', JSON.stringify(fuenteGuardar('b')));
    fd.set('vista', JSON.stringify(vista)); fd.set('ajuste', JSON.stringify(bT));
    fd.set('modo', JSON.stringify({ modo: est.modo, pos: est.pos, dir: est.dir, fade: parseInt($('#cmp-fade').value, 10) || 50, tol: parseInt($('#cmp-diff-tol').value, 10) || 0 }));
    fd.set('anotaciones', JSON.stringify({ ancho: slots.a.w, alto: slots.a.h, escala: anot.escala, unidad: anot.unidad, tamanos: slots.a.tamanos, shapes: anot.shapes }));
    fd.set('miniatura', miniatura());
    ['a', 'b'].forEach(k => { if (slots[k].archivo) fd.append(k + '_archivo', slots[k].archivo, slots[k].archivo.name); });
    try {
      const r = await fetch('/comparador/guardar', { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (!d.success) { avisar(d.error || 'No se pudo guardar.'); return; }
      nombreActual = nombre; descripcionActual = fd.get('descripcion') || '';
      guardadoId = d.id;
      location.href = '/comparador?proyecto=' + C.proyecto + '&guardado=' + d.id + '&ok=1';
    } catch (e) { avisar('No se pudo guardar: ' + (e && e.message ? e.message : 'error')); }
  }
  if ($('#cmp-guardar')) $('#cmp-guardar').addEventListener('click', guardarDialogo);
  if ($('#cmp-cancelar')) $('#cmp-cancelar').addEventListener('click', () => { const d = $('#cmp-guardar-dlg'); if (d && d.open) d.close(); });
  if ($('#cmp-confirmar-guardar')) $('#cmp-confirmar-guardar').addEventListener('click', confirmarGuardar);

  async function restaurarGuardado(datos) {
    if (!datos) return;
    restaurando = true;
    avisar('Abriendo «' + datos.nombre + '»…');
    for (const k of ['a', 'b']) {
      const src = datos[k]; if (!src) continue;
      slots[k].origen = src; slots[k].pagina = parseInt(src.pagina, 10) || 1;
      let url;
      if (C.publico) url = '/comparador/publico/fuente?t=' + encodeURIComponent(C.token) + '&slot=' + k;
      else if (src.tipo === 'subido' || src.tipo === 'nuevo') url = '/comparador/guardado/archivo?proyecto=' + C.proyecto + '&id=' + datos.id + '&slot=' + k;
      else url = '/comparador/fuente?proyecto=' + C.proyecto + '&tipo=' + encodeURIComponent(src.tipo) + '&id=' + encodeURIComponent(src.id);
      try {
        const r = await fetch(url, { credentials: 'same-origin' });
        if (!r.ok) throw new Error('el servidor respondió ' + r.status);
        await abrir(k, await r.arrayBuffer(), src.ext, src.nombre || ('Documento ' + k.toUpperCase()), src.pagina);
      } catch (e) { estado(k, 'No disponible: ' + e.message); }
    }
    if (datos.vista) Object.assign(vista, datos.vista);
    if (datos.ajuste) Object.assign(bT, datos.ajuste);
    if (datos.modo) Object.assign(est, datos.modo);
    if ($('#cmp-fade')) $('#cmp-fade').value = String(est.fade);
    if ($('#cmp-dir')) $('#cmp-dir').value = est.dir;
    if ($('#cmp-diff-tol')) $('#cmp-diff-tol').value = String(est.tol ?? 24);
    pintarSelectores();
    cargarAnotaciones(datos.anotaciones);
    $('#cmp').hidden = false;
    aplicar(); sizeAnot(); redibujarAnot(); pintarAnotUI();
    est.encuadrado = true;
    avisar(datos.puede_editar ? 'Guardada por ' + (datos.autor || '—') : 'Solo lectura · puedes duplicarla para editarla');
    if (!datos.puede_editar) {
      soloLectura = true;
      const b = $('#cmp-guardar'); if (b) { b.disabled = true; b.title = 'Solo quien la creó o el administrador pueden modificarla. Usa Duplicar para crear tu copia.'; }
      const t = $('#cmp-anot-toggle'); if (t) { t.disabled = true; t.title = 'Solo lectura: duplica la comparación para anotarla.'; }
    }
    restaurando = false;
    pintarAnotUI();
    if (H && guardadoId && !C.publico) abrirVersiones();
  }
  function cargarAnotaciones(d) {
    anot.shapes = [];
    anot.multi = []; anot.sel = -1;
    if (!d || !Array.isArray(d.shapes)) { redibujarAnot(); pintarAnotUI(); return; }
    if (d.tamanos && typeof d.tamanos === 'object') {
      Object.keys(d.tamanos).forEach(p => { const t = d.tamanos[p]; if (Array.isArray(t) && t.length >= 2) slots.a.tamanos[p] = { w: +t[0], h: +t[1] }; });
    }
    anot.shapes = clone(d.shapes).map(sh => {
      sh.pagina = Math.max(1, parseInt(sh.pagina, 10) || 1);
      const guardado = (d.tamanos && d.tamanos[sh.pagina]) || [d.ancho, d.alto];
      const actual = slots.a.tamanos[sh.pagina] || { w: guardado[0] || d.ancho, h: guardado[1] || d.alto };
      const sx = guardado[0] ? actual.w / guardado[0] : 1, sy = guardado[1] ? actual.h / guardado[1] : 1;
      const avg = (Math.abs(sx) + Math.abs(sy)) / 2;
      const m = v => v * sx, n = v => v * sy;
      if (sh.tipo === 'mano' || sh.tipo === 'resaltador') sh.pts = (sh.pts || []).map(p => [m(p[0]), n(p[1])]);
      else if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') { sh.x1 = m(sh.x1); sh.x2 = m(sh.x2); sh.y1 = n(sh.y1); sh.y2 = n(sh.y2); }
      else if (sh.tipo === 'globo') { sh.x = m(sh.x); sh.ax = m(sh.ax); sh.y = n(sh.y); sh.ay = n(sh.ay); sh.radio = (sh.radio || 28) * avg; }
      else { sh.x = m(sh.x); sh.y = n(sh.y); sh.w = (sh.w || 0) * sx; sh.h = (sh.h || 0) * sy; sh.fuente = (sh.fuente || 16) * avg; }
      sh.grosor = (sh.grosor || 5) * avg;
      sh.estado = sh.estado === 'resuelta' ? 'resuelta' : 'abierta';
      sh.notas = Array.isArray(sh.notas) ? sh.notas.slice(0, 50) : [];
      return sh;
    });
    const base = d.tamanos && d.tamanos[1] ? d.tamanos[1] : [d.ancho, d.alto];
    const actual1 = slots.a.tamanos[1] || { w: base[0], h: base[1] };
    anot.escala = d.escala && base[0] ? d.escala * (base[0] / actual1.w) : 0;
    anot.unidad = d.unidad || 'm';
    anot.contador = 0;
    redibujarAnot(); pintarAnotUI();
  }

  // ===================================================================== compartir
  if ($('#cmp-compartir')) $('#cmp-compartir').addEventListener('click', () => {
    if (!guardadoId) { avisar('Guarda la comparación antes de compartirla.'); return; }
    const d = $('#cmp-compartir-dlg');
    const url = (G && G.publico_url) || (window.CMP_GUARDADO && window.CMP_GUARDADO.publico_url) || '';
    $('#cmp-enlace').value = url;
    $('#cmp-compartir-estado').textContent = url ? 'Enlace activo.' : 'Todavía no hay enlace. Pulsa «Crear enlace».';
    if (d && d.showModal) d.showModal();
  });
  const compartirPost = async (datos) => {
    const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.set('id', guardadoId);
    Object.entries(datos || {}).forEach(([k, v]) => fd.set(k, v));
    const r = await fetch('/comparador/compartir', { method: 'POST', body: fd, credentials: 'same-origin' });
    return r.json();
  };
  if ($('#cmp-crear-enlace')) $('#cmp-crear-enlace').addEventListener('click', async () => {
    if (!guardadoId) return;
    const d = await compartirPost({});
    if (!d.success) { $('#cmp-compartir-estado').textContent = d.error || 'No se pudo crear el enlace.'; return; }
    $('#cmp-enlace').value = d.url; if (window.CMP_GUARDADO) window.CMP_GUARDADO.publico_url = d.url; $('#cmp-compartir-estado').textContent = 'Enlace listo. Cópialo o envíalo por Telegram.';
  });
  if ($('#cmp-copiar-enlace')) $('#cmp-copiar-enlace').addEventListener('click', async () => {
    const t = $('#cmp-enlace').value;
    if (!t) { $('#cmp-compartir-estado').textContent = 'Primero crea el enlace.'; return; }
    try { await navigator.clipboard.writeText(t); $('#cmp-compartir-estado').textContent = 'Enlace copiado.'; }
    catch (e) { $('#cmp-enlace').select(); document.execCommand('copy'); $('#cmp-compartir-estado').textContent = 'Enlace copiado.'; }
  });
  if ($('#cmp-revocar-enlace')) $('#cmp-revocar-enlace').addEventListener('click', async () => {
    if (!guardadoId || !confirm('¿Revocar el enlace? Quien lo tenga dejará de poder abrirlo.')) return;
    const d = await compartirPost({ revocar: 1 });
    if (!d.success) { $('#cmp-compartir-estado').textContent = d.error || 'No se pudo revocar.'; return; }
    $('#cmp-enlace').value = ''; if (window.CMP_GUARDADO) window.CMP_GUARDADO.publico_url = ''; $('#cmp-compartir-estado').textContent = 'Enlace revocado.';
  });
  if ($('#cmp-enviar-telegram')) $('#cmp-enviar-telegram').addEventListener('click', async () => {
    if (!guardadoId) { $('#cmp-compartir-estado').textContent = 'Guarda la comparación antes de enviarla.'; return; }
    const btn = $('#cmp-enviar-telegram'); btn.disabled = true;
    $('#cmp-compartir-estado').textContent = 'Enviando…';
    try {
      let enlace = $('#cmp-enlace').value.trim();
      if (!enlace) { const d = await compartirPost({}); if (d.success) { enlace = d.url; $('#cmp-enlace').value = enlace; } }
      const fd = new FormData();
      fd.set('proyecto', C.proyecto); fd.set('id', guardadoId); fd.set('mensaje', $('#cmp-tg-mensaje').value);
      if ($('#cmp-tg-pdf').checked) {
        try { const doc = await construirPdf(); fd.set('pdf', doc.output('datauristring').replace(/^data:application\/pdf;[^,]*,/, 'data:application/pdf;base64,')); }
        catch (e) { /* sin adjunto */ }
      }
      const r = await fetch('/comparador/telegram', { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      $('#cmp-compartir-estado').textContent = d.success ? (d.mensaje || 'Enviado.') : (d.error || 'No se pudo enviar.');
    } catch (e) { $('#cmp-compartir-estado').textContent = 'No se pudo enviar: ' + (e.message || 'error'); }
    finally { btn.disabled = false; }
  });
  if ($('#cmp-compartir-cerrar')) $('#cmp-compartir-cerrar').addEventListener('click', () => { const d = $('#cmp-compartir-dlg'); if (d && d.open) d.close(); });

  // ===================================================================== versiones
  async function abrirVersiones() {
    if (!guardadoId) { avisar('Abre una comparación guardada para ver su historial.'); return; }
    const dlg = $('#cmp-versiones-dlg');
    if (dlg && dlg.showModal) dlg.showModal();
    const lista = $('#cmp-versiones-lista'); if (!lista) return;
    lista.textContent = 'Cargando…';
    try {
      const r = await fetch('/comparador/versiones?proyecto=' + C.proyecto + '&id=' + guardadoId, { credentials: 'same-origin' });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'No se pudo cargar');
      lista.innerHTML = '';
      if (!d.versiones.length) { const p = document.createElement('p'); p.className = 'text-muted'; p.textContent = 'Todavía no hay versiones anteriores (se crean al volver a guardar).'; lista.appendChild(p); return; }
      d.versiones.forEach(v => {
        const f = document.createElement('div'); f.className = 'cmp-ver-fila';
        const i = document.createElement('div');
        const b = document.createElement('strong'); b.textContent = fechaCorta(v.creado);
        const s = document.createElement('small'); s.className = 'text-muted'; s.textContent = (v.autor || '—') + ' · ' + v.anotaciones + ' anotación(es)';
        i.append(b, s);
        const r2 = document.createElement('button'); r2.type = 'button'; r2.className = 'btn btn-xs btn-ghost'; r2.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Restaurar';
        r2.addEventListener('click', async () => {
          if (!confirm('¿Restaurar esta versión? El estado actual se guarda como una versión más.')) return;
          const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.set('id', guardadoId); fd.set('version', v.id);
          const rr = await fetch('/comparador/restaurar', { method: 'POST', body: fd, credentials: 'same-origin' });
          const dd = await rr.json();
          if (!dd.success) { alert(dd.error || 'No se pudo restaurar.'); return; }
          if (dd.datos.vista) Object.assign(vista, dd.datos.vista);
          if (dd.datos.ajuste) Object.assign(bT, dd.datos.ajuste);
          if (dd.datos.modo) Object.assign(est, dd.datos.modo);
          cargarAnotaciones(dd.datos.anotaciones);
          aplicar(); redibujarAnot(); pintarAnotUI();
          abrirVersiones();
        });
        f.append(i, r2); lista.appendChild(f);
      });
    } catch (e) { lista.textContent = 'No se pudo cargar el historial: ' + e.message; }
  }
  if ($('#cmp-versiones')) $('#cmp-versiones').addEventListener('click', abrirVersiones);
  if ($('#cmp-versiones-cerrar')) $('#cmp-versiones-cerrar').addEventListener('click', () => { const d = $('#cmp-versiones-dlg'); if (d && d.open) d.close(); });

  // ===================================================================== páginas
  const pags = { datos: [], ocupado: false };
  const renderPagina = async (pdf, n, lado) => {
    const page = await pdf.getPage(n), base = page.getViewport({ scale: 1 });
    const vp = page.getViewport({ scale: lado / Math.max(base.width, base.height) });
    const cv = document.createElement('canvas'); cv.width = Math.round(vp.width); cv.height = Math.round(vp.height);
    const g = cv.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, cv.width, cv.height);
    await page.render({ canvasContext: g, viewport: vp }).promise;
    return { cv, w: base.width, h: base.height };
  };
  const pctEntre = (a, b) => {
    const w = 140, h = Math.max(1, Math.round(w * a.cv.height / a.cv.width));
    const ca = document.createElement('canvas'), cb = document.createElement('canvas');
    ca.width = cb.width = w; ca.height = cb.height = h;
    const ga = ca.getContext('2d'), gb = cb.getContext('2d');
    ga.drawImage(a.cv, 0, 0, w, h);
    const f = w / a.w;
    gb.fillStyle = '#fff'; gb.fillRect(0, 0, w, h);
    gb.save(); gb.scale(f, f);
    gb.translate(bT.x + b.w / 2, bT.y + b.h / 2); gb.rotate(bT.rot * Math.PI / 180); gb.scale(bT.s, bT.s);
    gb.drawImage(b.cv, -b.w / 2, -b.h / 2); gb.restore();
    const da = ga.getImageData(0, 0, w, h).data, db = gb.getImageData(0, 0, w, h).data;
    const tol = Math.max(0, Math.min(100, parseInt(($('#cmp-diff-tol') || {}).value, 10) || 24));
    let n = 0;
    for (let i = 0; i < da.length; i += 4) if (Math.max(Math.abs(da[i] - db[i]), Math.abs(da[i + 1] - db[i + 1]), Math.abs(da[i + 2] - db[i + 2])) > tol) n++;
    return 100 * n / (w * h);
  };
  async function analizarPaginas() {
    if (pags.ocupado) return;
    const a = slots.a, b = slots.b;
    if (!a.pdf) { $('#cmp-analizar-estado').textContent = 'El documento A debe ser un PDF de varias páginas.'; return; }
    const n = Math.min(a.pdf.numPages, 80);
    pags.ocupado = true; pags.datos = [];
    const grid = $('#cmp-paginas-grid'); grid.innerHTML = '';
    const estd = $('#cmp-analizar-estado');
    for (let p = 1; p <= n; p++) {
      estd.textContent = 'Analizando ' + p + ' de ' + n + '…';
      try {
        const cvA = await renderPagina(a.pdf, p, 260);
        const cvB = b.pdf ? await renderPagina(b.pdf, Math.min(p, b.pdf.numPages), 260) : { cv: b.canvas, w: b.w, h: b.h };
        const pct = pctEntre(cvA, cvB);
        pags.datos.push({ p, pct, img: cvA.cv.toDataURL('image/jpeg', .72) });
        pintarPags();
      } catch (e) { /* página no disponible */ }
      await new Promise(r => setTimeout(r, 0));
    }
    estd.textContent = 'Listo: ' + n + ' página(s).';
    pags.ocupado = false;
  }
  function pintarPags() {
    const grid = $('#cmp-paginas-grid'); if (!grid) return;
    grid.innerHTML = '';
    pags.datos.forEach(d => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'cmp-pag-item' + (d.pct > 0.2 ? ' cambio' : '');
      const img = document.createElement('img'); img.src = d.img; img.alt = 'Página ' + d.p;
      const l = document.createElement('span'); l.textContent = 'Pág. ' + d.p;
      const v = document.createElement('b'); v.textContent = d.pct.toFixed(1) + '%';
      b.append(img, l, v);
      b.addEventListener('click', async () => {
        const ia = document.querySelector('.cmp-pagina[data-slot="a"]'), ib = document.querySelector('.cmp-pagina[data-slot="b"]');
        if (ia) { ia.value = d.p; ia.dispatchEvent(new Event('change')); }
        if (slots.b.pdf && ib) { ib.value = Math.min(d.p, slots.b.pdf.numPages); ib.dispatchEvent(new Event('change')); }
        const dlg = $('#cmp-paginas-dlg'); if (dlg && dlg.open) dlg.close();
      });
      grid.appendChild(b);
    });
    const ex = $('#cmp-exportar-cambios'); if (ex) ex.disabled = !pags.datos.some(d => d.pct > 0.2);
  }
  async function exportarCambios() {
    const cambios = pags.datos.filter(d => d.pct > 0.2);
    if (!cambios.length) return;
    if (!(window.jspdf && window.jspdf.jsPDF)) { $('#cmp-analizar-estado').textContent = 'No se pudo cargar jsPDF.'; return; }
    let pw, ph;
    const fmt = $('#cmp-pdf-fmt').value;
    if (fmt === 'custom') { pw = (parseFloat($('#cmp-pdf-w').value) || 0) * 10; ph = (parseFloat($('#cmp-pdf-h').value) || 0) * 10; }
    else [pw, ph] = fmt.split('x').map(Number);
    const ori = $('#cmp-pdf-ori').value, corto = Math.min(pw, ph), largo = Math.max(pw, ph);
    const horiz = ori === 'l' || (ori === 'auto' && slots.a.w >= slots.a.h);
    const PW = horiz ? largo : corto, PH = horiz ? corto : largo;
    const doc = new window.jspdf.jsPDF({ unit: 'mm', format: [PW, PH], orientation: horiz ? 'l' : 'p', compress: true });
    const u = Math.min(PW, PH) / 215.9, mg = 10 * u, estd = $('#cmp-analizar-estado');
    for (let i = 0; i < cambios.length; i++) {
      const d = cambios[i];
      estd.textContent = 'Generando página ' + d.p + ' (' + (i + 1) + '/' + cambios.length + ')…';
      const a = await renderPagina(slots.a.pdf, d.p, 1100);
      const b = slots.b.pdf ? await renderPagina(slots.b.pdf, Math.min(d.p, slots.b.pdf.numPages), 1100) : { cv: slots.b.canvas, w: slots.b.w, h: slots.b.h };
      const cv = document.createElement('canvas'); cv.width = a.cv.width; cv.height = a.cv.height;
      const g = cv.getContext('2d');
      g.fillStyle = '#fff'; g.fillRect(0, 0, cv.width, cv.height); g.drawImage(a.cv, 0, 0);
      const f = cv.width / a.w;
      g.save();
      if (est.modo === 'diff') g.globalCompositeOperation = 'difference';
      else if (est.modo === 'fade') g.globalAlpha = (parseInt($('#cmp-fade').value, 10) || 50) / 100;
      if (est.modo === 'slider') {
        g.beginPath();
        if (est.dir === 'v') g.rect(cv.width * est.pos, 0, cv.width, cv.height);
        else g.rect(0, cv.height * est.pos, cv.width, cv.height);
        g.clip();
      }
      g.translate(bT.x * f + (b.w / 2) * f, bT.y * f + (b.h / 2) * f);
      g.rotate(bT.rot * Math.PI / 180); g.scale(bT.s * f, bT.s * f);
      g.drawImage(b.cv, -b.w / 2, -b.h / 2); g.restore();
      if (i) doc.addPage([PW, PH], horiz ? 'l' : 'p');
      doc.setFont('helvetica', 'bold'); doc.setFontSize(Math.max(8, 12 * u)); doc.setTextColor(30);
      doc.text('Página ' + d.p + ' · ' + d.pct.toFixed(1) + '% de cambio', mg, mg + 4.5 * u);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(Math.max(6, 8 * u)); doc.setTextColor(90);
      doc.text((C.nombreProyecto || '') + ' · ' + (slots.a.nombre || '') + '  |  ' + (slots.b.nombre || ''), mg, mg + 8.4 * u);
      const aw = PW - 2 * mg, ah = PH - mg - 11 * u - mg;
      const esc = Math.min(aw / cv.width, ah / cv.height), iw = cv.width * esc, ih = cv.height * esc;
      doc.addImage(cv.toDataURL('image/jpeg', .9), 'JPEG', mg + (aw - iw) / 2, mg + 10 * u, iw, ih, undefined, 'FAST');
      await new Promise(r => setTimeout(r, 0));
    }
    const ahora = new Date();
    doc.save(ahora.getFullYear() + '_' + p2(ahora.getMonth() + 1) + '_' + p2(ahora.getDate()) + '_Comparacion_cambios.pdf');
    estd.textContent = 'PDF de cambios generado (' + cambios.length + ' página(s)).';
  }
  if ($('#cmp-exportar-cambios')) $('#cmp-exportar-cambios').addEventListener('click', exportarCambios);
  if ($('#cmp-paginas')) $('#cmp-paginas').addEventListener('click', () => {
    const d = $('#cmp-paginas-dlg');
    if (d && d.showModal) d.showModal();
    if (!pags.datos.length) pintarPags();
  });
  if ($('#cmp-analizar-paginas')) $('#cmp-analizar-paginas').addEventListener('click', analizarPaginas);
  if ($('#cmp-paginas-cerrar')) $('#cmp-paginas-cerrar').addEventListener('click', () => { const d = $('#cmp-paginas-dlg'); if (d && d.open) d.close(); });

  // ===================================================================== informe
  function datosInforme() {
    const total = anot.shapes.length;
    const globos = anot.shapes.filter(s => s.tipo === 'globo');
    const abiertas = globos.filter(s => (s.estado || 'abierta') !== 'resuelta').length;
    const medidas = anot.shapes.filter(s => s.tipo === 'medir' || s.tipo === 'area');
    return { total, globos: globos.length, abiertas, resueltas: globos.length - abiertas, medidas };
  }
  function abrirInforme() {
    const d = datosInforme();
    const cont = $('#cmp-informe-cuerpo'); if (!cont) return;
    cont.innerHTML = '';
    const fila = (t, v) => { const p = document.createElement('p'); const b = document.createElement('strong'); b.textContent = t + ': '; p.append(b, document.createTextNode(v)); return p; };
    cont.append(fila('Documentos', (slots.a.nombre || 'A') + '  vs  ' + (slots.b.nombre || 'B')));
    if (est.modo === 'diff') cont.append(fila('Área con cambios', diff.pct.toFixed(2) + '% · ' + diff.regiones.length + ' zona(s)'));
    cont.append(fila('Anotaciones', d.total + ' · ' + d.globos + ' globo(s) · ' + d.abiertas + ' abierta(s) · ' + d.resueltas + ' resuelta(s)'));
    if (d.medidas.length) cont.append(fila('Medidas', d.medidas.map(s => s.tipo === 'medir' ? fmtLong(longitud(s)) : fmtArea(Math.abs(s.w * s.h))).join(' · ')));
    if (pags.datos.length) {
      const cambios = pags.datos.filter(x => x.pct > 0.2);
      cont.append(fila('Páginas', pags.datos.length + ' analizadas · ' + cambios.length + ' con cambios (' + (cambios.map(x => 'p.' + x.p).join(', ') || 'ninguna') + ')'));
    }
    const globos = anot.shapes.filter(s => s.tipo === 'globo');
    if (globos.length) {
      const h = document.createElement('h4'); h.textContent = 'Globos'; cont.appendChild(h);
      const ol = document.createElement('ol');
      globos.forEach(g => {
        const li = document.createElement('li');
        li.textContent = 'Globo ' + g.numero + ' · pág. ' + paginaDe(g) + ' · ' + ((g.estado || 'abierta') === 'resuelta' ? 'resuelta' : 'abierta') + (g.texto ? ': ' + g.texto : '') + (g.notas && g.notas.length ? ' (' + g.notas.length + ' nota(s))' : '');
        ol.appendChild(li);
      });
      cont.appendChild(ol);
    }
    if (anot.escala) cont.append(fila('Escala', '1 m = ' + Math.round(1 / anot.escala) + ' px'));
    const dlg = $('#cmp-informe-dlg'); if (dlg && dlg.showModal) dlg.showModal();
  }
  if ($('#cmp-informe')) $('#cmp-informe').addEventListener('click', abrirInforme);
  if ($('#cmp-informe-cerrar')) $('#cmp-informe-cerrar').addEventListener('click', () => { const d = $('#cmp-informe-dlg'); if (d && d.open) d.close(); });
  if ($('#cmp-informe-pdf')) $('#cmp-informe-pdf').addEventListener('click', async () => { const d = $('#cmp-informe-dlg'); if (d && d.open) d.close(); await exportarPdf(); });

  // ===================================================================== exportar a PDF
  const MODOS = { slider: 'Deslizador', fade: 'Fundido', diff: 'Diferencias' };
  function dibujarComposicion(g, x0, y0, W, H, que) {
    const a = slots.a, b = slots.b, v = $('#cmp-vista');
    if (que === 'ambos' && est.modo === 'diff' && diff.listo && diff.cv && diff.cv.width > 1) {
      g.save(); g.translate(-x0, -y0); g.drawImage(diff.cv, 0, 0, a.w, a.h); g.restore();
      return;
    }
    g.fillStyle = '#3a3f4b'; g.fillRect(0, 0, W, H);
    if (que !== 'b') { g.save(); g.translate(-x0, -y0); g.drawImage(a.canvas, 0, 0); g.restore(); }
    if (que === 'a') return;
    const sx = (est.pos * v.clientWidth - vista.x) / vista.k - x0, sy = (est.pos * v.clientHeight - vista.y) / vista.k - y0;
    const verSlider = que === 'ambos' && est.modo === 'slider';
    g.save();
    if (verSlider) { g.beginPath(); if (est.dir === 'v') g.rect(sx, 0, W * 4, H); else g.rect(0, sy, W, H * 4); g.clip(); }
    else if (que === 'ambos' && est.modo === 'fade') g.globalAlpha = $('#cmp-fade').value / 100;
    else if (que === 'ambos') g.globalCompositeOperation = 'difference';
    g.translate(-x0, -y0);
    g.translate(bT.x + b.w / 2, bT.y + b.h / 2); g.rotate(bT.rot * Math.PI / 180); g.scale(bT.s, bT.s);
    g.fillStyle = '#fff'; g.fillRect(-b.w / 2, -b.h / 2, b.w, b.h);
    g.drawImage(b.canvas, -b.w / 2, -b.h / 2);
    g.restore();
    if (verSlider) {
      g.save(); g.strokeStyle = '#fff'; g.lineWidth = Math.max(2, W / 600); g.shadowColor = 'rgba(0,0,0,.6)'; g.shadowBlur = 4;
      g.beginPath(); if (est.dir === 'v') { g.moveTo(sx, 0); g.lineTo(sx, H); } else { g.moveTo(0, sy); g.lineTo(W, sy); }
      g.stroke(); g.restore();
    }
  }
  function dibujarAnotaciones(g, x0, y0) {
    if (!anot.shapes.length) return;
    g.save(); g.translate(-x0, -y0);
    anot.shapes.forEach(sh => dibujarShape(g, sh, false));
    g.restore();
  }
  async function construirPdf() {
    if (!(slots.a.listo && slots.b.listo)) throw new Error('Faltan los documentos');
    if (!(window.jspdf && window.jspdf.jsPDF)) throw new Error('No se pudo cargar jsPDF');
    if (est.modo === 'diff' && !diff.listo) calcularDiff();
    let pw, ph;
    const fmt = $('#cmp-pdf-fmt').value;
    if (fmt === 'custom') { pw = (parseFloat($('#cmp-pdf-w').value) || 0) * 10; ph = (parseFloat($('#cmp-pdf-h').value) || 0) * 10; }
    else [pw, ph] = fmt.split('x').map(Number);
    if (!(pw >= 50 && ph >= 50 && pw <= 5000 && ph <= 5000)) throw new Error('Tamaño no válido (entre 5 y 500 cm por lado)');
    let x0 = 0, y0 = 0, W = slots.a.w, H = slots.a.h;
    const que = $('#cmp-pdf-que').value;
    if ($('#cmp-pdf-cont').value === 'vista') {
      const v = $('#cmp-vista'); x0 = -vista.x / vista.k; y0 = -vista.y / vista.k; W = v.clientWidth / vista.k; H = v.clientHeight / vista.k;
    }
    const f = Math.min(1, 4000 / Math.max(W, H));
    const cv = document.createElement('canvas'); cv.width = Math.max(1, Math.round(W * f)); cv.height = Math.max(1, Math.round(H * f));
    const g = cv.getContext('2d'); g.scale(f, f);
    dibujarComposicion(g, x0, y0, W, H, que);
    const conAnot = $('#cmp-pdf-anot') && $('#cmp-pdf-anot').checked && anot.ver;
    if (conAnot) dibujarAnotaciones(g, x0, y0);
    g.setTransform(1, 0, 0, 1, 0, 0);

    const ori = $('#cmp-pdf-ori').value, corto = Math.min(pw, ph), largo = Math.max(pw, ph);
    const horiz = ori === 'l' || (ori === 'auto' && W >= H);
    const PW = horiz ? largo : corto, PH = horiz ? corto : largo;
    const doc = new window.jspdf.jsPDF({ unit: 'mm', format: [PW, PH], orientation: horiz ? 'l' : 'p', compress: true });
    const u = Math.min(PW, PH) / 215.9;
    const mg = 10 * u, fs = v => Math.max(6, v * u);
    const ahora = new Date(), fecha = ahora.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
    doc.setFont('helvetica', 'bold'); doc.setFontSize(fs(14)); doc.setTextColor(30);
    doc.text(nombreActual ? 'Comparación: ' + nombreActual : 'Comparación de documentos', mg, mg + 5 * u);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(fs(9)); doc.setTextColor(80);
    const pagTxt = k => (slots[k].pdf ? ' (pág. ' + (slots[k].pagina || 1) + ')' : '');
    const modoTxt = que === 'a' ? 'Solo A' : que === 'b' ? 'Solo B' : MODOS[est.modo] + (est.modo === 'slider' ? (est.dir === 'v' ? ' vertical' : ' horizontal') + ' ' + Math.round(est.pos * 100) + '%' : est.modo === 'fade' ? ' ' + $('#cmp-fade').value + '%' : '');
    const d = datosInforme();
    const lin = [
      (C.nombreProyecto || '') + ' · ' + fecha + (C.usuario ? ' · ' + C.usuario : ''),
      (que === 'b' ? '' : 'A (base): ' + slots.a.nombre + pagTxt('a')) + (que === 'ambos' ? '   |   ' : '') + (que === 'a' ? '' : 'B (superpuesto): ' + slots.b.nombre + pagTxt('b')) + '   |   Modo: ' + modoTxt,
      'Anotaciones: ' + d.total + ' · ' + d.globos + ' globo(s) · ' + d.abiertas + ' abierta(s) · ' + d.resueltas + ' resuelta(s)' + (est.modo === 'diff' ? '   |   Cambios: ' + diff.pct.toFixed(2) + '% en ' + diff.regiones.length + ' zona(s)' : ''),
    ];
    let y = mg + 5 * u + 6 * u;
    lin.forEach(t => { doc.text(doc.splitTextToSize(t, PW - 2 * mg), mg, y); y += 4.6 * u * Math.max(1, doc.splitTextToSize(t, PW - 2 * mg).length); });
    y += 2 * u;
    const leyenda = conAnot ? anot.shapes.filter(s => (s.tipo === 'globo' && (s.texto || s.notas.length)) || s.tipo === 'medir' || s.tipo === 'area').map(s => {
      if (s.tipo === 'medir') return 'p.' + paginaDe(s) + ' · Medida: ' + fmtLong(longitud(s));
      if (s.tipo === 'area') return 'p.' + paginaDe(s) + ' · Área: ' + fmtArea(Math.abs(s.w * s.h));
      return s.numero + '. ' + (s.estado === 'resuelta' ? '[Resuelta] ' : '') + (s.texto || '') + (s.notas.length ? ' (' + s.notas.length + ' nota(s))' : '') + ' · p.' + paginaDe(s);
    }) : [];
    const lineaH = 4.2 * u, leyendaAlto = leyenda.length ? Math.min(110 * u, (leyenda.length + 1) * lineaH) : 0;
    const pie = 6 * u, dispW = PW - 2 * mg, dispH = PH - y - mg - pie - leyendaAlto;
    const esc = Math.min(dispW / cv.width, dispH / cv.height), iw = cv.width * esc, ih = cv.height * esc;
    const ix = mg + (dispW - iw) / 2;
    doc.addImage(cv.toDataURL('image/jpeg', 0.92), 'JPEG', ix, y, iw, ih, undefined, 'FAST');
    doc.setDrawColor(150); doc.setLineWidth(0.2 * u); doc.rect(ix, y, iw, ih);
    if (leyenda.length) {
      let ly = y + ih + 5 * u;
      doc.setFont('helvetica', 'bold'); doc.setFontSize(fs(9)); doc.setTextColor(60); doc.text('Anotaciones y medidas', mg, ly); ly += lineaH;
      doc.setFont('helvetica', 'normal'); doc.setFontSize(fs(8)); doc.setTextColor(80);
      leyenda.forEach(t => { const ls = doc.splitTextToSize(t, PW - 2 * mg); doc.text(ls, mg, ly); ly += lineaH * Math.max(1, ls.length); });
    }
    doc.setFontSize(fs(8)); doc.setTextColor(110);
    doc.text('Generado con Programa de Obra · ' + PW.toFixed(0) + ' × ' + PH.toFixed(0) + ' mm', mg, PH - mg);
    return doc;
  }
  async function exportarPdf() {
    const btn = $('#cmp-pdf-btn'), msg = $('#cmp-pdf-estado');
    if (!(slots.a.listo && slots.b.listo)) return;
    if (btn) btn.disabled = true; if (msg) msg.textContent = 'Generando…';
    await new Promise(r => setTimeout(r, 30));
    try {
      const doc = await construirPdf();
      const ahora = new Date();
      doc.save(ahora.getFullYear() + '_' + p2(ahora.getMonth() + 1) + '_' + p2(ahora.getDate()) + '_Comparacion.pdf');
      if (msg) msg.textContent = 'PDF generado';
    } catch (e) { if (msg) msg.textContent = 'No se pudo generar: ' + (e && e.message ? e.message : 'error'); }
    finally { if (btn) btn.disabled = false; }
  }
  if ($('#cmp-pdf-fmt')) $('#cmp-pdf-fmt').addEventListener('change', e => { $('#cmp-pdf-cust').hidden = e.target.value !== 'custom'; });
  if ($('#cmp-pdf-btn')) $('#cmp-pdf-btn').addEventListener('click', exportarPdf);
  window.addEventListener('resize', () => { if (!$('#cmp').hidden) aplicar(); });

  if (PUBLICO) {
    document.querySelectorAll('.cmp-anot-barra').forEach(e => { e.hidden = true; });
    if ($('#cmp-guardar')) $('#cmp-guardar').hidden = true;
  }
  aplicar();

  if (G) restaurarGuardado(G);
})();
