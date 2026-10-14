<div class="programa-header">
    <div class="programa-titulo">
        <?php if ($proyecto->logo): ?>
            <img src="/build/img/logos/<?php echo s($proyecto->logo); ?>" alt="logo" class="programa-logo">
        <?php endif; ?>
        <div>
            <h2><?php echo s($proyecto->nombre); ?></h2>
            <p class="text-muted mono"><?php echo s($proyecto->codigo); ?> · <?php echo s($proyecto->ubicacion); ?></p>
        </div>
    </div>
    <div class="programa-meta">
        <div class="meta-item">
            <span class="meta-label">Inicio</span>
            <span class="mono"><?php echo formatear_fecha($proyecto->fecha_inicio); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Fin</span>
            <span class="mono"><?php echo formatear_fecha($proyecto->fecha_fin); ?></span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Hoy</span>
            <span class="mono text-accent"><?php echo date('d/m/Y'); ?></span>
        </div>
    </div>
</div>

<?php if (!empty($_GET['importado'])): ?>
    <div class="alerta alerta-exito">Programa importado: <?php echo (int)($_GET['partidas'] ?? 0); ?> partida(s) y <?php echo (int)$_GET['importado']; ?> actividad(es).</div>
<?php endif; ?>

<div class="programa-acciones">
    <div>
        <?php if (puede_gestionar_proyecto($proyecto)): ?>
            <a href="/programa/partidas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nueva Partida</a>
            <a href="/programa/actividades/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-secondary">+ Nueva Actividad</a>
            <a href="/programa/importar?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost hide-m"><i class="fa-solid fa-file-import"></i> Importar XML / POD</a>
        <?php endif; ?>
    </div>
    <div class="hide-m">
        <?php if (puede_descargar()): ?>
            <a href="/ajustes?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">⚙ Ajustes & Exportar</a>
        <?php endif; ?>
        <a href="/proyectos" class="btn btn-ghost">← Portafolio</a>
    </div>
</div>

