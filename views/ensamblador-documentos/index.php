<?php // Variables: $proyecto, $ensambles [['e' => Ensamble, 'fuentes' => [...]]], $puedeEditar, $puedeDescargar, $carpetas
$id = (int)$proyecto->id; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-object-group"></i> <?php echo s(t('Ensamblador de documentos')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · une documentos del Redactor y PDF del Archivo en un solo PDF no editable que se actualiza solo</p>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?><a href="/ensamblador-documentos/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo ensamble</a><?php endif; ?>
    </div>
</div>
<?php if (isset($_GET['guardado'])): ?><div class="alerta alerta-exito">PDF guardado en Archivo. Se regenerará solo cuando cambie alguno de sus documentos.</div><?php endif; ?>

<?php if (!$ensambles): ?>
    <div class="card"><p class="text-muted">Aún no hay ensambles. <?php echo $puedeEditar ? 'Crea el primero con «Nuevo ensamble»: elige los documentos y su orden.' : ''; ?></p></div>
<?php else: ?>
<div class="card">
    <div class="tabla-wrapper">
    <table class="tabla" data-sin-buscador>
        <thead><tr><th>Nombre</th><th>Documentos (en orden)</th><th class="hide-m">En Archivo</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($ensambles as ['e' => $e, 'fuentes' => $fuentes]): $q = 'proyecto=' . $id . '&id=' . (int)$e->id; ?>
            <tr>
                <td><?php echo archivo_icono_html('pdf', 'PDF'); ?> <strong><?php echo s($e->nombre); ?></strong></td>
                <td>
                    <ol style="margin:0;padding-left:1.8rem">
                    <?php foreach ($fuentes as $f): ?>
                        <li>
                            <i class="fa-solid <?php echo $f['t'] === 'redactor' ? 'fa-file-pen' : 'fa-file-pdf'; ?>" aria-hidden="true"></i>
                            <?php echo $f['falta'] ? '<em class="text-muted">Documento eliminado (se omite)</em>' : s($f['nombre']); ?>
                            <small class="text-muted"><?php echo $f['t'] === 'redactor' ? 'Redactor' : 'Archivo'; ?></small>
                        </li>
                    <?php endforeach; ?>
                    </ol>
                </td>
                <td class="hide-m"><?php echo $e->archivo_id ? '<span class="badge-carpeta"><i class="fa-solid fa-rotate"></i> Sí · se actualiza solo</span>' : '<span class="text-muted">No guardado</span>'; ?></td>
                <td class="acciones">
                    <?php if ($puedeDescargar): ?>
                        <a href="/ensamblador-documentos/descargar?<?php echo $q; ?>&inline=1" target="_blank" rel="noopener" class="btn btn-sm btn-ghost"><i class="fa-regular fa-eye"></i> Ver</a>
                        <a href="/ensamblador-documentos/descargar?<?php echo $q; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-download"></i> Descargar PDF</a>
                    <?php endif; ?>
                    <?php if ($puedeEditar): ?>
                        <form method="POST" action="/ensamblador-documentos/archivo" style="display:inline-flex;gap:.4rem;align-items:center">
                            <input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$e->id; ?>">
                            <?php if (!$e->archivo_id && $carpetas): ?>
                                <select name="carpeta_id" aria-label="Carpeta del Archivo"><option value="">Sin carpeta</option><?php foreach ($carpetas as $k): ?><option value="<?php echo (int)$k->id; ?>"><?php echo s($k->nombre); ?></option><?php endforeach; ?></select>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-ghost" title="Guarda el PDF en Archivo; se regenera solo cuando cambian sus documentos"><i class="fa-solid fa-folder-open"></i> <?php echo $e->archivo_id ? 'Actualizar en Archivo' : 'Guardar en Archivo'; ?></button>
                        </form>
                        <a href="/ensamblador-documentos/editar?<?php echo $q; ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-pen"></i> Editar</a>
                        <form method="POST" action="/ensamblador-documentos/eliminar" style="display:inline" data-confirm="¿Eliminar «<?php echo s($e->nombre); ?>»?|Se borra el ensamble. Si ya está en Archivo, ese PDF se conserva tal como está, pero dejará de actualizarse.|Sí, eliminar"><input type="hidden" name="proyecto" value="<?php echo $id; ?>"><input type="hidden" name="id" value="<?php echo (int)$e->id; ?>"><button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> Eliminar</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<p class="text-sm text-muted">El PDF siempre se arma con la versión actual de cada documento. El archivo que descargas ya es un PDF común: no conserva el vínculo ni se actualiza.</p>
<?php endif; ?>
