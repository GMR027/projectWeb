<?php
/** Costos y presupuesto. Variables: $proyecto, $pestana, $disponible, $resumen, $curva, $movimientos, $cambios, $ordenes, $editar, $error, $ok */
use Classes\Costos;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/costos?' . http_build_query(['proyecto' => $pid] + $q);
$tabs = ['resumen' => ['fa-gauge-high', 'Resumen y valor ganado'], 'real' => ['fa-receipt', 'Costo real'], 'estimaciones' => ['fa-file-invoice-dollar', 'Estimaciones'], 'cambios' => ['fa-file-circle-exclamation', 'Órdenes de cambio']];
$m = fn($x) => Costos::moneda((float)$x);
$ind = fn($v, $bueno = 1.0) => $v === null ? '—' : number_format($v, 2);
$clsIdx = fn($v) => $v === null ? '' : ($v < 0.9 ? 'cs-mal' : ($v < 1 ? 'co-aviso' : 'cs-bien'));
$clsVar = fn($v) => $v === null ? '' : ($v < 0 ? 'cs-mal' : 'cs-bien');
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-sack-dollar"></i> Costos y presupuesto</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · presupuesto por partida, costo real, valor ganado y estimaciones</p>
    </div>
    <div class="la-controles">
        <a href="/curvas/s?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-chart-line"></i> Curva S</a>
        <a href="/cambios?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-file-circle-exclamation"></i> Órdenes de cambio</a>
    </div>
</div>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_fase3.sql</code> para usar los costos.</p></div>
<?php return; endif; ?>

<nav class="centro-ia-tabs" aria-label="Costos">
    <?php foreach ($tabs as $k => [$icono, $nombre]): ?>
        <a href="<?php echo s($url(['pestana' => $k])); ?>" class="centro-ia-tabs__tab<?php echo $k === $pestana ? ' activa' : ''; ?>"<?php echo $k === $pestana ? ' aria-current="page"' : ''; ?>><i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s($nombre); ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($error !== ''): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito">Guardado.</div><?php endif; ?>

