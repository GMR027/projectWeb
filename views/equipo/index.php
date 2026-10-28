<?php
/** Equipo y maquinaria. Variables: $proyecto, $disponible, $lista, $resumen, $servicios, $editar, $abrirForm, $verHoras, $horas, $actividades, $puedeEditar, $ok, $error */
use Classes\Equipo;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/equipo?' . http_build_query(['proyecto' => $pid] + $q);
$e = $editar;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-truck"></i> Equipo y maquinaria</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · horas, costo por actividad y servicios preventivos</p>
    </div>
    <?php if ($puedeEditar && $disponible): ?><div><a href="<?php echo s($url(['nuevo' => 1])); ?>" class="btn btn-primary">+ Nuevo equipo</a> <a href="<?php echo s($url(['registrar' => 1])); ?>" class="btn btn-ghost">Registrar horas</a></div><?php endif; ?>
</div>

<?php if (es_demo()): ?>
<div class="alerta alerta-info">
    <strong>Demo · Equipo y maquinaria.</strong> Hay 5 equipos con horas y costo: una grúa torre, una retroexcavadora, una revolvedora en mantenimiento, una bomba de concreto y un camión fuera de servicio.
    Arriba se ven los <strong>servicios próximos</strong> (por fecha o por horas). El costo se calcula solo con la tarifa por hora. En tu proyecto puedes registrar horas y editar cada equipo.
    <a href="/manual#equipo">Ver en el manual</a>.
</div>
<?php endif; ?>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_calidad_equipo_contratos_cierre.sql</code> para usar Equipo.</p></div>
<?php return; endif; ?>

<?php if ($error !== ''): ?><div class="alerta alerta-error" role="alert"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito" role="status"><?php echo s($ok); ?></div><?php endif; ?>

<div class="cb-kpis">
    <div class="cb-kpi"><small>Equipo registrado</small><strong><?php echo (int)$resumen['total']; ?></strong><span><?php echo (int)$resumen['operativos']; ?> operativo(s) · <?php echo (int)$resumen['mantenimiento']; ?> en mantenimiento</span></div>
    <div class="cb-kpi"><small>Horas este mes</small><strong><?php echo number_format($resumen['horas_mes'], 1); ?></strong><span>Costo: <?php echo \Classes\Costos::moneda($resumen['costo_mes']); ?></span></div>
    <div class="cb-kpi <?php echo $servicios ? 'cb-kpi-alerta' : ''; ?>"><small>Servicios próximos</small><strong><?php echo count($servicios); ?></strong><span>Por fecha o por horas acumuladas</span></div>
</div>

<?php if ($servicios): ?>
<div class="card" style="margin-bottom:1.4rem">
    <h3><i class="fa-solid fa-screwdriver-wrench text-warning"></i> Servicios que se acercan</h3>
    <div class="tabla-wrapper"><table class="tabla"><thead><tr><th>Equipo</th><th>Servicio</th><th>Horas</th><th></th></tr></thead><tbody>
        <?php foreach ($servicios as $s): ?>
            <tr><td><?php echo s($s['nombre']); ?><?php if ($s['marca'] || $s['modelo']): ?><br><small class="text-muted"><?php echo s(trim($s['marca'] . ' ' . $s['modelo'])); ?></small><?php endif; ?></td>
                <td class="mono"><?php echo $s['proximo_servicio'] ? s(formatear_fecha($s['proximo_servicio'], 'd/m/Y')) : '—'; ?><?php if ($s['dias'] !== null && (int)$s['dias'] <= 0): ?> <b class="text-danger">(vencido)</b><?php endif; ?></td>
                <td><?php echo number_format((float)$s['horas'], 1); ?><?php echo $s['proximo_servicio_horas'] ? ' / ' . (int)$s['proximo_servicio_horas'] : ''; ?></td>
                <td><a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => $s['id']])); ?>">Editar</a></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>

