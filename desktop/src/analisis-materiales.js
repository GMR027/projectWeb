'use strict';
// Análisis de materiales: manda al modelo los lotes que arma Project Web y devuelve sus hallazgos para guardarlos
const { analizarLote } = require('./programador');

/**
 * @param sesion  fetch con la sesión web del admin (session.fetch de la vista de Project Web)
 * @param alAvanzar fn({texto, hecho, total})
 * @param cancelado fn() → true si el usuario canceló
 */
async function ejecutar({ servidor, sesion, proyectoId, conexion, clave, alAvanzar, cancelado }) {
  const json = async (r, que) => {
    const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
    if (!r.ok || !j || j.success === false) throw new Error(que + ': ' + (j?.error || (r.status === 403 || r.status === 401 ? 'inicia sesión como administrador en Project Web' : 'respuesta ' + r.status)));
    return j;
  };
  alAvanzar({ texto: 'Leyendo materiales, submittals y MSR, y aplicando las reglas del sistema…', hecho: 0, total: 1 });
  const ctx = await json(await sesion(servidor + '/api/analisis-materiales/contexto?proyecto=' + encodeURIComponent(proyectoId), { credentials: 'include' }), 'No se pudo leer el proyecto');
  const respuestas = [];
  const fallidos = [];
  const registro = [];   // un registro por lote (también los que fallan) para consultarlo después en Project Web
  const total = ctx.lotes.length + 1;   // +1: guardar
  for (let i = 0; i < ctx.lotes.length; i++) {
    if (cancelado()) throw new Error('Análisis cancelado.');
    const lote = ctx.lotes[i], base = 'Analizando ' + lote.titulo, inicio = Date.now();
    alAvanzar({ texto: base + '…', hecho: i, total });
    try {
      const resp = await analizarLote(conexion, clave, lote.prompt, a => {
        const min = Math.floor((Date.now() - inicio) / 60000);
        alAvanzar({ texto: base + (a.fase === 'leyendo' ? ' · leyendo' : ' · escribiendo: ' + a.tokens + ' fragmentos') + (min ? ' · ' + min + ' min' : '') + '…', hecho: i, total });
      });
      respuestas.push(resp);
      registro.push({ titulo: lote.titulo, ids: lote.ids, estado: 'ok', respuesta: resp });
    } catch (x) {
      if (/cancelado/.test(x.message)) throw x;
      registro.push({ titulo: lote.titulo, ids: lote.ids, estado: 'error', error: x.message });
      fallidos.push(lote.titulo + ': ' + x.message);   // un paquete que falla no tira el resto: se guarda lo demás
    }
  }
  alAvanzar({ texto: 'Guardando el análisis…', hecho: total - 1, total });
  const cuerpo = new URLSearchParams({ proyecto: String(proyectoId), datos: JSON.stringify({ modelo: conexion.nombre + (conexion.modelo ? ' · ' + conexion.modelo : ''), respuestas, lotes: registro }), _csrf: ctx.csrf });
  const r = await json(await sesion(servidor + '/api/analisis-materiales/resultado', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': ctx.csrf }, body: cuerpo.toString() }), 'No se pudo guardar el resultado');
  return { resumen: { corrida: r.corrida, del_sistema: r.del_sistema, de_la_ia: r.de_la_ia, nuevos: r.nuevos, ya_resueltos: r.ya_resueltos, fallidos }, proyecto: ctx.proyecto };
}

module.exports = { ejecutar };
