<?php // Variables: $proyecto, $tab ('laminas'|'tablas'), $puedeEditar ?>
<div class="vp-tabs">
    <a href="/vplan?proyecto=<?php echo s($proyecto->id); ?>" class="<?php echo $tab === 'laminas' ? 'activo' : ''; ?>"<?php echo glosario('vplan'); ?>><i class="fa-solid fa-sheet-plastic"></i> vPlans</a>
    <?php if (puede_gestionar_proyecto($proyecto)): ?>
        <a href="/vplan/tablas?proyecto=<?php echo s($proyecto->id); ?>" class="<?php echo $tab === 'tablas' ? 'activo' : ''; ?>"<?php echo glosario('tabla'); ?>><i class="fa-solid fa-table"></i> Tablas adicionales</a>
    <?php endif; ?>
</div>
