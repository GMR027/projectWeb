/* Dependencias de materiales: tablero de cadenas, panel de requisitos, reprogramación y sugerencias. */
(function () {
  'use strict';
  const D = window.DEPMAT;
  let datos = D.datos;
  const S = {
    sel: null, vista: 'cadenas', q: '', esp: '', estado: '', solo: false,
    conectar: false, origen: null,
    modalSuc: null, modalSel: new Map(),
    sug: null, sugTab: 'rel', relSel: new Set(), actSel: new Set(),
    previa: null,
  };
  const $ = id => document.getElementById(id);
  const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmt = s => s ? s.slice(8, 10) + '/' + s.slice(5, 7) + '/' + s.slice(2, 4) : '—';
  const mat = id => datos.materiales.find(m => m.id === Number(id));
  const relDe = id => datos.relaciones.filter(r => r.suc === Number(id));
  const relA = id => datos.relaciones.filter(r => r.pred === Number(id));
  const esEditor = !!D.puedeEditar;

  // ---------- comunicación ----------
  async function api(ruta, cuerpo = {}) {
    const fd = new FormData(); fd.append('proyecto', D.proyecto);
    Object.entries(cuerpo).forEach(([k, v]) => fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v));
    try {
      const r = await fetch('/api/materiales/dependencias/' + ruta, { method: 'POST', body: fd, credentials: 'same-origin' });
      const d = await r.json();
      if (!d.success) { toast(d.error || 'No se pudo completar', 'error'); if (d.datos) { datos = d.datos; pintar(); } return null; }
      if (d.datos) { datos = d.datos; }
      return d;
    } catch (e) { toast('Error de conexión', 'error'); return null; }
  }
  async function apiGet(ruta) {
    try {
      const r = await fetch('/api/materiales/dependencias/' + ruta + '?proyecto=' + D.proyecto, { credentials: 'same-origin' });
      const d = await r.json();
      return d.success ? d : null;
    } catch (e) { return null; }
  }
  let tToast = null;
  function toast(txt, tipo = 'ok') {
    let el = $('md-toast');
    if (!el) { el = document.createElement('div'); el.id = 'md-toast'; el.className = 'md-toast'; el.setAttribute('role', 'status'); document.body.append(el); }
    el.className = 'md-toast md-toast-' + tipo;
    el.innerHTML = '<i class="fa-solid ' + (tipo === 'error' ? 'fa-circle-exclamation' : tipo === 'warn' ? 'fa-triangle-exclamation' : 'fa-circle-check') + '"></i> ' + esc(txt);
    el.classList.add('visible');
    clearTimeout(tToast); tToast = setTimeout(() => el.classList.remove('visible'), 4500);
  }

  // ---------- estado visual ----------
  function estado(m) {
    if (m.entrega_real) return { k: 'listo', t: 'En sitio' };
    if (!m.requiere && !m.requerido_por && !m.actividades.length) return { k: 'sinreq', t: 'Sin requisitos' };
    if (m.bloqueado) return { k: 'bloqueado', t: 'Bloqueado' };
    if (m.disponible && m.necesidad && m.disponible > m.necesidad) return { k: 'justo', t: 'Llega tarde' };
    if (m.disponible) return { k: 'listo', t: 'Disponible' };
    return { k: 'bloqueado', t: 'Sin fecha' };
  }
  function tieneFiltro() { return S.q.trim() !== '' || S.esp !== '' || S.estado !== '' || S.solo; }
  function filtrados() {
    const q = S.q.trim().toLowerCase();
    return datos.materiales.filter(m => {
      if (S.solo && !m.requiere && !m.requerido_por) return false;
      if (S.esp && m.especialidad !== S.esp) return false;
      if (q && !((m.nombre || '') + ' ' + (m.referencia || '') + ' ' + (m.especialidad || '')).toLowerCase().includes(q)) return false;
      if (S.estado) {
        const e = estado(m).k;
        if (S.estado === 'listo' && e !== 'listo') return false;
        if (S.estado === 'bloqueado' && e !== 'bloqueado') return false;
        if (S.estado === 'sinrequisitos' && e !== 'sinreq') return false;
      }
      return true;
    });
  }
  const foto = m => m.imagen
    ? '<img class="md-thumb" src="/uploads/materiales/' + esc(m.imagen) + '" alt="" loading="lazy">'
    : '<span class="md-thumb md-thumb-vacio"><i class="fa-regular fa-image"></i></span>';
  const pillCompra = m => '<span class="md-pill ' + (m.compra === 'comprado' ? 'ok' : 'warn') + '">' + (m.compra === 'comprado' ? 'Comprado' : 'Sin comprar') + '</span>';
  const pillEntrega = m => m.entrega_real
    ? '<span class="md-pill ok"><i class="fa-solid fa-truck-ramp-box"></i> ' + fmt(m.entrega_real) + '</span>'
    : (m.entrega_prog ? '<span class="md-pill">' + fmt(m.entrega_prog) + '</span>' : '<span class="md-pill danger">Sin entrega</span>');

  // ---------- pintura general ----------
  function pintar() {
    pintarModos();
    pintarKpis();
    pintarFiltros();
    if (S.vista === 'lista') pintarLista(); else pintarTablero();
    pintarPanel();
    pintarBotones();
    pintarAviso();
  }

  function pintarModos() {
    document.querySelectorAll('.md-modo').forEach(b => { const on = b.dataset.modo === datos.modo; b.classList.toggle('activo', on); b.setAttribute('aria-checked', on); });
  }

  function pintarKpis() {
    const r = datos.resumen;
    const k = (ic, t, v, c = '') => '<div class="md-kpi ' + c + '"><i class="fa-solid ' + ic + '"></i><div><span>' + esc(t) + '</span><strong>' + esc(v) + '</strong></div></div>';
    $('md-kpis').innerHTML =
      k('fa-diagram-project', 'Relaciones', r.relaciones) +
      k('fa-link-slash', 'Materiales bloqueados', r.materiales_bloqueados, r.materiales_bloqueados ? 'bad' : '') +
      k('fa-triangle-exclamation', 'Actividades bloqueadas', r.actividades_bloqueadas, r.actividades_bloqueadas ? 'bad' : '') +
      k('fa-clock', 'Actividades atrasadas', r.actividades_atrasadas, r.actividades_atrasadas ? 'warn' : '') +
      k('fa-calendar-plus', 'Días de atraso', r.dias, r.dias ? 'warn' : '');
  }

  function pintarFiltros() {
    const esp = [...new Set(datos.materiales.map(m => m.especialidad).filter(Boolean))].sort((a, b) => a.localeCompare(b));
    const sel = $('md-esp');
    sel.innerHTML = '<option value="">Todas las especialidades</option>' + esp.map(e => '<option value="' + esc(e) + '"' + (S.esp === e ? ' selected' : '') + '>' + esc(e) + '</option>').join('');
  }

  function pintarBotones() {
    const rep = $('md-reprogramar'), des = $('md-deshacer'), con = $('md-conectar');
    if (rep) {
      const problemas = datos.resumen.actividades_atrasadas + datos.resumen.actividades_bloqueadas;
      rep.hidden = !(esEditor && problemas > 0);
    }
    if (des) des.hidden = !(esEditor && datos.ultimaAplicacion);
    if (con) {
      con.classList.toggle('activo', S.conectar);
      con.innerHTML = S.conectar ? '<i class="fa-solid fa-xmark"></i> Cancelar conexión' : '<i class="fa-solid fa-link"></i> Conectar materiales';
    }
  }

  function pintarAviso() {
    const el = $('md-aviso'), r = datos.resumen;
    let txt = '', tipo = 'info', fijo = false;
    if (datos.ciclo && datos.ciclo.length) {
      txt = 'Hay un ciclo entre materiales: ' + datos.ciclo.map(id => esc((mat(id) || {}).nombre || id)).join(', ') + '. Quita una relación para poder calcular.';
      tipo = 'error'; fijo = true;
    } else if (datos.modo === 'apagado' && r.relaciones > 0) {
      txt = 'Modo apagado: las relaciones se guardan y se ven, pero no se reprograma. Cambia a «Automático» para que las actividades sigan la disponibilidad de sus materiales.';
    } else if (datos.modo === 'automatico' && r.actividades_atrasadas > 0) {
      txt = '<strong>' + r.actividades_atrasadas + '</strong> actividad(es) deben moverse para respetar la disponibilidad de sus materiales. Usa «Revisar y reprogramar».';
      tipo = 'warn'; fijo = true;
    } else if (datos.modo === 'informativo' && (r.actividades_atrasadas > 0 || r.actividades_bloqueadas > 0)) {
      txt = 'En modo informativo no se mueven fechas: revisa las actividades marcadas y corrígelas desde el Programa.';
      tipo = 'warn'; fijo = true;
    } else if (r.relaciones === 0 && datos.materiales.length > 0) {
      txt = (r.actividades_atrasadas || r.actividades_bloqueadas)
        ? 'Aún no hay relaciones entre materiales. Las actividades marcadas esperan la entrega de sus propios materiales; conecta materiales para sumar los prerrequisitos.'
        : 'Aún no hay relaciones. Conecta el material que debe estar primero con el que lo necesita.';
    }
    el.hidden = !txt; el.className = 'md-aviso md-aviso-' + tipo; el.innerHTML = txt || '';
    el.dataset.fijo = fijo ? '1' : '0';
  }

  // ---------- tablero de cadenas ----------
  function niveles(lista) {
    const ids = new Set(lista.map(m => m.id));
    const rels = datos.relaciones.filter(r => ids.has(r.pred) && ids.has(r.suc));
    const n = new Map(lista.map(m => [m.id, 0]));
    for (let k = 0; k < lista.length; k++) {
      let cambio = false;
      rels.forEach(r => {
        const v = (n.get(r.pred) || 0) + 1;
        if (v > (n.get(r.suc) || 0) && v <= lista.length) { n.set(r.suc, v); cambio = true; }
      });
      if (!cambio) break;
    }
    return n;
  }

  function pintarTablero() {
    const $tab = $('md-tablero'), $cols = $('md-cols'), $vacio = $('md-vacio');
    $tab.hidden = false; $('md-lista').hidden = true;
    const lista = filtrados();
    $cols.innerHTML = '';
    if (!lista.length) {
      const h = $vacio.querySelector('h3'), p = $vacio.querySelector('p'), b = $vacio.querySelector('#md-vacio-conectar');
      if (!datos.materiales.length) {
        h.textContent = 'Aún no hay materiales';
        p.textContent = 'Agrega materiales al catálogo para conectarlos entre sí.';
        if (b) b.hidden = true;
      } else if (tieneFiltro()) {
        h.textContent = 'Sin resultados';
        p.textContent = 'No hay materiales que coincidan con los filtros.';
        if (b) b.hidden = true;
      } else {
        h.textContent = 'Conecta tus materiales';
        p.innerHTML = 'Empieza con el material que debe estar primero (por ejemplo, <strong>soportería</strong> o <strong>muro</strong>) y conéctalo con el que lo necesita (<strong>cable</strong> o <strong>pintura</strong>).';
        if (b) b.hidden = false;
      }
      $vacio.hidden = false;
      $('md-svg').innerHTML = '';
      return;
    }
    $vacio.hidden = true;

    const niv = niveles(lista);
    const grupos = new Map();
    lista.forEach(m => {
      const n = niv.get(m.id) || 0;
      if (!grupos.has(n)) grupos.set(n, []);
      grupos.get(n).push(m);
    });
    const orden = [...grupos.keys()].sort((a, b) => a - b);
    orden.forEach(n => {
      const col = document.createElement('div');
      col.className = 'md-col';
      const titulo = n === 0 ? (datos.relaciones.length ? 'Primero en la cadena' : 'Materiales') : n === 1 ? 'Después' : n === 2 ? 'Al final' : 'Nivel ' + (n + 1);
      col.innerHTML = '<div class="md-col-cab"><span>' + esc(titulo) + '</span><b>' + grupos.get(n).length + '</b></div><div class="md-col-cuerpo"></div>';
      const cuerpo = col.querySelector('.md-col-cuerpo');
      grupos.get(n).sort((a, b) => a.nombre.localeCompare(b.nombre)).forEach(m => cuerpo.append(tarjeta(m, niv)));
      $cols.append(col);
    });
    requestAnimationFrame(lineas);
  }

  function tarjeta(m, niv) {
    const e = estado(m);
    const el = document.createElement('div');
    el.className = 'md-card' + (m.id === S.sel ? ' sel' : '') + (m.id === S.origen ? ' origen' : '') + ' est-' + e.k;
    el.dataset.id = m.id;
    el.tabIndex = 0;
    el.setAttribute('role', 'button');
    el.setAttribute('aria-label', m.nombre + ' · ' + e.t);
    el.innerHTML =
      '<div class="md-card-top">' + foto(m) + '<div class="md-card-txt"><strong>' + esc(m.nombre) + '</strong><small>' + esc(m.referencia || m.especialidad || 'Sin especialidad') + '</small></div><i class="md-dot ' + e.k + '" title="' + esc(e.t) + '"></i></div>' +
      '<div class="md-card-pills">' + pillCompra(m) + pillEntrega(m) + '</div>' +
      '<div class="md-card-meta">' +
        (m.requiere ? '<span class="md-mini req"><i class="fa-solid fa-arrow-down-long"></i> ' + m.requiere + '</span>' : '') +
        (m.requerido_por ? '<span class="md-mini dep"><i class="fa-solid fa-arrow-up-long"></i> ' + m.requerido_por + '</span>' : '') +
        '<span class="md-mini">' + (m.actividades.length) + ' act.</span>' +
        (m.disponible ? '<span class="md-mini fecha"><i class="fa-regular fa-calendar"></i> ' + fmt(m.disponible) + '</span>' : '') +
      '</div>' +
      (m.bloqueado && m.motivos.length ? '<div class="md-card-alerta" title="' + esc(m.motivos.join(' · ')) + '"><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(m.motivos[0]) + '</div>' : '');
    el.addEventListener('click', ev => { ev.stopPropagation(); clicMaterial(m.id); });
    el.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); clicMaterial(m.id); } });
    el.addEventListener('mouseenter', () => resaltar(m.id, true));
    el.addEventListener('mouseleave', () => resaltar(m.id, false));
    return el;
  }

  function clicMaterial(id) {
    if (S.conectar) {
      if (!S.origen) { S.origen = id; pintarTablero(); toast('Ahora elige el material que lo necesita'); return; }
      if (S.origen === id) { S.origen = null; pintarTablero(); return; }
      abrirConectar(id, S.origen);
      S.conectar = false; S.origen = null;
      return;
    }
    S.sel = id;
    pintar();
    const card = document.querySelector('.md-card[data-id="' + id + '"]');
    if (card) card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  // ---------- líneas del tablero ----------
  let tLineas = null;
  function lineas() {
    clearTimeout(tLineas);
    tLineas = setTimeout(() => {
      const svg = $('md-svg'), cols = $('md-cols'), scroll = $('md-scroll');
      if (!svg || !cols || !scroll) return;
      const base = scroll.getBoundingClientRect();
      const ancho = Math.max(cols.scrollWidth, scroll.scrollWidth, 10), alto = Math.max(cols.scrollHeight, scroll.scrollHeight, 10);
      svg.setAttribute('width', ancho); svg.setAttribute('height', alto);
      svg.setAttribute('viewBox', '0 0 ' + ancho + ' ' + alto);
      const pos = new Map();
      document.querySelectorAll('.md-card').forEach(c => {
        const r = c.getBoundingClientRect();
        pos.set(Number(c.dataset.id), {
          x1: r.right - base.left + scroll.scrollLeft, y1: r.top - base.top + scroll.scrollTop + r.height / 2,
          x2: r.left - base.left + scroll.scrollLeft, y2: r.top - base.top + scroll.scrollTop + r.height / 2,
          cx: r.left - base.left + scroll.scrollLeft + r.width / 2, cy: r.top - base.top + scroll.scrollTop + r.height / 2,
        });
      });
      let s = '<defs><marker id="md-flecha" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 z" fill="currentColor"/></marker></defs>';
      datos.relaciones.forEach(r => {
        const a = pos.get(r.pred), b = pos.get(r.suc);
        if (!a || !b) return;
        const x1 = a.x1, y1 = a.y1, x2 = b.x2, y2 = b.y2;
        const dx = Math.max(26, Math.abs(x2 - x1) * 0.45);
        const d = 'M' + x1 + ',' + y1 + ' C' + (x1 + dx) + ',' + y1 + ' ' + (x2 - dx) + ',' + y2 + ' ' + x2 + ',' + y2;
        const m = mat(r.suc);
        const e = m ? estado(m) : { k: '' };
        s += '<path class="md-linea ' + e.k + '" data-pred="' + r.pred + '" data-suc="' + r.suc + '" d="' + d + '" marker-end="url(#md-flecha)"/>';
        s += '<circle class="md-linea-punto" cx="' + ((x1 + x2) / 2) + '" cy="' + ((y1 + y2) / 2) + '" r="2.5"/>';
      });
      svg.innerHTML = s;
    }, 40);
  }

  function resaltar(id, on) {
    document.querySelectorAll('.md-linea').forEach(l => {
      const conecta = Number(l.dataset.pred) === id || Number(l.dataset.suc) === id;
      l.classList.toggle('hl', on && conecta);
      l.classList.toggle('dim', on && !conecta);
    });
    document.querySelectorAll('.md-card').forEach(c => {
      const cid = Number(c.dataset.id);
      const conecta = cid === id || datos.relaciones.some(r => (r.pred === id && r.suc === cid) || (r.suc === id && r.pred === cid));
      c.classList.toggle('hl', on && conecta);
      c.classList.toggle('dim', on && !conecta);
    });
  }

  // ---------- lista ----------
  function pintarLista() {
    $('md-tablero').hidden = true; $('md-lista').hidden = false;
    const lista = filtrados();
    const cuerpo = $('md-lista-cuerpo');
    if (!lista.length) { cuerpo.innerHTML = '<tr><td colspan="9" class="text-muted">No hay materiales que coincidan con los filtros.</td></tr>'; return; }
    cuerpo.innerHTML = lista.map(m => {
      const e = estado(m);
      return '<tr data-id="' + m.id + '" class="' + (m.id === S.sel ? 'sel' : '') + '">' +
        '<td><div class="md-lista-mat">' + foto(m) + '<span><strong>' + esc(m.nombre) + '</strong><small>' + esc(m.referencia || '') + '</small></span></div></td>' +
        '<td class="hide-m">' + esc(m.especialidad) + '</td>' +
        '<td>' + pillCompra(m) + '</td>' +
        '<td class="hide-m">' + pillEntrega(m) + '</td>' +
        '<td class="num">' + (m.requiere || '—') + '</td>' +
        '<td class="num hide-m">' + (m.requerido_por || '—') + '</td>' +
        '<td>' + (m.disponible ? fmt(m.disponible) : '<span class="text-muted">—</span>') + '</td>' +
        '<td class="hide-m">' + (m.necesidad ? fmt(m.necesidad) : '—') + '</td>' +
        '<td><span class="md-estado ' + e.k + '"><i class="md-dot ' + e.k + '"></i> ' + esc(e.t) + '</span></td>' +
      '</tr>';
    }).join('');
    cuerpo.querySelectorAll('tr[data-id]').forEach(tr => tr.addEventListener('click', () => { S.sel = Number(tr.dataset.id); pintar(); }));
  }

  // ---------- panel ----------
  function pintarPanel() {
    const p = $('md-panel');
    if (!S.sel || !mat(S.sel)) {
      p.innerHTML = '<p class="text-muted">Elige un material para ver qué requiere, quién lo requiere y qué actividades lo usan.</p>';
      return;
    }
    const m = mat(S.sel), e = estado(m), reqs = relDe(m.id), deps = relA(m.id);
    const bloqueo = m.bloqueado
      ? '<div class="md-panel-alerta"><i class="fa-solid fa-triangle-exclamation"></i><div><strong>No se puede programar todavía</strong><span>' + esc(m.motivos.join(' · ') || 'Sin fecha de entrega') + '</span></div></div>'
      : '';
    const filaRel = (r, tipo) => {
      const otro = mat(tipo === 'req' ? r.pred : r.suc);
      if (!otro) return '';
      return '<button type="button" class="md-rel" data-ir="' + otro.id + '">' + foto(otro) +
        '<span class="md-rel-txt"><strong>' + esc(otro.nombre) + '</strong><small>' + (tipo === 'req' ? esc(D.requisitos[r.requisito] || r.requisito) : 'Requiere este material') + (r.retraso ? ' · +' + r.retraso + ' d' : '') + (tipo === 'req' && r.nota ? ' · ' + esc(r.nota) : '') + '</small></span>' +
        (tipo === 'req' && esEditor ? '<span class="md-rel-x" role="button" tabindex="0" data-quitar="' + r.id + '" title="Quitar requisito" aria-label="Quitar requisito"><i class="fa-solid fa-xmark"></i></span>' : '<i class="fa-solid fa-chevron-right"></i>') +
      '</button>';
    };
    const acts = m.actividades.length
      ? m.actividades.map(a => '<div class="md-act"><span class="md-act-cod">' + esc(a.codigo) + '</span><div><strong>' + esc(a.nombre) + '</strong><small>' + esc(a.partida) + ' · ' + fmt(a.inicio) + ' → ' + fmt(a.fin) + '</small></div><span class="md-pill ' + (a.rol === 'instala' ? 'ok' : '') + '">' + esc(D.roles[a.rol] || a.rol) + '</span></div>').join('')
      : '<p class="text-muted text-sm">Este material no está ligado a ninguna actividad. Usa «Sugerir actividades» para proponer una.</p>';
    const fecha = (t, v, k) => '<div class="md-fecha ' + k + '"><span>' + esc(t) + '</span><strong>' + (v ? fmt(v) : '—') + '</strong></div>';
    p.innerHTML =
      '<div class="md-panel-cab">' + foto(m) + '<div><h3>' + esc(m.nombre) + '</h3><small>' + esc(m.referencia || 'Sin referencia') + ' · ' + esc(m.especialidad || 'Sin especialidad') + '</small></div><button type="button" class="btn btn-ghost btn-sm md-panel-cerrar" id="md-panel-cerrar" aria-label="Cerrar panel"><i class="fa-solid fa-xmark"></i></button></div>' +
      '<div class="md-panel-estado"><span class="md-estado ' + e.k + '"><i class="md-dot ' + e.k + '"></i> ' + esc(e.t) + '</span>' + pillCompra(m) + pillEntrega(m) + '</div>' +
      bloqueo +
      '<div class="md-fechas">' + fecha('Disponible desde', m.disponible, e.k) + fecha('Se necesita el', m.necesidad, '') + fecha('Instalado', m.instalado, '') + '</div>' +
      (esEditor ? '<div class="md-panel-acciones"><button type="button" class="btn btn-sm btn-primary" id="md-panel-agregar"><i class="fa-solid fa-link"></i> Agregar prerrequisito</button><a class="btn btn-sm btn-ghost" href="/materiales/editar?proyecto=' + D.proyecto + '&id=' + m.id + '"><i class="fa-solid fa-pen"></i> Editar material</a></div>' : '') +
      '<div class="md-seccion"><div class="md-seccion-cab"><h4><i class="fa-solid fa-arrow-down-long"></i> Requiere <span>' + reqs.length + '</span></h4></div>' +
        (reqs.length ? '<div class="md-rel-lista">' + reqs.map(r => filaRel(r, 'req')).join('') + '</div>' : '<p class="text-muted text-sm">No requiere otros materiales. Es el primero de su cadena.</p>') + '</div>' +
      '<div class="md-seccion"><div class="md-seccion-cab"><h4><i class="fa-solid fa-arrow-up-long"></i> Requerido por <span>' + deps.length + '</span></h4></div>' +
        (deps.length ? '<div class="md-rel-lista">' + deps.map(r => filaRel(r, 'dep')).join('') + '</div>' : '<p class="text-muted text-sm">Ningún material depende de este todavía.</p>') + '</div>' +
      '<div class="md-seccion"><div class="md-seccion-cab"><h4><i class="fa-solid fa-list-check"></i> Actividades que lo usan <span>' + m.actividades.length + '</span></h4></div><div class="md-acts">' + acts + '</div></div>';

    p.querySelector('#md-panel-cerrar').addEventListener('click', () => { S.sel = null; pintar(); });
    const ag = p.querySelector('#md-panel-agregar');
    if (ag) ag.addEventListener('click', () => abrirConectar(m.id, null));
    p.querySelectorAll('[data-ir]').forEach(b => b.addEventListener('click', ev => {
      if (ev.target.closest('[data-quitar]')) return;
      S.sel = Number(b.dataset.ir); pintar();
    }));
    p.querySelectorAll('[data-quitar]').forEach(x => {
      const quitar = async ev => {
        ev.stopPropagation();
        if (!confirm('¿Quitar este requisito?')) return;
        const d = await api('eliminar', { id: x.dataset.quitar });
        if (d) { toast('Requisito eliminado'); pintar(); }
      };
      x.addEventListener('click', quitar);
      x.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); quitar(ev); } });
    });
  }

  // ---------- conectar ----------
  function abrirConectar(sucId, predInicial) {
    if (!esEditor) return;
    S.modalSuc = sucId;
    S.modalSel = new Map();
    if (predInicial) S.modalSel.set(Number(predInicial), { requisito: 'entrega', retraso: 0 });
    const m = mat(sucId);
    const $suc = $('md-mc-suc');
    $suc.innerHTML = foto(m) + '<div><strong>' + esc(m.nombre) + '</strong><small>Este material requiere que estén disponibles:</small></div>';
    $('md-mc-buscar').value = '';
    pintarModalLista();
    pintarModalSel();
    abrirModal('md-modal-conectar');
    setTimeout(() => $('md-mc-buscar').focus(), 80);
  }

  function pintarModalLista() {
    const q = $('md-mc-buscar').value.trim().toLowerCase();
    const suc = S.modalSuc;
    const existentes = new Set(relDe(suc).map(r => r.pred));
    const lista = datos.materiales.filter(m => m.id !== suc && !S.modalSel.has(m.id) && (!q || ((m.nombre || '') + ' ' + (m.referencia || '') + ' ' + (m.especialidad || '')).toLowerCase().includes(q)));
    $('md-mc-lista').innerHTML = lista.length
      ? lista.map(m => {
          const e = estado(m), ya = existentes.has(m.id);
          return '<button type="button" class="md-mc-item" data-add="' + m.id + '">' + foto(m) +
            '<span class="md-mc-txt"><strong>' + esc(m.nombre) + '</strong><small>' + esc(m.especialidad || m.referencia || '') + '</small></span>' +
            '<span class="md-dot ' + e.k + '" title="' + esc(e.t) + '"></span>' + (ya ? '<span class="md-pill">Ya conectado</span>' : '<i class="fa-solid fa-plus"></i>') + '</button>';
        }).join('')
      : '<p class="text-muted text-sm">No hay materiales que coincidan.</p>';
    $('md-mc-lista').querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => {
      const id = Number(b.dataset.add);
      const rel = relDe(S.modalSuc).find(r => r.pred === id);
      S.modalSel.set(id, { requisito: rel ? rel.requisito : 'entrega', retraso: rel ? rel.retraso : 0 });
      pintarModalLista(); pintarModalSel();
    }));
  }

  function pintarModalSel() {
    const sel = [...S.modalSel.entries()];
    if (!sel.length) {
      $('md-mc-sel').innerHTML = '<p class="text-muted text-sm">Aún no has elegido ningún material. Puedes marcar varios.</p>';
      $('md-mc-guardar').disabled = true;
      return;
    }
    $('md-mc-guardar').disabled = false;
    $('md-mc-sel').innerHTML = '<h4>Seleccionados (' + sel.length + ')</h4>' + sel.map(([id, v]) => {
      const m = mat(id);
      return '<div class="md-mc-fila" data-fila="' + id + '">' + foto(m) + '<strong>' + esc(m ? m.nombre : id) + '</strong>' +
        '<label>Requisito <select data-req="' + id + '">' + Object.entries(D.requisitos).map(([k, t]) => '<option value="' + k + '"' + (v.requisito === k ? ' selected' : '') + '>' + esc(t) + '</option>').join('') + '</select></label>' +
        '<label>Días <input type="number" min="0" max="365" value="' + Number(v.retraso || 0) + '" data-ret="' + id + '"></label>' +
        '<button type="button" class="btn btn-ghost btn-sm" data-quitar-sel="' + id + '" aria-label="Quitar de la selección"><i class="fa-solid fa-xmark"></i></button></div>';
    }).join('');
    $('md-mc-sel').querySelectorAll('[data-req]').forEach(s => s.addEventListener('change', () => { S.modalSel.get(Number(s.dataset.req)).requisito = s.value; }));
    $('md-mc-sel').querySelectorAll('[data-ret]').forEach(i => i.addEventListener('change', () => { S.modalSel.get(Number(i.dataset.ret)).retraso = Math.max(0, Math.min(365, Number(i.value) || 0)); }));
    $('md-mc-sel').querySelectorAll('[data-quitar-sel]').forEach(b => b.addEventListener('click', () => {
      S.modalSel.delete(Number(b.dataset.quitarSel)); pintarModalLista(); pintarModalSel();
    }));
  }

  async function guardarConexion() {
    const items = [...S.modalSel.entries()].map(([pred, v]) => ({ pred, requisito: v.requisito, retraso: Number(v.retraso) || 0 }));
    if (!items.length) return;
    const btn = $('md-mc-guardar'); btn.disabled = true;
    const d = await api('guardar-varios', { suc: S.modalSuc, items });
    btn.disabled = false;
    if (d) {
      cerrarModal('md-modal-conectar');
      toast(d.errores && d.errores.length ? (d.guardadas + ' guardada(s); ' + d.errores.join(' · ')) : d.guardadas + ' relación(es) guardada(s)', d.errores && d.errores.length ? 'warn' : 'ok');
      S.sel = S.modalSuc; pintar();
    }
  }

  // ---------- modos ----------
  async function cambiarModo(modo) {
    if (!esEditor) return;
    const d = await api('modo', { modo });
    if (d) { toast('Modo: ' + modo); pintar(); }
  }

  // ---------- reprogramación ----------
  async function abrirPrevia() {
    const d = await api('previa');
    if (!d) return;
    S.previa = d;
    const c = d.cambios || [], b = d.bloqueadas || [];
    let html = '';
    if (c.length) {
      html += '<h4><i class="fa-solid fa-calendar-check"></i> Se moverían ' + c.length + ' actividad(es)</h4><div class="tabla-wrapper" style="max-height:34vh;overflow:auto"><table class="tabla"><thead><tr><th>Actividad</th><th>Inicio actual</th><th>Nuevo inicio</th><th>Fin nuevo</th><th class="num">Días</th><th class="hide-m">Motivo</th></tr></thead><tbody>' +
        c.map(x => '<tr><td><strong>' + esc(x.codigo) + '</strong> ' + esc(x.nombre) + '</td><td>' + fmt(x.inicio) + '</td><td><strong>' + fmt(x.nuevoInicio) + '</strong></td><td>' + fmt(x.nuevoFin) + '</td><td class="num">+' + x.dias + '</td><td class="hide-m text-sm">' + esc((x.motivos || []).join(' · ')) + '</td></tr>').join('') + '</tbody></table></div>';
    } else {
      html += '<p class="text-muted"><i class="fa-solid fa-circle-check"></i> Ninguna actividad necesita moverse por materiales.</p>';
    }
    if (b.length) {
      html += '<h4 class="md-mp-sub"><i class="fa-solid fa-triangle-exclamation"></i> ' + b.length + ' actividad(es) bloqueada(s) sin fecha</h4><div class="md-mp-bloq">' +
        b.map(x => '<div><strong>' + esc(x.codigo) + ' ' + esc(x.nombre) + '</strong><small>' + esc((x.motivos || []).join(' · ')) + '</small></div>').join('') + '</div>';
    }
    if (datos.modo !== 'automatico' && c.length) html += '<p class="md-nota"><i class="fa-solid fa-circle-info"></i> El modo actual es <strong>' + esc(datos.modo) + '</strong>. Para aplicar los cambios, cambia a «Automático».</p>';
    $('md-mp-cuerpo').innerHTML = html;
    $('md-mp-txt').textContent = c.length ? 'Revisa el antes y el después. Nada cambia hasta que apliques.' : 'Vista previa del efecto de las dependencias de materiales.';
    const ap = $('md-mp-aplicar');
    ap.disabled = !(datos.modo === 'automatico' && c.length > 0);
    abrirModal('md-modal-previa');
  }
  async function aplicarPrevia() {
    const d = await api('aplicar');
    if (!d) return;
    cerrarModal('md-modal-previa');
    toast(d.movidas + ' actividad(es) reprogramada(s)');
    pintar();
  }
  async function deshacer() {
    if (!confirm('¿Restaurar las fechas anteriores a la última reprogramación?')) return;
    const d = await api('deshacer');
    if (d) { toast(d.restauradas + ' actividad(es) restaurada(s)'); pintar(); }
  }

  // ---------- sugerencias ----------
  async function abrirSugerencias() {
    $('md-ms-cuerpo').innerHTML = '<p class="text-muted">Calculando sugerencias…</p>';
    abrirModal('md-modal-sugerencias');
    const d = await apiGet('sugerencias');
    if (!d) { $('md-ms-cuerpo').innerHTML = '<p class="text-muted">No se pudieron calcular las sugerencias.</p>'; return; }
    S.sug = d.sugerencias;
    S.relSel = new Set(S.sug.relaciones.map((_, i) => i));   // las relaciones son seguras; las actividades se marcan a mano
    S.actSel = new Set();
    $('md-ms-nrel').textContent = S.sug.relaciones.length;
    $('md-ms-nact').textContent = S.sug.actividades.length;
    pintarSugerencias();
  }
  function pintarSugerencias() {
    const sb = S.sug;
    if (!sb) return;
    const tab = S.sugTab;
    const todos = tab === 'rel' ? (sb.relaciones.length > 0 && S.relSel.size === sb.relaciones.length) : (sb.actividades.length > 0 && S.actSel.size === sb.actividades.length);
    const selector = '<label class="md-sug-todos"><input type="checkbox" id="md-ms-todos"' + (todos ? ' checked' : '') + '> Seleccionar todas</label>';
    $('md-ms-cuerpo').innerHTML = tab === 'rel' ? (sb.relaciones.length
      ? selector + '<p class="text-muted text-sm">Relaciones entre actividades que ya existen. Se crearían como <strong>Fin → inicio</strong> en Dependencias de obra.</p><div class="md-sug-lista">' +
        sb.relaciones.map((r, i) => '<label class="md-sug"><input type="checkbox" data-rel="' + i + '"' + (S.relSel.has(i) ? ' checked' : '') + '><span class="md-sug-txt"><strong>' + esc(r.pred_codigo + ' · ' + r.pred_nombre) + '</strong><i class="fa-solid fa-arrow-right"></i><strong>' + esc(r.suc_codigo + ' · ' + r.suc_nombre) + '</strong><small>' + esc(r.motivo) + '</small></span></label>').join('') + '</div>'
      : '<p class="text-muted"><i class="fa-solid fa-circle-check"></i> ' + (datos.relaciones.length ? 'No hay relaciones nuevas que sugerir entre actividades existentes.' : 'Primero conecta materiales en el tablero; con las cadenas se pueden sugerir relaciones.') + '</p>')
      : (sb.actividades.length
        ? selector + '<p class="text-muted text-sm">Actividades para materiales de las cadenas que todavía no tienen ninguna. Marca las que quieras crear y edita el nombre, la partida, la duración o la fecha.</p><div class="md-sug-lista">' +
          sb.actividades.map((a, i) => '<div class="md-sug md-sug-act"><input type="checkbox" data-act="' + i + '"' + (S.actSel.has(i) ? ' checked' : '') + '>' +
            '<div class="md-sug-txt"><div class="md-sug-campos"><label>Nombre <input type="text" data-campo="nombre" data-i="' + i + '" value="' + esc(a.nombre) + '"></label>' +
            '<label>Partida <select data-campo="partida_id" data-i="' + i + '">' + sb.partidas.map(p => '<option value="' + p.id + '"' + (Number(a.partida_id) === Number(p.id) ? ' selected' : '') + '>' + esc(p.codigo + ' · ' + p.nombre) + '</option>').join('') + '</select></label>' +
            '<label>Duración <input type="number" min="1" max="365" data-campo="duracion_dias" data-i="' + i + '" value="' + Number(a.duracion_dias || 1) + '"></label>' +
            '<label>Inicio <input type="date" data-campo="fecha_inicio" data-i="' + i + '" value="' + esc(a.fecha_inicio || '') + '"></label></div>' +
            '<small>' + esc(a.material + ' · ' + (a.motivo || '')) +
              (a.pred_ids && a.pred_ids.length ? ' · después de ' + a.pred_ids.map(id => esc((datos.actividades.find(x => x.id === id) || {}).codigo || ('#' + id))).join(', ') : (a.pred_materiales && a.pred_materiales.length ? ' · después de ' + a.pred_materiales.length + ' material(es) prerrequisito' : '')) +
              (a.sucesoras_ids && a.sucesoras_ids.length ? ' · antes de ' + a.sucesoras_ids.map(id => esc((datos.actividades.find(x => x.id === id) || {}).codigo || ('#' + id))).join(', ') : '') +
            '</small></div></div>').join('') + '</div>'
        : '<p class="text-muted"><i class="fa-solid fa-circle-check"></i> ' + (datos.relaciones.length ? 'Todos los materiales que participan en las cadenas ya tienen actividad.' : 'Primero conecta materiales en el tablero; con las cadenas se pueden sugerir actividades.') + '</p>');
    const todosChk = $('md-ms-todos');
    if (todosChk) todosChk.addEventListener('change', () => {
      const arr = tab === 'rel' ? sb.relaciones : sb.actividades;
      const set = tab === 'rel' ? S.relSel : S.actSel;
      set.clear();
      if (todosChk.checked) arr.forEach((_, i) => set.add(i));
      pintarSugerencias();
    });
    const sincronizarTodos = () => {
      if (!todosChk) return;
      const arr = tab === 'rel' ? sb.relaciones : sb.actividades;
      const set = tab === 'rel' ? S.relSel : S.actSel;
      todosChk.checked = arr.length > 0 && set.size === arr.length;
    };
    $('md-ms-cuerpo').querySelectorAll('[data-rel]').forEach(cb => cb.addEventListener('change', () => {
      const i = Number(cb.dataset.rel); cb.checked ? S.relSel.add(i) : S.relSel.delete(i); sincronizarTodos();
    }));
    $('md-ms-cuerpo').querySelectorAll('[data-act]').forEach(cb => cb.addEventListener('change', () => {
      const i = Number(cb.dataset.act); cb.checked ? S.actSel.add(i) : S.actSel.delete(i); sincronizarTodos();
    }));
    $('md-ms-cuerpo').querySelectorAll('[data-campo]').forEach(inp => {
      const ev = inp.tagName === 'SELECT' ? 'change' : 'input';
      inp.addEventListener(ev, () => {
        const a = S.sug.actividades[Number(inp.dataset.i)];
        if (!a) return;
        a[inp.dataset.campo] = inp.dataset.campo === 'duracion_dias' ? Math.max(1, Number(inp.value) || 1) : inp.value;
      });
    });
  }
  async function aplicarSugerencias() {
    const rel = [...S.relSel].map(i => S.sug.relaciones[i]).map(r => ({ pred: r.pred, suc: r.suc }));
    const act = [...S.actSel].map(i => S.sug.actividades[i]).map(a => ({
      material_id: a.material_id, partida_id: Number(a.partida_id), nombre: a.nombre, duracion_dias: Number(a.duracion_dias) || 1,
      fecha_inicio: a.fecha_inicio || null, pred_ids: a.pred_ids || [], pred_materiales: a.pred_materiales || [], sucesoras_ids: a.sucesoras_ids || [],
    }));
    if (!rel.length && !act.length) { toast('No hay nada seleccionado', 'warn'); return; }
    const btn = $('md-ms-aplicar'); btn.disabled = true;
    const d = await api('aplicar-sugerencias', { relaciones: rel, actividades: act });
    btn.disabled = false;
    if (d) {
      cerrarModal('md-modal-sugerencias');
      toast(d.actividades + ' actividad(es) y ' + d.relaciones + ' relación(es) creadas' + (d.errores && d.errores.length ? ' · ' + d.errores.join(' · ') : ''), d.errores && d.errores.length ? 'warn' : 'ok');
      pintar();
    }
  }

  // ---------- modales ----------
  function abrirModal(id) { const el = $(id); if (!el) return; el.classList.add('abierto'); el.setAttribute('aria-hidden', 'false'); document.body.classList.add('modal-abierto'); }
  function cerrarModal(id) { const el = $(id); if (!el) return; el.classList.remove('abierto'); el.setAttribute('aria-hidden', 'true'); if (!document.querySelector('.modal-overlay.abierto')) document.body.classList.remove('modal-abierto'); }

  // ---------- eventos ----------
  document.addEventListener('click', ev => {
    const cerrar = ev.target.closest('[data-cerrar]');
    if (cerrar) { cerrarModal(cerrar.dataset.cerrar); return; }
    if (ev.target.classList.contains('modal-overlay')) cerrarModal(ev.target.id);
  });
  document.addEventListener('keydown', ev => {
    if (ev.key === 'Escape') document.querySelectorAll('.modal-overlay.abierto').forEach(m => cerrarModal(m.id));
    if ((ev.key === 'Enter' || ev.key === ' ') && ev.target.matches('.modal-overlay')) return;
  });
  document.querySelectorAll('.md-modo').forEach(b => b.addEventListener('click', () => cambiarModo(b.dataset.modo)));
  document.querySelectorAll('.md-vista').forEach(b => b.addEventListener('click', () => {
    S.vista = b.dataset.vista;
    document.querySelectorAll('.md-vista').forEach(x => { x.classList.toggle('activa', x === b); x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
    pintar();
  }));
  const buscar = $('md-buscar');
  if (buscar) buscar.addEventListener('input', () => { S.q = buscar.value; pintar(); });
  $('md-esp').addEventListener('change', () => { S.esp = $('md-esp').value; pintar(); });
  $('md-estado').addEventListener('change', () => { S.estado = $('md-estado').value; pintar(); });
  $('md-solo').addEventListener('change', () => { S.solo = $('md-solo').checked; pintar(); });
  const conectar = $('md-conectar');
  if (conectar) conectar.addEventListener('click', () => {
    if (S.conectar) { S.conectar = false; S.origen = null; toast('Modo conectar cancelado'); pintar(); return; }
    S.conectar = true; S.origen = null; S.sel = null;
    toast('Clic en el material que debe estar primero y luego en el que lo necesita');
    pintar();
  });
  const vc = $('md-vacio-conectar'); if (vc) vc.addEventListener('click', () => { S.conectar = true; S.origen = null; pintar(); toast('Clic en el material que debe estar primero'); });
  const mcBuscar = $('md-mc-buscar'); if (mcBuscar) mcBuscar.addEventListener('input', pintarModalLista);
  const mcGuardar = $('md-mc-guardar'); if (mcGuardar) mcGuardar.addEventListener('click', guardarConexion);
  const rep = $('md-reprogramar'); if (rep) rep.addEventListener('click', abrirPrevia);
  const des = $('md-deshacer'); if (des) des.addEventListener('click', deshacer);
  const ap = $('md-mp-aplicar'); if (ap) ap.addEventListener('click', aplicarPrevia);
  const sug = $('md-sugerir'); if (sug) sug.addEventListener('click', abrirSugerencias);
  document.querySelectorAll('.md-ms-tab').forEach(b => b.addEventListener('click', () => {
    S.sugTab = b.dataset.tab; document.querySelectorAll('.md-ms-tab').forEach(x => x.classList.toggle('activa', x === b)); pintarSugerencias();
  }));
  const msa = $('md-ms-aplicar'); if (msa) msa.addEventListener('click', aplicarSugerencias);
  const scroll = $('md-scroll'); if (scroll) scroll.addEventListener('scroll', lineas, { passive: true });
  window.addEventListener('resize', lineas);
  window.addEventListener('load', lineas);

  // arranque
  pintar();
})();
