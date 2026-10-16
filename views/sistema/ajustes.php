<?php
$fmt = fn($b) => \Classes\Volumen::formato($b);
$vols = [
    ['nombre' => 'Volumen de archivos', 'icono' => 'fa-folder-open', 'v' => $archivos, 'pron' => $pronArchivos, 'col' => 'Carpeta'],
    ['nombre' => 'Volumen de base de datos', 'icono' => 'fa-database', 'v' => $bd, 'pron' => $pronBd, 'col' => 'Tabla'],
];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-gear"></i> <?php echo s(t('Ajustes')); ?></h2>
        <p class="text-muted">Almacenamiento del servidor · se guarda una foto diaria para estimar cuándo se llenará cada volumen</p>
    </div>
</div>

<?php foreach ($vols as $i => $vol):
    $v = $vol['v'];
    $pct = $v['capacidad'] > 0 ? min(100, $v['usado'] / $v['capacidad'] * 100) : 0;
    $clase = $pct >= 90 ? 'alm-rojo' : ($pct >= 70 ? 'alm-amarillo' : '');
    $p = $vol['pron'];
?>
<div class="card" id="idioma" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-language"></i> <?php echo s(t('Idioma de la interfaz')); ?> · Language</h3>
    <form method="POST" action="/idioma" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="volver" value="<?php echo s($_SERVER['REQUEST_URI'] ?? '/'); ?>#idioma">
        <select name="idioma" aria-label="<?php echo s(t('Idioma')); ?>"><?php foreach (IDIOMAS as $cod => $nom): ?><option value="<?php echo $cod; ?>"<?php echo idioma_actual() === $cod ? ' selected' : ''; ?>><?php echo s($nom); ?></option><?php endforeach; ?></select>
        <button type="submit" class="btn btn-primary"><?php echo s(t('Guardar')); ?></button>
    </form>
    <p class="text-muted text-sm" style="margin-top:.8rem">Español / English · <?php echo idioma_actual() === 'en' ? 'Menus and the main screens are translated; the rest of the app keeps showing Spanish for now.' : 'Menús y pantallas principales traducidos; el resto de la aplicación sigue en español por ahora.'; ?></p>
</div>

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
        <thead><tr><th><?php echo $vol['col']; ?></th><th style="text-align:right"><?php echo s(t('Ocupado')); ?></th></tr></thead>
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
    <h3 class="card-titulo"><i class="fa-solid fa-server"></i> <?php echo s(t('Disco del servidor')); ?></h3>
    <p class="text-muted text-sm">Los volúmenes no reservan espacio: comparten el disco del nodo con otras aplicaciones. Aunque un volumen tenga capacidad, se llena si el disco se acaba.</p>
    <?php $dp = $disco['total'] > 0 ? 100 - $disco['libre'] / $disco['total'] * 100 : 0; ?>
    <div class="alm-barra"><span class="<?php echo $dp >= 90 ? 'alm-rojo' : ($dp >= 70 ? 'alm-amarillo' : ''); ?>" style="width: <?php echo number_format($dp, 2, '.', ''); ?>%"></span></div>
    <p><strong><?php echo $fmt($disco['libre']); ?></strong> libres de <?php echo $fmt($disco['total']); ?> · <?php echo number_format($dp, 1); ?> % usado</p>
</div>

