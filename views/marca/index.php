<?php
/** Marca del cliente. Variables: $proyecto, $marca [disponible, activa, nombre, logo, color, color2], $ok, $error */
$pid = (int)$proyecto->id;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-palette"></i> <?php echo s(t('Marca del cliente')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · el logo, el nombre y los colores del cliente salen en la cabecera de los PDF de reportes (programa, Lookahead, Last Planner, curvas, MSR, submittals, minutas, reportes diarios, punch, informes y permisos). Las láminas del vPlan y los documentos del Redactor llevan su propio diseño.</p>
    </div>
</div>
<?php if (!$marca['disponible']): ?><div class="card"><p><?php echo s(t('Falta aplicar la migración')); ?> <code>database/migracion_personalizacion_columnas.sql</code>.</p></div><?php return; endif; ?>
<?php if ($error !== ''): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php elseif ($ok): ?><div class="alerta alerta-exito"><?php echo s(t('Marca guardada.')); ?></div><?php endif; ?>
<div class="card">
    <form method="POST" action="/marca/guardar" enctype="multipart/form-data" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <label class="campo-check campo-full"><input type="checkbox" name="activa" value="1"<?php echo $marca['activa'] ? ' checked' : ''; ?>> <?php echo s(t('Poner la marca del cliente en los PDF de este proyecto')); ?></label>
        <div class="form-grid">
            <div class="campo"><label for="mc-nombre"><?php echo s(t('Nombre que se muestra')); ?></label><input type="text" id="mc-nombre" name="nombre" maxlength="150" value="<?php echo s($marca['nombre']); ?>" placeholder="<?php echo s($proyecto->empresa ?: $proyecto->nombre); ?>"></div>
            <div class="campo"><label for="mc-logo"><?php echo s(t('Logo')); ?></label><input type="file" id="mc-logo" name="marca_logo" accept="image/png,image/jpeg,image/webp,image/gif">
                <?php if ($marca['logo']): ?><small class="text-muted"><img src="/build/img/logos/<?php echo s($marca['logo']); ?>" alt="Logo actual" style="max-height:3.6rem;vertical-align:middle"> <label><input type="checkbox" name="quitar_logo" value="1"> <?php echo s(t('Quitar logo')); ?></label></small><?php endif; ?></div>
            <div class="campo"><label for="mc-color"><?php echo s(t('Color principal')); ?></label><input type="color" id="mc-color" name="color" value="<?php echo s($marca['color']); ?>"><small class="text-muted"><?php echo s(t('Títulos y encabezados de las tablas')); ?></small></div>
            <div class="campo"><label for="mc-color2"><?php echo s(t('Color secundario')); ?></label><input type="color" id="mc-color2" name="color2" value="<?php echo s($marca['color2']); ?>"><small class="text-muted"><?php echo s(t('Línea bajo la cabecera')); ?></small></div>
        </div>
        <h3 class="mt-2"><?php echo s(t('Vista previa de la cabecera')); ?></h3>
        <div class="mc-previa" id="mc-previa">
            <div class="mc-banda" id="mc-banda" style="border-bottom:3px solid <?php echo s($marca['color']); ?>">
                <span class="mc-logo"><?php if ($marca['logo']): ?><img src="/build/img/logos/<?php echo s($marca['logo']); ?>" alt=""><?php endif; ?></span>
                <strong id="mc-nombre-prev" style="color:<?php echo s($marca['color']); ?>"><?php echo s($marca['nombre'] ?: ($proyecto->empresa ?: $proyecto->nombre)); ?></strong>
                <small><?php echo s($proyecto->nombre); ?></small>
            </div>
            <div class="mc-linea2" id="mc-linea2" style="background:<?php echo s($marca['color2']); ?>"></div>
            <table class="mc-tabla"><thead><tr id="mc-th"><th style="background:<?php echo s($marca['color']); ?>"><?php echo s(t('Actividad')); ?></th><th style="background:<?php echo s($marca['color']); ?>"><?php echo s(t('Inicio')); ?></th><th style="background:<?php echo s($marca['color']); ?>"><?php echo s(t('Fin')); ?></th></tr></thead></table>
        </div>
        <p style="margin-top:2rem"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> <?php echo s(t('Guardar marca')); ?></button></p>
    </form>
</div>
<script>
(() => {
    const c1 = document.getElementById('mc-color'), c2 = document.getElementById('mc-color2'), nom = document.getElementById('mc-nombre');
    const pintar = () => {
        document.getElementById('mc-banda').style.borderBottomColor = c1.value;
        document.getElementById('mc-nombre-prev').style.color = c1.value;
        document.getElementById('mc-linea2').style.background = c2.value;
        document.querySelectorAll('#mc-th th').forEach((th) => { th.style.background = c1.value; });
        document.getElementById('mc-nombre-prev').textContent = nom.value || nom.placeholder;
    };
    [c1, c2, nom].forEach((e) => e.addEventListener('input', pintar));
})();
</script>
