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

<?php foreach (array_slice(\Classes\DatosObra::alertas((int)$proyecto->id), 0, 3) as $al): ?>
    <div class="alerta <?php echo $al['nivel'] === 'critica' ? 'alerta-error' : 'alerta-aviso'; ?>"><i class="fa-solid fa-file-contract"></i> <?php echo s($al['texto']); ?> <a href="/datos-obra?proyecto=<?php echo s($proyecto->id); ?>">Ver contrato</a></div>
<?php endforeach; ?>

<div class="programa-acciones">
    <div>
        <?php if (puede_gestionar_proyecto($proyecto)): ?>
            <a href="/programa/partidas/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-primary">+ Nueva Partida</a>
            <a href="/programa/actividades/crear?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-secondary">+ Nueva Actividad</a>
            <a href="/programa/ordenar?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost hide-m"><i class="fa-solid fa-arrow-down-1-9"></i> Ordenar y renumerar</a>
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

<?php if (is_admin()): $pg = $propuestasGuardadas ?? []; ?>
    <details class="card prg-guardadas"<?php echo $pg ? ' open' : ''; ?>>
        <summary><i class="fa-solid fa-wand-magic-sparkles"></i> Propuestas del Programador de actividades (<?php echo count($pg); ?> guardada<?php echo count($pg) === 1 ? '' : 's'; ?>)</summary>
        <?php if ($pg): ?>
            <ul>
                <?php foreach ($pg as $g): ?>
                    <li>
                        <a href="/programador?proyecto=<?php echo (int)$proyecto->id; ?>&propuesta=<?php echo (int)$g['id']; ?>"><strong><?php echo s($g['nombre'] ?: 'Propuesta ' . date('d/m/Y', strtotime($g['creado']))); ?></strong></a>
                        <small class="text-muted"><?php echo s(date('d/m/Y H:i', strtotime($g['creado']))); ?> · fin <?php echo s(formatear_fecha($g['fin_propuesto'])); ?> (<?php echo $g['dif'] > 0 ? '+' : ''; ?><?php echo $g['dif']; ?> d háb.) · <?php echo $g['movidas']; ?> cambian · <?php echo $g['obstaculos']; ?> obstáculos · <?php echo $g['mejoras']; ?> mejoras</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">Aún no hay propuestas guardadas. Al analizar el proyecto con la IA, guarda la que te interese desde el Programador.</p>
        <?php endif; ?>
        <a href="/programador?proyecto=<?php echo (int)$proyecto->id; ?>" class="btn btn-sm btn-ghost">Abrir el Programador</a>
    </details>
<?php endif; ?>

