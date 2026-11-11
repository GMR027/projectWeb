<?php
/** Variables: $proyecto, $secciones, $filtros, $total, $img */
$e = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$fecha = fn($f) => $f ? date('d/m/Y', strtotime($f)) : '—';
$compra = fn($c) => \Model\Material::COMPRA[$c ?? ''] ?? 'Pendiente';
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Catálogo de materiales</title>
<style>
    @page { margin: 12mm 10mm 13mm 10mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 7.4pt; color: #111827; margin: 0; }
    .cab { border-bottom: 2px solid #0f172a; padding-bottom: 5pt; margin-bottom: 8pt; }
    .cab h1 { font-size: 14pt; margin: 0 0 2pt; color: #0f172a; }
    .cab .sub { color: #475569; font-size: 8pt; }
    .cab .meta { color: #64748b; font-size: 7pt; margin-top: 3pt; }
    table { width: 100%; border-collapse: collapse; }
    thead { display: table-header-group; }
    th { background: #17365d; color: #fff; font-size: 7pt; text-align: left; padding: 4pt 3pt; border: .5pt solid #17365d; }
    td { border: .5pt solid #cbd5e1; padding: 2.5pt 3pt; vertical-align: middle; }
    tr { page-break-inside: avoid; }
    tr.sec td { background: #e2e8f0; color: #0f172a; font-weight: bold; font-size: 8pt; padding: 4pt 5pt; border: .5pt solid #94a3b8; }
    .img { width: 15mm; height: 12mm; object-fit: contain; display: block; margin: 0 auto; }
    .sin { color: #94a3b8; font-size: 14pt; text-align: center; }
    .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 6.7pt; color: #334155; }
    .mat { font-weight: bold; }
    .chip { display: inline-block; padding: 1.5pt 5pt; border-radius: 8pt; font-size: 6.6pt; font-weight: bold; }
    .chip-pendiente { background: #fef3c7; color: #92400e; }
    .chip-comprado { background: #dcfce7; color: #166534; }
    .centro { text-align: center; }
    .vacio { color: #94a3b8; text-align: center; padding: 18pt; }
</style>
</head>
<body>
<div class="cab">
    <h1>Catálogo de materiales</h1>
    <div class="sub"><?php echo $e($proyecto->nombre); ?><?php echo $proyecto->codigo ? ' · ' . $e($proyecto->codigo) : ''; ?><?php echo $proyecto->ubicacion ? ' · ' . $e($proyecto->ubicacion) : ''; ?></div>
    <div class="meta"><?php echo (int)$total; ?> material(es) · <?php echo $e(\Classes\MaterialesCatalogo::textoFiltros($filtros)); ?> · Generado el <?php echo date('d/m/Y H:i'); ?></div>
</div>

<?php if (!$secciones): ?>
    <p class="vacio">No hay materiales que coincidan con los filtros.</p>
<?php else: ?>
<table>
    <thead>
        <tr>
            <th style="width:10%">Imagen</th>
            <th style="width:12%">Referencia</th>
            <th style="width:31%">Material</th>
            <th style="width:14%">Especialidad</th>
            <th style="width:11%">Status de compra</th>
            <th style="width:7.5%">Compra prog.</th>
            <th style="width:7.5%">Entrega prog.</th>
            <th style="width:7%">Entrega real</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($secciones as $especialidad => $lista): ?>
        <tr class="sec"><td colspan="8"><?php echo $e($especialidad); ?> · <?php echo count($lista); ?> material(es)</td></tr>
        <?php foreach ($lista as $m): $im = $img($m->imagen); ?>
        <tr>
            <td class="centro"><?php if ($im): ?><img class="img" src="<?php echo $im; ?>" alt=""><?php else: ?><span class="sin">—</span><?php endif; ?></td>
            <td class="mono"><?php echo $e($m->referencia ?: '—'); ?></td>
            <td class="mat"><?php echo $e($m->nombre); ?></td>
            <td><?php echo $e($m->especialidad ?: 'Sin especialidad'); ?></td>
            <td class="centro"><span class="chip chip-<?php echo $m->compra === 'comprado' ? 'comprado' : 'pendiente'; ?>"><?php echo $e($compra($m->compra)); ?></span></td>
            <td class="centro"><?php echo $e($fecha($m->fecha_compra_prog)); ?></td>
            <td class="centro"><?php echo $e($fecha($m->fecha_entrega_prog)); ?></td>
            <td class="centro"><?php echo $e($fecha($m->fecha_entrega_real)); ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
</body>
</html>
