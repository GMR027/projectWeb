'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const { ejecutar } = require('../src/inconsistencias');

const servidor = manejar => new Promise(ok => { const s = http.createServer(manejar).listen(0, '127.0.0.1', () => ok(s)); });
const leer = req => new Promise(ok => { let d = ''; req.on('data', c => d += c); req.on('end', () => ok(d)); });
const json = (res, o, c = 200) => { res.writeHead(c, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };

test('ejecutar: manda cada paquete a la IA, sigue si uno falla y guarda lo demás', async () => {
  let llamadas = 0, guardado = null;
  const ia = await servidor(async (req, res) => {
    const b = JSON.parse(await leer(req)); llamadas++;
    if (b.messages[0].content === 'malo') return json(res, { error: 'boom' }, 500);
    json(res, { message: { content: '{"hallazgos":[{"titulo":"t"}]}' } });
  });
  const web = await servidor(async (req, res) => {
    if (req.url.startsWith('/api/inconsistencias/contexto')) return json(res, { success: true, csrf: 'tok', proyecto: 'P', lotes: [{ titulo: 'Paquete 1', prompt: 'bueno' }, { titulo: 'Paquete 2', prompt: 'malo' }] });
    guardado = { cab: req.headers['x-csrf-token'], cuerpo: new URLSearchParams(await leer(req)) };
    json(res, { success: true, del_sistema: 3, de_la_ia: 1, nuevos: 4, ya_resueltos: 0 });
  });
  const r = await ejecutar({ servidor: 'http://127.0.0.1:' + web.address().port, proyectoId: 5, clave: '', sesion: (u, o) => fetch(u, o), cancelado: () => false, alAvanzar() {},
    conexion: { nombre: 'Local', tipo: 'ollama', url: 'http://127.0.0.1:' + ia.address().port, modelo: 'm' } });
  assert.strictEqual(r.resumen.nuevos, 4);
  assert.strictEqual(r.resumen.fallidos.length, 1);
  assert.match(r.resumen.fallidos[0], /Paquete 2/);
  assert.strictEqual(guardado.cab, 'tok');
  assert.deepStrictEqual(JSON.parse(guardado.cuerpo.get('datos')).respuestas, [{ hallazgos: [{ titulo: 't' }] }]);
  ia.close(); web.close();
});

test('ejecutar: respeta la cancelación y el error de sesión', async () => {
  const web = await servidor((req, res) => json(res, { success: false, error: 'No autorizado' }, 403));
  await assert.rejects(ejecutar({ servidor: 'http://127.0.0.1:' + web.address().port, proyectoId: 1, conexion: {}, sesion: (u, o) => fetch(u, o), cancelado: () => false, alAvanzar() {} }), /No autorizado/);
  web.close();
  const web2 = await servidor((req, res) => json(res, { success: true, csrf: 't', proyecto: 'P', lotes: [{ titulo: 'x', prompt: 'p' }] }));
  await assert.rejects(ejecutar({ servidor: 'http://127.0.0.1:' + web2.address().port, proyectoId: 1, conexion: {}, sesion: (u, o) => fetch(u, o), cancelado: () => true, alAvanzar() {} }), /cancelado/);
  web2.close();
});
