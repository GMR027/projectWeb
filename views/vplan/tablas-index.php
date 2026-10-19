<?php $tab = 'tablas'; use Model\VplanTabla; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table"></i> Tablas adicionales</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · catálogo reutilizable en las láminas</p>
    </div>
    <a href="/vplan/tablas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nueva tabla</a>
</div>
<?php include __DIR__ . '/_tabs.php'; ?>
<?php if (!empty($hojasSheetflow)): ?>
<div class="card">
    <form method="POST" action="/sheetflow/a-vplan" class="vp-dis-fila">
        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
        <label for="sf-hoja"><i class="fa-solid fa-table-cells"></i> <strong>Crear desde SheetFlow</strong></label>
        <select id="sf-hoja" name="id">
            <?php foreach ($hojasSheetflow as $h): ?><option value="<?php echo (int)$h['id']; ?>"><?php echo s($h['nombre'] . ' · ' . $h['filas'] . '×' . $h['cols']); ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary">Crear tabla</button>
        <small class="text-muted">Copia las primeras 8 columnas y hasta 200 renglones; después se puede actualizar desde SheetFlow.</small>
    </form>
</div>
<?php endif; ?>
<div class="card">
    <?php if (empty($tablas)): ?>
        <p class="text-muted">Aún no hay tablas. Crea una punch list, una tabla de restricciones, de actividades secundarias u otra.</p>
    <?php else: ?>
    <table class="tabla">
        <thead><tr><th>Nombre en el catálogo</th><th class="hide-m">Título impreso</th><th class="hide-m">Tipo</th><th class="hide-m">Renglones</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($tablas as $t): ?>
            <tr>
                <td><a href="/vplan/tablas/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($t->id); ?>"><?php echo s($t->etiqueta()); ?></a><?php echo $t->sheetflow_id ? ' <span class="badge" title="Creada desde SheetFlow">SheetFlow</span>' : ''; ?></td>
                <td class="text-muted hide-m"><?php echo s($t->titulo); ?></td>
                <td class="hide-m"><?php echo s(VplanTabla::TIPOS[$t->tipo] ?? $t->tipo); ?></td>
                <td class="mono hide-m"><?php echo (int)$conteos[$t->id]; ?></td>
                <td class="acciones">
                    <a href="/vplan/tablas/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($t->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                    <form method="POST" action="/vplan/tablas/duplicar" style="display:inline">
                        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                        <input type="hidden" name="id" value="<?php echo s($t->id); ?>">
                        <button type="submit" class="btn btn-sm btn-ghost" title="Crear una copia independiente">Duplicar</button>
                    </form>
                    <form method="POST" action="/vplan/tablas/eliminar" style="display:inline" data-confirm="¿Eliminar tabla?|Se quitará también de las láminas donde se use.">
                        <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                        <input type="hidden" name="id" value="<?php echo s($t->id); ?>">
                        <button type="submit" class="btn btn-sm btn-danger">✕</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
