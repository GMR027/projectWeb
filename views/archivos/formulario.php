<?php $esNuevo = !$archivo->id; $id = s($proyecto->id); ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Subir archivo' : 'Editar archivo'; ?></h2>
    <a href="/archivos?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/archivos/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($archivo->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($archivo->fecha); ?>" required></div>
            <div class="campo">
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id">
                    <option value="">— Sin categoría —</option>
                    <?php foreach ($categorias as $c): ?><option value="<?php echo s($c->id); ?>" <?php echo (int)$archivo->categoria_id === (int)$c->id ? 'selected' : ''; ?>><?php echo s($c->nombre); ?></option><?php endforeach; ?>
                </select>
                <?php if (!$categorias): ?><small class="text-muted">Aún no hay categorías. <a href="/archivos/categorias/crear?proyecto=<?php echo $id; ?>">Crea la primera</a>.</small><?php endif; ?>
            </div>
            <div class="campo"><label for="nombre">Nombre del archivo *</label><input type="text" id="nombre" name="nombre" maxlength="200" value="<?php echo s($archivo->nombre); ?>" required></div>
            <div class="campo"><label for="numero_revision">Número de revisión</label><input type="text" id="numero_revision" name="numero_revision" maxlength="30" value="<?php echo s($archivo->numero_revision); ?>" placeholder="Ej: 0, A, 02"></div>
            <div class="campo campo-full">
                <label for="archivo"><?php echo $esNuevo ? 'Archivo *' : 'Reemplazar archivo (opcional)'; ?></label>
                <input type="file" id="archivo" name="archivo" <?php echo $esNuevo ? 'required' : ''; ?> accept="<?php echo s('.' . implode(',.', $extensiones)); ?>">
                <small class="text-muted">Máx. 100 MB. Permitidos: pdf, Word, Excel, PowerPoint, XML, LibreOffice (odt, ods, odp, odg, otg…), imágenes (jpg, png, gif, webp…), dwg/dxf, zip y similares.</small>
                <?php if (!$esNuevo): ?><div class="text-sm" style="margin-top:.5rem">Actual: <span class="mono"><?php echo s($archivo->archivo_original); ?></span> · <?php echo s($archivo->tamanoLegible()); ?></div><?php endif; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
