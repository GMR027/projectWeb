'use strict';
const $ = s => document.querySelector(s);
let tipos = {}, cfg = null;

const el = (tag, txt, cls) => { const e = document.createElement(tag); if (txt != null) e.textContent = txt; if (cls) e.className = cls; return e; };
const msgErr = e => String(e && e.message || e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');

const CATEGORIAS = {
  local: { tipos: ['ollama'], titulo: 'Conectar una IA local', nota: 'Modelos que corren en este equipo (Ollama). Nada sale de tu computadora. Abajo se sugieren los que mejor le van a tu hardware.' },
  suscripcion: { tipos: ['claude-code', 'opencode'], titulo: 'Conectar una suscripción', nota: 'Usa el agente de línea de comandos con tu propia sesión de suscripción (inicia sesión antes en el terminal).' },
  api: { tipos: ['openai', 'anthropic', 'opencode-api'], titulo: 'Conectar una API', nota: 'Con API key de un proveedor. Se cifra con el llavero del sistema y no sale de este equipo.' },
};
let categoria = 'local', hwCargado = false;
let conSesion = false, errorWeb = false;
let webEnIA = false;   // una página de Project Web (Centro de IA) mostrada dentro de Herramientas IA, con el menú a la izquierda
let proyectoActual = null, seccionActual = null, modo = 'web';   // modo: web | ia | ajustes

async function abrirSeccion(ruta) {
  seccionActual = ruta; mostrar('web');
  try { await pw.irWeb(ruta); } catch (x) { $('#hueco-msg').textContent = msgErr(x); }
  ajustarMarco();
}
const proyectoIA = () => $('#ia-menu-proyecto').value || proyectoActual;
pw.alProyecto(p => { const cambio = p && p !== proyectoActual; proyectoActual = p || proyectoActual; if (cambio && modo === 'ia') { fijarProyectoMenu(p); pintarMenuIA(); } });
Centro.alAbrirWeb((ruta, seccion) => { seccionActual = seccion || ruta; mostrar('web'); pw.irWeb(ruta).catch(x => { $('#hueco-msg').textContent = msgErr(x); }); });
Ensamblador.alAbrirWeb((ruta, seccion) => { seccionActual = seccion || ruta; mostrar('web'); pw.irWeb(ruta).catch(x => { $('#hueco-msg').textContent = msgErr(x); }); });
Ensamblador.alCambiar(() => pintarMenuIA());
OrdenarIA.alAbrirWeb((ruta, seccion) => { seccionActual = seccion || ruta; mostrar('web'); pw.irWeb(ruta).catch(x => { $('#hueco-msg').textContent = msgErr(x); }); });
OrdenarIA.alCambiar(() => pintarMenuIA());
TablaDoc.alAbrirWeb((ruta, seccion) => { seccionActual = seccion || ruta; mostrar('web'); pw.irWeb(ruta).catch(x => { $('#hueco-msg').textContent = msgErr(x); }); });

// Zoom de la vista (Ctrl +/−/0 también funcionan: los atiende el proceso principal y avisa aquí)
function pintarZoom(z) {
  $('#zoom-pct').textContent = z + ' %'; $('#aj-zoom').value = z; $('#aj-zoom-val').textContent = z + ' %';
}
const zoomA = z => pw.zoom(z);
$('#zoom-mas').addEventListener('click', () => zoomA({ delta: 10 }));
$('#zoom-menos').addEventListener('click', () => zoomA({ delta: -10 }));
$('#zoom-pct').addEventListener('click', () => zoomA(100));
$('#aj-zoom-mas').addEventListener('click', () => zoomA({ delta: 10 }));
$('#aj-zoom-menos').addEventListener('click', () => zoomA({ delta: -10 }));
$('#aj-zoom-rest').addEventListener('click', () => zoomA(100));
$('#aj-zoom').addEventListener('change', e => zoomA(Number(e.target.value)));
pw.alZoom(z => { if (cfg) cfg.zoom = z; pintarZoom(z); });

// La vista web ocupa el hueco de la derecha; se oculta mientras está abierto un panel propio (IA o ajustes)
function ajustarMarco() {
  const enIA = modo === 'ia' && webEnIA, r = (enIA ? $('#ia-web') : $('#hueco')).getBoundingClientRect();
  pw.marcoWeb({ x: r.x, y: r.y, width: r.width, height: r.height }, (enIA || (modo === 'web' && !!seccionActual)) && !errorWeb);
}
// Herramientas IA es un espacio aparte: sin el menú de Project Web
function mostrar(m) {
  modo = conSesion || m === 'ajustes' ? m : 'web';   // sin sesión solo se puede ver el login o los Ajustes (para corregir el servidor)
  m = modo;
  document.body.classList.toggle('login', !conSesion && m === 'web');
  document.body.classList.toggle('sin-sesion', !conSesion);
  $('#hueco').hidden = m !== 'web'; $('#panel-ia').hidden = m !== 'ia'; $('#panel-ajustes').hidden = m !== 'ajustes';
  $('#hueco-error').hidden = !errorWeb; $('#hueco-msg').hidden = errorWeb;
  $('#hamb').hidden = !conSesion || m === 'ajustes'; $('#hamb').title = m === 'ia' ? 'Mostrar u ocultar el menú de Herramientas IA' : 'Menú de Project Web'; $('#hamb').setAttribute('aria-label', $('#hamb').title); $('#zoom-barra').hidden = !conSesion;
  $('#btn-ia').classList.toggle('on', m === 'ia'); $('#btn-ajustes').classList.toggle('on', m === 'ajustes');
  $('#btn-ia').textContent = m === 'ia' ? '← Volver a Project Web' : 'Herramientas IA';
  requestAnimationFrame(ajustarMarco);
}
$('#btn-ia').addEventListener('click', () => { mostrar(modo === 'ia' ? 'web' : 'ia'); if (modo === 'ia') pintarMenuIA().then(() => { if (modo === 'ia' && !webEnIA && !document.querySelector('#ia-menu button.on')) elegirSeccionIA('propuestas'); }); if (modo === 'ia' && !$('#ia-prog').hidden) pintarProgramador(); if (modo === 'ia' && !$('#ia-inc').hidden) pintarInconsistencias(); if (modo === 'ia' && !$('#ia-mat').hidden) pintarMateriales(); if (modo === 'ia' && !$('#ia-int').hidden) pintarInteligentes(); });
// Ajustes por pestañas: cada sección tiene su espacio (se recuerda la última abierta)
function elegirAjuste(tab) {
  document.querySelectorAll('#aj-tabs button').forEach(b => b.classList.toggle('on', b.dataset.aj === tab));
  document.querySelectorAll('#panel-ajustes [data-aj]:not(button)').forEach(e => { e.hidden = e.dataset.aj !== tab; });
  guardado('aj-tab', tab);
  if (tab === 'ia') { elegirCategoria(categoria); pintarEstadoAjIA(); }
  if (tab === 'servidor') sondearServidor();
}
function pintarTareas() {
  for (const [id, tarea] of [['#tarea-rapida', 'rapida'], ['#tarea-avanzada', 'avanzada']]) {
    const s = $(id), o0 = el('option', '(la conexión principal)'); o0.value = '';
    s.replaceChildren(o0, ...(cfg?.proveedores || []).map(opcionConexion)); s.value = cfg?.porTarea?.[tarea] || '';
  }
}
$('#tareas-form').addEventListener('submit', async e => {
  e.preventDefault();
  try {
    await pw.guardarTareas({ rapida: $('#tarea-rapida').value, avanzada: $('#tarea-avanzada').value }); cfg = await pw.config();
    for (const p of ['prog', 'inc', 'mat', 'int', 'gen']) $('#' + p + '-conexion').replaceChildren();   // que cada herramienta tome la nueva por defecto
    $('#tareas-msg').textContent = '✔ Guardado. Las herramientas usarán estas conexiones por defecto.'; pintarTareas();
  } catch (x) { $('#tareas-msg').textContent = '✘ ' + msgErr(x); }
});
function pintarEstadoAjIA() {
  pintarTareas();
  const a = cfg && cfg.proveedores && cfg.proveedores.find(p => p.id === cfg.activa);
  $('#aj-ia-conn').textContent = a ? 'Conexión activa: ' + a.nombre : 'Aún no hay una conexión de IA activa: guarda una abajo.';
}
function abrirAjustes(tab) {
  mostrar('ajustes'); pintarVozAjustes(); pintarAjustesApp();
  elegirAjuste(tab || guardado('aj-tab') || 'vista');
}
document.querySelectorAll('#aj-tabs button').forEach(b => b.addEventListener('click', () => elegirAjuste(b.dataset.aj)));
$('#btn-ajustes').addEventListener('click', () => { if (modo === 'ajustes') mostrar('web'); else abrirAjustes('servidor'); });   // el engranaje lleva directo a «Servidor y actualizaciones» (a dónde apunta la conexión)
// Menú lateral de Herramientas IA: se oculta con la hamburguesa y recuerda su estado
const guardado = (k, v) => { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch { return null; } };
function menuIAOculto(oculto) {
  $('#panel-ia').classList.toggle('menu-oculto', oculto); $('#hamb').classList.toggle('ia-abierto', !oculto); guardado('menuIAOculto', oculto ? '1' : '0');
}
$('#panel-ia').classList.toggle('menu-oculto', guardado('menuIAOculto') === '1');
$('#hamb').addEventListener('click', () => { if (modo === 'ia') menuIAOculto(!$('#panel-ia').classList.contains('menu-oculto')); else pw.menuWeb(); });
// Tema claro/oscuro (Ajustes)
function aplicarTema(t) { document.documentElement.dataset.tema = t === 'claro' ? 'claro' : 'oscuro'; $('#aj-tema').value = t === 'claro' ? 'claro' : 'oscuro'; }
aplicarTema(guardado('tema'));
$('#aj-tema').addEventListener('change', e => { guardado('tema', e.target.value); aplicarTema(e.target.value); });
// Al iniciar solo se ve el login; al entrar aparece la ventana general, y al cerrar sesión se vuelve al login
pw.alSesion(v => { errorWeb = false; conSesion = v; if (v) { proyectosProg = null; proyectosMenuListos = false; if (modo === 'ia') pintarMenuIA(); } pintarChip(); setTimeout(sondearServidor, 300); if (!v) seccionActual = seccionActual || '/dashboard'; mostrar('web'); });
pw.alErrorWeb(d => { errorWeb = true; $('#err-detalle').textContent = 'No se pudo conectar con Project Web (' + d + ').'; $('#err-url').value = cfg?.servidor || ''; mostrar('web'); });
$('#err-form').addEventListener('submit', async e => {
  e.preventDefault();
  try { await pw.guardarServidor($('#err-url').value); await recargar(); errorWeb = false; mostrar('web'); await pw.irWeb('/dashboard'); } catch (x) { $('#err-detalle').textContent = msgErr(x); }
});
new ResizeObserver(ajustarMarco).observe($('#hueco')); new ResizeObserver(ajustarMarco).observe($('#ia-web'));

async function recargar() {
  cfg = await pw.config();
  pintarMenuIA();
  $('#srv-url').value = cfg.servidor;
  const lista = $('#lista-prov');
  lista.replaceChildren(...cfg.proveedores.map(p => {
    const d = el('div', null, 'prov'), t = el('div'), principal = p.id === cfg.activa;
    t.append(el('strong', p.nombre + (principal ? '  ' : '')), el('small', (tipos[p.tipo]?.etiqueta || p.tipo) + (p.modelo ? ' · ' + p.modelo : '') + (p.url ? ' · ' + p.url : '') + (tipos[p.tipo]?.clave ? (p.claveGuardada ? ' · clave guardada' : ' · SIN clave') : '')));
    if (principal) t.firstChild.append(el('span', '● Principal', 'etq-prin'));
    const est = estadoIA[p.id] || { nivel: 'revisando', texto: 'Comprobando…' }, ep = el('small', null, 'estado-ia');
    ep.append(el('i', null, 'pto ' + est.nivel), document.createTextNode(est.texto));
    t.append(ep);
    const act = el('button', principal ? 'En uso' : 'Usar', principal ? 'sec on' : ''); act.disabled = principal; act.title = 'Usar esta conexión como la principal de las herramientas de IA';
    act.addEventListener('click', async () => { await pw.activarConexion(p.id); await recargar(); });
    const b = el('button', 'Eliminar', 'sec');
    b.addEventListener('click', async () => {
      if (!await window.confirmarDesktop({ titulo: 'Eliminar conexión', mensaje: '¿Eliminar «' + p.nombre + '»?', aceptar: 'Eliminar', peligro: true })) return;
      await pw.eliminarProveedor(p.id);
      recargar();
    });
    const rev = el('button', 'Comprobar', 'sec');
    rev.addEventListener('click', () => refrescarEstado(p.id));
    const acc = el('div', null, 'fila'); acc.append(act, rev, b); d.append(t, acc); return d;
  }));
  pintarChip(); pintarEstadoAjIA();
  cfg.proveedores.forEach(p => { if (!estadoIA[p.id]) refrescarEstado(p.id); });
}

const AYUDA_URL = {
  ollama: 'Es la dirección donde corre Ollama en tu equipo. Ya viene la estándar (http://localhost:11434): déjala así. Solo cámbiala si Ollama está en otra computadora.',
  openai: 'Dirección de la API. Para OpenAI ya viene la estándar; cámbiala solo si usas otro proveedor compatible (LM Studio, OpenRouter, etc.).',
  'opencode-api': 'Dirección de opencode Zen (API compatible con OpenAI). Ya viene la estándar; pega tu API key de opencode.ai.',
  anthropic: 'Dirección de la API de Anthropic. Ya viene la estándar; normalmente no hay que tocarla.',
};
let urlEstandar = '';
function ajustarForm() {
  const t = tipos[$('#prov-tipo').value] || {};
  $('#l-url').hidden = t.cli; $('#l-modelo').hidden = t.cli; $('#l-clave').hidden = !t.clave;
  const campo = $('#l-url input');
  if (!campo.value || campo.value === urlEstandar) campo.value = t.url || '';   // solo se reemplaza si el usuario no la cambió
  urlEstandar = t.url || '';
  campo.placeholder = t.url || '';
  $('#url-ayuda').textContent = AYUDA_URL[$('#prov-tipo').value] || ''; $('#url-ayuda').hidden = !!t.cli;
  $('#prov-modelos').hidden = t.cli || t.clave === undefined;
}
$('#prov-tipo').addEventListener('change', ajustarForm);

// Panel lateral de Herramientas IA: local, suscripción, API y conexiones guardadas
function elegirCategoria(cat) {
  categoria = cat;
  document.querySelectorAll('#ia-tabs button').forEach(b => b.classList.toggle('on', b.dataset.cat === cat));
  const c = CATEGORIAS[cat];
  $('#ia-form-sec').hidden = !c; $('#ia-hw').hidden = cat !== 'local'; $('#ia-modelos').hidden = cat !== 'local'; $('#ia-lista-sec').hidden = cat !== 'guardadas';
  if (c) {
    $('#ia-nota').textContent = c.nota; $('#ia-titulo').textContent = c.titulo; $('#prov-msg').textContent = '';
    $('#prov-tipo').replaceChildren(...c.tipos.map(k => { const o = el('option', tipos[k].etiqueta); o.value = k; return o; }));
    ajustarForm();
  }
  if (cat === 'local' && !hwCargado) cargarHardware();
  if (cat === 'local') cargarModelosOllama();
  if (cat === 'guardadas') refrescarTodas();
}
document.querySelectorAll('#ia-tabs button').forEach(b => b.addEventListener('click', () => elegirCategoria(b.dataset.cat)));

// Secciones de Herramientas IA (la configuración de conexiones vive ahora en Ajustes → Configuración de IA)
// Calidad de datos (Inconsistencias + Materiales y normativa) y Análisis inteligente (Pendientes + Historial) son una sola pantalla con pestañas
let cdTab = 'inc', paTab = 'pend';
function pintarCalidad() {
  document.querySelectorAll('#cd-tabs button').forEach(b => b.classList.toggle('on', b.dataset.cd === cdTab));
  $('#ia-inc').hidden = cdTab !== 'inc'; $('#ia-mat').hidden = cdTab !== 'mat'; $('#ia-int').hidden = cdTab !== 'mat';
  if (cdTab === 'inc') { pintarInconsistencias(); pintarHist('inconsistencias'); } else { pintarMateriales(); pintarHist('materiales'); pintarInteligentes(); }
}
function pintarAnalisis() {
  sondearServidor();
  document.querySelectorAll('#pa-tabs button').forEach(b => b.classList.toggle('on', b.dataset.pa === paTab));
  pintarVigilancia();
  $('#pa-pend').hidden = paTab !== 'pend'; $('#pa-hist').hidden = paTab !== 'hist';
  if (paTab === 'pend') Centro.propuestas($('#propuestas-res'), proyectoIA(), pintarMenuIA); else Centro.auditoria($('#auditoria-res'), proyectoIA());
}
document.querySelectorAll('#cd-tabs button').forEach(b => b.addEventListener('click', () => { cdTab = b.dataset.cd; pintarCalidad(); }));
document.querySelectorAll('#pa-tabs button').forEach(b => b.addEventListener('click', () => { paTab = b.dataset.pa; pintarAnalisis(); }));
function elegirSeccionIA(sec) {
  if (webEnIA) { webEnIA = false; $('#ia-web').hidden = true; $('#ia-cuerpo').hidden = false; ajustarMarco(); }
  document.querySelectorAll('#ia-menu button').forEach(b => b.classList.toggle('on', b.dataset.sec === sec));
  $('#ia-prog').hidden = sec !== 'programador'; $('#ia-gen').hidden = sec !== 'gen'; $('#ia-propuestas').hidden = sec !== 'propuestas';
  $('#ia-ens').hidden = sec !== 'ensamblador'; $('#ia-ord').hidden = sec !== 'ordenar-ia'; $('#ia-tdoc').hidden = sec !== 'tabla-doc';
  $('#cd-tabs').hidden = sec !== 'calidad'; if (sec !== 'calidad') for (const x of ['#ia-inc', '#ia-mat', '#ia-int']) $(x).hidden = true;
  if (sec === 'programador') { pintarProgramador(); pintarHist('programador'); }
  else if (sec === 'ensamblador') Ensamblador.pintar();
  else if (sec === 'ordenar-ia') OrdenarIA.pintar();
  else if (sec === 'tabla-doc') TablaDoc.pintar();
  else if (sec === 'calidad') pintarCalidad();
  else if (sec === 'propuestas') pintarAnalisis();
  else pintarGenerica();
}

// ---- Menú de Herramientas IA: grupos, indicadores según la conexión y avisos de capacidad ----
let herramientasMenu = [], genActual = null, ejecutandoGen = false;
const NOMBRE_ESTADO = { ok: 'Funciona bien', limitado: 'Funciona con límites', bloqueado: 'No disponible con esta conexión' };
let proyectosMenuListos = false;
async function cargarProyectosMenu() {
  if (proyectosMenuListos) return;
  const sel = $('#ia-menu-proyecto');
  try { proyectosProg = proyectosProg || await pw.proyectosPrograma(); }
  catch (x) {   // sin sesión de administrador la lista llega vacía: se avisa en vez de dejar el selector en blanco
    const o = el('option', '⚠ ' + msgErr(x)); o.value = ''; sel.replaceChildren(o); proyectosProg = null; return;
  }
  const previo = sel.value || proyectoActual;
  sel.replaceChildren(...(proyectosProg || []).map(p => { const o = el('option', p.codigo + ' · ' + p.nombre); o.value = p.id; return o; }));
  if (previo && (proyectosProg || []).some(p => String(p.id) === String(previo))) sel.value = previo;
  proyectosMenuListos = !!(proyectosProg || []).length;
}
/** El proyecto del menú gobierna las marcas de uso y todas las secciones; las listas de proyecto de cada herramienta lo siguen */
function fijarProyectoMenu(id) {
  if (!id) return;
  const sel = $('#ia-menu-proyecto');
  if ([...sel.options].some(o => String(o.value) === String(id))) sel.value = id;
  for (const s of ['#prog-proyecto', '#inc-proyecto', '#mat-proyecto', '#int-proyecto', '#gen-proyecto']) { const e = $(s); if ([...e.options].some(o => String(o.value) === String(id))) e.value = id; }
}
function repintarSeccion() {
  const sec = document.querySelector('#ia-menu button.on')?.dataset.sec; if (!sec) return;
  elegirSeccionIA(sec.startsWith('gen:') ? 'gen' : sec);
  if (sec.startsWith('gen:')) document.querySelectorAll('#ia-menu button').forEach(b => b.classList.toggle('on', b.dataset.sec === sec));
}
$('#ia-menu-proyecto').addEventListener('change', () => { fijarProyectoMenu($('#ia-menu-proyecto').value); pintarMenuIA(); repintarSeccion(); });
for (const s of ['#prog-proyecto', '#inc-proyecto', '#mat-proyecto', '#int-proyecto', '#gen-proyecto']) $(s).addEventListener('change', e => { fijarProyectoMenu(e.target.value); pintarMenuIA(); });
/** Icono y color de cada herramienta del menú (por id; las generadas por el servidor, por palabras del título; si no, por grupo) */
const ICONOS = { ensamblador: ['🧩', '#0891b2'], 'ordenar-ia': ['🔢', '#0891b2'], 'tabla-doc': ['📄', '#0891b2'], config: ['⚙️', '#64748b'], calidad: ['🔎', '#ea580c'], programador: ['🗓️', '#7c3aed'], inconsistencias: ['🔎', '#ea580c'], materiales: ['🧱', '#0284c7'], inteligentes: ['🧠', '#c026d3'], propuestas: ['✨', '#f59e0b'], auditoria: ['📜', '#64748b'],
  informes: ['📄', '#0ea5e9'], normativa: ['⚖️', '#059669'], mapa: ['🗺️', '#14b8a6'], seguridad: ['🦺', '#eab308'], punch: ['✅', '#22c55e'], cambios: ['🔁', '#f97316'], tipoobra: ['🏗️', '#78716c'], grabaciones: ['🎙️', '#ef4444'] };
const ICONOS_TITULO = [[/ejecutivo/i, ['📊', '#2563eb']], [/semanal/i, ['📈', '#0891b2']], [/reporte diario/i, ['📝', '#16a34a']], [/lookahead/i, ['🔭', '#6366f1']], [/simulador/i, ['🎲', '#a855f7']], [/clima/i, ['⛅', '#38bdf8']], [/riesgo/i, ['⚠️', '#dc2626']],
  [/causas/i, ['🧩', '#d97706']], [/voz|dictado/i, ['🎤', '#ec4899']], [/foto|visi/i, ['📷', '#8b5cf6']], [/minuta|reuni/i, ['🤝', '#0d9488']], [/seguridad|charla/i, ['🦺', '#eab308']], [/punch/i, ['✅', '#22c55e']], [/cierre|entrega/i, ['📦', '#84cc16']], [/normativa/i, ['⚖️', '#059669']]];
const ICONOS_GRUPO = { ensamblador: ['🧩', '#0891b2'], config: ['⚙️', '#64748b'], control: ['🎛️', '#f59e0b'], planeacion: ['🗓️', '#6366f1'], campo: ['👷', '#16a34a'], datos: ['🔎', '#ea580c'], compras: ['🛒', '#0284c7'], documentos: ['📚', '#0d9488'], seguridad: ['🦺', '#eab308'], cierre: ['📦', '#84cc16'], centro: ['🌐', '#0ea5e9'] };
const iconoHerr = h => ICONOS[h.id] || (ICONOS_TITULO.find(([re]) => re.test(h.titulo)) || [])[1] || ICONOS_GRUPO[h.grupo] || ['🔧', '#64748b'];
let menuCentroAbierto = guardado('menu-centro') === '1';
async function pintarMenuIA() {
  let r;
  await cargarProyectosMenu();
  try { r = await pw.estadosHerramientas(); } catch { return; }
  const lista = $('#ia-menu-lista'), actual = document.querySelector('#ia-menu button.on')?.dataset.sec;
  lista.replaceChildren();
  herramientasMenu = r.herramientas;
  for (const g of r.grupos) {
    const hs = r.herramientas.filter(h => h.grupo === g.id && !h.oculto);
    if (!hs.length) continue;
    const grupo = el('div', null, 'grupo'), botones = el('div', null, 'botones');
    let t;
    if (g.id === 'centro') {   // páginas de Project Web: no son IA, van plegadas
      t = el('h4', (menuCentroAbierto ? '▾ ' : '▸ ') + (ICONOS_GRUPO.centro?.[0] || '') + ' ' + g.titulo, 'plegable'); t.tabIndex = 0; t.setAttribute('role', 'button');
      const alternar = () => { menuCentroAbierto = !menuCentroAbierto; guardado('menu-centro', menuCentroAbierto ? '1' : '0'); pintarMenuIA(); };
      t.addEventListener('click', alternar); t.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); alternar(); } });
    } else t = el('h4', (ICONOS_GRUPO[g.id] ? ICONOS_GRUPO[g.id][0] + ' ' : '') + g.titulo);
    grupo.append(t, botones); lista.append(grupo);
    if (g.id === 'centro' && !menuCentroAbierto && !webEnIA) { botones.hidden = true; continue; }
    for (const h of hs) {
      const e = r.estados[h.id], b = el('button');
      b.dataset.id = h.id; if (h.sec) b.dataset.sec = h.sec; if (h.web) b.dataset.web = h.web; if (h.gen) { b.dataset.gen = h.gen; b.dataset.sec = 'gen:' + h.gen; }
      const [ico, col] = iconoHerr(h), chip = el('i', ico, 'ico'); chip.style.setProperty('--ico', col); b.append(chip, el('span', h.titulo, 'tit'));
      b.setAttribute('aria-label', h.titulo);
      if (h.web) { if (seccionActual === h.web && webEnIA) b.classList.add('on'); }
      else if (h.requiere) { const i = el('i', null, 'ind ' + e.estado); b.append(i); }
      b.title = h.titulo + (h.requiere ? ' — ' + NOMBRE_ESTADO[e.estado] + (e.texto && e.estado !== 'ok' ? ': ' + e.texto : '') : '');
      if (e.estado === 'bloqueado') b.disabled = true;
      if (h.sec && h.sec === actual) b.classList.add('on');
      botones.append(b);
    }
  }
  marcarUsoMenu(lista).catch(() => {});
  $('#ia-menu-conn').textContent = r.conexion ? 'Conexión: ' + r.conexion + (r.perfil ? ' (' + { basico: 'modelo pequeño', medio: 'modelo mediano', grande: 'modelo grande / nube' }[r.perfil.nivel] + (r.perfil.vision ? ', con visión' : '') + ')' : '') : 'Sin conexión de IA: guarda una en Ajustes → Configuración de IA.';
}
/** Junto a cada herramienta: ✔ ejecutada y al día, ⟳ desactualizada (volver a ejecutar), ○ sin ejecutar; y tu valoración de resultados (✅ ⚠ ❌) del proyecto abierto */
const USO = { al_dia: ['✔', 'Ejecutada y al día'], desactualizada: ['⟳', 'Desactualizada: los datos cambiaron, conviene volver a ejecutarla'], sin_ejecutar: ['○', 'Sin ejecutar en este proyecto'] };
const VALORACION = { correcto: ['✅', 'Resultados correctos'], revisar: ['⚠', 'Resultados por revisar'], no_cumple: ['❌', 'Resultados rechazados'] };
async function marcarUsoMenu(lista) {
  const pid = proyectoIA();
  if (!pid) return;
  const uso = await pw.usoHerramientas(pid);
  if (!uso) return;
  const porClave = Object.fromEntries(uso.map(u => [u.clave, u]));
  for (const b of lista.querySelectorAll('button[data-id]')) {
    const id = b.dataset.id, u = id === 'calidad' ? ['desactualizada', 'sin_ejecutar', 'al_dia'].map(e => ['inconsistencias', 'materiales'].map(k => porClave[k]).find(x => x && x.estado === e)).find(Boolean) : porClave[id.startsWith('gen:') ? 'informe:' + id.slice(4) : id];
    if (!u) continue;
    const [sim, txt] = USO[u.estado], m = el('span', sim, 'uso ' + u.estado), corto = { al_dia: 'Ejecutada · al día', desactualizada: 'Desactualizada · volver a ejecutar', sin_ejecutar: 'Sin ejecutar' }[u.estado];
    let t = txt;
    if (u.veredicto !== 'sin_evaluar') { m.textContent += ' ' + VALORACION[u.veredicto][0]; t += ' · ' + VALORACION[u.veredicto][1] + (u.revisado ? ' (revisado)' : ''); }
    else if (u.estado !== 'sin_ejecutar') t += u.revisado ? ' · revisado' : ' · resultados sin revisar';
    m.title = t; b.title = b.getAttribute('aria-label') + ' — ' + corto + (u.veredicto !== 'sin_evaluar' ? ' · ' + VALORACION[u.veredicto][1] : ''); b.append(m);
    const sub = el('small', corto + (u.veredicto !== 'sin_evaluar' ? ' · ' + VALORACION[u.veredicto][0] : u.estado !== 'sin_ejecutar' ? (u.revisado ? ' · revisada' : ' · sin revisar') : ''), 'sub ' + u.estado); sub.title = t; b.append(sub);
  }
}
/** Las páginas del Centro de IA (Project Web) se ven dentro de Herramientas IA, a la derecha del menú */
async function abrirWebEnIA(b) {
  const pid = proyectoIA();
  document.querySelectorAll('#ia-menu button').forEach(x => x.classList.toggle('on', x === b));
  webEnIA = true; seccionActual = b.dataset.web; $('#ia-cuerpo').hidden = true; $('#ia-web').hidden = false;
  await new Promise(r => requestAnimationFrame(r)); ajustarMarco();
  try { await pw.irWeb(b.dataset.web + (pid ? '?proyecto=' + encodeURIComponent(pid) : '')); } catch (x) { $('#ia-menu-conn').textContent = msgErr(x); }
  ajustarMarco();
}
$('#ia-menu-lista').addEventListener('click', e => {
  const b = e.target.closest('button');
  if (!b || b.disabled) return;
  if (b.dataset.web) { abrirWebEnIA(b); return; }
  if (b.dataset.gen) { abrirGenerica(b.dataset.gen); return; }
  elegirSeccionIA(b.dataset.sec);
});
/** Aviso bajo el selector de conexión de una herramienta: ¿qué tan bien funcionará con la conexión elegida? Devuelve el estado */
async function pintarCapacidad(herrId, provId, selector) {
  const caja = $(selector);
  try {
    const r = await pw.estadosHerramientas(provId || undefined), e = r.estados[herrId];
    caja.className = 'capacidad ' + e.estado;
    caja.textContent = e.texto ? (e.estado === 'ok' ? '✔ ' : e.estado === 'limitado' ? '⚠ ' : '🔒 ') + e.texto : '';
    return e.estado;
  } catch { caja.textContent = ''; return 'ok'; }
}

