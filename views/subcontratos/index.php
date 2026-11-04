<?php
/** Subcontratos. Variables: $proyecto, $disponible, $lista, $resumen, $subcontrato, $editar, $abrirForm, $siguienteCodigo, $archivos, $partidas, $puedeEditar, $ok, $error */
use Classes\Subcontratos;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/subcontratos?' . http_build_query(['proyecto' => $pid] + $q);
$m = fn($v) => '$' . number_format((float)$v, 2);
$s = $subcontrato;
$e = $editar;
$hoy = date('Y-m-d');
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-handshake"></i> Subcontratos</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · alcance, monto, documentos obligatorios y estimaciones</p>
    </div>
    <?php if ($disponible && $puedeEditar && !$s): ?><a class="btn btn-primary" href="<?php echo s($url(['nuevo' => 1])); ?>">+ Nuevo subcontrato</a><?php endif; ?>
</div>

<?php if (es_demo()): ?>
<div class="alerta alerta-info">
    <strong>Demo · Subcontratos.</strong> El subcontrato reserva su monto como compromiso. Antes de aprobar o pagar una estimación se revisan fianza, seguro, permisos y alta de personal: si falta uno, el pago se bloquea salvo autorización del administrador con motivo.
    <a href="/manual#subcontratos">Ver en el manual</a>.
</div>
<?php endif; ?>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_subcontratos.sql</code> para usar los subcontratos.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<?php if (!$s): ?>
<div class="cb-kpis" style="margin-bottom:1.4rem">
    <div class="cb-kpi"><small>Subcontratos</small><strong><?php echo (int)$resumen['total']; ?></strong><span>Con alcance y monto</span></div>
    <div class="cb-kpi"><small>Monto contratado</small><strong class="mono"><?php echo $m($resumen['monto']); ?></strong><span>Pagado <?php echo $m($resumen['pagado']); ?></span></div>
    <div class="cb-kpi"><small>Saldo por pagar</small><strong class="mono"><?php echo $m($resumen['saldo']); ?></strong><span>Según estimaciones aprobadas</span></div>
    <div class="cb-kpi <?php echo $resumen['con_faltantes'] ? 'cb-kpi-alerta' : ''; ?>"><small>Con documentos faltantes</small><strong><?php echo (int)$resumen['con_faltantes']; ?></strong><span><?php echo (int)$resumen['por_vencer']; ?> por vencer</span></div>
</div>
<?php endif; ?>

<?php if ($abrirForm && $puedeEditar): ?>
<form method="POST" action="/subcontratos/guardar" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
    <h3><?php echo $e ? 'Editar subcontrato' : 'Nuevo subcontrato'; ?></h3>
    <div class="co-campos">
        <label>Código<input type="text" name="codigo" maxlength="40" value="<?php echo s($e['codigo'] ?? $siguienteCodigo); ?>"></label>
        <label>Subcontratista *<input type="text" name="proveedor" maxlength="200" required value="<?php echo s($e['proveedor'] ?? ''); ?>"></label>
        <label>Partida *<select name="partida_id" required><option value="">— Elige —</option><?php foreach ($partidas as $p): ?><option value="<?php echo (int)$p->id; ?>"<?php echo (int)($e['partida_id'] ?? 0) === (int)$p->id ? ' selected' : ''; ?>><?php echo s($p->codigo . ' ' . $p->nombre); ?></option><?php endforeach; ?></select></label>
        <label>Monto *<input type="text" inputmode="decimal" name="monto" required value="<?php echo $e ? s(number_format((float)$e['monto'], 2, '.', '')) : ''; ?>" placeholder="0.00"></label>
        <label>Retención (%)<input type="number" name="retencion_pct" min="0" max="100" step="0.01" value="<?php echo s($e['retencion_pct'] ?? 10); ?>"></label>
        <label>Anticipo (%)<input type="number" name="anticipo_pct" min="0" max="100" step="0.01" value="<?php echo s($e['anticipo_pct'] ?? 0); ?>"></label>
        <label>Inicio<input type="date" name="fecha_inicio" value="<?php echo s($e['fecha_inicio'] ?? date('Y-m-d')); ?>"></label>
        <label>Fin<input type="date" name="fecha_fin" value="<?php echo s($e['fecha_fin'] ?? ''); ?>"></label>
        <label>Estado<select name="estado"><?php foreach (Subcontratos::ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['estado'] ?? 'vigente') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label class="co-ancho">Alcance<textarea name="alcance" rows="2" maxlength="5000" placeholder="Qué hace y qué no incluye"><?php echo s($e['alcance'] ?? ''); ?></textarea></label>
        <label class="co-ancho">Motivo del sobregiro (si el monto supera el disponible de la partida)<input type="text" name="motivo_sobregiro" maxlength="500" value="<?php echo s($e['motivo_sobregiro'] ?? ''); ?>"></label>
        <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"><?php echo s($e['notas'] ?? ''); ?></textarea></label>
    </div>
    <div style="display:flex;gap:.8rem;flex-wrap:wrap"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar subcontrato</button><a class="btn btn-ghost" href="<?php echo s($url()); ?>">Cancelar</a></div>
    <p class="text-muted text-sm">Al guardarlo se reserva su monto como compromiso en Costos. El borrador no reserva.</p>
