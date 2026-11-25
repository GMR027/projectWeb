<?php
/**
 * Tarjeta de proyecto compartida por el inicio y la lista de proyectos.
 * Variables: $proyecto (Model\Proyecto); $cardContexto = 'inicio' | 'lista' (por defecto 'lista').
 * Mantiene los mismos enlaces y formularios que tenía cada pantalla.
 */
$cardContexto = $cardContexto ?? 'lista';
$progreso = (float)$proyecto->getProgreso();
$archivado = $proyecto->estaArchivado();
$color = $proyecto->color ?: '#f97316';
$caratula = $cardContexto === 'lista' && $proyecto->caratula;
$hoyISO = date('Y-m-d');
if ($archivado) {
    $estado = ['Archivada', 'archivada'];
} elseif (!empty($proyecto->fecha_inicio) && $proyecto->fecha_inicio > $hoyISO) {
    $estado = ['Por iniciar', 'espera'];
} elseif (!empty($proyecto->fecha_fin) && $proyecto->fecha_fin < $hoyISO) {
    $estado = ['Finalizada', 'fin'];
} else {
    $estado = ['En ejecución', 'ok'];
}
?>
<article class="card proyecto-card proyecto-card--<?php echo s($cardContexto); ?><?php echo $archivado ? ' proyecto-card--archivada' : ''; ?>" style="--proyecto-color: <?php echo s($color); ?>">
    <?php if ($caratula): ?>
        <div class="proyecto-caratula" style="background-image: url('/build/img/caratulas/<?php echo s($proyecto->caratula); ?>');">
            <span class="proyecto-estado proyecto-estado--<?php echo s($estado[1]); ?> proyecto-estado--flotante"><span aria-hidden="true"></span><?php echo s(t($estado[0])); ?></span>
            <div class="proyecto-caratula-overlay">
                <span class="proyecto-codigo mono"><?php echo s($proyecto->codigo); ?></span>
                <h4><?php echo s($proyecto->nombre); ?></h4>
            </div>
        </div>
    <?php else: ?>
        <div class="proyecto-cab">
            <span class="proyecto-ident">
                <span class="proyecto-cab-fila">
                    <span class="proyecto-codigo mono"><?php echo s($proyecto->codigo); ?></span>
                    <span class="proyecto-estado proyecto-estado--<?php echo s($estado[1]); ?>"><span aria-hidden="true"></span><?php echo s(t($estado[0])); ?></span>
                </span>
                <h4><?php echo s($proyecto->nombre); ?></h4>
                <?php if ($proyecto->ubicacion): ?>
                    <span class="proyecto-ubicacion text-muted text-sm"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?php echo s($proyecto->ubicacion); ?></span>
                <?php endif; ?>
            </span>
            <?php if ($proyecto->logo): ?>
                <img src="/build/img/logos/<?php echo s($proyecto->logo); ?>" alt="<?php echo s($proyecto->nombre); ?>" class="proyecto-logo">
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="proyecto-body">
        <div class="proyecto-avance">
            <span class="proyecto-anillo" style="--pct: <?php echo min(100, max(0, $progreso)); ?>" role="img" aria-label="<?php echo s(t('Avance General')); ?>: <?php echo s(number_format($progreso, 0)); ?>%">
                <span class="mono"><?php echo s(number_format($progreso, 0)); ?>%</span>
            </span>
            <span class="proyecto-avance-txt">
                <span class="text-sm text-muted"><?php echo s(t('Avance General')); ?></span>
                <span class="proyecto-fechas mono">
                    <span title="<?php echo s(t('Inicio')); ?>"><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo s(fecha_corta_es($proyecto->fecha_inicio)); ?></span>
                    <span class="proyecto-fechas-sep" aria-hidden="true">→</span>
                    <span title="<?php echo s(t('Fin')); ?>"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> <?php echo s(fecha_corta_es($proyecto->fecha_fin)); ?></span>
                </span>
            </span>
        </div>
        <div class="barra-progreso barra-proyecto" aria-hidden="true">
            <div class="barra-fill" style="width: <?php echo min(100, max(0, $progreso)); ?>%"></div>
        </div>
    </div>

    <?php if ($cardContexto === 'inicio'): ?>
        <?php $saludCompacto = true; include __DIR__ . '/../salud/_semaforo.php'; ?>
        <?php include __DIR__ . '/../revisiones/_mini.php'; ?>
    <?php endif; ?>

    <div class="proyecto-acciones">
        <a href="<?php echo s(url_inicio_proyecto((int)$proyecto->id)); ?>" class="btn btn-sm btn-primary">
            <?php echo s(t('Ver Proyecto')); ?>
        </a>
        <?php if ($cardContexto === 'inicio'): ?>
            <?php if (puede_descargar()): ?>
                <a href="/ajustes?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-ghost" title="<?php echo s(t('Ajustes')); ?>">
                    <i class="fa-solid fa-gear" aria-hidden="true"></i><span class="hide-m"> <?php echo s(t('Ajustes')); ?></span>
                </a>
            <?php endif; ?>
        <?php else: ?>
            <?php if (puede_gestionar_proyecto($proyecto)): ?>
                <a href="/proyectos/editar?id=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-ghost">
                    <i class="fa-solid fa-pen" aria-hidden="true"></i> <?php echo s(t('Editar')); ?>
                </a>
                <?php if (puede_archivar_proyecto($proyecto)): ?>
                    <?php if ($archivado): ?>
                        <form class="inline" method="POST" action="/proyectos/desarchivar">
                            <input type="hidden" name="id" value="<?php echo s($proyecto->id); ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" title="<?php echo s(t('Volver a contar como obra activa')); ?>">
                                <i class="fa-solid fa-box-open" aria-hidden="true"></i> <?php echo s(t('Desarchivar')); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form class="inline" method="POST" action="/proyectos/archivar" data-confirm="<?php echo s(t('¿Archivar obra?|Dejará de contar para el cupo de obras activas, pero conservará todos sus datos y almacenamiento. Puedes desarchivarla si hay cupo.')); ?>">
                            <input type="hidden" name="id" value="<?php echo s($proyecto->id); ?>">
                            <button type="submit" class="btn btn-sm btn-ghost" title="<?php echo s(t('Liberar un lugar sin borrar la obra')); ?>">
                                <i class="fa-solid fa-box-archive" aria-hidden="true"></i> <?php echo s(t('Archivar')); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (puede_eliminar_proyecto($proyecto)): ?>
                    <form class="inline" method="POST" action="/proyectos/eliminar" data-confirm="<?php echo s(t('¿Eliminar proyecto?|Se eliminarán todas sus partidas, actividades, restricciones y calendario. Esta acción no se puede deshacer.')); ?>">
                        <input type="hidden" name="id" value="<?php echo s($proyecto->id); ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="<?php echo s(t('Eliminar')); ?>">
                            <i class="fa-solid fa-trash" aria-hidden="true"></i><span class="hide-m"> <?php echo s(t('Eliminar')); ?></span>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</article>
