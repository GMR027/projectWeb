<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 14mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .cab td { vertical-align: middle; }
    h1 { font-size: 14pt; text-align: center; margin: 0; }
    .datos td { border: 0.5pt solid #888; padding: 3pt 6pt; }
    .datos td.k { background: #e8edf3; font-weight: bold; width: 22%; }
    .mat th { background: #1f3a5f; color: #fff; padding: 4pt; border: 0.5pt solid #1f3a5f; }
    .mat td { border: 0.5pt solid #888; padding: 3pt 5pt; vertical-align: middle; }
    tr { page-break-inside: avoid; }
    .laminas-titulo { font-size: 11pt; margin: 14pt 0 6pt; padding: 4pt 6pt; background: #1f3a5f; color: #fff; }
    .lamina { page-break-inside: avoid; margin: 0 0 12pt; text-align: center; }
    .lamina h2 { font-size: 9.5pt; color: #1f3a5f; margin: 0 0 4pt; text-align: left; }
    .lamina img { max-width: 100%; max-height: 640pt; border: 0.5pt solid #aaa; }
</style></head><body>
<table class="cab"><tr>
    <td style="width:25%"><?php if ($logoE): ?><img src="<?php echo $logoE; ?>" style="max-height:40pt;max-width:120pt"><?php endif; ?></td>
    <td><h1>SUBMITTAL <?php echo s($submittal->numero_submittal); ?></h1></td>
    <td style="width:25%;text-align:right"><?php if ($logoC): ?><img src="<?php echo $logoC; ?>" style="max-height:40pt;max-width:120pt"><?php endif; ?></td>
</tr></table>
<br>
<table class="datos">
    <tr><td class="k">Fecha</td><td><?php echo date('d/m/Y', strtotime($submittal->fecha)); ?></td><td class="k">Número de trabajo</td><td><?php echo s($submittal->numero_trabajo); ?></td></tr>
    <tr><td class="k">Proyecto</td><td colspan="3"><?php echo s($proyecto->nombre); ?></td></tr>
    <tr><td class="k">Departamento</td><td><?php echo s($submittal->departamento); ?></td><td class="k">Para</td><td><?php echo s($submittal->para); ?></td></tr>
    <tr><td class="k">No. de submittal</td><td><?php echo s($submittal->numero_submittal); ?></td><td class="k">Especialidad</td><td><?php echo s($submittal->especialidad); ?></td></tr>
</table>
<br>
<table class="mat">
    <thead><tr><th style="width:70pt">Imagen</th><th>Material</th><th style="width:25%">Especialidad</th></tr></thead>
    <tbody>
    <?php foreach ($materiales as $m): $im = $img($m->imagen, 'uploads/materiales'); ?>
        <tr>
            <td style="text-align:center"><?php if ($im): ?><img src="<?php echo $im; ?>" style="max-height:48pt;max-width:60pt"><?php endif; ?></td>
            <td><?php echo s($m->nombre); ?><?php if ($m->referencia): ?><br><span style="color:#555;font-size:7.5pt">Ref. <?php echo s($m->referencia); ?></span><?php endif; ?></td>
            <td><?php echo s($m->especialidad); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php
$laminasPdf = [];
foreach (($laminas ?? []) as $l) {
    $im = $img($l->marcas_png, 'uploads/vplan');
    if ($im) $laminasPdf[] = [$l, $im];
}
?>
<?php if ($laminasPdf): ?>
<h2 class="laminas-titulo">Láminas adjuntas</h2>
<?php foreach ($laminasPdf as [$l, $im]): ?>
    <div class="lamina">
        <h2><?php echo s($l->grafico_nombre . ' · ' . $l->titulo((int)$l->orden)); ?><?php echo $l->categoria_nombre ? ' · ' . s($l->categoria_nombre) : ''; ?></h2>
        <img src="<?php echo $im; ?>" alt="">
    </div>
<?php endforeach; ?>
<?php endif; ?>
</body></html>
