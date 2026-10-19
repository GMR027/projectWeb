'use strict';
// Conexión con modelos: Ollama (local), APIs compatibles con OpenAI, API de Anthropic y agentes CLI (Claude Code, opencode)
const { execFile, spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const httpNodo = require('http');
const https = require('https');
const { Readable } = require('stream');

const TIPOS = {
  ollama: { etiqueta: 'Ollama (local)', url: 'http://localhost:11434', clave: false },
  openai: { etiqueta: 'API compatible con OpenAI', url: 'https://api.openai.com/v1', clave: true },
  anthropic: { etiqueta: 'API de Anthropic', url: 'https://api.anthropic.com', clave: true },
  'claude-code': { etiqueta: 'Claude Code (CLI, tu suscripción)', bin: 'claude', args: p => ['-p', p], imagenes: true },
  'opencode-api': { etiqueta: 'opencode Zen (API key)', url: 'https://opencode.ai/zen/v1', clave: true },
  opencode: { etiqueta: 'opencode (CLI)', bin: 'opencode', args: p => ['run', p] },
};
const esOpenAI = t => t === 'openai' || t === 'opencode-api';   // opencode Zen habla el protocolo de OpenAI
const TIMEOUT_MS = 120000;

// Uso de la suscripción de Claude Code: % de las ventanas de 5 h / 7 días (las informa el propio CLI) y tokens gastados hoy desde esta app
let usoArchivo = null;
let usoCli = { ventanas: {}, actualizado: 0, hoy: { fecha: '', entrada: 0, salida: 0, cache: 0, costo: 0, llamadas: 0 } };
function usoConfigurar(ruta) { usoArchivo = ruta; try { usoCli = { ...usoCli, ...JSON.parse(fs.readFileSync(ruta, 'utf8')) }; } catch { /* sin datos previos */ } }
const usoGuardar = () => { if (usoArchivo) fs.writeFile(usoArchivo, JSON.stringify(usoCli), () => {}); };
const diaActual = () => new Date().toLocaleDateString('sv');
function usoVentanas(info) {
  const w = info?.unifiedWindows || (info?.rateLimitType ? { [info.rateLimitType]: { utilization: info.utilization, resetsAt: info.resetsAt } } : null);
  if (!w) return;
  for (const [k, v] of Object.entries(w)) if (typeof v?.utilization === 'number') usoCli.ventanas[k] = { utilization: v.utilization, resetsAt: v.resetsAt };
  usoCli.actualizado = Date.now(); usoGuardar();
}
function usoResultado(r) {
  const u = r?.usage; if (!u) return;
  if (usoCli.hoy.fecha !== diaActual()) usoCli.hoy = { fecha: diaActual(), entrada: 0, salida: 0, cache: 0, costo: 0, llamadas: 0 };
  const h = usoCli.hoy;
  h.entrada += u.input_tokens || 0; h.salida += u.output_tokens || 0; h.cache += (u.cache_read_input_tokens || 0) + (u.cache_creation_input_tokens || 0);
  h.costo += r.total_cost_usd || 0; h.llamadas++; usoGuardar();
}
const uso = () => (usoCli.hoy.fecha === diaActual() ? usoCli : { ...usoCli, hoy: { fecha: diaActual(), entrada: 0, salida: 0, cache: 0, costo: 0, llamadas: 0 } });
/** Consulta mínima para que el CLI informe el % de uso de la suscripción (solo si el dato tiene más de `minutos`) */
function usoSondear(minutos = 10) {
  if (Date.now() - usoCli.actualizado < minutos * 60000) return Promise.resolve(uso());
  return new Promise(resolve => {
    const cwd = fs.mkdtempSync(path.join(os.tmpdir(), 'pwd-'));
    const hijo = spawn('claude', ['-p', 'ok', '--output-format', 'stream-json', '--verbose', '--no-session-persistence', '--tools', ''], { cwd, env: entorno(), stdio: ['ignore', 'pipe', 'ignore'] });
    let resto = '';
    const fin = () => { clearTimeout(tope); fs.rm(cwd, { recursive: true, force: true }, () => {}); resolve(uso()); };
    const tope = setTimeout(() => { hijo.kill(); }, 45000);
    const linea = l => { try { const j = JSON.parse(l); if (j.type === 'rate_limit_event') usoVentanas(j.rate_limit_info); else if (j.type === 'result') usoResultado(j); } catch { /* línea parcial */ } };
    hijo.stdout.on('data', d => { resto += d; const ps = resto.split('\n'); resto = ps.pop(); ps.forEach(linea); });
    hijo.on('error', fin); hijo.on('close', fin);
  });
}

function urlBase(p) {
  const u = new URL(p.url || TIPOS[p.tipo].url);
  if (!['http:', 'https:'].includes(u.protocol) || u.username || u.password) throw new Error('URL no válida (http/https sin usuario ni contraseña)');
  return u.toString().replace(/\/+$/, '');
}

/**
 * Como fetch pero sin el límite de ~5 min que fetch (undici) impone a la espera de las cabeceras: un modelo en CPU puede
 * tardar mucho en empezar a responder. `timeout` es el tope total de la petición.
 */
function fetchLargo(url, { method = 'GET', headers = {}, body, timeout = TIMEOUT_MS } = {}) {
  return new Promise((resolve, reject) => {
    const u = new URL(url);
    const req = (u.protocol === 'https:' ? https : httpNodo).request(u, { method, headers: { ...headers, ...(body ? { 'Content-Length': Buffer.byteLength(body) } : {}) } }, res => {
      const web = Readable.toWeb(res);
      resolve({
        ok: res.statusCode >= 200 && res.statusCode < 300, status: res.statusCode, body: web,
        text: async () => { const l = web.getReader(), d = new TextDecoder(); let t = ''; for (;;) { const { done, value } = await l.read(); if (done) return t + d.decode(); t += d.decode(value, { stream: true }); } },
      });
    });
    const tope = setTimeout(() => req.destroy(new Error('Tiempo de espera agotado (' + Math.round(timeout / 60000) + ' min)')), timeout);
    req.on('close', () => clearTimeout(tope));
    req.on('error', e => reject(new Error(e.code === 'ECONNREFUSED' ? 'No se pudo conectar con ' + u.host + ' (¿está en marcha?)' : e.message)));
    req.end(body);
  });
}

async function http(url, opciones, timeout = TIMEOUT_MS) {
  const r = await fetchLargo(url, { ...opciones, timeout });
  const texto = await r.text();
  let j = null; try { j = JSON.parse(texto); } catch { /* respuesta no JSON */ }
  if (!r.ok) throw new Error('El servidor respondió ' + r.status + (j && (j.error?.message || j.error) ? ': ' + (j.error.message || j.error) : ''));
  return j;
}

/** Al abrir la app desde el menú (no desde un terminal) el PATH no incluye donde suelen instalarse los agentes CLI */
function entorno() {
  const extra = [path.join(os.homedir(), '.local', 'bin'), path.join(os.homedir(), '.claude', 'local'), path.join(os.homedir(), '.npm-global', 'bin'), path.join(os.homedir(), '.bun', 'bin'), '/usr/local/bin', '/opt/homebrew/bin', '/usr/bin', '/bin'];
  const actual = (process.env.PATH || '').split(path.delimiter).filter(Boolean);
  return { ...process.env, PATH: [...actual, ...extra.filter(d => !actual.includes(d))].join(path.delimiter) };
}

function errorCli(bin, err, errout) {
  return new Error(err.code === 'ENOENT' ? bin + ' no está instalado o no está en el PATH' : (errout || err.message).trim().slice(0, 400));
}

function cli(bin, args, timeout = TIMEOUT_MS) {
  return new Promise((resolve, reject) => {
    // cwd temporal: el agente no debe tocar ningún proyecto del usuario
    const cwd = fs.mkdtempSync(path.join(os.tmpdir(), 'pwd-'));
    execFile(bin, args, { cwd, timeout, maxBuffer: 8 * 1024 * 1024, env: entorno() }, (err, out, errout) => {
      fs.rm(cwd, { recursive: true, force: true }, () => {});
      if (err) reject(errorCli(bin, err, errout));
      else resolve(out.trim());
    });
  });
}

/** Los agentes CLI reciben un solo texto: la instrucción de sistema (si hay) y el historial completo van dentro de él */
function guion(mensajes) {
  const dialogo = mensajes.filter(m => m.role !== 'system');
  const ultimo = dialogo.pop();
  if (!ultimo) return '';
  if (!dialogo.length) return ultimo.content;
  return 'Conversación hasta ahora:\n\n' + dialogo.map(m => (m.role === 'assistant' ? 'Asistente' : 'Usuario') + ': ' + m.content).join('\n\n')
    + '\n\nResponde ahora al último mensaje del usuario, teniendo en cuenta lo anterior:\n\n' + ultimo.content;
}
const sistemaDe = mensajes => mensajes.filter(m => m.role === 'system').map(m => m.content).join('\n\n');
const EXT_IMG = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/jpg': 'jpg', 'image/webp': 'webp', 'image/gif': 'gif' };

/**
 * Claude Code con la sesión de suscripción del terminal: historial e instrucción de sistema completos, imágenes (se dejan en la
 * carpeta temporal y se leen con la herramienta Read, lo único permitido) y progreso en streaming.
 */
function claudeCode(mensajes, opts, timeout) {
  return new Promise((resolve, reject) => {
    const cwd = fs.mkdtempSync(path.join(os.tmpdir(), 'pwd-'));
    const limpiar = () => fs.rm(cwd, { recursive: true, force: true }, () => {});
    let prompt = guion(mensajes);
    const rutas = (opts.imagenes || []).map((im, i) => {
      const f = 'imagen-' + (i + 1) + '.' + (EXT_IMG[im.mime] || 'jpg');
      fs.writeFileSync(path.join(cwd, f), Buffer.from(im.base64, 'base64'));
      return f;
    });
    if (rutas.length) prompt = 'Se adjuntan ' + rutas.length + ' imagen(es) en estos archivos: ' + rutas.join(', ') + '. Ábrelas con la herramienta Read antes de responder.\n\n' + prompt;
    const sistema = sistemaDe(mensajes);
    const args = ['-p', prompt, '--output-format', 'stream-json', '--verbose', '--include-partial-messages', '--no-session-persistence',
      ...(rutas.length ? ['--tools', 'Read', '--allowedTools', 'Read'] : ['--tools', '']), ...(sistema ? ['--append-system-prompt', sistema] : [])];
    const hijo = spawn('claude', args, { cwd, env: entorno(), stdio: ['ignore', 'pipe', 'pipe'] });
    let resto = '', final = null, n = 0, errtxt = '', terminado = false;
    const acabar = (f, v) => { if (terminado) return; terminado = true; clearTimeout(tope); limpiar(); f(v); };
    const tope = setTimeout(() => { hijo.kill(); acabar(reject, new Error('Tiempo de espera agotado (' + Math.round(timeout / 60000) + ' min)')); }, timeout);
    opts.alAvanzar?.({ tokens: 0, fase: 'leyendo' });
    const linea = l => {
      let j; try { j = JSON.parse(l); } catch { return; }
      if (j.type === 'stream_event' && j.event?.delta?.type === 'text_delta') { n++; if (n % 5 === 0) opts.alAvanzar?.({ tokens: n, fase: 'escribiendo' }); }
      else if (j.type === 'rate_limit_event') usoVentanas(j.rate_limit_info);
      else if (j.type === 'result') { final = j; usoResultado(j); }
    };
    hijo.stdout.on('data', d => { resto += d; const partes = resto.split('\n'); resto = partes.pop(); partes.forEach(linea); });
    hijo.stderr.on('data', d => { errtxt += d; });
    hijo.on('error', e => acabar(reject, errorCli('claude', e, '')));
    hijo.on('close', () => {
      if (resto.trim()) linea(resto);
      if (final && !final.is_error) { opts.alAvanzar?.({ tokens: n, fase: 'listo' }); return acabar(resolve, String(final.result ?? '').trim()); }
      const motivo = String(final?.result || errtxt || 'Claude Code terminó sin respuesta').trim();
      acabar(reject, new Error(/log ?in|auth|credential|401/i.test(motivo) ? 'Claude Code no tiene sesión: ejecuta «claude» en el terminal e inicia sesión (/login). ' + motivo.slice(0, 200) : motivo.slice(0, 400)));
    });
  });
}

/**
 * @param mensajes [{role:'user'|'assistant', content}]
 * @param opts {timeout (ms), maxTokens, numCtx}: para tareas largas como el Programador de actividades
 */
/** Adjunta imágenes [{mime, base64}] al primer mensaje del usuario con el formato de cada proveedor */
function conImagenes(tipo, mensajes, imagenes) {
  if (!imagenes?.length) return mensajes;
  let hecho = false;
  return mensajes.map(m => {
    if (hecho || m.role !== 'user') return m;
    hecho = true;
    if (tipo === 'ollama') return { ...m, images: imagenes.map(i => i.base64) };
    if (esOpenAI(tipo)) return { ...m, content: [{ type: 'text', text: m.content }, ...imagenes.map(i => ({ type: 'image_url', image_url: { url: 'data:' + i.mime + ';base64,' + i.base64 } }))] };
    return { ...m, content: [...imagenes.map(i => ({ type: 'image', source: { type: 'base64', media_type: i.mime, data: i.base64 } })), { type: 'text', text: m.content }] };
  });
}

async function chat(p, clave, mensajes, opts = {}) {
  const timeout = opts.timeout || TIMEOUT_MS;
  const t = TIPOS[p.tipo];
  if (!t) throw new Error('Tipo de conexión desconocido');
  if (opts.imagenes?.length && t.bin && !t.imagenes) throw new Error('Esta conexión (agente de línea de comandos) no admite imágenes: usa Ollama con un modelo de visión o una API con visión.');
  if (p.tipo === 'claude-code') return claudeCode(mensajes, opts, timeout);
  if (t.bin) return cli(t.bin, t.args([sistemaDe(mensajes), guion(mensajes)].filter(Boolean).join('\n\n')), timeout);
  mensajes = conImagenes(p.tipo, mensajes, opts.imagenes);
  if (!p.modelo) throw new Error('Indica el modelo en la conexión');
  const base = urlBase(p);
  if (p.tipo === 'ollama') {
    const cuerpo = { model: p.modelo, messages: mensajes, stream: !!opts.alAvanzar, ...(opts.numCtx ? { options: { num_ctx: opts.numCtx } } : {}) };
    if (!opts.alAvanzar) return (await http(base + '/api/chat', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cuerpo) }, timeout))?.message?.content ?? '';
    return ollamaChatStream(base, cuerpo, timeout, opts.alAvanzar);
  }
  if (!clave) throw new Error('Falta la API key de esta conexión');
  if (esOpenAI(p.tipo)) {
    const j = await http(base + '/chat/completions', { method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + clave }, body: JSON.stringify({ model: p.modelo, messages: mensajes }) }, timeout);
    return j?.choices?.[0]?.message?.content ?? '';
  }
  const j = await http(base + '/v1/messages', { method: 'POST', headers: { 'Content-Type': 'application/json', 'x-api-key': clave, 'anthropic-version': '2023-06-01' }, body: JSON.stringify({ model: p.modelo, max_tokens: opts.maxTokens || 4096, messages: mensajes }) }, timeout);
  return (j?.content || []).filter(b => b.type === 'text').map(b => b.text).join('');
}

