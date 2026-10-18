<?php $pid = s($proyecto->id); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-timeline"></i> Líneas base del programa</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · una línea base es una foto inmutable del programa (fechas, ruta crítica y fechas de materiales) para comparar cómo cambia con el tiempo</p>
    </div>
    <a href="/cambios?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← Órdenes de cambio</a>
</div>
<?php if ($mensaje): ?><div class="alerta <?php echo $mensaje['ok'] ? 'exito' : 'error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>
<?php if ($puede): ?>
<div class="card">
    <h3><?php echo $lineas ? 'Guardar una nueva línea base' : 'Guardar el programa inicial'; ?></h3>
    <form method="POST" action="/lineas-base/capturar" class="formulario">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <div class="form-grid">
            <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" maxlength="150" placeholder="<?php echo $lineas ? 'Ej.: LB1 tras la orden de cambio 3' : 'Programa inicial'; ?>"></div>
            <div class="campo"><label for="notas">Notas</label><input type="text" id="notas" name="notas" maxlength="500"></div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-camera"></i> Guardar línea base con el programa de hoy</button>
    </form>
</div>
<?php endif; ?>
<div class="card">
    <?php if (empty($lineas)): ?>
        <p class="text-muted">Todavía no hay líneas base. Guarda la primera cuando el programa esté aprobado.</p>
    <?php else: ?>
        <table class="tabla">
            <thead><tr><th>Nombre</th><th>Guardada</th><th class="hide-m">Por</th><th>Actividades</th><th>Fin programado</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($lineas as $l): ?>
                <tr>
                    <td><a href="/lineas-base/comparar?proyecto=<?php echo $pid; ?>&id=<?php echo s($l->id); ?>"><?php echo s($l->nombre); ?></a><?php echo $l->notas ? '<br><small class="text-muted">' . s($l->notas) . '</small>' : ''; ?></td>
                    <td class="mono"><?php echo formatear_fecha(substr((string)$l->creado, 0, 10)); ?></td>
                    <td class="hide-m"><?php echo s($l->creado_por); ?></td>
                    <td class="mono"><?php echo (int)$l->total_actividades; ?></td>
                    <td class="mono"><?php echo $l->fin_programado ? formatear_fecha($l->fin_programado) : '—'; ?></td>
                    <td class="acciones">
                        <a href="/lineas-base/comparar?proyecto=<?php echo $pid; ?>&id=<?php echo s($l->id); ?>" class="btn btn-sm btn-primary">Comparar con hoy</a>
                        <?php if ($puede): ?>
                        <form method="POST" action="/lineas-base/eliminar" style="display:inline" data-confirm="¿Eliminar la línea base?|Se pierde la foto del programa; no se puede recuperar.">
                            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo s($l->id); ?>">
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
