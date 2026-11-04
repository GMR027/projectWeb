<?php
/** Almacén. Variables: $proyecto, $vista, $disponible, $almacen, $stock, $resumen, $ordenes, $ordenEditar, $recibir, $recepciones, $salidas, $movimientos, $materiales, $actividades, $partidas, $puedeEditar, $ok, $error */
use Classes\Almacen;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/almacen?' . http_build_query(['proyecto' => $pid] + $q);
$m = fn($v) => '$' . number_format((float)$v, 2);
$n = fn($v, $d = 3) => number_format((float)$v, $d);
$stockPorMaterial = [];
foreach ($stock as $s) $stockPorMaterial[(int)$s['id']] = $s;
$lineasOrden = $ordenEditar['lineas'] ?? [];
while (count($lineasOrden) < 6) $lineasOrden[] = null;
$tabs = ['existencias' => ['fa-warehouse', 'Existencias'], 'ordenes' => ['fa-file-signature', 'Órdenes de compra'], 'movimientos' => ['fa-arrow-right-arrow-left', 'Movimientos']];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-warehouse"></i> Almacén</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · existencias, órdenes de compra, recepciones y salidas a actividad</p>
    </div>
    <?php if ($disponible && $puedeEditar): ?>
    <div class="la-controles">
        <a class="btn btn-primary" href="<?php echo s($url(['vista' => 'ordenes', 'nueva' => 1])); ?>">+ Orden de compra</a>
        <a class="btn btn-ghost" href="<?php echo s($url(['vista' => 'existencias', 'salida' => 1])); ?>"><i class="fa-solid fa-dolly"></i> Salida</a>
        <a class="btn btn-ghost" href="<?php echo s($url(['vista' => 'existencias', 'ajuste' => 1])); ?>"><i class="fa-solid fa-sliders"></i> Ajuste</a>
    </div>
    <?php endif; ?>
</div>

<?php if (es_demo()): ?>
<div class="alerta alerta-info">
    <strong>Demo · Almacén.</strong> Aquí se sigue el material desde la orden de compra hasta la actividad: recepciones parciales, existencias, salidas y ajustes. Las existencias se calculan con los movimientos; no se escriben a mano.
    <a href="/manual#almacen">Ver en el manual</a>.
</div>
<?php endif; ?>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_almacen.sql</code> para usar el almacén.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<div class="al-kpis">
    <div class="al-kpi"><small>Materiales</small><strong><?php echo (int)$resumen['materiales']; ?></strong><span>En el catálogo</span></div>
    <div class="al-kpi <?php echo $resumen['bajos'] ? 'al-kpi-alerta' : ''; ?>"><small>Bajo mínimo</small><strong><?php echo (int)$resumen['bajos']; ?></strong><span>Requieren reposición</span></div>
    <div class="al-kpi"><small>Órdenes abiertas</small><strong><?php echo (int)$resumen['abiertas']; ?></strong><span>Por recibir o parciales</span></div>
    <div class="al-kpi"><small>Valor del inventario</small><strong class="mono"><?php echo $m($resumen['valor']); ?></strong><span><?php echo number_format((float)$resumen['movimientos']); ?> movimiento(s)</span></div>
</div>

