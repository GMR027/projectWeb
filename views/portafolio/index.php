<?php
/** Tablero de portafolio. Variables: $filas, $resumen, $filtros, $clientes, $disponible */
$esAdmin = is_admin();
$qs = array_filter(['q' => $filtros['q'], 'cliente' => $filtros['cliente'] ?: null, 'alertas' => $filtros['alertas'] ? 1 : null, 'orden' => $filtros['orden'], 'dir' => $filtros['dir']], fn($v) => $v !== null && $v !== '');
$baseExport = '/portafolio/exportar?' . http_build_query($qs + ['formato' => 'pdf']);
$baseOds = '/portafolio/exportar?' . http_build_query($qs + ['formato' => 'ods']);
$num = fn($v, $d = 2) => $v === null ? '—' : number_format((float)$v, $d);
$pct = fn($v) => $v === null ? '—' : number_format((float)$v, 1) . ' %';
$colSalud = ['ok' => 'var(--success, #10b981)', 'aviso' => 'var(--warning, #d97706)', 'critico' => 'var(--danger, #ef4444)'];
$rotSalud = ['ok' => 'En orden', 'aviso' => 'Atención', 'critico' => 'Urgente'];
$alertasTodas = [];
foreach ($filas as $f) foreach ($f['alertas'] as $a) $alertasTodas[] = $a + ['proyecto' => $f['nombre'], 'codigo' => $f['codigo'], 'id' => $f['id'], 'nivel' => $a['nivel']];
usort($alertasTodas, fn($a, $b) => ($b['nivel'] === 'critico') <=> ($a['nivel'] === 'critico'));
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-table-columns"></i> Tablero de portafolio</h2>
        <p class="text-muted"><?php echo s($resumen['total']); ?> obra(s) · indicadores lado a lado y alertas por tendencia</p>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
        <a href="<?php echo s($baseExport); ?>" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        <a href="<?php echo s($baseOds); ?>" class="btn btn-ghost"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="/proyectos" class="btn btn-ghost">← Proyectos</a>
    </div>
</div>

<?php if (!empty($_GET['error'])): ?><div class="alerta alerta-error" role="alert"><?php echo s(texto_param($_GET['error'])); ?></div><?php endif; ?>
<?php if (!$disponible): ?><div class="alerta alerta-aviso">Falta aplicar <code>database/migracion_portafolio.sql</code> para guardar las alertas de portafolio. El tablero funciona igual, pero la campana no las mostrará.</div><?php endif; ?>

<div class="grid-stats">
    <div class="stat-card"><div class="stat-label">Obras</div><div class="stat-valor"><?php echo (int)$resumen['total']; ?></div></div>
    <div class="stat-card"><div class="stat-label">Con alertas</div><div class="stat-valor<?php echo $resumen['con_alertas'] ? ' stat-danger' : ''; ?>"><?php echo (int)$resumen['con_alertas']; ?></div></div>
    <div class="stat-card"><div class="stat-label">PPC medio</div><div class="stat-valor<?php echo $resumen['ppc'] === null ? '' : ($resumen['ppc'] < 70 ? ' text-danger' : ($resumen['ppc'] >= 85 ? ' text-success' : '')); ?>"><?php echo s($pct($resumen['ppc'])); ?></div></div>
    <div class="stat-card"><div class="stat-label">CPI medio</div><div class="stat-valor<?php echo $resumen['cpi'] === null ? '' : ($resumen['cpi'] < 1 ? ' text-danger' : ' text-success'); ?>"><?php echo s($num($resumen['cpi'])); ?></div></div>
    <div class="stat-card"><div class="stat-label">SPI medio</div><div class="stat-valor<?php echo $resumen['spi'] === null ? '' : ($resumen['spi'] < 0.95 ? ' text-danger' : ($resumen['spi'] >= 1 ? ' text-success' : '')); ?>"><?php echo s($num($resumen['spi'])); ?></div></div>
</div>

<?php if ($alertasTodas): ?>
<div class="card" style="margin-bottom:1.2rem">
    <h3><i class="fa-solid fa-triangle-exclamation" style="color:var(--danger,#ef4444)"></i> Tendencias que requieren atención <span class="badge badge-cliente"><?php echo count($alertasTodas); ?></span></h3>
    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.6rem">
        <?php foreach (array_slice($alertasTodas, 0, 10) as $a): ?>
        <li style="display:flex;gap:.7rem;align-items:flex-start;border-left:3px solid <?php echo $a['nivel'] === 'critico' ? 'var(--danger,#ef4444)' : 'var(--warning,#d97706)'; ?>;padding:.4rem .8rem;background:var(--bg-secondary,#f8fafc);border-radius:.4rem">
            <i class="fa-solid <?php echo $a['nivel'] === 'critico' ? 'fa-arrow-trend-down' : 'fa-triangle-exclamation'; ?>" style="color:<?php echo $a['nivel'] === 'critico' ? 'var(--danger,#ef4444)' : 'var(--warning,#d97706)'; ?>;margin-top:.3rem"></i>
            <div><a href="/programa?proyecto=<?php echo (int)$a['id']; ?>"><strong><?php echo s($a['codigo'] . ' · ' . $a['proyecto']); ?></strong></a><br><span class="text-sm"><?php echo s($a['mensaje']); ?></span></div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if (count($alertasTodas) > 10): ?><p class="text-muted text-sm">y <?php echo count($alertasTodas) - 10; ?> alerta(s) más en la tabla.</p><?php endif; ?>
