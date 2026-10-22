<?php // Variables: $proyecto, $datos, $desde, $puedeEditar
$p = $datos['periodos']; $r = $datos['resumen'];
$fmt = fn($f) => date('d/m/y', strtotime($f));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-chart-line"></i> Curva S del programa</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · avance acumulado (%) programado contra real, por semana · peso de cada actividad = su duración</p>
    </div>
    <div class="la-controles">
        <a href="/curvas/maestra?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost"><i class="fa-solid fa-chart-area"></i> Gráfica maestra</a>
        <?php if (puede_gestionar_proyecto($proyecto)): ?><a href="/costos?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost"><i class="fa-solid fa-sack-dollar"></i> Costos</a><?php endif; ?>
        <a href="/curvas?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-ghost">← Curvas</a>
    </div>
</div>

<?php if (!$datos['hayDatos']): ?>
    <div class="card"><p class="text-muted">La Curva S necesita actividades con duración en el programa. Agrégalas en <a href="/programa?proyecto=<?php echo (int)$proyecto->id; ?>">Programa</a>.</p></div>
<?php else: ?>
<div class="cs-resumen">
    <div class="card"><span class="text-muted text-sm">Programado a la fecha</span><strong><?php echo $r ? number_format($r['plan'], 1) . ' %' : '—'; ?></strong></div>
    <div class="card"><span class="text-muted text-sm">Real a la fecha</span><strong><?php echo $r ? number_format($r['real'], 1) . ' %' : '—'; ?></strong></div>
    <div class="card"><span class="text-muted text-sm">Desvío</span><strong class="<?php echo $r && $r['desvio'] < 0 ? 'cs-mal' : 'cs-bien'; ?>"><?php echo $r ? ($r['desvio'] > 0 ? '+' : '') . number_format($r['desvio'], 1) . ' pp' : '—'; ?></strong></div>
    <div class="card"><span class="text-muted text-sm" title="Real ÷ programado. Menor a 1 = atraso">Índice SPI</span><strong class="<?php echo $r && ($r['spi'] ?? 1) < 1 ? 'cs-mal' : 'cs-bien'; ?>"><?php echo $r && $r['spi'] !== null ? number_format($r['spi'], 2) : '—'; ?></strong></div>
</div>

<div class="card curva-grafica">
    <p class="text-muted text-sm">Línea continua = programado · línea discontinua = real. El real se toma del historial: cada vez que cambias el avance de una actividad (o la marcas completada) queda anotado con su fecha.</p>
    <canvas id="curva-s" height="120"></canvas>
</div>

<div class="card">
    <h3>Por semana</h3>
    <?php if ($desde): ?><p class="text-muted text-sm">El historial automático empieza el <strong><?php echo $fmt($desde); ?></strong>. Lo anterior se estimó con la fecha fin programada de las actividades ya completadas; si tienes el avance real de esas semanas, captúralo abajo y sustituye al cálculo.</p><?php endif; ?>
    <form method="POST" action="/curvas/s/manual">
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <div class="tabla-wrapper">
        <table class="tabla">
            <thead><tr><th>Semana</th><th>Del</th><th class="num">Programado</th><th class="num">Real</th><th class="num">Desvío</th><?php if ($puedeEditar): ?><th title="Déjalo vacío para que se calcule solo">Real capturado a mano (%)</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($p as $i => $w): $d = $w['real'] === null ? null : round($w['real'] - $w['plan'], 1); ?>
                <tr<?php echo $w['manual'] ? ' class="cs-manual"' : ''; ?>>
                    <td class="mono"><?php echo $i + 1; ?></td>
                    <td class="mono"><?php echo $fmt($w['inicio']); ?> – <?php echo $fmt($w['fin']); ?></td>
                    <td class="mono num"><?php echo number_format($w['plan'], 1); ?> %</td>
                    <td class="mono num"><?php echo $w['real'] === null ? '—' : number_format($w['real'], 1) . ' %' . ($w['manual'] ? ' ✎' : ''); ?></td>
                    <td class="mono num <?php echo $d !== null && $d < 0 ? 'cs-mal' : 'cs-bien'; ?>"><?php echo $d === null ? '—' : ($d > 0 ? '+' : '') . number_format($d, 1); ?></td>
                    <?php if ($puedeEditar): ?><td><?php if ($w['inicio'] <= date('Y-m-d')): ?><input type="number" name="manual[<?php echo s($w['inicio']); ?>]" min="0" max="100" step="0.1" value="<?php echo $w['manual'] ? s($w['real']) : ''; ?>" style="width:9rem"><?php else: ?><span class="text-muted">—</span><?php endif; ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($puedeEditar): ?><button type="submit" class="btn btn-primary" style="margin-top:1.2rem"><i class="fa-solid fa-floppy-disk"></i> Guardar capturas a mano</button><?php endif; ?>
    </form>
</div>

<script src="/vendor/chart.umd.js"></script>
<script>
(function () {
    const P = <?php echo json_encode($p, JSON_UNESCAPED_UNICODE); ?>;
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#888';
    Chart.defaults.borderColor = 'rgba(128,128,128,.3)';
    const fmt = d => { const [y, m, dd] = d.split('-'); return dd + '/' + m; };
    new Chart(document.getElementById('curva-s'), {
        type: 'line',
        data: { labels: P.map(x => fmt(x.fin)), datasets: [
            { label: 'Programado', data: P.map(x => x.plan), borderColor: '#00b4d8', backgroundColor: '#00b4d8', borderWidth: 3, pointRadius: 0, tension: .3 },
            { label: 'Real', data: P.map(x => x.real), borderColor: '#ff5a1f', backgroundColor: '#ff5a1f', borderWidth: 3, borderDash: [10, 6], pointRadius: 3, spanGaps: false, tension: .3 }
        ] },
        options: { responsive: true, interaction: { mode: 'index', intersect: false },
            scales: { y: { min: 0, max: 100, ticks: { callback: v => v + ' %' } }, x: { title: { display: true, text: 'Fin de semana' } } },
            plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + (c.parsed.y === null ? '—' : c.parsed.y.toFixed(1) + ' %') } } } }
    });
})();
</script>
<?php endif; ?>
