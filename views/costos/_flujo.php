<?php
/** Flujo de caja. Variables: $proyecto, $pid, $url, $m, $flujo, $costoFinal, $conceptosFlujo, $desdeFlujo, $semanasFlujo */
$t = $flujo['totales'] ?? ['ingresos' => 0, 'egresos' => 0, 'neto' => 0, 'minimo' => 0];
$cf = $costoFinal ?: ['ac' => 0, 'comprometido' => 0, 'restante' => 0, 'final' => 0, 'bac' => 0, 'variacion' => 0];
?>
<div class="card" style="margin-bottom:1.2rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
        <div>
            <h3 class="m-0"><i class="fa-solid fa-money-bill-trend-up"></i> Flujo de caja</h3>
            <p class="text-muted text-sm m-0">Cobros de estimaciones, pagos de compromisos, costo real y gastos recurrentes. Proyección semanal; no es saldo bancario.</p>
        </div>
        <form method="GET" action="/costos" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="pestana" value="flujo">
            <label class="text-sm">Desde<input type="date" name="desde" value="<?php echo s($desdeFlujo); ?>"></label>
            <label class="text-sm">Semanas<input type="number" name="semanas" min="1" max="52" value="<?php echo (int)$semanasFlujo; ?>"></label>
            <button class="btn btn-sm btn-primary" type="submit">Ver</button>
        </form>
    </div>

    <?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

    <div class="cb-kpis" style="margin:1rem 0 .4rem">
        <div class="cb-kpi"><small>Cobros del periodo</small><strong><?php echo $m($t['ingresos']); ?></strong><span>Estimaciones y recurrentes</span></div>
        <div class="cb-kpi"><small>Pagos del periodo</small><strong><?php echo $m($t['egresos']); ?></strong><span>Compromisos, facturas y recurrentes</span></div>
        <div class="cb-kpi <?php echo $t['neto'] < 0 ? 'cb-kpi-alerta' : ''; ?>"><small>Flujo neto</small><strong class="<?php echo $t['neto'] < 0 ? 'text-danger' : ''; ?>"><?php echo $m($t['neto']); ?></strong><span>Acumulado al final</span></div>
        <div class="cb-kpi <?php echo $t['minimo'] < 0 ? 'cb-kpi-alerta' : ''; ?>"><small>Punto más bajo</small><strong class="<?php echo $t['minimo'] < 0 ? 'text-danger' : ''; ?>"><?php echo $m($t['minimo']); ?></strong><span>Acumulado mínimo</span></div>
    </div>
    <?php if ($t['minimo'] < 0): ?><div class="alerta alerta-error"><i class="fa-solid fa-triangle-exclamation"></i> El flujo acumulado se vuelve negativo: hay un hueco de caja de <b><?php echo $m(abs($t['minimo'])); ?></b>. Revisa cobros o negociación de pagos.</div><?php endif; ?>
</div>

<div class="card" style="margin-bottom:1.2rem">
    <h3><i class="fa-solid fa-chart-area"></i> Flujo acumulado</h3>
    <canvas id="flujo-chart" height="110"></canvas>
</div>

<div class="card" style="margin-bottom:1.2rem">
    <h3><i class="fa-solid fa-table"></i> Semana por semana</h3>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Semana</th><th>Del</th><th>Al</th><th class="num">Cobros</th><th class="num">Pagos</th><th class="num">Neto</th><th class="num">Acumulado</th></tr></thead>
        <tbody>
        <?php foreach (($flujo['semanas'] ?? []) as $s): ?>
            <tr class="<?php echo $s['acumulado'] < 0 ? 'flujo-negativo' : ''; ?>">
                <td class="mono"><b><?php echo (int)$s['numero']; ?></b></td>
                <td class="mono"><?php echo s(formatear_fecha($s['inicio'], 'd/m/Y')); ?></td>
                <td class="mono"><?php echo s(formatear_fecha($s['fin'], 'd/m/Y')); ?></td>
                <td class="num mono"><?php echo $m($s['ingresos']); ?></td>
                <td class="num mono"><?php echo $m($s['egresos']); ?></td>
                <td class="num mono <?php echo $s['neto'] < 0 ? 'text-danger' : ''; ?>"><?php echo $m($s['neto']); ?></td>
                <td class="num mono <?php echo $s['acumulado'] < 0 ? 'text-danger' : ''; ?>"><b><?php echo $m($s['acumulado']); ?></b></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="3">Total</th><th class="num mono"><?php echo $m($t['ingresos']); ?></th><th class="num mono"><?php echo $m($t['egresos']); ?></th><th class="num mono"><?php echo $m($t['neto']); ?></th><th class="num mono"><?php echo $m($t['neto']); ?></th></tr></tfoot>
    </table></div>
</div>

