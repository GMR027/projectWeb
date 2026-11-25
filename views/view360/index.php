<?php // Variables: $proyecto, $graficos, $puedeEditar ?>
<?php
$nV360 = count($graficos);
$nListos = count(array_filter($graficos, fn($g) => (bool)$g->grafico_id));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-street-view"></i> View360</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · recorridos 360° sobre las láminas del Editor.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-street-view"></i><b><?php echo $nV360; ?></b> gráficos</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-circle-check"></i><b><?php echo $nListos; ?></b> con vista</span>
        </div>
    </div>
    <?php if ($puedeEditar): ?>
        <a href="/view360/crear?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Crear gráfico interactivo</a>
    <?php endif; ?>
</div>
<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-street-view" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Gráficos interactivos</small><strong><?php echo $nV360; ?></strong><span>recorridos sobre el plano</span></span>
    </div>
    <div class="suite-kpi is-success">
        <span class="suite-kpi-icono"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Con vista</small><strong><?php echo $nListos; ?></strong><span>láminas con imagen guardada</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Origen</small><strong>Editor</strong><span>los gráficos se dibujan en /graficos</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-link"></i></span>
        <span class="suite-kpi-txt"><small>Navegación</small><strong>360°</strong><span>con enlaces desde el plano</span></span>
    </div>
</div>

<div class="suite-panel">
    <div class="suite-panel-cab"><div><h3><i class="fa-solid fa-list"></i> Recorridos <span class="suite-chip"><?php echo $nV360; ?></span></h3><p class="text-muted text-sm">Abre uno para recorrer la vista 360° a pantalla completa.</p></div></div>
    <div class="suite-panel-cuerpo">
    <?php if (empty($graficos)): ?>
        <?php echo estado_vacio('fa-street-view', 'Aún no hay gráficos interactivos', $puedeEditar ? 'Crea el primero con «Crear gráfico interactivo».' : 'Cuando el equipo cree recorridos 360° aparecerán aquí.', $puedeEditar ? ['texto' => 'Crear gráfico interactivo', 'url' => '/view360/crear?proyecto=' . (int)$proyecto->id, 'icono' => 'fa-plus'] : null); ?>
    <?php else: ?>
        <div class="tabla-wrapper">
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Nombre del gráfico</th><th>Versión</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($graficos as $g): ?>
                <tr>
                    <td class="mono"><?php echo s(formatear_fecha($g->fecha)); ?></td>
                    <td><?php echo s($g->nombre); ?></td>
                    <td class="mono"><?php echo $g->version !== '' ? s($g->version) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="acciones">
                        <?php if ($g->grafico_id): ?><a href="/view360/ver?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$g->id; ?>" class="btn btn-sm btn-secondary"><i class="fa-solid fa-eye"></i> Ver</a><?php endif; ?>
                        <?php if ($puedeEditar): ?>
                        <?php if ($g->grafico_id): ?><a href="/graficos/abrir?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$g->grafico_id; ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen-ruler"></i> Editor</a><?php endif; ?>
                        <a href="/view360/editar?proyecto=<?php echo (int)$proyecto->id; ?>&id=<?php echo (int)$g->id; ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/view360/eliminar" style="display:inline" data-confirm="¿Eliminar gráfico?|Se eliminará «<?php echo s($g->nombre); ?>».">
                            <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$g->id; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Quitar" aria-label="Quitar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </div>
</div>