async function modelos(p, clave) {
  if (p.tipo === 'ollama') return ((await http(urlBase(p) + '/api/tags', {}))?.models || []).map(m => m.name);
  if (esOpenAI(p.tipo)) return ((await http(urlBase(p) + '/models', { headers: { Authorization: 'Bearer ' + clave } }))?.data || []).map(m => m.id);
  return [];
}

/** Qué agentes CLI hay instalados */
async function detectarCli() {
  const r = {};
  for (const [k, t] of Object.entries(TIPOS)) {
    if (!t.bin) continue;
    r[k] = await new Promise(res => execFile('which', [t.bin], { env: entorno() }, e => res(!e)));
  }
  return r;
}

/** Comprueba que la conexión funciona; devuelve un texto de éxito o lanza un error explicativo */
async function probar(p, clave) {
  const t = TIPOS[p.tipo];
  if (!t) throw new Error('Tipo de conexión desconocido');
  if (t.bin) {
    const v = await cli(t.bin, ['--version']);
    if (p.tipo === 'claude-code') {   // además de la instalación, comprueba que haya sesión iniciada
      const e = JSON.parse(await cli(t.bin, ['auth', 'status']).catch(() => '{}'));
      if (!e.loggedIn) throw new Error('Claude Code está instalado (' + v.split('\n')[0] + ') pero no hay sesión: ejecuta «claude» en el terminal e inicia sesión (/login).');
      return 'Claude Code instalado (' + v.split('\n')[0] + ') con la sesión de ' + (e.authMethod || 'suscripción') + ' iniciada.';
    }
    return t.bin + ' instalado (' + v.split('\n')[0] + ').';
  }
  if (p.tipo === 'ollama') {
    const lista = await modelos(p, clave);
    if (p.modelo && !lista.some(n => n === p.modelo || n.split(':')[0] === p.modelo)) throw new Error('Ollama responde, pero el modelo «' + p.modelo + '» no está descargado. Ejecuta: ollama pull ' + p.modelo);
    return 'Ollama responde (' + lista.length + ' modelos instalados).';
  }
  if (!clave) throw new Error('Falta la API key');
  const base = urlBase(p);
  if (esOpenAI(p.tipo)) await http(base + '/models', { headers: { Authorization: 'Bearer ' + clave } });
  else await http(base + '/v1/models', { headers: { 'x-api-key': clave, 'anthropic-version': '2023-06-01' } });
  return 'Conexión correcta: la API aceptó la clave.';
}

