<?php $esNuevo = !$cat->id; $id = s($proyecto->id); ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nueva categoría' : 'Editar categoría'; ?></h2>
    <a href="/archivos/categorias?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/archivos/categorias/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($cat->id); ?>"><?php endif; ?>
        <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="100" value="<?php echo s($cat->nombre); ?>" required autofocus></div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