// Resultados de cada herramienta (guardar lote, aceptar/rechazar hallazgos) dentro de su misma sección
const HIST = { programador: '#prog-hist', inconsistencias: '#inc-hist', materiales: '#mat-hist' };
const pintarHist = (herr, abrir) => Centro.resultados($(HIST[herr]), herr, proyectoIA(), { alCambiar: pintarMenuIA, abrirPendientes: !!abrir });
// ---- Programador de actividades ----
// ---- Estado de la IA conectada (chip de la cabecera, lista de conexiones y Programador) ----
const estadoIA = {};   // id de conexión → {nivel: 'ok' | 'aviso' | 'error' | 'revisando', texto}
const principal = () => cfg?.proveedores?.find(p => p.id === cfg.activa) || null;
// Uso de la suscripción de Claude Code: barras de la ventana de 5 h y de 7 días (las informa el CLI) y tokens gastados hoy desde la app
let usoClaude = null, usoTexto = '';
const fmtTok = n => n >= 1e6 ? (n / 1e6).toFixed(1) + ' M' : n >= 1e3 ? (n / 1e3).toFixed(1) + ' k' : String(n);
function pintarUsoIA(p) {
  const caja = $('#ia-uso'); usoTexto = '';
  if (!p || p.tipo !== 'claude-code' || !usoClaude) { caja.hidden = true; return; }
  const v = usoClaude.ventanas || {}, h = usoClaude.hoy || {}, filas = [['five_hour', '5 h'], ['seven_day', '7 d']].filter(([k]) => v[k]);
  caja.replaceChildren(...filas.map(([k, et]) => {
    const pct = Math.round(v[k].utilization * 100), b = el('span', null, 'barra-uso ' + (pct >= 90 ? 'alto' : pct >= 70 ? 'medio' : ''));
    b.style.setProperty('--pct', Math.min(100, pct) + '%'); b.append(el('i', null)); const f = el('span', et + ' ' + pct + ' %', 'uso-et');
    const w = el('span', null, 'uso-v'); w.append(f, b); w.title = 'Ventana de ' + (k === 'five_hour' ? '5 horas' : '7 días') + ': ' + pct + ' % usado' + (v[k].resetsAt ? ' · se renueva ' + new Date(v[k].resetsAt * 1000).toLocaleString() : ''); return w;
  }));
  usoTexto = '\n' + filas.map(([k, et]) => 'Suscripción ' + et + ': ' + Math.round(v[k].utilization * 100) + ' %').join(' · ') + '\nHoy en la app: ' + fmtTok((h.entrada || 0) + (h.salida || 0)) + ' tokens (' + fmtTok(h.salida || 0) + ' de salida, ' + fmtTok(h.cache || 0) + ' en caché) · ' + (h.llamadas || 0) + ' consultas';
  caja.append(el('span', fmtTok((h.entrada || 0) + (h.salida || 0)) + ' tok hoy', 'uso-tok'));
  caja.hidden = false;
}
async function leerUsoClaude(sondear) { try { usoClaude = await pw.usoClaude(sondear); } catch { /* sin datos */ } pintarChip(); }
function pintarChip() {
  const p = principal(), chip = $('#ia-chip'), e = p && (estadoIA[p.id] || { nivel: 'revisando', texto: 'Comprobando…' });
  chip.hidden = !conSesion;
  chip.querySelector('i').className = 'pto ' + (e ? e.nivel : 'error');
  $('#ia-chip-txt').textContent = p ? 'IA: ' + (p.modelo || p.nombre) + ' · ' + ({ ok: 'lista', aviso: 'revisar', error: 'sin conexión', revisando: 'comprobando…' }[e.nivel]) : 'IA: sin conexión guardada';
  pintarUsoIA(p); chip.title = p ? p.nombre + '\n' + e.texto + usoTexto + '\n(clic: ver conexiones guardadas)' : 'Aún no hay una IA conectada. Clic para configurarla.';
}
function pintarEstadoProg() {
  for (const [conn, caja] of [['#prog-conexion', '#prog-estado'], ['#inc-conexion', '#inc-estado'], ['#mat-conexion', '#mat-estado'], ['#int-conexion', '#int-estado'], ['#gen-conexion', '#gen-estado']]) {
    const e = estadoIA[$(conn).value];
    $(caja).replaceChildren(el('i', null, 'pto ' + (e ? e.nivel : 'revisando')), document.createTextNode(e ? e.texto : 'Comprobando…'));
  }
}
async function refrescarEstado(id, silencioso = false) {
  if (!id) { pintarChip(); return; }
  if (!silencioso || !estadoIA[id]) { estadoIA[id] = { nivel: 'revisando', texto: 'Comprobando…' }; pintarChip(); pintarEstadoProg(); }   // en las comprobaciones periódicas no parpadea
  try { estadoIA[id] = await pw.estadoConexion(id); } catch (x) { estadoIA[id] = { nivel: 'error', texto: msgErr(x) }; }
  pintarChip(); pintarEstadoProg();
  if (!$('#ia-lista-sec').hidden) recargar();
}
const refrescarTodas = () => (cfg?.proveedores || []).forEach(p => refrescarEstado(p.id));
$('#ia-chip').addEventListener('click', () => { leerUsoClaude('ya'); refrescarTodas(); abrirAjustes('ia'); elegirCategoria('guardadas'); });
setInterval(() => { if (conSesion && principal()) refrescarEstado(principal().id, true); }, 30000);
setInterval(() => { if (conSesion && principal()?.tipo === 'claude-code') leerUsoClaude(true); }, 60000);
setTimeout(() => leerUsoClaude(true), 3000);

