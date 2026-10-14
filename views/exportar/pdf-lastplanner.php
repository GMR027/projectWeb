<?php
// Variables: $paginas (arrays de tarjetas), $cols (2|3), $logo (data URI), $proyecto
$dias = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
$meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$gap = $cols === 3 ? 0.12 : 0.6;              // cm entre tarjetas
$anchoGrid = $cols * 7 + ($cols - 1) * $gap;
$altoGrid = 3 * 7 + 2 * $gap;
$x0 = (21.59 - $anchoGrid) / 2;
$y0 = (27.94 - $altoGrid) / 2;
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #000; }
    .hoja { position: relative; width: 21.59cm; height: 27.9cm; page-break-after: always; }
    .hoja.ultima { page-break-after: auto; }
    .grid { position: absolute; left: <?php echo $x0; ?>cm; top: <?php echo $y0; ?>cm; border-collapse: collapse; }
    .grid td { padding: 0; vertical-align: top; }
    .corte-v { position: absolute; top: 0; width: 0.02cm; height: 27.9cm; border-left: 0.6pt dashed #777; }
    .corte-h { position: absolute; left: 0; width: 21.59cm; border-top: 0.6pt dashed #777; }
    .tarjeta { width: 7cm; height: 7cm; overflow: hidden; border: 1.4pt solid #000; }
    .tarjeta table { border-collapse: collapse; width: 100%; }
    .tarjeta td { padding: 0.1cm 0.15cm; vertical-align: top; }
    .rot { font-size: 4.5pt; display: block; }
    .sup td { height: 1.0cm; border-bottom: 1.4pt solid #000; }
    .sup td + td, .inf td + td { border-left: 1.4pt solid #000; }
    .inf td { height: 1.75cm; border-top: 1.4pt solid #000; }
    .nom { font-size: 6.4pt; line-height: 1.15; }
    .act td { border-bottom: 0.5pt dotted #888; padding: 0.06cm 0.1cm; vertical-align: middle; }
    .dias { font-size: 10pt; font-weight: bold; text-align: center; width: 1.1cm; }
    .verde { color: #059669; } .amarillo { color: #d97706; } .rojo { color: #dc2626; }
    .rb { font-size: 14pt; line-height: 0.8; }      /* rombos de las actividades */
    .ind { font-size: 4.4pt; line-height: 1.2; white-space: nowrap; } .ind .rb { font-size: 11pt; }
    .leyenda { position: absolute; left: 0.8cm; top: 0.35cm; font-size: 6pt; color: #444; }
</style></head><body>
<?php foreach ($paginas as $pi => $grupo): ?>
    <div class="hoja <?php echo $pi === count($paginas) - 1 ? 'ultima' : ''; ?>">
        <div class="leyenda"><?php echo s($proyecto->nombre); ?> · <span class="verde">◆</span> finalizada · <span class="amarillo">◆</span> en proceso · <span class="rojo">◆</span> con restricción</div>
        <?php for ($c = 1; $c < $cols; $c++): ?><div class="corte-v" style="left:<?php echo number_format($x0 + $c * 7 + ($c - 0.5) * $gap, 2, '.', ''); ?>cm"></div><?php endfor; ?>
        <?php for ($r = 1; $r < 3; $r++): ?><div class="corte-h" style="top:<?php echo number_format($y0 + $r * 7 + ($r - 0.5) * $gap, 2, '.', ''); ?>cm"></div><?php endfor; ?>
        <table class="grid">
        <?php for ($r = 0; $r < 3; $r++): ?>
            <tr>
            <?php for ($c = 0; $c < $cols; $c++): $t = $grupo[$r * $cols + $c] ?? null; ?>
                <td style="width:7cm;height:7cm;<?php echo $c < $cols - 1 ? 'padding-right:' . $gap . 'cm;' : ''; ?><?php echo $r < 2 ? 'padding-bottom:' . $gap . 'cm;' : ''; ?>">
                <?php if ($t): $ts = strtotime($t['fecha']); ?>
                    <div class="tarjeta">
                        <table style="height:7cm">
                            <tr class="sup">
                                <td style="width:55%"><span class="rot">PARTIDA:</span><b style="font-size:6.6pt"><?php echo $t['partida'] ? s(mb_strimwidth($t['partida']->nombre, 0, 44, '…')) : 'TODAS LAS PARTIDAS'; ?></b></td>
                                <td><span class="rot">FECHA:</span><b style="font-size:10pt"><?php echo date('d/m/Y', $ts); ?></b></td>
                            </tr>
                            <tr><td colspan="2" style="height:3.9cm">
                                <table><tr><td style="padding:0;border:0"><span class="rot">ACTIVIDAD:</span></td><td style="padding:0;border:0;text-align:right"><span class="rot">DÍAS PARA TERMINAR:</span></td></tr></table>
                                <table class="act">
                                <?php foreach ($t['items'] as $it): ?>
                                    <tr>
                                        <td class="nom">
                                            <?php if ($it['completada']): ?><span class="verde rb">◆</span> <?php endif; ?><?php if ($it['proceso']): ?><span class="amarillo rb">◆</span> <?php endif; ?><?php if ($it['restriccion']): ?><span class="rojo rb">◆</span> <?php endif; ?><?php if (!empty($it['partida'])): ?><span style="font-family:DejaVu Sans Mono,monospace;font-size:5pt;font-weight:bold;background:#e5e7eb">&nbsp;<?php echo s($it['partida']->codigo); ?>&nbsp;</span> <?php endif; ?><?php echo s(mb_strimwidth($it['actividad']->nombre, 0, 78, '…')); ?>
                                        </td>
                                        <td class="dias"><?php echo (int)$it['dias']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </table>
                            </td></tr>
                            <tr class="inf">
                                <td style="width:55%"><span class="rot">COMPAÑÍA:</span><?php if ($logo): ?><img src="<?php echo $logo; ?>" style="max-height:0.8cm;max-width:3cm"><?php endif; ?></td>
                                <td style="vertical-align:middle">
                                    <div class="ind"><span class="verde rb">◆</span> Finalizada</div>
                                    <div class="ind"><span class="amarillo rb">◆</span> En proceso</div>
                                    <div class="ind"><span class="rojo rb">◆</span> Con restricción</div>
                                    <?php if ($t['partes'] > 1): ?><span class="rot" style="text-align:right"><?php echo $t['parte'] . '/' . $t['partes']; ?></span><?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
                </td>
            <?php endfor; ?>
            </tr>
        <?php endfor; ?>
        </table>
    </div>
<?php endforeach; ?>
</body></html>
