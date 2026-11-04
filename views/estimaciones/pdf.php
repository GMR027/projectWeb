<?php
/** Hoja de estimación en PDF. Variables: $proyecto, $estimacion, $contrato, $resumen */
$e = $estimacion;
$m = fn($v) => '$' . number_format((float)$v, 2);
$f = fn($v) => $v ? date('d/m/Y', strtotime((string)$v)) : '—';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; margin: 0; }
    h1 { font-size: 16pt; margin: 0 0 2px; }
    .sub { color: #64748b; font-size: 8.5pt; margin: 0 0 14px; }
    .franja { display: table; width: 100%; margin-bottom: 12px; border: 1px solid #cbd5e1; }
    .franja > div { display: table-cell; padding: 8px 10px; border-right: 1px solid #e2e8f0; }
    .franja > div:last-child { border-right: 0; }
    .franja small { display: block; color: #64748b; font-size: 7.5pt; text-transform: uppercase; }
    .franja b { font-size: 10pt; }
    table.detalle { border-collapse: collapse; width: 100%; margin: 6px 0 12px; }
    table.detalle th { background: #f1f5f9; font-size: 8pt; text-align: left; padding: 5px; border: 1px solid #cbd5e1; }
    table.detalle td { padding: 5px; border: 1px solid #e2e8f0; }
    .num { text-align: right; white-space: nowrap; }
    table.totales { border-collapse: collapse; width: 55%; margin-left: 45%; }
    table.totales td { padding: 4px 6px; }
    table.totales td:last-child { text-align: right; white-space: nowrap; }
    table.totales tr.neto td { border-top: 2px solid #334155; font-size: 11pt; font-weight: bold; padding-top: 7px; }
    .notas { margin-top: 12px; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 8pt; }
    .firmas { width: 100%; margin-top: 40px; border-collapse: collapse; }
    .firmas td { width: 50%; text-align: center; padding-top: 28px; border-top: 1px solid #334155; font-size: 8pt; }
    .pie { margin-top: 18px; color: #64748b; font-size: 7pt; }
</style>
</head>
<body>
<h1>Estimación <?php echo s($e['numero']); ?></h1>
<p class="sub"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->codigo ? ' · ' . s($proyecto->codigo) : ''; ?> · <?php echo $f($e['fecha']); ?></p>

<div class="franja">
    <div><small>Periodo</small><b><?php echo $f($e['periodo_desde']); ?> – <?php echo $f($e['periodo_hasta']); ?></b></div>
    <div><small>Contrato vigente</small><b><?php echo $m($resumen['contrato_vigente']); ?></b></div>
    <div><small>Ejecutado aprobado</small><b><?php echo $m($resumen['ejecutado']); ?></b></div>
    <div><small>Saldo del contrato</small><b><?php echo $m($resumen['saldo']); ?></b></div>
</div>

<table class="detalle">
    <thead><tr><th>Partida</th><th class="num">Anterior aprobado</th><th class="num">Esta estimación</th><th class="num">Acumulado</th></tr></thead>
    <tbody>
    <?php foreach ($e['partidas'] as $p): ?>
        <tr>
            <td><b><?php echo s($p['partida_codigo']); ?></b> <?php echo s($p['partida']); ?></td>
            <td class="num"><?php echo $m($p['anterior']); ?></td>
            <td class="num"><?php echo $m($p['importe']); ?></td>
            <td class="num"><?php echo $m($p['acumulado']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<table class="totales">
    <tr><td>Subtotal</td><td><?php echo $m($e['subtotal']); ?></td></tr>
    <?php if ((float)$e['ajuste'] != 0.0): ?><tr><td>Ajuste de precios</td><td><?php echo $m($e['ajuste']); ?></td></tr><?php endif; ?>
    <tr><td>IVA (<?php echo number_format((float)$e['iva_pct'], 2); ?> %)</td><td><?php echo $m($e['iva']); ?></td></tr>
    <tr><td>Retención (<?php echo number_format((float)$e['retencion_pct'], 2); ?> %)</td><td>− <?php echo $m($e['retencion']); ?></td></tr>
    <tr><td>Amortización del anticipo (<?php echo number_format((float)$e['anticipo_pct'], 2); ?> %)</td><td>− <?php echo $m($e['amortizacion']); ?></td></tr>
    <tr class="neto"><td>Neto a cobrar</td><td><?php echo $m($e['neto']); ?></td></tr>
</table>

<?php if (trim((string)$e['notas']) !== ''): ?><div class="notas"><b>Notas:</b> <?php echo nl2br(s($e['notas'])); ?></div><?php endif; ?>

<p class="pie">Fondo retenido acumulado: <?php echo $m($resumen['retenido']); ?> · Anticipo pendiente de amortizar: <?php echo $m($resumen['anticipo_pendiente']); ?> · Documento generado desde ProjectWeb.</p>

<table class="firmas">
    <tr>
        <td>Elaboró<br><small><?php echo s($e['usuario'] ?: ''); ?></small></td>
        <td>Revisó / autorizó<br><small>Nombre y firma</small></td>
    </tr>
</table>
</body>
</html>
