<?php // Variables: $proyecto, $filtros, $corridas, $corrida (análisis mostrado o null), $lista, $lotes (registro por lote de la IA), $conteos, $mensaje, $vigilancia
use Classes\Inconsistencias;
$pid = (int)$proyecto->id;
$sevNombre = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$accionNombre = ['nota' => 'Sin cambio de datos', 'ajustar_fechas' => 'Cambia fechas', 'sincronizar_status' => 'Cambia estatus', 'ajustar_progreso' => 'Cambia avance', 'recalcular_partida' => 'Recalcula partida',
    'marcar_comprado' => 'Marca material comprado', 'resolver_restriccion' => 'Resuelve restricción', 'quitar_dependencia' => 'Quita relación', 'ajustar_duracion' => 'Cambia duración', 'renombrar_actividad' => 'Renombra la actividad', 'editar_descripcion' => 'Edita la descripción', 'agregar_dia_no_habil' => 'Marca día no hábil', 'agregar_dependencia' => 'Agrega una relación'];
$url = fn(array $cambia) => '/inconsistencias?' . http_build_query(array_filter(['proyecto' => $pid] + $cambia + $filtros, fn($v) => $v !== '' && $v !== 0));
$nombreCorrida = fn($c) => ($c['nombre'] !== '' ? $c['nombre'] : 'Análisis') . ' · ' . date('d/m/Y H:i', strtotime($c['creado'])) . ($c['modelo'] ? ' · ' . $c['modelo'] : '');
?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-magnifying-glass-chart"></i> <?php echo s(t('Inconsistencias de proyecto')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · incoherencias en la información del proyecto, cada una con una solución que <strong>tú aceptas o rechazas</strong>. Nada se cambia sin tu aprobación.</p>
    </div>
    <div class="inc-acciones">
        <form method="post" action="/inconsistencias/revisar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-ghost" type="submit" title="Aplica las reglas del sistema, sin IA"><i class="fa-solid fa-rotate"></i> Revisar ahora (sin IA)</button></form>
        <a href="/programa?proyecto=<?php echo $pid; ?>" class="btn btn-ghost">← <?php echo s(t('Programa')); ?></a>
    </div>
</div>

<?php if (!empty($mensaje)): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<details class="card inc-vigilancia"<?php echo $vigilancia && ($vigilancia['total_nuevos'] || $vigilancia['cambios']) ? ' open' : ''; ?>>
    <summary><i class="fa-solid fa-eye"></i> Vigilancia continua <?php if ($vigilancia): ?>· última comprobación <?php echo s(date('d/m/Y H:i', strtotime($vigilancia['revisado']))); ?> · <strong><?php echo (int)$vigilancia['total_nuevos']; ?></strong> nuevo(s), <strong><?php echo count($vigilancia['cambios']); ?></strong> cambio(s) brusco(s)<?php else: ?>· aún sin comprobaciones<?php endif; ?></summary>
    <p class="text-muted text-sm">La app de escritorio puede comprobar el proyecto cada cierto tiempo con las reglas del sistema (sin IA) y avisarte con una notificación si aparece algo nuevo o si el avance, el fin del programa o las restricciones cambian de golpe. Actívala en <em>Ajustes → Vigilancia</em>. Aquí puedes comprobar ahora.</p>
    <?php if ($vigilancia && $vigilancia['cambios']): ?><ul class="hist-lista"><?php foreach ($vigilancia['cambios'] as $c): ?><li><span class="prg-sev"><?php echo s($sevNombre[$c['severidad']] ?? $c['severidad']); ?></span> <strong><?php echo s($c['titulo']); ?></strong> <small class="text-muted"><?php echo s($c['descripcion']); ?></small></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if ($vigilancia && $vigilancia['hallazgos']): ?><ul class="hist-lista"><?php foreach ($vigilancia['hallazgos'] as $h): ?><li><span class="prg-sev"><?php echo s($sevNombre[$h['severidad']] ?? $h['severidad']); ?></span> <?php echo s($h['titulo']); ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" action="/inconsistencias/vigilar"><input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><button class="btn btn-sm btn-ghost" type="submit"><i class="fa-solid fa-rotate"></i> Comprobar ahora</button></form>
</details>

