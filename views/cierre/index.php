<?php
/** Cierre de obra. Variables: $proyecto, $disponible, $config, $items, $resumen, $filtro, $editar, $abrirForm, $archivos, $puedeEditar, $ok, $error */
use Classes\Cierre;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/cierre?' . http_build_query(['proyecto' => $pid] + $q);
$e = $editar;
$badge = ['pendiente' => 'admin', 'en_proceso' => 'visitante', 'completado' => 'cliente', 'na' => 'visitante'];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-flag-checkered"></i> Cierre de obra</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · entrega-recepción, documentos y pendientes</p>
    </div>
    <?php if ($disponible): ?>
    <div>
        <?php if ($puedeEditar): ?><a href="<?php echo s($url(['nuevo' => 1])); ?>" class="btn btn-primary">+ Nuevo punto</a><?php endif; ?>
        <?php if (puede_descargar()): ?><a href="/cierre/pdf?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-file-pdf"></i> Acta PDF</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (es_demo()): ?>
<div class="alerta alerta-info">
    <strong>Demo · Cierre de obra.</strong> La lista de entrega tiene 10 puntos con distinto avance (30 %): as-builts, garantías, manuales, punch y documentos.
    Mira cómo se calcula la barra de avance y descarga el <strong>Acta PDF</strong> con la tabla y las firmas. En tu proyecto puedes marcar puntos y completar los datos del acta.
    <a href="/manual#cierre">Ver en el manual</a>.
</div>
<?php endif; ?>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_calidad_equipo_contratos_cierre.sql</code> para usar Cierre de obra.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<div class="card" style="margin-bottom:1.4rem">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.8rem">
        <h3 style="margin:0">Avance de entrega</h3>
        <strong class="mono text-accent"><?php echo (int)$resumen['pct']; ?>%</strong>
    </div>
    <div class="barra-progreso"><div class="barra-fill" style="width:<?php echo (int)$resumen['pct']; ?>%"></div></div>
    <p class="text-sm text-muted" style="margin-top:.6rem"><?php echo (int)$resumen['completados']; ?> completado(s) · <?php echo (int)$resumen['en_proceso']; ?> en proceso · <?php echo (int)$resumen['pendientes']; ?> pendiente(s)<?php echo (int)$resumen['na'] ? ' · ' . (int)$resumen['na'] . ' no aplica' : ''; ?></p>
    <?php if (!$resumen['total'] && $puedeEditar): ?>
        <form method="POST" action="/cierre/sugeridos" style="margin-top:1rem"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <button class="btn btn-ghost btn-sm" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Agregar la lista estándar de entrega</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($puedeEditar): ?>
<details class="card" style="margin-bottom:1.4rem"<?php echo ($config['fecha_entrega'] ?? '') ? '' : ' open'; ?>>
    <summary><strong>Datos del acta</strong> <small class="text-muted">· fecha, responsable y notas de la entrega</small></summary>
    <form method="POST" action="/cierre/config" class="co-form" style="margin-top:1rem">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <div class="co-campos">
            <label>Fecha de entrega<input type="date" name="fecha_entrega" value="<?php echo s($config['fecha_entrega'] ?? ''); ?>"></label>
            <label>Responsable de la entrega<input type="text" name="responsable" maxlength="150" value="<?php echo s($config['responsable'] ?? ''); ?>"></label>
            <label class="co-ancho">Notas del acta<textarea name="notas" rows="2" maxlength="5000"><?php echo s($config['notas'] ?? ''); ?></textarea></label>
        </div>
        <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar datos</button>
    </form>
</details>
<?php endif; ?>

