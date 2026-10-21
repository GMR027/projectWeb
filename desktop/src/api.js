'use strict';
// Llamadas a Project Web con la sesión del admin: respuesta JSON validada y POST de formulario con el código CSRF

/** Lee la respuesta como JSON y falla con un mensaje claro si no lo es o trae success:false */
const json = async (r, que) => {
  const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
  if (!r.ok || !j || j.success === false) throw new Error(que + ': ' + (j?.error || (r.status === 403 || r.status === 401 ? 'inicia sesión como administrador en Project Web' : 'respuesta ' + r.status)));
  return j;
};

/** URL de una API del proyecto: servidor + ruta + ?proyecto=id */
const pagina = (servidor, ruta, proyectoId) => servidor + ruta + '?proyecto=' + encodeURIComponent(proyectoId);

/** GET con la sesión y JSON validado */
const leer = (sesion, url, que) => sesion(url, { credentials: 'include' }).then(r => json(r, que));

/** POST de formulario (campos + _csrf) con la sesión y JSON validado */
const enviar = (sesion, url, campos, csrf, que) => sesion(url, {
  method: 'POST', credentials: 'include',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf },
  body: new URLSearchParams({ ...campos, _csrf: csrf }).toString(),
}).then(r => json(r, que));

module.exports = { json, pagina, leer, enviar };
