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

test('claude-code: manda historial, sistema e imágenes a un CLI falso y devuelve el resultado', async () => {
  const fs = require('fs'), os = require('os'), path = require('path');
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'fakeclaude-')), anterior = process.env.PATH;
  // el «claude» falso vuelca sus argumentos y los archivos del cwd, y responde en el formato stream-json
  fs.writeFileSync(path.join(dir, 'claude'), '#!/usr/bin/env node\nconst fs=require("fs");const a=process.argv.slice(2);\nconsole.log(JSON.stringify({type:"result",is_error:false,result:JSON.stringify({a,archivos:fs.readdirSync(".")})}));\n', { mode: 0o755 });
  process.env.PATH = dir + path.delimiter + anterior;
  try {
    const m = [{ role: 'system', content: 'SIS' }, { role: 'user', content: 'soy Edgar' }, { role: 'assistant', content: 'hola Edgar' }, { role: 'user', content: '¿quién soy?' }];
    const r = JSON.parse(await chat({ tipo: 'claude-code' }, '', m, {}));
    assert.ok(r.a[1].includes('Usuario: soy Edgar') && r.a[1].includes('Asistente: hola Edgar') && r.a[1].endsWith('¿quién soy?'));
    assert.deepStrictEqual(r.a.slice(r.a.indexOf('--append-system-prompt'), r.a.indexOf('--append-system-prompt') + 2), ['--append-system-prompt', 'SIS']);
    assert.deepStrictEqual(r.a.slice(r.a.indexOf('--tools'), r.a.indexOf('--tools') + 2), ['--tools', '']);   // sin imágenes no hay herramientas
    const ci = JSON.parse(await chat({ tipo: 'claude-code' }, '', [{ role: 'user', content: 'mira' }], { imagenes: [{ mime: 'image/png', base64: 'AA==' }] }));
    assert.deepStrictEqual(ci.archivos, ['imagen-1.png']);
    assert.ok(ci.a.includes('Read'));
  } finally { process.env.PATH = anterior; fs.rmSync(dir, { recursive: true, force: true }); }
});