</div>
<?php endif; ?>

<form method="GET" action="/portafolio" class="card inc-filtros">
    <label>Buscar<input type="search" name="q" value="<?php echo s($filtros['q']); ?>" placeholder="Código, obra o cliente…"></label>
    <?php if ($esAdmin && $clientes): ?>
    <label>Cliente<select name="cliente"><option value="">Todos</option><?php foreach ($clientes as $id => $nombre): ?><option value="<?php echo (int)$id; ?>"<?php echo (int)$filtros['cliente'] === (int)$id ? ' selected' : ''; ?>><?php echo s($nombre); ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label>Ordenar por<select name="orden"><?php foreach (\Classes\Portafolio::ORDENES as $k => $t): ?><option value="<?php echo s($k); ?>"<?php echo $filtros['orden'] === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
    <label>Sentido<select name="dir"><option value="desc"<?php echo $filtros['dir'] === 'desc' ? ' selected' : ''; ?>>Mayor a menor</option><option value="asc"<?php echo $filtros['dir'] === 'asc' ? ' selected' : ''; ?>>Menor a mayor</option></select></label>
    <label class="inc-check"><input type="checkbox" name="alertas" value="1"<?php echo $filtros['alertas'] ? ' checked' : ''; ?>> Solo con alertas</label>
    <div class="inc-acciones">
        <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
        <a class="btn btn-ghost btn-sm" href="/portafolio">Limpiar</a>
    </div>
</form>

<?php if (!$filas): ?>
<div class="card"><p class="text-muted">No hay obras que cumplan los filtros. <a href="/portafolio">Quitar filtros</a> o crea una obra desde <a href="/proyectos">Proyectos</a>.</p></div>
<?php else: ?>
<div class="card">
    <h3><i class="fa-solid fa-list"></i> Comparativa de obras</h3>
    <div class="tabla-wrapper"><table class="tabla tabla-ancha-xl">
        <thead><tr><th>Obra</th><th class="num">Avance real</th><th class="num hide-m">Plan</th><th class="num">SPI</th><th class="num">CPI</th><th class="num hide-m">PPC</th><th class="num">Atrasadas</th><th class="num hide-m">Punch</th><th>Salud</th><th class="num">Alertas</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($filas as $f): ?>
            <tr>
                <td>
                    <a href="/programa?proyecto=<?php echo (int)$f['id']; ?>"><strong><?php echo s($f['nombre']); ?></strong></a><br>
                    <small class="text-muted"><span class="mono"><?php echo s($f['codigo']); ?></span><?php if ($f['cliente']): ?> · <?php echo s($f['cliente']); ?><?php endif; ?></small>
                </td>
                <td class="num mono"><?php echo s($pct($f['real'])); ?></td>
                <td class="num mono text-muted hide-m"><?php echo s($pct($f['plan'])); ?></td>
                <td class="num mono" style="color:<?php echo $f['spi'] !== null && $f['spi'] < 0.95 ? 'var(--danger,#ef4444)' : 'inherit'; ?>"><?php echo s($num($f['spi'])); ?></td>
                <td class="num mono" style="color:<?php echo $f['cpi'] !== null && $f['cpi'] < 1 ? 'var(--danger,#ef4444)' : 'inherit'; ?>"><?php echo s($num($f['cpi'])); ?></td>
                <td class="num mono hide-m" style="color:<?php echo $f['ppc'] !== null && $f['ppc'] < 70 ? 'var(--danger,#ef4444)' : 'inherit'; ?>"><?php echo s($pct($f['ppc'])); ?></td>
                <td class="num mono"><?php echo (int)$f['atrasadas']; ?></td>
                <td class="num mono hide-m"><?php echo (int)$f['punch']; ?><?php if ($f['punch_critico']): ?> <span class="text-danger" title="Críticos">!</span><?php endif; ?></td>
                <td><span style="color:<?php echo $colSalud[$f['salud']] ?? 'inherit'; ?>;font-weight:600"><?php echo s($rotSalud[$f['salud']] ?? $f['salud']); ?></span></td>
                <td class="num mono"><?php echo count($f['alertas']); ?></td>
                <td><a class="btn btn-xs btn-ghost" href="/programa?proyecto=<?php echo (int)$f['id']; ?>" title="Abrir el programa" aria-label="Abrir el programa"><i class="fa-solid fa-arrow-right"></i></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="solo-movil text-muted text-sm"><i class="fa-solid fa-arrows-left-right"></i> Desliza la tabla para ver el resto de columnas.</p>
    <p class="text-muted text-sm" style="margin-top:.8rem">SPI = avance real ÷ planificado · CPI = valor ganado ÷ costo real · PPC = actividades terminadas ÷ las que debían terminar en la última semana completa. Las alertas comparan el indicador con hace 4 semanas (o con las 3 semanas anteriores en el PPC).</p>
</div>
<?php endif; ?>
