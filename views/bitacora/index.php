<?php $puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); $cols = array_values(array_filter($diseno['cols'], fn($c) => $c['k'] !== 'acciones' || $puede)); $nCols = count($cols); $grupoActual = null; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-book"></i> <?php echo s(t('Bitácora de obra')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo count($registros); ?> registro(s)</p>
    </div>
    <div class="la-controles">
        <form method="GET" class="buscador-inline" style="display:flex;gap:.8rem">
            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
            <select name="departamento" onchange="this.form.submit()" aria-label="Filtrar por departamento">
                <option value=""><?php echo s(t('Todos los departamentos')); ?></option>
                <?php foreach ($deptos as $d): ?><option value="<?php echo s($d); ?>" <?php echo $filtroDepto === $d ? 'selected' : ''; ?>><?php echo s($d); ?></option><?php endforeach; ?>
                <option value="__sin" <?php echo $filtroDepto === '__sin' ? 'selected' : ''; ?>><?php echo s(t('Sin departamento')); ?></option>
            </select>
            <select name="importancia" onchange="this.form.submit()" aria-label="Filtrar por importancia">
                <option value=""><?php echo s(t('Toda importancia')); ?></option>
                <?php foreach (\Model\Bitacora::IMPORTANCIAS as $k => $v): ?><option value="<?php echo s($k); ?>" <?php echo $filtroImp === $k ? 'selected' : ''; ?>><?php echo s($v); ?></option><?php endforeach; ?>
            </select>
            <?php if ($filtroDepto !== '' || $filtroImp !== ''): ?><a href="/bitacora?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><?php echo s(t('Limpiar')); ?></a><?php endif; ?>
        </form>
        <a href="/bitacora/departamentos?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-sitemap"></i> <?php echo s(t('Departamentos')); ?></a>
        <?php if ($puede): ?><a href="/bitacora/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"><?php echo s(t('+ Nuevo registro')); ?></a><?php endif; ?>
    </div>
</div>
<?php $herramienta = 'bitacora'; $puedeDisenar = $puede; include __DIR__ . '/../listas/_barra.php'; ?>
<div class="card">
    <?php if (empty($registros)): ?>
        <p class="text-muted"><?php echo ($filtroDepto !== '' || $filtroImp !== '' || $cp['filtros']) ? 'Ningún registro coincide con el filtro.' : 'Aún no hay registros en la bitácora.'; ?></p>
    <?php else: ?>
        <div class="tabla-wrapper"><table class="tabla" <?php echo $cp['agrupar'] ? '' : pag_attr($proyecto); ?>>
            <thead><tr><?php foreach ($cols as $c): ?><th class="<?php echo $c['movil'] ? '' : 'hide-m'; ?>"><?php echo s($c['n']); ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($registros as $b): ?>
                <?php if ($cp['agrupar'] && $grupoActual !== $cp['grupo'][(int)$b->id]): $grupoActual = $cp['grupo'][(int)$b->id]; ?>
                    <tr class="cp-grupo"><td colspan="<?php echo $nCols; ?>"><?php echo s($grupoActual); ?></td></tr>
                <?php endif; ?>
                <tr>
                    <?php foreach ($cols as $c): switch ($c['k']): case 'fecha': ?>
                    <td class="mono hide-m" style="white-space:nowrap"><?php echo s(fecha_corta_es($b->fecha)); ?></td>
                    <?php break; case 'departamento': ?>
                    <td class="hide-m"><?php echo s($b->departamento ?: '—'); ?></td>
                    <?php break; case 'descripcion': ?>
                    <td style="white-space:pre-wrap"><div class="solo-movil text-sm text-muted" style="white-space:normal"><span class="badge bit-<?php echo s($b->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$b->importancia] ?? $b->importancia); ?></span></div><?php echo s($b->descripcion); ?></td>
                    <?php break; case 'importancia': ?>
                    <td class="hide-m"><span class="badge bit-<?php echo s($b->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$b->importancia] ?? $b->importancia); ?></span></td>
                    <?php break; case 'acciones': ?>
                    <td class="acciones">
                        <a href="/bitacora/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($b->id); ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Editar')); ?></a>
                        <form method="POST" action="/bitacora/eliminar" style="display:inline" data-confirm="¿Eliminar registro?|Se borrará este registro de la bitácora. Esta acción no se puede deshacer.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
                            <input type="hidden" name="id" value="<?php echo s($b->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?php echo s(t('Eliminar')); ?></button>
                        </form>
                    </td>
                    <?php break; default: if ($c['campo']) echo \Classes\CamposPersonalizados::celda($c['campo'], (int)$b->id, $cp['valores'][(int)$b->id][$c['campo']['id']] ?? '', $puede); endswitch; endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
