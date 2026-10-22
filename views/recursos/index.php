<?php
/** Recursos y cuadrillas. Variables: $proyecto, $disponible, $pestana, $desde, $nSemanas, $puedeEditar, $carga, $asignaciones, $actividades, $personal, $error, $ok */
use Classes\Recursos;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/recursos?' . http_build_query(['proyecto' => $pid] + $q);
$tabs = ['carga' => ['fa-chart-gantt', 'Carga semanal'], 'recursos' => ['fa-people-group', 'Personas y cuadrillas'], 'asignaciones' => ['fa-link', 'Asignaciones']];
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-people-group"></i> Recursos y cuadrillas</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · quién trabaja en qué actividad y cuándo se sobrecarga</p>
    </div>
</div>

<?php if (!$disponible): ?>
    <div class="card"><p>Falta aplicar la migración <code>database/migracion_fase3.sql</code> para usar los recursos.</p></div>
<?php return; endif; ?>

<nav class="centro-ia-tabs" aria-label="Recursos">
    <?php foreach ($tabs as $k => [$icono, $nombre]): ?>
        <a href="<?php echo s($url(['pestana' => $k])); ?>" class="centro-ia-tabs__tab<?php echo $k === $pestana ? ' activa' : ''; ?>"<?php echo $k === $pestana ? ' aria-current="page"' : ''; ?>><i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s($nombre); ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($error !== ''): ?><div class="alerta alerta-error"><?php echo s($error); ?></div><?php elseif ($ok !== ''): ?><div class="alerta alerta-exito">Guardado.</div><?php endif; ?>

<?php if ($pestana === 'carga'): $sem = $carga['semanas']; $rec = $carga['recursos']; $sob = $carga['sobrecargas']; ?>
    <?php if ($sob): ?>
        <div class="card rc-alerta">
            <h3><i class="fa-solid fa-triangle-exclamation"></i> <?php echo count($sob); ?> sobrecarga(s) en este periodo</h3>
            <ul>
            <?php foreach (array_slice($sob, 0, 8) as $x): ?>
                <li><strong><?php echo s($x['recurso']); ?></strong>: <?php echo (int)$x['pct']; ?> % la semana del <?php echo formatear_fecha($x['inicio'], 'd/m'); ?> — <?php echo s(implode(' + ', array_map(fn($a) => $a['codigo'] . ' (' . $a['dedicacion'] . '%)', $x['acts']))); ?></li>
            <?php endforeach; ?>
            <?php if (count($sob) > 8): ?><li class="text-muted">y <?php echo count($sob) - 8; ?> más…</li><?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
    <div class="rc-nav">
        <a class="btn btn-sm btn-ghost" href="<?php echo s($url(['desde' => $desde - 4, 'semanas' => $nSemanas])); ?>">← 4 semanas</a>
        <a class="btn btn-sm btn-ghost" href="<?php echo s($url(['semanas' => $nSemanas])); ?>">Hoy</a>
        <a class="btn btn-sm btn-ghost" href="<?php echo s($url(['desde' => $desde + 4, 'semanas' => $nSemanas])); ?>">4 semanas →</a>
        <span class="text-muted text-sm">100 % = la jornada completa del recurso (sin cuenta de actividades al 100 % de avance)</span>
    </div>
    <?php if (!$rec): ?>
        <div class="card"><p class="text-muted">Aún no hay recursos. Crea personas o cuadrillas en la pestaña «Personas y cuadrillas» y asígnalas a actividades.</p></div>
    <?php else: ?>
    <div class="card"><div class="tabla-wrapper"><table class="tabla rc-tabla">
        <thead><tr><th>Recurso</th><?php foreach ($sem as $s): ?><th class="mono" title="<?php echo formatear_fecha($s['inicio']); ?> – <?php echo formatear_fecha($s['fin']); ?>">S<?php echo (int)$s['numero']; ?><br><small><?php echo formatear_fecha($s['inicio'], 'd/m'); ?></small></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($rec as $r): ?>
            <tr>
                <td><i class="fa-solid <?php echo $r['tipo'] === 'cuadrilla' ? 'fa-people-group' : 'fa-user'; ?>"></i> <?php echo s($r['nombre']); ?><?php if ($r['tipo'] === 'cuadrilla'): ?> <small class="text-muted">× <?php echo (int)$r['integrantes']; ?></small><?php endif; ?></td>
                <?php foreach ($r['carga'] as $c): $nv = Recursos::nivel($c['pct']); ?>
                    <td class="mono rc-celda rc-<?php echo $nv ?: 'vacia'; ?>" title="<?php echo $c['acts'] ? s(implode("\n", array_map(fn($a) => $a['codigo'] . ' ' . $a['nombre'] . ' (' . $a['dedicacion'] . '%)', $c['acts'])) . "\n" . $c['horas'] . ' h de ' . $c['capacidad'] . ' h') : 'Libre'; ?>"><?php echo $c['pct'] > 0 ? (int)$c['pct'] . '%' : ''; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
    <?php endif; ?>

