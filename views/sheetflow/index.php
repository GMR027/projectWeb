<?php $id = (int)$proyecto->id; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table-cells"></i> SheetFlow</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · tablas del proyecto que se usan en vPlan, Editor y Redactor</p>
    </div>
    <div class="la-controles">
        <button type="button" class="btn btn-ghost" data-tour="sheetflow-lista" title="Recorrer SheetFlow paso a paso (se muestra solo la primera vez)"><i class="fa-solid fa-circle-play"></i> Guía rápida</button>
        <?php if ($puedeEditar): ?><a href="/sheetflow/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"<?php echo glosario('sheetflow'); ?>><i class="fa-solid fa-plus"></i> Nueva tabla</a><?php endif; ?>
    </div>
</div>

<?php if (!$porDepto): ?>
    <div class="card"><?php echo estado_vacio('fa-table-cells', 'Aún no hay tablas', 'Hojas de cálculo del proyecto que se reutilizan en vPlan, Editor y Redactor: en blanco, desde una plantilla de obra o importando tu Excel.',
        $puedeEditar ? ['texto' => 'Crear la primera tabla', 'url' => '/sheetflow/crear?proyecto=' . $id, 'icono' => 'fa-plus'] : null); ?></div>
<?php endif; ?>

<?php $carpetas = array_values(array_diff(array_keys($porDepto), [\Classes\Carpetas::SIN])); ?>
<?php foreach ($porDepto as $depto => $hojas): ?>
    <div class="card rd-grupo">
        <h3<?php echo glosario('departamento'); ?>><i class="fa-solid fa-folder-open"></i> <?php echo s($depto); ?> <small class="text-muted">(<?php echo count($hojas); ?>)</small>
            <?php if ($puedeEditar && $depto !== \Classes\Carpetas::SIN): ?><button type="button" class="btn btn-sm btn-ghost" data-carpeta-renombrar="<?php echo s($depto); ?>" title="Renombrar carpeta"><i class="fa-solid fa-pen"></i></button><?php endif; ?></h3>
        <div class="tabla-wrapper">
        <table class="tabla" <?php echo pag_attr($proyecto); ?> data-sin-buscador>
            <thead><tr><th class="hide-m">Fecha</th><th>Nombre</th><th class="hide-m">Tamaño</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($hojas as $h): $q = 'proyecto=' . $id . '&id=' . (int)$h->id; [$nf, $nc] = $tamanos[$h->id]; ?>
                <tr>
                    <td class="mono hide-m"><?php echo s(formatear_fecha($h->fecha, 'd/m/Y')); ?></td>
                    <td><?php echo archivo_icono_html('sheetflow', 'Tabla de SheetFlow'); ?> <strong><?php echo s($h->nombre); ?></strong><span class="solo-movil text-sm text-muted mono"><?php echo s(formatear_fecha($h->fecha, 'd/m/Y')); ?></span><?php echo $h->descripcion !== '' ? '<br><small class="text-muted">' . s($h->descripcion) . '</small>' : ''; ?></td>
                    <td class="hide-m mono"><?php echo $nf; ?> × <?php echo $nc; ?></td>
                    <td class="acciones">
                        <a href="/sheetflow/ver?<?php echo $q; ?>" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> Ver</a>
                        <?php if ($puedeEditar): ?><a href="/sheetflow/abrir?<?php echo $q; ?>" class="btn btn-sm btn-primary hide-m"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
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
                        <?php if ($puedeEditar): ?><span class="hide-m"><?php echo \Classes\Carpetas::selector('sheetflow', (int)$h->id, (string)$h->departamento, $carpetas); ?><?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <form method="POST" action="/sheetflow/a-vplan" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$h->id; ?>"><button type="submit" class="btn btn-sm btn-ghost" title="Crea una tabla en el catálogo de vPlan con estos datos"<?php echo glosario('vplan'); ?>><i class="fa-solid fa-table-list"></i> A vPlan</button></form>
                            <form method="POST" action="/sheetflow/duplicar" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$h->id; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> Duplicar</button></form>
                            <form method="POST" action="/sheetflow/eliminar" style="display:inline" data-confirm="¿Eliminar «<?php echo s($h->nombre); ?>»?|Las tablas de vPlan que se crearon con ella conservan sus datos. Esta acción no se puede deshacer.|Sí, eliminar"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$h->id; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> Eliminar</button></form>
                        </span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($puedeEditar && $porDepto) { $herramienta = 'sheetflow'; include __DIR__ . '/../templates/carpetas-ui.php'; } ?>
<script src="/vendor/tour.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tour.js'); ?>"></script>
<script>
window.Tour.registrar('sheetflow-lista', {
    auto: true,
    pasos: [
        { icono: 'fa-table-cells', titulo: 'SheetFlow', texto: 'Las <b>hojas de cálculo</b> del proyecto: control de materiales, presupuesto, asistencia… Se usan en el vPlan, el Editor y el Redactor; las enlazadas se actualizan solas.' },
        { sel: '.rd-grupo h3', icono: 'fa-folder-open', titulo: 'Carpetas por departamento', texto: 'Como en el Redactor: las tablas se agrupan por <b>departamento</b>, se renombra con el lápiz y se mueven con «Mover a…».' },
        { sel: 'a[href*="/sheetflow/crear"]', icono: 'fa-plus', titulo: 'Nueva tabla', texto: 'En blanco, <b>inteligente</b> (columnas con tipo que se llenan desde vPlan), de una <b>plantilla de obra</b> o importando tu Excel, CSV u otra tabla .sheetflow.', alEntrar: () => document.querySelector('.rd-grupo')?.scrollIntoView({ block: 'center' }) },
        { icono: 'fa-circle-check', titulo: 'Listo', texto: 'Cada tabla se <b>Ver</b>, <b>Editar</b>, pasa <b>A vPlan</b> con un clic y se descarga en Excel, CSV o PDF. El botón «Guía rápida» repite este recorrido.' }
    ]
});
</script>
