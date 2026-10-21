<?php
use Model\VplanTabla;
$fd = fn($f) => $f ? formatear_fecha($f) : '';
$modoDiseno = $puedeEditar && !empty($_GET['diseno']);
$q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$lamina->id;
$volver = $paquete ? '/vplan/paquete?proyecto=' . (int)$proyecto->id . '&id=' . (int)$paquete->id : '/vplan?proyecto=' . (int)$proyecto->id;
$conNav = count($navLaminas) > 1 || ($paquete && $puedeEditar);
?>
<div class="page-header no-print">
    <div>
        <h2><i class="fa-solid fa-map-location-dot"></i> <?php echo s($lamina->titulo); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $paquete ? ' · ' . s($paquete->nombre) : ''; ?> · Hoja <?php echo (int)$lamina->ancho_mm; ?> × <?php echo (int)$lamina->alto_mm; ?> mm</p>
    </div>
    <div class="vp-herr">
        <?php if ($conNav && !$modoDiseno): ?><button type="button" class="btn btn-ghost" data-vp-nav-toggle aria-pressed="true" title="Mostrar u ocultar las láminas para tener más espacio"><i class="fa-solid fa-table-columns"></i> Láminas</button><?php endif; ?>
        <?php if ($puedeEditar && !$modoDiseno): ?><a href="/vplan/ver?<?php echo $q; ?>&diseno=1" class="btn btn-primary"><i class="fa-solid fa-vector-square"></i> Modo diseño</a><?php endif; ?>
        <?php if ($puedeEditar && $lamina->plano && !$modoDiseno): ?><a href="/vplan/editor?<?php echo $q; ?>" class="btn btn-ghost"><i class="fa-solid fa-pen-ruler"></i> Editar marcas</a><?php endif; ?>
        <?php if ($puedeEditar): ?><a href="/vplan/editar?<?php echo $q; ?>" class="btn btn-ghost"><i class="fa-solid fa-sliders"></i> Datos de la lámina</a><?php endif; ?>
        <button type="button" class="btn btn-ghost" id="vp-ampliar"><i class="fa-solid fa-expand"></i> Ampliar</button>
        <?php if ($puedeEditar): ?><button type="button" class="btn btn-ghost" id="vp-nota-nueva"><i class="fa-solid fa-note-sticky"></i> Crear nota</button><?php endif; ?>
        <a href="<?php echo $volver; ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> <?php echo $paquete ? s($paquete->nombre) : 'Láminas'; ?></a>
    </div>
</div>

<?php if (!(int)$lamina->visible): ?><div class="alerta alerta-error no-print">Esta lámina está <strong>oculta</strong>: no aparece en las listas de los visitantes ni en el PDF. Muéstrala desde el gestor del vPlan.</div><?php endif; ?>
<?php if ($puedeEditar && $desactualizada): ?>
<div class="alerta alerta-error no-print">El avance del programa cambió desde que se dibujaron las marcas: el plano está desactualizado.
    <a href="/vplan/editor?<?php echo $q; ?>&auto=1" class="btn btn-sm btn-primary" style="margin-left:1rem">Actualizar plano</a></div>
<?php endif; ?>

<?php if ($modoDiseno) include __DIR__ . '/_diseno-barra.php'; ?>
<div class="vp-shell<?php echo $conNav ? ' vp-con-nav' : ''; ?><?php echo $modoDiseno ? ' vp-shell-diseno' : ''; ?>" id="vp-shell">
<?php if ($conNav) include __DIR__ . '/_nav-laminas.php'; ?>
<div class="vp-dis-wrap<?php echo $modoDiseno ? ' vp-dis-on' : ''; ?>">
<?php if ($modoDiseno): ?>
<div class="vp-reglas" id="vp-reglas">
    <div class="vp-regla-esq"></div>
    <div class="vp-regla-celda vp-regla-celda-x"><canvas class="vp-regla" id="vp-regla-x" title="Arrastra hacia la hoja para crear una guía horizontal"></canvas></div>
    <div class="vp-regla-celda vp-regla-celda-y"><canvas class="vp-regla" id="vp-regla-y" title="Arrastra hacia la hoja para crear una guía vertical"></canvas></div>
    <div class="vp-reglas-hoja">
