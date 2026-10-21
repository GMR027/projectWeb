'use strict';
// OCR local (tesseract.js, sin instalar nada en el sistema): lee el texto de una imagen y lo devuelve con las columnas alineadas con espacios
// (el mismo formato que `pdftotext -layout`), para que el motor del servidor lo separe en una tabla. Los idiomas (español e inglés) se descargan una vez.
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const IDIOMAS = ['spa', 'eng'];
const URL_BASE = 'https://tessdata.projectnaptha.com/4.0.0_fast/';
const MIN_BYTES = 1_000_000;   // un archivo de idioma completo pesa varios MB; menos es una descarga cortada

function crear({ dir }) {
  const ruta = l => path.join(dir, l + '.traineddata.gz');
  const instalados = () => IDIOMAS.every(l => { try { return fs.statSync(ruta(l)).size > MIN_BYTES; } catch { return false; } });
  let worker = null, cola = Promise.resolve();

  async function instalar(avisar = () => {}) {
    fs.mkdirSync(dir, { recursive: true });
    for (const [i, l] of IDIOMAS.entries()) {
      avisar({ texto: 'Descargando idioma «' + l + '» (' + (i + 1) + ' de ' + IDIOMAS.length + ')…', hecho: i, total: IDIOMAS.length });
      const r = await fetch(URL_BASE + l + '.traineddata.gz');
      if (!r.ok) throw new Error('No se pudo descargar el idioma ' + l + ' (respuesta ' + r.status + ')');
      const buf = Buffer.from(await r.arrayBuffer());
      try { zlib.gunzipSync(buf).subarray(0, 1); } catch { throw new Error('El archivo del idioma ' + l + ' llegó dañado; intenta de nuevo'); }
      if (buf.length < MIN_BYTES) throw new Error('El archivo del idioma ' + l + ' llegó incompleto; intenta de nuevo');
      fs.writeFileSync(ruta(l) + '.tmp', buf);
      fs.renameSync(ruta(l) + '.tmp', ruta(l));
    }
    avisar({ texto: 'OCR instalado.', hecho: IDIOMAS.length, total: IDIOMAS.length });
  }

  function quitar() {
    for (const l of IDIOMAS) { try { fs.unlinkSync(ruta(l)); } catch { /* no estaba */ } }
    return cerrar();
  }

  async function cerrar() {
    const w = worker; worker = null;
    if (w) { try { await w.terminate(); } catch { /* ya cerrado */ } }
  }

  async function obtener() {
    if (worker) return worker;
    const { createWorker } = require('tesseract.js');
    worker = await createWorker(IDIOMAS, 1, { langPath: dir, cachePath: dir, gzip: true });
    return worker;
  }

  /** Texto de una imagen (Buffer) con las palabras en su columna: un renglón por línea, espacios según la posición horizontal */
  function leer(imagen) {
    if (!instalados()) return Promise.reject(new Error('El OCR no está instalado (Ajustes → OCR).'));
    const tarea = cola.then(async () => {
      const w = await obtener();
      const { data } = await w.recognize(imagen, {}, { blocks: true });
      return aTexto(data);
    });
    cola = tarea.catch(() => {});
    return tarea;
  }

  return { instalados, instalar, quitar, leer, cerrar };
}

/** Palabras con caja → texto monoespaciado: cada palabra empieza en la columna (x / ancho de carácter) de su posición en la imagen */
function aTexto(data) {
  const palabras = [];
  for (const b of data.blocks || []) for (const p of b.paragraphs || []) for (const l of p.lines || []) for (const w of l.words || []) {
    const t = String(w.text || '').trim();
    if (t && w.bbox && w.confidence > 20) palabras.push({ t, x0: w.bbox.x0, x1: w.bbox.x1, y0: w.bbox.y0, y1: w.bbox.y1 });
  }
  if (!palabras.length) return String(data.text || '');
  const anchos = palabras.filter(w => w.t.length >= 3).map(w => (w.x1 - w.x0) / w.t.length).sort((a, b) => a - b);
  const car = Math.max(2, anchos[Math.floor(anchos.length / 2)] || 6);
  const altos = palabras.map(w => w.y1 - w.y0).sort((a, b) => a - b);
  const alto = altos[Math.floor(altos.length / 2)] || 10;
  // Renglones: palabras cuyo centro vertical cae dentro de la mitad de la altura típica del renglón anterior
  palabras.sort((a, b) => (a.y0 + a.y1) - (b.y0 + b.y1));
  const lineas = [];
  for (const w of palabras) {
    const cy = (w.y0 + w.y1) / 2, ult = lineas[lineas.length - 1];
    if (ult && Math.abs(cy - ult.cy) <= alto * 0.6) { ult.p.push(w); ult.cy = (ult.cy * (ult.p.length - 1) + cy) / ult.p.length; } else lineas.push({ cy, p: [w] });
  }
  return lineas.map(l => {
    // Palabras separadas por un espacio normal forman una celda; una celda empieza en la columna que le toca por su posición
    const celdas = [];
    for (const w of l.p.sort((a, b) => a.x0 - b.x0)) {
      const ult = celdas[celdas.length - 1];
      if (ult && w.x0 - ult.x1 < car * 1.8) { ult.t += ' ' + w.t; ult.x1 = w.x1; } else celdas.push({ t: w.t, x0: w.x0, x1: w.x1 });
    }
    let s = '';
    for (const c of celdas) {
      const col = Math.round(c.x0 / car);
      s += s ? ' '.repeat(Math.max(2, col - s.length)) + c.t : ' '.repeat(col) + c.t;
    }
    return s;
  }).join('\n');
}

module.exports = { crear, aTexto, IDIOMAS };
