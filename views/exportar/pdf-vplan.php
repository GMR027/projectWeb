<?php
// Variables: $paginas, $proyecto, $logoC, $logoE
use Model\VplanTabla;
$fd = fn($f) => $f ? date('d/m/Y', strtotime($f)) : '';
$meses = ['', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
$actualizado = $meses[(int)date('n')] . ' | ' . date('d') . ' | ' . date('Y');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 0.9cm 1cm; }
    body { font-family: DejaVu Sans, sans-serif; color: #000; margin: 0; }
    .hoja { position: relative; height: 25.9cm; page-break-after: always; }
    .hoja.ultima { page-break-after: auto; }
    table { border-collapse: collapse; }
    .cab { width: 100%; } .cab td { vertical-align: middle; padding: 0; }
    .titulo { text-align: center; font-weight: bold; font-size: 20pt; line-height: 1.1; }
    .titulo small { display: block; font-size: 22pt; }
    .plano { width: 20.1cm; text-align: center; }
    .plano img { border: 1pt solid #444; }
    .fija { position: absolute; left: 0; bottom: 0.9cm; width: 20.1cm; }
    .fija td { padding: 0; border: none; }
    .t { width: 100%; margin-bottom: 7pt; }
    .t th, .t td { border: 0.7pt solid #000; padding: 1.5pt 3pt; vertical-align: middle; }
    .t th { background: #5b82b4; color: #fff; text-align: center; font-weight: bold; }
    .c { text-align: center; }
    .e0 { font-size: 9pt; } .e1 { font-size: 7.5pt; } .e2 { font-size: 6.2pt; } .e3 { font-size: 5.2pt; } .e4 { font-size: 4.4pt; }
    .e0 .t th, .e0 .t td { padding: 3pt 4pt; } .e1 .t th, .e1 .t td { padding: 2pt 3pt; } .e2 .t th, .e2 .t td { padding: 1.5pt 3pt; } .e3 .t th, .e3 .t td { padding: 0.9pt 2pt; } .e4 .t th, .e4 .t td { padding: 0.5pt 1.5pt; }
    .tit { font-size: 1.25em; letter-spacing: 0.04em; }
    .punch th { background: #fff0c4; color: #000; } .sec th { background: #bfe3f7; color: #000; } .otro th { background: #d9d9d9; color: #000; }
    .verde { background: #059669; } .amarillo { background: #d97706; } .rojo { background: #dc2626; color: #fff; }
    .rojotxt { color: #dc2626; }
    .rb { width: 1pt; }
    .leg { border: 0.8pt solid #000; background: #fff; } .leg td { border: none; padding: 0 4pt; font-size: 7pt; white-space: nowrap; } .leg td.sw { border: 0.7pt solid #000; width: 22pt; } .leg td.leg-tit { text-decoration: underline; font-weight: bold; padding: 2pt 4pt; border-bottom: 0.8pt solid #000; }
    .pie { position: absolute; bottom: 0; left: 0; width: 100%; font-size: 7.5pt; color: #dc2626; }
    .pie td { border: none; padding: 0; }
</style></head><body>
<?php foreach ($paginas as $pi => $d): $l = $d['lamina']; $rec1 = $d['rec1']; $rec2 = $d['rec2']; ?>
<style>.pc<?php echo $pi; ?> th { background: <?php echo s($d['colorTabla'][0]); ?>; color: <?php echo s($d['colorTabla'][1]); ?>; }</style>
<div class="hoja <?php echo $pi === count($paginas) - 1 ? 'ultima' : ''; ?>">
    <table class="cab"><tr>
        <td style="width:20%"><?php if ($logoC): ?><img src="<?php echo $logoC; ?>" style="max-height:48pt;max-width:150pt"><?php endif; ?></td>
        <td class="titulo"><?php echo s($proyecto->codigo ?: $proyecto->nombre); ?><small><?php echo s(mb_strtoupper($l->titulo)); ?></small></td>
        <td style="width:20%;text-align:right"><?php if ($logoE): ?><img src="<?php echo $logoE; ?>" style="max-height:48pt;max-width:150pt"><?php endif; ?></td>
    </tr></table>
    <div style="height:6pt"></div>
    <table style="width:100%"><tr>
        <td style="width:50%;vertical-align:top;padding-right:0.4cm">
            <?php // Plano centrado en su mitad; si es angosto queda al centro ?>
            <div class="plano"><?php if ($d['plano'] && $d['plano_dim']): ?><img src="<?php echo $d['plano']; ?>" style="width:<?php echo $d['plano_dim'][0]; ?>cm;height:<?php echo $d['plano_dim'][1]; ?>cm"><?php else: ?>&nbsp;<?php endif; ?></div>
        </td>
        <td style="vertical-align:top" class="<?php echo $d['escala']; ?>">
            <table class="t pc<?php echo $pi; ?>">
                <tr><th colspan="<?php echo 6 + 2 * ((int)$rec1 + (int)$rec2); ?>" class="tit"><?php echo s(mb_strtoupper($d['partida'] ? $d['partida']->nombre : '')); ?></th></tr>
                <?php $rs = ($rec1 || $rec2) ? 2 : 1; ?>
                <tr><th rowspan="<?php echo $rs; ?>"># SEQ</th><th rowspan="<?php echo $rs; ?>" style="width:30%">TASK</th><th rowspan="<?php echo $rs; ?>">START</th><th rowspan="<?php echo $rs; ?>">FINISH</th><?php if ($rec1): ?><th colspan="2">RECOVERY</th><?php endif; ?><?php if ($rec2): ?><th colspan="2">RECOVERY 02</th><?php endif; ?><th rowspan="<?php echo $rs; ?>">%</th><th rowspan="<?php echo $rs; ?>">CONSTRAINS</th></tr>
                <?php if ($rec1 || $rec2): ?><tr><?php if ($rec1): ?><th>START</th><th>FINISH</th><?php endif; ?><?php if ($rec2): ?><th>START</th><th>FINISH</th><?php endif; ?></tr><?php endif; ?>
                <?php foreach ($d['tareas'] as $t): $a = $t['actividad']; $p = (int)$a->progreso; ?>
                    <tr>
                        <td class="c"><?php echo (int)$t['seq']; ?></td>
                        <td><?php echo s($a->nombre); ?></td>
                        <td class="c"><?php echo s($fd($a->fecha_inicio)); ?></td><td class="c"><?php echo s($fd($a->fecha_fin)); ?></td>
                        <?php if ($rec1): ?><td class="c"><?php echo s($fd($t['r1_inicio'])); ?></td><td class="c"><?php echo s($fd($t['r1_fin'])); ?></td><?php endif; ?>
                        <?php if ($rec2): ?><td class="c rojotxt"><?php echo s($fd($t['r2_inicio'])); ?></td><td class="c rojotxt"><?php echo s($fd($t['r2_fin'])); ?></td><?php endif; ?>
                        <td class="c <?php echo $p >= 100 ? 'verde' : ($p > 0 ? 'amarillo' : ''); ?>"><?php echo $p; ?>%</td>
                        <td class="c"><?php if ($t['restricciones']): foreach ($t['restricciones'] as $n): ?><img src="<?php echo \Classes\Exportadores\VplanPdfExporter::rombo((int)$n); ?>" style="width:13pt"> <?php endforeach; elseif ($t['estado'] === 'completo'): ?>COMPLETE<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php foreach ($d['tablas'] as $b): $tb = $b['tabla']; $campos = $b['campos']; ?>
                <?php if ($tb->tipo === 'punch'): ?>
                    <table class="t punch">
                        <tr><th rowspan="2" colspan="2" class="tit"><?php echo s($tb->titulo); ?></th><th colspan="2">DATE</th><th rowspan="2">%</th></tr>
                        <tr><th>START</th><th>FINISH</th></tr>
                        <?php foreach ($b['filas'] as $f): $p = $f['pct'] ?? ''; ?>
                            <tr><td class="c" style="width:6%"><?php echo (int)$f['n']; ?></td><td><?php echo s($f['descripcion'] ?? ''); ?></td><td class="c"><?php echo s($fd($f['inicio'] ?? '')); ?></td><td class="c"><?php echo s($fd($f['fin'] ?? '')); ?></td>
                                <td class="c <?php echo $p !== '' && (int)$p >= 100 ? 'verde' : ($p !== '' && (int)$p > 0 ? 'amarillo' : ''); ?>"><?php echo $p === '' ? '' : (int)$p . '%'; ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php elseif ($tb->tipo === 'restricciones'): ?>
                    <table class="t">
                        <tr><th colspan="2" class="tit"><?php echo s($tb->titulo); ?></th></tr>
                        <?php foreach ($b['filas'] as $f): ?>
                            <tr><td class="c" style="width:9%"><img src="<?php echo \Classes\Exportadores\VplanPdfExporter::rombo((int)$f['n']); ?>" style="width:14pt"></td><td><?php echo s($f['descripcion'] ?? ''); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php elseif ($tb->tipo === 'secundarias'): ?>
                    <table class="t sec">
                        <tr><th colspan="3" class="tit"><?php echo s($tb->titulo); ?></th></tr>
                        <tr><th>ELEMENTO</th><th>FECHA</th><th>FECHA (RECOVERY)</th></tr>
                        <?php foreach ($b['filas'] as $f): $est = $f['estado'] ?? 'proceso'; $cl = ['completo' => 'verde', 'proceso' => 'amarillo', 'espera' => 'rojo'][$est] ?? ''; ?>
                            <tr class="<?php echo $cl; ?>"><td class="<?php echo $cl; ?>"><?php echo s($f['elemento'] ?? ''); ?></td><td class="c <?php echo $cl; ?>"><?php echo s($f['fecha'] ?? ''); ?></td><td class="c <?php echo $cl; ?>"><?php echo s($f['recovery'] ?? ''); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <table class="t otro">
                        <tr><th colspan="<?php echo count($campos) + 1; ?>" class="tit"><?php echo s($tb->titulo); ?></th></tr>
                        <tr><th>N.</th><?php foreach ($campos as [$etq]): ?><th><?php echo s($etq); ?></th><?php endforeach; ?></tr>
                        <?php foreach ($b['filas'] as $f): ?>
                            <tr><td class="c"><?php echo (int)$f['n']; ?></td><?php foreach ($campos as $k => $c): ?><td><?php echo s($f[$k] ?? ''); ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>
        </td>
    </tr></table>
    <?php // Simbología y SYMBOLOGY FOR SCHEDULE: fijas abajo, justo encima del supervisor ?>
    <div class="fija"><table style="width:100%"><tr>
        <td style="vertical-align:bottom;width:60%"><?php if ($d['simb']): ?><img src="<?php echo $d['simb']; ?>" style="max-height:4.6cm;max-width:12cm"><?php endif; ?></td>
        <td style="vertical-align:bottom;text-align:right">
            <table class="leg" align="right" style="width:5.6cm">
                <tr><td colspan="2" class="leg-tit">SYMBOLOGY FOR SCHEDULE</td></tr>
                <tr><td class="sw verde"></td><td>COMPLETE</td></tr>
                <tr><td class="sw amarillo"></td><td>IN PROGRESS</td></tr>
                <tr><td class="sw rojo"></td><td>ON HOLD</td></tr>
                <tr><td class="sw c"><img src="<?php echo \Classes\Exportadores\VplanPdfExporter::rombo(0); ?>" style="width:9pt"></td><td>CONSTRAIN</td></tr>
            </table>
        </td>
    </tr></table></div>
    <table class="pie"><tr><td><?php echo s($l->supervisor ? 'SUPERVISOR: ' . $l->supervisor : ''); ?></td><td style="text-align:right">UPDATED: <?php echo s($actualizado); ?></td></tr></table>
</div>
<?php endforeach; ?>
</body></html>
