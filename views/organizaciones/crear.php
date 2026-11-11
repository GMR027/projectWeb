<?php
/** Alta de una organización (solo admin). Los clientes se agregan después, en su ficha. */
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-building-circle-arrow-right"></i> Nueva organización</h2>
        <p class="text-muted">Crea la empresa y su plan. En el siguiente paso le agregas sus clientes, visitantes y obras.</p>
    </div>
    <a href="/organizaciones" class="btn btn-ghost">← Volver</a>
</div>

<div class="card">
    <form method="POST" action="/organizaciones/crear" class="formulario" enctype="multipart/form-data">
        <?php include __DIR__ . '/_form.php'; ?>
        <div class="form-grid" style="margin-top:1.2rem">
            <div class="campo">
                <label for="plan_id">Plan inicial</label>
                <select id="plan_id" name="plan_id">
                    <?php foreach ($planes as $p): ?>
                        <option value="<?php echo s($p['clave']); ?>" <?php echo $org->planClave() === $p['clave'] ? 'selected' : ''; ?>>
                            <?php echo s($p['nombre']); ?> · <?php echo s(\Classes\Plan::precio($p['clave'])); ?> MXN/mes ·
                            <?php echo $p['obras'] === null ? 'obras ilimitadas' : (int)$p['obras'] . ' obras'; ?> ·
                            <?php echo $p['usuarios'] === null ? 'usuarios ilimitados' : (int)$p['usuarios'] . ' personas'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">La empresa empieza con 14 días de prueba. Después puedes activarla y ajustar sus topes en su ficha.</small>
            </div>
        </div>
        <div style="display:flex; gap:.6rem; margin-top:1.4rem">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Crear organización</button>
            <a href="/organizaciones" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