let analizando = false;
let proyectosProg = null;
// Parte común de las herramientas con conexión + proyecto: llena ambos selectores (la conexión por defecto sale de Ajustes → Configuración de IA), avisa si falta algo y devuelve la capacidad
const conexionPara = tarea => { const id = cfg?.porTarea?.[tarea]; return (cfg?.proveedores || []).some(p => p.id === id) ? id : (cfg?.activa || ''); };
const opcionConexion = p => { const o = el('option', p.nombre + ' · ' + (tipos[p.tipo]?.etiqueta || p.tipo) + (p.modelo ? ' · ' + p.modelo : '')); o.value = p.id; return o; };
async function llenarComun(pref, herrId, tarea) {
  const sel = $('#' + pref + '-conexion'), previa = sel.value, selP = $('#' + pref + '-proyecto'), previoP = selP.value || proyectoActual, msg = $('#' + pref + '-msg');
  sel.replaceChildren(...(cfg?.proveedores || []).map(opcionConexion));
  sel.value = previa && [...sel.options].some(o => o.value === previa) ? previa : conexionPara(tarea);
  if (!proyectosProg) {
    msg.textContent = 'Cargando proyectos…';
    try { proyectosProg = await pw.proyectosPrograma(); msg.textContent = ''; } catch (x) { msg.textContent = '✘ ' + msgErr(x); proyectosProg = null; }
  }
  selP.replaceChildren(...(proyectosProg || []).map(p => { const o = el('option', p.codigo + ' · ' + p.nombre); o.value = p.id; return o; }));
  if (previoP && (proyectosProg || []).some(p => String(p.id) === String(previoP))) selP.value = previoP;   // por defecto, el proyecto abierto en Project Web
  const sinConn = !(cfg?.proveedores || []).length;
  if (sinConn) msg.textContent = 'Primero guarda una conexión en Ajustes → Configuración de IA.';
  else if (proyectosProg && !proyectosProg.length) msg.textContent = 'No hay proyectos para analizar.';
  const est = sinConn ? 'ok' : await pintarCapacidad(herrId, sel.value, '#' + pref + '-capacidad');
  return { sel, selP, sinConn, est };
}
async function pintarProgramador() {
  const { sel, selP, sinConn, est: estProg } = await llenarComun('prog', 'programador', 'avanzada');
  $('#prog-ir').disabled = analizando || sinConn || !selP.value || estProg === 'bloqueado';
  pintarEstadoProg(); if (sel.value && !estadoIA[sel.value]) refrescarEstado(sel.value);
}
$('#prog-proyecto').addEventListener('change', pintarProgramador);
$('#prog-conexion').addEventListener('change', () => { pintarEstadoProg(); refrescarEstado($('#prog-conexion').value); });
pw.alAvanceAnalisis(p => { $('#prog-msg').textContent = p.texto; $('#prog-barra').max = p.total || 1; $('#prog-barra').value = p.hecho || 0; });
$('#prog-cancelar').addEventListener('click', () => { pw.cancelarAnalisis(); $('#prog-msg').textContent = 'Cancelando al terminar el lote en curso…'; });
$('#prog-form').addEventListener('submit', async e => {
  e.preventDefault();
  const pid = $('#prog-proyecto').value, res = $('#prog-res');
  analizando = true; res.hidden = true; $('#prog-barra').hidden = false; $('#prog-cancelar').hidden = false; pintarProgramador();
  try {
    const r = await pw.analizarPrograma(pid, $('#prog-conexion').value), s = r.resumen;
    const caja = el('div', null, 'prov'), t = el('div');
    t.append(el('strong', 'Propuesta lista para «' + r.proyecto + '»'),
      el('small', s.movidas + ' de ' + s.actividades + ' actividades cambian · ' + s.administrativas + ' administrativas, ' + s.operativas + ' operativas · fin ' + s.fin_actual + ' → ' + s.fin_propuesto + ' (' + (s.dif_dias_habiles > 0 ? '+' : '') + s.dif_dias_habiles + ' días hábiles) · ' + (s.n_mejoras || 0) + ' mejoras sugeridas'));
    (s.avisos || []).forEach(a => t.append(el('small', '⚠ ' + a)));
    const ver = el('button', 'Abrir en Project Web');
    ver.addEventListener('click', () => { seccionActual = '/programador'; mostrar('web'); pw.irWeb('/programador?proyecto=' + pid + '&propuesta=' + r.id); });
    caja.append(t, ver); res.replaceChildren(caja); res.hidden = false;
    $('#prog-msg').textContent = 'Listo. El programa actual no se modificó.'; pintarHist('programador', true); pintarMenuIA();
  } catch (x) { $('#prog-msg').textContent = '✘ ' + msgErr(x); }
  analizando = false; $('#prog-barra').hidden = true; $('#prog-cancelar').hidden = true; pintarProgramador();
});

