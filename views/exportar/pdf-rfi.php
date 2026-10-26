<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 14mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .cab td { vertical-align: middle; }
    h1 { font-size: 14pt; text-align: center; margin: 0; }
    .datos td { border: 0.5pt solid #888; padding: 3pt 6pt; vertical-align: top; }
    .datos td.k { background: #e8edf3; font-weight: bold; width: 22%; }
    .texto { border: 0.5pt solid #888; padding: 6pt; margin-top: 8pt; }
    .texto h2 { font-size: 9.5pt; color: #1f3a5f; margin: 0 0 4pt; }
    .texto p { margin: 0; white-space: pre-wrap; }
    .laminas-titulo { font-size: 11pt; margin: 14pt 0 6pt; padding: 4pt 6pt; background: #1f3a5f; color: #fff; }
    .lamina { page-break-inside: avoid; margin: 0 0 12pt; text-align: center; }
    .lamina h2 { font-size: 9.5pt; color: #1f3a5f; margin: 0 0 4pt; text-align: left; }
    .lamina img { max-width: 100%; max-height: 640pt; border: 0.5pt solid #aaa; }
</style></head><body>
<?php
$prioridades = \Classes\Rfi::PRIORIDADES;
$estados = \Classes\Rfi::ESTADOS;
?>
<table class="cab"><tr>
    <td style="width:25%"><?php if ($logoE): ?><img src="<?php echo $logoE; ?>" style="max-height:40pt;max-width:120pt"><?php endif; ?></td>
    <td><h1>RFI <?php echo s($rfi['numero']); ?></h1></td>
    <td style="width:25%;text-align:right"><?php if ($logoC): ?><img src="<?php echo $logoC; ?>" style="max-height:40pt;max-width:120pt"><?php endif; ?></td>
</tr></table>
<br>
<table class="datos">
    <tr><td class="k">Proyecto</td><td colspan="3"><?php echo s($proyecto->nombre); ?></td></tr>
    <tr><td class="k">Asunto</td><td colspan="3"><strong><?php echo s($rfi['asunto']); ?></strong></td></tr>
    <tr><td class="k">Fecha</td><td><?php echo $rfi['fecha'] ? date('d/m/Y', strtotime($rfi['fecha'])) : '—'; ?></td><td class="k">Fecha límite</td><td><?php echo $rfi['fecha_limite'] ? date('d/m/Y', strtotime($rfi['fecha_limite'])) : '—'; ?></td></tr>
    <tr><td class="k">Solicitante</td><td><?php echo s($rfi['solicitante']); ?></td><td class="k">Responsable</td><td><?php echo s($rfi['responsable']); ?></td></tr>
    <tr><td class="k">Prioridad</td><td><?php echo s($prioridades[$rfi['prioridad']] ?? $rfi['prioridad']); ?></td><td class="k">Estado</td><td><?php echo s($estados[$rfi['estado']] ?? $rfi['estado']); ?><?php if (!empty($rfi['fecha_respuesta'])): ?> · <?php echo date('d/m/Y', strtotime($rfi['fecha_respuesta'])); ?><?php endif; ?></td></tr>
    <tr><td class="k">Actividad que bloquea</td><td colspan="3"><?php echo $rfi['actividad_id'] ? s(($rfi['act_codigo'] ?? '') . ' ' . ($rfi['act_nombre'] ?? '')) : '—'; ?></td></tr>
</table>

<div class="texto">
    <h2>Pregunta</h2>
    <p><?php echo s($rfi['pregunta'] ?: '—'); ?></p>
</div>
<div class="texto">
    <h2>Respuesta</h2>
    <p><?php echo s($rfi['respuesta'] ?: 'Sin respuesta todavía.'); ?></p>
</div>

<?php
$laminasPdf = [];
foreach ($laminas as $l) {
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
