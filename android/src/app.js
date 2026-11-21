'use strict';

// App Android · Fase 1.
// Pantallas, conexión con /api/app, caché local (SQLite en Tauri; localStorage en el navegador),
// respuestas locales con datos reales y ajustes de cuenta. El modelo llama.cpp se conecta en la
// siguiente fase; mientras tanto el chat usa reglas deterministas sobre los datos sincronizados.

const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
const DEFAULT_SERVER = 'https://projectweb.iguzman.com.mx';
const APP_VERSION = '0.2.9';
const APP_VERSION_CODE = 2009;

const state = {
  tema: localStorage.getItem('pw-theme') || 'dark',
  tab: 'inicio',
  servidor: localStorage.getItem('pw-servidor') || DEFAULT_SERVER,
  token: null,
  usuario: null,
  proyectos: [],
  sync: [],
  proyectoId: null,
  conversacionId: 0,
  cambios: [],
  actualizado: null,
  sesiones: [],
  outbox: [],
  enviando: false,
  servidorInfo: null,
  conectado: false,
  cargando: false,
  modal: null,
  chatTurnos: [],
  generando: false,
  detenido: false,
  ultimaPregunta: '',
  parteFotos: [],
  evidenciaFotos: [],
  stream: null,
  motor: {
    disponible: false,
    presente: false,
    cargado: false,
    descargando: false,
    generando: false,
    pct: 0,
    bytes: 0,
    total: 0,
    nombre: 'Qwen2.5',
    archivo: '',
    ramMb: 0,
    hilos: 0,
    bytesDisco: 0,
    ruta: '',
    error: '',
  },
};

const tieneTauri = () => !!(window.__TAURI__ && window.__TAURI__.core && typeof window.__TAURI__.core.invoke === 'function');

async function cacheGuardar(clave, valor) {
  if (tieneTauri()) {
    try { await window.__TAURI__.core.invoke('cache_guardar', { clave, valor: String(valor) }); return; } catch (e) { /* fallback */ }
  }
  try { localStorage.setItem('pw-' + clave, String(valor)); } catch (e) {}
}

async function cacheLeer(clave) {
  if (tieneTauri()) {
    try { return await window.__TAURI__.core.invoke('cache_leer', { clave }); } catch (e) { /* fallback */ }
  }
  try { return localStorage.getItem('pw-' + clave); } catch (e) { return null; }
}

async function cacheBorrar(clave) {
  if (tieneTauri()) {
    try { await window.__TAURI__.core.invoke('cache_borrar', { clave }); } catch (e) { /* fallback */ }
  }
  try { localStorage.removeItem('pw-' + clave); } catch (e) {}
}

function invokeTauri(cmd, args) {
  return window.__TAURI__.core.invoke(cmd, args);
}

function formatoMB(bytes) {
  const mb = Number(bytes || 0) / 1048576;
  if (!mb) return '0 MB';
  return mb >= 1024 ? (mb / 1024).toFixed(1).replace('.', ',') + ' GB' : Math.round(mb) + ' MB';
}

async function guardarTokenSeguro(token) {
  if (tieneTauri()) {
    try {
      await invokeTauri('plugin:secure-keystore|set_item', { key: 'token', value: String(token) });
      await cacheBorrar('token');
      return;
    } catch (e) { /* usa el caché local si el Keystore no está disponible */ }
  }
  await cacheGuardar('token', token);
}

async function leerTokenSeguro() {
  if (tieneTauri()) {
    try {
      const r = await invokeTauri('plugin:secure-keystore|get_item', { key: 'token' });
      if (r && r.value) {
        await cacheBorrar('token');   // migración: borra la copia en claro anterior
        return String(r.value);
      }
    } catch (e) { /* usa el caché local */ }
  }
  return cacheLeer('token');
}

async function borrarTokenSeguro() {
  if (tieneTauri()) {
    try { await invokeTauri('plugin:secure-keystore|delete_item', { key: 'token' }); } catch (e) {}
  }
  await cacheBorrar('token');
}

