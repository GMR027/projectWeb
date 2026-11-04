<?php
/** Costos y presupuesto. Variables: $proyecto, $pestana, $disponible, $resumen, $curva, $movimientos, $cambios, $ordenes, $editar, $error, $ok */
use Classes\Costos;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/costos?' . http_build_query(['proyecto' => $pid] + $q);
$tabs = ['resumen' => ['fa-gauge-high', 'Resumen y valor ganado'], 'real' => ['fa-receipt', 'Costo real'], 'estimaciones' => ['fa-file-invoice-dollar', 'Estimaciones'], 'compromisos' => ['fa-file-signature', 'Compromisos'], 'flujo' => ['fa-money-bill-trend-up', 'Flujo de caja'], 'cambios' => ['fa-file-circle-exclamation', 'Órdenes de cambio']];
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
        <div class="cb-kpi"><small title="Órdenes de compra, subcontratos y servicios vigentes que aún no se facturan">Comprometido</small><strong><?php echo $m($t['comprometido']); ?></strong><span>Prometido, aún sin factura</span></div>
        <div class="cb-kpi <?php echo $t['disponible'] < 0 ? 'cb-kpi-alerta' : ''; ?>"><small title="Presupuesto vigente − costo real − comprometido">Disponible</small><strong class="<?php echo $t['disponible'] < 0 ? 'text-danger' : ''; ?>"><?php echo $m($t['disponible']); ?></strong><span><?php echo $t['disponible'] < 0 ? 'La obra ya debe más de lo presupuestado' : 'Lo que queda por gastar o comprometer'; ?></span></div>
        <div class="cb-kpi"><small title="Valor ganado ÷ costo real. Menor a 1 = se gasta más de lo avanzado">CPI · costo</small><strong class="<?php echo $clsIdx($t['cpi']); ?>"><?php echo $ind($t['cpi']); ?></strong><span>SPI · tiempo: <b class="<?php echo $clsIdx($t['spi']); ?>"><?php echo $ind($t['spi']); ?></b></span></div>
        <div class="cb-kpi"><small title="Presupuesto ÷ CPI">Costo final estimado (EAC)</small><strong><?php echo $t['eac'] !== null ? $m($t['eac']) : '—'; ?></strong><span class="<?php echo $clsVar($t['vac']); ?>"><?php echo $t['vac'] !== null ? 'Variación al final: ' . $m($t['vac']) : 'Se calcula con el primer costo real'; ?></span></div>
        <div class="cb-kpi"><small>Estimado al cliente</small><strong><?php echo $m($t['estimado']); ?></strong><span>Neto a cobrar: <?php echo $m($t['estimado_neto']); ?></span></div>
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
            <thead><tr><th>Partida</th><th title="Se calcula con las cantidades cuando la partida está completa; si no, se captura a mano">Presupuesto</th><th class="hide-m">Cambios</th><th class="hide-m">Vigente</th><th class="hide-m">Prog. / real</th><th>PV</th><th>EV</th><th>AC</th><th>Comprometido</th><th>Disponible</th><th class="hide-m">CPI</th><th class="hide-m">Estimado</th></tr></thead>
            <tbody>
            <?php foreach ($resumen['partidas'] as $p): ?>
                <tr>
                    <td><span class="co-punto" style="background:<?php echo s($p['color'] ?: '#64748b'); ?>"></span> <span class="mono"><?php echo s($p['codigo']); ?></span> <?php echo s($p['nombre']); ?></td>
                    <td>
                        <?php if ($p['calculado']): ?>
                            <span class="mono"><?php echo $m($p['presupuesto']); ?></span>
                            <small class="text-muted" title="Suma de cantidad × precio unitario de las actividades de la partida"><i class="fa-solid fa-calculator"></i> <?php echo (int)$p['valuadas']; ?>/<?php echo (int)$p['actividades']; ?> conceptos</small>
                        <?php else: ?>
                            <input type="text" inputmode="decimal" class="co-in" name="presupuesto[<?php echo (int)$p['id']; ?>]" value="<?php echo $p['presupuesto'] ? s(number_format($p['presupuesto'], 2, '.', '')) : ''; ?>" placeholder="0.00" aria-label="Presupuesto de <?php echo s($p['nombre']); ?>">
                            <?php if ((int)$p['faltantes'] > 0): ?>
                                <small class="co-aviso" title="Mientras falten actividades por valuar se conserva el presupuesto manual"><i class="fa-solid fa-triangle-exclamation"></i> faltan <?php echo (int)$p['faltantes']; ?> por valuar<?php if ($p['suma_parcial'] > 0): ?> · parcial <?php echo $m($p['suma_parcial']); ?><?php endif; ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="mono hide-m <?php echo $clsVar($p['cambios']); ?>"><?php echo $p['cambios'] ? $m($p['cambios']) : '—'; ?></td>
                    <td class="mono hide-m"><?php echo $m($p['bac']); ?></td>
                    <td class="mono hide-m"><?php echo number_format($p['plan_pct'], 0); ?>% / <?php echo number_format($p['real_pct'], 0); ?>%</td>
                    <td class="mono"><?php echo $m($p['pv']); ?></td>
                    <td class="mono"><?php echo $m($p['ev']); ?></td>
                    <td class="mono"><?php echo $m($p['ac']); ?></td>
                    <td class="mono <?php echo $p['comprometido'] > 0 ? 'co-aviso' : ''; ?>"><?php echo $m($p['comprometido']); ?></td>
                    <td class="mono <?php echo $p['disponible'] < 0 ? 'text-danger' : ''; ?>"><?php echo $m($p['disponible']); ?></td>
                    <td class="mono hide-m <?php echo $clsIdx($p['cpi']); ?>"><?php echo $ind($p['cpi']); ?></td>
                    <td class="mono hide-m"><?php echo $m($p['estimado']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th>Total</th><th class="mono"><?php echo $m($t['presupuesto']); ?></th><th class="mono hide-m"><?php echo $m($t['cambios']); ?></th><th class="mono hide-m"><?php echo $m($t['bac']); ?></th><th class="hide-m"></th><th class="mono"><?php echo $m($t['pv']); ?></th><th class="mono"><?php echo $m($t['ev']); ?></th><th class="mono"><?php echo $m($t['ac']); ?></th><th class="mono"><?php echo $m($t['comprometido']); ?></th><th class="mono <?php echo $t['disponible'] < 0 ? 'text-danger' : ''; ?>"><?php echo $m($t['disponible']); ?></th><th class="mono hide-m <?php echo $clsIdx($t['cpi']); ?>"><?php echo $ind($t['cpi']); ?></th><th class="mono hide-m"><?php echo $m($t['estimado']); ?></th></tr></tfoot>
        </table></div>
        <?php $sp = $resumen['sinPartida']; if ($sp['cambios'] || $sp['real'] || $sp['estimado']): ?>
            <p class="text-muted text-sm">Sin partida (cuenta en el total): cambios <?php echo $m($sp['cambios']); ?> · costo real <?php echo $m($sp['real']); ?> · estimado <?php echo $m($sp['estimado']); ?>.</p>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar presupuesto</button>
        <button type="submit" formaction="/costos/recalcular" class="btn btn-ghost" formnovalidate title="Vuelve a sumar las cantidades de todas las partidas (útil para datos anteriores a esta función)"><i class="fa-solid fa-calculator"></i> Recalcular desde cantidades</button>
        <?php endif; ?>
    </form>
    <p class="text-muted text-sm">Cuando todas las actividades con duración de una partida tienen cantidad y precio unitario, el presupuesto se calcula solo y el campo manual se desactiva. Si falta alguna, se conserva el presupuesto manual y se muestra cuántas faltan. El <strong>importe</strong> se captura en la actividad (<em>Editar actividad → Concepto de obra</em>).</p>
    <p class="text-muted text-sm">PV = presupuesto vigente × avance programado a hoy · EV = presupuesto vigente × avance real (ambos pesados por la duración de las actividades, como la Curva S) · AC = costo real capturado · CPI = EV ÷ AC · SPI = EV ÷ PV.</p>

<?php elseif ($pestana === 'flujo'): ?>
    <?php include __DIR__ . '/_flujo.php'; ?>

<?php elseif ($pestana === 'real'): $tipo = 'real'; $e = $editar; include __DIR__ . '/_movimientos.php'; ?>

<?php elseif ($pestana === 'estimaciones'): ?>
    <?php include __DIR__ . '/_estimaciones.php'; ?>
    <details style="margin-top:1.5rem">
        <summary><strong>Captura rápida</strong> <small class="text-muted">· estimaciones simples sin deducciones, para registros antiguos o cobros sueltos</small></summary>
        <?php $tipo = 'estimacion'; $e = $editar; include __DIR__ . '/_movimientos.php'; ?>
    </details>

<?php elseif ($pestana === 'compromisos'): $ce = $compromisoEditar; $ap = $aplicar; ?>
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.8rem">
            <div>
                <h3 class="m-0"><i class="fa-solid fa-file-signature"></i> Dinero comprometido</h3>
                <p class="text-muted text-sm m-0">Órdenes de compra, subcontratos y servicios que ya prometiste pagar. Reservan presupuesto desde que se emiten; al aplicar la factura pasan a costo real sin contarse dos veces.</p>
            </div>
            <?php if (!$ce): ?><a class="btn btn-primary" href="<?php echo s($url(['pestana' => 'compromisos', 'nuevo_compromiso' => 1])); ?>">+ Nuevo compromiso</a><?php endif; ?>
        </div>
        <?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

        <?php if ($ce || isset($_GET['nuevo_compromiso'])): ?>
        <form method="POST" action="/costos/compromiso" class="co-form" style="margin-top:1rem">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <input type="hidden" name="id" value="<?php echo $ce ? (int)$ce['id'] : 0; ?>">
            <h4><?php echo $ce ? 'Editar compromiso' : 'Nuevo compromiso'; ?></h4>
            <div class="co-campos">
                <label>Tipo<select name="tipo"><?php foreach (\Classes\Compromisos::TIPOS as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo ($ce['tipo'] ?? 'orden_compra') === $k ? ' selected' : ''; ?>><?php echo s($etq); ?></option><?php endforeach; ?></select></label>
                <label>Estado<select name="estado"><?php foreach (\Classes\Compromisos::ESTADOS as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo ($ce['estado'] ?? 'vigente') === $k ? ' selected' : ''; ?>><?php echo s($etq); ?></option><?php endforeach; ?></select></label>
                <label>Proveedor o contratista *<input type="text" name="proveedor" maxlength="200" required value="<?php echo s($ce['proveedor'] ?? ''); ?>" placeholder="Ej.: Aceros del Norte"></label>
                <label>Número / referencia<input type="text" name="numero" maxlength="80" value="<?php echo s($ce['numero'] ?? ''); ?>" placeholder="OC-2026-014"></label>
                <label class="co-ancho">Concepto<input type="text" name="concepto" maxlength="255" value="<?php echo s($ce['concepto'] ?? ''); ?>" placeholder="Ej.: Suministro y montaje de estructura metálica"></label>
                <label>Monto *<input type="text" inputmode="decimal" name="monto" required value="<?php echo $ce ? s(number_format((float)$ce['monto'], 2, '.', '')) : ''; ?>" placeholder="0.00"></label>
                <label>Fecha de emisión<input type="date" name="fecha" value="<?php echo s($ce['fecha'] ?? date('Y-m-d')); ?>"></label>
                <label>Entrega / vigencia<input type="date" name="entrega" value="<?php echo s($ce['entrega'] ?? ''); ?>"></label>
                <label>Pago programado<input type="date" name="fecha_pago_programada" value="<?php echo s($ce['fecha_pago_programada'] ?? ''); ?>"></label>
                <label>Partida *<select name="partida_id" required>
                    <option value="">— Elige —</option>
                    <?php foreach ($resumen['partidas'] as $p): ?><option value="<?php echo (int)$p['id']; ?>"<?php echo (int)($ce['partida_id'] ?? 0) === (int)$p['id'] ? ' selected' : ''; ?>><?php echo s($p['codigo'] . ' ' . $p['nombre']); ?> · disp. <?php echo $m($p['disponible']); ?></option><?php endforeach; ?>
                </select></label>
                <label>Actividad (opcional)<select name="actividad_id"><option value="0">— Toda la partida —</option>
                    <?php $porPartida = []; foreach ($actividades as $a) $porPartida[$a['partida_codigo'] . ' · ' . $a['partida']][] = $a; ?>
                    <?php foreach ($porPartida as $nom => $acts): ?><optgroup label="<?php echo s($nom); ?>"><?php foreach ($acts as $a): ?><option value="<?php echo (int)$a['id']; ?>"<?php echo (int)($ce['actividad_id'] ?? 0) === (int)$a['id'] ? ' selected' : ''; ?>><?php echo s($a['codigo'] . ' · ' . $a['nombre']); ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
                </select></label>
                <label>Documento del Archivo<select name="archivo_id"><option value="0">— Sin documento —</option><?php foreach ($archivos as $ar): ?><option value="<?php echo (int)$ar->id; ?>"<?php echo (int)($ce['archivo_id'] ?? 0) === (int)$ar->id ? ' selected' : ''; ?>><?php echo s($ar->nombre); ?></option><?php endforeach; ?></select></label>
                <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"><?php echo s($ce['notas'] ?? ''); ?></textarea></label>
                <label class="co-ancho">Motivo del sobregiro (obligatorio si el monto supera el disponible)
                    <input type="text" name="motivo_sobregiro" maxlength="500" value="<?php echo s($ce['motivo_sobregiro'] ?? ''); ?>" placeholder="Ej.: el precio del acero subió y la orden de cambio va en trámite">
                </label>
            </div>
            <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar compromiso</button>
                <a class="btn btn-ghost" href="<?php echo s($url(['pestana' => 'compromisos'])); ?>">Cancelar</a>
                <span class="text-muted text-sm">El borrador no reserva dinero; al pasar a <em>vigente</em> sí.</span>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($ap): ?>
        <form method="POST" action="/costos/compromiso/aplicar" class="co-form" style="margin-top:1rem;border-left:3px solid var(--primary);padding-left:1rem">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <input type="hidden" name="id" value="<?php echo (int)$ap['id']; ?>">
            <h4>Aplicar factura al compromiso <?php echo s($ap['numero'] ?: '#' . $ap['id']); ?></h4>
            <p class="text-sm text-muted"><?php echo s($ap['proveedor']); ?> · saldo comprometido <b class="mono"><?php echo $m($ap['saldo']); ?></b>. Al aplicar, se crea el costo real y el saldo comprometido baja.</p>
            <div class="co-campos">
                <label>Factura / referencia *<input type="text" name="factura" maxlength="100" required placeholder="F-8891"></label>
                <label>Monto *<input type="text" inputmode="decimal" name="monto" required value="<?php echo s(number_format((float)$ap['saldo'], 2, '.', '')); ?>"></label>
                <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>"></label>
            </div>
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-receipt"></i> Aplicar factura</button>
            <a class="btn btn-ghost" href="<?php echo s($url(['pestana' => 'compromisos'])); ?>">Cancelar</a>
        </form>
        <?php endif; ?>

        <?php if (!$compromisos): ?>
            <p class="text-muted" style="margin-top:1rem">Todavía no hay compromisos. Empieza con las órdenes de compra abiertas y los subcontratos firmados.</p>
        <?php else: ?>
        <div class="tabla-wrapper" style="margin-top:1rem"><table class="tabla">
            <thead><tr><th>Compromiso</th><th>Partida / actividad</th><th class="num">Monto</th><th class="num">Aplicado</th><th class="num">Saldo</th><th>Entrega</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($compromisos as $c): ?>
                <tr>
                    <td><strong class="mono"><?php echo s($c['numero'] ?: '#' . $c['id']); ?></strong> · <?php echo s(\Classes\Compromisos::TIPOS[$c['tipo']] ?? $c['tipo']); ?><br><small class="text-muted"><?php echo s($c['proveedor']); ?><?php echo trim((string)$c['concepto']) !== '' ? ' · ' . s($c['concepto']) : ''; ?></small><?php if ($c['motivo_sobregiro'] !== ''): ?><br><span class="badge badge-pdf" title="<?php echo s($c['motivo_sobregiro']); ?>"><i class="fa-solid fa-triangle-exclamation"></i> Sobregiro autorizado</span><?php endif; ?></td>
                    <td><span class="mono"><?php echo s($c['partida_codigo']); ?></span> <?php echo s($c['partida']); ?><?php if ($c['act_codigo']): ?><br><small class="text-muted"><?php echo s($c['act_codigo'] . ' · ' . $c['act_nombre']); ?></small><?php endif; ?></td>
                    <td class="num mono"><?php echo $m($c['monto']); ?></td>
                    <td class="num mono"><?php echo $m($c['aplicado']); ?></td>
                    <td class="num mono"><?php echo $m($c['saldo']); ?></td>
                    <td class="mono text-sm"><?php echo $c['entrega'] ? s(formatear_fecha($c['entrega'], 'd/m/Y')) : '—'; ?></td>
                    <td><span class="cb-tag"><?php echo s(\Classes\Compromisos::ESTADOS[$c['estado']] ?? $c['estado']); ?></span></td>
                    <td class="acciones" style="white-space:nowrap">
                        <?php if ($c['abierto'] && $c['saldo'] > 0): ?><a class="btn btn-xs btn-primary" href="<?php echo s($url(['pestana' => 'compromisos', 'aplicar' => $c['id']])); ?>" title="Aplicar factura"><i class="fa-solid fa-receipt"></i></a><?php endif; ?>
                        <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['pestana' => 'compromisos', 'editar_compromiso' => $c['id']])); ?>" title="Editar" aria-label="Editar"><i class="fa-solid fa-pen"></i></a>
                        <?php if (!in_array($c['estado'], ['cerrado', 'cancelado'], true)): ?>
                            <form class="inline" method="POST" action="/costos/compromiso/cerrar" data-confirm="Cerrar compromiso|Se liberará el saldo de <?php echo s($m($c['saldo'])); ?> y ya no reservará presupuesto.|Cerrar">
                                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                <button class="btn btn-xs btn-ghost" type="submit" title="Cerrar y liberar saldo" aria-label="Cerrar"><i class="fa-solid fa-lock"></i></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($c['aplicado'] <= 0): ?>
                            <form class="inline" method="POST" action="/costos/compromiso/eliminar" data-confirm="Eliminar compromiso|Se borrará el registro. Solo se puede si no tiene facturas aplicadas.|Eliminar">
                                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
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
