<?php $esNueva = !$lamina->id; ?>
<div class="page-header">
    <h2><?php echo $esNueva ? 'Nueva lámina de vPlan' : 'Editar lámina'; ?></h2>
    <a href="<?php echo $lamina->paquete_id ? '/vplan/paquete?proyecto=' . s($proyecto->id) . '&id=' . s($lamina->paquete_id) : '/vplan?proyecto=' . s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/vplan/<?php echo $esNueva ? 'crear' : 'editar'; ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if ($lamina->paquete_id): ?><input type="hidden" name="paquete" value="<?php echo s($lamina->paquete_id); ?>"><?php endif; ?>
        <?php if (!$esNueva): ?><input type="hidden" name="id" value="<?php echo s($lamina->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <?php if ($esNueva): ?>
            <div class="campo">
                <label for="partida_id">Partida inicial</label>
                <select id="partida_id" name="partida_id">
                    <option value="">— Ninguna (la lámina empieza sin tareas) —</option>
                    <?php foreach ($partidas as $p): ?><option value="<?php echo s($p->id); ?>" data-nombre="<?php echo s($p->nombre); ?>" <?php echo (int)$lamina->partida_id === (int)$p->id ? 'selected' : ''; ?>><?php echo s($p->codigo . ' · ' . $p->nombre); ?></option><?php endforeach; ?>
                </select>
                <small class="text-muted">Es solo el punto de partida: después, en «Modo diseño», puedes sumar otras partidas, elegir actividades y mover cada bloque donde quieras.</small>
            </div>
            <div class="campo vp-acts-campo" id="vp-acts" hidden style="grid-column:1/-1">
                <label>Actividades a mostrar en esta lámina</label>
                <p class="text-sm text-muted">Marca solo las que quieras ver aquí; si marcas todas (o ninguna) se muestra la partida completa. Una actividad puede estar en varias láminas.</p>
                <div class="vp-acts-atajos">
                    <button type="button" class="btn btn-sm btn-ghost" data-atajo="todas">Todas</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-atajo="ninguna">Ninguna</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-atajo="invertir">Invertir</button>
                    <button type="button" class="btn btn-sm btn-ghost" data-atajo="libres">Solo las que no están en otra lámina</button>
                </div>
                <p class="text-sm" id="vp-acts-resumen" aria-live="polite"></p>
                <div id="vp-acts-lista" class="vp-acts-lista"></div>
            </div>
            <div class="campo" style="grid-column:1/-1">
                <label for="tabla_inicial">Tabla inicial (SheetFlow)</label>
                <select id="tabla_inicial" name="tabla_inicial">
                    <option value="">— Ninguna —</option>
                    <option value="nueva"<?php echo $tablaInicial === 'nueva' ? ' selected' : ''; ?>>Nueva tabla en blanco</option>
                    <?php if ($hojasSheetflow): ?><optgroup label="Hojas de SheetFlow existentes">
                        <?php foreach ($hojasSheetflow as $h): ?><option value="sf:<?php echo (int)$h['id']; ?>"<?php echo $tablaInicial === 'sf:' . $h['id'] ? ' selected' : ''; ?>><?php echo s($h['nombre'] . ' (' . $h['filas'] . ' renglones, ' . $h['cols'] . ' col.)'); ?></option><?php endforeach; ?>
                    </optgroup><?php endif; ?>
                </select>
                <small class="text-muted">La tabla se edita en SheetFlow y se ve igual aquí. ¿Tienes un documento (Excel, Word, PDF)? Después usa «Tabla en base a documento» en el vPlan.</small>
            </div>
            <?php endif; ?>
            <div class="campo" style="grid-column:1/-1">
                <label for="preset">Tamaño de la hoja</label>
                <div class="vp-dis-fila">
                    <select id="preset" name="preset">
                        <?php foreach (\Classes\VplanDiseno::PRESETS as $k => $pr): ?><option value="<?php echo s($k); ?>"<?php echo $lamina->preset === $k ? ' selected' : ''; ?>><?php echo s($pr[0] . ' · ' . $pr[1] . '×' . $pr[2] . ' mm'); ?></option><?php endforeach; ?>
                        <option value="personalizado"<?php echo $lamina->preset === 'personalizado' ? ' selected' : ''; ?>>Personalizada…</option>
                    </select>
                    <select id="orientacion" name="orientacion"><option value="horizontal">Horizontal</option><option value="vertical"<?php echo $lamina->alto_mm > $lamina->ancho_mm && $lamina->preset !== 'personalizado' ? ' selected' : ''; ?>>Vertical</option></select>
                    <span id="preset-custom" class="vp-dis-fila" hidden>
                        <input type="number" name="ancho" id="ancho" min="1" step="0.1" value="<?php echo (int)$lamina->ancho_mm; ?>" style="width:9rem" aria-label="Ancho"> ×
                        <input type="number" name="alto" id="alto" min="1" step="0.1" value="<?php echo (int)$lamina->alto_mm; ?>" style="width:9rem" aria-label="Alto">
                        <select name="unidad" aria-label="Unidad"><option value="mm">mm</option><option value="cm">cm</option><option value="in">pulgadas</option></select>
                    </span>
                </div>
                <small class="text-muted">Los bloques conservan su lugar en proporción si cambias el tamaño después. Mínimo 100 mm, máximo 1500 mm por lado.</small>
                <script>
                (function () {
                    const p = document.getElementById('preset'), c = document.getElementById('preset-custom'), o = document.getElementById('orientacion');
                    const f = () => { c.hidden = p.value !== 'personalizado'; o.disabled = p.value === 'personalizado'; };
                    p.addEventListener('change', f); f();
                })();
                </script>
            </div>
            <div class="campo"><label for="titulo">Título de la lámina</label><input type="text" id="titulo" name="titulo" value="<?php echo s($lamina->titulo); ?>" placeholder="Por defecto, el nombre de la partida inicial"></div>
            <div class="campo"><label for="grupo">Grupo (disciplina, nivel…)</label><input type="text" id="grupo" name="grupo" value="<?php echo s($lamina->grupo); ?>" maxlength="100" placeholder="Opcional: agrupa las láminas en el vPlan"></div>
            <div class="campo"><label for="supervisor">Supervisor</label><input type="text" id="supervisor" name="supervisor" value="<?php echo s($lamina->supervisor); ?>" placeholder="Ej: JUAN PÉREZ / ANA LÓPEZ"></div>
            <div class="campo">
                <label for="plano">Imagen del plano (hasta Full HD o 4K)</label>
                <input type="file" id="plano" name="plano" accept="image/*">
                <?php if ($lamina->plano): ?>
                    <img class="vp-mini" src="/uploads/vplan/<?php echo s($lamina->plano); ?>" alt="" style="margin-top:.5rem">
                    <label class="text-sm"><input type="checkbox" name="quitar_plano" value="1"> Quitar plano (borra también las marcas dibujadas)</label>
                <?php endif; ?>
            </div>
            <div class="campo">
                <label for="simbologia_grafico_id">Simbología del schedule</label>
                <select id="simbologia_grafico_id" name="simbologia_grafico_id">
                    <option value="">Tabla estándar (SYMBOLOGY FOR SCHEDULE)</option>
                    <?php foreach ($simbologias ?? [] as $sg): ?><option value="<?php echo (int)$sg->id; ?>"<?php echo (int)$lamina->simbologia_grafico_id === (int)$sg->id ? ' selected' : ''; ?>><?php echo s($sg->nombre); ?></option><?php endforeach; ?>
                </select>
                <?php $previas = []; foreach ($simbologias ?? [] as $sg) $previas[(int)$sg->id] = $sg->imagenSimbologia(); ?>
                <div id="simb-previa" class="text-sm" style="margin-top:.5rem" aria-live="polite"></div>
                <script>
                (function () {
                    const sel = document.getElementById('simbologia_grafico_id'), caja = document.getElementById('simb-previa'), img = <?php echo json_encode($previas); ?>;
                    const pinta = () => {
                        caja.textContent = '';
                        if (!sel.value) return;
                        if (!img[sel.value]) { caja.className = 'text-sm text-muted'; caja.textContent = 'Esta simbología aún no tiene imagen: ábrela en Símbolos → Simbologías y guárdala. Mientras tanto se usa la tabla estándar.'; return; }
                        const i = document.createElement('img'); i.src = '/uploads/vplan/' + img[sel.value]; i.alt = 'Vista previa'; i.style.maxWidth = '24rem'; i.style.maxHeight = '16rem'; i.style.border = '1px solid #8b95a5'; caja.appendChild(i);
                    };
                    sel.addEventListener('change', pinta); pinta();
                })();
                </script>
                <small class="text-muted">Se crean y editan en <a href="/simbolos?proyecto=<?php echo (int)$proyecto->id; ?>#simbologias">Símbolos → Simbologías</a>; si cambian, cambian en todas las láminas que las usan.</small>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar lámina</button>
    </form>
