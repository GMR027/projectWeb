<?php
$nomPrograma = \Classes\ProgramaColumnas::nombrePrograma($proyecto);
$hoyISO = date('Y-m-d');
if (($proyecto->fecha_inicio ?? '') > $hoyISO) { $estadoProyecto = ['Por iniciar', 'info']; }
elseif (($proyecto->fecha_fin ?? '') && $proyecto->fecha_fin < $hoyISO) { $estadoProyecto = ['Finalizada', 'ok']; }
else { $estadoProyecto = ['En ejecución', 'ok']; }
$iniciales = '';
foreach (preg_split('/\s+/', trim((string)$proyecto->nombre)) as $palabra) {
    if ($palabra === '') continue;
    $iniciales .= mb_strtoupper(mb_substr($palabra, 0, 1));
    if (mb_strlen($iniciales) >= 2) break;
}
$totalFilasActividad = (int)$resumen['actividades'] + (int)$resumen['subactividades'];
$completadas = (int)$resumen['completadas'];
?>
<div class="programa-header prg-hero">
    <div class="prg-hero-main">
        <div class="prg-hero-id">
            <?php if ($proyecto->logo): ?>
                <img src="/build/img/logos/<?php echo s($proyecto->logo); ?>" alt="logo" class="programa-logo">
            <?php else: ?>
                <span class="prg-monograma" aria-hidden="true"><?php echo s($iniciales ?: 'PW'); ?></span>
            <?php endif; ?>
        </div>
        <div class="prg-hero-txt">
            <div class="prg-eyebrow"><i class="fa-solid fa-chart-gantt" aria-hidden="true"></i> <?php echo s(t('Programa de obra')); ?></div>
            <h2><?php echo s($proyecto->nombre); ?></h2>
            <div class="prg-chips">
                <span class="prg-chip mono"><i class="fa-solid fa-hashtag" aria-hidden="true"></i> <?php echo s($proyecto->codigo); ?></span>
                <?php if (!empty($proyecto->ubicacion)): ?><span class="prg-chip"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo s($proyecto->ubicacion); ?></span><?php endif; ?>
                <span class="prg-chip prg-chip-<?php echo s($estadoProyecto[1]); ?>"><i class="fa-solid fa-circle" aria-hidden="true"></i> <?php echo s(t($estadoProyecto[0])); ?></span>
                <span class="prg-chip"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo formatear_fecha($proyecto->fecha_inicio); ?> → <?php echo formatear_fecha($proyecto->fecha_fin); ?></span>
                <?php if (!empty($resumen['hitos'])): ?><span class="prg-chip"><i class="fa-solid fa-flag" aria-hidden="true"></i> <?php echo (int)$resumen['hitos']; ?> <?php echo s(t('hitos')); ?></span><?php endif; ?>
            </div>
            <p class="prg-hero-meta">
                <?php if ($nomPrograma !== $proyecto->nombre): ?><span class="prg-hero-nom"><?php echo s($nomPrograma); ?></span> · <?php endif; ?>
                <?php echo (int)$resumen['partidas']; ?> <?php echo s(t('partidas')); ?> ·
                <?php echo (int)$resumen['actividades']; ?> <?php echo s(t('actividades')); ?><?php if (!empty($resumen['subactividades'])): ?> ·
                <?php echo (int)$resumen['subactividades']; ?> <?php echo s(t('subactividades')); ?><?php endif; ?>
                <?php if (!empty($resumen['prioridad_critica'])): ?> · <?php echo (int)$resumen['prioridad_critica']; ?> <?php echo s(t('de prioridad crítica')); ?><?php endif; ?>
            </p>
        </div>
    </div>

    <div class="prg-hero-side">
        <div class="prg-avance" style="--pct: <?php echo s((float)$resumen['avance']); ?>">
            <div class="prg-avance-ring" role="img" aria-label="<?php echo s(t('Avance ponderado')); ?>: <?php echo s((float)$resumen['avance']); ?>%">
                <span><?php echo number_format((float)$resumen['avance'], 1); ?><small>%</small></span>
            </div>
            <div class="prg-avance-txt">
                <strong><?php echo s(t('Avance ponderado')); ?></strong>
                <span><?php echo (int)$completadas; ?> <?php echo s(t('de')); ?> <?php echo (int)$totalFilasActividad; ?> <?php echo s(t('completadas')); ?></span>
            </div>
        </div>
        <div class="prg-hero-acciones">
            <?php if ($puedeGestionar): ?>
                <a href="/programa/actividades/crear?proyecto=<?php echo (int)$proyecto->id; ?>"
                   class="btn btn-primary prg-modal-link"
                   data-modal-url="/programa/actividades/crear?proyecto=<?php echo (int)$proyecto->id; ?>&modal=1"
                   data-modal-titulo="<?php echo s(t('Nueva actividad')); ?>">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> <?php echo s(t('Nueva actividad')); ?>
                </a>
                <details class="prg-menu-herramientas">
                    <summary class="btn btn-ghost" title="<?php echo s(t('Más herramientas del programa')); ?>" aria-label="<?php echo s(t('Más herramientas del programa')); ?>"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></summary>
                    <div class="prg-menu-herramientas-lista">
                        <a href="/programa/partidas/crear?proyecto=<?php echo (int)$proyecto->id; ?>" class="prg-modal-link" data-modal-url="/programa/partidas/crear?proyecto=<?php echo (int)$proyecto->id; ?>&modal=1" data-modal-titulo="<?php echo s(t('Nueva partida')); ?>"><i class="fa-solid fa-folder-plus"></i> <?php echo s(t('Nueva partida')); ?></a>
                        <a href="/programa/diseno?proyecto=<?php echo (int)$proyecto->id; ?>"><i class="fa-solid fa-table-columns"></i> <?php echo s(t('Diseñar programa')); ?></a>
                        <a href="/programa/ordenar?proyecto=<?php echo (int)$proyecto->id; ?>"><i class="fa-solid fa-arrow-down-1-9"></i> <?php echo s(t('Ordenar y renumerar')); ?></a>
                        <a href="/campos?proyecto=<?php echo (int)$proyecto->id; ?>"><i class="fa-solid fa-tags"></i> <?php echo s(t('Campos personalizados')); ?></a>
                        <span class="prg-menu-sep"></span>
                        <a href="/programa/importar?proyecto=<?php echo (int)$proyecto->id; ?>"><i class="fa-solid fa-file-import"></i> <?php echo s(t('Importar XML / POD')); ?></a>
                        <a href="/programa/importar/p6?proyecto=<?php echo (int)$proyecto->id; ?>"><i class="fa-solid fa-file-import"></i> <?php echo s(t('Importar Primavera P6')); ?></a>
                        <a href="/programador?proyecto=<?php echo (int)$proyecto->id; ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> <?php echo s(t('Programador (IA)')); ?></a>
                        <?php if (puede_descargar()): ?><a href="/ajustes?proyecto=<?php echo (int)$proyecto->id; ?>"><i class="fa-solid fa-gear"></i> <?php echo s(t('Ajustes y exportar')); ?></a><?php endif; ?>
                        <span class="prg-menu-sep"></span>
                        <a href="/proyectos"><i class="fa-solid fa-arrow-left"></i> <?php echo s(t('Volver al portafolio')); ?></a>
                    </div>
                </details>
            <?php endif; ?>
            <a href="/proyectos" class="btn btn-ghost prg-volver" title="<?php echo s(t('Volver al portafolio')); ?>"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <span class="hide-m"><?php echo s(t('Proyectos')); ?></span></a>
        </div>
    </div>
