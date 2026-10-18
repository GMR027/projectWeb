<?php
use Model\VplanTabla;
$fd = fn($f) => $f ? formatear_fecha($f) : '';
$colsTabla = 6 + 2 * ((int)$rec1 + (int)$rec2);   // SEQ, TASK, START, FINISH + recovery + %, CONSTRAINS
$logoC = $proyecto->contratista_logo;
$logoE = $proyecto->logoEmpresa();
$planoImg = $lamina->marcas_png ?: $lamina->plano;
$lkW = $lkH = 0.0;
$enlaces = $lamina->marcas_png ? \Classes\EnlacesLamina::de($lamina->marcas_json, $lkW, $lkH) : [];
$editarFila = fn($t, $campo) => $puedeEditar
    ? '<input type="date" class="vp-rec" data-actividad="' . (int)$t['actividad']->id . '" data-campo="' . $campo . '" value="' . s($t[$campo]) . '">'
    : s($fd($t[$campo]));
?>
<div class="page-header no-print">
    <div>
        <h2><i class="fa-solid fa-map-location-dot"></i> <?php echo s($lamina->titulo); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo $partida ? 'Partida ' . s($partida->codigo . ' · ' . $partida->nombre) : 'Sin partida'; ?></p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?>
            <a href="/vplan/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>" class="btn btn-ghost">Editar lámina</a>
        <?php endif; ?>
        <a href="/vplan?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Láminas</a>
    </div>
</div>

<?php if ($puedeEditar && $desactualizada): ?>
<div class="alerta alerta-error no-print">El avance del programa cambió desde que se dibujaron las marcas: el plano está desactualizado.
    <a href="/vplan/editor?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>&auto=1" class="btn btn-sm btn-primary" style="margin-left:1rem">Actualizar plano</a></div>
<?php endif; ?>
<?php if ($puedeEditar): ?>
<div class="card vp-barra no-print">
    <form method="POST" action="/vplan/lamina/tabla" class="vp-agregar-tabla">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <input type="hidden" name="lamina" value="<?php echo s($lamina->id); ?>">
        <input type="hidden" name="accion" value="agregar">
        <label for="tabla_id" class="text-sm">Agregar tabla adicional:</label>
        <select id="tabla_id" name="tabla_id" required>
            <option value="">— Elige de la lista —</option>
            <?php foreach ($catalogo as $c): ?><option value="<?php echo s($c->id); ?>"><?php echo s($c->etiqueta() . ' (' . (VplanTabla::TIPOS[$c->tipo] ?? $c->tipo) . ')'); ?></option><?php endforeach; ?>
        </select>
        <label class="text-sm" title="Crea un duplicado con los mismos renglones; los cambios no afectan a las demás láminas"><input type="checkbox" name="copia" value="1"> Como copia independiente</label>
        <button type="submit" class="btn btn-primary btn-sm">Agregar</button>
        <a href="/vplan/tablas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost btn-sm">+ Crear tabla nueva</a>
    </form>
    <div class="vp-recovery-btns">
        <?php foreach ([['agregar', '1', !$rec1, 'Agregar Recovery', 'btn-primary'], ['agregar', '2', !$rec2, 'Agregar Recovery 02', 'btn-primary'],
                        ['quitar', '1', $rec1, 'Quitar Recovery', 'btn-danger'], ['quitar', '2', $rec2, 'Quitar Recovery 02', 'btn-danger']] as [$acc, $cual, $mostrar, $texto, $clase]): if (!$mostrar) continue; ?>
            <form method="POST" action="/vplan/lamina/recovery" style="display:inline"<?php echo $acc === 'quitar' ? ' data-confirm="¿Quitar ' . s($texto === 'Quitar Recovery 02' ? 'Recovery 02' : 'Recovery') . '?|Se oculta la columna de la lámina; las fechas capturadas se conservan.|Sí, quitar"' : ''; ?>>
                <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                <input type="hidden" name="lamina" value="<?php echo s($lamina->id); ?>">
                <input type="hidden" name="accion" value="<?php echo $acc; ?>">
                <input type="hidden" name="cual" value="<?php echo $cual; ?>">
                <button type="submit" class="btn btn-sm <?php echo $clase; ?>"<?php echo $acc === 'quitar' ? ' ' : ''; ?>><?php echo s($texto); ?></button>
            </form>
        <?php endforeach; ?>
        <span class="text-muted text-sm">Las fechas capturadas se conservan aunque quites la columna.</span>
    </div>
