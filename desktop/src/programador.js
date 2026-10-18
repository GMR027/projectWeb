'use strict';
// Programador de actividades: manda al modelo los lotes que arma Project Web y devuelve sus respuestas ya como JSON
const prov = require('./providers');

const OPCIONES = { timeout: 15 * 60 * 1000, maxTokens: 8192, numCtx: 32768 };

/** Extrae el objeto JSON de la respuesta de un modelo (con o sin ```json ... ```); null si no hay uno válido */
function extraerJson(texto) {
  const t = String(texto || '').trim();
  try { const j = JSON.parse(t); if (j && typeof j === 'object' && !Array.isArray(j)) return j; } catch { /* sigue */ }
  const i = t.indexOf('{'), f = t.lastIndexOf('}');
  if (i < 0 || f <= i) return null;
  try { const j = JSON.parse(t.slice(i, f + 1)); return j && typeof j === 'object' && !Array.isArray(j) ? j : null; } catch { return null; }
}

/** Envía un lote; si la respuesta no es JSON se reintenta una vez pidiendo solo el JSON */
async function analizarLote(p, clave, prompt, alToken, imagenes) {
  const mensajes = [{ role: 'user', content: prompt }];
  const opciones = { ...OPCIONES, ...(alToken ? { alAvanzar: alToken } : {}), ...(imagenes?.length ? { imagenes } : {}) };
  let texto = await prov.chat(p, clave, mensajes, opciones);
  let j = extraerJson(texto);
  if (!j) {
    texto = await prov.chat(p, clave, [...mensajes, { role: 'assistant', content: String(texto).slice(0, 4000) }, { role: 'user', content: 'Tu respuesta no fue un JSON válido. Responde de nuevo SOLO con el JSON del formato pedido, sin texto adicional ni bloques de código.' }], opciones);
    j = extraerJson(texto);
  }
  if (!j) throw new Error('El modelo no devolvió un JSON válido (revisa que el modelo siga instrucciones; los muy pequeños suelen fallar).');
  return j;
}

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
  alAvanzar({ texto: 'Leyendo el programa, materiales, submittals y bitácora…', hecho: 0, total: 1 });
  const ctx = await json(await sesion(servidor + '/api/programador/contexto?proyecto=' + encodeURIComponent(proyectoId), { credentials: 'include' }), 'No se pudo leer el proyecto');
  if (!ctx.lotes.length) throw new Error('No hay actividades por analizar: todas están iniciadas, terminadas o son hitos.');
  const respuestas = [];
  // Fase 2: un paquete pequeño a la vez
  const paso = async (lote, i, n) => {
    if (cancelado()) throw new Error('Análisis cancelado.');
    const base = 'Analizando ' + lote.titulo;
    const inicio = Date.now();
    alAvanzar({ texto: base + '…', hecho: i, total: n });
    respuestas.push(await analizarLote(conexion, clave, lote.prompt, a => {
      const min = Math.floor((Date.now() - inicio) / 60000);
      alAvanzar({ texto: base + (a.fase === 'leyendo' ? ' · leyendo' : ' · escribiendo: ' + a.tokens + ' fragmentos') + (min ? ' · ' + min + ' min' : '') + '…', hecho: i, total: n });
    }));
  };
  const total = ctx.lotes.length + 2;   // +2: la integración de paquetes y la revisión
  for (let i = 0; i < ctx.lotes.length; i++) await paso(ctx.lotes[i], i, total);
  // Fase 3: conectar los paquetes entre sí (solo si hay más de uno)
  if (ctx.lotes.length > 1) {
    const cuerpoI = new URLSearchParams({ proyecto: String(proyectoId), datos: JSON.stringify({ respuestas }), _csrf: ctx.csrf });
    const ri = await json(await sesion(servidor + '/api/programador/integracion', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': ctx.csrf }, body: cuerpoI.toString() }), 'No se pudo preparar la integración');
    if (ri.lote) { try { await paso(ri.lote, ctx.lotes.length, total); } catch (x) { if (/cancelado/.test(x.message)) throw x; alAvanzar({ texto: 'La integración falló (' + x.message + '); se usa lo de cada paquete por separado.', hecho: ctx.lotes.length, total }); } }
  }
  // Fase 4: la IA revisa la información obtenida y señala qué se puede mejorar (si falla, la propuesta se guarda igual)
  const posRev = total - 1;
  try {
    const rr = await json(await sesion(servidor + '/api/programador/revision', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': ctx.csrf }, body: new URLSearchParams({ proyecto: String(proyectoId), datos: JSON.stringify({ respuestas }), _csrf: ctx.csrf }).toString() }), 'No se pudo preparar la revisión');
    if (rr.lote) await paso(rr.lote, posRev, total);
  } catch (x) { if (/cancelado/.test(x.message)) throw x; alAvanzar({ texto: 'La revisión falló (' + x.message + '); se guarda la propuesta sin ella.', hecho: posRev, total }); }
  alAvanzar({ texto: 'Calculando el programa propuesto con el calendario…', hecho: total, total });
  const cuerpo = new URLSearchParams({ proyecto: String(proyectoId), datos: JSON.stringify({ modelo: conexion.nombre + (conexion.modelo ? ' · ' + conexion.modelo : ''), respuestas }), _csrf: ctx.csrf });
  const r = await json(await sesion(servidor + '/api/programador/propuesta', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': ctx.csrf }, body: cuerpo.toString() }), 'No se pudo guardar la propuesta');
  return { id: r.id, resumen: r.resumen, proyecto: ctx.proyecto };
}

module.exports = { extraerJson, analizarLote, ejecutar };
