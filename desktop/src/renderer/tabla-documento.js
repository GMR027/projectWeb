'use strict';
// Tabla en base a documento: el servidor la lee con su motor (sin IA) o la IA (imágenes, escaneos, formatos difíciles); el admin revisa y la crea.
const TablaDoc = (() => {
  const $ = s => document.querySelector(s);
  const el = (tag, txt, cls) => { const e = document.createElement(tag); if (txt != null) e.textContent = txt; if (cls) e.className = cls; return e; };
  const err = e => String(e && e.message || e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');
  let abrirWeb = () => {}, proyectos = null, listo = false, ocupado = false, filas = [], meta = null;

  function pintaTabla() {
    const nc = Math.max(1, ...filas.map(f => f.length));
    filas.forEach(f => { while (f.length < nc) f.push(''); });
    const t = el('table', null, 'tdoc-tabla'), cab = el('tr');
    cab.append(el('th'));
    for (let c = 0; c < nc; c++) {
      const th = el('th'), i = el('input'), x = el('button', '✕', 'tdoc-x'); i.value = filas[0][c]; x.type = 'button'; x.title = 'Quitar columna';
      i.addEventListener('input', () => { filas[0][c] = i.value; });
      x.addEventListener('click', () => { if (nc > 1) { filas.forEach(f => f.splice(c, 1)); pintaTabla(); } });
      th.append(i, x); cab.append(th);
    }
    t.append(cab);
    for (let r = 1; r < filas.length; r++) {
      const tr = el('tr'), td0 = el('td'), x = el('button', '✕', 'tdoc-x'); x.type = 'button'; x.title = 'Quitar renglón';
      x.addEventListener('click', () => { filas.splice(r, 1); pintaTabla(); });
      td0.append(x); tr.append(td0);
      for (let c = 0; c < nc; c++) { const td = el('td'), i = el('input'); i.value = filas[r][c]; i.addEventListener('input', () => { filas[r][c] = i.value; }); td.append(i); tr.append(td); }
      t.append(tr);
    }
    $('#tdoc-tabla').replaceChildren(t);
    $('#tdoc-resumen').textContent = nc + ' columnas · ' + (filas.length - 1) + ' renglones' + (nc > 8 ? ' (el vPlan usa las primeras 8 columnas)' : '');
  }

  async function leer(usarIA) {
    if (ocupado) return;
    const pid = $('#tdoc-proyecto').value, f = $('#tdoc-archivo').files[0], msg = $('#tdoc-msg');
    $('#tdoc-previa').hidden = true; $('#tdoc-fin').replaceChildren();
    if (!pid) { msg.textContent = 'Elige un proyecto.'; return; }
    if (!f) { msg.textContent = 'Elige un documento (Excel, CSV, Word, PDF o imagen).'; return; }
    ocupado = true; $('#tdoc-motor').disabled = $('#tdoc-ia').disabled = true; $('#tdoc-barra').hidden = false; $('#tdoc-barra').removeAttribute('value'); msg.textContent = '';
    try {
      const r = await pw.tdoc.leer(pid, { nombre: f.name, datos: new Uint8Array(await f.arrayBuffer()) }, usarIA ? ($('#tdoc-conexion').value || null) : null, usarIA);
      if (!r.filas.length) { msg.textContent = (r.avisos || []).join(' ') || 'No se encontró ninguna tabla.'; if (!usarIA) msg.textContent += ' Prueba «Leer con IA».'; }
      else {
        filas = r.filas; meta = { pid, origen: f.name, metodo: r.metodo === 'ia' ? 'ia' : 'motor', modelo: r.modelo || '' };
        $('#tdoc-nombre').value = (r.titulo || f.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ')).slice(0, 140);
        msg.textContent = (r.metodo === 'ia' ? 'Leído por IA. ' : 'Leído por el motor (sin IA). ') + (r.avisos || []).join(' ');
        $('#tdoc-previa').hidden = false; pintaTabla();
      }
    } catch (e) { msg.textContent = '✘ ' + err(e); }
    ocupado = false; $('#tdoc-motor').disabled = $('#tdoc-ia').disabled = false; $('#tdoc-barra').hidden = true;
  }

  async function crear() {
    const nombre = $('#tdoc-nombre').value.trim(), msg = $('#tdoc-msg');
    if (!nombre) { msg.textContent = 'Escribe un nombre para la tabla.'; return; }
    $('#tdoc-crear').disabled = true;
    try {
      const r = await pw.tdoc.crear(meta.pid, { nombre, origen: meta.origen, metodo: meta.metodo, modelo: meta.modelo, filas });
      $('#tdoc-previa').hidden = true;
      const web = el('button', 'Abrir en vPlan', 'sec'); web.type = 'button';
      web.addEventListener('click', () => abrirWeb(r.url, '/vplan'));
      $('#tdoc-fin').replaceChildren(el('strong', '✔ Tabla creada en el catálogo de vPlan'), web);
      msg.textContent = '';
    } catch (e) { msg.textContent = '✘ ' + err(e); }
    $('#tdoc-crear').disabled = false;
  }

  async function pintar() {
    if (!listo) {
      listo = true;
      $('#tdoc-motor').addEventListener('click', () => leer(false));
      $('#tdoc-ia').addEventListener('click', () => leer(true));
      $('#tdoc-crear').addEventListener('click', crear);
      $('#tdoc-mas').addEventListener('click', () => { filas.push(new Array(filas[0].length).fill('')); pintaTabla(); });
      $('#tdoc-proyecto').addEventListener('change', () => { if (typeof fijarProyectoMenu === 'function') fijarProyectoMenu($('#tdoc-proyecto').value); });
      pw.tdoc.alAvance(p => { if (p.texto) $('#tdoc-msg').textContent = p.texto; });
    }
    const cfg = await pw.config();
    const sel = $('#tdoc-conexion'), previa = sel.value;
    sel.replaceChildren(...(cfg?.proveedores || []).map(p => { const o = el('option', p.nombre + (p.modelo ? ' · ' + p.modelo : '')); o.value = p.id; return o; }));
    const pref = cfg?.porTarea?.avanzada;
    sel.value = previa && [...sel.options].some(o => o.value === previa) ? previa : ((cfg?.proveedores || []).some(p => p.id === pref) ? pref : (cfg?.activa || ''));
    if (!proyectos) { try { proyectos = await pw.proyectosPrograma(); } catch (e) { $('#tdoc-msg').textContent = '✘ ' + err(e); proyectos = null; } }
    const sp = $('#tdoc-proyecto'), previo = sp.value;
    sp.replaceChildren(...(proyectos || []).map(p => { const o = el('option', p.codigo + ' · ' + p.nombre); o.value = p.id; return o; }));
    const deseado = (typeof proyectoIA === 'function' && proyectoIA()) || previo;
    if (deseado && [...sp.options].some(o => String(o.value) === String(deseado))) sp.value = deseado;
  }

  return { pintar, alAbrirWeb: fn => { abrirWeb = fn; } };
})();
