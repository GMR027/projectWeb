<?php
/** PDF de un solo parte diario (vista incluida por ReporteController::partePdf) */
$r = $parte['reporte'];
$c = $parte['costos'];
$nomAct = function (int $idA) use ($parte): string {
    foreach ($parte['actividades'] as [$a]) if ((int)$a->id === $idA) return $a->codigo . ' · ' . $a->nombre;
    return '—';
};
$foto = fn(?string $a) => \Classes\Exportadores\ReportesPdfExporter::foto('reportes', $a);
$fmt = fn($f) => $f ? fecha_corta_es($f) : '—';
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 14mm 12mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    h1 { font-size: 14pt; margin: 6pt 0 2pt; }
    h3 { font-size: 10pt; margin: 12pt 0 4pt; border-bottom: 1.2pt solid #333; padding-bottom: 2pt; }
    .peq { font-size: 7.5pt; color: #555; }
    .d td { border: 0.5pt solid #999; padding: 3pt 5pt; } .d td.k { background: #eee; width: 18%; font-weight: bold; }
    .t th { background: #333; color: #fff; font-size: 8pt; padding: 3pt 5pt; text-align: left; }
    .t td { border: 0.5pt solid #999; padding: 3pt 5pt; vertical-align: top; }
    tr { page-break-inside: avoid; }
    .foto { width: 49%; display: inline-block; vertical-align: top; margin: 0 0 6pt; text-align: center; page-break-inside: avoid; }
    .foto img { max-width: 100%; max-height: 190pt; }
    .firma { max-width: 220pt; max-height: 80pt; }
    .tot td { border: 0.5pt solid #999; padding: 3pt 5pt; font-weight: bold; }
</style></head><body>
<h1>Parte diario de obra · <?php echo s(fecha_larga_es($r->fecha)); ?></h1>
<div class="peq"><?php echo s($proyecto->nombre . ($proyecto->numero_trabajo ? ' · Trabajo ' . $proyecto->numero_trabajo : '')); ?></div>

<table class="d" style="margin-top:8pt">
    <tr><td class="k">Fecha</td><td><?php echo s(fecha_larga_es($r->fecha)); ?></td><td class="k">Turno</td><td><?php echo s(\Model\ReporteDiario::TURNOS[$r->turno] ?? $r->turno); ?></td></tr>
    <tr><td class="k">Área / frente</td><td><?php echo s($r->area ?: '—'); ?></td><td class="k">Supervisor</td><td><?php echo s($r->supervisor ?: '—'); ?></td></tr>
    <tr><td class="k">Clima</td><td><?php echo $r->clima_precip_mm !== null ? s($r->clima_precip_mm) . ' mm lluvia' : '—'; ?><?php echo $r->clima_tmax !== null ? ' · ' . s($r->clima_tmax) . '°/' . s($r->clima_tmin) . '°' : ''; ?></td><td class="k">Visitas</td><td><?php echo s($r->visitas ?: '—'); ?></td></tr>
</table>

<h3>Actividades y avance (<?php echo count($parte['actividades']); ?>)</h3>
<?php if (!$parte['actividades']): ?><div class="peq">Sin actividades.</div><?php else: ?>
<table class="t"><thead><tr><th style="width:11%">Código</th><th>Actividad</th><th style="width:20%">Partida</th><th style="width:19%">Fechas</th><th style="width:9%">Avance</th></tr></thead><tbody>
<?php foreach ($parte['actividades'] as [$a, $p]): ?><tr>
    <td><?php echo s($a->codigo); ?></td><td><?php echo s($a->nombre); ?></td><td><?php echo s($p->nombre); ?></td>
    <td><?php echo s($fmt($a->fecha_inicio) . ' → ' . $fmt($a->fecha_fin)); ?></td><td><?php echo $a->esHito() ? 'Hito' : (int)$a->progreso . ' %'; ?></td>
</tr><?php endforeach; ?>
</tbody></table><?php endif; ?>

<h3>Personal (<?php echo count($parte['personal']); ?>)</h3>
<?php if (!$parte['personal']): ?><div class="peq">Sin personal registrado.</div><?php else: ?>
<table class="t"><thead><tr><th>Persona</th><th style="width:22%">Actividad</th><th style="width:9%">Horas</th><th style="width:8%">Extra</th><th style="width:16%">Cuadrilla</th><th>Observación</th></tr></thead><tbody>
<?php foreach ($parte['personal'] as $x): ?><tr>
    <td><?php echo s($x->nombre ?: '—'); ?></td><td><?php echo s($nomAct((int)$x->actividad_id)); ?></td>
    <td><?php echo s($x->horas_normal); ?></td><td><?php echo s($x->horas_extra); ?></td>
    <td><?php echo s($x->cuadrilla ?: '—'); ?></td><td><?php echo s($x->observacion ?: '—'); ?></td>
</tr><?php endforeach; ?>
</tbody></table><?php endif; ?>

<h3>Equipo (<?php echo count($parte['equipo']); ?>)</h3>
<?php if (!$parte['equipo']): ?><div class="peq">Sin equipo registrado.</div><?php else: ?>
<table class="t"><thead><tr><th>Equipo</th><th style="width:22%">Actividad</th><th style="width:9%">Horas</th><th style="width:20%">Operador</th><th style="width:12%">Importe</th></tr></thead><tbody>
<?php foreach ($parte['equipo'] as $x): ?><tr>
    <td><?php echo s($x->nombre); ?></td><td><?php echo s($nomAct((int)$x->actividad_id)); ?></td>
    <td><?php echo s($x->horas); ?></td><td><?php echo s($x->operador ?: '—'); ?></td><td>$<?php echo number_format((float)$x->importe, 2); ?></td>
</tr><?php endforeach; ?>
</tbody></table><?php endif; ?>

<h3>Materiales (<?php echo count($parte['materiales']); ?>)</h3>
<?php if (!$parte['materiales']): ?><div class="peq">Sin materiales registrados.</div><?php else: ?>
<table class="t"><thead><tr><th>Material</th><th style="width:12%">Movimiento</th><th style="width:12%">Cantidad</th><th style="width:20%">Actividad</th><th style="width:18%">Proveedor</th><th style="width:12%">Remisión</th></tr></thead><tbody>
<?php foreach ($parte['materiales'] as $x): ?><tr>
    <td><?php echo s($x->nombre); ?></td><td><?php echo s(\Model\ReporteMaterial::TIPOS[$x->tipo] ?? $x->tipo); ?></td>
    <td><?php echo s($x->cantidad) . ' ' . s($x->unidad); ?></td><td><?php echo s($nomAct((int)$x->actividad_id)); ?></td>
    <td><?php echo s($x->proveedor ?: '—'); ?></td><td><?php echo s($x->remision ?: '—'); ?></td>
</tr><?php endforeach; ?>
</tbody></table><?php endif; ?>

<h3>Tiempos muertos (<?php echo count($parte['tiempos']); ?>)</h3>
<?php if (!$parte['tiempos']): ?><div class="peq">Sin tiempos muertos.</div><?php else: ?>
<table class="t"><thead><tr><th style="width:24%">Actividad</th><th style="width:8%">Horas</th><th style="width:20%">Causa</th><th>Nota</th></tr></thead><tbody>
<?php foreach ($parte['tiempos'] as $x): ?><tr>
    <td><?php echo s($nomAct((int)$x->actividad_id)); ?></td><td><?php echo s($x->horas); ?></td>
    <td><?php echo s(\Model\ReporteTiempo::CAUSAS[$x->causa] ?? $x->causa); ?></td><td><?php echo s($x->nota ?: '—'); ?></td>
</tr><?php endforeach; ?>
</tbody></table><?php endif; ?>

<h3>Seguridad</h3>
<table class="d"><tr>
    <td class="k">Charla de 5 minutos</td><td><?php echo s(($parte['seguridad'] && $parte['seguridad']->charla_tema !== '') ? $parte['seguridad']->charla_tema : '—'); ?></td>
    <td class="k">Asistentes</td><td><?php echo (int)($parte['seguridad']->asistentes ?? 0); ?></td>
    <td class="k">EPP</td><td><?php echo !empty($parte['seguridad']->epp_ok) ? 'Completo' : 'No verificado'; ?></td>
</tr></table>

<h3>Comentarios</h3>
<div><?php echo trim((string)$r->comentarios) !== '' ? nl2br(s($r->comentarios)) : '—'; ?></div>

<h3>Costo del día</h3>
<table class="tot" style="width:60%">
    <tr><td>Personal</td><td style="text-align:right">$<?php echo number_format($c['personal'], 2); ?></td></tr>
    <tr><td>Equipo</td><td style="text-align:right">$<?php echo number_format($c['equipo'], 2); ?></td></tr>
    <tr><td>Material consumido</td><td style="text-align:right">$<?php echo number_format($c['material'], 2); ?></td></tr>
    <tr><td>Total</td><td style="text-align:right">$<?php echo number_format($c['total'], 2); ?></td></tr>
</table>
<div class="peq" style="margin-top:4pt"><?php echo s($c['horas_personal']); ?> h de personal · <?php echo s($c['horas_equipo']); ?> h de equipo · <?php echo s($c['horas_muertas']); ?> h muertas</div>

<?php if ($parte['fotos']): ?>
<h3>Fotos (<?php echo count($parte['fotos']); ?>)</h3>
<?php foreach ($parte['fotos'] as $f): $im = $foto($f->archivo); if (!$im) continue; ?>
    <div class="foto"><img src="<?php echo $im; ?>"><?php if ($f->leyenda): ?><div class="peq"><?php echo s($f->leyenda); ?></div><?php endif; ?></div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($r->firma_imagen || $r->firma_nombre): ?>
<h3>Firma del residente</h3>
<?php $firma = $foto($r->firma_imagen); ?>
<?php if ($firma): ?><img class="firma" src="<?php echo $firma; ?>"><?php endif; ?>
<div class="peq"><?php echo s($r->firma_nombre ?: ''); ?></div>
<?php endif; ?>
</body></html>
