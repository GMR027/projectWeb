'use strict';
// Ordenar y renumerar por fechas (IA): el servidor calcula el orden por fechas; la IA solo explica cada cambio y avisa de lo raro.
// Nada se aplica sin que el admin elija qué ordenar y qué renumerar; todo se puede deshacer.
const { analizarLote } = require('./programador');

const MAX_ITEMS = 25, MAX_FILAS = 40;

function prompt(proy, items, avisos) {
  const datos = items.slice(0, MAX_ITEMS).map(i => ({
    id: i.id, grupo: i.titulo,
    cambios: i.filas.slice(0, MAX_FILAS).filter(f => f.de !== f.a || f.codigo !== f.nuevo).map(f => `${f.codigo} «${f.nombre}» (${f.inicio || 'sin fecha'}→${f.fin || 'sin fecha'}): posición ${f.de}→${f.a}, código ${f.codigo}→${f.nuevo}`),
  }));
  return `Eres asistente de control de obra. El proyecto «${proy}» se va a ordenar por fecha de inicio (el orden ya lo calculó el sistema; NO lo cambies).
Para cada grupo escribe UNA frase corta en español (máx. 25 palabras) que explique por qué cambia (p. ej. «la actividad X empieza antes que Y») y, solo si ves algo dudoso (fechas iguales, una actividad que por su nombre debería ir antes o después, fases fuera de secuencia), añade una advertencia corta.
Avisos del sistema: ${JSON.stringify(avisos)}
Grupos: ${JSON.stringify(datos)}
Responde SOLO con JSON: {"notas":[{"id":"<id del grupo>","texto":"<explicación>","advertencia":"<opcional>"}]}`;
}

function crear({ cfg, sesion, avisar }) {
  const json = async (r, que) => {
    const t = await r.text(); let j = null; try { j = JSON.parse(t); } catch { /* no JSON */ }
    if (!r.ok || !j || j.success === false) throw new Error(que + ': ' + (j?.error || (r.status === 403 || r.status === 401 ? 'inicia sesión como administrador en Project Web' : 'respuesta ' + r.status)));
    return j;
  };
  const srv = () => cfg.leer().servidor;
  const get = async (ruta, que) => json(await sesion(srv() + ruta, { credentials: 'include' }), que);
  const post = async (ruta, campos, csrf, que) => json(await sesion(srv() + ruta, {
    method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrf }, body: new URLSearchParams(campos).toString(),
  }), que);
  const leer = pid => get('/api/ordenar-ia/propuesta?proyecto=' + encodeURIComponent(pid), 'No se pudo calcular el orden por fechas');

  /** Propuesta del servidor + explicación de la IA (si falla o no hay conexión, se devuelve sin notas) */
  async function propuesta(pid, conexion, clave) {
    avisar({ texto: 'Calculando el orden por fechas…' });
    const p = await leer(pid);
    p.notas = {}; p.aviso_ia = '';
    if (conexion && p.items.length) {
      try {
        avisar({ texto: 'La IA está explicando los cambios…' });
        const r = await analizarLote(conexion, clave, prompt(p.proyecto, p.items, p.avisos));
        for (const n of (Array.isArray(r?.notas) ? r.notas : [])) {
          if (n && typeof n.id === 'string' && p.items.some(i => i.id === n.id)) p.notas[n.id] = { texto: String(n.texto || '').slice(0, 300), advertencia: String(n.advertencia || '').slice(0, 300) };
        }
      } catch (x) { p.aviso_ia = 'La IA no pudo explicar los cambios (' + x.message + '); el orden por fechas sí está calculado.'; }
    }
    p.modelo = conexion ? conexion.nombre + (conexion.modelo ? ' · ' + conexion.modelo : '') : '';
    return p;
  }

  const accion = async (ruta, pid, campos, que) => { const p = await leer(pid); return post(ruta, { proyecto: pid, ...campos }, p.csrf, que); };
  return {
    propuesta,
    aplicar: (pid, huella, seleccion) => accion('/api/ordenar-ia/aplicar', pid, { huella, seleccion: JSON.stringify(seleccion) }, 'No se pudo aplicar'),
    rechazar: (pid, modelo) => accion('/api/ordenar-ia/rechazar', pid, { modelo: modelo || '' }, 'No se pudo descartar'),
    deshacer: (pid, id) => accion('/api/ordenar-ia/deshacer', pid, { id }, 'No se pudo deshacer'),
    estado: leer,
  };
}

module.exports = { crear, prompt };
