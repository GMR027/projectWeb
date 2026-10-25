<?php $esNueva = !$lamina->id; ?>
<div class="page-header">
    <h2><?php echo $esNueva ? '<i class="fa-solid fa-wand-sparkles"></i> ' . s(t('Nueva lámina de vPlan')) : '<i class="fa-solid fa-sliders"></i> ' . s(t('Editar lámina')); ?></h2>
    <a href="<?php echo $lamina->paquete_id ? '/vplan/paquete?proyecto=' . s($proyecto->id) . '&id=' . s($lamina->paquete_id) : '/vplan?proyecto=' . s($proyecto->id); ?>" class="btn btn-ghost">← <?php echo s(t('Volver')); ?></a>
</div>
<div class="card">
<?php if ($esNueva): ?>
    <?php // ---- Asistente de 3 pasos: qué lleva · tamaño de hoja · datos (los pasos se envían juntos) ---- ?>
    <div class="pasos" role="tablist" aria-label="<?php echo s(t('Pasos de creación')); ?>">
        <?php foreach ([[1, 'fa-list-check', '¿Qué lleva?', 'partidas y tablas'], [2, 'fa-expand', 'Tamaño de hoja', 'elige el papel'], [3, 'fa-pen-to-square', 'Datos y plano', 'todo opcional']] as [$n, $ic, $tt, $sub]): ?>
        <button type="button" class="paso-tab<?php echo $n === 1 ? ' activo' : ''; ?>" data-paso="<?php echo $n; ?>" role="tab" aria-selected="<?php echo $n === 1 ? 'true' : 'false'; ?>">
            <span class="paso-num"><?php echo $n; ?></span><span><b><i class="fa-solid <?php echo $ic; ?>"></i> <?php echo s(t($tt)); ?></b><small><?php echo s($sub); ?></small></span>
        </button>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
    <form method="POST" action="/vplan/<?php echo $esNueva ? 'crear' : 'editar'; ?>" enctype="multipart/form-data" class="formulario" id="vp-form">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <?php if ($lamina->paquete_id): ?><input type="hidden" name="paquete" value="<?php echo s($lamina->paquete_id); ?>"><?php endif; ?>
        <?php if (!$esNueva): ?><input type="hidden" name="id" value="<?php echo s($lamina->id); ?>"><?php endif; ?>

        <?php if ($esNueva): ?><div class="paso-secc" data-paso="1"><?php endif; ?>
        <?php if ($esNueva): ?>
            <h3>¿Qué lleva esta lámina?</h3>
            <p class="text-muted">Elige de qué partidas del programa trae las tareas y si arranca con una tabla. Después podrás sumar o quitar cosas en el modo diseño.</p>
            <div class="form-grid">
            <div class="campo">
                <label for="partida_id"><?php echo s(t('Partida inicial')); ?></label>
                <select id="partida_id" name="partida_id">
                    <option value="">— <?php echo s(t('Ninguna (la lámina empieza sin tareas)')); ?> —</option>
                    <?php foreach ($partidas as $p): ?><option value="<?php echo s($p->id); ?>" data-nombre="<?php echo s($p->nombre); ?>" <?php echo (int)$lamina->partida_id === (int)$p->id ? 'selected' : ''; ?>><?php echo s($p->codigo . ' · ' . $p->nombre); ?></option><?php endforeach; ?>
                </select>
                <small class="text-muted"><?php echo s(t('Es solo el punto de partida: después, en «Acomodar bloques», puedes sumar otras partidas, elegir actividades y mover cada bloque donde quieras.')); ?></small>
            </div>
            <div class="campo vp-acts-campo" id="vp-acts" hidden style="grid-column:1/-1">
                <label><?php echo s(t('Actividades a mostrar en esta lámina')); ?></label>
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
            </div>
        </div>
        <div class="paso-secc" data-paso="2" hidden>
            <h3>¿De qué tamaño es la hoja?</h3>
            <p class="text-muted">Elige la proporción del papel. La más usada en obra es <strong>Tabloide</strong>, la misma del programa y del PDF.</p>
        <?php else: ?>
        <?php endif; ?>

        <?php // ---- Tamaño de hoja (paso 2 al crear; sección normal al editar) ---- ?>
        <?php $personalizado = $lamina->preset === 'personalizado'; ?>
        <div class="campo" style="grid-column:1/-1">
            <label for="preset"><?php echo s(t('Tamaño de la hoja')); ?></label>
            <div class="tamano-cards" role="radiogroup" aria-label="<?php echo s(t('Tamaño de hoja')); ?>" id="preset-cards">
                <?php $recom = 'tabloide'; foreach (\Classes\VplanDiseno::PRESETS as $k => $pr): [$nombre, $w, $h] = $pr; ?>
                <?php $maxW = 8.6; $ancho = $maxW; $alto = round($maxW * $h / max(1, $w), 2);   // rectángulo a escala del papel ?>
                <button type="button" class="tamano-card<?php echo $lamina->preset === $k ? ' activo' : ''; ?>" data-preset="<?php echo s($k); ?>" role="radio" aria-checked="<?php echo $lamina->preset === $k ? 'true' : 'false'; ?>" title="<?php echo s($nombre . ' · ' . $w . ' × ' . $h . ' mm'); ?>">
                    <span class="tamano-mini"><div style="width:<?php echo $ancho; ?>rem;height:<?php echo $alto; ?>rem"></div></span>
                    <b><?php echo s($nombre); ?><?php echo $k === $recom ? ' ★' : ''; ?></b>
                    <small><?php echo (int)$w; ?> × <?php echo (int)$h; ?> mm<?php echo $k === $recom ? ' · recomendada' : ''; ?></small>
                </button>
                <?php endforeach; ?>
                <button type="button" class="tamano-card tamano-custom<?php echo $personalizado ? ' activo' : ''; ?>" data-preset="personalizado" role="radio" aria-checked="<?php echo $personalizado ? 'true' : 'false'; ?>">
                    <span class="tamano-mini"><i class="fa-solid fa-sliders" style="font-size:1.8rem"></i></span>
                    <b>Personalizada…</b>
                    <small>medidas exactas</small>
                </button>
            </div>
            <select id="preset" name="preset" hidden aria-hidden="true" tabindex="-1">
                <?php foreach (\Classes\VplanDiseno::PRESETS as $k => $pr): ?><option value="<?php echo s($k); ?>"<?php echo $lamina->preset === $k ? ' selected' : ''; ?>><?php echo s($pr[0]); ?></option><?php endforeach; ?>
                <option value="personalizado"<?php echo $personalizado ? ' selected' : ''; ?>>Personalizada</option>
            </select>
            <div class="vp-dis-fila" style="margin-top:.8rem">
                <label class="text-sm" for="orientacion" style="align-self:center">Orientación:</label>
                <select id="orientacion" name="orientacion"><option value="horizontal">Horizontal</option><option value="vertical"<?php echo $lamina->alto_mm > $lamina->ancho_mm && !$personalizado ? ' selected' : ''; ?>>Vertical</option></select>
                <span id="preset-custom" class="vp-dis-fila" <?php echo !$personalizado ? ' hidden' : ''; ?>>
                    <input type="number" name="ancho" id="ancho" min="1" step="0.1" value="<?php echo (int)$lamina->ancho_mm; ?>" style="width:9rem" aria-label="Ancho"> ×
                    <input type="number" name="alto" id="alto" min="1" step="0.1" value="<?php echo (int)$lamina->alto_mm; ?>" style="width:9rem" aria-label="Alto">
                    <select name="unidad" aria-label="Unidad"><option value="mm">mm</option><option value="cm">cm</option><option value="in">pulgadas</option></select>
                </span>
            </div>
            <small class="text-muted">Los bloques conservan su lugar en proporción si cambias el tamaño después. Mínimo 100 mm, máximo 1500 mm por lado.</small>
        </div>

        <?php if ($esNueva): ?>
        </div>
        <div class="paso-secc" data-paso="3" hidden>
            <h3>Datos y plano <small class="text-muted">(todo opcional)</small></h3>
            <p class="text-muted">Puedes dejarlo así y rellenarlo luego: la lámina se crea con lo de los pasos anteriores.</p>
        <?php endif; ?>
        <?php // ---- Datos de la lámina (paso 3 al crear; sección normal al editar) ---- ?>
        <div class="form-grid">
            <div class="campo"><label for="titulo"><?php echo s(t('Título de la lámina')); ?></label><input type="text" id="titulo" name="titulo" value="<?php echo s($lamina->titulo); ?>" placeholder="Por defecto, el nombre de la partida inicial"></div>
            <div class="campo"><label for="grupo">Grupo (disciplina, nivel…)</label><input type="text" id="grupo" name="grupo" value="<?php echo s($lamina->grupo); ?>" maxlength="100" placeholder="Opcional: agrupa las láminas en el vPlan"></div>
            <div class="campo"><label for="supervisor">Supervisor</label><input type="text" id="supervisor" name="supervisor" value="<?php echo s($lamina->supervisor); ?>" placeholder="Ej: JUAN PÉREZ / ANA LÓPEZ"></div>
            <div class="campo">
                <label for="plano"><?php echo s(t('Imagen del plano')); ?> (hasta Full HD o 4K)</label>
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
                <small class="text-muted">Se crean y editan en <a href="/simbolos?proyecto=<?php echo (int)$proyecto->id; ?>#simbologias">Símbolos → Simbologías</a>; si cambian, cambian en todas las láminas que las usan.</small>
            </div>
        </div>
        <?php if ($esNueva): ?>
        </div>
        <?php endif; ?>

        <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
        <?php if ($esNueva): ?>
        <div class="paso-nav">
            <span class="paso-nav-info" id="vp-paso-info">Paso 1 de 3 · <?php echo s(t('Puedes volver con «Anterior»')); ?></span>
            <button type="button" class="btn btn-ghost" id="vp-paso-ant" hidden><i class="fa-solid fa-arrow-left"></i> <?php echo s(t('Anterior')); ?></button>
            <button type="button" class="btn btn-primary" id="vp-paso-sig">Siguiente <i class="fa-solid fa-arrow-right"></i></button>
            <button type="submit" class="btn btn-primary" id="vp-paso-fin" hidden><i class="fa-solid fa-wand-sparkles"></i> Crear y acomodar bloques</button>
        </div>
        <?php else: ?>
        <button type="submit" class="btn btn-primary"><?php echo s(t('Guardar lámina')); ?></button>
        <?php endif; ?>
    </form>
