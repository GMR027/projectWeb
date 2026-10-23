<?php // Variables: $proyecto, $lt, $puedeEditar, $partidasSel, $filtros
$estados = ['pendientes' => 'No iniciadas y en proceso', 'todas' => 'Todas', 'completadas' => 'Solo completadas'];
// Filtros actuales como consulta, para que la exportación lleve exactamente lo que se ve
$qLT = http_build_query(array_filter([
    'proyecto' => (int)$proyecto->id,
    'partida' => $filtros['partida'] ?: null,
    'estado' => $filtros['estado'],
    'restriccion' => $filtros['restriccion'] ? 1 : null,
    'icono' => $filtros['icono'] ? 1 : null,
], fn($v) => $v !== null && $v !== ''));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-chart-gantt"></i> <?php echo s(t('Línea de tiempo activa')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo $puedeEditar
            ? s(t('Agrupado por partida: arrastra las barras para reprogramar y usa la rueda del ratón para ampliar o reducir.'))
            : s(t('Vista de solo lectura.')); ?></p>
    </div>
    <div class="lt-header-acciones">
        <?php if (puede_descargar()): ?>
            <details class="gr-descargar" data-ayuda="<?php echo s(t('Descarga la vista actual (con estos filtros) como PDF en una sola hoja del tamaño que haga falta, o como tabla .ods editable.')); ?>" data-manual="/manual#lineatiempo">
                <summary class="btn btn-ghost"><i class="fa-solid fa-file-export"></i> <?php echo s(t('Exportar')); ?></summary>
                <div class="gr-descargar-menu">
                    <a href="/lineatiempo/exportar?<?php echo s($qLT); ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> <?php echo s(t('PDF (una sola hoja)')); ?></a>
                    <a href="/lineatiempo/exportar?<?php echo s($qLT); ?>&formato=ods"><i class="fa-solid fa-table"></i> <?php echo s(t('LibreOffice Calc (.ods)')); ?></a>
                </div>
            </details>
        <?php endif; ?>
        <a href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← <?php echo s(t('Programa')); ?></a>
    </div>
</div>

<form class="card lt-filtros" method="get" action="/lineatiempo">
    <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
    <label><?php echo s(t('Partida')); ?> <select name="partida" onchange="this.form.submit()">
        <option value=""><?php echo s(t('Todas')); ?></option>
        <?php foreach ($partidasSel as $pid => $nombre): ?>
            <option value="<?php echo (int)$pid; ?>"<?php echo $filtros['partida'] === $pid ? ' selected' : ''; ?>><?php echo s($nombre); ?></option>
        <?php endforeach; ?>
    </select></label>
    <label><?php echo s(t('Estado')); ?> <select name="estado" onchange="this.form.submit()">
        <?php foreach ($estados as $k => $texto): ?>
            <option value="<?php echo $k; ?>"<?php echo $filtros['estado'] === $k ? ' selected' : ''; ?>><?php echo s(t($texto)); ?></option>
        <?php endforeach; ?>
    </select></label>
    <label class="campo-check"><input type="checkbox" name="restriccion" value="1"<?php echo $filtros['restriccion'] ? ' checked' : ''; ?> onchange="this.form.submit()"> <?php echo s(t('Solo con restricción')); ?></label>
    <label class="campo-check"><input type="checkbox" name="icono" value="1"<?php echo !empty($filtros['icono']) ? ' checked' : ''; ?> onchange="this.form.submit()"> <?php echo s(t('Icono de actividad')); ?></label>
    <span class="lt-nav" role="group" aria-label="<?php echo s(t('Navegar por meses')); ?>">
        <button type="button" class="btn btn-sm btn-ghost" id="lt-prev" title="<?php echo s(t('Mes anterior')); ?>" aria-label="<?php echo s(t('Mes anterior')); ?>"><i class="fa-solid fa-chevron-left"></i></button>
        <button type="button" class="btn btn-sm btn-ghost" id="lt-hoy" title="<?php echo s(t('Hoy')); ?>" aria-label="<?php echo s(t('Hoy')); ?>"><i class="fa-solid fa-location-crosshairs"></i></button>
        <button type="button" class="btn btn-sm btn-ghost" id="lt-next" title="<?php echo s(t('Mes siguiente')); ?>" aria-label="<?php echo s(t('Mes siguiente')); ?>"><i class="fa-solid fa-chevron-right"></i></button>
    </span>
    <span class="lt-contador" aria-live="polite"><strong id="lt-cont-total"><?php echo (int)$lt['total']; ?></strong> <?php echo s(t('actividades')); ?><span id="lt-cont-restr"<?php echo $lt['conRestriccion'] ? '' : ' hidden'; ?>> · <strong id="lt-cont-restr-n"><?php echo (int)$lt['conRestriccion']; ?></strong> <?php echo s(t('con restricción')); ?></span></span>
