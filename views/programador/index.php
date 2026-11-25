<?php // Variables: $proyecto, $lista, $propuesta (o null), $impacto (por partida), $curva, $seguimiento, $otra/$comparacion (comparar), $mensaje
$r = $propuesta['resumen'] ?? [];
$filas = $propuesta['filas'] ?? [];
$sevNombre = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$sevClase = ['critica' => 'ia-sev--critica', 'alta' => 'ia-sev--alta', 'media' => 'ia-sev--media', 'baja' => 'ia-sev--baja'];
$fuenteNombre = ['material' => 'Material', 'submittal' => 'Submittal', 'bitacora' => 'Bitácora', 'reporte' => 'Reporte diario', 'restriccion' => 'Restricción', 'otro' => 'Otro'];
$catNombre = ['inconsistencia' => 'Inconsistencia', 'relacion_faltante' => 'Relación faltante', 'duracion' => 'Duración', 'riesgo' => 'Riesgo', 'dato_faltante' => 'Dato faltante', 'oportunidad' => 'Oportunidad'];
$estados = \Classes\ProgramadorAcciones::ESTADOS;
$pidP = (int)$proyecto->id;
$enlaceFuente = ['material' => '/materiales', 'submittal' => '/submittals', 'bitacora' => '/bitacora', 'reporte' => '/reportes', 'restriccion' => '/lookahead'];
$control = function (string $clave) use ($seguimiento, $estados) {
    $x = $seguimiento[$clave] ?? ['estado' => 'pendiente', 'responsable' => ''];
    $h = '<div class="prg-seg" data-clave="' . $clave . '"><select aria-label="Estado">';
    foreach ($estados as $k => $n) $h .= '<option value="' . $k . '"' . ($k === $x['estado'] ? ' selected' : '') . '>' . s($n) . '</option>';
    return $h . '</select><input type="text" maxlength="80" placeholder="Responsable" aria-label="Responsable" value="' . s($x['responsable']) . '"></div>';
};
$fmt = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$codigos = array_column($filas, 'codigo', 'id');
$dif = (int)($r['dif_dias_habiles'] ?? 0);
$totalAplicables = 0;
$partidasCambian = 0;
foreach ($impacto as $xImpacto) {
    $totalAplicables += (int)($xImpacto['aplicables'] ?? 0);
    if ((int)($xImpacto['cambian'] ?? 0) > 0) $partidasCambian++;
}
$volver = '/programa?proyecto=' . $pidP;

