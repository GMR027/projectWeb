<?php // Variables: $proyecto, $x, $actividades, $puede, $mensaje
use Classes\Punch;
$pid = (int)$proyecto->id;
$foto = fn($n) => $n ? '/uploads/' . Punch::CARPETA . '/' . basename($n) : null;
$vencido = $x['fecha_limite'] && $x['fecha_limite'] < date('Y-m-d') && !in_array($x['estado'], ['cerrado', 'rechazado'], true);
?>
<div class="page-header"><div><h2><i class="fa-solid fa-screwdriver-wrench"></i> <?php echo s($x['titulo']); ?></h2><p class="text-muted"><?php echo s($proyecto->nombre); ?> · creado por <?php echo s($x['creado_por'] ?: '—'); ?> el <?php echo s(date('d/m/Y', strtotime($x['creado']))); ?><?php echo $x['fecha_emision'] ? ' · emitido el ' . s(date('d/m/Y', strtotime($x['fecha_emision']))) : ''; ?><?php echo $x['origen'] !== 'manual' ? ' · origen: ' . s($x['origen']) : ''; ?></p>
    <div class="herr-meta">
        <span class="herr-chip"><i class="fa-solid fa-signal"></i> <b><?php echo s(Punch::SEVERIDADES[$x['severidad']] ?? $x['severidad']); ?></b></span>
        <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-circle-dot"></i> <?php echo s(Punch::ESTADOS[$x['estado']] ?? $x['estado']); ?></span>
        <?php if ($x['fecha_limite']): ?><span class="herr-chip<?php echo $vencido ? '' : ' herr-chip-suave'; ?>"><i class="fa-solid fa-calendar-xmark"></i> límite <b><?php echo s(date('d/m/Y', strtotime($x['fecha_limite']))); ?></b></span><?php endif; ?>
    </div></div>
    <div class="inc-acciones"><a href="/punch?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Punch list</a></div></div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>
<div class="suite-panel prg-seccion">
    <div class="suite-panel-cab"><h3><i class="fa-solid fa-images"></i> Fotos antes y después</h3></div>
    <div class="suite-panel-cuerpo">
    <div class="inf-gen">
        <div><h4>Antes</h4><?php echo $x['foto_antes'] ? '<a href="' . s($foto($x['foto_antes'])) . '" target="_blank" rel="noopener"><img src="' . s($foto($x['foto_antes'])) . '" alt="Foto antes" style="max-width:100%;border-radius:.8rem"></a>' : '<p class="text-muted">Sin foto.</p>'; ?></div>
        <div><h4>Después</h4><?php echo $x['foto_despues'] ? '<a href="' . s($foto($x['foto_despues'])) . '" target="_blank" rel="noopener"><img src="' . s($foto($x['foto_despues'])) . '" alt="Foto después" style="max-width:100%;border-radius:.8rem"></a>' : '<p class="text-muted">Sin foto.</p>'; ?></div>
    </div>
    </div>
</div>
<?php if ($puede): ?>
<div class="suite-panel prg-seccion">
    <div class="suite-panel-cab"><h3><i class="fa-solid fa-pen"></i> Editar pendiente</h3></div>
    <div class="suite-panel-cuerpo">
    <form method="post" action="/punch/guardar" enctype="multipart/form-data" class="inc-filtros"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>">
        <label class="flex-2">Pendiente <input type="text" name="titulo" value="<?php echo s($x['titulo']); ?>" required maxlength="200"></label>
        <label>Ubicación <input type="text" name="ubicacion" value="<?php echo s($x['ubicacion']); ?>" maxlength="200"></label>
        <label>Categoría <input type="text" name="categoria" value="<?php echo s($x['categoria']); ?>" maxlength="60" list="cats"><datalist id="cats"><?php foreach (Punch::categoriasDeProyecto((int)$x['proyecto_id']) as $c): ?><option value="<?php echo s($c); ?>"><?php endforeach; ?></datalist></label>
        <label>Severidad <select name="severidad"><?php foreach (Punch::SEVERIDADES as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $x['severidad'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
        <label>Responsable <input type="text" name="responsable" value="<?php echo s($x['responsable']); ?>" maxlength="150"></label>
        <label>Contratista <input type="text" name="contratista" value="<?php echo s($x['contratista']); ?>" maxlength="150"></label>
        <label>Fecha de emisión <input type="date" name="fecha_emision" value="<?php echo s($x['fecha_emision']); ?>"></label>
        <label>Fecha límite <input type="date" name="fecha_limite" value="<?php echo s($x['fecha_limite']); ?>"></label>
        <label>Actividad <select name="actividad_id"><option value="0">(ninguna)</option><?php echo opciones_actividades($actividades, $x['actividad_id'], 45); ?></select></label>
        <label class="full">Descripción <textarea name="descripcion" rows="3" maxlength="3000"><?php echo s($x['descripcion']); ?></textarea></label>
        <label>Foto «antes» <input type="file" name="foto_antes" accept="image/*"></label><label>Foto «después» <input type="file" name="foto_despues" accept="image/*"></label>
        <button class="btn btn-primary" type="submit">Guardar</button></form>
    <div class="inc-botones" style="margin-top:1rem">
        <?php foreach (Punch::ESTADOS as $e => $n): if ($e === $x['estado']) continue; ?><form class="inline" method="post" action="/punch/estado"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><input type="hidden" name="estado" value="<?php echo $e; ?>"><input type="hidden" name="volver_ver" value="1"><button class="btn btn-sm btn-ghost" type="submit"><?php echo s($n); ?></button></form><?php endforeach; ?>
        <form class="inline" method="post" action="/punch/eliminar" data-confirmar="¿Eliminar este pendiente y sus fotos?"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button></form></div>
    </div>
</div>
<?php else: ?><div class="suite-panel"><div class="suite-panel-cuerpo"><p><?php echo nl2br(s($x['descripcion'])); ?></p><p class="text-muted"><?php echo s($x['ubicacion']); ?> · <?php echo s($x['responsable'] ?: $x['contratista']); ?></p></div></div><?php endif; ?>
