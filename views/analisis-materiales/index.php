<?php // Variables: $proyecto, $filtros, $corridas, $corrida (análisis mostrado o null), $lista, $lotes (registro por lote de la IA), $conteos, $mensaje
use Classes\AnalisisMateriales;
$pid = (int)$proyecto->id;
$sevNombre = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$accionNombre = ['nota' => 'Sin cambio de datos', 'renombrar_material' => 'Renombra el material', 'sincronizar_msr_material' => 'Actualiza fechas del MSR', 'sincronizar_msr_submittal' => 'Actualiza estatus del MSR', 'sincronizar_instalacion' => 'Recalcula instalación del MSR'];
$url = fn(array $cambia) => '/analisis-materiales?' . http_build_query(array_filter(['proyecto' => $pid] + $cambia + $filtros, fn($v) => $v !== '' && $v !== 0));
$nombreCorrida = fn($c) => ($c['nombre'] !== '' ? $c['nombre'] : 'Análisis') . ' · ' . date('d/m/Y H:i', strtotime($c['creado'])) . ($c['modelo'] ? ' · ' . $c['modelo'] : '');
?>
<?php require_once __DIR__ . '/../templates/herramientas-proyecto.php'; pestanas_herramienta('revision', '/analisis-materiales', $proyecto); ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-boxes-stacked"></i> <?php echo s(t('Análisis de materiales')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · materiales, submittals pendientes, información incompleta y coherencia con el MSR (Reportes de materiales), con propuestas de nombre completo (nombre + marca + modelo + valor extra). Cada propuesta la <strong>aceptas o rechazas</strong>; nada se cambia sin tu aprobación.
            <?php if (!empty($conteosGlobales)): ?> · <strong><?php echo (int)$conteosGlobales['accionables']; ?></strong> accionables y <strong><?php echo (int)$conteosGlobales['observaciones']; ?></strong> observaciones en todo el proyecto<?php endif; ?></p>
    </div>
    <div class="inc-acciones">
        <form method="post" action="/analisis-materiales/revisar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-ghost" type="submit" title="Aplica las reglas del sistema, sin IA"><i class="fa-solid fa-rotate"></i> Revisar ahora (sin IA)</button></form>
        <?php $qExport = array_filter(['proyecto' => $pid, 'corrida' => $filtros['corrida'], 'estado' => $filtros['estado'], 'severidad' => $filtros['severidad'], 'categoria' => $filtros['categoria'], 'origen' => $filtros['origen']], fn($v) => $v !== '' && $v !== 0); ?>
        <a class="btn btn-ghost" href="/analisis-materiales/exportar?<?php echo http_build_query($qExport + ['formato' => 'pdf']); ?>" title="Descargar el reporte agrupado por problema"><i class="fa-solid fa-file-pdf"></i> Reporte PDF</a>
        <a class="btn btn-ghost" href="/analisis-materiales/exportar?<?php echo http_build_query($qExport + ['formato' => 'ods']); ?>" title="Descargar el reporte agrupado en hoja de cálculo"><i class="fa-solid fa-file-excel"></i> Reporte ODS</a>
        <a href="/msr?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">MSR →</a>
    </div>
</div>

<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<?php if (is_admin()): ?>
<details class="card inc-salud">
    <summary><i class="fa-solid fa-wand-magic-sparkles"></i> Materiales inteligentes:
        <strong><?php echo !empty($inteligentes) ? 'activo' : 'inactivo'; ?></strong>
        · <?php echo (int)($normativaFrags ?? 0); ?> fragmento(s) de normativa indexados
    </summary>
    <p class="text-muted text-sm">Con esto activo, la app de escritorio verifica cada material nuevo o modificado contra la normativa y las especificaciones del proyecto, y avisa si no cumple. La web solo muestra el veredicto y bloquea submittals con materiales no aprobados; nunca escribe el veredicto.</p>
    <form method="post" action="/analisis-materiales/inteligentes">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <input type="hidden" name="activar" value="<?php echo !empty($inteligentes) ? '0' : '1'; ?>">
        <button class="btn btn-sm <?php echo !empty($inteligentes) ? 'btn-ghost' : 'btn-primary'; ?>" type="submit">
            <i class="fa-solid fa-toggle-<?php echo !empty($inteligentes) ? 'on' : 'off'; ?>"></i>
            <?php echo !empty($inteligentes) ? 'Desactivar Materiales inteligentes' : 'Activar Materiales inteligentes'; ?>
        </button>
    </form>
