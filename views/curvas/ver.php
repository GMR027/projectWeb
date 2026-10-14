<?php
use Classes\CurvaProductividad as CP;
$n = $serie['n'];
$fmtF = fn($f) => date('n/j/y', strtotime($f));
$datos = [
    'labels' => array_map($fmtF, $serie['fechas']), 'unidad' => $curva->unidad, 'nombre' => $curva->nombre,
    'planeado' => $serie['planeado'], 'actual' => $serie['actual'], 'planeadoAcum' => $serie['planeado_acum'], 'actualAcum' => $serie['actual_acum'],
    'hoy' => $serie['hoy'], 'hoyTexto' => 'HOY ' . date('d/m/y'),
];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-chart-line"></i> <?php echo s($curva->nombre); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo $material ? s($material->nombre) . ' · ' : ''; ?><?php echo number_format((int)$curva->cantidad); ?> <?php echo s($curva->unidad); ?> · <?php echo s(CP::TIPOS[$curva->tipo_curva]); ?> · <?php echo $n; ?> semana(s)</p>
    </div>
    <div class="la-controles">
        <a href="/curvas/maestra?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">Gráfica maestra</a>
        <?php if ($puedeEditar): ?><a href="/curvas/editar?proyecto=<?php echo s($proyecto->id); ?>&id=<?php echo s($curva->id); ?>" class="btn btn-ghost">Editar</a><?php endif; ?>
        <a href="/curvas?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">← Curvas</a>
    </div>
</div>

<div class="card">
    <div class="la-wrap">
    <table class="curva-tabla" id="curva-tabla">
        <thead><tr><th><?php echo s($curva->nombre); ?></th><?php foreach ($serie['fechas'] as $f): ?><th class="mono"><?php echo s($fmtF($f)); ?></th><?php endforeach; ?></tr></thead>
        <tbody>
            <tr><th>Planned <?php echo s($curva->unidad); ?></th><?php foreach ($serie['planeado'] as $v): ?><td><?php echo number_format($v); ?></td><?php endforeach; ?></tr>
            <tr><th>Actual <?php echo s($curva->unidad); ?></th>
                <?php foreach ($serie['actual'] as $i => $v): ?>
                    <td><?php if ($puedeEditar): ?><input type="number" min="0" step="any" class="curva-real" data-periodo="<?php echo $i; ?>" value="<?php echo $v === null ? '' : s($v + 0); ?>"><?php else: ?><?php echo $v === null ? '' : number_format($v); ?><?php endif; ?></td>
                <?php endforeach; ?></tr>
            <tr><th>Planned Cumulative <?php echo s($curva->unidad); ?></th><?php foreach ($serie['planeado_acum'] as $v): ?><td><?php echo number_format($v); ?></td><?php endforeach; ?></tr>
            <tr id="fila-actual-acum"><th>Actual Cumulative <?php echo s($curva->unidad); ?></th><?php foreach ($serie['actual_acum'] as $v): ?><td><?php echo $v === null ? '' : number_format($v); ?></td><?php endforeach; ?></tr>
        </tbody>
    </table>
    </div>
</div>

<div class="card curva-grafica"><canvas id="grafica" height="120"></canvas></div>

<script src="/vendor/chart.umd.js"></script>
<script>
(function () {
    const D = <?php echo json_encode($datos, JSON_UNESCAPED_UNICODE); ?>;
    const curvaId = <?php echo (int)$curva->id; ?>;
    const estilo = getComputedStyle(document.documentElement);
    const texto = estilo.getPropertyValue('--text').trim() || '#888';
    Chart.defaults.color = texto;
    Chart.defaults.borderColor = 'rgba(128,128,128,.3)';
    const nf = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });

    // Línea vertical de la fecha actual
    const lineaHoy = {
        id: 'lineaHoy',
        afterDatasetsDraw(chart) {
            if (D.hoy === null) return;
            const { ctx, chartArea: a, scales: { x } } = chart;
            const px = x.getPixelForValue(D.hoy);
            if (px < a.left || px > a.right) return;
            ctx.save();
            ctx.strokeStyle = '#0070C0'; ctx.lineWidth = 2.5; ctx.setLineDash([8, 5]);
            ctx.beginPath(); ctx.moveTo(px, a.top); ctx.lineTo(px, a.bottom); ctx.stroke();
            ctx.setLineDash([]); ctx.fillStyle = '#0070C0'; ctx.font = 'bold 12px sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(D.hoyTexto, px, a.top - 6);
            ctx.restore();
        },
    };
    const grafica = new Chart(document.getElementById('grafica'), {
        plugins: [lineaHoy],
        data: {
            labels: D.labels,
            datasets: [
                { type: 'bar', label: 'Planned ' + D.unidad, data: D.planeado, backgroundColor: 'rgba(160,160,160,.45)', borderColor: texto, borderWidth: 1, yAxisID: 'y', order: 3 },
                { type: 'bar', label: 'Actual ' + D.unidad, data: D.actual, backgroundColor: 'rgba(60,60,60,.75)', borderColor: texto, borderWidth: 1, yAxisID: 'y', order: 3 },
                { type: 'line', label: 'Planned Cumulative ' + D.unidad, data: D.planeadoAcum, borderColor: '#ff0000', backgroundColor: '#ff0000', pointStyle: 'triangle', pointRadius: 6, borderWidth: 3, yAxisID: 'y2', order: 1 },
                { type: 'line', label: 'Actual Cumulative ' + D.unidad, data: D.actualAcum, borderColor: texto, backgroundColor: texto, pointStyle: 'circle', pointRadius: 5, borderWidth: 3, borderDash: [12, 6], yAxisID: 'y2', spanGaps: false, order: 2 },
            ],
        },
        options: {
            responsive: true,
            plugins: { title: { display: true, text: D.nombre.toUpperCase(), font: { size: 20, weight: 'bold' } }, legend: { position: 'bottom' } },
            scales: {
                y: { beginAtZero: true, position: 'left', title: { display: true, text: D.unidad, font: { weight: 'bold' } } },
                y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Cumulative', font: { weight: 'bold' } } },
            },
        },
    });

    // Captura del real: guarda, recalcula el acumulado y actualiza gráfica y tabla
    const inputs = [...document.querySelectorAll('.curva-real')];
    const filaAcum = document.querySelectorAll('#fila-actual-acum td');
    const recalcular = () => {
        const vals = inputs.map(i => i.value === '' ? null : parseFloat(i.value));
        let ultimo = -1; vals.forEach((v, i) => { if (v !== null && !isNaN(v)) ultimo = i; });
        let acc = 0;
        const acum = vals.map((v, i) => { acc += (v && !isNaN(v)) ? v : 0; return i <= ultimo ? acc : null; });
        grafica.data.datasets[1].data = vals;
        grafica.data.datasets[3].data = acum;
        grafica.update();
        filaAcum.forEach((td, i) => { td.textContent = acum[i] === null ? '' : nf.format(acum[i]); });
    };
    inputs.forEach(inp => inp.addEventListener('change', async () => {
        inp.classList.remove('msr-error');
        try {
            const r = await fetch('/api/curvas/real', { method: 'POST', body: new URLSearchParams({ curva: curvaId, periodo: inp.dataset.periodo, valor: inp.value }) });
            const d = await r.json();
            if (!d.success) { inp.classList.add('msr-error'); return; }
            recalcular();
        } catch (_) { inp.classList.add('msr-error'); }
    }));
})();
</script>
