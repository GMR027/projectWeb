<?php $esNuevo = !$registro->id; $id = s($proyecto->id); ?>
<div class="page-header">
    <h2><?php echo $esNuevo ? 'Nuevo registro de bitácora' : 'Editar registro de bitácora'; ?></h2>
    <a href="/bitacora?proyecto=<?php echo $id; ?>" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/bitacora/<?php echo $esNuevo ? 'crear' : 'editar'; ?>" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
        <?php if (!$esNuevo): ?><input type="hidden" name="id" value="<?php echo s($registro->id); ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="campo"><label for="fecha">Fecha *</label><input type="date" id="fecha" name="fecha" value="<?php echo s($registro->fecha); ?>" required></div>
            <div class="campo">
                <label for="departamento">Departamento</label>
                <select id="departamento" name="departamento">
                    <option value="">— Sin departamento —</option>
                    <?php foreach ($departamentos as $d): ?><option value="<?php echo s($d); ?>" <?php echo $registro->departamento === $d ? 'selected' : ''; ?>><?php echo s($d); ?></option><?php endforeach; ?>
                </select>
                <?php if (!$departamentos): ?><small class="text-muted">Aún no hay departamentos. <a href="/bitacora/departamentos/crear?proyecto=<?php echo $id; ?>">Crea el primero</a>.</small><?php endif; ?>
            </div>
            <div class="campo">
                <label for="importancia">Nivel de importancia *</label>
                <select id="importancia" name="importancia">
                    <?php foreach (\Model\Bitacora::IMPORTANCIAS as $k => $v): ?>
                        <option value="<?php echo s($k); ?>" <?php echo $registro->importancia === $k ? 'selected' : ''; ?>><?php echo s($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo campo-full"><label for="descripcion">Descripción *</label><textarea id="descripcion" name="descripcion" rows="6" required><?php echo s($registro->descripcion); ?></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
