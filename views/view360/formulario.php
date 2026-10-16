<?php $esNuevo = !$grafico->id; // Variables: $proyecto, $grafico, $alertas ?>
<div class="page-header">
    <h2><i class="fa-solid fa-street-view"></i> <?php echo $esNuevo ? 'Crear gráfico interactivo' : 'Editar gráfico interactivo'; ?></h2>
    <a href="/view360?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/view360/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario" enctype="multipart/form-data">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo (int)$grafico->id; ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($grafico->fecha); ?>" required></div>
            <div class="campo"><label for="nombre">Nombre del gráfico *</label><input type="text" id="nombre" name="nombre" maxlength="150" value="<?php echo s($grafico->nombre); ?>" placeholder="Ej: Planta baja · recorrido" required></div>
            <div class="campo"><label for="version">Versión</label><input type="text" id="version" name="version" maxlength="30" value="<?php echo s($grafico->version); ?>" placeholder="Ej: v1.0"></div>
            <?php if ($esNuevo): ?>
            <div class="campo campo-full"><label for="fondo">Fondo (archivo de fondo) *</label>
                <input type="file" id="fondo" name="fondo" accept="application/pdf,.pdf,image/png,image/jpeg,image/webp,image/gif" required>
                <small class="text-muted">PDF (se usa la primera página) o imagen, hasta 30 MB. Se carga como fondo del lienzo y se abre el editor para empezar a trabajar.</small></div>
            <?php else: ?>
            <div class="campo campo-full"><a href="/graficos/abrir?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$grafico->grafico_id; ?>" class="btn btn-ghost"><i class="fa-solid fa-pen-ruler"></i> Abrir en el editor</a></div>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo $esNuevo ? 'Crear gráfico' : 'Guardar cambios'; ?></button>
    </form>
</div>
