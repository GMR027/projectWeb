<?php
$puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); $cols = array_values(array_filter($diseno['cols'], fn($c) => $c['k'] !== 'acciones' || $puede)); $nCols = count($cols); $grupoActual = null;
$nRegistros = count($registros);
$nCriticas = count(array_filter($registros, fn($r) => $r->importancia === 'critica'));
$nAltas = count(array_filter($registros, fn($r) => $r->importancia === 'alta'));
$ultimo = $registros[0] ?? null;
$nDeptos = count(array_unique(array_filter(array_map(fn($r) => trim((string)$r->departamento), $registros))));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-book"></i> <?php echo s(t('Bitácora de obra')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · el día a día de la obra: visitas, acuerdos, incidencias y clima.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-book"></i><b><?php echo $nRegistros; ?></b> registro<?php echo $nRegistros === 1 ? '' : 's'; ?></span>
            <span class="herr-chip<?php echo $nCriticas ? '' : ' herr-chip-suave'; ?>"><i class="fa-solid fa-circle-exclamation"></i><b><?php echo $nCriticas; ?></b> crítico<?php echo $nCriticas === 1 ? '' : 's'; ?></span>
            <?php if ($ultimo): ?><span class="herr-chip herr-chip-suave"><i class="fa-solid fa-clock-rotate-left"></i> último: <b><?php echo fecha_corta_es($ultimo->fecha); ?></b></span><?php endif; ?>
        </div>
    </div>
    <div class="la-controles">
        <a href="/bitacora/departamentos?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-sitemap"></i> <?php echo s(t('Departamentos')); ?></a>
        <?php if ($puede): ?><a href="/bitacora/crear?proyecto=<?php echo $id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> <?php echo s(t('Nuevo registro')); ?></a><?php endif; ?>
    </div>
</div>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-book" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Registros</small><strong><?php echo $nRegistros; ?></strong><span>eventos documentados</span></span>
    </div>
    <div class="suite-kpi <?php echo $nCriticas ? 'is-danger' : ''; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Críticos</small><strong><?php echo $nCriticas; ?></strong><span><?php echo $nAltas; ?> de importancia alta</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-sitemap" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Departamentos</small><strong><?php echo $nDeptos; ?></strong><span>con registros en la bitácora</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Último registro</small><strong><?php echo $ultimo ? fecha_corta_es($ultimo->fecha) : '—'; ?></strong><span><?php echo $ultimo ? s(\Model\Bitacora::IMPORTANCIAS[$ultimo->importancia] ?? '') : 'sin registros'; ?></span></span>
    </div>
</div>

<form method="GET" class="suite-toolbar">
    <input type="hidden" name="proyecto" value="<?php echo $id; ?>">
    <label><i class="fa-solid fa-sitemap"></i>
        <select name="departamento" onchange="this.form.submit()" aria-label="Filtrar por departamento">
            <option value=""><?php echo s(t('Todos los departamentos')); ?></option>
            <?php foreach ($deptos as $d): ?><option value="<?php echo s($d); ?>" <?php echo $filtroDepto === $d ? 'selected' : ''; ?>><?php echo s($d); ?></option><?php endforeach; ?>
            <option value="__sin" <?php echo $filtroDepto === '__sin' ? 'selected' : ''; ?>><?php echo s(t('Sin departamento')); ?></option>
        </select>
    </label>
    <label><i class="fa-solid fa-signal"></i>
        <select name="importancia" onchange="this.form.submit()" aria-label="Filtrar por importancia">
            <option value=""><?php echo s(t('Toda importancia')); ?></option>
            <?php foreach (\Model\Bitacora::IMPORTANCIAS as $k => $v): ?><option value="<?php echo s($k); ?>" <?php echo $filtroImp === $k ? 'selected' : ''; ?>><?php echo s($v); ?></option><?php endforeach; ?>
        </select>
    </label>
    <?php if ($filtroDepto !== '' || $filtroImp !== ''): ?><a href="/bitacora?proyecto=<?php echo $id; ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Limpiar')); ?></a><?php endif; ?>
</form>

<?php $herramienta = 'bitacora'; $puedeDisenar = $puede; include __DIR__ . '/../listas/_barra.php'; ?>
<div class="suite-panel">
    <div class="suite-panel-cab">
        <div>
            <h3><i class="fa-solid fa-list"></i> Registros <span class="suite-chip"><?php echo $nRegistros; ?></span></h3>
            <p class="text-muted text-sm">Ordenados del más reciente al más antiguo.</p>
        </div>
    </div>
    <div class="suite-panel-cuerpo">
    <?php if (empty($registros)): ?>
        <?php $__filtra = $filtroDepto !== '' || $filtroImp !== '' || $cp['filtros'];
        echo estado_vacio('fa-book', $__filtra ? 'Ningún registro coincide con el filtro' : 'Aún no hay registros en la bitácora',
            $__filtra ? 'Prueba con otro departamento, importancia o quita la búsqueda.' : 'Anota el día a día de la obra: visitas, acuerdos, incidencias y clima.',
            $__filtra || !$puede ? null : ['texto' => 'Nuevo registro', 'url' => '/bitacora/crear?proyecto=' . $id, 'icono' => 'fa-plus'],
            null, '', 'estado-vacio-sm'); ?>
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
                    <td class="mono hide-m nowrap"><?php echo s(fecha_corta_es($b->fecha)); ?></td>
                    <?php break; case 'departamento': ?>
                    <td class="hide-m"><?php echo s($b->departamento ?: '—'); ?></td>
                    <?php break; case 'descripcion': ?>
                    <td style="white-space:pre-wrap"><div class="solo-movil text-sm text-muted" style="white-space:normal"><span class="badge bit-<?php echo s($b->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$b->importancia] ?? $b->importancia); ?></span></div><?php echo s($b->descripcion); ?></td>
                    <?php break; case 'importancia': ?>
                    <td class="hide-m"><span class="badge bit-<?php echo s($b->importancia); ?>"><?php echo s(\Model\Bitacora::IMPORTANCIAS[$b->importancia] ?? $b->importancia); ?></span></td>
                    <?php break; case 'acciones': ?>
                    <td class="acciones">
                        <a href="/bitacora/editar?proyecto=<?php echo $id; ?>&id=<?php echo s($b->id); ?>" class="btn btn-sm btn-ghost"><?php echo s(t('Editar')); ?></a>
                        <form class="inline" method="POST" action="/bitacora/eliminar" data-confirm="¿Eliminar registro?|Se borrará este registro de la bitácora. Esta acción no se puede deshacer.|Eliminar">
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
</div>