</form>

<div id="lt-aviso" class="lt-aviso" hidden aria-live="polite"></div>

<div class="card lt-lienzo">
    <div class="lt-leyenda">
        <span><i class="lt-sw" style="background:#3b82f6"></i> <?php echo s(t('Color de su partida')); ?></span>
        <span><i class="lt-sw lt-sw-nohab"></i> <?php echo s(t('Día no hábil (jornada o festivo)')); ?></span>
        <span><i class="lt-sw lt-sw-hoy"></i> <?php echo s(t('Hoy')); ?></span>
        <span><i class="lt-chip-est lt-ley-ico st-no_iniciada"><svg viewBox="0 0 16 16"><path d="M4.5 4.5l7 7m0-7l-7 7" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/></svg></i><i class="lt-chip-est lt-ley-ico st-en_proceso"><svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="5.8" fill="none" stroke="#fff" stroke-width="2"/><path d="M8 4.4V8l2.6 1.7" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg></i><i class="lt-chip-est lt-ley-ico st-completada"><svg viewBox="0 0 16 16"><path d="M3 8.5l3.5 3.5L13 5" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg></i><?php echo s(t('Estado: no iniciada · en proceso · completada')); ?></span>
        <span><i class="lt-chip-restr lt-ley-ico"><svg viewBox="0 0 16 16"><path d="M8 2.4 14.6 13.6H1.4z" fill="#fff"/><rect x="7.2" y="6" width="1.6" height="4.4" rx=".8" fill="#ef4444"/><circle cx="8" cy="12.2" r="1" fill="#ef4444"/></svg></i><?php echo s(t('Con restricción')); ?></span>
        <span>◆ <?php echo s(t('Hito (solo se mueve completo)')); ?></span>
    </div>
    <div class="lt-scroll" id="lt-scroll"><div class="lt-labels" id="lt-labels"></div><div class="lt-canvas" id="lt-canvas"></div></div>
</div>

<p class="text-muted text-sm lt-ayuda"><?php echo $puedeEditar
    ? s(t('Arrastra el cuerpo de una barra para cambiar inicio y fin juntos; arrastra las flechas ◀ ▶ de sus extremos para cambiar solo el inicio o solo el fin. Usa la rueda del ratón sobre el diagrama para ampliar o reducir, mantenla presionada (botón central) para arrastrar el diagrama y ‹ › para cambiar de mes. Al soltar, la fecha se ajusta al día hábil más cercano y se guarda sin recargar. Si las dependencias están en modo automático, las actividades sucesoras se recorren solas. La casilla «Icono de actividad» muestra la imagen de cada actividad en forma circular sobre su barra.'))
    : s(t('Los cambios de fechas se hacen en el Programa; aquí solo se consulta.')); ?></p>

<script>window.LT = <?php echo json_encode($lt, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>; window.LT.puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>; window.LT.soloRestriccion = <?php echo !empty($filtros['restriccion']) ? 'true' : 'false'; ?>;</script>
<script src="/vendor/lineatiempo.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/lineatiempo.js'); ?>"></script>