</div>
<?php endif; ?>

<div class="vp-hoja" id="vp-hoja">
<button type="button" class="vp-cerrar-ampliado no-print" id="vp-cerrar-ampliado" aria-label="Cerrar vista ampliada" title="Cerrar"><i class="fa-solid fa-xmark"></i></button>
<div class="vp-lamina">
    <div class="vp-encabezado">
        <div class="vp-logo"><?php if ($logoC): ?><img src="/build/img/logos/<?php echo s($logoC); ?>" alt=""><?php endif; ?></div>
        <div class="vp-titulo"><span><?php echo s($proyecto->codigo ?: $proyecto->nombre); ?></span><strong><?php echo s(mb_strtoupper($lamina->titulo)); ?></strong></div>
        <div class="vp-logo vp-logo-der"><?php if ($logoE): ?><img src="/build/img/logos/<?php echo s($logoE); ?>" alt=""><?php endif; ?></div>
    </div>

    <div class="vp-cuerpo<?php echo ($rec1 && $rec2) ? ' vp-con-rec2' : ($rec1 || $rec2 ? ' vp-con-rec' : ''); ?>">
        <div class="vp-izq">
            <div class="vp-plano">
                <?php if ($puedeEditar && $lamina->plano): ?>
                    <div class="vp-plano-esq no-print"><a href="/vplan/editor?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>" class="vp-plano-editar" title="Abrir el editor de marcas de este plano"><i class="fa-solid fa-pen-ruler"></i> Editar marcas</a></div>
                <?php endif; ?>
                <?php if ($planoImg): ?><?php if ($enlaces): ?><div class="vp-lk-wrap"><?php endif; ?><img src="/uploads/vplan/<?php echo s($planoImg); ?>" alt="Plano"><?php if ($enlaces): echo \Classes\EnlacesLamina::html($enlaces, $lkW, $lkH); ?></div><?php endif; ?><?php else: ?><p class="text-muted">Sin imagen de plano. Súbela en «Editar lámina».</p><?php endif; ?>
            </div>
            <div class="vp-simbologia-fila">
                <div class="vp-simb-img"><?php if ($lamina->simbologia): ?><img src="/uploads/vplan/<?php echo s($lamina->simbologia); ?>" alt="Simbología"><?php endif; ?></div>
                <?php if ($simbSchedule = $lamina->imagenSimbologiaSchedule()): ?>
                <img class="vp-schedule-img" src="/uploads/vplan/<?php echo s($simbSchedule); ?>" alt="Simbología del schedule">
                <?php else: ?>
                <table class="vp-schedule">
                    <thead><tr><th colspan="2">SYMBOLOGY FOR SCHEDULE</th></tr></thead>
                    <tbody>
                        <tr><td class="vp-verde"></td><td>COMPLETE</td></tr>
                        <tr><td class="vp-amarillo"></td><td>IN PROGRESS</td></tr>
                        <tr><td class="vp-rojo"></td><td>ON HOLD</td></tr>
                        <tr><td class="vp-rombo-celda"><span class="vp-rombo vp-rombo-sm"></span></td><td>CONSTRAIN</td></tr>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="vp-der">
            <div class="vp-scroll"><table class="vp-tabla vp-programa" style="--vp-c:<?php echo s($colorTabla[0]); ?>;--vp-t:<?php echo s($colorTabla[1]); ?>">
                <thead>
                    <tr><th colspan="<?php echo $colsTabla; ?>" class="vp-adic-titulo"><?php echo $partida ? s(mb_strtoupper($partida->nombre)) : 'SIN PARTIDA'; ?></th></tr>
                    <tr>
                        <?php $rs = ($rec1 || $rec2) ? 2 : 1; ?><th rowspan="<?php echo $rs; ?>"># SEQ</th><th rowspan="<?php echo $rs; ?>" class="vp-task">TASK</th><th rowspan="<?php echo $rs; ?>">START</th><th rowspan="<?php echo $rs; ?>">FINISH</th>
                        <?php if ($rec1): ?><th colspan="2">RECOVERY</th><?php endif; ?><?php if ($rec2): ?><th colspan="2">RECOVERY 02</th><?php endif; ?>
                        <th rowspan="<?php echo $rs; ?>">%</th><th rowspan="<?php echo $rs; ?>">CONSTRAINS</th>
                    </tr>
                    <?php if ($rec1 || $rec2): ?><tr><?php if ($rec1): ?><th>START</th><th>FINISH</th><?php endif; ?><?php if ($rec2): ?><th>START</th><th>FINISH</th><?php endif; ?></tr><?php endif; ?>
                </thead>
                <tbody>
                <?php foreach ($tareas as $t): $a = $t['actividad']; $p = (int)$a->progreso; ?>
                    <tr>
                        <td class="c"><?php echo (int)$t['seq']; ?></td>
                        <td><?php echo s($a->nombre); ?></td>
                        <td class="c"><?php echo $puedeEditar ? '<input type="date" class="vp-rec vp-fecha-act" data-actividad="' . (int)$a->id . '" data-tipo="inicio" value="' . s($a->fecha_inicio) . '" title="Misma fecha que el programa de obra">' : s($fd($a->fecha_inicio)); ?></td>
                        <td class="c"><?php echo $puedeEditar ? '<input type="date" class="vp-rec vp-fecha-act" data-actividad="' . (int)$a->id . '" data-tipo="fin" value="' . s($a->fecha_fin) . '" title="Misma fecha que el programa de obra">' : s($fd($a->fecha_fin)); ?></td>
                        <?php if ($rec1): ?>
                            <td class="c"><?php echo $editarFila($t, 'r1_inicio'); ?></td>
                            <td class="c"><?php echo $editarFila($t, 'r1_fin'); ?></td>
                        <?php endif; ?>
                        <?php if ($rec2): ?>
                            <td class="c vp-rojo-txt"><?php echo $editarFila($t, 'r2_inicio'); ?></td>
                            <td class="c vp-rojo-txt"><?php echo $editarFila($t, 'r2_fin'); ?></td>
                        <?php endif; ?>
                        <td class="c vp-pct-act <?php echo $p >= 100 ? 'vp-verde' : ($p > 0 ? 'vp-amarillo' : ''); ?>"><?php echo $puedeEditar ? '<input type="number" min="0" max="100" step="1" class="vp-rec vp-progreso-act" data-actividad="' . (int)$a->id . '" value="' . $p . '" title="Mismo avance que el programa de obra">' : $p . '%'; ?></td>
                        <td class="c">
                            <?php if ($t['restricciones']): foreach ($t['restricciones'] as $n): ?><span class="vp-rombo"><b><?php echo (int)$n; ?></b></span><?php endforeach;
                            elseif ($t['estado'] === 'completo'): ?>COMPLETE<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tareas): ?><tr><td colspan="<?php echo $colsTabla; ?>" class="text-muted">Selecciona una partida con actividades en «Editar lámina».</td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <?php if ($filtrada): ?><p class="text-sm text-muted no-print">Esta lámina muestra <?php echo count($tareas); ?> actividades de la partida (<?php echo s($partida->nombre); ?>). <?php if ($puedeEditar): ?><a href="/vplan/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>">Cambiar selección</a><?php endif; ?></p><?php endif; ?>
            <?php foreach ($tablas as $bloque): $t = $bloque['tabla']; ?>
                <div class="vp-bloque">
                    <?php if ($puedeEditar): ?>
                        <div class="vp-bloque-acciones no-print">
                            <?php foreach ([['subir', '↑'], ['bajar', '↓'], ['quitar', '✕']] as [$acc, $ico]): ?>
                                <form method="POST" action="/vplan/lamina/tabla" style="display:inline"<?php echo $acc === 'quitar' ? ' data-confirm="¿Quitar tabla de la lámina?|La tabla sigue en el catálogo; solo se quita de esta lámina.|Sí, quitar"' : ''; ?>>
                                    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                                    <input type="hidden" name="lamina" value="<?php echo s($lamina->id); ?>">
                                    <input type="hidden" name="tabla_id" value="<?php echo s($t->id); ?>">
                                    <input type="hidden" name="accion" value="<?php echo $acc; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $acc === 'quitar' ? 'btn-danger' : 'btn-ghost'; ?>" title="<?php echo $acc; ?>"><?php echo $ico; ?></button>
                                </form>
                            <?php endforeach; ?>
                            <a href="/vplan/tablas/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($t->id); ?>" class="btn btn-sm btn-ghost">Editar datos</a>
                        </div>
                    <?php endif; ?>
                    <?php include __DIR__ . '/_tabla-adicional.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="vp-pie">
        <div><?php echo s($lamina->supervisor ? 'SUPERVISOR: ' . $lamina->supervisor : ''); ?></div>
        <div>UPDATED: <?php $meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE']; echo $meses[(int)date('n')] . ' | ' . date('d') . ' | ' . date('Y'); ?></div>
    </div>
