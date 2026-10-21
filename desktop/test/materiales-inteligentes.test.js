'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const { ejecutar, estado, activar } = require('../src/materiales-inteligentes');

const servidor = manejar => new Promise(ok => { const s = http.createServer(manejar).listen(0, '127.0.0.1', () => ok(s)); });
const leer = req => new Promise(ok => { let d = ''; req.on('data', c => d += c); req.on('end', () => ok(d)); });
const json = (res, o, c = 200) => { res.writeHead(c, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };
const base = (web, ia) => ({ servidor: 'http://127.0.0.1:' + web.address().port, proyectoId: 7, clave: '', sesion: (u, o) => fetch(u, o), cancelado: () => false, alAvanzar() {}, conexion: { nombre: 'Local', tipo: 'ollama', url: 'http://127.0.0.1:' + (ia ? ia.address().port : 1), modelo: 'm' } });

test('ejecutar: verifica cada lote, sigue si uno falla y guarda lo demás con el CSRF', async () => {
  let guardado = null;
  const ia = await servidor(async (req, res) => {
    const b = JSON.parse(await leer(req));
    if (b.messages[0].content === 'malo') return json(res, { error: 'boom' }, 500);
    json(res, { message: { content: '{"materiales":[{"id":1,"veredicto":"aprobado"}]}' } });
  });
  const web = await servidor(async (req, res) => {
    if (req.url.startsWith('/api/materiales-inteligentes/contexto')) return json(res, { success: true, csrf: 'tok', proyecto: 'P', lotes: [{ titulo: 'Lote A', prompt: 'bueno' }, { titulo: 'Lote B', prompt: 'malo' }] });
    guardado = { cab: req.headers['x-csrf-token'], cuerpo: new URLSearchParams(await leer(req)) };
    json(res, { success: true, verificados: 1, aprobados: 1, por_revisar: 0, sin_normativa: 0, no_aprobados: [] });
  });
  const r = await ejecutar(base(web, ia));
  assert.strictEqual(r.resumen.verificados, 1);
  assert.strictEqual(r.resumen.fallidos.length, 1);
  assert.match(r.resumen.fallidos[0], /Lote B/);
  assert.strictEqual(guardado.cab, 'tok');
  assert.strictEqual(guardado.cuerpo.get('_csrf'), 'tok');
  const datos = JSON.parse(guardado.cuerpo.get('datos'));
  assert.strictEqual(datos.respuestas.length, 1);
  assert.strictEqual(datos.lotes, undefined);   // esta herramienta no manda registro por lote
  ia.close(); web.close();
});

test('ejecutar: sin materiales por verificar no guarda nada y devuelve el motivo', async () => {
  let posts = 0;
  const web = await servidor(async (req, res) => {
    if (req.method === 'POST') { posts++; return json(res, { success: true }); }
    json(res, { success: true, csrf: 't', proyecto: 'P', lotes: [], motivo: 'sin_documentos', conteos: { pendiente: 0 } });
  });
  const r = await ejecutar(base(web));
  assert.strictEqual(posts, 0);
  assert.strictEqual(r.resumen.motivo, 'sin_documentos');
  assert.strictEqual(r.resumen.verificados, 0);
  web.close();
});

test('estado y activar: leen con la sesión y mandan el CSRF', async () => {
  let cab = null, cuerpo = null;
  const web = await servidor(async (req, res) => {
    if (req.url.startsWith('/api/materiales-inteligentes/estado')) return json(res, { success: true, activo: false, csrf: 'abc' });
    cab = req.headers['x-csrf-token']; cuerpo = new URLSearchParams(await leer(req));
    json(res, { success: true, activo: true });
  });
  const o = base(web);
  assert.strictEqual((await estado(o)).activo, false);
  assert.strictEqual((await activar({ ...o, activo: true })).activo, true);
  assert.strictEqual(cab, 'abc');
  assert.strictEqual(cuerpo.get('activo'), '1');
  assert.strictEqual(cuerpo.get('proyecto'), '7');
  web.close();
});

test('ejecutar: error de sesión y cancelación', async () => {
  const web = await servidor((req, res) => json(res, { success: false, error: 'No autorizado' }, 403));
  await assert.rejects(ejecutar(base(web)), /No autorizado/);
  web.close();
  const web2 = await servidor((req, res) => json(res, { success: true, csrf: 't', proyecto: 'P', lotes: [{ titulo: 'x', prompt: 'p' }] }));
  await assert.rejects(ejecutar({ ...base(web2), cancelado: () => true }), /cancelada/);
  web2.close();
});
