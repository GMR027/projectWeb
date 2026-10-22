<?php
/** RFI. Variables: $proyecto, $disponible, $filtro, $lista, $resumen, $puedeEditar, $editar, $actividades, $revs, $puedeRev, $abrirForm, $numero, $error, $ok */
use Classes\Rfi;
use Classes\Revisiones;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/rfi?' . http_build_query(['proyecto' => $pid] + $q);
$e = $editar;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-circle-question"></i> RFI · Solicitudes de información</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · quién debe responder, para cuándo y qué actividad detiene</p>
    </div>
    <?php if ($puedeEditar && $disponible): ?><a href="<?php echo s($url(['nuevo' => 1])); ?>" class="btn btn-primary">+ Nuevo RFI</a><?php endif; ?>
</div>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_fase3.sql</code> para usar los RFI.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito">Guardado.</div><?php endif; ?>

<div class="cb-kpis">
    <div class="cb-kpi"><small>Abiertos</small><strong><?php echo (int)$resumen['abierto']; ?></strong><span><?php echo (int)$resumen['respondido']; ?> respondido(s) · <?php echo (int)$resumen['cerrado']; ?> cerrado(s)</span></div>
    <div class="cb-kpi <?php echo $resumen['vencidos'] ? 'cb-kpi-alerta' : ''; ?>"><small>Vencidos</small><strong><?php echo (int)$resumen['vencidos']; ?></strong><span>Abiertos con la fecha límite pasada</span></div>
    <div class="cb-kpi <?php echo $resumen['bloqueando'] ? 'cb-kpi-alerta' : ''; ?>"><small>Bloqueando actividades</small><strong><?php echo (int)$resumen['bloqueando']; ?></strong><span>Abiertos sobre actividades sin terminar</span></div>
</div>

