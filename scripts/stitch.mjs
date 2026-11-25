#!/usr/bin/env node
/**
 * Utilidad de apoyo para Google Stitch.
 *
 * Lee STITCH_API_KEY del .env (o del entorno) y habla con el servidor MCP
 * de Stitch por HTTP. No imprime la clave.
 *
 * Uso:
 *   node scripts/stitch.mjs list
 *   node scripts/stitch.mjs create "ProjectWeb · Inicio / Dashboard"
 *   node scripts/stitch.mjs design-systems <proyecto> [json]
 *   node scripts/stitch.mjs copy-design <proyectoDestino> <proyectoOrigen>
 *   node scripts/stitch.mjs generate <proyecto> <desktop|mobile> <assetOSistema|-> "<prompt>"
 *   node scripts/stitch.mjs project <proyecto>
 *   node scripts/stitch.mjs screens <proyecto>
 *   node scripts/stitch.mjs screen <proyecto> <screen>
 *   node scripts/stitch.mjs export <proyecto> <carpeta>
 *   node scripts/stitch.mjs download <projects/.../files/...> <archivo>
 */
import fs from 'node:fs';
import path from 'node:path';

const RAIZ = path.resolve(import.meta.dirname, '..');

function leerEnv() {
  const env = { ...process.env };
  const archivo = path.join(RAIZ, '.env');
  if (!fs.existsSync(archivo)) return env;
  for (const linea of fs.readFileSync(archivo, 'utf8').split(/\r?\n/)) {
    const m = linea.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (!m) continue;
    if (!(m[1] in env)) env[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
  return env;
}

const env = leerEnv();
const KEY = env.STITCH_API_KEY;
if (!KEY) {
  console.error('Falta STITCH_API_KEY en .env o en el entorno.');
  process.exit(1);
}

const ENDPOINT = 'https://stitch.googleapis.com/mcp';
let siguienteId = 1;

async function mcp(nombre, argumentos = {}) {
  const cuerpo = {
    jsonrpc: '2.0',
    id: siguienteId++,
    method: 'tools/call',
    params: { name: nombre, arguments: argumentos },
  };
  const respuesta = await fetch(ENDPOINT, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json, text/event-stream',
      'X-Goog-Api-Key': KEY,
    },
    body: JSON.stringify(cuerpo),
    signal: AbortSignal.timeout(1000 * 60 * 10),
  });
  if (!respuesta.ok) throw new Error(`Stitch HTTP ${respuesta.status}: ${await respuesta.text()}`);
  const json = await respuesta.json();
  if (json.error) throw new Error(`Stitch: ${json.error.message || JSON.stringify(json.error)}`);
  const resultado = json.result || {};
  if (resultado.isError) {
    const texto = (resultado.content || []).map((c) => c.text || '').join('\n');
    throw new Error(`Stitch: ${texto || 'error desconocido'}`);
  }
  if (resultado.structuredContent && Object.keys(resultado.structuredContent).length) {
    return resultado.structuredContent;
  }
  const texto = (resultado.content || []).map((c) => c.text || '').join('\n').trim();
  if (!texto) return resultado;
  try {
    return JSON.parse(texto);
  } catch {
    return { texto };
  }
}

