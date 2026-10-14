<?php $series = $maestra['series']; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-chart-area"></i> Gráfica maestra</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · programa del <?php echo formatear_fecha($proyecto->fecha_inicio); ?> al <?php echo formatear_fecha($proyecto->fecha_fin); ?> · avance acumulado (%) de cada curva</p>
    </div>
    <a href="/curvas?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Curvas</a>
</div>
<?php if (!$series): ?>
    <div class="card"><p class="text-muted">Aún no hay curvas. Crea al menos una para ver la gráfica maestra.</p></div>
<?php else: ?>
<div class="card curva-grafica">
    <p class="text-muted text-sm">Línea continua = planeado · línea discontinua = real. Haz clic en una leyenda para ocultar o mostrar esa curva.</p>
    <canvas id="maestra" height="130"></canvas>
</div>
<script src="/vendor/chart.umd.js"></script>
<script>
(function () {
    const M = <?php echo json_encode($maestra, JSON_UNESCAPED_UNICODE); ?>;
    const texto = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#888';
    Chart.defaults.color = texto;
    Chart.defaults.borderColor = 'rgba(128,128,128,.3)';
    const paleta = ['#ff5a1f', '#00b4d8', '#16a34a', '#a855f7', '#eab308', '#ec4899', '#0ea5e9', '#84cc16', '#f43f5e', '#14b8a6'];
    const fmt = d => { const [y, m, dd] = d.split('-'); return dd + '/' + m + '/' + y.slice(2); };
    const datasets = [];
    M.series.forEach((s, i) => {
        const color = paleta[i % paleta.length];
        datasets.push({ label: s.nombre + ' (plan)', data: s.planeado, borderColor: color, backgroundColor: color, borderWidth: 3, pointRadius: 0, tension: .25 });
        datasets.push({ label: s.nombre + ' (real)', data: s.real, borderColor: color, backgroundColor: color, borderWidth: 3, borderDash: [10, 6], pointRadius: 3, spanGaps: false, tension: .25 });
    });
    const lineaHoy = {
        id: 'lineaHoy',
        afterDatasetsDraw(chart) {
            if (M.hoy === null) return;
            const { ctx, chartArea: a, scales: { x } } = chart;
            const px = x.getPixelForValue(M.hoy);
            ctx.save();
            ctx.strokeStyle = '#0070C0'; ctx.lineWidth = 2.5; ctx.setLineDash([8, 5]);
            ctx.beginPath(); ctx.moveTo(px, a.top); ctx.lineTo(px, a.bottom); ctx.stroke();
            ctx.setLineDash([]); ctx.fillStyle = '#0070C0'; ctx.font = 'bold 12px sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('HOY <?php echo date('d/m/y'); ?>', px, a.top - 6);
            ctx.restore();
        },
    };
    new Chart(document.getElementById('maestra'), {
        type: 'line', plugins: [lineaHoy],
        data: { labels: M.semanas.map(fmt), datasets },
        options: {
            responsive: true, interaction: { mode: 'index', intersect: false },
            plugins: { title: { display: true, text: 'GRÁFICA MAESTRA', font: { size: 20, weight: 'bold' } }, legend: { position: 'bottom' } },
            scales: { y: { min: 0, max: 100, title: { display: true, text: 'Avance acumulado (%)', font: { weight: 'bold' } } }, x: { ticks: { maxTicksLimit: 20 } } },
        },
    });
})();
</script>
<?php endif; ?>
