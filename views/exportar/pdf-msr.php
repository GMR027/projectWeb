<?php
$colSt = ['A' => '#92D050', 'AN' => '#C5E384', 'P' => '#FFEB9C', 'RA' => '#F79646'];
$etq = ['A' => 'A', 'AN' => 'AN', 'P' => 'P', 'RA' => 'RA'];
$logoE = $img($logoEmpresa, 'build/img/logos');
$logoC = $img($proyecto->contratista_logo, 'build/img/logos');
$nomC = mb_strtoupper($proyecto->contratista ?: 'CONTRATISTA');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 7mm 7mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 5.6pt; color: #000; }
    table { border-collapse: collapse; width: 100%; }
    .cab td { vertical-align: middle; padding: 0; }
    .caja { border: 0.8pt solid #000; }
    .datos { width: 100%; } .datos td { border: 0.4pt solid #000; font-size: 5.6pt; padding: 1pt 3pt; }
    .leyenda td { border: 0.4pt solid #000; font-size: 5.4pt; padding: 1pt 3pt; }
    .tabla th, .tabla td { border: 1pt solid #000; padding: 1.2pt 2pt; }
    .tabla th { color: #fff; font-size: 5pt; text-align: center; font-weight: bold; }
    .barra th { font-size: 6.4pt; padding: 3pt; border: 0; }
    .sec td { background: #3F3F3F; color: #fff; font-weight: bold; font-size: 4.6pt; text-align: center; padding: 1pt; }
    .c { text-align: center; } .m { font-family: DejaVu Sans Mono, monospace; }
    .st { font-weight: bold; font-size: 6.4pt; }
    .crit { background: #FF99FF; font-weight: bold; } .atr { background: #fca5a5; }
    .prov { color: #1F497D; text-align: center; }
    thead { display: table-header-group; } tr { page-break-inside: avoid; }
</style></head><body>
<?php $encEst = isset($msr) && $msr ? $msr->encabezado() : null; if ($encEst): [$Wu, $Hu] = \Classes\Estampas::dimensiones($encEst->config()); $Kenc = 802.0 / $Wu; ?>
<div style="position:relative;width:802pt;height:<?php echo round($Hu * $Kenc, 2); ?>pt"><?php echo \Classes\Estampas::renderGuardada($encEst, $proyecto, null, ['lamina_n' => 1, 'lamina_total' => 1], 'pt', $Kenc); ?></div>
<?php else: ?>
<table class="cab">
    <tr>
        <td style="width:22%"><?php if ($logoC): ?><img src="<?php echo $logoC; ?>" style="max-height:38pt;max-width:130pt"><?php endif; ?></td>
        <td style="width:31%;padding-left:6pt">
            <div style="font-size:8pt;font-weight:bold">MSR -MATERIAL STATUS REPORT</div>
            <div style="font-size:8pt;font-weight:bold">REPORTE DEL STATUS DE LOS MATERIALES</div>
        </td>
        <td style="width:25%" class="caja c"><?php if ($logoE): ?><img src="<?php echo $logoE; ?>" style="max-height:36pt;max-width:110pt;margin:2pt"><?php endif; ?></td>
        <td style="width:22%;padding-left:6pt">
            <table class="datos">
                <tr><td colspan="2" style="text-align:right"><b>FECHA:</b> <b><?php echo s(fecha_larga_es()); ?></b></td></tr>
                <tr><td style="width:30%">CLIENTE:</td><td><b><?php echo s($proyecto->contratista); ?></b></td></tr>
                <tr><td>PROYECTO:</td><td><b><?php echo s($proyecto->nombre); ?></b></td></tr>
                <tr><td>JOB No.</td><td><b><?php echo s($proyecto->numero_trabajo); ?></b></td></tr>
                <tr><td>LOCALIZACIÓN:</td><td><b><?php echo s($proyecto->ubicacion); ?></b></td></tr>
            </table>
        </td>
    </tr>
    <tr>
        <td></td><td></td>
        <td style="padding-top:4pt">
            <div style="font-weight:bold;font-size:5.6pt">STATUS SUBMITTAL</div>
            <table class="leyenda">
                <tr><td class="c" style="background:#92D050;width:26pt"><b>A</b></td><td>Aprobado</td></tr>
                <tr><td class="c" style="background:#C5E384"><b>AN</b></td><td>Aprobado con anotaciones</td></tr>
                <tr><td class="c" style="background:#FFEB9C"><b>P</b></td><td>En revisión</td></tr>
                <tr><td class="c" style="background:#F79646"><b>RA</b></td><td>Resubmitar para aprobación</td></tr>
            </table>
        </td>
        <td style="padding:4pt 0 0 6pt;vertical-align:bottom">
            <table class="leyenda">
                <tr><td colspan="2"><b>Identificación tiempo de entrega</b></td></tr>
                <tr><td style="background:#FF99FF;width:26pt"></td><td>Tiempo de entrega de Material Crítico</td></tr>
            </table>
        </td>
    </tr>
</table>
<?php endif; ?>
<div style="height:5pt"></div>

<?php
$tinta = function ($hex) { $h = ltrim($hex, '#'); $l = (0.299 * hexdec(substr($h, 0, 2)) + 0.587 * hexdec(substr($h, 2, 2)) + 0.114 * hexdec(substr($h, 4, 2))) / 255; return $l > 0.62 ? '#000' : '#fff'; };
$anchos = ['imagen' => 'width:30pt', 'descripcion' => 'width:120pt', 'observaciones' => 'width:60pt'];
$nCols = max(1, count($columnas));
?>
<table class="tabla">
    <thead>
        <?php if ($grupos): ?>
        <tr class="barra"><?php foreach ($grupos as $g): ?><th colspan="<?php echo (int)$g['span']; ?>" style="<?php echo $g['n'] === '' ? 'background:#fff' : 'background:' . s($g['c']) . ';color:' . $tinta($g['c']); ?>"><?php echo s($g['n']); ?></th><?php endforeach; ?></tr>
        <?php endif; ?>
        <tr>
            <?php foreach ($columnas as $col): ?><th style="background:<?php echo s($col['c']); ?>;color:<?php echo $tinta($col['c']); ?>;<?php echo $anchos[$col['k']] ?? ''; ?>"><?php echo s($col['n']); ?></th><?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($secciones as $nombreSec => $lista): ?>
        <?php if ($nombreSec !== ''): $cs = $coloresSec[$nombreSec] ?? ''; ?><tr class="sec"><td colspan="<?php echo $nCols; ?>"<?php echo $cs ? ' style="background:' . s($cs) . ';color:' . $tinta($cs) . '"' : ''; ?>><?php echo s($nombreSec); ?></td></tr><?php endif; ?>
        <?php foreach ($lista as $it): $dias = $it->diasEntrega(); ?>
        <tr>
            <?php foreach ($columnas as $col): $k = $col['k']; ?>
                <?php if ($k === 'imagen'): $im = $img($it->mat_imagen, 'uploads/materiales'); ?>
                    <td class="c"><?php if ($im): ?><img src="<?php echo $im; ?>" style="max-height:26pt;max-width:26pt"><?php endif; ?></td>
                <?php elseif ($k === 'sub_numero'): ?><td class="c"><?php echo s($it->sub_numero); ?></td>
                <?php elseif ($k === 'referencia'): ?><td class="c"><?php echo s($it->mat_referencia); ?></td>
                <?php elseif ($k === 'descripcion'): ?><td><?php echo s($it->mat_nombre); ?></td>
                <?php elseif ($k === 'status'): ?><td class="c st" style="background:<?php echo $colSt[$it->status] ?? '#fff'; ?>"><?php echo $etq[$it->status] ?? s($it->status); ?></td>
                <?php elseif ($k === 'dias'): ?><td class="c <?php echo $it->esCritico($umbral) ? 'crit' : ''; ?>"><?php echo $dias !== null ? number_format($dias, 2) : ''; ?></td>
                <?php elseif (str_starts_with($k, 'fecha')): ?><td class="c"><?php echo s(fecha_corta_es($it->$k)); ?></td>
                <?php elseif (str_starts_with($k, 'txt_')): ?><td><?php echo s($it->extra($k)); ?></td>
                <?php elseif ($k === 'proveedor'): ?><td class="prov"><?php echo s($it->proveedor); ?></td>
                <?php else: ?><td class="<?php echo $k === 'no_submittal_contratista' ? 'c' : ''; ?>"><?php echo s($it->$k); ?></td>
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
</body></html>