</details>
<?php endif; ?>

<?php if (!empty($metricas)): $mm = $metricas; ?>
<details class="card inc-salud">
    <summary><i class="fa-solid fa-heart-pulse"></i> Salud de la revisión
        · <strong><?php echo $mm['precision'] === null ? 'sin cierres aún' : s($mm['precision']) . '% de precisión'; ?></strong>
        · <?php echo (int)$mm['accionables']; ?> accionables · <?php echo (int)$mm['observaciones']; ?> observaciones
        <?php if ($mm['vencidas']): ?> · <span class="text-danger"><?php echo (int)$mm['vencidas']; ?> vencidas</span><?php endif; ?>
        <?php if ($mm['duplicados']): ?> · <?php echo (int)$mm['duplicados']; ?> duplicadas<?php endif; ?>
    </summary>
    <p class="text-muted text-sm">Precisión = aceptadas / (aceptadas + rechazadas) de las propuestas que cambian datos. La antigüedad máxima de un pendiente es de <?php echo (int)$mm['antiguedad']; ?> día(s).</p>
    <div class="inc-salud-grid">
        <div><small>Pendientes accionables</small><strong><?php echo (int)$mm['accionables']; ?></strong></div>
        <div><small>Observaciones</small><strong><?php echo (int)$mm['observaciones']; ?></strong></div>
        <div><small>Vencidas</small><strong><?php echo (int)$mm['vencidas']; ?></strong></div>
        <div><small>Aceptadas</small><strong><?php echo (int)$mm['aceptada']; ?></strong></div>
        <div><small>Rechazadas</small><strong><?php echo (int)$mm['rechazada']; ?></strong></div>
        <div><small>Precisión</small><strong><?php echo $mm['precision'] === null ? '—' : s($mm['precision']) . '%'; ?></strong></div>
    </div>
    <?php if (!empty($mm['porGrupo'])): ?>
    <table class="inc-gtabla text-sm">
        <thead><tr><th>Problema</th><th>Pendientes</th><th>Aceptadas</th><th>Rechazadas</th><th>Precisión</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($mm['porGrupo'], 0, 12) as $g): ?>
            <tr><td><?php echo s($g['etiqueta']); ?></td><td><?php echo (int)$g['pendiente']; ?></td><td><?php echo (int)$g['aceptada']; ?></td><td><?php echo (int)$g['rechazada']; ?></td><td><?php echo $g['precision'] === null ? '—' : s($g['precision']) . '%'; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php if (!empty($mm['motivos'])): ?><p class="text-muted text-sm">Motivos de rechazo: <?php $ms = []; foreach ($mm['motivos'] as $k => $n) $ms[] = (\Classes\HallazgosIA::MOTIVOS[$k] ?? $k) . ' (' . $n . ')'; echo s(implode(' · ', $ms)); ?></p><?php endif; ?>
</details>
<?php endif; ?>

