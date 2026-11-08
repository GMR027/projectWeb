<div class="page-header">
    <div>
        <h2><?php echo s(t(is_admin() ? 'Portafolio de Proyectos' : (is_visitante() ? 'Proyectos Asignados' : 'Mis Proyectos'))); ?></h2>
        <p class="text-muted"><?php echo s(t('{n} proyecto(s) registrado(s)', ['n' => count($proyectos)])); ?></p>
    </div>
    <?php if (!is_visitante()): ?>
        <a href="/portafolio" class="btn btn-ghost"><i class="fa-solid fa-table-columns"></i> Tablero de portafolio</a>
    <?php endif; ?>
    <?php if (puede_crear_proyecto()): ?>
        <a href="/plantillas-proyecto" class="btn btn-ghost"><i class="fa-solid fa-copy"></i> <?php echo s(t('Plantillas')); ?></a>
        <a href="/proyectos/crear" class="btn btn-primary"><?php echo s(t('+ Nuevo Proyecto')); ?></a>
    <?php elseif (($cupo = cupo_proyectos()) !== null && $cupo['limite'] !== null && $cupo['usados'] >= (int)$cupo['limite']): ?>
        <span class="text-muted text-sm" title="Archiva una obra o amplía el plan"><i class="fa-solid fa-lock"></i> Límite de obras activas alcanzado (<?php echo (int)$cupo['usados']; ?>/<?php echo (int)$cupo['limite']; ?>)</span>
    <?php elseif (($cupo = cupo_proyectos()) !== null): ?>
        <span class="text-muted text-sm"><i class="fa-solid fa-lock"></i> Tu plan no permite crear obras o no tienes permiso para hacerlo.</span>
    <?php endif; ?>
</div>

<div class="grid-proyectos">
    <?php if (empty($proyectos)): ?>
        <div class="card"><?php echo estado_vacio(
            'fa-diagram-project',
            is_visitante() ? 'Aún no tienes proyectos asignados' : 'Aún no hay proyectos',
            is_visitante() ? 'Solicita acceso al administrador para consultar el programa, el avance y los documentos.' : 'Crea el primero para empezar a programar partidas, actividades y el Gantt.',
            !is_visitante() && puede_crear_proyecto() ? ['texto' => 'Crear proyecto', 'url' => '/proyectos/crear', 'icono' => 'fa-plus'] : null
        ); ?></div>
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
                    <?php if ($proyecto->estaArchivado()): ?>
                        <span class="badge badge-warn" title="Archivada: no cuenta para el cupo de obras activas, pero conserva su almacenamiento">Archivada</span>
                    <?php endif; ?>
                    <p class="text-muted text-sm"><?php echo s($proyecto->ubicacion); ?></p>
                    <div class="progreso-row">
                        <span class="text-sm"><?php echo s(t('Avance')); ?></span>
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
                    <a href="<?php echo s(url_inicio_proyecto((int)$proyecto->id)); ?>" class="btn btn-sm btn-primary"><?php echo s(t('Ver Proyecto')); ?></a>
                    <?php if (puede_gestionar_proyecto($proyecto)): ?>
                        <a href="/proyectos/editar?id=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Editar')); ?></a>
                        <?php if (puede_archivar_proyecto($proyecto)): ?>
                            <?php if ($proyecto->estaArchivado()): ?>
                                <form class="inline" method="POST" action="/proyectos/desarchivar">
                                    <input type="hidden" name="id" value="<?php echo s($proyecto->id); ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost" title="Volver a contar como obra activa">Desarchivar</button>
                                </form>
                            <?php else: ?>
                                <form class="inline" method="POST" action="/proyectos/archivar" data-confirm="¿Archivar obra?|Dejará de contar para el cupo de obras activas, pero conservará todos sus datos y almacenamiento. Puedes desarchivarla si hay cupo.">
                                    <input type="hidden" name="id" value="<?php echo s($proyecto->id); ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost" title="Liberar un lugar sin borrar la obra">Archivar</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (puede_eliminar_proyecto($proyecto)): ?>
                        <form class="inline" method="POST" action="/proyectos/eliminar" data-confirm="¿Eliminar proyecto?|Se eliminarán todas sus partidas, actividades, restricciones y calendario. Esta acción no se puede deshacer.">
                            <input type="hidden" name="id" value="<?php echo s($proyecto->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?php echo s(t('Eliminar')); ?></button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php unset($proyecto); // el layout no debe heredar el último proyecto del listado ?>
    <?php endif; ?>
</div>
