<?php
/** Calidad e inspecciones. Variables: $proyecto, $disponible, $filtro, $lista, $resumen, $noConformidades, $plan, $puntos, $editarPlan, $abrirForm, $actividades, $puedeEditar, $ok, $error */
use Classes\Calidad;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/calidad?' . http_build_query(['proyecto' => $pid] + $q);
$e = $editarPlan;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clipboard-check"></i> Calidad e inspecciones</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · puntos de inspección, pruebas y no conformidades</p>
    </div>
    <?php if ($puedeEditar && $disponible): ?><a href="<?php echo s($url(['nuevo' => 1])); ?>" class="btn btn-primary">+ Nuevo plan</a><?php endif; ?>
</div>

<?php if (es_demo()): ?>
<div class="alerta alerta-info">
    <strong>Demo · Calidad e inspecciones.</strong> Este proyecto trae 6 planes de ejemplo para que veas todos los estados: <em>pendiente</em>, <em>en proceso</em>, <em>conforme</em>, <em>no conforme</em> y <em>cerrado</em>.
    Abre uno con el ícono de lista para revisar sus puntos y marca cómo se ve una <strong>no conformidad abierta</strong> en la sección de abajo. En tu proyecto puedes crear planes y marcar cada punto.
    <a href="/manual#calidad">Ver en el manual</a>.
</div>
<?php endif; ?>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_calidad_equipo_contratos_cierre.sql</code> para usar Calidad.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<div class="cb-kpis">
    <div class="cb-kpi"><small>Planes</small><strong><?php echo (int)$resumen['planes']; ?></strong><span><?php echo (int)$resumen['pendiente']; ?> pendiente(s) · <?php echo (int)$resumen['en_proceso']; ?> en proceso</span></div>
    <div class="cb-kpi"><small>Puntos de inspección</small><strong><?php echo (int)$resumen['puntos']; ?></strong><span><?php echo (int)$resumen['puntos_cumplen']; ?> cumplen · <?php echo (int)$resumen['puntos_pendientes']; ?> sin revisar</span></div>
    <div class="cb-kpi <?php echo $resumen['nc'] ? 'cb-kpi-alerta' : ''; ?>"><small>No conformidades abiertas</small><strong><?php echo (int)$resumen['nc']; ?></strong><span>Puntos marcados «no cumple» sin resolver</span></div>
</div>

<?php if ($puedeEditar && $abrirForm): ?>
<form method="POST" action="/calidad/guardar" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
    <h3><?php echo $e ? 'Editar plan' : 'Nuevo plan de inspección'; ?></h3>
    <div class="co-campos">
        <label>Código<input type="text" name="codigo" maxlength="40" value="<?php echo s($e['codigo'] ?? ''); ?>" placeholder="ITP-001"></label>
        <label class="co-ancho">Título<input type="text" name="titulo" maxlength="200" value="<?php echo s($e['titulo'] ?? ''); ?>" required placeholder="Inspección de cimentación…"></label>
        <label>Tipo<select name="tipo"><?php foreach (Calidad::TIPOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['tipo'] ?? 'itp') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label>Actividad<select name="actividad_id"><option value="0">— Todo el proyecto —</option><?php echo opciones_actividades($actividades, $e['actividad_id'] ?? null); ?></select></label>
        <label>Norma o referencia<input type="text" name="norma" maxlength="200" value="<?php echo s($e['norma'] ?? ''); ?>" placeholder="NOM, ASTM, especificación…"></label>
        <label>Responsable<input type="text" name="responsable" maxlength="150" value="<?php echo s($e['responsable'] ?? ''); ?>"></label>
        <label>Fecha<input type="date" name="fecha" value="<?php echo s($e['fecha'] ?? date('Y-m-d')); ?>"></label>
        <label>Estado<select name="estado"><?php foreach (Calidad::ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['estado'] ?? 'pendiente') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="5000"><?php echo s($e['notas'] ?? ''); ?></textarea></label>
        <?php if (!$e): ?>
        <label class="co-ancho">Puntos de inspección <span class="text-muted">(uno por línea; opcional: descripción | criterio)</span>
            <textarea name="puntos" rows="5" placeholder="Replanteo y niveles | ±5 mm&#10;Armado de acero según plano | Sin faltantes&#10;Resistencia del concreto | f'c ≥ 250 kg/cm²"></textarea>
        </label>
        <?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="<?php echo s($url()); ?>" class="btn btn-ghost">Cancelar</a>
