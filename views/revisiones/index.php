<?php
/** Revisiones del proyecto. Variables: $proyecto, $pestana, $filtro, $materiales, $actividades, $herramientas, $cMateriales, $cActividades, $cHerramientas, $historial, $puede */
use Classes\Revisiones;

$pid = (int)$proyecto->id;
$url = fn(array $q = []) => '/revisiones?' . http_build_query(['proyecto' => $pid] + $q);
$tabs = ['resumen' => ['fa-gauge-high', 'Resumen'], 'materiales' => ['fa-cubes', 'Materiales'], 'actividades' => ['fa-list-check', 'Actividades'], 'rfis' => ['fa-circle-question', 'RFI'], 'herramientas' => ['fa-robot', 'Herramientas de IA'], 'historial' => ['fa-clock-rotate-left', 'Historial']];
$filtros = ['correcto' => 'Correctos', 'revisar' => 'Por revisar', 'no_cumple' => 'No cumplen', 'sin_evaluar' => 'Sin evaluar', 'sin_revisar' => 'Sin revisar', 'cambio' => 'Cambiaron'];
$pasa = function (array $e) use ($filtro) {
    return $filtro === '' || ($filtro === 'sin_revisar' ? !$e['revisado'] : ($filtro === 'cambio' ? $e['cambio'] : $e['veredicto'] === $filtro));
};
$tarjeta = function (string $titulo, string $icono, array $c, string $pest) use ($url, $filtros) { ?>
    <div class="card rev-resumen">
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
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-clipboard-check"></i> Revisiones</h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · cumplimiento de normativa y especificaciones, y uso de las herramientas de IA</p>
    </div>
</div>
<nav class="centro-ia-tabs" aria-label="Revisiones">
    <?php foreach ($tabs as $k => [$icono, $nombre]): ?>
        <a href="<?php echo s($url(['pestana' => $k])); ?>" class="centro-ia-tabs__tab<?php echo $k === $pestana ? ' activa' : ''; ?>"<?php echo $k === $pestana ? ' aria-current="page"' : ''; ?>><i class="fa-solid <?php echo $icono; ?>"></i> <?php echo s($nombre); ?></a>
    <?php endforeach; ?>
</nav>
<?php if (!$puede): ?><p class="text-muted text-sm"><i class="fa-solid fa-eye"></i> Solo lectura: marcar revisiones lo hacen el administrador y el cliente.</p><?php endif; ?>

<?php if ($pestana === 'resumen'): ?>
    <div class="rev-grid">
        <?php $tarjeta('Materiales', 'fa-cubes', $cMateriales, 'materiales'); ?>
        <?php $tarjeta('Actividades', 'fa-list-check', $cActividades, 'actividades'); ?>
        <?php $tarjeta('RFI', 'fa-circle-question', $cRfis, 'rfis'); ?>
        <div class="card rev-resumen">
            <h3><i class="fa-solid fa-robot"></i> Herramientas de IA <small class="text-muted">· <?php echo (int)$cHerramientas['total']; ?></small></h3>
            <div class="rev-cifras">
                <a class="rev-chip rev-correcto rev-ok" href="<?php echo s($url(['pestana' => 'herramientas'])); ?>"><i class="fa-solid fa-circle-check"></i> <?php echo (int)$cHerramientas['al_dia']; ?> al día</a>
                <a class="rev-chip rev-cambio" href="<?php echo s($url(['pestana' => 'herramientas'])); ?>"><i class="fa-solid fa-rotate"></i> <?php echo (int)$cHerramientas['desactualizada']; ?> desactualizadas</a>
                <a class="rev-chip rev-sin_evaluar" href="<?php echo s($url(['pestana' => 'herramientas'])); ?>"><i class="fa-regular fa-circle"></i> <?php echo (int)$cHerramientas['sin_ejecutar']; ?> sin ejecutar</a>
            </div>
            <p class="text-sm"><strong><?php echo (int)$cHerramientas['revisadas']; ?></strong> de <?php echo (int)$cHerramientas['total']; ?> con resultados revisados</p>
        </div>
    </div>
    <p class="text-muted text-sm rev-leyenda"><?php foreach (Revisiones::VEREDICTOS as $k => [$etq, $ic]): ?><span class="rev-chip rev-<?php echo $k; ?>"><i class="fa-solid <?php echo $ic; ?>"></i> <?php echo s($etq); ?></span> <?php endforeach; ?>
        <span class="rev-chip"><i class="fa-solid fa-eye"></i> revisado</span> <span class="rev-chip"><i class="fa-regular fa-eye-slash"></i> sin revisar</span> <span class="rev-chip rev-cambio"><i class="fa-solid fa-rotate"></i> cambió después de revisarse</span></p>

<?php elseif ($pestana === 'materiales' || $pestana === 'actividades' || $pestana === 'rfis'): ?>
    <?php $lista = ['materiales' => $materiales, 'actividades' => $actividades, 'rfis' => $rfis][$pestana]; $tipo = ['materiales' => 'material', 'actividades' => 'actividad', 'rfis' => 'rfi'][$pestana]; ?>
    <div class="rev-filtros">
        <a class="btn btn-sm <?php echo $filtro === '' ? 'btn-primary' : 'btn-ghost'; ?>" href="<?php echo s($url(['pestana' => $pestana])); ?>">Todos</a>
        <?php foreach ($filtros as $k => $etq): ?><a class="btn btn-sm <?php echo $filtro === $k ? 'btn-primary' : 'btn-ghost'; ?>" href="<?php echo s($url(['pestana' => $pestana, 'filtro' => $k])); ?>"><?php echo s($etq); ?></a><?php endforeach; ?>
    </div>
    <div class="card">
        <table class="tabla" data-rev-tabla data-proyecto="<?php echo $pid; ?>">
            <thead><tr><th>Revisión</th><th><?php echo ['material' => 'Material', 'actividad' => 'Actividad', 'rfi' => 'RFI'][$tipo]; ?></th><th class="hide-m"><?php echo ['material' => 'Referencia · especialidad', 'actividad' => 'Partida', 'rfi' => 'Estado · responsable'][$tipo]; ?></th><th class="hide-m">Nota</th><th class="hide-m">Revisó</th><th></th></tr></thead>
            <tbody>
            <?php $n = 0; foreach ($lista as $e): if (!$pasa($e)) continue; $n++; ?>
                <tr>
                    <td><?php echo Revisiones::chip($tipo, $e['ref'], $e, $puede, $e['nombre']); ?></td>
                    <td><?php echo s($e['nombre']); ?></td>
                    <td class="hide-m text-muted"><?php echo s($e['detalle']); ?></td>
                    <td class="hide-m text-sm" data-rev-nota><?php echo s($e['nota']); ?></td>
                    <td class="hide-m text-sm text-muted"><?php echo $e['usuario'] !== '' ? s($e['usuario']) . ' · ' . s(substr($e['fecha'], 0, 10)) : ''; ?></td>
                    <td><a class="btn btn-sm btn-ghost" href="<?php echo ['material' => '/materiales/editar?id=' . (int)$e['ref'] . '&proyecto=' . $pid, 'actividad' => '/programa/actividades/editar?id=' . (int)$e['ref'] . '&proyecto=' . $pid, 'rfi' => '/rfi?proyecto=' . $pid . '&ver=' . (int)$e['ref']][$tipo]; ?>" title="Abrir"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$n): ?><tr><td colspan="6" class="text-muted">No hay elementos<?php echo $filtro !== '' ? ' con ese filtro' : ''; ?>.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($pestana === 'herramientas'): ?>
    <div class="card">
        <table class="tabla" data-rev-tabla data-proyecto="<?php echo $pid; ?>">
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
    <p class="text-muted text-sm">«Desactualizada» significa que las actividades o los materiales cambiaron después de la última ejecución. El chip de la izquierda es tu valoración de los resultados (la IA nunca la marca sola).</p>

<?php else: ?>
    <div class="card">
        <table class="tabla">
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
<?php endif; ?>