function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function normalizarTexto(v) {
  return String(v || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function normalizarServidor(valor) {
  let s = String(valor || '').trim();
  if (s === '') s = DEFAULT_SERVER;
  if (!/^https?:\/\//i.test(s)) s = 'https://' + s;
  return s.replace(/\/+$/, '');
}

function apiUrl(ruta) {
  return normalizarServidor(state.servidor) + ruta;
}

async function apiFetch(ruta, opciones = {}) {
  const ctrl = new AbortController();
  const { tiempoLimite, ...resto } = opciones;
  const tope = setTimeout(() => ctrl.abort(), tiempoLimite || 25000);
  try {
    return await fetch(apiUrl(ruta), {
      ...resto,
      signal: ctrl.signal,
      headers: {
        Accept: 'application/json',
        ...(state.token ? { Authorization: 'Bearer ' + state.token, 'X-App-Token': state.token } : {}),
        ...(opciones.headers || {}),
      },
    });
  } finally {
    clearTimeout(tope);
  }
}

function hoyISO() {
  const d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function fechaCorta(f) {
  if (!f) return '—';
  const hoy = hoyISO();
  if (f === hoy) return 'Hoy';
  const ayer = new Date(); ayer.setDate(ayer.getDate() - 1);
  if (f === ayer.toISOString().slice(0, 10)) return 'Ayer';
  const d = new Date(f + 'T00:00:00');
  if (Number.isNaN(d.getTime())) return f;
  return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function fechaLarga(f) {
  if (!f) return '';
  const d = new Date(f.length <= 10 ? f + 'T00:00:00' : f);
  if (Number.isNaN(d.getTime())) return f;
  return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ---------- Tema y navegación ----------

function aplicarTema(tema) {
  state.tema = tema === 'light' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', state.tema);
  try { localStorage.setItem('pw-theme', state.tema); } catch (e) {}
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute('content', state.tema === 'light' ? '#f3f6fb' : '#0f172a');
  const ico = $('#tema-ico use');
  if (ico) ico.setAttribute('href', state.tema === 'light' ? '#i-sun' : '#i-moon');
}

function irA(tab) {
  if (!tab) return;
  state.tab = tab;
  $$('.pantalla').forEach((p) => p.classList.toggle('activa', p.dataset.vista === tab));
  $$('.tab').forEach((b) => {
    const on = b.dataset.tab === tab;
    b.classList.toggle('activo', on);
    if (on) b.setAttribute('aria-current', 'page');
    else b.removeAttribute('aria-current');
  });
  const vista = $('#vista');
  if (vista) {
    vista.classList.toggle('vista-chat', tab === 'asistente');
    vista.scrollTo({ top: 0, behavior: tab === 'parte' ? 'auto' : 'smooth' });
  }
}

function toast(texto, ms = 3400) {
  const el = $('#toast');
  if (!el) return;
  el.textContent = texto;
  el.classList.add('visible');
  clearTimeout(toast._t);
  toast._t = setTimeout(() => el.classList.remove('visible'), ms);
}

function estadoRed() {
  const el = $('#estado-conexion');
  if (!el) return;
  const online = navigator.onLine;
  let txt = online ? 'En línea' : 'Sin conexión';
  if (online && state.conectado && state.actualizado) txt = 'En línea · ' + fechaCorta(state.actualizado.slice(0, 10));
  el.innerHTML = `<i class="punto ${online ? (state.conectado ? 'activo' : 'offline') : 'offline'}"></i> ${esc(txt)}`;
  const perfil = $('#perfil-estado');
  if (perfil) {
    perfil.textContent = !navigator.onLine ? 'Sin conexión' : (state.conectado ? 'Conectado' : 'Vista previa');
    perfil.className = 'chip ' + (state.conectado && navigator.onLine ? 'chip-ok' : 'chip-warn');
  }
  renderChatEstado();
}

// ---------- Sesión y sincronización ----------

function elegirProyectoInicial() {
  const activos = state.sync.filter((p) => !Number(p?.proyecto?.archivado));
  const puntaje = (p) => (p.recordatorios || []).length
    + (p.punch || []).filter((x) => ['abierto', 'en_proceso'].includes(x.estado)).length * 3
    + (p.rfis || []).filter((x) => x.estado === 'abierto').length * 2
    + Math.min(10, (p.actividades || []).length);
  const mejor = activos.slice().sort((a, b) => puntaje(b) - puntaje(a))[0] || state.sync[0];
  return Number(mejor?.proyecto?.id || 0);
}

function proyectoActual() {
  if (!state.sync.length) return null;
  return state.sync.find((p) => Number(p?.proyecto?.id) === Number(state.proyectoId)) || state.sync[0];
}

async function guardarSesion(datos, servidor) {
  state.token = datos.token;
  state.servidor = normalizarServidor(servidor || state.servidor);
  state.usuario = datos.usuario || null;
  state.proyectos = datos.proyectos || [];
  state.servidorInfo = datos.servidor || null;
  state.conectado = true;
  await guardarTokenSeguro(state.token);
  await cacheGuardar('servidor', state.servidor);
  await cacheGuardar('usuario', JSON.stringify(state.usuario || {}));
  await cacheGuardar('proyectos', JSON.stringify(state.proyectos));
  actualizarBanner();
}

async function limpiarSesion() {
  const proyectos = state.sync;
  state.token = null;
  state.usuario = null;
  state.proyectos = [];
  state.sync = [];
  state.sesiones = [];
  state.proyectoId = null;
  state.actualizado = null;
  state.conectado = false;
  state.chatTurnos = [];
  state.stream = null;
  state.generando = false;
  for (const p of proyectos) await cacheBorrar('chat_' + Number(p?.proyecto?.id || 0));
  await borrarTokenSeguro();
  for (const k of ['usuario', 'proyectos', 'sync', 'outbox', 'alertas_vistas']) await cacheBorrar(k);
  state.outbox = [];
  actualizarPendientes();
}

async function cargarSesion() {
  const token = await leerTokenSeguro();
  const servidor = await cacheLeer('servidor');
  if (servidor) state.servidor = servidor;
  const usuario = await cacheLeer('usuario');
  if (usuario) { try { state.usuario = JSON.parse(usuario); } catch (e) {} }
  const outbox = await cacheLeer('outbox');
  if (outbox) { try { state.outbox = JSON.parse(outbox) || []; } catch (e) { state.outbox = []; } }
  actualizarPendientes();
  const sync = await cacheLeer('sync');
  const proyectoGuardado = Number(await cacheLeer('proyecto') || 0);
  if (sync) {
    try {
      const j = JSON.parse(sync);
      state.sync = j.proyectos || [];
      state.actualizado = j.generado || null;
      state.proyectos = state.sync.map((p) => p.proyecto);
      state.proyectoId = state.sync.some((p) => Number(p.proyecto.id) === proyectoGuardado)
        ? proyectoGuardado : elegirProyectoInicial();
    } catch (e) {}
  }
  if (token) {
    state.token = token;
    state.conectado = true;
    await guardarTokenSeguro(token);   // migra el token antiguo del SQLite al Keystore
    await sincronizar(true);
  } else {
    renderTodo();
    setTimeout(() => abrirConexion(), 700);
  }
}

async function sincronizar(silencioso = false) {
  if (!state.token) {
    if (!silencioso) abrirConexion();
    return false;
  }
  state.cargando = true;
  try {
    if (navigator.onLine && state.outbox.length) await enviarPendientes(true);
    const [syncResp, sesionesResp] = await Promise.all([
      apiFetch('/api/app/sync'),
      apiFetch('/api/app/sesiones').catch(() => null),
    ]);
    if (syncResp.status === 401) {
      await limpiarSesion();
      renderTodo();
      if (!silencioso) { toast('La sesión se cerró. Vuelve a conectar tu cuenta.'); abrirConexion(); }
      return false;
    }
    const j = await syncResp.json();
    if (!syncResp.ok || !j.success) throw new Error(j.error || 'No se pudo sincronizar');
    const anterior = state.sync;
    state.sync = j.proyectos || [];
    state.cambios = calcularCambios(anterior, state.sync);
    state.actualizado = j.generado || new Date().toISOString();
    const proyectoGuardado = Number(await cacheLeer('proyecto') || 0);
    if (!state.proyectoId || !state.sync.some((p) => Number(p.proyecto.id) === Number(state.proyectoId))) {
      state.proyectoId = state.sync.some((p) => Number(p.proyecto.id) === proyectoGuardado)
        ? proyectoGuardado : elegirProyectoInicial();
    }
    if (state.proyectoId) await cacheGuardar('proyecto', String(state.proyectoId));
    if (sesionesResp && sesionesResp.ok) {
      const sj = await sesionesResp.json().catch(() => null);
      if (sj && sj.success) state.sesiones = sj.sesiones || [];
    }
    await cacheGuardar('sync', JSON.stringify({ generado: state.actualizado, proyectos: state.sync }));
    await cacheGuardar('proyectos', JSON.stringify(state.sync.map((p) => p.proyecto)));
    state.conectado = true;
    renderTodo();
    await cargarChat();
    estadoRed();
    avisarNuevosCriticos();
    if (!silencioso) toast(state.sync.length ? 'Obras actualizadas.' : 'No hay obras para mostrar.');
    return true;
  } catch (e) {
    if (!silencioso) toast('Sin conexión: se muestran los últimos datos descargados.');
    renderTodo();
    estadoRed();
    return false;
  } finally {
    state.cargando = false;
  }
}

// ---------- Render ----------

function renderTodo() {
  if (state.sync.length) {
    renderInicio();
    renderObras();
    renderAlertas();
    renderPerfil();
    const p = proyectoActual();
    if (p && state.tab === 'punch') renderPunch(p);
    if (p && state.tab === 'rfi') renderRfi(p);
    if (p && state.tab === 'archivo') renderArchivoObra(p);
  }
  renderChatEstado();
  estadoRed();
}

function nivelChip(nivel) {
  return nivel === 'critico' ? 'chip-bad' : (nivel === 'aviso' ? 'chip-warn' : 'chip-ok');
}

function puntoClase(nivel) {
  return nivel === 'critico' ? 'kpi-bad' : (nivel === 'aviso' ? 'kpi-warn' : 'kpi-ok');
}

function renderInicio() {
  const p = proyectoActual();
  if (!p) return;
  const proj = p.proyecto;
  const fecha = $('#inicio-fecha');
  if (fecha) fecha.textContent = new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
  const saludo = $('#inicio-saludo');
  if (saludo) saludo.textContent = 'Buenos días, ' + String(state.usuario?.nombre || 'Edgar').split(' ')[0] + '.';
  const subp = $('#inicio-subtitulo-proyecto');
  if (subp) subp.textContent = proj.nombre;
  const progreso = Math.max(0, Math.min(100, Math.round(Number(proj.progreso) || 0)));
  const nombre = $('#inicio-nombre');
  if (nombre) nombre.textContent = proj.nombre;
  const meta = $('#inicio-meta');
  if (meta) meta.textContent = `${proj.codigo || ''} · ${fechaLarga(proj.fecha_inicio)} — ${fechaLarga(proj.fecha_fin)}`;
  const aro = $('#inicio-aro');
  if (aro) { aro.style.setProperty('--p', progreso); aro.setAttribute('aria-label', `Avance ${progreso} por ciento`); }
  const avance = $('#inicio-avance');
  if (avance) avance.innerHTML = `${progreso}<small>%</small>`;
  const barra = $('#inicio-barra');
  if (barra) barra.style.width = `${progreso}%`;

  const hoy = hoyISO();
  const hitos = (p.recordatorios || []).filter((x) => x.tipo === 'hito' && x.fecha >= hoy).sort((a, b) => a.fecha.localeCompare(b.fecha));
  const proxima = hitos[0] || (p.actividades || []).filter((a) => !a.hito && a.progreso < 100 && a.fecha_inicio >= hoy).sort((a, b) => a.fecha_inicio.localeCompare(b.fecha_inicio))[0];
  const hito = $('#inicio-hito');
  if (hito) {
    const texto = proxima ? (proxima.titulo || proxima.nombre) + ' · ' + fechaCorta(proxima.fecha || proxima.fecha_inicio) : 'Sin hitos próximos';
    hito.innerHTML = '<svg class="ico"><use href="#i-flag"/></svg> ' + esc(texto);
  }

  const abiertos = (p.punch || []).filter((x) => ['abierto', 'en_proceso'].includes(x.estado));
  const criticos = abiertos.filter((x) => ['alta', 'critica'].includes(x.severidad));
  const rfis = (p.rfis || []).filter((x) => x.estado === 'abierto');
  const atras = (p.actividades || []).filter((a) => !Number(a.hito) && Number(a.progreso) < 100 && a.fecha_fin && a.fecha_fin < hoy);
  const set = (id, v) => { const el = $(id); if (el) el.textContent = String(v); };
  set('#kpi-actividades', (p.actividades || []).length);
  set('#kpi-punch', criticos.length);
  set('#kpi-rfi', rfis.length);
  set('#kpi-atrasos', atras.length);

  const lista = $('#lista-hoy');
  if (lista) {
    const recs = (p.recordatorios || []).slice(0, 3);
    if (!recs.length) {
      lista.innerHTML = '<div class="aviso"><svg class="ico"><use href="#i-check"/></svg><p>No hay recordatorios próximos para esta obra.</p></div>';
    } else {
      lista.innerHTML = recs.map((r) => `
        <div class="item-hoy">
          <span class="item-hora">${esc(fechaCorta(r.fecha))}</span>
          <span class="item-punto ${puntoClase(r.nivel)}"></span>
          <div><strong>${esc(r.titulo)}</strong><small>${esc(r.detalle || '')}</small></div>
          <svg class="ico"><use href="#i-arrow"/></svg>
        </div>`).join('');
    }
  }
}

function estadoProyecto(p) {
  const salud = p?.salud?.nivel || 'ok';
  if (Number(p?.proyecto?.progreso) >= 100) return { texto: 'Completada', clase: 'chip-ok' };
  if (salud === 'critico') return { texto: 'En riesgo', clase: 'chip-warn' };
  return { texto: 'En proceso', clase: 'chip-ok' };
}

const HERRAMIENTAS_OBRA = [
  ['PR', 'Programa', 'Actividades y avance', '/programa'],
  ['LT', 'Línea de tiempo', 'Gantt diario', '/lineatiempo'],
  ['LK', 'Lookahead', 'Próximas semanas', '/lookahead'],
  ['PD', 'Parte diario', 'Campo y evidencias', '/reportes'],
  ['BT', 'Bitácora', 'Notas de obra', '/bitacora'],
  ['PU', 'Punch', 'Pendientes y cierre', '/punch'],
  ['RF', 'RFI', 'Consultas y respuestas', '/rfi'],
  ['AR', 'Archivo', 'Planos y documentos', '/archivos'],
  ['EV', 'Evidencias', 'Fotos por actividad', '/evidencias'],
  ['CO', 'Costos', 'Presupuesto y estimaciones', '/costos'],
  ['MT', 'Materiales', 'Compras y almacén', '/materiales'],
  ['SG', 'Seguridad', 'Incidentes y ATS', '/seguridad'],
  ['CA', 'Calidad', 'Inspecciones y NC', '/calidad'],
];

async function abrirWeb(ruta, proyecto = 0) {
  if (!navigator.onLine) { toast('Necesitas conexión para abrir esta herramienta.'); return; }
  // La mayoría de herramientas necesitan la obra en la URL; si no se pasa, la web pide elegirla.
  const pid = Number(proyecto) || Number(state.proyectoId) || 0;
  if (pid && !/[?&]proyecto=/.test(ruta) && !['/dashboard', '/proyectos', '/cuenta', '/manual', '/novedades'].includes(ruta.split('?')[0])) {
    ruta += (ruta.includes('?') ? '&' : '?') + 'proyecto=' + pid;
  }
  let url = apiUrl(ruta);
  // Puente de sesión: la web abre ya autenticada con un código de un solo uso.
  if (state.token) {
    try {
      const r = await apiFetch('/api/app/enlace', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ruta }),
        tiempoLimite: 12000,
      });
      if (r.ok) {
        const j = await r.json();
        if (j?.url) url = j.url;
      }
    } catch (e) { /* sin puente: abre la URL normal y pedirá sesión */ }
  }
  try {
    if (tieneTauri() && window.__TAURI__?.opener?.openUrl) {
      await window.__TAURI__.opener.openUrl(url);
      return;
    }
    window.open(url, '_blank', 'noopener');
  } catch (e) {
    toast('No se pudo abrir la herramienta.');
  }
}

function abrirObra(id) {
  const p = state.sync.find((x) => Number(x.proyecto.id) === Number(id));
  if (!p) return;
  const proj = p.proyecto;
  const e = estadoProyecto(p);
  const progreso = Math.max(0, Math.min(100, Math.round(Number(proj.progreso) || 0)));
  const herramientas = HERRAMIENTAS_OBRA.map(([ini, nombre, detalle, ruta]) => `
    <button class="hub-tool" type="button" data-web="${ruta}" data-proyecto="${Number(proj.id)}">
      <span class="hub-ini">${ini}</span>
      <span><strong>${esc(nombre)}</strong><small>${esc(detalle)}</small></span>
    </button>`).join('');
  abrirModal('Obra', `
    <div class="hub-cab">
      <div>
        <span class="chip ${e.clase}">${e.texto}</span>
        <h3>${esc(proj.nombre)}</h3>
        <p>${esc(proj.codigo || '')} · ${esc(fechaLarga(proj.fecha_inicio))} — ${esc(fechaLarga(proj.fecha_fin))}</p>
      </div>
      <div class="aro" style="--p:${progreso}" aria-label="Avance ${progreso} por ciento"><span>${progreso}<small>%</small></span></div>
    </div>
    <div class="barra"><i style="width:${progreso}%"></i></div>
    <div class="hub-acciones">
      <button class="btn-principal" id="hub-entrar" type="button">Entrar a la obra</button>
      <button class="btn-mini" type="button" data-web="/reportes">Parte diario</button>
    </div>
    <p class="hub-nota">Las herramientas se abren en la web de Project Web. Necesitas conexión; el avance, las notas y el asistente funcionan sin señal.</p>
    <div class="hub-grid">${herramientas}</div>
    <div class="conexion-acciones"><button class="btn-mini" type="button" data-web="/dashboard">Ver todas las herramientas en la web</button></div>`);
  state.modal = 'obra';
  $('#hub-entrar')?.addEventListener('click', () => {
    state.proyectoId = Number(proj.id);
    cacheGuardar('proyecto', String(state.proyectoId));
    cerrarModal();
    renderTodo();
    cargarChat();
    irA('inicio');
  });
  $$('#modal-cuerpo [data-web]').forEach((b) => b.addEventListener('click', () => {
    const ruta = b.dataset.web;
    if (ruta === '/reportes') { abrirParteDiario(Number(proj.id)); return; }
    if (ruta === '/evidencias') { abrirEvidencias(Number(proj.id)); return; }
    if (ruta === '/archivos') { abrirArchivoObra(Number(proj.id)); return; }
    if (ruta === '/punch') { abrirPunch(Number(proj.id)); return; }
    if (ruta === '/rfi') { abrirRfi(Number(proj.id)); return; }
    abrirWeb(ruta + (ruta.includes('?') ? '&' : '?') + 'proyecto=' + Number(proj.id));
  }));
}

function leerArchivoDataUrl(archivo) {
  return new Promise((resolve, reject) => {
    const lector = new FileReader();
    lector.onload = () => {
      const original = String(lector.result || '');
      const img = new Image();
      img.onload = () => {
        try {
          const max = 1600;
          const escala = Math.min(1, max / Math.max(img.width, img.height));
          const lienzo = document.createElement('canvas');
          lienzo.width = Math.max(1, Math.round(img.width * escala));
          lienzo.height = Math.max(1, Math.round(img.height * escala));
          const ctx = lienzo.getContext('2d');
          ctx.drawImage(img, 0, 0, lienzo.width, lienzo.height);
          resolve(lienzo.toDataURL('image/jpeg', 0.82));
        } catch (e) { resolve(original); }
      };
      img.onerror = () => resolve(original);
      img.src = original;
    };
    lector.onerror = () => reject(new Error('No se pudo leer la foto.'));
    lector.readAsDataURL(archivo);
  });
}

function renderFotosParte() {
  const cont = $('#parte-fotos-prev');
  if (!cont) return;
  cont.innerHTML = state.parteFotos.length ? state.parteFotos.map((f, i) => `
    <div class="parte-foto">
      <img src="${f.data}" alt="${esc(f.nombre || 'Foto del parte')}">
      <button type="button" data-quitar-foto="${i}" aria-label="Quitar foto">×</button>
    </div>`).join('') : '';
  $$('[data-quitar-foto]', cont).forEach((b) => b.addEventListener('click', () => {
    state.parteFotos.splice(Number(b.dataset.quitarFoto), 1);
    renderFotosParte();
  }));
}

function limpiarFirma(canvas) {
  const ctx = canvas.getContext('2d');
  ctx.fillStyle = '#f8fafc';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.lineWidth = 6;
  ctx.lineCap = 'round';
  ctx.strokeStyle = '#0f172a';
  canvas.dataset.tocada = '0';
}

function prepararFirma(canvas) {
  limpiarFirma(canvas);
  let dibujando = false;
  let ultimo = null;
  const punto = (e) => {
    const r = canvas.getBoundingClientRect();
    return {
      x: (e.clientX - r.left) * (canvas.width / r.width),
      y: (e.clientY - r.top) * (canvas.height / r.height),
    };
  };
  const empezar = (e) => {
    e.preventDefault();
    dibujando = true;
    ultimo = punto(e);
    canvas.setPointerCapture?.(e.pointerId);
  };
  const mover = (e) => {
    if (!dibujando) return;
    e.preventDefault();
    const p = punto(e);
    const ctx = canvas.getContext('2d');
    ctx.beginPath();
    ctx.moveTo(ultimo.x, ultimo.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    ultimo = p;
    canvas.dataset.tocada = '1';
  };
  const terminar = (e) => {
    dibujando = false;
    ultimo = null;
    try { canvas.releasePointerCapture?.(e.pointerId); } catch (err) {}
  };
  canvas.addEventListener('pointerdown', empezar);
  canvas.addEventListener('pointermove', mover);
  canvas.addEventListener('pointerup', terminar);
  canvas.addEventListener('pointercancel', terminar);
  canvas.addEventListener('pointerleave', terminar);
}

function archivoTamano(bytes) {
  const n = Number(bytes) || 0;
  return n >= 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB';
}

function sumarDiasISO(dias) {
  const d = new Date();
  d.setDate(d.getDate() + Number(dias || 0));
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function opcionesActividades(p, seleccion = 0) {
  const acts = (p.actividades || []).filter((a) => !Number(a.hito)).sort((a, b) => String(a.fecha_inicio || '').localeCompare(String(b.fecha_inicio || '')));
  return '<option value="0">Sin actividad</option>' + acts.map((a) => `<option value="${Number(a.id)}"${Number(a.id) === Number(seleccion) ? ' selected' : ''}>${esc(a.codigo)} · ${esc(a.nombre)}</option>`).join('');
}

const PUNCH_ESTADOS = { abierto: 'Abierto', en_proceso: 'En proceso', corregido: 'Corregido', cerrado: 'Cerrado', rechazado: 'Rechazado' };
const PUNCH_SEVERIDADES = { baja: 'Baja', media: 'Media', alta: 'Alta', critica: 'Crítica' };
const RFI_PRIORIDADES = { baja: 'Baja', media: 'Media', alta: 'Alta', critica: 'Crítica' };
const RFI_ESTADOS = { abierto: 'Abierto', respondido: 'Respondido', cerrado: 'Cerrado' };

function abrirPunch(id) {
  const p = state.sync.find((x) => Number(x.proyecto.id) === Number(id));
  if (!p) { toast('No se encontró la obra.'); return; }
  state.punchVolver = state.tab === 'punch' ? 'obras' : state.tab;
  state.proyectoId = Number(p.proyecto.id);
  cacheGuardar('proyecto', String(state.proyectoId));
  cerrarModal();
  $('#punch-obra').textContent = `${p.proyecto.nombre} · ${p.proyecto.codigo || ''}`;
  const input = $('#punch-buscar'); if (input) input.value = '';
  renderPunch(p);
  irA('punch');
  setTimeout(() => { const v = $('#vista'); if (v) v.scrollTo({ top: 0, behavior: 'auto' }); }, 220);
}

function renderPunch(p) {
  const cont = $('#punch-lista');
  if (!cont) return;
  const items = Array.isArray(p.punch) ? p.punch : [];
  const q = normalizarTexto($('#punch-buscar')?.value || '').trim();
  const lista = q ? items.filter((x) => normalizarTexto(`${x.titulo} ${x.ubicacion || ''} ${x.categoria || ''} ${x.responsable || ''}`).includes(q)) : items;
  $('#punch-nuevo').hidden = !p.permisos?.editar;
  if (!lista.length) {
    cont.innerHTML = `<div class="aviso"><svg class="ico"><use href="#i-check"/></svg><p>${items.length ? 'No hay pendientes que coincidan.' : 'No hay pendientes de punch. Crea el primero con «+ Nuevo».'}</p></div>`;
    return;
  }
  cont.innerHTML = lista.map((x) => `
    <button class="tarea-item" type="button" data-punch="${Number(x.id)}"${Number(x.id) <= 0 ? ' disabled' : ''}>
      <span class="tarea-punto ${esc(x.severidad || 'media')}"></span>
      <span class="tarea-txt"><strong>${esc(x.titulo)}</strong>
        <small>${esc(x.ubicacion || 'Sin ubicación')}${x.act_codigo ? ' · ' + esc(x.act_codigo) : ''}${x.fecha_limite ? ' · ' + (x.vencido ? 'vencido ' : 'límite ') + fechaLarga(x.fecha_limite) : ''}</small></span>
      <span class="chip ${x.estado === 'cerrado' ? 'chip-ok' : (x.vencido || x.severidad === 'critica' ? 'chip-bad' : (x.estado === 'abierto' ? 'chip-warn' : ''))}">${esc(PUNCH_ESTADOS[x.estado] || x.estado)}</span>
    </button>`).join('');
  cont.onclick = (e) => {
    const b = e.target.closest('[data-punch]');
    if (!b || !cont.contains(b)) return;
    const x = items.find((i) => Number(i.id) === Number(b.dataset.punch));
    if (x) verPunch(p, x);
  };
}

function verPunch(p, x) {
  const filas = [
    ['Estado', PUNCH_ESTADOS[x.estado] || x.estado],
    ['Severidad', PUNCH_SEVERIDADES[x.severidad] || x.severidad || 'Media'],
    ['Ubicación', x.ubicacion || '—'],
    ['Responsable', x.responsable || '—'],
    ['Contratista', x.contratista || '—'],
    ['Actividad', x.act_codigo || '—'],
    ['Emisión', x.fecha_emision ? fechaLarga(x.fecha_emision) : '—'],
    ['Límite', x.fecha_limite ? fechaLarga(x.fecha_limite) : '—'],
  ];
  const puede = p.permisos?.editar && Number(x.id) > 0 && x.estado !== 'cerrado';
  const acciones = puede ? `
    <div class="tarea-acciones">
      ${x.estado === 'abierto' ? '<button class="btn-mini" type="button" data-p-estado="en_proceso">En proceso</button>' : ''}
      ${x.estado !== 'corregido' ? '<button class="btn-mini" type="button" data-p-estado="corregido">Corregido</button>' : ''}
      <button class="btn-mini" type="button" data-p-estado="cerrado">Cerrar</button>
    </div>` : '';
  abrirModal('Punch', `
    <div class="hist-detalle">${filas.map(([k, v]) => `<div><span>${esc(k)}</span><strong>${esc(String(v))}</strong></div>`).join('')}</div>
    ${x.descripcion ? `<p class="parte-nota">${esc(x.descripcion)}</p>` : ''}
    ${x._pendiente ? '<p class="parte-nota">Pendiente de enviar: se sincroniza al recuperar conexión.</p>' : ''}
    ${acciones}`);
  $$('#modal-cuerpo [data-p-estado]').forEach((b) => b.addEventListener('click', () => cambiarEstadoPunch(p, x, b.dataset.pEstado)));
}

async function cambiarEstadoPunch(p, x, estado) {
  await encolar({ tipo: 'punch_estado', proyecto: Number(p.proyecto.id), uuid: nuevoUuid(), id: Number(x.id), estado });
  x.estado = estado;
  cerrarModal();
  renderPunch(p);
  toast('Pendiente actualizado.');
  if (navigator.onLine) sincronizar(true);
}

function formPunch(p, pre = {}) {
  const actOpts = opcionesActividades(p);
  abrirModal('Nuevo pendiente', `
    <form id="punch-form" class="modal-form">
      <label>Título *<input type="text" id="p-titulo" maxlength="200" required placeholder="Muro sin aplanar, fuga en baño…" value="${esc(pre.titulo || '')}"></label>
      <label>Descripción<textarea id="p-desc" rows="3" maxlength="3000">${esc(pre.descripcion || '')}</textarea></label>
      <div class="parte-grid">
        <label class="parte-campo">Ubicación<input type="text" id="p-ubicacion" maxlength="200" placeholder="Nivel 3, eje B"></label>
        <label class="parte-campo">Categoría<input type="text" id="p-categoria" maxlength="60" placeholder="Acabados"></label>
        <label class="parte-campo">Severidad<select id="p-severidad">${Object.entries(PUNCH_SEVERIDADES).map(([k, v]) => `<option value="${k}"${k === 'media' ? ' selected' : ''}>${v}</option>`).join('')}</select></label>
        <label class="parte-campo">Límite<input type="date" id="p-limite" value="${sumarDiasISO(7)}"></label>
        <label class="parte-campo">Responsable<input type="text" id="p-responsable" maxlength="150"></label>
        <label class="parte-campo">Contratista<input type="text" id="p-contratista" maxlength="150"></label>
      </div>
      <label class="parte-campo">Actividad<select id="p-actividad">${actOpts}</select></label>
      <button class="btn-principal" type="submit">Guardar pendiente</button>
    </form>`);
  $('#punch-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const titulo = String($('#p-titulo')?.value || '').trim();
    if (!titulo) { toast('Escribe el título del pendiente.'); return; }
    const op = {
      tipo: 'punch_crear', proyecto: Number(p.proyecto.id), uuid: nuevoUuid(),
      titulo,
      descripcion: String($('#p-desc')?.value || '').trim(),
      ubicacion: String($('#p-ubicacion')?.value || '').trim(),
      categoria: String($('#p-categoria')?.value || '').trim(),
      severidad: String($('#p-severidad')?.value || 'media'),
      responsable: String($('#p-responsable')?.value || '').trim(),
      contratista: String($('#p-contratista')?.value || '').trim(),
      fecha_emision: hoyISO(),
      fecha_limite: String($('#p-limite')?.value || ''),
      actividad_id: Number($('#p-actividad')?.value || 0),
    };
    await encolar(op);
    if (!Array.isArray(p.punch)) p.punch = [];
    p.punch.unshift({ ...op, id: 0, estado: 'abierto', vencido: false, _pendiente: true, act_codigo: '' });
    cerrarModal();
    renderPunch(p);
    toast('Pendiente guardado. Se enviará al sincronizar.');
    if (navigator.onLine) sincronizar(true);
  });
}

function abrirRfi(id) {
  const p = state.sync.find((x) => Number(x.proyecto.id) === Number(id));
  if (!p) { toast('No se encontró la obra.'); return; }
  state.rfiVolver = state.tab === 'rfi' ? 'obras' : state.tab;
  state.proyectoId = Number(p.proyecto.id);
  cacheGuardar('proyecto', String(state.proyectoId));
  cerrarModal();
  $('#rfi-obra').textContent = `${p.proyecto.nombre} · ${p.proyecto.codigo || ''}`;
  const input = $('#rfi-buscar'); if (input) input.value = '';
  renderRfi(p);
  irA('rfi');
  setTimeout(() => { const v = $('#vista'); if (v) v.scrollTo({ top: 0, behavior: 'auto' }); }, 220);
}

function renderRfi(p) {
  const cont = $('#rfi-lista');
  if (!cont) return;
  const items = Array.isArray(p.rfis) ? p.rfis : [];
  const q = normalizarTexto($('#rfi-buscar')?.value || '').trim();
  const lista = q ? items.filter((x) => normalizarTexto(`${x.numero || ''} ${x.asunto} ${x.pregunta || ''} ${x.responsable || ''}`).includes(q)) : items;
  $('#rfi-nuevo').hidden = !p.permisos?.editar;
  if (!lista.length) {
    cont.innerHTML = `<div class="aviso"><svg class="ico"><use href="#i-check"/></svg><p>${items.length ? 'No hay RFI que coincidan.' : 'No hay RFI en esta obra. Crea el primero con «+ Nuevo».'}</p></div>`;
    return;
  }
  cont.innerHTML = lista.map((x) => `
    <button class="tarea-item" type="button" data-rfi="${Number(x.id)}"${Number(x.id) <= 0 ? ' disabled' : ''}>
      <span class="tarea-punto ${esc(x.prioridad || 'media')}"></span>
      <span class="tarea-txt"><strong>${esc(x.numero || 'RFI')} · ${esc(x.asunto)}</strong>
        <small>${esc(x.responsable || 'Sin responsable')}${x.act_codigo ? ' · ' + esc(x.act_codigo) : ''}${x.fecha_limite ? ' · ' + (x.vencido ? 'vencido ' : 'límite ') + fechaLarga(x.fecha_limite) : ''}</small></span>
      <span class="chip ${x.estado === 'cerrado' ? 'chip-ok' : (x.vencido ? 'chip-bad' : (x.estado === 'abierto' ? 'chip-warn' : ''))}">${esc(RFI_ESTADOS[x.estado] || x.estado)}</span>
    </button>`).join('');
  cont.onclick = (e) => {
    const b = e.target.closest('[data-rfi]');
    if (!b || !cont.contains(b)) return;
    const x = items.find((i) => Number(i.id) === Number(b.dataset.rfi));
    if (x) verRfi(p, x);
  };
}

function verRfi(p, x) {
  const filas = [
    ['Estado', RFI_ESTADOS[x.estado] || x.estado],
    ['Prioridad', RFI_PRIORIDADES[x.prioridad] || x.prioridad || 'Media'],
    ['Actividad', x.act_codigo ? `${x.act_codigo} · ${x.act_nombre || ''}` : '—'],
    ['Responsable', x.responsable || '—'],
    ['Fecha', x.fecha ? fechaLarga(x.fecha) : '—'],
    ['Límite', x.fecha_limite ? fechaLarga(x.fecha_limite) : '—'],
    ['Respuesta', x.fecha_respuesta ? fechaLarga(x.fecha_respuesta) : 'Pendiente'],
  ];
  const puede = p.permisos?.editar && Number(x.id) > 0 && x.estado !== 'cerrado';
  const acciones = puede ? `
    <div class="tarea-acciones">
      ${x.estado === 'abierto' ? '<button class="btn-mini" type="button" data-r-estado="respondido">Marcar respondido</button>' : ''}
      <button class="btn-mini" type="button" data-r-estado="cerrado">Cerrar</button>
    </div>` : '';
  abrirModal('RFI ' + (x.numero || ''), `
    <div class="hist-detalle">${filas.map(([k, v]) => `<div><span>${esc(k)}</span><strong>${esc(String(v))}</strong></div>`).join('')}</div>
    <p class="parte-nota"><strong>Pregunta:</strong><br>${esc(x.pregunta || '—')}</p>
    <p class="parte-nota"><strong>Respuesta:</strong><br>${esc(x.respuesta || 'Sin respuesta todavía.')}</p>
    ${x._pendiente ? '<p class="parte-nota">Pendiente de enviar: se sincroniza al recuperar conexión.</p>' : ''}
    ${acciones}`);
  $$('#modal-cuerpo [data-r-estado]').forEach((b) => b.addEventListener('click', () => cambiarEstadoRfi(p, x, b.dataset.rEstado)));
}

async function cambiarEstadoRfi(p, x, estado) {
  await encolar({ tipo: 'rfi_estado', proyecto: Number(p.proyecto.id), uuid: nuevoUuid(), id: Number(x.id), estado });
  x.estado = estado;
  cerrarModal();
  renderRfi(p);
  toast('RFI actualizado.');
  if (navigator.onLine) sincronizar(true);
}

function formRfi(p, pre = {}) {
  const actOpts = opcionesActividades(p);
  abrirModal('Nuevo RFI', `
    <form id="rfi-form" class="modal-form">
      <label>Asunto *<input type="text" id="r-asunto" maxlength="200" required placeholder="Detalle de muro en eje B" value="${esc(pre.asunto || '')}"></label>
      <label>Pregunta *<textarea id="r-pregunta" rows="4" maxlength="5000" required placeholder="¿Qué se debe hacer…?">${esc(pre.pregunta || '')}</textarea></label>
      <div class="parte-grid">
        <label class="parte-campo">Responsable<input type="text" id="r-responsable" maxlength="150" placeholder="Proyectista, cliente…"></label>
        <label class="parte-campo">Prioridad<select id="r-prioridad">${Object.entries(RFI_PRIORIDADES).map(([k, v]) => `<option value="${k}"${k === 'media' ? ' selected' : ''}>${v}</option>`).join('')}</select></label>
        <label class="parte-campo">Fecha<input type="date" id="r-fecha" value="${hoyISO()}"></label>
        <label class="parte-campo">Límite<input type="date" id="r-limite" value="${sumarDiasISO(7)}"></label>
      </div>
      <label class="parte-campo">Actividad<select id="r-actividad">${actOpts}</select></label>
      <button class="btn-principal" type="submit">Guardar RFI</button>
    </form>`);
  $('#rfi-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const asunto = String($('#r-asunto')?.value || '').trim();
    const pregunta = String($('#r-pregunta')?.value || '').trim();
    if (!asunto || !pregunta) { toast('Escribe el asunto y la pregunta.'); return; }
    const op = {
      tipo: 'rfi_crear', proyecto: Number(p.proyecto.id), uuid: nuevoUuid(),
      asunto, pregunta,
      responsable: String($('#r-responsable')?.value || '').trim(),
      prioridad: String($('#r-prioridad')?.value || 'media'),
      fecha: String($('#r-fecha')?.value || hoyISO()),
      fecha_limite: String($('#r-limite')?.value || ''),
      actividad_id: Number($('#r-actividad')?.value || 0),
    };
    await encolar(op);
    if (!Array.isArray(p.rfis)) p.rfis = [];
    p.rfis.unshift({ ...op, id: 0, numero: 'RFI', estado: 'abierto', vencido: false, _pendiente: true, act_codigo: '' });
    cerrarModal();
    renderRfi(p);
    toast('RFI guardado. Se enviará al sincronizar.');
    if (navigator.onLine) sincronizar(true);
  });
}

function renderArchivoObra(p) {
  const cont = $('#archivo-lista');
  if (!cont) return;
  const archivos = p.archivos || [];
  const q = normalizarTexto($('#archivo-buscar')?.value || '').trim();
  const lista = q ? archivos.filter((f) => normalizarTexto(`${f.nombre} ${f.categoria || ''} ${f.extension || ''}`).includes(q)) : archivos;
  if (!lista.length) {
    cont.innerHTML = '<div class="aviso"><svg class="ico"><use href="#i-obra"/></svg><p>' + (archivos.length ? 'No hay archivos que coincidan.' : 'No hay archivos descargados. Sincroniza para ver el Archivo de la obra.') + '</p></div>';
    return;
  }
  cont.innerHTML = lista.map((f) => `
    <div class="archivo-item">
      <span class="archivo-ico">${esc((f.extension || 'DOC').slice(0, 4))}</span>
      <div><strong>${esc(f.nombre)}${f.revision ? ' · Rev. ' + esc(f.revision) : ''}</strong><small>${esc(f.categoria || 'Sin categoría')} · ${archivoTamano(f.tamano)} · ${esc(fechaLarga(f.fecha))}</small></div>
      <button class="btn-mini" type="button" data-abrir-archivo="${Number(f.id)}">Abrir</button>
    </div>`).join('');
  $$('[data-abrir-archivo]', cont).forEach((b) => b.addEventListener('click', () => {
    const f = archivos.find((x) => Number(x.id) === Number(b.dataset.abrirArchivo));
    if (f) descargarAbrirArchivo(f);
  }));
}

const VISOR_EXT = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'txt', 'csv', 'log', 'json', 'xml'];

async function descargarAbrirArchivo(f) {
  const ext = String(f.extension || '').toLowerCase();
  const propio = VISOR_EXT.includes(ext);
  if (!tieneTauri()) {
    toast('El visor de documentos está disponible en la app Android.');
    return;
  }
  if (!state.token) { toast('Conecta tu cuenta para abrir el archivo.'); return; }
  if (!propio) {
    // Documentos de oficina: se abren con una app externa (necesita conexión si no están descargados).
    if (!navigator.onLine) { toast('Ese documento necesita conexión para abrirse en este teléfono.'); return; }
    const url = apiUrl('/api/app/archivo?id=' + Number(f.id) + '&token=' + encodeURIComponent(state.token));
    try {
      if (window.__TAURI__?.opener?.openUrl) await window.__TAURI__.opener.openUrl(url);
      else window.open(url, '_blank', 'noopener');
    } catch (e) { toast('No se pudo abrir el documento.', 5000); }
    return;
  }
  const nombre = `${Number(f.id)}-${String(f.nombre || 'archivo').replace(/[^a-zA-Z0-9._-]+/g, '_')}.${ext}`;
  toast(navigator.onLine ? 'Descargando documento…' : 'Abriendo documento guardado…', 5000);
  try {
    const ruta = await invokeTauri('descargar_archivo', {
      url: apiUrl('/api/app/archivo?id=' + Number(f.id)),
      token: state.token,
      nombre,
    });
    const datos = await invokeTauri('leer_archivo', { ruta });
    abrirVisor(f, datos, ext);
  } catch (e) {
    const msg = String(e?.message || e || 'No se pudo abrir el documento.');
    toast(/descargado|no se pudo descargar|conexión|servidor/i.test(msg) ? 'Necesitas conexión para descargarlo la primera vez.' : msg, 6500);
  }
}

function bytesDe(datos) {
  if (datos instanceof ArrayBuffer) return new Uint8Array(datos);
  if (ArrayBuffer.isView(datos)) return new Uint8Array(datos.buffer, datos.byteOffset, datos.byteLength);
  if (Array.isArray(datos)) return new Uint8Array(datos);
  return new Uint8Array(0);
}

function mimeDe(ext) {
  if (ext === 'pdf') return 'application/pdf';
  if (ext === 'svg') return 'image/svg+xml';
  if (['txt', 'csv', 'log'].includes(ext)) return 'text/plain; charset=utf-8';
  if (ext === 'json') return 'application/json';
  if (ext === 'xml') return 'application/xml';
  return 'image/' + (ext === 'jpg' ? 'jpeg' : ext);
}

function abrirVisor(f, datos, ext) {
  const bytes = bytesDe(datos);
  if (!bytes.length) { toast('El documento está vacío.'); return; }
  cerrarVisor(false);
  state.visorVolver = state.tab === 'visor' ? 'archivo' : state.tab;
  state.visorToken = (state.visorToken || 0) + 1;
  const token = state.visorToken;
  state.visor = { pdf: null, escala: 1, token };
  const titulo = $('#visor-titulo');
  const info = $('#visor-info');
  if (titulo) titulo.textContent = String(f.nombre || 'Documento') + (f.revision ? ' · Rev. ' + f.revision : '');
  if (info) info.textContent = [f.categoria || '', archivoTamano(f.tamano), fechaLarga(f.fecha)].filter(Boolean).join(' · ');
  const zoom = $('#visor-zoom');
  if (zoom) zoom.hidden = ext !== 'pdf';
  const estado = $('#visor-estado');
  if (estado) { estado.hidden = true; estado.textContent = ''; }
  irA('visor');
  setTimeout(() => { const v = $('#vista'); if (v) v.scrollTo({ top: 0, behavior: 'auto' }); }, 220);

  if (ext === 'pdf') { visorPdf(bytes, token); return; }
  const cont = $('#visor-paginas');
  if (!cont) return;
  if (['txt', 'csv', 'log', 'json', 'xml'].includes(ext)) {
    cont.innerHTML = '';
    const pre = document.createElement('pre');
    pre.textContent = new TextDecoder('utf-8').decode(bytes);
    cont.appendChild(pre);
    return;
  }
  const blob = new Blob([bytes], { type: mimeDe(ext) });
  const url = URL.createObjectURL(blob);
  state.visor.blobUrl = url;
  cont.innerHTML = `<img src="${url}" alt="${esc(String(f.nombre || 'Imagen'))}">`;
}

function visorMensaje(texto) {
  const el = $('#visor-estado');
  if (!el) return;
  el.hidden = !texto;
  el.textContent = texto || '';
}

async function visorPdf(bytes, token) {
  const cont = $('#visor-paginas');
  if (!cont) return;
  cont.innerHTML = '';
  if (!window.pdfjsLib) { visorMensaje('No se pudo cargar el visor de PDF. Actualiza la app.'); return; }
  if (!window.pdfjsLib.GlobalWorkerOptions.workerSrc) {
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'vendor/pdfjs/pdf.worker.min.js';
  }
  visorMensaje('Cargando documento…');
  try {
    const pdf = await window.pdfjsLib.getDocument({ data: bytes }).promise;
    if (token !== state.visor?.token) return;
    state.visor.pdf = pdf;
    state.visor.paginas = pdf.numPages;
    visorMensaje('');
    visorPintarEstructura();
  } catch (e) {
    visorMensaje('No se pudo abrir el PDF. Puede estar dañado o protegido.');
  }
}

function visorPintarEstructura() {
  const cont = $('#visor-paginas');
  const v = state.visor;
  if (!cont || !v?.pdf) return;
  v.observador?.disconnect();
  cont.innerHTML = '';
  const fragmento = document.createDocumentFragment();
  for (let n = 1; n <= v.paginas; n++) {
    const pagina = document.createElement('div');
    pagina.className = 'visor-pagina';
    pagina.dataset.pagina = String(n);
    pagina.dataset.listo = '0';
    pagina.style.minHeight = '220px';
    const canvas = document.createElement('canvas');
    pagina.appendChild(canvas);
    const num = document.createElement('span');
    num.className = 'visor-num';
    num.textContent = String(n);
    pagina.appendChild(num);
    fragmento.appendChild(pagina);
  }
  cont.appendChild(fragmento);
  const observador = new IntersectionObserver((entradas) => {
    entradas.forEach((e) => { if (e.isIntersecting) visorPintarPagina(e.target); });
  }, { root: $('#vista') || null, rootMargin: '700px 0px' });
  v.observador = observador;
  cont.querySelectorAll('.visor-pagina').forEach((p) => observador.observe(p));
  visorPintarPagina(cont.querySelector('.visor-pagina'));
}

async function visorPintarPagina(div) {
  const v = state.visor;
  if (!div || !v?.pdf || div.dataset.listo === '1') return;
  div.dataset.listo = '1';
  const n = Number(div.dataset.pagina);
  const cont = $('#visor-paginas');
  try {
    const pagina = await v.pdf.getPage(n);
    if (v.token !== state.visor?.token) return;
    const base = pagina.getViewport({ scale: 1 });
    const ancho = Math.max(260, (cont?.clientWidth || 360) - 24);
    const escala = (ancho / base.width) * (v.escala || 1);
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    const vp = pagina.getViewport({ scale: escala * dpr });
    const canvas = div.querySelector('canvas');
    if (!canvas) return;
    canvas.width = Math.floor(vp.width);
    canvas.height = Math.floor(vp.height);
    canvas.style.width = '100%';
    canvas.style.height = 'auto';
    const ctx = canvas.getContext('2d', { alpha: false });
    await pagina.render({ canvasContext: ctx, viewport: vp }).promise;
  } catch (e) {
    div.dataset.listo = '0';
  }
}

function visorZoom(delta) {
  const v = state.visor;
  if (!v?.pdf) return;
  v.escala = Math.max(0.5, Math.min(3, (v.escala || 1) + delta));
  const cont = $('#visor-paginas');
  if (!cont) return;
  cont.querySelectorAll('.visor-pagina').forEach((p) => {
    p.dataset.listo = '0';
    const c = p.querySelector('canvas');
    if (c) { c.width = 1; c.height = 1; }
    p.style.minHeight = '220px';
  });
  visorPintarPagina(cont.querySelector('.visor-pagina'));
  cont.querySelectorAll('.visor-pagina').forEach((p) => {
    const r = p.getBoundingClientRect();
    if (r.top < window.innerHeight && r.bottom > 0) visorPintarPagina(p);
  });
}

function cerrarVisor(volver = true) {
  state.visorToken = (state.visorToken || 0) + 1;
  if (state.visor?.observador) state.visor.observador.disconnect();
  if (state.visor?.blobUrl) URL.revokeObjectURL(state.visor.blobUrl);
  if (state.visor?.pdf) { try { state.visor.pdf.destroy(); } catch (e) {} }
  state.visor = null;
  const cont = $('#visor-paginas');
  if (cont) cont.innerHTML = '';
  if (volver) irA(state.visorVolver || 'archivo');
}

function abrirArchivoObra(id) {
  const p = state.sync.find((x) => Number(x.proyecto.id) === Number(id));
  if (!p) { toast('No se encontró la obra.'); return; }
  state.archivoVolver = state.tab === 'archivo' ? 'obras' : state.tab;
  state.proyectoId = Number(p.proyecto.id);
  cacheGuardar('proyecto', String(state.proyectoId));
  cerrarModal();
  $('#archivo-obra').textContent = `${p.proyecto.nombre} · ${p.proyecto.codigo || ''}`;
  const input = $('#archivo-buscar'); if (input) input.value = '';
  renderArchivoObra(p);
  irA('archivo');
  setTimeout(() => { const v = $('#vista'); if (v) v.scrollTo({ top: 0, behavior: 'auto' }); }, 250);
}

function renderFotosEvidencia() {
  const cont = $('#evid-fotos-prev');
  if (!cont) return;
  cont.innerHTML = state.evidenciaFotos.length ? state.evidenciaFotos.map((f, i) => `
    <div class="parte-foto">
      <img src="${f.data}" alt="${esc(f.nombre || 'Evidencia')}">
      <button type="button" data-quitar-evidencia="${i}" aria-label="Quitar foto">×</button>
    </div>`).join('') : '';
  $$('[data-quitar-evidencia]', cont).forEach((b) => b.addEventListener('click', () => {
    state.evidenciaFotos.splice(Number(b.dataset.quitarEvidencia), 1);
    renderFotosEvidencia();
  }));
}

function renderEvidencias(p) {
  const proj = p.proyecto;
  const hoy = hoyISO();
  const acts = (p.actividades || []).filter((a) => !Number(a.hito));
  const opciones = acts.map((a) => `<option value="${Number(a.id)}">${esc(a.codigo)} · ${esc(a.nombre)}</option>`).join('');
  state.evidenciaFotos = [];
  $('#evidencias-obra').textContent = `${proj.nombre} · ${proj.codigo || ''}`;
  $('#evidencias-form').innerHTML = `
    <div class="parte-grupo">
      <h3>Datos de la evidencia</h3>
      <div class="parte-grid">
        <label class="parte-campo">Fecha *<input type="date" id="evid-fecha" value="${hoy}" required></label>
        <label class="parte-campo">Actividad *<select id="evid-actividad"><option value="0">Elige una actividad</option>${opciones}</select></label>
        <label class="parte-campo">Departamento<input type="text" id="evid-departamento" maxlength="100"></label>
        <label class="parte-campo">Categoría<input type="text" id="evid-categoria" maxlength="100"></label>
      </div>
      <label class="parte-campo" style="margin-top:.7rem">Descripción<textarea id="evid-descripcion" rows="3" maxlength="300" placeholder="Qué muestra la foto, dónde y por qué…"></textarea></label>
    </div>
    <div class="parte-grupo">
      <h3>Fotos</h3>
      <p class="parte-nota">Hasta 5 fotos. Se guardan en el teléfono y se envían al sincronizar.</p>
      <input type="file" id="evid-fotos" class="parte-file" accept="image/*" multiple>
      <div id="evid-fotos-prev" class="parte-fotos-prev"></div>
    </div>
    <div class="parte-acciones">
      <button class="btn-principal" type="submit">Guardar evidencia</button>
      <button class="btn-mini" type="button" id="evid-cancelar">Cancelar</button>
    </div>`;
  $('#evid-cancelar')?.addEventListener('click', () => irA(state.evidenciasVolver || 'obras'));
  $('#evid-fotos')?.addEventListener('change', async (e) => {
    const archivos = [...(e.target.files || [])].slice(0, Math.max(0, 5 - state.evidenciaFotos.length));
    for (const archivo of archivos) {
      if (!archivo.type.startsWith('image/')) continue;
      if (archivo.size > 8 * 1024 * 1024) { toast('Cada foto debe pesar menos de 8 MB.'); continue; }
      try { state.evidenciaFotos.push({ nombre: archivo.name, data: await leerArchivoDataUrl(archivo) }); } catch (err) { toast('No se pudo leer la foto.'); }
    }
    e.target.value = '';
    renderFotosEvidencia();
  });
  renderFotosEvidencia();
  $('#evidencias-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!p.permisos?.editar) { toast('Tu usuario no puede editar esta obra.'); return; }
    const actividad = Number($('#evid-actividad')?.value || 0);
    if (!actividad) { toast('Elige la actividad de la evidencia.'); return; }
    if (!state.evidenciaFotos.length) { toast('Adjunta al menos una foto.'); return; }
    const fecha = String($('#evid-fecha')?.value || hoy);
    await encolar({
      tipo: 'evidencia_crear', proyecto: Number(proj.id), fecha, actividad_id: actividad,
      descripcion: String($('#evid-descripcion')?.value || '').trim(),
      departamento: String($('#evid-departamento')?.value || '').trim(),
      categoria: String($('#evid-categoria')?.value || '').trim(),
      fotos_base64: state.evidenciaFotos.map((f) => f.data),
    });
    toast('Evidencia guardada. Se enviará al sincronizar.');
    irA(state.evidenciasVolver || 'obras');
  });
}

function abrirEvidencias(id) {
  const p = state.sync.find((x) => Number(x.proyecto.id) === Number(id));
  if (!p) { toast('No se encontró la obra.'); return; }
  if (!p.permisos?.editar) { toast('Tu usuario no puede editar esta obra.'); return; }
  state.evidenciasVolver = state.tab === 'evidencias' ? 'obras' : state.tab;
  state.proyectoId = Number(p.proyecto.id);
  cacheGuardar('proyecto', String(state.proyectoId));
  cerrarModal();
  renderEvidencias(p);
  irA('evidencias');
  setTimeout(() => { const v = $('#vista'); if (v) v.scrollTo({ top: 0, behavior: 'auto' }); }, 250);
}

function renderParteDiario(p) {
  const proj = p.proyecto;
  const hoy = hoyISO();
  const cat = p.parte || {};
  state.parteFotos = [];
  const acts = (p.actividades || []).filter((a) => !Number(a.hito))
    .sort((a, b) => String(a.fecha_inicio || '').localeCompare(String(b.fecha_inicio || '')));
  const personal = cat.personal || [];
  const equipo = p.equipo || [];
  const mats = p.materiales || [];
  const turnos = cat.turnos || { dia: 'Día', tarde: 'Tarde', noche: 'Noche' };
  const tipos = cat.tipos_material || { consumido: 'Consumido', recibido: 'Recibido', retirado: 'Retirado' };
  const actOpts = '<option value="0">Sin actividad</option>' + acts.map((a) => `<option value="${Number(a.id)}">${esc(a.codigo)} · ${esc(a.nombre)}</option>`).join('');
  const esDeHoy = (a) => a.fecha_inicio && a.fecha_fin && a.fecha_inicio <= hoy && a.fecha_fin >= hoy && Number(a.progreso) < 100;
  const actRows = acts.length ? acts.map((a) => `
    <label class="parte-act">
      <input type="checkbox" data-act="${Number(a.id)}"${esDeHoy(a) ? ' checked' : ''}>
      <span><strong>${esc(a.codigo)} · ${esc(a.nombre)}</strong><small>${Number(a.progreso) || 0} % · fin ${esc(fechaLarga(a.fecha_fin))}</small></span>
      <input type="number" min="0" max="100" step="1" value="${Number(a.progreso) || 0}" data-avance="${Number(a.id)}" aria-label="Avance de ${esc(a.nombre)}">
    </label>`).join('') : '<p class="parte-vacio">No hay actividades en el programa descargado.</p>';
  const personalRows = personal.length ? personal.map((x) => `
    <div class="parte-fila">
      <label class="parte-check"><input type="checkbox" data-personal="${Number(x.id)}"><span><strong>${esc(x.nombre)}</strong><small>${esc(x.departamento || 'Sin departamento')}</small></span></label>
      <label class="parte-campo">Horas<input type="number" min="0" max="24" step=".25" value="8" data-horas="${Number(x.id)}"></label>
      <label class="parte-campo">Extra<input type="number" min="0" max="24" step=".25" value="0" data-extra="${Number(x.id)}"></label>
      <label class="parte-campo">Actividad<select data-personal-act="${Number(x.id)}">${actOpts}</select></label>
    </div>`).join('') : '<p class="parte-vacio">Sincroniza para descargar el personal de la obra.</p>';
  const equipoRows = equipo.length ? equipo.map((x) => `
    <div class="parte-fila">
      <label class="parte-check"><input type="checkbox" data-equipo="${Number(x.id)}"><span><strong>${esc(x.nombre)}</strong><small>${esc(x.tipo || 'Equipo')} · ${esc(x.estado || '')}</small></span></label>
      <label class="parte-campo">Horas<input type="number" min="0" max="24" step=".25" value="8" data-equipo-horas="${Number(x.id)}"></label>
      <label class="parte-campo">Actividad<select data-equipo-act="${Number(x.id)}">${actOpts}</select></label>
      <label class="parte-campo">Operador<input type="text" maxlength="150" data-operador="${Number(x.id)}"></label>
    </div>`).join('') : '<p class="parte-vacio">No hay equipo registrado.</p>';
  const matRows = mats.length ? mats.slice(0, 300).map((x) => `
    <div class="parte-fila">
      <label class="parte-check"><input type="checkbox" data-material="${Number(x.id)}" data-unidad="${esc(x.unidad || '')}"><span><strong>${esc(x.nombre)}</strong><small>${esc(x.marca || '')} ${esc(x.unidad || '')}</small></span></label>
      <label class="parte-campo">Cantidad<input type="number" min="0" step=".001" value="0" data-material-cant="${Number(x.id)}"></label>
      <label class="parte-campo">Movimiento<select data-material-tipo="${Number(x.id)}">${Object.entries(tipos).map(([k, v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join('')}</select></label>
      <span></span>
    </div>`).join('') : '<p class="parte-vacio">No hay materiales en el catálogo descargado.</p>';

  const opcionesTurno = Object.entries(turnos).map(([k, v]) => `<option value="${esc(k)}"${k === 'dia' ? ' selected' : ''}>${esc(v)}</option>`).join('');
  $('#parte-obra').textContent = `${proj.nombre} · ${proj.codigo || ''}`;
  $('#parte-form').innerHTML = `
    <div class="parte-grupo">
      <h3>Datos del día</h3>
      <div class="parte-grid">
        <label class="parte-campo">Fecha *<input type="date" id="parte-fecha" value="${hoy}" required></label>
        <label class="parte-campo">Turno<select id="parte-turno">${opcionesTurno}</select></label>
        <label class="parte-campo">Área / frente<input type="text" id="parte-area" maxlength="150" placeholder="Torre A, nivel 3…"></label>
        <label class="parte-campo">Supervisor<input type="text" id="parte-supervisor" maxlength="150" value="${esc(state.usuario?.nombre || '')}"></label>
        <label class="parte-campo">Visitas<input type="text" id="parte-visitas" maxlength="500" placeholder="Cliente, supervisión…"></label>
      </div>
      <label class="parte-campo" style="margin-top:.7rem">Comentarios del día<textarea id="parte-comentarios" rows="3" maxlength="5000" placeholder="Qué se hizo, qué quedó pendiente…"></textarea></label>
      <div class="parte-grid" style="margin-top:.7rem">
        <label class="parte-campo">Lluvia (mm)<input type="number" id="parte-lluvia" step=".1" min="0"></label>
        <label class="parte-campo">Viento (km/h)<input type="number" id="parte-viento" step=".1" min="0"></label>
        <label class="parte-campo">T. máx (°C)<input type="number" id="parte-tmax" step=".1"></label>
        <label class="parte-campo">T. mín (°C)<input type="number" id="parte-tmin" step=".1"></label>
      </div>
    </div>
    <div class="parte-grupo">
      <h3>Actividades y avance</h3>
      <p class="parte-nota">Marca las actividades del día y ajusta su avance. Se guarda en el teléfono y se envía al sincronizar.</p>
      <div class="parte-lista">${actRows}</div>
    </div>
    <div class="parte-grupo">
      <h3>Personal</h3>
      <p class="parte-nota">Marca a quienes trabajaron y ajusta sus horas.</p>
      <div class="parte-lista">${personalRows}</div>
    </div>
    <div class="parte-grupo">
      <h3>Equipo</h3>
      <p class="parte-nota">Horas de equipo y maquinaria utilizadas.</p>
      <div class="parte-lista">${equipoRows}</div>
    </div>
    <div class="parte-grupo">
      <h3>Materiales</h3>
      <p class="parte-nota">Movimientos del día: consumido, recibido o retirado.</p>
      <div class="parte-lista">${matRows}</div>
    </div>
    <div class="parte-grupo">
      <h3>Tiempos muertos</h3>
      <p class="parte-nota">Horas detenidas por lluvia, falta de material, espera o falla de equipo.</p>
      <div id="parte-tiempos" class="parte-lista"></div>
      <button class="btn-mini" type="button" id="parte-tiempo-add">+ Agregar tiempo muerto</button>
    </div>
    <div class="parte-grupo">
      <h3>Fotos</h3>
      <p class="parte-nota">Hasta 5 fotos del avance, materiales o incidencias. Se guardan en el teléfono y se envían al sincronizar.</p>
      <input type="file" id="parte-fotos" class="parte-file" accept="image/*" multiple>
      <div id="parte-fotos-prev" class="parte-fotos-prev"></div>
    </div>
    <div class="parte-grupo">
      <h3>Seguridad</h3>
      <div class="parte-grid">
        <label class="parte-campo">Charla / tema<input type="text" id="parte-charla" maxlength="255" placeholder="Orden y limpieza…"></label>
        <label class="parte-campo">Asistentes<input type="number" id="parte-asistentes" min="0" max="32767" value="0"></label>
        <label class="parte-check" style="align-self:end"><input type="checkbox" id="parte-epp"> EPP completo</label>
      </div>
    </div>
    <div class="parte-grupo">
      <h3>Firma</h3>
      <p class="parte-nota">Firma con el dedo o el lápiz. Se guarda con el parte.</p>
      <canvas id="parte-firma" class="parte-firma" width="700" height="220"></canvas>
      <div class="parte-grid" style="margin-top:.6rem">
        <label class="parte-campo">Nombre de quien firma<input type="text" id="parte-firma-nombre" maxlength="150" value="${esc(state.usuario?.nombre || '')}"></label>
        <button class="btn-mini" type="button" id="parte-firma-limpiar" style="align-self:end">Limpiar firma</button>
      </div>
    </div>
    <div class="parte-acciones">
      <button class="btn-principal" type="submit" name="accion" value="cerrar">Guardar y cerrar</button>
      <button class="btn-mini" type="submit" name="accion" value="guardar">Guardar borrador</button>
      <button class="btn-mini" type="button" id="parte-cancelar">Cancelar</button>
      <p class="parte-nota" style="flex-basis:100%;margin:.3rem 0 0">«Guardar borrador» lo deja abierto; «Guardar y cerrar» agrega el resumen a la bitácora.</p>
    </div>`;

  $('#parte-cancelar')?.addEventListener('click', () => irA(state.parteVolver || 'obras'));
  const firma = $('#parte-firma');
  if (firma) {
    prepararFirma(firma);
    $('#parte-firma-limpiar')?.addEventListener('click', () => limpiarFirma(firma));
  }
  $('#parte-fotos')?.addEventListener('change', async (e) => {
    const archivos = [...(e.target.files || [])].slice(0, Math.max(0, 5 - state.parteFotos.length));
    for (const archivo of archivos) {
      if (!archivo.type.startsWith('image/')) continue;
      if (archivo.size > 8 * 1024 * 1024) { toast('Cada foto debe pesar menos de 8 MB.'); continue; }
      try {
        state.parteFotos.push({ nombre: archivo.name, data: await leerArchivoDataUrl(archivo) });
      } catch (err) { toast('No se pudo leer la foto.'); }
    }
    e.target.value = '';
    renderFotosParte();
  });
  renderFotosParte();
  const causas = cat.causas_tiempo || { lluvia: 'Lluvia', falta_material: 'Falta de material', espera: 'Espera / instrucción', falla_equipo: 'Falla de equipo', seguridad: 'Seguridad', otra: 'Otra' };
  const agregarTiempo = () => {
    const cont = $('#parte-tiempos');
    if (!cont) return;
    const fila = document.createElement('div');
    fila.className = 'parte-fila';
    fila.dataset.tiempo = '1';
    fila.innerHTML = `
      <label class="parte-campo">Horas<input type="number" min="0" max="24" step=".25" value="1" data-t-horas></label>
      <label class="parte-campo">Causa<select data-t-causa>${Object.entries(causas).map(([k, v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join('')}</select></label>
      <label class="parte-campo">Actividad<select data-t-act>${actOpts}</select></label>
      <label class="parte-campo">Nota<input type="text" maxlength="500" data-t-nota placeholder="Qué se detuvo"></label>
      <button class="btn-mini" type="button" data-t-quitar>Quitar</button>`;
    fila.querySelector('[data-t-quitar]')?.addEventListener('click', () => fila.remove());
    cont.appendChild(fila);
  };
  $('#parte-tiempo-add')?.addEventListener('click', agregarTiempo);
  renderHistorialPartes(p);

  $('#parte-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const accion = e.submitter?.value === 'cerrar' ? 'cerrar' : 'guardar';
    if (!p.permisos?.editar) { toast('Tu usuario no puede editar esta obra.'); return; }
    const fecha = String($('#parte-fecha')?.value || '');
    if (!fecha) { toast('Elige la fecha del parte.'); return; }
    const seleccionadas = $$('#parte-form [data-act]:checked');
    if (!seleccionadas.length) { toast('Selecciona al menos una actividad.'); return; }
    const actividades = [];
    const avance = {};
    for (const c of seleccionadas) {
      const id = Number(c.dataset.act);
      actividades.push(id);
      avance[id] = Math.max(0, Math.min(100, Number($(`#parte-form [data-avance="${id}"]`)?.value || 0)));
    }
    const personalId = [], personalHoras = [], personalExtra = [], personalActividad = [];
    $$('#parte-form [data-personal]:checked').forEach((c) => {
      const id = Number(c.dataset.personal);
      const horas = Number($(`#parte-form [data-horas="${id}"]`)?.value || 0);
      if (horas <= 0) return;
      personalId.push(id);
      personalHoras.push(horas);
      personalExtra.push(Number($(`#parte-form [data-extra="${id}"]`)?.value || 0));
      personalActividad.push(Number($(`#parte-form [data-personal-act="${id}"]`)?.value || 0));
    });
    const equipoId = [], equipoHoras = [], equipoActividad = [], equipoOperador = [];
    $$('#parte-form [data-equipo]:checked').forEach((c) => {
      const id = Number(c.dataset.equipo);
      const horas = Number($(`#parte-form [data-equipo-horas="${id}"]`)?.value || 0);
      if (horas <= 0) return;
      equipoId.push(id);
      equipoHoras.push(horas);
      equipoActividad.push(Number($(`#parte-form [data-equipo-act="${id}"]`)?.value || 0));
      equipoOperador.push(String($(`#parte-form [data-operador="${id}"]`)?.value || '').trim());
    });
    const materialId = [], materialCantidad = [], materialTipo = [], materialUnidad = [];
    $$('#parte-form [data-material]:checked').forEach((c) => {
      const id = Number(c.dataset.material);
      const cantidad = Number($(`#parte-form [data-material-cant="${id}"]`)?.value || 0);
      if (cantidad <= 0) return;
      materialId.push(id);
      materialCantidad.push(cantidad);
      materialTipo.push(String($(`#parte-form [data-material-tipo="${id}"]`)?.value || 'consumido'));
      materialUnidad.push(String(c.dataset.unidad || ''));
    });
    const tiempoHoras = [], tiempoCausa = [], tiempoActividad = [], tiempoNota = [];
    $$('#parte-form [data-tiempo]').forEach((fila) => {
      const horas = Number(fila.querySelector('[data-t-horas]')?.value || 0);
      if (horas <= 0) return;
      tiempoHoras.push(horas);
      tiempoCausa.push(String(fila.querySelector('[data-t-causa]')?.value || 'otra'));
      tiempoActividad.push(Number(fila.querySelector('[data-t-act]')?.value || 0));
      tiempoNota.push(String(fila.querySelector('[data-t-nota]')?.value || '').trim());
    });
    const charla = String($('#parte-charla')?.value || '').trim();
    const asistentes = Number($('#parte-asistentes')?.value || 0);
    const epp = $('#parte-epp')?.checked ? 1 : 0;
    const parte = {
      fecha,
      turno: String($('#parte-turno')?.value || 'dia'),
      area: String($('#parte-area')?.value || '').trim(),
      supervisor: String($('#parte-supervisor')?.value || '').trim(),
      visitas: String($('#parte-visitas')?.value || '').trim(),
      comentarios: String($('#parte-comentarios')?.value || '').trim(),
      clima_precip_mm: String($('#parte-lluvia')?.value || ''),
      clima_viento_kmh: String($('#parte-viento')?.value || ''),
      clima_tmax: String($('#parte-tmax')?.value || ''),
      clima_tmin: String($('#parte-tmin')?.value || ''),
      actividades,
      avance,
      personal_present: personalId.length ? 1 : 0,
      personal_id: personalId,
      personal_horas: personalHoras,
      personal_extra: personalExtra,
      personal_actividad: personalActividad,
      equipo_present: equipoId.length ? 1 : 0,
      equipo_id: equipoId,
      equipo_horas: equipoHoras,
      equipo_actividad: equipoActividad,
      equipo_operador: equipoOperador,
      material_present: materialId.length ? 1 : 0,
      material_id: materialId,
      material_cantidad: materialCantidad,
      material_tipo: materialTipo,
      material_unidad: materialUnidad,
      tiempo_present: tiempoHoras.length ? 1 : 0,
      tiempo_horas: tiempoHoras,
      tiempo_causa: tiempoCausa,
      tiempo_actividad: tiempoActividad,
      tiempo_nota: tiempoNota,
    };
    if (charla || asistentes > 0 || epp) {
      parte.seguridad_present = 1;
      parte.charla_tema = charla;
      parte.charla_asistentes = asistentes;
      parte.epp_ok = epp;
    }
    parte.firma_nombre = String($('#parte-firma-nombre')?.value || parte.supervisor || '').trim();
    if (firma?.dataset.tocada === '1') parte.firma_data = firma.toDataURL('image/png');
    if (state.parteFotos.length) parte.fotos_base64 = state.parteFotos.map((f) => f.data);
    const parteUuid = nuevoUuid();
    parte.offline_uuid = parteUuid;
    await encolar({ tipo: 'parte_diario', proyecto: Number(proj.id), uuid: nuevoUuid(), parte });
    if (accion === 'cerrar') {
      await encolar({ tipo: 'parte_cerrar', proyecto: Number(proj.id), uuid: nuevoUuid(), offline_uuid: parteUuid });
    }
    if (!Array.isArray(p.partes)) p.partes = [];
    p.partes.unshift({
      id: 0, fecha, turno: parte.turno, area: parte.area, supervisor: parte.supervisor,
      estado: accion === 'cerrar' ? 'cerrado' : 'borrador', comentarios: parte.comentarios,
      personal: personalId.length, equipo: equipoId.length, materiales: materialId.length,
      tiempos: tiempoHoras.reduce((a, b) => a + b, 0),
      horas_personal: personalHoras.reduce((a, b) => a + b, 0) + personalExtra.reduce((a, b) => a + b, 0),
      horas_equipo: equipoHoras.reduce((a, b) => a + b, 0),
    });
    toast(accion === 'cerrar' ? 'Parte diario guardado y cerrado.' : 'Parte diario guardado como borrador.');
    irA(state.parteVolver || 'obras');
  });
}

