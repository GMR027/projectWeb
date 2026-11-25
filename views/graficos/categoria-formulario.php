<?php $esNuevo = !$cat->id; $id = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-tags"></i> <?php echo $esNuevo ? 'Nueva categoría' : 'Editar categoría'; ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · categoría de la galería del Editor.</p>
    </div>
    <a href="/graficos/categorias?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="suite-panel">
    <div class="suite-panel-cab"><h3><i class="fa-solid fa-tag"></i> Nombre de la categoría</h3></div>
    <div class="suite-panel-cuerpo">
    <form method="POST" action="/graficos/categorias/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($cat->id); ?>"><?php endif; ?>
        <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="100" value="<?php echo s($cat->nombre); ?>" required autofocus></div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    </form>
    </div>
</div>
