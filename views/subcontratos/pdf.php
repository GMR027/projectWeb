<?php
/** Ficha de subcontrato en PDF. Variables: $proyecto, $s */
use Classes\Subcontratos;
$m = fn($v) => '$' . number_format((float)$v, 2);
$f = fn($v) => $v ? date('d/m/Y', strtotime((string)$v)) : '—';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; margin: 0; }
    h1 { font-size: 15pt; margin: 0 0 2px; }
    .sub { color: #64748b; font-size: 8.5pt; margin: 0 0 12px; }
    .franja { display: table; width: 100%; margin-bottom: 12px; border: 1px solid #cbd5e1; }
    .franja > div { display: table-cell; padding: 7px 9px; border-right: 1px solid #e2e8f0; }
    .franja > div:last-child { border-right: 0; }
    .franja small { display: block; color: #64748b; font-size: 7.5pt; text-transform: uppercase; }
    table { border-collapse: collapse; width: 100%; margin: 6px 0 12px; }
    th { background: #f1f5f9; text-align: left; font-size: 8pt; padding: 5px; border: 1px solid #cbd5e1; }
    td { padding: 5px; border: 1px solid #e2e8f0; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    h2 { font-size: 10.5pt; margin: 14px 0 4px; }
    .firmas { width: 100%; margin-top: 34px; border-collapse: collapse; }
    .firmas td { width: 50%; text-align: center; padding-top: 26px; border-top: 1px solid #334155; font-size: 8pt; }
    .pie { margin-top: 14px; color: #64748b; font-size: 7pt; }
</style>
</head>
<body>
<h1>Subcontrato <?php echo s($s['codigo'] ?: '#' . $s['id']); ?></h1>
<p class="sub"><?php echo s($proyecto->nombre); ?> · <?php echo s($s['proveedor']); ?> · <?php echo $f($s['fecha_inicio']); ?> – <?php echo $f($s['fecha_fin']); ?></p>

<div class="franja">
    <div><small>Partida</small><b><?php echo s($s['partida_codigo'] . ' ' . $s['partida']); ?></b></div>
    <div><small>Monto</small><b><?php echo $m($s['monto']); ?></b></div>
    <div><small>Pagado</small><b><?php echo $m($s['pagado']); ?></b></div>
    <div><small>Saldo</small><b><?php echo $m($s['saldo']); ?></b></div>
</div>

<h2>Alcance</h2>
<p><?php echo nl2br(s($s['alcance'] ?: 'Sin alcance capturado.')); ?></p>

<h2>Documentos obligatorios</h2>
<table>
    <thead><tr><th>Documento</th><th>Archivo</th><th>Vencimiento</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach (Subcontratos::DOCS_OBLIGATORIOS as $tipo): $d = $s['docs']['filas'][$tipo] ?? null; $ok = $d && (int)$d['validado'] && !empty($d['archivo_id']) && (empty($d['vencimiento']) || $d['vencimiento'] >= date('Y-m-d')); ?>
        <tr><td><?php echo s(Subcontratos::DOCS[$tipo]); ?></td><td><?php echo s($d['archivo'] ?? '—'); ?></td><td><?php echo $f($d['vencimiento'] ?? null); ?></td><td><?php echo $ok ? 'Vigente' : 'Falta / vencido'; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Estimaciones</h2>
<table>
    <thead><tr><th>Número</th><th>Fecha</th><th class="num">Subtotal</th><th class="num">IVA</th><th class="num">Retención</th><th class="num">Amortización</th><th class="num">Neto</th><th>Estado</th></tr></thead>
    <tbody>
    <?php if (!$s['estimaciones']): ?><tr><td colspan="8">Sin estimaciones.</td></tr><?php endif; ?>
    <?php foreach ($s['estimaciones'] as $e): ?>
        <tr><td><?php echo s($e['numero']); ?></td><td><?php echo $f($e['fecha']); ?></td><td class="num"><?php echo $m($e['subtotal']); ?></td><td class="num"><?php echo $m($e['iva']); ?></td><td class="num"><?php echo $m($e['retencion']); ?></td><td class="num"><?php echo $m($e['amortizacion']); ?></td><td class="num"><b><?php echo $m($e['neto']); ?></b></td><td><?php echo s(Subcontratos::ESTADOS_ESTIMACION[$e['estado']] ?? $e['estado']); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if (trim((string)$s['notas']) !== ''): ?><p><b>Notas:</b> <?php echo nl2br(s($s['notas'])); ?></p><?php endif; ?>
<p class="pie">Retención <?php echo number_format((float)$s['retencion_pct'], 2); ?> % · Anticipo <?php echo number_format((float)$s['anticipo_pct'], 2); ?> %. Documento generado desde ProjectWeb.</p>

<table class="firmas"><tr><td>Contratante</td><td>Subcontratista</td></tr></table>
</body>
</html>