// Escala del comparativo: de la primera a la última fecha entre el programa actual y el propuesto
$min = $max = null;
foreach ($filas as $f) {
    foreach ([$f['actual']['inicio'], $f['propuesto']['inicio']] as $d) if ($min === null || $d < $min) $min = $d;
    foreach ([$f['actual']['fin'], $f['propuesto']['fin']] as $d) if ($max === null || $d > $max) $max = $d;
}
$span = $min ? max(1, (strtotime($max) - strtotime($min)) / 86400 + 1) : 1;
$pos = fn($ini, $fin) => [round((strtotime($ini) - strtotime($min)) / 86400 / $span * 100, 2), max(0.4, round(((strtotime($fin) - strtotime($ini)) / 86400 + 1) / $span * 100, 2))];
$hoyPos = $min ? round((strtotime($r['hoy'] ?? date('Y-m-d')) - strtotime($min)) / 86400 / $span * 100, 2) : -1;
?>
<div class="page-header ia-header">
    <div>
        <span class="ia-eyebrow"><i class="fa-solid fa-wand-magic-sparkles"></i> <?php echo s(t('Inteligencia artificial')); ?></span>
        <h2><i class="fa-solid fa-wand-magic-sparkles"></i> <?php echo s(t('Programador de actividades')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · propuesta de la IA sobre cómo ejecutar las actividades. <strong>No reemplaza el programa actual.</strong></p>
        <?php if ($propuesta): ?>
            <span class="ia-status"><span class="ia-status-dot"></span><?php echo s('Propuesta del ' . date('d/m/Y H:i', strtotime($propuesta['creado'])) . ($propuesta['modelo'] ? ' · ' . $propuesta['modelo'] : '')); ?></span>
        <?php endif; ?>
    </div>
    <div class="ia-header-acciones">
        <a href="<?php echo s($volver); ?>" class="btn btn-ghost">← <?php echo s(t('Programa')); ?></a>
        <?php if ($propuesta): ?>
            <a class="btn btn-sm btn-ghost" href="/programador/exportar?proyecto=<?php echo $pidP; ?>&propuesta=<?php echo (int)$propuesta['id']; ?>&formato=xlsx"><i class="fa-solid fa-file-excel"></i> Excel</a>
            <a class="btn btn-sm btn-ghost" href="/programador/exportar?proyecto=<?php echo $pidP; ?>&propuesta=<?php echo (int)$propuesta['id']; ?>&formato=pdf"><i class="fa-solid fa-file-pdf"></i> PDF</a>
        <?php endif; ?>
    </div>
</div>

<div class="ia-trust" role="note">
    <span class="ia-trust-icono"><i class="fa-solid fa-shield-halved"></i></span>
    <p><strong>La IA propone; tú apruebas.</strong> Nada se aplica solo.</p>
    <a href="/historial-cambios?proyecto=<?php echo $pidP; ?>">Ver historial de cambios →</a>
</div>

<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<?php if (!$propuesta): ?>
    <div class="card ia-empty">
        <span class="ia-empty-icono"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
        <h3>Aún no hay propuestas</h3>
        <p class="text-muted">El análisis lo ejecuta la app de escritorio con tu modelo de IA (local, suscripción o API). Ábrela y entra a <strong>Herramientas IA → Programador de actividades</strong>, elige este proyecto y una conexión, y pulsa <em>Analizar</em>. Al terminar, la propuesta aparece aquí.</p>
        <p class="text-muted text-sm">La IA clasifica cada actividad (administrativa u operativa), propone predecesoras y paralelismos, y revisa materiales, submittals, restricciones, bitácora y reportes diarios en busca de obstáculos. Las fechas las calcula el calendario del proyecto, no la IA.</p>
        <div class="ia-empty-acciones">
            <a class="btn btn-primary" href="<?php echo s($volver); ?>"><?php echo s(t('Programa')); ?></a>
            <a class="btn btn-ghost" href="/historial-cambios?proyecto=<?php echo $pidP; ?>">Historial de cambios</a>
        </div>
    </div>
<?php else: ?>
    <div class="ia-shell">
        <aside class="ia-rail" aria-label="Escenarios de programación">
            <div class="ia-rail-head"><span>Escenarios</span><span class="ia-count"><?php echo count($lista); ?></span></div>
            <div class="ia-rail-list">
                <?php foreach ($lista as $l): $activa = (int)$l['id'] === (int)$propuesta['id']; ?>
                    <a class="ia-rail-item<?php echo $activa ? ' activa' : ''; ?>" href="/programador?proyecto=<?php echo $pidP; ?>&propuesta=<?php echo (int)$l['id']; ?>"<?php echo $activa ? ' aria-current="page"' : ''; ?>>
                        <span class="ia-rail-dot <?php echo !empty($l['guardada']) ? 'ok' : 'info'; ?>"></span>
                        <span class="ia-rail-txt">
                            <strong><?php echo s($l['nombre'] !== '' ? $l['nombre'] : 'Propuesta ' . date('d/m/Y', strtotime($l['creado']))); ?></strong>
                            <small><?php echo s(date('d/m/Y H:i', strtotime($l['creado'])) . ($l['modelo'] ? ' · ' . $l['modelo'] : '')); ?></small>
                        </span>
                        <?php if (!empty($l['guardada'])): ?><span class="ia-chip ia-chip--ok">Guardada</span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="ia-rail-actions">
                <?php if (count($lista) > 1): ?>
                    <label class="ia-select">Comparar con
                        <select onchange="location.href='/programador?proyecto=<?php echo $pidP; ?>&propuesta=<?php echo (int)$propuesta['id']; ?>'+(this.value?'&comparar='+this.value:'')">
                            <option value="">—</option>
                            <?php foreach ($lista as $l): if ((int)$l['id'] === (int)$propuesta['id']) continue; ?>
                                <option value="<?php echo (int)$l['id']; ?>"<?php echo $otra && (int)$l['id'] === (int)$otra['id'] ? ' selected' : ''; ?>><?php echo s(($l['nombre'] ?: date('d/m/Y H:i', strtotime($l['creado']))) . ($l['guardada'] ? ' ★' : '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <form method="post" action="/programador/guardar" class="prg-guardar">
                    <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$propuesta['id']; ?>">
                    <?php if (!empty($propuesta['guardada'])): ?>
                        <span class="ia-chip ia-chip--ok"><i class="fa-solid fa-bookmark"></i> Guardada<?php echo $propuesta['nombre'] !== '' ? ': ' . s($propuesta['nombre']) : ''; ?></span>
                        <button class="btn btn-sm btn-ghost" type="submit" name="quitar" value="1">Quitar de guardadas</button>
                    <?php else: ?>
                        <input type="text" name="nombre" maxlength="150" placeholder="Nombre (ej. Compras adelantadas)" aria-label="Nombre de la propuesta" value="<?php echo s('Propuesta ' . date('d/m/Y', strtotime($propuesta['creado']))); ?>">
                        <button class="btn btn-sm btn-primary" type="submit"><i class="fa-regular fa-bookmark"></i> Guardar propuesta</button>
                    <?php endif; ?>
                </form>
                <form method="post" action="/programador/eliminar" data-confirmar="¿Eliminar esta propuesta? El programa actual no se toca.">
                    <input type="hidden" name="proyecto" value="<?php echo (int)$proyecto->id; ?>"><input type="hidden" name="id" value="<?php echo (int)$propuesta['id']; ?>">
                    <button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i> Eliminar propuesta</button>
                </form>
            </div>
        </aside>

        <main class="ia-lienzo">
            <div class="ia-kpis" aria-label="Resumen de la propuesta">
                <div class="suite-kpi">
                    <span class="suite-kpi-icono"><i class="fa-solid fa-calendar"></i></span>
                    <span class="suite-kpi-txt"><small>Fin actual</small><strong><?php echo s($fmt($r['fin_actual'] ?? null)); ?></strong><span>Línea base</span></span>
                </div>
                <div class="suite-kpi <?php echo $dif < 0 ? 'is-success' : ($dif > 0 ? 'is-danger' : ''); ?>">
                    <span class="suite-kpi-icono"><i class="fa-solid fa-flag-checkered"></i></span>
                    <span class="suite-kpi-txt"><small>Fin propuesto</small><strong><?php echo s($fmt($r['fin_propuesto'] ?? null)); ?></strong><span><?php echo $dif === 0 ? 'Sin cambio' : ($dif < 0 ? abs($dif) . ' días hábiles antes' : $dif . ' días hábiles después'); ?></span></span>
                </div>
                <div class="suite-kpi <?php echo (int)($r['movidas'] ?? 0) > 0 ? 'is-info' : ''; ?>">
                    <span class="suite-kpi-icono"><i class="fa-solid fa-list-check"></i></span>
                    <span class="suite-kpi-txt"><small>Actividades que cambian</small><strong><?php echo (int)($r['movidas'] ?? 0); ?></strong><span>de <?php echo (int)($r['actividades'] ?? 0); ?> · <?php echo (int)($r['analizadas'] ?? 0); ?> analizadas</span></span>
                </div>
                <div class="suite-kpi ia-kpi--texto">
                    <span class="suite-kpi-icono"><i class="fa-solid fa-tags"></i></span>
                    <span class="suite-kpi-txt"><small>Tipo de actividad</small><strong><?php echo (int)($r['administrativas'] ?? 0); ?> adm. · <?php echo (int)($r['operativas'] ?? 0); ?> oper.</strong><span>Clasificación IA</span></span>
                </div>
                <div class="suite-kpi <?php echo (int)($r['relaciones_nuevas'] ?? 0) > 0 ? 'is-info' : ''; ?>">
                    <span class="suite-kpi-icono"><i class="fa-solid fa-link"></i></span>
                    <span class="suite-kpi-txt"><small>Relaciones</small><strong>+<?php echo (int)($r['relaciones_nuevas'] ?? 0); ?></strong><span>reemplazan <?php echo (int)($r['relaciones_reemplazadas'] ?? 0); ?> actuales</span></span>
                </div>
                <div class="suite-kpi <?php echo count($r['obstaculos'] ?? []) > 0 ? 'is-warning' : 'is-success'; ?>">
                    <span class="suite-kpi-icono"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <span class="suite-kpi-txt"><small>Obstáculos</small><strong><?php echo count($r['obstaculos'] ?? []); ?></strong><span><?php echo (int)($r['obstaculos_por_severidad']['critica'] ?? 0) + (int)($r['obstaculos_por_severidad']['alta'] ?? 0); ?> críticos o altos</span></span>
                </div>
            </div>

            <?php foreach ($r['avisos'] ?? [] as $av): ?><p class="prg-aviso"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo s($av); ?></p><?php endforeach; ?>

            <?php if ($comparacion): $cmp = $comparacion; ?>
                <section class="ia-panel">
                    <div class="ia-panel-head">
                        <div><h3>Comparación con «<?php echo s($otra['nombre'] ?: date('d/m/Y H:i', strtotime($otra['creado']))); ?>»</h3><p>Dos escenarios de la misma IA lado a lado.</p></div>
                    </div>
                    <div class="ia-tabla-wrap">
                        <table class="ia-tabla prg-cmp">
                            <thead><tr><th></th><th>Esta propuesta</th><th>La otra</th></tr></thead>
                            <tbody>
                            <?php foreach ($cmp['resumen'] as [$etq, $va, $vb]): ?>
                                <tr><td><?php echo s($etq); ?></td><td><?php echo s(preg_match('/^\d{4}-/', (string)$va) ? $fmt($va) : $va); ?></td><td><?php echo s(preg_match('/^\d{4}-/', (string)$vb) ? $fmt($vb) : $vb); ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($cmp['partidas']): ?>
                        <h4>Partidas que difieren</h4>
                        <div class="ia-tabla-wrap">
                            <table class="ia-tabla"><thead><tr><th>Partida</th><th>Fin (esta)</th><th>Fin (otra)</th><th class="num">Δ</th><th class="num">Actividades distintas</th></tr></thead><tbody>
                            <?php foreach ($cmp['partidas'] as $q): ?>
                                <tr><td><?php echo s($q['partida']); ?></td><td class="fecha"><?php echo s($fmt($q['fin_a'])); ?></td><td class="fecha"><?php echo s($fmt($q['fin_b'])); ?></td>
                                    <td class="num <?php echo $q['delta'] < 0 ? 'prg-mejor' : ($q['delta'] > 0 ? 'prg-peor' : ''); ?>"><?php echo $q['delta'] > 0 ? '+' : ''; ?><?php echo (int)$q['delta']; ?> d</td><td class="num"><?php echo (int)$q['difieren']; ?></td></tr>
                            <?php endforeach; ?>
                            </tbody></table>
                        </div>
                        <details class="ia-details">
                            <summary><?php echo (int)$cmp['total_difieren']; ?> actividades con fechas distintas (las 80 que más difieren)</summary>
                            <div class="ia-tabla-wrap">
                                <table class="ia-tabla"><thead><tr><th>Actividad</th><th>Esta</th><th>La otra</th><th class="num">Δ fin</th></tr></thead><tbody>
                                <?php foreach ($cmp['filas'] as $f): ?>
                                    <tr><td><strong><?php echo s($f['codigo']); ?></strong> <?php echo s($f['nombre']); ?></td>
                                        <td class="fecha"><?php echo s($fmt($f['a']['inicio'])); ?><br><?php echo s($fmt($f['a']['fin'])); ?></td><td class="fecha"><?php echo s($fmt($f['b']['inicio'])); ?><br><?php echo s($fmt($f['b']['fin'])); ?></td>
                                        <td class="num"><?php echo $f['delta'] > 0 ? '+' : ''; ?><?php echo (int)$f['delta']; ?> d</td></tr>
                                <?php endforeach; ?>
                                </tbody></table>
                            </div>
                        </details>
                    <?php else: ?><p class="text-muted">Las dos propuestas producen las mismas fechas.</p><?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="ia-panel" id="impacto">
                <div class="ia-panel-head">
                    <div>
                        <h3>Impacto por partida</h3>
                        <p>Acepta una partida para pasar sus fechas, duraciones y predecesoras propuestas al <strong>programa de obra</strong>. Solo se mueven actividades sin avance y sin fecha fija, y solo si el programa actual sigue igual al que vio la IA. Puedes deshacerlo.</p>
                    </div>
                    <span class="ia-panel-meta"><?php echo count($impacto); ?> partidas · <?php echo $totalAplicables; ?> aplicables</span>
                </div>
                <div class="ia-tabla-wrap">
                    <table class="ia-tabla ia-tabla--partidas">
                        <thead><tr><th>Partida</th><th class="num hide-m">Act.</th><th class="num">Cambian</th><th class="hide-m">Fin actual</th><th class="hide-m">Fin propuesto</th><th class="num">Δ</th><th class="num hide-m">Crít.</th><th class="num hide-m">Obst.</th><th class="num hide-m">Mejoras</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($impacto as $x): ?>
                            <tr>
                                <td><a href="#" class="prg-ver-partida" data-nombre="<?php echo s($x['nombre']); ?>"><?php echo s($x['nombre']); ?></a></td>
                                <td class="num hide-m"><?php echo (int)$x['actividades']; ?></td><td class="num"><?php echo (int)$x['cambian']; ?></td>
                                <td class="fecha hide-m"><?php echo s($fmt($x['fin_actual'])); ?></td><td class="fecha hide-m"><?php echo s($fmt($x['fin_propuesto'])); ?></td>
                                <td class="num <?php echo $x['delta'] < 0 ? 'prg-mejor' : ($x['delta'] > 0 ? 'prg-peor' : ''); ?>"><?php echo $x['delta'] > 0 ? '+' : ''; ?><?php echo (int)$x['delta']; ?> d</td>
                                <td class="num hide-m"><?php echo (int)$x['criticas']; ?></td><td class="num hide-m"><?php echo (int)$x['obstaculos']; ?></td><td class="num hide-m"><?php echo (int)$x['mejoras']; ?></td>
                                <td class="prg-acepta">
                                    <?php if ($x['aceptada']): ?>
                                        <span class="ia-chip ia-chip--ok"><i class="fa-solid fa-check"></i> Aceptada <?php echo s(date('d/m/Y', strtotime($x['aceptada']['fecha']))); ?></span>
                                        <form method="post" action="/programador/deshacer" data-confirmar="¿Deshacer? Las actividades de esta partida vuelven a como estaban antes de aceptar (se pierden ediciones hechas después).">
                                            <input type="hidden" name="proyecto" value="<?php echo $pidP; ?>"><input type="hidden" name="id" value="<?php echo (int)$propuesta['id']; ?>"><input type="hidden" name="partida" value="<?php echo (int)$x['id']; ?>">
                                            <button class="btn btn-sm btn-ghost" type="submit">Deshacer</button>
                                        </form>
                                    <?php elseif ($x['aplicables'] > 0): ?>
                                        <form method="post" action="/programador/aceptar" data-confirmar="¿Aceptar la propuesta de «<?php echo s($x['nombre']); ?>»? Se actualizarán <?php echo (int)$x['aplicables']; ?> actividades en el programa de obra.">
                                            <input type="hidden" name="proyecto" value="<?php echo $pidP; ?>"><input type="hidden" name="id" value="<?php echo (int)$propuesta['id']; ?>"><input type="hidden" name="partida" value="<?php echo (int)$x['id']; ?>">
                                            <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-check"></i> Aceptar (<?php echo (int)$x['aplicables']; ?>)</button>
                                        </form>
                                    <?php else: ?><span class="text-muted">Sin cambios</span><?php endif; ?>
                                </td>
                            </tr>
                            <?php foreach (($x['aceptada']['avisos'] ?? []) as $av): ?><tr><td colspan="10" class="prg-aviso-fila"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo s($av); ?></td></tr><?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if ($curva):
                $W = 860; $H = 220; $n = count($curva);
                $px = fn($i) => round(40 + ($W - 60) * ($n > 1 ? $i / ($n - 1) : 0), 1);
                $py = fn($v) => round($H - 24 - ($H - 44) * $v / 100, 1);
                $linea = function (string $k) use ($curva, $px, $py) { $pts = []; foreach ($curva as $i => $c) if (($c[$k] ?? null) !== null) $pts[] = $px($i) . ',' . $py($c[$k]); return implode(' ', $pts); };
                $hoyIdx = null; foreach ($curva as $i => $c) if ($c['inicio'] <= ($r['hoy'] ?? date('Y-m-d'))) $hoyIdx = $i;
            ?>
                <section class="ia-panel">
                    <div class="ia-panel-head">
                        <div><h3>Curva S: programa actual vs. propuesta</h3><p>Avance físico acumulado, sin ponderar hitos.</p></div>
                        <span class="ia-panel-meta"><?php echo s($fmt($curva[0]['inicio'])); ?> — <?php echo s($fmt($curva[$n - 1]['fin'])); ?></span>
                    </div>
                    <svg viewBox="0 0 <?php echo $W; ?> <?php echo $H; ?>" class="prg-curva" role="img" aria-label="Curva S del programa actual, de la propuesta y real">
                        <?php foreach ([0, 25, 50, 75, 100] as $g): ?><line x1="40" x2="<?php echo $W - 20; ?>" y1="<?php echo $py($g); ?>" y2="<?php echo $py($g); ?>" class="g"/><text x="34" y="<?php echo $py($g) + 4; ?>" text-anchor="end"><?php echo $g; ?>%</text><?php endforeach; ?>
                        <?php if ($hoyIdx !== null): ?><line x1="<?php echo $px($hoyIdx); ?>" x2="<?php echo $px($hoyIdx); ?>" y1="10" y2="<?php echo $H - 24; ?>" class="hoy"/><?php endif; ?>
                        <text x="40" y="<?php echo $H - 6; ?>"><?php echo s($fmt($curva[0]['inicio'])); ?></text><text x="<?php echo $W - 20; ?>" y="<?php echo $H - 6; ?>" text-anchor="end"><?php echo s($fmt($curva[$n - 1]['fin'])); ?></text>
                        <polyline points="<?php echo $linea('plan'); ?>" class="actual"/><polyline points="<?php echo $linea('alt'); ?>" class="prop"/><polyline points="<?php echo $linea('real'); ?>" class="real"/>
                    </svg>
                    <span class="ia-legend prg-leyenda"><span><i class="sw-actual"></i> Programa actual</span><span><i class="sw-prop"></i> Propuesta</span><span><i class="sw-real"></i> Avance real</span></span>
                </section>
            <?php endif; ?>

            <section class="ia-panel" id="comparativo">
                <div class="ia-panel-head">
                    <div><h3>Programa actual vs. propuesto</h3><p>Qué actividad se mueve, cuánto y por qué.</p></div>
                    <span class="ia-panel-meta"><?php echo count($filas); ?> actividades</span>
                </div>
                <div class="ia-command ia-command--sticky prg-cab">
                    <input type="search" data-filtra="#prg-comparativo" placeholder="Buscar actividad, partida, motivo…" aria-label="Buscar en la propuesta" autocomplete="off">
                    <label class="campo-check"><input type="checkbox" id="prg-solo"> Solo las que cambian</label>
                    <select id="prg-partida" aria-label="Filtrar por partida">
                        <option value="">Todas las partidas</option>
                        <?php foreach (array_values(array_unique(array_column($filas, 'partida'))) as $nombrePartida): ?>
                            <option value="<?php echo s($nombrePartida); ?>"><?php echo s($nombrePartida); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="ia-legend prg-leyenda"><span><i class="sw-actual"></i> Actual</span><span><i class="sw-prop"></i> Propuesto</span><span><i class="sw-crit"></i> Ruta crítica</span></span>
                </div>
                <div class="ia-tabla-wrap">
                    <table class="ia-tabla" id="prg-comparativo">
                        <thead><tr><th>Actividad</th><th class="hide-m">Tipo</th><th>Actual</th><th>Propuesto</th><th class="num">Δ fin</th><th class="num hide-m">Holgura</th><th class="prg-gantt-th hide-m">Comparativo</th></tr></thead>
                        <tbody>
                        <?php foreach ($filas as $f):
                            [$x1, $w1] = $pos($f['actual']['inicio'], $f['actual']['fin']);
                            [$x2, $w2] = $pos($f['propuesto']['inicio'], $f['propuesto']['fin']); ?>
                            <tr class="<?php echo $f['cambia'] ? 'cambia' : 'igual'; ?>" data-partida="<?php echo s($f['partida']); ?>">
                                <td>
                                    <strong><?php echo s($f['codigo']); ?></strong> <?php echo s($f['nombre']); ?>
                                    <small class="text-muted"><?php echo s($f['partida']); ?></small>
                                    <?php if ($f['justificacion'] || $f['predecesoras']): ?>
                                        <details class="ia-details">
                                            <summary>Por qué</summary>
                                            <?php if ($f['justificacion']): ?><p><?php echo s($f['justificacion']); ?></p><?php endif; ?>
                                            <?php if ($f['predecesoras']): ?><p class="text-muted">Predecesoras: <?php echo s(implode(', ', array_map(fn($d) => $d['codigo'] . ' ' . $d['tipo'] . ($d['retraso'] ? ($d['retraso'] > 0 ? '+' : '') . $d['retraso'] : ''), $f['predecesoras']))); ?></p><?php endif; ?>
                                        </details>
                                    <?php endif; ?>
                                </td>
                                <td class="hide-m"><?php echo $f['tipo'] ? ($f['tipo'] === 'administrativa' ? 'Adm.' : 'Oper.') : '—'; ?></td>
                                <td class="fecha"><?php echo s($fmt($f['actual']['inicio'])); ?><br><?php echo s($fmt($f['actual']['fin'])); ?></td>
                                <td class="fecha"><?php echo s($fmt($f['propuesto']['inicio'])); ?><br><?php echo s($fmt($f['propuesto']['fin'])); ?></td>
                                <td class="num <?php echo $f['delta_dias'] < 0 ? 'prg-mejor' : ($f['delta_dias'] > 0 ? 'prg-peor' : ''); ?>"><?php echo $f['delta_dias'] > 0 ? '+' : ''; ?><?php echo (int)$f['delta_dias']; ?> d</td>
                                <td class="num hide-m"><?php echo $f['critica'] ? '<span class="prg-peor" title="Ruta crítica">0 d · crít.</span>' : ($f['holgura'] !== null ? (int)$f['holgura'] . ' d' : '—'); ?></td>
                                <td class="prg-gantt hide-m">
                                    <?php if ($hoyPos >= 0 && $hoyPos <= 100): ?><i class="prg-hoy" style="left:<?php echo $hoyPos; ?>%"></i><?php endif; ?>
                                    <i class="prg-barra-actual" style="left:<?php echo $x1; ?>%;width:<?php echo $w1; ?>%"></i>
                                    <i class="prg-barra-prop<?php echo $f['critica'] ? ' crit' : ''; ?>" style="left:<?php echo $x2; ?>%;width:<?php echo $w2; ?>%"></i>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <aside class="ia-inspector" aria-label="Aprobación y hallazgos">
            <section class="ia-card" id="aprobacion">
                <h3><i class="fa-solid fa-shield-halved"></i> Aprobación</h3>
                <p class="text-muted text-sm">Solo se mueven actividades sin avance y sin fecha fija. Acepta por partida y puedes deshacerlo.</p>
                <div class="ia-metrics">
                    <div class="ia-metric"><small>Partidas con cambios</small><strong><?php echo $partidasCambian; ?></strong></div>
                    <div class="ia-metric <?php echo $totalAplicables > 0 ? 'is-success' : ''; ?>"><small>Actividades aplicables</small><strong><?php echo $totalAplicables; ?></strong></div>
                </div>
                <a class="btn btn-primary" href="#impacto"><i class="fa-solid fa-check"></i> Revisar <?php echo $partidasCambian; ?> partida(s)</a>
                <p class="ia-msg">La IA nunca modifica el programa por su cuenta.</p>
            </section>

            <section class="ia-card">
                <h3><i class="fa-solid fa-list-check"></i> Hallazgos</h3>
                <details class="ia-details" open>
                    <summary>Mejoras sugeridas (<?php echo count($r['mejoras'] ?? []); ?>)</summary>
                    <?php if (empty($r['mejoras'])): ?>
                        <p class="text-muted text-sm">La IA no encontró nada que mejorar (o esta propuesta se generó sin la fase de revisión).</p>
                    <?php else: ?>
                        <ul class="ia-finding-list">
                            <?php foreach ($r['mejoras'] as $mi => $m): $est = $seguimiento['m' . $mi]['estado'] ?? 'pendiente'; ?>
                                <li class="ia-finding sev-<?php echo s($m['severidad']); ?> est-<?php echo s($est); ?>">
                                    <span class="ia-sev <?php echo $sevClase[$m['severidad']] ?? ''; ?>"><?php echo s($sevNombre[$m['severidad']] ?? $m['severidad']); ?></span>
                                    <div class="ia-finding-head"><strong><?php echo s($catNombre[$m['categoria']] ?? $m['categoria']); ?></strong><span class="ia-finding-meta"><?php echo $m['actividades'] ? s(implode(', ', array_map(fn($i) => $codigos[$i] ?? $i, $m['actividades']))) : ''; ?></span></div>
                                    <p><?php echo s($m['hallazgo']); ?></p>
                                    <?php if ($m['mejora']): ?><p class="ia-finding-meta">→ <?php echo s($m['mejora']); ?></p><?php endif; ?>
                                    <?php echo $control('m' . $mi); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </details>
                <details class="ia-details" open>
                    <summary>Obstáculos detectados (<?php echo count($r['obstaculos'] ?? []); ?>)</summary>
                    <?php if (empty($r['obstaculos'])): ?>
                        <p class="text-muted text-sm">No se detectaron obstáculos.</p>
                    <?php else: ?>
                        <ul class="ia-finding-list">
                            <?php foreach ($r['obstaculos'] as $oi => $o): $est = $seguimiento['o' . $oi]['estado'] ?? 'pendiente'; ?>
                                <li class="ia-finding sev-<?php echo s($o['severidad']); ?> est-<?php echo s($est); ?>">
                                    <span class="ia-sev <?php echo $sevClase[$o['severidad']] ?? ''; ?>"><?php echo s($sevNombre[$o['severidad']] ?? $o['severidad']); ?></span>
                                    <div class="ia-finding-head"><strong><?php echo s($fuenteNombre[$o['fuente']] ?? $o['fuente']); ?></strong><span class="ia-finding-meta"><?php echo s(implode(', ', array_map(fn($i) => $codigos[$i] ?? $i, $o['actividades']))); ?> · <?php echo $o['origen'] === 'ia' ? 'detectado por la IA' : 'detectado por el sistema'; ?></span></div>
                                    <p><?php echo s($o['descripcion']); ?></p>
                                    <?php if ($o['recomendacion']): ?><p class="ia-finding-meta">→ <?php echo s($o['recomendacion']); ?></p><?php endif; ?>
                                    <?php if (isset($enlaceFuente[$o['fuente']])): ?><a class="text-sm" href="<?php echo $enlaceFuente[$o['fuente']]; ?>?proyecto=<?php echo $pidP; ?>">Abrir <?php echo s(strtolower($fuenteNombre[$o['fuente']] ?? '')); ?> →</a><?php endif; ?>
                                    <?php echo $control('o' . $oi); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </details>
            </section>

            <section class="ia-card">
                <h3><i class="fa-solid fa-circle-info"></i> Cómo leer</h3>
                <span class="ia-legend">
                    <span><i class="sw-actual"></i> Actual</span>
                    <span><i class="sw-prop"></i> Propuesto</span>
                    <span><i class="sw-crit"></i> Ruta crítica</span>
                    <span><i class="sw-real"></i> Avance real</span>
                </span>
                <p class="text-muted text-sm">La propuesta no reemplaza el programa: solo mueve actividades sin avance y sin fecha fija cuando aceptas una partida.</p>
            </section>
        </aside>
    </div>

    <div class="ia-mobile-bar">
        <a class="btn btn-primary" href="#impacto"><i class="fa-solid fa-check"></i> Revisar partidas</a>
        <a class="btn btn-ghost" href="#comparativo"><i class="fa-solid fa-table"></i> Comparativo</a>
    </div>

    <script>
        document.querySelectorAll('.prg-ver-partida').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                var sel = document.getElementById('prg-partida');
                for (var i = 0; i < sel.options.length; i++) if (sel.options[i].value.indexOf(a.dataset.nombre.split(' · ')[0]) === 0) sel.selectedIndex = i;
                sel.dispatchEvent(new Event('change'));
                sel.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
        document.querySelectorAll('.prg-seg').forEach(function (c) {
            var enviar = function () {
                var d = new FormData();
                d.append('proyecto', '<?php echo $pidP; ?>'); d.append('id', '<?php echo (int)$propuesta['id']; ?>'); d.append('clave', c.dataset.clave);
                d.append('estado', c.querySelector('select').value); d.append('responsable', c.querySelector('input').value);
                fetch('/programador/seguimiento', { method: 'POST', body: d, credentials: 'same-origin' });
                c.closest('li').className = c.closest('li').className.replace(/est-\S+/, 'est-' + c.querySelector('select').value);
            };
            c.querySelector('select').addEventListener('change', enviar);
            c.querySelector('input').addEventListener('change', enviar);
        });
        document.getElementById('prg-partida').addEventListener('change', function () {
            var tabla = document.getElementById('prg-comparativo');
            tabla.querySelectorAll('tbody tr').forEach(function (tr) { tr.classList.toggle('fuera-partida', this.value !== '' && tr.dataset.partida !== this.value); }, this);
        });
        document.getElementById('prg-solo').addEventListener('change', function () { document.getElementById('prg-comparativo').classList.toggle('solo-cambios', this.checked); });
    </script>
<?php endif; ?>
