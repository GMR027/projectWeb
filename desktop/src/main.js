'use strict';
const { app, BrowserWindow, WebContentsView, ipcMain, safeStorage, shell, Notification, dialog } = require('electron');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { crear } = require('./config');
const prov = require('./providers');
const upd = require('./updater');
const hw = require('./hardware');
const programador = require('./programador');
const inconsistencias = require('./inconsistencias');
const analisisMateriales = require('./analisis-materiales');
const inteligentes = require('./materiales-inteligentes');
const capacidad = require('./capacidad');
const catalogo = require('./herramientas');
const informes = require('./informes');
const voz = require('./voz');
const chat = require('./chat');
const ensamblador = require('./ensamblador');
const ordenarIA = require('./ordenarIA');
const tablaDocumento = require('./tablaDocumento');
const ocrLocal = require('./ocr');

// AppImage no puede instalar chrome-sandbox con setuid: si el sistema restringe los user namespaces, Chromium abortaría
if (process.env.APPIMAGE) {
  const fs = require('fs');
  const leer = f => { try { return fs.readFileSync(f, 'utf8').trim(); } catch { return ''; } };
  if (leer('/proc/sys/kernel/apparmor_restrict_unprivileged_userns') === '1' || leer('/proc/sys/kernel/unprivileged_userns_clone') === '0') app.commandLine.appendSwitch('no-sandbox');
}
if (!app.requestSingleInstanceLock()) app.quit();

// Medidor de uso de la IA: las tareas que llaman al modelo corren dentro de prov.conUso y cada llamada se informa al servidor (por lotes)
const HERRAMIENTA_USO = { 'prog:ejecutar': 'programador', 'inc:ejecutar': 'inconsistencias', 'mat:ejecutar': 'materiales', 'mi:verificar': 'inteligentes', 'chat:enviar': 'chat', 'ord:propuesta': 'ordenar', 'ens:mensaje': 'ensamblador', 'gen:ejecutar': 'informes' };
const handleOriginal = ipcMain.handle.bind(ipcMain);
ipcMain.handle = (canal, fn) => handleOriginal(canal, !HERRAMIENTA_USO[canal] ? fn : (e, ...a) => {
  const gen = canal === 'gen:ejecutar', id = parseInt(gen ? a[1] : (a[0] && typeof a[0] === 'object' ? a[0].pid : a[0]), 10) || 0;
  return prov.conUso({ herramienta: gen ? 'informe:' + String(a[0]).slice(0, 40) : HERRAMIENTA_USO[canal], proyectoId: id }, () => fn(e, ...a));
});
let cfg, win, vista, cancelarProg = false, ejecutandoProg = false, cancelarInc = false, ejecutandoInc = false, cancelarMat = false, ejecutandoMat = false, cancelarMI = false, cancelarGen = false, ejecutandoGen = false, proyectoVigilado = null, timerVig = null, vistaChat = null, chatAbierto = false, sesionActiva = false, errorWebMain = false, ejecutandoChat = false, nombresProyectos = { t: 0, mapa: {} }, cacheFichas = { t: 0, lista: [] }, descargaOllama = null, alNavegar = null, verificandoMI = false;
const intentosMI = {};   // proyecto → última verificación automática (evita repetirla en cada clic)

// Zoom de la vista de Project Web (Ctrl +, Ctrl -, Ctrl 0 o desde la interfaz)
function cambiarZoom(fn) {
  const z = cfg.guardarZoom(fn(cfg.leer().zoom));
  vista?.webContents.setZoomFactor(z / 100);
  win?.webContents.send('web:zoom', z);
  return z;
}
function atajosZoom(wc) {
  wc.on('before-input-event', (e, i) => {
    if (i.type !== 'keyDown' || !(i.control || i.meta)) return;
    if (i.key === '+' || i.key === '=') { e.preventDefault(); cambiarZoom(z => z + 10); }
    else if (i.key === '-' || i.key === '_') { e.preventDefault(); cambiarZoom(z => z - 10); }
    else if (i.key === '0') { e.preventDefault(); cambiarZoom(() => 100); }
  });
}

function ventana() {
  win = new BrowserWindow({
    width: 1000, height: 760, minWidth: 760, minHeight: 560, title: 'Project Web Desktop', icon: path.join(__dirname, 'icon.png'), backgroundColor: '#0f172a',
    webPreferences: { preload: path.join(__dirname, 'preload.js'), sandbox: true, contextIsolation: true, nodeIntegration: false },
  });
  win.setMenuBarVisibility(false);
  // El micrófono solo lo puede usar la ventana propia de la app (la grabadora), nunca el contenido web
  win.webContents.session.setPermissionRequestHandler((wc, permiso, cb) => cb(permiso === 'media' && wc === win.webContents));
  win.webContents.session.setPermissionCheckHandler((wc, permiso) => permiso === 'media' && !!wc && wc === win.webContents);
  win.webContents.setWindowOpenHandler(({ url }) => { if (/^https?:/.test(url)) shell.openExternal(url); return { action: 'deny' }; });
  win.webContents.on('will-navigate', e => e.preventDefault());
  win.loadFile(path.join(__dirname, 'renderer', 'index.html'));
  crearVista();
  crearChat();
  win.on('resize', ajustarChat);
}

