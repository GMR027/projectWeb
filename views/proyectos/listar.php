<div class="page-header">
    <div>
        <h2><?php echo is_admin() ? 'Portafolio de Proyectos' : (is_visitante() ? 'Proyectos Asignados' : 'Mis Proyectos'); ?></h2>
        <p class="text-muted"><?php echo s(count($proyectos)); ?> proyecto(s) registrado(s)</p>
    </div>
    <?php if (puede_crear_proyecto()): ?>
        <a href="/proyectos/crear" class="btn btn-primary">+ Nuevo Proyecto</a>
    <?php elseif (($cupo = cupo_proyectos()) !== null): ?>
        <span class="text-muted text-sm" title="Solicita al administrador ampliar tu límite"><i class="fa-solid fa-lock"></i> Límite de proyectos alcanzado (<?php echo (int)$cupo['usados']; ?>/<?php echo (int)$cupo['limite']; ?>)</span>
    <?php endif; ?>
</div>

<div class="grid-proyectos">
    <?php if (empty($proyectos)): ?>
        <div class="card empty-state">
            <p><?php echo is_visitante() ? 'Aún no tienes proyectos asignados. Solicita acceso al administrador.' : 'No hay proyectos registrados.'; ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($proyectos as $proyecto): ?>
            <div class="card proyecto-card">
                <?php if ($proyecto->caratula): ?>
                    <div class="proyecto-caratula" style="background-image: url('/build/img/caratulas/<?php echo s($proyecto->caratula); ?>');">
                        <div class="proyecto-caratula-overlay">
                            <div class="proyecto-codigo mono"><?php echo s($proyecto->codigo); ?></div>
                            <h4><?php echo s($proyecto->nombre); ?></h4>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="proyecto-header" style="border-left-color: <?php echo s($proyecto->color); ?>">
                        <?php if ($proyecto->logo): ?>
                            <img src="/build/img/logos/<?php echo s($proyecto->logo); ?>" alt="logo" class="proyecto-logo">
                        <?php endif; ?>
                        <div class="proyecto-codigo mono"><?php echo s($proyecto->codigo); ?></div>
                        <h4><?php echo s($proyecto->nombre); ?></h4>
                    </div>
                <?php endif; ?>
                <div class="proyecto-body">
                    <p class="text-muted text-sm"><?php echo s($proyecto->ubicacion); ?></p>
                    <div class="progreso-row">
                        <span class="text-sm">Avance</span>
                        <span class="mono text-accent"><?php echo s($proyecto->getProgreso()); ?>%</span>
                    </div>
                    <div class="barra-progreso">
                        <div class="barra-fill" style="width: <?php echo s($proyecto->getProgreso()); ?>%"></div>
                    </div>
                    <div class="proyecto-meta">
                        <span class="text-sm"><?php echo formatear_fecha($proyecto->fecha_inicio); ?> → <?php echo formatear_fecha($proyecto->fecha_fin); ?></span>
                    </div>
                </div>
                <div class="proyecto-acciones">
                    <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-primary">Ver Proyecto</a>
                    <?php if (puede_gestionar_proyecto($proyecto)): ?>
                        <a href="/proyectos/editar?id=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/proyectos/eliminar" style="display:inline" data-confirm="¿Eliminar proyecto?|Se eliminarán todas sus partidas, actividades, restricciones y calendario. Esta acción no se puede deshacer.">
                            <input type="hidden" name="id" value="<?php echo s($proyecto->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
