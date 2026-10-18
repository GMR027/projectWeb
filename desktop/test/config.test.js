'use strict';
const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { crear } = require('../src/config');

const seguro = { isEncryptionAvailable: () => true, encryptString: s => Buffer.from(s), decryptString: b => b.toString() };
const nuevo = () => crear(fs.mkdtempSync(path.join(os.tmpdir(), 'pwd-cfg-')), seguro);

test('conexionPara: sin elección usa la principal y luego la primera', () => {
  const c = nuevo();
  assert.strictEqual(c.conexionPara('rapida'), null);
  c.guardarProveedor({ id: 'a', nombre: 'A', tipo: 'ollama' });
  c.guardarProveedor({ id: 'b', nombre: 'B', tipo: 'openai' });
  assert.strictEqual(c.conexionPara('rapida').id, 'b');   // la recién creada es la principal
});

test('conexionPara: respeta la elección por tarea y descarta ids que ya no existen', () => {
  const c = nuevo();
  c.guardarProveedor({ id: 'a', nombre: 'A', tipo: 'ollama' });
  c.guardarProveedor({ id: 'b', nombre: 'B', tipo: 'openai' });
  c.guardarPorTarea({ rapida: 'a', avanzada: 'zzz' });
  assert.strictEqual(c.conexionPara('rapida').id, 'a');
  assert.strictEqual(c.conexionPara('avanzada').id, 'b');
  assert.deepStrictEqual(c.publica().porTarea, { rapida: 'a', avanzada: '' });
});
