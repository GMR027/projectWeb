<?php
$fmt = fn($b) => \Classes\Volumen::formato($b);
$vols = [
    ['nombre' => 'Volumen de archivos', 'icono' => 'fa-folder-open', 'v' => $archivos, 'pron' => $pronArchivos, 'col' => 'Carpeta'],
    ['nombre' => 'Volumen de base de datos', 'icono' => 'fa-database', 'v' => $bd, 'pron' => $pronBd, 'col' => 'Tabla'],
];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-gear"></i> Ajustes</h2>
        <p class="text-muted">Almacenamiento del servidor · se guarda una foto diaria para estimar cuándo se llenará cada volumen</p>
    </div>
</div>

<?php foreach ($vols as $i => $vol):
    $v = $vol['v'];
    $pct = $v['capacidad'] > 0 ? min(100, $v['usado'] / $v['capacidad'] * 100) : 0;
    $clase = $pct >= 90 ? 'alm-rojo' : ($pct >= 70 ? 'alm-amarillo' : '');
    $p = $vol['pron'];
?>
<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid <?php echo $vol['icono']; ?>"></i> <?php echo s($vol['nombre']); ?></h3>
    <div class="alm-barra" title="<?php echo number_format($pct, 2); ?> %"><span class="<?php echo $clase; ?>" style="width: <?php echo number_format($pct, 2, '.', ''); ?>%"></span></div>
    <p><strong><?php echo $fmt($v['usado']); ?></strong> usados de <?php echo $fmt($v['capacidad']); ?> · <?php echo number_format($pct, 2); ?> % · libres <?php echo $fmt(max(0, $v['capacidad'] - $v['usado'])); ?></p>
    <p class="text-muted text-sm">
        <?php if ($p === null): ?>
            Pronóstico: aún no hay suficiente historial (se necesitan al menos 2 días de datos).
        <?php elseif ($p['dias'] === null): ?>
            Pronóstico: el uso no está creciendo; no se prevé que se llene.
        <?php else: ?>
            Pronóstico: crece ~<?php echo $fmt($p['bytes_dia']); ?> por día; al ritmo actual se llenaría en
            <strong><?php echo $p['dias']; ?> días</strong> (≈ <?php echo formatear_fecha($p['fecha']); ?>).
        <?php endif; ?>
    </p>
    <table class="tabla">
        <thead><tr><th><?php echo $vol['col']; ?></th><th style="text-align:right">Ocupado</th></tr></thead>
        <tbody>
        <?php foreach ($v['desglose'] as $nombre => $bytes): ?>
            <tr><td><?php echo s($nombre); ?></td><td class="mono" style="text-align:right"><?php echo $fmt($bytes); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-chart-line"></i> Evolución del espacio usado</h3>
    <?php if (count($historial) < 2): ?>
        <p class="text-muted">La gráfica aparecerá cuando haya al menos dos días registrados (hoy ya quedó guardado el primero).</p>
    <?php else: ?>
        <canvas id="evolucion" height="110"></canvas>
    <?php endif; ?>
</div>

<div class="card" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-server"></i> Disco del servidor</h3>
    <p class="text-muted text-sm">Los volúmenes no reservan espacio: comparten el disco del nodo con otras aplicaciones. Aunque un volumen tenga capacidad, se llena si el disco se acaba.</p>
    <?php $dp = $disco['total'] > 0 ? 100 - $disco['libre'] / $disco['total'] * 100 : 0; ?>
    <div class="alm-barra"><span class="<?php echo $dp >= 90 ? 'alm-rojo' : ($dp >= 70 ? 'alm-amarillo' : ''); ?>" style="width: <?php echo number_format($dp, 2, '.', ''); ?>%"></span></div>
    <p><strong><?php echo $fmt($disco['libre']); ?></strong> libres de <?php echo $fmt($disco['total']); ?> · <?php echo number_format($dp, 1); ?> % usado</p>
</div>

