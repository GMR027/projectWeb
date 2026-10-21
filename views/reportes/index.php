<?php $puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clipboard-list"></i> Reporte diario</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->numero_trabajo ? ' · Trabajo ' . s($proyecto->numero_trabajo) : ''; ?> · <?php echo count($filas); ?> reporte(s)</p>
    </div>
    <div class="la-controles">
        <a href="/reportes/personal?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-users"></i> Personal</a>
        <?php if ($puede): ?><a href="/reportes/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary">+ Nuevo reporte</a><?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if (empty($filas)): ?>
        <p class="text-muted">Aún no hay reportes diarios.<?php echo $puede ? ' Crea el primero con «Nuevo reporte».' : ''; ?></p>
    <?php else: ?>
        <table class="tabla" <?php echo pag_attr($proyecto); ?>>
            <thead><tr><th class="hide-m">Fecha</th><th>Actividad del programa</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $f): $r = $f['reporte']; ?>
                <tr>
                    <td class="mono hide-m" style="white-space:nowrap"><?php echo s(fecha_corta_es($r->fecha)); ?></td>
                    <td>
                        <div class="solo-movil text-sm mono text-muted"><?php echo s(fecha_corta_es($r->fecha)); ?></div>
                        <?php foreach ($f['actividades'] as $n): ?><div><?php echo s($n); ?></div><?php endforeach; ?>
                        <?php if (!$f['actividades']): ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="acciones">
                        <a href="/reportes/ver?proyecto=<?php echo $id; ?>&id=<?php echo s($r->id); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-primary">Ver</a>
                        <?php if ($puede): ?>
                            <a href="/reportes/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($r->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                            <form method="POST" action="/reportes/eliminar" style="display:inline" data-confirm="¿Eliminar reporte?|Se borrará el reporte del <?php echo s(fecha_corta_es($r->fecha)); ?> junto con sus fotos. Esta acción no se puede deshacer.|Eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                                <input type="hidden" name="id" value="<?php echo s($r->id); ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
