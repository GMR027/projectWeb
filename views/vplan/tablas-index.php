<?php $tab = 'tablas'; use Model\VplanTabla; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table"></i> Tablas adicionales</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · catálogo reutilizable en las láminas</p>
    </div>
    <div class="vp-recovery-btns">
        <a href="/vplan/tablas/documento?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost"><i class="fa-solid fa-file-import"></i> Tabla en base a documento</a>
    <a href="/vplan/tablas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nueva tabla</a>
    </div>
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
            <?php
            $camposP = $t->campos();
            $clavesP = array_slice(array_keys($camposP), 0, 4);
            $filasP = array_slice($t->filas(), 0, 3);
            ?>
            <tr>
                <td>
                    <div class="vp-tabla-nombre">
                        <a href="/vplan/tablas/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($t->id); ?>"><?php echo s($t->etiqueta()); ?></a>
                        <?php if ($t->sheetflow_id): ?>
                            <span class="vp-tabla-ico" title="Creada desde SheetFlow"><i class="fa-solid fa-table-cells"></i></span>
                        <?php elseif ($t->esPunchVista() || $t->tipo === 'punch'): ?>
                            <span class="vp-tabla-ico" title="Punch list"><i class="fa-solid fa-clipboard-check"></i></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($clavesP): ?>
                    <div class="vp-tabla-prev" style="--vp-cols:<?php echo count($clavesP); ?>">
                        <div class="vp-prev-fila vp-prev-cab">
                            <?php foreach ($clavesP as $k): ?><span><?php echo s(mb_strimwidth($camposP[$k][0], 0, 14, '…')); ?></span><?php endforeach; ?>
                        </div>
                        <?php if (!$filasP): ?>
                            <div class="vp-prev-fila vp-prev-vacia"><span>Sin renglones</span></div>
                        <?php else: ?>
                            <?php foreach ($filasP as $f): ?>
                                <div class="vp-prev-fila">
                                    <?php foreach ($clavesP as $k): ?><span><?php echo s(mb_strimwidth((string)($f[$k] ?? ''), 0, 14, '…')); ?></span><?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </td>
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
