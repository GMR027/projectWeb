<?php
/** PDF de envío. Variables: $proyecto, $t */
use Classes\Transmittals;
$f = fn($v) => $v ? date('d/m/Y', strtotime((string)$v)) : '—';
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8">
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
    h2 { font-size: 10.5pt; margin: 14px 0 4px; }
    .firmas { width: 100%; margin-top: 36px; border-collapse: collapse; }
    .firmas td { width: 50%; text-align: center; padding-top: 28px; border-top: 1px solid #334155; font-size: 8pt; }
    .pie { margin-top: 14px; color: #64748b; font-size: 7pt; }
</style></head>
<body>
<h1>Envío <?php echo s($t['numero']); ?></h1>
<p class="sub"><?php echo s($proyecto->nombre); ?> · <?php echo $f($t['fecha']); ?> · <?php echo s(Transmittals::MEDIOS[$t['medio']] ?? $t['medio']); ?></p>
<div class="franja">
    <div><small>De</small><b><?php echo s($t['de']); ?></b></div>
    <div><small>Asunto</small><b><?php echo s($t['asunto']); ?></b></div>
    <div><small>Acuse hasta</small><b><?php echo $f($t['fecha_limite']); ?></b></div>
</div>
<?php if (trim((string)$t['mensaje']) !== ''): ?><p><?php echo nl2br(s($t['mensaje'])); ?></p><?php endif; ?>

<h2>Documentos y revisión</h2>
<table><thead><tr><th>Documento</th><th>Revisión</th></tr></thead><tbody>
<?php foreach ($t['documentos'] as $d): ?><tr><td><?php echo s($d['nombre']); ?></td><td><?php echo s($d['revision'] ?: '—'); ?></td></tr><?php endforeach; ?>
</tbody></table>

<h2>Destinatarios y acuses</h2>
<table><thead><tr><th>Destinatario</th><th>Estado</th><th>Acuse / fecha</th></tr></thead><tbody>
<?php $estados = ['pendiente' => 'Pendiente', 'recibido' => 'Recibido conforme', 'observado' => 'Con observaciones', 'rechazado' => 'No recibido']; ?>
<?php foreach ($t['destinatarios'] as $d): ?><tr><td><?php echo s($d['nombre']); ?><?php echo $d['email'] ? '<br><small>' . s($d['email']) . '</small>' : ''; ?></td><td><?php echo s($estados[$d['estado']] ?? $d['estado']); ?></td><td><?php echo s($d['acuse_nombre']); ?><?php echo $d['acuse_fecha'] ? ' · ' . $f(substr((string)$d['acuse_fecha'], 0, 10)) : ''; ?><?php echo trim((string)$d['acuse_comentario']) !== '' ? '<br><small>' . s($d['acuse_comentario']) . '</small>' : ''; ?></td></tr><?php endforeach; ?>
</tbody></table>

<p class="pie">Documento generado desde ProjectWeb. La revisión enviada queda congelada en este envío.</p>
<table class="firmas"><tr><td>Quien envía</td><td>Quien recibe</td></tr></table>
</body></html>
