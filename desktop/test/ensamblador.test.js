'use strict';
const test = require('node:test');
const assert = require('node:assert');
const { crear, multipart } = require('../src/ensamblador');

test('multipart: campos, nombre en UTF-8 y bytes del archivo intactos', () => {
  const datos = Buffer.from([0, 1, 2, 255, 13, 10, 45, 45]);
  const m = multipart({ proyecto: '5', sesion: '2' }, 'Cámaras "A".pdf', datos);
  const b = m.tipo.split('boundary=')[1];
  assert.ok(m.body.includes(Buffer.from('name="proyecto"\r\n\r\n5\r\n')));
  assert.ok(m.body.includes(Buffer.from('filename="Cámaras _A_.pdf"')));
  assert.ok(m.body.includes(datos));
  assert.ok(m.body.toString('latin1').endsWith(`\r\n--${b}--\r\n`));
});

test('subir: un archivo con error no detiene a los demás y se conserva la sesión', async () => {
  const llamadas = [];
  const sesion = async (url, o) => {
    llamadas.push(url);
    const R = (j, ok = true) => ({ ok, status: ok ? 200 : 422, text: async () => JSON.stringify(j) });
    if (url.includes('/estado')) return R({ success: true, csrf: 'tok', submittals: [], historial: [] });
    const cuerpo = o.body.toString('latin1');
    if (cuerpo.includes('malo.exe')) return R({ success: false, error: 'Formato no admitido', sesion: 7 }, false);
    return R({ success: true, sesion: 7, archivo: { id: llamadas.length, nombre: 'ok.pdf' } });
  };
  const ens = crear({ cfg: { leer: () => ({ servidor: 'http://x' }) }, sesion, avisar: () => {} });
  const r = await ens.subir(3, 0, [{ nombre: 'malo.exe', datos: Buffer.from('x') }, { nombre: 'ok.pdf', datos: Buffer.from('y') }]);
  assert.strictEqual(r.sesion, 7);
  assert.strictEqual(r.archivos.length, 1);
  assert.strictEqual(r.errores.length, 1);
  assert.match(r.errores[0].error, /Formato/);
});

test('subir: rechaza de entrada lo que pasa de 50 MB', async () => {
  const sesion = async url => ({ ok: true, status: 200, text: async () => JSON.stringify({ success: true, csrf: 't' }) });
  const ens = crear({ cfg: { leer: () => ({ servidor: 'http://x' }) }, sesion, avisar: () => {} });
  const r = await ens.subir(3, 0, [{ nombre: 'grande.pdf', datos: { byteLength: 51 * 1024 * 1024 } }]);
  assert.strictEqual(r.errores.length, 1);
});

test('ordenar por fechas: el prompt incluye solo filas que cambian y pide JSON de notas', () => {
  const { prompt } = require('../src/ordenarIA');
  const p = prompt('Obra', [{ id: 'a7', titulo: '1.0 Civil', filas: [
    { id: 1, codigo: '1.1', nombre: 'Zanja', nuevo: '1.2', de: 1, a: 2, inicio: '2026-03-05', fin: '2026-03-06' },
    { id: 2, codigo: '1.2', nombre: 'Relleno', nuevo: '1.1', de: 2, a: 1, inicio: '2026-03-01', fin: '2026-03-02' },
    { id: 3, codigo: '1.3', nombre: 'Igual', nuevo: '1.3', de: 3, a: 3, inicio: null, fin: null }] }], []);
  assert.match(p, /Zanja/); assert.doesNotMatch(p, /Igual/); assert.match(p, /"notas"/);
});