function renderHistorialPartes(p) {
  const cont = $('#parte-historial');
  if (!cont) return;
  const partes = Array.isArray(p.partes) ? p.partes : [];
  if (!partes.length) { cont.innerHTML = ''; return; }
  cont.innerHTML = `<div class="hist-cab"><strong>Partes recientes</strong><span>${partes.length}</span></div>` +
    partes.map((r, i) => `
      <button class="hist-item" type="button" data-hist="${i}">
        <span class="hist-fecha">${esc(fechaLarga(r.fecha))}</span>
        <span class="hist-txt"><strong>${esc(r.area || 'Sin área')} · ${r.turno === 'noche' ? 'Noche' : 'Día'}</strong>
          <small>${Number(r.personal) || 0} personal · ${Number(r.equipo) || 0} equipo · ${Number(r.tiempos) || 0} h muertas</small></span>
        <span class="chip ${r.estado === 'cerrado' ? 'chip-ok' : 'chip-warn'}">${r.estado === 'cerrado' ? 'Cerrado' : 'Borrador'}</span>
      </button>`).join('');
  cont.onclick = (e) => {
    const b = e.target.closest('[data-hist]');
    if (!b || !cont.contains(b)) return;
    const r = partes[Number(b.dataset.hist)];
    if (r) verParteHistorial(r);
  };
}