<form method="GET" action="/programa" class="card programa-filtros formulario">
    <input type="hidden" name="proyecto" value="<?php echo s($proyecto->id); ?>">
    <div class="form-grid-inline">
        <div class="campo">
            <label for="f-partida">Partida</label>
            <select id="f-partida" name="partida">
                <option value="">Todas</option>
                <?php foreach ($partidasFiltro as $p): ?>
                    <option value="<?php echo s($p['id']); ?>"<?php echo (int)$filtros['partida'] === (int)$p['id'] ? ' selected' : ''; ?>><?php echo s($p['codigo'] . ' · ' . $p['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="f-status">Estatus</label>
            <select id="f-status" name="status">
                <option value="">Todos</option>
                <?php foreach (['no_iniciada' => 'No iniciada', 'en_proceso' => 'En proceso', 'completada' => 'Completada'] as $k => $etq): ?>
                    <option value="<?php echo $k; ?>"<?php echo $filtros['status'] === $k ? ' selected' : ''; ?>><?php echo $etq; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="f-prioridad">Prioridad</label>
            <select id="f-prioridad" name="prioridad">
                <option value="">Todas</option>
                <?php foreach (['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'] as $k => $etq): ?>
                    <option value="<?php echo $k; ?>"<?php echo $filtros['prioridad'] === $k ? ' selected' : ''; ?>><?php echo $etq; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="campo">
            <label for="f-restriccion">Restricción</label>
            <select id="f-restriccion" name="restriccion">
                <option value="">Todas</option>
                <option value="con"<?php echo $filtros['restriccion'] === 'con' ? ' selected' : ''; ?>>Con restricción activa</option>
                <option value="sin"<?php echo $filtros['restriccion'] === 'sin' ? ' selected' : ''; ?>>Sin restricción</option>
            </select>
        </div>
        <?php if ($hayFiltros): ?><a href="/programa?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost">Limpiar</a><?php endif; ?>
    </div>
</form>
<script>document.querySelectorAll('.programa-filtros select').forEach(s => s.addEventListener('change', () => s.form.submit()));</script>

<!-- Tabla de actividades -->
<div class="card">
    <h3 class="card-titulo"><i class="fa-solid fa-list"></i> Desglose WBS & Partidas</h3>
    <?php $puedeGestionar = puede_gestionar_proyecto($proyecto); ?>
    <?php if ($puedeGestionar): ?>
    <form method="POST" action="/programa/eliminar-varias" id="form-seleccion" class="barra-seleccion" hidden>
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <span class="barra-seleccion-n"><b id="sel-n">0</b> seleccionadas</span>
        <button type="button" class="btn btn-sm btn-ghost" id="sel-limpiar">Quitar selección</button>
        <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> Eliminar seleccionadas</button>
        <div id="sel-campos"></div>
    </form>
    <?php endif; ?>
    <div class="tabla-wrapper">
        <?php
        // Posición de cada actividad dentro de su partida (sobre el programa completo, aunque haya filtros) para los botones subir/bajar
        $posAct = [];
        if ($puedeGestionar) foreach (\Classes\Renumeracion::estructura((int)$proyecto->id) as $pp) foreach ($pp['actividades'] as $k => $aa) $posAct[$aa['id']] = [$k, count($pp['actividades'])];
        $colsTabla = $puedeGestionar ? 11 : 9;
        $partidaPrevia = null;
        $filaRapida = function (array $p) use ($proyecto, $colsTabla) { ?>
                    <tr class="fila-rapida">
                        <td colspan="<?php echo (int)$colsTabla; ?>">
                            <form method="POST" action="/programa/actividades/rapida" class="form-rapida">
                                <input type="hidden" name="partida" value="<?php echo (int)$p['id']; ?>">
                                <i class="fa-solid fa-plus"></i>
                                <input type="text" name="nombre" maxlength="200" placeholder="Agregar actividad a <?php echo s($p['codigo']); ?> · <?php echo s($p['nombre']); ?> (nombre y Enter)" aria-label="Nombre de la nueva actividad" required>
                                <input type="date" name="fecha_inicio" title="Inicio (vacío = día hábil siguiente a la última actividad)" aria-label="Inicio">
                                <input type="number" name="duracion_dias" min="1" value="1" title="Duración en días hábiles" aria-label="Duración en días">
                                <input type="number" name="progreso" min="0" max="100" value="0" title="Avance inicial (%) — vacío = 0%" aria-label="Avance inicial en %" class="input-avance">
                                <button type="submit" class="btn btn-sm">Agregar</button>
                            </form>
                        </td>
                    </tr>
        <?php };
        $revAct = array_column(\Classes\Revisiones::elementos((int)$proyecto->id, 'actividad'), null, 'ref'); $puedeRev = puede_revisar($proyecto); ?>
        <table class="tabla tabla-programa" data-proyecto="<?php echo (int)$proyecto->id; ?>">
            <thead>
                <tr>
                    <?php if ($puedeGestionar): ?><th class="th-sel"><input type="checkbox" id="sel-todo" title="Seleccionar todo" aria-label="Seleccionar todo"></th><?php endif; ?>
                    <th class="hide-m">WBS / Código</th>
                    <th><span class="hide-m">Nombre</span><span class="solo-movil">Actividad</span></th>
                    <th class="col-fecha"><span class="hide-m">Fecha </span>Inicio</th>
                    <th class="col-fecha"><span class="hide-m">Fecha </span>Fin</th>
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
                    <?php if ($puedeGestionar && $partidaPrevia) $filaRapida($partidaPrevia); $partidaPrevia = $fila; ?>
                        <tr class="fila-partida" id="fila-p<?php echo (int)$fila['id']; ?>" <?php if ($fila['color_bg']) echo 'style="background-color: ' . s($fila['color_bg']) . '22; border-left: 3px solid ' . s($fila['color_bg']) . ';"'; ?>>
                            <?php if ($puedeGestionar): ?><td class="td-sel"><input type="checkbox" class="sel-fila" value="p<?php echo (int)$fila['id']; ?>" aria-label="Seleccionar partida"></td><?php endif; ?>
                            <td class="mono negrita hide-m"><?php echo s($fila['codigo']); ?></td>
                            <td class="negrita"><span class="solo-movil mono text-sm"><?php echo s($fila['codigo']); ?></span> <?php echo s($fila['nombre']); ?></td>
                            <td class="mono col-fecha"><span class="hide-m"><?php echo formatear_fecha($fila['fecha_inicio']); ?></span><span class="solo-movil"><?php echo fecha_movil($fila['fecha_inicio']); ?></span></td>
                            <td class="mono col-fecha"><span class="hide-m"><?php echo formatear_fecha($fila['fecha_fin']); ?></span><span class="solo-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span></td>
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
                        <tr class="fila-actividad" id="fila-a<?php echo (int)$fila['id']; ?>" data-actividad-id="<?php echo s($fila['id']); ?>">
                            <?php if ($puedeGestionar): ?><td class="td-sel"><input type="checkbox" class="sel-fila" value="a<?php echo (int)$fila['id']; ?>" aria-label="Seleccionar actividad"></td><?php endif; ?>
                            <td class="mono indent hide-m"><?php echo s($fila['codigo']); ?></td>
                            <td class="indent"><span class="solo-movil mono text-sm"><?php echo s($fila['codigo']); ?></span> <?php echo s($fila['nombre']); ?> <?php if (isset($revAct[(string)$fila['id']])) echo \Classes\Revisiones::chip('actividad', (string)$fila['id'], $revAct[(string)$fila['id']], $puedeRev, 'Cumplimiento'); ?></td>
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
                                    <?php if (puede_gestionar_proyecto($proyecto)): ?>
                                        <input type="number" class="input-avance input-progreso" min="0" max="100" step="1"
                                               value="<?php echo (int)$fila['progreso']; ?>" data-actividad-id="<?php echo s($fila['id']); ?>"
                                               aria-label="Avance (%)" title="Avance (%)">%
                                    <?php else: ?>
                                        <span class="mono text-sm"><?php echo s($fila['progreso']); ?>%</span>
                                    <?php endif; ?>
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
                                    <?php [$posI, $posN] = $posAct[$fila['id']] ?? [0, 1]; ?>
                                    <form method="POST" action="/programa/actividades/mover" style="display:inline" class="form-mover">
                                        <input type="hidden" name="id" value="<?php echo s($fila['id']); ?>">
                                        <button type="submit" name="dir" value="arriba" class="btn-action btn-action-move" title="Subir (renumera la partida)"<?php echo $posI <= 0 ? ' disabled' : ''; ?>><i class="fa-solid fa-arrow-up"></i></button>
                                        <button type="submit" name="dir" value="abajo" class="btn-action btn-action-move" title="Bajar (renumera la partida)"<?php echo $posI >= $posN - 1 ? ' disabled' : ''; ?>><i class="fa-solid fa-arrow-down"></i></button>
                                    </form>
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
                <?php if ($puedeGestionar && $partidaPrevia) $filaRapida($partidaPrevia); ?>
                <?php if (empty($filas)): ?>
                    <tr><td colspan="11" class="text-center text-muted"><?php echo $hayFiltros ? 'Ninguna actividad coincide con los filtros.' : 'No hay partidas ni actividades.'; ?> <?php if (!$hayFiltros && puede_gestionar_proyecto($proyecto)): ?>Comienza creando una partida.<?php endif; ?></td></tr>
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
            <?php if (!empty($depFlechas)): ?>
                <label class="campo-check gantt-dep-toggle"><input type="checkbox" id="gantt-dep-toggle" checked> <i class="fa-solid fa-diagram-project"></i> Dependencias</label>
            <?php endif; ?>
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
                    <div<?php echo $fila['tipo'] === 'actividad' ? ' data-act="' . (int)$fila['id'] . '"' : ''; ?> class="gantt-barra <?php echo $fila['tipo']; ?> <?php echo !empty($fila['hito']) ? 'gantt-hito' : ''; ?> status-<?php echo $fila['tipo'] === 'partida' ? 'partida' : s($fila['status']); ?>"
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

<?php if (!empty($depFlechas)): ?>
<script>
(function () {
    const rels = <?php echo json_encode($depFlechas, JSON_HEX_TAG); ?>;
    const grid = document.querySelector('.gantt-grid'), chk = document.getElementById('gantt-dep-toggle');
    if (!grid) return;
    const NS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(NS, 'svg'); svg.setAttribute('class', 'gantt-deps'); grid.appendChild(svg);
    function dibujar() {
        svg.innerHTML = '';
        if (chk && !chk.checked) return;
        const g = grid.getBoundingClientRect();
        svg.setAttribute('width', grid.scrollWidth); svg.setAttribute('height', grid.scrollHeight);
        const caja = id => { const el = grid.querySelector('.gantt-barra[data-act="' + id + '"]'); if (!el) return null; const r = el.getBoundingClientRect(); return { l: r.left - g.left, r: r.right - g.left, y: r.top - g.top + r.height / 2, h: r.height }; };
        let out = '';
        rels.forEach(r => {
            const A = caja(r.pred), B = caja(r.suc); if (!A || !B) return;
            const salidaFin = r.tipo === 'FS' || r.tipo === 'FF', entraInicio = r.tipo === 'FS' || r.tipo === 'SS';
            const x1 = salidaFin ? A.r : A.l, x2 = entraInicio ? B.l : B.r, d1 = salidaFin ? 1 : -1, d2 = entraInicio ? 1 : -1;
            const ym = A.y < B.y ? A.y + (B.y - A.y) / 2 : A.y - (A.y - B.y) / 2;
            const pts = [[x1, A.y], [x1 + d1 * 7, A.y], [x1 + d1 * 7, ym], [x2 - d2 * 7, ym], [x2 - d2 * 7, B.y], [x2, B.y]];
            const cls = (r.critica ? ' crit' : '') + (r.conflicto ? ' conf' : '');
            out += '<path class="gantt-dep' + cls + '" d="M' + pts.map(p => p.join(',')).join(' L') + '"/><path class="gantt-dep-punta' + cls + '" d="M' + x2 + ',' + B.y + ' l' + (-d2 * 6) + ',-3.5 l0,7 z"/>';
        });
        svg.innerHTML = out;
    }
    if (chk) chk.addEventListener('change', dibujar);
    window.addEventListener('resize', dibujar);
    if (window.ResizeObserver) new ResizeObserver(dibujar).observe(grid);
    dibujar(); window.addEventListener('load', dibujar);
})();
</script>
<?php endif; ?>

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
<script>
// Tras crear/editar una partida o actividad, lleva la pantalla hasta su fila
(function () {
    const m = /^([pa])(\d+)$/.exec(new URLSearchParams(location.search).get('foco') || '');
    if (!m) return;
    const fila = document.getElementById('fila-' + m[1] + m[2]);
    if (fila) {
        fila.scrollIntoView({ block: 'center' });
        fila.classList.add('fila-foco');
    }
    const url = new URL(location.href);
    url.searchParams.delete('foco');
    history.replaceState(null, '', url);
})();
</script>
<?php if ($puedeGestionar): ?>
<script>
(function () {
    const form = document.getElementById('form-seleccion');
    if (!form) return;
    const filas = [...document.querySelectorAll('.sel-fila')];
    const todo = document.getElementById('sel-todo');
    const cuenta = document.getElementById('sel-n');
    const campos = document.getElementById('sel-campos');
    const marcadas = () => filas.filter(c => c.checked);
    const refrescar = () => {
        const m = marcadas();
        cuenta.textContent = m.length;
        form.hidden = m.length === 0;
        todo.checked = m.length > 0 && m.length === filas.length;
        todo.indeterminate = m.length > 0 && m.length < filas.length;
        filas.forEach(c => c.closest('tr').classList.toggle('fila-seleccionada', c.checked));
    };
    filas.forEach(c => c.addEventListener('change', refrescar));
    todo.addEventListener('change', () => { filas.forEach(c => { c.checked = todo.checked; }); refrescar(); });
    document.getElementById('sel-limpiar').addEventListener('click', () => { filas.forEach(c => { c.checked = false; }); refrescar(); });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const m = marcadas();
        const np = m.filter(c => c.value[0] === 'p').length, na = m.length - np;
        const partes = [];
        if (np) partes.push(np + (np === 1 ? ' partida (con todas sus actividades)' : ' partidas (con todas sus actividades)'));
        if (na) partes.push(na + (na === 1 ? ' actividad' : ' actividades'));
        const ok = await window.confirmarModal({ titulo: '¿Eliminar selección?', mensaje: 'Se eliminarán ' + partes.join(' y ') + ' y sus restricciones asociadas. Esta acción no se puede deshacer.' });
        if (!ok) return;
        campos.innerHTML = '';
        m.forEach(c => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'sel[]'; i.value = c.value; campos.appendChild(i); });
        form.submit();
    });
    refrescar();
})();
</script>
<?php endif; ?>
