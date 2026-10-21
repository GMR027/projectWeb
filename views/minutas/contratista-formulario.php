<?php $esNuevo = !$persona->id; $id = s($proyecto->id); $deDirectorio = ($_REQUEST['volver'] ?? '') === 'directorio'; ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo personal del contratista' : 'Editar personal del contratista'; ?></h2>
    <a href="<?php echo $deDirectorio ? "/directorio?tab=contratista&proyecto=$id" : "/minutas/contratista?proyecto=$id"; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/minutas/contratista/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if ($deDirectorio): ?><input type="hidden" name="volver" value="directorio"><?php endif; ?>
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($persona->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo campo-full"><label for="nombre">Nombre *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($persona->nombre); ?>" required autofocus></div>
            <div class="campo"><label for="puesto">Puesto</label><input type="text" id="puesto" name="puesto" maxlength="100" value="<?php echo s($persona->puesto); ?>"></div>
            <div class="campo"><label for="empresa">Empresa</label><input type="text" id="empresa" name="empresa" maxlength="150" value="<?php echo s($persona->empresa ?: ($esNuevo ? $proyecto->contratista : '')); ?>"></div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
