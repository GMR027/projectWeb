<?php // Variables: $proyecto, $filtros, $items, $conteo, $partidas, $bajoRiesgo, $niveles, $mensaje
use Classes\CentroIA;
use Classes\RiesgoIA;
$pid = (int)$proyecto->id;
$sevNombre = ['critica' => 'Crítica', 'alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'];
$sevClase = ['critica' => 'ia-sev--critica', 'alta' => 'ia-sev--alta', 'media' => 'ia-sev--media', 'baja' => 'ia-sev--baja'];
$accionNombre = array_map(fn($a) => $a[0], RiesgoIA::ACCIONES);
$url = fn(array $c) => '/propuestas-ia?' . http_build_query(array_filter(['proyecto' => $pid] + $c + $filtros, fn($v) => $v !== ''));
$total = array_sum($conteo);
$bajo = count(array_filter($items, fn($x) => $x['riesgo'] === 'simple'));
$permiso = count(array_filter($items, fn($x) => $x['riesgo'] === 'permiso'));
$criticas = count(array_filter($items, fn($x) => $x['severidad'] === 'critica'));
$herramientasActivas = count(array_filter($conteo));
?>
<?php $pestana = 'propuestas'; include __DIR__ . '/../centro-ia/_pestanas.php'; ?>
<div class="page-header ia-header">
    <div>
        <span class="ia-eyebrow"><i class="fa-solid fa-inbox"></i> Centro de IA</span>
        <h2><i class="fa-solid fa-inbox"></i> <?php echo s(t('Propuestas de IA')); ?></h2>
        <p class="text-muted"><?php echo s($proyecto->nombre); ?> · todo lo que las herramientas de IA proponen y sigue pendiente, en un solo lugar. <strong>Nada se aplica sin que lo aceptes.</strong></p>
        <span class="ia-status"><span class="ia-status-dot"></span><?php echo (int)$total; ?> pendientes · <?php echo (int)$bajo; ?> de bajo riesgo · <?php echo (int)$permiso; ?> piden permiso</span>
    </div>
    <div class="ia-header-acciones">
        <a class="btn btn-sm btn-ghost" href="/resumen-ia?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-gauge-high"></i> Resumen de IA</a>
        <a class="btn btn-sm btn-ghost" href="/historial-cambios?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Auditoría</a>
        <a class="btn btn-ghost" href="/programa?proyecto=<?php echo $pid; ?>">← <?php echo s(t('Programa')); ?></a>
    </div>
</div>

<div class="ia-trust" role="note">
    <span class="ia-trust-icono"><i class="fa-solid fa-shield-halved"></i></span>
    <p><strong>La IA propone; tú apruebas.</strong> Nada se aplica solo.</p>
    <a href="/historial-cambios?proyecto=<?php echo $pid; ?>">Ver historial de cambios →</a>
</div>

<?php if ($mensaje): ?><div class="alerta <?php echo $mensaje['ok'] ? 'alerta-exito' : 'alerta-error'; ?>"><?php echo s($mensaje['texto']); ?></div><?php endif; ?>