</div>

<div class="prg-kpis">
    <a class="prg-kpi" href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>&vista=tabla">
        <span class="prg-kpi-icono"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
        <span class="prg-kpi-txt">
            <small><?php echo s(t('Actividades')); ?></small>
            <strong><?php echo (int)$resumen['actividades']; ?></strong>
            <span><?php echo (int)$resumen['completadas']; ?> <?php echo s(t('completadas')); ?> · <?php echo (int)$resumen['en_proceso']; ?> <?php echo s(t('en proceso')); ?></span>
        </span>
    </a>
    <a class="prg-kpi prg-kpi-warning" href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>&riesgo=1">
        <span class="prg-kpi-icono"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
        <span class="prg-kpi-txt">
            <small><?php echo s(t('En riesgo')); ?></small>
            <strong><?php echo (int)($resumen['riesgo'] ?? 0); ?></strong>
            <span><?php echo s(t('Material, punch o permisos')); ?></span>
        </span>
    </a>
    <a class="prg-kpi prg-kpi-danger" href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>&restriccion=con">
        <span class="prg-kpi-icono"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
        <span class="prg-kpi-txt">
            <small><?php echo s(t('Restricciones activas')); ?></small>
            <strong><?php echo (int)($resumen['restricciones'] ?? 0); ?></strong>
            <span><?php echo (int)($resumen['con_restriccion'] ?? 0); ?> <?php echo s(t('actividades afectadas')); ?></span>
        </span>
    </a>
    <a class="prg-kpi prg-kpi-info" href="/lineatiempo?proyecto=<?php echo (int)$proyecto->id; ?>">
        <span class="prg-kpi-icono"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
        <span class="prg-kpi-txt">
            <small><?php echo s(t('Días hábiles restantes')); ?></small>
            <strong><?php echo (int)($resumen['dias_restantes'] ?? 0); ?></strong>
            <span><?php echo s(t('Fin')); ?>: <?php echo formatear_fecha($proyecto->fecha_fin, 'd/m/Y'); ?></span>
        </span>
    </a>
    <?php if (!empty($resumen['punch_bloqueos']) || !empty($resumen['calidad_bloqueos'])): ?>
        <a class="prg-kpi prg-kpi-bloqueo" href="/punch?proyecto=<?php echo (int)$proyecto->id; ?>&estado=pendientes">
            <span class="prg-kpi-icono"><i class="fa-solid fa-ban" aria-hidden="true"></i></span>
            <span class="prg-kpi-txt">
                <small><?php echo s(t('Bloqueos')); ?></small>
                <strong><?php echo (int)($resumen['punch_bloqueos'] ?? 0) + (int)($resumen['calidad_bloqueos'] ?? 0); ?></strong>
                <span><?php echo (int)($resumen['punch_bloqueos'] ?? 0); ?> punch · <?php echo (int)($resumen['calidad_bloqueos'] ?? 0); ?> calidad</span>
            </span>
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($alertasObra)): ?>
    <div class="prg-alertas" role="status">
        <?php foreach ($alertasObra as $al): ?>
            <a class="prg-alerta <?php echo $al['nivel'] === 'critica' ? 'prg-alerta-error' : 'prg-alerta-warning'; ?>" href="/datos-obra?proyecto=<?php echo (int)$proyecto->id; ?>">
                <i class="fa-solid <?php echo $al['nivel'] === 'critica' ? 'fa-triangle-exclamation' : 'fa-circle-info'; ?>" aria-hidden="true"></i>
                <span><?php echo s($al['texto']); ?></span>
                <i class="fa-solid fa-chevron-right prg-alerta-flecha" aria-hidden="true"></i>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($pg) && is_admin()): ?>
    <details class="prg-ia card">
        <summary>
            <span><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> <?php echo s(t('Propuestas del Programador')); ?> <b><?php echo count($pg); ?></b></span>
            <span class="prg-ia-ver"><?php echo s(t('Ver propuestas')); ?> <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></span>
        </summary>
        <div class="prg-ia-lista">
            <?php foreach (array_slice($pg, 0, 4) as $g): ?>
                <a href="/programador?proyecto=<?php echo (int)$proyecto->id; ?>&propuesta=<?php echo (int)$g['id']; ?>">
                    <strong><?php echo s($g['nombre'] ?: 'Propuesta ' . date('d/m/Y', strtotime($g['creado']))); ?></strong>
                    <small><?php echo s(date('d/m/Y H:i', strtotime($g['creado']))); ?> · <?php echo $g['dif'] > 0 ? '+' : ''; ?><?php echo (int)$g['dif']; ?> d · <?php echo (int)$g['movidas']; ?> cambian</small>
                </a>
            <?php endforeach; ?>
            <a class="prg-ia-todas" href="/programador?proyecto=<?php echo (int)$proyecto->id; ?>"><?php echo s(t('Abrir el Programador')); ?> <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </div>
    </details>
<?php endif; ?>