<nav class="centro-ia-tabs" aria-label="Almacén">
    <?php foreach ($tabs as $k => [$icono, $nombre]): ?>
        <a href="<?php echo s($url(['vista' => $k])); ?>" class="centro-ia-tabs__tab<?php echo $k === $vista ? ' activa' : ''; ?>"<?php echo $k === $vista ? ' aria-current="page"' : ''; ?>><i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s($nombre); ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($vista === 'existencias'): ?>
    <?php if ($puedeEditar && isset($_GET['salida'])): ?>
    <form method="POST" action="/almacen/salida" class="card co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <h3><i class="fa-solid fa-dolly"></i> Salida a actividad</h3>
        <p class="text-muted text-sm">El material sale del almacén y queda cargado a la actividad. No se permite sacar más de lo que hay.</p>
        <div class="co-campos">
            <label>Actividad *<select name="actividad_id" required><option value="">— Elige —</option>
                <?php foreach ($actividades as $a): ?><option value="<?php echo (int)$a['id']; ?>"><?php echo s($a['codigo'] . ' · ' . $a['nombre']); ?></option><?php endforeach; ?>
            </select></label>
            <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>"></label>
            <label>Recibió<input type="text" name="recibio" maxlength="150" value="<?php echo s($_SESSION['nombre'] ?? ''); ?>"></label>
            <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"></textarea></label>
        </div>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Material</th><th class="num">Existencia</th><th class="num">Cantidad a sacar</th></tr></thead>
            <tbody>
            <?php for ($i = 0; $i < 6; $i++): ?>
                <tr><td><select name="linea_material[]"><option value="">— Sin material —</option>
                    <?php foreach ($materiales as $mat): $st = $stockPorMaterial[(int)$mat['id']]['existencia'] ?? 0; ?><option value="<?php echo (int)$mat['id']; ?>"><?php echo s($mat['nombre']); ?> — disp. <?php echo $n($st) . ' ' . s($mat['unidad']); ?></option><?php endforeach; ?>
                </select></td><td class="num mono">—</td><td class="num"><input type="number" name="linea_cantidad[]" min="0" step="0.001" class="co-in" placeholder="0"></td></tr>
            <?php endfor; ?>
            </tbody>
        </table></div>
        <div style="margin-top:.8rem"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-dolly"></i> Registrar salida</button> <a class="btn btn-ghost" href="<?php echo s($url(['vista' => 'existencias'])); ?>">Cancelar</a></div>
    </form>
    <?php endif; ?>

    <?php if ($puedeEditar && isset($_GET['ajuste'])): ?>
    <form method="POST" action="/almacen/ajuste" class="card co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <h3><i class="fa-solid fa-sliders"></i> Ajuste de inventario</h3>
        <p class="text-muted text-sm">Para corregir una diferencia física. El motivo es obligatorio y queda en el historial.</p>
        <div class="co-campos">
            <label>Material *<select name="material_id" required><option value="">— Elige —</option><?php foreach ($materiales as $mat): ?><option value="<?php echo (int)$mat['id']; ?>"><?php echo s($mat['nombre'] . ' (' . $mat['unidad'] . ')'); ?></option><?php endforeach; ?></select></label>
            <label>Tipo<select name="tipo"><option value="entrada">Entrada</option><option value="salida">Salida</option></select></label>
            <label>Cantidad *<input type="number" name="cantidad" min="0" step="0.001" required></label>
            <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>"></label>
            <label class="co-ancho">Motivo *<input type="text" name="motivo" maxlength="300" required placeholder="Ej.: conteo físico, merma, devolución"></label>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Registrar ajuste</button>
        <a class="btn btn-ghost" href="<?php echo s($url(['vista' => 'existencias'])); ?>">Cancelar</a>
    </form>
    <?php endif; ?>

    <div class="card">
        <h3><i class="fa-solid fa-boxes-stacked"></i> Existencias · <?php echo s($almacen['nombre'] ?? 'Almacén'); ?></h3>
        <?php if (!$stock): ?><p class="text-muted">Aún no hay materiales en el catálogo.</p><?php else: ?>
        <div class="tabla-wrapper"><table class="tabla al-tabla">
            <thead><tr><th>Material</th><th>Unidad</th><th class="num">Existencia</th><th class="num">Mínimo</th><th>Estado</th><th class="num">Costo prom.</th><th class="num">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($stock as $s): ?>
                <tr class="<?php echo $s['bajo'] ? 'al-bajo' : ''; ?>">
                    <td><?php echo s($s['nombre']); ?></td>
                    <td><?php echo s($s['unidad']); ?></td>
                    <td class="num mono"><b><?php echo $n($s['existencia']); ?></b></td>
                    <td class="num mono"><?php echo (float)$s['stock_minimo'] > 0 ? $n($s['stock_minimo']) : '—'; ?></td>
                    <td><?php if ($s['bajo']): ?><span class="al-badge al-badge-bajo"><i class="fa-solid fa-triangle-exclamation"></i> Bajo mínimo</span><?php elseif ((float)$s['stock_minimo'] > 0): ?><span class="al-badge al-badge-ok">Suficiente</span><?php else: ?><span class="text-muted text-sm">Sin mínimo</span><?php endif; ?></td>
                    <td class="num mono"><?php echo $m($s['costo_promedio']); ?></td>
                    <td class="num mono"><?php echo $m($s['valor']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
        <p class="text-muted text-sm">El mínimo se configura en el material (<a href="/materiales?proyecto=<?php echo $pid; ?>">Catálogo de materiales</a>). La existencia es la suma de entradas menos salidas y ajustes.</p>
    </div>

<?php elseif ($vista === 'ordenes'): ?>
    <?php if ($recibir): ?>
    <form method="POST" action="/almacen/recibir" class="card co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="id" value="<?php echo (int)$recibir['id']; ?>">
        <h3>Recibir orden <?php echo s($recibir['numero'] ?: '#' . $recibir['id']); ?></h3>
        <p class="text-muted text-sm"><?php echo s($recibir['proveedor']); ?> · saldo comprometido <b class="mono"><?php echo $m($recibir['saldo']); ?></b>. Captura lo que llegó; puedes recibir en partes.</p>
        <div class="co-campos">
            <label>Fecha de recepción<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>"></label>
            <label>Factura (opcional)<input type="text" name="factura" maxlength="100" placeholder="F-8891"></label>
            <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"></textarea></label>
        </div>
        <?php if (!$recibir['lineas']): ?>
            <div class="alerta alerta-aviso">Esta orden se creó sin detalle de materiales. Edítala en <a href="<?php echo s($url(['vista' => 'ordenes', 'orden' => $recibir['id']])); ?>">Órdenes de compra</a> para agregar líneas.</div>
        <?php else: ?>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Material</th><th class="num">Pedido</th><th class="num">Recibido</th><th class="num">Pendiente</th><th class="num">Recibir ahora</th></tr></thead>
            <tbody>
            <?php foreach ($recibir['lineas'] as $l): ?>
                <tr><td><?php echo s($l['material']); ?> <small class="text-muted">(<?php echo s($l['unidad']); ?>)</small></td>
                    <td class="num mono"><?php echo $n($l['cantidad']); ?></td>
                    <td class="num mono"><?php echo $n($l['recibido']); ?></td>
                    <td class="num mono"><?php echo $n($l['pendiente']); ?></td>
                    <td class="num"><input type="number" name="recibir[<?php echo (int)$l['id']; ?>]" min="0" max="<?php echo $l['pendiente']; ?>" step="0.001" class="co-in" value="<?php echo $l['pendiente'] > 0 ? $l['pendiente'] : ''; ?>"></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
        <div style="margin-top:.8rem"><button class="btn btn-primary" type="submit" <?php echo $recibir['lineas'] ? '' : 'disabled'; ?>><i class="fa-solid fa-truck-ramp-box"></i> Registrar recepción</button> <a class="btn btn-ghost" href="<?php echo s($url(['vista' => 'ordenes'])); ?>">Cancelar</a></div>
        <p class="text-muted text-sm">Si escribes la factura, el monto recibido se aplica al compromiso y se registra como costo real. Si la dejas vacía, solo entra al almacén y la factura se aplica después desde Costos.</p>
    </form>
    <?php endif; ?>

    <?php if ($puedeEditar && ($ordenEditar || isset($_GET['nueva']))): $o = $ordenEditar; ?>
    <form method="POST" action="/almacen/orden" class="card co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="id" value="<?php echo $o ? (int)$o['id'] : 0; ?>">
        <h3><?php echo $o ? 'Editar orden de compra' : 'Nueva orden de compra'; ?></h3>
        <div class="co-campos">
            <label>Proveedor *<input type="text" name="proveedor" maxlength="200" required value="<?php echo s($o['proveedor'] ?? ''); ?>"></label>
            <label>Número<input type="text" name="numero" maxlength="80" value="<?php echo s($o['numero'] ?? ''); ?>" placeholder="OC-2026-014"></label>
            <label>Partida *<select name="partida_id" required><option value="">— Elige —</option><?php foreach ($partidas as $p): ?><option value="<?php echo (int)$p->id; ?>"<?php echo (int)($o['partida_id'] ?? 0) === (int)$p->id ? ' selected' : ''; ?>><?php echo s($p->codigo . ' ' . $p->nombre); ?></option><?php endforeach; ?></select></label>
            <label>Actividad (opcional)<select name="actividad_id"><option value="0">— Toda la partida —</option><?php foreach ($actividades as $a): ?><option value="<?php echo (int)$a['id']; ?>"<?php echo (int)($o['actividad_id'] ?? 0) === (int)$a['id'] ? ' selected' : ''; ?>><?php echo s($a['codigo'] . ' · ' . $a['nombre']); ?></option><?php endforeach; ?></select></label>
            <label>Fecha<input type="date" name="fecha" value="<?php echo s($o['fecha'] ?? date('Y-m-d')); ?>"></label>
            <label>Entrega / vigencia<input type="date" name="entrega" value="<?php echo s($o['entrega'] ?? ''); ?>"></label>
            <label>Estado<select name="estado"><option value="vigente"<?php echo ($o['estado'] ?? 'vigente') === 'vigente' ? ' selected' : ''; ?>>Vigente (reserva)</option><option value="borrador"<?php echo ($o['estado'] ?? '') === 'borrador' ? ' selected' : ''; ?>>Borrador</option></select></label>
            <label class="co-ancho">Concepto<input type="text" name="concepto" maxlength="255" value="<?php echo s($o['concepto'] ?? 'Orden de compra'); ?>"></label>
            <label class="co-ancho">Motivo del sobregiro (si el total supera el disponible)<input type="text" name="motivo_sobregiro" maxlength="500" value="<?php echo s($o['motivo_sobregiro'] ?? ''); ?>"></label>
            <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"><?php echo s($o['notas'] ?? ''); ?></textarea></label>
        </div>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Material</th><th class="num">Cantidad</th><th class="num">Precio unitario</th></tr></thead>
            <tbody>
            <?php foreach ($lineasOrden as $i => $l): ?>
                <tr><td><select name="linea_material[]"><option value="">— Sin material —</option>
                    <?php foreach ($materiales as $mat): ?><option value="<?php echo (int)$mat['id']; ?>" data-costo="<?php echo (float)$mat['costo_unitario']; ?>"<?php echo (int)($l['material_id'] ?? 0) === (int)$mat['id'] ? ' selected' : ''; ?>><?php echo s($mat['nombre'] . ' (' . $mat['unidad'] . ')'); ?></option><?php endforeach; ?>
                </select></td>
                <td class="num"><input type="number" name="linea_cantidad[]" min="0" step="0.001" class="co-in" value="<?php echo $l ? s($l['cantidad']) : ''; ?>" placeholder="0"></td>
                <td class="num"><input type="number" name="linea_precio[]" min="0" step="0.01" class="co-in" value="<?php echo $l ? s($l['precio_unitario']) : ''; ?>" placeholder="0.00"></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div style="margin-top:.8rem"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar orden</button> <a class="btn btn-ghost" href="<?php echo s($url(['vista' => 'ordenes'])); ?>">Cancelar</a></div>
        <p class="text-muted text-sm">La orden reserva presupuesto como compromiso. Si editas una orden con recepciones, primero debes cerrarla o ajustar el inventario.</p>
    </form>
    <?php endif; ?>

    <div class="card">
        <h3><i class="fa-solid fa-file-signature"></i> Órdenes de compra</h3>
        <?php if (!$ordenes): ?><p class="text-muted">Todavía no hay órdenes de compra con materiales.</p><?php else: ?>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Orden</th><th>Partida</th><th class="num">Total</th><th>Recibido</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ordenes as $o): ?>
                <tr>
                    <td><strong class="mono"><?php echo s($o['numero'] ?: '#' . $o['id']); ?></strong><br><small class="text-muted"><?php echo s($o['proveedor']); ?> · <?php echo (int)$o['lineas']; ?> línea(s)</small></td>
                    <td><span class="mono"><?php echo s($o['partida_codigo']); ?></span> <?php echo s($o['partida']); ?></td>
                    <td class="num mono"><?php echo $m($o['monto']); ?></td>
                    <td><div class="barra-progreso" style="min-width:10rem"><div class="barra-fill" style="width:<?php echo min(100, (float)$o['avance']); ?>%"></div></div><small class="text-muted"><?php echo number_format((float)$o['avance'], 0); ?>%</small></td>
                    <td><span class="al-badge"><?php echo s(\Classes\Compromisos::ESTADOS[$o['estado']] ?? $o['estado']); ?></span></td>
                    <td class="acciones" style="white-space:nowrap">
                        <?php if (in_array($o['estado'], ['vigente', 'parcial'], true) && (int)$o['lineas'] > 0): ?><a class="btn btn-xs btn-primary" href="<?php echo s($url(['vista' => 'ordenes', 'recibir' => $o['id']])); ?>" title="Recibir" aria-label="Recibir"><i class="fa-solid fa-truck-ramp-box"></i></a><?php endif; ?>
                        <?php if ($puedeEditar && (float)$o['recibido_unidades'] <= 0): ?><a class="btn btn-xs btn-ghost" href="<?php echo s($url(['vista' => 'ordenes', 'orden' => $o['id']])); ?>" title="Editar" aria-label="Editar"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
        <p class="text-muted text-sm">Las órdenes también aparecen en <a href="/costos?proyecto=<?php echo $pid; ?>&pestana=compromisos">Costos → Compromisos</a>, donde se aplican las facturas y se controla el sobregiro.</p>
    </div>

