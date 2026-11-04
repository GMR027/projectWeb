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
    <div class="cb-kpi <?php echo $detenidas ? 'cb-kpi-alerta' : ''; ?>"><small>Actividades detenidas</small><strong><?php echo count($detenidas); ?></strong><span>El avance está topado por su inspección</span></div>
    <?php if (!empty($completa)): ?>
    <div class="cb-kpi <?php echo $completa['rechazados'] ? 'cb-kpi-alerta' : ''; ?>"><small>Ensayos rechazados</small><strong><?php echo (int)$completa['rechazados']; ?></strong><span>De <?php echo (int)$completa['ensayos']; ?> ensayo(s)</span></div>
    <div class="cb-kpi <?php echo $completa['nc_abiertas'] ? 'cb-kpi-alerta' : ''; ?>"><small>NC formales abiertas</small><strong><?php echo (int)$completa['nc_abiertas']; ?></strong><span>Costo abierto <?php echo '$' . number_format((float)$completa['costo_nc'], 2); ?></span></div>
    <?php endif; ?>
</div>

<nav class="centro-ia-tabs" aria-label="Calidad">
    <?php foreach (['planes' => ['fa-clipboard-check', 'Planes ITP'], 'ensayos' => ['fa-flask', 'Ensayos'], 'nc' => ['fa-triangle-exclamation', 'No conformidades'], 'expedientes' => ['fa-folder-open', 'Expedientes']] as $k => [$ic, $nom]): ?>
        <a href="<?php echo s($url(['tab' => $k])); ?>" class="centro-ia-tabs__tab<?php echo $tab === $k ? ' activa' : ''; ?>"<?php echo $tab === $k ? ' aria-current="page"' : ''; ?>><i class="fa-solid <?php echo $ic; ?>"></i> <?php echo s($nom); ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'planes'): ?>
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
        <label class="co-check" title="Mientras tenga puntos pendientes o una no conformidad abierta, la actividad no podrá cerrarse">
            <input type="checkbox" name="bloquea" value="1"<?php echo (int)($e['bloquea'] ?? 1) ? ' checked' : ''; ?>> Detiene el avance de la actividad
        </label>
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
    <?php if (!$lista): ?><?php echo estado_vacio('fa-clipboard-check',
        $filtro !== '' ? 'Ningún plan coincide con el filtro' : 'Aún no hay planes de inspección',
        $filtro !== '' ? 'Prueba con otro estado.' : 'Crea el primer plan con sus puntos de inspección, pruebas y no conformidades.',
        $filtro !== '' || !$puedeEditar || !$disponible ? null : ['texto' => 'Nuevo plan', 'url' => $url(['nuevo' => 1]), 'icono' => 'fa-plus'],
        null, '', 'estado-vacio-sm'); ?><?php else: ?>
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
                        <form class="inline" method="POST" action="/calidad/eliminar" data-confirm="¿Eliminar el plan?|Se eliminarán el plan de inspección y todos sus puntos.|Eliminar">
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
            <p class="text-muted"><?php echo $plan['act_codigo'] ? s($plan['act_codigo'] . ' · ' . $plan['act_nombre']) . ' · ' : ''; ?><?php echo s(Calidad::TIPOS[$plan['tipo']] ?? $plan['tipo']); ?><?php echo $plan['norma'] ? ' · ' . s($plan['norma']) : ''; ?></p>
            <?php if ((int)$plan['bloquea']): ?><span class="badge badge-visitante"><i class="fa-solid fa-lock"></i> Detiene el avance de la actividad</span><?php else: ?><span class="badge badge-cliente"><i class="fa-solid fa-lock-open"></i> Solo informativo</span><?php endif; ?></div>
        <a href="<?php echo s($url()); ?>" class="btn btn-ghost btn-sm">Cerrar</a>
    </div>
    <?php if ($puedeEditar): ?>
    <form method="POST" action="/calidad/punto/agregar" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end;margin-bottom:1rem">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
        <label class="flex-1" style="min-width:18rem">Nuevo punto<input type="text" name="descripcion" maxlength="500" required placeholder="Qué se inspecciona"></label>
        <label class="flex-1" style="min-width:14rem">Criterio<input type="text" name="criterio" maxlength="300" placeholder="Opcional"></label>
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

