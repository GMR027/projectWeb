<?php
/** Matriz y lista de riesgos en PDF. Variables: $proyecto, $lista, $resumen, $matriz */
use Classes\Riesgos;

$hoy = date('Y-m-d');
$colores = ['rojo' => '#dc2626', 'amarillo' => '#d97706', 'verde' => '#059669'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; margin: 0; }
    h1 { font-size: 15pt; margin: 0 0 2px; }
    h2 { font-size: 11pt; margin: 18px 0 6px; }
    .sub { color: #64748b; font-size: 8.5pt; margin: 0 0 14px; }
    .resumen { width: 100%; margin: 0 0 12px; }
    .resumen td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; }
    .resumen b { font-size: 14pt; display: block; }
    .matriz { border-collapse: collapse; width: 55%; }
    .matriz th, .matriz td { border: 1px solid #cbd5e1; padding: 6px; text-align: center; }
    .matriz th { background: #f1f5f9; font-size: 8pt; }
    .matriz td b { font-size: 12pt; display: block; }
    .matriz td small { color: #475569; }
    table.lista { border-collapse: collapse; width: 100%; }
    table.lista th { background: #f1f5f9; text-align: left; font-size: 8pt; padding: 5px; border: 1px solid #cbd5e1; }
    table.lista td { border: 1px solid #e2e8f0; padding: 5px; vertical-align: top; }
    .sev { display: inline-block; width: 18px; height: 18px; border-radius: 9px; color: #fff; text-align: center; line-height: 18px; font-weight: bold; font-size: 8pt; }
    .pie { margin-top: 14px; color: #64748b; font-size: 7.5pt; }
    .celda-rojo { background: #fee2e2; } .celda-amarillo { background: #fef3c7; } .celda-verde { background: #d1fae5; }
</style>
</head>
<body>
<h1>Matriz de riesgos</h1>
<p class="sub"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->codigo ? ' · ' . s($proyecto->codigo) : ''; ?> · <?php echo date('d/m/Y'); ?></p>

<table class="resumen">
    <tr>
        <td><b><?php echo (int)$resumen['abiertos']; ?></b>Abiertos</td>
        <td><b style="color:#dc2626"><?php echo (int)$resumen['rojos']; ?></b>Altos (7-9)</td>
        <td><b style="color:#d97706"><?php echo (int)$resumen['amarillos']; ?></b>Medios (4-6)</td>
        <td><b style="color:#059669"><?php echo (int)$resumen['verdes']; ?></b>Bajos (1-3)</td>
        <td><b style="color:#dc2626"><?php echo (int)$resumen['vencidos']; ?></b>Compromisos vencidos</td>
    </tr>
</table>

<h2>Matriz 3 × 3</h2>
<table class="matriz">
    <thead><tr><th></th><th>Impacto bajo</th><th>Impacto medio</th><th>Impacto alto</th></tr></thead>
    <tbody>
    <?php foreach ([3, 2, 1] as $probFila): ?>
        <tr>
            <th>Prob. <?php echo s(mb_strtolower(Riesgos::PROBABILIDADES[$probFila])); ?></th>
            <?php foreach ([1, 2, 3] as $impCol): $sv = Riesgos::severidad($probFila, $impCol); $nv = Riesgos::nivel($sv); $n = (int)($matriz[$probFila][$impCol] ?? 0); ?>
                <td class="celda-<?php echo $nv; ?>"><b><?php echo $sv; ?></b><small><?php echo $n ? $n . ' riesgo' . ($n > 1 ? 's' : '') : '—'; ?></small></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Riesgos registrados (<?php echo count($lista); ?>)</h2>
<table class="lista">
    <thead><tr><th style="width:6%">Sev.</th><th style="width:27%">Riesgo</th><th style="width:10%">Categoría</th><th style="width:13%">Responsable</th><th style="width:10%">Compromiso</th><th style="width:10%">Estado</th><th>Plan de acción</th></tr></thead>
    <tbody>
    <?php if (!$lista): ?>
        <tr><td colspan="7">Sin riesgos registrados.</td></tr>
    <?php endif; ?>
    <?php foreach ($lista as $r): $vencido = $r['fecha_compromiso'] && $r['fecha_compromiso'] < $hoy && Riesgos::abierto($r); ?>
        <tr>
            <td><span class="sev" style="background:<?php echo $colores[$r['nivel']]; ?>"><?php echo (int)$r['severidad']; ?></span></td>
            <td><b><?php echo s($r['titulo']); ?></b><?php if ($r['act_codigo']): ?><br><small><?php echo s($r['act_codigo'] . ' · ' . $r['act_nombre']); ?></small><?php endif; ?></td>
            <td><?php echo s(Riesgos::CATEGORIAS[$r['categoria']] ?? $r['categoria']); ?></td>
            <td><?php echo s($r['responsable']); ?></td>
            <td><?php echo $r['fecha_compromiso'] ? s(formatear_fecha($r['fecha_compromiso'], 'd/m/Y')) : '—'; ?><?php if ($vencido): ?><br><small style="color:#dc2626">vencido</small><?php endif; ?></td>
            <td><?php echo s(Riesgos::ESTADOS[$r['estado']] ?? $r['estado']); ?></td>
            <td><?php echo s($r['plan_accion']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p class="pie">Severidad = probabilidad × impacto. Verde 1-3 · Amarillo 4-6 · Rojo 7-9. Generado desde ProjectWeb.</p>
</body>
</html>