</div>
<?php if ($esNueva): ?>
<script>
(function () {
    const sel = document.getElementById('partida_id'), tit = document.getElementById('titulo');
    sel.addEventListener('change', () => { if (!tit.value.trim() && sel.selectedOptions[0]) tit.value = sel.selectedOptions[0].dataset.nombre || ''; });

    // Selección de actividades de la partida
    const datos = <?php echo json_encode($actividadesPorPartida, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const guardadas = new Set(<?php echo json_encode(array_map('intval', $seleccion)); ?>);
    const partidaInicial = <?php echo (int)$lamina->partida_id; ?>;
    const caja = document.getElementById('vp-acts'), lista = document.getElementById('vp-acts-lista'), resumen = document.getElementById('vp-acts-resumen');
    const esc = t => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const cajas = () => [...lista.querySelectorAll('input[type=checkbox]')];

    const actualizar = () => {
        const cs = cajas(), marc = cs.filter(c => c.checked).length;
        const libres = cs.filter(c => c.dataset.en === '' && !c.checked).length;
        resumen.textContent = !cs.length ? '' :
            (marc === 0 || marc === cs.length ? 'Se mostrará la partida completa (' + cs.length + ' actividades).' : 'Se mostrarán ' + marc + ' de ' + cs.length + ' actividades.') +
            (libres ? ' ' + libres + ' sin marcar y sin estar en ninguna otra lámina.' : '');
    };

    const pintar = () => {
        const acts = datos[sel.value] || [];
        caja.hidden = !acts.length;
        // al volver a la partida original se respeta la selección guardada; en otra partida, vacía
        const usar = +sel.value === partidaInicial ? guardadas : new Set();
        lista.innerHTML = acts.map(a =>
            '<label class="vp-acts-item"><input type="checkbox" name="actividades[]" value="' + a.id + '" data-seq="' + a.seq + '" data-en="' + esc(a.en.join('|')) + '"' + (usar.has(a.id) ? ' checked' : '') + '> ' +
            '<b>' + a.seq + '.</b> ' + esc(a.nombre) + (a.en.length ? ' <small class="text-muted">· también en: ' + esc(a.en.join(', ')) + '</small>' : '') + '</label>'
        ).join('');
        actualizar();
    };
    sel.addEventListener('change', pintar);
    lista.addEventListener('change', actualizar);

    caja.addEventListener('click', e => {
        const b = e.target.closest('[data-atajo]');
        if (!b) return;
        const cs = cajas();
        switch (b.dataset.atajo) {
            case 'todas': cs.forEach(c => c.checked = true); break;
            case 'ninguna': cs.forEach(c => c.checked = false); break;
            case 'invertir': cs.forEach(c => c.checked = !c.checked); break;
            case 'libres': cs.forEach(c => c.checked = c.dataset.en === ''); break;
        }
        actualizar();
    });
    pintar();
})();
</script>
<?php endif; ?>
