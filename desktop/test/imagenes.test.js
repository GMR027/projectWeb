'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const prov = require('../src/providers');
const { ejecutar } = require('../src/informes');

const servidor = manejar => new Promise(ok => { const s = http.createServer(manejar).listen(0, '127.0.0.1', () => ok(s)); });
const leer = req => new Promise(ok => { let d = ''; req.on('data', c => d += c); req.on('end', () => ok(d)); });
const json = (res, o, c = 200) => { res.writeHead(c, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };
const IMG = [{ mime: 'image/jpeg', base64: 'QUJD' }];

test('las imágenes viajan en el formato de cada proveedor', async () => {
  let visto = null;
  const s = await servidor(async (req, res) => { visto = { url: req.url, cuerpo: JSON.parse(await leer(req)) }; json(res, { message: { content: 'ok' }, choices: [{ message: { content: 'ok' } }], content: [{ type: 'text', text: 'ok' }] }); });
  const url = 'http://127.0.0.1:' + s.address().port;
  await prov.chat({ tipo: 'ollama', url, modelo: 'llava' }, '', [{ role: 'user', content: 'describe' }], { imagenes: IMG });
  assert.deepStrictEqual(visto.cuerpo.messages[0].images, ['QUJD']);
  await prov.chat({ tipo: 'openai', url: url + '/v1', modelo: 'gpt-4o' }, 'k', [{ role: 'user', content: 'describe' }], { imagenes: IMG });
  assert.strictEqual(visto.cuerpo.messages[0].content[0].type, 'text');
  assert.strictEqual(visto.cuerpo.messages[0].content[1].image_url.url, 'data:image/jpeg;base64,QUJD');
  await prov.chat({ tipo: 'anthropic', url, modelo: 'claude' }, 'k', [{ role: 'user', content: 'describe' }], { imagenes: IMG });
  assert.strictEqual(visto.cuerpo.messages[0].content[0].source.data, 'QUJD');
  assert.strictEqual(visto.cuerpo.messages[0].content[1].text, 'describe');
  // sin imágenes no cambia nada
  await prov.chat({ tipo: 'ollama', url, modelo: 'm' }, '', [{ role: 'user', content: 'hola' }], {});
  assert.strictEqual(visto.cuerpo.messages[0].images, undefined);
  s.close();
});

test('un agente CLI rechaza las imágenes con un mensaje claro', async () => {
  await assert.rejects(prov.chat({ tipo: 'claude-code', modelo: '' }, '', [{ role: 'user', content: 'x' }], { imagenes: IMG }), /no admite imágenes/);
});

test('informes: descarga las fotos del lote, las manda al modelo y etiqueta la respuesta con la clave', async () => {
  let ollama = null;
  const ia = await servidor(async (req, res) => { ollama = JSON.parse(await leer(req)); json(res, { message: { content: '{"estado":"verde","porcentaje":90,"confianza":"alta","descripcion":"ok"}' } }); });
  let guardado = null;
  const web = await servidor(async (req, res) => {
    if (req.url === '/api/ia/herramientas') return json(res, { success: true, csrf: 't', herramientas: [] });
    if (req.url.startsWith('/api/ia/evidencia')) { res.writeHead(200, { 'Content-Type': 'image/jpeg' }); return res.end(Buffer.from('ABC')); }
    const c = new URLSearchParams(await leer(req));
    if (req.url === '/api/ia/contexto') return json(res, { success: true, lotes: [{ titulo: 'Foto 1', clave: 'A12', prompt: 'mira', imagenes: [{ url: '/api/ia/evidencia?proyecto=1&id=5' }] }] });
    guardado = JSON.parse(c.get('datos')); json(res, { success: true, informe_id: 1, titulo: 'T', con_ia: true, url: '/x' });
  });
  const r = await ejecutar({ servidor: 'http://127.0.0.1:' + web.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 1, herramienta: 'fotos_avance', parametros: {}, clave: '', sinIA: false, alAvanzar() {}, cancelado: () => false,
    conexion: { nombre: 'V', tipo: 'ollama', url: 'http://127.0.0.1:' + ia.address().port, modelo: 'llava' } });
  assert.strictEqual(r.con_ia, true);
  assert.deepStrictEqual(ollama.messages[0].images, ['QUJD']);   // «ABC» en base64
  assert.strictEqual(guardado.respuestas[0]._clave, 'A12');
  assert.strictEqual(guardado.respuestas[0].estado, 'verde');
  ia.close(); web.close();
});

test('informes: si las fotos no se pueden descargar el lote falla sin tirar el informe', async () => {
  const ia = await servidor(async (req, res) => { await leer(req); json(res, { message: { content: '{}' } }); });
  const web = await servidor(async (req, res) => {
    if (req.url === '/api/ia/herramientas') return json(res, { success: true, csrf: 't', herramientas: [] });
    if (req.url.startsWith('/api/ia/evidencia')) { res.writeHead(404); return res.end('no'); }
    const c = new URLSearchParams(await leer(req));
    if (req.url === '/api/ia/contexto') return json(res, { success: true, lotes: [{ titulo: 'Foto', clave: 'A1', prompt: 'm', imagenes: [{ url: '/api/ia/evidencia?id=1' }] }] });
    json(res, { success: true, informe_id: 2, titulo: 'T', con_ia: JSON.parse(c.get('datos')).respuestas.length > 0, url: '/x' });
  });
  const r = await ejecutar({ servidor: 'http://127.0.0.1:' + web.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 1, herramienta: 'fotos_avance', parametros: {}, clave: '', sinIA: false, alAvanzar() {}, cancelado: () => false, conexion: { nombre: 'V', tipo: 'ollama', url: 'http://127.0.0.1:' + ia.address().port, modelo: 'llava' } });
  assert.strictEqual(r.fallidos.length, 1); assert.match(r.fallidos[0], /fotos/); assert.strictEqual(r.con_ia, false);
  ia.close(); web.close();
});
