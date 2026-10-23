'use strict';
// Transcripción de voz a texto, siempre en el equipo del usuario salvo que elija una API compatible con OpenAI.
// Motores: whisper.cpp (whisper-cli + modelo ggml), faster-whisper (Python) o un endpoint /audio/transcriptions.
const { execFile } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { PATH_SISTEMA } = require('./providers');   // la app puede arrancar sin PATH (menú de escritorio)

/** Entorno de los procesos de voz: el del proceso y, si vino vacío, el del sistema (sin añadir rutas de agentes) */
const entornoVoz = () => ({ ...process.env, PATH: process.env.PATH ? process.env.PATH : PATH_SISTEMA });

const MOTORES = ['auto', 'whisper-cli', 'faster-whisper', 'openai'];
const IDIOMAS = ['auto', 'es', 'en'];
const BINARIOS_CPP = ['whisper-cli', 'whisper-cpp', 'whisper.cpp'];
const PY_SCRIPT = [
  'import sys',
  'from faster_whisper import WhisperModel',
  'f, m, l = sys.argv[1], sys.argv[2], sys.argv[3]',
  "model = WhisperModel(m or 'base', device='cpu', compute_type='int8')",
  "segs, info = model.transcribe(f, language=(l if l != 'auto' else None), vad_filter=True)",
  "print(' '.join(s.text.strip() for s in segs))",
].join('\n');

const INSTRUCCIONES = 'Para dictar y grabar reuniones instala un motor de transcripción local: (a) whisper.cpp — compílalo o instala «whisper-cli» y descarga un modelo (por ejemplo ggml-base.bin o ggml-small.bin) y pon su ruta en Ajustes → Voz; o (b) faster-whisper — «pip install faster-whisper» (descarga el modelo la primera vez). También puedes elegir una API compatible con OpenAI (/audio/transcriptions). Mientras tanto puedes escribir o pegar el texto.';

function ejecutar(bin, args, { timeout = 60000, env } = {}) {
  return new Promise((resolve, reject) => execFile(bin, args, { timeout, maxBuffer: 64 * 1024 * 1024, env: env || entornoVoz() }, (e, out, err) => e ? reject(Object.assign(e, { stderr: String(err || '') })) : resolve({ out: String(out), err: String(err || '') })));
}
async function cual(bin, env = entornoVoz()) {
  for (const d of String(env.PATH || '').split(path.delimiter)) {
    if (!d) continue;
    const r = path.join(d, bin);
    try { fs.accessSync(r, fs.constants.X_OK); if (fs.statSync(r).isFile()) return r; } catch { /* siguiente */ }
  }
  return null;
}

/** Ruta del modelo ggml: la configurada, la de WHISPER_MODEL o las ubicaciones habituales */
function modeloCpp(voz) {
  const c = [voz?.modelo, process.env.WHISPER_MODEL, ...['small', 'base', 'tiny'].flatMap(t => [path.join(os.homedir(), '.local/share/whisper/ggml-' + t + '.bin'), path.join(os.homedir(), 'whisper.cpp/models/ggml-' + t + '.bin'), '/usr/share/whisper/ggml-' + t + '.bin'])].filter(Boolean);
  return c.find(r => { try { return r.endsWith('.bin') && fs.statSync(r).isFile(); } catch { return false; } }) || null;
}

