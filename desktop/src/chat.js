'use strict';
// «Pregunta a tu proyecto»: la IA no escribe consultas libres. Elige entre consultas predefinidas de solo lectura del servidor, el servidor las
// ejecuta y la IA redacta la respuesta con esos datos. Sin conexión de IA se muestran los datos de las consultas que sugiere el servidor.
const { analizarLote } = require('./programador');

const MAX_DATOS = 7000;
const MSG_SOLO_LECTURA = 'Este chat solo consulta datos, no los modifica. Para cambiar algo usa las herramientas de IA (cada cambio llega como propuesta que tú aceptas) o edítalo directamente en Project Web.';

/** ¿Pide la pregunta hacer un cambio? (verbo de acción al inicio, con o sin «puedes…»). Se decide sin el modelo para que nunca invente que lo hizo */
function pideCambio(q) {
  const t = String(q).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  const verbos = 'borr|elimin|quit|cre|agreg|anad|cambi|modific|mueve|mover|mueva|aprueb|aprob|acept|rechaz|marc|actualiz|edit|renombr|reprogram|adelant|atras|pon|sub|baj|guard|ejecut|aplic|revert|deshac|deshag';
  // «Borra la 3.2» (imperativo al inicio) o «puedes cambiar…», «quiero que apruebes…» (con prefijo cortés: cualquier forma del verbo)
  return new RegExp('^(por favor[, ]*)?(((puedes|podrias|podria|me puedes|quiero que|necesito que|quisiera que|hazme|haz que)\\s+(' + verbos + ')\\w*)|((borra|elimina|quita|crea|agrega|anade|cambia|modifica|mueve|aprueba|acepta|rechaza|marca|actualiza|edita|renombra|reprograma|adelanta|atrasa|pon|sube|baja|guarda|ejecuta|aplica|revierte|deshaz)\\b))').test(t);
}
/** Si el modelo afirma haber hecho algo, se advierte: no pudo hacerlo */
function afirmaAccion(r) {
  return /\b(he|hemos|ya|se ha|se han|fue|fueron|quedo|quedaron)\s+(eliminad|borrad|creado|creada|modificad|actualizad|cambiad|movid|agregad|aprobad|aceptad|renombrad|reprogramad|guardad)/i.test(String(r).normalize('NFD').replace(/[\u0300-\u036f]/g, ''));
}

function promptPlan(catalogo, pregunta) {
  const lineas = Object.entries(catalogo).map(([n, c]) => `- ${n}: ${c.descripcion}${c.parametros.length ? ' Parámetros: ' + c.parametros.join(', ') + '.' : ''}`);
  return 'Eres el asistente de un proyecto de construcción. Para responder la pregunta del usuario elige hasta 3 de estas consultas de SOLO LECTURA (usa exactamente esos nombres) y sus parámetros.\n\n'
    + lineas.join('\n') + '\n\nPregunta: "' + pregunta + '"\n\nSi la pregunta pide CAMBIAR datos (crear, borrar, mover, aprobar), devuelve consultas vacías y modificar=true.\n'
    + 'Devuelve SOLO un JSON: {"consultas":[{"nombre":"actividades","params":{"estado":"atrasadas"}}],"modificar":false}';
}

function promptRespuesta(pregunta, historial, datos) {
  const previo = (historial || []).slice(-4).map(m => (m.rol === 'usuario' ? 'Usuario: ' : 'Asistente: ') + String(m.texto).slice(0, 400)).join('\n');
  return 'Eres el asistente de un proyecto de construcción. Responde en español, claro y breve, usando SOLO los datos de abajo; si faltan datos para responder, dilo y sugiere qué consultar. No inventes cifras ni nombres. Cita códigos de actividad y fechas tal como aparecen. Este chat SOLO CONSULTA: nunca digas que creaste, borraste, cambiaste o aprobaste algo.\n\n'
    + (previo ? '## CONVERSACIÓN PREVIA\n' + previo + '\n\n' : '') + '## DATOS DEL PROYECTO\n' + String(datos).slice(0, MAX_DATOS) + '\n\n## PREGUNTA\n' + pregunta
    + '\n\nDevuelve SOLO un JSON: {"respuesta":"texto de la respuesta (puede usar saltos de línea y viñetas con guion)","sugerencias":["hasta 3 preguntas de seguimiento útiles"]}';
}