<?php endif; ?>
<div class="vp-hoja" id="vp-hoja">
<button type="button" class="vp-cerrar-ampliado no-print" id="vp-cerrar-ampliado" aria-label="Cerrar vista ampliada" title="Cerrar"><i class="fa-solid fa-xmark"></i></button>
<?php include __DIR__ . '/_hoja.php'; ?>
</div><!-- /vp-hoja -->
<?php if ($modoDiseno): ?>
    </div>
</div>
<?php endif; ?>
<?php if ($modoDiseno) include __DIR__ . '/_diseno-panel.php'; ?>
</div>
</div><!-- /vp-shell -->

<section class="card vp-acciones no-print" id="vp-acciones">
    <div class="vp-notas">
        <h4><i class="fa-solid fa-note-sticky"></i> Notas</h4>
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
    document.querySelectorAll('.vp-rec:not(.vp-fecha-act):not(.vp-progreso-act):not(.vp-col-texto)').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/vplan/recovery', { lamina, actividad: inp.dataset.actividad, n: inp.dataset.n, campo: inp.dataset.campo, valor: inp.value });
            if (!d.success) inp.classList.add('msr-error');
        } catch (_) { inp.classList.add('msr-error'); }
    }));

    // Columnas de texto propias de las tablas de tareas
    document.querySelectorAll('.vp-col-texto').forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const d = await post('/api/vplan/columna-texto', { lamina, actividad: inp.dataset.actividad, col: inp.dataset.col, valor: inp.value });
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
            const d = await post('/api/vplan/fila', { tabla: tabla.dataset.tabla, accion: 'set', indice: tr.dataset.indice, campo: inp.dataset.campo, valor: inp.type === 'checkbox' ? (inp.checked ? '1' : '') : inp.value });
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
    new MutationObserver(crecerTodos).observe(document.querySelector('.vp-sheet'), { childList: true, subtree: true });
    window.addEventListener('load', crecerTodos); crecerTodos();
})();
</script>
<?php endif; ?>

<script src="/vendor/vplan-ajuste.js?v=<?php echo filemtime(__DIR__ . "/../../public/vendor/vplan-ajuste.js"); ?>"></script>
<?php if ($modoDiseno): ?>
<script>
window.VP_DISENO = <?php echo json_encode([
    'lamina' => (int)$lamina->id, 'proyecto' => (int)$proyecto->id, 'volver' => '/vplan/ver?proyecto=' . $proyecto->id . '&id=' . $lamina->id,
    'bloques' => array_map(fn($b) => $b->aArray(), $bloques),
    'partidas' => $partidasDiseno,
    'presets' => \Classes\VplanDiseno::PRESETS,
    'tamano' => ['preset' => $lamina->preset, 'ancho_mm' => (int)$lamina->ancho_mm, 'alto_mm' => (int)$lamina->alto_mm],
    'tablas' => array_reduce($tablas, function ($c, $t) { $c[(int)$t['tabla']->id] = ['titulo' => $t['tabla']->etiqueta()]; return $c; }, []),
    'tipos' => \Model\VplanBloque::TIPOS,
    'guias' => $lamina->guias(),
    'catalogo' => $catalogoTablas,
    'biblioteca' => $biblioteca,
    'recovery' => ['n' => (int)$lamina->recovery_n, 'max' => \Model\VplanLamina::MAX_RECOVERY],
    'etiquetasRecovery' => array_map(fn($n) => \Classes\Vplan::etiquetaRecovery($n), range(1, \Model\VplanLamina::MAX_RECOVERY)),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="/vendor/vplan-diseno.js?v=<?php echo filemtime(__DIR__ . "/../../public/vendor/vplan-diseno.js"); ?>"></script>
<?php endif; ?>
