'use strict';
// Ensamblador de documentos: sube PDF, PowerPoint, Word, OpenDocument o texto; la IA los analiza y un chat pregunta qué hacer con lo encontrado.
// Lo que propone se muestra como lista de cambios con casillas: nada se aplica hasta que el admin pulsa «Aplicar» y todo se puede deshacer.
const Ensamblador = (() => {
  const $ = s => document.querySelector(s);
  const el = (tag, txt, cls) => { const e = document.createElement(tag); if (txt != null) e.textContent = txt; if (cls) e.className = cls; return e; };
  const err = e => String(e && e.message || e).replace(/^Error invoking remote method '[^']+': (Error: )?/, '');
  const fecha = f => { const d = new Date(String(f).replace(' ', 'T')); return isNaN(d) ? String(f || '') : d.toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }); };
  const MODOS = [['plantilla', '🏗 Plantilla de proyecto'], ['partidas_nuevas', '➕ Partidas nuevas'], ['partidas_existentes', '✏️ Modificar partidas existentes'], ['materiales', '🧱 Materiales'], ['submittal_nuevo', '📦 Submittal nuevo'], ['submittal_existente', '📎 Agregar a un submittal'], ['dependencias', '🔗 Dependencias'], ['contrato', '📑 Contrato: fechas, festivos, restricciones'], ['responsables', '👷 Contactos y responsables'], ['cantidades', '📐 Cantidades de obra'], ['minuta', '📝 Compromisos de minuta'], ['normativa', '📚 Guardar en Normativa']];
  const GRUPOS = [['partida_nueva', 'Partidas nuevas'], ['partida_actualizar', 'Partidas que se modifican'], ['material_nuevo', 'Materiales nuevos'], ['material_actualizar', 'Materiales que se completan'], ['submittal', 'Submittal'], ['fecha_contrato', 'Fechas contractuales'], ['festivo', 'Días no hábiles (festivos)'], ['dependencia', 'Dependencias entre actividades'], ['restriccion', 'Restricciones'], ['cantidad_obra', 'Cantidades de obra'], ['contacto', 'Contactos'], ['responsable', 'Responsables de actividad'], ['compromiso', 'Compromisos (a la bitácora)'], ['normativa_documento', 'Documentos para Normativa']];
  const ESTADO_PROP = { pendiente: 'Pendiente', aplicada: 'Aplicada', revertida: 'Deshecha', rechazada: 'Descartada' };
  const IRA = { partida_nueva: ['/programa', 'Ver programa'], partida_actualizar: ['/programa', 'Ver programa'], material_nuevo: ['/materiales', 'Ver materiales'], material_actualizar: ['/materiales', 'Ver materiales'], submittal: ['/submittals', 'Ver submittals'], dependencia: ['/dependencias', 'Ver dependencias'], festivo: ['/ajustes', 'Ver calendario'], restriccion: ['/programa', 'Ver programa'], fecha_contrato: ['/programa', 'Ver programa'], cantidad_obra: ['/programa', 'Ver programa'], contacto: ['/reportes/personal', 'Ver personal'], responsable: ['/programa', 'Ver programa'], compromiso: ['/bitacora', 'Ver bitácora'], normativa_documento: ['/normativa', 'Ver Normativa'] };

  let abrirWeb = () => {}, alCambiar = () => {}, proyectos = null, cfg = null;
  let pid = null, ses = 0, archivos = [], modos = new Set(), submittals = [], historial = [], ocupado = false, listo = false;

  const burbuja = (rol, contenido) => {
    const b = el('div', null, 'ens-msg ' + rol);
    if (typeof contenido === 'string') b.textContent = contenido; else b.append(...[].concat(contenido));
    $('#ens-chat').append(b); b.scrollIntoView({ block: 'end' });
    return b;
  };
  const asistente = c => burbuja('ia', c);

  function pintarChips() {
    const caja = $('#ens-chips');
    caja.replaceChildren(...MODOS.map(([k, etq]) => {
      const b = el('button', etq, 'sec chip' + (modos.has(k) ? ' on' : '')); b.type = 'button';
      b.addEventListener('click', () => { modos.has(k) ? modos.delete(k) : modos.add(k); pintarChips(); });
      return b;
    }));
    const todo = el('button', '✨ Lo que encuentres', 'sec chip' + (modos.size ? '' : ' on')); todo.type = 'button'; todo.title = 'Sin botones elegidos, la IA decide qué información útil trae el documento';
    todo.addEventListener('click', () => { modos.clear(); pintarChips(); });
    const plantilla = el('button', '🏗 Plantilla de proyecto', 'sec chip' + (modos.size === 1 && modos.has('plantilla') ? ' on' : ''));
    plantilla.type = 'button'; plantilla.title = 'Leer el documento como un proceso constructivo y proponer una plantilla reutilizable (sin fechas ni avance)';
    plantilla.addEventListener('click', () => {
      modos.clear(); modos.add('plantilla'); pintarChips();
      const t = $('#ens-texto'); if (!t.value.trim()) t.value = 'Analiza el documento como un proceso constructivo completo y propone una plantilla reutilizable.';
    });
    caja.prepend(todo); caja.prepend(plantilla);
    const sel = $('#ens-sub');
    sel.hidden = !modos.has('submittal_existente');
    sel.replaceChildren(...(submittals.length ? submittals : [{ id: '', numero: 'No hay submittals' }]).map(s => { const o = el('option', s.numero + (s.especialidad ? ' · ' + s.especialidad : '')); o.value = s.id; return o; }));
  }

  function pintarArchivos() {
    const ul = $('#ens-archivos');
    ul.replaceChildren(...archivos.map(a => {
      const li = el('li'), x = el('button', '✕', 'sec ens-x'); x.type = 'button'; x.title = 'Quitar de esta conversación';
      x.addEventListener('click', async () => { try { await pw.ens.quitar(pid, ses, a.id); archivos = archivos.filter(y => y.id !== a.id); pintarArchivos(); } catch (e) { $('#ens-msg').textContent = '✘ ' + err(e); } });
      li.append(el('span', '📄 ' + a.nombre), el('small', a.paginas + ' pág./diap. · ' + Math.round(a.caracteres / 1000) + ' mil caracteres' + (a.duplicado ? ' · ya estaba en el proyecto' : '') + (a.aviso ? ' · ⚠ ' + a.aviso : ''), 'tenue'), x);
      return li;
    }));
    $('#ens-archivos-tit').hidden = !archivos.length;
  }

  function pintarHistorial() {
    const caja = $('#ens-hist');
    caja.replaceChildren();
    if (!historial.length) return;
    caja.append(el('h3', 'Propuestas anteriores'));
    for (const h of historial) {
      const fila = el('div', null, 'analisis'), t = el('div'), ver = el('button', 'Abrir', 'sec'); ver.type = 'button';
      t.append(el('strong', (h.instruccion || 'Propuesta ' + h.id).slice(0, 80)), el('small', fecha(h.creado) + ' · ' + ESTADO_PROP[h.estado] + ' · ' + h.aplicados + ' de ' + h.items + ' cambios aplicados'));
      ver.addEventListener('click', async () => { try { const r = await pw.ens.propuesta(pid, h.id); asistente(tarjeta(r.propuesta, [])); } catch (e) { $('#ens-msg').textContent = '✘ ' + err(e); } });
      fila.append(t, ver); caja.append(fila);
    }
  }

  async function recargarEstado() {
    if (!pid) return;
    try { const st = await pw.ens.estado(pid); submittals = st.submittals || []; historial = st.historial || []; } catch (e) { $('#ens-msg').textContent = '✘ ' + err(e); }
    pintarChips(); pintarHistorial();
  }

  /** Líneas legibles con todo lo que haría un cambio, para decidir antes de marcarlo */
  function lineasDetalle(i) {
    const d = i.datos || {}, L = [];
    const nom = a => a.nombre + (a.duracion_dias ? ' (' + a.duracion_dias + ' d)' : ' (sin duración: 1 día)') + (a.descripcion ? ' — ' + a.descripcion : '');
    if (i.tipo === 'partida_nueva') {
      if (d.descripcion) L.push('Descripción: ' + d.descripcion);
      L.push('Actividades que se crearían (' + (d.actividades || []).length + '):', ...(d.actividades || []).map(a => '  • ' + nom(a)));
      L.push('Código y fechas: se asignan al final del programa al aplicar.');
    } else if (i.tipo === 'partida_actualizar') {
      if (d.descripcion) L.push('Descripción que se agregaría: ' + d.descripcion);
      if ((d.actividades_nuevas || []).length) L.push('Actividades nuevas:', ...d.actividades_nuevas.map(a => '  • ' + nom(a)));
      if ((d.actividades_completar || []).length) L.push('Actividades a las que se les completa la descripción:', ...d.actividades_completar.map(a => '  • ' + a.nombre + ' — ' + a.descripcion));
    } else if (i.tipo === 'material_nuevo') {
      for (const [k, etq] of [['nombre_base', 'Material'], ['marca', 'Marca'], ['modelo', 'Modelo'], ['valor_extra', 'Valor extra'], ['cantidad', 'Cantidad'], ['unidad', 'Unidad'], ['especialidad', 'Especialidad'], ['referencia', 'Referencia']]) if (d[k] != null && d[k] !== '') L.push(etq + ': ' + d[k]);
    } else if (i.tipo === 'material_actualizar') {
      L.push('Solo se llenan campos vacíos; no se sobrescribe nada:', ...Object.entries(d.cambios || {}).map(([k, v]) => '  • ' + k + ' → ' + v));
    } else if (i.tipo === 'submittal') {
      L.push(d.destino === 'existente' ? 'Se agregan al submittal existente.' : 'Submittal nuevo' + (d.numero ? ' ' + d.numero : '') + (d.especialidad ? ' · ' + d.especialidad : ''));
      L.push('Materiales (' + (d.materiales || []).length + '):', ...(d.materiales || []).map(m => '  • ' + m));
    } else if (i.tipo === 'dependencia') {
      L.push('Tipo: ' + d.tipo + (d.retraso ? ' · desfase ' + d.retraso + ' d' : ''), d.previa ? 'Ya existía una relación (' + d.previa.tipo + (d.previa.retraso ? ' ' + d.previa.retraso + ' d' : '') + '): se reemplaza.' : 'Relación nueva.', 'Si crea un ciclo, no se aplica.');
      if (d.cita) L.push('Texto del documento: «' + d.cita + '»');
    } else if (i.tipo === 'festivo') {
      L.push('Fecha: ' + d.fecha, 'Motivo: ' + d.motivo, 'Se agrega al calendario del proyecto como día no hábil.');
    } else if (i.tipo === 'fecha_contrato') {
      L.push('Tipo: ' + d.tipo, 'Fecha: ' + d.fecha, ...(d.penalizacion ? ['Penalización: ' + d.penalizacion] : []), ...(d.origen ? ['Texto del documento: «' + d.origen + '»'] : []));
    } else if (i.tipo === 'restriccion') {
      L.push('Descripción: ' + d.descripcion, ...(d.fecha ? ['Fecha límite: ' + d.fecha] : []), 'Se crea activa en la actividad indicada.');
    } else if (i.tipo === 'cantidad_obra') {
      L.push((d.clave ? 'Clave: ' + d.clave + '\n' : '') + 'Concepto: ' + d.concepto, 'Cantidad: ' + d.cantidad + ' ' + d.unidad, ...(d.importe != null ? ['Importe: ' + d.importe] : []), 'Si todas las actividades tienen importe, la Curva S pesa el avance por importe.');
    } else if (i.tipo === 'contacto') {
      for (const [k, etq] of [['departamento', 'Departamento'], ['cargo', 'Cargo'], ['empresa', 'Empresa'], ['telefono', 'Teléfono'], ['correo', 'Correo']]) if (d[k]) L.push(etq + ': ' + d[k]);
      L.push(d.personal_id ? 'Solo se llenan campos vacíos.' : 'Se agrega al personal del proyecto.');
    } else if (i.tipo === 'responsable') {
      L.push('Persona: ' + d.persona, d.previo ? 'Reemplaza a ' + d.previo : 'La actividad no tenía responsable.');
    } else if (i.tipo === 'compromiso') {
      L.push('Fecha: ' + d.fecha, ...(d.departamento ? ['Departamento: ' + d.departamento] : []), 'Importancia: ' + d.importancia, d.descripcion);
    } else if (i.tipo === 'normativa_documento') {
      L.push('Archivo: ' + d.nombre, 'Se indexa por cláusulas y las vinculaciones a actividades y materiales quedan como «propuesta» para que las aceptes en Normativa.');
    }
    if (i.estado === 'aplicado') L.push('Ya aplicado: «Deshacer lo aplicado» lo quita (conserva lo que se haya usado después).');
    return L;
  }

  /** Tarjeta con la propuesta: casillas por cambio, aplicar / descartar / deshacer */
  function tarjeta(prop, notas) {
    const caja = el('div', null, 'ens-prop');
    const cabecera = el('div', null, 'ens-prop-cab');
    const items = prop.items || [];
    const pendientes = () => items.filter(i => i.estado !== 'aplicado' && i.estado !== 'revertido');
    const marcas = new Map();
    caja.append(cabecera);
    if (prop.instruccion) caja.append(el('small', 'Pedido: ' + prop.instruccion, 'tenue'));
    for (const [tipo, titulo] of GRUPOS) {
      const lote = items.filter(i => i.tipo === tipo);
      if (!lote.length) continue;
      caja.append(el('h4', titulo + ' (' + lote.length + ')'));
      for (const i of lote) {
        const fila = el('label', null, 'ens-item' + (i.confianza === 'dudosa' ? ' dudosa' : '') + ' est-' + (i.estado || 'pendiente'));
        const cb = el('input'); cb.type = 'checkbox';
        const fijo = i.estado === 'aplicado' || i.estado === 'revertido';
        cb.checked = !fijo && i.marcado !== false; cb.disabled = fijo || prop.estado === 'rechazada';
        marcas.set(i.id, cb); cb.addEventListener('change', actualizar);
        const t = el('span');
        t.append(el('strong', (i.estado === 'aplicado' ? '✔ ' : i.estado === 'revertido' ? '↩ ' : '') + i.titulo), el('small', i.detalle || '', 'tenue'));
        if (i.confianza === 'dudosa') t.append(el('small', '⚠ Coincidencia dudosa: revísala antes de aplicar', 'aviso'));
        if (i.error) t.append(el('small', '✘ ' + i.error, 'aviso'));
        const ver = el('button', 'Ver detalles', 'sec ens-ver'); ver.type = 'button';
        const det = el('pre', lineasDetalle(i).join('\n'), 'ens-det'); det.hidden = true;
        ver.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); det.hidden = !det.hidden; ver.textContent = det.hidden ? 'Ver detalles' : 'Ocultar'; });
        t.append(ver, det);
        fila.append(cb, t); caja.append(fila);
      }
    }
    if ((notas || []).length) { caja.append(el('h4', 'Notas de la IA')); notas.forEach(n => caja.append(el('small', '• ' + n, 'tenue'))); }
    const res = el('div', null, 'ens-res'), botones = el('div', null, 'fila');
    const aplicar = el('button', 'Aplicar seleccionados'), descartar = el('button', 'Descartar', 'sec'), deshacer = el('button', 'Deshacer lo aplicado', 'sec');
    for (const b of [aplicar, descartar, deshacer]) b.type = 'button';
    const hayAplicados = items.some(i => i.estado === 'aplicado');
    function actualizar() {
      const n = [...marcas].filter(([, c]) => c.checked && !c.disabled).length;
      aplicar.textContent = 'Aplicar seleccionados (' + n + ')'; aplicar.disabled = n === 0;
    }
    cabecera.append(el('strong', 'Propuesta · ' + ESTADO_PROP[prop.estado] + (prop.modelo ? ' · ' + prop.modelo : '')), el('small', 'Nada cambia hasta que pulses «Aplicar». Se puede deshacer.', 'tenue'));
    if (prop.estado === 'pendiente' || (prop.estado === 'aplicada' && pendientes().length)) botones.append(aplicar);
    if (prop.estado === 'pendiente') botones.append(descartar);
    if (hayAplicados) botones.append(deshacer);
    actualizar();
    const refrescar = (nueva, lineas) => {
      const n = tarjeta(nueva, notas);
      if (lineas?.length) n.append(...lineas);
      caja.replaceWith(n); n.scrollIntoView({ block: 'nearest' });
      recargarEstado(); alCambiar();
    };
    const ejecutar = async (b, fn) => { for (const x of [aplicar, descartar, deshacer]) x.disabled = true; try { await fn(); } catch (e) { res.textContent = '✘ ' + err(e); actualizar(); descartar.disabled = deshacer.disabled = false; } };
    aplicar.addEventListener('click', () => ejecutar(aplicar, async () => {
      const ids = [...marcas].filter(([, c]) => c.checked && !c.disabled).map(([id]) => id);
      const r = await pw.ens.aplicar(pid, prop.id, ids);
      const lineas = r.resultados.map(x => el('small', (x.ok ? '✔ ' : '✘ ') + x.titulo + (x.error ? ': ' + x.error : ''), x.ok ? 'tenue' : 'aviso'));
      const tipos = new Set(r.propuesta.items.filter(i => i.estado === 'aplicado').map(i => i.tipo));
      const enlaces = el('div', null, 'fila');
      if ([...tipos].some(t => t.startsWith('partida'))) {
        const b = el('button', '🔢 Ordenar y renumerar partidas', 'sec'); b.type = 'button'; b.addEventListener('click', () => abrirWeb('/programa/ordenar?proyecto=' + pid, '/programa')); enlaces.append(b);
        // Guardar la estructura aplicada como plantilla reutilizable (sin salir del Ensamblador)
        const cajaPl = el('div', null, 'fila');
        const nombrePl = el('input'); nombrePl.type = 'text'; nombrePl.maxLength = 150; nombrePl.placeholder = 'Nombre de la plantilla';
        const proyecto = (proyectos || []).find(p => String(p.id) === String(pid));
        nombrePl.value = ((proyecto && proyecto.nombre) || 'Proyecto') + ' (plantilla)';
        const descPl = el('input'); descPl.type = 'text'; descPl.maxLength = 500; descPl.placeholder = 'Descripción (opcional)';
        descPl.value = 'Plantilla propuesta a partir de «' + ((archivos[0] && archivos[0].nombre) || 'documento') + '».';
        const guardarPl = el('button', '💾 Guardar como plantilla', 'sec'); guardarPl.type = 'button';
        const estadoPl = el('small', '', 'tenue');
        guardarPl.addEventListener('click', async () => {
          guardarPl.disabled = true; estadoPl.textContent = 'Guardando…';
          try {
            const r = await pw.ens.guardarPlantilla(pid, nombrePl.value.trim(), descPl.value.trim(), (archivos[0] && archivos[0].nombre) || '', false);
            estadoPl.textContent = '✔ Plantilla guardada: ' + r.nombre + '. Ya aparece en Plantillas de proyecto.';
          } catch (e) { estadoPl.textContent = '✘ ' + err(e); guardarPl.disabled = false; }
        });
        cajaPl.append(nombrePl, descPl, guardarPl, estadoPl); enlaces.append(cajaPl);
      }
      for (const [ruta, etq] of new Map([...tipos].map(t => IRA[t]))) { const b = el('button', etq, 'sec'); b.type = 'button'; b.addEventListener('click', () => abrirWeb(ruta + '?proyecto=' + pid, ruta)); enlaces.append(b); }
      refrescar(r.propuesta, [...lineas, enlaces]);
    }));
    descartar.addEventListener('click', () => ejecutar(descartar, async () => { await pw.ens.rechazar(pid, prop.id); refrescar({ ...prop, estado: 'rechazada' }, [el('small', 'Propuesta descartada.', 'tenue')]); }));
    deshacer.addEventListener('click', () => ejecutar(deshacer, async () => {
      const r = await pw.ens.revertir(pid, prop.id);
      refrescar(r.propuesta, r.resultados.map(x => el('small', '↩ ' + x.titulo + ': ' + x.mensaje, 'tenue')));
    }));
    caja.append(res, botones);
    return caja;
  }

  async function subirArchivos(lista) {
    if (!pid) { $('#ens-msg').textContent = 'Elige primero un proyecto.'; return; }
    const arch = [...lista];
    if (!arch.length) return;
    $('#ens-msg').textContent = 'Subiendo…';
    try {
      const cargados = [];
      for (const f of arch) cargados.push({ nombre: f.name, datos: new Uint8Array(await f.arrayBuffer()) });
      const r = await pw.ens.subir(pid, ses, cargados);
      ses = r.sesion || ses;
      for (const a of r.archivos) if (!archivos.some(x => x.id === a.id)) archivos.push(a);
      submittals = r.estado?.submittals || submittals; historial = r.estado?.historial || historial;
      pintarArchivos(); pintarChips(); pintarHistorial();
      const lineas = [];
      if (r.archivos.length) {
        const sinTexto = r.archivos.filter(a => a.sin_texto);
        lineas.push(el('div', 'Leí ' + r.archivos.length + ' archivo(s) y los guardé en el Archivero del proyecto. ¿Qué quieres hacer con esa información?'));
        lineas.push(el('small', 'Elige uno o varios botones de abajo, o escríbeme con tus palabras (por ejemplo: «agrega solo los materiales de cableado» o «¿cuántas cámaras pide?»).', 'tenue'));
        sinTexto.forEach(a => lineas.push(el('small', '⚠ «' + a.nombre + '» no tiene texto legible' + (a.aviso ? ': ' + a.aviso : '') + '.', 'aviso')));
      }
      r.errores.forEach(e => lineas.push(el('small', '✘ ' + e.nombre + ': ' + e.error, 'aviso')));
      if (lineas.length) asistente(lineas);
      $('#ens-msg').textContent = '';
    } catch (e) { $('#ens-msg').textContent = '✘ ' + err(e); }
  }

  async function enviar() {
    if (ocupado) return;
    const texto = $('#ens-texto').value.trim(), msg = $('#ens-msg');
    if (!archivos.length) { msg.textContent = 'Sube primero un documento.'; return; }
    const prov = $('#ens-conexion').value;
    if (!prov) { msg.textContent = 'Primero guarda una conexión en Ajustes → Configuración de IA.'; return; }
    if (!texto && !modos.size) { asistente('Elige al menos una opción de los botones (o escribe qué necesitas) y vuelve a enviar.'); return; }
    if (modos.has('submittal_existente') && !$('#ens-sub').value) { asistente('No hay un submittal existente al que agregar: crea uno o elige «Submittal nuevo».'); return; }
    const lista = [...modos].map(k => MODOS.find(m => m[0] === k)[1]);
    burbuja('yo', (lista.length ? lista.join(' · ') : '✨ Lo que encuentres') + (texto ? '\n' + texto : ''));
    $('#ens-texto').value = '';
    ocupado = true; $('#ens-enviar').disabled = true; $('#ens-cancelar').hidden = false; $('#ens-barra').hidden = false; $('#ens-barra').removeAttribute('value');
    try {
      const r = await pw.ens.mensaje({ pid, ses, texto, modos: [...modos], submittalId: modos.has('submittal_existente') ? $('#ens-sub').value : 0, provId: prov, revisar: $('#ens-revisar').checked });
      const extra = [];
      if (r.fallidos) extra.push(el('small', '⚠ ' + r.fallidos + ' fragmento(s) no se pudieron analizar; revisa que la propuesta esté completa.', 'aviso'));
      if (r.aviso) extra.push(el('small', '⚠ ' + r.aviso, 'aviso'));
      if (r.tipo === 'respuesta') asistente([el('div', r.respuesta), ...extra]);
      else if (r.tipo === 'nada') asistente([el('div', r.mensaje), ...(r.notas || []).map(n => el('small', '• ' + n, 'tenue')), ...extra]);
      else { asistente([el('div', 'Esto encontré. Revisa la lista, quita lo que no quieras y pulsa «Aplicar».'), ...extra]); asistente(tarjeta(r.propuesta, r.notas)); modos.clear(); pintarChips(); await recargarEstado(); alCambiar(); }
      msg.textContent = '';
    } catch (e) { msg.textContent = '✘ ' + err(e); asistente('No pude completar el análisis: ' + err(e)); }
    ocupado = false; $('#ens-enviar').disabled = false; $('#ens-cancelar').hidden = true; $('#ens-barra').hidden = true;
  }

  async function llenarSelectores() {
    cfg = await pw.config();
    const sel = $('#ens-conexion'), previa = sel.value;
    sel.replaceChildren(...(cfg?.proveedores || []).map(p => { const o = el('option', p.nombre + (p.modelo ? ' · ' + p.modelo : '')); o.value = p.id; return o; }));
    const pref = cfg?.porTarea?.avanzada;
    sel.value = previa && [...sel.options].some(o => o.value === previa) ? previa : ((cfg?.proveedores || []).some(p => p.id === pref) ? pref : (cfg?.activa || ''));
    if (!proyectos) { try { proyectos = await pw.proyectosPrograma(); } catch (e) { $('#ens-msg').textContent = '✘ ' + err(e); proyectos = null; } }
    const sp = $('#ens-proyecto'), previo = sp.value;
    sp.replaceChildren(...(proyectos || []).map(p => { const o = el('option', p.codigo + ' · ' + p.nombre); o.value = p.id; return o; }));
    const deseado = (typeof proyectoIA === 'function' && proyectoIA()) || previo;
    if (deseado && [...sp.options].some(o => String(o.value) === String(deseado))) sp.value = deseado;
    return sp.value;
  }

  /** Se llama al abrir la sección */
  async function pintar() {
    if (!listo) iniciar();
    const nuevo = await llenarSelectores();
    if (String(nuevo) !== String(pid)) {   // otro proyecto: conversación nueva (los archivos pertenecen a un proyecto)
      pid = nuevo || null; ses = 0; archivos = []; modos.clear();
      $('#ens-chat').replaceChildren(); pintarArchivos();
      asistente('Hola. Sube aquí un PDF, una presentación (.pptx), un documento de Word/OpenDocument o un texto. Lo leo y te pregunto qué quieres hacer: agregar partidas, modificar las que ya existen, agregar materiales o armar un submittal.');
      await recargarEstado();
    } else pintarChips();
  }

  function iniciar() {
    listo = true;
    const zona = $('#ens-drop'), input = $('#ens-input');
    $('#ens-elegir').addEventListener('click', () => input.click());
    input.addEventListener('change', async () => { const l = [...input.files]; input.value = ''; await subirArchivos(l); });
    for (const ev of ['dragenter', 'dragover']) zona.addEventListener(ev, e => { e.preventDefault(); zona.classList.add('sobre'); });
    for (const ev of ['dragleave', 'drop']) zona.addEventListener(ev, e => { e.preventDefault(); zona.classList.remove('sobre'); });
    zona.addEventListener('drop', e => subirArchivos(e.dataTransfer.files));
    $('#ens-proyecto').addEventListener('change', () => { if (typeof fijarProyectoMenu === 'function') fijarProyectoMenu($('#ens-proyecto').value); pintar(); });
    $('#ens-form').addEventListener('submit', e => { e.preventDefault(); enviar(); });
    $('#ens-texto').addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); } });
    $('#ens-cancelar').addEventListener('click', () => { pw.ens.cancelar(); $('#ens-msg').textContent = 'Cancelando al terminar el fragmento en curso…'; });
    $('#ens-nueva').addEventListener('click', () => { pid = null; pintar(); });
    pw.ens.alAvance(p => { if (p.texto) $('#ens-msg').textContent = p.texto; const b = $('#ens-barra'); if (p.total) { b.max = p.total; b.value = p.hecho || 0; } });
  }

  return { pintar, alAbrirWeb: fn => { abrirWeb = fn; }, alCambiar: fn => { alCambiar = fn; } };
})();