<div class="card" id="respaldo" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-box-archive"></i> Respaldo</h3>
    <p class="text-muted text-sm">Cada respaldo es un .zip con la base de datos completa (incluye usuarios y contraseñas) y todo el volumen de archivos. Al restaurar se reemplaza todo el sistema y se cierra tu sesión; antes se guarda un respaldo automático «antes_de_restaurar».</p>
    <?php if ($ok): ?><div class="alerta alerta-exito" style="margin-bottom:1rem"><?php echo s($ok); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alerta alerta-error" style="margin-bottom:1rem"><?php echo s($error); ?></div><?php endif; ?>

    <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-end;margin-bottom:2rem">
        <form method="POST" action="/sistema/respaldo/crear" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Generando…'">
            <div><label class="text-sm text-muted">Nombre (opcional)</label><br>
                <input type="text" name="nombre" maxlength="40" placeholder="respaldo"></div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Generar respaldo</button>
        </form>
        <form method="POST" action="/sistema/respaldo/restaurar" enctype="multipart/form-data" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap"
              onsubmit="return confirm('Se reemplazarán TODA la base de datos y TODOS los archivos por los del respaldo. ¿Continuar?')">
            <div><label class="text-sm text-muted">Restaurar desde mi equipo (.zip)</label><br>
                <input type="file" name="respaldo" accept=".zip" required></div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Subir y restaurar</button>
        </form>
    </div>

    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Nombre del respaldo</th><th style="text-align:right">Almacenamiento ocupado</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if (!$respaldos): ?>
            <tr><td colspan="4" class="text-muted">Aún no hay respaldos.</td></tr>
        <?php endif; ?>
        <?php foreach ($respaldos as $r): ?>
            <tr>
                <td class="mono"><?php echo date('d/m/Y H:i', $r['fecha']); ?></td>
                <td><?php echo s($r['nombre']); ?></td>
                <td class="mono" style="text-align:right"><?php echo $fmt($r['bytes']); ?></td>
                <td style="white-space:nowrap">
                    <a class="btn btn-sm btn-ghost" href="/sistema/respaldo/descargar?archivo=<?php echo urlencode($r['archivo']); ?>" title="Descargar"><i class="fa-solid fa-download"></i></a>
                    <form method="POST" action="/sistema/respaldo/restaurar" style="display:inline" onsubmit="return confirm('Se reemplazarán TODA la base de datos y TODOS los archivos por los de este respaldo. ¿Restaurar?')">
                        <input type="hidden" name="archivo" value="<?php echo s($r['archivo']); ?>">
                        <button type="submit" class="btn btn-sm btn-ghost" title="Restaurar"><i class="fa-solid fa-clock-rotate-left"></i></button>
                    </form>
                    <form method="POST" action="/sistema/respaldo/eliminar" style="display:inline" onsubmit="return confirm('¿Eliminar este respaldo?')">
                        <input type="hidden" name="archivo" value="<?php echo s($r['archivo']); ?>">
                        <button type="submit" class="btn btn-sm btn-ghost" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (count($historial) >= 2): ?>
<script src="/vendor/chart.umd.js"></script>
<script>
(function () {
    const H = <?php echo json_encode($historial); ?>;
    const capA = <?php echo (int)$archivos['capacidad']; ?>, capB = <?php echo (int)$bd['capacidad']; ?>;
    const MB = 1048576;
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#888';
    Chart.defaults.borderColor = 'rgba(128,128,128,.3)';
    const fmt = d => { const [y, m, dd] = d.split('-'); return dd + '/' + m + '/' + y.slice(2); };
    new Chart(document.getElementById('evolucion'), {
        type: 'line',
        data: {
            labels: H.map(h => fmt(h.fecha)),
            datasets: [
                { label: 'Archivos (MB)', data: H.map(h => +(h.archivos / MB).toFixed(2)), borderColor: '#ff5a1f', backgroundColor: '#ff5a1f', borderWidth: 3, pointRadius: 3, tension: .25 },
                { label: 'Base de datos (MB)', data: H.map(h => +(h.bd / MB).toFixed(2)), borderColor: '#00b4d8', backgroundColor: '#00b4d8', borderWidth: 3, pointRadius: 3, tension: .25 },
            ],
        },
        options: {
            responsive: true, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => c.dataset.label.replace('(MB)', '') + ': ' + c.parsed.y.toLocaleString() + ' MB' } } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'MB usados' } } },
        },
    });
})();
</script>
<?php endif; ?>