<?php if ($corrida): $cid = (int)$corrida['id']; ?>
<div class="card prg-barra inc-barra">
    <label>Análisis
        <select onchange="location.href='/inconsistencias?proyecto=<?php echo $pid; ?>&corrida='+this.value">
            <?php foreach ($corridas as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>"<?php echo (int)$c['id'] === $cid ? ' selected' : ''; ?>><?php echo s(($c['guardada'] ? '★ ' : '') . $nombreCorrida($c) . ' — ' . (int)$c['pendientes'] . ' pendientes'); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <form method="post" action="/inconsistencias/guardar" class="prg-guardar">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="corrida" value="<?php echo $cid; ?>">
        <?php if ($corrida['guardada']): ?>
            <span class="prg-guardada"><i class="fa-solid fa-bookmark"></i> Guardado<?php echo $corrida['nombre'] !== '' ? ': ' . s($corrida['nombre']) : ''; ?></span>
            <button class="btn btn-sm btn-ghost" type="submit" name="quitar" value="1">Quitar de guardados</button>
        <?php else: ?>
            <input type="text" name="nombre" maxlength="150" aria-label="Nombre del análisis" placeholder="Nombre (ej. Revisión semana 42)" value="<?php echo s('Análisis ' . date('d/m/Y', strtotime($corrida['creado']))); ?>">
            <button class="btn btn-sm btn-primary" type="submit"><i class="fa-regular fa-bookmark"></i> Guardar análisis</button>
        <?php endif; ?>
    </form>
    <form method="post" action="/inconsistencias/eliminar" data-confirmar="¿Eliminar este análisis y su historial? Los cambios ya aplicados al proyecto se conservan, pero ya no podrás revertirlos desde aquí.">
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
    <p class="text-muted text-sm">Lo que la IA revisó en este análisis, lote por lote: actividades cubiertas y hallazgos que propuso (incluye los que ya habías aceptado o rechazado antes y por eso no se repiten abajo).</p>
    <?php foreach ($lotes as $l): ?>
        <details class="inc-acts text-sm">
            <summary><strong><?php echo s($l['titulo']); ?></strong> · <?php echo $l['estado'] === 'error' ? '<span class="text-danger">falló: ' . s($l['error']) . '</span> (estas actividades no se revisaron; vuelve a analizar)' : count($l['hallazgos']) . ' hallazgo(s) de la IA'; ?></summary>
            <?php if ($l['hallazgos']): ?>
                <ul><?php foreach ($l['hallazgos'] as $h): ?><li><span class="prg-sev"><?php echo s($sevNombre[$h['severidad']] ?? $h['severidad']); ?></span> <?php echo s($h['titulo']); ?> <small class="text-muted">· <?php echo s(Inconsistencias::CATEGORIAS[$h['categoria']] ?? $h['categoria']); ?> · <?php echo s($accionNombre[$h['accion']] ?? $h['accion']); ?></small></li><?php endforeach; ?></ul>
            <?php elseif ($l['estado'] === 'ok'): ?><p>Sin hallazgos: la IA no encontró nada que corregir en estas actividades.</p><?php endif; ?>
            <?php if ($l['actividades']): ?>
                <details><summary><?php echo count($l['actividades']); ?> actividad(es) analizadas</summary>
                    <ul><?php foreach ($l['actividades'] as $a): ?><li><?php echo s(trim($a['codigo'] . ' ' . $a['nombre'])); ?></li><?php endforeach; ?></ul>
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

<form method="get" action="/inconsistencias" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>"><input type="hidden" name="corrida" value="<?php echo (int)$filtros['corrida']; ?>">
    <label>Estado <select name="estado" onchange="this.form.submit()"><?php foreach (['pendiente' => 'Pendientes', 'aceptada' => 'Aceptadas', 'rechazada' => 'Rechazadas', '' => 'Todas'] as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['estado'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Severidad <select name="severidad" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($sevNombre as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['severidad'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Categoría <select name="categoria" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (Inconsistencias::CATEGORIAS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['categoria'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Detectada por <select name="origen" onchange="this.form.submit()"><option value="">Todas</option><option value="sistema"<?php echo $filtros['origen'] === 'sistema' ? ' selected' : ''; ?>>Sistema</option><option value="ia"<?php echo $filtros['origen'] === 'ia' ? ' selected' : ''; ?>>IA</option></select></label>
    <input type="search" data-filtra=".inc-lista" placeholder="Buscar…" aria-label="Buscar inconsistencias" autocomplete="off">
</form>

<?php if (!$lista): ?>
    <div class="card prg-vacio">
        <h3><?php echo !$corrida ? 'Aún no hay análisis' : ($conteos['pendiente'] + $conteos['aceptada'] + $conteos['rechazada'] ? 'Nada con estos filtros' : 'Este análisis no encontró inconsistencias nuevas'); ?></h3>
        <p class="text-muted">Pulsa <em>Revisar ahora (sin IA)</em> para aplicar las reglas del sistema (fechas, avance, calendario, dependencias, materiales, restricciones, submittals y reportes). Para sumar la revisión de la IA —contradicciones con la bitácora y los reportes, duplicados con otro nombre, etc.— abre la app de escritorio en <strong>Herramientas IA → Inconsistencias de proyecto</strong>.</p>
    </div>
<?php else: ?>
    <ul class="inc-lista">
        <?php foreach ($lista as $x): $sol = $x['solucion']; ?>
            <li class="card inc-item sev-<?php echo s($x['severidad']); ?> est-<?php echo s($x['estado']); ?>" data-id="<?php echo $x['id']; ?>">
                <div class="inc-cab">
                    <span class="prg-sev"><?php echo s($sevNombre[$x['severidad']]); ?></span>
                    <strong><?php echo s($x['titulo']); ?></strong>
                    <small class="text-muted"><?php echo s(Inconsistencias::CATEGORIAS[$x['categoria']] ?? $x['categoria']); ?> · <?php echo $x['origen'] === 'ia' ? 'detectada por la IA' . ($x['modelo'] ? ' (' . s($x['modelo']) . ')' : '') : 'detectada por el sistema'; ?></small>
                </div>
                <p><?php echo s($x['descripcion']); ?></p>
                <?php if ($x['actividades']): ?>
                    <p class="inc-acts text-sm"><?php foreach ($x['actividades'] as $a): ?><a href="/programa/actividades/editar?id=<?php echo (int)$a['id']; ?>" title="<?php echo s($a['nombre']); ?>"><?php echo s($a['codigo']); ?></a> <?php endforeach; ?></p>
                <?php endif; ?>
                <div class="inc-solucion">
                    <span class="inc-tag"><?php echo s($accionNombre[$sol['accion']] ?? $sol['accion']); ?></span>
                    <span><strong>Solución propuesta:</strong> <?php echo s($sol['texto']); ?></span>
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
            fetch('/inconsistencias/' + accion, { method: 'POST', body: d, credentials: 'same-origin' })
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
    </script>
<?php endif; ?>