<?php elseif ($pestana === 'recursos'): ?>
    <?php if ($puedeEditar): ?>
    <form method="POST" action="/recursos/guardar" class="card co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <h3>Nuevo recurso</h3>
        <div class="co-campos">
            <label>Tipo<select name="tipo"><?php foreach (Recursos::TIPOS as $k => $etq): ?><option value="<?php echo $k; ?>"><?php echo $etq; ?></option><?php endforeach; ?></select></label>
            <label>Persona del directorio<select name="personal_id"><option value="0">— Ninguna (escribir nombre) —</option><?php foreach ($personal as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo s($p['nombre'] . ($p['departamento'] ? ' · ' . $p['departamento'] : '')); ?></option><?php endforeach; ?></select></label>
            <label>Nombre<input type="text" name="nombre" maxlength="150" placeholder="Cuadrilla de albañilería, Ing. Pérez…"></label>
            <label>Integrantes (cuadrilla)<input type="number" name="integrantes" min="1" max="500" value="1"></label>
            <label>Horas por día<input type="number" name="horas_dia" min="1" max="24" step="0.5" value="8"></label>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Agregar</button>
    </form>
    <?php endif; ?>
    <div class="card">
        <?php if (!$carga['recursos']): ?><p class="text-muted">Aún no hay recursos.</p><?php else: ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Integrantes</th><th>Horas/día</th><th>Actividades</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($carga['recursos'] as $r): ?>
                <tr>
                    <td><?php echo s($r['nombre']); ?></td><td><?php echo s(Recursos::TIPOS[$r['tipo']]); ?></td><td class="mono"><?php echo (int)$r['integrantes']; ?></td><td class="mono"><?php echo s($r['horas_dia'] + 0); ?></td><td class="mono"><?php echo (int)$r['asignaciones']; ?></td>
                    <td class="acciones"><?php if ($puedeEditar): ?>
                        <form method="POST" action="/recursos/eliminar" style="display:inline" data-confirm="¿Eliminar este recurso?|Se quitará de todas las actividades.">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button type="submit" class="btn btn-sm btn-danger">✕</button>
                        </form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php else: ?>
    <?php if ($puedeEditar): ?>
    <form method="POST" action="/recursos/asignar" class="card co-form">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <h3>Asignar a una actividad</h3>
        <?php if (!$carga['recursos']): ?><p class="text-muted">Primero crea un recurso en «Personas y cuadrillas».</p><?php else: ?>
        <div class="co-campos">
            <label>Actividad<select name="actividad_id" required><?php echo opciones_actividades($actividades); ?></select></label>
            <label>Recurso<select name="recurso_id" required><?php foreach ($carga['recursos'] as $r): ?><option value="<?php echo (int)$r['id']; ?>"><?php echo s($r['nombre']); ?></option><?php endforeach; ?></select></label>
            <label>Dedicación (%)<input type="number" name="dedicacion" min="1" max="100" value="100"></label>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-link"></i> Asignar</button>
        <span class="text-muted text-sm">Si el recurso ya estaba en esa actividad, se actualiza su dedicación.</span>
        <?php endif; ?>
    </form>
    <?php endif; ?>
    <div class="card">
        <?php if (!$asignaciones): ?><p class="text-muted">Ninguna actividad tiene recursos asignados.</p><?php else: ?>
        <div class="tabla-wrapper"><table class="tabla" <?php echo pag_attr($proyecto); ?>>
            <thead><tr><th>Actividad</th><th class="hide-m">Fechas</th><th>Recurso</th><th>%</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($asignaciones as $a): ?>
                <tr>
                    <td><span class="mono"><?php echo s($a['codigo']); ?></span> <?php echo s($a['actividad']); ?><br><small class="text-muted"><?php echo s($a['partida']); ?></small></td>
                    <td class="mono hide-m"><?php echo formatear_fecha($a['fecha_inicio']); ?> – <?php echo formatear_fecha($a['fecha_fin']); ?></td>
                    <td><?php echo s($a['recurso']); ?></td><td class="mono"><?php echo (int)$a['dedicacion']; ?>%</td>
                    <td class="acciones"><?php if ($puedeEditar): ?>
                        <form method="POST" action="/recursos/quitar" style="display:inline">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="actividad_id" value="<?php echo (int)$a['actividad_id']; ?>"><input type="hidden" name="recurso_id" value="<?php echo (int)$a['recurso_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Quitar de la actividad">✕</button>
                        </form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
