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
    const restr = a.restriccion ? '<span class="lt-restr" title="' + esc((a.restriccion.texto || []).join(' | ')) + '">⚠</span>' : '';
    const manos = (LT.puedeEditar && !a.hito) ? '<span class="lt-mano lt-mano-l" title="Cambiar el inicio">◀</span><span class="lt-mano lt-mano-r" title="Cambiar el fin">▶</span>' : '';
    // El número de la actividad con sus días de duración, centrado en la barra (los hitos solo muestran el rombo)
    const texto = a.hito ? '' : '<span class="lt-barra-texto mono">' + esc(a.codigo) + ' (' + (Number(a.duracion_dias) || 0) + ' d)</span>';
    // Icono (casilla «Icono de actividad»): disco circular centrado sobre la barra
    const icono = (LT.iconos && a.icono)
      ? '<img class="lt-icono" src="' + esc(a.icono) + '" alt="" draggable="false" style="left:' + (i0 * ppd + ((i1 - i0 + 1) * ppd) / 2 - 11) + 'px" onerror="this.remove()">'
      : '';
    return '<div class="lt-fila" style="top:' + top + 'px;height:' + alto + 'px">' +
      '<div class="' + cls + '" data-id="' + a.id + '" style="left:' + (i0 * ppd) + 'px;width:' + ((i1 - i0 + 1) * ppd) + 'px;background:' + esc(p.color || '#3b82f6') + '" title="' + esc(titulo) + '">' +
      '<span class="lt-barra-fill" style="width:' + (a.progreso || 0) + '%"></span>' + restr + manos + texto + '</div>' + icono + '</div>';
  }

  function pintar() {
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
    const filaAlto = LT.iconos ? 48 : ROW;
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
