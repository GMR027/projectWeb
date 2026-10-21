'use strict';
// Recorrido común de las herramientas de IA que revisan el proyecto por lotes: Project Web arma los lotes (/contexto),
// el modelo contesta cada uno aquí y Project Web guarda el resultado (/resultado). Un lote que falla no tira el resto.
const { analizarLote } = require('./programador');
const { leer, enviar, pagina } = require('./api');

/**
 * @param rutaApi  p. ej. '/api/inconsistencias' (usa `${rutaApi}/contexto` y `${rutaApi}/resultado`)
 * @param textos   {leyendo, verbo, guardando, cancelado, queFalla?} mensajes de avance de cada herramienta
 * @param registrar  true = manda también un registro por lote (también los que fallan) para consultarlo en Project Web
 * @param alAvanzar fn({texto, hecho, total});  @param cancelado fn() → true si el usuario canceló
 * @returns {resultado, ctx, fallidos} resultado = respuesta de /resultado; ctx = respuesta de /contexto
 */
async function porLotes({ servidor, sesion, proyectoId, conexion, clave, alAvanzar, cancelado, rutaApi, textos, registrar = false, resultadoSiVacio = null }) {
  alAvanzar({ texto: textos.leyendo, hecho: 0, total: 1 });
  const ctx = await leer(sesion, pagina(servidor, rutaApi + '/contexto', proyectoId), 'No se pudo leer el proyecto');
  const respuestas = [], fallidos = [], registro = [];
  const total = ctx.lotes.length + 1;   // +1: guardar
  for (let i = 0; i < ctx.lotes.length; i++) {
    if (cancelado()) throw new Error(textos.cancelado);
    const lote = ctx.lotes[i], base = (textos.verbo || '') + lote.titulo, inicio = Date.now();
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
      fallidos.push(lote.titulo + ': ' + x.message);   // se guarda lo demás
    }
  }
  // Algunas herramientas no guardan nada si no hubo trabajo (p. ej. materiales sin pendientes)
  if (resultadoSiVacio && resultadoSiVacio(ctx)) return { resultado: null, ctx, fallidos };
  alAvanzar({ texto: textos.guardando, hecho: total - 1, total });
  const datos = { modelo: conexion.nombre + (conexion.modelo ? ' · ' + conexion.modelo : ''), respuestas, ...(registrar ? { lotes: registro } : {}) };
  const resultado = await enviar(sesion, servidor + rutaApi + '/resultado', { proyecto: String(proyectoId), datos: JSON.stringify(datos) }, ctx.csrf, 'No se pudo guardar el resultado');
  return { resultado, ctx, fallidos };
}

module.exports = { porLotes };