// ---- Grabadora de voz: micrófono → WAV 16 kHz → motor de transcripción local → texto en el campo ----
let genGrabacionId = 0;
function grabadora(campo) {
  const caja = el('div', null, 'voz-barra'), rec = el('button', '🎙 Grabar'), parar = el('button', '⏹ Detener'), t = el('span', '0:00', 'voz-tiempo'), est = el('span', '', 'voz-estado');
  rec.type = parar.type = 'button'; parar.hidden = true; rec.className = 'rec';
  const guardar = el('label', null, 'campo-check'), chk = el('input'); chk.type = 'checkbox'; chk.checked = true; guardar.append(chk, document.createTextNode(' Guardar la grabación en el proyecto'));
  caja.append(rec, parar, t, guardar, est);
  let mr = null, trozos = [], flujo = null, ini = 0, reloj = null;
  const fmt = s => Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  rec.addEventListener('click', async () => {
    try {
      const e = await pw.vozEstado();
      if (!e.disponible) est.textContent = 'Aviso: no hay motor de transcripción (' + e.detalle + '). Se grabará el audio, pero tendrás que escribir el texto.';
      flujo = await navigator.mediaDevices.getUserMedia({ audio: true });
      mr = new MediaRecorder(flujo, MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? { mimeType: 'audio/webm;codecs=opus' } : undefined);
      trozos = []; mr.ondataavailable = ev => { if (ev.data.size) trozos.push(ev.data); };
      mr.onstop = () => procesar(new Blob(trozos, { type: mr.mimeType || 'audio/webm' }));
      mr.start(1000); ini = Date.now(); rec.hidden = true; parar.hidden = false;
      reloj = setInterval(() => { t.textContent = fmt(Math.round((Date.now() - ini) / 1000)); }, 500);
      if (!(await pw.vozEstado()).disponible) { /* ya avisado */ } else est.textContent = 'Grabando…';
    } catch (x) { est.textContent = 'No se pudo usar el micrófono: ' + msgErr(x); }
  });
  parar.addEventListener('click', () => { clearInterval(reloj); parar.hidden = true; rec.hidden = false; flujo?.getTracks().forEach(p => p.stop()); if (mr && mr.state !== 'inactive') mr.stop(); });
  async function procesar(blob) {
    const seg = Math.round((Date.now() - ini) / 1000);
    let texto = '';
    try {
      est.textContent = 'Convirtiendo el audio…';
      const { wav } = await Wav.blobAWav(blob);
      const e = await pw.vozEstado();
      if (e.disponible) { est.textContent = 'Transcribiendo con ' + e.detalle + '… (puede tardar unos minutos)'; texto = await pw.vozTranscribir(wav, undefined); campo.value = (campo.value ? campo.value.trimEnd() + '\n\n' : '') + texto; est.textContent = texto ? 'Transcripción lista: revísala y corrígela.' : 'No se reconoció voz en el audio.'; }
    } catch (x) { est.textContent = '✘ ' + msgErr(x); }
    if (chk.checked) {
      try {
        const pid = $('#gen-proyecto').value;
        genGrabacionId = await pw.vozSubir(pid, new Uint8Array(await blob.arrayBuffer()), blob.type, 'Grabación ' + new Date().toLocaleString('es-MX'), seg, texto);
        est.textContent += ' Grabación guardada en el proyecto.';
      } catch (x) { est.textContent += ' ✘ No se guardó la grabación: ' + msgErr(x); }
    }
  }
  return caja;
}
// ---- Indicador de conexión y sincronización con el servidor (Ajustes → Servidor y Análisis inteligente) ----
let syncEstado = { nivel: 'conectando', texto: 'Conectando…', det: '' }, syncBusy = false;
function pintarSync() {
  document.querySelectorAll('[data-sync]').forEach(c => {
    c.className = 'sync sync-' + syncEstado.nivel;
    c.querySelector('.sync-txt').textContent = syncEstado.texto; c.querySelector('.sync-det').textContent = syncEstado.det;
  });
}
async function sondearServidor() {
  if (syncBusy) return; syncBusy = true;
  syncEstado = { ...syncEstado, nivel: syncEstado.nivel === 'ok' ? 'sincronizando' : 'conectando', texto: syncEstado.nivel === 'ok' ? 'Sincronizando…' : 'Conectando con el servidor…' }; pintarSync();
  const hora = () => new Date().toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  try {
    const r = await pw.pingServidor();
    syncEstado = !r.conectado ? { nivel: 'error', texto: 'Sin conexión con el servidor', det: 'Reintentando cada 30 s' }
      : !r.sesion ? { nivel: 'aviso', texto: 'Servidor conectado · falta iniciar sesión como administrador', det: r.ms + ' ms' }
      : { nivel: 'ok', texto: 'Conectado y sincronizando', det: 'Última sincronización ' + hora() + ' · ' + r.ms + ' ms' };
  } catch (x) { syncEstado = { nivel: 'error', texto: 'Sin conexión con el servidor', det: msgErr(x) }; }
  syncBusy = false; pintarSync();
}
setInterval(() => { if (!$('#act').hidden || !$('#ia-propuestas').hidden) sondearServidor(); }, 30000);