</form>
<?php endif; ?>

<?php if ($s): ?>
<div class="card" style="margin-bottom:1.4rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
        <div>
            <h3 class="m-0"><?php echo s($s['codigo'] ?: 'Subcontrato #' . $s['id']); ?> · <?php echo s($s['proveedor']); ?></h3>
            <p class="text-muted text-sm m-0"><span class="mono"><?php echo s($s['partida_codigo']); ?></span> <?php echo s($s['partida']); ?> · <?php echo s(Subcontratos::ESTADOS[$s['estado']] ?? $s['estado']); ?></p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap">
            <?php if ($puedeEditar): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url(['editar' => $s['id']])); ?>"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="/subcontratos/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$s['id']; ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a>
            <a class="btn btn-sm btn-ghost" href="<?php echo s($url()); ?>">Cerrar</a>
        </div>
    </div>
    <div class="rg-detalle-grid" style="margin-top:1rem">
        <div><small>Monto</small><b class="mono"><?php echo $m($s['monto']); ?></b></div>
        <div><small>Pagado (estimaciones)</small><b class="mono"><?php echo $m($s['pagado']); ?></b></div>
        <div><small>Saldo</small><b class="mono"><?php echo $m($s['saldo']); ?></b></div>
        <div><small>Retención</small><b><?php echo number_format((float)$s['retencion_pct'], 2); ?> %</b></div>
        <div><small>Anticipo</small><b><?php echo number_format((float)$s['anticipo_pct'], 2); ?> %</b></div>
    </div>
    <?php if (trim((string)$s['alcance']) !== ''): ?><p><?php echo nl2br(s($s['alcance'])); ?></p><?php endif; ?>

    <h4><i class="fa-solid fa-folder-open"></i> Documentos obligatorios</h4>
    <?php if (!$s['docs']['ok']): ?>
        <div class="alerta alerta-aviso" role="alert"><strong>Pago bloqueado.</strong>
            <?php if ($s['docs']['faltan']): ?>Falta: <?php echo s(implode(', ', $s['docs']['faltan'])); ?>.<?php endif; ?>
            <?php if ($s['docs']['vencidos']): ?> Vencido: <?php echo s(implode(', ', $s['docs']['vencidos'])); ?>.<?php endif; ?>
            Solo el administrador puede autorizar una estimación con motivo firmado.
        </div>
    <?php else: ?>
        <div class="alerta alerta-exito">Documentos obligatorios vigentes: se puede pagar.</div>
    <?php endif; ?>
    <div class="sc-docs">
        <?php foreach (Subcontratos::DOCS_OBLIGATORIOS as $tipo): $d = $s['docs']['filas'][$tipo] ?? null; $vigente = $d && (int)$d['validado'] && !empty($d['archivo_id']) && (empty($d['vencimiento']) || $d['vencimiento'] >= $hoy); $porVencer = $d && $vigente && !empty($d['vencimiento']) && $d['vencimiento'] <= date('Y-m-d', strtotime('+30 days')); ?>
        <div class="sc-doc <?php echo $vigente ? ($porVencer ? 'sc-doc-aviso' : 'sc-doc-ok') : 'sc-doc-falta'; ?>">
            <div class="sc-doc-cab"><strong><?php echo s(Subcontratos::DOCS[$tipo]); ?></strong>
                <?php if ($vigente): ?><span class="badge badge-cliente"><?php echo $porVencer ? 'Por vencer' : 'Vigente'; ?></span><?php else: ?><span class="badge badge-pdf">Falta / vencido</span><?php endif; ?>
            </div>
            <?php if ($d): ?><small class="text-muted"><?php echo s($d['archivo'] ?: 'Sin archivo'); ?><?php echo $d['vencimiento'] ? ' · vence ' . s(formatear_fecha($d['vencimiento'], 'd/m/Y')) : ''; ?></small><?php endif; ?>
            <?php if ($puedeEditar): ?>
            <form method="POST" action="/subcontratos/documento" class="sc-doc-form">
                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>"><input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                <select name="archivo_id" aria-label="Archivo"><option value="0">— Archivo —</option><?php foreach ($archivos as $ar): ?><option value="<?php echo (int)$ar->id; ?>"<?php echo (int)($d['archivo_id'] ?? 0) === (int)$ar->id ? ' selected' : ''; ?>><?php echo s($ar->nombre); ?></option><?php endforeach; ?></select>
                <input type="date" name="vencimiento" value="<?php echo s($d['vencimiento'] ?? ''); ?>" aria-label="Vencimiento">
                <label class="co-check"><input type="checkbox" name="validado" value="1"<?php echo (int)($d['validado'] ?? 0) ? ' checked' : ''; ?>> Validado</label>
                <button class="btn btn-xs btn-secondary" type="submit">Guardar</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <h4 style="margin-top:1.6rem"><i class="fa-solid fa-file-invoice-dollar"></i> Estimaciones del subcontratista</h4>
    <?php if (!$s['estimaciones']): ?><p class="text-muted">Aún no hay estimaciones.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Número</th><th>Fecha</th><th class="num">Subtotal</th><th class="num">IVA</th><th class="num">Retención</th><th class="num">Amortización</th><th class="num">Neto</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($s['estimaciones'] as $ev): ?>
            <tr><td class="mono"><b><?php echo s($ev['numero']); ?></b><?php if ($ev['motivo_autorizacion'] !== ''): ?><br><small class="text-muted" title="<?php echo s($ev['motivo_autorizacion']); ?>">Autorizada por <?php echo s($ev['autorizado_por']); ?></small><?php endif; ?></td>
                <td class="mono"><?php echo s(formatear_fecha($ev['fecha'], 'd/m/Y')); ?></td>
                <td class="num mono"><?php echo $m($ev['subtotal']); ?></td>
                <td class="num mono"><?php echo $m($ev['iva']); ?></td>
                <td class="num mono"><?php echo $m($ev['retencion']); ?></td>
                <td class="num mono"><?php echo $m($ev['amortizacion']); ?></td>
                <td class="num mono"><b><?php echo $m($ev['neto']); ?></b></td>
                <td><span class="cb-tag"><?php echo s(Subcontratos::ESTADOS_ESTIMACION[$ev['estado']] ?? $ev['estado']); ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>

    <?php if ($puedeEditar): ?>
    <details style="margin-top:1rem" <?php echo (!$s['estimaciones'] || !$s['docs']['ok']) ? 'open' : ''; ?>>
        <summary><strong>Registrar estimación</strong> <small class="text-muted">· IVA, retención, anticipo y compuerta de documentos</small></summary>
        <form method="POST" action="/subcontratos/estimacion" class="co-form" style="margin-top:1rem">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="subcontrato_id" value="<?php echo (int)$s['id']; ?>">
            <div class="co-campos">
                <label>Número *<input type="text" name="numero" maxlength="40" required value="SUB-EST-<?php echo str_pad((string)(count($s['estimaciones']) + 1), 3, '0', STR_PAD_LEFT); ?>"></label>
                <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>"></label>
                <label>Periodo desde<input type="date" name="periodo_desde" value="<?php echo date('Y-m-01'); ?>"></label>
                <label>Periodo hasta<input type="date" name="periodo_hasta" value="<?php echo date('Y-m-t'); ?>"></label>
                <label>Subtotal *<input type="text" inputmode="decimal" name="subtotal" required placeholder="0.00"></label>
                <label>IVA (%)<input type="number" name="iva_pct" min="0" max="100" step="0.01" value="16"></label>
                <label>Retención (%)<input type="number" name="retencion_pct" min="0" max="100" step="0.01" value="<?php echo s($s['retencion_pct']); ?>"></label>
                <label>Amortización anticipo (%)<input type="number" name="anticipo_pct" min="0" max="100" step="0.01" value="<?php echo s($s['anticipo_pct']); ?>"></label>
                <label>Ajuste (+/−)<input type="text" inputmode="decimal" name="ajuste" value="0"></label>
                <label>Estado<select name="estado"><option value="borrador">Borrador</option><option value="aprobada">Aprobada</option><option value="pagada">Pagada</option></select></label>
                <label class="co-ancho">Motivo de autorización (solo admin, si faltan documentos)<input type="text" name="motivo_autorizacion" maxlength="500" placeholder="Ej.: el alta de personal está en trámite y el trabajo no puede esperar"></label>
                <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"></textarea></label>
            </div>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar estimación</button>
            <p class="text-muted text-sm">Una estimación aprobada o pagada crea el costo real por el neto y descuenta el compromiso. Si faltan documentos, solo el administrador puede autorizarla con motivo.</p>
        </form>
    </details>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
    <h3><i class="fa-solid fa-list-check"></i> Lista de subcontratos</h3>
    <?php if (!$lista): ?><p class="text-muted">Todavía no hay subcontratos. Crea el primero con <strong>+ Nuevo subcontrato</strong>.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Subcontrato</th><th>Partida</th><th class="num">Monto</th><th class="num">Pagado</th><th class="num">Saldo</th><th>Documentos</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $x): ?>
            <tr>
                <td><strong class="mono"><?php echo s($x['codigo'] ?: '#' . $x['id']); ?></strong> · <?php echo s($x['proveedor']); ?><br><small class="text-muted"><?php echo (int)$x['estimaciones']; ?> estimación(es)</small></td>
                <td><span class="mono"><?php echo s($x['partida_codigo']); ?></span> <?php echo s($x['partida']); ?></td>
                <td class="num mono"><?php echo $m($x['monto']); ?></td>
                <td class="num mono"><?php echo $m($x['pagado']); ?></td>
                <td class="num mono"><?php echo $m($x['saldo']); ?></td>
                <td><?php if ($x['docs']['ok']): ?><span class="badge badge-cliente"><i class="fa-solid fa-circle-check"></i> Completo</span><?php else: ?><span class="badge badge-pdf" title="<?php echo s(implode(', ', array_merge($x['docs']['faltan'], $x['docs']['vencidos']))); ?>"><i class="fa-solid fa-triangle-exclamation"></i> Falta</span><?php endif; ?><?php if ($x['docs']['por_vencer']): ?><br><small class="text-muted"><?php echo count($x['docs']['por_vencer']); ?> por vencer</small><?php endif; ?></td>
                <td><span class="cb-tag"><?php echo s(Subcontratos::ESTADOS[$x['estado']] ?? $x['estado']); ?></span></td>
                <td class="acciones" style="white-space:nowrap"><a class="btn btn-xs btn-ghost" href="<?php echo s($url(['ver' => $x['id']])); ?>" title="Ver" aria-label="Ver"><i class="fa-solid fa-eye"></i></a><?php if ($puedeEditar): ?><a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => $x['id']])); ?>" title="Editar" aria-label="Editar"><i class="fa-solid fa-pen"></i></a><?php endif; ?><a class="btn btn-xs btn-ghost" href="/subcontratos/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$x['id']; ?>" title="PDF" aria-label="PDF"><i class="fa-solid fa-file-pdf"></i></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php endif; ?>
