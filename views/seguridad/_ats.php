<?php
/** ATS: análisis de riesgo de la tarea. Variables: $proyecto, $pid, $ats, $atsVer, $atsPendientes, $actividades, $puede, $fmt */
use Classes\Seguridad;
?>
<?php if ($puede && !$atsVer): ?>
<form method="POST" action="/seguridad/ats" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <h3><i class="fa-solid fa-clipboard-list"></i> Nuevo ATS</h3>
    <div class="co-campos">
        <label>Actividad<select name="actividad_id"><option value="0">— Sin actividad —</option><?php echo opciones_actividades($actividades); ?></select></label>
        <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></label>
        <label>Responsable<input type="text" name="responsable" maxlength="150"></label>
        <label>Participantes<input type="number" name="participantes" min="1" value="1"></label>
        <label>Trabajo a realizar *<textarea name="trabajo" rows="2" required maxlength="5000"></textarea></label>
        <label>Peligros<textarea name="peligros" rows="2" maxlength="5000" placeholder="Uno por línea"></textarea></label>
        <label>Controles<textarea name="controles" rows="2" maxlength="5000" placeholder="Uno por línea"></textarea></label>
        <label>EPP<textarea name="epp" rows="2" maxlength="3000" placeholder="Casco, arnés, guantes…"></textarea></label>
        <label>Estado<select name="estado"><option value="borrador">Borrador</option><option value="firmado">Firmado</option></select></label>
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar ATS</button>
</form>
<?php endif; ?>

<?php if ($atsPendientes): ?>
<div class="alerta alerta-aviso"><strong>ATS pendientes:</strong> <?php echo count($atsPendientes); ?> actividad(es) de alto riesgo inician en 7 días sin ATS firmado para su fecha de inicio.
    <ul><?php foreach (array_slice($atsPendientes, 0, 5) as $x): ?><li><?php echo s($x['actividad']['codigo'] . ' · ' . $x['actividad']['nombre']); ?> — <?php echo s(implode(', ', array_map(fn($t) => Seguridad::tipos($pid)[$t]['nombre'] ?? $t, $x['tipos']))); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($atsVer): $a = $atsVer; ?>
<div class="card" style="margin-bottom:1.4rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
        <div><h3 class="m-0">ATS · <?php echo s($a['act_codigo'] ? $a['act_codigo'] . ' · ' . $a['act_nombre'] : 'Sin actividad'); ?></h3><p class="text-muted text-sm m-0"><?php echo s($fmt($a['fecha'])); ?> · <?php echo s(Seguridad::ATS_ESTADOS[$a['estado']] ?? $a['estado']); ?> · <?php echo count($a['firmas_lista']); ?>/<?php echo (int)$a['participantes']; ?> firmas</p></div>
        <a class="btn btn-sm btn-ghost" href="/seguridad?proyecto=<?php echo $pid; ?>&tab=ats">Cerrar</a>
    </div>
    <div class="rg-detalle-grid" style="margin-top:1rem">
        <div><small>Trabajo</small><b><?php echo nl2br(s($a['trabajo'])); ?></b></div>
        <div><small>Peligros</small><b><?php echo nl2br(s($a['peligros'])); ?></b></div>
        <div><small>Controles</small><b><?php echo nl2br(s($a['controles'])); ?></b></div>
        <div><small>EPP</small><b><?php echo nl2br(s($a['epp'])); ?></b></div>
    </div>
    <h4>Firmas</h4>
    <?php if (!$a['firmas_lista']): ?><p class="text-muted">Sin firmas.</p><?php else: ?><ul><?php foreach ($a['firmas_lista'] as $f): ?><li><?php echo s($f['nombre']); ?><?php echo $f['puesto'] ? ' · ' . s($f['puesto']) : ''; ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($puede): ?>
    <form method="POST" action="/seguridad/ats/firma" class="co-form" style="margin-top:.8rem">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
        <div class="co-campos"><label>Nombre *<input type="text" name="nombre" maxlength="150" required></label><label>Puesto<input type="text" name="puesto" maxlength="100"></label></div>
        <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-signature"></i> Agregar firma</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h3><i class="fa-solid fa-clipboard-list"></i> ATS registrados</h3>
    <?php if (!$ats): ?><p class="text-muted">Aún no hay ATS. Crea uno antes de la tarea de alto riesgo.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Fecha</th><th>Actividad</th><th>Trabajo</th><th>Firmas</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php foreach ($ats as $a): ?><tr><td class="mono"><?php echo s($fmt($a['fecha'])); ?></td><td class="text-sm"><?php echo s($a['act_codigo'] ? $a['act_codigo'] . ' · ' . $a['act_nombre'] : '—'); ?></td><td><?php echo s(mb_substr((string)$a['trabajo'], 0, 120)); ?></td><td class="num"><?php echo (int)$a['firmas']; ?>/<?php echo (int)$a['participantes']; ?></td><td><span class="badge badge-<?php echo $a['estado'] === 'firmado' ? 'cliente' : ($a['estado'] === 'cerrado' ? 'visitante' : 'admin'); ?>"><?php echo s(Seguridad::ATS_ESTADOS[$a['estado']] ?? $a['estado']); ?></span></td><td><a class="btn btn-xs btn-ghost" href="/seguridad?proyecto=<?php echo $pid; ?>&tab=ats&ats=<?php echo (int)$a['id']; ?>" title="Ver" aria-label="Ver"><i class="fa-solid fa-eye"></i></a></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
