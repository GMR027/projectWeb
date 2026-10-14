<?php
// Variables: $bloque (['tabla','campos','filas','base']), $puedeEditar
use Model\VplanTabla;
$t = $bloque['tabla'];
$campos = $bloque['campos'];
$extra = $puedeEditar ? 1 : 0;   // columna de acciones (solo en pantalla)
?>
<table class="vp-tabla vp-adic vp-adic-<?php echo s($t->tipo); ?>" data-tabla="<?php echo (int)$t->id; ?>" data-base="<?php echo (int)($bloque['base'] ?? 0); ?>" data-tipo="<?php echo s($t->tipo); ?>">
    <?php if ($t->tipo === 'punch'): ?>
        <thead>
            <tr><th rowspan="2" colspan="2" class="vp-adic-titulo"><?php echo s($t->titulo); ?></th><th colspan="2">DATE</th><th rowspan="2">%</th><?php if ($extra): ?><th rowspan="2" class="no-print"></th><?php endif; ?></tr>
            <tr><th>START</th><th>FINISH</th></tr>
        </thead>
    <?php elseif ($t->tipo === 'restricciones'): ?>
        <thead><tr><th colspan="<?php echo 2 + $extra; ?>" class="vp-adic-titulo"><?php echo s($t->titulo); ?></th></tr></thead>
    <?php elseif ($t->tipo === 'secundarias'): ?>
        <thead>
            <tr><th colspan="<?php echo 3 + $extra * 2; ?>" class="vp-adic-titulo"><?php echo s($t->titulo); ?></th></tr>
            <tr><th>ELEMENTO</th><th>FECHA</th><th>FECHA (RECOVERY)</th><?php if ($extra): ?><th class="no-print">ESTADO</th><th class="no-print"></th><?php endif; ?></tr>
        </thead>
    <?php else: ?>
        <thead>
            <tr><th colspan="<?php echo count($campos) + 1 + $extra; ?>" class="vp-adic-titulo"><?php echo s($t->titulo); ?></th></tr>
            <tr><th>N.</th><?php foreach ($campos as [$etq]): ?><th><?php echo s($etq); ?></th><?php endforeach; ?><?php if ($extra): ?><th class="no-print"></th><?php endif; ?></tr>
        </thead>
    <?php endif; ?>
    <tbody>
        <?php foreach ($bloque['filas'] as $fila) include __DIR__ . '/_fila-adicional.php'; ?>
    </tbody>
</table>
<?php if ($puedeEditar): ?>
    <button type="button" class="btn btn-sm btn-ghost vp-agregar-dato no-print" data-tabla="<?php echo (int)$t->id; ?>">+ Agregar dato</button>
<?php endif; ?>
