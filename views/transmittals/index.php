<?php
/** Envíos y acuses. Variables: $proyecto, $disponible, $lista, $resumen, $transmittal, $editar, $abrirForm, $siguienteNumero, $archivos, $puedeEditar, $ok, $error */
use Classes\Transmittals;
$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/transmittals?' . http_build_query(['proyecto' => $pid] + $q);
$t = $transmittal;
$e = $editar;
$dias = ['recibido' => 'Recibido conforme', 'observado' => 'Con observaciones', 'rechazado' => 'No recibido', 'pendiente' => 'Pendiente'];
?>
<?php require_once __DIR__ . '/../templates/herramientas-proyecto.php'; pestanas_herramienta('archivo', '/transmittals', $proyecto); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-paper-plane"></i> Envíos de documentos</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · a quién se le mandó cada revisión y quién firmó el acuse</p>
    </div>
    <?php if ($disponible && $puedeEditar && !$t): ?><a class="btn btn-primary" href="<?php echo s($url(['nuevo' => 1])); ?>">+ Nuevo envío</a><?php endif; ?>
</div>

<?php if (es_demo()): ?>
<div class="alerta alerta-info"><strong>Demo · Envíos.</strong> Cada envío congela la revisión de los documentos y da a cada destinatario un enlace para acusar recibo. Los envíos sin acuse se avisan, pero no bloquean la obra. <a href="/manual#envios">Ver en el manual</a>.</div>
<?php endif; ?>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_transmittals.sql</code> para usar los envíos.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<?php if (!$t): ?>
<div class="cb-kpis" style="margin-bottom:1.4rem">
    <div class="cb-kpi"><small>Envíos</small><strong><?php echo (int)$resumen['total']; ?></strong><span>Con documento y revisión</span></div>
    <div class="cb-kpi <?php echo $resumen['sin_acuse'] ? 'cb-kpi-alerta' : ''; ?>"><small>Sin acuse</small><strong><?php echo (int)$resumen['sin_acuse']; ?></strong><span>Destinatarios pendientes</span></div>
    <div class="cb-kpi <?php echo $resumen['vencidos'] ? 'cb-kpi-alerta' : ''; ?>"><small>Acuses vencidos</small><strong><?php echo (int)$resumen['vencidos']; ?></strong><span>Pasó la fecha límite</span></div>
    <div class="cb-kpi"><small>Con observaciones</small><strong><?php echo (int)$resumen['observados']; ?></strong><span>Requieren revisión</span></div>
</div>
<?php endif; ?>

