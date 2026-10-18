'use strict';
// Materiales inteligentes: verifica los materiales nuevos o modificados contra la normativa y especificaciones cargadas en el proyecto.
// Project Web arma los lotes (material + cláusulas candidatas) y guarda el veredicto; aquí solo se consulta al modelo.
const { analizarLote } = require('./programador');

const json = async (r, que) => {
  const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
  if (!r.ok || !j || j.success === false) throw new Error(que + ': ' + (j?.error || (r.status === 403 || r.status === 401 ? 'inicia sesión como administrador en Project Web' : 'respuesta ' + r.status)));
  return j;
};
const pagina = (servidor, ruta, proyectoId) => servidor + ruta + '?proyecto=' + encodeURIComponent(proyectoId);

/** {activo, documentos, conteos:{aprobado,no_aprobado,revisar,sin_normativa,pendiente}, csrf} */
async function estado({ servidor, sesion, proyectoId }) {
  return json(await sesion(pagina(servidor, '/api/materiales-inteligentes/estado', proyectoId), { credentials: 'include' }), 'No se pudo leer el estado');
}

async function activar({ servidor, sesion, proyectoId, activo }) {
  const e = await estado({ servidor, sesion, proyectoId });
  const cuerpo = new URLSearchParams({ proyecto: String(proyectoId), activo: activo ? '1' : '', _csrf: e.csrf });
  return json(await sesion(servidor + '/api/materiales-inteligentes/activar', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': e.csrf }, body: cuerpo.toString() }), 'No se pudo cambiar la opción');
}

/**
 * @param alAvanzar fn({texto, hecho, total})
 * @param cancelado fn() → true si el usuario canceló
 * @returns {resumen:{verificados, aprobados, por_revisar, sin_normativa, no_aprobados:[{id,nombre,resumen}], fallidos, motivo}}
 */
async function ejecutar({ servidor, sesion, proyectoId, conexion, clave, alAvanzar, cancelado }) {
  alAvanzar({ texto: 'Buscando materiales por verificar…', hecho: 0, total: 1 });
  const ctx = await json(await sesion(pagina(servidor, '/api/materiales-inteligentes/contexto', proyectoId), { credentials: 'include' }), 'No se pudo leer el proyecto');
  const fallidos = [], respuestas = [];
  const total = ctx.lotes.length + 1;
  for (let i = 0; i < ctx.lotes.length; i++) {
    if (cancelado()) throw new Error('Verificación cancelada.');
    const lote = ctx.lotes[i], base = lote.titulo, inicio = Date.now();
    alAvanzar({ texto: base + '…', hecho: i, total });
    try {
      respuestas.push(await analizarLote(conexion, clave, lote.prompt, a => {
        const min = Math.floor((Date.now() - inicio) / 60000);
        alAvanzar({ texto: base + (a.fase === 'leyendo' ? ' · leyendo' : ' · escribiendo: ' + a.tokens + ' fragmentos') + (min ? ' · ' + min + ' min' : '') + '…', hecho: i, total });
      }));
    } catch (x) {
      if (/cancelado/.test(x.message)) throw x;
      fallidos.push(lote.titulo + ': ' + x.message);   // un lote que falla no tira el resto: sus materiales quedan pendientes
    }
  }
  if (ctx.motivo || (!ctx.lotes.length && !ctx.conteos?.pendiente)) return { resumen: { verificados: 0, aprobados: 0, por_revisar: 0, sin_normativa: 0, no_aprobados: [], fallidos, motivo: ctx.motivo || 'nada' }, proyecto: ctx.proyecto };
  alAvanzar({ texto: 'Guardando el resultado en los materiales…', hecho: total - 1, total });
  const cuerpo = new URLSearchParams({ proyecto: String(proyectoId), datos: JSON.stringify({ modelo: conexion.nombre + (conexion.modelo ? ' · ' + conexion.modelo : ''), respuestas }), _csrf: ctx.csrf });
  const r = await json(await sesion(servidor + '/api/materiales-inteligentes/resultado', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': ctx.csrf }, body: cuerpo.toString() }), 'No se pudo guardar el resultado');
  return { resumen: { verificados: r.verificados, aprobados: r.aprobados, por_revisar: r.por_revisar, sin_normativa: r.sin_normativa, no_aprobados: r.no_aprobados, fallidos, motivo: '' }, proyecto: ctx.proyecto };
}

module.exports = { ejecutar, estado, activar };