/** ¿Qué motor se puede usar ahora? `conexion` = la conexión OpenAI-compatible elegida (si hay) */
async function estado(voz, conexion) {
  const v = { motor: 'auto', modelo: '', idioma: 'auto', conexion: '', ...(voz || {}) };
  const probar = {
    'whisper-cli': async () => {
      const bin = (v.binario && fs.existsSync(v.binario) ? v.binario : null) || (await Promise.all(BINARIOS_CPP.map(b => cual(b)))).find(Boolean);
      if (!bin) return { ok: false, falta: 'No se encontró «whisper-cli» (whisper.cpp).' };
      const modelo = modeloCpp(v);
      return modelo ? { ok: true, detalle: 'whisper.cpp (' + path.basename(modelo) + ')', bin, modelo } : { ok: false, falta: 'Falta el modelo de whisper.cpp (archivo ggml-*.bin): indica su ruta en Ajustes → Voz.' };
    },
    'faster-whisper': async () => {
      const py = await cual('python3');
      if (!py) return { ok: false, falta: 'No se encontró Python 3.' };
      try { await ejecutar(py, ['-I', '-c', 'import faster_whisper'], { timeout: 20000 }); return { ok: true, detalle: 'faster-whisper' + (v.modelo ? ' (' + v.modelo + ')' : ' (base)'), bin: py }; } catch { return { ok: false, falta: 'faster-whisper no está instalado («pip install faster-whisper»).' }; }
    },
    openai: async () => (conexion && conexion.tipo === 'openai') ? { ok: true, detalle: 'API compatible con OpenAI (' + (conexion.nombre || conexion.url || '') + ')' } : { ok: false, falta: 'Elige en Ajustes → Voz una conexión de tipo «API compatible con OpenAI».' },
  };
  const orden = v.motor === 'auto' ? ['whisper-cli', 'faster-whisper', 'openai'] : [v.motor];
  const faltas = [];
  for (const m of orden) {
    if (!probar[m]) continue;
    const r = await probar[m]();
    if (r.ok) return { disponible: true, motor: m, detalle: r.detalle, idioma: v.idioma, instrucciones: '', _r: r };
    faltas.push(r.falta);
  }
  return { disponible: false, motor: null, detalle: faltas.join(' '), idioma: v.idioma, instrucciones: INSTRUCCIONES };
}

/** Transcribe un WAV (16 kHz, mono). Devuelve el texto */
async function transcribir(wav, { voz, conexion, clave, idioma }) {
  const e = await estado(voz, conexion);
  if (!e.disponible) throw new Error('No hay un motor de transcripción disponible. ' + e.detalle);
  const lang = IDIOMAS.includes(idioma) ? idioma : (voz?.idioma || 'auto');
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pwd-voz-'));
  const archivo = path.join(dir, 'audio.wav');
  try {
    fs.writeFileSync(archivo, wav, { mode: 0o600 });
    if (e.motor === 'whisper-cli') {
      const { out } = await ejecutar(e._r.bin, ['-m', e._r.modelo, '-f', archivo, '-nt', '-np', ...(lang !== 'auto' ? ['-l', lang] : ['-l', 'auto'])], { timeout: 3 * 3600 * 1000 });
      return limpiar(out);
    }
    if (e.motor === 'faster-whisper') {
      const { out } = await ejecutar(e._r.bin, ['-I', '-c', PY_SCRIPT, archivo, voz?.modelo || 'base', lang], { timeout: 3 * 3600 * 1000 });
      return limpiar(out);
    }
    // API compatible con OpenAI
    const base = new URL(conexion.url || 'https://api.openai.com/v1').toString().replace(/\/+$/, '');
    const f = new FormData();
    f.append('file', new Blob([wav], { type: 'audio/wav' }), 'audio.wav');
    f.append('model', voz?.modelo || 'whisper-1');
    if (lang !== 'auto') f.append('language', lang);
    const r = await fetch(base + '/audio/transcriptions', { method: 'POST', headers: clave ? { Authorization: 'Bearer ' + clave } : {}, body: f, signal: AbortSignal.timeout(30 * 60 * 1000) });
    const j = await r.json().catch(() => null);
    if (!r.ok) throw new Error('La API de transcripción respondió ' + r.status + (j?.error?.message ? ': ' + j.error.message : ''));
    return limpiar(j?.text || '');
  } finally { fs.rmSync(dir, { recursive: true, force: true }); }
}

function limpiar(t) {
  return String(t).replace(/\[[^\]]*\]/g, ' ').replace(/\s+/g, ' ').trim();   // quita marcas como [BLANK_AUDIO] y espacios sobrantes
}

/** Valida los ajustes de voz que llegan de la interfaz */
function sanear(v) {
  const x = v && typeof v === 'object' ? v : {};
  return { motor: MOTORES.includes(x.motor) ? x.motor : 'auto', modelo: String(x.modelo || '').slice(0, 300).trim(), idioma: IDIOMAS.includes(x.idioma) ? x.idioma : 'auto', conexion: String(x.conexion || '').slice(0, 80), binario: String(x.binario || '').slice(0, 300).trim() };
}

module.exports = { estado, transcribir, sanear, limpiar, MOTORES, IDIOMAS, INSTRUCCIONES };
