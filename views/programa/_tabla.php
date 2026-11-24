<?php
$estadoMovil = function ($st) {
    return match ((string)$st) {
        'completada' => '<i class="fa-solid fa-check-circle"></i> Completada',
        'en_proceso' => '<i class="fa-solid fa-spinner"></i> En proceso',
        default => '<i class="fa-solid fa-circle"></i> No iniciada',
    };
};
?>
<div class="card prg-tabla-card">
    <div class="prg-card-cab">
        <div>
            <h3 class="card-titulo"><i class="fa-solid fa-list-tree"></i> <?php echo s(t('Desglose WBS')); ?></h3>
            <p class="prg-card-sub">
                <?php echo (int)$resumen['partidas']; ?> <?php echo s(t('partidas')); ?> ·
                <?php echo (int)$resumen['actividades']; ?> <?php echo s(t('actividades')); ?><?php if (!empty($resumen['subactividades'])): ?> ·
                <?php echo (int)$resumen['subactividades']; ?> <?php echo s(t('subactividades')); ?><?php endif; ?>
                <?php if ($hayFiltros): ?><span class="prg-filtrado">· <?php echo s(t('filtrado')); ?></span><?php endif; ?>
            </p>
        </div>
        <div class="prg-card-acciones">
            <a class="prg-switch<?php echo $mostrarSub ? ' on' : ''; ?>" href="<?php echo s($prgUrlCon(['sub' => $mostrarSub ? 0 : 1], ['foco'])); ?>" role="switch" aria-checked="<?php echo $mostrarSub ? 'true' : 'false'; ?>">
                <span class="prg-switch-pista"><span></span></span> <?php echo s(t('Mostrar subactividades')); ?>
            </a>
            <button type="button" class="btn btn-ghost btn-sm prg-colapsar" id="prg-colapsar" title="<?php echo s(t('Contraer o expandir todas las partidas')); ?>">
                <i class="fa-solid fa-angles-up"></i> <?php echo s(t('Contraer')); ?>
            </button>
            <?php if ($puedeGestionar): ?>
            <a href="/programa/diseno?proyecto=<?php echo s($proyecto->id); ?>" class="btn btn-ghost btn-sm hide-m" title="<?php echo s(t('Elegir columnas, nombres y orden')); ?>">
                <i class="fa-solid fa-table-columns"></i> <?php echo s(t('Columnas')); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($puedeGestionar && $puedeBorrar): ?>
    <form method="POST" action="/programa/eliminar-varias" id="form-seleccion" class="barra-seleccion" hidden>
        <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>">
        <span class="barra-seleccion-n"><b id="sel-n">0</b> <?php echo s(t('seleccionadas')); ?></span>
        <button type="button" class="btn btn-sm btn-ghost" id="sel-limpiar"><?php echo s(t('Quitar selección')); ?></button>
        <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash-can"></i> <?php echo s(t('Eliminar seleccionadas')); ?></button>
        <div id="sel-campos"></div>
    </form>
    <?php endif; ?>
    <div class="tabla-wrapper prg-tabla-scroll">
        <?php
        // Posición de cada actividad dentro de su partida (sobre el programa completo, aunque haya filtros) para los botones subir/bajar
        $posAct = [];
        if ($puedeGestionar) foreach (\Classes\Renumeracion::estructura((int)$proyecto->id) as $pp) foreach ($pp['actividades'] as $k => $aa) $posAct[$aa['id']] = [$k, count($pp['actividades'])];
        $colsTabla = count(array_filter($columnas, fn($c) => $c['k'] !== 'acciones' || $puedeGestionar)) + ($puedeBorrar ? 1 : 0);
        $partidaPrevia = null;
        $filaRapida = function (array $p) use ($proyecto, $colsTabla) { ?>
                    <tr class="fila-rapida" data-partida-id="<?php echo (int)$p['id']; ?>">
                        <td colspan="<?php echo (int)$colsTabla; ?>">
                            <details class="prg-rapida">
                                <summary>
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                    <?php echo s(t('Agregar actividad a')); ?> <span class="mono"><?php echo s($p['codigo']); ?></span> · <?php echo s($p['nombre']); ?>
                                </summary>
                                <form method="POST" action="/programa/actividades/rapida" class="form-rapida prg-rapida-form">
                                    <input type="hidden" name="partida" value="<?php echo (int)$p['id']; ?>">
                                    <input type="text" name="nombre" maxlength="200" placeholder="<?php echo s(t('Nombre de la actividad (Enter para guardar)')); ?>" aria-label="<?php echo s(t('Nombre de la nueva actividad')); ?>" required>
                                    <input type="date" name="fecha_inicio" title="<?php echo s(t('Inicio (vacío = día hábil siguiente a la última actividad)')); ?>" aria-label="<?php echo s(t('Inicio')); ?>">
                                    <input type="number" name="duracion_dias" min="1" value="1" title="<?php echo s(t('Duración en días hábiles')); ?>" aria-label="<?php echo s(t('Duración en días')); ?>">
                                    <input type="number" name="progreso" min="0" max="100" value="0" title="<?php echo s(t('Avance inicial (%)')); ?>" aria-label="<?php echo s(t('Avance inicial en %')); ?>" class="input-avance">
                                    <button type="submit" class="btn btn-sm btn-primary"><?php echo s(t('Agregar')); ?></button>
                                </form>
                            </details>
                        </td>
                    </tr>
        <?php };
        $revAct = array_column(\Classes\Revisiones::elementos((int)$proyecto->id, 'actividad'), null, 'ref'); $puedeRev = puede_revisar($proyecto); ?>
        <?php
        // Columnas del diseño (Diseñar programa): «acciones» solo para quien gestiona el proyecto
        $cols = array_values(array_filter($columnas, fn($c) => $c['k'] !== 'acciones' || $puedeGestionar));
        // En móvil solo se ven nombre, fechas y acciones (como siempre); las demás llevan hide-m
        $claseTh = fn($k) => match ($k) { 'nombre' => '', 'fecha_inicio', 'fecha_fin' => 'col-fecha', 'acciones' => 'th-acciones', default => 'hide-m' };
        $reglasInd = $indicador['reglas']; $campoInd = $indicador['campo'];
        $valorInd = function (array $f) use ($campoInd, $calendario) {
            $esSub = ($f['tipo'] ?? '') === 'subactividad';
            if ($campoInd === 'duracion') {
                if (!empty($f['hito'])) return null;
                if ($esSub) return (float)($f['duracion_dias'] ?? 0);
                return ($f['tipo'] ?? '') === 'actividad' ? $calendario->calcularDuracion($f['fecha_inicio'], $f['fecha_fin']) : null;
            }
            if ($campoInd === 'libre_numero') return in_array($f['tipo'] ?? '', ['actividad', 'subactividad'], true) ? ($f['libre_numero'] ?? null) : null;
            return $f['progreso'] ?? null;
        };
        ?>
        <table class="tabla tabla-programa" id="prg-tabla" data-proyecto="<?php echo (int)$proyecto->id; ?>" data-sin-buscador>
            <thead>
                <tr>
                    <?php if ($puedeBorrar): ?><th class="th-sel" scope="col"><input type="checkbox" id="sel-todo" title="Seleccionar todo" aria-label="Seleccionar todo"></th><?php endif; ?>
                    <?php foreach ($cols as $c): $k = $c['k']; ?>
                        <th scope="col" class="<?php echo $claseTh($k); ?>" data-col="<?php echo s($k); ?>"<?php echo $puedeGestionar && $k !== 'acciones' ? ' draggable="true" title="Doble clic para renombrar · arrastra para reordenar"' : ''; ?>>
                            <?php if ($k === 'nombre' && !$c['propio']): ?><span class="hide-m"><?php echo s(t('Nombre')); ?></span><span class="solo-movil"><?php echo s(t('Actividad')); ?></span>
                            <?php elseif (($k === 'fecha_inicio' || $k === 'fecha_fin') && !$c['propio']): ?><span class="hide-m"><?php echo s(t('Fecha')); ?> </span><?php echo $k === 'fecha_inicio' ? 'Inicio' : 'Fin'; ?>
                            <?php else: echo s($c['n']); endif; ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <?php if ($fila['tipo'] === 'partida'): ?>
                    <?php if ($puedeGestionar && $partidaPrevia) $filaRapida($partidaPrevia); $partidaPrevia = $fila; ?>
                        <tr class="fila-partida prg-partida" id="fila-p<?php echo (int)$fila['id']; ?>" data-partida-id="<?php echo (int)$fila['id']; ?>" <?php if ($fila['color_bg']) echo 'style="background-color: ' . s($fila['color_bg']) . '22; border-left: 3px solid ' . s($fila['color_bg']) . ';"'; ?>>
                            <?php if ($puedeBorrar): ?><td class="td-sel"><input type="checkbox" class="sel-fila" value="p<?php echo (int)$fila['id']; ?>" aria-label="Seleccionar partida"></td><?php endif; ?>
                            <?php foreach ($cols as $c): switch ($c['k']): case 'codigo': ?>
                            <td class="mono negrita hide-m"><?php echo s($fila['codigo']); ?></td>
                            <?php break; case 'nombre': $pb = (int)($punchBloqueos[(int)$fila['id']] ?? 0); ?>
                            <td class="negrita prg-nombre-partida">
                                <button type="button" class="prg-partida-toggle" data-partida-toggle="<?php echo (int)$fila['id']; ?>" aria-expanded="true" title="<?php echo s(t('Contraer o expandir las actividades de esta partida')); ?>" aria-label="<?php echo s(t('Contraer o expandir las actividades de esta partida')); ?>"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>
                                <span class="solo-movil mono text-sm"><?php echo s($fila['codigo']); ?></span> <?php echo s($fila['nombre']); ?>
                                <?php if (!empty($fila['cerrada'])): ?><span class="badge badge-cliente" title="Partida cerrada"><i class="fa-solid fa-lock"></i> Cerrada</span>
                                <?php elseif ($pb): ?><a class="badge badge-pdf" href="/punch?proyecto=<?php echo (int)$proyecto->id; ?>&estado=pendientes" title="No se puede cerrar: <?php echo $pb; ?> pendiente(s) grave(s) de punch"><i class="fa-solid fa-lock"></i> <?php echo $pb; ?> punch</a><?php endif; ?>
                                <span class="prg-solo-tel prg-movil-meta"><span class="mono"><?php echo s($fila['progreso']); ?>%</span></span>
                            </td>
                            <?php break; case 'fecha_inicio': ?>
                            <td class="mono col-fecha"><span class="hide-m"><?php echo formatear_fecha($fila['fecha_inicio']); ?></span><span class="solo-movil"><?php echo fecha_movil($fila['fecha_inicio']); ?></span></td>
                            <?php break; case 'fecha_fin': ?>
                            <td class="mono col-fecha"><span class="hide-m"><?php echo formatear_fecha($fila['fecha_fin']); ?></span><span class="solo-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span></td>
                            <?php break; case 'progreso': ?>
                            <td class="hide-m prg-progreso-cell">
                                <div class="progreso-cell">
                                    <div class="barra-progreso barra-sm">
                                        <div class="barra-fill" style="width: <?php echo s($fila['progreso']); ?>%"></div>
                                    </div>
                                    <span class="mono text-sm"><?php echo s($fila['progreso']); ?>%</span>
                                </div>
                            </td>
                            <?php break; case 'indicador': $col = $campoInd === 'progreso' ? \Classes\ProgramaColumnas::colorIndicador($reglasInd, $valorInd($fila)) : null; ?>
                            <td class="hide-m"><?php if ($col): ?><span class="prg-ind" style="--ind: <?php echo s($col); ?>" title="<?php echo s($fila['progreso']); ?>%"></span><?php else: ?>—<?php endif; ?></td>
                            <?php break; case 'acciones': ?>
                                <td class="acciones prg-acciones">
                                    <a href="/programa/actividades/crear?proyecto=<?php echo s($proyecto->id); ?>&partida=<?php echo s($fila['id']); ?>" class="btn-action btn-action-add prg-modal-link" data-modal-url="/programa/actividades/crear?proyecto=<?php echo s($proyecto->id); ?>&partida=<?php echo s($fila['id']); ?>&modal=1" data-modal-titulo="<?php echo s(t('Nueva actividad')); ?>" title="Agregar actividad">
                                        <i class="fa-solid fa-plus"></i>
                                    </a>
                                    <a href="/programa/partidas/editar?id=<?php echo s($fila['id']); ?>" class="btn-action btn-action-edit prg-modal-link" data-modal-url="/programa/partidas/editar?id=<?php echo s($fila['id']); ?>&modal=1" data-modal-titulo="<?php echo s(t('Editar partida')); ?>" title="Editar partida">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <?php if (!empty($fila['cerrada'])): ?>
                                        <?php if (is_admin() || es_propietario($proyecto)): ?><form class="inline" method="POST" action="/programa/partidas/reabrir" data-confirm="Reabrir partida|Volverá a quedar abierta para cierre.|Reabrir"><input type="hidden" name="id" value="<?php echo s($fila['id']); ?>"><button type="submit" class="btn-action" title="Reabrir partida"><i class="fa-solid fa-lock-open"></i></button></form><?php endif; ?>
                                    <?php else: ?>
                                        <a href="/programa/partidas/cerrar?id=<?php echo s($fila['id']); ?>" class="btn-action" title="Cerrar partida"><i class="fa-solid fa-lock"></i></a>
                                    <?php endif; ?>
                                    <?php if (puede_borrar_contenido($proyecto)): ?>
                                    <form class="inline" method="POST" action="/programa/partidas/eliminar" data-confirm="¿Eliminar partida?|Se eliminará la partida junto con todas sus actividades. Esta acción no se puede deshacer.">
                                        <input type="hidden" name="id" value="<?php echo s($fila['id']); ?>">
                                        <button type="submit" class="btn-action btn-action-delete" title="Eliminar partida">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            <?php break; default: ?>
                            <td class="hide-m" data-col="<?php echo s($c['k']); ?>">—</td>
                            <?php endswitch; endforeach; ?>
                        </tr>
                    <?php elseif ($fila['tipo'] === 'actividad'): ?>
                        <?php $conSub = !empty($fila['subactividades_total']); ?>
                        <tr class="fila-actividad prg-actividad" id="fila-a<?php echo (int)$fila['id']; ?>" data-actividad-id="<?php echo s($fila['id']); ?>" data-partida-id="<?php echo (int)$fila['partida_id']; ?>" data-estado="<?php echo s($fila['status']); ?>">
                            <?php if ($puedeBorrar): ?><td class="td-sel"><input type="checkbox" class="sel-fila" value="a<?php echo (int)$fila['id']; ?>" aria-label="Seleccionar actividad"></td><?php endif; ?>
                            <?php foreach ($cols as $c): switch ($c['k']): case 'codigo': ?>
                            <td class="mono indent hide-m"><?php echo s($fila['codigo']); ?></td>
                            <?php break; case 'nombre': ?>
                            <td class="indent prg-nombre-actividad"><span class="solo-movil mono text-sm"><?php echo s($fila['codigo']); ?></span> <?php echo s($fila['nombre']); ?><?php $py = $proyecciones[(int)$fila['id']] ?? null; ?><?php if ($py && $py['aviso']): ?><span class="badge badge-pdf" title="<?php echo s($py['aviso']); ?>"><i class="fa-solid fa-triangle-exclamation"></i> Fecha</span><?php elseif ($py && $py['tarde']): ?><span class="badge badge-visitante" title="Fin proyectado: <?php echo s(formatear_fecha($py['fin'], 'd/m/Y')); ?>"><i class="fa-solid fa-clock"></i> Tarde</span><?php endif; ?><?php echo \Classes\Salud::insignia($riesgos, (int)$fila['id']); ?> <?php if (isset($revAct[(string)$fila['id']])) echo \Classes\Revisiones::chip('actividad', (string)$fila['id'], $revAct[(string)$fila['id']], $puedeRev, 'Cumplimiento'); ?><span class="prg-solo-tel prg-movil-meta"><span class="badge-status status-<?php echo s($fila['status']); ?>"><?php echo $estadoMovil($fila['status']); ?></span><span class="mono"><?php echo (int)$fila['progreso']; ?>%</span></span></td>
                            <?php break; case 'fecha_inicio': ?>
                            <td class="fecha-editable">
                                <?php if ($puedeGestionar): ?>
                                    <input type="date"
                                           class="input-fecha fecha-inicio-input hide-m"
                                           value="<?php echo s($fila['fecha_inicio']); ?>"
                                           data-actividad-id="<?php echo s($fila['id']); ?>"
                                           data-tipo="inicio"
                                           aria-label="Fecha de inicio de <?php echo s($fila['codigo']); ?>">
                                    <span class="solo-movil mono fecha-texto-movil"><?php echo fecha_movil($fila['fecha_inicio']); ?></span>
                                <?php else: ?>
                                    <span class="mono hide-m"><?php echo formatear_fecha($fila['fecha_inicio']); ?></span><span class="mono solo-movil"><?php echo fecha_movil($fila['fecha_inicio']); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php break; case 'fecha_fin': ?>
                            <td class="fecha-editable">
                                <?php if ($puedeGestionar && !$conSub): ?>
                                    <input type="date"
                                           class="input-fecha fecha-fin-input hide-m"
                                           value="<?php echo s($fila['fecha_fin']); ?>"
                                           data-actividad-id="<?php echo s($fila['id']); ?>"
                                           data-tipo="fin"
                                           aria-label="Fecha de fin de <?php echo s($fila['codigo']); ?>">
                                    <span class="solo-movil mono fecha-texto-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span>
                                <?php elseif ($conSub): ?>
                                    <span class="mono hide-m" title="El fin se calcula del máximo de sus subactividades"><?php echo formatear_fecha($fila['fecha_fin']); ?> <i class="fa-solid fa-lock text-muted"></i></span>
                                    <span class="solo-movil mono fecha-texto-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span>
                                <?php else: ?>
                                    <span class="mono hide-m"><?php echo formatear_fecha($fila['fecha_fin']); ?></span><span class="mono solo-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php break; case 'duracion': $dur = $calendario->calcularDuracion($fila['fecha_inicio'], $fila['fecha_fin']); ?>
                            <td class="mono duracion-cell hide-m"><?php echo !empty($fila['hito']) ? 'Hito' : s(number_format($dur, 1)) . ' d'; ?></td>
                            <?php break; case 'progreso': ?>
                            <td class="hide-m prg-progreso-cell">
                                <div class="progreso-cell">
                                    <div class="barra-progreso barra-sm barra-status-<?php echo s($fila['status']); ?>">
                                        <div class="barra-fill" style="width: <?php echo s($fila['progreso']); ?>%"></div>
                                    </div>
                                    <?php $conSubactividades = !empty($fila['subactividades_total']); ?>
                                    <?php $bloq = $bloqueosCalidad[(int)$fila['id']] ?? null; $topeCal = $bloq ? \Classes\Calidad::tope($bloq, (int)$fila['progreso']) : 100; $maxCal = max($topeCal, (int)$fila['progreso']); $congelado = $bloq && $bloq['nivel'] === 'nc' && (int)$fila['progreso'] < 100;
                                    if ($bloq && $topeCal >= 100) $bloq = null;   // ya está cerrada: no hay nada que detener ?>
                                    <?php if ($puedeGestionar && !$conSubactividades): ?>
                                        <input type="number" class="input-avance input-progreso" min="0" max="<?php echo $maxCal; ?>" step="1"
                                               value="<?php echo (int)$fila['progreso']; ?>" data-actividad-id="<?php echo s($fila['id']); ?>"
                                               aria-label="Avance (%)" title="<?php echo s($bloq ? $bloq['motivo'] : 'Avance (%)'); ?>"<?php echo $congelado ? ' disabled' : ''; ?>>%
                                    <?php else: ?>
                                        <span class="mono text-sm"><?php echo s($fila['progreso']); ?>%</span>
                                        <?php if ($conSubactividades): ?>
                                            <i class="fa-solid fa-lock text-muted" data-ayuda="El avance es el promedio de <?php echo (int)$fila['subactividades_total']; ?> subactividad(es). Se editan dentro de la actividad." data-manual="/manual#programa" title="Promedio de <?php echo (int)$fila['subactividades_total']; ?> subactividad(es)"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($bloq): ?>
                                        <a class="cal-lock<?php echo $congelado ? ' cal-lock-nc' : ''; ?>" href="/calidad?proyecto=<?php echo s($proyecto->id); ?><?php echo $bloq['plan_id'] ? '&ver=' . (int)$bloq['plan_id'] : ''; ?>"
                                           title="<?php echo s($bloq['motivo']); ?>" aria-label="<?php echo s($bloq['motivo']); ?>" data-ayuda="<?php echo s($bloq['motivo']); ?>" data-manual="/manual#calidad">
                                            <i class="fa-solid <?php echo $bloq['nivel'] === 'liberada' ? 'fa-lock-open' : 'fa-lock'; ?>"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php break; case 'status': ?>
                            <td class="hide-m prg-status-cell">
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
                            <?php break; case 'prioridad': ?>
                            <td class="hide-m prg-prio-cell"><span class="badge-prio prio-<?php echo s($fila['prioridad']); ?>"><?php echo s(ucfirst($fila['prioridad'])); ?></span></td>
                            <?php break; case 'restriccion': ?>
                            <td class="hide-m prg-restr-cell">
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
                            <?php break; case 'responsable': case 'libre_texto': case 'libre_numero': case 'rec_inicio': case 'rec_fin': case 'libre_fecha':
                                [, $tipoCampo] = \Classes\ProgramaColumnas::COLUMNAS[$c['k']]; $v = $fila[$c['k']] ?? ''; $tipoIn = ['texto' => 'text', 'fecha' => 'date', 'numero' => 'number'][$tipoCampo] ?? 'text'; ?>
                            <td class="hide-m prg-celda">
                                <?php if ($puedeGestionar): ?>
                                    <input type="<?php echo $tipoIn; ?>" class="prg-campo<?php echo $tipoIn === 'date' ? ' input-fecha' : ''; ?>" data-actividad-id="<?php echo (int)$fila['id']; ?>" data-campo="<?php echo s($c['k']); ?>"
                                           value="<?php echo s($tipoIn === 'number' && $v !== '' && $v !== null ? rtrim(rtrim((string)$v, '0'), '.') : $v); ?>"<?php echo $tipoIn === 'number' ? ' step="any"' : ($tipoIn === 'text' ? ' maxlength="255"' : ''); ?> aria-label="<?php echo s($c['n']); ?>">
                                <?php else: ?>
                                    <span class="<?php echo $tipoIn === 'text' ? '' : 'mono'; ?>"><?php echo $v === '' || $v === null ? '—' : s($tipoIn === 'date' ? formatear_fecha($v) : ($tipoIn === 'number' ? rtrim(rtrim((string)$v, '0'), '.') : $v)); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php break; case 'predecesora': case 'sucesora':
                                $lado = $c['k'] === 'predecesora' ? 'pred' : 'suc'; $lista = $vecinas[$lado][(int)$fila['id']] ?? []; $actual = $lista[0] ?? 0; ?>
                            <td class="hide-m prg-celda">
                                <?php if ($puedeGestionar): ?>
                                    <select class="prg-dep" data-actividad-id="<?php echo (int)$fila['id']; ?>" data-lado="<?php echo $lado; ?>" aria-label="<?php echo s($c['n']); ?>">
                                        <option value="0">—</option>
                                        <?php foreach ($actividadesLista as $aid => $anom): if ($aid === (int)$fila['id']) continue; ?>
                                            <option value="<?php echo (int)$aid; ?>"<?php echo $aid === $actual ? ' selected' : ''; ?>><?php echo s($anom); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <span class="mono"><?php echo $actual && isset($actividadesLista[$actual]) ? s(strstr($actividadesLista[$actual], ' · ', true)) : '—'; ?></span>
                                <?php endif; ?>
                                <small class="prg-otras text-muted"<?php echo count($lista) > 1 ? '' : ' hidden'; ?>>+<?php echo max(0, count($lista) - 1); ?> más (ver Dependencias)</small>
                            </td>
                            <?php break; case 'indicador': $vi = $valorInd($fila); $col = \Classes\ProgramaColumnas::colorIndicador($reglasInd, $vi); ?>
                            <td class="hide-m prg-celda"><?php if ($col): ?><span class="prg-ind" style="--ind: <?php echo s($col); ?>" data-indicador="<?php echo (int)$fila['id']; ?>"></span> <span class="mono text-sm text-muted"><?php echo s(rtrim(rtrim(number_format((float)$vi, 2, '.', ''), '0'), '.')); ?></span><?php else: ?>—<?php endif; ?></td>
                            <?php break; case 'icono': ?>
                            <td class="hide-m prg-celda text-center prg-icono-cell"><?php if (!empty($fila['icono'])): ?><img class="prg-icono" src="<?php echo s('/build/img/actividades-iconos/' . basename($fila['icono'])); ?>" alt=""><?php else: ?>—<?php endif; ?></td>
                            <?php break; case 'acciones': ?>
                                <td class="acciones prg-acciones">
                                    <button type="button" class="btn-action btn-action-edit btn-fechas solo-movil" title="Cambiar fechas"
                                            data-id="<?php echo s($fila['id']); ?>" data-codigo="<?php echo s($fila['codigo']); ?>" data-nombre="<?php echo s($fila['nombre']); ?>"
                                            data-inicio="<?php echo s($fila['fecha_inicio']); ?>" data-fin="<?php echo s($fila['fecha_fin']); ?>"<?php echo $conSub ? ' data-bloquea-fin="1"' : ''; ?>>
                                        <i class="fa-regular fa-calendar-days"></i>
                                    </button>
                                    <a href="/programa/restricciones/crear?actividad=<?php echo s($fila['id']); ?>" class="btn-action btn-action-warning" title="Agregar restricción">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                    </a>
                                    <?php [$posI, $posN] = $posAct[$fila['id']] ?? [0, 1]; ?>
                                    <form method="POST" action="/programa/actividades/mover" class="form-mover inline">
                                        <input type="hidden" name="id" value="<?php echo s($fila['id']); ?>">
                                        <button type="submit" name="dir" value="arriba" class="btn-action btn-action-move" title="Subir (renumera la partida)"<?php echo $posI <= 0 ? ' disabled' : ''; ?>><i class="fa-solid fa-arrow-up"></i></button>
                                        <button type="submit" name="dir" value="abajo" class="btn-action btn-action-move" title="Bajar (renumera la partida)"<?php echo $posI >= $posN - 1 ? ' disabled' : ''; ?>><i class="fa-solid fa-arrow-down"></i></button>
                                    </form>
                                    <a href="/programa/actividades/editar?id=<?php echo s($fila['id']); ?>" class="btn-action btn-action-edit prg-modal-link" data-modal-url="/programa/actividades/editar?id=<?php echo s($fila['id']); ?>&modal=1" data-modal-titulo="<?php echo s(t('Editar actividad')); ?>" title="Editar actividad">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <?php if (puede_borrar_contenido($proyecto)): ?>
                                    <form class="inline" method="POST" action="/programa/actividades/eliminar" data-confirm="¿Eliminar actividad?|Se eliminará la actividad y sus restricciones asociadas. Esta acción no se puede deshacer.">
                                        <input type="hidden" name="id" value="<?php echo s($fila['id']); ?>">
                                        <button type="submit" class="btn-action btn-action-delete" title="Eliminar actividad">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            <?php break; default: if (!empty($c['campo'])) echo \Classes\CamposPersonalizados::celda($c['campo'], (int)$fila['id'], $valoresCp[(int)$fila['id']][$c['campo']['id']] ?? '', $puedeGestionar); endswitch; endforeach; ?>
                        </tr>
                    <?php else: ?>
                        <?php // Subactividad: fila hija indentada, editable en fechas y avance; el resto abre su ficha ?>
                        <tr class="fila-actividad fila-subactividad prg-subactividad" id="fila-s<?php echo (int)$fila['id']; ?>" data-subactividad-id="<?php echo (int)$fila['id']; ?>" data-padre-id="<?php echo (int)$fila['actividad_id']; ?>" data-partida-id="<?php echo (int)$fila['partida_id']; ?>" data-estado="<?php echo s($fila['status']); ?>">
                            <?php if ($puedeBorrar): ?><td class="td-sel"></td><?php endif; ?>
                            <?php foreach ($cols as $c): switch ($c['k']): case 'codigo': ?>
                            <td class="mono indent-sub hide-m"><?php echo s($fila['codigo']); ?></td>
                            <?php break; case 'nombre': ?>
                            <td class="indent-sub prg-nombre-sub"><span class="solo-movil mono text-sm"><?php echo s($fila['codigo']); ?></span><i class="fa-solid fa-turn-up fa-rotate-90 text-dim sub-marca" aria-hidden="true"></i> <?php echo s($fila['nombre']); ?><span class="prg-solo-tel prg-movil-meta"><span class="badge-status status-<?php echo s($fila['status']); ?>"><?php echo $estadoMovil($fila['status']); ?></span><span class="mono"><?php echo (int)$fila['progreso']; ?>%</span></span></td>
                            <?php break; case 'fecha_inicio': ?>
                            <td class="fecha-editable">
                                <?php if ($puedeGestionar): ?>
                                    <input type="date" class="input-fecha input-fecha-sub hide-m" value="<?php echo s($fila['fecha_inicio']); ?>" data-subactividad-id="<?php echo (int)$fila['id']; ?>" data-tipo="inicio" aria-label="Fecha de inicio de <?php echo s($fila['codigo']); ?>">
                                    <span class="solo-movil mono fecha-texto-movil"><?php echo fecha_movil($fila['fecha_inicio']); ?></span>
                                <?php else: ?>
                                    <span class="mono hide-m"><?php echo formatear_fecha($fila['fecha_inicio']); ?></span><span class="mono solo-movil"><?php echo fecha_movil($fila['fecha_inicio']); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php break; case 'fecha_fin': ?>
                            <td class="fecha-editable">
                                <?php if ($puedeGestionar): ?>
                                    <input type="date" class="input-fecha input-fecha-sub hide-m" value="<?php echo s($fila['fecha_fin']); ?>" data-subactividad-id="<?php echo (int)$fila['id']; ?>" data-tipo="fin" aria-label="Fecha de fin de <?php echo s($fila['codigo']); ?>">
                                    <span class="solo-movil mono fecha-texto-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span>
                                <?php else: ?>
                                    <span class="mono hide-m"><?php echo formatear_fecha($fila['fecha_fin']); ?></span><span class="mono solo-movil"><?php echo fecha_movil($fila['fecha_fin']); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php break; case 'duracion': ?>
                            <td class="mono duracion-cell hide-m"><?php echo !empty($fila['hito']) ? 'Hito' : s(number_format((float)$fila['duracion_dias'], 1)) . ' d'; ?></td>
                            <?php break; case 'progreso': ?>
                            <td class="hide-m prg-progreso-cell">
                                <div class="progreso-cell">
                                    <div class="barra-progreso barra-sm barra-status-<?php echo s($fila['status']); ?>">
                                        <div class="barra-fill" style="width: <?php echo (int)$fila['progreso']; ?>%"></div>
                                    </div>
                                    <?php if ($puedeGestionar): ?>
                                        <input type="number" class="input-avance input-progreso input-progreso-sub" min="0" max="100" step="1" value="<?php echo (int)$fila['progreso']; ?>" data-subactividad-id="<?php echo (int)$fila['id']; ?>" aria-label="Avance (%)">
                                    <?php else: ?>
                                        <span class="mono text-sm"><?php echo (int)$fila['progreso']; ?>%</span>
                                    <?php endif; ?>%
                                </div>
                            </td>
                            <?php break; case 'status': ?>
                            <td class="hide-m prg-status-cell">
                                <span class="badge-status status-<?php echo s($fila['status']); ?>">
                                    <?php echo match($fila['status']) {
                                        'completada' => '<i class="fa-solid fa-check-circle"></i> Completada',
                                        'en_proceso' => '<i class="fa-solid fa-spinner"></i> En Proceso',
                                        default => '<i class="fa-solid fa-circle"></i> No Iniciada'
                                    }; ?>
                                </span>
                            </td>
                            <?php break; case 'prioridad': ?>
                            <td class="hide-m prg-prio-cell"><span class="badge-prio prio-<?php echo s($fila['prioridad']); ?>"><?php echo s(ucfirst((string)$fila['prioridad'])); ?></span></td>
                            <?php break; case 'restriccion': ?>
                            <td class="hide-m prg-restr-cell">
                                <?php if (!empty($fila['restricciones'])): ?>
                                    <div class="restricciones-lista">
                                        <?php foreach ($fila['restricciones'] as $rest): $tipoRest = $rest->getTipo(); ?>
                                            <span class="badge-restriccion" title="<?php echo s($rest->descripcion ?: 'Sin descripción'); ?><?php echo $rest->fecha ? ' · ' . formatear_fecha($rest->fecha) : ''; ?>">
                                                <i class="fa-solid fa-circle-exclamation"></i> <?php echo s($rest->codigo ?: ($tipoRest ? $tipoRest->nombre : 'Restricción')); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-dim">—</span>
                                <?php endif; ?>
                                <?php if ($puedeGestionar): ?><a href="/programa/restricciones/crear?subactividad=<?php echo (int)$fila['id']; ?>" class="btn-action btn-action-warning" title="Agregar restricción a la subactividad"><i class="fa-solid fa-triangle-exclamation"></i></a><?php endif; ?>
                            </td>
                            <?php break; case 'responsable': case 'rec_inicio': case 'rec_fin': case 'libre_texto': case 'libre_numero': case 'libre_fecha':
                                $v = $fila[$c['k']] ?? ''; $esFecha = in_array($c['k'], ['rec_inicio', 'rec_fin', 'libre_fecha'], true); ?>
                            <td class="hide-m prg-celda"><span class="<?php echo $esFecha ? 'mono' : ''; ?>"><?php echo $v === '' || $v === null ? '—' : s($esFecha ? formatear_fecha($v) : $v); ?></span></td>
                            <?php break; case 'predecesora': case 'sucesora': ?>
                            <td class="hide-m prg-celda"><span class="text-dim" title="Las dependencias de la subactividad se gestionan en Dependencias de obra">—</span></td>
                            <?php break; case 'indicador': $vi = $valorInd($fila); $col = \Classes\ProgramaColumnas::colorIndicador($reglasInd, $vi); ?>
                            <td class="hide-m prg-celda"><?php if ($col): ?><span class="prg-ind" style="--ind: <?php echo s($col); ?>"></span> <span class="mono text-sm text-muted"><?php echo s(rtrim(rtrim(number_format((float)$vi, 2, '.', ''), '0'), '.')); ?></span><?php else: ?>—<?php endif; ?></td>
                            <?php break; case 'icono': ?>
                            <td class="hide-m prg-celda text-center prg-icono-cell"><?php if (!empty($fila['icono'])): ?><img class="prg-icono" src="<?php echo s('/build/img/actividades-iconos/' . basename($fila['icono'])); ?>" alt=""><?php else: ?>—<?php endif; ?></td>
                            <?php break; case 'acciones': ?>
                                <td class="acciones prg-acciones">
                                    <a href="/programa/subactividades/editar?id=<?php echo (int)$fila['id']; ?>&volver=programa#seccion-materiales" class="btn-action prg-modal-link" data-modal-url="/programa/subactividades/editar?id=<?php echo (int)$fila['id']; ?>&volver=programa&modal=1#seccion-materiales" data-modal-titulo="<?php echo s(t('Materiales de la subactividad')); ?>" title="Materiales de la subactividad"><i class="fa-solid fa-cubes"></i></a>
                                    <a href="/programa/subactividades/editar?id=<?php echo (int)$fila['id']; ?>&volver=programa" class="btn-action btn-action-edit prg-modal-link" data-modal-url="/programa/subactividades/editar?id=<?php echo (int)$fila['id']; ?>&volver=programa&modal=1" data-modal-titulo="<?php echo s(t('Editar subactividad')); ?>" title="Editar subactividad"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <?php if (puede_borrar_contenido($proyecto)): ?>
                                    <form class="inline" method="POST" action="/programa/subactividades/eliminar" data-confirm="¿Eliminar subactividad?|Se quitará «<?php echo s($fila['nombre']); ?>» y el avance de la actividad se recalculará. Esta acción no se puede deshacer.|Eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int)$fila['id']; ?>">
                                        <input type="hidden" name="volver" value="programa">
                                        <button type="submit" class="btn-action btn-action-delete" title="Eliminar subactividad"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            <?php break; default: ?>
                            <td class="hide-m" data-col="<?php echo s($c['k']); ?>">—</td>
                            <?php endswitch; endforeach; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($puedeGestionar && $partidaPrevia) $filaRapida($partidaPrevia); ?>
                <?php if (empty($filas)): ?>
                    <tr><td colspan="<?php echo (int)$colsTabla; ?>" class="text-center text-muted"><?php echo $hayFiltros ? 'Ninguna actividad coincide con los filtros.' : 'No hay partidas ni actividades.'; ?> <?php if (!$hayFiltros && puede_gestionar_proyecto($proyecto)): ?>Comienza creando una partida.<?php endif; ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($totalFilas ?? 0) > count($filas)): $qMas = $_GET; $qMas['filas'] = (int)$limiteFilas + 300; ?>
    <div class="programa-mas no-print">
        <span class="text-muted text-sm"><?php echo s(t('Mostrando {a} de {b} filas', ['a' => count($filas), 'b' => (int)$totalFilas])); ?></span>
        <a class="btn btn-ghost btn-sm" href="/programa?<?php echo s(http_build_query($qMas)); ?>"><i class="fa-solid fa-chevron-down"></i> <?php echo s(t('Mostrar 300 más')); ?></a>
        <a class="btn btn-ghost btn-sm" href="/programa?<?php echo s(http_build_query(array_merge($_GET, ['filas' => 5000]))); ?>"><?php echo s(t('Mostrar todo')); ?></a>
    </div>
<?php endif; ?>