<?php if ($puedeEditar && $abrirForm): ?>
<form method="POST" action="/equipo/guardar" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <input type="hidden" name="id" value="<?php echo $e ? (int)$e['id'] : 0; ?>">
    <h3><?php echo $e ? 'Editar equipo' : 'Nuevo equipo'; ?></h3>
    <div class="co-campos">
        <label class="co-ancho">Nombre<input type="text" name="nombre" maxlength="150" value="<?php echo s($e['nombre'] ?? ''); ?>" required placeholder="Retroexcavadora CAT 320"></label>
        <label>Tipo<input type="text" name="tipo" maxlength="80" value="<?php echo s($e['tipo'] ?? ''); ?>" placeholder="Excavadora, grúa, revolvedora…"></label>
        <label>Marca<input type="text" name="marca" maxlength="80" value="<?php echo s($e['marca'] ?? ''); ?>"></label>
        <label>Modelo<input type="text" name="modelo" maxlength="80" value="<?php echo s($e['modelo'] ?? ''); ?>"></label>
        <label>Serie / placas<input type="text" name="serie" maxlength="80" value="<?php echo s($e['serie'] ?? ''); ?>"></label>
        <label>Propietario<input type="text" name="propietario" maxlength="150" value="<?php echo s($e['propietario'] ?? ''); ?>" placeholder="Propio o empresa rentadora"></label>
        <label>Tarifa por hora<input type="number" name="tarifa_hora" min="0" step="0.01" value="<?php echo s($e['tarifa_hora'] ?? '0'); ?>"></label>
        <label>Estado<select name="estado"><?php foreach (Equipo::ESTADOS as $k => $t): ?><option value="<?php echo $k; ?>"<?php echo ($e['estado'] ?? 'operativo') === $k ? ' selected' : ''; ?>><?php echo s($t); ?></option><?php endforeach; ?></select></label>
        <label>Próximo servicio (fecha)<input type="date" name="proximo_servicio" value="<?php echo s($e['proximo_servicio'] ?? ''); ?>"></label>
        <label>Próximo servicio (horas)<input type="number" name="proximo_servicio_horas" min="0" step="1" value="<?php echo s($e['proximo_servicio_horas'] ?? ''); ?>" placeholder="Ej. 500"></label>
        <label class="text-sm"><input type="checkbox" name="rentado" value="1"<?php echo !empty($e['rentado']) ? ' checked' : ''; ?>> Es rentado</label>
        <label class="co-ancho">Notas<textarea name="notas" rows="2" maxlength="1000"><?php echo s($e['notas'] ?? ''); ?></textarea></label>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar</button>
    <a href="<?php echo s($url()); ?>" class="btn btn-ghost">Cancelar</a>
</form>
<?php endif; ?>

<?php if ($puedeEditar && !empty($abrirHoras)): ?>
<form method="POST" action="/equipo/horas" class="card co-form">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <h3>Registrar horas trabajadas</h3>
    <div class="co-campos">
        <label>Equipo<select name="equipo_id" required><option value="">— Elige —</option><?php foreach ($lista as $x): ?><option value="<?php echo (int)$x['id']; ?>"><?php echo s($x['nombre']); ?><?php echo $x['tarifa_hora'] ? ' · $' . number_format((float)$x['tarifa_hora'], 2) . '/h' : ''; ?></option><?php endforeach; ?></select></label>
        <label>Fecha<input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></label>
        <label>Horas<input type="number" name="horas" min="0.25" max="24" step="0.25" value="8" required></label>
        <label>Actividad<select name="actividad_id"><option value="0">— Sin actividad —</option><?php echo opciones_actividades($actividades, null); ?></select></label>
        <label>Operador<input type="text" name="operador" maxlength="150"></label>
        <label class="co-ancho">Observaciones<input type="text" name="observaciones" maxlength="1000"></label>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Registrar</button>
    <a href="<?php echo s($url()); ?>" class="btn btn-ghost">Cancelar</a>
</form>
<?php endif; ?>

