<?php
$logoE = $img($logoEmpresa, 'build/img/logos');
$logoC = $img($proyecto->contratista_logo, 'build/img/logos');
$col = ['baja' => '#6b7280', 'media' => '#0e7490', 'alta' => '#d97706', 'critica' => '#dc2626'];
$nombres = fn(array $l, callable $det) => array_map(fn($p) => ['n' => $p->nombre, 'd' => $det($p)], $l);
$co = $nombres($contratista, fn($p) => trim($p->puesto . ($p->puesto && $p->empresa ? ' · ' : '') . $p->empresa));
$pr = $nombres($personal, fn($p) => (string)$p->departamento);
$tabla = function (string $titulo, string $col1, array $filas) { ?>
    <h3><?php echo s($titulo); ?></h3>
    <table class="t"><thead><tr><th style="width:46%"><?php echo s($col1); ?></th><th style="width:27%">Responsable</th><th>Departamento</th></tr></thead><tbody>
    <?php foreach ($filas as $f): ?><tr><td><?php echo nl2br(s($f['tema'] ?? '')); ?></td><td><?php echo s($f['responsable'] ?? ''); ?></td><td><?php echo s($f['departamento'] ?? ''); ?></td></tr><?php endforeach; ?>
    </tbody></table>
<?php };
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 14mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    h1 { font-size: 14pt; margin: 8pt 0 2pt; } h3 { font-size: 10pt; margin: 14pt 0 4pt; border-bottom: 1.2pt solid #333; padding-bottom: 2pt; }
    .cab td { vertical-align: middle; padding: 0; }
    .d td { border: 0.5pt solid #999; padding: 3pt 5pt; } .d td.k { background: #eee; width: 22%; font-weight: bold; }
    .t th { background: #333; color: #fff; font-size: 8pt; padding: 3pt 5pt; text-align: left; }
    .t td { border: 0.5pt solid #999; padding: 3pt 5pt; vertical-align: top; }
    tr { page-break-inside: avoid; }
    .peq { font-size: 7.5pt; color: #555; }
</style></head><body>
<table class="cab"><tr>
    <td style="width:30%"><?php if ($logoC): ?><img src="<?php echo $logoC; ?>" style="max-height:34pt;max-width:120pt"><?php endif; ?></td>
    <td style="text-align:center;font-weight:bold;font-size:11pt">MINUTA DE JUNTA</td>
    <td style="width:30%;text-align:right"><?php if ($logoE): ?><img src="<?php echo $logoE; ?>" style="max-height:34pt;max-width:120pt"><?php endif; ?></td>
</tr></table>
<h1><?php echo s($minuta->nombre); ?></h1>
<table class="d">
    <tr><td class="k">Fecha</td><td><?php echo s(fecha_larga_es($minuta->fecha)); ?></td><td class="k">Importancia</td><td style="font-weight:bold;color:<?php echo $col[$minuta->importancia] ?? '#000'; ?>"><?php echo s(mb_strtoupper(\Model\Bitacora::IMPORTANCIAS[$minuta->importancia] ?? '')); ?></td></tr>
    <tr><td class="k">Proyecto</td><td><?php echo s($proyecto->nombre); ?></td><td class="k">No. de trabajo</td><td><?php echo s($proyecto->numero_trabajo); ?></td></tr>
</table>

<?php if ($co || $pr): ?>
<h3>Asistentes</h3>
<table><tr>
    <td style="width:50%;vertical-align:top;padding-right:8pt"><b>Contratista</b>
        <?php foreach ($co as $x): ?><div><?php echo s($x['n']); ?><?php if ($x['d']): ?> <span class="peq">· <?php echo s($x['d']); ?></span><?php endif; ?></div><?php endforeach; ?>
        <?php if (!$co): ?><div class="peq">—</div><?php endif; ?></td>
    <td style="width:50%;vertical-align:top"><b>Proyecto</b>
        <?php foreach ($pr as $x): ?><div><?php echo s($x['n']); ?><?php if ($x['d']): ?> <span class="peq">· <?php echo s($x['d']); ?></span><?php endif; ?></div><?php endforeach; ?>
        <?php if (!$pr): ?><div class="peq">—</div><?php endif; ?></td>
</tr></table>
<?php endif; ?>

<?php if ($temas) $tabla('Temas vistos', 'Tema', $temas); ?>
<?php if ($compromisos) $tabla('Compromisos', 'Compromiso', $compromisos); ?>
<div class="peq" style="margin-top:16pt">Generado por ProjectWeb · <?php echo date('d/m/Y H:i'); ?></div>
</body></html>