function verParteHistorial(r) {
  const filas = [
    ['Fecha', fechaLarga(r.fecha)],
    ['Turno', r.turno === 'noche' ? 'Noche' : 'Día'],
    ['Área', r.area || '—'],
    ['Supervisor', r.supervisor || '—'],
    ['Estado', r.estado === 'cerrado' ? 'Cerrado' : 'Borrador'],
    ['Personal', Number(r.personal) || 0],
    ['Horas de personal', (Number(r.horas_personal) || 0) + ' h'],
    ['Equipo', Number(r.equipo) || 0],
    ['Horas de equipo', (Number(r.horas_equipo) || 0) + ' h'],
    ['Materiales', Number(r.materiales) || 0],
    ['Tiempos muertos', (Number(r.tiempos) || 0) + ' h'],
  ];
  abrirModal('Parte del ' + fechaLarga(r.fecha), `
    <div class="hist-detalle">
      ${filas.map(([k, v]) => `<div><span>${esc(k)}</span><strong>${esc(String(v))}</strong></div>`).join('')}
      ${r.comentarios ? `<p class="parte-nota">${esc(r.comentarios)}</p>` : ''}
      <p class="parte-nota">Para editar un parte ya enviado, ábrelo en la web desde el hub de Obras.</p>
    </div>`);
}

function abrirParteDiario(id) {
  const p = state.sync.find((x) => Number(x.proyecto.id) === Number(id));
  if (!p) { toast('No se encontró la obra.'); return; }
  if (!p.permisos?.editar) { toast('Tu usuario no puede editar esta obra.'); return; }
  state.parteVolver = state.tab === 'parte' ? 'obras' : state.tab;
  state.proyectoId = Number(projIdDe(p));
  cacheGuardar('proyecto', String(state.proyectoId));
  cerrarModal();
  renderParteDiario(p);
  irA('parte');
  setTimeout(() => {
    const vista = $('#vista');
    if (vista) vista.scrollTo({ top: 0, behavior: 'auto' });
    window.scrollTo(0, 0);
  }, 250);
}

function projIdDe(p) {
  return p?.proyecto?.id || 0;
}

function renderObras() {
  const grid = $('#obras-grid');
  if (!grid) return;
  if (!state.sync.length) {
    grid.innerHTML = '<div class="aviso"><svg class="ico"><use href="#i-obra"/></svg><p>No hay obras descargadas. Conecta tu cuenta para verlas.</p></div>';
    return;
  }
  grid.innerHTML = state.sync.map((p) => {
    const e = estadoProyecto(p);
    const progreso = Math.max(0, Math.min(100, Math.round(Number(p.proyecto.progreso) || 0)));
    const atras = (p.actividades || []).filter((a) => !Number(a.hito) && Number(a.progreso) < 100 && a.fecha_fin && a.fecha_fin < hoyISO()).length;
    return `
      <article class="obra-mini" data-proyecto="${Number(p.proyecto.id)}" tabindex="0" role="button">
        <div class="obra-mini-top"><span class="chip ${e.clase}">${e.texto}</span><strong>${progreso}%</strong></div>
        <h3>${esc(p.proyecto.nombre)}</h3>
        <p>${esc(p.proyecto.codigo || '')} · ${esc(fechaLarga(p.proyecto.fecha_fin))}</p>
        <div class="barra"><i style="width:${progreso}%"></i></div>
        <div class="obra-mini-pie"><span>${atras ? atras + ' actividad(es) atrasada(s)' : 'Sin atrasos'}</span><svg class="ico"><use href="#i-arrow"/></svg></div>
      </article>`;
  }).join('');
  filtrarObras();
}

function filtrarObras() {
  const input = $('#obras-buscar');
  const q = normalizarTexto(input?.value || '').trim();
  $$('#obras-grid .obra-mini').forEach((card) => {
    card.hidden = q !== '' && !normalizarTexto(card.textContent).includes(q);
  });
}

function renderAlertas() {
  const cont = $('#alertas-lista');
  if (!cont) return;
  const p = proyectoActual();
  const recs = p?.recordatorios || [];
  if (!recs.length) {
    cont.innerHTML = '<div class="aviso"><svg class="ico"><use href="#i-check"/></svg><p>No hay recordatorios próximos. Cuando sincronices se actualizarán.</p></div>';
    return;
  }
  cont.innerHTML = recs.map((r) => `
    <article class="alerta-item ${r.nivel === 'critico' ? 'alerta-critica' : ''}">
      <span class="alerta-ico"><svg class="ico"><use href="#${r.tipo === 'punch' ? 'i-alert' : (r.tipo === 'equipo' ? 'i-model' : (r.tipo === 'rfi' ? 'i-chat' : 'i-clock'))}"/></svg></span>
      <div><strong>${esc(r.titulo)}</strong><p>${esc(r.detalle || '')}</p><small>${esc(fechaLarga(r.fecha))} · ${esc(r.tipo)}</small></div>
    </article>`).join('');
}