<?php if ($puedeEditar && $abrirForm): ?>
<form method="POST" action="/rfi/guardar" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
    <h3><?php echo $e ? 'Editar ' . s($e['numero']) : 'Nuevo RFI'; ?></h3>
    <div class="co-campos">
        <?php if (!$e): ?><label>Número<input type="text" name="numero" maxlength="30" value="<?php echo s($numero); ?>"></label><?php endif; ?>
        <label class="co-ancho">Asunto<input type="text" name="asunto" maxlength="200" value="<?php echo s($e['asunto'] ?? ''); ?>" required></label>
        <label>Responsable de contestar<input type="text" name="responsable" maxlength="150" value="<?php echo s($e['responsable'] ?? ''); ?>" placeholder="Arquitecto, supervisor, cliente…"></label>
        <label>Fecha<input type="date" name="fecha" value="<?php echo s($e['fecha'] ?? date('Y-m-d')); ?>"></label>
        <label>Fecha límite<input type="date" name="fecha_limite" value="<?php echo s($e['fecha_limite'] ?? ''); ?>"></label>
        <label>Prioridad<select name="prioridad"><?php foreach (Rfi::PRIORIDADES as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo ($e['prioridad'] ?? 'media') === $k ? ' selected' : ''; ?>><?php echo $etq; ?></option><?php endforeach; ?></select></label>
        <label>Estado<select name="estado"><?php foreach (Rfi::ESTADOS as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo ($e['estado'] ?? 'abierto') === $k ? ' selected' : ''; ?>><?php echo $etq; ?></option><?php endforeach; ?></select></label>
        <label class="co-ancho">Actividad que bloquea<select name="actividad_id"><option value="0">— Ninguna —</option><?php echo opciones_actividades($actividades, $e['actividad_id'] ?? null); ?></select></label>
        <label class="co-ancho">Pregunta<textarea name="pregunta" rows="3" maxlength="5000"><?php echo s($e['pregunta'] ?? ''); ?></textarea></label>
        <label class="co-ancho">Respuesta<textarea name="respuesta" rows="3" maxlength="5000"><?php echo s($e['respuesta'] ?? ''); ?></textarea></label>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="<?php echo s($url()); ?>" class="btn btn-ghost">Cancelar</a>
</form>
<?php endif; ?>

<div class="cb-filtros">
    <a href="<?php echo s($url()); ?>" class="cb-chip<?php echo $filtro === '' ? ' activo' : ''; ?>">Todos <b><?php echo (int)$resumen['total']; ?></b></a>
    <?php foreach (Rfi::ESTADOS as $k => $etq): ?><a href="<?php echo s($url(['estado' => $k])); ?>" class="cb-chip<?php echo $filtro === $k ? ' activo' : ''; ?>"><?php echo $etq; ?> <b><?php echo (int)$resumen[$k]; ?></b></a><?php endforeach; ?>
    <a href="<?php echo s($url(['estado' => 'vencidos'])); ?>" class="cb-chip<?php echo $filtro === 'vencidos' ? ' activo' : ''; ?>">Vencidos <b><?php echo (int)$resumen['vencidos']; ?></b></a>
</div>
<div class="card">
    <?php if (!$lista): ?><p class="text-muted">No hay RFI<?php echo $filtro !== '' ? ' con ese filtro' : ' todavía'; ?>.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla" data-rev-tabla data-proyecto="<?php echo $pid; ?>" <?php echo pag_attr($proyecto); ?>>
        <thead><tr><?php if (is_admin()): ?><th title="Revisión del RFI">Rev.</th><?php endif; ?><th>RFI</th><th class="hide-m">Responsable</th><th>Fecha límite</th><th class="hide-m">Bloquea</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $r): ?>
            <tr>
                <?php if (is_admin()): ?><td><?php echo Revisiones::chip('rfi', (string)$r['id'], Revisiones::estado($revs[(string)$r['id']] ?? null, Revisiones::huellaRfi($r)), $puedeRev, $r['numero']); ?></td><?php endif; ?>
                <td>
                    <span class="mono"><?php echo s($r['numero']); ?></span> <span class="cb-tag cb-tag-<?php echo s($r['prioridad']); ?>"><?php echo s(Rfi::PRIORIDADES[$r['prioridad']]); ?></span><br>
                    <strong><?php echo s($r['asunto']); ?></strong>
                    <?php if (trim((string)$r['pregunta']) !== ''): ?><br><small class="text-muted"><?php echo s(mb_strimwidth($r['pregunta'], 0, 160, '…')); ?></small><?php endif; ?>
                    <?php if (trim((string)$r['respuesta']) !== ''): ?><br><small><i class="fa-solid fa-reply"></i> <?php echo s(mb_strimwidth($r['respuesta'], 0, 160, '…')); ?></small><?php endif; ?>
                </td>
                <td class="hide-m"><?php echo s($r['responsable']); ?></td>
                <td class="mono"><?php echo $r['fecha_limite'] ? formatear_fecha($r['fecha_limite']) : '—'; ?><?php if ($r['vencido']): ?> <span class="cb-tag cb-tag-critica"><?php echo (int)$r['dias_retraso']; ?> d vencido</span><?php endif; ?></td>
                <td class="hide-m"><?php echo $r['actividad_id'] ? '<a href="/programa/actividades/editar?proyecto=' . $pid . '&id=' . (int)$r['actividad_id'] . '">' . s($r['act_codigo'] . ' ' . mb_strimwidth($r['act_nombre'], 0, 40, '…')) . '</a>' . ($r['estado'] === 'abierto' && (int)$r['act_progreso'] < 100 ? ' <span class="cb-tag cb-tag-alta">bloqueada</span>' : '') : '<span class="text-muted">—</span>'; ?></td>
                <td>
                    <?php if ($puedeEditar): ?>
                    <form method="POST" action="/rfi/estado" class="co-estado">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                        <select name="estado" onchange="this.form.submit()" aria-label="Estado de <?php echo s($r['numero']); ?>"><?php foreach (Rfi::ESTADOS as $k => $etq): ?><option value="<?php echo $k; ?>"<?php echo $r['estado'] === $k ? ' selected' : ''; ?>><?php echo $etq; ?></option><?php endforeach; ?></select>
                    </form>
                    <?php else: ?><span class="cb-tag"><?php echo s(Rfi::ESTADOS[$r['estado']]); ?></span><?php endif; ?>
                </td>
                <td class="acciones">
                    <?php if ($puedeEditar): ?>
                        <a href="<?php echo s($url(['editar' => $r['id']])); ?>" class="btn btn-sm btn-ghost">Editar</a>
                        <form method="POST" action="/rfi/eliminar" style="display:inline" data-confirm="¿Eliminar este RFI?|Se borra también su revisión.">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