<?php else: ?>
    <div class="card">
        <h3><i class="fa-solid fa-arrow-right-arrow-left"></i> Movimientos recientes</h3>
        <?php if (!$movimientos): ?><p class="text-muted">Aún no hay entradas ni salidas.</p><?php else: ?>
        <div class="tabla-wrapper"><table class="tabla">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Material</th><th class="num">Cantidad</th><th class="num">Costo unit.</th><th class="num">Importe</th><th>Actividad / orden</th><th>Usuario</th></tr></thead>
            <tbody>
            <?php foreach ($movimientos as $mv): ?>
                <tr>
                    <td class="mono"><?php echo s(formatear_fecha($mv['fecha'], 'd/m/Y')); ?></td>
                    <td><span class="al-badge al-tipo-<?php echo s($mv['tipo']); ?>"><?php echo s(Almacen::TIPOS_MOV[$mv['tipo']] ?? $mv['tipo']); ?></span></td>
                    <td><?php echo s($mv['material']); ?><small class="text-muted"> (<?php echo s($mv['unidad']); ?>)</small></td>
                    <td class="num mono"><?php echo $n($mv['cantidad']); ?></td>
                    <td class="num mono"><?php echo $m($mv['costo_unitario']); ?></td>
                    <td class="num mono"><?php echo $m($mv['importe']); ?></td>
                    <td class="text-sm"><?php echo $mv['act_codigo'] ? s($mv['act_codigo'] . ' · ' . $mv['act_nombre']) : ($mv['compromiso_id'] ? 'Orden #' . (int)$mv['compromiso_id'] : '—'); ?><?php if ($mv['motivo'] !== ''): ?><br><small class="text-muted"><?php echo s($mv['motivo']); ?></small><?php endif; ?></td>
                    <td class="text-sm"><?php echo s($mv['usuario']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
