<?php // Variables: $proyecto, $lamina, $config ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-pen-ruler"></i> Marcas del plano</h2>
        <p class="text-muted"><?php echo s($lamina->titulo); ?> · <?php echo s($proyecto->nombre); ?></p>
    </div>
    <div class="la-controles">
        <span id="ve-estado" class="text-sm text-muted"></span>
        <button type="button" id="ve-guardar" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        <a href="/vplan/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>" class="btn btn-ghost">← Lámina</a>
    </div>
</div>

<?php include __DIR__ . '/_editor-lienzo.php'; ?>

<section class="card vp-acciones" id="vp-acciones">
    <h3>Acciones</h3>
    <div class="vp-acciones-menu">
        <button type="button" class="btn btn-ghost" id="ve-ampliar"><i class="fa-solid fa-expand"></i> Ampliar</button>
        <a href="/vplan/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($lamina->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-sheet-plastic"></i> Ver lámina</a>
    </div>
    <p class="text-muted text-sm" style="margin-top:1rem">«Ampliar» muestra el plano con todas sus herramientas a pantalla completa para trabajarlo con más espacio.</p>
</section>

<?php include __DIR__ . '/_editor-scripts.php'; ?>
