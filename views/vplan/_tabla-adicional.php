<?php
// Variables: $bloque (['tabla','campos','filas','base']), $puedeEditar, $anchoTablaCm, $f, $p
use Model\VplanTabla;
use Classes\VplanDiseno;
$t = $bloque['tabla'];
$campos = $bloque['campos'];
$estEnc = $t->estiloEncabezados();
$extra = $puedeEditar ? 1 : 0;   // columna de acciones (solo en pantalla)
$nAcc = $t->tipo === 'secundarias' ? $extra * 2 : $extra;
// El reparto de columnas es el mismo que usan el PDF y el estimador: así la lámina y el PDF se ven igual
[$pct, $pctAcc] = VplanDiseno::repartoColumnas($t->tipo, $bloque['filas'], $campos, $anchoTablaCm, $f, $p, $nAcc, $t->metaColumnas());
?>
<table class="vp-tabla vp-adic vp-adic-<?php echo s($t->tipo); ?>" data-tabla="<?php echo (int)$t->id; ?>" data-base="<?php echo (int)($bloque['base'] ?? 0); ?>" data-tipo="<?php echo s($t->tipo); ?>">
    <colgroup>
        <?php foreach ($pct as $w): ?><col style="width:<?php echo $w; ?>%"><?php endforeach; ?>
        <?php for ($i = 0; $i < $nAcc; $i++): ?><col style="width:<?php echo round($pctAcc / max(1, $nAcc), 3); ?>%"><?php endfor; ?>
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
            <tr><th>N.</th><?php foreach ($campos as $k => [$etq]): ?><th<?php echo !empty($estEnc[$k]) ? ' style="' . s($estEnc[$k]) . '"' : ''; ?>><?php echo s($etq); ?></th><?php endforeach; ?><?php if ($extra): ?><th class="no-print"></th><?php endif; ?></tr>
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
