<?php
/** PDF del presupuesto. Variables: $proyecto, $datos, $logoE, $logoC */
$t = $datos['totales'];
$cargos = $datos['cargos'];
$m = fn($n) => '$' . number_format((float)$n, 2);
$n = fn($v, $d = 3) => number_format((float)$v, $d);
$grupos = $datos['grupos'];
if ($datos['sinPartida']) $grupos[] = $datos['sinPartida'];
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8">
<style>
    @page { margin: 12mm 10mm 14mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .cab { margin-bottom: 8pt; }
    .cab h1 { font-size: 15pt; margin: 0; text-align: center; }
    .cab p { margin: 2pt 0 0; text-align: center; color: #475569; font-size: 8pt; }
    .datos { margin: 8pt 0 10pt; }
    .datos td { border: 0.5pt solid #94a3b8; padding: 3pt 5pt; vertical-align: top; }
    .datos td.k { background: #eef2f7; font-weight: bold; width: 18%; }
    .conceptos th { background: #1f3a5f; color: #fff; font-size: 7.5pt; padding: 4pt 3pt; border: 0.5pt solid #64748b; }
    .conceptos td { border: 0.5pt solid #b6c2cf; padding: 3pt 4pt; vertical-align: top; }
    .conceptos td.num { text-align: right; font-family: DejaVu Sans Mono, monospace; white-space: nowrap; }
    .partida td { background: #e2e8f0; font-weight: bold; font-size: 8.2pt; }
    .subtotal td { background: #f1f5f9; font-weight: bold; }
    .resumen { width: 55%; margin-left: 45%; margin-top: 10pt; }
    .resumen td { border: 0.5pt solid #b6c2cf; padding: 3pt 5pt; }
    .resumen td.k { text-align: right; }
    .resumen tr.total td { background: #e2e8f0; font-weight: bold; font-size: 9.5pt; }
    .nota { margin-top: 8pt; font-size: 7pt; color: #64748b; }
    .pie { margin-top: 26pt; }
    .pie td { text-align: center; font-size: 7.5pt; padding-top: 22pt; border-top: 0.5pt solid #94a3b8; width: 45%; }
</style></head><body>

<table class="cab"><tr>
    <td style="width:22%"><?php if ($logoE): ?><img src="<?php echo $logoE; ?>" style="max-height:44pt;max-width:120pt"><?php endif; ?></td>
    <td>
        <h1>Presupuesto de obra</h1>
        <p><?php echo s($proyecto->nombre); ?><?php echo $proyecto->codigo ? ' · ' . s($proyecto->codigo) : ''; ?></p>
        <p>Catálogo de conceptos</p>
    </td>
    <td class="text-right" style="width:22%;text-align:right"><?php if ($logoC): ?><img src="<?php echo $logoC; ?>" style="max-height:44pt;max-width:120pt"><?php endif; ?></td>
</tr></table>

<table class="datos">
    <tr>
        <td class="k">Proyecto</td><td><?php echo s($proyecto->nombre); ?></td>
        <td class="k">Código</td><td><?php echo s($proyecto->codigo ?: '—'); ?></td>
    </tr>
    <tr>
        <td class="k">Ubicación</td><td><?php echo s($proyecto->ubicacion ?: '—'); ?></td>
        <td class="k">Fecha</td><td><?php echo date('d/m/Y'); ?></td>
    </tr>
</table>

<table class="conceptos">
    <thead><tr>
        <th style="width:11%">Clave</th><th>Concepto</th><th style="width:7%">Unidad</th>
        <th style="width:9%">Cantidad</th><th style="width:11%">P. unitario</th><th style="width:12%">Importe</th>
        <th style="width:11%">Programado</th><th style="width:10%">Pendiente</th>
    </tr></thead>
    <tbody>
    <?php foreach ($grupos as $g): ?>
        <tr class="partida"><td colspan="8"><?php echo s($g['codigo'] . ' · ' . $g['nombre']); ?><?php echo !empty($g['ajuste']) ? ' · ajuste ' . $m($g['ajuste']) : ''; ?></td></tr>
        <?php foreach ($g['conceptos'] as $c): ?>
            <tr>
                <td class="mono"><?php echo s($c['clave'] ?: '—'); ?></td>
                <td><?php echo s($c['descripcion']); ?><?php if ($c['excedido']): ?> <strong>(excedido)</strong><?php endif; ?></td>
                <td><?php echo s($c['unidad'] ?: '—'); ?></td>
                <td class="num"><?php echo $n($c['cantidad']); ?></td>
                <td class="num"><?php echo $m($c['precio_unitario']); ?></td>
                <td class="num"><?php echo $m($c['importe']); ?></td>
                <td class="num"><?php echo $m($c['importe_programado']); ?></td>
                <td class="num"><?php echo $m($c['pendiente']); ?></td>
            </tr>
        <?php endforeach; ?>
        <tr class="subtotal"><td colspan="5" style="text-align:right">Subtotal <?php echo s($g['codigo']); ?></td><td class="num"><?php echo $m($g['subtotal']); ?></td><td class="num"><?php echo $m($g['programado']); ?></td><td class="num"><?php echo $m($g['pendiente']); ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<table class="resumen">
    <tr><td class="k">Subtotal del catálogo</td><td class="num" style="text-align:right"><?php echo $m($t['directo']); ?></td></tr>
    <?php if (!empty($t['manual'])): ?><tr><td class="k">Partidas con presupuesto manual (sin conceptos)</td><td class="num" style="text-align:right"><?php echo $m($t['manual']); ?></td></tr><?php endif; ?>
    <?php if ($t['ajustes']): ?><tr><td class="k">Ajustes manuales por partida</td><td class="num" style="text-align:right"><?php echo $m($t['ajustes']); ?></td></tr><?php endif; ?>
    <?php if ($t['cambios']): ?><tr><td class="k">Órdenes de cambio autorizadas</td><td class="num" style="text-align:right"><?php echo $m($t['cambios']); ?></td></tr><?php endif; ?>
    <?php if ($cargos['indirectos']): ?><tr><td class="k">Indirectos (<?php echo $n($cargos['indirectos'], 2); ?> %)</td><td class="num" style="text-align:right"><?php echo $m($t['indirectos']); ?></td></tr><?php endif; ?>
    <?php if ($cargos['financiamiento']): ?><tr><td class="k">Financiamiento (<?php echo $n($cargos['financiamiento'], 2); ?> %)</td><td class="num" style="text-align:right"><?php echo $m($t['financiamiento']); ?></td></tr><?php endif; ?>
    <?php if ($cargos['utilidad']): ?><tr><td class="k">Utilidad (<?php echo $n($cargos['utilidad'], 2); ?> %)</td><td class="num" style="text-align:right"><?php echo $m($t['utilidad']); ?></td></tr><?php endif; ?>
    <tr class="total"><td class="k">Total<?php echo $t['iva_en_bac'] ? ' (con IVA)' : ''; ?></td><td class="num" style="text-align:right"><?php echo $m($t['total']); ?></td></tr>
    <?php if (!$t['iva_en_bac']): ?><tr><td class="k">IVA (<?php echo $n($cargos['iva'], 2); ?> %)</td><td class="num" style="text-align:right"><?php echo $m($t['iva']); ?></td></tr><?php endif; ?>
</table>

<p class="nota">Importes en la moneda del proyecto. El precio unitario es costo directo: no incluye IVA ni cargos, salvo que se indique en el resumen. «Programado» es lo que ejecutan las actividades del programa y «Pendiente» lo que falta por programar.</p>

<table class="pie"><tr>
    <td>Elaboró</td>
    <td style="border:0"></td>
    <td>Revisó / autorizó</td>
</tr></table>

</body></html>
