<?php
// Reporte de hallazgos agrupados por problema (Inconsistencias / Análisis de materiales). Lo usa Classes\Exportadores\HallazgosPdfExporter.
// Variables: $proyecto, $datos (lista, grupos, filtros, corrida, titulo, categorias, gruposNombres, generado)
$sev = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$est = ['pendiente' => 'Pendiente', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada'];
$esMat = $datos['herramienta'] === 'analisis-materiales';
$filtrosTxt = [];
if (!empty($datos['filtros']['severidad'])) $filtrosTxt[] = 'Severidad: ' . ($sev[$datos['filtros']['severidad']] ?? $datos['filtros']['severidad']);
if (!empty($datos['filtros']['categoria'])) $filtrosTxt[] = 'Categoría: ' . ($datos['categorias'][$datos['filtros']['categoria']] ?? $datos['filtros']['categoria']);
if (!empty($datos['filtros']['origen'])) $filtrosTxt[] = 'Detectada por: ' . ($datos['filtros']['origen'] === 'ia' ? 'IA' : 'Sistema');
if (!empty($datos['filtros']['estado'])) $filtrosTxt[] = 'Estado: ' . ($est[$datos['filtros']['estado']] ?? 'Todas');
$entidades = fn($x) => $esMat
    ? implode(', ', array_map(fn($m) => $m['nombre'], $x['materiales'] ?? []))
    : implode(', ', array_map(fn($a) => trim($a['codigo'] . ' ' . $a['nombre']), $x['actividades'] ?? []));
$accion = fn($x) => $x['solucion']['accion'] ?? 'nota';
$params = function ($x) {
    $p = $x['solucion']['params'] ?? [];
    $partes = [];
    foreach ($p as $k => $v) {
        if ($k === 'esperado' || $v === '' || $v === null) continue;
        $partes[] = $k . ': ' . (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v);
    }
    return implode('; ', $partes);
};
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><style>
    @page { margin: 26px 30px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #222; }
    h1 { font-size: 16px; margin: 0 0 2px; color: #1e293b; }
    h2 { font-size: 11px; margin: 14px 0 5px; padding-bottom: 2px; border-bottom: 1.5px solid #f97316; color: #1e293b; }
    .sub { color: #555; font-size: 8px; margin-bottom: 8px; }
    .kpis { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
    .kpis td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: center; background: #f8fafc; }
    .kpis strong { display: block; font-size: 13px; color: #1e293b; }
    table.t { width: 100%; border-collapse: collapse; margin: 3px 0 8px; }
    table.t th, table.t td { border: 1px solid #cbd5e1; padding: 3px 5px; vertical-align: top; }
    table.t th { background: #1e293b; color: #fff; font-size: 7.5px; text-align: left; }
    table.t tr:nth-child(even) td { background: #f8fafc; }
    .sev { font-weight: bold; }
    .sev-critica { color: #b91c1c; } .sev-alta { color: #c2410c; } .sev-media { color: #a16207; } .sev-baja { color: #1d4ed8; }
    .muted { color: #64748b; }
    .grupo-cab { background: #f1f5f9; padding: 4px 6px; border-left: 3px solid #f97316; margin-top: 8px; font-size: 9px; }
    .grupo-cab strong { color: #1e293b; }
    .pie { margin-top: 12px; font-size: 7.5px; color: #64748b; text-align: center; }
</style></head>
<body>
<h1><?php echo htmlspecialchars($datos['titulo'], ENT_QUOTES, 'UTF-8'); ?></h1>
<div class="sub">
    <strong><?php echo htmlspecialchars($proyecto->nombre, ENT_QUOTES, 'UTF-8'); ?></strong>
    <?php echo $proyecto->codigo ? ' · ' . htmlspecialchars($proyecto->codigo, ENT_QUOTES, 'UTF-8') : ''; ?>
    · Generado <?php echo htmlspecialchars($datos['generado'], ENT_QUOTES, 'UTF-8'); ?>
    <?php if ($datos['corrida']): ?><br>Análisis: <?php echo htmlspecialchars(($datos['corrida']['nombre'] ?: 'Análisis') . ' · ' . date('d/m/Y H:i', strtotime($datos['corrida']['creado'])), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
    <?php if ($filtrosTxt): ?><br>Filtros: <?php echo htmlspecialchars(implode(' · ', $filtrosTxt), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
</div>

<?php
$tot = count($datos['lista']);
$pen = count(array_filter($datos['lista'], fn($x) => $x['estado'] === 'pendiente'));
$ace = count(array_filter($datos['lista'], fn($x) => $x['estado'] === 'aceptada'));
$rec = count(array_filter($datos['lista'], fn($x) => $x['estado'] === 'rechazada'));
?>
<table class="kpis">
    <tr>
        <td><strong><?php echo $tot; ?></strong>hallazgos</td>
        <td><strong><?php echo count($datos['grupos']); ?></strong>problemas distintos</td>
        <td><strong><?php echo $pen; ?></strong>pendientes</td>
        <td><strong><?php echo $ace; ?></strong>aceptados</td>
        <td><strong><?php echo $rec; ?></strong>rechazados</td>
    </tr>
</table>

<h2>Resumen por problema</h2>
<table class="t">
    <thead><tr><th style="width:34%">Problema</th><th>Categoría</th><th>Severidad</th><th>Total</th><th>Pendientes</th><th>Aceptados</th><th>Rechazados</th></tr></thead>
    <tbody>
    <?php foreach ($datos['grupos'] as $g): ?>
        <tr>
            <td><?php echo htmlspecialchars($g['etiqueta'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars($datos['categorias'][$g['categoria']] ?? $g['categoria'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="sev sev-<?php echo htmlspecialchars($g['severidad'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sev[$g['severidad']] ?? $g['severidad'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int)$g['total']; ?></td>
            <td><?php echo (int)$g['pendientes']; ?></td>
            <td><?php echo count(array_filter($g['items'], fn($x) => $x['estado'] === 'aceptada')); ?></td>
            <td><?php echo count(array_filter($g['items'], fn($x) => $x['estado'] === 'rechazada')); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Detalle por problema</h2>
<?php foreach ($datos['grupos'] as $g): ?>
    <div class="grupo-cab">
        <strong><?php echo htmlspecialchars($g['etiqueta'], ENT_QUOTES, 'UTF-8'); ?></strong>
        · <?php echo (int)$g['total']; ?> hallazgo(s) · severidad <?php echo htmlspecialchars($sev[$g['severidad']] ?? $g['severidad'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <table class="t">
        <thead><tr>
            <th style="width:22%"><?php echo $esMat ? 'Material(es)' : 'Actividad(es)'; ?></th>
            <th style="width:24%">Hallazgo</th>
            <th style="width:24%">Solución / valores aplicados</th>
            <th style="width:9%">Acción</th>
            <th style="width:8%">Estado</th>
            <th style="width:13%">Resuelto</th>
        </tr></thead>
        <tbody>
        <?php foreach ($g['items'] as $x): ?>
            <tr>
                <td><?php echo htmlspecialchars($entidades($x), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><strong><?php echo htmlspecialchars($x['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="muted"><?php echo htmlspecialchars($x['descripcion'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?php echo htmlspecialchars($x['solucion']['texto'] ?? '', ENT_QUOTES, 'UTF-8'); ?><?php if ($p = $params($x)): ?><br><span class="muted"><?php echo htmlspecialchars($p, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></td>
                <td><?php echo htmlspecialchars($accion($x), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($est[$x['estado']] ?? $x['estado'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $x['resuelto_en'] ? htmlspecialchars(($x['resuelto_por'] ?: '—') . ' · ' . date('d/m/Y H:i', strtotime($x['resuelto_en'])), ENT_QUOTES, 'UTF-8') : '—'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<div class="pie"><?php echo htmlspecialchars($proyecto->nombre, ENT_QUOTES, 'UTF-8'); ?> · Reporte generado por ProjectWeb · <?php echo htmlspecialchars($datos['generado'], ENT_QUOTES, 'UTF-8'); ?></div>
</body>
</html>