<!-- Tabla de actividades -->
<div class="card">
    <h3 class="card-titulo"><i class="fa-solid fa-list"></i> Desglose WBS & Partidas</h3>
    <div class="tabla-wrapper">
        <table class="tabla tabla-programa">
            <thead>
                <tr>
                    <th class="hide-m">WBS / Código</th>
                    <th><span class="hide-m">Nombre</span><span class="solo-movil">Actividad</span></th>
                    <th><span class="hide-m">Fecha </span>Inicio</th>
                    <th><span class="hide-m">Fecha </span>Fin</th>
                    <th class="hide-m">Duración</th>
                    <th class="hide-m">Progreso</th>
                    <th class="hide-m">Status</th>
                    <th class="hide-m">Prioridad</th>
                    <th class="hide-m">Restricción</th>
                    <?php if (puede_gestionar_proyecto($proyecto)): ?><th class="th-acciones">Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <?php if ($fila['tipo'] === 'partida'): ?>
                        <tr class="fila-partida" <?php if ($fila['color_bg']) echo 'style="background-color: ' . s($fila['color_bg']) . '22; border-left: 3px solid ' . s($fila['color_bg']) . ';"'; ?>>
                            <td class="mono negrita hide-m"><?php echo s($fila['codigo']); ?></td>
                            <td class="negrita"><span class="solo-movil mono text-sm"><?php echo s($fila['codigo']); ?></span> <?php echo s($fila['nombre']); ?></td>
                            <td class="mono"><span class="hide-m"><?php echo formatear_fecha($fila['fecha_inicio']); ?></span><span class="solo-movil"><?php echo fecha_movil($fila['fecha_inicio']); ?></span></td>
                            <td class="mono"><span class="hide-m"><?php echo formatear_fecha($fila['fecha_fin']); ?></span><span class="solo-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span></td>
                            <td class="hide-m">—</td>
                            <td class="hide-m">
                                <div class="progreso-cell">
                                    <div class="barra-progreso barra-sm">
                                        <div class="barra-fill" style="width: <?php echo s($fila['progreso']); ?>%"></div>
                                    </div>
                                    <span class="mono text-sm"><?php echo s($fila['progreso']); ?>%</span>
                                </div>
                            </td>
                            <td class="hide-m">—</td>
                            <td class="hide-m">—</td>
                            <td class="hide-m">—</td>
                            <?php if (puede_gestionar_proyecto($proyecto)): ?>
                                <td class="acciones">
                                    <a href="/programa/actividades/crear?proyecto=<?php echo s($proyecto->id); ?>&partida=<?php echo s($fila['id']); ?>" class="btn-action btn-action-add" title="Agregar actividad">
                                        <i class="fa-solid fa-plus"></i>
                                    </a>
                                    <a href="/programa/partidas/editar?id=<?php echo s($fila['id']); ?>" class="btn-action btn-action-edit" title="Editar partida">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <form method="POST" action="/programa/partidas/eliminar" style="display:inline" data-confirm="¿Eliminar partida?|Se eliminará la partida junto con todas sus actividades. Esta acción no se puede deshacer.">
                                        <input type="hidden" name="id" value="<?php echo s($fila['id']); ?>">
                                        <button type="submit" class="btn-action btn-action-delete" title="Eliminar partida">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php else: ?>
                        <tr class="fila-actividad" data-actividad-id="<?php echo s($fila['id']); ?>">
                            <td class="mono indent hide-m"><?php echo s($fila['codigo']); ?></td>
                            <td class="indent"><span class="solo-movil mono text-sm"><?php echo s($fila['codigo']); ?></span> <?php echo s($fila['nombre']); ?></td>
                            <td class="fecha-editable">
                                <?php if (puede_gestionar_proyecto($proyecto)): ?>
                                    <input type="date"
                                           class="input-fecha fecha-inicio-input hide-m"
                                           value="<?php echo s($fila['fecha_inicio']); ?>"
                                           data-actividad-id="<?php echo s($fila['id']); ?>"
                                           data-tipo="inicio">
                                    <span class="solo-movil mono"><?php echo fecha_movil($fila['fecha_inicio']); ?></span>
                                <?php else: ?>
                                    <span class="mono hide-m"><?php echo formatear_fecha($fila['fecha_inicio']); ?></span><span class="mono solo-movil"><?php echo fecha_movil($fila['fecha_inicio']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fecha-editable">
                                <?php if (puede_gestionar_proyecto($proyecto)): ?>
                                    <input type="date"
                                           class="input-fecha fecha-fin-input hide-m"
                                           value="<?php echo s($fila['fecha_fin']); ?>"
                                           data-actividad-id="<?php echo s($fila['id']); ?>"
                                           data-tipo="fin">
                                    <span class="solo-movil mono"><?php echo fecha_movil($fila['fecha_fin']); ?></span>
                                <?php else: ?>
                                    <span class="mono hide-m"><?php echo formatear_fecha($fila['fecha_fin']); ?></span><span class="mono solo-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php
                                $dur = $calendario->calcularDuracion($fila['fecha_inicio'], $fila['fecha_fin']);
                            ?>
                            <td class="mono duracion-cell hide-m"><?php echo !empty($fila['hito']) ? 'Hito' : s(number_format($dur, 1)) . ' d'; ?></td>
                            <td class="hide-m">
                                <div class="progreso-cell">
                                    <div class="barra-progreso barra-sm barra-status-<?php echo s($fila['status']); ?>">
                                        <div class="barra-fill" style="width: <?php echo s($fila['progreso']); ?>%"></div>
                                    </div>
                                    <span class="mono text-sm"><?php echo s($fila['progreso']); ?>%</span>
                                </div>
                            </td>
                            <td class="hide-m">
                                <span class="badge-status status-<?php echo s($fila['status']); ?>">
                                    <?php
                                    echo match($fila['status']) {
                                        'completada' => '<i class="fa-solid fa-check-circle"></i> Completada',
                                        'en_proceso' => '<i class="fa-solid fa-spinner"></i> En Proceso',
                                        default => '<i class="fa-solid fa-circle"></i> No Iniciada'
                                    };
                                    ?>
                                </span>
                            </td>
                            <td class="hide-m"><span class="badge-prio prio-<?php echo s($fila['prioridad']); ?>"><?php echo s(ucfirst($fila['prioridad'])); ?></span></td>
                            <td class="hide-m">
                                <?php if ($fila['tiene_restriccion']): ?>
                                    <div class="restricciones-lista">
                                        <?php foreach ($fila['restricciones'] as $rest): ?>
                                            <?php $tipoRest = $rest->getTipo(); ?>
                                            <span class="badge-restriccion"
                                                  title="<?php echo s($rest->descripcion ?: 'Sin descripción'); ?><?php echo $rest->fecha ? ' · ' . formatear_fecha($rest->fecha) : ''; ?>">
                                                <i class="fa-solid fa-circle-exclamation"></i>
                                                <?php echo s($rest->codigo ?: ($tipoRest ? $tipoRest->nombre : 'Restricción')); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-dim">—</span>
                                <?php endif; ?>
                            </td>
                            <?php if (puede_gestionar_proyecto($proyecto)): ?>
                                <td class="acciones">
                                    <button type="button" class="btn-action btn-action-edit btn-fechas solo-movil" title="Cambiar fechas"
                                            data-id="<?php echo s($fila['id']); ?>" data-nombre="<?php echo s($fila['nombre']); ?>"
                                            data-inicio="<?php echo s($fila['fecha_inicio']); ?>" data-fin="<?php echo s($fila['fecha_fin']); ?>">
                                        <i class="fa-regular fa-calendar-days"></i>
                                    </button>
                                    <a href="/programa/restricciones/crear?actividad=<?php echo s($fila['id']); ?>" class="btn-action btn-action-warning" title="Agregar restricción">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                    </a>
                                    <a href="/programa/actividades/editar?id=<?php echo s($fila['id']); ?>" class="btn-action btn-action-edit" title="Editar actividad">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <form method="POST" action="/programa/actividades/eliminar" style="display:inline" data-confirm="¿Eliminar actividad?|Se eliminará la actividad y sus restricciones asociadas. Esta acción no se puede deshacer.">
                                        <input type="hidden" name="id" value="<?php echo s($fila['id']); ?>">
                                        <button type="submit" class="btn-action btn-action-delete" title="Eliminar actividad">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty($filas)): ?>
                    <tr><td colspan="10" class="text-center text-muted">No hay partidas ni actividades. <?php if (puede_gestionar_proyecto($proyecto)): ?>Comienza creando una partida.<?php endif; ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Gantt (no se muestra en móvil) -->