// «Pregunta a tu proyecto»: una capa propia encima de la vista web (la vista web es un panel nativo y taparía cualquier botón del DOM de la ventana)
function crearChat() {
  vistaChat = new WebContentsView({ webPreferences: { preload: path.join(__dirname, 'chat', 'chat-preload.js'), sandbox: true, contextIsolation: true, nodeIntegration: false } });
  vistaChat.setBackgroundColor('#00000000');
  win.contentView.addChildView(vistaChat);
  const wc = vistaChat.webContents;
  wc.setWindowOpenHandler(() => ({ action: 'deny' }));
  wc.on('will-navigate', e => e.preventDefault());
  wc.loadFile(path.join(__dirname, 'chat', 'chat.html'));
  ajustarChat();
}
function ajustarChat() {
  if (!vistaChat || !win || win.isDestroyed()) return;
  const { width: W, height: H } = win.getContentBounds();
  vistaChat.setVisible(sesionActiva && !errorWebMain);
  if (chatAbierto) { const w = Math.max(280, Math.min(420, W - 32)), h = Math.max(300, Math.min(620, H - 96)); vistaChat.setBounds({ x: W - w - 16, y: H - h - 16, width: w, height: h }); vistaChat.setBorderRadius(14); }
  else { vistaChat.setBounds({ x: W - 72, y: H - 72, width: 56, height: 56 }); vistaChat.setBorderRadius(28); }
}

// El contenido de Project Web (la nube) se muestra en una vista aparte; la sesión se conserva entre ejecuciones
function crearVista() {
  vista = new WebContentsView({ webPreferences: { partition: 'persist:pw-web', sandbox: true, contextIsolation: true, nodeIntegration: false } });
  win.contentView.addChildView(vista);
  vista.setVisible(false);
  const wc = vista.webContents;
  const ses = wc.session, ua = ses.getUserAgent() + ' ProjectWebDesktop';
  ses.setUserAgent(ua);   // el servidor lo usa para conservar la sesión
  wc.setUserAgent(ua);    // en Electron recientes la página conserva el UA original si solo se cambia el de la sesión: login y API deben llevar el mismo UA (guardan la sesión en la misma carpeta)
  const fetchOriginal = ses.fetch.bind(ses);   // session.fetch no hereda el User-Agent de la sesión: se pone a mano
  ses.fetch = (u, o = {}) => { const h = new Headers(o.headers || {}); if (!h.has('user-agent')) h.set('user-agent', ua); return fetchOriginal(u, { ...o, headers: h }); };
  atajosZoom(wc); atajosZoom(win.webContents);
  const mismoOrigen = u => { try { return new URL(u).origin === new URL(cfg.leer().servidor).origin; } catch { return false; } };
  const externo = u => { if (/^https?:/.test(u)) shell.openExternal(u); };
  wc.setWindowOpenHandler(({ url }) => { if (mismoOrigen(url)) wc.loadURL(url); else externo(url); return { action: 'deny' }; });
  wc.on('will-navigate', (e, u) => { if (!mismoOrigen(u)) { e.preventDefault(); externo(u); } });
  const avisar = () => {
    try { const p = parseInt(new URL(wc.getURL()).searchParams.get('proyecto'), 10); if (p > 0) proyectoVigilado = p; win?.webContents.send('web:proyecto', p > 0 ? p : null); } catch { /* sin URL todavía */ }
  };
  // Solo el login se muestra hasta que el usuario inicia sesión; si el servidor no responde se avisa para poder corregirlo
  const sesion = () => { try { const ok = new URL(wc.getURL()).pathname !== '/login'; sesionActiva = ok; errorWebMain = false; ajustarChat(); win?.webContents.send('web:sesion', ok); } catch { /* sin URL todavía */ } };
  // En la app el menú es la hamburguesa de la cabecera: se oculta el botón flotante de la web y se aplica el zoom elegido
  const preparar = () => { wc.insertCSS('.btn-menu-movil { display: none !important; }').catch(() => {}); wc.setZoomFactor(cfg.leer().zoom / 100); };
  wc.on('dom-ready', preparar);
  wc.on('did-navigate', sesion);
  wc.on('did-fail-load', (_e, codigo, desc, _u, principal) => { if (principal && codigo !== -3) { errorWebMain = true; ajustarChat(); win?.webContents.send('web:error', desc); } });
  wc.on('did-navigate', avisar);
  wc.on('did-navigate', () => alNavegar?.(wc.getURL()));
  wc.on('did-navigate-in-page', avisar);
}

