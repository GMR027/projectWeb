'use strict';
// Ejecutor genérico de las herramientas de IA que producen un informe: pide al servidor los datos y los prompts, manda cada prompt al
// modelo elegido y devuelve lo redactado para que el servidor arme el informe. Sin modelo (sinIA) el informe se arma solo con plantilla.
const { analizarLote } = require('./programador');

/**
 * @param sesion  fetch con la sesión web del admin (session.fetch de la vista de Project Web)
 * @param alAvanzar fn({texto, hecho, total})
 * @param cancelado fn() → true si el usuario canceló
 */
async function ejecutar({ servidor, sesion, proyectoId, herramienta, parametros, conexion, clave, sinIA, alAvanzar, cancelado }) {
  const json = async (r, que) => {
    const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
    if (!r.ok || !j || j.success === false) throw new Error(que + ': ' + (j?.error || (r.status === 403 || r.status === 401 ? 'inicia sesión como administrador en Project Web' : 'respuesta ' + r.status)));
    return j;
  };
  const post = async (ruta, campos, csrf) => json(await sesion(servidor + ruta, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf }, body: new URLSearchParams({ ...campos, _csrf: csrf }).toString() }), 'El servidor rechazó la petición');
  alAvanzar({ texto: 'Reuniendo los datos del proyecto…', hecho: 0, total: 1 });
  const cat = await json(await sesion(servidor + '/api/ia/herramientas', { credentials: 'include' }), 'No se pudo conectar con Project Web');
  const ctx = await post('/api/ia/contexto', { proyecto: String(proyectoId), herramienta, params: JSON.stringify(parametros || {}) }, cat.csrf);
  const respuestas = [], fallidos = [];
  const lotes = sinIA ? [] : ctx.lotes;
  const total = lotes.length + 1;
  for (let i = 0; i < lotes.length; i++) {
    if (cancelado()) throw new Error('Cancelado.');
    const lote = lotes[i], base = lote.titulo || 'Redactando', inicio = Date.now();
    alAvanzar({ texto: base + '…', hecho: i, total });
    try {
      // Si el lote trae imágenes (fotos de evidencia) se descargan del servidor y viajan con el prompt al modelo de visión
      const imagenes = [];
      for (const im of (lote.imagenes || []).slice(0, 4)) {
        try { const r = await sesion(servidor + im.url, { credentials: 'include' }); if (r.ok) imagenes.push({ mime: (r.headers.get('content-type') || 'image/jpeg').split(';')[0], base64: Buffer.from(await r.arrayBuffer()).toString('base64') }); } catch { /* foto no disponible */ }
      }
      if ((lote.imagenes || []).length && !imagenes.length) throw new Error('no se pudieron descargar las fotos');
      const j = await analizarLote(conexion, clave, lote.prompt, a => {
        const min = Math.floor((Date.now() - inicio) / 60000);
        alAvanzar({ texto: base + (a.fase === 'leyendo' ? ' · leyendo' : ' · escribiendo: ' + a.tokens + ' fragmentos') + (min ? ' · ' + min + ' min' : '') + '…', hecho: i, total });
      }, imagenes);
      respuestas.push(lote.clave ? { ...j, _clave: lote.clave } : j);   // la clave permite al servidor saber a qué elemento corresponde cada respuesta aunque otro lote falle
    } catch (x) {
      if (/cancelado/i.test(x.message)) throw x;
      fallidos.push(base + ': ' + x.message);   // si la IA falla el informe se arma igual, con texto de plantilla
    }
  }
  alAvanzar({ texto: 'Armando el informe…', hecho: total - 1, total });
  const modelo = respuestas.length ? conexion.nombre + (conexion.modelo ? ' · ' + conexion.modelo : '') : '';
  const r = await post('/api/ia/resultado', { proyecto: String(proyectoId), herramienta, datos: JSON.stringify({ modelo, params: parametros || {}, respuestas }) }, cat.csrf);
  return { informe_id: r.informe_id, titulo: r.titulo, con_ia: r.con_ia, url: r.url, fallidos };
}

/** Fichas de las herramientas que ofrece el servidor (para el menú); lista vacía si no hay sesión */
async function catalogo({ servidor, sesion }) {
  try {
    const r = await sesion(servidor + '/api/ia/herramientas', { credentials: 'include' });
    const j = await r.json();
    return r.ok && j.success ? j.herramientas : [];
  } catch { return []; }
}

module.exports = { ejecutar, catalogo };
