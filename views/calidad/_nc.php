<?php
/** No conformidades formales. Variables: $proyecto, $pid, $url, $ncs, $ncVer, $archivos, $actividades, $lista, $siguienteNC, $puedeEditar, $ok, $error */
use Classes\CalidadCompleta;
$m = fn($v) => '$' . number_format((float)$v, 2);
$n = $ncVer;
?>
<?php if ($n): ?>
<div class="card" style="margin-bottom:1.4rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
        <div><h3 class="m-0"><?php echo s($n['codigo']); ?> · <?php echo s(mb_substr((string)$n['hallazgo'], 0, 100)); ?></h3><p class="text-muted text-sm m-0"><?php echo $n['act_codigo'] ? s($n['act_codigo'] . ' · ' . $n['act_nombre']) . ' · ' : ''; ?><?php echo s(CalidadCompleta::NC_ESTADOS[$n['estado']] ?? $n['estado']); ?></p></div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap"><a class="btn btn-sm btn-ghost" href="<?php echo s($url(['tab' => 'nc'])); ?>">Cerrar</a><?php if ($puedeEditar && $n['estado'] !== 'cerrada'): ?><form class="inline" method="POST" action="/calidad/nc/cerrar" data-confirm="Cerrar no conformidad|Se marcará como cerrada y se liberará el avance si no hay otra NC abierta.|Cerrar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>"><button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-circle-check"></i> Cerrar NC</button></form><?php endif; ?></div>
    </div>
    <div class="rg-detalle-grid" style="margin-top:1rem">
        <div><small>Causa raíz</small><b><?php echo s($n['causa'] ?: '—'); ?></b></div>
        <div><small>Responsable</small><b><?php echo s($n['responsable'] ?: '—'); ?></b></div>
        <div><small>Compromiso</small><b><?php echo $n['fecha_compromiso'] ? s(formatear_fecha($n['fecha_compromiso'], 'd/m/Y')) : '—'; ?></b></div>
        <div><small>Costo</small><b class="mono"><?php echo $m($n['costo']); ?></b></div>
        <div><small>Estado</small><b><?php echo s(CalidadCompleta::NC_ESTADOS[$n['estado']] ?? $n['estado']); ?></b></div>
    </div>
    <?php if (trim((string)$n['hallazgo']) !== ''): ?><p><strong>Hallazgo:</strong> <?php echo nl2br(s($n['hallazgo'])); ?></p><?php endif; ?>
    <?php if (trim((string)$n['accion']) !== ''): ?><p><strong>Acción correctiva:</strong> <?php echo nl2br(s($n['accion'])); ?></p><?php endif; ?>
    <?php if (!$n['movimiento_id'] && (float)$n['costo'] > 0 && $puedeEditar): ?>
        <form method="POST" action="/calidad/nc/costo" data-confirm="Enviar a Costos|Se creará un costo real por el importe de la NC. Después no se puede volver a enviar.|Enviar">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
            <button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-sack-dollar"></i> Enviar costo a Costos</button>
        </form>
    <?php elseif ($n['movimiento_id']): ?><p class="text-sm text-muted"><i class="fa-solid fa-check"></i> Costo enviado a Costos (movimiento #<?php echo (int)$n['movimiento_id']; ?>).</p><?php endif; ?>

    <h4>Seguimiento de acciones</h4>
    <?php if (!$n['acciones']): ?><p class="text-muted">Sin acciones registradas.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Fecha</th><th>Acción</th><th>Responsable</th><th>Verificada</th><th>Notas</th></tr></thead><tbody>
        <?php foreach ($n['acciones'] as $a): ?><tr><td class="mono"><?php echo $a['fecha'] ? s(formatear_fecha($a['fecha'], 'd/m/Y')) : '—'; ?></td><td><?php echo s($a['accion']); ?></td><td><?php echo s($a['responsable']); ?></td><td><?php echo (int)$a['verificada'] ? '<span class="badge badge-cliente">Sí</span>' : '<span class="badge badge-visitante">No</span>'; ?></td><td class="text-sm"><?php echo s($a['notas']); ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <?php if ($puedeEditar && $n['estado'] !== 'cerrada'): ?>
    <form method="POST" action="/calidad/nc/accion" class="co-form" style="margin-top:1rem">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="nc_id" value="<?php echo (int)$n['id']; ?>">
        <div class="co-campos">
            <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>"></label>
            <label>Responsable<input type="text" name="responsable" maxlength="150" value="<?php echo s($n['responsable']); ?>"></label>
            <label class="co-ancho">Acción realizada<textarea name="accion" rows="2" required maxlength="5000"></textarea></label>
            <label class="co-check"><input type="checkbox" name="verificada" value="1"> Verificada</label>
            <label class="co-check"><input type="checkbox" name="en_correccion" value="1"> Pasar a «en corrección»</label>
            <label class="co-ancho">Notas<input type="text" name="notas" maxlength="500"></label>
        </div>
        <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-plus"></i> Agregar acción</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($puedeEditar && !$n): ?>
<form method="POST" action="/calidad/nc" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <h3><i class="fa-solid fa-triangle-exclamation"></i> Nueva no conformidad</h3>
    <div class="co-campos">
        <label>Código<input type="text" name="codigo" maxlength="40" value="<?php echo s($siguienteNC); ?>"></label>
        <label>Actividad<select name="actividad_id"><option value="0">— Sin actividad —</option><?php echo opciones_actividades($actividades, 0); ?></select></label>
        <label>Plan<select name="plan_id"><option value="0">— Sin plan —</option><?php foreach ($lista as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo s(($p['codigo'] ? $p['codigo'] . ' · ' : '') . $p['titulo']); ?></option><?php endforeach; ?></select></label>
        <label>Responsable<input type="text" name="responsable" maxlength="150"></label>
        <label>Compromiso<input type="date" name="fecha_compromiso"></label>
        <label>Costo estimado<input type="text" inputmode="decimal" name="costo" placeholder="0.00"></label>
        <label>Estado<select name="estado"><?php foreach (CalidadCompleta::NC_ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>"><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label class="co-ancho">Hallazgo *<textarea name="hallazgo" rows="2" required maxlength="5000"></textarea></label>
        <label class="co-ancho">Causa raíz<textarea name="causa" rows="2" maxlength="5000"></textarea></label>
        <label class="co-ancho">Acción correctiva<textarea name="accion" rows="2" maxlength="5000"></textarea></label>
        <label>Evidencia antes<select name="evidencia_antes"><option value="0">— Sin archivo —</option><?php foreach ($archivos as $a): ?><option value="<?php echo (int)$a->id; ?>"><?php echo s($a->nombre); ?></option><?php endforeach; ?></select></label>
        <label>Evidencia después<select name="evidencia_despues"><option value="0">— Sin archivo —</option><?php foreach ($archivos as $a): ?><option value="<?php echo (int)$a->id; ?>"><?php echo s($a->nombre); ?></option><?php endforeach; ?></select></label>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar no conformidad</button>
</form>
<?php endif; ?>

<div class="card">
    <h3><i class="fa-solid fa-list"></i> No conformidades formales</h3>
    <?php if (!$ncs): ?><p class="text-muted">Sin no conformidades formales registradas.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Código</th><th>Hallazgo</th><th>Actividad</th><th>Responsable</th><th>Compromiso</th><th class="num">Costo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($ncs as $x): ?>
            <tr><td class="mono"><b><?php echo s($x['codigo']); ?></b></td><td><?php echo s(mb_substr((string)$x['hallazgo'], 0, 120)); ?><?php if ((int)$x['acciones']): ?><br><small class="text-muted"><?php echo (int)$x['acciones']; ?> acción(es)</small><?php endif; ?></td><td class="text-sm"><?php echo $x['act_codigo'] ? s($x['act_codigo'] . ' · ' . $x['act_nombre']) : '—'; ?></td><td class="text-sm"><?php echo s($x['responsable']); ?></td><td class="mono text-sm"><?php echo $x['fecha_compromiso'] ? s(formatear_fecha($x['fecha_compromiso'], 'd/m/Y')) : '—'; ?></td><td class="num mono"><?php echo $m($x['costo']); ?></td><td><span class="badge badge-<?php echo $x['estado'] === 'cerrada' ? 'cliente' : ($x['estado'] === 'abierta' ? 'admin' : 'visitante'); ?>"><?php echo s(CalidadCompleta::NC_ESTADOS[$x['estado']] ?? $x['estado']); ?></span></td><td><a class="btn btn-xs btn-ghost" href="<?php echo s($url(['tab' => 'nc', 'nc' => $x['id']])); ?>" title="Ver" aria-label="Ver"><i class="fa-solid fa-eye"></i></a></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
