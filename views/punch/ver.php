<?php // Variables: $proyecto, $x, $actividades, $puede, $mensaje
use Classes\Punch;
$pid = (int)$proyecto->id;
$foto = fn($n) => $n ? '/uploads/' . Punch::CARPETA . '/' . basename($n) : null;
?>
<div class="page-header"><div><h2><i class="fa-solid fa-clipboard-check"></i> <?php echo s($x['titulo']); ?></h2><p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo s(Punch::ESTADOS[$x['estado']]); ?> · creado por <?php echo s($x['creado_por'] ?: '—'); ?> el <?php echo s(date('d/m/Y', strtotime($x['creado']))); ?><?php echo $x['origen'] !== 'manual' ? ' · origen: ' . s($x['origen']) : ''; ?></p></div>
    <div class="inc-acciones"><a href="/punch?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Punch list</a></div></div>
<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>
<div class="card prg-seccion">
    <div class="inf-gen">
        <div><h4>Antes</h4><?php echo $x['foto_antes'] ? '<a href="' . s($foto($x['foto_antes'])) . '" target="_blank" rel="noopener"><img src="' . s($foto($x['foto_antes'])) . '" alt="Foto antes" style="max-width:100%;border-radius:.8rem"></a>' : '<p class="text-muted">Sin foto.</p>'; ?></div>
        <div><h4>Después</h4><?php echo $x['foto_despues'] ? '<a href="' . s($foto($x['foto_despues'])) . '" target="_blank" rel="noopener"><img src="' . s($foto($x['foto_despues'])) . '" alt="Foto después" style="max-width:100%;border-radius:.8rem"></a>' : '<p class="text-muted">Sin foto.</p>'; ?></div>
    </div>
</div>
<?php if ($puede): ?>
<div class="card prg-seccion"><h3>Editar</h3>
    <form method="post" action="/punch/guardar" enctype="multipart/form-data" class="inc-filtros"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>">
        <label style="flex:2">Pendiente <input type="text" name="titulo" value="<?php echo s($x['titulo']); ?>" required maxlength="200"></label>
        <label>Ubicación <input type="text" name="ubicacion" value="<?php echo s($x['ubicacion']); ?>" maxlength="200"></label>
        <label>Categoría <input type="text" name="categoria" value="<?php echo s($x['categoria']); ?>" maxlength="60" list="cats"><datalist id="cats"><?php foreach (Punch::categoriasDeProyecto((int)$x['proyecto_id']) as $c): ?><option value="<?php echo s($c); ?>"><?php endforeach; ?></datalist></label>
        <label>Severidad <select name="severidad"><?php foreach (Punch::SEVERIDADES as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $x['severidad'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
        <label>Responsable <input type="text" name="responsable" value="<?php echo s($x['responsable']); ?>" maxlength="150"></label>
        <label>Contratista <input type="text" name="contratista" value="<?php echo s($x['contratista']); ?>" maxlength="150"></label>
        <label>Fecha límite <input type="date" name="fecha_limite" value="<?php echo s($x['fecha_limite']); ?>"></label>
        <label>Actividad <select name="actividad_id"><option value="0">(ninguna)</option><?php foreach ($actividades as $id => $a): ?><option value="<?php echo (int)$id; ?>"<?php echo (int)$x['actividad_id'] === (int)$id ? ' selected' : ''; ?>><?php echo s($a['codigo'] . ' ' . mb_substr($a['nombre'], 0, 45)); ?></option><?php endforeach; ?></select></label>
        <label style="flex-basis:100%">Descripción <textarea name="descripcion" rows="3" maxlength="3000"><?php echo s($x['descripcion']); ?></textarea></label>
        <label>Foto «antes» <input type="file" name="foto_antes" accept="image/*"></label><label>Foto «después» <input type="file" name="foto_despues" accept="image/*"></label>
        <button class="btn btn-primary" type="submit">Guardar</button></form>
    <div class="inc-botones" style="margin-top:1rem">
        <?php foreach (Punch::ESTADOS as $e => $n): if ($e === $x['estado']) continue; ?><form method="post" action="/punch/estado" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><input type="hidden" name="estado" value="<?php echo $e; ?>"><input type="hidden" name="volver_ver" value="1"><button class="btn btn-sm btn-ghost" type="submit"><?php echo s($n); ?></button></form><?php endforeach; ?>
        <form method="post" action="/punch/eliminar" data-confirmar="¿Eliminar este pendiente y sus fotos?" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i> Eliminar</button></form></div>
</div>
<?php else: ?><div class="card"><p><?php echo nl2br(s($x['descripcion'])); ?></p><p class="text-muted"><?php echo s($x['ubicacion']); ?> · <?php echo s($x['responsable'] ?: $x['contratista']); ?></p></div><?php endif; ?>