// ---- Gestión de modelos de Ollama: descargar (pull), listar, cargar en memoria y eliminar ----
const NOMBRE_MODELO = /^[A-Za-z0-9][\w.\-]*(\/[\w.\-]+)*(:[\w.\-]+)?$/;
const urlOllama = url => urlBase({ tipo: 'ollama', url });
function nombreModelo(m) {
  if (!NOMBRE_MODELO.test(String(m || '')) || m.length > 100) throw new Error('Nombre de modelo no válido');
  return m;
}
const JSON_POST = { 'Content-Type': 'application/json' };

/** Chat de Ollama en streaming: llama a alAvanzar({tokens, fase}) mientras genera (fase 'leyendo' hasta el primer token) */
async function ollamaChatStream(base, cuerpo, timeout, alAvanzar) {
  const r = await fetchLargo(base + '/api/chat', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cuerpo), timeout });
  if (!r.ok) throw new Error('El servidor respondió ' + r.status + ': ' + (await r.text()).slice(0, 200));
  const lector = r.body.getReader(), dec = new TextDecoder();
  let resto = '', texto = '', n = 0;
  alAvanzar({ tokens: 0, fase: 'leyendo' });
  const linea = l => {
    if (!l.trim()) return;
    let j; try { j = JSON.parse(l); } catch { return; }
    if (j.error) throw new Error(j.error);
    if (j.message?.content) { texto += j.message.content; n++; if (n % 5 === 0) alAvanzar({ tokens: n, fase: 'escribiendo' }); }
  };
  for (;;) {
    const { done, value } = await lector.read();
    if (done) break;
    resto += dec.decode(value, { stream: true });
    const partes = resto.split('\n'); resto = partes.pop();
    partes.forEach(linea);
  }
  linea(resto);
  alAvanzar({ tokens: n, fase: 'listo' });
  return texto;
}

