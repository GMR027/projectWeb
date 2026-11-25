<?php
/** Revisiones del proyecto. Variables: $proyecto, $pestana, $filtro, $materiales, $actividades, $rfis, $herramientas, $cMateriales, $cActividades, $cHerramientas, $historial, $puede */
use Classes\Revisiones;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/revisiones?' . http_build_query(['proyecto' => $pid] + $q);
$tabs = ['resumen' => ['fa-gauge-high', 'Resumen'], 'materiales' => ['fa-cubes', 'Materiales'], 'actividades' => ['fa-list-check', 'Actividades'], 'rfis' => ['fa-circle-question', 'RFI'], 'herramientas' => ['fa-robot', 'Herramientas de IA'], 'historial' => ['fa-clock-rotate-left', 'Historial']];
$filtros = ['correcto' => 'Correctos', 'revisar' => 'Por revisar', 'no_cumple' => 'No cumplen', 'sin_evaluar' => 'Sin evaluar', 'sin_revisar' => 'Sin revisar', 'cambio' => 'Cambiaron'];
$pasa = function (array $e) use ($filtro) {
    return $filtro === '' || ($filtro === 'sin_revisar' ? !$e['revisado'] : ($filtro === 'cambio' ? $e['cambio'] : $e['veredicto'] === $filtro));
};
$tarjeta = function (string $titulo, string $icono, array $c, string $pest) use ($url, $filtros) { ?>
    <div class="card ia-card rev-resumen">
        <h3><i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s($titulo); ?> <small class="text-muted">· <?php echo (int)$c['total']; ?></small></h3>
        <?php if (!$c['total']): ?><p class="text-muted">Aún no hay elementos.</p><?php else: ?>
        <div class="rev-barra" role="img" aria-label="<?php echo (int)$c['revisados']; ?> de <?php echo (int)$c['total']; ?> revisados"><span style="width: <?php echo round($c['revisados'] / $c['total'] * 100); ?>%"></span></div>
        <p class="text-sm"><strong><?php echo (int)$c['revisados']; ?></strong> de <?php echo (int)$c['total']; ?> revisados</p>
        <div class="rev-cifras">
            <a class="rev-chip rev-correcto rev-ok" href="<?php echo s($url(['pestana' => $pest, 'filtro' => 'correcto'])); ?>"><i class="fa-solid fa-circle-check"></i> <?php echo (int)$c['correcto']; ?> correctos</a>
            <a class="rev-chip rev-revisar" href="<?php echo s($url(['pestana' => $pest, 'filtro' => 'revisar'])); ?>"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo (int)$c['revisar']; ?> por revisar</a>
            <a class="rev-chip rev-no_cumple" href="<?php echo s($url(['pestana' => $pest, 'filtro' => 'no_cumple'])); ?>"><i class="fa-solid fa-circle-xmark"></i> <?php echo (int)$c['no_cumple']; ?> no cumplen</a>
            <a class="rev-chip rev-sin_evaluar" href="<?php echo s($url(['pestana' => $pest, 'filtro' => 'sin_evaluar'])); ?>"><i class="fa-regular fa-circle"></i> <?php echo (int)$c['sin_evaluar']; ?> sin evaluar</a>
            <?php if ($c['cambio']): ?><a class="rev-chip rev-cambio" href="<?php echo s($url(['pestana' => $pest, 'filtro' => 'cambio'])); ?>"><i class="fa-solid fa-rotate"></i> <?php echo (int)$c['cambio']; ?> cambiaron después de revisarse</a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
<?php };

$totalRev = (int)$cMateriales['revisados'] + (int)$cActividades['revisados'] + (int)$cRfis['revisados'];
$totalElem = (int)$cMateriales['total'] + (int)$cActividades['total'] + (int)$cRfis['total'];
$pctRev = $totalElem ? (int)round($totalRev / $totalElem * 100) : 0;
$scoreClass = $pctRev >= 80 ? '' : ($pctRev >= 50 ? 'ia-score--warn' : 'ia-score--danger');

