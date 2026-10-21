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
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · materiales, submittals pendientes, información incompleta y coherencia con el MSR (Reportes de materiales), con propuestas de nombre completo (nombre + marca + modelo + valor extra). Cada propuesta la <strong>aceptas o rechazas</strong>; nada se cambia sin tu aprobación.</p>
    </div>
    <div class="inc-acciones">
        <form method="post" action="/analisis-materiales/revisar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-ghost" type="submit" title="Aplica las reglas del sistema, sin IA"><i class="fa-solid fa-rotate"></i> Revisar ahora (sin IA)</button></form>
        <a href="/msr?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">MSR →</a>
    </div>
</div>

<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<?php if ($corrida): $cid = (int)$corrida['id']; ?>
<div class="card prg-barra inc-barra">
    <label>Análisis
        <select onchange="location.href='/analisis-materiales?proyecto=<?php echo $pid; ?>&corrida='+this.value">
            <?php foreach ($corridas as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>"<?php echo (int)$c['id'] === $cid ? ' selected' : ''; ?>><?php echo s(($c['guardada'] ? '★ ' : '') . $nombreCorrida($c) . ' — ' . (int)$c['pendientes'] . ' pendientes'); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
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
    <a class="card" href="<?php echo s($url(['estado' => 'pendiente'])); ?>"><small>Pendientes</small><strong id="inc-n-pendiente"><?php echo (int)$conteos['pendiente']; ?></strong><span><span id="inc-n-criticas"><?php echo (int)$conteos['criticas']; ?></span> de severidad alta o crítica</span></a>
    <a class="card" href="<?php echo s($url(['estado' => 'aceptada'])); ?>"><small>Aceptadas</small><strong id="inc-n-aceptada"><?php echo (int)$conteos['aceptada']; ?></strong></a>
    <a class="card" href="<?php echo s($url(['estado' => 'rechazada'])); ?>"><small>Rechazadas</small><strong id="inc-n-rechazada"><?php echo (int)$conteos['rechazada']; ?></strong></a>
</div>

<form method="get" action="/analisis-materiales" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="corrida" value="<?php echo (int)$filtros['corrida']; ?>">
    <label>Estado <select name="estado" onchange="this.form.submit()"><?php foreach (['pendiente' => 'Pendientes', 'aceptada' => 'Aceptadas', 'rechazada' => 'Rechazadas', '' => 'Todas'] as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['estado'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Severidad <select name="severidad" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($sevNombre as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['severidad'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Categoría <select name="categoria" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (AnalisisMateriales::CATEGORIAS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['categoria'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Detectada por <select name="origen" onchange="this.form.submit()"><option value="">Todas</option><option value="sistema"<?php echo $filtros['origen'] === 'sistema' ? ' selected' : ''; ?>>Sistema</option><option value="ia"<?php echo $filtros['origen'] === 'ia' ? ' selected' : ''; ?>>IA</option></select></label>
    <input type="search" data-filtra=".inc-lista" placeholder="Buscar…" aria-label="Buscar hallazgos" autocomplete="off">
</form>

<?php if (!$lista): ?>
    <div class="card prg-vacio">
        <h3><?php echo !$corrida ? 'Aún no hay análisis' : ($conteos['pendiente'] + $conteos['aceptada'] + $conteos['rechazada'] ? 'Nada con estos filtros' : 'Este análisis no encontró hallazgos nuevos'); ?></h3>
        <p class="text-muted">Pulsa <em>Revisar ahora (sin IA)</em> para aplicar las reglas del sistema (información incompleta, coherencia material ↔ MSR, submittals pendientes o rechazados, fechas y duplicados). Para que además la IA proponga el <strong>nombre completo</strong> de cada material (nombre base + marca + modelo + valor extra) y detecte inconsistencias entre materiales, abre la app de escritorio en <strong>Herramientas IA → Análisis de materiales</strong>.</p>
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
            <li class="card inc-item sev-<?php echo s($x['severidad']); ?> est-<?php echo s($x['estado']); ?>" data-id="<?php echo $x['id']; ?>">
                <div class="inc-cab">
                    <span class="prg-sev"><?php echo s($sevNombre[$x['severidad']]); ?></span>
                    <strong><?php echo s($x['titulo']); ?></strong>
                    <small class="text-muted"><?php echo s(AnalisisMateriales::CATEGORIAS[$x['categoria']] ?? $x['categoria']); ?> · <?php echo $x['origen'] === 'ia' ? 'detectada por la IA' . ($x['modelo'] ? ' (' . s($x['modelo']) . ')' : '') : 'detectada por el sistema'; ?></small>
                </div>
                <p><?php echo s($x['descripcion']); ?></p>
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
    <script>
        document.querySelector('.inc-lista').addEventListener('click', async function (e) {
            var b = e.target.closest('button[data-accion]');
            if (!b) return;
            var li = b.closest('.inc-item'), msg = li.querySelector('.inc-msg'), accion = b.dataset.accion;
            if (accion === 'aceptar' && !li.querySelector('.inc-tag').textContent.startsWith('Sin cambio') && !await window.confirmarModal({ titulo: '¿Aceptar la solución?', mensaje: 'Se modificarán datos del proyecto con esta solución.', aceptar: 'Aceptar', tono: 'info' })) return;
            var d = new FormData();
            d.append('proyecto', '<?php echo $pid; ?>'); d.append('id', li.dataset.id); d.append('corrida', '<?php echo (int)$filtros['corrida']; ?>');
            li.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
            fetch('/analisis-materiales/' + accion, { method: 'POST', body: d, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    msg.textContent = j.mensaje || '';
                    if (j.success) {
                        li.classList.add('hecha');
                        ['pendiente', 'aceptada', 'rechazada'].forEach(function (k) { var n = document.getElementById('inc-n-' + k); if (n && j.conteos) n.textContent = j.conteos[k]; });
                        var c = document.getElementById('inc-n-criticas'); if (c && j.conteos) c.textContent = j.conteos.criticas;
                        setTimeout(function () { li.remove(); }, 1400);
                    } else li.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
                })
                .catch(function () { msg.textContent = 'Error de conexión'; li.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); });
        });
        var todas = document.getElementById('inc-todas');
        if (todas) todas.addEventListener('click', async function () {
            var items = Array.prototype.filter.call(document.querySelectorAll('.inc-item.est-pendiente'), function (li) { return !li.querySelector('.inc-tag').textContent.startsWith('Sin cambio'); });
            if (!await window.confirmarModal({ titulo: '¿Aplicar ' + items.length + ' cambios?', mensaje: 'Se aplicarán a los datos del proyecto, uno por uno (cada uno se puede revertir).', aceptar: 'Aplicar', tono: 'info' })) return;
            todas.disabled = true; var msg = document.getElementById('inc-todas-msg'), ok = 0, mal = 0;
            for (var i = 0; i < items.length; i++) {
                var d = new FormData(); d.append('proyecto', '<?php echo $pid; ?>'); d.append('id', items[i].dataset.id); d.append('corrida', '<?php echo (int)$filtros['corrida']; ?>');
                try { var j = await (await fetch('/analisis-materiales/aceptar', { method: 'POST', body: d, credentials: 'same-origin' })).json(); if (j.success) { ok++; items[i].classList.add('hecha'); } else { mal++; items[i].querySelector('.inc-msg').textContent = j.mensaje; } } catch (e) { mal++; }
                msg.textContent = (ok + mal) + ' de ' + items.length + '…';
            }
            msg.textContent = ok + ' aceptadas' + (mal ? ', ' + mal + ' no se pudieron aplicar (ver el aviso en cada una)' : '') + '.';
            if (!mal) setTimeout(function () { location.reload(); }, 1200);
        });
    </script>
<?php endif; ?>
