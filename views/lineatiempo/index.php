<?php // Variables: $proyecto, $lt, $puedeEditar, $partidasSel, $filtros
$estados = ['pendientes' => 'No iniciadas y en proceso', 'todas' => 'Todas', 'completadas' => 'Solo completadas'];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-chart-gantt"></i> <?php echo s(t('Línea de tiempo activa')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo $puedeEditar
            ? s(t('Agrupado por partida: arrastra las barras para reprogramar y usa la rueda del ratón para ampliar o reducir.'))
            : s(t('Vista de solo lectura.')); ?></p>
    </div>
    <a href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← <?php echo s(t('Programa')); ?></a>
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
    <span class="lt-contador" aria-live="polite"><strong><?php echo (int)$lt['total']; ?></strong> <?php echo s(t('actividades')); ?><?php echo $lt['conRestriccion'] ? ' · <strong>' . (int)$lt['conRestriccion'] . '</strong> ' . s(t('con restricción')) : ''; ?></span>
</form>

<div id="lt-aviso" class="lt-aviso" hidden aria-live="polite"></div>

<div class="card lt-lienzo">
    <div class="lt-leyenda">
        <span><i class="lt-sw" style="background:#3b82f6"></i> <?php echo s(t('Color de su partida')); ?></span>
        <span><i class="lt-sw lt-sw-nohab"></i> <?php echo s(t('Día no hábil (jornada o festivo)')); ?></span>
        <span><i class="lt-sw lt-sw-hoy"></i> <?php echo s(t('Hoy')); ?></span>
        <span>⚠ <?php echo s(t('Con restricción')); ?></span>
        <span>◆ <?php echo s(t('Hito (solo se mueve completo)')); ?></span>
    </div>
    <div class="lt-scroll" id="lt-scroll"><div class="lt-labels" id="lt-labels"></div><div class="lt-canvas" id="lt-canvas"></div></div>
</div>

<p class="text-muted text-sm lt-ayuda"><?php echo $puedeEditar
    ? s(t('Arrastra el cuerpo de una barra para cambiar inicio y fin juntos; arrastra las flechas ◀ ▶ de sus extremos para cambiar solo el inicio o solo el fin. Usa la rueda del ratón sobre el diagrama para ampliar o reducir, mantenla presionada (botón central) para arrastrar el diagrama y ‹ › para cambiar de mes. Al soltar, la fecha se ajusta al día hábil más cercano y se guarda sin recargar. Si las dependencias están en modo automático, las actividades sucesoras se recorren solas. La casilla «Icono de actividad» muestra la imagen de cada actividad en forma circular sobre su barra.'))
    : s(t('Los cambios de fechas se hacen en el Programa; aquí solo se consulta.')); ?></p>

<script>window.LT = <?php echo json_encode($lt, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>; window.LT.puedeEditar = <?php echo $puedeEditar ? 'true' : 'false'; ?>;</script>
<script src="/vendor/lineatiempo.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/lineatiempo.js'); ?>"></script>