$todos = [];
foreach ([['material', $materiales], ['actividad', $actividades], ['rfi', $rfis]] as [$tipoUltima, $listaUltima]) {
    foreach ($listaUltima as $eUltima) {
        if (!empty($eUltima['fecha']) && strtotime((string)$eUltima['fecha'])) $todos[] = ['tipo' => $tipoUltima] + $eUltima;
    }
}
usort($todos, fn($a, $b) => strcmp((string)$b['fecha'], (string)$a['fecha']));
$ultima = $todos[0] ?? null;

$atencion = [];
foreach ([['material', $materiales], ['actividad', $actividades], ['rfi', $rfis]] as [$tipoAtencion, $listaAtencion]) {
    foreach ($listaAtencion as $eAtencion) {
        if (in_array($eAtencion['veredicto'], ['revisar', 'no_cumple'], true) || !empty($eAtencion['cambio'])) $atencion[] = ['tipo' => $tipoAtencion] + $eAtencion;
    }
}
usort($atencion, fn($a, $b) => strcmp((string)($b['fecha'] ?? ''), (string)($a['fecha'] ?? '')));
$atencion = array_slice($atencion, 0, 8);

$enlaceElemento = function (string $tipo, int $ref) use ($pid): string {
    return match ($tipo) {
        'material' => '/materiales/editar?id=' . $ref . '&proyecto=' . $pid,
        'actividad' => '/programa/actividades/editar?id=' . $ref . '&proyecto=' . $pid,
        'rfi' => '/rfi?proyecto=' . $pid . '&ver=' . $ref,
        default => '#',
    };
};
$conteo = function (array $c): string {
    return (int)$c['revisados'] . '/' . (int)$c['total'];
};
?>
<div class="page-header ia-header">
    <div>
        <span class="ia-eyebrow"><i class="fa-solid fa-traffic-light"></i> <?php echo s(t('Inteligencia artificial')); ?></span>
        <h2><i class="fa-solid fa-traffic-light"></i> Revisiones</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · cumplimiento de normativa y especificaciones, y uso de las herramientas de IA.</p>
        <span class="ia-status"><span class="ia-status-dot"></span><?php echo (int)$pctRev; ?>% revisado<?php echo $ultima ? ' · última revisión ' . s(date('d/m/Y H:i', strtotime((string)$ultima['fecha']))) : ' · sin revisiones todavía'; ?></span>
    </div>
    <div class="ia-header-acciones">
        <a class="btn btn-sm btn-ghost" href="/propuestas-ia?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-inbox"></i> Centro de IA</a>
        <a class="btn btn-sm btn-ghost" href="/historial-cambios?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Historial</a>
        <a class="btn btn-ghost" href="/programa?proyecto=<?php echo $pid; ?>">← <?php echo s(t('Programa')); ?></a>
    </div>
</div>

<div class="ia-trust" role="note">
    <span class="ia-trust-icono"><i class="fa-solid fa-shield-halved"></i></span>
    <p><strong>La IA propone; tú apruebas.</strong> Nada se aplica solo.</p>
    <a href="/historial-cambios?proyecto=<?php echo $pid; ?>">Ver historial de cambios →</a>
</div>

