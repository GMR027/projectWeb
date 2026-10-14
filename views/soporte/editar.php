<div class="page-header">
    <h2>Editar mensaje de soporte</h2>
    <a href="/soporte" class="btn btn-ghost">← Volver</a>
</div>
<div class="card">
    <form method="POST" action="/soporte/editar?id=<?php echo s($soporte->id); ?>" class="formulario">
        <div class="campo">
            <label>Nombre de usuario</label>
            <input type="text" value="<?php echo s($_SESSION['nombre'] ?? ''); ?>" disabled>
        </div>
        <div class="campo">
            <label for="tema">Tema</label>
            <input type="text" id="tema" name="tema" maxlength="200" value="<?php echo s($soporte->tema); ?>" required>
        </div>
        <div class="campo">
            <label for="informacion">Información</label>
            <textarea id="informacion" name="informacion" rows="8" maxlength="5000" required><?php echo s($soporte->informacion); ?></textarea>
        </div>
        <?php foreach (($alertas['error'] ?? []) as $e): ?><div class="alerta alerta-error"><?php echo s($e); ?></div><?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </form>
</div>
