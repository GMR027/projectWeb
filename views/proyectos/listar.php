<?php
/** Lista de proyectos. Mantiene las mismas acciones, permisos y cupos. */
$totalActivos = count(array_filter($proyectos, fn($p) => !$p->estaArchivado()));
$totalArchivados = count($proyectos) - $totalActivos;
$cupo = cupo_proyectos();
?>
<div class="page-header proyectos-hero">
    <div class="proyectos-hero-txt">
        <h2><i class="fa-solid fa-diagram-project" aria-hidden="true"></i> <?php echo s(t(is_admin() ? 'Portafolio de Proyectos' : (is_visitante() ? 'Proyectos Asignados' : 'Mis Proyectos'))); ?></h2>
        <p class="text-muted"><?php echo s(t('{n} proyecto(s) registrado(s)', ['n' => count($proyectos)])); ?></p>
        <div class="proyectos-hero-chips">
            <span class="suite-chip is-primary"><i class="fa-solid fa-folder-open" aria-hidden="true"></i> <?php echo count($proyectos); ?> <?php echo s(t('obras')); ?></span>
            <span class="suite-chip"><i class="fa-solid fa-circle-play" aria-hidden="true"></i> <?php echo (int)$totalActivos; ?> <?php echo s(t('activas')); ?></span>
            <?php if ($totalArchivados): ?>
                <span class="suite-chip"><i class="fa-solid fa-box-archive" aria-hidden="true"></i> <?php echo (int)$totalArchivados; ?> <?php echo s(t('archivadas')); ?></span>
            <?php endif; ?>
            <?php if (!is_admin() && $cupo !== null && $cupo['limite'] !== null): ?>
                <span class="suite-chip"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> <?php echo (int)$cupo['usados']; ?>/<?php echo (int)$cupo['limite']; ?> <?php echo s(t('obras activas')); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="proyectos-hero-acciones">
        <?php if (!is_visitante()): ?>
            <a href="/portafolio" class="btn btn-ghost"><i class="fa-solid fa-table-columns" aria-hidden="true"></i> <?php echo s(t('Portafolio')); ?></a>
        <?php endif; ?>
        <?php if (puede_crear_proyecto()): ?>
            <a href="/plantillas-proyecto" class="btn btn-ghost"><i class="fa-solid fa-copy" aria-hidden="true"></i> <?php echo s(t('Plantillas')); ?></a>
            <a href="/proyectos/crear" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?php echo s(t('Nuevo Proyecto')); ?></a>
        <?php elseif (!is_visitante() && $cupo !== null && $cupo['limite'] !== null && $cupo['usados'] >= (int)$cupo['limite']): ?>
            <span class="suite-chip is-warning" title="<?php echo s(t('Archiva una obra o amplía el plan')); ?>"><i class="fa-solid fa-lock" aria-hidden="true"></i> <?php echo s(t('Límite de obras activas alcanzado')); ?> (<?php echo (int)$cupo['usados']; ?>/<?php echo (int)$cupo['limite']; ?>)</span>
        <?php elseif (!is_visitante()): ?>
            <span class="suite-chip"><i class="fa-solid fa-lock" aria-hidden="true"></i> <?php echo s(t('Tu plan no permite crear obras o no tienes permiso para hacerlo.')); ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="grid-proyectos">
    <?php if (empty($proyectos)): ?>
        <div class="card proyectos-vacio">
            <?php echo estado_vacio(
                'fa-diagram-project',
                is_visitante() ? 'Aún no tienes proyectos asignados' : 'Aún no hay proyectos',
                is_visitante() ? 'Solicita acceso al administrador para consultar el programa, el avance y los documentos.' : 'Crea el primero para empezar a programar partidas, actividades y el Gantt.',
                !is_visitante() && puede_crear_proyecto() ? ['texto' => 'Crear proyecto', 'url' => '/proyectos/crear', 'icono' => 'fa-plus'] : null
            ); ?>
        </div>
    <?php else: ?>
        <?php foreach ($proyectos as $proyecto): ?>
            <?php $cardContexto = 'lista'; include __DIR__ . '/_card.php'; ?>
        <?php endforeach; ?>
        <?php unset($proyecto, $cardContexto); // el layout no debe heredar el último proyecto del listado ?>
    <?php endif; ?>
</div>