<div class="card" id="respaldo" style="margin-bottom:2rem">
    <h3 class="card-titulo"><i class="fa-solid fa-box-archive"></i> <?php echo s(t('Respaldo')); ?></h3>
    <p class="text-muted text-sm">Cada respaldo es un .zip con la base de datos completa (incluye usuarios y contraseñas) y todo el volumen de archivos. Al restaurar se reemplaza todo el sistema y se cierra tu sesión; antes se guarda un respaldo automático «antes_de_restaurar».</p>
    <?php if ($ok): ?><div class="alerta alerta-exito" style="margin-bottom:1rem"><?php echo s($ok); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alerta alerta-error" style="margin-bottom:1rem"><?php echo s($error); ?></div><?php endif; ?>

    <div class="alm-prog" style="border:1px solid var(--border);border-radius:1rem;padding:1.4rem;margin-bottom:2rem">
        <h4 style="margin-bottom:.8rem"><i class="fa-solid fa-clock"></i> <?php echo s(t('Respaldo diario automático')); ?></h4>
        <form method="POST" action="/sistema/respaldo/programar" style="display:flex;gap:1.4rem;align-items:flex-end;flex-wrap:wrap">
            <label class="campo-check"><input type="checkbox" name="activo" value="1" <?php echo $programado['activo'] ? 'checked' : ''; ?>> <?php echo s(t('Activado')); ?></label>
            <div><label class="text-sm text-muted"><?php echo s(t('Hora')); ?></label><br><input type="time" name="hora" value="<?php echo s($programado['hora']); ?>" required></div>
            <div><label class="text-sm text-muted">Conservar los últimos</label><br><input type="number" name="conservar" min="1" max="30" value="<?php echo (int)$programado['conservar']; ?>" style="width:8rem"> respaldos</div>
            <button type="submit" class="btn btn-primary"><?php echo s(t('Guardar')); ?></button>
        </form>
        <p class="text-sm" style="margin-top:1rem">
            Último automático: <strong><?php echo $ultimoAuto ? date('d/m/Y H:i', $ultimoAuto) : 'aún ninguno'; ?></strong>
            <?php if ($programado['activo']): ?> · Próximo: <strong><?php echo $proximoAuto <= time() ? 'pendiente (se hará en cuanto el cron corra o tú entres)' : date('d/m/Y H:i', $proximoAuto); ?></strong><?php endif; ?>
        </p>
        <p class="text-muted text-sm">Al hacerse el respaldo número <?php echo (int)$programado['conservar'] + 1; ?> se borra el más antiguo, así que siempre quedan los últimos <?php echo (int)$programado['conservar']; ?> automáticos (los manuales no se tocan). Para que corra a la hora exacta aunque nadie entre, agrega al cron del servidor:
            <code style="display:block;margin-top:.5rem;user-select:all">0 <?php echo (int)substr($programado['hora'], 0, 2); ?> * * * php <?php echo s(realpath(__DIR__ . '/../../bin/respaldo_programado.php') ?: '/ruta/al/proyecto/bin/respaldo_programado.php'); ?></code>
            Sin cron, el respaldo se hace la primera vez que el administrador entra después de esa hora.</p>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-end;margin-bottom:2rem">
        <form method="POST" action="/sistema/respaldo/crear" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Generando…'">
            <div><label class="text-sm text-muted">Nombre (opcional)</label><br>
                <input type="text" name="nombre" maxlength="40" placeholder="respaldo"></div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> <?php echo s(t('Generar respaldo')); ?></button>
        </form>
        <form method="POST" action="/sistema/respaldo/restaurar" enctype="multipart/form-data" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap"
              onsubmit="return confirm('Se reemplazarán TODA la base de datos y TODOS los archivos por los del respaldo. ¿Continuar?')">
            <div><label class="text-sm text-muted">Restaurar desde mi equipo (.zip)</label><br>
                <input type="file" name="respaldo" accept=".zip" required></div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Subir y restaurar</button>
        </form>
    </div>

    <table class="tabla">
        <thead><tr><th><?php echo s(t('Fecha')); ?></th><th>Nombre del respaldo</th><th style="text-align:right">Almacenamiento ocupado</th><th><?php echo s(t('Acciones')); ?></th></tr></thead>
        <tbody>
        <?php if (!$respaldos): ?>
            <tr><td colspan="4" class="text-muted">Aún no hay respaldos.</td></tr>
        <?php endif; ?>
        <?php foreach ($respaldos as $r): ?>
            <tr>
                <td class="mono"><?php echo date('d/m/Y H:i', $r['fecha']); ?></td>
                <td><?php echo s($r['nombre']); ?><?php if (\Classes\Respaldo::esAutomatico($r['archivo'])): ?> <span class="badge"><?php echo s(t('Automático')); ?></span><?php endif; ?></td>
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
