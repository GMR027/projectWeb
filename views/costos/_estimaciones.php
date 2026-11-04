<?php
/**
 * Estimaciones completas. Variables: $proyecto, $pid, $url, $m, $resumen, $contrato, $resumenContrato,
 * $estimaciones, $estimacionEditar, $siguienteNumero
 */
$c = $contrato ?: ['monto_contrato' => 0, 'anticipo_monto' => 0, 'retencion_pct' => 5, 'iva_pct' => 16, 'notas' => ''];
$rc = $resumenContrato ?: ['monto_contrato' => 0, 'cambios' => 0, 'contrato_vigente' => 0, 'ejecutado' => 0, 'saldo' => 0, 'retenido' => 0, 'amortizado' => 0, 'anticipo_monto' => 0, 'anticipo_pendiente' => 0, 'bruto' => 0, 'neto' => 0, 'retencion_pct' => 5, 'iva_pct' => 16];
$est = $estimacionEditar;
$lineas = [];
foreach (($est['partidas'] ?? []) as $l) $lineas[(int)$l['partida_id']] = (float)$l['importe'];
$antPctDefault = $c['monto_contrato'] > 0 ? round($c['anticipo_monto'] / $c['monto_contrato'] * 100, 2) : 0;
$ivaPct = (float)($est['iva_pct'] ?? $c['iva_pct']);
$retPct = (float)($est['retencion_pct'] ?? $c['retencion_pct']);
$antPct = (float)($est['anticipo_pct'] ?? $antPctDefault);
?>

<div class="card" style="margin-bottom:1.2rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
        <div>
            <h3 class="m-0"><i class="fa-solid fa-file-invoice-dollar"></i> Estimaciones</h3>
            <p class="text-muted text-sm m-0">Subtotal por partida + IVA − retención − amortización del anticipo ± ajuste = neto a cobrar. Al aprobarse descuenta el saldo del contrato.</p>
        </div>
        <?php if (!$est): ?><a class="btn btn-primary" href="<?php echo s($url(['pestana' => 'estimaciones', 'nueva' => 1])); ?>">+ Nueva estimación</a><?php endif; ?>
    </div>

    <?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

    <?php if ($rc['monto_contrato'] > 0): ?>
    <div class="cb-kpis" style="margin:1rem 0 .4rem">
        <div class="cb-kpi"><small>Contrato vigente</small><strong><?php echo $m($rc['contrato_vigente']); ?></strong><span><?php echo $m($rc['monto_contrato']); ?> + cambios <?php echo $m($rc['cambios']); ?></span></div>
        <div class="cb-kpi"><small>Ejecutado (aprobado)</small><strong><?php echo $m($rc['ejecutado']); ?></strong><span>Bruto presentado <?php echo $m($rc['bruto']); ?></span></div>
        <div class="cb-kpi <?php echo $rc['saldo'] < 0 ? 'cb-kpi-alerta' : ''; ?>"><small>Saldo del contrato</small><strong class="<?php echo $rc['saldo'] < 0 ? 'text-danger' : ''; ?>"><?php echo $m($rc['saldo']); ?></strong><span>Por estimar</span></div>
        <div class="cb-kpi"><small>Fondo retenido</small><strong><?php echo $m($rc['retenido']); ?></strong><span>Neto cobrado <?php echo $m($rc['neto']); ?></span></div>
        <div class="cb-kpi"><small>Anticipo pendiente</small><strong><?php echo $m($rc['anticipo_pendiente']); ?></strong><span>Amortizado <?php echo $m($rc['amortizado']); ?></span></div>
    </div>
    <?php endif; ?>

    <details style="margin-top:1rem"<?php echo $rc['monto_contrato'] <= 0 ? ' open' : ''; ?>>
        <summary><strong>Datos del contrato</strong> <small class="text-muted">· monto, anticipo, retención e IVA</small></summary>
        <form method="POST" action="/estimaciones/contrato" class="co-form" style="margin-top:1rem">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <div class="co-campos">
                <label>Monto del contrato (sin IVA)<input type="text" inputmode="decimal" name="monto_contrato" value="<?php echo s(number_format((float)$c['monto_contrato'], 2, '.', '')); ?>" placeholder="0.00"></label>
                <label>Anticipo recibido<input type="text" inputmode="decimal" name="anticipo_monto" value="<?php echo s(number_format((float)$c['anticipo_monto'], 2, '.', '')); ?>" placeholder="0.00"></label>
                <label>Retención (%)<input type="number" name="retencion_pct" min="0" max="100" step="0.01" value="<?php echo s($c['retencion_pct']); ?>"></label>
                <label>IVA (%)<input type="number" name="iva_pct" min="0" max="100" step="0.01" value="<?php echo s($c['iva_pct']); ?>"></label>
                <label>Días de cobro<input type="number" name="dias_cobro" min="0" max="365" value="<?php echo (int)($c['dias_cobro'] ?? 30); ?>"><small class="text-muted">Para proyectar el cobro de las estimaciones</small></label>
                <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"><?php echo s($c['notas']); ?></textarea></label>
            </div>
            <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar datos del contrato</button>
            <?php if ($rc['monto_contrato'] > 0): ?><span class="text-muted text-sm">Cambiar el monto no reescribe las estimaciones ya aprobadas.</span><?php endif; ?>
        </form>
    </details>

    <?php if ($rc['monto_contrato'] <= 0): ?>
        <div class="alerta alerta-aviso" style="margin-top:1rem">Configura el monto del contrato para poder aprobar estimaciones. Puedes guardar borradores mientras tanto.</div>
    <?php endif; ?>
