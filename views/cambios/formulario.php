<?php $esNueva = !$orden->id; $pid = s($proyecto->id); ?>
<div class="page-header">
    <h2><?php echo $esNueva ? 'Nueva orden de cambio' : 'Editar orden ' . s($orden->numero); ?></h2>
    <a href="/cambios?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/cambios/<?php echo $esNueva ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <?php if (!$esNueva): ?><input type="hidden" name="id" value="<?php echo s($orden->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="numero">Número</label><input type="text" id="numero" name="numero" value="<?php echo s($orden->numero); ?>"></div>
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($orden->fecha); ?>" required></div>
            <div class="campo campo-full"><label for="titulo">Título *</label><input type="text" id="titulo" name="titulo" value="<?php echo s($orden->titulo); ?>" required placeholder="Ej.: Reubicación de cámaras de CCTV en nivel 2"></div>
            <div class="campo campo-full"><label for="descripcion">Descripción / justificación</label><textarea id="descripcion" name="descripcion" rows="3"><?php echo s($orden->descripcion); ?></textarea></div>
            <div class="campo"><label for="solicitante">Solicitante</label><input type="text" id="solicitante" name="solicitante" value="<?php echo s($orden->solicitante); ?>"></div>
            <div class="campo"><label for="estado">Estado</label>
                <select id="estado" name="estado"><?php foreach (\Model\OrdenCambio::ESTADOS as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo $orden->estado === $k ? ' selected' : ''; ?>><?php echo s($etq); ?></option><?php endforeach; ?></select>
            </div>
            <div class="campo"><label for="impacto_costo">Impacto en costo ($)</label><input type="number" step="0.01" id="impacto_costo" name="impacto_costo" value="<?php echo s($orden->impacto_costo); ?>"></div>
            <div class="campo"><label for="impacto_dias">Impacto en días</label><input type="number" id="impacto_dias" name="impacto_dias" value="<?php echo s($orden->impacto_dias); ?>"></div>
            <div class="campo campo-full"><label for="actividad_id">Actividad principal afectada</label>
                <select id="actividad_id" name="actividad_id">
                    <option value="">— Ninguna —</option>
                    <?php echo opciones_actividades($actividades, $orden->actividad_id); ?>
                </select>
                <small class="text-muted">Una orden vinculada a una actividad justifica sus desviaciones frente a la línea base.</small>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar orden</button>
    </form>
</div>
