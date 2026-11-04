<?php
/** Ensayos de laboratorio. Variables: $proyecto, $pid, $url, $ensayos, $archivos, $actividades, $lista, $puedeEditar, $ok, $error */
use Classes\CalidadCompleta;
$m = fn($v) => '$' . number_format((float)$v, 2);
?>
<?php if ($puedeEditar): ?>
<form method="POST" action="/calidad/ensayo" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <h3><i class="fa-solid fa-flask"></i> Nuevo ensayo</h3>
    <div class="co-campos">
        <label>Tipo de ensayo *<input type="text" name="tipo" maxlength="100" required placeholder="Resistencia, hermeticidad, densidad…"></label>
        <label>Actividad<select name="actividad_id"><option value="0">— Sin actividad —</option><?php echo opciones_actividades($actividades, 0); ?></select></label>
        <label>Plan de inspección<select name="plan_id"><option value="0">— Sin plan —</option><?php foreach ($lista as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo s(($p['codigo'] ? $p['codigo'] . ' · ' : '') . $p['titulo']); ?></option><?php endforeach; ?></select></label>
        <label>Muestra<input type="text" name="muestra" maxlength="150" placeholder="Losa 2.4, Nave A…"></label>
        <label>Ubicación<input type="text" name="ubicacion" maxlength="200"></label>
        <label>Fecha de toma<input type="date" name="fecha_toma" value="<?php echo date('Y-m-d'); ?>"></label>
        <label>Fecha de resultado<input type="date" name="fecha_resultado"></label>
        <label>Laboratorio<input type="text" name="laboratorio" maxlength="150"></label>
        <label>Norma / especificación<input type="text" name="norma" maxlength="200" placeholder="ASTM, NOM, especificación…"></label>
        <label>Resultado<input type="text" name="resultado" maxlength="100" placeholder="280 kg/cm², sin fuga…"></label>
        <label>Veredicto<select name="veredicto"><?php foreach (CalidadCompleta::VEREDICTOS as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label>Certificado<select name="archivo_id"><option value="0">— Sin archivo —</option><?php foreach ($archivos as $a): ?><option value="<?php echo (int)$a->id; ?>"><?php echo s($a->nombre); ?></option><?php endforeach; ?></select></label>
        <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"></textarea></label>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar ensayo</button>
</form>
<?php endif; ?>

<div class="card">
    <h3><i class="fa-solid fa-flask"></i> Ensayos registrados</h3>
    <?php if (!$ensayos): ?><p class="text-muted">Aún no hay ensayos. Registra el primero con el formulario de arriba.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Tipo / muestra</th><th>Actividad</th><th>Fechas</th><th>Laboratorio / norma</th><th>Resultado</th><th>Certificado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ensayos as $e): ?>
            <tr>
                <td><strong><?php echo s($e['tipo']); ?></strong><?php if ($e['muestra']): ?><br><small class="text-muted"><?php echo s($e['muestra'] . ($e['ubicacion'] ? ' · ' . $e['ubicacion'] : '')); ?></small><?php endif; ?></td>
                <td class="text-sm"><?php echo $e['act_codigo'] ? s($e['act_codigo'] . ' · ' . $e['act_nombre']) : '—'; ?><?php if ($e['plan_codigo']): ?><br><small class="text-muted"><?php echo s($e['plan_codigo'] . ' · ' . $e['plan_titulo']); ?></small><?php endif; ?></td>
                <td class="mono text-sm"><?php echo $e['fecha_toma'] ? s(formatear_fecha($e['fecha_toma'], 'd/m/Y')) : '—'; ?><?php if ($e['fecha_resultado']): ?><br><small><?php echo s(formatear_fecha($e['fecha_resultado'], 'd/m/Y')); ?></small><?php endif; ?></td>
                <td class="text-sm"><?php echo s($e['laboratorio'] ?: '—'); ?><?php if ($e['norma']): ?><br><small class="text-muted"><?php echo s($e['norma']); ?></small><?php endif; ?></td>
                <td><?php echo s($e['resultado'] ?: '—'); ?><br><span class="badge badge-<?php echo $e['veredicto'] === 'aprobado' ? 'cliente' : ($e['veredicto'] === 'rechazado' ? 'admin' : 'visitante'); ?>"><?php echo s(CalidadCompleta::VEREDICTOS[$e['veredicto']] ?? $e['veredicto']); ?></span></td>
                <td><?php if ($e['archivo_nombre']): ?><span class="text-sm"><i class="fa-solid fa-paperclip"></i> <?php echo s($e['archivo_nombre']); ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                <td><?php if ($puedeEditar): ?><form class="inline" method="POST" action="/calidad/ensayo/eliminar" data-confirm="Eliminar ensayo|Se borrará el registro del ensayo. El certificado del Archivo no se borra.|Eliminar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>"><button class="btn btn-xs btn-ghost text-danger" type="submit" title="Eliminar" aria-label="Eliminar"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
