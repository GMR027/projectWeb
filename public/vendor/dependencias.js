/* Dependencias de obra: diagrama de barras interactivo (SVG) con relaciones, ruta crítica y panel de edición. */
(function () {
  'use strict';
  const D = window.DEP;
  let datos = D.datos;
  const S = { sel: null, selDep: null, enlazar: false, origen: null, ppd: 5, partida: '', solo: false, q: '' };
  const $ = id => document.getElementById(id);
  const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const dn = s => Math.round(Date.UTC(+s.slice(0, 4), +s.slice(5, 7) - 1, +s.slice(8, 10)) / 864e5);
  const fmt = s => s ? s.slice(8, 10) + '/' + s.slice(5, 7) + '/' + s.slice(2, 4) : '—';
  const TIPOS = { FS: 'Fin → inicio', SS: 'Inicio → inicio', FF: 'Fin → fin', SF: 'Inicio → fin' };
  const ROW = 30, GRP = 24, HEAD = 46;
  const esMovil = () => window.matchMedia('(max-width: 64rem)').matches;
  // La columna de nombres se adapta al ancho: en un teléfono 300 px se comían casi todo el diagrama
  const medidaLbl = () => {
    const w = window.innerWidth || 1200;
    return w <= 480 ? Math.max(104, Math.round(w * 0.32)) : w <= 1024 ? 180 : 300;
  };
  let LBL = medidaLbl();
  let autoVista = true;   // mientras el usuario no toque el zoom, la vista se reajusta al girar el teléfono
  const act = id => datos.actividades.find(a => a.id === id);

  // ---------- comunicación con el servidor ----------
  async function api(ruta, cuerpo = {}) {
    const fd = new FormData(); fd.append('proyecto', D.proyecto);
    Object.entries(cuerpo).forEach(([k, v]) => fd.append(k, v));
    try {
      const r = await fetch('/api/dependencias/' + ruta, { method: 'POST', body: fd });
      const d = await r.json();
      if (!d.success) { aviso(d.error || 'No se pudo completar', 'error'); if (d.datos) { datos = d.datos; pintar(); } return null; }
      if (d.datos) { datos = d.datos; }
      return d;
    } catch (e) { aviso('Error de conexión', 'error'); return null; }
  }
  let tAviso = null;
  function aviso(txt, tipo = 'ok', fijo = false) {
    const el = $('dep-aviso'); el.hidden = !txt; el.className = 'dep-aviso dep-aviso-' + tipo; el.innerHTML = txt;
    clearTimeout(tAviso); if (txt && !fijo) tAviso = setTimeout(() => { el.hidden = true; pintarAvisoFijo(); }, 6000);
  }
  function pintarAvisoFijo() {
    const r = datos.resumen;
    if (datos.ciclo) aviso('Hay un ciclo entre estas actividades: ' + datos.ciclo.map(id => esc((act(id) || {}).codigo)).join(', ') + '. Quita una de las relaciones para poder calcular.', 'error', true);
    else if (datos.modo === 'automatico' && r.pendientes > 0) aviso('<strong>' + r.pendientes + '</strong> actividad(es) deben reprogramarse para respetar las relaciones. Usa «Revisar y reprogramar».', 'warn', true);
    else if (datos.modo === 'informativo' && r.conflictos > 0) aviso('<strong>' + r.conflictos + '</strong> actividad(es) empiezan antes de lo que exigen sus predecesoras. En modo informativo no se mueven; puedes corregir sus fechas en el Programa.', 'warn', true);
    else if (datos.modo === 'apagado' && r.relaciones > 0) aviso('El modo está apagado: tus relaciones se guardan pero no se calcula la ruta crítica. Activa «Informativo» para verla.', 'info', true);
    else $('dep-aviso').hidden = true;
  }

  // ---------- encabezado: modos y resumen ----------
  function pintarEncabezado() {
    document.querySelectorAll('.dep-modo').forEach(b => { const on = b.dataset.modo === datos.modo; b.classList.toggle('activo', on); b.setAttribute('aria-checked', on); });
    const r = datos.resumen, dif = r.finProgramado && r.finCalculado ? dn(r.finCalculado) - dn(r.finProgramado) : null;
    $('dep-resumen').innerHTML =
      chip('Relaciones', r.relaciones) + chip('En ruta crítica', datos.modo === 'apagado' ? '—' : r.criticas, r.criticas ? 'crit' : '') + chip('Con conflicto', datos.modo === 'apagado' ? '—' : r.conflictos, r.conflictos ? 'warn' : '') +
      chip(datos.modo === 'apagado' ? 'Fin del programa' : 'Fin actual (actividades enlazadas)', fmt(r.finProgramado)) + chip('Fin calculado', r.finCalculado ? fmt(r.finCalculado) + (dif ? ' (' + (dif > 0 ? '+' : '') + dif + ' d)' : '') : '—', dif > 0 ? 'warn' : '');
    const rep = $('dep-reprogramar'), des = $('dep-deshacer');
    if (rep) { rep.hidden = !(datos.modo === 'automatico' && r.pendientes > 0); }
    if (des) { des.hidden = !(datos.modo === 'automatico' && datos.ultimaAplicacion); }
    pintarAvisoFijo();
  }
  const chip = (t, v, c = '') => '<div class="dep-chip ' + c + '"><span>' + esc(t) + '</span><strong>' + esc(v) + '</strong></div>';

  // ---------- filtros ----------
  function filtradas() {
    const q = S.q.trim().toLowerCase();
    return datos.actividades.filter(a => (!S.partida || String(a.partida_id) === S.partida) && (!S.solo || a.enRed || datos.relaciones.some(r => r.pred === a.id || r.suc === a.id) || a.id === S.sel) && (!q || (a.codigo + ' ' + a.nombre).toLowerCase().includes(q)));
  }
  function pintarPartidas() {
    const sel = $('dep-partida'), vistas = new Map();
    datos.actividades.forEach(a => vistas.set(String(a.partida_id), a.partida));
    sel.innerHTML = '<option value="">Todas</option>' + [...vistas].map(([id, n]) => '<option value="' + id + '"' + (S.partida === id ? ' selected' : '') + '>' + esc(n) + '</option>').join('');
  }

  // ---------- diagrama ----------
  function pintar() {
    pintarEncabezado();
    LBL = medidaLbl();
    $('dep-zoom-pct').textContent = Math.round(S.ppd / PPD_BASE * 100) + '%';
    const lista = filtradas();
    const filas = []; let ult = null;
    lista.forEach(a => { if (a.partida_id !== ult) { filas.push({ g: a.partida }); ult = a.partida_id; } filas.push({ a }); });
    let y = HEAD; const pos = {};
    filas.forEach(f => { f.y = y; y += f.g ? GRP : ROW; if (f.a) pos[f.a.id] = f; });
    const alto = y + 10;
    const fechas = lista.flatMap(a => [a.inicio, a.fin, a.es, a.ef].filter(Boolean));
    if (!fechas.length) { $('dep-labels').innerHTML = ''; $('dep-svg').innerHTML = ''; $('dep-svg').setAttribute('width', 10); pintarPanel(); return; }
    const d0 = Math.min(...fechas.map(dn)) - 7, d1 = Math.max(...fechas.map(dn)) + 10, ppd = S.ppd, ancho = (d1 - d0 + 1) * ppd + 20;
    const X = s => (dn(s) - d0) * ppd, hoy = new Date().toISOString().slice(0, 10);

    $('dep-labels').style.width = LBL + 'px';
    $('dep-labels').innerHTML = '<div class="dep-lab-cab" style="height:' + HEAD + 'px">Actividad</div>' + filas.map(f => f.g
      ? '<div class="dep-lab-grp" style="height:' + GRP + 'px">' + esc(f.g) + '</div>'
      : '<div class="dep-lab' + (f.a.id === S.sel ? ' sel' : '') + (f.a.id === S.origen ? ' origen' : '') + '" style="height:' + ROW + 'px" data-id="' + f.a.id + '" title="' + esc(f.a.codigo + ' · ' + f.a.nombre) + '"><b>' + esc(f.a.codigo) + '</b> ' + esc(f.a.nombre) + (f.a.critica ? ' <i class="dep-pt crit"></i>' : '') + (f.a.conflicto ? ' <i class="dep-pt warn"></i>' : '') + '</div>').join('');

    let s = '';
    // ejes: semanas y meses
    const meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    for (let d = d0; d <= d1; d++) {
      const dt = new Date(d * 864e5), x = (d - d0) * ppd;
      if (dt.getUTCDay() === 1) { s += '<line class="dep-grid" x1="' + x + '" y1="' + (HEAD - 12) + '" x2="' + x + '" y2="' + alto + '"/>'; if (ppd >= 4) s += '<text class="dep-eje" x="' + (x + 2) + '" y="' + (HEAD - 2) + '">' + dt.getUTCDate() + '</text>'; }
      if (dt.getUTCDate() === 1) s += '<line class="dep-grid dep-mes" x1="' + x + '" y1="0" x2="' + x + '" y2="' + alto + '"/><text class="dep-eje dep-eje-mes" x="' + (x + 3) + '" y="14">' + meses[dt.getUTCMonth()] + ' ' + String(dt.getUTCFullYear()).slice(2) + '</text>';
    }
    if (hoy >= '2000') s += '<line class="dep-hoy" x1="' + X(hoy) + '" y1="' + (HEAD - 12) + '" x2="' + X(hoy) + '" y2="' + alto + '"/>';
    filas.forEach(f => { if (f.g) s += '<rect class="dep-fgrp" x="0" y="' + f.y + '" width="' + ancho + '" height="' + GRP + '"/>'; });

    // flechas (debajo de las barras)
    let flechas = '';
    datos.relaciones.forEach(r => {
      const p = pos[r.pred], q = pos[r.suc]; if (!p || !q) return;
      const A = p.a, B = q.a, y1 = p.y + ROW / 2, y2 = q.y + ROW / 2;
      const salidaFin = r.tipo === 'FS' || r.tipo === 'FF', entraInicio = r.tipo === 'FS' || r.tipo === 'SS';
      const x1 = salidaFin ? X(A.fin) + ppd : X(A.inicio), x2 = entraInicio ? X(B.inicio) : X(B.fin) + ppd;
      const d1_ = salidaFin ? 1 : -1, d2_ = entraInicio ? 1 : -1, ym = y1 < y2 ? y1 + ROW / 2 : y1 - ROW / 2;
      const pts = [[x1, y1], [x1 + d1_ * 9, y1], [x1 + d1_ * 9, ym], [x2 - d2_ * 9, ym], [x2 - d2_ * 9, y2], [x2, y2]];
      const crit = A.critica && B.critica && datos.modo !== 'apagado', cls = 'dep-flecha' + (crit ? ' crit' : '') + (r.conflicto ? ' conf' : '') + (r.id === S.selDep ? ' sel' : '') + (datos.modo === 'apagado' ? ' apag' : '');
      flechas += '<path class="' + cls + '" data-dep="' + r.id + '" d="M' + pts.map(p_ => p_.join(',')).join(' L') + '"/>' +
        '<path class="dep-hit" data-dep="' + r.id + '" d="M' + pts.map(p_ => p_.join(',')).join(' L') + '"/>' +
        '<path class="dep-punta' + (crit ? ' crit' : '') + (r.conflicto ? ' conf' : '') + '" d="M' + x2 + ',' + y2 + ' l' + (-d2_ * 7) + ',-4 l0,8 z"/>';
    });
    s += flechas;

    // barras
    lista.forEach(a => {
      const f = pos[a.id], x1 = X(a.inicio), x2 = X(a.fin) + ppd, y_ = f.y + 6, h = ROW - 12;
      const color = a.critica && datos.modo !== 'apagado' ? '#dc2626' : (a.color || '#3b82f6');
      const cls = 'dep-barra-g' + (a.id === S.sel ? ' sel' : '') + (a.id === S.origen ? ' origen' : '') + (a.conflicto ? ' conf' : '');
      s += '<g class="' + cls + '" data-id="' + a.id + '">';
      if (datos.modo !== 'apagado' && a.es && a.es !== a.inicio) s += '<rect class="dep-fantasma" x="' + X(a.es) + '" y="' + (y_ - 2) + '" width="' + Math.max(ppd, (dn(a.ef) - dn(a.es) + 1) * ppd) + '" height="' + (h + 4) + '" rx="3"/>';
      if (a.hito) s += '<path class="dep-hito" d="M' + x1 + ',' + (y_ - 1) + ' l' + (h / 2 + 1) + ',' + (h / 2 + 1) + ' l' + (-(h / 2 + 1)) + ',' + (h / 2 + 1) + ' l' + (-(h / 2 + 1)) + ',' + (-(h / 2 + 1)) + ' z" fill="' + color + '"/>';
      else {
        s += '<rect class="dep-barra" x="' + x1 + '" y="' + y_ + '" width="' + Math.max(3, x2 - x1) + '" height="' + h + '" rx="3" fill="' + color + '"/>';
        if (a.progreso > 0) s += '<rect class="dep-prog" x="' + x1 + '" y="' + (y_ + h - 4) + '" width="' + Math.max(0, (x2 - x1) * a.progreso / 100) + '" height="4" rx="2"/>';
        if (D.puedeEditar) s += '<circle class="dep-asa" cx="' + x2 + '" cy="' + (y_ + h / 2) + '" r="5.5" data-id="' + a.id + '"/>';
      }
      if (a.fija) s += '<text class="dep-pin" x="' + (x1 - 4) + '" y="' + (y_ + h - 2) + '" text-anchor="end">📌</text>';
      if (a.conflicto) s += '<text class="dep-alerta" x="' + (x2 + 14) + '" y="' + (y_ + h - 2) + '">⚠</text>';
      s += '</g>';
    });
    s += '<line id="dep-arrastre" class="dep-arrastre" x1="0" y1="0" x2="0" y2="0" hidden/>';
    const svg = $('dep-svg'); svg.setAttribute('width', ancho); svg.setAttribute('height', alto); svg.innerHTML = s;
    pintarPanel();
  }

  // ---------- zoom ----------
  const PPD_MIN = 0.4, PPD_MAX = 40, PPD_BASE = 5;
  function zoom(nuevo, ancla) {   // ancla: px dentro del visor que deben quedar fijos (por defecto, el centro)
    autoVista = false; LBL = medidaLbl();
    const sc = $('dep-scroll'), vis = sc.clientWidth - LBL, ref = ancla == null ? LBL + vis / 2 : ancla;
    const dia = (sc.scrollLeft + ref - LBL) / S.ppd;       // día (relativo al origen) bajo el ancla
    S.ppd = Math.min(PPD_MAX, Math.max(PPD_MIN, nuevo)); pintar();
    sc.scrollLeft = Math.max(0, dia * S.ppd - (ref - LBL));
  }
  function ajustar() {
    autoVista = false; LBL = medidaLbl();
    const lista = filtradas(), fechas = lista.flatMap(a => [a.inicio, a.fin, a.es, a.ef].filter(Boolean)); if (!fechas.length) return;
    const dias = Math.max(...fechas.map(dn)) - Math.min(...fechas.map(dn)) + 18, vis = $('dep-scroll').clientWidth - LBL - 30;
    S.ppd = Math.min(PPD_MAX, Math.max(PPD_MIN, vis / dias)); pintar(); $('dep-scroll').scrollLeft = 0;
  }
  // En el teléfono el diagrama se abre legible (nunca por debajo de ~3 px/día, ~80 días visibles)
  // y centrado en hoy, en vez de mostrar el primer día con las etiquetas ocupando media pantalla.
  function vistaInicial() {
    if (!esMovil()) { pintar(); return; }
    const lista = filtradas(), fechas = lista.flatMap(a => [a.inicio, a.fin, a.es, a.ef].filter(Boolean));
    if (!fechas.length) { pintar(); return; }
    const sc = $('dep-scroll'); LBL = medidaLbl();
    const dias = fechas.map(dn), d0 = Math.min(...dias) - 7;
    const vis = Math.max(80, sc.clientWidth - LBL - 20), rango = Math.max(...dias) - Math.min(...dias) + 18;
    S.ppd = Math.max(3, Math.min(PPD_BASE, vis / rango));
    pintar();
    const hoy = dn(new Date().toISOString().slice(0, 10)), centro = hoy >= Math.min(...dias) && hoy <= Math.max(...dias) ? hoy : Math.min(...dias);
    sc.scrollLeft = Math.max(0, (centro - d0) * S.ppd - vis * 0.25);
  }

  // ---------- selección y enlace ----------
  function elegir(id) {
    if (S.enlazar && D.puedeEditar) {
      if (S.origen == null) { S.origen = id; aviso('Predecesora: <strong>' + esc(act(id).codigo) + '</strong>. Ahora haz clic en la actividad sucesora (Esc cancela).', 'info', true); pintar(); return; }
      if (S.origen === id) { S.origen = null; pintar(); return; }
      const o = S.origen; S.origen = null; crear(o, id); return;
    }
    S.sel = id; S.selDep = null; pintar();
    const fila = document.querySelector('.dep-lab[data-id="' + id + '"]'); if (fila) fila.scrollIntoView({ block: 'nearest' });
    // En el teléfono el panel queda debajo del diagrama: sin esto el toque parece no hacer nada
    if (esMovil()) setTimeout(() => $('dep-panel').scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 60);
  }
  async function crear(pred, suc, tipo = 'FS', retraso = 0) {
    const d = await api('guardar', { pred, suc, tipo, retraso }); if (!d) { pintar(); return; }
    S.sel = suc; const r = datos.relaciones.find(x => x.pred === pred && x.suc === suc); S.selDep = r ? r.id : null;
    aviso('Relación creada: <strong>' + esc(act(pred).codigo) + '</strong> → <strong>' + esc(act(suc).codigo) + '</strong>. Ajusta el tipo y el retraso en el panel.', 'ok');
    pintar(); pintarPartidas();
  }
  function alternarEnlazar(v) {
    S.enlazar = v === undefined ? !S.enlazar : v; S.origen = null;
    $('dep-enlazar') && $('dep-enlazar').classList.toggle('activo', S.enlazar);
    $('dep-scroll').classList.toggle('enlazando', S.enlazar);
    if (S.enlazar) aviso('Modo enlazar: haz clic en la actividad predecesora y luego en la sucesora (Esc para salir).', 'info', true); else pintarAvisoFijo();
    pintar();
  }

  // ---------- panel de la actividad ----------
  function filaRel(r, lado) {   // lado: 'pred' (esta es sucesora) o 'suc'
    const otra = act(lado === 'pred' ? r.pred : r.suc); if (!otra) return '';
    return '<div class="dep-rel' + (r.conflicto ? ' conf' : '') + (r.id === S.selDep ? ' sel' : '') + '" data-dep="' + r.id + '">' +
      '<a href="#" class="dep-ir" data-id="' + otra.id + '"><b>' + esc(otra.codigo) + '</b> ' + esc(otra.nombre) + '</a>' +
      '<div class="dep-rel-ctl"><select data-campo="tipo" aria-label="Tipo de relación"' + (D.puedeEditar ? '' : ' disabled') + '>' + Object.entries(TIPOS).map(([k, v]) => '<option value="' + k + '"' + (r.tipo === k ? ' selected' : '') + '>' + k + ' · ' + v + '</option>').join('') + '</select>' +
      '<label title="Días hábiles de espera (negativo = adelanto)"><input type="number" data-campo="retraso" value="' + r.retraso + '" min="-365" max="365"' + (D.puedeEditar ? '' : ' disabled') + '> d</label>' +
      (D.puedeEditar ? '<button type="button" class="btn btn-sm btn-ghost dep-del" title="Quitar la relación" aria-label="Quitar"><i class="fa-solid fa-xmark"></i></button>' : '') + '</div>' +
      (r.conflicto ? '<small class="dep-conf-txt">Exige empezar el ' + fmt(r.requerido) + ' o después; hoy empieza antes.</small>' : '') + '</div>';
  }
  function pintarPanel() {
    const p = $('dep-panel'), a = S.sel != null ? act(S.sel) : null;
    if (!a) { p.innerHTML = '<p class="text-muted">Elige una actividad para ver sus predecesoras y sucesoras.</p>'; return; }
    const preds = datos.relaciones.filter(r => r.suc === a.id), sucs = datos.relaciones.filter(r => r.pred === a.id);
    const opciones = quitar => '<option value="">— Elegir actividad —</option>' + datos.actividades.filter(x => x.id !== a.id && !quitar.includes(x.id)).map(x => '<option value="' + x.id + '">' + esc(x.codigo + ' · ' + x.nombre) + '</option>').join('');
    const calc = datos.modo === 'apagado' ? '' :
      '<p class="dep-datos">' + (a.holgura != null ? 'Holgura: <strong>' + a.holgura + ' día(s) hábil(es)</strong>' : 'Sin relaciones: no entra al cálculo') + (a.critica ? ' · <span class="dep-et crit">Ruta crítica</span>' : '') + (a.conflicto ? ' · <span class="dep-et warn">Conflicto</span>' : '') +
      (a.es && a.es !== a.inicio ? '<br>Fecha calculada: <strong>' + fmt(a.es) + ' – ' + fmt(a.ef) + '</strong> (+' + a.empuje + ' d)' : '') + '</p>';
    p.innerHTML = '<h3>' + esc(a.codigo) + ' · ' + esc(a.nombre) + '</h3><p class="text-muted text-sm">' + esc(a.partida) + '</p>' +
      '<p class="dep-datos mono">' + fmt(a.inicio) + ' – ' + fmt(a.fin) + ' · ' + (a.hito ? 'hito' : a.dur + ' día(s)') + ' · ' + a.progreso + ' %</p>' + calc +
      (D.puedeEditar ? '<label class="campo-check" title="El modo automático no mueve esta actividad; solo se avisa si choca con sus predecesoras"><input type="checkbox" id="dep-fija"' + (a.fija ? ' checked' : '') + '> 📌 Fecha fija</label>' : '') +
      '<h4>Predecesoras <small class="text-muted">(deben cumplirse antes)</small></h4>' + (preds.map(r => filaRel(r, 'pred')).join('') || '<p class="text-muted text-sm">Ninguna.</p>') +
      (D.puedeEditar ? '<div class="dep-agregar"><select id="dep-add-pred">' + opciones(preds.map(r => r.pred)) + '</select><button type="button" class="btn btn-sm btn-primary" id="dep-add-pred-b">Agregar</button></div>' : '') +
      '<h4>Sucesoras <small class="text-muted">(dependen de esta)</small></h4>' + (sucs.map(r => filaRel(r, 'suc')).join('') || '<p class="text-muted text-sm">Ninguna.</p>') +
      (D.puedeEditar ? '<div class="dep-agregar"><select id="dep-add-suc">' + opciones(sucs.map(r => r.suc)) + '</select><button type="button" class="btn btn-sm btn-primary" id="dep-add-suc-b">Agregar</button></div>' : '');
  }

  // ---------- eventos ----------
  function enlazar() {
    document.querySelectorAll('.dep-modo').forEach(b => b.addEventListener('click', async () => {
      if (b.dataset.modo === datos.modo) return;
      if (b.dataset.modo === 'automatico' && !(await window.confirmarModal({ titulo: '¿Activar el modo automático?', mensaje: 'Podrá reprogramar actividades para respetar las relaciones. Siempre verás una vista previa antes de aplicar y podrás deshacer la última reprogramación.', aceptar: 'Activar', iconoBoton: 'fa-wand-magic-sparkles', tipo: 'aviso' }))) return;
      const d = await api('modo', { modo: b.dataset.modo }); if (d) { pintar(); aviso('Modo «' + b.querySelector('strong').textContent + '» activado.', 'ok'); }
    }));
    $('dep-partida').addEventListener('change', e => { S.partida = e.target.value; pintar(); });
    $('dep-solo').addEventListener('change', e => { S.solo = e.target.checked; pintar(); });
    $('dep-buscar').addEventListener('input', e => { S.q = e.target.value; pintar(); });
    $('dep-mas').addEventListener('click', () => zoom(S.ppd * 1.25));
    $('dep-menos').addEventListener('click', () => zoom(S.ppd / 1.25));
    $('dep-ajustar').addEventListener('click', ajustar);
    $('dep-scroll').addEventListener('wheel', e => {
      if (!e.ctrlKey && !e.metaKey) return;
      e.preventDefault(); const r = $('dep-scroll').getBoundingClientRect();
      zoom(S.ppd * (e.deltaY < 0 ? 1.15 : 1 / 1.15), e.clientX - r.left);
    }, { passive: false });
    // paneo: mantener presionada la rueda (botón central) y arrastrar
    const sc = $('dep-scroll'); let pan = null;
    sc.addEventListener('mousedown', e => {
      if (e.button !== 1) return;
      e.preventDefault(); pan = { x: e.clientX, y: e.clientY, l: sc.scrollLeft, t: sc.scrollTop }; sc.classList.add('paneando');
    });
    window.addEventListener('mousemove', e => { if (pan) { sc.scrollLeft = pan.l - (e.clientX - pan.x); sc.scrollTop = pan.t - (e.clientY - pan.y); } });
    window.addEventListener('mouseup', e => { if (pan && e.button === 1) { pan = null; sc.classList.remove('paneando'); } });
    sc.addEventListener('auxclick', e => { if (e.button === 1) e.preventDefault(); });

    // Dos dedos: zoom del diagrama (el desplazamiento de un dedo ya lo hace el navegador)
    let pinza = null, pinzaRaf = null;
    sc.addEventListener('touchstart', e => {
      if (e.touches.length !== 2) return;
      const [a, b] = e.touches;
      pinza = { d: Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY), ppd: S.ppd, x: (a.clientX + b.clientX) / 2 };
    }, { passive: true });
    sc.addEventListener('touchmove', e => {
      if (!pinza || !pinza.d || e.touches.length !== 2) return;
      e.preventDefault();
      const [a, b] = e.touches, r = sc.getBoundingClientRect();
      const nuevo = pinza.ppd * (Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY) / pinza.d), ancla = pinza.x - r.left;
      if (pinzaRaf) return;   // un repintado por cuadro: el diagrama se rehace entero
      pinzaRaf = requestAnimationFrame(() => { pinzaRaf = null; zoom(nuevo, ancla); });
    }, { passive: false });
    sc.addEventListener('touchend', () => { pinza = null; }, { passive: true });
    sc.addEventListener('touchcancel', () => { pinza = null; }, { passive: true });

    // Girar el teléfono: se recalcula la columna de nombres y, si no se tocó el zoom, la vista
    let tResize = null;
    window.addEventListener('resize', () => {
      clearTimeout(tResize);
      tResize = setTimeout(() => { if (autoVista) vistaInicial(); else pintar(); }, 150);
    });

    if ($('dep-enlazar')) $('dep-enlazar').addEventListener('click', () => alternarEnlazar());
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && (S.enlazar || S.origen != null)) alternarEnlazar(false); });

    // clic en etiquetas y barras; arrastre desde el asa
    $('dep-labels').addEventListener('click', e => { const f = e.target.closest('.dep-lab'); if (f) elegir(+f.dataset.id); });
    const svg = $('dep-svg');
    svg.addEventListener('click', e => {
      if (arrastrando) return;
      const fl = e.target.closest('[data-dep]'); if (fl) { const r = datos.relaciones.find(x => x.id === +fl.dataset.dep); if (r) { S.sel = r.suc; S.selDep = r.id; pintar(); } return; }
      const g = e.target.closest('.dep-barra-g'); if (g) elegir(+g.dataset.id);
    });
    let arrastrando = null;
    svg.addEventListener('mousedown', e => {
      const asa = e.target.closest('.dep-asa'); if (!asa || !D.puedeEditar) return;
      e.preventDefault(); arrastrando = { id: +asa.dataset.id, x: +asa.getAttribute('cx'), y: +asa.getAttribute('cy') };
      const ln = $('dep-arrastre'); ln.hidden = false; ln.setAttribute('x1', arrastrando.x); ln.setAttribute('y1', arrastrando.y); ln.setAttribute('x2', arrastrando.x); ln.setAttribute('y2', arrastrando.y);
    });
    window.addEventListener('mousemove', e => {
      if (!arrastrando) return; const r = svg.getBoundingClientRect(), ln = $('dep-arrastre');
      ln.setAttribute('x2', e.clientX - r.left); ln.setAttribute('y2', e.clientY - r.top);
    });
    window.addEventListener('mouseup', e => {
      if (!arrastrando) return;
      const o = arrastrando.id; const ln = $('dep-arrastre'); if (ln) ln.hidden = true;
      const el = document.elementFromPoint(e.clientX, e.clientY), g = el && el.closest && el.closest('.dep-barra-g, .dep-lab');
      arrastrando = null; setTimeout(() => { /* evita el clic posterior */ }, 0);
      const dest = g ? +g.dataset.id : null;
      if (dest && dest !== o) crear(o, dest);
    });

    // panel
    const p = $('dep-panel');
    p.addEventListener('click', async e => {
      const ir = e.target.closest('.dep-ir'); if (ir) { e.preventDefault(); S.sel = +ir.dataset.id; S.selDep = null; pintar(); return; }
      const del = e.target.closest('.dep-del'); if (del) { const id = +del.closest('.dep-rel').dataset.dep; if (await api('eliminar', { id })) { S.selDep = null; pintar(); pintarPartidas(); aviso('Relación eliminada.', 'ok'); } return; }
      if (e.target.id === 'dep-add-pred-b') { const v = +$('dep-add-pred').value; if (v) crear(v, S.sel); }
      if (e.target.id === 'dep-add-suc-b') { const v = +$('dep-add-suc').value; if (v) crear(S.sel, v); }
      const rel = e.target.closest('.dep-rel'); if (rel && !e.target.closest('select,input,button,a')) { S.selDep = +rel.dataset.dep; pintar(); }
    });
    p.addEventListener('change', async e => {
      if (e.target.id === 'dep-fija') { if (await api('fijar', { actividad_id: S.sel, fija: e.target.checked ? 1 : 0 })) pintar(); return; }
      const rel = e.target.closest('.dep-rel'); if (!rel) return;
      const r = datos.relaciones.find(x => x.id === +rel.dataset.dep); if (!r) return;
      const tipo = rel.querySelector('[data-campo=tipo]').value, retraso = parseInt(rel.querySelector('[data-campo=retraso]').value, 10) || 0;
      S.selDep = r.id; if (await api('guardar', { pred: r.pred, suc: r.suc, tipo, retraso })) pintar();
    });

    // reprogramación
    if ($('dep-reprogramar')) $('dep-reprogramar').addEventListener('click', abrirPrevia);
    if ($('dep-deshacer')) $('dep-deshacer').addEventListener('click', async () => {
      if (!(await window.confirmarModal({ titulo: '¿Deshacer la última reprogramación?', mensaje: 'Las actividades que se movieron vuelven a sus fechas anteriores.', aceptar: 'Deshacer', iconoBoton: 'fa-rotate-left' }))) return;
      const d = await api('deshacer'); if (d) { aviso('Se restauraron ' + d.restauradas + ' actividad(es).', 'ok'); pintar(); }
    });
    const modal = $('dep-modal'); const cerrar = () => { modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true'); };
    $('dep-modal-no').addEventListener('click', cerrar); modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
    $('dep-modal-si').addEventListener('click', async () => {
      $('dep-modal-si').disabled = true; const d = await api('aplicar'); $('dep-modal-si').disabled = false; cerrar();
      if (d) { aviso('Se reprogramaron <strong>' + d.movidas + '</strong> actividad(es). Puedes deshacerlo con el botón «Deshacer la última reprogramación».', 'ok'); pintar(); }
    });
  }
  async function abrirPrevia() {
    const d = await api('previa'); if (!d) return;
    if (!d.cambios.length) { aviso('No hay nada que reprogramar.', 'ok'); return; }
    $('dep-modal-txt').textContent = 'Esto moverá ' + d.cambios.length + ' actividad(es) hacia adelante para respetar sus predecesoras. Las que tienen 📌 fecha fija no se tocan. Puedes deshacerlo después.';
    $('dep-modal-filas').innerHTML = d.cambios.map(c => '<tr><td><b>' + esc(c.codigo) + '</b> ' + esc(c.nombre) + '</td><td class="mono">' + fmt(c.inicio) + ' → <strong>' + fmt(c.nuevoInicio) + '</strong></td><td class="mono">' + fmt(c.fin) + ' → <strong>' + fmt(c.nuevoFin) + '</strong></td><td class="mono num">+' + c.dias + '</td></tr>').join('');
    const m = $('dep-modal'); m.classList.add('abierto'); m.setAttribute('aria-hidden', 'false');
  }

  pintarPartidas(); enlazar(); vistaInicial();
  window.__dep = { S, get datos() { return datos; }, pintar, crear, abrirPrevia };
})();