app.whenReady().then(() => {
  cfg = crear(app.getPath('userData'), safeStorage);
  prov.usoConfigurar(path.join(app.getPath('userData'), 'uso-claude.json'));
  ipcMain.handle('web:ir', (_e, ruta) => {
    const srv = cfg.leer().servidor;
    if (!srv) throw new Error('Configura primero el servidor de Project Web (Servidor y actualizaciones).');
    const r = String(ruta || '/dashboard');
    if (!r.startsWith('/') || r.startsWith('//')) throw new Error('Ruta no válida');
    return vista.webContents.loadURL(srv + r);
  });
  ipcMain.handle('web:marco', (_e, r, visible) => {
    const n = v => Math.max(0, Math.round(Number(v) || 0));
    vista.setBounds({ x: n(r.x), y: n(r.y), width: n(r.width), height: n(r.height) });
    vista.setVisible(!!visible);
  });
  ipcMain.handle('prog:ejecutar', async (_e, proyectoId, provId) => {
    if (ejecutandoProg) throw new Error('Ya hay un análisis en curso.');
    const pid = parseInt(proyectoId, 10);
    if (!(pid > 0)) throw new Error('Abre primero un proyecto en Project Web.');
    const c = cfg.leer(), conexion = c.proveedores.find(x => x.id === provId);
    if (!conexion) throw new Error('Elige una conexión de IA (Ajustes → Configuración de IA).');
    ejecutandoProg = true; cancelarProg = false;
    try {
      return await programador.ejecutar({
        servidor: c.servidor, proyectoId: pid, conexion, clave: cfg.secreto(conexion.id),
        sesion: (u, o) => vista.webContents.session.fetch(u, o),
        alAvanzar: p => win?.webContents.send('prog:progreso', p), cancelado: () => cancelarProg,
      });
    } finally { ejecutandoProg = false; }
  });
  ipcMain.handle('prog:proyectos', async () => {
    const r = await vista.webContents.session.fetch(cfg.leer().servidor + '/api/programador/proyectos', { credentials: 'include' });
    const j = await r.json().catch(() => null);
    if (!r.ok || !j?.success) throw new Error(r.status === 403 || r.status === 401 ? 'Inicia sesión como administrador en Project Web.' : 'No se pudo leer la lista de proyectos (' + r.status + ').');
    return j.proyectos;
  });
  ipcMain.handle('prog:cancelar', () => { cancelarProg = true; });
  // Ensamblador: documentos → propuesta de partidas, materiales y submittals (solo la acepta el admin; el servidor aplica y deshace)
  const ens = ensamblador.crear({ cfg, sesion: (u, o) => vista.webContents.session.fetch(u, o), avisar: p => win?.webContents.send('ens:progreso', p) });
  const idEns = v => { const n = parseInt(v, 10); if (!(n > 0)) throw new Error('Elige un proyecto.'); return n; };
  ipcMain.handle('ens:estado', (_e, pid) => ens.estado(idEns(pid)));
  ipcMain.handle('ens:subir', (_e, pid, ses, archivos) => ens.subir(idEns(pid), parseInt(ses, 10) || 0, (archivos || []).map(a => ({ nombre: String(a.nombre || 'documento'), datos: Buffer.from(a.datos) }))));
  ipcMain.handle('ens:quitar', (_e, pid, ses, archivo) => ens.quitar(idEns(pid), parseInt(ses, 10), parseInt(archivo, 10)));
  ipcMain.handle('ens:mensaje', (_e, o) => {
    const c = cfg.leer(), conexion = c.proveedores.find(x => x.id === o.provId);
    if (!conexion) throw new Error('Elige una conexión de IA (Ajustes → Configuración de IA).');
    return ens.mensaje({ pid: idEns(o.pid), ses: parseInt(o.ses, 10), texto: String(o.texto || '').slice(0, 1000), modos: Array.isArray(o.modos) ? o.modos.map(String) : [], submittalId: parseInt(o.submittalId, 10) || 0, conexion, clave: cfg.secreto(conexion.id), revisar: !!o.revisar });
  });
  ipcMain.handle('ens:cancelar', () => ens.cancelar());
  // Tabla desde documento: el motor del servidor o la IA leen el archivo; el admin revisa y crea la tabla en el catálogo de vPlan
  const ocr = ocrLocal.crear({ dir: path.join(app.getPath('userData'), 'ocr') });
  app.once('will-quit', () => { ocr.cerrar(); });
  ipcMain.handle('ocr:estado', () => ({ instalado: ocr.instalados() }));
  ipcMain.handle('ocr:instalar', async () => { await ocr.instalar(p => win?.webContents.send('ocr:progreso', p)); return { instalado: ocr.instalados() }; });
  ipcMain.handle('ocr:quitar', async () => { await ocr.quitar(); return { instalado: false }; });
  const tdoc = tablaDocumento.crear({ cfg, ocr, sesion: (u, o) => vista.webContents.session.fetch(u, o), avisar: p => win?.webContents.send('tdoc:progreso', p) });
  ipcMain.handle('tdoc:leer', (_e, pid, archivo, provId, usarIA) => {
    const conexion = provId ? cfg.leer().proveedores.find(x => x.id === provId) : null;
    return tdoc.leer(idEns(pid), { nombre: String(archivo?.nombre || 'documento'), datos: Buffer.from(archivo?.datos || []) }, conexion, conexion ? cfg.secreto(conexion.id) : '', !!usarIA);
  });
  ipcMain.handle('tdoc:crear', (_e, pid, o) => tdoc.crearTabla(idEns(pid), { nombre: String(o?.nombre || '').slice(0, 140), origen: String(o?.origen || '').slice(0, 200), metodo: o?.metodo === 'ia' ? 'ia' : 'motor', modelo: String(o?.modelo || '').slice(0, 150), filas: Array.isArray(o?.filas) ? o.filas : [] }));
  // Ordenar y renumerar por fechas: el servidor calcula, la IA explica, el admin elige qué ordenar y qué renumerar
  const ord = ordenarIA.crear({ cfg, sesion: (u, o) => vista.webContents.session.fetch(u, o), avisar: p => win?.webContents.send('ord:progreso', p) });
  ipcMain.handle('ord:propuesta', (_e, pid, provId) => {
    const conexion = provId ? cfg.leer().proveedores.find(x => x.id === provId) : null;
    return ord.propuesta(idEns(pid), conexion, conexion ? cfg.secreto(conexion.id) : '');
  });
  ipcMain.handle('ord:aplicar', (_e, pid, huella, sel) => ord.aplicar(idEns(pid), String(huella), (Array.isArray(sel) ? sel : []).map(x => ({ id: String(x.id), ordenar: !!x.ordenar, renumerar: !!x.renumerar }))));
  ipcMain.handle('ord:rechazar', (_e, pid, modelo) => ord.rechazar(idEns(pid), String(modelo || '')));
  ipcMain.handle('ord:deshacer', (_e, pid, id) => ord.deshacer(idEns(pid), parseInt(id, 10)));
  ipcMain.handle('ens:aplicar', (_e, pid, id, items) => ens.aplicar(idEns(pid), parseInt(id, 10), (items || []).map(String)));
  ipcMain.handle('ens:revertir', (_e, pid, id) => ens.revertir(idEns(pid), parseInt(id, 10)));
  ipcMain.handle('ens:rechazar', (_e, pid, id) => ens.rechazar(idEns(pid), parseInt(id, 10)));
  ipcMain.handle('ens:propuesta', (_e, pid, id) => ens.propuesta(idEns(pid), parseInt(id, 10)));
  // Herramientas IA: catálogo del menú y qué puede hacer la conexión elegida con cada una (la principal si no se indica)
  const fichasServidor = async () => {
    if (Date.now() - cacheFichas.t < 30000 && cacheFichas.lista.length) return cacheFichas.lista;
    const lista = await informes.catalogo({ servidor: cfg.leer().servidor, sesion: (u, o) => vista.webContents.session.fetch(u, o) });
    if (lista.length) cacheFichas = { t: Date.now(), lista };
    return lista.length ? lista : cacheFichas.lista;
  };
  ipcMain.handle('herr:estados', async (_e, provId) => {
    // Cada herramienta usa la conexión elegida para su tipo de tarea (Ajustes → Configuración de IA); sin ninguna se usa la principal o la primera guardada
    const c = cfg.leer(), principal = c.proveedores.find(x => x.id === (provId || c.activa)) || c.proveedores[0] || null;
    const todas = [...catalogo.HERRAMIENTAS, ...(await fichasServidor()).map(catalogo.desdeServidor)];
    const estados = {};
    for (const h of todas) {
      const conexion = provId ? principal : cfg.conexionPara(h.tarea || 'rapida');
      estados[h.id] = h.requiere ? capacidad.evaluar(h.requiere, conexion ? capacidad.perfil(conexion) : null) : { estado: 'ok', texto: '' };
    }
    const p = principal ? capacidad.perfil(principal) : null;
    return { grupos: catalogo.GRUPOS, herramientas: todas, perfil: p, conexion: principal ? principal.nombre : null, estados };
  });
  // Estado de uso de cada herramienta en el proyecto (ejecutada, desactualizada, revisada): lo calcula el servidor; sin sesión o sin red no hay marcas
  ipcMain.handle('herr:uso', async (_e, proyectoId) => {
    const pid = parseInt(proyectoId, 10);
    if (!(pid > 0) || !vista) return null;
    try {
      const r = await (await vista.webContents.session.fetch(cfg.leer().servidor + '/api/revisiones/resumen?proyecto=' + pid, { credentials: 'include' })).json();
      return r?.success ? r.lista_herramientas : null;
    } catch { return null; }
  });
  ipcMain.handle('inc:ejecutar', async (_e, proyectoId, provId) => {
    if (ejecutandoInc) throw new Error('Ya hay un análisis en curso.');
    const pid = parseInt(proyectoId, 10);
    if (!(pid > 0)) throw new Error('Elige un proyecto.');
    const c = cfg.leer(), conexion = c.proveedores.find(x => x.id === provId);
    if (!conexion) throw new Error('Elige una conexión de IA (Ajustes → Configuración de IA).');
    ejecutandoInc = true; cancelarInc = false;
    try {
      return await inconsistencias.ejecutar({
        servidor: c.servidor, proyectoId: pid, conexion, clave: cfg.secreto(conexion.id),
        sesion: (u, o) => vista.webContents.session.fetch(u, o),
        alAvanzar: p => win?.webContents.send('inc:progreso', p), cancelado: () => cancelarInc,
      });
    } finally { ejecutandoInc = false; }
  });
  ipcMain.handle('inc:cancelar', () => { cancelarInc = true; });
  ipcMain.handle('mat:ejecutar', async (_e, proyectoId, provId) => {
    if (ejecutandoMat) throw new Error('Ya hay un análisis en curso.');
    const pid = parseInt(proyectoId, 10);
    if (!(pid > 0)) throw new Error('Elige un proyecto.');
    const c = cfg.leer(), conexion = c.proveedores.find(x => x.id === provId);
    if (!conexion) throw new Error('Elige una conexión de IA (Ajustes → Configuración de IA).');
    ejecutandoMat = true; cancelarMat = false;
    try {
      return await analisisMateriales.ejecutar({
        servidor: c.servidor, proyectoId: pid, conexion, clave: cfg.secreto(conexion.id),
        sesion: (u, o) => vista.webContents.session.fetch(u, o),
        alAvanzar: p => win?.webContents.send('mat:progreso', p), cancelado: () => cancelarMat,
      });
    } finally { ejecutandoMat = false; }
  });
  ipcMain.handle('mat:cancelar', () => { cancelarMat = true; });
  ipcMain.handle('gen:ejecutar', async (_e, herramienta, proyectoId, provId, parametros, sinIA) => {
    if (ejecutandoGen) throw new Error('Ya hay una tarea en curso.');
    const pid = parseInt(proyectoId, 10);
    if (!(pid > 0)) throw new Error('Elige un proyecto.');
    const c = cfg.leer(), conexion = c.proveedores.find(x => x.id === provId) || null;
    if (!sinIA && !conexion) throw new Error('Elige una conexión de IA (Ajustes → Configuración de IA) o genera el informe sin IA.');
    ejecutandoGen = true; cancelarGen = false;
    try {
      return await informes.ejecutar({
        servidor: c.servidor, proyectoId: pid, herramienta: String(herramienta), parametros: parametros && typeof parametros === 'object' ? parametros : {}, conexion, clave: conexion ? cfg.secreto(conexion.id) : '', sinIA: !!sinIA,
        sesion: (u, o) => vista.webContents.session.fetch(u, o), alAvanzar: p => win?.webContents.send('gen:progreso', p), cancelado: () => cancelarGen,
      });
    } finally { ejecutandoGen = false; }
  });
  ipcMain.handle('gen:cancelar', () => { cancelarGen = true; });
  // Materiales inteligentes: activar la opción por proyecto, verificar ahora y verificación automática al ver materiales/submittals
  const sesionWeb = (u, o) => vista.webContents.session.fetch(u, o);
  const conexionPrincipalMI = () => cfg.conexionPara('rapida');
  // Centro de IA dentro de la app: resultados guardados, espacio, pendientes y auditoría (solo rutas conocidas; el servidor exige sesión de administrador)
  const API_IA_GET = new Set(['/api/ia/centro', '/api/ia/pendientes', '/api/ia/analisis', '/api/ia/auditoria', '/api/ia/dias-sin-reporte', '/api/ia/informe']);
  const API_IA_POST = new Set(['/api/ia/biblioteca', '/propuestas-ia/aceptar', '/propuestas-ia/rechazar', '/api/ia/informe-resolver']);
  // Comprobación ligera de que el servidor responde y de que hay sesión de administrador (indicador de conexión)
  ipcMain.handle('srv:ping', async () => {
    const t0 = Date.now();
    try {
      const r = await sesionWeb(cfg.leer().servidor + '/api/ia/herramientas', { credentials: 'include', signal: AbortSignal.timeout(8000) });
      const j = await r.json().catch(() => null);
      // La sesión caducó en el servidor (p. ej. tras un despliegue) pero la vista sigue en una página interna: llevarla al login
      if (!j?.success) { try { const u = new URL(vista.webContents.getURL()); if (u.pathname !== '/login' && u.origin === new URL(cfg.leer().servidor).origin) vista.webContents.loadURL(cfg.leer().servidor + '/login'); } catch { /* sin URL todavía */ } }
      return { conectado: true, sesion: !!j?.success, ms: Date.now() - t0 };
    } catch (x) { return { conectado: false, sesion: false, ms: Date.now() - t0, error: String(x.message || x) }; }
  });
  ipcMain.handle('ia:api', async (_e, metodo, ruta, params) => {
    const srv = cfg.leer().servidor, post = metodo === 'POST';
    if (!(post ? API_IA_POST : API_IA_GET).has(ruta)) throw new Error('Ruta no permitida');
    const campos = {};
    for (const [k, v] of Object.entries(params && typeof params === 'object' ? params : {})) if (v != null && v !== '') campos[k] = String(v).slice(0, 2000);
    let r;
    if (post) {
      const cat = await (await sesionWeb(srv + '/api/ia/herramientas', { credentials: 'include' })).json().catch(() => null);
      if (!cat?.success) throw new Error('Inicia sesión como administrador en Project Web.');
      r = await sesionWeb(srv + ruta, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': cat.csrf }, body: new URLSearchParams({ ...campos, _csrf: cat.csrf }).toString() });
    } else r = await sesionWeb(srv + ruta + '?' + new URLSearchParams(campos).toString(), { credentials: 'include' });
    const j = await r.json().catch(() => null);
    if (!j) throw new Error(r.status === 403 || r.status === 401 ? 'Inicia sesión como administrador en Project Web.' : 'Respuesta no válida del servidor (' + r.status + ')');
    if (!r.ok || j.success === false) throw new Error(j.error || j.mensaje || 'El servidor rechazó la petición (' + r.status + ')');
    return j;
  });
  ipcMain.handle('mi:estado', (_e, proyectoId) => inteligentes.estado({ servidor: cfg.leer().servidor, sesion: sesionWeb, proyectoId: parseInt(proyectoId, 10) }));
  ipcMain.handle('mi:activar', (_e, proyectoId, activo) => inteligentes.activar({ servidor: cfg.leer().servidor, sesion: sesionWeb, proyectoId: parseInt(proyectoId, 10), activo: !!activo }));
  ipcMain.handle('mi:verificar', async (_e, proyectoId, provId) => {
    if (verificandoMI) throw new Error('Ya hay una verificación en curso.');
    const pid = parseInt(proyectoId, 10);
    if (!(pid > 0)) throw new Error('Elige un proyecto.');
    const c = cfg.leer(), conexion = c.proveedores.find(x => x.id === provId);
    if (!conexion) throw new Error('Elige una conexión de IA (Ajustes → Configuración de IA).');
    verificandoMI = true; cancelarMI = false;
    try {
      const r = await inteligentes.ejecutar({ servidor: c.servidor, sesion: sesionWeb, proyectoId: pid, conexion, clave: cfg.secreto(conexion.id), alAvanzar: p => win?.webContents.send('mi:progreso', p), cancelado: () => cancelarMI });
      avisarNoAprobados(pid, r.resumen);
      return r;
    } finally { verificandoMI = false; }
  });
  ipcMain.handle('mi:cancelar', () => { cancelarMI = true; });
  function avisarNoAprobados(pid, r) {
    if (!r.no_aprobados?.length) return;
    const n = new Notification({ title: 'Material no aprobado por la normativa', body: r.no_aprobados.slice(0, 3).map(m => m.nombre + ': ' + m.resumen).join('\n') + (r.no_aprobados.length > 3 ? '\n…y ' + (r.no_aprobados.length - 3) + ' más' : '') });
    n.on('click', () => { win?.show(); win?.focus(); vista.webContents.loadURL(cfg.leer().servidor + '/materiales?proyecto=' + pid); win?.webContents.send('vig:abrir'); });
    n.show();
  }
  alNavegar = async url => {
    let u; try { u = new URL(url); } catch { return; }
    const pid = parseInt(u.searchParams.get('proyecto'), 10);
    if (!(pid > 0) || !['/materiales', '/submittals', '/submittals/ver'].includes(u.pathname) || verificandoMI) return;
    if (Date.now() - (intentosMI[pid] || 0) < 120000 && !u.searchParams.has('ok')) return;   // tras guardar un material (ok=1) se verifica enseguida
    const c = cfg.leer(), conexion = conexionPrincipalMI();
    if (!conexion || !c.servidor) return;
    intentosMI[pid] = Date.now();
    verificandoMI = true; cancelarMI = false;
    try {
      const e = await inteligentes.estado({ servidor: c.servidor, sesion: sesionWeb, proyectoId: pid });   // sin sesión de administrador falla y no se hace nada
      if (!e.activo || !e.documentos || !e.conteos.pendiente) return;
      const r = await inteligentes.ejecutar({ servidor: c.servidor, sesion: sesionWeb, proyectoId: pid, conexion, clave: cfg.secreto(conexion.id), alAvanzar: p => win?.webContents.send('mi:progreso', p), cancelado: () => false });
      if (!r.resumen.verificados) return;
      avisarNoAprobados(pid, r.resumen);
      if (new URL(vista.webContents.getURL()).searchParams.get('proyecto') === String(pid)) vista.webContents.reload();   // solo listas: nunca un formulario a medias
    } catch { /* sin red, sin sesión o sin modelo disponible: se reintenta en la próxima visita */ }
    finally { verificandoMI = false; }
  };
  // Chat «Pregunta a tu proyecto»
  const listaProyectos = async () => {
    if (Date.now() - nombresProyectos.t > 60000) {
      try {
        const r = await (await vista.webContents.session.fetch(cfg.leer().servidor + '/api/programador/proyectos', { credentials: 'include' })).json();
        if (r.success) nombresProyectos = { t: Date.now(), mapa: Object.fromEntries(r.proyectos.map(p => [p.id, p.codigo + ' · ' + p.nombre])) };
      } catch { /* sin red */ }
    }
    return Object.entries(nombresProyectos.mapa).map(([id, texto]) => ({ id: Number(id), texto }));
  };
  // Proyecto sobre el que se pregunta: el que el usuario elija en el chat; si no ha elegido, el abierto en Project Web
  let proyectoChat = null;
  const proyectoDelChat = () => proyectoChat || proyectoVigilado;
  const conexionPrincipal = () => cfg.conexionPara('rapida');
  ipcMain.handle('chat:abrir', (_e, abierto) => { chatAbierto = !!abierto; if (chatAbierto) win.contentView.addChildView(vistaChat); ajustarChat(); return true; });
  ipcMain.handle('chat:contexto', async () => ({ proyectoId: proyectoDelChat(), proyectos: await listaProyectos(), conexion: conexionPrincipal()?.nombre || null }));
  ipcMain.handle('chat:proyecto', (_e, id) => { const n = parseInt(id, 10); proyectoChat = n > 0 ? n : null; return true; });
  ipcMain.handle('chat:enviar', async (_e, pregunta, historial) => {
    if (ejecutandoChat) throw new Error('Espera a que termine la respuesta anterior.');
    ejecutandoChat = true;
    try {
      const c = cfg.leer(), conexion = conexionPrincipal();
      return await chat.responder({ servidor: c.servidor, proyectoId: proyectoDelChat(), pregunta, historial, conexion, clave: conexion ? cfg.secreto(conexion.id) : '', sesion: (u, o) => vista.webContents.session.fetch(u, o), alAvanzar: t => vistaChat?.webContents.send('chat:avance', t) });
    } finally { ejecutandoChat = false; }
  });
  // Vigilancia continua: cada cierto tiempo comprueba el proyecto abierto con las reglas del sistema (sin IA) y avisa si hay algo nuevo
  const comprobarVigilancia = async () => {
    if (!proyectoVigilado || !vista) return;
    try {
      const c = cfg.leer(), sesion = (u, o) => vista.webContents.session.fetch(u, o);
      const cat = await (await sesion(c.servidor + '/api/ia/herramientas', { credentials: 'include' })).json().catch(() => null);
      if (!cat?.success) return;   // sin sesión de administrador no hay nada que vigilar
      const r = await (await sesion(c.servidor + '/api/inconsistencias/vigilancia', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': cat.csrf }, body: new URLSearchParams({ proyecto: String(proyectoVigilado), _csrf: cat.csrf }).toString() })).json().catch(() => null);
      if (!r?.success || r.primera || (!r.total_nuevos && !r.cambios.length)) return;
      const n = new Notification({ title: 'Project Web: vigilancia del proyecto', body: [r.total_nuevos ? r.total_nuevos + ' inconsistencia(s) nueva(s)' : '', ...r.cambios.slice(0, 2).map(x => x.titulo)].filter(Boolean).join(' · ') });
      n.on('click', () => { win?.show(); win?.focus(); vista.webContents.loadURL(c.servidor + '/inconsistencias?proyecto=' + proyectoVigilado); win?.webContents.send('vig:abrir'); });
      n.show();
    } catch { /* sin red: se reintenta en la siguiente comprobación */ }
  };
  const programarVigilancia = () => {
    clearInterval(timerVig); timerVig = null;
    const v = cfg.leer().vigilancia;
    if (v?.activa) { timerVig = setInterval(comprobarVigilancia, Math.max(5, v.minutos) * 60000); setTimeout(comprobarVigilancia, 30000); }
  };
  programarVigilancia();
  // Envía al servidor las llamadas medidas (agrupadas, con un respiro de 5 s); si falla se reintenta con la siguiente
  let colaUso = [], timerUso = null;
  const enviarUso = async () => {
    timerUso = null;
    if (!colaUso.length || !vista) return;
    const lote = colaUso; colaUso = [];
    try {
      const c = cfg.leer(), sesion = (u, o) => vista.webContents.session.fetch(u, o);
      const cat = await (await sesion(c.servidor + '/api/ia/herramientas', { credentials: 'include' })).json().catch(() => null);
      if (!cat?.success) throw new Error('sin sesión');
      const r = await (await sesion(c.servidor + '/api/ia/uso', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': cat.csrf }, body: new URLSearchParams({ registros: JSON.stringify(lote), _csrf: cat.csrf }).toString() })).json().catch(() => null);
      if (!r?.success) throw new Error('rechazado');
    } catch { colaUso = lote.concat(colaUso).slice(-500); }
  };
  prov.medirUso(r => { colaUso.push(r); if (!timerUso) timerUso = setTimeout(enviarUso, 5000); });
  ipcMain.handle('vig:guardar', (_e, v) => { const r = cfg.guardarVigilancia(v); programarVigilancia(); return r; });
  ipcMain.handle('vig:ahora', async () => { await comprobarVigilancia(); return true; });
  // Voz: estado del motor de transcripción, transcribir un WAV del micrófono y guardar la grabación en el proyecto
  const conexionVoz = c => c.proveedores.find(x => x.id === (c.voz || {}).conexion) || null;
  ipcMain.handle('voz:estado', async () => { const c = cfg.leer(); const e = await voz.estado(c.voz, conexionVoz(c)); delete e._r; return e; });
  ipcMain.handle('voz:guardar', async (_e, v) => { cfg.guardarVoz(voz.sanear(v)); const c = cfg.leer(); const e = await voz.estado(c.voz, conexionVoz(c)); delete e._r; return e; });
  ipcMain.handle('voz:transcribir', async (_e, wav, idioma) => {
    const c = cfg.leer(), conexion = conexionVoz(c);
    if (!wav || wav.byteLength < 100) throw new Error('No se grabó audio.');
    return voz.transcribir(Buffer.from(wav), { voz: c.voz, conexion, clave: conexion ? cfg.secreto(conexion.id) : '', idioma });
  });
  ipcMain.handle('voz:subir', async (_e, proyectoId, audio, mime, nombre, duracion, transcripcion) => {
    const c = cfg.leer(), sesion = (u, o) => vista.webContents.session.fetch(u, o);
    const cat = await (await sesion(c.servidor + '/api/ia/herramientas', { credentials: 'include' })).json().catch(() => null);
    if (!cat?.success) throw new Error('Inicia sesión como administrador en Project Web.');
    const f = new FormData();
    f.append('proyecto', String(parseInt(proyectoId, 10))); f.append('nombre', String(nombre || '').slice(0, 200)); f.append('duracion', String(Math.max(0, parseInt(duracion, 10) || 0))); f.append('_csrf', cat.csrf);
    if (transcripcion) f.append('transcripcion', String(transcripcion).slice(0, 200000));
    f.append('audio', new Blob([Buffer.from(audio)], { type: String(mime || 'audio/webm').split(';')[0] }), 'grabacion.webm');
    const r = await sesion(c.servidor + '/api/ia/grabacion', { method: 'POST', credentials: 'include', headers: { 'X-CSRF-Token': cat.csrf }, body: f });
    const j = await r.json().catch(() => null);
    if (!r.ok || !j?.success) throw new Error(j?.error || 'No se pudo guardar la grabación (' + r.status + ')');
    return j.id;
  });
  ipcMain.handle('web:menu', () => vista.webContents.executeJavaScript("document.getElementById('btn-menu')?.click()"));
  ipcMain.handle('web:zoom', (_e, z) => cambiarZoom(actual => (z && typeof z === 'object' ? actual + (Number(z.delta) || 0) : z)));   // número = valor; {delta} = relativo (evita carreras entre clics rápidos)
  ipcMain.handle('prov:uso', (_e, sondear) => (sondear ? prov.usoSondear(sondear === 'ya' ? 0 : 10) : prov.uso()));
  ipcMain.handle('prov:estado', (_e, id) => {
    const p = cfg.leer().proveedores.find(x => x.id === id);
    return p ? prov.estado(p, cfg.secreto(p.id)) : { nivel: 'error', texto: 'Conexión no encontrada' };
  });
  ipcMain.handle('prov:tareas', (_e, v) => cfg.guardarPorTarea(v));
  ipcMain.handle('prov:activa', (_e, id) => cfg.guardarActiva(String(id)));
  ipcMain.handle('ollama:estado', (_e, url) => prov.ollamaEstado(url));
  ipcMain.handle('ollama:descargar', async (_e, url, modelo) => {
    if (descargaOllama) throw new Error('Ya hay una descarga en curso.');
    descargaOllama = new AbortController();
    try { await prov.ollamaDescargar(url, modelo, p => win?.webContents.send('ollama:progreso', { modelo, ...p }), descargaOllama.signal); }
    catch (x) { throw descargaOllama.signal.aborted ? new Error('Descarga cancelada (lo ya descargado se conserva y se retoma al volver a empezar).') : x; }
    finally { descargaOllama = null; }
  });
  ipcMain.handle('ollama:cancelar', () => descargaOllama?.abort());
  ipcMain.handle('ollama:cargar', (_e, url, modelo, cargar) => prov.ollamaCargar(url, modelo, cargar !== false));
  ipcMain.handle('ollama:eliminar', (_e, url, modelo) => prov.ollamaEliminar(url, modelo));
  ipcMain.handle('app:version', () => app.getVersion());
  ipcMain.handle('config:get', () => cfg.publica());
  ipcMain.handle('config:tipos', () => Object.fromEntries(Object.entries(prov.TIPOS).map(([k, t]) => [k, { etiqueta: t.etiqueta, url: t.url || '', clave: !!t.clave, cli: !!t.bin }])));
  ipcMain.handle('config:servidor', (_e, url) => { if (url) new URL(url); cfg.guardarServidor(String(url || '').trim().replace(/\/+$/, '')); });
  ipcMain.handle('prov:guardar', (_e, p, clave) => {
    if (!prov.TIPOS[p.tipo]) throw new Error('Tipo no válido');
    const reg = { id: p.id || crypto.randomUUID(), nombre: String(p.nombre || '').trim().slice(0, 100), tipo: p.tipo, url: String(p.url || '').trim(), modelo: String(p.modelo || '').trim().slice(0, 100) };
    if (!reg.nombre) throw new Error('El nombre es obligatorio');
    if (reg.url) prov.urlBase(reg);
    if (clave) cfg.guardarSecreto(reg.id, clave);   // primero la clave: si el llavero falla, no queda la conexión a medias
    cfg.guardarProveedor(reg);
    return reg.id;
  });
  ipcMain.handle('prov:eliminar', (_e, id) => cfg.eliminarProveedor(id));
  ipcMain.handle('prov:modelos', (_e, p, clave) => { if (!prov.TIPOS[p.tipo]) throw new Error('Tipo no válido'); return prov.modelos({ tipo: p.tipo, url: String(p.url || '').trim() }, clave); });
  ipcMain.handle('prov:probar', async (_e, p, clave, id) => {
    if (!prov.TIPOS[p.tipo]) throw new Error('Tipo no válido');
    return prov.probar({ tipo: p.tipo, url: String(p.url || '').trim(), modelo: String(p.modelo || '').trim() }, clave || (id ? cfg.secreto(id) : ''));
  });
  ipcMain.handle('hw:detectar', async () => { const h = await hw.detectar(); return { ...h, sugerencias: hw.recomendar(h) }; });
  ipcMain.handle('prov:cli', () => prov.detectarCli());
  ipcMain.handle('upd:buscar', () => upd.consultar(cfg.leer().servidor, (u, o) => vista.webContents.session.fetch(u, o), app.getVersion()));
  ipcMain.handle('upd:instalar', async (_e, archivo) => {
    const c = cfg.leer();
    const ruta = await upd.descargar(c.servidor, (u, o) => vista.webContents.session.fetch(u, o), archivo, path.join(app.getPath('userData'), 'updates'), p => win?.webContents.send('upd:progreso', p));
    return upd.instalar(ruta);
  });
  // Ajustes: datos de la instalación, tamaño de los datos locales y desinstalación
  const tamano = d => { let n = 0; try { for (const e of fs.readdirSync(d, { withFileTypes: true })) { const r = path.join(d, e.name); n += e.isDirectory() ? tamano(r) : (e.isSymbolicLink() ? 0 : fs.statSync(r).size); } } catch { /* carpeta ilegible */ } return n; };
  const formato = () => !app.isPackaged ? 'desarrollo' : process.env.APPIMAGE ? 'appimage' : 'deb';
  ipcMain.handle('app:info', () => ({ version: app.getVersion(), formato: formato(), datos: app.getPath('userData'), datosBytes: tamano(app.getPath('userData')), ejecutable: process.env.APPIMAGE || process.execPath }));
  ipcMain.handle('app:desinstalar', async (_e, borrarDatos) => {
    const f = formato();
    if (f === 'desarrollo') throw new Error('Esta app se está ejecutando desde el código fuente (no está instalada): no hay nada que desinstalar.');
    const { response } = await dialog.showMessageBox(win, { type: 'warning', buttons: ['Cancelar', 'Desinstalar'], defaultId: 0, cancelId: 0, title: 'Desinstalar', message: '¿Desinstalar Project Web Desktop?',
      detail: (f === 'appimage' ? 'Se borrará el archivo AppImage.' : 'Se quitará el paquete del sistema (pedirá tu contraseña).') + (borrarDatos ? '\nTambién se borrarán tus conexiones de IA y claves guardadas en este equipo.' : '\nTus conexiones y ajustes se conservan por si la reinstalas.') + '\nTus proyectos en Project Web no se tocan.' });
    if (response !== 1) return { cancelado: true };
    if (f === 'deb') await new Promise((ok, mal) => require('child_process').execFile('pkexec', ['apt', 'remove', '-y', 'projectweb-desktop'], { timeout: 300000 }, (e, _o, err) => e ? mal(new Error((err || e.message).trim().slice(0, 300))) : ok()));
    const appimage = process.env.APPIMAGE, datos = app.getPath('userData');
    app.once('will-quit', () => { try { if (f === 'appimage' && appimage) fs.rmSync(appimage, { force: true }); if (borrarDatos) fs.rmSync(datos, { recursive: true, force: true }); } catch { /* ya no está */ } });
    setTimeout(() => app.quit(), 1200);
    return { ok: true, mensaje: 'Desinstalada. La aplicación se cerrará ahora.' };
  });
  ventana();
  app.on('second-instance', () => { if (win) { if (win.isMinimized()) win.restore(); win.focus(); } });
});
app.on('window-all-closed', () => app.quit());
