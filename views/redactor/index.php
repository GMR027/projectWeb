<?php $id = (int)$proyecto->id; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-pen"></i> Redactor</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · documentos de texto del proyecto, agrupados por departamento</p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?><a href="/redactor/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo documento</a><?php endif; ?>
    </div>
</div>

<?php if (!$porDepto): ?>
    <div class="card"><p class="text-muted">Aún no hay documentos. <?php echo $puedeEditar ? 'Crea el primero con «Nuevo documento».' : ''; ?></p></div>
<?php endif; ?>

<?php $carpetas = array_values(array_diff(array_keys($porDepto), [\Classes\Carpetas::SIN])); ?>
<?php foreach ($porDepto as $depto => $docs): ?>
    <div class="card rd-grupo">
        <h3><i class="fa-solid fa-folder-open"></i> <?php echo s($depto); ?> <small class="text-muted">(<?php echo count($docs); ?>)</small>
            <?php if ($puedeEditar && $depto !== \Classes\Carpetas::SIN): ?><button type="button" class="btn btn-sm btn-ghost" data-carpeta-renombrar="<?php echo s($depto); ?>" title="Renombrar carpeta"><i class="fa-solid fa-pen"></i></button><?php endif; ?></h3>
        <div class="tabla-wrapper">
        <table class="tabla" <?php echo pag_attr($proyecto); ?> data-sin-buscador>
            <thead><tr><th>Fecha</th><th>Nombre</th><th class="hide-m">Hoja</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d): $q = 'proyecto=' . $id . '&id=' . (int)$d->id; ?>
                <tr>
                    <td class="mono"><?php echo s(formatear_fecha($d->fecha, 'd/m/Y')); ?></td>
                    <td><?php echo archivo_icono_html('docx', 'Documento'); ?> <strong><?php echo s($d->nombre); ?></strong></td>
                    <td class="hide-m"><?php echo s(\Model\RedactorDocumento::PAPELES[$d->papel][0]); ?> · <?php echo s($d->orientacion); ?></td>
                    <td class="acciones">
                        <a href="/redactor/ver?<?php echo $q; ?>" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> Ver</a>
                        <?php if ($puedeEditar): ?><a href="/redactor/abrir?<?php echo $q; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
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
                        <?php if ($puedeEditar): ?><?php echo \Classes\Carpetas::selector('redactor', (int)$d->id, (string)$d->departamento, $carpetas); ?><?php endif; ?>
                        <?php if ($puedeEditar): ?>
                            <form method="POST" action="/redactor/duplicar" style="display:inline"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$d->id; ?>"><button type="submit" class="btn btn-sm btn-ghost"><i class="fa-regular fa-copy"></i> Duplicar</button></form>
                            <form method="POST" action="/redactor/eliminar" style="display:inline" data-confirm="¿Eliminar «<?php echo s($d->nombre); ?>»?|Se borrarán también sus imágenes. Esta acción no se puede deshacer.|Sí, eliminar"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$d->id; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> Eliminar</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($puedeEditar && $porDepto) { $herramienta = 'redactor'; include __DIR__ . '/../templates/carpetas-ui.php'; } ?>
