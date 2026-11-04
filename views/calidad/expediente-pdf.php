<?php
/** PDF del expediente de calidad. Variables: $proyecto, $datos */
$p = $datos['partida'];
$r = $datos['resumen'];
$f = fn($v) => $v ? date('d/m/Y', strtotime((string)$v)) : '—';
$m = fn($v) => '$' . number_format((float)$v, 2);
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; margin: 0; }
    h1 { font-size: 15pt; margin: 0 0 2px; } .sub { color: #64748b; font-size: 8.5pt; margin: 0 0 12px; }
    h2 { font-size: 10.5pt; margin: 14px 0 4px; }
    table { border-collapse: collapse; width: 100%; margin: 5px 0 10px; }
    th { background: #f1f5f9; text-align: left; font-size: 8pt; padding: 4px 5px; border: 1px solid #cbd5e1; }
    td { padding: 4px 5px; border: 1px solid #e2e8f0; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .muted { color: #64748b; }
    .firmas { width: 100%; margin-top: 34px; border-collapse: collapse; }
    .firmas td { width: 50%; text-align: center; padding-top: 26px; border-top: 1px solid #334155; font-size: 8pt; }
</style></head><body>
<h1>Expediente de calidad · <?php echo s($p['codigo'] . ' ' . $p['nombre']); ?></h1>
<p class="sub"><?php echo s($proyecto->nombre); ?> · generado el <?php echo date('d/m/Y H:i'); ?></p>
<table><tbody>
<tr><td><b>Planes ITP</b></td><td class="num"><?php echo (int)$r['planes']; ?></td><td><b>Puntos que cumplen</b></td><td class="num"><?php echo (int)$r['cumplen']; ?>/<?php echo (int)$r['puntos']; ?></td></tr>
<tr><td><b>Ensayos</b></td><td class="num"><?php echo (int)$r['ensayos']; ?> (<?php echo (int)$r['rechazados']; ?> rechazados)</td><td><b>No conformidades</b></td><td class="num"><?php echo (int)$r['nc']; ?> (<?php echo (int)$r['nc_abiertas']; ?> abiertas)</td></tr>
</tbody></table>

<h2>Planes de inspección y puntos</h2>
<?php if (!$datos['planes']): ?><p class="muted">Sin planes de inspección ligados a esta partida.</p><?php endif; ?>
<?php foreach ($datos['planes'] as $pl): ?>
    <p><b><?php echo s(trim(($pl['codigo'] ? $pl['codigo'] . ' · ' : '') . $pl['titulo'])); ?></b> <span class="muted"><?php echo s($pl['act_codigo'] ? $pl['act_codigo'] . ' · ' . $pl['act_nombre'] : ''); ?></span></p>
    <table><thead><tr><th>Punto</th><th>Criterio</th><th>Resultado</th><th>Observación</th></tr></thead><tbody>
    <?php foreach ($pl['puntos'] as $pt): ?><tr><td><?php echo s($pt['descripcion']); ?></td><td><?php echo s($pt['criterio']); ?></td><td><?php echo s($pt['resultado']); ?></td><td><?php echo s($pt['observacion']); ?></td></tr><?php endforeach; ?>
    </tbody></table>
<?php endforeach; ?>

<h2>Ensayos de laboratorio</h2>
<?php if (!$datos['ensayos']): ?><p class="muted">Sin ensayos registrados.</p><?php else: ?>
<table><thead><tr><th>Tipo / muestra</th><th>Fechas</th><th>Laboratorio</th><th>Norma</th><th>Resultado</th><th>Veredicto</th></tr></thead><tbody>
<?php foreach ($datos['ensayos'] as $e): ?><tr><td><?php echo s($e['tipo']); ?><?php echo $e['muestra'] ? '<br><small>' . s($e['muestra']) . '</small>' : ''; ?></td><td><?php echo $f($e['fecha_toma']); ?><br><?php echo $f($e['fecha_resultado']); ?></td><td><?php echo s($e['laboratorio']); ?></td><td><?php echo s($e['norma']); ?></td><td><?php echo s($e['resultado']); ?></td><td><?php echo s(\Classes\CalidadCompleta::VEREDICTOS[$e['veredicto']] ?? $e['veredicto']); ?></td></tr><?php endforeach; ?>
</tbody></table>
<?php endif; ?>

<h2>No conformidades y acciones</h2>
<?php if (!$datos['ncs']): ?><p class="muted">Sin no conformidades formales.</p><?php else: ?>
<table><thead><tr><th>Código</th><th>Hallazgo / causa</th><th>Acción</th><th>Responsable</th><th>Compromiso</th><th class="num">Costo</th><th>Estado</th></tr></thead><tbody>
<?php foreach ($datos['ncs'] as $n): ?><tr><td><?php echo s($n['codigo']); ?></td><td><b><?php echo s($n['hallazgo']); ?></b><?php echo $n['causa'] ? '<br><small>' . s($n['causa']) . '</small>' : ''; ?></td><td><?php echo s($n['accion']); ?></td><td><?php echo s($n['responsable']); ?></td><td><?php echo $f($n['fecha_compromiso']); ?></td><td class="num"><?php echo $m($n['costo']); ?></td><td><?php echo s(\Classes\CalidadCompleta::NC_ESTADOS[$n['estado']] ?? $n['estado']); ?></td></tr>
    <?php foreach ($n['acciones'] as $a): ?><tr><td></td><td colspan="2" class="muted">Acción <?php echo $f($a['fecha']); ?>: <?php echo s($a['accion']); ?></td><td colspan="2"><?php echo s($a['responsable']); ?><?php echo (int)$a['verificada'] ? ' · verificada' : ''; ?></td><td></td><td></td></tr><?php endforeach; ?>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>

<p class="muted">Fondo retenido / costo de NC abiertas: <?php echo $m($r['costo_nc']); ?>. Documento generado desde ProjectWeb.</p>
<table class="firmas"><tr><td>Responsable de calidad</td><td>Residente / supervisión</td></tr></table>
</body></html>
