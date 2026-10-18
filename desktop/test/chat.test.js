'use strict';
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const { responder, promptPlan, promptRespuesta, pideCambio, afirmaAccion } = require('../src/chat');

const servidor = manejar => new Promise(ok => { const s = http.createServer(manejar).listen(0, '127.0.0.1', () => ok(s)); });
const leer = req => new Promise(ok => { let d = ''; req.on('data', c => d += c); req.on('end', () => ok(d)); });
const json = (res, o, c = 200) => { res.writeHead(c, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(o)); };
const CAT = { actividades: { descripcion: 'Lista', parametros: ['estado'] }, resumen: { descripcion: 'Resumen', parametros: [] } };
const web = (visto = {}) => servidor(async (req, res) => {
  if (req.url === '/api/ia/consultas') return json(res, { success: true, csrf: 'tok', consultas: CAT });
  const c = new URLSearchParams(await leer(req)); visto.cuerpo = c; visto.csrf = req.headers['x-csrf-token'];
  json(res, { success: true, resultados: [{ nombre: 'actividades' }], texto: '### Actividades\n3.2 | Retiro' });
});
const ia = respuestas => servidor(async (req, res) => { await leer(req); json(res, { message: { content: respuestas.shift() } }); });

test('el prompt del plan lista solo las consultas del catálogo y avisa de la lectura', () => {
  const p = promptPlan(CAT, '¿atrasadas?');
  assert.match(p, /- actividades: Lista/); assert.match(p, /SOLO LECTURA/); assert.match(p, /modificar=true/);
  assert.match(promptRespuesta('¿y ahora?', [{ rol: 'usuario', texto: 'hola' }], 'DATOS'), /Usuario: hola/);
});

test('con IA: elige consultas, las ejecuta y redacta; ignora consultas inventadas', async () => {
  const visto = {}, w = await web(visto);
  const m = await ia(['{"consultas":[{"nombre":"actividades","params":{"estado":"atrasadas"}},{"nombre":"borrar_todo","params":{}}],"modificar":false}', '{"respuesta":"Hay una atrasada: 3.2","sugerencias":["¿Y las críticas?"]}']);
  const r = await responder({ servidor: 'http://127.0.0.1:' + w.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 10, pregunta: '¿qué está atrasado?', historial: [], clave: '', alAvanzar() {}, conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:' + m.address().port, modelo: 'm' } });
  assert.strictEqual(r.respuesta, 'Hay una atrasada: 3.2'); assert.deepStrictEqual(r.sugerencias, ['¿Y las críticas?']); assert.strictEqual(r.sinIA, false);
  assert.deepStrictEqual(JSON.parse(visto.cuerpo.get('consultas')), [{ nombre: 'actividades', params: { estado: 'atrasadas' } }]);
  assert.strictEqual(visto.csrf, 'tok'); assert.strictEqual(visto.cuerpo.get('pregunta'), '¿qué está atrasado?'); assert.strictEqual(visto.cuerpo.get('completar'), '1');
  m.close(); w.close();
});

test('sin IA o con un modelo que falla: se muestran los datos; la pregunta va al servidor para las palabras clave', async () => {
  const visto = {}, w = await web(visto);
  const base = { servidor: 'http://127.0.0.1:' + w.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 10, pregunta: 'resumen', historial: [], clave: '', alAvanzar() {} };
  let r = await responder({ ...base, conexion: null });
  assert.strictEqual(r.sinIA, true); assert.match(r.respuesta, /Actividades/); assert.strictEqual(visto.cuerpo.get('pregunta'), 'resumen');
  const roto = await servidor(async (req, res) => { await leer(req); json(res, { error: 'x' }, 500); });
  r = await responder({ ...base, conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:' + roto.address().port, modelo: 'm' } });
  assert.strictEqual(r.sinIA, true); assert.match(r.respuesta, /Actividades/);
  roto.close(); w.close();
});

test('pedir un cambio no ejecuta nada; sin proyecto o pregunta vacía da error claro', async () => {
  const w = await web(), m = await ia(['{"consultas":[],"modificar":true}']);
  const base = { servidor: 'http://127.0.0.1:' + w.address().port, sesion: (u, o) => fetch(u, o), historial: [], clave: '', alAvanzar() {} };
  const r = await responder({ ...base, proyectoId: 10, pregunta: 'borra la actividad 3.2', conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:' + m.address().port, modelo: 'm' } });
  assert.match(r.respuesta, /solo consulta/); assert.deepStrictEqual(r.consultas, []);
  await assert.rejects(responder({ ...base, proyectoId: null, pregunta: 'hola', conexion: null }), /Abre un proyecto/);
  await assert.rejects(responder({ ...base, proyectoId: 10, pregunta: '  ', conexion: null }), /Escribe una pregunta/);
  m.close(); w.close();
});

test('pideCambio detecta órdenes pero no preguntas sobre cambios; afirmaAccion detecta engaños del modelo', () => {
  for (const q of ['Borra la actividad 3.2', 'por favor elimina el material', 'puedes cambiar la fecha de la 4.1', 'Quiero que apruebes el submittal', 'pon la 3.2 al 80%']) assert.strictEqual(pideCambio(q), true, q);
  for (const q of ['¿Qué actividades se crearon esta semana?', 'qué cambios hizo la IA', '¿Cuál actividad está al 80%?', 'resumen del proyecto', 'materiales que se eliminaron']) assert.strictEqual(pideCambio(q), false, q);
  assert.strictEqual(afirmaAccion('Se ha eliminado la actividad 3.2'), true);
  assert.strictEqual(afirmaAccion('Ya cambié la fecha'), false);   // cubre solo las formas pasivas/perfectas más comunes
  assert.strictEqual(afirmaAccion('La actividad 3.2 está al 0%'), false);
});

test('una orden de cambio nunca llega al modelo ni al servidor', async () => {
  let llamadas = 0;
  const w = await servidor(async (req, res) => { llamadas++; await leer(req); json(res, { success: true }); });
  const r = await responder({ servidor: 'http://127.0.0.1:' + w.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 10, pregunta: 'Borra la actividad 3.2', historial: [], clave: '', alAvanzar() {}, conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:1', modelo: 'm' } });
  assert.match(r.respuesta, /solo consulta/); assert.strictEqual(llamadas, 0);
  w.close();
});

test('si el modelo afirma haber hecho un cambio se antepone la advertencia', async () => {
  const w = await web(), m = await ia(['{"consultas":[{"nombre":"actividades","params":{}}]}', '{"respuesta":"Se ha eliminado la actividad 3.2"}']);
  const r = await responder({ servidor: 'http://127.0.0.1:' + w.address().port, sesion: (u, o) => fetch(u, o), proyectoId: 10, pregunta: 'qué pasó con la 3.2', historial: [], clave: '', alAvanzar() {}, conexion: { nombre: 'L', tipo: 'ollama', url: 'http://127.0.0.1:' + m.address().port, modelo: 'm' } });
  assert.match(r.respuesta, /^⚠ Este chat solo consulta/); assert.match(r.respuesta, /Se ha eliminado/);
  m.close(); w.close();
});
