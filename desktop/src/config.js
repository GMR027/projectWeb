'use strict';
// Configuración local: conexiones de IA, servidor de Project Web y secretos (cifrados con safeStorage del sistema)
const fs = require('fs');
const path = require('path');

// Mientras no se configure otro, se usa el servidor local de desarrollo: ahí aparece el inicio de sesión
const SERVIDOR_DEFECTO = 'http://localhost:8010';   // el 8000 suele estar ocupado por otra app
const VACIA = { servidor: SERVIDOR_DEFECTO, proveedores: [], secretos: {}, zoom: 100, activa: '', voz: { motor: 'auto', modelo: '', idioma: 'auto', conexion: '', binario: '' }, vigilancia: { activa: false, minutos: 30 }, porTarea: { rapida: '', avanzada: '' } };
const ZOOM_MIN = 50, ZOOM_MAX = 200;

function crear(dir, safeStorage) {
  const archivo = path.join(dir, 'config.json');
  const leer = () => {
    try { const c = { ...VACIA, ...JSON.parse(fs.readFileSync(archivo, 'utf8')) }; if (!c.servidor) c.servidor = SERVIDOR_DEFECTO; return c; } catch { return { ...VACIA, proveedores: [], secretos: {} }; }
  };
  const escribir = c => {
    fs.mkdirSync(dir, { recursive: true });
    const tmp = archivo + '.tmp';
    fs.writeFileSync(tmp, JSON.stringify(c, null, 2), { mode: 0o600 });
    fs.renameSync(tmp, archivo);
  };
  return {
    leer,
    /** Lo que ve la interfaz: nunca los secretos, solo si existen */
    publica() {
      const c = leer();
      return { servidor: c.servidor, zoom: c.zoom, activa: c.proveedores.some(p => p.id === c.activa) ? c.activa : (c.proveedores[0]?.id || ''), voz: { ...VACIA.voz, ...(c.voz || {}) }, vigilancia: { ...VACIA.vigilancia, ...(c.vigilancia || {}) }, porTarea: { ...VACIA.porTarea, ...(c.porTarea || {}) }, proveedores: c.proveedores.map(p => ({ ...p, claveGuardada: !!c.secretos[p.id] })) };
    },
    /** Zoom de la vista de Project Web, en % (50–200, de 10 en 10) */
    guardarZoom(z) { const c = leer(); c.zoom = Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, Math.round((Number(z) || 100) / 10) * 10)); escribir(c); return c.zoom; },
    guardarVigilancia(v) { const c = leer(); c.vigilancia = { activa: !!v?.activa, minutos: [15, 30, 60, 120].includes(Number(v?.minutos)) ? Number(v.minutos) : 30 }; escribir(c); return c.vigilancia; },
    /** Conexión preferida por tipo de tarea: rapida (revisiones, verificaciones, chat) y avanzada (planeación, informes). '' = usar la principal */
    guardarPorTarea(v) {
      const c = leer(), ok = id => (c.proveedores.some(p => p.id === id) ? id : '');
      c.porTarea = { rapida: ok(v?.rapida), avanzada: ok(v?.avanzada) };
      escribir(c); return c.porTarea;
    },
    /** Conexión que corresponde a una tarea: la elegida para ese tipo; si no hay, la principal; si no, la primera guardada */
    conexionPara(tarea) {
      const c = leer(), id = (c.porTarea || {})[tarea];
      return c.proveedores.find(p => p.id === id) || c.proveedores.find(p => p.id === c.activa) || c.proveedores[0] || null;
    },
    guardarVoz(v) { const c = leer(); c.voz = v; escribir(c); return c.voz; },
    guardarServidor(url) { const c = leer(); c.servidor = url; escribir(c); },
    guardarProveedor(p) {
      const c = leer(), i = c.proveedores.findIndex(x => x.id === p.id);
      if (i >= 0) c.proveedores[i] = p; else { c.proveedores.push(p); c.activa = p.id; }   // la conexión recién creada pasa a ser la principal
      escribir(c);
    },
    eliminarProveedor(id) { const c = leer(); c.proveedores = c.proveedores.filter(p => p.id !== id); delete c.secretos[id]; if (c.activa === id) c.activa = c.proveedores[0]?.id || ''; escribir(c); },
    /** Conexión principal: la que muestra el indicador de la cabecera y se propone por defecto */
    guardarActiva(id) { const c = leer(); if (c.proveedores.some(p => p.id === id)) { c.activa = id; escribir(c); } },
    guardarSecreto(id, valor) {
      if (!safeStorage.isEncryptionAvailable()) throw new Error('El sistema no tiene un llavero disponible para cifrar la clave (instala gnome-keyring o kwallet). No se guardó.');
      const c = leer();
      if (valor) c.secretos[id] = safeStorage.encryptString(valor).toString('base64'); else delete c.secretos[id];
      escribir(c);
    },
    secreto(id) {
      const v = leer().secretos[id];
      return v ? safeStorage.decryptString(Buffer.from(v, 'base64')) : '';
    },
  };
}
module.exports = { crear };
