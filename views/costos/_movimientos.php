<?php
/** Captura rápida de costo real o estimación simple. Variables: $tipo, $e, $pestana, $resumen, $ordenes, $movimientos, $url, $m, $pid, $proyecto */
use Classes\Costos;
?>
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
    <?php if (!$movimientos): ?><p class="text-muted">Aún no hay <?php echo $tipo === 'real' ? 'costos capturados' : 'estimaciones simples'; ?>.</p><?php else: ?>
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
                    <form class="inline" method="POST" action="/costos/eliminar" data-confirm="¿Eliminar este movimiento?|Se quitará del costo del proyecto.">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$mv['id']; ?>"><input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Quitar" aria-label="Quitar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="<?php echo $tipo === 'estimacion' ? 6 : 5; ?>">Total<?php echo $tipo === 'estimacion' ? ' (sin borradores)' : ''; ?></th><th class="mono"><?php echo $m($suma); ?></th><th></th></tr></tfoot>
    </table></div>
    <?php endif; ?>
</div>
