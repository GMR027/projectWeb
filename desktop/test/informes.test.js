'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const { ejecutar, catalogo } = require('../src/informes');

const servidor = manejar => new Promise(ok => { const s = http.createServer(manejar).listen(0, '127.0.0.1', () => ok(s)); });
const leer = req => new Promise(ok => { let d = ''; req.on('data', c => d += c); req.on('end', () => ok(d)); });
const json = (res, o, c = 200) => { res.writeHead(c, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };
const web = (extra = {}) => servidor(async (req, res) => {
  if (req.url === '/api/ia/herramientas') return json(res, { success: true, csrf: 'tok', herramientas: [{ id: 'reporte_diario' }] });
  const cuerpo = new URLSearchParams(await leer(req));
  if (req.url === '/api/ia/contexto') return json(res, { success: true, lotes: [{ titulo: 'Redactando', prompt: 'redacta' }] });
  extra.guardado = { csrf: req.headers['x-csrf-token'], cuerpo };
  json(res, { success: true, informe_id: 3, titulo: 'T', con_ia: JSON.parse(cuerpo.get('datos')).respuestas.length > 0, url: '/informes-ia/ver?id=3' });
});

test('ejecutar: manda el prompt a la IA y devuelve el informe', async () => {
  const ia = await servidor(async (req, res) => { await leer(req); json(res, { message: { content: '{"comentarios":"hola"}' } }); });
  const extra = {}, w = await web(extra);
  const r = await ejecutar({ servidor: 'http://127.0.0.1:' + w.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 5, herramienta: 'reporte_diario', parametros: { fecha: '2026-01-02' }, clave: '', sinIA: false, alAvanzar() {}, cancelado: () => false,
    conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:' + ia.address().port, modelo: 'm' } });
  assert.strictEqual(r.informe_id, 3);
  assert.strictEqual(r.con_ia, true);
  assert.strictEqual(extra.guardado.csrf, 'tok');
  const d = JSON.parse(extra.guardado.cuerpo.get('datos'));
  assert.deepStrictEqual(d.respuestas, [{ comentarios: 'hola' }]);
  assert.deepStrictEqual(d.params, { fecha: '2026-01-02' });
  assert.match(d.modelo, /m/);
  ia.close(); w.close();
});

test('ejecutar: sin IA no llama al modelo; si el modelo falla el informe se arma igual', async () => {
  const extra = {}, w = await web(extra);
  const base = { servidor: 'http://127.0.0.1:' + w.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 5, herramienta: 'reporte_diario', parametros: {}, clave: '', alAvanzar() {}, cancelado: () => false };
  let r = await ejecutar({ ...base, sinIA: true, conexion: null });
  assert.strictEqual(r.con_ia, false);
  assert.deepStrictEqual(JSON.parse(extra.guardado.cuerpo.get('datos')).respuestas, []);
  const ia = await servidor(async (req, res) => { await leer(req); json(res, { error: 'boom' }, 500); });
  r = await ejecutar({ ...base, sinIA: false, conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:' + ia.address().port, modelo: 'm' } });
  assert.strictEqual(r.fallidos.length, 1);
  assert.strictEqual(r.con_ia, false);
  ia.close(); w.close();
});

test('ejecutar: error de sesión y cancelación; catalogo vacío sin sesión', async () => {
  const w = await servidor((req, res) => json(res, { success: false, error: 'No autorizado' }, 403));
  const base = { servidor: 'http://127.0.0.1:' + w.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 1, herramienta: 'x', parametros: {}, conexion: {}, alAvanzar() {} };
  await assert.rejects(ejecutar({ ...base, cancelado: () => false }), /No autorizado/);
  assert.deepStrictEqual(await catalogo({ servidor: base.servidor, sesion: base.sesion }), []);
  w.close();
  const w2 = await web();
  await assert.rejects(ejecutar({ servidor: 'http://127.0.0.1:' + w2.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 1, herramienta: 'x', parametros: {}, conexion: {}, alAvanzar() {}, cancelado: () => true, sinIA: false }), /ancelado/);
  w2.close();
});
