/* Comparador de documentos: dos imágenes o PDF (del proyecto o externos) superpuestos y comparables con un deslizador.
   Todo se dibuja en el navegador; los archivos externos no se suben. */
(function () {
  'use strict';
  const C = window.CMP_CONFIG || {};
  const $ = s => document.querySelector(s);
  const LADO_MAX = 3000;   // lado mayor (px) con el que se dibuja cada documento
  const EXT_IMG = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
  if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = C.worker;

  // slots: lienzo, tamaño, PDF abierto (si lo es) y datos para mostrar
  const slots = {
    a: { canvas: $('#cmp-ca'), w: 0, h: 0, pdf: null, nombre: '', listo: false, tok: 0 },
    b: { canvas: $('#cmp-cb'), w: 0, h: 0, pdf: null, nombre: '', listo: false, tok: 0 },
  };
  const vista = { x: 0, y: 0, k: 1 };
  const bT = { x: 0, y: 0, s: 1, rot: 0 };
  const est = { modo: 'slider', pos: 0.5, dir: 'v', moverB: false, encuadrado: false };

  const fuenteUI = k => document.querySelector('.cmp-fuente[data-slot="' + k + '"]');
  const estado = (k, txt) => { fuenteUI(k).querySelector('.cmp-estado').textContent = txt; };

  // ---------- carga ----------
  async function abrir(k, buffer, ext, nombre) {
    const s = slots[k], tok = ++s.tok, ui = fuenteUI(k), pag = ui.querySelector('.cmp-pag');
    estado(k, 'Cargando…');
    try {
      if (ext === 'pdf') {
        if (!window.pdfjsLib) throw new Error('No se pudo cargar el lector de PDF (revisa tu conexión)');
        const pdf = await pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
        if (tok !== s.tok) return;
        s.pdf = pdf;
        pag.hidden = false; ui.querySelector('.cmp-pagina').max = pdf.numPages; ui.querySelector('.cmp-pagina').value = 1;
        ui.querySelector('.cmp-total').textContent = 'de ' + pdf.numPages;
        await dibujarPdf(k, 1, tok);
      } else {
        const tipo = ext === 'jpg' ? 'jpeg' : ext;
        const bmp = await createImageBitmap(new Blob([buffer], { type: 'image/' + tipo }));
        if (tok !== s.tok) return;
        s.pdf = null; pag.hidden = true;
        const f = Math.min(1, LADO_MAX / Math.max(bmp.width, bmp.height));
        s.w = Math.round(bmp.width * f); s.h = Math.round(bmp.height * f);
        s.canvas.width = s.w; s.canvas.height = s.h;
        const g = s.canvas.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, s.w, s.h); g.drawImage(bmp, 0, 0, s.w, s.h);
      }
      s.nombre = nombre; s.listo = true;
      estado(k, nombre + ' · ' + s.w + ' × ' + s.h + ' px');
      listo();
    } catch (e) {
      if (tok === s.tok) { s.listo = false; estado(k, 'No se pudo abrir: ' + (e && e.message ? e.message : 'archivo no válido')); }
    }
  }
  async function dibujarPdf(k, n, tok) {
    const s = slots[k], page = await s.pdf.getPage(n), base = page.getViewport({ scale: 1 });
    const vp = page.getViewport({ scale: Math.min(4, LADO_MAX / Math.max(base.width, base.height)) });
    if (tok !== undefined && tok !== s.tok) return;
    s.w = Math.round(vp.width); s.h = Math.round(vp.height);
    s.canvas.width = s.w; s.canvas.height = s.h;
    const g = s.canvas.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, s.w, s.h);
    await page.render({ canvasContext: g, viewport: vp }).promise;
  }
  const extDe = nombre => (String(nombre).split('.').pop() || '').toLowerCase();

  document.querySelectorAll('.cmp-proy').forEach(sel => sel.addEventListener('change', async () => {
    const k = sel.dataset.slot, op = sel.selectedOptions[0];
    if (!sel.value) return;
    fuenteUI(k).querySelector('.cmp-ext').value = '';
    estado(k, 'Descargando…');
    try {
      const r = await fetch('/comparador/archivo?proyecto=' + C.proyecto + '&id=' + encodeURIComponent(sel.value), { credentials: 'same-origin' });
      if (!r.ok) throw new Error('el servidor respondió ' + r.status);
      abrir(k, await r.arrayBuffer(), op.dataset.ext, op.dataset.nombre);
    } catch (e) { estado(k, 'No se pudo descargar: ' + e.message); }
  }));
  document.querySelectorAll('.cmp-ext').forEach(inp => inp.addEventListener('change', async () => {
    const k = inp.dataset.slot, f = inp.files[0]; if (!f) return;
    const ext = extDe(f.name) || (f.type === 'application/pdf' ? 'pdf' : '');
    if (ext !== 'pdf' && !EXT_IMG.includes(ext)) { estado(k, 'Formato no permitido: usa PDF o imagen (png, jpg, webp, gif)'); inp.value = ''; return; }
    const sp = document.querySelector('.cmp-proy[data-slot="' + k + '"]'); if (sp) sp.value = '';
    abrir(k, await f.arrayBuffer(), ext, f.name);
  }));
  document.querySelectorAll('.cmp-pagina').forEach(inp => inp.addEventListener('change', async () => {
    const k = inp.dataset.slot, s = slots[k]; if (!s.pdf) return;
    const n = Math.min(s.pdf.numPages, Math.max(1, Math.round(+inp.value) || 1)); inp.value = n;
    estado(k, 'Cargando página…');
    await dibujarPdf(k, n, s.tok);
    estado(k, s.nombre + ' · pág. ' + n + ' · ' + s.w + ' × ' + s.h + ' px');
    aplicar();
  }));

  // ---------- panel ----------
  function listo() {
    if (!(slots.a.listo && slots.b.listo)) return;
    $('#cmp').hidden = false;
    if (!est.encuadrado) { ajustarB(); encuadrar(); est.encuadrado = true; } else aplicar();
  }

  function aplicar() {
    const a = slots.a, b = slots.b, ma = $('#cmp-mundo-a'), mb = $('#cmp-mundo-b');
    const t = `translate(${vista.x}px,${vista.y}px) scale(${vista.k})`;
    ma.style.transform = mb.style.transform = t;
    b.canvas.style.transform = `translate(${bT.x}px,${bT.y}px) rotate(${bT.rot}deg) scale(${bT.s})`;
    const capaB = $('#cmp-capa-b'), div = $('#cmp-div'), v = $('#cmp-vista');
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

  // ---------- eventos ----------
  document.querySelectorAll('button[data-modo]').forEach(b => b.addEventListener('click', () => { est.modo = b.dataset.modo; aplicar(); }));
  $('#cmp-dir').addEventListener('change', e => { est.dir = e.target.value; aplicar(); });
  $('#cmp-fade').addEventListener('input', aplicar);
  $('#cmp-mover').addEventListener('click', () => { est.moverB = !est.moverB; aplicar(); });
  $('#cmp-ajustar').addEventListener('click', ajustarB);
  $('#cmp-reset-b').addEventListener('click', () => { bT.x = bT.y = 0; bT.s = 1; bT.rot = 0; aplicar(); });
  $('#cmp-encuadrar').addEventListener('click', encuadrar);
  $('#cmp-intercambiar').addEventListener('click', () => {
    const A = slots.a, B = slots.b, ca = A.canvas, cb = B.canvas;
    const campos = ['w', 'h', 'pdf', 'nombre', 'listo'];
    campos.forEach(c => { const t = A[c]; A[c] = B[c]; B[c] = t; });
    const g = (c, o) => { const x = document.createElement('canvas'); x.width = o.width; x.height = o.height; x.getContext('2d').drawImage(o, 0, 0); return x; };
    const ta = g(0, ca), tb = g(0, cb);
    ca.width = tb.width; ca.height = tb.height; ca.getContext('2d').drawImage(tb, 0, 0);
    cb.width = ta.width; cb.height = ta.height; cb.getContext('2d').drawImage(ta, 0, 0);
    ['a', 'b'].forEach(k => { estado(k, slots[k].nombre ? slots[k].nombre + ' · ' + slots[k].w + ' × ' + slots[k].h + ' px' : 'Sin documento'); fuenteUI(k).querySelector('.cmp-pag').hidden = !slots[k].pdf; });
    ajustarB(); encuadrar();
  });
  const num = (id, fn) => $(id).addEventListener('change', e => { const v = parseFloat(e.target.value); if (Number.isFinite(v)) { fn(v); aplicar(); } else aplicar(); });
  num('#cmp-esc', v => { bT.s = Math.max(0.05, Math.min(10, v / 100)); });
  num('#cmp-rot', v => { bT.rot = Math.max(-180, Math.min(180, v)); });
  num('#cmp-bx', v => { bT.x = v; }); num('#cmp-by', v => { bT.y = v; });

  const vistaEl = $('#cmp-vista');
  vistaEl.addEventListener('wheel', e => {
    e.preventDefault();
    const r = vistaEl.getBoundingClientRect(), px = e.clientX - r.left, py = e.clientY - r.top;
    const k = Math.min(20, Math.max(0.02, vista.k * (e.deltaY < 0 ? 1.12 : 1 / 1.12)));
    vista.x = px - (px - vista.x) * k / vista.k; vista.y = py - (py - vista.y) * k / vista.k; vista.k = k; aplicar();
  }, { passive: false });
  let arr = null;
  vistaEl.addEventListener('pointerdown', e => {
    if (e.target.closest('.cmp-div')) return;
    arr = { x: e.clientX, y: e.clientY }; vistaEl.setPointerCapture(e.pointerId); vistaEl.classList.add('arrastrando'); vistaEl.focus();
  });
  vistaEl.addEventListener('pointermove', e => {
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

  // ---------- exportar a PDF (se arma en el navegador con jsPDF; nada se sube) ----------
  const MODOS = { slider: 'Deslizador', fade: 'Fundido', diff: 'Diferencias' };
  function dibujarComposicion(g, x0, y0, W, H, que) {
    const a = slots.a, b = slots.b, v = $('#cmp-vista');
    g.fillStyle = '#3a3f4b'; g.fillRect(0, 0, W, H);
    if (que !== 'b') { g.save(); g.translate(-x0, -y0); g.drawImage(a.canvas, 0, 0); g.restore(); }
    if (que === 'a') return;
    // posición del deslizador en coordenadas del documento (la misma que se ve en pantalla)
    const sx = (est.pos * v.clientWidth - vista.x) / vista.k - x0, sy = (est.pos * v.clientHeight - vista.y) / vista.k - y0;
    const verSlider = que === 'ambos' && est.modo === 'slider';
    g.save();
    if (verSlider) {
      g.beginPath();
      if (est.dir === 'v') g.rect(sx, 0, W * 4, H); else g.rect(0, sy, W, H * 4);
      g.clip();
    } else if (que === 'ambos' && est.modo === 'fade') g.globalAlpha = $('#cmp-fade').value / 100;
    else if (que === 'ambos') g.globalCompositeOperation = 'difference';
    g.translate(-x0, -y0);
    g.translate(bT.x + b.w / 2, bT.y + b.h / 2); g.rotate(bT.rot * Math.PI / 180); g.scale(bT.s, bT.s);
    g.fillStyle = '#fff'; g.fillRect(-b.w / 2, -b.h / 2, b.w, b.h);
    g.drawImage(b.canvas, -b.w / 2, -b.h / 2);
    g.restore();
    if (verSlider) {
      g.save(); g.strokeStyle = '#fff'; g.lineWidth = Math.max(2, W / 600); g.shadowColor = 'rgba(0,0,0,.6)'; g.shadowBlur = 4;
      g.beginPath();
      if (est.dir === 'v') { g.moveTo(sx, 0); g.lineTo(sx, H); } else { g.moveTo(0, sy); g.lineTo(W, sy); }
      g.stroke(); g.restore();
    }
  }
  async function exportarPdf() {
    const btn = $('#cmp-pdf-btn'), msg = $('#cmp-pdf-estado');
    if (!(slots.a.listo && slots.b.listo)) return;
    if (!(window.jspdf && window.jspdf.jsPDF)) { msg.textContent = 'No se pudo cargar jsPDF (revisa tu conexión)'; return; }
    let pw, ph;
    const fmt = $('#cmp-pdf-fmt').value;
    if (fmt === 'custom') { pw = (parseFloat($('#cmp-pdf-w').value) || 0) * 10; ph = (parseFloat($('#cmp-pdf-h').value) || 0) * 10; }
    else [pw, ph] = fmt.split('x').map(Number);
    if (!(pw >= 50 && ph >= 50 && pw <= 5000 && ph <= 5000)) { msg.textContent = 'Tamaño no válido (entre 5 y 500 cm por lado)'; return; }
    btn.disabled = true; msg.textContent = 'Generando…';
    await new Promise(r => setTimeout(r, 30));
    try {
      // región del mundo (px del documento A) que se exporta
      let x0 = 0, y0 = 0, W = slots.a.w, H = slots.a.h;
      const que = $('#cmp-pdf-que').value;
      if ($('#cmp-pdf-cont').value === 'vista') {
        const v = $('#cmp-vista'); x0 = -vista.x / vista.k; y0 = -vista.y / vista.k; W = v.clientWidth / vista.k; H = v.clientHeight / vista.k;
      }
      const f = Math.min(1, 4000 / Math.max(W, H));
      const cv = document.createElement('canvas'); cv.width = Math.max(1, Math.round(W * f)); cv.height = Math.max(1, Math.round(H * f));
      const g = cv.getContext('2d'); g.scale(f, f);
      dibujarComposicion(g, x0, y0, W, H, que);
      g.setTransform(1, 0, 0, 1, 0, 0);

      const ori = $('#cmp-pdf-ori').value, corto = Math.min(pw, ph), largo = Math.max(pw, ph);
      const horiz = ori === 'l' || (ori === 'auto' && W >= H);
      const PW = horiz ? largo : corto, PH = horiz ? corto : largo;
      const doc = new window.jspdf.jsPDF({ unit: 'mm', format: [PW, PH], orientation: horiz ? 'l' : 'p', compress: true });
      const u = Math.min(PW, PH) / 215.9;          // factor respecto a tamaño carta
      const mg = 10 * u, fs = v => Math.max(6, v * u);
      const ahora = new Date(), fecha = ahora.toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
      doc.setFont('helvetica', 'bold'); doc.setFontSize(fs(14)); doc.setTextColor(30);
      doc.text('Comparación de documentos', mg, mg + 5 * u);
      doc.setFont('helvetica', 'normal'); doc.setFontSize(fs(9)); doc.setTextColor(80);
      const pagTxt = k => (slots[k].pdf ? ' (pág. ' + (document.querySelector('.cmp-pagina[data-slot="' + k + '"]').value || 1) + ')' : '');
      const modoTxt = que === 'a' ? 'Solo A' : que === 'b' ? 'Solo B' : MODOS[est.modo] + (est.modo === 'slider' ? (est.dir === 'v' ? ' vertical' : ' horizontal') + ' ' + Math.round(est.pos * 100) + '%' : est.modo === 'fade' ? ' ' + $('#cmp-fade').value + '%' : '');
      const lin = [
        (C.nombreProyecto || '') + ' · ' + fecha + (C.usuario ? ' · ' + C.usuario : ''),
        (que === 'b' ? '' : 'A (base): ' + slots.a.nombre + pagTxt('a')) + (que === 'ambos' ? '   |   ' : '') + (que === 'a' ? '' : 'B (superpuesto): ' + slots.b.nombre + pagTxt('b')) + '   |   Modo: ' + modoTxt,
      ];
      let y = mg + 5 * u + 6 * u;
      lin.forEach(t => { doc.text(doc.splitTextToSize(t, PW - 2 * mg), mg, y); y += 4.6 * u * Math.max(1, doc.splitTextToSize(t, PW - 2 * mg).length); });
      y += 2 * u;
      const pie = 6 * u, dispW = PW - 2 * mg, dispH = PH - y - mg - pie;
      const esc = Math.min(dispW / cv.width, dispH / cv.height), iw = cv.width * esc, ih = cv.height * esc;
      const ix = mg + (dispW - iw) / 2;
      doc.addImage(cv.toDataURL('image/jpeg', 0.92), 'JPEG', ix, y, iw, ih, undefined, 'FAST');
      doc.setDrawColor(150); doc.setLineWidth(0.2 * u); doc.rect(ix, y, iw, ih);
      doc.setFontSize(fs(8)); doc.setTextColor(110);
      doc.text('Generado con Programa de Obra · ' + PW.toFixed(0) + ' × ' + PH.toFixed(0) + ' mm', mg, PH - mg);
      const p2 = n => String(n).padStart(2, '0');
      doc.save(ahora.getFullYear() + '_' + p2(ahora.getMonth() + 1) + '_' + p2(ahora.getDate()) + '_Comparacion.pdf');
      msg.textContent = 'PDF generado';
    } catch (e) { msg.textContent = 'No se pudo generar: ' + (e && e.message ? e.message : 'error'); }
    finally { btn.disabled = false; }
  }
  $('#cmp-pdf-fmt').addEventListener('change', e => { $('#cmp-pdf-cust').hidden = e.target.value !== 'custom'; });
  $('#cmp-pdf-btn').addEventListener('click', exportarPdf);
  window.addEventListener('resize', () => { if (!$('#cmp').hidden) aplicar(); });
  aplicar();
})();
