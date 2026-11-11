/* Anotador de planos y documentos: visor PDF/imagen, capa de anotaciones (formas, medidas, polígonos,
   áreas y volúmenes), imágenes, estampas inteligentes, firmas y exportación a PDF.
   La geometría y el dibujo de las formas viven en /vendor/anotaciones.js (compartido con el Comparador). */
(function () {
  'use strict';
  const C = window.ANT_CONFIG || {};
  const A = window.Anotaciones;
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const LADO_MAX = 3400;
  const EXT_IMG = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
  const TIPOS_MEDIDA = ['medir', 'area', 'poligono', 'volumen'];
  if (window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.min.js';

  const ant = {
    docId: 0, nombre: '', descripcion: '', fuenteTipo: '', fuenteId: null, fuenteNombre: '', ext: '',
    pdf: null, img: null, pagina: 1, paginas: 1, sizes: {}, cfg: { escalas: {}, unidad: 'm', capas: [] }, shapes: [],
    sel: -1, multi: [], herramienta: 'seleccion', color: '#e11d48', grosor: 5, relleno: false, opacidad: 1, estilo: 'solida',
    capas: [], capa: 'c1', simbolos: [],
    activo: true, ver: true, undo: [], redo: [], drag: null, editando: -1, k: 1, x: 0, y: 0,
    dirty: false, finalizado: false, soloLectura: false, imagenes: {}, portapapeles: [], filtro: 'todas', todasPaginas: false,
    polygon: null, pendiente: null, firmas: [], estampas: [], insertadas: [], miniaturaAnterior: '', cargando: false,
    draftFile: null, listaTokens: [],
  };

  const hojaEl = $('#ant-hoja'), anotEl = $('#ant-anot'), vistaEl = $('#ant-vista'), mundoEl = $('#ant-mundo');
  const hojaCtx = hojaEl ? hojaEl.getContext('2d') : null;
  const anotCtx = anotEl ? anotEl.getContext('2d') : null;
  const CALIDAD = 2;

  const clon = o => JSON.parse(JSON.stringify(o));
  const paginaActual = () => Math.max(1, parseInt(ant.pagina, 10) || 1);
  const paginaDe = sh => Math.max(1, parseInt(sh && sh.pagina, 10) || 1);
  const enPagina = sh => paginaDe(sh) === paginaActual();
  const seleccionada = i => i === ant.sel || ant.multi.includes(i);
  const tamPagina = n => ant.sizes[n] || ant.sizes[1] || { w: 1200, h: 800 };
  const cfgBase = () => {
    const w = tamPagina(paginaActual()).w || 1200;
    return { base: Math.max(.75, Math.min(3, w / 1200)), k: ant.k, mPorPx: ant.cfg.escalas[paginaActual()] || 0, imagenes: ant.imagenes, capas: ant.capas };
  };
  const capaDe = id => ant.capas.find(c => c.id === id) || null;
  const capaActiva = () => capaDe(ant.capa) || ant.capas[0] || null;
  const capaIndex = sh => Math.max(0, ant.capas.findIndex(c => c.id === (sh && (sh.capa || ant.capa))));
  const shapesOrdenados = n => ant.shapes.map((sh, i) => ({ sh, i })).filter(x => !n || paginaDe(x.sh) === n).sort((a, b) => capaIndex(a.sh) - capaIndex(b.sh) || a.i - b.i);
  function asegurarCapas() {
    if (!Array.isArray(ant.capas) || !ant.capas.length) ant.capas = [{ id: 'c1', nombre: 'Anotaciones', visible: true, bloqueada: false, opacidad: 100 }];
    ant.capas.forEach((c, i) => {
      if (!c.id) c.id = 'c' + (i + 1);
      if (!c.nombre) c.nombre = 'Capa ' + (i + 1);
      if (c.visible === undefined) c.visible = true;
      if (c.bloqueada === undefined) c.bloqueada = false;
      if (c.opacidad === undefined) c.opacidad = 100;
    });
    const primero = ant.capas[0].id;
    ant.shapes.forEach(sh => { if (!sh.capa || !capaDe(sh.capa)) sh.capa = primero; });
    if (!capaDe(ant.capa)) ant.capa = ant.capas[ant.capas.length - 1].id;
  }
  const aviso = (txt, tipo = 'error') => {
    const e = $('#ant-error'); if (!e) return;
    e.textContent = txt; e.hidden = !txt;
    e.classList.toggle('alerta-error', tipo === 'error'); e.classList.toggle('alerta-exito', tipo === 'ok');
    if (txt) clearTimeout(aviso._t), aviso._t = setTimeout(() => { e.hidden = true; }, 8000);
  };
  const estado = txt => { const e = $('#ant-estado'); if (e) e.textContent = txt; };
  const marcarSucio = () => { ant.dirty = true; const e = $('#ant-guardado'); if (e) e.textContent = 'Cambios sin guardar'; };
  const marcarLimpio = () => { ant.dirty = false; const e = $('#ant-guardado'); if (e) e.textContent = 'Guardado'; };
  const fechaISO = () => new Date().toISOString();
  const fechaCorta = v => { if (!v) return ''; const d = new Date(v); return isNaN(d) ? String(v).slice(0, 16) : d.toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); };
  const puntos = n => Math.round(n * 100) / 100;
  const fmtArea = px2 => A.fmtArea(px2, cfgBase());
  const fmtLong = px => A.fmtLong(px, cfgBase());
  const fmtVol = (a, p) => A.fmtVolumen(a, p, cfgBase());

  // ============================================================== fuente / documento
  function limpiarEstado() {
    ant.pdf = null; ant.img = null; ant.shapes = []; ant.sel = -1; ant.multi = []; ant.undo = []; ant.redo = [];
    ant.pagina = 1; ant.sizes = {}; ant.imagenes = {}; ant.cfg = { escalas: {}, unidad: 'm', capas: [] };
    ant.capas = [{ id: 'c1', nombre: 'Anotaciones', visible: true, bloqueada: false, opacidad: 100 }]; ant.capa = 'c1';
    ant.polygon = null; ant.pendiente = null; ant.finalizado = false; ant.soloLectura = false; ant.dirty = true;
    ant.insertadas = []; ant.firmas = []; ant.estampas = []; ant.draftFile = null; ant.miniaturaAnterior = '';
  }

  async function cargarBlob(blob, ext, nombre, meta = {}) {
    limpiarEstado();
    ant.ext = String(ext || '').toLowerCase();
    ant.fuenteNombre = nombre || 'Documento';
    ant.nombre = meta.nombre !== undefined ? meta.nombre : ant.fuenteNombre;
    ant.descripcion = meta.descripcion || '';
    ant.fuenteTipo = meta.fuenteTipo || ant.fuenteTipo;
    ant.fuenteId = meta.fuenteId || null;
    ant.docId = meta.docId || 0;
    ant.paginas = 1;
    if (ant.ext === 'pdf') {
      if (!window.pdfjsLib) throw new Error('No se pudo cargar el lector de PDF.');
      ant.pdf = await pdfjsLib.getDocument({ data: new Uint8Array(await blob.arrayBuffer()) }).promise;
      ant.paginas = ant.pdf.numPages;
    } else if (EXT_IMG.includes(ant.ext)) {
      const bmp = await createImageBitmap(blob);
      ant.img = bmp;
      const f = Math.min(1, LADO_MAX / Math.max(bmp.width, bmp.height));
      ant.sizes[1] = { w: Math.round(bmp.width * f), h: Math.round(bmp.height * f) };
      ant.paginas = 1;
    } else {
      throw new Error('Formato no compatible. Usa PDF, PNG, JPG, GIF o WEBP.');
    }
    $('#ant-app').hidden = false;
    $('#ant-vacia').hidden = true;
    const inicio = $('.ant-inicio'); if (inicio) inicio.hidden = true;
    setTimeout(() => { const a = $('#ant-app'); if (a) a.scrollIntoView({ block: 'start', behavior: 'smooth' }); }, 80);
    $('#ant-pag-total').textContent = 'de ' + ant.paginas;
    $('#ant-pag').max = ant.paginas;
    await irPagina(1, true);
    actualizarTodo();
  }

  async function abrirGuardado(d) {
    const r = await fetch(C.urls.fuente + '?proyecto=' + C.proyecto + '&doc=' + d.id, { credentials: 'same-origin' });
    if (!r.ok) throw new Error('No se pudo abrir el documento base.');
    await cargarBlob(await r.blob(), d.ext, d.nombre_archivo || d.nombre, { nombre: d.nombre, descripcion: d.descripcion, docId: d.id });
    ant.fuenteTipo = d.fuente_tipo; ant.fuenteId = d.fuente_id; ant.fuenteNombre = d.nombre_archivo;
    ant.cfg = d.config && d.config.escalas ? { escalas: d.config.escalas || {}, unidad: d.config.unidad || 'm', capas: d.config.capas || [] } : { escalas: {}, unidad: 'm', capas: [] };
    ant.capas = Array.isArray(d.config?.capas) ? d.config.capas : [];
    ant.shapes = (d.anotaciones && d.anotaciones.shapes) || [];
    asegurarCapas();
    const vistas = new Set();
    ant.shapes.forEach(sh => {
      if (sh.tipo === 'imagen' && sh.imagen && !vistas.has(sh.imagen)) {
        vistas.add(sh.imagen);
        ant.insertadas.push({ archivo: sh.imagen, url: '/anotador/imagen?proyecto=' + C.proyecto + '&archivo=' + sh.imagen, w: sh.w, h: sh.h });
      }
    });
    ant.finalizado = !!d.finalizado; ant.soloLectura = !!d.finalizado || d.puede_editar === false;
    if (d.config) { ant.cfg.firmado_por = d.config.firmado_por || ''; ant.cfg.firmado_fecha = d.config.firmado_fecha || ''; ant.cfg.firma_hash = d.config.firma_hash || ''; }
    ant.miniaturaAnterior = d.miniatura || '';
    marcarLimpio();
    aplicarBloqueo();
    cargarInsertables();
    redibujar(); pintarLista(); pintarMedidas(); actualizarEscalaInfo();
  }

  async function abrirNuevo() {
    const sel = $('#ant-proy');
    const file = $('#ant-archivo').files[0] || null;
    const nombre = $('#ant-nombre').value.trim();
    try {
      if (file) {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        ant.draftFile = file;
        await cargarBlob(file, ext, file.name, { nombre: nombre || file.name.replace(/\.[^.]+$/, ''), fuenteTipo: 'subido' });
        ant.draftFile = file;
        ant.fuenteTipo = 'subido'; ant.fuenteId = null;
        marcarSucio();
        if (!C.puedeGuardar) { ant.soloLectura = true; aplicarBloqueo(); }
        construirPaginas();
        cargarInsertables();
        return;
      }
      if (!sel || !sel.value) { aviso('Elige un documento del proyecto o sube un PDF/imagen.'); return; }
      const [tipo, id] = sel.value.split(':');
      const fuentes = $('#ant-fuentes'); if (fuentes) fuentes.classList.add('ant-fuentes-compacta');
      const r = await fetch(C.urls.fuente + '?proyecto=' + C.proyecto + '&tipo=' + encodeURIComponent(tipo) + '&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
      if (!r.ok) throw new Error('No se pudo abrir el documento del proyecto.');
      const opt = sel.selectedOptions[0];
      const ext = (opt.dataset.ext || 'pdf').toLowerCase();
      await cargarBlob(await r.blob(), ext, opt.dataset.nombre || opt.textContent, {
        nombre: nombre || (opt.dataset.nombre || opt.textContent).replace(/\s*\([A-Z0-9]+\)$/, ''), fuenteTipo: tipo, fuenteId: parseInt(id, 10),
      });
      ant.fuenteTipo = tipo; ant.fuenteId = parseInt(id, 10);
      marcarSucio();
      if (!C.puedeGuardar) { ant.soloLectura = true; aplicarBloqueo(); }
      construirPaginas();
      cargarInsertables();
    } catch (e) {
      aviso(e && e.message ? e.message : 'No se pudo abrir el documento.');
    }
  }

  async function irPagina(n, ajustar = false) {
    n = Math.max(1, Math.min(ant.paginas, n));
    ant.pagina = n;
    $('#ant-pag').value = n;
    ant.sel = -1; ant.multi = []; ant.polygon = null;
    estado('Cargando página…');
    try {
      if (ant.pdf) {
        const page = await ant.pdf.getPage(n);
        const base = page.getViewport({ scale: 1 });
        const vp = page.getViewport({ scale: Math.min(4, LADO_MAX / Math.max(base.width, base.height)) });
        const w = Math.round(vp.width), h = Math.round(vp.height);
        ant.sizes[n] = { w, h };
        hojaEl.width = w; hojaEl.height = h;
        hojaEl.style.width = w + 'px'; hojaEl.style.height = h + 'px';
        hojaCtx.setTransform(1, 0, 0, 1, 0, 0);
        hojaCtx.fillStyle = '#fff'; hojaCtx.fillRect(0, 0, w, h);
        await page.render({ canvasContext: hojaCtx, viewport: vp }).promise;
      } else if (ant.img) {
        const { w, h } = tamPagina(1);
        hojaEl.width = w; hojaEl.height = h;
        hojaEl.style.width = w + 'px'; hojaEl.style.height = h + 'px';
        hojaCtx.setTransform(1, 0, 0, 1, 0, 0);
        hojaCtx.fillStyle = '#fff'; hojaCtx.fillRect(0, 0, w, h);
        hojaCtx.drawImage(ant.img, 0, 0, w, h);
      }
      const t = tamPagina(n);
      anotEl.width = Math.max(1, Math.round(t.w * CALIDAD));
      anotEl.height = Math.max(1, Math.round(t.h * CALIDAD));
      anotEl.style.width = t.w + 'px'; anotEl.style.height = t.h + 'px';
      mundoEl.style.width = t.w + 'px'; mundoEl.style.height = t.h + 'px';
      if (ajustar || !ant.visto || ant.visto.w !== t.w) { ant.visto = { w: t.w }; ajustarVista(); }
      redibujar(); pintarLista(); pintarDetalle(); pintarProps(); pintarMedidas(); pintarPaginasActiva();
      estado('Página ' + n + ' de ' + ant.paginas + ' · ' + t.w + ' × ' + t.h + ' px');
      actualizarEscalaInfo();
      if (ant.herramienta === 'resaltado_texto' || ant.herramienta === 'subrayado' || ant.herramienta === 'tachado') montarTextLayer();
      else limpiarTextLayer();
    } catch (e) {
      estado('No se pudo dibujar la página');
      aviso('No se pudo dibujar la página: ' + (e && e.message ? e.message : 'error'));
    }
  }

  // ============================================================== vista
  function aplicar() {
    if (!mundoEl) return;
    mundoEl.style.transform = 'translate(' + ant.x + 'px,' + ant.y + 'px) scale(' + ant.k + ')';
    const z = $('#ant-zoom-val'); if (z) z.textContent = Math.round(ant.k * 100) + '%';
  }
  function ajustarVista() {
    const t = tamPagina(paginaActual()), vw = vistaEl.clientWidth, vh = vistaEl.clientHeight;
    if (!vw || !vh) return;
    ant.k = Math.max(.05, Math.min(4, Math.min((vw - 28) / t.w, (vh - 28) / t.h)));
    ant.x = (vw - t.w * ant.k) / 2; ant.y = (vh - t.h * ant.k) / 2;
    aplicar();
  }
  function zoomEn(f, cx, cy) {
    const r = vistaEl.getBoundingClientRect();
    const px = (cx == null ? r.width / 2 : cx - r.left), py = (cy == null ? r.height / 2 : cy - r.top);
    const k2 = Math.max(.08, Math.min(8, ant.k * f));
    if (k2 === ant.k) return;
    ant.x = px - (px - ant.x) * (k2 / ant.k);
    ant.y = py - (py - ant.y) * (k2 / ant.k);
    ant.k = k2; aplicar();
  }

  function punto(e) {
    const r = anotEl.getBoundingClientRect();
    const t = tamPagina(paginaActual());
    return { x: (e.clientX - r.left) / Math.max(.01, ant.k), y: (e.clientY - r.top) / Math.max(.01, ant.k), w: t.w, h: t.h };
  }

  // ============================================================== dibujo
  function cargarImagenShape(sh) {
    const n = sh && sh.imagen;
    if (!n || ant.imagenes[n]) return;
    const img = new Image();
    img.onload = () => redibujar();
    img.onerror = () => { ant.imagenes[n] = null; };
    img.src = '/anotador/imagen?proyecto=' + C.proyecto + '&archivo=' + n;
    ant.imagenes[n] = img;
  }
  /** Espera a que todas las imágenes/firmas/estampas del documento estén cargadas (para PDF y miniatura) */
  function esperarImagenes() {
    const nombres = [...new Set(ant.shapes.filter(s => s.imagen).map(s => s.imagen))];
    return Promise.all(nombres.map(n => new Promise(res => {
      let img = ant.imagenes[n];
      if (img && img.complete && img.naturalWidth) return res();
      if (!img) {
        img = new Image();
        ant.imagenes[n] = img;
        img.src = '/anotador/imagen?proyecto=' + C.proyecto + '&archivo=' + n;
      }
      const listo = () => res();
      img.addEventListener('load', listo, { once: true });
      img.addEventListener('error', listo, { once: true });
      if (img.complete) listo();
    })));
  }

  function redibujar() {
    if (!anotCtx) return;
    const t = tamPagina(paginaActual());
    anotCtx.setTransform(1, 0, 0, 1, 0, 0);
    anotCtx.clearRect(0, 0, anotEl.width, anotEl.height);
    if (!ant.ver) return;
    anotCtx.setTransform(CALIDAD, 0, 0, CALIDAD, 0, 0);
    const cfg = cfgBase();
    shapesOrdenados(paginaActual()).forEach(({ sh, i }) => {
      if (['imagen', 'simbolo', 'estampa', 'firma'].includes(sh.tipo)) cargarImagenShape(sh);
      A.dibujar(anotCtx, sh, seleccionada(i), cfg);
    });
    if (ant.polygon && ant.polygon.pts.length) {
      const tmp = Object.assign({}, ant.polygon);
      const lista = ant.polygon.mouse ? ant.polygon.pts.concat([ant.polygon.mouse]) : ant.polygon.pts;
      tmp.pts = lista.map(p => [p.x, p.y]);
      tmp.relleno = false;
      A.dibujar(anotCtx, tmp, false, cfg);
    }
  }

  const vacia = sh => A.vacia(sh);
  function snapshot() {
    ant.undo.push(JSON.stringify(ant.shapes.filter(enPagina)));
    if (ant.undo.length > 60) ant.undo.shift();
    ant.redo = [];
  }
  function deshacer() {
    if (ant.soloLectura || !ant.undo.length) return;
    ant.redo.push(JSON.stringify(ant.shapes.filter(enPagina)));
    const antes = JSON.parse(ant.undo.pop());
    ant.shapes = ant.shapes.filter(sh => !enPagina(sh)).concat(antes);
    ant.sel = -1; ant.multi = []; cerrarEditor(false); redibujar(); pintarLista(); pintarDetalle(); pintarProps(); pintarMedidas(); marcarSucio();
  }
  function rehacer() {
    if (ant.soloLectura || !ant.redo.length) return;
    ant.undo.push(JSON.stringify(ant.shapes.filter(enPagina)));
    const antes = JSON.parse(ant.redo.pop());
    ant.shapes = ant.shapes.filter(sh => !enPagina(sh)).concat(antes);
    ant.sel = -1; ant.multi = []; cerrarEditor(false); redibujar(); pintarLista(); pintarDetalle(); pintarProps(); pintarMedidas(); marcarSucio();
  }

  // ============================================================== selección
  function seleccionar(i, shift) {
    if (i < 0) { ant.sel = -1; ant.multi = []; pintarDetalle(); pintarProps(); redibujar(); return; }
    const sh = ant.shapes[i];
    const capa = sh ? capaDe(sh.capa) : null;
    if (capa && (capa.visible === false || capa.bloqueada || +capa.opacidad === 0)) { aviso('La capa de esta anotación está oculta, bloqueada o transparente.', 'ok'); return; }
    if (shift) {
      if (!ant.multi.includes(i)) ant.multi.push(i); else ant.multi = ant.multi.filter(x => x !== i);
      ant.sel = i;
    } else {
      ant.sel = i;
      ant.multi = sh && sh.grupo ? ant.shapes.map((s, j) => s.grupo === sh.grupo && enPagina(s) ? j : -1).filter(j => j >= 0) : [i];
    }
    redibujar(); pintarLista(); pintarDetalle(); pintarProps();
  }
  function hitIndex(p) {
    const cfg = cfgBase();
    const lista = shapesOrdenados(paginaActual()).slice().reverse();
    for (const { sh, i } of lista) {
      const c = capaDe(sh.capa);
      if (c && (c.visible === false || c.bloqueada || +c.opacidad === 0)) continue;
      if (A.hit(sh, p, cfg)) return i;
    }
    return -1;
  }
  function hitHandle(p) {
    if (ant.sel < 0) return null;
    const cfg = cfgBase(), t = 10 / ant.k;
    for (const h of A.handles(ant.shapes[ant.sel], cfg)) if (Math.abs(p.x - h.x) <= t && Math.abs(p.y - h.y) <= t) return h.k;
    return null;
  }
  function moverCopia(orig, dx, dy) { const s = clon(orig); A.mover(s, dx, dy); return s; }

  // ============================================================== interacción
  function iniciarArrastre(e, modo, i, extra = {}) {
    ant.drag = Object.assign({ modo, i, ini: punto(e), orig: clon(ant.shapes[i]) }, extra);
    anotEl.setPointerCapture(e.pointerId);
  }

  if (anotEl) {
    anotEl.addEventListener('pointerdown', e => {
      if (ant.soloLectura) { aviso('El documento está firmado y bloqueado.'); return; }
      if (!['seleccion', 'mano'].includes(ant.herramienta)) {
        const ca = capaActiva();
        if (ca && (ca.visible === false || ca.bloqueada || +ca.opacidad === 0)) { aviso('La capa activa está oculta, bloqueada o transparente.'); return; }
      }
      if (e.button === 1 || ant.herramienta === 'mano') return;   // el paneo lo maneja la vista
      if (e.button !== 0) return;
      if (gestos && gestos.activo && gestos.activo()) return;
      e.preventDefault(); e.stopPropagation();
      const p = punto(e);
      if (ant.editando >= 0) cerrarEditor(true);

      if (ant.herramienta === 'seleccion') {
        const h = hitHandle(p);
        if (h && ant.sel >= 0) { iniciarArrastre(e, 'resize', ant.sel, { h }); return; }
        const i = hitIndex(p);
        seleccionar(i, e.shiftKey);
        if (i >= 0) {
          const ix = ant.multi.length > 1 ? ant.multi : [i];
          ant.drag = { modo: 'move', i, ini: p, origs: ix.map(x => [x, clon(ant.shapes[x])]) };
          anotEl.setPointerCapture(e.pointerId);
        }
        return;
      }
      if (ant.herramienta === 'poligono' || ant.herramienta === 'volumen' || ant.herramienta === 'polilinea') { clicPoligono(p, e); return; }
      if (['imagen', 'simbolo', 'estampa', 'firma'].includes(ant.herramienta)) { colocarPendiente(p); return; }
      if (ant.herramienta === 'resaltado_texto' || ant.herramienta === 'subrayado' || ant.herramienta === 'tachado') {
        aviso('Selecciona texto en el documento; si el PDF no tiene texto, usa el resaltador rectangular.', 'ok'); return;
      }

      snapshot();
      const comun = { color: ant.color, grosor: ant.grosor, estilo: ant.estilo, opacidad: ant.opacidad, capa: ant.capa, relleno: ant.relleno, texto: '', autor: C.usuario || '', creado: fechaISO(), estado: 'abierta', notas: [], grupo: 0, pagina: paginaActual() };
      let sh;
      if (ant.herramienta === 'texto') sh = Object.assign(comun, { tipo: 'texto', x: p.x, y: p.y, fuente: 16 });
      else if (ant.herramienta === 'globo') sh = Object.assign(comun, { tipo: 'globo', x: p.x + 40, y: Math.max(20, p.y - 40), ax: p.x, ay: p.y, numero: siguienteNumero(), radio: 28 });
      else if (['flecha', 'linea', 'medir'].includes(ant.herramienta)) sh = Object.assign(comun, { tipo: ant.herramienta, x1: p.x, y1: p.y, x2: p.x, y2: p.y });
      else if (ant.herramienta === 'mano' || ant.herramienta === 'resaltador') sh = Object.assign(comun, { tipo: ant.herramienta, pts: [[p.x, p.y]] });
      else sh = Object.assign(comun, { tipo: ant.herramienta, x: p.x, y: p.y, w: 0, h: 0 });
      ant.shapes.push(sh); ant.sel = ant.shapes.length - 1; ant.multi = [ant.sel];
      marcarSucio(); redibujar(); pintarLista(); pintarDetalle();
      if (ant.herramienta === 'texto' || ant.herramienta === 'globo') { abrirEditor(ant.sel); return; }
      ant.drag = { modo: 'draw', i: ant.sel, ini: p, orig: clon(sh) };
      anotEl.setPointerCapture(e.pointerId);
    });

    anotEl.addEventListener('pointermove', e => {
      if (ant.polygon) { ant.polygon.mouse = { x: punto(e).x, y: punto(e).y }; redibujar(); }
      const d = ant.drag; if (!d) return;
      const p = punto(e);
      if (d.modo !== 'draw' && !d.snap) { d.snap = true; snapshot(); }
      if (d.modo === 'move') {
        const dx = p.x - d.ini.x, dy = p.y - d.ini.y;
        (d.origs || []).forEach(([i, orig]) => { const s = ant.shapes[i]; if (s) Object.assign(s, moverCopia(orig, dx, dy)); });
      } else if (d.modo === 'resize') {
        const o = d.orig, b = A.caja(o, null, cfgBase()), h = d.h;
        let sx = 1, sy = 1, fx = b.x, fy = b.y;
        if (h.includes('e')) { sx = Math.max(.02, (p.x - b.x) / Math.max(1, b.w)); fx = b.x; }
        if (h.includes('w')) { sx = Math.max(.02, (b.x + b.w - p.x) / Math.max(1, b.w)); fx = b.x + b.w; }
        if (h.includes('s')) { sy = Math.max(.02, (p.y - b.y) / Math.max(1, b.h)); fy = b.y; }
        if (h.includes('n')) { sy = Math.max(.02, (b.y + b.h - p.y) / Math.max(1, b.h)); fy = b.y + b.h; }
        const sh = clon(o); A.escalar(sh, fx, fy, sx, sy, cfgBase()); ant.shapes[d.i] = sh;
      } else if (d.modo === 'draw') {
        const sh = ant.shapes[d.i]; if (!sh) return;
        if (sh.tipo === 'mano' || sh.tipo === 'resaltador') sh.pts.push([p.x, p.y]);
        else if (['flecha', 'linea', 'medir'].includes(sh.tipo)) { sh.x2 = p.x; sh.y2 = p.y; }
        else { sh.x = Math.min(p.x, d.ini.x); sh.y = Math.min(p.y, d.ini.y); sh.w = Math.abs(p.x - d.ini.x); sh.h = Math.abs(p.y - d.ini.y); }
      }
      redibujar();
    });

    const fin = e => {
      const d = ant.drag;
      if (d) {
        const sh = ant.shapes[d.i];
        if (d.modo === 'draw' && sh) {
          if (vacia(sh) && (sh.w < 4 || sh.h < 4)) ant.shapes.splice(d.i, 1);
          else if (['mano', 'resaltador'].includes(sh.tipo) && (!sh.pts || sh.pts.length < 2)) ant.shapes.splice(d.i, 1);
          else if (sh.tipo === 'medir' && A.longitud(sh) < 4) ant.shapes.splice(d.i, 1);
        }
        ant.drag = null;
        if (ant.shapes.length <= d.i) ant.sel = -1;
        if (ant.sel >= 0) ant.sel = Math.min(ant.sel, ant.shapes.length - 1);
        marcarSucio(); pintarLista(); pintarDetalle(); pintarMedidas();
      } else if (ant.polygon && ant.polygon.mouse) {
        // el clic del polígono ya se procesó en pointerdown
      }
      redibujar();
    };
    anotEl.addEventListener('pointerup', fin); anotEl.addEventListener('pointercancel', fin);

    anotEl.addEventListener('dblclick', e => {
      if (ant.soloLectura) return;
      if (ant.polygon) { e.preventDefault(); cerrarPoligono(); return; }
      if (ant.herramienta !== 'seleccion') return;
      const i = hitIndex(punto(e));
      if (i >= 0 && ['texto', 'globo'].includes(ant.shapes[i].tipo)) { snapshot(); abrirEditor(i); }
    });
  }

  // paneo de la vista
  let pan = null;
  if (vistaEl) {
    vistaEl.addEventListener('pointerdown', e => {
      const cuerpo = $('#ant-cuerpo');
      if (cuerpo) { cuerpo.classList.remove('ant-movil-panel'); cuerpo.classList.remove('ant-movil-paginas'); }
      if (e.button !== 1 && !(ant.herramienta === 'mano' && e.button === 0)) return;
      e.preventDefault(); pan = { x: e.clientX, y: e.clientY, vx: ant.x, vy: ant.y };
      vistaEl.classList.add('ant-moviendo'); vistaEl.setPointerCapture(e.pointerId);
    });
    vistaEl.addEventListener('pointermove', e => {
      if (!pan) return;
      ant.x = pan.vx + (e.clientX - pan.x); ant.y = pan.vy + (e.clientY - pan.y); aplicar();
    });
    const finPan = () => { pan = null; vistaEl.classList.remove('ant-moviendo'); };
    vistaEl.addEventListener('pointerup', finPan); vistaEl.addEventListener('pointercancel', finPan);
    vistaEl.addEventListener('wheel', e => {
      if (!ant.pdf && !ant.img) return;
      e.preventDefault(); zoomEn(e.deltaY < 0 ? 1.12 : 1 / 1.12, e.clientX, e.clientY);
    }, { passive: false });
  }
  let gestos = null;
  try {
    if (window.GestosLienzo && vistaEl) {
      gestos = window.GestosLienzo.montar(vistaEl, {
        zoomActual: () => ant.k, min: .08, max: 8,
        alInicio: () => { },
        alMover: d => {
          if (d && Number.isFinite(d.z)) zoomEn(d.z / ant.k, d.cx, d.cy);
          if (d && (d.dx || d.dy)) { ant.x += d.dx; ant.y += d.dy; aplicar(); }
        },
        alFin: () => aplicar(),
        alDobleToque: (cx, cy) => { if (ant.k < 1.2) zoomEn(1.8, cx, cy); else ajustarVista(); },
      });
    }
  } catch (err) { gestos = null; }

  // ============================================================== polígonos
  function clicPoligono(p, e) {
    if (!ant.polygon) {
      const esLinea = ant.herramienta === 'polilinea';
      if (!esLinea && !ant.cfg.escalas[paginaActual()]) aviso('Calibra la página para que el área y el volumen salgan en unidades reales.', 'ok');
      snapshot();
      ant.polygon = {
        tipo: ant.herramienta, color: ant.color, grosor: ant.grosor, estilo: ant.estilo, opacidad: ant.opacidad, capa: ant.capa,
        relleno: !esLinea, pagina: paginaActual(),
        pts: [{ x: p.x, y: p.y }], mouse: { x: p.x, y: p.y }, autor: C.usuario || '', creado: fechaISO(),
        estado: 'abierta', notas: [], grupo: 0, cerrado: !esLinea, profundidad: 0, etiqueta: 'area',
      };
      aviso(esLinea ? 'Haz clic para agregar vértices; doble clic o Enter para terminar.' : 'Haz clic para agregar vértices; doble clic o Enter para cerrar.', 'ok');
      redibujar(); return;
    }
    const ult = ant.polygon.pts[ant.polygon.pts.length - 1];
    if (Math.hypot(p.x - ult.x, p.y - ult.y) < 3 / ant.k) return;
    ant.polygon.pts.push({ x: p.x, y: p.y });
    redibujar();
  }
  async function cerrarPoligono() {
    const poly = ant.polygon;
    if (!poly) return;
    ant.polygon = null;
    const esLinea = poly.tipo === 'polilinea';
    if (poly.pts.length < (esLinea ? 2 : 3)) { redibujar(); return; }
    const sh = {
      tipo: poly.tipo, color: poly.color, grosor: poly.grosor, estilo: poly.estilo || 'solida', opacidad: poly.opacidad == null ? 1 : poly.opacidad,
      capa: poly.capa || ant.capa, relleno: !esLinea, pagina: poly.pagina,
      pts: poly.pts.map(p => [puntos(p.x), puntos(p.y)]), autor: poly.autor, creado: poly.creado,
      estado: 'abierta', notas: [], grupo: 0, cerrado: !esLinea, profundidad: 0, etiqueta: 'area',
    };
    if (sh.tipo === 'volumen') {
      const prof = await pedirProfundidad();
      if (prof === null) { redibujar(); return; }
      sh.profundidad = prof;
    }
    ant.shapes.push(sh); ant.sel = ant.shapes.length - 1; ant.multi = [ant.sel];
    marcarSucio(); redibujar(); pintarLista(); pintarDetalle(); pintarMedidas(); pintarProps();
  }
  function pedirProfundidad() {
    return new Promise(resolve => {
      const dlg = $('#ant-dlg-profundidad'), inp = $('#ant-prof-valor');
      if (!dlg) { window.solicitarModal({ titulo: 'Profundidad del volumen', mensaje: 'El área del polígono se multiplica por esta profundidad.', etiqueta: 'Profundidad en metros', tipo: 'number', valor: '0.10', min: 0.01, paso: 0.01, aceptar: 'Calcular', icono: 'fa-cube' }).then(v => { const n = parseFloat(v); resolve(Number.isFinite(n) && n > 0 ? n : null); }); return; }
      inp.value = '0.10'; dlg.showModal(); inp.focus(); inp.select();
      const ok = () => { dlg.close(); const v = parseFloat(inp.value); resolve(Number.isFinite(v) && v > 0 ? v : null); };
      const cancel = () => { dlg.close(); resolve(null); };
      const bOk = $('#ant-prof-confirmar');
      const alOk = e => { e.preventDefault(); ok(); };
      const alCancel = () => { limpiar(); cancel(); };
      const limpiar = () => { bOk.removeEventListener('click', alOk); dlg.removeEventListener('cancel', alCancel); dlg.removeEventListener('close', alCancel); };
      bOk.addEventListener('click', alOk);
      dlg.addEventListener('cancel', alCancel);
      dlg.addEventListener('close', alCancel, { once: true });
    });
  }

  // ============================================================== colocar imágenes / firmas / estampas
  async function colocarPendiente(p) {
    const pend = ant.pendiente;
    if (!pend) {
      aviso('Elige primero una imagen, una firma o una estampa en el panel Insertar.');
      panelTab('insertar');
      return;
    }
    const t = tamPagina(paginaActual());
    let w = Math.min(pend.w || 240, t.w * .5), h = pend.h || w;
    if (pend.w && pend.h) h = w * pend.h / pend.w;
    else h = w * .5;
    const sh = {
      tipo: pend.tipo, color: ant.color, grosor: 2, estilo: 'solida', opacidad: ant.opacidad, capa: ant.capa, relleno: false, texto: '', autor: C.usuario || '', creado: fechaISO(),
      estado: 'abierta', notas: [], grupo: 0, pagina: paginaActual(),
      x: p.x - w / 2, y: p.y - h / 2, w, h, rot: 0, imagen: pend.imagen,
    };
    if (pend.tipo === 'firma') Object.assign(sh, { nombre: pend.nombre || C.usuario || '', cargo: pend.cargo || '', fecha: new Date().toLocaleDateString('es-MX'), bloqueada: false, firma_id: pend.firma_id || 0 });
    if (pend.tipo === 'estampa') sh.estampa_id = pend.estampa_id || 0;
    if (pend.tipo === 'simbolo') Object.assign(sh, { simbolo_id: pend.simbolo_id || 0, nombre: pend.nombre || '' });
    snapshot(); ant.shapes.push(sh); ant.sel = ant.shapes.length - 1; ant.multi = [ant.sel];
    ant.pendiente = null; ant.herramienta = 'seleccion';
    marcarSucio(); pintarHerramientas(); redibujar(); pintarLista(); pintarDetalle(); pintarProps();
  }

  function cargarInsertables() {
    fetch(C.urls.firmas + '?proyecto=' + C.proyecto, { credentials: 'same-origin' }).then(r => r.json()).then(d => {
      if (d.success) { ant.firmas = d.firmas || []; pintarFirmas(); }
    }).catch(() => { });
    cargarEstampas();
    pintarImagenes();
    cargarSimbolos();
  }

  async function cargarSimbolos() {
    try {
      const r = await fetch('/anotador/simbolos?proyecto=' + encodeURIComponent(C.proyecto), { credentials: 'same-origin' });
      const d = await r.json();
      ant.simbolos = d.success ? (d.simbolos || []) : [];
    } catch (e) { ant.simbolos = []; }
    pintarSimbolos();
  }
  function pintarSimbolos() {
    const l = $('#ant-simbolos-lista'); if (!l) return;
    const q = ($('#ant-sim-buscar')?.value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const norm = t => String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const lista = (ant.simbolos || []).filter(s => !q || norm(s.nombre + ' ' + s.cat).includes(q));
    l.innerHTML = '';
    if (!lista.length) { l.innerHTML = '<p class="text-sm text-muted">' + (q ? 'Ningún símbolo coincide.' : 'Aún no hay símbolos en la biblioteca del proyecto.') + '</p>'; return; }
    const grupos = {};
    lista.forEach(s => { (grupos[s.cat || 'Símbolos'] = grupos[s.cat || 'Símbolos'] || []).push(s); });
    Object.keys(grupos).sort((a, b) => a.localeCompare(b, 'es')).forEach(cat => {
      const h = document.createElement('h5'); h.className = 'ant-sim-grupo'; h.textContent = cat;
      l.appendChild(h);
      const g = document.createElement('div'); g.className = 'ant-sim-grid';
      grupos[cat].forEach(s => {
        const b = document.createElement('button'); b.type = 'button'; b.className = 'ant-sim-item'; b.title = 'Colocar ' + s.nombre;
        const img = document.createElement('img'); img.src = s.src; img.alt = '';
        const t = document.createElement('span'); t.textContent = s.nombre;
        b.append(img, t);
        b.addEventListener('click', () => colocarSimbolo(s));
        g.appendChild(b);
      });
      l.appendChild(g);
    });
  }

  function pintarImagenes() {
    const l = $('#ant-imagenes-lista'); if (!l) return;
    l.innerHTML = '';
    (ant.insertadas || []).forEach(it => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'ant-mini-img'; b.title = 'Colocar imagen';
      const img = document.createElement('img'); img.src = it.url; img.alt = '';
      b.appendChild(img);
      b.addEventListener('click', () => { ant.pendiente = { tipo: 'imagen', imagen: it.archivo, w: it.w, h: it.h }; ant.herramienta = 'imagen'; pintarHerramientas(); cerrarPanelMovil(); aviso('Haz clic en la página para colocar la imagen.', 'ok'); });
      l.appendChild(b);
    });
  }
  function pintarFirmas() {
    const l = $('#ant-firmas-lista'); if (!l) return;
    l.innerHTML = '';
    if (!(ant.firmas || []).length) { l.innerHTML = '<span class="text-sm text-muted">Aún no tienes firmas guardadas.</span>'; return; }
    ant.firmas.forEach(f => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'ant-firma-item'; b.title = 'Usar esta firma';
      const img = document.createElement('img'); img.src = f.src; img.alt = f.nombre;
      const s = document.createElement('small'); s.textContent = f.nombre;
      b.append(img, s);
      b.addEventListener('click', () => usarFirmaBiblioteca(f));
      l.appendChild(b);
    });
  }
  async function usarFirmaBiblioteca(f) {
    try {
      const r = await fetch(f.src, { credentials: 'same-origin' });
      if (!r.ok) throw new Error('No se pudo leer la firma.');
      const blob = await r.blob();
      const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.append('imagen', new File([blob], 'firma.png', { type: 'image/png' }));
      const rr = await fetch(C.urls.imagen, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await rr.json();
      if (!d.success) throw new Error(d.error || 'No se pudo copiar la firma.');
      ant.pendiente = { tipo: 'firma', imagen: d.archivo, w: d.w || 320, h: d.h || 120, nombre: f.nombre || C.usuario, cargo: '', firma_id: f.id };
      ant.herramienta = 'firma'; pintarHerramientas(); cerrarPanelMovil();
      aviso('Haz clic en la página para colocar la firma.', 'ok');
    } catch (e) { aviso(e.message || 'No se pudo usar la firma.'); }
  }

  function cargarEstampas() {
    fetch(C.urls.estampas + '?proyecto=' + C.proyecto, { credentials: 'same-origin' }).then(r => r.json()).then(d => {
      if (d.success) { ant.estampas = d.estampas || []; pintarEstampas(); }
    }).catch(() => { });
  }
  function pintarEstampas() {
    const l = $('#ant-estampas-lista'); if (!l) return;
    l.innerHTML = '';
    if (!(ant.estampas || []).length) { l.innerHTML = '<span class="text-sm text-muted">No hay estampas guardadas en el proyecto. Créalas en Símbolos › Estampas inteligentes.</span>'; return; }
    ant.estampas.forEach(e => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'ant-estampa-item'; b.title = 'Insertar estampa';
      const img = document.createElement('img'); img.loading = 'lazy'; img.src = e.preview || '/anotador/estampa?proyecto=' + C.proyecto + '&id=' + e.id; img.alt = e.nombre;
      const s = document.createElement('span'); s.textContent = e.nombre;
      b.append(img, s);
      b.addEventListener('click', async () => {
        try {
          const r = await fetch('/anotador/estampa?proyecto=' + C.proyecto + '&id=' + e.id, { credentials: 'same-origin' });
          if (!r.ok) { const d = await r.json().catch(() => ({})); throw new Error(d.error || 'No se pudo preparar la estampa.'); }
          const blob = await r.blob();
          const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.append('imagen', new File([blob], 'estampa.png', { type: 'image/png' }));
          const rr = await fetch(C.urls.imagen, { method: 'POST', body: fd, credentials: 'same-origin' });
          const d = await rr.json();
          if (!d.success) throw new Error(d.error || 'No se pudo copiar la estampa.');
          ant.pendiente = { tipo: 'estampa', imagen: d.archivo, w: d.w || e.w || 320, h: d.h || e.h || 200, estampa_id: e.id };
          ant.herramienta = 'estampa'; pintarHerramientas(); cerrarPanelMovil();
          aviso('Haz clic en la página para colocar la estampa.', 'ok');
        } catch (err) { aviso(err.message || 'No se pudo preparar la estampa.'); }
      });
      l.appendChild(b);
    });
  }

  // ============================================================== editor de texto
  const editor = $('#ant-txt');
  function abrirEditor(i) {
    const sh = ant.shapes[i]; if (!sh || !editor) return;
    ant.editando = i; ant.sel = i; ant.multi = [i];
    editor.value = sh.texto || '';
    const r = anotEl.getBoundingClientRect();
    const x = r.left + (sh.x + (sh.tipo === 'globo' ? 50 : 0)) * ant.k;
    const y = r.top + (sh.y + (sh.tipo === 'globo' ? 30 : 10)) * ant.k + 16;
    editor.style.left = Math.max(8, Math.min(window.innerWidth - 280, x)) + 'px';
    editor.style.top = Math.max(8, Math.min(window.innerHeight - 130, y)) + 'px';
    editor.style.width = Math.min(340, Math.max(200, (sh.w || 220) * ant.k)) + 'px';
    editor.hidden = false;
    setTimeout(() => { editor.focus(); editor.select(); }, 0);
    redibujar();
  }
  function cerrarEditor(guardar = true) {
    if (ant.editando < 0) return;
    const i = ant.editando, sh = ant.shapes[i]; ant.editando = -1;
    if (sh && guardar) {
      sh.texto = editor.value.trim();
      if (sh.tipo === 'texto' && sh.texto === '') { ant.shapes.splice(i, 1); ant.sel = -1; }
    }
    editor.hidden = true;
    marcarSucio(); redibujar(); pintarLista(); pintarDetalle();
  }
  if (editor) {
    editor.addEventListener('keydown', e => {
      if (e.key === 'Escape') { e.preventDefault(); cerrarEditor(false); }
      else if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); cerrarEditor(true); }
    });
    editor.addEventListener('blur', () => cerrarEditor(true));
  }

  // ============================================================== panel
  function panelTab(t) {
    $$('.pn-tab[data-ptab]').forEach(b => {
      const on = b.dataset.ptab === t;
      b.classList.toggle('activo', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    $$('.ant-panel-cont').forEach(p => { p.hidden = p.dataset.panel !== t; });
    const cont = $('.ant-panel-cont[data-panel="' + t + '"]'); if (cont) cont.scrollTop = 0;
    const sub = $('#ant-panel-sub'); if (sub) sub.textContent = { lista: 'Anotaciones', props: 'Propiedades', capas: 'Capas', medidas: 'Medidas', insertar: 'Insertar' }[t] || '';
  }
  function pintarHerramientas() {
    $$('[data-tool]').forEach(b => {
      const a = b.dataset.tool === ant.herramienta;
      b.classList.toggle('activo', a);
      b.setAttribute('aria-pressed', a ? 'true' : 'false');
      if (b.classList.contains('btn')) {
        b.classList.toggle('btn-primary', a);
        b.classList.toggle('btn-ghost', !a);
      }
    });
  }
  function icono(sh) {
    return { globo: 'fa-comment-dots', texto: 'fa-font', resaltador: 'fa-highlighter', resaltado: 'fa-fill-drip', flecha: 'fa-arrow-right', linea: 'fa-minus', polilinea: 'fa-bezier-curve', rect: 'fa-square', elipse: 'fa-circle', nube: 'fa-cloud', mano: 'fa-pen', medir: 'fa-ruler', area: 'fa-vector-square', poligono: 'fa-draw-polygon', volumen: 'fa-cube', imagen: 'fa-image', simbolo: 'fa-shapes', estampa: 'fa-stamp', firma: 'fa-signature', resaltado_texto: 'fa-text-height', subrayado: 'fa-underline', tachado: 'fa-strikethrough' }[sh.tipo] || 'fa-pen';
  }
  function pintarLista() {
    const ol = $('#ant-lista-anot'); if (!ol) return;
    ol.innerHTML = '';
    const ix = ant.shapes.map((s, i) => i).filter(i => (ant.todasPaginas || enPagina(ant.shapes[i])) && (ant.filtro === 'todas' || (ant.shapes[i].estado || 'abierta') === (ant.filtro === 'abiertas' ? 'abierta' : 'resuelta')));
    ix.forEach(i => {
      const sh = ant.shapes[i];
      const li = document.createElement('li');
      const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'ant-item' + (seleccionada(i) ? ' sel' : '');
      const ico = document.createElement('i'); ico.className = 'fa-solid ' + icono(sh);
      const sp = document.createElement('span');
      let txt = A.titulo(sh, cfgBase());
      if (ant.todasPaginas && paginaDe(sh) !== paginaActual()) txt = 'p. ' + paginaDe(sh) + ' · ' + txt;
      sp.textContent = String(txt || '').slice(0, 100);
      btn.append(ico, sp);
      btn.addEventListener('click', () => { seleccionar(i, false); centrarEn(i); cerrarPanelMovil(); });
      const acc = document.createElement('span'); acc.className = 'ant-item-acc';
      if (['globo', 'texto'].includes(sh.tipo)) {
        const st = document.createElement('button'); st.type = 'button'; st.className = 'ant-item-del'; st.title = (sh.estado === 'resuelta' ? 'Reabrir' : 'Resolver');
        st.innerHTML = '<i class="fa-solid ' + (sh.estado === 'resuelta' ? 'fa-circle-check' : 'fa-circle') + '"></i>';
        st.addEventListener('click', e => { e.stopPropagation(); snapshot(); sh.estado = sh.estado === 'resuelta' ? 'abierta' : 'resuelta'; marcarSucio(); redibujar(); pintarLista(); });
        acc.appendChild(st);
      }
      const del = document.createElement('button'); del.type = 'button'; del.className = 'ant-item-del'; del.title = 'Eliminar';
      del.innerHTML = '<i class="fa-solid fa-xmark"></i>';
      del.addEventListener('click', e => { e.stopPropagation(); snapshot(); ant.shapes.splice(i, 1); ant.sel = -1; ant.multi = []; marcarSucio(); redibujar(); pintarLista(); pintarDetalle(); pintarMedidas(); });
      acc.appendChild(del);
      li.append(btn, acc);
      ol.appendChild(li);
    });
    const vacio = $('#ant-lista-vacia'); if (vacio) vacio.hidden = ix.length > 0;
    const c = $('#ant-cuenta'); if (c) c.textContent = String(ant.shapes.filter(enPagina).length);
  }
  function centrarEn(i) {
    const sh = ant.shapes[i]; if (!sh || !vistaEl.clientWidth) return;
    const b = A.caja(sh, null, cfgBase());
    ant.x = vistaEl.clientWidth / 2 - (b.x + b.w / 2) * ant.k;
    ant.y = vistaEl.clientHeight / 2 - (b.y + b.h / 2) * ant.k;
    aplicar();
  }
  function pintarDetalle() {
    const p = $('#ant-detalle'); if (!p) return;
    const sh = ant.shapes[ant.sel];
    if (!sh) { p.hidden = true; return; }
    p.hidden = false;
    $('#ant-det-titulo').textContent = A.titulo(sh, cfgBase());
    const meta = [];
    if (sh.autor) meta.push(sh.autor);
    if (sh.creado) meta.push(fechaCorta(sh.creado));
    meta.push('pág. ' + paginaDe(sh));
    if (sh.tipo === 'medir') meta.push(fmtLong(A.longitud(sh)));
    if (sh.tipo === 'poligono') meta.push(fmtArea(A.areaPoligono(sh.pts || [])) + ' · ' + fmtLong(A.perimetroPoligono(sh.pts || [])));
    if (sh.tipo === 'volumen') meta.push(fmtArea(A.areaPoligono(sh.pts || [])) + ' · ' + fmtVol(A.areaPoligono(sh.pts || []), sh.profundidad));
    if (sh.estado === 'resuelta') meta.push('resuelta');
    $('#ant-det-meta').textContent = meta.join(' · ');
    const est = $('#ant-det-estado');
    est.hidden = !['globo', 'texto'].includes(sh.tipo);
    est.innerHTML = sh.estado === 'resuelta' ? '<i class="fa-solid fa-rotate-left"></i> Reabrir' : '<i class="fa-solid fa-circle-check"></i> Resolver';
    est.disabled = ant.soloLectura;
    const gb = $('#ant-det-grupo');
    const n = sh.grupo ? ant.shapes.filter(s => s.grupo === sh.grupo).length : (ant.multi.length || 1);
    gb.innerHTML = sh.grupo ? '<i class="fa-solid fa-object-ungroup"></i> Desagrupar' : '<i class="fa-solid fa-object-group"></i> Agrupar (' + n + ')';
    gb.hidden = !sh.grupo && n < 2; gb.disabled = ant.soloLectura;
    const notas = $('#ant-det-notas'); notas.innerHTML = '';
    (sh.notas || []).forEach(no => {
      const d = document.createElement('div'); d.className = 'ant-nota';
      d.innerHTML = '<div class="ant-nota-cab"><strong></strong><small class="text-muted"></small></div><p></p>';
      d.querySelector('strong').textContent = no.autor || '—';
      d.querySelector('small').textContent = fechaCorta(no.fecha);
      d.querySelector('p').textContent = no.texto;
      notas.appendChild(d);
    });
    if (!(sh.notas || []).length) { const v = document.createElement('p'); v.className = 'text-sm text-muted'; v.textContent = 'Sin notas todavía.'; notas.appendChild(v); }
    $('#ant-nota-form').hidden = ant.soloLectura;
    ['#ant-det-punch', '#ant-det-rfi'].forEach(s => { const b = $(s); if (b) b.disabled = ant.soloLectura || !C.puedeGuardar; });
  }
  function pintarProps() {
    const sh = ant.shapes[ant.sel];
    $('#ant-props-vacio').hidden = !!sh;
    $('#ant-props-form').hidden = !sh;
    if (!sh) return;
    $('#ant-prop-texto').value = sh.texto || '';
    $('#ant-prop-color').value = /^#[0-9a-f]{6}$/i.test(sh.color || '') ? sh.color : '#e11d48';
    $('#ant-prop-grosor').value = sh.grosor || 5;
    $('#ant-prop-relleno').checked = !!sh.relleno;
    $('#ant-prop-estilo').value = sh.estilo || 'solida';
    $('#ant-prop-opacidad').value = Math.round((sh.opacidad == null ? 1 : sh.opacidad) * 100);
    const selCapa = $('#ant-prop-capa');
    if (selCapa) {
      selCapa.replaceChildren(...ant.capas.map(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.nombre; return o; }));
      selCapa.value = sh.capa || ant.capa;
    }
    $('#ant-prop-profundidad-campo').hidden = sh.tipo !== 'volumen';
    $('#ant-prop-profundidad').value = sh.profundidad || 0;
    $('#ant-prop-etiqueta-campo').hidden = sh.tipo !== 'poligono';
    $('#ant-prop-etiqueta').value = sh.etiqueta || 'area';
    const esFirma = sh.tipo === 'firma';
    $('#ant-prop-firma-campos').hidden = !esFirma;
    if (esFirma) { $('#ant-prop-nombre').value = sh.nombre || ''; $('#ant-prop-cargo').value = sh.cargo || ''; }
    const esImg = ['imagen', 'simbolo', 'estampa', 'firma'].includes(sh.tipo);
    $('#ant-prop-imagen-campos').hidden = !esImg;
    if (esImg) $('#ant-prop-rot').value = sh.rot || 0;
    $$('#ant-props-form input, #ant-props-form textarea, #ant-props-form select, #ant-prop-aplicar').forEach(el => { el.disabled = ant.soloLectura; });
  }
  function pintarCapas() {
    const lista = $('#ant-capas-lista'); if (!lista) return;
    lista.innerHTML = '';
    ant.capas.forEach((c, i) => {
      const fila = document.createElement('div');
      fila.className = 'ant-capa' + (c.id === ant.capa ? ' on' : '') + (c.visible === false ? ' oculta' : '') + (c.bloqueada ? ' bloqueada' : '');
      fila.dataset.id = c.id;
      const n = ant.shapes.filter(sh => (sh.capa || ant.capas[0].id) === c.id).length;
      const ojo = document.createElement('button'); ojo.type = 'button'; ojo.className = 'btn btn-xs btn-ghost'; ojo.dataset.op = 'ver'; ojo.disabled = ant.soloLectura; ojo.title = c.visible === false ? 'Mostrar capa' : 'Ocultar capa'; ojo.innerHTML = '<i class="fa-solid ' + (c.visible === false ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
      const candado = document.createElement('button'); candado.type = 'button'; candado.className = 'btn btn-xs btn-ghost'; candado.dataset.op = 'bloq'; candado.disabled = ant.soloLectura; candado.title = c.bloqueada ? 'Desbloquear capa' : 'Bloquear capa'; candado.innerHTML = '<i class="fa-solid ' + (c.bloqueada ? 'fa-lock' : 'fa-lock-open') + '"></i>';
      const nombre = document.createElement('input'); nombre.type = 'text'; nombre.className = 'ant-capa-nombre'; nombre.value = c.nombre; nombre.maxLength = 60; nombre.dataset.op = 'nombre'; nombre.disabled = ant.soloLectura; nombre.setAttribute('aria-label', 'Nombre de la capa');
      const meta = document.createElement('small'); meta.className = 'text-muted'; meta.textContent = n + ' anot.';
      const op = document.createElement('input'); op.type = 'range'; op.min = 0; op.max = 100; op.value = c.opacidad == null ? 100 : c.opacidad; op.className = 'ant-capa-op'; op.dataset.op = 'opacidad'; op.disabled = ant.soloLectura; op.title = 'Opacidad de la capa'; op.setAttribute('aria-label', 'Opacidad de la capa');
      const subir = document.createElement('button'); subir.type = 'button'; subir.className = 'btn btn-xs btn-ghost'; subir.dataset.op = 'subir'; subir.title = 'Subir'; subir.innerHTML = '<i class="fa-solid fa-chevron-up"></i>'; subir.disabled = ant.soloLectura || i === 0;
      const bajar = document.createElement('button'); bajar.type = 'button'; bajar.className = 'btn btn-xs btn-ghost'; bajar.dataset.op = 'bajar'; bajar.title = 'Bajar'; bajar.innerHTML = '<i class="fa-solid fa-chevron-down"></i>'; bajar.disabled = ant.soloLectura || i === ant.capas.length - 1;
      const borrar = document.createElement('button'); borrar.type = 'button'; borrar.className = 'btn btn-xs btn-ghost'; borrar.dataset.op = 'borrar'; borrar.title = 'Eliminar capa (mueve sus anotaciones a la primera)'; borrar.innerHTML = '<i class="fa-solid fa-trash"></i>'; borrar.disabled = ant.soloLectura || ant.capas.length < 2;
      const acc = document.createElement('span'); acc.className = 'ant-capa-acc'; acc.append(ojo, candado, subir, bajar, borrar);
      fila.append(acc, nombre, op, meta);
      lista.appendChild(fila);
    });
    const cuenta = $('#ant-capas-cuenta'); if (cuenta) cuenta.textContent = ant.capas.length;
    const sel = $('#ant-capa-activa');
    if (sel) {
      sel.replaceChildren(...ant.capas.map(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.nombre; return o; }));
      sel.value = ant.capa;
    }
  }

  function nuevaCapa() {
    snapshot();
    const id = 'c' + Date.now().toString(36);
    ant.capas.push({ id, nombre: 'Capa ' + (ant.capas.length + 1), visible: true, bloqueada: false, opacidad: 100 });
    ant.capa = id;
    marcarSucio(); pintarCapas(); pintarProps();
  }

  function pintarMedidas() {
    const tbody = $('#ant-medidas-tabla tbody'); if (!tbody) return;
    tbody.innerHTML = '';
    let areaTotal = 0, volTotal = 0, hay = false;
    ant.shapes.forEach(sh => {
      if (!TIPOS_MEDIDA.includes(sh.tipo)) return;
      const capa = capaDe(sh.capa);
      if (capa && (capa.visible === false || +capa.opacidad === 0)) return;
      hay = true;
      const tr = document.createElement('tr');
      const c = v => { const td = document.createElement('td'); td.textContent = v; tr.appendChild(td); };
      c('p. ' + paginaDe(sh));
      c({ medir: 'Distancia', area: 'Área', poligono: 'Polígono', volumen: 'Volumen' }[sh.tipo]);
      if (sh.tipo === 'medir') c(fmtLong(A.longitud(sh)));
      else c(fmtArea(A.areaPoligono(sh.pts || []) || Math.abs(sh.w * sh.h)));
      c(sh.tipo === 'poligono' || sh.tipo === 'volumen' ? fmtLong(A.perimetroPoligono(sh.pts || [])) : '—');
      c(sh.tipo === 'volumen' ? (sh.profundidad || 0).toFixed(2) + ' m' : '—');
      c(sh.tipo === 'volumen' ? fmtVol(A.areaPoligono(sh.pts || []), sh.profundidad) : '—');
      if (sh.tipo === 'area') areaTotal += Math.abs(sh.w * sh.h) * Math.pow(ant.cfg.escalas[paginaDe(sh)] || 0, 2);
      else if (sh.tipo === 'poligono' || sh.tipo === 'volumen') areaTotal += A.areaPoligono(sh.pts || []) * Math.pow(ant.cfg.escalas[paginaDe(sh)] || 0, 2);
      if (sh.tipo === 'volumen') volTotal += A.areaPoligono(sh.pts || []) * Math.pow(ant.cfg.escalas[paginaDe(sh)] || 0, 2) * (sh.profundidad || 0);
      tbody.appendChild(tr);
    });
    $('#ant-medidas-vacio').hidden = hay;
    $('#ant-total-area').textContent = hay && areaTotal ? areaTotal.toFixed(2) + ' m²' : '—';
    $('#ant-total-vol').textContent = hay && volTotal ? volTotal.toFixed(3) + ' m³' : '—';
  }
  function actualizarEscalaInfo() {
    const e = ant.cfg.escalas[paginaActual()] || 0;
    const txt = e > 0 ? '1 m = ' + Math.round(1 / e) + ' px' : 'Sin escala';
    ['#ant-escala', '#ant-escala-pie'].forEach(s => { const el = $(s); if (el) el.textContent = txt; });
    const m = $('#ant-medidas-vacio'); if (m && e > 0) m.textContent = 'La página está calibrada. Dibuja líneas, áreas o polígonos para medir.';
  }
  function actualizarTodo() {
    asegurarCapas(); pintarHerramientas(); pintarLista(); pintarDetalle(); pintarProps(); pintarCapas(); pintarMedidas(); pintarPaginasActiva(); actualizarEscalaInfo(); aplicar();
    $('#ant-panel-sub').textContent = 'Anotaciones';
  }
  function pintarPaginasActiva() {
    $$('.ant-pag-mini').forEach(b => b.classList.toggle('activa', parseInt(b.dataset.pagina, 10) === paginaActual()));
  }

  // ============================================================== guardar / versiones
  async function miniatura() {
    await esperarImagenes();
    const t = tamPagina(paginaActual());
    const c = document.createElement('canvas');
    const f = Math.min(1, 640 / Math.max(t.w, t.h));
    c.width = Math.max(1, Math.round(t.w * f)); c.height = Math.max(1, Math.round(t.h * f));
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
    g.drawImage(hojaEl, 0, 0, c.width, c.height);
    g.save(); g.scale(c.width / t.w, c.height / t.h);
    const cfg = { base: Math.max(.75, Math.min(3, t.w / 1200)), k: 1, mPorPx: 0, imagenes: ant.imagenes, capas: ant.capas };
    shapesOrdenados(paginaActual()).forEach(({ sh }) => A.dibujar(g, sh, false, cfg));
    g.restore();
    return c.toDataURL('image/jpeg', .8);
  }
  function abrirGuardar() {
    if (!C.puedeGuardar) { aviso('No tienes permiso para guardar en este proyecto.'); return; }
    if (!ant.pdf && !ant.img) { aviso('Abre un documento primero.'); return; }
    $('#ant-g-nombre').value = ant.nombre || ant.fuenteNombre || '';
    $('#ant-g-desc').value = ant.descripcion || '';
    $('#ant-dlg-guardar').showModal();
  }
  async function guardar() {
    if (!C.puedeGuardar) { aviso('No tienes permiso para guardar.'); return false; }
    const nombre = ($('#ant-g-nombre').value || ant.nombre || ant.fuenteNombre || '').trim();
    if (!nombre) { aviso('Escribe un nombre para el documento.'); return false; }
    const cfg = { escalas: ant.cfg.escalas || {}, unidad: ant.cfg.unidad || 'm', capas: ant.capas };
    const an = { version: 2, ancho: tamPagina(paginaActual()).w, alto: tamPagina(paginaActual()).h, escalas: cfg.escalas, unidad: cfg.unidad, shapes: ant.shapes };
    const fd = new FormData();
    fd.set('proyecto', C.proyecto); if (ant.docId) fd.set('id', ant.docId);
    fd.set('nombre', nombre); fd.set('descripcion', $('#ant-g-desc').value.trim());
    fd.set('paginas', ant.paginas); fd.set('config', JSON.stringify(cfg)); fd.set('anotaciones', JSON.stringify(an));
    fd.set('miniatura', await miniatura());
    if (!ant.docId && ant.draftFile) fd.append('documento', ant.draftFile, ant.draftFile.name);
    if (!ant.docId) { fd.set('fuente_tipo', ant.fuenteTipo || 'subido'); fd.set('fuente_id', ant.fuenteId || ''); }
    const b = $('#ant-g-confirmar'); b.disabled = true;
    estado('Guardando…');
    try {
      const r = await fetch(C.urls.guardar, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'No se pudo guardar.');
      ant.docId = d.id; ant.nombre = nombre; ant.descripcion = $('#ant-g-desc').value.trim(); ant.draftFile = null;
      marcarLimpio(); $('#ant-dlg-guardar').close();
      const u = new URL(window.location.href); u.searchParams.set('proyecto', C.proyecto); u.searchParams.set('id', d.id); history.replaceState({}, '', u);
      const badge = $('#ant-badge-estado'); if (badge) badge.querySelector('span').textContent = nombre;
      aviso('Documento guardado.', 'ok'); estado('Guardado');
      return true;
    } catch (e) { aviso(e.message || 'No se pudo guardar.'); estado('No se pudo guardar'); return false; }
    finally { b.disabled = false; }
  }
  async function abrirVersiones() {
    if (!ant.docId) { aviso('Guarda el documento para ver su historial.'); return; }
    const l = $('#ant-versiones-lista'); l.innerHTML = '<p class="text-sm text-muted">Cargando…</p>';
    $('#ant-dlg-versiones').showModal();
    try {
      const r = await fetch(C.urls.versiones + '?proyecto=' + C.proyecto + '&id=' + ant.docId, { credentials: 'same-origin' });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'No se pudo leer el historial.');
      l.innerHTML = '';
      if (!d.versiones.length) { l.innerHTML = '<p class="text-sm text-muted">Todavía no hay versiones anteriores (se crean al volver a guardar).</p>'; return; }
      d.versiones.forEach(v => {
        const f = document.createElement('div'); f.className = 'ant-version';
        f.innerHTML = '<span><strong></strong><br><small class="text-muted"></small></span>';
        f.querySelector('strong').textContent = v.nombre || 'Versión';
        f.querySelector('small').textContent = fechaCorta(v.creado) + ' · ' + (v.autor || '—') + ' · ' + v.formas + ' anot.';
        const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-xs btn-ghost'; b.innerHTML = '<i class="fa-solid fa-rotate-left"></i> Restaurar';
        b.addEventListener('click', async () => {
          if (!await window.confirmarModal({ titulo: '¿Restaurar esta versión?', mensaje: 'Antes se guarda una copia del estado actual.', aceptar: 'Restaurar', icono: 'fa-clock-rotate-left' })) return;
          const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.set('id', ant.docId); fd.set('version', v.id);
          const rr = await fetch(C.urls.restaurar, { method: 'POST', body: fd, credentials: 'same-origin' });
          const dd = await rr.json();
          if (!dd.success) { aviso(dd.error || 'No se pudo restaurar.'); return; }
          $('#ant-dlg-versiones').close();
          aviso('Versión restaurada. Recargando…', 'ok');
          setTimeout(() => location.reload(), 500);
        });
        f.appendChild(b); l.appendChild(f);
      });
    } catch (e) { l.innerHTML = '<p class="text-sm text-muted"></p>'; l.querySelector('p').textContent = e.message || 'No se pudo leer el historial.'; }
  }

  // ============================================================== inserción de imágenes y firmas
  async function subirImagen(file, tipo = 'imagen', extra = {}) {
    if (!file) return null;
    const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.append('imagen', file, file.name);
    estado('Subiendo imagen…');
    try {
      const r = await fetch(C.urls.imagen, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'No se pudo subir la imagen.');
      const item = { archivo: d.archivo, url: d.url || ('/anotador/imagen?proyecto=' + C.proyecto + '&archivo=' + d.archivo), w: d.w, h: d.h };
      if (tipo === 'imagen') ant.insertadas.unshift(item);
      ant.pendiente = Object.assign({ tipo, imagen: d.archivo, w: d.w, h: d.h }, extra);
      ant.herramienta = tipo; pintarHerramientas(); pintarImagenes(); cerrarPanelMovil();
      aviso(tipo === 'simbolo' ? 'Haz clic en la página para colocar el símbolo.' : 'Haz clic en la página para colocar la imagen.', 'ok');
      estado(tipo === 'simbolo' ? 'Símbolo listo para colocar' : 'Imagen lista para colocar');
      const inp = $('#ant-imagen-file'); if (inp) inp.value = '';
      return item;
    } catch (e) { aviso(e.message || 'No se pudo subir la imagen.'); return null; }
  }

  /** Convierte un símbolo (incluido SVG) en PNG para copiarlo al documento del Anotador */
  async function simboloAPng(url) {
    const r = await fetch(url, { credentials: 'same-origin' });
    if (!r.ok) throw new Error('No se pudo leer el símbolo.');
    const blob = await r.blob();
    if (!/svg/i.test(blob.type)) return blob;
    const txt = await blob.text();
    const img = new Image();
    await new Promise((ok, mal) => {
      img.onload = ok; img.onerror = mal;
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(txt);
    });
    const w = img.naturalWidth || 512, h = img.naturalHeight || 512;
    const lado = Math.min(1024, Math.max(w, h));
    const cv = document.createElement('canvas');
    cv.width = Math.max(1, Math.round(w * lado / Math.max(w, h)));
    cv.height = Math.max(1, Math.round(h * lado / Math.max(w, h)));
    cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
    return await new Promise((ok, mal) => cv.toBlob(b => b ? ok(b) : mal(new Error('No se pudo convertir el símbolo.')), 'image/png'));
  }
  async function colocarSimbolo(sim) {
    try {
      estado('Preparando símbolo…');
      const blob = await simboloAPng(sim.src);
      const f = new File([blob], 'simbolo.png', { type: blob.type || 'image/png' });
      const sid = sim.material_id || (sim.ref && /^s:\d+$/.test(sim.ref) ? +sim.ref.slice(2) : (Number.isFinite(+sim.id) ? +sim.id : 0));
      await subirImagen(f, 'simbolo', { simbolo_id: sid, nombre: sim.nombre || '' });
    } catch (e) { aviso(e.message || 'No se pudo preparar el símbolo.'); }
  }

  // firma: lienzo
  let firma = { modo: 'dibujar', trazos: [], dibujando: false };
  function cerrarPanelMovil() { const c = $('#ant-cuerpo'); if (c) c.classList.remove('ant-movil-panel'); }
  function modoFirma(modo) {
    firma.modo = modo === 'escribir' ? 'escribir' : 'dibujar';
    $$('#ant-dlg-firma [data-ftab]').forEach(x => {
      const on = x.dataset.ftab === firma.modo;
      x.classList.toggle('activo', on);
      x.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    const dib = $('#ant-firma-dibujar'), esc = $('#ant-firma-escribir');
    if (dib) dib.hidden = firma.modo !== 'dibujar';
    if (esc) esc.hidden = firma.modo !== 'escribir';
    pintarFirmaVista();
  }
  function iniciarFirma() {
    const cv = $('#ant-firma-canvas');
    if (!cv) return;
    const ctx = cv.getContext('2d');
    ctx.clearRect(0, 0, cv.width, cv.height);
    firma.trazos = []; firma.dibujando = false;
    const p = e => { const r = cv.getBoundingClientRect(); return { x: (e.clientX - r.left) * cv.width / r.width, y: (e.clientY - r.top) * cv.height / r.height }; };
    cv.onpointerdown = e => {
      if (firma.modo !== 'dibujar') return;
      e.preventDefault(); e.stopPropagation();
      firma.dibujando = true; firma.trazos.push([p(e)]);
      try { cv.setPointerCapture(e.pointerId); } catch (err) { }
      ctx.fillStyle = '#0f172a'; ctx.beginPath(); ctx.arc(p(e).x, p(e).y, 1.6, 0, Math.PI * 2); ctx.fill();
    };
    cv.onpointermove = e => {
      if (!firma.dibujando) return;
      e.preventDefault();
      const q = p(e); const t = firma.trazos[firma.trazos.length - 1];
      if (!t || !t.length) return;
      t.push(q);
      ctx.strokeStyle = '#0f172a'; ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
      ctx.beginPath(); ctx.moveTo(t[t.length - 2].x, t[t.length - 2].y); ctx.lineTo(q.x, q.y); ctx.stroke();
    };
    const fin = e => { if (e && e.pointerId != null && cv.hasPointerCapture && cv.hasPointerCapture(e.pointerId)) { try { cv.releasePointerCapture(e.pointerId); } catch (err) { } } firma.dibujando = false; };
    cv.onpointerup = fin; cv.onpointercancel = fin; cv.onpointerleave = fin;
  }
  function fuenteFirma(g, txt, base, ancho) {
    const fam = $('#ant-firma-fuente') ? $('#ant-firma-fuente').value : 'cursive';
    let f = base;
    g.font = f + 'px ' + fam;
    while (g.measureText(txt).width > ancho && f > 16) { f -= 2; g.font = f + 'px ' + fam; }
    return f;
  }
  function pintarFirmaVista() {
    const cv = $('#ant-firma-vista'); if (!cv) return;
    const ctx = cv.getContext('2d'); ctx.clearRect(0, 0, cv.width, cv.height);
    const txt = ($('#ant-firma-texto') ? $('#ant-firma-texto').value : '').trim(); if (!txt) return;
    ctx.fillStyle = '#0f172a'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    fuenteFirma(ctx, txt, 52, cv.width - 40);
    ctx.fillText(txt, cv.width / 2, cv.height / 2);
  }
  /** Recorta los márgenes transparentes para que la firma se coloque ajustada */
  function recortarPng(cv) {
    try {
      const datos = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height).data;
      let x1 = cv.width, y1 = cv.height, x2 = -1, y2 = -1;
      for (let y = 0; y < cv.height; y++) {
        for (let x = 0; x < cv.width; x++) {
          if (datos[(y * cv.width + x) * 4 + 3] > 6) {
            if (x < x1) x1 = x; if (x > x2) x2 = x;
            if (y < y1) y1 = y; if (y > y2) y2 = y;
          }
        }
      }
      if (x2 < 0) return '';
      const pad = 10;
      x1 = Math.max(0, x1 - pad); y1 = Math.max(0, y1 - pad);
      x2 = Math.min(cv.width - 1, x2 + pad); y2 = Math.min(cv.height - 1, y2 + pad);
      const out = document.createElement('canvas');
      out.width = x2 - x1 + 1; out.height = y2 - y1 + 1;
      out.getContext('2d').drawImage(cv, x1, y1, out.width, out.height, 0, 0, out.width, out.height);
      return out.toDataURL('image/png');
    } catch (e) {
      return cv.toDataURL('image/png');
    }
  }
  function firmaPng() {
    if (firma.modo === 'escribir') {
      const txt = $('#ant-firma-texto').value.trim();
      if (!txt) return '';
      const cv = document.createElement('canvas'); cv.width = 560; cv.height = 200;
      const g = cv.getContext('2d');
      g.fillStyle = '#0f172a'; g.textAlign = 'center'; g.textBaseline = 'middle';
      fuenteFirma(g, txt, 56, cv.width - 40);
      g.fillText(txt, cv.width / 2, cv.height / 2);
      return recortarPng(cv);
    }
    if (!firma.trazos.length) return '';
    const cv = document.createElement('canvas'); cv.width = 560; cv.height = 200;
    const g = cv.getContext('2d');
    g.strokeStyle = '#0f172a'; g.lineWidth = 3.5; g.lineCap = 'round'; g.lineJoin = 'round';
    firma.trazos.forEach(t => { if (t.length < 2) return; g.beginPath(); g.moveTo(t[0].x, t[0].y); t.slice(1).forEach(p => g.lineTo(p.x, p.y)); g.stroke(); });
    if (!firma.trazos.some(t => t.length > 1)) return '';
    return recortarPng(cv);
  }
  function errorFirma(txt) {
    const e = $('#ant-firma-error');
    if (!e) { aviso(txt); return; }
    e.textContent = txt; e.hidden = !txt;
  }
  function abrirDialogoFirma() {
    if (!C.puedeGuardar) { aviso('No tienes permiso para insertar firmas en este proyecto.'); return; }
    if (!$('#ant-firma-canvas')) return;
    $('#ant-firma-nombre').value = C.usuario || '';
    $('#ant-firma-cargo').value = '';
    $('#ant-firma-texto').value = '';
    errorFirma('');
    modoFirma('dibujar');
    iniciarFirma();
    const dlg = $('#ant-dlg-firma');
    if (dlg && !dlg.open) dlg.showModal();
  }
  async function guardarFirma(soloUsar = false) {
    if (!C.puedeGuardar) { errorFirma('No tienes permiso para insertar firmas en este proyecto.'); return; }
    const png = firmaPng();
    if (!png) { errorFirma(firma.modo === 'escribir' ? 'Escribe tu nombre para generar la firma.' : 'Dibuja tu firma primero.'); return; }
    const nombre = $('#ant-firma-nombre').value.trim() || C.usuario || 'Mi firma';
    const cargo = $('#ant-firma-cargo').value.trim();
    errorFirma('');
    const cerrar = () => { const d = $('#ant-dlg-firma'); if (d && d.open) d.close(); };
    if (soloUsar) {
      try {
        const r = await fetch(png); const blob = await r.blob();
        const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.append('imagen', new File([blob], 'firma.png', { type: 'image/png' }));
        const rr = await fetch(C.urls.imagen, { method: 'POST', body: fd, credentials: 'same-origin' });
        const d = await rr.json();
        if (!d.success) throw new Error(d.error || 'No se pudo preparar la firma.');
        ant.pendiente = { tipo: 'firma', imagen: d.archivo, w: d.w, h: d.h, nombre, cargo };
        ant.herramienta = 'firma'; pintarHerramientas(); cerrar(); cerrarPanelMovil();
        aviso('Haz clic en la página para colocar la firma.', 'ok');
      } catch (e) { errorFirma(e.message || 'No se pudo preparar la firma.'); }
      return;
    }
    const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.set('nombre', nombre); fd.set('png', png);
    try {
      const r = await fetch(C.urls.firma, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'No se pudo guardar la firma.');
      ant.pendiente = { tipo: 'firma', imagen: d.archivo, w: d.w || 320, h: d.h || 120, nombre, cargo, firma_id: d.biblioteca ? d.biblioteca.id : 0 };
      ant.herramienta = 'firma'; pintarHerramientas(); cerrar(); cerrarPanelMovil();
      if (d.biblioteca) { ant.firmas = (ant.firmas || []).filter(f => f.id !== d.biblioteca.id); ant.firmas.unshift(d.biblioteca); pintarFirmas(); }
      aviso('Firma guardada. Haz clic en la página para colocarla.', 'ok');
    } catch (e) { errorFirma(e.message || 'No se pudo guardar la firma.'); }
  }

  // ============================================================== firmar (finalizar)
  async function finalizarFirma() {
    if (!ant.docId) { aviso('Guarda el documento antes de finalizarlo.'); return; }
    if (ant.finalizado) { aviso('Ya está firmado y bloqueado.'); return; }
    const firmas = ant.shapes.filter(s => s.tipo === 'firma');
    if (!firmas.length) { aviso('Coloca al menos una firma en la página.'); panelTab('insertar'); return; }
    const ok = await window.confirmarModal({ titulo: '¿Finalizar la firma?', mensaje: 'Se guarda el documento, se bloquea la edición y se descarga el PDF firmado. Después solo el administrador puede reabrirlo.', aceptar: 'Firmar y descargar', icono: 'fa-signature' });
    if (!ok) return;
    $('#ant-g-nombre').value = ant.nombre || ant.fuenteNombre || '';
    $('#ant-g-desc').value = ant.descripcion || '';
    const guardado = await guardar();
    if (!guardado || !ant.docId) return;
    const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.set('id', ant.docId);
    try {
      const r = await fetch(C.urls.firmar, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'No se pudo finalizar la firma.');
      ant.finalizado = true; ant.soloLectura = true;
      ant.cfg.firmado_por = d.firmado || C.usuario; ant.cfg.firmado_fecha = d.fecha || new Date().toISOString(); ant.cfg.firma_hash = d.hash || '';
      aplicarBloqueo();
      aviso('Documento firmado y bloqueado. Se descarga el PDF.', 'ok');
      await exportarPdf(true);
    } catch (e) { aviso(e.message || 'No se pudo finalizar la firma.'); }
  }
  function aplicarBloqueo() {
    const bloqueado = ant.soloLectura;
    $$('[data-tool], #ant-undo, #ant-redo, #ant-borrar, #ant-prop-aplicar, #ant-guardar, #ant-firmar-btn, #ant-imagen-subir, #ant-imagen-file, #ant-firma-nueva, #ant-calibrar, #ant-medidas-csv, #ant-estilo, #ant-opacidad, #ant-capa-activa, #ant-capa-nueva, #ant-capas-lista input, #ant-capas-lista button').forEach(b => { b.disabled = bloqueado; });
    $('#ant-finalizado').hidden = !ant.finalizado;
    $('#ant-reabrir').hidden = !(ant.finalizado && C.admin);
    document.body.classList.toggle('ant-solo-lectura', bloqueado);
    const e = $('#ant-guardado'); if (e && bloqueado) e.textContent = 'Solo lectura';
  }
  async function reabrir() {
    const ok = await window.confirmarModal({ titulo: '¿Reabrir el documento?', mensaje: 'Se quita el bloqueo de firma para poder corregirlo. El registro de firmas se conserva.', aceptar: 'Reabrir', icono: 'fa-lock-open' });
    if (!ok) return;
    const fd = new FormData(); fd.set('proyecto', C.proyecto); fd.set('id', ant.docId);
    const r = await fetch(C.urls.reabrir, { method: 'POST', body: fd, credentials: 'same-origin' });
    const d = await r.json();
    if (!d.success) { aviso(d.error || 'No se pudo reabrir.'); return; }
    ant.finalizado = false; ant.soloLectura = false; aplicarBloqueo(); aviso('Documento reabierto.', 'ok');
  }

  // ============================================================== exportar PDF
  async function renderParaPdf(n) {
    const page = { canvas: document.createElement('canvas'), wPt: 612, hPt: 792 };
    if (ant.pdf) {
      const p = await ant.pdf.getPage(n);
      const base = p.getViewport({ scale: 1 });
      const vp = p.getViewport({ scale: Math.min(4, LADO_MAX / Math.max(base.width, base.height)) });
      page.wPt = base.width; page.hPt = base.height;
      page.canvas.width = Math.round(vp.width); page.canvas.height = Math.round(vp.height);
      const g = page.canvas.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, page.canvas.width, page.canvas.height);
      await p.render({ canvasContext: g, viewport: vp }).promise;
    } else {
      const t = tamPagina(1);
      page.canvas.width = t.w; page.canvas.height = t.h;
      page.wPt = t.w * 72 / 96; page.hPt = t.h * 72 / 96;
      const g = page.canvas.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, t.w, t.h); g.drawImage(ant.img, 0, 0, t.w, t.h);
    }
    const g = page.canvas.getContext('2d');
    const cfg = { base: Math.max(.75, Math.min(3, page.canvas.width / 1200)), k: 1, mPorPx: ant.cfg.escalas[n] || 0, imagenes: ant.imagenes, capas: ant.capas };
    shapesOrdenados(n).forEach(({ sh }) => { cargarImagenShape(sh); A.dibujar(g, sh, false, cfg); });
    return page;
  }
  function slug(t) { return (t || 'documento').replace(/[^\p{L}\p{N}]+/gu, '-').replace(/^-|-$/g, '').slice(0, 80) || 'documento'; }
  async function exportarPdf(desdeFirma = false) {
    if (!(window.jspdf && window.jspdf.jsPDF)) { aviso('No se pudo cargar el generador de PDF.'); return; }
    if (!ant.pdf && !ant.img) { aviso('Abre un documento primero.'); return; }
    const btn = $('#ant-exportar'); btn.disabled = true; estado('Generando PDF…');
    try {
      await esperarImagenes();
      const { jsPDF } = window.jspdf;
      let doc = null;
      for (let n = 1; n <= ant.paginas; n++) {
        const p = await renderParaPdf(n);
        const pw = p.wPt * 25.4 / 72, ph = p.hPt * 25.4 / 72;
        const orient = pw > ph ? 'l' : 'p';
        if (!doc) doc = new jsPDF({ unit: 'mm', format: [pw, ph], orientation: orient, compress: true });
        else doc.addPage([pw, ph], orient);
        doc.addImage(p.canvas.toDataURL('image/jpeg', .92), 'JPEG', 0, 0, pw, ph);
      }
      // Leyenda de medidas y firma
      const med = ant.shapes.filter(s => TIPOS_MEDIDA.includes(s.tipo));
      if (med.length || ant.finalizado) {
        doc.addPage('letter', 'portrait');
        let y = 18;
        doc.setFont('helvetica', 'bold'); doc.setFontSize(14); doc.text('Anotador · ' + (ant.nombre || 'Documento'), 14, y); y += 8;
        doc.setFont('helvetica', 'normal'); doc.setFontSize(9);
        if (ant.finalizado) {
          doc.text('Documento firmado por: ' + (ant.cfg.firmado_por || C.usuario), 14, y); y += 5;
          doc.text('Fecha de firma: ' + fechaCorta(ant.cfg.firmado_fecha || ''), 14, y); y += 5;
          doc.text('Huella: ' + (ant.cfg.firma_hash || ''), 14, y); y += 8;
        }
        if (med.length) {
          doc.setFont('helvetica', 'bold'); doc.text('Medidas', 14, y); y += 5; doc.setFont('helvetica', 'normal');
          med.forEach(sh => {
            if (y > 265) { doc.addPage('letter', 'portrait'); y = 18; }
            let txt = 'Pág. ' + paginaDe(sh) + ' · ';
            if (sh.tipo === 'medir') txt += 'Distancia ' + fmtLong(A.longitud(sh));
            else if (sh.tipo === 'area') txt += 'Área ' + fmtArea(Math.abs(sh.w * sh.h));
            else if (sh.tipo === 'poligono') txt += 'Polígono ' + fmtArea(A.areaPoligono(sh.pts || [])) + ' · perímetro ' + fmtLong(A.perimetroPoligono(sh.pts || []));
            else txt += 'Volumen ' + fmtArea(A.areaPoligono(sh.pts || [])) + ' × ' + (sh.profundidad || 0).toFixed(2) + ' m = ' + fmtVol(A.areaPoligono(sh.pts || []), sh.profundidad);
            doc.text(txt, 14, y); y += 4.5;
          });
        }
      }
      doc.save('Anotador-' + slug(ant.nombre) + '.pdf');
      estado('PDF descargado');
      if (!desdeFirma) aviso('PDF descargado con las anotaciones.', 'ok');
    } catch (e) {
      aviso('No se pudo generar el PDF: ' + (e && e.message ? e.message : 'error'));
    } finally { btn.disabled = false; }
  }

  // ============================================================== CSV / medidas
  function medidasCsv() {
    const filas = [['Página', 'Tipo', 'Medida', 'Perímetro', 'Profundidad (m)', 'Volumen']];
    ant.shapes.forEach(sh => {
      if (!TIPOS_MEDIDA.includes(sh.tipo)) return;
      const area = sh.tipo === 'area' ? Math.abs(sh.w * sh.h) : A.areaPoligono(sh.pts || []);
      filas.push([paginaDe(sh), sh.tipo, sh.tipo === 'medir' ? fmtLong(A.longitud(sh)) : fmtArea(area), sh.tipo === 'poligono' || sh.tipo === 'volumen' ? fmtLong(A.perimetroPoligono(sh.pts || [])) : '', sh.tipo === 'volumen' ? (sh.profundidad || 0).toFixed(2) : '', sh.tipo === 'volumen' ? fmtVol(area, sh.profundidad) : '']);
    });
    const csv = filas.map(f => f.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' })); a.download = 'medidas-' + slug(ant.nombre) + '.csv'; a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
  }
  function medidasTexto() {
    return ant.shapes.filter(s => TIPOS_MEDIDA.includes(s.tipo)).map(sh => {
      const area = sh.tipo === 'area' ? Math.abs(sh.w * sh.h) : A.areaPoligono(sh.pts || []);
      if (sh.tipo === 'medir') return 'Pág. ' + paginaDe(sh) + ' · Distancia ' + fmtLong(A.longitud(sh));
      if (sh.tipo === 'volumen') return 'Pág. ' + paginaDe(sh) + ' · Volumen ' + fmtArea(area) + ' × ' + (sh.profundidad || 0).toFixed(2) + ' m = ' + fmtVol(area, sh.profundidad);
      return 'Pág. ' + paginaDe(sh) + ' · ' + (sh.tipo === 'area' ? 'Área ' : 'Polígono ') + fmtArea(area);
    }).join('\n');
  }

  // ============================================================== calibración
  function calibrar() {
    if (!ant.pdf && !ant.img) { aviso('Abre un documento primero.'); return; }
    ant.herramienta = 'medir'; pintarHerramientas();
    aviso('Dibuja una línea sobre una cota conocida del plano; al soltar te pedirá la medida real.', 'ok');
    ant.calibrando = true;
  }
  async function calibrarCon(sh) {
    const px = A.longitud(sh);
    if (px <= 1) return;
    const valor = await window.solicitarModal({ titulo: 'Calibrar la página', mensaje: '¿Cuántos metros mide la línea que dibujaste sobre la cota del plano?', etiqueta: 'Metros', tipo: 'number', valor: '1', min: 0.001, paso: 0.001, aceptar: 'Calibrar', icono: 'fa-ruler-combined' });
    const m = parseFloat(valor);
    ant.calibrando = false;
    if (!Number.isFinite(m) || m <= 0) { pintarMedidas(); return; }
    ant.cfg.escalas[paginaActual()] = m / px;
    ant.cfg.unidad = 'm';
    // La línea de calibración no se conserva como medida
    const i = ant.shapes.indexOf(sh); if (i >= 0) ant.shapes.splice(i, 1);
    ant.sel = -1; ant.multi = [];
    marcarSucio(); actualizarEscalaInfo(); pintarMedidas(); redibujar(); pintarLista();
    aviso('Página calibrada: 1 m = ' + Math.round(px / m) + ' px. Las medidas ya salen en unidades reales.', 'ok');
  }

  // ============================================================== construir páginas
  async function construirPaginas() {
    const l = $('#ant-paginas-lista'); if (!l) return;
    l.innerHTML = '';
    if (!(ant.pdf || ant.img)) return;
    $('#ant-paginas').hidden = false;
    const total = ant.paginas;
    const paso = Math.max(1, Math.ceil(total / 60));   // no dibujar cientos de miniaturas de golpe
    for (let n = 1; n <= total; n += paso) {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'ant-pag-mini' + (n === paginaActual() ? ' activa' : ''); b.dataset.pagina = String(n);
      const cv = document.createElement('canvas');
      try {
        if (ant.pdf) {
          const p = await ant.pdf.getPage(n);
          const base = p.getViewport({ scale: 1 });
          const vp = p.getViewport({ scale: Math.min(1, 150 / Math.max(base.width, base.height)) });
          cv.width = Math.max(1, Math.round(vp.width)); cv.height = Math.max(1, Math.round(vp.height));
          await p.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise;
        } else {
          const t = tamPagina(1); cv.width = Math.max(1, Math.round(t.w * 150 / Math.max(t.w, t.h))); cv.height = Math.max(1, Math.round(t.h * 150 / Math.max(t.w, t.h)));
          cv.getContext('2d').drawImage(ant.img, 0, 0, cv.width, cv.height);
        }
        const img = document.createElement('img'); img.src = cv.toDataURL('image/jpeg', .7); img.alt = 'Página ' + n; b.appendChild(img);
      } catch (e) { b.textContent = 'Página ' + n; }
      const s = document.createElement('span'); s.textContent = 'Página ' + n; b.appendChild(s);
      b.addEventListener('click', () => { irPagina(n, true); const c = $('#ant-cuerpo'); if (c) c.classList.remove('ant-movil-paginas'); });
      l.appendChild(b);
    }
    pintarPaginasActiva();
  }

  // ============================================================== resaltar texto (capa PDF)
  let textLayerListo = false;
  function limpiarTextLayer() {
    const t = $('#ant-texto'); if (t) { t.innerHTML = ''; t.classList.remove('activo'); }
    textLayerListo = false;
  }
  async function montarTextLayer() {
    const cont = $('#ant-texto');
    if (!cont) return;
    if (!ant.pdf) { limpiarTextLayer(); aviso('Este documento no tiene texto seleccionable; usa el resaltador rectangular.', 'ok'); return; }
    if (textLayerListo) { cont.classList.add('activo'); return; }
    cont.innerHTML = '';
    try {
      const page = await ant.pdf.getPage(paginaActual());
      const vp = page.getViewport({ scale: 1 });
      const k = tamPagina(paginaActual()).w / vp.width;
      const viewport = page.getViewport({ scale: k });
      cont.style.width = viewport.width + 'px'; cont.style.height = viewport.height + 'px';
      cont.style.setProperty('--scale-factor', viewport.scale);
      const tl = document.createElement('div');
      tl.className = 'ant-texto-capa';
      cont.appendChild(tl);
      if (pdfjsLib.renderTextLayer) {
        await pdfjsLib.renderTextLayer({ textContentSource: page.streamTextContent(), container: tl, viewport, textDivs: [] }).promise;
      } else {
        const tc = await page.getTextContent();
        tc.items.forEach(it => {
          const tr = pdfjsLib.Util.transform(viewport.transform, it.transform);
          const div = document.createElement('span');
          const fontH = Math.hypot(tr[2], tr[3]);
          div.textContent = it.str;
          div.style.cssText = 'position:absolute;left:' + tr[4] + 'px;top:' + (tr[5] - fontH) + 'px;font-size:' + fontH + 'px;transform-origin:0 0;color:transparent;white-space:pre;';
          tl.appendChild(div);
        });
      }
      textLayerListo = true;
      cont.classList.add('activo');
      cont.onmouseup = () => aplicarResaltadoTexto();
    } catch (e) {
      limpiarTextLayer(); aviso('No se pudo preparar el texto del PDF; usa el resaltador rectangular.', 'ok');
    }
  }
  function aplicarResaltadoTexto() {
    if (ant.soloLectura) return;
    const sel = window.getSelection();
    if (!sel || sel.isCollapsed || !sel.rangeCount) return;
    const layer = $('#ant-texto .ant-texto-capa'); if (!layer) return;
    const lr = layer.getBoundingClientRect();
    const rects = [];
    for (let i = 0; i < sel.rangeCount; i++) {
      const r = sel.getRangeAt(i);
      const rs = r.getClientRects();
      for (const x of rs) {
        if (x.width < 1 || x.height < 1) continue;
        rects.push([(x.left - lr.left) / ant.k, (x.top - lr.top) / ant.k, x.width / ant.k, x.height / ant.k]);
      }
    }
    const texto = sel.toString().slice(0, 2000);
    sel.removeAllRanges();
    if (!rects.length) return;
    const tipo = ant.herramienta === 'subrayado' || ant.herramienta === 'tachado' ? ant.herramienta : 'resaltado_texto';
    const sh = { tipo, color: tipo === 'resaltado_texto' ? '#facc15' : ant.color, grosor: 2, estilo: 'solida', relleno: false, texto, autor: C.usuario || '', creado: fechaISO(), estado: 'abierta', notas: [], grupo: 0, pagina: paginaActual(), rects, opacidad: .32, capa: ant.capa };
    snapshot(); ant.shapes.push(sh); ant.sel = ant.shapes.length - 1; ant.multi = [ant.sel];
    marcarSucio(); redibujar(); pintarLista(); pintarDetalle();
  }

  // ============================================================== punch / RFI
  function crearPunch() {
    const sh = ant.shapes[ant.sel]; if (!sh) return;
    const txt = (sh.texto || '').trim() || A.titulo(sh, cfgBase());
    const desc = txt + (sh.notas && sh.notas.length ? '\n\nNotas:\n' + sh.notas.map(n => '· ' + n.texto).join('\n') : '') + '\n\nOrigen: ' + (ant.nombre || 'Anotador') + ' · pág. ' + paginaDe(sh);
    const q = new URLSearchParams({ proyecto: C.proyecto, desde: 'anotador', titulo: txt.slice(0, 200), ubicacion: (ant.fuenteNombre || '') + ' · pág. ' + paginaDe(sh), descripcion: desc });
    window.open('/punch?' + q.toString(), '_blank', 'noopener');
  }
  function crearRfi() {
    const sh = ant.shapes[ant.sel]; if (!sh) return;
    const txt = (sh.texto || '').trim() || A.titulo(sh, cfgBase());
    const q = new URLSearchParams({ proyecto: C.proyecto, desde: 'anotador', asunto: txt.slice(0, 200), descripcion: (txt + '\n\nPág. ' + paginaDe(sh) + ' · ' + (ant.nombre || '')).slice(0, 1500) });
    window.open('/rfi?' + q.toString(), '_blank', 'noopener');
  }

  // ============================================================== eventos de UI
  function bind() {
    $('#ant-abrir') && $('#ant-abrir').addEventListener('click', abrirNuevo);
    $$('.pn-tab[data-ptab]').forEach(b => b.addEventListener('click', () => panelTab(b.dataset.ptab)));
    $$('[data-tool]').forEach(b => b.addEventListener('click', () => {
      ant.herramienta = b.dataset.tool;
      ant.polygon = null;
      if (['imagen', 'simbolo', 'estampa', 'firma'].includes(ant.herramienta) && !ant.pendiente) {
        panelTab('insertar');
        const c = $('#ant-cuerpo');
        if (c && window.matchMedia && matchMedia('(max-width: 80rem)').matches) c.classList.add('ant-movil-panel');
        aviso({ imagen: 'Sube o elige una imagen para insertarla.', simbolo: 'Elige un símbolo de la biblioteca.', firma: 'Crea o elige una firma para insertarla.', estampa: 'Elige una estampa para insertarla.' }[ant.herramienta], 'ok');
      }
      if (['resaltado_texto', 'subrayado', 'tachado'].includes(ant.herramienta)) montarTextLayer(); else limpiarTextLayer();
      pintarHerramientas(); redibujar();
    }));
    $('#ant-color') && $('#ant-color').addEventListener('input', e => { ant.color = e.target.value; const sh = ant.shapes[ant.sel]; if (sh && !ant.soloLectura) { sh.color = ant.color; marcarSucio(); redibujar(); } });
    const cambiarGrosor = e => {
      ant.grosor = parseFloat(e.target.value) || 5;
      const v = $('#ant-grosor-val'); if (v) v.textContent = ant.grosor;
      const sh = ant.shapes[ant.sel];
      if (sh && !ant.soloLectura) { sh.grosor = ant.grosor; marcarSucio(); redibujar(); }
    };
    $('#ant-grosor') && $('#ant-grosor').addEventListener('input', cambiarGrosor);
    $('#ant-grosor') && $('#ant-grosor').addEventListener('change', cambiarGrosor);
    $('#ant-estilo') && $('#ant-estilo').addEventListener('change', e => {
      ant.estilo = e.target.value;
      const sh = ant.shapes[ant.sel];
      if (sh && !ant.soloLectura) { sh.estilo = ant.estilo; marcarSucio(); redibujar(); }
    });
    $('#ant-relleno') && $('#ant-relleno').addEventListener('change', e => { ant.relleno = e.target.checked; });
    $('#ant-opacidad') && $('#ant-opacidad').addEventListener('input', e => {
      ant.opacidad = (parseInt(e.target.value, 10) || 100) / 100;
      const v = $('#ant-opacidad-val'); if (v) v.textContent = Math.round(ant.opacidad * 100) + '%';
      const sh = ant.shapes[ant.sel];
      if (sh && !ant.soloLectura) { sh.opacidad = ant.opacidad; marcarSucio(); redibujar(); }
    });
    $('#ant-capa-activa') && $('#ant-capa-activa').addEventListener('change', e => { ant.capa = e.target.value; pintarCapas(); });
    $('#ant-capa-nueva') && $('#ant-capa-nueva').addEventListener('click', nuevaCapa);
    $('#ant-capas-lista') && $('#ant-capas-lista').addEventListener('click', e => {
      const fila = e.target.closest('.ant-capa'); if (!fila) return;
      const op = e.target.closest('[data-op]')?.dataset.op;
      const id = fila.dataset.id, c = capaDe(id); if (!c) return;
      if (!op) { ant.capa = id; pintarCapas(); return; }
      if (ant.soloLectura) return;
      if (op === 'ver') { c.visible = c.visible === false; }
      else if (op === 'bloq') { c.bloqueada = !c.bloqueada; }
      else if (op === 'subir' || op === 'bajar') {
        const i = ant.capas.indexOf(c), j = op === 'subir' ? i - 1 : i + 1;
        if (j >= 0 && j < ant.capas.length) { const t = ant.capas[i]; ant.capas[i] = ant.capas[j]; ant.capas[j] = t; }
      } else if (op === 'borrar') {
        if (ant.capas.length < 2) return;
        const primera = ant.capas.find(x => x.id !== id);
        ant.shapes.forEach(sh => { if (sh.capa === id) sh.capa = primera.id; });
        ant.capas = ant.capas.filter(x => x.id !== id);
        if (ant.capa === id) ant.capa = primera.id;
      }
      marcarSucio(); pintarCapas(); redibujar(); pintarLista(); pintarMedidas();
    });
    $('#ant-capas-lista') && $('#ant-capas-lista').addEventListener('input', e => {
      if (ant.soloLectura) return;
      const fila = e.target.closest('.ant-capa'); if (!fila) return;
      const c = capaDe(fila.dataset.id); if (!c) return;
      if (e.target.dataset.op === 'nombre') c.nombre = e.target.value.slice(0, 60);
      else if (e.target.dataset.op === 'opacidad') c.opacidad = parseInt(e.target.value, 10) || 0;
      else return;
      marcarSucio(); redibujar();
    });
    $('#ant-capas-lista') && $('#ant-capas-lista').addEventListener('change', e => {
      if (e.target.dataset.op === 'nombre') { pintarCapas(); marcarSucio(); }
    });
    $('#ant-sim-buscar') && $('#ant-sim-buscar').addEventListener('input', pintarSimbolos);
    $('#ant-undo') && $('#ant-undo').addEventListener('click', deshacer);
    $('#ant-redo') && $('#ant-redo').addEventListener('click', rehacer);
    $('#ant-borrar') && $('#ant-borrar').addEventListener('click', borrarSeleccion);
    $('#ant-ver') && $('#ant-ver').addEventListener('click', () => { ant.ver = !ant.ver; $('#ant-ver').setAttribute('aria-pressed', ant.ver); $('#ant-ver').innerHTML = '<i class="fa-solid ' + (ant.ver ? 'fa-eye' : 'fa-eye-slash') + '"></i>'; redibujar(); });
    $('#ant-ajustar') && $('#ant-ajustar').addEventListener('click', ajustarVista);
    $('#ant-zoom-mas') && $('#ant-zoom-mas').addEventListener('click', () => zoomEn(1.2));
    $('#ant-zoom-menos') && $('#ant-zoom-menos').addEventListener('click', () => zoomEn(1 / 1.2));
    $('#ant-pag-prev') && $('#ant-pag-prev').addEventListener('click', () => irPagina(paginaActual() - 1));
    $('#ant-pag-next') && $('#ant-pag-next').addEventListener('click', () => irPagina(paginaActual() + 1));
    $('#ant-pag') && $('#ant-pag').addEventListener('change', e => irPagina(parseInt(e.target.value, 10) || 1));
    $('#ant-guardar') && $('#ant-guardar').addEventListener('click', abrirGuardar);
    $('#ant-g-confirmar') && $('#ant-g-confirmar').addEventListener('click', e => { e.preventDefault(); guardar(); });
    $('#ant-exportar') && $('#ant-exportar').addEventListener('click', () => exportarPdf(false));
    $('#ant-firmar-btn') && $('#ant-firmar-btn').addEventListener('click', finalizarFirma);
    $('#ant-reabrir') && $('#ant-reabrir').addEventListener('click', reabrir);
    $('#ant-versiones') && $('#ant-versiones').addEventListener('click', abrirVersiones);
    $('#ant-calibrar') && $('#ant-calibrar').addEventListener('click', calibrar);
    $('#ant-medidas-csv') && $('#ant-medidas-csv').addEventListener('click', medidasCsv);
    $('#ant-medidas-copiar') && $('#ant-medidas-copiar').addEventListener('click', async () => { try { await navigator.clipboard.writeText(medidasTexto()); aviso('Medidas copiadas.', 'ok'); } catch (e) { aviso('No se pudo copiar.'); } });
    const esMovil = () => !!(window.matchMedia && matchMedia('(max-width: 80rem)').matches);
    $('#ant-panel-btn') && $('#ant-panel-btn').addEventListener('click', () => {
      const c = $('#ant-cuerpo');
      if (esMovil()) {
        c.classList.remove('ant-movil-paginas');
        const abrir = !c.classList.contains('ant-movil-panel');
        c.classList.toggle('ant-movil-panel', abrir);
        if (abrir) panelTab('lista');
      } else {
        c.classList.toggle('ant-sin-panel');
      }
    });
    $('#ant-panel-cerrar') && $('#ant-panel-cerrar').addEventListener('click', () => { $('#ant-cuerpo').classList.add('ant-sin-panel'); $('#ant-cuerpo').classList.remove('ant-movil-panel'); });
    const alternarPaginas = () => {
      const c = $('#ant-cuerpo');
      if (esMovil()) {
        c.classList.remove('ant-movil-panel');
        c.classList.toggle('ant-movil-paginas');
        construirPaginas();
        return;
      }
      c.classList.toggle('ant-sin-paginas');
      if (!c.classList.contains('ant-sin-paginas')) construirPaginas();
    };
    $('#ant-paginas-cerrar') && $('#ant-paginas-cerrar').addEventListener('click', alternarPaginas);
    $('#ant-paginas-btn') && $('#ant-paginas-btn').addEventListener('click', alternarPaginas);
    $('#ant-imagen-subir') && $('#ant-imagen-subir').addEventListener('click', () => { const f = $('#ant-imagen-file').files[0]; if (!f) { $('#ant-imagen-file').click(); } else subirImagen(f); });
    $('#ant-imagen-file') && $('#ant-imagen-file').addEventListener('change', e => { const f = e.target.files[0]; if (f) subirImagen(f); });
    $('#ant-img-file2') && $('#ant-img-file2').addEventListener('change', e => {
      const f = e.target.files[0]; if (!f) return;
      const url = URL.createObjectURL(f); $('#ant-img-preview').innerHTML = '<img src="' + url + '" alt="">'; $('#ant-img-preview').dataset.file = '1';
      $('#ant-img-confirmar').dataset.file = '1';
    });
    $('#ant-img-confirmar') && $('#ant-img-confirmar').addEventListener('click', async () => {
      const f = $('#ant-img-file2').files[0]; if (f) { await subirImagen(f); $('#ant-dlg-imagen').close(); }
      else aviso('Elige una imagen.');
    });
    $('#ant-form-imagen') && $('#ant-form-imagen').addEventListener('submit', e => { e.preventDefault(); $('#ant-img-confirmar') && $('#ant-img-confirmar').click(); });
    $('#ant-form-profundidad') && $('#ant-form-profundidad').addEventListener('submit', e => { e.preventDefault(); $('#ant-prof-confirmar') && $('#ant-prof-confirmar').click(); });
    $('#ant-firma-nueva') && $('#ant-firma-nueva').addEventListener('click', abrirDialogoFirma);
    $$('#ant-dlg-firma [data-ftab]').forEach(b => b.addEventListener('click', () => modoFirma(b.dataset.ftab)));
    $('#ant-firma-texto') && $('#ant-firma-texto').addEventListener('input', pintarFirmaVista);
    $('#ant-firma-fuente') && $('#ant-firma-fuente').addEventListener('change', pintarFirmaVista);
    $('#ant-firma-limpiar') && $('#ant-firma-limpiar').addEventListener('click', iniciarFirma);
    $('#ant-firma-guardar') && $('#ant-firma-guardar').addEventListener('click', e => { e.preventDefault(); guardarFirma(false); });
    $('#ant-firma-solo') && $('#ant-firma-solo').addEventListener('click', e => { e.preventDefault(); guardarFirma(true); });
    $('#ant-form-firma') && $('#ant-form-firma').addEventListener('submit', e => { e.preventDefault(); guardarFirma(false); });
    $('#ant-form-guardar') && $('#ant-form-guardar').addEventListener('submit', e => { e.preventDefault(); guardar(); });
    $$('[data-cerrar-dlg]').forEach(b => b.addEventListener('click', () => { const d = b.closest('dialog'); if (d) d.close(); }));
    // Buscar en el listado de documentos guardados
    const buscar = $('#ant-buscar-doc');
    if (buscar) buscar.addEventListener('input', () => {
      const q = buscar.value.trim().toLowerCase();
      let visibles = 0;
      $$('.ant-doc').forEach(card => {
        const coincide = !q || (card.dataset.buscar || '').includes(q);
        card.hidden = !coincide;
        if (coincide) visibles++;
      });
      const grid = $('#ant-lista-grid'); const gridVacia = $('#ant-buscar-vacia');
      if (grid) grid.hidden = visibles === 0;
      if (gridVacia) gridVacia.hidden = visibles > 0;
      if (gridVacia) gridVacia.textContent = 'No hay documentos que coincidan con «' + buscar.value.trim() + '».';
    });
    // filtros y detalle
    $$('.ant-filtros [data-filtro]').forEach(b => b.addEventListener('click', () => {
      ant.filtro = b.dataset.filtro;
      $$('.ant-filtros [data-filtro]').forEach(x => x.classList.toggle('activo', x === b));
      pintarLista();
    }));
    $('#ant-todas-paginas') && $('#ant-todas-paginas').addEventListener('change', e => { ant.todasPaginas = e.target.checked; pintarLista(); });
    $('#ant-det-cerrar') && $('#ant-det-cerrar').addEventListener('click', () => { ant.sel = -1; ant.multi = []; redibujar(); pintarLista(); pintarDetalle(); pintarProps(); });
    $('#ant-det-estado') && $('#ant-det-estado').addEventListener('click', () => { const sh = ant.shapes[ant.sel]; if (!sh) return; snapshot(); sh.estado = sh.estado === 'resuelta' ? 'abierta' : 'resuelta'; marcarSucio(); redibujar(); pintarLista(); pintarDetalle(); });
    $('#ant-det-grupo') && $('#ant-det-grupo').addEventListener('click', () => {
      const sh = ant.shapes[ant.sel]; if (!sh) return; snapshot();
      if (sh.grupo) { const g = sh.grupo; ant.shapes.forEach(s => { if (s.grupo === g) s.grupo = 0; }); ant.multi = [ant.sel]; }
      else { const g = ant.shapes.reduce((m, s) => Math.max(m, parseInt(s.grupo, 10) || 0), 0) + 1; (ant.multi.length ? ant.multi : [ant.sel]).forEach(i => { if (ant.shapes[i]) ant.shapes[i].grupo = g; }); }
      marcarSucio(); redibujar(); pintarLista(); pintarDetalle();
    });
    $('#ant-det-duplicar') && $('#ant-det-duplicar').addEventListener('click', duplicarSeleccion);
    $('#ant-det-punch') && $('#ant-det-punch').addEventListener('click', crearPunch);
    $('#ant-det-rfi') && $('#ant-det-rfi').addEventListener('click', crearRfi);
    $('#ant-nota-form') && $('#ant-nota-form').addEventListener('submit', e => {
      e.preventDefault(); const sh = ant.shapes[ant.sel], t = $('#ant-nota-texto');
      if (!sh || !t.value.trim()) return;
      snapshot(); (sh.notas = sh.notas || []).push({ texto: t.value.trim(), autor: C.usuario || '', fecha: fechaISO() });
      t.value = ''; marcarSucio(); redibujar(); pintarLista(); pintarDetalle();
    });
    $('#ant-prop-aplicar') && $('#ant-prop-aplicar').addEventListener('click', () => {
      const sh = ant.shapes[ant.sel]; if (!sh || ant.soloLectura) return;
      snapshot();
      sh.texto = $('#ant-prop-texto').value.trim();
      sh.color = $('#ant-prop-color').value;
      sh.grosor = parseFloat($('#ant-prop-grosor').value) || 5;
      sh.estilo = $('#ant-prop-estilo').value || 'solida';
      sh.opacidad = (parseInt($('#ant-prop-opacidad').value, 10) || 100) / 100;
      sh.capa = $('#ant-prop-capa').value || sh.capa;
      sh.relleno = $('#ant-prop-relleno').checked;
      if (sh.tipo === 'volumen') sh.profundidad = Math.max(0, parseFloat($('#ant-prop-profundidad').value) || 0);
      if (sh.tipo === 'poligono') sh.etiqueta = $('#ant-prop-etiqueta').value;
      if (sh.tipo === 'firma') { sh.nombre = $('#ant-prop-nombre').value.trim(); sh.cargo = $('#ant-prop-cargo').value.trim(); }
      if (['imagen', 'simbolo', 'estampa', 'firma'].includes(sh.tipo)) sh.rot = parseFloat($('#ant-prop-rot').value) || 0;
      marcarSucio(); redibujar(); pintarLista(); pintarDetalle(); pintarMedidas(); aviso('Propiedades aplicadas.', 'ok');
    });
    // atajos
    document.addEventListener('keydown', e => {
      const tag = (document.activeElement || {}).tagName || '';
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;
      if (!ant.pdf && !ant.img) return;
      const ctrl = e.ctrlKey || e.metaKey;
      if (ctrl && e.key.toLowerCase() === 's') { e.preventDefault(); abrirGuardar(); return; }
      if (ctrl && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? rehacer() : deshacer(); return; }
      if (ctrl && e.key.toLowerCase() === 'c' && ant.sel >= 0) { ant.portapapeles = (ant.multi.length ? ant.multi : [ant.sel]).map(i => clon(ant.shapes[i])).filter(Boolean); return; }
      if (ctrl && e.key.toLowerCase() === 'v' && ant.portapapeles.length) { e.preventDefault(); pegar(); return; }
      if (ctrl && e.key.toLowerCase() === 'd' && ant.sel >= 0) { e.preventDefault(); duplicarSeleccion(); return; }
      if (e.key === 'Backspace' && ant.polygon) { e.preventDefault(); ant.polygon.pts.pop(); if (!ant.polygon.pts.length) ant.polygon = null; redibujar(); return; }
      if ((e.key === 'Delete' || e.key === 'Backspace') && ant.sel >= 0) { e.preventDefault(); borrarSeleccion(); return; }
      if (e.key === 'Escape') { ant.sel = -1; ant.multi = []; ant.polygon = null; ant.herramienta = 'seleccion'; limpiarTextLayer(); pintarHerramientas(); redibujar(); pintarLista(); pintarDetalle(); pintarProps(); return; }
      if (e.key === 'Enter' && ant.polygon) { e.preventDefault(); cerrarPoligono(); return; }
      const k = e.key.toLowerCase(), mapa = { v: 'seleccion', h: 'mano', g: 'globo', t: 'texto', f: 'flecha', l: 'linea', y: 'polilinea', r: 'rect', e: 'elipse', n: 'nube', m: 'medir', a: 'area', p: 'poligono', o: 'volumen' };
      if (mapa[k]) { ant.herramienta = mapa[k]; ant.polygon = null; pintarHerramientas(); redibujar(); }
    });
    window.addEventListener('beforeunload', e => { if (ant.dirty && (ant.pdf || ant.img)) { e.preventDefault(); e.returnValue = ''; } });
    // calibración al soltar la línea
    const fin = e => {
      if (ant.calibrando && ant.sel >= 0) {
        const sh = ant.shapes[ant.sel];
        if (sh && sh.tipo === 'medir') calibrarCon(sh);
        ant.calibrando = false;
      }
    };
    if (anotEl) { anotEl.addEventListener('pointerup', fin); }
  }

  function borrarSeleccion() {
    if (ant.sel < 0 || ant.soloLectura) return;
    const bloqueada = (ant.multi.length ? ant.multi : [ant.sel]).some(i => { const c = capaDe(ant.shapes[i]?.capa); return c && (c.bloqueada || c.visible === false); });
    if (bloqueada) { aviso('La capa de la selección está bloqueada u oculta.'); return; }
    snapshot();
    const ix = (ant.multi.length ? ant.multi : [ant.sel]).slice().sort((a, b) => b - a);
    ix.forEach(i => ant.shapes.splice(i, 1));
    ant.sel = -1; ant.multi = []; marcarSucio(); redibujar(); pintarLista(); pintarDetalle(); pintarProps(); pintarMedidas();
  }
  function pegar() {
    snapshot();
    const base = ant.portapapeles.map(clon), off = 20;
    base.forEach(sh => { A.mover(sh, off, off); sh.autor = C.usuario || ''; sh.creado = fechaISO(); sh.pagina = paginaActual(); sh.estado = 'abierta'; ant.shapes.push(sh); });
    ant.multi = ant.shapes.map((_, i) => i).slice(-base.length); ant.sel = ant.multi[ant.multi.length - 1] || -1;
    marcarSucio(); redibujar(); pintarLista(); pintarDetalle();
  }
  function duplicarSeleccion() {
    const ix = ant.multi.length ? ant.multi.slice() : [ant.sel];
    if (ix.some(i => { const c = capaDe(ant.shapes[i]?.capa); return c && (c.bloqueada || c.visible === false); })) { aviso('La capa de la selección está bloqueada u oculta.'); return; }
    const ors = ix.map(i => clon(ant.shapes[i])).filter(Boolean);
    snapshot();
    ors.forEach(sh => { A.mover(sh, 18, 18); sh.creado = fechaISO(); sh.autor = C.usuario || ''; ant.shapes.push(sh); });
    ant.multi = ant.shapes.map((_, i) => i).slice(-ors.length); ant.sel = ant.multi[ant.multi.length - 1] || -1;
    marcarSucio(); redibujar(); pintarLista(); pintarDetalle();
  }
  function siguienteNumero() {
    let n = 0; ant.shapes.forEach(s => { if (s.tipo === 'globo') n = Math.max(n, parseInt(s.numero, 10) || 0); }); return n + 1;
  }

  // ============================================================== arranque
  async function iniciar() {
    bind();
    asegurarCapas();
    pintarHerramientas();
    pintarCapas();
    if (C.abrir && C.abrir.id) {
      try { await abrirGuardado(C.abrir); construirPaginas(); }
      catch (e) { aviso(e.message || 'No se pudo abrir el documento.'); }
    }
    if (C.historial && C.abrir && C.abrir.id) abrirVersiones();
  }
  iniciar();
})();
