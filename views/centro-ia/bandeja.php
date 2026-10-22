<?php // Variables: $proyecto, $filtros, $items, $conteo, $partidas, $bajoRiesgo, $niveles, $mensaje
use Classes\CentroIA;
use Classes\RiesgoIA;
$pid = (int)$proyecto->id;
$sevNombre = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$accionNombre = array_map(fn($a) => $a[0], RiesgoIA::ACCIONES);
$url = fn(array $c) => '/propuestas-ia?' . http_build_query(array_filter(['proyecto' => $pid] + $c + $filtros, fn($v) => $v !== ''));
$total = array_sum($conteo);
?>
<?php $pestana = 'propuestas'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header">
    <div>
        <h2><i class="fa-solid fa-inbox"></i> <?php echo s(t('Propuestas de IA')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · todo lo que las herramientas de IA proponen y sigue pendiente, en un solo lugar. <strong>Nada se aplica sin que lo aceptes.</strong></p>
    </div>
</div>

<div class="prg-resumen inc-resumen">
    <a class="card" href="<?php echo s($url(['herramienta' => ''])); ?>"><small>Pendientes en total</small><strong><?php echo (int)$total; ?></strong></a>
    <?php foreach (CentroIA::HERRAMIENTAS as $k => $n): ?>
        <a class="card" href="<?php echo s($url(['herramienta' => $k])); ?>"><small><?php echo s($n); ?></small><strong><?php echo (int)$conteo[$k]; ?></strong></a>
    <?php endforeach; ?>
</div>

<form method="get" action="/propuestas-ia" class="card inc-filtros">
    <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
    <label>Herramienta <select name="herramienta" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (CentroIA::HERRAMIENTAS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['herramienta'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
    <label>Severidad <select name="severidad" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($sevNombre as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['severidad'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
    <label>Detectada por <select name="origen" onchange="this.form.submit()"><option value="">Todas</option><option value="sistema"<?php echo $filtros['origen'] === 'sistema' ? ' selected' : ''; ?>>Sistema</option><option value="ia"<?php echo $filtros['origen'] === 'ia' ? ' selected' : ''; ?>>IA</option></select></label>
    <label>Riesgo <select name="riesgo" onchange="this.form.submit()"><option value="">Todos</option><option value="simple"<?php echo $filtros['riesgo'] === 'simple' ? ' selected' : ''; ?>>Simple (se acepta por lote)</option><option value="permiso"<?php echo $filtros['riesgo'] === 'permiso' ? ' selected' : ''; ?>>Pide permiso</option></select></label>
    <?php if ($partidas): ?><label>Partida <select name="partida" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($partidas as $cod => $nom): ?><option value="<?php echo s($cod); ?>"<?php echo $filtros['partida'] === (string)$cod ? ' selected' : ''; ?>><?php echo s($cod . ' ' . $nom); ?></option><?php endforeach; ?></select></label><?php endif; ?>
    <input type="search" data-filtra=".inc-lista" placeholder="Buscar…" aria-label="Buscar propuestas" autocomplete="off">
</form>

<?php if ($mensaje): ?><div class="card ia-aviso"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>
<?php if ($bajoRiesgo): ?>
    <div class="card ia-lote">
        <div>
            <strong><i class="fa-solid fa-layer-group"></i> Aprobación por lotes</strong>
            <p class="text-muted text-sm">Acepta de una vez las propuestas <strong>simples</strong> (renombrar, vincular normativa, recalcular…)<?php echo $filtros['partida'] !== '' ? ' de la partida ' . s($filtros['partida']) : ' que coinciden con los filtros'; ?>. Las que piden permiso (mover fechas, relaciones, avance…) siempre se aceptan una por una.</p>
        </div>
        <button type="button" class="btn btn-primary" id="ia-lote-btn"><i class="fa-solid fa-check-double"></i> <?php echo $filtros['partida'] !== '' ? 'Aceptar las simples de esta partida' : 'Aceptar todas las de bajo riesgo'; ?></button>
        <span class="inc-msg" id="ia-lote-msg" aria-live="polite"></span>
    </div>
<?php endif; ?>

<?php if (!$items): ?>
    <div class="card prg-vacio">
        <h3><?php echo $total ? 'Nada con estos filtros' : 'No hay propuestas pendientes'; ?></h3>
        <p class="text-muted">Las propuestas aparecen aquí cuando ejecutas un análisis en la app de escritorio (Herramientas IA) o pulsas «Revisar ahora» en Inconsistencias o en el Análisis de materiales.</p>
    </div>
<?php else: ?>
    <ul class="inc-lista">
        <?php foreach ($items as $x): ?>
            <li class="card inc-item sev-<?php echo s($x['severidad']); ?> est-pendiente" data-riesgo="<?php echo s($x['riesgo']); ?>" data-herramienta="<?php echo s($x['herramienta']); ?>" data-id="<?php echo (int)$x['id']; ?>">
                <div class="inc-cab">
                    <span class="prg-sev"><?php echo s($sevNombre[$x['severidad']]); ?></span>
                    <strong><?php echo s($x['titulo']); ?></strong>
                    <small class="text-muted"><?php echo s(CentroIA::HERRAMIENTAS[$x['herramienta']]); ?> · <?php echo s($x['categoria']); ?> · <?php echo $x['origen'] === 'ia' ? 'detectada por la IA' : 'detectada por el sistema'; ?></small>
                </div>
                <p><?php echo s($x['descripcion']); ?></p>
                <div class="inc-solucion">
                    <span class="inc-tag"><?php echo s($accionNombre[$x['accion']] ?? $x['accion']); ?></span>
                    <span class="ia-riesgo ia-riesgo-<?php echo s($x['riesgo']); ?>" title="<?php echo $x['riesgo'] === 'permiso' ? 'Siempre pide tu confirmación y no entra en la aprobación por lotes' : 'Riesgo bajo: se acepta sin confirmación y puede entrar por lote'; ?>"><?php echo s(RiesgoIA::NIVELES[$x['riesgo']]); ?></span>
                    <span><strong>Solución propuesta:</strong> <?php echo s($x['solucion']); ?></span>
                </div>
                <div class="inc-botones">
                    <?php if ($x['resoluble']): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-accion="aceptar"><i class="fa-solid fa-check"></i> <?php echo $x['accion'] === 'nota' ? 'Marcar como atendida' : 'Aceptar y aplicar'; ?></button>
                        <button type="button" class="btn btn-sm btn-ghost" data-accion="rechazar"><i class="fa-solid fa-xmark"></i> Rechazar</button>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-ghost" href="<?php echo s($x['url']); ?>"><?php echo $x['resoluble'] ? 'Ver en su herramienta' : 'Abrir y revisar'; ?> →</a>
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
            if (accion === 'aceptar' && li.dataset.riesgo === 'permiso' && !await window.confirmarModal({ titulo: '¿Aceptar la solución?', mensaje: 'Este cambio pide tu permiso: se modificarán datos del proyecto con esta solución.', aceptar: 'Aceptar', tono: 'info' })) return;
            var d = new FormData();
            d.append('proyecto', '<?php echo $pid; ?>'); d.append('herramienta', li.dataset.herramienta); d.append('id', li.dataset.id);
            li.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
            fetch('/propuestas-ia/' + accion, { method: 'POST', body: d, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    msg.textContent = j.mensaje || '';
                    if (j.success) { li.classList.add('hecha'); setTimeout(function () { li.remove(); }, 1400); }
                    else li.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
                })
                .catch(function () { msg.textContent = 'Error de conexión'; li.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); });
        });
    </script>
<?php endif; ?>
<script>
    (function () {
        var b = document.getElementById('ia-lote-btn');
        if (!b) return;
        b.addEventListener('click', async function () {
            var f = new URLSearchParams(location.search), msg = document.getElementById('ia-lote-msg');
            if (!await window.confirmarModal({ titulo: '¿Aceptar las propuestas de bajo riesgo?', mensaje: 'Se aplicarán todas las propuestas simples que ves con estos filtros. Cada una queda en el historial y se puede revertir.', aceptar: 'Aceptar todas', tono: 'info' })) return;
            var d = new FormData();
            d.append('proyecto', '<?php echo $pid; ?>');
            ['herramienta', 'severidad', 'origen', 'partida'].forEach(function (k) { d.append(k, f.get(k) || ''); });
            b.disabled = true; msg.textContent = 'Aplicando…';
            fetch('/propuestas-ia/aceptar-lote', { method: 'POST', body: d, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) { msg.textContent = j.mensaje || j.error || ''; if (j.success) setTimeout(function () { location.reload(); }, 1800); else b.disabled = false; })
                .catch(function () { msg.textContent = 'Error de conexión'; b.disabled = false; });
        });
    })();
</script>

<details class="card ia-riesgos" id="riesgo"<?php echo $mensaje ? ' open' : ''; ?>>
    <summary><strong><i class="fa-solid fa-shield-halved"></i> Niveles de riesgo</strong> <span class="text-muted text-sm">— qué cambios siempre piden tu permiso y cuáles son simples</span></summary>
    <form method="post" action="/propuestas-ia/riesgo">
        <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
        <p class="text-muted text-sm">«Pide permiso»: se acepta una por una, con confirmación, y nunca entra en la aprobación por lotes. «Simple»: se acepta con un clic y puede entrar por lote. Nada se aplica solo en ningún caso.</p>
        <div class="ia-riesgos__lista">
            <?php foreach (RiesgoIA::ACCIONES as $a => [$nombre, $def]): ?>
                <label class="ia-riesgos__fila">
                    <span><?php echo s($nombre); ?><?php echo in_array($a, RiesgoIA::SIN_LOTE, true) ? ' <small class="text-muted">(siempre se revisa en su pantalla)</small>' : ''; ?></span>
                    <select name="nivel[<?php echo s($a); ?>]">
                        <?php foreach (RiesgoIA::NIVELES as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo ($niveles[$a] ?? $def) === $k ? ' selected' : ''; ?>><?php echo s($n); ?><?php echo $k === $def ? ' (por defecto)' : ''; ?></option><?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary" type="submit">Guardar niveles</button>
    </form>
</details>
