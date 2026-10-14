<div class="page-header">
    <h2>Editar Partida — <?php echo s($proyecto->nombre); ?></h2>
    <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <form method="POST" class="formulario">
        <div class="form-grid">
            <div class="campo">
                <label for="codigo">Código WBS *</label>
                <input type="text" id="codigo" name="codigo" value="<?php echo s($partida->codigo); ?>" required>
            </div>
            <div class="campo">
                <label for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo s($partida->nombre); ?>" required>
            </div>
            <div class="campo campo-full">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="3"><?php echo s($partida->descripcion); ?></textarea>
            </div>
            <div class="campo">
                <label for="color_bg">Color de Fondo (opcional)</label>
                <input type="color" id="color_bg" name="color_bg" value="<?php echo s($partida->color_bg ?: '#212942'); ?>">
            </div>
            <div class="campo">
                <label for="orden">Orden</label>
                <input type="number" id="orden" name="orden" value="<?php echo s($partida->orden); ?>" min="0">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
