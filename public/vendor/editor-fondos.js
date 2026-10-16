/* Fondos de plantilla del Editor: retícula, líneas, puntos, papel kraft, etc.
 * Cada fondo se dibuja una vez en una «tesela» (canvas pequeño) que luego se repite, así no pesa aunque el lienzo sea enorme.
 * Lo usan el editor (Konva) y las vistas previas del formulario de creación. */
(function () {
  'use strict';
  // Generador pseudoaleatorio con semilla: la textura es siempre la misma
  const rng = semilla => { let a = semilla >>> 0; return () => { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; };
  const lienzo = (w, h) => { const c = document.createElement('canvas'); c.width = w; c.height = h; return c; };

  function reticula(paso, mayor, base, fino, grueso, anchoFino = 1, anchoGrueso = 2) {
    const n = mayor, t = paso * n, c = lienzo(t, t), x = c.getContext('2d');
    x.fillStyle = base; x.fillRect(0, 0, t, t);
    for (let i = 0; i < n; i++) {
      const p = i * paso;
      x.fillStyle = i === 0 ? grueso : fino; const g = i === 0 ? anchoGrueso : anchoFino;
      x.fillRect(p, 0, g, t); x.fillRect(0, p, t, g);
    }
    return c;
  }
  // Textura de papel: fibras y manchitas que se repiten sin costura (cada trazo cerca del borde se dibuja también al otro lado)
  function papel(t, base, claro, oscuro, semilla, fibras) {
    const c = lienzo(t, t), x = c.getContext('2d'), r = rng(semilla);
    x.fillStyle = base; x.fillRect(0, 0, t, t);
    const envolver = (fn, px, py, margen) => { for (const dx of [-t, 0, t]) for (const dy of [-t, 0, t]) { if (Math.abs(px + dx - t / 2) > t / 2 + margen || Math.abs(py + dy - t / 2) > t / 2 + margen) continue; fn(px + dx, py + dy); } };
    for (let i = 0; i < t * t / 60; i++) {   // manchitas
      const px = r() * t, py = r() * t, d = r() < .5;
      x.fillStyle = d ? oscuro : claro; x.globalAlpha = .05 + r() * .12;
      const rad = .6 + r() * 1.6; envolver((a, b) => { x.beginPath(); x.arc(a, b, rad, 0, 7); x.fill(); }, px, py, 4);
    }
    x.lineCap = 'round';
    for (let i = 0; i < fibras; i++) {   // fibras
      const px = r() * t, py = r() * t, ang = r() * Math.PI, len = 6 + r() * 26, d = r() < .55;
      x.strokeStyle = d ? oscuro : claro; x.globalAlpha = .08 + r() * .16; x.lineWidth = .5 + r() * 1;
      envolver((a, b) => { x.beginPath(); x.moveTo(a, b); x.quadraticCurveTo(a + Math.cos(ang) * len / 2 + (r() - .5) * 6, b + Math.sin(ang) * len / 2 + (r() - .5) * 6, a + Math.cos(ang) * len, b + Math.sin(ang) * len); x.stroke(); }, px, py, 30);
    }
    x.globalAlpha = 1;
    return c;
  }


  // ---- más texturas y retículas ----
  function isometrico(d, base, color, ancho) {
    const w = d, h = Math.round(d * Math.sqrt(3)), c = lienzo(w, h), x = c.getContext('2d');
    x.fillStyle = base; x.fillRect(0, 0, w, h); x.strokeStyle = color; x.lineWidth = ancho; x.lineCap = 'square';
    const l = (a, b, c2, e) => { x.beginPath(); x.moveTo(a, b); x.lineTo(c2, e); x.stroke(); };
    [0, h / 2, h].forEach(y => l(0, y, w, y));
    l(0, 0, w / 2, h / 2); l(w / 2, h / 2, w, h); l(w, 0, w / 2, h / 2); l(w / 2, h / 2, 0, h);
    return c;
  }
  function hexagonos(r, base, color, ancho) {
    const w = Math.round(r * 3), h = Math.round(r * Math.sqrt(3)), c = lienzo(w, h), x = c.getContext('2d');
    x.fillStyle = base; x.fillRect(0, 0, w, h); x.strokeStyle = color; x.lineWidth = ancho; x.lineJoin = 'round';
    const hex = (cx, cy) => { x.beginPath(); for (let i = 0; i < 6; i++) { const a = Math.PI / 3 * i; x[i ? 'lineTo' : 'moveTo'](cx + r * Math.cos(a), cy + r * Math.sin(a)); } x.closePath(); x.stroke(); };
    for (const dx of [-w, 0, w]) for (const dy of [-h, 0, h]) { hex(dx, dy); hex(dx + 1.5 * r, dy + h / 2); }
    return c;
  }
  function ladrillo(semilla) {
    const w = 120, h = 60, c = lienzo(w, h), x = c.getContext('2d'), r = rng(semilla);
    x.fillStyle = '#d6cfc2'; x.fillRect(0, 0, w, h);   // mortero
    const pieza = (px, py) => { const t = r() * 0.18; x.fillStyle = `rgb(${Math.round(178 + t * 90)},${Math.round(84 + t * 60)},${Math.round(52 + t * 40)})`; x.fillRect(px + 2, py + 2, 56, 26); };
    pieza(0, 0); pieza(60, 0); pieza(-30, 30); pieza(30, 30); pieza(90, 30);
    x.globalAlpha = .1; for (let i = 0; i < 220; i++) { x.fillStyle = r() < .5 ? '#000' : '#fff'; x.fillRect(r() * w, r() * h, 1.4, 1.4); }
    x.globalAlpha = 1; return c;
  }
  function madera(semilla) {
    const w = 512, h = 256, c = lienzo(w, h), x = c.getContext('2d'), r = rng(semilla), tabla = h / 4;
    for (let t = 0; t < 4; t++) {
      const y0 = t * tabla, base = 150 + r() * 40;
      x.fillStyle = `rgb(${base},${Math.round(base * .68)},${Math.round(base * .4)})`; x.fillRect(0, y0, w, tabla);
      for (let v = 0; v < 22; v++) {   // vetas: ondas con periodo entero para que empalmen en los bordes
        const k = 1 + Math.floor(r() * 3), fase = r() * 6.28, amp = 1 + r() * 2.5, yy = y0 + 3 + r() * (tabla - 6);
        x.strokeStyle = r() < .6 ? 'rgba(80,45,20,.28)' : 'rgba(255,220,170,.18)'; x.lineWidth = .6 + r() * 1.2;
        x.beginPath(); for (let px = 0; px <= w; px += 4) { const py = yy + Math.sin(px / w * 6.2832 * k + fase) * amp; x[px ? 'lineTo' : 'moveTo'](px, py); } x.stroke();
      }
      const nx = r() * w; x.fillStyle = 'rgba(60,35,15,.55)'; x.fillRect(nx, y0 + 1, 2, tabla - 2);   // unión de tablas
      x.fillStyle = 'rgba(40,22,10,.7)'; x.fillRect(0, y0, w, 2);
    }
    return c;
  }
  function lino(semilla) {
    const t = 128, c = lienzo(t, t), x = c.getContext('2d'), r = rng(semilla);
    x.fillStyle = '#e8e0cf'; x.fillRect(0, 0, t, t);
    for (let i = 0; i < t; i++) { x.fillStyle = r() < .5 ? '#fff' : '#9c8f74'; x.globalAlpha = .06 + r() * .12; x.fillRect(0, i, t, 1); x.fillStyle = r() < .5 ? '#fff' : '#9c8f74'; x.globalAlpha = .06 + r() * .12; x.fillRect(i, 0, 1, t); }
    x.globalAlpha = 1; return c;
  }
  function puntosOscuro() { const c = lienzo(40, 40), x = c.getContext('2d'); x.fillStyle = '#1f2937'; x.fillRect(0, 0, 40, 40); x.fillStyle = '#6b7a90'; x.beginPath(); x.arc(20, 20, 2.2, 0, 7); x.fill(); return c; }
  function diagonal(paso, base, color, ancho) {
    const c = lienzo(paso, paso), x = c.getContext('2d'); x.fillStyle = base; x.fillRect(0, 0, paso, paso); x.strokeStyle = color; x.lineWidth = ancho;
    [[-paso / 2, paso / 2, paso / 2, -paso / 2], [0, paso, paso, 0], [paso / 2, paso * 1.5, paso * 1.5, paso / 2]].forEach(([a, b, c2, d]) => { x.beginPath(); x.moveTo(a, b); x.lineTo(c2, d); x.stroke(); });
    return c;
  }
  function tablero(t, a, b) { const c = lienzo(t * 2, t * 2), x = c.getContext('2d'); x.fillStyle = a; x.fillRect(0, 0, t * 2, t * 2); x.fillStyle = b; x.fillRect(0, 0, t, t); x.fillRect(t, t, t, t); return c; }

  const FONDOS = {
    liso: { nombre: 'Liso', desc: 'Un solo color (puedes elegirlo)' },
    imagen: { nombre: 'Imagen propia', desc: 'Tu imagen como fondo, ajustada al formato o en mosaico' },
    reticula: { nombre: 'Retícula', desc: 'Cuadrícula con líneas principales', tesela: () => reticula(40, 5, '#ffffff', '#dbe3ee', '#aebcd0') },
    milimetrado: { nombre: 'Milimetrado', desc: 'Cuadrícula fina tipo papel milimétrico', tesela: () => reticula(10, 5, '#fffdf7', '#f1c7c7', '#e08f8f', 1, 1.6) },
    lineas: { nombre: 'Líneas (cuaderno)', desc: 'Renglones horizontales',
      tesela: () => { const c = lienzo(40, 40), x = c.getContext('2d'); x.fillStyle = '#ffffff'; x.fillRect(0, 0, 40, 40); x.fillStyle = '#bcd3ee'; x.fillRect(0, 38, 40, 2); return c; } },
    puntos: { nombre: 'Puntos', desc: 'Retícula de puntos',
      tesela: () => { const c = lienzo(40, 40), x = c.getContext('2d'); x.fillStyle = '#ffffff'; x.fillRect(0, 0, 40, 40); x.fillStyle = '#9aa8bb'; x.beginPath(); x.arc(20, 20, 2.2, 0, 7); x.fill(); return c; } },
    'cuadros-azul': { nombre: 'Cuadriculado azul', desc: 'Cuadros de cuaderno', tesela: () => reticula(28, 1, '#ffffff', '#9ec2e8', '#9ec2e8', 1, 1.5) },
    ingenieria: { nombre: 'Papel de ingeniería', desc: 'Verde pálido con cuadrícula', tesela: () => reticula(12, 5, '#eaf4e0', '#cfe2c0', '#9bc283', 1, 1.5) },
    isometrico: { nombre: 'Isométrico', desc: 'Retícula triangular a 60°', tesela: () => isometrico(40, '#ffffff', '#c3cfdf', 1) },
    hexagonos: { nombre: 'Hexágonos', desc: 'Panal', tesela: () => hexagonos(26, '#ffffff', '#c3cfdf', 1.4) },
    diagonal: { nombre: 'Líneas diagonales', desc: 'Rayado suave', tesela: () => diagonal(18, '#ffffff', '#dbe2ec', 1.6) },
    tablero: { nombre: 'Tablero (transparencia)', desc: 'Cuadros grises como en los programas de imagen', tesela: () => tablero(16, '#ffffff', '#dfe3e8') },
    reciclado: { nombre: 'Papel reciclado', desc: 'Gris verdoso con motas', tesela: () => papel(512, '#d6d5c7', '#f1efe2', '#7d7c6b', 91, 220) },
    lino: { nombre: 'Lino', desc: 'Tela de trama fina', tesela: () => lino(5) },
    concreto: { nombre: 'Concreto', desc: 'Cemento gris con poros', tesela: () => papel(256, '#b8b8b5', '#dcdcd9', '#6c6c69', 77, 40) },
    ladrillo: { nombre: 'Ladrillo', desc: 'Muro de ladrillo con mortero', tesela: () => ladrillo(13) },
    madera: { nombre: 'Madera', desc: 'Tablones con vetas', tesela: () => madera(21) },
    'pizarra-verde': { nombre: 'Pizarra verde', desc: 'Gis sobre pizarra', oscuro: true, tesela: () => papel(512, '#2f4a3f', '#8fb3a0', '#16261f', 63, 200) },
    'puntos-oscuro': { nombre: 'Puntos sobre oscuro', desc: 'Retícula de puntos clara', oscuro: true, tesela: puntosOscuro },
    kraft: { nombre: 'Papel kraft', desc: 'Cartón café con fibras', tesela: () => papel(512, '#c8a97e', '#e6cfa6', '#7a5a35', 11, 520) },
    crema: { nombre: 'Papel crema', desc: 'Papel claro con textura suave', tesela: () => papel(512, '#f4ecd8', '#ffffff', '#b9a77d', 29, 260) },
    'plano-azul': { nombre: 'Plano azul', desc: 'Cianotipo con retícula clara', oscuro: true, tesela: () => reticula(40, 5, '#1b4a8c', 'rgba(170,205,255,.28)', 'rgba(190,220,255,.6)') },
    pizarron: { nombre: 'Pizarrón', desc: 'Fondo oscuro con textura', oscuro: true, tesela: () => papel(512, '#27343a', '#5b6f78', '#0f171a', 47, 160) },
  };

  const cache = {};
  window.VPFondos = {
    lista: Object.entries(FONDOS).map(([clave, f]) => ({ clave, nombre: f.nombre, desc: f.desc, oscuro: !!f.oscuro })),
    /** Tesela repetible del fondo, o null si es liso */
    tesela(clave) { const f = FONDOS[clave]; if (!f || !f.tesela) return null; return cache[clave] || (cache[clave] = f.tesela()); },
    esOscuro(clave, color) {
      if (FONDOS[clave] && FONDOS[clave].oscuro) return true;
      if (clave !== 'liso' || !/^#[0-9a-f]{6}$/i.test(color || '')) return false;
      const n = parseInt(color.slice(1), 16), l = (0.299 * (n >> 16) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255;
      return l < 0.45;
    },
    /** Dibuja una imagen propia como fondo en un canvas 2D de w x h. cfg: modo (cubrir|contener|estirar|mosaico), escala, posX/posY (0-100),
     *  repX/repY (mosaico; repY 0 = automático), opacidad (5-100) y color (bandas y fondo bajo la imagen) */
    dibujarImagen(ctx, w, h, img, cfg) {
      const c = cfg || {};
      ctx.save();
      ctx.fillStyle = c.color || '#ffffff'; ctx.fillRect(0, 0, w, h);
      const iw = img && (img.naturalWidth || img.width), ih = img && (img.naturalHeight || img.height);
      if (iw && ih) {
        ctx.beginPath(); ctx.rect(0, 0, w, h); ctx.clip();
        ctx.globalAlpha = Math.max(.05, Math.min(1, (c.opacidad ?? 100) / 100));
        if (c.modo === 'estirar') ctx.drawImage(img, 0, 0, w, h);
        else if (c.modo === 'mosaico') {
          const nx = Math.max(1, Math.round(c.repX || 1)), tw = w / nx, th = c.repY > 0 ? h / c.repY : tw * ih / iw, ny = Math.ceil(h / th - 1e-6);
          for (let j = 0; j < ny; j++) for (let i = 0; i < nx; i++) ctx.drawImage(img, i * tw, j * th, tw + .6, th + .6);   // un poco de traslape para que no se vean costuras
        } else {
          const base = c.modo === 'contener' ? Math.min(w / iw, h / ih) : Math.max(w / iw, h / ih), sc = base * (c.escala || 1), dw = iw * sc, dh = ih * sc;
          ctx.drawImage(img, (w - dw) * (c.posX ?? 50) / 100, (h - dh) * (c.posY ?? 50) / 100, dw, dh);
        }
      }
      ctx.restore();
    },
    /** Pinta el fondo en un canvas 2D (vistas previas) */
    pintar(ctx, w, h, clave, color) {
      const t = this.tesela(clave);
      if (!t) { ctx.fillStyle = color || '#ffffff'; ctx.fillRect(0, 0, w, h); return; }
      ctx.fillStyle = ctx.createPattern(t, 'repeat'); ctx.fillRect(0, 0, w, h);
    },
  };
})();