function renderPerfil() {
  if (!state.usuario) return;
  const esAdmin = String(state.usuario.rol || '') === 'admin';
  $$('[data-solo-admin]').forEach((el) => { el.hidden = !esAdmin; });
  const nombre = $('#perfil-nombre');
  if (nombre) nombre.textContent = state.usuario.nombre || 'Mi cuenta';
  const email = $('#perfil-email');
  if (email) email.textContent = (state.usuario.email || '') + ' · ' + (state.usuario.rol || '');
  const ses = $('#ajustes-sesiones-txt');
  if (ses) ses.textContent = (state.sesiones.length || 1) + ' dispositivo(s) con sesión abierta';
  renderDatosLocales();
  const mod = $('#ajustes-modelo-txt');
  if (mod) {
    const m = state.motor;
    mod.textContent = m.descargando
      ? 'Descargando ' + Math.round(m.pct) + ' %'
      : (m.presente ? (m.nombre || 'Modelo local') + ' · instalado' : (m.nombre || 'Qwen2.5') + ' · pendiente de descargar');
  }
  const chip = $('#ajustes-modelo-chip');
  if (chip) {
    const m = state.motor;
    chip.textContent = m.descargando ? Math.round(m.pct) + ' %' : formatoMB(m.presente ? m.total : (m.total || 2_104_932_768));
    chip.className = 'chip ' + (m.presente ? 'chip-ok' : (m.descargando ? 'chip-ia' : 'chip-warn'));
  }
}

async function renderDatosLocales() {
  const txt = $('#ajustes-datos-txt');
  const chip = $('#ajustes-datos-chip');
  if (!txt) return;
  if (!tieneTauri()) {
    txt.textContent = 'Disponible en la app Android';
    return;
  }
  try {
    const e = await invokeTauri('db_estado');
    state.datosLocales = e;
    txt.textContent = e.cifrada
      ? 'Base local cifrada (SQLCipher) · token en Android Keystore'
      : 'Base local sin cifrar';
    if (chip) {
      chip.textContent = e.cifrada ? 'Protegida' : 'Revisar';
      chip.className = 'chip ' + (e.cifrada ? 'chip-ok' : 'chip-warn');
    }
  } catch (err) {
    txt.textContent = 'No se pudo comprobar la base local.';
  }
}

function verDatosLocales() {
  const e = state.datosLocales || {};
  abrirModal('Seguridad de los datos', `
    <div class="hist-detalle">
      <div><span>Base de datos</span><strong>${e.cifrada ? 'Cifrada (SQLCipher)' : 'Sin cifrar'}</strong></div>
      <div><span>Tamaño</span><strong>${archivoTamano(e.bytes || 0)}</strong></div>
      <div><span>Llave</span><strong>${e.llave_en_archivo ? 'Archivo privado 0600' : 'Android Keystore'}</strong></div>
      <div><span>Token de sesión</span><strong>Android Keystore</strong></div>
    </div>
    <p class="parte-nota">La base guarda la copia de tus obras para trabajar sin conexión. En Android la llave vive en el Keystore del dispositivo; si el Keystore no está disponible se usa un archivo privado de la app como respaldo.</p>
    <p class="parte-nota">Los documentos que abres se guardan en la carpeta privada de la app para el visor. Se borran al cerrar sesión.</p>`);
}

function renderChatEstado() {
  const el = $('#chat-estado');
  if (!el) return;
  const m = state.motor;
  if (m.descargando) {
    el.innerHTML = `<span class="punto"></span> Preparando el asistente · ${Math.round(m.pct)} %`;
    return;
  }
  if (state.conectado && state.actualizado) {
    const f = state.actualizado.slice(0, 10);
    const cuando = f === hoyISO() ? 'hoy' : 'al ' + fechaCorta(f);
    el.innerHTML = `<span class="punto activo"></span> ${navigator.onLine ? 'Datos de ' + cuando : 'Sin conexión · datos de ' + cuando}`;
  } else {
    el.innerHTML = '<span class="punto"></span> Conecta tu cuenta';
  }
}

// ---------- Respuestas locales sobre datos reales ----------


function calcularCambios(antes, despues) {
  if (!antes || !antes.length) return [];
  const out = [];
  const previas = new Map(antes.map((p) => [Number(p.proyecto.id), p]));
  for (const p of despues) {
    const vieja = previas.get(Number(p.proyecto.id));
    if (!vieja) { out.push(`Nueva obra: ${p.proyecto.nombre}.`); continue; }
    const actsViejas = new Map((vieja.actividades || []).map((a) => [Number(a.id), a]));
    for (const a of (p.actividades || [])) {
      const va = actsViejas.get(Number(a.id));
      if (!va) continue;
      if (Number(va.progreso) !== Number(a.progreso)) out.push(`${a.codigo} ${a.nombre}: avance ${va.progreso}% → ${a.progreso}%`);
      if (va.fecha_fin !== a.fecha_fin) out.push(`${a.codigo} ${a.nombre}: fin ${va.fecha_fin} → ${a.fecha_fin}`);
    }
    const punchViejos = new Set((vieja.punch || []).map((x) => Number(x.id)));
    for (const x of (p.punch || [])) if (!punchViejos.has(Number(x.id))) out.push(`Nuevo punch: ${x.titulo}`);
    const rfiViejos = new Set((vieja.rfis || []).map((x) => Number(x.id)));
    for (const x of (p.rfis || [])) if (!rfiViejos.has(Number(x.id))) out.push(`Nuevo RFI: ${x.numero} ${x.asunto}`);
    if (Number(vieja.proyecto.progreso) !== Number(p.proyecto.progreso)) out.push(`${p.proyecto.nombre}: avance ${vieja.proyecto.progreso}% → ${p.proyecto.progreso}%`);
  }
  return out.slice(0, 20);
}

function buscarEnObra(p, consulta) {
  const limpio = normalizarTexto(consulta).replace(/[¿?.,;:]/g, ' ').trim();
  const palabras = limpio.split(/\s+/).filter((w) => w.length > 2);
  if (!palabras.length) return [];
  const fuentes = [
    ['Actividad', p.actividades || [], (a) => `${a.codigo} ${a.nombre} ${a.responsable || ''} ${a.partida_nombre || ''}`, (a) => `${a.codigo} · ${a.nombre} (${Number(a.progreso) || 0} %)`],
    ['Bitácora', p.bitacora || [], (b) => `${b.descripcion || ''} ${b.fecha || ''}`, (b) => `${fechaCorta(b.fecha)} · ${String(b.descripcion || '').slice(0, 130)}`],
    ['Punch', p.punch || [], (x) => `${x.titulo || ''} ${x.descripcion || ''} ${x.ubicacion || ''} ${x.responsable || ''} ${x.categoria || ''}`, (x) => `${x.titulo}${x.ubicacion ? ' · ' + x.ubicacion : ''}`],
    ['RFI', p.rfis || [], (x) => `${x.numero || ''} ${x.asunto || ''} ${x.pregunta || ''} ${x.respuesta || ''} ${x.responsable || ''}`, (x) => `${x.numero} · ${x.asunto}`],
    ['Material', p.materiales || [], (m) => `${m.nombre || ''} ${m.marca || ''} ${m.modelo || ''}`, (m) => `${m.nombre}${m.marca ? ' · ' + m.marca : ''}`],
    ['Archivo', p.archivos || [], (a) => `${a.nombre || ''} ${a.categoria || ''} ${a.extension || ''}`, (a) => `${a.nombre}${a.revision ? ' rev. ' + a.revision : ''}`],
    ['Riesgo', p.riesgos || [], (r) => `${r.titulo || ''} ${r.categoria || ''} ${r.responsable || ''}`, (r) => String(r.titulo || 'Riesgo')],
    ['Equipo', p.equipo || [], (e) => `${e.nombre || ''} ${e.tipo || ''} ${e.marca || ''}`, (e) => `${e.nombre} · ${e.tipo || ''}`],
    ['Seguridad', p.seguridad?.incidentes || [], (i) => `${i.descripcion || ''} ${i.tipo || ''}`, (i) => `${i.tipo || 'Incidente'} · ${i.descripcion || ''}`],
    ['Contrato', p.contratos || [], (c) => Object.values(c).join(' '), (c) => `${c.nombre || c.proveedor || c.numero || 'Contrato'}${c.estado ? ' · ' + c.estado : ''}`],
  ];
  const out = [];
  for (const [tipo, lista, heno, texto] of fuentes) {
    let n = 0;
    for (const item of lista) {
      const h = normalizarTexto(heno(item));
      if (!palabras.every((w) => h.includes(w))) continue;
      out.push(`${tipo}: ${texto(item)}`);
      if (++n >= 3) break;
    }
    if (out.length >= 8) break;
  }
  return out;
}