<?php if ($corrida || $todas): $cid = $corrida ? (int)$corrida['id'] : 0; ?>
<div class="card prg-barra inc-barra">
    <label>Análisis
        <select onchange="location.href='/analisis-materiales?proyecto=<?php echo $pid; ?>&corrida='+this.value">
            <option value="todas"<?php echo $todas ? ' selected' : ''; ?>>Todas las corridas — backlog completo (<?php echo (int)$conteosGlobales['accionables']; ?> accionables)</option>
            <?php foreach ($corridas as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>"<?php echo (int)$c['id'] === $cid && !$todas ? ' selected' : ''; ?>><?php echo s(($c['guardada'] ? '★ ' : '') . $nombreCorrida($c) . ' — ' . (int)$c['pendientes'] . ' pendientes'); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php if ($corrida): ?>
    <form method="post" action="/analisis-materiales/guardar" class="prg-guardar">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="corrida" value="<?php echo $cid; ?>">
        <?php if ($corrida['guardada']): ?>
            <span class="prg-guardada"><i class="fa-solid fa-bookmark"></i> Guardado<?php echo $corrida['nombre'] !== '' ? ': ' . s($corrida['nombre']) : ''; ?></span>
            <button class="btn btn-sm btn-ghost" type="submit" name="quitar" value="1">Quitar de guardados</button>
        <?php else: ?>
            <input type="text" name="nombre" maxlength="150" aria-label="Nombre del análisis" placeholder="Nombre (ej. Revisión semana 42)" value="<?php echo s('Análisis ' . date('d/m/Y', strtotime($corrida['creado']))); ?>">
            <button class="btn btn-sm btn-primary" type="submit"><i class="fa-regular fa-bookmark"></i> Guardar análisis</button>
        <?php endif; ?>
    </form>
    <form method="post" action="/analisis-materiales/eliminar" data-confirmar="¿Eliminar este análisis y su historial? Los cambios ya aplicados al proyecto se conservan, pero ya no podrás revertirlos desde aquí.">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="corrida" value="<?php echo $cid; ?>">
        <button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-trash"></i> Eliminar análisis</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$lotesTotal = count($lotes); $lotesError = count(array_filter($lotes, fn($l) => $l['estado'] === 'error')); $lotesOk = $lotesTotal - $lotesError;
if ($corrida): ?>
<div class="alerta <?php echo !$lotesTotal ? 'alerta-info' : ($lotesError ? 'alerta-error' : 'alerta-exito'); ?> inc-completo" role="status">
    <?php if (!$lotesTotal): ?>
        <i class="fa-solid fa-circle-info"></i> <strong>Solo reglas del sistema:</strong> este análisis no pasó por la IA (o es anterior al registro por lote).
    <?php elseif ($lotesError): ?>
        <i class="fa-solid fa-triangle-exclamation"></i> <strong>Análisis incompleto:</strong> <?php echo $lotesOk; ?> de <?php echo $lotesTotal; ?> lotes revisados; <?php echo $lotesError; ?> fallaron. Vuelve a analizar desde la app de escritorio.
    <?php else: ?>
        <i class="fa-solid fa-circle-check"></i> <strong>Análisis completo:</strong> <?php echo $lotesTotal; ?> de <?php echo $lotesTotal; ?> lotes revisados por la IA.
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($lotes)): ?>
<details class="card inc-lotes"<?php echo $lotesError ? ' open' : ''; ?>>
    <summary><strong>Registro por lote de la IA</strong> · <?php echo count($lotes); ?> lote(s)<?php echo $lotesError ? ' · <span class="text-danger">' . $lotesError . ' con error</span>' : ''; ?></summary>
    <p class="text-muted text-sm">Lo que la IA revisó en este análisis, lote por lote: materiales cubiertos y hallazgos que propuso (incluye los que ya habías aceptado o rechazado antes y por eso no se repiten arriba).</p>
    <?php foreach ($lotes as $l): ?>
        <details class="inc-acts text-sm">
            <summary><strong><?php echo s($l['titulo']); ?></strong> · <?php echo $l['estado'] === 'error' ? '<span class="text-danger">falló: ' . s($l['error']) . '</span> (estos materiales no se revisaron; vuelve a analizar)' : count($l['hallazgos']) . ' hallazgo(s) de la IA'; ?></summary>
            <?php if ($l['hallazgos']): ?>
                <ul><?php foreach ($l['hallazgos'] as $h): ?><li><span class="prg-sev"><?php echo s($sevNombre[$h['severidad']] ?? $h['severidad']); ?></span> <?php echo s($h['titulo']); ?> <small class="text-muted">· <?php echo s(AnalisisMateriales::CATEGORIAS[$h['categoria']] ?? $h['categoria']); ?> · <?php echo s($accionNombre[$h['accion']] ?? $h['accion']); ?></small></li><?php endforeach; ?></ul>
            <?php elseif ($l['estado'] === 'ok'): ?><p>Sin hallazgos: la IA no encontró nada que corregir en estos materiales.</p><?php endif; ?>
            <?php if ($l['materiales']): ?>
                <details><summary><?php echo count($l['materiales']); ?> material(es) analizados</summary>
                    <ul><?php foreach ($l['materiales'] as $m): ?><li><a href="/materiales/editar?id=<?php echo (int)$m['id']; ?>"><?php echo s($m['nombre']); ?></a></li><?php endforeach; ?></ul>
                </details>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</details>
