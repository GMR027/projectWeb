'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const { extraerJson, ejecutar } = require('../src/programador');

const servidor = manejar => new Promise(ok => { const s = http.createServer(manejar).listen(0, '127.0.0.1', () => ok(s)); });
const leer = req => new Promise(ok => { let d = ''; req.on('data', c => d += c); req.on('end', () => ok(d)); });
const json = (res, o, c = 200) => { res.writeHead(c, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };

test('extraerJson acepta JSON puro, con texto alrededor y en bloque de código', () => {
  assert.deepStrictEqual(extraerJson('{"a":1}'), { a: 1 });
  assert.deepStrictEqual(extraerJson('Claro:\n```json\n{"a":{"b":2}}\n```\nListo'), { a: { b: 2 } });
  assert.strictEqual(extraerJson('sin json'), null);
  assert.strictEqual(extraerJson('{roto'), null);
  assert.strictEqual(extraerJson('[1,2]'), null);
});

test('ejecutar: lee lotes, reintenta si el modelo no devuelve JSON y guarda la propuesta', async () => {
  let llamadas = 0, guardado = null;
  const ia = await servidor(async (req, res) => {
    await leer(req); llamadas++;
    json(res, { message: { content: llamadas === 1 ? 'no sé' : '{"actividades":[],"obstaculos":[]}' } });
  });
  const web = await servidor(async (req, res) => {
    if (req.url.startsWith('/api/programador/contexto')) return json(res, { success: true, csrf: 'tok', proyecto: 'P', actividades: 1, lotes: [{ titulo: 'Lote 1 de 1', prompt: 'analiza', ids: [1] }] });
    guardado = { cab: req.headers['x-csrf-token'], cuerpo: new URLSearchParams(await leer(req)) };
    json(res, { success: true, id: 7, resumen: { movidas: 0 } });
  });
  const avances = [];
  const r = await ejecutar({
    servidor: 'http://127.0.0.1:' + web.address().port, proyectoId: 5, clave: '', sesion: (u, o) => fetch(u, o), cancelado: () => false, alAvanzar: p => avances.push(p.texto),
    conexion: { nombre: 'Local', tipo: 'ollama', url: 'http://127.0.0.1:' + ia.address().port, modelo: 'm' },
  });
  assert.strictEqual(r.id, 7);
  assert.strictEqual(llamadas, 2);
  assert.strictEqual(guardado.cab, 'tok');
  assert.strictEqual(guardado.cuerpo.get('proyecto'), '5');
  assert.deepStrictEqual(JSON.parse(guardado.cuerpo.get('datos')).respuestas, [{ actividades: [], obstaculos: [] }]);
  assert.ok(avances.length >= 3);
  ia.close(); web.close();
});

test('ejecutar: error claro si la sesión no es de admin', async () => {
  const web = await servidor((req, res) => json(res, { success: false, error: 'No autorizado' }, 403));
  await assert.rejects(ejecutar({ servidor: 'http://127.0.0.1:' + web.address().port, proyectoId: 1, conexion: {}, sesion: (u, o) => fetch(u, o), cancelado: () => false, alAvanzar() {} }), /No autorizado/);
  web.close();
});

test('ejecutar: con varios paquetes pide la integración y manda sus relaciones', async () => {
  const prompts = [];
  const ia = await servidor(async (req, res) => {
    const b = JSON.parse(await leer(req)); prompts.push(b.messages[0].content);
    json(res, { message: { content: b.messages[0].content === 'integra' ? '{"relaciones":[{"pred":1,"suc":2,"tipo":"FS","retraso":0,"motivo":"m"}]}' : '{"actividades":[]}' } });
  });
  let guardado = null;
  const web = await servidor(async (req, res) => {
    if (req.url.startsWith('/api/programador/contexto')) return json(res, { success: true, csrf: 't', proyecto: 'P', actividades: 2, lotes: [{ titulo: 'Paquete 1', prompt: 'p1' }, { titulo: 'Paquete 2', prompt: 'p2' }] });
    const cuerpo = new URLSearchParams(await leer(req));
    if (req.url === '/api/programador/integracion') return json(res, { success: true, lote: { titulo: 'Integración', prompt: 'integra' } });
    guardado = JSON.parse(cuerpo.get('datos')); json(res, { success: true, id: 1, resumen: {} });
  });
  await ejecutar({ servidor: 'http://127.0.0.1:' + web.address().port, proyectoId: 1, clave: '', sesion: (u, o) => fetch(u, o), cancelado: () => false, alAvanzar() {}, conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:' + ia.address().port, modelo: 'm' } });
  assert.deepStrictEqual(prompts, ['p1', 'p2', 'integra']);
  assert.strictEqual(guardado.respuestas.length, 3);
  assert.strictEqual(guardado.respuestas[2].relaciones[0].suc, 2);
  ia.close(); web.close();
});

test('ejecutar: pide la revisión y manda sus mejoras junto con las respuestas; si falla, guarda igual', async () => {
  const ia = await servidor(async (req, res) => {
    const b = JSON.parse(await leer(req)); const c = b.messages[0].content;
    json(res, { message: { content: c === 'revisa' ? '{"mejoras":[{"actividades":[1],"categoria":"riesgo","severidad":"alta","hallazgo":"h","mejora":"m"}]}' : '{"actividades":[]}' } });
  });
  let guardado = null, revisiones = 0;
  const web = await servidor(async (req, res) => {
    if (req.url.startsWith('/api/programador/contexto')) return json(res, { success: true, csrf: 't', proyecto: 'P', actividades: 1, lotes: [{ titulo: 'Paquete 1', prompt: 'p1' }] });
    if (req.url === '/api/programador/revision') { await leer(req); revisiones++; return json(res, { success: true, lote: { titulo: 'Revisión', prompt: 'revisa' } }); }
    guardado = new URLSearchParams(await leer(req));
    json(res, { success: true, id: 9, resumen: {} });
  });
  await ejecutar({ servidor: 'http://127.0.0.1:' + web.address().port, proyectoId: 1, clave: '', sesion: (u, o) => fetch(u, o), cancelado: () => false, alAvanzar() {}, conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:' + ia.address().port, modelo: 'm' } });
  assert.strictEqual(revisiones, 1);
  assert.deepStrictEqual(JSON.parse(guardado.get('datos')).respuestas[1].mejoras[0].categoria, 'riesgo');
  ia.close(); web.close();
});