</form>
<?php endif; ?>

<div class="cb-filtros">
    <a href="<?php echo s($url()); ?>" class="cb-chip<?php echo $filtro === '' ? ' activo' : ''; ?>">Todos <b><?php echo (int)$resumen['planes']; ?></b></a>
    <?php foreach (Calidad::ESTADOS as $k => $etq): ?><a href="<?php echo s($url(['estado' => $k])); ?>" class="cb-chip<?php echo $filtro === $k ? ' activo' : ''; ?>"><?php echo s($etq); ?></a><?php endforeach; ?>
</div>

<div class="card">
    <?php if (!$lista): ?><p class="text-muted">No hay planes de inspección<?php echo $filtro !== '' ? ' con ese filtro' : ' todavía'; ?>. Crea el primero con «Nuevo plan».</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla" <?php echo pag_attr($proyecto); ?>>
        <thead><tr><th>Plan</th><th class="hide-m">Actividad</th><th class="hide-m">Tipo</th><th>Fecha</th><th class="num">Puntos</th><th class="num">NC</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $c): ?>
            <tr>
                <td><strong><?php echo s(trim(($c['codigo'] ? $c['codigo'] . ' · ' : '') . $c['titulo'])); ?></strong><?php if ($c['norma']): ?><br><small class="text-muted"><?php echo s($c['norma']); ?></small><?php endif; ?></td>
                <td class="hide-m"><?php echo $c['act_codigo'] ? s($c['act_codigo'] . ' · ' . $c['act_nombre']) : '<span class="text-muted">Todo el proyecto</span>'; ?></td>
                <td class="hide-m"><?php echo s(Calidad::TIPOS[$c['tipo']] ?? $c['tipo']); ?></td>
                <td class="mono"><?php echo s(formatear_fecha($c['fecha'], 'd/m/Y')); ?></td>
                <td class="num"><?php echo (int)$c['cumplen']; ?>/<?php echo (int)$c['puntos']; ?></td>
                <td class="num"><?php echo (int)$c['nc'] ? '<b class="text-danger">' . (int)$c['nc'] . '</b>' : '0'; ?></td>
                <td><span class="badge badge-<?php echo $c['estado'] === 'conforme' ? 'cliente' : (in_array($c['estado'], ['no_conforme'], true) ? 'admin' : 'visitante'); ?>"><?php echo s(Calidad::ESTADOS[$c['estado']] ?? $c['estado']); ?></span></td>
                <td class="acciones">
                    <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['ver' => $c['id']])); ?>" title="Ver puntos"><i class="fa-solid fa-list-check"></i></a>
                    <?php if ($puedeEditar): ?>
                        <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => $c['id']])); ?>" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <form method="POST" action="/calidad/eliminar" onsubmit="return confirm('¿Eliminar este plan y sus puntos?');" style="display:inline">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                            <button class="btn btn-xs btn-ghost" type="submit" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php if ($plan): ?>