</div>
</div><!-- /vp-hoja -->

<section class="card vp-acciones no-print" id="vp-acciones">
    <h3>Acciones</h3>
    <div class="vp-acciones-menu">
        <?php if ($puedeEditar && $lamina->plano): ?><a href="/vplan/editor?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>" class="btn btn-primary"><i class="fa-solid fa-pen-ruler"></i> Editar marcas</a><?php endif; ?>
        <button type="button" class="btn btn-ghost" id="vp-ampliar"><i class="fa-solid fa-expand"></i> Ampliar</button>
        <?php if ($puedeEditar): ?><button type="button" class="btn btn-primary" id="vp-nota-nueva"><i class="fa-solid fa-note-sticky"></i> Crear nota</button><?php endif; ?>
    </div>
    <div class="vp-notas">
        <h4>Notas</h4>
        <p class="text-muted" id="vp-notas-vacio" hidden>Aún no hay notas en esta lámina.</p>
        <ul class="vp-notas-lista" id="vp-notas-lista"></ul>
    </div>
</section>

<?php if ($puedeEditar): ?>
<div class="modal-overlay" id="vp-nota-modal" aria-hidden="true">
    <form class="modal-card vp-nota-card" id="vp-nota-form" role="dialog" aria-modal="true" aria-labelledby="vp-nota-titulo">
        <h3 class="modal-titulo" id="vp-nota-titulo">Crear nota</h3>
        <p class="text-muted text-sm vp-nota-fecha">Fecha: <strong id="vp-nota-fecha"></strong> <span id="vp-nota-auto">(se pone automáticamente)</span></p>
        <div class="campo">
            <label for="vp-nota-descripcion">Descripción *</label>
            <textarea id="vp-nota-descripcion" rows="5" maxlength="<?php echo (int)\Model\VplanNota::MAX_LARGO; ?>" required></textarea>
        </div>
        <p class="msr-error-txt text-sm" id="vp-nota-error" hidden></p>
        <div class="modal-acciones">
            <button type="button" class="btn btn-ghost" id="vp-nota-cancelar"><i class="fa-solid fa-xmark"></i> Cancelar</button>
            <button type="submit" class="btn btn-primary" id="vp-nota-guardar"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
