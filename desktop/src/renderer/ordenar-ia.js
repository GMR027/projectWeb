'use strict';
// Ordenar y renumerar por fechas (IA): lista de grupos con dos casillas (ordenar / renumerar), aplicar, descartar y deshacer.
const OrdenarIA = (() => {
  const $ = s => document.querySelector(s);
  const el = (tag, txt, cls) => { const e = document.createElement(tag); if (txt != null) e.textContent = txt; if (cls) e.className = cls; return e; };
  const err = e => String(e && e.message || e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');
  let abrirWeb = () => {}, alCambiar = () => {}, proyectos = null, listo = false, ocupado = false;

  function tarjeta(p, pid) {
    const caja = el('div', null, 'ens-prop');
    caja.append(el('strong', 'Propuesta por fechas' + (p.modelo ? ' · ' + p.modelo : '')));
    for (const a of p.avisos || []) caja.append(el('small', '⚠ ' + a, 'aviso'));
    if (p.aviso_ia) caja.append(el('small', p.aviso_ia, 'aviso'));
    const marcas = new Map();
    if (!p.items.length) caja.append(el('p', 'Todo está ya en orden por fechas y con la numeración correcta. No hay nada que cambiar.'));
    for (const i of p.items) {
      const fila = el('div', null, 'ens-item'), t = el('span'), nota = p.notas?.[i.id];
      const o = el('input'), r = el('input'); o.type = r.type = 'checkbox';
      o.checked = i.orden; o.disabled = !i.orden; r.checked = i.codigo; r.disabled = !i.codigo;
      marcas.set(i.id, { o, r });
      const lo = el('label', null, 'chip-casilla'), lr = el('label', null, 'chip-casilla');
      lo.append(o, document.createTextNode(' Ordenar')); lr.append(r, document.createTextNode(' Renumerar'));
      t.append(el('strong', (i.tipo === 'partidas' ? 'Orden de las partidas' : 'Actividades de ' + i.titulo)), el('small', (i.orden ? 'Cambia el orden' : 'Orden igual') + ' · ' + (i.codigo ? 'cambian códigos' : 'códigos igual'), 'tenue'));
      if (nota?.texto) t.append(el('small', '🤖 ' + nota.texto));
      if (nota?.advertencia) t.append(el('small', '⚠ ' + nota.advertencia, 'aviso'));
      const ver = el('button', 'Ver cambios', 'sec ens-ver'); ver.type = 'button';
      const det = el('pre', i.filas.map(f => `${f.a}. ${f.codigo} → ${f.nuevo}  ${f.nombre}  [${f.inicio || 'sin fecha'}${f.de !== f.a ? ' · antes en ' + f.de : ''}]`).join('\n'), 'ens-det'); det.hidden = true;
      ver.addEventListener('click', () => { det.hidden = !det.hidden; ver.textContent = det.hidden ? 'Ver cambios' : 'Ocultar'; });
      t.append(ver, det);
      const cab = el('div', null, 'fila'); cab.append(lo, lr);
      fila.append(t, cab); caja.append(fila);
    }
    const res = el('div', null, 'ens-res'), botones = el('div', null, 'fila');
    const aplicar = el('button', 'Aplicar'), descartar = el('button', 'Descartar', 'sec'), todo = el('button', 'Marcar todo', 'sec'), nada = el('button', 'Quitar todo', 'sec');
    for (const b of [aplicar, descartar, todo, nada]) b.type = 'button';
    const elegidos = () => [...marcas].map(([id, m]) => ({ id, ordenar: m.o.checked, renumerar: m.r.checked })).filter(x => x.ordenar || x.renumerar);
    const act = () => { const n = elegidos().length; aplicar.textContent = 'Aplicar (' + n + ' grupo' + (n === 1 ? '' : 's') + ')'; aplicar.disabled = !n; };
    for (const m of marcas.values()) for (const c of [m.o, m.r]) c.addEventListener('change', act);
    todo.addEventListener('click', () => { for (const m of marcas.values()) for (const c of [m.o, m.r]) if (!c.disabled) c.checked = true; act(); });
    nada.addEventListener('click', () => { for (const m of marcas.values()) for (const c of [m.o, m.r]) c.checked = false; act(); });
    act();
    const bloquear = v => { for (const b of [aplicar, descartar, todo, nada]) b.disabled = v; if (!v) act(); };
    aplicar.addEventListener('click', async () => {
      bloquear(true);
      try {
        const r = await pw.ord.aplicar(pid, p.huella, elegidos());
        caja.replaceChildren(el('strong', '✔ Aplicado'), el('p', (r.cambios?.codigos || 0) + ' código(s) cambiados y ' + (r.cambios?.movidas || 0) + ' actividad(es) con otra posición.'));
        const f = el('div', null, 'fila'), und = el('button', 'Deshacer', 'sec'), web = el('button', 'Ver programa', 'sec'); und.type = web.type = 'button';
        und.addEventListener('click', async () => { und.disabled = true; try { await pw.ord.deshacer(pid, r.deshacer_id); caja.replaceChildren(el('p', '↩ Orden y numeración anteriores restaurados.')); alCambiar(); } catch (e) { caja.append(el('small', '✘ ' + err(e), 'aviso')); und.disabled = false; } });
        web.addEventListener('click', () => abrirWeb('/programa?proyecto=' + pid, '/programa'));
        f.append(und, web); caja.append(f); alCambiar();
      } catch (e) { res.textContent = '✘ ' + err(e); bloquear(false); }
    });
    descartar.addEventListener('click', async () => { bloquear(true); try { await pw.ord.rechazar(pid, p.modelo); caja.replaceChildren(el('small', 'Propuesta descartada.', 'tenue')); } catch (e) { res.textContent = '✘ ' + err(e); bloquear(false); } });
    if (p.items.length) botones.append(aplicar, todo, nada, descartar);
    caja.append(res, botones);
    if (p.deshacer) {
      const u = el('div', null, 'fila'), b = el('button', 'Deshacer el último orden guardado', 'sec'); b.type = 'button';
      u.append(el('small', 'Último orden: ' + p.deshacer.resumen, 'tenue'), b);
      b.addEventListener('click', async () => { b.disabled = true; try { await pw.ord.deshacer(pid, p.deshacer.id); res.textContent = '↩ Deshecho. Calcula la propuesta de nuevo.'; alCambiar(); } catch (e) { res.textContent = '✘ ' + err(e); b.disabled = false; } });
      caja.append(u);
    }
    return caja;
  }

  async function calcular() {
    if (ocupado) return;
    const pid = $('#ord-proyecto').value, msg = $('#ord-msg');
    if (!pid) { msg.textContent = 'Elige un proyecto.'; return; }
    ocupado = true; $('#ord-ir').disabled = true; $('#ord-barra').hidden = false; $('#ord-barra').removeAttribute('value'); $('#ord-res').replaceChildren();
    try {
      const p = await pw.ord.propuesta(pid, $('#ord-conexion').value || null);
      msg.textContent = '';
      $('#ord-res').append(tarjeta(p, pid));
    } catch (e) { msg.textContent = '✘ ' + err(e); }
    ocupado = false; $('#ord-ir').disabled = false; $('#ord-barra').hidden = true;
  }

  async function pintar() {
    if (!listo) {
      listo = true;
      $('#ord-ir').addEventListener('click', calcular);
      $('#ord-proyecto').addEventListener('change', () => { if (typeof fijarProyectoMenu === 'function') fijarProyectoMenu($('#ord-proyecto').value); $('#ord-res').replaceChildren(); });
      pw.ord.alAvance(p => { if (p.texto) $('#ord-msg').textContent = p.texto; });
    }
    const cfg = await pw.config();
    const sel = $('#ord-conexion'), previa = sel.value;
    sel.replaceChildren(el('option', 'Sin IA (solo el orden por fechas)'), ...(cfg?.proveedores || []).map(p => { const o = el('option', p.nombre + (p.modelo ? ' · ' + p.modelo : '')); o.value = p.id; return o; }));
    sel.firstChild.value = '';
    const pref = cfg?.porTarea?.avanzada;
    sel.value = previa && [...sel.options].some(o => o.value === previa) ? previa : ((cfg?.proveedores || []).some(p => p.id === pref) ? pref : (cfg?.activa || ''));
    if (!proyectos) { try { proyectos = await pw.proyectosPrograma(); } catch (e) { $('#ord-msg').textContent = '✘ ' + err(e); proyectos = null; } }
    const sp = $('#ord-proyecto'), previo = sp.value;
    sp.replaceChildren(...(proyectos || []).map(p => { const o = el('option', p.codigo + ' · ' + p.nombre); o.value = p.id; return o; }));
    const deseado = (typeof proyectoIA === 'function' && proyectoIA()) || previo;
    if (deseado && [...sp.options].some(o => String(o.value) === String(deseado))) sp.value = deseado;
  }

  return { pintar, alAbrirWeb: fn => { abrirWeb = fn; }, alCambiar: fn => { alCambiar = fn; } };
})();
