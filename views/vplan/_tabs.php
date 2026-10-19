<?php // Variables: $proyecto, $tab ('laminas'|'tablas'), $puedeEditar ?>
<div class="vp-tabs">
    <a href="/vplan?proyecto=<?php echo s($proyecto->id); ?>" class="<?php echo $tab === 'laminas' ? 'activo' : ''; ?>"><i class="fa-solid fa-map-location-dot"></i> vPlans</a>
    <?php if (puede_gestionar_proyecto($proyecto)): ?>
        <a href="/vplan/tablas?proyecto=<?php echo s($proyecto->id); ?>" class="<?php echo $tab === 'tablas' ? 'activo' : ''; ?>"><i class="fa-solid fa-table"></i> Tablas adicionales</a>
    <?php endif; ?>
</div>
