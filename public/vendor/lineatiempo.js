/* Línea de tiempo activa: Gantt diario de todos los meses del proyecto, con navegación por meses
   y barras arrastrables. Mover el cuerpo de la barra cambia inicio y fin juntos; las flechas ◀ ▶
   de los extremos cambian solo el inicio o solo el fin. La rueda del ratón sobre el diagrama
   amplía o reduce (anclada en el día del cursor). Al soltar se ajusta al día hábil más cercano
   y se guarda al instante (sin recargar) en /api/lineatiempo/fechas. */
(function () {
  'use strict';
  const LT = window.LT;
  if (!LT || !document.getElementById('lt-canvas')) return;
  const $ = id => document.getElementById(id);
  const esc = t => String(t == null ? '' : t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const dn = s => Math.round(Date.UTC(+s.slice(0, 4), +s.slice(5, 7) - 1, +s.slice(8, 10)) / 864e5);
  const fecha = n => new Date(n * 864e5).toISOString().slice(0, 10);
  const fmt = s => s ? s.slice(8, 10) + '/' + s.slice(5, 7) : '—';
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
  const DOW = ['', 'L', 'M', 'M', 'J', 'V', 'S', 'D'];
  const HEAD = 62, GRP = 26, ROW = 30;   // HEAD = banda de mes (22) + celdas de día (40)
  const PPD_MIN = 8, PPD_MAX = 120;
  let ppd = 30;
  const d0 = dn(LT.inicio), len = LT.dias.length;
  const idxDe = s => dn(s) - d0;
  const esHabil = i => !!(LT.dias[i] && LT.dias[i].habil);
  const act = id => LT.actividades.find(a => a.id === id);
  const partidas = new Map(LT.partidas.map(p => [p.id, p]));
  let mesesIdx = [];   // [{i0, i1, etiqueta}] de la última pintada

  /** Índice de día hábil más cercano (por delante si hay empate) */
  function snap(i) {
    if (esHabil(i)) return i;
    for (let dif = 1; dif < len; dif++) {
      if (esHabil(i + dif)) return i + dif;
      if (esHabil(i - dif)) return i - dif;
    }
    return clamp(i, 0, len - 1);
  }

  /** Columnas [inicio, fin] de una actividad, recortadas al rango visible */
  function barraI(a) {
    let i0 = Math.max(0, idxDe(a.fecha_inicio)), i1 = Math.min(len - 1, idxDe(a.fecha_fin));
    if (i1 < i0) i1 = i0;
    return [i0, i1];
  }

  /** Meses consecutivos del rango: [{i0, i1, etiqueta}] */
  function agruparMeses() {
    const out = [];
    let i = 0;
    while (i < len) {
      const d = LT.dias[i];
      let j = i;
      while (j + 1 < len && LT.dias[j + 1].mes === d.mes && LT.dias[j + 1].anio === d.anio) j++;
      const anioPrev = i > 0 ? LT.dias[i - 1].anio : null;
      const etiqueta = MESES[d.mes - 1] + ((d.mes === 1 || i === 0 || anioPrev !== d.anio) ? ' ' + d.anio : '');
      out.push({ i0: i, i1: j, etiqueta });
      i = j + 1;
    }
    return out;
  }

  // ---------- avisos ----------
  let tAviso = null;
  function aviso(txt, tipo = 'ok') {
    const el = $('lt-aviso'); el.hidden = !txt; el.className = 'lt-aviso lt-aviso-' + tipo; el.innerHTML = txt;
    clearTimeout(tAviso); if (txt) tAviso = setTimeout(() => { el.hidden = true; }, 6000);
  }

  // ---------- pintado ----------
  function barra(a, top, alto) {
    const p = partidas.get(a.partida_id) || {};
    const [i0, i1] = barraI(a);
    const cls = 'lt-barra st-' + esc(a.status) + (a.hito ? ' lt-hito' : '') + (a.fecha_inicio < LT.inicio ? ' corta-ini' : '') + (a.fecha_fin > LT.fin ? ' corta-fin' : '');
    const titulo = a.codigo + ' — ' + a.nombre + ' | ' + fmt(a.fecha_inicio) + ' → ' + fmt(a.fecha_fin) + ' | ' + a.progreso + '%';
    const x0 = i0 * ppd, ancho = (i1 - i0 + 1) * ppd, x1 = x0 + ancho;
    const centro = alto - 15;   // centro vertical de la barra (fila normal y fila con iconos)
    const manos = (LT.puedeEditar && !a.hito) ? '<span class="lt-mano lt-mano-l" title="Cambiar el inicio">◀</span><span class="lt-mano lt-mano-r" title="Cambiar el fin">▶</span>' : '';
    // El número de la actividad con sus días de duración, centrado en la barra (los hitos solo muestran el rombo)
    const texto = a.hito ? '' : '<span class="lt-barra-texto mono">' + esc(a.codigo) + ' (' + (Number(a.duracion_dias) || 0) + ' d)</span>';
    // Chips a la derecha de la barra, fuera de ella y legibles: estado (✕ roja = no iniciada, reloj amarillo =
    // en proceso, paloma verde = completada) y, a continuación, la restricción en rojo (si no cabe al borde, se meten)
    const estTam = 24, restTam = 26, holg = 5;
    const bloq = estTam + (a.restriccion ? holg + restTam : 0);
    let xDer = x1 + holg;
    if (xDer + bloq > LT.dias.length * ppd) xDer = Math.max(x0 + 2, LT.dias.length * ppd - bloq - holg);
    const estTit = a.status === 'completada' ? 'Completada' : a.status === 'en_proceso' ? 'En proceso' : 'No iniciada';
    const estSvg = a.status === 'completada'
      ? '<svg viewBox="0 0 16 16"><path d="M3 8.5l3.5 3.5L13 5" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>'
      : a.status === 'en_proceso'
        ? '<svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="5.8" fill="none" stroke="#fff" stroke-width="2"/><path d="M8 4.4V8l2.6 1.7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>'
        : '<svg viewBox="0 0 16 16"><path d="M4.5 4.5l7 7m0-7l-7 7" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/></svg>';
    const chipEst = '<span class="lt-chip-est st-' + esc(a.status) + '" title="' + estTit + '" style="left:' + xDer + 'px;top:' + (centro - estTam / 2) + 'px">' + estSvg + '</span>';
    const chipRest = a.restriccion
      ? '<span class="lt-chip-restr" data-id="' + a.id + '" style="left:' + (xDer + estTam + holg) + 'px;top:' + (centro - restTam / 2) + 'px"><svg viewBox="0 0 16 16"><path d="M8 2.4 14.6 13.6H1.4z" fill="#fff"/><rect x="7.2" y="6" width="1.6" height="4.4" rx=".8" fill="#ef4444"/><circle cx="8" cy="12.2" r="1" fill="#ef4444"/></svg></span>'
      : '';
    // Icono (casilla «Icono de actividad»): disco circular centrado sobre la barra
    const icono = (LT.iconos && a.icono)
      ? '<img class="lt-icono" src="' + esc(a.icono) + '" alt="" draggable="false" style="left:' + (x0 + ancho / 2 - 24) + 'px" onerror="this.remove()">'
      : '';
    return '<div class="lt-fila" style="top:' + top + 'px;height:' + alto + 'px">' +
      '<div class="' + cls + '" data-id="' + a.id + '" style="left:' + x0 + 'px;width:' + ancho + 'px;background:' + esc(p.color || '#3b82f6') + '" title="' + esc(titulo) + '">' +
      '<span class="lt-barra-fill" style="width:' + (a.progreso || 0) + '%"></span>' + manos + texto + '</div>' + chipEst + chipRest + icono + '</div>';
  }

  function pintar() {
    if (pop && !pop.hidden) popOcultar();   // la burbuja de restricción no sobrevive a un repintado
    const sc = $('lt-scroll'), sl = sc.scrollLeft, st = sc.scrollTop;
    $('lt-canvas').style.width = (len * ppd + 20) + 'px';
    $('lt-canvas').classList.toggle('lt-ppd-chico', ppd < 22);
    $('lt-canvas').classList.toggle('lt-iconos', !!LT.iconos);

    if (!LT.actividades.length) {
      $('lt-labels').innerHTML = '<div class="lt-lab-cab" style="height:' + HEAD + 'px">Actividad / partida</div>';
      $('lt-canvas').innerHTML = '<div class="lt-vacio">No hay actividades que cumplan los filtros. Cambia la partida, el estado o quita «Solo con restricción».</div>';
      mesesIdx = [];
      return;
    }

    // Eje: banda de meses + celdas de día; fondo con días no hábiles y línea de hoy
    mesesIdx = agruparMeses();
    let bandas = '';
    mesesIdx.forEach(m => {
      bandas += '<div class="lt-eje-mes" style="left:' + (m.i0 * ppd) + 'px;width:' + ((m.i1 - m.i0 + 1) * ppd) + 'px" title="' + esc(m.etiqueta) + '">' + esc(m.etiqueta) + '</div>';
    });
    let eje = '', fondo = '';
    LT.dias.forEach((d, i) => {
      const x = i * ppd;
      eje += '<div class="lt-eje-dia' + (d.habil ? '' : ' nohabil') + '" style="left:' + x + 'px;width:' + ppd + 'px" title="' + esc(d.fecha) + '"><b>' + d.nro + '</b><span>' + DOW[d.dow] + '</span></div>';
      if (!d.habil) fondo += '<div class="lt-nohabil" style="left:' + x + 'px;width:' + ppd + 'px"></div>';
    });
    const hoyIdx = idxDe(LT.hoy);
    if (hoyIdx >= 0 && hoyIdx < len) fondo += '<div class="lt-hoy" style="left:' + (hoyIdx * ppd + Math.round(ppd / 2)) + 'px"></div>';

    // Etiquetas + filas (agrupadas por partida); con icono la fila de actividad crece para que el disco quepa sobre la barra
    const filaAlto = LT.iconos ? 82 : ROW;   // el icono mide 48 px: la fila crece para que quepa sobre la barra
    let labels = '<div class="lt-lab-cab" style="height:' + HEAD + 'px">Actividad / partida</div>', filas = '', y = HEAD;
    LT.partidas.forEach(p => {
      const acts = LT.actividades.filter(a => a.partida_id === p.id);
      if (!acts.length) return;
      labels += '<div class="lt-lab-grp" style="height:' + GRP + 'px"><i class="lt-chip" style="background:' + esc(p.color || '#3b82f6') + '"></i><span>' + esc(p.codigo) + ' · ' + esc(p.nombre) + '</span>' +
        (p.restricciones ? ' <span class="lt-warn" title="' + p.restricciones + ' actividad(es) con restricción">⚠</span>' : '') + '</div>';
      filas += '<div class="lt-grp" style="top:' + y + 'px;height:' + GRP + 'px"><i class="lt-chip" style="background:' + esc(p.color || '#3b82f6') + '"></i>' + esc(p.codigo) + ' · ' + esc(p.nombre) + '</div>';
      y += GRP;
      acts.forEach(a => {
        labels += '<div class="lt-lab" style="height:' + filaAlto + 'px" title="' + esc(a.codigo + ' — ' + a.nombre) + '"><b>' + esc(a.codigo) + '</b> ' + esc(a.nombre) +
          (a.restriccion ? ' <span class="lt-warn" title="' + esc((a.restriccion.texto || []).join(' | ')) + '">⚠</span>' : '') + '</div>';
        filas += barra(a, y, filaAlto);
        y += filaAlto;
      });
    });

    $('lt-labels').innerHTML = labels;
    $('lt-canvas').innerHTML = '<div class="lt-eje">' + bandas + eje + '</div>' + fondo + filas;
    sc.scrollLeft = sl; sc.scrollTop = st;
    actualizarNav();
  }

  // ---------- guardar ----------
  function guardar(a, ini, fin) {
    const fd = new FormData();
    fd.append('proyecto', LT.proyecto); fd.append('actividad_id', a.id);
    fd.append('fecha_inicio', ini); fd.append('fecha_fin', fin);
    return fetch('/api/lineatiempo/fechas', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (!d.success) { aviso(d.error || 'No se pudo guardar el cambio', 'error'); return false; }
        a.fecha_inicio = d.fecha_inicio; a.fecha_fin = d.fecha_fin; a.duracion_dias = d.duracion_dias;
        if (d.dependientes_movidas > 0) aviso('<strong>' + d.dependientes_movidas + '</strong> actividad(es) dependiente(s) se reprogramaron con este movimiento (dependencias en modo automático).', 'ok');
        return true;
      })
      .catch(() => { aviso('Error de conexión: el cambio no se guardó.', 'error'); return false; });
  }

  // ---------- arrastre y redimensionado ----------
  let drag = null, panear = null, tip = null;
  function tooltip() {
    if (!tip) { tip = document.createElement('div'); tip.className = 'lt-tooltip'; document.body.appendChild(tip); }
    return tip;
  }
  const ocultarTooltip = () => { if (tip) { tip.remove(); tip = null; } };

  const canvas = $('lt-canvas');
  canvas.addEventListener('pointerdown', e => {
    // Botón central (rueda) presionado: paneo del diagrama (funciona también en solo lectura)
    if (e.button === 1) {
      e.preventDefault();
      const sc = $('lt-scroll');
      panear = { x0: e.clientX, y0: e.clientY, sl: sc.scrollLeft, st: sc.scrollTop };
      canvas.classList.add('lt-paneo');
      try { canvas.setPointerCapture(e.pointerId); } catch (err) { }
      return;
    }
    if (!LT.puedeEditar) return;
    const barraEl = e.target.closest('.lt-barra');
    if (!barraEl) return;
    const a = act(+barraEl.dataset.id);
    if (!a || !a.fecha_inicio || !a.fecha_fin) return;
    // Un hito no se redimensiona: solo se mueve completo
    const modo = a.hito ? 'mover' : e.target.classList.contains('lt-mano-l') ? 'ini' : e.target.classList.contains('lt-mano-r') ? 'fin' : 'mover';
    const [i0, i1] = barraI(a);
    drag = { a, barra: barraEl, modo, x0: e.clientX, i0orig: i0, i1orig: i1, ini: a.fecha_inicio, fin: a.fecha_fin, movido: false, ni0: i0, ni1: i1 };
    barraEl.classList.add('lt-arrastre');
    try { barraEl.setPointerCapture(e.pointerId); } catch (err) { }
    e.preventDefault();
  });

  canvas.addEventListener('pointermove', e => {
    if (panear) {   // paneo con el botón central: mover el área visible sin cambiar el zoom
      const sc = $('lt-scroll');
      sc.scrollLeft = panear.sl - (e.clientX - panear.x0);
      sc.scrollTop = panear.st - (e.clientY - panear.y0);
      return;
    }
    if (!drag) return;
    const d = Math.round((e.clientX - drag.x0) / ppd);
    if (d !== 0) drag.movido = true;
    const span = drag.i1orig - drag.i0orig;
    let i0 = drag.i0orig, i1 = drag.i1orig;
    if (drag.modo === 'mover') {           // mover la barra entera conservando su largo
      i0 = clamp(snap(clamp(drag.i0orig + d, 0, len - 1 - span)), 0, len - 1 - span);
      i1 = i0 + span;
    } else if (drag.modo === 'ini') {      // flecha izquierda: cambiar solo el inicio
      i0 = clamp(snap(clamp(drag.i0orig + d, 0, drag.i1orig)), 0, drag.i1orig);
    } else {                               // flecha derecha: cambiar solo el fin
      i1 = clamp(snap(clamp(drag.i1orig + d, drag.i0orig, len - 1)), drag.i0orig, len - 1);
    }
    drag.ni0 = i0; drag.ni1 = i1;
    drag.barra.classList.toggle('lt-cambio', i0 !== drag.i0orig || i1 !== drag.i1orig);
    drag.barra.style.left = (i0 * ppd) + 'px';
    drag.barra.style.width = ((i1 - i0 + 1) * ppd) + 'px';
    const t = tooltip();
    t.textContent = fmt(fecha(d0 + i0)) + ' → ' + fmt(fecha(d0 + i1));
    t.style.left = e.clientX + 'px';
    t.style.top = (e.clientY + 18) + 'px';
  });

  function terminarDrag() {
    if (!drag) return;
    const d = drag; drag = null;
    d.barra.classList.remove('lt-arrastre', 'lt-cambio');
    ocultarTooltip();
    if (!d.movido || (d.ni0 === d.i0orig && d.ni1 === d.i1orig)) { pintar(); return; }   // nada que guardar: se restaura
    const ini = fecha(d0 + d.ni0), fin = fecha(d0 + d.ni1);
    const orig = { ini: d.ini, fin: d.fin };
    d.a.fecha_inicio = ini; d.a.fecha_fin = fin;   // optimista: se pinta ya en su lugar
    guardar(d.a, ini, fin).then(ok => {
      if (!ok) { d.a.fecha_inicio = orig.ini; d.a.fecha_fin = orig.fin; }   // rollback
      pintar();
    });
  }
  function terminarPan() {
    if (!panear) return;
    panear = null;
    canvas.classList.remove('lt-paneo');
  }
  canvas.addEventListener('pointerup', e => { terminarPan(); terminarDrag(); });
  canvas.addEventListener('pointercancel', e => { terminarPan(); terminarDrag(); });
  // El botón central no debe disparar el modo "desplazamiento automático" del navegador
  canvas.addEventListener('mousedown', e => { if (e.button === 1) e.preventDefault(); });
  canvas.addEventListener('auxclick', e => { if (e.button === 1) e.preventDefault(); });

  // ---------- zoom con la rueda del ratón (anclado en el día bajo el cursor) ----------
  canvas.addEventListener('wheel', e => {
    if (e.deltaX !== 0 && e.deltaY === 0) return;   // desplazamiento lateral normal (trackpad)
    e.preventDefault();
    const sc = $('lt-scroll'), labelsW = $('lt-labels').offsetWidth;
    const px = e.clientX - sc.getBoundingClientRect().left;   // cursor dentro del área visible
    const dia = (sc.scrollLeft + px - labelsW) / ppd;        // día bajo el cursor
    const nuevo = clamp(Math.round(ppd * (e.deltaY < 0 ? 1.14 : 1 / 1.14) * 10) / 10, PPD_MIN, PPD_MAX);
    if (nuevo === ppd) return;
    ppd = nuevo;
    pintar();
    sc.scrollLeft = Math.max(0, labelsW + dia * ppd - px);    // el mismo día queda bajo el cursor
  }, { passive: false });

  // ---------- navegación entre meses (sin recargar) ----------
  function mesDe(i) {
    for (let k = 0; k < mesesIdx.length; k++) if (i >= mesesIdx[k].i0 && i <= mesesIdx[k].i1) return k;
    return i < 0 ? 0 : Math.max(0, mesesIdx.length - 1);
  }
  function actualizarNav() {
    if (!mesesIdx.length) return;
    const k = mesDe(clamp(Math.round($('lt-scroll').scrollLeft / ppd), 0, len - 1));
    $('lt-prev').disabled = k <= 0;
    $('lt-next').disabled = k >= mesesIdx.length - 1;
    const h = idxDe(LT.hoy);
    $('lt-hoy').disabled = h < 0 || h >= len;
  }
  function irAMes(paso) {
    if (!mesesIdx.length) return;
    const k = mesDe(clamp(Math.round($('lt-scroll').scrollLeft / ppd), 0, len - 1));
    const destino = mesesIdx[clamp(k + paso, 0, mesesIdx.length - 1)];
    $('lt-scroll').scrollTo({ left: Math.max(0, destino.i0 * ppd), behavior: 'smooth' });
    setTimeout(actualizarNav, 420);
  }
  $('lt-prev').addEventListener('click', () => irAMes(-1));
  $('lt-next').addEventListener('click', () => irAMes(1));
  $('lt-hoy').addEventListener('click', () => {
    const h = idxDe(LT.hoy);
    if (h < 0 || h >= len) return;
    $('lt-scroll').scrollTo({ left: Math.max(0, (h - 3) * ppd), behavior: 'smooth' });
    setTimeout(actualizarNav, 420);
  });
  $('lt-scroll').addEventListener('scroll', actualizarNav, { passive: true });

  // ---------- burbuja de la restricción (flotante y con retardo, igual que las burbujas de ayuda) ----------
  let pop = null, popT = 0, popOc = 0, popChip = null, popAct = null;
  function popOcultar() {
    clearTimeout(popT); clearTimeout(popOc);
    if (pop) pop.hidden = true;
    if (popChip) { popChip.removeAttribute('aria-describedby'); popChip = null; }
    popAct = null;
  }
  function popOcultarPronto() { clearTimeout(popOc); popOc = setTimeout(popOcultar, 150); }   // gracia para llegar a la burbuja
  function popMostrar(chip) {
    const a = act(+chip.dataset.id);
    if (!a || !a.restriccion || !document.body.contains(chip)) return;
    if (!pop) {
      pop = document.createElement('div');
      pop.className = 'burbuja-ayuda lt-restr-pop';
      pop.id = 'lt-restr-pop';
      pop.setAttribute('role', 'tooltip');
      pop.hidden = true;
      pop.addEventListener('mouseenter', () => clearTimeout(popOc));
      pop.addEventListener('mouseleave', popOcultarPronto);
      document.body.appendChild(pop);
    }
    const items = (a.restriccion.items || []);
    pop.innerHTML = items.length
      ? items.map(it => {
          const cab = esc([it.tipo, it.codigo].filter(Boolean).join(' · '));
          const btn = (LT.puedeEditar && it.id)
            ? '<button type="button" class="lt-pop-btn" data-res="' + (it.id | 0) + '"><i class="fa-solid fa-check"></i> Resolver</button>'
            : '';
          return '<div class="lt-pop-item">' + (cab ? '<span class="lt-pop-cab">' + cab + '</span>' : '') + '<p>' + esc(it.descripcion) + '</p>' + btn + '</div>';
        }).join('')
      : '<p>' + esc((a.restriccion.texto || []).join(' · ')) + '</p>';
    popAct = a; popChip = chip;
    pop.hidden = false;
    chip.setAttribute('aria-describedby', pop.id);
    // Cerca del chip: debajo si cabe; si no, arriba; siempre dentro de la ventana
    const r = chip.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight, m = 8;
    let x = clamp(r.left, m, Math.max(m, innerWidth - w - m));
    let y = r.bottom + m;
    if (y + h > innerHeight - m) y = r.top - h - m;
    pop.style.left = x + 'px';
    pop.style.top = Math.max(m, y) + 'px';
  }
  function popProgramar(chip) {
    clearTimeout(popT); clearTimeout(popOc);
    if (popChip === chip && pop && !pop.hidden) return;
    popT = setTimeout(() => popMostrar(chip), 650);
  }
  function resolverRest(id) {
    const a = popAct;
    if (!a) return;
    if (!confirm('¿Marcar la restricción como resuelta?')) return;
    const fd = new FormData();
    fd.append('proyecto', LT.proyecto); fd.append('id', id);
    fetch('/api/lineatiempo/resolver', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (!d.success) { aviso(d.error || 'No se pudo resolver la restricción', 'error'); return; }
        const quedan = ((a.restriccion && a.restriccion.items) || []).filter(x => x.id !== +id);
        if (quedan.length) {
          a.restriccion.items = quedan;
        } else {
          a.restriccion = null;
          const p = partidas.get(a.partida_id);
          if (p && +p.restricciones > 0) p.restricciones = +p.restricciones - 1;
          LT.conRestriccion = Math.max(0, (+LT.conRestriccion || 1) - 1);
          const cN = $('lt-cont-restr-n');
          if (cN) {
            cN.textContent = LT.conRestriccion;
            if (!LT.conRestriccion && $('lt-cont-restr')) $('lt-cont-restr').hidden = true;
          }
          if (LT.soloRestriccion) {   // con el filtro «Solo con restricción» activo, la actividad ya no corresponde
            LT.actividades = LT.actividades.filter(x => x.id !== a.id);
            LT.total = Math.max(0, (+LT.total || 1) - 1);
            const cT = $('lt-cont-total');
            if (cT) cT.textContent = LT.total;
          }
        }
        popOcultar();
        aviso('Restricción resuelta.', 'ok');
        pintar();
      })
      .catch(() => aviso('Error de conexión: la restricción no se pudo resolver.', 'error'));
  }

  // Retardo al pasar el cursor sobre la ⚠ (los chips de estado no capturan el puntero)
  document.addEventListener('mouseover', e => {
    if (e.pointerType === 'touch') return;
    const chip = e.target instanceof Element ? e.target.closest('.lt-chip-restr') : null;
    if (chip) popProgramar(chip);
  });
  document.addEventListener('mouseout', e => {
    const chip = e.target instanceof Element ? e.target.closest('.lt-chip-restr') : null;
    if (!chip || (e.relatedTarget && chip.contains(e.relatedTarget))) return;
    if (pop && e.relatedTarget instanceof Node && pop.contains(e.relatedTarget)) return;
    clearTimeout(popT);
    popOcultarPronto();
  });
  document.addEventListener('click', e => {
    const b = e.target instanceof Element ? e.target.closest('.lt-pop-btn') : null;
    if (b) { resolverRest(+b.dataset.res); return; }
    const sobre = e.target instanceof Element && (e.target.closest('.lt-restr-pop') || e.target.closest('.lt-chip-restr'));
    if (pop && !pop.hidden && !sobre) popOcultar();
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') popOcultar(); });
  addEventListener('scroll', () => { if (pop && !pop.hidden) popOcultar(); }, true);
  addEventListener('resize', () => { if (pop && !pop.hidden) popOcultar(); });

  // ---------- inicio ----------
  pintar();
  let iInicial = 0;
  if (LT.mesAncla) {
    const i = LT.dias.findIndex(d => d.fecha >= LT.mesAncla + '-01');
    if (i >= 0) iInicial = i;
  } else {
    const h = idxDe(LT.hoy);
    if (h >= 0 && h < len) iInicial = Math.max(0, h - 3);
  }
  $('lt-scroll').scrollLeft = iInicial * ppd;
  actualizarNav();
})();
