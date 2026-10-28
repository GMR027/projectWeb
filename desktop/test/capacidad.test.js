'use strict';
const test = require('node:test');
const assert = require('node:assert');
const { parametrosB, perfil, evaluar } = require('../src/capacidad');
const { HERRAMIENTAS, GRUPOS } = require('../src/herramientas');

test('parametrosB lee el tamaño del nombre del modelo', () => {
  assert.strictEqual(parametrosB('qwen2.5:7b'), 7);
  assert.strictEqual(parametrosB('llama3.1:70b-instruct-q4'), 70);
  assert.strictEqual(parametrosB('mistral-nemo:12B'), 12);
  assert.strictEqual(parametrosB('gpt-4o'), null);
  assert.strictEqual(parametrosB(''), null);
});

test('perfil: niveles por tamaño, nube y visión', () => {
  assert.deepStrictEqual(perfil({ tipo: 'ollama', modelo: 'qwen2.5:7b' }), { nivel: 'basico', vision: false, local: true, nube: false, parametros: 7 });
  assert.strictEqual(perfil({ tipo: 'ollama', modelo: 'qwen2.5:14b' }).nivel, 'medio');
  assert.strictEqual(perfil({ tipo: 'ollama', modelo: 'llama3.3:70b' }).nivel, 'grande');
  assert.strictEqual(perfil({ tipo: 'ollama', modelo: 'llava:7b' }).vision, true);
  assert.strictEqual(perfil({ tipo: 'ollama', modelo: 'qwen2.5vl:7b' }).vision, true);
  assert.strictEqual(perfil({ tipo: 'ollama', modelo: '' }).nivel, 'basico');
  const claude = perfil({ tipo: 'anthropic', modelo: 'claude-sonnet' });
  assert.deepStrictEqual([claude.nivel, claude.vision, claude.nube], ['grande', true, true]);
  assert.strictEqual(perfil({ tipo: 'openai', modelo: 'gpt-4o', url: 'https://api.openai.com/v1' }).nube, true);
  assert.strictEqual(perfil({ tipo: 'openai', modelo: 'mistral:7b', url: 'http://localhost:1234/v1' }).nube, false);
  const cli = perfil({ tipo: 'claude-code' });
  assert.deepStrictEqual([cli.nivel, cli.vision], ['grande', true]);   // Claude Code lee imágenes con Read
  assert.strictEqual(evaluar({ vision: true }, cli).estado, 'ok');
  assert.strictEqual(perfil({ tipo: 'opencode', modelo: '' }).vision, false);
});

test('evaluar: ok, limitado, bloqueado y sin conexión', () => {
  const chico = perfil({ tipo: 'ollama', modelo: 'qwen2.5:7b' });
  const grande = perfil({ tipo: 'anthropic', modelo: 'claude-sonnet' });
  assert.strictEqual(evaluar({ minimo: 'basico', recomendado: 'basico' }, chico).estado, 'ok');
  assert.strictEqual(evaluar({ minimo: 'basico', recomendado: 'medio' }, chico).estado, 'limitado');
  assert.strictEqual(evaluar({ minimo: 'medio', recomendado: 'grande' }, chico).estado, 'bloqueado');
  assert.strictEqual(evaluar({ minimo: 'medio', recomendado: 'grande' }, grande).estado, 'ok');
  assert.strictEqual(evaluar({ vision: true }, chico).estado, 'bloqueado');
  assert.strictEqual(evaluar({ vision: true }, grande).estado, 'ok');
  assert.match(evaluar({ vision: true }, chico).texto, /visión/);
  assert.strictEqual(evaluar({ minimo: 'grande', vision: true }, null).estado, 'limitado');   // sin conexión nunca se bloquea
});

test('el catálogo: ids únicos, grupos válidos y destino definido', () => {
  const ids = HERRAMIENTAS.map(h => h.id);
  assert.strictEqual(new Set(ids).size, ids.length);
  for (const h of HERRAMIENTAS) {
    assert.ok(GRUPOS.some(g => g.id === h.grupo), h.id + ' tiene un grupo válido');
    assert.ok(h.sec || h.web, h.id + ' lleva a algún lado');
  }
});
