'use strict';
// Actualizaciones: consulta a Project Web (token de dispositivo del admin), descarga el instalador y verifica su SHA-256
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { execFile } = require('child_process');

/** Compara versiones x.y.z: 1 si a > b */
function comparar(a, b) {
  const n = v => String(v).split('.').map(x => parseInt(x, 10) || 0);
  const [x, y] = [n(a), n(b)];
  for (let i = 0; i < 3; i++) if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) > (y[i] || 0) ? 1 : -1;
  return 0;
}
const NOMBRE_OK = /^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/;

function base(servidor) {
  const u = new URL(servidor);
  if (!['http:', 'https:'].includes(u.protocol) || u.username || u.password) throw new Error('URL del servidor no válida');
  return u.origin;
}

async function consultar(servidor, token, versionActual) {
  if (!servidor || !token) throw new Error('Configura el servidor y el token en la pestaña Actualizaciones');
  const r = await fetch(base(servidor) + '/api/escritorio/version', { headers: { Authorization: 'Bearer ' + token }, signal: AbortSignal.timeout(15000) });
  if (r.status === 401) throw new Error('Token rechazado: genera uno nuevo en Project Web → Ajustes');
  if (!r.ok) throw new Error('El servidor respondió ' + r.status);
  const j = await r.json();
  return { actual: versionActual, ultima: j.version || '', hayNueva: !!j.version && comparar(j.version, versionActual) > 0, archivos: j.archivos || [] };
}

/** Descarga a `dir` verificando el SHA-256 que publica el servidor; devuelve la ruta */
async function descargar(servidor, token, archivo, dir, alProgreso) {
  if (!NOMBRE_OK.test(archivo.nombre)) throw new Error('Nombre de archivo no válido');
  fs.mkdirSync(dir, { recursive: true });
  const destino = path.join(dir, archivo.nombre), tmp = destino + '.part';
  const r = await fetch(base(servidor) + '/api/escritorio/descargar?archivo=' + encodeURIComponent(archivo.nombre), { headers: { Authorization: 'Bearer ' + token } });
  if (!r.ok) throw new Error('El servidor respondió ' + r.status);
  const hash = crypto.createHash('sha256'), salida = fs.createWriteStream(tmp), total = +r.headers.get('content-length') || archivo.tam || 0;
  let recibido = 0;
  try {
    for await (const trozo of r.body) {
      hash.update(trozo); recibido += trozo.length;
      if (!salida.write(trozo)) await new Promise(ok => salida.once('drain', ok));
      if (alProgreso && total) alProgreso(recibido / total);
    }
    await new Promise((ok, mal) => salida.end(e => e ? mal(e) : ok()));
  } catch (e) { salida.destroy(); fs.rmSync(tmp, { force: true }); throw e; }
  if (archivo.sha256 && hash.digest('hex') !== archivo.sha256) { fs.rmSync(tmp, { force: true }); throw new Error('La suma SHA-256 no coincide; se descartó la descarga'); }
  fs.renameSync(tmp, destino);
  return destino;
}

/** .deb: instala con permisos de administrador (pkexec); AppImage: se deja ejecutable y se indica la ruta */
function instalar(ruta) {
  return new Promise((resolve, reject) => {
    if (ruta.endsWith('.deb')) {
      execFile('pkexec', ['apt', 'install', '-y', ruta], { timeout: 600000 }, (e, out, err) => e ? reject(new Error((err || e.message).trim().slice(0, 300))) : resolve('Instalado. Cierra y vuelve a abrir la aplicación.'));
    } else {
      fs.chmodSync(ruta, 0o755);
      resolve('AppImage descargada y lista: ' + ruta + '\nCierra esta aplicación y ábrela desde ahí (puedes reemplazar tu AppImage actual con ella).');
    }
  });
}
module.exports = { comparar, consultar, descargar, instalar };
