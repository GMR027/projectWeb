<?php
// Variables: $d (titulo, cols, filas, decimales), $png, $proyecto, $esMaestra
$fmt = fn($v) => $v === null ? '' : number_format($v, $d['decimales']);
$tablaCorta = !$esMaestra;
$muchas = count($d['cols']) > 10;   // la maestra tiene muchas columnas: solo se imprime la gráfica
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8">
<style>
    @page { margin: 10mm 12mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 7pt; color: #000; }
    h1 { font-size: 9pt; margin: 0 0 3pt 0; }
    table { border-collapse: collapse; width: 100%; }
    table.densa { font-size: 5.2pt; }
    table.densa th, table.densa td { padding: 1.2pt 2pt; }
    th, td { border: 0.8pt solid #000; padding: 1.5pt 4pt; text-align: right; }
    th.k { text-align: left; font-weight: bold; white-space: nowrap; }
    th.f { text-align: right; font-weight: normal; }
    .caja { border: 0.6pt solid #888; padding: 4pt; margin-top: 6pt; }
</style></head><body>
<?php if ($tablaCorta): ?>
    <h1><?php echo s($d['titulo']); ?></h1>
    <table class="<?php echo $muchas ? 'densa' : ''; ?>">
        <tr><th class="k"></th><?php foreach ($d['cols'] as $c): ?><th class="f"><?php echo s($c); ?></th><?php endforeach; ?></tr>
        <?php foreach ($d['filas'] as $k => $vals): ?>
            <tr><th class="k"><?php echo s($k); ?></th><?php foreach ($vals as $v): ?><td><?php echo $fmt($v); ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <h1><?php echo s($proyecto->nombre); ?> · programa del <?php echo s(date('d/m/Y', strtotime($proyecto->fecha_inicio))); ?> al <?php echo s(date('d/m/Y', strtotime($proyecto->fecha_fin))); ?></h1>
<?php endif; ?>
<div class="caja" style="text-align:center"><img src="<?php echo $png; ?>" style="width:<?php echo $tablaCorta ? "78%" : "100%"; ?>"></div>
<?php if ($tablaCorta): ?>
    <table class="<?php echo $muchas ? 'densa' : ''; ?>" style="margin-top:6pt">
        <tr><th class="k"></th><?php foreach ($d['cols'] as $c): ?><th class="f"><?php echo s($c); ?></th><?php endforeach; ?></tr>
        <?php foreach ($d['filas'] as $k => $vals): ?>
            <tr><th class="k"><?php echo s($k); ?></th><?php foreach ($vals as $v): ?><td><?php echo $fmt($v); ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
</body></html>
