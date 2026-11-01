/* Motor de anotaciones compartido (Comparador y Anotador).
   Dibuja las formas sobre un canvas de documento, resuelve selección, tiradores, movimiento y escala,
   y calcula medidas reales (longitud, área, perímetro y volumen) con la escala calibrada.
   No guarda estado ni escucha eventos: cada herramienta decide qué formas tiene y cuándo redibujar.
   Configuración que recibe cada llamada: { base, k, mPorPx, imagenes }.
   - base: factor de tamaño de pantalla (el que usa el Comparador para textos y globos).
   - k: zoom de la vista (para tolerancias y tiradores nítidos).
   - mPorPx: metros por píxel de la calibración (0 = sin escala).
   - imagenes: mapa opcional url => HTMLImageElement ya cargado (imágenes, firmas y estampas). */
(function () {
  'use strict';

  const VACIAS = ['rect', 'elipse', 'nube', 'resaltado', 'area', 'poligono', 'volumen'];
  const TIPOS = ['seleccion', 'globo', 'texto', 'resaltador', 'resaltado', 'flecha', 'linea', 'rect', 'elipse', 'nube',
    'medir', 'area', 'poligono', 'volumen', 'imagen', 'estampa', 'firma', 'resaltado_texto', 'subrayado', 'tachado', 'mano'];

  // ---------- utilidades de configuración ----------
  const base = cfg => Math.max(0.1, +(cfg && cfg.base) || 1);
  const zoom = cfg => Math.max(0.02, +(cfg && cfg.k) || 1);
  const pxPantalla = cfg => 1 / zoom(cfg);
  const mPorPx = cfg => (cfg && cfg.mPorPx > 0 ? +cfg.mPorPx : 0);

  const vacia = sh => VACIAS.includes(sh && sh.tipo);
  const clone = o => JSON.parse(JSON.stringify(o));

  // ---------- formato de medidas ----------
  function fmtLong(px, cfg) {
    const m = mPorPx(cfg);
    if (!m) return Math.round(px) + ' px';
    const v = px * m;
    if (v >= 1) return v.toFixed(2) + ' m';
    if (v >= 0.01) return (v * 100).toFixed(0) + ' cm';
    return (v * 1000).toFixed(0) + ' mm';
  }
  function fmtArea(px2, cfg) {
    const m = mPorPx(cfg);
    if (!m) return Math.round(px2) + ' px²';
    const v = px2 * m * m;
    if (v >= 1) return v.toFixed(2) + ' m²';
    return (v * 10000).toFixed(0) + ' cm²';
  }
  function fmtVol(px3, cfg) {
    const m = mPorPx(cfg);
    if (!m) return Math.round(px3) + ' px³';
    const v = px3 * m * m * m;
    if (v >= 1) return v.toFixed(2) + ' m³';
    return (v * 1e6).toFixed(0) + ' cm³';
  }
  /** Volumen de un polígono (área en px²) por una profundidad en metros */
  function fmtVolumen(areaPx2, profundidadM, cfg) {
    const m = mPorPx(cfg);
    if (!m) return '';
    const v = areaPx2 * m * m * Math.max(0, +profundidadM || 0);
    if (v >= 1) return v.toFixed(2) + ' m³';
    return (v * 1e6).toFixed(0) + ' cm³';
  }

  function longitud(sh) {
    return Math.hypot((sh.x2 || 0) - (sh.x1 || 0), (sh.y2 || 0) - (sh.y1 || 0));
  }
  function areaPoligono(pts) {
    if (!pts || pts.length < 3) return 0;
    let a = 0;
    for (let i = 0; i < pts.length; i++) {
      const p = pts[i], q = pts[(i + 1) % pts.length];
      a += p[0] * q[1] - q[0] * p[1];
    }
    return Math.abs(a) / 2;
  }
  function perimetroPoligono(pts, cerrado = true) {
    if (!pts || pts.length < 2) return 0;
    let p = 0;
    for (let i = 1; i < pts.length; i++) p += Math.hypot(pts[i][0] - pts[i - 1][0], pts[i][1] - pts[i - 1][1]);
    if (cerrado && pts.length > 2) p += Math.hypot(pts[0][0] - pts[pts.length - 1][0], pts[0][1] - pts[pts.length - 1][1]);
    return p;
  }
  function centroPoligono(pts) {
    if (!pts || !pts.length) return { x: 0, y: 0 };
    let a = 0, cx = 0, cy = 0;
    for (let i = 0; i < pts.length; i++) {
      const p = pts[i], q = pts[(i + 1) % pts.length];
      const f = p[0] * q[1] - q[0] * p[1];
      a += f; cx += (p[0] + q[0]) * f; cy += (p[1] + q[1]) * f;
    }
    if (Math.abs(a) < 1e-6) {   // polígono degenerado: promedio simple
      const s = pts.reduce((o, p) => ({ x: o.x + p[0], y: o.y + p[1] }), { x: 0, y: 0 });
      return { x: s.x / pts.length, y: s.y / pts.length };
    }
    return { x: cx / (3 * a), y: cy / (3 * a) };
  }
  function puntoEnPoligono(pts, p) {
    let dentro = false;
    for (let i = 0, j = pts.length - 1; i < pts.length; j = i++) {
      const xi = pts[i][0], yi = pts[i][1], xj = pts[j][0], yj = pts[j][1];
      if (((yi > p.y) !== (yj > p.y)) && (p.x < (xj - xi) * (p.y - yi) / ((yj - yi) || 1e-9) + xi)) dentro = !dentro;
    }
    return dentro;
  }

  // ---------- texto ----------
  let medicion = null;
  function lienzoMedicion() {
    if (!medicion) medicion = document.createElement('canvas').getContext('2d');
    return medicion;
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
  function medidaTexto(sh, g, cfg) {
    g = g || lienzoMedicion();
    const e = base(cfg), f = (sh.fuente || 16) * e;
    g.save(); g.font = '600 ' + f + 'px Inter, system-ui, sans-serif';
    const maxW = 420 * e, lineas = envolver(g, sh.texto, maxW);
    let w = 0; lineas.forEach(l => { w = Math.max(w, g.measureText(l).width); });
    const out = { f, lineas, w: Math.max(20 * e, Math.min(maxW, w)), h: Math.max(f, lineas.length * f * 1.28), maxW };
    g.restore();
    return out;
  }
  function cajaTextoGlobo(sh, g, cfg) {
    const e = base(cfg), m = medidaTexto(sh, g, cfg), r = (sh.radio || 28) * e;
    return { x: sh.x + r + 10 * e, y: sh.y - r, w: Math.min(320 * e, m.w + 18 * e), h: Math.max(r * 2, m.h + 12 * e) };
  }
  function caja(sh, g, cfg) {
    g = g || lienzoMedicion();
    if (vacia(sh)) {
      if ((sh.tipo === 'poligono' || sh.tipo === 'volumen') && sh.pts && sh.pts.length) {
        const xs = sh.pts.map(p => p[0]), ys = sh.pts.map(p => p[1]);
        return { x: Math.min(...xs), y: Math.min(...ys), w: Math.max(1, Math.max(...xs) - Math.min(...xs)), h: Math.max(1, Math.max(...ys) - Math.min(...ys)) };
      }
      return { x: sh.x, y: sh.y, w: sh.w, h: sh.h };
    }
    if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') return { x: Math.min(sh.x1, sh.x2), y: Math.min(sh.y1, sh.y2), w: Math.abs(sh.x2 - sh.x1), h: Math.abs(sh.y2 - sh.y1) };
    if (sh.tipo === 'mano' || sh.tipo === 'resaltador') {
      const xs = (sh.pts || []).map(p => p[0]), ys = (sh.pts || []).map(p => p[1]);
      return { x: Math.min(...xs), y: Math.min(...ys), w: Math.max(1, Math.max(...xs) - Math.min(...xs)), h: Math.max(1, Math.max(...ys) - Math.min(...ys)) };
    }
    if (sh.tipo === 'texto') { const m = medidaTexto(sh, g, cfg); return { x: sh.x, y: sh.y, w: m.w, h: m.h }; }
    if (sh.tipo === 'globo') {
      const e = base(cfg), r = (sh.radio || 28) * e;
      let b = { x: sh.x - r, y: sh.y - r, w: r * 2, h: r * 2 };
      if (sh.texto) { const c = cajaTextoGlobo(sh, g, cfg); b = { x: Math.min(b.x, c.x), y: Math.min(b.y, c.y), w: Math.max(b.x + b.w, c.x + c.w) - Math.min(b.x, c.x), h: Math.max(b.y + b.h, c.y + c.h) - Math.min(b.y, c.y) }; }
      return b;
    }
    if (sh.tipo === 'imagen' || sh.tipo === 'estampa' || sh.tipo === 'firma') {
      const extra = sh.tipo === 'firma' && (sh.nombre || sh.cargo || sh.fecha) ? 26 * base(cfg) : 0;
      return { x: sh.x, y: sh.y, w: sh.w, h: sh.h + extra };
    }
    if (sh.tipo === 'resaltado_texto' || sh.tipo === 'subrayado' || sh.tipo === 'tachado') {
      const rs = sh.rects || [];
      if (!rs.length) return { x: 0, y: 0, w: 0, h: 0 };
      const x1 = Math.min(...rs.map(r => r[0])), y1 = Math.min(...rs.map(r => r[1]));
      const x2 = Math.max(...rs.map(r => r[0] + r[2])), y2 = Math.max(...rs.map(r => r[1] + r[3]));
      return { x: x1, y: y1, w: Math.max(1, x2 - x1), h: Math.max(1, y2 - y1) };
    }
    return { x: 0, y: 0, w: 0, h: 0 };
  }

  function etiqueta(g, x, y, texto, col, cfg) {
    const e = base(cfg), f = Math.max(10, 13 * e);
    g.save(); g.font = '700 ' + f + 'px Inter, system-ui, sans-serif';
    const w = g.measureText(texto).width + 10 * e, h = f + 8 * e;
    g.fillStyle = 'rgba(15,23,42,.88)'; g.strokeStyle = col; g.lineWidth = Math.max(1, 1.5 * e);
    g.beginPath(); g.roundRect(x - w / 2, y - h / 2, w, h, 4 * e); g.fill(); g.stroke();
    g.fillStyle = '#fff'; g.textAlign = 'center'; g.textBaseline = 'middle';
    g.fillText(texto, x, y + f * .04);
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

  function trazoPoligono(g, pts, cerrado) {
    if (!pts || !pts.length) return false;
    g.beginPath(); g.moveTo(pts[0][0], pts[0][1]);
    pts.slice(1).forEach(p => g.lineTo(p[0], p[1]));
    if (cerrado) g.closePath();
    return true;
  }

  function dibujarImagen(g, sh, cfg) {
    const src = sh.imagen || sh.archivo || '';
    const img = src && cfg && cfg.imagenes ? cfg.imagenes[src] : null;
    if (!img) return;
    g.save();
    if (sh.opacidad != null) g.globalAlpha = Math.max(0.05, Math.min(1, +sh.opacidad));
    const rot = ((+sh.rot || 0) * Math.PI) / 180;
    if (rot) {
      g.translate(sh.x + sh.w / 2, sh.y + sh.h / 2); g.rotate(rot); g.translate(-(sh.x + sh.w / 2), -(sh.y + sh.h / 2));
    }
    g.drawImage(img, sh.x, sh.y, Math.max(1, sh.w), Math.max(1, sh.h));
    if (sh.tipo === 'firma' && (sh.nombre || sh.cargo || sh.fecha)) {
      const e = base(cfg), f = Math.max(9, 11 * e), linea = [];
      if (sh.nombre) linea.push(sh.nombre);
      if (sh.cargo) linea.push(sh.cargo);
      if (sh.fecha) linea.push(sh.fecha);
      g.fillStyle = '#111827'; g.font = '600 ' + f + 'px Inter, system-ui, sans-serif'; g.textAlign = 'center'; g.textBaseline = 'top';
      g.fillText(linea.join(' · '), sh.x + sh.w / 2, sh.y + sh.h + 4 * e);
      g.strokeStyle = 'rgba(17,24,39,.55)'; g.lineWidth = Math.max(1, e);
      g.beginPath(); g.moveTo(sh.x, sh.y + sh.h + 2 * e); g.lineTo(sh.x + sh.w, sh.y + sh.h + 2 * e); g.stroke();
    }
    g.restore();
  }

  function dibujar(g, sh, seleccionada, cfg) {
    const e = base(cfg), k = zoom(cfg), col = sh.color || '#e11d48', grosor = Math.max(1, (sh.grosor || 5) * e);
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
        etiqueta(g, sh.x + sh.w / 2, sh.y + sh.h / 2, fmtArea(Math.abs(sh.w * sh.h), cfg), col, cfg); break;
      case 'poligono':
      case 'volumen': {
        const pts = sh.pts || [];
        if (pts.length < 2) break;
        if (trazoPoligono(g, pts, sh.tipo === 'poligono' || sh.cerrado !== false)) {
          if (sh.relleno !== false) { g.globalAlpha = .14; g.fill(); g.globalAlpha = 1; }
          g.stroke();
        }
        if (sh.tipo === 'poligono' && pts.length > 2 && sh.cerrado !== false) {
          const c = centroPoligono(pts), area = areaPoligono(pts);
          etiqueta(g, c.x, c.y, (sh.etiqueta === 'perimetro' ? fmtLong(perimetroPoligono(pts), cfg) + ' · ' : '') + fmtArea(area, cfg), col, cfg);
        }
        if (sh.tipo === 'volumen' && pts.length > 2 && sh.cerrado !== false) {
          const c = centroPoligono(pts), area = areaPoligono(pts);
          const prof = Math.max(0, +sh.profundidad || 0);
          const vol = prof > 0 ? fmtVolumen(area, prof, cfg) : '';
          const txt = fmtArea(area, cfg) + (prof > 0 ? ' × ' + prof.toFixed(2) + ' m = ' + vol : '');
          etiqueta(g, c.x, c.y, txt, col, cfg);
        }
        break;
      }
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
        etiqueta(g, (sh.x1 + sh.x2) / 2, (sh.y1 + sh.y2) / 2 - 16 * e, fmtLong(longitud(sh), cfg), col, cfg);
        break;
      }
      case 'mano': case 'resaltador':
        if (!sh.pts || sh.pts.length < 2) break;
        g.globalAlpha = sh.tipo === 'resaltador' ? .36 : 1;
        g.lineWidth = sh.tipo === 'resaltador' ? grosor * 2.6 : grosor;
        g.beginPath(); g.moveTo(sh.pts[0][0], sh.pts[0][1]); sh.pts.slice(1).forEach(p => g.lineTo(p[0], p[1])); g.stroke(); g.globalAlpha = 1; break;
      case 'texto': {
        const m = medidaTexto(sh, g, cfg); g.font = '600 ' + m.f + 'px Inter, system-ui, sans-serif'; g.textBaseline = 'top';
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
          const c = cajaTextoGlobo(sh, g, cfg), m = medidaTexto(sh, g, cfg);
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
      case 'resaltado_texto':
      case 'subrayado':
      case 'tachado': {
        const rs = sh.rects || [];
        rs.forEach(r => {
          if (sh.tipo === 'resaltado_texto') {
            g.globalAlpha = Math.max(.12, Math.min(.6, sh.opacidad != null ? +sh.opacidad : .32));
            g.fillStyle = col; g.fillRect(r[0], r[1], r[2], r[3]); g.globalAlpha = 1;
          } else {
            const y = sh.tipo === 'subrayado' ? r[1] + r[3] - Math.max(1, 1.5 * e) : r[1] + r[3] / 2;
            g.beginPath(); g.moveTo(r[0], y); g.lineTo(r[0] + r[2], y); g.lineWidth = Math.max(1, 1.6 * e); g.stroke();
          }
        });
        break;
      }
      case 'imagen':
      case 'estampa':
      case 'firma':
        dibujarImagen(g, sh, cfg);
        if ((sh.tipo === 'estampa' || sh.tipo === 'firma') && sh.bloqueada) {
          g.globalAlpha = 1; g.fillStyle = '#16a34a'; g.font = '700 ' + Math.max(9, 10 * e) + 'px Inter, sans-serif';
          g.fillText('✓ firmado', sh.x, sh.y - 6 * e);
        }
        break;
    }
    if (seleccionada) seleccion(g, sh, cfg);
    g.restore();
  }

  function handles(sh, cfg) {
    const b = caja(sh, null, cfg);
    return [
      { k: 'nw', x: b.x, y: b.y }, { k: 'ne', x: b.x + b.w, y: b.y },
      { k: 'sw', x: b.x, y: b.y + b.h }, { k: 'se', x: b.x + b.w, y: b.y + b.h },
    ];
  }
  function seleccion(g, sh, cfg) {
    const b = caja(sh, null, cfg), p = pxPantalla(cfg);
    g.save(); g.strokeStyle = '#2563eb'; g.lineWidth = Math.max(1, 1.4 * p); g.setLineDash([6 * p, 4 * p]); g.strokeRect(b.x, b.y, b.w, b.h); g.setLineDash([]);
    handles(sh, cfg).forEach(h => { g.fillStyle = '#fff'; g.strokeStyle = '#2563eb'; g.lineWidth = 1.6 * p; g.fillRect(h.x - 4 * p, h.y - 4 * p, 8 * p, 8 * p); g.strokeRect(h.x - 4 * p, h.y - 4 * p, 8 * p, 8 * p); });
    g.restore();
  }

  function distSeg(p, a, b) {
    const dx = b.x - a.x, dy = b.y - a.y, l2 = dx * dx + dy * dy;
    if (!l2) return Math.hypot(p.x - a.x, p.y - a.y);
    let t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / l2; t = Math.max(0, Math.min(1, t));
    return Math.hypot(p.x - (a.x + t * dx), p.y - (a.y + t * dy));
  }

  function hit(sh, p, cfg) {
    const e = base(cfg), b = caja(sh, null, cfg), t = Math.max(6 * pxPantalla(cfg), (sh.grosor || 5) * e);
    if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') return distSeg(p, { x: sh.x1, y: sh.y1 }, { x: sh.x2, y: sh.y2 }) <= t;
    if (sh.tipo === 'globo') {
      if (Math.hypot(p.x - sh.x, p.y - sh.y) <= (sh.radio || 28) * e + 6 * pxPantalla(cfg)) return true;
      if (sh.texto) { const c = cajaTextoGlobo(sh, null, cfg); if (p.x >= c.x && p.x <= c.x + c.w && p.y >= c.y && p.y <= c.y + c.h) return true; }
      const d = distSeg(p, { x: sh.ax, y: sh.ay }, { x: sh.x, y: sh.y });
      return d <= 8 * pxPantalla(cfg);
    }
    if (sh.tipo === 'mano' || sh.tipo === 'resaltador') {
      const m = 8 * pxPantalla(cfg);
      if (p.x < b.x - m || p.x > b.x + b.w + m || p.y < b.y - m || p.y > b.y + b.h + m) return false;
      for (let i = 1; i < sh.pts.length; i++) if (distSeg(p, { x: sh.pts[i - 1][0], y: sh.pts[i - 1][1] }, { x: sh.pts[i][0], y: sh.pts[i][1] }) <= Math.max(10 * pxPantalla(cfg), (sh.grosor || 5) * e)) return true;
      return false;
    }
    if (sh.tipo === 'poligono' || sh.tipo === 'volumen') {
      const pts = sh.pts || [];
      if (pts.length > 2 && sh.cerrado !== false && puntoEnPoligono(pts, p)) return true;
      for (let i = 1; i < pts.length; i++) if (distSeg(p, { x: pts[i - 1][0], y: pts[i - 1][1] }, { x: pts[i][0], y: pts[i][1] }) <= t) return true;
      if (pts.length > 2 && distSeg(p, { x: pts[pts.length - 1][0], y: pts[pts.length - 1][1] }, { x: pts[0][0], y: pts[0][1] }) <= t) return true;
      return false;
    }
    const m = 4 * pxPantalla(cfg);
    return p.x >= b.x - m && p.x <= b.x + b.w + m && p.y >= b.y - m && p.y <= b.y + b.h + m;
  }

  function mover(sh, dx, dy) {
    if (sh.tipo === 'mano' || sh.tipo === 'resaltador') { sh.pts = sh.pts.map(p => [p[0] + dx, p[1] + dy]); }
    else if (sh.tipo === 'poligono' || sh.tipo === 'volumen') { sh.pts = (sh.pts || []).map(p => [p[0] + dx, p[1] + dy]); }
    else if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') { sh.x1 += dx; sh.y1 += dy; sh.x2 += dx; sh.y2 += dy; }
    else if (sh.tipo === 'globo') { sh.x += dx; sh.y += dy; sh.ax += dx; sh.ay += dy; }
    else if (sh.tipo === 'resaltado_texto' || sh.tipo === 'subrayado' || sh.tipo === 'tachado') { sh.rects = (sh.rects || []).map(r => [r[0] + dx, r[1] + dy, r[2], r[3]]); }
    else { sh.x += dx; sh.y += dy; }
  }
  function escalar(sh, ox, oy, sx, sy, cfg) {
    const e = base(cfg), avg = (Math.abs(sx) + Math.abs(sy)) / 2;
    const X = v => ox + (v - ox) * sx, Y = v => oy + (v - oy) * sy;
    if (sh.tipo === 'mano' || sh.tipo === 'resaltador') sh.pts = sh.pts.map(p => [X(p[0]), Y(p[1])]);
    else if (sh.tipo === 'poligono' || sh.tipo === 'volumen') sh.pts = (sh.pts || []).map(p => [X(p[0]), Y(p[1])]);
    else if (sh.tipo === 'flecha' || sh.tipo === 'linea' || sh.tipo === 'medir') { sh.x1 = X(sh.x1); sh.y1 = Y(sh.y1); sh.x2 = X(sh.x2); sh.y2 = Y(sh.y2); }
    else if (sh.tipo === 'globo') { sh.x = X(sh.x); sh.y = Y(sh.y); sh.ax = X(sh.ax); sh.ay = Y(sh.ay); sh.radio = Math.max(8, (sh.radio || 28) * avg); }
    else if (sh.tipo === 'resaltado_texto' || sh.tipo === 'subrayado' || sh.tipo === 'tachado') {
      sh.rects = (sh.rects || []).map(r => { const x1 = X(r[0]), y1 = Y(r[1]); return [Math.min(x1, X(r[0] + r[2])), Math.min(y1, Y(r[1] + r[3])), Math.abs(X(r[0] + r[2]) - x1), Math.abs(Y(r[1] + r[3]) - y1)]; });
    }
    else { sh.x = X(sh.x); sh.y = Y(sh.y); sh.w *= sx; sh.h *= sy; if (sh.tipo === 'texto') sh.fuente = Math.max(6, (sh.fuente || 16) * avg); }
  }

  /** Resumen legible de una forma (para listas laterales) */
  function titulo(sh, cfg) {
    switch (sh.tipo) {
      case 'globo': return 'Globo ' + (sh.numero || '') + (sh.texto ? ': ' + sh.texto : '');
      case 'texto': return sh.texto || 'Texto';
      case 'medir': return 'Medida ' + fmtLong(longitud(sh), cfg);
      case 'area': return 'Área ' + fmtArea(Math.abs(sh.w * sh.h), cfg);
      case 'poligono': return 'Polígono ' + fmtArea(areaPoligono(sh.pts || []), cfg) + (sh.etiqueta === 'perimetro' ? ' · ' + fmtLong(perimetroPoligono(sh.pts || []), cfg) : '');
      case 'volumen': return 'Volumen ' + (sh.profundidad ? sh.profundidad.toFixed(2) + ' m · ' : '') + fmtVolumen(areaPoligono(sh.pts || []), sh.profundidad || 0, cfg);
      case 'imagen': return 'Imagen';
      case 'estampa': return 'Estampa';
      case 'firma': return 'Firma' + (sh.nombre ? ' · ' + sh.nombre : '');
      case 'resaltado_texto': return 'Texto resaltado';
      case 'subrayado': return 'Texto subrayado';
      case 'tachado': return 'Texto tachado';
      default: return sh.tipo;
    }
  }

  window.Anotaciones = {
    TIPOS, VACIAS,
    vacia, clone, fmtLong, fmtArea, fmtVol, fmtVolumen, longitud, areaPoligono, perimetroPoligono, centroPoligono, puntoEnPoligono,
    envolver, medidaTexto, cajaTextoGlobo, caja, etiqueta, nube, dibujar, handles, seleccion, distSeg, hit, mover, escalar, titulo,
    /** Carga una imagen y la deja disponible en el mapa que se pasa a dibujar() */
    cargarImagen(url, mapa, alCargar) {
      if (!url) return null;
      if (mapa[url]) return mapa[url];
      const img = new Image();
      img.decoding = 'async';
      img.onload = () => { if (typeof alCargar === 'function') alCargar(url, img); };
      img.onerror = () => { mapa[url] = null; };
      img.src = url;
      mapa[url] = img;
      return img;
    },
  };
})();