async function responder({ servidor, sesion, proyectoId, pregunta, historial, conexion, clave, alAvanzar }) {
  const json = async (r, que) => {
    const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
    if (!r.ok || !j || j.success === false) throw new Error(que + ': ' + (j?.error || (r.status === 403 || r.status === 401 ? 'inicia sesión como administrador en Project Web' : 'respuesta ' + r.status)));
    return j;
  };
  const q = String(pregunta || '').trim().slice(0, 600);
  if (!q) throw new Error('Escribe una pregunta.');
  if (!(proyectoId > 0)) throw new Error('Abre un proyecto en Project Web para preguntar sobre él.');
  if (pideCambio(q)) return { respuesta: MSG_SOLO_LECTURA, consultas: [], sinIA: false, sugerencias: ['¿Qué hay pendiente en el análisis inteligente?', 'Resumen del proyecto'] };
  alAvanzar?.('Eligiendo qué consultar…');
  const cat = await json(await sesion(servidor + '/api/ia/consultas', { credentials: 'include' }), 'No se pudo conectar con Project Web');
  let plan = [], modificar = false;
  if (conexion) {
    try {
      const j = await analizarLote(conexion, clave, promptPlan(cat.consultas, q), null);
      modificar = !!j.modificar;
      plan = (Array.isArray(j.consultas) ? j.consultas : []).filter(c => c && typeof c.nombre === 'string' && cat.consultas[c.nombre]).slice(0, 3).map(c => ({ nombre: c.nombre, params: c.params && typeof c.params === 'object' ? c.params : {} }));
    } catch { /* si el modelo falla se usan las consultas por palabras clave */ }
  }
  if (modificar && !plan.length) return { respuesta: MSG_SOLO_LECTURA, consultas: [], sinIA: false, sugerencias: ['¿Qué hay pendiente en el análisis inteligente?', 'Resumen del proyecto'] };
  alAvanzar?.('Consultando el proyecto…');
  // el servidor completa el plan del modelo con las consultas que sugieren las palabras clave (un modelo pequeño a veces elige mal)
  const cuerpo = { proyecto: String(proyectoId), consultas: JSON.stringify(plan), pregunta: q, completar: '1', _csrf: cat.csrf };
  const datos = await json(await sesion(servidor + '/api/ia/consulta', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': cat.csrf }, body: new URLSearchParams(cuerpo).toString() }), 'No se pudo consultar el proyecto');
  const nombres = datos.resultados.map(r => r.nombre);
  if (!conexion) return { respuesta: datos.texto, consultas: nombres, sinIA: true, sugerencias: [] };
  alAvanzar?.('Redactando la respuesta…');
  try {
    const j = await analizarLote(conexion, clave, promptRespuesta(q, historial, datos.texto), null);
    const respuesta = String(j.respuesta || '').trim();
    if (!respuesta) throw new Error('vacía');
    return { respuesta: (afirmaAccion(respuesta) ? '⚠ ' + MSG_SOLO_LECTURA + '\n\n' : '') + respuesta.slice(0, 4000), consultas: nombres, sinIA: false, sugerencias: (Array.isArray(j.sugerencias) ? j.sugerencias : []).map(String).filter(Boolean).slice(0, 3) };
  } catch {
    return { respuesta: 'No pude redactar la respuesta con el modelo; estos son los datos consultados:\n\n' + datos.texto, consultas: nombres, sinIA: true, sugerencias: [] };
  }
}

module.exports = { responder, promptPlan, promptRespuesta, pideCambio, afirmaAccion };
