<?php $pid = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-file-circle-exclamation"></i> Órdenes de cambio</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · <?php echo (int)$resumen['total']; ?> orden(es)</p>
    </div>
    <div class="cb-acciones">
        <a href="/lineas-base?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-timeline"></i> Líneas base</a>
        <a href="/cambios/cruce?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-truck-ramp-box"></i> Materiales vs cronograma</a>
        <?php if (is_admin()): ?>
            <form method="POST" action="/informes-ia/generar" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                <input type="hidden" name="herramienta" value="resumen_diario">
                <input type="hidden" name="p[fecha]" value="<?php echo date('Y-m-d'); ?>">
                <input type="hidden" name="p[linea]" value="0">
                <label class="text-sm text-muted" for="re-tipo">Informe</label>
                <select id="re-tipo" name="p[tipo]"><?php foreach (\Classes\Herramientas\ResumenEjecutivoDiario::TIPOS as $k => $v): ?><option value="<?php echo s($k); ?>"<?php echo $k === 'detallado' ? ' selected' : ''; ?>><?php echo s($v); ?></option><?php endforeach; ?></select>
                <label class="text-sm text-muted" for="re-detalle">Detalle</label>
                <select id="re-detalle" name="p[detalle]"><?php foreach (\Classes\Herramientas\ResumenEjecutivoDiario::NIVELES as $k => $v): ?><option value="<?php echo s($k); ?>"<?php echo $k === 'medio' ? ' selected' : ''; ?>><?php echo s($v); ?></option><?php endforeach; ?></select>
                <button type="submit" class="btn btn-ghost" title="Genera el resumen del día con los datos del sistema; con IA se redacta desde la app de escritorio"><i class="fa-solid fa-newspaper"></i> Resumen ejecutivo de hoy</button>
            </form>
        <?php endif; ?>
        <?php if (puede_gestionar_proyecto($proyecto)): ?><a href="/cambios/crear?proyecto=<?php echo $pid; ?>" class="btn btn-primary">+ Nueva orden</a><?php endif; ?>
    </div>
</div>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_cambios.sql</code> para usar las órdenes de cambio y las líneas base.</p></div>
<?php else: ?>
<div class="cb-kpis">
    <div class="cb-kpi"><small>Costo autorizado</small><strong>$<?php echo number_format($resumen['costo_autorizado'], 2); ?></strong><span><?php echo (int)$resumen['dias_autorizados']; ?> día(s) en órdenes aprobadas</span></div>
    <div class="cb-kpi"><small>Por autorizar</small><strong>$<?php echo number_format($resumen['costo_por_autorizar'], 2); ?></strong><span><?php echo (int)$resumen['por_estado']['solicitada']; ?> solicitada(s) · <?php echo (int)$resumen['dias_por_autorizar']; ?> día(s)</span></div>
    <div class="cb-kpi <?php echo $resumen['retiros_pendientes'] ? 'cb-kpi-alerta' : ''; ?>"><small>Retiros pendientes</small><strong><?php echo (int)$resumen['retiros_pendientes']; ?></strong><span><?php echo (int)$resumen['retiros_vencidos']; ?> vencido(s)</span></div>
    <div class="cb-kpi"><small>Ejecutadas</small><strong><?php echo (int)$resumen['por_estado']['ejecutada'] + (int)$resumen['por_estado']['cerrada']; ?></strong><span><?php echo (int)$resumen['por_estado']['aprobada']; ?> aprobada(s) por ejecutar</span></div>
</div>

