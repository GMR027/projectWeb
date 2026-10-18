<?php $pid = s($proyecto->id); $r = $cruce['resumen']; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-truck-ramp-box"></i> Materiales contra cronograma</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · compara cuándo se necesita cada material (inicio de la actividad o fecha prevista del cambio) con su compra, entrega y submittal</p>
    </div>
    <a href="/cambios?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Órdenes de cambio</a>
</div>
<div class="cb-kpis">
    <div class="cb-kpi <?php echo $r['tarde'] ? 'cb-kpi-alerta' : ''; ?>"><small>Llegan tarde</small><strong><?php echo (int)$r['tarde']; ?></strong><span>entrega posterior a la necesidad</span></div>
    <div class="cb-kpi <?php echo $r['compra_vencida'] ? 'cb-kpi-alerta' : ''; ?>"><small>Compra vencida</small><strong><?php echo (int)$r['compra_vencida']; ?></strong><span>debieron comprarse ya</span></div>
    <div class="cb-kpi"><small>Sin fecha / submittal</small><strong><?php echo (int)$r['sin_fecha'] + (int)$r['submittal']; ?></strong><span><?php echo (int)$r['sin_fecha']; ?> sin fecha · <?php echo (int)$r['submittal']; ?> sin aprobar</span></div>
    <div class="cb-kpi"><small>A tiempo / en sitio</small><strong><?php echo (int)$r['a_tiempo'] + (int)$r['en_sitio']; ?></strong><span>de <?php echo (int)$r['total']; ?> cruces</span></div>
</div>
<div class="card">
    <?php if (empty($cruce['filas'])): ?>
        <p class="text-muted">No hay materiales ligados a actividades sin terminar. Vincula materiales a las actividades en el Planificador o en las órdenes de cambio.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Situación</th><th>Material</th><th>Para</th><th>Se necesita</th><th>Entrega</th><th class="hide-m">Compra</th></tr></thead>
            <tbody>
            <?php foreach ($cruce['filas'] as $f): [$etq, $sev] = \Classes\CruceMateriales::ESTADOS[$f['estado']]; ?>
                <tr>
                    <td><span class="cb-tag cb-tag-<?php echo s($sev); ?>"><?php echo s($etq); ?><?php echo $f['dias'] ? ' · ' . (int)$f['dias'] . ' d' : ''; ?></span></td>
                    <td><?php echo s($f['material']); ?><?php echo $f['cantidad'] !== null ? ' <span class="mono text-muted">' . s(rtrim(rtrim(number_format((float)$f['cantidad'], 2, '.', ''), '0'), '.') . ' ' . $f['unidad']) . '</span>' : ''; ?></td>
                    <td><span class="mono"><?php echo s($f['ref']); ?></span> <?php echo s($f['actividad']); ?><?php echo $f['origen'] === 'cambio' ? ' <span class="cb-tag cb-tag-media">orden de cambio</span>' : ''; ?></td>
                    <td class="mono"><?php echo $f['necesidad'] ? formatear_fecha($f['necesidad']) : '—'; ?></td>
                    <td class="mono"><?php echo $f['entrega'] ? formatear_fecha($f['entrega']) . ($f['entrega_real'] ? ' ✓' : '') : '—'; ?></td>
                    <td class="hide-m"><?php echo s($f['compra'] === 'comprado' ? 'Comprado' : 'Pendiente'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
