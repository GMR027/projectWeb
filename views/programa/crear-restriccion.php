<div class="page-header">
    <h2><?php echo !empty($editando) ? 'Editar' : 'Nueva'; ?> Restricción — <?php echo s($actividad->codigo); ?><?php if (!empty($subactividad)): ?> · <?php echo s($subactividad->codigo ?: $subactividad->nombre); ?><?php endif; ?></h2>
    <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <div class="alerta alerta-info">
        <strong>Actividad:</strong> <?php echo s($actividad->nombre); ?>
        <?php if (!empty($subactividad)): ?><br><strong>Subactividad:</strong> <?php echo s($subactividad->nombre); ?><?php endif; ?>
    </div>
    <form method="POST" class="formulario">
        <?php if (!empty($volver)): ?><input type="hidden" name="volver" value="<?php echo s($volver); ?>"><?php endif; ?>
        <input type="hidden" name="subactividad_id" value="<?php echo (int)($subactividad->id ?? 0); ?>">
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
            <?php if (!empty($editando)): ?>
            <div class="campo">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <option value="activa" <?php echo $restriccion->estado === 'activa' ? 'selected' : ''; ?>>Activa</option>
                    <option value="resuelta" <?php echo $restriccion->estado === 'resuelta' ? 'selected' : ''; ?>>Resuelta</option>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Restricción</button>
    </form>
</div>
