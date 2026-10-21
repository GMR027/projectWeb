<?php $q = 'proyecto=' . (int)$proyecto->id . '&id=' . (int)$hoja->id; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table-cells"></i> <?php echo s($hoja->nombre); ?></h2>
        <p class="text-muted"><?php echo s(formatear_fecha($hoja->fecha, 'd/m/Y')); ?><?php echo $hoja->departamento !== '' ? ' · ' . s($hoja->departamento) : ''; ?><?php echo $hoja->descripcion !== '' ? ' · ' . s($hoja->descripcion) : ''; ?></p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?><a href="/sheetflow/abrir?<?php echo $q; ?>" class="btn btn-primary"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
        <?php if ($puedeDescargar): ?>
            <details class="gr-descargar">
                <summary class="btn btn-ghost"><i class="fa-solid fa-download"></i> Descargar</summary>
                <div class="gr-descargar-menu">
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=xlsx"><i class="fa-regular fa-file-excel"></i> Excel (.xlsx)</a>
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=csv"><i class="fa-solid fa-file-csv"></i> CSV</a>
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=sheetflow"><i class="fa-solid fa-table-cells"></i> SheetFlow (.sheetflow)</a>
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=pdf&inline=1" target="_blank" rel="noopener"><i class="fa-solid fa-print"></i> Imprimir</a>
                </div>
            </details>
        <?php endif; ?>
        <a href="/sheetflow?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Tablas</a>
    </div>
</div>
<div class="card sf-ver"><div class="tabla-wrapper"><?php echo $tabla; ?></div></div>
<?php if ($puedeEditar): ?>
<div class="card sf-usos">
    <h3><i class="fa-solid fa-share-nodes"></i> Usar esta tabla en otras herramientas</h3>
    <ul class="sf-lista-usos">
        <li><strong>vPlan:</strong> crea una tabla vinculada del catálogo de vPlan (en vivo, hasta 8 columnas y 200 renglones).
            <form method="POST" action="/sheetflow/a-vplan" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$hoja->id; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-solid fa-table-list"></i> Crear tabla en vPlan</button></form>
            <?php foreach ($vplan as $u): ?>
                <form method="POST" action="/sheetflow/a-vplan" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$hoja->id; ?>"><input type="hidden" name="tabla" value="<?php echo (int)$u['id']; ?>"><button type="submit" class="btn btn-sm btn-ghost" title="Reemplaza los renglones de esa tabla con los actuales"><i class="fa-solid fa-rotate"></i> Vinculada: «<?php echo s($u['titulo']); ?>» (<?php echo (int)$u['laminas']; ?> lámina<?php echo (int)$u['laminas'] === 1 ? '' : 's'; ?>)</button></form>
            <?php endforeach; ?></li>
        <li><strong>Editor:</strong> en el panel <em>Símbolos</em> de cualquier gráfico, sección «Tablas de SheetFlow».</li>
        <li><strong>Redactor:</strong> <em>Insertar › Tabla › Origen › SheetFlow</em>.</li>
    </ul>
</div>
<?php endif; ?>

<script>window.TABLAS_VIVO = { url: '/api/sheetflow/version', datos: { id: <?php echo (int)$hoja->id; ?> } };</script>
<script src="/vendor/tablas-vivo.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tablas-vivo.js'); ?>"></script>