(function () {
    const lamina = <?php echo (int)$lamina->id; ?>;
    const puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
    const notas = <?php echo json_encode($notas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());
    const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---- Ampliar: la lámina a pantalla completa, con botón de cerrar abajo a la izquierda ----
    const hoja = document.getElementById('vp-hoja');
    const modalConfirm = document.getElementById('modal-confirm');   // se mueve dentro de la lámina para que se vea a pantalla completa
    const abrir = () => {
        hoja.classList.add('vp-ampliado');
        if (modalConfirm) hoja.appendChild(modalConfirm);
        document.body.classList.add('vp-sin-scroll');
        if (hoja.requestFullscreen) hoja.requestFullscreen().catch(() => {});   // si el navegador no lo permite, queda a pantalla completa de la página
    };
    const cerrar = () => {
        hoja.classList.remove('vp-ampliado');
        if (modalConfirm && modalConfirm.parentNode === hoja) document.body.appendChild(modalConfirm);
        document.body.classList.remove('vp-sin-scroll');
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
    };
    document.getElementById('vp-ampliar').addEventListener('click', abrir);
    document.getElementById('vp-cerrar-ampliado').addEventListener('click', cerrar);
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) cerrar(); });   // Esc del navegador
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && hoja.classList.contains('vp-ampliado')) cerrar(); });

    // ---- Notas ----
    const lista = document.getElementById('vp-notas-lista'), vacio = document.getElementById('vp-notas-vacio');
    const pintar = () => {
        vacio.hidden = notas.length > 0;
        lista.innerHTML = notas.map(n =>
            '<li class="vp-nota" data-id="' + n.id + '">' +
            '<div class="vp-nota-cab"><span class="vp-nota-fecha-txt"><i class="fa-regular fa-calendar"></i> ' + esc(n.fecha) + '</span>' +
            (puedeEditar ? '<span class="vp-nota-acc"><button type="button" class="btn btn-sm btn-ghost" data-nota="editar"><i class="fa-solid fa-pen"></i> Editar</button>' +
                '<button type="button" class="btn btn-sm btn-danger" data-nota="borrar"><i class="fa-solid fa-trash-can"></i> Borrar</button></span>' : '') +
            '</div><p class="vp-nota-texto">' + esc(n.descripcion) + '</p></li>'
        ).join('');
    };
    pintar();
    if (!puedeEditar) return;

    const modal = document.getElementById('vp-nota-modal'), form = document.getElementById('vp-nota-form');
    const area = document.getElementById('vp-nota-descripcion'), err = document.getElementById('vp-nota-error');
    const btnGuardar = document.getElementById('vp-nota-guardar');
    let editando = null;
    const abrirModal = nota => {
        editando = nota || null;
        document.getElementById('vp-nota-titulo').textContent = nota ? 'Editar nota' : 'Crear nota';
        const hoy = new Date(), p = n => String(n).padStart(2, '0');
        document.getElementById('vp-nota-fecha').textContent = nota ? nota.fecha : p(hoy.getDate()) + '/' + p(hoy.getMonth() + 1) + '/' + hoy.getFullYear();
        document.getElementById('vp-nota-auto').hidden = !!nota;
        area.value = nota ? nota.descripcion : '';
        err.hidden = true;
        modal.classList.add('abierto'); modal.setAttribute('aria-hidden', 'false');
        area.focus();
    };
    const cerrarModal = () => { modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true'); editando = null; };
    document.getElementById('vp-nota-nueva').addEventListener('click', () => abrirModal());
    document.getElementById('vp-nota-cancelar').addEventListener('click', cerrarModal);
    modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('abierto')) cerrarModal(); });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!area.value.trim()) { err.textContent = 'La descripción es obligatoria'; err.hidden = false; return; }
        btnGuardar.disabled = true;
        try {
            const datos = { lamina, descripcion: area.value };
            if (editando) datos.id = editando.id;
            const d = await post('/api/vplan/nota/guardar', datos);
            if (!d.success) { err.textContent = d.error || 'No se pudo guardar'; err.hidden = false; return; }
            if (editando) notas[notas.findIndex(n => n.id === editando.id)] = d.nota; else notas.unshift(d.nota);
            pintar(); cerrarModal();
        } catch (_) { err.textContent = 'Error de conexión'; err.hidden = false; }
        finally { btnGuardar.disabled = false; }
    });

    lista.addEventListener('click', async e => {
        const b = e.target.closest('[data-nota]');
        if (!b) return;
        const nota = notas.find(n => n.id === +b.closest('.vp-nota').dataset.id);
        if (!nota) return;
        if (b.dataset.nota === 'editar') return abrirModal(nota);
        const ok = await window.confirmarModal({ titulo: '¿Borrar esta nota?', mensaje: 'Esta acción no se puede deshacer.', aceptar: 'Sí, borrar', iconoBoton: 'fa-trash-can' });
        if (!ok) return;
        const d = await post('/api/vplan/nota/eliminar', { lamina, id: nota.id });
        if (d.success) { notas.splice(notas.indexOf(nota), 1); pintar(); }
    });
})();
</script>

