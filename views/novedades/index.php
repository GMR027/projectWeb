<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-bullhorn"></i> <?php echo s(t('Novedades de la plataforma')); ?></h2>
        <p class="text-muted"><?php echo s(t('Lo último que cambió en ProjectWeb: herramientas nuevas, mejoras y correcciones.')); ?></p>
    </div>
    <div>
        <a href="/dashboard" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> <?php echo s(t('Inicio')); ?></a>
    </div>
</div>

<?php if ($sinVer > 0): ?>
    <p class="text-muted text-sm"><i class="fa-solid fa-circle-info"></i> <?php echo (int)$sinVer; ?> <?php echo s(t('novedades sin ver. Al abrir esta página quedan marcadas como vistas.')); ?></p>
<?php endif; ?>

<?php include __DIR__ . '/../templates/novedades-lista.php'; ?>