</div>
<script>
(function () {
    const sel = document.getElementById('partida_id'), tit = document.getElementById('titulo');
    if (sel && tit) sel.addEventListener('change', () => { if (!tit.value.trim() && sel.selectedOptions[0]) tit.value = sel.selectedOptions[0].dataset.nombre || ''; });

    // ---- Tarjetas de tamaño: sincronizan el <select> oculto que envía el formulario ----
    const preset = document.getElementById('preset'), cards = document.getElementById('preset-cards'),
        custom = document.getElementById('preset-custom'), ori = document.getElementById('orientacion');
    const pintaCards = () => {
        custom.hidden = preset.value !== 'personalizado';
        if (ori) ori.disabled = preset.value === 'personalizado';
        if (!cards) return;
        cards.querySelectorAll('.tamano-card').forEach(c => {
            const on = c.dataset.preset === preset.value;
            c.classList.toggle('activo', on); c.setAttribute('aria-checked', on ? 'true' : 'false');
        });
    };
    if (cards) cards.addEventListener('click', e => {
        const c = e.target.closest('.tamano-card'); if (!c) return;
        preset.value = c.dataset.preset; pintaCards();
    });
    if (preset) preset.addEventListener('change', pintaCards);
    pintaCards();

    // ---- Asistente: se envía todo junto al final ----
    const pasos = [...document.querySelectorAll('#vp-form .paso-secc')];
    if (!pasos.length) return;
    const tabs = [...document.querySelectorAll('.paso-tab')], ant = document.getElementById('vp-paso-ant'),
        sig = document.getElementById('vp-paso-sig'), fin = document.getElementById('vp-paso-fin'), info = document.getElementById('vp-paso-info');
    let actual = 1;
    const ver = n => {
        actual = n;
        pasos.forEach(s => { s.hidden = +s.dataset.paso !== n; });
        tabs.forEach(t => { const k = +t.dataset.paso; t.classList.toggle('activo', k === n); t.classList.toggle('hecho', k < n); t.setAttribute('aria-selected', k === n ? 'true' : 'false'); t.disabled = k > n; });
        ant.hidden = n === 1;
        sig.hidden = n === pasos.length;
        fin.hidden = n !== pasos.length;
        if (info) info.textContent = 'Paso ' + n + ' de ' + pasos.length + (n === pasos.length ? ' · revisa y crea' : '');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    tabs.forEach(t => t.addEventListener('click', () => { if (+t.dataset.paso <= actual) ver(+t.dataset.paso); }));
    sig.addEventListener('click', () => ver(Math.min(pasos.length, actual + 1)));
    ant.addEventListener('click', () => ver(Math.max(1, actual - 1)));
    ver(1);
})();
</script>
<?php if ($esNueva): ?>
<script>
(function () {
    // Selección de actividades de la partida (paso 1 del asistente)
    const datos = <?php echo json_encode($actividadesPorPartida, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const guardadas = new Set(<?php echo json_encode(array_map('intval', $seleccion)); ?>);
    const partidaInicial = <?php echo (int)$lamina->partida_id; ?>;
    const caja = document.getElementById('vp-acts'), lista = document.getElementById('vp-acts-lista'), resumen = document.getElementById('vp-acts-resumen');
    const sel = document.getElementById('partida_id');
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
<script>
(function () {
    // Vista previa de la simbología elegida (paso 3)
    const sel = document.getElementById('simbologia_grafico_id'), caja = document.getElementById('simb-previa'), img = <?php echo json_encode($previas); ?>;
    if (!sel || !caja) return;
    const pinta = () => {
        caja.textContent = '';
        if (!sel.value) return;
        if (!img[sel.value]) { caja.className = 'text-sm text-muted'; caja.textContent = 'Esta simbología aún no tiene imagen: ábrela en Símbolos → Simbologías y guárdala. Mientras tanto se usa la tabla estándar.'; return; }
        const i = document.createElement('img'); i.src = '/uploads/vplan/' + img[sel.value]; i.alt = 'Vista previa'; i.style.maxWidth = '24rem'; i.style.maxHeight = '16rem'; i.style.border = '1px solid #8b95a5'; caja.appendChild(i);
    };
    sel.addEventListener('change', pinta); pinta();
})();
</script>
<?php endif; ?>
