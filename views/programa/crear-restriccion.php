<div class="page-header">
    <h2>Nueva Restricción — <?php echo s($actividad->codigo); ?></h2>
    <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <div class="alerta alerta-info">
        <strong>Actividad:</strong> <?php echo s($actividad->nombre); ?>
    </div>
    <form method="POST" class="formulario">
        <div class="form-grid">
            <div class="campo">
                <label for="tipo_id">Tipo de Restricción *</label>
                <select id="tipo_id" name="tipo_id" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($tipos as $tipo): ?>
                        <option value="<?php echo s($tipo->id); ?>" <?php echo $restriccion->tipo_id == $tipo->id ? 'selected' : ''; ?>>
                            <?php echo s($tipo->nombre); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="codigo">Código (ej: RFI-04)</label>
                <input type="text" id="codigo" name="codigo" value="<?php echo s($restriccion->codigo); ?>">
            </div>
            <div class="campo">
                <label for="fecha">Fecha de la Restricción</label>
                <input type="date" id="fecha" name="fecha" value="<?php echo s($restriccion->fecha); ?>">
            </div>
            <div class="campo campo-full">
                <label for="descripcion">Descripción *</label>
                <textarea id="descripcion" name="descripcion" rows="3" required><?php echo s($restriccion->descripcion); ?></textarea>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Restricción</button>
    </form>
</div>
