'use strict';
// Tabla en base a documento: el servidor lee Excel/CSV/Word/PDF con su motor (sin IA); la IA (solo admin) lee imágenes, escaneos y formatos difíciles,
// o corrige la lectura del motor. Siempre devuelve una tabla para revisar: nada se crea hasta que el admin la acepta en la vista previa.
const { multipart } = require('./ensamblador');
const { analizarLote } = require('./programador');
const capacidad = require('./capacidad');

const MIME = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', webp: 'image/webp' };
const MAX_BYTES = 25 * 1024 * 1024, MAX_TEXTO = 24000;

/** Matriz de textos con el mismo número de columnas en todos los renglones (lo que devuelve el modelo no es de fiar) */
function limpiarFilas(f) {
  if (!Array.isArray(f)) return [];
  const filas = f.filter(Array.isArray).map(r => r.map(c => (c == null ? '' : String(c)).trim().slice(0, 500))).filter(r => r.some(c => c !== ''));
  const nc = Math.min(30, Math.max(0, ...filas.map(r => r.length)));
  return filas.slice(0, 1000).map(r => { const x = r.slice(0, nc); while (x.length < nc) x.push(''); return x; });
}

function prompt(nombre, texto, previa, truncado) {
  return `Eres asistente de control de obra. Extrae del documento «${nombre}» UNA tabla con sus datos.
Reglas: la primera fila son los encabezados; una fila por renglón del documento; copia el texto tal cual (corrige solo errores evidentes de lectura); no inventes datos ni columnas; deja vacía una celda sin dato; une el texto que continúa en varias líneas dentro de la misma celda. Si hay varias tablas elige la principal (la que más datos tiene).
${previa ? `El sistema ya hizo una lectura automática que puede traer columnas mal separadas o renglones partidos; corrígela con el documento:\n${JSON.stringify(previa.slice(0, 60))}\n` : ''}${texto ? `Texto del documento${truncado ? ' (recortado)' : ''}:\n${texto}\n` : 'El documento va como imagen adjunta.\n'}
Responde SOLO con JSON: {"titulo":"<nombre corto de la tabla>","filas":[["<encabezado 1>","<encabezado 2>"],["<celda>","<celda>"]]}`;
}

function crear({ cfg, sesion, avisar, ocr }) {
  const srv = () => cfg.leer().servidor;
  const json = async (r, que) => {
    const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
    if (!r.ok || !j || j.success === false) throw new Error(que + ': ' + (j?.error || (r.status === 403 || r.status === 401 ? 'inicia sesión como administrador en Project Web' : 'respuesta ' + r.status)));
    return j;
  };
  /** El token CSRF de la sesión web lo da cualquier API de administrador (la misma que usa el Ensamblador) */
  const csrf = async pid => (await json(await sesion(srv() + '/api/ensamblador/estado?proyecto=' + encodeURIComponent(pid), { credentials: 'include' }), 'No se pudo conectar con el servidor')).csrf;

  const formulario = async (ruta, campos, token, que) => json(await sesion(srv() + ruta, {
    method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': token }, body: new URLSearchParams(campos).toString(),
  }), que);

  /** archivo {nombre, datos}; conexion = null → solo el motor (servidor, y OCR local para imágenes) */
  async function leer(pid, archivo, conexion, clave, usarIA) {
    if (archivo.datos.byteLength > MAX_BYTES) throw new Error('El archivo supera los 25 MB');
    const token = await csrf(pid);
    const ext = (archivo.nombre.split('.').pop() || '').toLowerCase();
    const esImagen = !!MIME[ext];
    let r = { filas: [], texto: '', avisos: [] };
    if (esImagen) {
      // Imagen: OCR local (sin IA); su texto con columnas lo separa el motor del servidor
      if (ocr.instalados()) {
        avisar({ texto: 'Leyendo el texto de la imagen (OCR)…' });
        const texto = await ocr.leer(Buffer.from(archivo.datos));
        r = await formulario('/api/vplan/documento/texto', { proyecto: String(pid), texto }, token, 'No se pudo interpretar el texto de la imagen');
        r.texto = texto;
      } else if (!usarIA) {
        throw new Error('Para leer imágenes sin IA instala el OCR (Ajustes → OCR) o usa «Leer con IA».');
      }
    } else {
      avisar({ texto: 'El servidor está leyendo «' + archivo.nombre + '»…' });
      const m = multipart({ proyecto: String(pid) }, archivo.nombre, archivo.datos);
      r = await json(await sesion(srv() + '/api/vplan/documento/leer', { method: 'POST', credentials: 'include', headers: { 'Content-Type': m.tipo, 'X-CSRF-Token': token }, body: m.body }), 'No se pudo leer el documento')
        .catch(e => { if (usarIA) return { filas: [], texto: '', avisos: [], error: e.message }; throw e; });
    }
    const motor = r.filas || [];
    if (!usarIA) return { filas: motor, metodo: esImagen ? 'OCR' : 'motor', avisos: r.avisos || [], titulo: '', modelo: '' };
    if (!conexion) throw new Error('Elige una conexión de IA (Ajustes → Configuración de IA).');
    // Imagen: si el modelo ve imágenes se le manda la imagen; si no, el texto del OCR
    const imagenes = esImagen && capacidad.perfil(conexion).vision ? [{ mime: MIME[ext], base64: Buffer.from(archivo.datos).toString('base64') }] : [];
    if (esImagen && !imagenes.length && !r.texto) throw new Error('Esta conexión no ve imágenes: elige un modelo con visión (p. ej. llava, gemma3, qwen-vl, GPT-4o, Claude) o instala el OCR (Ajustes → OCR).');
    const texto = String(r.texto || '');
    if (!imagenes.length && !texto.trim() && !motor.length) throw new Error(r.error || (r.avisos || []).join(' ') || 'El documento no tiene texto que leer: sube una imagen de la tabla.');
    avisar({ texto: 'La IA está leyendo la tabla…' });
    const j = await analizarLote(conexion, clave, prompt(archivo.nombre, texto.slice(0, MAX_TEXTO), motor.length ? motor : null, texto.length > MAX_TEXTO), null, imagenes);
    const filas = limpiarFilas(j.filas);
    if (filas.length < 2 || filas[0].length < 1) throw new Error('La IA no encontró una tabla en el documento.');
    return { filas, metodo: 'ia', avisos: [...(r.avisos || []), 'Lectura hecha por IA: revisa los datos antes de crear la tabla.'], titulo: String(j.titulo || '').slice(0, 140), modelo: conexion.nombre + (conexion.modelo ? ' · ' + conexion.modelo : '') };
  }

  async function crearTabla(pid, { nombre, origen, metodo, modelo, filas }) {
    const token = await csrf(pid);
    return json(await sesion(srv() + '/api/vplan/documento/crear', {
      method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': token },
      body: new URLSearchParams({ proyecto: String(pid), nombre, origen, metodo: metodo === 'ia' ? 'ia' : 'motor', modelo: modelo || '', filas: JSON.stringify(filas) }).toString(),
    }), 'No se pudo crear la tabla');
  }

  return { leer, crearTabla };
}

module.exports = { crear, prompt, limpiarFilas };