<?php if ($puedeEditar): ?>
<script>
(function () {
    const lamina = <?php echo (int)$lamina->id; ?>;
    const post = (url, datos) => fetch(url, { method: 'POST', body: new URLSearchParams(datos) }).then(r => r.json());

    // Recovery de las tareas del programa
    document.querySelectorAll('.vp-rec:not(.vp-fecha-act):not(.vp-progreso-act)').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/vplan/recovery', { lamina, actividad: inp.dataset.actividad, campo: inp.dataset.campo, valor: inp.value });
            if (!d.success) inp.classList.add('msr-error');
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // Inicio y fin de la tarea: son los mismos datos del programa de obra (se guardan ahí y se reflejan en todos los formatos)
    document.querySelectorAll('.vp-fecha-act').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        if (!inp.value) { inp.classList.add('msr-error'); return; }
        try {
            const d = await post('/api/actividad/actualizar-fecha', { actividad_id: inp.dataset.actividad, tipo: inp.dataset.tipo, fecha: inp.value });
            if (!d.success) { inp.classList.add('msr-error'); return; }
            const fila = inp.closest('tr');
            fila.querySelector('[data-tipo=inicio]').value = d.fecha_inicio;
            fila.querySelector('[data-tipo=fin]').value = d.fecha_fin;
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // % de avance de la tarea: mismo dato del programa de obra
    document.querySelectorAll('.vp-progreso-act').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/actividad/actualizar-progreso', { actividad_id: inp.dataset.actividad, progreso: inp.value });
            if (!d.success) { inp.classList.add('msr-error'); return; }
            inp.value = d.progreso;
            const td = inp.closest('td');
            td.classList.toggle('vp-verde', d.progreso >= 100);
            td.classList.toggle('vp-amarillo', d.progreso > 0 && d.progreso < 100);
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // Tablas adicionales: editar en línea, agregar y quitar renglones
    const colorPct = td => {
        const v = td.querySelector('input').value;
        td.classList.toggle('vp-verde', v !== '' && +v >= 100);
        td.classList.toggle('vp-amarillo', v !== '' && +v > 0 && +v < 100);
    };
    const renumerar = tabla => {
        const base = +tabla.dataset.base || 0;
        [...tabla.tBodies[0].rows].forEach((tr, i) => {
            const n = tr.querySelector('.vp-n');
            if (!n) return;
            const b = n.querySelector('b');
            const manuales = [...tabla.tBodies[0].rows].filter(r => r.dataset.indice !== undefined);
            if (b) b.textContent = base + manuales.indexOf(tr) + 1; else n.textContent = i + 1;
        });
    };
    document.addEventListener('change', async e => {
        const inp = e.target.closest('.vp-in');
        if (!inp) return;
        const tr = inp.closest('tr'), tabla = inp.closest('table');
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/vplan/fila', { tabla: tabla.dataset.tabla, accion: 'set', indice: tr.dataset.indice, campo: inp.dataset.campo, valor: inp.value });
            if (!d.success) { inp.classList.add('msr-error'); return; }
            if (inp.dataset.campo === 'pct') { inp.value = d.valor; colorPct(inp.closest('td')); }
            if (inp.dataset.campo === 'estado') tr.className = tr.className.replace(/vp-est-\w+/, 'vp-est-' + inp.value);
        } catch (_) { inp.classList.add('msr-error'); }
    });
    document.addEventListener('click', async e => {
        const agregar = e.target.closest('.vp-agregar-dato');
        if (agregar) {
            const tabla = document.querySelector('table.vp-adic[data-tabla="' + agregar.dataset.tabla + '"]');
            agregar.disabled = true;
            try {
                const d = await post('/api/vplan/fila', { tabla: tabla.dataset.tabla, accion: 'add', base: tabla.dataset.base });
                if (d.success) {
                    tabla.tBodies[0].insertAdjacentHTML('beforeend', d.html);
                    renumerar(tabla);
                    const nuevo = tabla.tBodies[0].lastElementChild.querySelector('input:not([type=date]), input');
                    if (nuevo) nuevo.focus();
                }
            } finally { agregar.disabled = false; }
            return;
        }
        const del = e.target.closest('.vp-del');
        if (del) {
            const tr = del.closest('tr'), tabla = del.closest('table');
            const ok = await window.confirmarModal({ titulo: '¿Quitar este dato?', mensaje: 'Se quitará este renglón de la tabla. Esta acción no se puede deshacer.', aceptar: 'Sí, quitar', iconoBoton: 'fa-trash-can' });
            if (!ok) return;
            const d = await post('/api/vplan/fila', { tabla: tabla.dataset.tabla, accion: 'del', indice: tr.dataset.indice });
            if (d.success) {
                // los renglones posteriores bajan un índice
                [...tabla.tBodies[0].rows].forEach(r => { if (r.dataset.indice !== undefined && +r.dataset.indice > +tr.dataset.indice) r.dataset.indice = +r.dataset.indice - 1; });
                tr.remove(); renumerar(tabla);
            }
        }
    });
    document.querySelectorAll('.vp-pct').forEach(colorPct);

    // Los textos largos se ajustan a su contenido (sin cortarse)
    const crecer = el => { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; };
    const crecerTodos = () => document.querySelectorAll('.vp-ta').forEach(crecer);
    document.addEventListener('input', e => { if (e.target.classList && e.target.classList.contains('vp-ta')) crecer(e.target); });
    new MutationObserver(crecerTodos).observe(document.querySelector('.vp-lamina'), { childList: true, subtree: true });
    window.addEventListener('load', crecerTodos); crecerTodos();
})();
</script>
<?php endif; ?>
