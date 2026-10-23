'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const { chat, modelos, familiaOpencode, probar, estado, detectarCli, urlBase } = require('../src/providers');
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

test('opencode: manda el modelo con --model, sin permisos de escritura, y lista los modelos del equipo', async () => {
  const fs = require('fs'), os = require('os'), path = require('path');
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'fakeopencode-')), anterior = process.env.PATH;
  // el «opencode» falso: contesta a --version y a models, y en «run» vuelca sus argumentos y el opencode.json de su cwd.
  // Si la entrada estándar le llega como tubería sin datos (como hace execFile) se queda mudo y sale con 130, como el CLI real
  fs.writeFileSync(path.join(dir, 'opencode'), '#!/usr/bin/env node\nconst fs=require("fs");\nif (fs.fstatSync(0).isFIFO()) process.exit(130);\nconst a=process.argv.slice(2);\n'
    + 'if (a[0]==="--version") { console.log("opencode v9.9.9"); process.exit(0); }\n'
    + 'if (a[0]==="models") { console.log("opencode/glm-5.3-flash-free\\nopenai/gpt-4o\\nopencode-go/mimo-v2.6-flash\\nopencode/mimo-v2.6-flash-free"); process.exit(0); }\n'
    + 'console.log(JSON.stringify({a, cfg: fs.existsSync("opencode.json") ? JSON.parse(fs.readFileSync("opencode.json","utf8")) : null}));\n', { mode: 0o755 });
  process.env.PATH = dir + path.delimiter + anterior;
  try {
    const m = [{ role: 'system', content: 'SIS' }, { role: 'user', content: 'hola' }];
    const r = JSON.parse(await chat({ tipo: 'opencode', modelo: 'opencode-go/mimo-v2.6-flash' }, '', m, {}));
    assert.deepStrictEqual(r.a.slice(0, 3), ['run', '--model', 'opencode-go/mimo-v2.6-flash']);   // sin esto, el CLI usa su modelo por defecto
    assert.ok(r.a[3].includes('SIS') && r.a[3].endsWith('hola'));
    assert.strictEqual(r.cfg.permission.edit, 'deny');   // no puede escribir archivos ni ejecutar comandos
    assert.strictEqual(r.cfg.permission.shell, 'deny');
    await assert.rejects(chat({ tipo: 'opencode' }, '', m, {}), /modelo/);   // sin modelo elegido no se lanza
    // el listado trae lo de tu suscripción y lo gratuito, con la familia de cada uno para poder etiquetarlo
    const lista = await modelos({ tipo: 'opencode' }, '');
    assert.deepStrictEqual(lista, ['opencode-go/mimo-v2.6-flash', 'opencode/glm-5.3-flash-free', 'opencode/mimo-v2.6-flash-free', 'openai/gpt-4o']);
    assert.deepStrictEqual(lista.map(familiaOpencode), ['go', 'free', 'free', 'otro']);
    // comprobación de la conexión: pide modelo, lo valida en la lista y (no económica) hace una consulta de verdad
    await assert.rejects(probar({ tipo: 'opencode' }, ''), /modelo/);
    await assert.rejects(probar({ tipo: 'opencode', modelo: 'otro/modelo' }, ''), /no está en opencode/);
    assert.strictEqual(await probar({ tipo: 'opencode', modelo: 'opencode-go/mimo-v2.6-flash' }, '', true), 'opencode instalado (opencode v9.9.9) · modelo «opencode-go/mimo-v2.6-flash» disponible.');
    assert.match(await probar({ tipo: 'opencode', modelo: 'opencode-go/mimo-v2.6-flash' }, ''), /«opencode-go\/mimo-v2.6-flash» responde/);
    const e = await estado({ tipo: 'opencode', modelo: 'opencode-go/mimo-v2.6-flash' }, '');
    assert.strictEqual(e.nivel, 'aviso');
    assert.match(e.texto, /Comprobar/);
    assert.strictEqual((await estado({ tipo: 'opencode' }, '')).nivel, 'error');   // la conexión sin modelo no se marca como lista
  } finally { process.env.PATH = anterior; fs.rmSync(dir, { recursive: true, force: true }); }
});

test('opencode se encuentra aunque la app arranque SIN PATH (menú de escritorio)', async () => {
  const fs = require('fs'), os = require('os'), path = require('path');
  const hogar = fs.mkdtempSync(path.join(os.tmpdir(), 'pwd-home-')), dir = path.join(hogar, '.opencode', 'bin');
  fs.mkdirSync(dir, { recursive: true });
  // «opencode» instalado en ~/.opencode/bin (donde lo pone su instalador), escrito en sh para no depender de node
  fs.writeFileSync(path.join(dir, 'opencode'), '#!/bin/sh\n'
    + 'if [ "$1" = "--version" ]; then echo "opencode v9.9.9"; exit 0; fi\n'
    + 'if [ "$1" = "models" ]; then echo "opencode-go/mimo-v2.6-flash"; exit 0; fi\n'
    + 'echo listo\n', { mode: 0o755 });
  const PATH = process.env.PATH, HOME = process.env.HOME;
  process.env.HOME = hogar; process.env.PATH = '';
  try {
    assert.strictEqual((await detectarCli()).opencode, true);
    assert.strictEqual(await chat({ tipo: 'opencode', modelo: 'opencode-go/mimo-v2.6-flash' }, '', [{ role: 'user', content: 'hola' }], {}), 'listo');
    assert.match(await probar({ tipo: 'opencode', modelo: 'opencode-go/mimo-v2.6-flash' }, ''), /responde \(listo\)/);
    // el error de un CLI que sí falta explica dónde se busca
    await assert.rejects(chat({ tipo: 'claude-code' }, '', [{ role: 'user', content: 'x' }], {}), /no está instalado.*\.opencode\/bin/);
  } finally { process.env.PATH = PATH; process.env.HOME = HOME; fs.rmSync(hogar, { recursive: true, force: true }); }
});