<div class="card">
    <?php if (!$lista): ?><p class="text-muted">Todavía no hay equipo registrado.</p><?php else: ?>
    <div class="tabla-wrapper"><table class="tabla" <?php echo pag_attr($proyecto); ?>>
        <thead><tr><th>Equipo</th><th class="hide-m">Propietario</th><th class="num">Tarifa/h</th><th class="num">Horas</th><th class="num">Costo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $x): ?>
            <tr>
                <td><strong><?php echo s($x['nombre']); ?></strong><?php if ($x['marca'] || $x['modelo']): ?><br><small class="text-muted"><?php echo s(trim($x['marca'] . ' ' . $x['modelo'])); ?><?php echo $x['serie'] ? ' · ' . s($x['serie']) : ''; ?></small><?php endif; ?></td>
                <td class="hide-m"><?php echo s($x['propietario'] ?: ($x['rentado'] ? 'Rentado' : 'Propio')); ?></td>
                <td class="num mono"><?php echo $x['tarifa_hora'] ? '$' . number_format((float)$x['tarifa_hora'], 2) : '—'; ?></td>
                <td class="num"><?php echo number_format((float)$x['horas'], 1); ?></td>
                <td class="num mono"><?php echo \Classes\Costos::moneda((float)$x['importe']); ?></td>
                <td><span class="badge badge-<?php echo $x['estado'] === 'operativo' ? 'cliente' : ($x['estado'] === 'mantenimiento' ? 'visitante' : 'admin'); ?>"><?php echo s(Equipo::ESTADOS[$x['estado']] ?? $x['estado']); ?></span></td>
                <td class="acciones">
                    <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['horas' => (int)$x['id']])); ?>" title="Ver horas"><i class="fa-solid fa-clock"></i></a>
                    <?php if ($puedeEditar): ?>
                        <a class="btn btn-xs btn-ghost" href="<?php echo s($url(['editar' => $x['id']])); ?>" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        <form method="POST" action="/equipo/eliminar" onsubmit="return confirm('¿Eliminar este equipo y sus horas?');" style="display:inline">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$x['id']; ?>">
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

<?php if ($horas): ?>
<div class="card">
    <div class="page-header" style="margin-bottom:1rem"><div><h3>Horas registradas<?php echo $verHoras ? ' · ' . s($horas[0]['equipo']) : ''; ?></h3></div>
        <?php if ($verHoras): ?><a class="btn btn-ghost btn-sm" href="<?php echo s($url()); ?>">Ver todo el equipo</a><?php endif; ?></div>
    <div class="tabla-wrapper"><table class="tabla" <?php echo pag_attr($proyecto); ?>>
        <thead><tr><th>Fecha</th><th>Equipo</th><th class="hide-m">Actividad</th><th class="num">Horas</th><th class="num">Importe</th><th class="hide-m">Operador</th><th></th></tr></thead>
        <tbody><?php foreach ($horas as $h): ?>
            <tr><td class="mono"><?php echo s(formatear_fecha($h['fecha'], 'd/m/Y')); ?></td>
                <td><?php echo s($h['equipo']); ?></td>
                <td class="hide-m"><?php echo $h['act_codigo'] ? s($h['act_codigo'] . ' · ' . $h['act_nombre']) : '<span class="text-muted">—</span>'; ?></td>
                <td class="num"><?php echo number_format((float)$h['horas'], 1); ?></td>
                <td class="num mono"><?php echo \Classes\Costos::moneda((float)$h['importe']); ?></td>
                <td class="hide-m"><?php echo s($h['operador']); ?></td>
                <td><?php if ($puedeEditar): ?><form method="POST" action="/equipo/horas/eliminar" style="display:inline" onsubmit="return confirm('¿Eliminar este registro?');"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="equipo_id" value="<?php echo (int)$h['equipo_id']; ?>"><input type="hidden" name="id" value="<?php echo (int)$h['id']; ?>"><button class="btn btn-xs btn-ghost" type="submit" title="Eliminar"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div>
<?php endif; ?>
