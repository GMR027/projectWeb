<?php
$hoyGantt = date('Y-m-d');
$colHoyGantt = 0;
foreach ($semanas as $iSemana => $semanaGantt) {
    if ($hoyGantt >= $semanaGantt['inicio'] && $hoyGantt <= $semanaGantt['fin']) { $colHoyGantt = $iSemana + 1; break; }
}
$totalSemanas = count($semanas);
$colMesGantt = 2; // la columna 1 es la etiqueta fija
$filaGridGantt = 3; // las filas 1 y 2 son la cabecera
?>
<div class="card prg-gantt-card" id="prg-gantt">
    <div class="prg-card-cab">
        <div>
            <h3 class="card-titulo"><i class="fa-solid fa-chart-gantt"></i> <?php echo s(t('Cronograma semanal')); ?></h3>
            <p class="prg-card-sub">
                <?php echo s(t('Barras por semana con avance, dependencias y restricciones.')); ?>
                <?php if ($colHoyGantt): ?><span class="prg-hoy-chip"><i class="fa-solid fa-location-crosshairs"></i> <?php echo s(t('Hoy')); ?>: <?php echo formatear_fecha($hoyGantt, 'd/m/Y'); ?></span><?php endif; ?>
            </p>
        </div>
        <div class="prg-gantt-cab-acciones">
            <div class="gantt-leyenda prg-gantt-leyenda">
                <span><span class="dot dot-completado"></span> <?php echo s(t('Completado')); ?></span>
                <span><span class="dot dot-proceso"></span> <?php echo s(t('En proceso')); ?></span>
                <span><span class="dot dot-noiniciado"></span> <?php echo s(t('No iniciado')); ?></span>
                <span><span class="dot dot-restriccion"></span> <?php echo s(t('Restricción')); ?></span>
            </div>
            <div class="prg-gantt-controles">
                <?php if ($colHoyGantt): ?>
                    <button type="button" class="btn btn-sm btn-ghost" id="prg-gantt-hoy" title="<?php echo s(t('Desplazar el cronograma a la semana actual')); ?>">
                        <i class="fa-solid fa-location-crosshairs"></i> <?php echo s(t('Hoy')); ?>
                    </button>
                <?php endif; ?>
                <?php if (!empty($depFlechas)): ?>
                    <label class="prg-switch prg-switch-mini gantt-dep-toggle" title="<?php echo s(t('Mostrar u ocultar las flechas de dependencias')); ?>">
                        <input type="checkbox" id="gantt-dep-toggle" checked>
                        <span class="prg-switch-pista"><span></span></span> <?php echo s(t('Dependencias')); ?>
                    </label>
                <?php endif; ?>
                <a href="/lineatiempo?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-sm btn-ghost hide-m" title="<?php echo s(t('Abrir la Línea de tiempo activa')); ?>">
                    <i class="fa-solid fa-timeline"></i> <?php echo s(t('Línea de tiempo')); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="prg-gantt-scroll" id="prg-gantt-scroll" tabindex="0" role="region" aria-label="<?php echo s(t('Cronograma semanal: desplázate para ver todas las semanas')); ?>">
        <div class="gantt-grid prg-gantt-grid" id="prg-gantt-grid" data-hoy="<?php echo (int)$colHoyGantt; ?>" style="grid-template-columns: 26rem repeat(<?php echo $totalSemanas; ?>, minmax(4.2rem, 1fr));">
            <!-- Cabecera: etiqueta fija + banda de meses + semanas -->
            <div class="gantt-cell gantt-head-label">
                <span><?php echo s(t('Actividad / ID')); ?></span>
                <small><?php echo s(t('Semana')); ?></small>
            </div>
            <?php foreach (($resumen['meses'] ?? []) as $mes): $spanMes = max(1, (int)$mes['span']); ?>
                <div class="gantt-cell gantt-mes" style="grid-column: <?php echo $colMesGantt; ?> / span <?php echo $spanMes; ?>; grid-row: 1;" title="<?php echo s(ucfirst($mes['nombre'])); ?>">
                    <span class="mono"><?php echo s($mes['corto']); ?></span>
                </div>
                <?php $colMesGantt += $spanMes; ?>
            <?php endforeach; ?>
            <?php foreach ($semanas as $iSemana => $semana): $esHoySemana = ($iSemana + 1) === $colHoyGantt; ?>
                <div class="gantt-cell gantt-header-cell prg-semana-cab<?php echo $esHoySemana ? ' semana-hoy' : ''; ?>" style="grid-column: <?php echo $iSemana + 2; ?>; grid-row: 2;" data-semana="<?php echo (int)($iSemana + 1); ?>">
                    <div class="semana-num mono">S<?php echo s($semana['numero']); ?></div>
                    <div class="semana-rango"><?php echo s($semana['inicio_fmt']); ?> – <?php echo s($semana['fin_fmt']); ?></div>
                </div>
            <?php endforeach; ?>

            <!-- Filas -->
            <?php foreach ($filas as $fila):
                [$colI, $colF] = $fila['pos'];
                $span = max(1, $colF - $colI + 1);
                $esPartida = $fila['tipo'] === 'partida';
                $esSub = $fila['tipo'] === 'subactividad';
                $claseLabel = $esPartida ? 'label-partida' : ($esSub ? 'label-subactividad' : 'label-actividad');
                $dataNodo = $esPartida ? '' : ($esSub ? ' data-nodo="s' . (int)$fila['id'] . '"' : ' data-nodo="a' . (int)$fila['id'] . '"');
                $urlEditar = $esPartida
                    ? '/programa/partidas/editar?id=' . (int)$fila['id'] . '&modal=1'
                    : ($esSub
                        ? '/programa/subactividades/editar?id=' . (int)$fila['id'] . '&volver=programa&modal=1'
                        : '/programa/actividades/editar?id=' . (int)$fila['id'] . '&modal=1');
                $tituloModal = $esPartida ? t('Editar partida') : ($esSub ? t('Editar subactividad') : t('Editar actividad'));
                $styleLabel = 'grid-column: 1; grid-row: ' . $filaGridGantt . ';' . ($esPartida && $fila['color_bg'] ? ' --partida-color: ' . s($fila['color_bg']) . ';' : '');
                $styleBarra = 'grid-column: ' . ($colI + 1) . ' / span ' . $span . '; grid-row: ' . $filaGridGantt . ';' . ($esPartida && $fila['color_bg'] ? ' --partida-color: ' . s($fila['color_bg']) . ';' : '');
            ?>
                <div class="gantt-cell gantt-label <?php echo $claseLabel; ?> prg-gantt-label" style="<?php echo $styleLabel; ?>">
                    <span class="mono text-sm prg-gantt-codigo"><?php echo s($fila['codigo']); ?></span>
                    <span class="gantt-label-nombre"><?php if ($esSub): ?><i class="fa-solid fa-turn-up fa-rotate-90 text-dim" aria-hidden="true"></i> <?php endif; ?><?php echo s($fila['nombre']); ?></span>
                </div>

                <?php if ($colI > 1): ?>
                    <div class="gantt-cell gantt-bar-cell prg-gantt-vacio" style="grid-column: 2 / span <?php echo $colI - 1; ?>; grid-row: <?php echo $filaGridGantt; ?>;"></div>
                <?php endif; ?>

                <div class="gantt-cell gantt-bar-cell prg-gantt-barra-cell" style="<?php echo $styleBarra; ?>">
                    <div<?php echo $dataNodo; ?>
                         class="gantt-barra prg-gantt-barra <?php echo $esSub ? 'subactividad' : $fila['tipo']; ?> <?php echo !empty($fila['hito']) ? 'gantt-hito' : ''; ?> status-<?php echo $esPartida ? 'partida' : s($fila['status']); ?>"
                         data-id="<?php echo (int)$fila['id']; ?>"
                         data-tipo="<?php echo s($fila['tipo']); ?>" aria-label="<?php echo s($fila['codigo'] . ' · ' . $fila['nombre'] . ': ' . $fila['progreso'] . '%'); ?>"
                         <?php if ($puedeGestionar): ?>data-editar-url="<?php echo s($urlEditar); ?>" data-modal-titulo="<?php echo s($tituloModal); ?>" role="button" tabindex="0"<?php endif; ?>
                         title="<?php echo s($fila['codigo'] . ' — ' . $fila['nombre']); ?> | <?php echo formatear_fecha($fila['fecha_inicio']); ?> → <?php echo formatear_fecha($fila['fecha_fin']); ?> | <?php echo s($fila['progreso']); ?>%">
                        <?php if (!$esPartida): ?>
                            <div class="gantt-barra-fill" style="width: <?php echo s($fila['progreso']); ?>%"></div>
                            <?php if (!$esSub && $fila['tiene_restriccion']): ?>
                                <span class="gantt-restriccion-dot" title="<?php echo s(t('Tiene una restricción activa')); ?>"></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span class="gantt-barra-texto mono"><?php echo s($fila['progreso']); ?>%</span>
                    </div>
                </div>

                <?php if ($colF < $totalSemanas): ?>
                    <div class="gantt-cell gantt-bar-cell prg-gantt-vacio" style="grid-column: <?php echo $colF + 2; ?> / span <?php echo $totalSemanas - $colF; ?>; grid-row: <?php echo $filaGridGantt; ?>;"></div>
                <?php endif; ?>
                <?php $filaGridGantt++; ?>
            <?php endforeach; ?>

            <?php if (empty($filas)): ?>
                <div class="prg-gantt-vacio-mensaje" style="grid-column: 1 / -1; grid-row: 3;">
                    <i class="fa-solid fa-chart-gantt" aria-hidden="true"></i>
                    <p><?php echo s(t('No hay actividades que mostrar con estos filtros.')); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