<?php if ($puedeEditar && $abrirForm): ?>
<form method="POST" action="/cierre/item" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
    <h3><?php echo $e ? 'Editar punto de entrega' : 'Nuevo punto de entrega'; ?></h3>
    <div class="co-campos">
        <label>Categoría<select name="categoria"><?php foreach (Cierre::CATEGORIAS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['categoria'] ?? 'documento') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label class="co-ancho">Título<input type="text" name="titulo" maxlength="250" value="<?php echo s($e['titulo'] ?? ''); ?>" required></label>
        <label>Responsable<input type="text" name="responsable" maxlength="150" value="<?php echo s($e['responsable'] ?? ''); ?>"></label>
        <label>Fecha compromiso<input type="date" name="fecha_compromiso" value="<?php echo s($e['fecha_compromiso'] ?? ''); ?>"></label>
        <label>Estado<select name="estado"><?php foreach (Cierre::ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['estado'] ?? 'pendiente') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label>Orden<input type="number" name="orden" min="0" value="<?php echo s($e['orden'] ?? 0); ?>"></label>
        <label class="co-ancho">Documento en el Archivo (opcional)<select name="archivo_id"><option value="0">— Sin documento —</option><?php foreach ($archivos as $a): ?><option value="<?php echo (int)$a->id; ?>"<?php echo (int)($e['archivo_id'] ?? 0) === (int)$a->id ? ' selected' : ''; ?>><?php echo s($a->nombre); ?></option><?php endforeach; ?></select></label>
        <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="1000"><?php echo s($e['notas'] ?? ''); ?></textarea></label>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="<?php echo s($url()); ?>" class="btn btn-ghost">Cancelar</a>
</form>
<?php endif; ?>

<div class="cb-filtros">
    <a href="<?php echo s($url()); ?>" class="cb-chip<?php echo $filtro === '' ? ' activo' : ''; ?>">Todo <b><?php echo (int)$resumen['total']; ?></b></a>
    <?php foreach (Cierre::CATEGORIAS as $k => $t): $c = $resumen['por_categoria'][$k] ?? null; if (!$c) continue; ?>
        <a href="<?php echo s($url(['categoria' => $k])); ?>" class="cb-chip<?php echo $filtro === $k ? ' activo' : ''; ?>"><?php echo s($t); ?> <b><?php echo (int)$c['completados']; ?>/<?php echo (int)$c['total']; ?></b></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php if (!$items): ?><p class="text-muted">No hay puntos de entrega<?php echo $filtro !== '' ? ' en esa categoría' : ' todavía'; ?>.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla" <?php echo pag_attr($proyecto); ?>>
        <thead><tr><th>Punto</th><th class="hide-m">Responsable</th><th>Compromiso</th><th>Estado</th><th></th></tr></thead>
        <tbody><?php foreach ($items as $i): ?>
            <tr>
                <td><strong><?php echo s($i['titulo']); ?></strong><br><small class="text-muted"><?php echo s(Cierre::CATEGORIAS[$i['categoria']] ?? $i['categoria']); ?><?php echo $i['notas'] ? ' · ' . s($i['notas']) : ''; ?></small></td>
                <td class="hide-m"><?php echo s($i['responsable'] ?: '—'); ?></td>
                <td class="mono"><?php echo $i['fecha_compromiso'] ? s(formatear_fecha($i['fecha_compromiso'], 'd/m/Y')) : '—'; ?><?php if ($i['fecha_compromiso'] && (int)$i['dias'] < 0 && !in_array($i['estado'], ['completado', 'na'], true)): ?><br><small class="text-danger">vencido</small><?php endif; ?></td>
                <td><span class="badge badge-<?php echo $badge[$i['estado']] ?? 'visitante'; ?>"><?php echo s(Cierre::ESTADOS[$i['estado']] ?? $i['estado']); ?></span></td>
                <td class="acciones">
                    <?php if ($i['archivo_id']): ?><a class="btn btn-xs btn-ghost" href="/archivos/ver?proyecto=<?php echo $pid; ?>&id=<?php echo (int)$i['archivo_id']; ?>" title="Ver documento"><i class="fa-solid fa-paperclip"></i></a><?php endif; ?>
                    <?php if ($puedeEditar): ?>
                        <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => $i['id']])); ?>" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <form method="POST" action="/cierre/item/eliminar" onsubmit="return confirm('¿Eliminar este punto?');" style="display:inline">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$i['id']; ?>">
                            <button class="btn btn-xs btn-ghost" type="submit" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>