<div class="card hide-m">
    <div class="gantt-header">
        <h3 class="card-titulo">Diagrama de Gantt por Semanas</h3>
        <div class="gantt-leyenda">
            <span><span class="dot dot-completado"></span> Completado</span>
            <span><span class="dot dot-proceso"></span> En Proceso</span>
            <span><span class="dot dot-noiniciado"></span> No Iniciado</span>
            <span><span class="dot dot-restriccion"></span> Restricción</span>
        </div>
    </div>

    <div class="gantt-wrapper">
        <div class="gantt-grid" style="grid-template-columns: 18rem repeat(<?php echo count($semanas); ?>, minmax(4rem, 1fr));">
            <!-- Header -->
            <div class="gantt-cell gantt-header-cell">Actividad / ID</div>
            <?php foreach ($semanas as $semana): ?>
                <div class="gantt-cell gantt-header-cell">
                    <div class="semana-num mono">S<?php echo s($semana['numero']); ?></div>
                    <div class="semana-rango"><?php echo s($semana['inicio_fmt']); ?></div>
                </div>
            <?php endforeach; ?>

            <!-- Filas -->
            <?php
            $totalSemanas = count($semanas);
            foreach ($filas as $fila):
                [$colI, $colF] = $fila['pos'];
                $span = $colF - $colI + 1;
            ?>
                <!-- Columna etiqueta -->
                <div class="gantt-cell gantt-label <?php echo $fila['tipo'] === 'partida' ? 'label-partida' : 'label-actividad'; ?>"
                     <?php if ($fila['tipo'] === 'partida' && $fila['color_bg']) echo 'style="border-left: 3px solid ' . s($fila['color_bg']) . ';"'; ?>>
                    <span class="mono text-sm"><?php echo s($fila['codigo']); ?></span>
                    <span class="gantt-label-nombre"><?php echo s($fila['nombre']); ?></span>
                </div>

                <!-- Spacer antes de la barra -->
                <?php if ($colI > 1): ?>
                    <div class="gantt-cell gantt-bar-cell" style="grid-column: span <?php echo ($colI - 1); ?>;"></div>
                <?php endif; ?>

                <!-- Celda con la barra: ocupa las columnas de su duración -->
                <div class="gantt-cell gantt-bar-cell" style="grid-column: span <?php echo $span; ?>;">
                    <div class="gantt-barra <?php echo $fila['tipo']; ?> <?php echo !empty($fila['hito']) ? 'gantt-hito' : ''; ?> status-<?php echo $fila['tipo'] === 'partida' ? 'partida' : s($fila['status']); ?>"
                         title="<?php echo s($fila['codigo'] . ' — ' . $fila['nombre']); ?> | <?php echo formatear_fecha($fila['fecha_inicio']); ?> → <?php echo formatear_fecha($fila['fecha_fin']); ?> | <?php echo s($fila['progreso']); ?>%">
                        <?php if ($fila['tipo'] === 'actividad'): ?>
                            <div class="gantt-barra-fill" style="width: <?php echo s($fila['progreso']); ?>%"></div>
                            <?php if ($fila['tiene_restriccion']): ?>
                                <span class="gantt-restriccion-dot"></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span class="gantt-barra-texto mono"><?php echo s($fila['progreso']); ?>%</span>
                    </div>
                </div>

                <!-- Spacer después de la barra -->
                <?php if ($colF < $totalSemanas): ?>
                    <div class="gantt-cell gantt-bar-cell" style="grid-column: span <?php echo ($totalSemanas - $colF); ?>;"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if (puede_gestionar_proyecto($proyecto)): ?>
