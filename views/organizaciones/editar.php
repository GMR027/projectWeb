<?php
/** Edición de una organización: plan, estado, fechas y datos fiscales (solo admin). */
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-building"></i> <?php echo s($org->nombre); ?></h2>
        <p class="text-muted"><?php echo $propietario ? 'Propietario: ' . s($propietario->nombre) . ' · ' . s($propietario->email) : 'Sin propietario'; ?></p>
    </div>
    <div class="acciones">
        <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" class="btn btn-ghost">Ver ficha</a>
        <a href="/organizaciones" class="btn btn-ghost">← Volver</a>
    </div>
</div>

<div class="card">
    <div class="grid-proyectos" style="margin-bottom:1.6rem">
        <div><span class="text-muted text-sm">Plan</span><br><strong><?php echo s($plan['nombre']); ?></strong></div>
        <div><span class="text-muted text-sm">Obras activas</span><br><strong><?php echo (int)$obras; ?> / <?php echo $limites['obras'] === null ? '∞' : (int)$limites['obras']; ?></strong></div>
        <div><span class="text-muted text-sm">Asientos</span><br><strong><?php echo (int)$asientos; ?> / <?php echo $limites['usuarios'] === null ? '∞' : (int)$limites['usuarios']; ?></strong></div>
        <div><span class="text-muted text-sm">Almacenamiento</span><br><strong><?php echo \Classes\Almacenamiento::formato((int)$almacenamiento); ?> / <?php echo $limite_almacenamiento === null ? 'sin tope' : \Classes\Almacenamiento::formato((int)$limite_almacenamiento); ?></strong></div>
    </div>

    <form method="POST" action="/organizaciones/editar" class="formulario" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
        <?php include __DIR__ . '/_form.php'; ?>
        <div style="display:flex; gap:.6rem; margin-top:1.4rem">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar organización</button>
            <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
