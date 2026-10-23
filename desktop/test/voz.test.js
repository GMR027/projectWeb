'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');
const voz = require('../src/voz');
const Wav = require('../src/renderer/wav');

test('WAV: cabecera correcta, mezcla a mono y recorte', () => {
  const m = Wav.mezclarMono([Float32Array.from([1, 0, -1]), Float32Array.from([0, 0, -1])]);
  assert.deepStrictEqual(Array.from(m), [0.5, 0, -1]);
  const w = Wav.codificarWav(Float32Array.from([0, 1, -1, 2]), 16000);
  assert.strictEqual(Buffer.from(w.slice(0, 4)).toString(), 'RIFF');
  assert.strictEqual(Buffer.from(w.slice(8, 12)).toString(), 'WAVE');
  const v = new DataView(w.buffer);
  assert.strictEqual(v.getUint32(24, true), 16000);
  assert.strictEqual(v.getUint32(40, true), 8);
  assert.strictEqual(v.getInt16(46, true), 32767);   // 1 → máx.
  assert.strictEqual(v.getInt16(48, true), -32768);  // -1 → mín.
  assert.strictEqual(v.getInt16(50, true), 32767);   // 2 se recorta a 1
});

test('sanear: solo valores permitidos', () => {
  assert.deepStrictEqual(voz.sanear({ motor: 'rm -rf', idioma: 'fr', modelo: ' /x/m.bin ', conexion: 'a'.repeat(200) }), { motor: 'auto', modelo: '/x/m.bin', idioma: 'auto', conexion: 'a'.repeat(80), binario: '' });
  assert.strictEqual(voz.sanear(null).motor, 'auto');
  assert.strictEqual(voz.sanear({ motor: 'openai', idioma: 'es' }).idioma, 'es');
});

test('limpiar quita marcas y espacios', () => {
  assert.strictEqual(voz.limpiar(' [BLANK_AUDIO]  hola   mundo\n[música] '), 'hola mundo');
});

test('estado: sin motores disponible=false con instrucciones; con OpenAI-compatible sí', async () => {
  const viejo = process.env.PATH, sinPath = fs.mkdtempSync(path.join(os.tmpdir(), 'vacio-'));
  process.env.PATH = sinPath;
  try {
    const e = await voz.estado({ motor: 'auto' }, null);
    assert.strictEqual(e.disponible, false);
    assert.match(e.instrucciones, /whisper/i);
    const o = await voz.estado({ motor: 'openai' }, { tipo: 'openai', nombre: 'API', url: 'http://x/v1' });
    assert.strictEqual(o.disponible, true);
    assert.strictEqual(o.motor, 'openai');
    const n = await voz.estado({ motor: 'openai' }, { tipo: 'ollama' });
    assert.strictEqual(n.disponible, false);
  } finally { process.env.PATH = viejo; fs.rmSync(sinPath, { recursive: true, force: true }); }
});

test('whisper-cli simulado: se invoca con modelo, idioma y -nt, y se limpia la salida', async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'fake-')), modelo = path.join(dir, 'ggml-base.bin'), reg = path.join(dir, 'args.txt');
  fs.writeFileSync(modelo, 'x');
  fs.writeFileSync(path.join(dir, 'whisper-cli'), '#!/bin/sh\necho "$@" > "' + reg + '"\necho " [BLANK_AUDIO] Hola equipo, avance al 80 por ciento."\n', { mode: 0o755 });
  const viejo = process.env.PATH; process.env.PATH = dir + path.delimiter + viejo;
  try {
    const t = await voz.transcribir(Buffer.from('RIFFxxxxWAVE'), { voz: { motor: 'whisper-cli', modelo, idioma: 'es' }, idioma: 'es' });
    assert.strictEqual(t, 'Hola equipo, avance al 80 por ciento.');
    const a = fs.readFileSync(reg, 'utf8');
    assert.match(a, /-m .*ggml-base\.bin/); assert.match(a, /-nt/); assert.match(a, /-l es/);
    const auto = await voz.estado({ motor: 'whisper-cli', modelo }, null);
    assert.strictEqual(auto.disponible, true);
  } finally { process.env.PATH = viejo; fs.rmSync(dir, { recursive: true, force: true }); }
});

test('la voz encuentra los binarios del sistema aunque la app arranque sin PATH', async () => {
  const PATH = process.env.PATH;
  delete process.env.PATH;   // así arranca la app al abrirla desde el menú del escritorio
  try {
    const e = await voz.estado({ motor: 'faster-whisper' }, null);
    assert.doesNotMatch(e.detalle, /No se encontró Python 3/);   // el PATH del sistema sí trae python3
    if (!e.disponible) assert.match(e.detalle, /faster-whisper no está instalado/);
  } finally { if (PATH !== undefined) process.env.PATH = PATH; }
});

test('API compatible con OpenAI: manda el audio y devuelve el texto', async () => {
  let visto = null;
  const srv = await new Promise(ok => { const s = http.createServer((req, res) => { let n = 0; req.on('data', c => n += c.length); req.on('end', () => { visto = { url: req.url, auth: req.headers.authorization, ct: req.headers['content-type'], n }; res.writeHead(200, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ text: ' Acuerdos de la reunión ' })); }); }).listen(0, '127.0.0.1', () => ok(s)); });
  try {
    const t = await voz.transcribir(Buffer.from('RIFFxxxxWAVE'), { voz: { motor: 'openai', modelo: 'whisper-1' }, conexion: { tipo: 'openai', url: 'http://127.0.0.1:' + srv.address().port + '/v1' }, clave: 'sk-test', idioma: 'es' });
    assert.strictEqual(t, 'Acuerdos de la reunión');
    assert.strictEqual(visto.url, '/v1/audio/transcriptions'); assert.strictEqual(visto.auth, 'Bearer sk-test'); assert.match(visto.ct, /multipart\/form-data/);
  } finally { srv.close(); }
});