</div>

<?php if ($est || isset($_GET['nueva'])): ?>
<form method="POST" action="/estimaciones/guardar" class="card co-form" id="est-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo $est ? (int)$est['id'] : 0; ?>">
    <h3><?php echo $est ? 'Editar estimación' : 'Nueva estimación'; ?></h3>
    <div class="co-campos">
        <label>Número *<input type="text" name="numero" maxlength="40" required value="<?php echo s($est['numero'] ?? $siguienteNumero); ?>"></label>
        <label>Fecha<input type="date" name="fecha" value="<?php echo s($est['fecha'] ?? date('Y-m-d')); ?>"></label>
        <label>Periodo desde<input type="date" name="periodo_desde" value="<?php echo s($est['periodo_desde'] ?? date('Y-m-01')); ?>"></label>
        <label>Periodo hasta<input type="date" name="periodo_hasta" value="<?php echo s($est['periodo_hasta'] ?? date('Y-m-t')); ?>"></label>
        <label>Estado<select name="estado"><?php foreach (\Classes\Estimaciones::ESTADOS as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo ($est['estado'] ?? 'borrador') === $k ? ' selected' : ''; ?>><?php echo s($etq); ?></option><?php endforeach; ?></select></label>
        <label>IVA (%)<input type="number" name="iva_pct" min="0" max="100" step="0.01" value="<?php echo s($ivaPct); ?>" data-est-pct></label>
        <label>Retención (%)<input type="number" name="retencion_pct" min="0" max="100" step="0.01" value="<?php echo s($retPct); ?>" data-est-pct></label>
        <label>Amortización anticipo (%)<input type="number" name="anticipo_pct" min="0" max="100" step="0.01" value="<?php echo s($antPct); ?>" data-est-pct></label>
        <label>Ajuste de precios (+/−)<input type="text" inputmode="decimal" name="ajuste" value="<?php echo s(number_format((float)($est['ajuste'] ?? 0), 2, '.', '')); ?>" data-est-ajuste></label>
        <label>Cobro estimado<input type="date" name="fecha_cobro_estimada" value="<?php echo s($est['fecha_cobro_estimada'] ?? ''); ?>"></label>
        <label>Cobro real<input type="date" name="fecha_cobro_real" value="<?php echo s($est['fecha_cobro_real'] ?? ''); ?>"></label>
        <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"><?php echo s($est['notas'] ?? ''); ?></textarea></label>
    </div>

    <h4>Detalle por partida</h4>
    <div class="tabla-wrapper"><table class="tabla co-tabla">
        <thead><tr><th>Partida</th><th class="num">Anterior aprobado</th><th class="num">Esta estimación</th><th class="num">Acumulado</th></tr></thead>
        <tbody>
        <?php $anteriores = []; foreach (($est['partidas'] ?? []) as $l) $anteriores[(int)$l['partida_id']] = (float)$l['anterior']; ?>
        <?php foreach ($resumen['partidas'] as $p): $pidP = (int)$p['id']; $ant = (float)($anteriores[$pidP] ?? 0); ?>
            <tr>
                <td><span class="mono"><?php echo s($p['codigo']); ?></span> <?php echo s($p['nombre']); ?></td>
                <td class="num mono"><?php echo $m($ant); ?></td>
                <td class="num"><input type="text" inputmode="decimal" class="co-in" name="partidas[<?php echo $pidP; ?>]" value="<?php echo isset($lineas[$pidP]) ? s(number_format($lineas[$pidP], 2, '.', '')) : ''; ?>" placeholder="0.00" data-est-linea data-anterior="<?php echo $ant; ?>" aria-label="Importe de <?php echo s($p['nombre']); ?>"></td>
                <td class="num mono" data-est-acumulado><?php echo $m($ant + ($lineas[$pidP] ?? 0)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <div class="est-totales" id="est-totales">
        <div><span>Subtotal</span><b class="mono" data-t="subtotal">$0.00</b></div>
        <div><span>Ajuste de precios</span><b class="mono" data-t="ajuste">$0.00</b></div>
        <div><span>IVA</span><b class="mono" data-t="iva">$0.00</b></div>
        <div><span>Retención</span><b class="mono" data-t="retencion">$0.00</b></div>
        <div><span>Amortización del anticipo</span><b class="mono" data-t="amortizacion">$0.00</b></div>
        <div class="est-neto"><span>Neto a cobrar</span><b class="mono" data-t="neto">$0.00</b></div>
    </div>

    <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-top:1rem">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar estimación</button>
        <a class="btn btn-ghost" href="<?php echo s($url(['pestana' => 'estimaciones'])); ?>">Cancelar</a>
        <span class="text-muted text-sm">El borrador no descuenta el contrato; al aprobarlo sí.</span>
    </div>
</form>
<script>
(function () {
    const f = document.getElementById('est-form');
    if (!f) return;
    const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const val = s => { const n = Number(String(s || '').replace(/[^0-9.\-]/g, '')); return isFinite(n) ? n : 0; };
    const pintar = () => {
        let sub = 0;
        f.querySelectorAll('[data-est-linea]').forEach(i => {
            const n = val(i.value); sub += n;
            const ac = i.closest('tr').querySelector('[data-est-acumulado]');
            if (ac) ac.textContent = money(Number(i.dataset.anterior || 0) + n);
        });
        const aj = val(f.querySelector('[data-est-ajuste]')?.value);
        const iva = val(f.querySelector('[name="iva_pct"]').value);
        const ret = val(f.querySelector('[name="retencion_pct"]').value);
        const ant = val(f.querySelector('[name="anticipo_pct"]').value);
        const base = sub + aj;
        const i = base * iva / 100, r = base * ret / 100, a = base * ant / 100;
        const set = (k, v) => { const el = f.querySelector('[data-t="' + k + '"]'); if (el) el.textContent = money(v); };
        set('subtotal', sub); set('ajuste', aj); set('iva', i); set('retencion', r); set('amortizacion', a); set('neto', base + i - r - a);
    };
    f.addEventListener('input', e => { if (e.target.matches('[data-est-linea], [data-est-pct], [data-est-ajuste]')) pintar(); });
    pintar();
})();
</script>
<?php endif; ?>

<div class="card">
    <?php if (!$estimaciones): ?>
        <p class="text-muted">Todavía no hay estimaciones. Crea la primera con el botón <strong>+ Nueva estimación</strong>.</p>
    <?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Número</th><th>Fecha</th><th class="hide-m">Periodo</th><th class="num">Subtotal</th><th class="num">IVA</th><th class="num">Retención</th><th class="num">Amortización</th><th class="num">Neto</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($estimaciones as $e): ?>
            <tr>
                <td><strong class="mono"><?php echo s($e['numero']); ?></strong></td>
                <td class="mono"><?php echo s(formatear_fecha($e['fecha'], 'd/m/Y')); ?></td>
                <td class="hide-m text-sm"><?php echo $e['periodo_desde'] ? s(formatear_fecha($e['periodo_desde'], 'd/m/Y')) : '—'; ?><?php echo $e['periodo_hasta'] ? ' – ' . s(formatear_fecha($e['periodo_hasta'], 'd/m/Y')) : ''; ?></td>
                <td class="num mono"><?php echo $m($e['subtotal']); ?></td>
                <td class="num mono"><?php echo $m($e['iva']); ?></td>
                <td class="num mono"><?php echo $m($e['retencion']); ?></td>
                <td class="num mono"><?php echo $m($e['amortizacion']); ?></td>
                <td class="num mono"><b><?php echo $m($e['neto']); ?></b></td>
                <td><span class="cb-tag"><?php echo s(\Classes\Estimaciones::ESTADOS[$e['estado']] ?? $e['estado']); ?></span></td>
                <td class="acciones" style="white-space:nowrap">
                    <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['pestana' => 'estimaciones', 'editar_estimacion' => $e['id']])); ?>" title="Editar" aria-label="Editar"><i class="fa-solid fa-pen"></i></a>
                    <a class="btn btn-xs btn-ghost" href="/estimaciones/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$e['id']; ?>" title="PDF" aria-label="PDF"><i class="fa-solid fa-file-pdf"></i></a>
                    <?php if ($e['estado'] !== 'pagada'): ?>
                        <form class="inline" method="POST" action="/estimaciones/eliminar" data-confirm="Eliminar estimación|Se borrará la estimación y su movimiento en Costos. Esta acción no se puede deshacer.|Eliminar">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$e['id']; ?>">
                            <button class="btn btn-xs btn-ghost text-danger" type="submit" title="Eliminar" aria-label="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