<?php if ($detenidas): ?>
<div class="card" id="detenidas">
    <h3><i class="fa-solid fa-lock text-warning"></i> Actividades detenidas por calidad</h3>
    <p class="text-muted text-sm">Mientras su inspección tenga puntos pendientes, la actividad no pasa de 99 %. Con una no conformidad abierta, el avance se congela. Al marcar los puntos como «cumple» el candado se abre solo.</p>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Actividad</th><th>Por qué está detenida</th><th class="num">Avance</th><?php if ($puedeLiberar): ?><th>Liberar avance</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($detenidas as $d): $a = $d['actividad']; ?>
            <tr>
                <td><strong><?php echo s($a['codigo'] . ' · ' . $a['nombre']); ?></strong><br><small class="text-muted"><?php echo s($a['partida']); ?></small></td>
                <td class="text-sm">
                    <?php echo s($d['motivo']); ?>
                    <?php if ($d['nivel'] === 'liberada'): ?><br><span class="badge badge-cliente"><i class="fa-solid fa-lock-open"></i> Liberada por <?php echo s($d['liberada_por']); ?></span><?php endif; ?>
                    <?php if ($d['plan_id']): ?><br><a class="text-sm" href="<?php echo s($url(['ver' => $d['plan_id']])); ?>">Ver plan de inspección</a><?php endif; ?>
                </td>
                <td class="num"><b><?php echo (int)$a['progreso']; ?> %</b><br><small class="text-muted">tope <?php echo (int)$d['tope']; ?> %</small></td>
                <?php if ($puedeLiberar): ?>
                <td>
                    <?php if ($d['nivel'] === 'liberada'): ?>
                        <span class="text-sm text-muted"><i class="fa-solid fa-lock-open"></i> Avance liberado</span>
                    <?php else: ?>
                    <form method="POST" action="/calidad/liberar" class="co-form" style="min-width:24rem">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                        <input type="hidden" name="actividad_id" value="<?php echo (int)$a['id']; ?>">
                        <label>Autorizar hasta (%)
                            <input type="number" name="progreso_autorizado" min="<?php echo max(1, (int)$d['tope']); ?>" max="100" value="100" required style="width:9rem">
                        </label>
                        <label>Motivo (obligatorio)
                            <input type="text" name="motivo" minlength="10" maxlength="500" required placeholder="Ej.: el cliente autorizó continuar; el ensayo llega el viernes">
                        </label>
                        <button class="btn btn-xs btn-primary" type="submit"><i class="fa-solid fa-lock-open"></i> Liberar</button>
                    </form>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<?php if ($liberaciones && $puedeLiberar): ?>
<div class="card">
    <h3><i class="fa-solid fa-lock-open"></i> Liberaciones firmadas</h3>
    <p class="text-muted text-sm">Una liberación caduca sola si aparece una no conformidad nueva en esa actividad.</p>
    <div class="tabla-wrapper"><table class="tabla">
        <thead><tr><th>Actividad</th><th class="num">Hasta</th><th>Motivo</th><th>Quién y cuándo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($liberaciones as $l): ?>
            <tr>
                <td><?php echo $l['act_codigo'] ? s($l['act_codigo'] . ' · ' . $l['act_nombre']) : 'Actividad ' . (int)$l['actividad_id']; ?></td>
                <td class="num"><?php echo (int)$l['progreso_autorizado']; ?> %</td>
                <td class="text-sm"><?php echo s($l['motivo']); ?></td>
                <td class="text-sm"><?php echo s($l['usuario']); ?><br><small class="text-muted"><?php echo s(formatear_fecha(substr((string)$l['creado'], 0, 10), 'd/m/Y')); ?></small></td>
                <td><?php if ($l['vigente']): ?><span class="badge badge-cliente">Vigente</span><?php else: ?><span class="badge badge-admin" title="Apareció una no conformidad nueva: el candado volvió">Caducada</span><?php endif; ?></td>
                <td>
                    <form class="inline" method="POST" action="/calidad/liberar/revocar" data-confirm="Revocar liberación|El candado de calidad volverá a aplicar en el siguiente cambio de avance.|Revocar">
                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">
                        <button class="btn btn-xs btn-ghost" type="submit" title="Revocar"><i class="fa-solid fa-rotate-left"></i></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
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
<?php elseif ($tab === 'ensayos'): ?>
    <?php include __DIR__ . '/_ensayos.php'; ?>
<?php elseif ($tab === 'nc'): ?>
    <?php include __DIR__ . '/_nc.php'; ?>
<?php else: ?>
    <?php include __DIR__ . '/_expedientes.php'; ?>
<?php endif; ?>
