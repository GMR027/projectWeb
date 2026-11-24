<?php
/** Indicadores de seguridad. Variables: $indicadores, $resumen */
$i = $indicadores;
?>
<div class="suite-kpis" style="margin-bottom:1.4rem">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-clock" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Horas-hombre (12 meses)</small><strong><?php echo number_format((float)$i['horas'], 1); ?></strong><span>del parte diario</span></span>
    </div>
    <div class="suite-kpi <?php echo (int)$i['accidentes'] ? 'is-danger' : 'is-success'; ?>">
        <span class="suite-kpi-icono"><i class="fa-solid fa-user-injured" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Accidentes</small><strong><?php echo (int)$i['accidentes']; ?></strong><span><?php echo (int)$i['dias_perdidos']; ?> día(s) perdido(s)</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-wave-square" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Frecuencia</small><strong><?php echo $i['frecuencia'] === null ? '—' : number_format((float)$i['frecuencia'], 2); ?></strong><span>accidentes × 200,000 ÷ horas</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Gravedad</small><strong><?php echo $i['gravedad'] === null ? '—' : number_format((float)$i['gravedad'], 2); ?></strong><span>días perdidos × 200,000 ÷ horas</span></span>
    </div>
    <div class="suite-kpi is-success">
        <span class="suite-kpi-icono"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Días sin accidente</small><strong><?php echo $i['dias_sin'] === null ? '—' : (int)$i['dias_sin']; ?></strong><span>desde el último con días perdidos</span></span>
    </div>
</div>

<div class="suite-panel" style="margin-bottom:1.4rem">
    <div class="suite-panel-cab"><h3><i class="fa-solid fa-chart-line"></i> Últimos 6 meses</h3></div>
    <div class="suite-panel-cuerpo"><canvas id="seg-chart" height="100"></canvas></div>
</div>

<div class="suite-panel">
    <div class="suite-panel-cab"><h3><i class="fa-solid fa-table"></i> Detalle mensual</h3></div>
    <div class="suite-panel-cuerpo">
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Mes</th><th class="num">Horas-hombre</th><th class="num">Accidentes</th><th class="num">Días perdidos</th><th class="num">Frecuencia</th><th class="num">Gravedad</th></tr></thead><tbody>
        <?php foreach ($i['mensual'] as $m): ?><tr><td class="mono"><?php echo s($m['etiqueta']); ?></td><td class="num mono"><?php echo number_format((float)$m['horas'], 1); ?></td><td class="num mono"><?php echo (int)$m['accidentes']; ?></td><td class="num mono"><?php echo (int)$m['dias_perdidos']; ?></td><td class="num mono"><?php echo $m['frecuencia'] === null ? '—' : number_format((float)$m['frecuencia'], 2); ?></td><td class="num mono"><?php echo $m['gravedad'] === null ? '—' : number_format((float)$m['gravedad'], 2); ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p class="text-muted text-sm">Si un mes no tiene horas del parte diario, la frecuencia y la gravedad se muestran como «—»: no se inventa un cero. Las horas salen de <a href="/reportes?proyecto=<?php echo (int)$proyecto->id; ?>">Reporte diario</a>.</p>
    </div>
</div>

<script src="/vendor/chart.umd.js"></script>
<script>
(function () {
    const M = <?php echo json_encode($i['mensual'], JSON_UNESCAPED_UNICODE); ?>;
    const el = document.getElementById('seg-chart'); if (!el || !M.length) return;
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#888';
    new Chart(el, { type: 'line', data: { labels: M.map(x => x.etiqueta), datasets: [
        { label: 'Frecuencia', data: M.map(x => x.frecuencia), borderColor: '#ff5a1f', borderWidth: 3, tension: .25, spanGaps: false },
        { label: 'Gravedad', data: M.map(x => x.gravedad), borderColor: '#00b4d8', borderWidth: 3, tension: .25, spanGaps: false }
    ] }, options: { responsive: true, scales: { y: { beginAtZero: true } } } });
})();
</script>