<?php if ($pestana === 'resumen'): $t = $resumen['total']; ?>
    <div class="cb-kpis">
        <div class="cb-kpi"><small>Presupuesto vigente</small><strong><?php echo $m($t['bac']); ?></strong><span><?php echo $m($t['presupuesto']); ?> original<?php echo $t['cambios'] ? ' ' . ($t['cambios'] > 0 ? '+' : '−') . ' ' . $m(abs($t['cambios'])) . ' en cambios' : ''; ?></span></div>
        <div class="cb-kpi"><small>Valor ganado (EV)</small><strong><?php echo $m($t['ev']); ?></strong><span>Programado a hoy (PV): <?php echo $m($t['pv']); ?></span></div>
        <div class="cb-kpi"><small>Costo real (AC)</small><strong><?php echo $m($t['ac']); ?></strong><span class="<?php echo $clsVar($t['cv']); ?>">Variación de costo: <?php echo $m($t['cv']); ?></span></div>
        <div class="cb-kpi"><small title="Valor ganado ÷ costo real. Menor a 1 = se gasta más de lo avanzado">CPI · costo</small><strong class="<?php echo $clsIdx($t['cpi']); ?>"><?php echo $ind($t['cpi']); ?></strong><span>SPI · tiempo: <b class="<?php echo $clsIdx($t['spi']); ?>"><?php echo $ind($t['spi']); ?></b></span></div>
        <div class="cb-kpi"><small title="Presupuesto ÷ CPI">Costo final estimado (EAC)</small><strong><?php echo $t['eac'] !== null ? $m($t['eac']) : '—'; ?></strong><span class="<?php echo $clsVar($t['vac']); ?>"><?php echo $t['vac'] !== null ? 'Variación al final: ' . $m($t['vac']) : 'Se calcula con el primer costo real'; ?></span></div>
        <div class="cb-kpi"><small>Estimado al cliente</small><strong><?php echo $m($t['estimado']); ?></strong><span>Por estimar (EV − estimado): <?php echo $m($t['por_cobrar']); ?></span></div>
    </div>

    <?php if ($curva): ?>
    <div class="card curva-grafica">
        <h3><i class="fa-solid fa-chart-line"></i> Curva de costos</h3>
        <p class="text-muted text-sm">Sale de la <a href="/curvas/s?proyecto=<?php echo $pid; ?>">Curva S</a>: lo programado (PV) y lo real (EV) por el presupuesto vigente, contra el costo real acumulado (AC) y lo estimado al cliente.</p>
        <canvas id="curva-costos" height="110"></canvas>
    </div>
    <?php endif; ?>

    <form method="POST" action="/costos/presupuesto" class="card">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <h3><i class="fa-solid fa-list-check"></i> Por partida</h3>
        <?php if (!$resumen['partidas']): ?><p class="text-muted">Aún no hay partidas. Créalas en <a href="/programa?proyecto=<?php echo $pid; ?>">Programa</a>.</p><?php else: ?>
        <div class="tabla-wrapper"><table class="tabla co-tabla">
            <thead><tr><th>Partida</th><th title="Captura el presupuesto de la partida">Presupuesto</th><th class="hide-m">Cambios</th><th class="hide-m">Vigente</th><th class="hide-m">Prog. / real</th><th>PV</th><th>EV</th><th>AC</th><th class="hide-m">CPI</th><th class="hide-m">Estimado</th></tr></thead>
            <tbody>
            <?php foreach ($resumen['partidas'] as $p): ?>
                <tr>
                    <td><span class="co-punto" style="background:<?php echo s($p['color'] ?: '#64748b'); ?>"></span> <span class="mono"><?php echo s($p['codigo']); ?></span> <?php echo s($p['nombre']); ?></td>
                    <td><input type="text" inputmode="decimal" class="co-in" name="presupuesto[<?php echo (int)$p['id']; ?>]" value="<?php echo $p['presupuesto'] ? s(number_format($p['presupuesto'], 2, '.', '')) : ''; ?>" placeholder="0.00" aria-label="Presupuesto de <?php echo s($p['nombre']); ?>"></td>
                    <td class="mono hide-m <?php echo $clsVar($p['cambios']); ?>"><?php echo $p['cambios'] ? $m($p['cambios']) : '—'; ?></td>
                    <td class="mono hide-m"><?php echo $m($p['bac']); ?></td>
                    <td class="mono hide-m"><?php echo number_format($p['plan_pct'], 0); ?>% / <?php echo number_format($p['real_pct'], 0); ?>%</td>
                    <td class="mono"><?php echo $m($p['pv']); ?></td>
                    <td class="mono"><?php echo $m($p['ev']); ?></td>
                    <td class="mono"><?php echo $m($p['ac']); ?></td>
                    <td class="mono hide-m <?php echo $clsIdx($p['cpi']); ?>"><?php echo $ind($p['cpi']); ?></td>
                    <td class="mono hide-m"><?php echo $m($p['estimado']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th>Total</th><th class="mono"><?php echo $m($t['presupuesto']); ?></th><th class="mono hide-m"><?php echo $m($t['cambios']); ?></th><th class="mono hide-m"><?php echo $m($t['bac']); ?></th><th class="hide-m"></th><th class="mono"><?php echo $m($t['pv']); ?></th><th class="mono"><?php echo $m($t['ev']); ?></th><th class="mono"><?php echo $m($t['ac']); ?></th><th class="mono hide-m <?php echo $clsIdx($t['cpi']); ?>"><?php echo $ind($t['cpi']); ?></th><th class="mono hide-m"><?php echo $m($t['estimado']); ?></th></tr></tfoot>
        </table></div>
        <?php $sp = $resumen['sinPartida']; if ($sp['cambios'] || $sp['real'] || $sp['estimado']): ?>
            <p class="text-muted text-sm">Sin partida (cuenta en el total): cambios <?php echo $m($sp['cambios']); ?> · costo real <?php echo $m($sp['real']); ?> · estimado <?php echo $m($sp['estimado']); ?>.</p>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar presupuesto</button>
        <?php endif; ?>
    </form>
    <p class="text-muted text-sm">PV = presupuesto vigente × avance programado a hoy · EV = presupuesto vigente × avance real (ambos pesados por la duración de las actividades, como la Curva S) · AC = costo real capturado · CPI = EV ÷ AC · SPI = EV ÷ PV.</p>

<?php elseif ($pestana === 'real' || $pestana === 'estimaciones'): $tipo = $pestana === 'real' ? 'real' : 'estimacion'; $e = $editar; ?>
    <form method="POST" action="/costos/movimiento" class="card co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
        <input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
        <h3><?php echo $e ? 'Editar' : 'Nuevo'; ?> <?php echo $tipo === 'real' ? 'costo real' : 'estimación'; ?></h3>
        <div class="co-campos">
            <label>Fecha<input type="date" name="fecha" value="<?php echo s($e['fecha'] ?? date('Y-m-d')); ?>" required></label>
            <label>Concepto<input type="text" name="concepto" maxlength="255" value="<?php echo s($e['concepto'] ?? ''); ?>" placeholder="<?php echo $tipo === 'real' ? 'Factura, nómina, renta de equipo…' : 'Estimación No. 3, anticipo…'; ?>" required></label>
            <label>Monto<input type="text" inputmode="decimal" name="monto" value="<?php echo $e ? s(number_format((float)$e['monto'], 2, '.', '')) : ''; ?>" placeholder="0.00" required></label>
            <label>Partida<select name="partida_id"><option value="0">— Sin partida —</option>
                <?php foreach ($resumen['partidas'] as $p): ?><option value="<?php echo (int)$p['id']; ?>"<?php echo (int)($e['partida_id'] ?? 0) === (int)$p['id'] ? ' selected' : ''; ?>><?php echo s($p['codigo'] . ' ' . $p['nombre']); ?></option><?php endforeach; ?>
            </select></label>
            <label><?php echo $tipo === 'real' ? 'Factura / referencia' : 'Número'; ?><input type="text" name="referencia" maxlength="100" value="<?php echo s($e['referencia'] ?? ''); ?>"></label>
            <label>Orden de cambio<select name="orden_cambio_id"><option value="0">— Ninguna —</option>
                <?php foreach ($ordenes as $o): ?><option value="<?php echo (int)$o->id; ?>"<?php echo (int)($e['orden_cambio_id'] ?? 0) === (int)$o->id ? ' selected' : ''; ?>><?php echo s($o->numero . ' · ' . mb_substr($o->titulo, 0, 40)); ?></option><?php endforeach; ?>
            </select></label>
            <?php if ($tipo === 'estimacion'): ?>
            <label>Estado<select name="estado"><?php foreach (Costos::ESTADOS_ESTIMACION as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo ($e['estado'] ?? 'aprobada') === $k ? ' selected' : ''; ?>><?php echo $etq; ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
        <?php if ($e): ?><a href="<?php echo s($url(['pestana' => $pestana])); ?>" class="btn btn-ghost">Cancelar</a><?php endif; ?>
        <?php if ($tipo === 'estimacion'): ?><span class="text-muted text-sm">Las estimaciones en borrador no cuentan en lo estimado.</span><?php endif; ?>
    </form>
    <div class="card">
        <?php if (!$movimientos): ?><p class="text-muted">Aún no hay <?php echo $tipo === 'real' ? 'costos capturados' : 'estimaciones'; ?>.</p><?php else: ?>
        <div class="tabla-wrapper"><table class="tabla" <?php echo pag_attr($proyecto); ?>>
            <thead><tr><th>Fecha</th><th>Concepto</th><th class="hide-m">Partida</th><th class="hide-m">Ref.</th><th class="hide-m">Cambio</th><?php if ($tipo === 'estimacion'): ?><th>Estado</th><?php endif; ?><th>Monto</th><th></th></tr></thead>
            <tbody>
            <?php $suma = 0; foreach ($movimientos as $mv): if ($tipo === 'real' || $mv['estado'] !== 'borrador') $suma += (float)$mv['monto']; ?>
                <tr>
                    <td class="mono"><?php echo formatear_fecha($mv['fecha']); ?></td>
                    <td><?php echo s($mv['concepto']); ?></td>
                    <td class="hide-m"><?php echo $mv['partida'] ? s($mv['partida_codigo'] . ' ' . $mv['partida']) : '<span class="text-muted">—</span>'; ?></td>
                    <td class="hide-m mono"><?php echo s($mv['referencia']); ?></td>
                    <td class="hide-m mono"><?php echo s($mv['oc_numero'] ?? ''); ?></td>
                    <?php if ($tipo === 'estimacion'): ?><td><span class="cb-tag"><?php echo s(Costos::ESTADOS_ESTIMACION[$mv['estado']] ?? $mv['estado']); ?></span></td><?php endif; ?>
                    <td class="mono"><?php echo $m($mv['monto']); ?></td>
                    <td class="acciones">
                        <a href="<?php echo s($url(['pestana' => $pestana, 'editar' => $mv['id']])); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/costos/eliminar" style="display:inline" data-confirm="¿Eliminar este movimiento?|Se quitará del costo del proyecto.">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$mv['id']; ?>"><input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="<?php echo $tipo === 'estimacion' ? 6 : 5; ?>">Total<?php echo $tipo === 'estimacion' ? ' (sin borradores)' : ''; ?></th><th class="mono"><?php echo $m($suma); ?></th><th></th></tr></tfoot>
        </table></div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <div class="card">
        <p class="text-muted text-sm">Las órdenes de cambio aprobadas, ejecutadas o cerradas con costo suben (o bajan) el presupuesto de la partida de su actividad; si no tienen actividad, el del proyecto. El costo se captura en cada <a href="/cambios?proyecto=<?php echo $pid; ?>">orden de cambio</a>.</p>
        <?php if (!$cambios): ?><p class="text-muted">Ninguna orden autorizada tiene costo todavía.</p><?php else: ?>
        <table class="tabla">
            <thead><tr><th>Orden</th><th>Título</th><th>Estado</th><th>Impacto</th></tr></thead>
            <tbody>
            <?php $sum = 0; foreach ($cambios as $c): $sum += (float)$c['impacto_costo']; ?>
                <tr><td class="mono"><a href="/cambios/ver?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$c['id']; ?>"><?php echo s($c['numero']); ?></a></td><td><?php echo s($c['titulo']); ?></td><td><?php echo s(\Model\OrdenCambio::ESTADOS[$c['estado']] ?? ''); ?></td><td class="mono <?php echo $clsVar(-(float)$c['impacto_costo']); ?>"><?php echo $m($c['impacto_costo']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3">Total autorizado</th><th class="mono"><?php echo $m($sum); ?></th></tr></tfoot>
        </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($pestana === 'resumen' && $curva): ?>
<script src="/vendor/chart.umd.js"></script>
<script>
(function () {
    const P = <?php echo json_encode($curva, JSON_UNESCAPED_UNICODE); ?>;
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#888';
    Chart.defaults.borderColor = 'rgba(128,128,128,.3)';
    const fmt = d => { const [y, m, dd] = d.split('-'); return dd + '/' + m; };
    const mon = v => '$' + Number(v).toLocaleString('es-MX', { maximumFractionDigits: 0 });
    const serie = (etq, k, color, extra) => Object.assign({ label: etq, data: P.map(x => x[k]), borderColor: color, backgroundColor: color, borderWidth: 3, pointRadius: 0, tension: .3, spanGaps: false }, extra || {});
    new Chart(document.getElementById('curva-costos'), {
        type: 'line',
        data: { labels: P.map(x => fmt(x.fin)), datasets: [
            serie('Programado (PV)', 'pv', '#00b4d8'),
            serie('Valor ganado (EV)', 'ev', '#16a34a', { borderDash: [10, 6] }),
            serie('Costo real (AC)', 'ac', '#ff5a1f'),
            serie('Estimado al cliente', 'estimado', '#a855f7', { borderWidth: 2 })
        ] },
        options: { responsive: true, interaction: { mode: 'index', intersect: false },
            scales: { y: { min: 0, ticks: { callback: v => mon(v) } }, x: { title: { display: true, text: 'Fin de semana' } } },
            plugins: { tooltip: { callbacks: { label: c => c.dataset.label + ': ' + (c.parsed.y === null ? '—' : mon(c.parsed.y)) } } } }
    });
})();
</script>
<?php endif; ?>
