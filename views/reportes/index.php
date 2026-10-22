<?php $puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); $cols = $diseno['cols']; $nCols = count($cols); $grupoActual = null; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clipboard-list"></i> <?php echo s(t('Reporte diario')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->numero_trabajo ? ' · Trabajo ' . s($proyecto->numero_trabajo) : ''; ?> · <?php echo count($filas); ?> reporte(s)</p>
    </div>
    <div class="la-controles">
        <a href="/reportes/personal?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-users"></i> <?php echo s(t('Personal')); ?></a>
        <?php if ($puede): ?><a href="/reportes/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"><?php echo s(t('+ Nuevo reporte')); ?></a><?php endif; ?>
    </div>
</div>

<?php $herramienta = 'reportes'; $puedeDisenar = $puede; include __DIR__ . '/../listas/_barra.php'; ?>
<div class="card">
    <?php if (empty($filas)): ?>
        <p class="text-muted"><?php echo $cp['filtros'] ? 'Ningún reporte coincide con el filtro.' : 'Aún no hay reportes diarios.'; ?><?php echo !$cp['filtros'] && $puede ? ' Crea el primero con «Nuevo reporte».' : ''; ?></p>
    <?php else: ?>
        <div class="tabla-wrapper"><table class="tabla" <?php echo $cp['agrupar'] ? '' : pag_attr($proyecto); ?>>
            <thead><tr><?php foreach ($cols as $c): ?><th class="<?php echo $c['movil'] ? '' : 'hide-m'; ?>"><?php echo s($c['n']); ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($filas as $f): $r = $f['reporte']; ?>
                <?php if ($cp['agrupar'] && $grupoActual !== $cp['grupo'][(int)$r->id]): $grupoActual = $cp['grupo'][(int)$r->id]; ?>
                    <tr class="cp-grupo"><td colspan="<?php echo $nCols; ?>"><?php echo s($grupoActual); ?></td></tr>
                <?php endif; ?>
                <tr>
                    <?php foreach ($cols as $c): switch ($c['k']): case 'fecha': ?>
                    <td class="mono hide-m" style="white-space:nowrap"><?php echo s(fecha_corta_es($r->fecha)); ?></td>
                    <?php break; case 'actividades': ?>
                    <td>
                        <div class="solo-movil text-sm mono text-muted"><?php echo s(fecha_corta_es($r->fecha)); ?></div>
                        <?php foreach ($f['actividades'] as $n): ?><div><?php echo s($n); ?></div><?php endforeach; ?>
                        <?php if (!$f['actividades']): ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <?php break; case 'acciones': ?>
                    <td class="acciones">
                        <a href="/reportes/ver?proyecto=<?php echo $id; ?>&id=<?php echo s($r->id); ?>" class="btn btn-sm btn-primary"><?php echo s(t('Ver')); ?></a>
                        <?php if ($puede): ?>
                            <a href="/reportes/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($r->id); ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Editar')); ?></a>
                            <form method="POST" action="/reportes/eliminar" style="display:inline" data-confirm="¿Eliminar reporte?|Se borrará el reporte del <?php echo s(fecha_corta_es($r->fecha)); ?> junto con sus fotos. Esta acción no se puede deshacer.|Eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                                <input type="hidden" name="id" value="<?php echo s($r->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><?php echo s(t('Eliminar')); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <?php break; default: if ($c['campo']) echo \Classes\CamposPersonalizados::celda($c['campo'], (int)$r->id, $cp['valores'][(int)$r->id][$c['campo']['id']] ?? '', $puede); endswitch; endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
