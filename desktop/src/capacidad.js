'use strict';
// Qué puede hacer bien cada conexión de IA y qué herramientas habilita. Todo es heurístico y se calcula sin llamar al modelo.
const NIVELES = ['basico', 'medio', 'grande'];
const ETIQUETA_NIVEL = { basico: 'modelo pequeño', medio: 'modelo mediano', grande: 'modelo grande o en la nube' };
const VISION = /llava|vision|minicpm-v|moondream|bakllava|gemma-?3|qwen[\d.]*-?vl|\bvl\b|-vl|pixtral|gpt-4o|gpt-4\.1|gpt-5|o4|claude|gemini/i;

/** Tamaño del modelo en miles de millones de parámetros según su nombre (llama3.1:8b → 8), o null */
function parametrosB(modelo) {
  const m = /(\d+(?:\.\d+)?)\s*b\b/i.exec(String(modelo || ''));
  return m ? parseFloat(m[1]) : null;
}

/**
 * @param conexion {tipo, modelo, url, nombre}
 * @returns {{nivel: 'basico'|'medio'|'grande', vision: boolean, local: boolean, nube: boolean, parametros: number|null}}
 */
function perfil(conexion) {
  const c = conexion || {};
  const tipo = c.tipo || '';
  const b = parametrosB(c.modelo);
  // Agentes de línea de comandos (Claude Code, opencode) y APIs de proveedores: modelos grandes en la nube; los CLI solo reciben texto
  if (tipo === 'claude-code' || tipo === 'opencode') return { nivel: 'grande', vision: false, local: false, nube: true, parametros: null };
  const url = String(c.url || '');
  const localUrl = /^https?:\/\/(localhost|127\.|192\.168\.|10\.|\[::1\])/i.test(url);
  if (tipo === 'anthropic' || ((tipo === 'openai' || tipo === 'opencode-api') && !localUrl)) return { nivel: 'grande', vision: VISION.test(String(c.modelo || (tipo === 'anthropic' ? 'claude' : ''))), local: false, nube: true, parametros: b };
  // Ollama o una API compatible apuntando a esta red: el tamaño sale del nombre del modelo
  let nivel = 'basico';
  if (b !== null) nivel = b >= 35 ? 'grande' : b >= 12 ? 'medio' : 'basico';
  return { nivel, vision: VISION.test(String(c.modelo || '')), local: true, nube: false, parametros: b };
}

const rango = n => NIVELES.indexOf(n);

/**
 * Evalúa una herramienta con el perfil de la conexión.
 * `requiere`: {minimo, recomendado, vision}. minimo = por debajo no se habilita; recomendado = por debajo funciona con límites.
 * @returns {{estado: 'ok'|'limitado'|'bloqueado', texto: string}}
 */
function evaluar(requiere, p) {
  const r = { minimo: 'basico', recomendado: 'basico', vision: false, ...(requiere || {}) };
  // Sin conexión no se bloquea nada: cada herramienta ya avisa que primero hay que guardar una
  if (!p) return { estado: 'limitado', texto: 'Aún no hay una conexión de IA: guarda una en «Ajustes → Configuración de IA».' };
  if (r.vision && !p.vision) return { estado: 'bloqueado', texto: 'Requiere un modelo con visión (por ejemplo llava o qwen-vl en Ollama, o una API/suscripción con imágenes).' };
  if (rango(p.nivel) < rango(r.minimo)) return { estado: 'bloqueado', texto: 'Requiere ' + ETIQUETA_NIVEL[r.minimo] + ' o mayor (tu conexión es ' + ETIQUETA_NIVEL[p.nivel] + '). Usa un modelo más potente, una API o tu suscripción.' };
  if (rango(p.nivel) < rango(r.recomendado)) return { estado: 'limitado', texto: 'Funciona, pero rinde mejor con ' + ETIQUETA_NIVEL[r.recomendado] + ' o mayor. Con tu conexión el análisis será más corto o menos fino.' };
  return { estado: 'ok', texto: 'Funciona bien con tu conexión actual.' };
}

module.exports = { parametrosB, perfil, evaluar, NIVELES, ETIQUETA_NIVEL };
