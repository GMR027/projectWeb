<?php
$nMsr = count($msrs);
$nSub = 0; $nRenglones = 0;
foreach ($msrs as $m) { $nSub += (int)$m->n_submittals; $nRenglones += (int)$m->n_renglones; }
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-truck-ramp-box"></i> MSR · Reportes de materiales</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · el reporte de status de materiales que se comparte con el cliente y se actualiza con los submittals.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-truck-ramp-box"></i><b><?php echo $nMsr; ?></b> MSR</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-boxes-packing"></i><b><?php echo $nSub; ?></b> submittals</span>
            <span class="herr-chip herr-chip-suave"><i class="fa-solid fa-list"></i><b><?php echo $nRenglones; ?></b> renglones</span>
        </div>
    </div>
    <?php if ($puedeEditar): ?>
        <a href="/msr/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nuevo MSR</a>
    <?php endif; ?>
</div>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-truck-ramp-box" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Reportes MSR</small><strong><?php echo $nMsr; ?></strong><span>paquetes de materiales del proyecto</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-boxes-packing" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Submittals</small><strong><?php echo $nSub; ?></strong><span>paquetes vinculados a un MSR</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Renglones</small><strong><?php echo $nRenglones; ?></strong><span>materiales con seguimiento</span></span>
    </div>
    <div class="suite-kpi <?php echo $nMsr ? 'is-success' : ''; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-file-export" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Salida</small><strong>PDF</strong><span>listo para imprimir o compartir</span></span>
    </div>
</div>

<div class="suite-panel">
    <div class="suite-panel-cab">
        <div>
            <h3><i class="fa-solid fa-list"></i> Reportes guardados <span class="suite-chip"><?php echo $nMsr; ?></span></h3>
            <p class="text-muted text-sm">Abre un MSR para capturar fechas, status y proveedores; los submittals actualizan sus renglones.</p>
        </div>
    </div>
    <div class="suite-panel-cuerpo">
        <?php if (empty($msrs)): ?>
            <?php echo estado_vacio('fa-truck-ramp-box', 'Aún no hay ningún MSR', 'Crea uno y asígnale paquetes de submittals, o impórtalo desde Excel dentro del MSR.', $puedeEditar ? ['texto' => 'Nuevo MSR', 'url' => '/msr/crear?proyecto=' . s($proyecto->id), 'icono' => 'fa-plus'] : null); ?>
        <?php else: ?>
            <div class="tabla-wrapper"><table class="tabla">
                <thead><tr><th>Nombre</th><th class="hide-m">Descripción</th><th class="hide-m">Submittals</th><th class="hide-m">Renglones</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($msrs as $m): ?>
                    <tr>
                        <td><a href="/msr/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($m->id); ?>"><strong><?php echo s($m->nombre); ?></strong></a></td>
                        <td class="hide-m"><?php echo s($m->descripcion); ?></td>
                        <td class="mono hide-m"><?php echo (int)$m->n_submittals; ?></td>
                        <td class="mono hide-m"><?php echo (int)$m->n_renglones; ?></td>
                        <td class="acciones">
                            <a href="/msr/ver?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($m->id); ?>" class="btn btn-sm btn-primary"><i class="fa-regular fa-eye"></i> Ver</a>
                            <?php if ($puedeEditar): ?>
                                <a href="/msr/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($m->id); ?>" class="btn btn-sm btn-ghost hide-m">Editar</a>
                                <span class="hide-m"><form class="inline" method="POST" action="/msr/eliminar" data-confirm="¿Eliminar MSR?|Se borrarán sus renglones. Los paquetes de submittal se conservan pero quedan «Sin MSR».">
                                    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
                                    <input type="hidden" name="id" value="<?php echo s($m->id); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Quitar" aria-label="Quitar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                </form></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
</div>