<?php if ($abrirForm && $puedeEditar): ?>
<form method="POST" action="/transmittals/guardar" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
    <h3><?php echo $e ? 'Editar envío' : 'Nuevo envío'; ?></h3>
    <?php if ($e): ?><p class="text-muted text-sm">Solo se puede editar mientras está en borrador.</p><?php endif; ?>
    <div class="co-campos">
        <label>Número<input type="text" name="numero" maxlength="40" value="<?php echo s($e['numero'] ?? $siguienteNumero); ?>"></label>
        <label>Fecha<input type="date" name="fecha" value="<?php echo s($e['fecha'] ?? date('Y-m-d')); ?>"></label>
        <label>De<input type="text" name="de" maxlength="150" value="<?php echo s($e['de'] ?? ($_SESSION['nombre'] ?? '')); ?>"></label>
        <label>Medio<select name="medio"><?php foreach (Transmittals::MEDIOS as $k => $v): ?><option value="<?php echo $k; ?>"<?php echo ($e['medio'] ?? 'correo') === $k ? ' selected' : ''; ?>><?php echo s($v); ?></option><?php endforeach; ?></select></label>
        <label>Fecha límite de acuse<input type="date" name="fecha_limite" value="<?php echo s($e['fecha_limite'] ?? ''); ?>"></label>
        <label>Estado<select name="estado"><option value="enviado"<?php echo ($e['estado'] ?? 'enviado') === 'enviado' ? ' selected' : ''; ?>>Enviado</option><option value="borrador"<?php echo ($e['estado'] ?? '') === 'borrador' ? ' selected' : ''; ?>>Borrador</option></select></label>
        <label class="co-ancho">Asunto *<input type="text" name="asunto" maxlength="250" required value="<?php echo s($e['asunto'] ?? ''); ?>" placeholder="Ej.: Planos para construcción"></label>
        <label class="co-ancho">Mensaje<textarea name="mensaje" rows="2" maxlength="5000" placeholder="Favor de acusar recibo..."><?php echo s($e['mensaje'] ?? ''); ?></textarea></label>
    </div>
    <h4>Documentos</h4>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Documento del Archivo</th><th>Revisión que se congela</th></tr></thead>
        <tbody>
        <?php $docsSel = array_column($e['documentos'] ?? [], 'archivo_id'); for ($i = 0; $i < 6; $i++): $sel = $docsSel[$i] ?? 0; ?>
            <tr><td><select name="archivo_id[]"><option value="">— Sin documento —</option><?php foreach ($archivos as $a): ?><option value="<?php echo (int)$a->id; ?>"<?php echo (int)$sel === (int)$a->id ? ' selected' : ''; ?>><?php echo s($a->nombre . ($a->numero_revision ? ' · Rev. ' . $a->numero_revision : '')); ?></option><?php endforeach; ?></select></td><td class="text-muted text-sm">Se toma la revisión actual al guardar</td></tr>
        <?php endfor; ?>
        </tbody>
    </table></div>
    <h4>Destinatarios</h4>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Nombre *</th><th>Correo (opcional)</th></tr></thead>
        <tbody>
        <?php $dests = $e['destinatarios'] ?? []; for ($i = 0; $i < 4; $i++): $d = $dests[$i] ?? null; ?>
            <tr><td><input type="text" name="dest_nombre[]" maxlength="150" value="<?php echo s($d['nombre'] ?? ''); ?>" placeholder="Nombre o empresa"></td><td><input type="email" name="dest_email[]" maxlength="150" value="<?php echo s($d['email'] ?? ''); ?>" placeholder="correo@…"></td></tr>
        <?php endfor; ?>
        </tbody>
    </table></div>
    <div style="margin-top:.8rem"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Guardar envío</button> <a class="btn btn-ghost" href="<?php echo s($url()); ?>">Cancelar</a></div>
    <p class="text-muted text-sm">Al guardar como enviado se generan los enlaces de acuse. Después ya no se puede editar: se crea un envío nuevo.</p>
</form>
<?php endif; ?>

