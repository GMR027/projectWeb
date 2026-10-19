<?php $id = (int)$proyecto->id; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table-cells"></i> SheetFlow</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · tablas del proyecto que se usan en vPlan, Editor y Redactor</p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?><a href="/sheetflow/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nueva tabla</a><?php endif; ?>
    </div>
</div>

<?php if (!$porDepto): ?>
    <div class="card"><p class="text-muted">Aún no hay tablas. <?php echo $puedeEditar ? 'Crea la primera con «Nueva tabla»: en blanco, desde una plantilla o importando un Excel/CSV.' : ''; ?></p></div>
<?php endif; ?>

<?php foreach ($porDepto as $depto => $hojas): ?>
    <div class="card rd-grupo">
        <h3><i class="fa-solid fa-folder-open"></i> <?php echo s($depto); ?> <small class="text-muted">(<?php echo count($hojas); ?>)</small></h3>
        <div class="tabla-wrapper">
        <table class="tabla" data-sin-buscador>
            <thead><tr><th>Fecha</th><th>Nombre</th><th class="hide-m">Tamaño</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($hojas as $h): $q = 'proyecto=' . $id . '&id=' . (int)$h->id; [$nf, $nc] = $tamanos[$h->id]; ?>
                <tr>
                    <td class="mono"><?php echo s(formatear_fecha($h->fecha, 'd/m/Y')); ?></td>
                    <td><?php echo archivo_icono_html('sheetflow', 'Tabla de SheetFlow'); ?> <strong><?php echo s($h->nombre); ?></strong><?php echo $h->descripcion !== '' ? '<br><small class="text-muted">' . s($h->descripcion) . '</small>' : ''; ?></td>
                    <td class="hide-m mono"><?php echo $nf; ?> × <?php echo $nc; ?></td>
                    <td class="acciones">
                        <a href="/sheetflow/ver?<?php echo $q; ?>" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> Ver</a>
                        <?php if ($puedeEditar): ?><a href="/sheetflow/abrir?<?php echo $q; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
                        <?php if ($puedeDescargar): ?>
                            <details class="gr-descargar">
                                <summary class="btn btn-sm btn-ghost"><i class="fa-solid fa-download"></i> Descargar</summary>
                                <div class="gr-descargar-menu">
                                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=xlsx"><i class="fa-regular fa-file-excel"></i> Excel (.xlsx)</a>
                                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=csv"><i class="fa-solid fa-file-csv"></i> CSV</a>
                                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=sheetflow"><i class="fa-solid fa-table-cells"></i> SheetFlow (.sheetflow)</a>
                                    <a href="/sheetflow/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <form method="POST" action="/sheetflow/a-vplan" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$h->id; ?>"><button type="submit" class="btn btn-sm btn-ghost" title="Crea una tabla en el catálogo de vPlan con estos datos"><i class="fa-solid fa-table-list"></i> A vPlan</button></form>
                            <form method="POST" action="/sheetflow/duplicar" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$h->id; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> Duplicar</button></form>
                            <form method="POST" action="/sheetflow/eliminar" style="display:inline" data-confirm="¿Eliminar «<?php echo s($h->nombre); ?>»?|Las tablas de vPlan que se crearon con ella conservan sus datos. Esta acción no se puede deshacer.|Sí, eliminar"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$h->id; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> Eliminar</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endforeach; ?>
