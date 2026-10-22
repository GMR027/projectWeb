<?php
/** Campos personalizados del proyecto. Variables: $proyecto, $campos [id => fila], $error, $ok */
use Classes\CamposPersonalizados as CP;
use Classes\ListaDiseno;

$pid = (int)$proyecto->id;
$rutas = ['actividad' => '/programa', 'punch' => '/punch', 'submittal' => '/submittals', 'bitacora' => '/bitacora', 'reporte' => '/reportes'];
$herr = ['actividad' => 'programa', 'punch' => 'punch', 'submittal' => 'submittals', 'bitacora' => 'bitacora', 'reporte' => 'reportes'];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-tags"></i> <?php echo s(t('Campos personalizados')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · agrega tus propios datos («Nivel», «Frente», «Torre»…) a las herramientas; salen como columnas, se editan en la lista y sirven para filtrar y agrupar</p>
    </div>
</div>
<?php if ($error !== ''): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito"><?php echo s(t('Listo.')); ?></div><?php endif; ?>

<?php foreach (CP::ENTIDADES as $ent => $etq): $lista = array_filter($campos, fn($c) => $c['entidad'] === $ent); ?>
<section class="card cp-seccion">
    <h3><?php echo s($etq); ?> <small class="text-muted">· <?php echo count($lista); ?> de <?php echo CP::MAX_POR_ENTIDAD; ?></small></h3>
    <?php if (!$lista): ?><p class="text-muted"><?php echo s(t('Sin campos todavía.')); ?></p><?php endif; ?>
    <?php foreach ($lista as $c): ?>
        <form method="POST" action="/campos/guardar" class="cp-fila">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
            <input type="text" name="nombre" value="<?php echo s($c['nombre']); ?>" maxlength="60" required aria-label="Nombre">
            <select name="tipo" aria-label="Tipo"><?php foreach (CP::TIPOS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $c['tipo'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select>
            <textarea name="opciones" rows="2" placeholder="Una opción por línea" aria-label="Opciones"<?php echo $c['tipo'] === 'lista' ? '' : ' hidden'; ?>><?php echo s(implode("\n", $c['opciones'])); ?></textarea>
            <button type="submit" class="btn btn-sm btn-primary"><?php echo s(t('Guardar')); ?></button>
            <button type="submit" formaction="/campos/eliminar" class="btn btn-sm btn-danger" formnovalidate data-confirm="¿Eliminar «<?php echo s($c['nombre']); ?>»?|Se borran también los valores guardados en todos los registros.">✕</button>
        </form>
    <?php endforeach; ?>
    <?php if (count($lista) < CP::MAX_POR_ENTIDAD): ?>
        <form method="POST" action="/campos/guardar" class="cp-fila cp-nuevo">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="entidad" value="<?php echo s($ent); ?>">
            <input type="text" name="nombre" maxlength="60" placeholder="Nuevo campo (ej. Nivel)" required aria-label="Nombre del nuevo campo">
            <select name="tipo" aria-label="Tipo"><?php foreach (CP::TIPOS as $k => $n): ?><option value="<?php echo $k; ?>"><?php echo s($n); ?></option><?php endforeach; ?></select>
            <textarea name="opciones" rows="2" placeholder="Una opción por línea" aria-label="Opciones" hidden></textarea>
            <button type="submit" class="btn btn-sm btn-primary"><?php echo s(t('+ Agregar')); ?></button>
        </form>
    <?php endif; ?>
    <p class="text-sm text-muted"><?php echo s(t('Ver en')); ?> <a href="<?php echo $rutas[$ent]; ?>?proyecto=<?php echo $pid; ?>"><?php echo s($etq); ?></a><?php echo $lista ? ' · <a href="' . ($ent === 'actividad' ? '/programa/diseno' : '/lista/diseno') . '?proyecto=' . $pid . ($ent === 'actividad' ? '' : '&herramienta=' . $herr[$ent]) . '">Diseñar columnas</a>' : ''; ?></p>
</section>
<?php endforeach; ?>
<script>
document.querySelectorAll('.cp-fila').forEach((f) => {
    const tipo = f.querySelector('select[name=tipo]'), ops = f.querySelector('textarea');
    const ver = () => { ops.hidden = tipo.value !== 'lista'; ops.required = tipo.value === 'lista'; };
    tipo.addEventListener('change', ver); ver();
});
</script>
