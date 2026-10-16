<div class="page-header">
    <div>
        <h2><?php echo s($proyecto->nombre); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->codigo); ?> · <?php echo s($proyecto->ubicacion); ?></p>
    </div>
    <div>
        <a href="<?php echo s(url_inicio_proyecto((int)$proyecto->id)); ?>" class="btn btn-primary">Ver Proyecto</a>
        <a href="/proyectos" class="btn btn-ghost">← Volver</a>
    </div>
</div>

<div class="card">
    <div class="proyecto-detalle">
        <div class="detalle-seccion">
            <h4>Descripción</h4>
            <p><?php echo nl2br(s($proyecto->descripcion)); ?></p>
        </div>
        <div class="detalle-seccion">
            <h4>Periodo</h4>
            <p><?php echo formatear_fecha($proyecto->fecha_inicio); ?> → <?php echo formatear_fecha($proyecto->fecha_fin); ?></p>
        </div>
        <div class="detalle-seccion">
            <h4>Progreso</h4>
            <div class="barra-progreso">
                <div class="barra-fill" style="width: <?php echo s($proyecto->getProgreso()); ?>%"></div>
            </div>
            <p class="mono"><?php echo s($proyecto->getProgreso()); ?>%</p>
        </div>
    </div>
</div>
