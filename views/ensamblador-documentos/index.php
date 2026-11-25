<?php // Variables: $proyecto, $ensambles [['e' => Ensamble, 'fuentes' => [...]]], $puedeEditar, $puedeDescargar, $carpetas
$id = (int)$proyecto->id;
$nEnsambles = count($ensambles);
$nFuentes = 0; $nEnArchivo = 0;
foreach ($ensambles as $x) { $nFuentes += count($x['fuentes']); if ($x['e']->archivo_id) $nEnArchivo++; }
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-object-group"></i> <?php echo s(t('Ensamblador de documentos')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · une documentos del Redactor y PDF del Archivo en un solo PDF no editable que se actualiza solo.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-object-group"></i><b><?php echo $nEnsambles; ?></b> ensambles</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-file-lines"></i><b><?php echo $nFuentes; ?></b> documentos</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-rotate"></i><b><?php echo $nEnArchivo; ?></b> en Archivo</span>
        </div>
    </div>
    <div class="la-controles">
        <?php if ($puedeEditar): ?><a href="/ensamblador-documentos/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo ensamble</a><?php endif; ?>
    </div>
</div>
<?php if (isset($_GET['guardado'])): ?><div class="alerta alerta-exito">PDF guardado en Archivo. Se regenerará solo cuando cambie alguno de sus documentos.</div><?php endif; ?>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-object-group" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Ensamble</small><strong><?php echo $nEnsambles; ?></strong><span>PDF que unen varios documentos</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Documentos</small><strong><?php echo $nFuentes; ?></strong><span>del Redactor y del Archivo</span></span>
    </div>
    <div class="suite-kpi is-success">
        <span class="suite-kpi-icono"><i class="fa-solid fa-rotate" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>En Archivo</small><strong><?php echo $nEnArchivo; ?></strong><span>se regeneran solos al cambiar</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Salida</small><strong>PDF</strong><span>no editable y siempre al día</span></span>
    </div>
</div>

<?php if (!$ensambles): ?>
    <div class="suite-panel"><?php echo estado_vacio('fa-object-group', 'Aún no hay ensambles', $puedeEditar ? 'Crea el primero con «Nuevo ensamble»: elige los documentos y su orden.' : 'Cuando el equipo cree ensambles aparecerán aquí.', $puedeEditar ? ['texto' => 'Nuevo ensamble', 'url' => '/ensamblador-documentos/crear?proyecto=' . $id, 'icono' => 'fa-plus'] : null); ?></div>
<?php else: ?>
<div class="suite-panel">
    <div class="suite-panel-cab"><div><h3><i class="fa-solid fa-list"></i> Ensambles <span class="suite-chip"><?php echo $nEnsambles; ?></span></h3><p class="text-muted text-sm">El PDF se arma con la versión actual de cada documento.</p></div></div>
    <div class="suite-panel-cuerpo">
    <div class="tabla-wrapper">
    <table class="tabla tabla-ensambles" data-sin-buscador>
        <thead><tr><th>Nombre</th><th>Documentos (en orden)</th><th class="hide-m">En Archivo</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($ensambles as ['e' => $e, 'fuentes' => $fuentes]): $q = 'proyecto=' . $id . '&id=' . (int)$e->id; ?>
            <tr>
                <td><?php echo archivo_icono_html('pdf', 'PDF'); ?> <strong><?php echo s($e->nombre); ?></strong></td>
                <td>
                    <ol class="m-0" style="padding-left:1.8rem">
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
</div>
<p class="text-sm text-muted">El PDF siempre se arma con la versión actual de cada documento. El archivo que descargas ya es un PDF común: no conserva el vínculo ni se actualiza.</p>
<?php endif; ?>
