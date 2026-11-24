<?php if (!es_modal()): ?>
<div class="page-header">
    <h2><i class="fa-solid fa-folder-plus" aria-hidden="true"></i> <?php echo s(t('Nueva partida')); ?> — <?php echo s($proyecto->nombre); ?></h2>
    <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← <?php echo s(t('Volver')); ?></a>
</div>
<?php endif; ?>

<div class="card prg-form">
    <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error" role="alert"><?php echo s($e); ?></div><?php endforeach; ?>
    <form method="POST" class="formulario" id="form-partida">
        <div class="prg-form-sec-titulo campo-full"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> <?php echo s(t('Datos de la partida')); ?></div>
        <div class="form-grid">
            <div class="campo">
                <label for="codigo"><?php echo s(t('Código WBS')); ?> *</label>
                <input type="text" id="codigo" name="codigo" value="<?php echo s($partida->codigo); ?>" placeholder="P-01" required>
                <small class="text-muted"><?php echo s(t('Identifica la partida en el desglose y en el Gantt.')); ?></small>
            </div>
            <div class="campo">
                <label for="nombre"><?php echo s(t('Nombre')); ?> *</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo s($partida->nombre); ?>" required>
            </div>
            <div class="campo campo-full">
                <label for="descripcion"><?php echo s(t('Descripción')); ?></label>
                <textarea id="descripcion" name="descripcion" rows="3"><?php echo s($partida->descripcion); ?></textarea>
            </div>
            <div class="campo">
                <label for="color_bg"><?php echo s(t('Color de la partida')); ?></label>
                <input type="color" id="color_bg" name="color_bg" value="<?php echo s($partida->color_bg ?: '#f97316'); ?>">
                <small class="text-muted"><?php echo s(t('Se usa como acento en la tabla y en las barras del Gantt.')); ?></small>
            </div>
            <div class="campo">
                <label for="orden"><?php echo s(t('Orden')); ?></label>
                <input type="number" id="orden" name="orden" value="<?php echo s($partida->orden); ?>" min="0">
            </div>
        </div>
        <div class="prg-form-pie">
            <?php if (es_modal()): ?>
                <button type="button" class="btn btn-ghost" data-cerrar-modal><?php echo s(t('Cancelar')); ?></button>
            <?php else: ?>
                <a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><?php echo s(t('Cancelar')); ?></a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i> <?php echo s(t('Crear partida')); ?></button>
        </div>
    </form>
</div>