/** Estado de Ollama: si responde, qué modelos hay descargados y cuáles están cargados en memoria */
async function ollamaEstado(url) {
  const base = urlOllama(url);
  const binario = await new Promise(res => execFile('which', ['ollama'], e => res(!e)));
  try {
    const tags = await http(base + '/api/tags', {}, 8000);
    const ps = await http(base + '/api/ps', {}, 8000).catch(() => ({ models: [] }));
    return {
      activo: true, binario,
      instalados: (tags?.models || []).map(m => ({ nombre: m.name, tamGB: Math.round((m.size || 0) / 1e8) / 10 })),
      cargados: (ps?.models || []).map(m => m.name),
    };
  } catch (x) {
    return { activo: false, binario, instalados: [], cargados: [], error: x.message };
  }
}

/** Descarga un modelo con progreso. alAvanzar({estado, pct}); signal permite cancelar (lo ya descargado se conserva y se retoma) */
async function ollamaDescargar(url, modelo, alAvanzar, signal) {
  const r = await fetch(urlOllama(url) + '/api/pull', { method: 'POST', headers: JSON_POST, body: JSON.stringify({ model: nombreModelo(modelo), stream: true }), signal });
  if (!r.ok) throw new Error('Ollama respondió ' + r.status + ': ' + (await r.text()).slice(0, 200));
  const lector = r.body.getReader(), dec = new TextDecoder();
  let resto = '', ultimo = '';
  const linea = l => {
    if (!l.trim()) return;
    let j; try { j = JSON.parse(l); } catch { return; }
    if (j.error) throw new Error(j.error);
    ultimo = j.status || ultimo;
    alAvanzar({ estado: ultimo, pct: j.total ? Math.min(100, Math.round((j.completed || 0) / j.total * 100)) : null });
  };
  for (;;) {
    const { done, value } = await lector.read();
    if (done) break;
    resto += dec.decode(value, { stream: true });
    const partes = resto.split('\n'); resto = partes.pop();
    partes.forEach(linea);
  }
  linea(resto);
  if (ultimo !== 'success') throw new Error('La descarga terminó sin confirmar (último estado: ' + (ultimo || 'ninguno') + ')');
}