function sumarHabilesProyecto(fecha, dias, cal) {
  const d = new Date(fecha + 'T00:00:00');
  if (Number.isNaN(d.getTime())) return fecha;
  const festivos = new Set(cal?.festivos || []);
  let acumulado = 0, guarda = 0;
  while (acumulado < Number(dias) && guarda++ < 4000) {
    d.setDate(d.getDate() + 1);
    const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    if (festivos.has(iso)) continue;
    const dow = d.getDay() || 7;
    const factor = Number(cal?.jornada?.[dow] ?? ([1, 2, 3, 4, 5].includes(dow) ? 1 : (dow === 6 ? 0.5 : 0)));
    if (factor > 0) acumulado += factor;
  }
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function simularAtraso(p, texto) {
  const dias = Number((texto.match(/(\d+)\s*d[ií]as?/) || [])[1] || 0);
  const acts = (p.actividades || []).filter((a) => !Number(a.hito) && a.fecha_fin);
  if (!dias) return 'Dime cuántos días: por ejemplo «¿Qué pasa si atraso SUB.0.2 cinco días?». No cambié nada.';
  const t = normalizarTexto(texto);
  let act = acts.find((a) => t.includes(normalizarTexto(a.codigo)));
  if (!act) {
    const palabras = t.replace(/[¿?.,;:]/g, ' ').split(/\s+/).filter((w) => w.length > 4 && !['atraso', 'retraso', 'muevo', 'mover', 'dias', 'pasa', 'simula', 'actividad'].includes(w));
    act = acts.find((a) => palabras.some((w) => normalizarTexto(a.nombre).includes(w)));
  }
  if (!act) return 'No identifiqué la actividad. Usa su código, por ejemplo: «¿Qué pasa si atraso SUB.0.2 5 días?». No cambié nada.';
  const finActual = act.fecha_fin;
  const finNuevo = sumarHabilesProyecto(finActual, dias, p.calendario);
  const finObra = acts.map((a) => a.fecha_fin).sort().pop() || p.proyecto.fecha_fin;
  const eraLaUltima = finActual >= finObra;
  const finObraNuevo = eraLaUltima && finNuevo > finObra ? finNuevo : finObra;
  const posteriores = acts.filter((a) => Number(a.partida_id) === Number(act.partida_id) && a.fecha_inicio > act.fecha_inicio).length;
  return `Simulación (no cambié nada): «${act.codigo} ${act.nombre}» termina hoy ${fechaLarga(finActual)}; con ${dias} día(s) hábil(es) más pasaría a ${fechaLarga(finNuevo)}. ${posteriores ? `En su partida hay ${posteriores} actividad(es) posterior(es) que podrían recorrer su inicio. ` : ''}${eraLaUltima ? `El fin de obra pasaría de ${fechaLarga(finObra)} a ${fechaLarga(finObraNuevo)}. ` : `El fin de obra sigue en ${fechaLarga(finObra)} salvo que la actividad esté en la ruta crítica. `}Confírmalo en Línea de tiempo antes de mover fechas.`;
}

function ultimoMensajeUsuario() {
  const turnos = state.chatTurnos || [];
  for (let i = turnos.length - 1; i >= 0; i--) if (turnos[i].role === 'user' && String(turnos[i].content || '').trim()) return String(turnos[i].content).trim();
  return '';
}

function minutaDelDia(p) {
  const hoy = hoyISO();
  const abiertos = (p.punch || []).filter((x) => ['abierto', 'en_proceso'].includes(x.estado));
  const rfis = (p.rfis || []).filter((x) => x.estado === 'abierto');
  const atras = (p.actividades || []).filter((a) => !Number(a.hito) && Number(a.progreso) < 100 && a.fecha_fin && a.fecha_fin < hoy);
  const recs = (p.recordatorios || []).filter((r) => r.fecha >= hoy).slice(0, 5);
  const lineas = [
    `Minuta · ${p.proyecto.nombre} · ${fechaLarga(hoy)}`,
    `Avance: ${Math.round(Number(p.proyecto.progreso) || 0)} %.`,
    `Punch abiertos: ${abiertos.length}${abiertos.length ? ' (' + abiertos.slice(0, 5).map((x) => `${x.titulo}${x.severidad === 'critica' || x.severidad === 'alta' ? ' [' + x.severidad + ']' : ''}`).join('; ') + ')' : ''}.`,
    `RFI abiertos: ${rfis.length}${rfis.length ? ' (' + rfis.slice(0, 5).map((x) => `${x.numero}: ${x.asunto}`).join('; ') + ')' : ''}.`,
    `Actividades atrasadas: ${atras.length}${atras.length ? ' (' + atras.slice(0, 5).map((a) => `${a.codigo} ${a.nombre}`).join('; ') + ')' : ''}.`,
    `Recordatorios: ${recs.length ? recs.map((r) => `${r.titulo} (${fechaLarga(r.fecha)})`).join('; ') : 'sin pendientes próximos'}.`,
  ];
  return lineas.join('\n');
}

function abrirAccionesChat() {
  const p = proyectoActual();
  if (!p) { toast('Conecta tu cuenta para crear borradores.'); return; }
  const ultimo = ultimoMensajeUsuario();
  abrirModal('Acciones del asistente', `
    <div class="tarea-acciones" style="flex-direction:column;align-items:stretch">
      <button class="btn-mini" type="button" id="acc-punch">Crear punch${ultimo ? ' con la última pregunta' : ''}</button>
      <button class="btn-mini" type="button" id="acc-rfi">Crear RFI${ultimo ? ' con la última pregunta' : ''}</button>
      <button class="btn-mini" type="button" id="acc-minuta">Minuta del día</button>
    </div>
    <p class="parte-nota">Los borradores se abren en el formulario para que los revises antes de guardar. La minuta se puede guardar en la bitácora.</p>`);
  $('#acc-punch')?.addEventListener('click', () => formPunch(p, ultimo ? { titulo: ultimo.slice(0, 120), descripcion: ultimo } : {}));
  $('#acc-rfi')?.addEventListener('click', () => formRfi(p, ultimo ? { asunto: ultimo.slice(0, 120), pregunta: ultimo } : {}));
  $('#acc-minuta')?.addEventListener('click', () => {
    const texto = minutaDelDia(p);
    abrirModal('Minuta del día', `
      <label class="parte-campo">Texto<textarea id="minuta-texto" rows="10">${esc(texto)}</textarea></label>
      <div class="tarea-acciones">
        <button class="btn-principal" type="button" id="minuta-guardar">Guardar en bitácora</button>
        <button class="btn-mini" type="button" id="minuta-copiar">Copiar</button>
      </div>`);
    $('#minuta-copiar')?.addEventListener('click', () => copiarTexto(texto));
    $('#minuta-guardar')?.addEventListener('click', async () => {
      const contenido = String($('#minuta-texto')?.value || '').trim();
      if (!contenido) return;
      await encolar({ tipo: 'bitacora_crear', proyecto: Number(p.proyecto.id), descripcion: contenido, importancia: 'media', fecha: hoyISO() });
      if (!Array.isArray(p.bitacora)) p.bitacora = [];
      p.bitacora.unshift({ id: 0, fecha: hoyISO(), descripcion: contenido, importancia: 'media' });
      cerrarModal();
      toast('Minuta guardada en la bitácora.');
      if (navigator.onLine) sincronizar(true);
    });
  });
}

function hablarTexto(texto) {
  if (!('speechSynthesis' in window)) { toast('La lectura en voz alta no está disponible en este teléfono.'); return; }
  try {
    if (window.speechSynthesis.speaking) { window.speechSynthesis.cancel(); return; }
    const u = new SpeechSynthesisUtterance(String(texto || '').slice(0, 1500));
    u.lang = 'es-MX';
    u.rate = 1;
    window.speechSynthesis.speak(u);
    toast('Leyendo la respuesta…');
  } catch (e) { toast('No se pudo iniciar la lectura.'); }
}

async function copiarTexto(texto) {
  try {
    if (navigator.clipboard?.writeText) { await navigator.clipboard.writeText(String(texto)); toast('Copiado.'); return; }
  } catch (e) {}
  try {
    const ta = document.createElement('textarea');
    ta.value = String(texto); ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
    toast('Copiado.');
  } catch (e) { toast('No se pudo copiar.'); }
}

function respuestaLocal(texto) {
  const p = proyectoActual();
  if (!p) return respuestaDemo(texto);
  const t = texto.toLowerCase();
  const proj = p.proyecto;
  const hoy = hoyISO();
  const abiertos = (p.punch || []).filter((x) => ['abierto', 'en_proceso'].includes(x.estado));
  const rfis = (p.rfis || []).filter((x) => x.estado === 'abierto');
  const atras = (p.actividades || []).filter((a) => !Number(a.hito) && Number(a.progreso) < 100 && a.fecha_fin && a.fecha_fin < hoy);
  const nombre = (x) => String(x.nombre || x.titulo || x.asunto || '').trim();

  if (/(estatus|c[oó]mo va|avance|salud)/.test(t) && !/material/.test(t)) {
    const nivel = p.salud?.nivel === 'critico' ? 'en riesgo' : (p.salud?.nivel === 'aviso' ? 'con avisos' : 'sin alertas graves');
    const ind = (p.salud?.indicadores || []).filter((i) => Number(i.valor) > 0).slice(0, 3).map((i) => `${i.titulo}: ${i.valor}`).join('; ');
    return `${proj.nombre} va al ${Math.round(proj.progreso)} % y está ${nivel}. Hay ${abiertos.length} punch abiertos (${abiertos.filter((x) => ['alta', 'critica'].includes(x.severidad)).length} graves), ${rfis.length} RFI abiertos y ${atras.length} actividades atrasadas.${ind ? ' Indicadores: ' + ind + '.' : ''}`;
  }
  if (/(busca|buscar|encuentra|d[oó]nde est[aá])/.test(t)) {
    const q = t.replace(/^.*?(busca|buscar|encuentra|d[oó]nde est[aá])\s*/, '').replace(/\b(en|la|el|los|las|obra|proyecto|por|favor|del|de)\b/g, ' ').replace(/[¿?.,;:]/g, ' ').replace(/\s+/g, ' ').trim();
    if (!q) return 'Dime qué busco. Puedo rastrear actividades, bitácora, punch, RFI, materiales, archivo, riesgos, equipo y seguridad.';
    const res = buscarEnObra(p, q);
    return res.length
      ? `Encontré ${res.length} coincidencia(s) para «${q}»: ${res.join('; ')}.`
      : `No encontré «${q}» en actividades, bitácora, punch, RFI, materiales, archivo, riesgos, equipo ni seguridad. Prueba con otra palabra o con el código.`;
  }
  if (/(si\s+(atraso|retraso|muevo|mover)|qu[eé] pasa si|simula)/.test(t) && /d[ií]as?/.test(t)) {
    return simularAtraso(p, t);
  }
  if (/(punch|pendiente|corregir)/.test(t)) {
    if (!abiertos.length) return 'No hay punch abiertos en esta obra.';
    return 'Punch abiertos: ' + abiertos.slice(0, 6).map((x) => `${nombre(x)} (${x.severidad}${x.fecha_limite ? ', vence ' + fechaCorta(x.fecha_limite) : ''})`).join('; ') + '.';
  }
  if (/(atras|retras|fecha|cambiar|mover)/.test(t)) {
    if (!atras.length) return 'No veo actividades atrasadas con la fecha de hoy.';
    const lista = atras.slice(0, 6).map((a) => `${a.codigo} ${a.nombre}: fin ${fechaLarga(a.fecha_fin)}, avance ${a.progreso} %`).join('; ');
    return `Estas son las que revisaría primero: ${lista}. No cambiaré fechas; dime cuál quieres mover y la ajustas en la app.`;
  }
  if (/(material|norma|marca|modelo|cumple)/.test(t)) {
    const mats = p.materiales || [];
    const q = t.replace(/[¿?]/g, ' ').replace(/\b(qué|que|cumple|con|la|el|norma|normativa|material|marca|modelo|busca|buscar)\b/g, ' ').trim();
    const encontrados = mats.filter((m) => {
      const heno = `${m.nombre} ${m.marca} ${m.modelo}`.toLowerCase();
      return q.length > 2 ? q.split(/\s+/).some((w) => w.length > 2 && heno.includes(w)) : false;
    }).slice(0, 5);
    const lista = (encontrados.length ? encontrados : mats.slice(0, 3)).map((m) => {
      const est = m.verificacion_estado ? `verificación: ${m.verificacion_estado}` : 'sin verificación registrada';
      const norm = (m.normativa || [])[0];
      return `${m.nombre}${m.marca ? ' · ' + m.marca : ''}${m.modelo ? ' ' + m.modelo : ''} (${est}${norm ? '; ' + (norm.documento || norm.referencia || 'normativa') + (norm.clausula ? ' ' + norm.clausula : '') : ''})`;
    }).join('. ');
    return lista ? 'Materiales: ' + lista + '.' : 'No encontré materiales en la copia descargada.';
  }
  if (/(semana|hoy|record|mantenimiento|evento|vence)/.test(t)) {
    const recs = (p.recordatorios || []).filter((r) => r.fecha >= hoy).slice(0, 7);
    if (!recs.length) return 'No hay recordatorios próximos en la copia descargada.';
    return 'Próximos recordatorios: ' + recs.map((r) => `${r.titulo} (${fechaLarga(r.fecha)})`).join('; ') + '.';
  }
  if (/(incidente|seguridad|accidente|riesgo|ats)/.test(t)) {
    const inc = (p.seguridad?.incidentes || []).filter((x) => x.estado === 'abierto');
    const ats = (p.seguridad?.ats_pendientes || []).length;
    return `Seguridad: ${inc.length} incidente(s) abierto(s) y ${ats} ATS pendiente(s). ${inc.length ? 'El más reciente: ' + (inc[0].descripcion || inc[0].tipo) + '.' : 'Sin incidentes abiertos.'}`;
  }
  if (/(cambi|actualiz|sincron)/.test(t)) {
    if (state.cambios.length) return 'Cambios desde la última sincronización: ' + state.cambios.slice(0, 8).join('; ') + '.';
    return `No detecté cambios desde la última sincronización. Los datos son de ${fechaLarga(state.actualizado || hoy)}. ${navigator.onLine ? 'Puedes sincronizar ahora desde Ajustes.' : 'Estás sin conexión; al volver se actualizarán.'}`;
  }
  if (/(reuni|junta|prepar)/.test(t)) {
    const crit = abiertos.filter((x) => ['alta', 'critica'].includes(x.severidad)).slice(0, 4).map(nombre);
    const rfi = rfis.slice(0, 4).map((x) => `${x.numero}: ${x.asunto}`);
    const ret = atras.slice(0, 4).map((a) => `${a.codigo} ${a.nombre} (${a.progreso}%)`);
    return `Para la reunión: ${crit.length ? 'punch crítico: ' + crit.join(', ') + '. ' : ''}${rfi.length ? 'RFI abiertos: ' + rfi.join(', ') + '. ' : ''}${ret.length ? 'actividades atrasadas: ' + ret.join(', ') + '. ' : ''}${(!crit.length && !rfi.length && !ret.length) ? 'No hay pendientes críticos en la copia descargada.' : ''}`;
  }
  if (/(portafolio|todas las obras|comparar)/.test(t)) {
    return 'Portafolio: ' + state.sync.map((x) => `${x.proyecto.nombre} ${Math.round(x.proyecto.progreso)}% (${(x.punch || []).filter((q) => ['abierto', 'en_proceso'].includes(q.estado)).length} punch, ${(x.rfis || []).filter((q) => q.estado === 'abierto').length} RFI)`).join('; ') + '.';
  }
  if (/(glosario|qu[eé] significa|definici[oó]n|qu[eé] es un)/.test(t)) {
    return 'Glosario rápido: RFI = solicitud de información; Punch = pendiente de corrección; Lookahead = planeación de las próximas semanas; Last Planner = compromisos semanales del equipo; PPC = porcentaje de compromisos cumplidos; SPI = avance real contra el programado; CPI = valor ganado contra el costo; ATS = análisis de trabajo seguro; NC = no conformidad; ITP = plan de inspección y pruebas.';
  }
  return `Puedo revisar ${proj.nombre}: avance, punch, RFI, actividades atrasadas, seguridad, materiales, recordatorios, qué cambió, preparar la reunión y el portafolio. También puedo buscar en toda la obra («busca fuga»), simular un atraso («si atraso SUB.0.2 5 días»), armar la minuta del día y preparar borradores de punch o RFI.`;
}

function respuestaDemo(texto) {
  const t = texto.toLowerCase();
  if (/(estatus|c[oó]mo va|avance|salud)/.test(t)) return 'La obra va al 62 %. Hay 5 días de atraso acumulados y 3 punch críticos. El próximo hito es la losa del nivel 4.';
  if (/(punch|pendiente|corregir)/.test(t)) return 'Quedan 3 punch críticos: barandal del nivel 3, sello en fachada poniente y prueba de bomba. El más urgente es el barandal, asignado a Luis Méndez.';
  if (/(atras|retras|fecha|cambiar|mover)/.test(t)) return 'Las actividades con más atraso son "Colado de losa nivel 4" (3 días) e "Instalación eléctrica torre A" (2 días). Te propongo mover la primera al 24 de noviembre; no cambiaré nada sin que lo confirmes.';
  if (/(material|norma|marca|modelo|cumple)/.test(t)) return 'Encontré el Cable THW-90 de Condumex, calibre 12 AWG. Su verificación está aprobada contra la NOM-063-SCFI; la ficha está al día.';
  if (/(semana|hoy|record|mantenimiento|evento)/.test(t)) return 'Hoy tienes: revisión de seguridad a las 08:00, colado de losa a las 11:30 y mantenimiento de grúa a las 16:00. Mañana vence el RFI-018.';
  if (/(incidente|seguridad|accidente|riesgo)/.test(t)) return 'Hay 2 incidentes abiertos y 1 ATS pendiente de firma. Ninguno es grave; el más reciente es un acto inseguro en el nivel 3.';
  return 'Conecta tu cuenta para responder con los datos reales de tus obras. Mientras tanto, esta es una vista previa de lo que podré hacer.';
}

function burbuja(texto, tipo, hint) {
  const wrap = document.createElement('div');
  wrap.className = 'msg ' + (tipo === 'user' ? 'msg-user' : 'msg-bot');
  if (tipo !== 'user') {
    const img = document.createElement('img');
    img.src = 'assets/bot.svg'; img.alt = ''; img.className = 'msg-avatar';
    wrap.appendChild(img);
  }
  const b = document.createElement('div');
  b.className = 'burbuja';
  b.textContent = texto;
  if (hint) {
    const h = document.createElement('div');
    h.className = 'msg-hint';
    h.innerHTML = '<svg class="ico"><use href="#i-model"/></svg>';
    h.appendChild(document.createTextNode(hint));
    b.appendChild(h);
  }
  if (tipo !== 'user' && texto && texto !== 'Escribiendo…') {
    const acciones = document.createElement('div');
    acciones.className = 'msg-acciones';
    const oir = document.createElement('button');
    oir.type = 'button'; oir.className = 'btn-mini'; oir.textContent = 'Escuchar';
    oir.addEventListener('click', () => hablarTexto(texto));
    const copiar = document.createElement('button');
    copiar.type = 'button'; copiar.className = 'btn-mini'; copiar.textContent = 'Copiar';
    copiar.addEventListener('click', () => copiarTexto(texto));
    acciones.append(oir, copiar);
    b.appendChild(acciones);
  }
  wrap.appendChild(b);
  return wrap;
}

function guardarMensajeChat(rol, contenido, extra = {}) {
  const p = proyectoActual();
  if (!p || !state.token) return;
  encolar({
    tipo: 'chat_mensaje',
    proyecto: Number(p.proyecto.id),
    conversacion_id: state.conversacionId || 0,
    rol,
    contenido,
    ...extra,
    uuid: nuevoUuid(),
  });
}

function actividadTexto(a) {
  const partes = [`${a.codigo || ''} ${a.nombre || ''}`.trim()];
  partes.push(`inicio ${a.fecha_inicio || '—'}, fin ${a.fecha_fin || '—'}, avance ${Number(a.progreso) || 0} %`);
  if (a.responsable) partes.push('responsable ' + a.responsable);
  if (a.restriccion_tipo && a.restriccion_tipo !== 'ninguna') partes.push('restricción ' + a.restriccion_tipo);
  const activas = (a.restricciones_activas || []).map((r) => r.descripcion).filter(Boolean);
  if (activas.length) partes.push('bloqueos: ' + activas.slice(0, 2).join('; '));
  return partes.join(', ');
}

function contextoObra(pregunta = '') {
  const p = proyectoActual();
  const hoy = hoyISO();
  const normal = (v) => String(v || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const t = normal(pregunta);
  const pide = (re) => re.test(t);
  const lineas = [`Fecha de hoy: ${hoy}.`];
  if (!p) {
    lineas.push('No hay obras descargadas en el teléfono.');
    return lineas.join('\n');
  }
  const proj = p.proyecto;
  lineas.push(`Obra activa: ${proj.nombre} (${proj.codigo || 'sin código'}), avance ${Number(proj.progreso) || 0} %, del ${proj.fecha_inicio || '—'} al ${proj.fecha_fin || '—'}.`);

  const acts = p.actividades || [];
  const abiertas = acts.filter((a) => !Number(a.hito) && Number(a.progreso) < 100);
  const atras = abiertas.filter((a) => a.fecha_fin && a.fecha_fin < hoy)
    .sort((a, b) => String(a.fecha_fin).localeCompare(String(b.fecha_fin)));
  const proximas = abiertas.filter((a) => a.fecha_inicio >= hoy)
    .sort((a, b) => String(a.fecha_inicio).localeCompare(String(b.fecha_inicio)));
  const punch = (p.punch || []).filter((x) => ['abierto', 'en_proceso'].includes(x.estado));
  const rfis = (p.rfis || []).filter((x) => x.estado === 'abierto');
  const salud = p.salud || {};
  lineas.push(`Resumen: ${abiertas.length} actividades sin terminar, ${atras.length} atrasadas, ${punch.length} punch abiertos, ${rfis.length} RFI abiertos.`);
  if (salud.nivel) {
    const ind = (salud.indicadores || []).filter((i) => Number(i.valor) > 0).slice(0, 3)
      .map((i) => `${i.titulo}: ${i.valor}`).join('; ');
    lineas.push(`Salud de la obra: ${salud.nivel}.${ind ? ' ' + ind + '.' : ''}`);
  }

  const temas = {
    punch: pide(/(punch|pendiente|corregir|acabado|detalle|observacion)/),
    atrasos: pide(/(atras|retras)/),
    fechas: pide(/(fecha|cambiar|mover|programa|actividad|avance|estatus|como va)/),
    material: pide(/(material|marca|modelo|norma|cumple|especifica|ficha|proveedor)/),
    seguridad: pide(/(seguridad|incidente|accidente|riesgo|ats|permiso|epp)/),
    equipo: pide(/(equipo|maquina|grua|mantenimiento|servicio|recordatorio|evento|vence|calendario|semana|hoy)/),
    reunion: pide(/(reuni|junta|prepar|resumen ejecutivo)/),
    portafolio: pide(/(portafolio|todas las obras|comparar|otras obras)/),
    cambios: pide(/(cambi|actualiz|sincroniz)/),
  };
  const general = !Object.values(temas).some(Boolean);

  if ((temas.atrasos || temas.fechas || temas.reunion || general) && atras.length) {
    lineas.push('Actividades atrasadas: ' + atras.slice(0, 8).map(actividadTexto).join(' | ') + '.');
  }
  const soloAtrasos = temas.atrasos && !temas.fechas && !temas.reunion && !general;
  if (!soloAtrasos && (temas.fechas || temas.reunion || general) && proximas.length) {
    lineas.push('Próximas por iniciar: ' + proximas.slice(0, 9).map(actividadTexto).join(' | ') + '.');
  }
  if ((temas.punch || temas.reunion || general) && punch.length) {
    lineas.push('Punch: ' + punch.slice(0, 8).map((x) => `${x.titulo} (${x.severidad}, ${x.ubicacion || 'sin ubicación'}${x.fecha_limite ? ', vence ' + x.fecha_limite : ''}${x.responsable ? ', resp. ' + x.responsable : ''})`).join(' | ') + '.');
  }
  if ((temas.reunion || general || pide(/rfi|consulta/)) && rfis.length) {
    lineas.push('RFI: ' + rfis.slice(0, 6).map((x) => `${x.numero || ''} ${x.asunto || ''}${x.fecha_limite ? ' (vence ' + x.fecha_limite + ')' : ''}`).join(' | ') + '.');
  }
  if (temas.equipo || general) {
    const recs = (p.recordatorios || []).filter((r) => r.fecha >= hoy).slice(0, 8);
    if (recs.length) lineas.push('Recordatorios: ' + recs.map((r) => `${r.fecha} ${r.titulo} (${r.nivel})`).join(' | ') + '.');
  }
  if (temas.material || general) {
    const mats = p.materiales || [];
    if (mats.length) {
      const textoMat = (m) => {
        const normas = (m.normativa || []).slice(0, 3).map((n) => (n.documento || n.codigo || n.referencia || '') + (n.clausula ? ' ' + n.clausula : '')).filter(Boolean);
        return `${m.nombre}${m.marca ? ' ' + m.marca : ''}${m.modelo ? ' ' + m.modelo : ''}, ${m.cantidad || ''} ${m.unidad || ''}, verificación ${m.verificacion_estado || 'sin registro'}${normas.length ? ', norma ' + [...new Set(normas)].join('; ') : ''}`;
      };
      const ignorar = new Set(['que', 'cual', 'como', 'para', 'material', 'materiales', 'marca', 'modelo', 'norma', 'normativa', 'cumple', 'busca', 'buscar', 'este', 'esta', 'hay', 'del', 'los', 'las', 'con', 'por', 'una', 'uno', 'sobre', 'dime', 'tiene']);
      const palabras = normal(pregunta).split(/[^a-z0-9]+/).filter((w) => w.length > 2 && !ignorar.has(w));
      const encontrados = palabras.length ? mats.filter((m) => {
        const heno = normal(`${m.nombre || ''} ${m.marca || ''} ${m.modelo || ''} ${(m.normativa || []).map((n) => n.documento || n.codigo || '').join(' ')}`);
        return palabras.some((w) => heno.includes(w));
      }).slice(0, 8) : [];
      if (encontrados.length) lineas.push('Materiales que coinciden: ' + encontrados.map(textoMat).join(' | ') + '.');
      lineas.push(`Materiales en la obra (${mats.length}${encontrados.length ? ', muestra' : ''}): ` + mats.slice(0, 8).map(textoMat).join(' | ') + '.');
    }
  }
  if (temas.seguridad) {
    const archivos = p.archivos || [];
  if (archivos.length) {
    lineas.push('Archivos del proyecto (' + archivos.length + '): ' + archivos.slice(0, 20).map((a) => `${a.nombre}${a.revision ? ' rev. ' + a.revision : ''}`).join(' | ') + '.');
  }

  const seg = p.seguridad || {};
    const inc = (seg.incidentes || []).filter((x) => x.estado === 'abierto');
    lineas.push(`Seguridad: ${inc.length} incidente(s) abierto(s), ${(seg.ats_pendientes || []).length} ATS pendiente(s).`);
    if (inc.length) lineas.push('Incidentes: ' + inc.slice(0, 5).map((x) => `${x.tipo || 'incidente'}: ${x.descripcion || ''} (${x.severidad || 'sin severidad'})`).join(' | ') + '.');
    const riesgos = (p.riesgos || []).slice(0, 6);
    if (riesgos.length) lineas.push('Riesgos: ' + riesgos.map((r) => `${r.titulo} (${r.probabilidad}/${r.impacto})`).join(' | ') + '.');
  }
  if (temas.equipo) {
    const equipo = (p.equipo || []).slice(0, 8);
    if (equipo.length) lineas.push('Equipo: ' + equipo.map((e) => `${e.nombre} (${e.estado}${e.proximo_servicio ? ', servicio ' + e.proximo_servicio : ''})`).join(' | ') + '.');
  }
  if (temas.portafolio) {
    lineas.push('Portafolio: ' + state.sync.map((x) => {
      const p2 = x.proyecto;
      const pn = (x.punch || []).filter((q) => ['abierto', 'en_proceso'].includes(q.estado)).length;
      const rn = (x.rfis || []).filter((q) => q.estado === 'abierto').length;
      return `${p2.nombre} ${Number(p2.progreso) || 0} % (${pn} punch, ${rn} RFI)`;
    }).join(' | ') + '.');
  }
  if (temas.cambios && state.cambios.length) {
    lineas.push('Cambios recientes: ' + state.cambios.slice(0, 8).join('; ') + '.');
  }
  return lineas.join('\n').slice(0, 4200);
}

function esConsultaDeDatos(pregunta) {
  return /(estatus|como va|avance|salud|punch|pendiente|corregir|atras|retras|fecha|material|marca|modelo|norma|cumple|semana|hoy|record|mantenimiento|evento|vence|incidente|seguridad|accidente|riesgo|ats|cambi|actualiz|sincron|portafolio|todas las obras|comparar|reuni|junta|prepar)/.test(normalizarTexto(pregunta));
}

function mensajesParaModelo(pregunta) {
  const partes = [
    'Eres el asistente de obra de Project Web dentro de la app Android. Hablas español de México, claro y breve.',
    'Responde en 2 a 5 frases (máximo 130 palabras), salvo que pidan una lista; entonces usa viñetas cortas.',
    'Usa solo el CONTEXTO y la RESPUESTA BASE. Si un dato no está, dilo con naturalidad; nunca inventes cifras, fechas, nombres, códigos, punch ni normas.',
    'Nombra la obra por su nombre, no solo por su código.',
    'Si aparece RESPUESTA BASE VERIFICADA, es la verdad de los datos: reformúlala sin cambiar cifras, códigos ni nombres. Si dice que no hay punch, dilo así; no confundas códigos de actividad con punch.',
    'Para materiales cita marca, modelo y normativa cuando existan. Para seguridad, sugiere revisar el procedimiento sin dar órdenes peligrosas.',
    'Si te piden cambiar o mover una fecha, propone la fecha y explica el efecto, pero aclara que tú no aplicas cambios: se hacen en la app.',
    'No uses Markdown complicado; texto plano con guiones si hace falta.',
    'Hoy es ' + hoyISO() + '.',
    '',
  ];
  if (esConsultaDeDatos(pregunta)) {
    partes.push('RESPUESTA BASE VERIFICADA (datos exactos de la obra):', respuestaLocal(pregunta), '');
  }
  partes.push('CONTEXTO DESCARGADO EN EL TELÉFONO:', contextoObra(pregunta));
  const sistema = partes.join('\n');
  const previos = state.chatTurnos.slice(0, -1)
    .filter((m) => m.role === 'user' || m.role === 'assistant')
    .slice(-4)
    .map((m) => ({ role: m.role, content: String(m.content || '').slice(0, 1500) }));
  return [{ role: 'system', content: sistema }, ...previos, { role: 'user', content: pregunta }];
}

function respuestaDirecta(pregunta) {
  const t = normalizarTexto(pregunta);
  const base = respuestaLocal(pregunta);
  if (/(glosario|qu[eé] significa|definici[oó]n)/.test(t)) return base;
  if (/(busca|buscar|encuentra|d[oó]nde est[aá])/.test(t)) return base;
  if (/(si\s+(atraso|retraso|muevo|mover)|qu[eé] pasa si|simula)/.test(t) && /d[ií]as?/.test(t)) return base;
  if (/(punch|pendiente|corregir)/.test(t) && /no hay punch/i.test(base)) return base;
  if (/(atras|retras)/.test(t) && /no veo actividades atrasadas/i.test(base)) return base;
  if (/(material|marca|modelo|cumple)/.test(t) && /no encontr[eé] materiales/i.test(base)) return base;
  return '';
}

function respuestaModeloAceptable(pregunta, texto) {
  const t = normalizarTexto(pregunta);
  if (!esConsultaDeDatos(pregunta)) return true;
  const base = respuestaLocal(pregunta);
  if (/(punch|pendiente|corregir)/.test(t) && /no hay punch/i.test(base)) {
    return /(no hay|ning[uú]n|sin punch|cero)/i.test(texto);
  }
  if (/(atras|retras)/.test(t)) {
    const codigosBase = new Set(base.match(/\b[A-Z]{1,4}\.\d+(?:\.\d+)*\b/g) || []);
    const codigosResp = texto.match(/\b[A-Z]{1,4}\.\d+(?:\.\d+)*\b/g) || [];
    if (codigosResp.some((c) => !codigosBase.has(c))) return false;
  }
  if (/(material|marca|modelo|cumple)/.test(t) && /no encontr[eé] materiales/i.test(base)) {
    return !/(encontr[eé]|tengo|hay)\s+(el|la|un)\s+/i.test(texto);
  }
  return true;
}

function actualizarBienvenida() {
  const b = $('#chat-bienvenida');
  if (!b) return;
  if (state.motor.presente) {
    b.textContent = 'Hola. El asistente ya está en tu teléfono: pregúntame por el avance, punch, RFI, materiales o fechas, incluso sin señal.';
  } else if (state.conectado) {
    b.textContent = 'Hola. Ya tengo los datos de tus obras. Prepara el asistente digital (una sola descarga por Wi-Fi) para que pueda responder sin señal.';
  } else {
    b.textContent = 'Hola. Conecta tu cuenta y prepara el asistente digital para que pueda responder sin señal.';
  }
}

function asegurarStream() {
  if (state.stream) return state.stream;
  const cont = $('#chat-mensajes');
  if (!cont) return null;
  const wrap = document.createElement('div');
  wrap.className = 'msg msg-bot';
  const img = document.createElement('img');
  img.src = 'assets/bot.svg'; img.alt = ''; img.className = 'msg-avatar';
  const b = document.createElement('div');
  b.className = 'burbuja';
  const texto = document.createElement('span');
  texto.className = 'texto';
  texto.textContent = 'Analizando los datos…';
  const cursor = document.createElement('span');
  cursor.className = 'cursor';
  b.appendChild(texto);
  b.appendChild(cursor);
  wrap.appendChild(img);
  wrap.appendChild(b);
  cont.appendChild(wrap);
  cont.scrollTop = cont.scrollHeight;
  state.stream = { wrap, burbuja: b, texto, cursor, acumulado: '' };
  return state.stream;
}

function agregarDelta(delta) {
  const s = asegurarStream();
  if (!s) return;
  s.acumulado += delta;
  s.texto.textContent = s.acumulado;
  const cont = $('#chat-mensajes');
  if (cont) cont.scrollTop = cont.scrollHeight;
}

function finalizarStream(texto, hint) {
  const s = state.stream;
  state.stream = null;
  if (!s) return;
  const limpio = String(texto || '').trim();
  if (!limpio) { s.wrap.remove(); return; }
  s.texto.textContent = limpio;
  s.cursor.remove();
  if (hint) {
    const h = document.createElement('div');
    h.className = 'msg-hint';
    h.innerHTML = '<svg class="ico"><use href="#i-model"/></svg>';
    h.appendChild(document.createTextNode(hint));
    s.burbuja.appendChild(h);
  }
  const cont = $('#chat-mensajes');
  if (cont) cont.scrollTop = cont.scrollHeight;
}

function bloquearEnvio(bloqueado) {
  const btn = $('#chat-form .enviar');
  if (!btn) return;
  const icono = btn.querySelector('use');
  if (icono) icono.setAttribute('href', bloqueado ? '#i-stop' : '#i-send');
  btn.setAttribute('aria-label', bloqueado ? 'Detener respuesta' : 'Enviar');
  btn.title = bloqueado ? 'Detener respuesta' : 'Enviar';
  btn.classList.toggle('detener', !!bloqueado);
  btn.disabled = false;
}

async function detenerRespuesta() {
  if (!state.generando) return;
  state.detenido = true;
  toast('Deteniendo…');
  if (tieneTauri()) {
    try { await invokeTauri('motor_detener'); return; } catch (e) {}
  }
  state.generando = false;
  bloquearEnvio(false);
  finalizarStream('', null);
  toast('Respuesta detenida.');
}

function respuestaConReglas(texto, motivo) {
  const cont = $('#chat-mensajes');
  if (!cont) return;
  const typing = burbuja('Escribiendo…', 'bot');
  cont.appendChild(typing);
  cont.scrollTop = cont.scrollHeight;
  setTimeout(() => {
    typing.remove();
    const f = (state.actualizado || hoyISO()).slice(0, 10);
    const base = state.sync.length
      ? 'Asistente digital · datos ' + (f === hoyISO() ? 'de hoy' : 'al ' + fechaCorta(f))
      : 'Asistente digital · datos de ejemplo';
    const hint = motivo ? base + ' · ' + motivo : base;
    const respuesta = respuestaLocal(texto);
    cont.appendChild(burbuja(respuesta, 'bot', hint));
    cont.scrollTop = cont.scrollHeight;
    state.chatTurnos.push({ role: 'assistant', content: respuesta });
    guardarMensajeChat('assistant', respuesta, { datos: { fuente: 'reglas', hint }, modelo: 'reglas-locales' });
  }, 450);
}

async function enviarConModelo(pregunta) {
  state.generando = true;
  state.detenido = false;
  bloquearEnvio(true);
  asegurarStream();
  try {
    await invokeTauri('motor_responder', {
      mensajes: mensajesParaModelo(pregunta),
      maxTokens: 240,
    });
  } catch (e) {
    state.generando = false;
    bloquearEnvio(false);
    finalizarStream('', null);
    respuestaConReglas(pregunta, 'el asistente no respondió');
  }
}

function dictarMensaje() {
  const Reconocimiento = window.SpeechRecognition || window.webkitSpeechRecognition;
  const input = $('#chat-texto');
  if (!Reconocimiento || !input) {
    toast('El dictado no está disponible en este teléfono. Escribe la pregunta.');
    return;
  }
  try {
    const rec = new Reconocimiento();
    rec.lang = 'es-MX';
    rec.interimResults = false;
    rec.maxAlternatives = 1;
    rec.onresult = (ev) => {
      const texto = String(ev.results?.[0]?.[0]?.transcript || '').trim();
      if (texto) { input.value = texto; input.focus(); }
    };
    rec.onerror = () => toast('No se pudo dictar. Revisa el micrófono o escríbelo.');
    rec.onend = () => {};
    rec.start();
    toast('Escuchando… habla ahora.');
  } catch (e) {
    toast('No se pudo iniciar el dictado.');
  }
}

function enviarTexto(texto) {
  const limpio = String(texto || '').trim();
  if (!limpio) return;
  if (state.generando) { toast('Espera a que termine la respuesta.'); return; }
  const cont = $('#chat-mensajes');
  if (!cont) return;
  cont.appendChild(burbuja(limpio, 'user'));
  const input = $('#chat-texto');
  if (input) input.value = '';
  cont.scrollTop = cont.scrollHeight;
  state.chatTurnos.push({ role: 'user', content: limpio });
  state.ultimaPregunta = limpio;
  guardarMensajeChat('user', limpio);

  if (tieneTauri() && state.motor.presente) {
    const directa = respuestaDirecta(limpio);
    if (directa) { respuestaConReglas(limpio, 'dato verificado'); return; }
    enviarConModelo(limpio);
    return;
  }
  respuestaConReglas(limpio, tieneTauri() ? 'el asistente digital aún se está preparando' : '');
}

// ---------- Conexión, sesiones y modales ----------

function abrirModal(titulo, html) {
  const m = $('#modal');
  if (!m) return;
  $('#modal-titulo').textContent = titulo;
  $('#modal-cuerpo').innerHTML = html;
  state.modal = 'generico';
  m.hidden = false;
  document.body.classList.add('modal-abierto');
}

function cerrarModal() {
  const m = $('#modal');
  if (m) m.hidden = true;
  state.modal = null;
  document.body.classList.remove('modal-abierto');
}

function abrirConexion() {
  const c = $('#conexion');
  if (!c) return;
  const srv = $('#login-servidor');
  if (srv && !srv.value) srv.value = state.servidor;
  const em = $('#login-email');
  if (em && state.usuario?.email && !em.value) em.value = state.usuario.email;
  const err = $('#login-error'); if (err) err.textContent = '';
  const salir = $('#login-salir');
  if (salir) salir.hidden = !state.token;
  c.hidden = false;
  document.body.classList.add('modal-abierto');
}

function cerrarConexion() {
  const c = $('#conexion');
  if (c) c.hidden = true;
  document.body.classList.remove('modal-abierto');
}

async function login(ev) {
  ev.preventDefault();
  const err = $('#login-error');
  const btn = $('#login-entrar');
  const servidor = normalizarServidor($('#login-servidor')?.value || '');
  const email = String($('#login-email')?.value || '').trim();
  const password = String($('#login-password')?.value || '');
  if (err) err.textContent = '';
  if (!email || !password) { if (err) err.textContent = 'Escribe tu correo y contraseña.'; return; }
  if (btn) { btn.disabled = true; btn.textContent = 'Conectando…'; }
  try {
    const r = await fetch(servidor + '/api/app/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email, password, dispositivo: { nombre: 'Teléfono', sistema: 'Android', app_version: APP_VERSION } }),
    });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.success) throw new Error(j.error || 'No se pudo iniciar sesión.');
    state.servidor = servidor;
    await guardarSesion(j, servidor);
    const ok = await sincronizar(true);
    cerrarConexion();
    if (ok) toast('Conectado. Obras descargadas.');
    else toast('Conectado, pero no se pudieron descargar las obras.');
  } catch (e) {
    if (err) err.textContent = e.message || 'No se pudo conectar. Revisa el servidor y tu conexión.';
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Entrar y descargar mis obras'; }
  }
}

