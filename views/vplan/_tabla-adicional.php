<?php
// Variables: $bloque (['tabla','campos','filas','base']), $puedeEditar
use Model\VplanTabla;
$t = $bloque['tabla'];
$campos = $bloque['campos'];
$extra = $puedeEditar ? 1 : 0;   // columna de acciones (solo en pantalla)
?>
<table class="vp-tabla vp-adic vp-adic-<?php echo s($t->tipo); ?>" data-tabla="<?php echo (int)$t->id; ?>" data-base="<?php echo (int)($bloque['base'] ?? 0); ?>" data-tipo="<?php echo s($t->tipo); ?>">
    <colgroup>
        <?php foreach (\Classes\VplanDiseno::columnasTabla($t->tipo, count($campos)) as $w): ?><col style="width:<?php echo $w; ?>%"><?php endforeach; ?>
        <?php for ($i = 0; $i < ($t->tipo === 'secundarias' ? $extra * 2 : $extra); $i++): ?><col style="width:3.2em"><?php endfor; ?>
    </colgroup>
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
    <div class="vp-tab-acc no-print">
        <?php if ($t->tipo === 'punchlist'): ?>
            <a href="/punch?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-clipboard-check"></i> Agregar en Punch list</a>
            <a href="/vplan/tablas/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($t->id); ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-eye"></i> Qué mostrar</a>
        <?php else: ?>
        <button type="button" class="btn btn-sm btn-primary vp-agregar-dato" data-tabla="<?php echo (int)$t->id; ?>">+ Agregar dato</button>
        <a href="/vplan/tablas/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($t->id); ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-pen-to-square"></i> Editar datos</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
