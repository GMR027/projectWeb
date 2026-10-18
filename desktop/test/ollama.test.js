'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const { ollamaEstado, ollamaDescargar, ollamaCargar, ollamaEliminar, nombreModelo } = require('../src/providers');

const leer = req => new Promise(ok => { let d = ''; req.on('data', c => d += c); req.on('end', () => ok(d ? JSON.parse(d) : null)); });
const servidor = manejar => new Promise(ok => { const s = http.createServer(manejar).listen(0, '127.0.0.1', () => ok(s)); });
const url = s => 'http://127.0.0.1:' + s.address().port;

test('ollamaEstado: modelos instalados y cargados; inactivo si no responde', async () => {
  const s = await servidor((req, res) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(req.url === '/api/tags' ? { models: [{ name: 'qwen2.5:7b', size: 4.7e9 }] } : { models: [{ name: 'qwen2.5:7b' }] }));
  });
  const e = await ollamaEstado(url(s));
  assert.strictEqual(e.activo, true);
  assert.deepStrictEqual(e.instalados, [{ nombre: 'qwen2.5:7b', tamGB: 4.7 }]);
  assert.deepStrictEqual(e.cargados, ['qwen2.5:7b']);
  s.close();
  assert.strictEqual((await ollamaEstado('http://127.0.0.1:1')).activo, false);
});

test('ollamaDescargar: reporta el progreso en porcentaje y exige success', async () => {
  const s = await servidor(async (req, res) => {
    const b = await leer(req); assert.strictEqual(b.model, 'llama3.2:3b');
    res.write(JSON.stringify({ status: 'pulling manifest' }) + '\n');
    res.write(JSON.stringify({ status: 'pulling abc', total: 200, completed: 50 }) + '\n');
    res.write(JSON.stringify({ status: 'pulling abc', total: 200, completed: 200 }) + '\n{"status":"suc');   // línea partida entre dos trozos
    setTimeout(() => res.end('cess"}\n'), 30);
  });
  const avances = [];
  await ollamaDescargar(url(s), 'llama3.2:3b', p => avances.push(p));
  assert.deepStrictEqual(avances.map(a => a.pct), [null, 25, 100, null]);
  assert.strictEqual(avances.at(-1).estado, 'success');
  s.close();
});

test('ollamaDescargar: error del servidor y descarga incompleta', async () => {
  const s = await servidor((req, res) => { res.end(JSON.stringify({ error: 'model not found' }) + '\n'); });
  await assert.rejects(ollamaDescargar(url(s), 'no-existe', () => {}), /model not found/);
  s.close();
  const s2 = await servidor((req, res) => { res.end(JSON.stringify({ status: 'pulling abc', total: 10, completed: 1 }) + '\n'); });
  await assert.rejects(ollamaDescargar(url(s2), 'x', () => {}), /sin confirmar/);
  s2.close();
});

test('ollamaCargar y ollamaEliminar usan el modelo y keep_alive correctos', async () => {
  const vistos = [];
  const s = await servidor(async (req, res) => { vistos.push([req.method, req.url, await leer(req)]); res.end('{}'); });
  await ollamaCargar(url(s), 'm1'); await ollamaCargar(url(s), 'm1', false); await ollamaEliminar(url(s), 'm1');
  assert.deepStrictEqual(vistos, [['POST', '/api/generate', { model: 'm1', keep_alive: '30m' }], ['POST', '/api/generate', { model: 'm1', keep_alive: 0 }], ['DELETE', '/api/delete', { model: 'm1' }]]);
  s.close();
});

test('nombreModelo rechaza nombres raros', () => {
  assert.strictEqual(nombreModelo('hf.co/user/repo:Q4_K_M'), 'hf.co/user/repo:Q4_K_M');
  for (const m of ['', '../x', 'a b', 'a;b', '-x', 'x'.repeat(101)]) assert.throws(() => nombreModelo(m), /no válido/);
});

test('chat de Ollama en streaming: junta el texto y reporta avance', async () => {
  const { chat, estado } = require('../src/providers');
  const s = await servidor(async (req, res) => {
    const b = await leer(req); assert.strictEqual(b.stream, true);
    for (const t of ['{"a', '":1', '}']) res.write(JSON.stringify({ message: { content: t } }) + '\n');
    res.end(JSON.stringify({ done: true, message: { content: '' } }) + '\n');
  });
  const fases = [];
  const txt = await chat({ tipo: 'ollama', url: url(s), modelo: 'm' }, '', [{ role: 'user', content: 'x' }], { alAvanzar: a => fases.push(a.fase) });
  assert.strictEqual(txt, '{"a":1}');
  assert.deepStrictEqual([fases[0], fases.at(-1)], ['leyendo', 'listo']);
  s.close();
});

test('estado de la conexión: lista, sin descargar y sin Ollama', async () => {
  const { estado } = require('../src/providers');
  const s = await servidor((req, res) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(req.url === '/api/tags' ? { models: [{ name: 'a:1', size: 1e9 }, { name: 'b:1', size: 1e9 }] } : { models: [{ name: 'a:1' }] }));
  });
  const p = m => ({ tipo: 'ollama', url: url(s), modelo: m });
  assert.deepStrictEqual(await estado(p('a:1'), ''), { nivel: 'ok', texto: 'Lista · modelo cargado en memoria' });
  assert.strictEqual((await estado(p('b:1'), '')).nivel, 'ok');
  assert.match((await estado(p('b:1'), '')).texto, /primera consulta/);
  assert.match((await estado(p('z:9'), '')).texto, /no está descargado/);
  s.close();
  assert.strictEqual((await estado({ tipo: 'ollama', url: 'http://127.0.0.1:1', modelo: 'a:1' }, '')).nivel, 'error');
});