<!-- Cambio de fechas desde el icono (modo móvil) -->
<div class="modal-overlay" id="modal-fechas" aria-hidden="true">
    <div class="modal-card modal-fechas-caja" role="dialog" aria-modal="true" aria-labelledby="mf-titulo">
        <h4 id="mf-titulo">Cambiar fechas</h4>
        <p class="text-muted text-sm" id="mf-actividad"></p>
        <div class="formulario">
            <div class="campo"><label for="mf-inicio">Fecha de inicio</label><input type="date" id="mf-inicio"></div>
            <div class="campo"><label for="mf-fin">Fecha de fin</label><input type="date" id="mf-fin"></div>
        </div>
        <p class="text-sm mf-error" id="mf-error" hidden></p>
        <div class="mf-botones">
            <button type="button" class="btn btn-ghost" id="mf-cancelar">Cancelar</button>
            <button type="button" class="btn btn-primary" id="mf-guardar">Guardar</button>
        </div>
    </div>
</div>
<script>
(function () {
    const modal = document.getElementById('modal-fechas');
    const ini = document.getElementById('mf-inicio'), fin = document.getElementById('mf-fin'), err = document.getElementById('mf-error');
    let actual = null;
    const cerrar = () => { modal.classList.remove('abierto'); modal.setAttribute('aria-hidden', 'true'); };
    document.querySelectorAll('.btn-fechas').forEach(b => b.addEventListener('click', () => {
        actual = b.dataset;
        document.getElementById('mf-actividad').textContent = b.dataset.nombre;
        ini.value = b.dataset.inicio; fin.value = b.dataset.fin; err.hidden = true;
        modal.classList.add('abierto'); modal.setAttribute('aria-hidden', 'false');
    }));
    document.getElementById('mf-cancelar').addEventListener('click', cerrar);
    modal.addEventListener('click', e => { if (e.target === modal) cerrar(); });
    const enviar = async (tipo, fecha) => {
        const fd = new FormData();
        fd.append('actividad_id', actual.id); fd.append('tipo', tipo); fd.append('fecha', fecha);
        const r = await fetch('/api/actividad/actualizar-fecha', { method: 'POST', body: fd });
        return r.json();
    };
    document.getElementById('mf-guardar').addEventListener('click', async () => {
        err.hidden = true;
        if (!ini.value || !fin.value || fin.value < ini.value) { err.textContent = 'La fecha de fin no puede ser anterior a la de inicio.'; err.hidden = false; return; }
        try {
            // Primero el inicio (la API mueve el fin con la misma duración) y después el fin definitivo
            if (ini.value !== actual.inicio) { const d = await enviar('inicio', ini.value); if (!d.success) throw new Error(d.error); }
            const d2 = await enviar('fin', fin.value); if (!d2.success) throw new Error(d2.error);
            location.reload();
        } catch (e) { err.textContent = e.message || 'No se pudo guardar'; err.hidden = false; }
    });
})();
</script>
<?php endif; ?>
