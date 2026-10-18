'use strict';
// Centro de IA dentro de la app: resultados de cada herramienta (guardar lote, aceptar/rechazar), guardado y espacio, propuestas y auditoría.
// Todo se lee del servidor (/api/ia/*); aquí solo se dibuja. Nada cambia datos sin que el admin pulse Aceptar.
const Centro = (() => {
  const el = (tag, txt, cls) => { const e = document.createElement(tag); if (txt != null) e.textContent = txt; if (cls) e.className = cls; return e; };
  const err = e => String(e && e.message || e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');
  const fecha = f => { const d = new Date(String(f).replace(' ', 'T')); return isNaN(d) ? String(f || '') : d.toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }); };
  const bytes = n => n >= 1073741824 ? (n / 1073741824).toFixed(2) + ' GB' : n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(0, Math.round(n / 1024)) + ' KB';
  const TITULOS = { inconsistencias: 'Inconsistencias de proyecto', materiales: 'Análisis de materiales', programador: 'Programador de actividades', normativa: 'Normativa y especificaciones' };
  const SEV = { critica: 'Crítica', alta: 'Alta', media: 'Media', baja: 'Baja' };
  let abrirWeb = () => {};

  const api = (m, ruta, p) => pw.apiIA(m, ruta, p);
  const boton = (txt, fn, sec = true) => { const b = el('button', txt, sec ? 'sec' : ''); b.type = 'button'; b.addEventListener('click', async () => { b.disabled = true; try { await fn(b); } finally { b.disabled = false; } }); return b; };
  const aviso = (cont, txt, ok) => { let a = cont.querySelector(':scope > .aviso'); if (!a) { a = el('p', null, 'msg aviso'); cont.prepend(a); } a.textContent = txt; a.style.color = ok ? '#22c55e' : '#ef4444'; };

  const ACCIONES = { nota: 'Solo informativa (sin cambio automático)', vincular_normativa: 'Vincular la cláusula al elemento', aceptar_partida: 'Aceptar partida por partida en el Programador' };
  const TIPOS = { FC: 'Fin → Inicio (FC)', CC: 'Inicio → Inicio (CC)', FF: 'Fin → Fin (FF)', CF: 'Inicio → Fin (CF)' };
  const dia = f => f ? String(f).slice(8, 10) + '/' + String(f).slice(5, 7) + '/' + String(f).slice(2, 4) : '—';
  /** Actividades implicadas: partida, fechas, avance, mini Gantt y relaciones entre ellas */
  function detalleActividades(d) {
    const cont = el('div', null, 'hall-acts'), a = d.actividades, ms = f => Date.parse(f + 'T00:00:00');
    const ini = Math.min(...a.map(x => ms(x.inicio)).filter(n => !isNaN(n))), fin = Math.max(...a.map(x => ms(x.fin)).filter(n => !isNaN(n))), rango = Math.max(fin - ini, 864e5);
    const tabla = el('table', null, 'res-tabla');
    const th = el('tr'); ['Código', 'Actividad', 'Partida', 'Inicio', 'Fin', 'Días', 'Avance', 'Cronograma'].forEach(h => th.append(el('th', h))); tabla.append(th);
    for (const x of a) {
      const tr = el('tr'), pg = el('div', null, 'barra-prog'), i = el('i'); i.style.width = Math.min(100, x.progreso) + '%'; pg.append(i, el('span', Math.round(x.progreso) + '%'));
      const gt = el('div', null, 'mini-gantt'), b = el('i', null, x.hito ? 'hito' : ''); b.style.left = ((ms(x.inicio) - ini) / rango * 100) + '%'; b.style.width = Math.max(x.hito ? 0 : 2, (ms(x.fin) - ms(x.inicio)) / rango * 100) + '%'; if (x.color) b.style.background = x.color; gt.append(b);
      const c = (t, n) => { const e = el('td', t); if (n) e.className = n; return e; };
      const tp = el('td'); tp.append(pg);
      const tg = el('td'); tg.append(gt);
      tr.append(c(x.codigo, 'cod'), c(x.nombre), c(x.partida_codigo + ' · ' + x.partida), c(dia(x.inicio)), c(dia(x.fin)), c(String(x.dur)), tp, tg); tabla.append(tr);
    }
    const w = el('div', null, 'res-tabla-wrap'); w.append(tabla); cont.append(el('strong', 'Actividades implicadas'), w);
    if (d.relaciones.length) {
      const nom = id => { const x = a.find(y => y.id === id); return x ? x.codigo + ' ' + x.nombre : '#' + id; };
      const ul = el('ul'); for (const r of d.relaciones) ul.append(el('li', nom(r.pred) + '  →  ' + nom(r.suc) + ' · ' + (TIPOS[r.tipo] || r.tipo) + (r.retraso ? ' · retraso ' + r.retraso + ' d' : '')));
      cont.append(el('strong', 'Relaciones existentes entre ellas'), ul);
    }
    return cont;
  }
  /** Un hallazgo: resumen + «Ver a Detalle» (expande hacia abajo con toda la información) + Aceptar / Rechazar */
  function hallazgo(it, pid, cont, alCambiar) {
    const d = el('div', null, 'hallazgo sev-' + it.severidad), cab = el('div', null, 'hall-cab'), t = el('div');
    t.append(el('strong', it.titulo), el('small', (SEV[it.severidad] || it.severidad) + ' · ' + it.categoria + ' · ' + (it.origen === 'ia' ? 'propuesto por la IA' : 'regla del sistema')));
    const acc = el('div', null, 'fila'), det = el('div', null, 'hall-det');
    det.hidden = true;
    const fila = (etq, txt) => { if (!txt) return; const f = el('p'); f.append(el('strong', etq + ': '), document.createTextNode(txt)); det.append(f); };
    fila('Descripción', it.descripcion); fila('Solución propuesta', it.solucion); fila('Qué hará al aceptar', ACCIONES[it.accion] || (it.accion && it.accion !== 'nota' ? 'Aplicar «' + it.accion.replace(/_/g, ' ') + '»' : '')); fila('Categoría', it.categoria); fila('Severidad', SEV[it.severidad] || it.severidad); fila('Origen', it.origen === 'ia' ? 'Propuesto por la IA' : 'Regla del sistema'); fila('Detectado', it.fecha ? fecha(it.fecha) : '');
    if (it.detalle && it.detalle.actividades && it.detalle.actividades.length) det.append(detalleActividades(it.detalle));
    const ver = boton('Ver a Detalle', async b => { det.hidden = !det.hidden; d.classList.toggle('abierto', !det.hidden); b.textContent = det.hidden ? 'Ver a Detalle' : 'Ocultar detalle'; });
    acc.append(ver);
    if (it.resoluble) {
      acc.append(boton('Aceptar', async () => { try { await api('POST', '/propuestas-ia/aceptar', { proyecto: pid, herramienta: it.herramienta, id: it.id }); d.remove(); alCambiar?.(); } catch (x) { aviso(cont, '✘ ' + err(x)); } }, false),
        boton('Rechazar', async () => { try { await api('POST', '/propuestas-ia/rechazar', { proyecto: pid, herramienta: it.herramienta, id: it.id }); d.remove(); alCambiar?.(); } catch (x) { aviso(cont, '✘ ' + err(x)); } }));
    } else acc.append(boton('Abrir propuesta', async () => abrirWeb(it.url, '/programador')));
    cab.append(t, acc); d.append(cab, det); return d;
  }

  /** Hallazgos pendientes de una herramienta, con Aceptar / Rechazar en la misma pantalla */
  async function pendientes(cont, herr, pid, alCambiar) {
    cont.replaceChildren(el('p', 'Cargando hallazgos…', 'nota'));
    let r;
    try { r = await api('GET', '/api/ia/pendientes', { proyecto: pid, herramienta: herr }); } catch (x) { cont.replaceChildren(el('p', '✘ ' + err(x), 'msg')); return; }
    if (!r.items.length) { cont.replaceChildren(el('p', '✔ No quedan hallazgos pendientes.', 'nota')); return; }
    cont.replaceChildren(...r.items.map(it => hallazgo(it, pid, cont, alCambiar)));
  }

  function tarjeta(a, pid, refrescar) {
    const d = el('div', null, 'analisis' + (a.guardada ? ' guardada' : '')), t = el('div');
    t.append(el('strong', (a.guardada ? '💾 ' : '') + (a.nombre || 'Análisis del ' + fecha(a.creado))),
      el('small', fecha(a.creado) + (a.modelo ? ' · ' + a.modelo : '') + (a.nota ? ' · ' + a.nota : ' · ' + a.pendientes + ' por revisar · ' + a.aceptadas + ' aceptadas · ' + a.rechazadas + ' rechazadas · ' + a.total + ' en total')));
    const acc = el('div', null, 'fila');
    if (a.guardada) acc.append(boton('Quitar de guardados', async () => { await api('POST', '/api/ia/biblioteca', { proyecto: pid, herramienta: a.herramienta, id: a.id, accion: 'quitar' }); refrescar(); }));
    else {
      const nombre = el('input'); nombre.placeholder = 'Nombre del lote'; nombre.value = (TITULOS[a.herramienta] || '').split(' ')[0] + ' ' + fecha(a.creado); nombre.maxLength = 150; nombre.hidden = true;
      const guardar = boton('💾 Guardar lote', async b => {
        if (nombre.hidden) { nombre.hidden = false; nombre.select(); b.textContent = 'Confirmar guardado'; return; }
        await api('POST', '/api/ia/biblioteca', { proyecto: pid, herramienta: a.herramienta, id: a.id, accion: 'guardar', nombre: nombre.value }); refrescar();
      }, false);
      acc.append(nombre, guardar);
    }
    acc.append(boton('Eliminar', async () => { if (!confirm('¿Eliminar este análisis? Los cambios ya aplicados al proyecto se conservan en la auditoría.')) return; await api('POST', '/api/ia/biblioteca', { proyecto: pid, herramienta: a.herramienta, id: a.id, accion: 'eliminar' }); refrescar(); }));
    d.append(t, acc); return d;
  }

  /** Panel de resultados dentro de la sección de una herramienta */
  async function resultados(cont, herr, pid, opts = {}) {
    if (!pid) { cont.replaceChildren(); return; }
    let r;
    try { r = await api('GET', '/api/ia/centro', { proyecto: pid }); } catch (x) { cont.replaceChildren(el('p', '✘ No se pudieron leer los resultados: ' + err(x), 'msg')); return; }
    const lista = r.biblioteca.filter(a => a.herramienta === herr), refrescar = () => { resultados(cont, herr, pid, { ...opts, abierto: undefined }); opts.alCambiar?.(); };
    const esp = r.espacio.find(e => e.clave === herr), caja = el('div', null, 'res-caja');
    caja.append(el('h3', 'Resultados y guardado'));
    caja.append(el('p', (lista.length ? lista.length + ' análisis en el historial (' + lista.filter(a => a.guardada).length + ' guardados con nombre)' : 'Aún no hay análisis de esta herramienta en el proyecto') + ' · Pendientes por resolver: ' + (r.pendientes[herr] || 0) + (esp ? ' · Espacio que ocupa: ' + bytes(esp.bytes) : ''), 'nota'));
    if (lista.length) {
      const ultimo = lista[0], zona = el('div', null, 'pend');
      caja.append(el('h4', 'Último análisis'), tarjeta(ultimo, pid, refrescar));
      const ver = boton((r.pendientes[herr] ? 'Ver ' + r.pendientes[herr] + ' hallazgo(s) pendientes' : 'Ver hallazgos'), async b => { zona.hidden = !zona.hidden; if (!zona.hidden) await pendientes(zona, herr, pid, refrescar); b.textContent = zona.hidden ? 'Ver hallazgos pendientes' : 'Ocultar hallazgos'; }, false);
      zona.hidden = true; caja.append(ver, zona);
      if (opts.abierto || (r.pendientes[herr] && opts.abrirPendientes)) { zona.hidden = false; ver.textContent = 'Ocultar hallazgos'; pendientes(zona, herr, pid, refrescar); }
      if (lista.length > 1) { const det = el('details'); det.append(el('summary', 'Análisis anteriores (' + (lista.length - 1) + ')'), ...lista.slice(1).map(a => tarjeta(a, pid, refrescar))); caja.append(det); }
    }
    cont.replaceChildren(caja);
  }

  /** Sección «Guardado y espacio» */
  async function guardado(cont, pid) {
    if (!pid) { cont.replaceChildren(el('p', 'Elige un proyecto.', 'nota')); return; }
    cont.replaceChildren(el('p', 'Cargando…', 'nota'));
    let r;
    try { r = await api('GET', '/api/ia/centro', { proyecto: pid, completo: 1 }); } catch (x) { cont.replaceChildren(el('p', '✘ ' + err(x), 'msg')); return; }
    const refrescar = () => guardado(cont, pid), partes = [];
    const uso = el('div', null, 'prov'), t = el('div'), barra = el('progress'); barra.max = r.proyecto_limite || 1; barra.value = Math.min(r.proyecto_usado, r.proyecto_limite);
    t.append(el('strong', 'Espacio del proyecto: ' + bytes(r.proyecto_usado) + ' de ' + bytes(r.proyecto_limite) + ' (' + Math.round(r.proyecto_usado / (r.proyecto_limite || 1) * 100) + ' %)'), barra,
      el('small', 'De eso, lo que guardan las herramientas de IA (base de datos, grabaciones y normativa): ' + bytes(r.espacio_ia) + '.'));
    uso.append(t); partes.push(uso);
    partes.push(el('h3', 'Espacio por herramienta de IA'));
    const max = Math.max(1, ...r.espacio.map(e => e.bytes));
    const tabla = el('div', null, 'espacio');
    for (const e of r.espacio) { const f = el('div', null, 'esp-fila'), b = el('progress'); b.max = max; b.value = e.bytes; f.append(el('span', e.titulo), b, el('strong', bytes(e.bytes))); tabla.append(f); }
    partes.push(tabla);
    const guardadas = r.biblioteca.filter(a => a.guardada), otras = r.biblioteca.filter(a => !a.guardada);
    partes.push(el('h3', 'Lotes guardados (' + guardadas.length + ')'));
    partes.push(...(guardadas.length ? guardadas.map(a => { const c = tarjeta(a, pid, refrescar); c.querySelector('strong').textContent += ' · ' + a.herramienta_titulo; return c; }) : [el('p', 'Todavía no guardaste ningún lote. Entra a una herramienta (p. ej. Inconsistencias de proyecto) y pulsa «Guardar lote» en sus resultados.', 'nota')]));
    partes.push(el('h3', 'Análisis recientes sin guardar (' + otras.length + ')'), el('p', 'Los análisis sin guardar rotan con los más recientes; guarda los que quieras conservar.', 'nota'));
    partes.push(...otras.map(a => { const c = tarjeta(a, pid, refrescar); c.querySelector('strong').textContent += ' · ' + a.herramienta_titulo; return c; }));
    cont.replaceChildren(...partes);
  }

  /** Sección «Análisis inteligente»: gráfico circular con los elementos por tema; al elegir un tema se ve su detalle. Cada tema se carga y recarga por separado */
  const TEMAS = [['inconsistencias', 'Inconsistencias', '#ea580c'], ['materiales', 'Análisis de materiales', '#0284c7'], ['programador', 'Programador', '#7c3aed'], ['normativa', 'Normativa', '#059669'], ['riesgos', 'Riesgos por patrón', '#dc2626'], ['causas', 'Causas de retraso', '#d97706']];
  const USO = { sin_ejecutar: 'Sin ejecutar', al_dia: 'Al día', desactualizada: 'Desactualizada' };
  const COLOR_SEG = ['#ea580c', '#0284c7', '#7c3aed', '#059669', '#dc2626', '#d97706', '#0d9488', '#64748b'], COLOR_SEV = { critica: '#991b1b', alta: '#dc2626', media: '#d97706', baja: '#0284c7' };
  const estado = { pid: null, datos: {}, items: {}, abierto: null, seg: null };
  const NS = 'http://www.w3.org/2000/svg';

  function donut(partes, alClic, activa, mini) {
    const svg = document.createElementNS(NS, 'svg'), r = 46, c = 2 * Math.PI * r, total = partes.reduce((a, p) => a + p.valor, 0);
    svg.setAttribute('viewBox', '0 0 120 120'); svg.setAttribute('class', 'ai-donut' + (mini ? ' mini' : ''));
    const circ = cls => { const k = document.createElementNS(NS, 'circle'); k.setAttribute('cx', 60); k.setAttribute('cy', 60); k.setAttribute('r', r); k.setAttribute('class', cls); return k; };
    svg.append(circ('base'));
    let acum = 0;
    for (const p of partes) {
      if (!p.valor) continue;
      const len = p.valor / total * c, k = circ('arco' + (activa === p.clave ? ' activa' : '')), t = document.createElementNS(NS, 'title');
      k.setAttribute('stroke', p.color); k.setAttribute('stroke-dasharray', Math.max(len - (partes.length > 1 ? 1.2 : 0), 0.5) + ' ' + c); k.setAttribute('stroke-dashoffset', -acum); k.setAttribute('transform', 'rotate(-90 60 60)');
      t.textContent = p.etiqueta + ': ' + p.valor; k.append(t); if (alClic) k.addEventListener('click', () => alClic(p.clave));
      svg.append(k); acum += len;
    }
    return svg;
  }

  /** Anillos concéntricos (estilo CodeLearning): un anillo por tema, su largo es proporcional al tema con más elementos; el color es el de cada herramienta y el estado del lote se ve en la opacidad/pulso */
  const DEG = 270, GRAD = { inconsistencias: ['#ea580c', '#fbbf24'], materiales: ['#0284c7', '#22d3ee'], programador: ['#7c3aed', '#c026d3'], normativa: ['#059669', '#84cc16'], riesgos: ['#dc2626', '#fb7185'], causas: ['#d97706', '#fcd34d'] };
  const polar = (cx, cy, r, deg) => { const a = deg * Math.PI / 180; return [cx + r * Math.sin(a), cy - r * Math.cos(a)]; };
  const arco = (cx, cy, r, deg) => { const [x0, y0] = polar(cx, cy, r, 0), [x1, y1] = polar(cx, cy, r, deg); return 'M' + x0.toFixed(2) + ' ' + y0.toFixed(2) + ' A' + r + ' ' + r + ' 0 ' + (deg > 180 ? 1 : 0) + ' 1 ' + x1.toFixed(2) + ' ' + y1.toFixed(2); };
  function anillos(partes, alClic, activa, lote) {
    const mk = (tag, at, padre) => { const n = document.createElementNS(NS, tag); for (const k in at) n.setAttribute(k, at[k]); if (padre) padre.append(n); return n; };
    const cx = 150, cy = 150, inner = 66, outer = 140, pitch = Math.min(14, (outer - inner) / partes.length), w = pitch - 3.5, max = Math.max(1, ...partes.map(p => p.valor));
    const svg = mk('svg', { viewBox: '0 0 300 300', class: 'ai-anillos', role: 'img', 'aria-label': 'Elementos por tema' }), defs = mk('defs', {}, svg);
    partes.forEach((p, i) => {
      const [c1, c2] = GRAD[p.clave] || [p.color, p.color], g = mk('linearGradient', { id: 'ar' + i, x1: 0, y1: 0, x2: 1, y2: 1 }, defs);
      mk('stop', { offset: 0, 'stop-color': c1 }, g); mk('stop', { offset: 1, 'stop-color': c2 }, g);
    });
    mk('circle', { cx, cy, r: outer + 8, class: 'fondo' }, svg);
    partes.forEach((p, i) => {
      const r = outer - i * pitch - w / 2, est = lote[p.clave] || 'espera', g = mk('g', { class: 'anillo ' + est + (activa === p.clave ? ' activa' : '') }, svg), t = mk('title', {}, g);
      t.textContent = p.etiqueta + ': ' + p.valor; g.addEventListener('click', () => alClic(p.clave));
      mk('path', { d: arco(cx, cy, r, DEG), class: 'pista', 'stroke-width': w }, g);
      mk('path', { d: arco(cx, cy, r, Math.max(p.valor ? DEG * p.valor / max : 0, p.valor ? 6 : 0.01)), class: 'relleno', stroke: 'url(#ar' + i + ')', 'stroke-width': w }, g);
      const ty = cy - r, nom = mk('text', { x: cx - 8, y: ty + 2.6, class: 'nom', 'text-anchor': 'end' }, g);
      nom.textContent = p.etiqueta + ' · ' + p.valor; nom.style.fill = p.color;
      const [bx, by] = polar(cx, cy, r, DEG), ins = mk('circle', { cx: bx, cy: by, r: Math.max(5.5, w / 2 + 1), fill: 'url(#ar' + i + ')', class: 'insignia' }, g);
      mk('text', { x: bx, y: by + 2.4, class: 'num', 'text-anchor': 'middle' }, g).textContent = String(i + 1);
    });
    return svg;
  }
  function leyenda(partes, alClic, activa) {
    const ul = el('ul', null, 'ai-ley');
    for (const p of partes) {
      const li = el('li'), b = el('button', null, activa === p.clave ? 'activa' : ''), sw = el('span', null, 'sw');
      b.type = 'button'; sw.style.background = p.color; b.append(sw, el('span', (p.num ? p.num + '. ' : '') + p.etiqueta), el('strong', String(p.valor))); b.addEventListener('click', () => alClic(p.clave)); li.append(b); ul.append(li);
    }
    return ul;
  }

  async function cargarTema(clave, pid) {
    const [r, pend] = await Promise.all([api('GET', '/api/ia/analisis', { proyecto: pid, clave }), ['riesgos', 'causas'].includes(clave) ? null : api('GET', '/api/ia/pendientes', { proyecto: pid, herramienta: clave })]);
    estado.datos[clave] = r.seccion; if (pend) estado.items[clave] = pend.items;
  }

  async function propuestas(cont, pid, alCambiar) {
    if (!pid) { cont.replaceChildren(el('p', 'Elige un proyecto.', 'nota')); return; }
    if (estado.pid !== pid) Object.assign(estado, { pid, datos: {}, items: {}, abierto: null, seg: null });
    const cargando = new Set(), cambiar = () => { alCambiar?.(); }, lote = { estados: {}, corriendo: false, texto: '', cancelar: () => {} };
    /** Analizar por lotes: recorre los temas en orden; los que tienen herramienta de IA propia la ejecutan con la conexión principal y todos se recargan al terminar */
    const EJECUTA = { inconsistencias: (p, c) => pw.analizarInconsistencias(p, c), materiales: (p, c) => pw.analizarMateriales(p, c), programador: (p, c) => pw.analizarPrograma(p, c) };
    const CANCELA = { inconsistencias: () => pw.cancelarInconsistencias(), materiales: () => pw.cancelarMateriales(), programador: () => pw.cancelarAnalisis() };
    const correrLote = async () => {
      const c = await pw.config(), conexion = c.activa || (c.proveedores[0] || {}).id;
      let paro = false, actual = null; lote.corriendo = true; lote.estados = {}; lote.cancelar = () => { paro = true; lote.texto = 'Cancelando…'; if (actual && CANCELA[actual]) CANCELA[actual](); dibujar(); };
      for (const [i, [k, n]] of TEMAS.entries()) {
        if (paro) break;
        actual = k; lote.estados[k] = 'analizando'; lote.texto = (i + 1) + '/' + TEMAS.length + ' · ' + n; dibujar();
        try { if (EJECUTA[k] && conexion) await EJECUTA[k](pid, conexion); await cargarTema(k, pid); lote.estados[k] = 'listo'; } catch (x) { delete lote.estados[k]; aviso(cont, '✘ ' + n + ': ' + err(x)); }
        dibujar();
      }
      actual = null; lote.corriendo = false; cambiar(); dibujar();
      if (!conexion) aviso(cont, 'Sin conexión de IA guardada: solo se recargaron los datos de cada tema.', true);
    };
    const dibujar = () => {
      const partes = TEMAS.map(([k, n, c], i) => ({ clave: k, etiqueta: n, valor: estado.datos[k]?.total || 0, color: c, num: i + 1 })), listos = TEMAS.filter(([k]) => estado.datos[k]).length, total = partes.reduce((a, p) => a + p.valor, 0);
      const elegir = k => { estado.abierto = k; estado.seg = null; dibujar(); };
      const caja = el('div', null, 'ai-donut-caja'), centro = el('div', null, 'ai-centro');
      const hechos = TEMAS.filter(([k]) => lote.estados[k] === 'listo').length, btn = el('button', null, 'ai-lote' + (lote.corriendo ? ' corriendo' : hechos === TEMAS.length ? ' listo' : '')); btn.type = 'button';
      btn.append(el('strong', lote.corriendo ? 'Analizando…' : hechos === TEMAS.length ? 'Listo ✔' : 'Analizar por lotes'), el('small', lote.corriendo ? lote.texto : listos < TEMAS.length ? listos + '/' + TEMAS.length + ' cargados' : total + ' elementos'));
      btn.title = lote.corriendo ? 'Pulsa para cancelar' : 'Analiza una por una las herramientas de IA del proyecto y actualiza cada tema';
      btn.addEventListener('click', () => lote.corriendo ? lote.cancelar() : correrLote());
      centro.append(btn);
      caja.append(anillos(partes, elegir, estado.abierto, lote.estados), centro);
      const resumen = el('div', null, 'ai-resumen'); resumen.append(caja, leyenda(partes, elegir, estado.abierto));
      const tarjetas = el('div', null, 'ai-tarjetas');
      for (const [k, n, c] of TEMAS) {
        const s = estado.datos[k], t = el('div', null, 'ai-tarjeta' + (estado.abierto === k ? ' activa' : '') + (cargando.has(k) ? ' cargando' : '')), ab = el('button', null, 'ai-abrir');
        t.style.setProperty('--tema', c); ab.type = 'button'; ab.append(el('span', n), el('strong', s ? String(s.total) : '…')); ab.addEventListener('click', () => elegir(k));
        const pie = el('div', null, 'ai-pie'), re = el('button', '↻', 'ai-rec'); re.type = 'button'; re.title = 'Recargar este tema';
        re.addEventListener('click', async () => { cargando.add(k); dibujar(); try { await cargarTema(k, pid); } catch (x) { aviso(cont, '✘ ' + err(x)); } cargando.delete(k); dibujar(); });
        pie.append(el('small', s ? (USO[s.uso.estado] || '') + (s.uso.ejecutada ? ' · ' + String(s.uso.ejecutada).slice(0, 10) : '') : ''), re);
        t.append(ab, pie); tarjetas.append(t);
      }
      const partesDom = [resumen, tarjetas];
      const s = estado.datos[estado.abierto];
      if (s) partesDom.push(detalle(s));
      else partesDom.push(el('p', 'Elige un tema (en el círculo o en las tarjetas) para ver su detalle.', 'nota'));
      cont.replaceChildren(...partesDom);
    };
    const detalle = s => {
      const d = el('div', null, 'ai-detalle'), partes = s.segmentos.map((g, i) => ({ clave: g.clave, etiqueta: g.etiqueta, valor: g.valor, color: COLOR_SEV[g.clave] || COLOR_SEG[i % COLOR_SEG.length] }));
      const filtrar = k => { estado.seg = estado.seg === k ? null : k; dibujar(); };
      d.style.setProperty('--tema', s.color);
      const graf = el('div', null, 'ai-graf'); graf.append(donut(partes, filtrar, estado.seg, true), leyenda(partes, filtrar, estado.seg));
      d.append(el('h3', s.titulo + ' (' + s.total + ')'), el('p', s.resumen, 'nota'), partes.length ? graf : el('span'));
      const items = estado.items[s.clave];
      if (items) {
        const vis = items.filter(i => !estado.seg || i.severidad === estado.seg);
        d.append(...(vis.length ? vis.map(it => hallazgo(it, estado.pid, d, () => { cambiar(); cargarTema(s.clave, estado.pid).then(dibujar); })) : [el('p', s.total ? 'Nada con este filtro.' : '✔ Sin propuestas pendientes.', 'nota')]));
      } else {
        const vis = s.items.filter(i => !estado.seg || (i.tipos || []).includes(estado.seg) || i.sev === estado.seg);
        d.append(...(vis.length ? vis.slice(0, 80).map(i => { const h = el('div', null, 'hallazgo sev-' + (i.sev || 'baja')), t = el('div'); t.append(el('strong', i.titulo), el('small', i.meta + ' · ' + i.etiqueta), el('small', i.texto, 'sol')); h.append(t); return h; }) : [el('p', 'Sin elementos en este tema.', 'nota')]));
      }
      return d;
    };
    dibujar();
    await Promise.all(TEMAS.map(async ([k]) => { cargando.add(k); try { await cargarTema(k, pid); } catch (x) { aviso(cont, '✘ ' + err(x)); } cargando.delete(k); dibujar(); }));
  }

  /** Sección «Auditoría»: quién aplicó, revirtió o rechazó qué, con filtros y paginación */
  const filtroAud = { herramienta: '', evento: '', q: '', pagina: 1 };
  async function auditoria(cont, pid) {
    if (!pid) { cont.replaceChildren(el('p', 'Elige un proyecto.', 'nota')); return; }
    let r;
    try { r = await api('GET', '/api/ia/auditoria', { proyecto: pid, ...filtroAud }); } catch (x) { cont.replaceChildren(el('p', '✘ ' + err(x), 'msg')); return; }
    const rep = () => auditoria(cont, pid), barra = el('form', null, 'filtros'), sel = (clave, op, vacio) => { const s = el('select'), o0 = el('option', vacio); o0.value = ''; s.append(o0); for (const [k, v] of Object.entries(op)) { const o = el('option', v); o.value = k; s.append(o); } s.value = filtroAud[clave]; s.addEventListener('change', () => { filtroAud[clave] = s.value; filtroAud.pagina = 1; rep(); }); return s; };
    const q = el('input'); q.type = 'search'; q.placeholder = 'Buscar en el resumen…'; q.value = filtroAud.q;
    barra.append(sel('herramienta', r.herramientas, 'Todas las herramientas'), sel('evento', r.eventos, 'Todos los eventos'), q, el('button', 'Buscar'));
    barra.addEventListener('submit', e => { e.preventDefault(); filtroAud.q = q.value; filtroAud.pagina = 1; rep(); });
    const filas = r.filas.length ? r.filas.map(f => {
      const d = el('div', null, 'aud ev-' + f.evento), t = el('div');
      t.append(el('strong', f.resumen || f.herramienta), el('small', fecha(f.creado) + ' · ' + f.usuario + ' · ' + f.herramienta + (f.entidad ? ' · ' + f.entidad : '') + (f.modelo ? ' · ' + f.modelo : '')));
      d.append(t, el('span', f.evento_texto, 'etq-ev')); return d;
    }) : [el('p', 'Sin registros con estos filtros. Aquí aparece cada cambio que aceptas, revierto o rechazas desde las herramientas de IA.', 'nota')];
    const pie = el('div', null, 'fila');
    pie.append(boton('← Anterior', async () => { filtroAud.pagina = Math.max(1, r.pagina - 1); rep(); }), el('span', 'Página ' + r.pagina + ' de ' + r.paginas + ' · ' + r.total + ' registro(s)', 'nota'), boton('Siguiente →', async () => { filtroAud.pagina = Math.min(r.paginas, r.pagina + 1); rep(); }));
    pie.children[0].disabled = r.pagina <= 1; pie.children[2].disabled = r.pagina >= r.paginas;
    cont.replaceChildren(barra, ...filas, pie);
  }

  return { resultados, guardado, propuestas, auditoria, bytes, alAbrirWeb: fn => { abrirWeb = fn; } };
})();
