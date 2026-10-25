<?php $id = (int)$proyecto->id; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-pen"></i> Redactor</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · documentos de texto del proyecto, agrupados por departamento</p>
    </div>
    <div class="la-controles">
        <button type="button" class="btn btn-ghost" data-tour="redactor-lista" title="Recorrer el Redactor paso a paso (se muestra solo la primera vez)"><i class="fa-solid fa-circle-play"></i> Guía rápida</button>
        <?php if ($puedeEditar): ?><a href="/redactor/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"<?php echo glosario('documento'); ?>><i class="fa-solid fa-plus"></i> Nuevo documento</a><?php endif; ?>
    </div>
</div>

<?php if (!$porDepto): ?>
    <div class="card"><?php echo estado_vacio('fa-file-pen', 'Aún no hay documentos', 'Oficios, minutas, RFIs, informes… con plantillas de obra listas, corrector ortográfico y exportación a Word, LibreOffice o PDF.',
        $puedeEditar ? ['texto' => 'Escribir el primer documento', 'url' => '/redactor/crear?proyecto=' . $id, 'icono' => 'fa-plus'] : null); ?></div>
<?php endif; ?>

<?php $carpetas = array_values(array_diff(array_keys($porDepto), [\Classes\Carpetas::SIN])); ?>
<?php foreach ($porDepto as $depto => $docs): ?>
    <div class="card rd-grupo">
        <h3<?php echo glosario('departamento'); ?>><i class="fa-solid fa-folder-open"></i> <?php echo s($depto); ?> <small class="text-muted">(<?php echo count($docs); ?>)</small>
            <?php if ($puedeEditar && $depto !== \Classes\Carpetas::SIN): ?><button type="button" class="btn btn-sm btn-ghost" data-carpeta-renombrar="<?php echo s($depto); ?>" title="Renombrar carpeta"><i class="fa-solid fa-pen"></i></button><?php endif; ?></h3>
        <div class="tabla-wrapper">
        <table class="tabla" <?php echo pag_attr($proyecto); ?> data-sin-buscador>
            <thead><tr><th class="hide-m">Fecha</th><th>Nombre</th><th class="hide-m">Hoja</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d): $q = 'proyecto=' . $id . '&id=' . (int)$d->id; ?>
                <tr>
                    <td class="mono hide-m"><?php echo s(formatear_fecha($d->fecha, 'd/m/Y')); ?></td>
                    <td><?php echo archivo_icono_html('docx', 'Documento'); ?> <strong><?php echo s($d->nombre); ?></strong><div class="solo-movil text-sm text-muted mono"><?php echo s(formatear_fecha($d->fecha, 'd/m/Y')); ?></div></td>
                    <td class="hide-m"><?php echo s(\Model\RedactorDocumento::PAPELES[$d->papel][0]); ?> · <?php echo s($d->orientacion); ?></td>
                    <td class="acciones">
                        <a href="/redactor/ver?<?php echo $q; ?>" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> Ver</a>
                        <?php if ($puedeEditar): ?><a href="/redactor/abrir?<?php echo $q; ?>" class="btn btn-sm btn-primary hide-m"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
                        <?php if ($puedeDescargar): ?>
                            <details class="gr-descargar">
                                <summary class="btn btn-sm btn-ghost"><i class="fa-solid fa-download"></i> Descargar</summary>
                                <div class="gr-descargar-menu">
                                    <a href="/redactor/descargar?<?php echo $q; ?>&formato=docx"><i class="fa-regular fa-file-word"></i> Word (.docx)</a>
                                    <a href="/redactor/descargar?<?php echo $q; ?>&formato=odt"><i class="fa-regular fa-file-lines"></i> LibreOffice (.odt)</a>
                                    <a href="/redactor/descargar?<?php echo $q; ?>&formato=pdf"><i class="fa-regular fa-file-pdf"></i> PDF</a>
                                </div>
                            </details>
                        <?php endif; ?>
                        <?php if ($puedeEditar): ?><span class="hide-m"><?php echo \Classes\Carpetas::selector('redactor', (int)$d->id, (string)$d->departamento, $carpetas); ?><?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <form method="POST" action="/redactor/duplicar" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$d->id; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> Duplicar</button></form>
                            <form method="POST" action="/redactor/eliminar" style="display:inline" data-confirm="¿Eliminar «<?php echo s($d->nombre); ?>»?|Se borrarán también sus imágenes. Esta acción no se puede deshacer.|Sí, eliminar"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$d->id; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> Eliminar</button></form>
                        </span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($puedeEditar && $porDepto) { $herramienta = 'redactor'; include __DIR__ . '/../templates/carpetas-ui.php'; } ?>
<script src="/vendor/tour.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/tour.js'); ?>"></script>
<script>
window.Tour.registrar('redactor-lista', {
    auto: true,
    pasos: [
        { icono: 'fa-file-pen', titulo: 'El Redactor', texto: 'Aquí viven los <b>documentos de texto</b> del proyecto: oficios, minutas, RFIs, informes… Se exportan a Word, LibreOffice o PDF, con corrector ortográfico y firmas electrónicas.' },
        { sel: '.rd-grupo h3', icono: 'fa-folder-open', titulo: 'Carpetas por departamento', texto: 'Los documentos se agrupan por <b>departamento</b>. El lápiz renombra la carpeta y «Mover a…» cambia un documento de carpeta.' },
        { sel: 'a[href*="/redactor/crear"]', icono: 'fa-plus', titulo: 'Nuevo documento', texto: 'Arranca de una <b>plantilla de obra</b> (oficio, minuta, RFI, memoria…) o de la hoja en blanco: las marcas del encabezado se llenan solas.', alEntrar: () => document.querySelector('.rd-grupo')?.scrollIntoView({ block: 'center' }) },
        { icono: 'fa-download', titulo: 'Listo', texto: 'Cada documento se <b>Ver</b>, <b>Editar</b>, <b>Duplicar</b> y <b>Descargar</b> en Word, LibreOffice o PDF. El botón «Guía rápida» vuelve a mostrar este recorrido.' }
    ]
});
</script>
