'use strict';
// Ensamblador: sube documentos a Project Web (que extrae el texto), manda al modelo los lotes que arma el servidor y
// devuelve la propuesta ya validada. La IA nunca escribe datos: aplicar/deshacer son llamadas del admin al servidor.
const crypto = require('crypto');
const { analizarLote } = require('./programador');

const MAX_BYTES = 50 * 1024 * 1024;

/** Cuerpo multipart/form-data con campos de texto y un archivo (sin dependencias; el nombre va en UTF-8) */
function multipart(campos, nombre, datos) {
  const b = '----pw' + crypto.randomBytes(12).toString('hex');
  const partes = [];
  for (const [k, v] of Object.entries(campos)) partes.push(Buffer.from(`--${b}\r\nContent-Disposition: form-data; name="${k}"\r\n\r\n${v}\r\n`));
  partes.push(Buffer.from(`--${b}\r\nContent-Disposition: form-data; name="archivo"; filename="${String(nombre).replace(/["\r\n\\]/g, '_')}"\r\nContent-Type: application/octet-stream\r\n\r\n`), Buffer.from(datos), Buffer.from(`\r\n--${b}--\r\n`));
  return { body: Buffer.concat(partes), tipo: 'multipart/form-data; boundary=' + b };
}

function crear({ cfg, sesion, avisar }) {
  let cancelar = false, ocupado = false;

  const json = async (r, que) => {
    const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
    if (!r.ok || !j || j.success === false) throw new Error(que + ': ' + (j?.error || (r.status === 403 || r.status === 401 ? 'inicia sesión como administrador en Project Web' : 'respuesta ' + r.status)));
    return j;
  };
  const srv = () => cfg.leer().servidor;
  const get = async (ruta, que) => json(await sesion(srv() + ruta, { credentials: 'include' }), que);
  const post = async (ruta, campos, csrf, que) => json(await sesion(srv() + ruta, {
    method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf }, body: new URLSearchParams(campos).toString(),
  }), que);

  async function estado(pid) {
    return get('/api/ensamblador/estado?proyecto=' + encodeURIComponent(pid), 'No se pudo leer el Ensamblador');
  }

  /** archivos: [{nombre, datos: Uint8Array}]. Un archivo con problema no detiene a los demás */
  async function subir(pid, ses, archivos) {
    const st = await estado(pid);
    let sesionId = ses || 0;
    const subidos = [], errores = [];
    for (const f of archivos) {
      if (f.datos.byteLength > MAX_BYTES) { errores.push({ nombre: f.nombre, error: 'Supera los 50 MB' }); continue; }
      avisar({ texto: 'Subiendo y leyendo «' + f.nombre + '»…' });
      const m = multipart({ proyecto: String(pid), ...(sesionId ? { sesion: String(sesionId) } : {}) }, f.nombre, f.datos);
      const r = await sesion(srv() + '/api/ensamblador/subir', { method: 'POST', credentials: 'include', headers: { 'Content-Type': m.tipo, 'X-CSRF-Token': st.csrf }, body: m.body });
      const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
      if (j?.sesion) sesionId = j.sesion;
      if (r.ok && j?.success) subidos.push(j.archivo); else errores.push({ nombre: f.nombre, error: j?.error || 'respuesta ' + r.status });
    }
    return { sesion: sesionId, archivos: subidos, errores, estado: st };
  }

  async function quitar(pid, ses, archivo) {
    const st = await estado(pid);
    return post('/api/ensamblador/quitar', { proyecto: pid, sesion: ses, archivo }, st.csrf, 'No se pudo quitar el archivo');
  }

  /**
   * Un mensaje del chat: botones elegidos (modos) + texto libre.
   * @returns {tipo:'respuesta', respuesta} | {tipo:'propuesta', propuesta, notas, fallidos} | {tipo:'nada', notas, mensaje}
   */
  async function mensaje({ pid, ses, texto, modos, submittalId, conexion, clave, revisar }) {
    if (ocupado) throw new Error('Ya hay un análisis en curso.');
    ocupado = true; cancelar = false;
    try {
      avisar({ texto: 'Preparando el documento…', hecho: 0, total: 1 });
      const st = await estado(pid);
      const ctx = await post('/api/ensamblador/contexto', { proyecto: pid, sesion: ses, instruccion: texto || '', modos: JSON.stringify(modos || []), submittal_id: submittalId || 0 }, st.csrf, 'No se pudo preparar el análisis');
      if (!ctx.lotes.length && ctx.tipo !== 'local') return { tipo: 'nada', mensaje: ctx.mensaje || 'No hay nada que analizar.', notas: [] };
      const respuestas = []; let fallidos = 0, ultimoError = '';
      for (let i = 0; i < ctx.lotes.length; i++) {
        if (cancelar) throw new Error('Análisis cancelado.');
        const lote = ctx.lotes[i], inicio = Date.now(), base = lote.titulo;
        avisar({ texto: base + '…', hecho: i, total: ctx.lotes.length });
        try {
          respuestas.push(await analizarLote(conexion, clave, lote.prompt, a => {
            const min = Math.floor((Date.now() - inicio) / 60000);
            avisar({ texto: base + (a.fase === 'leyendo' ? ' · leyendo' : ' · escribiendo: ' + a.tokens + ' fragmentos') + (min ? ' · ' + min + ' min' : '') + '…', hecho: i, total: ctx.lotes.length });
          }));
        } catch (x) { if (cancelar) throw new Error('Análisis cancelado.'); fallidos++; ultimoError = x.message; continue; }   // un fragmento que falla no tira el resto
        // Segunda pasada: el modelo contrasta su propia extracción con el fragmento; si falla, se queda la primera
        if (revisar && ctx.tipo === 'extraccion') {
          const primera = respuestas[respuestas.length - 1];
          try {
            avisar({ texto: base + ' · segunda pasada de revisión…', hecho: i, total: ctx.lotes.length });
            respuestas[respuestas.length - 1] = await analizarLote(conexion, clave, lote.prompt + '\n\nSEGUNDA PASADA DE REVISIÓN. Esta es tu extracción anterior de este mismo fragmento:\n' + JSON.stringify(primera) + '\n\nContrástala con el fragmento: quita lo que el texto no respalda, corrige cantidades, fechas y nombres mal leídos y agrega lo que omitiste. Responde con el JSON COMPLETO y corregido, en el mismo formato.');
          } catch (x) { if (cancelar) throw new Error('Análisis cancelado.'); respuestas[respuestas.length - 1] = primera; }
        }
      }
      if (!respuestas.length && ctx.tipo !== 'local') throw new Error('El modelo no pudo analizar ningún fragmento: ' + ultimoError);
      avisar({ texto: 'Comparando con el programa y los materiales del proyecto…', hecho: ctx.lotes.length, total: ctx.lotes.length });
      const datos = { modelo: conexion.nombre + (conexion.modelo ? ' · ' + conexion.modelo : ''), tipo: ctx.tipo, instruccion: texto || '', modos: modos || [], submittal_id: submittalId || 0, respuestas };
      const r = await post('/api/ensamblador/resultado', { proyecto: pid, sesion: ses, datos: JSON.stringify(datos) }, ctx.csrf || st.csrf, 'No se pudo guardar la propuesta');
      if (ctx.tipo === 'pregunta') return { tipo: 'respuesta', respuesta: r.respuesta, aviso: ctx.aviso || '' };
      if (!r.propuesta) return { tipo: 'nada', notas: r.notas || [], mensaje: 'No encontré en el documento información que encaje con lo que pediste.', fallidos };
      return { tipo: 'propuesta', propuesta: r.propuesta, notas: r.notas || [], fallidos, aviso: ctx.aviso || '' };
    } finally { ocupado = false; }
  }

  const accion = async (ruta, pid, campos, que) => { const st = await estado(pid); return post(ruta, { proyecto: pid, ...campos }, st.csrf, que); };

  return {
    estado, subir, quitar, mensaje,
    cancelar: () => { cancelar = true; },
    aplicar: (pid, id, items) => accion('/api/ensamblador/aplicar', pid, { id, items: JSON.stringify(items) }, 'No se pudo aplicar'),
    revertir: (pid, id) => accion('/api/ensamblador/revertir', pid, { id }, 'No se pudo deshacer'),
    rechazar: (pid, id) => accion('/api/ensamblador/rechazar', pid, { id }, 'No se pudo descartar'),
    propuesta: (pid, id) => get('/api/ensamblador/propuesta?proyecto=' + encodeURIComponent(pid) + '&id=' + encodeURIComponent(id), 'No se pudo leer la propuesta'),
  };
}

module.exports = { crear, multipart };
