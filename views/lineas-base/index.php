<?php
$pid = s($proyecto->id);
$nLineas = count($lineas);
$ultima = $lineas[0] ?? null;
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-timeline"></i> Líneas base del programa</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · una línea base es una foto inmutable del programa (fechas, ruta crítica y fechas de materiales) para comparar cómo cambia con el tiempo.</p>
        <div class="herr-meta">
            <span class="herr-chip"><i class="fa-solid fa-camera"></i><b><?php echo $nLineas; ?></b> línea<?php echo $nLineas === 1 ? '' : 's'; ?> base</span>
            <?php if ($ultima): ?><span class="herr-chip herr-chip-suave"><i class="fa-solid fa-clock-rotate-left"></i> última: <b><?php echo s($ultima->nombre); ?></b></span><?php endif; ?>
            <?php if ($ultima && $ultima->fin_programado): ?><span class="herr-chip herr-chip-suave"><i class="fa-solid fa-flag-checkered"></i> fin <b><?php echo formatear_fecha($ultima->fin_programado); ?></b></span><?php endif; ?>
        </div>
    </div>
    <a href="/cambios?proyecto=<?php echo $pid; ?>" class="btn btn-ghost"><i class="fa-solid fa-code-compare"></i> Órdenes de cambio</a>
</div>

<?php if ($mensaje): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>" role="status"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="suite-kpis">
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-camera" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Líneas base</small><strong><?php echo $nLineas; ?></strong><span>fotos guardadas del programa</span></span>
    </div>
    <div class="suite-kpi is-info">
        <span class="suite-kpi-icono"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Actividades fotografiadas</small><strong><?php echo $ultima ? (int)$ultima->total_actividades : 0; ?></strong><span><?php echo $ultima ? 'en la línea más reciente' : 'aún sin capturar'; ?></span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Fin programado</small><strong><?php echo $ultima && $ultima->fin_programado ? formatear_fecha($ultima->fin_programado) : '—'; ?></strong><span>de la última línea base</span></span>
    </div>
    <div class="suite-kpi">
        <span class="suite-kpi-icono"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
        <span class="suite-kpi-txt"><small>Última captura</small><strong><?php echo $ultima ? formatear_fecha(substr((string)$ultima->creado, 0, 10)) : '—'; ?></strong><span><?php echo $ultima ? s($ultima->creado_por ?: 'sin usuario') : 'sin líneas base'; ?></span></span>
    </div>
</div>

<?php if ($puede): ?>
<div class="suite-panel lb-cap">
    <div class="suite-panel-cab">
        <div>
            <h3><i class="fa-solid fa-camera"></i> <?php echo $lineas ? 'Guardar una nueva línea base' : 'Guardar el programa inicial'; ?></h3>
            <p class="text-muted text-sm">La foto conserva fechas, ruta crítica y fechas de materiales del programa actual.</p>
        </div>
        <span class="suite-chip is-primary"><i class="fa-solid fa-lock"></i> Inmutable</span>
    </div>
    <div class="suite-panel-cuerpo">
        <form method="POST" action="/lineas-base/capturar" class="formulario suite-form">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <div class="lb-cap-form">
                <div class="campo"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" maxlength="150" placeholder="<?php echo $lineas ? 'Ej.: LB1 tras la orden de cambio 3' : 'Programa inicial'; ?>"></div>
                <div class="campo"><label for="notas">Notas</label><input type="text" id="notas" name="notas" maxlength="500" placeholder="Qué cambió y por qué"></div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-camera"></i> Guardar línea base de hoy</button>
            </div>
        </form>
        <div class="lb-cap-pie">
            <p class="lb-cap-nota"><i class="fa-solid fa-circle-info"></i> Se guarda una foto del programa tal como está hoy. Comparar no modifica ninguna fecha.</p>
            <a href="/cambios?proyecto=<?php echo $pid; ?>" class="btn btn-sm btn-ghost"><i class="fa-solid fa-code-compare"></i> Ver órdenes de cambio</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="suite-panel lb-lista">
    <div class="lb-tabla-cab">
        <h3><i class="fa-solid fa-layer-group"></i> Líneas base guardadas <span class="suite-chip"><?php echo $nLineas; ?></span></h3>
        <span class="text-muted text-sm">Abre una para comparar la foto contra el programa de hoy.</span>
    </div>
    <div class="suite-panel-cuerpo">
        <?php if (empty($lineas)): ?>
            <?php echo estado_vacio('fa-timeline', 'Todavía no hay líneas base', 'Guarda la primera cuando el programa esté aprobado; después podrás comparar cada cambio contra esa foto.', $puede ? ['texto' => 'Guardar programa inicial', 'url' => '#nombre', 'icono' => 'fa-camera'] : null); ?>
        <?php else: ?>
            <div class="tabla-wrapper">
                <table class="tabla">
                    <thead><tr><th>Nombre</th><th>Guardada</th><th class="hide-m">Por</th><th class="num">Actividades</th><th>Fin programado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($lineas as $l): ?>
                        <tr>
                            <td><div class="lb-nombre"><strong><a href="/lineas-base/comparar?proyecto=<?php echo $pid; ?>&id=<?php echo s($l->id); ?>"><?php echo s($l->nombre); ?></a></strong><?php echo $l->notas ? '<small class="text-muted">' . s($l->notas) . '</small>' : ''; ?></div></td>
                            <td class="mono"><?php echo formatear_fecha(substr((string)$l->creado, 0, 10)); ?></td>
                            <td class="hide-m"><?php echo s($l->creado_por); ?></td>
                            <td class="mono num"><?php echo (int)$l->total_actividades; ?></td>
                            <td class="mono lb-fin"><?php echo $l->fin_programado ? formatear_fecha($l->fin_programado) : '—'; ?></td>
                            <td>
                                <div class="lb-acciones">
                                    <a href="/lineas-base/comparar?proyecto=<?php echo $pid; ?>&id=<?php echo s($l->id); ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-code-compare"></i> Comparar</a>
                                    <?php if ($puede): ?>
                                    <form class="inline" method="POST" action="/lineas-base/eliminar" data-confirm="¿Eliminar la línea base?|Se pierde la foto del programa; no se puede recuperar.">
                                        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="id" value="<?php echo s($l->id); ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost" title="Quitar" aria-label="Quitar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