<?php endif; ?>

<div class="prg-resumen inc-resumen">
    <a class="card" href="<?php echo s($url(['estado' => 'pendiente', 'tipo' => 'accionable'])); ?>"><small>Pendientes accionables</small><strong id="inc-n-pendiente"><?php echo (int)($conteos['accionables'] ?? $conteos['pendiente']); ?></strong><span><span id="inc-n-criticas"><?php echo (int)$conteos['criticas']; ?></span> de severidad alta o crítica</span></a>
    <a class="card" href="<?php echo s($url(['estado' => 'pendiente', 'tipo' => 'observacion'])); ?>"><small>Observaciones</small><strong id="inc-n-observaciones"><?php echo (int)($conteos['observaciones'] ?? 0); ?></strong><span>solo informan, no cambian datos</span></a>
    <a class="card" href="<?php echo s($url(['estado' => 'pendiente'])); ?>"><small>Vencidas</small><strong id="inc-n-vencidas"><?php echo (int)($conteos['vencidas'] ?? 0); ?></strong><span>requieren volver a analizar</span></a>
    <a class="card" href="<?php echo s($url(['estado' => 'aceptada'])); ?>"><small>Aceptadas</small><strong id="inc-n-aceptada"><?php echo (int)$conteos['aceptada']; ?></strong></a>
    <a class="card" href="<?php echo s($url(['estado' => 'rechazada'])); ?>"><small>Rechazadas</small><strong id="inc-n-rechazada"><?php echo (int)$conteos['rechazada']; ?></strong></a>
</div>