async function cerrarSesion() {
  try { await apiFetch('/api/app/logout', { method: 'POST' }); } catch (e) {}
  await limpiarSesion();
  cerrarConexion();
  cerrarModal();
  location.reload();
}

async function verSesiones() {
  if (!state.token) { abrirConexion(); return; }
  try {
    const r = await apiFetch('/api/app/sesiones');
    const j = await r.json();
    if (!r.ok || !j.success) throw new Error(j.error || 'No se pudieron cargar las sesiones.');
    state.sesiones = j.sesiones || [];
    const html = state.sesiones.length ? '<div class="modal-lista">' + state.sesiones.map((s) => `
      <div class="modal-item">
        <span class="ajuste-ico"><svg class="ico"><use href="#${s.tipo === 'android' ? 'i-model' : 'i-shield'}"/></svg></span>
        <div><strong>${esc(s.dispositivo || 'Dispositivo')}</strong><small>${esc(s.ip || '')} · ${esc(s.ultimo_acceso ? fechaLarga(s.ultimo_acceso) : '')}</small></div>
        ${s.actual ? '<span class="es-actual">Esta sesión</span>' : `<button class="btn-mini" data-cerrar-sesion="${Number(s.id)}">Cerrar</button>`}
      </div>`).join('') + '</div>' : '<p>No hay sesiones activas.</p>';
    abrirModal('Sesiones y dispositivos', html);
    $$('[data-cerrar-sesion]').forEach((b) => b.addEventListener('click', () => cerrarSesionRemota(Number(b.dataset.cerrarSesion))));
  } catch (e) {
    toast(e.message || 'No se pudieron cargar las sesiones.');
  }
}

async function cerrarSesionRemota(id) {
  try {
    const r = await apiFetch('/api/app/sesiones/cerrar', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id }) });
    const j = await r.json();
    if (!r.ok || !j.success) throw new Error(j.error || 'No se pudo cerrar.');
    toast('Sesión cerrada.');
    cerrarModal();
    await sincronizar(true);
  } catch (e) { toast(e.message || 'No se pudo cerrar la sesión.'); }
}



function actualizarBanner() {
  // El servidor entrega la última versión en login/bootstrap; si el usuario conectó, comparamos.
  const apk = state.servidorInfo?.apk;
  if (!apk) return;
  const hay = Number(apk.versionCode) > APP_VERSION_CODE;
  const banner = $('#banner-actualizacion');
  if (!banner) return;
  banner.hidden = !hay;
  if (!hay) return;
  const txt = $('#banner-texto');
  if (txt) txt.textContent = `Hay una versión nueva (${apk.version}).`;
  const link = $('#banner-link');
  if (link) link.href = normalizarServidor(state.servidor) + '/ajustes';
}



// ---------- Historial del chat ----------

async function cargarChat() {
  const p = proyectoActual();
  if (!p || !state.token) return;
  const pid = Number(p.proyecto.id);
  const key = 'chat_' + pid;
  try {
    if (navigator.onLine) {
      const r = await apiFetch('/api/app/chat?proyecto=' + pid);
      const j = await r.json().catch(() => null);
      if (r.ok && j && j.success) {
        state.conversacionId = Number(j.conversacion_id || 0);
        await cacheGuardar(key, JSON.stringify(j.mensajes || []));
        renderChatMensajes(j.mensajes || []);
        return;
      }
    }
  } catch (e) { /* usa la copia local */ }
  const cached = await cacheLeer(key);
  if (cached) {
    try { renderChatMensajes(JSON.parse(cached)); } catch (e) {}
  }
}

function renderChatMensajes(mensajes) {
  const cont = $('#chat-mensajes');
  if (!cont) return;
  cont.innerHTML = '';
  state.chatTurnos = (mensajes || []).map((m) => ({
    role: m.rol === 'user' ? 'user' : 'assistant',
    content: String(m.contenido || ''),
  }));
  if (!mensajes || !mensajes.length) {
    const bienvenida = burbuja(state.motor.presente
      ? 'Hola. El asistente ya está en tu teléfono: pregúntame por el avance, punch, RFI, materiales o fechas, incluso sin señal.'
      : (state.conectado
        ? 'Hola. Ya tengo los datos de tus obras. Prepara el asistente digital una sola vez para que responda sin conexión.'
        : 'Hola. Puedo revisar tu obra con los datos descargados. Conecta tu cuenta y prepara el asistente digital.'), 'bot');
    const b = bienvenida.querySelector('.burbuja');
    if (b) b.id = 'chat-bienvenida';
    cont.appendChild(bienvenida);
  } else {
    for (const m of mensajes) {
      const esUser = m.rol === 'user';
      const b = burbuja(m.contenido || '', esUser ? 'user' : 'bot');
      if (!esUser) {
        const pie = b.querySelector('.burbuja');
        const meta = document.createElement('div');
        meta.className = 'msg-hint';
        meta.innerHTML = '<svg class="ico"><use href="#i-model"/></svg>';
        const fecha = m.creado ? fechaLarga(String(m.creado).replace(' ', 'T')) : '';
        const fuente = m.datos?.motivo === 'verificacion' ? 'Asistente digital · dato verificado' : 'Asistente digital';
        meta.appendChild(document.createTextNode(fuente + (fecha ? ' · ' + fecha : '')));
        if (pie) pie.appendChild(meta);
      }
      cont.appendChild(b);
    }
  }
  cont.scrollTop = cont.scrollHeight;
}

// ---------- Cambios offline (cola de salida) ----------

async function guardarOutbox() {
  await cacheGuardar('outbox', JSON.stringify(state.outbox));
  actualizarPendientes();
}

function actualizarPendientes() {
  const chip = $('#pendientes-chip');
  if (!chip) return;
  const n = state.outbox.length;
  chip.hidden = n === 0;
  chip.textContent = `${n} pendiente${n === 1 ? '' : 's'} de enviar`;
}

function nuevoUuid() {
  return 'app-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
}

async function encolar(op) {
  op.uuid = op.uuid || nuevoUuid();
  state.outbox.push(op);
  await guardarOutbox();
  if (navigator.onLine && state.token) await enviarPendientes();
}

async function enviarPendientes(silencioso = false) {
  if (state.enviando || !state.token || !navigator.onLine || !state.outbox.length) return;
  state.enviando = true;
  const ops = state.outbox.slice();
  try {
    const llevaFotos = ops.some((o) => o.tipo === 'parte_diario' && Array.isArray(o.parte?.fotos_base64) && o.parte.fotos_base64.length > 0);
    const r = await apiFetch('/api/app/sync', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ operaciones: ops }),
      tiempoLimite: llevaFotos ? 90000 : 25000,
    });
    const j = await r.json().catch(() => ({}));
    if (!r.ok || !j.success) throw new Error(j.error || 'No se pudieron enviar los cambios.');
    const resultados = j.resultados || [];
    const ok = new Set(resultados.filter((x) => x.ok).map((x) => x.uuid));
    const conflictos = resultados.filter((x) => x.datos?.conflicto).length;
    const fallidos = resultados.filter((x) => !x.ok);
    state.outbox = state.outbox.filter((o) => !ok.has(o.uuid));
    await guardarOutbox();
    if (!silencioso) {
      if (fallidos.length) toast(fallidos[0].mensaje || 'No se pudieron guardar algunos cambios.', 5000);
      else if (ok.size) toast(conflictos ? 'Cambios enviados; hubo un conflicto de avance.' : 'Cambios enviados.');
      else toast('No se pudieron enviar los cambios. Se reintentará al sincronizar.');
    }
  } catch (e) {
    if (!silencioso) toast('Sin conexión: los cambios quedan en la cola.');
  } finally {
    state.enviando = false;
  }
}

function verActividades() {
  const p = proyectoActual();
  if (!p) { toast('Conecta tu cuenta para actualizar el avance.'); return; }
  if (!p.permisos?.editar) { toast('Tu usuario no puede editar esta obra.'); return; }
  const acts = (p.actividades || []).filter((a) => !Number(a.hito)).slice(0, 60);
  if (!acts.length) { toast('No hay actividades editables.'); return; }
  const html = `<p class="texto-tenue">El cambio se guarda en el teléfono y se envía al sincronizar.</p><div class="modal-lista">` + acts.map((a) => `
    <div class="modal-item">
      <div><strong>${esc(a.codigo)} · ${esc(a.nombre)}</strong><small>${Number(a.progreso)} % · fin ${esc(fechaLarga(a.fecha_fin))}</small></div>
      <input class="avance-input" type="number" min="0" max="100" step="5" value="${Number(a.progreso)}" aria-label="Avance de ${esc(a.nombre)}" data-avance-id="${Number(a.id)}">
      <button class="btn-mini" type="button" data-guardar-avance="${Number(a.id)}">Guardar</button>
    </div>`).join('') + '</div>';
  abrirModal('Actualizar avance', html);
  $$('[data-guardar-avance]').forEach((b) => b.addEventListener('click', async () => {
    const id = Number(b.dataset.guardarAvance);
    const input = $(`input[data-avance-id="${id}"]`);
    const valor = Math.max(0, Math.min(100, Number(input?.value ?? 0)));
    const act = (p.actividades || []).find((x) => Number(x.id) === id);
    if (!act) return;
    const anterior = Number(act.progreso);
    if (valor === anterior) { toast('El avance no cambió.'); return; }
    act.progreso = valor;
    act.status = valor === 0 ? 'no_iniciada' : (valor >= 100 ? 'completada' : 'en_proceso');
    await encolar({
      tipo: 'actividad_progreso', proyecto: Number(p.proyecto.id), id,
      progreso: valor, base_progreso: anterior,
    });
    cerrarModal();
    renderTodo();
    toast('Avance guardado. Se enviará al sincronizar.');
  }));
}

function nuevaNota() {
  const p = proyectoActual();
  if (!p) { toast('Conecta tu cuenta para guardar una nota.'); return; }
  if (!p.permisos?.editar) { toast('Tu usuario no puede editar esta obra.'); return; }
  abrirModal('Nota rápida', `
    <form id="nota-form" class="modal-form">
      <label>Descripción<textarea id="nota-texto" rows="4" maxlength="5000" required placeholder="Qué pasó, qué falta, a quién se avisó…"></textarea></label>
      <label>Importancia<select id="nota-importancia"><option value="baja">Baja</option><option value="media" selected>Media</option><option value="alta">Alta</option><option value="critica">Crítica</option></select></label>
      <button class="btn-principal" type="submit">Guardar nota</button>
    </form>`);
  $('#nota-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const texto = String($('#nota-texto')?.value || '').trim();
    if (!texto) return;
    const importancia = $('#nota-importancia')?.value || 'media';
    await encolar({ tipo: 'bitacora_crear', proyecto: Number(p.proyecto.id), descripcion: texto, importancia, fecha: hoyISO() });
    if (!p.bitacora) p.bitacora = [];
    p.bitacora.unshift({ id: 0, fecha: hoyISO(), descripcion: texto, importancia });
    cerrarModal();
    toast('Nota guardada. Se enviará al sincronizar.');
  });
}


// ---------- Motor local de IA ----------

async function pedirEstadoMotor() {
  if (!tieneTauri()) { pintarMotor(); return; }
  try {
    const m = await invokeTauri('motor_estado');
    const estabaDescargando = state.motor.descargando;
    state.motor = {
      ...state.motor,
      ...m,
      nombre: m.modeloNombre || state.motor.nombre,
      archivo: m.modeloArchivo || '',
      presente: !!m.modeloPresente,
      cargado: !!m.modeloCargado,
      ramMb: Number(m.ramMb) || state.motor.ramMb,
      total: Number(m.total) || state.motor.total,
    };
    pintarMotor();
    if (estabaDescargando && !state.motor.descargando && state.motor.presente) toast('Asistente digital listo.');
  } catch (e) { /* versión sin motor o error de IPC */ }
}