/** Carga el modelo en memoria (o lo descarga de memoria con cargar=false) para que la primera respuesta no espere */
async function ollamaCargar(url, modelo, cargar = true) {
  await http(urlOllama(url) + '/api/generate', { method: 'POST', headers: JSON_POST, body: JSON.stringify({ model: nombreModelo(modelo), keep_alive: cargar ? '30m' : 0 }) }, 300000);
}

async function ollamaEliminar(url, modelo) {
  const r = await fetch(urlOllama(url) + '/api/delete', { method: 'DELETE', headers: JSON_POST, body: JSON.stringify({ model: nombreModelo(modelo) }), signal: AbortSignal.timeout(30000) });
  if (!r.ok) throw new Error('No se pudo eliminar (' + r.status + ')');
}

/**
 * Estado de una conexión para mostrarlo al usuario: {nivel: 'ok' | 'aviso' | 'error', texto}
 * ok = lista para responder; aviso = funciona pero con una salvedad; error = no puede responder ahora
 */
async function estado(p, clave) {
  const t = TIPOS[p.tipo];
  if (!t) return { nivel: 'error', texto: 'Tipo de conexión desconocido' };
  try {
    if (p.tipo === 'ollama') {
      const e = await ollamaEstado(p.url);
      if (!e.activo) return { nivel: 'error', texto: e.binario ? 'Ollama está instalado pero no responde' : 'Ollama no está instalado o no responde' };
      if (!p.modelo) return { nivel: 'error', texto: 'La conexión no tiene modelo elegido' };
      const hay = e.instalados.some(m => m.nombre === p.modelo || m.nombre === p.modelo + ':latest');
      if (!hay) return { nivel: 'error', texto: 'El modelo «' + p.modelo + '» no está descargado' };
      return e.cargados.some(m => m === p.modelo || m === p.modelo + ':latest')
        ? { nivel: 'ok', texto: 'Lista · modelo cargado en memoria' }
        : { nivel: 'ok', texto: 'Lista · el modelo se cargará en la primera consulta' };
    }
    const texto = await probar(p, clave);
    return { nivel: t.bin ? 'aviso' : 'ok', texto: t.bin ? 'Instalado · la sesión de suscripción se valida al usarlo' : 'Lista · ' + texto.replace(/\.$/, '') };
  } catch (x) {
    return { nivel: 'error', texto: x.message };
  }
}

module.exports = { uso, usoConfigurar, usoSondear, estado, TIPOS, probar, chat, modelos, detectarCli, urlBase, ollamaEstado, ollamaDescargar, ollamaCargar, ollamaEliminar, nombreModelo };