<div class="flujo-grid" style="margin-bottom:1.2rem">
    <div class="card">
        <h3><i class="fa-solid fa-flag-checkered"></i> Costo final estimado</h3>
        <div class="flujo-caja">
            <div class="flujo-datos" style="margin:0;border:0;padding:0">
                <div><small>Costo real (AC)</small><b class="mono"><?php echo $m($cf['ac']); ?></b></div>
                <div><small>Comprometido</small><b class="mono"><?php echo $m($cf['comprometido']); ?></b></div>
                <div><small>Trabajo restante</small><b class="mono"><?php echo $m($cf['restante']); ?></b></div>
                <div><small>Costo final</small><b class="mono"><?php echo $m($cf['final']); ?></b></div>
                <div><small>Presupuesto vigente</small><b class="mono"><?php echo $m($cf['bac']); ?></b></div>
                <div><small>Variación</small><b class="mono <?php echo $cf['variacion'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $m($cf['variacion']); ?></b></div>
            </div>
        </div>
        <p class="text-muted text-sm">Costo final = costo real + comprometido + trabajo restante no cubierto por compromisos. No incluye el IVA de tus estimaciones al cliente.</p>
    </div>

    <div class="card">
        <h3><i class="fa-solid fa-list"></i> Próximos movimientos</h3>
        <?php $evs = array_slice($flujo['eventos'] ?? [], 0, 20); ?>
        <?php if (!$evs): ?><p class="text-muted">Sin movimientos en el periodo.</p><?php else: ?>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Concepto</th><th class="num">Monto</th></tr></thead>
            <tbody>
            <?php foreach ($evs as $ev): ?>
                <tr><td class="mono"><?php echo s(formatear_fecha($ev['fecha'], 'd/m/Y')); ?></td><td><span class="cb-tag"><?php echo $ev['tipo'] === 'ingreso' ? 'Cobro' : 'Pago'; ?></span></td><td><?php echo s($ev['concepto']); ?></td><td class="num mono <?php echo $ev['tipo'] === 'egreso' ? 'text-danger' : 'text-success'; ?>"><?php echo ($ev['tipo'] === 'egreso' ? '−' : '+') . $m($ev['monto']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3><i class="fa-solid fa-repeat"></i> Gastos e ingresos recurrentes</h3>
    <p class="text-muted text-sm">Nómina, renta, seguros, servicios… Se repiten en la proyección según su frecuencia.</p>
    <?php if ($puedeEditar ?? true): ?>
    <form method="POST" action="/costos/flujo/concepto" class="co-form" style="margin-top:.6rem">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <div class="co-campos">
            <label>Nombre *<input type="text" name="nombre" maxlength="200" required placeholder="Ej.: nómina quincenal"></label>
            <label>Tipo<select name="tipo"><option value="egreso">Egreso</option><option value="ingreso">Ingreso</option></select></label>
            <label>Monto *<input type="text" inputmode="decimal" name="monto" required placeholder="0.00"></label>
            <label>Frecuencia<select name="frecuencia"><option value="mensual">Mensual</option><option value="quincenal">Quincenal</option><option value="semanal">Semanal</option></select></label>
            <label>Inicio<input type="date" name="fecha_inicio" value="<?php echo date('Y-m-d'); ?>"></label>
            <label>Fin (opcional)<input type="date" name="fecha_fin"></label>
            <label class="co-ancho">Notas<input type="text" name="notas" maxlength="500"></label>
        </div>
        <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-plus"></i> Agregar concepto</button>
    </form>
    <?php endif; ?>
    <?php if (!$conceptosFlujo): ?><p class="text-muted">Sin conceptos recurrentes.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Concepto</th><th>Tipo</th><th class="num">Monto</th><th>Frecuencia</th><th>Desde / hasta</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($conceptosFlujo as $c): ?>
            <tr><td><?php echo s($c['nombre']); ?></td><td><?php echo s(\Classes\FlujoCaja::TIPOS[$c['tipo']] ?? $c['tipo']); ?></td><td class="num mono"><?php echo $m($c['monto']); ?></td><td><?php echo s(\Classes\FlujoCaja::FRECUENCIAS[$c['frecuencia']] ?? $c['frecuencia']); ?></td><td class="mono"><?php echo s(formatear_fecha($c['fecha_inicio'], 'd/m/Y')); ?><?php echo $c['fecha_fin'] ? ' – ' . s(formatear_fecha($c['fecha_fin'], 'd/m/Y')) : ''; ?></td><td><?php if ($puedeEditar ?? true): ?><form class="inline" method="POST" action="/costos/flujo/concepto/eliminar" data-confirm="Eliminar concepto|Se quitará de la proyección de flujo.|Eliminar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>"><button class="btn btn-xs btn-ghost text-danger" type="submit" title="Eliminar" aria-label="Eliminar"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<script src="/vendor/chart.umd.js"></script>
<script>
(function () {
    const S = <?php echo json_encode($flujo['semanas'] ?? [], JSON_UNESCAPED_UNICODE); ?>;
    const el = document.getElementById('flujo-chart');
    if (!el || !S.length) return;
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#888';
    Chart.defaults.borderColor = 'rgba(128,128,128,.3)';
    const mon = v => '$' + Number(v).toLocaleString('es-MX', { maximumFractionDigits: 0 });
    new Chart(el, {
        type: 'bar',
        data: { labels: S.map(s => 'Sem ' + s.numero), datasets: [
            { label: 'Cobros', data: S.map(s => s.ingresos), backgroundColor: 'rgba(5,150,105,.55)' },
            { label: 'Pagos', data: S.map(s => -s.egresos), backgroundColor: 'rgba(220,38,38,.55)' },
            { type: 'line', label: 'Acumulado', data: S.map(s => s.acumulado), borderColor: '#00b4d8', borderWidth: 3, pointRadius: 2, tension: .25 }
        ] },
        options: { responsive: true, interaction: { mode: 'index', intersect: false },
            scales: { y: { ticks: { callback: v => mon(v) } } },
            plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + mon(c.parsed.y) } } } }
    });
})();
</script>
