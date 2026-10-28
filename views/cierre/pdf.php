<?php
/** Acta de entrega-recepción (PDF). Variables: $proyecto, $config, $items, $resumen */
use Classes\Cierre;

$porCat = [];
foreach ($items as $i) $porCat[$i['categoria']][] = $i;
$fecha = $config['fecha_entrega'] ? formatear_fecha($config['fecha_entrega'], 'd/m/Y') : formatear_fecha(date('Y-m-d'), 'd/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><style>
    @page { margin: 2cm 1.6cm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1f2937; }
    h1 { font-size: 16pt; margin: 0 0 .2cm; }
    h2 { font-size: 11pt; margin: .6cm 0 .2cm; padding-bottom: .1cm; border-bottom: 1px solid #cbd5e1; }
    .muted { color: #64748b; font-size: 9pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: .3cm; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; }
    th { background: #f1f5f9; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .04em; }
    .si { color: #047857; font-weight: bold; }
    .no { color: #b91c1c; font-weight: bold; }
    .firmas { margin-top: 1.2cm; width: 100%; }
    .firmas td { border: 0; border-top: 1px solid #334155; text-align: center; padding-top: 4px; width: 33%; }
</style></head>
<body>
    <h1>Acta de entrega-recepción</h1>
    <p class="muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->codigo ? ' · ' . s($proyecto->codigo) : ''; ?><?php echo $proyecto->ubicacion ? ' · ' . s($proyecto->ubicacion) : ''; ?></p>
    <p><strong>Fecha de entrega:</strong> <?php echo s($fecha); ?> &nbsp; · &nbsp; <strong>Responsable:</strong> <?php echo s($config['responsable'] ?: '—'); ?> &nbsp; · &nbsp; <strong>Avance:</strong> <?php echo (int)$resumen['pct']; ?>%</p>
    <?php if (!empty($config['notas'])): ?><p class="muted"><?php echo nl2br(s($config['notas'])); ?></p><?php endif; ?>

    <?php if (!$items): ?>
        <p class="muted">El proyecto no tiene puntos de entrega registrados.</p>
    <?php else: ?>
        <?php foreach (Cierre::CATEGORIAS as $k => $titulo): if (empty($porCat[$k])) continue; ?>
            <h2><?php echo s($titulo); ?></h2>
            <table>
                <thead><tr><th style="width:52%">Punto</th><th style="width:20%">Responsable</th><th style="width:14%">Compromiso</th><th style="width:14%">Estado</th></tr></thead>
                <tbody>
                <?php foreach ($porCat[$k] as $i): ?>
                    <tr><td><?php echo s($i['titulo']); ?><?php if ($i['notas']): ?><br><span class="muted"><?php echo s($i['notas']); ?></span><?php endif; ?></td>
                        <td><?php echo s($i['responsable'] ?: '—'); ?></td>
                        <td><?php echo $i['fecha_compromiso'] ? s(formatear_fecha($i['fecha_compromiso'], 'd/m/Y')) : '—'; ?></td>
                        <td class="<?php echo $i['estado'] === 'completado' ? 'si' : ($i['estado'] === 'pendiente' ? 'no' : ''); ?>"><?php echo s(Cierre::ESTADOS[$i['estado']] ?? $i['estado']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <table class="firmas"><tr>
        <td>Responsable de la obra<br><span class="muted">Nombre y firma</span></td>
        <td>Supervisor / cliente<br><span class="muted">Nombre y firma</span></td>
        <td>Testigo<br><span class="muted">Nombre y firma</span></td>
    </tr></table>
</body>
</html>
