<?php
/** Datos fiscales y logo de la organización (solo admin). El plan y el estado se editan en la ficha. */
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-building"></i> Datos de <?php echo s($org->nombre); ?></h2>
        <p class="text-muted"><?php echo $propietario ? 'Propietario: ' . s($propietario->nombre) . ' · ' . s($propietario->email) : 'Sin propietario'; ?></p>
    </div>
    <div class="acciones">
        <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>#plan" class="btn btn-ghost"><i class="fa-solid fa-layer-group"></i> Plan y estado</a>
        <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" class="btn btn-ghost">Ver ficha</a>
        <a href="/organizaciones" class="btn btn-ghost">← Volver</a>
    </div>
</div>

<div class="card">
    <div class="card-titulo" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap">
        <span><i class="fa-solid fa-id-card"></i> Datos de la empresa</span>
        <span>
            <span class="badge"><?php echo s($plan['nombre']); ?></span>
            <span class="badge <?php echo s($org->estadoBadge()); ?>"><?php echo s($org->estadoEtiqueta()); ?></span>
        </span>
    </div>
    <p class="text-muted text-sm" style="margin-top:.6rem">El plan, el estado y los topes se cambian desde la ficha, en <strong>Plan y estado</strong>. Aquí solo se editan los datos fiscales y el logo.</p>

    <form method="POST" action="/organizaciones/editar" class="formulario" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
        <?php include __DIR__ . '/_form.php'; ?>
        <div style="display:flex; gap:.6rem; margin-top:1.4rem">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar datos</button>
            <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
