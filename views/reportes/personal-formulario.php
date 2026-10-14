<?php $esNuevo = !$persona->id; $id = s($proyecto->id); ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo personal' : 'Editar personal'; ?></h2>
    <a href="/reportes/personal?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/reportes/personal/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($persona->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($persona->nombre); ?>" required></div>
            <div class="campo">
                <label for="departamento">Departamento</label>
                <input type="text" id="departamento" name="departamento" maxlength="100" list="lista-deptos" value="<?php echo s($persona->departamento); ?>">
                <datalist id="lista-deptos"><?php foreach ($departamentos as $d): ?><option value="<?php echo s($d); ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="campo campo-full">
                <label for="imagen">Imagen</label>
                <input type="file" id="imagen" name="imagen" accept="image/*">
                <?php if ($persona->imagen): ?>
                    <img class="rd-avatar" src="/uploads/personal/<?php echo s($persona->imagen); ?>" alt="" style="margin-top:.5rem">
                    <label class="text-sm"><input type="checkbox" name="quitar_imagen" value="1" style="width:auto"> Quitar imagen</label>
                <?php endif; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
