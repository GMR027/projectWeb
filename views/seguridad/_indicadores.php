<?php
/** Indicadores de seguridad. Variables: $indicadores, $resumen */
$i = $indicadores;
?>
<div class="cb-kpis" style="margin-bottom:1.4rem">
    <div class="cb-kpi"><small>Horas-hombre (12 meses)</small><strong><?php echo number_format((float)$i['horas'], 1); ?></strong><span>Del parte diario</span></div>
    <div class="cb-kpi"><small>Accidentes</small><strong><?php echo (int)$i['accidentes']; ?></strong><span><?php echo (int)$i['dias_perdidos']; ?> día(s) perdido(s)</span></div>
    <div class="cb-kpi"><small>Frecuencia</small><strong><?php echo $i['frecuencia'] === null ? '—' : number_format((float)$i['frecuencia'], 2); ?></strong><span>Accidentes × 200,000 ÷ horas</span></div>
    <div class="cb-kpi"><small>Gravedad</small><strong><?php echo $i['gravedad'] === null ? '—' : number_format((float)$i['gravedad'], 2); ?></strong><span>Días perdidos × 200,000 ÷ horas</span></div>
    <div class="cb-kpi"><small>Días sin accidente</small><strong><?php echo $i['dias_sin'] === null ? '—' : (int)$i['dias_sin']; ?></strong><span>Desde el último con días perdidos</span></div>
</div>

<div class="card" style="margin-bottom:1.4rem">
    <h3><i class="fa-solid fa-chart-line"></i> Últimos 6 meses</h3>
    <canvas id="seg-chart" height="100"></canvas>
</div>

<div class="card">
    <h3><i class="fa-solid fa-table"></i> Detalle mensual</h3>
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Mes</th><th class="num">Horas-hombre</th><th class="num">Accidentes</th><th class="num">Días perdidos</th><th class="num">Frecuencia</th><th class="num">Gravedad</th></tr></thead><tbody>
        <?php foreach ($i['mensual'] as $m): ?><tr><td class="mono"><?php echo s($m['etiqueta']); ?></td><td class="num mono"><?php echo number_format((float)$m['horas'], 1); ?></td><td class="num mono"><?php echo (int)$m['accidentes']; ?></td><td class="num mono"><?php echo (int)$m['dias_perdidos']; ?></td><td class="num mono"><?php echo $m['frecuencia'] === null ? '—' : number_format((float)$m['frecuencia'], 2); ?></td><td class="num mono"><?php echo $m['gravedad'] === null ? '—' : number_format((float)$m['gravedad'], 2); ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p class="text-muted text-sm">Si un mes no tiene horas del parte diario, la frecuencia y la gravedad se muestran como «—»: no se inventa un cero. Las horas salen de <a href="/reportes?proyecto=<?php echo (int)$proyecto->id; ?>">Reporte diario</a>.</p>
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