function idProyecto(valor) {
  return String(valor || '').replace(/^projects\//, '');
}

function idAsset(valor) {
  return String(valor || '').replace(/^assets\//, '');
}

async function listar() {
  const r = await mcp('list_projects', { pageSize: 100 });
  for (const p of r.projects || []) {
    console.log(`${p.name}\t${p.title || ''}\t${p.updateTime || ''}`);
  }
}

async function crear(titulo) {
  if (!titulo) throw new Error('Falta el título del proyecto.');
  const r = await mcp('create_project', { title: titulo });
  console.log(JSON.stringify(r, null, 2));
}

async function sistemasDiseno(proyecto, crudo = false) {
  if (!proyecto) throw new Error('Falta el proyecto.');
  const r = await mcp('list_design_systems', { projectId: idProyecto(proyecto) });
  if (crudo) { console.log(JSON.stringify(r, null, 2)); return; }
  for (const s of r.designSystems || r.design_systems || []) {
    const base = s.designSystem || s;
    console.log([
      s.name || base.name || '',
      base.displayName || '',
      base.theme?.colorMode || '',
      base.theme?.headlineFontFamily || base.theme?.headlineFont || '',
    ].join('\t'));
  }
}

async function copiarDiseno(destino, origen) {
  const lista = await mcp('list_design_systems', { projectId: idProyecto(origen) });
  const sistemas = lista.designSystems || lista.design_systems || [];
  if (!sistemas.length) throw new Error('El proyecto de origen no tiene sistemas de diseño.');
  const base = sistemas[0].designSystem || sistemas[0];
  const t = base.theme || base;
  // Solo los campos que acepta create_design_system; el manifest completo se rechaza.
  const tema = {
    bodyFont: t.bodyFont || 'MANROPE',
    colorMode: t.colorMode || 'DARK',
    customColor: t.customColor || t.overridePrimaryColor || '#ff5a1f',
    headlineFont: t.headlineFont || 'SPACE_GROTESK',
    labelFont: t.labelFont || 'JETBRAINS_MONO',
    roundness: t.roundness || 'ROUND_FOUR',
    colorVariant: t.colorVariant || 'FIDELITY',
    overrideNeutralColor: t.overrideNeutralColor || '#0b132b',
    overridePrimaryColor: t.overridePrimaryColor || '#ff5a1f',
    overrideSecondaryColor: t.overrideSecondaryColor || '#00b4d8',
    overrideTertiaryColor: t.overrideTertiaryColor || '#3a506b',
  };
  const r = await mcp('create_design_system', {
    projectId: idProyecto(destino),
    designSystem: {
      displayName: base.displayName || 'Vanguard CivilTech',
      theme: tema,
    },
  });
  console.log(JSON.stringify(r, null, 2));
}

async function generar(proyecto, device, asset, prompt) {
  if (!proyecto || !prompt) throw new Error('Uso: generate <proyecto> <desktop|mobile> <asset|-> "<prompt>"');
  const argumentos = {
    projectId: idProyecto(proyecto),
    prompt,
    deviceType: String(device || 'DESKTOP').toUpperCase() === 'MOBILE' ? 'MOBILE' : 'DESKTOP',
  };
  if (asset && asset !== '-') argumentos.designSystem = idAsset(asset);
  const r = await mcp('generate_screen_from_text', argumentos);
  const salida = path.join('/tmp/opencode', 'stitch-' + Date.now() + '.json');
  fs.mkdirSync(path.dirname(salida), { recursive: true });
  fs.writeFileSync(salida, JSON.stringify(r, null, 2));
  console.log(`Respuesta guardada en ${salida}`);
  for (const c of r.outputComponents || []) {
    for (const s of c.design?.screens || []) {
      console.log(`SCREEN ${s.id}\t${s.name}\t${s.title || ''}\t${s.screenshot?.name || ''}\t${s.htmlCode?.name || ''}`);
    }
  }
}

async function proyecto(nombre) {
  if (!nombre) throw new Error('Falta el proyecto.');
  const r = await mcp('get_project', { name: `projects/${idProyecto(nombre)}` });
  console.log(JSON.stringify(r, null, 2));
}

async function pantalla(proyecto, screen) {
  if (!proyecto || !screen) throw new Error('Uso: screen <proyecto> <screen>');
  const r = await mcp('get_screen', { name: `projects/${idProyecto(proyecto)}/screens/${screen}` });
  console.log(JSON.stringify(r, null, 2));
}

async function pantallas(proyecto) {
  const r = await mcp('list_screens', { projectId: idProyecto(proyecto) });
  for (const s of r.screens || []) {
    console.log([
      s.name,
      s.title || '',
      s.screenType || '',
      s.deviceType || '',
      s.width && s.height ? `${s.width}x${s.height}` : '',
      s.screenshot?.name || '',
      s.htmlCode?.name || '',
    ].join('\t'));
  }
}

async function descargar(recurso, salida) {
  const r = await mcp('download_file', { name: recurso });
  if (r.image) {
    fs.mkdirSync(path.dirname(salida), { recursive: true });
    fs.writeFileSync(salida, Buffer.from(r.image, 'base64'));
    console.log(`${salida} (${r.image_metadata?.mime_type || 'imagen'})`);
    return;
  }
  if (r.html) {
    fs.mkdirSync(path.dirname(salida), { recursive: true });
    fs.writeFileSync(salida, r.html, 'utf8');
    console.log(`${salida} (html)`);
    return;
  }
  console.log(JSON.stringify(r, null, 2));
}

async function exportar(proyecto, carpeta) {
  const r = await mcp('list_screens', { projectId: idProyecto(proyecto) });
  const destino = path.resolve(RAIZ, carpeta || 'design/inicio_dashboard');
  fs.mkdirSync(destino, { recursive: true });
  for (const s of r.screens || []) {
    if (!s.screenshot?.name) continue;
    const base = (s.title || s.name.split('/').pop())
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-zA-Z0-9._-]+/g, '-').replace(/^-+|-+$/g, '').toLowerCase() || 'pantalla';
    const sub = path.join(destino, base);
    fs.mkdirSync(sub, { recursive: true });
    if (s.screenshot?.name) await descargar(s.screenshot.name, path.join(sub, 'screen.png'));
    if (s.htmlCode?.name) await descargar(s.htmlCode.name, path.join(sub, 'code.html'));
    fs.writeFileSync(path.join(sub, 'stitch.json'), JSON.stringify({ name: s.name, title: s.title, deviceType: s.deviceType, width: s.width, height: s.height }, null, 2));
  }
}

const [cmd, ...args] = process.argv.slice(2);
try {
  switch (cmd) {
    case 'list': await listar(); break;
    case 'create': await crear(args[0]); break;
    case 'design-systems': await sistemasDiseno(args[0], args[1] === 'json'); break;
    case 'copy-design': await copiarDiseno(args[0], args[1]); break;
    case 'generate': await generar(args[0], args[1], args[2], args.slice(3).join(' ')); break;
    case 'screens': await pantallas(args[0]); break;
    case 'screen': await pantalla(args[0], args[1]); break;
    case 'project': await proyecto(args[0]); break;
    case 'download': await descargar(args[0], path.resolve(RAIZ, args[1] || 'design/stitch-descarga')); break;
    case 'export': await exportar(args[0], args[1]); break;
    default:
      console.log(fs.readFileSync(import.meta.filename, 'utf8').split('\n').slice(1, 20).join('\n'));
  }
} catch (e) {
  console.error(e.message);
  process.exit(1);
}
