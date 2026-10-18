'use strict';
// Detección del equipo y sugerencia de modelos locales (Ollama) que le caben
const os = require('os');
const fs = require('fs');
const { execFile } = require('child_process');

const GB = 1024 ** 3;
const ejecutar = (bin, args) => new Promise(res => execFile(bin, args, { timeout: 6000 }, (e, out) => res(e ? '' : out)));

async function gpus() {
  const lista = [];
  const nv = await ejecutar('nvidia-smi', ['--query-gpu=name,memory.total', '--format=csv,noheader,nounits']);
  for (const l of nv.split('\n').filter(Boolean)) {
    const [nombre, mib] = l.split(',').map(x => x.trim());
    lista.push({ nombre, vramGB: Math.round((+mib / 1024) * 10) / 10, marca: 'nvidia' });
  }
  if (!lista.length) {
    // AMD (amdgpu) expone su VRAM en sysfs
    try {
      for (const c of fs.readdirSync('/sys/class/drm').filter(x => /^card\d+$/.test(x))) {
        const v = parseInt(fs.readFileSync(`/sys/class/drm/${c}/device/mem_info_vram_total`, 'utf8'), 10);
        if (v > 512 * 1024 * 1024) lista.push({ nombre: 'GPU AMD (' + c + ')', vramGB: Math.round((v / GB) * 10) / 10, marca: 'amd' });
      }
    } catch { /* sin amdgpu */ }
  }
  if (!lista.length) {
    const pci = await ejecutar('lspci', []);
    for (const l of pci.split('\n').filter(x => /VGA|3D controller/i.test(x))) lista.push({ nombre: l.replace(/^.*?: /, '').trim(), vramGB: 0, marca: 'otra' });
  }
  return lista;
}

async function detectar() {
  const cpus = os.cpus();
  return { ramGB: Math.round((os.totalmem() / GB) * 10) / 10, cpu: cpus[0]?.model?.trim() || 'CPU', nucleos: cpus.length, gpus: await gpus() };
}

// tam = GB aproximados del modelo cuantizado (q4) que Ollama descarga
const CATALOGO = [
  { modelo: 'llama3.2:1b', tam: 1.3, nota: 'Mínimo: respuestas básicas, equipos muy limitados' },
  { modelo: 'llama3.2:3b', tam: 2.0, nota: 'Ligero: resúmenes y consultas sencillas' },
  { modelo: 'qwen2.5:7b', tam: 4.7, nota: 'Equilibrado: buen español, extracción de datos de documentos' },
  { modelo: 'qwen2.5:14b', tam: 9.0, nota: 'Alta calidad para análisis y comparación de documentos' },
  { modelo: 'qwen2.5:32b', tam: 20, nota: 'Muy alta calidad; requiere GPU grande o mucha RAM' },
  { modelo: 'llama3.3:70b', tam: 43, nota: 'Nivel estación de trabajo' },
];
const EMBEDDING = { modelo: 'nomic-embed-text', tam: 0.3, nota: 'Búsqueda semántica y comparación de documentos (embeddings)' };

/** Memoria útil para el modelo: VRAM si hay GPU con VRAM conocida; si no, ~60 % de la RAM (CPU, más lento) */
function recomendar(hw) {
  const vram = Math.max(0, ...hw.gpus.map(g => g.vramGB || 0));
  const conGpu = vram >= 4;
  const presupuesto = conGpu ? vram : hw.ramGB * 0.6;
  const caben = CATALOGO.filter(m => m.tam * 1.25 <= presupuesto);
  const mejor = caben.length ? caben[caben.length - 1] : null;
  // En CPU, más de ~9 GB resulta demasiado lento para uso interactivo
  const practico = conGpu ? mejor : (caben.filter(m => m.tam <= 5).pop() || null);
  const elegido = practico || mejor;
  const lista = caben.map(m => ({ ...m, recomendado: m === elegido }));
  return {
    modo: conGpu ? 'GPU (' + vram + ' GB de VRAM)' : 'CPU (' + hw.ramGB + ' GB de RAM)',
    presupuestoGB: Math.round(presupuesto * 10) / 10,
    aviso: !caben.length ? 'Este equipo es demasiado limitado para modelos locales útiles; considera una suscripción o una API.'
      : !conGpu ? 'Sin GPU dedicada detectada: los modelos corren en CPU y serán más lentos; se recomienda uno pequeño.' : '',
    modelos: lista,
    embedding: EMBEDDING,
  };
}
module.exports = { detectar, recomendar, CATALOGO };