<form method="get" action="/analisis-materiales" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="corrida" value="<?php echo (int)$filtros['corrida']; ?>">
    <label>Estado <select name="estado" onchange="this.form.submit()"><?php foreach (['pendiente' => 'Pendientes', 'aceptada' => 'Aceptadas', 'rechazada' => 'Rechazadas', '' => 'Todas'] as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['estado'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Severidad <select name="severidad" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($sevNombre as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['severidad'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Categoría <select name="categoria" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (AnalisisMateriales::CATEGORIAS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['categoria'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Detectada por <select name="origen" onchange="this.form.submit()"><option value="">Todas</option><option value="sistema"<?php echo $filtros['origen'] === 'sistema' ? ' selected' : ''; ?>>Sistema</option><option value="ia"<?php echo $filtros['origen'] === 'ia' ? ' selected' : ''; ?>>IA</option></select></label>
    <label>Tipo <select name="tipo" onchange="this.form.submit()"><option value="">Todas</option><option value="accionable"<?php echo $filtros['tipo'] === 'accionable' ? ' selected' : ''; ?>>Accionables</option><option value="observacion"<?php echo $filtros['tipo'] === 'observacion' ? ' selected' : ''; ?>>Observaciones</option></select></label>
    <input type="search" data-filtra=".inc-lista" placeholder="Buscar…" aria-label="Buscar hallazgos" autocomplete="off">
</form>

<div class="inc-vistas" role="group" aria-label="Forma de ver los hallazgos">
    <a class="btn btn-sm <?php echo $vista === 'lista' ? 'btn-primary' : 'btn-ghost'; ?>" href="<?php echo s($url(['vista' => 'lista'])); ?>"><i class="fa-solid fa-list"></i> Lista</a>
    <a class="btn btn-sm <?php echo $vista === 'grupos' ? 'btn-primary' : 'btn-ghost'; ?>" href="<?php echo s($url(['vista' => 'grupos'])); ?>"><i class="fa-solid fa-layer-group"></i> Por problema</a>
    <span class="text-muted text-sm">«Por problema» junta todos los materiales con la misma falla para corregirlos de una vez y sacar el reporte.</span>
</div>

<?php if (!$lista): ?>
    <div class="card prg-vacio">
        <h3><?php echo !$corrida ? 'Aún no hay análisis' : ($conteos['pendiente'] + $conteos['aceptada'] + $conteos['rechazada'] ? 'Nada con estos filtros' : 'Este análisis no encontró hallazgos nuevos'); ?></h3>
        <p class="text-muted">Pulsa <em>Revisar ahora (sin IA)</em> para aplicar las reglas del sistema (información incompleta, coherencia material ↔ MSR, submittals pendientes o rechazados, fechas y duplicados). Para que además la IA proponga el <strong>nombre completo</strong> de cada material (nombre base + marca + modelo + valor extra) y detecte inconsistencias entre materiales, abre la app de escritorio en <strong>Herramientas IA → Análisis de materiales</strong>.</p>
    </div>
<?php else: ?>
<?php if ($vista === 'grupos'): ?>
    <div class="inc-grupos">
        <?php foreach ($grupos as $g):
            $accionables = array_values(array_filter($g['items'], fn($x) => $x['estado'] === 'pendiente' && ($x['solucion']['accion'] ?? 'nota') !== 'nota'));
            $aplicables = array_values(array_filter($accionables, fn($x) => \Classes\HallazgosIA::accionSegura((string)($x['solucion']['accion'] ?? 'nota'))));
            $riesgo = count($accionables) - count($aplicables); ?>
            <details class="card inc-grupo" open>
                <summary>
                    <span class="prg-sev"><?php echo s($sevNombre[$g['severidad']] ?? $g['severidad']); ?></span>
                    <strong><?php echo s($g['etiqueta']); ?></strong>
                    <span class="inc-grupo-n"><?php echo (int)$g['total']; ?> hallazgo(s)</span>
                    <span class="text-muted text-sm"><?php echo (int)$g['pendientes']; ?> pendiente(s)</span>
                    <?php if ($aplicables): ?>
                        <button type="button" class="btn btn-sm btn-primary inc-grupo-aplicar" data-ids="<?php echo s(json_encode(array_map(fn($x) => (int)$x['id'], $aplicables))); ?>"><i class="fa-solid fa-check-double"></i> Corregir las seguras (<?php echo count($aplicables); ?>)</button>
                    <?php endif; ?>
                    <?php if ($riesgo): ?><span class="text-muted text-sm"><?php echo $riesgo; ?> requieren revisión individual</span><?php endif; ?>
                </summary>
                <p class="text-muted text-sm">Categoría: <?php echo s(AnalisisMateriales::CATEGORIAS[$g['categoria']] ?? $g['categoria']); ?>. <?php if ($riesgo): ?>Las que mueven el MSR o la instalación se revisan una por una. <?php endif; ?><a href="<?php echo s($url(['categoria' => $g['categoria'], 'vista' => 'lista'])); ?>">Ver en la lista para corregir a mano</a>.</p>
                <table class="inc-gtabla">
                    <thead><tr><th>Material</th><th>Hallazgo</th><th>Propuesta</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($g['items'] as $x): ?>
                        <tr class="est-<?php echo s($x['estado']); ?>">
                            <td><?php foreach ($x['materiales'] as $m): ?><div><a href="/materiales/editar?id=<?php echo (int)$m['id']; ?>"><?php echo s($m['nombre']); ?></a></div><?php endforeach; ?><?php if (!$x['materiales']): ?><span class="text-muted">—</span><?php endif; ?></td>
                            <td><?php echo s($x['titulo']); ?></td>
                            <td><?php echo s($x['solucion']['texto']); ?></td>
                            <td><span class="inc-estado"><?php echo ['pendiente' => 'Pendiente', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada'][$x['estado']] ?? $x['estado']; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <span class="inc-msg inc-grupo-msg" aria-live="polite"></span>
                <div class="inc-botones">
                    <a class="btn btn-sm btn-ghost" href="<?php echo s($url(['categoria' => $g['categoria'], 'vista' => 'lista'])); ?>"><i class="fa-solid fa-pen-to-square"></i> Corregir uno por uno</a>
                    <a class="btn btn-sm btn-ghost" href="/analisis-materiales/exportar?<?php echo http_build_query(array_filter(['proyecto' => $pid, 'corrida' => $filtros['corrida'], 'categoria' => $g['categoria'], 'severidad' => $g['severidad'], 'estado' => $filtros['estado'], 'formato' => 'pdf'], fn($v) => $v !== '' && $v !== 0)); ?>"><i class="fa-solid fa-file-pdf"></i> Reporte de este problema</a>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php $aplicables = array_filter($lista, fn($x) => $x['estado'] === 'pendiente' && $x['solucion']['accion'] !== 'nota'); if (count($aplicables) > 1): ?>
        <div class="card inc-masivo">
            <span><strong><?php echo count($aplicables); ?></strong> propuestas con cambio de datos en esta vista.</span>
            <button type="button" class="btn btn-sm btn-primary" id="inc-todas"><i class="fa-solid fa-check-double"></i> Aceptar todas (<?php echo count($aplicables); ?>)</button>
            <span class="inc-msg" id="inc-todas-msg" aria-live="polite"></span>
        </div>
    <?php endif; ?>
    <ul class="inc-lista">
        <?php foreach ($lista as $x): $sol = $x['solucion']; ?>
            <li class="card inc-item sev-<?php echo s($x['severidad']); ?> est-<?php echo s($x['estado']); ?>" data-id="<?php echo $x['id']; ?>" data-accion-tipo="<?php echo s($sol['accion']); ?>">
                <div class="inc-cab">
                    <span class="prg-sev"><?php echo s($sevNombre[$x['severidad']]); ?></span>
                    <strong><?php echo s($x['titulo']); ?></strong>
                    <small class="text-muted"><?php echo s(AnalisisMateriales::CATEGORIAS[$x['categoria']] ?? $x['categoria']); ?> · <?php echo $x['origen'] === 'ia' ? 'detectada por la IA' . ($x['modelo'] ? ' (' . s($x['modelo']) . ')' : '') : 'detectada por el sistema'; ?></small>
                </div>
                <p><?php echo s($x['descripcion']); ?></p>
                <?php if (!empty($x['evidencia']['cita'])): ?><p class="inc-evidencia"><i class="fa-solid fa-quote-left"></i> <?php echo s($x['evidencia']['cita']); ?></p><?php endif; ?>
                <?php if (!empty($x['vencido'])): ?><p class="inc-vencido"><i class="fa-solid fa-hourglass-end"></i> Vencido el <?php echo s(date('d/m/Y', strtotime((string)$x['vigente_hasta']))); ?>: vuelve a analizar o corrígelo a mano.</p><?php endif; ?>
                <?php if ($x['materiales'] || $x['submittals']): ?>
                    <details class="inc-acts text-sm"<?php echo count($x['materiales']) <= 4 ? ' open' : ''; ?>>
                        <summary><?php echo count($x['materiales']) ? count($x['materiales']) . ' material(es)' : ''; ?><?php echo $x['submittals'] ? ' · submittal ' . s(implode(', ', array_column($x['submittals'], 'numero'))) : ''; ?></summary>
                        <ul><?php foreach ($x['materiales'] as $m): ?><li><a href="/materiales/editar?id=<?php echo (int)$m['id']; ?>"><?php echo s($m['nombre']); ?></a></li><?php endforeach; ?></ul>
                    </details>
                <?php endif; ?>
                <div class="inc-solucion">
                    <span class="inc-tag"><?php echo s($accionNombre[$sol['accion']] ?? $sol['accion']); ?></span>
                    <span><strong>Solución propuesta:</strong> <?php echo s($sol['texto']); ?></span>
                    <?php if ($sol['accion'] === 'renombrar_material'): $q = $sol['params']; ?>
                        <span class="inc-estructura"><b>Base</b> <?php echo s($q['nombre_base']); ?> · <b>Marca</b> <?php echo s($q['marca'] ?: '—'); ?> · <b>Modelo</b> <?php echo s($q['modelo'] ?: '—'); ?> · <b>Extra</b> <?php echo s($q['valor_extra'] ?: '—'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="inc-botones">
                    <?php if ($x['estado'] === 'pendiente'): ?>
                        <button type="button" class="btn btn-sm btn-ghost inc-abrir-editor" data-id="<?php echo (int)$x['id']; ?>" data-url="/analisis-materiales/editor"><i class="fa-solid fa-pen-to-square"></i> Corregir aquí</button>
                        <button type="button" class="btn btn-sm btn-primary" data-accion="aceptar"><i class="fa-solid fa-check"></i> <?php echo $sol['accion'] === 'nota' ? 'Marcar como atendida' : 'Aceptar y aplicar'; ?></button>
                        <button type="button" class="btn btn-sm btn-ghost" data-accion="rechazar"><i class="fa-solid fa-xmark"></i> Rechazar</button>
                    <?php else: ?>
                        <span class="inc-estado"><?php echo $x['estado'] === 'aceptada' ? '<i class="fa-solid fa-check"></i> Aceptada' : '<i class="fa-solid fa-xmark"></i> Rechazada'; ?> · <?php echo s($x['resuelto_por']); ?> · <?php echo s(date('d/m/Y H:i', strtotime($x['resuelto_en']))); ?></span>
                        <button type="button" class="btn btn-sm btn-ghost" data-accion="reabrir"><?php echo $x['revertible'] ? 'Revertir el cambio' : 'Volver a pendiente'; ?></button>
                    <?php endif; ?>
                    <span class="inc-msg" aria-live="polite"></span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php endif; ?>
<dialog id="inc-editor-dialog" class="inc-editor-dialog">
    <div class="inc-editor-cab"><strong><i class="fa-solid fa-pen-to-square"></i> Corregir el material</strong><button type="button" class="btn btn-sm btn-ghost" data-cerrar-editor aria-label="Cerrar el editor">✕</button></div>
    <div id="inc-editor-host" class="inc-editor-host"><p class="text-muted">Cargando…</p></div>
</dialog>
<dialog id="inc-rechazo-dialog" class="inc-rechazo-dialog">
    <h3><i class="fa-solid fa-xmark"></i> Rechazar el hallazgo</h3>
    <p class="text-muted text-sm">El motivo nos dice qué reglas o modelos están fallando. No se volverá a mostrar.</p>
    <label class="inc-campo">Motivo
        <select id="inc-rechazo-motivo">
            <?php foreach (\Classes\HallazgosIA::MOTIVOS as $k => $n): ?><option value="<?php echo s($k); ?>"><?php echo s($n); ?></option><?php endforeach; ?>
        </select>
    </label>
    <label class="inc-campo inc-ancho">Nota (opcional) <textarea id="inc-rechazo-nota" rows="2" maxlength="500"></textarea></label>
    <div class="inc-botones"><button type="button" class="btn btn-sm btn-ghost" data-cancelar-rechazo>Cancelar</button><button type="button" class="btn btn-sm btn-primary" data-confirmar-rechazo>Rechazar</button><span class="inc-msg" id="inc-rechazo-msg" aria-live="polite"></span></div>
</dialog>
<script>window.HALLAZGOS = {grupo: '/analisis-materiales/grupo', proyecto: <?php echo $pid; ?>, corrida: <?php echo (int)$filtros['corrida']; ?>, editor: '/analisis-materiales/editor', impacto: '', herramienta: 'analisis-materiales'};</script>
<script src="/vendor/hallazgos-editor.js?v=<?php echo filemtime(__DIR__ . '/../../public/vendor/hallazgos-editor.js'); ?>"></script>