function pintarVigilancia() {
  const v = cfg?.vigilancia || {};
  $('#vig-activa').checked = !!v.activa; $('#vig-minutos').value = String(v.minutos || 30);
  $('#vig-estado').textContent = v.activa ? '● Activa · cada ' + $('#vig-minutos').selectedOptions[0].textContent : '○ Desactivada'; $('#vig-estado').style.color = v.activa ? '#22c55e' : '';
}
async function pintarVozAjustes() {
  try {
    const c = cfg?.voz || {}; $('#voz-motor').value = c.motor || 'auto'; $('#voz-modelo').value = c.modelo || ''; $('#voz-idioma').value = c.idioma || 'auto';
    const sel = $('#voz-conexion'); sel.replaceChildren(...[el('option', '(ninguna)'), ...(cfg?.proveedores || []).filter(p => p.tipo === 'openai').map(p => { const o = el('option', p.nombre); o.value = p.id; return o; })]); sel.firstChild.value = ''; sel.value = c.conexion || '';
    const e = await pw.vozEstado(); $('#voz-estado').textContent = e.disponible ? '✔ Transcripción disponible: ' + e.detalle : '⚠ Sin motor de transcripción. ' + e.detalle; $('#voz-ayuda').textContent = e.instrucciones || 'Ya tienes un motor configurado.';
  } catch (x) { $('#voz-estado').textContent = '✘ ' + msgErr(x); }
}
$('#vig-form').addEventListener('submit', async e => {
  e.preventDefault();
  try { await pw.vigilanciaGuardar({ activa: $('#vig-activa').checked, minutos: Number($('#vig-minutos').value) }); cfg = await pw.config(); pintarVigilancia(); $('#vig-msg').textContent = $('#vig-activa').checked ? '✔ Vigilancia activa: se comprobará cada ' + $('#vig-minutos').selectedOptions[0].textContent + ' mientras la app esté abierta.' : 'Vigilancia desactivada.'; } catch (x) { $('#vig-msg').textContent = '✘ ' + msgErr(x); }
});
$('#vig-activa').addEventListener('change', () => $('#vig-form').requestSubmit());
$('#vig-minutos').addEventListener('change', () => { if ($('#vig-activa').checked) $('#vig-form').requestSubmit(); });
$('#vig-ahora').addEventListener('click', async () => { $('#vig-msg').textContent = 'Comprobando…'; try { await pw.vigilanciaAhora(); $('#vig-msg').textContent = 'Comprobación hecha. Si hay algo nuevo recibirás una notificación (también lo ves en Inconsistencias → Vigilancia continua).'; } catch (x) { $('#vig-msg').textContent = '✘ ' + msgErr(x); } });
pw.alAbrirVigilancia(() => { seccionActual = '/inconsistencias'; mostrar('web'); });
async function pintarOcr() {
  const e = await pw.ocr.estado();
  $('#ocr-estado').textContent = e.instalado ? '✔ OCR instalado (español e inglés).' : 'El OCR no está instalado.';
  $('#ocr-instalar').textContent = e.instalado ? 'Reinstalar' : 'Instalar OCR'; $('#ocr-quitar').hidden = !e.instalado;
}
pw.ocr.alAvance(p => { $('#ocr-barra').hidden = false; $('#ocr-barra').max = p.total || 1; $('#ocr-barra').value = p.hecho || 0; if (p.texto) $('#ocr-estado').textContent = p.texto; });
$('#ocr-instalar').addEventListener('click', async () => {
  $('#ocr-instalar').disabled = true; $('#ocr-barra').hidden = false;
  try { await pw.ocr.instalar(); await pintarOcr(); } catch (x) { $('#ocr-estado').textContent = '✘ ' + msgErr(x); }
  $('#ocr-instalar').disabled = false; $('#ocr-barra').hidden = true;
});
$('#ocr-quitar').addEventListener('click', async () => { await pw.ocr.quitar(); pintarOcr(); });
pintarOcr().catch(() => {});
$('#voz-form').addEventListener('submit', async e => {
  e.preventDefault();
  try { const r = await pw.vozGuardar({ motor: $('#voz-motor').value, modelo: $('#voz-modelo').value, idioma: $('#voz-idioma').value, conexion: $('#voz-conexion').value }); cfg = await pw.config(); $('#voz-estado').textContent = r.disponible ? '✔ Guardado. Transcripción disponible: ' + r.detalle : '⚠ Guardado, pero no hay motor disponible. ' + r.detalle; $('#voz-ayuda').textContent = r.instrucciones || 'Ya tienes un motor configurado.'; } catch (x) { $('#voz-estado').textContent = '✘ ' + msgErr(x); }
});

// ---- Herramientas que producen informes (reporte diario, resumen semanal…): una sola pantalla que se adapta a cada ficha ----
function abrirGenerica(gen) {
  genActual = herramientasMenu.find(h => h.gen === gen) || null;
  if (!genActual) return;
  elegirSeccionIA('gen'); document.querySelectorAll('#ia-menu button').forEach(b => b.classList.toggle('on', b.dataset.gen === gen));
}
function campoParametro(p) {
  if (p.oculto) return el('span');
  const l = el('label', p.etiqueta + ' '); let i;
  if (p.tipo === 'opcion') { i = el('select'); for (const [k, n] of Object.entries(p.opciones || {})) { const o = el('option', n); o.value = k; i.append(o); } i.value = p.defecto ?? i.value; }
  else if (p.tipo === 'texto' && (p.max || 0) > 500) { i = el('textarea'); i.rows = 6; i.maxLength = p.max; i.value = p.defecto ?? ''; }
  else { i = el('input'); i.type = p.tipo === 'fecha' ? 'date' : p.tipo === 'numero' ? 'number' : 'text'; if (p.tipo === 'numero') { if (p.min != null) i.min = p.min; if (p.max != null) i.max = p.max; } if (p.max && p.tipo === 'texto') i.maxLength = p.max; i.value = p.defecto ?? ''; }
  i.name = p.nombre; l.append(i);
  if (p.voz) { const caja = el('div', null, 'voz-caja'); l.append(grabadora(i)); }
  return l;
}
async function pintarGenerica() {
  const h = genActual; if (!h) return;
  $('#gen-titulo').textContent = h.titulo; $('#gen-desc').textContent = h.descripcion;
  const sel = $('#gen-conexion'), previa = sel.value, selP = $('#gen-proyecto'), previoP = selP.value || proyectoActual;
  const params = $('#gen-params');
  if (params.dataset.herr !== h.id) { genGrabacionId = 0; params.replaceChildren(...(h.parametros || []).map(campoParametro)); params.dataset.herr = h.id; $('#gen-res').hidden = true; $('#gen-msg').textContent = ''; }
  sel.replaceChildren(...(cfg?.proveedores || []).map(opcionConexion));
  sel.value = previa && [...sel.options].some(o => o.value === previa) ? previa : conexionPara('avanzada');
  if (!proyectosProg) { try { proyectosProg = await pw.proyectosPrograma(); } catch (x) { $('#gen-msg').textContent = '✘ ' + msgErr(x); proyectosProg = null; } }
  selP.replaceChildren(...(proyectosProg || []).map(p => { const o = el('option', p.codigo + ' · ' + p.nombre); o.value = p.id; return o; }));
  if (previoP && (proyectosProg || []).some(p => String(p.id) === String(previoP))) selP.value = previoP;
  const sinConn = !(cfg?.proveedores || []).length;
  const est = sinConn ? 'ok' : await pintarCapacidad(h.id, sel.value, '#gen-capacidad');
  if (sinConn) { $('#gen-capacidad').className = 'capacidad limitado'; $('#gen-capacidad').textContent = '⚠ Aún no hay una conexión de IA: puedes generar el informe sin IA (datos reales y texto de plantilla).'; }
  $('#gen-ir').disabled = ejecutandoGen || sinConn || !selP.value || est === 'bloqueado';
  $('#gen-sin').disabled = ejecutandoGen || !selP.value;
  const lote = h.gen === 'reporte_diario'; $('#lote-form').hidden = !lote; if (lote) previoLote();
  if (lote) { $('#lote-ir').disabled = ejecutandoGen || sinConn || !selP.value || est === 'bloqueado'; $('#lote-sin').disabled = ejecutandoGen || !selP.value; if (!$('#lote-fecha').value) { $('#lote-fecha').value = fechaLocal(new Date()); $('#lote-mes').value = fechaLocal(new Date()).slice(0, 7); } }
  pintarEstadoProg(); if (sel.value && !estadoIA[sel.value]) refrescarEstado(sel.value);
}
$('#gen-proyecto').addEventListener('change', pintarGenerica);
$('#gen-conexion').addEventListener('change', () => { pintarEstadoProg(); refrescarEstado($('#gen-conexion').value); pintarGenerica(); });
let lotePref = '';
pw.alAvanceHerramienta(p => { $('#gen-msg').textContent = (lotePref ? lotePref + ' · ' : '') + p.texto; $('#gen-barra').max = p.total || 1; $('#gen-barra').value = p.hecho || 0; });
$('#gen-cancelar').addEventListener('click', () => { loteCancelado = true; pw.cancelarHerramienta(); $('#gen-msg').textContent = 'Cancelando al terminar el paso en curso…'; });
async function ejecutarGenerica(sinIA) {
  const h = genActual, pid = $('#gen-proyecto').value, res = $('#gen-res');
  const parametros = {}; $('#gen-params').querySelectorAll('input,select,textarea').forEach(i => { if (i.name) parametros[i.name] = i.value; });
  if (h.grabacion && genGrabacionId) parametros.grabacion = genGrabacionId;
  ejecutandoGen = true; res.hidden = true; $('#gen-barra').hidden = false; $('#gen-cancelar').hidden = false; pintarGenerica();
  try {
    const r = await pw.ejecutarHerramienta(h.gen, pid, $('#gen-conexion').value, parametros, sinIA);
    const caja = el('div', null, 'prov'), t = el('div');
    t.append(el('strong', 'Informe listo: ' + r.titulo), el('small', (r.con_ia ? 'Redactado con IA: revísalo antes de usarlo.' : 'Generado sin IA (datos reales y texto de plantilla).') + ' Queda como borrador.'));
    (r.fallidos || []).forEach(a => t.append(el('small', '⚠ La IA no pudo redactar un paso (' + a + '); se usó texto de plantilla.')));
    const ver = el('button', 'Ver informe');
    ver.addEventListener('click', () => { seccionActual = '/informes-ia'; mostrar('web'); pw.irWeb(r.url); });
    caja.append(t, ver); res.replaceChildren(caja); res.hidden = false;
    $('#gen-msg').textContent = 'Listo.';
  } catch (x) { $('#gen-msg').textContent = '✘ ' + msgErr(x); }
  ejecutandoGen = false; $('#gen-barra').hidden = true; $('#gen-cancelar').hidden = true; pintarGenerica();
}
$('#gen-form').addEventListener('submit', e => { e.preventDefault(); ejecutarGenerica(false); });
$('#gen-sin').addEventListener('click', () => ejecutarGenerica(true));

