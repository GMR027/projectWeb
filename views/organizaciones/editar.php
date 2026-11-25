<?php
/** Datos fiscales y logo de la organización (solo admin). El plan y el estado se editan en la ficha. */
?>
<div class="page-header">
    <div>
        <span class="adm-eyebrow">Ficha de organización</span>
        <h2><i class="fa-solid fa-building"></i> Datos de <?php echo s($org->nombre); ?></h2>
        <p class="text-muted"><?php echo $propietario ? 'Propietario: ' . s($propietario->nombre) . ' · ' . s($propietario->email) : 'Sin propietario'; ?></p>
        <div class="adm-meta">
            <span class="badge"><?php echo s($plan['nombre']); ?></span>
            <span class="badge <?php echo s($org->estadoBadge()); ?>"><?php echo s($org->estadoEtiqueta()); ?></span>
            <span><i class="fa-solid fa-arrow-left"></i> <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" style="color:inherit">Volver a la ficha</a></span>
        </div>
    </div>
    <div class="la-controles">
        <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>#plan" class="btn btn-secondary"><i class="fa-solid fa-layer-group"></i> Plan y estado</a>
        <a href="/organizaciones" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Organizaciones</a>
    </div>
</div>

<div class="ctl-form-grid">
<div>
<div class="card">
    <div class="card-titulo" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap">
        <span><i class="fa-solid fa-id-card"></i> Datos de la empresa</span>
        <span>
            <span class="badge"><?php echo s($plan['nombre']); ?></span>
            <span class="badge <?php echo s($org->estadoBadge()); ?>"><?php echo s($org->estadoEtiqueta()); ?></span>
        </span>
    </div>
    <p class="text-muted text-sm" style="margin-top:.6rem">El plan, el estado y los topes se cambian desde la ficha, en <strong>Plan y estado</strong>. Aquí solo se editan los datos fiscales y el logo.</p>

    <form method="POST" action="/organizaciones/editar" class="formulario ctl-form" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo (int)$org->id; ?>">
        <div class="ctl-bloque">
            <div class="ctl-bloque-cab">
                <i class="fa-solid fa-building"></i>
                <div><h4>Identidad fiscal</h4><p>Estos datos se usan en la ficha y en la facturación manual.</p></div>
            </div>
            <?php include __DIR__ . '/_form.php'; ?>
        </div>
        <div class="ctl-form-pie">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar datos</button>
            <a href="/organizaciones/ver?id=<?php echo (int)$org->id; ?>" class="btn btn-ghost">Cancelar</a>
        </div>
    </form>
</div>
</div>
<aside class="ctl-aside">
    <div class="ctl-aside-card">
        <h4><i class="fa-solid fa-user-tie"></i> Propietario</h4>
        <?php if ($propietario): ?>
            <div class="ctl-identidad">
                <span class="ctl-avatar"><?php echo s(iniciales_nombre($propietario->nombre)); ?></span>
                <div class="ctl-id-txt"><strong><?php echo s($propietario->nombre); ?></strong><span class="ctl-id-sub"><?php echo s($propietario->email); ?></span></div>
            </div>
        <?php else: ?>
            <p class="text-muted text-sm">Sin propietario. Agrégalo desde la ficha.</p>
        <?php endif; ?>
    </div>
    <div class="ctl-aside-card">
        <h4><i class="fa-solid fa-circle-info"></i> Qué se edita aquí</h4>
        <p>Solo los datos fiscales y el logo. El plan, los topes, el estado y la facturación manual se administran en la ficha para no tener dos lugares que se pisen.</p>
    </div>
</aside>
</div>