<?php if ($retiros): ?>
<div class="card cb-retiros">
    <h3><i class="fa-solid fa-person-digging"></i> Instalaciones temporales por retirar</h3>
    <table class="tabla">
        <thead><tr><th>Orden</th><th>Instalado</th><th class="hide-m">Ubicación</th><th>Días</th><th>Retiro previsto</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($retiros as $r): ?>
            <tr>
                <td class="mono"><a href="/cambios/ver?proyecto=<?php echo $pid; ?>&id=<?php echo s($r['orden_id']); ?>"><?php echo s($r['numero']); ?></a></td>
                <td><?php echo s($r['descripcion']); ?></td>
                <td class="hide-m"><?php echo s($r['ubicacion_original'] ?: $r['ubicacion_nueva']); ?></td>
                <td class="mono"><?php echo (int)$r['dias_instalado']; ?></td>
                <td class="mono"><?php echo $r['sin_programar'] ? '<span class="cb-tag cb-tag-alta">sin programar</span>' : formatear_fecha($r['fecha_retiro_prevista']) . ($r['vencido'] ? ' <span class="cb-tag cb-tag-critica">vencido</span>' : ''); ?></td>
                <td><?php if (puede_gestionar_proyecto($proyecto)): ?><button type="button" class="btn btn-sm btn-primary cb-estado" data-id="<?php echo s($r['id']); ?>" data-estado="retirado" data-actualizado="<?php echo s($r['actualizado']); ?>" data-etiqueta="Retiro: <?php echo s($r['descripcion']); ?>">Marcar retirado</button><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="cb-filtros">
    <a href="/cambios?proyecto=<?php echo $pid; ?>" class="cb-chip<?php echo $filtro === '' ? ' activo' : ''; ?>">Todas</a>
    <?php foreach (\Model\OrdenCambio::ESTADOS as $k => $etq): ?>
        <a href="/cambios?proyecto=<?php echo $pid; ?>&estado=<?php echo $k; ?>" class="cb-chip<?php echo $filtro === $k ? ' activo' : ''; ?>"><?php echo s($etq); ?> <b><?php echo (int)$resumen['por_estado'][$k]; ?></b></a>
    <?php endforeach; ?>
</div>
<div class="card">
    <?php if (empty($ordenes)): ?>
        <p class="text-muted">No hay órdenes de cambio<?php echo $filtro ? ' con ese estado' : ''; ?>. Crea una para registrar instalaciones, retiros, reubicaciones, demoliciones o adiciones.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Número</th><th>Título</th><th class="hide-m">Fecha</th><th>Estado</th><th class="hide-m">Renglones</th><th class="hide-m">Impacto</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($ordenes as $o): $c = $conteos[(int)$o->id] ?? null; ?>
                <tr>
                    <td class="mono"><a href="/cambios/ver?proyecto=<?php echo $pid; ?>&id=<?php echo s($o->id); ?>"><?php echo s($o->numero ?: '#' . $o->id); ?></a></td>
                    <td><?php echo s($o->titulo); ?></td>
                    <td class="mono hide-m"><?php echo formatear_fecha($o->fecha); ?></td>
                    <td><span class="cb-estado-oc cb-oc-<?php echo s($o->estado); ?>"><?php echo s(\Model\OrdenCambio::ESTADOS[$o->estado] ?? $o->estado); ?></span></td>
                    <td class="mono hide-m"><?php echo $c ? (int)$c['hechas'] . '/' . (int)$c['n'] : '—'; ?></td>
                    <td class="mono hide-m"><?php echo (float)$o->impacto_costo ? '$' . number_format((float)$o->impacto_costo, 2) : ''; ?><?php echo (int)$o->impacto_dias ? ' · ' . (int)$o->impacto_dias . ' d' : ''; ?></td>
                    <td class="acciones">
                        <a href="/cambios/ver?proyecto=<?php echo $pid; ?>&id=<?php echo s($o->id); ?>" class="btn btn-sm btn-ghost">Ver</a>
                        <?php if (puede_gestionar_proyecto($proyecto)): ?>
                        <a href="/cambios/editar?proyecto=<?php echo $pid; ?>&id=<?php echo s($o->id); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/cambios/eliminar" style="display:inline" data-confirm="¿Eliminar la orden de cambio?|Se borrarán también sus renglones.">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo s($o->id); ?>">
                            <button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/_estado-js.php'; ?>