// ---- Reportes diarios atrasados (por semana o mes): se generan como borradores y se aceptan o rechazan uno por uno ----
let loteCancelado = false;
const fechaLocal = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
const fechaLarga = f => new Date(f + 'T12:00:00').toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
function rangoLote() {
  if ($('#lote-modo').value === 'mes') {
    const [a, m] = ($('#lote-mes').value || fechaLocal(new Date()).slice(0, 7)).split('-').map(Number);
    return [fechaLocal(new Date(a, m - 1, 1)), fechaLocal(new Date(a, m, 0))];
  }
  const d = new Date(($('#lote-fecha').value || fechaLocal(new Date())) + 'T12:00:00'), lun = new Date(d); lun.setDate(d.getDate() - ((d.getDay() + 6) % 7));
  const dom = new Date(lun); dom.setDate(lun.getDate() + 6);
  return [fechaLocal(lun), fechaLocal(dom)];
}
function modoLote() { const mes = $('#lote-modo').value === 'mes'; $('#lote-mes-l').hidden = !mes; $('#lote-rapido').hidden = !mes; $('#lote-fecha-l').hidden = mes; previoLote(); }
/** Avisa cuántos días faltan por reporte en el periodo elegido, antes de generar nada */
let previoSeq = 0;
async function previoLote() {
  const pid = $('#gen-proyecto').value, out = $('#lote-previo'), seq = ++previoSeq;
  if (!pid || $('#lote-form').hidden) { out.textContent = ''; return; }
  const [desde, hasta] = rangoLote(); out.textContent = 'Revisando…';
  try {
    const r = await pw.apiIA('GET', '/api/ia/dias-sin-reporte', { proyecto: pid, desde, hasta });
    if (seq !== previoSeq) return;
    const hab = r.dias.filter(d => d.habil).length, vacios = r.dias.filter(d => d.sin_cambios).length;
    out.textContent = r.dias.length ? 'Faltan ' + hab + ' día(s) laborables' + (r.dias.length > hab ? ' (y ' + (r.dias.length - hab) + ' no laborables)' : '') + ' por reporte entre ' + desde + ' y ' + hasta + '.' + (vacios ? ' ' + vacios + ' sin ningún cambio se omitirán.' : '') : 'Todos los días de este periodo ya tienen reporte.';
  } catch { if (seq === previoSeq) out.textContent = ''; }
}
$('#lote-modo').addEventListener('change', modoLote);
$('#lote-mes').value = fechaLocal(new Date()).slice(0, 7); $('#lote-mes').addEventListener('change', previoLote); $('#lote-fecha').addEventListener('change', previoLote); $('#lote-todos').addEventListener('change', previoLote);
$('#lote-rapido').addEventListener('click', e => { const b = e.target.closest('button[data-mes]'); if (!b) return; const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() + Number(b.dataset.mes)); $('#lote-mes').value = fechaLocal(d).slice(0, 7); previoLote(); });
$('#gen-proyecto').addEventListener('change', previoLote);

/** Secciones de un informe → DOM (sin innerHTML: la vista no admite estilos ni scripts en línea) */
function pintarSecciones(secs) {
  return (secs || []).map((s, i) => {
    const c = el('div', null, 'res-sec' + (i < 2 || s.tipo === 'kpis' ? ' res-resumen' : ''));
    if (s.tipo !== 'kpis' || i > 0) c.append(el('h4', s.titulo));
    if (s.tipo === 'kpis') { const g = el('div', null, 'res-kpis'); (s.kpis || []).forEach(k => { const d = el('div'); d.append(el('small', k.etq), el('strong', k.valor)); g.append(d); }); c.append(g); }
    else if (s.tipo === 'lista') { const u = el('ul'); (s.items || []).forEach(t => u.append(el('li', t))); c.append(u.children.length ? u : el('p', 'Sin elementos.', 'nota')); }
    else if (s.tipo === 'tabla') {
      if (!(s.filas || []).length) c.append(el('p', 'Sin elementos.', 'nota'));
      else { const w = el('div', null, 'res-tabla-wrap'), t = el('table', null, 'res-tabla'), h = el('tr'); (s.columnas || []).forEach(x => h.append(el('th', x))); t.append(el('thead')); t.firstChild.append(h);
        const b = el('tbody'); s.filas.forEach(f => { const r = el('tr'); f.forEach(x => r.append(el('td', x))); b.append(r); }); t.append(b); w.append(t); c.append(w); }
    } else (String(s.texto || '').split(/\n{2,}/)).forEach(par => { if (par.trim()) c.append(el('p', par.trim())); });
    if (i >= 2 && s.tipo !== 'kpis') c.classList.remove('res-resumen');
    return c;
  });
}
function tarjetaReporte(pid, inf, fecha) {
  const caja = el('div', null, 'rep'), cab = el('div', null, 'rep-cab'), t = el('div'), acc = el('div', null, 'rep-acc');
  const vacio = !!inf.sin_cambios && !inf.aplicado, estado = el('span', inf.aplicado ? 'Aceptado' : inf.estado === 'descartado' ? 'Rechazado' : 'Pendiente de revisar', 'rep-estado');
  if (vacio) caja.classList.add('sin-cambios');
  t.append(el('strong', (vacio ? '🔴 ' : '') + fechaLarga(fecha)), vacio ? el('small', 'Sin cambios: no hubo avances, bitácora, entregas ni hitos. Considera rechazarlo.', 'rep-alerta') : '', el('small', (inf.origen === 'ia' ? 'Redactado con IA: revísalo antes de aceptar.' : 'Borrador automático (sin IA).')));
  const detalle = el('button', 'Ver a Detalle', 'sec'), ok = el('button', 'Aceptar'), no = el('button', 'Rechazar', 'sec');
  detalle.type = ok.type = no.type = 'button';
  const resolver = async accion => {
    ok.disabled = no.disabled = true;
    try { const r = await pw.apiIA('POST', '/api/ia/informe-resolver', { proyecto: pid, id: inf.id, accion }); caja.classList.add(accion === 'aceptar' ? 'aceptado' : 'rechazado'); estado.textContent = accion === 'aceptar' ? 'Aceptado · reporte diario creado' : 'Rechazado'; ok.remove(); no.remove(); caja.classList.remove('error'); caja.dataset.resuelto = '1'; if (r.mensaje) estado.title = r.mensaje; if (accion !== 'aceptar') setTimeout(() => { caja.remove(); actualizarTituloLote(); }, 900); }   // lo rechazado desaparece para no ocupar espacio
    catch (x) { caja.classList.add('error'); estado.textContent = '✘ ' + msgErr(x); ok.disabled = no.disabled = false; }
    actualizarTituloLote();
  };
  ok.addEventListener('click', () => resolver('aceptar')); no.addEventListener('click', () => resolver('rechazar'));
  detalle.addEventListener('click', () => { const a = caja.classList.toggle('abierta'); detalle.textContent = a ? 'Ocultar detalle' : 'Ver a Detalle'; });
  caja.pintarOk = ok; caja.pintarNo = no; caja.resolver = resolver;
  if (inf.aplicado || inf.estado === 'descartado') { caja.classList.add(inf.aplicado ? 'aceptado' : 'rechazado'); caja.dataset.resuelto = '1'; acc.append(detalle); if (!inf.aplicado) caja.hidden = true; } else acc.append(detalle, ok, no);
  cab.append(t, estado, acc);
  const cuerpo = el('div', null, 'rep-cuerpo'); cuerpo.append(...pintarSecciones(inf.secciones));
  caja.append(cab, cuerpo);
  return caja;
}
function actualizarTituloLote() {
  const l = [...$('#lote-lista').children].filter(c => !c.hidden), pend = l.filter(c => !c.dataset.resuelto).length;
  $('#lote-titulo').textContent = l.length + ' reporte(s) · ' + pend + ' por revisar';
  $('#lote-aceptar-todos').disabled = $('#lote-rechazar-todos').disabled = !pend;
}
$('#lote-aceptar-todos').addEventListener('click', async () => { for (const c of [...$('#lote-lista').children]) if (!c.dataset.resuelto && c.resolver) await c.resolver('aceptar'); });
$('#lote-rechazar-todos').addEventListener('click', async () => { for (const c of [...$('#lote-lista').children]) if (!c.dataset.resuelto && c.resolver) await c.resolver('rechazar'); });

async function generarLote(sinIA) {
  const h = genActual, pid = $('#gen-proyecto').value, msg = $('#lote-msg'), [desde, hasta] = rangoLote();
  const area = $('#gen-params').querySelector('[name=area]')?.value || 'General';
  ejecutandoGen = true; loteCancelado = false; $('#lote-res').hidden = true; $('#lote-lista').replaceChildren(); msg.textContent = 'Buscando los días sin reporte…';
  $('#gen-cancelar').hidden = false; $('#gen-barra').hidden = false; pintarGenerica();
  try {
    const r = await pw.apiIA('GET', '/api/ia/dias-sin-reporte', { proyecto: pid, desde, hasta });
    const todos = r.dias.filter(d => d.habil || $('#lote-todos').checked), bor = r.borradores || {};
    // los días sin ningún cambio no se generan (salvo que ya exista un borrador, que se muestra marcado en rojo)
    const dias = todos.filter(d => !d.sin_cambios || bor[d.fecha]), omitidos = todos.length - dias.length;
    if (!dias.length) {
      if (omitidos) { msg.textContent = 'Los ' + omitidos + ' día(s) sin reporte entre ' + desde + ' y ' + hasta + ' no tuvieron ningún cambio, así que se omitieron.'; return; } msg.textContent = 'No hay días sin reporte entre ' + desde + ' y ' + hasta + (r.con_reporte ? ' (' + r.con_reporte + ' ya tienen reporte).' : '.'); return; }
    $('#lote-res').hidden = false;
    let falla = 0;
    for (let i = 0; i < dias.length && !loteCancelado; i++) {
      const f = dias[i].fecha; lotePref = 'Día ' + (i + 1) + ' de ' + dias.length + ' (' + f + ')';
      msg.textContent = lotePref + '…';
      try {
        let id = bor[f];
        if (!id) id = (await pw.ejecutarHerramienta(h.gen, pid, $('#gen-conexion').value, { fecha: f, area }, sinIA)).informe_id;
        const inf = await pw.apiIA('GET', '/api/ia/informe', { proyecto: pid, id });
        $('#lote-lista').append(tarjetaReporte(pid, inf, f)); actualizarTituloLote();
      } catch (x) { if (/cancelado/i.test(msgErr(x))) break; falla++; const e = el('div', '✘ ' + f + ': ' + msgErr(x), 'rep error'); $('#lote-lista').append(e); }
    }
    msg.textContent = (loteCancelado ? 'Cancelado. ' : 'Listo. ') + 'Revisa cada reporte: «Ver a Detalle» muestra toda la información; después acéptalo o recházalo.' + (omitidos ? ' Se omitieron ' + omitidos + ' día(s) sin ningún cambio.' : '') + (falla ? ' ' + falla + ' día(s) fallaron.' : '');
  } catch (x) { msg.textContent = '✘ ' + msgErr(x); }
  finally { lotePref = ''; ejecutandoGen = false; $('#gen-barra').hidden = true; $('#gen-cancelar').hidden = true; pintarGenerica(); actualizarTituloLote(); }
}
$('#lote-form').addEventListener('submit', e => { e.preventDefault(); generarLote(false); });
$('#lote-sin').addEventListener('click', () => generarLote(true));

