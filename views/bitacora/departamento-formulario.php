<?php $esNuevo = !$depto->id; $id = s($proyecto->id); ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo departamento' : 'Editar departamento'; ?></h2>
    <a href="/bitacora/departamentos?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/bitacora/departamentos/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($depto->id); ?>"><?php endif; ?>
        <div class="campo">
            <label for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" maxlength="100" value="<?php echo s($depto->nombre); ?>" required autofocus>
            <?php if (!$esNuevo): ?><small class="text-muted">Al renombrarlo se actualizan también los registros de la bitácora que lo usan.</small><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