<div class="ia-shell">
    <aside class="ia-rail" aria-label="Dominios de revisión">
        <div class="ia-rail-head"><span>Dominios</span><span class="ia-count"><?php echo count($tabs); ?></span></div>
        <div class="ia-rail-list">
            <?php foreach ($tabs as $k => [$icono, $nombre]):
                $activa = $k === $pestana;
                $totalTab = match ($k) {
                    'materiales' => $cMateriales['total'], 'actividades' => $cActividades['total'], 'rfis' => $cRfis['total'], 'herramientas' => $cHerramientas['total'], default => 0,
                };
                $revTab = match ($k) {
                    'materiales' => $cMateriales['revisados'], 'actividades' => $cActividades['revisados'], 'rfis' => $cRfis['revisados'], 'herramientas' => $cHerramientas['revisadas'], default => 0,
                };
                $pctTab = $totalTab ? (int)round($revTab / $totalTab * 100) : 0;
                $texto = match ($k) {
                    'resumen' => $pctRev . '% revisado',
                    'materiales', 'actividades', 'rfis' => $conteo($k === 'materiales' ? $cMateriales : ($k === 'actividades' ? $cActividades : $cRfis)) . ' revisados',
                    'herramientas' => $cHerramientas['revisadas'] . '/' . $cHerramientas['total'] . ' con resultados revisados',
                    default => 'Últimos cambios',
                };
            ?>
                <a class="ia-rail-item<?php echo $activa ? ' activa' : ''; ?>" href="<?php echo s($url(['pestana' => $k])); ?>"<?php echo $activa ? ' aria-current="page"' : ''; ?>>
                    <span class="ia-rail-dot <?php echo $k === 'resumen' ? ($pctRev >= 80 ? 'ok' : ($pctRev >= 50 ? 'warn' : 'danger')) : ($pctTab >= 80 ? 'ok' : ($pctTab > 0 ? 'warn' : 'info')); ?>"></span>
                    <span class="ia-rail-txt">
                        <strong><i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s($nombre); ?></strong>
                        <small><?php echo s($texto); ?></small>
                        <?php if ($totalTab): ?><span class="ia-rail-progress" aria-hidden="true"><span style="width:<?php echo $pctTab; ?>%"></span></span><?php endif; ?>
                    </span>
                    <?php if ($k === 'resumen'): ?><span class="ia-chip ia-chip--<?php echo $pctRev >= 80 ? 'ok' : ($pctRev >= 50 ? 'warn' : 'danger'); ?>"><?php echo (int)$pctRev; ?>%</span>
                    <?php elseif ($totalTab): ?><span class="ia-chip"><?php echo (int)$totalTab; ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="ia-rail-actions">
            <a class="btn btn-sm btn-ghost" href="/manual#revisiones"><i class="fa-solid fa-book-open"></i> Cómo revisar</a>
        </div>
    </aside>

    <main class="ia-lienzo">
        <?php if ($pestana === 'resumen'): ?>
            <section class="ia-panel">
                <div class="ia-score-caja">
                    <div class="ia-score <?php echo $scoreClass; ?>" style="--ia-score:<?php echo $pctRev; ?>" role="img" aria-label="<?php echo $pctRev; ?>% de elementos revisados">
                        <strong><?php echo (int)$pctRev; ?>%</strong>
                        <small>elementos<br>revisados</small>
                    </div>
                    <div>
                        <span class="ia-eyebrow">Resumen del proyecto</span>
                        <h3 style="margin:.2rem 0 .3rem">Cumplimiento de revisiones</h3>
                        <p class="text-muted text-sm" style="margin:0 0 .8rem">Materiales, actividades, RFI y herramientas de IA en una sola lectura. El chip de cada elemento es tu valoración; la IA nunca la marca sola.</p>
                        <div class="ia-metrics">
                            <div class="ia-metric"><small>Elementos revisados</small><strong><?php echo $totalRev; ?></strong><span>de <?php echo $totalElem; ?></span></div>
                            <div class="ia-metric <?php echo (int)$cMateriales['revisar'] + (int)$cActividades['revisar'] + (int)$cRfis['revisar'] ? 'is-warning' : 'is-success'; ?>"><small>Por revisar</small><strong><?php echo (int)$cMateriales['revisar'] + (int)$cActividades['revisar'] + (int)$cRfis['revisar']; ?></strong><span>requieren tu criterio</span></div>
                            <div class="ia-metric <?php echo (int)$cMateriales['no_cumple'] + (int)$cActividades['no_cumple'] + (int)$cRfis['no_cumple'] ? 'is-danger' : 'is-success'; ?>"><small>No cumplen</small><strong><?php echo (int)$cMateriales['no_cumple'] + (int)$cActividades['no_cumple'] + (int)$cRfis['no_cumple']; ?></strong><span>con observación</span></div>
                            <div class="ia-metric"><small>Herramientas IA</small><strong><?php echo (int)$cHerramientas['revisadas']; ?>/<?php echo (int)$cHerramientas['total']; ?></strong><span>con resultados revisados</span></div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="ia-dominios">
                <?php $tarjeta('Materiales', 'fa-cubes', $cMateriales, 'materiales'); ?>
                <?php $tarjeta('Actividades', 'fa-list-check', $cActividades, 'actividades'); ?>
                <?php $tarjeta('RFI', 'fa-circle-question', $cRfis, 'rfis'); ?>
                <div class="card ia-card rev-resumen">
                    <h3><i class="fa-solid fa-robot"></i> Herramientas de IA <small class="text-muted">· <?php echo (int)$cHerramientas['total']; ?></small></h3>
                    <div class="rev-cifras">
                        <a class="rev-chip rev-correcto rev-ok" href="<?php echo s($url(['pestana' => 'herramientas'])); ?>"><i class="fa-solid fa-circle-check"></i> <?php echo (int)$cHerramientas['al_dia']; ?> al día</a>
                        <a class="rev-chip rev-cambio" href="<?php echo s($url(['pestana' => 'herramientas'])); ?>"><i class="fa-solid fa-rotate"></i> <?php echo (int)$cHerramientas['desactualizada']; ?> desactualizadas</a>
                        <a class="rev-chip rev-sin_evaluar" href="<?php echo s($url(['pestana' => 'herramientas'])); ?>"><i class="fa-regular fa-circle"></i> <?php echo (int)$cHerramientas['sin_ejecutar']; ?> sin ejecutar</a>
                    </div>
                    <p class="text-sm"><strong><?php echo (int)$cHerramientas['revisadas']; ?></strong> de <?php echo (int)$cHerramientas['total']; ?> con resultados revisados</p>
                </div>
            </div>

            <section class="ia-panel">
                <div class="ia-panel-head">
                    <div><h3>Requieren atención</h3><p>Lo que está por revisar, no cumple o cambió después de revisarse.</p></div>
                    <span class="ia-panel-meta"><?php echo count($atencion); ?> elementos</span>
                </div>
                <?php if (!$atencion): ?>
                    <div class="ia-empty">
                        <span class="ia-empty-icono"><i class="fa-solid fa-circle-check"></i></span>
                        <h3>Todo al día</h3>
                        <p class="text-muted">No hay elementos por revisar, no conformes ni cambios posteriores a la revisión.</p>
                    </div>
                <?php else: ?>
                    <div class="ia-tabla-wrap">
                        <table class="tabla ia-tabla">
                            <thead><tr><th>Revisión</th><th>Elemento</th><th class="hide-m">Referencia / especialidad</th><th class="hide-m">Nota</th><th class="hide-m">Revisó</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($atencion as $e): ?>
                                <tr>
                                    <td><?php echo Revisiones::chip($e['tipo'], $e['ref'], $e, $puede, $e['nombre']); ?></td>
                                    <td><?php echo s($e['nombre']); ?></td>
                                    <td class="hide-m text-muted"><?php echo s($e['detalle']); ?></td>
                                    <td class="hide-m text-sm" data-rev-nota><?php echo s($e['nota']); ?></td>
                                    <td class="hide-m text-sm text-muted"><?php echo $e['usuario'] !== '' ? s($e['usuario']) . ' · ' . s(substr($e['fecha'], 0, 10)) : ''; ?></td>
                                    <td><a class="btn btn-sm btn-ghost" href="<?php echo s($enlaceElemento($e['tipo'], (int)$e['ref'])); ?>" title="Abrir"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <p class="text-muted text-sm rev-leyenda"><?php foreach (Revisiones::VEREDICTOS as $k => [$etq, $ic]): ?><span class="rev-chip rev-<?php echo $k; ?>"><i class="fa-solid <?php echo $ic; ?>"></i> <?php echo s($etq); ?></span> <?php endforeach; ?>
                <span class="rev-chip"><i class="fa-solid fa-eye"></i> revisado</span> <span class="rev-chip"><i class="fa-regular fa-eye-slash"></i> sin revisar</span> <span class="rev-chip rev-cambio"><i class="fa-solid fa-rotate"></i> cambió después de revisarse</span></p>

        <?php elseif ($pestana === 'materiales' || $pestana === 'actividades' || $pestana === 'rfis'): ?>
            <?php $lista = ['materiales' => $materiales, 'actividades' => $actividades, 'rfis' => $rfis][$pestana]; $tipo = ['materiales' => 'material', 'actividades' => 'actividad', 'rfis' => 'rfi'][$pestana]; ?>
            <section class="ia-panel">
                <div class="ia-panel-head">
                    <div><h3><?php echo s($tabs[$pestana][1]); ?></h3><p><?php echo count($lista); ?> elementos · marca ✅ ⚠ ❌ y deja una nota cuando haga falta.</p></div>
                    <span class="ia-panel-meta"><?php echo $pestana === 'materiales' ? $conteo($cMateriales) : ($pestana === 'actividades' ? $conteo($cActividades) : $conteo($cRfis)); ?> revisados</span>
                </div>
                <div class="rev-filtros ia-command">
                    <a class="btn btn-sm <?php echo $filtro === '' ? 'btn-primary' : 'btn-ghost'; ?>" href="<?php echo s($url(['pestana' => $pestana])); ?>">Todos</a>
                    <?php foreach ($filtros as $k => $etq): ?><a class="btn btn-sm <?php echo $filtro === $k ? 'btn-primary' : 'btn-ghost'; ?>" href="<?php echo s($url(['pestana' => $pestana, 'filtro' => $k])); ?>"><?php echo s($etq); ?></a><?php endforeach; ?>
                </div>
                <div class="ia-tabla-wrap">
                    <table class="tabla ia-tabla" <?php echo pag_attr($proyecto); ?> data-rev-tabla data-proyecto="<?php echo $pid; ?>">
                        <thead><tr><th>Revisión</th><th><?php echo ['material' => 'Material', 'actividad' => 'Actividad', 'rfi' => 'RFI'][$tipo]; ?></th><th class="hide-m"><?php echo ['material' => 'Referencia · especialidad', 'actividad' => 'Partida', 'rfi' => 'Estado · responsable'][$tipo]; ?></th><th class="hide-m">Nota</th><th class="hide-m">Revisó</th><th></th></tr></thead>
                        <tbody>
                        <?php $n = 0; foreach ($lista as $e): if (!$pasa($e)) continue; $n++; ?>
                            <tr>
                                <td><?php echo Revisiones::chip($tipo, $e['ref'], $e, $puede, $e['nombre']); ?></td>
                                <td><?php echo s($e['nombre']); ?></td>
                                <td class="hide-m text-muted"><?php echo s($e['detalle']); ?></td>
                                <td class="hide-m text-sm" data-rev-nota><?php echo s($e['nota']); ?></td>
                                <td class="hide-m text-sm text-muted"><?php echo $e['usuario'] !== '' ? s($e['usuario']) . ' · ' . s(substr($e['fecha'], 0, 10)) : ''; ?></td>
                                <td><a class="btn btn-sm btn-ghost" href="<?php echo s($enlaceElemento($tipo, (int)$e['ref'])); ?>" title="Abrir"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$n): ?><tr><td colspan="6" class="text-muted">No hay elementos<?php echo $filtro !== '' ? ' con ese filtro' : ''; ?>.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($pestana === 'herramientas'): ?>
            <div class="ia-kpis ia-kpis--4">
                <div class="suite-kpi is-success"><span class="suite-kpi-icono"><i class="fa-solid fa-circle-check"></i></span><span class="suite-kpi-txt"><small>Al día</small><strong><?php echo (int)$cHerramientas['al_dia']; ?></strong><span>resultados revisados</span></span></div>
                <div class="suite-kpi is-warning"><span class="suite-kpi-icono"><i class="fa-solid fa-rotate"></i></span><span class="suite-kpi-txt"><small>Desactualizadas</small><strong><?php echo (int)$cHerramientas['desactualizada']; ?></strong><span>los datos cambiaron</span></span></div>
                <div class="suite-kpi"><span class="suite-kpi-icono"><i class="fa-regular fa-circle"></i></span><span class="suite-kpi-txt"><small>Sin ejecutar</small><strong><?php echo (int)$cHerramientas['sin_ejecutar']; ?></strong><span>nunca se han usado</span></span></div>
                <div class="suite-kpi is-info"><span class="suite-kpi-icono"><i class="fa-solid fa-robot"></i></span><span class="suite-kpi-txt"><small>Total</small><strong><?php echo (int)$cHerramientas['total']; ?></strong><span>herramientas del proyecto</span></span></div>
            </div>
            <section class="ia-panel">
                <div class="ia-panel-head"><div><h3>Herramientas de IA</h3><p>«Desactualizada» significa que las actividades o los materiales cambiaron después de la última ejecución. El chip de la izquierda es tu valoración.</p></div></div>
                <div class="ia-tabla-wrap">
                    <table class="tabla ia-tabla" <?php echo pag_attr($proyecto); ?> data-rev-tabla data-proyecto="<?php echo $pid; ?>">
                        <thead><tr><th>Resultados</th><th>Herramienta</th><th>Uso</th><th class="hide-m">Última ejecución</th><th class="hide-m">Nota</th></tr></thead>
                        <tbody>
                        <?php foreach ($herramientas as $h): [$etqE, $icE] = Revisiones::ESTADOS_HERRAMIENTA[$h['estado']]; ?>
                            <tr>
                                <td><?php echo Revisiones::chip('herramienta', $h['clave'], $h, $puede, $h['titulo']); ?></td>
                                <td><?php echo s($h['titulo']); ?></td>
                                <td><span class="rev-chip <?php echo $h['estado'] === 'al_dia' ? 'rev-correcto rev-ok' : ($h['estado'] === 'desactualizada' ? 'rev-cambio' : 'rev-sin_evaluar'); ?>" title="<?php echo s($etqE); ?>"><i class="fa-solid <?php echo $icE; ?>"></i> <?php echo $h['estado'] === 'desactualizada' ? 'Volver a ejecutar' : ($h['estado'] === 'al_dia' ? 'Al día' : 'Sin ejecutar'); ?></span></td>
                                <td class="hide-m text-sm text-muted"><?php echo $h['ejecutada'] ? s(substr($h['ejecutada'], 0, 16)) : '—'; ?></td>
                                <td class="hide-m text-sm" data-rev-nota><?php echo s($h['nota']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php else: ?>
            <section class="ia-panel">
                <div class="ia-panel-head"><div><h3>Historial de revisiones</h3><p>Quién cambió cada veredicto y cuándo.</p></div></div>
                <div class="ia-tabla-wrap">
                    <table class="tabla ia-tabla" <?php echo pag_attr($proyecto); ?>>
                        <thead><tr><th>Fecha</th><th>Tipo</th><th>Elemento</th><th>Cambio</th><th class="hide-m">Nota</th><th>Quién</th></tr></thead>
                        <tbody>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td class="mono"><?php echo s(substr($h['creado'], 0, 16)); ?></td>
                                <td><?php echo s(Revisiones::TIPOS[$h['tipo']] ?? $h['tipo']); ?></td>
                                <td><?php echo s($h['nombre'] ?: $h['ref']); ?></td>
                                <td>
                                    <span class="rev-chip rev-<?php echo s($h['veredicto_antes']); ?>"><i class="fa-solid <?php echo Revisiones::VEREDICTOS[$h['veredicto_antes']][1] ?? 'fa-circle'; ?>"></i></span> →
                                    <span class="rev-chip rev-<?php echo s($h['veredicto_despues']); ?>"><i class="fa-solid <?php echo Revisiones::VEREDICTOS[$h['veredicto_despues']][1] ?? 'fa-circle'; ?>"></i> <?php echo s(Revisiones::VEREDICTOS[$h['veredicto_despues']][0] ?? ''); ?></span>
                                    <small class="text-muted"><?php echo (int)$h['revisado_despues'] ? 'revisado' : 'sin revisar'; ?></small>
                                </td>
                                <td class="hide-m text-sm"><?php echo s($h['nota']); ?></td>
                                <td><?php echo s($h['usuario']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$historial): ?><tr><td colspan="6" class="text-muted">Aún no hay cambios de estado.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!$puede): ?><p class="text-muted text-sm"><i class="fa-solid fa-eye"></i> Solo lectura: marcar revisiones lo hacen el administrador y el cliente.</p><?php endif; ?>
    </main>

    <aside class="ia-inspector" aria-label="Leyenda y última revisión">
        <section class="ia-card">
            <h3><i class="fa-solid fa-circle-info"></i> Cómo leer</h3>
            <div class="ia-legend">
                <?php foreach (Revisiones::VEREDICTOS as $k => [$etq, $ic]): ?><span><span class="rev-chip rev-<?php echo $k; ?>"><i class="fa-solid <?php echo $ic; ?>"></i> <?php echo s($etq); ?></span></span><?php endforeach; ?>
            </div>
            <div class="ia-legend">
                <span><span class="rev-chip"><i class="fa-solid fa-eye"></i> revisado</span></span>
                <span><span class="rev-chip"><i class="fa-regular fa-eye-slash"></i> sin revisar</span></span>
                <span><span class="rev-chip rev-cambio"><i class="fa-solid fa-rotate"></i> cambió</span></span>
            </div>
            <p class="text-muted text-sm">El chip de la izquierda es tu valoración de los resultados. La IA nunca la marca sola.</p>
        </section>

        <section class="ia-card">
            <h3><i class="fa-solid fa-user-check"></i> Última revisión</h3>
            <?php if ($ultima): ?>
                <div class="ia-metrics">
                    <div class="ia-metric"><small>Revisó</small><strong style="font-size:1.3rem"><?php echo s($ultima['usuario'] ?: '—'); ?></strong></div>
                    <div class="ia-metric"><small>Cuándo</small><strong style="font-size:1.2rem"><?php echo s(substr((string)$ultima['fecha'], 0, 16)); ?></strong></div>
                </div>
                <a class="btn btn-sm btn-ghost" href="<?php echo s($enlaceElemento((string)$ultima['tipo'], (int)$ultima['ref'])); ?>">Abrir el último elemento →</a>
            <?php else: ?>
                <p class="text-muted text-sm">Aún no hay revisiones marcadas en este proyecto.</p>
            <?php endif; ?>
        </section>

        <section class="ia-card">
            <h3><i class="fa-solid fa-eye"></i> Permisos</h3>
            <p class="text-muted text-sm"><?php echo $puede ? 'Puedes marcar y desmarcar revisiones. Cada cambio queda en el historial con tu nombre.' : 'Solo lectura: marcar revisiones lo hacen el administrador y el cliente.'; ?></p>
            <a class="btn btn-sm btn-ghost" href="/manual#revisiones"><i class="fa-solid fa-book-open"></i> Ver en el manual</a>
        </section>
    </aside>
</div>

<div class="ia-mobile-bar">
    <a class="btn btn-primary" href="<?php echo s($url(['pestana' => 'resumen'])); ?>"><i class="fa-solid fa-gauge-high"></i> Resumen</a>
    <a class="btn btn-ghost" href="/programa?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-diagram-project"></i> Programa</a>
</div>
