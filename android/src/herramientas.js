/* Herramientas de campo · solo en el teléfono.
   Fotos selladas, rutas GPS, comparador, rondín, check-in, inspección,
   croquis de punch y medición sobre foto. Los datos viven en la app
   (SQLite cifrada + archivos privados) y las fotos se guardan en el
   álbum del teléfono. No se sincronizan con la web ni con la plataforma. */
'use strict';

(function () {
  const H = window.Herramientas = window.Herramientas || {};
  const CLAVES = {
    fotos: 'herr_fotos',
    rutas: 'herr_rutas',
    comparaciones: 'herr_comparaciones',
    rondines: 'herr_rondines',
    checkins: 'herr_checkins',
    inspecciones: 'herr_inspecciones',
    croquis: 'herr_croquis',
    mediciones: 'herr_mediciones',
  };
  const ALBUM = {
    fotos: 'Foto sellada',
    rutas: 'Rutas',
    comparaciones: 'Comparador',
    rondines: 'Rondin',
    checkins: 'Check-in',
    inspecciones: 'Inspecciones',
    croquis: 'Croquis de punch',
    mediciones: 'Mediciones',
  };
  const TITULO = {
    fotos: 'Foto sellada',
    rutas: 'Rutas',
    comparaciones: 'Comparador antes/después',
    rondines: 'Rondín GPS',
    checkins: 'Check-in de residente',
    inspecciones: 'Inspección rápida',
    croquis: 'Croquis de punch',
    mediciones: 'Medición sobre foto',
  };
  const ICONO = {
    fotos: 'i-camera',
    rutas: 'i-route',
    comparaciones: 'i-compare',
    rondines: 'i-pin',
    checkins: 'i-user-check',
    inspecciones: 'i-check-list',
    croquis: 'i-pin',
    mediciones: 'i-ruler',
  };
  const DESC = {
    fotos: 'Foto con obra, actividad, fecha, GPS, responsable y nota. Se guarda sellada en el álbum.',
    rutas: 'Une fotos con GPS en un recorrido, calcula la distancia y guarda un croquis en el álbum.',
    comparaciones: 'Junta una foto de antes y una de después en una sola imagen para comparar el avance.',
    rondines: 'Define checkpoints, registra tu llegada con hora y GPS, y guarda la evidencia.',
    checkins: 'Registra tu presencia en obra con hora, ubicación, nota y selfie.',
    inspecciones: 'Checklist rápido de calidad y seguridad con resultado, nota y resumen en foto.',
    croquis: 'Marca puntos y notas sobre una foto para ubicar un punch o una observación.',
    mediciones: 'Mide distancias sobre una foto usando una medida de referencia.',
  };
  const ESTADOS_INSPECCION = [['ok', 'Bien'], ['no', 'No'], ['na', 'N/A']];
  const ITEMS_INSPECCION = ['Trabajo seguro y EPP', 'Material conforme al proyecto', 'Limpieza y orden', 'Protección de áreas terminadas'];

  H.datos = Object.fromEntries(Object.keys(CLAVES).map((k) => [k, []]));
  H.vista = 'inicio';
  H.params = {};
  H.abriendo = false;
  H.cargado = false;
  H.usuarioId = 0;
  H.ultimaUbicacion = null;

  // ---------------------------------------------------------------- utilidades

  const uid = () => Number(state.usuario?.id || 0);
  const nombreUsuario = () => String(state.usuario?.nombre || 'Usuario');
  const proyecto = () => proyectoActual()?.proyecto || null;
  const nuevoId = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  const ahoraISO = () => new Date().toISOString();
  const fechaHora = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return String(iso);
    return d.toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  };
  const coordsTexto = (lat, lon) => (lat == null || lon == null ? 'Sin GPS' : `${Number(lat).toFixed(6)}, ${Number(lon).toFixed(6)}`);
  const esc2 = (v) => esc(v);
  const bytesDe = (datos) => {
    if (datos instanceof ArrayBuffer) return new Uint8Array(datos);
    if (ArrayBuffer.isView(datos)) return new Uint8Array(datos.buffer, datos.byteOffset, datos.byteLength);
    if (Array.isArray(datos)) return new Uint8Array(datos);
    return new Uint8Array(0);
  };

  function haversine(a, b) {
    if (!a || !b || a.lat == null || a.lon == null || b.lat == null || b.lon == null) return 0;
    const R = 6371008.8, r = (x) => (x * Math.PI) / 180;
    const dLat = r(b.lat - a.lat), dLon = r(b.lon - a.lon);
    const s = Math.sin(dLat / 2) ** 2 + Math.cos(r(a.lat)) * Math.cos(r(b.lat)) * Math.sin(dLon / 2) ** 2;
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(s)));
  }
  const distanciaTexto = (m) => {
    if (!m || m < 1) return '—';
    return m < 1000 ? `${Math.round(m)} m` : `${(m / 1000).toFixed(m < 10000 ? 2 : 1)} km`;
  };

  async function leerTodos(tipo) {
    try {
      const raw = await cacheLeer(CLAVES[tipo]);
      const arr = JSON.parse(raw || '[]');
      return Array.isArray(arr) ? arr : [];
    } catch (e) { return []; }
  }

  async function mis(tipo) {
    const u = uid();
    return (await leerTodos(tipo)).filter((r) => Number(r.usuario_id) === u);
  }

  async function guardarTodos(tipo, lista) {
    await cacheGuardar(CLAVES[tipo], JSON.stringify(lista));
    H.datos[tipo] = await mis(tipo);
  }

  async function agregar(tipo, reg) {
    const todos = await leerTodos(tipo);
    const item = { ...reg, id: reg.id || nuevoId(), usuario_id: uid(), creado_en: reg.creado_en || ahoraISO() };
    todos.unshift(item);
    await guardarTodos(tipo, todos);
    return item;
  }

  async function actualizar(tipo, reg) {
    const todos = await leerTodos(tipo);
    const i = todos.findIndex((x) => x.id === reg.id && Number(x.usuario_id) === uid());
    if (i < 0) return null;
    todos[i] = { ...todos[i], ...reg, usuario_id: uid() };
    await guardarTodos(tipo, todos);
    return todos[i];
  }

  async function borrar(tipo, id) {
    const todos = await leerTodos(tipo);
    const item = todos.find((x) => x.id === id && Number(x.usuario_id) === uid());
    if (item) {
      for (const ruta of rutasDeRegistro(item)) {
        try { await invokeTauri('borrar_foto_herramienta', { ruta }); } catch (e) { /* ya no existe */ }
      }
    }
    await guardarTodos(tipo, todos.filter((x) => !(x.id === id && Number(x.usuario_id) === uid())));
  }

  function rutasDeRegistro(reg) {
    const out = [];
    const push = (v) => { if (v && String(v).includes('/files/herramientas/')) out.push(v); };
    push(reg.archivo); push(reg.antesarchivo); push(reg.despuesarchivo); push(reg.comparacionarchivo);
    push(reg.resumen_archivo);
    for (const p of reg.puntos || []) push(p.archivo);
    for (const c of reg.checkpoints || []) push(c.archivo);
    return [...new Set(out)];
  }

  async function cargarDatos(force = false) {
    const u = uid();
    if (!force && H.cargado && H.usuarioId === u) return;
    H.usuarioId = u;
    for (const tipo of Object.keys(CLAVES)) H.datos[tipo] = u ? await mis(tipo) : [];
    H.cargado = true;
  }

  // ---------------------------------------------------------------- fotos y archivos

  function pedirArchivo(camara = true) {
    return new Promise((resolve) => {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      if (camara) input.capture = 'environment';
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.appendChild(input);
      let listo = false;
      let tope = 0;
      const cerrar = (v) => {
        if (listo) return;
        listo = true;
        clearTimeout(tope);
        input.remove();
        resolve(v || null);
      };
      input.addEventListener('change', () => cerrar(input.files && input.files[0]));
      input.addEventListener('cancel', () => cerrar(null));
      tope = setTimeout(() => cerrar(null), 120000);
      input.click();
    });
  }

  function cargarImagen(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('No se pudo leer la imagen.'));
      img.src = src;
    });
  }

  async function archivoAImagen(archivo, max = 3000) {
    const dataUrl = await leerArchivoDataUrl(archivo);
    const img = await cargarImagen(dataUrl);
    const escala = Math.min(1, max / Math.max(img.width || 1, img.height || 1));
    const w = Math.max(1, Math.round((img.width || 1) * escala));
    const h = Math.max(1, Math.round((img.height || 1) * escala));
    const canvas = document.createElement('canvas');
    canvas.width = w; canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, w, h);
    return { canvas, ctx, ancho: w, alto: h, dataUrl, archivo };
  }

  function miniatura(canvas, max = 240) {
    const escala = Math.min(1, max / Math.max(canvas.width, canvas.height));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(canvas.width * escala));
    c.height = Math.max(1, Math.round(canvas.height * escala));
    c.getContext('2d').drawImage(canvas, 0, 0, c.width, c.height);
    return c.toDataURL('image/jpeg', 0.62);
  }

  async function guardarImagen(canvas, { tipo, nombre, album, aGaleria = true } = {}) {
    if (!tieneTauri()) throw new Error('Guardar fotos está disponible en la app Android.');
    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
    const limpio = String(nombre || `pw-${tipo || 'foto'}-${Date.now()}.jpg`).replace(/[^a-zA-Z0-9._-]+/g, '_');
    const archivo = await invokeTauri('guardar_foto_herramienta', { nombre: limpio, base64: dataUrl });
    let galeria = true, errorGaleria = '';
    if (aGaleria) {
      try {
        await invokeTauri('guardar_en_galeria', {
          ruta: archivo,
          album: album || ALBUM[tipo] || 'Project Web',
          nombre: limpio,
          mime: 'image/jpeg',
        });
      } catch (e) {
        galeria = false;
        errorGaleria = String(e?.message || e || 'No se pudo guardar en el álbum.');
      }
    }
    return { archivo, thumb: miniatura(canvas), galeria, errorGaleria };
  }

  async function urlDeArchivo(ruta) {
    const bytes = bytesDe(await invokeTauri('leer_foto_herramienta', { ruta }));
    return URL.createObjectURL(new Blob([bytes], { type: 'image/jpeg' }));
  }

  async function verFoto(ruta, titulo = 'Foto') {
    if (!ruta) return;
    try {
      const url = await urlDeArchivo(ruta);
      abrirModal(titulo, `<img class="herr-foto-grande" src="${url}" alt="${esc2(titulo)}">`);
    } catch (e) { toast('No se pudo abrir la foto.', 5000); }
  }

  // ---------------------------------------------------------------- ubicación y EXIF

  function ubicacionActual(tiempo = 9000) {
    return new Promise((resolve) => {
      if (!navigator.geolocation) { resolve(null); return; }
      let terminado = false;
      const listo = (v) => { if (!terminado) { terminado = true; resolve(v); } };
      try {
        navigator.geolocation.getCurrentPosition(
          (p) => {
            const loc = { lat: p.coords.latitude, lon: p.coords.longitude, alt: p.coords.altitude, precision: p.coords.accuracy };
            H.ultimaUbicacion = loc;
            listo(loc);
          },
          () => listo(H.ultimaUbicacion || null),
          { enableHighAccuracy: true, timeout: tiempo, maximumAge: 120000 }
        );
      } catch (e) { listo(H.ultimaUbicacion || null); }
      setTimeout(() => listo(H.ultimaUbicacion || null), tiempo + 500);
    });
  }

  function leerIfd(view, base, tiff, little) {
    const g16 = (p) => view.getUint16(p, little);
    const g32 = (p) => view.getUint32(p, little);
    const out = {};
    if (base + 2 > view.byteLength) return out;
    const n = g16(base);
    for (let i = 0; i < n; i++) {
      const p = base + 2 + i * 12;
      if (p + 12 > view.byteLength) break;
      const tag = g16(p), tipo = g16(p + 2), count = g32(p + 4);
      const val = p + 8;
      const leerAscii = (pos, len) => {
        let s = '';
        for (let j = 0; j < len && pos + j < view.byteLength; j++) {
          const c = view.getUint8(pos + j);
          if (!c) break;
          s += String.fromCharCode(c);
        }
        return s.trim();
      };
      const leerRacional = (pos, cant) => {
        const arr = [];
        for (let j = 0; j < cant; j++) {
          const q = pos + j * 8;
          if (q + 8 > view.byteLength) break;
          const num = g32(q), den = g32(q + 4);
          arr.push(den ? num / den : 0);
        }
        return arr;
      };
      if (tipo === 2) {
        const len = count;
        const pos = len <= 4 ? val : tiff + g32(val);
        out[tag] = leerAscii(pos, len);
      } else if (tipo === 5) {
        const pos = tiff + g32(val);
        out[tag] = leerRacional(pos, count);
      } else if (tipo === 3) {
        out[tag] = g16(val);
      } else if (tipo === 4) {
        out[tag] = g32(val);
      }
    }
    return out;
  }

  async function exifDeArchivo(archivo) {
    try {
      const buf = await archivo.arrayBuffer();
      const view = new DataView(buf);
      if (view.byteLength < 4 || view.getUint16(0) !== 0xffd8) return {};
      let off = 2;
      while (off + 4 < view.byteLength) {
        if (view.getUint8(off) !== 0xff) break;
        const marca = view.getUint16(off);
        if (marca === 0xffda) break;
        const largo = view.getUint16(off + 2);
        if (marca === 0xffe1 && off + 10 < view.byteLength && view.getUint32(off + 4) === 0x45786966) {
          const tiff = off + 10;
          const little = view.getUint16(tiff) === 0x4949;
          const g32 = (p) => view.getUint32(p, little);
          const ifd0 = tiff + g32(tiff + 4);
          const tags = leerIfd(view, ifd0, tiff, little);
          const exifPtr = tags[0x8769];
          const gpsPtr = tags[0x8825];
          const exif = exifPtr ? leerIfd(view, tiff + exifPtr, tiff, little) : {};
          const gps = gpsPtr ? leerIfd(view, tiff + gpsPtr, tiff, little) : {};
          let lat = null, lon = null;
          if (Array.isArray(gps[2]) && gps[2].length >= 3) lat = gps[2][0] + gps[2][1] / 60 + gps[2][2] / 3600;
          if (Array.isArray(gps[4]) && gps[4].length >= 3) lon = gps[4][0] + gps[4][1] / 60 + gps[4][2] / 3600;
          if (String(gps[1] || '').toUpperCase().startsWith('S')) lat = lat == null ? null : -lat;
          if (String(gps[3] || '').toUpperCase().startsWith('W')) lon = lon == null ? null : -lon;
          const fecha = exif[0x9003] || tags[0x0132] || '';
          const m = String(fecha).match(/^(\d{4}):(\d\d):(\d\d)[ T](\d\d):(\d\d):(\d\d)/);
          return {
            tomada_en: m ? `${m[1]}-${m[2]}-${m[3]}T${m[4]}:${m[5]}:${m[6]}` : null,
            lat, lon,
            alt: Array.isArray(gps[6]) ? gps[6][0] : null,
          };
        }
        off += 2 + largo;
      }
    } catch (e) { /* sin EXIF */ }
    return {};
  }

  async function datosDeFoto(archivo, imagen) {
    const exif = await exifDeArchivo(archivo);
    let loc = H.ultimaUbicacion;
    if (!loc) loc = await ubicacionActual();
    const lat = loc?.lat ?? exif.lat ?? null;
    const lon = loc?.lon ?? exif.lon ?? null;
    const alt = loc?.alt ?? exif.alt ?? null;
    return {
      lat, lon, alt, precision: loc?.precision ?? null,
      tomada_en: exif.tomada_en || ahoraISO(),
      ancho: imagen?.ancho || null, alto: imagen?.alto || null,
    };
  }

  // ---------------------------------------------------------------- texto y QR

  function envolver(ctx, texto, maxAncho) {
    const palabras = String(texto || '').split(/\s+/).filter(Boolean);
    const lineas = [];
    let actual = '';
    for (const palabra of palabras) {
      const prueba = actual ? actual + ' ' + palabra : palabra;
      if (!actual || ctx.measureText(prueba).width <= maxAncho) actual = prueba;
      else { lineas.push(actual); actual = palabra; }
    }
    if (actual) lineas.push(actual);
    return lineas;
  }

  function panelRedondeado(ctx, x, y, w, h, r, relleno) {
    const radio = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + radio, y);
    ctx.lineTo(x + w - radio, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + radio);
    ctx.lineTo(x + w, y + h - radio);
    ctx.quadraticCurveTo(x + w, y + h, x + w - radio, y + h);
    ctx.lineTo(x + radio, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - radio);
    ctx.lineTo(x, y + radio);
    ctx.quadraticCurveTo(x, y, x + radio, y);
    ctx.closePath();
    ctx.fillStyle = relleno;
    ctx.fill();
  }

  function dibujarQR(ctx, texto, x, y, lado) {
    try {
      if (typeof qrcode !== 'function') return;
      if (qrcode.stringToBytesFuncs && qrcode.stringToBytesFuncs['UTF-8']) qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
      const qr = qrcode(0, 'M');
      qr.addData(String(texto).slice(0, 600));
      qr.make();
      const n = qr.getModuleCount();
      const celda = lado / n;
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(x, y, lado, lado);
      ctx.fillStyle = '#0b1220';
      for (let fy = 0; fy < n; fy++) for (let fx = 0; fx < n; fx++) {
        if (qr.isDark(fy, fx)) ctx.fillRect(x + fx * celda, y + fy * celda, Math.ceil(celda), Math.ceil(celda));
      }
    } catch (e) { /* el sello sigue sin QR */ }
  }

  // ---------------------------------------------------------------- Foto sellada

  function selloFoto(canvas, meta) {
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height, S = Math.min(w, h), pad = S * 0.035;
    const compacto = meta.plantilla === 'compacto';
    const alto = compacto ? S * 0.18 : S * 0.34;
    const y = h - alto;
    const grad = ctx.createLinearGradient(0, y, 0, h);
    grad.addColorStop(0, 'rgba(8, 15, 28, 0.10)');
    grad.addColorStop(0.18, 'rgba(8, 15, 28, 0.78)');
    grad.addColorStop(1, 'rgba(8, 15, 28, 0.94)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, y, w, alto);
    ctx.fillStyle = '#f97316';
    ctx.fillRect(0, y, w, Math.max(2, S * 0.006));

    ctx.textBaseline = 'top';
    ctx.fillStyle = '#ffffff';
    const titulo = meta.actividad || meta.obra || 'Project Web';
    const lineas = [];
    lineas.push({ t: titulo, px: S * (compacto ? 0.038 : 0.042), peso: 700 });
    lineas.push({ t: [meta.obra, meta.codigo].filter(Boolean).join(' · '), px: S * 0.026, peso: 600 });
    lineas.push({ t: `${fechaHora(meta.fecha)} · ${coordsTexto(meta.lat, meta.lon)}`, px: S * 0.024, peso: 500 });
    if (meta.responsable) lineas.push({ t: `Responsable: ${meta.responsable}`, px: S * 0.023, peso: 500 });
    if (meta.nota) lineas.push({ t: meta.nota, px: S * 0.023, peso: 400 });
    let yy = y + S * 0.028;
    const maxAncho = w - pad * 2 - (compacto ? 0 : S * 0.20);
    for (const linea of lineas.slice(0, compacto ? 2 : 6)) {
      ctx.font = `${linea.peso} ${Math.round(linea.px)}px Inter, system-ui, sans-serif`;
      for (const l of envolver(ctx, linea.t, maxAncho).slice(0, compacto ? 2 : 3)) {
        if (yy + linea.px > h - pad) break;
        ctx.fillText(l, pad, yy);
        yy += linea.px * 1.22;
      }
    }
    if (!compacto) {
      const qrLado = Math.min(S * 0.20, alto - pad * 1.2);
      dibujarQR(ctx, meta.qr || `PROJECTWEB|${meta.obra}|${meta.fecha}|${meta.lat},${meta.lon}`, w - pad - qrLado, h - pad - qrLado, qrLado);
      ctx.font = `500 ${Math.round(S * 0.018)}px Inter, system-ui, sans-serif`;
      ctx.fillStyle = 'rgba(255,255,255,.65)';
      ctx.fillText('Project Web · solo en este teléfono', pad, h - S * 0.026);
    }
  }

  function vistaFotoSellada() {
    const p = proyecto();
    if (!state.usuario) return avisoCuenta();
    const acts = (proyectoActual()?.actividades || []).filter((a) => !Number(a.hito)).slice(0, 120);
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>Foto sellada</h2><p>Solo en este teléfono · se guarda en el álbum</p></div>
      </div>
      <div class="herr-form-card">
        <div class="herr-form-grid">
          <label>Obra<input type="text" value="${esc2(p?.nombre || 'Sin obra')}" readonly></label>
          <label>Actividad<select id="fs-actividad"><option value="">Sin actividad</option>${acts.map((a) => `<option value="${esc2(a.nombre || a.codigo)}">${esc2(a.codigo ? a.codigo + ' · ' : '')}${esc2(a.nombre)}</option>`).join('')}</select></label>
          <label>Plantilla<select id="fs-plantilla"><option value="completo">Completa (con QR)</option><option value="compacto">Compacta</option></select></label>
          <label>Responsable<input type="text" id="fs-responsable" value="${esc2(nombreUsuario())}" maxlength="80"></label>
        </div>
        <label class="herr-ancho">Nota<textarea id="fs-nota" rows="3" maxlength="300" placeholder="Qué muestra la foto, dónde y por qué…"></textarea></label>
        <div class="tarea-acciones">
          <button class="btn-principal" type="button" id="fs-camara"><svg class="ico"><use href="#i-camera"/></svg> Tomar foto</button>
          <button class="btn-mini" type="button" id="fs-galeria"><svg class="ico"><use href="#i-photo"/></svg> Elegir de galería</button>
        </div>
        <p class="parte-nota">El sello incluye obra, actividad, fecha, GPS, responsable y nota. Si no hay GPS se omite.</p>
      </div>
      <div id="fs-preview"></div>
      ${listaFotos('fotos', 'Fotos selladas')}
    `;
  }

  async function prepararFotoSellada(camara) {
    const archivo = await pedirArchivo(camara);
    if (!archivo) return;
    try {
      const cont = $('#fs-preview');
      if (cont) cont.innerHTML = '<p class="parte-nota">Preparando la foto…</p>';
      const img = await archivoAImagen(archivo);
      const datos = await datosDeFoto(archivo, img);
      const meta = {
        obra: proyecto()?.nombre || 'Project Web',
        codigo: proyecto()?.codigo || '',
        actividad: String($('#fs-actividad')?.value || '').trim(),
        responsable: String($('#fs-responsable')?.value || '').trim(),
        nota: String($('#fs-nota')?.value || '').trim(),
        plantilla: String($('#fs-plantilla')?.value || 'completo'),
        fecha: datos.tomada_en,
        lat: datos.lat, lon: datos.lon,
      };
      meta.qr = `PROJECTWEB|FOTO|${proyecto()?.id || 0}|${meta.fecha}|${meta.lat ?? ''},${meta.lon ?? ''}`;
      selloFoto(img.canvas, meta);
      if (cont) cont.innerHTML = '';
      const preview = document.createElement('div');
      preview.className = 'herr-preview';
      preview.innerHTML = '<div class="herr-preview-cab"><strong>Vista previa</strong><span class="chip chip-ia">Se guardará en el álbum</span></div>';
      const canvas = img.canvas;
      canvas.className = 'herr-canvas';
      preview.appendChild(canvas);
      const acciones = document.createElement('div');
      acciones.className = 'tarea-acciones';
      acciones.innerHTML = '<button class="btn-principal" type="button" id="fs-guardar">Guardar en álbum del teléfono</button><button class="btn-mini" type="button" id="fs-descartar">Descartar</button>';
      preview.appendChild(acciones);
      cont.appendChild(preview);
      $('#fs-descartar')?.addEventListener('click', () => cont.replaceChildren());
      $('#fs-guardar')?.addEventListener('click', async () => {
        const btn = $('#fs-guardar');
        if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }
        try {
          const guardado = await guardarImagen(canvas, { tipo: 'fotos', nombre: `foto-sellada-${Date.now()}.jpg`, album: ALBUM.fotos });
          await agregar('fotos', { ...meta, ...guardado, proyecto_id: Number(proyecto()?.id || 0), proyecto_nombre: proyecto()?.nombre || '' });
          cont.replaceChildren();
          toast(guardado.galeria ? 'Foto guardada en el álbum del teléfono.' : 'Foto guardada en la app, pero no se pudo copiar al álbum.', 5000);
          H.render();
        } catch (e) {
          toast(String(e?.message || e || 'No se pudo guardar la foto.'), 6000);
          if (btn) { btn.disabled = false; btn.textContent = 'Guardar en álbum del teléfono'; }
        }
      });
    } catch (e) {
      toast(String(e?.message || e || 'No se pudo preparar la foto.'), 6000);
      const cont = $('#fs-preview'); if (cont) cont.replaceChildren();
    }
  }

  // ---------------------------------------------------------------- listas comunes

  function listaFotos(tipo, titulo, limite = 20) {
    const items = (H.datos[tipo] || []).slice(0, limite);
    if (!items.length) return `<div class="herr-vacio">${titulo ? titulo + ': ' : ''}todavía no hay fotos.</div>`;
    return `
      <h3 class="herr-subtitulo">${esc2(titulo)} · ${items.length}</h3>
      <div class="herr-fotos">${items.map((f) => `
        <article class="herr-foto">
          <img src="${f.thumb || ''}" alt="${esc2(f.actividad || f.titulo || 'Foto')}" loading="lazy">
          <div><strong>${esc2(f.actividad || f.titulo || f.proyecto_nombre || 'Foto')}</strong><small>${fechaHora(f.creado_en)} · ${esc2(coordsTexto(f.lat, f.lon))}</small></div>
          <div class="herr-foto-acciones">
            <button class="icono-btn mini" type="button" data-herr-ver-foto="${esc2(f.archivo || '')}" data-herr-ver-titulo="${esc2(f.actividad || 'Foto')}" aria-label="Ver foto"><svg class="ico"><use href="#i-photo"/></svg></button>
            <button class="icono-btn mini danger" type="button" data-herr-borrar="${tipo}:${esc2(f.id)}" aria-label="Borrar"><svg class="ico"><use href="#i-trash"/></svg></button>
          </div>
        </article>`).join('')}</div>`;
  }

  function avisoCuenta() {
    return `<div class="herr-cab"><button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button><div><h2>Herramientas</h2><p>Solo en este teléfono</p></div></div>
      <div class="aviso"><svg class="ico"><use href="#i-shield"/></svg><p>Conecta tu cuenta para guardar las herramientas ligadas a tu usuario. Los datos se quedan en este teléfono.</p></div>`;
  }

  function avisoLocal() {
    return '<p class="herr-local"><svg class="ico"><use href="#i-shield"/></svg> Solo en este teléfono · no se sincroniza con la web</p>';
  }

  // ---------------------------------------------------------------- inicio / router

  H.inicio = async function () {
    H.vista = 'inicio';
    await cargarDatos();
    H.render();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  H.entrar = function () {
    if (H.vista === 'inicio') H.inicio();
    else H.render();
  };

  H.reiniciar = function () {
    H.vista = 'inicio';
    H.params = {};
    H.cargado = false;
    H.usuarioId = 0;
    H.comp = {};
    H.checkFoto = null;
    H.insp = null;
    H.croquis = null;
    H.medicion = null;
    H.datos = Object.fromEntries(Object.keys(CLAVES).map((k) => [k, []]));
  };

  H.abrir = function (vista, params = {}) {
    if (!state.usuario) { toast('Conecta tu cuenta para usar las herramientas.'); return; }
    H.abriendo = true;
    if (state.tab !== 'herramientas') irA('herramientas');
    H.abriendo = false;
    H.vista = vista;
    H.params = params;
    H.render();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  H.render = function () {
    const cont = $('#herr-contenido');
    if (!cont) return;
    if (!state.usuario) { cont.innerHTML = avisoCuenta(); return; }
    const vistas = {
      inicio: vistaInicio,
      fotos: vistaFotoSellada,
      rutas: vistaRutas,
      comparaciones: vistaComparador,
      rondines: vistaRondin,
      checkins: vistaCheckin,
      inspecciones: vistaInspeccion,
      croquis: vistaCroquis,
      mediciones: vistaMedicion,
    };
    const fn = vistas[H.vista] || vistaInicio;
    cont.innerHTML = fn();
    enlazarVista();
  };

  function vistaInicio() {
    const cards = Object.keys(TITULO).map((tipo) => `
      <button class="herr-card" type="button" data-herr="${tipo}">
        <span class="herr-card-ico"><svg class="ico"><use href="#${ICONO[tipo]}"/></svg></span>
        <span class="herr-card-txt"><strong>${esc2(TITULO[tipo])}</strong><small>${esc2(DESC[tipo])}</small></span>
        <span class="herr-card-n">${(H.datos[tipo] || []).length || ''}</span>
      </button>`).join('');
    return `
      <div class="herr-cab">
        <div><h2>Herramientas</h2><p>Para el residente y el equipo en obra</p></div>
        <span class="chip chip-ia">${esc2(nombreUsuario().split(' ')[0] || 'Campo')}</span>
      </div>
      ${avisoLocal()}
      <div class="herr-grid">${cards}</div>
      <div class="herr-pie">
        <p>Las fotos se guardan en el álbum del teléfono (Pictures/Project Web). Los registros quedan ocultos para otras cuentas.</p>
        <button class="btn-mini" type="button" id="herr-borrar-todo">Borrar mis datos locales</button>
      </div>`;
  }

  // ---------------------------------------------------------------- enlaces de vista

  function enlazarVista() {
    $('#herr-borrar-todo')?.addEventListener('click', async () => {
      if (!window.confirm('¿Borrar todas las herramientas de tu cuenta en este teléfono? Las fotos del álbum no se borran.')) return;
      for (const tipo of Object.keys(CLAVES)) {
        for (const reg of (H.datos[tipo] || [])) {
          for (const ruta of rutasDeRegistro(reg)) {
            try { await invokeTauri('borrar_foto_herramienta', { ruta }); } catch (e) {}
          }
        }
        await guardarTodos(tipo, (await leerTodos(tipo)).filter((r) => Number(r.usuario_id) !== uid()));
      }
      toast('Datos locales de tus herramientas borrados.');
      H.comp = {};
      H.checkFoto = null;
      H.insp = null;
      H.croquis = null;
      H.medicion = null;
      H.render();
    });
    if (H.vista === 'fotos') enlazarFotoSellada();
    if (H.vista === 'rutas') enlazarRutas();
    if (H.vista === 'comparaciones') enlazarComparador();
    if (H.vista === 'rondines') enlazarRondin();
    if (H.vista === 'checkins') enlazarCheckin();
    if (H.vista === 'inspecciones') enlazarInspeccion();
    if (H.vista === 'croquis') enlazarCroquis();
    if (H.vista === 'mediciones') enlazarMedicion();
  }

  function enlazarFotoSellada() {
    $('#fs-camara')?.addEventListener('click', () => prepararFotoSellada(true));
    $('#fs-galeria')?.addEventListener('click', () => prepararFotoSellada(false));
  }

  // ---------------------------------------------------------------- eventos globales

  document.addEventListener('click', async (e) => {
    const ir = e.target.closest('[data-herr]');
    if (ir) { H.abrir(ir.dataset.herr); return; }
    const volver = e.target.closest('[data-herr-volver]');
    if (volver) { H.inicio(); return; }
    const ver = e.target.closest('[data-herr-ver-foto]');
    if (ver) { verFoto(ver.dataset.herrVerFoto, ver.dataset.herrVerTitulo || 'Foto'); return; }
    const borrarBtn = e.target.closest('[data-herr-borrar]');
    if (borrarBtn) {
      const [tipo, id] = String(borrarBtn.dataset.herrBorrar || '').split(':');
      if (!tipo || !id) return;
      if (!window.confirm('¿Borrar este registro de tu cuenta? La foto ya guardada en el álbum no se borra.')) return;
      await borrar(tipo, id);
      toast('Registro borrado.');
      H.render();
    }
  });

  // ---------------------------------------------------------------- Rutas

  function rutaActual() {
    const id = H.params.rutaId;
    return (H.datos.rutas || []).find((r) => r.id === id) || null;
  }

  function distanciaRuta(puntos) {
    return (puntos || []).reduce((total, p, i) => {
      if (i === 0) return 0;
      return total + haversine(puntos[i - 1], p);
    }, 0);
  }

  function vistaRutas() {
    const r = rutaActual();
    if (r) return vistaRutaDetalle(r);
    const rutas = H.datos.rutas || [];
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>Rutas</h2><p>Recorrido con fotos y GPS</p></div>
        <span class="chip chip-ia">${rutas.length}</span>
      </div>
      ${avisoLocal()}
      <div class="herr-form-card">
        <label class="herr-ancho">Nueva ruta<input type="text" id="rt-nueva" maxlength="120" placeholder="Recorrido de obra · hoy"></label>
        <div class="tarea-acciones"><button class="btn-principal" type="button" id="rt-crear">Crear ruta</button></div>
      </div>
      ${rutas.length ? `<div class="herr-lista">${rutas.map((r) => {
        const n = (r.puntos || []).length;
        const d = distanciaRuta(r.puntos);
        const portada = (r.puntos || []).find((p) => p.thumb)?.thumb || r.resumen_thumb || '';
        return `<button class="herr-item" type="button" data-herr-ruta="${esc2(r.id)}">
          ${portada ? `<img src="${portada}" alt="">` : '<span class="herr-item-ico"><svg class="ico"><use href="#i-route"/></svg></span>'}
          <span><strong>${esc2(r.titulo)}</strong><small>${n} punto${n === 1 ? '' : 's'} · ${distanciaTexto(d)} · ${esc2(fechaHora(r.creado_en))}</small></span>
          <svg class="ico"><use href="#i-arrow"/></svg>
        </button>`;
      }).join('')}</div>` : '<div class="herr-vacio">Todavía no hay rutas. Crea una y agrega fotos con GPS.</div>'}
    `;
  }

  function vistaRutaDetalle(r) {
    const puntos = r.puntos || [];
    const d = distanciaRuta(puntos);
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>${esc2(r.titulo)}</h2><p>${puntos.length} punto${puntos.length === 1 ? '' : 's'} · ${distanciaTexto(d)}${r.cerrada ? ' · cerrada' : ''}</p></div>
        <button class="icono-btn danger" type="button" id="rt-borrar" aria-label="Borrar ruta"><svg class="ico"><use href="#i-trash"/></svg></button>
      </div>
      ${avisoLocal()}
      <div class="tarea-acciones">
        <button class="btn-principal" type="button" id="rt-camara"><svg class="ico"><use href="#i-camera"/></svg> Agregar foto</button>
        <button class="btn-mini" type="button" id="rt-galeria"><svg class="ico"><use href="#i-photo"/></svg> De galería</button>
        <button class="btn-mini" type="button" id="rt-mapa-guardar">Guardar croquis</button>
        ${puntos.length ? `<button class="btn-mini" type="button" id="rt-cerrar">${r.cerrada ? 'Reabrir' : 'Finalizar ruta'}</button>` : ''}
      </div>
      <div class="herr-mapa" id="rt-mapa"></div>
      <div class="herr-lista">${puntos.length ? puntos.map((p, i) => `
        <article class="herr-item estatico">
          ${p.thumb ? `<img src="${p.thumb}" alt="">` : '<span class="herr-item-ico"><svg class="ico"><use href="#i-photo"/></svg></span>'}
          <span><strong>${i + 1}. ${esc2(p.nota || 'Punto ' + (i + 1))}</strong><small>${esc2(coordsTexto(p.lat, p.lon))} · ${esc2(fechaHora(p.tomada_en))}${i ? ' · +' + distanciaTexto(p.distancia_m) : ''}</small></span>
          <button class="icono-btn mini" type="button" data-herr-ver-foto="${esc2(p.archivo || '')}" data-herr-ver-titulo="Punto ${i + 1}" aria-label="Ver foto"><svg class="ico"><use href="#i-photo"/></svg></button>
        </article>`).join('') : '<div class="herr-vacio">Agrega la primera foto. Si la foto trae GPS o el teléfono lo permite, se guarda la ubicación.</div>'}</div>
      <p class="parte-nota">Las fotos se guardan en Pictures/Project Web/Rutas. El croquis también se puede guardar en el álbum.</p>
    `;
  }

  function enlazarRutas() {
    const r = rutaActual();
    if (!r) {
      $('#rt-crear')?.addEventListener('click', async () => {
        const titulo = String($('#rt-nueva')?.value || '').trim();
        if (!titulo) { toast('Escribe el nombre de la ruta.'); return; }
        const nueva = await agregar('rutas', {
          titulo,
          proyecto_id: Number(proyecto()?.id || 0),
          proyecto_nombre: proyecto()?.nombre || '',
          puntos: [],
          cerrada: false,
        });
        H.params = { rutaId: nueva.id };
        H.render();
      });
      $$('[data-herr-ruta]').forEach((b) => b.addEventListener('click', () => {
        H.params = { rutaId: b.dataset.herrRuta };
        H.render();
      }));
      return;
    }
    pintarMapaRuta(r);
    $('#rt-camara')?.addEventListener('click', () => agregarPuntoRuta(r, true));
    $('#rt-galeria')?.addEventListener('click', () => agregarPuntoRuta(r, false));
    $('#rt-mapa-guardar')?.addEventListener('click', () => guardarMapaRuta(r, false));
    $('#rt-cerrar')?.addEventListener('click', () => guardarMapaRuta(r, !r.cerrada));
    $('#rt-borrar')?.addEventListener('click', async () => {
      if (!window.confirm('¿Borrar esta ruta de tu cuenta? Las fotos del álbum no se borran.')) return;
      await borrar('rutas', r.id);
      H.params = {};
      toast('Ruta borrada.');
      H.render();
    });
  }

  async function agregarPuntoRuta(r, camara) {
    const archivo = await pedirArchivo(camara);
    if (!archivo) return;
    try {
      toast('Leyendo foto y ubicación…', 5000);
      const img = await archivoAImagen(archivo);
      const datos = await datosDeFoto(archivo, img);
      const guardado = await guardarImagen(img.canvas, { tipo: 'rutas', album: ALBUM.rutas, nombre: `ruta-${Date.now()}.jpg` });
      const punto = {
        id: nuevoId(),
        archivo: guardado.archivo,
        thumb: guardado.thumb,
        lat: datos.lat, lon: datos.lon, alt: datos.alt,
        tomada_en: datos.tomada_en,
        nota: '',
        distancia_m: 0,
      };
      const prev = (r.puntos || []).slice(-1)[0];
      punto.distancia_m = prev ? haversine(prev, punto) : 0;
      const nueva = { ...r, puntos: [...(r.puntos || []), punto] };
      await actualizar('rutas', nueva);
      toast(guardado.galeria ? 'Foto agregada a la ruta y al álbum.' : 'Foto agregada a la ruta.', 4000);
      H.render();
    } catch (e) {
      toast(String(e?.message || e || 'No se pudo agregar la foto.'), 6000);
    }
  }

  function pintarMapaRuta(r) {
    const cont = $('#rt-mapa');
    if (!cont) return;
    const canvas = mapaRutaCanvas(r, false);
    canvas.className = 'herr-canvas';
    cont.replaceChildren(canvas);
  }

  function mapaRutaCanvas(r, resumen) {
    const W = 1200, H = resumen ? 900 : 700;
    const cab = resumen ? 126 : 0;
    const pie = resumen ? 120 : 0;
    const canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#0b1220'; ctx.fillRect(0, 0, W, H);
    const area = { x: 50, y: 34 + cab, w: W - 100, h: H - 68 - cab - pie };
    // rejilla
    ctx.strokeStyle = 'rgba(148,163,184,.12)'; ctx.lineWidth = 1;
    for (let x = area.x; x < area.x + area.w; x += 60) { ctx.beginPath(); ctx.moveTo(x, area.y); ctx.lineTo(x, area.y + area.h); ctx.stroke(); }
    for (let y = area.y; y < area.y + area.h; y += 60) { ctx.beginPath(); ctx.moveTo(area.x, y); ctx.lineTo(area.x + area.w, y); ctx.stroke(); }
    const puntos = (r.puntos || []).filter((p) => p.lat != null && p.lon != null);
    if (resumen) {
      ctx.fillStyle = '#f97316'; ctx.fillRect(0, 0, W, 6);
      ctx.fillStyle = '#fff'; ctx.font = '700 46px Inter, system-ui, sans-serif'; ctx.textBaseline = 'top';
      ctx.fillText(r.titulo || 'Ruta', 50, 30);
      ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.font = '500 26px Inter, system-ui, sans-serif';
      ctx.fillText(`${(r.puntos || []).length} puntos · ${distanciaTexto(distanciaRuta(r.puntos))} · ${fechaHora(r.creado_en)}`, 50, 86);
    }
    if (!puntos.length) {
      ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.font = '500 30px Inter, system-ui, sans-serif'; ctx.textAlign = 'center';
      ctx.fillText('Sin puntos con GPS todavía', W / 2, area.y + area.h / 2);
      ctx.textAlign = 'left';
      return canvas;
    }
    const lats = puntos.map((p) => Number(p.lat)), lons = puntos.map((p) => Number(p.lon));
    let latMin = Math.min(...lats), latMax = Math.max(...lats), lonMin = Math.min(...lons), lonMax = Math.max(...lons);
    if (latMax - latMin < 1e-7) { latMin -= 1e-6; latMax += 1e-6; }
    if (lonMax - lonMin < 1e-7) { lonMin -= 1e-6; lonMax += 1e-6; }
    const midLat = ((latMin + latMax) / 2) * Math.PI / 180;
    const xs = puntos.map((p) => (Number(p.lon) - lonMin) * Math.cos(midLat));
    const ys = puntos.map((p) => (latMax - Number(p.lat)));
    const maxX = Math.max(...xs), maxY = Math.max(...ys);
    const margen = 54;
    const esc = Math.min((area.w - margen * 2) / (maxX || 1), (area.h - margen * 2) / (maxY || 1));
    const offX = area.x + margen + (area.w - margen * 2 - maxX * esc) / 2;
    const offY = area.y + margen + (area.h - margen * 2 - maxY * esc) / 2;
    const px = (i) => offX + xs[i] * esc;
    const py = (i) => offY + ys[i] * esc;
    ctx.strokeStyle = '#f97316'; ctx.lineWidth = 8; ctx.lineJoin = 'round'; ctx.lineCap = 'round';
    ctx.beginPath();
    puntos.forEach((p, i) => { if (i) ctx.lineTo(px(i), py(i)); else ctx.moveTo(px(i), py(i)); });
    ctx.stroke();
    puntos.forEach((p, i) => {
      ctx.beginPath(); ctx.arc(px(i), py(i), 20, 0, Math.PI * 2);
      ctx.fillStyle = i === 0 ? '#22c55e' : (i === puntos.length - 1 ? '#ef4444' : '#f97316'); ctx.fill();
      ctx.strokeStyle = '#fff'; ctx.lineWidth = 4; ctx.stroke();
      ctx.fillStyle = '#fff'; ctx.font = '700 20px Inter, system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(String(i + 1), px(i), py(i));
    });
    ctx.textAlign = 'left'; ctx.textBaseline = 'top';
    // norte
    ctx.strokeStyle = 'rgba(255,255,255,.8)'; ctx.fillStyle = 'rgba(255,255,255,.8)'; ctx.lineWidth = 3;
    const nx = area.x + area.w - 46, ny = area.y + 46;
    ctx.beginPath(); ctx.moveTo(nx, ny + 30); ctx.lineTo(nx, ny - 24); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(nx, ny - 34); ctx.lineTo(nx - 10, ny - 14); ctx.lineTo(nx + 10, ny - 14); ctx.closePath(); ctx.fill();
    ctx.font = '700 18px Inter, system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('N', nx, ny + 36);
    // escala
    const mPorPx = ((lonMax - lonMin) * 111320 * Math.cos(midLat)) / Math.max(1, maxX * esc);
    const objetivo = mPorPx * area.w * 0.24;
    const pot = Math.pow(10, Math.floor(Math.log10(Math.max(1, objetivo))));
    const opciones = [1, 2, 5, 10].map((n) => n * pot);
    const metros = opciones.reduce((a, b) => Math.abs(b - objetivo) < Math.abs(a - objetivo) ? b : a, opciones[0]);
    const largo = metros / Math.max(1e-9, mPorPx);
    ctx.strokeStyle = 'rgba(255,255,255,.85)'; ctx.lineWidth = 4;
    ctx.beginPath(); ctx.moveTo(area.x + 24, area.y + area.h - 24); ctx.lineTo(area.x + 24 + largo, area.y + area.h - 24); ctx.stroke();
    ctx.font = '500 18px Inter, system-ui, sans-serif'; ctx.textAlign = 'left'; ctx.fillStyle = 'rgba(255,255,255,.8)';
    ctx.fillText(distanciaTexto(metros), area.x + 24, area.y + area.h - 50);
    ctx.textAlign = 'left';
    if (resumen) {
      ctx.fillStyle = 'rgba(255,255,255,.65)'; ctx.font = '500 22px Inter, system-ui, sans-serif';
      ctx.fillText('Project Web · solo en este teléfono', 50, H - 52);
    }
    return canvas;
  }

  async function guardarMapaRuta(r, cerrar) {
    try {
      const canvas = mapaRutaCanvas(r, true);
      const guardado = await guardarImagen(canvas, { tipo: 'rutas', album: ALBUM.rutas, nombre: `ruta-croquis-${Date.now()}.jpg` });
      const nueva = { ...r, resumen_archivo: guardado.archivo, resumen_thumb: guardado.thumb, cerrada: !!cerrar };
      await actualizar('rutas', nueva);
      toast(guardado.galeria ? 'Croquis guardado en el álbum del teléfono.' : 'Croquis guardado en la app.', 5000);
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo guardar el croquis.'), 6000); }
  }

  // ---------------------------------------------------------------- Comparador

  function vistaComparador() {
    const items = H.datos.comparaciones || [];
    const listo = H.comp?.antes && H.comp?.despues;
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>Comparador</h2><p>Antes y después en una sola imagen</p></div>
      </div>
      ${avisoLocal()}
      <div class="herr-comp-slots">
        ${['antes', 'despues'].map((rol) => `
          <div class="herr-comp-slot">
            <button class="herr-comp-boton" type="button" data-comp-rol="${rol}" data-comp-camara="1">
              ${H.comp?.[rol]?.thumb ? `<img src="${H.comp[rol].thumb}" alt="">` : `<svg class="ico"><use href="#i-camera"/></svg>`}
              <strong>${rol === 'antes' ? 'Antes' : 'Después'}</strong>
              <small>${H.comp?.[rol] ? 'Cambiar' : 'Tomar o elegir'}</small>
            </button>
            <button class="btn-mini" type="button" data-comp-rol="${rol}" data-comp-camara="0">Elegir de galería</button>
          </div>`).join('')}
      </div>
      <div class="tarea-acciones">
        <button class="btn-principal" type="button" id="comp-componer" ${listo ? '' : 'disabled'}>Guardar comparación en álbum</button>
      </div>
      <div id="comp-preview"></div>
      ${items.length ? `<h3 class="herr-subtitulo">Comparaciones · ${items.length}</h3><div class="herr-fotos">${items.map((c) => `
        <article class="herr-foto"><img src="${c.thumb || ''}" alt=""><div><strong>${esc2(c.titulo || 'Comparación')}</strong><small>${esc2(fechaHora(c.creado_en))}</small></div>
        <div class="herr-foto-acciones"><button class="icono-btn mini" type="button" data-herr-ver-foto="${esc2(c.archivo || '')}" data-herr-ver-titulo="Comparación" aria-label="Ver"><svg class="ico"><use href="#i-photo"/></svg></button>
        <button class="icono-btn mini danger" type="button" data-herr-borrar="comparaciones:${esc2(c.id)}" aria-label="Borrar"><svg class="ico"><use href="#i-trash"/></svg></button></div></article>`).join('')}</div>` : ''}
    `;
  }

  function enlazarComparador() {
    $$('[data-comp-rol]').forEach((b) => b.addEventListener('click', () => prepararComparador(b.dataset.compRol, b.dataset.compCamara === '1')));
    $('#comp-componer')?.addEventListener('click', componerComparacion);
  }

  async function prepararComparador(rol, camara) {
    const archivo = await pedirArchivo(camara);
    if (!archivo) return;
    try {
      const img = await archivoAImagen(archivo, 2400);
      const guardado = await guardarImagen(img.canvas, { tipo: 'comparaciones', aGaleria: false, nombre: `comparador-${rol}-${Date.now()}.jpg` });
      H.comp = { ...(H.comp || {}), [rol]: { archivo: guardado.archivo, thumb: guardado.thumb, ancho: img.ancho, alto: img.alto } };
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo preparar la foto.'), 6000); }
  }

  async function componerComparacion() {
    if (!H.comp?.antes || !H.comp?.despues) return;
    try {
      const [antes, despues] = await Promise.all([
        cargarImagen(await urlDeArchivo(H.comp.antes.archivo)),
        cargarImagen(await urlDeArchivo(H.comp.despues.archivo)),
      ]);
      const alto = 900, gap = 18, cab = 120, pie = 90;
      const wa = Math.round(antes.width * (alto / antes.height));
      const wd = Math.round(despues.width * (alto / despues.height));
      const canvas = document.createElement('canvas');
      canvas.width = wa + wd + gap * 3;
      canvas.height = alto + cab + pie;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#0b1220'; ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = '#f97316'; ctx.fillRect(0, 0, canvas.width, 6);
      ctx.fillStyle = '#fff'; ctx.font = '700 42px Inter, system-ui, sans-serif'; ctx.textBaseline = 'top';
      ctx.fillText('Antes / después', gap, 28);
      ctx.fillStyle = 'rgba(255,255,255,.65)'; ctx.font = '500 22px Inter, system-ui, sans-serif';
      ctx.fillText(`${proyecto()?.nombre || ''} · ${fechaHora(ahoraISO())}`, gap, 80);
      ctx.drawImage(antes, gap, cab, wa, alto);
      ctx.drawImage(despues, gap * 2 + wa, cab, wd, alto);
      ctx.fillStyle = 'rgba(255,255,255,.92)'; ctx.font = '700 28px Inter, system-ui, sans-serif';
      ctx.fillText('ANTES', gap + 16, cab + 18);
      ctx.fillText('DESPUÉS', gap * 2 + wa + 16, cab + 18);
      ctx.fillStyle = '#f97316'; ctx.fillRect(gap + wa + gap / 2 - 2, cab, 4, alto);
      ctx.fillStyle = 'rgba(255,255,255,.6)'; ctx.font = '500 20px Inter, system-ui, sans-serif';
      ctx.fillText('Project Web · solo en este teléfono', gap, cab + alto + 30);
      const guardado = await guardarImagen(canvas, { tipo: 'comparaciones', album: ALBUM.comparaciones, nombre: `comparacion-${Date.now()}.jpg` });
      await agregar('comparaciones', {
        titulo: 'Antes / después',
        antesarchivo: H.comp.antes.archivo,
        despuesarchivo: H.comp.despues.archivo,
        ...guardado,
        proyecto_id: Number(proyecto()?.id || 0),
      });
      toast(guardado.galeria ? 'Comparación guardada en el álbum.' : 'Comparación guardada en la app.', 5000);
      H.comp = {};
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo componer la comparación.'), 6000); }
  }

  // ---------------------------------------------------------------- Rondín GPS

  function rondinActual() {
    return (H.datos.rondines || []).find((r) => r.id === H.params.rondinId) || null;
  }

  function vistaRondin() {
    const r = rondinActual();
    if (r) return vistaRondinDetalle(r);
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>Rondín GPS</h2><p>Checkpoints y llegadas con hora</p></div>
      </div>
      ${avisoLocal()}
      <div class="herr-form-card">
        <label class="herr-ancho">Nuevo rondín<input type="text" id="rd-nuevo" maxlength="120" placeholder="Rondín de seguridad · hoy"></label>
        <div class="tarea-acciones"><button class="btn-principal" type="button" id="rd-crear">Crear rondín</button></div>
      </div>
      ${(H.datos.rondines || []).length ? `<div class="herr-lista">${H.datos.rondines.map((r) => {
        const hechos = (r.checkpoints || []).filter((c) => c.visitado_en).length;
        return `<button class="herr-item" type="button" data-herr-rondin="${esc2(r.id)}">
          <span class="herr-item-ico"><svg class="ico"><use href="#i-pin"/></svg></span>
          <span><strong>${esc2(r.titulo)}</strong><small>${hechos}/${(r.checkpoints || []).length} checkpoints · ${esc2(fechaHora(r.creado_en))}</small></span>
          <svg class="ico"><use href="#i-arrow"/></svg>
        </button>`;
      }).join('')}</div>` : '<div class="herr-vacio">Todavía no hay rondines. Crea uno y agrega checkpoints en la obra.</div>'}
    `;
  }

  function vistaRondinDetalle(r) {
    const cs = r.checkpoints || [];
    const hechos = cs.filter((c) => c.visitado_en).length;
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>${esc2(r.titulo)}</h2><p>${hechos}/${cs.length} visitados${r.cerrado ? ' · cerrado' : ''}</p></div>
        <button class="icono-btn danger" type="button" id="rd-borrar" aria-label="Borrar rondín"><svg class="ico"><use href="#i-trash"/></svg></button>
      </div>
      ${avisoLocal()}
      <div class="herr-form-card">
        <div class="herr-form-grid">
          <label>Nombre del checkpoint<input type="text" id="rd-nombre" maxlength="100" placeholder="Ej. Frente norte"></label>
          <label>Ubicación<input type="text" id="rd-coords" placeholder="Se toma del GPS al agregar" readonly></label>
        </div>
        <div class="tarea-acciones">
          <button class="btn-principal" type="button" id="rd-agregar"><svg class="ico"><use href="#i-gps"/></svg> Agregar con mi ubicación</button>
          <button class="btn-mini" type="button" id="rd-agregar-sin">Agregar sin GPS</button>
          <button class="btn-mini" type="button" id="rd-resumen">Guardar resumen en álbum</button>
        </div>
      </div>
      <div class="herr-lista">${cs.length ? cs.map((c, i) => `
        <article class="herr-item estatico">
          <span class="herr-check ${c.visitado_en ? 'ok' : ''}">${c.visitado_en ? '✓' : i + 1}</span>
          <span><strong>${esc2(c.nombre)}</strong><small>${c.visitado_en ? 'Visitado ' + esc2(fechaHora(c.visitado_en)) : (c.lat != null ? 'Pendiente · ' + esc2(coordsTexto(c.lat, c.lon)) : 'Pendiente · sin GPS')}</small>
          ${c.archivo ? `<img class="herr-mini" src="${c.thumb || ''}" alt="">` : ''}</span>
          <div class="herr-acciones-col">
            <button class="btn-mini" type="button" data-rd-visitar="${esc2(c.id)}" ${c.visitado_en ? 'disabled' : ''}>Llegué</button>
            <button class="icono-btn mini" type="button" data-rd-foto="${esc2(c.id)}" aria-label="Foto"><svg class="ico"><use href="#i-camera"/></svg></button>
          </div>
        </article>`).join('') : '<div class="herr-vacio">Agrega el primer checkpoint con tu ubicación actual.</div>'}</div>
      <p class="parte-nota">El rondín y las fotos se quedan en este teléfono. La llegada se marca con hora y GPS; si estás lejos te pedirá confirmación.</p>
    `;
  }

  function enlazarRondin() {
    const r = rondinActual();
    if (!r) {
      $('#rd-crear')?.addEventListener('click', async () => {
        const titulo = String($('#rd-nuevo')?.value || '').trim();
        if (!titulo) { toast('Escribe el nombre del rondín.'); return; }
        const nuevo = await agregar('rondines', { titulo, proyecto_id: Number(proyecto()?.id || 0), proyecto_nombre: proyecto()?.nombre || '', checkpoints: [], cerrado: false });
        H.params = { rondinId: nuevo.id };
        H.render();
      });
      $$('[data-herr-rondin]').forEach((b) => b.addEventListener('click', () => { H.params = { rondinId: b.dataset.herrRondin }; H.render(); }));
      return;
    }
    $('#rd-borrar')?.addEventListener('click', async () => {
      if (!window.confirm('¿Borrar este rondín de tu cuenta? Las fotos del álbum no se borran.')) return;
      await borrar('rondines', r.id); H.params = {}; toast('Rondín borrado.'); H.render();
    });
    $('#rd-agregar')?.addEventListener('click', () => agregarCheckpoint(r, true));
    $('#rd-agregar-sin')?.addEventListener('click', () => agregarCheckpoint(r, false));
    $('#rd-resumen')?.addEventListener('click', () => guardarResumenRondin(r));
    $$('[data-rd-visitar]').forEach((b) => b.addEventListener('click', () => registrarLlegada(r, b.dataset.rdVisitar)));
    $$('[data-rd-foto]').forEach((b) => b.addEventListener('click', () => fotoCheckpoint(r, b.dataset.rdFoto)));
  }

  async function agregarCheckpoint(r, conGps) {
    const nombre = String($('#rd-nombre')?.value || '').trim();
    if (!nombre) { toast('Escribe el nombre del checkpoint.'); return; }
    let loc = null;
    if (conGps) { toast('Tomando ubicación…', 5000); loc = await ubicacionActual(); }
    if (conGps && !loc) toast('No se pudo obtener el GPS; se agrega sin ubicación.', 5000);
    const cp = { id: nuevoId(), nombre, lat: loc?.lat ?? null, lon: loc?.lon ?? null, visitado_en: null, nota: '', archivo: '', thumb: '' };
    await actualizar('rondines', { ...r, checkpoints: [...(r.checkpoints || []), cp] });
    const input = $('#rd-nombre'); if (input) input.value = '';
    toast('Checkpoint agregado.');
    H.render();
  }

  async function registrarLlegada(r, cpId) {
    const cp = (r.checkpoints || []).find((c) => c.id === cpId);
    if (!cp || cp.visitado_en) return;
    toast('Comprobando ubicación…', 5000);
    const loc = await ubicacionActual();
    let distancia = null;
    if (loc && cp.lat != null && cp.lon != null) distancia = haversine(loc, cp);
    if (distancia != null && distancia > 150 && !window.confirm(`Estás a ${distanciaTexto(distancia)} del checkpoint. ¿Marcar la llegada de todos modos?`)) return;
    const ahora = ahoraISO();
    const nuevo = { ...cp, visitado_en: ahora, llegada_lat: loc?.lat ?? null, llegada_lon: loc?.lon ?? null, distancia_m: distancia };
    await actualizar('rondines', { ...r, checkpoints: (r.checkpoints || []).map((c) => c.id === cpId ? nuevo : c) });
    toast('Llegada registrada.');
    H.render();
  }

  async function fotoCheckpoint(r, cpId) {
    const archivo = await pedirArchivo(true);
    if (!archivo) return;
    try {
      const img = await archivoAImagen(archivo);
      const guardado = await guardarImagen(img.canvas, { tipo: 'rondines', album: ALBUM.rondines, nombre: `rondin-${Date.now()}.jpg` });
      const nuevo = { ...r, checkpoints: (r.checkpoints || []).map((c) => c.id === cpId ? { ...c, archivo: guardado.archivo, thumb: guardado.thumb } : c) };
      await actualizar('rondines', nuevo);
      toast(guardado.galeria ? 'Foto guardada en el álbum.' : 'Foto guardada en la app.', 4000);
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo guardar la foto.'), 6000); }
  }

  async function guardarResumenRondin(r) {
    try {
      const W = 1200, cab = 150, fila = 92, pie = 100;
      const Hc = cab + Math.max(1, (r.checkpoints || []).length) * fila + pie;
      const canvas = document.createElement('canvas'); canvas.width = W; canvas.height = Hc;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#0b1220'; ctx.fillRect(0, 0, W, Hc);
      ctx.fillStyle = '#f97316'; ctx.fillRect(0, 0, W, 6);
      ctx.fillStyle = '#fff'; ctx.font = '700 44px Inter, system-ui, sans-serif'; ctx.textBaseline = 'top';
      ctx.fillText(r.titulo || 'Rondín', 50, 34);
      ctx.fillStyle = 'rgba(255,255,255,.65)'; ctx.font = '500 24px Inter, system-ui, sans-serif';
      ctx.fillText(`${proyecto()?.nombre || ''} · ${fechaHora(ahoraISO())}`, 50, 92);
      (r.checkpoints || []).forEach((c, i) => {
        const y = cab + i * fila;
        ctx.fillStyle = c.visitado_en ? 'rgba(34,197,94,.16)' : 'rgba(148,163,184,.10)';
        ctx.fillRect(30, y, W - 60, fila - 12);
        ctx.fillStyle = c.visitado_en ? '#22c55e' : '#94a3b8'; ctx.font = '700 30px Inter, system-ui, sans-serif';
        ctx.fillText(c.visitado_en ? '✓' : String(i + 1), 52, y + 24);
        ctx.fillStyle = '#fff'; ctx.font = '600 26px Inter, system-ui, sans-serif'; ctx.fillText(c.nombre || `Checkpoint ${i + 1}`, 110, y + 16);
        ctx.fillStyle = 'rgba(255,255,255,.62)'; ctx.font = '500 20px Inter, system-ui, sans-serif';
        ctx.fillText(c.visitado_en ? `Llegada ${fechaHora(c.visitado_en)}${c.distancia_m != null ? ' · ' + distanciaTexto(c.distancia_m) : ''}` : 'Pendiente', 110, y + 50);
      });
      ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.font = '500 20px Inter, system-ui, sans-serif'; ctx.fillText('Project Web · solo en este teléfono', 50, Hc - 52);
      const guardado = await guardarImagen(canvas, { tipo: 'rondines', album: ALBUM.rondines, nombre: `rondin-resumen-${Date.now()}.jpg` });
      await actualizar('rondines', { ...r, resumen_archivo: guardado.archivo, resumen_thumb: guardado.thumb, cerrado: true });
      toast(guardado.galeria ? 'Resumen del rondín guardado en el álbum.' : 'Resumen guardado en la app.', 5000);
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo guardar el resumen.'), 6000); }
  }

  // ---------------------------------------------------------------- Check-in

  async function logoPW() {
    try { return await cargarImagen('assets/projectweb.svg'); } catch (e) { return null; }
  }

  function recortarImagen(ctx, img, x, y, w, h, r) {
    const radio = Math.min(r, w / 2, h / 2);
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(x + radio, y);
    ctx.lineTo(x + w - radio, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + radio);
    ctx.lineTo(x + w, y + h - radio);
    ctx.quadraticCurveTo(x + w, y + h, x + w - radio, y + h);
    ctx.lineTo(x + radio, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - radio);
    ctx.lineTo(x, y + radio);
    ctx.quadraticCurveTo(x, y, x + radio, y);
    ctx.closePath();
    ctx.clip();
    ctx.drawImage(img, x, y, w, h);
    ctx.restore();
  }

  function vistaCheckin() {
    const items = H.datos.checkins || [];
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>Check-in de residente</h2><p>Presencia en obra con hora, GPS y selfie</p></div>
      </div>
      ${avisoLocal()}
      <div class="herr-form-card">
        <div class="herr-check-preview" id="ck-preview">${H.checkFoto?.thumb ? `<img src="${H.checkFoto.thumb}" alt="Selfie">` : '<span class="herr-check-vacio"><svg class="ico"><use href="#i-user-check"/></svg><small>Selfie opcional</small></span>'}</div>
        <div class="tarea-acciones">
          <button class="btn-mini" type="button" id="ck-selfie"><svg class="ico"><use href="#i-camera"/></svg> ${H.checkFoto ? 'Cambiar selfie' : 'Tomar selfie'}</button>
          <button class="btn-principal" type="button" id="ck-registrar">Registrar check-in</button>
        </div>
        <label class="herr-ancho">Nota<textarea id="ck-nota" rows="3" maxlength="300" placeholder="Motivo de la visita, área, persona que atendió…"></textarea></label>
        <p class="parte-nota">Se guarda una tarjeta con tu nombre, la obra, la fecha, la ubicación y la selfie en Pictures/Project Web/Check-in.</p>
      </div>
      ${items.length ? `<h3 class="herr-subtitulo">Check-ins · ${items.length}</h3><div class="herr-lista">${items.map((c) => `
        <article class="herr-item estatico">
          ${c.thumb ? `<img src="${c.thumb}" alt="">` : '<span class="herr-item-ico"><svg class="ico"><use href="#i-user-check"/></svg></span>'}
          <span><strong>${esc2(c.proyecto_nombre || 'Check-in')}</strong><small>${esc2(fechaHora(c.creado_en))} · ${esc2(coordsTexto(c.lat, c.lon))}</small></span>
          <button class="icono-btn mini" type="button" data-herr-ver-foto="${esc2(c.archivo || '')}" data-herr-ver-titulo="Check-in" aria-label="Ver"><svg class="ico"><use href="#i-photo"/></svg></button>
        </article>`).join('')}</div>` : ''}
    `;
  }

  function enlazarCheckin() {
    $('#ck-selfie')?.addEventListener('click', async () => {
      const archivo = await pedirArchivo(true);
      if (!archivo) return;
      try {
        const img = await archivoAImagen(archivo, 1400);
        H.checkFoto = { canvas: img.canvas, thumb: miniatura(img.canvas, 300) };
        H.render();
      } catch (e) { toast(String(e?.message || e || 'No se pudo leer la selfie.'), 5000); }
    });
    $('#ck-registrar')?.addEventListener('click', registrarCheckin);
  }

  async function registrarCheckin() {
    try {
      toast('Tomando ubicación…', 5000);
      const loc = await ubicacionActual();
      const nota = String($('#ck-nota')?.value || '').trim();
      const W = 1080, Hc = 1350;
      const canvas = document.createElement('canvas'); canvas.width = W; canvas.height = Hc;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#0b1220'; ctx.fillRect(0, 0, W, Hc);
      ctx.fillStyle = '#f97316'; ctx.fillRect(0, 0, W, 10);
      const logo = await logoPW();
      if (logo) ctx.drawImage(logo, 60, 52, 110, 110);
      ctx.fillStyle = '#fff'; ctx.font = '700 52px Inter, system-ui, sans-serif'; ctx.textBaseline = 'top';
      ctx.fillText('Check-in de residente', 195, 58);
      ctx.fillStyle = 'rgba(255,255,255,.65)'; ctx.font = '500 26px Inter, system-ui, sans-serif';
      ctx.fillText(proyecto()?.nombre || 'Project Web', 195, 122);
      const lado = 620, xf = (W - lado) / 2, yf = 230;
      if (H.checkFoto?.canvas) recortarImagen(ctx, H.checkFoto.canvas, xf, yf, lado, lado, 36);
      else {
        ctx.fillStyle = 'rgba(148,163,184,.12)'; panelRedondeado(ctx, xf, yf, lado, lado, 36, 'rgba(148,163,184,.12)');
        ctx.fillStyle = 'rgba(255,255,255,.45)'; ctx.font = '500 28px Inter, system-ui, sans-serif'; ctx.textAlign = 'center';
        ctx.fillText('Sin selfie', W / 2, yf + lado / 2);
        ctx.textAlign = 'left';
      }
      let yy = yf + lado + 54;
      ctx.fillStyle = '#fff'; ctx.font = '700 40px Inter, system-ui, sans-serif';
      ctx.fillText(nombreUsuario(), 70, yy); yy += 58;
      ctx.fillStyle = 'rgba(255,255,255,.75)'; ctx.font = '500 28px Inter, system-ui, sans-serif';
      ctx.fillText(fechaHora(ahoraISO()), 70, yy); yy += 42;
      ctx.fillText(loc ? coordsTexto(loc.lat, loc.lon) : 'Sin GPS', 70, yy); yy += 54;
      if (nota) {
        ctx.fillStyle = 'rgba(255,255,255,.62)'; ctx.font = '400 27px Inter, system-ui, sans-serif';
        for (const l of envolver(ctx, nota, W - 140).slice(0, 4)) { ctx.fillText(l, 70, yy); yy += 38; }
      }
      ctx.fillStyle = 'rgba(255,255,255,.45)'; ctx.font = '500 22px Inter, system-ui, sans-serif';
      ctx.fillText('Project Web · solo en este teléfono', 70, Hc - 60);
      const guardado = await guardarImagen(canvas, { tipo: 'checkins', album: ALBUM.checkins, nombre: `checkin-${Date.now()}.jpg` });
      await agregar('checkins', {
        proyecto_id: Number(proyecto()?.id || 0),
        proyecto_nombre: proyecto()?.nombre || '',
        nota, lat: loc?.lat ?? null, lon: loc?.lon ?? null,
        ...guardado,
      });
      H.checkFoto = null;
      toast(guardado.galeria ? 'Check-in guardado en el álbum.' : 'Check-in guardado en la app.', 5000);
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo registrar el check-in.'), 6000); }
  }

  // ---------------------------------------------------------------- Inspección rápida

  function vistaInspeccion() {
    if (!H.insp) H.insp = { titulo: '', area: '', items: ITEMS_INSPECCION.map((n) => ({ nombre: n, estado: 'ok', nota: '' })) };
    const items = H.insp.items || [];
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>Inspección rápida</h2><p>Checklist de calidad y seguridad</p></div>
      </div>
      ${avisoLocal()}
      <div class="herr-form-card">
        <div class="herr-form-grid">
          <label>Título<input type="text" id="in-titulo" maxlength="100" value="${esc2(H.insp.titulo)}" placeholder="Inspección de colado"></label>
          <label>Área<input type="text" id="in-area" maxlength="80" value="${esc2(H.insp.area)}" placeholder="Nivel 3 · eje B"></label>
        </div>
        <div class="herr-inspeccion">${items.map((it, i) => `
          <div class="herr-ins-row" data-ins-row="${i}">
            <strong>${esc2(it.nombre)}</strong>
            <div class="herr-ins-estados">${ESTADOS_INSPECCION.map(([k, t]) => `<button type="button" class="herr-chip ${it.estado === k ? 'on ' + k : ''}" data-in-estado="${i}:${k}">${t}</button>`).join('')}</div>
            <input type="text" data-in-nota="${i}" value="${esc2(it.nota)}" placeholder="Nota (opcional)">
          </div>`).join('')}</div>
        <div class="herr-add-item">
          <input type="text" id="in-nuevo" maxlength="90" placeholder="Agregar punto a revisar…">
          <button class="btn-mini" type="button" id="in-agregar">+ Agregar</button>
        </div>
        <div class="tarea-acciones">
          <button class="btn-principal" type="button" id="in-guardar">Guardar inspección en álbum</button>
          <button class="btn-mini" type="button" id="in-reiniciar">Nueva</button>
        </div>
      </div>
      ${(H.datos.inspecciones || []).length ? `<h3 class="herr-subtitulo">Inspecciones · ${H.datos.inspecciones.length}</h3><div class="herr-lista">${H.datos.inspecciones.map((x) => `
        <article class="herr-item estatico">${x.thumb ? `<img src="${x.thumb}" alt="">` : '<span class="herr-item-ico"><svg class="ico"><use href="#i-check-list"/></svg></span>'}
          <span><strong>${esc2(x.titulo || 'Inspección')}</strong><small>${esc2(fechaHora(x.creado_en))}${x.area ? ' · ' + esc2(x.area) : ''}</small></span>
          <button class="icono-btn mini" type="button" data-herr-ver-foto="${esc2(x.archivo || '')}" data-herr-ver-titulo="Inspección" aria-label="Ver"><svg class="ico"><use href="#i-photo"/></svg></button>
        </article>`).join('')}</div>` : ''}
    `;
  }

  function leerInspeccionDom() {
    if (!H.insp) return;
    H.insp.titulo = String($('#in-titulo')?.value || '').trim();
    H.insp.area = String($('#in-area')?.value || '').trim();
    H.insp.items = (H.insp.items || []).map((it, i) => ({
      ...it,
      estado: it.estado || 'ok',
      nota: String($(`[data-in-nota="${i}"]`)?.value || ''),
    }));
  }

  function enlazarInspeccion() {
    $$('[data-in-estado]').forEach((b) => b.addEventListener('click', () => {
      const [i, estado] = b.dataset.inEstado.split(':');
      if (!H.insp?.items?.[Number(i)]) return;
      leerInspeccionDom();
      H.insp.items[Number(i)].estado = estado;
      H.render();
    }));
    $('#in-agregar')?.addEventListener('click', () => {
      const nombre = String($('#in-nuevo')?.value || '').trim();
      if (!nombre) return;
      leerInspeccionDom();
      H.insp.items.push({ nombre, estado: 'ok', nota: '' });
      H.render();
    });
    $('#in-reiniciar')?.addEventListener('click', () => {
      H.insp = { titulo: '', area: '', items: ITEMS_INSPECCION.map((n) => ({ nombre: n, estado: 'ok', nota: '' })) };
      H.render();
    });
    $('#in-guardar')?.addEventListener('click', guardarInspeccion);
  }

  async function guardarInspeccion() {
    leerInspeccionDom();
    if (!H.insp.titulo) { toast('Escribe el título de la inspección.'); return; }
    try {
      const items = H.insp.items || [];
      const W = 1200, cab = 190, fila = 92, pie = 110;
      const Hc = cab + items.length * fila + pie;
      const canvas = document.createElement('canvas'); canvas.width = W; canvas.height = Hc;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#0b1220'; ctx.fillRect(0, 0, W, Hc);
      ctx.fillStyle = '#f97316'; ctx.fillRect(0, 0, W, 6);
      const logo = await logoPW();
      if (logo) ctx.drawImage(logo, 46, 34, 80, 80);
      ctx.fillStyle = '#fff'; ctx.font = '700 42px Inter, system-ui, sans-serif'; ctx.textBaseline = 'top';
      ctx.fillText(H.insp.titulo, logo ? 146 : 46, 34);
      ctx.fillStyle = 'rgba(255,255,255,.65)'; ctx.font = '500 24px Inter, system-ui, sans-serif';
      ctx.fillText(`${proyecto()?.nombre || ''}${H.insp.area ? ' · ' + H.insp.area : ''} · ${fechaHora(ahoraISO())}`, logo ? 146 : 46, 92);
      items.forEach((it, i) => {
        const y = cab + i * fila;
        ctx.fillStyle = it.estado === 'no' ? 'rgba(239,68,68,.15)' : (it.estado === 'ok' ? 'rgba(34,197,94,.12)' : 'rgba(148,163,184,.10)');
        ctx.fillRect(28, y, W - 56, fila - 12);
        ctx.fillStyle = it.estado === 'no' ? '#ef4444' : (it.estado === 'ok' ? '#22c55e' : '#94a3b8');
        ctx.font = '700 26px Inter, system-ui, sans-serif';
        ctx.fillText(it.estado === 'no' ? '✕' : (it.estado === 'ok' ? '✓' : '—'), 52, y + 18);
        ctx.fillStyle = '#fff'; ctx.font = '600 26px Inter, system-ui, sans-serif';
        ctx.fillText(String(it.nombre).slice(0, 64), 104, y + 14);
        if (it.nota) { ctx.fillStyle = 'rgba(255,255,255,.6)'; ctx.font = '400 20px Inter, system-ui, sans-serif'; ctx.fillText(String(it.nota).slice(0, 90), 104, y + 48); }
      });
      ctx.fillStyle = 'rgba(255,255,255,.5)'; ctx.font = '500 20px Inter, system-ui, sans-serif';
      ctx.fillText(`Inspección de ${nombreUsuario()} · solo en este teléfono`, 46, Hc - 54);
      const guardado = await guardarImagen(canvas, { tipo: 'inspecciones', album: ALBUM.inspecciones, nombre: `inspeccion-${Date.now()}.jpg` });
      await agregar('inspecciones', { ...H.insp, ...guardado, proyecto_id: Number(proyecto()?.id || 0), proyecto_nombre: proyecto()?.nombre || '' });
      H.insp = null;
      toast(guardado.galeria ? 'Inspección guardada en el álbum.' : 'Inspección guardada en la app.', 5000);
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo guardar la inspección.'), 6000); }
  }

  // ---------------------------------------------------------------- Croquis de punch

  function vistaCroquis() {
    const c = H.croquis;
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>Croquis de punch</h2><p>Marca puntos sobre una foto</p></div>
      </div>
      ${avisoLocal()}
      ${!c?.base ? `
        <div class="herr-form-card">
          <p class="parte-nota">Toma o elige una foto del área. Después toca la foto para colocar puntos numerados con su nota.</p>
          <div class="tarea-acciones">
            <button class="btn-principal" type="button" id="cr-camara"><svg class="ico"><use href="#i-camera"/></svg> Tomar foto</button>
            <button class="btn-mini" type="button" id="cr-galeria"><svg class="ico"><use href="#i-photo"/></svg> Elegir de galería</button>
          </div>
        </div>` : `
        <div class="herr-preview">
          <div class="herr-preview-cab"><strong>${esc2(c.titulo || 'Croquis')}</strong><button class="btn-mini" type="button" id="cr-nueva">Cambiar foto</button></div>
          <canvas class="herr-canvas" id="cr-canvas"></canvas>
          <p class="parte-nota">Toca la foto para agregar un punto. Escribe la nota de cada punto abajo.</p>
          <div class="herr-pines">${c.pins.map((p, i) => `<label class="herr-pin"><span>${i + 1}</span><input type="text" data-cr-nota="${i}" value="${esc2(p.nota)}" placeholder="Qué observas en este punto"></label>`).join('')}</div>
          <div class="tarea-acciones">
            <button class="btn-principal" type="button" id="cr-guardar">Guardar croquis en álbum</button>
            <button class="btn-mini" type="button" id="cr-deshacer">Deshacer último punto</button>
          </div>
        </div>`}
      ${(H.datos.croquis || []).length ? `<h3 class="herr-subtitulo">Croquis · ${H.datos.croquis.length}</h3><div class="herr-fotos">${H.datos.croquis.map((x) => `
        <article class="herr-foto"><img src="${x.thumb || ''}" alt=""><div><strong>${esc2(x.titulo || 'Croquis')}</strong><small>${esc2(fechaHora(x.creado_en))}</small></div>
        <div class="herr-foto-acciones"><button class="icono-btn mini" type="button" data-herr-ver-foto="${esc2(x.archivo || '')}" data-herr-ver-titulo="Croquis" aria-label="Ver"><svg class="ico"><use href="#i-photo"/></svg></button>
        <button class="icono-btn mini danger" type="button" data-herr-borrar="croquis:${esc2(x.id)}" aria-label="Borrar"><svg class="ico"><use href="#i-trash"/></svg></button></div></article>`).join('')}</div>` : ''}
    `;
  }

  function enlazarCroquis() {
    const c = H.croquis;
    $('#cr-camara')?.addEventListener('click', () => nuevaFotoCroquis(true));
    $('#cr-galeria')?.addEventListener('click', () => nuevaFotoCroquis(false));
    if (!c?.base) return;
    $('#cr-nueva')?.addEventListener('click', () => { H.croquis = null; H.render(); });
    $('#cr-deshacer')?.addEventListener('click', () => { c.pins.pop(); H.render(); });
    $('#cr-guardar')?.addEventListener('click', guardarCroquis);
    $$('[data-cr-nota]').forEach((inp) => inp.addEventListener('change', () => {
      const i = Number(inp.dataset.crNota);
      if (c.pins[i]) c.pins[i].nota = inp.value;
      pintarCroquis(false);
    }));
    const canvas = $('#cr-canvas');
    if (canvas) {
      pintarCroquis(false, canvas);
      canvas.addEventListener('pointerdown', (ev) => {
        ev.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const x = Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width));
        const y = Math.max(0, Math.min(1, (ev.clientY - rect.top) / rect.height));
        c.pins.push({ x, y, nota: '' });
        H.render();
      });
    }
  }

  async function nuevaFotoCroquis(camara) {
    const archivo = await pedirArchivo(camara);
    if (!archivo) return;
    try {
      const img = await archivoAImagen(archivo, 2600);
      H.croquis = { base: img.canvas, pins: [], titulo: proyecto()?.nombre || 'Croquis de punch' };
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo preparar la foto.'), 6000); }
  }

  function croquisCanvas(final) {
    const c = H.croquis;
    const base = c.base;
    const pie = final ? Math.max(120, Math.min(360, 70 + c.pins.slice(0, 5).length * 44)) : 0;
    const canvas = document.createElement('canvas');
    canvas.width = base.width; canvas.height = base.height + pie;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(base, 0, 0);
    const S = Math.min(base.width, base.height);
    c.pins.forEach((p, i) => {
      const x = p.x * base.width, y = p.y * base.height, r = Math.max(16, S * 0.022);
      ctx.beginPath(); ctx.arc(x, y, r * 1.35, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(11,18,32,.72)'; ctx.fill();
      ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2);
      ctx.fillStyle = '#f97316'; ctx.fill();
      ctx.strokeStyle = '#fff'; ctx.lineWidth = Math.max(3, S * 0.004); ctx.stroke();
      ctx.fillStyle = '#fff'; ctx.font = `700 ${Math.round(r * 1.05)}px Inter, system-ui, sans-serif`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(String(i + 1), x, y);
    });
    if (final) {
      ctx.fillStyle = '#0b1220'; ctx.fillRect(0, base.height, canvas.width, pie);
      ctx.fillStyle = '#f97316'; ctx.fillRect(0, base.height, canvas.width, 4);
      ctx.fillStyle = '#fff'; ctx.font = `600 ${Math.round(S * 0.026)}px Inter, system-ui, sans-serif`; ctx.textAlign = 'left'; ctx.textBaseline = 'top';
      let y = base.height + 20;
      c.pins.slice(0, 5).forEach((p, i) => {
        ctx.fillStyle = '#f97316'; ctx.fillText(`${i + 1}`, 26, y);
        ctx.fillStyle = '#e8eef8'; ctx.fillText(String(p.nota || 'Punto ' + (i + 1)).slice(0, 70), 58, y);
        y += 42;
      });
      ctx.fillStyle = 'rgba(255,255,255,.5)'; ctx.font = `500 ${Math.round(S * 0.018)}px Inter, system-ui, sans-serif`;
      ctx.fillText(`${proyecto()?.nombre || ''} · ${fechaHora(ahoraISO())} · solo en este teléfono`, 26, base.height + pie - 38);
    }
    return canvas;
  }

  function pintarCroquis(final, canvas) {
    const c = H.croquis;
    if (!c?.base) return;
    const destino = canvas || $('#cr-canvas');
    if (!destino) return;
    const dib = croquisCanvas(final);
    destino.width = dib.width; destino.height = dib.height;
    destino.getContext('2d').drawImage(dib, 0, 0);
  }

  async function guardarCroquis() {
    const c = H.croquis;
    if (!c?.base) return;
    if (!c.pins.length) { toast('Toca la foto para agregar al menos un punto.'); return; }
    try {
      const canvas = croquisCanvas(true);
      const guardado = await guardarImagen(canvas, { tipo: 'croquis', album: ALBUM.croquis, nombre: `croquis-${Date.now()}.jpg` });
      await agregar('croquis', {
        titulo: c.titulo || 'Croquis de punch',
        pins: c.pins,
        ...guardado,
        proyecto_id: Number(proyecto()?.id || 0),
        proyecto_nombre: proyecto()?.nombre || '',
      });
      H.croquis = null;
      toast(guardado.galeria ? 'Croquis guardado en el álbum.' : 'Croquis guardado en la app.', 5000);
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo guardar el croquis.'), 6000); }
  }

  // ---------------------------------------------------------------- Medición sobre foto

  function vistaMedicion() {
    const m = H.medicion;
    return `
      <div class="herr-cab">
        <button class="icono-btn" type="button" data-herr-volver aria-label="Volver"><svg class="ico"><use href="#i-arrow-left"/></svg></button>
        <div><h2>Medición sobre foto</h2><p>Medida referencial con una escala conocida</p></div>
      </div>
      ${avisoLocal()}
      ${!m?.base ? `
        <div class="herr-form-card">
          <p class="parte-nota">Toma o elige una foto donde se vea una medida conocida (un tramo, una cinta, una regla). Después marcas esa referencia y mides otras distancias.</p>
          <div class="tarea-acciones">
            <button class="btn-principal" type="button" id="md-camara"><svg class="ico"><use href="#i-camera"/></svg> Tomar foto</button>
            <button class="btn-mini" type="button" id="md-galeria"><svg class="ico"><use href="#i-photo"/></svg> Elegir de galería</button>
          </div>
          <p class="parte-nota">La medida es aproximada: depende de la perspectiva y de que la referencia esté en el mismo plano.</p>
        </div>` : `
        <div class="herr-preview">
          <div class="herr-preview-cab"><strong>${esc2(m.titulo || 'Medición')}</strong><button class="btn-mini" type="button" id="md-nueva">Cambiar foto</button></div>
          <canvas class="herr-canvas" id="md-canvas"></canvas>
          <div class="herr-med-controls">
            <button class="btn-mini ${m.modo === 'ref' ? 'activo' : ''}" type="button" id="md-modo-ref">1. Marcar referencia</button>
            <button class="btn-mini ${m.modo === 'medir' ? 'activo' : ''}" type="button" id="md-modo-medir" ${m.ref?.m ? '' : 'disabled'}>2. Medir</button>
            <button class="btn-mini" type="button" id="md-deshacer">Deshacer</button>
          </div>
          ${m.ref && !m.ref.m ? `<div class="herr-add-item"><input type="number" id="md-referencia-m" min="0.01" step="0.01" placeholder="Medida real de la referencia (m)"><button class="btn-mini" type="button" id="md-fijar">Fijar</button></div>` : ''}
          <div class="herr-med-lista">${m.ref?.m ? `<div class="herr-med-item"><span>Referencia</span><strong>${m.ref.m} m</strong></div>` : ''}
            ${m.medidas.map((x, i) => `<div class="herr-med-item"><span>Medida ${i + 1}</span><strong>${x.m.toFixed(2)} m</strong><button class="icono-btn mini danger" type="button" data-md-quitar="${i}" aria-label="Quitar"><svg class="ico"><use href="#i-trash"/></svg></button></div>`).join('')}</div>
          <div class="tarea-acciones"><button class="btn-principal" type="button" id="md-guardar">Guardar medición en álbum</button></div>
        </div>`}
      ${(H.datos.mediciones || []).length ? `<h3 class="herr-subtitulo">Mediciones · ${H.datos.mediciones.length}</h3><div class="herr-fotos">${H.datos.mediciones.map((x) => `
        <article class="herr-foto"><img src="${x.thumb || ''}" alt=""><div><strong>${esc2(x.titulo || 'Medición')}</strong><small>${esc2(fechaHora(x.creado_en))}</small></div>
        <div class="herr-foto-acciones"><button class="icono-btn mini" type="button" data-herr-ver-foto="${esc2(x.archivo || '')}" data-herr-ver-titulo="Medición" aria-label="Ver"><svg class="ico"><use href="#i-photo"/></svg></button>
        <button class="icono-btn mini danger" type="button" data-herr-borrar="mediciones:${esc2(x.id)}" aria-label="Borrar"><svg class="ico"><use href="#i-trash"/></svg></button></div></article>`).join('')}</div>` : ''}
    `;
  }

  function enlazarMedicion() {
    const m = H.medicion;
    $('#md-camara')?.addEventListener('click', () => nuevaFotoMedicion(true));
    $('#md-galeria')?.addEventListener('click', () => nuevaFotoMedicion(false));
    if (!m?.base) return;
    $('#md-nueva')?.addEventListener('click', () => { H.medicion = null; H.render(); });
    $('#md-modo-ref')?.addEventListener('click', () => { m.modo = 'ref'; H.render(); });
    $('#md-modo-medir')?.addEventListener('click', () => { m.modo = 'medir'; H.render(); });
    $('#md-fijar')?.addEventListener('click', () => {
      const valor = Number($('#md-referencia-m')?.value || 0);
      if (!(valor > 0)) { toast('Escribe la medida real de la referencia.'); return; }
      m.ref.m = valor; m.modo = 'medir'; H.render();
    });
    $('#md-deshacer')?.addEventListener('click', () => {
      if (m.pend.length) m.pend = [];
      else if (m.medidas.length) m.medidas.pop();
      else m.ref = null;
      pintarMedicion(); H.render();
    });
    $$('[data-md-quitar]').forEach((b) => b.addEventListener('click', () => {
      m.medidas.splice(Number(b.dataset.mdQuitar), 1); H.render();
    }));
    const canvas = $('#md-canvas');
    if (canvas) {
      pintarMedicion(canvas);
      canvas.addEventListener('pointerdown', (ev) => {
        ev.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const p = { x: Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width)), y: Math.max(0, Math.min(1, (ev.clientY - rect.top) / rect.height)) };
        if (m.pend.length >= 2) m.pend = [];
        m.pend.push(p);
        if (m.pend.length === 2) {
          const [a, b] = m.pend;
          const px = Math.hypot((b.x - a.x) * canvas.width, (b.y - a.y) * canvas.height);
          if (m.modo === 'ref') {
            m.ref = { a, b, px, m: null };
            m.pend = [];
            H.render();
            return;
          }
          if (m.ref?.m && m.ref.px > 0) {
            const metros = (px / m.ref.px) * m.ref.m;
            m.medidas.push({ a, b, m: metros });
            m.pend = [];
            H.render();
            return;
          }
        }
        pintarMedicion(canvas);
      });
    }
    $('#md-guardar')?.addEventListener('click', guardarMedicion);
  }

  async function nuevaFotoMedicion(camara) {
    const archivo = await pedirArchivo(camara);
    if (!archivo) return;
    try {
      const img = await archivoAImagen(archivo, 2600);
      H.medicion = { base: img.canvas, ref: null, medidas: [], pend: [], modo: 'ref', titulo: proyecto()?.nombre || 'Medición' };
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo preparar la foto.'), 6000); }
  }

  function medCanvas(final) {
    const m = H.medicion;
    const base = m.base;
    const pie = final ? Math.max(130, 64 + (m.medidas.length + 1) * 38) : 0;
    const canvas = document.createElement('canvas');
    canvas.width = base.width; canvas.height = base.height + pie;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(base, 0, 0);
    const S = Math.min(base.width, base.height);
    const linea = (a, b, color, etiqueta, grosor) => {
      const x1 = a.x * base.width, y1 = a.y * base.height, x2 = b.x * base.width, y2 = b.y * base.height;
      ctx.strokeStyle = color; ctx.lineWidth = grosor || Math.max(4, S * 0.006); ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
      for (const [x, y] of [[x1, y1], [x2, y2]]) {
        ctx.beginPath(); ctx.arc(x, y, Math.max(9, S * 0.009), 0, Math.PI * 2);
        ctx.fillStyle = color; ctx.fill(); ctx.strokeStyle = '#fff'; ctx.lineWidth = Math.max(2, S * 0.003); ctx.stroke();
      }
      if (etiqueta) {
        const mx = (x1 + x2) / 2, my = (y1 + y2) / 2;
        ctx.font = `700 ${Math.round(S * 0.028)}px Inter, system-ui, sans-serif`;
        const w = ctx.measureText(etiqueta).width + 24;
        panelRedondeado(ctx, mx - w / 2, my - 26, w, 44, 18, 'rgba(11,18,32,.82)');
        ctx.fillStyle = color; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(etiqueta, mx, my - 4);
        ctx.textAlign = 'left'; ctx.textBaseline = 'top';
      }
    };
    if (m.ref) linea(m.ref.a, m.ref.b, '#38bdf8', m.ref.m ? `Ref. ${m.ref.m} m` : 'Referencia');
    m.medidas.forEach((x, i) => linea(x.a, x.b, '#f97316', `${i + 1}: ${x.m.toFixed(2)} m`));
    if (m.pend.length === 1) {
      const p = m.pend[0];
      ctx.beginPath(); ctx.arc(p.x * base.width, p.y * base.height, Math.max(8, S * 0.008), 0, Math.PI * 2);
      ctx.fillStyle = '#facc15'; ctx.fill();
    }
    if (final) {
      ctx.fillStyle = '#0b1220'; ctx.fillRect(0, base.height, canvas.width, pie);
      ctx.fillStyle = '#f97316'; ctx.fillRect(0, base.height, canvas.width, 4);
      ctx.fillStyle = '#fff'; ctx.font = `600 ${Math.round(S * 0.025)}px Inter, system-ui, sans-serif`; ctx.textAlign = 'left'; ctx.textBaseline = 'top';
      let y = base.height + 18;
      if (m.ref?.m) { ctx.fillText(`Referencia: ${m.ref.m} m`, 24, y); y += 36; }
      m.medidas.forEach((x, i) => { ctx.fillText(`Medida ${i + 1}: ${x.m.toFixed(2)} m`, 24, y); y += 36; });
      ctx.fillStyle = 'rgba(255,255,255,.5)'; ctx.font = `500 ${Math.round(S * 0.018)}px Inter, system-ui, sans-serif`;
      ctx.fillText('Medida referencial · solo en este teléfono', 24, base.height + pie - 34);
    }
    return canvas;
  }

  function pintarMedicion(canvas) {
    const m = H.medicion;
    if (!m?.base) return;
    const destino = canvas || $('#md-canvas');
    if (!destino) return;
    const dib = medCanvas(false);
    destino.width = dib.width; destino.height = dib.height;
    destino.getContext('2d').drawImage(dib, 0, 0);
  }

  async function guardarMedicion() {
    const m = H.medicion;
    if (!m?.ref?.m) { toast('Primero marca y fija la referencia.'); return; }
    if (!m.medidas.length) { toast('Marca al menos una medida.'); return; }
    try {
      const canvas = medCanvas(true);
      const guardado = await guardarImagen(canvas, { tipo: 'mediciones', album: ALBUM.mediciones, nombre: `medicion-${Date.now()}.jpg` });
      await agregar('mediciones', {
        titulo: m.titulo || 'Medición',
        referencia_m: m.ref.m,
        medidas: m.medidas.map((x) => ({ m: x.m })),
        ...guardado,
        proyecto_id: Number(proyecto()?.id || 0),
        proyecto_nombre: proyecto()?.nombre || '',
      });
      H.medicion = null;
      toast(guardado.galeria ? 'Medición guardada en el álbum.' : 'Medición guardada en la app.', 5000);
      H.render();
    } catch (e) { toast(String(e?.message || e || 'No se pudo guardar la medición.'), 6000); }
  }

  // Las funciones de las herramientas se agregan abajo en el mismo archivo.
  // (fin)
})();