function pintarMotor() {
  const m = state.motor;
  const card = $('#motor-card');
  if (card) {
    const mostrar = tieneTauri() && !m.presente;
    card.hidden = !mostrar;
    if (mostrar) {
      const pct = Math.max(0, Math.min(100, Math.round(Number(m.pct) || 0)));
      const titulo = $('#motor-card-titulo');
      const texto = $('#motor-card-texto');
      const btn = $('#motor-card-btn');
      const barra = $('#motor-barra');
      const relleno = $('#motor-barra-i');
      const esAdmin = String(state.usuario?.rol || '') === 'admin';
      if (m.descargando) {
        if (titulo) titulo.textContent = 'Preparando el asistente';
        if (texto) texto.textContent = `${pct} %. Puedes seguir preguntando mientras termina.`;
        if (btn) { btn.textContent = 'Cancelar'; btn.dataset.accion = 'cancelar'; btn.disabled = false; }
        if (barra) barra.hidden = false;
        if (relleno) relleno.style.width = pct + '%';
      } else {
        if (titulo) titulo.textContent = m.error ? 'No se pudo preparar' : 'Asistente digital';
        if (texto) texto.textContent = m.error
          ? (esAdmin ? String(m.error) : 'No se pudo preparar el asistente. Reintenta.')
          : 'Se prepara una sola vez. La descarga va por Wi-Fi y después responde sin conexión.';
        if (btn) { btn.textContent = m.error ? 'Reintentar' : 'Preparar'; btn.dataset.accion = 'descargar'; btn.disabled = false; }
        if (barra) barra.hidden = true;
      }
    }
  }
  actualizarBienvenida();
  actualizarModalMotor();
  renderChatEstado();
  renderPerfil();
}

function estadoModeloTexto() {
  const m = state.motor;
  if (m.descargando) return `${Math.round(Number(m.pct) || 0)} % · ${formatoMB(m.bytes)} de ${formatoMB(m.total)}`;
  if (m.presente) return m.cargado ? 'Instalado · cargado en memoria' : 'Instalado y listo';
  if (m.error) return m.error;
  return 'Pendiente de descargar';
}

function abrirModelo() {
  if (!tieneTauri()) { toast('El modelo local está disponible en la app Android.'); return; }
  const m = state.motor;
  const pct = Math.max(0, Math.min(100, Math.round(Number(m.pct) || 0)));
  const ram = m.ramMb ? (m.ramMb / 1024).toFixed(1).replace('.', ',') + ' GB' : '—';
  const accion = m.descargando
    ? '<button class="btn-mini" id="modelo-accion" type="button" data-accion="cancelar">Cancelar descarga</button>'
    : (m.presente
      ? '<button class="btn-mini" id="modelo-accion" type="button" data-accion="eliminar">Eliminar del teléfono</button>'
      : `<button class="btn-principal" id="modelo-accion" type="button" data-accion="descargar">Descargar ${formatoMB(m.total || 2104932768)}</button>`);
  const html = `
    <div class="modelo-ficha">
      <span class="motor-card-ico"><svg class="ico"><use href="#i-model"/></svg></span>
      <div><strong>${esc(m.nombre || 'Qwen2.5')}</strong><span>Elegido solo según la memoria del equipo</span></div>
    </div>
    <p>El asistente usa un modelo que vive en tu teléfono: no manda tus datos a internet y sigue funcionando sin señal. La descarga es de una sola vez, por Wi-Fi, y se reanuda si se interrumpe.</p>
    <dl class="modelo-datos">
      <dt>Memoria del equipo</dt><dd>${ram}</dd>
      <dt>Hilos de cálculo</dt><dd>${Number(m.hilos) || '—'}</dd>
      <dt>Tamaño del modelo</dt><dd>${formatoMB(m.total || 2104932768)}</dd>
      <dt>Estado</dt><dd id="modelo-estado">${esc(estadoModeloTexto())}</dd>
    </dl>
    <div class="barra" id="modelo-barra" ${m.descargando ? '' : 'hidden'}><i id="modelo-barra-i" style="width:${pct}%"></i></div>
    ${m.error ? `<div class="aviso"><svg class="ico"><use href="#i-alert"/></svg><p>${esc(m.error)}</p></div>` : ''}
    <div class="conexion-acciones">${accion}</div>
    <p class="texto-tenue">${m.presente ? 'Instalado en el teléfono' : 'Se guardará en el teléfono'}${Number(m.bytesDisco) ? ' · ' + formatoMB(m.bytesDisco) : ''}.</p>
    <p class="texto-tenue">La descarga va por Wi-Fi. El modelo es Qwen2.5 (licencia Apache 2.0) y se ejecuta con llama.cpp en el teléfono. Si lo eliminas, se libera el espacio al reiniciar la app.</p>`;
  abrirModal('Modelo de IA', html);
  state.modal = 'modelo';
  $('#modelo-accion')?.addEventListener('click', (e) => {
    const accion = e.currentTarget.dataset.accion;
    if (accion === 'cancelar') cancelarDescarga();
    else if (accion === 'eliminar') eliminarModelo();
    else descargarModelo();
  });
}

function abrirAjustesAsistente() {
  const m = state.motor;
  const esAdmin = String(state.usuario?.rol || '') === 'admin';
  const estado = m.presente
    ? (m.nombre || 'Modelo local') + ' instalado'
    : (m.descargando ? 'Descargando ' + Math.round(Number(m.pct) || 0) + ' %' : 'Pendiente de descargar');
  const modeloBoton = esAdmin ? `
      <button class="ajuste-item" type="button" id="asistente-modelo">
        <span class="ajuste-ico"><svg class="ico"><use href="#i-model"/></svg></span>
        <div><strong>Modelo de IA</strong><small>${esc(estado)}</small></div>
        <svg class="ico"><use href="#i-arrow"/></svg>
      </button>` : '';
  abrirModal('Asistente', `
    <div class="modal-lista">${modeloBoton}
      <button class="ajuste-item" type="button" id="asistente-nuevo">
        <span class="ajuste-ico"><svg class="ico"><use href="#i-chat"/></svg></span>
        <div><strong>Nuevo chat</strong><small>Empieza una conversación limpia</small></div>
        <svg class="ico"><use href="#i-arrow"/></svg>
      </button>
    </div>
    <p class="texto-tenue">El historial completo queda en la web; las conversaciones se borran a los 6 meses.</p>`);
  $('#asistente-modelo')?.addEventListener('click', abrirModelo);
  $('#asistente-nuevo')?.addEventListener('click', nuevoChat);
}

async function nuevoChat() {
  const p = proyectoActual();
  state.conversacionId = 0;
  state.chatTurnos = [];
  state.stream = null;
  state.detenido = false;
  state.generando = false;
  bloquearEnvio(false);
  if (p) await cacheBorrar('chat_' + Number(p.proyecto.id));
  cerrarModal();
  renderChatMensajes([]);
  actualizarBienvenida();
  if (p && state.token && navigator.onLine) {
    try {
      const r = await apiFetch('/api/app/chat/nueva', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ proyecto: Number(p.proyecto.id) }),
      });
      const j = await r.json().catch(() => null);
      if (r.ok && j && j.success) state.conversacionId = Number(j.conversacion_id || 0);
    } catch (e) { /* sin conexión: se une al hilo más reciente al sincronizar */ }
  }
  toast('Chat nuevo. La conversación anterior queda en la web.');
}

function actualizarModalMotor() {
  if (state.modal !== 'modelo') return;
  const m = state.motor;
  const pct = Math.max(0, Math.min(100, Math.round(Number(m.pct) || 0)));
  const barra = $('#modelo-barra');
  const relleno = $('#modelo-barra-i');
  const estado = $('#modelo-estado');
  if (barra) barra.hidden = !m.descargando;
  if (relleno) relleno.style.width = pct + '%';
  if (estado) estado.textContent = estadoModeloTexto();
  const btn = $('#modelo-accion');
  if (!btn) return;
  if (m.descargando) {
    btn.textContent = 'Cancelar descarga';
    btn.className = 'btn-mini';
    btn.dataset.accion = 'cancelar';
  } else if (m.presente) {
    btn.textContent = 'Eliminar del teléfono';
    btn.className = 'btn-mini';
    btn.dataset.accion = 'eliminar';
  } else {
    btn.textContent = 'Descargar ' + formatoMB(m.total || 2104932768);
    btn.className = 'btn-principal';
    btn.dataset.accion = 'descargar';
  }
}

async function descargarModelo() {
  if (!tieneTauri()) return;
  if (state.motor.descargando) return;
  state.motor.descargando = true;
  state.motor.error = '';
  pintarMotor();
  try {
    await invokeTauri('motor_descargar');
  } catch (e) {
    state.motor.descargando = false;
    state.motor.error = String(e?.message || e);
    pintarMotor();
    toast('No se pudo iniciar la descarga.');
  }
}

async function cancelarDescarga() {
  if (!tieneTauri()) return;
  try { await invokeTauri('motor_cancelar_descarga'); } catch (e) {}
}

async function eliminarModelo() {
  if (!tieneTauri()) return;
  if (!window.confirm('¿Eliminar el modelo del teléfono? El asistente volverá a usar reglas hasta que lo descargues otra vez.')) return;
  try {
    await invokeTauri('motor_eliminar');
    state.motor.presente = false;
    state.motor.cargado = false;
    state.motor.cargando = false;
    state.motor.pct = 0;
    state.motor.bytes = 0;
    state.motor.error = '';
    pintarMotor();
    await pedirEstadoMotor();
    toast('Modelo eliminado. El espacio se libera al cerrar la app.');
  } catch (e) {
    toast(String(e?.message || e));
  }
}

async function registrarEventosMotor() {
  if (!tieneTauri() || !window.__TAURI__?.event?.listen) return;
  const listen = window.__TAURI__.event.listen;
  try {
    await listen('motor-progreso', (ev) => {
      const d = ev.payload || {};
      state.motor.pct = Number(d.pct) || 0;
      state.motor.bytes = Number(d.bytes) || 0;
      if (Number(d.total)) state.motor.total = Number(d.total);
      if (d.error) {
        state.motor.error = d.mensaje || 'La descarga se interrumpió.';
        state.motor.descargando = false;
      }
      if (state.motor.pct >= 100 && !d.error) {
        state.motor.descargando = false;
        state.motor.presente = true;
      }
      pintarMotor();
    });
    await listen('motor-descarga-fin', async (ev) => {
      const mensaje = String(ev.payload?.mensaje || '');
      await pedirEstadoMotor();
      if (mensaje === 'listo') toast('Asistente digital listo. Ya responde sin conexión.');
      else if (mensaje) toast(mensaje, 5000);
    });
    await listen('motor-cargando', () => {
      state.motor.cargando = true;
      const s = state.stream;
      if (s && !s.acumulado) s.texto.textContent = 'Preparando el asistente…';
      renderChatEstado();
    });
    await listen('motor-token', (ev) => {
      state.motor.cargando = false;
      agregarDelta(String(ev.payload?.delta || ''));
    });
    await listen('motor-fin', (ev) => {
      const d = ev.payload || {};
      const detenido = state.detenido;
      state.detenido = false;
      state.generando = false;
      state.motor.generando = false;
      state.motor.cargado = true;
      state.motor.cargando = false;
      bloquearEnvio(false);
      const texto = String(d.texto || '')
        .replace(/\*\*(.+?)\*\*/g, '$1')
        .replace(/^#{1,4}\s+/gm, '')
        .trim();
      if (!texto) {
        finalizarStream('', null);
        if (detenido) { toast('Respuesta detenida.'); return; }
        respuestaConReglas(state.ultimaPregunta || '', 'respuesta vacía del asistente');
        return;
      }
      if (detenido) toast('Respuesta detenida.');
      if (!respuestaModeloAceptable(state.ultimaPregunta || '', texto)) {
        const base = respuestaLocal(state.ultimaPregunta || '');
        finalizarStream(base, 'Asistente digital · dato verificado');
        state.chatTurnos.push({ role: 'assistant', content: base });
        guardarMensajeChat('assistant', base, {
          datos: { fuente: 'reglas', motivo: 'verificacion' },
          modelo: 'reglas-locales',
        });
        toast('Ajusté la respuesta a los datos de la obra.');
        renderChatEstado();
        return;
      }
      finalizarStream(texto, 'Asistente digital');
      state.chatTurnos.push({ role: 'assistant', content: texto });
      guardarMensajeChat('assistant', texto, {
        datos: { fuente: 'modelo', tokens: Number(d.tokens) || 0, tps: Number(d.tps) || 0 },
        modelo: String(state.motor.archivo || 'qwen2.5').replace('.gguf', ''),
      });
      renderChatEstado();
    });
    await listen('motor-error', (ev) => {
      const mensaje = String(ev.payload?.mensaje || 'El asistente no pudo responder.');
      const detenido = state.detenido;
      state.detenido = false;
      state.generando = false;
      state.motor.generando = false;
      state.motor.cargando = false;
      state.motor.error = mensaje;
      bloquearEnvio(false);
      finalizarStream('', null);
      if (detenido) { toast('Respuesta detenida.'); return; }
      if (/contexto|demasiado larga/i.test(mensaje)) toast('La conversación es muy larga. Inicia un chat nuevo.', 5000);
      respuestaConReglas(state.ultimaPregunta || '', 'el asistente no respondió');
      pintarMotor();
    });
  } catch (e) { /* eventos no disponibles */ }
}

async function iniciarMotor() {
  if (tieneTauri()) await registrarEventosMotor();
  await pedirEstadoMotor();
}

// ---------- Avisos locales ----------

async function notificar(titulo, cuerpo) {
  if (tieneTauri()) {
    try {
      await window.__TAURI__.core.invoke('notificar', { titulo, cuerpo });
      return true;
    } catch (e) { /* sigue al fallback */ }
  }
  try {
    if ('Notification' in window) {
      let permiso = Notification.permission;
      if (permiso === 'default') permiso = await Notification.requestPermission();
      if (permiso === 'granted') { new Notification(titulo, { body: cuerpo, icon: 'assets/bot.svg' }); return true; }
    }
  } catch (e) {}
  toast(titulo + ': ' + cuerpo);
  return false;
}

async function avisarNuevosCriticos() {
  const criticos = [];
  for (const p of state.sync) {
    for (const r of (p.recordatorios || [])) {
      if (r.nivel === 'critico') criticos.push({ id: r.id, titulo: r.titulo, proyecto: p.proyecto?.nombre || '' });
    }
  }
  if (!criticos.length) return;
  let previos = [];
  try { previos = JSON.parse((await cacheLeer('alertas_vistas')) || '[]') || []; } catch (e) { previos = []; }
  const nuevos = criticos.filter((r) => !previos.includes(r.id));
  await cacheGuardar('alertas_vistas', JSON.stringify(criticos.map((r) => r.id).slice(0, 120)));
  if (!previos.length || !nuevos.length) return;   // la primera sync solo guarda la línea base
  const primero = nuevos[0];
  await notificar('Alerta de obra', `${nuevos.length} aviso(s) crítico(s). ${primero.titulo}${primero.proyecto ? ' · ' + primero.proyecto : ''}`);
}

// ---------- Init ----------

function init() {
  aplicarTema(state.tema);
  estadoRed();
  window.addEventListener('online', () => { estadoRed(); if (state.token) sincronizar(true); });
  window.addEventListener('offline', estadoRed);

  $('#tema')?.addEventListener('click', () => aplicarTema(state.tema === 'dark' ? 'light' : 'dark'));
  $('#btn-tema')?.addEventListener('click', () => aplicarTema(state.tema === 'dark' ? 'light' : 'dark'));
  $$('.tab').forEach((b) => b.addEventListener('click', () => irA(b.dataset.tab)));

  // Delegación: funciona también con tarjetas y botones creados al vuelo.
  document.addEventListener('click', (e) => {
    const ir = e.target.closest('[data-ir]');
    if (ir) { irA(ir.dataset.ir); return; }
    const toastEl = e.target.closest('[data-toast]');
    if (toastEl) { toast(toastEl.dataset.toast); return; }
    const proy = e.target.closest('[data-proyecto]');
    if (proy) { abrirObra(Number(proy.dataset.proyecto)); return; }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const ir = e.target.closest?.('[data-ir][role="button"]');
    if (ir) { e.preventDefault(); irA(ir.dataset.ir); }
    const proy = e.target.closest?.('[data-proyecto]');
    if (proy) { e.preventDefault(); abrirObra(Number(proy.dataset.proyecto)); }
  });

  $$('#sugerencias button').forEach((b) => b.addEventListener('click', () => enviarTexto(b.dataset.pregunta || b.textContent)));
  $('#obras-buscar')?.addEventListener('input', filtrarObras);
  $('#parte-volver')?.addEventListener('click', () => irA(state.parteVolver || 'obras'));
  $('#evidencias-volver')?.addEventListener('click', () => irA(state.evidenciasVolver || 'obras'));
  $('#archivo-volver')?.addEventListener('click', () => irA(state.archivoVolver || 'obras'));
  $('#punch-volver')?.addEventListener('click', () => irA(state.punchVolver || 'obras'));
  $('#rfi-volver')?.addEventListener('click', () => irA(state.rfiVolver || 'obras'));
  $('#punch-nuevo')?.addEventListener('click', () => { const p = proyectoActual(); if (p && p.permisos?.editar) formPunch(p); });
  $('#rfi-nuevo')?.addEventListener('click', () => { const p = proyectoActual(); if (p && p.permisos?.editar) formRfi(p); });
  $('#punch-buscar')?.addEventListener('input', () => { const p = proyectoActual(); if (p) renderPunch(p); });
  $('#rfi-buscar')?.addEventListener('input', () => { const p = proyectoActual(); if (p) renderRfi(p); });
  $('#visor-volver')?.addEventListener('click', () => cerrarVisor(true));
  $('#visor-mas')?.addEventListener('click', () => visorZoom(0.35));
  $('#visor-menos')?.addEventListener('click', () => visorZoom(-0.35));
  $('#archivo-buscar')?.addEventListener('input', () => { const p = proyectoActual(); if (p) renderArchivoObra(p); });
  $('#btn-parte')?.addEventListener('click', () => {
    const p = proyectoActual();
    if (p) abrirParteDiario(Number(p.proyecto.id));
    else toast('Conecta tu cuenta para crear un parte diario.');
  });
  $('#chat-form')?.addEventListener('submit', (e) => {
    e.preventDefault();
    if (state.generando) { detenerRespuesta(); return; }
    enviarTexto($('#chat-texto')?.value || '');
  });

  $('#login-form')?.addEventListener('submit', login);
  $('#login-cancelar')?.addEventListener('click', cerrarConexion);
  $('#login-salir')?.addEventListener('click', cerrarSesion);
  $('#btn-conectar')?.addEventListener('click', abrirConexion);
  $('#btn-sync')?.addEventListener('click', () => sincronizar());
  $('#btn-sesiones')?.addEventListener('click', verSesiones);
  $('#btn-modelo')?.addEventListener('click', abrirModelo);
  $('#btn-modelo-chat')?.addEventListener('click', abrirAjustesAsistente);
  $('#motor-card-btn')?.addEventListener('click', (e) => {
    const accion = e.currentTarget.dataset.accion;
    if (accion === 'cancelar') cancelarDescarga();
    else descargarModelo();
  });
  $('#btn-avance')?.addEventListener('click', verActividades);
  $('#btn-datos')?.addEventListener('click', verDatosLocales);
  $('#btn-nota')?.addEventListener('click', nuevaNota);
  $('#btn-voz')?.addEventListener('click', dictarMensaje);
  $('#btn-acciones-chat')?.addEventListener('click', abrirAccionesChat);
  $('#btn-notificar')?.addEventListener('click', async () => { await notificar('Project Web', 'Las notificaciones están activas.'); });
  $('#modal-cerrar')?.addEventListener('click', cerrarModal);
  $$('.modal').forEach((m) => m.addEventListener('click', (e) => { if (e.target === m) { m.hidden = true; state.modal = null; document.body.classList.remove('modal-abierto'); } }));

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && tieneTauri()) pedirEstadoMotor();
  });
  setInterval(() => {
    if (tieneTauri() && state.motor.descargando) pedirEstadoMotor();
  }, 3000);

  iniciarMotor();
  const versionEl = $('#app-version');
  if (versionEl) versionEl.textContent = APP_VERSION;

  cargarSesion();
}

document.addEventListener('DOMContentLoaded', init);