<div class="card" id="plan-<?php echo (int)$plan['id']; ?>">
    <div class="page-header" style="margin-bottom:1rem">
        <div><h3><?php echo s(trim(($plan['codigo'] ? $plan['codigo'] . ' · ' : '') . $plan['titulo'])); ?></h3>
            <p class="text-muted"><?php echo $plan['act_codigo'] ? s($plan['act_codigo'] . ' · ' . $plan['act_nombre']) . ' · ' : ''; ?><?php echo s(Calidad::TIPOS[$plan['tipo']] ?? $plan['tipo']); ?><?php echo $plan['norma'] ? ' · ' . s($plan['norma']) : ''; ?></p></div>
        <a href="<?php echo s($url()); ?>" class="btn btn-ghost btn-sm">Cerrar</a>
    </div>
    <?php if ($puedeEditar): ?>
    <form method="POST" action="/calidad/punto/agregar" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;margin-bottom:1rem">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
        <label style="flex:1;min-width:18rem">Nuevo punto<input type="text" name="descripcion" maxlength="500" required placeholder="Qué se inspecciona"></label>
        <label style="flex:1;min-width:14rem">Criterio<input type="text" name="criterio" maxlength="300" placeholder="Opcional"></label>
        <button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-plus"></i> Agregar punto</button>
    </form>
    <?php endif; ?>
    <?php if (!$puntos): ?><p class="text-muted">Este plan no tiene puntos de inspección.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Punto de inspección</th><th>Criterio</th><th>Resultado</th><th>Observación</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($puntos as $p): ?>
            <tr>
                <td><?php echo s($p['descripcion']); ?><?php if ($p['fecha']): ?><br><small class="text-muted"><?php echo s(formatear_fecha($p['fecha'], 'd/m/Y')); ?><?php echo $p['responsable'] ? ' · ' . s($p['responsable']) : ''; ?></small><?php endif; ?></td>
                <td class="text-sm text-muted"><?php echo s($p['criterio']); ?></td>
                <td colspan="2">
                    <?php if ($puedeEditar): ?>
                    <form method="POST" action="/calidad/punto" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                        <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
                        <input type="hidden" name="punto_id" value="<?php echo (int)$p['id']; ?>">
                        <select name="resultado" style="min-width:11rem"><?php foreach (Calidad::RESULTADOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo $p['resultado'] === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select>
                        <input type="text" name="observacion" value="<?php echo s($p['observacion']); ?>" placeholder="Observación" style="flex:1;min-width:14rem" maxlength="1000">
                        <label class="text-sm" title="Marcar como resuelta"><input type="checkbox" name="resuelto" value="1"<?php echo (int)$p['resuelto'] ? ' checked' : ''; ?>> resuelta</label>
                        <button class="btn btn-xs btn-primary" type="submit">Guardar</button>
                    </form>
                    <?php else: ?>
                        <span class="badge badge-<?php echo $p['resultado'] === 'cumple' ? 'cliente' : ($p['resultado'] === 'no_cumple' ? 'admin' : 'visitante'); ?>"><?php echo s(Calidad::RESULTADOS[$p['resultado']] ?? $p['resultado']); ?></span>
                        <?php if ($p['observacion']): ?><div class="text-sm text-muted"><?php echo s($p['observacion']); ?></div><?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($noConformidades): ?>
<div class="card">
    <h3><i class="fa-solid fa-triangle-exclamation text-danger"></i> No conformidades abiertas</h3>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Plan</th><th>Punto</th><th>Observación</th><th></th></tr></thead>
        <tbody><?php foreach ($noConformidades as $n): ?>
            <tr><td><?php echo s(trim(($n['plan_codigo'] ? $n['plan_codigo'] . ' · ' : '') . $n['plan_titulo'])); ?><?php if ($n['act_codigo']): ?><br><small class="text-muted"><?php echo s($n['act_codigo'] . ' · ' . $n['act_nombre']); ?></small><?php endif; ?></td>
                <td><?php echo s($n['descripcion']); ?></td>
                <td class="text-sm"><?php echo s($n['observacion']); ?></td>
                <td><a class="btn btn-xs btn-ghost" href="<?php echo s($url(['ver' => $n['plan_id']])); ?>">Ver plan</a></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php endif; ?>
