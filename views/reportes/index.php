<?php
$puede = puede_gestionar_proyecto($proyecto); $id = s($proyecto->id); $cols = $diseno['cols']; $nCols = count($cols); $grupoActual = null;
$nReportes = count($filas);
$ultimoReporte = $filas[0]['reporte'] ?? null;
$nEsteMes = count(array_filter($filas, fn($f) => str_starts_with((string)$f['reporte']->fecha, date('Y-m'))));
$nConActs = count(array_filter($filas, fn($f) => !empty($f['actividades'])));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clipboard-list"></i> <?php echo s(t('Reporte diario')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?><?php echo $proyecto->numero_trabajo ? ' · Trabajo ' . s($proyecto->numero_trabajo) : ''; ?> · el parte de campo con personal, equipo, materiales, avance, fotos y firma.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-clipboard-list"></i><b><?php echo $nReportes; ?></b> reporte<?php echo $nReportes === 1 ? '' : 's'; ?></span>
            <?php if ($ultimoReporte): ?><span class="herr-chip herr-chip-suave"><i class="fa-solid fa-calendar-check"></i> último: <b><?php echo fecha_corta_es($ultimoReporte->fecha); ?></b></span><?php endif; ?>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-calendar-days"></i><b><?php echo $nEsteMes; ?></b> este mes</span>
        </div>
    </div>
    <div class="la-controles">
        <a href="/reportes/personal?proyecto=<?php echo $id; ?>" class="btn btn-ghost"><i class="fa-solid fa-users"></i> <?php echo s(t('Personal')); ?></a>
        <?php if ($puede): ?><a href="/reportes/parte?proyecto=<?php echo $id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> <?php echo s(t('Nuevo reporte')); ?></a><?php endif; ?>
    </div>
</div>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Reportes</small><strong><?php echo $nReportes; ?></strong><span>partes diarios registrados</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Este mes</small><strong><?php echo $nEsteMes; ?></strong><span>partes del mes en curso</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Último parte</small><strong><?php echo $ultimoReporte ? fecha_corta_es($ultimoReporte->fecha) : '—'; ?></strong><span><?php echo $ultimoReporte ? s($ultimoReporte->supervisor ?: $ultimoReporte->creado_por ?: 'sin supervisor') : 'sin reportes'; ?></span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Con actividades</small><strong><?php echo $nConActs; ?></strong><span>partes que reportan avance</span></span>
    </div>
</div>

<?php $herramienta = 'reportes'; $puedeDisenar = $puede; include __DIR__ . '/../listas/_barra.php'; ?>
<div class="suite-panel">
    <div class="suite-panel-cab">
        <div>
            <h3><i class="fa-solid fa-list"></i> Historial de reportes <span class="suite-chip"><?php echo $nReportes; ?></span></h3>
            <p class="text-muted text-sm">Abre un parte para ver personal, equipo, materiales, fotos y firma.</p>
        </div>
    </div>
    <div class="suite-panel-cuerpo">
    <?php if (empty($filas)): ?>
        <?php echo estado_vacio('fa-clipboard-list',
            $cp['filtros'] ? 'Ningún reporte coincide con el filtro' : 'Aún no hay reportes diarios',
            $cp['filtros'] ? 'Prueba con otro filtro o quita la búsqueda.' : 'Registra el avance del día, el personal, las fotos y el clima de la obra.',
            $cp['filtros'] || !$puede ? null : ['texto' => 'Nuevo reporte', 'url' => '/reportes/parte?proyecto=' . $id, 'icono' => 'fa-plus'],
            null, '', 'estado-vacio-sm'); ?>
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
                    <td class="mono hide-m nowrap"><?php echo s(fecha_corta_es($r->fecha)); ?></td>
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
</div>
