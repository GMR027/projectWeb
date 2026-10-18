<div class="dashboard-hero">
    <h2>Bienvenido, <?php echo s(explode(' ', $usuario['nombre'])[0]); ?></h2>
    <p class="text-muted">Panel de control de programas de obra</p>
</div>

<div class="grid-stats">
    <div class="stat-card">
        <div class="stat-label">Proyectos</div>
        <div class="stat-valor"><?php echo s($totalProyectos); ?></div>
    </div>
</div>

<div class="page-header">
    <h3>Proyectos Recientes</h3>
    <?php if (puede_crear_proyecto()): ?>
        <a href="/proyectos/crear" class="btn btn-primary">+ Nuevo Proyecto</a>
    <?php endif; ?>
</div>

<div class="grid-proyectos">
    <?php if (empty($proyectos)): ?>
        <div class="card empty-state">
            <p><?php echo is_visitante() ? 'Aún no tienes proyectos asignados.' : 'No hay proyectos registrados aún.'; ?></p>
            <?php if (puede_crear_proyecto()): ?>
                <a href="/proyectos/crear" class="btn btn-primary">Crear el primero</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach (array_slice($proyectos, 0, 6) as $proyecto): ?>
            <div class="card proyecto-card">
                <div class="proyecto-header" style="border-left-color: <?php echo s($proyecto->color); ?>">
                    <div class="proyecto-codigo mono"><?php echo s($proyecto->codigo); ?></div>
                    <h4><?php echo s($proyecto->nombre); ?></h4>
                    <p class="text-muted text-sm"><?php echo s($proyecto->ubicacion); ?></p>
                </div>
                <div class="proyecto-body">
                    <div class="progreso-row">
                        <span class="text-sm">Avance General</span>
                        <span class="mono text-accent"><?php echo s($proyecto->getProgreso()); ?>%</span>
                    </div>
                    <div class="barra-progreso">
                        <div class="barra-fill" style="width: <?php echo s($proyecto->getProgreso()); ?>%"></div>
                    </div>
                    <div class="proyecto-meta">
                        <span class="text-sm">Inicio: <?php echo formatear_fecha($proyecto->fecha_inicio); ?></span>
                        <span class="text-sm">Fin: <?php echo formatear_fecha($proyecto->fecha_fin); ?></span>
                    </div>
                </div>
                <?php include __DIR__ . '/../revisiones/_mini.php'; ?>
                <div class="proyecto-acciones">
                    <a href="<?php echo s(url_inicio_proyecto((int)$proyecto->id)); ?>" class="btn btn-sm btn-primary">Ver Proyecto</a>
                    <?php if (puede_descargar()): ?>
                        <a href="/ajustes?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-ghost">Ajustes</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (count($proyectos) > 6): ?>
    <div class="text-center mt-3">
        <a href="/proyectos" class="btn btn-ghost">Ver todos (<?php echo s(count($proyectos)); ?>)</a>
    </div>
<?php endif; ?>