<?php if ($t): ?>
<div class="card" style="margin-bottom:1.4rem">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
        <div><h3 class="m-0"><?php echo s($t['numero']); ?> · <?php echo s($t['asunto']); ?></h3><p class="text-muted text-sm m-0"><?php echo s(formatear_fecha($t['fecha'], 'd/m/Y')); ?> · De <?php echo s($t['de']); ?> · <?php echo s(Transmittals::MEDIOS[$t['medio']] ?? $t['medio']); ?> · <?php echo s(Transmittals::ESTADOS[$t['estado']] ?? $t['estado']); ?></p></div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap"><a class="btn btn-sm btn-ghost" href="/transmittals/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$t['id']; ?>"><i class="fa-solid fa-file-pdf"></i> PDF</a><?php if ($puedeEditar && $t['estado'] === 'borrador'): ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url(['editar' => $t['id']])); ?>"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?><a class="btn btn-sm btn-ghost" href="<?php echo s($url()); ?>">Cerrar</a></div>
    </div>
    <?php if (trim((string)$t['mensaje']) !== ''): ?><p style="margin-top:.8rem"><?php echo nl2br(s($t['mensaje'])); ?></p><?php endif; ?>
    <h4>Documentos y revisión</h4>
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Documento</th><th>Revisión enviada</th></tr></thead><tbody>
        <?php foreach ($t['documentos'] as $d): ?><tr><td><?php echo s($d['nombre']); ?></td><td class="mono"><?php echo s($d['revision'] ?: '—'); ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <h4>Destinatarios y acuses</h4>
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Destinatario</th><th>Estado</th><th>Acuse</th><th>Enlace</th></tr></thead><tbody>
        <?php foreach ($t['destinatarios'] as $d): $link = '/transmittals/acuse?t=' . urlencode((string)$d['token']); ?>
            <tr><td><?php echo s($d['nombre']); ?><br><small class="text-muted"><?php echo s($d['email'] ?: 'Sin correo'); ?></small></td>
                <td><span class="cb-tag"><?php echo s($dias[$d['estado']] ?? $d['estado']); ?></span><?php if ($d['acuse_fecha']): ?><br><small class="text-muted"><?php echo s(formatear_fecha(substr((string)$d['acuse_fecha'], 0, 10), 'd/m/Y')); ?></small><?php endif; ?></td>
                <td class="text-sm"><?php echo s($d['acuse_nombre'] ?: '—'); ?><?php if (trim((string)$d['acuse_comentario']) !== ''): ?><br><small class="text-muted"><?php echo s($d['acuse_comentario']); ?></small><?php endif; ?></td>
                <td><a class="btn btn-xs btn-ghost" href="<?php echo s($link); ?>" target="_blank" rel="noopener"><i class="fa-solid fa-link"></i> Abrir</a><button type="button" class="btn btn-xs btn-ghost" data-copiar="<?php echo s($link); ?>" title="Copiar enlace"><i class="fa-solid fa-copy"></i></button></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <p class="text-muted text-sm">Comparte el enlace de cada destinatario. El acuse queda con nombre, fecha, hora e IP.</p>
</div>
<?php else: ?>
<div class="card">
    <h3><i class="fa-solid fa-list"></i> Envíos registrados</h3>
    <?php if (!$lista): ?><p class="text-muted">Todavía no hay envíos. Crea el primero con <strong>+ Nuevo envío</strong>.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Envío</th><th>Fecha</th><th>Asunto</th><th>Documentos</th><th>Acuses</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php foreach ($lista as $x): ?>
            <tr><td class="mono"><b><?php echo s($x['numero']); ?></b></td><td class="mono"><?php echo s(formatear_fecha($x['fecha'], 'd/m/Y')); ?></td><td><?php echo s($x['asunto']); ?></td><td class="num"><?php echo (int)$x['documentos']; ?></td><td class="num"><?php echo (int)$x['destinatarios'] - (int)$x['pendientes']; ?>/<?php echo (int)$x['destinatarios']; ?><?php if ((int)$x['pendientes']): ?><br><small class="text-danger"><?php echo (int)$x['pendientes']; ?> sin acuse</small><?php endif; ?></td><td><span class="cb-tag"><?php echo s(Transmittals::ESTADOS[$x['estado']] ?? $x['estado']); ?></span></td><td class="acciones" style="white-space:nowrap"><a class="btn btn-xs btn-ghost" href="<?php echo s($url(['ver' => $x['id']])); ?>" title="Ver" aria-label="Ver"><i class="fa-solid fa-eye"></i></a><?php if ($puedeEditar && $x['estado'] === 'borrador'): ?><a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => $x['id']])); ?>" title="Editar" aria-label="Editar"><i class="fa-solid fa-pen"></i></a><?php endif; ?><a class="btn btn-xs btn-ghost" href="/transmittals/pdf?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$x['id']; ?>" title="PDF" aria-label="PDF"><i class="fa-solid fa-file-pdf"></i></a></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div>
<?php endif; ?>
<script>
document.addEventListener('click', e => {
    const b = e.target.closest('[data-copiar]'); if (!b) return;
    const url = new URL(b.dataset.copiar, location.origin).href;
    navigator.clipboard?.writeText(url).then(() => { b.innerHTML = '<i class="fa-solid fa-check"></i>'; setTimeout(() => b.innerHTML = '<i class="fa-solid fa-copy"></i>', 1500); }).catch(() => {});
});
</script>