// ---- Inconsistencias de proyecto ----
let revisando = false;
async function pintarInconsistencias() {
  const { sel, selP, sinConn, est: estInc } = await llenarComun('inc', 'inconsistencias', 'rapida');
  $('#inc-ir').disabled = revisando || sinConn || !selP.value || estInc === 'bloqueado';
  pintarEstadoProg(); if (sel.value && !estadoIA[sel.value]) refrescarEstado(sel.value);
}
$('#inc-proyecto').addEventListener('change', pintarInconsistencias);
$('#inc-conexion').addEventListener('change', () => { pintarEstadoProg(); refrescarEstado($('#inc-conexion').value); });
pw.alAvanceInconsistencias(p => { $('#inc-msg').textContent = p.texto; $('#inc-barra').max = p.total || 1; $('#inc-barra').value = p.hecho || 0; });
$('#inc-cancelar').addEventListener('click', () => { pw.cancelarInconsistencias(); $('#inc-msg').textContent = 'Cancelando al terminar el paquete en curso…'; });
$('#inc-form').addEventListener('submit', async e => {
  e.preventDefault();
  const pid = $('#inc-proyecto').value, res = $('#inc-res');
  revisando = true; res.hidden = true; $('#inc-barra').hidden = false; $('#inc-cancelar').hidden = false; pintarInconsistencias();
  try {
    const r = await pw.analizarInconsistencias(pid, $('#inc-conexion').value), s = r.resumen;
    const caja = el('div', null, 'prov'), t = el('div');
    t.append(el('strong', 'Revisión lista para «' + r.proyecto + '»'),
      el('small', s.nuevos + ' inconsistencias por revisar (el análisis queda en tu historial; guárdalo abajo con «Guardar lote») · ' + s.del_sistema + ' por reglas del sistema, ' + s.de_la_ia + ' propuestas por la IA' + (s.ya_resueltos ? ' · ' + s.ya_resueltos + ' ya resueltas o rechazadas no se repiten' : '')));
    (s.fallidos || []).forEach(a => t.append(el('small', '⚠ No se pudo revisar: ' + a)));
    const ver = el('button', 'Abrir en Project Web');
    ver.addEventListener('click', () => { seccionActual = '/inconsistencias'; mostrar('web'); pw.irWeb('/inconsistencias?proyecto=' + pid + (s.corrida ? '&corrida=' + s.corrida : '')); });
    caja.append(t, ver); res.replaceChildren(caja); res.hidden = false;
    $('#inc-msg').textContent = 'Listo. No se modificó ningún dato: abajo puedes aceptar o rechazar cada solución y guardar este lote.'; pintarHist('inconsistencias', true); pintarMenuIA();
  } catch (x) { $('#inc-msg').textContent = '✘ ' + msgErr(x); }
  revisando = false; $('#inc-barra').hidden = true; $('#inc-cancelar').hidden = true; pintarInconsistencias();
});

// ---- Análisis de materiales ----
let analizandoMat = false;
async function pintarMateriales() {
  const { sel, selP, sinConn, est: estMat } = await llenarComun('mat', 'materiales', 'rapida');
  $('#mat-ir').disabled = analizandoMat || sinConn || !selP.value || estMat === 'bloqueado';
  pintarEstadoProg(); if (sel.value && !estadoIA[sel.value]) refrescarEstado(sel.value);
}
$('#mat-proyecto').addEventListener('change', pintarMateriales);
$('#mat-conexion').addEventListener('change', () => { pintarEstadoProg(); refrescarEstado($('#mat-conexion').value); });
pw.alAvanceMateriales(p => { $('#mat-msg').textContent = p.texto; $('#mat-barra').max = p.total || 1; $('#mat-barra').value = p.hecho || 0; });
$('#mat-cancelar').addEventListener('click', () => { pw.cancelarMateriales(); $('#mat-msg').textContent = 'Cancelando al terminar el lote en curso…'; });
$('#mat-form').addEventListener('submit', async e => {
  e.preventDefault();
  const pid = $('#mat-proyecto').value, res = $('#mat-res');
  analizandoMat = true; res.hidden = true; $('#mat-barra').hidden = false; $('#mat-cancelar').hidden = false; pintarMateriales();
  try {
    const r = await pw.analizarMateriales(pid, $('#mat-conexion').value), s = r.resumen;
    const caja = el('div', null, 'prov'), t = el('div');
    t.append(el('strong', 'Análisis listo para «' + r.proyecto + '»'),
      el('small', s.nuevos + ' hallazgos por revisar (el análisis queda en tu historial; guárdalo abajo con «Guardar lote») · ' + s.del_sistema + ' por reglas del sistema, ' + s.de_la_ia + ' propuestas por la IA' + (s.ya_resueltos ? ' · ' + s.ya_resueltos + ' ya resueltas o rechazadas no se repiten' : '')));
    (s.fallidos || []).forEach(a => t.append(el('small', '⚠ No se pudo revisar: ' + a)));
    const ver = el('button', 'Abrir en Project Web');
    ver.addEventListener('click', () => { seccionActual = '/analisis-materiales'; mostrar('web'); pw.irWeb('/analisis-materiales?proyecto=' + pid + (s.corrida ? '&corrida=' + s.corrida : '')); });
    caja.append(t, ver); res.replaceChildren(caja); res.hidden = false;
    $('#mat-msg').textContent = 'Listo. No se modificó ningún dato: abajo puedes aceptar o rechazar cada propuesta y guardar este lote.'; pintarHist('materiales', true); pintarMenuIA();
  } catch (x) { $('#mat-msg').textContent = '✘ ' + msgErr(x); }
  analizandoMat = false; $('#mat-barra').hidden = true; $('#mat-cancelar').hidden = true; pintarMateriales();
});

// ---- Materiales inteligentes ----
let verificandoInt = false;
async function pintarInteligentes() {
  const { sel, selP, sinConn, est: est } = await llenarComun('int', 'inteligentes', 'rapida');
  let e = null;
  if (selP.value) {
    try { e = await pw.estadoMaterialesInteligentes(selP.value); } catch (x) { $('#int-msg').textContent = '✘ ' + msgErr(x); }
  }
  $('#int-activo').checked = !!e?.activo; $('#int-activo').disabled = !e;
  const c = e?.conteos;
  $('#int-info').textContent = !e ? '' : (e.documentos ? e.documentos + ' documento(s) de normativa y especificaciones listo(s) para consultar.' : 'Este proyecto aún no tiene normativas ni especificaciones cargadas (sección Normativa y especificaciones de Project Web): sin ellas no hay con qué verificar.')
    + ' Materiales: ' + c.aprobado + ' aprobados, ' + c.no_aprobado + ' no aprobados, ' + c.revisar + ' por revisar, ' + c.sin_normativa + ' sin normativa aplicable, ' + c.pendiente + ' pendientes de verificar.';
  $('#int-ir').disabled = verificandoInt || sinConn || !selP.value || est === 'bloqueado' || !e?.activo || !e?.documentos;
  pintarEstadoProg(); if (sel.value && !estadoIA[sel.value]) refrescarEstado(sel.value);
}
$('#int-proyecto').addEventListener('change', pintarInteligentes);
$('#int-conexion').addEventListener('change', () => { pintarEstadoProg(); refrescarEstado($('#int-conexion').value); });
$('#int-activo').addEventListener('change', async ev => {
  const caja = ev.target;
  try { await pw.activarMaterialesInteligentes($('#int-proyecto').value, caja.checked); $('#int-msg').textContent = caja.checked ? 'Activado: los materiales nuevos o modificados se verificarán al abrir Materiales o Submittals en esta app.' : 'Desactivado: los veredictos ya guardados se conservan.'; }
  catch (x) { caja.checked = !caja.checked; $('#int-msg').textContent = '✘ ' + msgErr(x); }
  pintarInteligentes();
});
pw.alAvanceVerificacion(p => { $('#int-msg').textContent = p.texto; $('#int-barra').max = p.total || 1; $('#int-barra').value = p.hecho || 0; });
$('#int-cancelar').addEventListener('click', () => { pw.cancelarVerificacion(); $('#int-msg').textContent = 'Cancelando al terminar el lote en curso…'; });
$('#int-form').addEventListener('submit', async e => {
  e.preventDefault();
  const pid = $('#int-proyecto').value, res = $('#int-res');
  verificandoInt = true; res.hidden = true; $('#int-barra').hidden = false; $('#int-cancelar').hidden = false; pintarInteligentes();
  try {
    const r = await pw.verificarMateriales(pid, $('#int-conexion').value), s = r.resumen;
    const caja = el('div', null, 'prov'), t = el('div');
    t.append(el('strong', s.verificados ? s.verificados + ' material(es) verificado(s) en «' + r.proyecto + '»' : 'No había materiales por verificar'),
      el('small', s.aprobados + ' aprobados · ' + s.no_aprobados.length + ' no aprobados · ' + s.por_revisar + ' por revisar · ' + s.sin_normativa + ' sin normativa aplicable'));
    s.no_aprobados.forEach(m => t.append(el('small', '⚠ ' + m.nombre + ': ' + m.resumen)));
    (s.fallidos || []).forEach(a => t.append(el('small', '⚠ No se pudo revisar: ' + a + ' (quedan pendientes)')));
    const ver = el('button', 'Ver materiales');
    ver.addEventListener('click', () => { seccionActual = '/materiales'; mostrar('web'); pw.irWeb('/materiales?proyecto=' + pid); });
    caja.append(t, ver); res.replaceChildren(caja); res.hidden = false;
    $('#int-msg').textContent = 'Listo. El resultado quedó guardado en cada material (Normativas y especificaciones).';
  } catch (x) { $('#int-msg').textContent = '✘ ' + msgErr(x); }
  verificandoInt = false; $('#int-barra').hidden = true; $('#int-cancelar').hidden = true; pintarInteligentes();
});

let sugerencias = null, ollama = { activo: false, instalados: [], cargados: [] }, descargando = null;
const urlOllama = () => ($('#prov-form').elements.url.value || '').trim() || tipos.ollama.url;
const usarModelo = m => {
  const f = $('#prov-form'); f.elements.modelo.value = m;
  if (!f.elements.nombre.value) f.elements.nombre.value = 'Ollama · ' + m;
  $('#prov-msg').textContent = 'Modelo «' + m + '» cargado en el formulario: revisa el nombre y pulsa Guardar para crear la conexión.';
  f.scrollIntoView();
};
const instalado = m => ollama.instalados.some(x => x.nombre === m || x.nombre === m + ':latest');

function pintarSugerencias() {
  if (!sugerencias) return;
  const s = sugerencias, caja = [];
  if (s.aviso) caja.push(el('p', s.aviso, 'msg'));
  if (s.modelos.length) caja.push(el('h2', 'Modelos sugeridos para tu equipo'));
  [...s.modelos].reverse().concat([s.embedding]).forEach(m => {
    const d = el('div', null, 'prov'), t = el('div');
    t.append(el('strong', m.modelo + (m.recomendado ? '  ★ recomendado' : '')), el('small', '~' + m.tam + ' GB · ' + m.nota));
    const acc = el('div', null, 'fila');
    if (instalado(m.modelo)) { acc.append(el('span', '✔ Descargado', 'etq')); const u = el('button', 'Usar', 'sec'); u.addEventListener('click', () => usarModelo(m.modelo)); acc.append(u); }
    else {
      const b = el('button', (m.recomendado ? 'Descargar recomendado' : 'Descargar'), m.recomendado ? '' : 'sec');
      b.disabled = !!descargando || !ollama.activo; b.title = ollama.activo ? 'Descarga el modelo con Ollama y lo guarda en este equipo' : 'Ollama no responde: revisa «Modelos en tu equipo»';
      b.addEventListener('click', () => descargarModelo(m.modelo, m.tam));
      acc.append(b);
    }
    d.append(t, acc); caja.push(d);
  });
  $('#hw-sug').replaceChildren(...caja);
}

async function cargarHardware() {
  hwCargado = true;
  try {
    const h = await pw.hardware(), s = h.sugerencias;
    const gpu = h.gpus.length ? h.gpus.map(g => g.nombre + (g.vramGB ? ' · ' + g.vramGB + ' GB VRAM' : '')).join('; ') : 'Sin GPU detectada';
    $('#hw-info').textContent = h.cpu + ' (' + h.nucleos + ' núcleos) · ' + h.ramGB + ' GB RAM · ' + gpu + '. Los modelos correrán en ' + s.modo + '.';
    sugerencias = s; pintarSugerencias();
  } catch (x) { hwCargado = false; $('#hw-info').textContent = 'No se pudo detectar el hardware: ' + msgErr(x); }
}