<div class="ia-shell">
    <aside class="ia-rail" aria-label="Herramientas con propuestas">
        <div class="ia-rail-head"><span>Herramientas</span><span class="ia-count"><?php echo count(CentroIA::HERRAMIENTAS); ?></span></div>
        <div class="ia-rail-list">
            <a class="ia-rail-item<?php echo $filtros['herramienta'] === '' ? ' activa' : ''; ?>" href="<?php echo s($url(['herramienta' => ''])); ?>"<?php echo $filtros['herramienta'] === '' ? ' aria-current="page"' : ''; ?>>
                <span class="ia-rail-dot info"></span>
                <span class="ia-rail-txt"><strong>Todas</strong><small>Bandeja completa</small></span>
                <span class="ia-chip ia-chip--info"><?php echo (int)$total; ?></span>
            </a>
            <?php foreach (CentroIA::HERRAMIENTAS as $k => $n): $act = $filtros['herramienta'] === $k; ?>
                <a class="ia-rail-item<?php echo $act ? ' activa' : ''; ?><?php echo empty($conteo[$k]) ? ' deshabilitada' : ''; ?>" href="<?php echo s($url(['herramienta' => $k])); ?>"<?php echo $act ? ' aria-current="page"' : ''; ?>>
                    <span class="ia-rail-dot <?php echo empty($conteo[$k]) ? '' : 'warn'; ?>"></span>
                    <span class="ia-rail-txt"><strong><?php echo s($n); ?></strong><small><?php echo empty($conteo[$k]) ? 'Sin pendientes' : 'Propuestas pendientes'; ?></small></span>
                    <span class="ia-chip <?php echo empty($conteo[$k]) ? '' : 'ia-chip--warn'; ?>"><?php echo (int)$conteo[$k]; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="ia-rail-actions">
            <a class="btn btn-sm btn-ghost" href="#riesgo"><i class="fa-solid fa-shield-halved"></i> Niveles de riesgo</a>
            <a class="btn btn-sm btn-ghost" href="/resumen-ia?proyecto=<?php echo $pid; ?>"><i class="fa-solid fa-gauge-high"></i> Resumen de IA</a>
        </div>
        <section class="ia-card">
            <h3><i class="fa-solid fa-shield-halved"></i> Riesgo</h3>
            <p class="text-muted text-sm"><?php echo (int)$bajo; ?> simples se aceptan por lote · <?php echo (int)$permiso; ?> piden permiso y se revisan una por una.</p>
            <a class="btn btn-sm btn-ghost" href="#riesgo">Configurar niveles →</a>
        </section>
    </aside>

    <main class="ia-lienzo">
        <div class="ia-kpis ia-kpis--5" aria-label="Resumen de propuestas">
            <div class="suite-kpi is-info">
                <span class="suite-kpi-icono"><i class="fa-solid fa-inbox"></i></span>
                <span class="suite-kpi-txt"><small>Pendientes en total</small><strong><?php echo (int)$total; ?></strong><span>Bandeja completa</span></span>
            </div>
            <div class="suite-kpi is-success">
                <span class="suite-kpi-icono"><i class="fa-solid fa-layer-group"></i></span>
                <span class="suite-kpi-txt"><small>Bajo riesgo</small><strong><?php echo (int)$bajo; ?></strong><span>se aceptan por lote</span></span>
            </div>
            <div class="suite-kpi is-warning">
                <span class="suite-kpi-icono"><i class="fa-solid fa-hand"></i></span>
                <span class="suite-kpi-txt"><small>Piden permiso</small><strong><?php echo (int)$permiso; ?></strong><span>siempre una por una</span></span>
            </div>
            <div class="suite-kpi <?php echo (int)$criticas ? 'is-danger' : ''; ?>">
                <span class="suite-kpi-icono"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <span class="suite-kpi-txt"><small>Críticas</small><strong><?php echo (int)$criticas; ?></strong><span>severidad alta o crítica</span></span>
            </div>
            <div class="suite-kpi">
                <span class="suite-kpi-icono"><i class="fa-solid fa-robot"></i></span>
                <span class="suite-kpi-txt"><small>Herramientas activas</small><strong><?php echo (int)$herramientasActivas; ?></strong><span>con propuestas pendientes</span></span>
            </div>
        </div>

        <form method="get" action="/propuestas-ia" class="ia-command ia-command--filtros">
            <input type="hidden" name="proyecto" value="<?php echo $pid; ?>">
            <label>Herramienta <select name="herramienta" onchange="this.form.submit()"><option value="">Todas</option><?php foreach (CentroIA::HERRAMIENTAS as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['herramienta'] === $k ? ' selected' : ''; ?>><?php echo s($n); ?></option><?php endforeach; ?></select></label>
            <label>Severidad <select name="severidad" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($sevNombre as $k => $n): ?><option value="<?php echo $k; ?>"<?php echo $filtros['severidad'] === $k ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></label>
            <label>Detectada por <select name="origen" onchange="this.form.submit()"><option value="">Todas</option><option value="sistema"<?php echo $filtros['origen'] === 'sistema' ? ' selected' : ''; ?>>Sistema</option><option value="ia"<?php echo $filtros['origen'] === 'ia' ? ' selected' : ''; ?>>IA</option></select></label>
            <label>Riesgo <select name="riesgo" onchange="this.form.submit()"><option value="">Todos</option><option value="simple"<?php echo $filtros['riesgo'] === 'simple' ? ' selected' : ''; ?>>Simple</option><option value="permiso"<?php echo $filtros['riesgo'] === 'permiso' ? ' selected' : ''; ?>>Pide permiso</option></select></label>
            <?php if ($partidas): ?><label>Partida <select name="partida" onchange="this.form.submit()"><option value="">Todas</option><?php foreach ($partidas as $cod => $nom): ?><option value="<?php echo s($cod); ?>"<?php echo $filtros['partida'] === (string)$cod ? ' selected' : ''; ?>><?php echo s($cod . ' ' . $nom); ?></option><?php endforeach; ?></select></label><?php endif; ?>
            <input type="search" data-filtra=".inc-lista" placeholder="Buscar propuesta, herramienta, actividad…" aria-label="Buscar propuestas" autocomplete="off">
        </form>

        <?php if ($bajo): ?>
            <div class="card ia-lote" id="lote">
                <div>
                    <strong><i class="fa-solid fa-layer-group"></i> Aprobación por lotes</strong>
                    <p class="text-muted text-sm">Acepta de una vez las propuestas <strong>simples</strong> (renombrar, vincular normativa, recalcular…)<?php echo $filtros['partida'] !== '' ? ' de la partida ' . s($filtros['partida']) : ' que coinciden con los filtros'; ?>. Las que piden permiso (mover fechas, relaciones, avance…) siempre se aceptan una por una.</p>
                </div>
                <button type="button" class="btn btn-primary" id="ia-lote-btn"><i class="fa-solid fa-check-double"></i> <?php echo $filtros['partida'] !== '' ? 'Aceptar las simples de esta partida' : 'Aceptar todas las de bajo riesgo'; ?> (<?php echo (int)$bajo; ?>)</button>
                <span class="inc-msg" id="ia-lote-msg" aria-live="polite"></span>
            </div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="card ia-empty">
                <span class="ia-empty-icono"><i class="fa-solid fa-inbox"></i></span>
                <h3><?php echo $total ? 'Nada con estos filtros' : 'No hay propuestas pendientes'; ?></h3>
                <p class="text-muted">Las propuestas aparecen aquí cuando ejecutas un análisis en la app de escritorio (Herramientas IA) o pulsas «Revisar ahora» en Inconsistencias o en el Análisis de materiales.</p>
                <div class="ia-empty-acciones">
                    <a class="btn btn-primary" href="/inconsistencias?proyecto=<?php echo $pid; ?>">Ir a Inconsistencias</a>
                    <a class="btn btn-ghost" href="/resumen-ia?proyecto=<?php echo $pid; ?>">Resumen de IA</a>
                </div>
            </div>
        <?php else: ?>
            <ul class="inc-lista" <?php echo pag_attr($proyecto); ?>>
                <?php foreach ($items as $x): ?>
                    <li class="card inc-item ia-finding sev-<?php echo s($x['severidad']); ?> est-pendiente" data-riesgo="<?php echo s($x['riesgo']); ?>" data-herramienta="<?php echo s($x['herramienta']); ?>" data-id="<?php echo (int)$x['id']; ?>">
                        <div class="inc-cab ia-finding-head">
                            <span class="ia-sev <?php echo $sevClase[$x['severidad']] ?? ''; ?>"><?php echo s($sevNombre[$x['severidad']]); ?></span>
                            <strong><?php echo s($x['titulo']); ?></strong>
                            <small class="text-muted ia-finding-meta"><?php echo s(CentroIA::HERRAMIENTAS[$x['herramienta']]); ?> · <?php echo s($x['categoria']); ?> · <?php echo $x['origen'] === 'ia' ? 'detectada por la IA' : 'detectada por el sistema'; ?></small>
                        </div>
                        <p><?php echo s($x['descripcion']); ?></p>
                        <div class="inc-solucion ia-solution">
                            <span class="inc-tag ia-tag"><?php echo s($accionNombre[$x['accion']] ?? $x['accion']); ?></span>
                            <span class="ia-risk ia-risk--<?php echo s($x['riesgo']); ?>" title="<?php echo $x['riesgo'] === 'permiso' ? 'Siempre pide tu confirmación y no entra en la aprobación por lotes' : 'Riesgo bajo: se acepta sin confirmación y puede entrar por lote'; ?>"><?php echo s(RiesgoIA::NIVELES[$x['riesgo']]); ?></span>
                            <span><strong>Solución propuesta:</strong> <?php echo s($x['solucion']); ?></span>
                        </div>
                        <div class="inc-botones ia-actions">
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
            <p class="text-muted text-sm">Bandeja completa: <?php echo (int)$total; ?> propuestas. La lista se pagina por bloques.</p>
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
    </main>

    <aside class="ia-inspector" aria-label="Riesgo y reglas de aprobación">
        <details class="ia-card ia-riesgos" id="riesgo"<?php echo $mensaje ? ' open' : ''; ?>>
            <summary><strong><i class="fa-solid fa-shield-halved"></i> Niveles de riesgo</strong> <span class="text-muted text-sm">— qué cambios piden permiso</span></summary>
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

        <section class="ia-card">
            <h3><i class="fa-solid fa-layer-group"></i> Reglas de lote</h3>
            <ul class="ia-lista">
                <li><i class="fa-solid fa-circle-check"></i> Solo se aplican propuestas simples.</li>
                <li><i class="fa-solid fa-hand"></i> Las que mueven el programa se revisan una por una.</li>
                <li><i class="fa-solid fa-clock-rotate-left"></i> Todo queda en la auditoría y se puede revertir.</li>
            </ul>
        </section>

        <section class="ia-card">
            <h3><i class="fa-solid fa-circle-info"></i> Cómo leer</h3>
            <span class="ia-legend">
                <?php foreach ($sevNombre as $k => $n): ?><span><span class="ia-sev <?php echo $sevClase[$k] ?? ''; ?>"><?php echo s($n); ?></span></span><?php endforeach; ?>
            </span>
            <span class="ia-legend">
                <span><span class="ia-risk ia-risk--simple">Simple</span></span>
                <span><span class="ia-risk ia-risk--permiso">Pide permiso</span></span>
            </span>
            <p class="text-muted text-sm">«Simple» se acepta con un clic y puede entrar en el lote. «Pide permiso» siempre exige confirmación individual.</p>
        </section>
    </aside>
</div>


<script>
    // La búsqueda de las fichas se hace sobre la lista (no sobre una tabla):
    // el buscador compartido solo recorre filas de tabla.
    (function () {
        var input = document.querySelector('input[data-filtra=".inc-lista"]');
        var lista = document.querySelector('.inc-lista');
        if (!input || !lista) return;
        var norm = function (t) {
            return String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        };
        input.addEventListener('input', function () {
            var q = norm(input.value.trim());
            lista.querySelectorAll(':scope > .inc-item').forEach(function (li) {
                li.hidden = q !== '' && !norm(li.textContent).includes(q);
            });
        });
    })();
</script>

<div class="ia-mobile-bar">
    <?php if ($bajo): ?><a class="btn btn-primary" href="#lote"><i class="fa-solid fa-check-double"></i> Aprobación por lotes</a><?php endif; ?>
    <a class="btn btn-ghost" href="#riesgo"><i class="fa-solid fa-shield-halved"></i> Niveles de riesgo</a>
</div>

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
