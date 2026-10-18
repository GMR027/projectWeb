'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const { chat, modelos, urlBase } = require('../src/providers');
const { comparar } = require('../src/updater');

function servidor(manejar) {
  return new Promise(ok => { const s = http.createServer(manejar).listen(0, '127.0.0.1', () => ok(s)); });
}
const json = (res, o, c = 200) => { res.writeHead(c, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };
const cuerpo = req => new Promise(ok => { let d = ''; req.on('data', c => d += c); req.on('end', () => ok(d ? JSON.parse(d) : null)); });

test('ollama: chat y lista de modelos', async () => {
  const s = await servidor(async (req, res) => {
    if (req.url === '/api/tags') return json(res, { models: [{ name: 'llama3.1' }] });
    const b = await cuerpo(req); json(res, { message: { content: 'eco:' + b.messages[0].content + ':' + b.model } });
  });
  const p = { tipo: 'ollama', url: 'http://127.0.0.1:' + s.address().port, modelo: 'llama3.1' };
  assert.strictEqual(await chat(p, '', [{ role: 'user', content: 'hola' }]), 'eco:hola:llama3.1');
  assert.deepStrictEqual(await modelos(p, ''), ['llama3.1']);
  s.close();
});

test('openai y anthropic envían la clave en su cabecera', async () => {
  const s = await servidor(async (req, res) => {
    await cuerpo(req);
    if (req.url === '/chat/completions') return json(res, { choices: [{ message: { content: 'oai:' + req.headers.authorization } }] });
    json(res, { content: [{ type: 'text', text: 'ant:' + req.headers['x-api-key'] }] });
  });
  const url = 'http://127.0.0.1:' + s.address().port, m = [{ role: 'user', content: 'x' }];
  assert.strictEqual(await chat({ tipo: 'openai', url, modelo: 'm' }, 'K1', m), 'oai:Bearer K1');
  assert.strictEqual(await chat({ tipo: 'anthropic', url, modelo: 'm' }, 'K2', m), 'ant:K2');
  await assert.rejects(chat({ tipo: 'openai', url, modelo: 'm' }, '', m), /API key/);
  s.close();
});

test('rechaza URLs con credenciales o esquemas raros', () => {
  assert.throws(() => urlBase({ tipo: 'ollama', url: 'http://u:p@x' }));
  assert.throws(() => urlBase({ tipo: 'ollama', url: 'file:///etc/passwd' }));
});

test('comparar versiones', () => {
  assert.strictEqual(comparar('0.2.0', '0.1.9'), 1);
  assert.strictEqual(comparar('0.1.0', '0.1.0'), 0);
  assert.strictEqual(comparar('0.1.0', '1.0.0'), -1);
});

test('hardware: recomienda según VRAM o RAM', () => {
  const { recomendar } = require('../src/hardware');
  const gpu = recomendar({ ramGB: 32, gpus: [{ nombre: 'X', vramGB: 12 }] });
  assert.strictEqual(gpu.modelos.find(m => m.recomendado).modelo, 'qwen2.5:14b');
  const cpu = recomendar({ ramGB: 16, gpus: [] });
  assert.ok(cpu.aviso && cpu.modelos.find(m => m.recomendado).tam <= 5);
  assert.strictEqual(recomendar({ ramGB: 2, gpus: [] }).modelos.length, 0);
});