// ---- Modelos de Ollama: descargar, cargar en memoria, usar y eliminar ----
async function cargarModelosOllama() {
  try { ollama = await pw.ollamaEstado(urlOllama()); } catch (x) { ollama = { activo: false, binario: false, instalados: [], cargados: [], error: msgErr(x) }; }
  const ayuda = $('#oll-ayuda');
  ayuda.hidden = ollama.activo;
  if (!ollama.activo) {
    $('#oll-estado').textContent = ollama.binario ? 'Ollama está instalado pero no responde en ' + urlOllama() + '.' : 'Ollama no está instalado (o no responde en ' + urlOllama() + ').';
    const cmd = ollama.binario ? 'ollama serve' : 'curl -fsSL https://ollama.com/install.sh | sh';
    const b = el('button', 'Comprobar de nuevo', 'sec'); b.addEventListener('click', cargarModelosOllama);
    ayuda.replaceChildren(el('p', ollama.binario ? 'Inícialo en una terminal con:' : 'Instálalo en una terminal (te pedirá tu contraseña) y luego pulsa «Comprobar de nuevo»:', 'nota'), (() => { const p = el('p'); p.append(el('code', cmd)); return p; })(), b);
  } else {
    $('#oll-estado').textContent = ollama.instalados.length ? ollama.instalados.length + ' modelo(s) guardado(s) en este equipo.' : 'Ollama funciona, pero aún no hay modelos descargados. Descarga el recomendado de la lista de abajo.';
  }
  $('#oll-lista').replaceChildren(...ollama.instalados.map(m => {
    const enMemoria = ollama.cargados.includes(m.nombre), d = el('div', null, 'prov'), t = el('div');
    t.append(el('strong', m.nombre), el('small', m.tamGB + ' GB · ' + (enMemoria ? 'cargado en memoria (listo para responder rápido)' : 'guardado en disco')));
    const acc = el('div', null, 'fila'), cargar = el('button', enMemoria ? 'Descargar de memoria' : 'Cargar en memoria', 'sec'), usar = el('button', 'Usar', 'sec'), del = el('button', 'Eliminar', 'sec');
    cargar.addEventListener('click', async () => {
      cargar.disabled = true; $('#oll-msg').textContent = (enMemoria ? 'Liberando ' : 'Cargando ') + m.nombre + '… (la primera carga puede tardar)';
      try { await pw.ollamaCargar(urlOllama(), m.nombre, !enMemoria); $('#oll-msg').textContent = enMemoria ? 'Modelo liberado de la memoria.' : '✔ ' + m.nombre + ' cargado en memoria.'; } catch (x) { $('#oll-msg').textContent = '✘ ' + msgErr(x); }
      cargarModelosOllama();
    });
    usar.addEventListener('click', () => usarModelo(m.nombre));
    del.addEventListener('click', async () => {
      if (!await window.confirmarDesktop({ titulo: 'Eliminar modelo', mensaje: '¿Eliminar «' + m.nombre + '» de este equipo? Tendrías que descargarlo de nuevo.', aceptar: 'Eliminar', peligro: true })) return;
      try { await pw.ollamaEliminar(urlOllama(), m.nombre); $('#oll-msg').textContent = 'Modelo eliminado.'; } catch (x) { $('#oll-msg').textContent = '✘ ' + msgErr(x); }
      cargarModelosOllama();
    });
    acc.append(cargar, usar, del); d.append(t, acc); return d;
  }));
  pintarSugerencias();
  if (principal()?.tipo === 'ollama') refrescarEstado(principal().id, true);
}

async function descargarModelo(modelo, tamGB) {
  if (descargando) return;
  if (!await window.confirmarDesktop({ titulo: 'Descargar modelo', mensaje: 'Se descargará «' + modelo + '» (~' + tamGB + ' GB) y se guardará en este equipo. ¿Continuar?', aceptar: 'Descargar' })) return;
  descargando = modelo; pintarSugerencias();
  const barra = $('#oll-barra'), msg = $('#oll-msg');
  barra.hidden = false; barra.value = 0; msg.textContent = 'Iniciando la descarga de ' + modelo + '…';
  const cancelar = el('button', 'Cancelar descarga', 'sec'); cancelar.addEventListener('click', () => pw.ollamaCancelar());
  msg.after(cancelar);
  try {
    await pw.ollamaDescargar(urlOllama(), modelo);
    msg.textContent = '✔ «' + modelo + '» descargado y guardado en este equipo.';
    usarModelo(modelo);
  } catch (x) { msg.textContent = '✘ ' + msgErr(x); }
  cancelar.remove(); barra.hidden = true; descargando = null;
  cargarModelosOllama();
}
pw.alProgresoOllama(p => {
  if (!descargando) return;
  $('#oll-barra').value = p.pct ?? 0;
  $('#oll-msg').textContent = 'Descargando ' + p.modelo + ': ' + p.estado + (p.pct != null ? ' · ' + p.pct + ' %' : '…');
});
$('#prov-form').elements.url.addEventListener('change', () => { if (categoria === 'local') cargarModelosOllama(); });

$('#prov-probar').addEventListener('click', async () => {
  const f = new FormData($('#prov-form')), m = $('#prov-msg'); m.textContent = 'Probando…';
  try { m.textContent = '✔ ' + await pw.probar({ tipo: f.get('tipo'), url: f.get('url'), modelo: f.get('modelo') }, f.get('clave')); }
  catch (x) { m.textContent = '✘ ' + msgErr(x); }
});

$('#prov-form').addEventListener('submit', async e => {
  e.preventDefault();
  const f = new FormData(e.target), m = $('#prov-msg');
  try {
    await pw.guardarProveedor({ nombre: f.get('nombre'), tipo: f.get('tipo'), url: f.get('url'), modelo: f.get('modelo') }, f.get('clave'));
    e.target.reset(); urlEstandar = ''; ajustarForm(); m.textContent = 'Conexión guardada.'; recargar();
  } catch (x) { m.textContent = msgErr(x); }
});
$('#prov-modelos').addEventListener('click', async () => {
  const m = $('#prov-msg'), f = new FormData($('#prov-form'));
  m.textContent = 'Consultando…';
  try {
    const lista = await pw.modelos({ tipo: f.get('tipo'), url: f.get('url') }, f.get('clave'));
    $('#lista-modelos').replaceChildren(...lista.map(n => { const o = el('option'); o.value = n; return o; }));
    m.textContent = lista.length ? lista.length + ' modelos disponibles (elige en el campo Modelo).' : 'Sin modelos listados para este tipo.';
  } catch (x) { m.textContent = msgErr(x); }
});

$('#srv-form').addEventListener('submit', async e => {
  e.preventDefault();
  try { await pw.guardarServidor($('#srv-url').value); $('#upd-res').textContent = 'Guardado.'; recargar(); }
  catch (x) { $('#upd-res').textContent = msgErr(x); }
});
pw.alProgreso(p => { $('#upd-prog').hidden = false; $('#upd-prog').value = p; });
function pintarActualizacion(r) {
  const res = $('#upd-res');
  if (!r.hayNueva) { res.replaceChildren(el('p', 'Estás al día (versión ' + r.actual + ').')); return; }
  const caja = el('div'); caja.append(el('p', 'Nueva versión ' + r.ultima + ' disponible (tienes la ' + r.actual + ').'));
  r.archivos.filter(a => /\.(deb|AppImage)$/.test(a.nombre)).forEach(a => {
    const b = el('button', 'Descargar e instalar ' + a.nombre); b.style.marginRight = '.6rem';
    b.addEventListener('click', async () => {
      b.disabled = true;
      try { res.append(el('p', await pw.actualizar(a))); } catch (x) { res.append(el('p', msgErr(x), 'msg')); b.disabled = false; }
    });
    caja.append(b);
  });
  res.replaceChildren(caja);
}
$('#upd-buscar').addEventListener('click', async () => {
  $('#upd-res').replaceChildren(el('p', 'Consultando…'));
  try { const r = await pw.buscarActualizacion(); pintarActualizacion(r); pintarCampana(r); }
  catch (x) { $('#upd-res').replaceChildren(el('p', msgErr(x))); }
});
// Campana de notificaciones: busca sola al abrir la app y cada 6 h (en silencio; sin sesión de administrador no hace nada) y avisa con un punto si hay versión nueva
function pintarCampana(r) {
  const c = $('#campana'); c.classList.toggle('hay', !!r?.hayNueva);
  c.title = r?.hayNueva ? 'Nueva versión ' + r.ultima + ' disponible: clic para descargarla' : 'Notificaciones: la app está al día';
  c.dataset.r = r?.hayNueva ? '1' : '';
}
let ultimaActualizacion = null;
async function vigilarActualizaciones() {
  try { ultimaActualizacion = await pw.buscarActualizacion(); pintarCampana(ultimaActualizacion); } catch { /* sin servidor, sin sesión de administrador o sin red */ }
}
$('#campana').addEventListener('click', () => {
  abrirAjustes('servidor');
  if (ultimaActualizacion?.hayNueva) pintarActualizacion(ultimaActualizacion); else $('#upd-buscar').click();
});
setTimeout(vigilarActualizaciones, 5000); setInterval(vigilarActualizaciones, 6 * 3600000);

// ---- Ajustes: almacenamiento, versión instalada y desinstalación ----
const FORMATOS = { appimage: 'AppImage', deb: 'paquete .deb', desarrollo: 'modo desarrollo (código fuente)' };
async function pintarAlmacenamiento() {
  const sel = $('#alm-proyecto'), res = $('#alm-res');
  if (!proyectosProg) { try { proyectosProg = await pw.proyectosPrograma(); } catch { proyectosProg = null; } }
  const previo = sel.value || proyectoActual;
  sel.replaceChildren(...(proyectosProg || []).map(p => { const o = el('option', p.codigo + ' · ' + p.nombre); o.value = p.id; return o; }));
  if (previo && (proyectosProg || []).some(p => String(p.id) === String(previo))) sel.value = previo;
  const partes = [], guardado = el('div');
  if (sel.value) { partes.push(guardado); Centro.guardado(guardado, sel.value); }
  else partes.push(el('p', 'Inicia sesión como administrador para ver el espacio de un proyecto.', 'nota'));
  try { const i = await pw.infoApp(); partes.push(el('p', 'Datos de esta app en tu equipo: ' + Centro.bytes(i.datosBytes) + ' (' + i.datos + ')', 'nota')); } catch { /* sin datos */ }
  res.replaceChildren(...partes);
}
$('#alm-proyecto').addEventListener('change', pintarAlmacenamiento); $('#alm-act').addEventListener('click', pintarAlmacenamiento);
async function pintarAjustesApp() {
  pintarAlmacenamiento();
  try {
    const i = await pw.infoApp();
    $('#upd-ver').textContent = 'Versión instalada: ' + i.version + ' · ' + FORMATOS[i.formato];
    $('#des-nota').textContent = i.formato === 'desarrollo' ? 'Esta app se ejecuta desde el código fuente, así que no hay instalación que quitar.' : i.formato === 'appimage' ? 'Borra el archivo AppImage (' + i.ejecutable + ') y cierra la app.' : 'Quita el paquete projectweb-desktop del sistema (te pedirá tu contraseña) y cierra la app.';
    $('#des-ir').disabled = i.formato === 'desarrollo';
  } catch { /* sin información */ }
}
$('#des-ir').addEventListener('click', async () => {
  $('#des-ir').disabled = true; $('#des-msg').textContent = '';
  try { const r = await pw.desinstalar($('#des-datos').checked); $('#des-msg').textContent = r.cancelado ? 'Cancelado.' : r.mensaje; } catch (x) { $('#des-msg').textContent = '✘ ' + msgErr(x); }
  $('#des-ir').disabled = false;
});

(async () => {
  $('#ver').textContent = 'v' + await pw.version();
  tipos = await pw.tipos();
  elegirCategoria('local'); await recargar(); pintarZoom(cfg.zoom);
  $('#hueco-msg').textContent = cfg.servidor ? 'Cargando Project Web…' : 'Configura el servidor de Project Web en «Servidor y actualizaciones».';
  if (cfg.servidor) abrirSeccion('/dashboard');
})();
