<?php
$col = ['baja' => '#6b7280', 'media' => '#0e7490', 'alta' => '#d97706', 'critica' => '#dc2626'];
$impNombre = fn($i) => mb_strtoupper(\Model\Bitacora::IMPORTANCIAS[$i] ?? '');
$fotoPdf = fn(string $c, ?string $a) => \Classes\Exportadores\ReportesPdfExporter::foto($c, $a);
$fmt = fn($f) => $f ? fecha_corta_es($f) : '—';
$cab = function (string $subtitulo = '') use ($logoE, $logoC, $titulo, $proyecto, $criterios) { ?>
    <table class="cab"><tr>
        <td style="width:28%"><?php if ($logoC): ?><img src="<?php echo $logoC; ?>" style="max-height:34pt;max-width:120pt"><?php endif; ?></td>
        <td class="text-center" style="font-weight:bold; font-size:11pt"><?php echo s(mb_strtoupper($titulo)); ?></td>
        <td class="text-right" style="width:28%"><?php if ($logoE): ?><img src="<?php echo $logoE; ?>" style="max-height:34pt;max-width:120pt"><?php endif; ?></td>
    </tr></table>
    <h1><?php echo s($subtitulo ?: $proyecto->nombre); ?></h1>
    <div class="peq"><?php echo s($proyecto->nombre . ($proyecto->numero_trabajo ? ' · Trabajo ' . $proyecto->numero_trabajo : '')); ?><?php if ($criterios): ?> · <?php echo s(implode(' · ', $criterios)); ?><?php endif; ?></div>
<?php };
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 14mm 12mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    h1 { font-size: 13pt; margin: 8pt 0 2pt; } h3 { font-size: 10pt; margin: 12pt 0 4pt; border-bottom: 1.2pt solid #333; padding-bottom: 2pt; }
    .cab td { vertical-align: middle; padding: 0; }
    .d td { border: 0.5pt solid #999; padding: 3pt 5pt; } .d td.k { background: #eee; width: 20%; font-weight: bold; }
    .t th { background: #333; color: #fff; font-size: 8pt; padding: 3pt 5pt; text-align: left; }
    .t td { border: 0.5pt solid #999; padding: 3pt 5pt; vertical-align: top; }
    tr { page-break-inside: avoid; }
    .peq { font-size: 7.5pt; color: #555; }
    .salto { page-break-before: always; }
    .foto { width: 49%; display: inline-block; vertical-align: top; margin: 0 0 6pt; text-align: center; page-break-inside: avoid; }
    .foto img { max-width: 100%; max-height: 190pt; }
    .dia { background: #333; color: #fff; padding: 3pt 6pt; font-weight: bold; margin: 10pt 0 6pt; }
</style></head><body>
<?php
switch ($tipo):

case 'reporte':
    foreach ($registros as $i => $x): $r = $x['reporte']; ?>
    <div<?php echo $i ? ' class="salto"' : ''; ?>>
        <?php $cab('Reporte diario · ' . fecha_larga_es($r->fecha)); ?>
        <table class="d" style="margin-top:6pt">
            <tr><td class="k">Fecha</td><td><?php echo s(fecha_larga_es($r->fecha)); ?></td><td class="k">Área</td><td><?php echo s($r->area ?: '—'); ?></td></tr>
        </table>
        <h3>Actividades del programa</h3>
        <?php if (!$x['actividades']): ?><div class="peq">Sin actividades.</div><?php else: ?>
        <table class="t"><thead><tr><th style="width:11%">Código</th><th>Actividad</th><th style="width:22%">Partida</th><th style="width:20%">Fechas</th><th style="width:9%">Avance</th></tr></thead><tbody>
        <?php foreach ($x['actividades'] as [$a, $p]): ?><tr><td><?php echo s($a->codigo); ?></td><td><?php echo s($a->nombre); ?></td><td><?php echo s($p->nombre); ?></td><td><?php echo s($fmt($a->fecha_inicio) . ' → ' . $fmt($a->fecha_fin)); ?></td><td><?php echo $a->esHito() ? 'Hito' : (int)$a->progreso . ' %'; ?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
        <?php $det = $x['parte'] ?? null; $nomAct = function (int $idA) use ($det): string { foreach (($det['actividades'] ?? []) as [$a]) if ((int)$a->id === $idA) return $a->codigo . ' · ' . $a->nombre; return '—'; }; ?>
        <h3>Personal (<?php echo $det ? count($det['personal']) : count($x['personal']); ?>)</h3>
        <?php if ($det && $det['personal']): ?>
        <table class="t"><thead><tr><th>Persona</th><th style="width:24%">Actividad</th><th style="width:8%">Horas</th><th style="width:7%">Extra</th><th style="width:16%">Cuadrilla</th><th>Observación</th></tr></thead><tbody>
        <?php foreach ($det['personal'] as $p): ?><tr><td><?php echo s($p->nombre ?: '—'); ?></td><td><?php echo s($nomAct((int)$p->actividad_id)); ?></td><td><?php echo s($p->horas_normal); ?></td><td><?php echo s($p->horas_extra); ?></td><td><?php echo s($p->cuadrilla ?: '—'); ?></td><td><?php echo s($p->observacion ?: '—'); ?></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php elseif (!$x['personal']): ?><div class="peq">Sin personal registrado.</div><?php else: ?>
        <table class="t"><thead><tr><th>Nombre</th><th style="width:40%">Departamento</th></tr></thead><tbody>
        <?php foreach ($x['personal'] as $p): ?><tr><td><?php echo s($p->nombre); ?></td><td><?php echo s($p->departamento ?: '—'); ?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
        <?php if ($det && $det['equipo']): ?>
        <h3>Equipo</h3>
        <table class="t"><thead><tr><th>Equipo</th><th style="width:24%">Actividad</th><th style="width:8%">Horas</th><th style="width:20%">Operador</th><th style="width:12%">Importe</th></tr></thead><tbody>
        <?php foreach ($det['equipo'] as $e): ?><tr><td><?php echo s($e->nombre); ?></td><td><?php echo s($nomAct((int)$e->actividad_id)); ?></td><td><?php echo s($e->horas); ?></td><td><?php echo s($e->operador ?: '—'); ?></td><td>$<?php echo number_format((float)$e->importe, 2); ?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
        <?php if ($det && $det['materiales']): ?>
        <h3>Materiales</h3>
        <table class="t"><thead><tr><th>Material</th><th style="width:12%">Movimiento</th><th style="width:12%">Cantidad</th><th style="width:22%">Actividad</th><th style="width:18%">Proveedor</th><th style="width:12%">Remisión</th></tr></thead><tbody>
        <?php foreach ($det['materiales'] as $m): ?><tr><td><?php echo s($m->nombre); ?></td><td><?php echo s(\Model\ReporteMaterial::TIPOS[$m->tipo] ?? $m->tipo); ?></td><td><?php echo s($m->cantidad) . ' ' . s($m->unidad); ?></td><td><?php echo s($nomAct((int)$m->actividad_id)); ?></td><td><?php echo s($m->proveedor ?: '—'); ?></td><td><?php echo s($m->remision ?: '—'); ?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
        <?php if ($det && $det['tiempos']): ?>
        <h3>Tiempos muertos</h3>
        <table class="t"><thead><tr><th style="width:26%">Actividad</th><th style="width:8%">Horas</th><th style="width:20%">Causa</th><th>Nota</th></tr></thead><tbody>
        <?php foreach ($det['tiempos'] as $t): ?><tr><td><?php echo s($nomAct((int)$t->actividad_id)); ?></td><td><?php echo s($t->horas); ?></td><td><?php echo s(\Model\ReporteTiempo::CAUSAS[$t->causa] ?? $t->causa); ?></td><td><?php echo s($t->nota ?: '—'); ?></td></tr><?php endforeach; ?>
        </tbody></table><?php endif; ?>
        <?php if ($det): ?>
        <h3>Seguridad y costo del día</h3>
        <table class="d"><tr>
            <td class="k">Charla</td><td><?php echo s(($det['seguridad'] && $det['seguridad']->charla_tema !== '') ? $det['seguridad']->charla_tema : '—'); ?></td>
            <td class="k">Asistentes</td><td><?php echo (int)($det['seguridad']->asistentes ?? 0); ?></td>
            <td class="k">Costo</td><td>$<?php echo number_format($det['costos']['total'], 2); ?></td>
        </tr></table>
        <?php endif; ?>
        <h3>Comentarios</h3>
        <div><?php echo trim((string)$r->comentarios) !== '' ? nl2br(s($r->comentarios)) : '—'; ?></div>
        <?php if ($x['fotos']): ?><h3>Fotos (<?php echo count($x['fotos']); ?>)</h3>
            <?php foreach ($x['fotos'] as $f): ?><div class="foto"><img src="<?php echo $f; ?>"></div><?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endforeach; break;

case 'bitacora':
    $cab('Bitácora · ' . $proyecto->nombre); ?>
    <p class="peq"><?php echo count($registros); ?> registro(s)</p>
    <table class="t"><thead><tr><th style="width:13%">Fecha</th><th style="width:20%">Departamento</th><th style="width:12%">Importancia</th><th>Descripción</th></tr></thead><tbody>
    <?php foreach ($registros as $b): ?><tr>
        <td><?php echo s($fmt($b->fecha)); ?></td><td><?php echo s($b->departamento ?: '—'); ?></td>
        <td style="font-weight:bold;color:<?php echo $col[$b->importancia] ?? '#000'; ?>"><?php echo s($impNombre($b->importancia)); ?></td>
        <td><?php echo nl2br(s($b->descripcion)); ?></td></tr><?php endforeach; ?>
    </tbody></table>
<?php break;

case 'minutas':
    foreach ($registros as $i => $x): $m = $x['minuta']; ?>
    <div<?php echo $i ? ' class="salto"' : ''; ?>>
        <?php $cab('Minuta · ' . $m->nombre); ?>
        <table class="d" style="margin-top:6pt"><tr>
            <td class="k">Fecha</td><td><?php echo s(fecha_larga_es($m->fecha)); ?></td>
            <td class="k">Importancia</td><td style="font-weight:bold;color:<?php echo $col[$m->importancia] ?? '#000'; ?>"><?php echo s($impNombre($m->importancia)); ?></td></tr></table>
        <?php if ($x['contratista'] || $x['personal']): ?><h3>Asistentes</h3>
        <table><tr>
            <td style="width:50%;vertical-align:top"><b>Contratista</b><?php foreach ($x['contratista'] as $p): ?><div><?php echo s($p->nombre); ?> <span class="peq"><?php echo s(trim($p->puesto . ($p->puesto && $p->empresa ? ' · ' : '') . $p->empresa)); ?></span></div><?php endforeach; ?></td>
            <td style="vertical-align:top"><b>Proyecto</b><?php foreach ($x['personal'] as $p): ?><div><?php echo s($p->nombre); ?> <span class="peq"><?php echo s($p->departamento); ?></span></div><?php endforeach; ?></td>
        </tr></table><?php endif; ?>
        <?php foreach (['Temas vistos' => ['Tema', $x['temas']], 'Compromisos' => ['Compromiso', $x['compromisos']]] as $tt => [$c1, $filas]): if (!$filas) continue; ?>
        <h3><?php echo $tt; ?></h3>
        <table class="t"><thead><tr><th style="width:46%"><?php echo $c1; ?></th><th style="width:27%">Responsable</th><th>Departamento</th></tr></thead><tbody>
        <?php foreach ($filas as $f): ?><tr><td><?php echo nl2br(s($f['tema'] ?? '')); ?></td><td><?php echo s($f['responsable'] ?? ''); ?></td><td><?php echo s($f['departamento'] ?? ''); ?></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php endforeach; ?>
    </div>
<?php endforeach; break;

case 'submittals':
    foreach ($registros as $i => $x): $sb = $x['submittal']; ?>
    <div<?php echo $i ? ' class="salto"' : ''; ?>>
        <?php $cab('Submittal ' . $sb->numero_submittal); ?>
        <table class="d" style="margin-top:6pt">
            <tr><td class="k">Fecha</td><td><?php echo s($fmt($sb->fecha)); ?></td><td class="k">Número de trabajo</td><td><?php echo s($sb->numero_trabajo); ?></td></tr>
            <tr><td class="k">Departamento</td><td><?php echo s($sb->departamento); ?></td><td class="k">Para</td><td><?php echo s($sb->para); ?></td></tr>
            <tr><td class="k">Especialidad</td><td><?php echo s($sb->especialidad); ?></td><td class="k">Estatus</td><td><?php echo s(\Model\Submittal::ESTATUS[$sb->estatus] ?? $sb->estatus); ?></td></tr>
        </table>
        <h3>Materiales (<?php echo count($x['materiales']); ?>)</h3>
        <table class="t"><thead><tr><th style="width:70pt">Imagen</th><th>Material</th><th style="width:25%">Especialidad</th></tr></thead><tbody>
        <?php foreach ($x['materiales'] as $m): $im = $img($m->imagen, 'uploads/materiales'); ?><tr>
            <td class="text-center"><?php if ($im): ?><img src="<?php echo $im; ?>" style="max-height:48pt;max-width:60pt"><?php endif; ?></td>
            <td><?php echo s($m->nombre); ?><?php if ($m->referencia): ?><br><span class="peq">Ref. <?php echo s($m->referencia); ?></span><?php endif; ?></td>
            <td><?php echo s($m->especialidad); ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
<?php endforeach; break;

case 'materiales':
    $cab('Materiales · ' . $proyecto->nombre); ?>
    <p class="peq"><?php echo count($registros); ?> material(es)</p>
    <table class="t"><thead><tr><th style="width:44pt">Imagen</th><th>Material</th><th style="width:15%">Especialidad</th><th style="width:9%">Cant.</th><th style="width:10%">Compra</th><th style="width:12%">Entrega prog.</th><th style="width:12%">Entrega real</th></tr></thead><tbody>
    <?php foreach ($registros as $m): $im = $img($m->imagen, 'uploads/materiales'); ?><tr>
        <td class="text-center"><?php if ($im): ?><img src="<?php echo $im; ?>" style="max-height:34pt;max-width:40pt"><?php endif; ?></td>
        <td><?php echo s($m->nombre); ?><?php if ($m->referencia): ?><br><span class="peq">Ref. <?php echo s($m->referencia); ?></span><?php endif; ?></td>
        <td><?php echo s($m->especialidad ?: '—'); ?></td>
        <td><?php echo $m->cantidad !== null && $m->cantidad !== '' ? s(rtrim(rtrim((string)$m->cantidad, '0'), '.') . ' ' . $m->unidad) : '—'; ?></td>
        <td><?php echo s(\Model\Material::COMPRA[$m->compra] ?? $m->compra); ?></td>
        <td><?php echo s($fmt($m->fecha_entrega_prog)); ?></td>
        <td><?php echo s($fmt($m->fecha_entrega_real)); ?></td></tr><?php endforeach; ?>
    </tbody></table>
<?php break;

case 'evidencias':
    $cab('Evidencias fotográficas');
    foreach ($registros as $fecha => $fotos): ?>
    <div class="dia"><?php echo s(fecha_larga_es($fecha)); ?> <span style="font-weight:normal">· <?php echo count($fotos); ?> foto(s)</span></div>
    <?php foreach ($fotos as $f): $src = $fotoPdf($f['carpeta'], $f['archivo']); if (!$src) continue; ?>
        <div class="foto"><img src="<?php echo $src; ?>">
            <div class="peq"><?php echo s(trim($f['actividad'] . ($f['actividad'] && $f['texto'] ? ' — ' : '') . $f['texto'])); ?></div></div>
    <?php endforeach; endforeach;
    break;
endswitch; ?>
<div class="peq" style="margin-top:14pt">Generado por ProjectWeb · <?php echo date('d/m/Y H:i'); ?></div>
</body></html>
