<?php
/** Página pública para el cliente. Variables: $proyecto, $enlace, $secciones, $avance, $curva, $proximas, $fotos, $reportes */
$fd = fn($f) => $f ? formatear_fecha($f) : '';
?>
<header class="publico-cab">
    <?php if ($proyecto->logo && is_file(__DIR__ . '/../../public/build/img/logos/' . $proyecto->logo)): ?><img class="publico-logo" src="/build/img/logos/<?php echo s($proyecto->logo); ?>" alt=""><?php endif; ?>
    <div>
        <h1><?php echo s($proyecto->nombre); ?></h1>
        <p class="text-muted"><?php echo s(trim(($proyecto->empresa ?? '') . ' ' . ($proyecto->ubicacion ? '· ' . $proyecto->ubicacion : ''))); ?></p>
        <p class="text-muted text-sm">Programa: <?php echo $fd($proyecto->fecha_inicio); ?> – <?php echo $fd($proyecto->fecha_fin); ?> · actualizado el <?php echo formatear_fecha(date('Y-m-d')); ?><?php echo $enlace['expira'] ? ' · este enlace vence el ' . $fd(substr($enlace['expira'], 0, 10)) : ''; ?></p>
    </div>
</header>

<?php if ($avance !== null): $r = $curva['resumen'] ?? []; ?>
    <section class="publico-seccion">
        <h2><i class="fa-solid fa-gauge-high"></i> Avance del programa</h2>
        <div class="cb-kpis">
            <div class="cb-kpi"><small>Avance real</small><strong><?php echo number_format($r ? $r['real'] : $avance['general'], 1); ?> %</strong><span>de la obra, pesado por la duración de las actividades</span></div>
            <?php if ($r): ?>
            <div class="cb-kpi"><small>Programado a la fecha</small><strong><?php echo number_format($r['plan'], 1); ?> %</strong><span class="<?php echo $r['desvio'] < 0 ? 'cs-mal' : 'cs-bien'; ?>"><?php echo ($r['desvio'] > 0 ? '+' : '') . number_format($r['desvio'], 1); ?> puntos contra lo programado</span></div>
            <?php endif; ?>
        </div>
        <?php if ($curva && $curva['hayDatos']): ?>
            <div class="card"><canvas id="curva-cliente" height="110"></canvas></div>
        <?php endif; ?>
        <div class="card">
            <h3>Por partida</h3>
            <?php foreach ($avance['partidas'] as $p): ?>
                <div class="publico-barra">
                    <div class="publico-barra__etq"><span><span class="co-punto" style="background:<?php echo s($p['color'] ?: '#64748b'); ?>"></span> <?php echo s($p['codigo'] . ' ' . $p['nombre']); ?></span><strong class="mono"><?php echo number_format($p['pct'], 0); ?>%</strong></div>
                    <div class="rev-barra"><span style="width:<?php echo min(100, $p['pct']); ?>%"></span></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$avance['partidas']): ?><p class="text-muted">El programa aún no tiene partidas.</p><?php endif; ?>
        </div>
        <?php if ($proximas['actividades'] || $proximas['hitos']): ?>
        <div class="card">
            <h3>Próximas tres semanas</h3>
            <?php if ($proximas['hitos']): ?><p><?php foreach ($proximas['hitos'] as $h): ?><span class="cb-tag">◆ <?php echo s($h['nombre']); ?> · <?php echo $fd($h['fecha']); ?></span> <?php endforeach; ?></p><?php endif; ?>
            <?php if ($proximas['actividades']): ?>
            <div class="tabla-wrapper"><table class="tabla">
                <thead><tr><th>Actividad</th><th class="hide-m">Partida</th><th>Inicio</th><th>Fin</th><th>Avance</th></tr></thead>
                <tbody><?php foreach ($proximas['actividades'] as $a): ?>
                    <tr><td><span class="mono"><?php echo s($a['codigo']); ?></span> <?php echo s($a['nombre']); ?></td><td class="hide-m"><?php echo s($a['partida']); ?></td><td class="mono"><?php echo $fd($a['fecha_inicio']); ?></td><td class="mono"><?php echo $fd($a['fecha_fin']); ?></td><td class="mono"><?php echo (int)$a['progreso']; ?>%</td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (in_array('fotos', $secciones, true)): ?>
    <section class="publico-seccion">
        <h2><i class="fa-solid fa-camera-retro"></i> Fotos de la obra</h2>
        <?php if (!$fotos): ?><div class="card"><p class="text-muted">Aún no hay fotos.</p></div><?php else: ?>
        <div class="publico-fotos">
            <?php foreach ($fotos as $f): ?>
                <figure><a href="<?php echo s($f['url']); ?>" target="_blank" rel="noopener"><img src="<?php echo s($f['url']); ?>" alt="<?php echo s($f['texto'] ?: 'Foto de la obra'); ?>" loading="lazy"></a><figcaption><?php echo $fd($f['fecha']); ?><?php echo $f['texto'] ? ' · ' . s($f['texto']) : ''; ?></figcaption></figure>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (in_array('reportes', $secciones, true)): ?>
    <section class="publico-seccion">
        <h2><i class="fa-solid fa-clipboard-list"></i> Reportes diarios</h2>
        <?php if (!$reportes): ?><div class="card"><p class="text-muted">Aún no hay reportes.</p></div><?php endif; ?>
        <?php foreach ($reportes as $r): ?>
            <article class="card">
                <h3><?php echo $fd($r['fecha']); ?><?php echo $r['area'] ? ' · ' . s($r['area']) : ''; ?></h3>
                <?php if (trim((string)$r['comentarios']) !== ''): ?><p><?php echo nl2br(s($r['comentarios'])); ?></p><?php endif; ?>
                <?php if ($r['actividades']): ?><p class="text-muted text-sm">Actividades: <?php echo s(implode(' · ', $r['actividades'])); ?></p><?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if ($avance !== null && $curva && $curva['hayDatos']): ?>
<script src="/vendor/chart.umd.js"></script>
<script>
(function () {
    const P = <?php echo json_encode($curva['periodos'], JSON_UNESCAPED_UNICODE); ?>;
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#888';
    Chart.defaults.borderColor = 'rgba(128,128,128,.3)';
    const fmt = d => { const [y, m, dd] = d.split('-'); return dd + '/' + m; };
    new Chart(document.getElementById('curva-cliente'), {
        type: 'line',
        data: { labels: P.map(x => fmt(x.fin)), datasets: [
            { label: 'Programado', data: P.map(x => x.plan), borderColor: '#00b4d8', backgroundColor: '#00b4d8', borderWidth: 3, pointRadius: 0, tension: .3 },
            { label: 'Real', data: P.map(x => x.real), borderColor: '#ff5a1f', backgroundColor: '#ff5a1f', borderWidth: 3, borderDash: [10, 6], pointRadius: 3, spanGaps: false, tension: .3 }
        ] },
        options: { responsive: true, interaction: { mode: 'index', intersect: false }, scales: { y: { min: 0, max: 100, ticks: { callback: v => v + ' %' } } },
            plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + (c.parsed.y === null ? '—' : c.parsed.y.toFixed(1) + ' %') } } } }
    });
})();
</script>
<?php endif; ?>
