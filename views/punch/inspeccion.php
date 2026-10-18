<?php // Variables: $proyecto, $plantilla
use Classes\Punch;
$pid = (int)$proyecto->id;
?>
<div class="page-header"><div><h2><i class="fa-solid fa-list-check"></i> <?php echo s(t('Inspección')); ?></h2><p class="text-muted"><?php echo s($proyecto->nombre); ?> · elige la lista de verificación del sistema que inspeccionas; cada punto marcado como <strong>falla</strong> se convierte en un pendiente del punch list.</p></div>
    <div class="inc-acciones"><a href="/punch?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Punch list</a></div></div>
<form method="get" action="/punch/inspeccion" class="card inc-filtros"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Lista de verificación <select name="plantilla" onchange="this.form.submit()"><option value="">Elige…</option><?php foreach (Punch::PLANTILLAS as $k => $p): ?><option value="<?php echo $k; ?>"<?php echo $plantilla === $k ? ' selected' : ''; ?>><?php echo s($p['nombre']); ?></option><?php endforeach; ?></select></label></form>
<?php if ($plantilla): ?>
<form method="post" action="/punch/inspeccion" class="card prg-seccion"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="plantilla" value="<?php echo s($plantilla); ?>">
    <div class="inc-filtros"><label style="flex:2">Ubicación inspeccionada <input type="text" name="ubicacion" maxlength="200" placeholder="Ej. Nivel 3, IDF-2" required></label><label>Contratista <input type="text" name="contratista" maxlength="150"></label><label>Responsable <input type="text" name="responsable" maxlength="150"></label></div>
    <table class="prg-tabla"><thead><tr><th>Punto de verificación</th><th>Categoría</th><th>Resultado</th><th>Nota (si falla)</th></tr></thead><tbody>
        <?php foreach (Punch::PLANTILLAS[$plantilla]['items'] as $i => [$texto, $cat]): ?>
            <tr><td><?php echo s($texto); ?></td><td><?php echo s($cat); ?></td>
                <td style="white-space:nowrap"><label><input type="radio" name="r[<?php echo $i; ?>]" value="ok" checked> Cumple</label> <label><input type="radio" name="r[<?php echo $i; ?>]" value="falla"> <b class="prg-peor">Falla</b></label> <label><input type="radio" name="r[<?php echo $i; ?>]" value="na"> N/A</label></td>
                <td><input type="text" name="nota[<?php echo $i; ?>]" maxlength="300" style="width:100%"></td></tr>
        <?php endforeach; ?></tbody></table>
    <button class="btn btn-primary" type="submit" style="margin-top:1rem">Registrar inspección</button></form>
<?php endif; ?>
